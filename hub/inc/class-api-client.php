<?php
/**
 * API Client Class
 * Handles communication with child sites
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPHUB_API_Client {
    
    private $timeout = 30;
    private $user_agent = 'ReplantaHub/1.0';
    
    public function __construct() {
        // Set longer timeout for maintenance tasks
        $this->timeout = apply_filters('rphub_api_timeout', 30);
    }
    
    /**
     * Test connection to a child site
     */
    public function test_connection($site_url, $token) {
        $endpoint = rtrim($site_url, '/') . '/wp-json/replanta/v1/ping';

        $response = $this->make_request('GET', $endpoint, [], $token);

        if (is_wp_error($response)) {
            return $response;
        }

        return isset($response['status']) && $response['status'] === 'ok';
    }
    
    /**
     * Get site information
     */
    public function get_site_info($site_url, $token) {
        $endpoint = rtrim($site_url, '/') . '/wp-json/replanta/v1/metrics';
        
        $response = $this->make_request('GET', $endpoint, [], $token);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $response;
    }
    
    /**
     * Execute a task on a child site
     */
    public function execute_task($site_url, $token, $task_type, $params = []) {
        $endpoint = rtrim($site_url, '/') . '/wp-json/replanta/v1/run';

        $data = [
            'task' => $task_type,
            'args' => $params,
        ];
        
        // Increase timeout for maintenance tasks
        $timeout = $task_type === 'backup' ? 300 : $this->timeout;
        
        $response = $this->make_request('POST', $endpoint, $data, $token, $timeout);
        
        return $response;
    }
    
    /**
     * Get recent task logs of a given type from child site.
     * Care endpoint: GET /wp-json/replanta/v1/logs  (task_type, status, limit)
     */
    public function get_task_status($site_url, $token, $task_type, $limit = 10) {
        $endpoint = rtrim($site_url, '/') . '/wp-json/replanta/v1/logs';

        $data = ['task_type' => $task_type, 'limit' => $limit];

        $response = $this->make_request('GET', $endpoint, $data, $token);

        return $response;
    }
    
    /**
     * Get site metrics (includes pending_updates, security_score, performance_score).
     * Care endpoint: GET /wp-json/replanta/v1/metrics
     */
    public function get_updates($site_url, $token) {
        $endpoint = rtrim($site_url, '/') . '/wp-json/replanta/v1/metrics';
        return $this->make_request('GET', $endpoint, [], $token);
    }

    /**
     * Run the updates task on a child site.
     * Care endpoint: POST /wp-json/replanta/v1/run  (task=updates)
     */
    public function install_updates($site_url, $token, $updates = []) {
        $endpoint = rtrim($site_url, '/') . '/wp-json/replanta/v1/run';
        $data = [
            'task' => 'updates',
            'args' => ['items' => $updates, 'auto_backup' => true],
        ];
        return $this->make_request('POST', $endpoint, $data, $token, 300);
    }

    /**
     * Run the health/security task on a child site.
     * Care endpoint: POST /wp-json/replanta/v1/run  (task=health)
     */
    public function run_security_scan($site_url, $token) {
        $endpoint = rtrim($site_url, '/') . '/wp-json/replanta/v1/run';
        return $this->make_request('POST', $endpoint, ['task' => 'health'], $token, 120);
    }

    /**
     * Get health/security logs from a child site.
     * Care endpoint: GET /wp-json/replanta/v1/logs  (task_type=health)
     */
    public function get_security_report($site_url, $token) {
        $endpoint = rtrim($site_url, '/') . '/wp-json/replanta/v1/logs';
        return $this->make_request('GET', $endpoint, ['task_type' => 'health', 'limit' => 50], $token);
    }

    /**
     * Run the backup task on a child site.
     * Care endpoint: POST /wp-json/replanta/v1/run  (task=backup)
     */
    public function create_backup($site_url, $token, $backup_type = 'full') {
        $endpoint = rtrim($site_url, '/') . '/wp-json/replanta/v1/run';
        $data = [
            'task' => 'backup',
            'args' => ['type' => $backup_type],
        ];
        return $this->make_request('POST', $endpoint, $data, $token, 600);
    }

    /**
     * Get backup logs from a child site.
     * Care endpoint: GET /wp-json/replanta/v1/logs  (task_type=backup)
     */
    public function get_backups($site_url, $token) {
        $endpoint = rtrim($site_url, '/') . '/wp-json/replanta/v1/logs';
        return $this->make_request('GET', $endpoint, ['task_type' => 'backup', 'limit' => 20], $token);
    }

    /**
     * Run the WPO (performance optimisation) task on a child site.
     * Care endpoint: POST /wp-json/replanta/v1/run  (task=wpo)
     */
    public function optimize_cache($site_url, $token) {
        $endpoint = rtrim($site_url, '/') . '/wp-json/replanta/v1/run';
        return $this->make_request('POST', $endpoint, ['task' => 'wpo'], $token);
    }

    /**
     * Clear cache by running the WPO task on a child site.
     * Care endpoint: POST /wp-json/replanta/v1/run  (task=wpo, args.action=clear_cache)
     */
    public function clear_cache($site_url, $token) {
        $endpoint = rtrim($site_url, '/') . '/wp-json/replanta/v1/run';
        return $this->make_request('POST', $endpoint, ['task' => 'wpo', 'args' => ['action' => 'clear_cache']], $token);
    }

    /**
     * Get site metrics (includes performance_score, pending_updates, etc.).
     * Care endpoint: GET /wp-json/replanta/v1/metrics
     */
    public function get_performance_report($site_url, $token) {
        $endpoint = rtrim($site_url, '/') . '/wp-json/replanta/v1/metrics';
        return $this->make_request('GET', $endpoint, [], $token);
    }

    /**
     * Get task logs from a child site.
     * Care endpoint: GET /wp-json/replanta/v1/logs
     */
    public function get_logs($site_url, $token, $log_type = 'error', $limit = 100) {
        $endpoint = rtrim($site_url, '/') . '/wp-json/replanta/v1/logs';
        $data = [
            'task_type' => $log_type,
            'limit'     => $limit,
        ];
        return $this->make_request('GET', $endpoint, $data, $token);
    }

    /**
     * Get 404 errors from a child site.
     * Care endpoint: GET /wp-json/replanta/v1/404s
     */
    public function get_404_errors($site_url, $token, $limit = 100) {
        $endpoint = rtrim($site_url, '/') . '/wp-json/replanta/v1/404s';
        return $this->make_request('GET', $endpoint, ['limit' => $limit], $token);
    }

    /**
     * Update site configuration (plan, settings) on a child site.
     * Care endpoint: POST /wp-json/replanta/v1/config
     */
    public function update_site_settings($site_url, $token, $settings) {
        $endpoint = rtrim($site_url, '/') . '/wp-json/replanta/v1/config';

        $data = ['settings' => $settings];

        $response = $this->make_request('POST', $endpoint, $data, $token);

        return $response;
    }

    /**
     * Push configuration change to Care (plan, settings).
     * Calls Care's /wp-json/replanta/v1/config endpoint so changes
     * take effect immediately instead of waiting for the 6-hour transient.
     */
    public function push_config($site_url, $token, $config) {
        $endpoint = rtrim($site_url, '/') . '/wp-json/replanta/v1/config';

        return $this->make_request('POST', $endpoint, $config, $token);
    }

    /**
     * Fetch SA (replanta-site-audit) summary from a managed site.
     * Care reads the transient directly (no nested HTTP) and returns it.
     * GET /wp-json/replanta/v1/sa/summary
     *
     * @return array|WP_Error  Keys: sa_available, global_score, cf_score, seo_score,
     *                         perf_score, cwv_score, issue_count_critical,
     *                         issue_count_warning, last_audit_at
     */
    public function get_sa_summary($site_url, $token) {
        $endpoint = rtrim($site_url, '/') . '/wp-json/replanta/v1/sa/summary';
        return $this->make_request('GET', $endpoint, [], $token, 15);
    }

    /**
     * Get list of SA warning/critical issues from a managed site.
     * Care endpoint: GET /wp-json/replanta/v1/sa/issues
     */
    public function get_sa_issues($site_url, $token) {
        $endpoint = rtrim($site_url, '/') . '/wp-json/replanta/v1/sa/issues';
        return $this->make_request('GET', $endpoint, [], $token, 15);
    }

    /**
     * Execute a replanta-site-audit atomic fix on a managed site.
     * POST /wp-json/replanta/v1/sa/fix  { fix_id: "..." }
     */
    public function run_sa_fix($site_url, $token, $fix_id) {
        $endpoint = rtrim($site_url, '/') . '/wp-json/replanta/v1/sa/fix';
        return $this->make_request('POST', $endpoint, ['fix_id' => $fix_id], $token, 30);
    }
    
    /**
     * Make HTTP request to child site
     */
    private function make_request($method, $url, $data = [], $token = '', $timeout = null) {
        if ($timeout === null) {
            $timeout = $this->timeout;
        }
        
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => $this->user_agent,
            'Accept' => 'application/json'
        ];
        
        if (!empty($token)) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        
        $args = [
            'method' => strtoupper($method),
            'headers' => $headers,
            'timeout' => $timeout,
            'sslverify' => true,
            'user-agent' => $this->user_agent
        ];
        
        if (!empty($data)) {
            if ($method === 'GET') {
                $url = add_query_arg($data, $url);
            } else {
                $args['body'] = json_encode($data);
            }
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return new WP_Error(
                'api_request_failed',
                'Falló la conexión: ' . $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        // Strip UTF-8 BOM if present — some shared hosts inject it via output buffering
        if (substr($response_body, 0, 3) === "\xEF\xBB\xBF") {
            $response_body = substr($response_body, 3);
        }

        if ($response_code >= 400) {
            $error_message = "HTTP {$response_code}";
            
            $decoded_body = json_decode($response_body, true);
            if (isset($decoded_body['message'])) {
                $error_message .= ': ' . $decoded_body['message'];
            }
            
            return new WP_Error('api_http_error', $error_message);
        }
        
        $decoded_response = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error(
                'api_json_error',
                'Respuesta JSON inválida: ' . json_last_error_msg()
            );
        }
        
        return $decoded_response;
    }
    
    /**
     * Validate SSL certificate
     */
    public function validate_ssl($site_url) {
        $parsed_url = parse_url($site_url);
        
        if ($parsed_url['scheme'] !== 'https') {
            return new WP_Error('no_ssl', 'El sitio no usa HTTPS');
        }
        
        $response = wp_remote_get($site_url, [
            'timeout' => 10,
            'sslverify' => true
        ]);
        
        if (is_wp_error($response)) {
            return new WP_Error('ssl_error', 'Error de certificado SSL: ' . $response->get_error_message());
        }
        
        return true;
    }
    
    /**
     * Batch request to multiple sites
     */
    public function batch_request($sites_data, $endpoint, $method = 'GET', $data = []) {
        $results = [];
        
        foreach ($sites_data as $site_id => $site_info) {
            $site_url = $site_info['url'];
            $token = $site_info['token'];
            
            $full_endpoint = rtrim($site_url, '/') . '/wp-json/replanta/v1/' . ltrim($endpoint, '/');
            
            $result = $this->make_request($method, $full_endpoint, $data, $token);
            
            $results[$site_id] = [
                'site_url' => $site_url,
                'success' => !is_wp_error($result),
                'data' => $result
            ];
            
            // Small delay between requests to avoid overwhelming servers
            usleep(100000); // 0.1 seconds
        }
        
        return $results;
    }
    
    /**
     * Get API client version info
     */
    public function get_version_info() {
        return [
            'version' => RPHUB_VERSION,
            'user_agent' => $this->user_agent,
            'timeout' => $this->timeout
        ];
    }
}
