<?php
/**
 * Analytics Integration for Replanta Hub Professional
 * 
 * Handles integration with Google Analytics 4, Search Console, and Web Vitals
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ReplantaHub_Analytics_Integration {
    
    private $ga4_property_id;
    private $search_console_domain;
    private $credentials_file;
    private $access_token;
    private $refresh_token;
    
    public function __construct() {
        $this->init_hooks();
        $this->load_credentials();
    }
    
    private function init_hooks() {
        add_action('init', array($this, 'schedule_analytics_sync'));
        add_action('rphub_sync_analytics_data', array($this, 'sync_all_analytics_data'));
        add_action('wp_ajax_rphub_test_analytics_connection', array($this, 'test_analytics_connection'));
        add_action('wp_ajax_rphub_sync_analytics', array($this, 'manual_sync_analytics'));
        add_action('wp_ajax_rphub_get_analytics_overview', array($this, 'get_analytics_overview'));
    }
    
    private function load_credentials() {
        $settings = get_option('replanta_hub_analytics_settings', array());
        $this->ga4_property_id = $settings['ga4_property_id'] ?? '';
        $this->search_console_domain = $settings['search_console_domain'] ?? '';
        $this->access_token = $settings['access_token'] ?? '';
        $this->refresh_token = $settings['refresh_token'] ?? '';
    }
    
    public function schedule_analytics_sync() {
        RPHUB_Scheduler::schedule('rphub_sync_analytics_data', 'hourly');
    }
    
    /**
     * Test connection to Google Analytics and Search Console
     */
    public function test_analytics_connection() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        try {
            $results = array();
            
            // Test Google Analytics 4 connection
            $ga4_test = $this->test_ga4_connection();
            $results['ga4'] = $ga4_test;
            
            // Test Search Console connection
            $sc_test = $this->test_search_console_connection();
            $results['search_console'] = $sc_test;
            
            // Test Web Vitals API
            $vitals_test = $this->test_web_vitals_connection();
            $results['web_vitals'] = $vitals_test;
            
            $overall_status = ($ga4_test['success'] && $sc_test['success'] && $vitals_test['success']) ? 'success' : 'partial';
            
            wp_send_json_success(array(
                'status' => $overall_status,
                'message' => 'Prueba de conexión completada',
                'results' => $results
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Error en la prueba de conexión: ' . $e->getMessage());
        }
    }
    
    private function test_ga4_connection() {
        if (empty($this->ga4_property_id) || empty($this->access_token)) {
            return array(
                'success' => false,
                'message' => 'Credenciales de GA4 no configuradas'
            );
        }
        
        try {
            $response = $this->make_ga4_request('metadata/dimensions');
            
            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'message' => 'Error de conexión: ' . $response->get_error_message()
                );
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (isset($data['dimensions'])) {
                return array(
                    'success' => true,
                    'message' => 'Conexión exitosa con Google Analytics 4',
                    'property_id' => $this->ga4_property_id
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'Respuesta inválida de GA4'
                );
            }
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            );
        }
    }
    
    private function test_search_console_connection() {
        if (empty($this->search_console_domain) || empty($this->access_token)) {
            return array(
                'success' => false,
                'message' => 'Credenciales de Search Console no configuradas'
            );
        }
        
        try {
            $response = $this->make_search_console_request('sites/' . urlencode($this->search_console_domain));
            
            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'message' => 'Error de conexión: ' . $response->get_error_message()
                );
            }
            
            $code = wp_remote_retrieve_response_code($response);
            
            if ($code === 200) {
                return array(
                    'success' => true,
                    'message' => 'Conexión exitosa con Search Console',
                    'domain' => $this->search_console_domain
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'Error HTTP ' . $code
                );
            }
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            );
        }
    }
    
    private function test_web_vitals_connection() {
        try {
            // Test Chrome UX Report API for Web Vitals
            $test_url = home_url();
            $vitals_data = $this->get_web_vitals_data($test_url);
            
            if ($vitals_data && !empty($vitals_data)) {
                return array(
                    'success' => true,
                    'message' => 'Web Vitals API disponible',
                    'metrics_available' => count($vitals_data)
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'No hay datos de Web Vitals disponibles'
                );
            }
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Sync all analytics data for all sites
     */
    public function sync_all_analytics_data() {
        global $wpdb;

        $sites = $wpdb->get_results("
            SELECT id, url, ga4_property_id, sc_domain
            FROM {$wpdb->prefix}rphub_sites
            WHERE status = 'active' AND url IS NOT NULL
        ");

        foreach ($sites as $site) {
            $ga4_id = !empty($site->ga4_property_id) ? $site->ga4_property_id : $this->ga4_property_id;
            $sc_dom = !empty($site->sc_domain)       ? $site->sc_domain       : $this->search_console_domain;
            $this->sync_site_analytics($site->id, $site->url, $ga4_id, $sc_dom);
        }

        update_option('rphub_last_analytics_sync', current_time('mysql'));
    }
    
    /**
     * Manual sync via AJAX
     */
    public function manual_sync_analytics() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        try {
            $site_id = isset($_POST['site_id']) ? intval($_POST['site_id']) : null;
            
            if ($site_id) {
                // Sync specific site
                global $wpdb;
                $site = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}rphub_sites WHERE id = %d",
                    $site_id
                ));
                
                if ($site) {
                    $ga4_id = !empty($site->ga4_property_id) ? $site->ga4_property_id : $this->ga4_property_id;
                    $sc_dom = !empty($site->sc_domain)       ? $site->sc_domain       : $this->search_console_domain;
                    $results = $this->sync_site_analytics($site->id, $site->url, $ga4_id, $sc_dom);
                    wp_send_json_success(array(
                        'message' => 'Sincronización completada para ' . $site->name,
                        'results' => $results
                    ));
                } else {
                    wp_send_json_error('Sitio no encontrado');
                }
            } else {
                // Sync all sites
                $this->sync_all_analytics_data();
                wp_send_json_success('Sincronización completada para todos los sitios');
            }
            
        } catch (Exception $e) {
            wp_send_json_error('Error en sincronización: ' . $e->getMessage());
        }
    }
    
    /**
     * Sync analytics data for a specific site.
     * $ga4_property_id and $sc_domain are per-site overrides; falls back to global settings.
     */
    public function sync_site_analytics($site_id, $site_url, $ga4_property_id = '', $sc_domain = '') {
        $results = array();

        try {
            $ga4_data = $this->get_ga4_data($site_url, $ga4_property_id);
            if ($ga4_data) {
                $this->store_ga4_data($site_id, $ga4_data);
                $results['ga4'] = 'success';
            }

            $sc_data = $this->get_search_console_data($site_url, $sc_domain);
            if ($sc_data) {
                $this->store_search_console_data($site_id, $sc_data);
                $results['search_console'] = 'success';
            }
            
            // Get Web Vitals data
            $vitals_data = $this->get_web_vitals_data($site_url);
            if ($vitals_data) {
                $this->store_web_vitals_data($site_id, $vitals_data);
                $results['web_vitals'] = 'success';
            }
            
            // Get Real User Monitoring data
            $rum_data = $this->get_rum_data($site_url);
            if ($rum_data) {
                $this->store_rum_data($site_id, $rum_data);
                $results['rum'] = 'success';
            }
            
        } catch (Exception $e) {
            error_log('Error syncing analytics for site ' . $site_id . ': ' . $e->getMessage());
            $results['error'] = $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Get Google Analytics 4 data for a site, using its per-site property ID.
     */
    private function get_ga4_data($site_url, $property_id = '') {
        $property_id = $property_id ?: $this->ga4_property_id;
        if (empty($property_id) || empty($this->access_token)) {
            return false;
        }

        $end_date   = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime('-30 days'));

        $request_body = array(
            'dateRanges' => array(
                array(
                    'startDate' => $start_date,
                    'endDate' => $end_date
                )
            ),
            'dimensions' => array(
                array('name' => 'date'),
                array('name' => 'deviceCategory'),
                array('name' => 'country')
            ),
            'metrics' => array(
                array('name' => 'sessions'),
                array('name' => 'users'),
                array('name' => 'pageviews'),
                array('name' => 'bounceRate'),
                array('name' => 'sessionDuration'),
                array('name' => 'engagementRate')
            ),
            'dimensionFilter' => array(
                'filter' => array(
                    'fieldName' => 'hostname',
                    'stringFilter' => array(
                        'matchType' => 'EXACT',
                        'value' => parse_url($site_url, PHP_URL_HOST)
                    )
                )
            )
        );
        
        $response = $this->make_ga4_request('runReport', 'POST', $request_body, $property_id);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return $this->process_ga4_response($data);
    }
    
    /**
     * Get Search Console data for a site, using its per-site sc_domain.
     */
    private function get_search_console_data($site_url, $sc_domain = '') {
        $sc_domain = $sc_domain ?: $this->search_console_domain;
        if (empty($sc_domain) || empty($this->access_token)) {
            return false;
        }

        $end_date   = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime('-30 days'));

        $request_body = array(
            'startDate'  => $start_date,
            'endDate'    => $end_date,
            'dimensions' => array('date', 'query', 'page', 'device'),
            'rowLimit'   => 1000,
            'startRow'   => 0,
        );

        $endpoint = 'sites/' . urlencode($sc_domain) . '/searchAnalytics/query';
        $response = $this->make_search_console_request($endpoint, 'POST', $request_body);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return $this->process_search_console_response($data);
    }
    
    /**
     * Get Web Vitals data from Chrome UX Report
     */
    private function get_web_vitals_data($site_url) {
        $api_key = get_option('replanta_hub_google_api_key');
        
        if (empty($api_key)) {
            return false;
        }
        
        $request_body = array(
            'url' => $site_url,
            'formFactor' => 'DESKTOP',
            'metrics' => array(
                'LARGEST_CONTENTFUL_PAINT',
                'FIRST_INPUT_DELAY',
                'CUMULATIVE_LAYOUT_SHIFT',
                'FIRST_CONTENTFUL_PAINT',
                'INTERACTION_TO_NEXT_PAINT'
            )
        );
        
        $response = wp_remote_post(
            'https://chromeuxreport.googleapis.com/v1/records:queryRecord?key=' . $api_key,
            array(
                'headers' => array(
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($request_body),
                'timeout' => 30
            )
        );
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return $this->process_web_vitals_response($data);
    }
    
    /**
     * Get Real User Monitoring data
     */
    private function get_rum_data($site_url) {
        return array(
            'avg_page_load_time' => null,
            'avg_dom_ready_time' => null,
            'avg_first_paint' => null,
            'bounce_rate' => null,
            'user_satisfaction' => null,
            'error_rate' => null,
            'total_sessions' => null,
            'unique_visitors' => null,
            'geo_data' => array(),
            'device_breakdown' => array(),
            'collected_at' => null,
            'data_available' => false
        );
    }
    
    /**
     * Process GA4 API response
     */
    private function process_ga4_response($data) {
        if (!isset($data['rows'])) {
            return false;
        }
        
        $processed = array(
            'total_sessions' => 0,
            'total_users' => 0,
            'total_pageviews' => 0,
            'avg_bounce_rate' => 0,
            'avg_session_duration' => 0,
            'avg_engagement_rate' => 0,
            'daily_data' => array(),
            'device_breakdown' => array(
                'desktop' => 0,
                'mobile' => 0,
                'tablet' => 0
            ),
            'top_countries' => array()
        );
        
        $country_data = array();
        $daily_data = array();
        
        foreach ($data['rows'] as $row) {
            $date = $row['dimensionValues'][0]['value'];
            $device = $row['dimensionValues'][1]['value'];
            $country = $row['dimensionValues'][2]['value'];
            
            $sessions = intval($row['metricValues'][0]['value']);
            $users = intval($row['metricValues'][1]['value']);
            $pageviews = intval($row['metricValues'][2]['value']);
            $bounce_rate = floatval($row['metricValues'][3]['value']);
            $session_duration = floatval($row['metricValues'][4]['value']);
            $engagement_rate = floatval($row['metricValues'][5]['value']);
            
            // Aggregate totals
            $processed['total_sessions'] += $sessions;
            $processed['total_users'] += $users;
            $processed['total_pageviews'] += $pageviews;
            
            // Device breakdown
            $device_key = strtolower($device);
            if (isset($processed['device_breakdown'][$device_key])) {
                $processed['device_breakdown'][$device_key] += $sessions;
            }
            
            // Country data
            if (!isset($country_data[$country])) {
                $country_data[$country] = 0;
            }
            $country_data[$country] += $sessions;
            
            // Daily data
            if (!isset($daily_data[$date])) {
                $daily_data[$date] = array(
                    'sessions' => 0,
                    'users' => 0,
                    'pageviews' => 0
                );
            }
            $daily_data[$date]['sessions'] += $sessions;
            $daily_data[$date]['users'] += $users;
            $daily_data[$date]['pageviews'] += $pageviews;
        }
        
        // Calculate averages
        $total_rows = count($data['rows']);
        if ($total_rows > 0) {
            $processed['avg_bounce_rate'] = $processed['avg_bounce_rate'] / $total_rows;
            $processed['avg_session_duration'] = $processed['avg_session_duration'] / $total_rows;
            $processed['avg_engagement_rate'] = $processed['avg_engagement_rate'] / $total_rows;
        }
        
        // Sort and limit top countries
        arsort($country_data);
        $processed['top_countries'] = array_slice($country_data, 0, 10, true);
        
        $processed['daily_data'] = $daily_data;
        $processed['collected_at'] = current_time('mysql');
        
        return $processed;
    }
    
    /**
     * Process Search Console API response
     */
    private function process_search_console_response($data) {
        if (!isset($data['rows'])) {
            return false;
        }
        
        $processed = array(
            'total_impressions' => 0,
            'total_clicks' => 0,
            'avg_ctr' => 0,
            'avg_position' => 0,
            'top_queries' => array(),
            'top_pages' => array(),
            'device_breakdown' => array(
                'desktop' => array('clicks' => 0, 'impressions' => 0),
                'mobile' => array('clicks' => 0, 'impressions' => 0),
                'tablet' => array('clicks' => 0, 'impressions' => 0)
            ),
            'daily_data' => array()
        );
        
        $query_data = array();
        $page_data = array();
        $daily_data = array();
        
        foreach ($data['rows'] as $row) {
            $date = $row['keys'][0];
            $query = $row['keys'][1];
            $page = $row['keys'][2];
            $device = strtolower($row['keys'][3]);
            
            $clicks = intval($row['clicks']);
            $impressions = intval($row['impressions']);
            $ctr = floatval($row['ctr']);
            $position = floatval($row['position']);
            
            // Aggregate totals
            $processed['total_clicks'] += $clicks;
            $processed['total_impressions'] += $impressions;
            
            // Query data
            if (!isset($query_data[$query])) {
                $query_data[$query] = array(
                    'clicks' => 0,
                    'impressions' => 0,
                    'ctr' => 0,
                    'position' => 0
                );
            }
            $query_data[$query]['clicks'] += $clicks;
            $query_data[$query]['impressions'] += $impressions;
            
            // Page data
            if (!isset($page_data[$page])) {
                $page_data[$page] = array(
                    'clicks' => 0,
                    'impressions' => 0
                );
            }
            $page_data[$page]['clicks'] += $clicks;
            $page_data[$page]['impressions'] += $impressions;
            
            // Device breakdown
            if (isset($processed['device_breakdown'][$device])) {
                $processed['device_breakdown'][$device]['clicks'] += $clicks;
                $processed['device_breakdown'][$device]['impressions'] += $impressions;
            }
            
            // Daily data
            if (!isset($daily_data[$date])) {
                $daily_data[$date] = array(
                    'clicks' => 0,
                    'impressions' => 0
                );
            }
            $daily_data[$date]['clicks'] += $clicks;
            $daily_data[$date]['impressions'] += $impressions;
        }
        
        // Calculate averages
        if ($processed['total_impressions'] > 0) {
            $processed['avg_ctr'] = ($processed['total_clicks'] / $processed['total_impressions']) * 100;
        }
        
        // Sort and limit top queries and pages
        uasort($query_data, function($a, $b) {
            return $b['clicks'] - $a['clicks'];
        });
        $processed['top_queries'] = array_slice($query_data, 0, 20, true);
        
        uasort($page_data, function($a, $b) {
            return $b['clicks'] - $a['clicks'];
        });
        $processed['top_pages'] = array_slice($page_data, 0, 20, true);
        
        $processed['daily_data'] = $daily_data;
        $processed['collected_at'] = current_time('mysql');
        
        return $processed;
    }
    
    /**
     * Process Web Vitals API response
     */
    private function process_web_vitals_response($data) {
        if (!isset($data['record']['metrics'])) {
            return false;
        }
        
        $processed = array();
        $metrics = $data['record']['metrics'];
        
        foreach ($metrics as $metric_name => $metric_data) {
            $processed[strtolower($metric_name)] = array(
                'p75' => $metric_data['percentiles']['p75'] ?? null,
                'good' => $metric_data['histogram'][0]['density'] ?? 0,
                'needs_improvement' => $metric_data['histogram'][1]['density'] ?? 0,
                'poor' => $metric_data['histogram'][2]['density'] ?? 0
            );
        }
        
        $processed['collected_at'] = current_time('mysql');
        
        return $processed;
    }
    
    /**
     * Store GA4 data in database
     */
    private function store_ga4_data($site_id, $data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'rphub_analytics_ga4';
        
        $wpdb->replace(
            $table,
            array(
                'site_id' => $site_id,
                'data' => json_encode($data),
                'collected_at' => $data['collected_at']
            ),
            array('%d', '%s', '%s')
        );
    }
    
    /**
     * Store Search Console data in database
     */
    private function store_search_console_data($site_id, $data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'rphub_analytics_search_console';
        
        $wpdb->replace(
            $table,
            array(
                'site_id' => $site_id,
                'data' => json_encode($data),
                'collected_at' => $data['collected_at']
            ),
            array('%d', '%s', '%s')
        );
    }
    
    /**
     * Store Web Vitals data in database
     */
    private function store_web_vitals_data($site_id, $data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'rphub_analytics_web_vitals';
        
        $wpdb->replace(
            $table,
            array(
                'site_id' => $site_id,
                'data' => json_encode($data),
                'collected_at' => $data['collected_at']
            ),
            array('%d', '%s', '%s')
        );
    }
    
    /**
     * Store RUM data in database
     */
    private function store_rum_data($site_id, $data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'rphub_analytics_rum';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
        if (!$table_exists) {
            return false;
        }
        
        $wpdb->replace(
            $table,
            array(
                'site_id' => $site_id,
                'data' => json_encode($data),
                'collected_at' => $data['collected_at']
            ),
            array('%d', '%s', '%s')
        );
    }
    
    /**
     * Get analytics overview for dashboard
     */
    public function get_analytics_overview() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        try {
            global $wpdb;
            
            $overview = array(
                'total_sessions' => 0,
                'total_users' => 0,
                'total_pageviews' => 0,
                'total_clicks' => 0,
                'total_impressions' => 0,
                'avg_web_vitals' => array(),
                'top_performing_sites' => array(),
                'recent_data_points' => 0
            );
            
            // Aggregate GA4 data
            $ga4_data = $wpdb->get_results("
                SELECT site_id, data 
                FROM {$wpdb->prefix}rphub_analytics_ga4 
                WHERE collected_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            
            foreach ($ga4_data as $row) {
                $data = json_decode($row->data, true);
                $overview['total_sessions'] += $data['total_sessions'] ?? 0;
                $overview['total_users'] += $data['total_users'] ?? 0;
                $overview['total_pageviews'] += $data['total_pageviews'] ?? 0;
            }
            
            // Aggregate Search Console data
            $sc_data = $wpdb->get_results("
                SELECT site_id, data 
                FROM {$wpdb->prefix}rphub_analytics_search_console 
                WHERE collected_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            
            foreach ($sc_data as $row) {
                $data = json_decode($row->data, true);
                $overview['total_clicks'] += $data['total_clicks'] ?? 0;
                $overview['total_impressions'] += $data['total_impressions'] ?? 0;
            }
            
            // Get top performing sites
            $top_sites = $wpdb->get_results("
                SELECT 
                    s.name,
                    s.url,
                    ga4.data as ga4_data,
                    sc.data as sc_data
                FROM {$wpdb->prefix}rphub_sites s
                LEFT JOIN {$wpdb->prefix}rphub_analytics_ga4 ga4 ON s.id = ga4.site_id
                LEFT JOIN {$wpdb->prefix}rphub_analytics_search_console sc ON s.id = sc.site_id
                WHERE s.status = 'active'
                ORDER BY s.performance_score DESC
                LIMIT 5
            ");
            
            foreach ($top_sites as $site) {
                $ga4_data = json_decode($site->ga4_data, true);
                $sc_data = json_decode($site->sc_data, true);
                
                $overview['top_performing_sites'][] = array(
                    'name' => $site->name,
                    'url' => $site->url,
                    'sessions' => $ga4_data['total_sessions'] ?? 0,
                    'clicks' => $sc_data['total_clicks'] ?? 0
                );
            }
            
            $overview['recent_data_points'] = count($ga4_data) + count($sc_data);
            $overview['last_sync'] = get_option('rphub_last_analytics_sync', 'Nunca');
            
            wp_send_json_success($overview);
            
        } catch (Exception $e) {
            wp_send_json_error('Error obteniendo overview: ' . $e->getMessage());
        }
    }
    
    /**
     * Get analytics data for a specific site
     */
    public function get_site_analytics($site_id, $date_range = '30d') {
        global $wpdb;
        
        $analytics_data = array(
            'ga4' => null,
            'search_console' => null,
            'web_vitals' => null,
            'rum' => null
        );
        
        // Get GA4 data
        $ga4_row = $wpdb->get_row($wpdb->prepare(
            "SELECT data FROM {$wpdb->prefix}rphub_analytics_ga4 WHERE site_id = %d ORDER BY collected_at DESC LIMIT 1",
            $site_id
        ));
        if ($ga4_row) {
            $analytics_data['ga4'] = json_decode($ga4_row->data, true);
        }
        
        // Get Search Console data
        $sc_row = $wpdb->get_row($wpdb->prepare(
            "SELECT data FROM {$wpdb->prefix}rphub_analytics_search_console WHERE site_id = %d ORDER BY collected_at DESC LIMIT 1",
            $site_id
        ));
        if ($sc_row) {
            $analytics_data['search_console'] = json_decode($sc_row->data, true);
        }
        
        // Get Web Vitals data
        $vitals_row = $wpdb->get_row($wpdb->prepare(
            "SELECT data FROM {$wpdb->prefix}rphub_analytics_web_vitals WHERE site_id = %d ORDER BY collected_at DESC LIMIT 1",
            $site_id
        ));
        if ($vitals_row) {
            $analytics_data['web_vitals'] = json_decode($vitals_row->data, true);
        }
        
        // Get RUM data
        $rum_row = $wpdb->get_row($wpdb->prepare(
            "SELECT data FROM {$wpdb->prefix}rphub_analytics_rum WHERE site_id = %d ORDER BY collected_at DESC LIMIT 1",
            $site_id
        ));
        if ($rum_row) {
            $analytics_data['rum'] = json_decode($rum_row->data, true);
        }
        
        return $analytics_data;
    }
    
    /**
     * Make GA4 API request against the given property ID (defaults to global).
     * Detects HTTP 401 and retries once after refreshing the access token.
     */
    private function make_ga4_request($endpoint, $method = 'GET', $body = null, $property_id = '') {
        $property_id = $property_id ?: $this->ga4_property_id;
        $url = "https://analyticsdata.googleapis.com/v1beta/properties/{$property_id}/" . $endpoint;

        $response = $this->do_google_request($url, $method, $body);

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 401) {
            if ($this->refresh_access_token()) {
                $response = $this->do_google_request($url, $method, $body);
            }
        }

        return $response;
    }

    /**
     * Make Search Console API request.
     * Detects HTTP 401 and retries once after refreshing the access token.
     */
    private function make_search_console_request($endpoint, $method = 'GET', $body = null) {
        $url = "https://www.googleapis.com/webmasters/v3/" . $endpoint;

        $response = $this->do_google_request($url, $method, $body);

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 401) {
            if ($this->refresh_access_token()) {
                $response = $this->do_google_request($url, $method, $body);
            }
        }

        return $response;
    }

    /**
     * Shared authenticated HTTP request to Google APIs using the current access_token.
     */
    private function do_google_request($url, $method = 'GET', $body = null) {
        $args = array(
            'method'  => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type'  => 'application/json',
            ),
            'timeout' => 30,
        );

        if ($body && $method === 'POST') {
            $args['body'] = wp_json_encode($body);
        }

        return wp_remote_request($url, $args);
    }
    
    /**
     * Refresh access token using refresh token
     */
    private function refresh_access_token() {
        $client_id = get_option('replanta_hub_google_client_id');
        $client_secret = get_option('replanta_hub_google_client_secret');
        
        if (empty($client_id) || empty($client_secret) || empty($this->refresh_token)) {
            return false;
        }
        
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'refresh_token' => $this->refresh_token,
                'grant_type' => 'refresh_token'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['access_token'])) {
            $this->access_token = $data['access_token'];
            
            $settings = get_option('replanta_hub_analytics_settings', array());
            $settings['access_token'] = $this->access_token;
            update_option('replanta_hub_analytics_settings', $settings);
            
            return true;
        }
        
        return false;
    }
    /**
     * Sync analytics for a single site if data is older than 12 hours.
     * Used for on-demand refresh from the admin UI.
     */
    public function sync_site_on_demand($site_id) {
        global $wpdb;

        // Check age of existing data
        $last_sync = $wpdb->get_var($wpdb->prepare(
            "SELECT collected_at FROM {$wpdb->prefix}rphub_analytics_ga4 WHERE site_id = %d ORDER BY collected_at DESC LIMIT 1",
            $site_id
        ));

        if ($last_sync && strtotime($last_sync) > time() - 12 * HOUR_IN_SECONDS) {
            return; // Data is fresh enough
        }

        // Fetch site credentials
        $site = $wpdb->get_row($wpdb->prepare(
            "SELECT id, url, ga4_property_id, sc_domain FROM {$wpdb->prefix}rphub_sites WHERE id = %d AND status = 'active'",
            $site_id
        ));

        if (!$site) {
            return;
        }

        $ga4_id = !empty($site->ga4_property_id) ? $site->ga4_property_id : $this->ga4_property_id;
        $sc_dom = !empty($site->sc_domain)        ? $site->sc_domain        : $this->search_console_domain;

        $this->sync_site_analytics($site->id, $site->url, $ga4_id, $sc_dom);
    }
}

// Instantiated by replanta-hub.php — do not instantiate here.
