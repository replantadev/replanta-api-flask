<?php
/**
 * Global performance tweaks for the public frontend.
 *
 * - Disable WP emoji output (saves ~7KB script + 1 request).
 * - Disable oEmbed JS and discovery on singular front pages.
 * - Remove jQuery Migrate on the public side.
 * - Force `display=swap` on Google Fonts URLs.
 * - Mark non-essential scripts as defer (anything not jQuery and not in admin).
 * - On Mirror posts, dequeue WP block library CSS (the mirrored CSS already
 *   provides full styling and we want to avoid double bytes).
 *
 * This class is intentionally conservative: nothing here changes admin or
 * REST behaviour; only the public render.
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class RT_Perf {

	public function register(): void {
		add_action( 'init', [ $this, 'remove_emoji' ] );
		add_action( 'wp_footer', [ $this, 'remove_emoji' ], 0 );
		add_action( 'init', [ $this, 'tweak_oembed' ], 11 );
		add_action( 'wp_default_scripts', [ $this, 'remove_jquery_migrate' ] );
		add_filter( 'style_loader_src', [ $this, 'google_fonts_display_swap' ], 10, 1 );
		add_filter( 'script_loader_tag', [ $this, 'defer_non_critical_scripts' ], 10, 3 );
		add_action( 'wp_enqueue_scripts', [ $this, 'dequeue_on_mirror' ], 100 );
		add_action( 'wp_head', [ $this, 'preconnect_origins' ], 1 );
	}

	public function remove_emoji(): void {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		add_filter( 'tiny_mce_plugins', static function ( $plugins ) {
			return is_array( $plugins ) ? array_diff( $plugins, [ 'wpemoji' ] ) : [];
		} );
		add_filter( 'emoji_svg_url', '__return_false' );
	}

	public function tweak_oembed(): void {
		// Front side only; keep the REST endpoint so editors can still embed.
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		add_filter( 'embed_oembed_html', static function ( $cache, $url, $attr, $post_id ) {
			unset( $url, $attr, $post_id );
			return $cache;
		}, 10, 4 );
	}

	public function remove_jquery_migrate( $scripts ): void {
		if ( is_admin() || empty( $scripts->registered['jquery'] ) ) {
			return;
		}
		$jq = $scripts->registered['jquery'];
		if ( ! empty( $jq->deps ) ) {
			$jq->deps = array_diff( $jq->deps, [ 'jquery-migrate' ] );
		}
	}

	public function google_fonts_display_swap( $src ) {
		$src = (string) $src;
		if ( $src === '' ) {
			return $src;
		}
		if ( strpos( $src, 'fonts.googleapis.com/css' ) === false ) {
			return $src;
		}
		if ( strpos( $src, 'display=' ) !== false ) {
			return $src;
		}
		return $src . ( strpos( $src, '?' ) === false ? '?' : '&' ) . 'display=swap';
	}

	public function defer_non_critical_scripts( string $tag, string $handle, string $src ): string {
		if ( is_admin() ) {
			return $tag;
		}
		// Skip already-async/defer or core handles we cannot safely defer.
		$skip = [ 'jquery-core', 'jquery', 'jquery-migrate', 'wp-i18n', 'wp-hooks', 'wp-polyfill' ];
		if ( in_array( $handle, $skip, true ) ) {
			return $tag;
		}
		if ( strpos( $tag, ' defer' ) !== false || strpos( $tag, ' async' ) !== false ) {
			return $tag;
		}
		if ( $src === '' ) {
			return $tag;
		}
		return str_replace( ' src=', ' defer src=', $tag );
	}

	public function dequeue_on_mirror(): void {
		if ( ! is_singular( [ RT_CPT_Page::POST_TYPE, 'page' ] ) ) {
			return;
		}
		$post = get_post();
		if ( ! $post || ! get_post_meta( $post->ID, RT_Mirror_Importer::META_MIRROR, true ) ) {
			return;
		}
		// The mirrored CSS already provides the full visual; WP block library
		// CSS would only add bytes that are already covered.
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'wp-block-library-theme' );
		wp_dequeue_style( 'classic-theme-styles' );
		wp_dequeue_style( 'global-styles' );
	}

	/**
	 * Emit DNS-prefetch + preconnect to common third-party origins. Cheap and
	 * usually a measurable LCP win when the page has external fonts/CDNs.
	 */
	public function preconnect_origins(): void {
		$origins = (array) apply_filters( 'rt_perf_preconnect_origins', [
			'https://fonts.googleapis.com',
			'https://fonts.gstatic.com',
		] );
		foreach ( $origins as $o ) {
			$href = esc_url( (string) $o );
			if ( $href === '' ) {
				continue;
			}
			echo "<link rel=\"preconnect\" href=\"{$href}\" crossorigin>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
			echo "<link rel=\"dns-prefetch\" href=\"{$href}\">\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}
}
