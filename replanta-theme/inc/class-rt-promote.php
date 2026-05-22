<?php
/**
 * Promote: convert an imported rt_page into the canonical page of the site.
 *
 * Pipeline:
 *   1. List candidates (rt_page with _rt_source_url meta).
 *   2. Resolve which existing post (page / Elementor / Doc / ...) lives at that URL.
 *   3. "Adopt URL"        — swap slugs, optionally draft the original.
 *   4. "Replace in menus" — rewrite all nav_menu_items pointing to old → new.
 *   5. "Add 301 redirect" — store {from, to} in option array, served on template_redirect.
 *
 * Non-destructive by default: every action is opt-in and auditable.
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class RT_Promote {

	public const OPTION_REDIRECTS = 'rt_redirects';

	public const META_ORIGIN_ID            = '_rt_promoted_origin_id';
	public const META_ORIGIN_URL           = '_rt_promoted_origin_url';
	public const META_ORIGIN_SLUG          = '_rt_promoted_origin_slug';
	public const META_ORIGIN_STATUS        = '_rt_promoted_origin_status';
	public const META_ORIGIN_ARCHIVED_SLUG = '_rt_promoted_origin_archived_slug';
	public const META_SELF_OLD_SLUG        = '_rt_promoted_self_old_slug';
	public const META_REDIRECT_FROM        = '_rt_promoted_redirect_from';
	public const META_REWRITTEN_POSTS      = '_rt_promoted_rewritten_posts';
	public const META_AT                   = '_rt_promoted_at';
	public const META_PROMOTED_PAGE_ID     = '_rt_promoted_page_id';

	public function register(): void {
		add_action( 'template_redirect', [ $this, 'maybe_redirect' ], 1 );
	}

	/* =========================================================== Discovery */

	/** @return array<int,array<string,mixed>> */
	public function list_candidates(): array {
		$q = new \WP_Query(
			[
				'post_type'      => RT_CPT_Page::POST_TYPE,
				'post_status'    => [ 'publish', 'draft', 'pending' ],
				'posts_per_page' => 200,
				'no_found_rows'  => true,
			]
		);
		$out = [];
		foreach ( $q->posts as $p ) {
			$src = (string) get_post_meta( $p->ID, RT_Content_Sync::META_SOURCE_URL, true );
			if ( $src === '' ) {
				$src = $this->source_from_frontmatter( $p->ID );
			}
			$origin = $src !== '' ? $this->resolve_origin( $src ) : null;
			$out[] = [
				'id'         => $p->ID,
				'title'      => get_the_title( $p ),
				'slug'       => $p->post_name,
				'rt_url'     => get_permalink( $p ),
				'source_url' => $src,
				'origin'     => $origin,
			];
		}
		return $out;
	}

	private function source_from_frontmatter( int $post_id ): string {
		$json = (string) get_post_meta( $post_id, RT_Content_Sync::META_FRONTMATTER, true );
		if ( $json === '' ) {
			return '';
		}
		$front = json_decode( $json, true );
		if ( ! is_array( $front ) ) {
			return '';
		}
		return (string) ( $front['source_url'] ?? '' );
	}

	/**
	 * Try to find which post lives at $url on this site.
	 *
	 * @return array<string,mixed>|null
	 */
	public function resolve_origin( string $url ): ?array {
		if ( $url === '' ) {
			return null;
		}
		$home   = (string) home_url();
		$path   = (string) wp_parse_url( $url, PHP_URL_PATH );
		$path   = '/' . trim( (string) $path, '/' );
		$is_local = strpos( $url, $home ) === 0;

		// Try url_to_postid first when the URL is local.
		if ( $is_local ) {
			$pid = url_to_postid( $url );
			if ( $pid > 0 ) {
				return $this->describe_post( $pid );
			}
		}
		// Fallback: match by path slug across pages, posts, custom types.
		$slug = trim( (string) basename( $path ), '/' );
		if ( $slug === '' ) {
			return null;
		}
		$q = new \WP_Query(
			[
				'name'           => $slug,
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			]
		);
		if ( ! empty( $q->posts ) ) {
			return $this->describe_post( $q->posts[0]->ID );
		}
		return null;
	}

	/** @return array<string,mixed> */
	private function describe_post( int $id ): array {
		$p = get_post( $id );
		return [
			'id'        => $id,
			'title'     => $p ? get_the_title( $p ) : '',
			'slug'      => $p ? $p->post_name : '',
			'type'      => $p ? $p->post_type : '',
			'status'    => $p ? $p->post_status : '',
			'parent'    => $p ? (int) $p->post_parent : 0,
			'permalink' => get_permalink( $id ),
		];
	}

	/* =============================================================== Adopt */

	/**
	 * Swap slugs and (optionally) demote, replace menus, rewrite internal links, set front page.
	 *
	 * Accepted opts:
	 *   demote         (bool, default true)  Send original to draft with -archived-XXXX slug.
	 *   replace_menus  (bool, default true)  Reassign nav_menu_items pointing to original.
	 *   rewrite_links  (bool, default true)  Rewrite <a href> in post_content for all posts.
	 *   set_front      (bool, default false) Set the rt_page as static front page.
	 *
	 * Backwards compatible: if $opts is bool it is treated as the legacy `demote` flag.
	 *
	 * @param int                $rt_page_id
	 * @param array<string,bool>|bool $opts
	 * @return array<string,mixed>
	 */
	public function adopt_url( int $rt_page_id, $opts = [] ): array {
		if ( is_bool( $opts ) ) {
			$opts = [ 'demote' => $opts ];
		}
		$opts = array_merge( [
			'demote'        => true,
			'replace_menus' => true,
			'rewrite_links' => true,
			'set_front'     => false,
		], (array) $opts );

		$rt_page = get_post( $rt_page_id );
		if ( ! $rt_page || $rt_page->post_type !== RT_CPT_Page::POST_TYPE ) {
			return [ 'ok' => false, 'error' => 'rt_page not found' ];
		}
		if ( (string) get_post_meta( $rt_page_id, self::META_AT, true ) !== '' ) {
			return [ 'ok' => false, 'error' => 'already promoted' ];
		}
		$src = (string) get_post_meta( $rt_page_id, RT_Content_Sync::META_SOURCE_URL, true );
		if ( $src === '' ) {
			$src = $this->source_from_frontmatter( $rt_page_id );
		}
		$origin = $this->resolve_origin( $src );
		if ( ! $origin ) {
			return [ 'ok' => false, 'error' => 'origin url not resolvable on this site' ];
		}
		$origin_id   = (int) $origin['id'];
		$old_url     = (string) $origin['permalink'];
		$old_slug    = (string) $origin['slug'];
		$old_status  = (string) $origin['status'];
		$rt_old_url  = (string) get_permalink( $rt_page_id );
		$rt_old_slug = (string) $rt_page->post_name;

		$archived_slug = '';
		if ( ! empty( $opts['demote'] ) ) {
			$archived_slug = $old_slug . '-archived-' . wp_generate_password( 4, false, false );
			wp_update_post( [
				'ID'          => $origin_id,
				'post_status' => 'draft',
				'post_name'   => $archived_slug,
			] );
		}

		// Swap rt_page to take the original slug.
		wp_update_post( [
			'ID'        => $rt_page_id,
			'post_name' => $old_slug,
		] );
		$new_url = (string) get_permalink( $rt_page_id );

		// Persist undo metadata.
		update_post_meta( $rt_page_id, self::META_ORIGIN_ID, $origin_id );
		update_post_meta( $rt_page_id, self::META_ORIGIN_URL, $old_url );
		update_post_meta( $rt_page_id, self::META_ORIGIN_SLUG, $old_slug );
		update_post_meta( $rt_page_id, self::META_ORIGIN_STATUS, $old_status );
		update_post_meta( $rt_page_id, self::META_ORIGIN_ARCHIVED_SLUG, $archived_slug );
		update_post_meta( $rt_page_id, self::META_SELF_OLD_SLUG, $rt_old_slug );
		update_post_meta( $rt_page_id, self::META_AT, (string) time() );

		// Create native `page` heir so the canonical URL is served by core WP without /rt_page/ prefix.
		$origin_parent = (int) ( $origin['parent'] ?? 0 );
		$page_id = wp_insert_post( [
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $rt_page->post_title !== '' ? $rt_page->post_title : (string) $origin['title'],
			'post_content' => $rt_page->post_content,
			'post_name'    => $old_slug,
			'post_parent'  => $origin_parent,
		], true );
		if ( ! is_wp_error( $page_id ) && (int) $page_id > 0 ) {
			$page_id = (int) $page_id;
			// Keep original template assignment (e.g., Elementor canvas/full-width)
			// so the adopted page preserves container behavior.
			$origin_tpl = (string) get_post_meta( $origin_id, '_wp_page_template', true );
			if ( $origin_tpl !== '' ) {
				update_post_meta( $page_id, '_wp_page_template', $origin_tpl );
			}
			$copy_metas = [
				RT_Mirror_Importer::META_MIRROR,
				RT_Mirror_Importer::META_CSS_FILES,
				RT_Mirror_Importer::META_CSS_BUNDLE,
				RT_Mirror_Importer::META_INLINE_CSS,
				RT_Mirror_Importer::META_FONTS,
				RT_Mirror_Importer::META_IMPORTED_AT,
				RT_Mirror_Importer::META_SOURCE_URL,
				RT_Content_Sync::META_SOURCE_URL,
			];
			foreach ( $copy_metas as $mk ) {
				$mv = get_post_meta( $rt_page_id, $mk, true );
				if ( $mv !== '' && $mv !== false ) {
					update_post_meta( $page_id, $mk, $mv );
				}
			}
			// Demote the rt_page itself so it stops serving /rt_page/{slug}/.
			wp_update_post( [
				'ID'          => $rt_page_id,
				'post_status' => 'draft',
			] );
			update_post_meta( $rt_page_id, self::META_PROMOTED_PAGE_ID, $page_id );
			$new_url = (string) get_permalink( $page_id );
		}

		// Auto-create 301 from the rt_page's old URL → new URL.
		$redirect_from = (string) wp_parse_url( $rt_old_url, PHP_URL_PATH );
		$redirect_to   = (string) wp_parse_url( $new_url, PHP_URL_PATH );
		$redirect_ok   = $this->add_redirect( $redirect_from, $redirect_to );
		if ( $redirect_ok ) {
			update_post_meta( $rt_page_id, self::META_REDIRECT_FROM, '/' . ltrim( $redirect_from, '/' ) );
		}

		$report = [
			'ok'             => true,
			'old_id'         => $origin_id,
			'old_url'        => $old_url,
			'new_url'        => $new_url,
			'demoted'        => (bool) $opts['demote'],
			'menus_changed'  => 0,
			'posts_rewritten'=> 0,
			'links_rewritten'=> 0,
			'front_page_set' => false,
			'promoted_page_id' => isset( $page_id ) && is_int( $page_id ) ? $page_id : 0,
		];

		if ( ! empty( $opts['replace_menus'] ) ) {
			$menus = $this->replace_in_menus( $rt_page_id, $old_url );
			$report['menus_changed'] = (int) ( $menus['count'] ?? 0 );
		}

		if ( ! empty( $opts['rewrite_links'] ) ) {
			$rw = $this->rewrite_internal_links( $rt_page_id, $old_url, $new_url );
			$report['posts_rewritten'] = (int) ( $rw['posts'] ?? 0 );
			$report['links_rewritten'] = (int) ( $rw['links'] ?? 0 );
		}

		if ( ! empty( $opts['set_front'] ) ) {
			$front_id = ( isset( $page_id ) && is_int( $page_id ) && $page_id > 0 ) ? $page_id : $rt_page_id;
			$front = $this->set_front_page( $front_id );
			$report['front_page_set'] = ! empty( $front['ok'] );
		}

		return $report;
	}

	/* ============================================================= Menus */

	/**
	 * Rewrite all nav_menu_items pointing to $from_url so they point to $rt_page_id.
	 *
	 * @return array<string,mixed>
	 */
	public function replace_in_menus( int $rt_page_id, string $from_url = '' ): array {
		if ( $from_url === '' ) {
			$from_url = (string) get_post_meta( $rt_page_id, self::META_ORIGIN_URL, true );
			if ( $from_url === '' ) {
				$src = (string) get_post_meta( $rt_page_id, RT_Content_Sync::META_SOURCE_URL, true );
				$from_url = $src !== '' ? $src : $this->source_from_frontmatter( $rt_page_id );
			}
		}
		if ( $from_url === '' ) {
			return [ 'ok' => false, 'error' => 'no source url' ];
		}
		$origin = $this->resolve_origin( $from_url );
		$origin_id = $origin ? (int) $origin['id'] : 0;
		$from_path = (string) wp_parse_url( $from_url, PHP_URL_PATH );

		$items = get_posts( [
			'post_type'      => 'nav_menu_item',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		] );
		$changed = [];
		foreach ( $items as $item ) {
			$obj_id   = (int) get_post_meta( $item->ID, '_menu_item_object_id', true );
			$obj_type = (string) get_post_meta( $item->ID, '_menu_item_object', true );
			$type     = (string) get_post_meta( $item->ID, '_menu_item_type', true );
			$url      = (string) get_post_meta( $item->ID, '_menu_item_url', true );
			$matches_post = ( $origin_id > 0 && $type === 'post_type' && $obj_id === $origin_id );
			$matches_url  = ( $type === 'custom' && $url !== '' && (
				$url === $from_url ||
				rtrim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' ) === rtrim( $from_path, '/' )
			) );
			if ( ! $matches_post && ! $matches_url ) {
				continue;
			}
			update_post_meta( $item->ID, '_menu_item_type', 'post_type' );
			update_post_meta( $item->ID, '_menu_item_object', RT_CPT_Page::POST_TYPE );
			update_post_meta( $item->ID, '_menu_item_object_id', $rt_page_id );
			update_post_meta( $item->ID, '_menu_item_url', '' );
			$changed[] = [
				'item_id'   => $item->ID,
				'menu_item' => $item->post_title,
			];
		}
		return [
			'ok'      => true,
			'changed' => $changed,
			'count'   => count( $changed ),
		];
	}

	/* =========================================================== Redirects */

	/** @return array<string,string> */
	public function get_redirects(): array {
		$opt = get_option( self::OPTION_REDIRECTS, [] );
		return is_array( $opt ) ? $opt : [];
	}

	public function add_redirect( string $from_path, string $to_path ): bool {
		$from_path = '/' . ltrim( (string) $from_path, '/' );
		$to_path   = '/' . ltrim( (string) $to_path, '/' );
		if ( $from_path === '/' || $from_path === $to_path ) {
			return false;
		}
		$map = $this->get_redirects();
		$map[ $from_path ] = $to_path;
		return (bool) update_option( self::OPTION_REDIRECTS, $map, false );
	}

	public function remove_redirect( string $from_path ): bool {
		$from_path = '/' . ltrim( (string) $from_path, '/' );
		$map = $this->get_redirects();
		if ( ! isset( $map[ $from_path ] ) ) {
			return false;
		}
		unset( $map[ $from_path ] );
		return (bool) update_option( self::OPTION_REDIRECTS, $map, false );
	}

	public function maybe_redirect(): void {
		if ( is_admin() ) {
			return;
		}
		$req = '/' . ltrim( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), '/' );
		$req_path = (string) wp_parse_url( $req, PHP_URL_PATH );
		$map = $this->get_redirects();
		if ( ! $map ) {
			return;
		}
		$req_norm = rtrim( $req_path, '/' );
		foreach ( $map as $from => $to ) {
			if ( rtrim( (string) $from, '/' ) === $req_norm ) {
				$qs = (string) wp_parse_url( $req, PHP_URL_QUERY );
				$dest = home_url( $to . ( $qs !== '' ? '?' . $qs : '' ) );
				wp_safe_redirect( $dest, 301 );
				exit;
			}
		}
	}

	/* =============================================================== Misc */

	public function set_front_page( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, [ RT_CPT_Page::POST_TYPE, 'page' ], true ) ) {
			return [ 'ok' => false, 'error' => 'page not found' ];
		}
		update_option( 'show_on_front', 'page', false );
		update_option( 'page_on_front', $post_id, false );
		return [ 'ok' => true, 'page_on_front' => $post_id ];
	}

	/* =========================================================== Preflight */

	/**
	 * Inspect the impact of adopting a given rt_page before doing it.
	 *
	 * @return array<string,mixed>
	 */
	public function preflight( int $rt_page_id ): array {
		$post = get_post( $rt_page_id );
		if ( ! $post || $post->post_type !== RT_CPT_Page::POST_TYPE ) {
			return [ 'ok' => false, 'error' => 'rt_page not found' ];
		}
		$src = (string) get_post_meta( $rt_page_id, RT_Content_Sync::META_SOURCE_URL, true );
		if ( $src === '' ) {
			$src = $this->source_from_frontmatter( $rt_page_id );
		}
		$origin           = $src !== '' ? $this->resolve_origin( $src ) : null;
		$already_promoted = (string) get_post_meta( $rt_page_id, self::META_AT, true ) !== '';

		$origin_id   = $origin ? (int) $origin['id'] : 0;
		$origin_url  = $origin ? (string) $origin['permalink'] : '';

		$menu_links     = $origin_id > 0 ? $this->count_menu_links( $origin_id, $origin_url ) : 0;
		$internal       = $origin_url !== '' ? $this->find_internal_links( $origin_url, $rt_page_id ) : [ 'count' => 0, 'links' => 0, 'samples' => [] ];
		$is_front       = $origin_id > 0 && (int) get_option( 'page_on_front' ) === $origin_id;
		$translations   = $origin_id > 0 ? $this->detect_translations( $origin_id ) : [ 'has' => false, 'plugin' => '', 'count' => 0 ];
		$seo_meta       = $origin_id > 0 && $this->has_seo_meta( $origin_id );
		$revisions      = $origin_id > 0 ? count( wp_get_post_revisions( $origin_id ) ) : 0;
		$comments       = $origin_id > 0 ? (int) get_comments_number( $origin_id ) : 0;

		$warnings = [];
		if ( $already_promoted ) {
			$warnings[] = __( 'Esta página ya está adoptada.', 'replanta-theme' );
		}
		if ( $is_front ) {
			$warnings[] = __( 'El original es la página de inicio del sitio.', 'replanta-theme' );
		}
		if ( $translations['has'] ) {
			$warnings[] = sprintf(
				/* translators: 1: plugin name, 2: number of related translations */
				__( 'El original tiene %2$d traducciones asociadas (%1$s). No se copiarán.', 'replanta-theme' ),
				$translations['plugin'],
				$translations['count']
			);
		}
		if ( $seo_meta ) {
			$warnings[] = __( 'El original tiene metadatos SEO (Yoast/RankMath). Revisa el rt_page antes de adoptar.', 'replanta-theme' );
		}
		if ( $revisions > 10 ) {
			$warnings[] = sprintf(
				/* translators: %d: number of revisions */
				__( 'El original tiene %d revisiones que quedarán archivadas.', 'replanta-theme' ),
				$revisions
			);
		}

		return [
			'ok'               => true,
			'id'               => $rt_page_id,
			'rt_url'           => (string) get_permalink( $rt_page_id ),
			'source_url'       => $src,
			'origin'           => $origin,
			'already_promoted' => $already_promoted,
			'is_front_page'    => $is_front,
			'menu_links'       => $menu_links,
			'internal_links'   => $internal,
			'translations'     => $translations,
			'seo_meta'         => $seo_meta,
			'revisions'        => $revisions,
			'comments'         => $comments,
			'warnings'         => $warnings,
		];
	}

	private function count_menu_links( int $origin_id, string $origin_url ): int {
		$origin_path = (string) wp_parse_url( $origin_url, PHP_URL_PATH );
		$origin_path = rtrim( $origin_path, '/' );
		$items = get_posts( [
			'post_type'      => 'nav_menu_item',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		] );
		$n = 0;
		foreach ( $items as $item ) {
			$obj_id = (int) get_post_meta( $item->ID, '_menu_item_object_id', true );
			$type   = (string) get_post_meta( $item->ID, '_menu_item_type', true );
			$url    = (string) get_post_meta( $item->ID, '_menu_item_url', true );
			if ( $type === 'post_type' && $obj_id === $origin_id ) {
				$n++;
				continue;
			}
			if ( $type === 'custom' && $url !== '' ) {
				$path = rtrim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
				if ( $url === $origin_url || ( $origin_path !== '' && $path === $origin_path ) ) {
					$n++;
				}
			}
		}
		return $n;
	}

	/**
	 * Scan post_content for <a href="$origin_url"> across all post types (excluding nav_menu_item).
	 *
	 * @return array{count:int,links:int,samples:array<int,array<string,mixed>>}
	 */
	private function find_internal_links( string $origin_url, int $exclude_id ): array {
		global $wpdb;
		$origin_path = (string) wp_parse_url( $origin_url, PHP_URL_PATH );
		$origin_path = '/' . trim( $origin_path, '/' );
		$home        = (string) home_url();
		$abs         = rtrim( $home, '/' ) . $origin_path;

		$like_abs   = '%' . $wpdb->esc_like( 'href="' . $abs ) . '%';
		$like_path  = '%' . $wpdb->esc_like( 'href="' . $origin_path ) . '%';
		$like_path2 = '%' . $wpdb->esc_like( "href='" . $origin_path ) . '%';
		$like_abs2  = '%' . $wpdb->esc_like( "href='" . $abs ) . '%';

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT ID, post_title, post_type FROM {$wpdb->posts}
			 WHERE post_status IN ('publish','draft','pending','private','future')
			   AND post_type NOT IN ('revision','nav_menu_item','rt_block')
			   AND ID <> %d
			   AND ( post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s )
			 LIMIT 200",
			$exclude_id, $like_abs, $like_path, $like_abs2, $like_path2
		) );

		$count   = 0;
		$links   = 0;
		$samples = [];
		foreach ( (array) $rows as $r ) {
			$content = (string) get_post_field( 'post_content', (int) $r->ID );
			$n = $this->count_link_occurrences( $content, $origin_path, $abs );
			if ( $n === 0 ) {
				continue;
			}
			$count++;
			$links += $n;
			if ( count( $samples ) < 10 ) {
				$samples[] = [
					'id'    => (int) $r->ID,
					'title' => (string) $r->post_title,
					'type'  => (string) $r->post_type,
					'edit'  => (string) get_edit_post_link( (int) $r->ID, 'raw' ),
					'count' => $n,
				];
			}
		}
		return [ 'count' => $count, 'links' => $links, 'samples' => $samples ];
	}

	private function count_link_occurrences( string $content, string $origin_path, string $abs ): int {
		$n = 0;
		$needles = [ 'href="' . $abs, "href='" . $abs, 'href="' . $origin_path . '"', "href='" . $origin_path . "'", 'href="' . $origin_path . '/"', "href='" . $origin_path . "/'" ];
		foreach ( $needles as $needle ) {
			$n += substr_count( $content, $needle );
		}
		return $n;
	}

	/**
	 * @return array{has:bool,plugin:string,count:int}
	 */
	private function detect_translations( int $origin_id ): array {
		if ( function_exists( 'pll_get_post_translations' ) ) {
			$tr = (array) call_user_func( 'pll_get_post_translations', $origin_id );
			$tr = array_filter( $tr, static fn( $v ) => (int) $v !== $origin_id );
			if ( $tr ) {
				return [ 'has' => true, 'plugin' => 'Polylang', 'count' => count( $tr ) ];
			}
		}
		if ( function_exists( 'icl_object_id' ) ) {
			global $wpdb;
			$count = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}icl_translations
				 WHERE element_id = %d AND element_type LIKE 'post_%'",
				$origin_id
			) );
			if ( $count > 1 ) {
				return [ 'has' => true, 'plugin' => 'WPML', 'count' => $count - 1 ];
			}
		}
		return [ 'has' => false, 'plugin' => '', 'count' => 0 ];
	}

	private function has_seo_meta( int $origin_id ): bool {
		$keys = [ '_yoast_wpseo_title', '_yoast_wpseo_metadesc', 'rank_math_title', 'rank_math_description' ];
		foreach ( $keys as $k ) {
			if ( (string) get_post_meta( $origin_id, $k, true ) !== '' ) {
				return true;
			}
		}
		return false;
	}

	/* =================================================== Internal link rewrite */

	/**
	 * Rewrite <a href="$from_url"> → href="$to_url" in post_content (all post types except nav_menu_item).
	 *
	 * Stores affected post IDs into META_REWRITTEN_POSTS on $rt_page_id for later reporting.
	 *
	 * @return array{posts:int,links:int,ids:array<int,int>}
	 */
	public function rewrite_internal_links( int $rt_page_id, string $from_url, string $to_url ): array {
		$home      = (string) home_url();
		$from_path = '/' . trim( (string) wp_parse_url( $from_url, PHP_URL_PATH ), '/' );
		$to_path   = '/' . trim( (string) wp_parse_url( $to_url, PHP_URL_PATH ), '/' );
		$from_abs  = rtrim( $home, '/' ) . $from_path;
		$to_abs    = rtrim( $home, '/' ) . $to_path;

		$pairs = [
			'href="' . $from_abs . '"'        => 'href="' . $to_abs . '"',
			"href='" . $from_abs . "'"        => "href='" . $to_abs . "'",
			'href="' . $from_abs . '/"'       => 'href="' . $to_abs . '/"',
			"href='" . $from_abs . "/'"       => "href='" . $to_abs . "/'",
			'href="' . $from_path . '"'       => 'href="' . $to_path . '"',
			"href='" . $from_path . "'"       => "href='" . $to_path . "'",
			'href="' . $from_path . '/"'      => 'href="' . $to_path . '/"',
			"href='" . $from_path . "/'"      => "href='" . $to_path . "/'",
		];

		global $wpdb;
		$like_abs  = '%' . $wpdb->esc_like( 'href="' . $from_abs ) . '%';
		$like_path = '%' . $wpdb->esc_like( 'href="' . $from_path ) . '%';
		$like_abs2 = '%' . $wpdb->esc_like( "href='" . $from_abs ) . '%';
		$like_path2= '%' . $wpdb->esc_like( "href='" . $from_path ) . '%';

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts}
			 WHERE post_status IN ('publish','draft','pending','private','future')
			   AND post_type NOT IN ('revision','nav_menu_item','rt_block')
			   AND ID <> %d
			   AND ( post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s )
			 LIMIT 1000",
			$rt_page_id, $like_abs, $like_path, $like_abs2, $like_path2
		) );

		$posts = 0;
		$links = 0;
		$ids   = [];
		foreach ( (array) $rows as $r ) {
			$id      = (int) $r->ID;
			$content = (string) get_post_field( 'post_content', $id );
			$count   = 0;
			$new     = str_replace( array_keys( $pairs ), array_values( $pairs ), $content, $count );
			if ( $count > 0 && $new !== $content ) {
				wp_update_post( [ 'ID' => $id, 'post_content' => $new ] );
				$posts++;
				$links += $count;
				$ids[]  = $id;
			}
		}
		if ( $ids ) {
			update_post_meta( $rt_page_id, self::META_REWRITTEN_POSTS, $ids );
		}
		return [ 'posts' => $posts, 'links' => $links, 'ids' => $ids ];
	}

	/* =============================================================== Undo */

	/**
	 * Revert a previous adopt: restore slugs and remove the auto-created 301.
	 *
	 * Does NOT revert menu reassignments or content link rewrites by design — those
	 * remain valid (menus point to the rt_page, links point to the canonical URL).
	 *
	 * @return array<string,mixed>
	 */
	public function undo_adopt( int $rt_page_id ): array {
		$post = get_post( $rt_page_id );
		if ( ! $post || $post->post_type !== RT_CPT_Page::POST_TYPE ) {
			return [ 'ok' => false, 'error' => 'rt_page not found' ];
		}
		$at = (string) get_post_meta( $rt_page_id, self::META_AT, true );
		if ( $at === '' ) {
			return [ 'ok' => false, 'error' => 'not promoted' ];
		}
		$origin_id      = (int) get_post_meta( $rt_page_id, self::META_ORIGIN_ID, true );
		$origin_slug    = (string) get_post_meta( $rt_page_id, self::META_ORIGIN_SLUG, true );
		$origin_status  = (string) get_post_meta( $rt_page_id, self::META_ORIGIN_STATUS, true );
		$self_old_slug  = (string) get_post_meta( $rt_page_id, self::META_SELF_OLD_SLUG, true );
		$redirect_from  = (string) get_post_meta( $rt_page_id, self::META_REDIRECT_FROM, true );
		$promoted_page  = (int) get_post_meta( $rt_page_id, self::META_PROMOTED_PAGE_ID, true );

		// Delete the native `page` heir we created during adopt.
		if ( $promoted_page > 0 ) {
			wp_delete_post( $promoted_page, true );
		}
		// Restore rt_page to publish (it was demoted to draft when we created the page heir).
		wp_update_post( [ 'ID' => $rt_page_id, 'post_status' => 'publish' ] );
		// Restore rt_page slug.
		if ( $self_old_slug !== '' ) {
			wp_update_post( [ 'ID' => $rt_page_id, 'post_name' => $self_old_slug ] );
		}
		// Restore original.
		if ( $origin_id > 0 && $origin_slug !== '' ) {
			$payload = [ 'ID' => $origin_id, 'post_name' => $origin_slug ];
			if ( $origin_status !== '' ) {
				$payload['post_status'] = $origin_status;
			}
			wp_update_post( $payload );
		}
		// Drop the 301 we created.
		if ( $redirect_from !== '' ) {
			$this->remove_redirect( $redirect_from );
		}
		// Clear metadata.
		delete_post_meta( $rt_page_id, self::META_ORIGIN_ID );
		delete_post_meta( $rt_page_id, self::META_ORIGIN_URL );
		delete_post_meta( $rt_page_id, self::META_ORIGIN_SLUG );
		delete_post_meta( $rt_page_id, self::META_ORIGIN_STATUS );
		delete_post_meta( $rt_page_id, self::META_ORIGIN_ARCHIVED_SLUG );
		delete_post_meta( $rt_page_id, self::META_SELF_OLD_SLUG );
		delete_post_meta( $rt_page_id, self::META_REDIRECT_FROM );
		delete_post_meta( $rt_page_id, self::META_REWRITTEN_POSTS );
		delete_post_meta( $rt_page_id, self::META_AT );
		delete_post_meta( $rt_page_id, self::META_PROMOTED_PAGE_ID );

		// Defensive cleanup: if rt_page still carries mirror metas and its old slug
		// is different from current, purge any leftover assets folder.
		if ( $self_old_slug !== '' && get_post_meta( $rt_page_id, RT_Mirror_Importer::META_MIRROR, true ) ) {
			RT_Mirror_Cleanup::purge_slug( $self_old_slug );
		}

		return [
			'ok'             => true,
			'restored_id'    => $origin_id,
			'restored_slug'  => $origin_slug,
			'rt_page_slug'   => $self_old_slug,
			'redirect_dropped' => $redirect_from,
		];
	}

	/* ============================================================== Bulk */

	/**
	 * Adopt several rt_pages in one shot. Continues on individual errors.
	 *
	 * @param array<int,int>          $ids
	 * @param array<string,bool>|bool $opts
	 * @return array{ok:bool,results:array<int,array<string,mixed>>,success:int,fail:int}
	 */
	public function bulk_adopt( array $ids, $opts = [] ): array {
		$results = [];
		$success = 0;
		$fail    = 0;
		foreach ( $ids as $raw ) {
			$id = (int) $raw;
			if ( $id <= 0 ) {
				$results[] = [ 'id' => $id, 'ok' => false, 'error' => 'invalid id' ];
				$fail++;
				continue;
			}
			$res = $this->adopt_url( $id, $opts );
			$res['id'] = $id;
			$results[] = $res;
			if ( ! empty( $res['ok'] ) ) {
				$success++;
			} else {
				$fail++;
			}
		}
		return [ 'ok' => true, 'results' => $results, 'success' => $success, 'fail' => $fail ];
	}
}
