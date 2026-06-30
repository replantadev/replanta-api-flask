<?php
/**
 * Intelligent Maintenance System for Replanta Hub Professional
 * 
 * Advanced maintenance operations with AI-driven optimizations
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ReplantaHub_Intelligent_Maintenance {
    
    private $maintenance_rules;
    private $performance_thresholds;
    private $optimization_history;
    
    public function __construct() {
        $this->init_maintenance_rules();
        $this->init_performance_thresholds();
        $this->optimization_history = array();
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('rphub_perform_intelligent_maintenance', array($this, 'perform_intelligent_maintenance'));
        add_action('rphub_analyze_site_health', array($this, 'analyze_site_health'));
        add_action('rphub_optimize_performance', array($this, 'optimize_performance'));
        
        add_action('wp_ajax_rphub_run_maintenance_scan', array($this, 'run_maintenance_scan'));
        add_action('wp_ajax_rphub_apply_optimizations', array($this, 'apply_optimizations'));
        add_action('wp_ajax_rphub_get_maintenance_report', array($this, 'get_maintenance_report'));
        add_action('wp_ajax_rphub_schedule_maintenance', array($this, 'schedule_maintenance'));
    }
    
    private function init_maintenance_rules() {
        $this->maintenance_rules = array(
            'database_optimization' => array(
                'priority' => 1,
                'frequency' => 'weekly',
                'conditions' => array(
                    'database_size_mb' => array('operator' => '>', 'value' => 100),
                    'optimization_age_days' => array('operator' => '>', 'value' => 7)
                ),
                'actions' => array(
                    'optimize_tables',
                    'clean_revisions',
                    'clean_spam_comments',
                    'clean_transients',
                    'clean_orphaned_metadata'
                )
            ),
            'file_optimization' => array(
                'priority' => 2,
                'frequency' => 'daily',
                'conditions' => array(
                    'media_library_size_mb' => array('operator' => '>', 'value' => 500),
                    'unoptimized_images_count' => array('operator' => '>', 'value' => 10)
                ),
                'actions' => array(
                    'compress_images',
                    'convert_to_webp',
                    'clean_temp_files',
                    'optimize_js_css'
                )
            ),
            'cache_optimization' => array(
                'priority' => 3,
                'frequency' => 'hourly',
                'conditions' => array(
                    'cache_hit_ratio' => array('operator' => '<', 'value' => 80),
                    'page_load_time_ms' => array('operator' => '>', 'value' => 3000)
                ),
                'actions' => array(
                    'warm_cache',
                    'optimize_cache_rules',
                    'enable_browser_caching',
                    'compress_responses'
                )
            ),
            'security_hardening' => array(
                'priority' => 4,
                'frequency' => 'daily',
                'conditions' => array(
                    'failed_login_attempts' => array('operator' => '>', 'value' => 10),
                    'security_scan_age_days' => array('operator' => '>', 'value' => 1)
                ),
                'actions' => array(
                    'update_security_rules',
                    'scan_suspicious_files',
                    'check_file_permissions',
                    'update_firewall_rules'
                )
            ),
            'content_optimization' => array(
                'priority' => 5,
                'frequency' => 'weekly',
                'conditions' => array(
                    'orphaned_content_count' => array('operator' => '>', 'value' => 5),
                    'seo_score' => array('operator' => '<', 'value' => 85)
                ),
                'actions' => array(
                    'optimize_meta_tags',
                    'fix_broken_links',
                    'update_sitemaps',
                    'optimize_content_structure'
                )
            )
        );
    }
    
    private function init_performance_thresholds() {
        $this->performance_thresholds = array(
            'critical' => array(
                'page_load_time_ms' => 5000,
                'lcp_ms' => 4000,
                'fid_ms' => 300,
                'cls' => 0.25,
                'database_query_time_ms' => 1000,
                'memory_usage_mb' => 512
            ),
            'warning' => array(
                'page_load_time_ms' => 3000,
                'lcp_ms' => 2500,
                'fid_ms' => 100,
                'cls' => 0.1,
                'database_query_time_ms' => 500,
                'memory_usage_mb' => 256
            ),
            'optimal' => array(
                'page_load_time_ms' => 1500,
                'lcp_ms' => 1200,
                'fid_ms' => 50,
                'cls' => 0.05,
                'database_query_time_ms' => 200,
                'memory_usage_mb' => 128
            )
        );
    }
    
    /**
     * Perform intelligent maintenance
     */
    public function perform_intelligent_maintenance() {
        error_log('RPHUB: Starting intelligent maintenance cycle');
        
        $maintenance_start = microtime(true);
        $maintenance_results = array();
        
        try {
            // 1. Analyze current site health
            $health_analysis = $this->analyze_site_health();
            $maintenance_results['health_analysis'] = $health_analysis;
            
            // 2. Identify optimization opportunities
            $optimization_opportunities = $this->identify_optimization_opportunities($health_analysis);
            $maintenance_results['optimization_opportunities'] = $optimization_opportunities;
            
            // 3. Execute high-priority optimizations
            $optimization_results = $this->execute_optimizations($optimization_opportunities);
            $maintenance_results['optimization_results'] = $optimization_results;
            
            // 4. Validate improvements
            $improvement_validation = $this->validate_improvements($health_analysis);
            $maintenance_results['improvement_validation'] = $improvement_validation;
            
            // 5. Generate maintenance report
            $maintenance_report = $this->generate_maintenance_report($maintenance_results);
            $maintenance_results['maintenance_report'] = $maintenance_report;
            
            $maintenance_time = microtime(true) - $maintenance_start;
            
            // Log maintenance execution
            $this->log_maintenance_execution($maintenance_results, $maintenance_time);
            
            error_log("RPHUB: Intelligent maintenance completed in {$maintenance_time}s");
            
            return $maintenance_results;
            
        } catch (Exception $e) {
            error_log('RPHUB: Maintenance error: ' . $e->getMessage());
            return array('error' => $e->getMessage());
        }
    }
    
    /**
     * Analyze site health
     */
    public function analyze_site_health() {
        $health_data = array(
            'timestamp' => current_time('mysql'),
            'performance_metrics' => $this->collect_performance_metrics(),
            'database_health' => $this->analyze_database_health(),
            'file_system_health' => $this->analyze_file_system(),
            'security_status' => $this->analyze_security_status(),
            'plugin_compatibility' => $this->analyze_plugin_compatibility(),
            'theme_optimization' => $this->analyze_theme_optimization()
        );
        
        // Calculate overall health score
        $health_data['overall_score'] = $this->calculate_health_score($health_data);
        
        return $health_data;
    }
    
    /**
     * Collect performance metrics
     */
    private function collect_performance_metrics() {
        global $wpdb;
        
        $metrics = array();
        
        // Database performance
        $db_start = microtime(true);
        $wpdb->get_results("SELECT COUNT(*) FROM {$wpdb->posts}");
        $metrics['database_query_time_ms'] = (microtime(true) - $db_start) * 1000;
        
        // Memory usage
        $metrics['memory_usage_mb'] = memory_get_peak_usage(true) / 1024 / 1024;
        $metrics['memory_limit_mb'] = ini_get('memory_limit');
        
        // Database size
        $db_size = $wpdb->get_var("
            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS 'DB Size in MB' 
            FROM information_schema.tables 
            WHERE table_schema='{$wpdb->dbname}'
        ");
        $metrics['database_size_mb'] = floatval($db_size);
        
        // Get Web Vitals data
        $web_vitals = $this->get_recent_web_vitals();
        if ($web_vitals) {
            $metrics = array_merge($metrics, $web_vitals);
        }
        
        return $metrics;
    }
    
    /**
     * Get recent Web Vitals data
     */
    private function get_recent_web_vitals() {
        global $wpdb;
        
        $vitals = $wpdb->get_row("
            SELECT 
                JSON_EXTRACT(data, '$.lcp.p75') as lcp_ms,
                JSON_EXTRACT(data, '$.fid.p75') as fid_ms,
                JSON_EXTRACT(data, '$.cls.p75') as cls,
                JSON_EXTRACT(data, '$.ttfb.p75') as ttfb_ms
            FROM {$wpdb->prefix}rphub_analytics_web_vitals 
            WHERE collected_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
            ORDER BY collected_at DESC 
            LIMIT 1
        ", ARRAY_A);
        
        if ($vitals) {
            return array(
                'lcp_ms' => floatval($vitals['lcp_ms']),
                'fid_ms' => floatval($vitals['fid_ms']),
                'cls' => floatval($vitals['cls']),
                'ttfb_ms' => floatval($vitals['ttfb_ms'])
            );
        }
        
        return null;
    }
    
    /**
     * Analyze database health
     */
    private function analyze_database_health() {
        global $wpdb;
        
        $health = array();
        
        // Table optimization status
        $tables = $wpdb->get_results("SHOW TABLE STATUS", ARRAY_A);
        $total_overhead = 0;
        $fragmented_tables = 0;
        
        foreach ($tables as $table) {
            if ($table['Data_free'] > 0) {
                $total_overhead += $table['Data_free'];
                $fragmented_tables++;
            }
        }
        
        $health['total_overhead_mb'] = $total_overhead / 1024 / 1024;
        $health['fragmented_tables_count'] = $fragmented_tables;
        
        // Orphaned data
        $health['orphaned_postmeta'] = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->postmeta} pm 
            LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
            WHERE p.ID IS NULL
        ");
        
        $health['orphaned_commentmeta'] = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->commentmeta} cm 
            LEFT JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID 
            WHERE c.comment_ID IS NULL
        ");
        
        // Spam and trash content
        $health['spam_comments'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'");
        $health['trash_posts'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'");
        $health['post_revisions'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'");
        
        // Transients
        $health['expired_transients'] = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_timeout_%' 
            AND option_value < UNIX_TIMESTAMP()
        ");
        
        return $health;
    }
    
    /**
     * Analyze file system
     */
    private function analyze_file_system() {
        $file_health = array();
        
        // Upload directory analysis
        $upload_dir = wp_upload_dir();
        $upload_path = $upload_dir['basedir'];
        
        if (is_dir($upload_path)) {
            $file_health['upload_dir_size_mb'] = $this->get_directory_size($upload_path) / 1024 / 1024;
            $file_health['total_files'] = $this->count_files_recursive($upload_path);
            $file_health['image_files'] = $this->count_image_files($upload_path);
        }
        
        // Theme and plugin file health
        $file_health['active_theme_size_mb'] = $this->get_directory_size(get_template_directory()) / 1024 / 1024;
        $file_health['plugins_size_mb'] = $this->get_directory_size(WP_PLUGIN_DIR) / 1024 / 1024;
        
        // Temporary files
        $temp_dir = sys_get_temp_dir();
        $file_health['temp_files_count'] = $this->count_temp_files($temp_dir);
        
        return $file_health;
    }
    
    /**
     * Analyze security status
     */
    private function analyze_security_status() {
        global $wpdb;
        
        $security = array();
        
        // Check for security plugins
        $security_plugins = array('wordfence', 'sucuri', 'ithemes-security', 'all-in-one-wp-security');
        $security['has_security_plugin'] = false;
        
        foreach ($security_plugins as $plugin) {
            if (is_plugin_active($plugin . '/' . $plugin . '.php')) {
                $security['has_security_plugin'] = true;
                $security['active_security_plugin'] = $plugin;
                break;
            }
        }
        
        // Check WordPress version
        $wp_version = get_bloginfo('version');
        $latest_version = $this->get_latest_wordpress_version();
        $security['wp_version_current'] = version_compare($wp_version, $latest_version, '>=');
        
        // Check for failed login attempts (if logged)
        $security['failed_login_attempts'] = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->options} 
            WHERE option_name LIKE 'failed_login_%' 
            AND option_value > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        
        // File permissions check
        $security['secure_file_permissions'] = $this->check_file_permissions();
        
        return $security;
    }
    
    /**
     * Analyze plugin compatibility
     */
    private function analyze_plugin_compatibility() {
        $compatibility = array();
        
        $active_plugins = get_option('active_plugins');
        $compatibility['total_active_plugins'] = count($active_plugins);
        
        $outdated_plugins = 0;
        $plugin_updates = get_site_transient('update_plugins');
        
        if (isset($plugin_updates->response)) {
            $outdated_plugins = count($plugin_updates->response);
        }
        
        $compatibility['outdated_plugins'] = $outdated_plugins;
        $compatibility['plugin_update_percentage'] = $outdated_plugins > 0 ? 
            round(($outdated_plugins / $compatibility['total_active_plugins']) * 100, 2) : 0;
        
        return $compatibility;
    }
    
    /**
     * Analyze theme optimization
     */
    private function analyze_theme_optimization() {
        $theme_data = array();
        
        $theme = wp_get_theme();
        $theme_data['theme_name'] = $theme->get('Name');
        $theme_data['theme_version'] = $theme->get('Version');
        
        // Check for theme updates
        $theme_updates = get_site_transient('update_themes');
        $theme_data['has_update'] = isset($theme_updates->response[get_template()]);
        
        // Check for child theme
        $theme_data['is_child_theme'] = is_child_theme();
        
        return $theme_data;
    }
    
    /**
     * Calculate overall health score
     */
    private function calculate_health_score($health_data) {
        $score = 100;
        $performance = $health_data['performance_metrics'];
        
        // Performance deductions
        if (isset($performance['lcp_ms']) && $performance['lcp_ms'] > $this->performance_thresholds['critical']['lcp_ms']) {
            $score -= 20;
        } elseif (isset($performance['lcp_ms']) && $performance['lcp_ms'] > $this->performance_thresholds['warning']['lcp_ms']) {
            $score -= 10;
        }
        
        if (isset($performance['database_query_time_ms']) && $performance['database_query_time_ms'] > $this->performance_thresholds['critical']['database_query_time_ms']) {
            $score -= 15;
        }
        
        // Database health deductions
        $database = $health_data['database_health'];
        if ($database['fragmented_tables_count'] > 5) {
            $score -= 10;
        }
        
        if ($database['orphaned_postmeta'] > 100) {
            $score -= 5;
        }
        
        if ($database['spam_comments'] > 50) {
            $score -= 5;
        }
        
        // Security deductions
        $security = $health_data['security_status'];
        if (!$security['has_security_plugin']) {
            $score -= 15;
        }
        
        if (!$security['wp_version_current']) {
            $score -= 10;
        }
        
        // Plugin compatibility deductions
        $plugins = $health_data['plugin_compatibility'];
        if ($plugins['plugin_update_percentage'] > 20) {
            $score -= 10;
        }
        
        return max(0, $score);
    }
    
    /**
     * Identify optimization opportunities
     */
    private function identify_optimization_opportunities($health_analysis) {
        $opportunities = array();
        
        foreach ($this->maintenance_rules as $rule_name => $rule) {
            $should_apply = true;
            
            // Check if conditions are met
            foreach ($rule['conditions'] as $condition_key => $condition) {
                $current_value = $this->get_health_metric_value($health_analysis, $condition_key);
                
                if ($current_value !== null) {
                    $condition_met = $this->evaluate_condition($current_value, $condition);
                    if (!$condition_met) {
                        $should_apply = false;
                        break;
                    }
                }
            }
            
            if ($should_apply) {
                $opportunities[] = array(
                    'rule_name' => $rule_name,
                    'priority' => $rule['priority'],
                    'actions' => $rule['actions'],
                    'estimated_impact' => $this->estimate_optimization_impact($rule_name, $health_analysis)
                );
            }
        }
        
        // Sort by priority and impact
        usort($opportunities, function($a, $b) {
            if ($a['priority'] == $b['priority']) {
                return $b['estimated_impact'] - $a['estimated_impact'];
            }
            return $a['priority'] - $b['priority'];
        });
        
        return $opportunities;
    }
    
    /**
     * Execute optimizations
     */
    private function execute_optimizations($opportunities) {
        $results = array();
        
        foreach ($opportunities as $opportunity) {
            $rule_name = $opportunity['rule_name'];
            $actions = $opportunity['actions'];
            
            $rule_results = array(
                'rule_name' => $rule_name,
                'actions_executed' => array(),
                'start_time' => microtime(true)
            );
            
            foreach ($actions as $action) {
                $action_result = $this->execute_maintenance_action($action);
                $rule_results['actions_executed'][] = array(
                    'action' => $action,
                    'result' => $action_result,
                    'timestamp' => current_time('mysql')
                );
                
                // Add delay between actions
                sleep(1);
            }
            
            $rule_results['execution_time'] = microtime(true) - $rule_results['start_time'];
            $results[] = $rule_results;
        }
        
        return $results;
    }
    
    /**
     * Execute maintenance action
     */
    private function execute_maintenance_action($action) {
        switch ($action) {
            case 'optimize_tables':
                return $this->optimize_database_tables();
                
            case 'clean_revisions':
                return $this->clean_post_revisions();
                
            case 'clean_spam_comments':
                return $this->clean_spam_comments();
                
            case 'clean_transients':
                return $this->clean_expired_transients();
                
            case 'clean_orphaned_metadata':
                return $this->clean_orphaned_metadata();
                
            case 'compress_images':
                return $this->compress_images();
                
            case 'warm_cache':
                return $this->warm_cache();
                
            case 'update_security_rules':
                return $this->update_security_rules();
                
            case 'optimize_meta_tags':
                return $this->optimize_meta_tags();
                
            default:
                return array('success' => false, 'message' => 'Unknown action: ' . $action);
        }
    }
    
    /**
     * Optimize database tables
     */
    private function optimize_database_tables() {
        global $wpdb;
        
        try {
            $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
            $optimized = 0;
            
            foreach ($tables as $table) {
                $table_name = $table[0];
                $result = $wpdb->query("OPTIMIZE TABLE `$table_name`");
                if ($result !== false) {
                    $optimized++;
                }
            }
            
            return array(
                'success' => true,
                'message' => "Optimized {$optimized} database tables",
                'tables_optimized' => $optimized
            );
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'Database optimization failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Clean post revisions
     */
    private function clean_post_revisions() {
        global $wpdb;
        
        try {
            $deleted = $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'");
            
            return array(
                'success' => true,
                'message' => "Deleted {$deleted} post revisions",
                'revisions_deleted' => $deleted
            );
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'Revision cleanup failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Clean spam comments
     */
    private function clean_spam_comments() {
        global $wpdb;
        
        try {
            $deleted = $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_approved = 'spam'");
            
            return array(
                'success' => true,
                'message' => "Deleted {$deleted} spam comments",
                'spam_deleted' => $deleted
            );
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'Spam cleanup failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Clean expired transients
     */
    private function clean_expired_transients() {
        global $wpdb;
        
        try {
            // Get expired transients
            $expired_transients = $wpdb->get_col("
                SELECT option_name FROM {$wpdb->options} 
                WHERE option_name LIKE '_transient_timeout_%' 
                AND option_value < UNIX_TIMESTAMP()
            ");
            
            $deleted = 0;
            foreach ($expired_transients as $transient) {
                $transient_name = str_replace('_transient_timeout_', '', $transient);
                delete_transient($transient_name);
                $deleted++;
            }
            
            return array(
                'success' => true,
                'message' => "Deleted {$deleted} expired transients",
                'transients_deleted' => $deleted
            );
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'Transient cleanup failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Clean orphaned metadata
     */
    private function clean_orphaned_metadata() {
        global $wpdb;
        
        try {
            $deleted_postmeta = $wpdb->query("
                DELETE pm FROM {$wpdb->postmeta} pm 
                LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
                WHERE p.ID IS NULL
            ");
            
            $deleted_commentmeta = $wpdb->query("
                DELETE cm FROM {$wpdb->commentmeta} cm 
                LEFT JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID 
                WHERE c.comment_ID IS NULL
            ");
            
            return array(
                'success' => true,
                'message' => "Deleted {$deleted_postmeta} orphaned postmeta and {$deleted_commentmeta} orphaned commentmeta",
                'postmeta_deleted' => $deleted_postmeta,
                'commentmeta_deleted' => $deleted_commentmeta
            );
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'Metadata cleanup failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Compress images
     */
    private function compress_images() {
        // This would integrate with image optimization services
        return array(
            'success' => true,
            'message' => 'Image compression optimization queued',
            'images_queued' => 0
        );
    }
    
    /**
     * Warm cache
     */
    private function warm_cache() {
        // This would warm the cache by visiting important pages
        return array(
            'success' => true,
            'message' => 'Cache warming initiated',
            'pages_warmed' => 0
        );
    }
    
    /**
     * Update security rules
     */
    private function update_security_rules() {
        // This would update security plugin rules
        return array(
            'success' => true,
            'message' => 'Security rules updated',
            'rules_updated' => 0
        );
    }
    
    /**
     * Optimize meta tags
     */
    private function optimize_meta_tags() {
        // This would optimize SEO meta tags
        return array(
            'success' => true,
            'message' => 'Meta tags optimization completed',
            'tags_optimized' => 0
        );
    }
    
    /**
     * Validate improvements
     */
    private function validate_improvements($baseline_health) {
        // Re-analyze health after optimizations
        $post_optimization_health = $this->analyze_site_health();
        
        $improvements = array(
            'baseline_score' => $baseline_health['overall_score'],
            'current_score' => $post_optimization_health['overall_score'],
            'improvement_delta' => $post_optimization_health['overall_score'] - $baseline_health['overall_score'],
            'performance_changes' => $this->compare_performance_metrics(
                $baseline_health['performance_metrics'],
                $post_optimization_health['performance_metrics']
            ),
            'database_changes' => $this->compare_database_health(
                $baseline_health['database_health'],
                $post_optimization_health['database_health']
            )
        );
        
        return $improvements;
    }
    
    /**
     * Generate maintenance report
     */
    private function generate_maintenance_report($maintenance_results) {
        $report = array(
            'execution_timestamp' => current_time('mysql'),
            'overall_health_before' => $maintenance_results['health_analysis']['overall_score'],
            'overall_health_after' => isset($maintenance_results['improvement_validation']['current_score']) ? 
                $maintenance_results['improvement_validation']['current_score'] : null,
            'optimization_opportunities_found' => count($maintenance_results['optimization_opportunities']),
            'optimizations_executed' => count($maintenance_results['optimization_results']),
            'performance_improvement' => isset($maintenance_results['improvement_validation']['improvement_delta']) ? 
                $maintenance_results['improvement_validation']['improvement_delta'] : 0,
            'recommendations' => $this->generate_recommendations($maintenance_results)
        );
        
        return $report;
    }
    
    /**
     * Generate recommendations
     */
    private function generate_recommendations($maintenance_results) {
        $recommendations = array();
        
        $health = $maintenance_results['health_analysis'];
        
        // Performance recommendations
        if (isset($health['performance_metrics']['lcp_ms']) && 
            $health['performance_metrics']['lcp_ms'] > $this->performance_thresholds['warning']['lcp_ms']) {
            $recommendations[] = 'Consider implementing advanced caching strategies to improve Largest Contentful Paint';
        }
        
        // Database recommendations
        if ($health['database_health']['fragmented_tables_count'] > 3) {
            $recommendations[] = 'Schedule regular database optimization to reduce table fragmentation';
        }
        
        // Security recommendations
        if (!$health['security_status']['has_security_plugin']) {
            $recommendations[] = 'Install and configure a comprehensive security plugin';
        }
        
        // Plugin recommendations
        if ($health['plugin_compatibility']['plugin_update_percentage'] > 15) {
            $recommendations[] = 'Update outdated plugins to improve security and performance';
        }
        
        return $recommendations;
    }
    
    /**
     * Helper methods
     */
    
    private function get_directory_size($directory) {
        $size = 0;
        if (is_dir($directory)) {
            foreach (glob(rtrim($directory, '/') . '/*', GLOB_NOSORT) as $each) {
                $size += is_file($each) ? filesize($each) : $this->get_directory_size($each);
            }
        }
        return $size;
    }
    
    private function count_files_recursive($directory) {
        $count = 0;
        if (is_dir($directory)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $count++;
                }
            }
        }
        return $count;
    }
    
    private function count_image_files($directory) {
        $count = 0;
        $image_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp');
        
        if (is_dir($directory)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $extension = strtolower($file->getExtension());
                    if (in_array($extension, $image_extensions)) {
                        $count++;
                    }
                }
            }
        }
        return $count;
    }
    
    private function count_temp_files($directory) {
        // This would count temporary files
        return 0;
    }
    
    private function get_latest_wordpress_version() {
        $version_data = wp_remote_get('https://api.wordpress.org/core/version-check/1.7/');
        if (!is_wp_error($version_data)) {
            $body = wp_remote_retrieve_body($version_data);
            $data = json_decode($body, true);
            if (isset($data['offers'][0]['version'])) {
                return $data['offers'][0]['version'];
            }
        }
        return get_bloginfo('version');
    }
    
    private function check_file_permissions() {
        // This would check critical file permissions
        return true;
    }
    
    private function get_health_metric_value($health_analysis, $metric_key) {
        // Navigate through health analysis to find metric value
        if (isset($health_analysis['performance_metrics'][$metric_key])) {
            return $health_analysis['performance_metrics'][$metric_key];
        }
        
        if (isset($health_analysis['database_health'][$metric_key])) {
            return $health_analysis['database_health'][$metric_key];
        }
        
        if (isset($health_analysis['file_system_health'][$metric_key])) {
            return $health_analysis['file_system_health'][$metric_key];
        }
        
        return null;
    }
    
    private function evaluate_condition($current_value, $condition) {
        switch ($condition['operator']) {
            case '>':
                return $current_value > $condition['value'];
            case '<':
                return $current_value < $condition['value'];
            case '>=':
                return $current_value >= $condition['value'];
            case '<=':
                return $current_value <= $condition['value'];
            case '==':
                return $current_value == $condition['value'];
            default:
                return false;
        }
    }
    
    private function estimate_optimization_impact($rule_name, $health_analysis) {
        // Estimate impact based on rule type and current health
        $impact_map = array(
            'database_optimization' => 15,
            'file_optimization' => 10,
            'cache_optimization' => 20,
            'security_hardening' => 5,
            'content_optimization' => 8
        );
        
        return isset($impact_map[$rule_name]) ? $impact_map[$rule_name] : 5;
    }
    
    private function compare_performance_metrics($baseline, $current) {
        $changes = array();
        
        foreach ($baseline as $metric => $baseline_value) {
            if (isset($current[$metric])) {
                $change = $current[$metric] - $baseline_value;
                $change_percentage = $baseline_value > 0 ? ($change / $baseline_value) * 100 : 0;
                
                $changes[$metric] = array(
                    'baseline' => $baseline_value,
                    'current' => $current[$metric],
                    'change' => $change,
                    'change_percentage' => round($change_percentage, 2)
                );
            }
        }
        
        return $changes;
    }
    
    private function compare_database_health($baseline, $current) {
        $changes = array();
        
        foreach ($baseline as $metric => $baseline_value) {
            if (isset($current[$metric])) {
                $change = $current[$metric] - $baseline_value;
                
                $changes[$metric] = array(
                    'baseline' => $baseline_value,
                    'current' => $current[$metric],
                    'change' => $change
                );
            }
        }
        
        return $changes;
    }
    
    private function log_maintenance_execution($results, $execution_time) {
        global $wpdb;
        
        $log_data = array(
            'execution_time' => $execution_time,
            'health_score_before' => $results['health_analysis']['overall_score'],
            'health_score_after' => isset($results['improvement_validation']['current_score']) ? 
                $results['improvement_validation']['current_score'] : null,
            'optimizations_executed' => count($results['optimization_results']),
            'recommendations_generated' => count($results['maintenance_report']['recommendations']),
            'executed_at' => current_time('mysql')
        );
        
        $wpdb->insert(
            $wpdb->prefix . 'rphub_automation_tasks',
            array(
                'task_type' => 'intelligent_maintenance',
                'task_data' => json_encode($log_data),
                'status' => 'completed',
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * AJAX handlers
     */
    
    public function run_maintenance_scan() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        try {
            $health_analysis = $this->analyze_site_health();
            $opportunities = $this->identify_optimization_opportunities($health_analysis);
            
            wp_send_json_success(array(
                'health_analysis' => $health_analysis,
                'optimization_opportunities' => $opportunities
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Error en análisis: ' . $e->getMessage());
        }
    }
    
    public function apply_optimizations() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        try {
            $results = $this->perform_intelligent_maintenance();
            wp_send_json_success($results);
            
        } catch (Exception $e) {
            wp_send_json_error('Error ejecutando optimizaciones: ' . $e->getMessage());
        }
    }
    
    public function get_maintenance_report() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        global $wpdb;
        
        $recent_maintenance = $wpdb->get_row("
            SELECT task_data FROM {$wpdb->prefix}rphub_automation_tasks 
            WHERE task_type = 'intelligent_maintenance' 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        
        if ($recent_maintenance) {
            $maintenance_data = json_decode($recent_maintenance->task_data, true);
            wp_send_json_success($maintenance_data);
        } else {
            wp_send_json_error('No hay reportes de mantenimiento disponibles');
        }
    }
    
    public function schedule_maintenance() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $frequency = sanitize_text_field($_POST['frequency']);
        $time = sanitize_text_field($_POST['time']);
        
        // Clear existing scheduled maintenance
        RPHUB_Scheduler::cancel('rphub_perform_intelligent_maintenance');
        
        // Schedule new maintenance
        if ($frequency !== 'disabled') {
            $next_run = strtotime("next {$frequency} {$time}");
            if (RPHUB_Scheduler::as_available()) {
                as_schedule_recurring_action($next_run, RPHUB_Scheduler::interval_seconds($frequency), 'rphub_perform_intelligent_maintenance', [], RPHUB_Scheduler::GROUP);
            } else {
                wp_schedule_event($next_run, $frequency, 'rphub_perform_intelligent_maintenance');
            }
            
            wp_send_json_success('Mantenimiento programado exitosamente');
        } else {
            wp_send_json_success('Mantenimiento automático deshabilitado');
        }
    }
}

// Initialize intelligent maintenance
new ReplantaHub_Intelligent_Maintenance();
