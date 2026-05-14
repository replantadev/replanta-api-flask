<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div style="max-width:600px;margin:0 auto;font-family:Arial,sans-serif;color:#1E2F23;">
    <div style="background:#1E2F23;padding:24px;text-align:center;border-radius:12px 12px 0 0;">
        <img src="https://replanta.net/wp-content/uploads/2026/03/replantav3-blanco.svg" alt="Replanta" style="height:32px;width:auto;" />
    </div>
    <div style="background:#fff;padding:32px;border:1px solid #E6F3EF;">
        <h2 style="margin:0 0 16px;color:#1E2F23;">Accede a tu dashboard</h2>
        <p>Hola <?php echo esc_html( $affiliate->name ); ?>,</p>
        <p>Haz clic en el siguiente botón para acceder a tu panel de afiliado. Este enlace es válido durante 1 hora.</p>
        <p style="text-align:center;margin:28px 0;">
            <a href="<?php echo esc_url( $url ); ?>" style="background:#41999F;color:#fff;padding:16px 32px;border-radius:8px;text-decoration:none;font-weight:700;font-size:16px;display:inline-block;">
                Entrar al Dashboard →
            </a>
        </p>
        <p style="color:#6B7D76;font-size:13px;">Si no has solicitado este enlace, puedes ignorar este email.</p>
    </div>
    <div style="background:#F7FBF9;padding:16px;text-align:center;font-size:13px;color:#6B7D76;border-radius:0 0 12px 12px;">
        <p style="margin:0;">Replanta · <a href="https://replanta.net" style="color:#41999F;">replanta.net</a></p>
    </div>
</div>
