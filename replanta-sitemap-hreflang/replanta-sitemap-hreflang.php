<?php
/**
 * Plugin Name: Replanta Sitemap Hreflang
 * Plugin URI:  https://replanta.net
 * Description: Adds hreflang annotations to RankMath XML sitemaps for Polylang multilingual support. Ensures Google correctly understands language relationships between ES and EN pages.
 * Version:     1.3.0
 * Author:      Replanta
 * Author URI:  https://replanta.net
 * License:     GPL-2.0+
 * Text Domain: replanta-sitemap-hreflang
 *
 * Requires at least: 5.8
 * Tested up to: 7.0
 * Requires PHP: 7.4
 * Requires Plugins: seo-by-rank-math, polylang
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prevent double-loading (plugin + mu-plugin conflict)
if ( defined( 'RSH_VERSION' ) ) {
	return;
}

define( 'RSH_VERSION', '1.3.0' );
define( 'RSH_PLUGIN_FILE', __FILE__ );
define( 'RSH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

if ( ! class_exists( 'Replanta_Sitemap_Hreflang' ) ) :

/**
 * Main plugin class - bridges RankMath sitemaps with Polylang translations.
 *
 * Strategy:
 * 1. Hook rank_math/sitemap/entry to capture post/term/author translations from Polylang
 * 2. Hook rank_math/sitemap/url to inject <xhtml:link> hreflang XML into each <url> block
 * 3. Hook rank_math/sitemap/{type}_urlset to add the xhtml namespace declaration
 * 4. Hook rank_math/frontend/hreflang to fix x-default + paginated URLs in the HTML head
 * 5. wp_head fallback for RankMath versions without the frontend/hreflang filter
 */
class Replanta_Sitemap_Hreflang {

	/** @var self|null */
	private static $instance = null;

	/** @var string Default language slug from Polylang. */
	private $default_lang = 'es';

	/** @var array All active language slugs from Polylang. */
	private $languages = [];

	/**
	 * Set to true when rank_math/frontend/hreflang filter runs successfully.
	 * Prevents the wp_head fallback from emitting duplicate tags.
	 *
	 * @var bool
	 */
	private $xdefault_filter_ran = false;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		register_activation_hook( RSH_PLUGIN_FILE, [ $this, 'on_activation' ] );
		add_action( 'plugins_loaded', [ $this, 'init' ], 20 );
	}

	public function on_activation() {
		if ( class_exists( 'RankMath\\Sitemap\\Cache' ) ) {
			\RankMath\Sitemap\Cache::invalidate_storage();
		}

		global $wpdb;
		$wpdb->query(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE '%rank_math_sitemap%'"
		);

		update_option( 'rsh_activated', true );
	}

	public function init() {
		if ( ! class_exists( 'RankMath' ) ) {
			add_action( 'admin_notices', [ $this, 'notice_no_rankmath' ] );
			return;
		}

		if ( ! function_exists( 'pll_get_post_translations' ) ) {
			add_action( 'admin_notices', [ $this, 'notice_no_polylang' ] );
			return;
		}

		$this->maybe_clear_cache();

		$this->default_lang = pll_default_language( 'slug' );
		$this->languages    = pll_languages_list( [ 'fields' => 'slug' ] );

		// --- Sitemap hooks ---
		add_filter( 'rank_math/sitemap/entry', [ $this, 'capture_translations' ], 20, 3 );
		add_filter( 'rank_math/sitemap/url',   [ $this, 'inject_hreflang_xml' ], 20, 2 );
		$this->register_urlset_filters();

		// Admin hooks
		add_action( 'admin_notices', [ $this, 'activation_notice' ] );
		add_action( 'admin_init',    [ $this, 'dismiss_notice' ] );

		// Clear sitemap cache when translations are saved
		add_action( 'pll_save_post', [ $this, 'clear_sitemap_cache' ] );
		add_action( 'pll_save_term', [ $this, 'clear_sitemap_cache' ] );

		// --- GSC compatibility ---
		add_filter( 'rank_math/sitemap/stylesheet_url', '__return_empty_string' );
		add_filter( 'rank_math/sitemap/http_headers',   [ $this, 'fix_sitemap_headers' ], 20 );

		// --- HTML head hreflang fixes ---
		// Primary: modify RankMath's hreflang array before output
		add_filter( 'rank_math/frontend/hreflang', [ $this, 'fix_xdefault_hreflang' ], 20 );
		// Fallback: for RankMath versions without the filter, and to handle author archives
		add_action( 'wp_head', [ $this, 'fix_xdefault_hreflang_output' ], 999 );

		// Debug endpoint
		add_action( 'wp_ajax_rsh_check', [ $this, 'ajax_check' ] );
	}

	private function maybe_clear_cache() {
		$stored_version = get_option( 'rsh_version', '' );
		if ( $stored_version !== RSH_VERSION ) {
			if ( class_exists( 'RankMath\\Sitemap\\Cache' ) ) {
				\RankMath\Sitemap\Cache::invalidate_storage();
			}

			global $wpdb;
			$wpdb->query(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient%rank_math%sitemap%'"
			);

			update_option( 'rsh_version', RSH_VERSION );
			update_option( 'rsh_activated', true );
		}
	}

	public function ajax_check() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'No permission' );
		}

		header( 'Content-Type: text/plain; charset=utf-8' );

		echo "=== Replanta Sitemap Hreflang - Diagnostico ===\n\n";
		echo "Version: " . RSH_VERSION . "\n";
		echo "Stored version: " . get_option( 'rsh_version', 'none' ) . "\n";
		echo "RankMath activo: " . ( class_exists( 'RankMath' ) ? 'SI' : 'NO' ) . "\n";
		echo "Polylang activo: " . ( function_exists( 'pll_get_post_translations' ) ? 'SI' : 'NO' ) . "\n";
		echo "Idioma por defecto: " . $this->default_lang . "\n";
		echo "Idiomas activos: " . implode( ', ', $this->languages ) . "\n\n";

		$home_id = get_option( 'page_on_front' );
		if ( $home_id ) {
			echo "--- Test: Pagina de inicio (ID: {$home_id}) ---\n";
			$translations = $this->get_post_translations( $home_id );
			if ( ! empty( $translations ) ) {
				foreach ( $translations as $lang => $url ) {
					echo "  [{$lang}] {$url}\n";
				}
			} else {
				echo "  Sin traducciones encontradas\n";
			}
		}

		echo "\n--- Test: Ultimos 5 posts con traducciones ---\n";
		$posts = get_posts( [
			'numberposts' => 10,
			'post_type'   => [ 'post', 'page' ],
			'post_status' => 'publish',
		] );

		$count = 0;
		foreach ( $posts as $post ) {
			$translations = $this->get_post_translations( $post->ID );
			if ( count( $translations ) >= 2 ) {
				$count++;
				echo "\n  Post: {$post->post_title} (ID: {$post->ID})\n";
				foreach ( $translations as $lang => $url ) {
					echo "    [{$lang}] {$url}\n";
				}
				if ( $count >= 5 ) break;
			}
		}

		if ( 0 === $count ) {
			echo "  Ningun post reciente tiene traducciones vinculadas en Polylang\n";
		}

		echo "\n--- Test: Autores ---\n";
		$authors = get_users( [ 'who' => 'authors', 'number' => 5 ] );
		if ( $authors ) {
			foreach ( $authors as $author ) {
				$author_trans = $this->get_author_translations( $author );
				echo "\n  Autor: {$author->display_name} (ID: {$author->ID})\n";
				if ( count( $author_trans ) >= 2 ) {
					foreach ( $author_trans as $lang => $url ) {
						echo "    [{$lang}] {$url}\n";
					}
				} else {
					echo "    Sin traducciones (necesita pll_home_url)\n";
				}
			}
		} else {
			echo "  No hay autores\n";
		}

		echo "\n--- Test: BetterDocs Pages (si existen) ---\n";
		$bd_posts = get_posts( [
			'post_type'      => 'docs',
			'posts_per_page' => 5,
			'post_status'    => 'publish',
		] );

		if ( ! empty( $bd_posts ) ) {
			foreach ( $bd_posts as $bd_post ) {
				$bd_trans = $this->get_betterdocs_translations( $bd_post );
				if ( count( $bd_trans ) >= 2 ) {
					echo "\n  Docs: {$bd_post->post_title} (ID: {$bd_post->ID})\n";
					foreach ( $bd_trans as $lang => $url ) {
						echo "    [{$lang}] {$url}\n";
					}
				}
			}
		} else {
			echo "  No BetterDocs pages found\n";
		}

		echo "\n--- Estado de la cache de sitemaps ---\n";
		global $wpdb;
		$cache_count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient%rank_math%sitemap%'"
		);
		echo "Transients de sitemap en cache: {$cache_count}\n";

		echo "\n=== Verificacion completada ===\n";
		wp_die();
	}

	private function register_urlset_filters() {
		$post_types = get_post_types( [ 'public' => true ], 'names' );
		$taxonomies = get_taxonomies( [ 'public' => true ], 'names' );

		$types = array_merge(
			array_values( $post_types ),
			array_values( $taxonomies ),
			[ 'author' ]
		);

		foreach ( $types as $type ) {
			add_filter(
				"rank_math/sitemap/{$type}_urlset",
				[ $this, 'add_xhtml_namespace' ],
				20
			);
		}
	}

	// =========================================================================
	// SITEMAP FILTER CALLBACKS
	// =========================================================================

	/**
	 * Filter: rank_math/sitemap/entry
	 *
	 * Capture Polylang translations for the current entry and store them
	 * in a custom 'hreflang' key of the URL array.
	 */
	public function capture_translations( $url, $type, $object ) {
		if ( ! isset( $url['loc'] ) ) {
			return $url;
		}

		$translations = [];

		if ( 'post' === $type && isset( $object->ID ) ) {
			$translations = $this->get_post_translations( $object->ID );

			if ( count( $translations ) < 2 ) {
				$bd_translations = $this->get_betterdocs_translations( $object );
				if ( count( $bd_translations ) >= 2 ) {
					$translations = $bd_translations;
				}
			}

		} elseif ( 'term' === $type && isset( $object->term_id ) ) {
			$translations = $this->get_term_translations( $object->term_id );

		} elseif ( 'user' === $type ) {
			// Author archives: Polylang prefixes URLs by language directory.
			// Build translations by replacing the home URL with each language's home URL.
			$translations = $this->get_author_translations( $object );
		}

		if ( count( $translations ) >= 2 ) {
			$url['hreflang'] = $translations;
		}

		return $url;
	}

	/**
	 * Filter: rank_math/sitemap/url
	 *
	 * Inject <xhtml:link rel="alternate" hreflang="..."> elements into
	 * each <url> XML block in the sitemap.
	 */
	public function inject_hreflang_xml( $output, $url ) {
		if ( empty( $url['hreflang'] ) || ! is_array( $url['hreflang'] ) ) {
			return $output;
		}

		$hreflang_xml = '';

		foreach ( $url['hreflang'] as $lang => $href ) {
			$hreflang_xml .= sprintf(
				"\t\t<xhtml:link rel=\"alternate\" hreflang=\"%s\" href=\"%s\" />\n",
				esc_attr( $this->normalize_hreflang_code( $lang ) ),
				esc_url( $href )
			);
		}

		if ( isset( $url['hreflang'][ $this->default_lang ] ) ) {
			$hreflang_xml .= sprintf(
				"\t\t<xhtml:link rel=\"alternate\" hreflang=\"x-default\" href=\"%s\" />\n",
				esc_url( $url['hreflang'][ $this->default_lang ] )
			);
		}

		if ( ! empty( $hreflang_xml ) ) {
			$output = str_replace( '</url>', $hreflang_xml . "\t</url>", $output );
		}

		return $output;
	}

	/**
	 * Filter: rank_math/sitemap/{type}_urlset
	 *
	 * Add the xhtml namespace declaration to the <urlset> element.
	 */
	public function add_xhtml_namespace( $urlset ) {
		if ( false !== strpos( $urlset, 'xmlns:xhtml=' ) ) {
			return $urlset;
		}

		return str_replace(
			'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
			'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">',
			$urlset
		);
	}

	// =========================================================================
	// POLYLANG TRANSLATION HELPERS
	// =========================================================================

	/**
	 * Get all published, indexable translations for a post.
	 *
	 * @param int $post_id
	 * @return array lang_slug => permalink
	 */
	private function get_post_translations( $post_id ) {
		if ( ! function_exists( 'pll_get_post_translations' ) ) {
			return [];
		}

		$trans_ids    = pll_get_post_translations( $post_id );
		$translations = [];

		foreach ( $trans_ids as $lang => $trans_id ) {
			if ( 'publish' !== get_post_status( $trans_id ) ) {
				continue;
			}

			$robots = get_post_meta( $trans_id, 'rank_math_robots', true );
			if ( is_array( $robots ) && in_array( 'noindex', $robots, true ) ) {
				continue;
			}

			$permalink = get_permalink( $trans_id );
			if ( $permalink ) {
				$translations[ $lang ] = $permalink;
			}
		}

		return $translations;
	}

	/**
	 * Get hreflang translations for an author archive.
	 *
	 * Polylang doesn't store author "translations" — it simply prefixes the
	 * URL with the language directory (e.g. /en/autor/nicename/). We derive
	 * each language URL by swapping home_url() for pll_home_url($lang).
	 *
	 * @param \WP_User $user
	 * @return array lang_slug => author_archive_url
	 */
	private function get_author_translations( $user ) {
		if ( ! function_exists( 'pll_home_url' ) || ! isset( $user->ID ) ) {
			return [];
		}

		$default_url  = get_author_posts_url( $user->ID, $user->user_nicename ?? '' );
		$default_home = trailingslashit( home_url() );
		$translations = [];

		foreach ( $this->languages as $lang ) {
			if ( $lang === $this->default_lang ) {
				$translations[ $lang ] = $default_url;
			} else {
				$lang_home             = trailingslashit( pll_home_url( $lang ) );
				$translations[ $lang ] = str_replace( $default_home, $lang_home, $default_url );
			}
		}

		return count( $translations ) >= 2 ? $translations : [];
	}

	/**
	 * Detect BetterDocs translations by slug pattern when Polylang links are missing.
	 *
	 * @param \WP_Post $post
	 * @return array lang_slug => permalink
	 */
	private function get_betterdocs_translations( $post ) {
		if ( ! isset( $post->ID, $post->post_name ) ) {
			return [];
		}

		if ( ! function_exists( 'pll_get_post_language' ) ) {
			return [];
		}

		$translations = [];
		$current_lang = pll_get_post_language( $post->ID ) ?: $this->default_lang;
		$translations[ $current_lang ] = get_permalink( $post->ID );

		$base_slug = preg_replace( '/-\d+$/', '', $post->post_name );

		foreach ( $this->languages as $lang ) {
			if ( $lang === $current_lang ) {
				continue;
			}

			$possible_posts = get_posts( [
				'post_type'        => $post->post_type,
				'posts_per_page'   => 20,
				'post_status'      => 'publish',
				's'                => $base_slug,
				'suppress_filters' => false,
			] );

			foreach ( $possible_posts as $candidate ) {
				if ( pll_get_post_language( $candidate->ID ) !== $lang ) {
					continue;
				}

				$robots = get_post_meta( $candidate->ID, 'rank_math_robots', true );
				if ( is_array( $robots ) && in_array( 'noindex', $robots, true ) ) {
					continue;
				}

				$permalink = get_permalink( $candidate->ID );
				if ( $permalink ) {
					$translations[ $lang ] = $permalink;
				}
				break;
			}
		}

		return $translations;
	}

	/**
	 * Get all published translations for a taxonomy term.
	 *
	 * @param int $term_id
	 * @return array lang_slug => term_link
	 */
	private function get_term_translations( $term_id ) {
		if ( ! function_exists( 'pll_get_term_translations' ) ) {
			return [];
		}

		$trans_ids    = pll_get_term_translations( $term_id );
		$translations = [];

		foreach ( $trans_ids as $lang => $trans_id ) {
			$term_link = get_term_link( (int) $trans_id );
			if ( ! is_wp_error( $term_link ) ) {
				$translations[ $lang ] = $term_link;
			}
		}

		return $translations;
	}

	/**
	 * Normalize Polylang language slug to hreflang code.
	 *
	 * @param string $lang
	 * @return string
	 */
	private function normalize_hreflang_code( $lang ) {
		$map = [
			'es' => 'es',
			'en' => 'en',
			'fr' => 'fr',
			'de' => 'de',
			'it' => 'it',
			'pt' => 'pt',
			'ca' => 'ca',
			'eu' => 'eu',
			'gl' => 'gl',
		];

		return isset( $map[ $lang ] ) ? $map[ $lang ] : $lang;
	}

	// =========================================================================
	// CACHE MANAGEMENT
	// =========================================================================

	public function clear_sitemap_cache() {
		if ( class_exists( 'RankMath\\Sitemap\\Cache' ) ) {
			\RankMath\Sitemap\Cache::invalidate_storage();
		}
	}

	// =========================================================================
	// GSC COMPATIBILITY
	// =========================================================================

	public function fix_sitemap_headers( $headers ) {
		$headers['Content-Type'] = 'application/xml; charset=UTF-8';
		$headers['X-Robots-Tag'] = 'noindex, follow';
		return $headers;
	}

	// =========================================================================
	// HTML HEAD HREFLANG FIXES
	// =========================================================================

	/**
	 * Filter: rank_math/frontend/hreflang
	 *
	 * Two fixes in one pass:
	 *
	 * 1. Paginated archives — RankMath always uses the page-1 archive URL in its
	 *    hreflang array. On page 2+ we replace every language URL with the
	 *    correct paginated equivalent (/page/N/), giving pages a self-referencing
	 *    hreflang instead of pointing back to page 1.
	 *
	 * 2. x-default — RankMath sets x-default → homepage for every page. We
	 *    override it to point to the default-language version of the current page.
	 *
	 * Sets $xdefault_filter_ran = true to prevent the wp_head fallback from
	 * emitting duplicate tags.
	 *
	 * @param array $hreflang RankMath hreflang array [ locale => url, ... ]
	 * @return array
	 */
	public function fix_xdefault_hreflang( $hreflang ) {
		if ( ! is_array( $hreflang ) || empty( $hreflang ) ) {
			return $hreflang;
		}

		// Fix 1: paginated archives — append /page/N/ to every language URL.
		// Drop the stale x-default first; it will be rebuilt below.
		if ( is_paged() ) {
			$paged = (int) get_query_var( 'paged' );
			if ( $paged > 1 ) {
				$paged_hreflang = [];
				foreach ( $hreflang as $key => $url ) {
					if ( 'x-default' === $key ) {
						continue;
					}
					$paged_hreflang[ $key ] = trailingslashit( $url ) . 'page/' . $paged . '/';
				}
				$hreflang = $paged_hreflang;
			}
		}

		// Fix 2: x-default → default-language URL of the current page (not homepage).
		$default_url = $hreflang[ $this->default_lang ] ?? ( ! empty( $hreflang ) ? reset( $hreflang ) : null );

		if ( $default_url ) {
			$hreflang['x-default'] = $default_url;
		} else {
			unset( $hreflang['x-default'] );
		}

		// Signal to the fallback that this filter ran; no duplicate output needed.
		$this->xdefault_filter_ran = true;

		return $hreflang;
	}

	/**
	 * Action: wp_head (priority 999) — fallback hreflang output.
	 *
	 * Runs only if rank_math/frontend/hreflang filter did NOT fire (older RankMath).
	 * Also handles author archives unconditionally, because RankMath does not
	 * generate hreflang for author archives when the plugin skipped them before.
	 */
	public function fix_xdefault_hreflang_output() {
		if ( ! function_exists( 'pll_current_language' ) ) {
			return;
		}

		$default_lang = $this->default_lang;

		// --- Author archives: always output full hreflang set ---
		// RankMath may or may not handle these; we emit a complete, correct set.
		if ( is_author() ) {
			$user = get_queried_object();
			if ( ! ( $user instanceof \WP_User ) ) {
				return;
			}

			$translations = $this->get_author_translations( $user );
			if ( count( $translations ) < 2 ) {
				return;
			}

			$paged = is_paged() ? (int) get_query_var( 'paged' ) : 0;
			if ( $paged > 1 ) {
				foreach ( $translations as $lang => $url ) {
					$translations[ $lang ] = trailingslashit( $url ) . 'page/' . $paged . '/';
				}
			}

			$xdefault = $translations[ $default_lang ] ?? reset( $translations );

			echo "\n<!-- [rsh] hreflang author archive -->\n";
			foreach ( $translations as $lang => $url ) {
				printf(
					'<link rel="alternate" hreflang="%s" href="%s" />' . "\n",
					esc_attr( $this->normalize_hreflang_code( $lang ) ),
					esc_url( $url )
				);
			}
			printf(
				'<link rel="alternate" hreflang="x-default" href="%s" />' . "\n",
				esc_url( $xdefault )
			);

			return;
		}

		// --- For all other page types, only run if primary filter didn't fire ---
		if ( $this->xdefault_filter_ran ) {
			return;
		}

		if ( is_home() || is_front_page() ) {
			return;
		}

		$xdefault_url = null;
		$paged        = is_paged() ? (int) get_query_var( 'paged' ) : 0;

		if ( is_singular() ) {
			$translations = $this->get_post_translations( get_queried_object_id() );
			$xdefault_url = $translations[ $default_lang ] ?? ( ! empty( $translations ) ? reset( $translations ) : null );

		} elseif ( is_archive() || is_tax() || is_category() || is_tag() ) {
			$term = get_queried_object();
			if ( $term && isset( $term->term_id ) ) {
				$translations = $this->get_term_translations( $term->term_id );
				$xdefault_url = $translations[ $default_lang ] ?? null;
				if ( $xdefault_url && $paged > 1 ) {
					$xdefault_url = trailingslashit( $xdefault_url ) . 'page/' . $paged . '/';
				}
			}
		}

		$home_url = trailingslashit( home_url() );
		if ( ! $xdefault_url || trailingslashit( $xdefault_url ) === $home_url ) {
			return;
		}

		echo "\n<!-- [rsh] x-default fallback -->\n";
		printf(
			'<link rel="alternate" hreflang="x-default" href="%s" />' . "\n",
			esc_url( $xdefault_url )
		);
	}

	// =========================================================================
	// ADMIN NOTICES
	// =========================================================================

	public function activation_notice() {
		if ( ! get_option( 'rsh_activated' ) ) {
			return;
		}

		if ( isset( $_GET['rsh_dismiss'] ) ) {
			return;
		}

		$dismiss_url = wp_nonce_url(
			add_query_arg( 'rsh_dismiss', '1' ),
			'rsh_dismiss_nonce'
		);

		echo '<div class="notice notice-success is-dismissible">';
		echo '<p><strong>Replanta Sitemap Hreflang</strong> - ';
		echo 'Plugin activado correctamente. Las anotaciones hreflang se han inyectado en los sitemaps de RankMath. ';
		echo 'La cache de sitemaps ha sido limpiada automaticamente. ';
		echo '<a href="' . esc_url( home_url( '/sitemap_index.xml' ) ) . '" target="_blank">Ver sitemap</a>';
		echo ' | <a href="' . esc_url( $dismiss_url ) . '">Cerrar</a>';
		echo '</p></div>';
	}

	public function dismiss_notice() {
		if ( current_user_can( 'manage_options' ) &&
			 isset( $_GET['rsh_dismiss'] ) &&
			 wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'rsh_dismiss_nonce' ) ) {
			delete_option( 'rsh_activated' );
		}
	}

	public function notice_no_rankmath() {
		echo '<div class="notice notice-error"><p>';
		echo '<strong>Replanta Sitemap Hreflang</strong>: ';
		echo 'Rank Math SEO no esta activo. Este plugin requiere Rank Math para funcionar.';
		echo '</p></div>';
	}

	public function notice_no_polylang() {
		echo '<div class="notice notice-error"><p>';
		echo '<strong>Replanta Sitemap Hreflang</strong>: ';
		echo 'Polylang no esta activo. Este plugin requiere Polylang para funcionar.';
		echo '</p></div>';
	}
}

endif; // class_exists check

Replanta_Sitemap_Hreflang::get_instance();
