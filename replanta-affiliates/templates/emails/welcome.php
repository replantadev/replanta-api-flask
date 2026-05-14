<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div style="max-width:600px;margin:0 auto;font-family:Arial,sans-serif;color:#1E2F23;">
    <div style="background:#1E2F23;padding:24px;text-align:center;border-radius:12px 12px 0 0;">
        <img src="https://replanta.net/wp-content/uploads/2026/03/replantav3-blanco.svg" alt="Replanta" style="height:32px;width:auto;" />
    </div>
    <div style="background:#fff;padding:32px;border:1px solid #E6F3EF;">
        <h2 style="margin:0 0 16px;color:#1E2F23;">¡Hola <?php echo esc_html( $affiliate->name ); ?>!</h2>
        <p>Hemos recibido tu solicitud para el <strong>Programa de Afiliados de Replanta</strong>.</p>
        <p>Nuestro equipo revisará tu documentación y te contactaremos lo antes posible. El proceso suele tardar entre 24-48 horas laborables.</p>
        <div style="background:#F7FBF9;border:1px solid #E6F3EF;border-radius:8px;padding:16px;margin:20px 0;">
            <strong>Tu código de referido:</strong> <code style="background:#93F1C9;color:#1E2F23;padding:4px 8px;border-radius:4px;font-weight:700;"><?php echo esc_html( $affiliate->ref_code ); ?></code>
        </div>
        <p style="color:#6B7D76;font-size:14px;">Este código se activará cuando tu solicitud sea aprobada.</p>
    </div>
    <div style="background:#F7FBF9;padding:16px;text-align:center;font-size:13px;color:#6B7D76;border-radius:0 0 12px 12px;">
        <p style="margin:0;">Replanta · Hosting WordPress sostenible · <a href="https://replanta.net" style="color:#41999F;">replanta.net</a></p>
    </div>
</div>
