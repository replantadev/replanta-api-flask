<?php
if (!defined('ABSPATH')) exit;

class RPHUB_WP_Fixer {

    private static array $plan_gates = [
        'semilla'    => ['wp_debug_off', 'heartbeat_optimize'],
        'raiz'       => ['wp_debug_off', 'heartbeat_optimize',
                         'db_clean_revisions', 'db_clean_transients', 'db_clean_spam', 'wp_memory_limit'],
        'ecosistema' => ['wp_debug_off', 'heartbeat_optimize',
                         'db_clean_revisions', 'db_clean_transients', 'db_clean_spam', 'wp_memory_limit',
                         'wp_cron_disable', 'ls_enable_object_cache'],
    ];

    public static function get_allowed_fixes(string $plan): array {
        return self::$plan_gates[$plan] ?? self::$plan_gates['semilla'];
    }

    public static function send_fix(int $site_id, string $fix_id, array $ctx = []): array {
        global $wpdb;
        $site = $wpdb->get_row(
            $wpdb->prepare("SELECT url, token FROM {$wpdb->prefix}rphub_sites WHERE id = %d", $site_id)
        );

        if (!$site || empty($site->token)) {
            return ['success' => false, 'error' => 'Sitio no encontrado o sin token'];
        }

        $endpoint = rtrim($site->url, '/') . '/wp-json/replanta/v1/execute-fix';
        $body     = array_merge(['fix_id' => $fix_id], $ctx);

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $site->token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($body),
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            return ['success' => false, 'error' => "Care respondió HTTP {$code}", 'details' => $data];
        }

        return $data ?? ['success' => true];
    }

    public static function send_plan_fixes(int $site_id, string $plan): array {
        $fixes   = self::get_allowed_fixes($plan);
        $results = [];
        foreach ($fixes as $fix_id) {
            $results[$fix_id] = self::send_fix($site_id, $fix_id);
        }
        return $results;
    }
}
