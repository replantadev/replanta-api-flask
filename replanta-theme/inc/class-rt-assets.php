<?php
/**
 * Front-end assets (Tailwind compiled CSS + interactivity bundle).
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class RT_Assets {

	public function register(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	public function enqueue(): void {
		$css = RT_THEME_DIR . 'assets/dist/theme.css';
		if ( is_readable( $css ) ) {
			wp_enqueue_style(
				'replanta-theme',
				RT_THEME_URL . 'assets/dist/theme.css',
				[],
				(string) filemtime( $css )
			);
		}

		$js = RT_THEME_DIR . 'assets/dist/theme.js';
		if ( is_readable( $js ) ) {
			wp_enqueue_script(
				'replanta-theme',
				RT_THEME_URL . 'assets/dist/theme.js',
				[],
				(string) filemtime( $js ),
				[ 'strategy' => 'defer', 'in_footer' => true ]
			);
		}

		// Migrated/custom CSS injected from imported sites.
		$custom = (string) get_option( RT_HTML_Importer::OPTION_CUSTOM_CSS, '' );
		if ( $custom !== '' ) {
			wp_register_style( 'replanta-custom', false, [], (string) get_option( 'rt_custom_css_ver', '1' ) );
			wp_enqueue_style( 'replanta-custom' );
			wp_add_inline_style( 'replanta-custom', $custom );
		}
	}
}
