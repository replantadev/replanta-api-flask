<?php
/**
 * RESTful API System for Replanta Hub Professional
 * 
 * Comprehensive API for external integrations and third-party services
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ReplantaHub_API_System {
    
    private $namespace;
    private $version;
    private $rate_limiter;
    private $auth_handler;
    
    public function __construct() {
        $this->namespace = 'rphub/v1';
        $this->version = '1.0';
        $this->init_hooks();
        $this->init_rate_limiter();
        $this->init_auth_handler();
    }
    
    private function init_hooks() {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_filter('rest_pre_dispatch', array($this, 'handle_preflight'), 10, 3);
        add_filter('rest_post_dispatch', array($this, 'add_cors_headers'), 10, 3);
        
        // Authentication hooks
        add_filter('determine_current_user', array($this, 'authenticate_api_request'), 20);
        
        // Rate limiting
        add_action('rest_api_init', array($this, 'setup_rate_limiting'));
    }
    
    private function init_rate_limiter() {
        $this->rate_limiter = array(
            'requests_per_minute' => 60,
            'requests_per_hour' => 1000,
            'requests_per_day' => 10000,
            'burst_limit' => 10
        );
    }
    
    private function init_auth_handler() {
        $this->auth_handler = array(
            'api_key_header' => 'X-RPHUB-API-Key',
            'jwt_header' => 'Authorization',
            'webhook_secret_header' => 'X-RPHUB-Webhook-Secret'
        );
    }
    
    /**
     * Register all API routes
     */
    public function register_routes() {
        // Sites Management
        $this->register_sites_routes();
        
        // Analytics & Reports
        $this->register_analytics_routes();
        
        // Monitoring & Health
        $this->register_monitoring_routes();
        
        // Automation & Workflows
        $this->register_automation_routes();
        
        // Integrations Management
        $this->register_integrations_routes();
        
        // Webhooks
        $this->register_webhook_routes();
        
        // Authentication & Users
        $this->register_auth_routes();
        
        // System Management
        $this->register_system_routes();
    }
    
    /**
     * Register sites management routes
     */
    private function register_sites_routes() {
        // GET /rphub/v1/sites - List all sites
        register_rest_route($this->namespace, '/sites', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_sites'),
            'permission_callback' => array($this, 'check_api_permissions'),
            'args' => array(
                'page' => array(
                    'default' => 1,
                    'sanitize_callback' => 'absint'
                ),
                'per_page' => array(
                    'default' => 20,
                    'sanitize_callback' => 'absint'
                ),
                'status' => array(
                    'default' => 'all',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'search' => array(
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
        
        // POST /rphub/v1/sites - Create new site
        register_rest_route($this->namespace, '/sites', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_site'),
            'permission_callback' => array($this, 'check_write_permissions'),
            'args' => array(
                'name' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'url' => array(
                    'required' => true,
                    'sanitize_callback' => 'esc_url_raw'
                ),
                'type' => array(
                    'default' => 'wordpress',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
        
        // GET /rphub/v1/sites/{id} - Get specific site
        register_rest_route($this->namespace, '/sites/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_site'),
            'permission_callback' => array($this, 'check_api_permissions'),
            'args' => array(
                'id' => array(
                    'required' => true,
                    'sanitize_callback' => 'absint'
                )
            )
        ));
        
        // PUT /rphub/v1/sites/{id} - Update site
        register_rest_route($this->namespace, '/sites/(?P<id>\d+)', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_site'),
            'permission_callback' => array($this, 'check_write_permissions'),
            'args' => array(
                'id' => array(
                    'required' => true,
                    'sanitize_callback' => 'absint'
                )
            )
        ));
        
        // DELETE /rphub/v1/sites/{id} - Delete site
        register_rest_route($this->namespace, '/sites/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete_site'),
            'permission_callback' => array($this, 'check_delete_permissions'),
            'args' => array(
                'id' => array(
                    'required' => true,
                    'sanitize_callback' => 'absint'
                )
            )
        ));
        
        // POST /rphub/v1/sites/{id}/scan - Scan site
        register_rest_route($this->namespace, '/sites/(?P<id>\d+)/scan', array(
            'methods' => 'POST',
            'callback' => array($this, 'scan_site'),
            'permission_callback' => array($this, 'check_write_permissions'),
            'args' => array(
                'id' => array(
                    'required' => true,
                    'sanitize_callback' => 'absint'
                ),
                'scan_type' => array(
                    'default' => 'full',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
    }
    
    /**
     * Register analytics routes
     */
    private function register_analytics_routes() {
        // GET /rphub/v1/analytics/overview - Analytics overview
        register_rest_route($this->namespace, '/analytics/overview', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_analytics_overview'),
            'permission_callback' => array($this, 'check_api_permissions'),
            'args' => array(
                'site_id' => array(
                    'sanitize_callback' => 'absint'
                ),
                'date_range' => array(
                    'default' => '30d',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
        
        // GET /rphub/v1/analytics/performance - Performance metrics
        register_rest_route($this->namespace, '/analytics/performance', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_performance_metrics'),
            'permission_callback' => array($this, 'check_api_permissions'),
            'args' => array(
                'site_id' => array(
                    'sanitize_callback' => 'absint'
                ),
                'metric_type' => array(
                    'default' => 'web_vitals',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
        
        // GET /rphub/v1/analytics/comparative - Comparative analytics
        register_rest_route($this->namespace, '/analytics/comparative', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_comparative_analytics'),
            'permission_callback' => array($this, 'check_api_permissions'),
            'args' => array(
                'group_id' => array(
                    'sanitize_callback' => 'absint'
                ),
                'benchmark_type' => array(
                    'default' => 'industry',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
        
        // POST /rphub/v1/analytics/reports - Generate report
        register_rest_route($this->namespace, '/analytics/reports', array(
            'methods' => 'POST',
            'callback' => array($this, 'generate_analytics_report'),
            'permission_callback' => array($this, 'check_write_permissions'),
            'args' => array(
                'report_type' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'site_ids' => array(
                    'sanitize_callback' => array($this, 'sanitize_array_int')
                ),
                'format' => array(
                    'default' => 'json',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
    }
    
    /**
     * Register monitoring routes
     */
    private function register_monitoring_routes() {
        // GET /rphub/v1/monitoring/uptime - Uptime status
        register_rest_route($this->namespace, '/monitoring/uptime', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_uptime_status'),
            'permission_callback' => array($this, 'check_api_permissions'),
            'args' => array(
                'site_id' => array(
                    'sanitize_callback' => 'absint'
                ),
                'period' => array(
                    'default' => '24h',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
        
        // GET /rphub/v1/monitoring/health - Health checks
        register_rest_route($this->namespace, '/monitoring/health', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_health_status'),
            'permission_callback' => array($this, 'check_api_permissions'),
            'args' => array(
                'site_id' => array(
                    'sanitize_callback' => 'absint'
                ),
                'check_type' => array(
                    'default' => 'all',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
        
        // GET /rphub/v1/monitoring/alerts - Active alerts
        register_rest_route($this->namespace, '/monitoring/alerts', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_active_alerts'),
            'permission_callback' => array($this, 'check_api_permissions'),
            'args' => array(
                'severity' => array(
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'status' => array(
                    'default' => 'active',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
        
        // POST /rphub/v1/monitoring/alerts/{id}/acknowledge - Acknowledge alert
        register_rest_route($this->namespace, '/monitoring/alerts/(?P<id>\d+)/acknowledge', array(
            'methods' => 'POST',
            'callback' => array($this, 'acknowledge_alert'),
            'permission_callback' => array($this, 'check_write_permissions'),
            'args' => array(
                'id' => array(
                    'required' => true,
                    'sanitize_callback' => 'absint'
                ),
                'note' => array(
                    'sanitize_callback' => 'sanitize_textarea_field'
                )
            )
        ));
    }
    
    /**
     * Register automation routes
     */
    private function register_automation_routes() {
        // GET /rphub/v1/automation/workflows - List workflows
        register_rest_route($this->namespace, '/automation/workflows', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_workflows'),
            'permission_callback' => array($this, 'check_api_permissions')
        ));
        
        // POST /rphub/v1/automation/workflows - Create workflow
        register_rest_route($this->namespace, '/automation/workflows', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_workflow'),
            'permission_callback' => array($this, 'check_write_permissions'),
            'args' => array(
                'name' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'trigger' => array(
                    'required' => true,
                    'sanitize_callback' => array($this, 'sanitize_workflow_trigger')
                ),
                'actions' => array(
                    'required' => true,
                    'sanitize_callback' => array($this, 'sanitize_workflow_actions')
                )
            )
        ));
        
        // POST /rphub/v1/automation/workflows/{id}/execute - Execute workflow
        register_rest_route($this->namespace, '/automation/workflows/(?P<id>[\w-]+)/execute', array(
            'methods' => 'POST',
            'callback' => array($this, 'execute_workflow'),
            'permission_callback' => array($this, 'check_write_permissions'),
            'args' => array(
                'id' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'force' => array(
                    'default' => false,
                    'sanitize_callback' => 'rest_sanitize_boolean'
                )
            )
        ));
        
        // POST /rphub/v1/automation/maintenance - Run maintenance
        register_rest_route($this->namespace, '/automation/maintenance', array(
            'methods' => 'POST',
            'callback' => array($this, 'run_maintenance'),
            'permission_callback' => array($this, 'check_write_permissions'),
            'args' => array(
                'site_id' => array(
                    'sanitize_callback' => 'absint'
                ),
                'maintenance_type' => array(
                    'default' => 'intelligent',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
    }
    
    /**
     * Register integrations routes
     */
    private function register_integrations_routes() {
        // GET /rphub/v1/integrations - List integrations
        register_rest_route($this->namespace, '/integrations', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_integrations'),
            'permission_callback' => array($this, 'check_api_permissions')
        ));
        
        // POST /rphub/v1/integrations/{service}/connect - Connect integration
        register_rest_route($this->namespace, '/integrations/(?P<service>[\w-]+)/connect', array(
            'methods' => 'POST',
            'callback' => array($this, 'connect_integration'),
            'permission_callback' => array($this, 'check_write_permissions'),
            'args' => array(
                'service' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'credentials' => array(
                    'required' => true,
                    'sanitize_callback' => array($this, 'sanitize_credentials')
                )
            )
        ));
        
        // POST /rphub/v1/integrations/{service}/test - Test integration
        register_rest_route($this->namespace, '/integrations/(?P<service>[\w-]+)/test', array(
            'methods' => 'POST',
            'callback' => array($this, 'test_integration'),
            'permission_callback' => array($this, 'check_api_permissions'),
            'args' => array(
                'service' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
    }
    
    /**
     * Register webhook routes
     */
    private function register_webhook_routes() {
        // POST /rphub/v1/webhooks/cloudflare - Cloudflare webhook
        register_rest_route($this->namespace, '/webhooks/cloudflare', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_cloudflare_webhook'),
            'permission_callback' => array($this, 'verify_webhook_signature')
        ));
        
        // POST /rphub/v1/webhooks/backuply - Backuply webhook
        register_rest_route($this->namespace, '/webhooks/backuply', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_backuply_webhook'),
            'permission_callback' => array($this, 'verify_webhook_signature')
        ));
        
        // POST /rphub/v1/webhooks/uptime - Uptime monitoring webhook
        register_rest_route($this->namespace, '/webhooks/uptime', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_uptime_webhook'),
            'permission_callback' => array($this, 'verify_webhook_signature')
        ));
        
        // POST /rphub/v1/webhooks/security - Security alerts webhook
        register_rest_route($this->namespace, '/webhooks/security', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_security_webhook'),
            'permission_callback' => array($this, 'verify_webhook_signature')
        ));

    }
    
    /**
     * Register authentication routes
     */
    private function register_auth_routes() {
        // POST /rphub/v1/auth/token - Generate API token
        register_rest_route($this->namespace, '/auth/token', array(
            'methods' => 'POST',
            'callback' => array($this, 'generate_api_token'),
            'permission_callback' => array($this, 'check_admin_permissions'),
            'args' => array(
                'name' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'permissions' => array(
                    'required' => true,
                    'sanitize_callback' => array($this, 'sanitize_permissions')
                ),
                'expires_in' => array(
                    'default' => '1y',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
        
        // DELETE /rphub/v1/auth/token/{token_id} - Revoke API token
        register_rest_route($this->namespace, '/auth/token/(?P<token_id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'revoke_api_token'),
            'permission_callback' => array($this, 'check_admin_permissions'),
            'args' => array(
                'token_id' => array(
                    'required' => true,
                    'sanitize_callback' => 'absint'
                )
            )
        ));
        
        // GET /rphub/v1/auth/tokens - List API tokens
        register_rest_route($this->namespace, '/auth/tokens', array(
            'methods' => 'GET',
            'callback' => array($this, 'list_api_tokens'),
            'permission_callback' => array($this, 'check_admin_permissions')
        ));
    }
    
    /**
     * Register system routes
     */
    private function register_system_routes() {
        // GET /rphub/v1/system/status - System status
        register_rest_route($this->namespace, '/system/status', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_system_status'),
            'permission_callback' => array($this, 'check_api_permissions')
        ));
        
        // GET /rphub/v1/system/info - System information
        register_rest_route($this->namespace, '/system/info', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_system_info'),
            'permission_callback' => array($this, 'check_admin_permissions')
        ));
        
        // POST /rphub/v1/system/backup - Create system backup
        register_rest_route($this->namespace, '/system/backup', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_system_backup'),
            'permission_callback' => array($this, 'check_admin_permissions'),
            'args' => array(
                'include_files' => array(
                    'default' => true,
                    'sanitize_callback' => 'rest_sanitize_boolean'
                ),
                'include_database' => array(
                    'default' => true,
                    'sanitize_callback' => 'rest_sanitize_boolean'
                )
            )
        ));

        // GET /rphub/v1/openapi - OpenAPI 3.0 specification (public, read-only)
        register_rest_route($this->namespace, '/openapi', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_openapi_spec'),
            'permission_callback' => '__return_true',
        ));
    }
    
    /**
     * API Route Handlers
     */
    
    /**
     * Get sites list
     */
    public function get_sites($request) {
        if (!$this->check_rate_limit($request)) {
            return new WP_Error('rate_limit_exceeded', 'Rate limit exceeded', array('status' => 429));
        }
        
        global $wpdb;
        
        $page = $request->get_param('page');
        $per_page = min($request->get_param('per_page'), 100); // Max 100 per page
        $status = $request->get_param('status');
        $search = $request->get_param('search');
        
        $offset = ($page - 1) * $per_page;
        
        $where_conditions = array();
        $where_values = array();
        
        if ($status !== 'all') {
            $where_conditions[] = "status = %s";
            $where_values[] = $status;
        }
        
        if (!empty($search)) {
            $where_conditions[] = "(name LIKE %s OR url LIKE %s)";
            $where_values[] = '%' . $wpdb->esc_like($search) . '%';
            $where_values[] = '%' . $wpdb->esc_like($search) . '%';
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $query = "SELECT * FROM {$wpdb->prefix}rphub_sites {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $where_values[] = $per_page;
        $where_values[] = $offset;
        
        $sites = $wpdb->get_results($wpdb->prepare($query, ...$where_values));

        // Get total count for pagination
        $count_query = "SELECT COUNT(*) FROM {$wpdb->prefix}rphub_sites {$where_clause}";
        $count_where_values = array_slice($where_values, 0, -2);
        $total = $wpdb->get_var(
            !empty($count_where_values)
                ? $wpdb->prepare($count_query, ...$count_where_values)
                : $count_query
        );
        
        $formatted_sites = array_map(array($this, 'format_site_response'), $sites ?? []);
        
        return new WP_REST_Response(array(
            'sites' => $formatted_sites,
            'pagination' => array(
                'page' => $page,
                'per_page' => $per_page,
                'total' => intval($total),
                'pages' => ceil($total / $per_page)
            )
        ), 200);
    }
    
    /**
     * Create new site
     */
    public function create_site($request) {
        if (!$this->check_rate_limit($request)) {
            return new WP_Error('rate_limit_exceeded', 'Rate limit exceeded', array('status' => 429));
        }
        
        global $wpdb;
        
        $name = $request->get_param('name');
        $url = $request->get_param('url');
        $type = $request->get_param('type');
        
        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', 'Invalid URL provided', array('status' => 400));
        }
        
        // Check if site already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}rphub_sites WHERE url = %s",
            $url
        ));
        
        if ($existing) {
            return new WP_Error('site_exists', 'Site with this URL already exists', array('status' => 409));
        }
        
        $site_data = array(
            'name' => $name,
            'url' => $url,
            'type' => $type,
            'status' => 'active',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($wpdb->prefix . 'rphub_sites', $site_data);
        
        if ($result === false) {
            return new WP_Error('creation_failed', 'Failed to create site', array('status' => 500));
        }
        
        $site_id = $wpdb->insert_id;
        $site = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rphub_sites WHERE id = %d",
            $site_id
        ));
        
        return new WP_REST_Response(array(
            'message' => 'Site created successfully',
            'site' => $this->format_site_response($site)
        ), 201);
    }
    
    /**
     * Get analytics overview
     */
    public function get_analytics_overview($request) {
        if (!$this->check_rate_limit($request)) {
            return new WP_Error('rate_limit_exceeded', 'Rate limit exceeded', array('status' => 429));
        }
        
        $site_id = $request->get_param('site_id');
        $date_range = $request->get_param('date_range');
        
        // Initialize analytics integration
        if (!class_exists('ReplantaHub_Analytics_Integration')) {
            return new WP_Error('analytics_unavailable', 'Analytics integration not available', array('status' => 503));
        }
        
        $analytics = new ReplantaHub_Analytics_Integration();
        
        try {
            $overview_data = array(
                'performance' => $this->get_performance_overview_data($site_id, $date_range),
                'traffic' => $this->get_traffic_overview_data($site_id, $date_range),
                'user_experience' => $this->get_ux_overview_data($site_id, $date_range),
                'seo' => $this->get_seo_overview_data($site_id, $date_range)
            );
            
            return new WP_REST_Response($overview_data, 200);
            
        } catch (Exception $e) {
            return new WP_Error('analytics_error', 'Analytics data unavailable: ' . $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Get performance overview data
     */
    private function get_performance_overview_data($site_id, $date_range) {
        global $wpdb;
        
        $where_clause = '';
        $params = array();
        
        if ($site_id) {
            $where_clause = 'WHERE site_id = %d';
            $params[] = $site_id;
        }
        
        // Get Web Vitals data
        $web_vitals = $wpdb->get_row($wpdb->prepare("
            SELECT 
                AVG(JSON_EXTRACT(data, '$.lcp.p75')) as avg_lcp,
                AVG(JSON_EXTRACT(data, '$.fid.p75')) as avg_fid,
                AVG(JSON_EXTRACT(data, '$.cls.p75')) as avg_cls,
                AVG(JSON_EXTRACT(data, '$.ttfb.p75')) as avg_ttfb
            FROM {$wpdb->prefix}rphub_analytics_web_vitals 
            {$where_clause}
            AND collected_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ", $params));
        
        return array(
            'web_vitals' => array(
                'lcp' => round($web_vitals->avg_lcp ?? 0, 2),
                'fid' => round($web_vitals->avg_fid ?? 0, 2),
                'cls' => round($web_vitals->avg_cls ?? 0, 3),
                'ttfb' => round($web_vitals->avg_ttfb ?? 0, 2)
            ),
            'score' => $this->calculate_performance_score($web_vitals)
        );
    }
    
    /**
     * Get traffic overview data
     */
    private function get_traffic_overview_data($site_id, $date_range) {
        global $wpdb;
        
        $where_clause = '';
        $params = array();
        
        if ($site_id) {
            $where_clause = 'WHERE site_id = %d';
            $params[] = $site_id;
        }
        
        // Get Google Analytics data
        $ga_data = $wpdb->get_row($wpdb->prepare("
            SELECT 
                SUM(JSON_EXTRACT(data, '$.sessions')) as total_sessions,
                SUM(JSON_EXTRACT(data, '$.users')) as total_users,
                AVG(JSON_EXTRACT(data, '$.bounce_rate')) as avg_bounce_rate,
                AVG(JSON_EXTRACT(data, '$.session_duration')) as avg_session_duration
            FROM {$wpdb->prefix}rphub_analytics_ga4 
            {$where_clause}
            AND collected_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ", $params));
        
        return array(
            'sessions' => intval($ga_data->total_sessions ?? 0),
            'users' => intval($ga_data->total_users ?? 0),
            'bounce_rate' => round($ga_data->avg_bounce_rate ?? 0, 2),
            'session_duration' => round($ga_data->avg_session_duration ?? 0, 2)
        );
    }
    
    /**
     * Get UX overview data
     */
    private function get_ux_overview_data($site_id, $date_range) {
        global $wpdb;
        
        $where_clause = '';
        $params = array();
        
        if ($site_id) {
            $where_clause = 'WHERE site_id = %d';
            $params[] = $site_id;
        }
        
        // Get RUM data
        $rum_data = $wpdb->get_row($wpdb->prepare("
            SELECT 
                AVG(JSON_EXTRACT(data, '$.interaction_count')) as avg_interactions,
                AVG(JSON_EXTRACT(data, '$.scroll_depth')) as avg_scroll_depth,
                COUNT(*) as total_measurements
            FROM {$wpdb->prefix}rphub_analytics_rum 
            {$where_clause}
            AND collected_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ", $params));
        
        return array(
            'interactions' => round($rum_data->avg_interactions ?? 0, 2),
            'scroll_depth' => round($rum_data->avg_scroll_depth ?? 0, 2),
            'measurements' => intval($rum_data->total_measurements ?? 0),
            'user_satisfaction' => $this->calculate_ux_score($rum_data)
        );
    }
    
    /**
     * Get SEO overview data
     */
    private function get_seo_overview_data($site_id, $date_range) {
        global $wpdb;
        
        $where_clause = '';
        $params = array();
        
        if ($site_id) {
            $where_clause = 'WHERE site_id = %d';
            $params[] = $site_id;
        }
        
        // Get Search Console data
        $sc_data = $wpdb->get_row($wpdb->prepare("
            SELECT 
                SUM(JSON_EXTRACT(data, '$.clicks')) as total_clicks,
                SUM(JSON_EXTRACT(data, '$.impressions')) as total_impressions,
                AVG(JSON_EXTRACT(data, '$.ctr')) as avg_ctr,
                AVG(JSON_EXTRACT(data, '$.avg_position')) as avg_position
            FROM {$wpdb->prefix}rphub_analytics_search_console 
            {$where_clause}
            AND collected_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ", $params));
        
        return array(
            'clicks' => intval($sc_data->total_clicks ?? 0),
            'impressions' => intval($sc_data->total_impressions ?? 0),
            'ctr' => round($sc_data->avg_ctr ?? 0, 2),
            'avg_position' => round($sc_data->avg_position ?? 0, 1),
            'visibility_score' => $this->calculate_seo_score($sc_data)
        );
    }
    
    /**
     * Calculate performance score
     */
    private function calculate_performance_score($web_vitals) {
        if (!$web_vitals) return 0;
        
        $lcp_score = $web_vitals->avg_lcp <= 2500 ? 100 : max(0, 100 - (($web_vitals->avg_lcp - 2500) / 25));
        $fid_score = $web_vitals->avg_fid <= 100 ? 100 : max(0, 100 - (($web_vitals->avg_fid - 100) / 3));
        $cls_score = $web_vitals->avg_cls <= 0.1 ? 100 : max(0, 100 - (($web_vitals->avg_cls - 0.1) * 400));
        
        return round(($lcp_score + $fid_score + $cls_score) / 3);
    }
    
    /**
     * Calculate UX score
     */
    private function calculate_ux_score($rum_data) {
        if (!$rum_data) return 0;
        
        $interaction_score = min(100, ($rum_data->avg_interactions ?? 0) * 10);
        $scroll_score = min(100, ($rum_data->avg_scroll_depth ?? 0));
        
        return round(($interaction_score + $scroll_score) / 2);
    }
    
    /**
     * Calculate SEO score
     */
    private function calculate_seo_score($sc_data) {
        if (!$sc_data) return 0;
        
        $ctr_score = min(100, ($sc_data->avg_ctr ?? 0) * 10);
        $position_score = $sc_data->avg_position ? max(0, 100 - ($sc_data->avg_position * 5)) : 0;
        
        return round(($ctr_score + $position_score) / 2);
    }
    
    /**
     * Run maintenance
     */
    public function run_maintenance($request) {
        if (!$this->check_rate_limit($request)) {
            return new WP_Error('rate_limit_exceeded', 'Rate limit exceeded', array('status' => 429));
        }
        
        $site_id = $request->get_param('site_id');
        $maintenance_type = $request->get_param('maintenance_type');
        
        try {
            if (!class_exists('ReplantaHub_Intelligent_Maintenance')) {
                require_once RPHUB_PLUGIN_DIR . 'includes/class-intelligent-maintenance.php';
            }
            
            $maintenance_system = new ReplantaHub_Intelligent_Maintenance();
            $results = $maintenance_system->perform_intelligent_maintenance();
            
            if (isset($results['error'])) {
                return new WP_Error('maintenance_failed', $results['error'], array('status' => 500));
            }
            
            return new WP_REST_Response(array(
                'message' => 'Maintenance completed successfully',
                'results' => $results
            ), 200);
            
        } catch (Exception $e) {
            return new WP_Error('maintenance_error', 'Maintenance failed: ' . $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Get system status
     */
    public function get_system_status($request) {
        if (!$this->check_rate_limit($request)) {
            return new WP_Error('rate_limit_exceeded', 'Rate limit exceeded', array('status' => 429));
        }
        
        global $wpdb;
        
        $status = array(
            'timestamp' => current_time('c'),
            'version' => $this->version,
            'wordpress' => array(
                'version' => get_bloginfo('version'),
                'multisite' => is_multisite(),
                'debug' => WP_DEBUG
            ),
            'database' => array(
                'status' => 'connected',
                'version' => $wpdb->db_version(),
                'charset' => $wpdb->charset,
                'collate' => $wpdb->collate
            ),
            'php' => array(
                'version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time')
            ),
            'integrations' => $this->get_integrations_status(),
            'services' => array(
                'api' => 'operational',
                'webhooks' => 'operational',
                'monitoring' => 'operational',
                'analytics' => 'operational'
            )
        );
        
        return new WP_REST_Response($status, 200);
    }
    
    /**
     * Authentication and Permission Callbacks
     */

    /**
     * Filter: rest_authentication_errors
     * Pass through existing errors; don't add any unless we have reason to block.
     */
    public function check_authentication_errors($errors) {
        // If another plugin already set an error, respect it
        if (is_wp_error($errors)) {
            return $errors;
        }

        // No additional authentication checks for now — our own routes
        // use permission_callback for authorization.
        return $errors;
    }

    public function authenticate_api_request($user_id) {
        // Skip if already authenticated or not REST request
        if ($user_id || !defined('REST_REQUEST') || !REST_REQUEST) {
            return $user_id;
        }
        
        // Check for API key authentication
        $api_key = $this->get_api_key_from_request();
        if ($api_key) {
            $user_id = $this->authenticate_api_key($api_key);
        }
        
        // Check for JWT authentication
        if (!$user_id) {
            $jwt_token = $this->get_jwt_from_request();
            if ($jwt_token) {
                $user_id = $this->authenticate_jwt($jwt_token);
            }
        }
        
        return $user_id;
    }
    
    public function check_api_permissions($request) {
        return current_user_can('manage_options') || $this->has_api_permission('read');
    }
    
    public function check_write_permissions($request) {
        return current_user_can('manage_options') || $this->has_api_permission('write');
    }
    
    public function check_delete_permissions($request) {
        return current_user_can('manage_options') || $this->has_api_permission('delete');
    }
    
    public function check_admin_permissions($request) {
        return current_user_can('manage_options') || $this->has_api_permission('admin');
    }
    
    public function verify_webhook_signature($request) {
        $signature = $request->get_header('X-RPHUB-Webhook-Secret');
        $payload = $request->get_body();
        
        if (!$signature || !$payload) {
            return false;
        }
        
        $expected_signature = hash_hmac('sha256', $payload, get_option('rphub_webhook_secret'));
        
        return hash_equals($expected_signature, $signature);
    }

    /**
     * Rate Limiting
     */
    
    public function setup_rate_limiting() {
        add_filter('rest_request_before_callbacks', array($this, 'check_rate_limit_filter'), 10, 3);
    }
    
    public function check_rate_limit_filter($response, $handler, $request) {
        if (strpos($request->get_route(), '/rphub/') === 0) {
            if (!$this->check_rate_limit($request)) {
                return new WP_Error('rate_limit_exceeded', 'Rate limit exceeded', array('status' => 429));
            }
        }
        return $response;
    }
    
    private function check_rate_limit($request) {
        $client_ip = $this->get_client_ip();
        $current_time = time();
        
        $rate_limit_data = get_transient('rphub_rate_limit_' . md5($client_ip));
        
        if (!$rate_limit_data) {
            $rate_limit_data = array(
                'requests' => 0,
                'window_start' => $current_time
            );
        }
        
        // Reset window if needed (1 minute window)
        if ($current_time - $rate_limit_data['window_start'] >= 60) {
            $rate_limit_data = array(
                'requests' => 0,
                'window_start' => $current_time
            );
        }
        
        $rate_limit_data['requests']++;
        
        // Check if limit exceeded
        if ($rate_limit_data['requests'] > $this->rate_limiter['requests_per_minute']) {
            return false;
        }
        
        // Update transient
        set_transient('rphub_rate_limit_' . md5($client_ip), $rate_limit_data, 120);
        
        return true;
    }
    
    /**
     * CORS Support
     */
    
    public function handle_preflight($response, $server, $request) {
        if ('OPTIONS' === $request->get_method()) {
            return new WP_REST_Response(null, 200);
        }
        return $response;
    }
    
    public function add_cors_headers($response, $server, $request) {
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-RPHUB-API-Key, X-RPHUB-Webhook-Secret');
        $response->header('Access-Control-Max-Age', '3600');
        
        return $response;
    }
    
    /**
     * Helper Methods
     */
    
    private function get_api_key_from_request() {
        $headers = getallheaders();
        
        if (isset($headers[$this->auth_handler['api_key_header']])) {
            return $headers[$this->auth_handler['api_key_header']];
        }
        
        if (isset($_GET['api_key'])) {
            return sanitize_text_field($_GET['api_key']);
        }
        
        return null;
    }
    
    private function get_jwt_from_request() {
        $headers = getallheaders();
        
        if (isset($headers[$this->auth_handler['jwt_header']])) {
            $auth_header = $headers[$this->auth_handler['jwt_header']];
            if (strpos($auth_header, 'Bearer ') === 0) {
                return substr($auth_header, 7);
            }
        }
        
        return null;
    }
    
    private function authenticate_api_key($api_key) {
        global $wpdb;
        
        $token_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rphub_api_tokens WHERE token_hash = %s AND status = 'active' AND (expires_at IS NULL OR expires_at > NOW())",
            hash('sha256', $api_key)
        ));
        
        if ($token_data) {
            // Update last used timestamp
            $wpdb->update(
                $wpdb->prefix . 'rphub_api_tokens',
                array('last_used_at' => current_time('mysql')),
                array('id' => $token_data->id)
            );
            
            return $token_data->user_id;
        }
        
        return null;
    }
    
    private function authenticate_jwt($jwt_token) {
        // JWT authentication implementation would go here
        // For now, return null (not implemented)
        return null;
    }
    
    private function has_api_permission($permission) {
        // Check API permissions based on current authentication
        $current_user = wp_get_current_user();
        
        if (!$current_user->exists()) {
            return false;
        }
        
        // Get API permissions for current user/token
        $api_permissions = get_user_meta($current_user->ID, 'rphub_api_permissions', true);
        
        if (!is_array($api_permissions)) {
            return false;
        }
        
        return in_array($permission, $api_permissions) || in_array('admin', $api_permissions);
    }
    
    private function get_client_ip() {
        $ip_headers = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    private function format_site_response($site) {
        return array(
            'id' => intval($site->id),
            'name' => $site->name,
            'url' => $site->url,
            'type' => $site->type,
            'status' => $site->status,
            'health_score' => intval($site->health_score ?? 0),
            'performance_score' => intval($site->performance_score ?? 0),
            'security_score' => intval($site->security_score ?? 0),
            'last_scan' => $site->last_scan,
            'created_at' => $site->created_at,
            'updated_at' => $site->updated_at
        );
    }
    
    private function get_integrations_status() {
        $integrations = array(
            'cloudflare' => get_option('rphub_cloudflare_enabled', false),
            'backuply' => get_option('rphub_backuply_enabled', false),
            'litespeed' => get_option('rphub_litespeed_enabled', false),
            'google_analytics' => get_option('rphub_ga4_enabled', false),
            'search_console' => get_option('rphub_search_console_enabled', false)
        );
        
        return $integrations;
    }
    
    /**
     * Sanitization callbacks
     */
    
    public function sanitize_array_int($value) {
        if (is_array($value)) {
            return array_map('absint', $value);
        }
        return array();
    }
    
    public function sanitize_workflow_trigger($value) {
        // Sanitize workflow trigger data
        if (is_array($value)) {
            return array_map('sanitize_text_field', $value);
        }
        return array();
    }
    
    public function sanitize_workflow_actions($value) {
        // Sanitize workflow actions data
        if (is_array($value)) {
            return array_map('sanitize_text_field', $value);
        }
        return array();
    }
    
    public function sanitize_credentials($value) {
        // Sanitize credentials (encrypt sensitive data)
        if (is_array($value)) {
            return array_map('sanitize_text_field', $value);
        }
        return array();
    }
    
    public function sanitize_permissions($value) {
        // Sanitize API permissions
        if (is_array($value)) {
            $allowed_permissions = array('read', 'write', 'delete', 'admin');
            return array_intersect($value, $allowed_permissions);
        }
        return array();
    }

    /**
     * GET /rphub/v1/openapi — minimal OpenAPI 3.0 spec for all Hub endpoints.
     */
    public function get_openapi_spec(): WP_REST_Response {
        $base = rest_url( $this->namespace );

        $spec = [
            'openapi' => '3.0.3',
            'info'    => [
                'title'   => 'Replanta Hub API',
                'version' => RPHUB_VERSION,
                'description' => 'REST API for Replanta Hub WordPress plugin.',
            ],
            'servers' => [ [ 'url' => $base ] ],
            'paths'   => [
                '/sites'                                       => [ 'get'  => [ 'summary' => 'List managed sites',     'tags' => ['Sites'] ],
                                                                     'post' => [ 'summary' => 'Create a site',          'tags' => ['Sites'] ] ],
                '/sites/{id}'                                  => [ 'get'    => [ 'summary' => 'Get site by ID',        'tags' => ['Sites'] ],
                                                                     'put'    => [ 'summary' => 'Update site',           'tags' => ['Sites'] ],
                                                                     'delete' => [ 'summary' => 'Delete site',           'tags' => ['Sites'] ] ],
                '/sites/{id}/scan'                             => [ 'post' => [ 'summary' => 'Trigger security scan',  'tags' => ['Sites'] ] ],
                '/analytics/overview'                          => [ 'get'  => [ 'summary' => 'Analytics overview',     'tags' => ['Analytics'] ] ],
                '/monitoring/health'                           => [ 'get'  => [ 'summary' => 'Health status',          'tags' => ['Monitoring'] ] ],
                '/monitoring/alerts'                           => [ 'get'  => [ 'summary' => 'List alerts',            'tags' => ['Monitoring'] ] ],
                '/automation/workflows'                        => [ 'get'  => [ 'summary' => 'List workflows',         'tags' => ['Automation'] ],
                                                                     'post' => [ 'summary' => 'Create workflow',        'tags' => ['Automation'] ] ],
                '/automation/workflows/{id}/execute'           => [ 'post' => [ 'summary' => 'Execute workflow',       'tags' => ['Automation'] ] ],
                '/system/status'                               => [ 'get'  => [ 'summary' => 'System status',          'tags' => ['System'] ] ],
                '/system/info'                                 => [ 'get'  => [ 'summary' => 'System info',            'tags' => ['System'] ] ],
                '/openapi'                                     => [ 'get'  => [ 'summary' => 'This OpenAPI spec',      'tags' => ['System'] ] ],
            ],
            'components' => [
                'securitySchemes' => [
                    'ApiKeyAuth' => [
                        'type' => 'apiKey',
                        'in'   => 'header',
                        'name' => 'X-RPHUB-API-Key',
                    ],
                ],
            ],
            'security' => [ [ 'ApiKeyAuth' => [] ] ],
        ];

        return new WP_REST_Response( RPHUB_API_Response::success( $spec ), 200 );
    }
}

// Initialize API system
new ReplantaHub_API_System();
