<?php

namespace Replanta\AiChat\Rest;

use Replanta\AiChat\Chat\ChatService;
use Replanta\AiChat\Chat\SessionManager;

defined( 'ABSPATH' ) || exit;

class ChatEndpoint {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init(): void {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        register_rest_route( 'replanta/v1', '/chat', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_chat' ],
            'permission_callback' => '__return_true', // Public endpoint
            'args'                => [
                'message' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                    'validate_callback' => static fn( $v ) => strlen( trim( $v ) ) > 0 && strlen( $v ) < 2000,
                ],
                'session_id' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ] );
    }

    public function handle_chat( \WP_REST_Request $request ): \WP_REST_Response {
        $message    = $request->get_param( 'message' );
        $session_id = $request->get_param( 'session_id' );
        $session_mgr= new SessionManager();

        if ( ! $session_id ) {
            $header_session = $request->get_header( 'X-Replanta-Session' );
            $session_id     = $header_session ?: wp_generate_uuid4();
        }

        // Rate limiting: max 30 requests per session per hour
        if ( $this->is_rate_limited( $session_id ) ) {
            return new \WP_REST_Response(
                [ 'error' => __( 'Demasiadas peticiones. Inténtalo en unos minutos.', 'replanta-ai-chat' ) ],
                429
            );
        }

        try {
            $service  = new ChatService();
            $result   = $service->handle( $message, $session_id );

            return new \WP_REST_Response( array_merge( $result, [
                'session_id' => $session_id,
            ] ), 200 );

        } catch ( \RuntimeException $e ) {
            // Log it but return a graceful error to the user
            error_log( '[Replanta AI Chat] ' . $e->getMessage() );

            return new \WP_REST_Response( [
                'error'      => __( 'El asistente no está disponible en este momento. Por favor, inténtalo de nuevo.', 'replanta-ai-chat' ),
                'session_id' => $session_id,
            ], 503 );
        }
    }

    private function is_rate_limited( string $session_id ): bool {
        $key     = 'replanta_rl_' . md5( $session_id );
        $count   = (int) get_transient( $key );
        $limit   = 30;

        if ( $count >= $limit ) {
            return true;
        }

        if ( 0 === $count ) {
            set_transient( $key, 1, HOUR_IN_SECONDS );
        } else {
            set_transient( $key, $count + 1, HOUR_IN_SECONDS );
        }

        return false;
    }
}
