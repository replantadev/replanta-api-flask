<?php
/**
 * Awin URL Helper - Appends AWC parameter to Upmind order URLs.
 *
 * @package Replanta_Prices
 * @subpackage Awin
 */

defined( 'ABSPATH' ) || exit;

class Replanta_Awin_URL_Helper {

    /**
     * Initialize hooks.
     */
    public static function init() {
        // Filter for output buffer (fallback for non-plugin URLs)
        // Only enable if JS fallback is disabled - prefer JS for performance
        // add_filter( 'the_content', array( __CLASS__, 'filter_content_urls' ), 100 );
    }

    /**
     * Append AWC parameter to a Upmind order URL if cookie exists.
     *
     * @param string $url Original URL
     * @return string URL with AWC appended if appropriate
     */
    public static function append_awc( $url ) {
        if ( ! Replanta_Awin_Cookie::is_enabled() ) {
            return $url;
        }

        // Get current AWC
        $awc = Replanta_Awin_Cookie::get_awc();
        if ( empty( $awc ) ) {
            return $url;
        }

        // Check if URL is a valid target
        if ( ! self::is_target_url( $url ) ) {
            return $url;
        }

        // Check if AWC already exists in URL
        if ( self::url_has_awc( $url ) ) {
            return $url;
        }

        // Append AWC parameter
        $url = self::add_query_arg_safe( 'awc', $awc, $url );

        // Log the URL modification
        if ( class_exists( 'Replanta_Awin_Logger' ) ) {
            $settings = Replanta_Awin_Cookie::get_settings();
            if ( ! empty( $settings['detailed_logs'] ) ) {
                Replanta_Awin_Logger::log_event( 'url_modified', array(
                    'awc'          => $awc,
                    'original_url' => $url,
                ) );
            }
        }

        return $url;
    }

    /**
     * Check if URL is a valid target for AWC injection.
     *
     * @param string $url
     * @return bool
     */
    public static function is_target_url( $url ) {
        $settings      = Replanta_Awin_Cookie::get_settings();
        $target_domain = ! empty( $settings['target_domain'] ) ? $settings['target_domain'] : 'clientes.replanta.net';

        // Parse URL
        $parsed = wp_parse_url( $url );
        if ( ! $parsed || empty( $parsed['host'] ) ) {
            return false;
        }

        // Check if host matches target domain
        $host = strtolower( $parsed['host'] );
        if ( $host !== strtolower( $target_domain ) && ! str_ends_with( $host, '.' . strtolower( $target_domain ) ) ) {
            return false;
        }

        // Check if it's an order/product URL
        $path = isset( $parsed['path'] ) ? $parsed['path'] : '';
        if ( strpos( $path, '/order' ) === false ) {
            return false;
        }

        return true;
    }

    /**
     * Check if URL already has AWC parameter.
     *
     * @param string $url
     * @return bool
     */
    public static function url_has_awc( $url ) {
        $parsed = wp_parse_url( $url );
        if ( ! isset( $parsed['query'] ) ) {
            return false;
        }

        parse_str( $parsed['query'], $params );
        return isset( $params['awc'] ) && ! empty( $params['awc'] );
    }

    /**
     * Safely add query argument to URL, handling edge cases.
     *
     * @param string $key
     * @param string $value
     * @param string $url
     * @return string
     */
    public static function add_query_arg_safe( $key, $value, $url ) {
        // Use WordPress function for reliability
        return add_query_arg( $key, rawurlencode( $value ), $url );
    }

    /**
     * Build complete order URL with AWC.
     * Wrapper for templates that generates the full URL.
     *
     * @param string $pid Product ID
     * @param string|null $currency Optional currency override
     * @return string
     */
    public static function build_order_url( $pid, $currency = null ) {
        // Start with base Upmind URL
        $settings = Replanta_Awin_Cookie::get_settings();
        $target   = ! empty( $settings['target_domain'] ) ? $settings['target_domain'] : 'clientes.replanta.net';
        
        $url = 'https://' . $target . '/order/product?pid=' . rawurlencode( $pid );

        // Add currency if specified
        if ( $currency && 'EUR' !== $currency ) {
            $url = add_query_arg( 'currency', $currency, $url );
        }

        // Append AWC if available
        $url = self::append_awc( $url );

        return esc_url( $url );
    }

    /**
     * Filter content to rewrite Upmind URLs (fallback method).
     * Only used if JS fallback is disabled.
     *
     * @param string $content
     * @return string
     */
    public static function filter_content_urls( $content ) {
        if ( ! Replanta_Awin_Cookie::is_enabled() || ! Replanta_Awin_Cookie::has_awc() ) {
            return $content;
        }

        $settings = Replanta_Awin_Cookie::get_settings();
        $target   = preg_quote( $settings['target_domain'], '/' );

        // Pattern to match Upmind order URLs in href attributes
        $pattern = '/(href=["\'])https?:\/\/' . $target . '\/order[^"\']*(["\'])/i';

        return preg_replace_callback( $pattern, function( $matches ) {
            $url = str_replace( array( $matches[1], $matches[2] ), '', $matches[0] );
            $url = html_entity_decode( $url );
            $new_url = self::append_awc( $url );
            return $matches[1] . esc_url( $new_url ) . $matches[2];
        }, $content );
    }

    /**
     * Get JS configuration for frontend fallback.
     *
     * @return array
     */
    public static function get_js_config() {
        $settings = Replanta_Awin_Cookie::get_settings();

        return array(
            'enabled'      => Replanta_Awin_Cookie::is_enabled() && ! empty( $settings['js_fallback'] ),
            'cookieName'   => Replanta_Awin_Cookie::get_cookie_name(),
            'targetDomain' => ! empty( $settings['target_domain'] ) ? $settings['target_domain'] : 'clientes.replanta.net',
            'hasAwc'       => Replanta_Awin_Cookie::has_awc(),
        );
    }
}
