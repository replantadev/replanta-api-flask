<?php
/**
 * Intelligent Automation System for Replanta Hub
 * 
 * Manages automated maintenance, monitoring, and intelligent decision making
 * across all integrated systems
 */

class ReplantaHub_Automation_System {
    
    private $wptoolkit;
    private $backuply;
    private $pagespeed;
    private $cloudflare;
    private $reports;
    
    public function __construct() {
        add_action('init', [$this, 'init']);
    }
    
    public function init() {
        // Initialize integrations
        $this->wptoolkit = new ReplantaHub_WPToolkit_Integration();
        $this->backuply = new ReplantaHub_Backuply_Integration();
        $this->pagespeed = new ReplantaHub_PageSpeed_Integration();
        $this->cloudflare = new ReplantaHub_Cloudflare_Integration();
        $this->reports = new ReplantaHub_Reports_System();
        
        // AJAX handlers
        add_action('wp_ajax_rphub_automation_enable', [$this, 'ajax_enable_automation']);
        add_action('wp_ajax_rphub_automation_configure', [$this, 'ajax_configure_automation']);
        add_action('wp_ajax_rphub_automation_status', [$this, 'ajax_get_automation_status']);
        add_action('wp_ajax_rphub_automation_logs', [$this, 'ajax_get_automation_logs']);
        add_action('wp_ajax_rphub_run_maintenance_check', [$this, 'ajax_run_maintenance_check']);
        
        // Scheduled automation tasks
        add_action('rphub_intelligent_maintenance', [$this, 'intelligent_maintenance_routine']);
        add_action('rphub_security_monitoring', [$this, 'security_monitoring_routine']);
        add_action('rphub_performance_optimization', [$this, 'performance_optimization_routine']);
        add_action('rphub_backup_management', [$this, 'backup_management_routine']);
        add_action('rphub_health_assessment', [$this, 'health_assessment_routine']);
        
        // Schedule automation events
        $this->schedule_automation_events();
    }
    
    /**
     * Schedule all automation events
     */
    private function schedule_automation_events() {
        RPHUB_Scheduler::schedule('rphub_intelligent_maintenance',  'sixhourly');
        RPHUB_Scheduler::schedule('rphub_security_monitoring',      'hourly');
        RPHUB_Scheduler::schedule('rphub_performance_optimization', 'daily');
        RPHUB_Scheduler::schedule('rphub_backup_management',        'fourhourly');
        RPHUB_Scheduler::schedule('rphub_health_assessment',        'twicehourly');

        // Custom WP Cron intervals (still needed as AS fallback uses them)
        add_filter('cron_schedules', [$this, 'add_custom_cron_intervals']);
    }
    
    /**
     * Add custom cron intervals
     */
    public function add_custom_cron_intervals($schedules) {
        $schedules['twicehourly'] = [
            'interval' => 2 * HOUR_IN_SECONDS,
            'display' => 'Twice Hourly'
        ];
        
        $schedules['fourhourly'] = [
            'interval' => 4 * HOUR_IN_SECONDS,
            'display' => 'Four Hourly'
        ];
        
        $schedules['sixhourly'] = [
            'interval' => 6 * HOUR_IN_SECONDS,
            'display' => 'Six Hourly'
        ];
        
        return $schedules;
    }
    
    /**
     * Intelligent maintenance routine
     */
    public function intelligent_maintenance_routine() {
        $this->log_automation('Starting intelligent maintenance routine');
        
        global $wpdb;
        $table_sites = $wpdb->prefix . 'rphub_sites';
        
        // Get sites with automation enabled
        $sites = $wpdb->get_results("
            SELECT * FROM $table_sites 
            WHERE status = 'active' AND automation_enabled = 1
            ORDER BY last_maintenance_check ASC
            LIMIT 10
        ", ARRAY_A);
        
        foreach ($sites as $site) {
            $this->perform_intelligent_maintenance($site);
            
            // Update last maintenance check
            $wpdb->update($table_sites, 
                ['last_maintenance_check' => current_time('mysql')], 
                ['id' => $site['id']]
            );
            
            // Small delay between sites
            sleep(2);
        }
        
        $this->log_automation('Completed intelligent maintenance routine for ' . count($sites) . ' sites');
    }
    
    /**
     * Perform intelligent maintenance for a single site
     */
    private function perform_intelligent_maintenance($site) {
        $domain = parse_url($site['url'], PHP_URL_HOST);
        $site_id = $site['id'];
        
        $this->log_automation("Starting maintenance for {$site['name']} ({$domain})", $site_id);
        
        // 1. Health Assessment
        $health = $this->assess_site_health($site_id, $domain);
        
        // 2. Intelligent Update Management
        if ($health['security_score'] < 80 || $health['maintenance_score'] < 70) {
            $this->perform_intelligent_updates($site_id, $domain);
        }
        
        // 3. Backup Verification
        $this->verify_backup_health($site_id, $domain);
        
        // 4. Performance Optimization
        if ($health['performance_score'] < 70) {
            $this->perform_performance_optimization($site_id, $domain);
        }
        
        // 5. Security Enhancement
        if ($health['security_score'] < 90) {
            $this->enhance_security($site_id, $domain);
        }
        
        // 6. Generate intelligent recommendations
        $this->generate_intelligent_recommendations($site_id, $domain, $health);
        
        $this->log_automation("Completed maintenance for {$site['name']}", $site_id);
    }
    
    /**
     * Assess comprehensive site health
     */
    private function assess_site_health($site_id, $domain) {
        $health = [
            'security_score' => 0,
            'performance_score' => 0,
            'maintenance_score' => 0,
            'backup_score' => 0,
            'overall_score' => 0
        ];
        
        try {
            // Security assessment
            $vulnerability_scan = $this->wptoolkit->scan_vulnerabilities($domain);
            if (!is_wp_error($vulnerability_scan)) {
                $vulns = $vulnerability_scan['vulnerabilities_found'] ?? [];
                $health['security_score'] = max(0, 100 - (count($vulns) * 15));
            } else {
                rphub_log_integration_error('Automation', 'assess_site_health', 
                    'Security scan failed: ' . $vulnerability_scan->get_error_message(),
                    ['site_id' => $site_id, 'domain' => $domain]
                );
            }
            
            // Performance assessment
            $pagespeed = $this->pagespeed->get_performance_summary($domain);
            if (!is_wp_error($pagespeed)) {
                $health['performance_score'] = $pagespeed['performance_score'] ?? 0;
            } else {
                rphub_log_integration_error('Automation', 'assess_site_health', 
                    'PageSpeed analysis failed: ' . $pagespeed->get_error_message(),
                    ['site_id' => $site_id, 'domain' => $domain]
                );
            }
            
            // Backup assessment
            $backup_health = $this->backuply->get_backup_health($domain);
            if (!is_wp_error($backup_health)) {
                $health['backup_score'] = $backup_health['score'] ?? 0;
            } else {
                rphub_log_integration_error('Automation', 'assess_site_health', 
                    'Backup health check failed: ' . $backup_health->get_error_message(),
                    ['site_id' => $site_id, 'domain' => $domain]
                );
            }
            
            // Maintenance assessment
            $wp_info = $this->wptoolkit->get_site_info($domain);
            if (!is_wp_error($wp_info)) {
                $updates = $this->wptoolkit->get_update_recommendations($domain);
                $update_count = 0;
                if (!is_wp_error($updates)) {
                    $update_count += count($updates['plugins'] ?? []);
                    $update_count += count($updates['themes'] ?? []);
                    $update_count += isset($updates['wordpress']) ? 1 : 0;
                }
                $health['maintenance_score'] = max(0, 100 - ($update_count * 10));
            } else {
                rphub_log_integration_error('Automation', 'assess_site_health', 
                    'Site info retrieval failed: ' . $wp_info->get_error_message(),
                    ['site_id' => $site_id, 'domain' => $domain]
                );
            }
            
            // Calculate overall score
            $health['overall_score'] = round(
                ($health['security_score'] * 0.3) +
                ($health['performance_score'] * 0.25) +
                ($health['backup_score'] * 0.25) +
                ($health['maintenance_score'] * 0.2)
            );
            
        } catch (Exception $e) {
            rphub_log_integration_error('Automation', 'assess_site_health', 
                'Exception during health assessment: ' . $e->getMessage(),
                ['site_id' => $site_id, 'domain' => $domain]
            );
        }
        
        return $health;
    }
    
    /**
     * Perform intelligent updates based on risk assessment
     */
    private function perform_intelligent_updates($site_id, $domain) {
        $this->log_automation("Analyzing update requirements for {$domain}", $site_id);
        
        $updates = $this->wptoolkit->get_update_recommendations($domain);
        
        if (is_wp_error($updates)) {
            $this->log_automation("Failed to get update recommendations: " . $updates->get_error_message(), $site_id);
            return;
        }
        
        $update_plan = $this->create_intelligent_update_plan($updates);
        
        foreach ($update_plan as $phase => $phase_updates) {
            if (empty($phase_updates)) continue;
            
            $this->log_automation("Executing update phase: {$phase}", $site_id);
            
            // Create backup before updates
            $backup = $this->backuply->create_backup($domain, [
                'type' => 'full',
                'description' => "Pre-update backup - Phase {$phase}"
            ]);
            
            if (is_wp_error($backup)) {
                $this->log_automation("Backup creation failed, skipping updates: " . $backup->get_error_message(), $site_id);
                continue;
            }
            
            // Execute updates for this phase
            $update_result = $this->wptoolkit->smart_update($domain, [
                'create_backup' => false, // Already created
                'maintenance_mode' => true,
                'rollback_on_error' => true,
                'update_core' => in_array('wordpress', $phase_updates),
                'update_plugins' => in_array('plugins', $phase_updates),
                'update_themes' => in_array('themes', $phase_updates)
            ]);
            
            if (is_wp_error($update_result)) {
                $this->log_automation("Update phase {$phase} failed: " . $update_result->get_error_message(), $site_id);
                $this->create_alert($site_id, 'update_failed', "Update phase {$phase} failed", 'error');
            } else {
                $this->log_automation("Update phase {$phase} completed successfully", $site_id);
                
                // Verify site health after updates
                $this->verify_post_update_health($site_id, $domain);
            }
            
            // Delay between phases
            sleep(30);
        }
    }
    
    /**
     * Create intelligent update plan based on risk assessment
     */
    private function create_intelligent_update_plan($updates) {
        $plan = [
            'critical' => [],
            'high' => [],
            'medium' => [],
            'low' => []
        ];
        
        // WordPress core updates (highest priority if security-related)
        if (isset($updates['wordpress'])) {
            $priority = $updates['wordpress']['security_update'] ? 'critical' : 'high';
            $plan[$priority][] = 'wordpress';
        }
        
        // Plugin updates
        foreach ($updates['plugins'] ?? [] as $plugin) {
            $priority = $this->assess_update_priority($plugin);
            $plan[$priority][] = 'plugins';
            break; // Group all plugin updates together
        }
        
        // Theme updates (lowest priority)
        if (!empty($updates['themes'])) {
            $plan['low'][] = 'themes';
        }
        
        return $plan;
    }
    
    /**
     * Assess update priority based on security and compatibility factors
     */
    private function assess_update_priority($update) {
        if ($update['security_update'] ?? false) {
            return 'critical';
        }
        
        if ($update['compatibility_issues'] ?? false) {
            return 'low';
        }
        
        return 'medium';
    }
    
    /**
     * Verify site health after updates
     */
    private function verify_post_update_health($site_id, $domain) {
        $this->log_automation("Verifying post-update health for {$domain}", $site_id);
        
        // Wait for updates to settle
        sleep(10);
        
        // Check site accessibility
        $response = wp_remote_get('https://' . $domain, ['timeout' => 30]);
        
        if (is_wp_error($response)) {
            $this->log_automation("Site accessibility check failed: " . $response->get_error_message(), $site_id);
            $this->create_alert($site_id, 'site_down', 'Site may be down after updates', 'error');
            return;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $this->log_automation("Site returned HTTP {$code}", $site_id);
            $this->create_alert($site_id, 'site_error', "Site returned HTTP {$code} after updates", 'warning');
        } else {
            $this->log_automation("Site accessibility verified", $site_id);
        }
        
        // Schedule performance check
        wp_schedule_single_event(time() + 300, 'rphub_post_update_performance_check', [$site_id, $domain]);
    }
    
    /**
     * Verify backup health and create if needed
     */
    private function verify_backup_health($site_id, $domain) {
        $this->log_automation("Verifying backup health for {$domain}", $site_id);
        
        $backup_health = $this->backuply->get_backup_health($domain);
        
        if (is_wp_error($backup_health)) {
            $this->log_automation("Failed to get backup health: " . $backup_health->get_error_message(), $site_id);
            return;
        }
        
        if ($backup_health['status'] !== 'good') {
            $this->log_automation("Backup health issues detected, creating new backup", $site_id);
            
            $backup = $this->backuply->create_backup($domain, [
                'type' => 'full',
                'description' => 'Automated backup - Health maintenance'
            ]);
            
            if (is_wp_error($backup)) {
                $this->log_automation("Backup creation failed: " . $backup->get_error_message(), $site_id);
                $this->create_alert($site_id, 'backup_failed', 'Automated backup creation failed', 'error');
            } else {
                $this->log_automation("Backup created successfully", $site_id);
            }
        } else {
            $this->log_automation("Backup health is good", $site_id);
        }
    }
    
    /**
     * Perform performance optimization
     */
    private function perform_performance_optimization($site_id, $domain) {
        $this->log_automation("Starting performance optimization for {$domain}", $site_id);
        
        // Analyze current performance
        $pagespeed = $this->pagespeed->analyze_page('https://' . $domain, 'mobile');
        
        if (is_wp_error($pagespeed)) {
            $this->log_automation("PageSpeed analysis failed: " . $pagespeed->get_error_message(), $site_id);
            return;
        }
        
        $opportunities = $pagespeed['opportunities'] ?? [];
        
        // Cloudflare optimizations
        $this->optimize_cloudflare_settings($site_id, $domain, $opportunities);
        
        // WP Toolkit optimizations
        $this->optimize_wordpress_settings($site_id, $domain, $opportunities);
        
        $this->log_automation("Performance optimization completed", $site_id);
    }
    
    /**
     * Optimize Cloudflare settings based on performance data
     */
    private function optimize_cloudflare_settings($site_id, $domain, $opportunities) {
        $this->log_automation("Optimizing Cloudflare settings for {$domain}", $site_id);
        
        // Purge cache if cache-related issues found
        $cache_issues = array_filter($opportunities, function($opp) {
            return strpos(strtolower($opp['title'] ?? ''), 'cache') !== false;
        });
        
        if (!empty($cache_issues)) {
            $purge_result = $this->cloudflare->purge_cache($domain, ['purge_everything' => true]);
            
            if (!is_wp_error($purge_result)) {
                $this->log_automation("Cache purged successfully", $site_id);
            }
        }
        
        // Get current Cloudflare analytics
        $cf_analytics = $this->cloudflare->get_analytics($domain, '24h');
        
        if (!is_wp_error($cf_analytics) && $cf_analytics['cache_hit_ratio'] < 80) {
            $this->log_automation("Low cache hit ratio detected: {$cf_analytics['cache_hit_ratio']}%", $site_id);
            $this->create_alert($site_id, 'low_cache_ratio', 'Low Cloudflare cache hit ratio', 'warning');
        }
    }
    
    /**
     * Optimize WordPress settings through WP Toolkit
     */
    private function optimize_wordpress_settings($site_id, $domain, $opportunities) {
        $this->log_automation("Optimizing WordPress settings for {$domain}", $site_id);
        
        // Analyze opportunities and apply automated fixes
        foreach ($opportunities as $opportunity) {
            $title = strtolower($opportunity['title'] ?? '');
            
            if (strpos($title, 'image') !== false) {
                $this->log_automation("Image optimization opportunity detected", $site_id);
                // Could integrate with image optimization plugins
            }
            
            if (strpos($title, 'javascript') !== false || strpos($title, 'css') !== false) {
                $this->log_automation("Asset optimization opportunity detected", $site_id);
                // Could enable caching/minification plugins
            }
        }
    }
    
    /**
     * Enhance security based on threat analysis
     */
    private function enhance_security($site_id, $domain) {
        $this->log_automation("Enhancing security for {$domain}", $site_id);
        
        // Get recent security events
        $security_events = $this->cloudflare->get_security_events($domain, 50);
        
        if (!is_wp_error($security_events)) {
            $threat_count = $security_events['total_events'] ?? 0;
            
            if ($threat_count > 10) {
                $this->log_automation("High threat activity detected: {$threat_count} events", $site_id);
                
                // Increase security level temporarily
                $security_update = $this->cloudflare->update_security_level($domain, 'high');
                
                if (!is_wp_error($security_update)) {
                    $this->log_automation("Security level increased to HIGH", $site_id);
                    
                    // Schedule security level reduction
                    wp_schedule_single_event(time() + (24 * HOUR_IN_SECONDS), 'rphub_reduce_security_level', [$domain]);
                }
            }
        }
        
        // Check SSL status
        $ssl_status = $this->cloudflare->get_ssl_status($domain);
        
        if (!is_wp_error($ssl_status) && $ssl_status['ssl_mode'] !== 'full_strict') {
            $this->log_automation("SSL not in strict mode, creating recommendation", $site_id);
            $this->create_alert($site_id, 'ssl_not_strict', 'SSL not in strict mode', 'warning');
        }
    }
    
    /**
     * Generate intelligent recommendations based on analysis
     */
    private function generate_intelligent_recommendations($site_id, $domain, $health) {
        $this->log_automation("Generating intelligent recommendations for {$domain}", $site_id);
        
        global $wpdb;
        $table_recommendations = $wpdb->prefix . 'rphub_intelligent_recommendations';
        
        $recommendations = [];
        
        // Security recommendations
        if ($health['security_score'] < 80) {
            $recommendations[] = [
                'type' => 'security',
                'priority' => $health['security_score'] < 60 ? 'high' : 'medium',
                'title' => 'Mejorar seguridad del sitio',
                'description' => 'Se detectaron vulnerabilidades o configuraciones de seguridad subóptimas',
                'automated_action' => 'security_scan_and_update',
                'estimated_impact' => 'high'
            ];
        }
        
        // Performance recommendations
        if ($health['performance_score'] < 70) {
            $recommendations[] = [
                'type' => 'performance',
                'priority' => $health['performance_score'] < 50 ? 'high' : 'medium',
                'title' => 'Optimizar rendimiento',
                'description' => 'El sitio tiene oportunidades de mejora en velocidad de carga',
                'automated_action' => 'performance_optimization',
                'estimated_impact' => 'medium'
            ];
        }
        
        // Backup recommendations
        if ($health['backup_score'] < 80) {
            $recommendations[] = [
                'type' => 'backup',
                'priority' => $health['backup_score'] < 50 ? 'high' : 'medium',
                'title' => 'Revisar sistema de backups',
                'description' => 'El sistema de backups requiere atención',
                'automated_action' => 'backup_health_check',
                'estimated_impact' => 'high'
            ];
        }
        
        // Store recommendations
        $table_recommendations = $wpdb->prefix . 'rphub_intelligent_recommendations';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_recommendations'") === $table_recommendations;
        
        if ($table_exists) {
            foreach ($recommendations as $rec) {
                $result = $wpdb->insert($table_recommendations, [
                    'site_id' => $site_id,
                    'type' => $rec['type'],
                    'priority' => $rec['priority'],
                    'title' => $rec['title'],
                    'description' => $rec['description'],
                    'automated_action' => $rec['automated_action'],
                    'estimated_impact' => $rec['estimated_impact'],
                    'status' => 'pending',
                    'created_at' => current_time('mysql')
                ]);
                
                if ($result === false) {
                    rphub_log_db_error("INSERT INTO $table_recommendations", $wpdb->last_error, [
                        'site_id' => $site_id,
                        'recommendation' => $rec
                    ]);
                }
            }
        } else {
            // Log recommendations to error manager if table doesn't exist
            foreach ($recommendations as $rec) {
                rphub_error_manager()->log_error(
                    sprintf('[Recommendation] %s: %s (%s priority)', $rec['title'], $rec['description'], $rec['priority']),
                    ReplantaHub_Error_Manager::LEVEL_INFO,
                    ReplantaHub_Error_Manager::TYPE_INTEGRATION_ERROR,
                    array_merge($rec, ['site_id' => $site_id, 'table_missing' => $table_recommendations])
                );
            }
        }
        
        $this->log_automation("Generated " . count($recommendations) . " intelligent recommendations", $site_id);
    }
    
    /**
     * Security monitoring routine
     */
    public function security_monitoring_routine() {
        $this->log_automation('Starting security monitoring routine');
        
        global $wpdb;
        $table_sites = $wpdb->prefix . 'rphub_sites';
        
        $sites = $wpdb->get_results("
            SELECT * FROM $table_sites 
            WHERE status = 'active' AND security_monitoring = 1
            LIMIT 20
        ", ARRAY_A);
        
        foreach ($sites as $site) {
            $this->monitor_site_security($site);
        }
        
        $this->log_automation('Completed security monitoring for ' . count($sites) . ' sites');
    }
    
    /**
     * Monitor individual site security
     */
    private function monitor_site_security($site) {
        $domain = parse_url($site['url'], PHP_URL_HOST);
        $site_id = $site['id'];
        
        // Check for new security events
        $security_events = $this->cloudflare->get_security_events($domain, 10);
        
        if (!is_wp_error($security_events)) {
            $recent_events = array_filter($security_events['events'] ?? [], function($event) {
                return strtotime($event['occurred_at']) > (time() - HOUR_IN_SECONDS);
            });
            
            if (count($recent_events) > 5) {
                $this->create_alert($site_id, 'high_security_activity', 
                    'High security activity detected: ' . count($recent_events) . ' events in last hour', 
                    'warning'
                );
            }
        }
        
        // Check SSL certificate status
        $ssl_status = $this->cloudflare->get_ssl_status($domain);
        
        if (!is_wp_error($ssl_status)) {
            foreach ($ssl_status['certificates'] ?? [] as $cert) {
                if (isset($cert['validity_days']) && $cert['validity_days'] < 30) {
                    $this->create_alert($site_id, 'ssl_expiring', 
                        'SSL certificate expires in ' . $cert['validity_days'] . ' days', 
                        'warning'
                    );
                }
            }
        }
    }
    
    /**
     * Performance optimization routine
     */
    public function performance_optimization_routine() {
        $this->log_automation('Starting performance optimization routine');
        
        global $wpdb;
        $table_sites = $wpdb->prefix . 'rphub_sites';
        
        $sites = $wpdb->get_results("
            SELECT * FROM $table_sites 
            WHERE status = 'active' AND performance_optimization = 1
            ORDER BY last_performance_check ASC
            LIMIT 5
        ", ARRAY_A);
        
        foreach ($sites as $site) {
            $this->optimize_site_performance($site);
            
            $wpdb->update($table_sites, 
                ['last_performance_check' => current_time('mysql')], 
                ['id' => $site['id']]
            );
        }
        
        $this->log_automation('Completed performance optimization for ' . count($sites) . ' sites');
    }
    
    /**
     * Optimize individual site performance
     */
    private function optimize_site_performance($site) {
        $domain = parse_url($site['url'], PHP_URL_HOST);
        $site_id = $site['id'];
        
        // Run PageSpeed analysis
        $pagespeed = $this->pagespeed->analyze_page($site['url'], 'mobile');
        
        if (!is_wp_error($pagespeed)) {
            $performance_score = $pagespeed['scores']['performance']['score'] ?? 0;
            
            if ($performance_score < 70) {
                $this->log_automation("Low performance score detected: {$performance_score}", $site_id);
                
                // Automatic optimizations
                $this->perform_automatic_optimizations($site_id, $domain, $pagespeed);
            }
        }
    }
    
    /**
     * Perform automatic optimizations
     */
    private function perform_automatic_optimizations($site_id, $domain, $pagespeed_data) {
        $opportunities = $pagespeed_data['opportunities'] ?? [];
        
        foreach ($opportunities as $opportunity) {
            $savings = $opportunity['potential_savings']['time'] ?? 0;
            
            if ($savings > 500) { // More than 500ms potential savings
                $this->apply_optimization($site_id, $domain, $opportunity);
            }
        }
    }
    
    /**
     * Apply specific optimization
     */
    private function apply_optimization($site_id, $domain, $opportunity) {
        $optimization_type = $opportunity['id'] ?? '';
        
        switch ($optimization_type) {
            case 'unused-css-rules':
            case 'unused-javascript':
                $this->log_automation("Detected unused assets optimization opportunity", $site_id);
                break;
                
            case 'render-blocking-resources':
                $this->log_automation("Detected render-blocking resources", $site_id);
                break;
                
            case 'optimize-images':
                $this->log_automation("Detected image optimization opportunity", $site_id);
                break;
        }
    }
    
    /**
     * Backup management routine
     */
    public function backup_management_routine() {
        $this->log_automation('Starting backup management routine');
        
        global $wpdb;
        $table_sites = $wpdb->prefix . 'rphub_sites';
        
        $sites = $wpdb->get_results("
            SELECT * FROM $table_sites 
            WHERE status = 'active' AND auto_backups = 1
        ", ARRAY_A);
        
        foreach ($sites as $site) {
            $this->manage_site_backups($site);
        }
        
        $this->log_automation('Completed backup management for ' . count($sites) . ' sites');
    }
    
    /**
     * Manage backups for individual site
     */
    private function manage_site_backups($site) {
        $domain = parse_url($site['url'], PHP_URL_HOST);
        $site_id = $site['id'];
        
        // Check backup health
        $backup_health = $this->backuply->get_backup_health($domain);
        
        if (is_wp_error($backup_health)) {
            $this->log_automation("Failed to check backup health: " . $backup_health->get_error_message(), $site_id);
            return;
        }
        
        if ($backup_health['status'] !== 'good') {
            $this->log_automation("Backup health issues detected, taking corrective action", $site_id);
            
            // Create immediate backup
            $backup = $this->backuply->create_backup($domain, [
                'type' => 'full',
                'description' => 'Emergency backup - Health issue detected'
            ]);
            
            if (is_wp_error($backup)) {
                $this->create_alert($site_id, 'backup_emergency_failed', 
                    'Emergency backup creation failed', 'error');
            }
        }
        
        // Cleanup old backups
        $this->cleanup_old_backups($site_id, $domain);
    }
    
    /**
     * Cleanup old backups based on retention policy
     */
    private function cleanup_old_backups($site_id, $domain) {
        $backups = $this->backuply->list_backups($domain, 100);
        
        if (is_wp_error($backups)) {
            return;
        }
        
        $retention_days = 30; // Default retention
        $cutoff_date = time() - ($retention_days * DAY_IN_SECONDS);
        
        $deleted_count = 0;
        
        foreach ($backups as $backup) {
            if (strtotime($backup['created_at']) < $cutoff_date) {
                $delete_result = $this->backuply->delete_backup($domain, $backup['backup_id']);
                
                if (!is_wp_error($delete_result)) {
                    $deleted_count++;
                }
            }
        }
        
        if ($deleted_count > 0) {
            $this->log_automation("Cleaned up {$deleted_count} old backups", $site_id);
        }
    }
    
    /**
     * Health assessment routine
     */
    public function health_assessment_routine() {
        $this->log_automation('Starting health assessment routine');
        
        global $wpdb;
        $table_sites = $wpdb->prefix . 'rphub_sites';
        
        $sites = $wpdb->get_results("
            SELECT * FROM $table_sites 
            WHERE status = 'active'
            ORDER BY last_health_check ASC
            LIMIT 15
        ", ARRAY_A);
        
        foreach ($sites as $site) {
            $health = $this->assess_site_health($site['id'], parse_url($site['url'], PHP_URL_HOST));
            
            // Store health assessment
            $this->store_health_assessment($site['id'], $health);
            
            // Create alerts for critical issues
            if ($health['overall_score'] < 60) {
                $this->create_alert($site['id'], 'poor_health', 
                    'Site health score is critically low: ' . $health['overall_score'], 'error');
            }
            
            $wpdb->update($table_sites, 
                ['last_health_check' => current_time('mysql')], 
                ['id' => $site['id']]
            );
        }
        
        $this->log_automation('Completed health assessment for ' . count($sites) . ' sites');
    }
    
    /**
     * Store health assessment results
     */
    private function store_health_assessment($site_id, $health) {
        global $wpdb;
        $table_health = $wpdb->prefix . 'rphub_health_assessments';
        
        // Check if health assessments table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_health'") === $table_health;
        
        if ($table_exists) {
            $result = $wpdb->insert($table_health, [
                'site_id' => $site_id,
                'security_score' => $health['security_score'],
                'performance_score' => $health['performance_score'],
                'maintenance_score' => $health['maintenance_score'],
                'backup_score' => $health['backup_score'],
                'overall_score' => $health['overall_score'],
                'assessment_data' => json_encode($health),
                'created_at' => current_time('mysql')
            ]);
            
            if ($result === false) {
                rphub_log_db_error("INSERT INTO $table_health", $wpdb->last_error, [
                    'site_id' => $site_id,
                    'health_data' => $health
                ]);
            }
        } else {
            // Log health assessment to error manager if table doesn't exist
            rphub_error_manager()->log_error(
                sprintf('Health Assessment - Site %d: Overall Score %d (Security: %d, Performance: %d, Maintenance: %d, Backup: %d)',
                    $site_id, $health['overall_score'], $health['security_score'], 
                    $health['performance_score'], $health['maintenance_score'], $health['backup_score']
                ),
                ReplantaHub_Error_Manager::LEVEL_INFO,
                ReplantaHub_Error_Manager::TYPE_INTEGRATION_ERROR,
                array_merge($health, ['site_id' => $site_id, 'table_missing' => $table_health])
            );
        }
    }
    
    /**
     * Create alert notification
     */
    private function create_alert($site_id, $type, $message, $severity = 'info') {
        global $wpdb;
        $table_notifications = $wpdb->prefix . 'rphub_notifications';
        
        // Check if notifications table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_notifications'") === $table_notifications;
        
        if ($table_exists) {
            $result = $wpdb->insert($table_notifications, [
                'site_id' => $site_id,
                'type' => $type,
                'severity' => $severity,
                'title' => 'Automated Alert',
                'message' => $message,
                'is_read' => 0,
                'created_at' => current_time('mysql')
            ]);
            
            if ($result === false) {
                rphub_log_db_error("INSERT INTO $table_notifications", $wpdb->last_error, [
                    'site_id' => $site_id,
                    'type' => $type,
                    'message' => $message
                ]);
            }
        } else {
            // Use error manager if notifications table doesn't exist
            rphub_error_manager()->log_error(
                sprintf('[Alert] %s: %s', $type, $message),
                $severity === 'error' ? ReplantaHub_Error_Manager::LEVEL_ERROR : ReplantaHub_Error_Manager::LEVEL_WARNING,
                ReplantaHub_Error_Manager::TYPE_INTEGRATION_ERROR,
                [
                    'site_id' => $site_id,
                    'alert_type' => $type,
                    'severity' => $severity,
                    'table_missing' => $table_notifications
                ]
            );
        }
        
        $this->log_automation("Alert created: {$type} - {$message}", $site_id);
    }
    
    /**
     * Log automation activity
     */
    private function log_automation($message, $site_id = null) {
        global $wpdb;
        $table_logs = $wpdb->prefix . 'rphub_automation_logs';
        
        // Check if table exists before trying to insert
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_logs'") === $table_logs;
        
        if ($table_exists) {
            $result = $wpdb->insert($table_logs, [
                'site_id' => $site_id,
                'message' => $message,
                'timestamp' => current_time('mysql')
            ]);
            
            if ($result === false) {
                rphub_log_db_error("INSERT INTO $table_logs", $wpdb->last_error, [
                    'site_id' => $site_id,
                    'message' => $message
                ]);
            }
        } else {
            // If automation logs table doesn't exist, use error manager instead
            rphub_error_manager()->log_error(
                '[Automation] ' . $message,
                ReplantaHub_Error_Manager::LEVEL_INFO,
                ReplantaHub_Error_Manager::TYPE_INTEGRATION_ERROR,
                ['site_id' => $site_id, 'table_missing' => $table_logs]
            );
        }
        
        // Also log to WordPress error log for debugging
        if (defined('RPHUB_DEBUG') && RPHUB_DEBUG) {
            error_log('[Replanta Automation] ' . ($site_id ? "Site $site_id: " : '') . $message);
        }
    }
    
    /**
     * Get automation status for a site
     */
    public function get_automation_status($site_id) {
        global $wpdb;
        $table_sites = $wpdb->prefix . 'rphub_sites';
        
        $site = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_sites WHERE id = %d", $site_id), ARRAY_A);
        
        if (!$site) {
            return new WP_Error('site_not_found', 'Site not found');
        }
        
        return [
            'automation_enabled' => $site['automation_enabled'] ?? 0,
            'auto_updates' => $site['auto_updates'] ?? 0,
            'auto_backups' => $site['auto_backups'] ?? 0,
            'security_monitoring' => $site['security_monitoring'] ?? 0,
            'performance_optimization' => $site['performance_optimization'] ?? 0,
            'last_maintenance_check' => $site['last_maintenance_check'] ?? null,
            'last_health_check' => $site['last_health_check'] ?? null,
            'last_performance_check' => $site['last_performance_check'] ?? null
        ];
    }
    
    /**
     * AJAX Handlers
     */
    public function ajax_enable_automation() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $site_id = $_POST['site_id'] ?? 0;
        $enabled = $_POST['enabled'] ?? 0;
        
        if (!$site_id) {
            wp_send_json_error('Site ID required');
        }
        
        global $wpdb;
        $table_sites = $wpdb->prefix . 'rphub_sites';
        
        $result = $wpdb->update($table_sites, 
            ['automation_enabled' => $enabled], 
            ['id' => $site_id]
        );
        
        if ($result === false) {
            wp_send_json_error('Failed to update automation status');
        }
        
        wp_send_json_success(['automation_enabled' => $enabled]);
    }
    
    public function ajax_configure_automation() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $site_id = $_POST['site_id'] ?? 0;
        $config = $_POST['config'] ?? [];
        
        if (!$site_id) {
            wp_send_json_error('Site ID required');
        }
        
        global $wpdb;
        $table_sites = $wpdb->prefix . 'rphub_sites';
        
        $update_data = [];
        if (isset($config['auto_updates'])) $update_data['auto_updates'] = $config['auto_updates'];
        if (isset($config['auto_backups'])) $update_data['auto_backups'] = $config['auto_backups'];
        if (isset($config['security_monitoring'])) $update_data['security_monitoring'] = $config['security_monitoring'];
        if (isset($config['performance_optimization'])) $update_data['performance_optimization'] = $config['performance_optimization'];
        
        $result = $wpdb->update($table_sites, $update_data, ['id' => $site_id]);
        
        if ($result === false) {
            wp_send_json_error('Failed to update configuration');
        }
        
        wp_send_json_success($config);
    }
    
    public function ajax_get_automation_status() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $site_id = $_POST['site_id'] ?? 0;
        
        if (!$site_id) {
            wp_send_json_error('Site ID required');
        }
        
        $status = $this->get_automation_status($site_id);
        
        if (is_wp_error($status)) {
            wp_send_json_error($status->get_error_message());
        }
        
        wp_send_json_success($status);
    }
    
    public function ajax_get_automation_logs() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $site_id = $_POST['site_id'] ?? 0;
        $limit = $_POST['limit'] ?? 50;
        
        global $wpdb;
        $table_logs = $wpdb->prefix . 'rphub_automation_logs';
        
        $where_clause = $site_id ? $wpdb->prepare("WHERE site_id = %d", $site_id) : "";
        
        $logs = $wpdb->get_results("
            SELECT * FROM $table_logs 
            {$where_clause}
            ORDER BY timestamp DESC 
            LIMIT {$limit}
        ", ARRAY_A);
        
        wp_send_json_success($logs);
    }
    
    public function ajax_run_maintenance_check() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $site_id = $_POST['site_id'] ?? 0;
        
        if (!$site_id) {
            wp_send_json_error('Site ID required');
        }
        
        global $wpdb;
        $table_sites = $wpdb->prefix . 'rphub_sites';
        
        $site = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_sites WHERE id = %d", $site_id), ARRAY_A);
        
        if (!$site) {
            wp_send_json_error('Site not found');
        }
        
        $this->perform_intelligent_maintenance($site);
        
        wp_send_json_success(['message' => 'Maintenance check completed']);
    }
}

// Initialize Automation system
function rphub_automation_init() {
    return new ReplantaHub_Automation_System();
}
add_action('plugins_loaded', 'rphub_automation_init');
