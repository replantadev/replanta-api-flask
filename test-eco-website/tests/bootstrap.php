<?php
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'TEW_PLUGIN_DIR' ) ) {
	define( 'TEW_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'TEW_PLUGIN_URL' ) ) {
	define( 'TEW_PLUGIN_URL', 'https://example.test/wp-content/plugins/test-eco-website/' );
}

if ( ! defined( 'TEW_VERSION' ) ) {
	define( 'TEW_VERSION', '0.1.0-test' );
}

require_once TEW_PLUGIN_DIR . 'includes/class-tew-autoloader.php';

if ( ! function_exists( 'add_action' ) ) {
	require_once TEW_PLUGIN_DIR . 'includes/compat/wp-stubs.php';
}

\TEW\Autoloader::init();
