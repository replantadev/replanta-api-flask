<?php
/**
 * Mirror importer: clones the live HTML body + external CSS of a remote URL
 * into an rt_page so it renders visually identical to the source.
 *
 * Strategy:
 *  - Fetch URL.
 *  - Pick content root (main, [role=main], .elementor, #content, body).
 *  - Strip nav/header/footer/script/noscript (only direct ancestors) and inline event handlers.
 *  - Capture all <link rel=stylesheet> and <style> from <head>.
 *  - Download external CSS into uploads/replanta-imports/{slug}/, rewriting relative url() to absolute.
 *  - Insert/update an rt_page post with post_content = core/html block wrapping the body HTML.
 *  - Persist metas: _rt_mirror=1, _rt_source_url, _rt_mirror_css (array of URLs), _rt_mirror_inline_css.
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class RT_Mirror_Importer {

	public const META_MIRROR        = '_rt_mirror';
	public const META_SOURCE_URL    = '_rt_mirror_source_url';
	public const META_CSS_FILES     = '_rt_mirror_css';
	public const META_INLINE_CSS    = '_rt_mirror_inline_css';
	public const META_IMPORTED_AT   = '_rt_mirror_imported_at';

	public const UPLOAD_DIR = 'replanta-imports';

	/**
	 * @return array<string,mixed>
	 */
	public function import( string $url, ?string $slug = null, string $lang = 'es' ): array {
		$url = esc_url_raw( $url );
		if ( $url === '' ) {
			return [ 'ok' => false, 'error' => 'invalid url' ];
		}

		$resp = wp_remote_get( $url, [
			'timeout'     => 45,
			'redirection' => 5,
			'user-agent'  => 'ReplantaMirror/1.0 (+https://replanta.net)',
		] );
		if ( is_wp_error( $resp ) ) {
			return [ 'ok' => false, 'error' => $resp->get_error_message() ];
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		if ( $code < 200 || $code >= 400 ) {
			return [ 'ok' => false, 'error' => 'http ' . $code ];
		}
		$html = (string) wp_remote_retrieve_body( $resp );
		if ( trim( $html ) === '' ) {
			return [ 'ok' => false, 'error' => 'empty body' ];
		}

		$base = $this->base_url( $url );

		libxml_use_internal_errors( true );
		$doc = new DOMDocument();
		$doc->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();

		$xpath = new DOMXPath( $doc );

		// Title.
		$title = '';
		$tnode = $xpath->query( '//title' )->item( 0 );
		if ( $tnode ) {
			$title = trim( (string) $tnode->textContent );
		}
		if ( $title === '' ) {
			$h1 = $xpath->query( '//h1' )->item( 0 );
			if ( $h1 ) {
				$title = trim( (string) $h1->textContent );
			}
		}
		if ( $title === '' ) {
			$title = 'Mirror ' . wp_parse_url( $url, PHP_URL_HOST );
		}

		// Slug.
		$slug = $slug !== null && $slug !== '' ? sanitize_title( $slug ) : sanitize_title( $this->slug_from_url( $url ) );
		if ( $slug === '' ) {
			$slug = 'mirror-' . wp_generate_password( 6, false );
		}

		// Pick content root.
		$root = $this->pick_root( $xpath );

		// Strip noisy / sensitive nodes inside root.
		$this->strip_noise( $xpath, $root );

		// Rewrite relative URLs in attributes (href, src, srcset, action) to absolute.
		$this->absolutize_urls( $xpath, $root, $base );

		// Render inner HTML.
		$body_html = $this->inner_html( $root );

		// Collect inline <style> from head.
		$inline_css = '';
		foreach ( $xpath->query( '//head/style' ) as $st ) {
			$inline_css .= (string) $st->textContent . "\n";
		}

		// Collect external CSS links from head (also keep relevant inline in body? we ignore body styles to avoid noise).
		$css_links = [];
		foreach ( $xpath->query( '//head/link[@rel="stylesheet"]' ) as $lnk ) {
			$href = (string) $lnk->getAttribute( 'href' );
			if ( $href !== '' ) {
				$css_links[] = $this->absolute_url( $href, $base );
			}
		}

		// Download CSS files into uploads/replanta-imports/{slug}/.
		$saved_css = $this->download_css_files( $css_links, $slug );

		// Build/Update rt_page.
		$post_id = $this->find_or_create_post( $slug, $title );
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return [ 'ok' => false, 'error' => 'wp_insert_post failed' ];
		}

		$content = "<!-- wp:html -->\n" . $body_html . "\n<!-- /wp:html -->";
		wp_update_post( [
			'ID'           => $post_id,
			'post_title'   => $title,
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_name'    => $slug,
		] );

		update_post_meta( $post_id, self::META_MIRROR, 1 );
		update_post_meta( $post_id, self::META_SOURCE_URL, $url );
		update_post_meta( $post_id, self::META_CSS_FILES, $saved_css );
		update_post_meta( $post_id, self::META_INLINE_CSS, $inline_css );
		update_post_meta( $post_id, self::META_IMPORTED_AT, time() );
		// Compatibility with the rest of the engine.
		if ( defined( 'RT_Content_Sync::META_SOURCE_URL' ) || class_exists( 'RT_Content_Sync' ) ) {
			update_post_meta( $post_id, RT_Content_Sync::META_SOURCE_URL, $url );
		}
		update_post_meta( $post_id, '_rt_lang', $lang );

		return [
			'ok'        => true,
			'id'        => $post_id,
			'url'       => get_permalink( $post_id ),
			'edit_url'  => get_edit_post_link( $post_id, 'raw' ),
			'css_count' => count( $saved_css ),
			'css_files' => $saved_css,
			'title'     => $title,
			'slug'      => $slug,
		];
	}

	/* ------------------------------------------------------------ Internals */

	private function pick_root( DOMXPath $xpath ): DOMNode {
		$candidates = [
			'//main',
			'//*[@role="main"]',
			'//*[contains(concat(" ",normalize-space(@class)," ")," elementor ")]',
			'//*[@id="content"]',
			'//*[@id="primary"]',
			'//body',
		];
		foreach ( $candidates as $q ) {
			$n = $xpath->query( $q )->item( 0 );
			if ( $n instanceof DOMNode ) {
				return $n;
			}
		}
		return $xpath->document->documentElement;
	}

	private function strip_noise( DOMXPath $xpath, DOMNode $root ): void {
		$selectors = [
			'.//script', './/noscript', './/template', './/iframe[contains(@src,"recaptcha")]',
			// Site chrome (only when present as descendants of the picked root—so .elementor wins keeping its own content).
			'.//header[not(ancestor::*[contains(@class,"elementor")])]',
			'.//footer[not(ancestor::*[contains(@class,"elementor")])]',
			'.//nav[not(ancestor::*[contains(@class,"elementor")])]',
		];
		foreach ( $selectors as $sel ) {
			$nodes = $xpath->query( $sel, $root );
			if ( ! $nodes ) {
				continue;
			}
			foreach ( iterator_to_array( $nodes ) as $n ) {
				if ( $n->parentNode ) {
					$n->parentNode->removeChild( $n );
				}
			}
		}
		// Remove on* event handlers.
		foreach ( $xpath->query( './/*', $root ) as $el ) {
			if ( ! $el instanceof DOMElement ) {
				continue;
			}
			$attrs = [];
			foreach ( $el->attributes as $a ) {
				if ( strpos( strtolower( $a->name ), 'on' ) === 0 ) {
					$attrs[] = $a->name;
				}
			}
			foreach ( $attrs as $name ) {
				$el->removeAttribute( $name );
			}
		}
	}

	private function absolutize_urls( DOMXPath $xpath, DOMNode $root, string $base ): void {
		$map = [ 'href', 'src', 'action', 'data-src', 'data-bg', 'poster' ];
		foreach ( $xpath->query( './/*[@href or @src or @action or @data-src or @data-bg or @poster]', $root ) as $el ) {
			if ( ! $el instanceof DOMElement ) {
				continue;
			}
			foreach ( $map as $attr ) {
				if ( ! $el->hasAttribute( $attr ) ) {
					continue;
				}
				$v = (string) $el->getAttribute( $attr );
				if ( $v === '' ) {
					continue;
				}
				$abs = $this->absolute_url( $v, $base );
				if ( $abs !== $v ) {
					$el->setAttribute( $attr, $abs );
				}
			}
			if ( $el->hasAttribute( 'srcset' ) ) {
				$el->setAttribute( 'srcset', $this->absolutize_srcset( $el->getAttribute( 'srcset' ), $base ) );
			}
			if ( $el->hasAttribute( 'style' ) ) {
				$el->setAttribute( 'style', $this->absolutize_css_urls( $el->getAttribute( 'style' ), $base ) );
			}
		}
	}

	private function absolutize_srcset( string $srcset, string $base ): string {
		$parts = preg_split( '/\s*,\s*/', $srcset );
		if ( ! is_array( $parts ) ) {
			return $srcset;
		}
		$out = [];
		foreach ( $parts as $p ) {
			$p = trim( $p );
			if ( $p === '' ) {
				continue;
			}
			$pieces = preg_split( '/\s+/', $p, 2 );
			if ( ! $pieces ) {
				continue;
			}
			$pieces[0] = $this->absolute_url( $pieces[0], $base );
			$out[]     = implode( ' ', $pieces );
		}
		return implode( ', ', $out );
	}

	private function absolutize_css_urls( string $css, string $base ): string {
		return (string) preg_replace_callback(
			'/url\(\s*([\'"]?)([^\'")]+)\1\s*\)/i',
			function ( $m ) use ( $base ) {
				$abs = $this->absolute_url( $m[2], $base );
				return 'url(' . $m[1] . $abs . $m[1] . ')';
			},
			$css
		);
	}

	private function absolute_url( string $u, string $base ): string {
		$u = trim( $u );
		if ( $u === '' || str_starts_with( $u, 'data:' ) || str_starts_with( $u, 'mailto:' ) || str_starts_with( $u, 'tel:' ) || str_starts_with( $u, '#' ) ) {
			return $u;
		}
		if ( preg_match( '#^[a-z][a-z0-9+.-]*://#i', $u ) ) {
			return $u;
		}
		$parsed = wp_parse_url( $base );
		$scheme = $parsed['scheme'] ?? 'https';
		$host   = $parsed['host'] ?? '';
		$path   = $parsed['path'] ?? '/';
		if ( str_starts_with( $u, '//' ) ) {
			return $scheme . ':' . $u;
		}
		if ( str_starts_with( $u, '/' ) ) {
			return $scheme . '://' . $host . $u;
		}
		// Relative path.
		$dir = rtrim( substr( $path, 0, (int) strrpos( $path, '/' ) + 1 ), '/' );
		return $scheme . '://' . $host . $dir . '/' . $u;
	}

	private function base_url( string $url ): string {
		$p = wp_parse_url( $url );
		if ( ! is_array( $p ) || empty( $p['scheme'] ) || empty( $p['host'] ) ) {
			return $url;
		}
		return $p['scheme'] . '://' . $p['host'] . ( $p['path'] ?? '/' );
	}

	private function slug_from_url( string $url ): string {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$path = trim( $path, '/' );
		if ( $path === '' ) {
			return 'home';
		}
		$last = basename( $path );
		return $last !== '' ? $last : str_replace( '/', '-', $path );
	}

	private function inner_html( DOMNode $node ): string {
		$out = '';
		foreach ( $node->childNodes as $child ) {
			$out .= $node->ownerDocument->saveHTML( $child );
		}
		return $out;
	}

	/**
	 * @param array<int,string> $links
	 * @return array<int,string> URLs of saved CSS in uploads.
	 */
	private function download_css_files( array $links, string $slug ): array {
		if ( ! $links ) {
			return [];
		}
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . self::UPLOAD_DIR . '/' . $slug;
		$baseurl = trailingslashit( $uploads['baseurl'] ) . self::UPLOAD_DIR . '/' . $slug;
		if ( ! wp_mkdir_p( $dir ) ) {
			return [];
		}
		$saved = [];
		$i     = 0;
		foreach ( array_unique( $links ) as $link ) {
			$i++;
			$resp = wp_remote_get( $link, [ 'timeout' => 30, 'redirection' => 5 ] );
			if ( is_wp_error( $resp ) ) {
				continue;
			}
			$code = (int) wp_remote_retrieve_response_code( $resp );
			if ( $code < 200 || $code >= 400 ) {
				continue;
			}
			$css      = (string) wp_remote_retrieve_body( $resp );
			$css_base = $this->base_url( $link );
			// Resolve url() inside the CSS to absolute origin URLs.
			$css = $this->absolutize_css_urls( $css, $css_base );
			// Resolve @import url() similarly.
			$css = (string) preg_replace_callback(
				'/@import\s+(?:url\()?\s*([\'"]?)([^\'")\s;]+)\1\s*\)?/i',
				function ( $m ) use ( $css_base ) {
					$abs = $this->absolute_url( $m[2], $css_base );
					return '@import url(' . $m[1] . $abs . $m[1] . ')';
				},
				$css
			);

			$basename = sanitize_file_name( basename( (string) wp_parse_url( $link, PHP_URL_PATH ) ) );
			if ( $basename === '' || ! str_ends_with( strtolower( $basename ), '.css' ) ) {
				$basename = 'sheet-' . $i . '.css';
			}
			$file = $dir . '/' . sprintf( '%02d-%s', $i, $basename );
			if ( file_put_contents( $file, $css ) === false ) {
				continue;
			}
			$saved[] = $baseurl . '/' . sprintf( '%02d-%s', $i, $basename );
		}
		return $saved;
	}

	private function find_or_create_post( string $slug, string $title ): int {
		$existing = get_page_by_path( $slug, OBJECT, RT_CPT_Page::POST_TYPE );
		if ( $existing instanceof WP_Post ) {
			return (int) $existing->ID;
		}
		$id = wp_insert_post( [
			'post_type'   => RT_CPT_Page::POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => $title,
			'post_name'   => $slug,
		], true );
		return is_wp_error( $id ) ? 0 : (int) $id;
	}
}
