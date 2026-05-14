<?php
/**
 * MDX parser — frontmatter (YAML-lite) + body to Gutenberg blocks.
 *
 * Intentionally minimal: supports the subset we need. Component tags <Hero>, <Stats>, <CTA>
 * map to registered patterns. Plain markdown maps to core blocks.
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class RT_MDX_Parser {

	/**
	 * @return array{ frontmatter: array<string,mixed>, body: string }
	 */
	public static function split( string $raw ): array {
		$raw = ltrim( $raw );
		if ( ! str_starts_with( $raw, '---' ) ) {
			return [ 'frontmatter' => [], 'body' => $raw ];
		}
		$end = strpos( $raw, "\n---", 3 );
		if ( $end === false ) {
			return [ 'frontmatter' => [], 'body' => $raw ];
		}
		$front = substr( $raw, 3, $end - 3 );
		$body  = ltrim( substr( $raw, $end + 4 ) );
		return [
			'frontmatter' => self::parse_yaml_lite( $front ),
			'body'        => $body,
		];
	}

	/**
	 * Tiny YAML subset: key: value, nested 1 level, lists [a, b], strings quoted/plain.
	 * Good enough for our frontmatter; for more, drop in symfony/yaml later.
	 *
	 * @return array<string,mixed>
	 */
	public static function parse_yaml_lite( string $yaml ): array {
		$out   = [];
		$stack = [ &$out ];
		$indents = [ 0 ];
		$lines = preg_split( '/\R/', $yaml ) ?: [];
		foreach ( $lines as $line ) {
			if ( trim( $line ) === '' || str_starts_with( ltrim( $line ), '#' ) ) {
				continue;
			}
			$indent = strlen( $line ) - strlen( ltrim( $line ) );
			while ( count( $indents ) > 1 && $indent <= end( $indents ) ) {
				array_pop( $stack );
				array_pop( $indents );
			}
			$trim = trim( $line );
			if ( ! preg_match( '/^([A-Za-z0-9_\-]+)\s*:\s*(.*)$/', $trim, $m ) ) {
				continue;
			}
			$key = $m[1];
			$val = trim( $m[2] );
			$current = &$stack[ count( $stack ) - 1 ];
			if ( $val === '' ) {
				$current[ $key ] = [];
				$stack[] = &$current[ $key ];
				$indents[] = $indent;
				continue;
			}
			$current[ $key ] = self::cast_scalar( $val );
		}
		return $out;
	}

	/** @return mixed */
	private static function cast_scalar( string $v ) {
		if ( preg_match( '/^"(.*)"$/', $v, $m ) || preg_match( "/^'(.*)'$/", $v, $m ) ) {
			return $m[1];
		}
		if ( preg_match( '/^\[(.*)\]$/', $v, $m ) ) {
			return array_map( 'trim', array_map( static fn( $s ) => trim( $s, "\"' " ), explode( ',', $m[1] ) ) );
		}
		if ( $v === 'true' )  return true;
		if ( $v === 'false' ) return false;
		if ( $v === 'null' )  return null;
		if ( is_numeric( $v ) ) return $v + 0;
		return $v;
	}

	/**
	 * Convert MDX body to block markup.
	 * Component tags <Pattern name="..."> render the registered pattern.
	 * Markdown body is wrapped as core/freeform-friendly via a simple converter.
	 */
	public static function body_to_blocks( string $body ): string {
		$out = '';
		// Extract <Component ...>...</Component> top-level blocks.
		$body = preg_replace_callback(
			'/<([A-Z][A-Za-z0-9]*)([^>]*)>([\s\S]*?)<\/\1>/m',
			static function ( array $m ) {
				$tag   = $m[1];
				$attrs = self::parse_jsx_attrs( $m[2] );
				$inner = trim( $m[3] );
				return "\n<!-- rt:component {$tag} " . wp_json_encode( $attrs ) . " -->\n" . $inner . "\n<!-- /rt:component -->\n";
			},
			$body
		) ?? $body;

		// Self-closing components.
		$body = preg_replace_callback(
			'/<([A-Z][A-Za-z0-9]*)([^>]*)\/>/m',
			static function ( array $m ) {
				$tag   = $m[1];
				$attrs = self::parse_jsx_attrs( $m[2] );
				return "\n<!-- rt:component {$tag} " . wp_json_encode( $attrs ) . " -->\n<!-- /rt:component -->\n";
			},
			$body
		) ?? $body;

		// Lightweight markdown → blocks (headings, paragraphs, lists).
		$lines = preg_split( '/\R/', $body ) ?: [];
		$buffer = '';
		$flush = static function () use ( &$buffer, &$out ): void {
			$p = trim( $buffer );
			$buffer = '';
			if ( $p === '' ) return;
			if ( str_starts_with( $p, '<!--' ) ) {
				$out .= $p . "\n";
				return;
			}
			$out .= "<!-- wp:paragraph -->\n<p>" . self::inline_md( $p ) . "</p>\n<!-- /wp:paragraph -->\n";
		};
		foreach ( $lines as $line ) {
			if ( preg_match( '/^(#{1,6})\s+(.+)$/', $line, $h ) ) {
				$flush();
				$level = strlen( $h[1] );
				$text  = self::inline_md( trim( $h[2] ) );
				$out  .= "<!-- wp:heading {\"level\":{$level}} -->\n<h{$level} class=\"wp-block-heading\">{$text}</h{$level}>\n<!-- /wp:heading -->\n";
				continue;
			}
			if ( preg_match( '/^[-*]\s+(.+)$/', $line ) ) {
				$buffer .= $line . "\n";
				continue;
			}
			if ( trim( $line ) === '' ) {
				if ( str_contains( $buffer, "\n- " ) || str_starts_with( ltrim( $buffer ), '- ' ) || str_starts_with( ltrim( $buffer ), '* ' ) ) {
					$items = '';
					foreach ( preg_split( '/\R/', trim( $buffer ) ) ?: [] as $li ) {
						if ( preg_match( '/^[-*]\s+(.+)$/', $li, $m ) ) {
							$items .= '<li>' . self::inline_md( $m[1] ) . "</li>\n";
						}
					}
					$buffer = '';
					$out   .= "<!-- wp:list -->\n<ul>\n{$items}</ul>\n<!-- /wp:list -->\n";
					continue;
				}
				$flush();
				continue;
			}
			$buffer .= $line . ' ';
		}
		$flush();
		return $out;
	}

	private static function inline_md( string $text ): string {
		$text = esc_html( $text );
		$text = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text ) ?? $text;
		$text = preg_replace( '/\*(.+?)\*/', '<em>$1</em>', $text ) ?? $text;
		$text = preg_replace( '/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $text ) ?? $text;
		return $text;
	}

	/** @return array<string,mixed> */
	private static function parse_jsx_attrs( string $raw ): array {
		$attrs = [];
		preg_match_all( '/([A-Za-z_][A-Za-z0-9_]*)\s*=\s*"([^"]*)"/', $raw, $m, PREG_SET_ORDER );
		foreach ( $m as $a ) {
			$attrs[ $a[1] ] = $a[2];
		}
		return $attrs;
	}

	/** Render an array (frontmatter + body) back to MDX text. */
	public static function compose( array $frontmatter, string $body ): string {
		$yaml = self::dump_yaml_lite( $frontmatter );
		return "---\n{$yaml}---\n\n" . trim( $body ) . "\n";
	}

	/** @param array<string,mixed> $data */
	private static function dump_yaml_lite( array $data, int $indent = 0 ): string {
		$pad = str_repeat( '  ', $indent );
		$out = '';
		foreach ( $data as $k => $v ) {
			if ( is_array( $v ) ) {
				if ( array_is_list( $v ) ) {
					$out .= "{$pad}{$k}: [" . implode( ', ', array_map( static fn( $x ) => is_string( $x ) ? "\"{$x}\"" : (string) $x, $v ) ) . "]\n";
				} else {
					$out .= "{$pad}{$k}:\n" . self::dump_yaml_lite( $v, $indent + 1 );
				}
				continue;
			}
			if ( is_bool( $v ) ) { $out .= "{$pad}{$k}: " . ( $v ? 'true' : 'false' ) . "\n"; continue; }
			if ( is_null( $v ) )  { $out .= "{$pad}{$k}: null\n"; continue; }
			if ( is_numeric( $v ) ) { $out .= "{$pad}{$k}: {$v}\n"; continue; }
			$out .= "{$pad}{$k}: \"" . str_replace( '"', '\\"', (string) $v ) . "\"\n";
		}
		return $out;
	}
}
