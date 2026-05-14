<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div style="max-width:600px;margin:0 auto;font-family:'Segoe UI',Arial,sans-serif;color:#1E2F23;background:#fff;">
    <!-- Header -->
    <div style="background:#1E2F23;padding:32px 24px;text-align:center;border-radius:12px 12px 0 0;">
        <img src="https://replanta.net/wp-content/uploads/2026/03/replantav3-blanco.svg" alt="Replanta" style="height:36px;width:auto;" />
    </div>
    <!-- Body -->
    <div style="padding:40px 32px;border:1px solid #E6F3EF;border-top:none;">
        <h1 style="margin:0 0 8px;font-size:24px;color:#1E2F23;">¡Estás dentro, <?php echo esc_html( explode( ' ', $affiliate->name )[0] ); ?>! 🎉</h1>
        <p style="font-size:16px;color:#444;line-height:1.6;margin:0 0 24px;">
            Hemos revisado tu solicitud y todo está perfecto. Ya formas parte del <strong>Programa de Afiliados de Replanta</strong>.
        </p>

        <!-- Coupon Box -->
        <div style="background:linear-gradient(135deg,#1E2F23 0%,#2a4a33 100%);border-radius:12px;padding:28px;text-align:center;margin:0 0 28px;">
            <p style="margin:0 0 4px;color:#93F1C9;font-size:13px;text-transform:uppercase;letter-spacing:1px;">Tu cupón de afiliado</p>
            <p style="margin:0;font-size:32px;font-weight:800;color:#fff;letter-spacing:2px;"><?php echo esc_html( $affiliate->ref_code ); ?></p>
            <p style="margin:8px 0 0;color:rgba(255,255,255,.6);font-size:13px;">Tu referido ahorra 10% · Tú ganas 20% de comisión</p>
        </div>

        <!-- What now -->
        <h2 style="font-size:18px;margin:0 0 16px;color:#1E2F23;">¿Y ahora qué?</h2>
        <table style="width:100%;border-collapse:collapse;margin:0 0 28px;">
            <tr>
                <td style="padding:12px 16px;border-bottom:1px solid #f0f0f0;vertical-align:top;width:32px;">
                    <span style="display:inline-block;width:28px;height:28px;line-height:28px;text-align:center;background:#93F1C9;color:#1E2F23;font-weight:700;border-radius:50%;font-size:14px;">1</span>
                </td>
                <td style="padding:12px 16px;border-bottom:1px solid #f0f0f0;">
                    <strong>Comparte tu cupón</strong><br>
                    <span style="color:#666;font-size:14px;">Cuando alguien compra usándolo, se le aplica un 10% de descuento automáticamente.</span>
                </td>
            </tr>
            <tr>
                <td style="padding:12px 16px;border-bottom:1px solid #f0f0f0;vertical-align:top;">
                    <span style="display:inline-block;width:28px;height:28px;line-height:28px;text-align:center;background:#93F1C9;color:#1E2F23;font-weight:700;border-radius:50%;font-size:14px;">2</span>
                </td>
                <td style="padding:12px 16px;border-bottom:1px solid #f0f0f0;">
                    <strong>Accede a tu Dashboard</strong><br>
                    <span style="color:#666;font-size:14px;">Desde ahí verás cada venta, el estado de tus comisiones y tus pagos.</span>
                </td>
            </tr>
            <tr>
                <td style="padding:12px 16px;vertical-align:top;">
                    <span style="display:inline-block;width:28px;height:28px;line-height:28px;text-align:center;background:#93F1C9;color:#1E2F23;font-weight:700;border-radius:50%;font-size:14px;">3</span>
                </td>
                <td style="padding:12px 16px;">
                    <strong>Cobra cada mes</strong><br>
                    <span style="color:#666;font-size:14px;">Cuando tu saldo supere 50€, procesamos tu pago por transferencia.</span>
                </td>
            </tr>
        </table>

        <!-- CTA -->
        <div style="text-align:center;margin:0 0 28px;">
            <a href="<?php echo esc_url( home_url( '/afiliados/' ) ); ?>" style="display:inline-block;background:#93F1C9;color:#1E2F23;font-weight:700;font-size:16px;padding:14px 32px;border-radius:8px;text-decoration:none;">Ir a mi Dashboard</a>
        </div>

        <!-- Your affiliate link -->
        <div style="background:#F7FBF9;border:1px solid #E6F3EF;border-radius:8px;padding:20px;margin:0 0 24px;">
            <p style="margin:0 0 8px;font-weight:600;font-size:14px;">Tu enlace de afiliado:</p>
            <code style="font-size:14px;color:#41999F;word-break:break-all;">https://replanta.net/precios/?ref=<?php echo esc_attr( $affiliate->ref_code ); ?></code>
            <p style="margin:8px 0 0;font-size:13px;color:#888;">Cuando alguien visita este enlace, tu cupón se inyecta automáticamente en los botones de compra. El visitante ve los precios, navega con calma y cuando hace clic en "Comprar", el descuento ya está aplicado. Atribución 100% server-side.</p>
        </div>

        <!-- Toolkit -->
        <div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:8px;padding:16px 20px;">
            <p style="margin:0;font-size:14px;"><strong>Consejo:</strong> Usa el <a href="<?php echo esc_url( home_url( '/mediakit/affiliates.html' ) ); ?>" style="color:#41999F;">Toolkit de Afiliados</a> para copiar enlaces con tu cupón ya incluido, mensajes y posts listos para publicar.</p>
        </div>

        <!-- FAQ mini -->
        <div style="margin-top:32px;padding-top:24px;border-top:1px solid #f0f0f0;">
            <h3 style="font-size:15px;margin:0 0 12px;color:#1E2F23;">Preguntas rápidas</h3>
            <p style="font-size:14px;color:#555;margin:0 0 8px;"><strong>¿La comisión es recurrente?</strong><br>La comisión aplica sobre el primer pago del cliente (anual).</p>
            <p style="font-size:14px;color:#555;margin:0 0 8px;"><strong>¿Cuándo cobro?</strong><br>Pagos mensuales por transferencia cuando tu saldo ≥ 50€.</p>
            <p style="font-size:14px;color:#555;margin:0;"><strong>¿Necesito facturar?</strong><br>No. Nosotros emitimos una autofactura con tus datos. Tú solo cobras.</p>
        </div>
    </div>
    <!-- Footer -->
    <div style="background:#F7FBF9;padding:20px;text-align:center;font-size:13px;color:#6B7D76;border-radius:0 0 12px 12px;border:1px solid #E6F3EF;border-top:none;">
        <p style="margin:0 0 4px;">Replanta · <a href="https://replanta.net" style="color:#41999F;text-decoration:none;">replanta.net</a></p>
        <p style="margin:0;font-size:12px;">¿Dudas? Responde directamente a este email.</p>
    </div>
</div>
