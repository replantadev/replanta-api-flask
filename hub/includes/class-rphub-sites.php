<?php
/**
 * Replanta Hub — Sites service.
 *
 * Single source of truth for managed sites. Wraps wp_rphub_sites and exposes
 * helpers used by integrations, REST endpoints and admin pages.
 *
 * Replaces direct reads/writes of the legacy `rphub_managed_sites` option;
 * during the transition window (v1.9.x) the option is also kept in sync via
 * sync_to_legacy_option() so older callers that we have not yet refactored
 * keep working. The option will be dropped entirely in v2.0.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPHUB_Sites {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'rphub_sites';
    }

    public static function all($args = []) {
        global $wpdb;
        $t = self::table();
        $defaults = [
            'status'  => null,
            'orderby' => 'created_at',
            'order'   => 'DESC',
            'limit'   => 500,
        ];
        $args = array_merge($defaults, $args);

        $where = '1=1';
        $params = [];
        if ($args['status']) { $where .= ' AND status = %s'; $params[] = $args['status']; }

        $orderby = preg_match('/^[a-z_]+$/', $args['orderby']) ? $args['orderby'] : 'created_at';
        $order   = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $limit   = (int) $args['limit'];

        $sql = "SELECT * FROM $t WHERE $where ORDER BY $orderby $order LIMIT $limit";
        $rows = $params
            ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A)
            : $wpdb->get_results($sql, ARRAY_A);

        return array_map([__CLASS__, 'hydrate'], $rows ?: []);
    }

    public static function get($id) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::table() . " WHERE id = %d", (int) $id), ARRAY_A);
        return $row ? self::hydrate($row) : null;
    }

    public static function get_by_token($token) {
        if (empty($token)) return null;
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE token = %s LIMIT 1",
            $token
        ), ARRAY_A);
        return $row ? self::hydrate($row) : null;
    }

    public static function get_by_domain($domain) {
        if (empty($domain)) return null;
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE domain = %s LIMIT 1",
            $domain
        ), ARRAY_A);
        return $row ? self::hydrate($row) : null;
    }

    public static function get_by_care_url($care_url) {
        if (empty($care_url)) return null;
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE care_url = %s LIMIT 1",
            $care_url
        ), ARRAY_A);
        return $row ? self::hydrate($row) : null;
    }

    /**
     * Hydrate a raw DB row: decode JSON `integrations`, cast numerics, expose
     * a stable `integrations` array even when columns are populated piecewise.
     */
    private static function hydrate(array $row) {
        $integrations = [];
        if (!empty($row['integrations'])) {
            $decoded = json_decode($row['integrations'], true);
            if (is_array($decoded)) $integrations = $decoded;
        }
        // Column-level values always override the JSON blob (column is canonical).
        if (!empty($row['ga4_property_id'])) $integrations['ga4_property_id'] = $row['ga4_property_id'];
        if (!empty($row['sc_site_url']))     $integrations['sc_site_url']     = $row['sc_site_url'];
        if (!empty($row['cf_zone_id']))      $integrations['cf_zone_id']      = $row['cf_zone_id'];
        $row['integrations'] = $integrations;

        foreach (['id','health_score','performance_score','security_score'] as $n) {
            if (isset($row[$n])) $row[$n] = (int) $row[$n];
        }
        return $row;
    }

    /**
     * Upsert from Care heartbeat. Matches by token first, then by care_url/domain.
     * Only updates fields supplied by Care; leaves the rest intact.
     */
    public static function upsert_from_heartbeat(array $payload) {
        global $wpdb;
        $domain   = sanitize_text_field($payload['domain']     ?? '');
        $care_url = esc_url_raw($payload['care_url']           ?? '');
        $token    = sanitize_text_field($payload['token']      ?? '');
        if (empty($domain) && empty($care_url) && empty($token)) {
            return new WP_Error('bad_payload', 'Heartbeat sin domain/care_url/token');
        }
        if (empty($domain)) {
            $domain = parse_url($care_url, PHP_URL_HOST) ?: '';
        }

        $existing = null;
        if ($token)    $existing = self::get_by_token($token);
        if (!$existing && $care_url) $existing = self::get_by_care_url($care_url);
        if (!$existing && $domain)   $existing = self::get_by_domain($domain);

        $row = [
            'domain'         => $domain,
            'url'            => esc_url_raw($payload['url'] ?? $care_url ?? ('https://' . $domain)),
            'care_url'       => $care_url ?: ($existing['care_url'] ?? ''),
            'care_token'     => sanitize_text_field($payload['care_token'] ?? ($existing['care_token'] ?? '')),
            'token'          => $token ?: ($existing['token'] ?? ''),
            'plan'           => sanitize_text_field($payload['plan'] ?? ($existing['plan'] ?? 'semilla')),
            'status'         => 'active',
            'last_seen'      => current_time('mysql'),
            'source'         => $existing ? ($existing['source'] ?? 'care_heartbeat') : 'care_heartbeat',
        ];
        if (!empty($payload['last_backup'])) $row['last_backup'] = sanitize_text_field($payload['last_backup']);
        if (!empty($payload['last_update'])) $row['last_update'] = sanitize_text_field($payload['last_update']);

        $t = self::table();
        if ($existing) {
            $wpdb->update($t, $row, ['id' => $existing['id']]);
            $id = (int) $existing['id'];
        } else {
            $row['name']          = $domain;
            $row['registered_at'] = current_time('mysql');
            $row['created_at']    = current_time('mysql');
            $wpdb->insert($t, $row);
            $id = (int) $wpdb->insert_id;
        }

        self::sync_to_legacy_option();
        return self::get($id);
    }

    public static function save_integrations($id_or_token, array $integrations) {
        $site = ctype_digit((string) $id_or_token)
            ? self::get((int) $id_or_token)
            : self::get_by_token($id_or_token);
        if (!$site) return false;

        $integrations = array_map('sanitize_text_field', $integrations);
        $clean = array_intersect_key($integrations, array_flip([
            'ga4_property_id', 'sc_site_url', 'cf_zone_id',
        ]));

        global $wpdb;
        $wpdb->update(self::table(), [
            'ga4_property_id' => $clean['ga4_property_id'] ?? '',
            'sc_site_url'     => $clean['sc_site_url']     ?? '',
            'cf_zone_id'      => $clean['cf_zone_id']      ?? '',
            'integrations'    => wp_json_encode($clean),
        ], ['id' => $site['id']]);

        self::sync_to_legacy_option();
        return true;
    }

    public static function update_plan($id, $plan) {
        global $wpdb;
        $r = $wpdb->update(self::table(), [
            'plan' => sanitize_text_field($plan),
        ], ['id' => (int) $id]);
        if ($r !== false) self::sync_to_legacy_option();
        return $r !== false;
    }

    public static function delete($id) {
        global $wpdb;
        $r = $wpdb->delete(self::table(), ['id' => (int) $id]);
        if ($r) self::sync_to_legacy_option();
        return (bool) $r;
    }

    /**
     * Rebuild the legacy rphub_managed_sites option from the table so any code
     * we have not yet refactored keeps working during the transition window.
     * Throttled to once every 30s to avoid pathological write rates.
     */
    public static function sync_to_legacy_option() {
        $last = (int) get_option('rphub_managed_sites_sync_at', 0);
        if (time() - $last < 30) return;

        $sites = self::all(['limit' => 1000]);
        $out = [];
        foreach ($sites as $s) {
            $key = $s['domain'] ?: ('site-' . $s['id']);
            $out[$key] = [
                'domain'        => $s['domain'],
                'url'           => $s['url'],
                'care_url'      => $s['care_url'],
                'care_token'    => $s['care_token'],
                'token'         => $s['token'],
                'plan'          => $s['plan'],
                'status'        => $s['status'],
                'registered_at' => $s['registered_at'],
                'last_seen'     => $s['last_seen'],
                'integrations'  => $s['integrations'],
                'updated_at'    => $s['updated_at'] ?? current_time('mysql'),
            ];
        }
        update_option('rphub_managed_sites', $out, false);
        update_option('rphub_managed_sites_sync_at', time(), false);
    }
}
