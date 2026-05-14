<?php
namespace TEW\API;

use TEW\Utils;
use WP_Error;
use function current_time;
use function is_wp_error;
use function rawurlencode;
use function trailingslashit;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Greenweb_Client extends Client_Base {

    const ENDPOINT = 'https://api.thegreenwebfoundation.org/greencheck';

    /**
     * @param string $url
     *
     * @return array|WP_Error
     */
    public function audit( $url ) {
        $domain   = Utils::get_domain( $url );
        $endpoint = trailingslashit( self::ENDPOINT ) . rawurlencode( $domain );

        $response = $this->get( $endpoint );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return [
            'domain'    => $domain,
            'is_green'  => ! empty( $response['green'] ),
            'hosted_by' => isset( $response['hostedby'] ) ? $response['hostedby'] : null,
            'country'   => isset( $response['country'] ) ? $response['country'] : null,
            'checked_on'=> isset( $response['modified'] ) ? $response['modified'] : current_time( 'mysql' ),
            'report_url'=> 'https://www.thegreenwebfoundation.org/green-web-check/?url=' . urlencode( $url ),
        ];
    }
}
