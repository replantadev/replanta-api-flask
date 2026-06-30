<?php

namespace Replanta\AiChat;

defined( 'ABSPATH' ) || exit;

class Autoloader {

    private static string $base_namespace = 'Replanta\\AiChat\\';
    private static string $base_dir       = '';

    public static function register(): void {
        self::$base_dir = REPLANTA_AI_CHAT_DIR . 'includes/';
        spl_autoload_register( [ self::class, 'load' ] );
    }

    public static function load( string $class ): void {
        if ( strpos( $class, self::$base_namespace ) !== 0 ) {
            return;
        }

        $relative   = substr( $class, strlen( self::$base_namespace ) );
        $parts      = explode( '\\', $relative );
        $class_name = array_pop( $parts );

        // CamelCase → kebab-case: LlmResponse → llm-response
        $kebab = strtolower( preg_replace( '/([A-Z])/', '-$1', lcfirst( $class_name ) ) );

        $sub_path = '';
        if ( ! empty( $parts ) ) {
            $sub_path = strtolower( implode( '/', $parts ) ) . '/';
        }

        // Try class-, interface-, trait- prefixes
        foreach ( [ 'class-', 'interface-', 'trait-' ] as $prefix ) {
            $file = self::$base_dir . $sub_path . $prefix . $kebab . '.php';
            if ( file_exists( $file ) ) {
                require_once $file;
                return;
            }
        }
    }
}
