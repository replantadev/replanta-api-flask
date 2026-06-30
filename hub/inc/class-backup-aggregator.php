<?php
/**
 * Backup Aggregator
 *
 * Unified backup status across all providers (Backuply via Care API + Backblaze B2).
 * This is the single source of truth for backup health across all managed sites.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPHUB_Backup_Aggregator {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_rphub_backup_aggregator_overview', [$this, 'ajax_get_overview']);
        add_action('wp_ajax_rphub_backup_aggregator_site',     [$this, 'ajax_get_site_status']);
        add_action('wp_ajax_rphub_backup_push_b2_all',         [$this, 'ajax_push_b2_to_all']);
        add_action('rphub_backup_daily_check',                 [$this, 'run_daily_check']);

        RPHUB_Scheduler::schedule('rphub_backup_daily_check', 'daily');
    }

    // -------------------------------------------------------------------------
    // Thresholds by plan
    // -------------------------------------------------------------------------

    /**
     * Returns max acceptable backup age in hours per plan.
     * Used to evaluate both local (Backuply) and remote (B2) backups.
     */
    public static function get_threshold_hours($plan_slug, $has_ecommerce_addon = false) {
        if ($has_ecommerce_addon) {
            return 13; // ecommerce: backup every 12h → alert at 13h
        }

        return match ($plan_slug) {
            'ecosistema' => 26,
            'raiz'       => 26,
            default      => 192, // semilla: weekly → alert after 8 days
        };
    }

    // -------------------------------------------------------------------------
    // Per-site unified status
    // -------------------------------------------------------------------------

    /**
     * Returns a unified backup status object for a single site.
     * Merges: local backup (from Care /metrics), B2 remote backup.
     */
    public function get_site_status($site_id) {
        $site = RPHUB_Database::get_site($site_id);
        if (!$site) {
            return null;
        }

        $plan_slug         = $site->plan ?: 'semilla';
        $has_ecommerce     = $this->site_has_ecommerce_addon($site_id);
        $threshold_hours   = self::get_threshold_hours($plan_slug, $has_ecommerce);

        $status = [
            'site_id'          => $site_id,
            'site_name'        => $site->name,
            'site_url'         => $site->url,
            'plan'             => $plan_slug,
            'ecommerce_addon'  => $has_ecommerce,
            'threshold_hours'  => $threshold_hours,
            'local'            => $this->get_local_backup_status($site_id),
            'b2'               => $this->get_b2_status($site_id),
            'overall'          => null,
            'checked_at'       => current_time('mysql'),
        ];

        $status['overall'] = $this->compute_overall_status($status, $threshold_hours);

        return $status;
    }

    private function get_local_backup_status($site_id) {
        // Read from site_meta (populated by Care's /metrics endpoint, polled hourly by Hub)
        $last_backup  = RPHUB_Database::get_site_meta($site_id, 'last_backup');
        $backup_method = RPHUB_Database::get_site_meta($site_id, 'backup_method');

        if (!$last_backup) {
            return ['status' => 'unknown', 'age_hours' => null, 'method' => $backup_method ?: 'unknown'];
        }

        $age_hours = round((time() - strtotime($last_backup)) / 3600, 1);

        return [
            'status'    => 'known',
            'last_date' => $last_backup,
            'age_hours' => $age_hours,
            'method'    => $backup_method ?: 'unknown',
        ];
    }

    private function get_b2_status($site_id) {
        if (!class_exists('ReplantaHub_Backblaze_Integration')) {
            return ['enabled' => false];
        }

        $b2 = ReplantaHub_Backblaze_Integration::get_instance();

        // Use cached meta to avoid hitting B2 on every request
        $cached = RPHUB_Database::get_site_meta($site_id, 'b2_backup_health');
        if ($cached && is_array($cached)) {
            $checked_at = RPHUB_Database::get_site_meta($site_id, 'b2_backup_checked_at');
            if ($checked_at && (time() - strtotime($checked_at)) < 3 * HOUR_IN_SECONDS) {
                return array_merge(['enabled' => true, 'cached' => true], $cached);
            }
        }

        if (!ReplantaHub_Backblaze_Integration::is_configured()) {
            return ['enabled' => false];
        }

        $health = $b2->get_backup_health($site_id);
        return array_merge(['enabled' => true, 'cached' => false], $health);
    }

    /**
     * Compute an overall traffic-light status from local + B2.
     * Strategy: B2 is mandatory for any plan; local is a bonus.
     */
    private function compute_overall_status($status, $threshold_hours) {
        $b2 = $status['b2'];

        // If B2 not enabled yet, fall back to local only
        if (!($b2['enabled'] ?? false)) {
            $local = $status['local'];
            if (($local['status'] ?? 'unknown') === 'unknown') {
                return ['level' => 'unknown', 'message' => 'Sin datos de backup'];
            }
            $age = $local['age_hours'] ?? PHP_INT_MAX;
            if ($age <= $threshold_hours) {
                return ['level' => 'ok', 'message' => sprintf('Local: hace %.1fh', $age)];
            }
            if ($age <= $threshold_hours * 2) {
                return ['level' => 'warning', 'message' => sprintf('Local: hace %.1fh (límite %dh)', $age, $threshold_hours)];
            }
            return ['level' => 'critical', 'message' => sprintf('Local: hace %.1fh — CRÍTICO', $age)];
        }

        // B2 enabled: use its health as primary indicator
        $b2_status = $b2['status'] ?? 'no_backup';
        $b2_age    = $b2['age_hours'] ?? PHP_INT_MAX;

        if ($b2_status === 'ok') {
            return ['level' => 'ok', 'message' => sprintf('B2: hace %.1fh', $b2_age)];
        }
        if ($b2_status === 'warning') {
            return ['level' => 'warning', 'message' => $b2['message'] ?? 'Backup B2 atrasado'];
        }
        return ['level' => 'critical', 'message' => $b2['message'] ?? 'Sin backup B2 reciente'];
    }

    // -------------------------------------------------------------------------
    // Fleet overview
    // -------------------------------------------------------------------------

    /**
     * Returns summary stats for all active sites.
     * Used by Hub dashboard widget.
     */
    public function get_fleet_overview() {
        $sites = RPHUB_Database::get_all_sites();
        $result = [
            'total'    => 0,
            'ok'       => 0,
            'warning'  => 0,
            'critical' => 0,
            'unknown'  => 0,
            'sites'    => [],
        ];

        foreach ($sites as $site) {
            if ($site->status !== 'active') {
                continue;
            }
            $status = $this->get_site_status($site->id);
            if (!$status) {
                continue;
            }
            $level = $status['overall']['level'] ?? 'unknown';
            $result['total']++;
            $result[$level] = ($result[$level] ?? 0) + 1;
            $result['sites'][] = [
                'id'      => $site->id,
                'name'    => $site->name,
                'url'     => $site->url,
                'plan'    => $site->plan,
                'level'   => $level,
                'message' => $status['overall']['message'] ?? '',
                'b2_age'  => $status['b2']['age_hours'] ?? null,
            ];
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Daily cron
    // -------------------------------------------------------------------------

    public function run_daily_check() {
        $sites = RPHUB_Database::get_all_sites();

        foreach ($sites as $site) {
            if ($site->status !== 'active') {
                continue;
            }

            $status = $this->get_site_status($site->id);
            if (!$status) {
                continue;
            }

            // Persist overall level in site_meta for fast dashboard reads
            RPHUB_Database::update_site_meta($site->id, 'backup_overall_level',   $status['overall']['level']);
            RPHUB_Database::update_site_meta($site->id, 'backup_overall_message', $status['overall']['message']);
            RPHUB_Database::update_site_meta($site->id, 'backup_last_checked',    current_time('mysql'));

            $this->maybe_send_alert($site->id, $status);
        }
    }

    private function maybe_send_alert($site_id, $status) {
        $level = $status['overall']['level'] ?? 'ok';
        if ($level === 'ok' || $level === 'unknown') {
            return;
        }

        // Deduplicate: max one alert per level per 24h
        $dedup_key = 'backup_alert_sent_' . $level;
        $last_sent = RPHUB_Database::get_site_meta($site_id, $dedup_key);
        if ($last_sent && (time() - strtotime($last_sent)) < DAY_IN_SECONDS) {
            return;
        }

        $site     = RPHUB_Database::get_site($site_id);
        $severity = $level === 'critical' ? 'critical' : 'warning';
        $subject  = sprintf('[BACKUP %s] %s: %s', strtoupper($level), $site->name ?? '', $status['overall']['message']);

        if (class_exists('RPHUB_Alerting')) {
            RPHUB_Alerting::send_alert([
                'site_id'  => $site_id,
                'type'     => 'backup_check',
                'severity' => $severity,
                'subject'  => $subject,
                'message'  => $status['overall']['message'],
                'data'     => $status,
            ]);
        } else {
            wp_mail(get_option('admin_email'), $subject, $status['overall']['message']);
        }

        RPHUB_Database::update_site_meta($site_id, $dedup_key, current_time('mysql'));
    }

    // -------------------------------------------------------------------------
    // Ecommerce addon helper
    // -------------------------------------------------------------------------

    private function site_has_ecommerce_addon($site_id) {
        $addons = RPHUB_Database::get_site_meta($site_id, 'active_addons');
        if (!is_array($addons)) {
            return false;
        }
        return in_array('ecommerce', $addons, true);
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    public function ajax_get_overview() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes', 403);
        }
        wp_send_json_success($this->get_fleet_overview());
    }

    public function ajax_get_site_status() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes', 403);
        }
        $site_id = intval($_POST['site_id'] ?? 0);
        if (!$site_id) {
            wp_send_json_error('site_id requerido');
        }
        wp_send_json_success($this->get_site_status($site_id));
    }

    public function ajax_push_b2_to_all() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes', 403);
        }

        if (!class_exists('ReplantaHub_Backblaze_Integration')) {
            wp_send_json_error('Backblaze integration not loaded');
        }

        $b2    = ReplantaHub_Backblaze_Integration::get_instance();
        $sites = RPHUB_Database::get_all_sites();
        $ok = $fail = 0;

        foreach ($sites as $site) {
            if ($site->status !== 'active') {
                continue;
            }
            $result = $b2->push_config_to_care($site->id);
            is_wp_error($result) ? $fail++ : $ok++;
        }

        wp_send_json_success([
            'pushed'  => $ok,
            'failed'  => $fail,
            'message' => "B2 config enviada: {$ok} OK, {$fail} errores",
        ]);
    }
}
