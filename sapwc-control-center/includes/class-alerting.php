<?php
/**
 * SAPWCC_Alerting — Email alerts and weekly digest for the Vigilante monitor.
 *
 * Sends:
 *   - Immediate HTML alert (admin) when new critical admin issues are detected.
 *   - Immediate HTML alert (SAP user) for SAP-side critical issues.
 *   - Weekly HTML digest summarising all monitored sites + ROI.
 *
 * Uses wp_mail with HTML content type. Falls back to admin_email when no
 * explicit alert address is configured.
 *
 * @package SAPWCC
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SAPWCC_Alerting {

    // ── Settings helpers ─────────────────────────────────────────────────────

    public static function get_alert_email(): string {
        return get_option( 'sapwcc_alert_email', get_option( 'admin_email', '' ) );
    }

    // ── Public API ───────────────────────────────────────────────────────────

    /**
     * Send an immediate alert for admin-audience critical issues.
     */
    public static function send_critical_alert( string $site_label, string $site_key, array $issues ): bool {
        $to = self::get_alert_email();
        if ( empty( $to ) || empty( $issues ) ) {
            return false;
        }

        $n       = count( $issues );
        $subject = sprintf( '[SAP Woo Vigilante] %d problema(s) crítico(s) en %s', $n, $site_label );
        $body    = self::render_critical( $site_label, $issues );

        return wp_mail( $to, $subject, $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
    }

    /**
     * Send a business-friendly alert for SAP-user-audience critical issues.
     *
     * @param string $site_label  Human-readable site name.
     * @param string $to          SAP contact email (from sapwc_sap_contact_email option).
     * @param array  $issues      Array of sap_user issues.
     * @param string $site_url    Base URL of the WooCommerce site.
     */
    public static function send_sap_user_alert( string $site_label, string $to, array $issues, string $site_url ): bool {
        if ( empty( $to ) || empty( $issues ) ) {
            return false;
        }

        $n       = count( $issues );
        $subject = sprintf(
            '[%s] Acción requerida en SAP: %d incidencia%s',
            $site_label,
            $n,
            $n > 1 ? 's' : ''
        );
        $body = self::render_sap_user( $site_label, $issues, $site_url );

        return wp_mail( $to, $subject, $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
    }

    /**
     * Send the weekly digest covering all managed sites + ROI report.
     */
    public static function send_weekly_digest(): bool {
        $to = self::get_alert_email();
        if ( empty( $to ) ) {
            return false;
        }

        $results = SAPWCC_Vigilante::get_all_results();
        if ( empty( $results ) ) {
            return false;
        }

        $subject = sprintf( '[SAP Woo Vigilante] Informe semanal — %s', date_i18n( 'j M Y' ) );
        $body    = self::render_digest( $results );

        return wp_mail( $to, $subject, $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
    }

    // ── Email renderers ──────────────────────────────────────────────────────

    private static function render_critical( string $site_label, array $issues ): string {
        $issues_html = '';
        foreach ( $issues as $issue ) {
            $color    = $issue['severity'] === SAPWCC_Vigilante::SEV_CRITICAL ? '#d63638' : '#dba617';
            $ai_block = self::render_ai_block( $issue, $site_label );
            $since    = ! empty( $issue['since'] )
                ? '<br><span style="color:#999;font-size:12px;">Detectado: ' . esc_html( $issue['since'] ) . '</span>'
                : '';

            $issues_html .=
                '<div style="border-left:4px solid ' . $color . ';padding:12px 16px;margin:12px 0;background:#fff;border-radius:0 4px 4px 0;">'
                . '<strong style="font-size:14px;">' . esc_html( $issue['title'] ) . '</strong><br>'
                . '<span style="color:#555;font-size:13px;">' . esc_html( $issue['detail'] ) . '</span>'
                . $since
                . $ai_block
                . '</div>';
        }

        $admin_url = admin_url( 'admin.php?page=sapwcc&tab=vigilante' );

        return self::email_wrapper(
            'Alerta crítica — ' . esc_html( $site_label ),
            '<div style="background:#fff3cd;border:1px solid #ffc107;padding:12px 16px;border-radius:4px;margin-bottom:4px;">'
            . '<strong>' . count( $issues ) . ' problema(s) nuevo(s) detectado(s)</strong> que requieren atención inmediata.'
            . '</div>'
            . $issues_html,
            $admin_url,
            'Ver Dashboard Vigilante',
            '#d63638'
        );
    }

    /**
     * Render a business-friendly email for SAP-user tasks.
     */
    private static function render_sap_user( string $site_label, array $issues, string $site_url ): string {
        $issues_html = '';

        foreach ( $issues as $issue ) {
            $ai_block = self::render_ai_block_sap_user( $issue, $site_label );
            $steps    = class_exists( 'SAPWC_Sap_Tasks' ) ? SAPWC_Sap_Tasks::get_steps( $issue['type'] ) : [];
            $steps_html = '';
            if ( $steps ) {
                $steps_html .= '<ol style="margin:8px 0 0;padding-left:18px;">';
                foreach ( $steps as $step ) {
                    $steps_html .= '<li style="margin-bottom:4px;font-size:13px;">' . esc_html( $step ) . '</li>';
                }
                $steps_html .= '</ol>';
            }

            $since = ! empty( $issue['since'] )
                ? '<br><span style="color:#999;font-size:12px;">Detectado: ' . esc_html( $issue['since'] ) . '</span>'
                : '';

            $issues_html .=
                '<div style="border-left:4px solid #1a73e8;padding:12px 16px;margin:12px 0;background:#f8f9ff;border-radius:0 4px 4px 0;">'
                . '<strong style="font-size:14px;">' . esc_html( $issue['title'] ) . '</strong><br>'
                . '<span style="color:#555;font-size:13px;">' . esc_html( $issue['detail'] ) . '</span>'
                . $since
                . ( $steps_html
                    ? '<div style="margin-top:10px;"><p style="margin:0 0 4px;font-size:12px;font-weight:600;color:#50575e;text-transform:uppercase;letter-spacing:.4px;">Qué hacer en SAP:</p>' . $steps_html . '</div>'
                    : '' )
                . $ai_block
                . '</div>';
        }

        $tasks_url = rtrim( $site_url, '/' ) . '/wp-admin/admin.php?page=sapwc-sap-alerts';

        return self::email_wrapper(
            esc_html( $site_label ) . ' — Acción requerida en SAP',
            '<div style="background:#e8f0fe;border:1px solid #1a73e8;padding:12px 16px;border-radius:4px;margin-bottom:4px;">'
            . '<strong>Hay ' . count( $issues ) . ' incidencia(s) que requieren una acción manual en SAP Business One.</strong><br>'
            . '<span style="font-size:13px;color:#555;">Una vez resuelta en SAP, márcala como resuelta en tu panel.</span>'
            . '</div>'
            . $issues_html,
            $tasks_url,
            'Ver y gestionar tareas SAP',
            '#1a73e8'
        );
    }

    private static function render_digest( array $results ): string {
        $rows           = '';
        $total_critical = 0;
        $total_warning  = 0;
        $sites          = SAPWCC_Sites::get_all();
        $all_roi        = SAPWCC_Vigilante::get_all_roi();

        $roi_totals = [
            'incidencias_detectadas' => 0,
            'pedidos_recuperados'    => 0,
            'auto_resueltas'         => 0,
        ];

        foreach ( $results as $site_key => $result ) {
            $issues   = $result['issues'] ?? [];
            $critical = SAPWCC_Vigilante::count_by_severity( $issues, SAPWCC_Vigilante::SEV_CRITICAL );
            $warning  = SAPWCC_Vigilante::count_by_severity( $issues, SAPWCC_Vigilante::SEV_WARNING );
            $total_critical += $critical;
            $total_warning  += $warning;

            $label   = $sites[ $site_key ]['label'] ?? $site_key;
            $scanned = $result['scanned_at'] ?? '—';

            if ( $critical > 0 ) {
                $status_color = '#d63638';
                $status_text  = $critical . ' crítico' . ( $critical > 1 ? 's' : '' );
            } elseif ( $warning > 0 ) {
                $status_color = '#dba617';
                $status_text  = $warning . ' aviso' . ( $warning > 1 ? 's' : '' );
            } else {
                $status_color = '#00a32a';
                $status_text  = '✓ Sin incidencias';
            }

            // ROI for this site
            $roi = $all_roi[ $site_key ] ?? [];
            foreach ( $roi_totals as $k => $v ) {
                $roi_totals[ $k ] += (int) ( $roi[ $k ] ?? 0 );
            }

            $rows .=
                '<tr style="border-bottom:1px solid #f0f0f1;">'
                . '<td style="padding:10px 14px;">' . esc_html( $label ) . '</td>'
                . '<td style="padding:10px 14px;color:' . $status_color . ';font-weight:600;">' . esc_html( $status_text ) . '</td>'
                . '<td style="padding:10px 14px;color:#999;font-size:12px;">' . esc_html( $scanned ) . '</td>'
                . '</tr>';
        }

        if ( $total_critical === 0 && $total_warning === 0 ) {
            $summary       = 'Todos los sistemas funcionando con normalidad esta semana.';
            $summary_color = '#00a32a';
        } else {
            $summary       = "Se detectaron {$total_critical} problema(s) crítico(s) y {$total_warning} aviso(s) durante la semana.";
            $summary_color = $total_critical > 0 ? '#d63638' : '#dba617';
        }

        // ROI block
        $roi_html = '';
        if ( array_sum( $roi_totals ) > 0 ) {
            $roi_html =
                '<h3 style="font-size:14px;margin:24px 0 8px;">ROI esta semana</h3>'
                . '<table style="width:100%;border-collapse:collapse;border:1px solid #e0e0e0;margin-bottom:8px;">'
                . '<thead><tr style="background:#f6f7f7;">'
                . '<th style="padding:8px 14px;text-align:left;font-size:13px;">Métrica</th>'
                . '<th style="padding:8px 14px;text-align:right;font-size:13px;">Total</th>'
                . '</tr></thead><tbody>'
                . '<tr style="border-bottom:1px solid #f0f0f1;"><td style="padding:8px 14px;">Incidencias detectadas</td><td style="padding:8px 14px;text-align:right;">' . $roi_totals['incidencias_detectadas'] . '</td></tr>'
                . '<tr style="border-bottom:1px solid #f0f0f1;"><td style="padding:8px 14px;">Pedidos recuperados</td><td style="padding:8px 14px;text-align:right;color:#00a32a;font-weight:600;">' . $roi_totals['pedidos_recuperados'] . '</td></tr>'
                . '<tr><td style="padding:8px 14px;">Auto-resueltas (cron)</td><td style="padding:8px 14px;text-align:right;color:#1a73e8;font-weight:600;">' . $roi_totals['auto_resueltas'] . '</td></tr>'
                . '</tbody></table>';
        }

        $body =
            '<div style="background:#f6f7f7;border:1px solid #e0e0e0;padding:14px 16px;border-radius:4px;margin-bottom:16px;">'
            . '<p style="margin:0;color:' . $summary_color . ';font-weight:600;">' . esc_html( $summary ) . '</p>'
            . '</div>'
            . '<table style="width:100%;border-collapse:collapse;border:1px solid #e0e0e0;">'
            . '<thead><tr style="background:#f6f7f7;">'
            . '<th style="padding:10px 14px;text-align:left;font-size:13px;font-weight:600;">Sitio</th>'
            . '<th style="padding:10px 14px;text-align:left;font-size:13px;font-weight:600;">Estado</th>'
            . '<th style="padding:10px 14px;text-align:left;font-size:13px;font-weight:600;">Último scan</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table>'
            . $roi_html;

        return self::email_wrapper(
            'Informe semanal — ' . date_i18n( 'j M Y' ),
            $body,
            admin_url( 'admin.php?page=sapwcc&tab=vigilante' ),
            'Ver Dashboard Vigilante',
            '#1e1e1e'
        );
    }

    private static function render_ai_block( array $issue, string $site_label = '' ): string {
        if ( ! SAPWCC_AI::is_configured() ) {
            return '';
        }

        $ai = SAPWCC_AI::explain(
            $issue['type'],
            $issue['context'] ?? [],
            $site_label,
            $issue['id']
        );

        if ( ! $ai ) {
            return '';
        }

        $steps_html = '';
        foreach ( $ai['steps'] as $step ) {
            $steps_html .= '<li style="margin-bottom:4px;">' . esc_html( $step ) . '</li>';
        }

        return '<div style="background:#f0f6fc;border-radius:4px;padding:10px 14px;margin-top:10px;font-size:13px;">'
            . '<p style="margin:0 0 6px;color:#555;font-size:11px;text-transform:uppercase;letter-spacing:.5px;">Analisis IA</p>'
            . '<p style="margin:0 0 8px;">' . esc_html( $ai['explanation'] ) . '</p>'
            . ( $steps_html ? '<ol style="margin:0 0 8px;padding-left:18px;">' . $steps_html . '</ol>' : '' )
            . ( $ai['prevention'] ? '<p style="margin:0;color:#666;font-size:12px;">' . esc_html( $ai['prevention'] ) . '</p>' : '' )
            . '</div>';
    }

    /**
     * AI block for SAP-user emails — uses simpler, non-technical language.
     */
    private static function render_ai_block_sap_user( array $issue, string $site_label = '' ): string {
        if ( ! SAPWCC_AI::is_configured() ) {
            return '';
        }

        $ai = SAPWCC_AI::explain(
            $issue['type'],
            $issue['context'] ?? [],
            $site_label,
            $issue['id'] . '_sap_user'
        );

        if ( ! $ai || empty( $ai['explanation'] ) ) {
            return '';
        }

        return '<div style="background:#f0f6fc;border-radius:4px;padding:10px 14px;margin-top:10px;font-size:13px;">'
            . '<p style="margin:0 0 6px;color:#555;font-size:11px;text-transform:uppercase;letter-spacing:.5px;">Por que ocurre</p>'
            . '<p style="margin:0;">' . esc_html( $ai['explanation'] ) . '</p>'
            . '</div>';
    }

    // ── Email shell ──────────────────────────────────────────────────────────

    private static function email_wrapper( string $subtitle, string $body_html, string $cta_url, string $cta_text, string $cta_color ): string {
        return '<!DOCTYPE html><html lang="es"><body style="margin:0;padding:20px;font-family:Arial,sans-serif;background:#f0f0f1;">'
            . '<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:6px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.1);">'
            . '<div style="background:#1e1e1e;padding:18px 22px;">'
            . '<p style="margin:0;font-size:20px;font-weight:700;color:#fff;">SAP Woo Vigilante</p>'
            . '<p style="margin:4px 0 0;font-size:13px;color:#aaa;">' . esc_html( $subtitle ) . '</p>'
            . '</div>'
            . '<div style="padding:20px 22px;">'
            . $body_html
            . '<div style="margin-top:24px;text-align:center;">'
            . '<a href="' . esc_url( $cta_url ) . '" style="display:inline-block;background:' . $cta_color . ';color:#fff;padding:11px 28px;border-radius:4px;text-decoration:none;font-weight:700;font-size:14px;">' . esc_html( $cta_text ) . '</a>'
            . '</div>'
            . '</div>'
            . '<div style="background:#f6f7f7;padding:10px 22px;text-align:center;">'
            . '<p style="margin:0;color:#999;font-size:11px;">SAP Woo Control Center · Vigilante 24/7</p>'
            . '</div>'
            . '</div></body></html>';
    }
}
