<?php
/**
 * REST API namespace replanta/v1.
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class RT_REST {

	public const NAMESPACE = 'replanta/v1';

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'routes' ] );
	}

	public function routes(): void {
		$auth = [ $this, 'can_manage' ];

		register_rest_route( self::NAMESPACE, '/health', [
			'methods' => 'GET', 'callback' => [ $this, 'health' ], 'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NAMESPACE, '/settings', [
			[ 'methods' => 'GET',  'callback' => [ $this, 'get_settings' ],    'permission_callback' => $auth ],
			[ 'methods' => 'POST', 'callback' => [ $this, 'update_settings' ], 'permission_callback' => $auth ],
		] );

		register_rest_route( self::NAMESPACE, '/onboarding', [
			'methods' => 'POST', 'callback' => [ $this, 'save_onboarding' ], 'permission_callback' => $auth,
		] );

		register_rest_route( self::NAMESPACE, '/pages', [
			'methods' => 'GET', 'callback' => [ $this, 'list_pages' ], 'permission_callback' => $auth,
		] );

		register_rest_route( self::NAMESPACE, '/pages/(?P<path>.+)', [
			[ 'methods' => 'GET',    'callback' => [ $this, 'get_page' ],    'permission_callback' => $auth ],
			[ 'methods' => 'PUT',    'callback' => [ $this, 'save_page' ],   'permission_callback' => $auth ],
			[ 'methods' => 'DELETE', 'callback' => [ $this, 'delete_page' ], 'permission_callback' => $auth ],
		] );

		register_rest_route( self::NAMESPACE, '/sync', [
			'methods' => 'POST', 'callback' => [ $this, 'sync' ], 'permission_callback' => $auth,
		] );

		register_rest_route( self::NAMESPACE, '/generate/page', [
			'methods' => 'POST', 'callback' => [ $this, 'gen_page' ], 'permission_callback' => $auth,
		] );

		register_rest_route( self::NAMESPACE, '/generate/section', [
			'methods' => 'POST', 'callback' => [ $this, 'gen_section' ], 'permission_callback' => $auth,
		] );

		register_rest_route( self::NAMESPACE, '/rewrite', [
			'methods' => 'POST', 'callback' => [ $this, 'rewrite' ], 'permission_callback' => $auth,
		] );

		register_rest_route( self::NAMESPACE, '/translate', [
			'methods' => 'POST', 'callback' => [ $this, 'translate' ], 'permission_callback' => $auth,
		] );

		register_rest_route( self::NAMESPACE, '/interlink', [
			'methods' => 'GET', 'callback' => [ $this, 'interlink' ], 'permission_callback' => $auth,
		] );

		register_rest_route( self::NAMESPACE, '/style-packs', [
			[ 'methods' => 'GET',  'callback' => [ $this, 'list_packs' ], 'permission_callback' => $auth ],
			[ 'methods' => 'POST', 'callback' => [ $this, 'apply_pack' ], 'permission_callback' => $auth ],
		] );

		register_rest_route( self::NAMESPACE, '/import/elementor', [
			'methods' => 'POST', 'callback' => [ $this, 'import_elementor' ], 'permission_callback' => $auth,
		] );

		register_rest_route( self::NAMESPACE, '/import/elementor/list', [
			'methods' => 'GET', 'callback' => [ $this, 'import_elementor_list' ], 'permission_callback' => $auth,
		] );

		register_rest_route( self::NAMESPACE, '/install', [
			[ 'methods' => 'GET',  'callback' => [ $this, 'install_status' ], 'permission_callback' => $auth ],
			[ 'methods' => 'POST', 'callback' => [ $this, 'install_run' ],    'permission_callback' => $auth ],
		] );

		register_rest_route( self::NAMESPACE, '/import/html', [
			[ 'methods' => 'GET',  'callback' => [ $this, 'import_html_list' ], 'permission_callback' => $auth ],
			[ 'methods' => 'POST', 'callback' => [ $this, 'import_html_run' ],  'permission_callback' => $auth ],
		] );

		register_rest_route( self::NAMESPACE, '/import/html/raw', [
			'methods' => 'POST', 'callback' => [ $this, 'import_html_raw' ], 'permission_callback' => $auth,
		] );

		register_rest_route( self::NAMESPACE, '/import/url', [
			'methods' => 'POST', 'callback' => [ $this, 'import_url' ], 'permission_callback' => $auth,
		] );

		register_rest_route( self::NAMESPACE, '/import/mirror', [
			'methods' => 'POST', 'callback' => [ $this, 'import_mirror' ], 'permission_callback' => $auth,
		] );

		register_rest_route( self::NAMESPACE, '/import/sitemap', [
			'methods' => 'POST', 'callback' => [ $this, 'import_sitemap' ], 'permission_callback' => $auth,
		] );

		register_rest_route( self::NAMESPACE, '/import/sitemap/discover', [
			'methods' => 'POST', 'callback' => [ $this, 'import_sitemap_discover' ], 'permission_callback' => $auth,
		] );

		register_rest_route( self::NAMESPACE, '/import/ai-rewrite', [
			'methods' => 'POST', 'callback' => [ $this, 'import_ai_rewrite' ], 'permission_callback' => $auth,
		] );

		register_rest_route( self::NAMESPACE, '/import/diff', [
			'methods' => 'POST', 'callback' => [ $this, 'import_diff' ], 'permission_callback' => $auth,
		] );

		register_rest_route( self::NAMESPACE, '/blocks', [
			'methods' => 'GET', 'callback' => [ $this, 'blocks_list' ], 'permission_callback' => $auth,
			'args' => [ 'path' => [ 'required' => true ] ],
		] );
		register_rest_route( self::NAMESPACE, '/blocks/update', [
			'methods' => 'POST', 'callback' => [ $this, 'blocks_update' ], 'permission_callback' => $auth,
		] );
		register_rest_route( self::NAMESPACE, '/blocks/update-attrs', [
			'methods' => 'POST', 'callback' => [ $this, 'blocks_update_attrs' ], 'permission_callback' => $auth,
		] );
		register_rest_route( self::NAMESPACE, '/audit/page', [
			'methods' => 'POST', 'callback' => [ $this, 'audit_page' ], 'permission_callback' => $auth,
		] );
		register_rest_route( self::NAMESPACE, '/blocks/delete', [
			'methods' => 'POST', 'callback' => [ $this, 'blocks_delete' ], 'permission_callback' => $auth,
		] );
		register_rest_route( self::NAMESPACE, '/blocks/move', [
			'methods' => 'POST', 'callback' => [ $this, 'blocks_move' ], 'permission_callback' => $auth,
		] );
		register_rest_route( self::NAMESPACE, '/blocks/duplicate', [
			'methods' => 'POST', 'callback' => [ $this, 'blocks_duplicate' ], 'permission_callback' => $auth,
		] );
		register_rest_route( self::NAMESPACE, '/blocks/insert', [
			'methods' => 'POST', 'callback' => [ $this, 'blocks_insert' ], 'permission_callback' => $auth,
		] );
		register_rest_route( self::NAMESPACE, '/blocks/insert-ai', [
			'methods' => 'POST', 'callback' => [ $this, 'blocks_insert_ai' ], 'permission_callback' => $auth,
		] );
		register_rest_route( self::NAMESPACE, '/blocks/rewrite-ai', [
			'methods' => 'POST', 'callback' => [ $this, 'blocks_rewrite_ai' ], 'permission_callback' => $auth,
		] );
		register_rest_route( self::NAMESPACE, '/blocks/preview', [
			'methods' => 'POST', 'callback' => [ $this, 'blocks_preview' ], 'permission_callback' => $auth,
		] );

		// Block ↔ library bridge.
		register_rest_route( self::NAMESPACE, '/blocks/save-to-library', [
			'methods' => 'POST', 'callback' => [ $this, 'blocks_save_to_library' ], 'permission_callback' => $auth,
		] );
		register_rest_route( self::NAMESPACE, '/blocks/break-include', [
			'methods' => 'POST', 'callback' => [ $this, 'blocks_break_include' ], 'permission_callback' => $auth,
		] );
		register_rest_route( self::NAMESPACE, '/blocks/insert-include', [
			'methods' => 'POST', 'callback' => [ $this, 'blocks_insert_include' ], 'permission_callback' => $auth,
		] );
		register_rest_route( self::NAMESPACE, '/blocks/insert-library-copy', [
			'methods' => 'POST', 'callback' => [ $this, 'blocks_insert_library_copy' ], 'permission_callback' => $auth,
		] );

		// Promote: adopt URL, rewrite menus, redirects.
		register_rest_route( self::NAMESPACE, '/promote/candidates', [
			'methods' => 'GET', 'callback' => [ $this, 'promote_candidates' ], 'permission_callback' => $auth,
		] );
		register_rest_route( self::NAMESPACE, '/promote/preflight', [
			'methods' => 'GET', 'callback' => [ $this, 'promote_preflight' ], 'permission_callback' => $auth,
		] );
		register_rest_route( self::NAMESPACE, '/promote/adopt', [
			'methods' => 'POST', 'callback' => [ $this, 'promote_adopt' ], 'permission_callback' => $auth,
		] );
		register_rest_route( self::NAMESPACE, '/promote/undo', [
			'methods' => 'POST', 'callback' => [ $this, 'promote_undo' ], 'permission_callback' => $auth,
		] );
		register_rest_route( self::NAMESPACE, '/promote/bulk-adopt', [
			'methods' => 'POST', 'callback' => [ $this, 'promote_bulk_adopt' ], 'permission_callback' => $auth,
		] );
		register_rest_route( self::NAMESPACE, '/promote/diff', [
			'methods' => 'GET', 'callback' => [ $this, 'promote_diff' ], 'permission_callback' => $auth,
		] );
		register_rest_route( self::NAMESPACE, '/promote/psi', [
			'methods' => 'GET', 'callback' => [ $this, 'promote_psi' ], 'permission_callback' => $auth,
		] );
		register_rest_route( self::NAMESPACE, '/promote/replace-in-menus', [
			'methods' => 'POST', 'callback' => [ $this, 'promote_replace_in_menus' ], 'permission_callback' => $auth,
		] );
		register_rest_route( self::NAMESPACE, '/promote/redirects', [
			[ 'methods' => 'GET',    'callback' => [ $this, 'promote_redirects_list' ], 'permission_callback' => $auth ],
			[ 'methods' => 'POST',   'callback' => [ $this, 'promote_redirects_add' ],  'permission_callback' => $auth ],
			[ 'methods' => 'DELETE', 'callback' => [ $this, 'promote_redirects_del' ],  'permission_callback' => $auth ],
		] );
		register_rest_route( self::NAMESPACE, '/promote/front-page', [
			'methods' => 'POST', 'callback' => [ $this, 'promote_front_page' ], 'permission_callback' => $auth,
		] );

		register_rest_route( self::NAMESPACE, '/custom-css', [
			[ 'methods' => 'GET',  'callback' => [ $this, 'get_custom_css' ],    'permission_callback' => $auth ],
			[ 'methods' => 'POST', 'callback' => [ $this, 'update_custom_css' ], 'permission_callback' => $auth ],
		] );

		// Layout (header/footer) settings.
		register_rest_route( self::NAMESPACE, '/layout', [
			[ 'methods' => 'GET',  'callback' => [ $this, 'layout_get' ],  'permission_callback' => $auth ],
			[ 'methods' => 'POST', 'callback' => [ $this, 'layout_save' ], 'permission_callback' => $auth ],
		] );

		// Reusable block library.
		register_rest_route( self::NAMESPACE, '/library', [
			'methods' => 'GET', 'callback' => [ $this, 'library_list' ], 'permission_callback' => $auth,
		] );
		register_rest_route( self::NAMESPACE, '/library/save', [
			'methods' => 'POST', 'callback' => [ $this, 'library_save' ], 'permission_callback' => $auth,
		] );
		register_rest_route( self::NAMESPACE, '/library/delete', [
			'methods' => 'POST', 'callback' => [ $this, 'library_delete' ], 'permission_callback' => $auth,
		] );
		register_rest_route( self::NAMESPACE, '/library/break', [
			'methods' => 'POST', 'callback' => [ $this, 'library_break' ], 'permission_callback' => $auth,
		] );
	}

	public function import_html_list(): \WP_REST_Response {
		$importer = new RT_HTML_Importer();
		return new \WP_REST_Response( [
			'dir'     => $importer->import_dir(),
			'sources' => $importer->list_sources(),
		] );
	}

	public function import_html_run( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$res  = ( new RT_HTML_Importer() )->import_all( [
			'lang'      => (string) ( $body['lang'] ?? 'es' ),
			'merge_css' => (bool) ( $body['merge_css'] ?? true ),
			'specific'  => isset( $body['specific'] ) && is_array( $body['specific'] ) ? array_map( 'strval', $body['specific'] ) : null,
		] );
		return new \WP_REST_Response( $res );
	}

	public function import_html_raw( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		if ( empty( $body['html'] ) ) {
			return new \WP_REST_Response( [ 'error' => 'html required' ], 400 );
		}
		return new \WP_REST_Response( ( new RT_HTML_Importer() )->import_raw( $body ) );
	}

	public function import_url( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$url  = (string) ( $body['url'] ?? '' );
		if ( $url === '' ) {
			return new \WP_REST_Response( [ 'error' => 'url required' ], 400 );
		}
		$res = ( new RT_HTML_Importer() )->import_url(
			$url,
			(string) ( $body['lang'] ?? 'es' ),
			isset( $body['slug'] ) ? (string) $body['slug'] : null,
			(bool) ( $body['download_images'] ?? true )
		);
		return new \WP_REST_Response( $res, $res['ok'] ? 200 : 400 );
	}

	public function import_mirror( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$url  = (string) ( $body['url'] ?? '' );
		if ( $url === '' ) {
			return new \WP_REST_Response( [ 'error' => 'url required' ], 400 );
		}
		$res = ( new RT_Mirror_Importer() )->import(
			$url,
			isset( $body['slug'] ) ? (string) $body['slug'] : null,
			(string) ( $body['lang'] ?? 'es' )
		);
		return new \WP_REST_Response( $res, ! empty( $res['ok'] ) ? 200 : 400 );
	}

	public function import_sitemap( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$url  = (string) ( $body['url'] ?? '' );
		if ( $url === '' ) {
			return new \WP_REST_Response( [ 'error' => 'url required' ], 400 );
		}
		$res = ( new RT_HTML_Importer() )->import_sitemap(
			$url,
			(string) ( $body['lang'] ?? 'es' ),
			(int) ( $body['limit'] ?? 50 ),
			(bool) ( $body['download_images'] ?? true )
		);
		return new \WP_REST_Response( $res );
	}

	public function import_sitemap_discover( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$url  = (string) ( $body['url'] ?? '' );
		if ( $url === '' ) {
			return new \WP_REST_Response( [ 'ok' => false, 'error' => 'url required' ], 400 );
		}
		$res = ( new RT_HTML_Importer() )->discover_sitemaps( $url );
		return new \WP_REST_Response( $res, $res['ok'] ? 200 : 400 );
	}

	public function import_ai_rewrite( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$path = (string) ( $body['path'] ?? '' );
		if ( $path === '' ) {
			return new \WP_REST_Response( [ 'error' => 'path required' ], 400 );
		}
		$res = ( new RT_HTML_Importer() )->ai_rewrite_file( $path, (string) ( $body['instruction'] ?? '' ) );
		return new \WP_REST_Response( $res, $res['ok'] ? 200 : 400 );
	}

	public function import_diff( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$path = (string) ( $body['path'] ?? '' );
		if ( $path === '' ) {
			return new \WP_REST_Response( [ 'error' => 'path required' ], 400 );
		}
		return new \WP_REST_Response( ( new RT_HTML_Importer() )->diff_render( $path ) );
	}

	/* ----------------------------------------------------- Block-level editor */

	public function blocks_list( \WP_REST_Request $req ): \WP_REST_Response {
		$path = (string) $req->get_param( 'path' );
		if ( $path === '' ) {
			return new \WP_REST_Response( [ 'error' => 'path required' ], 400 );
		}
		$res = ( new RT_Block_Editor() )->load( $path );
		return new \WP_REST_Response( $res, $res['ok'] ? 200 : 404 );
	}

	public function blocks_update( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$res  = ( new RT_Block_Editor() )->update(
			(string) ( $body['path'] ?? '' ),
			(int) ( $body['index'] ?? -1 ),
			(string) ( $body['raw'] ?? '' )
		);
		return new \WP_REST_Response( $res, $res['ok'] ? 200 : 400 );
	}

	public function blocks_update_attrs( \WP_REST_Request $req ): \WP_REST_Response {
		$body  = (array) $req->get_json_params();
		$attrs = (array) ( $body['attrs'] ?? [] );
		$res   = ( new RT_Block_Editor() )->update_attrs(
			(string) ( $body['path'] ?? '' ),
			(int) ( $body['index'] ?? -1 ),
			$attrs
		);
		return new \WP_REST_Response( $res, $res['ok'] ? 200 : 400 );
	}

	public function audit_page( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$path = (string) ( $body['path'] ?? '' );
		if ( $path === '' ) {
			return new \WP_REST_Response( [ 'ok' => false, 'error' => 'path required' ], 400 );
		}
		$res = ( new RT_Block_Editor() )->audit( $path );
		return new \WP_REST_Response( $res, $res['ok'] ? 200 : 400 );
	}

	public function blocks_delete( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$res  = ( new RT_Block_Editor() )->delete(
			(string) ( $body['path'] ?? '' ),
			(int) ( $body['index'] ?? -1 )
		);
		return new \WP_REST_Response( $res, $res['ok'] ? 200 : 400 );
	}

	public function blocks_move( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$res  = ( new RT_Block_Editor() )->move(
			(string) ( $body['path'] ?? '' ),
			(int) ( $body['index'] ?? -1 ),
			(int) ( $body['direction'] ?? 0 )
		);
		return new \WP_REST_Response( $res, $res['ok'] ? 200 : 400 );
	}

	public function blocks_duplicate( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$res  = ( new RT_Block_Editor() )->duplicate(
			(string) ( $body['path'] ?? '' ),
			(int) ( $body['index'] ?? -1 )
		);
		return new \WP_REST_Response( $res, $res['ok'] ? 200 : 400 );
	}

	public function blocks_insert( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$res  = ( new RT_Block_Editor() )->insert(
			(string) ( $body['path'] ?? '' ),
			(int) ( $body['position'] ?? 0 ),
			(string) ( $body['template'] ?? 'Markdown' ),
			(string) ( $body['raw'] ?? '' )
		);
		return new \WP_REST_Response( $res, $res['ok'] ? 200 : 400 );
	}

	public function blocks_insert_ai( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$res  = ( new RT_Block_Editor() )->insert_ai(
			(string) ( $body['path'] ?? '' ),
			(int) ( $body['position'] ?? 0 ),
			(string) ( $body['template'] ?? 'Content' ),
			(string) ( $body['prompt'] ?? '' ),
			(string) ( $body['lang'] ?? 'es' )
		);
		return new \WP_REST_Response( $res, $res['ok'] ? 200 : 400 );
	}

	public function blocks_rewrite_ai( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$res  = ( new RT_Block_Editor() )->rewrite_ai(
			(string) ( $body['path'] ?? '' ),
			(int) ( $body['index'] ?? -1 ),
			(string) ( $body['instruction'] ?? '' ),
			(string) ( $body['lang'] ?? 'es' )
		);
		return new \WP_REST_Response( $res, $res['ok'] ? 200 : 400 );
	}

	/** Render a single block (raw MDX) to HTML for live preview. Runs the_content filter so shortcodes work. */
	public function blocks_preview( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$raw  = (string) ( $body['raw'] ?? '' );
		if ( $raw === '' ) {
			return new \WP_REST_Response( [ 'ok' => false, 'error' => 'raw required' ], 400 );
		}
		$blocks   = RT_MDX_Parser::body_to_blocks( $raw );
		$rendered = function_exists( 'do_blocks' ) ? do_blocks( $blocks ) : $blocks;
		if ( function_exists( 'apply_filters' ) ) {
			$rendered = (string) apply_filters( 'the_content', $rendered );
		}
		return new \WP_REST_Response( [ 'ok' => true, 'html' => $rendered ] );
	}

	/* ---------------------------------------------------- Block ↔ library bridge */

	public function blocks_save_to_library( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$res  = ( new RT_Block_Editor() )->save_to_library(
			(string) ( $body['path'] ?? '' ),
			(int) ( $body['index'] ?? -1 ),
			(string) ( $body['slug'] ?? '' ),
			(string) ( $body['title'] ?? '' ),
			! isset( $body['replace_with_include'] ) || (bool) $body['replace_with_include']
		);
		return new \WP_REST_Response( $res, $res['ok'] ? 200 : 400 );
	}

	public function blocks_break_include( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$res  = ( new RT_Block_Editor() )->break_include(
			(string) ( $body['path'] ?? '' ),
			(int) ( $body['index'] ?? -1 )
		);
		return new \WP_REST_Response( $res, $res['ok'] ? 200 : 400 );
	}

	public function blocks_insert_include( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$res  = ( new RT_Block_Editor() )->insert_include(
			(string) ( $body['path'] ?? '' ),
			(int) ( $body['position'] ?? 0 ),
			(string) ( $body['slug'] ?? '' )
		);
		return new \WP_REST_Response( $res, $res['ok'] ? 200 : 400 );
	}

	public function blocks_insert_library_copy( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$res  = ( new RT_Block_Editor() )->insert_library_copy(
			(string) ( $body['path'] ?? '' ),
			(int) ( $body['position'] ?? 0 ),
			(string) ( $body['slug'] ?? '' )
		);
		return new \WP_REST_Response( $res, $res['ok'] ? 200 : 400 );
	}

	/* ----------------------------------------------------------------- Library */

	public function library_list(): \WP_REST_Response {
		return new \WP_REST_Response( [ 'ok' => true, 'items' => ( new RT_Block_Library() )->list_items() ] );
	}

	public function library_save( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$res  = ( new RT_Block_Library() )->save(
			(string) ( $body['slug'] ?? '' ),
			(string) ( $body['title'] ?? '' ),
			(string) ( $body['body'] ?? '' )
		);
		return new \WP_REST_Response( $res, $res['ok'] ? 200 : 400 );
	}

	public function library_delete( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$ok   = ( new RT_Block_Library() )->delete( (string) ( $body['slug'] ?? '' ) );
		return new \WP_REST_Response( [ 'ok' => $ok ] );
	}

	public function library_break( \WP_REST_Request $req ): \WP_REST_Response {
		// Alias of blocks_break_include for symmetry under /library/break.
		return $this->blocks_break_include( $req );
	}

	/* ---------------------------------------------------------------- Layout */

	public function layout_get(): \WP_REST_Response {
		return new \WP_REST_Response( [ 'ok' => true, 'layout' => RT_Layout::settings() ] );
	}

	public function layout_save( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$next = RT_Layout::save( $body );
		return new \WP_REST_Response( [ 'ok' => true, 'layout' => $next ] );
	}

	public function get_custom_css(): \WP_REST_Response {
		return new \WP_REST_Response( [
			'css' => (string) get_option( RT_HTML_Importer::OPTION_CUSTOM_CSS, '' ),
		] );
	}

	public function update_custom_css( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$css  = (string) ( $body['css'] ?? '' );
		update_option( RT_HTML_Importer::OPTION_CUSTOM_CSS, $css, false );
		update_option( 'rt_custom_css_ver', (string) time(), false );
		return new \WP_REST_Response( [ 'ok' => true, 'bytes' => strlen( $css ) ] );
	}

	public function install_status(): \WP_REST_Response {
		return new \WP_REST_Response( [
			'installed' => ( new RT_Installer() )->is_installed(),
			'version'   => (string) get_option( RT_Installer::OPTION_VERSION, '' ),
		] );
	}

	public function install_run( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$lang = (string) ( $body['lang'] ?? 'es' );
		$seed = (bool) ( $body['seed_demo'] ?? true );
		$res  = ( new RT_Installer() )->install( $lang, $seed );
		return new \WP_REST_Response( $res );
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	public function health(): \WP_REST_Response {
		return new \WP_REST_Response( [
			'ok'    => true,
			'theme' => RT_THEME_VERSION,
			'php'   => PHP_VERSION,
			'wp'    => get_bloginfo( 'version' ),
			'i18n'  => ( new RT_I18n_Driver() )->active(),
		] );
	}

	public function get_settings(): \WP_REST_Response {
		$settings = (array) get_option( 'rt_theme_settings', [] );
		if ( ! empty( $settings['ai_api_key'] ) ) {
			$settings['ai_api_key_masked'] = '••••' . substr( (string) $settings['ai_api_key'], -4 );
			unset( $settings['ai_api_key'] );
		}
		$settings['onboarding_done'] = (bool) get_option( 'rt_theme_onboarding_done', false );
		$settings['active_pack']     = (string) get_option( RT_Style_Packs::OPTION, 'replanta-eco' );
		$settings['languages']       = ( new RT_I18n_Driver() )->languages();
		return new \WP_REST_Response( $settings );
	}

	public function update_settings( \WP_REST_Request $req ): \WP_REST_Response {
		$current = (array) get_option( 'rt_theme_settings', [] );
		$incoming = (array) $req->get_json_params();
		$merged = array_merge( $current, $incoming );
		update_option( 'rt_theme_settings', $merged, false );
		return new \WP_REST_Response( [ 'ok' => true ] );
	}

	public function save_onboarding( \WP_REST_Request $req ): \WP_REST_Response {
		$data = (array) $req->get_json_params();
		$settings = (array) get_option( 'rt_theme_settings', [] );
		if ( ! empty( $data['aiKey'] ) ) {
			$settings['ai_api_key']  = (string) $data['aiKey'];
			$settings['ai_provider'] = (string) ( $data['aiProvider'] ?? 'anthropic' );
		}
		update_option( 'rt_theme_settings', $settings, false );

		if ( ! empty( $data['stylePack'] ) ) {
			( new RT_Style_Packs() )->apply( (string) $data['stylePack'] );
		}

		unset( $data['aiKey'] );
		update_option( 'rt_theme_onboarding', $data, false );
		update_option( 'rt_theme_onboarding_done', true, false );
		return new \WP_REST_Response( [ 'ok' => true ] );
	}

	public function list_pages(): \WP_REST_Response {
		return new \WP_REST_Response( ( new RT_Content_Sync() )->list_files() );
	}

	public function get_page( \WP_REST_Request $req ): \WP_REST_Response {
		$path = urldecode( (string) $req['path'] );
		$file = ( new RT_Content_Sync() )->read_file( $path );
		if ( ! $file ) {
			return new \WP_REST_Response( [ 'error' => 'Not found' ], 404 );
		}
		return new \WP_REST_Response( $file );
	}

	public function save_page( \WP_REST_Request $req ): \WP_REST_Response {
		$path = urldecode( (string) $req['path'] );
		$body = (array) $req->get_json_params();
		$abs = ( new RT_Content_Sync() )->write_file(
			$path,
			(array) ( $body['frontmatter'] ?? [] ),
			(string) ( $body['body'] ?? '' )
		);
		return new \WP_REST_Response( [ 'ok' => true, 'abs' => $abs ] );
	}

	public function delete_page( \WP_REST_Request $req ): \WP_REST_Response {
		$path = urldecode( (string) $req['path'] );
		return new \WP_REST_Response( [ 'ok' => ( new RT_Content_Sync() )->delete_file( $path ) ] );
	}

	public function sync(): \WP_REST_Response {
		return new \WP_REST_Response( ( new RT_Content_Sync() )->sync_all() );
	}

	public function gen_page( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$prompt = (string) ( $body['prompt'] ?? '' );
		if ( $prompt === '' ) {
			return new \WP_REST_Response( [ 'error' => 'prompt required' ], 400 );
		}
		$res = ( new RT_Page_Generator() )->generate_page(
			$prompt,
			(string) ( $body['lang'] ?? 'es' ),
			( $body['slug'] ?? null ) ? (string) $body['slug'] : null
		);
		return new \WP_REST_Response( $res, $res['ok'] ? 200 : 500 );
	}

	public function gen_section( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$res = ( new RT_Page_Generator() )->generate_section(
			(string) ( $body['prompt'] ?? '' ),
			(string) ( $body['type'] ?? 'Hero' ),
			[ 'lang' => (string) ( $body['lang'] ?? 'es' ) ]
		);
		return new \WP_REST_Response( $res, $res['ok'] ? 200 : 500 );
	}

	public function rewrite( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$res = ( new RT_Page_Generator() )->rewrite(
			(string) ( $body['fragment'] ?? '' ),
			(string) ( $body['instruction'] ?? 'Improve clarity' ),
			(string) ( $body['lang'] ?? 'es' )
		);
		return new \WP_REST_Response( $res, $res['ok'] ? 200 : 500 );
	}

	public function translate( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$path = (string) ( $body['path'] ?? '' );
		$target = (string) ( $body['target'] ?? '' );
		if ( $path === '' || $target === '' ) {
			return new \WP_REST_Response( [ 'error' => 'path and target required' ], 400 );
		}
		$sync = new RT_Content_Sync();
		$source = $sync->read_file( $path );
		if ( ! $source ) {
			return new \WP_REST_Response( [ 'error' => 'Source not found' ], 404 );
		}
		$res = ( new RT_Page_Generator() )->translate( (string) $source['raw'], $target );
		if ( ! $res['ok'] ) {
			return new \WP_REST_Response( $res, 500 );
		}
		$split = RT_MDX_Parser::split( (string) $res['mdx'] );
		$front = $split['frontmatter'];
		$slug  = sanitize_title( (string) ( $front['slug'] ?? pathinfo( $path, PATHINFO_FILENAME ) ) );
		$rel   = $target . '/' . $slug . '.mdx';
		$front['lang'] = $target;
		$front['slug'] = $slug;
		$abs = $sync->write_file( $rel, $front, $split['body'] );
		return new \WP_REST_Response( [ 'ok' => true, 'path' => $rel, 'abs' => $abs ] );
	}

	public function interlink( \WP_REST_Request $req ): \WP_REST_Response {
		$out = ( new RT_RankMath_Bridge() )->suggest_internal_links(
			(string) $req->get_param( 'slug' ),
			(string) ( $req->get_param( 'lang' ) ?? 'es' )
		);
		return new \WP_REST_Response( $out );
	}

	public function list_packs(): \WP_REST_Response {
		$packs = RT_Style_Packs::packs();
		$out = [];
		foreach ( $packs as $id => $p ) {
			$out[] = array_merge( [ 'id' => $id ], $p );
		}
		return new \WP_REST_Response( [ 'packs' => $out, 'active' => get_option( RT_Style_Packs::OPTION, 'replanta-eco' ) ] );
	}

	public function apply_pack( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$ok = ( new RT_Style_Packs() )->apply( (string) ( $body['id'] ?? '' ) );
		return new \WP_REST_Response( [ 'ok' => $ok ] );
	}

	public function import_elementor_list(): \WP_REST_Response {
		$posts = get_posts( [
			'post_type'      => [ 'page', 'post' ],
			'meta_key'       => '_elementor_data',
			'posts_per_page' => 100,
			'post_status'    => [ 'publish', 'draft' ],
		] );
		$out = array_map( static fn( $p ) => [
			'id' => $p->ID, 'title' => $p->post_title, 'type' => $p->post_type,
			'status' => $p->post_status, 'slug' => $p->post_name,
		], $posts );
		return new \WP_REST_Response( $out );
	}

	public function import_elementor( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$id   = (int) ( $body['post_id'] ?? 0 );
		$lang = (string) ( $body['lang'] ?? 'es' );
		if ( $id <= 0 ) {
			return new \WP_REST_Response( [ 'error' => 'post_id required' ], 400 );
		}
		$res = ( new RT_Elementor_Importer() )->import( $id, $lang );
		return new \WP_REST_Response( $res, $res['ok'] ? 200 : 500 );
	}

	/* ------------------------------------------------------------ Promote */

	public function promote_candidates(): \WP_REST_Response {
		return new \WP_REST_Response( [ 'ok' => true, 'items' => ( new RT_Promote() )->list_candidates() ] );
	}

	public function promote_preflight( \WP_REST_Request $req ): \WP_REST_Response {
		$id = (int) $req->get_param( 'id' );
		if ( $id <= 0 ) {
			return new \WP_REST_Response( [ 'ok' => false, 'error' => 'id required' ], 400 );
		}
		$res = ( new RT_Promote() )->preflight( $id );
		return new \WP_REST_Response( $res, ! empty( $res['ok'] ) ? 200 : 400 );
	}

	public function promote_adopt( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$id   = (int) ( $body['id'] ?? 0 );
		if ( $id <= 0 ) {
			return new \WP_REST_Response( [ 'ok' => false, 'error' => 'id required' ], 400 );
		}
		$opts = $this->promote_normalize_opts( $body );
		$res = ( new RT_Promote() )->adopt_url( $id, $opts );
		return new \WP_REST_Response( $res, ! empty( $res['ok'] ) ? 200 : 400 );
	}

	public function promote_undo( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$id   = (int) ( $body['id'] ?? 0 );
		if ( $id <= 0 ) {
			return new \WP_REST_Response( [ 'ok' => false, 'error' => 'id required' ], 400 );
		}
		$res = ( new RT_Promote() )->undo_adopt( $id );
		return new \WP_REST_Response( $res, ! empty( $res['ok'] ) ? 200 : 400 );
	}

	public function promote_bulk_adopt( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$ids  = array_map( 'intval', (array) ( $body['ids'] ?? [] ) );
		$ids  = array_values( array_filter( $ids, static fn( $i ) => $i > 0 ) );
		if ( ! $ids ) {
			return new \WP_REST_Response( [ 'ok' => false, 'error' => 'ids required' ], 400 );
		}
		if ( count( $ids ) > 50 ) {
			return new \WP_REST_Response( [ 'ok' => false, 'error' => 'max 50 items per batch' ], 400 );
		}
		$opts = $this->promote_normalize_opts( $body );
		$res  = ( new RT_Promote() )->bulk_adopt( $ids, $opts );
		return new \WP_REST_Response( $res, 200 );
	}

	/**
	 * @param array<string,mixed> $body
	 * @return array<string,bool>
	 */
	private function promote_normalize_opts( array $body ): array {
		$raw = isset( $body['opts'] ) && is_array( $body['opts'] ) ? $body['opts'] : $body;
		return [
			'demote'        => ! isset( $raw['demote'] )        || (bool) $raw['demote'],
			'replace_menus' => ! isset( $raw['replace_menus'] ) || (bool) $raw['replace_menus'],
			'rewrite_links' => ! isset( $raw['rewrite_links'] ) || (bool) $raw['rewrite_links'],
			'set_front'     => isset( $raw['set_front'] ) && (bool) $raw['set_front'],
		];
	}

	public function promote_diff( \WP_REST_Request $req ): \WP_REST_Response {
		$id = (int) $req->get_param( 'id' );
		if ( $id <= 0 ) {
			return new \WP_REST_Response( [ 'ok' => false, 'error' => 'id required' ], 400 );
		}
		$strategy = (string) ( $req->get_param( 'strategy' ) ?? 'mobile' );
		$fresh    = (bool) $req->get_param( 'fresh' );
		$res      = ( new RT_Promote_Diff() )->compare( $id, $fresh, $strategy );
		return new \WP_REST_Response( $res, ! empty( $res['ok'] ) ? 200 : 400 );
	}

	public function promote_psi( \WP_REST_Request $req ): \WP_REST_Response {
		$url = (string) $req->get_param( 'url' );
		if ( $url === '' ) {
			return new \WP_REST_Response( [ 'ok' => false, 'error' => 'url required' ], 400 );
		}
		$strategy = (string) ( $req->get_param( 'strategy' ) ?? 'mobile' );
		$fresh    = (bool) $req->get_param( 'fresh' );
		$res      = ( new RT_Promote_Diff() )->psi_score( $url, $strategy, $fresh );
		return new \WP_REST_Response( $res, ! empty( $res['ok'] ) ? 200 : 400 );
	}

	public function promote_replace_in_menus( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$id   = (int) ( $body['id'] ?? 0 );
		$from = (string) ( $body['from'] ?? '' );
		if ( $id <= 0 ) {
			return new \WP_REST_Response( [ 'ok' => false, 'error' => 'id required' ], 400 );
		}
		$res = ( new RT_Promote() )->replace_in_menus( $id, $from );
		return new \WP_REST_Response( $res, $res['ok'] ? 200 : 400 );
	}

	public function promote_redirects_list(): \WP_REST_Response {
		$map = ( new RT_Promote() )->get_redirects();
		$items = [];
		foreach ( $map as $from => $to ) {
			$items[] = [ 'from' => $from, 'to' => $to ];
		}
		return new \WP_REST_Response( [ 'ok' => true, 'items' => $items ] );
	}

	public function promote_redirects_add( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$from = (string) ( $body['from'] ?? '' );
		$to   = (string) ( $body['to'] ?? '' );
		if ( $from === '' || $to === '' ) {
			return new \WP_REST_Response( [ 'ok' => false, 'error' => 'from + to required' ], 400 );
		}
		$ok = ( new RT_Promote() )->add_redirect( $from, $to );
		return new \WP_REST_Response( [ 'ok' => $ok ] );
	}

	public function promote_redirects_del( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$from = (string) ( $body['from'] ?? '' );
		if ( $from === '' ) {
			return new \WP_REST_Response( [ 'ok' => false, 'error' => 'from required' ], 400 );
		}
		$ok = ( new RT_Promote() )->remove_redirect( $from );
		return new \WP_REST_Response( [ 'ok' => $ok ] );
	}

	public function promote_front_page( \WP_REST_Request $req ): \WP_REST_Response {
		$body = (array) $req->get_json_params();
		$id   = (int) ( $body['id'] ?? 0 );
		if ( $id <= 0 ) {
			return new \WP_REST_Response( [ 'ok' => false, 'error' => 'id required' ], 400 );
		}
		$res = ( new RT_Promote() )->set_front_page( $id );
		return new \WP_REST_Response( $res, $res['ok'] ? 200 : 400 );
	}
}
