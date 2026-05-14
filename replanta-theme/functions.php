<?php
/**
 * Replanta Theme — bootstrap.
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

define( 'RT_THEME_VERSION', '0.1.0' );
define( 'RT_THEME_DIR', trailingslashit( get_stylesheet_directory() ) );
define( 'RT_THEME_URL', trailingslashit( get_stylesheet_directory_uri() ) );
define( 'RT_THEME_FILE', __FILE__ );

spl_autoload_register(
	static function ( string $class ): void {
		if ( strpos( $class, 'RT_' ) !== 0 ) {
			return;
		}
		$slug = strtolower( str_replace( '_', '-', $class ) );
		$candidates = [
			RT_THEME_DIR . "inc/class-{$slug}.php",
			RT_THEME_DIR . "inc/providers/class-{$slug}.php",
			RT_THEME_DIR . "inc/cli/class-{$slug}.php",
		];
		foreach ( $candidates as $file ) {
			if ( is_readable( $file ) ) {
				require_once $file;
				return;
			}
		}
	}
);

add_action(
	'after_setup_theme',
	static function (): void {
		load_theme_textdomain( 'replanta-theme', RT_THEME_DIR . 'languages' );
		add_theme_support( 'wp-block-styles' );
		add_theme_support( 'editor-styles' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'html5', [ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ] );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'title-tag' );
		add_theme_support( 'align-wide' );
	},
	5
);

add_filter( 'should_load_separate_core_block_assets', '__return_true' );

add_action(
	'init',
	static function (): void {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	}
);

add_action(
	'after_setup_theme',
	static function (): void {
		if ( class_exists( 'RT_Theme' ) ) {
			RT_Theme::instance()->init();
		}
	},
	20
);

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once RT_THEME_DIR . 'inc/cli/class-rt-cli.php';
}
