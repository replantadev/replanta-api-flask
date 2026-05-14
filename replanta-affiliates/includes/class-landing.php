<?php
/**
 * Unified affiliate landing page: hero + login + registration.
 *
 * @package Replanta_Affiliates
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raff_Landing {

    public static function init() {
        add_shortcode( 'replanta_affiliate_landing', array( __CLASS__, 'shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
    }

    public static function enqueue() {
        if ( ! is_singular() ) {
            return;
        }
        global $post;
        if ( $post && has_shortcode( $post->post_content, 'replanta_affiliate_landing' ) ) {
            wp_enqueue_style( 'raff-landing-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Sora:wght@600;700;800&display=swap', array(), null );
            wp_enqueue_style( 'raff-landing', RAFF_URL . 'assets/css/landing.css', array( 'raff-landing-fonts' ), RAFF_VERSION );
            wp_enqueue_script( 'raff-landing', RAFF_URL . 'assets/js/landing.js', array(), RAFF_VERSION, true );
            if ( '' !== Raff_Registration::get_turnstile_sitekey() ) {
                wp_enqueue_script( 'cf-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true );
            }
            wp_localize_script( 'raff-landing', 'raffLanding', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'raff_dashboard' ),
            ) );
        }
    }

    public static function shortcode() {
        /* If authenticated affiliate → redirect to dashboard */
        if ( ! empty( $_GET['raff_token'] ) || ! empty( $_COOKIE['raff_session'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            $dashboard_url = home_url( Raff_DB::get_setting( 'dashboard_path', '/afiliados/dashboard/' ) );
            if ( ! empty( $_GET['raff_token'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
                $dashboard_url = add_query_arg( 'raff_token', sanitize_text_field( wp_unslash( $_GET['raff_token'] ) ), $dashboard_url ); // phpcs:ignore WordPress.Security.NonceVerification
            }
            wp_safe_redirect( $dashboard_url );
            exit;
        }

        /* Handle registration POST */
        $errors = array();
        $registered = isset( $_GET['raff_registered'] ); // phpcs:ignore WordPress.Security.NonceVerification
        if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['raff_register_nonce'] ) ) {
            if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['raff_register_nonce'] ) ), 'raff_register' ) ) {
                $errors[] = __( 'Error de seguridad. Recarga la página.', 'replanta-affiliates' );
            } else {
                $errors = self::handle_registration();
                if ( empty( $errors ) ) {
                    $redirect = add_query_arg( 'raff_registered', '1' );
                    $redirect = remove_query_arg( array( 'raff_register_nonce' ), $redirect ) . '#raff-register';
                    wp_safe_redirect( $redirect );
                    exit;
                }
            }
        }

        $countries = Raff_Registration::get_countries();

        ob_start();
        include RAFF_DIR . 'templates/landing.php';
        return ob_get_clean();
    }

    /**
     * Process registration (delegates to Raff_Registration logic).
     */
    private static function handle_registration() {
        /* Use reflection to call private method OR just replicate the insert logic */
        $errors = array();

        /* Honeypot */
        if ( ! empty( $_POST['raff_website_url'] ) ) {
            $errors[] = __( 'Spam detectado.', 'replanta-affiliates' );
            return $errors;
        }

        if ( ! Raff_Registration::verify_turnstile_submission( $errors ) ) {
            return $errors;
        }

        $name         = sanitize_text_field( wp_unslash( $_POST['raff_name'] ?? '' ) );
        $email        = sanitize_email( wp_unslash( $_POST['raff_email'] ?? '' ) );
        $phone        = sanitize_text_field( wp_unslash( $_POST['raff_phone'] ?? '' ) );
        $country      = sanitize_text_field( wp_unslash( $_POST['raff_country'] ?? '' ) );
        $website_raw  = sanitize_text_field( wp_unslash( $_POST['raff_site'] ?? '' ) );
        $website      = Raff_Registration::normalize_website_url( $website_raw, $errors );
        $promo_method = sanitize_textarea_field( wp_unslash( $_POST['raff_promo'] ?? '' ) );
        $doc_type     = sanitize_text_field( wp_unslash( $_POST['raff_doc_type'] ?? 'dni' ) );
        $doc_number   = sanitize_text_field( wp_unslash( $_POST['raff_doc_number'] ?? '' ) );

        if ( '' === $name ) {
            $errors[] = __( 'El nombre es obligatorio.', 'replanta-affiliates' );
        }
        if ( ! is_email( $email ) ) {
            $errors[] = __( 'Introduce un email válido.', 'replanta-affiliates' );
        }
        if ( '' === $doc_number ) {
            $errors[] = __( 'El número de documento es obligatorio.', 'replanta-affiliates' );
        }
        if ( ! in_array( $doc_type, array( 'dni', 'nif', 'passport' ), true ) ) {
            $errors[] = __( 'Tipo de documento no válido.', 'replanta-affiliates' );
        }

        if ( empty( $errors ) && Raff_DB::get_affiliate_by_email( $email ) ) {
            $errors[] = __( 'Ya existe una solicitud con este email.', 'replanta-affiliates' );
        }

        /* File upload */
        $doc_path = '';
        if ( ! empty( $_FILES['raff_doc_file']['name'] ) ) {
            $doc_path = Raff_Registration::handle_upload( $_FILES['raff_doc_file'], $errors );
        } else {
            $errors[] = __( 'Sube una copia de tu documento de identidad (PDF, JPG o PNG).', 'replanta-affiliates' );
        }

        if ( ! empty( $errors ) ) {
            return $errors;
        }

        $ref_code = Raff_Registration::generate_ref_code( $name );

        $id = Raff_DB::insert_affiliate( array(
            'name'          => $name,
            'email'         => $email,
            'phone'         => $phone,
            'country'       => $country,
            'website'       => $website,
            'promo_method'  => $promo_method,
            'doc_type'      => $doc_type,
            'doc_number'    => $doc_number,
            'doc_file_path' => $doc_path,
            'ref_code'      => $ref_code,
            'commission_pct'=> (float) Raff_DB::get_setting( 'default_commission_pct', 20 ),
            'status'        => 'pending',
        ) );

        if ( $id ) {
            $affiliate = Raff_DB::get_affiliate( $id );
            Raff_Email::send_welcome( $affiliate );
            Raff_Email::notify_admin_new_affiliate( $affiliate );

            /* Sync to Replanta Contact Manager */
            self::sync_to_contact_manager( $name, $email, $phone, $country, $website, $promo_method );
        }

        return $errors;
    }

    /**
     * Create an entry in Replanta Contact Manager (rcm_contact CPT).
     */
    private static function sync_to_contact_manager( $name, $email, $phone, $country, $website, $promo_method ) {
        if ( ! class_exists( 'RCM_CPT' ) ) {
            return;
        }

        RCM_CPT::create_contact( array(
            'type'    => 'afiliado',
            'name'    => $name,
            'email'   => $email,
            'phone'   => $phone,
            'country' => $country,
            'source'  => 'Formulario Afiliados',
            'data'    => array(
                'website'      => $website,
                'promo_method' => $promo_method,
            ),
        ) );
    }
}
