<?php
if (!defined('ABSPATH')) exit;

class RPHUB_DR_Bridge {

    public static function is_available(): bool {
        static $cache = null;
        if ($cache !== null) return $cache;
        global $wpdb;
        $table = $wpdb->prefix . 'dominios_reseller';
        $cache = (bool) $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
        return $cache;
    }

    public static function domain_from_url(string $url): string {
        $host = parse_url($url, PHP_URL_HOST) ?: $url;
        return strtolower(ltrim($host, 'www.'));
    }

    public static function get_domain_row(string $domain): ?array {
        if (!self::is_available()) return null;
        global $wpdb;
        $table = $wpdb->prefix . 'dominios_reseller';
        $bare  = self::domain_from_url($domain);

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE domain = %s OR domain = %s OR primary_domain = %s ORDER BY is_primary DESC LIMIT 1",
            $domain, $bare, $bare
        ), ARRAY_A);

        return $row ?: null;
    }

    public static function get_cf_zone(string $domain): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'dominios_reseller_cf_zones';
        if (!$wpdb->get_var("SHOW TABLES LIKE '{$table}'")) return null;

        $bare = self::domain_from_url($domain);
        $row  = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE (name = %s OR name = %s) AND deleted_at IS NULL LIMIT 1",
            $domain, $bare
        ), ARRAY_A);

        return $row ?: null;
    }

    public static function get_cf_onboarding(string $domain): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'dominios_reseller_cf_onboarding';
        if (!$wpdb->get_var("SHOW TABLES LIKE '{$table}'")) return null;

        $bare = self::domain_from_url($domain);
        $row  = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE primary_domain = %s OR primary_domain = %s LIMIT 1",
            $domain, $bare
        ), ARRAY_A);

        return $row ?: null;
    }

    public static function get_php_version(array $row): string {
        if (empty($row['php_info'])) return '';
        $data = json_decode($row['php_info'], true);
        if (!is_array($data)) return '';
        return $data['php_version'] ?? $data['version'] ?? '';
    }

    public static function enrich_site(int $site_id, string $url): array {
        $meta = [];
        $domain = self::domain_from_url($url);

        $dr_row = self::get_domain_row($domain);
        if ($dr_row) {
            $meta['whm_server']       = $dr_row['server']        ?? '';
            $meta['whm_status']       = $dr_row['status']        ?? '';
            $meta['whm_primary_domain'] = $dr_row['primary_domain'] ?? '';
            $meta['co2_evaded']       = $dr_row['co2_evaded']    ?? '';
            $meta['trees_planted']    = $dr_row['trees_planted'] ?? '';
            $meta['php_version_whm']  = self::get_php_version($dr_row);
        }

        $cf_zone = self::get_cf_zone($domain);
        if ($cf_zone) {
            $meta['cf_zone_id']     = $cf_zone['zone_id']   ?? '';
            $meta['cf_zone_status'] = $cf_zone['status']    ?? '';
            $meta['cf_plan_name']   = $cf_zone['plan_name'] ?? '';
        }

        $cf_ob = self::get_cf_onboarding($domain);
        if ($cf_ob) {
            $meta['cf_onboarding_state'] = $cf_ob['state'] ?? '';
        }

        $meta['dr_enriched_at'] = current_time('mysql');

        foreach ($meta as $key => $value) {
            RPHUB_Database::update_site_meta($site_id, $key, $value);
        }

        return $meta;
    }

    public static function get_site_dr_data(int $site_id): array {
        $keys = [
            'php_version_whm', 'whm_server', 'whm_status', 'whm_primary_domain',
            'co2_evaded', 'trees_planted',
            'cf_zone_id', 'cf_zone_status', 'cf_plan_name', 'cf_onboarding_state',
            'dr_enriched_at',
        ];
        $data = [];
        foreach ($keys as $key) {
            $data[$key] = RPHUB_Database::get_site_meta($site_id, $key);
        }
        return $data;
    }
}
