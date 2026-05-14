<?php
/**
 * Dashboard summary tab.
 *
 * @var object $affiliate
 * @var array  $data
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="raff-kpis">
    <div class="raff-kpi">
        <span class="raff-kpi__value"><?php echo esc_html( number_format( $data['balance_available'], 2, ',', '.' ) ); ?>€</span>
        <span class="raff-kpi__label"><?php esc_html_e( 'Saldo disponible', 'replanta-affiliates' ); ?></span>
    </div>
    <div class="raff-kpi">
        <span class="raff-kpi__value"><?php echo esc_html( number_format( $data['balance_pending'], 2, ',', '.' ) ); ?>€</span>
        <span class="raff-kpi__label"><?php esc_html_e( 'Pendiente de confirmar', 'replanta-affiliates' ); ?></span>
    </div>
    <div class="raff-kpi">
        <span class="raff-kpi__value"><?php echo intval( $data['total_visits'] ); ?></span>
        <span class="raff-kpi__label"><?php esc_html_e( 'Visitas totales', 'replanta-affiliates' ); ?></span>
    </div>
    <div class="raff-kpi">
        <span class="raff-kpi__value"><?php echo intval( $data['total_sales'] ); ?></span>
        <span class="raff-kpi__label"><?php esc_html_e( 'Ventas totales', 'replanta-affiliates' ); ?></span>
    </div>
</div>

<div class="raff-section">
    <h3><?php esc_html_e( 'Tu enlace de afiliado', 'replanta-affiliates' ); ?></h3>
    <div class="raff-ref-url">
        <input type="text" readonly value="<?php echo esc_url( $data['ref_url'] ); ?>" id="raff-ref-url" />
        <button type="button" class="raff-btn raff-btn--sm" data-copy="#raff-ref-url">
            <?php esc_html_e( 'Copiar', 'replanta-affiliates' ); ?>
        </button>
    </div>
</div>

<?php if ( $affiliate->coupon_code ) : ?>
<div class="raff-section">
    <h3><?php esc_html_e( 'Tu cupón de descuento', 'replanta-affiliates' ); ?></h3>
    <div class="raff-coupon">
        <code class="raff-coupon__code"><?php echo esc_html( $affiliate->coupon_code ); ?></code>
        <p class="raff-coupon__note"><?php esc_html_e( 'Compártelo con tu audiencia. Las compras con este cupón se te atribuyen automáticamente.', 'replanta-affiliates' ); ?></p>
    </div>
</div>
<?php endif; ?>

<?php if ( $data['balance_available'] >= $data['threshold'] ) : ?>
<div class="raff-section">
    <h3><?php esc_html_e( 'Solicitar pago', 'replanta-affiliates' ); ?></h3>
    <p><?php printf( esc_html__( 'Tienes %s€ disponibles para retirar.', 'replanta-affiliates' ), esc_html( number_format( $data['balance_available'], 2, ',', '.' ) ) ); ?></p>
    <form id="raff-payout-form" class="raff-form">
        <div class="raff-field">
            <label for="raff-payout-method"><?php esc_html_e( 'Método de pago', 'replanta-affiliates' ); ?></label>
            <select id="raff-payout-method" name="method">
                <option value="paypal" <?php selected( $affiliate->payment_method, 'paypal' ); ?>>PayPal</option>
                <option value="bank" <?php selected( $affiliate->payment_method, 'bank' ); ?>><?php esc_html_e( 'Transferencia bancaria', 'replanta-affiliates' ); ?></option>
            </select>
        </div>
        <button type="submit" class="raff-btn raff-btn--primary"><?php esc_html_e( 'Solicitar pago', 'replanta-affiliates' ); ?></button>
    </form>
    <div id="raff-payout-msg"></div>
</div>
<?php endif; ?>

<div class="raff-section">
    <h3><?php esc_html_e( 'Últimas ventas', 'replanta-affiliates' ); ?></h3>
    <?php if ( empty( $data['recent_sales'] ) ) : ?>
        <p class="raff-empty"><?php esc_html_e( 'Aún no tienes ventas registradas.', 'replanta-affiliates' ); ?></p>
    <?php else : ?>
        <table class="raff-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Fecha', 'replanta-affiliates' ); ?></th>
                    <th><?php esc_html_e( 'Pedido', 'replanta-affiliates' ); ?></th>
                    <th><?php esc_html_e( 'Importe', 'replanta-affiliates' ); ?></th>
                    <th><?php esc_html_e( 'Comisión', 'replanta-affiliates' ); ?></th>
                    <th><?php esc_html_e( 'Estado', 'replanta-affiliates' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $data['recent_sales'] as $sale ) : ?>
                    <tr>
                        <td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $sale->attributed_at ) ) ); ?></td>
                        <td>#<?php echo esc_html( $sale->order_id ); ?></td>
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
</div>
