<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div style="max-width:600px;margin:0 auto;font-family:Arial,sans-serif;color:#1E2F23;">
    <div style="background:#1E2F23;padding:24px;text-align:center;border-radius:12px 12px 0 0;">
        <img src="https://replanta.net/wp-content/uploads/2026/03/replantav3-blanco.svg" alt="Replanta" style="height:32px;width:auto;" />
    </div>
    <div style="background:#fff;padding:32px;border:1px solid #E6F3EF;">
        <h2 style="margin:0 0 16px;color:#00a32a;">¡Nueva venta registrada!</h2>
        <p>Hola <?php echo esc_html( $affiliate->name ); ?>,</p>
        <p>¡Se ha registrado una nueva venta a través de tu enlace de afiliado!</p>
        <table style="width:100%;border-collapse:collapse;margin:20px 0;">
            <tr><td style="padding:8px 0;color:#6B7D76;">Pedido</td><td style="padding:8px 0;font-weight:600;"><?php echo esc_html( $sale->order_id ); ?></td></tr>
            <tr><td style="padding:8px 0;color:#6B7D76;">Importe</td><td style="padding:8px 0;"><?php echo esc_html( number_format( $sale->amount, 2, ',', '.' ) . ' ' . $sale->currency ); ?></td></tr>
            <tr><td style="padding:8px 0;color:#6B7D76;">Tu comisión</td><td style="padding:8px 0;font-weight:700;color:#00a32a;"><?php echo esc_html( number_format( $sale->commission_amount, 2, ',', '.' ) . ' ' . $sale->currency ); ?></td></tr>
            <tr><td style="padding:8px 0;color:#6B7D76;">Estado</td><td style="padding:8px 0;">Pendiente de confirmación</td></tr>
        </table>
        <p style="color:#6B7D76;font-size:14px;">La comisión se confirmará tras el periodo de garantía (<?php echo esc_html( Raff_DB::get_setting( 'confirmation_days', 30 ) ); ?> días).</p>
    </div>
    <div style="background:#F7FBF9;padding:16px;text-align:center;font-size:13px;color:#6B7D76;border-radius:0 0 12px 12px;">
        <p style="margin:0;">Replanta · <a href="https://replanta.net" style="color:#41999F;">replanta.net</a></p>
    </div>
</div>
