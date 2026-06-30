<?php
/**
 * LiteSpeed Integration for Replanta Hub
 * 
 * Handles LiteSpeed Web Server optimization and cache management
 */

if (!defined('ABSPATH')) {
    exit;
}

class ReplantaHub_LiteSpeed_Integration {
    
    private $api_url;
    private $api_key;
    private $server_ip;
    
    public function __construct() {
        $this->api_url    = get_option('rphub_litespeed_api_url', '');
        $this->api_key    = RPHUB_Crypto::decrypt( get_option('rphub_litespeed_api_key', '') );
        $this->server_ip  = get_option('rphub_server_ip', '');
        
        // If init already fired (class instantiated late), call directly
        if (did_action('init')) {
            $this->init();
        } else {
            add_action('init', [$this, 'init']);
        }
    }
    
    public function init() {
        // AJAX handlers
        add_action('wp_ajax_rphub_litespeed_purge_cache', [$this, 'ajax_purge_cache']);
        add_action('wp_ajax_rphub_litespeed_optimize_site', [$this, 'ajax_optimize_site']);
        add_action('wp_ajax_rphub_litespeed_get_stats', [$this, 'ajax_get_stats']);
        add_action('wp_ajax_rphub_litespeed_test_connection', [$this, 'ajax_test_connection']);
        
        // Scheduled tasks
        add_action('rphub_litespeed_optimize', [$this, 'scheduled_optimization']);
        add_action('rphub_litespeed_cache_warm', [$this, 'scheduled_cache_warmup']);
        
        // Schedule events
        RPHUB_Scheduler::schedule('rphub_litespeed_optimize',  'daily');
        RPHUB_Scheduler::schedule('rphub_litespeed_cache_warm', 'hourly');
    }
    
    /**
     * Check if LiteSpeed integration is configured
     */
    public function is_configured() {
        return !empty($this->api_url) && !empty($this->api_key);
    }
    
    /**
     * Test LiteSpeed connection
     */
    public function test_connection() {
        if (empty($this->api_url) || empty($this->api_key)) {
            return new WP_Error('missing_credentials', 'Falta configurar credenciales de LiteSpeed');
        }
        
        $response = $this->make_api_request('GET', '/api/v1/status');
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return [
            'success' => true,
            'message' => 'Conexión exitosa con LiteSpeed',
            'version' => $response['version'] ?? 'Desconocida'
        ];
    }
    
    /**
     * Purge cache for a specific site
     */
    public function purge_cache($domain) {
        $response = $this->make_api_request('POST', '/api/v1/cache/purge', [
            'domain' => $domain,
            'type' => 'all'
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return [
            'success' => true,
            'message' => 'Cache purgado exitosamente para ' . $domain
        ];
    }
    
    /**
     * Get performance statistics
     */
    public function get_performance_stats($domain) {
        $response = $this->make_api_request('GET', '/api/v1/stats', [
            'domain' => $domain,
            'period' => '24h'
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return [
            'success' => true,
            'stats' => [
                'requests_per_second' => $response['rps'] ?? 0,
                'cache_hit_ratio' => $response['cache_ratio'] ?? 0,
                'response_time' => $response['avg_response_time'] ?? 0,
                'bandwidth_saved' => $response['bandwidth_saved'] ?? 0
            ]
        ];
    }
    
    /**
     * Optimize site configuration
     */
    public function optimize_site($domain) {
        $optimizations = [
            'enable_cache' => true,
            'enable_compression' => true,
            'enable_http2' => true,
            'optimize_images' => true,
            'minify_css' => true,
            'minify_js' => true
        ];
        
        $response = $this->make_api_request('POST', '/api/v1/optimize', [
            'domain' => $domain,
            'settings' => $optimizations
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return [
            'success' => true,
            'message' => 'Sitio optimizado exitosamente',
            'optimizations' => $optimizations
        ];
    }
    
    /**
     * Warm up cache for critical pages
     */
    public function warm_cache($domain) {
        $critical_pages = [
            '/',
            '/about/',
            '/contact/',
            '/blog/'
        ];
        
        $results = [];
        
        foreach ($critical_pages as $page) {
            $response = $this->make_api_request('POST', '/api/v1/cache/warm', [
                'url' => 'https://' . $domain . $page
            ]);
            
            $results[$page] = !is_wp_error($response);
        }
        
        return [
            'success' => true,
            'warmed_pages' => array_filter($results),
            'total_pages' => count($critical_pages)
        ];
    }
    
    /**
     * Make API request to LiteSpeed
     */
    private function make_api_request($method, $endpoint, $data = []) {
        if (empty($this->api_url) || empty($this->api_key)) {
            return new WP_Error('missing_config', 'LiteSpeed no está configurado');
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
        
        if ($method === 'POST' && !empty($data)) {
            $args['body'] = json_encode($data);
        } elseif ($method === 'GET' && !empty($data)) {
            $url = add_query_arg($data, $url);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return new WP_Error('request_failed', 'Error de conexión: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code >= 400) {
            return new WP_Error('api_error', 'Error API: ' . $code);
        }
        
        return json_decode($body, true);
    }
    
    /**
     * AJAX Handlers
     */
    public function ajax_test_connection() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos');
        }
        
        $result = $this->test_connection();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_purge_cache() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos');
        }
        
        $domain = sanitize_text_field($_POST['domain'] ?? '');
        
        if (empty($domain)) {
            wp_send_json_error('Dominio requerido');
        }
        
        $result = $this->purge_cache($domain);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_optimize_site() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos');
        }
        
        $domain = sanitize_text_field($_POST['domain'] ?? '');
        
        if (empty($domain)) {
            wp_send_json_error('Dominio requerido');
        }
        
        $result = $this->optimize_site($domain);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_get_stats() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos');
        }
        
        $domain = sanitize_text_field($_POST['domain'] ?? '');
        
        if (empty($domain)) {
            wp_send_json_error('Dominio requerido');
        }
        
        $result = $this->get_performance_stats($domain);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Scheduled tasks
     */
    public function scheduled_optimization() {
        $sites = get_option('rphub_sites', []);
        
        foreach ($sites as $site) {
            if (!empty($site['domain']) && $site['litespeed_enabled'] ?? false) {
                $this->optimize_site($site['domain']);
            }
        }
    }
    
    public function scheduled_cache_warmup() {
        $sites = get_option('rphub_sites', []);
        
        foreach ($sites as $site) {
            if (!empty($site['domain']) && $site['litespeed_enabled'] ?? false) {
                $this->warm_cache($site['domain']);
            }
        }
    }
    
    /**
     * Run daily optimization
     */
    public function run_daily_optimization() {
        return $this->scheduled_optimization();
    }
}

// Initialize LiteSpeed integration
function rphub_litespeed_init() {
    return new ReplantaHub_LiteSpeed_Integration();
}
add_action('plugins_loaded', 'rphub_litespeed_init');
