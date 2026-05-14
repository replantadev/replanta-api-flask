/**
 * Debug Hub JavaScript
 */

(function($) {
    'use strict';

    console.log('🔧 Debug Hub JS loaded');

    // Ejecutar test
    window.runTest = function(testType) {
        console.log('🚀 Running test:', testType);
        console.log('📊 dr_debug_ajax available:', typeof dr_debug_ajax !== 'undefined');
        console.log('📊 dr_debug_ajax content:', dr_debug_ajax);

        const resultDiv = document.getElementById(testType + '_result');
        const button = resultDiv.previousElementSibling;

        // Limpiar resultado anterior
        resultDiv.innerHTML = '';
        resultDiv.className = 'dr-test-result loading';

        // Mostrar loading
        resultDiv.innerHTML = '<div class="dr-loading"></div>' + dr_debug_ajax.strings.running_test;

        // Desactivar botón
        button.disabled = true;
        const originalText = button.textContent;
        button.innerHTML = '<div class="dr-loading"></div>' + dr_debug_ajax.strings.running_test;

        // Ejecutar test via AJAX
        $.ajax({
            url: dr_debug_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'dr_debug_test',
                test_type: testType,
                nonce: dr_debug_ajax.nonce
            },
            success: function(response) {
                console.log('✅ AJAX success:', response);
                resultDiv.className = 'dr-test-result ' + (response.success ? 'success' : 'error');
                resultDiv.innerHTML = response.data;
            },
            error: function(xhr, status, error) {
                console.error('❌ AJAX error:', xhr, status, error);
                resultDiv.className = 'dr-test-result error';
                resultDiv.innerHTML = '❌ ' + dr_debug_ajax.strings.error + ': ' + error + '\n\nRespuesta del servidor:\n' + xhr.responseText;
            },
            complete: function() {
                // Restaurar botón
                button.disabled = false;
                button.innerHTML = originalText;
            }
        });
    };

    // Auto-refresh para algunos elementos
    $(document).ready(function() {
        console.log('📄 Debug Hub page ready');
        // Actualizar estadísticas cada 30 segundos
        setInterval(function() {
            // Podríamos actualizar algunas secciones automáticamente
        }, 30000);
    });

    // Encolado manual de dominio
    window.manualEnqueue = function() {
        const domain = document.getElementById('enqueue_domain').value.trim();
        const preset = document.getElementById('enqueue_preset').value;
        const autoNs = document.getElementById('enqueue_auto_ns').checked;

        if (!domain) {
            alert('Por favor ingresa un dominio');
            return;
        }

        const resultDiv = document.getElementById('manual_enqueue_result');
        resultDiv.innerHTML = '<div class="dr-loading"></div>Encolando dominio...';
        resultDiv.className = 'dr-test-result loading';

        $.ajax({
            url: dr_debug_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'dr_debug_manual_enqueue',
                domain: domain,
                preset: preset,
                auto_ns: autoNs ? 1 : 0,
                nonce: dr_debug_ajax.nonce
            },
            success: function(response) {
                resultDiv.className = 'dr-test-result ' + (response.success ? 'success' : 'error');
                resultDiv.innerHTML = response.data;
            },
            error: function(xhr, status, error) {
                resultDiv.className = 'dr-test-result error';
                resultDiv.innerHTML = '❌ Error: ' + error + '\n\n' + xhr.responseText;
            }
        });
    };

    // Verificar estado de dominio
    window.checkDomainStatus = function() {
        const domain = document.getElementById('existing_domain').value.trim();

        if (!domain) {
            alert('Por favor ingresa un dominio');
            return;
        }

        const resultDiv = document.getElementById('domain_status_result');
        const actionsDiv = document.getElementById('domain_actions');

        resultDiv.innerHTML = '<div class="dr-loading"></div>Verificando estado...';
        resultDiv.className = 'dr-test-result loading';
        actionsDiv.style.display = 'none';

        $.ajax({
            url: dr_debug_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'dr_debug_check_status',
                domain: domain,
                nonce: dr_debug_ajax.nonce
            },
            success: function(response) {
                resultDiv.className = 'dr-test-result ' + (response.success ? 'success' : 'error');
                resultDiv.innerHTML = response.data;

                // Mostrar acciones disponibles si el dominio existe
                if (response.actions) {
                    actionsDiv.style.display = 'block';
                    document.getElementById('retry_domain_btn').style.display = response.actions.can_retry ? 'inline-block' : 'none';
                    document.getElementById('update_config_btn').style.display = response.actions.can_update ? 'inline-block' : 'none';
                }
            },
            error: function(xhr, status, error) {
                resultDiv.className = 'dr-test-result error';
                resultDiv.innerHTML = '❌ Error: ' + error + '\n\n' + xhr.responseText;
            }
        });
    };

    // Reintentar dominio
    window.retryDomain = function() {
        const domain = document.getElementById('existing_domain').value.trim();

        if (!domain) {
            alert('Por favor ingresa un dominio');
            return;
        }

        if (!confirm('¿Estás seguro de que quieres reintentar el procesamiento de este dominio?')) {
            return;
        }

        const resultDiv = document.getElementById('domain_status_result');
        resultDiv.innerHTML = '<div class="dr-loading"></div>Reintentando dominio...';
        resultDiv.className = 'dr-test-result loading';

        $.ajax({
            url: dr_debug_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'dr_debug_retry_domain',
                domain: domain,
                nonce: dr_debug_ajax.nonce
            },
            success: function(response) {
                resultDiv.className = 'dr-test-result ' + (response.success ? 'success' : 'error');
                resultDiv.innerHTML = response.data;
                // Ocultar acciones después de la operación
                document.getElementById('domain_actions').style.display = 'none';
            },
            error: function(xhr, status, error) {
                resultDiv.className = 'dr-test-result error';
                resultDiv.innerHTML = '❌ Error: ' + error + '\n\n' + xhr.responseText;
            }
        });
    };

    // Actualizar configuración de dominio
    window.updateDomainConfig = function() {
        const domain = document.getElementById('existing_domain').value.trim();
        const newPreset = document.getElementById('enqueue_preset').value;

        if (!domain) {
            alert('Por favor ingresa un dominio');
            return;
        }

        if (!confirm(`¿Estás seguro de que quieres actualizar la configuración de ${domain} con el preset '${newPreset}'?`)) {
            return;
        }

        const resultDiv = document.getElementById('domain_status_result');
        resultDiv.innerHTML = '<div class="dr-loading"></div>Actualizando configuración...';
        resultDiv.className = 'dr-test-result loading';

        $.ajax({
            url: dr_debug_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'dr_debug_update_config',
                domain: domain,
                preset: newPreset,
                nonce: dr_debug_ajax.nonce
            },
            success: function(response) {
                resultDiv.className = 'dr-test-result ' + (response.success ? 'success' : 'error');
                resultDiv.innerHTML = response.data;
                // Ocultar acciones después de la operación
                document.getElementById('domain_actions').style.display = 'none';
            },
            error: function(xhr, status, error) {
                resultDiv.className = 'dr-test-result error';
                resultDiv.innerHTML = '❌ Error: ' + error + '\n\n' + xhr.responseText;
            }
        });
    };

    // Debug PHP Info para un dominio específico
    window.debugPHPInfo = function() {
        const domain = document.getElementById('php_debug_domain').value.trim();

        if (!domain) {
            alert('Por favor ingresa un dominio para debuguear');
            return;
        }

        const resultDiv = document.getElementById('php_debug_result');
        resultDiv.innerHTML = '<div class="dr-loading"></div>🐘 Obteniendo información PHP...';
        resultDiv.className = 'dr-test-result loading';

        $.ajax({
            url: dr_debug_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'dr_debug_php_info',
                domain: domain,
                nonce: dr_debug_ajax.nonce
            },
            success: function(response) {
                resultDiv.className = 'dr-test-result ' + (response.success ? 'success' : 'error');
                // Formatear como texto preformateado para mejor visualización
                resultDiv.innerHTML = '<pre style="white-space: pre-wrap; word-wrap: break-word; margin: 0; font-family: Consolas, Monaco, monospace; font-size: 12px;">' + (response.data || response.message || 'Sin datos') + '</pre>';
            },
            error: function(xhr, status, error) {
                resultDiv.className = 'dr-test-result error';
                resultDiv.innerHTML = '❌ Error: ' + error + '\n\n' + xhr.responseText;
            }
        });
    };

})(jQuery);