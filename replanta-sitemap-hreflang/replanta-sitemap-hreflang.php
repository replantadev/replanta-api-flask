<?php
/**
 * Plugin Name: Replanta Sitemap Hreflang
 * Plugin URI:  https://replanta.net
 * Description: Adds hreflang annotations to RankMath XML sitemaps for Polylang multilingual support. Ensures Google correctly understands language relationships between ES and EN pages.
 * Version:     1.2.0
 * Author:      Replanta
 * Author URI:  https://replanta.net
 * License:     GPL-2.0+
 * Text Domain: replanta-sitemap-hreflang
 *
 * Requires Plugins: seo-by-rank-math, polylang
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Prevent double-loading (plugin + mu-plugin conflict)
// Only check constant, not class (class may exist from previous failed load)
if ( defined( 'RSH_VERSION' ) ) {
    return;
}

define( 'RSH_VERSION', '1.2.0' );
define( 'RSH_PLUGIN_FILE', __FILE__ );
define( 'RSH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Wrap class definition to prevent fatal errors if class already exists
if ( ! class_exists( 'Replanta_Sitemap_Hreflang' ) ) :

/**
 * Main plugin class - bridges RankMath sitemaps with Polylang translations.
 *
 * Strategy:
 * 1. Hook rank_math/sitemap/entry to capture post/term translations from Polylang
 * 2. Hook rank_math/sitemap/url to inject <xhtml:link> hreflang XML into each <url> block
 * 3. Hook rank_math/sitemap/{type}_urlset to add the xhtml namespace declaration
 * 4. Clear RankMath sitemap cache on activation so changes take effect immediately
 */
class Replanta_Sitemap_Hreflang {

    /**
     * Singleton instance.
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * Default language slug from Polylang.
     *
     * @var string
     */
    private $default_lang = 'es';

    /**
     * All active languages from Polylang.
     *
     * @var array
     */
    private $languages = [];

    /**
     * Get singleton instance.
     *
     * @return self
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - register activation hook and plugins_loaded.
     */
    private function __construct() {
        register_activation_hook( RSH_PLUGIN_FILE, [ $this, 'on_activation' ] );
        add_action( 'plugins_loaded', [ $this, 'init' ], 20 );
    }

    /**
     * Plugin activation - clear RankMath sitemap cache.
     */
    public function on_activation() {
        if ( class_exists( 'RankMath\\Sitemap\\Cache' ) ) {
            \RankMath\Sitemap\Cache::invalidate_storage();
        }

        // Also delete any transient-based caches
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%rank_math_sitemap%'"
        );

        // Set flag for admin notice
        update_option( 'rsh_activated', true );
    }

    /**
     * Initialize if both RankMath and Polylang are active.
     */
    public function init() {
        // Check dependencies
        if ( ! class_exists( 'RankMath' ) ) {
            add_action( 'admin_notices', [ $this, 'notice_no_rankmath' ] );
            return;
        }

        if ( ! function_exists( 'pll_get_post_translations' ) ) {
            add_action( 'admin_notices', [ $this, 'notice_no_polylang' ] );
            return;
        }

        // Auto-clear RankMath sitemap cache on first load (mu-plugins don't have activation hooks)
        $this->maybe_clear_cache();

        // Get Polylang language config
        $this->default_lang = pll_default_language( 'slug' );
        $this->languages    = pll_languages_list( [ 'fields' => 'slug' ] );

        // --- Sitemap hooks ---

        // 1. Capture translation data from Polylang during entry processing
        add_filter( 'rank_math/sitemap/entry', [ $this, 'capture_translations' ], 20, 3 );

        // 2. Inject hreflang XML into each <url> block
        add_filter( 'rank_math/sitemap/url', [ $this, 'inject_hreflang_xml' ], 20, 2 );

        // 3. Add xhtml namespace to urlset for all known sitemap types
        $this->register_urlset_filters();

        // 4. Disable RankMath sitemap caching so hreflang is always fresh
        //    (Enable this only if translations change frequently)
        // add_filter( 'rank_math/sitemap/enable_caching', '__return_false' );

        // Admin hooks
        add_action( 'admin_notices', [ $this, 'activation_notice' ] );
        add_action( 'admin_init',    [ $this, 'dismiss_notice' ] );

        // Clear cache when translations are saved
        add_action( 'pll_save_post', [ $this, 'clear_sitemap_cache' ] );
        add_action( 'pll_save_term', [ $this, 'clear_sitemap_cache' ] );

        // --- GSC COMPATIBILITY FIXES ---
        // 5. Remove XSL stylesheet (fixes "formato no compatible" in GSC)
        add_filter( 'rank_math/sitemap/stylesheet_url', '__return_empty_string' );

        // 6. Ensure proper XML headers
        add_filter( 'rank_math/sitemap/http_headers', [ $this, 'fix_sitemap_headers' ], 20 );

        // 7. Fix x-default hreflang in HTML <head>:
        //    RankMath sets x-default → homepage for ALL pages, which pollutes all hreflang
        //    groups and causes Ahrefs errors ("more than one page linked for same language").
        //    We override x-default to point to the default-language URL of the current page.
        //    Filter name varies by RankMath version - hook both to be safe.
        add_filter( 'rank_math/frontend/hreflang', [ $this, 'fix_xdefault_hreflang' ], 20 );
        // Fallback: intercept the rendered HTML output and fix x-default inline
        add_action( 'wp_head', [ $this, 'fix_xdefault_hreflang_output' ], 999 );

        // Debug/verification endpoint: /wp-admin/admin-ajax.php?action=rsh_check
        add_action( 'wp_ajax_rsh_check', [ $this, 'ajax_check' ] );
    }

    /**
     * Auto-clear RankMath sitemap cache on first load after plugin install/update.
     * Since mu-plugins don't have activation hooks, we use a version flag.
     */
    private function maybe_clear_cache() {
        $stored_version = get_option( 'rsh_version', '' );
        if ( $stored_version !== RSH_VERSION ) {
            // Version changed or first install - clear sitemap cache
            if ( class_exists( 'RankMath\\Sitemap\\Cache' ) ) {
                \RankMath\Sitemap\Cache::invalidate_storage();
            }

            // Also clear transients related to sitemaps
            global $wpdb;
            $wpdb->query(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient%rank_math%sitemap%'"
            );

            update_option( 'rsh_version', RSH_VERSION );
            update_option( 'rsh_activated', true );
        }
    }

    /**
     * AJAX endpoint to verify hreflang is working.
     * Access: /wp-admin/admin-ajax.php?action=rsh_check
     */
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

        // Test with homepage
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

        // --- Test with a few recent posts
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

        // --- Test BetterDocs posts ---
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
            echo "  No BetterDocs pages found (post_type='docs' no existe)\n";
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

    /**
     * Register urlset namespace filters for all sitemap types.
     * This adds xmlns:xhtml to the <urlset> tag so hreflang is valid XML.
     */
    private function register_urlset_filters() {
        // Get all public post types that have sitemaps
        $post_types = get_post_types( [ 'public' => true ], 'names' );

        // Get all public taxonomies that have sitemaps
        $taxonomies = get_taxonomies( [ 'public' => true ], 'names' );

        // Combine all possible sitemap slug types
        $types = array_merge(
            array_values( $post_types ),
            array_values( $taxonomies ),
            [ 'author' ] // Author sitemap
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
     * in a custom 'hreflang' key of the URL array. This data persists
     * through the RankMath pipeline and is available in the sitemap/url filter.
     *
     * @param array  $url    URL entry array (loc, mod, images, etc.).
     * @param string $type   Entry type: 'post', 'term', or 'user'.
     * @param object $object The post, term, or user object.
     * @return array Modified URL entry with hreflang data.
     */
    public function capture_translations( $url, $type, $object ) {
        if ( ! isset( $url['loc'] ) ) {
            return $url;
        }

        $translations = [];

        if ( 'post' === $type && isset( $object->ID ) ) {
            $translations = $this->get_post_translations( $object->ID );
            
            // If Polylang didn't find translations, try BetterDocs pattern matching
            if ( count( $translations ) < 2 ) {
                $bd_translations = $this->get_betterdocs_translations( $object );
                if ( count( $bd_translations ) >= 2 ) {
                    $translations = $bd_translations;
                }
            }

        } elseif ( 'term' === $type && isset( $object->term_id ) ) {
            $translations = $this->get_term_translations( $object->term_id );

        } elseif ( 'user' === $type ) {
            // Authors don't have language-specific translations in Polylang.
            // Skip hreflang for author sitemaps.
            return $url;
        }

        // Only add hreflang if there are at least 2 language versions
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
     *
     * @param string $output XML string for the <url> block.
     * @param array  $url    URL entry array (may contain 'hreflang' from capture_translations).
     * @return string Modified XML with hreflang annotations.
     */
    public function inject_hreflang_xml( $output, $url ) {
        if ( empty( $url['hreflang'] ) || ! is_array( $url['hreflang'] ) ) {
            return $output;
        }

        $hreflang_xml = '';

        // Add hreflang for each language version
        foreach ( $url['hreflang'] as $lang => $href ) {
            $hreflang_xml .= sprintf(
                "\t\t<xhtml:link rel=\"alternate\" hreflang=\"%s\" href=\"%s\" />\n",
                esc_attr( $this->normalize_hreflang_code( $lang ) ),
                esc_url( $href )
            );
        }

        // Add x-default pointing to the default language (usually ES)
        if ( isset( $url['hreflang'][ $this->default_lang ] ) ) {
            $hreflang_xml .= sprintf(
                "\t\t<xhtml:link rel=\"alternate\" hreflang=\"x-default\" href=\"%s\" />\n",
                esc_url( $url['hreflang'][ $this->default_lang ] )
            );
        }

        // Insert hreflang links before </url>
        if ( ! empty( $hreflang_xml ) ) {
            $output = str_replace( '</url>', $hreflang_xml . "\t</url>", $output );
        }

        return $output;
    }

    /**
     * Filter: rank_math/sitemap/{type}_urlset
     *
     * Add the xhtml namespace declaration to the <urlset> element.
     * Without this, the hreflang tags would be invalid XML.
     *
     * @param string $urlset The <urlset> opening tag XML.
     * @return string Modified urlset with xhtml namespace.
     */
    public function add_xhtml_namespace( $urlset ) {
        // Don't add if already present
        if ( false !== strpos( $urlset, 'xmlns:xhtml=' ) ) {
            return $urlset;
        }

        // Insert xhtml namespace before the closing >
        $urlset = str_replace(
            'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
            'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">',
            $urlset
        );

        return $urlset;
    }

    // =========================================================================
    // POLYLANG TRANSLATION HELPERS
    // =========================================================================

    /**
     * Get all published translations for a post.
     *
     * @param int $post_id Post ID.
     * @return array Associative array of lang_slug => permalink.
     */
    private function get_post_translations( $post_id ) {
        if ( ! function_exists( 'pll_get_post_translations' ) ) {
            return [];
        }

        $trans_ids = pll_get_post_translations( $post_id );
        $translations = [];

        foreach ( $trans_ids as $lang => $trans_id ) {
            // Only include published posts
            if ( 'publish' !== get_post_status( $trans_id ) ) {
                continue;
            }

            // Check the post is not noindex in RankMath
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
     * Detectar traducciones de BetterDocs por patrón de slug o post ID.
     * Usado cuando Polylang no encuentra traducciones vinculadas.
     *
     * @param \WP_Post $post El post actual de BetterDocs
     * @return array Associative array of lang_slug => permalink.
     */
    private function get_betterdocs_translations( $post ) {
        if ( ! isset( $post->ID, $post->post_name ) ) {
            return [];
        }

        $translations = [];
        
        // Obtener el idioma del post actual en Polylang
        if ( ! function_exists( 'pll_get_post_language' ) ) {
            return $translations;
        }

        $current_lang = pll_get_post_language( $post->ID );
        if ( ! $current_lang ) {
            $current_lang = $this->default_lang;
        }

        $translations[ $current_lang ] = get_permalink( $post->ID );

        // Estrategia 1: Buscar por slug limpio (sin sufijo de idioma)
        // Ej: "doc-2" → buscar otros posts con slug "doc" o similar
        $base_slug = preg_replace( '/-\d+$/', '', $post->post_name );
        
        // Estrategia 2: Para cada idioma disponible, buscar posts con slug relacionado
        foreach ( $this->languages as $lang ) {
            if ( $lang === $current_lang ) {
                continue; // Ya lo tenemos
            }

            // Buscar posts del mismo post type que podrían ser la traducción
            $possible_posts = get_posts( [
                'post_type'      => $post->post_type,
                'posts_per_page' => 20,
                'post_status'    => 'publish',
                's'              => $base_slug, // Búsqueda por nombre
                'suppress_filters' => false,
            ] );

            foreach ( $possible_posts as $candidate ) {
                // Verificar que tiene el idioma esperado en Polylang
                $candidate_lang = pll_get_post_language( $candidate->ID );
                
                if ( $candidate_lang === $lang ) {
                    // Asegurarse de que no está marcado como noindex
                    $robots = get_post_meta( $candidate->ID, 'rank_math_robots', true );
                    if ( ! ( is_array( $robots ) && in_array( 'noindex', $robots, true ) ) ) {
                        $permalink = get_permalink( $candidate->ID );
                        if ( $permalink ) {
                            $translations[ $lang ] = $permalink;
                        }
                    }
                    break; // Solo necesitamos una por idioma
                }
            }
        }

        return $translations;
    }

    /**
     * Get all published translations for a taxonomy term.
     *
     * @param int $term_id Term ID.
     * @return array Associative array of lang_slug => term_link.
     */
    private function get_term_translations( $term_id ) {
        if ( ! function_exists( 'pll_get_term_translations' ) ) {
            return [];
        }

        $trans_ids = pll_get_term_translations( $term_id );
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
     * Normalize language slug to hreflang format.
     * E.g., 'es' stays 'es', 'en' stays 'en', 'pt-br' stays 'pt-br'.
     *
     * @param string $lang Polylang language slug.
     * @return string Normalized hreflang code.
     */
    private function normalize_hreflang_code( $lang ) {
        // Map common Polylang slugs to proper hreflang codes
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

    /**
     * Clear RankMath sitemap cache when Polylang translations change.
     */
    public function clear_sitemap_cache() {
        if ( class_exists( 'RankMath\\Sitemap\\Cache' ) ) {
            \RankMath\Sitemap\Cache::invalidate_storage();
        }
    }

    // =========================================================================
    // GSC COMPATIBILITY FIXES (v1.1.0)
    // =========================================================================

    /**
     * Fix HTTP headers for GSC compatibility.
     * Ensures proper Content-Type for XML parsing.
     *
     * @param array $headers HTTP headers array.
     * @return array Modified headers.
     */
    public function fix_sitemap_headers( $headers ) {
        $headers['Content-Type'] = 'application/xml; charset=UTF-8';
        $headers['X-Robots-Tag'] = 'noindex, follow';
        return $headers;
    }

    // =========================================================================
    // HREFLANG HTML FIX (v1.2.0)
    // =========================================================================

    /**
     * Fix x-default hreflang in the HTML <head>.
     *
     * Problem: RankMath sets hreflang="x-default" → homepage for EVERY page.
     * This pollutes all hreflang groups by connecting every page to the homepage.
     * Ahrefs then sees the homepage's own hreflang="es" pointing to itself,
     * alongside the page's hreflang="es", and reports "more than one page linked
     * for the same language".
     *
     * Fix: Change x-default to point to the default-language version of the
     * current page instead of the global homepage.
     *
     * @param array $hreflang RankMath hreflang array [ locale => url, ... ].
     * @return array Modified hreflang array.
     */
    public function fix_xdefault_hreflang( $hreflang ) {
        if ( ! is_array( $hreflang ) || empty( $hreflang ) ) {
            return $hreflang;
        }

        // Find the default language URL to use as x-default
        $default_url = null;

        if ( isset( $hreflang[ $this->default_lang ] ) ) {
            $default_url = $hreflang[ $this->default_lang ];
        } elseif ( ! empty( $hreflang ) ) {
            // Fallback: use first URL in the array
            $default_url = reset( $hreflang );
        }

        // Override x-default to point to the default language URL (not homepage)
        if ( $default_url ) {
            $hreflang['x-default'] = $default_url;
        } else {
            // Remove x-default entirely if no default URL found
            unset( $hreflang['x-default'] );
        }

        return $hreflang;
    }

    /**
     * Fallback fix: replace x-default hreflang in rendered HTML output.
     * Runs on wp_head at priority 999 (after RankMath outputs its tags).
     * Replaces the x-default href if it points to the homepage on non-homepage pages.
     */
    public function fix_xdefault_hreflang_output() {
        // Only on non-homepage pages
        if ( is_home() || is_front_page() ) {
            return;
        }

        if ( ! function_exists( 'pll_current_language' ) ) {
            return;
        }

        $current_lang  = pll_current_language( 'slug' );
        $default_lang  = $this->default_lang;
        $home_url      = trailingslashit( home_url() );

        // Determine x-default target: default-language URL of current page
        $xdefault_url = null;

        // For posts/pages, look up the default language translation
        if ( is_singular() ) {
            $post_id       = get_queried_object_id();
            $translations  = $this->get_post_translations( $post_id );
            if ( isset( $translations[ $default_lang ] ) ) {
                $xdefault_url = $translations[ $default_lang ];
            } elseif ( $current_lang !== $default_lang && ! empty( $translations ) ) {
                $xdefault_url = reset( $translations );
            }
        } elseif ( is_archive() || is_tax() ) {
            $term = get_queried_object();
            if ( $term && isset( $term->term_id ) ) {
                $translations = $this->get_term_translations( $term->term_id );
                if ( isset( $translations[ $default_lang ] ) ) {
                    $xdefault_url = $translations[ $default_lang ];
                }
            }
        }

        // If we have a correct x-default URL and it's different from homepage,
        // output a corrective link that browsers/crawlers should prefer
        // (overriding the earlier one via JavaScript or canonical approach is not ideal;
        // instead we use output buffering to replace it)
        if ( ! $xdefault_url || trailingslashit( $xdefault_url ) === $home_url ) {
            return;
        }

        // Start output buffer to capture and fix subsequent wp_head output
        // Note: we output a replacement script-based override since we can't
        // modify already-output HTML. The real fix is via the 'rank_math/frontend/hreflang'
        // filter above. This is a belt-and-suspenders fallback visible to crawlers.
        echo "\n<!-- [rsh] x-default corrected by replanta-sitemap-hreflang -->\n";
        printf(
            '<link rel="alternate" hreflang="x-default" href="%s" />' . "\n",
            esc_url( $xdefault_url )
        );
    }

    // =========================================================================
    // ADMIN NOTICES
    // =========================================================================

    /**
     * Show activation success notice.
     */
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

    /**
     * Dismiss activation notice.
     * SEGURIDAD: Verificar capacidades antes de eliminar opción
     */
    public function dismiss_notice() {
        if ( current_user_can( 'manage_options' ) && 
             isset( $_GET['rsh_dismiss'] ) && 
             wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'rsh_dismiss_nonce' ) ) {
            delete_option( 'rsh_activated' );
        }
    }

    /**
     * Admin notice: RankMath not found.
     */
    public function notice_no_rankmath() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>Replanta Sitemap Hreflang</strong>: ';
        echo 'Rank Math SEO no esta activo. Este plugin requiere Rank Math para funcionar.';
        echo '</p></div>';
    }

    /**
     * Admin notice: Polylang not found.
     */
    public function notice_no_polylang() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>Replanta Sitemap Hreflang</strong>: ';
        echo 'Polylang no esta activo. Este plugin requiere Polylang para funcionar.';
        echo '</p></div>';
    }
}

endif; // class_exists check

// Initialize the plugin
Replanta_Sitemap_Hreflang::get_instance();
