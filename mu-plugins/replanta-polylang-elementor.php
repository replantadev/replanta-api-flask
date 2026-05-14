<?php
/**
 * Plugin Name: Replanta Polylang Elementor Templates
 * Description: Filters Elementor Theme Builder templates by current Polylang language + auto-repairs empty EN templates
 * Version: 2.3.0
 * Author: Replanta
 *
 * Intercepts the Elementor Pro theme builder conditions option to remove
 * wrong-language templates from the candidates, ensuring the correct
 * footer/single/archive template is shown per language.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ES template ID => EN template ID
 */
function replanta_get_template_lang_map() {
	return array(
		// ES => EN pairs
		408  => 10036, // footer
		3605 => 10034, // faqs pie de página / faqs footer
		1947 => 10035, // faqs / Faqs
		5497 => 10011, // doc-archive
		6676 => 10008, // single-post (artículos/articles)
	);
}

/**
 * Strategy 1: Filter the theme builder conditions option on read.
 * Elementor Pro reads 'elementor_pro_theme_builder_conditions' to decide
 * which template to render.
 *
 * For each location (header, footer, single, archive), we:
 * - Remove wrong-language template IDs
 * - INJECT the correct-language template with the same conditions
 *
 * This way the EN templates don't need to be manually registered
 * in Elementor's Theme Builder conditions UI.
 */
add_filter( 'option_elementor_pro_theme_builder_conditions', 'replanta_filter_theme_builder_conditions' );

function replanta_filter_theme_builder_conditions( $conditions ) {
	if ( ! is_array( $conditions ) || empty( $conditions ) || is_admin() || wp_doing_ajax() ) {
		return $conditions;
	}

	if ( ! function_exists( 'pll_current_language' ) ) {
		return $conditions;
	}

	$lang = pll_current_language( 'slug' );
	if ( empty( $lang ) ) {
		return $conditions;
	}

	$map    = replanta_get_template_lang_map();
	$en_ids = array_values( $map );

	// Detect structure: nested (location => templates) vs flat (template_id => conditions_array).
	// Nested keys are strings like 'footer','single'; flat keys are numeric template IDs.
	$first_key = array_key_first( $conditions );
	$is_nested = ( $first_key !== null && ! is_numeric( $first_key ) );

	if ( $is_nested ) {
		// --- Nested: location => [ template_id => conditions_array ] ---
		if ( $lang === 'en' ) {
			foreach ( $conditions as $location => &$templates ) {
				if ( ! is_array( $templates ) ) {
					continue;
				}
				foreach ( $map as $es_id => $en_id ) {
					if ( isset( $templates[ $es_id ] ) ) {
						// Only swap if EN template is published & has Elementor data.
						// Otherwise keep ES template (wrong lang > invisible footer).
						if ( replanta_is_template_renderable( $en_id ) ) {
							$templates[ $en_id ] = $templates[ $es_id ];
							unset( $templates[ $es_id ] );
						}
					}
				}
			}
			unset( $templates );
		} else {
			foreach ( $conditions as $location => &$templates ) {
				if ( ! is_array( $templates ) ) {
					continue;
				}
				foreach ( $en_ids as $en_id ) {
					unset( $templates[ $en_id ] );
				}
			}
			unset( $templates );
		}
	} else {
		// --- Flat: template_id => conditions_array ---
		if ( $lang === 'en' ) {
			foreach ( $map as $es_id => $en_id ) {
				if ( isset( $conditions[ $es_id ] ) ) {
					if ( replanta_is_template_renderable( $en_id ) ) {
						$conditions[ $en_id ] = $conditions[ $es_id ];
						unset( $conditions[ $es_id ] );
					}
				}
			}
		} else {
			foreach ( $en_ids as $en_id ) {
				unset( $conditions[ $en_id ] );
			}
		}
	}

	return $conditions;
}

/**
 * Check whether an Elementor template is published and has renderable data.
 * Result is cached per request with a static variable.
 */
function replanta_is_template_renderable( $template_id ) {
	static $cache = array();
	if ( isset( $cache[ $template_id ] ) ) {
		return $cache[ $template_id ];
	}

	$post = get_post( $template_id );
	if ( ! $post || $post->post_status !== 'publish' ) {
		$cache[ $template_id ] = false;
		return false;
	}

	$data = get_post_meta( $template_id, '_elementor_data', true );
	if ( empty( $data ) || $data === '[]' ) {
		$cache[ $template_id ] = false;
		return false;
	}

	$cache[ $template_id ] = true;
	return true;
}

/* Strategy 2 & 3 removed in v2.3.0 — they were dead code.
 * Strategy 2 (pre_option_ filter) was a noop.
 * Strategy 3 (before_do_footer) accessed Elementor Pro internals
 * without doing anything useful and could fatal-error on newer EP versions.
 */

/**
 * Strategy 4: Most aggressive — filter get_option for the conditions,
 * and also hook into the actual template document resolution.
 */
add_action( 'wp', 'replanta_override_footer_template', 1 );

function replanta_override_footer_template() {
	if ( ! function_exists( 'pll_current_language' ) ) {
		return;
	}

	$lang = pll_current_language( 'slug' );
	if ( empty( $lang ) || $lang === 'es' ) {
		return;
	}

	$map    = replanta_get_template_lang_map();
	$es_ids = array_keys( $map );

	// Override: when Elementor tries to get the footer document for ID 408,
	// redirect it to 10036
	add_filter( 'elementor/frontend/builder_content_data', function( $data, $post_id ) use ( $map ) {
		if ( isset( $map[ $post_id ] ) ) {
			// Get the EN template data instead
			$en_id   = $map[ $post_id ];
			$en_data = get_post_meta( $en_id, '_elementor_data', true );
			if ( $en_data ) {
				return json_decode( $en_data, true ) ?: $data;
			}
		}
		return $data;
	}, 10, 2 );
}

/**
 * ---------------------------------------------------------
 * AUTO-REPAIR: Copy Elementor data from ES to EN templates
 * when EN templates are empty / corrupted.
 *
 * Runs once per day (transient). After repair, the EN
 * templates become editable in Elementor so you can translate them.
 * ---------------------------------------------------------
 */
add_action( 'wp', 'replanta_repair_empty_en_templates', 5 );

function replanta_repair_empty_en_templates() {
	if ( is_admin() ) {
		return;
	}

	// Only run once per day
	if ( get_transient( 'rpe_repair_done' ) ) {
		return;
	}

	$map     = replanta_get_template_lang_map();
	$repaired = array();

	foreach ( $map as $es_id => $en_id ) {
		$en_post = get_post( $en_id );
		if ( ! $en_post || $en_post->post_type !== 'elementor_library' ) {
			continue;
		}

		$en_data = get_post_meta( $en_id, '_elementor_data', true );
		$es_data = get_post_meta( $es_id, '_elementor_data', true );

		// If EN template is empty but ES has data, copy it
		if ( ( empty( $en_data ) || $en_data === '[]' ) && ! empty( $es_data ) && $es_data !== '[]' ) {
			// Copy Elementor data
			update_post_meta( $en_id, '_elementor_data', $es_data );

			// Copy essential Elementor meta if missing
			$meta_keys = array(
				'_elementor_edit_mode',
				'_elementor_template_type',
				'_elementor_version',
				'_elementor_pro_version',
			);
			foreach ( $meta_keys as $key ) {
				if ( empty( get_post_meta( $en_id, $key, true ) ) ) {
					$val = get_post_meta( $es_id, $key, true );
					if ( ! empty( $val ) ) {
						update_post_meta( $en_id, $key, $val );
					}
				}
			}

			// Copy post_content if empty (Elementor needs this as fallback)
			if ( empty( $en_post->post_content ) ) {
				$es_post = get_post( $es_id );
				if ( $es_post && ! empty( $es_post->post_content ) ) {
					wp_update_post( array(
						'ID'           => $en_id,
						'post_content' => $es_post->post_content,
					) );
				}
			}

			$repaired[] = "EN #{$en_id} <- ES #{$es_id}";
		}
	}

	if ( ! empty( $repaired ) ) {
		// Clear Elementor CSS cache
		if ( class_exists( '\Elementor\Plugin' ) ) {
			\Elementor\Plugin::instance()->files_manager->clear_cache();
		}
		error_log( '[Replanta PE] Repaired empty EN templates: ' . implode( ', ', $repaired ) );
	}

	set_transient( 'rpe_repair_done', '1', DAY_IN_SECONDS );
}

/**
 * ---------------------------------------------------------
 * DIAGNOSTIC: ?action=rpe_diag
 * Shows the state of all mapped templates.
 * ---------------------------------------------------------
 */
add_action( 'init', 'replanta_pe_diagnostic' );

function replanta_pe_diagnostic() {
	if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'rpe_diag' ) {
		return;
	}

	if ( ! headers_sent() ) {
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Cache-Control: no-store' );
		header( 'X-Robots-Tag: noindex' );
	}

	$map    = replanta_get_template_lang_map();
	$result = array(
		'plugin'  => 'replanta-polylang-elementor',
		'version' => '2.3.0',
		'repair_pending' => ! get_transient( 'rpe_repair_done' ),
	);

	foreach ( $map as $es_id => $en_id ) {
		foreach ( array( $es_id, $en_id ) as $pid ) {
			$p = get_post( $pid );
			$result['templates'][ $pid ] = array(
				'title'     => $p ? $p->post_title : '(not found)',
				'status'    => $p ? $p->post_status : null,
				'elem_data' => strlen( (string) get_post_meta( $pid, '_elementor_data', true ) ),
				'elem_mode' => get_post_meta( $pid, '_elementor_edit_mode', true ),
				'elem_type' => get_post_meta( $pid, '_elementor_template_type', true ),
				'lang'      => function_exists( 'pll_get_post_language' ) ? pll_get_post_language( $pid, 'slug' ) : 'N/A',
			);
		}
	}

	// Elementor conditions
	$result['conditions'] = get_option( 'elementor_pro_theme_builder_conditions', array() );

	echo wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	exit;
}

/**
 * [replanta_lang_switcher]
 *
 * Renders ES / EN language links using Polylang data.
 * Works in Elementor HTML or Shortcode widgets.
 *
 * Usage: [replanta_lang_switcher]
 */
add_shortcode( 'replanta_lang_switcher', 'replanta_lang_switcher_shortcode' );

function replanta_lang_switcher_shortcode() {
	if ( ! function_exists( 'pll_the_languages' ) ) {
		return '';
	}

	$languages = pll_the_languages( array(
		'raw'           => 1,
		'show_names'    => 1,
		'display_order' => 0,
	) );

	if ( empty( $languages ) ) {
		return '';
	}

	$out = '<div class="footer-lang">';
	foreach ( $languages as $lang ) {
		$active = $lang['current_lang'] ? ' class="active"' : '';
		$url    = esc_url( $lang['url'] );
		$slug   = esc_html( strtoupper( $lang['slug'] ) );
		$out   .= '<a href="' . $url . '"' . $active . '>' . $slug . '</a>';
	}
	$out .= '</div>';

	return $out;
}

/**
 * ---------------------------------------------------------
 * HREFLANG X-DEFAULT INJECTION
 *
 * Polylang adds hreflang for each language but NOT x-default.
 * Google needs x-default to know which version to show by default.
 * This injects x-default pointing to the Spanish (default) version.
 * ---------------------------------------------------------
 */
add_action( 'wp_head', 'replanta_inject_hreflang_xdefault', 1 );

function replanta_inject_hreflang_xdefault() {
	// Only on frontend
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return;
	}

	// Require Polylang
	if ( ! function_exists( 'pll_current_language' ) || ! function_exists( 'pll_home_url' ) ) {
		return;
	}

	// Get default language (should be 'es')
	$default_lang = function_exists( 'pll_default_language' ) ? pll_default_language( 'slug' ) : 'es';

	// Get URL for default language version of current page
	$xdefault_url = '';

	if ( is_front_page() || is_home() ) {
		// For homepage, use the home URL of default language
		$xdefault_url = pll_home_url( $default_lang );
	} elseif ( is_singular() ) {
		// For posts/pages, get the translation in default language
		$post_id = get_queried_object_id();
		if ( $post_id && function_exists( 'pll_get_post' ) ) {
			$default_post_id = pll_get_post( $post_id, $default_lang );
			if ( $default_post_id ) {
				$xdefault_url = get_permalink( $default_post_id );
			}
		}
	} elseif ( is_tax() || is_category() || is_tag() ) {
		// For taxonomies
		$term_id = get_queried_object_id();
		if ( $term_id && function_exists( 'pll_get_term' ) ) {
			$default_term_id = pll_get_term( $term_id, $default_lang );
			if ( $default_term_id ) {
				$term = get_term( $default_term_id );
				if ( $term && ! is_wp_error( $term ) ) {
					$xdefault_url = get_term_link( $term );
				}
			}
		}
	}

	// Fallback to home if no specific URL found
	if ( empty( $xdefault_url ) ) {
		$xdefault_url = pll_home_url( $default_lang );
	}

	// Output x-default hreflang
	if ( ! empty( $xdefault_url ) ) {
		printf(
			'<link rel="alternate" hreflang="x-default" href="%s" />' . "\n",
			esc_url( $xdefault_url )
		);
	}
}
