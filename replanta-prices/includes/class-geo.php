<?php
/**
 * Geo-detection bridge with multi-currency support.
 *
 * Regions:  eur → EUR | usd → USD | latam → local currency or USD fallback
 * On English pages (?lang=en or WPML/Polylang en): forces USD.
 *
 * @package Replanta_Prices
 */

defined( 'ABSPATH' ) || exit;

class Replanta_Prices_Geo {

    const COOKIE_REGION   = 'replanta_geo_region';
    const COOKIE_COUNTRY  = 'replanta_geo_cc';
    const COOKIE_CURRENCY = 'replanta_geo_currency';

    /** @var string|null Cached region for current request */
    private static $region   = null;
    /** @var string|null Cached currency code */
    private static $currency = null;
    /** @var string|null Cached country code */
    private static $country  = null;

    /** European country codes */
    const EUROPE_CODES = array(
        'AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE',
        'IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE',
        'IS','LI','NO','CH','GB',
        'AL','AD','BA','GE','XK','ME','MK','MD','MC','RS','SM','TR','UA','VA',
    );

    /** North America → USD */
    const NA_CODES = array( 'US', 'CA' );

    /**
     * LATAM country → preferred currency.
     * Keys not listed here fall back to USD.
     */
    const LATAM_CURRENCIES = array(
        // América del Sur
        'AR' => 'ARS', // Argentina
        'BO' => 'BOB', // Bolivia
        'BR' => 'BRL', // Brasil
        'CL' => 'CLP', // Chile
        'CO' => 'COP', // Colombia
        'EC' => 'USD', // Ecuador (dolarizado)
        'GY' => 'GYD', // Guyana
        'PY' => 'PYG', // Paraguay
        'PE' => 'PEN', // Perú
        'SR' => 'SRD', // Surinam
        'UY' => 'UYU', // Uruguay
        'VE' => 'USD', // Venezuela (fallback USD)
        // América Central
        'BZ' => 'BZD', // Belice
        'CR' => 'CRC', // Costa Rica
        'SV' => 'USD', // El Salvador (dolarizado)
        'GT' => 'GTQ', // Guatemala
        'HN' => 'HNL', // Honduras
        'MX' => 'MXN', // México
        'NI' => 'NIO', // Nicaragua
        'PA' => 'USD', // Panamá (dolarizado)
        // Caribe
        'CU' => 'USD', // Cuba (fallback)
        'DO' => 'DOP', // República Dominicana
        'HT' => 'HTG', // Haití
        'JM' => 'JMD', // Jamaica
        'PR' => 'USD', // Puerto Rico (USD)
        'TT' => 'TTD', // Trinidad y Tobago
        'BB' => 'BBD', // Barbados
        'BS' => 'BSD', // Bahamas
        'AG' => 'XCD', // Antigua y Barbuda
        'DM' => 'XCD', // Dominica
        'GD' => 'XCD', // Granada
        'KN' => 'XCD', // San Cristóbal y Nieves
        'LC' => 'XCD', // Santa Lucía
        'VC' => 'XCD', // San Vicente
        'AW' => 'AWG', // Aruba
        'CW' => 'ANG', // Curazao
        'SX' => 'ANG', // Sint Maarten
        'TC' => 'USD', // Turcos y Caicos
        'KY' => 'KYD', // Islas Caimán
        'VG' => 'USD', // Islas Vírgenes Británicas
        'VI' => 'USD', // Islas Vírgenes US
    );

    /** Currency display config: symbol, position ('before'|'after'), decimal sep, thousands sep */
    const CURRENCY_FORMAT = array(
        'EUR' => array( 'symbol' => '€', 'pos' => 'after',  'dec' => ',', 'thou' => '.' ),
        'USD' => array( 'symbol' => '$', 'pos' => 'before', 'dec' => '.', 'thou' => ',' ),
        'MXN' => array( 'symbol' => '$', 'pos' => 'before', 'dec' => '.', 'thou' => ',' ),
        'COP' => array( 'symbol' => '$', 'pos' => 'before', 'dec' => ',', 'thou' => '.' ),
        'CLP' => array( 'symbol' => '$', 'pos' => 'before', 'dec' => ',', 'thou' => '.' ),
        'ARS' => array( 'symbol' => '$', 'pos' => 'before', 'dec' => ',', 'thou' => '.' ),
        'PEN' => array( 'symbol' => 'S/', 'pos' => 'before', 'dec' => '.', 'thou' => ',' ),
        'UYU' => array( 'symbol' => '$',  'pos' => 'before', 'dec' => ',', 'thou' => '.' ),
        'BRL' => array( 'symbol' => 'R$', 'pos' => 'before', 'dec' => ',', 'thou' => '.' ),
        'CRC' => array( 'symbol' => '₡',  'pos' => 'before', 'dec' => ',', 'thou' => '.' ),
        'DOP' => array( 'symbol' => 'RD$','pos' => 'before', 'dec' => '.', 'thou' => ',' ),
        'GTQ' => array( 'symbol' => 'Q',  'pos' => 'before', 'dec' => '.', 'thou' => ',' ),
        'HNL' => array( 'symbol' => 'L',  'pos' => 'before', 'dec' => '.', 'thou' => ',' ),
        'NIO' => array( 'symbol' => 'C$', 'pos' => 'before', 'dec' => '.', 'thou' => ',' ),
        'PYG' => array( 'symbol' => '₲',  'pos' => 'before', 'dec' => ',', 'thou' => '.' ),
        'BOB' => array( 'symbol' => 'Bs', 'pos' => 'before', 'dec' => ',', 'thou' => '.' ),
        'GYD' => array( 'symbol' => '$',  'pos' => 'before', 'dec' => '.', 'thou' => ',' ),
        'SRD' => array( 'symbol' => '$',  'pos' => 'before', 'dec' => ',', 'thou' => '.' ),
        'BZD' => array( 'symbol' => 'BZ$','pos' => 'before', 'dec' => '.', 'thou' => ',' ),
        'HTG' => array( 'symbol' => 'G',  'pos' => 'before', 'dec' => ',', 'thou' => '.' ),
        'JMD' => array( 'symbol' => 'J$', 'pos' => 'before', 'dec' => '.', 'thou' => ',' ),
        'TTD' => array( 'symbol' => 'TT$','pos' => 'before', 'dec' => '.', 'thou' => ',' ),
        'BBD' => array( 'symbol' => 'Bds$','pos'=> 'before', 'dec' => '.', 'thou' => ',' ),
        'BSD' => array( 'symbol' => 'B$', 'pos' => 'before', 'dec' => '.', 'thou' => ',' ),
        'XCD' => array( 'symbol' => 'EC$','pos' => 'before', 'dec' => '.', 'thou' => ',' ),
        'AWG' => array( 'symbol' => 'Afl','pos' => 'before', 'dec' => '.', 'thou' => ',' ),
        'ANG' => array( 'symbol' => 'NAf','pos' => 'before', 'dec' => ',', 'thou' => '.' ),
        'KYD' => array( 'symbol' => 'CI$','pos' => 'before', 'dec' => '.', 'thou' => ',' ),
    );

    public static function init() {
        add_action( 'init', array( __CLASS__, 'set_geo_cookies' ) );
        add_action( 'litespeed_vary_add', array( __CLASS__, 'litespeed_vary_register' ) );
        add_filter( 'litespeed_vary_cookies', array( __CLASS__, 'litespeed_vary_cookies' ) );
    }

    /* ================================================================
       COOKIES & CACHE
       ================================================================ */

    /**
     * Set geo cookies on first visit using Cloudflare CF-IPCountry header.
     * Tells LiteSpeed NOT to cache until cookies exist (avoids wrong-currency cache).
     */
    public static function set_geo_cookies() {
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
            return;
        }

        $has_region   = ! empty( $_COOKIE[ self::COOKIE_REGION ] );
        $has_currency = ! empty( $_COOKIE[ self::COOKIE_CURRENCY ] );

        $cc = self::detect_country();
        if ( ! empty( $cc ) ) {
            $region   = self::country_to_region( $cc );
            $currency = self::resolve_currency_from_country( $cc );

            if ( empty( $_COOKIE[ self::COOKIE_COUNTRY ] ) ) {
                setcookie( self::COOKIE_COUNTRY, $cc, array(
                    'expires'  => time() + 86400,
                    'path'     => '/',
                    'secure'   => is_ssl(),
                    'httponly'  => false,
                    'samesite' => 'Lax',
                ) );
                $_COOKIE[ self::COOKIE_COUNTRY ] = $cc; // Immediately available for this request
            }

            if ( ! $has_region ) {
                setcookie( self::COOKIE_REGION, $region, array(
                    'expires'  => time() + 86400,
                    'path'     => '/',
                    'secure'   => is_ssl(),
                    'httponly'  => false,
                    'samesite' => 'Lax',
                ) );
                $_COOKIE[ self::COOKIE_REGION ] = $region;
            }

            if ( ! $has_currency || empty( $_COOKIE[ self::COOKIE_CURRENCY ] ) || $_COOKIE[ self::COOKIE_CURRENCY ] !== $currency ) {
                setcookie( self::COOKIE_CURRENCY, $currency, array(
                    'expires'  => time() + 86400,
                    'path'     => '/',
                    'secure'   => is_ssl(),
                    'httponly'  => false,
                    'samesite' => 'Lax',
                ) );
                $_COOKIE[ self::COOKIE_CURRENCY ] = $currency;
            }
        } elseif ( self::is_english() ) {
            if ( ! $has_region ) {
                setcookie( self::COOKIE_REGION, 'usd', array(
                    'expires'  => time() + 86400,
                    'path'     => '/',
                    'secure'   => is_ssl(),
                    'httponly'  => false,
                    'samesite' => 'Lax',
                ) );
                $_COOKIE[ self::COOKIE_REGION ] = 'usd';
            }

            if ( ! $has_currency ) {
                setcookie( self::COOKIE_CURRENCY, 'USD', array(
                    'expires'  => time() + 86400,
                    'path'     => '/',
                    'secure'   => is_ssl(),
                    'httponly'  => false,
                    'samesite' => 'Lax',
                ) );
                $_COOKIE[ self::COOKIE_CURRENCY ] = 'USD';
            }
        }

        // If geo cookies are not settled yet, do not cache this response.
        if ( ! $has_region || ! $has_currency ) {
            do_action( 'litespeed_control_set_nocache', 'replanta-prices: no region cookie yet' );
            if ( ! headers_sent() ) {
                header( 'X-LiteSpeed-Cache-Control: no-cache' );
            }
        }
    }

    /**
     * Register replanta_geo_region as a LiteSpeed Cache vary cookie.
     * This creates separate cached variants per region (eur / usd / latam).
     */
    public static function litespeed_vary_register() {
        do_action( 'litespeed_vary_append', self::COOKIE_REGION );
        do_action( 'litespeed_vary_append', self::COOKIE_CURRENCY );
    }

    /**
     * Declare the cookie to LiteSpeed vary filter.
     */
    public static function litespeed_vary_cookies( $cookies ) {
        $cookies[] = self::COOKIE_REGION;
        $cookies[] = self::COOKIE_CURRENCY;
        return $cookies;
    }

    /* ================================================================
       LANGUAGE DETECTION
       ================================================================ */

    /**
     * Get current page language: 'es' or 'en' (or other).
     * Checks WPML, Polylang, TranslatePress, or ?lang= param.
     *
     * @return string 2-letter lang code; defaults to 'es'.
     */
    public static function get_current_lang() {
        // WPML
        if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
            return (string) constant( 'ICL_LANGUAGE_CODE' );
        }
        // Polylang
        if ( function_exists( 'pll_current_language' ) ) {
            $lang = call_user_func( 'pll_current_language', 'slug' );
            if ( $lang ) {
                return $lang;
            }
        }
        // TranslatePress — URL-based
        if ( class_exists( 'TRP_Translate_Press' ) ) {
            $settings = get_option( 'trp_settings', array() );
            if ( ! empty( $settings['url-slugs'] ) ) {
                $uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
                foreach ( $settings['url-slugs'] as $lang => $slug ) {
                    if ( $slug && strpos( $uri, '/' . $slug . '/' ) !== false ) {
                        return $lang;
                    }
                }
            }
        }
        // Manual override (?lang=en)
        if ( ! empty( $_GET['lang'] ) ) {
            return sanitize_key( $_GET['lang'] );
        }
        // WP locale
        $locale = get_locale();
        return substr( $locale, 0, 2 );
    }

    /**
     * Is the current page in English?
     */
    public static function is_english() {
        return 'en' === self::get_current_lang();
    }

    /* ================================================================
       REGION & CURRENCY DETECTION
       ================================================================ */

    /**
     * Get the visitor's geo region: 'eur', 'usd', or 'latam'.
     */
    public static function get_region() {
        if ( null !== self::$region ) {
            return self::$region;
        }

        // English page → force usd
        if ( self::is_english() ) {
            self::$region = 'usd';
            return self::$region;
        }

        // 1. Cookie (most reliable with LiteSpeed cache)
        if ( ! empty( $_COOKIE[ self::COOKIE_REGION ] ) ) {
            $val = sanitize_text_field( $_COOKIE[ self::COOKIE_REGION ] );
            if ( in_array( $val, array( 'eur', 'usd', 'latam' ), true ) ) {
                self::$country = self::detect_country();
                self::$region = $val;
                return self::$region;
            }
        }

        // 2. Cloudflare header / cookie fallback
        $cc = self::detect_country();
        self::$country = $cc;
        self::$region  = self::country_to_region( $cc );
        return self::$region;
    }

    /**
     * Get the resolved currency code: EUR, USD, MXN, COP, etc.
     */
    public static function get_currency_code() {
        if ( null !== self::$currency ) {
            return self::$currency;
        }

        if ( self::is_english() ) {
            self::$currency = 'USD';
            return self::$currency;
        }

        if ( ! empty( $_COOKIE[ self::COOKIE_CURRENCY ] ) ) {
            $currency = strtoupper( sanitize_text_field( $_COOKIE[ self::COOKIE_CURRENCY ] ) );
            if ( self::is_valid_currency_code( $currency ) ) {
                self::$currency = $currency;
                return self::$currency;
            }
        }

        $region = self::get_region();

        if ( 'eur' === $region ) {
            self::$currency = 'EUR';
            return self::$currency;
        }

        if ( null === self::$country || '' === self::$country ) {
            self::$country = self::detect_country();
        }

        self::$currency = self::resolve_currency_from_country( self::$country );

        return self::$currency;
    }

    private static function resolve_currency_from_country( $cc ) {
        if ( self::is_english() ) {
            return 'USD';
        }

        $region = self::country_to_region( $cc );
        if ( 'eur' === $region ) {
            return 'EUR';
        }
        if ( 'latam' === $region ) {
            return isset( self::LATAM_CURRENCIES[ $cc ] )
                ? self::LATAM_CURRENCIES[ $cc ]
                : 'USD';
        }
        return 'USD';
    }

    private static function is_valid_currency_code( $currency ) {
        return isset( self::CURRENCY_FORMAT[ $currency ] ) || 'USD' === $currency || 'EUR' === $currency;
    }

    /* ================================================================
       COUNTRY DETECTION (private)
       ================================================================ */

    private static function detect_country() {
        // Admin geo testing
        if ( isset( $_GET['geo_test'] ) && current_user_can( 'manage_options' ) ) {
            return strtoupper( sanitize_text_field( $_GET['geo_test'] ) );
        }
        if ( ! empty( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) {
            return strtoupper( sanitize_text_field( $_SERVER['HTTP_CF_IPCOUNTRY'] ) );
        }
        if ( ! empty( $_COOKIE['replanta_geo_cc'] ) ) {
            return strtoupper( sanitize_text_field( $_COOKIE['replanta_geo_cc'] ) );
        }
        return '';
    }

    /**
     * Map country code → region.
     */
    private static function country_to_region( $cc ) {
        if ( empty( $cc ) ) {
            return 'eur';
        }
        $cc = strtoupper( $cc );
        if ( in_array( $cc, self::EUROPE_CODES, true ) ) {
            return 'eur';
        }
        if ( in_array( $cc, self::NA_CODES, true ) ) {
            return 'usd';
        }
        if ( array_key_exists( $cc, self::LATAM_CURRENCIES ) ) {
            return 'latam';
        }
        return 'usd'; // rest of world
    }

    /* ================================================================
       PRICE FORMATTING
       ================================================================ */

    /**
     * Format a numeric price with the correct currency symbol & separators.
     * For LATAM currencies where Upmind doesn't yet have prices → shows USD.
     *
     * @param float       $amount
     * @param string|null $currency  e.g. 'EUR', 'USD', 'MXN'. Null = auto.
     * @return string  Plain text "12,99€" or "$12.99"
     */
    public static function format_price( $amount, $currency = null ) {
        if ( null === $currency ) {
            $currency = self::get_currency_code();
        }
        $fmt = isset( self::CURRENCY_FORMAT[ $currency ] )
            ? self::CURRENCY_FORMAT[ $currency ]
            : self::CURRENCY_FORMAT['USD'];

        $is_round = ( floor( $amount ) == $amount );
        $decimals = $is_round ? 0 : 2;
        $number   = number_format( $amount, $decimals, $fmt['dec'], $fmt['thou'] );

        return 'before' === $fmt['pos']
            ? $fmt['symbol'] . $number
            : $number . $fmt['symbol'];
    }

    /**
     * Format price with decimals wrapped in <span class="rep-text-small">.
     *
     * @param float       $amount
     * @param string|null $currency  e.g. 'EUR', 'USD', 'MXN'. Null = auto.
     * @return string HTML
     */
    public static function format_price_html( $amount, $currency = null ) {
        if ( null === $currency ) {
            $currency = self::get_currency_code();
        }
        $fmt = isset( self::CURRENCY_FORMAT[ $currency ] )
            ? self::CURRENCY_FORMAT[ $currency ]
            : self::CURRENCY_FORMAT['USD'];

        $is_round = ( floor( $amount ) == $amount );

        if ( $is_round ) {
            $number = number_format( $amount, 0, $fmt['dec'], $fmt['thou'] );
            return 'before' === $fmt['pos']
                ? $fmt['symbol'] . $number
                : $number . $fmt['symbol'];
        }

        // Split integer part and decimal part
        $int = (int) floor( $amount );
        $int_str = number_format( $int, 0, '', $fmt['thou'] );
        $dec_raw = $amount - $int;
        $dec_str = number_format( $dec_raw, 2, $fmt['dec'], '' );
        $dec_str = ltrim( $dec_str, '0' ); // e.g. ",99" or ".99"

        $small = '<span class="rep-text-small">' . $dec_str . '</span>';

        return 'before' === $fmt['pos']
            ? $fmt['symbol'] . $int_str . $small
            : $int_str . $small . $fmt['symbol'];
    }

    /* ================================================================
       ORDER URL
       ================================================================ */

    /**
     * Get the order URL with currency parameter and AWC tracking.
     *
     * @param string $pid Product ID.
     * @return string Order URL with AWC appended if available.
     */
    public static function get_order_url( $pid, $plan = null ) {
        $settings = get_option( 'replanta_prices_settings', array() );
        $base     = ! empty( $settings['upmind_order_url'] )
            ? esc_url_raw( $settings['upmind_order_url'] )
            : 'https://clientes.replanta.net/order/';

        $base = rtrim( $base, '/' );
        if ( ! preg_match( '#/order/product$#', $base ) ) {
            if ( preg_match( '#/order$#', $base ) ) {
                $base .= '/product';
            } else {
                $base .= '/order/product';
            }
        }

        $currency = is_array( $plan )
            ? Replanta_Prices_Cache::get_effective_currency( $plan )
            : self::get_currency_code();

        $base = add_query_arg(
            array(
                'pid'      => $pid,
                'currency' => $currency,
                'bcm'      => 1,
            ),
            $base
        );
        
        // Integrate Awin AWC tracking if available
        if ( is_callable( array( 'Replanta_Awin_URL_Helper', 'append_awc' ) ) ) {
            $base = call_user_func( array( 'Replanta_Awin_URL_Helper', 'append_awc' ), $base );
        }
        
        return $base;
    }
}
