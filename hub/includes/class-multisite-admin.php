<?php
/**
 * Multi-site Network Administration Interface
 * Provides admin interface for managing multiple sites
 * 
 * @package ReplantaHub
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPHUB_Multisite_Admin {
    
    private $multisite_manager;
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_pages'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Initialize multisite manager
        if (class_exists('RPHUB_Multisite_Manager')) {
            $this->multisite_manager = new RPHUB_Multisite_Manager();
        }
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_pages() {
        // Groups and bulk-ops are accessible via direct URL; they do not appear
        // as top-level menu items to keep the sidebar clean.
        add_submenu_page(
            null,
            __('Grupos de Sitios', 'replanta-hub'),
            __('Grupos', 'replanta-hub'),
            'manage_options',
            'rphub-site-groups',
            array($this, 'render_groups_page')
        );

        add_submenu_page(
            null,
            __('Operaciones Masivas', 'replanta-hub'),
            __('Operaciones Masivas', 'replanta-hub'),
            'manage_options',
            'rphub-bulk-ops',
            array($this, 'render_bulk_operations_page')
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'rphub-') === false) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_script('jquery-ui-tabs');
        wp_enqueue_style('wp-jquery-ui-dialog');
        
        wp_enqueue_script(
            'rphub-multisite-admin',
            RPHUB_PLUGIN_URL . 'assets/js/multisite-admin.js',
            array('jquery', 'jquery-ui-dialog', 'jquery-ui-tabs'),
            RPHUB_VERSION,
            true
        );
        
        wp_enqueue_style(
            'rphub-multisite-admin',
            RPHUB_PLUGIN_URL . 'assets/css/multisite-admin.css',
            array(),
            RPHUB_VERSION
        );
        
        wp_localize_script('rphub-multisite-admin', 'rphubMultisite', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rphub_multisite_nonce'),
            'strings' => array(
                'confirmBulkAction' => __('Are you sure you want to perform this action on selected sites?', 'replanta-hub'),
                'selectSites' => __('Please select at least one site.', 'replanta-hub'),
                'actionInProgress' => __('Action in progress...', 'replanta-hub'),
                'actionCompleted' => __('Action completed successfully.', 'replanta-hub'),
                'actionFailed' => __('Action failed. Please try again.', 'replanta-hub')
            )
        ));
    }
    
    /**
     * Render network management page
     */
    public function render_network_page() {
        global $wpdb;
        
        // Get network statistics
        $total_sites = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rphub_websites WHERE status = 'active'");
        $active_sites = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rphub_websites WHERE status = 'active' AND last_check > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $groups_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rphub_site_groups WHERE status = 'active'");
        
        // Get recent sync data
        $last_sync = get_option('rphub_last_multisite_sync', 'Never');
        
        ?>
        <div class="wrap">
            <h1><?php _e('Network Management', 'replanta-hub'); ?></h1>
            
            <div class="rphub-network-overview">
                <div class="rphub-stats-grid">
                    <div class="rphub-stat-card">
                        <h3><?php echo $total_sites; ?></h3>
                        <p><?php _e('Total Sites', 'replanta-hub'); ?></p>
                    </div>
                    <div class="rphub-stat-card">
                        <h3><?php echo $active_sites; ?></h3>
                        <p><?php _e('Active Sites', 'replanta-hub'); ?></p>
                    </div>
                    <div class="rphub-stat-card">
                        <h3><?php echo $groups_count; ?></h3>
                        <p><?php _e('Site Groups', 'replanta-hub'); ?></p>
                    </div>
                    <div class="rphub-stat-card">
                        <h3><?php echo ($last_sync !== 'Never') ? human_time_diff(strtotime($last_sync)) . ' ago' : 'Never'; ?></h3>
                        <p><?php _e('Last Sync', 'replanta-hub'); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="rphub-network-actions">
                <h2><?php _e('Network Actions', 'replanta-hub'); ?></h2>
                <div class="rphub-action-buttons">
                    <button type="button" class="button button-primary" id="sync-all-sites">
                        <?php _e('Sync All Sites', 'replanta-hub'); ?>
                    </button>
                </div>
            </div>
            
            <div class="rphub-network-sites">
                <h2><?php _e('Site Status Overview', 'replanta-hub'); ?></h2>
                <div id="sites-status-table">
                    <?php $this->render_sites_status_table(); ?>
                </div>
            </div>
            
            <div class="rphub-recent-activity">
                <h2><?php _e('Recent Activity', 'replanta-hub'); ?></h2>
                <div id="recent-activity-log">
                    <?php $this->render_recent_activity(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render site groups page
     */
    public function render_groups_page() {
        global $wpdb;
        
        // Handle form submissions
        if (isset($_POST['create_group']) && wp_verify_nonce($_POST['rphub_nonce'], 'rphub_create_group')) {
            $this->handle_create_group();
        }
        
        if (isset($_POST['delete_group']) && wp_verify_nonce($_POST['rphub_nonce'], 'rphub_delete_group')) {
            $this->handle_delete_group();
        }
        
        // Get all groups
        $groups = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}rphub_site_groups WHERE status = 'active' ORDER BY group_name");
        
        // Get all sites for group assignment
        $sites = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}rphub_websites WHERE status = 'active' ORDER BY site_name");
        
        ?>
        <div class="wrap">
            <h1><?php _e('Site Groups Management', 'replanta-hub'); ?></h1>
            
            <div class="rphub-groups-container">
                <div class="rphub-create-group">
                    <h2><?php _e('Create New Group', 'replanta-hub'); ?></h2>
                    <form method="post" action="">
                        <?php wp_nonce_field('rphub_create_group', 'rphub_nonce'); ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Group Name', 'replanta-hub'); ?></th>
                                <td><input type="text" name="group_name" class="regular-text" required /></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Description', 'replanta-hub'); ?></th>
                                <td><textarea name="group_description" class="large-text" rows="3"></textarea></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Sites', 'replanta-hub'); ?></th>
                                <td>
                                    <select name="site_ids[]" multiple class="regular-text" style="height: 150px;">
                                        <?php foreach ($sites as $site): ?>
                                            <option value="<?php echo $site->id; ?>"><?php echo esc_html($site->site_name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description"><?php _e('Hold Ctrl/Cmd to select multiple sites', 'replanta-hub'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="create_group" class="button-primary" value="<?php _e('Create Group', 'replanta-hub'); ?>" />
                        </p>
                    </form>
                </div>
                
                <div class="rphub-existing-groups">
                    <h2><?php _e('Existing Groups', 'replanta-hub'); ?></h2>
                    
                    <?php if (empty($groups)): ?>
                        <p><?php _e('No site groups found. Create your first group above.', 'replanta-hub'); ?></p>
                    <?php else: ?>
                        <div class="rphub-groups-list">
                            <?php foreach ($groups as $group): ?>
                                <div class="rphub-group-card">
                                    <h3><?php echo esc_html($group->group_name); ?></h3>
                                    <p><?php echo esc_html($group->group_description); ?></p>
                                    
                                    <?php 
                                    $group_sites = $wpdb->get_results($wpdb->prepare(
                                        "SELECT w.site_name FROM {$wpdb->prefix}rphub_websites w 
                                         JOIN {$wpdb->prefix}rphub_site_group_members sgm ON w.id = sgm.site_id 
                                         WHERE sgm.group_id = %d",
                                        $group->id
                                    ));
                                    ?>
                                    
                                    <div class="group-sites">
                                        <strong><?php _e('Sites:', 'replanta-hub'); ?></strong>
                                        <?php if (empty($group_sites)): ?>
                                            <em><?php _e('No sites assigned', 'replanta-hub'); ?></em>
                                        <?php else: ?>
                                            <ul>
                                                <?php foreach ($group_sites as $site): ?>
                                                    <li><?php echo esc_html($site->site_name); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="group-actions">
                                        <button type="button" class="button edit-group" data-group-id="<?php echo $group->id; ?>">
                                            <?php _e('Edit', 'replanta-hub'); ?>
                                        </button>
                                        <form method="post" style="display: inline;">
                                            <?php wp_nonce_field('rphub_delete_group', 'rphub_nonce'); ?>
                                            <input type="hidden" name="group_id" value="<?php echo $group->id; ?>" />
                                            <input type="submit" name="delete_group" class="button button-link-delete" 
                                                   value="<?php _e('Delete', 'replanta-hub'); ?>" 
                                                   onclick="return confirm('<?php _e('Are you sure you want to delete this group?', 'replanta-hub'); ?>');" />
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render bulk operations page
     */
    public function render_bulk_operations_page() {
        global $wpdb;
        
        // Get all sites and groups
        $sites = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}rphub_websites WHERE status = 'active' ORDER BY site_name");
        $groups = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}rphub_site_groups WHERE status = 'active' ORDER BY group_name");
        
        ?>
        <div class="wrap">
            <h1><?php _e('Bulk Operations', 'replanta-hub'); ?></h1>
            
            <div class="rphub-bulk-operations-container">
                <form id="bulk-operations-form">
                    <div class="rphub-operation-selector">
                        <h2><?php _e('Select Operation', 'replanta-hub'); ?></h2>
                        <select name="operation_type" id="operation-type" class="regular-text">
                            <option value=""><?php _e('Choose operation...', 'replanta-hub'); ?></option>
                            <option value="update_plugins"><?php _e('Update Plugins', 'replanta-hub'); ?></option>
                            <option value="update_themes"><?php _e('Update Themes', 'replanta-hub'); ?></option>
                            <option value="backup_site"><?php _e('Create Backup', 'replanta-hub'); ?></option>
                            <option value="optimize_database"><?php _e('Optimize Database', 'replanta-hub'); ?></option>
                            <option value="security_scan"><?php _e('Security Scan', 'replanta-hub'); ?></option>
                            <option value="performance_optimization"><?php _e('Performance Optimization', 'replanta-hub'); ?></option>
                            <option value="content_sync"><?php _e('Content Synchronization', 'replanta-hub'); ?></option>
                            <option value="settings_deploy"><?php _e('Deploy Settings', 'replanta-hub'); ?></option>
                        </select>
                    </div>
                    
                    <div class="rphub-target-selector">
                        <h2><?php _e('Select Targets', 'replanta-hub'); ?></h2>
                        
                        <div class="target-tabs">
                            <button type="button" class="tab-button active" data-tab="individual-sites">
                                <?php _e('Individual Sites', 'replanta-hub'); ?>
                            </button>
                            <button type="button" class="tab-button" data-tab="site-groups">
                                <?php _e('Site Groups', 'replanta-hub'); ?>
                            </button>
                        </div>
                        
                        <div id="individual-sites" class="tab-content active">
                            <div class="sites-selector">
                                <div class="select-all-controls">
                                    <button type="button" id="select-all-sites" class="button"><?php _e('Select All', 'replanta-hub'); ?></button>
                                    <button type="button" id="deselect-all-sites" class="button"><?php _e('Deselect All', 'replanta-hub'); ?></button>
                                </div>
                                
                                <div class="sites-grid">
                                    <?php foreach ($sites as $site): ?>
                                        <label class="site-checkbox">
                                            <input type="checkbox" name="selected_sites[]" value="<?php echo $site->id; ?>" />
                                            <span class="site-name"><?php echo esc_html($site->site_name); ?></span>
                                            <span class="site-url"><?php echo esc_html($site->site_url); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div id="site-groups" class="tab-content">
                            <div class="groups-selector">
                                <?php if (empty($groups)): ?>
                                    <p><?php _e('No site groups available. Create groups first.', 'replanta-hub'); ?></p>
                                <?php else: ?>
                                    <?php foreach ($groups as $group): ?>
                                        <label class="group-checkbox">
                                            <input type="checkbox" name="selected_groups[]" value="<?php echo $group->id; ?>" />
                                            <span class="group-name"><?php echo esc_html($group->group_name); ?></span>
                                            <span class="group-description"><?php echo esc_html($group->group_description); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="rphub-operation-config" id="operation-config" style="display: none;">
                        <h2><?php _e('Operation Configuration', 'replanta-hub'); ?></h2>
                        <div id="config-content">
                            <!-- Dynamic content loaded via JavaScript -->
                        </div>
                    </div>
                    
                    <div class="rphub-operation-controls">
                        <button type="button" id="execute-operation" class="button button-primary" disabled>
                            <?php _e('Execute Operation', 'replanta-hub'); ?>
                        </button>
                    </div>
                </form>
                
                <div id="operation-results" class="rphub-operation-results" style="display: none;">
                    <h2><?php _e('Operation Results', 'replanta-hub'); ?></h2>
                    <div id="results-content"></div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render network analytics page
     */
    public function render_network_analytics_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Network Analytics', 'replanta-hub'); ?></h1>
            
            <div class="rphub-analytics-dashboard">
                <div class="analytics-filters">
                    <h2><?php _e('Analytics Filters', 'replanta-hub'); ?></h2>
                    <form id="analytics-filters-form">
                        <div class="filter-row">
                            <label for="date-range"><?php _e('Date Range:', 'replanta-hub'); ?></label>
                            <select name="date_range" id="date-range">
                                <option value="7days"><?php _e('Last 7 days', 'replanta-hub'); ?></option>
                                <option value="30days" selected><?php _e('Last 30 days', 'replanta-hub'); ?></option>
                                <option value="90days"><?php _e('Last 90 days', 'replanta-hub'); ?></option>
                                <option value="custom"><?php _e('Custom range', 'replanta-hub'); ?></option>
                            </select>
                        </div>
                        
                        <div class="filter-row">
                            <label for="metrics"><?php _e('Metrics:', 'replanta-hub'); ?></label>
                            <select name="metrics[]" id="metrics" multiple>
                                <option value="pageviews" selected><?php _e('Pageviews', 'replanta-hub'); ?></option>
                                <option value="sessions" selected><?php _e('Sessions', 'replanta-hub'); ?></option>
                                <option value="users" selected><?php _e('Users', 'replanta-hub'); ?></option>
                                <option value="bounce_rate"><?php _e('Bounce Rate', 'replanta-hub'); ?></option>
                                <option value="avg_session_duration"><?php _e('Avg Session Duration', 'replanta-hub'); ?></option>
                                <option value="conversion_rate"><?php _e('Conversion Rate', 'replanta-hub'); ?></option>
                            </select>
                        </div>
                        
                        <div class="filter-row">
                            <button type="button" id="apply-filters" class="button button-primary">
                                <?php _e('Apply Filters', 'replanta-hub'); ?>
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="analytics-summary">
                    <h2><?php _e('Network Summary', 'replanta-hub'); ?></h2>
                    <div id="network-summary-cards">
                        <!-- Dynamic content loaded via AJAX -->
                    </div>
                </div>
                
                <div class="analytics-charts">
                    <h2><?php _e('Network Performance Charts', 'replanta-hub'); ?></h2>
                    <div id="analytics-charts-container">
                        <!-- Charts loaded via JavaScript -->
                    </div>
                </div>
                
                <div class="top-performing-sites">
                    <h2><?php _e('Top Performing Sites', 'replanta-hub'); ?></h2>
                    <div id="top-sites-table">
                        <!-- Dynamic content loaded via AJAX -->
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render configuration templates page
     */
    public function render_config_templates_page() {
        global $wpdb;
        
        // Get all templates
        $templates = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}rphub_config_templates WHERE status = 'active' ORDER BY config_type, template_name");
        
        ?>
        <div class="wrap">
            <h1><?php _e('Configuration Templates', 'replanta-hub'); ?></h1>
            
            <div class="rphub-templates-container">
                <div class="templates-actions">
                    <button type="button" id="create-template" class="button button-primary">
                        <?php _e('Create New Template', 'replanta-hub'); ?>
                    </button>
                    <button type="button" id="import-template" class="button">
                        <?php _e('Import Template', 'replanta-hub'); ?>
                    </button>
                </div>
                
                <div class="templates-list">
                    <?php if (empty($templates)): ?>
                        <p><?php _e('No configuration templates found.', 'replanta-hub'); ?></p>
                    <?php else: ?>
                        <?php 
                        $grouped_templates = array();
                        foreach ($templates as $template) {
                            $grouped_templates[$template->config_type][] = $template;
                        }
                        ?>
                        
                        <?php foreach ($grouped_templates as $type => $type_templates): ?>
                            <div class="template-group">
                                <h2><?php echo ucfirst($type); ?> <?php _e('Templates', 'replanta-hub'); ?></h2>
                                
                                <div class="templates-grid">
                                    <?php foreach ($type_templates as $template): ?>
                                        <div class="template-card">
                                            <h3><?php echo esc_html($template->template_name); ?></h3>
                                            <p><?php echo esc_html($template->template_description); ?></p>
                                            
                                            <?php if ($template->is_default): ?>
                                                <span class="default-badge"><?php _e('Default', 'replanta-hub'); ?></span>
                                            <?php endif; ?>
                                            
                                            <div class="template-actions">
                                                <button type="button" class="button preview-template" 
                                                        data-template-id="<?php echo $template->id; ?>">
                                                    <?php _e('Preview', 'replanta-hub'); ?>
                                                </button>
                                                <button type="button" class="button edit-template" 
                                                        data-template-id="<?php echo $template->id; ?>">
                                                    <?php _e('Edit', 'replanta-hub'); ?>
                                                </button>
                                                <button type="button" class="button deploy-template" 
                                                        data-template-id="<?php echo $template->id; ?>">
                                                    <?php _e('Deploy', 'replanta-hub'); ?>
                                                </button>
                                                <button type="button" class="button button-link-delete delete-template" 
                                                        data-template-id="<?php echo $template->id; ?>">
                                                    <?php _e('Delete', 'replanta-hub'); ?>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Helper functions
     */
    private function render_sites_status_table() {
        global $wpdb;
        
        $sites = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}rphub_websites WHERE status = 'active' ORDER BY site_name LIMIT 20");
        
        if (empty($sites)) {
            echo '<p>' . __('No sites found.', 'replanta-hub') . '</p>';
            return;
        }
        
        ?>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Site Name', 'replanta-hub'); ?></th>
                    <th><?php _e('URL', 'replanta-hub'); ?></th>
                    <th><?php _e('Status', 'replanta-hub'); ?></th>
                    <th><?php _e('Last Check', 'replanta-hub'); ?></th>
                    <th><?php _e('Performance', 'replanta-hub'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sites as $site): ?>
                    <tr>
                        <td><strong><?php echo esc_html($site->site_name); ?></strong></td>
                        <td><a href="<?php echo esc_url($site->site_url); ?>" target="_blank"><?php echo esc_html($site->site_url); ?></a></td>
                        <td>
                            <span class="status-indicator status-<?php echo esc_attr($site->status); ?>">
                                <?php echo ucfirst($site->status); ?>
                            </span>
                        </td>
                        <td><?php echo $site->last_check ? human_time_diff(strtotime($site->last_check)) . ' ago' : 'Never'; ?></td>
                        <td>
                            <?php 
                            $performance_score = isset($site->performance_score) ? (int) $site->performance_score : 0;
                            $score_class = $performance_score >= 90 ? 'excellent' : ($performance_score >= 70 ? 'good' : 'poor');
                            ?>
                            <span class="performance-score score-<?php echo $score_class; ?>">
                                <?php echo $performance_score; ?>%
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    private function render_recent_activity() {
        global $wpdb;
        
        $activities = $wpdb->get_results(
            "SELECT bal.*, w.site_name 
             FROM {$wpdb->prefix}rphub_bulk_action_logs bal 
             JOIN {$wpdb->prefix}rphub_websites w ON bal.site_id = w.id 
             ORDER BY bal.executed_at DESC 
             LIMIT 10"
        );
        
        if (empty($activities)) {
            echo '<p>' . __('No recent activity found.', 'replanta-hub') . '</p>';
            return;
        }
        
        ?>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Site', 'replanta-hub'); ?></th>
                    <th><?php _e('Action', 'replanta-hub'); ?></th>
                    <th><?php _e('Status', 'replanta-hub'); ?></th>
                    <th><?php _e('Time', 'replanta-hub'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activities as $activity): ?>
                    <tr>
                        <td><?php echo esc_html($activity->site_name); ?></td>
                        <td><?php echo esc_html(str_replace('_', ' ', ucwords($activity->action_type, '_'))); ?></td>
                        <td>
                            <span class="status-indicator status-<?php echo esc_attr($activity->status); ?>">
                                <?php echo ucfirst($activity->status); ?>
                            </span>
                        </td>
                        <td><?php echo human_time_diff(strtotime($activity->executed_at)) . ' ago'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    private function handle_create_group() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $group_name = sanitize_text_field($_POST['group_name']);
        $group_description = sanitize_textarea_field($_POST['group_description']);
        $site_ids = array_map('intval', $_POST['site_ids'] ?? array());
        
        global $wpdb;
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'rphub_site_groups',
            array(
                'group_name' => $group_name,
                'group_description' => $group_description,
                'created_at' => current_time('mysql'),
                'created_by' => get_current_user_id(),
                'status' => 'active'
            ),
            array('%s', '%s', '%s', '%d', '%s')
        );
        
        if ($result && !empty($site_ids)) {
            $group_id = $wpdb->insert_id;
            
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
        }
    }
    
    private function handle_delete_group() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $group_id = intval($_POST['group_id']);
        
        global $wpdb;
        
        // Delete group members first
        $wpdb->delete(
            $wpdb->prefix . 'rphub_site_group_members',
            array('group_id' => $group_id),
            array('%d')
        );
        
        // Delete group
        $wpdb->delete(
            $wpdb->prefix . 'rphub_site_groups',
            array('id' => $group_id),
            array('%d')
        );
    }
}
