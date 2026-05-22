<?php
/**
 * Plugin Name: Replanta Sitemap XML Fix
 * Description: Elimina BOM y whitespace del inicio de sitemaps generados por RankMath
 * Version: 1.3
 */

add_action( 'init', function () {
    if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], 'sitemap' ) !== false ) {
        ob_start( function ( $buffer ) {
            $buffer = preg_replace( '/^\xEF\xBB\xBF/', '', $buffer ); // BOM UTF-8
            return ltrim( $buffer );
        } );
    }
}, 1 );

add_action( 'send_headers', function () {
    if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], 'sitemap' ) !== false ) {
        if ( ! headers_sent() ) {
            header( 'Content-Type: application/xml; charset=UTF-8' );
            header( 'X-Robots-Tag: noindex, follow' );
        }
    }
}, 1 );
