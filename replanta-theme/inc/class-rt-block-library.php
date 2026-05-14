<?php
/**
 * RT_Block_Library — reusable block library.
 *
 * Files live in `content/_library/{slug}.mdx`. The leading underscore prevents
 * the regular content-sync from publishing them as pages. Each library item is
 * a single MDX block (frontmatter `kind: library` + body).
 *
 * Reuse is done in MDX with the `<Include slug="..."/>` self-closing
 * component. The `RT_Component_Renderer` resolves it at runtime by reading the
 * library file and rendering its body inline.
 *
 * Two reuse modes are supported by the editor:
 *   - Copy: insert the raw body of the library item into the destination
 *           page (independent — edits do not propagate).
 *   - Reference: insert `<Include slug="X"/>` (synced — edits to the library
 *           item show up everywhere it is referenced).
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class RT_Block_Library {

	public const DIR = '_library';

	/** @return string Absolute path to the library directory (created on demand). */
	public function dir(): string {
		$base = trailingslashit( RT_THEME_DIR ) . 'content/' . self::DIR;
		if ( ! is_dir( $base ) ) wp_mkdir_p( $base );
		return $base;
	}

	/** @return array<int,array<string,mixed>> */
	public function list_items(): array {
		$dir = $this->dir();
		if ( ! is_dir( $dir ) ) return [];
		$out = [];
		foreach ( (array) glob( $dir . '/*.mdx' ) as $file ) {
			$slug   = pathinfo( (string) $file, PATHINFO_FILENAME );
			$raw    = (string) file_get_contents( (string) $file );
			$parsed = RT_MDX_Parser::split( $raw );
			$front  = $parsed['frontmatter'];
			$body   = $parsed['body'];
			$preview = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $body ) ) ?? '' );
			if ( strlen( $preview ) > 140 ) $preview = substr( $preview, 0, 137 ) . '…';
			$out[] = [
				'slug'        => $slug,
				'title'       => (string) ( $front['title'] ?? $slug ),
				'kind'        => (string) ( $front['component'] ?? $front['kind'] ?? 'block' ),
				'preview'     => $preview,
				'usage_count' => $this->usage_count( $slug ),
				'modified'    => (int) filemtime( (string) $file ),
				'bytes'       => (int) filesize( (string) $file ),
			];
		}
		usort( $out, static fn( $a, $b ) => ( $b['modified'] ?? 0 ) <=> ( $a['modified'] ?? 0 ) );
		return $out;
	}

	public function exists( string $slug ): bool {
		$slug = $this->normalize_slug( $slug );
		return $slug !== '' && is_file( $this->dir() . '/' . $slug . '.mdx' );
	}

	public function get( string $slug ): ?string {
		$slug = $this->normalize_slug( $slug );
		if ( $slug === '' ) return null;
		$file = $this->dir() . '/' . $slug . '.mdx';
		if ( ! is_file( $file ) ) return null;
		return (string) file_get_contents( $file );
	}

	/** @return array{ slug:string, body:string, frontmatter:array<string,mixed> } */
	public function get_parsed( string $slug ): ?array {
		$raw = $this->get( $slug );
		if ( $raw === null ) return null;
		$parsed = RT_MDX_Parser::split( $raw );
		return [
			'slug'        => $this->normalize_slug( $slug ),
			'body'        => $parsed['body'],
			'frontmatter' => $parsed['frontmatter'],
		];
	}

	/**
	 * Save (create or overwrite) a library item.
	 *
	 * @param string $slug
	 * @param string $title
	 * @param string $body  Raw MDX (a single block or several — used as-is).
	 * @return array{ ok:bool, slug:string, error?:string, path?:string }
	 */
	public function save( string $slug, string $title, string $body ): array {
		$slug = $this->normalize_slug( $slug );
		if ( $slug === '' ) return [ 'ok' => false, 'error' => 'invalid slug', 'slug' => '' ];
		$body = trim( $body );
		if ( $body === '' ) return [ 'ok' => false, 'error' => 'empty body', 'slug' => $slug ];

		$front = [
			'title' => $title !== '' ? $title : $slug,
			'slug'  => $slug,
			'kind'  => 'library',
		];
		$mdx = RT_MDX_Parser::compose( $front, $body );
		$file = $this->dir() . '/' . $slug . '.mdx';
		$ok   = file_put_contents( $file, $mdx ) !== false;
		return [ 'ok' => $ok, 'slug' => $slug, 'path' => $file ];
	}

	public function delete( string $slug ): bool {
		$slug = $this->normalize_slug( $slug );
		if ( $slug === '' ) return false;
		$file = $this->dir() . '/' . $slug . '.mdx';
		return is_file( $file ) ? unlink( $file ) : false;
	}

	/** Count how many `<Include slug="X"/>` references exist across content/. */
	public function usage_count( string $slug ): int {
		$slug = $this->normalize_slug( $slug );
		if ( $slug === '' ) return 0;
		$root = trailingslashit( RT_THEME_DIR ) . 'content';
		if ( ! is_dir( $root ) ) return 0;
		$count = 0;
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, RecursiveDirectoryIterator::SKIP_DOTS ) );
		$needle = 'slug="' . $slug . '"';
		foreach ( $it as $f ) {
			if ( ! $f->isFile() ) continue;
			$path = $f->getPathname();
			if ( ! preg_match( '/\.mdx?$/i', $path ) ) continue;
			// Skip the library file itself.
			if ( strpos( str_replace( '\\', '/', $path ), '/' . self::DIR . '/' ) !== false ) continue;
			$raw = (string) file_get_contents( $path );
			if ( strpos( $raw, '<Include' ) === false ) continue;
			$count += preg_match_all( '/<Include\b[^>]*\bslug="' . preg_quote( $slug, '/' ) . '"/i', $raw );
		}
		return $count;
	}

	public function normalize_slug( string $slug ): string {
		$slug = strtolower( trim( $slug ) );
		$slug = preg_replace( '/[^a-z0-9_-]+/', '-', $slug ) ?? '';
		$slug = trim( $slug, '-_' );
		return $slug;
	}
}
