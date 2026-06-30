<?php
/**
 * Replanta Hub — Third-party integrations broker.
 *
 * Centralized storage and access for Google (GA4 + Search Console), Cloudflare,
 * and PageSpeed Insights. Stores tokens encrypted via RPHUB_Crypto.
 *
 * Options layout (per integration):
 *   rphub_google_oauth         — array{client_id, client_secret(encrypted), redirect_uri}
 *   rphub_google_refresh_token — encrypted refresh token (single org-wide identity)
 *   rphub_google_account_email — for display
 *   rphub_cloudflare_token     — encrypted CF API token (account-wide)
 *   rphub_psi_api_key          — encrypted PSI key
 *
 * Per-site mapping (lives in rphub_managed_sites[$site_id]['integrations']):
 *   integrations => [
 *     'ga4_property_id'   => '123456',
 *     'sc_site_url'       => 'https://example.com/',
 *     'cf_zone_id'        => 'abc...',
 *   ]
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPHUB_Integrations {

    const OPT_GOOGLE_OAUTH    = 'rphub_google_oauth';
    const OPT_GOOGLE_REFRESH  = 'rphub_google_refresh_token';
    const OPT_GOOGLE_EMAIL    = 'rphub_google_account_email';
    const OPT_GOOGLE_ACCESS   = 'rphub_google_access_token_cache';
    const OPT_CLOUDFLARE      = 'rphub_cloudflare_token';
    const OPT_PSI             = 'rphub_psi_api_key';

    const GOOGLE_SCOPES = 'https://www.googleapis.com/auth/analytics.readonly https://www.googleapis.com/auth/webmasters.readonly https://www.googleapis.com/auth/userinfo.email';

    /* ------------------------------------------------------------------ */
    /*  Google credentials                                                */
    /* ------------------------------------------------------------------ */

    public static function get_google_oauth_config() {
        $opt = get_option(self::OPT_GOOGLE_OAUTH, []);
        if (!is_array($opt)) $opt = [];
        return [
            'client_id'     => $opt['client_id']     ?? '',
            'client_secret' => !empty($opt['client_secret']) ? RPHUB_Crypto::decrypt($opt['client_secret']) : '',
            'redirect_uri'  => $opt['redirect_uri']  ?? self::default_redirect_uri(),
        ];
    }

    public static function save_google_oauth_config($client_id, $client_secret, $redirect_uri = '') {
        update_option(self::OPT_GOOGLE_OAUTH, [
            'client_id'     => sanitize_text_field($client_id),
            'client_secret' => !empty($client_secret) ? RPHUB_Crypto::encrypt($client_secret) : '',
            'redirect_uri'  => $redirect_uri ?: self::default_redirect_uri(),
        ], false);
    }

    public static function default_redirect_uri() {
        return admin_url('admin.php?page=replanta-hub-integrations&action=google_callback');
    }

    public static function is_google_connected() {
        $rt = get_option(self::OPT_GOOGLE_REFRESH, '');
        return !empty($rt);
    }

    public static function get_google_account_email() {
        return get_option(self::OPT_GOOGLE_EMAIL, '');
    }

    public static function get_google_authorization_url($state = '') {
        $cfg = self::get_google_oauth_config();
        if (empty($cfg['client_id'])) return '';

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'              => $cfg['client_id'],
            'redirect_uri'           => $cfg['redirect_uri'],
            'response_type'          => 'code',
            'scope'                  => self::GOOGLE_SCOPES,
            'access_type'            => 'offline',
            'prompt'                 => 'consent',
            'include_granted_scopes' => 'true',
            'state'                  => $state ?: wp_create_nonce('rphub_google_state'),
        ]);
    }

    public static function exchange_google_code($code) {
        $cfg = self::get_google_oauth_config();
        if (empty($cfg['client_id']) || empty($cfg['client_secret'])) {
            return new WP_Error('not_configured', 'Google OAuth no configurado');
        }

        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'timeout' => 20,
            'body'    => [
                'code'          => $code,
                'client_id'     => $cfg['client_id'],
                'client_secret' => $cfg['client_secret'],
                'redirect_uri'  => $cfg['redirect_uri'],
                'grant_type'    => 'authorization_code',
            ],
        ]);

        if (is_wp_error($response)) return $response;

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['refresh_token'])) {
            return new WP_Error('no_refresh_token', $body['error_description'] ?? 'Sin refresh_token (revoca acceso en Google y reintenta)');
        }

        update_option(self::OPT_GOOGLE_REFRESH, RPHUB_Crypto::encrypt($body['refresh_token']), false);

        if (!empty($body['access_token'])) {
            self::cache_access_token($body['access_token'], (int) ($body['expires_in'] ?? 3600));
        }

        // Fetch user email for display
        $email = self::fetch_google_email($body['access_token'] ?? '');
        if ($email) {
            update_option(self::OPT_GOOGLE_EMAIL, sanitize_email($email), false);
        }

        return ['success' => true, 'email' => $email];
    }

    private static function fetch_google_email($access_token) {
        if (empty($access_token)) return '';
        $r = wp_remote_get('https://www.googleapis.com/oauth2/v2/userinfo', [
            'timeout' => 10,
            'headers' => ['Authorization' => 'Bearer ' . $access_token],
        ]);
        if (is_wp_error($r)) return '';
        $b = json_decode(wp_remote_retrieve_body($r), true);
        return $b['email'] ?? '';
    }

    public static function disconnect_google() {
        delete_option(self::OPT_GOOGLE_REFRESH);
        delete_option(self::OPT_GOOGLE_EMAIL);
        delete_option(self::OPT_GOOGLE_ACCESS);
    }

    /**
     * Get a valid Google access token, refreshing if necessary.
     */
    public static function get_google_access_token() {
        $cache = get_option(self::OPT_GOOGLE_ACCESS, []);
        if (is_array($cache) && !empty($cache['token']) && !empty($cache['expires']) && $cache['expires'] > time() + 60) {
            return $cache['token'];
        }

        $refresh_encrypted = get_option(self::OPT_GOOGLE_REFRESH, '');
        if (empty($refresh_encrypted)) {
            return new WP_Error('not_connected', 'Google no conectado');
        }
        $refresh = RPHUB_Crypto::decrypt($refresh_encrypted);
        $cfg = self::get_google_oauth_config();

        $r = wp_remote_post('https://oauth2.googleapis.com/token', [
            'timeout' => 20,
            'body'    => [
                'client_id'     => $cfg['client_id'],
                'client_secret' => $cfg['client_secret'],
                'refresh_token' => $refresh,
                'grant_type'    => 'refresh_token',
            ],
        ]);
        if (is_wp_error($r)) return $r;
        $body = json_decode(wp_remote_retrieve_body($r), true);
        if (empty($body['access_token'])) {
            return new WP_Error('refresh_failed', $body['error_description'] ?? 'No se pudo refrescar el token');
        }
        self::cache_access_token($body['access_token'], (int) ($body['expires_in'] ?? 3600));
        return $body['access_token'];
    }

    private static function cache_access_token($token, $expires_in) {
        update_option(self::OPT_GOOGLE_ACCESS, [
            'token'   => $token,
            'expires' => time() + $expires_in,
        ], false);
    }

    /* ------------------------------------------------------------------ */
    /*  Cloudflare                                                        */
    /* ------------------------------------------------------------------ */

    public static function get_cloudflare_token() {
        $stored = get_option(self::OPT_CLOUDFLARE, '');
        return !empty($stored) ? RPHUB_Crypto::decrypt($stored) : '';
    }

    public static function save_cloudflare_token($token) {
        if (empty($token)) {
            delete_option(self::OPT_CLOUDFLARE);
            return;
        }
        update_option(self::OPT_CLOUDFLARE, RPHUB_Crypto::encrypt($token), false);
    }

    public static function is_cloudflare_connected() {
        return !empty(self::get_cloudflare_token());
    }

    /* ------------------------------------------------------------------ */
    /*  PageSpeed Insights                                                */
    /* ------------------------------------------------------------------ */

    public static function get_psi_key() {
        $stored = get_option(self::OPT_PSI, '');
        return !empty($stored) ? RPHUB_Crypto::decrypt($stored) : '';
    }

    public static function save_psi_key($key) {
        if (empty($key)) {
            delete_option(self::OPT_PSI);
            return;
        }
        update_option(self::OPT_PSI, RPHUB_Crypto::encrypt($key), false);
    }

    /* ------------------------------------------------------------------ */
    /*  Per-site mapping                                                   */
    /* ------------------------------------------------------------------ */

    public static function get_site_mapping($site_token) {
        // Prefer the new sites service (single source of truth, wp_rphub_sites table).
        if (class_exists('RPHUB_Sites')) {
            $site = RPHUB_Sites::get_by_token($site_token);
            if ($site) {
                return [
                    'id'           => $site['id'],
                    'url'          => $site['url'] ?? '',
                    'integrations' => $site['integrations'] ?? [],
                ];
            }
        }
        // Fallback to the legacy option for sites not yet migrated.
        $sites = get_option('rphub_managed_sites', []);
        if (!is_array($sites)) return null;
        foreach ($sites as $id => $site) {
            if (($site['token'] ?? '') === $site_token) {
                return [
                    'id'           => $id,
                    'url'          => $site['url'] ?? '',
                    'integrations' => $site['integrations'] ?? [],
                ];
            }
        }
        return null;
    }

    public static function save_site_mapping($site_id, $integrations) {
        if (class_exists('RPHUB_Sites')) {
            $site = is_numeric($site_id)
                ? RPHUB_Sites::get((int) $site_id)
                : (RPHUB_Sites::get_by_token($site_id) ?: RPHUB_Sites::get_by_domain($site_id));
            if ($site) {
                return RPHUB_Sites::save_integrations($site['id'], (array) $integrations);
            }
        }
        // Legacy fallback.
        $sites = get_option('rphub_managed_sites', []);
        if (!isset($sites[$site_id])) return false;
        $sites[$site_id]['integrations'] = array_map('sanitize_text_field', (array) $integrations);
        update_option('rphub_managed_sites', $sites);
        return true;
    }

    /* ------------------------------------------------------------------ */
    /*  Discovery helpers (UI: list available properties to pick)         */
    /* ------------------------------------------------------------------ */

    public static function list_ga4_properties() {
        $token = self::get_google_access_token();
        if (is_wp_error($token)) return $token;
        $r = wp_remote_get('https://analyticsadmin.googleapis.com/v1beta/accountSummaries', [
            'timeout' => 20,
            'headers' => ['Authorization' => 'Bearer ' . $token],
        ]);
        if (is_wp_error($r)) return $r;
        $body = json_decode(wp_remote_retrieve_body($r), true);
        $out = [];
        foreach ($body['accountSummaries'] ?? [] as $acct) {
            foreach ($acct['propertySummaries'] ?? [] as $prop) {
                $out[] = [
                    'property_id' => str_replace('properties/', '', $prop['property']),
                    'display'     => ($acct['displayName'] ?? '') . ' — ' . ($prop['displayName'] ?? ''),
                ];
            }
        }
        return $out;
    }

    public static function list_sc_sites() {
        $token = self::get_google_access_token();
        if (is_wp_error($token)) return $token;
        $r = wp_remote_get('https://www.googleapis.com/webmasters/v3/sites', [
            'timeout' => 20,
            'headers' => ['Authorization' => 'Bearer ' . $token],
        ]);
        if (is_wp_error($r)) return $r;
        $body = json_decode(wp_remote_retrieve_body($r), true);
        $out = [];
        foreach ($body['siteEntry'] ?? [] as $entry) {
            $out[] = [
                'site_url'        => $entry['siteUrl'] ?? '',
                'permission_level'=> $entry['permissionLevel'] ?? '',
            ];
        }
        return $out;
    }

    public static function list_cloudflare_zones() {
        $token = self::get_cloudflare_token();
        if (empty($token)) return new WP_Error('no_cf_token', 'Cloudflare no conectado');
        $r = wp_remote_get('https://api.cloudflare.com/client/v4/zones?per_page=50', [
            'timeout' => 20,
            'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
        ]);
        if (is_wp_error($r)) return $r;
        $body = json_decode(wp_remote_retrieve_body($r), true);
        $out = [];
        foreach ($body['result'] ?? [] as $zone) {
            $out[] = [
                'zone_id' => $zone['id'],
                'name'    => $zone['name'],
                'status'  => $zone['status'],
            ];
        }
        return $out;
    }
}
