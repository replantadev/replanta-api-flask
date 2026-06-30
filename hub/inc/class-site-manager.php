<?php
/**
 * Site Manager Class
 * Handles site registration, communication, and management
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPHUB_Site_Manager {
    
    private $table_sites;
    private $table_tasks;
    
    public function __construct() {
        global $wpdb;
        $this->table_sites = $wpdb->prefix . 'rphub_sites';
        $this->table_tasks = $wpdb->prefix . 'rphub_tasks';
        
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // AJAX hooks for site management
        add_action('wp_ajax_rphub_add_site', [$this, 'ajax_add_site']);
        add_action('wp_ajax_rphub_remove_site', [$this, 'ajax_remove_site']);
        add_action('wp_ajax_rphub_update_site', [$this, 'ajax_update_site']);
        add_action('wp_ajax_rphub_get_site', [$this, 'ajax_get_site']);
        add_action('wp_ajax_rphub_get_site_data', [$this, 'ajax_get_site_data']);
        add_action('wp_ajax_rphub_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_rphub_sync_site_data', [$this, 'ajax_sync_site_data']);
        add_action('wp_ajax_rphub_sync_site',      [$this, 'ajax_sync_site_data']); // alias usado por admin-operations.php
        add_action('wp_ajax_rphub_sync_all', [$this, 'ajax_sync_all']);
        add_action('wp_ajax_rphub_force_care_update',  [$this, 'ajax_force_care_update']);
        add_action('wp_ajax_rphub_test_ftp',           [$this, 'ajax_test_ftp']);
        add_action('wp_ajax_rphub_ftp_update_care',    [$this, 'ajax_ftp_update_care']);
        add_action('wp_ajax_rphub_get_plan_status', [$this, 'ajax_get_plan_status']);
        add_action('wp_ajax_rphub_apply_plan_features', [$this, 'ajax_apply_plan_features']);
        
        // Addon alerts from Care sites (no auth â€” validated via site_token)
        add_action('wp_ajax_nopriv_rphub_care_alert', [$this, 'ajax_care_alert']);
        add_action('wp_ajax_rphub_care_alert',        [$this, 'ajax_care_alert']);

        // AJAX hooks for Care plugin connections (no auth required)
        add_action('wp_ajax_nopriv_rphub_test_care_connection', [$this, 'ajax_test_care_connection']);
        add_action('wp_ajax_rphub_test_care_connection', [$this, 'ajax_test_care_connection']);
        add_action('wp_ajax_nopriv_rphub_get_site_plan', [$this, 'ajax_get_site_plan']);
        add_action('wp_ajax_rphub_get_site_plan', [$this, 'ajax_get_site_plan']);
        
        // REST API endpoints for child sites to register
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }
    
    public function register_rest_routes() {
        // Site registration â€” requires admin auth (only Hub admins can register sites)
        register_rest_route('rphub/v1', '/sites/register', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_register_site'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
        
        // Heartbeat â€” requires valid site token+url pair
        register_rest_route('rphub/v1', '/sites/heartbeat', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_site_heartbeat'],
            'permission_callback' => [$this, 'validate_site_token_request']
        ]);
        
        register_rest_route('rphub/v1', '/sites/(?P<id>\d+)/data', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_site_data'],
            'permission_callback' => [$this, 'check_site_permission']
        ]);
        
        // Plan lookup â€” requires valid site token+url pair
        register_rest_route('api', '/sites/plan', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_site_plan'],
            'permission_callback' => [$this, 'validate_site_token_request']
        ]);
        
        // Connection test â€” requires valid site token+url pair
        register_rest_route('api', '/test-connection', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_test_connection'],
            'permission_callback' => [$this, 'validate_site_token_request']
        ]);

        // Notifications from Care sites â€” requires valid site token+url pair
        register_rest_route('rphub/v1', '/notifications', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_receive_notification'],
            'permission_callback' => [$this, 'validate_site_token_request']
        ]);
    }

    /**
     * Receive a notification from a Care site and store it in rphub_notifications
     */
    public function rest_receive_notification($request) {
        global $wpdb;

        $params = $request->get_json_params();
        if (!$params) {
            $params = $request->get_params();
        }

        $site_token = $request->get_header('X-Site-Token') ?: ($params['site_token'] ?? '');
        $site_url = $request->get_header('X-Site-URL') ?: ($params['site_url'] ?? '');

        $site = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->table_sites} WHERE token = %s AND url = %s AND status = 'active'",
            $site_token,
            $site_url
        ));

        if (!$site) {
            return new WP_Error('rest_unauthorized', 'Sitio no encontrado.', ['status' => 401]);
        }

        $type = sanitize_text_field($params['type'] ?? 'info');
        $severity = sanitize_text_field($params['data']['severity'] ?? 'info');
        if (!in_array($severity, ['info', 'warning', 'error', 'critical'], true)) {
            $severity = 'info';
        }

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'rphub_notifications',
            [
                'site_id' => (int) $site->id,
                'type' => $type,
                'severity' => $severity,
                'title' => sanitize_text_field($params['subject'] ?? ''),
                'message' => sanitize_textarea_field($params['message'] ?? ''),
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s']
        );

        if ($inserted === false) {
            return new WP_Error('db_error', 'No se pudo guardar la notificacion.', ['status' => 500]);
        }

        return rest_ensure_response(['success' => true, 'id' => $wpdb->insert_id]);
    }
    
    /**
     * Validate that the request comes from a registered Care site
     * Uses X-Site-Token + X-Site-URL headers, or site_token + site_url POST params
     */
    public function validate_site_token_request($request) {
        // Allow logged-in admins
        if (current_user_can('manage_options')) {
            return true;
        }

        $site_token = $request->get_header('X-Site-Token');
        $site_url = $request->get_header('X-Site-URL');

        // Fallback to POST params (for AJAX-style calls)
        if (empty($site_token)) {
            $params = $request->get_json_params();
            if (!$params) {
                $params = $request->get_params();
            }
            $site_token = $params['site_token'] ?? '';
            $site_url = $params['site_url'] ?? '';
        }

        if (empty($site_token) || empty($site_url)) {
            return new WP_Error(
                'rest_unauthorized',
                'Site token and URL are required.',
                ['status' => 401]
            );
        }

        global $wpdb;
        $site = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->table_sites} WHERE token = %s AND url = %s AND status = 'active'",
            $site_token,
            $site_url
        ));

        if (!$site) {
            return new WP_Error(
                'rest_unauthorized',
                'Invalid site token or URL.',
                ['status' => 401]
            );
        }

        return true;
    }
    
    public function add_site($data) {
        global $wpdb;
        
        $defaults = [
            'name'           => '',
            'url'            => '',
            'token'          => '',
            'plan'           => 'semilla',
            'status'         => 'active',
            'notes'          => '',
            'whm_account'    => '',
            'client_name'    => '',
            'client_email'   => '',
            'alert_email'    => '',
            'ga4_property_id'=> '',
            'sc_domain'      => '',
            'domain_type'    => 'external',
            'whm_server'     => '',
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        // Validate required fields
        if (empty($data['name']) || empty($data['url'])) {
            return new WP_Error('missing_data', 'Nombre y URL son requeridos');
        }
        
        // Validate URL
        if (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', 'URL no valida');
        }
        
        // Check if site already exists
        $existing = $this->get_site_by_url($data['url']);
        if ($existing) {
            return new WP_Error('site_exists', 'Este sitio ya esta registrado');
        }
        
        // Generate token if not provided
        if (empty($data['token'])) {
            $data['token'] = wp_generate_password(32, false);
        }
        
        // Clean URL
        $data['url'] = rtrim($data['url'], '/');
        
        $result = $wpdb->insert(
            $this->table_sites,
            [
                'name'            => sanitize_text_field($data['name']),
                'url'             => esc_url_raw($data['url']),
                'token'           => sanitize_text_field($data['token']),
                'plan'            => sanitize_text_field($data['plan']),
                'status'          => sanitize_text_field($data['status']),
                'notes'           => sanitize_textarea_field($data['notes']),
                'whm_account'     => sanitize_text_field($data['whm_account']),
                'client_name'     => sanitize_text_field($data['client_name']),
                'client_email'    => sanitize_email($data['client_email']),
                'alert_email'     => sanitize_email($data['alert_email']),
                'ga4_property_id' => sanitize_text_field($data['ga4_property_id']),
                'sc_domain'       => sanitize_text_field($data['sc_domain']),
                'domain_type'     => in_array($data['domain_type'], ['internal','external'], true) ? $data['domain_type'] : 'external',
                'whm_server'      => sanitize_key($data['whm_server']),
                'created_at'      => current_time('mysql'),
                'updated_at'      => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Error al guardar en la base de datos');
        }
        
        $site_id = $wpdb->insert_id;
        
        // Test initial connection
        $this->test_site_connection($site_id);

        // Schedule initial sync
        $this->schedule_site_sync($site_id);

        // Trigger CF onboarding via Dominios Reseller if domain is managed there
        $this->maybe_trigger_cf_onboarding($site_id, $data['url']);

        do_action('rphub_site_created', $site_id, $data);

        // Return site_id and token for display to user
        return [
            'site_id' => $site_id,
            'token' => $data['token']
        ];
    }

    /**
     * If the site's domain is managed in Dominios Reseller and has no onboarding
     * record yet, create one with state='pending' so the DR worker picks it up.
     */
    private function maybe_trigger_cf_onboarding(int $site_id, string $site_url): void {
        global $wpdb;

        if (!class_exists('Dominios_Reseller_Onboarding_DB')) {
            return;
        }

        $host   = parse_url($site_url, PHP_URL_HOST) ?: '';
        $domain = strtolower(preg_replace('/^www\./i', '', $host));
        if (!$domain) {
            return;
        }

        $dr_table = $wpdb->prefix . 'dominios_reseller';
        if (!$wpdb->get_var("SHOW TABLES LIKE '$dr_table'")) {
            return;
        }

        $managed = $wpdb->get_var($wpdb->prepare(
            "SELECT domain FROM $dr_table WHERE domain = %s OR primary_domain = %s LIMIT 1",
            $domain, $domain
        ));
        if (!$managed) {
            return; // not a DR-managed domain
        }

        $existing = Dominios_Reseller_Onboarding_DB::get_onboarding_state($domain);
        if ($existing && !in_array($existing['state'] ?? '', ['none', ''], true)) {
            return; // already has an onboarding entry in progress
        }

        Dominios_Reseller_Onboarding_DB::upsert_onboarding($domain, [
            'state'      => 'pending',
            'preset_key' => 'wp',
        ]);

        if (class_exists('RPHUB_Database')) {
            RPHUB_Database::update_site_meta($site_id, 'cf_onboarding_state', 'pending');
        }
    }

    public function remove_site($site_id) {
        global $wpdb;
        
        $site = $this->get_site($site_id);
        if (!$site) {
            return new WP_Error('site_not_found', 'Sitio no encontrado');
        }
        
        // Remove all tasks for this site
        $wpdb->delete($this->table_tasks, ['site_id' => $site_id], ['%d']);
        
        // Remove site
        $result = $wpdb->delete($this->table_sites, ['id' => $site_id], ['%d']);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Error al eliminar de la base de datos');
        }
        
        return true;
    }
    
    /**
     * Sanitize a single site field value. Returns sanitized string or WP_Error.
     */
    private function sanitize_site_field($field, $value) {
        switch ($field) {
            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', 'URL no valida');
                }
                return esc_url_raw(rtrim($value, '/'));
            case 'sc_domain':
                return sanitize_text_field($value);
            case 'notes':
                return sanitize_textarea_field($value);
            case 'client_email':
            case 'alert_email':
                return sanitize_email($value);
            case 'domain_type':
                return in_array($value, ['internal', 'external'], true) ? $value : 'external';
            case 'whm_server':
                return sanitize_key($value);
            default:
                return sanitize_text_field($value);
        }
    }

    public function update_site($site_id, $data) {
        global $wpdb;

        $site = $this->get_site($site_id);
        if (!$site) {
            return new WP_Error('site_not_found', 'Sitio no encontrado');
        }

        $old_plan = $site->plan;

        $allowed_fields  = ['name', 'url', 'plan', 'status', 'notes', 'whm_account', 'token',
                            'client_name', 'client_email', 'alert_email', 'ga4_property_id', 'sc_domain',
                            'domain_type', 'whm_server'];
        $clearable_fields = ['client_name', 'client_email', 'alert_email', 'ga4_property_id', 'sc_domain', 'whm_server', 'whm_account'];

        $update_data = [];
        $format      = [];

        foreach ($allowed_fields as $field) {
            if (!isset($data[$field])) {
                continue;
            }
            if ($data[$field] === '' && !in_array($field, $clearable_fields, true)) {
                continue;
            }
            $sanitized = $this->sanitize_site_field($field, $data[$field]);
            if (is_wp_error($sanitized)) {
                return $sanitized;
            }
            $update_data[$field] = $sanitized;
            $format[]            = '%s';
        }

        if (empty($update_data)) {
            return new WP_Error('no_data', 'No hay datos para actualizar');
        }

        $update_data['updated_at'] = current_time('mysql');
        $format[] = '%s';

        $result = $wpdb->update(
            $this->table_sites,
            $update_data,
            ['id' => $site_id],
            $format,
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Error al actualizar la base de datos');
        }

        // Push plan change to Care immediately so it doesn't have to wait
        // for the 6-hour transient expiry to pick up the new plan.
        $new_plan = $update_data['plan'] ?? null;
        if ($new_plan && $new_plan !== $old_plan) {
            $api_client = new RPHUB_API_Client();
            $api_client->push_config($site->url, $site->token, ['plan' => $new_plan]);
        }

        return true;
    }
    
    public function get_site($site_id) {
        global $wpdb;
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_sites}'");
        if (!$table_exists) {
            return null;
        }
        
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_sites} WHERE id = %d", $site_id)
        );
    }
    
    public function get_site_by_url($url) {
        global $wpdb;
        
        $url = rtrim($url, '/');
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_sites} WHERE url = %s", $url)
        );
    }
    
    public function get_sites($args = []) {
        global $wpdb;
        
        $defaults = [
            'status' => '',
            'plan' => '',
            'orderby' => 'name',
            'order' => 'ASC',
            'limit' => -1,
            'offset' => 0
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where_clauses = [];
        $values = [];
        
        if (!empty($args['status'])) {
            $where_clauses[] = "status = %s";
            $values[] = $args['status'];
        }
        
        if (!empty($args['plan'])) {
            $where_clauses[] = "plan = %s";
            $values[] = $args['plan'];
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = "WHERE " . implode(' AND ', $where_clauses);
        }
        
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        $order_sql = $orderby ? "ORDER BY $orderby" : '';
        
        $limit_sql = '';
        if ($args['limit'] > 0) {
            $limit_sql = $wpdb->prepare("LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }
        
        $sql = "SELECT * FROM {$this->table_sites} $where_sql $order_sql $limit_sql";
        
        if (!empty($values)) {
            return $wpdb->get_results($wpdb->prepare($sql, ...$values));
        } else {
            return $wpdb->get_results($sql);
        }
    }

    public function get_sites_count($args = []) {
        global $wpdb;
        
        $where_clauses = [];
        $values = [];
        
        if (!empty($args['status'])) {
            $where_clauses[] = "status = %s";
            $values[] = $args['status'];
        }
        
        if (!empty($args['plan'])) {
            $where_clauses[] = "plan = %s";
            $values[] = $args['plan'];
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = "WHERE " . implode(' AND ', $where_clauses);
        }
        
        $sql = "SELECT COUNT(*) FROM {$this->table_sites} $where_sql";
        
        if (!empty($values)) {
            return $wpdb->get_var($wpdb->prepare($sql, ...$values));
        } else {
            return $wpdb->get_var($sql);
        }
    }

    public function test_site_connection($site_id) {
        $site = $this->get_site($site_id);
        if (!$site) {
            return false;
        }
        
        $api_client = new RPHUB_API_Client();
        $result = $api_client->test_connection($site->url, $site->token);
        
        // Update last check time
        global $wpdb;
        $update_data = ['last_check' => current_time('mysql')];
        
        if ($result && !is_wp_error($result)) {
            $update_data['last_success'] = current_time('mysql');
            $update_data['status'] = 'active';
        } else {
            $update_data['status'] = 'error';
        }
        
        $wpdb->update(
            $this->table_sites,
            $update_data,
            ['id' => $site_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
        
        return $result;
    }
    
    public function sync_site_data($site_id) {
        $site = $this->get_site($site_id);
        if (!$site) {
            return false;
        }
        
        $api_client = new RPHUB_API_Client();
        $data = $api_client->get_site_info($site->url, $site->token);
        
        if (is_wp_error($data)) {
            return $data;
        }
        
        // Update site data
        global $wpdb;

        // Care sends 'pending_updates'; accept both names for compatibility
        $updates = $data['pending_updates'] ?? $data['updates_available'] ?? 0;

        // Care sends 'plugin_count' (singular); accept both names
        $plugins_count = $data['plugin_count'] ?? $data['plugins_count'] ?? 0;

        // Care sends 'security_score' (0-100); convert to issue count heuristic
        // 100 = no issues, <60 = critical. We store 0/1/2 as none/warning/critical.
        $sec_score = $data['security_score'] ?? null;
        if ($sec_score !== null) {
            $security_issues = $sec_score >= 80 ? 0 : ($sec_score >= 60 ? 1 : 2);
        } else {
            $security_issues = $data['security_issues'] ?? 0;
        }

        // Prefer Care's own performance_score; fall back to local calculation
        $health_score = $data['performance_score'] ?? $this->calculate_health_score(
            array_merge($data, ['updates_available' => $updates, 'security_issues' => $security_issues])
        );

        // Store ssl_type from Care metrics
        if (!empty($data['ssl_type']) && class_exists('RPHUB_Database')) {
            RPHUB_Database::update_site_meta($site_id, 'ssl_type', sanitize_text_field($data['ssl_type']));
        }

        $update_data = [
            'wp_version'       => $data['wp_version'] ?? '',
            'php_version'      => $data['php_version'] ?? '',
            'plugins_count'    => $plugins_count,
            'themes_count'     => $data['themes_count'] ?? 0,
            'updates_available'=> $updates,
            'security_issues'  => $security_issues,
            'health_score'     => $health_score,
            'status'           => 'active',
            'last_check'       => current_time('mysql'),
            'last_success'     => current_time('mysql'),
            'updated_at'       => current_time('mysql'),
        ];

        $wpdb->update(
            $this->table_sites,
            $update_data,
            ['id' => $site_id],
            ['%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s'],
            ['%d']
        );

        // Fetch SA (replanta-site-audit) scores if Care has them (best-effort, non-blocking)
        $sa = $api_client->get_sa_summary($site->url, $site->token);
        if (!is_wp_error($sa) && !empty($sa['sa_available'])) {
            if (class_exists('RPHUB_Database')) {
                $new_seo = (int) ($sa['seo_score'] ?? 0);
                $old_seo = (int) RPHUB_Database::get_site_meta($site_id, 'seo_score', 0);
                // Flag regression if SEO score dropped â‰¥10 points
                $seo_regression = ($old_seo > 0 && $new_seo < ($old_seo - 10)) ? 1 : 0;
                RPHUB_Database::update_site_meta($site_id, 'seo_regression', $seo_regression);

                RPHUB_Database::update_site_meta($site_id, 'cf_score',             (int) ($sa['cf_score']             ?? 0));
                RPHUB_Database::update_site_meta($site_id, 'seo_score',            $new_seo);
                RPHUB_Database::update_site_meta($site_id, 'perf_score',           (int) ($sa['perf_score']           ?? 0));
                RPHUB_Database::update_site_meta($site_id, 'sa_global_score',      (int) ($sa['global_score']         ?? 0));
                RPHUB_Database::update_site_meta($site_id, 'sa_critical_issues',   (int) ($sa['issue_count_critical'] ?? 0));
                RPHUB_Database::update_site_meta($site_id, 'sa_warning_issues',    (int) ($sa['issue_count_warning']  ?? 0));
                RPHUB_Database::update_site_meta($site_id, 'sa_last_audit',        sanitize_text_field($sa['last_audit_at'] ?? ''));
            }
        }

        return true;
    }
    
    private function calculate_health_score($data) {
        $score = 100;

        $updates = $data['updates_available'] ?? $data['pending_updates'] ?? 0;
        $score -= intval($updates) * 5;

        $sec = $data['security_issues'] ?? 0;
        $score -= intval($sec) * 20;

        if (!empty($data['wp_version'])) {
            $current_wp = get_bloginfo('version');
            if (version_compare($data['wp_version'], $current_wp, '<')) {
                $score -= 10;
            }
        }

        if (!empty($data['php_version'])) {
            if (version_compare($data['php_version'], '7.4', '<')) {
                $score -= 15;
            }
        }

        return max(0, min(100, $score));
    }
    
    public function schedule_site_sync($site_id, $delay = 0) {
        wp_schedule_single_event(time() + $delay, 'rphub_sync_site', [$site_id]);
    }
    
    // AJAX Handlers
    public function ajax_add_site() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $data = [
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'url' => esc_url_raw($_POST['url'] ?? ''),
            'plan' => sanitize_text_field($_POST['plan'] ?? 'semilla'),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
            'whm_account' => sanitize_text_field($_POST['whm_account'] ?? ''),
            'client_name' => sanitize_text_field($_POST['client_name'] ?? ''),
            'client_email' => sanitize_email($_POST['client_email'] ?? ''),
            'alert_email'  => sanitize_email($_POST['alert_email'] ?? ''),
            'ga4_property_id' => sanitize_text_field($_POST['ga4_property_id'] ?? ''),
            'sc_domain'    => sanitize_text_field($_POST['sc_domain'] ?? ''),
            'domain_type'  => sanitize_text_field($_POST['domain_type'] ?? 'external'),
            'whm_server'   => sanitize_key($_POST['whm_server'] ?? ''),
        ];
        
        $result = $this->add_site($data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            // Save uptime monitoring preference
            if (is_array($result) && !empty($result['site_id'])) {
                $new_site_id = $result['site_id'];
                $uptime = !empty($_POST['uptime_monitoring_enabled']) && $_POST['uptime_monitoring_enabled'] === '1';
                RPHUB_Database::update_site_meta($new_site_id, 'uptime_monitoring_enabled', $uptime ? '1' : '0');
                $this->save_addon_meta($new_site_id);
                RP_Hub_FTP_Recovery::save_credentials($new_site_id, $_POST);
            }

            // Handle the new response format
            if (is_array($result)) {
                wp_send_json_success([
                    'site_id' => $result['site_id'],
                    'token' => $result['token'],
                    'message' => 'Sitio anadido correctamente'
                ]);
            } else {
                // Fallback for old format (just in case)
                wp_send_json_success([
                    'site_id' => $result,
                    'message' => 'Sitio anadido correctamente'
                ]);
            }
        }
    }
    
    public function ajax_remove_site() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        // Support both site_id and domain for removal
        $site_id = intval($_POST['site_id'] ?? 0);
        $domain = sanitize_text_field($_POST['domain'] ?? '');
        
        if ($site_id > 0) {
            // Remove by site_id
            $result = $this->remove_site($site_id);
        } elseif (!empty($domain)) {
            // Remove by domain - find site first
            $site = $this->get_site_by_url($domain);
            if ($site) {
                $result = $this->remove_site($site->id);
            } else {
                $result = new WP_Error('site_not_found', 'Sitio no encontrado');
            }
        } else {
            $result = new WP_Error('missing_params', 'site_id o domain son requeridos');
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success('Sitio eliminado correctamente');
        }
    }
    
    public function ajax_update_site() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $site_id = intval($_POST['site_id'] ?? 0);
        // Pre-sanitization here is minimal; update_site() re-sanitizes each field.
        $data = [
            'name'            => $_POST['name']            ?? '',
            'url'             => $_POST['url']             ?? '',
            'plan'            => $_POST['plan']            ?? '',
            'status'          => $_POST['status']          ?? '',
            'notes'           => $_POST['notes']           ?? '',
            'whm_account'     => $_POST['whm_account']     ?? '',
            'token'           => $_POST['token']           ?? '',
            'client_name'     => $_POST['client_name']     ?? '',
            'client_email'    => $_POST['client_email']    ?? '',
            'alert_email'     => $_POST['alert_email']     ?? '',
            'ga4_property_id' => $_POST['ga4_property_id'] ?? '',
            'sc_domain'       => $_POST['sc_domain']       ?? '',
            'domain_type'     => $_POST['domain_type']     ?? '',
            'whm_server'      => $_POST['whm_server']      ?? '',
        ];

        $result = $this->update_site($site_id, $data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            // Save uptime monitoring preference (meta, not a table column)
            $uptime = !empty($_POST['uptime_monitoring_enabled']) && $_POST['uptime_monitoring_enabled'] === '1';
            RPHUB_Database::update_site_meta($site_id, 'uptime_monitoring_enabled', $uptime ? '1' : '0');
            $this->save_addon_meta($site_id);
            RP_Hub_FTP_Recovery::save_credentials($site_id, $_POST);
            wp_send_json_success('Sitio actualizado correctamente');
        }
    }

    public function ajax_get_site() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $site_id = intval($_POST['site_id'] ?? 0);
        
        if (!$site_id) {
            wp_send_json_error('ID de sitio requerido');
        }
        
        $site = $this->get_site($site_id);

        if (is_wp_error($site) || !$site) {
            wp_send_json_error($site instanceof WP_Error ? $site->get_error_message() : 'Sitio no encontrado');
        } else {
            // Append meta fields needed by the edit form
            $site_array = (array) $site;
            $site_array['uptime_monitoring_enabled'] = RPHUB_Database::get_site_meta($site_id, 'uptime_monitoring_enabled') ?: '0';
            $site_array['addon_ecommerce']         = RPHUB_Database::get_site_meta($site_id, 'addon_ecommerce') ?: '0';
            $site_array['ecom_revenue_threshold']  = RPHUB_Database::get_site_meta($site_id, 'ecom_revenue_threshold') ?: '35';
            $site_array['ecom_alert_email']        = RPHUB_Database::get_site_meta($site_id, 'ecom_alert_email') ?: '';
            wp_send_json_success((object) $site_array);
        }
    }

    public function ajax_get_site_data() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $site_id = intval($_POST['site_id'] ?? 0);
        
        if (!$site_id) {
            wp_send_json_error('ID de sitio requerido');
        }
        
        $site = $this->get_site($site_id);
        
        if (is_wp_error($site)) {
            wp_send_json_error($site->get_error_message());
        } else {
            $site_array = (array) $site;
            // FTP meta (password omitted â€” only presence flag)
            $site_array['ftp_host'] = RPHUB_Database::get_site_meta($site_id, 'ftp_host') ?: '';
            $site_array['ftp_user'] = RPHUB_Database::get_site_meta($site_id, 'ftp_user') ?: '';
            $site_array['ftp_port'] = RPHUB_Database::get_site_meta($site_id, 'ftp_port') ?: '21';
            $site_array['ftp_ssl']  = RPHUB_Database::get_site_meta($site_id, 'ftp_ssl')  ?: '0';
            $site_array['ftp_path'] = RPHUB_Database::get_site_meta($site_id, 'ftp_path') ?: '';
            $site_array['ftp_has_pass'] = ! empty(RPHUB_Database::get_site_meta($site_id, 'ftp_pass_enc'));
            wp_send_json_success((object) $site_array);
        }
    }

    public function ajax_test_connection() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        // Check if tables exist first
        global $wpdb;
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_sites}'");
        if (!$table_exists) {
            wp_send_json_error('Las tablas de la base de datos no existen. Por favor, ejecuta el setup de base de datos.');
            return;
        }
        
        $site_id = intval($_POST['site_id'] ?? 0);
        if ($site_id <= 0) {
            wp_send_json_error('ID de sitio no valido');
            return;
        }
        
        $result = $this->test_site_connection($site_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else if ($result) {
            wp_send_json_success('Conexion exitosa');
        } else {
            wp_send_json_error('No se pudo conectar al sitio');
        }
    }
    
    public function ajax_sync_site_data() {
        if (
            !check_ajax_referer('rphub_ajax', 'nonce', false)
            && !check_ajax_referer('rphub_sync_site', 'nonce', false)
        ) {
            wp_send_json_error('Nonce invalido o caducado. Recarga Operaciones e intentalo de nuevo.', 403);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $site_id = intval($_POST['site_id'] ?? 0);
        $result = $this->sync_site_data($site_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success('Datos sincronizados correctamente');
        }
    }
    
    private function save_addon_meta($site_id) {
        $addon_ecom = !empty($_POST['addon_ecommerce']) && $_POST['addon_ecommerce'] === '1';
        RPHUB_Database::update_site_meta($site_id, 'addon_ecommerce', $addon_ecom ? '1' : '0');
        if ($addon_ecom) {
            $threshold = max(1, intval($_POST['ecom_revenue_threshold'] ?? 35));
            RPHUB_Database::update_site_meta($site_id, 'ecom_revenue_threshold', $threshold);
            RPHUB_Database::update_site_meta($site_id, 'ecom_alert_email', sanitize_email($_POST['ecom_alert_email'] ?? ''));
            $this->push_addon_config_to_care($site_id, $addon_ecom, $threshold);
        }
    }

    private function push_addon_config_to_care($site_id, $ecom_active, $threshold) {
        $site = $this->get_site($site_id);
        if (!$site || empty($site->url) || empty($site->token)) {
            return;
        }
        $care_url = !empty($site->care_url) ? $site->care_url : $site->url;
        $payload = [
            'addons'          => $ecom_active ? ['ecommerce'] : [],
            'ecommerce_config' => [
                'revenue_alert_threshold' => $threshold,
                'alert_email'             => RPHUB_Database::get_site_meta($site_id, 'ecom_alert_email') ?: '',
            ],
        ];
        $response = wp_remote_post(
            trailingslashit($care_url) . 'wp-json/replanta/v1/config',
            [
                'headers'   => [
                    'Authorization' => 'Bearer ' . $site->token,
                    'Content-Type'  => 'application/json',
                ],
                'body'      => wp_json_encode($payload),
                'timeout'   => 10,
                'sslverify' => true,
            ]
        );
        if (is_wp_error($response)) {
            error_log('replanta-hub: push_addon_config_to_care site_id=' . $site_id . ' â€” ' . $response->get_error_message());
        }
    }

    public function ajax_sync_all() {
        if (
            !check_ajax_referer('rphub_ajax', 'nonce', false)
            && !check_ajax_referer('rphub_sync_site', 'nonce', false)
        ) {
            wp_send_json_error('Nonce invalido o caducado. Recarga Operaciones e intentalo de nuevo.', 403);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }

        global $wpdb;
        $sites = $wpdb->get_results("SELECT id FROM {$this->table_sites} WHERE status != 'inactive' ORDER BY id ASC");

        if (empty($sites)) {
            wp_send_json_success(['synced' => 0, 'errors' => 0, 'message' => 'No hay sitios activos']);
            return;
        }

        $synced = 0;
        $errors = 0;
        foreach ($sites as $row) {
            $result = $this->sync_site_data(intval($row->id));
            if (is_wp_error($result)) {
                $errors++;
            } else {
                $synced++;
            }
        }

        wp_send_json_success([
            'synced'  => $synced,
            'errors'  => $errors,
            'message' => "Sincronizados: {$synced} sitios" . ($errors ? ", {$errors} con errores" : ''),
        ]);
    }

    public function ajax_get_plan_status() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        $site_id = intval($_POST['site_id'] ?? 0);
        $site    = $this->get_site($site_id);
        if (!$site || is_wp_error($site)) {
            wp_send_json_error('Sitio no encontrado'); return;
        }

        $care_url   = $site->care_url   ?? '';
        $care_token = $site->care_token ?? '';
        $plan       = $site->plan       ?? 'semilla';

        $hub_settings = get_option('rphub_settings', []);
        $b2_ok = !empty($hub_settings['b2_key_id'])
               && !empty($hub_settings['b2_app_key'])
               && !empty($hub_settings['b2_bucket_id']);

        $addon_ecom    = RPHUB_Database::get_site_meta($site_id, 'addon_ecommerce') === '1';
        $last_push     = RPHUB_Database::get_site_meta($site_id, 'care_last_config_push') ?: '';
        $backup_freq   = in_array($plan, ['raiz', 'ecosistema'], true) ? 'daily' : 'weekly';
        $update_window = in_array($plan, ['raiz', 'ecosistema'], true)
            ? ['day' => 3, 'start_hour' => 23, 'end_hour' => 2]
            : ['day' => null, 'start_hour' => 2, 'end_hour' => 6];
        if ($addon_ecom) {
            $backup_freq = 'twicedaily';
        }
        $update_window_label = ($update_window['day'] === 3 && $update_window['start_hour'] === 23 && $update_window['end_hour'] === 2)
            ? 'miercoles 23:00-02:00'
            : sprintf(
                '%s %02d:00-%02d:00',
                $update_window['day'] === null ? 'sin dia fijo' : 'dia ' . $update_window['day'],
                $update_window['start_hour'],
                $update_window['end_hour']
            );

        $features = [
            [
                'label'  => 'Care conectado',
                'ok'     => !empty($care_url) && !empty($care_token),
                'detail' => !empty($care_url) ? $care_url : 'Sin care_url',
            ],
            [
                'label'  => 'Backup externo (B2)',
                'ok'     => $b2_ok,
                'detail' => $b2_ok ? 'Credenciales globales configuradas' : 'Faltan credenciales B2 en Ajustes',
            ],
            [
                'label'  => 'Plan: ' . ucfirst($plan),
                'ok'     => true,
                'detail' => 'Frecuencia backup: ' . $backup_freq . ' | Updates: ' . $update_window_label,
            ],
            [
                'label'  => 'Addon eCommerce',
                'ok'     => $addon_ecom,
                'detail' => $addon_ecom ? 'Activo' : 'Inactivo - activar en ficha del sitio',
            ],
        ];

        wp_send_json_success([
            'plan'            => $plan,
            'care_connected'  => !empty($care_url) && !empty($care_token),
            'b2_configured'   => $b2_ok,
            'addon_ecommerce' => $addon_ecom,
            'last_push'       => $last_push,
            'features'        => $features,
            'can_push'        => !empty($care_url) && !empty($care_token),
        ]);
    }

    public function ajax_apply_plan_features() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        $site_id = intval($_POST['site_id'] ?? 0);
        $site    = $this->get_site($site_id);
        if (!$site || is_wp_error($site)) {
            wp_send_json_error('Sitio no encontrado'); return;
        }

        $care_url   = $site->care_url   ?? '';
        $care_token = $site->care_token ?? '';
        if (empty($care_url) || empty($care_token)) {
            wp_send_json_error('Care no conectado - el sitio necesita care_url y care_token'); return;
        }

        $plan          = $site->plan ?? 'semilla';
        $addon_ecom    = RPHUB_Database::get_site_meta($site_id, 'addon_ecommerce') === '1';
        $hub_settings  = get_option('rphub_settings', []);
        $backup_freq   = in_array($plan, ['raiz', 'ecosistema'], true) ? 'daily' : 'weekly';
        $update_window = in_array($plan, ['raiz', 'ecosistema'], true)
            ? ['day' => 3, 'start_hour' => 23, 'end_hour' => 2]
            : ['day' => null, 'start_hour' => 2, 'end_hour' => 6];
        if ($addon_ecom) {
            $backup_freq = 'twicedaily';
        }

        $config = [
            'plan'            => $plan,
            'backup_frequency' => $backup_freq,
            'backup_retention_days' => $plan === 'ecosistema' ? 60 : ($plan === 'raiz' ? 30 : 30),
            'update_window'   => $update_window,
            'addons'          => $addon_ecom ? ['ecommerce'] : [],
        ];
        if ($addon_ecom) {
            $config['ecommerce_config'] = [
                'revenue_alert_threshold' => intval(RPHUB_Database::get_site_meta($site_id, 'ecom_revenue_threshold') ?: 35),
                'alert_email'             => RPHUB_Database::get_site_meta($site_id, 'ecom_alert_email') ?: '',
            ];
        }
        $b2_prefix = '';
        if (!empty($hub_settings['b2_key_id']) && class_exists('ReplantaHub_Backblaze_Integration')) {
            $b2 = ReplantaHub_Backblaze_Integration::get_global_config();
            if (!empty($b2['app_key'])) {
                $b2_prefix = ReplantaHub_Backblaze_Integration::build_site_prefix($site_id, $site);
                $config['b2_key_id']      = $b2['key_id'];
                $config['b2_app_key']     = $b2['app_key'];
                $config['b2_bucket_id']   = $b2['bucket_id'];
                $config['b2_bucket_name'] = $b2['bucket_name'];
                $config['b2_prefix']      = $b2_prefix;
            }
        }

        $endpoint = trailingslashit($care_url) . 'wp-json/replanta/v1/config';
        $response = wp_remote_post($endpoint, [
            'headers'   => [
                'Authorization' => 'Bearer ' . $care_token,
                'Content-Type'  => 'application/json',
            ],
            'body'      => wp_json_encode($config),
            'timeout'   => 15,
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error('Error de red: ' . $response->get_error_message()); return;
        }
        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            wp_send_json_error("Care devolvio HTTP {$code}"); return;
        }

        RPHUB_Database::update_site_meta($site_id, 'care_last_config_push', current_time('mysql'));
        if ($b2_prefix !== '') {
            RPHUB_Database::update_site_meta($site_id, 'b2_prefix', $b2_prefix);
        }
        wp_send_json_success(['message' => 'Configuracion aplicada en Care correctamente', 'plan' => $plan]);
    }

    // REST API Handlers
    public function rest_register_site($request) {
        $params = $request->get_json_params();
        
        if (empty($params['site_url']) || empty($params['token'])) {
            return new WP_Error('missing_params', 'URL del sitio y token son requeridos', ['status' => 400]);
        }
        
        $data = [
            'name' => $params['site_name'] ?? parse_url($params['site_url'], PHP_URL_HOST),
            'url' => $params['site_url'],
            'token' => $params['token'],
            'plan' => $params['plan'] ?? 'semilla'
        ];
        
        $result = $this->add_site($data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return rest_ensure_response([
            'success' => true,
            'site_id' => $result,
            'hub_url' => home_url()
        ]);
    }
    
    public function rest_site_heartbeat($request) {
        $params = $request->get_json_params();

        if (empty($params['site_url']) || empty($params['token'])) {
            return new WP_Error('missing_params', 'URL y token requeridos', ['status' => 400]);
        }

        $site = $this->get_site_by_url($params['site_url']);
        if (!$site || $site->token !== $params['token']) {
            return new WP_Error('invalid_site', 'Sitio no autorizado', ['status' => 401]);
        }

        global $wpdb;

        // Update last_check + last_seen in the sites table
        $updates = ['last_check' => current_time('mysql'), 'status' => 'active'];
        if (!empty($params['health_score'])) {
            $updates['health_score'] = intval($params['health_score']);
        }
        $wpdb->update($this->table_sites, $updates, ['id' => $site->id]);

        // Persist version data in site_meta so cards can display it
        if (class_exists('RPHUB_Database')) {
            if (!empty($params['plugin_version'])) {
                RPHUB_Database::update_site_meta($site->id, 'care_version', sanitize_text_field($params['plugin_version']));
            }
            if (!empty($params['wp_version'])) {
                RPHUB_Database::update_site_meta($site->id, 'wp_version', sanitize_text_field($params['wp_version']));
            }
            if (!empty($params['php_version'])) {
                RPHUB_Database::update_site_meta($site->id, 'php_version', sanitize_text_field($params['php_version']));
            }
            if (isset($params['pending_updates'])) {
                RPHUB_Database::update_site_meta($site->id, 'pending_updates_count', intval($params['pending_updates']));
            }

            // Re-enrich from DR if data is older than 24 hours
            if (class_exists('RPHUB_DR_Bridge') && RPHUB_DR_Bridge::is_available()) {
                $enriched_at = RPHUB_Database::get_site_meta($site->id, 'dr_enriched_at');
                if (!$enriched_at || strtotime($enriched_at) < (time() - DAY_IN_SECONDS)) {
                    RPHUB_DR_Bridge::enrich_site($site->id, $site->url);
                }
            }
        }

        return rest_ensure_response(['success' => true]);
    }
    
    public function rest_get_site_data($request) {
        $site_id = $request->get_param('id');
        $site = $this->get_site($site_id);
        
        if (is_wp_error($site)) {
            return new WP_Error('site_not_found', 'Sitio no encontrado', ['status' => 404]);
        }
        
        // Return basic site data (excluding sensitive info like token)
        $data = [
            'id' => $site->id,
            'name' => $site->name,
            'url' => $site->url,
            'status' => $site->status,
            'plan' => $site->plan,
            'last_check' => $site->last_check,
            'last_success' => $site->last_success,
            'notes' => $site->notes,
            'whm_account' => $site->whm_account,
            'created_at' => $site->created_at
        ];
        
        return rest_ensure_response($data);
    }
    
    public function check_site_permission($request) {
        $site_id = $request->get_param('id');
        $token = $request->get_header('Authorization');
        
        if (!$token) {
            return false;
        }
        
        $token = str_replace('Bearer ', '', $token);
        $site = $this->get_site($site_id);
        
        return $site && $site->token === $token;
    }
    
    // REST API handler for Care plugin test connection
    public function rest_test_connection($request) {
        $params = $request->get_json_params();
        if (!$params) {
            $params = $request->get_params();
        }
        
        $site_token = $params['site_token'] ?? '';
        $site_url = $params['site_url'] ?? '';
        
        if (empty($site_token) || empty($site_url)) {
            return new WP_Error('missing_params', 'Site token and URL required', ['status' => 400]);
        }
        
        // Check if site exists in our database. Token is authoritative; URL can
        // differ by scheme/www/trailing slash after migrations or SSL fixes.
        global $wpdb;
        $site = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_sites} WHERE token = %s AND status = 'active' LIMIT 1",
            $site_token
        ));
        
        if ($site) {
            $stored = strtolower(rtrim(preg_replace('#^https?://#', '', $site->url), '/'));
            $sent   = strtolower(rtrim(preg_replace('#^https?://#', '', $site_url), '/'));
            if ($stored !== $sent) {
                error_log(sprintf(
                    'Replanta Hub: plan lookup URL mismatch for site %d. Stored: %s | Sent: %s',
                    $site->id,
                    $stored,
                    $sent
                ));
            }
        }

        if (!$site) {
            return new WP_Error('site_not_found', 'Site not registered in Hub or invalid token', ['status' => 404]);
        }
        
        // Update last check time
        $wpdb->update(
            $this->table_sites,
            ['last_check' => current_time('mysql')],
            ['id' => $site->id],
            ['%s'],
            ['%d']
        );
        
        return rest_ensure_response([
            'success' => true,
            'message' => 'Connection successful',
            'site_name' => $site->name,
            'plan' => $site->plan ?: 'semilla'
        ]);
    }
    
    // REST API handler for Care plugin to get site plan
    public function rest_get_site_plan($request) {
        $site_domain = $request->get_header('X-Site-Domain');
        $site_url = $request->get_header('X-Site-URL');
        
        if (!$site_domain && !$site_url) {
            return new WP_Error('missing_site_info', 'Site domain or URL required in headers', ['status' => 400]);
        }
        
        // Search for site by domain or URL
        global $wpdb;
        $site = null;
        
        if ($site_url) {
            $site = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_sites} WHERE url = %s",
                $site_url
            ));
        }
        
        if (!$site && $site_domain) {
            $site = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_sites} WHERE url LIKE %s",
                '%' . $site_domain . '%'
            ));
        }
        
        if (!$site) {
            return new WP_Error('site_not_found', 'Site not registered in Hub', ['status' => 404]);
        }
        
        // Return plan information
        return rest_ensure_response([
            'plan' => $site->plan ?: 'semilla',
            'status' => 'active',
            'site_id' => $site->id,
            'site_name' => $site->name
        ]);
    }
    
    // AJAX handler for Care plugin connection test
    public function ajax_test_care_connection() {
        $site_token = sanitize_text_field($_POST['site_token'] ?? '');
        $site_url   = sanitize_text_field($_POST['site_url'] ?? '');

        if (empty($site_token)) {
            wp_send_json_error('Token del sitio es requerido');
            return;
        }

        // Look up by token only â€” URL may differ (http/https, www, trailing slash)
        global $wpdb;
        $site = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_sites} WHERE token = %s AND status = 'active'",
            $site_token
        ));

        if (!$site) {
            wp_send_json_error('Token invalido o sitio no registrado en el Hub');
            return;
        }

        // Optional URL sanity check (normalized comparison, non-blocking)
        if (!empty($site_url)) {
            $stored  = strtolower(rtrim(preg_replace('#^https?://#', '', $site->url), '/'));
            $sending = strtolower(rtrim(preg_replace('#^https?://#', '', $site_url), '/'));
            if ($stored !== $sending) {
                // Log the mismatch for debugging but still succeed â€” token is authoritative
                error_log(sprintf(
                    'Replanta Hub: token match but URL mismatch for site %d. Stored: %s | Sent: %s',
                    $site->id, $stored, $sending
                ));
            }
        }

        $wpdb->update(
            $this->table_sites,
            ['last_check' => current_time('mysql')],
            ['id' => $site->id],
            ['%s'],
            ['%d']
        );

        wp_send_json_success([
            'message' => 'Conexion exitosa con el Hub',
            'site'    => $site->name,
            'plan'    => $site->plan ?: 'semilla',
        ]);
    }
    
    /**
     * AJAX handler to get site plan for Care plugin
     */
    public function ajax_get_site_plan() {
        $site_token = sanitize_text_field($_POST['site_token'] ?? '');
        $site_url = sanitize_text_field($_POST['site_url'] ?? '');
        
        if (empty($site_token) || empty($site_url)) {
            wp_send_json_error('Token y URL del sitio son requeridos');
            return;
        }
        
        // Check if site exists in our database. Token is authoritative; URL can
        // differ by scheme/www/trailing slash after migrations or SSL fixes.
        global $wpdb;
        $site = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_sites} WHERE token = %s AND status = 'active' LIMIT 1",
            $site_token
        ));
        
        if ($site) {
            $stored = strtolower(rtrim(preg_replace('#^https?://#', '', $site->url), '/'));
            $sent   = strtolower(rtrim(preg_replace('#^https?://#', '', $site_url), '/'));
            if ($stored !== $sent) {
                error_log(sprintf(
                    'Replanta Hub: plan lookup URL mismatch for site %d. Stored: %s | Sent: %s',
                    $site->id,
                    $stored,
                    $sent
                ));
            }
        }

        if (!$site) {
            wp_send_json_error('Sitio no registrado en el Hub o token invalido');
            return;
        }
        
        // Update last check time
        $wpdb->update(
            $this->table_sites,
            ['last_check' => current_time('mysql')],
            ['id' => $site->id],
            ['%s'],
            ['%d']
        );
        
        wp_send_json_success([
            'plan' => $site->plan,
            'site_name' => $site->name,
            'status' => $site->status
        ]);
    }

    /**
     * Receive a fire-and-forget addon alert from a Care site.
     * Called via admin-ajax.php action=rphub_care_alert (no priv required).
     * Validates site_token + site_url, stores in rphub_notifications.
     */
    public function ajax_care_alert(): void {
        $site_token = sanitize_text_field($_POST['site_token'] ?? '');
        $site_url   = sanitize_text_field($_POST['site_url']   ?? '');
        $event      = sanitize_key($_POST['event']             ?? '');
        $raw_data   = $_POST['data'] ?? '';

        if (!$site_token || !$site_url || !$event) {
            wp_send_json_error('Parametros insuficientes', 400);
            return;
        }

        global $wpdb;
        $site = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name FROM {$this->table_sites} WHERE token = %s AND url = %s AND status = 'active'",
            $site_token,
            $site_url
        ));

        if (!$site) {
            wp_send_json_error('Sitio no autorizado', 401);
            return;
        }

        $data = is_string($raw_data) ? (json_decode($raw_data, true) ?: []) : (array) $raw_data;

        $event_map = [
            'checkout_failure'    => ['severity' => 'error',   'title' => 'Fallo en checkout detectado'],
            'revenue_anomaly'     => ['severity' => 'warning',  'title' => 'Anomalia de ingresos detectada'],
            'checkout_recovered'  => ['severity' => 'info',     'title' => 'Checkout recuperado'],
            'peak_scheduler'      => ['severity' => 'info',     'title' => 'Ventana de actualizaciones reprogramada'],
        ];
        $meta     = $event_map[$event] ?? ['severity' => 'info', 'title' => 'Alerta addon: ' . $event];
        $message  = $this->buildAlertMessage($event, $data);

        $wpdb->insert(
            $wpdb->prefix . 'rphub_notifications',
            [
                'site_id'    => (int) $site->id,
                'type'       => 'care_addon',
                'severity'   => $meta['severity'],
                'title'      => $meta['title'],
                'message'    => $message,
                'data'       => wp_json_encode($data),
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        wp_send_json_success(['stored' => true]);
    }

    public function ajax_force_care_update(): void {
        check_ajax_referer('rphub_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }

        global $wpdb;
        $sites = $wpdb->get_results(
            "SELECT id, care_url, care_token FROM {$this->table_sites} WHERE status != 'inactive' ORDER BY id ASC"
        );

        $ok      = 0;
        $ftp_ok  = 0;
        $errors  = 0;
        $skipped = 0;

        foreach ($sites as $site) {
            $care_url   = $site->care_url   ?? '';
            $care_token = $site->care_token ?? '';

            if (empty($care_url) || empty($care_token)) {
                $skipped++;
                continue;
            }

            $endpoint = trailingslashit($care_url) . 'wp-json/replanta/v1/run';
            $response = wp_remote_post($endpoint, [
                'headers'   => [
                    'Authorization' => 'Bearer ' . $care_token,
                    'Content-Type'  => 'application/json',
                ],
                'body'      => wp_json_encode(['task' => 'self_update']),
                'timeout'   => 30,
                'sslverify' => true,
            ]);

            $api_ok = ! is_wp_error($response)
                && wp_remote_retrieve_response_code($response) >= 200
                && wp_remote_retrieve_response_code($response) < 300;

            if ($api_ok) {
                $ok++;
                continue;
            }

            // REST failed â€” try FTP fallback if credentials are configured
            $site_id = intval($site->id);
            if (class_exists('RP_Hub_FTP_Recovery') && RP_Hub_FTP_Recovery::has_credentials($site_id)) {
                $ftp_result = RP_Hub_FTP_Recovery::update_care($site_id);
                if ($ftp_result['success']) {
                    $ftp_ok++;
                } else {
                    $errors++;
                }
            } else {
                $errors++;
            }
        }

        wp_send_json_success([
            'ok'      => $ok,
            'ftp_ok'  => $ftp_ok,
            'errors'  => $errors,
            'skipped' => $skipped,
        ]);
    }

    public function ajax_test_ftp(): void {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        $site_id = intval($_POST['site_id'] ?? 0);
        if (!$site_id) {
            wp_send_json_error('ID de sitio requerido'); return;
        }
        if (!class_exists('RP_Hub_FTP_Recovery')) {
            wp_send_json_error('Clase FTP no disponible'); return;
        }
        $result = RP_Hub_FTP_Recovery::test_connection($site_id);
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['error']);
        }
    }

    public function ajax_ftp_update_care(): void {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        $site_id = intval($_POST['site_id'] ?? 0);
        if (!$site_id) {
            wp_send_json_error('ID de sitio requerido'); return;
        }
        if (!class_exists('RP_Hub_FTP_Recovery')) {
            wp_send_json_error('Clase FTP no disponible'); return;
        }
        $result = RP_Hub_FTP_Recovery::update_care($site_id);
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['error']);
        }
    }

    private function buildAlertMessage(string $event, array $data): string {
        switch ($event) {
            case 'checkout_failure':
                $checks  = $data['failed_checks'] ?? [];
                $summary = !empty($checks) ? implode(', ', (array) $checks) : 'checks fallidos';
                return 'Fallos consecutivos en: ' . $summary;

            case 'revenue_anomaly':
                $drop = isset($data['drop_pct']) ? round((float) $data['drop_pct'], 1) . '%' : 'desconocida';
                return 'Caida de ingresos: ' . $drop . ' respecto a la semana anterior';

            case 'checkout_recovered':
                return 'El checkout volvio a responder correctamente';

            case 'peak_scheduler':
                $window = $data['window'] ?? '';
                return $window ? 'Nueva ventana de mantenimiento: ' . sanitize_text_field($window) : 'Ventana actualizada';

            default:
                return sanitize_textarea_field($data['message'] ?? 'Sin detalles');
        }
    }
}
