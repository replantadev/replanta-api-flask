<?php
/**
 * 3-Phase WordPress Update Manager
 *
 * Phase 1 – Inventory : read-only scan of all sites' pending updates, cached 4h.
 * Phase 2 – Classification: risk-score each update (safe / review / blocked).
 *            Admins can override per-update via ajax_classify_override.
 * Phase 3 – Gated execution: backup first → update → health-check → rollback on failure.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPHUB_Update_Manager {

    const META_INVENTORY  = 'update_inventory';
    const META_OVERRIDES  = 'update_overrides';
    const CACHE_TTL       = HOUR_IN_SECONDS * 4;

    public function __construct() {
        if (did_action('init')) {
            $this->init();
        } else {
            add_action('init', [$this, 'init']);
        }
    }

    public function init() {
        add_action('wp_ajax_rphub_update_inventory',       [$this, 'ajax_update_inventory']);
        add_action('wp_ajax_rphub_update_classify_override', [$this, 'ajax_classify_override']);
        add_action('wp_ajax_rphub_execute_update_batch',   [$this, 'ajax_execute_update_batch']);
        add_action('rphub_hourly_tasks', [$this, 'maybe_refresh_inventory']);
    }

    // ─── Phase 1: Inventory ────────────────────────────────────────────────────

    public function ajax_update_inventory() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        $force   = !empty($_POST['force']);
        $site_id = intval($_POST['site_id'] ?? 0);
        wp_send_json_success($this->get_inventory($site_id ?: null, $force));
    }

    private function get_inventory($filter_site_id, $force = false) {
        global $wpdb;
        $tbl = $wpdb->prefix . 'rphub_sites';

        if ($filter_site_id) {
            $sites = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $tbl WHERE id = %d AND status = 'active'", $filter_site_id
            ));
        } else {
            $sites = $wpdb->get_results("SELECT * FROM $tbl WHERE status = 'active' ORDER BY name ASC");
        }

        $inventory = [];
        foreach ($sites as $site) {
            $cached    = $this->get_meta((int)$site->id, self::META_INVENTORY);
            $age       = $cached ? (time() - ($cached['fetched_at'] ?? 0)) : PHP_INT_MAX;

            if ($force || !$cached || $age > self::CACHE_TTL) {
                $cached = $this->fetch_site_updates($site);
                $this->save_meta((int)$site->id, self::META_INVENTORY, $cached);
            }

            $inventory[] = [
                'site_id'    => (int)$site->id,
                'site_name'  => $site->name,
                'site_url'   => $site->url,
                'updates'    => $cached['updates'] ?? [],
                'fetched_at' => $cached['fetched_at'] ?? 0,
                'summary'    => $this->summarize($cached['updates'] ?? []),
            ];
        }

        return $inventory;
    }

    private function fetch_site_updates($site) {
        $updates = [];

        // Try WP Toolkit first
        if (class_exists('ReplantaHub_WPToolkit_Integration')) {
            $wptk = new ReplantaHub_WPToolkit_Integration();
            if ($wptk->is_configured()) {
                $domain = parse_url($site->url, PHP_URL_HOST);
                $info   = $wptk->get_site_info($domain);
                if (!is_wp_error($info)) {
                    $updates = $this->parse_wptoolkit_info($info);
                }
            }
        }

        // Fallback: ask Care's REST endpoint
        if (empty($updates) && !empty($site->token)) {
            $updates = $this->fetch_via_care_api($site);
        }

        // Apply classification + saved overrides
        $overrides = $this->get_meta((int)$site->id, self::META_OVERRIDES) ?? [];
        foreach ($updates as &$u) {
            $key = $u['type'] . ':' . $u['slug'];
            if (isset($overrides[$key])) {
                $u['classification'] = $overrides[$key];
                $u['override']       = true;
            } else {
                $u['classification'] = $this->classify($u);
                $u['override']       = false;
            }
        }
        unset($u);

        return ['updates' => $updates, 'fetched_at' => time()];
    }

    private function parse_wptoolkit_info($info) {
        $updates = [];

        // Core
        if (
            !empty($info['wordpress_version']) &&
            !empty($info['wordpress_latest']) &&
            version_compare($info['wordpress_version'], $info['wordpress_latest'], '<')
        ) {
            $updates[] = [
                'type'       => 'core',
                'slug'       => 'wordpress',
                'name'       => 'WordPress Core',
                'from'       => $info['wordpress_version'],
                'to'         => $info['wordpress_latest'],
                'vulnerable' => false,
            ];
        }

        // Plugins
        foreach ($info['plugins'] ?? [] as $plugin) {
            if (!empty($plugin['update_available']) && !empty($plugin['update_version'])) {
                $updates[] = [
                    'type'       => 'plugin',
                    'slug'       => $plugin['slug'] ?? $plugin['name'],
                    'name'       => $plugin['name'] ?? $plugin['slug'],
                    'from'       => $plugin['version'] ?? '',
                    'to'         => $plugin['update_version'],
                    'vulnerable' => isset($plugin['vulnerability_status'])
                                    && $plugin['vulnerability_status'] !== 'safe',
                ];
            }
        }

        // Themes
        foreach ($info['themes'] ?? [] as $theme) {
            if (!empty($theme['update_available']) && !empty($theme['update_version'])) {
                $updates[] = [
                    'type'       => 'theme',
                    'slug'       => $theme['slug'] ?? $theme['name'],
                    'name'       => $theme['name'] ?? $theme['slug'],
                    'from'       => $theme['version'] ?? '',
                    'to'         => $theme['update_version'],
                    'vulnerable' => isset($theme['vulnerability_status'])
                                    && $theme['vulnerability_status'] !== 'safe',
                ];
            }
        }

        return $updates;
    }

    private function fetch_via_care_api($site) {
        if (empty($site->url) || empty($site->token)) {
            return [];
        }
        $url      = rtrim($site->url, '/') . '/wp-json/replanta-care/v1/updates';
        $response = wp_remote_get($url, [
            'headers' => ['Authorization' => 'Bearer ' . $site->token],
            'timeout' => 15,
        ]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return is_array($body['updates'] ?? null) ? $body['updates'] : [];
    }

    // ─── Phase 2: Classification ───────────────────────────────────────────────

    /**
     * Returns: 'safe' | 'review' | 'blocked'
     *
     * Rules:
     *   - vulnerable plugin WITH an update available → safe (update IS the fix)
     *   - core major bump (X.y → X+1.y) → review
     *   - plugin/theme major bump → review
     *   - everything else → safe
     */
    public function classify($update) {
        $from  = $update['from'] ?? '';
        $to    = $update['to']   ?? '';
        $type  = $update['type'] ?? 'plugin';

        if (empty($from) || empty($to)) {
            return 'review';
        }

        $fv = array_map('intval', explode('.', $from));
        $tv = array_map('intval', explode('.', $to));

        $major_bump = ($tv[0] ?? 0) > ($fv[0] ?? 0);

        // Core: major bump is review, minor/patch is safe
        if ($type === 'core') {
            return $major_bump ? 'review' : 'safe';
        }

        // Plugin / theme: major bump → review, minor/patch → safe
        return $major_bump ? 'review' : 'safe';
    }

    public function ajax_classify_override() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }

        $site_id    = intval($_POST['site_id'] ?? 0);
        $update_key = sanitize_text_field($_POST['update_key'] ?? '');
        $decision   = sanitize_text_field($_POST['decision'] ?? '');

        if (!$site_id || !$update_key || !in_array($decision, ['safe', 'review', 'blocked'], true)) {
            wp_send_json_error(['message' => 'Parámetros inválidos']); return;
        }

        $overrides               = $this->get_meta($site_id, self::META_OVERRIDES) ?? [];
        $overrides[$update_key]  = $decision;
        $this->save_meta($site_id, self::META_OVERRIDES, $overrides);

        // Re-apply to cached inventory so UI refreshes immediately
        $cached = $this->get_meta($site_id, self::META_INVENTORY);
        if ($cached) {
            foreach ($cached['updates'] as &$u) {
                if ($u['type'] . ':' . $u['slug'] === $update_key) {
                    $u['classification'] = $decision;
                    $u['override']       = true;
                }
            }
            unset($u);
            $this->save_meta($site_id, self::META_INVENTORY, $cached);
        }

        wp_send_json_success(['message' => 'Clasificación guardada.']);
    }

    // ─── Phase 3: Gated execution ──────────────────────────────────────────────

    public function ajax_execute_update_batch() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }

        $site_id     = intval($_POST['site_id'] ?? 0);
        $update_keys = array_map('sanitize_text_field', (array)($_POST['update_keys'] ?? []));

        if (!$site_id || empty($update_keys)) {
            wp_send_json_error(['message' => 'Faltan parámetros (site_id, update_keys)']); return;
        }

        $result = $this->execute_batch($site_id, $update_keys);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]); return;
        }

        wp_send_json_success($result);
    }

    private function execute_batch($site_id, $update_keys) {
        global $wpdb;
        $site = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rphub_sites WHERE id = %d AND status = 'active'",
            $site_id
        ));

        if (!$site) {
            return new WP_Error('site_not_found', 'Sitio no encontrado o inactivo.');
        }

        $cached = $this->get_meta($site_id, self::META_INVENTORY);
        if (!$cached) {
            return new WP_Error('no_inventory', 'Sin inventario. Ejecuta el escaneo (Fase 1) primero.');
        }

        $updates_map = [];
        foreach ($cached['updates'] ?? [] as $u) {
            $updates_map[$u['type'] . ':' . $u['slug']] = $u;
        }

        $executed = [];
        $skipped  = [];

        foreach ($update_keys as $key) {
            if (!isset($updates_map[$key])) continue;
            $u = $updates_map[$key];

            if (($u['classification'] ?? '') === 'blocked') {
                $skipped[] = ['key' => $key, 'reason' => 'blocked'];
                continue;
            }

            $executed[] = $this->execute_single($site, $u);
        }

        return ['executed' => $executed, 'skipped' => $skipped];
    }

    private function execute_single($site, $update) {
        $key    = $update['type'] . ':' . $update['slug'];
        $result = [
            'key'  => $key,
            'name' => $update['name'],
            'from' => $update['from'],
            'to'   => $update['to'],
        ];

        // Step 1 — Pre-update backup
        if (!$this->create_backup($site)) {
            $result['status']  = 'skipped';
            $result['message'] = 'No se pudo crear backup previo. Actualización omitida por seguridad.';
            return $result;
        }

        // Step 2 — Run update via WP Toolkit
        $ok = $this->run_wptoolkit_update($site, $update);
        if (is_wp_error($ok)) {
            $result['status']  = 'error';
            $result['message'] = $ok->get_error_message();
            return $result;
        }

        // Step 3 — Health check
        if (!$this->health_check($site->url)) {
            $this->rollback($site, $update);
            $result['status']  = 'rolled_back';
            $result['message'] = 'Salud del sitio degradada tras la actualización. Se restauró la versión anterior.';
            return $result;
        }

        $result['status']  = 'success';
        $result['message'] = "Actualizado de {$update['from']} a {$update['to']}.";
        return $result;
    }

    private function create_backup($site) {
        if (class_exists('ReplantaHub_Backuply_Integration')) {
            $backuply = new ReplantaHub_Backuply_Integration();
            if ($backuply->is_configured()) {
                $domain = parse_url($site->url, PHP_URL_HOST);
                $r      = $backuply->create_backup($domain);
                return !is_wp_error($r) && !empty($r['success']);
            }
        }
        // No backup integration — fail safe (don't update without a backup)
        error_log("[Replanta Hub] Pre-update backup skipped for site {$site->id}: no backup integration configured.");
        return false;
    }

    private function run_wptoolkit_update($site, $update) {
        if (!class_exists('ReplantaHub_WPToolkit_Integration')) {
            return new WP_Error('no_wptoolkit', 'WP Toolkit no disponible en este servidor.');
        }
        $wptk = new ReplantaHub_WPToolkit_Integration();
        if (!$wptk->is_configured()) {
            return new WP_Error('wptoolkit_unconfigured', 'WP Toolkit no está configurado.');
        }
        $domain = parse_url($site->url, PHP_URL_HOST);
        $result = $wptk->smart_update($domain, [
            'create_backup'     => false,  // backup already created in Phase 3 Step 1
            'maintenance_mode'  => true,
            'rollback_on_error' => false,  // we handle rollback ourselves in Step 3
            'components'        => [['type' => $update['type'], 'slug' => $update['slug']]],
        ]);
        if (is_wp_error($result)) {
            return $result;
        }
        return true;
    }

    private function health_check($url) {
        $response = wp_remote_head(rtrim($url, '/'), ['timeout' => 15, 'redirection' => 3]);
        if (is_wp_error($response)) {
            return false;
        }
        $code = wp_remote_retrieve_response_code($response);
        return $code >= 200 && $code < 500;
    }

    private function rollback($site, $update) {
        if (!class_exists('ReplantaHub_WPToolkit_Integration')) return;
        $wptk = new ReplantaHub_WPToolkit_Integration();
        if (!$wptk->is_configured()) return;
        $domain = parse_url($site->url, PHP_URL_HOST);
        try {
            $wptk->smart_update($domain, [
                'create_backup'     => false,
                'maintenance_mode'  => true,
                'rollback_on_error' => true,
                'rollback'          => true,
                'components'        => [['type' => $update['type'], 'slug' => $update['slug']]],
            ]);
        } catch (\Throwable $e) {
            error_log("[Replanta Hub] Rollback failed for {$site->url} {$update['slug']}: " . $e->getMessage());
        }
    }

    // Refresh stale inventory entries during hourly cron
    public function maybe_refresh_inventory() {
        $this->get_inventory(null, false);
    }

    // ─── rphub_site_meta helpers — delegate to RPHUB_Database ─────────────────

    private function get_meta($site_id, $key) {
        if (!class_exists('RPHUB_Database')) {
            return null;
        }
        $raw = RPHUB_Database::get_site_meta($site_id, $key, true);
        if (!$raw || !is_array($raw)) {
            return null;
        }
        return $raw;
    }

    private function save_meta($site_id, $key, $value) {
        if (!class_exists('RPHUB_Database')) {
            return;
        }
        RPHUB_Database::update_site_meta($site_id, $key, $value);
    }

    private function summarize($updates) {
        $s = ['total' => count($updates), 'safe' => 0, 'review' => 0, 'blocked' => 0];
        foreach ($updates as $u) {
            $c = $u['classification'] ?? 'review';
            if (array_key_exists($c, $s)) $s[$c]++;
        }
        return $s;
    }
}
