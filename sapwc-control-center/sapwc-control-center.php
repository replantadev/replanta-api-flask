<?php
/**
 * Plugin Name: SAP Woo Control Center
 * Description: Panel de operador para gestionar instalaciones remotas de SAP Woo Suite.
 * Version:     1.2.48
 * Author:      Replanta
 * Text Domain: sapwcc
 * Requires PHP: 8.0
 * Requires at least: 6.0
 * Tested up to: 7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SAPWCC_VERSION', '1.2.48' );
define( 'SAPWCC_PATH', plugin_dir_path( __FILE__ ) );
define( 'SAPWCC_URL', plugin_dir_url( __FILE__ ) );
define( 'SAPWCC_LATEST_SUITE_VERSION', '2.19.3' );

// HMAC secret shared with sap-woo-suite for flags.json integrity.
// Override in wp-config.php: define( 'SAPWCC_FLAGS_HMAC_SECRET', 'your-secret' );
if ( ! defined( 'SAPWCC_FLAGS_HMAC_SECRET' ) ) {
    define( 'SAPWCC_FLAGS_HMAC_SECRET', 'sapwc-flags-hmac-v1-change-me-in-wp-config' );
}

// Admin notice: warn if HMAC secret is the insecure default.
add_action( 'admin_notices', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    // Only show on SAP Control Center pages — avoid polluting unrelated admin screens.
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || strpos( $screen->id, 'sapwcc' ) === false ) {
        return;
    }
    if ( SAPWCC_FLAGS_HMAC_SECRET === 'sapwc-flags-hmac-v1-change-me-in-wp-config'
         && empty( get_option( 'sapwcc_flags_hmac_secret', '' ) ) ) {
        echo '<div class="notice notice-warning is-dismissible"><p>'
            . '<strong>SAP Woo Control Center:</strong> '
            . 'El HMAC secret para flags.json usa el valor por defecto publico. '
            . 'Define <code>SAPWCC_FLAGS_HMAC_SECRET</code> en <code>wp-config.php</code> o activa el plugin para generar uno automatico.'
            . '</p></div>';
    }
} );

require_once SAPWCC_PATH . 'includes/class-sites.php';
require_once SAPWCC_PATH . 'includes/class-flags.php';
require_once SAPWCC_PATH . 'includes/class-audit.php';
require_once SAPWCC_PATH . 'includes/class-ai.php';
require_once SAPWCC_PATH . 'includes/class-alerting.php';
require_once SAPWCC_PATH . 'includes/class-vigilante.php';

// Boot the Vigilante (registers cron callbacks).
SAPWCC_Vigilante::init();

register_deactivation_hook( __FILE__, [ 'SAPWCC_Vigilante', 'unschedule' ] );

/**
 * Get the effective HMAC secret used to sign flags.json.
 *
 * Priority:
 *   1. SAPWCC_FLAGS_HMAC_SECRET constant (if overridden in wp-config.php to a custom value).
 *   2. Auto-generated per-CC secret stored encrypted in wp_options.
 *   3. Empty string (signing is skipped — flags.json is published unsigned).
 *
 * @return string The HMAC secret, or empty string if not configured.
 */
function sapwcc_get_flags_hmac_secret(): string {
    $default = 'sapwc-flags-hmac-v1-change-me-in-wp-config';
    // Honor an explicit wp-config override.
    if ( defined( 'SAPWCC_FLAGS_HMAC_SECRET' ) && SAPWCC_FLAGS_HMAC_SECRET !== $default ) {
        return SAPWCC_FLAGS_HMAC_SECRET;
    }
    // Use the auto-generated secret (created on first load, stored encrypted).
    $stored = get_option( 'sapwcc_flags_hmac_secret', '' );
    if ( ! empty( $stored ) ) {
        return SAPWCC_Sites::decrypt( $stored );
    }
    return '';
}

// Auto-generate a unique HMAC secret for flags.json if the constant uses the insecure default.
// This runs once and provides per-CC security without requiring wp-config.php configuration.
add_action( 'plugins_loaded', function () {
    if ( SAPWCC_FLAGS_HMAC_SECRET === 'sapwc-flags-hmac-v1-change-me-in-wp-config'
         && empty( get_option( 'sapwcc_flags_hmac_secret', '' ) ) ) {
        update_option( 'sapwcc_flags_hmac_secret', SAPWCC_Sites::encrypt( wp_generate_password( 32, false, false ) ), false );
    }
    // Schedule Vigilante cron jobs if not already registered.
    SAPWCC_Vigilante::schedule();
}, 1 );

// â”€â”€â”€ Admin Menu â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

add_action( 'admin_menu', function () {
    add_menu_page(
        'SAP Woo Control Center',
        'SAP Control',
        'manage_options',
        'sapwcc',
        'sapwcc_render_dashboard',
        'dashicons-admin-multisite',
        3
    );
} );

// â”€â”€â”€ Enqueue Assets â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( $hook !== 'toplevel_page_sapwcc' ) {
        return;
    }

    wp_enqueue_style( 'sapwcc', SAPWCC_URL . 'assets/control-center.css', [], SAPWCC_VERSION );
    wp_enqueue_script( 'sapwcc', SAPWCC_URL . 'assets/control-center.js', [ 'jquery' ], SAPWCC_VERSION, true );
    wp_localize_script( 'sapwcc', 'sapwcc', [
        'ajax_url'        => admin_url( 'admin-ajax.php' ),
        'nonce'           => wp_create_nonce( 'sapwcc_nonce' ),
        'latest_version'  => SAPWCC_LATEST_SUITE_VERSION,
        'flags'           => SAPWCC_Flags::read(),
        'valid_plans'     => SAPWCC_Flags::VALID_PLANS,
        'plan_labels'     => SAPWCC_Flags::PLAN_LABELS,
        'plan_features'   => SAPWCC_Flags::PLAN_FEATURE_LABELS,
        'kill_switch_labels' => SAPWCC_Flags::get_labels(),
        'allowed_crons'   => [
            'sapwc_cron_sync_orders'     => 'Sync Pedidos',
            'sapwc_cron_sync_stock'      => 'Sync Stock',
            'sapwc_cron_sync_products'   => 'Sync Productos',
            'sapwc_cron_sync_categories' => 'Sync Categorias',
        ],
    ] );
} );

// â”€â”€â”€ Dashboard Render â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

function sapwcc_render_dashboard() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'No autorizado.' );
    }
    include SAPWCC_PATH . 'templates/page-dashboard.php';
}

// --- AJAX: Anadir sitio ---------------------------------------------------

add_action( 'wp_ajax_sapwcc_add_site', function () {
    check_ajax_referer( 'sapwcc_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'No autorizado.' );
    }

    $label  = sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) );
    $url    = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );
    $secret = sanitize_text_field( wp_unslash( $_POST['secret'] ?? '' ) );

    if ( empty( $label ) || empty( $url ) ) {
        wp_send_json_error( 'Label y URL son obligatorios.' );
    }

    if ( 0 !== strpos( $url, 'https://' ) ) {
        wp_send_json_error( 'La URL del sitio debe usar HTTPS para proteger el X-SAPWC-Secret en transito.' );
    }

    $result = SAPWCC_Sites::add( $label, $url, $secret );
    if ( $result ) {
        SAPWCC_Audit::log( 'site_added', $label . ' - ' . $url );

        // Push HMAC secret to the newly registered site so it can verify flags.json integrity.
        $hmac_secret = function_exists( 'sapwcc_get_flags_hmac_secret' ) ? sapwcc_get_flags_hmac_secret() : '';
        if ( ! empty( $hmac_secret ) ) {
            $site_endpoint = rtrim( $url, '/' ) . '/wp-json/sapwc/v1/control/set-flags-hmac-secret';
            wp_remote_post( $site_endpoint, [
                'timeout'   => 10,
                'sslverify' => ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ),
                'headers'   => [
                    'X-SAPWC-Secret' => $secret,
                    'Content-Type'   => 'application/json',
                ],
                'body' => wp_json_encode( [ 'secret' => $hmac_secret ] ),
            ] );
        }
    }
    $result ? wp_send_json_success( 'Sitio anadido.' ) : wp_send_json_error( 'Error al guardar.' );
} );

// â”€â”€â”€ AJAX: Eliminar sitio â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

add_action( 'wp_ajax_sapwcc_remove_site', function () {
    check_ajax_referer( 'sapwcc_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'No autorizado.' );
    }

    $key = sanitize_key( wp_unslash( $_POST['site_key'] ?? '' ) );
    if ( empty( $key ) ) {
        wp_send_json_error( 'Key vacia.' );
    }

    SAPWCC_Audit::log( 'site_removed', $key );
    SAPWCC_Sites::remove( $key );
    wp_send_json_success( 'Sitio eliminado.' );
} );

// â”€â”€â”€ AJAX: Health check (un sitio o todos) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

add_action( 'wp_ajax_sapwcc_check_health', function () {
    check_ajax_referer( 'sapwcc_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'No autorizado.' );
    }

    $key = sanitize_key( wp_unslash( $_POST['site_key'] ?? '' ) );

    if ( empty( $key ) || $key === 'all' ) {
        $results = SAPWCC_Sites::check_all_health();
        wp_send_json_success( $results );
    }

    $health = SAPWCC_Sites::fetch_health( $key );
    if ( is_wp_error( $health ) ) {
        wp_send_json_error( $health->get_error_message() );
    }
    wp_send_json_success( [ $key => $health ] );
} );

// â”€â”€â”€ AJAX: Guardar flags.json â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

add_action( 'wp_ajax_sapwcc_save_flags', function () {
    check_ajax_referer( 'sapwcc_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'No autorizado.' );
    }

    $json = wp_unslash( $_POST['flags_json'] ?? '' );
    $data = json_decode( $json, true );

    if ( json_last_error() !== JSON_ERROR_NONE ) {
        wp_send_json_error( 'JSON invalido: ' . json_last_error_msg() );
    }

    $result = SAPWCC_Flags::write( $data );
    if ( $result ) {
        SAPWCC_Audit::log( 'flags_saved', 'flags.json actualizado desde Control Center.' );
    }
    $result ? wp_send_json_success( 'Flags guardados en ' . SAPWCC_Flags::get_path() ) : wp_send_json_error( 'Error al guardar en ' . SAPWCC_Flags::get_path() . ' - verificar permisos del directorio.' );
} );

// â”€â”€â”€ AJAX: Guardar settings â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

add_action( 'wp_ajax_sapwcc_save_settings', function () {
    check_ajax_referer( 'sapwcc_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'No autorizado.' );
    }

    $path = sanitize_text_field( wp_unslash( $_POST['flags_path'] ?? '' ) );
    if ( ! empty( $path ) ) {
        update_option( 'sapwcc_flags_path', $path, false );
    }

    $token = trim( wp_unslash( $_POST['github_token'] ?? '' ) );
    if ( ! empty( $token ) ) {
        update_option( 'sapwcc_github_token', SAPWCC_Sites::encrypt( $token ), false );
    }

    $cc_ip = sanitize_text_field( wp_unslash( $_POST['control_center_ip'] ?? '' ) );
    update_option( 'sapwcc_control_center_ip', $cc_ip, false );

    // Propagate the new IP to all registered sites so /control/update is protected
    // without manual per-site configuration.
    if ( isset( $_POST['control_center_ip'] ) ) {
        $sites      = SAPWCC_Sites::get_all();
        $propagated = 0;
        foreach ( $sites as $site_key => $site ) {
            $site_url = rtrim( $site['url'], '/' ) . '/wp-json/sapwc/v1/control/set-cc-ip';
            $secret   = SAPWCC_Sites::get_decrypted_secret( $site_key );
            $r = wp_remote_post( $site_url, [
                'timeout'   => 10,
                'sslverify' => ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ),
                'headers'   => [
                    'X-SAPWC-Secret' => $secret,
                    'Content-Type'   => 'application/json',
                ],
                'body' => wp_json_encode( [ 'ip' => $cc_ip ] ),
            ] );
            if ( ! is_wp_error( $r ) && wp_remote_retrieve_response_code( $r ) === 200 ) {
                $propagated++;
            }
        }
        SAPWCC_Audit::log( 'set_cc_ip', "IP '{$cc_ip}' propagada a {$propagated}/" . count( $sites ) . ' sitios.' );
    }

    // Propagate the HMAC secret to all registered sites.
    // Runs every time settings are saved so that sites registered before CC v1.2.3
    // (when auto-push on add was introduced) also receive the secret.
    $hmac_secret = function_exists( 'sapwcc_get_flags_hmac_secret' ) ? sapwcc_get_flags_hmac_secret() : '';
    if ( ! empty( $hmac_secret ) ) {
        $sites           = SAPWCC_Sites::get_all();
        $hmac_propagated = 0;
        foreach ( $sites as $site_key => $site ) {
            $site_url = rtrim( $site['url'], '/' ) . '/wp-json/sapwc/v1/control/set-flags-hmac-secret';
            $secret   = SAPWCC_Sites::get_decrypted_secret( $site_key );
            $r = wp_remote_post( $site_url, [
                'timeout'   => 10,
                'sslverify' => ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ),
                'headers'   => [
                    'X-SAPWC-Secret' => $secret,
                    'Content-Type'   => 'application/json',
                ],
                'body' => wp_json_encode( [ 'secret' => $hmac_secret ] ),
            ] );
            if ( ! is_wp_error( $r ) && wp_remote_retrieve_response_code( $r ) === 200 ) {
                $hmac_propagated++;
            }
        }
        if ( $hmac_propagated > 0 ) {
            SAPWCC_Audit::log( 'set_flags_hmac_secret', "HMAC secret propagado a {$hmac_propagated}/" . count( $sites ) . ' sitios.' );
        }
    }

    $saved_token = SAPWCC_Sites::decrypt( get_option( 'sapwcc_github_token', '' ) );
    $token_info  = $saved_token ? 'Token: ' . substr( $saved_token, 0, 6 ) . '...' : 'Token: (vacio)';
    wp_send_json_success( 'Settings guardados. ' . $token_info );
} );

// â”€â”€â”€ AJAX: Git push flags.json â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

add_action( 'wp_ajax_sapwcc_git_push_flags', function () {
    check_ajax_referer( 'sapwcc_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'No autorizado.' );
    }

    // Read current flags data.
    $flags = SAPWCC_Flags::read();
    if ( empty( $flags ) ) {
        wp_send_json_error( 'flags.json vacio o no leido.' );
    }

    $token = SAPWCC_Sites::decrypt( get_option( 'sapwcc_github_token', '' ) );
    if ( empty( $token ) ) {
        wp_send_json_error( 'Token de GitHub no configurado. Guardalo en Configuracion > GitHub Token y pulsa Guardar configuracion.' );
    }

    $repo   = 'replantadev/sapwoo';
    $path   = 'docs/flags.json';
    $branch = 'main';
    $api    = "https://api.github.com/repos/{$repo}/contents/{$path}";

    // 1. Get current file SHA (required for updates).
    $get = wp_remote_get( $api . '?ref=' . $branch, [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/vnd.github.v3+json',
            'User-Agent'    => 'SAPWCC-Control-Center',
        ],
        'timeout' => 15,
    ] );

    $sha = '';
    if ( ! is_wp_error( $get ) && wp_remote_retrieve_response_code( $get ) === 200 ) {
        $existing = json_decode( wp_remote_retrieve_body( $get ), true );
        $sha      = $existing['sha'] ?? '';
    }

    // 2. Encode content as base64.
    $json    = wp_json_encode( $flags, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    $content = base64_encode( $json . "\n" );
    $message = 'flags: update from Control Center ' . current_time( 'Y-m-d H:i' );

    $body = [
        'message' => $message,
        'content' => $content,
        'branch'  => $branch,
    ];
    if ( $sha ) {
        $body['sha'] = $sha;
    }

    // 3. PUT to GitHub Contents API.
    $put = wp_remote_request( $api, [
        'method'  => 'PUT',
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/vnd.github.v3+json',
            'Content-Type'  => 'application/json',
            'User-Agent'    => 'SAPWCC-Control-Center',
        ],
        'body'    => wp_json_encode( $body ),
        'timeout' => 30,
    ] );

    if ( is_wp_error( $put ) ) {
        wp_send_json_error( 'Error de conexion con GitHub: ' . $put->get_error_message() );
    }

    $code     = wp_remote_retrieve_response_code( $put );
    $response = json_decode( wp_remote_retrieve_body( $put ), true );

    if ( $code === 200 || $code === 201 ) {
        $commit_sha  = $response['commit']['sha'] ?? '?';
        $result_msg  = "Publicado en GitHub Pages. Commit: " . substr( $commit_sha, 0, 7 );
        SAPWCC_Audit::log( 'flags_published', $result_msg );

        // Flush feature flags transient en todos los sitios registrados.
        $sites       = SAPWCC_Sites::get_all();
        $flush_ok    = 0;
        $flush_fail  = 0;
        foreach ( $sites as $skey => $sdata ) {
            $surl    = $sdata['url'] . '/wp-json/sapwc/v1/control/clear-cache';
            $ssecret = SAPWCC_Sites::get_decrypted_secret( $skey );
            $flush   = wp_remote_post( $surl, [
                'timeout'   => 10,
                'sslverify' => ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ),
                'headers'   => [
                    'X-SAPWC-Secret' => $ssecret,
                    'Content-Type'   => 'application/json',
                ],
            ] );
            if ( ! is_wp_error( $flush ) && wp_remote_retrieve_response_code( $flush ) === 200 ) {
                $flush_ok++;
            } else {
                $flush_fail++;
            }
        }

        $result_msg .= " | Cache flush: {$flush_ok} OK, {$flush_fail} fail de " . count( $sites ) . " sitios.";
        wp_send_json_success( $result_msg );
    } else {
        $gh_message = $response['message'] ?? 'Error desconocido';
        wp_send_json_error( "GitHub API error (HTTP {$code}): {$gh_message}" );
    }
} );

// â”€â”€â”€ AJAX: Remote action proxy (logs, clear-cache, run-cron, maintenance, update-check) â”€â”€

add_action( 'wp_ajax_sapwcc_remote_action', function () {
    check_ajax_referer( 'sapwcc_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'No autorizado.' );
    }

    $site_key   = sanitize_key( wp_unslash( $_POST['site_key'] ?? '' ) );
    $endpoint   = sanitize_text_field( wp_unslash( $_POST['endpoint'] ?? '' ) );
    $method     = strtoupper( sanitize_text_field( wp_unslash( $_POST['method'] ?? 'GET' ) ) );
    $body_json  = wp_unslash( $_POST['body'] ?? '' );

    $allowed_endpoints = [
        'control/logs',
        'control/clear-cache',
        'control/run-cron',
        'control/maintenance',
        'control/update-check',
        'control/update',
        'control/rotate-secret',
        'control/set-cc-ip',
        'control/set-flags-hmac-secret',
        'control/pending-issues',
        'control/repair-ship-to',
        'control/repair-duplicates',
        'control/mark-order-completed',
        'control/resolve-task',
        'control/unresolve-task',
        'control/mark-exported',
    ];

    if ( ! in_array( $endpoint, $allowed_endpoints, true ) ) {
        wp_send_json_error( 'Endpoint no permitido: ' . $endpoint );
    }

    $sites = SAPWCC_Sites::get_all();
    if ( ! isset( $sites[ $site_key ] ) ) {
        wp_send_json_error( 'Sitio no encontrado: ' . $site_key );
    }

    $site   = $sites[ $site_key ];
    $url    = $site['url'] . '/wp-json/sapwc/v1/' . $endpoint;
    $secret = SAPWCC_Sites::get_decrypted_secret( $site_key );

    $args = [
        'timeout'   => ( $endpoint === 'control/update' ) ? 90 : 30,
        'sslverify' => ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ),
        'headers'   => [
            'X-SAPWC-Secret' => $secret,
            'Content-Type'   => 'application/json',
        ],
    ];

    if ( $method === 'POST' ) {
        $args['body'] = $body_json ?: '{}';
        $response = wp_remote_post( $url, $args );
    } else {
        // Append query params from body if GET.
        if ( ! empty( $body_json ) ) {
            $params = json_decode( $body_json, true );
            if ( is_array( $params ) ) {
                $url = add_query_arg( $params, $url );
            }
        }
        $response = wp_remote_get( $url, $args );
    }

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( 'Error de conexion: ' . $response->get_error_message() );
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    // Audit log for remote actions.
    $audit_map = [
        'control/logs'                   => 'remote_logs',
        'control/clear-cache'            => 'remote_cache',
        'control/run-cron'               => 'remote_cron',
        'control/maintenance'            => 'remote_maint',
        'control/update-check'           => 'health_check',
        'control/update'                 => 'remote_update',
        'control/rotate-secret'          => 'rotate_secret',
        'control/set-cc-ip'              => 'set_cc_ip',
        'control/set-flags-hmac-secret'  => 'set_flags_hmac_secret',
        'control/resolve-task'           => 'resolve_task',
        'control/unresolve-task'         => 'unresolve_task',
        'control/mark-exported'          => 'mark_exported',
    ];
    $audit_action = $audit_map[ $endpoint ] ?? 'remote_action';
    SAPWCC_Audit::log( $audit_action, "HTTP {$code} - {$endpoint}", $site['label'] );

    if ( $code >= 200 && $code < 300 ) {
        // After a successful secret rotation, persist the new secret in the Control Center.
        if ( $endpoint === 'control/rotate-secret' && ! empty( $body['new_secret'] ) ) {
            SAPWCC_Sites::update_secret( $site_key, $body['new_secret'] );
            // Remove the secret from the response body before sending to the browser.
            unset( $body['new_secret'] );
        }
        // After a successful update, invalidate health cache so the dashboard reflects
        // the new plugin version on reload instead of the 5-min cached pre-update one.
        if ( $endpoint === 'control/update' ) {
            delete_transient( SAPWCC_Sites::HEALTH_PREFIX . $site_key );
            // No re-ping immediately: remote opcache may still hold old plugin headers.
            // JS will re-ping after a delay (see control-center.js update handler).
        }
        wp_send_json_success( $body );
    } else {
        wp_send_json_error( [
            'http_code' => $code,
            'response'  => $body,
        ] );
    }
} );

// â”€â”€â”€ AJAX: Actualizar metadatos de sitio (client_name, email, MRR) â”€â”€â”€â”€â”€â”€

add_action( 'wp_ajax_sapwcc_update_site_meta', function () {
    check_ajax_referer( 'sapwcc_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'No autorizado.' );
    }

    $key = sanitize_key( wp_unslash( $_POST['site_key'] ?? '' ) );
    $meta = [
        'client_name'   => sanitize_text_field( wp_unslash( $_POST['client_name'] ?? '' ) ),
        'client_email'  => sanitize_email( wp_unslash( $_POST['client_email'] ?? '' ) ),
        'contract_date' => sanitize_text_field( wp_unslash( $_POST['contract_date'] ?? '' ) ),
        'monthly_fee'   => floatval( $_POST['monthly_fee'] ?? 0 ),
        'quiet_from'    => isset( $_POST['quiet_from'] ) && $_POST['quiet_from'] !== '' ? absint( $_POST['quiet_from'] ) : '',
        'quiet_to'      => isset( $_POST['quiet_to'] )   && $_POST['quiet_to']   !== '' ? absint( $_POST['quiet_to'] )   : '',
    ];

    $result = SAPWCC_Sites::update_meta( $key, $meta );

    if ( $result ) {
        SAPWCC_Audit::log( 'site_meta_update', wp_json_encode( $meta ), $key );
        wp_send_json_success( 'Datos actualizados.' );
    } else {
        wp_send_json_error( 'Error al guardar.' );
    }
} );

// â”€â”€â”€ AJAX: Obtener audit log â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

add_action( 'wp_ajax_sapwcc_get_audit', function () {
    check_ajax_referer( 'sapwcc_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'No autorizado.' );
    }

    $limit = absint( $_POST['limit'] ?? 50 );
    wp_send_json_success( SAPWCC_Audit::get_all( $limit ) );
} );

/**
 * AJAX — Asignar plan a un sitio en flags.json
 */
add_action( 'wp_ajax_sapwcc_assign_plan', function () {
    check_ajax_referer( 'sapwcc_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'No autorizado.' );
    }

    $site_id = sanitize_text_field( wp_unslash( $_POST['site_id'] ?? '' ) );
    $plan    = sanitize_text_field( wp_unslash( $_POST['plan'] ?? '' ) );

    if ( empty( $site_id ) ) {
        wp_send_json_error( 'Site ID requerido.' );
    }

    // Validate plan
    $valid_plans = [ 'starter', 'business', 'enterprise', '' ]; // empty = remove assignment
    if ( ! in_array( $plan, $valid_plans, true ) ) {
        wp_send_json_error( 'Plan invalido.' );
    }

    // Read flags.json
    $flags = SAPWCC_Flags::read();
    if ( ! isset( $flags['sites'][ $site_id ] ) ) {
        $flags['sites'][ $site_id ] = [];
    }

    // Assign or remove plan
    if ( empty( $plan ) ) {
        unset( $flags['sites'][ $site_id ]['plan'] );
    } else {
        $flags['sites'][ $site_id ]['plan'] = $plan;
    }

    // Write flags.json
    if ( ! SAPWCC_Flags::write( $flags ) ) {
        wp_send_json_error( 'Error al escribir flags.json' );
    }

    // Audit log
    SAPWCC_Audit::log(
        'plan_change',
        "Plan de {$site_id} " . ( empty( $plan ) ? 'eliminado' : "cambiado a {$plan}" ),
        $site_id
    );

    wp_send_json_success( [
        'message' => 'Plan asignado correctamente.',
        'plan'    => $plan,
    ] );
} );

// ─── AJAX: Vigilante — escanear un sitio ─────────────────────────────────────

add_action( 'wp_ajax_sapwcc_vigilante_scan', function () {
    check_ajax_referer( 'sapwcc_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'No autorizado.' );
    }

    $site_key = sanitize_key( wp_unslash( $_POST['site_key'] ?? '' ) );

    if ( $site_key === 'all' || empty( $site_key ) ) {
        SAPWCC_Vigilante::run_scheduled_scan();
        wp_send_json_success( [
            'message' => 'Escaneo completado para todos los sitios.',
            'results' => SAPWCC_Vigilante::get_all_results(),
        ] );
    } else {
        SAPWCC_Vigilante::scan_site( $site_key );
        wp_send_json_success( [
            'message' => 'Escaneo completado.',
            'result'  => SAPWCC_Vigilante::get_site_result( $site_key ),
        ] );
    }
} );

// ─── AJAX: Vigilante — explicación IA de un issue ────────────────────────────

add_action( 'wp_ajax_sapwcc_vigilante_ai', function () {
    check_ajax_referer( 'sapwcc_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'No autorizado.' );
    }

    $issue_id   = sanitize_text_field( wp_unslash( $_POST['issue_id']   ?? '' ) );
    $issue_type = sanitize_text_field( wp_unslash( $_POST['issue_type'] ?? '' ) );
    $site_label = sanitize_text_field( wp_unslash( $_POST['site_label'] ?? '' ) );
    $context    = json_decode( wp_unslash( $_POST['context'] ?? '{}' ), true );

    if ( ! SAPWCC_AI::is_configured() ) {
        wp_send_json_error( 'IA no configurada. Añade una API key en la configuración del Vigilante.' );
    }

    $explanation = SAPWCC_AI::explain( $issue_type, $context ?: [], $site_label, $issue_id );
    if ( ! $explanation ) {
        wp_send_json_error( 'No se pudo obtener respuesta de la IA. Verifica que la API key sea válida.' );
    }

    wp_send_json_success( $explanation );
} );

// ─── AJAX: Vigilante — guardar configuración ─────────────────────────────────

add_action( 'wp_ajax_sapwcc_vigilante_save_config', function () {
    check_ajax_referer( 'sapwcc_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'No autorizado.' );
    }

    $alert_email    = sanitize_email( wp_unslash( $_POST['alert_email']    ?? '' ) );
    $claude_key     = trim( wp_unslash( $_POST['claude_key']               ?? '' ) );
    $openai_key     = trim( wp_unslash( $_POST['openai_key']               ?? '' ) );
    $digest_enabled = sanitize_text_field( wp_unslash( $_POST['digest_enabled'] ?? '0' ) );

    if ( ! empty( $alert_email ) ) {
        update_option( 'sapwcc_alert_email', $alert_email, false );
    }

    if ( ! empty( $claude_key ) && strpos( $claude_key, '•' ) === false ) {
        SAPWCC_AI::save_claude_key( $claude_key );
    }
    if ( ! empty( $openai_key ) && strpos( $openai_key, '•' ) === false ) {
        SAPWCC_AI::save_openai_key( $openai_key );
    }

    update_option( 'sapwcc_vig_digest_enabled', $digest_enabled === '1' ? '1' : '0', false );

    SAPWCC_Audit::log( 'vigilante_config', 'Configuración Vigilante actualizada.' );
    wp_send_json_success( 'Configuración guardada correctamente.' );
} );

// ─── AJAX: Vigilante — digest de prueba ──────────────────────────────────────

add_action( 'wp_ajax_sapwcc_vigilante_test_digest', function () {
    check_ajax_referer( 'sapwcc_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'No autorizado.' );
    }

    $sent = SAPWCC_Alerting::send_weekly_digest();
    $to   = SAPWCC_Alerting::get_alert_email();

    $sent
        ? wp_send_json_success( "Digest enviado a {$to}." )
        : wp_send_json_error( 'No se pudo enviar. Verifica el email configurado y que wp_mail funcione.' );
} );
