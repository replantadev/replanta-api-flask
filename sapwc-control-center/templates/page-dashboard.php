<?php
/**
 * Dashboard template — SAP Woo Control Center.
 *
 * Tabs: Sitios | Feature Flags | Acciones | Config
 *
 * @package SAPWCC
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$sites          = SAPWCC_Sites::get_all();
$flags          = SAPWCC_Flags::read();
$labels         = SAPWCC_Flags::get_labels();
$recs           = SAPWCC_Sites::generate_recommendations();
$active         = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'sites';
$vig_criticals  = class_exists( 'SAPWCC_Vigilante' ) ? SAPWCC_Vigilante::total_critical_across_sites() : 0;
?>
<div class="wrap sapwcc-wrap">
    <h1 class="sapwcc-title">
        <span class="dashicons dashicons-admin-multisite"></span>
        SAP Woo Control Center
        <span class="sapwcc-version">v<?php echo esc_html( SAPWCC_VERSION ); ?></span>
    </h1>

    <nav class="nav-tab-wrapper sapwcc-tabs">
        <a class="nav-tab <?php echo $active === 'sites' ? 'nav-tab-active' : ''; ?>"
           href="#sites" data-tab="sites">
            Sitios <span class="sapwcc-badge"><?php echo count( $sites ); ?></span>
        </a>
        <a class="nav-tab <?php echo $active === 'flags' ? 'nav-tab-active' : ''; ?>"
           href="#flags" data-tab="flags">Feature Flags</a>
        <a class="nav-tab <?php echo $active === 'actions' ? 'nav-tab-active' : ''; ?>"
           href="#actions" data-tab="actions">
            Acciones
            <?php if ( ! empty( $recs ) ) : ?>
                <span class="sapwcc-badge sapwcc-badge--alert"><?php echo count( $recs ); ?></span>
            <?php endif; ?>
        </a>
        <a class="nav-tab <?php echo $active === 'config' ? 'nav-tab-active' : ''; ?>"
           href="#config" data-tab="config">Config</a>
        <a class="nav-tab <?php echo $active === 'vigilante' ? 'nav-tab-active' : ''; ?>"
           href="#vigilante" data-tab="vigilante">
            <span class="dashicons dashicons-shield" style="font-size:14px;width:14px;height:14px;vertical-align:text-bottom;margin-right:3px;"></span>
            Vigilante
            <?php if ( $vig_criticals > 0 ) : ?>
                <span class="sapwcc-badge sapwcc-badge--alert"><?php echo $vig_criticals; ?></span>
            <?php endif; ?>
        </a>
    </nav>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!--  TAB: SITIOS                                                       -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <div id="tab-sites" class="sapwcc-tab-content" <?php echo $active !== 'sites' ? 'style="display:none"' : ''; ?>>

        <!-- Add site form -->
        <div class="sapwcc-add-form postbox">
            <div class="inside">
                <h3>Añadir sitio</h3>
                <form id="sapwcc-add-site-form" class="sapwcc-inline-form">
                    <input type="text" name="label" placeholder="Nombre (ej: Cliente Demo)" required class="regular-text" />
                    <input type="url"  name="url"   placeholder="https://dominio.com" required class="regular-text" />
                    <input type="text" name="secret" placeholder="API Secret (X-SAPWC-Secret)" class="regular-text" />
                    <button type="submit" class="button button-primary">Añadir</button>
                </form>
            </div>
        </div>

        <?php if ( empty( $sites ) ) : ?>
            <div class="sapwcc-empty-state">
                <span class="dashicons dashicons-admin-site-alt3"></span>
                <p>No hay sitios registrados. Añade uno arriba para empezar.</p>
            </div>
        <?php else : ?>
            <div class="sapwcc-toolbar">
                <button id="sapwcc-check-all" class="button">
                    <span class="dashicons dashicons-update"></span> Check All
                </button>
            </div>

            <div class="sapwcc-sites-grid">
                <?php foreach ( $sites as $key => $site ) :
                    $health    = SAPWCC_Sites::get_cached_health( $key );
                    $status    = $health['status'] ?? 'unknown';
                    $status_cl = 'sapwcc-status--' . sanitize_html_class( $status );
                    $site_id   = $site['site_id'] ?: '—';
                    
                    // Check for transient warnings (errors that auto-resolved).
                    $tw = SAPWCC_Sites::get_transient_warnings( $key, 24 );
                    $tw_count = $tw['count'] ?? 0;
                ?>
                <div class="sapwcc-site-card postbox <?php echo $status_cl; ?>" data-site-key="<?php echo esc_attr( $key ); ?>">
                    <div class="sapwcc-card-header">
                        <span class="sapwcc-status-dot"></span>
                        <h3>
                            <?php echo esc_html( $site['label'] ); ?>
                            <?php if ( $tw_count > 0 ) : ?>
                                <span class="sapwcc-tw-badge" title="<?php echo esc_attr( "{$tw_count} warnings auto-resueltos en 24h" ); ?>">
                                    <span class="dashicons dashicons-info"></span>
                                    <?php echo $tw_count; ?>
                                </span>
                            <?php endif; ?>
                        </h3>
                        <span class="sapwcc-status-label"><?php echo esc_html( strtoupper( $status ) ); ?></span>
                    </div>

                    <div class="sapwcc-card-body">
                        <div class="sapwcc-card-row">
                            <span class="sapwcc-card-label">URL</span>
                            <a href="<?php echo esc_url( $site['url'] ); ?>/wp-admin/" target="_blank" rel="noopener">
                                <?php echo esc_html( wp_parse_url( $site['url'], PHP_URL_HOST ) ); ?>
                                <span class="dashicons dashicons-external"></span>
                            </a>
                        </div>
                        <div class="sapwcc-card-row">
                            <span class="sapwcc-card-label">Site ID</span>
                            <code><?php echo esc_html( $site_id ); ?></code>
                        </div>

                        <?php if ( ! empty( $site['site_id'] ) ) : ?>
                        <div class="sapwcc-card-row sapwcc-plan-assign-row">
                            <span class="sapwcc-card-label">Asignar Plan</span>
                            <?php
                                $flags = class_exists( 'SAPWCC_Flags' ) ? SAPWCC_Flags::read() : [];
                                $assigned_plan = $flags['sites'][ $site['site_id'] ]['plan'] ?? '';
                            ?>
                            <select class="sapwcc-plan-assign" data-site-key="<?php echo esc_attr( $key ); ?>" data-site-id="<?php echo esc_attr( $site['site_id'] ); ?>">
                                <option value="">-- Sin asignar --</option>
                                <?php
                                $plans = [ 'starter' => 'Starter', 'business' => 'Business', 'enterprise' => 'Enterprise' ];
                                $colors = [ 'starter' => '#2271b1', 'business' => '#00a32a', 'enterprise' => '#8c00b7' ];
                                foreach ( $plans as $pkey => $plabel ) :
                                ?>
                                    <option value="<?php echo esc_attr( $pkey ); ?>" 
                                            <?php selected( $assigned_plan, $pkey ); ?>
                                            style="color:<?php echo esc_attr( $colors[ $pkey ] ); ?>;font-weight:600;">
                                        <?php echo esc_html( $plabel ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ( ! empty( $assigned_plan ) ) : ?>
                                <span class="dashicons dashicons-yes-alt" style="color:#00a32a;" title="Plan asignado"></span>
                            <?php else : ?>
                                <span class="dashicons dashicons-warning" style="color:#dba617;" title="Sin plan asignado"></span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if ( $health && $status !== 'unreachable' && $status !== 'error' && $status !== 'unknown' ) : ?>
                            <?php
                                $plan_key   = $health['site']['plan'] ?? 'starter';
                                $plan_labels = [ 'starter' => 'Starter', 'business' => 'Business', 'enterprise' => 'Enterprise' ];
                                $plan_colors = [ 'starter' => '#2271b1', 'business' => '#00a32a', 'enterprise' => '#8c00b7' ];
                            ?>
                            <div class="sapwcc-card-row">
                                <span class="sapwcc-card-label">Plan</span>
                                <span style="font-weight:600;color:<?php echo esc_attr( $plan_colors[ $plan_key ] ?? '#2271b1' ); ?>;">
                                    <?php echo esc_html( $plan_labels[ $plan_key ] ?? ucfirst( $plan_key ) ); ?>
                                </span>
                            </div>
                            <div class="sapwcc-card-row">
                                <span class="sapwcc-card-label">Plugin</span>
                                <?php
                                    $pv = $health['plugin']['version'] ?? '?';
                                    $outdated = version_compare( $pv, SAPWCC_LATEST_SUITE_VERSION, '<' );
                                ?>
                                <span class="<?php echo $outdated ? 'sapwcc-outdated' : ''; ?>">
                                    v<?php echo esc_html( $pv ); ?>
                                    <?php if ( $outdated ) : ?>
                                        <small>(última: <?php echo esc_html( SAPWCC_LATEST_SUITE_VERSION ); ?>)</small>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="sapwcc-card-row">
                                <span class="sapwcc-card-label">Stack</span>
                                <span>
                                    WP <?php echo esc_html( $health['platform']['wp'] ?? '?' ); ?>
                                    · WC <?php echo esc_html( $health['platform']['wc'] ?? '?' ); ?>
                                    · PHP <?php echo esc_html( $health['platform']['php'] ?? '?' ); ?>
                                </span>
                            </div>
                            <div class="sapwcc-card-row">
                                <span class="sapwcc-card-label">SAP</span>
                                <?php
                                    $sap_ok = ! empty( $health['sap']['connected'] );
                                    $sap_ms = $health['sap']['response_ms'] ?? 0;
                                ?>
                                <span class="<?php echo $sap_ok ? 'sapwcc-ok' : 'sapwcc-fail'; ?>">
                                    <?php echo $sap_ok ? "Conectado ({$sap_ms}ms)" : 'Desconectado'; ?>
                                </span>
                            </div>
                            <div class="sapwcc-card-metrics">
                                <div class="sapwcc-metric">
                                    <span class="sapwcc-metric-value"><?php echo intval( $health['errors_24h'] ?? 0 ); ?></span>
                                    <span class="sapwcc-metric-label">Errores 24h</span>
                                </div>
                                <div class="sapwcc-metric">
                                    <span class="sapwcc-metric-value"><?php echo intval( $health['pending_orders'] ?? 0 ); ?></span>
                                    <span class="sapwcc-metric-label">Pendientes</span>
                                </div>
                                <div class="sapwcc-metric">
                                    <span class="sapwcc-metric-value"><?php echo intval( $health['warnings_24h'] ?? 0 ); ?></span>
                                    <span class="sapwcc-metric-label">Warnings 24h</span>
                                </div>
                            </div>

                            <?php if ( ! empty( $health['last_sync'] ) ) : ?>
                            <div class="sapwcc-card-syncs">
                                <span class="sapwcc-card-label">Último sync</span>
                                <ul>
                                    <?php foreach ( $health['last_sync'] as $type => $ts ) : ?>
                                    <li><small><?php echo esc_html( ucfirst( $type ) ); ?>:</small> <?php echo esc_html( $ts ); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>

                            <?php if ( ! empty( $health['issues'] ) ) : ?>
                            <div class="sapwcc-card-issues">
                                <?php foreach ( $health['issues'] as $issue ) : ?>
                                    <div class="sapwcc-issue"><span class="dashicons dashicons-warning"></span> <?php echo esc_html( $issue ); ?></div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        <?php elseif ( $health ) : ?>
                            <div class="sapwcc-card-error">
                                <?php echo esc_html( $health['error'] ?? 'Respuesta inválida' ); ?>
                            </div>
                        <?php else : ?>
                            <p class="sapwcc-muted">Sin datos — pulsa Check.</p>
                        <?php endif; ?>
                    </div>

                    <div class="sapwcc-card-footer">
                        <button class="button sapwcc-check-btn" data-key="<?php echo esc_attr( $key ); ?>">
                            <span class="dashicons dashicons-update"></span> Check
                        </button>
                        <button class="button sapwcc-quick-action" data-key="<?php echo esc_attr( $key ); ?>" data-action="logs" title="Ver logs remotos">
                            <span class="dashicons dashicons-clipboard"></span>
                        </button>
                        <button class="button sapwcc-quick-action" data-key="<?php echo esc_attr( $key ); ?>" data-action="clear-cache" title="Limpiar cache">
                            <span class="dashicons dashicons-performance"></span>
                        </button>
                        <button class="button sapwcc-quick-action" data-key="<?php echo esc_attr( $key ); ?>" data-action="run-cron" title="Ejecutar cron">
                            <span class="dashicons dashicons-clock"></span>
                        </button>
                        <button class="button sapwcc-quick-action" data-key="<?php echo esc_attr( $key ); ?>" data-action="maintenance" title="Toggle mantenimiento">
                            <span class="dashicons dashicons-admin-tools"></span>
                        </button>
                        <button class="button sapwcc-quick-action" data-key="<?php echo esc_attr( $key ); ?>" data-action="update-check" title="Comprobar actualizacion">
                            <span class="dashicons dashicons-download"></span>
                        </button>
                        <?php
                            $card_pv       = $health ? ( $health['plugin']['version'] ?? '' ) : '';
                            $card_outdated = $card_pv && $card_pv !== '?' && version_compare( $card_pv, SAPWCC_LATEST_SUITE_VERSION, '<' );
                        ?>
                        <?php if ( $card_outdated ) : ?>
                        <button class="button button-primary sapwcc-quick-action sapwcc-update-btn"
                                data-key="<?php echo esc_attr( $key ); ?>"
                                data-action="update"
                                data-current="<?php echo esc_attr( $card_pv ); ?>"
                                data-latest="<?php echo esc_attr( SAPWCC_LATEST_SUITE_VERSION ); ?>"
                                data-label="<?php echo esc_attr( $site['label'] ); ?>"
                                title="Actualizar a v<?php echo esc_attr( SAPWCC_LATEST_SUITE_VERSION ); ?>">
                            <span class="dashicons dashicons-update-alt"></span> Actualizar
                        </button>
                        <?php endif; ?>

                        <div class="sapwcc-footer-row2">
                            <button class="button sapwcc-quick-action sapwcc-rotate-secret-btn"
                                    data-key="<?php echo esc_attr( $key ); ?>"
                                    data-label="<?php echo esc_attr( $site['label'] ); ?>"
                                    title="Generar nuevo X-SAPWC-Secret para este sitio">
                                <span class="dashicons dashicons-lock"></span> Rotar Secret
                            </button>
                            <?php if ( $health ) : ?>
                                <small class="sapwcc-muted">
                                    Checked: <?php echo esc_html( $health['checked_at'] ?? '—' ); ?>
                                </small>
                            <?php endif; ?>
                            <button class="button sapwcc-remove-btn" data-key="<?php echo esc_attr( $key ); ?>"
                                    title="Eliminar sitio">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    </div>

                    <!-- Client metadata (expandable) -->
                    <div class="sapwcc-card-meta">
                        <button class="button-link sapwcc-toggle-meta" data-key="<?php echo esc_attr( $key ); ?>">
                            <span class="dashicons dashicons-businessman"></span> Datos cliente ▾
                        </button>
                        <div class="sapwcc-meta-form" id="sapwcc-meta-<?php echo esc_attr( $key ); ?>" style="display:none;">
                            <div class="sapwcc-meta-grid">
                                <label>
                                    <small>Nombre</small>
                                    <input type="text" class="sapwcc-meta-field" data-field="client_name"
                                           value="<?php echo esc_attr( $site['client_name'] ?? '' ); ?>" placeholder="Nombre del cliente" />
                                </label>
                                <label>
                                    <small>Email</small>
                                    <input type="email" class="sapwcc-meta-field" data-field="client_email"
                                           value="<?php echo esc_attr( $site['client_email'] ?? '' ); ?>" placeholder="email@cliente.com" />
                                </label>
                                <label>
                                    <small>Contrato</small>
                                    <input type="date" class="sapwcc-meta-field" data-field="contract_date"
                                           value="<?php echo esc_attr( $site['contract_date'] ?? '' ); ?>" />
                                </label>
                                <label>
                                    <small>MRR (€/mes)</small>
                                    <input type="number" step="0.01" class="sapwcc-meta-field" data-field="monthly_fee"
                                           value="<?php echo esc_attr( $site['monthly_fee'] ?? 0 ); ?>" placeholder="0.00" />
                                </label>
                            </div>
                            <div class="sapwcc-meta-grid" style="margin-top:8px;border-top:1px solid #f0f0f1;padding-top:8px;">
                                <label>
                                    <small>SAP offline desde (h, 0–23)</small>
                                    <input type="number" min="0" max="23" class="sapwcc-meta-field" data-field="quiet_from"
                                           value="<?php echo esc_attr( $site['quiet_from'] ?? '' ); ?>" placeholder="22" />
                                </label>
                                <label>
                                    <small>SAP offline hasta (h, 0–23)</small>
                                    <input type="number" min="0" max="23" class="sapwcc-meta-field" data-field="quiet_to"
                                           value="<?php echo esc_attr( $site['quiet_to'] ?? '' ); ?>" placeholder="7" />
                                </label>
                            </div>
                            <?php
                            $qw = SAPWCC_Vigilante::detect_quiet_window( $key );
                            if ( $qw ) :
                            ?>
                            <p style="font-size:11px;color:#666;margin:4px 0 0;">
                                <span class="dashicons dashicons-chart-bar" style="font-size:12px;width:12px;height:12px;vertical-align:middle;"></span>
                                Patron detectado: ~<?php echo $qw['from']; ?>h–<?php echo $qw['to']; ?>h
                                (<?php echo round( $qw['confidence'] * 100 ); ?>% confianza, <?php echo $qw['events']; ?> eventos)
                            </p>
                            <?php endif; ?>
                            <button class="button button-small sapwcc-save-meta" data-key="<?php echo esc_attr( $key ); ?>">Guardar datos</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Vista tabla condensada (inicialmente oculta) -->
            <div class="sapwcc-sites-table-container" style="display:none;">
                <table class="sapwcc-sites-table wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="20">St</th>
                            <th>Sitio</th>
                            <th>Site ID</th>
                            <th width="120">Plan Asignado</th>
                            <th width="100">Plan Reportado</th>
                            <th width="80">Plugin</th>
                            <th width="80">SAP</th>
                            <th width="60" class="center">E24h</th>
                            <th width="60" class="center">W24h</th>
                            <th width="100">MRR</th>
                            <th width="180">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $sites as $key => $site ) :
                            $health    = SAPWCC_Sites::get_cached_health( $key );
                            $status    = $health['status'] ?? 'unknown';
                            $site_id   = $site['site_id'] ?: '—';
                            $tw        = SAPWCC_Sites::get_transient_warnings( $key, 24 );
                            $tw_count  = $tw['count'] ?? 0;
                            $flags     = class_exists( 'SAPWCC_Flags' ) ? SAPWCC_Flags::read() : [];
                            $assigned_plan = ! empty( $site['site_id'] ) ? ( $flags['sites'][ $site['site_id'] ]['plan'] ?? '' ) : '';
                            $reported_plan = $health['site']['plan'] ?? '';
                        ?>
                        <tr data-site-key="<?php echo esc_attr( $key ); ?>" class="sapwcc-table-row-<?php echo esc_attr( sanitize_html_class( $status ) ); ?>">
                            <td class="center">
                                <span class="sapwcc-status-dot sapwcc-status-dot--<?php echo esc_attr( sanitize_html_class( $status ) ); ?>" 
                                      title="<?php echo esc_attr( strtoupper( $status ) ); ?>"></span>
                            </td>
                            <td>
                                <strong><?php echo esc_html( $site['label'] ); ?></strong>
                                <?php if ( $tw_count > 0 ) : ?>
                                    <span class="sapwcc-tw-badge-sm" title="<?php echo esc_attr( "{$tw_count} warnings auto-resueltos" ); ?>">
                                        <?php echo $tw_count; ?>
                                    </span>
                                <?php endif; ?>
                                <br><small class="sapwcc-muted"><?php echo esc_html( wp_parse_url( $site['url'], PHP_URL_HOST ) ); ?></small>
                            </td>
                            <td><code><?php echo esc_html( $site_id ); ?></code></td>
                            <td>
                                <?php if ( ! empty( $site['site_id'] ) ) : ?>
                                    <select class="sapwcc-plan-assign sapwcc-plan-select-sm" 
                                            data-site-key="<?php echo esc_attr( $key ); ?>" 
                                            data-site-id="<?php echo esc_attr( $site['site_id'] ); ?>">
                                        <option value="">-- Sin asignar --</option>
                                        <?php
                                        $plans = [ 'starter' => 'Starter', 'business' => 'Business', 'enterprise' => 'Enterprise' ];
                                        $colors = [ 'starter' => '#2271b1', 'business' => '#00a32a', 'enterprise' => '#8c00b7' ];
                                        foreach ( $plans as $pkey => $plabel ) :
                                        ?>
                                            <option value="<?php echo esc_attr( $pkey ); ?>" 
                                                    <?php selected( $assigned_plan, $pkey ); ?>
                                                    style="color:<?php echo esc_attr( $colors[ $pkey ] ); ?>;font-weight:600;">
                                                <?php echo esc_html( $plabel ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else : ?>
                                    <span class="sapwcc-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( ! empty( $reported_plan ) ) : ?>
                                    <span class="sapwcc-plan-badge sapwcc-plan-badge--<?php echo esc_attr( $reported_plan ); ?>">
                                        <?php echo esc_html( ucfirst( $reported_plan ) ); ?>
                                    </span>
                                <?php else : ?>
                                    <span class="sapwcc-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php $pv = $health['plugin']['version'] ?? '?'; ?>
                                <code><?php echo esc_html( $pv ); ?></code>
                            </td>
                            <td>
                                <?php
                                    $sap_ok = ! empty( $health['sap']['connected'] );
                                    $icon = $sap_ok ? 'yes' : 'dismiss';
                                    $color = $sap_ok ? '#00a32a' : '#d63638';
                                ?>
                                <span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>" 
                                      style="color:<?php echo esc_attr( $color ); ?>;" 
                                      title="<?php echo $sap_ok ? 'Conectado' : 'Desconectado'; ?>"></span>
                            </td>
                            <td class="center">
                                <strong><?php echo intval( $health['errors_24h'] ?? 0 ); ?></strong>
                            </td>
                            <td class="center">
                                <strong><?php echo intval( $health['warnings_24h'] ?? 0 ); ?></strong>
                            </td>
                            <td>
                                <?php $fee = floatval( $site['monthly_fee'] ?? 0 ); ?>
                                <?php if ( $fee > 0 ) : ?>
                                    <strong><?php echo number_format( $fee, 2 ); ?>€</strong>
                                <?php else : ?>
                                    <span class="sapwcc-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="sapwcc-table-actions">
                                <button class="button-small sapwcc-check-btn" data-key="<?php echo esc_attr( $key ); ?>" title="Health check">
                                    <span class="dashicons dashicons-update"></span>
                                </button>
                                <button class="button-small sapwcc-quick-action" data-key="<?php echo esc_attr( $key ); ?>" data-action="logs" title="Logs">
                                    <span class="dashicons dashicons-clipboard"></span>
                                </button>
                                <button class="button-small sapwcc-quick-action" data-key="<?php echo esc_attr( $key ); ?>" data-action="clear-cache" title="Cache">
                                    <span class="dashicons dashicons-performance"></span>
                                </button>
                                <?php if ( $pv && $pv !== '?' && version_compare( $pv, SAPWCC_LATEST_SUITE_VERSION, '<' ) ) : ?>
                                <button class="button-small button-primary sapwcc-quick-action sapwcc-update-btn"
                                        data-key="<?php echo esc_attr( $key ); ?>"
                                        data-action="update"
                                        data-current="<?php echo esc_attr( $pv ); ?>"
                                        data-latest="<?php echo esc_attr( SAPWCC_LATEST_SUITE_VERSION ); ?>"
                                        data-label="<?php echo esc_attr( $site['label'] ); ?>"
                                        title="Actualizar a v<?php echo esc_attr( SAPWCC_LATEST_SUITE_VERSION ); ?>">
                                    <span class="dashicons dashicons-update-alt"></span>
                                </button>
                                <?php endif; ?>
                                <button class="button-small sapwcc-remove-btn" data-key="<?php echo esc_attr( $key ); ?>" title="Eliminar">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!--  TAB: FEATURE FLAGS                                                -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <div id="tab-flags" class="sapwcc-tab-content" <?php echo $active !== 'flags' ? 'style="display:none"' : ''; ?>>

        <?php if ( empty( $flags ) ) : ?>
            <div class="notice notice-warning"><p>No se pudo leer flags.json en <code><?php echo esc_html( SAPWCC_Flags::get_path() ); ?></code></p></div>
        <?php endif; ?>

        <div class="sapwcc-flags-layout-v2">

            <!-- ── Kill-switches globales ─────────────────────────────────── -->
            <div class="postbox sapwcc-flags-section">
                <h3 class="hndle">Kill-Switches Globales</h3>
                <div class="inside">
                    <p class="description">Estos flags aplican a TODAS las instalaciones. Un flag desactivado aqui bloquea ese cron/endpoint en todos los sitios.</p>
                    <?php
                    $groups = [ 'crons' => 'Crons', 'endpoints' => 'Endpoints', 'features' => 'Features' ];
                    foreach ( $groups as $group_key => $group_title ) : ?>
                        <h4><?php echo esc_html( $group_title ); ?></h4>
                        <?php foreach ( $labels as $flag_key => $meta ) :
                            if ( $meta['group'] !== $group_key ) continue;
                            $checked = (bool) ( $flags['global'][ $flag_key ] ?? true );
                        ?>
                        <div class="sapwcc-flag-row">
                            <label class="sapwcc-toggle">
                                <input type="checkbox" name="global[<?php echo esc_attr( $flag_key ); ?>]"
                                       data-scope="global" data-flag="<?php echo esc_attr( $flag_key ); ?>"
                                       <?php checked( $checked ); ?> />
                                <span class="sapwcc-toggle-slider"></span>
                            </label>
                            <span class="sapwcc-flag-label"><?php echo esc_html( $meta['label'] ); ?></span>
                            <code class="sapwcc-flag-key"><?php echo esc_html( $flag_key ); ?></code>
                        </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ── Matriz de planes ───────────────────────────────────────── -->
            <div class="postbox sapwcc-flags-section">
                <h3 class="hndle">Matriz de Planes</h3>
                <div class="inside">
                    <p class="description">Define que funcionalidades incluye cada plan. Los cambios se guardan al pulsar "Guardar flags.json".</p>
                    <table class="widefat sapwcc-plan-matrix">
                        <thead>
                            <tr>
                                <th>Funcionalidad</th>
                                <?php foreach ( SAPWCC_Flags::VALID_PLANS as $plan ) : ?>
                                    <th class="sapwcc-plan-col">
                                        <span class="sapwcc-plan-header" style="color:<?php echo esc_attr( SAPWCC_Flags::PLAN_COLORS[ $plan ] ?? '#333' ); ?>">
                                            <?php echo esc_html( SAPWCC_Flags::PLAN_LABELS[ $plan ] ?? ucfirst( $plan ) ); ?>
                                        </span>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( SAPWCC_Flags::PLAN_FEATURE_LABELS as $feat => $feat_label ) : ?>
                            <tr>
                                <td>
                                    <?php echo esc_html( $feat_label ); ?>
                                    <code class="sapwcc-flag-key"><?php echo esc_html( $feat ); ?></code>
                                </td>
                                <?php foreach ( SAPWCC_Flags::VALID_PLANS as $plan ) :
                                    $feat_checked = (bool) ( $flags['plans'][ $plan ][ $feat ] ?? false );
                                ?>
                                <td class="sapwcc-plan-col">
                                    <label class="sapwcc-toggle">
                                        <input type="checkbox"
                                               data-scope="plan-matrix"
                                               data-plan="<?php echo esc_attr( $plan ); ?>"
                                               data-feature="<?php echo esc_attr( $feat ); ?>"
                                               <?php checked( $feat_checked ); ?> />
                                        <span class="sapwcc-toggle-slider"></span>
                                    </label>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── Configuracion por sitio ────────────────────────────────── -->
            <div class="postbox sapwcc-flags-section sapwcc-flags-full">
                <h3 class="hndle">Configuracion por Sitio</h3>
                <div class="inside">
                    <p class="description">Selecciona un sitio para asignar su plan y configurar overrides individuales.</p>

                    <?php $known_ids = SAPWCC_Flags::get_known_site_ids(); ?>
                    <?php if ( empty( $known_ids ) ) : ?>
                        <p class="sapwcc-muted">No hay sitios con site_id conocido. Ejecuta un health check en la pestaña Sitios primero.</p>
                    <?php else : ?>
                        <div class="sapwcc-site-selector-row">
                            <select id="sapwcc-site-selector" class="sapwcc-site-selector">
                                <option value="">-- Selecciona sitio --</option>
                                <?php foreach ( $known_ids as $sid => $slabel ) :
                                    $site_plan = $flags['sites'][ $sid ]['plan'] ?? '';
                                    $plan_tag  = $site_plan ? ' [' . ucfirst( $site_plan ) . ']' : '';
                                ?>
                                    <option value="<?php echo esc_attr( $sid ); ?>"><?php echo esc_html( $slabel . $plan_tag ); ?> (<?php echo esc_html( $sid ); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="sapwcc-site-config" style="display: none;">

                            <!-- Plan assignment -->
                            <div class="sapwcc-site-plan-box">
                                <label for="sapwcc-site-plan"><strong>Plan asignado:</strong></label>
                                <select id="sapwcc-site-plan" class="sapwcc-plan-select">
                                    <?php foreach ( SAPWCC_Flags::VALID_PLANS as $plan ) : ?>
                                        <option value="<?php echo esc_attr( $plan ); ?>"
                                                style="color:<?php echo esc_attr( SAPWCC_Flags::PLAN_COLORS[ $plan ] ?? '#333' ); ?>">
                                            <?php echo esc_html( SAPWCC_Flags::PLAN_LABELS[ $plan ] ?? ucfirst( $plan ) ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">El plan determina que funcionalidades estan disponibles. El sitio leera este valor desde flags.json.</p>
                            </div>

                            <!-- Kill-switch overrides -->
                            <div class="sapwcc-overrides-group">
                                <h4>Kill-Switch Overrides</h4>
                                <p class="description">Sobreescribe los kill-switches globales solo para este sitio.</p>
                                <div id="sapwcc-killswitch-overrides">
                                    <?php foreach ( $labels as $flag_key => $meta ) : ?>
                                    <div class="sapwcc-override-row" data-key="<?php echo esc_attr( $flag_key ); ?>">
                                        <label class="sapwcc-override-check">
                                            <input type="checkbox" class="sapwcc-override-active" />
                                            <small>Override</small>
                                        </label>
                                        <label class="sapwcc-toggle sapwcc-override-toggle">
                                            <input type="checkbox" disabled />
                                            <span class="sapwcc-toggle-slider"></span>
                                        </label>
                                        <span class="sapwcc-flag-label"><?php echo esc_html( $meta['label'] ); ?></span>
                                        <code class="sapwcc-flag-key"><?php echo esc_html( $flag_key ); ?></code>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Plan feature overrides -->
                            <div class="sapwcc-overrides-group">
                                <h4>Plan Feature Overrides</h4>
                                <p class="description">Sobreescribe funcionalidades individuales del plan para este sitio (ej: activar Miravia en Business).</p>
                                <div id="sapwcc-planfeature-overrides">
                                    <?php foreach ( SAPWCC_Flags::PLAN_FEATURE_LABELS as $feat => $feat_label ) : ?>
                                    <div class="sapwcc-override-row" data-key="<?php echo esc_attr( $feat ); ?>">
                                        <label class="sapwcc-override-check">
                                            <input type="checkbox" class="sapwcc-override-active" />
                                            <small>Override</small>
                                        </label>
                                        <label class="sapwcc-toggle sapwcc-override-toggle">
                                            <input type="checkbox" disabled />
                                            <span class="sapwcc-toggle-slider"></span>
                                        </label>
                                        <span class="sapwcc-flag-label"><?php echo esc_html( $feat_label ); ?></span>
                                        <code class="sapwcc-flag-key"><?php echo esc_html( $feat ); ?></code>
                                        <span class="sapwcc-plan-default"></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── Notices remotos ────────────────────────────────────────── -->
            <div class="postbox sapwcc-flags-section sapwcc-flags-full">
                <h3 class="hndle">Notices Remotos</h3>
                <div class="inside">
                    <p class="description">Mensajes que se muestran en el admin de los sitios cliente (deprecaciones, mantenimiento, etc).</p>

                    <div id="sapwcc-notices-list">
                        <?php
                        $notices = $flags['notices'] ?? [];
                        if ( empty( $notices ) ) : ?>
                            <p class="sapwcc-muted">No hay notices configurados.</p>
                        <?php else :
                            foreach ( $notices as $i => $notice ) : ?>
                            <div class="sapwcc-notice-item" data-index="<?php echo $i; ?>" data-id="<?php echo esc_attr( $notice['id'] ?? '' ); ?>">
                                <span class="sapwcc-notice-type sapwcc-notice-type--<?php echo esc_attr( $notice['type'] ?? 'info' ); ?>">
                                    <?php echo esc_html( strtoupper( $notice['type'] ?? 'info' ) ); ?>
                                </span>
                                <span class="sapwcc-notice-msg"><?php echo esc_html( $notice['message'] ?? '' ); ?></span>
                                <?php if ( ! empty( $notice['expires'] ) ) : ?>
                                    <small class="sapwcc-muted sapwcc-notice-exp">exp: <?php echo esc_html( $notice['expires'] ); ?></small>
                                <?php endif; ?>
                                <?php if ( ! empty( $notice['target_sites'] ) ) : ?>
                                    <small class="sapwcc-muted sapwcc-notice-target">solo: <?php echo esc_html( implode( ', ', $notice['target_sites'] ) ); ?></small>
                                <?php endif; ?>
                                <button class="button-link sapwcc-remove-notice" data-index="<?php echo $i; ?>" title="Eliminar"><span class="dashicons dashicons-no-alt"></span></button>
                            </div>
                        <?php endforeach;
                        endif; ?>
                    </div>

                    <div class="sapwcc-add-notice-form" style="margin-top: 12px;">
                        <h4>Añadir notice</h4>
                        <select id="sapwcc-notice-type">
                            <option value="info">Info</option>
                            <option value="warning">Warning</option>
                            <option value="error">Error</option>
                            <option value="success">Success</option>
                        </select>
                        <input type="text" id="sapwcc-notice-msg" placeholder="Mensaje del notice" class="regular-text" />
                        <input type="date" id="sapwcc-notice-expires" title="Fecha de expiracion (opcional)" />
                        <button id="sapwcc-add-notice" class="button">+ Añadir</button>
                    </div>
                </div>
            </div>

        </div>

        <!-- Flags action bar -->
        <div class="sapwcc-flags-actions">
            <button id="sapwcc-save-flags" class="button button-primary button-hero">
                <span class="dashicons dashicons-saved"></span> Guardar flags.json
            </button>
            <button id="sapwcc-git-push" class="button button-hero" title="Commit y push a GitHub Pages">
                <span class="dashicons dashicons-cloud-upload"></span> Publicar
            </button>
            <button id="sapwcc-preview-json" class="button">Vista previa JSON</button>
            <span class="sapwcc-flags-path">
                Ruta: <code><?php echo esc_html( SAPWCC_Flags::get_path() ); ?></code>
            </span>
        </div>
        <pre id="sapwcc-git-output" class="sapwcc-git-output" style="display:none;"></pre>

        <div id="sapwcc-json-preview" class="postbox" style="display: none;">
            <h3 class="hndle">Preview flags.json</h3>
            <div class="inside">
                <pre id="sapwcc-json-output" style="max-height: 400px; overflow: auto; background: #f0f0f1; padding: 12px;"></pre>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!--  TAB: ACCIONES                                                     -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <div id="tab-actions" class="sapwcc-tab-content" <?php echo $active !== 'actions' ? 'style="display:none"' : ''; ?>>

        <?php if ( empty( $recs ) ) : ?>
            <div class="sapwcc-empty-state">
                <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
                <p>Todo en orden. No hay acciones pendientes.</p>
            </div>
        <?php else : ?>
            <table class="widefat sapwcc-actions-table">
                <thead>
                    <tr>
                        <th></th>
                        <th>Sitio</th>
                        <th>Problema</th>
                        <th>Acción recomendada</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $recs as $rec ) : ?>
                    <tr class="sapwcc-action-row sapwcc-action--<?php echo esc_attr( $rec['type'] ); ?>">
                        <td class="sapwcc-action-icon"><span class="dashicons <?php echo esc_attr( $rec['icon'] ?? 'dashicons-marker' ); ?>"></span></td>
                        <td><strong><?php echo esc_html( $rec['label'] ); ?></strong></td>
                        <td><?php echo esc_html( $rec['message'] ); ?></td>
                        <td>
                            <span class="sapwcc-action-suggestion"><?php echo esc_html( $rec['action'] ); ?></span>
                            <?php if ( $rec['action'] === 'check' ) : ?>
                                <button class="button button-small sapwcc-check-btn" data-key="<?php echo esc_attr( $rec['site_key'] ); ?>">Check</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Audit Log -->
        <div class="postbox" style="margin-top: 20px;">
            <h3 class="hndle"><span class="dashicons dashicons-backup"></span> Audit Log</h3>
            <div class="inside">
                <p class="description">Historial de operaciones realizadas desde el Control Center.</p>
                <?php
                $audit = SAPWCC_Audit::get_all( 30 );
                if ( empty( $audit ) ) : ?>
                    <p class="sapwcc-muted">Sin entradas en el audit log.</p>
                <?php else : ?>
                    <table class="widefat sapwcc-audit-table">
                        <thead>
                            <tr>
                                <th style="width:140px;">Fecha</th>
                                <th style="width:30px;"></th>
                                <th>Acción</th>
                                <th>Detalles</th>
                                <th>Sitio</th>
                                <th>Usuario</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $audit as $entry ) : ?>
                            <tr>
                                <td><code style="font-size:11px;"><?php echo esc_html( $entry['timestamp'] ?? '' ); ?></code></td>
                                <td><span class="dashicons <?php echo esc_attr( SAPWCC_Audit::get_icon( $entry['action'] ?? '' ) ); ?>" style="font-size:14px;width:14px;height:14px;color:#646970;"></span></td>
                                <td><?php echo esc_html( SAPWCC_Audit::get_label( $entry['action'] ?? '' ) ); ?></td>
                                <td class="sapwcc-audit-details"><?php echo esc_html( $entry['details'] ?? '' ); ?></td>
                                <td><?php echo esc_html( $entry['site'] ?? '—' ); ?></td>
                                <td><?php echo esc_html( $entry['user'] ?? '' ); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if ( count( SAPWCC_Audit::get_all() ) > 30 ) : ?>
                        <p class="sapwcc-muted" style="margin-top:8px;">Mostrando las últimas 30 entradas de <?php echo count( SAPWCC_Audit::get_all() ); ?> totales.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!--  TAB: CONFIG                                                       -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <div id="tab-config" class="sapwcc-tab-content" <?php echo $active !== 'config' ? 'style="display:none"' : ''; ?>>
        <div class="postbox">
            <h3 class="hndle">Configuración del Control Center</h3>
            <div class="inside">
                <table class="form-table">
                    <tr>
                        <th><label for="sapwcc-flags-path">Ruta local de flags.json</label></th>
                        <td>
                            <input type="text" id="sapwcc-flags-path" class="regular-text"
                                   value="<?php echo esc_attr( SAPWCC_Flags::get_path() ); ?>" />
                            <p class="description">Ruta completa al archivo flags.json del repo sapwoo (docs).</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Versión Suite (última)</th>
                        <td>
                            <code><?php echo esc_html( SAPWCC_LATEST_SUITE_VERSION ); ?></code>
                            <p class="description">Definida en la constante <code>SAPWCC_LATEST_SUITE_VERSION</code>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="sapwcc-github-token">GitHub Token</label></th>
                        <td>
                            <?php $gh_token_saved = ! empty( get_option( 'sapwcc_github_token', '' ) ); ?>
                            <input type="password" id="sapwcc-github-token" class="regular-text"
                                   value=""
                                   placeholder="<?php echo $gh_token_saved ? '(token guardado — deja vacío para no cambiar)' : 'ghp_xxxxx'; ?>"
                                   autocomplete="new-password" />
                            <?php if ( $gh_token_saved ) : ?>
                                <span class="dashicons dashicons-yes-alt" style="color:#00a32a;vertical-align:middle;" title="Token guardado"></span>
                            <?php endif; ?>
                            <p class="description">Token con permiso <code>repo</code> para publicar flags.json via GitHub API. <a href="https://github.com/settings/tokens" target="_blank" rel="noopener">Crear token</a></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="sapwcc-control-center-ip">IP del Control Center</label></th>
                        <td>
                            <input type="text" id="sapwcc-control-center-ip" class="regular-text"
                                   value="<?php echo esc_attr( get_option( 'sapwcc_control_center_ip', '' ) ); ?>"
                                   placeholder="Ej: 203.0.113.42" />
                            <p class="description">La dirección IP pública de este servidor (replanta.net). Los sitios cliente solo aceptarán el endpoint <code>POST /control/update</code> desde esta IP. Déjalo vacío para no restringir.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>GitHub Pages URL</th>
                        <td>
                            <a href="https://replantadev.github.io/sapwoo/flags.json" target="_blank" rel="noopener">
                                https://replantadev.github.io/sapwoo/flags.json
                            </a>
                            <p class="description">Al pulsar Publicar, se sube flags.json directamente via GitHub API.</p>
                        </td>
                    </tr>
                </table>
                <p>
                    <button id="sapwcc-save-settings" class="button button-primary">Guardar configuración</button>
                </p>
            </div>
        </div>

        <div class="postbox">
            <h3 class="hndle">Resumen del ecosistema</h3>
            <div class="inside">
                <table class="widefat">
                    <tbody>
                        <tr>
                            <td><strong>Sitios registrados</strong></td>
                            <td><?php echo count( $sites ); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Con health data</strong></td>
                            <td>
                                <?php
                                $with_health = 0;
                                foreach ( array_keys( $sites ) as $k ) {
                                    if ( SAPWCC_Sites::get_cached_health( $k ) ) $with_health++;
                                }
                                echo $with_health;
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Flags definidos</strong></td>
                            <td><?php echo count( $flags['global'] ?? [] ); ?> globales, <?php echo count( $flags['sites'] ?? [] ); ?> sitios con config</td>
                        </tr>
                        <tr>
                            <td><strong>Planes asignados</strong></td>
                            <td>
                                <?php
                                $plan_counts = [ 'starter' => 0, 'business' => 0, 'enterprise' => 0 ];
                                foreach ( ( $flags['sites'] ?? [] ) as $sid => $sdata ) {
                                    $p = $sdata['plan'] ?? 'starter';
                                    if ( isset( $plan_counts[ $p ] ) ) {
                                        $plan_counts[ $p ]++;
                                    }
                                }
                                $parts = [];
                                foreach ( $plan_counts as $pk => $pc ) {
                                    if ( $pc > 0 ) {
                                        $color = SAPWCC_Flags::PLAN_COLORS[ $pk ] ?? '#333';
                                        $parts[] = '<span style="color:' . esc_attr( $color ) . ';font-weight:600;">' . $pc . ' ' . esc_html( ucfirst( $pk ) ) . '</span>';
                                    }
                                }
                                echo ! empty( $parts ) ? implode( ' &middot; ', $parts ) : '<span class="sapwcc-muted">Ninguno</span>';
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Notices activos</strong></td>
                            <td><?php echo count( $flags['notices'] ?? [] ); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Acciones pendientes</strong></td>
                            <td><?php echo count( $recs ); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- MRR Dashboard -->
        <?php $mrr = SAPWCC_Sites::get_mrr_summary(); ?>
        <div class="postbox">
            <h3 class="hndle"><span class="dashicons dashicons-chart-bar"></span> MRR Dashboard</h3>
            <div class="inside">
                <div class="sapwcc-mrr-grid">
                    <div class="sapwcc-mrr-card sapwcc-mrr-total">
                        <span class="sapwcc-mrr-value"><?php echo number_format( $mrr['total'], 2, ',', '.' ); ?> €</span>
                        <span class="sapwcc-mrr-label">MRR Total</span>
                    </div>
                    <div class="sapwcc-mrr-card">
                        <span class="sapwcc-mrr-value"><?php echo $mrr['count']; ?></span>
                        <span class="sapwcc-mrr-label">Clientes con cuota</span>
                    </div>
                    <?php foreach ( $mrr['by_plan'] as $plan_k => $plan_mrr ) :
                        $color = SAPWCC_Flags::PLAN_COLORS[ $plan_k ] ?? '#333';
                    ?>
                    <div class="sapwcc-mrr-card">
                        <span class="sapwcc-mrr-value" style="color:<?php echo esc_attr( $color ); ?>">
                            <?php echo number_format( $plan_mrr, 2, ',', '.' ); ?> €
                        </span>
                        <span class="sapwcc-mrr-label"><?php echo esc_html( ucfirst( $plan_k ) ); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <p class="description" style="margin-top:10px;">Los datos MRR se configuran en cada tarjeta de sitio → "Datos cliente".</p>
            </div>
        </div>
    </div>

    <?php include SAPWCC_PATH . 'templates/tab-vigilante.php'; ?>

    <!-- Remote logs modal -->
    <div id="sapwcc-logs-modal" class="sapwcc-modal" style="display:none;">
        <div class="sapwcc-modal-content">
            <div class="sapwcc-modal-header">
                <h3><span class="dashicons dashicons-clipboard"></span> <span id="sapwcc-logs-modal-title">Logs remotos</span></h3>
                <button class="button-link sapwcc-modal-close"><span class="dashicons dashicons-no-alt"></span></button>
            </div>
            <div class="sapwcc-modal-toolbar">
                <select id="sapwcc-logs-level">
                    <option value="">Todos los niveles</option>
                    <option value="error">Error</option>
                    <option value="warning">Warning</option>
                    <option value="success">Success</option>
                </select>
                <select id="sapwcc-logs-limit">
                    <option value="25">25 entradas</option>
                    <option value="50" selected>50 entradas</option>
                    <option value="100">100 entradas</option>
                    <option value="200">200 entradas</option>
                </select>
                <button id="sapwcc-logs-refresh" class="button"><span class="dashicons dashicons-update"></span> Refrescar</button>
                <span id="sapwcc-logs-total" class="sapwcc-muted"></span>
            </div>
            <div class="sapwcc-modal-body">
                <table class="widefat sapwcc-logs-table">
                    <thead>
                        <tr>
                            <th style="width:140px;">Fecha</th>
                            <th style="width:60px;">Estado</th>
                            <th style="width:100px;">Acción</th>
                            <th style="width:60px;">Pedido</th>
                            <th>Mensaje</th>
                        </tr>
                    </thead>
                    <tbody id="sapwcc-logs-body">
                        <tr><td colspan="5" class="sapwcc-muted">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Cron selector modal -->
    <div id="sapwcc-cron-modal" class="sapwcc-modal" style="display:none;">
        <div class="sapwcc-modal-content sapwcc-modal-sm">
            <div class="sapwcc-modal-header">
                <h3><span class="dashicons dashicons-clock"></span> Ejecutar Cron</h3>
                <button class="button-link sapwcc-modal-close"><span class="dashicons dashicons-no-alt"></span></button>
            </div>
            <div class="sapwcc-modal-body">
                <p>Selecciona el cron a ejecutar en <strong id="sapwcc-cron-site-label"></strong>:</p>
                <div id="sapwcc-cron-options" class="sapwcc-cron-options">
                    <?php
                    $cron_labels = [
                        'sapwc_cron_sync_orders'     => 'Sync Pedidos',
                        'sapwc_cron_sync_stock'      => 'Sync Stock',
                        'sapwc_cron_sync_products'   => 'Sync Productos',
                        'sapwc_cron_sync_categories' => 'Sync Categorías',
                    ];
                    foreach ( $cron_labels as $hook => $clabel ) : ?>
                        <button class="button sapwcc-run-cron-btn" data-hook="<?php echo esc_attr( $hook ); ?>">
                            <span class="dashicons dashicons-controls-play"></span> <?php echo esc_html( $clabel ); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <pre id="sapwcc-cron-output" class="sapwcc-git-output" style="display:none;"></pre>
            </div>
        </div>
    </div>

</div>
