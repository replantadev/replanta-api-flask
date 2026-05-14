<?php
/**
 * Public registration form + shortcode.
 *
 * @package Replanta_Affiliates
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raff_Registration {

    public static function init() {
        add_shortcode( 'replanta_affiliate_register', array( __CLASS__, 'shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
    }

    public static function enqueue() {
        if ( ! is_singular() ) {
            return;
        }
        global $post;
        if ( $post && has_shortcode( $post->post_content, 'replanta_affiliate_register' ) ) {
            wp_enqueue_style( 'raff-registration', RAFF_URL . 'assets/css/registration.css', array(), RAFF_VERSION );
            wp_enqueue_script( 'raff-registration', RAFF_URL . 'assets/js/registration.js', array(), RAFF_VERSION, true );

            $turnstile_sitekey = self::get_turnstile_sitekey();
            if ( '' !== $turnstile_sitekey ) {
                wp_enqueue_script( 'cf-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true );
            }
        }
    }

    /* ── Shortcode ──────────────────────────────────────── */
    public static function shortcode( $atts ) {
        /* Already registered? Show message */
        if ( isset( $_GET['raff_registered'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            return '<div class="raff-notice raff-notice--success">'
                . '<h3>' . esc_html__( '¡Solicitud enviada!', 'replanta-affiliates' ) . '</h3>'
                . '<p>' . esc_html__( 'Hemos recibido tu solicitud. Revisaremos tus datos y te contactaremos pronto por email.', 'replanta-affiliates' ) . '</p>'
                . '</div>';
        }

        /* Handle POST */
        $errors = array();
        if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['raff_register_nonce'] ) ) {
            $errors = self::handle_submit();
            if ( empty( $errors ) ) {
                $redirect = add_query_arg( 'raff_registered', '1' );
                $redirect = remove_query_arg( array( 'raff_register_nonce' ), $redirect ) . '#raff-register';
                wp_safe_redirect( $redirect );
                exit;
            }
        }

        ob_start();
        $countries = self::get_countries();
        include RAFF_DIR . 'templates/registration-form.php';
        return ob_get_clean();
    }

    /* ── Form processing ────────────────────────────────── */
    private static function handle_submit() {
        $errors = array();

        /* Nonce */
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['raff_register_nonce'] ?? '' ) ), 'raff_register' ) ) {
            $errors[] = __( 'Error de seguridad. Recarga la página e inténtalo de nuevo.', 'replanta-affiliates' );
            return $errors;
        }

        /* Honeypot */
        if ( ! empty( $_POST['raff_website_url'] ) ) {
            $errors[] = __( 'Spam detectado.', 'replanta-affiliates' );
            return $errors;
        }

        if ( ! self::verify_turnstile_submission( $errors ) ) {
            return $errors;
        }

        /* Sanitize fields */
        $name         = sanitize_text_field( wp_unslash( $_POST['raff_name'] ?? '' ) );
        $email        = sanitize_email( wp_unslash( $_POST['raff_email'] ?? '' ) );
        $phone        = sanitize_text_field( wp_unslash( $_POST['raff_phone'] ?? '' ) );
        $country      = sanitize_text_field( wp_unslash( $_POST['raff_country'] ?? '' ) );
        $website_raw  = sanitize_text_field( wp_unslash( $_POST['raff_site'] ?? '' ) );
        $website      = self::normalize_website_url( $website_raw, $errors );
        $promo_method = sanitize_textarea_field( wp_unslash( $_POST['raff_promo'] ?? '' ) );
        $doc_type     = sanitize_text_field( wp_unslash( $_POST['raff_doc_type'] ?? 'dni' ) );
        $doc_number   = sanitize_text_field( wp_unslash( $_POST['raff_doc_number'] ?? '' ) );

        /* Validate required */
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

        /* Check duplicate email */
        if ( empty( $errors ) && Raff_DB::get_affiliate_by_email( $email ) ) {
            $errors[] = __( 'Ya existe una solicitud con este email.', 'replanta-affiliates' );
        }

        /* File upload */
        $doc_path = '';
        if ( ! empty( $_FILES['raff_doc_file']['name'] ) ) {
            $doc_path = self::handle_upload( $_FILES['raff_doc_file'], $errors );
        } else {
            $errors[] = __( 'Sube una copia de tu documento de identidad (PDF, JPG o PNG).', 'replanta-affiliates' );
        }

        if ( ! empty( $errors ) ) {
            return $errors;
        }

        /* Generate ref code */
        $ref_code = self::generate_ref_code( $name );

        /* Insert */
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
        }

        return $errors;
    }

    /**
     * Accepts URLs with or without protocol and normalizes to a full URL.
     */
    public static function normalize_website_url( $website_raw, &$errors ) {
        $website_raw = trim( (string) $website_raw );

        if ( '' === $website_raw ) {
            return '';
        }

        // User-friendly: if protocol is missing, assume HTTPS.
        if ( ! preg_match( '#^[a-z][a-z0-9+.-]*://#i', $website_raw ) ) {
            $website_raw = 'https://' . ltrim( $website_raw, '/' );
        }

        $website = esc_url_raw( $website_raw, array( 'http', 'https' ) );
        if ( '' === $website || ! wp_http_validate_url( $website ) ) {
            $errors[] = __( 'La web no parece válida. Puedes escribirla sin http(s), por ejemplo: tudominio.com.', 'replanta-affiliates' );
            return '';
        }

        return $website;
    }

    /**
     * Returns Turnstile sitekey (constant first, then known site fallback).
     */
    public static function get_turnstile_sitekey() {
        if ( defined( 'CF_TURNSTILE_SITEKEY' ) && CF_TURNSTILE_SITEKEY ) {
            return (string) CF_TURNSTILE_SITEKEY;
        }

        // Fallback key already used in other Replanta forms.
        return '0x4AAAAAAB9AM8TVibxJ797V';
    }

    /**
     * Verify Cloudflare Turnstile token from the form submission.
     */
    public static function verify_turnstile_submission( &$errors ) {
        $sitekey = self::get_turnstile_sitekey();
        if ( '' === $sitekey ) {
            return true;
        }

        $token = sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ?? '' ) );
        if ( '' === $token ) {
            $errors[] = __( 'Completa la verificación de seguridad antes de enviar.', 'replanta-affiliates' );
            return false;
        }

        $secret = defined( 'CF_TURNSTILE_SECRET' ) ? (string) CF_TURNSTILE_SECRET : '';
        if ( '' === $secret ) {
            $errors[] = __( 'Configuración de seguridad incompleta (Turnstile secret no definida).', 'replanta-affiliates' );
            return false;
        }

        $response = wp_remote_post(
            'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            array(
                'timeout' => 10,
                'body'    => array(
                    'secret'   => $secret,
                    'response' => $token,
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            $errors[] = __( 'No pudimos validar la seguridad. Inténtalo de nuevo.', 'replanta-affiliates' );
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['success'] ) ) {
            $errors[] = __( 'Verificación de seguridad fallida. Inténtalo de nuevo.', 'replanta-affiliates' );
            return false;
        }

        return true;
    }

    /* ── File upload ────────────────────────────────────── */
    public static function handle_upload( $file, &$errors ) {
        $allowed_types = array( 'application/pdf', 'image/jpeg', 'image/png' );
        $max_size      = 5 * 1024 * 1024; // 5 MB

        if ( $file['error'] !== UPLOAD_ERR_OK ) {
            $errors[] = __( 'Error al subir el archivo.', 'replanta-affiliates' );
            return '';
        }

        $finfo    = new finfo( FILEINFO_MIME_TYPE );
        $mime     = $finfo->file( $file['tmp_name'] );

        if ( ! in_array( $mime, $allowed_types, true ) ) {
            $errors[] = __( 'Formato no permitido. Solo PDF, JPG o PNG.', 'replanta-affiliates' );
            return '';
        }
        if ( $file['size'] > $max_size ) {
            $errors[] = __( 'El archivo es demasiado grande (máx. 5 MB).', 'replanta-affiliates' );
            return '';
        }

        $upload_dir = self::get_docs_dir();
        $ext        = pathinfo( $file['name'], PATHINFO_EXTENSION );
        $filename   = wp_generate_password( 24, false ) . '.' . sanitize_file_name( $ext );
        $dest       = $upload_dir . '/' . $filename;

        if ( ! move_uploaded_file( $file['tmp_name'], $dest ) ) {
            $errors[] = __( 'No se pudo guardar el archivo.', 'replanta-affiliates' );
            return '';
        }

        return $dest;
    }

    private static function get_docs_dir() {
        $upload = wp_upload_dir();
        $dir    = $upload['basedir'] . '/replanta-affiliates/docs';
        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
            /* Protect directory */
            file_put_contents( $dir . '/.htaccess', "Order deny,allow\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
        }
        return $dir;
    }

    /* ── Ref-code generator ─────────────────────────────── */
    /**
     * Generates a coupon code like LUISJA10 (name prefix + 10 for the 10% discount).
     * If collision, appends incrementing digit: LUISJA10, LUISJA101, LUISJA102...
     */
    public static function generate_ref_code( $name ) {
        /* Normalize: remove accents, uppercase, strip non-alpha */
        $clean = strtoupper( remove_accents( $name ) );
        $clean = preg_replace( '/[^A-Z]/', '', $clean );
        $base  = substr( $clean, 0, 6 );
        if ( strlen( $base ) < 3 ) {
            $base = 'AFF';
        }

        /* Base code: NAME + 10 */
        $code = $base . '10';

        if ( ! Raff_DB::get_affiliate_by_ref( $code ) ) {
            return $code;
        }

        /* Collision: append incrementing digit */
        for ( $i = 1; $i <= 99; $i++ ) {
            $candidate = $base . '10' . $i;
            if ( ! Raff_DB::get_affiliate_by_ref( $candidate ) ) {
                return $candidate;
            }
        }

        /* Fallback (extremely unlikely) */
        return $base . '10' . wp_rand( 100, 999 );
    }

    /* ── Countries ──────────────────────────────────────── */
    public static function get_countries() {
        return array(
            'ES' => 'España', 'MX' => 'México', 'AR' => 'Argentina', 'CO' => 'Colombia',
            'CL' => 'Chile', 'PE' => 'Perú', 'EC' => 'Ecuador', 'VE' => 'Venezuela',
            'UY' => 'Uruguay', 'PY' => 'Paraguay', 'BO' => 'Bolivia', 'CR' => 'Costa Rica',
            'PA' => 'Panamá', 'DO' => 'Rep. Dominicana', 'GT' => 'Guatemala', 'HN' => 'Honduras',
            'SV' => 'El Salvador', 'NI' => 'Nicaragua', 'CU' => 'Cuba', 'PR' => 'Puerto Rico',
            'US' => 'Estados Unidos', 'GB' => 'Reino Unido', 'DE' => 'Alemania', 'FR' => 'Francia',
            'IT' => 'Italia', 'PT' => 'Portugal', 'BR' => 'Brasil', 'CA' => 'Canadá',
            'AU' => 'Australia', 'JP' => 'Japón', 'CN' => 'China', 'IN' => 'India',
            'OTHER' => 'Otro',
        );
    }
}
