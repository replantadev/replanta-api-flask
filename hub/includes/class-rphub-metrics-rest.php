<?php
/**
 * Replanta Hub — Metrics REST proxy.
 *
 * Endpoints (namespace replanta-hub/v1):
 *   GET /metrics/ga4/{site_token}?days=30
 *   GET /metrics/sc/{site_token}?days=30
 *   GET /metrics/cloudflare/{site_token}?days=7
 *
 * Auth: site_token must exist in rphub_managed_sites. Additionally requires
 * the X-RPHUB-Token header to match the same token (defence in depth) so the
 * URL alone is not enough if it leaks in logs.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPHUB_Metrics_REST {

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        $args_token = [
            'site_token' => [
                'required' => true,
                'type'     => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'days' => [
                'required' => false,
                'type'     => 'integer',
                'default'  => 30,
                'sanitize_callback' => 'absint',
            ],
        ];

        register_rest_route('replanta-hub/v1', '/metrics/ga4/(?P<site_token>[A-Za-z0-9\-_]+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'ga4'],
            'permission_callback' => [$this, 'check_auth'],
            'args'                => $args_token,
        ]);

        register_rest_route('replanta-hub/v1', '/metrics/sc/(?P<site_token>[A-Za-z0-9\-_]+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'sc'],
            'permission_callback' => [$this, 'check_auth'],
            'args'                => $args_token,
        ]);

        register_rest_route('replanta-hub/v1', '/metrics/cloudflare/(?P<site_token>[A-Za-z0-9\-_]+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'cloudflare'],
            'permission_callback' => [$this, 'check_auth'],
            'args'                => array_merge($args_token, [
                'days' => [
                    'required' => false, 'type' => 'integer', 'default' => 7,
                    'sanitize_callback' => 'absint',
                ],
            ]),
        ]);

        register_rest_route('replanta-hub/v1', '/metrics/all/(?P<site_token>[A-Za-z0-9\-_]+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'all'],
            'permission_callback' => [$this, 'check_auth'],
            'args'                => $args_token,
        ]);
    }

    public function check_auth(WP_REST_Request $request) {
        $token = $request->get_param('site_token');
        if (empty($token)) return new WP_Error('no_token', 'site_token requerido', ['status' => 401]);

        $header = $request->get_header('x_rphub_token');
        if (empty($header) || !hash_equals($token, $header)) {
            return new WP_Error('bad_token', 'Token inválido', ['status' => 401]);
        }

        $mapping = RPHUB_Integrations::get_site_mapping($token);
        if (!$mapping) return new WP_Error('unknown_site', 'Sitio no registrado', ['status' => 404]);

        return true;
    }

    public function ga4(WP_REST_Request $r) {
        $res = RPHUB_Metrics::ga4($r->get_param('site_token'), max(1, (int) $r->get_param('days')));
        return $this->respond($res);
    }

    public function sc(WP_REST_Request $r) {
        $res = RPHUB_Metrics::search_console($r->get_param('site_token'), max(1, (int) $r->get_param('days')));
        return $this->respond($res);
    }

    public function cloudflare(WP_REST_Request $r) {
        $res = RPHUB_Metrics::cloudflare($r->get_param('site_token'), max(1, (int) $r->get_param('days')));
        return $this->respond($res);
    }

    public function all(WP_REST_Request $r) {
        $token = $r->get_param('site_token');
        $days  = max(1, (int) $r->get_param('days'));
        $out = [
            'ga4'        => $this->payload_or_error(RPHUB_Metrics::ga4($token, $days)),
            'sc'         => $this->payload_or_error(RPHUB_Metrics::search_console($token, $days)),
            'cloudflare' => $this->payload_or_error(RPHUB_Metrics::cloudflare($token, min($days, 30))),
        ];
        return rest_ensure_response($out);
    }

    private function respond($result) {
        if (is_wp_error($result)) {
            $code = $result->get_error_data();
            $status = is_array($code) && !empty($code['status']) ? $code['status'] : 502;
            return new WP_REST_Response([
                'error'   => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ], $status);
        }
        return rest_ensure_response($result);
    }

    private function payload_or_error($result) {
        if (is_wp_error($result)) {
            return [
                'error'   => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ];
        }
        return $result;
    }
}

new RPHUB_Metrics_REST();
