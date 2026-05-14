<?php
/**
 * Admin: Sales management.
 *
 * @package Replanta_Affiliates
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raff_Admin_Sales {

    public static function init() {
        add_action( 'admin_post_raff_mark_sale_confirmed', array( __CLASS__, 'handle_mark_confirmed' ) );
        add_action( 'admin_post_raff_mark_sale_cancelled', array( __CLASS__, 'handle_mark_cancelled' ) );
        add_action( 'admin_post_raff_export_sales_csv', array( __CLASS__, 'handle_export_csv' ) );
    }

    public static function render_page() {
        $per_page     = 25;
        $page         = max( 1, intval( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification
        $affiliate_id = intval( $_GET['affiliate_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
        $status       = sanitize_text_field( $_GET['status'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification

        $args = array(
            'per_page' => $per_page,
            'offset'   => ( $page - 1 ) * $per_page,
        );
        if ( $affiliate_id ) {
            $args['affiliate_id'] = $affiliate_id;
        }
        if ( $status ) {
            $args['status'] = $status;
        }

        $sales = Raff_DB::list_sales( $args );
        $msg   = sanitize_text_field( $_GET['msg'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Ventas de Afiliados', 'replanta-affiliates' ); ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin-post.php?action=raff_export_sales_csv' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Exportar CSV', 'replanta-affiliates' ); ?></a>
            <hr class="wp-header-end" />

            <?php if ( 'sale_confirmed' === $msg ) : ?>
                <div class="notice notice-success"><p><?php esc_html_e( 'Venta marcada como confirmada.', 'replanta-affiliates' ); ?></p></div>
            <?php elseif ( 'sale_cancelled' === $msg ) : ?>
                <div class="notice notice-success"><p><?php esc_html_e( 'Venta marcada como cancelada.', 'replanta-affiliates' ); ?></p></div>
            <?php elseif ( 'sale_blocked_reserved' === $msg ) : ?>
                <div class="notice notice-error"><p><?php esc_html_e( 'No puedes cancelar una venta que ya está reservada en un payout. Rechaza primero el payout asociado.', 'replanta-affiliates' ); ?></p></div>
            <?php endif; ?>

            <!-- Filter by status -->
            <ul class="subsubsub">
                <?php
                $statuses = array( '' => 'Todas', 'pending' => 'Pendientes', 'confirmed' => 'Confirmadas', 'paid' => 'Pagadas', 'cancelled' => 'Canceladas' );
                $links    = array();
                foreach ( $statuses as $s => $label ) {
                    $url     = add_query_arg( array( 'page' => 'raff-sales', 'status' => $s ), admin_url( 'admin.php' ) );
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
                        <th><?php esc_html_e( 'Pedido', 'replanta-affiliates' ); ?></th>
                        <th><?php esc_html_e( 'Atribución', 'replanta-affiliates' ); ?></th>
                        <th><?php esc_html_e( 'Importe', 'replanta-affiliates' ); ?></th>
                        <th><?php esc_html_e( 'Comisión', 'replanta-affiliates' ); ?></th>
                        <th><?php esc_html_e( 'Estado', 'replanta-affiliates' ); ?></th>
                        <th><?php esc_html_e( 'Acciones', 'replanta-affiliates' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $sales ) ) : ?>
                        <tr><td colspan="8"><?php esc_html_e( 'No hay ventas.', 'replanta-affiliates' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $sales as $sale ) : ?>
                            <?php $aff = Raff_DB::get_affiliate( $sale->affiliate_id ); ?>
                            <tr>
                                <td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $sale->attributed_at ) ) ); ?></td>
                                <td>
                                    <?php if ( $aff ) : ?>
                                        <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'raff-affiliates', 'action' => 'edit', 'id' => $aff->id ), admin_url( 'admin.php' ) ) ); ?>">
                                            <?php echo esc_html( $aff->name ); ?>
                                        </a>
                                    <?php else : ?>
                                        #<?php echo intval( $sale->affiliate_id ); ?>
                                    <?php endif; ?>
                                </td>
                                <td>#<?php echo esc_html( $sale->order_id ); ?></td>
                                <td>
                                    <?php
                                    $types = array( 'cookie' => 'Enlace', 'voucher' => 'Cupón', 'both' => 'Ambos' );
                                    echo esc_html( $types[ $sale->attribution_type ] ?? $sale->attribution_type );
                                    ?>
                                </td>
                                <td><?php echo esc_html( number_format( $sale->amount, 2, ',', '.' ) ); ?>€</td>
                                <td><?php echo esc_html( number_format( $sale->commission_amount, 2, ',', '.' ) ); ?>€</td>
                                <td><span class="raff-admin-badge raff-admin-badge--<?php echo esc_attr( $sale->status ); ?>"><?php echo esc_html( ucfirst( $sale->status ) ); ?></span></td>
                                <td>
                                    <?php if ( 'pending' === $sale->status ) : ?>
                                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=raff_mark_sale_confirmed&id=' . $sale->id ), 'raff_sale_confirm_' . $sale->id ) ); ?>" class="button button-small button-primary"><?php esc_html_e( 'Confirmar', 'replanta-affiliates' ); ?></a>
                                    <?php endif; ?>
                                    <?php if ( in_array( $sale->status, array( 'pending', 'confirmed' ), true ) ) : ?>
                                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=raff_mark_sale_cancelled&id=' . $sale->id ), 'raff_sale_cancel_' . $sale->id ) ); ?>" class="button button-small" onclick="return confirm('<?php esc_attr_e( '¿Marcar esta venta como cancelada?', 'replanta-affiliates' ); ?>');"><?php esc_html_e( 'Cancelar', 'replanta-affiliates' ); ?></a>
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

    public static function handle_mark_confirmed() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $id = intval( $_GET['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        check_admin_referer( 'raff_sale_confirm_' . $id );

        $sale = Raff_DB::get_sale( $id );
        if ( $sale && 'pending' === $sale->status ) {
            Raff_DB::update_sale( $id, array(
                'status'       => 'confirmed',
                'confirmed_at' => current_time( 'mysql', true ),
            ) );
        }

        wp_safe_redirect( add_query_arg( array( 'page' => 'raff-sales', 'msg' => 'sale_confirmed' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function handle_mark_cancelled() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $id = intval( $_GET['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        check_admin_referer( 'raff_sale_cancel_' . $id );

        $sale = Raff_DB::get_sale( $id );
        if ( $sale && in_array( $sale->status, array( 'pending', 'confirmed' ), true ) ) {
            if ( Raff_DB::is_sale_reserved( $id ) ) {
                wp_safe_redirect( add_query_arg( array( 'page' => 'raff-sales', 'msg' => 'sale_blocked_reserved' ), admin_url( 'admin.php' ) ) );
                exit;
            }

            Raff_DB::update_sale( $id, array( 'status' => 'cancelled' ) );
            wp_safe_redirect( add_query_arg( array( 'page' => 'raff-sales', 'msg' => 'sale_cancelled' ), admin_url( 'admin.php' ) ) );
            exit;
        }

        wp_safe_redirect( add_query_arg( 'page', 'raff-sales', admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function handle_export_csv() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $sales = Raff_DB::list_sales( array( 'per_page' => 9999 ) );

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=ventas-afiliados-' . gmdate( 'Y-m-d' ) . '.csv' );

        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, array( 'ID', 'Afiliado ID', 'Afiliado', 'Pedido', 'Atribución', 'Importe', 'Moneda', 'Comisión %', 'Comisión', 'Estado', 'Fecha' ) );

        foreach ( $sales as $sale ) {
            $aff = Raff_DB::get_affiliate( $sale->affiliate_id );
            fputcsv( $out, array(
                $sale->id,
                $sale->affiliate_id,
                $aff ? $aff->name : '',
                $sale->order_id,
                $sale->attribution_type,
                $sale->amount,
                $sale->currency,
                $sale->commission_pct,
                $sale->commission_amount,
                $sale->status,
                $sale->attributed_at,
            ) );
        }

        fclose( $out );
        exit;
    }
}
