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

	public function register(): void {
		add_action( 'init', [ $this, 'register_post_type' ] );
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
				'rewrite'       => [ 'slug' => '', 'with_front' => false ],
				'supports'      => [ 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ],
				'menu_icon'     => 'dashicons-layout',
				'show_in_menu'  => 'replanta-ai',
			]
		);
	}
}
