<?php
/**
 * Backblaze B2 Integration
 *
 * Reads backup status from B2 buckets for all managed sites.
 * Care plugin uploads DB dumps; this class verifies and aggregates.
 */

if (!defined('ABSPATH')) {
    exit;
}

class ReplantaHub_Backblaze_Integration {

    private static $instance = null;

    const B2_AUTH_URL     = 'https://api.backblazeb2.com/b2api/v3/b2_authorize_account';
    const AUTH_TRANSIENT  = 'rphub_b2_auth';
    const AUTH_TTL        = 82800; // 23 hours — B2 token lifetime is 24h, refresh early

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_rphub_b2_get_site_status',    [$this, 'ajax_get_site_status']);
        add_action('wp_ajax_rphub_b2_push_config_to_care', [$this, 'ajax_push_config_to_care']);
        add_action('wp_ajax_rphub_b2_test_connection',    [$this, 'ajax_test_connection']);
        add_action('rphub_b2_daily_check',                [$this, 'check_all_sites']);
        RPHUB_Scheduler::schedule('rphub_b2_daily_check', 'daily');
    }

    // -------------------------------------------------------------------------
    // Configuration helpers
    // -------------------------------------------------------------------------

    public static function get_global_config() {
        $settings = get_option('rphub_settings', []);
        $raw_key  = $settings['b2_app_key'] ?? '';
        $app_key  = (class_exists('RPHUB_Crypto') && !empty($raw_key))
            ? RPHUB_Crypto::decrypt($raw_key)
            : $raw_key;
        return [
            'key_id'      => $settings['b2_key_id']     ?? '',
            'app_key'     => $app_key,
            'bucket_id'   => $settings['b2_bucket_id']  ?? '',
            'bucket_name' => $settings['b2_bucket_name'] ?? '',
        ];
    }

    public static function get_site_config($site_id) {
        $per_site_key_id  = RPHUB_Database::get_site_meta($site_id, 'b2_key_id');
        $per_site_app_key = RPHUB_Database::get_site_meta($site_id, 'b2_app_key');
        $per_site_bucket  = RPHUB_Database::get_site_meta($site_id, 'b2_bucket_id');
        $prefix           = RPHUB_Database::get_site_meta($site_id, 'b2_prefix') ?: self::build_site_prefix($site_id);

        if ($per_site_key_id && $per_site_app_key && $per_site_bucket) {
            return [
                'key_id'    => $per_site_key_id,
                'app_key'   => $per_site_app_key,
                'bucket_id' => $per_site_bucket,
                'prefix'    => $prefix,
            ];
        }

        return array_merge(self::get_global_config(), ['prefix' => $prefix]);
    }

    public static function build_site_prefix($site_id, $site = null) {
        if (!$site && class_exists('RPHUB_Database')) {
            $site = RPHUB_Database::get_site($site_id);
        }
        $host = $site && !empty($site->url) ? wp_parse_url($site->url, PHP_URL_HOST) : 'site';
        $host = preg_replace('#[^a-zA-Z0-9.-]+#', '-', (string) $host);
        return 'sites/' . intval($site_id) . '-' . strtolower(trim($host, '-')) . '/';
    }

    public static function is_configured() {
        $cfg = self::get_global_config();
        return !empty($cfg['key_id']) && !empty($cfg['app_key']) && !empty($cfg['bucket_id']);
    }

    // -------------------------------------------------------------------------
    // B2 API — Authentication
    // -------------------------------------------------------------------------

    /**
     * Authorize against B2. Returns auth array or WP_Error.
     * Cached for 23 hours to avoid hitting rate limits.
     */
    public function authorize($key_id = null, $app_key = null) {
        $use_global = ($key_id === null && $app_key === null);

        if ($use_global) {
            $cached = get_transient(self::AUTH_TRANSIENT);
            if (
                is_array($cached)
                && !empty($cached['auth_token'])
                && !empty($cached['api_url'])
                && !empty($cached['expires_at'])
                && intval($cached['expires_at']) > time() + 300
            ) {
                return $cached;
            }
            $cfg     = self::get_global_config();
            $key_id  = $cfg['key_id'];
            $app_key = $cfg['app_key'];
        }

        if (empty($key_id) || empty($app_key)) {
            return new WP_Error('b2_no_credentials', 'B2 credentials not configured');
        }

        $response = wp_remote_get(self::B2_AUTH_URL, [
            'timeout' => 20,
            'headers' => ['Authorization' => 'Basic ' . base64_encode($key_id . ':' . $app_key)],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200 || empty($body['authorizationToken'])) {
            return new WP_Error('b2_auth_failed', 'B2 auth error HTTP ' . $code . ': ' . ($body['message'] ?? 'unknown'));
        }

        $auth = [
            'api_url'    => rtrim($body['apiInfo']['storageApi']['apiUrl'] ?? $body['apiUrl'] ?? '', '/'),
            'auth_token' => $body['authorizationToken'],
            'account_id' => $body['accountId'] ?? '',
            'expires_at' => time() + self::AUTH_TTL,
        ];

        if (empty($auth['api_url'])) {
            return new WP_Error('b2_auth_failed', 'B2 auth response missing apiUrl');
        }

        if ($use_global) {
            set_transient(self::AUTH_TRANSIENT, $auth, self::AUTH_TTL);
        }

        return $auth;
    }

    // -------------------------------------------------------------------------
    // B2 API — File listing
    // -------------------------------------------------------------------------

    /**
     * List the most recent backup files for a site domain prefix.
     * Returns array of file objects or WP_Error.
     */
    public function list_site_backups($domain, $bucket_id, $auth, $max_files = 20, $prefix_root = null) {
        $prefix = $prefix_root
            ? trim($prefix_root, '/') . '/backup_'
            : sanitize_text_field($domain) . '/backup_';

        $response = wp_remote_post(
            $auth['api_url'] . '/b2api/v3/b2_list_file_names',
            [
                'timeout' => 20,
                'headers' => [
                    'Authorization' => $auth['auth_token'],
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode([
                    'bucketId'     => $bucket_id,
                    'prefix'       => $prefix,
                    'maxFileCount' => min(1000, max(1, (int) $max_files)),
                ]),
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            return new WP_Error('b2_list_failed', 'B2 list error HTTP ' . $code . ': ' . ($body['message'] ?? 'unknown'));
        }

        return $body['files'] ?? [];
    }

    /**
     * Get the latest backup entry for a site. Returns associative array or null.
     */
    public function get_latest_backup($site_id) {
        $site = RPHUB_Database::get_site($site_id);
        if (!$site) {
            return null;
        }

        $domain = wp_parse_url($site->url, PHP_URL_HOST);
        $cfg    = self::get_site_config($site_id);

        if (empty($cfg['key_id']) || empty($cfg['bucket_id'])) {
            return null;
        }

        $auth = $this->authorize($cfg['key_id'], $cfg['app_key']);
        if (is_wp_error($auth)) {
            return null;
        }

        $files = $this->list_site_backups($domain, $cfg['bucket_id'], $auth, 50, $cfg['prefix'] ?? null);
        if (is_wp_error($files) || empty($files)) {
            return null;
        }

        // Group by backup prefix (each backup = one prefix folder), pick newest
        $backups = [];
        foreach ($files as $file) {
            if (!isset($file['fileName'])) {
                continue;
            }
            // Extract prefix: domain/backup_YYYY-MM-DD_HH-ii-ss/
            if (preg_match('#^(.+/backup_[\d_-]+/)#', $file['fileName'], $m)) {
                $prefix = $m[1];
                $ts     = $file['uploadTimestamp'] ?? 0; // milliseconds
                if (!isset($backups[$prefix]) || $ts > $backups[$prefix]['ts']) {
                    $backups[$prefix] = ['ts' => $ts, 'prefix' => $prefix, 'files' => []];
                }
                $backups[$prefix]['files'][] = $file['fileName'];
            }
        }

        if (empty($backups)) {
            return null;
        }

        // Sort by timestamp descending, return the newest
        uasort($backups, fn($a, $b) => $b['ts'] <=> $a['ts']);
        $latest = reset($backups);

        return [
            'prefix'      => $latest['prefix'],
            'timestamp'   => intval($latest['ts'] / 1000), // convert ms → s
            'date'        => date('Y-m-d H:i:s', intval($latest['ts'] / 1000)),
            'age_hours'   => round((time() - intval($latest['ts'] / 1000)) / 3600, 1),
            'files'       => $latest['files'],
            'has_db'      => (bool) array_filter($latest['files'], fn($f) => str_contains($f, 'database')),
        ];
    }

    // -------------------------------------------------------------------------
    // Health scoring
    // -------------------------------------------------------------------------

    /**
     * Returns a 0-100 health score for a site's B2 backups.
     * 100 = recent backup with DB dump present.
     */
    public function get_backup_health($site_id, $plan_slug = null) {
        $info = $this->get_latest_backup($site_id);

        if (!$info) {
            return ['score' => 0, 'status' => 'no_backup', 'message' => 'Sin backup en B2'];
        }

        $plan_slug   = $plan_slug ?: RPHUB_Database::get_site_meta($site_id, 'plan') ?: 'semilla';
        $max_age_h   = $this->get_plan_max_age_hours($plan_slug);
        $age_h       = $info['age_hours'];
        $score       = 100;

        if ($age_h > $max_age_h) {
            // Linear penalty: 0 pts at 2× max age
            $score = max(0, intval(100 - (($age_h - $max_age_h) / $max_age_h) * 100));
        }

        if (!$info['has_db']) {
            $score = min($score, 40);
        }

        $status = $score >= 80 ? 'ok' : ($score >= 40 ? 'warning' : 'critical');

        return [
            'score'     => $score,
            'status'    => $status,
            'age_hours' => $age_h,
            'max_age_h' => $max_age_h,
            'has_db'    => $info['has_db'],
            'last_date' => $info['date'],
            'prefix'    => $info['prefix'],
            'message'   => $this->health_message($status, $age_h, $max_age_h),
        ];
    }

    private function get_plan_max_age_hours($plan_slug) {
        return match ($plan_slug) {
            'ecosistema'       => 26,
            'raiz'             => 26,
            'ecommerce_addon'  => 13,
            default            => 192, // semilla: 8 days (weekly backup)
        };
    }

    private function health_message($status, $age_h, $max_h) {
        if ($status === 'ok') {
            return sprintf('Último backup hace %.1fh — OK', $age_h);
        }
        if ($status === 'warning') {
            return sprintf('Backup tiene %.1fh (límite %dh)', $age_h, $max_h);
        }
        return sprintf('CRÍTICO: sin backup válido en las últimas %dh (tiene %.1fh)', $max_h, $age_h);
    }

    // -------------------------------------------------------------------------
    // Cron: check all sites daily
    // -------------------------------------------------------------------------

    public function check_all_sites() {
        if (!self::is_configured()) {
            return;
        }

        $sites = RPHUB_Database::get_all_sites();
        foreach ($sites as $site) {
            if ($site->status !== 'active') {
                continue;
            }
            $b2_enabled = RPHUB_Database::get_site_meta($site->id, 'b2_backup_enabled');
            if ($b2_enabled === '0') {
                continue;
            }
            $this->check_site_and_alert($site->id);
        }
    }

    private function check_site_and_alert($site_id) {
        $plan_slug = RPHUB_Database::get_site_meta($site_id, 'plan') ?: 'semilla';
        $health    = $this->get_backup_health($site_id, $plan_slug);

        RPHUB_Database::update_site_meta($site_id, 'b2_backup_health', $health);
        RPHUB_Database::update_site_meta($site_id, 'b2_backup_checked_at', current_time('mysql'));

        if (in_array($health['status'], ['warning', 'critical'], true)) {
            $this->send_backup_alert($site_id, $health);
        }
    }

    private function send_backup_alert($site_id, $health) {
        $site = RPHUB_Database::get_site($site_id);
        if (!$site) {
            return;
        }

        // Deduplicate: don't re-alert for same status within 12h
        $last_alert_key = 'b2_alert_sent_' . $health['status'];
        $last_alert     = RPHUB_Database::get_site_meta($site_id, $last_alert_key);
        if ($last_alert && (time() - strtotime($last_alert)) < 12 * HOUR_IN_SECONDS) {
            return;
        }

        $severity = $health['status'] === 'critical' ? 'critical' : 'warning';
        $subject  = sprintf('[%s] Backup B2 — %s: %s', strtoupper($severity), $site->name, $health['message']);

        if (class_exists('RPHUB_Alerting')) {
            RPHUB_Alerting::send_alert([
                'site_id'  => $site_id,
                'type'     => 'backup_b2',
                'severity' => $severity,
                'subject'  => $subject,
                'message'  => $health['message'],
                'data'     => $health,
            ]);
        } else {
            wp_mail(get_option('admin_email'), $subject, $health['message']);
        }

        RPHUB_Database::update_site_meta($site_id, $last_alert_key, current_time('mysql'));
    }

    // -------------------------------------------------------------------------
    // Push B2 credentials to Care plugin on a site
    // -------------------------------------------------------------------------

    public function push_config_to_care($site_id) {
        $site = RPHUB_Database::get_site($site_id);
        if (!$site) {
            return new WP_Error('no_site', 'Site not found');
        }

        $care_url = !empty($site->care_url) ? $site->care_url : ($site->url ?? '');
        $care_token = $site->care_token ?? $site->site_token ?? $site->token ?? '';
        if (empty($care_url) || empty($care_token)) {
            return new WP_Error('no_site', 'Site missing Care URL or token');
        }

        $cfg = self::get_site_config($site_id);
        $prefix = $cfg['prefix'] ?? self::build_site_prefix($site_id, $site);

        $response = wp_remote_post(
            trailingslashit($care_url) . 'wp-json/replanta/v1/config',
            [
                'timeout' => 20,
                'headers' => [
                    'Authorization' => 'Bearer ' . $care_token,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode([
                    'b2_key_id'     => $cfg['key_id'],
                    'b2_app_key'    => $cfg['app_key'],
                    'b2_bucket_id'  => $cfg['bucket_id'],
                    'b2_bucket_name'=> $cfg['bucket_name'] ?? '',
                    'b2_prefix'     => $prefix,
                ]),
                'sslverify' => true,
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            return new WP_Error('push_failed', 'Care /config returned HTTP ' . $code);
        }

        RPHUB_Database::update_site_meta($site_id, 'b2_config_pushed_at', current_time('mysql'));
        RPHUB_Database::update_site_meta($site_id, 'b2_prefix', $prefix);
        return true;
    }

    // Push portal cache (update history, risk, backup health, SA delta) to Care
    public function pushPortalCacheToCare($site_id) {
        $site = RPHUB_Database::get_site($site_id);
        if (!$site || empty($site->url) || empty($site->token)) {
            return new WP_Error('no_site', 'Site not found or missing token');
        }

        $cache = [
            'update_history'   => RPHUB_Database::get_site_meta($site_id, 'update_history')          ?? [],
            'risk_assessments' => RPHUB_Database::get_site_meta($site_id, 'update_risk_assessments') ?? [],
            'ssl_days'         => RPHUB_Database::get_site_meta($site_id, 'ssl_days'),
        ];

        $bh = $this->get_backup_health($site_id);
        $cache['backup_health'] = $bh['status'] ?? 'unknown';
        if (!empty($bh['last_backup'])) {
            $cache['backup_history'] = [['timestamp' => $bh['last_backup']]];
        }

        if (class_exists('RPHUB_Delta_Reporter')) {
            $monthly = RPHUB_Delta_Reporter::get_instance()->get_monthly_summary($site_id);
            if ($monthly) {
                $cache['monthly_summary'] = $monthly;
                $cache['sa_delta']        = $monthly['sa_trend'] ?? null;
            }
        }

        $response = wp_remote_post(
            trailingslashit($site->url) . 'wp-json/replanta/v1/config',
            [
                'timeout' => 20,
                'headers' => [
                    'Authorization' => 'Bearer ' . $site->token,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode(['portal_cache' => $cache]),
            ]
        );

        $code  = is_wp_error($response) ? 0 : wp_remote_retrieve_response_code($response);
        $error = null;
        if ($code !== 200) {
            $error = is_wp_error($response) ? $response : new WP_Error('push_failed', 'Care /config HTTP ' . $code);
        }

        if ($error) {
            return $error;
        }

        RPHUB_Database::update_site_meta($site_id, 'portal_cache_pushed_at', current_time('mysql'));
        return true;
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    public function ajax_get_site_status() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes', 403);
        }

        $site_id = intval($_POST['site_id'] ?? 0);
        if (!$site_id) {
            wp_send_json_error('site_id requerido');
        }

        $health = $this->get_backup_health($site_id);
        wp_send_json_success($health);
    }

    public function ajax_push_config_to_care() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes', 403);
        }

        $site_id = intval($_POST['site_id'] ?? 0);
        if (!$site_id) {
            wp_send_json_error('site_id requerido');
        }

        $result = $this->push_config_to_care($site_id);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        wp_send_json_success(['message' => 'Configuración B2 enviada a Care']);
    }

    public function ajax_test_connection() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes', 403); return;
        }

        $key_id    = sanitize_text_field($_POST['key_id']    ?? '');
        $app_key   = sanitize_text_field($_POST['app_key']   ?? '');
        $bucket_id = sanitize_text_field($_POST['bucket_id'] ?? '');

        // If form left app_key blank, fall back to stored encrypted value
        if ($key_id !== '' && $app_key === '') {
            $cfg     = self::get_global_config();
            $app_key = $cfg['app_key'];
        }

        if ($key_id !== '' && $app_key !== '') {
            $auth = $this->authorize($key_id, $app_key);
        } else {
            delete_transient(self::AUTH_TRANSIENT);
            $auth = $this->authorize();
            if (!is_wp_error($auth) && $bucket_id === '') {
                $cfg = self::get_global_config();
                $bucket_id = $cfg['bucket_id'];
            }
        }

        if (is_wp_error($auth)) {
            wp_send_json_error($auth->get_error_message()); return;
        }

        $bucket_check = null;
        if ($bucket_id !== '') {
            $bucket_check = $this->verify_bucket_access($auth, $bucket_id);
            if (is_wp_error($bucket_check)) {
                wp_send_json_error('Auth OK pero bucket inaccesible: ' . $bucket_check->get_error_message()); return;
            }
        }

        wp_send_json_success([
            'message'      => 'Conexion B2 OK' . ($bucket_check ? ' (bucket accesible)' : ''),
            'account_id'   => $auth['account_id'],
            'api_url'      => $auth['api_url'],
            'bucket_name'  => $bucket_check['bucketName'] ?? null,
            'bucket_type'  => $bucket_check['bucketType'] ?? null,
        ]);
    }

    /**
     * Validate that the authenticated session can see a specific bucket.
     * Returns the bucket array on success or WP_Error.
     */
    private function verify_bucket_access(array $auth, string $bucket_id) {
        $response = wp_remote_post($auth['api_url'] . '/b2api/v3/b2_list_buckets', [
            'timeout' => 15,
            'headers' => [
                'Authorization' => $auth['auth_token'],
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode([
                'accountId' => $auth['account_id'],
                'bucketId'  => $bucket_id,
            ]),
        ]);
        if (is_wp_error($response)) {
            return $response;
        }
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($code !== 200) {
            return new WP_Error('b2_list_buckets', $body['message'] ?? "HTTP {$code}");
        }
        if (empty($body['buckets']) || !is_array($body['buckets'])) {
            return new WP_Error('b2_bucket_not_found', 'Bucket no encontrado en la cuenta');
        }
        return $body['buckets'][0];
    }

    /**
     * Push current global B2 config to every active site with a Care token.
     * Used after settings save and exposed via AJAX for the "Sync now" button.
     * Returns [pushed, failed, errors[]].
     */
    public function push_config_to_all_sites(): array {
        $results = ['pushed' => 0, 'failed' => 0, 'errors' => []];

        if (!self::is_configured()) {
            return $results;
        }

        $sites = RPHUB_Database::get_all_sites();
        foreach ($sites as $site) {
            if (($site->status ?? '') !== 'active' || empty($site->url) || empty($site->token)) {
                continue;
            }
            $res = $this->push_config_to_care($site->id);
            if (is_wp_error($res)) {
                $results['failed']++;
                $results['errors'][] = ($site->name ?: $site->url) . ': ' . $res->get_error_message();
            } else {
                $results['pushed']++;
            }
        }
        return $results;
    }
}
