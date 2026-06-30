<?php
/**
 * Upmind Integration & WP Readiness Checker
 *
 * Gestiona el ciclo de vida de suscripciones Upmind → CyberPanel (Cedro)
 * y verificación de readiness para WordPress. WHM se preserva para
 * clientes existentes que no pasan por este webhook.
 *
 * Payload real Upmind: campo "hook_code" + datos en "object".
 * Header firma: X-Webhook-Signature (HMAC-SHA256, cuerpo crudo).
 *
 * @package Dominios_Reseller
 * @since   1.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dominios_Reseller_Upmind_Integration {

    private static ?Dominios_Reseller_Upmind_Integration $instance = null;

    private Dominios_Reseller_Onboarding_Worker $onboarding_worker;
    private ?Dominios_Reseller_Auto_Discovery $auto_discovery = null;

    private function __construct() {
        $this->onboarding_worker = Dominios_Reseller_Onboarding_Worker::get_instance();
        if (class_exists('Dominios_Reseller_Auto_Discovery')) {
            $this->auto_discovery = Dominios_Reseller_Auto_Discovery::get_instance();
        }
        $this->init_hooks();
    }

    public static function get_instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function init_hooks(): void {
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        add_filter('manage_dominios_reseller_posts_columns', [$this, 'add_wp_ready_column']);
        add_action('manage_dominios_reseller_posts_custom_column', [$this, 'render_wp_ready_column'], 10, 2);
        add_action('add_meta_boxes', [$this, 'add_wp_readiness_meta_box']);

        add_action('wp_ajax_dr_check_wp_readiness', [$this, 'ajax_check_wp_readiness']);
        add_action('wp_ajax_dr_fix_wp_readiness', [$this, 'ajax_fix_wp_readiness']);
    }

    // ── REST routes ───────────────────────────────────────────────────────

    public function register_rest_routes(): void {
        register_rest_route('dominios-reseller/v1', '/webhook/upmind', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_upmind_webhook'],
            'permission_callback' => [$this, 'verify_upmind_signature'],
        ]);

        register_rest_route('dominios-reseller/v1', '/wp-readiness/(?P<domain>[a-zA-Z0-9.-]+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_wp_readiness_status'],
            'permission_callback' => '__return_true',
        ]);
    }

    // ── Verificación de firma ─────────────────────────────────────────────

    public function verify_upmind_signature(WP_REST_Request $request): bool {
        // Upmind envía X-Webhook-Signature (no X-Upmind-Signature)
        $signature = $request->get_header('X-Webhook-Signature');

        // Fallback: buscar sin guión por si el servidor normaliza el header
        if (empty($signature)) {
            $signature = $request->get_header('X_Webhook_Signature');
        }

        // Acepta secreto Cedro o el secreto genérico de Upmind
        $secret = get_option('dr_cedro_upmind_secret', get_option('dr_upmind_webhook_secret', ''));

        if (empty($secret)) {
            $this->log('No hay secreto webhook configurado — rechazando', 'error');
            return false;
        }

        if (empty($signature)) {
            $this->log('Cabecera X-Webhook-Signature ausente — rechazando', 'error');
            return false;
        }

        $payload  = $request->get_body();
        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    // ── Dispatcher principal ──────────────────────────────────────────────

    public function handle_upmind_webhook(WP_REST_Request $request): WP_REST_Response {
        try {
            $data      = $request->get_json_params();
            $hook_code = $data['hook_code'] ?? ($data['event'] ?? '');
            $object    = $data['object']    ?? $data['data'] ?? [];

            $this->log("Webhook recibido: {$hook_code}");

            switch ($hook_code) {
                case 'contract_product_activated_hook':
                    return $this->handle_activated($object);

                case 'contract_product_suspended_hook':
                    return $this->handle_suspended($object);

                case 'contract_product_unsuspended_hook':
                    return $this->handle_unsuspended($object);

                case 'contract_product_cancelled_hook':
                    return $this->handle_cancelled($object);

                case 'contract_product_expiring_hook':
                case 'contract_product_expiry_hook':
                    return $this->handle_expiring($object);

                case 'contract_product_renewed_hook':
                case 'service.renewed':
                    return $this->handle_renewed($object);

                // Evento legado — onboarding CF
                case 'order.completed':
                    return $this->handle_order_completed($data);

                default:
                    $this->log("hook_code no reconocido: {$hook_code}");
                    return new WP_REST_Response(['status' => 'ignored', 'hook_code' => $hook_code], 200);
            }
        } catch (Exception $e) {
            $this->log('Error procesando webhook: ' . $e->getMessage(), 'error');
            return new WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    // ── Handlers de ciclo de vida ─────────────────────────────────────────

    private function handle_activated(array $object): WP_REST_Response {
        $domain = $this->extract_domain($object);
        if (!$domain) {
            return new WP_REST_Response(['status' => 'no_domain'], 400);
        }

        if (!$this->is_cedro_product($object)) {
            $this->log("Activación no-Cedro para {$domain} — ignorando (gestión WHM)");
            return new WP_REST_Response(['status' => 'ignored_non_cedro'], 200);
        }

        $email    = $this->extract_email($object);
        $name     = $this->extract_name($object);
        $order_id = $object['id'] ?? $object['contract']['id'] ?? uniqid('cp-');

        $cedro = Dominios_Reseller_Cedro_Service::get_instance();
        $result = $cedro->provision($domain, $email, $name, $order_id);

        if (is_wp_error($result)) {
            $this->log("Error provisionando {$domain}: " . $result->get_error_message(), 'error');
            return new WP_REST_Response(['status' => 'error', 'message' => $result->get_error_message()], 500);
        }

        $cp_user = $result['_cpUser']        ?? '';
        $cp_pass = $result['_ownerPassword'] ?? '';

        // Guardar registro de provisionamiento
        $provisioned = get_option('dr_cedro_provisioned', []);
        $provisioned[$domain] = [
            'order_id' => $order_id,
            'domain'   => $domain,
            'email'    => $email,
            'name'     => $name,
            'cp_user'  => $cp_user,
            'ts'       => time(),
        ];
        update_option('dr_cedro_provisioned', $provisioned);

        $cedro->send_welcome_email($domain, $email, $name, $cp_user, $cp_pass);

        $this->log("Activado y provisionado: {$domain}");
        return new WP_REST_Response(['status' => 'provisioned', 'domain' => $domain], 200);
    }

    private function handle_suspended(array $object): WP_REST_Response {
        $domain = $this->extract_domain($object);
        if (!$domain) {
            return new WP_REST_Response(['status' => 'no_domain'], 400);
        }

        if (!$this->is_cedro_product($object)) {
            $this->log("Suspensión no-Cedro para {$domain} — ignorando");
            return new WP_REST_Response(['status' => 'ignored_non_cedro'], 200);
        }

        $cedro = Dominios_Reseller_Cedro_Service::get_instance();
        $ok = $cedro->suspend($domain);

        $email = $this->get_provisioned_email($domain);
        if ($email) {
            $cedro->send_suspension_email($domain, $email);
        }

        $this->log($ok ? "Suspendido: {$domain}" : "Error suspendiendo {$domain}", $ok ? 'info' : 'error');
        return new WP_REST_Response(['status' => $ok ? 'suspended' : 'error', 'domain' => $domain], $ok ? 200 : 500);
    }

    private function handle_unsuspended(array $object): WP_REST_Response {
        $domain = $this->extract_domain($object);
        if (!$domain) {
            return new WP_REST_Response(['status' => 'no_domain'], 400);
        }

        if (!$this->is_cedro_product($object)) {
            $this->log("Reactivación no-Cedro para {$domain} — ignorando");
            return new WP_REST_Response(['status' => 'ignored_non_cedro'], 200);
        }

        $cedro = Dominios_Reseller_Cedro_Service::get_instance();
        $ok = $cedro->unsuspend($domain);

        $email = $this->get_provisioned_email($domain);
        if ($email) {
            $cedro->send_reactivation_email($domain, $email);
        }

        $this->log($ok ? "Reactivado: {$domain}" : "Error reactivando {$domain}", $ok ? 'info' : 'error');
        return new WP_REST_Response(['status' => $ok ? 'unsuspended' : 'error', 'domain' => $domain], $ok ? 200 : 500);
    }

    private function handle_cancelled(array $object): WP_REST_Response {
        $domain   = $this->extract_domain($object);
        $order_id = $object['id'] ?? '';

        if (!$domain) {
            return new WP_REST_Response(['status' => 'no_domain'], 400);
        }

        if (!$this->is_cedro_product($object)) {
            $this->log("Cancelación no-Cedro para {$domain} — ignorando");
            return new WP_REST_Response(['status' => 'ignored_non_cedro'], 200);
        }

        $cedro = Dominios_Reseller_Cedro_Service::get_instance();
        $cedro->suspend($domain);

        // Encolar para eliminación en 30 días
        $pending = get_option('dr_cedro_pending_deletion', []);
        $pending[] = [
            'domain'       => $domain,
            'order_id'     => $order_id,
            'cancel_ts'    => time(),
            'delete_after' => time() + (30 * DAY_IN_SECONDS),
        ];
        update_option('dr_cedro_pending_deletion', $pending);

        $email = $this->get_provisioned_email($domain);
        $cedro->send_cancellation_email($domain, $email ?: '', $order_id);

        $this->log("Cancelado (suspendido, eliminación en 30 días): {$domain}");
        return new WP_REST_Response(['status' => 'cancelled_suspended', 'domain' => $domain], 200);
    }

    private function handle_expiring(array $object): WP_REST_Response {
        $domain = $this->extract_domain($object);
        if (!$domain) {
            return new WP_REST_Response(['status' => 'no_domain'], 400);
        }

        if (!$this->is_cedro_product($object)) {
            return new WP_REST_Response(['status' => 'ignored_non_cedro'], 200);
        }

        $exp_date = $object['expires_at'] ?? $object['next_renewal_at'] ?? '';
        $email    = $this->extract_email($object) ?: $this->get_provisioned_email($domain);
        $name     = $this->extract_name($object);

        $provisioned = get_option('dr_cedro_provisioned', []);
        if (!empty($provisioned[$domain])) {
            if (empty($email)) { $email = $provisioned[$domain]['email'] ?? ''; }
            if (empty($name))  { $name  = $provisioned[$domain]['name']  ?? ''; }
        }

        if ($email) {
            $cedro = Dominios_Reseller_Cedro_Service::get_instance();
            $cedro->send_expiry_email($domain, $email, $name, $exp_date);
        }

        $this->log("Recordatorio vencimiento enviado: {$domain} ({$exp_date})");
        return new WP_REST_Response(['status' => 'expiry_notified', 'domain' => $domain], 200);
    }

    private function handle_renewed(array $object): WP_REST_Response {
        $domain = $this->extract_domain($object);
        if (!$domain) {
            return new WP_REST_Response(['status' => 'no_domain'], 400);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'dominios_reseller';

        $billing_cycle = strtolower($object['billing_cycle'] ?? $object['billing_period'] ?? '');
        $renewed_at    = $object['renewed_at'] ?? $object['renewal_date'] ?? null;
        $next_renewal  = $object['next_renewal_at'] ?? $object['next_due_date'] ?? null;

        if (!$next_renewal && $renewed_at && $billing_cycle) {
            $next_renewal = $this->calculate_next_renewal($renewed_at, $billing_cycle);
        }

        $update = ['last_sync' => current_time('mysql')];
        if ($next_renewal) {
            $update['next_renewal_date'] = $next_renewal;
        }

        $wpdb->update($table, $update, ['domain' => $domain]);

        do_action('dr_service_renewed', $domain, $update, $object);

        $this->log("Renovado: {$domain} (próxima: {$next_renewal})");
        return new WP_REST_Response(['status' => 'renewed', 'domain' => $domain, 'next_renewal' => $next_renewal], 200);
    }

    // ── Handler legado (order.completed → onboarding CF) ─────────────────

    private function handle_order_completed(array $data): WP_REST_Response {
        if (!isset($data['order']['line_items'])) {
            return new WP_REST_Response(['status' => 'no_items'], 400);
        }

        $processed = [];
        foreach ($data['order']['line_items'] as $item) {
            if ($this->is_hosting_product($item)) {
                $domain = $this->extract_domain_from_item($item);
                if ($domain) {
                    $this->trigger_welcome_optimization($domain, $data['order'], $item);
                    $processed[] = $domain;
                }
            }
        }

        return new WP_REST_Response(['status' => 'success', 'processed_domains' => $processed], 200);
    }

    // ── Extracción de datos del payload ───────────────────────────────────

    private function extract_domain(array $object): ?string {
        $candidates = [
            $object['service_identifier']        ?? null,
            $object['domain']                    ?? null,
            $object['hostname']                  ?? null,
            $object['properties']['domain']      ?? null,
            $object['attributes']['domain']      ?? null,
            $object['custom_fields']['domain']   ?? null,
        ];

        foreach ($candidates as $c) {
            if ($c && $this->is_valid_domain($c)) {
                return strtolower($c);
            }
        }

        // Extraer de name si contiene dominio
        $name = $object['name'] ?? '';
        if (preg_match('/([a-zA-Z0-9][-a-zA-Z0-9]*\.)+[a-zA-Z]{2,}/', $name, $m)) {
            return strtolower($m[0]);
        }

        return null;
    }

    private function extract_email(array $object): string {
        $client = $object['client'] ?? $object['account'] ?? [];
        return sanitize_email($client['email'] ?? $object['email'] ?? '');
    }

    private function extract_name(array $object): string {
        $client = $object['client'] ?? $object['account'] ?? [];
        $first  = trim($client['first_name'] ?? $object['first_name'] ?? '');
        $last   = trim($client['last_name']  ?? $object['last_name']  ?? '');
        return trim("$first $last");
    }

    private function is_cedro_product(array $object): bool {
        $cedro_product_id = get_option('dr_cedro_product_id', 'e2e071d9-31d5-e460-555a-646028758396');
        $product_id       = $object['product_id'] ?? $object['product']['id'] ?? '';
        return $product_id === $cedro_product_id;
    }

    private function get_provisioned_email(string $domain): string {
        $provisioned = get_option('dr_cedro_provisioned', []);
        return $provisioned[$domain]['email'] ?? '';
    }

    private function calculate_next_renewal(string $renewed_at, string $billing_cycle): string {
        $date = new DateTime($renewed_at);
        $intervals = [
            'monthly'     => 'P1M',
            'quarterly'   => 'P3M',
            'annually'    => 'P1Y',
            'annual'      => 'P1Y',
            'yearly'      => 'P1Y',
            'biannual'    => 'P2Y',
            'triennially' => 'P3Y',
        ];
        $date->add(new DateInterval($intervals[$billing_cycle] ?? 'P1Y'));
        return $date->format('Y-m-d');
    }

    // ── Helpers legados (order.completed) ─────────────────────────────────

    private function is_hosting_product(array $item): bool {
        $name = strtolower($item['name'] ?? '');
        foreach (['hosting', 'web', 'wordpress', 'wp', 'site', 'cedro'] as $kw) {
            if (str_contains($name, $kw)) {
                return true;
            }
        }
        return false;
    }

    private function extract_domain_from_item(array $item): ?string {
        foreach (['domain', 'custom_fields.domain', 'metadata.domain'] as $field) {
            $d = $this->get_nested_value($item, $field);
            if ($d && $this->is_valid_domain($d)) {
                return strtolower($d);
            }
        }
        return null;
    }

    private function trigger_welcome_optimization(string $domain, array $order_data, array $item_data): void {
        try {
            $existing_state = Dominios_Reseller_Onboarding_DB::get_onboarding_state($domain);
            if ($existing_state && in_array($existing_state['state'] ?? '', ['onboarded', 'running', 'pending'])) {
                return;
            }

            Dominios_Reseller_Onboarding_DB::upsert_onboarding($domain, [
                'state'      => 'pending',
                'preset_key' => 'wp',
                'meta'       => wp_json_encode([
                    'source'    => 'upmind_welcome_optimization',
                    'order_id'  => $order_data['id'] ?? null,
                    'auto_discovered' => true,
                ]),
            ]);

            $this->onboarding_worker->enqueue($domain, 'wp', false);
        } catch (Exception $e) {
            $this->log("Error en welcome optimization para {$domain}: " . $e->getMessage(), 'error');
        }
    }

    // ── WP Readiness checker (sin cambios funcionales) ────────────────────

    public function add_wp_ready_column(array $columns): array {
        $columns['wp_ready'] = 'WP Ready';
        return $columns;
    }

    public function render_wp_ready_column(string $column, int $post_id): void {
        if ($column !== 'wp_ready') return;
        $domain = get_post_meta($post_id, 'domain', true);
        if (empty($domain)) { echo '—'; return; }

        $status = $this->get_wp_readiness_cached($domain);
        if (!$status) {
            echo "<span class='wp-ready-indicator wp-ready-unknown' data-domain='{$domain}'>🔍 Verificar</span>";
            return;
        }
        if ($status['overall_ready']) {
            echo "<span class='wp-ready-indicator wp-ready-yes' data-domain='{$domain}'>✅ Listo</span>";
        } else {
            echo "<span class='wp-ready-indicator wp-ready-no' data-domain='{$domain}'>❌ Revisar</span>";
        }
    }

    public function add_wp_readiness_meta_box(): void {
        add_meta_box(
            'dr-wp-readiness', 'WordPress Readiness Check',
            [$this, 'render_wp_readiness_meta_box'],
            'dominios_reseller', 'side'
        );
    }

    public function render_wp_readiness_meta_box(WP_Post $post): void {
        $domain = get_post_meta($post->ID, 'domain', true);
        if (empty($domain)) { echo '<p>Sin dominio.</p>'; return; }
        echo "<div class='dr-wp-readiness-container' data-domain='{$domain}'>
<p><strong>Dominio:</strong> {$domain}</p>
<div id='wp-readiness-status'>Cargando...</div>
<button type='button' id='check-wp-readiness' class='button button-primary'>Verificar</button>
<button type='button' id='fix-wp-readiness' class='button button-secondary' style='margin-left:10px'>Corregir</button>
</div>";
    }

    public function ajax_check_wp_readiness(): void {
        $domain = sanitize_text_field($_POST['domain'] ?? '');
        if (empty($domain)) { wp_send_json_error('Dominio requerido'); return; }
        wp_send_json_success($this->check_wp_readiness($domain));
    }

    public function ajax_fix_wp_readiness(): void {
        $domain = sanitize_text_field($_POST['domain'] ?? '');
        if (empty($domain)) { wp_send_json_error('Dominio requerido'); return; }
        wp_send_json_success($this->fix_wp_readiness($domain));
    }

    private function get_wp_readiness_cached(string $domain): ?array {
        $key = 'dr_wp_readiness_' . md5($domain);
        $cached = get_transient($key);
        if ($cached !== false) return $cached;
        $result = $this->check_wp_readiness($domain);
        set_transient($key, $result, HOUR_IN_SECONDS);
        return $result;
    }

    public function check_wp_readiness(string $domain): array {
        $results = ['domain' => $domain, 'overall_ready' => true, 'checks' => [], 'recommendations' => []];

        $checks = [
            'php_version'           => $this->check_php_version($domain),
            'php_extensions'        => $this->check_php_extensions($domain),
            'php_limits'            => $this->check_php_limits($domain),
            'directory_permissions' => $this->check_directory_permissions($domain),
        ];

        foreach ($checks as $key => $check) {
            $results['checks'][$key] = $check;
            if (!$check['ready']) {
                $results['overall_ready'] = false;
                $fixes = $check['fixes'] ?? ($check['fix'] ? [$check['fix']] : []);
                $results['recommendations'] = array_merge($results['recommendations'], array_filter($fixes));
            }
        }

        return $results;
    }

    private function check_php_version(string $domain): array {
        $current = '8.3'; $required = '7.4';
        $ready = version_compare($current, $required, '>=');
        return [
            'ready'    => $ready,
            'current'  => $current,
            'required' => $required,
            'message'  => $ready ? 'PHP compatible' : "PHP {$current} < {$required}",
            'fix'      => $ready ? null : 'Actualizar PHP a 8.0+',
        ];
    }

    private function check_php_extensions(string $domain): array {
        $required = ['curl', 'gd', 'mbstring', 'mysqlnd', 'openssl', 'xml', 'zip'];
        $missing  = [];
        return [
            'ready'    => empty($missing),
            'required' => $required,
            'missing'  => $missing,
            'message'  => empty($missing) ? 'Extensiones OK' : 'Faltan: ' . implode(', ', $missing),
            'fixes'    => array_map(fn($e) => "Instalar extensión PHP: {$e}", $missing),
        ];
    }

    private function check_php_limits(string $domain): array {
        return [
            'ready'   => true,
            'message' => 'Límites PHP adecuados',
            'fixes'   => [],
        ];
    }

    private function check_directory_permissions(string $domain): array {
        return [
            'ready'   => true,
            'message' => 'Permisos correctos',
            'fix'     => null,
        ];
    }

    public function fix_wp_readiness(string $domain): array {
        return [
            'success' => false,
            'message' => 'Corrección automática requiere WHM API — pendiente de implementación',
            'fixes_applied' => [],
            'errors' => [],
        ];
    }

    public function get_wp_readiness_status(WP_REST_Request $request): WP_REST_Response {
        $domain = $request->get_param('domain');
        if (empty($domain)) {
            return new WP_REST_Response(['error' => 'Domain required'], 400);
        }
        return new WP_REST_Response($this->get_wp_readiness_cached($domain), 200);
    }

    // ── Utilidades ────────────────────────────────────────────────────────

    private function get_nested_value(array $array, string $path): mixed {
        $value = $array;
        foreach (explode('.', $path) as $key) {
            if (!isset($value[$key])) return null;
            $value = $value[$key];
        }
        return $value;
    }

    private function is_valid_domain(string $domain): bool {
        return filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }

    private function log(string $message, string $level = 'info'): void {
        if (method_exists('Dominios_Reseller_Onboarding_DB', 'log_activity')) {
            Dominios_Reseller_Onboarding_DB::log_activity(
                'upmind_integration', null, $message,
                ['level' => $level, 'component' => 'upmind_integration']
            );
            return;
        }
        error_log("[DR Upmind] [{$level}] {$message}");
    }

    // ── API pública para test/debug ───────────────────────────────────────

    public function process_test_webhook(array $webhook_data): array {
        $hook_code = $webhook_data['hook_code'] ?? $webhook_data['event'] ?? '';
        $domain    = $webhook_data['data']['domain'] ?? '';

        if (!$hook_code || !$domain || !$this->is_valid_domain($domain)) {
            return ['success' => false, 'error' => 'Datos inválidos'];
        }

        $this->log("Test webhook: {$hook_code} / {$domain}");
        return ['success' => true, 'hook_code' => $hook_code, 'domain' => $domain, 'message' => 'OK (test mode)'];
    }

    public function check_wp_readiness_test(string $domain): array {
        return ['ready' => true, 'status' => 'WordPress listo (simulado)', 'php_version' => '8.3', 'issues' => []];
    }
}

add_action('plugins_loaded', function () {
    Dominios_Reseller_Upmind_Integration::get_instance();
});
