<?php
/**
 * Invoice generator (HTML-based, no external PDF library).
 *
 * Generates an HTML invoice and optionally serves it as a downloadable
 * page with print-to-PDF hint. A lightweight approach that avoids
 * requiring DomPDF or wkhtmltopdf on the server.
 *
 * @package Replanta_Affiliates
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raff_Invoice {

    public static function init() {
        add_action( 'template_redirect', array( __CLASS__, 'maybe_serve_invoice' ) );
    }

    /* ── Generate invoice number & store ────────────────── */
    public static function generate_for_payout( $payout ) {
        $year   = gmdate( 'Y' );
        $prefix = 'RAFF';

        /* Get next sequential number for this year */
        global $wpdb;
        $table = $wpdb->prefix . 'raff_payouts';
        $last  = $wpdb->get_var( $wpdb->prepare(
            "SELECT invoice_number FROM {$table} WHERE invoice_number LIKE %s ORDER BY id DESC LIMIT 1",
            $prefix . '-' . $year . '-%'
        ) );

        $seq = 1;
        if ( $last ) {
            $parts = explode( '-', $last );
            $seq   = intval( end( $parts ) ) + 1;
        }

        return sprintf( '%s-%s-%04d', $prefix, $year, $seq );
    }

    /* ── Serve invoice as HTML page ─────────────────────── */
    public static function maybe_serve_invoice() {
        if ( empty( $_GET['raff_invoice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            return;
        }

        $payout_id = intval( $_GET['raff_invoice'] ); // phpcs:ignore WordPress.Security.NonceVerification

        /* Auth: admin OR affiliate with valid session */
        $allowed = false;

        if ( current_user_can( 'manage_options' ) ) {
            $allowed = true;
        } elseif ( ! empty( $_COOKIE['raff_session'] ) ) {
            $token     = sanitize_text_field( $_COOKIE['raff_session'] );
            $affiliate = Raff_DB::get_affiliate_by_token( $token );
            if ( $affiliate ) {
                global $wpdb;
                $table  = $wpdb->prefix . 'raff_payouts';
                $payout = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $payout_id ) );
                if ( $payout && (int) $payout->affiliate_id === (int) $affiliate->id ) {
                    $allowed = true;
                }
            }
        }

        if ( ! $allowed ) {
            wp_die( esc_html__( 'No tienes permiso para ver esta factura.', 'replanta-affiliates' ), 403 );
        }

        global $wpdb;
        $table  = $wpdb->prefix . 'raff_payouts';
        $payout = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $payout_id ) );

        if ( ! $payout || empty( $payout->invoice_number ) ) {
            wp_die( esc_html__( 'Factura no encontrada.', 'replanta-affiliates' ), 404 );
        }

        $aff = Raff_DB::get_affiliate( $payout->affiliate_id );
        if ( ! $aff ) {
            wp_die( esc_html__( 'Afiliado no encontrado.', 'replanta-affiliates' ), 404 );
        }

        /* Company data */
        $company = array(
            'name'    => Raff_DB::get_setting( 'company_name', 'Replanta S.L.' ),
            'nif'     => Raff_DB::get_setting( 'company_cif', '' ),
            'address' => Raff_DB::get_setting( 'company_address', '' ),
            'email'   => Raff_DB::get_setting( 'admin_email', get_option( 'admin_email' ) ),
        );

        self::render_invoice_html( $payout, $aff, $company );
        exit;
    }

    /* ── Render HTML invoice (print-friendly) ───────────── */
    private static function render_invoice_html( $payout, $aff, $company ) {
        $date_issued = date_i18n( 'd/m/Y', strtotime( $payout->paid_at ?? $payout->requested_at ) );
        ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title><?php printf( esc_html__( 'Factura %s', 'replanta-affiliates' ), esc_html( $payout->invoice_number ) ); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; color: #1E2F23; background: #fff; padding: 40px; max-width: 800px; margin: 0 auto; }
        .inv-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; border-bottom: 3px solid #93F1C9; padding-bottom: 20px; }
        .inv-header h1 { font-size: 28px; color: #1E2F23; }
        .inv-header .inv-number { font-size: 14px; color: #666; }
        .inv-parties { display: flex; justify-content: space-between; margin-bottom: 40px; }
        .inv-party { width: 45%; }
        .inv-party h3 { font-size: 12px; text-transform: uppercase; color: #41999F; margin-bottom: 8px; letter-spacing: 1px; }
        .inv-party p { font-size: 14px; line-height: 1.6; }
        .inv-details { margin-bottom: 30px; }
        .inv-details table { width: 100%; border-collapse: collapse; }
        .inv-details th { background: #1E2F23; color: #fff; padding: 10px 15px; text-align: left; font-size: 13px; }
        .inv-details td { padding: 10px 15px; border-bottom: 1px solid #eee; font-size: 14px; }
        .inv-details .total-row td { font-weight: bold; border-top: 2px solid #1E2F23; font-size: 16px; }
        .inv-footer { margin-top: 40px; text-align: center; font-size: 12px; color: #999; }
        .inv-print { text-align: center; margin-bottom: 20px; }
        .inv-print button { background: #41999F; color: #fff; border: none; padding: 10px 30px; border-radius: 6px; font-size: 14px; cursor: pointer; }
        .inv-print button:hover { background: #357f84; }
        @media print {
            .inv-print { display: none; }
            body { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="inv-print">
        <button onclick="window.print()"><?php esc_html_e( 'Imprimir / Guardar como PDF', 'replanta-affiliates' ); ?></button>
    </div>

    <div class="inv-header">
        <div>
            <h1><?php esc_html_e( 'FACTURA', 'replanta-affiliates' ); ?></h1>
            <p class="inv-number"><?php echo esc_html( $payout->invoice_number ); ?></p>
        </div>
        <div style="text-align: right;">
            <p><strong><?php esc_html_e( 'Fecha:', 'replanta-affiliates' ); ?></strong> <?php echo esc_html( $date_issued ); ?></p>
        </div>
    </div>

    <div class="inv-parties">
        <div class="inv-party">
            <h3><?php esc_html_e( 'Emisor (Afiliado)', 'replanta-affiliates' ); ?></h3>
            <p>
                <strong><?php echo esc_html( $aff->name ); ?></strong><br>
                <?php if ( ! empty( $aff->doc_type ) && ! empty( $aff->doc_number ) ) : ?>
                    <?php echo esc_html( strtoupper( $aff->doc_type ) . ': ' . $aff->doc_number ); ?><br>
                <?php endif; ?>
                <?php echo esc_html( $aff->email ); ?><br>
                <?php if ( ! empty( $aff->country ) ) : ?>
                    <?php echo esc_html( $aff->country ); ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="inv-party">
            <h3><?php esc_html_e( 'Receptor', 'replanta-affiliates' ); ?></h3>
            <p>
                <strong><?php echo esc_html( $company['name'] ); ?></strong><br>
                <?php if ( $company['nif'] ) : ?>
                    NIF: <?php echo esc_html( $company['nif'] ); ?><br>
                <?php endif; ?>
                <?php if ( $company['address'] ) : ?>
                    <?php echo esc_html( $company['address'] ); ?><br>
                <?php endif; ?>
                <?php echo esc_html( $company['email'] ); ?>
            </p>
        </div>
    </div>

    <div class="inv-details">
        <table>
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Concepto', 'replanta-affiliates' ); ?></th>
                    <th style="text-align:right"><?php esc_html_e( 'Importe', 'replanta-affiliates' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php printf( esc_html__( 'Comisiones de afiliación — Código %s', 'replanta-affiliates' ), esc_html( $aff->ref_code ) ); ?></td>
                    <td style="text-align:right"><?php echo esc_html( number_format( $payout->amount, 2, ',', '.' ) ); ?> <?php echo esc_html( $payout->currency ); ?></td>
                </tr>
                <?php if ( $payout->fee > 0 ) : ?>
                    <tr>
                        <td><?php printf( esc_html__( 'Comisión de procesamiento (%s)', 'replanta-affiliates' ), esc_html( ucfirst( $payout->method ) ) ); ?></td>
                        <td style="text-align:right">-<?php echo esc_html( number_format( $payout->fee, 2, ',', '.' ) ); ?> <?php echo esc_html( $payout->currency ); ?></td>
                    </tr>
                <?php endif; ?>
                <tr class="total-row">
                    <td><?php esc_html_e( 'TOTAL A RECIBIR', 'replanta-affiliates' ); ?></td>
                    <td style="text-align:right"><?php echo esc_html( number_format( $payout->net_amount, 2, ',', '.' ) ); ?> <?php echo esc_html( $payout->currency ); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="inv-footer">
        <p><?php printf( esc_html__( 'Factura generada por el programa de afiliados de %s', 'replanta-affiliates' ), esc_html( $company['name'] ) ); ?></p>
    </div>
</body>
</html>
        <?php
    }
}
