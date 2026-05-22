<?php
/**
 * Frontend renderer for Mirror-imported pages.
 *
 * When a singular rt_page (or its promoted page heir) has the mirror metadata,
 * we enqueue the locally-saved CSS files and inject the inlined head <style>
 * captured at import time. The body HTML is already in post_content as a
 * core/html block, so WordPress will render it untouched.
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class RT_Mirror_Renderer {

	public function register(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ], 99 );
		add_action( 'wp_head', [ $this, 'print_inline_css' ], 1 );
		add_action( 'wp_head', [ $this, 'print_font_preloads' ], 2 );
		add_action( 'wp_head', [ $this, 'print_deferred_css' ], 3 );
		add_action( 'wp_head', [ $this, 'print_layout_overrides' ], 4 );
		add_filter( 'style_loader_tag', [ $this, 'maybe_defer_style' ], 10, 4 );
		add_action( 'send_headers', [ $this, 'send_link_headers' ] );
	}

	public function enqueue(): void {
		$post_id = $this->current_mirror_post();
		if ( ! $post_id ) {
			return;
		}
		// Prefer a single bundled stylesheet when available.
		$bundle = (string) get_post_meta( $post_id, RT_Mirror_Importer::META_CSS_BUNDLE, true );
		if ( $bundle !== '' ) {
			wp_enqueue_style(
				'rt-mirror-bundle-' . $post_id,
				$bundle,
				[],
				(string) get_post_meta( $post_id, RT_Mirror_Importer::META_IMPORTED_AT, true )
			);
			return;
		}
		$files = (array) get_post_meta( $post_id, RT_Mirror_Importer::META_CSS_FILES, true );
		$i     = 0;
		foreach ( $files as $url ) {
			$url = (string) $url;
			if ( $url === '' ) {
				continue;
			}
			$i++;
			wp_enqueue_style(
				'rt-mirror-' . $post_id . '-' . $i,
				$url,
				[],
				(string) get_post_meta( $post_id, RT_Mirror_Importer::META_IMPORTED_AT, true )
			);
		}
	}

	public function print_inline_css(): void {
		$post_id = $this->current_mirror_post();
		if ( ! $post_id ) {
			return;
		}
		$inline = (string) get_post_meta( $post_id, RT_Mirror_Importer::META_INLINE_CSS, true );
		if ( $inline === '' ) {
			return;
		}
		echo "<style id=\"rt-mirror-inline-{$post_id}\">\n" . $inline . "\n</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public function print_font_preloads(): void {
		$post_id = $this->current_mirror_post();
		if ( ! $post_id ) {
			return;
		}
		$fonts = (array) get_post_meta( $post_id, RT_Mirror_Importer::META_FONTS, true );
		foreach ( $fonts as $f ) {
			if ( ! is_array( $f ) || empty( $f['href'] ) ) {
				continue;
			}
			$href  = esc_url( (string) $f['href'] );
			$type  = esc_attr( (string) ( $f['type'] ?? 'font/woff2' ) );
			$cross = esc_attr( (string) ( $f['crossorigin'] ?? 'anonymous' ) );
			echo "<link rel=\"preload\" as=\"font\" href=\"{$href}\" type=\"{$type}\" crossorigin=\"{$cross}\">\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	/**
	 * Emit a noscript fallback so users with JS disabled still see styles.
	 */
	public function print_deferred_css(): void {
		$post_id = $this->current_mirror_post();
		if ( ! $post_id ) {
			return;
		}
		$bundle = (string) get_post_meta( $post_id, RT_Mirror_Importer::META_CSS_BUNDLE, true );
		if ( $bundle === '' ) {
			return;
		}
		$href = esc_url( $bundle );
		echo "<noscript><link rel=\"stylesheet\" href=\"{$href}\"></noscript>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/**
	 * Mirror/adopted pages should render raw imported HTML without constrained
	 * theme wrappers changing widths/spacings.
	 */
	public function print_layout_overrides(): void {
		$post_id = $this->current_mirror_post();
		if ( ! $post_id ) {
			return;
		}
		echo "<style id=\"rt-mirror-layout-{$post_id}\">\n"
			. "main.wp-block-group{max-width:none!important;padding-left:0!important;padding-right:0!important;}\n"
			. ".wp-block-post-content{max-width:none!important;margin:0!important;}\n"
			. "main.wp-block-group > .wp-block-post-title,main.wp-block-group > .wp-block-post-featured-image{display:none!important;}\n"
			. "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/**
	 * Defer the Mirror bundle stylesheet so it does not block the LCP. The
	 * inline critical CSS already covers above-the-fold rendering.
	 */
	public function maybe_defer_style( string $tag, string $handle, string $href, string $media ): string {
		if ( strpos( $handle, 'rt-mirror-bundle-' ) !== 0 ) {
			return $tag;
		}
		// Use the rel=preload + onload swap pattern.
		$safe = esc_url( $href );
		return '<link rel="preload" as="style" href="' . $safe . '" onload="this.onload=null;this.rel=\'stylesheet\'">';
	}

	public function send_link_headers(): void {
		$post_id = $this->current_mirror_post();
		if ( ! $post_id || headers_sent() ) {
			return;
		}
		$bundle = (string) get_post_meta( $post_id, RT_Mirror_Importer::META_CSS_BUNDLE, true );
		if ( $bundle !== '' ) {
			header( 'Link: <' . esc_url_raw( $bundle ) . '>; rel=preload; as=style', false );
		}
		$fonts = (array) get_post_meta( $post_id, RT_Mirror_Importer::META_FONTS, true );
		$emitted = 0;
		foreach ( $fonts as $f ) {
			if ( ! is_array( $f ) || empty( $f['href'] ) || $emitted >= 4 ) {
				continue;
			}
			header( 'Link: <' . esc_url_raw( (string) $f['href'] ) . '>; rel=preload; as=font; crossorigin', false );
			$emitted++;
		}
	}

	private function current_mirror_post(): int {
		if ( ! is_singular( [ RT_CPT_Page::POST_TYPE, 'page' ] ) ) {
			return 0;
		}
		$post = get_post();
		if ( ! $post ) {
			return 0;
		}
		if ( ! get_post_meta( $post->ID, RT_Mirror_Importer::META_MIRROR, true ) ) {
			return 0;
		}
		return (int) $post->ID;
	}
}
