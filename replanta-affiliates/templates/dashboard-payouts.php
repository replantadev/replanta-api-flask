<?php
/**
 * Dashboard payouts tab.
 *
 * @var object $affiliate
 * @var array  $data
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<h3><?php esc_html_e( 'Historial de pagos', 'replanta-affiliates' ); ?></h3>

<?php if ( empty( $data['payouts'] ) ) : ?>
    <p class="raff-empty"><?php esc_html_e( 'No hay pagos registrados.', 'replanta-affiliates' ); ?></p>
<?php else : ?>
    <table class="raff-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Fecha', 'replanta-affiliates' ); ?></th>
                <th><?php esc_html_e( 'Bruto', 'replanta-affiliates' ); ?></th>
                <th><?php esc_html_e( 'Comisión', 'replanta-affiliates' ); ?></th>
                <th><?php esc_html_e( 'Neto', 'replanta-affiliates' ); ?></th>
                <th><?php esc_html_e( 'Método', 'replanta-affiliates' ); ?></th>
                <th><?php esc_html_e( 'Ref. pago', 'replanta-affiliates' ); ?></th>
                <th><?php esc_html_e( 'Estado', 'replanta-affiliates' ); ?></th>
                <th><?php esc_html_e( 'Factura', 'replanta-affiliates' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $data['payouts'] as $payout ) : ?>
                <tr>
                    <td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $payout->requested_at ) ) ); ?></td>
                    <td><?php echo esc_html( number_format( $payout->amount, 2, ',', '.' ) ); ?>€</td>
                    <td><?php echo esc_html( number_format( $payout->fee, 2, ',', '.' ) ); ?>€</td>
                    <td><strong><?php echo esc_html( number_format( $payout->net_amount, 2, ',', '.' ) ); ?>€</strong></td>
                    <td><?php echo esc_html( ucfirst( $payout->method ) ); ?></td>
                    <td><?php echo ! empty( $payout->payment_ref ) ? esc_html( $payout->payment_ref ) : '&mdash;'; ?></td>
                    <td>
                        <span class="raff-badge raff-badge--<?php echo esc_attr( $payout->status ); ?>">
                            <?php echo esc_html( ucfirst( $payout->status ) ); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ( ! empty( $payout->invoice_number ) ) : ?>
                            <a href="<?php echo esc_url( add_query_arg( array( 'raff_invoice' => $payout->id, 'raff_token' => $_COOKIE['raff_session'] ?? '' ), home_url() ) ); ?>" class="raff-btn raff-btn--sm">
                                <?php esc_html_e( 'Descargar', 'replanta-affiliates' ); ?>
                            </a>
                        <?php else : ?>
                            &mdash;
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
