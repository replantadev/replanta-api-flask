/**
 * SAP Woo Control Center — Admin JS
 */
(function ($) {
    'use strict';

    const AJAX = sapwcc.ajax_url;
    const NONCE = sapwcc.nonce;

    // ─── Tabs ────────────────────────────────────────────────────────────────

    $('.sapwcc-tabs .nav-tab').on('click', function (e) {
        e.preventDefault();
        const tab = $(this).data('tab');
        $('.sapwcc-tabs .nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.sapwcc-tab-content').hide();
        $('#tab-' + tab).show();
        history.replaceState(null, '', '?page=sapwcc&tab=' + tab);
    });

    // ─── Add Site ────────────────────────────────────────────────────────────

    $('#sapwcc-add-site-form').on('submit', function (e) {
        e.preventDefault();
        const $form = $(this);
        const $btn = $form.find('button[type="submit"]');

        $btn.prop('disabled', true).text('Añadiendo...');

        $.post(AJAX, {
            action: 'sapwcc_add_site',
            nonce: NONCE,
            label: $form.find('[name="label"]').val(),
            url: $form.find('[name="url"]').val(),
            secret: $form.find('[name="secret"]').val()
        }, function (res) {
            if (res.success) {
                location.reload();
            } else {
                alert('Error: ' + (res.data || 'Desconocido'));
                $btn.prop('disabled', false).text('Añadir');
            }
        }).fail(function () {
            alert('Error de conexión');
            $btn.prop('disabled', false).text('Añadir');
        });
    });

    // ─── Remove Site ─────────────────────────────────────────────────────────

    $(document).on('click', '.sapwcc-remove-btn', function () {
        const key = $(this).data('key');
        if (!confirm('¿Eliminar este sitio del Control Center?')) return;

        $.post(AJAX, { action: 'sapwcc_remove_site', nonce: NONCE, site_key: key }, function (res) {
            if (res.success) location.reload();
            else alert('Error: ' + (res.data || ''));
        });
    });

    // ─── Health Check (single) ───────────────────────────────────────────────

    $(document).on('click', '.sapwcc-check-btn', function () {
        const key = $(this).data('key');
        const $card = $('[data-site-key="' + key + '"]');
        const $btn = $(this);

        $btn.prop('disabled', true);
        $card.addClass('sapwcc-loading');
        $btn.html('<span class="sapwcc-spinner"></span> Checking...');

        $.post(AJAX, { action: 'sapwcc_check_health', nonce: NONCE, site_key: key }, function () {
            // Reload to show updated data
            location.reload();
        }).fail(function () {
            $card.removeClass('sapwcc-loading');
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Check');
            alert('Error de conexión');
        });
    });

    // ─── Check All ───────────────────────────────────────────────────────────

    $('#sapwcc-check-all').on('click', function () {
        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="sapwcc-spinner"></span> Checking all...');
        $('.sapwcc-site-card').addClass('sapwcc-loading');

        $.post(AJAX, { action: 'sapwcc_check_health', nonce: NONCE, site_key: 'all' }, function () {
            location.reload();
        }).fail(function () {
            alert('Error de conexión');
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Check All');
            $('.sapwcc-site-card').removeClass('sapwcc-loading');
        });
    });

    // ─── Flags: Working state (v2 schema) ───────────────────────────────────

    var workingFlags = JSON.parse(JSON.stringify(sapwcc.flags || {}));
    var currentSiteId = null;

    /**
     * Save the current site's UI state into workingFlags.
     */
    function saveSiteToWorking(siteId) {
        if (!siteId) return;
        if (!workingFlags.sites) workingFlags.sites = {};

        var plan = $('#sapwcc-site-plan').val() || 'starter';
        var overrides = {};

        // Collect kill-switch overrides
        $('#sapwcc-killswitch-overrides .sapwcc-override-row').each(function () {
            var key = $(this).data('key');
            if ($(this).find('.sapwcc-override-active').is(':checked')) {
                overrides[key] = $(this).find('.sapwcc-override-toggle input').is(':checked');
            }
        });

        // Collect plan feature overrides
        $('#sapwcc-planfeature-overrides .sapwcc-override-row').each(function () {
            var key = $(this).data('key');
            if ($(this).find('.sapwcc-override-active').is(':checked')) {
                overrides[key] = $(this).find('.sapwcc-override-toggle input').is(':checked');
            }
        });

        var siteData = { plan: plan };
        if (Object.keys(overrides).length > 0) {
            siteData.overrides = overrides;
        }
        workingFlags.sites[siteId] = siteData;
    }

    /**
     * Load a site's data from workingFlags into the UI.
     */
    function loadSiteFromWorking(siteId) {
        var siteData = (workingFlags.sites || {})[siteId] || {};
        var plan = siteData.plan || 'starter';
        var overrides = siteData.overrides || {};

        // Set plan selector
        $('#sapwcc-site-plan').val(plan);

        // Kill-switch overrides
        var globalFlags = workingFlags.global || {};
        $('#sapwcc-killswitch-overrides .sapwcc-override-row').each(function () {
            var key = $(this).data('key');
            var hasOverride = overrides.hasOwnProperty(key);
            var globalVal = globalFlags[key] !== undefined ? globalFlags[key] : true;
            $(this).find('.sapwcc-override-active').prop('checked', hasOverride);
            $(this).find('.sapwcc-override-toggle input')
                .prop('checked', hasOverride ? overrides[key] : globalVal)
                .prop('disabled', !hasOverride);
        });

        // Plan feature overrides
        updatePlanDefaults(plan, overrides);
    }

    /**
     * Update plan feature override UI to reflect the selected plan's defaults.
     */
    function updatePlanDefaults(planKey, overrides) {
        overrides = overrides || {};
        var planFeats = (workingFlags.plans || {})[planKey] || {};

        $('#sapwcc-planfeature-overrides .sapwcc-override-row').each(function () {
            var key = $(this).data('key');
            var planDefault = planFeats[key] !== undefined ? planFeats[key] : false;
            var hasOverride = overrides.hasOwnProperty(key);

            $(this).find('.sapwcc-override-active').prop('checked', hasOverride);
            $(this).find('.sapwcc-override-toggle input')
                .prop('checked', hasOverride ? overrides[key] : planDefault)
                .prop('disabled', !hasOverride);
            $(this).find('.sapwcc-plan-default').html(
                planDefault
                    ? '<span class="sapwcc-ok">Incluido en plan</span>'
                    : '<span class="sapwcc-muted">No incluido</span>'
            );
        });
    }

    /**
     * Build the complete flags.json v2 from workingFlags + UI state.
     */
    function buildFlagsJson() {
        // Save current site to working state
        if (currentSiteId) saveSiteToWorking(currentSiteId);

        // Update global kill-switches from UI
        workingFlags.global = {};
        $('input[data-scope="global"]').each(function () {
            workingFlags.global[$(this).data('flag')] = $(this).is(':checked');
        });

        // Update plan matrix from UI
        workingFlags.plans = {};
        var plans = sapwcc.valid_plans || ['starter', 'business', 'enterprise'];
        $.each(plans, function (i, plan) {
            workingFlags.plans[plan] = {};
            $('input[data-scope="plan-matrix"][data-plan="' + plan + '"]').each(function () {
                workingFlags.plans[plan][$(this).data('feature')] = $(this).is(':checked');
            });
        });

        // Update notices from UI
        workingFlags.notices = [];
        $('.sapwcc-notice-item').each(function () {
            var $item = $(this);
            var notice = {
                id: $item.data('id') || 'notice-' + $item.data('index'),
                type: $item.find('.sapwcc-notice-type').text().trim().toLowerCase(),
                message: $item.find('.sapwcc-notice-msg').text().trim(),
                dismissible: true
            };
            var $expEl = $item.find('.sapwcc-notice-exp');
            if ($expEl.length && $expEl.text().trim()) {
                notice.expires = $expEl.text().replace('exp:', '').trim();
            }
            var $targetEl = $item.find('.sapwcc-notice-target');
            if ($targetEl.length && $targetEl.text().trim()) {
                var targets = $targetEl.text().replace('solo:', '').trim();
                notice.target_sites = targets.split(',').map(function (s) { return s.trim(); });
            }
            workingFlags.notices.push(notice);
        });

        // Metadata
        workingFlags._schema = 'sapwc-flags-v2';
        workingFlags._updated = new Date().toISOString().split('T')[0];

        return workingFlags;
    }

    // ─── Flags: Save ─────────────────────────────────────────────────────────

    $('#sapwcc-save-flags').on('click', function () {
        var $btn = $(this);
        var flags = buildFlagsJson();

        $btn.prop('disabled', true).html('<span class="sapwcc-spinner"></span> Guardando...');

        $.post(AJAX, {
            action: 'sapwcc_save_flags',
            nonce: NONCE,
            flags_json: JSON.stringify(flags)
        }, function (res) {
            if (res.success) {
                $btn.html('<span class="dashicons dashicons-yes"></span> Guardado!');
                setTimeout(function () {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Guardar flags.json');
                }, 2000);
            } else {
                alert('Error: ' + (res.data || ''));
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Guardar flags.json');
            }
        });
    });

    // ─── Flags: Git Push ─────────────────────────────────────────────────────

    $('#sapwcc-git-push').on('click', function () {
        var $btn = $(this);
        if (!confirm('Publicar flags.json en GitHub Pages?\n\nEsto hara git commit + push al repo sapwoo.')) return;

        $btn.prop('disabled', true).html('<span class="sapwcc-spinner"></span> Pushing...');
        $('#sapwcc-git-output').hide();

        $.post(AJAX, {
            action: 'sapwcc_git_push_flags',
            nonce: NONCE
        }, function (res) {
            if (res.success) {
                $btn.html('<span class="dashicons dashicons-yes"></span> Publicado!');
                $('#sapwcc-git-output').text(res.data).show();
                setTimeout(function () {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-cloud-upload"></span> Publicar');
                }, 3000);
            } else {
                alert('Error: ' + (res.data || ''));
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-cloud-upload"></span> Publicar');
            }
        }).fail(function () {
            alert('Error de conexion');
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-cloud-upload"></span> Publicar');
        });
    });

    // ─── Flags: Preview ──────────────────────────────────────────────────────

    var previewOpen = false;

    $('#sapwcc-preview-json').on('click', function () {
        previewOpen = !previewOpen;
        if (previewOpen) {
            var flags = buildFlagsJson();
            $('#sapwcc-json-output').text(JSON.stringify(flags, null, 2));
            $('#sapwcc-json-preview').slideDown(200);
        } else {
            $('#sapwcc-json-preview').slideUp(200);
        }
    });

    // ─── Flags: Site selector ────────────────────────────────────────────────

    $('#sapwcc-site-selector').on('change', function () {
        var siteId = $(this).val();

        // Save current site before switching
        if (currentSiteId) saveSiteToWorking(currentSiteId);

        currentSiteId = siteId || null;

        if (!siteId) {
            $('#sapwcc-site-config').hide();
            return;
        }

        loadSiteFromWorking(siteId);
        $('#sapwcc-site-config').show();
    });

    // ─── Flags: Plan selector change ─────────────────────────────────────────

    $('#sapwcc-site-plan').on('change', function () {
        var plan = $(this).val();
        // Collect current overrides before updating defaults
        var overrides = {};
        $('#sapwcc-planfeature-overrides .sapwcc-override-row').each(function () {
            var key = $(this).data('key');
            if ($(this).find('.sapwcc-override-active').is(':checked')) {
                overrides[key] = $(this).find('.sapwcc-override-toggle input').is(':checked');
            }
        });
        updatePlanDefaults(plan, overrides);
    });

    // ─── Flags: Override toggle enable/disable ───────────────────────────────

    $(document).on('change', '.sapwcc-override-active', function () {
        var $row = $(this).closest('.sapwcc-override-row');
        var $toggle = $row.find('.sapwcc-override-toggle input');
        $toggle.prop('disabled', !$(this).is(':checked'));
    });

    // ─── Notices: Add ────────────────────────────────────────────────────────

    $('#sapwcc-add-notice').on('click', function () {
        var type = $('#sapwcc-notice-type').val();
        var msg = $('#sapwcc-notice-msg').val().trim();
        var exp = $('#sapwcc-notice-expires').val();

        if (!msg) { alert('Escribe un mensaje.'); return; }

        var index = $('.sapwcc-notice-item').length;
        var html = '<div class="sapwcc-notice-item" data-index="' + index + '">';
        html += '<span class="sapwcc-notice-type sapwcc-notice-type--' + type + '">' + type.toUpperCase() + '</span>';
        html += '<span class="sapwcc-notice-msg">' + $('<span>').text(msg).html() + '</span>';
        if (exp) {
            html += '<small class="sapwcc-muted sapwcc-notice-exp">exp: ' + $('<span>').text(exp).html() + '</small>';
        }
        html += '<button class="button-link sapwcc-remove-notice" data-index="' + index + '" title="Eliminar"><span class="dashicons dashicons-no-alt"></span></button>';
        html += '</div>';

        var $list = $('#sapwcc-notices-list');
        $list.children('.sapwcc-muted').remove();
        $list.append(html);

        $('#sapwcc-notice-msg').val('');
        $('#sapwcc-notice-expires').val('');
    });

    // ─── Notices: Remove ─────────────────────────────────────────────────────

    $(document).on('click', '.sapwcc-remove-notice', function () {
        $(this).closest('.sapwcc-notice-item').remove();
    });

    // ─── Config: Save Settings ───────────────────────────────────────────────

    $('#sapwcc-save-settings').on('click', function () {
        var $btn = $(this);
        var tokenVal = $('#sapwcc-github-token').val() || '';
        var pathVal = $('#sapwcc-flags-path').val() || '';
        var ccIpVal = $('#sapwcc-control-center-ip').val() || '';

        $btn.prop('disabled', true).text('Guardando...');

        $.post(AJAX, {
            action: 'sapwcc_save_settings',
            nonce: NONCE,
            flags_path: pathVal,
            github_token: tokenVal,
            control_center_ip: ccIpVal
        }, function (res) {
            if (res.success) {
                $btn.text(res.data || 'Guardado!');
                setTimeout(function () {
                    $btn.prop('disabled', false).text('Guardar configuracion');
                }, 3000);
            } else {
                alert('Error: ' + (res.data || ''));
                $btn.prop('disabled', false).text('Guardar configuracion');
            }
        }).fail(function(jqXHR, textStatus) {
            alert('AJAX fail: ' + textStatus + ' | token.length=' + tokenVal.length);
            $btn.prop('disabled', false).text('Guardar configuracion');
        });
    });

    // ─── Quick Actions: Remote proxy ─────────────────────────────────────────

    var currentQuickKey = null;

    function remoteAction(siteKey, endpoint, method, body, callback) {
        $.post(AJAX, {
            action: 'sapwcc_remote_action',
            nonce: NONCE,
            site_key: siteKey,
            endpoint: endpoint,
            method: method,
            body: body ? JSON.stringify(body) : ''
        }, function (res) {
            callback(res);
        }).fail(function () {
            callback({ success: false, data: 'Error de conexión' });
        });
    }

    $(document).on('click', '.sapwcc-quick-action', function () {
        var key = $(this).data('key');
        var act = $(this).data('action');
        var $btn = $(this);
        var siteLabel = $btn.closest('.sapwcc-site-card').find('.sapwcc-card-header h3').text().trim();

        currentQuickKey = key;

        if (act === 'logs') {
            // Open logs modal.
            $('#sapwcc-logs-modal-title').text('Logs — ' + siteLabel);
            $('#sapwcc-logs-modal').data('site-key', key).show();
            loadRemoteLogs(key);
            return;
        }

        if (act === 'clear-cache') {
            if (!confirm('¿Limpiar cache en ' + siteLabel + '?')) return;
            $btn.prop('disabled', true);
            remoteAction(key, 'control/clear-cache', 'POST', {}, function (res) {
                $btn.prop('disabled', false);
                if (res.success) {
                    alert('✓ ' + (res.data.message || 'Cache limpiada.'));
                } else {
                    alert('✗ Error: ' + (typeof res.data === 'string' ? res.data : JSON.stringify(res.data)));
                }
            });
            return;
        }

        if (act === 'run-cron') {
            // Open cron selector modal.
            $('#sapwcc-cron-site-label').text(siteLabel);
            $('#sapwcc-cron-modal').data('site-key', key).show();
            $('#sapwcc-cron-output').hide();
            return;
        }

        if (act === 'maintenance') {
            var enable = confirm('¿Activar modo mantenimiento en ' + siteLabel + '?\n\n(Cancelar = Desactivar mantenimiento)');
            $btn.prop('disabled', true);
            remoteAction(key, 'control/maintenance', 'POST', { enable: enable }, function (res) {
                $btn.prop('disabled', false);
                if (res.success) {
                    alert('✓ ' + (res.data.message || 'OK'));
                } else {
                    alert('✗ Error: ' + (typeof res.data === 'string' ? res.data : JSON.stringify(res.data)));
                }
            });
            return;
        }

        if (act === 'update-check') {
            $btn.prop('disabled', true);
            remoteAction(key, 'control/update-check', 'GET', {}, function (res) {
                $btn.prop('disabled', false);
                if (res.success) {
                    var d = res.data;
                    var latest = sapwcc.latest_version || '';
                    if (d.update_available) {
                        alert('Actualización disponible: v' + d.current_version + ' → v' + d.available_version);
                    } else if (!d.available_version && latest && d.current_version !== latest) {
                        alert('⚠ Sitio en v' + d.current_version + ' — última release: v' + latest +
                            '.\nPUC no detectó el update (transient cacheado). Opciones:\n' +
                            '1) WP Admin → Escritorio → Actualizaciones → "Volver a comprobar"\n' +
                            '2) Esperar hasta 12h al check automático');
                    } else {
                        alert('✓ Al día: v' + d.current_version);
                    }
                } else {
                    alert('✗ Error: ' + (typeof res.data === 'string' ? res.data : JSON.stringify(res.data)));
                }
            });
            return;
        }

        if (act === 'update') {
            var currentV = $(this).data('current');
            var latestV  = $(this).data('latest');
            var label    = $(this).data('label') || siteLabel || key;

            if (!confirm(
                'Actualizar SAP Woo Suite en "' + label + '"?\n\n' +
                'Versión instalada:   v' + currentV + '\n' +
                'Versión disponible:  v' + latestV + '\n\n' +
                'Esta operación reemplazará los archivos del plugin en el servidor remoto.'
            )) return;

            $btn.prop('disabled', true).html('<span class="sapwcc-spinner"></span> Actualizando...');

            remoteAction(key, 'control/update', 'POST', {}, function (res) {
                if (res.success) {
                    var d = res.data || {};
                    alert('✓ ' + (d.message || 'Actualización completada.'));
                    location.reload();
                } else {
                    var errMsg = (typeof res.data === 'string') ? res.data
                        : (res.data && res.data.response && res.data.response.message) ? res.data.response.message
                        : JSON.stringify(res.data);
                    alert('✗ Error: ' + errMsg);
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-update-alt"></span> Actualizar');
                }
            });
            return;
        }
    });

    // ─── Rotate Secret ───────────────────────────────────────────────────────

    $(document).on('click', '.sapwcc-rotate-secret-btn', function () {
        var $btn  = $(this);
        var key   = $btn.data('key');
        var label = $btn.data('label') || key;

        if (!confirm(
            'Rotar X-SAPWC-Secret en "' + label + '"?\n\n' +
            'Se generará un nuevo secret en el sitio remoto y se guardará automáticamente aquí.\n' +
            'El secret anterior quedará inválido de inmediato.'
        )) return;

        $btn.prop('disabled', true).html('<span class="sapwcc-spinner"></span> Rotando...');

        remoteAction(key, 'control/rotate-secret', 'POST', {}, function (res) {
            if (res.success) {
                alert('✓ Secret rotado correctamente. El nuevo valor ha sido guardado en el Control Center.');
            } else {
                var errMsg = (typeof res.data === 'string') ? res.data : JSON.stringify(res.data);
                alert('✗ Error al rotar secret: ' + errMsg);
            }
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-lock"></span> Rotar Secret');
        });
    });

    // ─── Remote Logs Modal ───────────────────────────────────────────────────

    function loadRemoteLogs(siteKey) {
        var level = $('#sapwcc-logs-level').val();
        var limit = $('#sapwcc-logs-limit').val();
        var $body = $('#sapwcc-logs-body');

        $body.html('<tr><td colspan="5" class="sapwcc-muted"><span class="sapwcc-spinner"></span> Cargando logs...</td></tr>');

        remoteAction(siteKey, 'control/logs', 'GET', { level: level, limit: limit }, function (res) {
            if (!res.success) {
                $body.html('<tr><td colspan="5" class="sapwcc-muted">Error: ' + (typeof res.data === 'string' ? res.data : JSON.stringify(res.data)) + '</td></tr>');
                return;
            }

            var logs = res.data.logs || [];
            var total = res.data.total || 0;
            $('#sapwcc-logs-total').text(total + ' entradas totales');

            if (logs.length === 0) {
                $body.html('<tr><td colspan="5" class="sapwcc-muted">Sin entradas.</td></tr>');
                return;
            }

            var html = '';
            $.each(logs, function (i, row) {
                var statusClass = 'sapwcc-muted';
                if (row.status === 'error') statusClass = 'sapwcc-fail';
                else if (row.status === 'warning') statusClass = 'sapwcc-outdated';
                else if (row.status === 'success') statusClass = 'sapwcc-ok';

                html += '<tr>';
                html += '<td><code style="font-size:11px;">' + $('<span>').text(row.created_at || '').html() + '</code></td>';
                html += '<td><span class="' + statusClass + '" style="font-size:11px;font-weight:600;text-transform:uppercase;">' + $('<span>').text(row.status || '').html() + '</span></td>';
                html += '<td>' + $('<span>').text(row.action || '').html() + '</td>';
                html += '<td>' + $('<span>').text(row.order_id || '—').html() + '</td>';
                html += '<td class="sapwcc-log-msg">' + $('<span>').text(row.message || '').html() + '</td>';
                html += '</tr>';
            });

            $body.html(html);
        });
    }

    $('#sapwcc-logs-refresh').on('click', function () {
        var key = $('#sapwcc-logs-modal').data('site-key');
        if (key) loadRemoteLogs(key);
    });

    $('#sapwcc-logs-level, #sapwcc-logs-limit').on('change', function () {
        var key = $('#sapwcc-logs-modal').data('site-key');
        if (key) loadRemoteLogs(key);
    });

    // ─── Cron Modal ──────────────────────────────────────────────────────────

    $(document).on('click', '.sapwcc-run-cron-btn', function () {
        var hook = $(this).data('hook');
        var key = $('#sapwcc-cron-modal').data('site-key');
        var $btn = $(this);
        var $output = $('#sapwcc-cron-output');

        $btn.prop('disabled', true);
        $output.text('Ejecutando ' + hook + '...').show();

        remoteAction(key, 'control/run-cron', 'POST', { hook: hook }, function (res) {
            $btn.prop('disabled', false);
            if (res.success) {
                $output.text('✓ ' + (res.data.message || 'OK'));
            } else {
                $output.text('✗ Error: ' + (typeof res.data === 'string' ? res.data : JSON.stringify(res.data)));
            }
        });
    });

    // ─── Modal Close ─────────────────────────────────────────────────────────

    $(document).on('click', '.sapwcc-modal-close', function () {
        $(this).closest('.sapwcc-modal').hide();
    });

    $(document).on('click', '.sapwcc-modal', function (e) {
        if ($(e.target).is('.sapwcc-modal')) {
            $(this).hide();
        }
    });

    // ─── Client Metadata: Toggle & Save ──────────────────────────────────────

    $(document).on('click', '.sapwcc-toggle-meta', function () {
        var key = $(this).data('key');
        $('#sapwcc-meta-' + key).slideToggle(200);
    });

    $(document).on('click', '.sapwcc-save-meta', function () {
        var key = $(this).data('key');
        var $form = $('#sapwcc-meta-' + key);
        var $btn = $(this);
        var data = { action: 'sapwcc_update_site_meta', nonce: NONCE, site_key: key };

        $form.find('.sapwcc-meta-field').each(function () {
            data[$(this).data('field')] = $(this).val();
        });

        $btn.prop('disabled', true).text('Guardando...');

        $.post(AJAX, data, function (res) {
            if (res.success) {
                $btn.text('✓ Guardado!');
                setTimeout(function () {
                    $btn.prop('disabled', false).text('Guardar datos');
                }, 1500);
            } else {
                alert('Error: ' + (res.data || ''));
                $btn.prop('disabled', false).text('Guardar datos');
            }
        });
    });

    // ─── Vista Toggle (Cards / Tabla) ────────────────────────────────────────

    $('.sapwcc-view-btn').on('click', function () {
        const view = $(this).data('view');
        $('.sapwcc-view-btn').removeClass('active');
        $(this).addClass('active');

        if (view === 'cards') {
            $('.sapwcc-sites-grid').show();
            $('.sapwcc-sites-table-container').hide();
        } else if (view === 'table') {
            $('.sapwcc-sites-grid').hide();
            $('.sapwcc-sites-table-container').show();
        }

        // Save preference to localStorage
        localStorage.setItem('sapwcc_view_mode', view);
    });

    // Restore preferred view on load
    const savedView = localStorage.getItem('sapwcc_view_mode');
    if (savedView === 'table') {
        $('.sapwcc-view-btn[data-view="table"]').click();
    }

    // ─── Asignar Plan (Auto-save) ────────────────────────────────────────────

    $(document).on('change', '.sapwcc-plan-assign', function () {
        const $select = $(this);
        const siteKey = $select.data('site-key');
        const siteId = $select.data('site-id');
        const plan = $select.val();
        const $icon = $select.next('.dashicons');

        // Visual feedback
        $select.prop('disabled', true);
        if ($icon.length) {
            $icon.removeClass('dashicons-yes-alt dashicons-warning')
                 .addClass('dashicons-update')
                 .css('color', '#2271b1')
                 .addClass('spin');
        }

        $.post(AJAX, {
            action: 'sapwcc_assign_plan',
            nonce: NONCE,
            site_id: siteId,
            plan: plan
        }, function (res) {
            $select.prop('disabled', false);

            if (res.success) {
                // Update icon
                if ($icon.length) {
                    $icon.removeClass('dashicons-update spin');
                    if (plan) {
                        $icon.addClass('dashicons-yes-alt').css('color', '#00a32a');
                    } else {
                        $icon.addClass('dashicons-warning').css('color', '#dba617');
                    }
                }

                // Show brief confirmation
                const $card = $select.closest('.sapwcc-site-card, tr');
                $card.css('background', '#f0f6fc');
                setTimeout(function () {
                    $card.css('background', '');
                }, 800);

                // Update all dropdowns for this site_id (both in cards and table)
                $('.sapwcc-plan-assign[data-site-id="' + siteId + '"]').val(plan);

                // Keep workingFlags in sync so Feature Flags tab doesn't overwrite this change.
                if (!workingFlags.sites) workingFlags.sites = {};
                if (!workingFlags.sites[siteId]) workingFlags.sites[siteId] = {};
                if (plan) {
                    workingFlags.sites[siteId].plan = plan;
                } else {
                    delete workingFlags.sites[siteId].plan;
                }
            } else {
                alert('Error: ' + (res.data || 'No se pudo asignar el plan'));
                if ($icon.length) {
                    $icon.removeClass('dashicons-update spin');
                }
            }
        }).fail(function () {
            alert('Error de conexión');
            $select.prop('disabled', false);
            if ($icon.length) {
                $icon.removeClass('dashicons-update spin');
            }
        });
    });

    // ── Vigilante ────────────────────────────────────────────────────────────

    // Scan all sites
    $('#sapwcc-vig-scan-all').on('click', function () {
        var $btn = $(this);
        $btn.prop('disabled', true).find('.dashicons').addClass('spin');
        $.post(sapwcc.ajax_url, {
            action: 'sapwcc_vigilante_scan',
            nonce:  sapwcc.nonce,
            site_key: 'all'
        }, function (res) {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            if (res.success) {
                location.reload();
            } else {
                alert('Error: ' + (res.data || 'Escaneo fallido'));
            }
        }).fail(function () {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            alert('Error de conexión al escanear.');
        });
    });

    // Scan single site
    $(document).on('click', '.sapwcc-vig-scan-single', function () {
        var $btn     = $(this);
        var siteKey  = $btn.data('site-key');
        var $card    = $btn.closest('.sapwcc-vig-card');
        $btn.prop('disabled', true).find('.dashicons').addClass('spin');
        $.post(sapwcc.ajax_url, {
            action:   'sapwcc_vigilante_scan',
            nonce:    sapwcc.nonce,
            site_key: siteKey
        }, function (res) {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            if (res.success) {
                location.reload();
            } else {
                alert('Error: ' + (res.data || 'Escaneo fallido'));
            }
        }).fail(function () {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
        });
    });

    // AI explanation button
    $(document).on('click', '.sapwcc-vig-ai-btn', function () {
        var $btn    = $(this);
        var $issue  = $btn.closest('.sapwcc-vig-issue');
        var $panel  = $issue.find('.sapwcc-vig-ai-panel');

        if ($panel.is(':visible')) {
            $panel.slideUp(150);
            return;
        }

        // Show loading
        $panel.html(
            '<div class="sapwcc-vig-ai-loading">' +
            '<span class="dashicons dashicons-update spin"></span>' +
            '<span>Consultando IA...</span></div>'
        ).slideDown(150);

        var issueId   = $issue.data('issue-id');
        var issueType = $issue.data('issue-type');
        var siteLabel = $issue.data('site-label');
        var context   = $issue.data('context') || '{}';

        $.post(sapwcc.ajax_url, {
            action:     'sapwcc_vigilante_ai',
            nonce:      sapwcc.nonce,
            issue_id:   issueId,
            issue_type: issueType,
            site_label: siteLabel,
            context:    typeof context === 'string' ? context : JSON.stringify(context)
        }, function (res) {
            if (!res.success) {
                $panel.html('<p style="color:#d63638;margin:0;font-size:13px;">⚠ ' + (res.data || 'Error desconocido') + '</p>');
                return;
            }
            var d = res.data;
            var stepsHtml = '';
            if (d.steps && d.steps.length) {
                stepsHtml = '<ol>' + d.steps.map(function (s) { return '<li>' + escHtml(s) + '</li>'; }).join('') + '</ol>';
            }
            var preventHtml = d.prevention
                ? '<div class="sapwcc-vig-ai-prevention">💡 ' + escHtml(d.prevention) + '</div>'
                : '';
            $panel.html(
                '<h4><span class="dashicons dashicons-superhero-alt"></span> Análisis IA</h4>' +
                '<p>' + escHtml(d.explanation || '') + '</p>' +
                stepsHtml +
                preventHtml
            );
        }).fail(function () {
            $panel.html('<p style="color:#d63638;margin:0;font-size:13px;">⚠ Error de conexión.</p>');
        });
    });

    // Repair button (Corregir) — async repair-ship-to / repair-duplicates
    $(document).on('click', '.sapwcc-vig-repair-btn', function () {
        var $btn      = $(this);
        var siteKey   = $btn.data('site-key');
        var endpoint  = $btn.data('endpoint');
        var $issue    = $btn.closest('.sapwcc-vig-issue');
        var $detail   = $issue.find('.sapwcc-muted');

        $btn.prop('disabled', true)
            .html('<span class="dashicons dashicons-update spin"></span> Reparando...');

        remoteAction(siteKey, endpoint, 'POST', {}, function (res) {
            if (res.success) {
                var d        = typeof res.data === 'string' ? JSON.parse(res.data) : res.data;
                var repaired = parseInt(d.repaired, 10) || 0;
                var skipped  = parseInt(d.skipped,  10) || 0;
                var firstErr = d.details && d.details.find(function(x) { return x.status === 'error'; });
                var label    = repaired + ' reparado(s)';
                if (skipped) label += ', ' + skipped + ' omitido(s)';

                if (repaired > 0) {
                    $btn.html('<span class="dashicons dashicons-yes"></span> ' + label)
                        .css({ background: '#d4edda', borderColor: '#28a745', color: '#155724' })
                        .prop('disabled', true);
                    $detail.text($detail.text().replace(/Se reparar[áa] autom[áa]ticamente\.?/, '') +
                        ' Auto-reparados: ' + repaired + '.');
                } else {
                    var errHint = firstErr ? ' — ' + firstErr.error : (skipped ? ' — ver logs SAP' : '');
                    $btn.html('<span class="dashicons dashicons-warning"></span> ' + label + errHint)
                        .css({ background: '#fff3cd', borderColor: '#ffc107', color: '#856404', 'font-size': '11px' })
                        .prop('disabled', false);
                }
            } else {
                var errMsg = typeof res.data === 'string' ? res.data : JSON.stringify(res.data);
                $btn.html('<span class="dashicons dashicons-warning"></span> Error: ' + errMsg)
                    .css({ background: '#f8d7da', borderColor: '#dc3545', color: '#721c24', 'font-size': '11px' })
                    .prop('disabled', false);
            }
        });
    });

    // Save Vigilante config
    $('#sapwcc-vig-config-form').on('submit', function (e) {
        e.preventDefault();
        var $msg     = $('#sapwcc-vig-config-msg');
        var $btn     = $(this).find('[type=submit]');
        $btn.prop('disabled', true);
        $.post(sapwcc.ajax_url, {
            action:         'sapwcc_vigilante_save_config',
            nonce:          sapwcc.nonce,
            alert_email:    $('#vig-alert-email').val(),
            claude_key:     $('#vig-claude-key').val(),
            openai_key:     $('#vig-openai-key').val(),
            digest_enabled: $('[name=digest_enabled]').is(':checked') ? '1' : '0'
        }, function (res) {
            $btn.prop('disabled', false);
            $msg.text(res.success ? '✓ ' + res.data : '✗ ' + res.data)
                .css('color', res.success ? '#00a32a' : '#d63638')
                .show();
            setTimeout(function () { $msg.fadeOut(); }, 3500);
        });
    });

    // Test digest
    $('#sapwcc-vig-test-digest').on('click', function () {
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.post(sapwcc.ajax_url, {
            action: 'sapwcc_vigilante_test_digest',
            nonce:  sapwcc.nonce
        }, function (res) {
            $btn.prop('disabled', false);
            alert(res.success ? '✓ ' + res.data : '✗ ' + res.data);
        }).fail(function () {
            $btn.prop('disabled', false);
            alert('Error de conexión.');
        });
    });

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

})(jQuery);
