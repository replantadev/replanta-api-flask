<?php
/**
 * REST API for Replanta Hub
 * Handles external API calls from Care plugins
 */

class RPHUB_REST_API {
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    public function register_routes() {
        // Public: Care plugin update metadata (proxies GitHub releases API)
        register_rest_route('rphub/v1', '/care-release-info', [
            'methods'             => 'GET',
            'callback'            => [$this, 'care_release_info'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('api', '/sites/plan', [
            'methods' => 'GET',
            'callback' => [$this, 'get_site_plan'],
            'permission_callback' => [$this, 'validate_request'],
            'args' => [
                'domain' => [
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Domain of the site requesting plan info'
                ]
            ]
        ]);
        
        register_rest_route('api', '/sites/register', [
            'methods' => 'POST',
            'callback' => [$this, 'register_site'],
            'permission_callback' => [$this, 'validate_request'],
            'args' => [
                'domain' => [
                    'required' => true,
                    'type' => 'string'
                ],
                'plan' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => ['semilla', 'raiz', 'ecosistema']
                ]
            ]
        ]);
    }
    
    public function validate_request($request) {
        // 1. Allow authenticated WP admins (logged-in dashboard users)
        if (current_user_can('manage_options')) {
            return true;
        }

        // 2. Validate via site token (Care plugin requests)
        $site_token = $request->get_header('X-Site-Token');
        $site_url = $request->get_header('X-Site-URL');

        if (!empty($site_token) && !empty($site_url)) {
            global $wpdb;
            $site = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}rphub_sites WHERE token = %s AND url = %s AND status = 'active'",
                $site_token,
                $site_url
            ));
            if ($site) {
                return true;
            }
        }

        // 3. Validate via Bearer token (API tokens system)
        $auth_header = $request->get_header('Authorization');
        if (!empty($auth_header) && strpos($auth_header, 'Bearer ') === 0) {
            $token = substr($auth_header, 7);
            if (class_exists('ReplantaHub_API_Tokens')) {
                $api_tokens = new ReplantaHub_API_Tokens();
                $validated = $api_tokens->validate_token($token);
                if ($validated) {
                    return true;
                }
            }
        }

        return new WP_Error(
            'rest_unauthorized',
            'Authentication required. Provide X-Site-Token + X-Site-URL headers, or a Bearer token.',
            ['status' => 401]
        );
    }
    
    public function get_site_plan($request) {
        $domain = $request->get_param('domain');
        
        // If no domain provided, try to detect from request
        if (!$domain) {
            $domain = $this->detect_requesting_domain($request);
        }
        
        // Also check X-Site-Domain header
        $header_domain = $request->get_header('x-site-domain');
        if ($header_domain && !$domain) {
            $domain = $header_domain;
        }
        
        if (!$domain) {
            return new WP_Error(
                'no_domain',
                'Unable to detect requesting domain',
                ['status' => 400]
            );
        }
        
        // Get the plan for this domain from our sites database
        $plan = $this->get_domain_plan($domain);
        
        if (!$plan) {
            return new WP_Error(
                'site_not_found',
                'Site not found in hub registry',
                ['status' => 404]
            );
        }
        
        return rest_ensure_response([
            'success' => true,
            'domain' => $domain,
            'plan' => $plan,
            'features' => $this->get_plan_features($plan),
            'hub_connected' => true,
            'last_check' => current_time('mysql')
        ]);
    }
    
    public function register_site($request) {
        $domain = sanitize_text_field($request->get_param('domain'));
        $plan = sanitize_text_field($request->get_param('plan'));
        
        if (!in_array($plan, ['semilla', 'raiz', 'ecosistema'])) {
            return new WP_Error(
                'invalid_plan',
                'Invalid plan specified',
                ['status' => 400]
            );
        }
        
        // Persist via the unified sites service (writes to wp_rphub_sites and
        // keeps the legacy option in sync for transitional callers).
        if (class_exists('RPHUB_Sites')) {
            RPHUB_Sites::upsert_from_heartbeat([
                'domain' => $domain,
                'plan'   => $plan,
                'url'    => $request->get_param('url'),
                'care_url'   => $request->get_param('care_url'),
                'care_token' => $request->get_param('care_token'),
                'token'  => $request->get_param('token'),
            ]);
        } else {
            // Legacy path (kept for sites where the service is not yet loaded).
            $sites = get_option('rphub_managed_sites', []);
            $sites[$domain] = [
                'plan' => $plan,
                'registered_at' => current_time('mysql'),
                'last_seen' => current_time('mysql'),
                'status' => 'active'
            ];
            update_option('rphub_managed_sites', $sites);
        }

        return rest_ensure_response([
            'success' => true,
            'message' => 'Site registered successfully',
            'domain' => $domain,
            'plan' => $plan
        ]);
    }
    
    private function detect_requesting_domain($request) {
        // Try to get domain from custom headers first
        $site_domain = $request->get_header('x-site-domain');
        if ($site_domain) {
            return $site_domain;
        }
        
        $site_url = $request->get_header('x-site-url');
        if ($site_url) {
            $parsed = parse_url($site_url);
            if (isset($parsed['host'])) {
                return $parsed['host'];
            }
        }
        
        // Try to get domain from HTTP headers
        $referer = $request->get_header('referer');
        if ($referer) {
            $parsed = parse_url($referer);
            if (isset($parsed['host'])) {
                return $parsed['host'];
            }
        }
        
        // Try HTTP_HOST
        if (isset($_SERVER['HTTP_HOST'])) {
            return $_SERVER['HTTP_HOST'];
        }
        
        // Fallback to server name
        return $_SERVER['SERVER_NAME'] ?? 'unknown';
    }
    
    private function get_domain_plan($domain) {
        global $wpdb;
        
        // Check our sites database (registered sites only)
        $table_sites = $wpdb->prefix . 'rphub_sites';
        $site = $wpdb->get_row($wpdb->prepare(
            "SELECT plan FROM $table_sites WHERE url LIKE %s OR domain = %s",
            '%' . $wpdb->esc_like($domain) . '%',
            $domain
        ));
        
        if ($site && $site->plan) {
            return $site->plan;
        }
        
        // Check managed sites option as fallback
        $sites = get_option('rphub_managed_sites', []);
        if (isset($sites[$domain])) {
            return $sites[$domain]['plan'];
        }
        
        return null; // Site not found — must be registered first
    }
    
    private function get_plan_features($plan) {
        // Use the plans manager to get features
        if (class_exists('RPHUB_Plans_Manager')) {
            $plans_manager = new RPHUB_Plans_Manager();
            $plan_obj = $plans_manager->get_plan_by_slug($plan);
            
            if ($plan_obj) {
                $features = $plans_manager->get_plan_features($plan_obj->id);
                $formatted_features = [];
                
                foreach ($features as $feature) {
                    $formatted_features[$feature->feature_key] = $feature->feature_value;
                }
                
                return $formatted_features;
            }
        }
        
        // Fallback to legacy plan features
        $features = [
            'semilla' => [
                'updates_frequency' => 'monthly',
                'backup_frequency' => 'weekly',
                'wpo_optimization' => 'basic',
                'performance_review' => 'quarterly',
                'support_type' => 'email',
                'dashboard_access' => 'true',
                'update_control' => 'true'
            ],
            'raiz' => [
                'updates_frequency' => 'weekly',
                'backup_frequency' => 'weekly',
                'wpo_optimization' => 'basic',
                'performance_review' => 'quarterly',
                'support_type' => 'priority',
                'monitoring' => 'true',
                'seo_wpo_review' => 'monthly',
                'monthly_reports' => 'true',
                'dashboard_access' => 'advanced',
                'update_control' => 'true'
            ],
            'ecosistema' => [
                'updates_frequency' => 'weekly',
                'backup_frequency' => 'weekly',
                'wpo_optimization' => 'advanced',
                'performance_review' => 'quarterly',
                'support_type' => 'priority',
                'monitoring' => 'true',
                'seo_wpo_review' => 'quarterly',
                'monthly_reports' => 'true',
                'consulting' => 'quarterly',
                'hosting_included' => 'true',
                'cdn_optimization' => 'true',
                'dashboard_access' => 'premium',
                'update_control' => 'true'
            ]
        ];
        
        return isset($features[$plan]) ? $features[$plan] : $features['semilla'];
    }

    /**
     * GET /wp-json/rphub/v1/care-release-info
     * Returns PluginUpdateChecker-compatible JSON for the latest replanta-care release.
     * Publicly accessible (no auth) — only contains version info and download URL.
     */
    public function care_release_info($request) {
        // Serve from care-info.json (written by deploy_care) — no external API call needed.
        $uploads   = wp_upload_dir();
        $json_path = $uploads['basedir'] . '/replanta-updates/care-info.json';
        $info      = null;

        if (file_exists($json_path)) {
            $raw  = preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($json_path));
            $data = json_decode($raw, true);
            if (is_array($data) && !empty($data['version'])) {
                $info = $data;
            }
        }

        // Fallback: try the deploy endpoint internally
        if (!$info) {
            $deploy_url = rest_url('replanta-hub/v1/updates/care');
            $response   = wp_remote_get($deploy_url, ['timeout' => 10]);
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $raw  = preg_replace('/^\xEF\xBB\xBF/', '', wp_remote_retrieve_body($response));
                $data = json_decode($raw, true);
                if (is_array($data) && !empty($data['version'])) {
                    $info = $data;
                }
            }
        }

        if ($info) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                status_header(200);
                header('Content-Type: application/json; charset=utf-8');
                nocache_headers();
            }
            echo wp_json_encode($info);
            exit;
        }

        return new WP_Error('not_found', 'No hay información de actualización disponible.', ['status' => 404]);
    }
}

// Initialize the REST API
new RPHUB_REST_API();
