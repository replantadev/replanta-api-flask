<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div style="max-width:600px;margin:0 auto;font-family:Arial,sans-serif;color:#1E2F23;">
    <div style="background:#1E2F23;padding:24px;text-align:center;border-radius:12px 12px 0 0;">
        <img src="https://replanta.net/wp-content/uploads/2026/03/replantav3-blanco.svg" alt="Replanta" style="height:32px;width:auto;" />
    </div>
    <div style="background:#fff;padding:32px;border:1px solid #E6F3EF;">
        <h2 style="margin:0 0 16px;">Nueva solicitud de afiliado</h2>
        <table style="width:100%;border-collapse:collapse;">
            <tr><td style="padding:8px 0;color:#6B7D76;">Nombre</td><td style="padding:8px 0;font-weight:600;"><?php echo esc_html( $affiliate->name ); ?></td></tr>
            <tr><td style="padding:8px 0;color:#6B7D76;">Email</td><td style="padding:8px 0;"><?php echo esc_html( $affiliate->email ); ?></td></tr>
            <tr><td style="padding:8px 0;color:#6B7D76;">País</td><td style="padding:8px 0;"><?php echo esc_html( $affiliate->country ); ?></td></tr>
            <tr><td style="padding:8px 0;color:#6B7D76;">Doc</td><td style="padding:8px 0;"><?php echo esc_html( strtoupper( $affiliate->doc_type ) . ': ' . $affiliate->doc_number ); ?></td></tr>
            <tr><td style="padding:8px 0;color:#6B7D76;">Ref</td><td style="padding:8px 0;"><code><?php echo esc_html( $affiliate->ref_code ); ?></code></td></tr>
        </table>
        <p style="margin-top:20px;">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=replanta-affiliates-manage&action=view&id=' . $affiliate->id ) ); ?>"
               style="background:#41999F;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;display:inline-block;">
                Revisar solicitud →
            </a>
        </p>
    </div>
</div>
