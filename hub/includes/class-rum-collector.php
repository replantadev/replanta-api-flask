<?php
/**
 * Real User Monitoring (RUM) for Replanta Hub Professional
 * 
 * Collects and processes real user performance metrics
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ReplantaHub_RUM_Collector {
    
    private $table_name;
    private $collection_enabled;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'rphub_rum_data';
        $this->collection_enabled = get_option('rphub_rum_enabled', true);
        
        $this->init_hooks();
    }
    
    private function init_hooks() {
        if ($this->collection_enabled) {
            add_action('wp_enqueue_scripts', array($this, 'enqueue_rum_scripts'));
            add_action('wp_footer', array($this, 'add_rum_collector'));
        }
        
        add_action('wp_ajax_rphub_collect_rum', array($this, 'collect_rum_data'));
        add_action('wp_ajax_nopriv_rphub_collect_rum', array($this, 'collect_rum_data'));
        add_action('wp_ajax_rphub_get_rum_overview', array($this, 'get_rum_overview'));
        add_action('init', array($this, 'schedule_rum_aggregation'));
        add_action('rphub_aggregate_rum_data', array($this, 'aggregate_rum_data'));
    }
    
    public function enqueue_rum_scripts() {
        // Only collect on sites we're monitoring
        if (!$this->should_collect_rum()) {
            return;
        }
        
        wp_enqueue_script(
            'web-vitals',
            'https://unpkg.com/web-vitals@3/dist/web-vitals.umd.js',
            array(),
            '3.0.0',
            true
        );
        
        wp_enqueue_script(
            'rphub-rum-collector',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/rum-collector.js',
            array('web-vitals'),
            '1.0.0',
            true
        );
        
        wp_localize_script('rphub-rum-collector', 'rphubRUM', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rphub_rum_nonce'),
            'siteId' => $this->get_current_site_id(),
            'userId' => get_current_user_id(),
            'pageType' => $this->get_page_type(),
            'deviceType' => $this->detect_device_type(),
            'connectionType' => $this->detect_connection_type()
        ));
    }
    
    public function add_rum_collector() {
        if (!$this->should_collect_rum()) {
            return;
        }
        
        ?>
        <script type="text/javascript">
        // Additional performance monitoring beyond Web Vitals
        if (typeof rphubRUM !== 'undefined') {
            // Collect page load timing
            window.addEventListener('load', function() {
                if (window.performance && window.performance.timing) {
                    const timing = window.performance.timing;
                    const navigation = window.performance.navigation;
                    
                    const loadTimes = {
                        dns: timing.domainLookupEnd - timing.domainLookupStart,
                        connect: timing.connectEnd - timing.connectStart,
                        request: timing.responseStart - timing.requestStart,
                        response: timing.responseEnd - timing.responseStart,
                        domLoad: timing.domContentLoadedEventEnd - timing.navigationStart,
                        pageLoad: timing.loadEventEnd - timing.navigationStart,
                        ttfb: timing.responseStart - timing.navigationStart,
                        navigationType: navigation.type
                    };
                    
                    // Send timing data
                    fetch(rphubRUM.ajaxUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'rphub_collect_rum',
                            nonce: rphubRUM.nonce,
                            type: 'timing',
                            data: JSON.stringify(loadTimes),
                            site_id: rphubRUM.siteId,
                            user_id: rphubRUM.userId,
                            page_type: rphubRUM.pageType,
                            device_type: rphubRUM.deviceType,
                            connection_type: rphubRUM.connectionType,
                            url: window.location.href,
                            referrer: document.referrer,
                            user_agent: navigator.userAgent,
                            timestamp: Date.now()
                        })
                    }).catch(function(error) {
                        console.debug('RUM collection error:', error);
                    });
                }
            });
            
            // Collect resource loading performance
            if (window.PerformanceObserver) {
                const resourceObserver = new PerformanceObserver(function(list) {
                    const resources = list.getEntries().map(function(entry) {
                        return {
                            name: entry.name,
                            type: entry.initiatorType,
                            duration: entry.duration,
                            size: entry.transferSize || entry.encodedBodySize,
                            cached: entry.transferSize === 0 && entry.decodedBodySize > 0
                        };
                    });
                    
                    if (resources.length > 0) {
                        fetch(rphubRUM.ajaxUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: new URLSearchParams({
                                action: 'rphub_collect_rum',
                                nonce: rphubRUM.nonce,
                                type: 'resources',
                                data: JSON.stringify(resources),
                                site_id: rphubRUM.siteId,
                                timestamp: Date.now()
                            })
                        }).catch(function(error) {
                            console.debug('RUM resource collection error:', error);
                        });
                    }
                });
                
                try {
                    resourceObserver.observe({entryTypes: ['resource']});
                } catch (e) {
                    console.debug('PerformanceObserver not supported for resources');
                }
            }
            
            // Collect JavaScript errors
            window.addEventListener('error', function(event) {
                const errorData = {
                    message: event.message,
                    filename: event.filename,
                    lineno: event.lineno,
                    colno: event.colno,
                    stack: event.error ? event.error.stack : null
                };
                
                fetch(rphubRUM.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'rphub_collect_rum',
                        nonce: rphubRUM.nonce,
                        type: 'error',
                        data: JSON.stringify(errorData),
                        site_id: rphubRUM.siteId,
                        url: window.location.href,
                        timestamp: Date.now()
                    })
                }).catch(function(error) {
                    console.debug('RUM error collection failed:', error);
                });
            });
            
            // Collect unhandled promise rejections
            window.addEventListener('unhandledrejection', function(event) {
                const errorData = {
                    type: 'unhandledrejection',
                    reason: event.reason,
                    promise: event.promise.toString()
                };
                
                fetch(rphubRUM.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'rphub_collect_rum',
                        nonce: rphubRUM.nonce,
                        type: 'promise_error',
                        data: JSON.stringify(errorData),
                        site_id: rphubRUM.siteId,
                        url: window.location.href,
                        timestamp: Date.now()
                    })
                }).catch(function(error) {
                    console.debug('RUM promise error collection failed:', error);
                });
            });
        }
        </script>
        <?php
    }
    
    public function collect_rum_data() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'rphub_rum_nonce')) {
            wp_send_json_error(['message' => 'Security check failed']); return;
        }
        
        $type = sanitize_text_field($_POST['type']);
        $data = json_decode(stripslashes($_POST['data']), true);
        $site_id = intval($_POST['site_id']);
        $timestamp = intval($_POST['timestamp']);
        
        // Validate and sanitize data
        if (empty($type) || empty($data) || empty($site_id)) {
            wp_send_json_error('Invalid data');
        }
        
        global $wpdb;
        
        $insert_data = array(
            'site_id' => $site_id,
            'type' => $type,
            'data' => json_encode($data),
            'url' => esc_url_raw($_POST['url'] ?? ''),
            'user_id' => intval($_POST['user_id'] ?? 0),
            'page_type' => sanitize_text_field($_POST['page_type'] ?? ''),
            'device_type' => sanitize_text_field($_POST['device_type'] ?? ''),
            'connection_type' => sanitize_text_field($_POST['connection_type'] ?? ''),
            'referrer' => esc_url_raw($_POST['referrer'] ?? ''),
            'user_agent' => sanitize_text_field($_POST['user_agent'] ?? ''),
            'collected_at' => gmdate('Y-m-d H:i:s', $timestamp / 1000)
        );
        
        $result = $wpdb->insert(
            $this->table_name,
            $insert_data,
            array('%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            wp_send_json_error('Database error');
        }
        
        wp_send_json_success('Data collected');
    }
    
    public function schedule_rum_aggregation() {
        RPHUB_Scheduler::schedule('rphub_aggregate_rum_data', 'daily');
    }
    
    public function aggregate_rum_data() {
        global $wpdb;
        
        // Check if RUM data table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") == $this->table_name;
        if (!$table_exists) {
            return;
        }
        
        $yesterday = gmdate('Y-m-d', strtotime('-1 day'));
        
        // Get all sites with RUM data from yesterday
        $sites = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT site_id 
            FROM {$this->table_name} 
            WHERE DATE(collected_at) = %s
        ", $yesterday));
        
        foreach ($sites as $site) {
            $this->aggregate_site_rum_data($site->site_id, $yesterday);
        }
        
        // Clean up old raw data (keep only last 7 days)
        $wpdb->query("
            DELETE FROM {$this->table_name} 
            WHERE collected_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
    }
    
    private function aggregate_site_rum_data($site_id, $date) {
        global $wpdb;
        
        // Aggregate timing data
        $timing_data = $wpdb->get_results($wpdb->prepare("
            SELECT data 
            FROM {$this->table_name} 
            WHERE site_id = %d 
            AND DATE(collected_at) = %s 
            AND type = 'timing'
        ", $site_id, $date));
        
        $aggregated_timing = $this->process_timing_aggregation($timing_data);
        
        // Aggregate Web Vitals data
        $vitals_data = $wpdb->get_results($wpdb->prepare("
            SELECT data 
            FROM {$this->table_name} 
            WHERE site_id = %d 
            AND DATE(collected_at) = %s 
            AND type IN ('CLS', 'FID', 'FCP', 'LCP', 'TTFB', 'INP')
        ", $site_id, $date));
        
        $aggregated_vitals = $this->process_vitals_aggregation($vitals_data);
        
        // Aggregate error data
        $error_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$this->table_name} 
            WHERE site_id = %d 
            AND DATE(collected_at) = %s 
            AND type IN ('error', 'promise_error')
        ", $site_id, $date));
        
        // Store aggregated data
        $aggregated_data = array(
            'timing' => $aggregated_timing,
            'vitals' => $aggregated_vitals,
            'error_count' => intval($error_count),
            'date' => $date
        );
        
        $wpdb->replace(
            $wpdb->prefix . 'rphub_analytics_rum',
            array(
                'site_id' => $site_id,
                'data' => json_encode($aggregated_data),
                'collected_at' => $date . ' 23:59:59'
            ),
            array('%d', '%s', '%s')
        );
    }
    
    private function process_timing_aggregation($timing_data) {
        if (empty($timing_data)) {
            return array();
        }
        
        $metrics = array(
            'dns' => array(),
            'connect' => array(),
            'request' => array(),
            'response' => array(),
            'domLoad' => array(),
            'pageLoad' => array(),
            'ttfb' => array()
        );
        
        foreach ($timing_data as $row) {
            $data = json_decode($row->data, true);
            
            foreach ($metrics as $metric => $values) {
                if (isset($data[$metric]) && is_numeric($data[$metric])) {
                    $metrics[$metric][] = floatval($data[$metric]);
                }
            }
        }
        
        $aggregated = array();
        
        foreach ($metrics as $metric => $values) {
            if (!empty($values)) {
                sort($values);
                $count = count($values);
                
                $aggregated[$metric] = array(
                    'avg' => array_sum($values) / $count,
                    'median' => $this->calculate_median($values),
                    'p75' => $this->calculate_percentile($values, 75),
                    'p90' => $this->calculate_percentile($values, 90),
                    'p95' => $this->calculate_percentile($values, 95),
                    'min' => min($values),
                    'max' => max($values),
                    'count' => $count
                );
            }
        }
        
        return $aggregated;
    }
    
    private function process_vitals_aggregation($vitals_data) {
        if (empty($vitals_data)) {
            return array();
        }
        
        $vitals = array(
            'CLS' => array(),
            'FID' => array(),
            'FCP' => array(),
            'LCP' => array(),
            'TTFB' => array(),
            'INP' => array()
        );
        
        foreach ($vitals_data as $row) {
            $data = json_decode($row->data, true);
            
            if (isset($data['name']) && isset($data['value'])) {
                $metric = $data['name'];
                $value = floatval($data['value']);
                
                if (isset($vitals[$metric])) {
                    $vitals[$metric][] = $value;
                }
            }
        }
        
        $aggregated = array();
        
        foreach ($vitals as $metric => $values) {
            if (!empty($values)) {
                sort($values);
                $count = count($values);
                
                $aggregated[$metric] = array(
                    'avg' => array_sum($values) / $count,
                    'median' => $this->calculate_median($values),
                    'p75' => $this->calculate_percentile($values, 75),
                    'p90' => $this->calculate_percentile($values, 90),
                    'p95' => $this->calculate_percentile($values, 95),
                    'count' => $count,
                    'good' => $this->calculate_vitals_rating($metric, $values, 'good'),
                    'needs_improvement' => $this->calculate_vitals_rating($metric, $values, 'needs_improvement'),
                    'poor' => $this->calculate_vitals_rating($metric, $values, 'poor')
                );
            }
        }
        
        return $aggregated;
    }
    
    private function calculate_median($values) {
        $count = count($values);
        $middle = floor($count / 2);
        
        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        } else {
            return $values[$middle];
        }
    }
    
    private function calculate_percentile($values, $percentile) {
        $count = count($values);
        $index = ($percentile / 100) * ($count - 1);
        
        if (floor($index) === $index) {
            return $values[$index];
        } else {
            $lower = $values[floor($index)];
            $upper = $values[ceil($index)];
            return $lower + (($upper - $lower) * ($index - floor($index)));
        }
    }
    
    private function calculate_vitals_rating($metric, $values, $rating) {
        $thresholds = array(
            'CLS' => array('good' => 0.1, 'poor' => 0.25),
            'FID' => array('good' => 100, 'poor' => 300),
            'FCP' => array('good' => 1800, 'poor' => 3000),
            'LCP' => array('good' => 2500, 'poor' => 4000),
            'TTFB' => array('good' => 800, 'poor' => 1800),
            'INP' => array('good' => 200, 'poor' => 500)
        );
        
        if (!isset($thresholds[$metric])) {
            return 0;
        }
        
        $good_threshold = $thresholds[$metric]['good'];
        $poor_threshold = $thresholds[$metric]['poor'];
        
        $count = 0;
        $total = count($values);
        
        foreach ($values as $value) {
            switch ($rating) {
                case 'good':
                    if ($value <= $good_threshold) {
                        $count++;
                    }
                    break;
                case 'needs_improvement':
                    if ($value > $good_threshold && $value <= $poor_threshold) {
                        $count++;
                    }
                    break;
                case 'poor':
                    if ($value > $poor_threshold) {
                        $count++;
                    }
                    break;
            }
        }
        
        return $total > 0 ? ($count / $total) * 100 : 0;
    }
    
    public function get_rum_overview() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        try {
            global $wpdb;
            
            $overview = array(
                'total_sessions' => 0,
                'avg_page_load_time' => 0,
                'avg_web_vitals_score' => 0,
                'error_rate' => 0,
                'data_points_today' => 0,
                'top_slowest_pages' => array(),
                'device_breakdown' => array(),
                'recent_errors' => array()
            );
            
            // Get data points from today
            $overview['data_points_today'] = $wpdb->get_var("
                SELECT COUNT(*) 
                FROM {$this->table_name} 
                WHERE DATE(collected_at) = CURDATE()
            ");
            
            // Get recent aggregated data
            $recent_data = $wpdb->get_results("
                SELECT site_id, data 
                FROM {$wpdb->prefix}rphub_analytics_rum 
                WHERE collected_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            
            $total_load_time = 0;
            $load_time_count = 0;
            
            foreach ($recent_data as $row) {
                $data = json_decode($row->data, true);
                
                if (isset($data['timing']['pageLoad']['avg'])) {
                    $total_load_time += $data['timing']['pageLoad']['avg'];
                    $load_time_count++;
                }
            }
            
            if ($load_time_count > 0) {
                $overview['avg_page_load_time'] = $total_load_time / $load_time_count;
            }
            
            // Get slowest pages from today
            $slow_pages = $wpdb->get_results("
                SELECT url, AVG(JSON_EXTRACT(data, '$.pageLoad')) as avg_load_time
                FROM {$this->table_name} 
                WHERE DATE(collected_at) = CURDATE() 
                AND type = 'timing'
                AND JSON_EXTRACT(data, '$.pageLoad') IS NOT NULL
                GROUP BY url 
                ORDER BY avg_load_time DESC 
                LIMIT 10
            ");
            
            foreach ($slow_pages as $page) {
                $overview['top_slowest_pages'][] = array(
                    'url' => $page->url,
                    'load_time' => round($page->avg_load_time, 2)
                );
            }
            
            // Get device breakdown from today
            $device_data = $wpdb->get_results("
                SELECT device_type, COUNT(*) as count
                FROM {$this->table_name} 
                WHERE DATE(collected_at) = CURDATE()
                AND device_type IS NOT NULL
                GROUP BY device_type
            ");
            
            foreach ($device_data as $device) {
                $overview['device_breakdown'][$device->device_type] = intval($device->count);
            }
            
            // Get recent errors
            $recent_errors = $wpdb->get_results("
                SELECT url, data, collected_at
                FROM {$this->table_name} 
                WHERE type IN ('error', 'promise_error')
                AND collected_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY collected_at DESC 
                LIMIT 20
            ");
            
            foreach ($recent_errors as $error) {
                $error_data = json_decode($error->data, true);
                $overview['recent_errors'][] = array(
                    'url' => $error->url,
                    'message' => $error_data['message'] ?? 'Unknown error',
                    'time' => $error->collected_at
                );
            }
            
            $overview['error_rate'] = count($overview['recent_errors']);
            $overview['collection_status'] = $this->collection_enabled ? 'active' : 'disabled';
            
            wp_send_json_success($overview);
            
        } catch (Exception $e) {
            wp_send_json_error('Error obteniendo overview RUM: ' . $e->getMessage());
        }
    }
    
    private function should_collect_rum() {
        // Only collect RUM data for sites we're monitoring
        $current_site_id = $this->get_current_site_id();
        return !empty($current_site_id) && $this->collection_enabled;
    }
    
    private function get_current_site_id() {
        global $wpdb;
        
        $current_url = home_url();
        $site = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}rphub_sites WHERE url = %s AND status = 'active'",
            $current_url
        ));
        
        return $site ? $site->id : null;
    }
    
    private function get_page_type() {
        if (is_front_page()) {
            return 'homepage';
        } elseif (is_single()) {
            return 'post';
        } elseif (is_page()) {
            return 'page';
        } elseif (is_category()) {
            return 'category';
        } elseif (is_tag()) {
            return 'tag';
        } elseif (is_archive()) {
            return 'archive';
        } elseif (is_search()) {
            return 'search';
        } else {
            return 'other';
        }
    }
    
    private function detect_device_type() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (preg_match('/Mobile|Android|iPhone|iPad/', $user_agent)) {
            if (preg_match('/iPad/', $user_agent)) {
                return 'tablet';
            }
            return 'mobile';
        }
        
        return 'desktop';
    }
    
    private function detect_connection_type() {
        // Try to detect connection type from headers
        $connection_header = $_SERVER['HTTP_DOWNLINK'] ?? $_SERVER['HTTP_CONNECTION'] ?? '';
        
        if (!empty($connection_header)) {
            return sanitize_text_field($connection_header);
        }
        
        return 'unknown';
    }
    
    /**
     * Get RUM data for a specific site
     */
    public function get_site_rum_data($site_id, $date_range = '7d') {
        global $wpdb;
        
        $days = intval(str_replace('d', '', $date_range));
        
        $rum_data = $wpdb->get_results($wpdb->prepare("
            SELECT data, collected_at 
            FROM {$wpdb->prefix}rphub_analytics_rum 
            WHERE site_id = %d 
            AND collected_at > DATE_SUB(NOW(), INTERVAL %d DAY)
            ORDER BY collected_at DESC
        ", $site_id, $days));
        
        $processed_data = array(
            'daily_metrics' => array(),
            'avg_load_time' => 0,
            'avg_web_vitals' => array(),
            'error_trends' => array(),
            'device_performance' => array()
        );
        
        foreach ($rum_data as $row) {
            $data = json_decode($row->data, true);
            $date = substr($row->collected_at, 0, 10);
            
            $processed_data['daily_metrics'][$date] = $data;
        }
        
        return $processed_data;
    }
}

// Initialize RUM collector
new ReplantaHub_RUM_Collector();
