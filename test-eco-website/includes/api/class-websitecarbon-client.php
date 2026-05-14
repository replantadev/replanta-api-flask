<?php
namespace TEW\API;

use WP_Error;
use function __;
use function add_query_arg;
use function is_wp_error;
use function rawurlencode;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Websitecarbon_Client extends Client_Base {

    const ENDPOINT = 'https://api.websitecarbon.com/data';

    /**
     * Calcula la huella de carbono usando el endpoint público /data.
     *
     * @param string   $url   URL original (solo para metadatos/reporting).
     * @param int|float $bytes Peso total en bytes de la carga de la página.
     * @param bool     $is_green Indica si el hosting es verde.
     *
     * @return array|WP_Error
     */
    public function audit( $url, $bytes = null, $is_green = false ) {
        if ( null === $bytes || ! is_numeric( $bytes ) || $bytes <= 0 ) {
            return new WP_Error( 'tew_wc_missing_bytes', __( 'Website Carbon necesita el peso de la página en bytes para realizar el cálculo.', 'test-eco-website' ), [ 'status' => 400 ] );
        }

        $query = [
            'bytes' => (int) round( $bytes ),
            'green' => $is_green ? 1 : 0,
        ];

        $response = $this->get( add_query_arg( $query, self::ENDPOINT ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $co2_grid      = isset( $response['statistics']['co2']['grid']['grams'] ) ? (float) $response['statistics']['co2']['grid']['grams'] : null;
        $co2_renewable = isset( $response['statistics']['co2']['renewable']['grams'] ) ? (float) $response['statistics']['co2']['renewable']['grams'] : null;

        return [
            'cleaner_than'      => isset( $response['cleanerThan'] ) ? (float) $response['cleanerThan'] * 100 : null,
            'co2_per_view'      => isset( $response['gco2e'] ) ? (float) $response['gco2e'] : $co2_grid,
            'co2_renewable'     => $co2_renewable,
            'is_green'          => ! empty( $response['green'] ),
            'bytes_transferred' => isset( $response['statistics']['adjustedBytes'] ) ? (float) $response['statistics']['adjustedBytes'] : ( isset( $response['bytes'] ) ? (float) $response['bytes'] : null ),
            'rating'            => isset( $response['rating'] ) ? $response['rating'] : null,
            'report_url'        => 'https://www.websitecarbon.com/site/' . urlencode( $url ),
            'inputs'            => $query,
        ];
    }
}
