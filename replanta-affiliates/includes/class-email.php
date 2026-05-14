<?php
/**
 * Email notifications for affiliates and admin.
 *
 * @package Replanta_Affiliates
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raff_Email {

    /**
     * Send email to the affiliate when their registration is received.
     */
    public static function send_welcome( $affiliate ) {
        $to      = $affiliate->email;
        $subject = __( '¡Solicitud recibida! — Programa de Afiliados Replanta', 'replanta-affiliates' );
        $body    = self::render_template( 'welcome', array( 'affiliate' => $affiliate ) );
        self::send( $to, $subject, $body );
    }

    /**
     * Notify admin of a new affiliate registration.
     */
    public static function notify_admin_new_affiliate( $affiliate ) {
        $to      = Raff_DB::get_setting( 'admin_email', get_option( 'admin_email' ) );
        $subject = sprintf(
            __( '[Replanta Affiliates] Nueva solicitud: %s', 'replanta-affiliates' ),
            $affiliate->name
        );
        $body = self::render_template( 'admin-new-affiliate', array( 'affiliate' => $affiliate ) );
        self::send( $to, $subject, $body );
    }

    /**
     * Notify affiliate their application was approved.
     */
    public static function send_approved( $affiliate ) {
        $to      = $affiliate->email;
        $subject = __( '¡Tu solicitud ha sido aprobada! — Replanta', 'replanta-affiliates' );
        $body    = self::render_template( 'approved', array( 'affiliate' => $affiliate ) );
        self::send( $to, $subject, $body );
    }

    /**
     * Notify affiliate their coupon code has been assigned (status → active).
     */
    public static function send_coupon_assigned( $affiliate ) {
        $to      = $affiliate->email;
        $subject = __( '¡Tu cupón de afiliado está listo! — Replanta', 'replanta-affiliates' );
        $body    = self::render_template( 'coupon-assigned', array( 'affiliate' => $affiliate ) );
        self::send( $to, $subject, $body );
    }

    /**
     * Notify affiliate of a new sale attributed to them.
     */
    public static function send_sale_notification( $affiliate, $sale ) {
        $to      = $affiliate->email;
        $subject = sprintf(
            __( '¡Nueva venta! +%s€ comisión — Replanta', 'replanta-affiliates' ),
            number_format( $sale->commission_amount, 2, ',', '.' )
        );
        $body = self::render_template( 'sale-notification', array(
            'affiliate' => $affiliate,
            'sale'      => $sale,
        ) );
        self::send( $to, $subject, $body );
    }

    /**
     * Notify admin of a new payout request.
     */
    public static function notify_admin_payout_requested( $affiliate, $payout ) {
        $to      = Raff_DB::get_setting( 'admin_email', get_option( 'admin_email' ) );
        $subject = sprintf(
            __( '[Replanta Affiliates] Nuevo pago solicitado: %s (%s€ neto)', 'replanta-affiliates' ),
            $affiliate->name,
            number_format( $payout->net_amount, 2, ',', '.' )
        );
        $body = self::render_template( 'admin-payout-requested', array(
            'affiliate' => $affiliate,
            'payout'    => $payout,
        ) );
        self::send( $to, $subject, $body );
    }

    /**
     * Notify affiliate their payout has been processed.
     */
    public static function send_payout_processed( $affiliate, $payout ) {
        $to      = $affiliate->email;
        $subject = sprintf(
            __( 'Pago procesado: %s€ — Replanta', 'replanta-affiliates' ),
            number_format( $payout->net_amount, 2, ',', '.' )
        );
        $body = self::render_template( 'payout-processed', array(
            'affiliate' => $affiliate,
            'payout'    => $payout,
        ) );

        $attachments = array();
        if ( ! empty( $payout->invoice_path ) && file_exists( $payout->invoice_path ) ) {
            $attachments[] = $payout->invoice_path;
        }

        self::send( $to, $subject, $body, $attachments );
    }

    /**
     * Send magic-link login email.
     */
    public static function send_magic_link( $affiliate, $url ) {
        $to      = $affiliate->email;
        $subject = __( 'Tu enlace de acceso al dashboard — Replanta', 'replanta-affiliates' );
        $body    = self::render_template( 'magic-link', array(
            'affiliate' => $affiliate,
            'url'       => $url,
        ) );
        return self::send( $to, $subject, $body );
    }

    /* ── Internal helpers ───────────────────────────────── */

    private static function send( $to, $subject, $html_body, $attachments = array() ) {
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Replanta Affiliates <' . Raff_DB::get_setting( 'admin_email', get_option( 'admin_email' ) ) . '>',
        );
        return wp_mail( $to, $subject, $html_body, $headers, $attachments );
    }

    private static function render_template( $template_name, $vars = array() ) {
        $file = RAFF_DIR . 'templates/emails/' . $template_name . '.php';
        if ( ! file_exists( $file ) ) {
            return '<p>' . esc_html( $template_name ) . '</p>';
        }
        extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
        ob_start();
        include $file;
        return ob_get_clean();
    }
}
