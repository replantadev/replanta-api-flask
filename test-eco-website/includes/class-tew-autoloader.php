<?php
namespace TEW;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Autoloader {

    /**
     * Namespace prefix para todas las clases del plugin.
     *
     * @var string
     */
    private static $prefix = __NAMESPACE__ . '\\';

    /**
     * Inicializa el autoloader.
     */
    public static function init() {
        spl_autoload_register( [ __CLASS__, 'autoload' ] );
    }

    /**
     * Carga automática de clases siguiendo convención PSR-4 simplificada.
     *
     * @param string $class Nombre completo de la clase.
     */
    private static function autoload( $class ) {
        if ( 0 !== strpos( $class, self::$prefix ) ) {
            return;
        }

        $relative_class = substr( $class, strlen( self::$prefix ) );
        $relative_path  = strtolower( str_replace( '\\', '/', $relative_class ) );

        $candidates = [];

        // Convención principal: class-{namespace}-{class}.php
        $candidates[] = TEW_PLUGIN_DIR . 'includes/class-' . str_replace( [ '\\', '/' ], '-', $relative_path ) . '.php';

        // Convención con prefijo tew-
        $candidates[] = TEW_PLUGIN_DIR . 'includes/class-tew-' . str_replace( [ '\\', '/' ], '-', $relative_path ) . '.php';

        // Convención por directorios: includes/{namespace}/class-{class}.php
        $segments = explode( '/', $relative_path );
        if ( $segments ) {
            $filename     = 'class-' . str_replace( '_', '-', array_pop( $segments ) ) . '.php';
            $directory    = $segments ? implode( '/', $segments ) . '/' : '';
            $candidates[] = TEW_PLUGIN_DIR . 'includes/' . $directory . $filename;
        }

        // Convención simple: includes/{namespace}/{class}.php
        $candidates[] = TEW_PLUGIN_DIR . 'includes/' . str_replace( '_', '-', $relative_path ) . '.php';

        foreach ( array_unique( $candidates ) as $candidate ) {
            $candidate = str_replace( '\\', '/', $candidate );
            if ( file_exists( $candidate ) ) {
                require_once $candidate;
                return;
            }
        }
    }
}
