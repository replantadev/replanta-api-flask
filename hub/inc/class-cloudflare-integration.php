<?php
/**
 * Cloudflare Integration for Replanta Hub
 * 
 * Provides Cloudflare analytics, cache management, security settings
 * and performance monitoring
 */

class ReplantaHub_Cloudflare_Integration {
    
    private $api_url = 'https://api.cloudflare.com/client/v4';
    private $api_email;
    private $api_key;
    private $zone_cache = [];
    
    public function __construct() {
        $this->api_email = get_option('rphub_cloudflare_email', '');
        $this->api_key   = RPHUB_Crypto::decrypt( get_option('rphub_cloudflare_api_key', '') );
        
        // If init already fired (class instantiated late), call directly
        if (did_action('init')) {
            $this->init();
        } else {
            add_action('init', [$this, 'init']);
        }
    }
    
    public function init() {
        // AJAX handlers
        add_action('wp_ajax_rphub_cloudflare_get_zone_info', [$this, 'ajax_get_zone_info']);
        add_action('wp_ajax_rphub_cloudflare_get_analytics', [$this, 'ajax_get_analytics']);
        add_action('wp_ajax_rphub_cloudflare_purge_cache', [$this, 'ajax_purge_cache']);
        add_action('wp_ajax_rphub_cloudflare_get_security_events', [$this, 'ajax_get_security_events']);
        add_action('wp_ajax_rphub_cloudflare_update_security_level', [$this, 'ajax_update_security_level']);
        add_action('wp_ajax_rphub_cloudflare_get_dns_records', [$this, 'ajax_get_dns_records']);
        add_action('wp_ajax_rphub_cloudflare_get_page_rules', [$this, 'ajax_get_page_rules']);
        add_action('wp_ajax_rphub_cloudflare_get_ssl_status', [$this, 'ajax_get_ssl_status']);
        
        // Scheduled tasks
        add_action('rphub_cloudflare_sync_analytics', [$this, 'scheduled_sync_analytics']);
        add_action('rphub_cloudflare_security_check', [$this, 'scheduled_security_check']);
        
        // Schedule events
        RPHUB_Scheduler::schedule('rphub_cloudflare_sync_analytics', 'hourly');
        RPHUB_Scheduler::schedule('rphub_cloudflare_security_check', 'twicedaily');
    }
    
    /**
     * Check if Cloudflare integration is configured
     */
    public function is_configured() {
        return !empty($this->api_email) && !empty($this->api_key);
    }
    
    /**
     * Get zone information for a domain
     */
    public function get_zone_info($domain) {
        $zone_id = $this->get_zone_id($domain);
        
        if (is_wp_error($zone_id)) {
            return $zone_id;
        }
        
        $response = $this->make_api_request('GET', '/zones/' . $zone_id);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $zone_data = $response['result'] ?? [];
        
        return [
            'zone_id' => $zone_data['id'] ?? '',
            'name' => $zone_data['name'] ?? $domain,
            'status' => $zone_data['status'] ?? 'unknown',
            'development_mode' => $zone_data['development_mode'] ?? 0,
            'name_servers' => $zone_data['name_servers'] ?? [],
            'original_name_servers' => $zone_data['original_name_servers'] ?? [],
            'plan' => [
                'id' => $zone_data['plan']['id'] ?? '',
                'name' => $zone_data['plan']['name'] ?? 'Free',
                'price' => $zone_data['plan']['price'] ?? 0,
                'currency' => $zone_data['plan']['currency'] ?? 'USD'
            ],
            'permissions' => $zone_data['permissions'] ?? [],
            'account' => [
                'id' => $zone_data['account']['id'] ?? '',
                'name' => $zone_data['account']['name'] ?? ''
            ],
            'created_on' => $zone_data['created_on'] ?? '',
            'modified_on' => $zone_data['modified_on'] ?? ''
        ];
    }
    
    /**
     * Get Cloudflare analytics for a domain
     */
    public function get_analytics($domain, $period = '24h') {
        $zone_id = $this->get_zone_id($domain);
        
        if (is_wp_error($zone_id)) {
            return $zone_id;
        }
        
        // Calculate date range based on period
        $end_time = time();
        switch ($period) {
            case '1h':
                $start_time = $end_time - HOUR_IN_SECONDS;
                break;
            case '24h':
                $start_time = $end_time - DAY_IN_SECONDS;
                break;
            case '7d':
                $start_time = $end_time - (7 * DAY_IN_SECONDS);
                break;
            case '30d':
                $start_time = $end_time - (30 * DAY_IN_SECONDS);
                break;
            default:
                $start_time = $end_time - DAY_IN_SECONDS;
        }
        
        $params = [
            'since' => date('Y-m-d\TH:i:s\Z', $start_time),
            'until' => date('Y-m-d\TH:i:s\Z', $end_time),
            'continuous' => 'true'
        ];
        
        $endpoint = '/zones/' . $zone_id . '/analytics/dashboard?' . http_build_query($params);
        $response = $this->make_api_request('GET', $endpoint);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $analytics_data = $response['result'] ?? [];
        
        return $this->process_analytics_data($analytics_data, $period);
    }
    
    /**
     * Get detailed web analytics
     */
    public function get_web_analytics($domain, $period = '24h') {
        $zone_id = $this->get_zone_id($domain);
        
        if (is_wp_error($zone_id)) {
            return $zone_id;
        }
        
        $queries = [
            'requests' => [
                'query' => '
                    query {
                        viewer {
                            zones(filter: {zoneTag: "' . $zone_id . '"}) {
                                httpRequests1dGroups(limit: 100, filter: {datetime_gt: "' . date('Y-m-d\TH:i:s\Z', time() - DAY_IN_SECONDS) . '"}) {
                                    dimensions {
                                        datetime
                                    }
                                    sum {
                                        requests
                                        bytes
                                        cachedRequests
                                        cachedBytes
                                        pageViews
                                        threats
                                    }
                                }
                            }
                        }
                    }
                '
            ],
            'browser_insights' => [
                'query' => '
                    query {
                        viewer {
                            zones(filter: {zoneTag: "' . $zone_id . '"}) {
                                httpRequests1dGroups(limit: 10, filter: {datetime_gt: "' . date('Y-m-d\TH:i:s\Z', time() - DAY_IN_SECONDS) . '"}) {
                                    dimensions {
                                        clientBrowser
                                        clientOS
                                        clientDeviceType
                                    }
                                    sum {
                                        requests
                                    }
                                }
                            }
                        }
                    }
                '
            ]
        ];
        
        $analytics = [];
        
        foreach ($queries as $type => $query) {
            $response = $this->make_graphql_request($query['query']);
            
            if (!is_wp_error($response)) {
                $analytics[$type] = $response;
            }
        }
        
        return $this->process_web_analytics($analytics);
    }
    
    /**
     * Get security events
     */
    public function get_security_events($domain, $limit = 50) {
        $zone_id = $this->get_zone_id($domain);
        
        if (is_wp_error($zone_id)) {
            return $zone_id;
        }
        
        $params = [
            'per_page' => $limit,
            'order' => 'occurred_at',
            'direction' => 'desc'
        ];
        
        $endpoint = '/zones/' . $zone_id . '/security/events?' . http_build_query($params);
        $response = $this->make_api_request('GET', $endpoint);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $events = $response['result'] ?? [];
        $processed_events = [];
        
        foreach ($events as $event) {
            $processed_events[] = [
                'id' => $event['id'] ?? '',
                'occurred_at' => $event['occurred_at'] ?? '',
                'action' => $event['action'] ?? 'unknown',
                'source_ip' => $event['source_ip'] ?? '',
                'source_country' => $event['source_country'] ?? '',
                'host' => $event['host'] ?? '',
                'uri' => $event['uri'] ?? '',
                'user_agent' => $event['user_agent'] ?? '',
                'rule_id' => $event['rule_id'] ?? '',
                'rule_message' => $event['rule_message'] ?? '',
                'kind' => $event['kind'] ?? '',
                'match' => $event['match'] ?? '',
                'metadata' => $event['metadata'] ?? []
            ];
        }
        
        return [
            'events' => $processed_events,
            'total_events' => count($processed_events),
            'event_types' => $this->categorize_security_events($processed_events),
            'top_countries' => $this->get_top_threat_countries($processed_events),
            'threat_summary' => $this->generate_threat_summary($processed_events)
        ];
    }
    
    /**
     * Purge Cloudflare cache
     */
    public function purge_cache($domain, $options = []) {
        $zone_id = $this->get_zone_id($domain);
        
        if (is_wp_error($zone_id)) {
            return $zone_id;
        }
        
        $default_options = [
            'purge_everything' => false,
            'files' => [],
            'tags' => [],
            'hosts' => []
        ];
        
        $options = array_merge($default_options, $options);
        
        $purge_data = [];
        
        if ($options['purge_everything']) {
            $purge_data['purge_everything'] = true;
        } else {
            if (!empty($options['files'])) {
                $purge_data['files'] = $options['files'];
            }
            if (!empty($options['tags'])) {
                $purge_data['tags'] = $options['tags'];
            }
            if (!empty($options['hosts'])) {
                $purge_data['hosts'] = $options['hosts'];
            }
        }
        
        $response = $this->make_api_request('POST', '/zones/' . $zone_id . '/purge_cache', $purge_data);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return [
            'success' => $response['success'] ?? false,
            'id' => $response['result']['id'] ?? '',
            'purged_at' => current_time('mysql')
        ];
    }
    
    /**
     * Get SSL/TLS status
     */
    public function get_ssl_status($domain) {
        $zone_id = $this->get_zone_id($domain);
        
        if (is_wp_error($zone_id)) {
            return $zone_id;
        }
        
        $response = $this->make_api_request('GET', '/zones/' . $zone_id . '/settings/ssl');
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $ssl_data = $response['result'] ?? [];
        
        // Get certificate details
        $cert_response = $this->make_api_request('GET', '/zones/' . $zone_id . '/ssl/certificate_packs');
        $certificates = $cert_response['result'] ?? [];
        
        return [
            'ssl_mode' => $ssl_data['value'] ?? 'off',
            'ssl_status' => $this->get_ssl_status_text($ssl_data['value'] ?? 'off'),
            'certificates' => $this->process_certificates($certificates),
            'universal_ssl' => $this->get_universal_ssl_status($zone_id),
            'ssl_recommendations' => $this->get_ssl_recommendations($ssl_data['value'] ?? 'off')
        ];
    }
    
    /**
     * Get DNS records
     */
    public function get_dns_records($domain) {
        $zone_id = $this->get_zone_id($domain);
        
        if (is_wp_error($zone_id)) {
            return $zone_id;
        }
        
        $response = $this->make_api_request('GET', '/zones/' . $zone_id . '/dns_records');
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $records = $response['result'] ?? [];
        $processed_records = [];
        
        foreach ($records as $record) {
            $processed_records[] = [
                'id' => $record['id'] ?? '',
                'type' => $record['type'] ?? '',
                'name' => $record['name'] ?? '',
                'content' => $record['content'] ?? '',
                'ttl' => $record['ttl'] ?? 0,
                'priority' => $record['priority'] ?? null,
                'proxied' => $record['proxied'] ?? false,
                'proxiable' => $record['proxiable'] ?? false,
                'created_on' => $record['created_on'] ?? '',
                'modified_on' => $record['modified_on'] ?? ''
            ];
        }
        
        return [
            'records' => $processed_records,
            'total_records' => count($processed_records),
            'record_types' => $this->categorize_dns_records($processed_records),
            'proxied_records' => array_filter($processed_records, function($r) { return $r['proxied']; })
        ];
    }
    
    /**
     * Get page rules
     */
    public function get_page_rules($domain) {
        $zone_id = $this->get_zone_id($domain);
        
        if (is_wp_error($zone_id)) {
            return $zone_id;
        }
        
        $response = $this->make_api_request('GET', '/zones/' . $zone_id . '/pagerules');
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $rules = $response['result'] ?? [];
        $processed_rules = [];
        
        foreach ($rules as $rule) {
            $processed_rules[] = [
                'id' => $rule['id'] ?? '',
                'targets' => $rule['targets'] ?? [],
                'actions' => $rule['actions'] ?? [],
                'priority' => $rule['priority'] ?? 0,
                'status' => $rule['status'] ?? 'disabled',
                'created_on' => $rule['created_on'] ?? '',
                'modified_on' => $rule['modified_on'] ?? ''
            ];
        }
        
        return [
            'rules' => $processed_rules,
            'total_rules' => count($processed_rules),
            'active_rules' => array_filter($processed_rules, function($r) { return $r['status'] === 'active'; })
        ];
    }
    
    /**
     * Update security level
     */
    public function update_security_level($domain, $level) {
        $zone_id = $this->get_zone_id($domain);
        
        if (is_wp_error($zone_id)) {
            return $zone_id;
        }
        
        $valid_levels = ['essentially_off', 'low', 'medium', 'high', 'under_attack'];
        
        if (!in_array($level, $valid_levels)) {
            return new WP_Error('invalid_level', 'Nivel de seguridad inválido');
        }
        
        $response = $this->make_api_request('PATCH', '/zones/' . $zone_id . '/settings/security_level', [
            'value' => $level
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return [
            'success' => $response['success'] ?? false,
            'security_level' => $level,
            'updated_at' => current_time('mysql')
        ];
    }
    
    /**
     * Get performance metrics summary
     */
    public function get_performance_summary($domain) {
        $analytics = $this->get_analytics($domain, '24h');
        
        if (is_wp_error($analytics)) {
            return $analytics;
        }
        
        $zone_info = $this->get_zone_info($domain);
        
        return [
            'cache_hit_ratio' => $analytics['cache_hit_ratio'] ?? 0,
            'bandwidth_saved' => $analytics['bandwidth_saved'] ?? '0 MB',
            'requests_served' => $analytics['total_requests'] ?? 0,
            'threats_blocked' => $analytics['total_threats'] ?? 0,
            'ssl_status' => $zone_info['ssl_status'] ?? 'unknown',
            'plan_type' => $zone_info['plan']['name'] ?? 'Free',
            'zone_status' => $zone_info['status'] ?? 'unknown',
            'performance_grade' => $this->calculate_performance_grade($analytics)
        ];
    }
    
    /**
     * Get zone ID for domain
     */
    private function get_zone_id($domain) {
        // Check cache first
        if (isset($this->zone_cache[$domain])) {
            return $this->zone_cache[$domain];
        }
        
        $response = $this->make_api_request('GET', '/zones?name=' . $domain);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $zones = $response['result'] ?? [];
        
        if (empty($zones)) {
            return new WP_Error('zone_not_found', 'Zona de Cloudflare no encontrada para el dominio: ' . $domain);
        }
        
        $zone_id = $zones[0]['id'] ?? '';
        
        // Cache the result
        $this->zone_cache[$domain] = $zone_id;
        
        return $zone_id;
    }
    
    /**
     * Process analytics data
     */
    private function process_analytics_data($data, $period) {
        $totals = $data['totals'] ?? [];
        $timeseries = $data['timeseries'] ?? [];
        
        $processed = [
            'period' => $period,
            'total_requests' => $totals['requests']['all'] ?? 0,
            'cached_requests' => $totals['requests']['cached'] ?? 0,
            'uncached_requests' => $totals['requests']['uncached'] ?? 0,
            'total_bandwidth' => $totals['bandwidth']['all'] ?? 0,
            'cached_bandwidth' => $totals['bandwidth']['cached'] ?? 0,
            'uncached_bandwidth' => $totals['bandwidth']['uncached'] ?? 0,
            'total_threats' => $totals['threats']['all'] ?? 0,
            'total_pageviews' => $totals['pageviews']['all'] ?? 0,
            'unique_visitors' => $totals['uniques']['all'] ?? 0,
            'cache_hit_ratio' => 0,
            'bandwidth_saved' => '0 MB',
            'timeseries' => []
        ];
        
        // Calculate cache hit ratio
        if ($processed['total_requests'] > 0) {
            $processed['cache_hit_ratio'] = round(
                ($processed['cached_requests'] / $processed['total_requests']) * 100, 
                2
            );
        }
        
        // Calculate bandwidth saved
        if ($processed['cached_bandwidth'] > 0) {
            $processed['bandwidth_saved'] = $this->format_bytes($processed['cached_bandwidth']);
        }
        
        // Process timeseries data
        foreach ($timeseries as $point) {
            $processed['timeseries'][] = [
                'datetime' => $point['since'] ?? '',
                'requests' => $point['requests']['all'] ?? 0,
                'cached_requests' => $point['requests']['cached'] ?? 0,
                'bandwidth' => $point['bandwidth']['all'] ?? 0,
                'threats' => $point['threats']['all'] ?? 0,
                'pageviews' => $point['pageviews']['all'] ?? 0
            ];
        }
        
        return $processed;
    }
    
    /**
     * Process web analytics data
     */
    private function process_web_analytics($analytics) {
        // This would process GraphQL response data
        // Simplified implementation
        return [
            'browser_breakdown' => [],
            'os_breakdown' => [],
            'device_breakdown' => [],
            'geographic_breakdown' => []
        ];
    }
    
    /**
     * Categorize security events
     */
    private function categorize_security_events($events) {
        $categories = [];
        
        foreach ($events as $event) {
            $action = $event['action'] ?? 'unknown';
            $categories[$action] = ($categories[$action] ?? 0) + 1;
        }
        
        return $categories;
    }
    
    /**
     * Get top threat countries
     */
    private function get_top_threat_countries($events) {
        $countries = [];
        
        foreach ($events as $event) {
            $country = $event['source_country'] ?? 'unknown';
            $countries[$country] = ($countries[$country] ?? 0) + 1;
        }
        
        arsort($countries);
        return array_slice($countries, 0, 10, true);
    }
    
    /**
     * Generate threat summary
     */
    private function generate_threat_summary($events) {
        $total_events = count($events);
        $blocked_events = array_filter($events, function($e) { return $e['action'] === 'block'; });
        $challenge_events = array_filter($events, function($e) { return $e['action'] === 'challenge'; });
        
        return [
            'total_events' => $total_events,
            'blocked_threats' => count($blocked_events),
            'challenged_requests' => count($challenge_events),
            'block_rate' => $total_events > 0 ? round((count($blocked_events) / $total_events) * 100, 2) : 0
        ];
    }
    
    /**
     * Process certificates
     */
    private function process_certificates($certificates) {
        $processed = [];
        
        foreach ($certificates as $cert) {
            $processed[] = [
                'id' => $cert['id'] ?? '',
                'type' => $cert['type'] ?? '',
                'primary_certificate' => $cert['primary_certificate'] ?? '',
                'status' => $cert['status'] ?? '',
                'validation_method' => $cert['validation_method'] ?? '',
                'validity_days' => $cert['validity_days'] ?? 0,
                'certificate_authority' => $cert['certificate_authority'] ?? '',
                'wildcard' => $cert['wildcard'] ?? false
            ];
        }
        
        return $processed;
    }
    
    /**
     * Get Universal SSL status
     */
    private function get_universal_ssl_status($zone_id) {
        $response = $this->make_api_request('GET', '/zones/' . $zone_id . '/ssl/universal/settings');
        
        if (is_wp_error($response)) {
            return ['enabled' => false, 'status' => 'unknown'];
        }
        
        $result = $response['result'] ?? [];
        
        return [
            'enabled' => $result['enabled'] ?? false,
            'status' => $result['enabled'] ? 'active' : 'disabled'
        ];
    }
    
    /**
     * Get SSL status text
     */
    private function get_ssl_status_text($mode) {
        $statuses = [
            'off' => 'Desactivado',
            'flexible' => 'Flexible',
            'full' => 'Completo',
            'full_strict' => 'Completo (estricto)',
            'strict' => 'Estricto'
        ];
        
        return $statuses[$mode] ?? 'Desconocido';
    }
    
    /**
     * Get SSL recommendations
     */
    private function get_ssl_recommendations($mode) {
        $recommendations = [];
        
        switch ($mode) {
            case 'off':
                $recommendations[] = 'Activar SSL/TLS para mejorar la seguridad';
                $recommendations[] = 'Configurar certificado SSL en el servidor origen';
                break;
            case 'flexible':
                $recommendations[] = 'Actualizar a modo "Completo" para mayor seguridad';
                $recommendations[] = 'Instalar certificado SSL válido en el servidor origen';
                break;
            case 'full':
                $recommendations[] = 'Considerar actualizar a "Completo (estricto)" para máxima seguridad';
                break;
            case 'full_strict':
                $recommendations[] = 'Configuración SSL óptima - mantener configuración actual';
                break;
        }
        
        return $recommendations;
    }
    
    /**
     * Categorize DNS records
     */
    private function categorize_dns_records($records) {
        $types = [];
        
        foreach ($records as $record) {
            $type = $record['type'];
            $types[$type] = ($types[$type] ?? 0) + 1;
        }
        
        return $types;
    }
    
    /**
     * Calculate performance grade
     */
    private function calculate_performance_grade($analytics) {
        $cache_ratio = $analytics['cache_hit_ratio'] ?? 0;
        
        if ($cache_ratio >= 95) {
            return 'A';
        } elseif ($cache_ratio >= 85) {
            return 'B';
        } elseif ($cache_ratio >= 70) {
            return 'C';
        } elseif ($cache_ratio >= 50) {
            return 'D';
        } else {
            return 'F';
        }
    }
    
    /**
     * Format bytes
     */
    private function format_bytes($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
    
    /**
     * Make API request to Cloudflare
     */
    private function make_api_request($method, $endpoint, $data = []) {
        if (empty($this->api_email) || empty($this->api_key)) {
            return new WP_Error('missing_credentials', 'Cloudflare API credentials not configured');
        }
        
        $url = $this->api_url . $endpoint;
        
        $args = [
            'method' => $method,
            'headers' => [
                'X-Auth-Email' => $this->api_email,
                'X-Auth-Key' => $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ];
        
        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($code >= 400) {
            return new WP_Error('api_error', 'Cloudflare API error: ' . $code);
        }
        
        $decoded = json_decode($body, true);
        
        if (!$decoded || !$decoded['success']) {
            $errors = $decoded['errors'] ?? [['message' => 'Unknown API error']];
            return new WP_Error('api_error', $errors[0]['message']);
        }
        
        return $decoded;
    }
    
    /**
     * Make GraphQL request
     */
    private function make_graphql_request($query) {
        $url = 'https://api.cloudflare.com/client/v4/graphql';
        
        $args = [
            'method' => 'POST',
            'headers' => [
                'X-Auth-Email' => $this->api_email,
                'X-Auth-Key' => $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode(['query' => $query]),
            'timeout' => 30
        ];
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }
    
    /**
     * AJAX Handlers
     */
    public function ajax_get_zone_info() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $domain = $_POST['domain'] ?? '';
        if (empty($domain)) {
            wp_send_json_error('Dominio requerido');
        }
        
        $result = $this->get_zone_info($domain);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_get_analytics() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $domain = $_POST['domain'] ?? '';
        $period = $_POST['period'] ?? '24h';
        
        if (empty($domain)) {
            wp_send_json_error('Dominio requerido');
        }
        
        $result = $this->get_analytics($domain, $period);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_purge_cache() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $domain = $_POST['domain'] ?? '';
        $options = $_POST['options'] ?? [];
        
        if (empty($domain)) {
            wp_send_json_error('Dominio requerido');
        }
        
        $result = $this->purge_cache($domain, $options);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_get_security_events() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $domain = $_POST['domain'] ?? '';
        $limit = $_POST['limit'] ?? 50;
        
        if (empty($domain)) {
            wp_send_json_error('Dominio requerido');
        }
        
        $result = $this->get_security_events($domain, $limit);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_update_security_level() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $domain = $_POST['domain'] ?? '';
        $level = $_POST['level'] ?? '';
        
        if (empty($domain) || empty($level)) {
            wp_send_json_error('Dominio y nivel de seguridad requeridos');
        }
        
        $result = $this->update_security_level($domain, $level);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_get_dns_records() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $domain = $_POST['domain'] ?? '';
        if (empty($domain)) {
            wp_send_json_error('Dominio requerido');
        }
        
        $result = $this->get_dns_records($domain);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_get_page_rules() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $domain = $_POST['domain'] ?? '';
        if (empty($domain)) {
            wp_send_json_error('Dominio requerido');
        }
        
        $result = $this->get_page_rules($domain);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_get_ssl_status() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $domain = $_POST['domain'] ?? '';
        if (empty($domain)) {
            wp_send_json_error('Dominio requerido');
        }
        
        $result = $this->get_ssl_status($domain);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Scheduled Tasks
     */
    public function scheduled_sync_analytics() {
        global $wpdb;
        $table_sites = $wpdb->prefix . 'rphub_sites';

        // Fetch all active sites — cloudflare_enabled column does not exist in schema;
        // site_meta cf_zone_id is the authoritative indicator.
        $sites = $wpdb->get_results(
            "SELECT s.id, s.url FROM $table_sites s
             INNER JOIN {$wpdb->prefix}rphub_site_meta m
                ON m.site_id = s.id AND m.meta_key = 'cf_zone_id' AND m.meta_value != ''
             WHERE s.status = 'active'
             LIMIT 20",
            ARRAY_A
        );
        
        foreach ($sites as $site) {
            $domain = parse_url($site['url'], PHP_URL_HOST);
            $analytics = $this->get_analytics($domain, '1h');
            
            if (!is_wp_error($analytics)) {
                $this->store_cloudflare_analytics($site['id'], $analytics);
            }
            
            // Small delay between requests
            sleep(1);
        }
    }
    
    public function scheduled_security_check() {
        global $wpdb;
        $table_sites = $wpdb->prefix . 'rphub_sites';
        
        $sites = $wpdb->get_results(
            "SELECT s.* FROM $table_sites s
             INNER JOIN {$wpdb->prefix}rphub_site_meta m
                ON m.site_id = s.id AND m.meta_key = 'cf_zone_id' AND m.meta_value != ''
             WHERE s.status = 'active'",
            ARRAY_A
        );
        
        foreach ($sites as $site) {
            $domain = parse_url($site['url'], PHP_URL_HOST);
            $security_events = $this->get_security_events($domain, 10);
            
            if (!is_wp_error($security_events) && 
                $security_events['total_events'] > 0) {
                
                $this->create_security_alert($site['id'], $security_events);
            }
        }
    }
    
    /**
     * Store Cloudflare analytics
     */
    private function store_cloudflare_analytics($site_id, $analytics) {
        global $wpdb;
        $table_cf_analytics = $wpdb->prefix . 'rphub_cloudflare_analytics';
        
        $wpdb->insert($table_cf_analytics, [
            'site_id' => $site_id,
            'total_requests' => $analytics['total_requests'],
            'cached_requests' => $analytics['cached_requests'],
            'cache_hit_ratio' => $analytics['cache_hit_ratio'],
            'bandwidth_saved' => $analytics['cached_bandwidth'],
            'threats_blocked' => $analytics['total_threats'],
            'created_at' => current_time('mysql')
        ]);
    }
    
    /**
     * Create security alert
     */
    private function create_security_alert($site_id, $security_events) {
        global $wpdb;
        $table_notifications = $wpdb->prefix . 'rphub_notifications';
        
        $message = sprintf(
            'Eventos de seguridad detectados: %d eventos, %d amenazas bloqueadas',
            $security_events['total_events'],
            $security_events['threat_summary']['blocked_threats']
        );
        
        $wpdb->insert($table_notifications, [
            'site_id' => $site_id,
            'type' => 'security',
            'severity' => 'warning',
            'title' => 'Actividad de Seguridad Cloudflare',
            'message' => $message,
            'created_at' => current_time('mysql')
        ]);
    }
    
    /**
     * Sync analytics (alias for scheduled_sync_analytics)
     */
    public function sync_analytics() {
        return $this->scheduled_sync_analytics();
    }
    
    /**
     * Run hourly checks
     */
    public function run_hourly_checks() {
        return $this->scheduled_security_check();
    }
}

// Initialize Cloudflare integration
function rphub_cloudflare_init() {
    return new ReplantaHub_Cloudflare_Integration();
}
add_action('plugins_loaded', 'rphub_cloudflare_init');
