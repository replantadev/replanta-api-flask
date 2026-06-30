<?php
/**
 * Cloudflare onboarding state machine.
 *
 * Consumes the queue in `wp_dominios_reseller_cf_onboarding` (owned by
 * the dominios-reseller plugin) and progresses each row through the
 * canonical flow:
 *
 *   pending      -> create zone in CF, record zone_id + NS         -> pending_ns
 *   pending_ns   -> poll CF zone status + real NS until verified    -> onboarded
 *                   (after N attempts without activation)            -> needs_manual_ns
 *   onboarded    -> apply preset (SSL, HTTPS, Brotli, TLS, AHR)     -> completed | partial
 *   error        -> manual intervention; ignored by the engine
 *   completed    -> terminal
 *
 * Exposes:
 *  - hourly cron `rphub_cf_onboarding_tick` that walks the queue
 *  - AJAX `rphub_cf_retry`         (single domain)
 *  - AJAX `rphub_cf_mark_manual`   (single domain)
 *
 * Requires `rphub_cloudflare_token` (encrypted, account-wide).
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPHUB_CF_Onboarding_Engine {

    const HOOK_TICK             = 'rphub_cf_onboarding_tick';
    const MAX_NS_ATTEMPTS       = 24;   // ~24h hourly polling
    const NS_ATTEMPT_OPT_PREFIX = 'rphub_cf_ns_attempts_';
    const BATCH_SIZE            = 25;
    const PRESET_SETTINGS       = [
        'ssl'                      => 'full',
        'always_use_https'         => 'on',
        'automatic_https_rewrites' => 'on',
        'brotli'                   => 'on',
        'min_tls_version'          => '1.2',
    ];

    private static $instance = null;

    public static function get_instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action(self::HOOK_TICK, [$this, 'tick']);

        if (class_exists('RPHUB_Scheduler')) {
            RPHUB_Scheduler::schedule(self::HOOK_TICK, 'hourly');
        }

        add_action('wp_ajax_rphub_cf_retry',       [$this, 'ajax_retry']);
        add_action('wp_ajax_rphub_cf_mark_manual', [$this, 'ajax_mark_manual']);
        add_action('wp_ajax_rphub_cf_run_tick',    [$this, 'ajax_run_tick']);
    }

    // -------------------------------------------------------------------------
    // Public AJAX handlers
    // -------------------------------------------------------------------------

    public function ajax_retry(): void {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes', 403);
        }
        $domain = sanitize_text_field(wp_unslash($_POST['domain'] ?? ''));
        if ($domain === '') {
            wp_send_json_error('Dominio requerido');
        }
        $row = $this->get_row($domain);
        if (!$row) {
            wp_send_json_error('Dominio no está en la cola');
        }
        // Force a re-attempt regardless of state
        delete_option(self::NS_ATTEMPT_OPT_PREFIX . md5($domain));
        $this->set_state($domain, 'pending', null);
        $this->progress($domain);
        wp_send_json_success(['domain' => $domain, 'state' => $this->get_row($domain)['state'] ?? null]);
    }

    public function ajax_mark_manual(): void {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes', 403);
        }
        $domain = sanitize_text_field(wp_unslash($_POST['domain'] ?? ''));
        if ($domain === '') {
            wp_send_json_error('Dominio requerido');
        }
        $this->set_state($domain, 'needs_manual_ns', 'Marcado como manual por admin');
        wp_send_json_success(['domain' => $domain]);
    }

    public function ajax_run_tick(): void {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes', 403);
        }
        $report = $this->tick();
        wp_send_json_success($report);
    }

    // -------------------------------------------------------------------------
    // Engine
    // -------------------------------------------------------------------------

    /**
     * Walk the onboarding queue and progress each row one step.
     * Returns an array { advanced, errors, skipped } for diagnostics.
     */
    public function tick(): array {
        $report = ['advanced' => 0, 'errors' => 0, 'skipped' => 0, 'rows' => []];

        if ($this->get_token() === '') {
            $report['skipped']++;
            $report['rows'][] = ['domain' => '-', 'note' => 'CF token no configurado'];
            return $report;
        }

        $rows = $this->get_queue_rows();
        foreach ($rows as $row) {
            $domain = $row['primary_domain'] ?? '';
            if ($domain === '') {
                $report['skipped']++;
                continue;
            }
            try {
                $advanced = $this->progress($domain);
                if ($advanced === null) {
                    $report['skipped']++;
                } elseif ($advanced === false) {
                    $report['errors']++;
                } else {
                    $report['advanced']++;
                }
                $current = $this->get_row($domain);
                $report['rows'][] = [
                    'domain' => $domain,
                    'state'  => $current['state'] ?? null,
                    'error'  => $current['last_error'] ?? null,
                ];
            } catch (\Throwable $e) {
                $this->set_state($domain, 'error', 'Excepción: ' . $e->getMessage());
                $report['errors']++;
            }
        }
        return $report;
    }

    /**
     * Advance a single domain by one transition.
     * Returns true if state advanced, false on error, null if skipped (terminal).
     */
    public function progress(string $domain): ?bool {
        $row = $this->get_row($domain);
        if (!$row) {
            return null;
        }
        $state = $row['state'] ?? '';

        switch ($state) {
            case 'pending':
            case 'running':
                return $this->step_create_zone($domain, $row);

            case 'pending_ns':
                return $this->step_verify_ns($domain, $row);

            case 'onboarded':
                return $this->step_apply_preset($domain, $row);

            case 'error':
            case 'failed':
            case 'partial':
            case 'needs_manual_ns':
            case 'completed':
            case 'none':
            default:
                return null;
        }
    }

    // -------------------------------------------------------------------------
    // Transitions
    // -------------------------------------------------------------------------

    private function step_create_zone(string $domain, array $row): bool {
        if (!empty($row['zone_id'])) {
            $this->set_state($domain, 'pending_ns', null);
            return true;
        }

        $existing = $this->cf_find_zone($domain);
        if (is_wp_error($existing)) {
            $this->set_state($domain, 'error', 'cf_find_zone: ' . $existing->get_error_message());
            return false;
        }

        if ($existing) {
            $nameservers = $existing['name_servers'] ?? [];
            $this->update_zone_and_ns($domain, $existing['id'], $nameservers);
            $ns_check = $this->verify_domain_ns($domain, $nameservers);
            if (($existing['status'] ?? '') === 'active' && $ns_check['verified']) {
                $this->set_state($domain, 'onboarded', null);
                $this->mark_ns_verified($domain);
                $this->notify_state_change($domain, 'onboarded', 'Zona ya existia en Cloudflare y los NS estan verificados');
                return true;
            }
            $next = 'pending_ns';
            $this->set_state($domain, $next, $ns_check['message']);
            $this->notify_state_change($domain, $next, 'Zona ya existía en Cloudflare');
            return true;
        }

        $created = $this->cf_create_zone($domain);
        if (is_wp_error($created)) {
            $this->set_state($domain, 'error', 'cf_create_zone: ' . $created->get_error_message());
            return false;
        }

        $this->update_zone_and_ns($domain, $created['id'], $created['name_servers'] ?? []);
        $this->set_state($domain, 'pending_ns', null);
        $this->notify_state_change($domain, 'pending_ns', 'Zona creada en CF. NS asignados.');
        return true;
    }

    private function step_verify_ns(string $domain, array $row): bool {
        $zone_id = $row['zone_id'] ?? '';
        if ($zone_id === '') {
            $this->set_state($domain, 'pending', 'pending_ns sin zone_id; reintentando creación');
            return false;
        }

        $attempts_key = self::NS_ATTEMPT_OPT_PREFIX . md5($domain);
        $attempts     = intval(get_option($attempts_key, 0));

        $info = $this->cf_zone_info($zone_id);
        if (is_wp_error($info)) {
            update_option($attempts_key, $attempts + 1, false);
            $this->set_state($domain, 'pending_ns', 'verify_ns: ' . $info->get_error_message());
            return false;
        }

        $expected_ns = $this->decode_nameservers($row['nameservers'] ?? '');
        if (empty($expected_ns) && !empty($info['name_servers'])) {
            $expected_ns = $info['name_servers'];
            $this->update_zone_and_ns($domain, $zone_id, $expected_ns);
        }
        $ns_check = $this->verify_domain_ns($domain, $expected_ns);

        if (($info['status'] ?? '') === 'active' && $ns_check['verified']) {
            delete_option($attempts_key);
            $this->set_state($domain, 'onboarded', null);
            $this->mark_ns_verified($domain);
            $this->notify_state_change($domain, 'onboarded', 'NS verificados. Aplicando preset.');
            return true;
        }

        $attempts++;
        update_option($attempts_key, $attempts, false);

        if ($attempts >= self::MAX_NS_ATTEMPTS) {
            $this->set_state($domain, 'needs_manual_ns', sprintf(
                'NS no propagados tras %d intentos. Verificar registrador.',
                $attempts
            ));
            $this->notify_state_change($domain, 'needs_manual_ns', 'NS no propagados — requiere intervención manual');
            return false;
        }

        $message = $ns_check['verified']
            ? 'Cloudflare aun no marca la zona como active.'
            : $ns_check['message'];
        $this->set_state($domain, 'pending_ns', $message);

        return false; // not advanced, will retry next tick
    }

    private function step_apply_preset(string $domain, array $row): bool {
        $zone_id = $row['zone_id'] ?? '';
        if ($zone_id === '') {
            $this->set_state($domain, 'error', 'apply_preset sin zone_id');
            return false;
        }

        $failed = [];
        foreach (self::PRESET_SETTINGS as $key => $value) {
            $res = $this->cf_patch_setting($zone_id, $key, $value);
            if (is_wp_error($res)) {
                $failed[] = $key . ': ' . $res->get_error_message();
            }
        }

        if (empty($failed)) {
            $this->set_state($domain, 'completed', null);
            $this->mark_applied($domain);
            $this->notify_state_change($domain, 'completed', 'Onboarding completado con preset aplicado');
            return true;
        }

        $this->set_state($domain, 'partial', 'Settings con error: ' . implode(' | ', $failed));
        $this->mark_applied($domain);
        $this->notify_state_change($domain, 'partial', 'Onboarding parcial — revisar settings fallidos');
        return false;
    }

    // -------------------------------------------------------------------------
    // Cloudflare API
    // -------------------------------------------------------------------------

    private function cf_find_zone(string $domain) {
        $resp = $this->cf_request('GET', '/zones?name=' . rawurlencode($domain) . '&status=active,pending,initializing,moved,deactivated&per_page=1');
        if (is_wp_error($resp)) {
            return $resp;
        }
        return $resp['result'][0] ?? null;
    }

    private function cf_create_zone(string $domain) {
        $account_id = $this->get_account_id();
        $body = ['name' => $domain, 'jump_start' => true];
        if ($account_id !== '') {
            $body['account'] = ['id' => $account_id];
        }
        $resp = $this->cf_request('POST', '/zones', $body);
        if (is_wp_error($resp)) {
            return $resp;
        }
        return $resp['result'] ?? null;
    }

    private function cf_zone_info(string $zone_id) {
        $resp = $this->cf_request('GET', '/zones/' . rawurlencode($zone_id));
        if (is_wp_error($resp)) {
            return $resp;
        }
        return $resp['result'] ?? null;
    }

    private function cf_patch_setting(string $zone_id, string $setting, $value) {
        return $this->cf_request(
            'PATCH',
            '/zones/' . rawurlencode($zone_id) . '/settings/' . rawurlencode($setting),
            ['value' => $value]
        );
    }

    private function cf_request(string $method, string $path, array $body = null) {
        $token = $this->get_token();
        if ($token === '') {
            return new WP_Error('cf_no_token', 'CF token no configurado');
        }
        $args = [
            'method'  => $method,
            'timeout' => 25,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
        ];
        if ($body !== null) {
            $args['body'] = wp_json_encode($body);
        }
        $resp = wp_remote_request('https://api.cloudflare.com/client/v4' . $path, $args);
        if (is_wp_error($resp)) {
            return $resp;
        }
        $code = wp_remote_retrieve_response_code($resp);
        $json = json_decode(wp_remote_retrieve_body($resp), true);
        $ok   = is_array($json) ? !empty($json['success']) : false;

        if ($code >= 200 && $code < 300 && $ok) {
            return $json;
        }

        $msg = 'HTTP ' . $code;
        if (is_array($json) && !empty($json['errors'][0]['message'])) {
            $msg .= ' — ' . $json['errors'][0]['message'];
        }
        return new WP_Error('cf_api_error', $msg, ['response' => $json]);
    }

    private function decode_nameservers($raw): array {
        if (is_array($raw)) {
            $items = $raw;
        } else {
            $items = json_decode((string) $raw, true);
        }
        if (!is_array($items)) {
            return [];
        }
        return array_values(array_filter(array_map([$this, 'normalize_nameserver'], $items)));
    }

    private function verify_domain_ns(string $domain, array $expected_ns): array {
        $expected_ns = array_values(array_filter(array_map([$this, 'normalize_nameserver'], $expected_ns)));
        if (empty($expected_ns)) {
            return [
                'verified' => false,
                'message'  => 'Cloudflare no ha devuelto nameservers para verificar.',
            ];
        }

        $records = function_exists('dns_get_record') ? @dns_get_record($domain, DNS_NS) : false;
        if ($records === false || empty($records)) {
            return [
                'verified' => false,
                'message'  => 'No se pudieron resolver los NS actuales del dominio.',
            ];
        }

        $actual_ns = [];
        foreach ($records as $record) {
            if (!empty($record['target'])) {
                $actual_ns[] = $this->normalize_nameserver($record['target']);
            }
        }
        $actual_ns = array_values(array_filter(array_unique($actual_ns)));
        $matches = array_intersect($actual_ns, $expected_ns);
        $verified = count($matches) >= min(2, count($expected_ns));

        return [
            'verified' => $verified,
            'message'  => $verified
                ? 'Nameservers verificados.'
                : 'NS actuales no coinciden con los nameservers asignados por Cloudflare.',
        ];
    }

    private function normalize_nameserver($value): string {
        return strtolower(rtrim(trim((string) $value), '. '));
    }

    // -------------------------------------------------------------------------
    // DB / state helpers
    // -------------------------------------------------------------------------

    private function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'dominios_reseller_cf_onboarding';
    }

    private function table_exists(): bool {
        global $wpdb;
        $t = $this->table();
        return (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t));
    }

    private function get_queue_rows(): array {
        global $wpdb;
        if (!$this->table_exists()) {
            return [];
        }
        $sql = sprintf(
            "SELECT primary_domain, zone_id, state, nameservers, last_error
             FROM %s
             WHERE state IN ('pending','running','pending_ns','onboarded')
             ORDER BY updated_at ASC
             LIMIT %d",
            $this->table(),
            self::BATCH_SIZE
        );
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    private function get_row(string $domain): ?array {
        global $wpdb;
        if (!$this->table_exists()) {
            return null;
        }
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT primary_domain, zone_id, state, nameservers, last_error
                 FROM ' . $this->table() . '
                 WHERE primary_domain = %s
                 LIMIT 1',
                $domain
            ),
            ARRAY_A
        );
        return $row ?: null;
    }

    private function set_state(string $domain, string $state, ?string $error): void {
        if (class_exists('Dominios_Reseller_Onboarding_DB')
            && method_exists('Dominios_Reseller_Onboarding_DB', 'update_state')) {
            Dominios_Reseller_Onboarding_DB::update_state($domain, $state, $error);
            return;
        }
        global $wpdb;
        if (!$this->table_exists()) {
            return;
        }
        $wpdb->update(
            $this->table(),
            [
                'state'      => $state,
                'last_error' => $error,
                'updated_at' => current_time('mysql'),
            ],
            ['primary_domain' => $domain],
            ['%s', '%s', '%s'],
            ['%s']
        );
    }

    private function update_zone_and_ns(string $domain, string $zone_id, array $nameservers): void {
        global $wpdb;
        if (!$this->table_exists()) {
            return;
        }
        $wpdb->update(
            $this->table(),
            [
                'zone_id'     => $zone_id,
                'nameservers' => wp_json_encode($nameservers),
                'updated_at'  => current_time('mysql'),
            ],
            ['primary_domain' => $domain],
            ['%s', '%s', '%s'],
            ['%s']
        );
    }

    private function mark_ns_verified(string $domain): void {
        global $wpdb;
        if (!$this->table_exists()) {
            return;
        }
        $wpdb->update(
            $this->table(),
            ['ns_verified' => 1, 'updated_at' => current_time('mysql')],
            ['primary_domain' => $domain],
            ['%d', '%s'],
            ['%s']
        );
    }

    private function mark_applied(string $domain): void {
        global $wpdb;
        if (!$this->table_exists()) {
            return;
        }
        $wpdb->update(
            $this->table(),
            ['applied_at' => current_time('mysql'), 'updated_at' => current_time('mysql')],
            ['primary_domain' => $domain],
            ['%s', '%s'],
            ['%s']
        );
    }

    // -------------------------------------------------------------------------
    // Config + notifications
    // -------------------------------------------------------------------------

    private function get_token(): string {
        $raw = get_option('rphub_cloudflare_token', '');
        if ($raw === '') {
            $raw = get_option('rphub_cloudflare_api_key', '');
        }
        if ($raw === '') {
            return '';
        }
        return class_exists('RPHUB_Crypto') ? (string) RPHUB_Crypto::decrypt($raw) : (string) $raw;
    }

    private function get_account_id(): string {
        $settings = get_option('rphub_settings', []);
        return (string) ($settings['cloudflare_account_id'] ?? '');
    }

    private function notify_state_change(string $domain, string $state, string $message): void {
        if (!class_exists('RPHUB_Alerting')) {
            return;
        }
        $level = in_array($state, ['error', 'needs_manual_ns'], true)
            ? RPHUB_Alerting::LEVEL_ERROR
            : (in_array($state, ['partial'], true) ? RPHUB_Alerting::LEVEL_WARNING : RPHUB_Alerting::LEVEL_INFO);

        RPHUB_Alerting::notify(
            $level,
            'CF onboarding: ' . $domain,
            $message,
            ['domain' => $domain, 'state' => $state]
        );
    }
}
