<?php
/**
 * Elementor importer — converts Elementor data on a post into MDX.
 * Best-effort: known widgets map to components; unknown ones become <Content> blocks.
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class RT_Elementor_Importer {

	private const WIDGET_MAP = [
		'heading'      => 'Hero',
		'text-editor'  => 'Content',
		'button'       => 'CTA',
		'icon-box'     => 'Features',
		'image'        => 'Content',
		'testimonial'  => 'Testimonials',
		'price-table'  => 'Pricing',
		'accordion'    => 'FAQ',
		'toggle'       => 'FAQ',
	];

	/**
	 * @return array{ ok: bool, path?: string, mdx?: string, error?: string, review_needed?: bool }
	 */
	public function import( int $post_id, string $lang = 'es' ): array {
		$post = get_post( $post_id );
		if ( ! $post ) return [ 'ok' => false, 'error' => 'Post not found' ];

		$elementor = get_post_meta( $post_id, '_elementor_data', true );
		$body = '';
		$review_needed = false;

		if ( $elementor ) {
			$data = is_string( $elementor ) ? json_decode( $elementor, true ) : $elementor;
			if ( is_array( $data ) ) {
				[ $body, $review_needed ] = $this->walk_sections( $data );
			} else {
				$review_needed = true;
				$body = "<Content id=\"imported-content\">\n" . wp_strip_all_tags( apply_filters( 'the_content', $post->post_content ) ) . "\n</Content>\n";
			}
		} else {
			// Plain Gutenberg / classic.
			$rendered = apply_filters( 'the_content', $post->post_content );
			$body = "<Content id=\"imported-content\">\n" . trim( wp_kses_post( $rendered ) ) . "\n</Content>\n";
			$review_needed = false;
		}

		$slug = $post->post_name ?: sanitize_title( $post->post_title );
		$front = [
			'title'  => $post->post_title,
			'slug'   => $slug,
			'lang'   => $lang,
			'template' => 'page-wide',
			'patterns' => array_values( array_unique( $this->extract_used( $body ) ) ),
			'imported' => true,
			'imported_from' => 'elementor',
			'review_needed' => $review_needed,
			'seo' => [
				'meta_description' => (string) get_post_meta( $post_id, 'rank_math_description', true ),
				'focus_keyword'    => (string) get_post_meta( $post_id, 'rank_math_focus_keyword', true ),
			],
		];

		$sync = new RT_Content_Sync();
		$rel  = $lang . '/imported/' . $slug . '.mdx';
		$abs  = $sync->write_file( $rel, $front, $body );

		return [ 'ok' => true, 'path' => $rel, 'abs' => $abs, 'review_needed' => $review_needed ];
	}

	/** @return array{0: string, 1: bool} body + review flag */
	private function walk_sections( array $sections ): array {
		$body = '';
		$review = false;
		$i = 0;
		foreach ( $sections as $section ) {
			$i++;
			$type = $this->guess_section_type( $section );
			$inner = $this->extract_text( $section );
			if ( $type === 'unknown' ) {
				$review = true;
				$body .= "<!-- TODO: review imported section -->\n";
				$body .= "<Content id=\"section-{$i}\">\n{$inner}\n</Content>\n\n";
				continue;
			}
			$body .= "<{$type} id=\"section-{$i}\">\n{$inner}\n</{$type}>\n\n";
		}
		return [ trim( $body ), $review ];
	}

	private function guess_section_type( array $node ): string {
		$elements = (array) ( $node['elements'] ?? [] );
		foreach ( $elements as $e ) {
			$widget = (string) ( $e['widgetType'] ?? '' );
			if ( $widget !== '' && isset( self::WIDGET_MAP[ $widget ] ) ) {
				return self::WIDGET_MAP[ $widget ];
			}
			if ( ! empty( $e['elements'] ) ) {
				$rec = $this->guess_section_type( $e );
				if ( $rec !== 'unknown' ) return $rec;
			}
		}
		return 'unknown';
	}

	private function extract_text( array $node ): string {
		$out = '';
		if ( isset( $node['settings'] ) && is_array( $node['settings'] ) ) {
			foreach ( [ 'title', 'heading', 'editor', 'text', 'description' ] as $k ) {
				if ( ! empty( $node['settings'][ $k ] ) ) {
					$txt = wp_strip_all_tags( (string) $node['settings'][ $k ] );
					if ( $txt !== '' ) $out .= $txt . "\n\n";
				}
			}
		}
		foreach ( (array) ( $node['elements'] ?? [] ) as $child ) {
			$out .= $this->extract_text( $child );
		}
		return trim( $out );
	}

	/** @return array<int,string> */
	private function extract_used( string $body ): array {
		preg_match_all( '/<([A-Z][A-Za-z0-9]+)/', $body, $m );
		return $m[1] ?? [];
	}
}
