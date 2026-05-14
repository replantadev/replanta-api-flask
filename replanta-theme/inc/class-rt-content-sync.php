<?php
/**
 * Content sync — files in /content/{lang}/*.mdx <-> CPT rt_page.
 *
 * Strategy: file is the source of truth. CPT is a render cache + WP runtime.
 * Sync triggers: WP-CLI, REST endpoint, admin button.
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class RT_Content_Sync {

	public const META_PATH = '_rt_source_path';
	public const META_HASH = '_rt_source_hash';
	public const META_LANG = '_rt_source_lang';
	public const META_SOURCE_URL = '_rt_source_url';
	public const META_FRONTMATTER = '_rt_frontmatter';

	public function content_dir(): string {
		$dir = trailingslashit( RT_THEME_DIR ) . 'content';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		return $dir;
	}

	/**
	 * @return array{ scanned: int, created: int, updated: int, skipped: int, errors: array<int,string> }
	 */
	public function sync_all(): array {
		$result = [ 'scanned' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [] ];
		$root   = $this->content_dir();
		if ( ! is_dir( $root ) ) {
			return $result;
		}
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, RecursiveDirectoryIterator::SKIP_DOTS ) );
		foreach ( $it as $file ) {
			if ( ! $file->isFile() ) continue;
			$path = $file->getPathname();
			if ( ! preg_match( '/\.mdx?$/i', $path ) ) continue;
			// Skip README/LICENSE/CHANGELOG and any file at the content root (must live in a {lang}/ folder).
			$rel  = ltrim( str_replace( $root, '', $path ), '/\\' );
			$rel  = str_replace( '\\', '/', $rel );
			if ( strpos( $rel, '/' ) === false ) continue;
			// Skip the reusable block library — those are not pages.
			$first_seg = explode( '/', $rel, 2 )[0] ?? '';
			if ( $first_seg !== '' && $first_seg[0] === '_' ) continue;
			$basename = pathinfo( $path, PATHINFO_FILENAME );
			if ( preg_match( '/^(README|LICENSE|CHANGELOG|CONTRIBUTING|NOTICE)$/i', $basename ) ) continue;
			$result['scanned']++;
			try {
				$action = $this->sync_file( $path );
				$result[ $action ] = ( $result[ $action ] ?? 0 ) + 1;
			} catch ( \Throwable $e ) {
				$result['errors'][] = $path . ': ' . $e->getMessage();
			}
		}
		return $result;
	}

	/** @return 'created'|'updated'|'skipped' */
	public function sync_file( string $path ): string {
		$root = $this->content_dir();
		$rel  = ltrim( str_replace( $root, '', $path ), '/\\' );
		$rel  = str_replace( '\\', '/', $rel );

		$lang_segments = explode( '/', $rel );
		$lang = $lang_segments[0] ?? 'es';

		$raw  = (string) file_get_contents( $path );
		$hash = md5( $raw );

		$existing = $this->find_post_by_path( $rel );
		if ( $existing && get_post_meta( $existing->ID, self::META_HASH, true ) === $hash ) {
			return 'skipped';
		}

		$parsed = RT_MDX_Parser::split( $raw );
		$front  = $parsed['frontmatter'];
		$body   = $parsed['body'];
		$blocks = RT_MDX_Parser::body_to_blocks( $body );

		$slug = (string) ( $front['slug'] ?? pathinfo( $rel, PATHINFO_FILENAME ) );
		$slug = sanitize_title( str_replace( '/', '-', $slug ) );
		$title = (string) ( $front['title'] ?? ucfirst( pathinfo( $rel, PATHINFO_FILENAME ) ) );

		$postarr = [
			'post_type'    => RT_CPT_Page::POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => $blocks,
			'meta_input'   => [
				self::META_PATH        => $rel,
				self::META_HASH        => $hash,
				self::META_LANG        => $lang,
				self::META_SOURCE_URL  => (string) ( $front['source_url'] ?? '' ),
				self::META_FRONTMATTER => wp_json_encode( $front ),
			],
		];

		if ( $existing ) {
			$postarr['ID'] = $existing->ID;
			wp_update_post( $postarr );
			$action = 'updated';
		} else {
			$id = wp_insert_post( $postarr );
			if ( is_wp_error( $id ) ) {
				throw new \RuntimeException( $id->get_error_message() );
			}
			$action = 'created';
		}

		// SEO + Polylang hooks.
		do_action( 'replanta/page_synced', $existing ? $existing->ID : (int) ( $postarr['ID'] ?? 0 ), $front, $lang );

		return $action;
	}

	public function write_file( string $rel_path, array $frontmatter, string $body ): string {
		$root = $this->content_dir();
		$abs  = $root . '/' . ltrim( $rel_path, '/\\' );
		$dir  = dirname( $abs );
		if ( ! is_dir( $dir ) ) wp_mkdir_p( $dir );
		$mdx = RT_MDX_Parser::compose( $frontmatter, $body );
		file_put_contents( $abs, $mdx );
		$this->sync_file( $abs );
		return $abs;
	}

	public function delete_file( string $rel_path ): bool {
		$root = $this->content_dir();
		$abs  = $root . '/' . ltrim( $rel_path, '/\\' );
		if ( ! is_file( $abs ) ) return false;
		$post = $this->find_post_by_path( $rel_path );
		if ( $post ) wp_delete_post( $post->ID, true );
		return unlink( $abs );
	}

	public function find_post_by_path( string $rel ): ?\WP_Post {
		$q = new \WP_Query(
			[
				'post_type'      => RT_CPT_Page::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_key'       => self::META_PATH,
				'meta_value'     => $rel,
				'no_found_rows'  => true,
			]
		);
		return $q->posts[0] ?? null;
	}

	/** @return array<int, array<string,mixed>> */
	public function list_files(): array {
		$out  = [];
		$root = $this->content_dir();
		if ( ! is_dir( $root ) ) return $out;
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, RecursiveDirectoryIterator::SKIP_DOTS ) );
		foreach ( $it as $f ) {
			if ( ! $f->isFile() || ! preg_match( '/\.mdx?$/i', $f->getPathname() ) ) continue;
			$rel = str_replace( '\\', '/', ltrim( str_replace( $root, '', $f->getPathname() ), '/\\' ) );
			$raw = (string) file_get_contents( $f->getPathname() );
			$front = RT_MDX_Parser::split( $raw )['frontmatter'];
			$out[] = [
				'path'  => $rel,
				'lang'  => explode( '/', $rel )[0] ?? 'es',
				'title' => $front['title'] ?? pathinfo( $rel, PATHINFO_FILENAME ),
				'slug'  => $front['slug'] ?? null,
				'mtime' => $f->getMTime(),
			];
		}
		usort( $out, static fn( $a, $b ) => strcmp( $a['path'], $b['path'] ) );
		return $out;
	}

	public function read_file( string $rel ): ?array {
		$abs = $this->content_dir() . '/' . ltrim( $rel, '/\\' );
		if ( ! is_file( $abs ) ) return null;
		$raw = (string) file_get_contents( $abs );
		$split = RT_MDX_Parser::split( $raw );
		return [
			'path'        => $rel,
			'raw'         => $raw,
			'frontmatter' => $split['frontmatter'],
			'body'        => $split['body'],
		];
	}
}
