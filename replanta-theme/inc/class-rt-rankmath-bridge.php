<?php
/**
 * RankMath bridge — sync frontmatter SEO into RankMath meta keys, schema, internal links suggestions.
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class RT_RankMath_Bridge {

	public function register(): void {
		add_action( 'replanta/page_synced', [ $this, 'sync_seo' ], 10, 3 );
	}

	/** @param array<string,mixed> $front */
	public function sync_seo( int $post_id, array $front, string $lang ): void {
		$seo = (array) ( $front['seo'] ?? [] );

		if ( ! empty( $seo['meta_description'] ) ) {
			update_post_meta( $post_id, 'rank_math_description', sanitize_text_field( (string) $seo['meta_description'] ) );
		}
		if ( ! empty( $seo['meta_title'] ) ) {
			update_post_meta( $post_id, 'rank_math_title', sanitize_text_field( (string) $seo['meta_title'] ) );
		}
		if ( ! empty( $seo['focus_keyword'] ) ) {
			update_post_meta( $post_id, 'rank_math_focus_keyword', sanitize_text_field( (string) $seo['focus_keyword'] ) );
		}
		if ( ! empty( $seo['canonical'] ) ) {
			update_post_meta( $post_id, 'rank_math_canonical_url', esc_url_raw( (string) $seo['canonical'] ) );
		}
		if ( ! empty( $seo['robots'] ) ) {
			update_post_meta( $post_id, 'rank_math_robots', (array) $seo['robots'] );
		}

		// Schema (Article / Service / FAQ).
		$schema_type = (string) ( $seo['schema'] ?? 'Article' );
		$this->apply_schema( $post_id, $schema_type, $front );
	}

	/** @param array<string,mixed> $front */
	private function apply_schema( int $post_id, string $type, array $front ): void {
		$schema = [
			'@type'           => $type,
			'metadata'        => [ 'title' => 'replanta-' . sanitize_title( (string) ( $front['title'] ?? '' ) ), 'type' => 'template' ],
			'name'            => (string) ( $front['title'] ?? '' ),
			'description'     => (string) ( $front['seo']['meta_description'] ?? '' ),
		];
		update_post_meta( $post_id, 'rank_math_schema_' . $type, $schema );
	}

	/**
	 * Suggest internal links by matching focus keywords/titles against $slug.
	 *
	 * @return array<int, array{ slug: string, title: string, score: int }>
	 */
	public function suggest_internal_links( string $slug, string $lang, int $limit = 5 ): array {
		$sync = new RT_Content_Sync();
		$files = $sync->list_files();
		$source = null;
		foreach ( $files as $f ) {
			if ( ( $f['slug'] ?? '' ) === $slug && $f['lang'] === $lang ) {
				$source = $f;
				break;
			}
		}
		if ( ! $source ) return [];

		$source_words = array_filter( preg_split( '/\W+/', strtolower( (string) $source['title'] ) ) ?: [] );
		$out = [];
		foreach ( $files as $f ) {
			if ( $f['lang'] !== $lang ) continue;
			if ( ( $f['slug'] ?? '' ) === $slug ) continue;
			$cand = strtolower( (string) $f['title'] );
			$score = 0;
			foreach ( $source_words as $w ) {
				if ( strlen( $w ) > 3 && str_contains( $cand, $w ) ) $score++;
			}
			if ( $score > 0 ) {
				$out[] = [ 'slug' => (string) $f['slug'], 'title' => (string) $f['title'], 'score' => $score ];
			}
		}
		usort( $out, static fn( $a, $b ) => $b['score'] <=> $a['score'] );
		return array_slice( $out, 0, $limit );
	}
}
