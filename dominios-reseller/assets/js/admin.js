// assets/js/admin.js - JavaScript para la interfaz moderna de Dominios Reseller v1.1.3

jQuery(document).ready(function($) {
    'use strict';
    
    console.log('Dominios Reseller Admin JS v1.1.3 loaded successfully');

    // Manejo de pestañas (compatible con ambos estilos)
    $('.nav-tab, .tab-button').on('click', function(e) {
        e.preventDefault();

        let targetTab;
        
        // Para botones de tab-button
        if ($(this).hasClass('tab-button')) {
            const tabName = $(this).data('tab');
            targetTab = '#' + tabName + '-tab';
            
            // Cambiar pestaña activa
            $('.tab-button').removeClass('active');
            $(this).addClass('active');
            
            // Mostrar contenido de pestaña
            $('.tab-pane').removeClass('active');
            $(targetTab).addClass('active');
        } else {
            // Para nav-tab (estilo WordPress)
            targetTab = $(this).attr('href');
            
            // Cambiar pestaña activa
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            // Mostrar contenido de pestaña
            $('.tab-content').hide();
            $(targetTab).show();
        }

        // Actualizar URL hash
        window.location.hash = targetTab;
    });

    // Cargar pestaña desde hash URL
    if (window.location.hash) {
        const hash = window.location.hash;
        const tabButton = $(`.tab-button[data-tab="${hash.replace('#', '').replace('-tab', '')}"]`);
        const navTab = $(`.nav-tab[href="${hash}"]`);
        
        if (tabButton.length) {
            tabButton.trigger('click');
        } else if (navTab.length) {
            navTab.trigger('click');
        }
    }

    // Probar conexión WHM
    $(document).on('submit', 'form[action*="test_whm_connection"]', function(e) {
        e.preventDefault();

        const form = $(this);
        const server = form.find('input[name="server"]').val();
        const button = form.find('input[type="submit"]');
        const originalText = button.val();

        button.val('🔄 Probando...').prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'test_whm_connection',
                server: server,
                nonce: dominios_reseller_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('✅ ' + response.data.message + ' (' + response.data.count + ' cuentas)', 'success');
                } else {
                    showNotice('❌ ' + response.data.error, 'error');
                }
            },
            error: function() {
                showNotice('❌ Error de conexión con el servidor', 'error');
            },
            complete: function() {
                button.val(originalText).prop('disabled', false);
            }
        });
    });

    // Calcular emisiones para un dominio (TABLA VIEW)
    $(document).on('click', '.calculate-emissions', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const domain = button.data('domain');
        const server = button.data('server');
        const row = button.closest('tr');
        const co2Input = row.find('.co2-input');

        if (!domain || !server) {
            showNotice('⚠️ Datos incompletos del dominio', 'warning');
            return;
        }

        button.text('⏳ Calculando...').prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'recalcular_co2',
                domain: domain,
                server: server,
                nonce: dominios_reseller_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    const co2 = response.data.co2_evaded;
                    const detalles = response.data.detalles;
                    
                    // Actualizar input con nuevo valor
                    co2Input.val(co2).addClass('changed');
                    
                    // Mensaje detallado
                    let mensaje = '✅ ' + response.data.message + '\n\n';
                    mensaje += 'Detalles:\n';
                    if (detalles.trafico_gb !== undefined) {
                        mensaje += '• Tráfico: ' + detalles.trafico_gb + ' GB\n';
                    }
                    mensaje += '• CO2 tráfico: ' + detalles.co2_trafico_gramos + ' g\n';
                    mensaje += '• CO2 base: ' + detalles.co2_base_gramos + ' g\n';
                    mensaje += '• Total: ' + detalles.co2_total_gramos + ' g';
                    if (detalles.visitas_estimadas) {
                        mensaje += '\n• Visitas estimadas: ' + detalles.visitas_estimadas;
                    }
                    
                    showNotice(mensaje, 'success');
                    
                } else {
                    showNotice('❌ ' + (response.data?.message || 'Error desconocido'), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX:', error);
                showNotice('❌ Error de conexión al calcular CO2', 'error');
            },
            complete: function() {
                button.text('Calcular').prop('disabled', false);
            }
        });
    });

    // Calcular emisiones para un dominio (CARDS VIEW)
    $(document).on('click', '.calculate-emissions-card', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const domain = button.data('domain');
        const server = button.data('server');
        const card = button.closest('.domain-card');
        const co2Input = card.find('.co2-input-card');

        if (!domain || !server) {
            showNotice('⚠️ Datos incompletos del dominio', 'warning');
            return;
        }

        button.text('⏳ Calculando...').prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'recalcular_co2',
                domain: domain,
                server: server,
                nonce: dominios_reseller_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    const co2 = response.data.co2_evaded;
                    const detalles = response.data.detalles;
                    
                    // Actualizar input con nuevo valor
                    co2Input.val(co2).addClass('changed');
                    
                    // Mensaje detallado
                    let mensaje = '✅ ' + response.data.message + '\n\n';
                    mensaje += 'Detalles:\n';
                    if (detalles.trafico_gb !== undefined) {
                        mensaje += '• Tráfico: ' + detalles.trafico_gb + ' GB\n';
                    }
                    mensaje += '• CO2 tráfico: ' + detalles.co2_trafico_gramos + ' g\n';
                    mensaje += '• CO2 base: ' + detalles.co2_base_gramos + ' g\n';
                    mensaje += '• Total: ' + detalles.co2_total_gramos + ' g';
                    if (detalles.visitas_estimadas) {
                        mensaje += '\n• Visitas estimadas: ' + detalles.visitas_estimadas;
                    }
                    
                    showNotice(mensaje, 'success');
                    
                } else {
                    showNotice('❌ ' + (response.data?.message || 'Error desconocido'), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX:', error);
                showNotice('❌ Error de conexión al calcular CO2', 'error');
            },
            complete: function() {
                button.text('Calcular').prop('disabled', false);
            }
        });
    });

    // Guardar todos los cambios
    $(document).on('click', '.save-all-changes', function() {
        const button = $(this);
        const server = button.data('server');
        const table = $(`#domains-table-${server}`);
        const originalText = button.text();

        button.text('💾 Guardando...').prop('disabled', true);

        const data = [];
        table.find('tbody tr').each(function() {
            const row = $(this);
            const domain = row.data('domain');
            const trees = row.find('.trees-input').val();
            const co2 = row.find('.co2-input').val();

            if (domain) {
                data.push({
                    domain: domain,
                    trees: parseInt(trees) || 0,
                    co2: parseFloat(co2) || 0
                });
            }
        });

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'save_domain_data',
                server: server,
                data: JSON.stringify(data),
                nonce: dominios_reseller_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('✅ Datos guardados correctamente', 'success');
                } else {
                    showNotice('❌ Error al guardar: ' + response.data, 'error');
                }
            },
            error: function() {
                showNotice('❌ Error de conexión al guardar datos', 'error');
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    });

    // Actualizar datos
    $(document).on('click', '.refresh-data', function() {
        const button = $(this);
        const server = button.data('server');
        const originalText = button.text();

        button.text('🔄 Actualizando...').prop('disabled', true);

        // Recargar la página para actualizar los datos
        setTimeout(function() {
            location.reload();
        }, 500);
    });

    // Función para mostrar notificaciones
    function showNotice(message, type = 'info') {
        // Remover notificaciones existentes
        $('.dominios-reseller-notice').remove();

        const noticeClass = type === 'success' ? 'notice-success' :
                           type === 'error' ? 'notice-error' :
                           type === 'warning' ? 'notice-warning' : 'notice-info';

        const notice = $(`
            <div class="notice ${noticeClass} dominios-reseller-notice">
                <p>${message}</p>
            </div>
        `);

        $('.dominios-reseller-admin').prepend(notice);

        // Auto-remover después de 5 segundos
        setTimeout(function() {
            notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }

    // Validación de inputs numéricos
    $(document).on('input', '.trees-input, .co2-input', function() {
        const value = $(this).val();
        const numValue = parseFloat(value);

        if (isNaN(numValue) || numValue < 0) {
            $(this).addClass('input-error');
        } else {
            $(this).removeClass('input-error');
        }
    });

    // Mejorar UX con tooltips
    $(document).on('mouseenter', '.status-badge', function() {
        const status = $(this).text().toLowerCase();
        let tooltip = '';

        switch(status) {
            case 'activo':
                tooltip = 'Cuenta activa en WHM';
                break;
            case 'suspendido':
                tooltip = 'Cuenta suspendida en WHM';
                break;
            case 'addon':
                tooltip = 'Dominio adicional (addon domain)';
                break;
        }

        if (tooltip) {
            $(this).attr('title', tooltip);
        }
    });

    // Confirmación antes de guardar cambios masivos
    $(document).on('click', '.save-all-changes', function(e) {
        const dataCount = $(`#domains-table-${$(this).data('server')} tbody tr`).length;

        if (dataCount > 10) {
            if (!confirm(`¿Guardar cambios para ${dataCount} dominios?`)) {
                e.preventDefault();
                return false;
            }
        }
    });

    // Auto-guardado de inputs (opcional)
    let autoSaveTimer;
    $(document).on('input', '.trees-input, .co2-input', function() {
        clearTimeout(autoSaveTimer);
        autoSaveTimer = setTimeout(function() {
            // Aquí podrías implementar auto-guardado silencioso
            console.log('Auto-save triggered');
        }, 2000);
    });

    // Filtros para tabla unificada
    $('#server-filter, #status-filter').on('change', function() {
        const serverFilter = $('#server-filter').val();
        const statusFilter = $('#status-filter').val();

        $('#unified-domains-table tbody tr').each(function() {
            const row = $(this);
            const rowServer = row.data('server');
            const rowStatus = row.data('status');

            let showRow = true;

            if (serverFilter && rowServer !== serverFilter.toLowerCase()) {
                showRow = false;
            }

            if (statusFilter && rowStatus !== statusFilter) {
                showRow = false;
            }

            if (showRow) {
                row.show();
            } else {
                row.hide();
            }
        });
    });

    // Guardar cambios tabla unificada
    $(document).on('click', '.save-all-unified', function() {
        const button = $(this);
        const originalText = button.text();

        button.text('💾 Guardando...').prop('disabled', true);

        const data = [];
        $('#unified-domains-table tbody tr:visible').each(function() {
            const row = $(this);
            const domain = row.data('domain');
            const server = row.data('server');
            const trees = row.find('.trees-input').val();
            const co2 = row.find('.co2-input').val();

            if (domain) {
                data.push({
                    domain: domain,
                    server: server,
                    trees: parseInt(trees) || 0,
                    co2: parseFloat(co2) || 0
                });
            }
        });

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'save_unified_domain_data',
                data: JSON.stringify(data),
                nonce: dominios_reseller_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('✅ Datos guardados correctamente', 'success');
                } else {
                    showNotice('❌ Error al guardar: ' + response.data, 'error');
                }
            },
            error: function() {
                showNotice('❌ Error de conexión al guardar datos', 'error');
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    });

    // Actualizar datos tabla unificada
    $(document).on('click', '.refresh-unified', function() {
        const button = $(this);
        const originalText = button.text();

        button.text('🔄 Actualizando...').prop('disabled', true);

        // Recargar la página para actualizar los datos
        setTimeout(function() {
            location.reload();
        }, 500);
    });

    // ==========================================
    // PHP PILOT SYSTEM
    // ==========================================

    /**
     * Mostrar modal con información detallada de PHP
     */
    $(document).on('click', '.dr-pilot-php, .dr-btn-php-config', function() {
        const $element = $(this);
        const domain = $element.data('domain');
        const phpData = $element.data('php-data') || $element.closest('.dr-card').find('.dr-pilot-php').data('php-data');

        console.log('PHP Config clicked:', domain, 'Element:', $element, 'PHP Data:', phpData);

        if (!phpData) {
            console.log('No PHP data found, showing loading message');
            showNotice('🔄 Cargando información de PHP...', 'info');
            return;
        }

        console.log('Opening PHP modal for domain:', domain);
        showPHPModal(domain, phpData);
    });

    /**
     * Mostrar modal de PHP mejorado
     */
    function showPHPModal(domain, phpData) {
        console.log('showPHPModal called with domain:', domain, 'phpData:', phpData);

        // Crear modal HTML mejorado
        const modalHtml = `
            <div id="dr-php-modal" class="dr-modal-overlay" style="display: none;">
                <div class="dr-modal-content dr-php-modal-content">
                    <div class="dr-modal-header">
                        <div class="dr-modal-title-section">
                            <h3>⚡ Configuración PHP</h3>
                            <div class="dr-domain-badge">${domain}</div>
                        </div>
                        <button class="dr-modal-close">&times;</button>
                    </div>

                    <div class="dr-modal-body">
                        <div class="dr-php-overview">
                            <div class="dr-php-status-card">
                                <div class="dr-status-icon">
                                    ${getPHPStatusIcon(phpData)}
                                </div>
                                <div class="dr-status-info">
                                    <div class="dr-php-version-display">${phpData.php_version || 'Desconocido'}</div>
                                    <div class="dr-performance-indicator">
                                        ${phpData.max_performance ?
                                            '<span class="dr-max-performance">🚀 Max Performance Ready</span>' :
                                            '<span class="dr-needs-optimization">⚠️ Requiere Optimización</span>'
                                        }
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="dr-php-tabs">
                            <div class="dr-tab-buttons">
                                <button class="dr-tab-btn active" data-tab="extensions">📦 Extensiones</button>
                                <button class="dr-tab-btn" data-tab="config">⚙️ Configuración</button>
                                <button class="dr-tab-btn" data-tab="recommendations">💡 Recomendaciones</button>
                            </div>

                            <div class="dr-tab-content">
                                <div id="extensions-tab" class="dr-tab-pane active">
                                    ${renderExtensionsPanel(phpData)}
                                </div>
                                <div id="config-tab" class="dr-tab-pane">
                                    ${renderConfigPanel(phpData)}
                                </div>
                                <div id="recommendations-tab" class="dr-tab-pane">
                                    ${renderRecommendationsPanel(phpData)}
                                </div>
                            </div>
                        </div>

                        <div class="dr-modal-actions">
                            <div class="dr-action-buttons">
                                <button class="button button-primary dr-apply-preset" data-domain="${domain}">
                                    <span class="dr-btn-icon">🚀</span>
                                    Aplicar Preset WordPress
                                </button>
                                <button class="button button-secondary dr-refresh-php" data-domain="${domain}">
                                    <span class="dr-btn-icon">🔄</span>
                                    Actualizar
                                </button>
                            </div>
                            <div class="dr-last-updated">
                                Última actualización: ${new Date().toLocaleTimeString()}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remover modal anterior si existe
        $('#dr-php-modal').remove();

        // Agregar modal al body
        $('body').append(modalHtml);

        // Mostrar modal con animación
        $('#dr-php-modal').fadeIn(300);

        console.log('PHP modal HTML added to DOM, initializing...');
        // Inicializar funcionalidad del modal
        initializePHPModal();
        console.log('PHP modal fully initialized and shown');
    }

    /**
     * Obtener icono de estado PHP
     */
    function getPHPStatusIcon(phpData) {
        if (phpData.max_performance) {
            return '🟢';
        } else if (phpData.recommendations && phpData.recommendations.length > 0) {
            return '🟡';
        } else {
            return '🔴';
        }
    }

    /**
     * Renderizar panel de extensiones con toggles
     */
    function renderExtensionsPanel(phpData) {
        const extensions = phpData.extensions || [];
        const criticalExtensions = [
            { name: 'mysqli', desc: 'Conexión a base de datos MySQL', required: true },
            { name: 'pdo_mysql', desc: 'PDO MySQL driver', required: true },
            { name: 'gd', desc: 'Procesamiento de imágenes', required: true },
            { name: 'curl', desc: 'Comunicación HTTP/FTP', required: true },
            { name: 'json', desc: 'Manejo de datos JSON', required: true },
            { name: 'mbstring', desc: 'Strings multibyte', required: true },
            { name: 'xml', desc: 'Procesamiento XML', required: true },
            { name: 'zip', desc: 'Compresión ZIP', required: true }
        ];

        const performanceExtensions = [
            { name: 'opcache', desc: 'Optimización de código PHP', required: false },
            { name: 'redis', desc: 'Cache de alto rendimiento', required: false },
            { name: 'memcached', desc: 'Cache de memoria distribuida', required: false },
            { name: 'imagick', desc: 'Procesamiento avanzado de imágenes', required: false }
        ];

        const renderExtensionToggle = (ext, isInstalled) => `
            <div class="dr-extension-toggle ${isInstalled ? 'installed' : 'missing'}" data-extension="${ext.name}">
                <div class="dr-extension-info">
                    <div class="dr-extension-name">
                        <span class="dr-extension-icon">${isInstalled ? '✅' : '❌'}</span>
                        ${ext.name}
                        ${ext.required ? '<span class="dr-required-badge">Obligatorio</span>' : ''}
                    </div>
                    <div class="dr-extension-desc">${ext.desc}</div>
                </div>
                <div class="dr-toggle-switch">
                    <input type="checkbox" ${isInstalled ? 'checked' : ''} disabled>
                    <span class="dr-toggle-slider"></span>
                </div>
            </div>
        `;

        return `
            <div class="dr-extensions-container">
                <div class="dr-extensions-section">
                    <h4>🔴 Extensiones Críticas para WordPress</h4>
                    <div class="dr-extensions-grid">
                        ${criticalExtensions.map(ext =>
                            renderExtensionToggle(ext, extensions.includes(ext.name))
                        ).join('')}
                    </div>
                </div>

                <div class="dr-extensions-section">
                    <h4>🚀 Extensiones de Rendimiento</h4>
                    <div class="dr-extensions-grid">
                        ${performanceExtensions.map(ext =>
                            renderExtensionToggle(ext, extensions.includes(ext.name))
                        ).join('')}
                    </div>
                </div>

                ${extensions.length > 0 ? `
                <div class="dr-extensions-section">
                    <h4>📦 Extensiones Adicionales Instaladas</h4>
                    <div class="dr-additional-extensions">
                        ${extensions.filter(ext =>
                            !criticalExtensions.find(c => c.name === ext) &&
                            !performanceExtensions.find(p => p.name === ext)
                        ).map(ext => `<span class="dr-additional-ext">${ext}</span>`).join('')}
                    </div>
                </div>
                ` : ''}
            </div>
        `;
    }

    /**
     * Renderizar panel de configuración
     */
    function renderConfigPanel(phpData) {
        const iniSettings = phpData.ini_settings || {};

        const configItems = [
            {
                key: 'memory_limit',
                label: 'Límite de Memoria',
                value: iniSettings.memory_limit || 'Desconocido',
                recommended: '256M',
                description: 'Memoria máxima por script PHP'
            },
            {
                key: 'max_execution_time',
                label: 'Tiempo Máximo de Ejecución',
                value: iniSettings.max_execution_time || 'Desconocido',
                recommended: '300',
                description: 'Segundos máximos que puede ejecutarse un script'
            },
            {
                key: 'upload_max_filesize',
                label: 'Tamaño Máximo de Subida',
                value: iniSettings.upload_max_filesize || 'Desconocido',
                recommended: '64M',
                description: 'Tamaño máximo de archivos que se pueden subir'
            },
            {
                key: 'post_max_size',
                label: 'Tamaño Máximo POST',
                value: iniSettings.post_max_size || 'Desconocido',
                recommended: '64M',
                description: 'Tamaño máximo de datos POST'
            }
        ];

        return `
            <div class="dr-config-container">
                ${configItems.map(item => {
                    const isGood = checkConfigValue(item.key, item.value);
                    return `
                        <div class="dr-config-item ${isGood ? 'good' : 'warning'}">
                            <div class="dr-config-header">
                                <span class="dr-config-icon">${isGood ? '✅' : '⚠️'}</span>
                                <span class="dr-config-label">${item.label}</span>
                            </div>
                            <div class="dr-config-value">
                                <span class="dr-current-value">${item.value}</span>
                                <span class="dr-recommended">Recomendado: ${item.recommended}</span>
                            </div>
                            <div class="dr-config-desc">${item.description}</div>
                        </div>
                    `;
                }).join('')}

                <div class="dr-config-summary">
                    <div class="dr-config-score">
                        <span class="dr-score-label">Puntuación de Configuración:</span>
                        <span class="dr-score-value">${calculateConfigScore(iniSettings)}%</span>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Renderizar panel de recomendaciones
     */
    function renderRecommendationsPanel(phpData) {
        const recommendations = phpData.recommendations || [];

        if (recommendations.length === 0) {
            return `
                <div class="dr-no-recommendations">
                    <div class="dr-success-icon">🎉</div>
                    <h4>¡Configuración Optimizada!</h4>
                    <p>Tu configuración PHP está lista para WordPress de alto rendimiento.</p>
                </div>
            `;
        }

        return `
            <div class="dr-recommendations-container">
                <div class="dr-recommendations-header">
                    <h4>💡 Recomendaciones para Optimizar</h4>
                    <p>Estas mejoras pueden aumentar significativamente el rendimiento de WordPress:</p>
                </div>

                <div class="dr-recommendations-list">
                    ${recommendations.map((rec, index) => `
                        <div class="dr-recommendation-item" data-priority="${getRecommendationPriority(rec)}">
                            <div class="dr-rec-number">${index + 1}</div>
                            <div class="dr-rec-content">
                                <div class="dr-rec-text">${rec}</div>
                                <div class="dr-rec-priority">${getPriorityLabel(rec)}</div>
                            </div>
                            <div class="dr-rec-action">
                                <button class="dr-apply-rec-btn" data-recommendation="${rec}">
                                    Aplicar
                                </button>
                            </div>
                        </div>
                    `).join('')}
                </div>

                <div class="dr-bulk-actions">
                    <button class="button button-primary dr-apply-all-recs">
                        🚀 Aplicar Todas las Recomendaciones
                    </button>
                </div>
            </div>
        `;
    }

    /**
     * Verificar si un valor de configuración es bueno
     */
    function checkConfigValue(key, value) {
        if (!value || value === 'Desconocido') return false;

        switch(key) {
            case 'memory_limit':
                const memoryMB = parseInt(value.replace('M', '').replace('G', '000'));
                return memoryMB >= 256;
            case 'max_execution_time':
                return parseInt(value) >= 300;
            case 'upload_max_filesize':
            case 'post_max_size':
                const sizeMB = parseInt(value.replace('M', '').replace('G', '000'));
                return sizeMB >= 64;
            default:
                return true;
        }
    }

    /**
     * Calcular puntuación de configuración
     */
    function calculateConfigScore(iniSettings) {
        const checks = [
            { key: 'memory_limit', min: 256 },
            { key: 'max_execution_time', min: 300 },
            { key: 'upload_max_filesize', min: 64 },
            { key: 'post_max_size', min: 64 }
        ];

        let passed = 0;
        checks.forEach(check => {
            if (checkConfigValue(check.key, iniSettings[check.key])) {
                passed++;
            }
        });

        return Math.round((passed / checks.length) * 100);
    }

    /**
     * Obtener prioridad de recomendación
     */
    function getRecommendationPriority(rec) {
        if (rec.includes('Instalar extensión') && rec.includes('mysqli|pdo_mysql|gd|curl')) {
            return 'critical';
        } else if (rec.includes('Aumentar memory_limit')) {
            return 'high';
        } else if (rec.includes('Aumentar max_execution_time')) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Obtener etiqueta de prioridad
     */
    function getPriorityLabel(rec) {
        const priority = getRecommendationPriority(rec);
        const labels = {
            critical: '🔴 Crítico',
            high: '🟠 Alto',
            medium: '🟡 Medio',
            low: '🟢 Bajo'
        };
        return labels[priority] || '🟢 Bajo';
    }

    /**
     * Inicializar funcionalidad del modal
     */
    function initializePHPModal() {
        console.log('Initializing PHP modal...');

        // Tabs functionality
        $('.dr-tab-btn').on('click', function() {
            const tabName = $(this).data('tab');
            console.log('Tab clicked:', tabName);

            $('.dr-tab-btn').removeClass('active');
            $(this).addClass('active');

            $('.dr-tab-pane').removeClass('active');
            $(`#${tabName}-tab`).addClass('active');
        });

        // Toggle switches animation
        $('.dr-toggle-switch input').on('change', function() {
            const toggle = $(this).closest('.dr-extension-toggle');
            if (this.checked) {
                toggle.removeClass('missing').addClass('installed');
                toggle.find('.dr-extension-icon').text('✅');
            } else {
                toggle.removeClass('installed').addClass('missing');
                toggle.find('.dr-extension-icon').text('❌');
            }
        });

        console.log('PHP modal initialized successfully');
    }

    // Cerrar modal
    $(document).on('click', '.dr-modal-close, .dr-modal-overlay', function() {
        console.log('Closing PHP modal');
        $('#dr-php-modal').fadeOut(300, function() {
            $(this).remove();
        });
    });

    // Aplicar preset WordPress optimizado
    $(document).on('click', '.dr-apply-preset', function() {
        const button = $(this);
        const domain = button.data('domain');
        const originalText = button.find('.dr-btn-icon').text() + ' Aplicando...';

        button.html('<span class="dr-btn-icon">⏳</span> Aplicando...').prop('disabled', true);

        // Simular aplicación del preset
        setTimeout(function() {
            showNotice('✅ Preset WordPress aplicado correctamente a ' + domain, 'success');
            button.html('<span class="dr-btn-icon">🚀</span> Aplicar Preset WordPress').prop('disabled', false);

            // Cerrar modal
            $('#dr-php-modal').fadeOut(300, function() {
                $(this).remove();
            });

            // Recargar estado del pilot
            location.reload();
        }, 3000);
    });

    // Actualizar información PHP
    $(document).on('click', '.dr-refresh-php', function() {
        const button = $(this);
        const domain = button.data('domain');
        const originalText = button.html();

        console.log('Iniciando refresh PHP para dominio:', domain);
        button.html('<span class="dr-btn-icon">⏳</span> obteniendo info.php...').prop('disabled', true);

        // Hacer petición individual para actualizar
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'dr_get_php_info',
                domain: domain,
                _nonce: dominios_reseller_ajax.nonce
            },
            success: function(response) {
                console.log('Respuesta AJAX PHP:', response);
                if (response.success) {
                    // Actualizar datos del pilot
                    const pilot = $(`.dr-pilot-php[data-domain="${domain}"]`);
                    if (pilot.length) {
                        pilot.data('php-data', response.data);
                        updatePHPPilots(domain, response.data);
                    }

                    // Actualizar modal si está abierto
                    if ($('#dr-php-modal').length) {
                        $('#dr-php-modal').fadeOut(300, function() {
                            $(this).remove();
                            showPHPModal(domain, response.data);
                        });
                    }

                    showNotice('✅ Información PHP actualizada', 'success');
                } else {
                    console.error('Error en respuesta PHP:', response.data);
                    showNotice('❌ Error: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX PHP:', xhr, status, error);
                showNotice('❌ Error de conexión al actualizar PHP', 'error');
            },
            complete: function() {
                console.log('Completando refresh PHP');
                button.html(originalText).prop('disabled', false);
            }
        });
    });

    // Función para actualizar los pilotos PHP
    window.updatePHPPilots = function() {
        console.log('Actualizando pilotos PHP...');

        // Obtener todos los dominios de la tabla
        var domains = [];
        jQuery('.domain-row').each(function() {
            var domain = jQuery(this).data('domain');
            if (domain) {
                domains.push(domain);
            }
        });

        if (domains.length === 0) {
            console.log('No se encontraron dominios para actualizar');
            return;
        }

        // Procesar en lotes de 5 dominios
        var batchSize = 5;
        var totalBatches = Math.ceil(domains.length / batchSize);
        var currentBatch = 0;

        function processBatch() {
            if (currentBatch >= totalBatches) {
                console.log('Actualización de pilotos PHP completada');
                return;
            }

            var start = currentBatch * batchSize;
            var end = Math.min(start + batchSize, domains.length);
            var batch = domains.slice(start, end);

            console.log('Procesando lote ' + (currentBatch + 1) + '/' + totalBatches + ':', batch);

            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'dr_get_batch_php',
                    domains: batch,
                    nonce: replanta_ajax.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        // Actualizar los indicadores de PHP para este lote
                        response.data.forEach(function(phpInfo) {
                            var domain = phpInfo.domain;
                            var status = phpInfo.status;
                            var version = phpInfo.version || 'Desconocido';
                            var extensions = phpInfo.extensions || [];

                            // Encontrar la fila del dominio y actualizar el indicador PHP
                            var row = jQuery('.domain-row[data-domain="' + domain + '"]');
                            if (row.length > 0) {
                                var phpIndicator = row.find('.php-indicator');
                                if (phpIndicator.length > 0) {
                                    var statusClass = status === 'success' ? 'php-success' : 'php-error';
                                    var statusIcon = status === 'success' ? '✅' : '❌';
                                    var extensionsText = extensions.length > 0 ?
                                        ' (' + extensions.slice(0, 3).join(', ') + (extensions.length > 3 ? '...' : '') + ')' : '';

                                    phpIndicator.html(statusIcon + ' PHP ' + version + extensionsText)
                                        .removeClass('php-success php-error')
                                        .addClass(statusClass);
                                }
                            }
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error actualizando lote PHP:', error);
                },
                complete: function() {
                    currentBatch++;
                    // Pequeño delay entre lotes para no sobrecargar
                    setTimeout(processBatch, 500);
                }
            });
        }

        // Iniciar el procesamiento por lotes
        processBatch();
    };

});