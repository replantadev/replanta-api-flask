<?php
/**
 * Dashboard sales tab.
 *
 * @var object $affiliate
 * @var array  $data
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$page     = max( 1, intval( $_GET['spag'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification
$per_page = 20;
$sales    = Raff_DB::list_sales( array(
    'affiliate_id' => $affiliate->id,
    'per_page'     => $per_page,
    'offset'       => ( $page - 1 ) * $per_page,
) );
?>

<h3><?php esc_html_e( 'Historial de ventas', 'replanta-affiliates' ); ?></h3>

<?php if ( empty( $sales ) ) : ?>
    <p class="raff-empty"><?php esc_html_e( 'No hay ventas registradas.', 'replanta-affiliates' ); ?></p>
<?php else : ?>
    <table class="raff-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Fecha', 'replanta-affiliates' ); ?></th>
                <th><?php esc_html_e( 'Pedido', 'replanta-affiliates' ); ?></th>
                <th><?php esc_html_e( 'Tipo', 'replanta-affiliates' ); ?></th>
                <th><?php esc_html_e( 'Importe', 'replanta-affiliates' ); ?></th>
                <th><?php esc_html_e( 'Comisión', 'replanta-affiliates' ); ?></th>
                <th><?php esc_html_e( 'Estado', 'replanta-affiliates' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $sales as $sale ) : ?>
                <tr>
                    <td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $sale->attributed_at ) ) ); ?></td>
                    <td>#<?php echo esc_html( $sale->order_id ); ?></td>
                    <td>
                        <?php
                        $types = array( 'cookie' => 'Enlace', 'voucher' => 'Cupón', 'both' => 'Ambos' );
                        echo esc_html( $types[ $sale->attribution_type ] ?? $sale->attribution_type );
                        ?>
                    </td>
                    <td><?php echo esc_html( number_format( $sale->amount, 2, ',', '.' ) ); ?>€</td>
                    <td><?php echo esc_html( number_format( $sale->commission_amount, 2, ',', '.' ) ); ?>€</td>
                    <td>
                        <span class="raff-badge raff-badge--<?php echo esc_attr( $sale->status ); ?>">
                            <?php echo esc_html( ucfirst( $sale->status ) ); ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
