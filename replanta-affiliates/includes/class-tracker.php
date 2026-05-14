<?php
/**
 * Cookie tracker + visit/event recording + purchase attribution.
 *
 * @package Replanta_Affiliates
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raff_Tracker {

    const COOKIE_NAME = 'replanta_aff_ref';

    public static function init() {
        /* Capture ?ref= on every page load (early, before output) */
        add_action( 'template_redirect', array( __CLASS__, 'capture_ref' ), 1 );

        /* Inject ref into checkout links on frontend */
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_ref_rewriter' ) );

        /* Listen for purchases from replanta-prices */
        add_action( 'replanta_prices_purchase_received', array( __CLASS__, 'handle_purchase' ) );

        /* Cron: confirm old sales */
        add_action( 'raff_daily_confirm_sales', array( __CLASS__, 'cron_confirm_sales' ) );

        /* Cron: cleanup expired magic tokens */
        add_action( 'raff_cleanup_expired_tokens', array( __CLASS__, 'cron_cleanup_tokens' ) );
    }

    /**
     * Enqueue the ref-rewriter JS on ALL frontend pages.
     *
     * We always enqueue because full-page caches (LiteSpeed, Cloudflare) may serve
     * pages without PHP executing. The JS reads the cookie client-side as fallback.
     * When PHP can read the cookie (uncached hit), we pass the validated code to
     * skip an extra validation round.
     */
    public static function enqueue_ref_rewriter() {
        if ( is_admin() ) {
            return;
        }

        $ref = '';

        /* From URL param (current page load) */
        if ( ! empty( $_GET['ref'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            $ref = sanitize_text_field( wp_unslash( $_GET['ref'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
        }
        /* From cookie (previous page load) */
        if ( '' === $ref && ! empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
            $ref = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
        }

        /* Validate if we have a code from server-side */
        $validated_code = '';
        if ( '' !== $ref ) {
            $affiliate = Raff_DB::get_affiliate_by_ref( $ref );
            if ( $affiliate && in_array( $affiliate->status, array( 'approved', 'active' ), true ) ) {
                $validated_code = $ref;
            }
        }

        /* Always enqueue — JS handles cookie fallback for cached pages */
        wp_enqueue_script( 'raff-ref-rewriter', RAFF_URL . 'assets/js/ref-rewriter.js', array(), RAFF_VERSION, true );
        $checkout_host = self::normalize_checkout_host( Raff_DB::get_setting( 'checkout_host', 'clientes.replanta.net' ) );
        wp_localize_script( 'raff-ref-rewriter', 'raffRef', array(
            'code'          => $validated_code,
            'checkout_host' => $checkout_host,
        ) );
    }

    /* ══════════════════════════════════════════════════════
     *  COOKIE CAPTURE
     * ══════════════════════════════════════════════════════ */
    public static function capture_ref() {
        if ( empty( $_GET['ref'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            return;
        }

        $ref = sanitize_text_field( wp_unslash( $_GET['ref'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
        $affiliate = Raff_DB::get_affiliate_by_ref( $ref );

        if ( ! $affiliate ) {
            return;
        }

        /* Set/refresh cookie */
        $days   = (int) Raff_DB::get_setting( 'cookie_days', 90 );
        $secure = is_ssl();
        setcookie(
            self::COOKIE_NAME,
            $ref,
            array(
                'expires'  => time() + ( $days * DAY_IN_SECONDS ),
                'path'     => '/',
                'domain'   => '.' . self::get_root_domain(),
                'secure'   => $secure,
                'httponly'  => false,
                'samesite'  => 'Lax',
            )
        );
        $_COOKIE[ self::COOKIE_NAME ] = $ref;

        /* Log visit (deduplicado by IP hash + day) */
        $ip_hash = hash( 'sha256', self::get_client_ip() . wp_salt( 'auth' ) );

        if ( ! Raff_DB::visit_exists_today( $affiliate->id, $ip_hash ) ) {
            Raff_DB::insert_event( array(
                'affiliate_id' => $affiliate->id,
                'event_type'   => 'visit',
                'ref_code'     => $ref,
                'ip_hash'      => $ip_hash,
                'url'          => esc_url_raw( home_url( $_SERVER['REQUEST_URI'] ?? '' ) ),
            ) );
        }
    }

    /* ══════════════════════════════════════════════════════
     *  PURCHASE ATTRIBUTION
     * ══════════════════════════════════════════════════════ */
    public static function handle_purchase( $data ) {
        $order_id = $data['order_id'] ?? '';
        $voucher  = $data['voucher']  ?? '';
        $value    = (float) ( $data['value'] ?? 0 );
        $currency = $data['currency'] ?? 'EUR';
        $pid      = $data['pid']      ?? '';
        $plan     = sanitize_text_field( $data['plan_name'] ?? '' );
        $url      = $data['url']      ?? '';

        if ( '' === $order_id || $value <= 0 ) {
            return;
        }

        /* Already attributed? */
        if ( Raff_DB::get_sale_by_order( $order_id ) ) {
            return;
        }

        $affiliate        = null;
        $attribution_type = '';

        /* 1. Try voucher match first (higher priority — explicit coupon use) */
        if ( '' !== $voucher ) {
            $by_voucher = Raff_DB::get_affiliate_by_coupon( $voucher );
            if ( $by_voucher ) {
                $affiliate        = $by_voucher;
                $attribution_type = 'voucher';
            }
        }

        /* 2. Try cookie match — from PHP cookie (direct hits) or from bridge payload (cross-origin REST) */
        $ref_cookie = $_COOKIE[ self::COOKIE_NAME ] ?? '';
        /* Bridge sends ref_code because sendBeacon is cross-origin (credentials:omit → no PHP cookies) */
        if ( '' === $ref_cookie && ! empty( $data['ref_code'] ) ) {
            $ref_cookie = sanitize_text_field( $data['ref_code'] );
        }
        if ( '' !== $ref_cookie ) {
            $by_cookie = Raff_DB::get_affiliate_by_ref( $ref_cookie );
            if ( $by_cookie ) {
                if ( $affiliate && $affiliate->id === $by_cookie->id ) {
                    $attribution_type = 'both';
                } elseif ( ! $affiliate ) {
                    $affiliate        = $by_cookie;
                    $attribution_type = 'cookie';
                }
            }
        }

        if ( ! $affiliate ) {
            return;
        }

        /* Calculate commission */
        $pct        = (float) $affiliate->commission_pct;
        $commission  = round( $value * ( $pct / 100 ), 2 );

        $sale_id = Raff_DB::insert_sale( array(
            'affiliate_id'     => $affiliate->id,
            'order_id'         => $order_id,
            'product_pid'      => $pid,
            'plan_name'        => $plan,
            'amount'           => $value,
            'currency'         => $currency,
            'commission_pct'   => $pct,
            'commission_amount'=> $commission,
            'voucher_used'     => $voucher,
            'attribution_type' => $attribution_type,
            'source_url'       => $url,
            'status'           => 'pending',
        ) );

        if ( $sale_id ) {
            $sale = Raff_DB::get_sale_by_order( $order_id );
            Raff_Email::send_sale_notification( $affiliate, $sale );
        }
    }

    /* ══════════════════════════════════════════════════════
     *  CRON JOBS
     * ══════════════════════════════════════════════════════ */
    public static function cron_confirm_sales() {
        $days = (int) Raff_DB::get_setting( 'confirmation_days', 30 );
        Raff_DB::confirm_old_sales( $days );
    }

    public static function cron_cleanup_tokens() {
        global $wpdb;
        $wpdb->query(
            "UPDATE " . Raff_DB::table( Raff_DB::T_AFFILIATES ) .
            " SET magic_token = '', magic_token_exp = NULL WHERE magic_token_exp < NOW() AND magic_token != ''"
        );
    }

    /* ── Helpers ────────────────────────────────────────── */
    private static function get_root_domain() {
        $host = wp_parse_url( home_url(), PHP_URL_HOST );
        $parts = explode( '.', $host );
        if ( count( $parts ) > 2 ) {
            return implode( '.', array_slice( $parts, -2 ) );
        }
        return $host;
    }

    private static function get_client_ip() {
        /* Only trust X-Forwarded-For behind known proxies (Cloudflare etc) */
        if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
            return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
        }
        return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' ) );
    }

    /**
     * Accept host, full URL, or host/path and normalize to hostname only.
     */
    private static function normalize_checkout_host( $raw ) {
        $raw = strtolower( trim( sanitize_text_field( (string) $raw ) ) );
        if ( '' === $raw ) {
            return 'clientes.replanta.net';
        }

        $host = '';
        if ( false !== strpos( $raw, '://' ) ) {
            $host = wp_parse_url( $raw, PHP_URL_HOST );
        } else {
            // Supports values like "clientes.replanta.net/order/".
            $parts = explode( '/', ltrim( $raw, '/' ) );
            $host  = $parts[0] ?? '';
        }

        $host = preg_replace( '/:\\d+$/', '', (string) $host );
        return '' !== $host ? $host : 'clientes.replanta.net';
    }
}
