<?php
/**
 * SAPWCC_Alerting — Email alerts and weekly digest for the Vigilante monitor.
 *
 * Sends:
 *   - Immediate HTML alert when new critical issues are detected.
 *   - Weekly HTML digest summarising all monitored sites.
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
     * Send an immediate alert for one or more new critical issues.
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
     * Send the weekly digest covering all managed sites.
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

    private static function render_digest( array $results ): string {
        $rows           = '';
        $total_critical = 0;
        $total_warning  = 0;
        $sites          = SAPWCC_Sites::get_all();

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

        $body =
            '<div style="background:#f6f7f7;border:1px solid #e0e0e0;padding:14px 16px;border-radius:4px;margin-bottom:16px;">'
            . '<p style="margin:0;color:' . $summary_color . ';font-weight:600;">' . esc_html( $summary ) . '</p>'
            . '</div>'
            . '<table style="width:100%;border-collapse:collapse;border:1px solid #e0e0e0;">'
            . '<thead><tr style="background:#f6f7f7;">'
            . '<th style="padding:10px 14px;text-align:left;font-size:13px;font-weight:600;">Sitio</th>'
            . '<th style="padding:10px 14px;text-align:left;font-size:13px;font-weight:600;">Estado</th>'
            . '<th style="padding:10px 14px;text-align:left;font-size:13px;font-weight:600;">Último scan</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table>';

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
            . '<p style="margin:0 0 6px;color:#555;font-size:11px;text-transform:uppercase;letter-spacing:.5px;">🤖 Análisis IA</p>'
            . '<p style="margin:0 0 8px;">' . esc_html( $ai['explanation'] ) . '</p>'
            . ( $steps_html ? '<ol style="margin:0 0 8px;padding-left:18px;">' . $steps_html . '</ol>' : '' )
            . ( $ai['prevention'] ? '<p style="margin:0;color:#666;font-size:12px;">💡 ' . esc_html( $ai['prevention'] ) . '</p>' : '' )
            . '</div>';
    }

    // ── Email shell ──────────────────────────────────────────────────────────

    private static function email_wrapper( string $subtitle, string $body_html, string $cta_url, string $cta_text, string $cta_color ): string {
        return '<!DOCTYPE html><html lang="es"><body style="margin:0;padding:20px;font-family:Arial,sans-serif;background:#f0f0f1;">'
            . '<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:6px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.1);">'
            . '<div style="background:#1e1e1e;padding:18px 22px;">'
            . '<p style="margin:0;font-size:20px;font-weight:700;color:#fff;">🛡️ SAP Woo Vigilante</p>'
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
