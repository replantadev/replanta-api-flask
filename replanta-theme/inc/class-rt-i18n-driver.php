<?php
/**
 * i18n driver — adapter for Polylang (primary) / WPML (future).
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class RT_I18n_Driver {

	public function register(): void {
		add_action( 'replanta/page_synced', [ $this, 'link_translation' ], 20, 3 );
	}

	public function active(): string {
		if ( function_exists( 'pll_set_post_language' ) ) return 'polylang';
		if ( defined( 'ICL_SITEPRESS_VERSION' ) )         return 'wpml';
		return 'none';
	}

	/** @return array<int, array{ slug: string, name: string }> */
	public function languages(): array {
		if ( function_exists( 'pll_languages_list' ) ) {
			$slugs = pll_languages_list( [ 'fields' => 'slug' ] ) ?: [];
			$names = pll_languages_list( [ 'fields' => 'name' ] ) ?: [];
			$out = [];
			foreach ( $slugs as $i => $s ) {
				$out[] = [ 'slug' => (string) $s, 'name' => (string) ( $names[ $i ] ?? $s ) ];
			}
			return $out;
		}
		return [ [ 'slug' => 'es', 'name' => 'Español' ] ];
	}

	/** @param array<string,mixed> $front */
	public function link_translation( int $post_id, array $front, string $lang ): void {
		if ( $this->active() === 'polylang' && function_exists( 'pll_set_post_language' ) ) {
			pll_set_post_language( $post_id, $lang );

			$translations = (array) ( $front['translations'] ?? [] );
			$ids = [ $lang => $post_id ];
			foreach ( $translations as $t_lang => $t_slug ) {
				$post = get_page_by_path( (string) $t_slug, OBJECT, RT_CPT_Page::POST_TYPE );
				if ( $post ) {
					$ids[ (string) $t_lang ] = $post->ID;
				}
			}
			if ( count( $ids ) > 1 && function_exists( 'pll_save_post_translations' ) ) {
				pll_save_post_translations( $ids );
			}
		}
	}
}
