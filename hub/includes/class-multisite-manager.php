<?php
/**
 * Multi-site Management System
 * Handles centralized control and bulk operations across multiple sites
 * 
 * @package ReplantaHub
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPHUB_Multisite_Manager {
    
    private $api_system;
    private $automation_workflows;
    private $intelligent_maintenance;
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_ajax_rphub_multisite_bulk_action', array($this, 'handle_bulk_action'));
        add_action('wp_ajax_rphub_multisite_sync_sites', array($this, 'sync_sites'));
        add_action('wp_ajax_rphub_multisite_create_group', array($this, 'create_site_group'));
        add_action('wp_ajax_rphub_multisite_get_network_analytics', array($this, 'get_network_analytics'));
        add_action('wp_ajax_rphub_multisite_deploy_config', array($this, 'deploy_configuration'));
    }
    
    public function init() {
        // Initialize class instances only if they exist
        if (class_exists('ReplantaHub_API_System')) {
            $this->api_system = new ReplantaHub_API_System();
        }
        
        if (class_exists('ReplantaHub_Automation_Workflows')) {
            $this->automation_workflows = new ReplantaHub_Automation_Workflows();
        }
        
        if (class_exists('ReplantaHub_Intelligent_Maintenance')) {
            $this->intelligent_maintenance = new ReplantaHub_Intelligent_Maintenance();
        }
        
        // Register multisite-specific hooks
        add_action('rphub_hourly_multisite_sync', array($this, 'sync_all_sites'));
        add_action('rphub_daily_network_report', array($this, 'generate_network_report'));
        
        // Schedule multisite tasks
        RPHUB_Scheduler::schedule('rphub_hourly_multisite_sync', 'hourly');
        RPHUB_Scheduler::schedule('rphub_daily_network_report',  'daily');
    }
    
    /**
     * Create site groups for organized management
     */
    public function create_site_group() {
        check_ajax_referer('rphub_multisite_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $group_name = sanitize_text_field($_POST['group_name']);
        $group_description = sanitize_textarea_field($_POST['group_description']);
        $site_ids = array_map('intval', $_POST['site_ids'] ?? []);
        $group_config = json_decode(stripslashes($_POST['group_config']), true);
        
        global $wpdb;
        
        // Create site group
        $result = $wpdb->insert(
            $wpdb->prefix . 'rphub_site_groups',
            array(
                'group_name' => $group_name,
                'group_description' => $group_description,
                'group_config' => wp_json_encode($group_config),
                'created_at' => current_time('mysql'),
                'created_by' => get_current_user_id(),
                'status' => 'active'
            ),
            array('%s', '%s', '%s', '%s', '%d', '%s')
        );
        
        if ($result === false) {
            wp_send_json_error('Failed to create site group');
        }
        
        $group_id = $wpdb->insert_id;
        
        // Assign sites to group
        foreach ($site_ids as $site_id) {
            $wpdb->insert(
                $wpdb->prefix . 'rphub_site_group_members',
                array(
                    'group_id' => $group_id,
                    'site_id' => $site_id,
                    'assigned_at' => current_time('mysql'),
                    'assigned_by' => get_current_user_id()
                ),
                array('%d', '%d', '%s', '%d')
            );
        }
        
        wp_send_json_success(array(
            'group_id' => $group_id,
            'message' => 'Site group created successfully',
            'sites_assigned' => count($site_ids)
        ));
    }
    
    /**
     * Perform bulk actions across multiple sites
     */
    public function handle_bulk_action() {
        check_ajax_referer('rphub_multisite_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $action = sanitize_text_field($_POST['action_type']);
        $site_ids = array_map('intval', $_POST['site_ids'] ?? []);
        $action_config = json_decode(stripslashes($_POST['action_config']), true);
        
        $results = array();
        $success_count = 0;
        $error_count = 0;
        
        foreach ($site_ids as $site_id) {
            $result = $this->execute_bulk_action($site_id, $action, $action_config);
            $results[$site_id] = $result;
            
            if ($result['success']) {
                $success_count++;
            } else {
                $error_count++;
            }
            
            // Log bulk action
            $this->log_bulk_action($site_id, $action, $result);
        }
        
        wp_send_json_success(array(
            'results' => $results,
            'summary' => array(
                'total' => count($site_ids),
                'success' => $success_count,
                'errors' => $error_count
            )
        ));
    }
    
    /**
     * Execute specific bulk action on a site
     */
    private function execute_bulk_action($site_id, $action, $config) {
        $site_data = $this->get_site_data($site_id);
        
        if (!$site_data) {
            return array('success' => false, 'message' => 'Site not found');
        }
        
        switch ($action) {
            case 'update_plugins':
                return $this->bulk_update_plugins($site_data, $config);
                
            case 'update_themes':
                return $this->bulk_update_themes($site_data, $config);
                
            case 'backup_site':
                return $this->bulk_backup_site($site_data, $config);
                
            case 'optimize_database':
                return $this->bulk_optimize_database($site_data, $config);
                
            case 'security_scan':
                return $this->bulk_security_scan($site_data, $config);
                
            case 'performance_optimization':
                return $this->bulk_performance_optimization($site_data, $config);
                
            case 'content_sync':
                return $this->bulk_content_sync($site_data, $config);
                
            case 'settings_deploy':
                return $this->bulk_settings_deploy($site_data, $config);
                
            default:
                return array('success' => false, 'message' => 'Unknown action');
        }
    }
    
    /**
     * Bulk plugin updates
     */
    private function bulk_update_plugins($site_data, $config) {
        try {
            $api_url = trailingslashit($site_data['site_url']) . 'wp-json/rphub/v1/plugins/update';
            $api_key = $this->get_site_api_key($site_data['id']);
            
            $response = wp_remote_post($api_url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => wp_json_encode($config),
                'timeout' => 60
            ));
            
            if (is_wp_error($response)) {
                return array('success' => false, 'message' => $response->get_error_message());
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            return array(
                'success' => true,
                'message' => 'Plugins updated successfully',
                'data' => $data
            );
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }
    }
    
    /**
     * Bulk theme updates
     */
    private function bulk_update_themes($site_data, $config) {
        try {
            $api_url = trailingslashit($site_data['site_url']) . 'wp-json/rphub/v1/themes/update';
            $api_key = $this->get_site_api_key($site_data['id']);
            
            $response = wp_remote_post($api_url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => wp_json_encode($config),
                'timeout' => 60
            ));
            
            if (is_wp_error($response)) {
                return array('success' => false, 'message' => $response->get_error_message());
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            return array(
                'success' => true,
                'message' => 'Themes updated successfully',
                'data' => $data
            );
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }
    }
    
    /**
     * Bulk site backup
     */
    private function bulk_backup_site($site_data, $config) {
        try {
            $api_url = trailingslashit($site_data['site_url']) . 'wp-json/rphub/v1/backup/create';
            $api_key = $this->get_site_api_key($site_data['id']);
            
            $backup_config = array(
                'include_database' => $config['include_database'] ?? true,
                'include_files' => $config['include_files'] ?? true,
                'include_uploads' => $config['include_uploads'] ?? true,
                'backup_name' => $config['backup_name'] ?? 'multisite_backup_' . date('Y-m-d_H-i-s')
            );
            
            $response = wp_remote_post($api_url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => wp_json_encode($backup_config),
                'timeout' => 300
            ));
            
            if (is_wp_error($response)) {
                return array('success' => false, 'message' => $response->get_error_message());
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            return array(
                'success' => true,
                'message' => 'Backup created successfully',
                'data' => $data
            );
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }
    }
    
    /**
     * Bulk database optimization
     */
    private function bulk_optimize_database($site_data, $config) {
        try {
            $api_url = trailingslashit($site_data['site_url']) . 'wp-json/rphub/v1/maintenance/optimize-database';
            $api_key = $this->get_site_api_key($site_data['id']);
            
            $response = wp_remote_post($api_url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => wp_json_encode($config),
                'timeout' => 120
            ));
            
            if (is_wp_error($response)) {
                return array('success' => false, 'message' => $response->get_error_message());
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            return array(
                'success' => true,
                'message' => 'Database optimized successfully',
                'data' => $data
            );
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }
    }
    
    /**
     * Bulk security scan
     */
    private function bulk_security_scan($site_data, $config) {
        try {
            $api_url = trailingslashit($site_data['site_url']) . 'wp-json/rphub/v1/security/scan';
            $api_key = $this->get_site_api_key($site_data['id']);
            
            $scan_config = array(
                'deep_scan' => $config['deep_scan'] ?? false,
                'scan_files' => $config['scan_files'] ?? true,
                'scan_database' => $config['scan_database'] ?? true,
                'scan_permissions' => $config['scan_permissions'] ?? true
            );
            
            $response = wp_remote_post($api_url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => wp_json_encode($scan_config),
                'timeout' => 180
            ));
            
            if (is_wp_error($response)) {
                return array('success' => false, 'message' => $response->get_error_message());
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            return array(
                'success' => true,
                'message' => 'Security scan completed',
                'data' => $data
            );
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }
    }
    
    /**
     * Bulk performance optimization
     */
    private function bulk_performance_optimization($site_data, $config) {
        try {
            $optimization_tasks = array(
                'clear_cache' => $config['clear_cache'] ?? true,
                'optimize_images' => $config['optimize_images'] ?? false,
                'minify_assets' => $config['minify_assets'] ?? false,
                'database_cleanup' => $config['database_cleanup'] ?? true,
                'preload_cache' => $config['preload_cache'] ?? false
            );
            
            $results = array();
            
            foreach ($optimization_tasks as $task => $enabled) {
                if (!$enabled) continue;
                
                $api_url = trailingslashit($site_data['site_url']) . 'wp-json/rphub/v1/optimization/' . $task;
                $api_key = $this->get_site_api_key($site_data['id']);
                
                $response = wp_remote_post($api_url, array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type' => 'application/json'
                    ),
                    'body' => wp_json_encode($config),
                    'timeout' => 120
                ));
                
                if (!is_wp_error($response)) {
                    $body = wp_remote_retrieve_body($response);
                    $data = json_decode($body, true);
                    $results[$task] = $data;
                }
            }
            
            return array(
                'success' => true,
                'message' => 'Performance optimization completed',
                'data' => $results
            );
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }
    }
    
    /**
     * Bulk content synchronization
     */
    private function bulk_content_sync($site_data, $config) {
        try {
            $sync_config = array(
                'sync_posts' => $config['sync_posts'] ?? false,
                'sync_pages' => $config['sync_pages'] ?? false,
                'sync_media' => $config['sync_media'] ?? false,
                'sync_settings' => $config['sync_settings'] ?? false,
                'source_site_id' => $config['source_site_id'] ?? null
            );
            
            if (!$sync_config['source_site_id']) {
                return array('success' => false, 'message' => 'Source site ID is required');
            }
            
            $api_url = trailingslashit($site_data['site_url']) . 'wp-json/rphub/v1/content/sync';
            $api_key = $this->get_site_api_key($site_data['id']);
            
            $response = wp_remote_post($api_url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => wp_json_encode($sync_config),
                'timeout' => 300
            ));
            
            if (is_wp_error($response)) {
                return array('success' => false, 'message' => $response->get_error_message());
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            return array(
                'success' => true,
                'message' => 'Content synchronized successfully',
                'data' => $data
            );
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }
    }
    
    /**
     * Bulk settings deployment
     */
    private function bulk_settings_deploy($site_data, $config) {
        try {
            $api_url = trailingslashit($site_data['site_url']) . 'wp-json/rphub/v1/settings/deploy';
            $api_key = $this->get_site_api_key($site_data['id']);
            
            $response = wp_remote_post($api_url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => wp_json_encode($config),
                'timeout' => 60
            ));
            
            if (is_wp_error($response)) {
                return array('success' => false, 'message' => $response->get_error_message());
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            return array(
                'success' => true,
                'message' => 'Settings deployed successfully',
                'data' => $data
            );
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }
    }
    
    /**
     * Synchronize all managed sites
     */
    public function sync_all_sites() {
        global $wpdb;
        
        $sites = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}rphub_websites 
             WHERE status = 'active' 
             ORDER BY last_sync ASC"
        );
        
        foreach ($sites as $site) {
            $this->sync_single_site($site->id);
            
            // Prevent timeout by adding small delay
            usleep(100000); // 0.1 second
        }
        
        // Update sync timestamp
        update_option('rphub_last_multisite_sync', current_time('mysql'));
        
        return count($sites);
    }
    
    /**
     * Synchronize data for a single site
     */
    private function sync_single_site($site_id) {
        $site_data = $this->get_site_data($site_id);
        
        if (!$site_data) {
            return false;
        }
        
        try {
            $api_url = trailingslashit($site_data['site_url']) . 'wp-json/rphub/v1/system/status';
            $api_key = $this->get_site_api_key($site_id);
            
            $response = wp_remote_get($api_url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key
                ),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                $this->log_sync_error($site_id, $response->get_error_message());
                return false;
            }
            
            $body = wp_remote_retrieve_body($response);
            $status_data = json_decode($body, true);
            
            if ($status_data && isset($status_data['success'])) {
                $this->update_site_status($site_id, $status_data['data']);
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->log_sync_error($site_id, $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get network-wide analytics
     */
    public function get_network_analytics() {
        check_ajax_referer('rphub_multisite_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $date_range = sanitize_text_field($_POST['date_range'] ?? '30days');
        $metrics = array_map('sanitize_text_field', $_POST['metrics'] ?? array());
        $group_ids = array_map('intval', $_POST['group_ids'] ?? array());
        
        global $wpdb;
        
        $analytics_data = array();
        
        // Get sites based on groups or all sites
        if (!empty($group_ids)) {
            $group_placeholders = implode(',', array_fill(0, count($group_ids), '%d'));
            $sites = $wpdb->get_results($wpdb->prepare(
                "SELECT DISTINCT w.* FROM {$wpdb->prefix}rphub_websites w
                 JOIN {$wpdb->prefix}rphub_site_group_members sgm ON w.id = sgm.site_id
                 WHERE sgm.group_id IN ($group_placeholders) AND w.status = 'active'",
                ...$group_ids
            ));
        } else {
            $sites = $wpdb->get_results(
                "SELECT * FROM {$wpdb->prefix}rphub_websites WHERE status = 'active'"
            );
        }
        
        // Collect analytics from each site
        foreach ($sites as $site) {
            $site_analytics = $this->get_site_analytics($site->id, $date_range, $metrics);
            if ($site_analytics) {
                $analytics_data[$site->id] = array(
                    'site_name' => $site->site_name,
                    'site_url' => $site->site_url,
                    'analytics' => $site_analytics
                );
            }
        }
        
        // Calculate network totals and averages
        $network_summary = $this->calculate_network_summary($analytics_data, $metrics);
        
        wp_send_json_success(array(
            'network_summary' => $network_summary,
            'site_analytics' => $analytics_data,
            'total_sites' => count($sites)
        ));
    }
    
    /**
     * Get analytics data for a specific site
     */
    private function get_site_analytics($site_id, $date_range, $metrics) {
        try {
            $site_data = $this->get_site_data($site_id);
            if (!$site_data) return null;
            
            $api_url = trailingslashit($site_data['site_url']) . 'wp-json/rphub/v1/analytics/summary';
            $api_key = $this->get_site_api_key($site_id);
            
            $response = wp_remote_post($api_url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => wp_json_encode(array(
                    'date_range' => $date_range,
                    'metrics' => $metrics
                )),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                return null;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            return $data['data'] ?? null;
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Calculate network-wide summary statistics
     */
    private function calculate_network_summary($analytics_data, $metrics) {
        $summary = array(
            'total_sites' => count($analytics_data),
            'active_sites' => 0,
            'total_pageviews' => 0,
            'total_sessions' => 0,
            'total_users' => 0,
            'average_bounce_rate' => 0,
            'average_session_duration' => 0,
            'top_performing_sites' => array(),
            'performance_distribution' => array()
        );
        
        $bounce_rates = array();
        $session_durations = array();
        $site_performance = array();
        
        foreach ($analytics_data as $site_id => $site_data) {
            $analytics = $site_data['analytics'];
            
            if (isset($analytics['status']) && $analytics['status'] === 'active') {
                $summary['active_sites']++;
            }
            
            if (isset($analytics['pageviews'])) {
                $summary['total_pageviews'] += intval($analytics['pageviews']);
                $site_performance[$site_id] = intval($analytics['pageviews']);
            }
            
            if (isset($analytics['sessions'])) {
                $summary['total_sessions'] += intval($analytics['sessions']);
            }
            
            if (isset($analytics['users'])) {
                $summary['total_users'] += intval($analytics['users']);
            }
            
            if (isset($analytics['bounce_rate'])) {
                $bounce_rates[] = floatval($analytics['bounce_rate']);
            }
            
            if (isset($analytics['avg_session_duration'])) {
                $session_durations[] = floatval($analytics['avg_session_duration']);
            }
        }
        
        // Calculate averages
        if (!empty($bounce_rates)) {
            $summary['average_bounce_rate'] = array_sum($bounce_rates) / count($bounce_rates);
        }
        
        if (!empty($session_durations)) {
            $summary['average_session_duration'] = array_sum($session_durations) / count($session_durations);
        }
        
        // Get top performing sites
        arsort($site_performance);
        $top_sites = array_slice($site_performance, 0, 5, true);
        
        foreach ($top_sites as $site_id => $pageviews) {
            $summary['top_performing_sites'][] = array(
                'site_id' => $site_id,
                'site_name' => $analytics_data[$site_id]['site_name'],
                'pageviews' => $pageviews
            );
        }
        
        return $summary;
    }
    
    /**
     * Generate network-wide report
     */
    public function generate_network_report() {
        $report_data = array(
            'report_date' => current_time('mysql'),
            'total_sites' => 0,
            'active_sites' => 0,
            'sites_with_issues' => 0,
            'total_pageviews' => 0,
            'total_storage_used' => 0,
            'security_incidents' => 0,
            'performance_scores' => array(),
            'top_issues' => array(),
            'recommendations' => array()
        );
        
        global $wpdb;
        
        $sites = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}rphub_websites WHERE status = 'active'"
        );
        
        $report_data['total_sites'] = count($sites);
        
        foreach ($sites as $site) {
            $site_status = $this->get_site_comprehensive_status($site->id);
            
            if ($site_status) {
                if ($site_status['status'] === 'active') {
                    $report_data['active_sites']++;
                }
                
                if (!empty($site_status['issues'])) {
                    $report_data['sites_with_issues']++;
                }
                
                $report_data['total_pageviews'] += $site_status['pageviews'] ?? 0;
                $report_data['total_storage_used'] += $site_status['storage_used'] ?? 0;
                $report_data['security_incidents'] += $site_status['security_incidents'] ?? 0;
                
                if (isset($site_status['performance_score'])) {
                    $report_data['performance_scores'][] = $site_status['performance_score'];
                }
                
                if (!empty($site_status['issues'])) {
                    foreach ($site_status['issues'] as $issue) {
                        $report_data['top_issues'][] = array(
                            'site_id' => $site->id,
                            'site_name' => $site->site_name,
                            'issue' => $issue
                        );
                    }
                }
            }
        }
        
        // Generate recommendations
        $report_data['recommendations'] = $this->generate_network_recommendations($report_data);
        
        // Save report
        $wpdb->insert(
            $wpdb->prefix . 'rphub_network_reports',
            array(
                'report_data' => wp_json_encode($report_data),
                'report_type' => 'daily',
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s')
        );
        
        // Send report email if configured
        $this->send_network_report_email($report_data);
        
        return $report_data;
    }
    
    /**
     * Get comprehensive status for a site
     */
    private function get_site_comprehensive_status($site_id) {
        try {
            $site_data = $this->get_site_data($site_id);
            if (!$site_data) return null;
            
            $api_url = trailingslashit($site_data['site_url']) . 'wp-json/rphub/v1/system/comprehensive-status';
            $api_key = $this->get_site_api_key($site_id);
            
            $response = wp_remote_get($api_url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key
                ),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                return null;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            return $data['data'] ?? null;
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Generate network recommendations
     */
    private function generate_network_recommendations($report_data) {
        $recommendations = array();
        
        // Performance recommendations
        if (!empty($report_data['performance_scores'])) {
            $avg_performance = array_sum($report_data['performance_scores']) / count($report_data['performance_scores']);
            
            if ($avg_performance < 70) {
                $recommendations[] = array(
                    'type' => 'performance',
                    'priority' => 'high',
                    'title' => 'Network Performance Optimization Needed',
                    'description' => 'Average performance score is below 70. Consider implementing caching and optimization.',
                    'action' => 'bulk_performance_optimization'
                );
            }
        }
        
        // Security recommendations
        if ($report_data['security_incidents'] > 0) {
            $recommendations[] = array(
                'type' => 'security',
                'priority' => 'critical',
                'title' => 'Security Incidents Detected',
                'description' => $report_data['security_incidents'] . ' security incidents detected across network.',
                'action' => 'bulk_security_scan'
            );
        }
        
        // Uptime recommendations
        $uptime_percentage = ($report_data['active_sites'] / $report_data['total_sites']) * 100;
        if ($uptime_percentage < 95) {
            $recommendations[] = array(
                'type' => 'uptime',
                'priority' => 'high',
                'title' => 'Network Uptime Below Target',
                'description' => 'Network uptime is ' . round($uptime_percentage, 2) . '%. Investigate inactive sites.',
                'action' => 'investigate_downtime'
            );
        }
        
        // Storage recommendations
        if ($report_data['total_storage_used'] > 50000000000) { // 50GB
            $recommendations[] = array(
                'type' => 'storage',
                'priority' => 'medium',
                'title' => 'High Storage Usage',
                'description' => 'Network using ' . size_format($report_data['total_storage_used']) . ' total storage.',
                'action' => 'storage_optimization'
            );
        }
        
        return $recommendations;
    }
    
    /**
     * Send network report email
     */
    private function send_network_report_email($report_data) {
        $email_settings = get_option('rphub_network_email_settings', array());
        
        if (empty($email_settings['enabled']) || empty($email_settings['recipients'])) {
            return;
        }
        
        $subject = 'ReplantaHub Network Daily Report - ' . date('Y-m-d');
        
        $message = "Network Status Report\n\n";
        $message .= "Total Sites: " . $report_data['total_sites'] . "\n";
        $message .= "Active Sites: " . $report_data['active_sites'] . "\n";
        $message .= "Sites with Issues: " . $report_data['sites_with_issues'] . "\n";
        $message .= "Total Pageviews: " . number_format($report_data['total_pageviews']) . "\n";
        $message .= "Total Storage: " . size_format($report_data['total_storage_used']) . "\n\n";
        
        if (!empty($report_data['recommendations'])) {
            $message .= "Recommendations:\n";
            foreach ($report_data['recommendations'] as $rec) {
                $message .= "- " . $rec['title'] . "\n";
            }
        }
        
        wp_mail($email_settings['recipients'], $subject, $message);
    }
    
    /**
     * Deploy configuration to multiple sites
     */
    public function deploy_configuration() {
        check_ajax_referer('rphub_multisite_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $site_ids = array_map('intval', $_POST['site_ids'] ?? []);
        $config_type = sanitize_text_field($_POST['config_type']);
        $config_data = json_decode(stripslashes($_POST['config_data']), true);
        
        $results = array();
        
        foreach ($site_ids as $site_id) {
            $result = $this->deploy_site_configuration($site_id, $config_type, $config_data);
            $results[$site_id] = $result;
        }
        
        wp_send_json_success($results);
    }
    
    /**
     * Deploy configuration to a specific site
     */
    private function deploy_site_configuration($site_id, $config_type, $config_data) {
        try {
            $site_data = $this->get_site_data($site_id);
            if (!$site_data) {
                return array('success' => false, 'message' => 'Site not found');
            }
            
            $api_url = trailingslashit($site_data['site_url']) . 'wp-json/rphub/v1/configuration/deploy';
            $api_key = $this->get_site_api_key($site_id);
            
            $payload = array(
                'config_type' => $config_type,
                'config_data' => $config_data
            );
            
            $response = wp_remote_post($api_url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => wp_json_encode($payload),
                'timeout' => 60
            ));
            
            if (is_wp_error($response)) {
                return array('success' => false, 'message' => $response->get_error_message());
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            return array(
                'success' => true,
                'message' => 'Configuration deployed successfully',
                'data' => $data
            );
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }
    }
    
    /**
     * Helper functions
     */
    private function get_site_data($site_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rphub_websites WHERE id = %d",
            $site_id
        ), ARRAY_A);
    }
    
    private function get_site_api_key($site_id) {
        global $wpdb;
        
        $api_key = $wpdb->get_var($wpdb->prepare(
            "SELECT api_key FROM {$wpdb->prefix}rphub_websites WHERE id = %d",
            $site_id
        ));
        
        return $api_key ?: $this->generate_site_api_key($site_id);
    }
    
    private function generate_site_api_key($site_id) {
        $api_key = wp_generate_password(32, false);
        
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'rphub_websites',
            array('api_key' => $api_key),
            array('id' => $site_id),
            array('%s'),
            array('%d')
        );
        
        return $api_key;
    }
    
    private function log_bulk_action($site_id, $action, $result) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'rphub_bulk_action_logs',
            array(
                'site_id' => $site_id,
                'action_type' => $action,
                'result' => wp_json_encode($result),
                'executed_by' => get_current_user_id(),
                'executed_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%d', '%s')
        );
    }
    
    private function log_sync_error($site_id, $error_message) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'rphub_sync_errors',
            array(
                'site_id' => $site_id,
                'error_message' => $error_message,
                'error_time' => current_time('mysql')
            ),
            array('%d', '%s', '%s')
        );
    }
    
    private function update_site_status($site_id, $status_data) {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'rphub_websites',
            array(
                'last_sync' => current_time('mysql'),
                'status_data' => wp_json_encode($status_data)
            ),
            array('id' => $site_id),
            array('%s', '%s'),
            array('%d')
        );
    }
}
