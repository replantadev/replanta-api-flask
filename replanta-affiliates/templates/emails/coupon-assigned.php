<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div style="max-width:600px;margin:0 auto;font-family:Arial,sans-serif;color:#1E2F23;">
    <div style="background:#1E2F23;padding:24px;text-align:center;border-radius:12px 12px 0 0;">
        <img src="https://replanta.net/wp-content/uploads/2026/03/replantav3-blanco.svg" alt="Replanta" style="height:32px;width:auto;" />
    </div>
    <div style="background:#fff;padding:32px;border:1px solid #E6F3EF;">
        <h2 style="margin:0 0 16px;color:#1E2F23;">¡Tu cupón está listo!</h2>
        <p>Hola <?php echo esc_html( $affiliate->name ); ?>,</p>
        <p>Ya tienes tu cupón de afiliado asignado. Tus referidos recibirán un descuento al usarlo, y tú ganarás una comisión del <strong><?php echo esc_html( $affiliate->commission_pct ); ?>%</strong> por cada venta.</p>
        <div style="background:linear-gradient(135deg,#F7D450 0%,#f5cc3d 100%);border-radius:12px;padding:24px;text-align:center;margin:24px 0;">
            <div style="font-size:13px;color:#1E2F23;margin-bottom:8px;">Tu cupón de descuento</div>
            <div style="font-size:28px;font-weight:800;color:#1E2F23;letter-spacing:2px;"><?php echo esc_html( $affiliate->coupon_code ); ?></div>
        </div>
        <div style="background:#F7FBF9;border:1px solid #E6F3EF;border-radius:8px;padding:16px;margin:20px 0;">
            <strong>Tu enlace de referido:</strong><br>
            <code style="font-size:14px;">https://replanta.net/?ref=<?php echo esc_attr( $affiliate->ref_code ); ?></code>
        </div>
        <p>
            <a href="https://replanta.net/mediakit/affiliates.html" style="background:#41999F;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;display:inline-block;">
                Ir al Toolkit de Afiliados →
            </a>
        </p>
    </div>
    <div style="background:#F7FBF9;padding:16px;text-align:center;font-size:13px;color:#6B7D76;border-radius:0 0 12px 12px;">
        <p style="margin:0;">Replanta · <a href="https://replanta.net" style="color:#41999F;">replanta.net</a></p>
    </div>
</div>
