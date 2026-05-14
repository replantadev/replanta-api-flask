<?php
/**
 * Admin: Payouts management.
 *
 * @package Replanta_Affiliates
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raff_Admin_Payouts {

    public static function init() {
        add_action( 'admin_post_raff_set_payout_processing', array( __CLASS__, 'handle_set_processing' ) );
        add_action( 'admin_post_raff_mark_payout_paid', array( __CLASS__, 'handle_mark_paid' ) );
        add_action( 'admin_post_raff_reject_payout', array( __CLASS__, 'handle_reject' ) );
        add_action( 'admin_post_raff_export_payouts_csv', array( __CLASS__, 'handle_export_csv' ) );
    }

    public static function render_page() {
        $per_page = 25;
        $page     = max( 1, intval( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification
        $status   = sanitize_text_field( $_GET['status'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification

        $args = array(
            'per_page' => $per_page,
            'offset'   => ( $page - 1 ) * $per_page,
        );
        if ( $status ) {
            $args['status'] = $status;
        }

        $payouts = Raff_DB::list_payouts( $args );
        $msg     = sanitize_text_field( $_GET['msg'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Pagos a Afiliados', 'replanta-affiliates' ); ?></h1>
            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=raff_export_payouts_csv' ), 'raff_export_payouts_csv' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Exportar CSV', 'replanta-affiliates' ); ?></a>
            <hr class="wp-header-end" />

            <?php if ( 'processing' === $msg ) : ?>
                <div class="notice notice-success"><p><?php esc_html_e( 'Pago marcado como procesando.', 'replanta-affiliates' ); ?></p></div>
            <?php elseif ( 'paid' === $msg ) : ?>
                <div class="notice notice-success"><p><?php esc_html_e( 'Pago marcado como pagado.', 'replanta-affiliates' ); ?></p></div>
            <?php elseif ( 'rejected' === $msg ) : ?>
                <div class="notice notice-success"><p><?php esc_html_e( 'Pago rechazado y reservas liberadas.', 'replanta-affiliates' ); ?></p></div>
            <?php endif; ?>

            <!-- Filter by status -->
            <ul class="subsubsub">
                <?php
                $statuses = array( '' => 'Todos', 'requested' => 'Solicitados', 'processing' => 'Procesando', 'paid' => 'Pagados', 'rejected' => 'Rechazados' );
                $links    = array();
                foreach ( $statuses as $s => $label ) {
                    $url     = add_query_arg( array( 'page' => 'raff-payouts', 'status' => $s ), admin_url( 'admin.php' ) );
                    $current = ( $status === $s ) ? ' class="current"' : '';
                    $links[] = sprintf( '<li><a href="%s"%s>%s</a></li>', esc_url( $url ), $current, esc_html( $label ) );
                }
                echo implode( ' | ', $links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                ?>
            </ul>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Fecha', 'replanta-affiliates' ); ?></th>
                        <th><?php esc_html_e( 'Afiliado', 'replanta-affiliates' ); ?></th>
                        <th><?php esc_html_e( 'Bruto', 'replanta-affiliates' ); ?></th>
                        <th><?php esc_html_e( 'Comisión', 'replanta-affiliates' ); ?></th>
                        <th><?php esc_html_e( 'Neto', 'replanta-affiliates' ); ?></th>
                        <th><?php esc_html_e( 'Método', 'replanta-affiliates' ); ?></th>
                        <th><?php esc_html_e( 'Ref. pago', 'replanta-affiliates' ); ?></th>
                        <th><?php esc_html_e( 'Factura', 'replanta-affiliates' ); ?></th>
                        <th><?php esc_html_e( 'Estado', 'replanta-affiliates' ); ?></th>
                        <th><?php esc_html_e( 'Acciones', 'replanta-affiliates' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $payouts ) ) : ?>
                        <tr><td colspan="10"><?php esc_html_e( 'No hay pagos.', 'replanta-affiliates' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $payouts as $payout ) : ?>
                            <?php $aff = Raff_DB::get_affiliate( $payout->affiliate_id ); ?>
                            <tr>
                                <td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $payout->requested_at ) ) ); ?></td>
                                <td>
                                    <?php if ( $aff ) : ?>
                                        <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'raff-affiliates', 'action' => 'edit', 'id' => $aff->id ), admin_url( 'admin.php' ) ) ); ?>">
                                            <?php echo esc_html( $aff->name ); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( number_format( $payout->amount, 2, ',', '.' ) ); ?>€</td>
                                <td><?php echo esc_html( number_format( $payout->fee, 2, ',', '.' ) ); ?>€</td>
                                <td><strong><?php echo esc_html( number_format( $payout->net_amount, 2, ',', '.' ) ); ?>€</strong></td>
                                <td><?php echo esc_html( ucfirst( $payout->method ) ); ?></td>
                                <td><?php echo ! empty( $payout->payment_ref ) ? esc_html( $payout->payment_ref ) : '&mdash;'; ?></td>
                                <td><?php echo $payout->invoice_number ? esc_html( $payout->invoice_number ) : '&mdash;'; ?></td>
                                <td><span class="raff-admin-badge raff-admin-badge--<?php echo esc_attr( $payout->status ); ?>"><?php echo esc_html( ucfirst( $payout->status ) ); ?></span></td>
                                <td>
                                    <?php if ( 'requested' === $payout->status ) : ?>
                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:4px;display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                                            <input type="hidden" name="action" value="raff_set_payout_processing" />
                                            <input type="hidden" name="id" value="<?php echo esc_attr( $payout->id ); ?>" />
                                            <?php wp_nonce_field( 'raff_processing_' . $payout->id ); ?>
                                            <input type="text" name="payment_ref" value="<?php echo esc_attr( $payout->payment_ref ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Ref. pago (opcional)', 'replanta-affiliates' ); ?>" style="min-width:140px;" />
                                            <button type="submit" class="button button-small"><?php esc_html_e( 'Marcar procesando', 'replanta-affiliates' ); ?></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ( in_array( $payout->status, array( 'requested', 'processing' ), true ) ) : ?>
                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                                            <input type="hidden" name="action" value="raff_mark_payout_paid" />
                                            <input type="hidden" name="id" value="<?php echo esc_attr( $payout->id ); ?>" />
                                            <?php wp_nonce_field( 'raff_pay_' . $payout->id ); ?>
                                            <input type="text" name="payment_ref" value="<?php echo esc_attr( $payout->payment_ref ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Ref. pago', 'replanta-affiliates' ); ?>" style="min-width:140px;" />
                                            <button type="submit" class="button button-small button-primary"><?php esc_html_e( 'Marcar pagado', 'replanta-affiliates' ); ?></button>
                                        </form>
                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:4px;" onsubmit="return confirm('<?php esc_attr_e( '¿Rechazar esta solicitud? Las ventas reservadas volverán a estar disponibles.', 'replanta-affiliates' ); ?>');">
                                            <input type="hidden" name="action" value="raff_reject_payout" />
                                            <input type="hidden" name="id" value="<?php echo esc_attr( $payout->id ); ?>" />
                                            <?php wp_nonce_field( 'raff_reject_payout_' . $payout->id ); ?>
                                            <button type="submit" class="button button-small"><?php esc_html_e( 'Rechazar', 'replanta-affiliates' ); ?></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function handle_set_processing() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $id = intval( $_REQUEST['id'] ?? 0 );
        check_admin_referer( 'raff_processing_' . $id );

        $payment_ref = sanitize_text_field( wp_unslash( $_POST['payment_ref'] ?? '' ) );
        $payout      = Raff_DB::get_payout( $id );

        if ( $payout && 'requested' === $payout->status ) {
            $data = array(
                'status'       => 'processing',
                'processed_at' => current_time( 'mysql', true ),
            );
            if ( '' !== $payment_ref ) {
                $data['payment_ref'] = $payment_ref;
            }
            Raff_DB::update_payout( $id, $data );
        }

        wp_safe_redirect( add_query_arg( array( 'page' => 'raff-payouts', 'msg' => 'processing' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function handle_mark_paid() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $id = intval( $_REQUEST['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        check_admin_referer( 'raff_pay_' . $id );

        $payment_ref = null;
        if ( isset( $_POST['payment_ref'] ) ) {
            $payment_ref = sanitize_text_field( wp_unslash( $_POST['payment_ref'] ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'raff_payouts';
        $payout = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

        if ( $payout && in_array( $payout->status, array( 'requested', 'processing' ), true ) ) {
            /* Generate invoice */
            $invoice_number = Raff_Invoice::generate_for_payout( $payout );

            $update_data = array(
                'status'         => 'paid',
                'paid_at'        => current_time( 'mysql', true ),
                'processed_at'   => current_time( 'mysql', true ),
                'invoice_number' => $invoice_number,
            );
            $update_format = array( '%s', '%s', '%s', '%s' );

            // Keep existing reference for legacy GET flows; allow explicit empty ref only from POST.
            if ( null !== $payment_ref ) {
                $update_data['payment_ref'] = $payment_ref;
                $update_format[] = '%s';
            }

            $wpdb->update(
                $table,
                $update_data,
                array( 'id' => $id ),
                $update_format,
                array( '%d' )
            );

            /* Mark only sales reserved for this payout as paid */
            Raff_DB::mark_reserved_sales_paid( $payout->id );

            /* Send notification */
            $aff = Raff_DB::get_affiliate( $payout->affiliate_id );
            if ( $aff ) {
                $effective_ref = null !== $payment_ref ? $payment_ref : ( $payout->payment_ref ?? '' );
                Raff_Email::send_payout_processed( $aff, (object) array_merge( (array) $payout, array(
                    'status'         => 'paid',
                    'invoice_number' => $invoice_number,
                    'payment_ref'    => $effective_ref,
                ) ) );
            }
        }

        wp_safe_redirect( add_query_arg( array( 'page' => 'raff-payouts', 'msg' => 'paid' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function handle_reject() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $id = intval( $_REQUEST['id'] ?? 0 );
        check_admin_referer( 'raff_reject_payout_' . $id );

        global $wpdb;
        $table  = $wpdb->prefix . 'raff_payouts';
        $payout = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

        if ( $payout && in_array( $payout->status, array( 'requested', 'processing' ), true ) ) {
            /* Release reserved sales back to 'confirmed' */
            Raff_DB::release_payout_reservation( $payout->id );

            $wpdb->update(
                $table,
                array( 'status' => 'rejected', 'notes' => sanitize_text_field( $_POST['notes'] ?? '' ) ),
                array( 'id'     => $id ),
                array( '%s', '%s' ),
                array( '%d' )
            );
        }

        wp_safe_redirect( add_query_arg( array( 'page' => 'raff-payouts', 'msg' => 'rejected' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function handle_export_csv() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        check_admin_referer( 'raff_export_payouts_csv' );

        $payouts = Raff_DB::list_payouts( array( 'per_page' => 9999 ) );

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=pagos-afiliados-' . gmdate( 'Y-m-d' ) . '.csv' );

        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, array( 'ID', 'Afiliado', 'Bruto', 'Comisión', 'Neto', 'Moneda', 'Método', 'Ref. pago', 'Factura', 'Estado', 'Fecha solicitud', 'Fecha pago' ) );

        foreach ( $payouts as $payout ) {
            $aff = Raff_DB::get_affiliate( $payout->affiliate_id );
            fputcsv( $out, array(
                $payout->id,
                $aff ? $aff->name : '#' . $payout->affiliate_id,
                $payout->amount,
                $payout->fee,
                $payout->net_amount,
                $payout->currency,
                $payout->method,
                $payout->payment_ref ?? '',
                $payout->invoice_number ?? '',
                $payout->status,
                $payout->requested_at,
                $payout->paid_at ?? '',
            ) );
        }

        fclose( $out );
        exit;
    }
}
