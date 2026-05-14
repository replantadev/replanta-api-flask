<?php
/**
 * Visual + metric diff between an rt_page and its origin URL.
 *
 * Pulls Core Web Vitals and Lighthouse-style scores from the public
 * PageSpeed Insights API (free, no key needed for ~5 req/day; 25k/day with key).
 *
 * Results cached for 1h in a transient keyed by URL+strategy.
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class RT_Promote_Diff {

	private const PSI_ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
	private const CACHE_TTL    = 3600;

	/**
	 * Build the comparison payload for the modal.
	 *
	 * @return array<string,mixed>
	 */
	public function compare( int $rt_page_id, bool $fresh = false, string $strategy = 'mobile' ): array {
		$promote = new RT_Promote();
		$post    = get_post( $rt_page_id );
		if ( ! $post || $post->post_type !== RT_CPT_Page::POST_TYPE ) {
			return [ 'ok' => false, 'error' => 'rt_page not found' ];
		}
		$src = (string) get_post_meta( $rt_page_id, RT_Content_Sync::META_SOURCE_URL, true );
		if ( $src === '' ) {
			$json = (string) get_post_meta( $rt_page_id, RT_Content_Sync::META_FRONTMATTER, true );
			if ( $json !== '' ) {
				$front = json_decode( $json, true );
				if ( is_array( $front ) ) {
					$src = (string) ( $front['source_url'] ?? '' );
				}
			}
		}
		$origin = $src !== '' ? $promote->resolve_origin( $src ) : null;
		$rt_url = (string) get_permalink( $rt_page_id );

		$origin_url = $origin ? (string) $origin['permalink'] : $src;

		$origin_psi = $origin_url !== '' ? $this->psi_score( $origin_url, $strategy, $fresh ) : [ 'ok' => false, 'error' => 'no origin url' ];
		$rt_psi     = $this->psi_score( $rt_url, $strategy, $fresh );

		return [
			'ok'          => true,
			'id'          => $rt_page_id,
			'strategy'    => $strategy,
			'origin_url'  => $origin_url,
			'rt_url'      => $rt_url,
			'origin'      => $origin_psi,
			'rt'          => $rt_psi,
			'delta'       => $this->delta( $origin_psi, $rt_psi ),
		];
	}

	/**
	 * Call PSI API for a URL and normalize the response.
	 *
	 * @return array<string,mixed>
	 */
	public function psi_score( string $url, string $strategy = 'mobile', bool $fresh = false ): array {
		$strategy = $strategy === 'desktop' ? 'desktop' : 'mobile';
		$key_t    = 'rt_psi_' . md5( $url . '|' . $strategy );
		if ( ! $fresh ) {
			$cached = get_transient( $key_t );
			if ( is_array( $cached ) ) {
				$cached['cached'] = true;
				return $cached;
			}
		}

		$settings = (array) get_option( 'rt_theme_settings', [] );
		$api_key  = (string) ( $settings['psi_api_key'] ?? '' );

		$args = [
			'url'      => $url,
			'strategy' => $strategy,
			'category' => [ 'performance', 'seo', 'accessibility', 'best-practices' ],
		];
		// http_build_query collapses arrays; PSI accepts repeated `category` params.
		$qs  = 'url=' . rawurlencode( $url ) . '&strategy=' . $strategy
			. '&category=performance&category=seo&category=accessibility&category=best-practices';
		if ( $api_key !== '' ) {
			$qs .= '&key=' . rawurlencode( $api_key );
		}

		$resp = wp_remote_get( self::PSI_ENDPOINT . '?' . $qs, [ 'timeout' => 60 ] );
		if ( is_wp_error( $resp ) ) {
			return [ 'ok' => false, 'error' => $resp->get_error_message() ];
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		$body = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
		if ( $code !== 200 || ! is_array( $body ) ) {
			$err = is_array( $body ) && isset( $body['error']['message'] ) ? (string) $body['error']['message'] : 'PSI HTTP ' . $code;
			return [ 'ok' => false, 'error' => $err ];
		}

		$normalized = $this->normalize_psi( $body );
		$normalized['ok']     = true;
		$normalized['url']    = $url;
		$normalized['cached'] = false;
		set_transient( $key_t, $normalized, self::CACHE_TTL );
		return $normalized;
	}

	/**
	 * @param array<string,mixed> $body
	 * @return array<string,mixed>
	 */
	private function normalize_psi( array $body ): array {
		$lh   = (array) ( $body['lighthouseResult'] ?? [] );
		$cats = (array) ( $lh['categories'] ?? [] );
		$audits = (array) ( $lh['audits'] ?? [] );

		$score = static function ( array $cats, string $key ): ?int {
			$v = $cats[ $key ]['score'] ?? null;
			return is_numeric( $v ) ? (int) round( $v * 100 ) : null;
		};
		$metric = static function ( array $audits, string $key ): array {
			$a = (array) ( $audits[ $key ] ?? [] );
			return [
				'value'   => isset( $a['numericValue'] ) ? (float) $a['numericValue'] : null,
				'display' => (string) ( $a['displayValue'] ?? '' ),
			];
		};

		$loadingExperience = (array) ( $body['loadingExperience']['metrics'] ?? [] );
		$crux              = static function ( array $exp, string $key ): array {
			$m = (array) ( $exp[ $key ] ?? [] );
			return [
				'percentile' => isset( $m['percentile'] ) ? (float) $m['percentile'] : null,
				'category'   => (string) ( $m['category'] ?? '' ),
			];
		};

		return [
			'scores' => [
				'performance'    => $score( $cats, 'performance' ),
				'seo'            => $score( $cats, 'seo' ),
				'accessibility'  => $score( $cats, 'accessibility' ),
				'best_practices' => $score( $cats, 'best-practices' ),
			],
			'metrics' => [
				'lcp' => $metric( $audits, 'largest-contentful-paint' ),
				'cls' => $metric( $audits, 'cumulative-layout-shift' ),
				'inp' => $metric( $audits, 'interaction-to-next-paint' ),
				'fcp' => $metric( $audits, 'first-contentful-paint' ),
				'tbt' => $metric( $audits, 'total-blocking-time' ),
				'tti' => $metric( $audits, 'interactive' ),
			],
			'crux' => [
				'lcp' => $crux( $loadingExperience, 'LARGEST_CONTENTFUL_PAINT_MS' ),
				'cls' => $crux( $loadingExperience, 'CUMULATIVE_LAYOUT_SHIFT_SCORE' ),
				'inp' => $crux( $loadingExperience, 'INTERACTION_TO_NEXT_PAINT' ),
			],
			'fetched_at' => time(),
		];
	}

	/**
	 * @param array<string,mixed> $a
	 * @param array<string,mixed> $b
	 * @return array<string,int|null>
	 */
	private function delta( array $a, array $b ): array {
		$out = [];
		$as = (array) ( $a['scores'] ?? [] );
		$bs = (array) ( $b['scores'] ?? [] );
		foreach ( [ 'performance', 'seo', 'accessibility', 'best_practices' ] as $k ) {
			$av = isset( $as[ $k ] ) ? $as[ $k ] : null;
			$bv = isset( $bs[ $k ] ) ? $bs[ $k ] : null;
			$out[ $k ] = ( is_int( $av ) && is_int( $bv ) ) ? ( $bv - $av ) : null;
		}
		return $out;
	}
}
