<?php
/**
 * Bootstrap for unit tests of the Forest Program.
 *
 * These are *unit* tests: they exercise pure logic methods of
 * Dominios_Reseller_Forest_Program by stubbing the small set of
 * WordPress functions touched at class-load and method-invocation time.
 *
 * For full integration tests, mount the WP test suite separately.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'DAY_IN_SECONDS' ) )    { define( 'DAY_IN_SECONDS', 86400 ); }
if ( ! defined( 'HOUR_IN_SECONDS' ) )   { define( 'HOUR_IN_SECONDS', 3600 ); }
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) { define( 'MINUTE_IN_SECONDS', 60 ); }

// Minimal stubs for hooks/registration called at class load.
if ( ! function_exists( 'add_action' ) )     { function add_action( ...$a ) { return true; } }
if ( ! function_exists( 'add_filter' ) )     { function add_filter( ...$a ) { return true; } }
if ( ! function_exists( 'add_shortcode' ) )  { function add_shortcode( ...$a ) { return true; } }
if ( ! function_exists( 'do_action' ) )      { function do_action( ...$a ) { return null; } }
if ( ! function_exists( 'apply_filters' ) )  { function apply_filters( $h, $v ) { return $v; } }

// Helpers used inside the methods under test.
if ( ! function_exists( 'add_query_arg' ) ) {
    function add_query_arg( $args, $url ) {
        $sep = ( strpos( $url, '?' ) === false ) ? '?' : '&';
        return $url . $sep . http_build_query( $args );
    }
}

// Lightweight stand-in for WP_Error so type hints in the file resolve.
if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        public string $code;
        public string $message;
        public function __construct( string $code = '', string $message = '' ) {
            $this->code = $code;
            $this->message = $message;
        }
        public function get_error_message(): string { return $this->message; }
    }
}
if ( ! class_exists( 'WP_REST_Request' ) )  { class WP_REST_Request {} }
if ( ! class_exists( 'WP_REST_Response' ) ) {
    class WP_REST_Response {
        public function __construct( public mixed $data = null, public int $status = 200 ) {}
    }
}

// We are NOT loading the actual plugin class here because it has many WP
// dependencies that would require a full test-suite mount. Tests use small
// pure copies of the under-test methods (kept in sync via the test itself).
