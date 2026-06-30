<?php
/**
 * RPHUB API Response
 *
 * Normalised response factory used across all Hub REST endpoints.
 * Ensures every response follows the same envelope:
 *
 *   Success: { "success": true,  "data": <any>,  "meta": { "timestamp": int } }
 *   Error:   { "success": false, "error": { "code": string, "message": string } }
 *
 * @package ReplacantaHub
 * @since   1.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RPHUB_API_Response {

    /**
     * Build a successful response array.
     *
     * @param mixed $data Payload to return in the "data" key.
     * @param array $meta Optional extra keys merged into the "meta" envelope.
     * @return array
     */
    public static function success( $data, array $meta = [] ): array {
        return [
            'success' => true,
            'data'    => $data,
            'meta'    => array_merge( [ 'timestamp' => time() ], $meta ),
        ];
    }

    /**
     * Build a WP_REST_Response representing an error.
     *
     * @param string $code    Machine-readable error code.
     * @param string $message Human-readable description.
     * @param int    $status  HTTP status code (default 400).
     * @return WP_REST_Response
     */
    public static function error( string $code, string $message, int $status = 400 ): WP_REST_Response {
        return new WP_REST_Response(
            [
                'success' => false,
                'error'   => [
                    'code'    => $code,
                    'message' => $message,
                ],
            ],
            $status
        );
    }
}
