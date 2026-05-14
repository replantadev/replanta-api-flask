<?php
/**
 * HTML Importer — converts existing static HTML pages into MDX content.
 *
 * Use case: user has a cloned site (HTML + Elementor custom CSS) and wants
 * to migrate it onto Replanta Theme. We parse each HTML file with DOMDocument,
 * pull <title>/<meta description>, segment <body> by H1/H2 sections, and
 * emit an MDX file per page with stable component blocks. Optionally the
 * supplied custom CSS is preserved as theme-wide custom CSS.
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class RT_HTML_Importer {

	public const OPTION_CUSTOM_CSS = 'rt_custom_css';

	/**
	 * Returns the import directory under uploads (created if missing).
	 */
	public function import_dir(): string {
		$uploads = wp_upload_dir();
		$dir = trailingslashit( $uploads['basedir'] ) . 'replanta-import';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		return $dir;
	}

	/**
	 * @return array<int,array{path:string,name:string,size:int}>
	 */
	public function list_sources(): array {
		$dir = $this->import_dir();
		$out = [];
		if ( ! is_dir( $dir ) ) {
			return $out;
		}
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ) );
		foreach ( $it as $f ) {
			if ( ! $f->isFile() ) {
				continue;
			}
			$ext = strtolower( pathinfo( $f->getPathname(), PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, [ 'html', 'htm', 'css' ], true ) ) {
				continue;
			}
			$out[] = [
				'path' => str_replace( $dir . DIRECTORY_SEPARATOR, '', $f->getPathname() ),
				'name' => $f->getFilename(),
				'size' => (int) $f->getSize(),
				'ext'  => $ext,
			];
		}
		usort( $out, static fn( $a, $b ) => strcmp( $a['path'], $b['path'] ) );
		return $out;
	}

	/**
	 * Imports all HTML files under the import dir into /content/{lang}/imported/.
	 *
	 * @param array{lang?:string,merge_css?:bool,specific?:array<int,string>} $opts
	 * @return array{ok:bool,imported:array<int,string>,skipped:array<int,string>,css_saved:bool,errors:array<int,string>}
	 */
	public function import_all( array $opts = [] ): array {
		$lang      = (string) ( $opts['lang'] ?? 'es' );
		$merge_css = (bool) ( $opts['merge_css'] ?? true );
		$only      = $opts['specific'] ?? null;

		$dir       = $this->import_dir();
		$dest_dir  = trailingslashit( RT_THEME_DIR ) . 'content/' . $lang . '/imported';
		if ( ! is_dir( $dest_dir ) ) {
			wp_mkdir_p( $dest_dir );
		}

		$imported = [];
		$skipped  = [];
		$errors   = [];
		$css_blob = '';

		$sources = $this->list_sources();
		foreach ( $sources as $src ) {
			if ( is_array( $only ) && ! in_array( $src['path'], $only, true ) ) {
				$skipped[] = $src['path'];
				continue;
			}
			$abs = trailingslashit( $dir ) . $src['path'];
			if ( $src['ext'] === 'css' ) {
				if ( $merge_css ) {
					$css_blob .= "\n\n/* === " . $src['path'] . " === */\n" . (string) file_get_contents( $abs );
				}
				continue;
			}
			try {
				$mdx = $this->html_file_to_mdx( $abs, $lang );
				$slug = sanitize_title( pathinfo( $src['path'], PATHINFO_FILENAME ) );
				if ( $slug === '' || $slug === 'index' ) {
					$slug = 'home';
				}
				$out = $dest_dir . '/' . $slug . '.mdx';
				file_put_contents( $out, $mdx );
				$imported[] = 'imported/' . basename( $out );
			} catch ( \Throwable $e ) {
				$errors[] = $src['path'] . ': ' . $e->getMessage();
			}
		}

		$css_saved = false;
		if ( $merge_css && $css_blob !== '' ) {
			$existing = (string) get_option( self::OPTION_CUSTOM_CSS, '' );
			update_option( self::OPTION_CUSTOM_CSS, $existing . $css_blob, false );
			$css_saved = true;
		}

		// Trigger a sync so the new files become CPT entries.
		( new RT_Content_Sync() )->sync_all();

		return [
			'ok'        => true,
			'imported'  => $imported,
			'skipped'   => $skipped,
			'css_saved' => $css_saved,
			'errors'    => $errors,
		];
	}

	/**
	 * Imports raw HTML (and optional CSS) directly from request payload.
	 *
	 * @param array{html:string,slug?:string,lang?:string,custom_css?:string,title?:string} $payload
	 * @return array{ok:bool,file:string,css_saved:bool}
	 */
	public function import_raw( array $payload ): array {
		$lang = (string) ( $payload['lang'] ?? 'es' );
		$slug = sanitize_title( (string) ( $payload['slug'] ?? 'imported-page' ) );
		if ( $slug === '' ) {
			$slug = 'imported-page';
		}
		$dest_dir = trailingslashit( RT_THEME_DIR ) . 'content/' . $lang . '/imported';
		if ( ! is_dir( $dest_dir ) ) {
			wp_mkdir_p( $dest_dir );
		}
		$mdx = $this->html_to_mdx( (string) ( $payload['html'] ?? '' ), $lang, [
			'title' => (string) ( $payload['title'] ?? '' ),
		] );
		$file = $dest_dir . '/' . $slug . '.mdx';
		file_put_contents( $file, $mdx );

		$css_saved = false;
		if ( ! empty( $payload['custom_css'] ) ) {
			$existing = (string) get_option( self::OPTION_CUSTOM_CSS, '' );
			update_option( self::OPTION_CUSTOM_CSS, $existing . "\n\n" . (string) $payload['custom_css'], false );
			$css_saved = true;
		}

		( new RT_Content_Sync() )->sync_all();

		return [ 'ok' => true, 'file' => 'imported/' . basename( $file ), 'css_saved' => $css_saved ];
	}

	/**
	 * Fetches a remote URL and converts to MDX.
	 *
	 * @return array{ok:bool,file?:string,error?:string,title?:string}
	 */
	public function import_url( string $url, string $lang = 'es', ?string $slug = null, bool $download_images = true ): array {
		$url = esc_url_raw( $url );
		if ( $url === '' ) {
			return [ 'ok' => false, 'error' => 'invalid url' ];
		}
		$resp = wp_remote_get( $url, [ 'timeout' => 30, 'redirection' => 5, 'user-agent' => 'ReplantaImporter/1.0' ] );
		if ( is_wp_error( $resp ) ) {
			return [ 'ok' => false, 'error' => $resp->get_error_message() ];
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		if ( $code < 200 || $code >= 400 ) {
			return [ 'ok' => false, 'error' => 'http ' . $code ];
		}
		$html = (string) wp_remote_retrieve_body( $resp );
		if ( $download_images ) {
			$html = $this->rewrite_images_to_media( $html, $url );
		}
		$slug = $slug ? sanitize_title( $slug ) : sanitize_title( $this->slug_from_url( $url ) );
		if ( $slug === '' ) {
			$slug = 'imported-' . wp_generate_password( 6, false );
		}
		$dest_dir = trailingslashit( RT_THEME_DIR ) . 'content/' . $lang . '/imported';
		if ( ! is_dir( $dest_dir ) ) {
			wp_mkdir_p( $dest_dir );
		}
		$mdx  = $this->html_to_mdx( $html, $lang, [ 'slug' => $slug, 'source_url' => $url ] );
		$file = $dest_dir . '/' . $slug . '.mdx';
		file_put_contents( $file, $mdx );
		( new RT_Content_Sync() )->sync_all();
		return [ 'ok' => true, 'file' => 'imported/' . basename( $file ), 'url' => $url ];
	}

	/**
	 * Reads a sitemap.xml URL and imports each <loc>.
	 *
	 * @return array{ok:bool,total:int,imported:array<int,string>,errors:array<int,string>}
	 */
	public function import_sitemap( string $sitemap_url, string $lang = 'es', int $limit = 50, bool $download_images = true ): array {
		$resp = wp_remote_get( $sitemap_url, [ 'timeout' => 20, 'redirection' => 5 ] );
		if ( is_wp_error( $resp ) ) {
			return [ 'ok' => false, 'total' => 0, 'imported' => [], 'errors' => [ $resp->get_error_message() ] ];
		}
		$xml_str = (string) wp_remote_retrieve_body( $resp );
		libxml_use_internal_errors( true );
		$xml = simplexml_load_string( $xml_str );
		libxml_clear_errors();
		if ( ! $xml ) {
			return [ 'ok' => false, 'total' => 0, 'imported' => [], 'errors' => [ 'invalid sitemap xml' ] ];
		}
		$urls = [];
		// Sitemap index.
		if ( isset( $xml->sitemap ) ) {
			foreach ( $xml->sitemap as $s ) {
				$child = $this->import_sitemap( (string) $s->loc, $lang, $limit, $download_images );
				$urls = array_merge( $urls, $child['imported'] ?? [] );
			}
			return [ 'ok' => true, 'total' => count( $urls ), 'imported' => $urls, 'errors' => [] ];
		}
		// urlset.
		$locs = [];
		foreach ( $xml->url as $u ) {
			$locs[] = (string) $u->loc;
		}
		$locs = array_slice( $locs, 0, $limit );

		$imported = [];
		$errors   = [];
		foreach ( $locs as $loc ) {
			$res = $this->import_url( $loc, $lang, null, $download_images );
			if ( $res['ok'] ) {
				$imported[] = $res['file'];
			} else {
				$errors[] = $loc . ': ' . ( $res['error'] ?? '?' );
			}
		}
		return [ 'ok' => true, 'total' => count( $locs ), 'imported' => $imported, 'errors' => $errors ];
	}

	/**
	 * Discovers sitemaps for a domain. Accepts either a homepage URL or a sitemap URL.
	 * Detects Yoast (sitemap_index.xml), Rank Math (sitemap_index.xml), WP Core (wp-sitemap.xml),
	 * AIOSEO (sitemap.xml), and BetterDocs (docs-sitemap / betterdocs).
	 * Returns a flat list of leaf sitemaps with metadata: url, label, kind, engine, count.
	 *
	 * @return array{ok:bool,engine:string,index_url:string,sitemaps:array<int,array<string,mixed>>,errors:array<int,string>}
	 */
	public function discover_sitemaps( string $url ): array {
		$url = trim( $url );
		if ( $url === '' ) {
			return [ 'ok' => false, 'engine' => '', 'index_url' => '', 'sitemaps' => [], 'errors' => [ 'empty url' ] ];
		}
		// If user passed a homepage, probe well-known locations in priority order.
		$origin       = $this->origin_of( $url );
		$is_xml_path  = (bool) preg_match( '/\.xml(\?.*)?$/i', $url );
		$candidates   = [];
		if ( $is_xml_path ) {
			$candidates[] = $url;
		}
		// Probe in priority order; first valid wins.
		$candidates[] = $origin . '/sitemap_index.xml'; // Yoast / Rank Math
		$candidates[] = $origin . '/sitemap.xml';        // AIOSEO / generic
		$candidates[] = $origin . '/wp-sitemap.xml';     // WP core 5.5+
		$candidates   = array_values( array_unique( $candidates ) );

		$picked    = '';
		$picked_xml = null;
		$engine    = 'unknown';
		$errors    = [];
		foreach ( $candidates as $cand ) {
			$resp = wp_remote_get( $cand, [ 'timeout' => 15, 'redirection' => 5 ] );
			if ( is_wp_error( $resp ) ) {
				$errors[] = $cand . ': ' . $resp->get_error_message();
				continue;
			}
			$code = (int) wp_remote_retrieve_response_code( $resp );
			if ( $code !== 200 ) {
				$errors[] = $cand . ': HTTP ' . $code;
				continue;
			}
			$body = (string) wp_remote_retrieve_body( $resp );
			libxml_use_internal_errors( true );
			$xml = simplexml_load_string( $body );
			libxml_clear_errors();
			if ( ! $xml ) {
				$errors[] = $cand . ': invalid xml';
				continue;
			}
			$picked     = $cand;
			$picked_xml = $xml;
			$engine     = $this->detect_engine( $body, $cand );
			break;
		}
		if ( $picked_xml === null ) {
			return [ 'ok' => false, 'engine' => '', 'index_url' => '', 'sitemaps' => [], 'errors' => $errors ?: [ 'no sitemap found' ] ];
		}

		$leaves = [];
		// If sitemap index, expand one level. Otherwise treat as a single leaf.
		if ( isset( $picked_xml->sitemap ) && count( $picked_xml->sitemap ) > 0 ) {
			foreach ( $picked_xml->sitemap as $s ) {
				$child_url = (string) $s->loc;
				$leaves[]  = $this->describe_sitemap( $child_url, $engine );
			}
		} else {
			$leaves[] = $this->describe_sitemap( $picked, $engine, $picked_xml );
		}

		// Sort: pages first, then posts, docs, products, others; alpha within group.
		$order = [ 'page' => 0, 'post' => 1, 'doc' => 2, 'product' => 3, 'category' => 4, 'tag' => 5, 'author' => 6, 'media' => 7, 'other' => 9 ];
		usort( $leaves, static function ( array $a, array $b ) use ( $order ): int {
			$oa = $order[ $a['kind'] ] ?? 8;
			$ob = $order[ $b['kind'] ] ?? 8;
			if ( $oa !== $ob ) {
				return $oa <=> $ob;
			}
			return strcmp( $a['label'], $b['label'] );
		} );

		return [
			'ok'        => true,
			'engine'    => $engine,
			'index_url' => $picked,
			'sitemaps'  => $leaves,
			'errors'    => $errors,
		];
	}

	private function origin_of( string $url ): string {
		$p = wp_parse_url( $url );
		if ( ! $p || empty( $p['host'] ) ) {
			return rtrim( $url, '/' );
		}
		$scheme = $p['scheme'] ?? 'https';
		$port   = isset( $p['port'] ) ? ':' . $p['port'] : '';
		return $scheme . '://' . $p['host'] . $port;
	}

	private function detect_engine( string $xml_body, string $url ): string {
		$head = substr( $xml_body, 0, 800 );
		if ( stripos( $head, 'yoast.com' ) !== false || stripos( $head, 'XSL Stylesheet by Yoast' ) !== false ) {
			return 'yoast';
		}
		if ( stripos( $head, 'rankmath' ) !== false ) {
			return 'rankmath';
		}
		if ( stripos( $head, 'aioseo' ) !== false || stripos( $head, 'all in one seo' ) !== false ) {
			return 'aioseo';
		}
		if ( stripos( $url, '/wp-sitemap' ) !== false ) {
			return 'wp-core';
		}
		if ( stripos( $url, 'sitemap_index' ) !== false ) {
			return 'yoast'; // most common case
		}
		return 'generic';
	}

	private function describe_sitemap( string $sm_url, string $engine, ?\SimpleXMLElement $xml = null ): array {
		$basename = strtolower( basename( wp_parse_url( $sm_url, PHP_URL_PATH ) ?: $sm_url ) );
		$kind     = 'other';
		$label    = $basename;

		// Normalize common patterns: page-sitemap.xml, post-sitemap.xml, docs-sitemap.xml,
		// wp-sitemap-posts-page-1.xml, wp-sitemap-posts-post-1.xml, wp-sitemap-posts-docs-1.xml…
		$lc = $basename;
		if ( strpos( $lc, 'page' ) !== false )                   { $kind = 'page';     $label = __( 'Páginas', 'replanta-theme' ); }
		elseif ( strpos( $lc, 'post' ) !== false && strpos( $lc, 'wp-sitemap-posts-' ) === false ) { $kind = 'post'; $label = __( 'Entradas', 'replanta-theme' ); }
		elseif ( strpos( $lc, 'docs' ) !== false || strpos( $lc, 'betterdocs' ) !== false || strpos( $lc, 'doc-' ) !== false ) { $kind = 'doc'; $label = __( 'BetterDocs / Docs', 'replanta-theme' ); }
		elseif ( strpos( $lc, 'product' ) !== false )            { $kind = 'product';  $label = __( 'Productos', 'replanta-theme' ); }
		elseif ( strpos( $lc, 'category' ) !== false || strpos( $lc, 'cat-' ) !== false ) { $kind = 'category'; $label = __( 'Categorías', 'replanta-theme' ); }
		elseif ( strpos( $lc, 'post_tag' ) !== false || strpos( $lc, 'tag-' ) !== false ) { $kind = 'tag'; $label = __( 'Etiquetas', 'replanta-theme' ); }
		elseif ( strpos( $lc, 'author' ) !== false )             { $kind = 'author';   $label = __( 'Autores', 'replanta-theme' ); }
		elseif ( strpos( $lc, 'attachment' ) !== false || strpos( $lc, 'media' ) !== false || strpos( $lc, 'image' ) !== false ) { $kind = 'media'; $label = __( 'Multimedia', 'replanta-theme' ); }

		// WP core slug: wp-sitemap-posts-{type}-1.xml
		if ( preg_match( '/wp-sitemap-posts-([a-z0-9_-]+)-\d+/', $lc, $m ) ) {
			$slug = $m[1];
			if ( $slug === 'page' ) { $kind = 'page';   $label = __( 'Páginas', 'replanta-theme' ); }
			elseif ( $slug === 'post' ) { $kind = 'post';   $label = __( 'Entradas', 'replanta-theme' ); }
			elseif ( $slug === 'docs' ) { $kind = 'doc';    $label = __( 'BetterDocs / Docs', 'replanta-theme' ); }
			elseif ( $slug === 'product' ) { $kind = 'product'; $label = __( 'Productos', 'replanta-theme' ); }
			else { $kind = 'other'; $label = ucfirst( $slug ); }
		}

		$count = $this->count_locs( $sm_url, $xml );

		return [
			'url'    => $sm_url,
			'label'  => $label,
			'kind'   => $kind,
			'engine' => $engine,
			'count'  => $count,
		];
	}

	private function count_locs( string $sm_url, ?\SimpleXMLElement $xml = null ): int {
		if ( $xml === null ) {
			$resp = wp_remote_get( $sm_url, [ 'timeout' => 12, 'redirection' => 5 ] );
			if ( is_wp_error( $resp ) || (int) wp_remote_retrieve_response_code( $resp ) !== 200 ) {
				return -1;
			}
			$body = (string) wp_remote_retrieve_body( $resp );
			libxml_use_internal_errors( true );
			$xml = simplexml_load_string( $body );
			libxml_clear_errors();
			if ( ! $xml ) {
				return -1;
			}
		}
		if ( isset( $xml->url ) ) {
			return (int) count( $xml->url );
		}
		if ( isset( $xml->sitemap ) ) {
			return (int) count( $xml->sitemap ); // index → number of child sitemaps
		}
		return 0;
	}

	/**
	 * Downloads remote images referenced in HTML to the Media Library
	 * and rewrites the src attributes to local URLs.
	 */
	public function rewrite_images_to_media( string $html, string $base_url ): string {
		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		return (string) preg_replace_callback(
			'/<img\b[^>]*\bsrc=([\'"])([^\'"]+)\1/i',
			function ( array $m ) use ( $base_url ): string {
				$src = $this->absolute_url( $m[2], $base_url );
				if ( strpos( $src, home_url() ) === 0 ) {
					return $m[0]; // already local
				}
				$id = media_sideload_image( $src, 0, null, 'id' );
				if ( is_wp_error( $id ) ) {
					return $m[0];
				}
				$local = wp_get_attachment_url( (int) $id );
				return $local ? str_replace( $m[2], $local, $m[0] ) : $m[0];
			},
			$html
		);
	}

	/**
	 * AI-powered rewrite of an existing imported MDX file.
	 * Reads file, sends to provider with rewrite instruction, writes back.
	 *
	 * @return array{ok:bool,path?:string,error?:string,before?:string,after?:string}
	 */
	public function ai_rewrite_file( string $rel_path, string $instruction = '' ): array {
		$abs = trailingslashit( RT_THEME_DIR ) . 'content/' . ltrim( $rel_path, '/' );
		if ( ! is_file( $abs ) ) {
			return [ 'ok' => false, 'error' => 'file not found' ];
		}
		$before = (string) file_get_contents( $abs );
		$instr  = $instruction !== '' ? $instruction : 'Improve copy clarity, structure with proper components (Hero/Features/CTA/FAQ when applicable), trim redundancy, keep meaning and language.';

		$gen   = new RT_Page_Generator();
		$lang  = $this->guess_lang_from_path( $rel_path );
		$res   = $gen->rewrite_full_mdx( $before, $instr, $lang );
		if ( ! $res['ok'] ) {
			return [ 'ok' => false, 'error' => $res['error'] ?? 'AI error', 'before' => $before ];
		}
		file_put_contents( $abs, $res['mdx'] );
		( new RT_Content_Sync() )->sync_file( $abs );
		return [ 'ok' => true, 'path' => $rel_path, 'before' => $before, 'after' => $res['mdx'] ];
	}

	/**
	 * Returns side-by-side HTML diff for the imported file vs current Replanta render.
	 *
	 * @return array{ok:bool,original_html:string,replanta_html:string,error?:string}
	 */
	public function diff_render( string $rel_path ): array {
		$abs = trailingslashit( RT_THEME_DIR ) . 'content/' . ltrim( $rel_path, '/' );
		if ( ! is_file( $abs ) ) {
			return [ 'ok' => false, 'original_html' => '', 'replanta_html' => '', 'error' => 'not found' ];
		}
		$mdx = (string) file_get_contents( $abs );
		$split = RT_MDX_Parser::split( $mdx );
		$blocks = RT_MDX_Parser::body_to_blocks( $split['body'] );
		$rendered = function_exists( 'do_blocks' ) ? do_blocks( $blocks ) : $blocks;
		// Apply the_content so RT_Component_Renderer (prio 9) and do_shortcode (prio 11) run.
		if ( function_exists( 'apply_filters' ) ) {
			$rendered = (string) apply_filters( 'the_content', $rendered );
		}

		$origin = (string) ( $split['frontmatter']['source_url'] ?? '' );
		$origin_html = '';
		if ( $origin !== '' ) {
			$resp = wp_remote_get( $origin, [ 'timeout' => 15 ] );
			if ( ! is_wp_error( $resp ) ) {
				$origin_html = (string) wp_remote_retrieve_body( $resp );
			}
		}
		return [ 'ok' => true, 'original_html' => $origin_html, 'replanta_html' => $rendered ];
	}

	private function slug_from_url( string $url ): string {
		$path = (string) parse_url( $url, PHP_URL_PATH );
		$path = trim( $path, '/' );
		if ( $path === '' ) {
			return 'home';
		}
		$last = basename( $path );
		$last = preg_replace( '/\.[a-z]+$/i', '', $last ) ?: $last;
		return $last;
	}

	private function absolute_url( string $maybe_relative, string $base ): string {
		if ( preg_match( '#^https?://#i', $maybe_relative ) ) {
			return $maybe_relative;
		}
		$parts = wp_parse_url( $base );
		$origin = ( $parts['scheme'] ?? 'https' ) . '://' . ( $parts['host'] ?? '' );
		if ( strpos( $maybe_relative, '/' ) === 0 ) {
			return $origin . $maybe_relative;
		}
		$dir = rtrim( dirname( (string) ( $parts['path'] ?? '/' ) ), '/' );
		return $origin . $dir . '/' . $maybe_relative;
	}

	private function guess_lang_from_path( string $rel_path ): string {
		$rel = ltrim( str_replace( '\\', '/', $rel_path ), '/' );
		$first = explode( '/', $rel )[0] ?? 'es';
		return preg_match( '/^[a-z]{2}$/', $first ) ? $first : 'es';
	}

	private function html_file_to_mdx( string $abs, string $lang ): string {
		$html = (string) file_get_contents( $abs );
		$hint = [
			'title' => '',
			'slug'  => pathinfo( $abs, PATHINFO_FILENAME ),
		];
		return $this->html_to_mdx( $html, $lang, $hint );
	}

	/**
	 * Core HTML → MDX conversion.
	 *
	 * @param array{title?:string,slug?:string} $hint
	 */
	private function html_to_mdx( string $html, string $lang, array $hint = [] ): string {
		if ( trim( $html ) === '' ) {
			throw new \RuntimeException( 'empty html' );
		}

		libxml_use_internal_errors( true );
		$doc = new \DOMDocument();
		// Force UTF-8 interpretation.
		$doc->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();

		$xpath = new \DOMXPath( $doc );

		// Title.
		$title = (string) ( $hint['title'] ?? '' );
		if ( $title === '' ) {
			$title_node = $xpath->query( '//title' )->item( 0 );
			if ( $title_node ) {
				$title = trim( (string) $title_node->textContent );
			}
		}
		if ( $title === '' ) {
			$h1 = $xpath->query( '//h1' )->item( 0 );
			if ( $h1 ) {
				$title = trim( (string) $h1->textContent );
			}
		}
		if ( $title === '' ) {
			$title = 'Imported page';
		}

		// Meta description.
		$desc = '';
		$desc_node = $xpath->query( '//meta[@name="description"]/@content' )->item( 0 );
		if ( $desc_node ) {
			$desc = trim( (string) $desc_node->nodeValue );
		}

		// Body — fallback to whole doc if no <body>.
		$body = $xpath->query( '//body' )->item( 0 );
		if ( ! $body ) {
			$body = $doc->documentElement;
		}

		$sections = $this->segment_sections( $body, $xpath );

		// Build MDX.
		$slug = sanitize_title( (string) ( $hint['slug'] ?? '' ) );
		$front_lines = [
			'title: ' . $this->yaml_escape( $title ),
			'slug: ' . ( $slug !== '' ? $slug : 'imported-page' ),
			'lang: ' . $lang,
			'review_needed: true',
		];
		if ( ! empty( $hint['source_url'] ) ) {
			$front_lines[] = 'source_url: ' . $this->yaml_escape( (string) $hint['source_url'] );
		}
		if ( $desc !== '' ) {
			$front_lines[] = 'seo:';
			$front_lines[] = '  meta_description: ' . $this->yaml_escape( $desc );
		}

		$out  = "---\n" . implode( "\n", $front_lines ) . "\n---\n\n";

		// Hero from first section.
		$hero_section = array_shift( $sections );
		if ( $hero_section ) {
			$out .= "<Hero id=\"imported-hero\">\n";
			$out .= '# ' . $title . "\n\n";
			if ( $desc !== '' ) {
				$out .= $desc . "\n";
			} elseif ( ! empty( $hero_section['intro'] ) ) {
				$out .= $hero_section['intro'] . "\n";
			}
			$out .= "</Hero>\n\n";
		} else {
			$out .= "<Hero id=\"imported-hero\">\n# {$title}\n</Hero>\n\n";
		}

		$i = 0;
		foreach ( $sections as $sec ) {
			$id = 'section-' . ( ++$i );
			$out .= "<Content id=\"{$id}\">\n";
			if ( ! empty( $sec['heading'] ) ) {
				$out .= '## ' . $sec['heading'] . "\n\n";
			}
			$out .= $sec['markdown'] . "\n";
			$out .= "</Content>\n\n";
		}

		return $out;
	}

	/**
	 * Walks the body and groups content per <h1>/<h2> as sections.
	 *
	 * @return array<int,array{heading:string,markdown:string,intro:string}>
	 */
	private function segment_sections( \DOMNode $body, \DOMXPath $xpath ): array {
		$sections = [];
		$current  = [ 'heading' => '', 'markdown' => '', 'intro' => '' ];
		$has_any  = false;

		foreach ( iterator_to_array( $body->childNodes ) as $node ) {
			if ( ! ( $node instanceof \DOMElement ) ) {
				continue;
			}
			$tag = strtolower( $node->tagName );
			if ( in_array( $tag, [ 'script', 'style', 'noscript', 'svg', 'nav', 'footer', 'header' ], true ) ) {
				continue;
			}
			if ( in_array( $tag, [ 'h1', 'h2' ], true ) ) {
				if ( $has_any ) {
					$sections[] = $current;
				}
				$current = [
					'heading'  => trim( (string) $node->textContent ),
					'markdown' => '',
					'intro'    => '',
				];
				$has_any = true;
				continue;
			}
			$md = $this->node_to_markdown( $node );
			if ( $md === '' ) {
				continue;
			}
			$current['markdown'] .= $md . "\n\n";
			if ( $current['intro'] === '' && $tag === 'p' ) {
				$current['intro'] = trim( (string) $node->textContent );
			}
			$has_any = true;
		}
		if ( $has_any ) {
			$sections[] = $current;
		}
		return $sections;
	}

	private function node_to_markdown( \DOMElement $el ): string {
		$tag = strtolower( $el->tagName );
		$txt = trim( (string) $el->textContent );
		if ( $txt === '' ) {
			return '';
		}
		switch ( $tag ) {
			case 'h3':
				return '### ' . $txt;
			case 'h4':
				return '#### ' . $txt;
			case 'p':
				return $txt;
			case 'ul':
			case 'ol':
				$lines = [];
				foreach ( $el->getElementsByTagName( 'li' ) as $li ) {
					$lines[] = '- ' . trim( (string) $li->textContent );
				}
				return implode( "\n", $lines );
			case 'blockquote':
				return '> ' . $txt;
			case 'a':
				$href = $el->getAttribute( 'href' );
				return $href ? "[{$txt}]({$href})" : $txt;
			case 'img':
				$src = $el->getAttribute( 'src' );
				$alt = $el->getAttribute( 'alt' );
				return $src ? "![{$alt}]({$src})" : '';
			case 'div':
			case 'section':
			case 'article':
			case 'main':
				// Recurse for nested wrappers.
				$out = '';
				foreach ( $el->childNodes as $child ) {
					if ( $child instanceof \DOMElement ) {
						$piece = $this->node_to_markdown( $child );
						if ( $piece !== '' ) {
							$out .= $piece . "\n\n";
						}
					}
				}
				return trim( $out );
			default:
				return $txt;
		}
	}

	private function yaml_escape( string $value ): string {
		$value = str_replace( [ "\r", "\n" ], ' ', $value );
		if ( preg_match( '/[:#"\'\\\\]/', $value ) ) {
			return '"' . str_replace( '"', '\\"', $value ) . '"';
		}
		return '"' . $value . '"';
	}
}
