<?php
/**
 * Backuply Advanced Integration — Dashboard & Site-Level Management
 *
 * Provides per-site backup monitoring, statistics, alerts, and settings
 * registration for the Backuply integration (replaces JetBackup Advanced).
 *
 * @since 1.5.2
 */

if (!defined('ABSPATH')) {
    exit;
}

class RP_Hub_Backuply_Integration {

    public function __construct() {
        // AJAX handlers for dashboard widgets
        add_action('wp_ajax_rphub_backuply_get_backups',     [$this, 'ajax_get_backups']);
        add_action('wp_ajax_rphub_backuply_create_backup',   [$this, 'ajax_create_backup']);
        add_action('wp_ajax_rphub_backuply_get_schedule',    [$this, 'ajax_get_schedule']);

        // Cron
        add_action('rphub_backuply_daily_check',  [$this, 'run_daily_backup_check']);
        add_action('rphub_backuply_sync_status',   [$this, 'sync_backup_status']);

        // Settings
        add_action('admin_init', [$this, 'register_settings']);
    }

    /* ------------------------------------------------------------------ */
    /*  Settings                                                          */
    /* ------------------------------------------------------------------ */

    public function register_settings() {
        register_setting('rphub_settings', 'rphub_backuply_enabled');
        register_setting('rphub_settings', 'rphub_backuply_auto_monitor');
        register_setting('rphub_settings', 'rphub_backuply_retention_days');
        register_setting('rphub_settings', 'rphub_backuply_alert_failed');
    }

    /* ------------------------------------------------------------------ */
    /*  Per-site operations (use main integration class)                  */
    /* ------------------------------------------------------------------ */

    /**
     * Get the main Backuply integration singleton.
     */
    private function main() {
        static $instance;
        if (!$instance && class_exists('ReplantaHub_Backuply_Integration')) {
            $instance = new ReplantaHub_Backuply_Integration();
        }
        return $instance;
    }

    public function get_site_backups($site_id) {
        $domain = $this->get_site_domain($site_id);
        if (!$domain || !$this->main()) {
            return [];
        }
        $backups = $this->main()->list_backups($domain);
        return is_wp_error($backups) ? [] : $backups;
    }

    public function get_backup_stats($site_id) {
        $domain = $this->get_site_domain($site_id);
        if (!$domain || !$this->main()) {
            return $this->empty_stats();
        }
        $stats = $this->main()->get_backup_stats($domain);
        return is_wp_error($stats) ? $this->empty_stats() : $stats;
    }

    public function get_backup_schedule($site_id) {
        $domain = $this->get_site_domain($site_id);
        if (!$domain || !$this->main()) {
            return null;
        }
        // Retrieve cached schedule from site meta
        if (class_exists('RPHUB_Database') && method_exists('RPHUB_Database', 'get_site_meta')) {
            $cached = RPHUB_Database::get_site_meta($site_id, 'backuply_schedule');
            if ($cached) {
                return json_decode($cached, true);
            }
        }
        return null;
    }

    public function update_site_backup_info($site_id) {
        $stats    = $this->get_backup_stats($site_id);
        $schedule = $this->get_backup_schedule($site_id);

        if (!class_exists('RPHUB_Database') || !method_exists('RPHUB_Database', 'update_site_meta')) {
            return false;
        }

        RPHUB_Database::update_site_meta($site_id, 'backup_stats',    wp_json_encode($stats));
        RPHUB_Database::update_site_meta($site_id, 'backup_schedule', wp_json_encode($schedule));
        RPHUB_Database::update_site_meta($site_id, 'last_backup',     $stats['last_backup']['created_at'] ?? '');
        RPHUB_Database::update_site_meta($site_id, 'backup_total_size', $stats['total_size'] ?? 0);
        RPHUB_Database::update_site_meta($site_id, 'backup_success_rate',
            $stats['total_backups'] > 0
                ? round(($stats['successful_backups'] / $stats['total_backups']) * 100)
                : 0
        );
        RPHUB_Database::update_site_meta($site_id, 'last_backuply_sync', current_time('mysql'));

        // Generate alerts if needed
        $this->check_backup_alerts($site_id, $stats);

        return true;
    }

    /* ------------------------------------------------------------------ */
    /*  Alerts                                                            */
    /* ------------------------------------------------------------------ */

    private function check_backup_alerts($site_id, $stats) {
        $alert_failed = get_option('rphub_backuply_alert_failed', true);
        if (!$alert_failed) {
            return;
        }

        $alerts = [];

        if (!empty($stats['last_backup'])) {
            $last = $stats['last_backup']['created_at'] ?? '';
            if ($last) {
                $hours = (time() - strtotime($last)) / 3600;
                if ($hours > 48) {
                    $alerts[] = [
                        'type'       => 'warning',
                        'message'    => 'No se han realizado backups en las últimas 48 horas',
                        'created_at' => current_time('mysql'),
                    ];
                }
            }
        } else {
            $alerts[] = [
                'type'       => 'error',
                'message'    => 'No se encontraron backups para este sitio',
                'created_at' => current_time('mysql'),
            ];
        }

        if (($stats['failed_backups'] ?? 0) > 0) {
            $alerts[] = [
                'type'       => 'error',
                'message'    => "Se encontraron {$stats['failed_backups']} backups fallidos",
                'created_at' => current_time('mysql'),
            ];
        }

        if (!empty($alerts) && class_exists('RPHUB_Database') && method_exists('RPHUB_Database', 'update_site_meta')) {
            $existing = RPHUB_Database::get_site_meta($site_id, 'backup_alerts');
            $existing = $existing ? json_decode($existing, true) : [];
            $all      = array_merge($existing, $alerts);
            $all      = array_slice($all, -10);
            RPHUB_Database::update_site_meta($site_id, 'backup_alerts', wp_json_encode($all));
        }
    }

    /* ------------------------------------------------------------------ */
    /*  AJAX Handlers (dashboard)                                         */
    /* ------------------------------------------------------------------ */

    public function ajax_get_backups() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rphub_dashboard_nonce')) {
            wp_send_json_error(['message' => 'Security check failed']); return;
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }

        $site_id = intval($_POST['site_id'] ?? 0);
        $backups = $this->get_site_backups($site_id);
        $stats   = $this->get_backup_stats($site_id);

        wp_send_json_success([
            'backups' => $backups,
            'stats'   => $stats,
        ]);
    }

    public function ajax_create_backup() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rphub_dashboard_nonce')) {
            wp_send_json_error(['message' => 'Security check failed']); return;
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }

        $site_id     = intval($_POST['site_id'] ?? 0);
        $backup_type = sanitize_text_field($_POST['backup_type'] ?? 'full');
        $domain      = $this->get_site_domain($site_id);

        if (!$domain || !$this->main()) {
            wp_send_json_error('Sitio no encontrado o Backuply no disponible');
            return;
        }

        $result = $this->main()->create_backup($domain, [
            'type'        => $backup_type,
            'description' => 'Backup manual desde Replanta Hub',
        ]);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }

        wp_send_json_success([
            'message'   => 'Backup iniciado correctamente vía Backuply',
            'backup_id' => $result['backup_id'] ?? '',
        ]);
    }

    public function ajax_get_schedule() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rphub_dashboard_nonce')) {
            wp_send_json_error(['message' => 'Security check failed']); return;
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }

        $site_id  = intval($_POST['site_id'] ?? 0);
        $schedule = $this->get_backup_schedule($site_id);

        wp_send_json_success($schedule);
    }

    /* ------------------------------------------------------------------ */
    /*  Cron tasks                                                        */
    /* ------------------------------------------------------------------ */

    public function run_daily_backup_check() {
        $auto = get_option('rphub_backuply_auto_monitor', false);
        if (!$auto) {
            return;
        }

        if (!class_exists('RPHUB_Database') || !method_exists('RPHUB_Database', 'get_all_sites')) {
            return;
        }

        $sites = RPHUB_Database::get_all_sites();
        foreach ($sites as $site) {
            $this->update_site_backup_info($site->id);
            sleep(2); // Rate limiting
        }
    }

    public function sync_backup_status() {
        if (!class_exists('RPHUB_Database') || !method_exists('RPHUB_Database', 'get_all_sites')) {
            return;
        }

        $sites = RPHUB_Database::get_all_sites();
        foreach ($sites as $site) {
            $in_progress = class_exists('RPHUB_Database')
                ? RPHUB_Database::get_site_meta($site->id, 'backup_in_progress')
                : '';
            if ($in_progress === 'yes') {
                $this->update_site_backup_info($site->id);
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                           */
    /* ------------------------------------------------------------------ */

    private function get_site_domain($site_id) {
        if (class_exists('RPHUB_Database') && method_exists('RPHUB_Database', 'get_site')) {
            $site = RPHUB_Database::get_site($site_id);
            return $site ? parse_url($site->url ?? '', PHP_URL_HOST) : null;
        }
        return null;
    }

    private function empty_stats() {
        return [
            'total_backups'          => 0,
            'successful_backups'     => 0,
            'failed_backups'         => 0,
            'last_backup'            => null,
            'next_backup'            => null,
            'total_size'             => 0,
            'oldest_backup'          => null,
            'backup_frequency'       => 'unknown',
            'success_rate'           => 0,
            'total_size_formatted'   => '0 B',
        ];
    }
}

// Initialize
new RP_Hub_Backuply_Integration();
