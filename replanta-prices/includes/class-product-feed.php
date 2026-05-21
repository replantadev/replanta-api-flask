<?php
/**
 * Replanta Product Feed
 *
 * Generates Google Merchant Center (RSS 2.0 / product XML) and
 * Meta / Instagram catalog feeds from the live price cache.
 *
 * Endpoints:
 *   GET /wp-json/replanta-prices/v1/feed/google   → XML (text/xml)
 *   GET /wp-json/replanta-prices/v1/feed/meta      → CSV (text/csv)
 *
 * Query params (both endpoints):
 *   ?currency=EUR|USD|MXN|... (default: EUR)
 *   ?period=monthly|annual    (default: monthly)
 *
 * @package Replanta_Prices
 */

defined( 'ABSPATH' ) || exit;

class Replanta_Prices_Product_Feed {

    const CACHE_KEY_GOOGLE = 'replanta_feed_google_v1';
    const CACHE_KEY_META   = 'replanta_feed_meta_v1';
    const CACHE_TTL        = 1800; // 30 min

    /* ─── Init ─────────────────────────────────────────────────────── */

    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );

        // Invalidate feed cache when prices are saved.
        add_action( 'update_option_' . Replanta_Prices_Cache::OPT_PRODUCTS, array( __CLASS__, 'bust_cache' ) );
    }

    /* ─── REST Routes ───────────────────────────────────────────────── */

    public static function register_routes() {
        $args = array(
            'methods'             => 'GET',
            'permission_callback' => '__return_true',
        );

        register_rest_route(
            'replanta-prices/v1',
            '/feed/google',
            array_merge( $args, array( 'callback' => array( __CLASS__, 'serve_google' ) ) )
        );

        register_rest_route(
            'replanta-prices/v1',
            '/feed/meta',
            array_merge( $args, array( 'callback' => array( __CLASS__, 'serve_meta' ) ) )
        );
    }

    /* ─── Serve Google ─────────────────────────────────────────────── */

    public static function serve_google( WP_REST_Request $request ) {
        $currency = strtoupper( sanitize_key( $request->get_param( 'currency' ) ?: 'EUR' ) );
        $period   = sanitize_key( $request->get_param( 'period' ) ?: 'monthly' );

        $cache_key = self::CACHE_KEY_GOOGLE . '_' . $currency . '_' . $period;
        $xml       = get_transient( $cache_key );

        if ( false === $xml ) {
            $xml = self::build_google_xml( $currency, $period );
            set_transient( $cache_key, $xml, self::CACHE_TTL );
        }

        // Clear any output WordPress or plugins may have buffered before this callback.
        while ( ob_get_level() > 0 ) {
            ob_end_clean();
        }

        header( 'Content-Type: application/xml; charset=utf-8', true );
        header( 'Cache-Control: public, max-age=900' );
        echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput -- already-escaped XML string
        exit;
    }

    /* ─── Serve Meta ────────────────────────────────────────────────── */

    public static function serve_meta( WP_REST_Request $request ) {
        $currency = strtoupper( sanitize_key( $request->get_param( 'currency' ) ?: 'EUR' ) );
        $period   = sanitize_key( $request->get_param( 'period' ) ?: 'monthly' );

        $cache_key = self::CACHE_KEY_META . '_' . $currency . '_' . $period;
        $csv       = get_transient( $cache_key );

        if ( false === $csv ) {
            $csv = self::build_meta_csv( $currency, $period );
            set_transient( $cache_key, $csv, self::CACHE_TTL );
        }

        // Clear any output WordPress or plugins may have buffered before this callback.
        while ( ob_get_level() > 0 ) {
            ob_end_clean();
        }

        header( 'Content-Type: text/csv; charset=utf-8', true );
        header( 'Content-Disposition: inline; filename="replanta-catalog-' . gmdate( 'Y-m-d' ) . '.csv"' );
        header( 'Cache-Control: public, max-age=900' );
        echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput -- CSV string
        exit;
    }

    /* ─── Cache bust ────────────────────────────────────────────────── */

    public static function bust_cache() {
        global $wpdb;
        // Delete all transients matching our feed cache pattern.
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_replanta_feed_%'
                OR option_name LIKE '_transient_timeout_replanta_feed_%'"
        );
    }

    /* ─── Build products list ───────────────────────────────────────── */

    /**
     * Returns a normalised flat product list for feed generation.
     *
     * @param string $currency  ISO 4217 currency code.
     * @param string $period    'monthly' or 'annual'.
     * @return array[]
     */
    private static function get_feed_products( $currency = 'EUR', $period = 'monthly' ) {
        $raw      = get_option( Replanta_Prices_Cache::OPT_PRODUCTS, array() );
        $defaults = Replanta_Prices_Cache::get_defaults();
        $catalogue = ! empty( $raw ) && is_array( $raw ) ? $raw : $defaults;

        $items = array();

        // Google taxonomy numeric IDs (unambiguous, from Google's official taxonomy).
        // https://www.google.com/basepages/producttype/taxonomy-with-ids.en-US.txt
        $category_map = array(
            'hosting'       => array(
                'google' => '5299',
                'fb'     => 'Software > Web Hosting',
                'label'  => 'Eco Web Hosting',
            ),
            'mantenimiento' => array(
                'google' => '5299',
                'fb'     => 'Services > IT Services',
                'label'  => 'Mantenimiento WordPress',
            ),
            'sapwoo'        => array(
                'google' => '5299',
                'fb'     => 'Software > E-Commerce Software',
                'label'  => 'SAP WooCommerce',
            ),
        );

        foreach ( $catalogue as $category_key => $category ) {
            if ( empty( $category['plans'] ) || ! is_array( $category['plans'] ) ) {
                continue;
            }

            $cat_label_google  = isset( $category_map[ $category_key ]['google'] ) ? $category_map[ $category_key ]['google'] : 'Software';
            $cat_label_fb      = isset( $category_map[ $category_key ]['fb'] )     ? $category_map[ $category_key ]['fb']     : 'Software';
            $cat_marketing     = isset( $category_map[ $category_key ]['label'] )  ? $category_map[ $category_key ]['label']  : ucfirst( $category_key );
            $cat_title         = isset( $category['title'] ) ? wp_strip_all_tags( $category['title'] ) : $category_key;

            foreach ( $category['plans'] as $plan_key => $plan ) {
                $price = self::resolve_price( $plan, $currency, $period );

                if ( null === $price ) {
                    continue; // no valid price for this currency/period combination.
                }

                // Clean title: remove HTML tags.
                $name     = wp_strip_all_tags( isset( $plan['name'] ) ? $plan['name'] : $plan_key );
                $subtitle = wp_strip_all_tags( isset( $plan['subtitle'] ) ? $plan['subtitle'] : '' );

                // Build features description.
                $desc_parts = array();
                if ( ! empty( $plan['features'] ) && is_array( $plan['features'] ) ) {
                    foreach ( $plan['features'] as $f ) {
                        $text = is_array( $f ) ? ( isset( $f['text'] ) ? $f['text'] : '' ) : $f;
                        $desc_parts[] = wp_strip_all_tags( $text );
                    }
                }

                $description_raw = $cat_marketing . ' - ' . $name . ( $subtitle ? ' (' . $subtitle . ')' : '' );
                if ( ! empty( $desc_parts ) ) {
                    $description_raw .= '. ' . implode( '. ', $desc_parts );
                }
                // Truncate to 5000 chars (Google limit).
                $description = mb_substr( $description_raw, 0, 5000 );

                // Canonical product URL.
                $landing_url = self::get_landing_url( $category_key, $plan_key );

                // Image: use theme asset or fallback to site logo.
                $image_url = self::get_product_image( $category_key, $plan_key, $plan );

                $items[] = array(
                    'id'             => 'replanta-' . $category_key . '-' . $plan_key,
                    'title'          => $cat_marketing . ' – ' . $name,
                    'description'    => $description,
                    'link'           => $landing_url,
                    'image_link'     => $image_url,
                    'price'          => number_format( $price, 2, '.', '' ) . ' ' . $currency,
                    'price_raw'      => $price,
                    'currency'       => $currency,
                    'brand'          => 'Replanta',
                    'condition'      => 'new',
                    'availability'   => 'in_stock',
                    'google_product_category' => $cat_label_google,
                    'fb_product_category'     => $cat_label_fb,
                    'category_key'   => $category_key,
                    'plan_key'       => $plan_key,
                    'pid'            => isset( $plan['pid'] ) ? (string) $plan['pid'] : '',
                    'featured'       => ! empty( $plan['featured'] ),
                    'period'         => $period,
                );
            }
        }

        return $items;
    }

    /**
     * Resolve the best price for a plan, currency and period.
     *
     * @param array  $plan
     * @param string $currency
     * @param string $period   'monthly' | 'annual'
     * @return float|null
     */
    private static function resolve_price( array $plan, $currency, $period ) {
        $period_key = ( 'annual' === $period ) ? 'y' : 'm';

        // Multi-currency prices array.
        if ( ! empty( $plan['prices'][ $currency ][ $period_key ] ) ) {
            return (float) $plan['prices'][ $currency ][ $period_key ];
        }

        // SAP-style setup price.
        if ( 'setup' === $period_key && ! empty( $plan['price_setup'] ) ) {
            return (float) $plan['price_setup'];
        }

        // EUR fallbacks.
        if ( 'm' === $period_key && ! empty( $plan['price_m'] ) ) {
            return (float) $plan['price_m'];
        }
        if ( 'y' === $period_key && ! empty( $plan['price_y'] ) ) {
            $y = (float) $plan['price_y'];
            return $y > 0 ? $y : null;
        }

        return null;
    }

    /**
     * Get the canonical landing URL for a product.
     */
    private static function get_landing_url( $category_key, $plan_key ) {
        $map = array(
            'hosting'       => 'eco-hosting',
            'mantenimiento' => 'mantenimiento-wordpress',
            'sapwoo'        => 'sap-woocommerce',
        );

        $slug = isset( $map[ $category_key ] ) ? $map[ $category_key ] : $category_key;
        return home_url( '/' . $slug . '/' );
    }

    /**
     * Get a public product image URL.
     * Priority: plan image_url field → plugin asset → landing page featured image → site icon.
     */
    private static function get_product_image( $category_key, $plan_key, array $plan = array() ) {
        // 1. Explicit image URL set in admin settings for this plan.
        if ( ! empty( $plan['image_url'] ) ) {
            return esc_url_raw( $plan['image_url'] );
        }

        // 2. Static asset in the plugin's assets folder.
        $relative = 'assets/img/' . $category_key . '/' . $plan_key . '.png';
        $abs_path  = REPLANTA_PRICES_DIR . $relative;
        if ( file_exists( $abs_path ) ) {
            return REPLANTA_PRICES_URL . $relative;
        }

        // Category-level fallback.
        $cat_rel  = 'assets/img/' . $category_key . '/cover.png';
        if ( file_exists( REPLANTA_PRICES_DIR . $cat_rel ) ) {
            return REPLANTA_PRICES_URL . $cat_rel;
        }

        // Featured image of the landing page post.
        $slug_map = array(
            'hosting'       => 'eco-hosting',
            'mantenimiento' => 'mantenimiento-wordpress',
            'sapwoo'        => 'sap-woocommerce',
        );
        $page_slug = isset( $slug_map[ $category_key ] ) ? $slug_map[ $category_key ] : $category_key;
        $page = get_page_by_path( $page_slug, OBJECT, array( 'page', 'post' ) );
        if ( $page && has_post_thumbnail( $page->ID ) ) {
            $thumb = get_the_post_thumbnail_url( $page->ID, 'large' );
            if ( $thumb ) {
                return $thumb;
            }
        }

        // Site icon (must be ≥ 100×100 for Google).
        $icon = get_site_icon_url( 512 );
        if ( $icon ) {
            return $icon;
        }

        return '';
    }

    /* ─── Build Google Merchant XML ─────────────────────────────────── */

    private static function build_google_xml( $currency, $period ) {
        $products  = self::get_feed_products( $currency, $period );
        $site_name = get_bloginfo( 'name' );
        $site_url  = home_url();
        $updated   = gmdate( 'Y-m-d\TH:i:s\Z' );

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n";
        $xml .= '  <channel>' . "\n";
        $xml .= '    <title>' . esc_xml( $site_name ) . ' Products</title>' . "\n";
        $xml .= '    <link>' . esc_xml( $site_url ) . '</link>' . "\n";
        $xml .= '    <description>Product catalog for ' . esc_xml( $site_name ) . '</description>' . "\n";
        $xml .= '    <updated>' . $updated . '</updated>' . "\n\n";

        foreach ( $products as $p ) {
            $xml .= '    <item>' . "\n";
            $xml .= '      <g:id>'                   . esc_xml( $p['id'] )                       . '</g:id>' . "\n";
            $xml .= '      <g:title>'                . esc_xml( $p['title'] )                    . '</g:title>' . "\n";
            $xml .= '      <g:description>'          . esc_xml( $p['description'] )              . '</g:description>' . "\n";
            $xml .= '      <g:link>'                 . esc_xml( $p['link'] )                     . '</g:link>' . "\n";
            if ( ! empty( $p['image_link'] ) ) {
                $xml .= '      <g:image_link>'       . esc_xml( $p['image_link'] )               . '</g:image_link>' . "\n";
            }
            $xml .= '      <g:price>'                . esc_xml( $p['price'] )                    . '</g:price>' . "\n";
            $xml .= '      <g:brand>'                . esc_xml( $p['brand'] )                    . '</g:brand>' . "\n";
            $xml .= '      <g:condition>'            . esc_xml( $p['condition'] )                . '</g:condition>' . "\n";
            $xml .= '      <g:availability>'         . esc_xml( $p['availability'] )             . '</g:availability>' . "\n";
            $xml .= '      <g:google_product_category>' . esc_xml( $p['google_product_category'] ) . '</g:google_product_category>' . "\n";
            $xml .= '      <g:identifier_exists>no</g:identifier_exists>' . "\n";
            if ( ! empty( $p['pid'] ) ) {
                $xml .= '      <g:mpn>' . esc_xml( $p['pid'] ) . '</g:mpn>' . "\n";
            }
            $xml .= '    </item>' . "\n";
        }

        $xml .= '  </channel>' . "\n";
        $xml .= '</rss>' . "\n";

        return $xml;
    }

    /* ─── Build Meta / Instagram CSV ────────────────────────────────── */

    private static function build_meta_csv( $currency, $period ) {
        $products = self::get_feed_products( $currency, $period );

        // Meta catalog required columns.
        $headers = array(
            'id',
            'title',
            'description',
            'availability',
            'condition',
            'price',
            'link',
            'image_link',
            'brand',
            'google_product_category',
            'fb_product_category',
        );

        $buf = fopen( 'php://temp', 'r+b' );
        fputcsv( $buf, $headers );

        foreach ( $products as $p ) {
            fputcsv( $buf, array(
                $p['id'],
                $p['title'],
                $p['description'],
                $p['availability'],
                $p['condition'],
                $p['price'],
                $p['link'],
                $p['image_link'],
                $p['brand'],
                $p['google_product_category'],
                $p['fb_product_category'],
            ) );
        }

        rewind( $buf );
        $csv = stream_get_contents( $buf );
        fclose( $buf );

        return $csv;
    }

    /* ─── Admin preview helper ──────────────────────────────────────── */

    /**
     * Returns a summary of feed items for the admin UI preview panel.
     *
     * @param string $currency
     * @param string $period
     * @return array{ count: int, items: array[], google_url: string, meta_url: string }
     */
    public static function get_feed_preview( $currency = 'EUR', $period = 'monthly' ) {
        $items = self::get_feed_products( $currency, $period );

        $base = rest_url( 'replanta-prices/v1/feed' );

        return array(
            'count'      => count( $items ),
            'items'      => $items,
            'google_url' => add_query_arg( array( 'currency' => $currency, 'period' => $period ), $base . '/google' ),
            'meta_url'   => add_query_arg( array( 'currency' => $currency, 'period' => $period ), $base . '/meta' ),
        );
    }
}
