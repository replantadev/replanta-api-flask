<?php
namespace TEW;

use function wp_json_encode;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Logger {

    /** @var bool */
    private static $enabled = false;

    /**
     * Configura si se debe registrar información.
     */
    public static function set_enabled( $enabled ) {
        self::$enabled = (bool) $enabled;
    }

    /**
     * Envía una línea al log de depuración de WordPress.
     *
     * @param string $message
     * @param array  $context
     */
    public static function info( $message, array $context = [] ) {
        self::write( 'INFO', $message, $context );
    }

    public static function error( $message, array $context = [] ) {
        self::write( 'ERROR', $message, $context );
    }

    private static function write( $level, $message, array $context ) {
        if ( ! self::$enabled ) {
            return;
        }

        $line = sprintf( '[TEW][%s] %s', $level, $message );

        if ( ! empty( $context ) ) {
            $line .= ' ' . wp_json_encode( $context );
        }

        error_log( $line );
    }
}
