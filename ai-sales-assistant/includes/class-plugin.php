<?php

namespace Replanta\AiChat;

defined( 'ABSPATH' ) || exit;

class Plugin {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
            self::$instance->init();
        }
        return self::$instance;
    }

    private function init(): void {
        Installer::maybe_upgrade();
        Updater::instance()->init();

        if ( is_admin() ) {
            Admin\Admin::instance()->init();
        }

        Rest\ChatEndpoint::instance()->init();
        Rest\FeedbackEndpoint::instance()->init();
        Rest\IndexingEndpoint::instance()->init();

        // Re-index on product save
        add_action( 'save_post_product', [ $this, 'on_product_save' ], 20, 1 );
        add_action( 'woocommerce_update_product', [ $this, 'on_product_save' ], 20, 1 );

        // Enqueue frontend widget
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend' ] );
        add_action( 'wp_footer', [ $this, 'render_widget' ] );
    }

    public function on_product_save( int $product_id ): void {
        $opts = Options::get_indexing();
        if ( empty( $opts['auto_index'] ) ) {
            return;
        }
        // Schedule async re-index to avoid blocking the save request
        wp_schedule_single_event( time() + 5, 'replanta_ai_chat_index_product', [ $product_id ] );
    }

    public function enqueue_frontend(): void {
        $general = Options::get_general();
        if ( empty( $general['chat_enabled'] ) ) {
            return;
        }
        if ( ! $this->should_show_on_current_page( $general['show_on'] ) ) {
            return;
        }

        wp_enqueue_style(
            'replanta-ai-chat',
            REPLANTA_AI_CHAT_URL . 'assets/css/chat-widget.css',
            [],
            REPLANTA_AI_CHAT_VERSION
        );

        wp_enqueue_script(
            'replanta-ai-chat',
            REPLANTA_AI_CHAT_URL . 'assets/js/chat-widget.js',
            [],
            REPLANTA_AI_CHAT_VERSION,
            true
        );

        wp_localize_script( 'replanta-ai-chat', 'replantaAiChat', [
            'apiUrl'        => rest_url( 'replanta/v1/chat' ),
            'nonce'         => wp_create_nonce( 'wp_rest' ),
            'assistantName' => esc_js( $general['assistant_name'] ),
            'welcomeMsg'    => esc_js( $general['welcome_message'] ),
            'primaryColor'  => esc_js( $general['primary_color'] ),
            'position'      => esc_js( $general['widget_position'] ),
            'cartUrl'       => wc_get_cart_url(),
            'checkoutUrl'   => wc_get_checkout_url(),
            'i18n'          => [
                'placeholder'    => __( 'Escribe tu pregunta...', 'replanta-ai-chat' ),
                'send'           => __( 'Enviar', 'replanta-ai-chat' ),
                'addedToCart'    => __( 'Añadido al carrito', 'replanta-ai-chat' ),
                'viewCart'       => __( 'Ver carrito', 'replanta-ai-chat' ),
                'checkout'       => __( 'Ir a pagar', 'replanta-ai-chat' ),
                'errorGeneric'   => __( 'Error al conectar con el asistente.', 'replanta-ai-chat' ),
            ],
        ] );
    }

    public function render_widget(): void {
        $general = Options::get_general();
        if ( empty( $general['chat_enabled'] ) ) {
            return;
        }
        if ( ! $this->should_show_on_current_page( $general['show_on'] ) ) {
            return;
        }
        include REPLANTA_AI_CHAT_DIR . 'templates/chat-widget.php';
    }

    private function should_show_on_current_page( string $scope ): bool {
        return match ( $scope ) {
            'shop'    => is_shop() || is_product_category() || is_product_tag(),
            'product' => is_product(),
            'cart'    => is_cart() || is_checkout(),
            default   => true, // 'all'
        };
    }
}
