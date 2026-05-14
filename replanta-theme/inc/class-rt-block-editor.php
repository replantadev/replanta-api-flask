<?php
/**
 * Block-level editor for MDX pages.
 *
 * Treats top-level JSX components, contiguous markdown chunks and standalone
 * WordPress shortcodes as ordered blocks that can be inserted, moved, edited,
 * deleted, duplicated and AI-rewritten individually — preserving the rest of
 * the page intact.
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class RT_Block_Editor {

	public const TYPE_COMPONENT = 'component';
	public const TYPE_MARKDOWN  = 'markdown';
	public const TYPE_SHORTCODE = 'shortcode';

	private const COMPONENT_PATTERN = '/<([A-Z][A-Za-z0-9]*)([^>]*?)(?:\/>|>([\s\S]*?)<\/\1>)/m';
	private const SHORTCODE_LINE    = '/^\s*\[[a-z][^\]\n]*\](?:[\s\S]*\[\/[a-z][^\]]*\])?\s*$/i';

	/** Available components and a starter template for "Insert AI block". */
	public const TEMPLATES = [
		'Hero'         => "<Hero id=\"hero-NEW\" title=\"Título\" subtitle=\"Subtítulo\" cta=\"Empezar\" href=\"#\">\n\nDescripción del hero.\n\n</Hero>",
		'Features'     => "<Features id=\"features-NEW\" title=\"Características\">\n\n- **Rápido** — descripción\n- **Eco** — descripción\n- **Sólido** — descripción\n\n</Features>",
		'Stats'        => "<Stats id=\"stats-NEW\">\n\n- 100k usuarios\n- 50% menos CO₂\n- 24/7 soporte\n\n</Stats>",
		'CTA'          => "<CTA id=\"cta-NEW\" title=\"¿Listo?\" cta=\"Empezar ahora\" href=\"#\">\n\nUna línea de copy persuasivo.\n\n</CTA>",
		'FAQ'          => "<FAQ id=\"faq-NEW\" title=\"Preguntas frecuentes\">\n\n**¿Pregunta 1?**\nRespuesta 1.\n\n**¿Pregunta 2?**\nRespuesta 2.\n\n</FAQ>",
		'Pricing'      => "<Pricing id=\"pricing-NEW\" title=\"Planes\">\n\n- **Básico** — 9€/mes\n- **Pro** — 29€/mes\n- **Enterprise** — contacta\n\n</Pricing>",
		'Testimonials' => "<Testimonials id=\"test-NEW\" title=\"Lo que dicen\">\n\n> \"Texto del testimonio.\" — Nombre, Empresa\n\n</Testimonials>",
		'Content'      => "<Content id=\"content-NEW\">\n\n## Encabezado\n\nPárrafo de contenido libre.\n\n</Content>",
		'Markdown'     => "## Nuevo encabezado\n\nPárrafo de markdown libre. Puedes usar **negrita**, *cursiva* y [enlaces](#).",
		'Shortcode'    => "[contact-form-7 id=\"123\"]",
	];

	/**
	 * Parse a raw MDX body into ordered blocks.
	 *
	 * @return array<int,array{index:int,type:string,tag:?string,attrs:array<string,string>,inner:string,raw:string,preview:string}>
	 */
	public function parse_body( string $body ): array {
		$blocks  = [];
		$offset  = 0;

		if ( preg_match_all( self::COMPONENT_PATTERN, $body, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) {
			foreach ( $matches as $m ) {
				$start = (int) $m[0][1];
				if ( $start > $offset ) {
					$chunk = substr( $body, $offset, $start - $offset );
					$this->push_text_chunks( $chunk, $blocks );
				}
				$tag       = (string) $m[1][0];
				$attrs_raw = (string) $m[2][0];
				$inner     = isset( $m[3] ) && (int) $m[3][1] !== -1 ? (string) $m[3][0] : '';
				$blocks[]  = [
					'type'  => self::TYPE_COMPONENT,
					'tag'   => $tag,
					'attrs' => $this->parse_attrs( $attrs_raw ),
					'inner' => trim( $inner ),
					'raw'   => (string) $m[0][0],
				];
				$offset = $start + strlen( (string) $m[0][0] );
			}
		}
		if ( $offset < strlen( $body ) ) {
			$this->push_text_chunks( substr( $body, $offset ), $blocks );
		}

		$out = [];
		foreach ( $blocks as $i => $b ) {
			$b['index']   = $i;
			$b['tag']     = $b['tag']   ?? null;
			$b['attrs']   = $b['attrs'] ?? [];
			$b['inner']   = $b['inner'] ?? '';
			$b['preview'] = $this->preview_for( $b );
			$out[]        = $b;
		}
		return $out;
	}

	/**
	 * Rebuild the MDX body from blocks (joined with blank lines).
	 *
	 * @param array<int,array<string,mixed>> $blocks
	 */
	public function serialize( array $blocks ): string {
		$parts = [];
		foreach ( $blocks as $b ) {
			$raw = trim( (string) ( $b['raw'] ?? '' ) );
			if ( $raw !== '' ) {
				$parts[] = $raw;
			}
		}
		return implode( "\n\n", $parts ) . "\n";
	}

	/* -------------------------------------------------------------- File I/O */

	public function load( string $rel_path ): array {
		$abs = $this->abs( $rel_path );
		if ( ! is_file( $abs ) ) {
			return [ 'ok' => false, 'error' => 'file not found' ];
		}
		$raw   = (string) file_get_contents( $abs );
		$split = RT_MDX_Parser::split( $raw );
		$blocks = $this->parse_body( $split['body'] );
		return [
			'ok'          => true,
			'path'        => $rel_path,
			'frontmatter' => $split['frontmatter'],
			'blocks'      => $blocks,
			'templates'   => array_keys( self::TEMPLATES ),
		];
	}

	private function save_blocks( string $rel_path, array $blocks ): array {
		$abs = $this->abs( $rel_path );
		if ( ! is_file( $abs ) ) {
			return [ 'ok' => false, 'error' => 'file not found' ];
		}
		$raw   = (string) file_get_contents( $abs );
		$split = RT_MDX_Parser::split( $raw );
		$body  = $this->serialize( $blocks );
		$out   = RT_MDX_Parser::compose( $split['frontmatter'], $body );
		file_put_contents( $abs, $out );
		( new RT_Content_Sync() )->sync_file( $abs );
		return [ 'ok' => true, 'path' => $rel_path, 'blocks' => $this->parse_body( $body ) ];
	}

	/* ------------------------------------------------------------------ CRUD */

	public function update( string $rel_path, int $index, string $raw ): array {
		$state = $this->load( $rel_path );
		if ( ! $state['ok'] ) {
			return $state;
		}
		$blocks = $state['blocks'];
		if ( ! isset( $blocks[ $index ] ) ) {
			return [ 'ok' => false, 'error' => 'index out of range' ];
		}
		$blocks[ $index ]['raw'] = trim( $raw );
		return $this->save_blocks( $rel_path, $blocks );
	}

	/**
	 * Update only the attributes of a component block, preserving inner content.
	 *
	 * @param array<string,string> $new_attrs
	 */
	public function update_attrs( string $rel_path, int $index, array $new_attrs ): array {
		$state = $this->load( $rel_path );
		if ( ! $state['ok'] ) {
			return $state;
		}
		$blocks = $state['blocks'];
		if ( ! isset( $blocks[ $index ] ) ) {
			return [ 'ok' => false, 'error' => 'index out of range' ];
		}
		$b = $blocks[ $index ];
		if ( $b['type'] !== self::TYPE_COMPONENT ) {
			return [ 'ok' => false, 'error' => 'attrs only available for component blocks' ];
		}
		$clean = [];
		foreach ( $new_attrs as $k => $v ) {
			$k = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $k ) ?? '';
			if ( $k === '' ) {
				continue;
			}
			$v = trim( (string) $v );
			if ( $v === '' ) {
				continue;
			}
			$clean[ $k ] = $v;
		}
		$tag      = (string) ( $b['tag'] ?? 'Section' );
		$attr_str = '';
		foreach ( $clean as $k => $v ) {
			$attr_str .= ' ' . $k . '="' . str_replace( '"', '&quot;', $v ) . '"';
		}
		$raw_old = (string) ( $b['raw'] ?? '' );
		if ( preg_match( '/\/>\s*$/', $raw_old ) ) {
			$new = '<' . $tag . $attr_str . ' />';
		} else {
			$inner = trim( (string) ( $b['inner'] ?? '' ) );
			$new   = '<' . $tag . $attr_str . '>' . "\n\n" . $inner . "\n\n" . '</' . $tag . '>';
		}
		$blocks[ $index ]['raw'] = $new;
		return $this->save_blocks( $rel_path, $blocks );
	}

	/**
	 * Heuristic page audit (SEO, accessibility, readability) without AI cost.
	 *
	 * @return array<string,mixed>
	 */
	public function audit( string $rel_path ): array {
		$state = $this->load( $rel_path );
		if ( ! $state['ok'] ) {
			return $state;
		}
		$blocks = $state['blocks'];
		$body   = $this->serialize( $blocks );
		$plain  = wp_strip_all_tags( preg_replace( '/<[^>]+>/', ' ', $body ) ?? $body );
		$plain  = preg_replace( '/\s+/', ' ', $plain ) ?? $plain;
		$words  = $plain === '' ? 0 : count( preg_split( '/\s+/', trim( $plain ) ) ?: [] );

		$h1   = preg_match_all( '/^# [^\n]+/m', $body );
		$h2   = preg_match_all( '/^## [^\n]+/m', $body );
		$imgs = preg_match_all( '/!\[(.*?)\]\(([^)]+)\)/', $body, $im );
		$imgs_no_alt = 0;
		if ( $imgs ) {
			foreach ( $im[1] as $alt ) {
				if ( trim( (string) $alt ) === '' ) {
					$imgs_no_alt++;
				}
			}
		}
		$component_titles = 0;
		foreach ( $blocks as $b ) {
			if ( $b['type'] === self::TYPE_COMPONENT && ! empty( $b['attrs']['title'] ) ) {
				$component_titles++;
			}
		}
		// Long sentences heuristic (>30 words).
		$long = 0;
		$sent = preg_split( '/[\.!?]+\s+/', $plain ) ?: [];
		foreach ( $sent as $s ) {
			$wc = $s === '' ? 0 : count( preg_split( '/\s+/', trim( $s ) ) ?: [] );
			if ( $wc > 30 ) {
				$long++;
			}
		}
		$reading_min = max( 1, (int) round( $words / 220 ) );

		$issues = [];
		$wins   = [];

		// SEO
		if ( $h1 === 0 && $component_titles === 0 ) {
			$issues[] = [ 'level' => 'err', 'cat' => 'SEO', 'msg' => 'No hay un H1 ni componente con título. Añade un Hero o un encabezado #.' ];
		} elseif ( $h1 > 1 ) {
			$issues[] = [ 'level' => 'warn', 'cat' => 'SEO', 'msg' => 'Hay ' . $h1 . ' H1. Lo recomendable es uno solo por página.' ];
		} else {
			$wins[] = 'Estructura de encabezados con un único H1.';
		}
		if ( $words < 120 ) {
			$issues[] = [ 'level' => 'warn', 'cat' => 'SEO', 'msg' => 'Contenido corto (' . $words . ' palabras). Añade contexto para mejorar posicionamiento.' ];
		} elseif ( $words >= 300 ) {
			$wins[] = 'Volumen de contenido razonable (' . $words . ' palabras).';
		}
		if ( $h2 === 0 && $words > 250 ) {
			$issues[] = [ 'level' => 'warn', 'cat' => 'SEO', 'msg' => 'No hay H2. Divide el contenido en secciones para mejor lectura.' ];
		}

		// Accesibilidad
		if ( $imgs > 0 && $imgs_no_alt > 0 ) {
			$issues[] = [ 'level' => 'err', 'cat' => 'A11y', 'msg' => $imgs_no_alt . ' imagen(es) sin texto alternativo.' ];
		} elseif ( $imgs > 0 ) {
			$wins[] = 'Todas las imágenes tienen texto alternativo.';
		}

		// Lectura
		if ( $long > 3 ) {
			$issues[] = [ 'level' => 'warn', 'cat' => 'Lectura', 'msg' => $long . ' frases con más de 30 palabras. Acórtalas para mejor lectura.' ];
		} elseif ( $words > 100 ) {
			$wins[] = 'Frases de longitud razonable.';
		}

		// Estructura
		if ( count( $blocks ) === 1 && $blocks[0]['type'] === RT_Block_Editor::TYPE_MARKDOWN ) {
			$issues[] = [ 'level' => 'warn', 'cat' => 'UX', 'msg' => 'La página es solo un bloque de markdown. Añade Hero, CTA o Features para más impacto.' ];
		}

		$score = 100;
		foreach ( $issues as $i ) {
			$score -= ( $i['level'] === 'err' ? 18 : 8 );
		}
		$score = max( 0, $score );

		return [
			'ok'           => true,
			'score'        => $score,
			'words'        => $words,
			'reading_min'  => $reading_min,
			'h1'           => (int) $h1,
			'h2'           => (int) $h2,
			'images'       => (int) $imgs,
			'images_no_alt'=> $imgs_no_alt,
			'blocks'       => count( $blocks ),
			'issues'       => $issues,
			'wins'         => $wins,
		];
	}

	public function delete( string $rel_path, int $index ): array {
		$state = $this->load( $rel_path );
		if ( ! $state['ok'] ) {
			return $state;
		}
		$blocks = $state['blocks'];
		if ( ! isset( $blocks[ $index ] ) ) {
			return [ 'ok' => false, 'error' => 'index out of range' ];
		}
		array_splice( $blocks, $index, 1 );
		return $this->save_blocks( $rel_path, $blocks );
	}

	public function move( string $rel_path, int $index, int $direction ): array {
		$state = $this->load( $rel_path );
		if ( ! $state['ok'] ) {
			return $state;
		}
		$blocks = $state['blocks'];
		$target = $index + ( $direction < 0 ? -1 : 1 );
		if ( ! isset( $blocks[ $index ], $blocks[ $target ] ) ) {
			return [ 'ok' => false, 'error' => 'cannot move' ];
		}
		[ $blocks[ $index ], $blocks[ $target ] ] = [ $blocks[ $target ], $blocks[ $index ] ];
		return $this->save_blocks( $rel_path, $blocks );
	}

	public function duplicate( string $rel_path, int $index ): array {
		$state = $this->load( $rel_path );
		if ( ! $state['ok'] ) {
			return $state;
		}
		$blocks = $state['blocks'];
		if ( ! isset( $blocks[ $index ] ) ) {
			return [ 'ok' => false, 'error' => 'index out of range' ];
		}
		$copy = $blocks[ $index ];
		// Bump id attribute if present so duplicates don't collide.
		$copy['raw'] = $this->bump_id( $copy['raw'] );
		array_splice( $blocks, $index + 1, 0, [ $copy ] );
		return $this->save_blocks( $rel_path, $blocks );
	}

	public function insert( string $rel_path, int $position, string $template, string $custom_raw = '' ): array {
		$state = $this->load( $rel_path );
		if ( ! $state['ok'] ) {
			return $state;
		}
		$blocks = $state['blocks'];
		$raw    = $custom_raw !== '' ? $custom_raw : (string) ( self::TEMPLATES[ $template ] ?? self::TEMPLATES['Markdown'] );
		$raw    = $this->bump_id( $raw );
		$position = max( 0, min( $position, count( $blocks ) ) );
		array_splice( $blocks, $position, 0, [ [ 'raw' => $raw ] ] );
		return $this->save_blocks( $rel_path, $blocks );
	}

	/** Insert an AI-generated block of given $template type from a free-form prompt. */
	public function insert_ai( string $rel_path, int $position, string $template, string $prompt, string $lang = 'es' ): array {
		$gen = new RT_Page_Generator();
		$res = $gen->generate_block_mdx( $template, $prompt, $lang );
		if ( ! $res['ok'] ) {
			return [ 'ok' => false, 'error' => $res['error'] ?? 'AI error' ];
		}
		return $this->insert( $rel_path, $position, $template, (string) $res['mdx'] );
	}

	/** Rewrite a single block in place using AI. */
	public function rewrite_ai( string $rel_path, int $index, string $instruction, string $lang = 'es' ): array {
		$state = $this->load( $rel_path );
		if ( ! $state['ok'] ) {
			return $state;
		}
		$blocks = $state['blocks'];
		if ( ! isset( $blocks[ $index ] ) ) {
			return [ 'ok' => false, 'error' => 'index out of range' ];
		}
		$gen = new RT_Page_Generator();
		$res = $gen->rewrite_block_mdx( (string) $blocks[ $index ]['raw'], $instruction, $lang );
		if ( ! $res['ok'] ) {
			return [ 'ok' => false, 'error' => $res['error'] ?? 'AI error' ];
		}
		$blocks[ $index ]['raw'] = (string) $res['mdx'];
		return $this->save_blocks( $rel_path, $blocks );
	}

	/* ----------------------------------------------------------- Block library */

	/**
	 * Save the block at $index of $rel_path to the reusable library, then —
	 * if $replace_with_include is true — replace the source block with a
	 * `<Include slug="X"/>` reference. Returns refreshed page state.
	 */
	public function save_to_library(
		string $rel_path,
		int $index,
		string $slug,
		string $title,
		bool $replace_with_include = true
	): array {
		$state = $this->load( $rel_path );
		if ( ! $state['ok'] ) {
			return $state;
		}
		$blocks = $state['blocks'];
		if ( ! isset( $blocks[ $index ] ) ) {
			return [ 'ok' => false, 'error' => 'index out of range' ];
		}
		$lib = new RT_Block_Library();
		$slug = $lib->normalize_slug( $slug );
		if ( $slug === '' ) {
			return [ 'ok' => false, 'error' => 'invalid slug' ];
		}
		$raw = (string) $blocks[ $index ]['raw'];
		$saved = $lib->save( $slug, $title, $raw );
		if ( ! $saved['ok'] ) {
			return [ 'ok' => false, 'error' => $saved['error'] ?? 'could not save library item' ];
		}
		if ( $replace_with_include ) {
			$blocks[ $index ]['raw'] = sprintf( '<Include slug="%s" />', $slug );
			$saved_state = $this->save_blocks( $rel_path, $blocks );
			if ( ! $saved_state['ok'] ) return $saved_state;
			$saved_state['library_slug'] = $slug;
			return $saved_state;
		}
		return [ 'ok' => true, 'path' => $rel_path, 'blocks' => $blocks, 'library_slug' => $slug ];
	}

	/**
	 * Replace `<Include slug="X"/>` at $index with the resolved raw content of
	 * the library item — breaking the sync (the destination becomes independent).
	 */
	public function break_include( string $rel_path, int $index ): array {
		$state = $this->load( $rel_path );
		if ( ! $state['ok'] ) {
			return $state;
		}
		$blocks = $state['blocks'];
		if ( ! isset( $blocks[ $index ] ) ) {
			return [ 'ok' => false, 'error' => 'index out of range' ];
		}
		$row = $blocks[ $index ];
		if ( ( $row['tag'] ?? '' ) !== 'Include' ) {
			return [ 'ok' => false, 'error' => 'not an Include block' ];
		}
		$slug = (string) ( $row['attrs']['slug'] ?? '' );
		$lib  = new RT_Block_Library();
		$item = $lib->get_parsed( $slug );
		if ( $item === null ) {
			return [ 'ok' => false, 'error' => 'library item not found' ];
		}
		$blocks[ $index ]['raw'] = trim( $item['body'] );
		return $this->save_blocks( $rel_path, $blocks );
	}

	/**
	 * Insert an `<Include slug="X"/>` reference at $position. Validates that
	 * the library item exists.
	 */
	public function insert_include( string $rel_path, int $position, string $slug ): array {
		$lib  = new RT_Block_Library();
		$slug = $lib->normalize_slug( $slug );
		if ( ! $lib->exists( $slug ) ) {
			return [ 'ok' => false, 'error' => 'library item not found' ];
		}
		$raw = sprintf( '<Include slug="%s" />', $slug );
		return $this->insert( $rel_path, $position, 'Markdown', $raw );
	}

	/**
	 * Insert a copy of the library item's body at $position (independent).
	 */
	public function insert_library_copy( string $rel_path, int $position, string $slug ): array {
		$lib  = new RT_Block_Library();
		$item = $lib->get_parsed( $slug );
		if ( $item === null ) {
			return [ 'ok' => false, 'error' => 'library item not found' ];
		}
		return $this->insert( $rel_path, $position, 'Markdown', trim( $item['body'] ) );
	}

	/* --------------------------------------------------------------- Helpers */

	private function abs( string $rel_path ): string {
		$rel = ltrim( str_replace( '\\', '/', $rel_path ), '/' );
		return trailingslashit( RT_THEME_DIR ) . 'content/' . $rel;
	}

	/** @param array<int,array<string,mixed>> $blocks */
	private function push_text_chunks( string $chunk, array &$blocks ): void {
		$lines = preg_split( '/\R{2,}/', trim( $chunk ) ) ?: [];
		foreach ( $lines as $piece ) {
			$piece = trim( $piece );
			if ( $piece === '' ) {
				continue;
			}
			if ( preg_match( self::SHORTCODE_LINE, $piece ) ) {
				$blocks[] = [
					'type'  => self::TYPE_SHORTCODE,
					'tag'   => null,
					'attrs' => [],
					'inner' => '',
					'raw'   => $piece,
				];
				continue;
			}
			$blocks[] = [
				'type'  => self::TYPE_MARKDOWN,
				'tag'   => null,
				'attrs' => [],
				'inner' => '',
				'raw'   => $piece,
			];
		}
	}

	/** @return array<string,string> */
	private function parse_attrs( string $raw ): array {
		$attrs = [];
		if ( preg_match_all( '/([A-Za-z_][A-Za-z0-9_-]*)\s*=\s*"([^"]*)"/', $raw, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $a ) {
				$attrs[ $a[1] ] = $a[2];
			}
		}
		return $attrs;
	}

	private function bump_id( string $raw ): string {
		$suffix = substr( (string) wp_generate_password( 6, false, false ), 0, 6 );
		return (string) preg_replace_callback(
			'/(\bid\s*=\s*")([^"]*)(")/',
			static function ( array $m ) use ( $suffix ): string {
				$base = preg_replace( '/-(?:NEW|copy|[a-z0-9]{6})$/', '', $m[2] ) ?? $m[2];
				return $m[1] . $base . '-' . $suffix . $m[3];
			},
			$raw,
			1
		);
	}

	/** @param array<string,mixed> $b */
	private function preview_for( array $b ): string {
		$type = (string) $b['type'];
		if ( $type === self::TYPE_COMPONENT ) {
			$tag   = (string) ( $b['tag'] ?? '' );
			$id    = (string) ( ( $b['attrs']['id'] ?? '' ) );
			$title = (string) ( ( $b['attrs']['title'] ?? '' ) );
			$inner = wp_strip_all_tags( (string) ( $b['inner'] ?? '' ) );
			$inner = preg_replace( '/\s+/', ' ', $inner ) ?? $inner;
			$inner = mb_substr( trim( $inner ), 0, 90 );
			$bits  = array_filter( [ $title !== '' ? '"' . $title . '"' : '', $inner ] );
			return $tag . ( $id !== '' ? ' #' . $id : '' ) . ( $bits ? ' — ' . implode( ' · ', $bits ) : '' );
		}
		if ( $type === self::TYPE_SHORTCODE ) {
			return 'Shortcode: ' . mb_substr( (string) $b['raw'], 0, 80 );
		}
		$txt = wp_strip_all_tags( (string) $b['raw'] );
		$txt = preg_replace( '/\s+/', ' ', $txt ) ?? $txt;
		return mb_substr( trim( $txt ), 0, 110 );
	}
}
