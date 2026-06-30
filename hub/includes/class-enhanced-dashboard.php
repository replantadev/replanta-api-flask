<?php
/**
 * Enhanced Hub Dashboard for Replanta Hub
 * Provides advanced site management and monitoring interface
 * 
 * @package ReplantaHub
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RP_Hub_Enhanced_Dashboard {
    
    private $plans_manager;
    
    public function __construct() {
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widgets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_rphub_get_sites_overview', array($this, 'ajax_get_sites_overview'));
        add_action('wp_ajax_rphub_assign_plan', array($this, 'ajax_assign_plan'));
        add_action('wp_ajax_rphub_get_site_details', array($this, 'ajax_get_site_details'));
        
        // Initialize plans manager
        if (class_exists('RPHUB_Plans_Manager')) {
            $this->plans_manager = new RPHUB_Plans_Manager();
        }
    }
    
    /**
     * Add dashboard widgets
     */
    public function add_dashboard_widgets() {
        wp_add_dashboard_widget(
            'rphub_sites_overview',
            __('Sites Overview', 'replanta-hub'),
            array($this, 'render_sites_overview_widget')
        );
        
        wp_add_dashboard_widget(
            'rphub_maintenance_summary',
            __('Maintenance Summary', 'replanta-hub'),
            array($this, 'render_maintenance_summary_widget')
        );
        
        wp_add_dashboard_widget(
            'rphub_revenue_stats',
            __('Revenue Statistics', 'replanta-hub'),
            array($this, 'render_revenue_stats_widget')
        );
    }
    
    /**
     * Enqueue dashboard scripts and styles
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'index.php') {
            return;
        }
        
        wp_enqueue_style(
            'rphub-enhanced-dashboard',
            RPHUB_PLUGIN_URL . 'assets/css/enhanced-dashboard.css',
            array(),
            RPHUB_VERSION
        );
        
        wp_enqueue_script(
            'rphub-enhanced-dashboard',
            RPHUB_PLUGIN_URL . 'assets/js/enhanced-dashboard.js',
            array('jquery', 'jquery-ui-sortable'),
            RPHUB_VERSION,
            true
        );
        
        wp_localize_script('rphub-enhanced-dashboard', 'rphub_dashboard', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rphub_dashboard_nonce'),
            'strings' => array(
                'loading' => __('Loading...', 'replanta-hub'),
                'error' => __('An error occurred', 'replanta-hub'),
                'success' => __('Operation completed successfully', 'replanta-hub'),
                'confirm_bulk' => __('Are you sure you want to perform this bulk action?', 'replanta-hub'),
                'assign_plan' => __('Assign Plan', 'replanta-hub'),
                'no_sites_selected' => __('Please select at least one site', 'replanta-hub')
            )
        ));
    }
    
    /**
     * Render sites overview widget
     */
    public function render_sites_overview_widget() {
        ?>
        <div class="rphub-sites-overview">
            <div class="rphub-widget-header">
                <div class="rphub-widget-controls">
                    <select id="rphub-plan-filter">
                        <option value=""><?php _e('All Plans', 'replanta-hub'); ?></option>
                        <option value="semilla"><?php _e('Semilla Plan', 'replanta-hub'); ?></option>
                        <option value="raiz"><?php _e('Raíz Plan', 'replanta-hub'); ?></option>
                        <option value="ecosistema"><?php _e('Ecosistema Plan', 'replanta-hub'); ?></option>
                    </select>
                    
                    <select id="rphub-status-filter">
                        <option value=""><?php _e('All Status', 'replanta-hub'); ?></option>
                        <option value="online"><?php _e('Online', 'replanta-hub'); ?></option>
                        <option value="warning"><?php _e('Warning', 'replanta-hub'); ?></option>
                        <option value="error"><?php _e('Error', 'replanta-hub'); ?></option>
                        <option value="offline"><?php _e('Offline', 'replanta-hub'); ?></option>
                    </select>
                    
                    <button type="button" class="button" id="rphub-refresh-sites">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Refresh', 'replanta-hub'); ?>
                    </button>
                </div>
            </div>
            
            <div class="rphub-bulk-actions">
                <select id="rphub-bulk-action">
                    <option value=""><?php _e('Bulk Actions', 'replanta-hub'); ?></option>
                    <option value="assign_plan"><?php _e('Assign Plan', 'replanta-hub'); ?></option>
                    <option value="run_backup"><?php _e('Run Backup', 'replanta-hub'); ?></option>
                    <option value="update_plugins"><?php _e('Update Plugins', 'replanta-hub'); ?></option>
                    <option value="clear_cache"><?php _e('Clear Cache', 'replanta-hub'); ?></option>
                    <option value="health_check"><?php _e('Health Check', 'replanta-hub'); ?></option>
                </select>
                
                <select id="rphub-bulk-plan" style="display:none;">
                    <option value=""><?php _e('Select Plan', 'replanta-hub'); ?></option>
                    <option value="semilla"><?php _e('Semilla (€49)', 'replanta-hub'); ?></option>
                    <option value="raiz"><?php _e('Raíz (€89)', 'replanta-hub'); ?></option>
                    <option value="ecosistema"><?php _e('Ecosistema (€149)', 'replanta-hub'); ?></option>
                </select>
                
                <button type="button" class="button" id="rphub-apply-bulk">
                    <?php _e('Apply', 'replanta-hub'); ?>
                </button>
            </div>
            
            <div class="rphub-sites-grid" id="rphub-sites-grid">
                <div class="rphub-loading">
                    <div class="rphub-spinner"></div>
                    <p><?php _e('Loading sites...', 'replanta-hub'); ?></p>
                </div>
            </div>
            
            <div class="rphub-pagination" id="rphub-pagination" style="display:none;">
                <button type="button" class="button" id="rphub-prev-page" disabled>
                    <?php _e('Previous', 'replanta-hub'); ?>
                </button>
                <span id="rphub-page-info"></span>
                <button type="button" class="button" id="rphub-next-page">
                    <?php _e('Next', 'replanta-hub'); ?>
                </button>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render maintenance summary widget
     */
    public function render_maintenance_summary_widget() {
        ?>
        <div class="rphub-maintenance-summary">
            <div class="rphub-summary-grid">
                <div class="rphub-summary-card rphub-card-backups">
                    <div class="rphub-card-icon">
                        <span class="dashicons dashicons-backup"></span>
                    </div>
                    <div class="rphub-card-content">
                        <h3 id="rphub-backups-count">-</h3>
                        <p><?php _e('Backups Today', 'replanta-hub'); ?></p>
                    </div>
                </div>
                
                <div class="rphub-summary-card rphub-card-updates">
                    <div class="rphub-card-icon">
                        <span class="dashicons dashicons-update"></span>
                    </div>
                    <div class="rphub-card-content">
                        <h3 id="rphub-updates-count">-</h3>
                        <p><?php _e('Updates Available', 'replanta-hub'); ?></p>
                    </div>
                </div>
                
                <div class="rphub-summary-card rphub-card-issues">
                    <div class="rphub-card-icon">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                    <div class="rphub-card-content">
                        <h3 id="rphub-issues-count">-</h3>
                        <p><?php _e('Sites with Issues', 'replanta-hub'); ?></p>
                    </div>
                </div>
                
                <div class="rphub-summary-card rphub-card-revenue">
                    <div class="rphub-card-icon">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <div class="rphub-card-content">
                        <h3 id="rphub-revenue-month">-</h3>
                        <p><?php _e('Monthly Revenue', 'replanta-hub'); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="rphub-recent-activities" id="rphub-recent-activities">
                <h4><?php _e('Recent Activities', 'replanta-hub'); ?></h4>
                <div class="rphub-activity-list">
                    <div class="rphub-loading-small">
                        <span class="spinner is-active"></span>
                        <?php _e('Loading activities...', 'replanta-hub'); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render revenue statistics widget
     */
    public function render_revenue_stats_widget() {
        ?>
        <div class="rphub-revenue-stats">
            <div class="rphub-stats-period">
                <select id="rphub-stats-period">
                    <option value="7"><?php _e('Last 7 days', 'replanta-hub'); ?></option>
                    <option value="30" selected><?php _e('Last 30 days', 'replanta-hub'); ?></option>
                    <option value="90"><?php _e('Last 90 days', 'replanta-hub'); ?></option>
                    <option value="365"><?php _e('Last year', 'replanta-hub'); ?></option>
                </select>
            </div>
            
            <div class="rphub-chart-container">
                <canvas id="rphub-revenue-chart" width="400" height="200"></canvas>
            </div>
            
            <div class="rphub-plan-breakdown">
                <h4><?php _e('Plan Distribution', 'replanta-hub'); ?></h4>
                <div class="rphub-plan-stats">
                    <div class="rphub-plan-stat rphub-plan-semilla">
                        <span class="rphub-plan-color"></span>
                        <span class="rphub-plan-name"><?php _e('Semilla', 'replanta-hub'); ?></span>
                        <span class="rphub-plan-count" id="rphub-semilla-count">0</span>
                        <span class="rphub-plan-revenue" id="rphub-semilla-revenue">€0</span>
                    </div>
                    
                    <div class="rphub-plan-stat rphub-plan-raiz">
                        <span class="rphub-plan-color"></span>
                        <span class="rphub-plan-name"><?php _e('Raíz', 'replanta-hub'); ?></span>
                        <span class="rphub-plan-count" id="rphub-raiz-count">0</span>
                        <span class="rphub-plan-revenue" id="rphub-raiz-revenue">€0</span>
                    </div>
                    
                    <div class="rphub-plan-stat rphub-plan-ecosistema">
                        <span class="rphub-plan-color"></span>
                        <span class="rphub-plan-name"><?php _e('Ecosistema', 'replanta-hub'); ?></span>
                        <span class="rphub-plan-count" id="rphub-ecosistema-count">0</span>
                        <span class="rphub-plan-revenue" id="rphub-ecosistema-revenue">€0</span>
                    </div>
                </div>
                
                <div class="rphub-total-revenue">
                    <strong>
                        <?php _e('Total:', 'replanta-hub'); ?>
                        <span id="rphub-total-revenue">€0</span>
                    </strong>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX: Get sites overview
     */
    public function ajax_get_sites_overview() {
        check_ajax_referer('rphub_dashboard_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $page = intval($_POST['page'] ?? 1);
        $per_page = intval($_POST['per_page'] ?? 12);
        $plan_filter = sanitize_text_field($_POST['plan_filter'] ?? '');
        $status_filter = sanitize_text_field($_POST['status_filter'] ?? '');
        
        try {
            // Get sites from database
            $sites = $this->get_sites_data($page, $per_page, $plan_filter, $status_filter);
            
            wp_send_json_success($sites);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Assign plan to site
     */
    public function ajax_assign_plan() {
        check_ajax_referer('rphub_dashboard_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $site_id = intval($_POST['site_id']);
        $plan_id = intval($_POST['plan_id']);
        
        if (!$site_id || !$plan_id) {
            wp_send_json_error(__('Invalid site or plan ID', 'replanta-hub'));
        }
        
        try {
            // Update site plan in database
            $result = $this->assign_plan_to_site($site_id, $plan_id);
            
            if ($result) {
                wp_send_json_success(__('Plan assigned successfully', 'replanta-hub'));
            } else {
                wp_send_json_error(__('Failed to assign plan', 'replanta-hub'));
            }
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Get site details
     */
    public function ajax_get_site_details() {
        check_ajax_referer('rphub_dashboard_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $site_id = intval($_POST['site_id']);
        
        if (!$site_id) {
            wp_send_json_error(__('Invalid site ID', 'replanta-hub'));
        }
        
        try {
            $details = $this->get_site_details($site_id);
            wp_send_json_success($details);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Get sites data with pagination and filters
     */
    private function get_sites_data($page = 1, $per_page = 12, $plan_filter = '', $status_filter = '') {
        global $wpdb;
        
        $offset = ($page - 1) * $per_page;
        
        // Build query
        $where_clauses = array("1=1");
        $join_clauses = array();
        
        if ($plan_filter) {
            $join_clauses[] = "LEFT JOIN {$wpdb->prefix}rphub_site_plans sp ON s.id = sp.site_id";
            $join_clauses[] = "LEFT JOIN {$wpdb->prefix}rphub_plans p ON sp.plan_id = p.id";
            $where_clauses[] = $wpdb->prepare("p.slug = %s", $plan_filter);
        }
        
        if ($status_filter) {
            $where_clauses[] = $wpdb->prepare("s.status = %s", $status_filter);
        }
        
        $join_sql = implode(' ', $join_clauses);
        $where_sql = implode(' AND ', $where_clauses);
        
        // Get total count
        $count_query = "SELECT COUNT(DISTINCT s.id) FROM {$wpdb->prefix}rphub_sites s {$join_sql} WHERE {$where_sql}";
        $total_sites = $wpdb->get_var($count_query);
        
        // Get sites
        $sites_query = "
            SELECT DISTINCT s.*, p.name as plan_name, p.slug as plan_slug, p.price as plan_price
            FROM {$wpdb->prefix}rphub_sites s 
            LEFT JOIN {$wpdb->prefix}rphub_site_plans sp ON s.id = sp.site_id
            LEFT JOIN {$wpdb->prefix}rphub_plans p ON sp.plan_id = p.id
            WHERE {$where_sql}
            ORDER BY s.last_success DESC
            LIMIT %d OFFSET %d
        ";
        
        $sites = $wpdb->get_results($wpdb->prepare($sites_query, $per_page, $offset));
        
        // Format sites data
        $formatted_sites = array();
        foreach ($sites as $site) {
            $formatted_sites[] = array(
                'id' => $site->id,
                'name' => $site->name,
                'url' => $site->url,
                'status' => $site->status,
                'last_success' => $site->last_success,
                'plan' => array(
                    'name' => $site->plan_name,
                    'slug' => $site->plan_slug,
                    'price' => $site->plan_price
                ),
                'health_score' => $this->get_site_health_score($site->id),
                'updates_available' => $this->get_site_updates_count($site->id)
            );
        }
        
        return array(
            'sites' => $formatted_sites,
            'pagination' => array(
                'current_page' => $page,
                'per_page' => $per_page,
                'total_sites' => intval($total_sites),
                'total_pages' => ceil($total_sites / $per_page)
            )
        );
    }
    
    /**
     * Get site health score
     */
    private function get_site_health_score($site_id) {
        $site = RPHUB_Database::get_site($site_id);
        return $site ? (int) $site->health_score : 0;
    }
    
    /**
     * Get site updates count
     */
    private function get_site_updates_count($site_id) {
        $pending = RPHUB_Database::get_site_meta($site_id, 'pending_updates');
        if (is_array($pending)) {
            return count($pending);
        }
        return 0;
    }
    
    /**
     * Assign plan to site
     */
    private function assign_plan_to_site($site_id, $plan_id) {
        global $wpdb;
        
        // First, remove existing plan assignment
        $wpdb->delete(
            $wpdb->prefix . 'rphub_site_plans',
            array('site_id' => $site_id),
            array('%d')
        );
        
        // Assign new plan
        return $wpdb->insert(
            $wpdb->prefix . 'rphub_site_plans',
            array(
                'site_id' => $site_id,
                'plan_id' => $plan_id,
                'assigned_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s')
        );
    }
    
    /**
     * Perform bulk action on multiple sites
     */
    private function perform_bulk_action($action, $site_ids, $plan_id = 0) {
        $results = array();
        
        foreach ($site_ids as $site_id) {
            try {
                switch ($action) {
                    case 'assign_plan':
                        if ($plan_id) {
                            $success = $this->assign_plan_to_site($site_id, $plan_id);
                            $results[] = array(
                                'site_id' => $site_id,
                                'success' => (bool) $success,
                                'message' => $success ? 'Plan assigned' : 'Failed to assign plan'
                            );
                        }
                        break;
                        
                    case 'run_backup':
                        // Trigger backup via API
                        $results[] = array(
                            'site_id' => $site_id,
                            'success' => true,
                            'message' => 'Backup initiated'
                        );
                        break;
                        
                    case 'update_plugins':
                        // Trigger plugin updates via API
                        $results[] = array(
                            'site_id' => $site_id,
                            'success' => true,
                            'message' => 'Updates initiated'
                        );
                        break;
                        
                    case 'clear_cache':
                        // Trigger cache clear via API
                        $results[] = array(
                            'site_id' => $site_id,
                            'success' => true,
                            'message' => 'Cache cleared'
                        );
                        break;
                        
                    case 'health_check':
                        // Trigger health check via API
                        $results[] = array(
                            'site_id' => $site_id,
                            'success' => true,
                            'message' => 'Health check initiated'
                        );
                        break;
                        
                    default:
                        $results[] = array(
                            'site_id' => $site_id,
                            'success' => false,
                            'message' => 'Unknown action'
                        );
                }
            } catch (Exception $e) {
                $results[] = array(
                    'site_id' => $site_id,
                    'success' => false,
                    'message' => $e->getMessage()
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Get detailed site information
     */
    private function get_site_details($site_id) {
        global $wpdb;
        
        $site = $wpdb->get_row($wpdb->prepare(
            "SELECT s.*, p.name as plan_name, p.slug as plan_slug, p.price as plan_price
             FROM {$wpdb->prefix}rphub_sites s 
             LEFT JOIN {$wpdb->prefix}rphub_site_plans sp ON s.id = sp.site_id
             LEFT JOIN {$wpdb->prefix}rphub_plans p ON sp.plan_id = p.id
             WHERE s.id = %d",
            $site_id
        ));
        
        if (!$site) {
            throw new Exception(__('Site not found', 'replanta-hub'));
        }
        
        return array(
            'site' => $site,
            'health_details' => $this->get_site_health_details($site_id),
            'recent_backups' => $this->get_site_recent_backups($site_id),
            'recent_activities' => $this->get_site_recent_activities($site_id)
        );
    }
    
    /**
     * Get site health details
     */
    private function get_site_health_details($site_id) {
        $site = RPHUB_Database::get_site($site_id);
        $score = $site ? (int) $site->health_score : 0;
        $wp_version = RPHUB_Database::get_site_meta($site_id, 'wp_version');
        $php_version = RPHUB_Database::get_site_meta($site_id, 'php_version');
        $ssl_status = RPHUB_Database::get_site_meta($site_id, 'ssl_status');
        $pending = RPHUB_Database::get_site_meta($site_id, 'pending_updates');
        $pending_count = is_array($pending) ? count($pending) : 0;

        $checks = [];
        if ($wp_version) $checks[] = ['name' => 'WordPress Version', 'status' => 'good', 'message' => $wp_version];
        if ($php_version) $checks[] = ['name' => 'PHP Version', 'status' => version_compare($php_version, '8.0', '>=') ? 'good' : 'warning', 'message' => $php_version];
        if ($ssl_status) $checks[] = ['name' => 'SSL Certificate', 'status' => ($ssl_status['valid'] ?? false) ? 'good' : 'critical', 'message' => ($ssl_status['valid'] ?? false) ? 'Valid' : 'Invalid/Missing'];
        if ($pending_count > 0) $checks[] = ['name' => 'Plugins', 'status' => 'warning', 'message' => $pending_count . ' updates available'];

        return ['score' => $score, 'checks' => $checks];
    }
    
    /**
     * Get site recent backups
     */
    private function get_site_recent_backups($site_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rphub_backups';
        $backups = $wpdb->get_results($wpdb->prepare(
            "SELECT created_at as date, size, type FROM $table WHERE site_id = %d ORDER BY created_at DESC LIMIT 5",
            $site_id
        ), ARRAY_A);
        return $backups ?: [];
    }
    
    /**
     * Get site recent activities
     */
    private function get_site_recent_activities($site_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rphub_activities';
        $activities = $wpdb->get_results($wpdb->prepare(
            "SELECT created_at as time, action, description as details, user FROM $table WHERE site_id = %d ORDER BY created_at DESC LIMIT 10",
            $site_id
        ), ARRAY_A);
        return $activities ?: [];
    }
}
