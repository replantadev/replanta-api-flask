<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div style="max-width:600px;margin:0 auto;font-family:Arial,sans-serif;color:#1E2F23;">
    <div style="background:#1E2F23;padding:24px;text-align:center;border-radius:12px 12px 0 0;">
        <img src="https://replanta.net/wp-content/uploads/2026/03/replantav3-blanco.svg" alt="Replanta" style="height:32px;width:auto;" />
    </div>
    <div style="background:#fff;padding:32px;border:1px solid #E6F3EF;">
        <h2 style="margin:0 0 16px;color:#1E2F23;">Pago procesado</h2>
        <p>Hola <?php echo esc_html( $affiliate->name ); ?>,</p>
        <p>Tu solicitud de pago ha sido procesada correctamente.</p>
        <table style="width:100%;border-collapse:collapse;margin:20px 0;">
            <tr><td style="padding:8px 0;color:#6B7D76;">Importe bruto</td><td style="padding:8px 0;"><?php echo esc_html( number_format( $payout->amount, 2, ',', '.' ) ); ?>€</td></tr>
            <tr><td style="padding:8px 0;color:#6B7D76;">Comisión</td><td style="padding:8px 0;">-<?php echo esc_html( number_format( $payout->fee, 2, ',', '.' ) ); ?>€</td></tr>
            <tr style="border-top:2px solid #E6F3EF;"><td style="padding:12px 0;font-weight:700;">Neto recibido</td><td style="padding:12px 0;font-weight:700;font-size:18px;color:#00a32a;"><?php echo esc_html( number_format( $payout->net_amount, 2, ',', '.' ) ); ?>€</td></tr>
            <tr><td style="padding:8px 0;color:#6B7D76;">Método</td><td style="padding:8px 0;"><?php echo esc_html( 'paypal' === $payout->method ? 'PayPal' : 'Transferencia bancaria' ); ?></td></tr>
        </table>
        <?php if ( ! empty( $payout->invoice_path ) ) : ?>
        <p style="color:#6B7D76;font-size:14px;">Adjuntamos la factura correspondiente a este pago.</p>
        <?php endif; ?>
    </div>
    <div style="background:#F7FBF9;padding:16px;text-align:center;font-size:13px;color:#6B7D76;border-radius:0 0 12px 12px;">
        <p style="margin:0;">Replanta · <a href="https://replanta.net" style="color:#41999F;">replanta.net</a></p>
    </div>
</div>
