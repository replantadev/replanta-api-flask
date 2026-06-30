<?php
/**
 * PageSpeed Insights Integration for Replanta Hub
 * 
 * Provides detailed performance analysis using Google PageSpeed Insights API
 */

class ReplantaHub_PageSpeed_Integration {
    
    private $api_key;
    private $api_url = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
    
    public function __construct() {
        $this->api_key = RPHUB_Crypto::decrypt( get_option('rphub_pagespeed_api_key', '') );
        
        // If init already fired (class instantiated late), call directly
        if (did_action('init')) {
            $this->init();
        } else {
            add_action('init', [$this, 'init']);
        }
    }
    
    public function init() {
        // AJAX handlers
        add_action('wp_ajax_rphub_pagespeed_analyze', [$this, 'ajax_analyze_page']);
        add_action('wp_ajax_rphub_pagespeed_bulk_analyze', [$this, 'ajax_bulk_analyze']);
        add_action('wp_ajax_rphub_pagespeed_get_history', [$this, 'ajax_get_history']);
        add_action('wp_ajax_rphub_pagespeed_compare', [$this, 'ajax_compare_results']);
        
        // Scheduled tasks
        add_action('rphub_pagespeed_daily_analysis', [$this, 'scheduled_daily_analysis']);
        add_action('rphub_pagespeed_weekly_report', [$this, 'scheduled_weekly_report']);
        
        // Schedule events
        RPHUB_Scheduler::schedule('rphub_pagespeed_daily_analysis', 'daily');
        RPHUB_Scheduler::schedule('rphub_pagespeed_weekly_report',   'weekly');
    }
    
    /**
     * Check if PageSpeed integration is configured
     */
    public function is_configured() {
        return !empty($this->api_key);
    }
    
    /**
     * Alias for analyze_page for backwards compatibility
     */
    public function analyze_url($url, $strategy = 'mobile') {
        return $this->analyze_page($url, $strategy);
    }
    
    /**
     * Run scheduled analysis
     */
    public function run_analysis() {
        // Run analysis on the home page
        $home_url = home_url();
        return $this->analyze_page($home_url, 'mobile');
    }
    
    /**
     * Analyze page performance using PageSpeed Insights
     */
    public function analyze_page($url, $strategy = 'mobile', $categories = ['performance', 'accessibility', 'best-practices', 'seo']) {
        if (empty($this->api_key)) {
            return new WP_Error('missing_api_key', 'PageSpeed Insights API key not configured');
        }
        
        // Build the API URL correctly
        $params = [
            'url' => $url,
            'key' => $this->api_key,
            'strategy' => $strategy,
            'locale' => 'es'
        ];
        
        // Add categories properly
        $category_params = '';
        foreach ($categories as $category) {
            $category_params .= '&category=' . urlencode($category);
        }
        
        $api_url = $this->api_url . '?' . http_build_query($params) . $category_params;
        
        $response = wp_remote_get($api_url, [
            'timeout' => 60,
            'headers' => [
                'User-Agent' => 'Replanta Hub PageSpeed Analyzer'
            ]
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Log the request for debugging
        error_log("PageSpeed API Request: $api_url");
        error_log("PageSpeed API Response Code: $code");
        
        if ($code !== 200) {
            // Try to get more specific error message
            $error_data = json_decode($body, true);
            $error_message = 'PageSpeed API error: ' . $code;
            
            if ($error_data && isset($error_data['error'])) {
                $error_message = $error_data['error']['message'] ?? $error_message;
                error_log("PageSpeed API Error Details: " . json_encode($error_data['error']));
            }
            
            if ($code === 403) {
                $error_message = 'API Key inválida o sin permisos para PageSpeed Insights API';
            } elseif ($code === 400) {
                $error_message = 'Solicitud incorrecta: ' . $error_message;
            }
            
            return new WP_Error('api_error', $error_message);
        }
        
        $data = json_decode($body, true);
        
        if (!$data) {
            return new WP_Error('parse_error', 'Could not parse PageSpeed API response');
        }
        
        // Process and format the results
        $analysis = $this->process_pagespeed_data($data, $url, $strategy);
        
        // Store results in database
        $this->store_analysis_results($url, $strategy, $analysis);
        
        return $analysis;
    }
    
    /**
     * Process raw PageSpeed data into structured format
     */
    private function process_pagespeed_data($data, $url, $strategy) {
        $lighthouse_result = $data['lighthouseResult'] ?? [];
        $categories = $lighthouse_result['categories'] ?? [];
        $audits = $lighthouse_result['audits'] ?? [];
        
        // Core Web Vitals
        $core_web_vitals = $this->extract_core_web_vitals($audits);
        
        // Performance metrics
        $performance_metrics = $this->extract_performance_metrics($audits);
        
        // Category scores
        $scores = [];
        foreach ($categories as $category_id => $category) {
            $scores[$category_id] = [
                'score' => round($category['score'] * 100),
                'title' => $category['title'] ?? ucfirst($category_id),
                'description' => $category['description'] ?? ''
            ];
        }
        
        // Opportunities (improvements)
        $opportunities = $this->extract_opportunities($audits);
        
        // Diagnostics
        $diagnostics = $this->extract_diagnostics($audits);
        
        // Passed audits
        $passed_audits = $this->extract_passed_audits($audits);
        
        return [
            'url' => $url,
            'strategy' => $strategy,
            'analysis_date' => current_time('mysql'),
            'scores' => $scores,
            'core_web_vitals' => $core_web_vitals,
            'performance_metrics' => $performance_metrics,
            'opportunities' => $opportunities,
            'diagnostics' => $diagnostics,
            'passed_audits' => $passed_audits,
            'overall_grade' => $this->calculate_overall_grade($scores),
            'loading_experience' => $data['loadingExperience'] ?? null,
            'origin_loading_experience' => $data['originLoadingExperience'] ?? null
        ];
    }
    
    /**
     * Extract Core Web Vitals
     */
    private function extract_core_web_vitals($audits) {
        $core_vitals = [
            'largest-contentful-paint' => [
                'title' => 'Largest Contentful Paint',
                'acronym' => 'LCP',
                'description' => 'Tiempo hasta que se muestra el elemento más grande'
            ],
            'first-input-delay' => [
                'title' => 'First Input Delay',
                'acronym' => 'FID',
                'description' => 'Tiempo de respuesta a la primera interacción'
            ],
            'cumulative-layout-shift' => [
                'title' => 'Cumulative Layout Shift',
                'acronym' => 'CLS',
                'description' => 'Estabilidad visual de la página'
            ],
            'first-contentful-paint' => [
                'title' => 'First Contentful Paint',
                'acronym' => 'FCP',
                'description' => 'Tiempo hasta el primer contenido visible'
            ]
        ];
        
        $results = [];
        
        foreach ($core_vitals as $audit_id => $info) {
            if (isset($audits[$audit_id])) {
                $audit = $audits[$audit_id];
                $results[$audit_id] = [
                    'title' => $info['title'],
                    'acronym' => $info['acronym'],
                    'description' => $info['description'],
                    'score' => $audit['score'],
                    'numeric_value' => $audit['numericValue'] ?? 0,
                    'display_value' => $audit['displayValue'] ?? '',
                    'score_display_mode' => $audit['scoreDisplayMode'] ?? '',
                    'rating' => $this->get_metric_rating($audit_id, $audit['numericValue'] ?? 0)
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Extract performance metrics
     */
    private function extract_performance_metrics($audits) {
        $metrics = [
            'speed-index' => 'Speed Index',
            'total-blocking-time' => 'Total Blocking Time',
            'interactive' => 'Time to Interactive',
            'server-response-time' => 'Server Response Time',
            'render-blocking-resources' => 'Render Blocking Resources',
            'unused-css-rules' => 'Unused CSS',
            'unused-javascript' => 'Unused JavaScript',
            'modern-image-formats' => 'Modern Image Formats',
            'efficiently-encode-images' => 'Efficient Image Encoding',
            'offscreen-images' => 'Offscreen Images'
        ];
        
        $results = [];
        
        foreach ($metrics as $audit_id => $title) {
            if (isset($audits[$audit_id])) {
                $audit = $audits[$audit_id];
                $results[$audit_id] = [
                    'title' => $title,
                    'score' => $audit['score'],
                    'numeric_value' => $audit['numericValue'] ?? 0,
                    'display_value' => $audit['displayValue'] ?? '',
                    'description' => $audit['description'] ?? '',
                    'details' => $audit['details'] ?? null
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Extract optimization opportunities
     */
    private function extract_opportunities($audits) {
        $opportunity_audits = [
            'render-blocking-resources',
            'unused-css-rules',
            'unused-javascript',
            'modern-image-formats',
            'efficiently-encode-images',
            'offscreen-images',
            'unminified-css',
            'unminified-javascript',
            'optimize-images',
            'uses-text-compression',
            'uses-responsive-images',
            'uses-optimized-images',
            'uses-webp-images',
            'uses-rel-preconnect',
            'uses-rel-preload',
            'font-display',
            'third-party-summary'
        ];
        
        $opportunities = [];
        
        foreach ($opportunity_audits as $audit_id) {
            if (isset($audits[$audit_id]) && 
                $audits[$audit_id]['score'] < 1 && 
                isset($audits[$audit_id]['details'])) {
                
                $audit = $audits[$audit_id];
                $opportunities[] = [
                    'id' => $audit_id,
                    'title' => $audit['title'] ?? '',
                    'description' => $audit['description'] ?? '',
                    'score' => $audit['score'],
                    'display_value' => $audit['displayValue'] ?? '',
                    'potential_savings' => $this->extract_potential_savings($audit),
                    'details' => $audit['details'] ?? null,
                    'priority' => $this->get_opportunity_priority($audit)
                ];
            }
        }
        
        // Sort by potential savings (descending)
        usort($opportunities, function($a, $b) {
            return $b['potential_savings']['time'] <=> $a['potential_savings']['time'];
        });
        
        return $opportunities;
    }
    
    /**
     * Extract diagnostics
     */
    private function extract_diagnostics($audits) {
        $diagnostic_audits = [
            'mainthread-work-breakdown',
            'bootup-time',
            'uses-long-cache-ttl',
            'total-byte-weight',
            'dom-size',
            'critical-request-chains',
            'user-timings',
            'diagnostics'
        ];
        
        $diagnostics = [];
        
        foreach ($diagnostic_audits as $audit_id) {
            if (isset($audits[$audit_id])) {
                $audit = $audits[$audit_id];
                $diagnostics[] = [
                    'id' => $audit_id,
                    'title' => $audit['title'] ?? '',
                    'description' => $audit['description'] ?? '',
                    'score' => $audit['score'],
                    'display_value' => $audit['displayValue'] ?? '',
                    'details' => $audit['details'] ?? null
                ];
            }
        }
        
        return $diagnostics;
    }
    
    /**
     * Extract passed audits
     */
    private function extract_passed_audits($audits) {
        $passed = [];
        
        foreach ($audits as $audit_id => $audit) {
            if ($audit['score'] === 1) {
                $passed[] = [
                    'id' => $audit_id,
                    'title' => $audit['title'] ?? '',
                    'description' => $audit['description'] ?? ''
                ];
            }
        }
        
        return $passed;
    }
    
    /**
     * Get metric rating (good/needs-improvement/poor)
     */
    private function get_metric_rating($metric, $value) {
        $thresholds = [
            'largest-contentful-paint' => [2500, 4000],
            'first-input-delay' => [100, 300],
            'cumulative-layout-shift' => [0.1, 0.25],
            'first-contentful-paint' => [1800, 3000]
        ];
        
        if (!isset($thresholds[$metric])) {
            return 'unknown';
        }
        
        $good_threshold = $thresholds[$metric][0];
        $poor_threshold = $thresholds[$metric][1];
        
        if ($value <= $good_threshold) {
            return 'good';
        } elseif ($value <= $poor_threshold) {
            return 'needs-improvement';
        } else {
            return 'poor';
        }
    }
    
    /**
     * Extract potential savings from audit
     */
    private function extract_potential_savings($audit) {
        $savings = [
            'time' => 0,
            'bytes' => 0
        ];
        
        if (isset($audit['details']['overallSavingsMs'])) {
            $savings['time'] = $audit['details']['overallSavingsMs'];
        }
        
        if (isset($audit['details']['overallSavingsBytes'])) {
            $savings['bytes'] = $audit['details']['overallSavingsBytes'];
        }
        
        return $savings;
    }
    
    /**
     * Get opportunity priority
     */
    private function get_opportunity_priority($audit) {
        $potential_savings = $this->extract_potential_savings($audit);
        
        if ($potential_savings['time'] > 1000) { // > 1 second
            return 'high';
        } elseif ($potential_savings['time'] > 500) { // > 0.5 seconds
            return 'medium';
        } else {
            return 'low';
        }
    }
    
    /**
     * Calculate overall grade
     */
    private function calculate_overall_grade($scores) {
        if (empty($scores)) {
            return 'F';
        }
        
        $performance_score = $scores['performance']['score'] ?? 0;
        
        if ($performance_score >= 90) {
            return 'A';
        } elseif ($performance_score >= 80) {
            return 'B';
        } elseif ($performance_score >= 70) {
            return 'C';
        } elseif ($performance_score >= 60) {
            return 'D';
        } else {
            return 'F';
        }
    }
    
    /**
     * Bulk analyze multiple pages
     */
    public function bulk_analyze($urls, $strategy = 'mobile') {
        $results = [];
        
        foreach ($urls as $url) {
            $analysis = $this->analyze_page($url, $strategy);
            
            if (!is_wp_error($analysis)) {
                $results[] = $analysis;
            } else {
                $results[] = [
                    'url' => $url,
                    'error' => $analysis->get_error_message()
                ];
            }
            
            // Add delay between requests to respect API limits
            sleep(1);
        }
        
        return $results;
    }
    
    /**
     * Get analysis history for a URL
     */
    public function get_analysis_history($url, $strategy = 'mobile', $limit = 30) {
        global $wpdb;
        $table_reports = $wpdb->prefix . 'rphub_pagespeed_reports';
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table_reports 
            WHERE url = %s AND strategy = %s 
            ORDER BY created_at DESC 
            LIMIT %d
        ", $url, $strategy, $limit), ARRAY_A);
        
        $history = [];
        
        foreach ($results as $result) {
            $data = json_decode($result['analysis_data'], true);
            $history[] = [
                'id' => $result['id'],
                'analysis_date' => $result['created_at'],
                'performance_score' => $data['scores']['performance']['score'] ?? 0,
                'accessibility_score' => $data['scores']['accessibility']['score'] ?? 0,
                'best_practices_score' => $data['scores']['best-practices']['score'] ?? 0,
                'seo_score' => $data['scores']['seo']['score'] ?? 0,
                'core_web_vitals' => $data['core_web_vitals'] ?? [],
                'overall_grade' => $data['overall_grade'] ?? 'F'
            ];
        }
        
        return $history;
    }
    
    /**
     * Compare two analysis results
     */
    public function compare_analyses($url, $strategy, $date1, $date2) {
        global $wpdb;
        $table_reports = $wpdb->prefix . 'rphub_pagespeed_reports';
        
        $analysis1 = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table_reports 
            WHERE url = %s AND strategy = %s AND DATE(created_at) = %s 
            ORDER BY created_at DESC LIMIT 1
        ", $url, $strategy, $date1), ARRAY_A);
        
        $analysis2 = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table_reports 
            WHERE url = %s AND strategy = %s AND DATE(created_at) = %s 
            ORDER BY created_at DESC LIMIT 1
        ", $url, $strategy, $date2), ARRAY_A);
        
        if (!$analysis1 || !$analysis2) {
            return new WP_Error('missing_data', 'No se encontraron análisis para las fechas especificadas');
        }
        
        $data1 = json_decode($analysis1['analysis_data'], true);
        $data2 = json_decode($analysis2['analysis_data'], true);
        
        return $this->calculate_comparison($data1, $data2);
    }
    
    /**
     * Calculate comparison between two analyses
     */
    private function calculate_comparison($data1, $data2) {
        $comparison = [
            'scores_comparison' => [],
            'core_web_vitals_comparison' => [],
            'performance_metrics_comparison' => [],
            'overall_improvement' => 0
        ];
        
        // Compare scores
        foreach ($data1['scores'] as $category => $score1) {
            $score2 = $data2['scores'][$category] ?? ['score' => 0];
            $difference = $score2['score'] - $score1['score'];
            
            $comparison['scores_comparison'][$category] = [
                'before' => $score1['score'],
                'after' => $score2['score'],
                'difference' => $difference,
                'improvement' => $difference > 0
            ];
        }
        
        // Compare Core Web Vitals
        foreach ($data1['core_web_vitals'] as $metric => $value1) {
            $value2 = $data2['core_web_vitals'][$metric] ?? ['numeric_value' => 0];
            $difference = $value2['numeric_value'] - $value1['numeric_value'];
            
            $comparison['core_web_vitals_comparison'][$metric] = [
                'before' => $value1['numeric_value'],
                'after' => $value2['numeric_value'],
                'difference' => $difference,
                'improvement' => $difference < 0 // Lower is better for most metrics
            ];
        }
        
        // Calculate overall improvement
        $performance_improvement = $comparison['scores_comparison']['performance']['difference'] ?? 0;
        $comparison['overall_improvement'] = $performance_improvement;
        
        return $comparison;
    }
    
    /**
     * Generate performance summary for dashboard
     */
    public function get_performance_summary($url, $strategy = 'mobile') {
        $latest_analysis = $this->get_latest_analysis($url, $strategy);
        
        if (!$latest_analysis) {
            return [
                'status' => 'no_data',
                'message' => 'No hay datos de rendimiento disponibles'
            ];
        }
        
        $scores = $latest_analysis['scores'];
        $core_web_vitals = $latest_analysis['core_web_vitals'];
        
        $summary = [
            'status' => 'good',
            'overall_grade' => $latest_analysis['overall_grade'],
            'performance_score' => $scores['performance']['score'] ?? 0,
            'core_web_vitals_status' => $this->assess_core_web_vitals($core_web_vitals),
            'key_metrics' => [
                'lcp' => $core_web_vitals['largest-contentful-paint']['display_value'] ?? 'N/A',
                'fid' => $core_web_vitals['first-input-delay']['display_value'] ?? 'N/A',
                'cls' => $core_web_vitals['cumulative-layout-shift']['display_value'] ?? 'N/A'
            ],
            'top_opportunities' => array_slice($latest_analysis['opportunities'], 0, 3),
            'last_analyzed' => $latest_analysis['analysis_date']
        ];
        
        // Determine overall status
        if ($summary['performance_score'] < 50) {
            $summary['status'] = 'poor';
        } elseif ($summary['performance_score'] < 80) {
            $summary['status'] = 'needs_improvement';
        }
        
        return $summary;
    }
    
    /**
     * Assess Core Web Vitals status
     */
    private function assess_core_web_vitals($vitals) {
        $good_count = 0;
        $total_count = count($vitals);
        
        foreach ($vitals as $vital) {
            if ($vital['rating'] === 'good') {
                $good_count++;
            }
        }
        
        if ($good_count === $total_count) {
            return 'good';
        } elseif ($good_count >= $total_count / 2) {
            return 'needs_improvement';
        } else {
            return 'poor';
        }
    }
    
    /**
     * Get latest analysis for URL
     */
    private function get_latest_analysis($url, $strategy) {
        global $wpdb;
        $table_reports = $wpdb->prefix . 'rphub_pagespeed_reports';
        
        $result = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table_reports 
            WHERE url = %s AND strategy = %s 
            ORDER BY created_at DESC 
            LIMIT 1
        ", $url, $strategy), ARRAY_A);
        
        if (!$result) {
            return null;
        }
        
        return json_decode($result['analysis_data'], true);
    }
    
    /**
     * Store analysis results in database
     */
    private function store_analysis_results($url, $strategy, $analysis) {
        global $wpdb;
        $table_reports = $wpdb->prefix . 'rphub_pagespeed_reports';
        
        $wpdb->insert($table_reports, [
            'url' => $url,
            'strategy' => $strategy,
            'analysis_data' => json_encode($analysis),
            'performance_score' => $analysis['scores']['performance']['score'] ?? 0,
            'created_at' => current_time('mysql')
        ]);
    }
    
    /**
     * AJAX Handlers
     */
    public function ajax_analyze_page() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $url = $_POST['url'] ?? '';
        $strategy = $_POST['strategy'] ?? 'mobile';
        
        if (empty($url)) {
            wp_send_json_error('URL requerida');
        }
        
        $result = $this->analyze_page($url, $strategy);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_bulk_analyze() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $urls = $_POST['urls'] ?? [];
        $strategy = $_POST['strategy'] ?? 'mobile';
        
        if (empty($urls)) {
            wp_send_json_error('URLs requeridas');
        }
        
        $result = $this->bulk_analyze($urls, $strategy);
        wp_send_json_success($result);
    }
    
    public function ajax_get_history() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $url = $_POST['url'] ?? '';
        $strategy = $_POST['strategy'] ?? 'mobile';
        $limit = $_POST['limit'] ?? 30;
        
        if (empty($url)) {
            wp_send_json_error('URL requerida');
        }
        
        $result = $this->get_analysis_history($url, $strategy, $limit);
        wp_send_json_success($result);
    }
    
    public function ajax_compare_results() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $url = $_POST['url'] ?? '';
        $strategy = $_POST['strategy'] ?? 'mobile';
        $date1 = $_POST['date1'] ?? '';
        $date2 = $_POST['date2'] ?? '';
        
        if (empty($url) || empty($date1) || empty($date2)) {
            wp_send_json_error('Parámetros requeridos: URL y fechas');
        }
        
        $result = $this->compare_analyses($url, $strategy, $date1, $date2);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Scheduled Tasks
     */
    public function scheduled_daily_analysis() {
        global $wpdb;
        $table_sites = $wpdb->prefix . 'rphub_sites';
        
        // Get sites with performance monitoring enabled
        $sites = $wpdb->get_results("
            SELECT * FROM $table_sites 
            WHERE status = 'active' 
            AND performance_monitoring = 1
            LIMIT 10
        ", ARRAY_A);
        
        foreach ($sites as $site) {
            $this->analyze_page($site['url'], 'mobile');
            
            // Add delay between requests
            sleep(2);
        }
    }
    
    public function scheduled_weekly_report() {
        global $wpdb;
        $table_sites = $wpdb->prefix . 'rphub_sites';
        
        $sites = $wpdb->get_results("
            SELECT * FROM $table_sites 
            WHERE status = 'active' 
            AND performance_monitoring = 1
        ", ARRAY_A);
        
        foreach ($sites as $site) {
            $summary = $this->get_performance_summary($site['url']);
            
            // Create notification if performance is poor
            if ($summary['status'] === 'poor') {
                $this->create_performance_alert($site['id'], $summary);
            }
        }
    }
    
    /**
     * Create performance alert
     */
    private function create_performance_alert($site_id, $summary) {
        global $wpdb;
        $table_notifications = $wpdb->prefix . 'rphub_notifications';
        
        $message = sprintf(
            'Rendimiento deficiente detectado. Puntuación: %d/100',
            $summary['performance_score']
        );
        
        $wpdb->insert($table_notifications, [
            'site_id' => $site_id,
            'type' => 'performance',
            'severity' => 'warning',
            'title' => 'Alerta de Rendimiento',
            'message' => $message,
            'created_at' => current_time('mysql')
        ]);
    }
}

// Initialize PageSpeed integration
function rphub_pagespeed_init() {
    return new ReplantaHub_PageSpeed_Integration();
}
add_action('plugins_loaded', 'rphub_pagespeed_init');
