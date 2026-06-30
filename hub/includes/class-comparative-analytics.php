<?php
/**
 * Analytics Comparative Analysis System for Replanta Hub Professional
 * 
 * Implements intelligent benchmarking, comparative analysis, and automated alerts
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ReplantaHub_Comparative_Analytics {
    
    private $benchmark_thresholds;
    private $alert_rules;
    
    public function __construct() {
        $this->init_hooks();
        $this->load_benchmark_thresholds();
        $this->load_alert_rules();
    }
    
    private function init_hooks() {
        add_action('rphub_daily_comparative_analysis', array($this, 'run_daily_analysis'));
        add_action('rphub_hourly_benchmark_check', array($this, 'check_benchmarks'));
        add_action('wp_ajax_rphub_get_comparative_analysis', array($this, 'get_comparative_analysis'));
        add_action('wp_ajax_rphub_update_benchmarks', array($this, 'update_benchmarks'));
        add_action('wp_ajax_rphub_create_alert_rule', array($this, 'create_alert_rule'));
        add_action('wp_ajax_rphub_get_performance_trends', array($this, 'get_performance_trends'));
        add_action('wp_ajax_rphub_generate_competitive_report', array($this, 'generate_competitive_report'));
        
        // Schedule analysis tasks
        RPHUB_Scheduler::schedule('rphub_daily_comparative_analysis', 'daily');
        RPHUB_Scheduler::schedule('rphub_hourly_benchmark_check',     'hourly');
    }
    
    private function load_benchmark_thresholds() {
        $this->benchmark_thresholds = array(
            'performance' => array(
                'lcp' => array('excellent' => 2000, 'good' => 2500, 'poor' => 4000),
                'fid' => array('excellent' => 50, 'good' => 100, 'poor' => 300),
                'cls' => array('excellent' => 0.05, 'good' => 0.1, 'poor' => 0.25),
                'fcp' => array('excellent' => 1200, 'good' => 1800, 'poor' => 3000),
                'ttfb' => array('excellent' => 200, 'good' => 600, 'poor' => 1000),
                'page_load_time' => array('excellent' => 2000, 'good' => 3000, 'poor' => 5000)
            ),
            'seo' => array(
                'organic_traffic' => array('excellent' => 10000, 'good' => 5000, 'poor' => 1000),
                'click_through_rate' => array('excellent' => 5.0, 'good' => 2.0, 'poor' => 0.5),
                'average_position' => array('excellent' => 5.0, 'good' => 10.0, 'poor' => 20.0),
                'indexed_pages' => array('excellent' => 1000, 'good' => 500, 'poor' => 100)
            ),
            'user_experience' => array(
                'bounce_rate' => array('excellent' => 25, 'good' => 40, 'poor' => 70),
                'session_duration' => array('excellent' => 300, 'good' => 180, 'poor' => 60),
                'pages_per_session' => array('excellent' => 4.0, 'good' => 2.5, 'poor' => 1.5),
                'conversion_rate' => array('excellent' => 5.0, 'good' => 2.0, 'poor' => 0.5)
            ),
            'technical' => array(
                'uptime' => array('excellent' => 99.9, 'good' => 99.5, 'poor' => 99.0),
                'error_rate' => array('excellent' => 0.1, 'good' => 1.0, 'poor' => 5.0),
                'security_score' => array('excellent' => 95, 'good' => 85, 'poor' => 70),
                'pagespeed_score' => array('excellent' => 90, 'good' => 75, 'poor' => 50)
            )
        );
    }
    
    private function load_alert_rules() {
        $this->alert_rules = get_option('rphub_alert_rules', array(
            'performance_degradation' => array(
                'enabled' => true,
                'threshold' => 20, // 20% degradation
                'timeframe' => '24h',
                'severity' => 'high'
            ),
            'traffic_drop' => array(
                'enabled' => true,
                'threshold' => 30, // 30% traffic drop
                'timeframe' => '7d',
                'severity' => 'medium'
            ),
            'security_threat' => array(
                'enabled' => true,
                'threshold' => 1, // Any security issue
                'timeframe' => '1h',
                'severity' => 'critical'
            ),
            'uptime_issue' => array(
                'enabled' => true,
                'threshold' => 99.0, // Below 99% uptime
                'timeframe' => '24h',
                'severity' => 'high'
            ),
            'benchmark_deviation' => array(
                'enabled' => true,
                'threshold' => 15, // 15% below benchmark
                'timeframe' => '7d',
                'severity' => 'medium'
            )
        ));
    }
    
    /**
     * Run daily comparative analysis for all sites
     */
    public function run_daily_analysis() {
        global $wpdb;
        
        error_log('RPHUB: Starting daily comparative analysis');
        
        // Check if sites table exists with required columns
        $table_name = $wpdb->prefix . 'rphub_sites';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        
        if (!$table_exists) {
            error_log('RPHUB: Sites table does not exist, skipping analysis');
            return;
        }
        
        // Check if plan_type column exists
        $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
        $has_plan_type = in_array('plan_type', $columns);
        
        // Get all active sites
        $select_fields = $has_plan_type ? 
            "id, name, url, plan_type, industry, region" : 
            "id, name, url, 'standard' as plan_type, '' as industry, '' as region";
            
        $sites = $wpdb->get_results("
            SELECT $select_fields 
            FROM {$wpdb->prefix}rphub_sites 
            WHERE status = 'active'
        ");
        
        if (empty($sites)) {
            error_log('RPHUB: No active sites found for analysis');
            return;
        }
        
        // Group sites by plan type and industry for fair comparison
        $site_groups = array();
        foreach ($sites as $site) {
            $group_key = $site->plan_type . '_' . ($site->industry ?: 'general');
            if (!isset($site_groups[$group_key])) {
                $site_groups[$group_key] = array();
            }
            $site_groups[$group_key][] = $site;
        }
        
        // Analyze each group
        foreach ($site_groups as $group_key => $group_sites) {
            $this->analyze_site_group($group_sites, $group_key);
        }
        
        error_log('RPHUB: Daily comparative analysis completed');
    }
    
    /**
     * Analyze a group of similar sites
     */
    private function analyze_site_group($sites, $group_key) {
        global $wpdb;
        
        $site_ids = array_column($sites, 'id');
        $site_ids_str = implode(',', array_map('intval', $site_ids));
        
        // Get latest analytics data for all sites in group
        $analytics_data = $this->get_group_analytics_data($site_ids);
        
        if (empty($analytics_data)) {
            return;
        }
        
        // Calculate group statistics
        $group_stats = $this->calculate_group_statistics($analytics_data);
        
        // Rank sites within group
        $site_rankings = $this->rank_sites_in_group($analytics_data, $group_stats);
        
        // Identify top performers and underperformers
        $performance_analysis = $this->analyze_group_performance($site_rankings, $group_stats);
        
        // Store comparative analysis results
        $this->store_comparative_analysis($group_key, $performance_analysis, $group_stats);
    }
    
    /**
     * Get analytics data for a group of sites
     */
    private function get_group_analytics_data($site_ids) {
        global $wpdb;
        
        $analytics_data = array();
        
        foreach ($site_ids as $site_id) {
            $site_data = array();
            
            // Get GA4 data
            $ga4_data = $wpdb->get_row($wpdb->prepare("
                SELECT data FROM {$wpdb->prefix}rphub_analytics_ga4 
                WHERE site_id = %d 
                ORDER BY collected_at DESC 
                LIMIT 1
            ", $site_id));
            
            if ($ga4_data) {
                $site_data['ga4'] = json_decode($ga4_data->data, true);
            }
            
            // Get Search Console data
            $sc_data = $wpdb->get_row($wpdb->prepare("
                SELECT data FROM {$wpdb->prefix}rphub_analytics_search_console 
                WHERE site_id = %d 
                ORDER BY collected_at DESC 
                LIMIT 1
            ", $site_id));
            
            if ($sc_data) {
                $site_data['search_console'] = json_decode($sc_data->data, true);
            }
            
            // Get Web Vitals data
            $vitals_data = $wpdb->get_row($wpdb->prepare("
                SELECT data FROM {$wpdb->prefix}rphub_analytics_web_vitals 
                WHERE site_id = %d 
                ORDER BY collected_at DESC 
                LIMIT 1
            ", $site_id));
            
            if ($vitals_data) {
                $site_data['web_vitals'] = json_decode($vitals_data->data, true);
            }
            
            // Get RUM data
            $rum_data = $wpdb->get_row($wpdb->prepare("
                SELECT data FROM {$wpdb->prefix}rphub_analytics_rum 
                WHERE site_id = %d 
                ORDER BY collected_at DESC 
                LIMIT 1
            ", $site_id));
            
            if ($rum_data) {
                $site_data['rum'] = json_decode($rum_data->data, true);
            }
            
            if (!empty($site_data)) {
                $analytics_data[$site_id] = $site_data;
            }
        }
        
        return $analytics_data;
    }
    
    /**
     * Calculate statistics for the group
     */
    private function calculate_group_statistics($analytics_data) {
        $metrics = array();
        
        foreach ($analytics_data as $site_id => $site_data) {
            // GA4 metrics
            if (isset($site_data['ga4'])) {
                $ga4 = $site_data['ga4'];
                $metrics['sessions'][] = $ga4['total_sessions'] ?? 0;
                $metrics['users'][] = $ga4['total_users'] ?? 0;
                $metrics['pageviews'][] = $ga4['total_pageviews'] ?? 0;
                $metrics['bounce_rate'][] = $ga4['avg_bounce_rate'] ?? 0;
                $metrics['session_duration'][] = $ga4['avg_session_duration'] ?? 0;
            }
            
            // Search Console metrics
            if (isset($site_data['search_console'])) {
                $sc = $site_data['search_console'];
                $metrics['clicks'][] = $sc['total_clicks'] ?? 0;
                $metrics['impressions'][] = $sc['total_impressions'] ?? 0;
                $metrics['ctr'][] = $sc['avg_ctr'] ?? 0;
                $metrics['avg_position'][] = $sc['avg_position'] ?? 0;
            }
            
            // Web Vitals metrics
            if (isset($site_data['web_vitals'])) {
                $vitals = $site_data['web_vitals'];
                foreach ($vitals as $metric => $data) {
                    if (isset($data['p75'])) {
                        $metrics['vitals_' . $metric][] = $data['p75'];
                    }
                }
            }
            
            // RUM metrics
            if (isset($site_data['rum']['timing'])) {
                $timing = $site_data['rum']['timing'];
                foreach ($timing as $metric => $data) {
                    if (isset($data['avg'])) {
                        $metrics['rum_' . $metric][] = $data['avg'];
                    }
                }
            }
        }
        
        // Calculate percentiles and statistics for each metric
        $group_stats = array();
        foreach ($metrics as $metric => $values) {
            if (empty($values)) continue;
            
            sort($values);
            $count = count($values);
            
            $group_stats[$metric] = array(
                'min' => min($values),
                'max' => max($values),
                'avg' => array_sum($values) / $count,
                'median' => $this->calculate_percentile($values, 50),
                'p25' => $this->calculate_percentile($values, 25),
                'p75' => $this->calculate_percentile($values, 75),
                'p90' => $this->calculate_percentile($values, 90),
                'count' => $count
            );
        }
        
        return $group_stats;
    }
    
    /**
     * Rank sites within their group
     */
    private function rank_sites_in_group($analytics_data, $group_stats) {
        $site_scores = array();
        
        foreach ($analytics_data as $site_id => $site_data) {
            $scores = array();
            
            // Performance score (Web Vitals)
            $performance_score = $this->calculate_performance_score($site_data);
            $scores['performance'] = $performance_score;
            
            // SEO score (Search Console)
            $seo_score = $this->calculate_seo_score($site_data);
            $scores['seo'] = $seo_score;
            
            // User Experience score (GA4)
            $ux_score = $this->calculate_ux_score($site_data);
            $scores['ux'] = $ux_score;
            
            // Technical score (RUM + uptime)
            $technical_score = $this->calculate_technical_score($site_data);
            $scores['technical'] = $technical_score;
            
            // Overall composite score
            $overall_score = ($performance_score * 0.3) + ($seo_score * 0.3) + 
                           ($ux_score * 0.25) + ($technical_score * 0.15);
            
            $site_scores[$site_id] = array(
                'scores' => $scores,
                'overall' => $overall_score,
                'data' => $site_data
            );
        }
        
        // Sort by overall score
        uasort($site_scores, function($a, $b) {
            return $b['overall'] <=> $a['overall'];
        });
        
        return $site_scores;
    }
    
    /**
     * Calculate performance score based on Web Vitals
     */
    private function calculate_performance_score($site_data) {
        if (!isset($site_data['web_vitals'])) {
            return 50; // Default neutral score
        }
        
        $vitals = $site_data['web_vitals'];
        $scores = array();
        
        // LCP Score
        if (isset($vitals['largest_contentful_paint']['p75'])) {
            $lcp = $vitals['largest_contentful_paint']['p75'];
            if ($lcp <= 2500) $scores[] = 100;
            elseif ($lcp <= 4000) $scores[] = 75;
            else $scores[] = 25;
        }
        
        // FID Score
        if (isset($vitals['first_input_delay']['p75'])) {
            $fid = $vitals['first_input_delay']['p75'];
            if ($fid <= 100) $scores[] = 100;
            elseif ($fid <= 300) $scores[] = 75;
            else $scores[] = 25;
        }
        
        // CLS Score
        if (isset($vitals['cumulative_layout_shift']['p75'])) {
            $cls = $vitals['cumulative_layout_shift']['p75'];
            if ($cls <= 0.1) $scores[] = 100;
            elseif ($cls <= 0.25) $scores[] = 75;
            else $scores[] = 25;
        }
        
        return empty($scores) ? 50 : array_sum($scores) / count($scores);
    }
    
    /**
     * Calculate SEO score based on Search Console data
     */
    private function calculate_seo_score($site_data) {
        if (!isset($site_data['search_console'])) {
            return 50;
        }
        
        $sc = $site_data['search_console'];
        $scores = array();
        
        // CTR Score
        $ctr = $sc['avg_ctr'] ?? 0;
        if ($ctr >= 5) $scores[] = 100;
        elseif ($ctr >= 2) $scores[] = 75;
        elseif ($ctr >= 1) $scores[] = 50;
        else $scores[] = 25;
        
        // Impressions Score (relative)
        $impressions = $sc['total_impressions'] ?? 0;
        if ($impressions >= 10000) $scores[] = 100;
        elseif ($impressions >= 5000) $scores[] = 75;
        elseif ($impressions >= 1000) $scores[] = 50;
        else $scores[] = 25;
        
        // Average Position Score
        $position = $sc['avg_position'] ?? 50;
        if ($position <= 5) $scores[] = 100;
        elseif ($position <= 10) $scores[] = 75;
        elseif ($position <= 20) $scores[] = 50;
        else $scores[] = 25;
        
        return empty($scores) ? 50 : array_sum($scores) / count($scores);
    }
    
    /**
     * Calculate User Experience score based on GA4 data
     */
    private function calculate_ux_score($site_data) {
        if (!isset($site_data['ga4'])) {
            return 50;
        }
        
        $ga4 = $site_data['ga4'];
        $scores = array();
        
        // Bounce Rate Score (lower is better)
        $bounce_rate = $ga4['avg_bounce_rate'] ?? 50;
        if ($bounce_rate <= 25) $scores[] = 100;
        elseif ($bounce_rate <= 40) $scores[] = 75;
        elseif ($bounce_rate <= 60) $scores[] = 50;
        else $scores[] = 25;
        
        // Session Duration Score
        $duration = $ga4['avg_session_duration'] ?? 60;
        if ($duration >= 300) $scores[] = 100;
        elseif ($duration >= 180) $scores[] = 75;
        elseif ($duration >= 120) $scores[] = 50;
        else $scores[] = 25;
        
        // Engagement Rate Score
        $engagement = $ga4['avg_engagement_rate'] ?? 50;
        if ($engagement >= 80) $scores[] = 100;
        elseif ($engagement >= 60) $scores[] = 75;
        elseif ($engagement >= 40) $scores[] = 50;
        else $scores[] = 25;
        
        return empty($scores) ? 50 : array_sum($scores) / count($scores);
    }
    
    /**
     * Calculate Technical score based on RUM and system data
     */
    private function calculate_technical_score($site_data) {
        $scores = array();
        
        // RUM Performance
        if (isset($site_data['rum']['timing']['pageLoad']['avg'])) {
            $load_time = $site_data['rum']['timing']['pageLoad']['avg'];
            if ($load_time <= 2000) $scores[] = 100;
            elseif ($load_time <= 3000) $scores[] = 75;
            elseif ($load_time <= 5000) $scores[] = 50;
            else $scores[] = 25;
        }
        
        // Error Rate
        if (isset($site_data['rum']['error_count'])) {
            $errors = $site_data['rum']['error_count'];
            if ($errors == 0) $scores[] = 100;
            elseif ($errors <= 5) $scores[] = 75;
            elseif ($errors <= 15) $scores[] = 50;
            else $scores[] = 25;
        }
        
        // Default technical score if no data
        return empty($scores) ? 75 : array_sum($scores) / count($scores);
    }
    
    /**
     * Analyze group performance and identify insights
     */
    private function analyze_group_performance($site_rankings, $group_stats) {
        $top_performers = array_slice($site_rankings, 0, 3, true);
        $bottom_performers = array_slice($site_rankings, -3, 3, true);
        
        $analysis = array(
            'top_performers' => $top_performers,
            'bottom_performers' => $bottom_performers,
            'group_stats' => $group_stats,
            'insights' => array(),
            'recommendations' => array()
        );
        
        // Generate insights
        $analysis['insights'] = $this->generate_performance_insights_for_group($site_rankings, $group_stats);
        
        // Generate recommendations
        $analysis['recommendations'] = $this->generate_recommendations_for_group($site_rankings, $group_stats);
        
        return $analysis;
    }
    
    /**
     * Generate performance insights for a group
     */
    private function generate_performance_insights_for_group($site_rankings, $group_stats) {
        $insights = array();
        
        // Performance variation analysis
        if (isset($group_stats['vitals_largest_contentful_paint'])) {
            $lcp_stats = $group_stats['vitals_largest_contentful_paint'];
            $variation = ($lcp_stats['max'] - $lcp_stats['min']) / $lcp_stats['avg'] * 100;
            
            if ($variation > 50) {
                $insights[] = array(
                    'type' => 'performance_variation',
                    'title' => 'Alta variación en rendimiento LCP',
                    'description' => sprintf(
                        'Hay una variación del %.1f%% en LCP entre sitios del grupo. Los mejores sitios cargan %.0fms más rápido.',
                        $variation,
                        $lcp_stats['max'] - $lcp_stats['min']
                    ),
                    'severity' => 'medium'
                );
            }
        }
        
        // SEO performance gaps
        if (isset($group_stats['ctr'])) {
            $ctr_stats = $group_stats['ctr'];
            if ($ctr_stats['max'] > $ctr_stats['avg'] * 2) {
                $insights[] = array(
                    'type' => 'seo_opportunity',
                    'title' => 'Oportunidad de mejora en CTR',
                    'description' => sprintf(
                        'Los mejores sitios tienen un CTR del %.2f%% vs promedio de %.2f%%. Optimizar títulos y descripciones.',
                        $ctr_stats['max'],
                        $ctr_stats['avg']
                    ),
                    'severity' => 'medium'
                );
            }
        }
        
        // User engagement insights
        if (isset($group_stats['session_duration'])) {
            $duration_stats = $group_stats['session_duration'];
            if ($duration_stats['p75'] < 120) {
                $insights[] = array(
                    'type' => 'engagement_low',
                    'title' => 'Duración de sesión baja en el grupo',
                    'description' => sprintf(
                        'El 75%% de los sitios tienen sesiones menores a %.0f segundos. Revisar contenido y navegación.',
                        $duration_stats['p75']
                    ),
                    'severity' => 'high'
                );
            }
        }
        
        return $insights;
    }
    
    /**
     * Generate recommendations for a group
     */
    private function generate_recommendations_for_group($site_rankings, $group_stats) {
        $recommendations = array();
        
        // Get top performer characteristics
        $top_sites = array_slice($site_rankings, 0, 2, true);
        $top_characteristics = $this->analyze_top_performer_characteristics($top_sites);
        
        foreach ($top_characteristics as $characteristic) {
            $recommendations[] = array(
                'type' => 'best_practice',
                'title' => $characteristic['title'],
                'description' => $characteristic['description'],
                'impact' => $characteristic['impact'],
                'effort' => $characteristic['effort']
            );
        }
        
        // Performance-specific recommendations
        if (isset($group_stats['vitals_largest_contentful_paint']['avg']) && 
            $group_stats['vitals_largest_contentful_paint']['avg'] > 2500) {
            $recommendations[] = array(
                'type' => 'performance',
                'title' => 'Optimizar LCP del grupo',
                'description' => 'Implementar lazy loading, optimización de imágenes y CDN para mejorar LCP promedio.',
                'impact' => 'high',
                'effort' => 'medium'
            );
        }
        
        return $recommendations;
    }
    
    /**
     * Analyze characteristics of top performers
     */
    private function analyze_top_performer_characteristics($top_sites) {
        $characteristics = array();
        
        foreach ($top_sites as $site_id => $site_data) {
            // Analyze what makes this site perform well
            $scores = $site_data['scores'];
            
            if ($scores['performance'] > 85) {
                $characteristics[] = array(
                    'title' => 'Excelente rendimiento técnico',
                    'description' => 'Los top performers mantienen LCP < 2s, FID < 100ms y CLS < 0.1',
                    'impact' => 'high',
                    'effort' => 'medium'
                );
            }
            
            if ($scores['seo'] > 80) {
                $characteristics[] = array(
                    'title' => 'SEO optimizado',
                    'description' => 'Títulos optimizados, meta descripciones atractivas y contenido relevante',
                    'impact' => 'high',
                    'effort' => 'low'
                );
            }
            
            if ($scores['ux'] > 80) {
                $characteristics[] = array(
                    'title' => 'Experiencia de usuario superior',
                    'description' => 'Navegación intuitiva, contenido engaging y tiempo de sesión alto',
                    'impact' => 'medium',
                    'effort' => 'high'
                );
            }
        }
        
        return array_unique($characteristics, SORT_REGULAR);
    }
    
    /**
     * Store comparative analysis results
     */
    private function store_comparative_analysis($group_key, $analysis, $group_stats) {
        global $wpdb;
        
        $insight_data = array(
            'group_key' => $group_key,
            'analysis' => $analysis,
            'group_stats' => $group_stats,
            'generated_at' => current_time('mysql')
        );
        
        $wpdb->replace(
            $wpdb->prefix . 'rphub_analytics_insights',
            array(
                'site_id' => 0, // Group-level insight
                'insight_type' => 'comparative_analysis',
                'title' => 'Análisis Comparativo - ' . $group_key,
                'description' => 'Análisis automático de rendimiento grupal',
                'severity' => 'info',
                'data' => json_encode($insight_data),
                'created_at' => current_time('mysql'),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days'))
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Check benchmarks and generate alerts
     */
    public function check_benchmarks() {
        global $wpdb;
        
        $sites = $wpdb->get_results("
            SELECT id, name FROM {$wpdb->prefix}rphub_sites 
            WHERE status = 'active'
        ");
        
        foreach ($sites as $site) {
            $this->check_site_benchmarks($site->id, $site->name);
        }
    }
    
    /**
     * Check benchmarks for a specific site
     */
    private function check_site_benchmarks($site_id, $site_name) {
        global $wpdb;
        
        // Check if benchmarks table exists
        $table_name = $wpdb->prefix . 'rphub_analytics_benchmarks';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        
        if (!$table_exists) {
            return; // Skip if table doesn't exist
        }
        
        // Get current benchmarks for site
        $benchmarks = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}rphub_analytics_benchmarks 
            WHERE site_id = %d
        ", $site_id));
        
        foreach ($benchmarks as $benchmark) {
            $current_value = $this->get_current_metric_value($site_id, $benchmark->metric_name);
            
            if ($current_value === null) continue;
            
            // Update current value
            $wpdb->update(
                $wpdb->prefix . 'rphub_analytics_benchmarks',
                array('current_value' => $current_value),
                array('id' => $benchmark->id),
                array('%f'),
                array('%d')
            );
            
            // Check if alert should be triggered
            $this->check_benchmark_alert($site_id, $site_name, $benchmark, $current_value);
        }
    }
    
    /**
     * Get current value for a metric
     */
    private function get_current_metric_value($site_id, $metric_name) {
        global $wpdb;
        
        switch ($metric_name) {
            case 'largest_contentful_paint':
                $data = $wpdb->get_var($wpdb->prepare("
                    SELECT JSON_EXTRACT(data, '$.largest_contentful_paint.p75') 
                    FROM {$wpdb->prefix}rphub_analytics_web_vitals 
                    WHERE site_id = %d 
                    ORDER BY collected_at DESC 
                    LIMIT 1
                ", $site_id));
                return $data ? floatval($data) : null;
                
            case 'first_input_delay':
                $data = $wpdb->get_var($wpdb->prepare("
                    SELECT JSON_EXTRACT(data, '$.first_input_delay.p75') 
                    FROM {$wpdb->prefix}rphub_analytics_web_vitals 
                    WHERE site_id = %d 
                    ORDER BY collected_at DESC 
                    LIMIT 1
                ", $site_id));
                return $data ? floatval($data) : null;
                
            case 'bounce_rate':
                $data = $wpdb->get_var($wpdb->prepare("
                    SELECT JSON_EXTRACT(data, '$.avg_bounce_rate') 
                    FROM {$wpdb->prefix}rphub_analytics_ga4 
                    WHERE site_id = %d 
                    ORDER BY collected_at DESC 
                    LIMIT 1
                ", $site_id));
                return $data ? floatval($data) : null;
                
            case 'click_through_rate':
                $data = $wpdb->get_var($wpdb->prepare("
                    SELECT JSON_EXTRACT(data, '$.avg_ctr') 
                    FROM {$wpdb->prefix}rphub_analytics_search_console 
                    WHERE site_id = %d 
                    ORDER BY collected_at DESC 
                    LIMIT 1
                ", $site_id));
                return $data ? floatval($data) : null;
                
            default:
                return null;
        }
    }
    
    /**
     * Check if benchmark alert should be triggered
     */
    private function check_benchmark_alert($site_id, $site_name, $benchmark, $current_value) {
        // Calculate deviation from target
        $target_deviation = abs($current_value - $benchmark->target_value) / $benchmark->target_value * 100;
        $baseline_deviation = abs($current_value - $benchmark->baseline_value) / $benchmark->baseline_value * 100;
        
        $alert_threshold = $this->alert_rules['benchmark_deviation']['threshold'];
        
        if ($target_deviation > $alert_threshold || $baseline_deviation > $alert_threshold) {
            $this->create_benchmark_alert($site_id, $site_name, $benchmark, $current_value, $target_deviation);
        }
    }
    
    /**
     * Create benchmark alert
     */
    private function create_benchmark_alert($site_id, $site_name, $benchmark, $current_value, $deviation) {
        global $wpdb;
        
        $severity = 'medium';
        if ($deviation > 30) $severity = 'high';
        if ($deviation > 50) $severity = 'critical';
        
        $alert_title = sprintf(
            'Desviación de benchmark: %s en %s',
            $benchmark->metric_name,
            $site_name
        );
        
        $alert_message = sprintf(
            'El métrico %s tiene un valor de %.2f%s, desviándose %.1f%% del objetivo (%.2f%s)',
            $benchmark->metric_name,
            $current_value,
            $benchmark->unit,
            $deviation,
            $benchmark->target_value,
            $benchmark->unit
        );
        
        $wpdb->insert(
            $wpdb->prefix . 'rphub_analytics_alerts',
            array(
                'site_id' => $site_id,
                'alert_type' => 'benchmark_deviation',
                'metric_name' => $benchmark->metric_name,
                'threshold_value' => $benchmark->target_value,
                'current_value' => $current_value,
                'comparison_operator' => '!=',
                'alert_title' => $alert_title,
                'alert_message' => $alert_message,
                'severity' => $severity,
                'is_active' => 1,
                'is_resolved' => 0,
                'triggered_at' => current_time('mysql'),
                'notification_sent' => 0
            ),
            array('%d', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d')
        );
    }
    
    /**
     * AJAX: Get comparative analysis
     */
    public function get_comparative_analysis() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        try {
            global $wpdb;
            
            $analysis_data = $wpdb->get_results("
                SELECT * FROM {$wpdb->prefix}rphub_analytics_insights 
                WHERE insight_type = 'comparative_analysis' 
                AND expires_at > NOW()
                ORDER BY created_at DESC 
                LIMIT 10
            ");
            
            $formatted_analysis = array();
            foreach ($analysis_data as $analysis) {
                $data = json_decode($analysis->data, true);
                $formatted_analysis[] = array(
                    'id' => $analysis->id,
                    'title' => $analysis->title,
                    'group_key' => $data['group_key'],
                    'insights' => $data['analysis']['insights'],
                    'recommendations' => $data['analysis']['recommendations'],
                    'created_at' => $analysis->created_at
                );
            }
            
            wp_send_json_success($formatted_analysis);
            
        } catch (Exception $e) {
            wp_send_json_error('Error obteniendo análisis: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX: Get performance trends
     */
    public function get_performance_trends() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $site_id = intval($_POST['site_id']);
        $timeframe = sanitize_text_field($_POST['timeframe'] ?? '30d');
        
        try {
            $trends = $this->calculate_performance_trends($site_id, $timeframe);
            wp_send_json_success($trends);
            
        } catch (Exception $e) {
            wp_send_json_error('Error obteniendo tendencias: ' . $e->getMessage());
        }
    }
    
    /**
     * Calculate performance trends for a site
     */
    private function calculate_performance_trends($site_id, $timeframe) {
        global $wpdb;
        
        $days = intval(str_replace('d', '', $timeframe));
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Get historical data
        $historical_data = array();
        
        // GA4 trends
        $ga4_data = $wpdb->get_results($wpdb->prepare("
            SELECT data, collected_at 
            FROM {$wpdb->prefix}rphub_analytics_ga4 
            WHERE site_id = %d 
            AND collected_at >= %s 
            ORDER BY collected_at ASC
        ", $site_id, $cutoff_date));
        
        foreach ($ga4_data as $row) {
            $data = json_decode($row->data, true);
            $date = substr($row->collected_at, 0, 10);
            
            if (!isset($historical_data[$date])) {
                $historical_data[$date] = array();
            }
            
            $historical_data[$date]['sessions'] = $data['total_sessions'] ?? 0;
            $historical_data[$date]['bounce_rate'] = $data['avg_bounce_rate'] ?? 0;
            $historical_data[$date]['session_duration'] = $data['avg_session_duration'] ?? 0;
        }
        
        // Web Vitals trends
        $vitals_data = $wpdb->get_results($wpdb->prepare("
            SELECT data, collected_at 
            FROM {$wpdb->prefix}rphub_analytics_web_vitals 
            WHERE site_id = %d 
            AND collected_at >= %s 
            ORDER BY collected_at ASC
        ", $site_id, $cutoff_date));
        
        foreach ($vitals_data as $row) {
            $data = json_decode($row->data, true);
            $date = substr($row->collected_at, 0, 10);
            
            if (!isset($historical_data[$date])) {
                $historical_data[$date] = array();
            }
            
            foreach ($data as $metric => $values) {
                if (isset($values['p75'])) {
                    $historical_data[$date][$metric] = $values['p75'];
                }
            }
        }
        
        // Calculate trends
        $trends = array();
        $metrics = array('sessions', 'bounce_rate', 'session_duration', 'largest_contentful_paint', 'first_input_delay');
        
        foreach ($metrics as $metric) {
            $values = array();
            foreach ($historical_data as $date => $data) {
                if (isset($data[$metric])) {
                    $values[] = array(
                        'date' => $date,
                        'value' => $data[$metric]
                    );
                }
            }
            
            if (count($values) >= 2) {
                $trend_direction = $this->calculate_trend_direction($values);
                $trends[$metric] = array(
                    'data' => $values,
                    'direction' => $trend_direction['direction'],
                    'change_percent' => $trend_direction['change_percent']
                );
            }
        }
        
        return $trends;
    }
    
    /**
     * Calculate trend direction and change percentage
     */
    private function calculate_trend_direction($values) {
        if (count($values) < 2) {
            return array('direction' => 'stable', 'change_percent' => 0);
        }
        
        $first_value = $values[0]['value'];
        $last_value = $values[count($values) - 1]['value'];
        
        $change_percent = (($last_value - $first_value) / $first_value) * 100;
        
        $direction = 'stable';
        if (abs($change_percent) > 5) {
            $direction = $change_percent > 0 ? 'increasing' : 'decreasing';
        }
        
        return array(
            'direction' => $direction,
            'change_percent' => round($change_percent, 2)
        );
    }
    
    /**
     * Helper function to calculate percentiles
     */
    private function calculate_percentile($values, $percentile) {
        if (empty($values)) return 0;
        
        sort($values);
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
    
    /**
     * Update dynamic benchmarks based on top performers
     */
    private function update_dynamic_benchmarks() {
        global $wpdb;
        
        // Get top 10% performers for each metric
        $sites = $wpdb->get_results("
            SELECT id FROM {$wpdb->prefix}rphub_sites 
            WHERE status = 'active'
        ");
        
        if (count($sites) < 10) return; // Need minimum sample size
        
        $top_performers_count = max(1, floor(count($sites) * 0.1));
        
        // Update benchmarks for key metrics
        $this->update_benchmark_from_top_performers('largest_contentful_paint', $top_performers_count);
        $this->update_benchmark_from_top_performers('first_input_delay', $top_performers_count);
        $this->update_benchmark_from_top_performers('bounce_rate', $top_performers_count);
        $this->update_benchmark_from_top_performers('click_through_rate', $top_performers_count);
    }
    
    /**
     * Update benchmark from top performers for a specific metric
     */
    private function update_benchmark_from_top_performers($metric_name, $top_count) {
        global $wpdb;
        
        // This would implement the logic to find top performers for each metric
        // and update the benchmark targets accordingly
        
        error_log("RPHUB: Updated benchmark for {$metric_name} based on top {$top_count} performers");
    }
}

// Initialize comparative analytics
new ReplantaHub_Comparative_Analytics();
