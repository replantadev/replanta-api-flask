<?php
/**
 * Frontend affiliate dashboard (magic-link access).
 *
 * @package Replanta_Affiliates
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raff_Dashboard {

    public static function init() {
        add_shortcode( 'replanta_affiliate_dashboard', array( __CLASS__, 'shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
        add_action( 'wp_ajax_raff_request_magic_link', array( __CLASS__, 'ajax_request_magic_link' ) );
        add_action( 'wp_ajax_nopriv_raff_request_magic_link', array( __CLASS__, 'ajax_request_magic_link' ) );
        add_action( 'wp_ajax_nopriv_raff_request_payout', array( __CLASS__, 'ajax_request_payout' ) );
        add_action( 'wp_ajax_raff_request_payout', array( __CLASS__, 'ajax_request_payout' ) );
    }

    public static function enqueue() {
        if ( ! is_singular() ) {
            return;
        }
        global $post;
        if ( $post && has_shortcode( $post->post_content, 'replanta_affiliate_dashboard' ) ) {
            wp_enqueue_style( 'raff-dashboard', RAFF_URL . 'assets/css/dashboard.css', array(), RAFF_VERSION );
            wp_enqueue_script( 'raff-dashboard', RAFF_URL . 'assets/js/dashboard.js', array(), RAFF_VERSION, true );
            wp_localize_script( 'raff-dashboard', 'raffDash', array(
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'raff_dashboard' ),
            ) );
        }
    }

    /* ── Shortcode ──────────────────────────────────────── */
    public static function shortcode() {
        /* Check magic-link token */
        $affiliate = self::get_authenticated_affiliate();

        if ( ! $affiliate ) {
            return self::render_login_form();
        }

        $tab = sanitize_text_field( $_GET['tab'] ?? 'resumen' ); // phpcs:ignore WordPress.Security.NonceVerification

        ob_start();
        include RAFF_DIR . 'templates/dashboard.php';
        return ob_get_clean();
    }

    /* ── Authentication ─────────────────────────────────── */
    private static function get_authenticated_affiliate() {
        /* Check token in URL */
        if ( ! empty( $_GET['raff_token'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            $token     = sanitize_text_field( wp_unslash( $_GET['raff_token'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
            $affiliate = Raff_DB::get_affiliate_by_token( $token );
            if ( $affiliate ) {
                /* Set session cookie for subsequent page loads */
                $session_token = wp_generate_password( 32, false );
                Raff_DB::update_affiliate( $affiliate->id, array(
                    'magic_token'     => $session_token,
                    'magic_token_exp' => gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS * 24 ),
                ) );
                setcookie( 'raff_session', $session_token, array(
                    'expires'  => time() + DAY_IN_SECONDS,
                    'path'     => '/',
                    'secure'   => is_ssl(),
                    'httponly'  => true,
                    'samesite'  => 'Lax',
                ) );
                $_COOKIE['raff_session'] = $session_token;
                return Raff_DB::get_affiliate( $affiliate->id );
            }
        }

        /* Check session cookie */
        if ( ! empty( $_COOKIE['raff_session'] ) ) {
            $token = sanitize_text_field( $_COOKIE['raff_session'] );
            return Raff_DB::get_affiliate_by_token( $token );
        }

        return null;
    }

    /* ── Login form ─────────────────────────────────────── */
    private static function render_login_form() {
        $sent = isset( $_GET['raff_link_sent'] ); // phpcs:ignore WordPress.Security.NonceVerification
        ob_start();
        ?>
        <div class="raff-login-wrap">
            <?php if ( $sent ) : ?>
                <div class="raff-notice raff-notice--success">
                    <p><?php esc_html_e( 'Te hemos enviado un enlace de acceso por email. Revisa tu bandeja de entrada.', 'replanta-affiliates' ); ?></p>
                </div>
            <?php endif; ?>
            <h2><?php esc_html_e( 'Acceso al Dashboard de Afiliado', 'replanta-affiliates' ); ?></h2>
            <p><?php esc_html_e( 'Introduce tu email de afiliado y te enviaremos un enlace de acceso directo.', 'replanta-affiliates' ); ?></p>
            <form method="post" id="raff-login-form" class="raff-form">
                <div class="raff-field">
                    <input type="email" name="raff_login_email" required placeholder="tu@email.com" />
                </div>
                <button type="submit" class="raff-btn raff-btn--primary"><?php esc_html_e( 'Enviar enlace de acceso', 'replanta-affiliates' ); ?></button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ── AJAX: Send magic link ──────────────────────────── */
    public static function ajax_request_magic_link() {
        check_ajax_referer( 'raff_dashboard', 'nonce' );

        $email     = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        if ( '' === $email ) {
            wp_send_json_success( array( 'message' => __( 'Si tu email está registrado, recibirás el enlace de acceso.', 'replanta-affiliates' ) ) );
        }

        // Prevent repeated sends/spam for the same address while keeping generic response.
        $rate_limit_key = 'raff_magic_rl_' . md5( strtolower( $email ) );
        if ( get_transient( $rate_limit_key ) ) {
            wp_send_json_success( array( 'message' => __( 'Si tu email está registrado, recibirás el enlace de acceso.', 'replanta-affiliates' ) ) );
        }
        set_transient( $rate_limit_key, 1, 10 * MINUTE_IN_SECONDS );

        $affiliate = Raff_DB::get_affiliate_by_email( $email );

        /* Always respond success to avoid email enumeration */
        if ( $affiliate && in_array( $affiliate->status, array( 'approved', 'active' ), true ) ) {
            $token = wp_generate_password( 48, false );
            Raff_DB::update_affiliate( $affiliate->id, array(
                'magic_token'     => $token,
                'magic_token_exp' => gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ),
            ) );

            $dashboard_path = Raff_DB::get_setting( 'dashboard_path', '/afiliados/dashboard/' );
            $dashboard_url = add_query_arg( 'raff_token', $token, home_url( $dashboard_path ) );
            Raff_Email::send_magic_link( $affiliate, $dashboard_url );
        }

        wp_send_json_success( array( 'message' => __( 'Si tu email está registrado, recibirás el enlace de acceso.', 'replanta-affiliates' ) ) );
    }

    /* ── AJAX: Request payout ───────────────────────────── */
    public static function ajax_request_payout() {
        check_ajax_referer( 'raff_dashboard', 'nonce' );

        $affiliate = self::get_authenticated_affiliate();
        if ( ! $affiliate ) {
            wp_send_json_error( array( 'message' => __( 'Sesión expirada.', 'replanta-affiliates' ) ) );
        }

        $threshold = (float) Raff_DB::get_setting( 'payout_threshold', 50 );
        $balance   = Raff_DB::get_available_balance( $affiliate->id );

        if ( $balance < $threshold ) {
            wp_send_json_error( array(
                'message' => sprintf(
                    __( 'Saldo insuficiente. Necesitas al menos %s€.', 'replanta-affiliates' ),
                    number_format( $threshold, 2, ',', '.' )
                ),
            ) );
        }

        $method = sanitize_text_field( $_POST['method'] ?? $affiliate->payment_method );
        if ( ! in_array( $method, array( 'paypal', 'bank' ), true ) ) {
            $method = 'paypal';
        }

        /* Block if a payout is already pending/processing to avoid duplicates */
        $existing = Raff_DB::list_payouts( array(
            'affiliate_id' => $affiliate->id,
            'status'       => 'requested',
            'per_page'     => 1,
        ) );
        if ( empty( $existing ) ) {
            $existing = Raff_DB::list_payouts( array(
                'affiliate_id' => $affiliate->id,
                'status'       => 'processing',
                'per_page'     => 1,
            ) );
        }
        if ( ! empty( $existing ) ) {
            wp_send_json_error( array( 'message' => __( 'Ya tienes una solicitud de pago en curso. Espera a que se procese antes de solicitar otro.', 'replanta-affiliates' ) ) );
        }

        /* Validate payment details are filled */
        if ( 'paypal' === $method && empty( trim( $affiliate->paypal_email ?? '' ) ) ) {
            wp_send_json_error( array( 'message' => __( 'Añade tu email de PayPal en el perfil antes de solicitar un pago.', 'replanta-affiliates' ) ) );
        }
        if ( 'bank' === $method && empty( trim( $affiliate->bank_iban ?? '' ) ) ) {
            wp_send_json_error( array( 'message' => __( 'Añade tu IBAN bancario en el perfil antes de solicitar un pago.', 'replanta-affiliates' ) ) );
        }

        /* Calculate fee */
        $fee = 0;
        if ( 'paypal' === $method ) {
            $fee_pct   = (float) Raff_DB::get_setting( 'paypal_fee_pct', 3.49 );
            $fee_fixed = (float) Raff_DB::get_setting( 'paypal_fee_fixed', 0.49 );
            $fee       = round( ( $balance * $fee_pct / 100 ) + $fee_fixed, 2 );
        } else {
            $country = $affiliate->country ?? '';
            $sepa_countries = array( 'ES', 'DE', 'FR', 'IT', 'PT', 'NL', 'BE', 'AT', 'IE', 'FI', 'LU', 'MT', 'SK', 'SI', 'EE', 'LV', 'LT', 'CY', 'GR' );
            if ( in_array( $country, $sepa_countries, true ) ) {
                $fee = (float) Raff_DB::get_setting( 'bank_fee_sepa', 0 );
            } else {
                $fee = (float) Raff_DB::get_setting( 'bank_fee_intl', 3 );
            }
        }

        $net = round( $balance - $fee, 2 );
        if ( $net <= 0 ) {
            wp_send_json_error( array( 'message' => __( 'El neto después de comisiones sería 0 o negativo.', 'replanta-affiliates' ) ) );
        }

        $payout_id = Raff_DB::insert_payout( array(
            'affiliate_id' => $affiliate->id,
            'amount'       => $balance,
            'currency'     => 'EUR',
            'fee'          => $fee,
            'net_amount'   => $net,
            'method'       => $method,
            'status'       => 'requested',
        ) );

        if ( ! $payout_id ) {
            wp_send_json_error( array( 'message' => __( 'No se pudo crear la solicitud de pago. Inténtalo de nuevo.', 'replanta-affiliates' ) ) );
        }

        $reserved = Raff_DB::reserve_sales_for_payout( $payout_id, $affiliate->id, $balance );
        if ( $reserved <= 0 ) {
            Raff_DB::update_payout( $payout_id, array( 'status' => 'rejected', 'notes' => 'No se encontraron ventas confirmadas reservables.' ) );
            wp_send_json_error( array( 'message' => __( 'No hay ventas confirmadas disponibles para esta solicitud.', 'replanta-affiliates' ) ) );
        }

        /* Adjust payout amounts to the exact reserved sum (rounding-safe). */
        if ( abs( $reserved - $balance ) > 0.01 ) {
            if ( 'paypal' === $method ) {
                $fee_pct   = (float) Raff_DB::get_setting( 'paypal_fee_pct', 3.49 );
                $fee_fixed = (float) Raff_DB::get_setting( 'paypal_fee_fixed', 0.49 );
                $fee       = round( ( $reserved * $fee_pct / 100 ) + $fee_fixed, 2 );
            } else {
                $country = $affiliate->country ?? '';
                $sepa_countries = array( 'ES', 'DE', 'FR', 'IT', 'PT', 'NL', 'BE', 'AT', 'IE', 'FI', 'LU', 'MT', 'SK', 'SI', 'EE', 'LV', 'LT', 'CY', 'GR' );
                if ( in_array( $country, $sepa_countries, true ) ) {
                    $fee = (float) Raff_DB::get_setting( 'bank_fee_sepa', 0 );
                } else {
                    $fee = (float) Raff_DB::get_setting( 'bank_fee_intl', 3 );
                }
            }
            $net = round( $reserved - $fee, 2 );
            Raff_DB::update_payout( $payout_id, array(
                'amount'     => $reserved,
                'fee'        => $fee,
                'net_amount' => $net,
            ) );
            $balance = $reserved;
        }

        $payout = Raff_DB::get_payout( $payout_id );
        if ( $payout ) {
            Raff_Email::notify_admin_payout_requested( $affiliate, $payout );
        }

        wp_send_json_success( array(
            'message' => sprintf(
                __( 'Solicitud de pago enviada: %s€ bruto, %s€ comisión, %s€ neto. Te avisaremos cuando se procese.', 'replanta-affiliates' ),
                number_format( $balance, 2, ',', '.' ),
                number_format( $fee, 2, ',', '.' ),
                number_format( $net, 2, ',', '.' )
            ),
        ) );
    }

    /* ── Helper: get dashboard data for templates ───────── */
    public static function get_dashboard_data( $affiliate ) {
        return array(
            'balance_available' => Raff_DB::get_available_balance( $affiliate->id ),
            'balance_pending'   => Raff_DB::get_pending_balance( $affiliate->id ),
            'total_visits'      => Raff_DB::count_events( $affiliate->id, 'visit' ),
            'total_sales'       => Raff_DB::count_sales( $affiliate->id ),
            'recent_sales'      => Raff_DB::list_sales( array( 'affiliate_id' => $affiliate->id, 'per_page' => 10 ) ),
            'payouts'           => Raff_DB::list_payouts( array( 'affiliate_id' => $affiliate->id ) ),
            'threshold'         => (float) Raff_DB::get_setting( 'payout_threshold', 50 ),
            'ref_url'           => home_url( '/?ref=' . $affiliate->ref_code ),
        );
    }
}
