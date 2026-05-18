<?php
/**
 * Sitemap registration for rt_page and hreflang output for translated entries.
 *
 * - Adds rt_page to the WP core sitemap provider.
 * - Emits <link rel="alternate" hreflang="..."> on singular views by matching
 *   slug across known languages stored in `_rt_lang`. If Polylang is active,
 *   defers to Polylang's own hreflang (we don't duplicate).
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class RT_Sitemap {

	public function register(): void {
		add_filter( 'wp_sitemaps_post_types', [ $this, 'include_rt_page' ] );
		add_action( 'wp_head', [ $this, 'emit_hreflang' ], 6 );
	}

	/**
	 * @param array<string,WP_Post_Type> $types
	 * @return array<string,WP_Post_Type>
	 */
	public function include_rt_page( array $types ): array {
		if ( isset( $types[ RT_CPT_Page::POST_TYPE ] ) ) {
			return $types;
		}
		$obj = get_post_type_object( RT_CPT_Page::POST_TYPE );
		if ( $obj instanceof WP_Post_Type && $obj->public ) {
			$types[ RT_CPT_Page::POST_TYPE ] = $obj;
		}
		return $types;
	}

	public function emit_hreflang(): void {
		if ( ! is_singular() ) {
			return;
		}
		if ( function_exists( 'pll_the_languages' ) || function_exists( 'icl_get_languages' ) ) {
			return; // Polylang/WPML emit their own.
		}
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		$post_types = [ 'page', RT_CPT_Page::POST_TYPE ];
		if ( ! in_array( $post->post_type, $post_types, true ) ) {
			return;
		}
		$current_lang = (string) get_post_meta( $post->ID, '_rt_lang', true );
		if ( $current_lang === '' ) {
			return;
		}
		$siblings = $this->find_siblings( $post );
		if ( ! $siblings ) {
			return;
		}
		$lines = [];
		$lines[] = sprintf( '<link rel="alternate" hreflang="%s" href="%s" />',
			esc_attr( $current_lang ), esc_url( get_permalink( $post ) )
		);
		foreach ( $siblings as $lang => $url ) {
			$lines[] = sprintf( '<link rel="alternate" hreflang="%s" href="%s" />',
				esc_attr( $lang ), esc_url( $url )
			);
		}
		$lines[] = sprintf( '<link rel="alternate" hreflang="x-default" href="%s" />',
			esc_url( get_permalink( $post ) )
		);
		echo "\n" . implode( "\n", $lines ) . "\n";
	}

	/**
	 * Find sibling translations by same slug across other `_rt_lang` values.
	 *
	 * @return array<string,string> language => permalink
	 */
	private function find_siblings( WP_Post $post ): array {
		$slug = (string) $post->post_name;
		if ( $slug === '' ) {
			return [];
		}
		$current_lang = (string) get_post_meta( $post->ID, '_rt_lang', true );

		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT p.ID, m.meta_value AS lang
			   FROM {$wpdb->posts} p
			   JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
			  WHERE p.post_name = %s
			    AND p.post_status = 'publish'
			    AND p.post_type IN ('page', %s)
			    AND p.ID <> %d
			    AND m.meta_value <> %s
			  LIMIT 50",
			'_rt_lang',
			$slug,
			RT_CPT_Page::POST_TYPE,
			(int) $post->ID,
			$current_lang
		) );
		$out = [];
		if ( ! is_array( $rows ) ) {
			return $out;
		}
		foreach ( $rows as $r ) {
			$lang = (string) $r->lang;
			if ( $lang === '' || isset( $out[ $lang ] ) ) {
				continue;
			}
			$out[ $lang ] = (string) get_permalink( (int) $r->ID );
		}
		return $out;
	}
}
