<?php
/**
 * WP-CLI commands.
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

final class RT_CLI {

	/**
	 * Sync /content files into rt_page CPT.
	 *
	 * ## EXAMPLES
	 *     wp replanta sync
	 */
	public function sync( $args, $assoc ): void { // phpcs:ignore
		$sync = new RT_Content_Sync();
		$r = $sync->sync_all();
		WP_CLI::log( sprintf( 'scanned=%d created=%d updated=%d skipped=%d errors=%d',
			$r['scanned'], $r['created'] ?? 0, $r['updated'] ?? 0, $r['skipped'] ?? 0, count( $r['errors'] )
		) );
		foreach ( $r['errors'] as $e ) {
			WP_CLI::warning( $e );
		}
	}

	/**
	 * Generate a page using AI.
	 *
	 * ## OPTIONS
	 * <prompt>
	 * : Description of the page.
	 *
	 * [--lang=<lang>]
	 * : Target language slug.
	 *
	 * [--slug=<slug>]
	 * : URL slug.
	 *
	 * ## EXAMPLES
	 *     wp replanta generate "Landing for carbon audit service" --lang=es --slug=auditoria
	 */
	public function generate( $args, $assoc ): void { // phpcs:ignore
		$prompt = (string) ( $args[0] ?? '' );
		$lang   = (string) ( $assoc['lang'] ?? 'es' );
		$slug   = (string) ( $assoc['slug'] ?? '' );
		if ( $prompt === '' ) {
			WP_CLI::error( 'Prompt required.' );
		}
		$gen = new RT_Page_Generator();
		$res = $gen->generate_page( $prompt, $lang, $slug ?: null );
		if ( ! $res['ok'] ) {
			WP_CLI::error( $res['error'] ?? 'Unknown error' );
		}
		WP_CLI::success( 'Created: ' . $res['path'] );
	}

	/**
	 * Import an Elementor post into MDX.
	 *
	 * ## OPTIONS
	 * --post=<id>
	 * : Post ID to import.
	 *
	 * [--lang=<lang>]
	 * : Language slug. Default: es.
	 *
	 * ## EXAMPLES
	 *     wp replanta import-elementor --post=123 --lang=es
	 */
	public function import_elementor( $args, $assoc ): void { // phpcs:ignore
		$id   = (int) ( $assoc['post'] ?? 0 );
		$lang = (string) ( $assoc['lang'] ?? 'es' );
		if ( $id <= 0 ) {
			WP_CLI::error( '--post=<id> required.' );
		}
		$importer = new RT_Elementor_Importer();
		$res = $importer->import( $id, $lang );
		if ( ! $res['ok'] ) {
			WP_CLI::error( $res['error'] ?? 'Unknown error' );
		}
		WP_CLI::success( 'Imported to: ' . $res['path'] . ' (review needed: ' . ( $res['review_needed'] ? 'yes' : 'no' ) . ')' );
	}

	/**
	 * Clone a live URL into an rt_page (Mirror mode).
	 *
	 * ## OPTIONS
	 * <url>
	 * : Source URL to clone.
	 *
	 * [--slug=<slug>]
	 * : Destination slug. Defaults to last path segment of URL.
	 *
	 * [--lang=<lang>]
	 * : Language meta. Default es.
	 *
	 * ## EXAMPLES
	 *     wp replanta mirror https://replanta.net/alojamiento --lang=es
	 */
	public function mirror( $args, $assoc ): void { // phpcs:ignore
		$url = (string) ( $args[0] ?? '' );
		if ( $url === '' ) {
			WP_CLI::error( 'URL required.' );
		}
		$slug = (string) ( $assoc['slug'] ?? '' );
		$lang = (string) ( $assoc['lang'] ?? 'es' );
		$res  = ( new RT_Mirror_Importer() )->import( $url, $slug !== '' ? $slug : null, $lang );
		if ( empty( $res['ok'] ) ) {
			WP_CLI::error( (string) ( $res['error'] ?? 'mirror failed' ) );
		}
		WP_CLI::success( sprintf( 'id=%d slug=%s css=%d img=%d url=%s',
			(int) $res['id'],
			(string) $res['slug'],
			(int) ( $res['css_count'] ?? 0 ),
			(int) ( $res['img_count'] ?? 0 ),
			(string) $res['url']
		) );
	}

	/**
	 * Refresh an existing Mirror (re-fetch source, rebuild assets).
	 *
	 * ## OPTIONS
	 * <id>
	 * : Post ID of the mirrored rt_page or page.
	 *
	 * ## EXAMPLES
	 *     wp replanta mirror-refresh 1234
	 */
	public function mirror_refresh( $args, $assoc ): void { // phpcs:ignore
		$id = (int) ( $args[0] ?? 0 );
		if ( $id <= 0 ) {
			WP_CLI::error( 'ID required.' );
		}
		$post = get_post( $id );
		if ( ! $post ) {
			WP_CLI::error( 'post not found' );
		}
		$src = (string) get_post_meta( $id, RT_Mirror_Importer::META_SOURCE_URL, true );
		if ( $src === '' ) {
			WP_CLI::error( 'not a mirror' );
		}
		$lang = (string) get_post_meta( $id, '_rt_lang', true );
		if ( $lang === '' ) {
			$lang = 'es';
		}
		RT_Mirror_Cleanup::purge_slug( (string) $post->post_name );
		$res = ( new RT_Mirror_Importer() )->import( $src, (string) $post->post_name, $lang );
		if ( empty( $res['ok'] ) ) {
			WP_CLI::error( (string) ( $res['error'] ?? 'refresh failed' ) );
		}
		WP_CLI::success( sprintf( 'refreshed id=%d css=%d img=%d',
			$id,
			(int) ( $res['css_count'] ?? 0 ),
			(int) ( $res['img_count'] ?? 0 )
		) );
	}

	/**
	 * Adopt an rt_page (swap with origin slug, demote, rewrite links).
	 *
	 * ## OPTIONS
	 * <id>
	 * : rt_page post ID.
	 *
	 * [--no-demote]
	 * : Keep the origin published (default is demote to draft).
	 *
	 * [--no-rewrite]
	 * : Skip rewriting internal links in other posts.
	 *
	 * [--no-menus]
	 * : Skip replacing references in nav menus.
	 *
	 * [--set-front]
	 * : Set the adopted page as static front page.
	 *
	 * ## EXAMPLES
	 *     wp replanta adopt 1234
	 *     wp replanta adopt 1234 --no-demote --set-front
	 */
	public function adopt( $args, $assoc ): void { // phpcs:ignore
		$id = (int) ( $args[0] ?? 0 );
		if ( $id <= 0 ) {
			WP_CLI::error( 'ID required.' );
		}
		$opts = [
			'demote'        => ! isset( $assoc['no-demote'] ),
			'rewrite_links' => ! isset( $assoc['no-rewrite'] ),
			'replace_menus' => ! isset( $assoc['no-menus'] ),
			'set_front'     => isset( $assoc['set-front'] ),
		];
		$res = ( new RT_Promote() )->adopt_url( $id, $opts );
		if ( empty( $res['ok'] ) ) {
			WP_CLI::error( (string) ( $res['error'] ?? 'adopt failed' ) );
		}
		WP_CLI::success( sprintf( 'adopted id=%d → %s (page_id=%d) menus=%d posts=%d links=%d',
			$id,
			(string) $res['new_url'],
			(int) ( $res['promoted_page_id'] ?? 0 ),
			(int) ( $res['menus_changed'] ?? 0 ),
			(int) ( $res['posts_rewritten'] ?? 0 ),
			(int) ( $res['links_rewritten'] ?? 0 )
		) );
	}

	/**
	 * Undo a previous adopt for an rt_page.
	 *
	 * ## OPTIONS
	 * <id>
	 * : rt_page post ID.
	 *
	 * ## EXAMPLES
	 *     wp replanta undo-adopt 1234
	 */
	public function undo_adopt( $args, $assoc ): void { // phpcs:ignore
		$id = (int) ( $args[0] ?? 0 );
		if ( $id <= 0 ) {
			WP_CLI::error( 'ID required.' );
		}
		$res = ( new RT_Promote() )->undo_adopt( $id );
		if ( empty( $res['ok'] ) ) {
			WP_CLI::error( (string) ( $res['error'] ?? 'undo failed' ) );
		}
		WP_CLI::success( sprintf( 'undone id=%d restored=%d slug=%s',
			$id,
			(int) ( $res['restored_id'] ?? 0 ),
			(string) ( $res['restored_slug'] ?? '' )
		) );
	}

	/**
	 * Run a PSI compare between origin URL and the rt_page candidate URL.
	 *
	 * ## OPTIONS
	 * <id>
	 * : rt_page post ID.
	 *
	 * [--strategy=<s>]
	 * : mobile|desktop. Default mobile.
	 *
	 * [--fresh]
	 * : Bypass the 1h transient cache.
	 *
	 * ## EXAMPLES
	 *     wp replanta diff 1234 --strategy=desktop
	 */
	public function diff( $args, $assoc ): void { // phpcs:ignore
		$id       = (int) ( $args[0] ?? 0 );
		$strategy = (string) ( $assoc['strategy'] ?? 'mobile' );
		$fresh    = isset( $assoc['fresh'] );
		if ( $id <= 0 ) {
			WP_CLI::error( 'ID required.' );
		}
		$res = ( new RT_Promote_Diff() )->compare( $id, $fresh, $strategy );
		if ( empty( $res['ok'] ) ) {
			WP_CLI::error( (string) ( $res['error'] ?? 'diff failed' ) );
		}
		$o = $res['origin']['scores'] ?? [];
		$n = $res['rt']['scores'] ?? [];
		WP_CLI::log( sprintf(
			'origin: perf=%s seo=%s a11y=%s bp=%s',
			$o['performance'] ?? '-', $o['seo'] ?? '-', $o['accessibility'] ?? '-', $o['best_practices'] ?? '-'
		) );
		WP_CLI::log( sprintf(
			'rt:     perf=%s seo=%s a11y=%s bp=%s',
			$n['performance'] ?? '-', $n['seo'] ?? '-', $n['accessibility'] ?? '-', $n['best_practices'] ?? '-'
		) );
		WP_CLI::success( 'compared id=' . $id );
	}
}

WP_CLI::add_command( 'replanta', RT_CLI::class );
