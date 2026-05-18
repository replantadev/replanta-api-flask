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
	public const META_ASSETS        = '_rt_mirror_assets';

	public const UPLOAD_DIR = 'replanta-imports';

	/** @var array<string,string> origin absolute URL => local URL */
	private array $asset_map = [];

	/** @var string Active slug used as destination subdir. */
	private string $current_slug = '';

	/**
	 * @return array<string,mixed>
	 */
	public function import( string $url, ?string $slug = null, string $lang = 'es' ): array {
		$url = esc_url_raw( $url );
		if ( $url === '' ) {
			return [ 'ok' => false, 'error' => 'invalid url' ];
		}

		$resp = $this->http_get_retry( $url, 45 );
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

		$this->asset_map    = [];
		$this->current_slug = $slug;

		// Pick content root.
		$root = $this->pick_root( $xpath );

		// Strip noisy / sensitive nodes inside root.
		$this->strip_noise( $xpath, $root );

		// Rewrite relative URLs in attributes (href, src, srcset, action) to absolute.
		$this->absolutize_urls( $xpath, $root, $base );

		// Download images and rewrite DOM references to local copies.
		$this->download_assets_in_dom( $xpath, $root );

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
		$saved_css = $this->download_css_files( $css_links, $slug );		// Build/Update rt_page.
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
		update_post_meta( $post_id, self::META_ASSETS, $this->asset_map );
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
			'img_count' => count( $this->asset_map ),
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
			$resp = $this->http_get_retry( $link, 30 );
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
			// Now rewrite url() to point to LOCAL downloaded copies when possible.
			$css = (string) preg_replace_callback(
				'/url\(\s*([\'"]?)([^\'")]+)\1\s*\)/i',
				function ( $m ) {
					$ref = trim( $m[2] );
					if ( $ref === '' || str_starts_with( $ref, 'data:' ) ) {
						return $m[0];
					}
					$local = $this->download_one( $ref, '' );
					return 'url(' . $m[1] . $local . $m[1] . ')';
				},
				$css
			);
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

	/* --------------------------------------------------------- Image assets */

	/**
	 * Walk the DOM and download every <img>/srcset/poster/background image to the local
	 * uploads dir, rewriting attributes to point to the local copies.
	 */
	private function download_assets_in_dom( DOMXPath $xpath, DOMNode $root ): void {
		$nodes = $xpath->query( './/img | .//source | .//video | .//audio | .//*[@poster or @data-src or @data-bg or @data-background or @data-lazy-src or @data-srcset]', $root );
		if ( $nodes ) {
			foreach ( $nodes as $el ) {
				if ( ! $el instanceof DOMElement ) {
					continue;
				}
				foreach ( [ 'src', 'data-src', 'data-bg', 'data-background', 'data-lazy-src', 'poster' ] as $attr ) {
					if ( ! $el->hasAttribute( $attr ) ) {
						continue;
					}
					$v = $el->getAttribute( $attr );
					if ( $v === '' ) {
						continue;
					}
					$new = $this->download_one( $v, '' );
					if ( $new !== $v ) {
						$el->setAttribute( $attr, $new );
					}
				}
				foreach ( [ 'srcset', 'data-srcset' ] as $attr ) {
					if ( ! $el->hasAttribute( $attr ) ) {
						continue;
					}
					$el->setAttribute( $attr, $this->rewrite_srcset_to_local( $el->getAttribute( $attr ) ) );
				}
			}
		}
		// Inline style backgrounds.
		$styled = $xpath->query( './/*[contains(@style,"url(")]', $root );
		if ( $styled ) {
			foreach ( $styled as $el ) {
				if ( ! $el instanceof DOMElement ) {
					continue;
				}
				$style = $el->getAttribute( 'style' );
				$new   = (string) preg_replace_callback(
					'/url\(\s*([\'"]?)([^\'")]+)\1\s*\)/i',
					function ( $m ) {
						$ref = trim( $m[2] );
						if ( $ref === '' || str_starts_with( $ref, 'data:' ) ) {
							return $m[0];
						}
						$local = $this->download_one( $ref, '' );
						return 'url(' . $m[1] . $local . $m[1] . ')';
					},
					$style
				);
				if ( $new !== $style ) {
					$el->setAttribute( 'style', $new );
				}
			}
		}
	}

	private function rewrite_srcset_to_local( string $srcset ): string {
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
			$pieces[0] = $this->download_one( $pieces[0], '' );
			$out[]     = implode( ' ', $pieces );
		}
		return implode( ', ', $out );
	}

	/**
	 * Download one remote asset into the slug uploads dir and return the local URL.
	 * Returns the original (absolute) URL on any failure so the page still works.
	 */
	private function download_one( string $remote, string $base ): string {
		$remote = trim( $remote );
		if ( $remote === '' || str_starts_with( $remote, 'data:' ) ) {
			return $remote;
		}
		$abs = $this->absolute_url( $remote, $base );
		if ( ! preg_match( '#^https?://#i', $abs ) ) {
			return $abs;
		}
		if ( isset( $this->asset_map[ $abs ] ) ) {
			return $this->asset_map[ $abs ];
		}
		$path = (string) wp_parse_url( $abs, PHP_URL_PATH );
		$bn   = sanitize_file_name( basename( $path ) );
		if ( $bn === '' || strpos( $bn, '.' ) === false ) {
			$this->asset_map[ $abs ] = $abs;
			return $abs;
		}
		$ext     = strtolower( pathinfo( $bn, PATHINFO_EXTENSION ) );
		$allowed = [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'bmp', 'ico' ];
		if ( ! in_array( $ext, $allowed, true ) ) {
			$this->asset_map[ $abs ] = $abs;
			return $abs;
		}
		$uploads  = wp_upload_dir();
		$dir      = trailingslashit( $uploads['basedir'] ) . self::UPLOAD_DIR . '/' . $this->current_slug . '/img';
		$url_base = trailingslashit( $uploads['baseurl'] ) . self::UPLOAD_DIR . '/' . $this->current_slug . '/img';
		if ( ! wp_mkdir_p( $dir ) ) {
			$this->asset_map[ $abs ] = $abs;
			return $abs;
		}
		$bn        = substr( md5( $abs ), 0, 8 ) . '-' . $bn;
		$file      = $dir . '/' . $bn;
		$local_url = $url_base . '/' . $bn;
		if ( ! file_exists( $file ) ) {
			$resp = $this->http_get_retry( $abs, 30 );
			if ( is_wp_error( $resp ) ) {
				$this->asset_map[ $abs ] = $abs;
				return $abs;
			}
			$code = (int) wp_remote_retrieve_response_code( $resp );
			if ( $code < 200 || $code >= 400 ) {
				$this->asset_map[ $abs ] = $abs;
				return $abs;
			}
			$body = (string) wp_remote_retrieve_body( $resp );
			if ( $body === '' || file_put_contents( $file, $body ) === false ) {
				$this->asset_map[ $abs ] = $abs;
				return $abs;
			}
		}
		$webp = $this->maybe_to_webp( $file );
		if ( $webp !== null ) {
			$local_url = $url_base . '/' . basename( $webp );
		}
		$this->asset_map[ $abs ] = $local_url;
		return $local_url;
	}

	/**
	 * Convert a JPG/PNG to WebP next to it (quality 82). Returns the webp path or null.
	 */
	private function maybe_to_webp( string $file ): ?string {
		$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, [ 'jpg', 'jpeg', 'png' ], true ) ) {
			return null;
		}
		$webp = (string) preg_replace( '/\.(jpe?g|png)$/i', '.webp', $file );
		if ( $webp === '' || $webp === $file ) {
			return null;
		}
		if ( file_exists( $webp ) ) {
			return $webp;
		}
		// Imagick path.
		if ( class_exists( 'Imagick' ) ) {
			try {
				$im = new Imagick( $file );
				$im->setImageFormat( 'webp' );
				$im->setImageCompressionQuality( 82 );
				$im->writeImage( $webp );
				$im->clear();
				if ( file_exists( $webp ) ) {
					return $webp;
				}
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
				// Fall through to GD.
			}
		}
		// GD path with WebP support.
		if ( function_exists( 'imagewebp' ) ) {
			$img = false;
			if ( $ext === 'png' && function_exists( 'imagecreatefrompng' ) ) {
				$img = @imagecreatefrompng( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			} elseif ( function_exists( 'imagecreatefromjpeg' ) ) {
				$img = @imagecreatefromjpeg( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
			if ( $img !== false ) {
				$ok = @imagewebp( $img, $webp, 82 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				imagedestroy( $img );
				if ( $ok ) {
					return $webp;
				}
			}
		}
		return null;
	}

	/**
	 * HTTP GET with ETag/Last-Modified cache and one retry on 5xx/timeout.
	 *
	 * Cached responses are stored as transient `rt_mir_http_<md5(url)>` with
	 * keys: etag, last_modified, body, code, expires. We never re-use the
	 * cached body if the server doesn't return 304, but ETag/IMS save bandwidth.
	 *
	 * @return array<mixed>|WP_Error WordPress HTTP response array, or WP_Error.
	 */
	private function http_get_retry( string $url, int $timeout ) {
		$key   = 'rt_mir_http_' . md5( $url );
		$cache = get_transient( $key );
		if ( ! is_array( $cache ) ) {
			$cache = [];
		}
		$headers = [];
		if ( ! empty( $cache['etag'] ) ) {
			$headers['If-None-Match'] = (string) $cache['etag'];
		}
		if ( ! empty( $cache['last_modified'] ) ) {
			$headers['If-Modified-Since'] = (string) $cache['last_modified'];
		}
		$args = [
			'timeout'     => $timeout,
			'redirection' => 5,
			'user-agent'  => 'ReplantaMirror/1.0 (+https://replanta.net)',
			'headers'     => $headers,
		];
		$resp = wp_remote_get( $url, $args );
		if ( $this->should_retry( $resp ) ) {
			usleep( 400000 ); // 400 ms backoff.
			$resp = wp_remote_get( $url, $args );
		}
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		if ( $code === 304 && isset( $cache['body'] ) ) {
			// Synthesize a 200 with the cached body.
			$resp['response']['code'] = 200;
			$resp['body']             = (string) $cache['body'];
			return $resp;
		}
		if ( $code >= 200 && $code < 300 ) {
			$etag = (string) wp_remote_retrieve_header( $resp, 'etag' );
			$lm   = (string) wp_remote_retrieve_header( $resp, 'last-modified' );
			$body = (string) wp_remote_retrieve_body( $resp );
			// Cap cached body at ~512 KB to avoid blowing up options table.
			if ( strlen( $body ) <= 524288 ) {
				set_transient( $key, [
					'etag'          => $etag,
					'last_modified' => $lm,
					'body'          => $body,
					'code'          => $code,
				], HOUR_IN_SECONDS );
			}
		}
		return $resp;
	}

	private function should_retry( $resp ): bool {
		if ( is_wp_error( $resp ) ) {
			return true;
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		return $code >= 500 && $code < 600;
	}
}
