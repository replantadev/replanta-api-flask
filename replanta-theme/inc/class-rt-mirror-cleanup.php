<?php
/**
 * Filesystem cleanup for Mirror imports.
 *
 * - On post delete (rt_page or page with mirror metas), remove the slug
 *   subfolder under uploads/replanta-imports/.
 * - Public helper `purge_slug()` used by undo_adopt and CLI.
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class RT_Mirror_Cleanup {

	public function register(): void {
		add_action( 'before_delete_post', [ $this, 'on_delete' ], 10, 2 );
	}

	public function on_delete( int $post_id, $post ): void {
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		if ( ! in_array( $post->post_type, [ 'page', RT_CPT_Page::POST_TYPE ], true ) ) {
			return;
		}
		if ( (string) get_post_meta( $post_id, RT_Mirror_Importer::META_MIRROR, true ) === '' ) {
			return;
		}
		$slug = (string) $post->post_name;
		if ( $slug === '' ) {
			return;
		}
		self::purge_slug( $slug );
	}

	/**
	 * Recursively delete uploads/replanta-imports/<slug>/.
	 */
	public static function purge_slug( string $slug ): bool {
		$slug = sanitize_title( $slug );
		if ( $slug === '' || $slug === '..' ) {
			return false;
		}
		$uploads = wp_upload_dir();
		$root    = trailingslashit( $uploads['basedir'] ) . RT_Mirror_Importer::UPLOAD_DIR;
		$target  = $root . '/' . $slug;
		$real_root = realpath( $root );
		$real_tgt  = realpath( $target );
		if ( $real_root === false || $real_tgt === false ) {
			return false;
		}
		// Hard guard: target must be inside root.
		if ( strpos( $real_tgt, $real_root . DIRECTORY_SEPARATOR ) !== 0 ) {
			return false;
		}
		return self::rrmdir( $real_tgt );
	}

	private static function rrmdir( string $dir ): bool {
		if ( ! is_dir( $dir ) ) {
			return false;
		}
		$entries = scandir( $dir );
		if ( $entries === false ) {
			return false;
		}
		foreach ( $entries as $e ) {
			if ( $e === '.' || $e === '..' ) {
				continue;
			}
			$path = $dir . DIRECTORY_SEPARATOR . $e;
			if ( is_dir( $path ) && ! is_link( $path ) ) {
				self::rrmdir( $path );
			} else {
				@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
		return @rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}
}
