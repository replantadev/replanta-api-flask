<?php
namespace TEW\API;

use TEW\Utils;
use WP_Error;
use function add_query_arg;
use function get_locale;
use function is_wp_error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Pagespeed_Client extends Client_Base {

    const ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    /**
     * Ejecuta la auditoría para móvil y escritorio.
     *
     * @param string $url
     *
     * @return array|WP_Error
     */
    public function audit( $url ) {
        $strategies = [ 'mobile', 'desktop' ];
        $results    = [];

        foreach ( $strategies as $strategy ) {
            $query = [
                'url'       => $url,
                'strategy'  => $strategy,
                'category'  => 'performance',
                'locale'    => get_locale(),
            ];

            if ( $this->api_key ) {
                $query['key'] = $this->api_key;
            }

        $args = [
            'headers' => [
                'Referer'    => rtrim( home_url(), '/' ),
                'User-Agent' => 'TEW-EcoSnapshot/1.0 (+' . home_url() . ')',
            ],
        ];

        $response = $this->get( add_query_arg( $query, self::ENDPOINT ), $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }            $results[ $strategy ] = $this->parse_metrics( $response );
        }

        return [
            'mobile'  => $results['mobile'],
            'desktop' => $results['desktop'],
        ];
    }

    /**
     * Extrae métricas relevantes.
     */
    private function parse_metrics( array $data ) {
        $result     = [
            'score'          => null,
            'lcp_ms'         => null,
            'lcp_seconds'    => null,
            'tbt_ms'         => null,
            'inp_ms'         => null,
            'cls'            => null,
            'ttfb_ms'        => null,
            'total_byte_weight' => null,
            'report_url'     => isset( $data['lighthouseResult']['finalUrl'] ) ? $data['lighthouseResult']['finalUrl'] : null,
            'screenshot'     => isset( $data['lighthouseResult']['audits']['final-screenshot']['details']['data'] ) ? $data['lighthouseResult']['audits']['final-screenshot']['details']['data'] : null,
            'timestamp'      => isset( $data['analysisUTCTimestamp'] ) ? $data['analysisUTCTimestamp'] : null,
            'lighthouse_ref' => isset( $data['lighthouseResult']['lighthouseVersion'] ) ? $data['lighthouseResult']['lighthouseVersion'] : null,
        ];

        $lighthouse = isset( $data['lighthouseResult'] ) ? $data['lighthouseResult'] : [];
        $audits     = isset( $lighthouse['audits'] ) ? $lighthouse['audits'] : [];

        if ( isset( $lighthouse['categories']['performance']['score'] ) ) {
            $result['score'] = round( $lighthouse['categories']['performance']['score'] * 100 );
        }

        if ( isset( $audits['largest-contentful-paint']['numericValue'] ) ) {
            $result['lcp_ms']      = (float) $audits['largest-contentful-paint']['numericValue'];
            $result['lcp_seconds'] = Utils::ms_to_seconds( $result['lcp_ms'] );
        }

        if ( isset( $audits['total-blocking-time']['numericValue'] ) ) {
            $result['tbt_ms'] = (float) $audits['total-blocking-time']['numericValue'];
        }

        if ( isset( $audits['cumulative-layout-shift']['numericValue'] ) ) {
            $result['cls'] = (float) $audits['cumulative-layout-shift']['numericValue'];
        }

        if ( isset( $audits['experimental-interaction-to-next-paint']['numericValue'] ) ) {
            $result['inp_ms'] = (float) $audits['experimental-interaction-to-next-paint']['numericValue'];
        } elseif ( isset( $audits['max-potential-fid']['numericValue'] ) ) {
            $result['inp_ms'] = (float) $audits['max-potential-fid']['numericValue'];
        }

        // TTFB (Time To First Byte) - server response time
        if ( isset( $audits['server-response-time']['numericValue'] ) ) {
            $result['ttfb_ms'] = (float) $audits['server-response-time']['numericValue'];
        }

        if ( isset( $audits['total-byte-weight']['numericValue'] ) ) {
            $result['total_byte_weight'] = (float) $audits['total-byte-weight']['numericValue'];
        }

        return $result;
    }
}
