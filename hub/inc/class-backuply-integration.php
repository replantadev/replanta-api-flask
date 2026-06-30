<?php
/**
 * Backuply Integration for Replanta Hub
 * 
 * Monitors and manages canonical B2 backups via Replanta Care.
 * Backuply remains an auxiliary local provider when available on client sites.
 * Communicates through the Replanta Care REST API on each remote site.
 * 
 * Replaces the former JetBackup integration.
 * 
 * @since 1.5.2
 */

if (!defined('ABSPATH')) {
    exit;
}

class ReplantaHub_Backuply_Integration {

    /** @var bool Whether backup monitoring is enabled in Hub settings */
    private $enabled;

    public function __construct() {
        $this->enabled = (bool) get_option('rphub_backuply_enabled', true);

        // If init already fired (class instantiated late), call directly
        if (did_action('init')) {
            $this->init();
        } else {
            add_action('init', [$this, 'init']);
        }
    }

    public function init() {
        // AJAX handlers
        add_action('wp_ajax_rphub_backuply_create_backup',   [$this, 'ajax_create_backup']);
        add_action('wp_ajax_rphub_backuply_list_backups',     [$this, 'ajax_list_backups']);
        add_action('wp_ajax_rphub_backuply_restore_backup',   [$this, 'ajax_restore_backup']);
        add_action('wp_ajax_rphub_backuply_get_backup_stats', [$this, 'ajax_get_backup_stats']);

        // Scheduled monitoring
        add_action('rphub_backuply_auto_check', [$this, 'scheduled_auto_check']);

        RPHUB_Scheduler::schedule('rphub_backuply_auto_check', 'twicedaily');
    }

    /* ------------------------------------------------------------------ */
    /*  Public API (used by Automation System)                            */
    /* ------------------------------------------------------------------ */

    /**
     * Whether the integration is active and ready.
     */
    public function is_configured() {
        return $this->enabled;
    }

    /**
     * Trigger a canonical B2 backup on a remote site via Care REST API.
     *
     * @param string $domain  The remote site domain.
     * @param array  $options Backup options forwarded to Care.
     * @return array|WP_Error
     */
    public function create_backup($domain, $options = []) {
        $site = $this->get_site_by_domain($domain);
        if (!$site) {
            return new WP_Error('site_not_found', 'Sitio no encontrado: ' . $domain);
        }

        $default_options = [
            'type'        => 'full',       // full, files, database
            'description' => 'Backup automático vía Replanta Hub — ' . date('Y-m-d H:i:s'),
        ];
        $options = array_merge($default_options, $options);

        $response = $this->care_api_call($site, 'backup/create', $options);

        if (is_wp_error($response)) {
            return $response;
        }

        // Persist in local tracking table
        $this->store_backup_record($domain, [
            'backup_id'  => $response['backup_id'] ?? uniqid('bkp_'),
            'type'       => $options['type'],
            'status'     => $response['status'] ?? 'pending',
            'size'       => $response['size'] ?? 0,
            'created_at' => current_time('mysql'),
            'description'=> $options['description'],
        ]);

        return $response;
    }

    /**
     * Restore a backup on a remote site via Care REST API.
     *
     * @param string $domain
     * @param string $backup_id
     * @return array|WP_Error
     */
    public function restore_backup($domain, $backup_id) {
        $site = $this->get_site_by_domain($domain);
        if (!$site) {
            return new WP_Error('site_not_found', 'Sitio no encontrado: ' . $domain);
        }

        return $this->care_api_call($site, 'backup/restore', [
            'backup_id' => $backup_id,
            'scopes'    => ['database'],
        ]);
    }

    /**
     * List backups for a domain (fetched from Care or local cache).
     *
     * @param string|null $domain
     * @param int         $limit
     * @return array|WP_Error
     */
    public function list_backups($domain = null, $limit = 50) {
        if (empty($domain)) {
            return [];
        }

        $site = $this->get_site_by_domain($domain);
        if (!$site) {
            return new WP_Error('site_not_found', 'Sitio no encontrado: ' . $domain);
        }

        // Try live data from Care
        $response = $this->care_api_call($site, 'backup/list', ['limit' => $limit]);

        if (is_wp_error($response)) {
            // Fall back to locally cached data
            return $this->get_cached_backups($domain, $limit);
        }

        $backups = [];
        foreach ($response['backups'] ?? [] as $b) {
            $backups[] = [
                'backup_id'   => $b['id'] ?? '',
                'type'        => $b['type'] ?? 'full',
                'status'      => $b['status'] ?? 'unknown',
                'size'        => $this->format_file_size($b['size'] ?? 0),
                'size_bytes'  => $b['size'] ?? 0,
                'created_at'  => $b['created_at'] ?? '',
                'completed_at'=> $b['completed_at'] ?? '',
                'description' => $b['name'] ?? $b['description'] ?? '',
                'is_restorable' => ($b['status'] ?? '') === 'completed',
            ];
        }

        // Cache result
        $this->cache_backups($domain, $backups);

        return array_slice($backups, 0, $limit);
    }

    /**
     * Get backup health assessment for a domain.
     *
     * @param string $domain
     * @return array|WP_Error
     */
    public function get_backup_health($domain) {
        $stats = $this->get_backup_stats($domain);

        if (is_wp_error($stats)) {
            return $stats;
        }

        $health = [
            'status'          => 'good',
            'score'           => 100,
            'issues'          => [],
            'recommendations' => [],
        ];

        // Evaluate last successful backup age
        if (!empty($stats['last_successful_backup'])) {
            $age = time() - strtotime($stats['last_successful_backup']['created_at']);

            if ($age > 7 * DAY_IN_SECONDS) {
                $health['issues'][] = 'Último backup exitoso hace más de 7 días';
                $health['score']   -= 30;
            } elseif ($age > 3 * DAY_IN_SECONDS) {
                $health['issues'][] = 'Último backup exitoso hace más de 3 días';
                $health['score']   -= 15;
            }
        } else {
            $health['issues'][] = 'No hay backups exitosos registrados';
            $health['score']   -= 50;
        }

        // Success rate
        if ($stats['success_rate'] < 80) {
            $health['issues'][] = 'Baja tasa de éxito en backups (' . $stats['success_rate'] . '%)';
            $health['score']   -= 20;
        }

        // Determine status label
        if ($health['score'] >= 80) {
            $health['status'] = 'good';
        } elseif ($health['score'] >= 60) {
            $health['status'] = 'warning';
        } else {
            $health['status'] = 'critical';
        }

        // Recommendations
        if (empty($stats['total_backups'])) {
            $health['recommendations'][] = 'Configurar backups automáticos con Backuply';
        }
        if ($stats['success_rate'] < 90 && $stats['total_backups'] > 0) {
            $health['recommendations'][] = 'Revisar la configuración de Backuply — tasa de fallos alta';
        }
        if ($health['score'] < 70) {
            $health['recommendations'][] = 'Ejecutar un backup manual desde el panel de Backuply';
        }

        return $health;
    }

    /**
     * Aggregate backup statistics for a domain.
     */
    public function get_backup_stats($domain) {
        $backups = $this->list_backups($domain);

        if (is_wp_error($backups)) {
            return $backups;
        }

        $stats = [
            'total_backups'           => count($backups),
            'successful_backups'      => 0,
            'failed_backups'          => 0,
            'pending_backups'         => 0,
            'total_size'              => 0,
            'last_backup'             => null,
            'last_successful_backup'  => null,
            'success_rate'            => 0,
            'total_size_formatted'    => '0 B',
        ];

        foreach ($backups as $backup) {
            $stats['total_size'] += $backup['size_bytes'];

            switch ($backup['status']) {
                case 'completed':
                    $stats['successful_backups']++;
                    if (!$stats['last_successful_backup']) {
                        $stats['last_successful_backup'] = $backup;
                    }
                    break;
                case 'failed':
                    $stats['failed_backups']++;
                    break;
                default:
                    $stats['pending_backups']++;
                    break;
            }

            if (!$stats['last_backup']) {
                $stats['last_backup'] = $backup;
            }
        }

        $stats['total_size_formatted'] = $this->format_file_size($stats['total_size']);
        $stats['success_rate'] = $stats['total_backups'] > 0
            ? round(($stats['successful_backups'] / $stats['total_backups']) * 100, 1)
            : 0;

        return $stats;
    }

    /**
     * Hourly monitoring hook.
     */
    public function run_hourly_checks() {
        if (!$this->is_configured()) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'rphub_sites';
        if (!$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table))) {
            return;
        }

        $sites = $wpdb->get_results("SELECT id, url FROM {$table} WHERE status = 'active' LIMIT 50");
        foreach ($sites as $site) {
            $domain = parse_url($site->url, PHP_URL_HOST);
            if ($domain) {
                $this->get_backup_health($domain);
            }
        }
    }

    /**
     * Alias used by cron.
     */
    public function check_backups() {
        return $this->run_hourly_checks();
    }

    /**
     * Scheduled twice-daily full check.
     */
    public function scheduled_auto_check() {
        $this->run_hourly_checks();
    }

    /* ------------------------------------------------------------------ */
    /*  Care REST API Communication                                       */
    /* ------------------------------------------------------------------ */

    /**
     * Call a Care endpoint on a remote site.
     *
     * @param object $site   DB row from rphub_sites.
     * @param string $endpoint  e.g. 'backup/create'
     * @param array  $data
     * @return array|WP_Error
     */
    private function care_api_call($site, $endpoint, $data = []) {
        $site_url   = rtrim($site->care_url ?? $site->url ?? '', '/');
        $site_token = $site->care_token ?? $site->site_token ?? $site->token ?? '';

        if (empty($site_url) || empty($site_token)) {
            return new WP_Error('missing_credentials', 'Credenciales del sitio no configuradas');
        }

        $url = $site_url . '/wp-json/replanta/v1/' . ltrim($endpoint, '/');

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization'    => 'Bearer ' . $site_token,
                'Content-Type'     => 'application/json',
            ],
            'body'    => wp_json_encode($data),
            'timeout' => 60,
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            return new WP_Error(
                'care_api_error',
                'Error de Care API (' . $code . '): ' . ($body['message'] ?? 'Error desconocido')
            );
        }

        return $body['data'] ?? $body ?? [];
    }

    /* ------------------------------------------------------------------ */
    /*  Local data helpers                                                */
    /* ------------------------------------------------------------------ */

    private function get_site_by_domain($domain) {
        global $wpdb;
        $table = $wpdb->prefix . 'rphub_sites';
        if (!$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table))) {
            return null;
        }
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE url LIKE %s LIMIT 1",
            '%' . $wpdb->esc_like($domain) . '%'
        ));
    }

    private function store_backup_record($domain, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'rphub_backups';
        if (!$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table))) {
            return;
        }
        $wpdb->insert($table, [
            'domain'     => $domain,
            'backup_id'  => $data['backup_id'],
            'type'       => $data['type'],
            'status'     => $data['status'],
            'size'       => $data['size'],
            'description'=> $data['description'],
            'created_at' => $data['created_at'],
        ]);
    }

    private function cache_backups($domain, $backups) {
        set_transient('rphub_backuply_' . md5($domain), $backups, HOUR_IN_SECONDS);
    }

    private function get_cached_backups($domain, $limit = 50) {
        $cached = get_transient('rphub_backuply_' . md5($domain));
        return is_array($cached) ? array_slice($cached, 0, $limit) : [];
    }

    private function format_file_size($bytes) {
        $bytes = (int) $bytes;
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }

    /* ------------------------------------------------------------------ */
    /*  AJAX handlers                                                     */
    /* ------------------------------------------------------------------ */

    public function ajax_create_backup() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }

        $domain  = sanitize_text_field($_POST['domain'] ?? '');
        $options = $_POST['options'] ?? [];

        if (empty($domain)) {
            wp_send_json_error('Dominio requerido');
        }

        $result = $this->create_backup($domain, $options);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    public function ajax_list_backups() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }

        $domain = sanitize_text_field($_POST['domain'] ?? '');
        $limit  = intval($_POST['limit'] ?? 50);

        if (empty($domain)) {
            wp_send_json_error('Dominio requerido');
        }

        $result = $this->list_backups($domain, $limit);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    public function ajax_restore_backup() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }

        $domain    = sanitize_text_field($_POST['domain'] ?? '');
        $backup_id = sanitize_text_field($_POST['backup_id'] ?? '');

        if (empty($domain) || empty($backup_id)) {
            wp_send_json_error('Dominio y ID de backup requeridos');
        }

        $site = $this->get_site_by_domain($domain);
        if (!$site) {
            wp_send_json_error('Sitio no encontrado');
        }

        $scopes = array_filter(array_map('sanitize_key', (array) ($_POST['scopes'] ?? ['database'])));
        if (empty($scopes)) {
            $scopes = ['database'];
        }

        $result = $this->care_api_call($site, 'backup/restore', [
            'backup_id' => $backup_id,
            'scopes'    => $scopes,
        ]);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    public function ajax_get_backup_stats() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }

        $domain = sanitize_text_field($_POST['domain'] ?? '');

        if (empty($domain)) {
            wp_send_json_error('Dominio requerido');
        }

        $stats  = $this->get_backup_stats($domain);
        $health = $this->get_backup_health($domain);

        if (is_wp_error($stats)) {
            wp_send_json_error($stats->get_error_message());
        }

        wp_send_json_success([
            'stats'  => $stats,
            'health' => is_wp_error($health) ? null : $health,
        ]);
    }
}
