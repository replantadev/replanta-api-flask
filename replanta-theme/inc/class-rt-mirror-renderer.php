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
		add_action( 'wp_head', [ $this, 'print_inline_css' ], 99 );
	}

	public function enqueue(): void {
		$post_id = $this->current_mirror_post();
		if ( ! $post_id ) {
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
