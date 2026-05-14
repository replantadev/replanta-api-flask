<?php
/**
 * Plugin Name: Test Eco Website
 * Plugin URI:  https://replanta.dev/
 * Description: Genera un informe "Análisis de Sostenibilidad Web" combinando métricas de PageSpeed Insights, Website Carbon, Green Web Foundation y el Eco Snapshot Score propio.
 * Version:     0.2.0
 * Author:      Replanta Dev
 * Author URI:  https://replanta.dev/
 * Text Domain: test-eco-website
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    if ( 'cli' !== PHP_SAPI ) {
        exit;
    }

    if ( ! function_exists( 'add_action' ) ) {
        require_once __DIR__ . '/includes/compat/wp-stubs.php';
    }

    define( 'ABSPATH', __DIR__ . '/' );
} elseif ( ! function_exists( 'add_action' ) ) {
    require_once __DIR__ . '/includes/compat/wp-stubs.php';
}

define( 'TEW_VERSION', '0.2.0' );
define( 'TEW_PLUGIN_FILE', __FILE__ );
define( 'TEW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TEW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once TEW_PLUGIN_DIR . 'includes/class-tew-autoloader.php';

TEW\Autoloader::init();

// Debug tool (solo para admins)
if ( defined( 'WP_DEBUG' ) && WP_DEBUG && file_exists( TEW_PLUGIN_DIR . 'debug-report.php' ) ) {
    require_once TEW_PLUGIN_DIR . 'debug-report.php';
}

// Repair tool (solo para admins)
if ( file_exists( TEW_PLUGIN_DIR . 'repair-old-reports.php' ) ) {
    require_once TEW_PLUGIN_DIR . 'repair-old-reports.php';
}

register_activation_hook( TEW_PLUGIN_FILE, function () {
    $storage = new TEW\Reporting\Report_Storage();
    $storage->register();

    $custom_table = new TEW\Reporting\Custom_Report_Table();
    $custom_table->maybe_upgrade();
    
    // Registrar rewrite rule para /r/dominio.com
    add_rewrite_rule(
        '^r/([a-zA-Z0-9][a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/?$',
        'index.php?tew_domain_redirect=$matches[1]',
        'top'
    );
    
    flush_rewrite_rules();
} );

register_deactivation_hook( TEW_PLUGIN_FILE, function () {
    flush_rewrite_rules();
} );

// =============================================
// REDIRECT ENDPOINT: /r/dominio.com
// =============================================
add_action('init', function() {
    // Registrar rewrite rule
    add_rewrite_rule(
        '^r/([a-zA-Z0-9][a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/?$',
        'index.php?tew_domain_redirect=$matches[1]',
        'top'
    );
});

add_filter('query_vars', function($vars) {
    $vars[] = 'tew_domain_redirect';
    return $vars;
});

add_action('template_redirect', function() {
    $domain = get_query_var('tew_domain_redirect');
    
    if (empty($domain)) {
        return;
    }
    
    // Limpiar dominio
    $domain = sanitize_text_field($domain);
    $domain = strtolower(trim($domain));
    $domain = preg_replace('#^https?://#i', '', $domain);
    $domain = preg_replace('#^www\.#i', '', $domain);
    $domain = preg_replace('#/.*$#', '', $domain);
    
    // Validar formato
    if (!preg_match('/^[a-z0-9][a-z0-9.-]+\.[a-z]{2,}$/i', $domain)) {
        wp_redirect(home_url('/'), 302);
        exit;
    }
    
    // Log de visita (opcional) - sanitizar datos
    $logs = get_option('tew_redirect_logs', []);
    $client_ip = '';
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $client_ip = sanitize_text_field($_SERVER['HTTP_CF_CONNECTING_IP']);
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $client_ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);
    }
    $referer = !empty($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '';
    
    array_unshift($logs, [
        'domain'    => $domain,
        'ip'        => $client_ip,
        'referer'   => $referer,
        'timestamp' => current_time('mysql'),
    ]);
    update_option('tew_redirect_logs', array_slice($logs, 0, 500), false);
    
    // Buscar página con shortcode [eco_performance_snapshot]
    $report_page = '/calculadora-huella/';
    
    // Primero buscar si ya existe un informe guardado para este dominio
    $args = [
        'post_type'      => 'tew_report',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'meta_query'     => [
            [
                'key'     => '_tew_domain',
                'value'   => $domain,
                'compare' => '=',
            ],
        ],
        'orderby'        => 'date',
        'order'          => 'DESC',
    ];
    $existing = get_posts($args);
    
    if (!empty($existing)) {
        // Ya existe informe - redirigir directamente a él
        wp_redirect(get_permalink($existing[0]->ID), 302);
        exit;
    }
    
    // No existe - redirigir a calculadora para generarlo (con from_campaign para bypass Turnstile)
    wp_redirect(home_url($report_page . '?site=' . rawurlencode($domain) . '&from_campaign=1&auto=1'), 302);
    exit;
});

add_action( 'plugins_loaded', function () {
    $plugin = TEW\Plugin::instance();
    $plugin->boot();

    TEW\Cli_Command::register();
} );
