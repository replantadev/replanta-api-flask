<?php
/**
 * WP Toolkit Pro Integration for Replanta Hub
 * 
 * Handles intelligent updates, vulnerability scanning, and WordPress management
 * via WP Toolkit Pro API
 */

class ReplantaHub_WPToolkit_Integration {
    
    private $api_url;
    private $api_key;
    private $server_ip;
    
    public function __construct() {
        $this->api_url = get_option('rphub_wptoolkit_api_url', '');
        $this->api_key = get_option('rphub_wptoolkit_api_key', '');
        $this->server_ip = get_option('rphub_server_ip', '');
        
        // If init already fired (class instantiated late), call directly
        if (did_action('init')) {
            $this->init();
        } else {
            add_action('init', [$this, 'init']);
        }
    }
    
    public function init() {
        // AJAX handlers
        add_action('wp_ajax_rphub_wptoolkit_scan_vulnerabilities', [$this, 'ajax_scan_vulnerabilities']);
        add_action('wp_ajax_rphub_wptoolkit_update_site', [$this, 'ajax_update_site']);
        add_action('wp_ajax_rphub_wptoolkit_get_site_info', [$this, 'ajax_get_site_info']);
        add_action('wp_ajax_rphub_wptoolkit_smart_update', [$this, 'ajax_smart_update']);
        
        // Scheduled tasks
        add_action('rphub_wptoolkit_vulnerability_scan', [$this, 'scheduled_vulnerability_scan']);
        add_action('rphub_wptoolkit_smart_updates', [$this, 'scheduled_smart_updates']);
        
        // Schedule events
        RPHUB_Scheduler::schedule('rphub_wptoolkit_vulnerability_scan', 'daily');
        RPHUB_Scheduler::schedule('rphub_wptoolkit_smart_updates',      'twicedaily');
    }
    
    /**
     * Check if WP Toolkit integration is configured
     */
    public function is_configured() {
        return !empty($this->api_url) && !empty($this->api_key);
    }
    
    /**
     * Get WP Toolkit Pro installation info for a site
     */
    public function get_site_info($domain) {
        try {
            $response = $this->make_api_request('GET', '/sites/' . $domain);
            
            if (is_wp_error($response)) {
                rphub_log_integration_error('WPToolkit', 'get_site_info', $response->get_error_message(), ['domain' => $domain]);
                return $response;
            }
            
            rphub_error_manager()->log_error(
                'WPToolkit site info retrieved successfully for ' . $domain,
                ReplantaHub_Error_Manager::LEVEL_INFO,
                ReplantaHub_Error_Manager::TYPE_INTEGRATION_ERROR,
                ['domain' => $domain, 'response_size' => strlen(json_encode($response))]
            );
            
            return [
                'wordpress_version' => $response['wordpress_version'] ?? '',
                'php_version' => $response['php_version'] ?? '',
                'plugins' => $response['plugins'] ?? [],
                'themes' => $response['themes'] ?? [],
                'security_status' => $response['security_status'] ?? 'unknown',
                'last_backup' => $response['last_backup'] ?? '',
                'ssl_status' => $response['ssl_status'] ?? 'unknown',
                'maintenance_mode' => $response['maintenance_mode'] ?? false
            ];
        } catch (Exception $e) {
            rphub_log_integration_error('WPToolkit', 'get_site_info', $e->getMessage(), ['domain' => $domain]);
            return new WP_Error('wptoolkit_error', $e->getMessage());
        }
    }
    
    /**
     * Scan for vulnerabilities using WP Toolkit Pro
     */
    public function scan_vulnerabilities($domain) {
        try {
            $response = $this->make_api_request('POST', '/sites/' . $domain . '/security/scan', [
                'scan_type' => 'full',
                'include_plugins' => true,
                'include_themes' => true,
                'include_core' => true
            ]);
            
            if (is_wp_error($response)) {
                rphub_log_integration_error('WPToolkit', 'scan_vulnerabilities', $response->get_error_message(), ['domain' => $domain]);
                return $response;
            }
            
            $vulnerabilities = $response['vulnerabilities'] ?? [];
            $risk_level = $this->calculate_risk_level($vulnerabilities);
            
            // Log security scan results
            rphub_error_manager()->log_error(
                sprintf('Vulnerability scan completed for %s - %d vulnerabilities found (Risk: %s)', $domain, count($vulnerabilities), $risk_level),
                $risk_level === 'high' ? ReplantaHub_Error_Manager::LEVEL_WARNING : ReplantaHub_Error_Manager::LEVEL_INFO,
                ReplantaHub_Error_Manager::TYPE_INTEGRATION_ERROR,
                [
                    'domain' => $domain,
                    'vulnerabilities_count' => count($vulnerabilities),
                    'risk_level' => $risk_level
                ]
            );
            
            return [
                'scan_id' => $response['scan_id'] ?? '',
                'status' => $response['status'] ?? 'pending',
                'vulnerabilities_found' => $vulnerabilities,
                'risk_level' => $risk_level,
                'recommendations' => $this->generate_security_recommendations($vulnerabilities)
            ];
        } catch (Exception $e) {
            rphub_log_integration_error('WPToolkit', 'scan_vulnerabilities', $e->getMessage(), ['domain' => $domain]);
            return new WP_Error('wptoolkit_scan_error', $e->getMessage());
        }
    }
    
    /**
     * Perform intelligent updates using WP Toolkit Pro
     */
    public function smart_update($domain, $options = []) {
        $default_options = [
            'create_backup' => true,
            'maintenance_mode' => true,
            'test_mode' => false,
            'rollback_on_error' => true,
            'update_core' => true,
            'update_plugins' => true,
            'update_themes' => true,
            'exclude_plugins' => [],
            'exclude_themes' => []
        ];
        
        $options = array_merge($default_options, $options);
        
        // Pre-update backup
        if ($options['create_backup']) {
            $backup_result = $this->create_backup($domain);
            if (is_wp_error($backup_result)) {
                return new WP_Error('backup_failed', 'No se pudo crear backup pre-actualización');
            }
        }
        
        // Enable maintenance mode
        if ($options['maintenance_mode']) {
            $this->set_maintenance_mode($domain, true);
        }
        
        $response = $this->make_api_request('POST', '/sites/' . $domain . '/updates/smart', $options);
        
        // Disable maintenance mode
        if ($options['maintenance_mode']) {
            $this->set_maintenance_mode($domain, false);
        }
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return [
            'update_id' => $response['update_id'] ?? '',
            'status' => $response['status'] ?? 'pending',
            'updated_items' => $response['updated_items'] ?? [],
            'failed_items' => $response['failed_items'] ?? [],
            'backup_id' => $response['backup_id'] ?? '',
            'rollback_available' => $response['rollback_available'] ?? false
        ];
    }
    
    /**
     * Create backup using WP Toolkit Pro
     */
    public function create_backup($domain, $type = 'full') {
        $response = $this->make_api_request('POST', '/sites/' . $domain . '/backup', [
            'type' => $type,
            'description' => 'Pre-update backup - ' . date('Y-m-d H:i:s')
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return [
            'backup_id' => $response['backup_id'] ?? '',
            'status' => $response['status'] ?? 'pending',
            'size' => $response['size'] ?? 0,
            'created_at' => $response['created_at'] ?? date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Set maintenance mode
     */
    public function set_maintenance_mode($domain, $enabled = true) {
        return $this->make_api_request('PUT', '/sites/' . $domain . '/maintenance', [
            'enabled' => $enabled,
            'message' => 'Sitio en mantenimiento - Actualizaciones en progreso'
        ]);
    }
    
    /**
     * Get update recommendations
     */
    public function get_update_recommendations($domain) {
        $site_info = $this->get_site_info($domain);
        if (is_wp_error($site_info)) {
            return $site_info;
        }
        
        $recommendations = [];
        
        // WordPress Core recommendations
        if (version_compare($site_info['wordpress_version'], $this->get_latest_wp_version(), '<')) {
            $recommendations['wordpress'] = [
                'current' => $site_info['wordpress_version'],
                'latest' => $this->get_latest_wp_version(),
                'priority' => 'high',
                'security_update' => $this->is_security_update($site_info['wordpress_version'])
            ];
        }
        
        // Plugin recommendations
        foreach ($site_info['plugins'] as $plugin) {
            if ($plugin['update_available']) {
                $recommendations['plugins'][] = [
                    'name' => $plugin['name'],
                    'current' => $plugin['version'],
                    'latest' => $plugin['latest_version'],
                    'priority' => $this->get_update_priority($plugin),
                    'security_update' => $plugin['security_update'] ?? false
                ];
            }
        }
        
        // Theme recommendations
        foreach ($site_info['themes'] as $theme) {
            if ($theme['update_available']) {
                $recommendations['themes'][] = [
                    'name' => $theme['name'],
                    'current' => $theme['version'],
                    'latest' => $theme['latest_version'],
                    'priority' => 'medium'
                ];
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Calculate risk level based on vulnerabilities
     */
    private function calculate_risk_level($vulnerabilities) {
        if (empty($vulnerabilities)) {
            return 'low';
        }
        
        $critical_count = 0;
        $high_count = 0;
        
        foreach ($vulnerabilities as $vuln) {
            switch ($vuln['severity'] ?? 'medium') {
                case 'critical':
                    $critical_count++;
                    break;
                case 'high':
                    $high_count++;
                    break;
            }
        }
        
        if ($critical_count > 0) {
            return 'critical';
        } elseif ($high_count > 2) {
            return 'high';
        } elseif ($high_count > 0 || count($vulnerabilities) > 5) {
            return 'medium';
        }
        
        return 'low';
    }
    
    /**
     * Generate security recommendations
     */
    private function generate_security_recommendations($vulnerabilities) {
        $recommendations = [];
        
        foreach ($vulnerabilities as $vuln) {
            $recommendation = [
                'title' => $vuln['title'] ?? 'Vulnerabilidad detectada',
                'description' => $vuln['description'] ?? '',
                'severity' => $vuln['severity'] ?? 'medium',
                'action' => $this->get_vulnerability_action($vuln),
                'affected_component' => $vuln['component'] ?? 'unknown'
            ];
            
            $recommendations[] = $recommendation;
        }
        
        return $recommendations;
    }
    
    /**
     * Get recommended action for vulnerability
     */
    private function get_vulnerability_action($vuln) {
        switch ($vuln['type'] ?? '') {
            case 'outdated_plugin':
                return 'Actualizar plugin a la última versión';
            case 'outdated_theme':
                return 'Actualizar tema a la última versión';
            case 'outdated_core':
                return 'Actualizar WordPress a la última versión';
            case 'weak_password':
                return 'Cambiar contraseñas débiles';
            case 'malware':
                return 'Limpiar malware y revisar seguridad';
            default:
                return 'Revisar y corregir vulnerabilidad';
        }
    }
    
    /**
     * Get update priority for plugin
     */
    private function get_update_priority($plugin) {
        if ($plugin['security_update'] ?? false) {
            return 'critical';
        }
        
        if ($plugin['compatibility_issues'] ?? false) {
            return 'low';
        }
        
        return 'medium';
    }
    
    /**
     * Get latest WordPress version
     */
    private function get_latest_wp_version() {
        $version = get_transient('rphub_latest_wp_version');
        
        if (!$version) {
            $response = wp_remote_get('https://api.wordpress.org/core/version-check/1.7/');
            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                if (isset($data['offers'][0]['version'])) {
                    $version = $data['offers'][0]['version'];
                    set_transient('rphub_latest_wp_version', $version, HOUR_IN_SECONDS);
                }
            }
        }
        
        return $version ?: '6.3';
    }
    
    /**
     * Check if update is security-related
     */
    private function is_security_update($current_version) {
        // Logic to determine if it's a security update
        // This would typically involve checking WordPress security advisories
        return false; // Simplified for now
    }
    
    /**
     * Make API request to WP Toolkit Pro
     */
    private function make_api_request($method, $endpoint, $data = []) {
        if (empty($this->api_url) || empty($this->api_key)) {
            $error_msg = 'WP Toolkit Pro API credentials not configured';
            rphub_log_integration_error('WPToolkit', 'make_api_request', $error_msg, [
                'method' => $method,
                'endpoint' => $endpoint,
                'has_api_url' => !empty($this->api_url),
                'has_api_key' => !empty($this->api_key)
            ]);
            return new WP_Error('missing_credentials', $error_msg);
        }
        
        $url = rtrim($this->api_url, '/') . $endpoint;
        
        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ];
        
        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = json_encode($data);
        }
        
        $start_time = microtime(true);
        $response = wp_remote_request($url, $args);
        $response_time = microtime(true) - $start_time;
        
        if (is_wp_error($response)) {
            rphub_log_api_error('WPToolkit', $endpoint, 0, $response->get_error_message(), [
                'method' => $method,
                'url' => $url,
                'response_time' => $response_time,
                'data_size' => strlen(json_encode($data))
            ]);
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($code >= 400) {
            $error_msg = 'WP Toolkit Pro API error: ' . $code;
            if ($body) {
                $decoded_body = json_decode($body, true);
                if (isset($decoded_body['message'])) {
                    $error_msg .= ' - ' . $decoded_body['message'];
                }
            }
            
            rphub_log_api_error('WPToolkit', $endpoint, $code, $error_msg, [
                'method' => $method,
                'url' => $url,
                'response_time' => $response_time,
                'response_body' => substr($body, 0, 500) // First 500 chars for debugging
            ]);
            
            return new WP_Error('api_error', $error_msg);
        }
        
        // Log successful API calls in debug mode
        if (defined('RPHUB_DEBUG') && RPHUB_DEBUG) {
            rphub_error_manager()->log_error(
                sprintf('WPToolkit API call successful: %s %s (Response: %d)', $method, $endpoint, $code),
                ReplantaHub_Error_Manager::LEVEL_DEBUG,
                ReplantaHub_Error_Manager::TYPE_API_ERROR,
                [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'response_code' => $code,
                    'response_time' => $response_time,
                    'response_size' => strlen($body)
                ]
            );
        }
        
        return json_decode($body, true);
    }
    
    /**
     * AJAX Handlers
     */
    public function ajax_scan_vulnerabilities() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $domain = $_POST['domain'] ?? '';
        if (empty($domain)) {
            wp_send_json_error('Dominio requerido');
        }
        
        $result = $this->scan_vulnerabilities($domain);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_smart_update() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $domain = $_POST['domain'] ?? '';
        $options = $_POST['options'] ?? [];
        
        if (empty($domain)) {
            wp_send_json_error('Dominio requerido');
        }
        
        $result = $this->smart_update($domain, $options);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_get_site_info() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $domain = $_POST['domain'] ?? '';
        if (empty($domain)) {
            wp_send_json_error('Dominio requerido');
        }
        
        $result = $this->get_site_info($domain);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Scheduled Tasks
     */
    public function scheduled_vulnerability_scan() {
        global $wpdb;
        $table_sites = $wpdb->prefix . 'rphub_sites';

        $sites = $wpdb->get_results("SELECT * FROM $table_sites WHERE status = 'active'", ARRAY_A);

        $api_client = new RPHUB_API_Client();

        foreach ($sites as $site) {
            $domain      = parse_url($site['url'], PHP_URL_HOST);
            $scan_result = $this->scan_vulnerabilities($domain);

            if (!is_wp_error($scan_result)) {
                $this->store_vulnerability_scan($site['id'], $scan_result);

                if ($scan_result['risk_level'] === 'critical') {
                    $this->create_vulnerability_notification($site['id'], $scan_result);
                }

                // Push real-time vulnerability data to Care agent
                $api_client->push_config($site['url'], $site['token'], [
                    'vulnerability_data' => $scan_result,
                ]);
            }
        }
    }

    public function scheduled_smart_updates() {
        global $wpdb;
        $table_sites = $wpdb->prefix . 'rphub_sites';

        $sites = $wpdb->get_results("
            SELECT * FROM $table_sites
            WHERE status = 'active'
            AND auto_updates = 1
        ", ARRAY_A);

        $api_client     = new RPHUB_API_Client();
        $wptk_available = $this->is_configured();

        foreach ($sites as $site) {
            $domain          = parse_url($site['url'], PHP_URL_HOST);
            $recommendations = $this->get_update_recommendations($domain);

            if (!is_wp_error($recommendations)) {
                $this->process_auto_updates($domain, $recommendations, $site);
            }

            // Tell Care whether Hub is managing updates for this site so it can skip its own task
            $api_client->push_config($site['url'], $site['token'], [
                'update_managed' => $wptk_available,
            ]);
        }
    }
    
    /**
     * Store vulnerability scan results
     */
    private function store_vulnerability_scan($site_id, $scan_result) {
        global $wpdb;
        $table_reports = $wpdb->prefix . 'rphub_reports';
        
        $wpdb->insert($table_reports, [
            'site_id' => $site_id,
            'type' => 'vulnerability_scan',
            'data' => json_encode($scan_result),
            'created_at' => current_time('mysql')
        ]);
    }
    
    /**
     * Create vulnerability notification
     */
    private function create_vulnerability_notification($site_id, $scan_result) {
        global $wpdb;
        $table_notifications = $wpdb->prefix . 'rphub_notifications';
        
        $message = sprintf(
            'Vulnerabilidades críticas detectadas: %d encontradas',
            count($scan_result['vulnerabilities_found'])
        );
        
        $wpdb->insert($table_notifications, [
            'site_id' => $site_id,
            'type' => 'security',
            'severity' => 'error',
            'title' => 'Vulnerabilidades Críticas Detectadas',
            'message' => $message,
            'created_at' => current_time('mysql')
        ]);
    }
    
    /**
     * Process automatic updates
     */
    private function process_auto_updates($domain, $recommendations, $site) {
        $auto_update_options = [
            'create_backup' => true,
            'maintenance_mode' => true,
            'rollback_on_error' => true
        ];
        
        // Only auto-update critical security updates
        $critical_updates = false;
        
        if (isset($recommendations['wordpress']) && $recommendations['wordpress']['security_update']) {
            $critical_updates = true;
        }
        
        foreach ($recommendations['plugins'] ?? [] as $plugin) {
            if ($plugin['priority'] === 'critical') {
                $critical_updates = true;
                break;
            }
        }
        
        if ($critical_updates) {
            $this->smart_update($domain, $auto_update_options);
        }
    }
    
    /**
     * Run vulnerability scan
     */
    public function run_vulnerability_scan() {
        return $this->scheduled_vulnerability_scan();
    }
    
    /**
     * Run daily checks
     */
    public function run_daily_checks() {
        return $this->scheduled_vulnerability_scan();
    }
}

// Initialize WP Toolkit Pro integration
function rphub_wptoolkit_init() {
    return new ReplantaHub_WPToolkit_Integration();
}
add_action('plugins_loaded', 'rphub_wptoolkit_init');
