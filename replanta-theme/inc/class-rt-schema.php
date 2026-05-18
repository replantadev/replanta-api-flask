<?php
/**
 * JSON-LD schema emitter for singular rt_page / page entries.
 *
 * Emits WebPage + (optional Article) + Organization on singular views.
 * Skipped when an SEO plugin (RankMath/Yoast/AIO) already emits schema.
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class RT_Schema {

	public function register(): void {
		add_action( 'wp_head', [ $this, 'emit' ], 5 );
	}

	public function emit(): void {
		if ( ! is_singular() ) {
			return;
		}
		// Defer to SEO plugins that already emit schema.
		if ( defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) || defined( 'AIOSEO_VERSION' ) ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		$post_types = [ 'page', RT_CPT_Page::POST_TYPE ];
		if ( ! in_array( $post->post_type, $post_types, true ) ) {
			return;
		}
		$site_url   = home_url( '/' );
		$site_name  = get_bloginfo( 'name' );
		$permalink  = (string) get_permalink( $post );
		$title      = get_the_title( $post );
		$excerpt    = trim( wp_strip_all_tags( (string) $post->post_excerpt ) );
		if ( $excerpt === '' ) {
			$excerpt = trim( wp_strip_all_tags( wp_trim_words( (string) $post->post_content, 30, '' ) ) );
		}
		$lang       = (string) get_post_meta( $post->ID, '_rt_lang', true );
		if ( $lang === '' ) {
			$lang = (string) substr( (string) get_locale(), 0, 2 );
		}
		$thumb_id   = (int) get_post_thumbnail_id( $post );
		$image_url  = $thumb_id > 0 ? (string) wp_get_attachment_image_url( $thumb_id, 'full' ) : '';
		if ( $image_url === '' ) {
			$image_url = $this->first_image_from_content( (string) $post->post_content );
		}

		$organization = [
			'@type' => 'Organization',
			'@id'   => $site_url . '#organization',
			'name'  => $site_name,
			'url'   => $site_url,
		];
		$logo = $this->site_logo_url();
		if ( $logo !== '' ) {
			$organization['logo'] = [ '@type' => 'ImageObject', 'url' => $logo ];
		}

		$webpage = [
			'@type'         => 'WebPage',
			'@id'           => $permalink . '#webpage',
			'url'           => $permalink,
			'name'          => $title,
			'inLanguage'    => $lang,
			'isPartOf'      => [ '@id' => $site_url . '#website' ],
			'datePublished' => mysql2date( 'c', $post->post_date_gmt, false ),
			'dateModified'  => mysql2date( 'c', $post->post_modified_gmt, false ),
			'publisher'     => [ '@id' => $site_url . '#organization' ],
		];
		if ( $excerpt !== '' ) {
			$webpage['description'] = $excerpt;
		}
		if ( $image_url !== '' ) {
			$webpage['primaryImageOfPage'] = [ '@type' => 'ImageObject', 'url' => $image_url ];
		}

		$website = [
			'@type'      => 'WebSite',
			'@id'        => $site_url . '#website',
			'url'        => $site_url,
			'name'       => $site_name,
			'inLanguage' => $lang,
			'publisher'  => [ '@id' => $site_url . '#organization' ],
		];

		$graph = [
			'@context' => 'https://schema.org',
			'@graph'   => [ $organization, $website, $webpage ],
		];

		echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
	}

	private function site_logo_url(): string {
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( $logo_id > 0 ) {
			$url = (string) wp_get_attachment_image_url( $logo_id, 'full' );
			if ( $url !== '' ) {
				return $url;
			}
		}
		$opts = (array) get_option( 'rt_theme_settings', [] );
		return isset( $opts['logo_url'] ) ? (string) $opts['logo_url'] : '';
	}

	private function first_image_from_content( string $content ): string {
		if ( $content === '' || strpos( $content, '<img' ) === false ) {
			return '';
		}
		if ( preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $m ) ) {
			return (string) $m[1];
		}
		return '';
	}
}
