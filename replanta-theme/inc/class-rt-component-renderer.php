<?php
/**
 * Render <!-- rt:component --> placeholders into actual pattern HTML at the_content time.
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class RT_Component_Renderer {

	private const COMPONENT_TO_PATTERN = [
		'Hero'         => 'replanta-theme/hero-eco',
		'Stats'        => 'replanta-theme/stats',
		'Features'     => 'replanta-theme/features',
		'CTA'          => 'replanta-theme/cta',
		'FAQ'          => 'replanta-theme/faq',
		'Pricing'      => 'replanta-theme/pricing',
		'Testimonials' => 'replanta-theme/testimonials',
		'Content'      => 'replanta-theme/content',
	];

	public function register(): void {
		add_filter( 'the_content', [ $this, 'render' ], 9 );
	}

	public function render( string $content ): string {
		if ( ! str_contains( $content, '<!-- rt:component' ) ) return $content;

		// First pass: resolve <Include slug="..."/> references inline by replacing
		// their wrapper with the body of the library item. Done up to 3 levels deep
		// to allow includes-of-includes without risking infinite recursion.
		$content = $this->resolve_includes( $content, 3 );

		return preg_replace_callback(
			'/<!--\s*rt:component\s+([A-Za-z0-9]+)\s+(.+?)\s*-->([\s\S]*?)<!--\s*\/rt:component\s*-->/s',
			function ( array $m ) {
				$tag   = $m[1];
				$attrs = json_decode( $m[2], true ) ?: [];
				$inner = $m[3];

				$pattern_slug = self::COMPONENT_TO_PATTERN[ $tag ] ?? null;
				$id = (string) ( $attrs['id'] ?? '' );

				$wrap_open  = '<section class="rt-component rt-' . esc_attr( strtolower( $tag ) ) . '"' . ( $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . '>';
				$wrap_close = '</section>';

				if ( $pattern_slug && function_exists( 'register_block_pattern' ) ) {
					$registry = WP_Block_Patterns_Registry::get_instance();
					if ( $registry->is_registered( $pattern_slug ) ) {
						$pattern = $registry->get_registered( $pattern_slug );
						$rendered = (string) ( $pattern['content'] ?? '' );
						return $wrap_open . do_blocks( $rendered ) . $this->render_inner( $inner ) . $wrap_close;
					}
				}

				return $wrap_open . $this->render_inner( $inner ) . $wrap_close;
			},
			$content
		) ?? $content;
	}

	/**
	 * Resolve <Include slug="..."/> wrappers by replacing them with the
	 * library item's parsed body (compiled to gutenberg block markup). Done in
	 * up to N passes to allow nested includes.
	 */
	private function resolve_includes( string $content, int $max_depth ): string {
		if ( $max_depth <= 0 ) return $content;
		if ( ! class_exists( 'RT_Block_Library' ) ) return $content;
		$lib = new RT_Block_Library();

		$pattern = '/<!--\s*rt:component\s+Include\s+(.+?)\s*-->([\s\S]*?)<!--\s*\/rt:component\s*-->/s';
		$replaced = false;
		$out = preg_replace_callback(
			$pattern,
			static function ( array $m ) use ( $lib, &$replaced ): string {
				$attrs = json_decode( $m[1], true ) ?: [];
				$slug  = (string) ( $attrs['slug'] ?? '' );
				if ( $slug === '' ) {
					return '<!-- rt:include error: missing slug -->';
				}
				$item = $lib->get_parsed( $slug );
				if ( $item === null ) {
					return '<div class="rt-include-missing" style="padding:1rem;border:1px dashed #b91c1c;color:#b91c1c;border-radius:.5rem">'
						. esc_html( sprintf( /* translators: %s: library slug */ __( 'Bloque de biblioteca no encontrado: %s', 'replanta-theme' ), $slug ) )
						. '</div>';
				}
				$replaced = true;
				$body = $item['body'];
				return RT_MDX_Parser::body_to_blocks( $body );
			},
			$content
		);
		$content = $out ?? $content;
		// If we replaced something, recurse so includes-of-includes work.
		if ( $replaced ) {
			return $this->resolve_includes( $content, $max_depth - 1 );
		}
		return $content;
	}

	private function render_inner( string $inner ): string {
		$inner = trim( $inner );
		if ( $inner === '' ) return '';
		// Inner already contains gutenberg block markup (built by parser).
		return do_blocks( $inner );
	}
}
