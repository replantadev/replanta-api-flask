<?php
namespace TEW\API;

use WP_Error;
use function __;
use function get_bloginfo;
use function is_wp_error;
use function wp_parse_args;
use function wp_remote_get;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class Client_Base {

    /**
     * @var string|null
     */
    protected $api_key;

    /**
     * @param string|null $api_key
     */
    public function __construct( $api_key = null ) {
        $this->api_key = $api_key;
    }

    /**
     * Realiza una solicitud GET.
     *
     * @param string $url
     * @param array  $args
     *
     * @return array|WP_Error
     */
    protected function get( $url, array $args = [] ) {
        $defaults = [
            'timeout' => 60, // Aumentado de 30 a 60 segundos para sitios lentos
            'headers' => [
                'Accept'     => 'application/json',
                'User-Agent' => $this->default_user_agent(),
            ],
        ];

        $response = wp_remote_get( $url, wp_parse_args( $args, $defaults ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code < 200 || $code >= 300 ) {
            return new WP_Error( 'tew_http_error', __( 'La API respondió con un estado inesperado.', 'test-eco-website' ), [
                'status' => $code,
                'body'   => $body,
                'url'    => $url,
            ] );
        }

        $decoded = json_decode( $body, true );
        if ( null === $decoded ) {
            return new WP_Error( 'tew_json_error', __( 'No se pudo interpretar la respuesta JSON.', 'test-eco-website' ), [
                'body' => $body,
                'url'  => $url,
            ] );
        }

        return $decoded;
    }

    /**
     * Construye un user-agent descriptivo para las peticiones remotas.
     *
     * @return string
     */
    protected function default_user_agent() {
        $plugin_version = defined( 'TEW_VERSION' ) ? TEW_VERSION : 'dev';

        if ( function_exists( 'get_bloginfo' ) ) {
            $wp_version = get_bloginfo( 'version' );
        } elseif ( defined( 'WP_VERSION' ) ) {
            $wp_version = constant( 'WP_VERSION' );
        } else {
            $wp_version = 'unknown';
        }

        // User-agent que parece navegador real pero identifica nuestro plugin
        return sprintf( 'Mozilla/5.0 (compatible; TEW-EcoSnapshot/%s; WordPress/%s; +https://replanta.net)', $plugin_version, $wp_version );
    }
}
