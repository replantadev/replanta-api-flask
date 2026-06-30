<?php
if (!defined('ABSPATH')) exit;

class RPHUB_CF_Fixer {

    private static array $plan_gates = [
        'semilla'    => ['always_use_https', 'brotli', 'http3', 'min_tls_12', 'dev_mode_off'],
        'raiz'       => ['always_use_https', 'brotli', 'http3', 'min_tls_12', 'dev_mode_off',
                         'early_hints', 'automatic_https_rewrites', 'rocket_loader_off'],
        'ecosistema' => ['always_use_https', 'brotli', 'http3', 'min_tls_12', 'dev_mode_off',
                         'early_hints', 'automatic_https_rewrites', 'rocket_loader_off',
                         'security_medium', 'hsts', 'bot_fight_mode', 'cf_purge_cache'],
    ];

    private static array $fix_map = [
        'always_use_https'        => ['setting' => 'always_use_https',        'value' => 'on'],
        'brotli'                  => ['setting' => 'brotli',                  'value' => 'on'],
        'http3'                   => ['setting' => 'http3',                   'value' => 'on'],
        'min_tls_12'              => ['setting' => 'min_tls_version',         'value' => '1.2'],
        'dev_mode_off'            => ['setting' => 'development_mode',        'value' => 'off'],
        'early_hints'             => ['setting' => 'early_hints',             'value' => 'on'],
        'automatic_https_rewrites'=> ['setting' => 'automatic_https_rewrites','value' => 'on'],
        'rocket_loader_off'       => ['setting' => 'rocket_loader',           'value' => 'off'],
        'security_medium'         => ['setting' => 'security_level',          'value' => 'medium'],
        'ssl_full'                => ['setting' => 'ssl',                     'value' => 'full'],
    ];

    public static function get_allowed_fixes(string $plan): array {
        return self::$plan_gates[$plan] ?? self::$plan_gates['semilla'];
    }

    public static function execute(string $zone_id, string $fix_id): array {
        if (!class_exists('Dominios_Reseller_Cloudflare_Service')) {
            return ['success' => false, 'error' => 'DR CF service unavailable'];
        }

        if ($fix_id === 'hsts') {
            return self::apply_hsts($zone_id);
        }

        if ($fix_id === 'cf_purge_cache') {
            return self::purge_cache($zone_id);
        }

        if ($fix_id === 'bot_fight_mode') {
            return self::apply_bot_fight_mode($zone_id);
        }

        if (!isset(self::$fix_map[$fix_id])) {
            return ['success' => false, 'error' => "Unknown fix: {$fix_id}"];
        }

        $map = self::$fix_map[$fix_id];
        $cf  = Dominios_Reseller_Cloudflare_Service::get_instance();
        $r   = $cf->set_zone_setting($zone_id, $map['setting'], $map['value']);

        if (is_wp_error($r)) {
            return ['success' => false, 'error' => $r->get_error_message()];
        }

        return ['success' => true, 'fix_id' => $fix_id, 'setting' => $map['setting'], 'value' => $map['value']];
    }

    public static function execute_plan_defaults(string $zone_id, string $plan): array {
        $fixes   = self::get_allowed_fixes($plan);
        $results = [];
        foreach ($fixes as $fix_id) {
            $results[$fix_id] = self::execute($zone_id, $fix_id);
        }
        return $results;
    }

    private static function apply_hsts(string $zone_id): array {
        $cf = Dominios_Reseller_Cloudflare_Service::get_instance();
        $r  = $cf->api_patch('/zones/' . $zone_id . '/settings/security_header', [
            'value' => [
                'strict_transport_security' => [
                    'enabled'            => true,
                    'max_age'            => 31536000,
                    'include_subdomains' => true,
                    'preload'            => false,
                ],
            ],
        ]);
        if (is_wp_error($r)) return ['success' => false, 'error' => $r->get_error_message()];
        return ['success' => true, 'fix_id' => 'hsts'];
    }

    private static function purge_cache(string $zone_id): array {
        $cf = Dominios_Reseller_Cloudflare_Service::get_instance();
        $r  = $cf->api_post('/zones/' . $zone_id . '/purge_cache', ['purge_everything' => true]);
        if (is_wp_error($r)) return ['success' => false, 'error' => $r->get_error_message()];
        return ['success' => true, 'fix_id' => 'cf_purge_cache'];
    }

    private static function apply_bot_fight_mode(string $zone_id): array {
        $cf = Dominios_Reseller_Cloudflare_Service::get_instance();
        $r  = $cf->api_put('/zones/' . $zone_id . '/bot_management', ['fight_mode' => true]);
        if (is_wp_error($r)) {
            // Fallback: try as a zone setting
            $r2 = $cf->set_zone_setting($zone_id, 'bfcache', 'on');
            if (is_wp_error($r2)) return ['success' => false, 'error' => $r->get_error_message()];
        }
        return ['success' => true, 'fix_id' => 'bot_fight_mode'];
    }
}
