<?php

namespace Replanta\AiChat\Rest;

defined( 'ABSPATH' ) || exit;

class FeedbackEndpoint {

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
        register_rest_route( 'replanta/v1', '/feedback', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'message_id' => [ 'required' => true, 'type' => 'integer' ],
                'rating'     => [ 'required' => true, 'type' => 'integer', 'minimum' => -1, 'maximum' => 1 ],
                'reason'     => [ 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );
    }

    public function handle( \WP_REST_Request $request ): \WP_REST_Response {
        global $wpdb;

        $wpdb->insert( $wpdb->prefix . 'replanta_feedback', [
            'message_id' => $request->get_param( 'message_id' ),
            'rating'     => $request->get_param( 'rating' ),
            'reason'     => $request->get_param( 'reason' ) ?? null,
            'created_at' => current_time( 'mysql' ),
        ] );

        return new \WP_REST_Response( [ 'success' => true ], 200 );
    }
}
