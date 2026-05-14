<?php
/**
 * Email Template: Tree Planted
 * 
 * Variables disponibles:
 * - $tree          : objeto con datos del árbol plantado
 * - $settings      : configuración del plugin
 * - $days_until    : días hasta renovación
 * 
 * @package DominiosReseller
 * @since   1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Defaults
$tree         = $tree ?? (object) [];
$settings     = $settings ?? [];
$days_until   = $days_until ?? '';

// Extract data
$client_name     = $tree->client_name ?: 'amigo/a de Replanta';
$species         = $tree->species_name ?: 'un árbol';
$project         = $tree->project_name ?: 'proyecto de reforestación';
$country         = $tree->country ?: '';
$co2             = $tree->co2_lifetime ?: 21.7;
$collect_url     = $tree->collect_url ?: '#';
$certificate_url = $tree->certificate_url ?: '#';
$domain          = $tree->domain ?: '';
$renewal_date    = $tree->next_renewal_date ?? '';

// Format renewal date
$renewal_formatted = '';
if ( $renewal_date ) {
    $renewal_formatted = date_i18n( 'j \d\e F \d\e Y', strtotime( $renewal_date ) );
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🌳 Tu árbol ya está plantado</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        /* Reset */
        body, table, td, p, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; }
        
        /* iOS blue links */
        a[x-apple-data-detectors] { color: inherit !important; text-decoration: none !important; font-size: inherit !important; font-family: inherit !important; font-weight: inherit !important; line-height: inherit !important; }
        
        /* Gmail fix */
        u + #body a { color: inherit; text-decoration: none; font-size: inherit; font-family: inherit; font-weight: inherit; line-height: inherit; }
        
        /* Mobile */
        @media screen and (max-width: 600px) {
            .wrapper { width: 100% !important; padding: 16px !important; }
            .content { padding: 32px 24px !important; }
            .hero-text { font-size: 28px !important; line-height: 1.2 !important; }
            .stat-grid { display: block !important; }
            .stat-cell { display: block !important; width: 100% !important; margin-bottom: 12px !important; }
        }
    </style>
</head>
<body id="body" style="margin: 0; padding: 0; background-color: #f0fdf4; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">

<!-- Preheader (hidden) -->
<div style="display: none; max-height: 0; overflow: hidden; mso-hide: all;">
    Hemos plantado un <?php echo esc_html( $species ); ?> en <?php echo esc_html( $country ); ?> gracias a ti. <?php echo esc_html( $co2 ); ?>kg de CO₂ capturados.
    &nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;
</div>

<!-- Main wrapper -->
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background: linear-gradient(180deg, #f0fdf4 0%, #ecfdf5 100%);">
    <tr>
        <td align="center" style="padding: 40px 16px;">
            
            <!-- Container -->
            <table role="presentation" class="wrapper" border="0" cellpadding="0" cellspacing="0" width="560" style="max-width: 560px; width: 100%;">
                
                <!-- Logo Header -->
                <tr>
                    <td align="center" style="padding-bottom: 32px;">
                        <a href="https://replanta.net" target="_blank" style="text-decoration: none;">
                            <img src="https://replanta.net/wp-content/uploads/2024/04/Replanta-sin-eslogan-1.webp" 
                                 alt="Replanta" width="160" style="display: block; width: 160px; height: auto;">
                        </a>
                    </td>
                </tr>
                
                <!-- Main Card -->
                <tr>
                    <td>
                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" 
                               style="background: #ffffff; border-radius: 20px; box-shadow: 0 8px 40px rgba(0,80,40,0.12); overflow: hidden;">
                            
                            <!-- Hero Section -->
                            <tr>
                                <td style="background: linear-gradient(145deg, #0B1710 0%, #1a3424 100%); padding: 48px 40px; text-align: center;">
                                    <!-- Tree emoji -->
                                    <div style="font-size: 64px; line-height: 1; margin-bottom: 20px;">🌳</div>
                                    
                                    <h1 class="hero-text" style="margin: 0 0 16px; color: #93F1C9; font-size: 32px; font-weight: 700; line-height: 1.15;">
                                        ¡Tu árbol ya está<br>creciendo!
                                    </h1>
                                    
                                    <p style="margin: 0; color: rgba(255,255,255,0.7); font-size: 16px; line-height: 1.5;">
                                        Hola <?php echo esc_html( $client_name ); ?>, gracias a tu hosting con Replanta<br>
                                        hemos plantado un árbol real en tu nombre.
                                    </p>
                                </td>
                            </tr>
                            
                            <!-- Tree Info -->
                            <tr>
                                <td class="content" style="padding: 40px;">
                                    
                                    <!-- Species card -->
                                    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" 
                                           style="background: #f0fdf4; border: 1px solid #d1fae5; border-radius: 12px; margin-bottom: 24px;">
                                        <tr>
                                            <td style="padding: 24px;">
                                                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                                    <tr>
                                                        <td width="60" valign="top">
                                                            <div style="width: 48px; height: 48px; background: #dcfce7; border-radius: 12px; text-align: center; line-height: 48px; font-size: 24px;">
                                                                🌱
                                                            </div>
                                                        </td>
                                                        <td valign="top" style="padding-left: 12px;">
                                                            <p style="margin: 0 0 4px; font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">
                                                                Tu árbol
                                                            </p>
                                                            <p style="margin: 0; font-size: 18px; font-weight: 700; color: #166534;">
                                                                <?php echo esc_html( $species ); ?>
                                                            </p>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                    
                                    <!-- Stats grid -->
                                    <table role="presentation" class="stat-grid" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 24px;">
                                        <tr>
                                            <td class="stat-cell" width="50%" valign="top" style="padding-right: 8px;">
                                                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" 
                                                       style="background: #faf5ff; border: 1px solid #e9d5ff; border-radius: 12px;">
                                                    <tr>
                                                        <td style="padding: 20px; text-align: center;">
                                                            <p style="margin: 0 0 4px; font-size: 11px; color: #7c3aed; text-transform: uppercase; letter-spacing: 0.05em;">
                                                                📍 Ubicación
                                                            </p>
                                                            <p style="margin: 0; font-size: 15px; font-weight: 600; color: #581c87;">
                                                                <?php echo esc_html( $country ?: 'Global' ); ?>
                                                            </p>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                            <td class="stat-cell" width="50%" valign="top" style="padding-left: 8px;">
                                                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" 
                                                       style="background: #f0fdfa; border: 1px solid #99f6e4; border-radius: 12px;">
                                                    <tr>
                                                        <td style="padding: 20px; text-align: center;">
                                                            <p style="margin: 0 0 4px; font-size: 11px; color: #0d9488; text-transform: uppercase; letter-spacing: 0.05em;">
                                                                💨 CO₂ capturado
                                                            </p>
                                                            <p style="margin: 0; font-size: 15px; font-weight: 600; color: #115e59;">
                                                                <?php echo esc_html( number_format( $co2, 1, ',', '.' ) ); ?> kg/año
                                                            </p>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                    
                                    <!-- Project info -->
                                    <p style="margin: 0 0 24px; font-size: 14px; color: #6b7280; line-height: 1.6; text-align: center;">
                                        Tu árbol forma parte del proyecto <strong style="color: #374151;"><?php echo esc_html( $project ); ?></strong>,
                                        verificado y gestionado por Tree-Nation.
                                    </p>
                                    
                                    <!-- CTA Buttons -->
                                    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                        <tr>
                                            <td align="center" style="padding-bottom: 12px;">
                                                <a href="<?php echo esc_url( $collect_url ); ?>" target="_blank" 
                                                   style="display: inline-block; padding: 14px 32px; background: #41999F; color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none; border-radius: 10px; box-shadow: 0 4px 12px rgba(65,153,159,0.3);">
                                                    Ver mi árbol en Tree-Nation →
                                                </a>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td align="center">
                                                <a href="<?php echo esc_url( $certificate_url ); ?>" target="_blank" 
                                                   style="display: inline-block; padding: 12px 28px; background: transparent; color: #41999F; font-size: 14px; font-weight: 600; text-decoration: none; border: 2px solid #41999F; border-radius: 10px;">
                                                    📜 Descargar certificado
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                    
                                </td>
                            </tr>
                            
                            <!-- Impact message -->
                            <tr>
                                <td style="padding: 0 40px 40px;">
                                    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" 
                                           style="background: linear-gradient(135deg, #ecfdf5 0%, #f0fdfa 100%); border-radius: 12px; border: 1px solid #d1fae5;">
                                        <tr>
                                            <td style="padding: 24px; text-align: center;">
                                                <p style="margin: 0 0 8px; font-size: 13px; color: #059669; font-weight: 600;">
                                                    💚 Tu impacto acumulado
                                                </p>
                                                <p style="margin: 0; font-size: 14px; color: #047857; line-height: 1.5;">
                                                    Con cada año de hosting, un nuevo árbol se suma a tu bosque personal.
                                                    Juntos estamos construyendo un internet más verde.
                                                </p>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            
                        </table>
                    </td>
                </tr>
                
                <!-- Footer -->
                <tr>
                    <td style="padding: 32px 20px; text-align: center;">
                        
                        <?php if ( $renewal_formatted ) : ?>
                        <!-- Renewal reminder (subtle) -->
                        <p style="margin: 0 0 16px; font-size: 13px; color: #6b7280;">
                            📅 Tu próxima renovación: <strong style="color: #374151;"><?php echo esc_html( $renewal_formatted ); ?></strong>
                        </p>
                        <?php endif; ?>
                        
                        <!-- Social links -->
                        <p style="margin: 0 0 16px;">
                            <a href="https://replanta.net" target="_blank" style="display: inline-block; margin: 0 8px; text-decoration: none; color: #6b7280; font-size: 13px;">
                                🌐 replanta.net
                            </a>
                            <a href="mailto:hola@replanta.net" style="display: inline-block; margin: 0 8px; text-decoration: none; color: #6b7280; font-size: 13px;">
                                ✉️ hola@replanta.net
                            </a>
                        </p>
                        
                        <p style="margin: 0; font-size: 12px; color: #9ca3af; line-height: 1.5;">
                            Este email se envía automáticamente a los clientes de Replanta<br>
                            que participan en nuestro Forest Program.<br><br>
                            <?php if ( $domain ) : ?>
                            <span style="color: #6b7280;">Dominio: <?php echo esc_html( $domain ); ?></span>
                            <?php endif; ?>
                        </p>
                        
                    </td>
                </tr>
                
            </table>
            
        </td>
    </tr>
</table>

</body>
</html>
