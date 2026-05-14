<?php
/**
 * Upmind API client.
 * Fetches product pricing via REST API with Bearer token auth.
 *
 * @package Replanta_Prices
 */

defined( 'ABSPATH' ) || exit;

class Replanta_Prices_Upmind_Api {

    /**
     * Fetch product data from Upmind.
     *
     * @param string $pid  Product UUID
     * @return array|WP_Error  Parsed product data or error
     */
    public static function get_product( $pid ) {
        $settings = get_option( 'replanta_prices_settings', array() );
        $token    = isset( $settings['api_token'] )    ? $settings['api_token']    : '';
        $base_url = isset( $settings['api_base_url'] ) ? $settings['api_base_url'] : 'https://api.upmind.io';

        if ( empty( $token ) ) {
            return new WP_Error( 'no_token', 'API token no configurado.' );
        }

        $url = trailingslashit( $base_url ) . 'api/admin/products/' . $pid . '?with=prices';

        $response = wp_remote_get( $url, array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
                'Run-As'        => 'user',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            return new WP_Error(
                'api_error',
                sprintf( 'Upmind API HTTP %d para PID %s', $code, $pid )
            );
        }

        $body = wp_remote_retrieve_body( $response );
        $ctype = wp_remote_retrieve_header( $response, 'content-type' );

        // Upmind returns HTML (the SPA) instead of JSON when the token
        // IP restriction blocks the request or the route is unrecognised.
        if ( $ctype && strpos( $ctype, 'application/json' ) === false ) {
            return new WP_Error(
                'not_json',
                sprintf(
                    'Upmind devolvió %s en vez de JSON (HTTP %d). Verifica que la IP del servidor esté permitida en el token API.',
                    $ctype,
                    $code
                )
            );
        }

        $data = json_decode( $body, true );

        if ( ! is_array( $data ) ) {
            return new WP_Error( 'parse_error', 'Respuesta JSON inválida: ' . json_last_error_msg() );
        }

        return $data;
    }

    /**
     * Extract billing cycle prices from Upmind product response.
     *
     * Upmind API returns `data.prices[]` with entries per currency/cycle.
     * Each entry has: currency_code, billing_cycle_months, price.
     * We extract EUR prices for monthly (1) and annual (12) cycles.
     *
     * @param array $product_data  Raw API response
     * @param string $currency     Currency code to extract (default EUR)
     * @return array  ['monthly' => float, 'annual' => float] or empty
     */
    public static function extract_prices( $product_data, $currency = 'EUR' ) {
        $prices = array(
            'monthly' => 0,
            'annual'  => 0,
        );

        // Upmind response: {status, data: {id, name, prices: [...]}}
        $price_list = array();
        if ( isset( $product_data['data']['prices'] ) ) {
            $price_list = $product_data['data']['prices'];
        } elseif ( isset( $product_data['prices'] ) ) {
            $price_list = $product_data['prices'];
        }

        foreach ( $price_list as $entry ) {
            // Filter by currency
            $entry_currency = isset( $entry['currency_code'] ) ? $entry['currency_code'] : '';
            if ( strtoupper( $entry_currency ) !== strtoupper( $currency ) ) {
                continue;
            }

            $cycle  = isset( $entry['billing_cycle_months'] ) ? (int) $entry['billing_cycle_months'] : 0;
            $amount = isset( $entry['price'] ) ? (float) $entry['price'] : 0;

            if ( 1 === $cycle && $amount > 0 ) {
                $prices['monthly'] = $amount;
            } elseif ( 12 === $cycle && $amount > 0 ) {
                $prices['annual'] = $amount;
            }
        }

        return $prices;
    }

    /**
     * Test API connection.
     *
     * @return true|WP_Error
     */
    public static function test_connection() {
        $settings = get_option( 'replanta_prices_settings', array() );
        $token    = isset( $settings['api_token'] )    ? $settings['api_token']    : '';
        $base_url = isset( $settings['api_base_url'] ) ? $settings['api_base_url'] : 'https://api.upmind.io';

        if ( empty( $token ) ) {
            return new WP_Error( 'no_token', 'API token no configurado.' );
        }

        // Ping a lightweight endpoint (clients list with limit=1)
        // Upmind API response format: {"data":[...], "links":{...}, "meta":{...}}
        // A 2xx response with valid JSON means the token is valid.
        $url      = trailingslashit( $base_url ) . 'api/admin/clients?limit=1';
        $response = wp_remote_get( $url, array(
            'timeout' => 10,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code  = wp_remote_retrieve_response_code( $response );
        $body  = wp_remote_retrieve_body( $response );
        $data  = json_decode( $body, true );
        $ctype = wp_remote_retrieve_header( $response, 'content-type' );

        // Non-JSON response (HTML SPA) means wrong base URL or IP restriction
        if ( $ctype && strpos( $ctype, 'application/json' ) === false ) {
            return new WP_Error(
                'not_json',
                sprintf(
                    'HTTP %d — respuesta no JSON (%s). URL llamada: %s — Cambia la Base URL al dominio de tu panel Upmind (ej: https://clientes.replanta.net)',
                    $code,
                    strtok( $ctype, ';' ),
                    $url
                )
            );
        }

        // Upmind API returns {"data":[...], "meta":{...}} on success — trust 2xx
        if ( $code >= 200 && $code < 300 && is_array( $data ) ) {
            return true;
        }

        // API returned an error response: extract message
        $msg = '';
        if ( isset( $data['error']['message'] ) ) {
            $msg = $data['error']['message'];
        } elseif ( isset( $data['message'] ) ) {
            $msg = $data['message'];
        }
        return new WP_Error( 'auth_failed', sprintf( 'HTTP %d — %s', $code, $msg ?: 'token inválido o sin permisos.' ) );
    }
}
