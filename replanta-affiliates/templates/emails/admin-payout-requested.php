<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div style="max-width:600px;margin:0 auto;font-family:Arial,sans-serif;color:#1E2F23;">
    <div style="background:#1E2F23;padding:24px;text-align:center;border-radius:12px 12px 0 0;">
        <img src="https://replanta.net/wp-content/uploads/2026/03/replantav3-blanco.svg" alt="Replanta" style="height:32px;width:auto;" />
    </div>
    <div style="background:#fff;padding:32px;border:1px solid #E6F3EF;">
        <h2 style="margin:0 0 16px;color:#1E2F23;">Nueva solicitud de pago de afiliado</h2>
        <p><strong>Afiliado:</strong> <?php echo esc_html( $affiliate->name ); ?> (<?php echo esc_html( $affiliate->email ); ?>)</p>
        <p><strong>ID pago:</strong> #<?php echo intval( $payout->id ); ?></p>
        <p><strong>Método:</strong> <?php echo esc_html( ucfirst( $payout->method ) ); ?></p>
        <p><strong>Bruto:</strong> <?php echo esc_html( number_format( (float) $payout->amount, 2, ',', '.' ) ); ?>€</p>
        <p><strong>Comisión:</strong> <?php echo esc_html( number_format( (float) $payout->fee, 2, ',', '.' ) ); ?>€</p>
        <p><strong>Neto:</strong> <strong><?php echo esc_html( number_format( (float) $payout->net_amount, 2, ',', '.' ) ); ?>€</strong></p>
        <p><strong>Fecha:</strong> <?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $payout->requested_at ?? 'now' ) ) ); ?></p>
        <p style="margin-top:18px;">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=raff-payouts' ) ); ?>" style="background:#41999F;color:#fff;text-decoration:none;padding:10px 16px;border-radius:6px;display:inline-block;">Revisar pagos en admin</a>
        </p>
    </div>
    <div style="background:#F7FBF9;padding:16px;text-align:center;font-size:13px;color:#6B7D76;border-radius:0 0 12px 12px;">
        <p style="margin:0;">Replanta Affiliates</p>
    </div>
</div>
