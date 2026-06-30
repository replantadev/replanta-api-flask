<?php
/**
 * Care Update Manager
 * Controls plugin/theme updates when Care is active
 * 
 * @package ReplantaHub
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Replanta_Care_Update_Manager {
    
    private $care_status;
    private $is_care_active;
    
    public function __construct() {
        $this->care_status = get_option('rphub_care_status', array(
            'connected' => false,
            'plan' => 'none',
            'update_management' => false
        ));
        
        $this->is_care_active = $this->care_status['connected'] && $this->care_status['update_management'];
        
        if ($this->is_care_active) {
            $this->init_update_controls();
        }
        
        // Always add admin hooks for status display
        add_action('admin_init', array($this, 'add_admin_hooks'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_update_assets'));
    }
    
    /**
     * Initialize update control hooks when Care is active
     */
    private function init_update_controls() {
        // Block automatic updates for plugins and themes
        add_filter('auto_update_plugin', array($this, 'block_auto_updates'), 10, 2);
        add_filter('auto_update_theme', array($this, 'block_auto_updates'), 10, 2);
        
        // Block manual updates
        add_filter('map_meta_cap', array($this, 'block_manual_updates'), 10, 4);
        
        // Modify update notifications
        add_action('admin_notices', array($this, 'show_care_management_notice'));
        add_action('network_admin_notices', array($this, 'show_care_management_notice'));
        
        // Hook into update pages
        add_action('load-update-core.php', array($this, 'modify_update_page'));
        add_action('load-plugins.php', array($this, 'modify_plugins_page'));
        add_action('load-themes.php', array($this, 'modify_themes_page'));
        
        // Filter update actions
        add_filter('plugin_action_links', array($this, 'filter_plugin_actions'), 10, 2);
        add_filter('theme_action_links', array($this, 'filter_theme_actions'), 10, 2);
        
        // AJAX handlers for update attempts
        add_action('wp_ajax_update-plugin', array($this, 'block_ajax_updates'), 1);
        add_action('wp_ajax_update-theme', array($this, 'block_ajax_updates'), 1);
        add_action('wp_ajax_upgrade-plugin', array($this, 'block_ajax_updates'), 1);
        add_action('wp_ajax_upgrade-theme', array($this, 'block_ajax_updates'), 1);
    }
    
    /**
     * Add admin hooks for status display
     */
    public function add_admin_hooks() {
        // Add Care status to admin bar
        add_action('admin_bar_menu', array($this, 'add_care_status_to_admin_bar'), 100);
        
        // Add dashboard widget
        add_action('wp_dashboard_setup', array($this, 'add_care_dashboard_widget'));
    }
    
    /**
     * Block automatic updates when Care is managing
     */
    public function block_auto_updates($update, $item) {
        if ($this->is_care_active) {
            return false;
        }
        return $update;
    }
    
    /**
     * Block manual update capabilities
     */
    public function block_manual_updates($caps, $cap, $user_id, $args) {
        if (!$this->is_care_active) {
            return $caps;
        }
        
        $blocked_caps = array(
            'update_plugins',
            'update_themes',
            'update_core',
            'install_plugins',
            'install_themes',
            'delete_plugins',
            'delete_themes'
        );
        
        if (in_array($cap, $blocked_caps)) {
            // Allow access if user is coming from Care dashboard
            if (isset($_GET['care_override']) && wp_verify_nonce($_GET['care_nonce'], 'care_override')) {
                return $caps;
            }
            
            // Otherwise block the capability
            $caps[] = 'do_not_allow';
        }
        
        return $caps;
    }
    
    /**
     * Show Care management notice on admin pages
     */
    public function show_care_management_notice() {
        if (!$this->is_care_active) {
            return;
        }
        
        $screen = get_current_screen();
        $update_screens = array('update-core', 'plugins', 'themes', 'plugin-install', 'theme-install');
        
        if (!in_array($screen->id, $update_screens)) {
            return;
        }
        ?>
        <div class="notice notice-info care-management-notice">
            <div class="care-notice-content">
                <div class="care-notice-icon"></div>
                <div class="care-notice-text">
                    <h3><?php _e('Gestionado por Replanta Care', 'replanta-hub'); ?></h3>
                    <p><?php _e('Las actualizaciones de plugins y temas están siendo gestionadas automáticamente por Replanta Care para garantizar la seguridad y estabilidad de tu sitio.', 'replanta-hub'); ?></p>
                    <div class="care-notice-actions">
                        <a href="https://care.replanta.com/updates" target="_blank" class="button button-primary">
                            <?php _e('Gestionar Actualizaciones', 'replanta-hub'); ?>
                        </a>
                        <a href="<?php echo admin_url('options-general.php?page=replanta-care'); ?>" class="button button-secondary">
                            <?php _e('Ver Estado de Care', 'replanta-hub'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .care-management-notice {
            border-left-color: #059669 !important;
            background: #f0fdf4;
        }
        .care-notice-content {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 5px 0;
        }
        .care-notice-icon {
            font-size: 32px;
            flex-shrink: 0;
        }
        .care-notice-text h3 {
            margin: 0 0 8px 0;
            color: #059669;
            font-size: 16px;
        }
        .care-notice-text p {
            margin: 0 0 15px 0;
            color: #374151;
        }
        .care-notice-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .care-notice-actions .button {
            margin: 0;
        }
        </style>
        <?php
    }
    
    /**
     * Modify update core page
     */
    public function modify_update_page() {
        if (!$this->is_care_active) {
            return;
        }
        
        add_action('admin_footer', array($this, 'block_update_buttons'));
    }
    
    /**
     * Modify plugins page
     */
    public function modify_plugins_page() {
        if (!$this->is_care_active) {
            return;
        }
        
        add_action('admin_footer', array($this, 'block_plugin_update_buttons'));
    }
    
    /**
     * Modify themes page
     */
    public function modify_themes_page() {
        if (!$this->is_care_active) {
            return;
        }
        
        add_action('admin_footer', array($this, 'block_theme_update_buttons'));
    }
    
    /**
     * Block update buttons with JavaScript
     */
    public function block_update_buttons() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Block core update buttons
            $('.button-primary[value="upgrade-core"]').prop('disabled', true)
                .text('<?php _e("Gestionado por Care", "replanta-hub"); ?>')
                .addClass('care-managed');
            
            // Add Care branding to disabled buttons
            $('.care-managed').after(
                '<p class="care-explanation">' +
                '<?php _e("Las actualizaciones son gestionadas automáticamente por Replanta Care.", "replanta-hub"); ?>' +
                ' <a href="https://care.replanta.com/updates" target="_blank"><?php _e("Gestionar", "replanta-hub"); ?></a>' +
                '</p>'
            );
        });
        </script>
        <style>
        .care-managed {
            background: #6b7280 !important;
            border-color: #6b7280 !important;
            cursor: not-allowed !important;
        }
        .care-explanation {
            font-style: italic;
            color: #6b7280;
            margin-top: 5px;
        }
        .care-explanation a {
            color: #059669;
            text-decoration: none;
        }
        </style>
        <?php
    }
    
    /**
     * Block plugin update buttons
     */
    public function block_plugin_update_buttons() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Block plugin update links and buttons
            $('a[href*="action=upgrade-plugin"]').each(function() {
                $(this).replaceWith(
                    '<span class="care-managed-link"><?php _e("Gestionado por Care", "replanta-hub"); ?></span>'
                );
            });
            
            // Block bulk update actions
            $('#bulk-action-selector-top option[value="update-selected"]').prop('disabled', true)
                .text('<?php _e("Actualizar Seleccionados (Gestionado por Care)", "replanta-hub"); ?>');
            $('#bulk-action-selector-bottom option[value="update-selected"]').prop('disabled', true)
                .text('<?php _e("Actualizar Seleccionados (Gestionado por Care)", "replanta-hub"); ?>');
                
            // Add Care notice to plugins with updates
            $('.plugin-update-tr').each(function() {
                $(this).find('.update-message').html(
                    '<div class="care-update-notice">' +
                    '<span class="care-icon"></span> ' +
                    '<?php _e("Actualización gestionada por Replanta Care", "replanta-hub"); ?>' +
                    ' <a href="https://care.replanta.com/updates" target="_blank"><?php _e("Ver detalles", "replanta-hub"); ?></a>' +
                    '</div>'
                );
            });
        });
        </script>
        <style>
        .care-managed-link {
            color: #6b7280;
            font-style: italic;
        }
        .care-update-notice {
            background: #f0fdf4;
            border: 1px solid #059669;
            border-radius: 4px;
            padding: 8px 12px;
            color: #059669;
        }
        .care-icon {
            margin-right: 5px;
        }
        </style>
        <?php
    }
    
    /**
     * Block theme update buttons
     */
    public function block_theme_update_buttons() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Block theme update links
            $('a[href*="action=upgrade-theme"]').each(function() {
                $(this).replaceWith(
                    '<span class="care-managed-link"><?php _e("Gestionado por Care", "replanta-hub"); ?></span>'
                );
            });
            
            // Add Care notice to themes with updates
            $('.theme-update').each(function() {
                $(this).find('.update-message').html(
                    '<div class="care-update-notice">' +
                    '<span class="care-icon"></span> ' +
                    '<?php _e("Actualización gestionada por Replanta Care", "replanta-hub"); ?>' +
                    ' <a href="https://care.replanta.com/updates" target="_blank"><?php _e("Ver detalles", "replanta-hub"); ?></a>' +
                    '</div>'
                );
            });
        });
        </script>
        <?php
    }
    
    /**
     * Filter plugin action links
     */
    public function filter_plugin_actions($actions, $plugin_file) {
        if (!$this->is_care_active) {
            return $actions;
        }
        
        // Remove update links
        unset($actions['update']);
        
        // Add Care management link
        $actions['care_managed'] = '<span class="care-managed-text">' . 
            __('Gestionado por Care', 'replanta-hub') . '</span>';
        
        return $actions;
    }
    
    /**
     * Filter theme action links
     */
    public function filter_theme_actions($actions, $theme) {
        if (!$this->is_care_active) {
            return $actions;
        }
        
        // Remove update links
        unset($actions['update']);
        
        return $actions;
    }
    
    /**
     * Block AJAX update requests
     */
    public function block_ajax_updates() {
        if (!$this->is_care_active) {
            return;
        }
        
        // Check if this is an update request
        $action = $_POST['action'] ?? '';
        $blocked_actions = array('update-plugin', 'update-theme', 'upgrade-plugin', 'upgrade-theme');
        
        if (in_array($action, $blocked_actions)) {
            wp_send_json_error(array(
                'message' => __('Las actualizaciones están siendo gestionadas por Replanta Care.', 'replanta-hub'),
                'care_managed' => true,
                'care_dashboard' => 'https://care.replanta.com/updates'
            ));
        }
    }
    
    /**
     * Add Care status to admin bar
     */
    public function add_care_status_to_admin_bar($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $status_text = $this->is_care_active ? 
            __('Care Activo', 'replanta-hub') : 
            __('Care Inactivo', 'replanta-hub');
        
        $status_class = $this->is_care_active ? 'care-active' : 'care-inactive';
        
        $wp_admin_bar->add_node(array(
            'id' => 'replanta-care-status',
            'title' => '<span class="ab-icon dashicons dashicons-shield-alt"></span><span class="ab-label">' . $status_text . '</span>',
            'href' => admin_url('options-general.php?page=replanta-care'),
            'meta' => array(
                'class' => $status_class,
                'title' => __('Ver estado de Replanta Care', 'replanta-hub')
            )
        ));
        
        // Add submenu for Care management
        if ($this->is_care_active) {
            $wp_admin_bar->add_node(array(
                'parent' => 'replanta-care-status',
                'id' => 'care-dashboard',
                'title' => __('Dashboard de Care', 'replanta-hub'),
                'href' => 'https://care.replanta.com/dashboard',
                'meta' => array('target' => '_blank')
            ));
            
            $wp_admin_bar->add_node(array(
                'parent' => 'replanta-care-status',
                'id' => 'care-updates',
                'title' => __('Gestionar Actualizaciones', 'replanta-hub'),
                'href' => 'https://care.replanta.com/updates',
                'meta' => array('target' => '_blank')
            ));
        }
        
        // Add CSS for admin bar styling
        add_action('admin_head', array($this, 'admin_bar_styles'));
        add_action('wp_head', array($this, 'admin_bar_styles'));
    }
    
    /**
     * Add admin bar styles
     */
    public function admin_bar_styles() {
        ?>
        <style>
        #wp-admin-bar-replanta-care-status .ab-icon:before {
            content: "\f332";
            top: 2px;
        }
        #wp-admin-bar-replanta-care-status.care-active .ab-icon {
            color: #10b981;
        }
        #wp-admin-bar-replanta-care-status.care-inactive .ab-icon {
            color: #6b7280;
        }
        #wp-admin-bar-replanta-care-status.care-active:hover .ab-icon,
        #wp-admin-bar-replanta-care-status.care-active:hover .ab-label {
            color: #059669;
        }
        </style>
        <?php
    }
    
    /**
     * Add Care dashboard widget
     */
    public function add_care_dashboard_widget() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        wp_add_dashboard_widget(
            'replanta_care_status',
            ' ' . __('Replanta Care Status', 'replanta-hub'),
            array($this, 'render_care_dashboard_widget')
        );
    }
    
    /**
     * Render Care dashboard widget
     */
    public function render_care_dashboard_widget() {
        ?>
        <div class="care-dashboard-widget">
            <?php if ($this->is_care_active): ?>
                <div class="care-status active">
                    <div class="status-indicator"></div>
                    <div class="status-content">
                        <h4><?php _e('Care Activo', 'replanta-hub'); ?></h4>
                        <p><?php _e('Tu sitio está siendo gestionado profesionalmente.', 'replanta-hub'); ?></p>
                        <div class="care-actions">
                            <a href="https://care.replanta.com/dashboard" target="_blank" class="button button-primary">
                                <?php _e('Ver Dashboard', 'replanta-hub'); ?>
                            </a>
                            <a href="<?php echo admin_url('options-general.php?page=replanta-care'); ?>" class="button button-secondary">
                                <?php _e('Configuración', 'replanta-hub'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="care-features">
                    <h5><?php _e('Servicios Activos:', 'replanta-hub'); ?></h5>
                    <ul>
                        <li> <?php _e('Gestión automática de actualizaciones', 'replanta-hub'); ?></li>
                        <li> <?php _e('Monitoreo de seguridad 24/7', 'replanta-hub'); ?></li>
                        <li> <?php _e('Backups automáticos diarios', 'replanta-hub'); ?></li>
                        <li> <?php _e('Optimización de rendimiento', 'replanta-hub'); ?></li>
                    </ul>
                </div>
            <?php else: ?>
                <div class="care-status inactive">
                    <div class="status-indicator"></div>
                    <div class="status-content">
                        <h4><?php _e('Care No Conectado', 'replanta-hub'); ?></h4>
                        <p><?php _e('Conecta tu sitio para gestión profesional automática.', 'replanta-hub'); ?></p>
                        <div class="care-actions">
                            <a href="<?php echo admin_url('options-general.php?page=replanta-care'); ?>" class="button button-primary">
                                <?php _e('Conectar Care', 'replanta-hub'); ?>
                            </a>
                            <a href="https://care.replanta.com/signup" target="_blank" class="button button-secondary">
                                <?php _e('Crear Cuenta', 'replanta-hub'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .care-dashboard-widget .care-status {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 20px;
        }
        .care-dashboard-widget .status-indicator {
            font-size: 24px;
            flex-shrink: 0;
        }
        .care-dashboard-widget .status-content h4 {
            margin: 0 0 8px 0;
            font-size: 16px;
        }
        .care-dashboard-widget .status-content p {
            margin: 0 0 15px 0;
            color: #666;
        }
        .care-dashboard-widget .care-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .care-dashboard-widget .care-actions .button {
            margin: 0;
            padding: 6px 12px;
            font-size: 13px;
        }
        .care-dashboard-widget .care-features {
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
        .care-dashboard-widget .care-features h5 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #333;
        }
        .care-dashboard-widget .care-features ul {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .care-dashboard-widget .care-features li {
            margin-bottom: 5px;
            font-size: 13px;
            color: #666;
        }
        </style>
        <?php
    }
    
    /**
     * Enqueue update management assets
     */
    public function enqueue_update_assets($hook) {
        $update_pages = array(
            'update-core.php',
            'plugins.php', 
            'themes.php',
            'plugin-install.php',
            'theme-install.php'
        );
        
        if (!in_array($hook, $update_pages) && !$this->is_care_active) {
            return;
        }
        
        wp_add_inline_style('admin-bar', '
            .care-managed-text {
                color: #059669 !important;
                font-weight: 600;
            }
        ');
    }
    
    /**
     * Check if Care should manage updates
     */
    public function should_manage_updates() {
        return $this->is_care_active;
    }
    
    /**
     * Update Care status
     */
    public function update_care_status($status) {
        $this->care_status = $status;
        $this->is_care_active = $status['connected'] && $status['update_management'];
        update_option('rphub_care_status', $status);
        
        if ($this->is_care_active) {
            $this->init_update_controls();
        }
    }
}

// Initialize the Care update manager
new Replanta_Care_Update_Manager();
