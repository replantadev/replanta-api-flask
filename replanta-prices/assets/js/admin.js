/**
 * Replanta Prices — Admin JS
 * Handles: manual sync button + connection test via AJAX
 */
(function($) {
    'use strict';

    console.log('[replanta-prices] admin.js cargado. replantaPricesAdmin:', typeof replantaPricesAdmin !== 'undefined' ? replantaPricesAdmin : 'NO DEFINIDO');

    $(document).ready(function() {

        console.log('[replanta-prices] document.ready OK');
        console.log('[replanta-prices] #replanta-prices-sync-btn encontrado:', $('#replanta-prices-sync-btn').length);
        console.log('[replanta-prices] #replanta-prices-test-btn encontrado:', $('#replanta-prices-test-btn').length);

        if (typeof replantaPricesAdmin === 'undefined') {
            console.error('[replanta-prices] ERROR: replantaPricesAdmin no está definido. El script no se localizó correctamente.');
            return;
        }

        /* ── Sync button (event delegation) ── */
        $(document).on('click', '#replanta-prices-sync-btn', function() {
            var $btn    = $(this);
            var $status = $('#replanta-prices-sync-status');

            $btn.prop('disabled', true).text('Sincronizando…');
            $status.text('');

            $.post(replantaPricesAdmin.ajax_url, {
                action: 'replanta_prices_sync',
                nonce:  replantaPricesAdmin.nonce
            }, function(response) {
                $btn.prop('disabled', false).text('Sincronizar ahora');

                if (response.success) {
                    var log = response.data.log;
                    $status.html('<span style="color:green;">Sincronización completada (' + response.data.last_sync + ')</span>');

                    var $logBox = $('.replanta-sync-log');
                    if ($logBox.length && log.length) {
                        var html = '';
                        for (var i = 0; i < log.length; i++) {
                            html += '<code>' + $('<span>').text(log[i]).html() + '</code><br>';
                        }
                        $logBox.html(html);
                    } else if (log.length) {
                        var $newLog = $('<div class="replanta-sync-log" style="margin-top:12px;"></div>');
                        var html2 = '';
                        for (var j = 0; j < log.length; j++) {
                            html2 += '<code>' + $('<span>').text(log[j]).html() + '</code><br>';
                        }
                        $newLog.html(html2);
                        $status.after($newLog);
                    }
                } else {
                    $status.html('<span style="color:red;">Error: ' + (response.data || 'desconocido') + '</span>');
                }
            }).fail(function() {
                $btn.prop('disabled', false).text('Sincronizar ahora');
                $status.html('<span style="color:red;">Error de conexión</span>');
            });
        });

        /* ── Test connection button (event delegation) ── */
        $(document).on('click', '#replanta-prices-test-btn', function() {
            console.log('[replanta-prices] Clic en Verificar conexión');
            var $testBtn    = $(this);
            var $testStatus = $('#replanta-prices-test-status');

            $testBtn.prop('disabled', true).text('Verificando…');
            $testStatus.text('').css('color', '');

            $.post(replantaPricesAdmin.ajax_url, {
                action: 'replanta_prices_test',
                nonce:  replantaPricesAdmin.nonce
            }, function(response) {
                console.log('[replanta-prices] Respuesta test completa:', JSON.stringify(response, null, 2));
                $testBtn.prop('disabled', false).text('Verificar conexión');

                if (response.success) {
                    $testStatus.css('color', 'green').text(response.data.message);
                } else {
                    $testStatus.css('color', 'red').text('Error: ' + (response.data || 'desconocido'));
                }
            }).fail(function(jqXHR) {
                console.error('[replanta-prices] AJAX fail:', jqXHR.status, jqXHR.responseText);
                $testBtn.prop('disabled', false).text('Verificar conexión');
                $testStatus.css('color', 'red').text('Error de conexión');
            });
        });

    });
})(jQuery);
