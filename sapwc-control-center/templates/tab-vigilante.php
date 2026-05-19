<?php
/**
 * Tab Vigilante — SAP Woo Control Center dashboard.
 *
 * Loaded by page-dashboard.php when $active === 'vigilante'.
 *
 * @package SAPWCC
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$vig_results   = SAPWCC_Vigilante::get_all_results();
$sites         = SAPWCC_Sites::get_all();
$ai_configured = SAPWCC_AI::is_configured();
$ai_provider   = SAPWCC_AI::active_provider();

// Overview counts
$total_critical = 0;
$total_warning  = 0;
$sites_ok       = 0;

foreach ( $vig_results as $r ) {
    $c = SAPWCC_Vigilante::count_by_severity( $r['issues'] ?? [], SAPWCC_Vigilante::SEV_CRITICAL );
    $w = SAPWCC_Vigilante::count_by_severity( $r['issues'] ?? [], SAPWCC_Vigilante::SEV_WARNING );
    $total_critical += $c;
    $total_warning  += $w;
    if ( $c === 0 && $w === 0 ) $sites_ok++;
}

$sites_with_issues = count( $vig_results ) - $sites_ok;
?>

<div id="tab-vigilante" class="sapwcc-tab-content" <?php echo $active !== 'vigilante' ? 'style="display:none"' : ''; ?>>

    <!-- ── Header bar ──────────────────────────────────────────────────── -->
    <div class="sapwcc-vig-header postbox">
        <div class="inside sapwcc-vig-header-inner">
            <div class="sapwcc-vig-title">
                <span class="dashicons dashicons-shield"></span>
                <div>
                    <h2>Vigilante <span class="sapwcc-vig-badge-new">NUEVO</span></h2>
                    <p>Monitorización proactiva del sync SAP ↔ WooCommerce con análisis IA</p>
                </div>
            </div>
            <div class="sapwcc-vig-status-bar">
                <?php if ( $ai_configured ) : ?>
                    <span class="sapwcc-vig-pill sapwcc-vig-pill--ok">
                        <span class="dashicons dashicons-yes-alt"></span>
                        IA activa (<?php echo esc_html( $ai_provider === 'claude' ? 'Claude' : 'OpenAI' ); ?>)
                    </span>
                <?php else : ?>
                    <span class="sapwcc-vig-pill sapwcc-vig-pill--muted">
                        <span class="dashicons dashicons-info-outline"></span>
                        IA no configurada
                    </span>
                <?php endif; ?>
                <button id="sapwcc-vig-scan-all" class="button button-primary">
                    <span class="dashicons dashicons-update"></span> Escanear ahora
                </button>
            </div>
        </div>
    </div>

    <!-- ── Overview counters ───────────────────────────────────────────── -->
    <div class="sapwcc-vig-overview">
        <div class="sapwcc-vig-counter <?php echo $total_critical > 0 ? 'sapwcc-vig-counter--critical' : 'sapwcc-vig-counter--ok'; ?>">
            <span class="sapwcc-vig-counter-num"><?php echo $total_critical; ?></span>
            <span class="sapwcc-vig-counter-label">Críticos</span>
        </div>
        <div class="sapwcc-vig-counter <?php echo $total_warning > 0 ? 'sapwcc-vig-counter--warning' : 'sapwcc-vig-counter--ok'; ?>">
            <span class="sapwcc-vig-counter-num"><?php echo $total_warning; ?></span>
            <span class="sapwcc-vig-counter-label">Avisos</span>
        </div>
        <div class="sapwcc-vig-counter sapwcc-vig-counter--ok">
            <span class="sapwcc-vig-counter-num"><?php echo $sites_ok; ?></span>
            <span class="sapwcc-vig-counter-label">Sin incidencias</span>
        </div>
        <div class="sapwcc-vig-counter sapwcc-vig-counter--neutral">
            <span class="sapwcc-vig-counter-num"><?php echo count( $sites ); ?></span>
            <span class="sapwcc-vig-counter-label">Sitios vigilados</span>
        </div>
    </div>

    <?php if ( empty( $sites ) ) : ?>

    <!-- ── Empty state ─────────────────────────────────────────────────── -->
    <div class="sapwcc-empty-state">
        <span class="dashicons dashicons-shield" style="font-size:48px;width:48px;height:48px;color:#c3c4c7;"></span>
        <p><strong>No hay sitios registrados.</strong></p>
        <p>Añade sitios en la pestaña <em>Sitios</em> para que el Vigilante pueda monitorizarlos.</p>
    </div>

    <?php else : ?>

    <!-- ── Sites ──────────────────────────────────────────────────────── -->
    <?php
    // Merge all registered sites with any scan results; sort: critical > warning > ok > error > unscanned.
    $sorted_keys = array_values( array_unique( array_merge( array_keys( $sites ), array_keys( $vig_results ) ) ) );
    $sorted_keys = array_filter( $sorted_keys, fn( $k ) => isset( $sites[ $k ] ) );
    usort( $sorted_keys, function ( $a, $b ) use ( $vig_results ) {
        $priority = function ( $key ) use ( $vig_results ) {
            if ( ! isset( $vig_results[ $key ] ) )              return 4;
            if ( ! empty( $vig_results[ $key ]['scan_error'] ) ) return 3;
            $c = SAPWCC_Vigilante::count_by_severity( $vig_results[ $key ]['issues'] ?? [], SAPWCC_Vigilante::SEV_CRITICAL );
            $w = SAPWCC_Vigilante::count_by_severity( $vig_results[ $key ]['issues'] ?? [], SAPWCC_Vigilante::SEV_WARNING );
            if ( $c > 0 ) return 0;
            if ( $w > 0 ) return 1;
            return 2;
        };
        $pa = $priority( $a );
        $pb = $priority( $b );
        if ( $pa !== $pb ) return $pa - $pb;
        $ca = SAPWCC_Vigilante::count_by_severity( $vig_results[$a]['issues'] ?? [], SAPWCC_Vigilante::SEV_CRITICAL );
        $cb = SAPWCC_Vigilante::count_by_severity( $vig_results[$b]['issues'] ?? [], SAPWCC_Vigilante::SEV_CRITICAL );
        return $cb - $ca;
    } );
    ?>

    <?php foreach ( $sorted_keys as $site_key ) :
        $result     = $vig_results[ $site_key ] ?? null;
        $issues     = $result['issues'] ?? [];
        $site_label = $sites[ $site_key ]['label'] ?? $site_key;
        $scanned_at = $result['scanned_at'] ?? null;
        $critical   = SAPWCC_Vigilante::count_by_severity( $issues, SAPWCC_Vigilante::SEV_CRITICAL );
        $warning    = SAPWCC_Vigilante::count_by_severity( $issues, SAPWCC_Vigilante::SEV_WARNING );

        if ( $result === null ) {
            $card_class  = 'sapwcc-vig-card--neutral';
            $badge_class = 'sapwcc-vig-badge--neutral';
            $badge_text  = 'Sin escanear';
        } elseif ( ! empty( $result['scan_error'] ) ) {
            $card_class  = 'sapwcc-vig-card--error';
            $badge_class = 'sapwcc-vig-badge--error';
            $badge_text  = 'Error de conexión';
        } elseif ( $critical > 0 ) {
            $card_class  = 'sapwcc-vig-card--critical';
            $badge_class = 'sapwcc-vig-badge--critical';
            $badge_text  = $critical . ' CRÍTICO' . ( $critical > 1 ? 'S' : '' );
        } elseif ( $warning > 0 ) {
            $card_class  = 'sapwcc-vig-card--warning';
            $badge_class = 'sapwcc-vig-badge--warning';
            $badge_text  = $warning . ' AVISO' . ( $warning > 1 ? 'S' : '' );
        } else {
            $card_class  = 'sapwcc-vig-card--ok';
            $badge_class = 'sapwcc-vig-badge--ok';
            $badge_text  = '✓ SIN INCIDENCIAS';
        }
    ?>
    <div class="postbox sapwcc-vig-card <?php echo esc_attr( $card_class ); ?>" data-site-key="<?php echo esc_attr( $site_key ); ?>">
        <div class="sapwcc-vig-card-header">
            <div class="sapwcc-vig-card-title">
                <span class="sapwcc-status-dot"></span>
                <strong><?php echo esc_html( $site_label ); ?></strong>
            </div>
            <div class="sapwcc-vig-card-meta">
                <?php if ( $scanned_at ) : ?>
                    <span class="sapwcc-muted" style="font-size:12px;">
                        Escaneado: <?php echo esc_html( $scanned_at ); ?>
                    </span>
                <?php endif; ?>
                <span class="sapwcc-vig-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $badge_text ); ?></span>
                <button class="button sapwcc-vig-scan-single" data-site-key="<?php echo esc_attr( $site_key ); ?>" title="Escanear este sitio">
                    <span class="dashicons dashicons-update"></span>
                </button>
            </div>
        </div>

        <?php if ( $result === null ) : ?>
        <div class="sapwcc-vig-card-body sapwcc-vig-all-ok">
            <span class="dashicons dashicons-clock" style="color:#999;"></span>
            <span>Pendiente de primer escaneo. Haz clic en <em>Escanear ahora</em> o espera al ciclo automático (cada hora).</span>
        </div>
        <?php elseif ( ! empty( $result['scan_error'] ) ) : ?>
        <div class="sapwcc-vig-card-body sapwcc-vig-all-ok">
            <span class="dashicons dashicons-dismiss" style="color:#d63638;"></span>
            <span>No se pudo conectar: <code><?php echo esc_html( $result['scan_error'] ); ?></code></span>
        </div>
        <?php elseif ( ! empty( $issues ) ) : ?>
        <div class="sapwcc-vig-card-body">
            <?php foreach ( $issues as $issue ) :
                $sev_class = 'sapwcc-vig-issue--' . $issue['severity'];
                $icon      = $issue['severity'] === SAPWCC_Vigilante::SEV_CRITICAL ? 'warning' : 'info';
            ?>
            <div class="sapwcc-vig-issue <?php echo esc_attr( $sev_class ); ?>" data-issue-id="<?php echo esc_attr( $issue['id'] ); ?>" data-issue-type="<?php echo esc_attr( $issue['type'] ); ?>" data-site-label="<?php echo esc_attr( $site_label ); ?>" data-context="<?php echo esc_attr( wp_json_encode( $issue['context'] ?? [] ) ); ?>">
                <div class="sapwcc-vig-issue-header">
                    <span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>"></span>
                    <div class="sapwcc-vig-issue-text">
                        <strong><?php echo esc_html( $issue['title'] ); ?></strong>
                        <span class="sapwcc-muted"><?php echo esc_html( $issue['detail'] ); ?></span>
                        <?php if ( ! empty( $issue['since'] ) ) : ?>
                            <span class="sapwcc-vig-since">Desde: <?php echo esc_html( $issue['since'] ); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ( $ai_configured ) : ?>
                    <button class="button button-small sapwcc-vig-ai-btn" title="Análisis IA">
                        <span class="dashicons dashicons-superhero-alt"></span> IA
                    </button>
                    <?php endif; ?>
                </div>
                <?php if ( $ai_configured ) : ?>
                <div class="sapwcc-vig-ai-panel" style="display:none;"></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else : ?>
        <div class="sapwcc-vig-card-body sapwcc-vig-all-ok">
            <span class="dashicons dashicons-yes-alt" style="color:#00a32a;"></span>
            <span>Sin incidencias en el último escaneo</span>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php endif; // empty sites ?>

    <!-- ── Config section ─────────────────────────────────────────────── -->
    <div class="postbox sapwcc-vig-config">
        <h3 class="hndle">
            <span class="dashicons dashicons-admin-settings"></span>
            Configuración Vigilante
        </h3>
        <div class="inside">
            <form id="sapwcc-vig-config-form">
                <table class="form-table" style="max-width:700px;">
                    <tr>
                        <th><label for="vig-alert-email">Email de alertas</label></th>
                        <td>
                            <input type="email" id="vig-alert-email" name="alert_email"
                                   value="<?php echo esc_attr( SAPWCC_Alerting::get_alert_email() ); ?>"
                                   class="regular-text" placeholder="admin@tudominio.com" />
                            <p class="description">Recibe alertas inmediatas cuando se detecten problemas críticos.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="vig-claude-key">Claude API Key</label></th>
                        <td>
                            <input type="password" id="vig-claude-key" name="claude_key"
                                   value="<?php echo esc_attr( SAPWCC_AI::get_claude_key() ? str_repeat( '•', 20 ) : '' ); ?>"
                                   class="regular-text" placeholder="sk-ant-api03-..." autocomplete="new-password" />
                            <p class="description">
                                Proveedor principal (Claude Haiku — muy económico).
                                <?php if ( $ai_provider === 'claude' ) echo '<strong style="color:#00a32a;">✓ Activo</strong>'; ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="vig-openai-key">OpenAI API Key <span class="sapwcc-muted">(fallback)</span></label></th>
                        <td>
                            <input type="password" id="vig-openai-key" name="openai_key"
                                   value="<?php echo esc_attr( SAPWCC_AI::get_openai_key() ? str_repeat( '•', 20 ) : '' ); ?>"
                                   class="regular-text" placeholder="sk-..." autocomplete="new-password" />
                            <p class="description">
                                Se usa si Claude no está configurado o falla.
                                <?php if ( $ai_provider === 'openai' ) echo '<strong style="color:#00a32a;">✓ Activo</strong>'; ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Digest semanal</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="digest_enabled" value="1"
                                       <?php checked( get_option( 'sapwcc_vig_digest_enabled', '1' ), '1' ); ?> />
                                Enviar informe semanal automático cada lunes a las 08:00
                            </label>
                        </td>
                    </tr>
                </table>
                <div style="display:flex;gap:10px;align-items:center;margin-top:8px;">
                    <button type="submit" class="button button-primary">Guardar configuración</button>
                    <button type="button" id="sapwcc-vig-test-digest" class="button">
                        <span class="dashicons dashicons-email-alt"></span> Enviar digest de prueba
                    </button>
                    <span id="sapwcc-vig-config-msg" style="color:#00a32a;display:none;"></span>
                </div>
            </form>
        </div>
    </div>

</div><!-- #tab-vigilante -->
