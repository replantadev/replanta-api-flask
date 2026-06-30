<?php
/**
 * Plugin Name: Replanta Hub
 * Plugin URI: https://replanta.com/hub
 * Description: Hub de gestion centralizada para sitios WordPress mantenidos por Replanta. Monitorizacion, reportes, seguridad y conectividad con Replanta Care.
 * Version: 2.5.5
 * Author: Replanta
 * Author URI: https://replanta.com
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: replanta-hub
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('RPHUB_VERSION', '2.5.5');
define('RPHUB_PLUGIN_FILE', __FILE__);
define('RPHUB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RPHUB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RPHUB_PLUGIN_BASENAME', plugin_basename(__FILE__));

if (!defined('RPHUB_GITHUB_REPO_URL')) {
    define('RPHUB_GITHUB_REPO_URL', 'https://github.com/replantadev/hub/');
}

if (!defined('RPHUB_GITHUB_BRANCH')) {
    define('RPHUB_GITHUB_BRANCH', 'main');
}

// Load secure configuration if available
$config_file = RPHUB_PLUGIN_DIR . 'config.php';
if (file_exists($config_file)) {
    require_once $config_file;
} else {
    // Load sample config for constants
    require_once RPHUB_PLUGIN_DIR . 'config-sample.php';
}

// Load Action Scheduler (bundled via Composer; defers to WooCommerce's copy if newer)
if (file_exists(RPHUB_PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php')) {
    require_once RPHUB_PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
}

// Centralized task-queue wrapper (must load before any class that schedules)
require_once RPHUB_PLUGIN_DIR . 'includes/class-rphub-scheduler.php';

// Encryption helper (must load early ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â used by integration classes at construction)
require_once RPHUB_PLUGIN_DIR . 'includes/class-rphub-crypto.php';

// Sites service ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â single source of truth for managed sites (wraps wp_rphub_sites)
require_once RPHUB_PLUGIN_DIR . 'includes/class-rphub-sites.php';

// Third-party integrations broker + admin (Google, Cloudflare, PSI)
require_once RPHUB_PLUGIN_DIR . 'includes/class-rphub-integrations.php';
require_once RPHUB_PLUGIN_DIR . 'includes/class-rphub-integrations-admin.php';
require_once RPHUB_PLUGIN_DIR . 'includes/class-rphub-metrics.php';
require_once RPHUB_PLUGIN_DIR . 'includes/class-rphub-metrics-rest.php';

// Alerting dispatcher (Slack + email)
require_once RPHUB_PLUGIN_DIR . 'includes/class-rphub-alerting.php';

// Normalised REST API response envelope
require_once RPHUB_PLUGIN_DIR . 'includes/class-rphub-api-response.php';

// Auto-updates from GitHub (Private Repository)
if (file_exists(RPHUB_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once RPHUB_PLUGIN_DIR . 'vendor/autoload.php';
    
    try {
        // Check if the required classes exist before trying to use them
        if (class_exists('\YahnisElsts\PluginUpdateChecker\v5\PucFactory')) {
            $updateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
                RPHUB_GITHUB_REPO_URL,
                __FILE__,
                'replanta-hub'
            );
            
            // Set the branch that contains the stable release
            $updateChecker->setBranch(RPHUB_GITHUB_BRANCH);
            
            // Private repository access token - secure configuration
            $github_token = get_option('rphub_github_token');
            if (empty($github_token)) {
                $rphub_settings = get_option('rphub_settings', []);
                $github_token = $rphub_settings['github_token'] ?? '';
            }
            if (empty($github_token) && defined('RPHUB_GITHUB_TOKEN')) {
                $github_token = RPHUB_GITHUB_TOKEN;
            }
            if (empty($github_token)) {
                $github_token = getenv('RPHUB_GITHUB_TOKEN') ?: '';
            }
            
            if (!empty($github_token)) {
                $updateChecker->setAuthentication($github_token);
            }
            // Silenced: GitHub token not configured message ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â no spam in debug.log
            
            // Add error handling for private repos
            $updateChecker->addFilter('request_info_result', function($result) {
                if (is_wp_error($result)) {
                    error_log('Replanta Hub: Update checker error - ' . $result->get_error_message());
                }
                return $result;
            }, 10, 2);
        } else {
            error_log('Replanta Hub: Plugin Update Checker classes not found. Auto-updates disabled.');
        }
        
    } catch (\Throwable $e) {
        // Silently fail if update checker can't be initialized
        error_log('Replanta Hub: Update checker failed to initialize - ' . $e->getMessage());
    }
}

class ReplantaHub {
    
    private static $instance = null;
    
    // Integration properties
    private $whm_integration;
    private $wptoolkit_integration;
    private $backuply_integration;
    private $cloudflare_integration;
    private $litespeed_integration;
    private $pagespeed_integration;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init();
    }
    
    public function init() {
        // Hook into WordPress
        add_action('init', [$this, 'load_textdomain']);
        add_action('init', [$this, 'init_components']);
        add_action('admin_menu', [$this, 'add_admin_menu'], 5);
        add_action('admin_menu', [$this, 'add_admin_menu_late'], 30);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_init', [$this, 'register_settings']);
        
        // Check for database updates
        add_action('admin_init', [$this, 'check_database_update']);
        add_action('admin_init', [$this, 'ensure_tables_exist']);

        // REST endpoint for cards (bypasses admin-ajax.php CF WAF restrictions)
        add_action('rest_api_init', function() {
            register_rest_route('replanta-hub/v1', '/sites-cards', [
                'methods'             => 'GET',
                'callback'            => [$this, 'rest_get_sites_cards'],
                'permission_callback' => '__return_true',
            ]);
        });
        
        // Add missing AJAX handlers
        add_action('wp_ajax_rphub_get_recent_reports', [$this, 'ajax_get_recent_reports']);
        add_action('wp_ajax_rphub_get_sites_list', [$this, 'ajax_get_sites_list']);
        add_action('wp_ajax_rphub_test_whm_connection', [$this, 'ajax_test_whm_connection']);
        add_action('wp_ajax_rphub_whm_run_diagnostics', [$this, 'ajax_whm_run_diagnostics']);
        add_action('wp_ajax_rphub_get_notifications_count', [$this, 'ajax_get_notifications_count']);
        add_action('wp_ajax_rphub_get_dashboard_stats', [$this, 'ajax_get_dashboard_stats']);
        // Removed: rphub_get_notifications duplicate - handled by RPHUB_Notifications class
        add_action('wp_ajax_rphub_get_tasks', [$this, 'ajax_get_tasks']);
        add_action('wp_ajax_rphub_test_site_connection', [$this, 'ajax_test_site_connection']);
        add_action('wp_ajax_rphub_get_site_status', [$this, 'ajax_get_site_status']);
        add_action('wp_ajax_rphub_get_site_analytics', [$this, 'ajax_get_site_analytics']);

        // Removed: rphub_test_care_connection duplicate - handled by RPHUB_Site_Manager class
        // Removed: rphub_bulk_action duplicate - handled by ReplantaHub_Ajax_Handlers class
        add_action('wp_ajax_rphub_update_site_plan', [$this, 'ajax_update_site_plan']);
        add_action('wp_ajax_rphub_run_diagnostics', [$this, 'ajax_run_diagnostics']);
        add_action('wp_ajax_rphub_update_care_on_site', [$this, 'ajax_update_care_on_site']);
        add_action('wp_ajax_rphub_refresh_care_cache', [$this, 'ajax_refresh_care_cache']);
        add_action('wp_ajax_rphub_get_sites_cards', [$this, 'ajax_get_sites_cards']);
        add_action('wp_ajax_rphub_trigger_care_upgrade', [$this, 'ajax_trigger_care_upgrade']);
        add_action('wp_ajax_rphub_enrich_all_from_dr', [$this, 'ajax_enrich_all_from_dr']);
        add_action('wp_ajax_rphub_run_site_audit',    [$this, 'ajax_run_site_audit']);
        add_action('wp_ajax_rphub_apply_cf_fix',      [$this, 'ajax_apply_cf_fix']);
        add_action('wp_ajax_rphub_apply_plan_cf_fixes', [$this, 'ajax_apply_plan_cf_fixes']);
        add_action('wp_ajax_rphub_send_wp_fix',       [$this, 'ajax_send_wp_fix']);
        add_action('wp_ajax_rphub_send_plan_wp_fixes',    [$this, 'ajax_send_plan_wp_fixes']);
        add_action('wp_ajax_rphub_enrich_site_from_dr',   [$this, 'ajax_enrich_site_from_dr']);
        add_action('wp_ajax_rphub_get_sa_issues',         [$this, 'ajax_get_sa_issues']);
        add_action('wp_ajax_rphub_run_sa_fix',            [$this, 'ajax_run_sa_fix']);
        
        // Schedule cron job hooks
        add_action('rphub_daily_check', [$this, 'run_daily_checks']);
        add_action('rphub_hourly_monitoring', [$this, 'run_hourly_monitoring']);
        add_action('rphub_litespeed_optimize', [$this, 'run_litespeed_optimization']);
        add_action('rphub_wptoolkit_vulnerability_scan', [$this, 'run_vulnerability_scan']);
        add_action('rphub_pagespeed_analysis', [$this, 'run_pagespeed_analysis']);
        add_action('rphub_backuply_check', [$this, 'run_backuply_check']);
        add_action('rphub_cloudflare_sync', [$this, 'run_cloudflare_sync']);
        
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        
        // Load required files
        $this->load_dependencies();
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('replanta-hub', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function load_dependencies() {
        // Core foundation classes (includes/)
        require_once RPHUB_PLUGIN_DIR . 'includes/class-error-manager.php';
        require_once RPHUB_PLUGIN_DIR . 'includes/class-rphub-scheduler.php';
        require_once RPHUB_PLUGIN_DIR . 'includes/class-query-optimizer.php';
        require_once RPHUB_PLUGIN_DIR . 'includes/class-rate-limiter.php';
        require_once RPHUB_PLUGIN_DIR . 'includes/class-database.php';
        // Instantiate DB so migration hooks (admin_init ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ check_database_version) register
        new ReplantaHub_Database();
        require_once RPHUB_PLUGIN_DIR . 'includes/class-setup-wizard.php';
        require_once RPHUB_PLUGIN_DIR . 'includes/class-ajax-handlers.php';
        require_once RPHUB_PLUGIN_DIR . 'includes/class-enhanced-dashboard.php';
        require_once RPHUB_PLUGIN_DIR . 'includes/class-enhanced-database-setup.php';
        require_once RPHUB_PLUGIN_DIR . 'includes/class-admin-utils.php';
        require_once RPHUB_PLUGIN_DIR . 'includes/system-initializer.php';
        require_once RPHUB_PLUGIN_DIR . 'includes/class-analytics-integration.php';
        require_once RPHUB_PLUGIN_DIR . 'includes/class-rum-collector.php';
        require_once RPHUB_PLUGIN_DIR . 'includes/class-analytics-settings.php';
        require_once RPHUB_PLUGIN_DIR . 'includes/class-analytics-schema.php';
        require_once RPHUB_PLUGIN_DIR . 'includes/class-comparative-analytics.php';
        require_once RPHUB_PLUGIN_DIR . 'includes/class-automation-workflows.php';
        require_once RPHUB_PLUGIN_DIR . 'includes/class-intelligent-maintenance.php';
        require_once RPHUB_PLUGIN_DIR . 'includes/class-api-system.php';
        require_once RPHUB_PLUGIN_DIR . 'includes/class-api-schema.php';
        require_once RPHUB_PLUGIN_DIR . 'includes/class-api-tokens.php';
        require_once RPHUB_PLUGIN_DIR . 'includes/class-multisite-manager.php';
        require_once RPHUB_PLUGIN_DIR . 'includes/class-multisite-schema.php';
        require_once RPHUB_PLUGIN_DIR . 'includes/class-multisite-admin.php';
        require_once RPHUB_PLUGIN_DIR . 'includes/class-security-framework.php';
        require_once RPHUB_PLUGIN_DIR . 'includes/class-security-schema.php';
        require_once RPHUB_PLUGIN_DIR . 'includes/class-security-admin.php';
        
        // @removed v1.5.1 ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â Phase 8.0 AI/Analytics stubs eliminated (zero real functionality):
        // class-ai-threat-predictor.php, class-advanced-analytics-dashboard.php,
        // class-behavioral-pattern-recognition.php, class-predictive-security-reports.php,
        // class-neural-network-security.php, class-hub-instructions.php, class-care-features.php
        require_once RPHUB_PLUGIN_DIR . 'includes/class-care-update-manager.php';
        require_once RPHUB_PLUGIN_DIR . 'includes/class-uptime-monitoring.php';
        require_once RPHUB_PLUGIN_DIR . 'includes/class-smart-updates.php';
        require_once RPHUB_PLUGIN_DIR . 'includes/class-report-generator.php';

        // Main functionality classes (inc/)
        require_once RPHUB_PLUGIN_DIR . 'inc/class-ftp-recovery.php';
        require_once RPHUB_PLUGIN_DIR . 'inc/class-site-manager.php';
        require_once RPHUB_PLUGIN_DIR . 'inc/class-task-orchestrator.php';
        require_once RPHUB_PLUGIN_DIR . 'inc/class-api-client.php';
        require_once RPHUB_PLUGIN_DIR . 'inc/class-reports.php';
        require_once RPHUB_PLUGIN_DIR . 'inc/class-notifications.php';
        require_once RPHUB_PLUGIN_DIR . 'inc/class-bulk-actions.php';
        require_once RPHUB_PLUGIN_DIR . 'inc/class-utils.php';
        require_once RPHUB_PLUGIN_DIR . 'inc/class-rest-api.php';
        require_once RPHUB_PLUGIN_DIR . 'inc/class-plans-manager.php';
        require_once RPHUB_PLUGIN_DIR . 'inc/class-reports-system.php';
        require_once RPHUB_PLUGIN_DIR . 'inc/class-automation-system.php';
        require_once RPHUB_PLUGIN_DIR . 'inc/class-update-manager.php';
        require_once RPHUB_PLUGIN_DIR . 'inc/class-dr-bridge.php';
        require_once RPHUB_PLUGIN_DIR . 'inc/class-cf-audit.php';
        require_once RPHUB_PLUGIN_DIR . 'inc/class-seo-audit.php';
        require_once RPHUB_PLUGIN_DIR . 'inc/class-perf-audit.php';
        require_once RPHUB_PLUGIN_DIR . 'inc/class-site-auditor.php';
        require_once RPHUB_PLUGIN_DIR . 'inc/class-cf-fixer.php';
        require_once RPHUB_PLUGIN_DIR . 'inc/class-cf-onboarding-engine.php';
        require_once RPHUB_PLUGIN_DIR . 'inc/class-wp-fixer.php';
        
        // Integration classes (inc/) - Load with error handling
        $integrations = [
            'inc/class-whm-integration.php',
            'inc/class-litespeed-integration.php',
            'inc/class-wptoolkit-integration.php',
            'inc/class-pagespeed-integration.php',
            'inc/class-backuply-integration.php',
            'inc/class-cloudflare-integration.php',
            'inc/class-pagespeed-advanced.php',
            'inc/class-wptoolkit-advanced.php',
            'inc/class-backuply-advanced.php',
            'inc/class-cloudflare-advanced.php',
            'inc/class-security-panel.php',
            'inc/class-deploy.php',
            // Sprint 1 ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â backup & care
            'inc/class-backblaze-integration.php',
            'inc/class-backup-aggregator.php',
            // Sprint 2 ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â AI risk scoring & delta reports
            'inc/class-risk-scorer.php',
            'inc/class-delta-reporter.php',
        ];
        
        foreach ($integrations as $integration) {
            $file_path = RPHUB_PLUGIN_DIR . $integration;
            if (file_exists($file_path)) {
                require_once $file_path;
                if (defined('RPHUB_DEBUG') && RPHUB_DEBUG) {
                    error_log("ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã¢â‚¬Å“ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¦ RPHUB: Loaded integration: " . basename($integration));
                }
            } else {
                if (defined('RPHUB_DEBUG') && RPHUB_DEBUG) {
                    error_log("ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ RPHUB: Missing integration: " . $integration);
                }
            }
        }
        
        // Diagnostic tools
        require_once RPHUB_PLUGIN_DIR . 'diagnostics.php';
        
        // System tests (debug mode only)
        if (defined('RPHUB_DEBUG') && RPHUB_DEBUG) {
            if (file_exists(RPHUB_PLUGIN_DIR . 'includes/system-tests.php')) {
                require_once RPHUB_PLUGIN_DIR . 'includes/system-tests.php';
            }
        }
        
        // Admin pages
        if (is_admin()) {
            require_once RPHUB_PLUGIN_DIR . 'inc/admin-dashboard.php';
            require_once RPHUB_PLUGIN_DIR . 'inc/admin-sites.php';
            require_once RPHUB_PLUGIN_DIR . 'inc/admin-reports.php';
            // class-smart-updates.php loaded above (required by report-generator on all requests)
            // Advanced integration classes already loaded above in the integrations loop
        }
    }
    
    public function init_components() {
        // Initialize core components
        new RPHUB_Site_Manager();
        new RPHUB_Task_Orchestrator();
        new RPHUB_Notifications();
        new RPHUB_Plans_Manager();

        // Register wizard AJAX handlers early (not just on page render)
        if (class_exists('ReplantaHub_Setup_Wizard')) {
            new ReplantaHub_Setup_Wizard();
        }
        
        // Initialize enhanced dashboard
        if (class_exists('RP_Hub_Enhanced_Dashboard')) {
            new RP_Hub_Enhanced_Dashboard();
        }
        
        // Initialize advanced system components
        // Globals first ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ReportGenerator reads these in init_data_sources()
        if (class_exists('RphubUptimeMonitoring')) {
            $GLOBALS['rphub_uptime_monitoring'] = new RphubUptimeMonitoring();
        }

        // Sprint 1 ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â Backblaze B2 + Backup Aggregator
        if (class_exists('ReplantaHub_Backblaze_Integration')) {
            $GLOBALS['rphub_backblaze'] = ReplantaHub_Backblaze_Integration::get_instance();
        }
        if (class_exists('RPHUB_Backup_Aggregator')) {
            $GLOBALS['rphub_backup_aggregator'] = RPHUB_Backup_Aggregator::get_instance();
        }

        if (class_exists('RPHUB_CF_Onboarding_Engine')) {
            $GLOBALS['rphub_cf_onboarding'] = RPHUB_CF_Onboarding_Engine::get_instance();
        }

        // Sprint 2 ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â Risk Scorer + Delta Reporter
        if (class_exists('RPHUB_Risk_Scorer')) {
            $GLOBALS['rphub_risk_scorer'] = RPHUB_Risk_Scorer::get_instance();
        }
        if (class_exists('RPHUB_Delta_Reporter')) {
            $GLOBALS['rphub_delta_reporter'] = RPHUB_Delta_Reporter::get_instance();
        }
        if (class_exists('ReplantaHub_Analytics_Integration')) {
            $GLOBALS['rphub_analytics'] = new ReplantaHub_Analytics_Integration();
        }

        if (class_exists('RphubReportGenerator')) {
            new RphubReportGenerator();
        }

        if (class_exists('RPHUB_Update_Manager')) {
            new RPHUB_Update_Manager();
        }
        
        if (class_exists('ReplantaHub_RUM_Collector')) {
            new ReplantaHub_RUM_Collector();
        }
        
        if (class_exists('ReplantaHub_Analytics_Settings')) {
            new ReplantaHub_Analytics_Settings();
        }
        
        if (class_exists('ReplantaHub_Analytics_Schema')) {
            new ReplantaHub_Analytics_Schema();
        }
        
        if (class_exists('ReplantaHub_Comparative_Analytics')) {
            new ReplantaHub_Comparative_Analytics();
        }
        
        if (class_exists('ReplantaHub_Automation_Workflows')) {
            new ReplantaHub_Automation_Workflows();
        }
        
        if (class_exists('ReplantaHub_Intelligent_Maintenance')) {
            new ReplantaHub_Intelligent_Maintenance();
        }
        
        if (class_exists('ReplantaHub_API_Schema')) {
            new ReplantaHub_API_Schema();
        }
        
        if (class_exists('ReplantaHub_API_Tokens')) {
            new ReplantaHub_API_Tokens();
        }
        
        if (class_exists('ReplantaHub_API_System')) {
            new ReplantaHub_API_System();
        }

        if (class_exists('ReplantaHub_Deploy')) {
            new ReplantaHub_Deploy();
        }
        
        if (class_exists('RPHUB_Multisite_Schema')) {
            new RPHUB_Multisite_Schema();
        }
        
        if (class_exists('RPHUB_Multisite_Manager')) {
            new RPHUB_Multisite_Manager();
        }
        
        if (class_exists('RPHUB_Multisite_Admin')) {
            new RPHUB_Multisite_Admin();
        }
        
        if (class_exists('RPHUB_Security_Framework')) {
            new RPHUB_Security_Framework();
        }
        
        if (class_exists('RPHUB_Security_Schema')) {
            new RPHUB_Security_Schema();
        }
        
        if (class_exists('RPHUB_Security_Admin')) {
            new RPHUB_Security_Admin();
        }
        
        // @removed v1.5.1 ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â AI/Analytics stubs eliminated (RPHUB_AI_Threat_Predictor,
        // RPHUB_Advanced_Analytics_Dashboard, behavioral-pattern, predictive-security, neural-network)
        
        // Initialize integrations with error handling
        $this->init_integrations();
        
        // Schedule cron jobs
        $this->schedule_cron_jobs();
    }
    
    private function init_integrations() {
        // Map: AJAX action prefix -> the only integration class needed for that request.
        // On AJAX calls we instantiate only the relevant integration, skipping the rest.
        $ajax_to_class = [
            'rphub_cloudflare_' => 'ReplantaHub_Cloudflare_Integration',
            'rphub_pagespeed_'  => 'ReplantaHub_PageSpeed_Integration',
            'rphub_litespeed_'  => 'ReplantaHub_LiteSpeed_Integration',
            'rphub_whm_'        => 'RPHUB_WHM_Integration',
            'rphub_backuply_'   => 'ReplantaHub_Backuply_Integration',
            'rphub_wptoolkit_'  => 'ReplantaHub_WPToolkit_Integration',
        ];

        $required_class = null;
        if ( wp_doing_ajax() ) {
            $action = sanitize_key( $_REQUEST['action'] ?? '' );
            foreach ( $ajax_to_class as $prefix => $class ) {
                if ( strpos( $action, $prefix ) === 0 ) {
                    $required_class = $class;
                    break;
                }
            }
            // AJAX call not related to any integration -- skip all of them.
            if ( $required_class === null ) {
                return;
            }
        }

        $integrations = [
            'RPHUB_WHM_Integration'              => 'whm_integration',
            'ReplantaHub_LiteSpeed_Integration'  => 'litespeed_integration',
            'ReplantaHub_WPToolkit_Integration'  => 'wptoolkit_integration',
            'ReplantaHub_PageSpeed_Integration'  => 'pagespeed_integration',
            'ReplantaHub_Backuply_Integration'   => 'backuply_integration',
            'ReplantaHub_Cloudflare_Integration' => 'cloudflare_integration',
        ];

        foreach ($integrations as $class_name => $property) {
            // Lazy: on AJAX, skip every class except the one this request needs.
            if ( $required_class !== null && $class_name !== $required_class ) {
                continue;
            }
            if (class_exists($class_name)) {
                try {
                    $this->{$property} = new $class_name();
                    if (defined('RPHUB_DEBUG') && RPHUB_DEBUG) {
                        error_log('RPHUB: Initialized integration: ' . $class_name);
                    }
                } catch (\Throwable $e) {
                    error_log('RPHUB: Failed to initialize ' . $class_name . ': ' . $e->getMessage());
                }
            } else {
                if (defined('RPHUB_DEBUG') && RPHUB_DEBUG) {
                    error_log('RPHUB: Class not found: ' . $class_name);
                }
            }
        }
    }
    public function add_admin_menu() {
        add_menu_page(
            'Replanta Hub',
            'Replanta Hub',
            'manage_options',
            'replanta-hub',
            [$this, 'dashboard_page'],
            'dashicons-networking',
            3
        );

        // F1.3: 5 entries ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â Panel, Sitios, Operaciones, [Integraciones at p20], Ajustes [at p30]
        // F1.4: First submenu labeled "Panel" (not "Dashboard") ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â the wp-submenu-head shows
        //       "Replanta Hub" (non-interactive), so "Panel" below it is unambiguous.
        add_submenu_page(
            'replanta-hub',
            'Panel',
            'Panel',
            'manage_options',
            'replanta-hub',
            [$this, 'dashboard_page']
        );

        add_submenu_page(
            'replanta-hub',
            'Sitios',
            'Sitios',
            'manage_options',
            'replanta-hub-sites',
            [$this, 'sites_page']
        );

        add_submenu_page(
            'replanta-hub',
            'Operaciones',
            'Operaciones',
            'manage_options',
            'replanta-hub-operations',
            [$this, 'operations_page']
        );

        add_submenu_page(
            'replanta-hub',
            'Reportes',
            'Reportes',
            'manage_options',
            'replanta-hub-reports',
            [$this, 'reports_page']
        );

        if (get_option('rphub_show_experimental', false)) {
            add_submenu_page(
                'replanta-hub',
                'SEO Autopilot',
                'SEO Autopilot',
                'manage_options',
                'replanta-hub-seo-autopilot',
                [$this, 'seo_autopilot_page']
            );
        }

        add_submenu_page(
            'replanta-hub',
            'Ecosistema',
            'Ecosistema',
            'manage_options',
            'replanta-hub-ecological',
            [$this, 'ecological_page']
        );
        // Integraciones registered by RPHUB_Integrations_Admin at priority 20 (between here and Ajustes)
    }

    public function add_admin_menu_late() {
        add_submenu_page(
            'replanta-hub',
            'Ajustes',
            'Ajustes',
            'manage_options',
            'replanta-hub-settings',
            [$this, 'settings_page']
        );

        $wizard_completed = get_option('rphub_setup_completed', false);
        add_submenu_page(
            $wizard_completed ? null : 'replanta-hub',
            'Asistente de Configuracion',
            'Wizard',
            'manage_options',
            'replanta-hub-wizard',
            [$this, 'wizard_page']
        );

        add_submenu_page(
            null,
            'Diagnosticos',
            'Diagnosticos',
            'manage_options',
            'replanta-hub-diagnostics',
            [$this, 'diagnostics_page']
        );
    }
    
    public function dashboard_page() {
        $dashboard = new RPHUB_Admin_Dashboard();
        $dashboard->render();
    }

    public function sites_page() {
        $sites = new RPHUB_Admin_Sites();
        $sites->render();
    }

    public function operations_page() {
        if (file_exists(RPHUB_PLUGIN_DIR . 'inc/admin-operations.php')) {
            include_once RPHUB_PLUGIN_DIR . 'inc/admin-operations.php';
            $ops = new RPHUB_Admin_Operations();
            $ops->render();
        }
    }

    public function reports_page() {
        $reports = new RPHUB_Admin_Reports();
        $reports->render();
    }

    public function seo_autopilot_page() {
        if (file_exists(RPHUB_PLUGIN_DIR . 'inc/admin-seo-autopilot.php')) {
            include_once RPHUB_PLUGIN_DIR . 'inc/admin-seo-autopilot.php';
            $ap = new RPHUB_Admin_SeoAutopilot();
            $ap->render();
        }
    }

    public function ecological_page() {
        if (file_exists(RPHUB_PLUGIN_DIR . 'inc/admin-ecological.php')) {
            include_once RPHUB_PLUGIN_DIR . 'inc/admin-ecological.php';
            $eco = new RPHUB_Admin_Ecological();
            $eco->render();
        }
    }
    
    public function settings_page() {
        // Load settings page
        if (file_exists(RPHUB_PLUGIN_DIR . 'inc/admin-settings.php')) {
            include_once RPHUB_PLUGIN_DIR . 'inc/admin-settings.php';
            $settings = new RPHUB_Admin_Settings();
            $settings->render();
        } else {
            $this->render_simple_settings_page();
        }
    }
    
    public function wizard_page() {
        // Load setup wizard
        if (class_exists('ReplantaHub_Setup_Wizard')) {
            $wizard = new ReplantaHub_Setup_Wizard();
            $wizard->display_setup_wizard();
        } else {
            echo '<div class="wrap"><h1>Asistente de Configuracion</h1><p>El asistente de configuracion no esta disponible.</p></div>';
        }
    }
    
    public function diagnostics_page() {
        // Use the comprehensive diagnostics function
        if (function_exists('rphub_run_comprehensive_diagnostics')) {
            rphub_run_comprehensive_diagnostics();
        } else {
            $test_file = RPHUB_PLUGIN_DIR . 'admin-test.php';
            if (file_exists($test_file) && filesize($test_file) > 0) {
                include_once $test_file;
            } else {
                include_once RPHUB_PLUGIN_DIR . 'diagnostics.php';
                if (function_exists('rphub_run_comprehensive_diagnostics')) {
                    rphub_run_comprehensive_diagnostics();
                }
            }
        }
    }
    
    private function render_simple_settings_page() {
        ?>
        <div class="wrap">
            <h1>Configuracion General</h1>
            <form method="post" action="options.php">
                <?php settings_fields('rphub_settings'); ?>
                <?php do_settings_sections('rphub_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">WHM Host</th>
                        <td><input type="text" name="rphub_whm_host" value="<?php echo esc_attr(get_option('rphub_whm_host')); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row">WHM Username</th>
                        <td><input type="text" name="rphub_whm_username" value="<?php echo esc_attr(get_option('rphub_whm_username')); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row">WHM Token</th>
                        <td><input type="password" name="rphub_whm_token" value="<?php echo esc_attr(get_option('rphub_whm_token')); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row">PageSpeed API Key</th>
                        <td><input type="text" name="rphub_pagespeed_api_key" value="<?php echo esc_attr(get_option('rphub_pagespeed_api_key')); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Cloudflare API Key</th>
                        <td><input type="text" name="rphub_cloudflare_api_key" value="<?php echo esc_attr(get_option('rphub_cloudflare_api_key')); ?>" /></td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    public function register_settings() {
        // Register setting group
        register_setting('rphub_settings', 'rphub_whm_host');
        register_setting('rphub_settings', 'rphub_whm_username');
        register_setting('rphub_settings', 'rphub_whm_token');
        register_setting('rphub_settings', 'rphub_pagespeed_api_key');
        register_setting('rphub_settings', 'rphub_cloudflare_api_key');
        register_setting('rphub_settings', 'rphub_cloudflare_email');
        register_setting('rphub_settings', 'rphub_litespeed_api_key');
        register_setting('rphub_settings', 'rphub_backuply_enabled');
        register_setting('rphub_settings', 'rphub_wptoolkit_api_key');
        register_setting('rphub_settings', 'rphub_github_token');
    }
    
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'replanta-hub') === false) {
            return;
        }
        
        // Debug: Log the current hook
        if (defined('RPHUB_DEBUG') && RPHUB_DEBUG) {
            error_log("ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â°ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Âµ RPHUB: Enqueuing assets for hook: " . $hook);
        }
        
        wp_enqueue_style(
            'rphub-admin',
            RPHUB_PLUGIN_URL . 'assets/css/admin.css',
            [],
            RPHUB_VERSION
        );
        
        wp_enqueue_script(
            'rphub-admin',
            RPHUB_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'jquery-ui-sortable'],
            RPHUB_VERSION,
            true
        );

        // Enhanced admin functionality
        wp_enqueue_script(
            'rphub-enhanced-admin',
            RPHUB_PLUGIN_URL . 'assets/js/enhanced-admin.js',
            ['jquery', 'rphub-admin'],
            RPHUB_VERSION,
            true
        );
        
        // Chart.js for analytics
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js',
            [],
            '3.9.1',
            true
        );
        
        // Create nonce and AJAX data
        $ajax_data = [
            'ajax_url'       => admin_url('admin-ajax.php'),
            'nonce'          => wp_create_nonce('rphub_ajax'),
            'admin_nonce'    => wp_create_nonce('rphub_admin'),
            'rest_url'       => rest_url('replanta-hub/v1/'),
            'rest_nonce'     => wp_create_nonce('wp_rest'),
            'strings' => [
                'adding_site' => 'Anadiendo sitio...',
                'site_added' => 'Sitio anadido correctamente',
                'error_adding_site' => 'Error al anadir sitio',
                'running_task' => 'Ejecutando tarea...',
                'task_completed' => 'Tarea completada',
                'task_failed' => 'Error en la tarea',
                'confirm_delete' => 'Estas seguro de que quieres eliminar este sitio?',
                'bulk_action_confirm' => 'Ejecutar esta accion en los sitios seleccionados?'
            ]
        ];
        
        // Localize script AFTER enqueuing
        wp_localize_script('rphub-admin', 'rphub_ajax', $ajax_data);
        
        // Add inline script to ensure variables are available
        wp_add_inline_script(
            'rphub-admin',
            'var rphub_ajax = ' . wp_json_encode($ajax_data) . ';' . "\n" .
            'var ajaxurl = "' . esc_js(admin_url('admin-ajax.php')) . '";',
            'before'
        );
        
        // Debug: Confirm localization
        if (defined('RPHUB_DEBUG') && RPHUB_DEBUG) {
            error_log("ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â°ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Âµ RPHUB: Scripts and variables localized successfully");
        }
        wp_add_inline_style('rphub-admin',
            '.rphub-ver{display:inline-block;font-size:11px;font-weight:500;color:#646970;background:#f0f0f1;border:1px solid #dcdcde;border-radius:10px;padding:1px 8px;margin-left:8px;vertical-align:middle;line-height:20px;letter-spacing:.2px;}'
        );
        wp_add_inline_script('rphub-admin',
            'document.addEventListener("DOMContentLoaded",function(){' .
            'var h=document.querySelector(".wrap .wp-heading-inline,.wrap h1");' .
            'if(h&&!h.querySelector(".rphub-ver")){' .
            'var b=document.createElement("span");b.className="rphub-ver";b.textContent="v' . RPHUB_VERSION . '";' .
            'h.appendChild(b);}});',
        'after');
    }
    
    /**
     * Schedule recurring actions via Action Scheduler (falls back to WP Cron
     * automatically when AS is not yet initialized on first load).
     */
    private function schedule_cron_jobs() {
        RPHUB_Scheduler::schedule('rphub_daily_check',                  'daily');
        RPHUB_Scheduler::schedule('rphub_hourly_monitoring',            'hourly');
        RPHUB_Scheduler::schedule('rphub_litespeed_optimize',           'daily');
        RPHUB_Scheduler::schedule('rphub_wptoolkit_vulnerability_scan', 'daily');
        RPHUB_Scheduler::schedule('rphub_pagespeed_analysis',           'twicedaily');
        RPHUB_Scheduler::schedule('rphub_backuply_check',               'twicedaily');
        RPHUB_Scheduler::schedule('rphub_cloudflare_sync',              'hourly');
    }

    public function activate() {
        // Force create database tables using our enhanced setup class
        if (class_exists('RPHUB_Enhanced_Database_Setup')) {
            RPHUB_Enhanced_Database_Setup::create_all_tables();
        } else {
            // Fallback to original method
            $this->create_tables();
        }
        
        // Create analytics tables
        if (class_exists('ReplantaHub_Analytics_Schema')) {
            ReplantaHub_Analytics_Schema::create_analytics_tables();
        }
        
        // Create plans tables
        if (class_exists('RPHUB_Plans_Manager')) {
            RPHUB_Plans_Manager::create_tables();
        }
        
        // Create database indexes for optimization
        if (class_exists('ReplantaHub_Query_Optimizer')) {
            ReplantaHub_Query_Optimizer::create_indexes();
        }
        
        // Set default options
        add_option('rphub_version', RPHUB_VERSION);
        add_option('rphub_db_version', '1.1');
        add_option('rphub_activated', true);
        
        // Migrate single-server WHM config to multi-server format
        if (class_exists('RPHUB_WHM_Integration')) {
            RPHUB_WHM_Integration::maybe_migrate_to_multi_server();
        }
        
        // Demo data insertion removed (Phase 3 cleanup)
        
        // Create reports table with full schema (report_id, report_html, etc.)
        if (class_exists('RphubReportGenerator')) {
            RphubReportGenerator::create_reports_table();
        }

        // Schedule recurring tasks via Action Scheduler
        RPHUB_Scheduler::schedule('rphub_hourly_tasks',                  'hourly');
        RPHUB_Scheduler::schedule('rphub_daily_tasks',                   'daily');
        RPHUB_Scheduler::schedule('rphub_generate_scheduled_reports',    'daily');
        
        // Create upload directory for reports
        $upload_dir = wp_upload_dir();
        $reports_dir = $upload_dir['basedir'] . '/rphub-reports';
        if (!file_exists($reports_dir)) {
            wp_mkdir_p($reports_dir);
            
            // Create .htaccess to protect reports
            $htaccess_content = "Order deny,allow\nDeny from all\n";
            file_put_contents($reports_dir . '/.htaccess', $htaccess_content);
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Cancel all scheduled actions (Action Scheduler + WP Cron fallback)
        RPHUB_Scheduler::cancel('rphub_hourly_tasks');
        RPHUB_Scheduler::cancel('rphub_daily_tasks');
        RPHUB_Scheduler::cancel('rphub_generate_scheduled_reports');

        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Sites table
        $table_sites = $wpdb->prefix . 'rphub_sites';
        $sql_sites = "CREATE TABLE $table_sites (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            url varchar(500) NOT NULL,
            token varchar(255) NOT NULL,
            api_key varchar(255) DEFAULT '',
            plan varchar(20) NOT NULL DEFAULT 'semilla',
            status varchar(20) NOT NULL DEFAULT 'active',
            last_check datetime DEFAULT NULL,
            last_success datetime DEFAULT NULL,
            health_score int(3) DEFAULT 0,
            wp_version varchar(20) DEFAULT '',
            php_version varchar(20) DEFAULT '',
            plugins_count int(5) DEFAULT 0,
            themes_count int(5) DEFAULT 0,
            updates_available int(5) DEFAULT 0,
            security_issues int(5) DEFAULT 0,
            notes text,
            whm_account varchar(100) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY url (url),
            KEY status (status),
            KEY plan (plan),
            KEY last_check (last_check)
        ) $charset_collate;";
        
        // Tasks table
        $table_tasks = $wpdb->prefix . 'rphub_tasks';
        $sql_tasks = "CREATE TABLE $table_tasks (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            site_id mediumint(9) NOT NULL,
            task_type varchar(50) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            priority int(2) NOT NULL DEFAULT 5,
            scheduled_at datetime NOT NULL,
            started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            result longtext,
            error_message text,
            retry_count int(2) DEFAULT 0,
            max_retries int(2) DEFAULT 3,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY site_id (site_id),
            KEY task_type (task_type),
            KEY status (status),
            KEY scheduled_at (scheduled_at)
        ) $charset_collate;";
        
        // Reports table
        $table_reports = $wpdb->prefix . 'rphub_reports';
        $sql_reports = "CREATE TABLE $table_reports (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            site_id mediumint(9) NOT NULL,
            report_type varchar(50) NOT NULL,
            period varchar(20) NOT NULL,
            data longtext NOT NULL,
            file_path varchar(500) DEFAULT '',
            email_sent tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY site_id (site_id),
            KEY report_type (report_type),
            KEY period (period),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Notifications table
        $table_notifications = $wpdb->prefix . 'rphub_notifications';
        $sql_notifications = "CREATE TABLE $table_notifications (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            site_id mediumint(9) DEFAULT NULL,
            type varchar(50) NOT NULL,
            severity varchar(20) NOT NULL DEFAULT 'info',
            title varchar(255) NOT NULL,
            message text NOT NULL,
            data text,
            read_status tinyint(1) DEFAULT 0,
            email_sent tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY site_id (site_id),
            KEY type (type),
            KEY severity (severity),
            KEY read_status (read_status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_sites);
        dbDelta($sql_tasks);
        dbDelta($sql_reports);
        dbDelta($sql_notifications);
        
        update_option('rphub_db_version', '1.1');
    }
    
    public function check_database_update() {
        $current_db_version = get_option('rphub_db_version', '0');

        if (version_compare($current_db_version, '1.1', '<')) {
            $this->update_database_to_1_1();
        }

        if (version_compare($current_db_version, '1.2', '<')) {
            $this->update_database_to_1_2();
        }

        if (version_compare($current_db_version, '1.3', '<')) {
            $this->update_database_to_1_3();
        }

        if (version_compare($current_db_version, '1.4', '<')) {
            $this->update_database_to_1_4();
        }
    }
    
    private function update_database_to_1_1() {
        global $wpdb;

        $table_sites = $wpdb->prefix . 'rphub_sites';

        // Update existing plan values from old to new
        $wpdb->query("UPDATE {$table_sites} SET plan = 'basic' WHERE plan = 'semilla'");
        $wpdb->query("UPDATE {$table_sites} SET plan = 'advanced' WHERE plan = 'raiz'");
        $wpdb->query("UPDATE {$table_sites} SET plan = 'premium' WHERE plan = 'ecosistema'");

        // Ensure all tables exist (in case activation didn't work properly)
        $this->create_tables();

        update_option('rphub_db_version', '1.1');
    }

    private function update_database_to_1_2() {
        global $wpdb;
        $table_sites = $wpdb->prefix . 'rphub_sites';

        $col = $wpdb->get_results("SHOW COLUMNS FROM {$table_sites} LIKE 'client_name'");
        if (empty($col)) {
            $wpdb->query("ALTER TABLE {$table_sites} ADD COLUMN client_name varchar(255) DEFAULT '' AFTER name");
        }
        $col2 = $wpdb->get_results("SHOW COLUMNS FROM {$table_sites} LIKE 'care_url'");
        if (empty($col2)) {
            $wpdb->query("ALTER TABLE {$table_sites} ADD COLUMN care_url varchar(500) DEFAULT '' AFTER token");
        }

        update_option('rphub_db_version', '1.2');
    }
    
    private function update_database_to_1_3() {
        global $wpdb;
        $table_sites = $wpdb->prefix . 'rphub_sites';
        $wpdb->query("UPDATE {$table_sites} SET plan = 'semilla'    WHERE plan = 'basic'");
        $wpdb->query("UPDATE {$table_sites} SET plan = 'raiz'       WHERE plan = 'advanced'");
        $wpdb->query("UPDATE {$table_sites} SET plan = 'ecosistema' WHERE plan = 'premium'");
        update_option('rphub_db_version', '1.3');
    }

    private function update_database_to_1_4() {
        // Ensure rphub_reports has all columns from both schema versions.
        // The original schema (create_tables) had: id, site_id, report_type, period, data, file_path, email_sent, created_at
        // RphubReportGenerator::create_reports_table() added: report_id, report_html, report_data, config, generated_at, sent_at, status
        if (class_exists('RphubReportGenerator')) {
            RphubReportGenerator::create_reports_table();
        }
        update_option('rphub_db_version', '1.4');
    }

    public function ensure_tables_exist() {
        // Only check once per session to avoid repeated queries
        if (get_transient('rphub_tables_checked')) {
            return;
        }
        
        // Use enhanced database setup
        if (class_exists('RPHUB_Enhanced_Database_Setup')) {
            $status = RPHUB_Enhanced_Database_Setup::get_setup_status();
            
            if (!$status['is_complete']) {
                // Log the missing tables
                error_log('Replanta Hub: Missing database tables: ' . implode(', ', $status['missing_list']));
                
                // Try to create them
                RPHUB_Enhanced_Database_Setup::create_all_tables();
                
                // Add admin notice
                add_action('admin_notices', function() use ($status) {
                    $setup_url = admin_url('admin.php?rphub_setup_enhanced=1');
                    echo '<div class="notice notice-warning">';
                    echo '<p><strong>Replanta Hub:</strong> Enhanced database setup is required. ';
                    echo '<a href="' . $setup_url . '" class="button button-primary">Run Enhanced Setup</a></p>';
                    echo '</div>';
                });
            }
        }
        
        // Set transient for 1 hour to avoid repeated checks
        set_transient('rphub_tables_checked', true, HOUR_IN_SECONDS);
    }
    
    public function is_activated() {
        return get_option('rphub_activated', false);
    }
    
    /**
     * AJAX handlers for missing functions
     */
    public function ajax_get_recent_reports() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        global $wpdb;
        $table_reports = $wpdb->prefix . 'rphub_reports';
        $table_sites   = $wpdb->prefix . 'rphub_sites';
        // Exclude large text columns (report_html, report_data, config, data) from listing
        $recent_reports = $wpdb->get_results(
            "SELECT r.id, r.site_id, r.report_type,
                    COALESCE(r.period, '') AS period,
                    COALESCE(r.file_path, '') AS file_path,
                    r.created_at,
                    COALESCE(r.generated_at, r.created_at) AS generated_at,
                    COALESCE(r.report_id, r.id) AS report_id,
                    COALESCE(r.status, '') AS status,
                    COALESCE(s.name, '') AS site_name
             FROM $table_reports r
             LEFT JOIN $table_sites s ON r.site_id = s.id
             ORDER BY COALESCE(r.generated_at, r.created_at) DESC LIMIT 20",
            ARRAY_A
        );

        wp_send_json_success($recent_reports ?: []);
    }
    
    public function ajax_get_sites_list() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        global $wpdb;
        $table_sites = $wpdb->prefix . 'rphub_sites';
        
        // Get sites from database
        $sites = $wpdb->get_results("
            SELECT id, name, url, plan, status, health_score, 
                   wp_version, php_version, updates_available, 
                   last_check, last_success 
            FROM $table_sites 
            ORDER BY name ASC
        ", ARRAY_A);
        
        // Format plan names for display
        if (!empty($sites)) {
            foreach ($sites as &$site) {
                switch ($site['plan']) {
                    case 'semilla':
                        $site['plan_label'] = 'Semilla';
                        break;
                    case 'raiz':
                        $site['plan_label'] = 'Raiz';
                        break;
                    case 'ecosistema':
                        $site['plan_label'] = 'Ecosistema';
                        break;
                    default:
                        $site['plan_label'] = ucfirst($site['plan']);
                }
            }
        }
        
        wp_send_json_success($sites);
    }
    
    public function ajax_test_whm_connection() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        if (!class_exists('RPHUB_WHM_Integration')) {
            wp_send_json_error('WHM Integration no esta disponible');
        }
        
        $whm_integration = new RPHUB_WHM_Integration();
        
        if (!$whm_integration->is_configured()) {
            wp_send_json_error('WHM no esta configurado correctamente. Verifica la configuracion.');
        }
        
        // Delegate to the class's AJAX handler (multi-server aware)
        $server_id = sanitize_key($_POST['server_id'] ?? '') ?: null;
        $test_results = $whm_integration->test_connection($server_id);
        
        // Normalize single/multi results
        if (isset($test_results['success'])) {
            wp_send_json_success([
                'servers' => [$test_results],
                'token_status' => $whm_integration->persistent_tokens_enabled() ? 'persistente' : 'temporal'
            ]);
        } else {
            wp_send_json_success([
                'servers' => array_values($test_results),
                'token_status' => $whm_integration->persistent_tokens_enabled() ? 'persistente' : 'temporal'
            ]);
        }
    }
    
    public function ajax_get_notifications_count() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'rphub_notifications';
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE read_status = 0");
        
        wp_send_json_success(['count' => $count]);
    }
    
    public function ajax_get_site_status() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $site_id = intval($_POST['site_id'] ?? 0);
        
        if (!$site_id) {
            wp_send_json_error('ID de sitio invalido');
        }
        
        $site = RPHUB_Database::get_site($site_id);
        $status = $site ? $site->status : 'unknown';
        
        wp_send_json_success(['status' => $status]);
    }
    
    // Removed: ajax_bulk_action() and process_bulk_action() - handled by ReplantaHub_Ajax_Handlers

    public function ajax_get_site_analytics() {
        check_ajax_referer('rphub_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        $site_id = intval($_POST['site_id'] ?? 0);
        if (!$site_id) {
            wp_send_json_error('ID de sitio invalido');
        }

        $analytics = $GLOBALS['rphub_analytics'] ?? null;
        if (!$analytics) {
            wp_send_json_error('Modulo de analitica no disponible');
        }

        // Trigger on-demand sync if data is stale (older than 12h)
        if (method_exists($analytics, 'sync_site_on_demand')) {
            $analytics->sync_site_on_demand($site_id);
        }

        $data = $analytics->get_site_analytics($site_id);
        wp_send_json_success($data);
    }

    public function ajax_update_site_plan() {
        check_ajax_referer('rphub_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $domain = sanitize_text_field($_POST['domain'] ?? '');
        $plan = sanitize_text_field($_POST['plan'] ?? '');
        
        if (!$domain || !in_array($plan, ['semilla', 'raiz', 'ecosistema'])) {
            wp_send_json_error('Invalid parameters');
        }
        
        $sites = get_option('rphub_managed_sites', []);
        if (isset($sites[$domain])) {
            $sites[$domain]['plan'] = $plan;
            $sites[$domain]['updated_at'] = current_time('mysql');
            update_option('rphub_managed_sites', $sites);

            // Push plan change to Care immediately (best-effort, non-blocking)
            $care_url   = $sites[$domain]['care_url']   ?? '';
            $care_token = $sites[$domain]['care_token'] ?? '';
            if ( $care_url && $care_token ) {
                wp_remote_post(
                    trailingslashit( $care_url ) . 'wp-json/replanta/v1/config',
                    [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $care_token,
                            'Content-Type'  => 'application/json',
                        ],
                        'body'    => wp_json_encode( [ 'plan' => $plan ] ),
                        'timeout' => 10,
                        'blocking' => false,
                    ]
                );
            }

            wp_send_json_success('Plan updated successfully');
        } else {
            wp_send_json_error('Site not found');
        }
    }
    
    public function ajax_whm_run_diagnostics() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        if (!class_exists('RPHUB_WHM_Integration')) {
            wp_send_json_error('WHM Integration no esta disponible');
        }
        
        $whm_integration = new RPHUB_WHM_Integration();
        
        if (!$whm_integration->is_configured()) {
            wp_send_json_error('WHM no esta configurado correctamente');
        }
        
        $diagnostics = $whm_integration->run_diagnostics();
        
        if (!is_array($diagnostics)) {
            wp_send_json_error('Error ejecutando diagnostico WHM');
        }
        
        wp_send_json_success($diagnostics);
    }
    
    public function ajax_run_diagnostics() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Cargar la clase solo cuando sea necesaria
        if (!class_exists('RPHUB_Diagnostics')) {
            require_once RPHUB_PLUGIN_DIR . 'inc/class-diagnostics.php';
        }
        
        if (!class_exists('RPHUB_Diagnostics')) {
            wp_send_json_error('Diagnostics class not available');
        }
        
        try {
            $diagnostics = new RPHUB_Diagnostics();
            $results = $diagnostics->run_full_diagnostics();
            wp_send_json_success($results);
        } catch (Exception $e) {
            wp_send_json_error('Error running diagnostics: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: trigger Care self-update on a single managed site.
     * Calls the site's REST /wp-json/replanta/v1/run with task=self_update.
     */
    public function ajax_update_care_on_site() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $site_url = esc_url_raw($_POST['site_url'] ?? '');
        if (!$site_url) {
            wp_send_json_error('site_url requerido');
        }

        // Look up site token from DB table (primary), fall back to legacy managed_sites option
        global $wpdb;
        $token    = '';
        $care_url = '';
        $table_sites = $wpdb->prefix . 'rphub_sites';
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT care_url, care_token FROM {$table_sites} WHERE url = %s LIMIT 1",
            rtrim( $site_url, '/' )
        ) );
        if ( $row && !empty( $row->care_token ) ) {
            $token    = $row->care_token;
            $care_url = $row->care_url ?: $site_url;
        } else {
            // Legacy fallback: rphub_managed_sites option
            $managed = get_option( 'rphub_managed_sites', [] );
            foreach ( $managed as $s ) {
                if ( rtrim( $s['url'] ?? '', '/' ) === rtrim( $site_url, '/' ) ) {
                    $token    = $s['token'] ?? '';
                    $care_url = $site_url;
                    break;
                }
            }
        }
        if ( !$token ) {
            wp_send_json_error( 'Token no encontrado para ' . $site_url );
        }

        if (!class_exists('RPHUB_API_Client')) {
            require_once RPHUB_PLUGIN_DIR . 'inc/class-api-client.php';
        }
        $client = new RPHUB_API_Client();
        $response = $client->execute_task($site_url, $token, 'self_update');

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }
        wp_send_json_success($response);
    }

    /**
     * AJAX: return enriched site data for the cards view.
     */
    public function ajax_get_sites_cards() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
            return;
        }

        try {
            wp_send_json_success($this->get_sites_cards_data());
        } catch (\Throwable $e) {
            error_log('RPHUB ajax_get_sites_cards error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            wp_send_json_error('Error al cargar sitios: ' . $e->getMessage());
        }
    }

    /**
     * REST endpoint: GET /wp-json/replanta-hub/v1/sites-cards
     * Allows bypassing admin-ajax.php for CF-WAF-blocked environments.
     */
    public function rest_get_sites_cards(\WP_REST_Request $request) {
        if (!current_user_can('manage_options')) {
            return new \WP_Error('forbidden', 'Insufficient permissions', ['status' => 403]);
        }
        try {
            return rest_ensure_response($this->get_sites_cards_data());
        } catch (\Throwable $e) {
            error_log('RPHUB rest_get_sites_cards error: ' . $e->getMessage());
            return new \WP_Error('server_error', $e->getMessage(), ['status' => 500]);
        }
    }

    private function get_sites_cards_data(): array {
        global $wpdb;
        $table_sites = $wpdb->prefix . 'rphub_sites';

        $sites = $wpdb->get_results(
            "SELECT id, name, url, plan, status, health_score,
                    updates_available, security_issues, last_check,
                    client_name
             FROM $table_sites
             WHERE status != 'deleted'
             ORDER BY COALESCE(NULLIF(client_name,''), name) ASC, name ASC",
            ARRAY_A
        );

        if (empty($sites)) {
            return ['sites' => [], 'clients' => []];
        }

        $site_ids = array_map('intval', array_column($sites, 'id'));

        // Batch load ALL site meta in one query (avoids NÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â15 individual queries)
        $ids_sql   = implode(',', $site_ids);
        $meta_rows = $wpdb->get_results(
            "SELECT site_id, meta_key, meta_value FROM {$wpdb->prefix}rphub_site_meta WHERE site_id IN ($ids_sql)",
            ARRAY_A
        ) ?: [];
        $meta = [];
        foreach ($meta_rows as $row) {
            $meta[(int)$row['site_id']][$row['meta_key']] = maybe_unserialize($row['meta_value']);
        }

        // Batch load DR data (3 queries for all sites instead of 3ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂN)
        $dr_batch = $this->get_dr_data_batch($sites);

        $clients = [];
        foreach ($sites as &$site) {
            $id = (int) $site['id'];
            $sm = $meta[$id] ?? [];
            $dr = $dr_batch[$id] ?? [];

            $site['care_version']          = $sm['care_version']          ?? '-';
            $site['wp_version']            = $sm['wp_version']            ?? '-';
            $site['php_version']           = $sm['php_version']           ?? '-';
            $site['pending_updates_count'] = (int)($sm['pending_updates_count'] ?? $site['updates_available']);
            $site['last_backup']           = $sm['last_backup']           ?? null;
            $site['cf_score']              = (int)($sm['cf_score']        ?? 0);
            $site['seo_score']             = (int)($sm['seo_score']       ?? 0);
            $site['perf_score']            = (int)($sm['perf_score']      ?? 0);
            $site['sa_global_score']       = (int)($sm['sa_global_score'] ?? 0);
            $site['sa_critical_issues']    = (int)($sm['sa_critical_issues'] ?? 0);
            $site['sa_warning_issues']     = (int)($sm['sa_warning_issues']  ?? 0);
            $site['sa_last_audit']         = $sm['sa_last_audit'] ?? null;
            $site['ssl_type']              = $sm['ssl_type'] ?? '';

            $site['php_version_whm']      = $dr['php_version_whm']      ?? '';
            $site['whm_server']           = $dr['whm_server']           ?? '';
            $site['whm_status']           = $dr['whm_status']           ?? '';
            $site['co2_evaded']           = $dr['co2_evaded']           ?? '';
            $site['trees_planted']        = $dr['trees_planted']        ?? '';
            $site['cf_zone_id']           = $dr['cf_zone_id']           ?? '';
            $site['cf_zone_status']       = $dr['cf_zone_status']       ?? '';
            $site['cf_plan_name']         = $dr['cf_plan_name']         ?? '';
            $site['cf_onboarding_state']  = $dr['cf_onboarding_state']  ?? '';
            $site['dr_enriched_at']       = $dr['dr_enriched_at']       ?? '';

            $client = $site['client_name'] ?: 'Sin cliente';
            if (!in_array($client, $clients)) {
                $clients[] = $client;
            }
        }
        unset($site);

        sort($clients);
        return ['sites' => $sites, 'clients' => $clients];
    }

    /**
     * Batch-load DR data for all sites in 3 queries (dr, cf_zones, cf_onboarding).
     */
    private function get_dr_data_batch(array $sites): array {
        global $wpdb;

        $data = array_fill_keys(array_map('intval', array_column($sites, 'id')), []);

        // Map site_id ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ bare domain
        $id_to_domain = [];
        foreach ($sites as $s) {
            $host = parse_url($s['url'], PHP_URL_HOST) ?: $s['url'];
            $id_to_domain[(int)$s['id']] = strtolower(ltrim($host, 'www.'));
        }
        $domains = array_unique(array_values($id_to_domain));
        if (empty($domains)) return $data;

        // Build safe IN clause
        $esc    = array_map('esc_sql', $domains);
        $in_sql = "'" . implode("','", $esc) . "'";

        // 1. dominios_reseller table
        $dr_table = $wpdb->prefix . 'dominios_reseller';
        if ($wpdb->get_var("SHOW TABLES LIKE '$dr_table'")) {
            $rows = $wpdb->get_results(
                "SELECT domain, primary_domain, server, status, co2_evaded, trees_planted, php_info
                 FROM $dr_table WHERE domain IN ($in_sql) OR primary_domain IN ($in_sql)",
                ARRAY_A
            ) ?: [];
            $by_domain = [];
            foreach ($rows as $r) {
                $key = $r['domain'] ?: $r['primary_domain'];
                $by_domain[$key] = $r;
            }
            foreach ($id_to_domain as $id => $bare) {
                if (isset($by_domain[$bare])) {
                    $r = $by_domain[$bare];
                    $php = json_decode($r['php_info'] ?? '', true) ?: [];
                    $data[$id]['whm_server']      = $r['server']       ?? '';
                    $data[$id]['whm_status']      = $r['status']       ?? '';
                    $data[$id]['co2_evaded']      = $r['co2_evaded']   ?? '';
                    $data[$id]['trees_planted']   = $r['trees_planted'] ?? '';
                    $data[$id]['php_version_whm'] = $php['php_version'] ?? $php['version'] ?? '';
                }
            }
        }

        // 2. CF zones table
        $zone_table = $wpdb->prefix . 'dominios_reseller_cf_zones';
        if ($wpdb->get_var("SHOW TABLES LIKE '$zone_table'")) {
            $rows = $wpdb->get_results(
                "SELECT name, zone_id, status, plan_name FROM $zone_table WHERE name IN ($in_sql) AND deleted_at IS NULL",
                ARRAY_A
            ) ?: [];
            $by_domain = array_column($rows, null, 'name');
            foreach ($id_to_domain as $id => $bare) {
                if (isset($by_domain[$bare])) {
                    $r = $by_domain[$bare];
                    $data[$id]['cf_zone_id']     = $r['zone_id']   ?? '';
                    $data[$id]['cf_zone_status'] = $r['status']    ?? '';
                    $data[$id]['cf_plan_name']   = $r['plan_name'] ?? '';
                }
            }
        }

        // 3. CF onboarding table
        $ob_table = $wpdb->prefix . 'dominios_reseller_cf_onboarding';
        if ($wpdb->get_var("SHOW TABLES LIKE '$ob_table'")) {
            $rows = $wpdb->get_results(
                "SELECT primary_domain, state FROM $ob_table WHERE primary_domain IN ($in_sql)",
                ARRAY_A
            ) ?: [];
            $by_domain = array_column($rows, 'state', 'primary_domain');
            foreach ($id_to_domain as $id => $bare) {
                if (isset($by_domain[$bare])) {
                    $data[$id]['cf_onboarding_state'] = $by_domain[$bare];
                }
            }
        }

        return $data;
    }

    /**
     * AJAX: trigger Care self-upgrade on a specific managed site via Care REST API.
     */
    public function ajax_trigger_care_upgrade() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
            return;
        }

        $site_id = intval($_POST['site_id'] ?? 0);
        if (!$site_id) {
            wp_send_json_error('site_id requerido');
            return;
        }

        $site = RPHUB_Database::get_site($site_id);
        if (!$site) {
            wp_send_json_error('Sitio no encontrado');
            return;
        }

        if (empty($site->token)) {
            wp_send_json_error('Este sitio no tiene token configurado');
            return;
        }

        $response = wp_remote_post(
            rtrim($site->url, '/') . '/wp-json/replanta/v1/upgrade',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $site->token,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => '{}',
                'timeout' => 60,
            ]
        );

        if (is_wp_error($response)) {
            wp_send_json_error('Error de conexion: ' . $response->get_error_message());
            return;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            wp_send_json_error('Care respondio con HTTP ' . $code . ': ' . ($body['message'] ?? ''));
            return;
        }

        wp_send_json_success($body);
    }

    /**
     * AJAX: run all audit modules on a single site.
     */
    public function ajax_run_site_audit() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
            return;
        }

        $site_id = intval($_POST['site_id'] ?? 0);
        $force   = !empty($_POST['force']);

        if (!$site_id) {
            wp_send_json_error('site_id requerido');
            return;
        }

        set_time_limit(180);
        $result = RPHUB_Site_Auditor::run_audit($site_id, $force);
        wp_send_json_success($result);
    }

    /**
     * AJAX: apply a single CF fix to a site's zone.
     */
    public function ajax_apply_cf_fix() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
            return;
        }

        $site_id = intval($_POST['site_id'] ?? 0);
        $fix_id  = sanitize_key($_POST['fix_id'] ?? '');

        if (!$site_id || !$fix_id) {
            wp_send_json_error('site_id y fix_id requeridos');
            return;
        }

        $zone_id = RPHUB_Database::get_site_meta($site_id, 'cf_zone_id');
        if (!$zone_id) {
            wp_send_json_error('Este sitio no tiene zona CF asociada');
            return;
        }

        global $wpdb;
        $site = $wpdb->get_row($wpdb->prepare("SELECT plan FROM {$wpdb->prefix}rphub_sites WHERE id = %d", $site_id));
        $plan = $site->plan ?? 'semilla';

        if (!in_array($fix_id, RPHUB_CF_Fixer::get_allowed_fixes($plan), true)) {
            wp_send_json_error("Fix '{$fix_id}' no disponible en el plan {$plan}");
            return;
        }

        $result = RPHUB_CF_Fixer::execute($zone_id, $fix_id);
        $result['success'] ? wp_send_json_success($result) : wp_send_json_error($result['error']);
    }

    /**
     * AJAX: apply all CF plan defaults to a site's zone.
     */
    public function ajax_apply_plan_cf_fixes() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
            return;
        }

        $site_id = intval($_POST['site_id'] ?? 0);
        if (!$site_id) {
            wp_send_json_error('site_id requerido');
            return;
        }

        $zone_id = RPHUB_Database::get_site_meta($site_id, 'cf_zone_id');
        if (!$zone_id) {
            wp_send_json_error('Este sitio no tiene zona CF asociada');
            return;
        }

        global $wpdb;
        $site = $wpdb->get_row($wpdb->prepare("SELECT plan FROM {$wpdb->prefix}rphub_sites WHERE id = %d", $site_id));
        $plan = $site->plan ?? 'semilla';

        $results = RPHUB_CF_Fixer::execute_plan_defaults($zone_id, $plan);
        wp_send_json_success(['plan' => $plan, 'results' => $results]);
    }

    /**
     * AJAX: send a single WP fix to Care on a site.
     */
    public function ajax_send_wp_fix() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
            return;
        }

        $site_id = intval($_POST['site_id'] ?? 0);
        $fix_id  = sanitize_key($_POST['fix_id'] ?? '');

        if (!$site_id || !$fix_id) {
            wp_send_json_error('site_id y fix_id requeridos');
            return;
        }

        global $wpdb;
        $site = $wpdb->get_row($wpdb->prepare("SELECT plan FROM {$wpdb->prefix}rphub_sites WHERE id = %d", $site_id));
        $plan = $site->plan ?? 'semilla';

        if (!in_array($fix_id, RPHUB_WP_Fixer::get_allowed_fixes($plan), true)) {
            wp_send_json_error("Fix '{$fix_id}' no disponible en el plan {$plan}");
            return;
        }

        $result = RPHUB_WP_Fixer::send_fix($site_id, $fix_id);
        $result['success'] ? wp_send_json_success($result) : wp_send_json_error($result['error'] ?? 'Error desconocido');
    }

    /**
     * AJAX: send all WP plan fixes to Care on a site.
     */
    public function ajax_send_plan_wp_fixes() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
            return;
        }

        $site_id = intval($_POST['site_id'] ?? 0);
        if (!$site_id) {
            wp_send_json_error('site_id requerido');
            return;
        }

        global $wpdb;
        $site = $wpdb->get_row($wpdb->prepare("SELECT plan FROM {$wpdb->prefix}rphub_sites WHERE id = %d", $site_id));
        $plan = $site->plan ?? 'semilla';

        $results = RPHUB_WP_Fixer::send_plan_fixes($site_id, $plan);
        wp_send_json_success(['plan' => $plan, 'results' => $results]);
    }

    /**
     * AJAX: enrich a single site from DR data.
     */
    public function ajax_enrich_site_from_dr(): void {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
            return;
        }

        $site_id = intval($_POST['site_id'] ?? 0);
        if (!$site_id) {
            wp_send_json_error('site_id requerido');
            return;
        }

        global $wpdb;
        $site = $wpdb->get_row($wpdb->prepare(
            "SELECT url FROM {$wpdb->prefix}rphub_sites WHERE id = %d", $site_id
        ));
        if (!$site) {
            wp_send_json_error('Sitio no encontrado');
            return;
        }

        if (!class_exists('RPHUB_DR_Bridge') || !RPHUB_DR_Bridge::is_available()) {
            wp_send_json_error('Plugin Dominios Reseller no disponible');
            return;
        }

        $result = RPHUB_DR_Bridge::enrich_site($site_id, $site->url);
        wp_send_json_success($result);
    }

    /**
     * AJAX: bulk-enrich all active sites from DR data.
     */
    public function ajax_enrich_all_from_dr() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
            return;
        }

        if (!RPHUB_DR_Bridge::is_available()) {
            wp_send_json_error('Plugin Dominios Reseller no disponible');
            return;
        }

        global $wpdb;
        $sites = $wpdb->get_results(
            "SELECT id, url FROM {$wpdb->prefix}rphub_sites WHERE status = 'active'",
            ARRAY_A
        );

        $count = 0;
        foreach ($sites as $site) {
            RPHUB_DR_Bridge::enrich_site((int) $site['id'], $site['url']);
            $count++;
        }

        wp_send_json_success(['enriched' => $count]);
    }

    /**
     * AJAX: re-trigger /deploy/care on this Hub so it pulls the latest
     * GitHub release into the served zip (useful if the workflow webhook failed).
     */
    public function ajax_refresh_care_cache() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $version = sanitize_text_field($_POST['version'] ?? '');
        if (!$version) {
            // Read latest tag from GitHub
            $r = wp_remote_get('https://api.github.com/repos/replantadev/care/releases/latest', ['timeout' => 10]);
            if (is_wp_error($r)) wp_send_json_error($r->get_error_message());
            $data = json_decode(wp_remote_retrieve_body($r), true);
            $version = ltrim($data['tag_name'] ?? '', 'v');
        }
        if (!$version) {
            wp_send_json_error('No se pudo determinar la version');
        }

        if (!class_exists('ReplantaHub_Deploy')) {
            require_once RPHUB_PLUGIN_DIR . 'inc/class-deploy.php';
        }
        $deploy = new ReplantaHub_Deploy();
        $req = new WP_REST_Request('POST', '/replanta-hub/v1/deploy/care');
        $req->set_param('version', $version);
        $result = $deploy->deploy_care($req);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        wp_send_json_success($result->get_data());
    }
    
    // Removed: add_demo_data() - Phase 3 cleanup, no more demo site insertion on activation
    
    public function ajax_get_dashboard_stats() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        global $wpdb;
        $table_sites = $wpdb->prefix . 'rphub_sites';
        $table_notifications = $wpdb->prefix . 'rphub_notifications';
        $table_tasks = $wpdb->prefix . 'rphub_tasks';
        
        // Get stats from database
        $total_sites = $wpdb->get_var("SELECT COUNT(*) FROM $table_sites");
        $active_sites = $wpdb->get_var("SELECT COUNT(*) FROM $table_sites WHERE status = 'active'");
        $pending_tasks = $wpdb->get_var("SELECT COUNT(*) FROM $table_tasks WHERE status = 'pending'");
        $unread_notifications = $wpdb->get_var("SELECT COUNT(*) FROM $table_notifications WHERE read_status = 0");
        
        // Calculate average health score
        $avg_health = $wpdb->get_var("SELECT AVG(health_score) FROM $table_sites WHERE health_score > 0");
        
        $stats = [
            'total_sites' => (int) $total_sites,
            'active_sites' => (int) $active_sites,
            'pending_tasks' => (int) $pending_tasks,
            'unread_notifications' => (int) $unread_notifications,
            'avg_health_score' => $avg_health ? round($avg_health) : 0
        ];
        
        wp_send_json_success($stats);
    }
    
    // Removed: ajax_get_notifications() - handled by RPHUB_Notifications class
    
    public function ajax_get_tasks() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        global $wpdb;
        $table_tasks = $wpdb->prefix . 'rphub_tasks';
        
        $tasks = $wpdb->get_results("
            SELECT * FROM $table_tasks 
            ORDER BY created_at DESC 
            LIMIT 10
        ", ARRAY_A);
        
        wp_send_json_success($tasks ?: []);
    }
    
    public function ajax_test_site_connection() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $site_id = intval($_POST['site_id'] ?? 0);
        
        if (!$site_id) {
            wp_send_json_error('ID de sitio requerido');
            return;
        }
        
        global $wpdb;
        $table_sites = $wpdb->prefix . 'rphub_sites';
        
        $site = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_sites WHERE id = %d", 
            $site_id
        ), ARRAY_A);
        
        if (!$site) {
            wp_send_json_error('Sitio no encontrado');
            return;
        }
        
        // Test connection (simplified for demo)
        $url = $site['url'];
        $response = wp_remote_get($url, ['timeout' => 10]);
        
        if (is_wp_error($response)) {
            wp_send_json_error('Error de conexion: ' . $response->get_error_message());
            return;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code === 200) {
            // Update last_success
            $wpdb->update(
                $table_sites,
                ['last_success' => current_time('mysql')],
                ['id' => $site_id]
            );
            
            wp_send_json_success([
                'message' => 'Conexion exitosa',
                'status_code' => $status_code,
                'response_time' => '250ms'
            ]);
        } else {
            wp_send_json_error("Error HTTP: $status_code");
        }
    }
    
    // Removed: ajax_test_care_connection() - handled by RPHUB_Site_Manager class
    
    /**
     * Cron job handlers
     */
    public function run_daily_checks() {
        if ($this->whm_integration && method_exists($this->whm_integration, 'is_configured') && is_callable([$this->whm_integration, 'is_configured']) && $this->whm_integration->is_configured()) {
            if (method_exists($this->whm_integration, 'run_daily_checks')) {
                $this->whm_integration->run_daily_checks();
            }
        }
        
        if ($this->wptoolkit_integration && method_exists($this->wptoolkit_integration, 'is_configured') && is_callable([$this->wptoolkit_integration, 'is_configured']) && $this->wptoolkit_integration->is_configured()) {
            if (method_exists($this->wptoolkit_integration, 'run_daily_checks')) {
                $this->wptoolkit_integration->run_daily_checks();
            }
        }
    }
    
    public function run_hourly_monitoring() {
        if ($this->backuply_integration && method_exists($this->backuply_integration, 'is_configured') && is_callable([$this->backuply_integration, 'is_configured']) && $this->backuply_integration->is_configured()) {
            if (method_exists($this->backuply_integration, 'run_hourly_checks')) {
                $this->backuply_integration->run_hourly_checks();
            }
        }
        
        if ($this->cloudflare_integration && method_exists($this->cloudflare_integration, 'is_configured') && is_callable([$this->cloudflare_integration, 'is_configured']) && $this->cloudflare_integration->is_configured()) {
            if (method_exists($this->cloudflare_integration, 'run_hourly_checks')) {
                $this->cloudflare_integration->run_hourly_checks();
            }
        }
    }
    
    public function run_litespeed_optimization() {
        if ($this->litespeed_integration && method_exists($this->litespeed_integration, 'is_configured') && is_callable([$this->litespeed_integration, 'is_configured']) && $this->litespeed_integration->is_configured()) {
            if (method_exists($this->litespeed_integration, 'run_daily_optimization')) {
                $this->litespeed_integration->run_daily_optimization();
            }
        }
    }
    
    public function run_vulnerability_scan() {
        if ($this->wptoolkit_integration && method_exists($this->wptoolkit_integration, 'is_configured') && is_callable([$this->wptoolkit_integration, 'is_configured']) && $this->wptoolkit_integration->is_configured()) {
            if (method_exists($this->wptoolkit_integration, 'run_vulnerability_scan')) {
                $this->wptoolkit_integration->run_vulnerability_scan();
            }
        }
    }
    
    public function run_pagespeed_analysis() {
        if ($this->pagespeed_integration && method_exists($this->pagespeed_integration, 'is_configured') && is_callable([$this->pagespeed_integration, 'is_configured']) && $this->pagespeed_integration->is_configured()) {
            if (method_exists($this->pagespeed_integration, 'run_analysis')) {
                $this->pagespeed_integration->run_analysis();
            }
        }
    }
    
    public function run_backuply_check() {
        if ($this->backuply_integration && method_exists($this->backuply_integration, 'is_configured') && is_callable([$this->backuply_integration, 'is_configured']) && $this->backuply_integration->is_configured()) {
            if (method_exists($this->backuply_integration, 'check_backups')) {
                $this->backuply_integration->check_backups();
            }
        }
    }
    
    public function run_cloudflare_sync() {
        if ($this->cloudflare_integration && method_exists($this->cloudflare_integration, 'is_configured') && is_callable([$this->cloudflare_integration, 'is_configured']) && $this->cloudflare_integration->is_configured()) {
            if (method_exists($this->cloudflare_integration, 'sync_analytics')) {
                $this->cloudflare_integration->sync_analytics();
            }
        }
    }

    /**
     * AJAX: get SA issues list for a site (via Care proxy /sa/issues).
     * Nonce: rphub_sync_site (reused since it's a read-only fetch)
     */
    public function ajax_get_sa_issues() {
        if (
            !check_ajax_referer('rphub_ajax', 'nonce', false)
            && !check_ajax_referer('rphub_sync_site', 'nonce', false)
        ) {
            wp_send_json_error('Nonce invalido o caducado. Recarga Operaciones e intentalo de nuevo.', 403);
            return;
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes', 403);
            return;
        }
        $site_id = intval($_POST['site_id'] ?? 0);
        if (!$site_id) {
            wp_send_json_error('site_id requerido');
            return;
        }
        global $wpdb;
        $site = $wpdb->get_row($wpdb->prepare(
            "SELECT url, token FROM {$wpdb->prefix}rphub_sites WHERE id = %d", $site_id
        ));
        if (!$site) {
            wp_send_json_error('Sitio no encontrado');
            return;
        }
        $api    = new RPHUB_API_Client();
        $result = $api->get_sa_issues($site->url, $site->token);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }
        wp_send_json_success($result);
    }

    /**
     * AJAX: execute a specific SA fix on a site (via Care proxy /sa/fix).
     * Nonce: rphub_execute_task (shared with other task triggers)
     */
    public function ajax_run_sa_fix() {
        if (
            !check_ajax_referer('rphub_ajax', 'nonce', false)
            && !check_ajax_referer('rphub_execute_task', 'nonce', false)
        ) {
            wp_send_json_error('Nonce invalido o caducado. Recarga Operaciones e intentalo de nuevo.', 403);
            return;
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes', 403);
            return;
        }
        $site_id = intval($_POST['site_id'] ?? 0);
        $fix_id  = sanitize_key($_POST['fix_id']  ?? '');
        if (!$site_id || !$fix_id) {
            wp_send_json_error('site_id y fix_id son requeridos');
            return;
        }
        global $wpdb;
        $site = $wpdb->get_row($wpdb->prepare(
            "SELECT url, token FROM {$wpdb->prefix}rphub_sites WHERE id = %d", $site_id
        ));
        if (!$site) {
            wp_send_json_error('Sitio no encontrado');
            return;
        }
        $api    = new RPHUB_API_Client();
        $result = $api->run_sa_fix($site->url, $site->token, $fix_id);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }
        wp_send_json_success($result);
    }
}

// Initialize the plugin
function rphub_init() {
    return ReplantaHub::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'rphub_init');

// Cron hooks
add_action('rphub_hourly_tasks', ['RPHUB_Task_Orchestrator', 'run_hourly_tasks']);
add_action('rphub_daily_tasks', ['RPHUB_Task_Orchestrator', 'run_daily_tasks']);
