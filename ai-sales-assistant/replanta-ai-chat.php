<?php
/**
 * Plugin Name: Replanta AI Chat
 * Plugin URI:  https://replanta.dev/plugins/ai-chat
 * Description: Chatbot de IA para WooCommerce con RAG antihalución: responde preguntas sobre productos, añade al carrito y prepara pedidos.
 * Version:     1.0.2
 * Author:      Replanta
 * Author URI:  https://replanta.dev
 * License:     Proprietary
 * Text Domain: replanta-ai-chat
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * WC requires at least: 8.0
 * WC tested up to:   9.9
 */

defined( 'ABSPATH' ) || exit;

define( 'REPLANTA_AI_CHAT_VERSION', '1.0.2' );
define( 'REPLANTA_AI_CHAT_FILE',    __FILE__ );
define( 'REPLANTA_AI_CHAT_DIR',     plugin_dir_path( __FILE__ ) );
define( 'REPLANTA_AI_CHAT_URL',     plugin_dir_url( __FILE__ ) );
define( 'REPLANTA_AI_CHAT_BASENAME', plugin_basename( __FILE__ ) );

require_once REPLANTA_AI_CHAT_DIR . 'includes/class-autoloader.php';
\Replanta\AiChat\Autoloader::register();

register_activation_hook( __FILE__, [ \Replanta\AiChat\Installer::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \Replanta\AiChat\Installer::class, 'deactivate' ] );

add_action( 'plugins_loaded', function () {
    load_plugin_textdomain( 'replanta-ai-chat', false, dirname( REPLANTA_AI_CHAT_BASENAME ) . '/languages' );

    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', static function () {
            echo '<div class="notice notice-error"><p>'
                . esc_html__( 'Replanta AI Chat requiere WooCommerce activo.', 'replanta-ai-chat' )
                . '</p></div>';
        } );
        return;
    }

    \Replanta\AiChat\Plugin::instance();
} );
