<?php
/**
 * Admin Operations Page - Sites que necesitan atencion
 * Aggregates SA scores, NS alerts, domain renewal alerts and pending updates.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPHUB_Admin_Operations {

    public function render() {
        $data       = $this->get_attention_data();
        $sites      = $data['sites'];
        $stats      = $data['stats'];
        $sync_nonce = wp_create_nonce('rphub_sync_site');
        $task_nonce = wp_create_nonce('rphub_execute_task');
        $ajax_nonce = wp_create_nonce('rphub_ajax');
        ?>
        <div class="wrap rphub-operations">
            <h1 class="wp-heading-inline">Operaciones</h1>
            <button class="page-title-action" id="rphub-sync-all-btn" onclick="rphubOpsRefreshAll()">
                <span class="dashicons dashicons-update" style="vertical-align:middle;font-size:14px;margin-top:-2px;"></span>
                Sincronizar todos
            </button>
            <button class="page-title-action" id="rphub-force-care-update-btn" onclick="rphubForceCarUpdate(this)">
                <span class="dashicons dashicons-upload" style="vertical-align:middle;font-size:14px;margin-top:-2px;"></span>
                Forzar actualizacion Care
            </button>
            <?php $this->renderSummaryChips($stats); ?>
            <?php $this->renderFilterBar(); ?>

            <!-- Attention table -->
            <table class="wp-list-table widefat fixed striped rphub-ops-table" id="rphub-ops-table">
                <thead>
                    <tr>
                        <th class="col-site">Sitio</th>
                        <th class="col-sa">Score SA</th>
                        <th class="col-alertas">Alertas</th>
                        <th class="col-updates">Updates</th>
                        <th class="col-scores">CF / SEO / Perf</th>
                        <th class="col-domain">Dominio</th>
                        <th class="col-audit">Ultima auditoria</th>
                        <th class="col-actions">Acciones</th>
                    </tr>
                </thead>
                <tbody id="rphub-ops-tbody">
                <?php foreach ($sites as $s): ?>
                    <?php
                    $score       = (int) $s['sa_global_score'];
                    $critical    = (int) $s['sa_critical_issues'];
                    $warnings    = (int) $s['sa_warning_issues'];
                    $updates     = (int) $s['pending_updates_count'];
                    $cf_score    = (int) $s['cf_score'];
                    $seo_score   = (int) $s['seo_score'];
                    $perf_score  = (int) $s['perf_score'];
                    $last_audit  = $s['sa_last_audit'] ?? '';
                    $has_sa      = $score > 0 || $critical > 0 || $warnings > 0;
                    $score_class = $score === 0 && !$has_sa ? 'score-none'
                                 : ($score >= 80 ? 'score-good' : ($score >= 50 ? 'score-warning' : 'score-bad'));

                    $pending_ns      = $s['pending_ns']       ?? false;
                    $ns_error        = $s['ns_error']          ?? false;
                    $days_left       = $s['days_to_expiry']    ?? null;
                    $expiry_date     = $s['domain_expiry']     ?? '';
                    $ssl_type        = $s['ssl_type']           ?? '';
                    $cf_zone_st      = $s['cf_zone_status']     ?? '';
                    $seo_regression  = (bool)($s['seo_regression'] ?? 0);

                    $has_any_issue = $critical > 0 || $updates > 0 || $pending_ns || $ns_error
                                   || $seo_regression
                                   || ($days_left !== null && $days_left < 30);

                    // Row highlight
                    $row_class = '';
                    if ($critical > 0 || $ns_error || ($days_left !== null && $days_left <= 7)) {
                        $row_class = 'row-critical';
                    } elseif ($warnings > 0 || $updates > 2 || $pending_ns || ($days_left !== null && $days_left <= 30)) {
                        $row_class = 'row-warning';
                    }

                    // Priority sort value (lower = higher priority)
                    $priority = 0;
                    if ($critical > 0)   $priority -= 100;
                    if ($ns_error)       $priority -= 50;
                    if ($days_left !== null && $days_left <= 7)  $priority -= 80;
                    if ($days_left !== null && $days_left <= 30) $priority -= 30;
                    if ($pending_ns)     $priority -= 20;
                    if ($seo_regression) $priority -= 15;
                    if ($warnings > 0)   $priority -= 10;
                    if ($has_sa && $score < 50)  $priority -= (50 - $score);
                    if ($updates > 0)    $priority -= min($updates * 2, 20);
                    ?>
                    <tr data-site-id="<?php echo esc_attr($s['id']); ?>"
                        data-priority="<?php echo esc_attr($priority); ?>"
                        data-score="<?php echo esc_attr($score); ?>"
                        data-critical="<?php echo esc_attr($critical); ?>"
                        data-updates="<?php echo esc_attr($updates); ?>"
                        data-expiry="<?php echo esc_attr($days_left ?? 9999); ?>"
                        data-name="<?php echo esc_attr(strtolower($s['name'])); ?>"
                        data-has-issues="<?php echo $has_any_issue ? '1' : '0'; ?>"
                        class="<?php echo esc_attr($row_class); ?>">

                        <td class="col-site">
                            <strong><?php echo esc_html($s['name']); ?></strong>
                            <?php if (!empty($s['client_name'])): ?>
                                <br><small class="rphub-client-tag"><?php echo esc_html($s['client_name']); ?></small>
                            <?php endif; ?>
                            <br><a href="<?php echo esc_url($s['url']); ?>" target="_blank" rel="noopener" class="rphub-site-url"><?php echo esc_html(preg_replace('#^https?://#', '', rtrim($s['url'], '/'))); ?></a>
                        </td>

                        <td class="col-sa" style="text-align:center;">
                            <?php if ($has_sa): ?>
                                <span class="rphub-score-badge <?php echo esc_attr($score_class); ?>"><?php echo esc_html($score); ?></span>
                            <?php else: ?>
                                <span class="rphub-score-badge score-none" title="Sin datos SA">-</span>
                            <?php endif; ?>
                        </td>

                        <td class="col-alertas">
                            <?php if ($critical > 0): ?>
                                <span class="rphub-alert-pill pill-critical" title="<?php echo esc_attr($critical); ?> issues criticos SA"><?php echo esc_html($critical); ?> !</span>
                            <?php endif; ?>
                            <?php if ($warnings > 0): ?>
                                <span class="rphub-alert-pill pill-warning" title="<?php echo esc_attr($warnings); ?> avisos SA"><?php echo esc_html($warnings); ?> !</span>
                            <?php endif; ?>
                            <?php if ($ns_error): ?>
                                <span class="rphub-alert-pill pill-critical" title="CF onboarding fallido - revisar NS">NS x</span>
                            <?php elseif ($pending_ns): ?>
                                <span class="rphub-alert-pill pill-warning" title="Zona CF creada, NS aun no apuntan a Cloudflare">NS ...</span>
                            <?php endif; ?>
                            <?php if ($days_left !== null && $days_left <= 7): ?>
                                <span class="rphub-alert-pill pill-critical" title="Dominio expira en <?php echo esc_attr($days_left); ?> dias (<?php echo esc_attr($expiry_date); ?>)">REN <?php echo esc_html($days_left); ?>d</span>
                            <?php elseif ($days_left !== null && $days_left <= 30): ?>
                                <span class="rphub-alert-pill pill-warning" title="Dominio expira en <?php echo esc_attr($days_left); ?> dias (<?php echo esc_attr($expiry_date); ?>)">REN <?php echo esc_html($days_left); ?>d</span>
                            <?php endif; ?>
                            <?php if ($seo_regression): ?>
                                <span class="rphub-alert-pill pill-warning" title="SEO score bajo 10+ puntos desde la ultima sincronizacion">SEO bajo</span>
                            <?php endif; ?>
                            <?php if (!$critical && !$warnings && !$pending_ns && !$ns_error && !$seo_regression && ($days_left === null || $days_left > 30) && $has_sa): ?>
                                <span class="rphub-alert-pill pill-ok">OK</span>
                            <?php endif; ?>
                            <?php if (!$has_sa && !$pending_ns && !$ns_error && !$seo_regression && ($days_left === null || $days_left > 30)): ?>
                                <span class="rphub-no-data">Sin datos</span>
                            <?php endif; ?>
                        </td>

                        <td class="col-updates" style="text-align:center;">
                            <?php if ($updates > 0): ?>
                                <span class="rphub-updates-badge <?php echo $updates > 5 ? 'badge-high' : 'badge-mid'; ?>"><?php echo esc_html($updates); ?></span>
                            <?php else: ?>
                                <span class="rphub-no-data">0</span>
                            <?php endif; ?>
                        </td>

                        <td class="col-scores">
                            <div class="rphub-mini-scores">
                                <?php echo $this->mini_score_bar('CF', $cf_score); ?>
                                <?php echo $this->mini_score_bar('SEO', $seo_score); ?>
                                <?php echo $this->mini_score_bar('P', $perf_score); ?>
                            </div>
                        </td>

                        <td class="col-domain">
                            <?php echo $this->ssl_badge($s); ?>
                            <?php if ($cf_zone_st === 'active'): ?>
                                <span class="rphub-domain-badge badge-cf-active" title="Cloudflare activo">CF OK</span>
                            <?php elseif (!empty($s['cf_zone_id'])): ?>
                                <span class="rphub-domain-badge badge-cf-pending" title="Zona CF creada, NS pendientes">CF ...</span>
                            <?php endif; ?>
                        </td>

                        <td class="col-audit">
                            <?php if ($last_audit): ?>
                                <span title="<?php echo esc_attr($last_audit); ?>"><?php echo esc_html($this->human_diff($last_audit)); ?></span>
                            <?php else: ?>
                                <span class="rphub-no-data">Sin datos</span>
                            <?php endif; ?>
                        </td>

                        <td class="col-actions">
                            <div class="rphub-ops-actions">
                                <button class="button button-small"
                                    title="Sincronizar sitio"
                                    data-rphub-action="sync"
                                    data-site-id="<?php echo esc_attr($s['id']); ?>">
                                    <span class="dashicons dashicons-update"></span>
                                </button>
                                <?php if ($updates > 0): ?>
                                <button class="button button-small button-primary"
                                    title="Instalar actualizaciones"
                                    data-rphub-action="task"
                                    data-site-id="<?php echo esc_attr($s['id']); ?>"
                                    data-task="updates">
                                    <span class="dashicons dashicons-arrow-up-alt"></span>
                                </button>
                                <?php endif; ?>
                                <?php if ($has_sa): ?>
                                <button class="button button-small"
                                    title="Ver y ejecutar fixes SA"
                                    data-rphub-action="fixes"
                                    data-site-id="<?php echo esc_attr($s['id']); ?>"
                                    data-site-name="<?php echo esc_attr($s['name']); ?>">
                                    <span class="dashicons dashicons-admin-tools"></span>
                                </button>
                                <?php endif; ?>
                                <button class="button button-small"
                                    title="Aplicar features del plan"
                                    data-rphub-action="plan"
                                    data-site-id="<?php echo esc_attr($s['id']); ?>"
                                    data-site-name="<?php echo esc_attr($s['name']); ?>"
                                    data-plan="<?php echo esc_attr($s['plan'] ?? 'semilla'); ?>">
                                    <span class="dashicons dashicons-admin-settings"></span>
                                </button>
                                <a class="button button-small"
                                    href="<?php echo esc_url(admin_url('admin.php?page=replanta-hub-sites&site_id=' . $s['id'])); ?>"
                                    title="Ver detalle del sitio">
                                    <span class="dashicons dashicons-visibility"></span>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($sites)): ?>
                    <tr><td colspan="8" style="text-align:center;padding:24px;color:#888;">No hay sitios registrados.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- SA Fixes Modal -->
        <div id="rphub-sa-modal" style="display:none;position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.55);">
            <div style="background:#fff;border-radius:6px;max-width:640px;width:90%;max-height:80vh;overflow-y:auto;margin:8vh auto;padding:24px;box-shadow:0 8px 32px rgba(0,0,0,.25);">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                    <h2 id="rphub-sa-modal-title" style="margin:0;font-size:16px;">Issues SA</h2>
                    <button onclick="document.getElementById('rphub-sa-modal').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:20px;color:#666;">x</button>
                </div>
                <div id="rphub-sa-modal-body">
                    <p style="color:#888;">Cargando...</p>
                </div>
            </div>
        </div>

        <!-- Plan Config Modal -->
        <div id="rphub-plan-modal" style="display:none;position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.55);">
            <div style="background:#fff;border-radius:6px;max-width:580px;width:90%;max-height:80vh;overflow-y:auto;margin:8vh auto;padding:24px;box-shadow:0 8px 32px rgba(0,0,0,.25);">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                    <h2 id="rphub-plan-modal-title" style="margin:0;font-size:16px;">Configurar plan</h2>
                    <button onclick="document.getElementById('rphub-plan-modal').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:20px;color:#666;">x</button>
                </div>
                <div id="rphub-plan-modal-body">
                    <p style="color:#888;">Cargando...</p>
                </div>
            </div>
        </div>

        <style>
        .rphub-operations .rphub-ops-summary {
            display: flex;
            gap: 10px;
            margin: 16px 0;
            flex-wrap: wrap;
        }
        .rphub-ops-chip {
            display: flex;
            flex-direction: column;
            align-items: center;
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 8px 16px;
            min-width: 80px;
        }
        .rphub-ops-chip strong { font-size: 20px; line-height: 1; }
        .rphub-ops-chip span { font-size: 11px; color: #666; margin-top: 3px; text-align: center; }
        .rphub-ops-chip-critical { border-color: #d63638; background: #fff5f5; }
        .rphub-ops-chip-critical strong { color: #d63638; }
        .rphub-ops-chip-warning { border-color: #dba617; background: #fffbea; }
        .rphub-ops-chip-warning strong { color: #b87000; }

        .rphub-ops-filters {
            margin-bottom: 12px;
            padding: 8px 14px;
            background: #f6f7f7;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .rphub-ops-table { margin-top: 0; }
        .rphub-ops-table .col-site    { width: 20%; }
        .rphub-ops-table .col-sa      { width: 7%; }
        .rphub-ops-table .col-alertas { width: 18%; }
        .rphub-ops-table .col-updates { width: 7%; }
        .rphub-ops-table .col-scores  { width: 13%; }
        .rphub-ops-table .col-domain  { width: 10%; }
        .rphub-ops-table .col-audit   { width: 12%; }
        .rphub-ops-table .col-actions { width: 13%; }

        .rphub-ops-table tr.row-critical { background: #fff5f5 !important; }
        .rphub-ops-table tr.row-warning  { background: #fffbea !important; }

        .rphub-score-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 13px;
        }
        .score-good    { background: #d1e7dd; color: #0a3622; }
        .score-warning { background: #fff3cd; color: #664d03; }
        .score-bad     { background: #f8d7da; color: #58151c; }
        .score-none    { background: #e9ecef; color: #6c757d; }

        .rphub-alert-pill {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
            margin: 1px 2px 1px 0;
        }
        .pill-critical { background: #fde8e9; color: #d63638; border: 1px solid #f5c2c7; }
        .pill-warning  { background: #fff3cd; color: #b87000; border: 1px solid #ffc107; }
        .pill-ok       { background: #d1e7dd; color: #0a3622; border: 1px solid #a3cfbb; }

        .rphub-updates-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 700;
        }
        .badge-high { background: #fde8e9; color: #d63638; }
        .badge-mid  { background: #fff3cd; color: #b87000; }

        .rphub-mini-scores { display: flex; flex-direction: column; gap: 3px; }
        .rphub-mini-score-row { display: flex; align-items: center; gap: 5px; font-size: 11px; }
        .rphub-mini-score-row .ms-label { width: 26px; color: #666; flex-shrink: 0; }
        .rphub-mini-bar-wrap { flex: 1; background: #eee; border-radius: 3px; height: 6px; }
        .rphub-mini-bar { height: 6px; border-radius: 3px; }
        .rphub-mini-score-row .ms-val { width: 24px; text-align: right; color: #444; flex-shrink: 0; }

        .rphub-ssl-badge-ops {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 700;
            margin-right: 3px;
        }
        .ssl-ops-cf   { background: #f4832a22; color: #c05000; border: 1px solid #f4832a55; }
        .ssl-ops-le   { background: #d1e7dd; color: #0a3622; border: 1px solid #a3cfbb; }
        .ssl-ops-as   { background: #ede7f6; color: #4527a0; border: 1px solid #b39ddb; }
        .ssl-ops-paid { background: #cfe2ff; color: #084298; border: 1px solid #9ec5fe; }
        .ssl-ops-ok   { background: #d1e7dd; color: #0a3622; border: 1px solid #a3cfbb; }
        .ssl-ops-warn { background: #fde8e9; color: #d63638; border: 1px solid #f5c2c7; }

        .rphub-domain-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 700;
        }
        .badge-cf-active  { background: #e6f9eb; color: #00a32a; border: 1px solid #a3cfbb; }
        .badge-cf-pending { background: #fff3cd; color: #b87000; border: 1px solid #ffc107; }

        .rphub-site-url { font-size: 11px; color: #666; text-decoration: none; }
        .rphub-client-tag { color: #888; font-size: 11px; }
        .rphub-no-data { color: #bbb; }
        .rphub-ops-actions,
        .rphub-queue-actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            align-items: center;
        }
        .rphub-ops-actions .button,
        .rphub-queue-actions .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 30px;
            min-height: 30px;
            padding: 0 8px;
            border-color: #8c8f94;
            background: #fff;
            color: #1d2327;
            line-height: 1;
            box-shadow: none;
        }
        .rphub-ops-actions .button:hover,
        .rphub-ops-actions .button:focus,
        .rphub-queue-actions .button:hover,
        .rphub-queue-actions .button:focus {
            border-color: #2271b1;
            color: #0a4b78;
            background: #f6f7f7;
        }
        .rphub-ops-actions .button:disabled,
        .rphub-queue-actions .button:disabled {
            opacity: .65;
            color: #646970 !important;
        }
        .rphub-ops-actions .button-primary {
            background: #135e96;
            border-color: #135e96;
            color: #fff;
        }
        .rphub-ops-actions .button-primary:hover,
        .rphub-ops-actions .button-primary:focus {
            background: #0a4b78;
            border-color: #0a4b78;
            color: #fff;
        }
        .rphub-ops-actions .dashicons {
            color: currentColor;
            font-size: 15px;
            width: 15px;
            height: 15px;
            line-height: 15px;
            margin: 0;
        }

        @keyframes rphub-spin { to { transform: rotate(360deg); } }
        .rphub-spinning { animation: rphub-spin 0.8s linear infinite; }
        </style>

        <script>
        (function() {
            var _syncNonce = <?php echo wp_json_encode($sync_nonce); ?>;
            var _taskNonce = <?php echo wp_json_encode($task_nonce); ?>;
            var _ajaxNonce = <?php echo wp_json_encode($ajax_nonce); ?>;
            var _ajaxUrl   = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;

            document.addEventListener('click', function(event) {
                var actionBtn = event.target.closest('[data-rphub-action]');
                if (actionBtn) {
                    var siteId = parseInt(actionBtn.dataset.siteId || '0', 10);
                    var action = actionBtn.dataset.rphubAction;
                    if (action === 'sync') {
                        event.preventDefault();
                        window.rphubOpsSyncSite(siteId, actionBtn);
                    } else if (action === 'task') {
                        event.preventDefault();
                        window.rphubOpsRunTask(siteId, actionBtn.dataset.task || '', actionBtn);
                    } else if (action === 'fixes') {
                        event.preventDefault();
                        window.rphubOpsShowFixes(siteId, actionBtn.dataset.siteName || '', actionBtn);
                    } else if (action === 'plan') {
                        event.preventDefault();
                        window.rphubOpsShowPlanConfig(siteId, actionBtn.dataset.siteName || '', actionBtn.dataset.plan || 'semilla');
                    }
                    return;
                }

                var fixBtn = event.target.closest('.rphub-sa-fix-btn');
                if (fixBtn) {
                    event.preventDefault();
                    window.rphubOpsExecFix(
                        parseInt(fixBtn.dataset.siteId || '0', 10),
                        fixBtn.dataset.fixId || '',
                        fixBtn
                    );
                    return;
                }

                var applyBtn = event.target.closest('.rphub-plan-apply-btn');
                if (applyBtn) {
                    event.preventDefault();
                    window.rphubOpsApplyPlan(parseInt(applyBtn.dataset.siteId || '0', 10), applyBtn);
                }
            });

            window.rphubOpsSyncSite = function(siteId, btn) {
                var icon = btn.querySelector('.dashicons');
                if (icon) icon.classList.add('rphub-spinning');
                btn.disabled = true;
                jQuery.post(_ajaxUrl, {
                    action: 'rphub_sync_site',
                    site_id: siteId,
                    nonce: _ajaxNonce
                }, function(resp) {
                    if (icon) icon.classList.remove('rphub-spinning');
                    btn.disabled = false;
                    if (resp.success) { location.reload(); }
                    else { alert('Error: ' + (resp.data || 'No se pudo sincronizar')); }
                });
            };

            window.rphubOpsRunTask = function(siteId, task, btn) {
                if (!confirm('Ejecutar "' + task + '" en este sitio?')) return;
                var icon = btn.querySelector('.dashicons');
                if (icon) icon.classList.add('rphub-spinning');
                btn.disabled = true;
                jQuery.post(_ajaxUrl, {
                    action: 'rphub_execute_task',
                    site_id: siteId,
                    task_type: task,
                    nonce: _ajaxNonce
                }, function(resp) {
                    if (icon) icon.classList.remove('rphub-spinning');
                    btn.disabled = false;
                    if (resp.success) {
                        alert('Tarea "' + task + '" iniciada.');
                        location.reload();
                    } else {
                        alert('Error: ' + (resp.data || 'Fallo la tarea'));
                    }
                });
            };

            window.rphubOpsRefreshAll = function() {
                if (!confirm('Sincronizar todos los sitios? Puede tardar varios minutos.')) return;
                jQuery.post(_ajaxUrl, {
                    action: 'rphub_sync_all',
                    nonce: _ajaxNonce
                }, function(resp) {
                    if (resp.success) { location.reload(); }
                    else { alert('Error: ' + (resp.data || 'Fallo la sincronizacion global')); }
                });
            };

            window.rphubOpsShowFixes = function(siteId, siteName, btn) {
                var modal = document.getElementById('rphub-sa-modal');
                var body  = document.getElementById('rphub-sa-modal-body');
                var title = document.getElementById('rphub-sa-modal-title');
                title.textContent = 'Issues SA - ' + siteName;
                body.innerHTML = '<p style="color:#888;">Cargando issues...</p>';
                modal.style.display = 'block';
                jQuery.post(_ajaxUrl, {
                    action: 'rphub_get_sa_issues',
                    site_id: siteId,
                    nonce: _ajaxNonce
                }, function(resp) {
                    if (!resp.success) {
                        body.innerHTML = '<p style="color:#d63638;">Error: ' + (resp.data || 'No se pudieron cargar los issues') + '</p>';
                        return;
                    }
                    var data   = resp.data;
                    var issues = data.issues || [];
                    if (!data.sa_available) {
                        body.innerHTML = '<p>replanta-site-audit no esta activo en este sitio.</p>';
                        return;
                    }
                    if (!issues.length) {
                        body.innerHTML = '<p style="color:#00a32a;">OK Sin issues pendientes.</p>';
                        return;
                    }
                    var html = '<p style="color:#666;margin-bottom:12px;">Score global: <strong>' + (data.global_score || 0) + '</strong> - ' + issues.length + ' issue(s)</p>';
                    html += '<table style="width:100%;border-collapse:collapse;">';
                    html += '<tr style="background:#f0f0f1;font-size:12px;"><th style="padding:6px 8px;text-align:left;">Issue</th><th style="padding:6px 8px;">Estado</th><th style="padding:6px 8px;">Accion</th></tr>';
                    issues.forEach(function(issue) {
                        var statusColor = issue.status === 'critical' ? '#d63638' : '#b87000';
                        var fixBtn = issue.fixable
                            ? '<button class="button button-small rphub-sa-fix-btn" style="font-size:11px;" data-site-id="' + String(siteId) + '" data-fix-id="' + escHtmlModal(issue.fix_id) + '">Ejecutar fix</button>'
                            : '<span style="color:#bbb;font-size:11px;">Manual</span>';
                        html += '<tr style="border-top:1px solid #eee;">';
                        html += '<td style="padding:8px;font-size:12px;"><strong>' + escHtmlModal(issue.label) + '</strong>';
                        if (issue.description) html += '<br><span style="color:#666;">' + escHtmlModal(issue.description) + '</span>';
                        html += '</td>';
                        html += '<td style="padding:8px;text-align:center;"><span style="color:' + statusColor + ';font-weight:600;font-size:11px;">' + issue.status.toUpperCase() + '</span></td>';
                        html += '<td style="padding:8px;text-align:center;">' + fixBtn + '</td>';
                        html += '</tr>';
                    });
                    html += '</table>';
                    body.innerHTML = html;
                });
            };

            window.rphubOpsExecFix = function(siteId, fixId, btn) {
                if (!confirm('Ejecutar fix "' + fixId + '"?')) return;
                btn.disabled = true;
                btn.textContent = 'Ejecutando...';
                jQuery.post(_ajaxUrl, {
                    action: 'rphub_run_sa_fix',
                    site_id: siteId,
                    fix_id: fixId,
                    nonce: _ajaxNonce
                }, function(resp) {
                    if (resp.success) {
                        btn.textContent = 'OK Hecho';
                        btn.style.color = '#00a32a';
                    } else {
                        btn.disabled = false;
                        btn.textContent = 'Ejecutar fix';
                        alert('Error: ' + (resp.data || 'Fallo el fix'));
                    }
                });
            };

            function escHtmlModal(str) {
                var d = document.createElement('div');
                d.appendChild(document.createTextNode(str || ''));
                return d.innerHTML;
            }

            window.rphubOpsFilter = function() {
                var onlyIssues = document.getElementById('rphub-ops-only-issues').checked;
                document.querySelectorAll('#rphub-ops-tbody tr[data-site-id]').forEach(function(row) {
                    row.style.display = (onlyIssues && row.dataset.hasIssues === '0') ? 'none' : '';
                });
            };

            window.rphubOpsSort = function() {
                var sortBy = document.getElementById('rphub-ops-sort').value;
                var tbody  = document.getElementById('rphub-ops-tbody');
                var rows   = Array.from(tbody.querySelectorAll('tr[data-site-id]'));
                rows.sort(function(a, b) {
                    if (sortBy === 'priority') return (parseInt(a.dataset.priority)||0) - (parseInt(b.dataset.priority)||0);
                    if (sortBy === 'score') {
                        var as = parseInt(a.dataset.score)||0, bs = parseInt(b.dataset.score)||0;
                        if (as === 0 && bs !== 0) return 1;
                        if (bs === 0 && as !== 0) return -1;
                        return as - bs;
                    }
                    if (sortBy === 'critical') return (parseInt(b.dataset.critical)||0) - (parseInt(a.dataset.critical)||0);
                    if (sortBy === 'updates')  return (parseInt(b.dataset.updates)||0) - (parseInt(a.dataset.updates)||0);
                    if (sortBy === 'expiry')   return (parseInt(a.dataset.expiry)||9999) - (parseInt(b.dataset.expiry)||9999);
                    if (sortBy === 'name')     return a.dataset.name.localeCompare(b.dataset.name);
                    return 0;
                });
                rows.forEach(function(row) { tbody.appendChild(row); });
            };

            // Initial sort by priority
            window.rphubOpsSort();

            window.rphubOpsShowPlanConfig = function(siteId, siteName, plan) {
                var modal = document.getElementById('rphub-plan-modal');
                var body  = document.getElementById('rphub-plan-modal-body');
                var title = document.getElementById('rphub-plan-modal-title');
                title.textContent = 'Plan ' + plan + ' - ' + siteName;
                body.innerHTML = '<p style="color:#888;">Cargando estado...</p>';
                modal.style.display = 'block';
                jQuery.post(_ajaxUrl, {
                    action: 'rphub_get_plan_status',
                    site_id: siteId,
                    nonce: _ajaxNonce
                }, function(resp) {
                    if (!resp.success) {
                        body.innerHTML = '<p style="color:#d63638;">Error: ' + escHtmlModal(resp.data || 'No se pudo cargar') + '</p>';
                        return;
                    }
                    var d = resp.data;
                    var html = '<table style="width:100%;border-collapse:collapse;margin-bottom:16px;">';
                    d.features.forEach(function(f) {
                        var icon  = f.ok ? '<span style="color:#00a32a;font-weight:700;">OK</span>' : '<span style="color:#d63638;font-weight:700;">--</span>';
                        html += '<tr style="border-top:1px solid #eee;">';
                        html += '<td style="padding:8px 6px;font-size:13px;">' + escHtmlModal(f.label) + '</td>';
                        html += '<td style="padding:8px 6px;text-align:center;width:50px;">' + icon + '</td>';
                        html += '<td style="padding:8px 6px;font-size:11px;color:#666;">' + escHtmlModal(f.detail) + '</td>';
                        html += '</tr>';
                    });
                    html += '</table>';
                    if (d.last_push) {
                        html += '<p style="font-size:11px;color:#888;margin-bottom:12px;">Ultima config enviada: ' + escHtmlModal(d.last_push) + '</p>';
                    }
                    if (d.can_push) {
                        html += '<button class="button button-primary rphub-plan-apply-btn" id="rphub-plan-apply-btn" data-site-id="' + String(siteId) + '">Aplicar features del plan a Care</button>';
                    } else {
                        html += '<p style="color:#b87000;font-size:12px;">Care no conectado - conecta el sitio primero desde la ficha del sitio.</p>';
                    }
                    body.innerHTML = html;
                }).fail(function(xhr) {
                    body.innerHTML = '<p style="color:#d63638;">Error de conexion cargando el plan. HTTP ' + escHtmlModal(String(xhr.status || '')) + '</p>';
                });
            };

            window.rphubOpsApplyPlan = function(siteId, btn) {
                btn.disabled = true;
                btn.textContent = 'Aplicando...';
                jQuery.post(_ajaxUrl, {
                    action: 'rphub_apply_plan_features',
                    site_id: siteId,
                    nonce: _ajaxNonce
                }, function(resp) {
                    if (resp.success) {
                        btn.textContent = 'Aplicado correctamente';
                        btn.style.background = '#00a32a';
                        btn.style.borderColor = '#00a32a';
                    } else {
                        btn.disabled = false;
                        btn.textContent = 'Aplicar features del plan a Care';
                        alert('Error: ' + (resp.data || 'Fallo al aplicar'));
                    }
                }).fail(function(xhr) {
                    btn.disabled = false;
                    btn.textContent = 'Aplicar features del plan a Care';
                    alert('Error de conexion aplicando el plan. HTTP ' + (xhr.status || ''));
                });
            };

            window.rphubForceCarUpdate = function(btn) {
                if (!confirm('Forzar actualizacion de Care en todos los sitios conectados?\n\nSe ejecutara self_update en cada sitio con Care activo.')) return;
                var icon = btn.querySelector('.dashicons');
                if (icon) icon.classList.add('rphub-spinning');
                btn.disabled = true;
                jQuery.post(_ajaxUrl, {
                    action: 'rphub_force_care_update',
                    nonce: _ajaxNonce
                }, function(resp) {
                    if (icon) icon.classList.remove('rphub-spinning');
                    btn.disabled = false;
                    if (resp.success) {
                        var d = resp.data;
                        var msg = 'Actualizacion Care completada.\n' +
                            'OK via API: ' + d.ok + ' sitio(s)\n' +
                            (d.ftp_ok ? 'Recuperados via FTP: ' + d.ftp_ok + ' sitio(s)\n' : '') +
                            'Errores: ' + d.errors + ' sitio(s)' +
                            (d.skipped ? '\nSin Care: ' + d.skipped : '');
                        alert(msg);
                    } else {
                        alert('Error: ' + (resp.data || 'Fallo al forzar actualizacion'));
                    }
                });
            };
        })();
        </script>

        <?php
        // FASE 2-D: CF Onboarding Queue section
        $queue = $this->get_onboarding_queue();
        if (!empty($queue)):
        ?>
        <h2 style="margin-top:30px;">Cola de Onboarding CF</h2>
        <p style="color:#666;margin-bottom:12px;">Dominios con proceso de activacion Cloudflare en curso o con incidencias.
            <button type="button" class="button button-secondary" style="margin-left:12px;" onclick="rphubCfRunTick(this)">Ejecutar ciclo ahora</button>
        </p>
        <table class="wp-list-table widefat fixed striped rphub-ops-table rphub-queue-table">
            <thead>
                <tr>
                    <th style="width:22%;">Dominio</th>
                    <th style="width:11%;">Estado</th>
                    <th style="width:18%;">NS asignados</th>
                    <th style="width:13%;">Zona CF</th>
                    <th style="width:13%;">Actualizado</th>
                    <th style="width:11%;">Error</th>
                    <th style="width:12%;">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($queue as $q):
                $state     = $q['state'];
                $state_map = [
                    'pending'          => ['Pendiente',       'pill-warning'],
                    'running'          => ['Procesando...',    'pill-warning'],
                    'pending_ns'       => ['Esperando NS',    'pill-warning'],
                    'onboarded'        => ['Onboarded',       'pill-ok'],
                    'completed'        => ['Completado',      'pill-ok'],
                    'partial'          => ['Parcial',         'pill-warning'],
                    'error'            => ['Error',           'pill-critical'],
                    'failed'           => ['Fallido',         'pill-critical'],
                    'needs_manual_ns'  => ['NS manual',       'pill-critical'],
                ];
                $label = $state_map[$state][0] ?? ucfirst($state);
                $cls   = $state_map[$state][1] ?? 'pill-warning';
                $ns    = $q['nameservers'] ? implode(', ', (array)json_decode($q['nameservers'], true)) : '-';
                $upd   = $q['updated_at'] ? $this->human_diff($q['updated_at']) : '-';
                $err   = $q['last_error'] ? ('<span title="' . esc_attr($q['last_error']) . '" style="cursor:help;">! ' . esc_html(substr($q['last_error'], 0, 40)) . (strlen($q['last_error']) > 40 ? '...' : '') . '</span>') : '-';
                $row_class = in_array($state, ['error', 'failed', 'needs_manual_ns'], true) ? 'row-critical' : ($state === 'pending_ns' ? 'row-warning' : '');
                $can_retry  = in_array($state, ['error', 'failed', 'partial', 'needs_manual_ns', 'pending_ns'], true);
                $can_manual = !in_array($state, ['needs_manual_ns', 'completed'], true);
                ?>
                <tr class="<?php echo esc_attr($row_class); ?>">
                    <td><strong><?php echo esc_html($q['primary_domain']); ?></strong></td>
                    <td><span class="rphub-alert-pill <?php echo esc_attr($cls); ?>"><?php echo esc_html($label); ?></span></td>
                    <td style="font-size:11px;"><?php echo esc_html($ns); ?></td>
                    <td style="font-size:11px;"><?php echo esc_html($q['zone_id'] ?: '-'); ?></td>
                    <td><?php echo esc_html($upd); ?></td>
                    <td style="font-size:11px;"><?php echo $err; ?></td>
                    <td class="rphub-queue-actions">
                        <?php if ($can_retry): ?>
                            <button type="button" class="button button-small" onclick="rphubCfRetry('<?php echo esc_js($q['primary_domain']); ?>', this)">Reintentar</button>
                        <?php endif; ?>
                        <?php if ($can_manual): ?>
                            <button type="button" class="button button-small" onclick="rphubCfMarkManual('<?php echo esc_js($q['primary_domain']); ?>', this)">Manual</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <script>
        (function(){
            var ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
            var nonce   = <?php echo wp_json_encode(wp_create_nonce('rphub_ajax')); ?>;
            function post(action, data, btn) {
                if (btn) { btn.disabled = true; btn.dataset._t = btn.textContent; btn.textContent = '...'; }
                return jQuery.post(ajaxUrl, Object.assign({action: action, nonce: nonce}, data || {}), function(resp){
                    if (resp && resp.success) { location.reload(); }
                    else {
                        if (btn) { btn.disabled = false; btn.textContent = btn.dataset._t || 'Reintentar'; }
                        alert('Error: ' + ((resp && resp.data) || 'desconocido'));
                    }
                }).fail(function(){
                    if (btn) { btn.disabled = false; btn.textContent = btn.dataset._t || 'Reintentar'; }
                    alert('Error de red');
                });
            }
            window.rphubCfRetry      = function(domain, btn){ post('rphub_cf_retry', {domain: domain}, btn); };
            window.rphubCfMarkManual = function(domain, btn){
                if (!confirm('Marcar ' + domain + ' como gestionado manualmente? El motor no volvera a intentarlo.')) return;
                post('rphub_cf_mark_manual', {domain: domain}, btn);
            };
            window.rphubCfRunTick    = function(btn){
                if (!confirm('Ejecutar ciclo completo de onboarding ahora?')) return;
                post('rphub_cf_run_tick', {}, btn);
            };
        })();
        </script>
        <?php endif; ?>
        <?php
    }

    private function renderSummaryChips(array $stats): void {
        ?>
        <div class="rphub-ops-summary">
            <div class="rphub-ops-chip rphub-ops-chip-critical">
                <strong><?php echo esc_html($stats['sites_critical']); ?></strong>
                <span>criticos SA</span>
            </div>
            <div class="rphub-ops-chip rphub-ops-chip-warning">
                <strong><?php echo esc_html($stats['sites_warning']); ?></strong>
                <span>con avisos</span>
            </div>
            <div class="rphub-ops-chip">
                <strong><?php echo esc_html($stats['total_pending_updates']); ?></strong>
                <span>actualizaciones pendientes</span>
            </div>
            <?php
            $ns_class     = $stats['sites_pending_ns']    > 0 ? 'rphub-ops-chip-warning' : '';
            $expiry_class = $stats['sites_expiring_soon'] > 0 ? 'rphub-ops-chip-critical' : '';
            ?>
            <div class="rphub-ops-chip <?php echo esc_attr($ns_class); ?>">
                <strong><?php echo esc_html($stats['sites_pending_ns']); ?></strong>
                <span>NS pendientes</span>
            </div>
            <div class="rphub-ops-chip <?php echo esc_attr($expiry_class); ?>">
                <strong><?php echo esc_html($stats['sites_expiring_soon']); ?></strong>
                <span>renuevan &lt;30d</span>
            </div>
            <div class="rphub-ops-chip">
                <strong><?php echo esc_html($stats['sites_no_audit']); ?></strong>
                <span>sin auditoria SA</span>
            </div>
        </div>
        <?php
    }

    private function renderFilterBar(): void {
        ?>
        <div class="rphub-ops-filters">
            <label>
                <input type="checkbox" id="rphub-ops-only-issues" onchange="rphubOpsFilter()">
                Solo sitios con problemas
            </label>
            <label style="margin-left:16px;">
                Ordenar por:
                <select id="rphub-ops-sort" onchange="rphubOpsSort()">
                    <option value="priority">Prioridad (defecto)</option>
                    <option value="score">Score SA (menor primero)</option>
                    <option value="critical">Issues criticos</option>
                    <option value="updates">Actualizaciones pendientes</option>
                    <option value="expiry">Expiracion dominio</option>
                    <option value="name">Nombre</option>
                </select>
            </label>
        </div>
        <?php
    }

    /**
     * Load CF onboarding queue Ã¢â‚¬â€ domains with active/failed/pending states.
     */
    private function get_onboarding_queue(): array {
        global $wpdb;
        $ob_table = $wpdb->prefix . 'dominios_reseller_cf_onboarding';
        if (!$wpdb->get_var("SHOW TABLES LIKE '$ob_table'")) {
            return [];
        }
        return $wpdb->get_results(
            "SELECT primary_domain, zone_id, state, nameservers, last_error, updated_at
             FROM $ob_table
             WHERE state NOT IN ('none', 'completed')
             ORDER BY
               FIELD(state, 'error','failed','needs_manual_ns','pending_ns','partial','running','pending','onboarded') ASC,
               updated_at DESC
             LIMIT 50",
            ARRAY_A
        ) ?: [];
    }

    /**
     * Load sites with SA scores + DR data (NS status, domain expiry).
     */
    private function get_attention_data(): array {
        global $wpdb;
        $t_sites = $wpdb->prefix . 'rphub_sites';
        $t_meta  = $wpdb->prefix . 'rphub_site_meta';

        $sites = $wpdb->get_results(
            "SELECT id, name, url, plan, status, client_name,
                    COALESCE(updates_available, 0) AS updates_available,
                    COALESCE(health_score, 0) AS health_score,
                    last_check
             FROM $t_sites
             WHERE status != 'deleted'
             ORDER BY name ASC",
            ARRAY_A
        );

        if (empty($sites)) {
            return ['sites' => [], 'stats' => $this->empty_stats()];
        }

        // Batch load meta
        $ids_sql   = implode(',', array_map('intval', array_column($sites, 'id')));
        $meta_rows = $wpdb->get_results(
            "SELECT site_id, meta_key, meta_value
             FROM $t_meta
             WHERE site_id IN ($ids_sql)
               AND meta_key IN (
                   'sa_global_score','sa_critical_issues','sa_warning_issues',
                   'sa_last_audit','pending_updates_count',
                   'cf_score','seo_score','perf_score',
                   'ssl_type','cf_zone_status','seo_regression'
               )",
            ARRAY_A
        ) ?: [];
        $meta = [];
        foreach ($meta_rows as $row) {
            $meta[(int)$row['site_id']][$row['meta_key']] = $row['meta_value'];
        }

        // Batch load DR data (CF zones + onboarding + domain dates)
        $dr = $this->load_dr_batch($sites);

        $stats = [
            'sites_critical'        => 0,
            'sites_warning'         => 0,
            'total_pending_updates' => 0,
            'sites_no_audit'        => 0,
            'sites_pending_ns'      => 0,
            'sites_expiring_soon'   => 0,
        ];

        foreach ($sites as &$s) {
            $id = (int) $s['id'];
            $sm = $meta[$id] ?? [];
            $d  = $dr[$id]   ?? [];

            $s['sa_global_score']       = (int)($sm['sa_global_score']    ?? 0);
            $s['sa_critical_issues']    = (int)($sm['sa_critical_issues'] ?? 0);
            $s['sa_warning_issues']     = (int)($sm['sa_warning_issues']  ?? 0);
            $s['sa_last_audit']         = $sm['sa_last_audit'] ?? '';
            $s['pending_updates_count'] = (int)($sm['pending_updates_count'] ?? $s['updates_available']);
            $s['cf_score']              = (int)($sm['cf_score']   ?? 0);
            $s['seo_score']             = (int)($sm['seo_score']  ?? 0);
            $s['perf_score']            = (int)($sm['perf_score'] ?? 0);
            $s['ssl_type']              = $sm['ssl_type']       ?? '';
            $s['cf_zone_status']        = $sm['cf_zone_status'] ?? $d['cf_zone_status'] ?? '';
            $s['cf_zone_id']            = $d['cf_zone_id']        ?? '';
            $s['seo_regression']        = (int)($sm['seo_regression'] ?? 0);

            // NS state
            $ob_state        = $d['cf_onboarding_state'] ?? '';
            $s['pending_ns'] = ($ob_state === 'pending_ns')
                || (!empty($s['cf_zone_id']) && $s['cf_zone_status'] !== 'active');
            $s['ns_error']   = in_array($ob_state, ['error', 'failed', 'needs_manual_ns'], true);
            if ($s['ns_error']) $s['pending_ns'] = false; // ns_error is more specific

            // Domain expiry Ã¢â‚¬â€ validez is a DATE (Y-m-d) set to startdate + 1 year
            $validez   = $d['validez'] ?? '';
            $days_left = null;
            $expiry_str = '';
            if ($validez) {
                $expiry_ts  = strtotime($validez);
                if ($expiry_ts) {
                    $days_left  = (int) ceil(($expiry_ts - time()) / 86400);
                    $expiry_str = date('d/m/Y', $expiry_ts);
                }
            }
            $s['days_to_expiry'] = $days_left;
            $s['domain_expiry']  = $expiry_str;

            // Stats
            if ($s['sa_critical_issues'] > 0) $stats['sites_critical']++;
            elseif ($s['sa_warning_issues'] > 0 || $s['pending_updates_count'] > 2) $stats['sites_warning']++;

            $stats['total_pending_updates'] += $s['pending_updates_count'];
            if (!$s['sa_last_audit']) $stats['sites_no_audit']++;
            if ($s['pending_ns'] || $s['ns_error']) $stats['sites_pending_ns']++;
            if ($days_left !== null && $days_left <= 30) $stats['sites_expiring_soon']++;
        }
        unset($s);

        return ['sites' => $sites, 'stats' => $stats];
    }

    /**
     * Batch-load CF zones, onboarding state, and domain dates from DR tables.
     * Returns array keyed by site_id.
     */
    private function load_dr_batch(array $sites): array {
        global $wpdb;

        $data = array_fill_keys(array_map('intval', array_column($sites, 'id')), []);

        // site_id Ã¢â€ â€™ bare domain mapping
        $id_to_domain = [];
        foreach ($sites as $s) {
            $host = parse_url($s['url'], PHP_URL_HOST) ?: $s['url'];
            $host = strtolower(preg_replace('/^www\./i', '', $host));
            $id_to_domain[(int)$s['id']] = $host;
        }
        $domains = array_unique(array_values($id_to_domain));
        if (empty($domains)) return $data;

        $esc    = array_map('esc_sql', $domains);
        $in_sql = "'" . implode("','", $esc) . "'";

        // 1. Domain dates from dominios_reseller
        $dr_table = $wpdb->prefix . 'dominios_reseller';
        if ($wpdb->get_var("SHOW TABLES LIKE '$dr_table'")) {
            $rows = $wpdb->get_results(
                "SELECT domain, primary_domain, validez
                 FROM $dr_table WHERE domain IN ($in_sql) OR primary_domain IN ($in_sql)",
                ARRAY_A
            ) ?: [];
            $by_domain = [];
            foreach ($rows as $r) {
                $key = $r['domain'] ?: $r['primary_domain'];
                $by_domain[$key] = $r;
            }
            foreach ($id_to_domain as $id => $bare) {
                if (isset($by_domain[$bare])) {
                    $data[$id]['validez'] = $by_domain[$bare]['validez'] ?? '';
                }
            }
        }

        // 2. CF zone status
        $zone_table = $wpdb->prefix . 'dominios_reseller_cf_zones';
        if ($wpdb->get_var("SHOW TABLES LIKE '$zone_table'")) {
            $rows = $wpdb->get_results(
                "SELECT name, zone_id, status FROM $zone_table WHERE name IN ($in_sql) AND deleted_at IS NULL",
                ARRAY_A
            ) ?: [];
            $by_domain = array_column($rows, null, 'name');
            foreach ($id_to_domain as $id => $bare) {
                if (isset($by_domain[$bare])) {
                    $data[$id]['cf_zone_id']     = $by_domain[$bare]['zone_id'] ?? '';
                    $data[$id]['cf_zone_status'] = $by_domain[$bare]['status']  ?? '';
                }
            }
        }

        // 3. CF onboarding state
        $ob_table = $wpdb->prefix . 'dominios_reseller_cf_onboarding';
        if ($wpdb->get_var("SHOW TABLES LIKE '$ob_table'")) {
            $rows = $wpdb->get_results(
                "SELECT primary_domain, state FROM $ob_table WHERE primary_domain IN ($in_sql)",
                ARRAY_A
            ) ?: [];
            $by_domain = array_column($rows, 'state', 'primary_domain');
            foreach ($id_to_domain as $id => $bare) {
                if (isset($by_domain[$bare])) {
                    $data[$id]['cf_onboarding_state'] = $by_domain[$bare];
                }
            }
        }

        return $data;
    }

    private function empty_stats(): array {
        return [
            'sites_critical'        => 0,
            'sites_warning'         => 0,
            'total_pending_updates' => 0,
            'sites_no_audit'        => 0,
            'sites_pending_ns'      => 0,
            'sites_expiring_soon'   => 0,
        ];
    }

    private function mini_score_bar(string $label, int $score): string {
        if ($score === 0) {
            return "<div class=\"rphub-mini-score-row\"><span class=\"ms-label\">{$label}</span><span class=\"rphub-no-data\">-</span></div>";
        }
        $color = $score >= 80 ? '#2ea043' : ($score >= 50 ? '#d4a017' : '#d63638');
        $w     = max(2, $score);
        return "<div class=\"rphub-mini-score-row\">"
             . "<span class=\"ms-label\">{$label}</span>"
             . "<div class=\"rphub-mini-bar-wrap\"><div class=\"rphub-mini-bar\" style=\"width:{$w}%;background:{$color};\"></div></div>"
             . "<span class=\"ms-val\">{$score}</span>"
             . "</div>";
    }

    private function ssl_badge(array $s): string {
        $type = $s['ssl_type']      ?? '';
        $cf   = $s['cf_zone_status'] ?? '';
        if ($cf === 'active' || $type === 'cf')  return '<span class="rphub-ssl-badge-ops ssl-ops-cf"  title="Cloudflare SSL">CF</span>';
        if ($type === 'le')                      return '<span class="rphub-ssl-badge-ops ssl-ops-le"  title="Let\'s Encrypt">LE</span>';
        if ($type === 'autossl')                 return '<span class="rphub-ssl-badge-ops ssl-ops-as"  title="AutoSSL">AS</span>';
        if ($type === 'paid')                    return '<span class="rphub-ssl-badge-ops ssl-ops-paid" title="SSL pago">$</span>';
        if (isset($s['url']) && strpos($s['url'], 'https://') === 0) {
            return '<span class="rphub-ssl-badge-ops ssl-ops-ok" title="HTTPS">SSL OK</span>';
        }
        return '<span class="rphub-ssl-badge-ops ssl-ops-warn" title="Sin SSL">SSL !</span>';
    }

    private function human_diff(string $date): string {
        if (!$date) return '-';
        $ts = strtotime($date);
        if (!$ts) return $date;
        $diff = time() - $ts;
        if ($diff < 2592000) return 'hace ' . round($diff / 86400) . ' dias';
        if ($diff < 2592000) return 'hace ' . round($diff / 86400) . ' dias';
        if ($diff < 2592000) return 'hace ' . round($diff / 86400) . ' dias';
        return date('d/m/Y', $ts);
    }
}
