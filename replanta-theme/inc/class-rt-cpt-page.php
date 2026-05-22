<?php
/**
 * Custom post type rt_page (mirrors files in /content).
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class RT_CPT_Page {

	public const POST_TYPE = 'rt_page';
	private const PREVIEW_BASE = 'preview';

	public function register(): void {
		add_action( 'init', [ $this, 'register_post_type' ] );
		add_action( 'init', [ $this, 'add_legacy_rewrite_rule' ], 20 );
		add_action( 'wp_head', [ $this, 'print_noindex_preview' ], 1 );
		add_action( 'template_redirect', [ $this, 'send_noindex_header' ], 1 );
		add_action( 'template_redirect', [ $this, 'redirect_legacy_preview_url' ], 2 );
	}

	public function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			[
				'label'         => __( 'Replanta Pages', 'replanta-theme' ),
				'labels'        => [
					'name'          => __( 'Páginas', 'replanta-theme' ),
					'singular_name' => __( 'Página', 'replanta-theme' ),
					'menu_name'     => __( 'Páginas', 'replanta-theme' ),
					'all_items'     => __( 'Todas las páginas', 'replanta-theme' ),
					'add_new'       => __( 'Añadir nueva', 'replanta-theme' ),
					'add_new_item'  => __( 'Nueva página', 'replanta-theme' ),
					'edit_item'     => __( 'Editar página', 'replanta-theme' ),
				],
				'public'        => true,
				'show_in_rest'  => true,
				'has_archive'   => false,
				// Keep mirror previews out of /rt_page/... and under a cleaner base.
				'rewrite'       => [ 'slug' => $this->preview_base(), 'with_front' => false ],
				'supports'      => [ 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ],
				'menu_icon'     => 'dashicons-layout',
				'show_in_menu'  => 'replanta-ai',
			]
		);
	}

	private function preview_base(): string {
		$base = (string) apply_filters( 'rt_page_preview_base', self::PREVIEW_BASE );
		$base = trim( sanitize_title( $base ), '/' );
		return $base !== '' ? $base : self::PREVIEW_BASE;
	}

	public function print_noindex_preview(): void {
		if ( ! is_singular( self::POST_TYPE ) ) {
			return;
		}
		echo "<meta name=\"robots\" content=\"noindex, nofollow, noarchive\" />\n";
	}

	public function send_noindex_header(): void {
		if ( ! is_singular( self::POST_TYPE ) || headers_sent() ) {
			return;
		}
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
	}

	public function add_legacy_rewrite_rule(): void {
		add_rewrite_rule( '^rt_page/([^/]+)/?$', 'index.php?' . self::POST_TYPE . '=$matches[1]', 'top' );
	}

	public function redirect_legacy_preview_url(): void {
		if ( ! is_singular( self::POST_TYPE ) || is_admin() || wp_doing_ajax() ) {
			return;
		}
		$uri = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
		if ( strpos( $uri, '/rt_page/' ) === false ) {
			return;
		}
		$target = get_permalink( get_queried_object_id() );
		if ( ! $target ) {
			return;
		}
		wp_safe_redirect( $target, 301 );
		exit;
	}
}
