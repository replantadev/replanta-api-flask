<?php
/**
 * Plugin Name: Replanta Affiliates
 * Plugin URI:  https://replanta.net
 * Description: Programa de afiliados propio de Replanta. Registro, tracking por cookie, atribución de ventas, dashboard de afiliado, comisiones y pagos.
 * Version:     1.0.0
 * Author:      Replanta
 * Author URI:  https://replanta.net
 * License:     GPL-2.0-or-later
 * Text Domain: replanta-affiliates
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ── Constants ─────────────────────────────────────────── */
define( 'RAFF_VERSION',    '1.0.0' );
define( 'RAFF_FILE',       __FILE__ );
define( 'RAFF_DIR',        plugin_dir_path( __FILE__ ) );
define( 'RAFF_URL',        plugin_dir_url( __FILE__ ) );
define( 'RAFF_BASENAME',   plugin_basename( __FILE__ ) );

/* ── Autoload includes ─────────────────────────────────── */
require_once RAFF_DIR . 'includes/class-db.php';
require_once RAFF_DIR . 'includes/class-settings.php';
require_once RAFF_DIR . 'includes/class-email.php';
require_once RAFF_DIR . 'includes/class-registration.php';
require_once RAFF_DIR . 'includes/class-tracker.php';
require_once RAFF_DIR . 'includes/class-dashboard.php';
require_once RAFF_DIR . 'includes/class-invoice.php';
require_once RAFF_DIR . 'includes/class-landing.php';
require_once RAFF_DIR . 'includes/class-admin-affiliates.php';
require_once RAFF_DIR . 'includes/class-admin-sales.php';
require_once RAFF_DIR . 'includes/class-admin-payouts.php';

/* ── Activation / Deactivation ─────────────────────────── */
register_activation_hook( __FILE__, array( 'Raff_DB', 'activate' ) );
register_deactivation_hook( __FILE__, 'raff_deactivate' );

function raff_deactivate() {
    wp_clear_scheduled_hook( 'raff_daily_confirm_sales' );
    wp_clear_scheduled_hook( 'raff_cleanup_expired_tokens' );
}

/* ── Init ──────────────────────────────────────────────── */
add_action( 'plugins_loaded', 'raff_bootstrap' );

function raff_bootstrap() {
    /* Check DB version and upgrade if needed */
    Raff_DB::maybe_upgrade();

    /* Admin */
    if ( is_admin() ) {
        Raff_Settings::init();
        Raff_Admin_Affiliates::init();
        Raff_Admin_Sales::init();
        Raff_Admin_Payouts::init();
    }

    /* Frontend */
    Raff_Registration::init();
    Raff_Landing::init();
    Raff_Tracker::init();
    Raff_Dashboard::init();
    Raff_Invoice::init();

    /* Cron schedules */
    if ( ! wp_next_scheduled( 'raff_daily_confirm_sales' ) ) {
        wp_schedule_event( time(), 'daily', 'raff_daily_confirm_sales' );
    }
    if ( ! wp_next_scheduled( 'raff_cleanup_expired_tokens' ) ) {
        wp_schedule_event( time(), 'daily', 'raff_cleanup_expired_tokens' );
    }
}

/* ── Block affiliate role from wp-admin ────────────────── */
add_action( 'admin_init', function () {
    if ( current_user_can( 'affiliate' ) && ! wp_doing_ajax() && ! defined( 'DOING_CRON' ) ) {
        wp_safe_redirect( home_url( Raff_DB::get_setting( 'dashboard_path', '/afiliados/dashboard/' ) ) );
        exit;
    }
} );
add_filter( 'show_admin_bar', function ( $show ) {
    if ( current_user_can( 'affiliate' ) ) {
        return false;
    }
    return $show;
} );

/* ── Plugin action links ──────────────────────────────── */
add_filter( 'plugin_action_links_' . RAFF_BASENAME, function ( $links ) {
    $url = admin_url( 'admin.php?page=replanta-affiliates' );
    array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . __( 'Settings', 'replanta-affiliates' ) . '</a>' );
    return $links;
} );
