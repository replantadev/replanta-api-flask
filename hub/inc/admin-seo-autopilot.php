<?php
/**
 * Admin SEO Autopilot Page — cross-site SA fix queue
 * Aggregates fixable SA issues from all managed sites in one place.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPHUB_Admin_SeoAutopilot {

    public function render() {
        global $wpdb;
        $t_sites = $wpdb->prefix . 'rphub_sites';

        $sites = $wpdb->get_results(
            "SELECT id, name, url FROM $t_sites WHERE status = 'active' ORDER BY name ASC",
            ARRAY_A
        ) ?: [];

        $sync_nonce = wp_create_nonce('rphub_sync_site');
        $task_nonce = wp_create_nonce('rphub_execute_task');
        ?>
        <div class="wrap rphub-autopilot">
            <h1 class="wp-heading-inline">SEO Autopilot</h1>
            <button class="page-title-action" id="rphub-ap-load-btn" onclick="rphubApLoad()">
                <span class="dashicons dashicons-search" style="vertical-align:middle;font-size:14px;margin-top:-2px;"></span>
                Cargar issues
            </button>
            <button class="page-title-action" id="rphub-ap-run-btn" onclick="rphubApRunSelected()" disabled style="background:#2271b1;color:#fff;border-color:#2271b1;">
                <span class="dashicons dashicons-controls-play" style="vertical-align:middle;font-size:14px;margin-top:-2px;"></span>
                Ejecutar seleccionados
            </button>

            <!-- Summary chips (populated by JS) -->
            <div class="rphub-ops-summary" id="rphub-ap-summary" style="display:none;">
                <div class="rphub-ops-chip rphub-ops-chip-critical">
                    <strong id="rphub-ap-count-critical">0</strong>
                    <span>críticos</span>
                </div>
                <div class="rphub-ops-chip rphub-ops-chip-warning">
                    <strong id="rphub-ap-count-warning">0</strong>
                    <span>avisos</span>
                </div>
                <div class="rphub-ops-chip">
                    <strong id="rphub-ap-count-fixable">0</strong>
                    <span>reparables</span>
                </div>
                <div class="rphub-ops-chip">
                    <strong id="rphub-ap-count-sites">0</strong>
                    <span>sitios con issues</span>
                </div>
            </div>

            <!-- Progress bar -->
            <div id="rphub-ap-progress" style="display:none;margin:12px 0;padding:10px 14px;background:#f0f6ff;border-left:3px solid #2271b1;border-radius:2px;">
                <span id="rphub-ap-progress-text">Cargando…</span>
            </div>

            <!-- Select/filter bar -->
            <div class="rphub-ops-filters" id="rphub-ap-filters" style="display:none;">
                <label>
                    <input type="checkbox" id="rphub-ap-select-all" onchange="rphubApToggleAll(this.checked)">
                    Seleccionar todos los reparables
                </label>
                &nbsp;&nbsp;
                <label>
                    <input type="checkbox" id="rphub-ap-only-critical" onchange="rphubApFilterCritical(this.checked)">
                    Solo críticos
                </label>
            </div>

            <!-- Issues table -->
            <table class="wp-list-table widefat fixed striped rphub-autopilot-table" id="rphub-ap-table" style="display:none;">
                <thead>
                    <tr>
                        <th style="width:32px;"></th>
                        <th style="width:200px;">Sitio</th>
                        <th>Issue</th>
                        <th style="width:90px;">Estado</th>
                        <th style="width:110px;">Módulo</th>
                        <th style="width:120px;">Acción</th>
                    </tr>
                </thead>
                <tbody id="rphub-ap-tbody">
                </tbody>
            </table>

            <p id="rphub-ap-empty" style="display:none;color:#666;font-style:italic;">
                No se encontraron issues reparables en ningún sitio.
            </p>
        </div>

        <style>
        .rphub-autopilot-table td { vertical-align: middle; }
        .rphub-ap-pill { display:inline-block; padding:2px 7px; border-radius:10px; font-size:11px; font-weight:600; }
        .rphub-ap-pill.critical { background:#fde8e8; color:#c0392b; }
        .rphub-ap-pill.warning  { background:#fff3cd; color:#856404; }
        .rphub-ap-fix-btn { padding:3px 10px; font-size:12px; cursor:pointer; }
        .rphub-ap-fix-btn:disabled { opacity:.5; cursor:default; }
        .rphub-ap-done { color:#2e7d32; font-weight:600; }
        .rphub-ap-err  { color:#c0392b; font-size:11px; }
        .rphub-ap-row-done td { opacity:.55; }
        </style>

        <script>
        (function($) {
            var _ajaxUrl   = <?php echo json_encode(admin_url('admin-ajax.php')); ?>;
            var _syncNonce = <?php echo json_encode($sync_nonce); ?>;
            var _taskNonce = <?php echo json_encode($task_nonce); ?>;
            var _sites     = <?php echo json_encode(array_values($sites)); ?>;

            var _issueRows = []; // { siteId, siteName, issue }

            window.rphubApLoad = function() {
                _issueRows = [];
                var $tbody = $('#rphub-ap-tbody').empty();
                $('#rphub-ap-table,#rphub-ap-empty,#rphub-ap-summary,#rphub-ap-filters').hide();
                $('#rphub-ap-progress').show().find('#rphub-ap-progress-text').text('Cargando issues de ' + _sites.length + ' sitios…');
                $('#rphub-ap-load-btn').prop('disabled', true);
                $('#rphub-ap-run-btn').prop('disabled', true);

                var queue = _sites.slice();
                var done = 0;

                function next() {
                    if (!queue.length) {
                        finish();
                        return;
                    }
                    var site = queue.shift();
                    $.post(_ajaxUrl, {
                        action: 'rphub_get_sa_issues',
                        site_id: site.id,
                        nonce: _syncNonce
                    }, function(resp) {
                        done++;
                        $('#rphub-ap-progress-text').text('Cargando… ' + done + '/' + _sites.length + ' — ' + site.name);
                        if (resp.success && resp.data && resp.data.issues) {
                            resp.data.issues.forEach(function(issue) {
                                _issueRows.push({ siteId: site.id, siteName: site.name, issue: issue });
                            });
                        }
                        next();
                    }).fail(function() {
                        done++;
                        next();
                    });
                }

                function finish() {
                    $('#rphub-ap-progress').hide();
                    $('#rphub-ap-load-btn').prop('disabled', false);

                    if (!_issueRows.length) {
                        $('#rphub-ap-empty').show();
                        return;
                    }

                    // Sort: critical first
                    _issueRows.sort(function(a, b) {
                        if (a.issue.status === 'critical' && b.issue.status !== 'critical') return -1;
                        if (b.issue.status === 'critical' && a.issue.status !== 'critical') return 1;
                        return a.siteName.localeCompare(b.siteName);
                    });

                    // Populate table
                    _issueRows.forEach(function(row, idx) {
                        var issue = row.issue;
                        var pillClass = issue.status === 'critical' ? 'critical' : 'warning';
                        var fixBtn = issue.fixable
                            ? '<button class="button button-small rphub-ap-fix-btn" data-idx="' + idx + '" onclick="rphubApRunOne(' + idx + ',this)">Ejecutar</button>'
                            : '<span style="color:#888;font-size:12px;">Manual</span>';
                        var chk = issue.fixable
                            ? '<input type="checkbox" class="rphub-ap-chk" data-idx="' + idx + '">'
                            : '';
                        $tbody.append(
                            '<tr id="rphub-ap-row-' + idx + '">' +
                            '<td>' + chk + '</td>' +
                            '<td><a href="' + row.siteId + '" style="font-size:12px;">' + $('<span>').text(row.siteName).html() + '</a></td>' +
                            '<td><strong>' + $('<span>').text(issue.label).html() + '</strong>' +
                                (issue.description ? '<br><span style="font-size:11px;color:#666;">' + $('<span>').text(issue.description).html() + '</span>' : '') + '</td>' +
                            '<td><span class="rphub-ap-pill ' + pillClass + '">' + issue.status + '</span></td>' +
                            '<td style="font-size:12px;color:#666;">' + $('<span>').text(issue.module || '—').html() + '</td>' +
                            '<td id="rphub-ap-action-' + idx + '">' + fixBtn + '</td>' +
                            '</tr>'
                        );
                    });

                    // Update chips
                    var nCrit = _issueRows.filter(function(r) { return r.issue.status === 'critical'; }).length;
                    var nWarn = _issueRows.filter(function(r) { return r.issue.status === 'warning'; }).length;
                    var nFix  = _issueRows.filter(function(r) { return r.issue.fixable; }).length;
                    var nSites = (new Set(_issueRows.map(function(r) { return r.siteId; }))).size;
                    $('#rphub-ap-count-critical').text(nCrit);
                    $('#rphub-ap-count-warning').text(nWarn);
                    $('#rphub-ap-count-fixable').text(nFix);
                    $('#rphub-ap-count-sites').text(nSites);
                    $('#rphub-ap-summary,#rphub-ap-filters,#rphub-ap-table').show();
                    $('#rphub-ap-run-btn').prop('disabled', false);
                }

                next();
            };

            window.rphubApToggleAll = function(checked) {
                $('.rphub-ap-chk:visible').prop('checked', checked);
            };

            window.rphubApFilterCritical = function(onlyC) {
                if (onlyC) {
                    $('#rphub-ap-table tbody tr').each(function() {
                        var idx = $(this).attr('id').replace('rphub-ap-row-', '');
                        if (_issueRows[idx] && _issueRows[idx].issue.status !== 'critical') {
                            $(this).hide();
                        }
                    });
                } else {
                    $('#rphub-ap-table tbody tr').show();
                }
            };

            window.rphubApRunOne = function(idx, btn) {
                var row = _issueRows[idx];
                if (!row || !row.issue.fixable) return;
                $(btn).prop('disabled', true).text('…');
                $.post(_ajaxUrl, {
                    action: 'rphub_run_sa_fix',
                    site_id: row.siteId,
                    fix_id: row.issue.fix_id,
                    nonce: _taskNonce
                }, function(resp) {
                    if (resp.success) {
                        $('#rphub-ap-action-' + idx).html('<span class="rphub-ap-done">✓ Hecho</span>');
                        $('#rphub-ap-row-' + idx).addClass('rphub-ap-row-done');
                    } else {
                        var msg = (resp.data && typeof resp.data === 'string') ? resp.data : 'Error';
                        $('#rphub-ap-action-' + idx).html('<button class="button button-small rphub-ap-fix-btn" onclick="rphubApRunOne(' + idx + ',this)">Reintentar</button><br><span class="rphub-ap-err">' + $('<span>').text(msg).html() + '</span>');
                    }
                }).fail(function() {
                    $(btn).prop('disabled', false).text('Ejecutar');
                });
            };

            window.rphubApRunSelected = function() {
                var selected = [];
                $('.rphub-ap-chk:checked').each(function() {
                    selected.push(parseInt($(this).data('idx'), 10));
                });
                if (!selected.length) {
                    alert('Selecciona al menos un issue reparable.');
                    return;
                }
                $('#rphub-ap-run-btn').prop('disabled', true);
                $('#rphub-ap-progress').show().find('#rphub-ap-progress-text').text('Ejecutando 0/' + selected.length + '…');

                var done = 0;
                var errors = 0;

                function runNext() {
                    if (!selected.length) {
                        $('#rphub-ap-progress-text').text('Completado: ' + done + ' fixes ejecutados' + (errors ? ', ' + errors + ' errores' : '') + '.');
                        $('#rphub-ap-run-btn').prop('disabled', false);
                        return;
                    }
                    var idx = selected.shift();
                    var row = _issueRows[idx];
                    $.post(_ajaxUrl, {
                        action: 'rphub_run_sa_fix',
                        site_id: row.siteId,
                        fix_id: row.issue.fix_id,
                        nonce: _taskNonce
                    }, function(resp) {
                        done++;
                        $('#rphub-ap-progress-text').text('Ejecutando ' + done + '/' + (done + selected.length) + '…');
                        if (resp.success) {
                            $('#rphub-ap-action-' + idx).html('<span class="rphub-ap-done">✓ Hecho</span>');
                            $('#rphub-ap-row-' + idx).addClass('rphub-ap-row-done');
                        } else {
                            errors++;
                            var msg = (resp.data && typeof resp.data === 'string') ? resp.data : 'Error';
                            $('#rphub-ap-action-' + idx).html('<span class="rphub-ap-err">' + $('<span>').text(msg).html() + '</span>');
                        }
                        runNext();
                    }).fail(function() {
                        done++;
                        errors++;
                        runNext();
                    });
                }

                runNext();
            };

        })(jQuery);
        </script>
        <?php
    }
}
