<?php
/**
 * Plugin Name: Replanta Hide Featured Image On Elementor Pages
 * Description: Hides Astra featured image block on pages built with Elementor.
 * Version: 1.0.0
 * Author: Replanta
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return true only for singular pages built with Elementor under Astra.
 */
function replanta_should_hide_featured_image_on_page() {
	if ( is_admin() || wp_doing_ajax() || ! is_singular( 'page' ) ) {
		return false;
	}

	$post_id = get_queried_object_id();
	if ( ! $post_id || ! has_post_thumbnail( $post_id ) ) {
		return false;
	}

	$elementor_data = get_post_meta( $post_id, '_elementor_data', true );
	if ( empty( $elementor_data ) ) {
		return false;
	}

	$theme      = wp_get_theme();
	$template   = $theme->get_template();
	$stylesheet = $theme->get_stylesheet();

	return ( $template === 'astra' || $stylesheet === 'astra' );
}

/**
 * Remove the featured image wrapper Astra prints before page content.
 */
function replanta_hide_featured_image_css() {
	if ( ! replanta_should_hide_featured_image_on_page() ) {
		return;
	}

	echo '<style id="replanta-hide-featured-image-elementor-pages">';
	echo 'body.page .ast-single-post-featured-section{display:none !important;}';
	echo '</style>';
}
add_action( 'wp_head', 'replanta_hide_featured_image_css', 99 );
