<?php
/**
 * Awin Manager - Main controller for Awin tracking module.
 *
 * @package Replanta_Prices
 * @subpackage Awin
 */

defined( 'ABSPATH' ) || exit;

class Replanta_Awin_Manager {

    /** @var bool Initialized flag */
    private static $initialized = false;

    /**
     * Initialize the Awin tracking module.
     */
    public static function init() {
        if ( self::$initialized ) {
            return;
        }

        self::$initialized = true;

        // Load dependencies
        self::load_classes();

        // Initialize components
        Replanta_Awin_Cookie::init();
        Replanta_Awin_Webhook::init();
        Replanta_Awin_Admin::init();

        // Schedule cleanup cron
        add_action( 'init', array( __CLASS__, 'schedule_cleanup' ) );
        add_action( 'replanta_awin_cleanup', array( __CLASS__, 'run_cleanup' ) );

        // Schedule S2S processing cron (every 5 minutes)
        add_action( 'init', array( __CLASS__, 'schedule_s2s_processing' ) );
        add_action( 'replanta_awin_s2s_process', array( __CLASS__, 'run_s2s_processing' ) );

        // Enqueue frontend scripts if JS fallback enabled
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_frontend' ) );

        // Inject MasterTag if enabled
        add_action( 'wp_footer', array( __CLASS__, 'maybe_inject_mastertag' ), 99 );
    }

    /**
     * Load Awin classes.
     */
    private static function load_classes() {
        $dir = REPLANTA_PRICES_PATH . 'includes/awin/';

        require_once $dir . 'class-awin-cookie.php';
        require_once $dir . 'class-awin-url-helper.php';
        require_once $dir . 'class-awin-logger.php';
        require_once $dir . 'class-awin-webhook.php';
        require_once $dir . 'class-awin-s2s.php';
        require_once $dir . 'class-awin-logs-cleanup.php';
        require_once $dir . 'class-awin-admin.php';
    }

    /**
     * Schedule daily cleanup cron.
     */
    public static function schedule_cleanup() {
        if ( ! wp_next_scheduled( 'replanta_awin_cleanup' ) ) {
            wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'replanta_awin_cleanup' );
        }
    }

    /**
     * Run scheduled cleanup.
     */
    public static function run_cleanup() {
        $deleted = Replanta_Awin_Logger::cleanup_old_events();
        
        if ( WP_DEBUG && $deleted > 0 ) {
            error_log( sprintf( '[Replanta Awin] Cleanup: %d old events deleted', $deleted ) );
        }
    }

    /**
     * Schedule S2S processing cron.
     * Runs every 5 minutes to send pending conversions to Awin.
     */
    public static function schedule_s2s_processing() {
        // Register custom interval if not exists
        add_filter( 'cron_schedules', function( $schedules ) {
            if ( ! isset( $schedules['every_five_minutes'] ) ) {
                $schedules['every_five_minutes'] = array(
                    'interval' => 5 * MINUTE_IN_SECONDS,
                    'display'  => __( 'Every 5 Minutes', 'replanta-prices' ),
                );
            }
            return $schedules;
        } );

        if ( ! wp_next_scheduled( 'replanta_awin_s2s_process' ) ) {
            wp_schedule_event( time() + MINUTE_IN_SECONDS, 'every_five_minutes', 'replanta_awin_s2s_process' );
        }
    }

    /**
     * Run S2S processing to send pending conversions.
     */
    public static function run_s2s_processing() {
        $settings = Replanta_Awin_Cookie::get_settings();

        // Skip if not enabled or no advertiser ID
        if ( ! $settings['enabled'] || empty( $settings['advertiser_id'] ) ) {
            return;
        }

        $results = Replanta_Awin_S2S::process_pending_conversions( 10 );

        if ( WP_DEBUG && $results['processed'] > 0 ) {
            error_log( sprintf( 
                '[Replanta Awin] S2S Processing: %d processed, %d success, %d failed, %d skipped',
                $results['processed'],
                $results['success'],
                $results['failed'],
                $results['skipped']
            ) );
        }
    }

    /**
     * Maybe enqueue frontend scripts.
     */
    public static function maybe_enqueue_frontend() {
        $settings = Replanta_Awin_Cookie::get_settings();

        if ( ! $settings['enabled'] || ! $settings['js_fallback'] ) {
            return;
        }

        wp_enqueue_script(
            'replanta-awin-fallback',
            REPLANTA_PRICES_URL . 'assets/js/awin-fallback.js',
            array(),
            REPLANTA_PRICES_VERSION,
            true
        );

        wp_localize_script( 'replanta-awin-fallback', 'replantaAwin', 
            Replanta_Awin_URL_Helper::get_js_config() 
        );
    }

    /**
     * Inject Awin MasterTag in footer if enabled.
     */
    public static function maybe_inject_mastertag() {
        // Skip admin, ajax, cron
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
            return;
        }

        $settings = Replanta_Awin_Cookie::get_settings();

        // Must be enabled, have advertiser ID, and injection enabled
        if ( ! $settings['enabled'] || empty( $settings['advertiser_id'] ) || empty( $settings['inject_mastertag'] ) ) {
            return;
        }

        // Sanitize advertiser ID (must be numeric)
        $advertiser_id = preg_replace( '/[^0-9]/', '', $settings['advertiser_id'] );
        if ( empty( $advertiser_id ) || strlen( $advertiser_id ) < 3 || strlen( $advertiser_id ) > 10 ) {
            return;
        }

        // Output the MasterTag script
        printf(
            '<script src="https://www.dwin1.com/%s.js" type="text/javascript" defer="defer"></script>' . "\n",
            esc_attr( $advertiser_id )
        );
    }

    /**
     * Check if Awin tracking is enabled.
     *
     * @return bool
     */
    public static function is_enabled() {
        $settings = Replanta_Awin_Cookie::get_settings();
        return ! empty( $settings['enabled'] );
    }

    /**
     * Get current AWC if available.
     *
     * @return string|null
     */
    public static function get_awc() {
        return Replanta_Awin_Cookie::get_awc();
    }

    /**
     * Build order URL with AWC appended.
     *
     * @param string      $pid      Product ID.
     * @param string|null $currency Currency code.
     * @return string
     */
    public static function build_order_url( $pid, $currency = null ) {
        return Replanta_Awin_URL_Helper::build_order_url( $pid, $currency );
    }

    /**
     * Append AWC to any URL.
     *
     * @param string $url URL to modify.
     * @return string
     */
    public static function append_awc( $url ) {
        return Replanta_Awin_URL_Helper::append_awc( $url );
    }

    /**
     * Log an Awin event.
     *
     * @param string $type   Event type.
     * @param array  $data   Event data.
     * @param string $status Event status.
     * @return int|false
     */
    public static function log( $type, $data = array(), $status = 'success' ) {
        return Replanta_Awin_Logger::log_event( $type, $status, $data );
    }

    /**
     * Plugin activation hook for Awin module.
     */
    public static function activate() {
        // Create database table
        self::load_classes();
        Replanta_Awin_Logger::create_table();

        // Set default settings if not exist
        if ( ! get_option( 'replanta_awin_settings' ) ) {
            Replanta_Awin_Cookie::save_settings( array(
                'enabled'            => false,
                'cookie_name'        => 'replanta_awin_awc',
                'cookie_days'        => 90,
                'target_domain'      => 'clientes.replanta.net',
                'webhook_secret'     => Replanta_Awin_Webhook::generate_secret(),
                'js_fallback'        => true,
                'detailed_logs'      => false,
                'log_retention_days' => 90,
            ) );
        }
    }

    /**
     * Plugin deactivation hook for Awin module.
     */
    public static function deactivate() {
        // Clear scheduled cron
        wp_clear_scheduled_hook( 'replanta_awin_cleanup' );
    }
}
