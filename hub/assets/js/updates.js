/**
 * JavaScript para el sistema de actualizaciones inteligentes
 * Maneja la configuración y programación de actualizaciones
 */

(function($) {
    'use strict';

    window.RphubUpdates = {
        siteId: null,
        
        init: function() {
            this.siteId = rphub_ajax.site_id;
            this.bindEvents();
            this.loadUpdateHistory();
        },
        
        bindEvents: function() {
            var self = this;
            
            // Verificar actualizaciones
            $('#check-updates').on('click', function(e) {
                e.preventDefault();
                self.checkUpdates();
            });
            
            // Configurar actualizaciones automáticas
            $('#configure-auto-updates').on('click', function(e) {
                e.preventDefault();
                self.openAutoUpdatesModal();
            });
            
            // Programar actualización individual
            $('.schedule-update').on('click', function(e) {
                e.preventDefault();
                var type = $(this).data('type');
                var item = $(this).data('item');
                self.openScheduleModal([{type: type, item: item}]);
            });
            
            // Actualizar ahora
            $('.update-now').on('click', function(e) {
                e.preventDefault();
                var type = $(this).data('type');
                var item = $(this).data('item');
                self.updateNow(type, item);
            });
            
            // Programar todas las actualizaciones
            $('#schedule-all-updates').on('click', function(e) {
                e.preventDefault();
                self.scheduleAllUpdates();
            });
            
            // Actualizar todo ahora
            $('#update-all-now').on('click', function(e) {
                e.preventDefault();
                self.updateAllNow();
            });
            
            // Modal events
            $('.modal-close, #cancel-auto-updates-config').on('click', function() {
                $('#auto-updates-modal').hide();
            });
            
            $('#cancel-schedule-update').on('click', function() {
                $('#schedule-update-modal').hide();
            });
            
            // Guardar configuración de auto-updates
            $('#save-auto-updates-config').on('click', function(e) {
                e.preventDefault();
                self.saveAutoUpdatesConfig();
            });
            
            // Confirmar programación
            $('#confirm-schedule-update').on('click', function(e) {
                e.preventDefault();
                self.confirmScheduleUpdate();
            });
            
            // Cargar más historial
            $('#load-more-history').on('click', function(e) {
                e.preventDefault();
                self.loadMoreHistory();
            });
            
            // Toggle de auto-updates
            $('#enable-auto-updates').on('change', function() {
                $('.update-types, .schedule-config, .safety-options').toggle($(this).is(':checked'));
            });
        },
        
        checkUpdates: function() {
            var self = this;
            var button = $('#check-updates');
            var originalText = button.text();
            
            button.prop('disabled', true).text('Verificando...');
            
            $.ajax({
                url: rphub_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rphub_check_individual_updates',
                    site_id: this.siteId,
                    nonce: rphub_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        RphubDashboard.showNotification('success', 'Verificación de actualizaciones completada');
                        
                        // Recargar la página para mostrar nuevas actualizaciones
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        RphubDashboard.showNotification('error', response.data || 'Error al verificar actualizaciones');
                    }
                },
                error: function() {
                    RphubDashboard.showNotification('error', 'Error de conexión');
                },
                complete: function() {
                    button.prop('disabled', false).text(originalText);
                }
            });
        },
        
        openAutoUpdatesModal: function() {
            $('#auto-updates-modal').show();
            
            // Toggle sections based on enable checkbox
            var enabled = $('#enable-auto-updates').is(':checked');
            $('.update-types, .schedule-config, .safety-options').toggle(enabled);
        },
        
        saveAutoUpdatesConfig: function() {
            var self = this;
            var formData = $('#auto-updates-form').serialize();
            
            // Recopilar configuración
            var config = {
                enabled: $('#enable-auto-updates').is(':checked'),
                core: {
                    enabled: $('input[name="core_enabled"]').is(':checked'),
                    major_versions: $('input[name="core_major"]').is(':checked')
                },
                plugins: {
                    enabled: $('input[name="plugins_enabled"]').is(':checked'),
                    excluded_plugins: []
                },
                themes: {
                    enabled: $('input[name="themes_enabled"]').is(':checked'),
                    excluded_themes: []
                },
                schedule: {
                    start_time: $('input[name="start_time"]').val(),
                    end_time: $('input[name="end_time"]').val(),
                    days: []
                },
                backup_before_update: $('input[name="backup_before_update"]').is(':checked'),
                rollback_on_failure: $('input[name="rollback_on_failure"]').is(':checked')
            };
            
            // Recopilar días seleccionados
            $('input[name="schedule_days[]"]:checked').each(function() {
                config.schedule.days.push(parseInt($(this).val()));
            });
            
            var button = $('#save-auto-updates-config');
            var originalText = button.text();
            button.prop('disabled', true).text('Guardando...');
            
            $.ajax({
                url: rphub_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rphub_configure_auto_updates',
                    site_id: this.siteId,
                    config: config,
                    nonce: rphub_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        RphubDashboard.showNotification('success', response.data.message);
                        $('#auto-updates-modal').hide();
                        
                        // Recargar para mostrar nueva configuración
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        RphubDashboard.showNotification('error', response.data || 'Error al guardar configuración');
                    }
                },
                error: function() {
                    RphubDashboard.showNotification('error', 'Error de conexión');
                },
                complete: function() {
                    button.prop('disabled', false).text(originalText);
                }
            });
        },
        
        openScheduleModal: function(updates) {
            var updatesList = $('#selected-updates-list');
            updatesList.empty();
            
            if (updates && updates.length > 0) {
                var html = '<h4>Actualizaciones seleccionadas:</h4><ul>';
                updates.forEach(function(update) {
                    html += '<li>' + update.item.name + ' (' + update.type + ')</li>';
                });
                html += '</ul>';
                updatesList.html(html);
            }
            
            // Establecer fecha mínima a ahora
            var now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            $('input[name="schedule_datetime"]').attr('min', now.toISOString().slice(0, 16));
            
            $('#schedule-update-modal').show();
        },
        
        scheduleAllUpdates: function() {
            var updates = [];
            
            $('.update-item').each(function() {
                var scheduleBtn = $(this).find('.schedule-update');
                if (scheduleBtn.length > 0) {
                    updates.push({
                        type: scheduleBtn.data('type'),
                        item: scheduleBtn.data('item')
                    });
                }
            });
            
            if (updates.length === 0) {
                RphubDashboard.showNotification('warning', 'No hay actualizaciones disponibles para programar');
                return;
            }
            
            this.openScheduleModal(updates);
        },
        
        confirmScheduleUpdate: function() {
            var self = this;
            var datetime = $('input[name="schedule_datetime"]').val();
            var createBackup = $('input[name="create_backup"]').is(':checked');
            
            if (!datetime) {
                RphubDashboard.showNotification('error', 'Por favor selecciona fecha y hora');
                return;
            }
            
            // Recopilar tipos de actualizaciones
            var updateTypes = [];
            $('.update-item').each(function() {
                var scheduleBtn = $(this).find('.schedule-update');
                if (scheduleBtn.length > 0) {
                    var type = scheduleBtn.data('type');
                    if (updateTypes.indexOf(type) === -1) {
                        updateTypes.push(type);
                    }
                }
            });
            
            var button = $('#confirm-schedule-update');
            var originalText = button.text();
            button.prop('disabled', true).text('Programando...');
            
            $.ajax({
                url: rphub_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rphub_schedule_update',
                    site_id: this.siteId,
                    update_types: updateTypes,
                    schedule_time: datetime,
                    create_backup: createBackup,
                    nonce: rphub_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        RphubDashboard.showNotification('success', response.data.message);
                        $('#schedule-update-modal').hide();
                        
                        // Recargar para actualizar estado
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        RphubDashboard.showNotification('error', response.data || 'Error al programar actualización');
                    }
                },
                error: function() {
                    RphubDashboard.showNotification('error', 'Error de conexión');
                },
                complete: function() {
                    button.prop('disabled', false).text(originalText);
                }
            });
        },
        
        updateNow: function(type, item) {
            var self = this;
            
            if (!confirm('¿Estás seguro de que quieres ejecutar esta actualización ahora? Se recomienda crear un backup primero.')) {
                return;
            }
            
            RphubDashboard.showLoading('Ejecutando actualización...');
            
            $.ajax({
                url: rphub_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rphub_execute_immediate_update',
                    site_id: this.siteId,
                    update_type: type,
                    update_item: item,
                    create_backup: true,
                    nonce: rphub_ajax.nonce
                },
                success: function(response) {
                    RphubDashboard.hideLoading();
                    
                    if (response.success) {
                        RphubDashboard.showNotification('success', 'Actualización completada exitosamente');
                        
                        // Recargar para mostrar nuevos datos
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        RphubDashboard.showNotification('error', response.data || 'Error en la actualización');
                    }
                },
                error: function() {
                    RphubDashboard.hideLoading();
                    RphubDashboard.showNotification('error', 'Error de conexión');
                }
            });
        },
        
        updateAllNow: function() {
            var self = this;
            
            if (!confirm('¿Estás seguro de que quieres ejecutar TODAS las actualizaciones ahora? Esto puede tomar varios minutos y se creará un backup automáticamente.')) {
                return;
            }
            
            // Recopilar todos los tipos de actualizaciones
            var updateTypes = [];
            $('.update-type-section').each(function() {
                var type = $(this).find('h5').text().toLowerCase().split(' ')[0];
                updateTypes.push(type);
            });
            
            RphubDashboard.showLoading('Ejecutando todas las actualizaciones...');
            
            $.ajax({
                url: rphub_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rphub_execute_immediate_update',
                    site_id: this.siteId,
                    update_type: 'all',
                    update_types: updateTypes,
                    create_backup: true,
                    nonce: rphub_ajax.nonce
                },
                timeout: 300000, // 5 minutos timeout
                success: function(response) {
                    RphubDashboard.hideLoading();
                    
                    if (response.success) {
                        RphubDashboard.showNotification('success', 'Todas las actualizaciones completadas exitosamente');
                        
                        // Recargar para mostrar nuevos datos
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        RphubDashboard.showNotification('error', response.data || 'Error en las actualizaciones');
                    }
                },
                error: function(xhr, status, error) {
                    RphubDashboard.hideLoading();
                    
                    if (status === 'timeout') {
                        RphubDashboard.showNotification('warning', 'Las actualizaciones están tomando más tiempo del esperado. Verifica el estado en unos minutos.');
                    } else {
                        RphubDashboard.showNotification('error', 'Error de conexión: ' + error);
                    }
                }
            });
        },
        
        loadUpdateHistory: function() {
            var self = this;
            
            $.ajax({
                url: rphub_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rphub_get_update_history',
                    site_id: this.siteId,
                    limit: 10,
                    nonce: rphub_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderUpdateHistory(response.data);
                    } else {
                        $('#update-history-list').html('<p>No se pudo cargar el historial</p>');
                    }
                },
                error: function() {
                    $('#update-history-list').html('<p>Error al cargar el historial</p>');
                }
            });
        },
        
        renderUpdateHistory: function(history) {
            var container = $('#update-history-list');
            container.empty();
            
            if (!history || history.length === 0) {
                container.html('<p>No hay historial de actualizaciones</p>');
                return;
            }
            
            var html = '<div class="history-list">';
            
            history.forEach(function(event) {
                var eventClass = self.getEventClass(event.event_type);
                var eventIcon = self.getEventIcon(event.event_type);
                var eventText = self.getEventText(event.event_type, event.data);
                
                html += '<div class="history-item ' + eventClass + '">';
                html += '<div class="history-icon">' + eventIcon + '</div>';
                html += '<div class="history-content">';
                html += '<div class="history-text">' + eventText + '</div>';
                html += '<div class="history-time">' + self.formatDateTime(event.timestamp) + '</div>';
                html += '</div>';
                html += '</div>';
            });
            
            html += '</div>';
            container.html(html);
        },
        
        getEventClass: function(eventType) {
            switch (eventType) {
                case 'update_completed':
                    return 'success';
                case 'update_failed':
                    return 'error';
                case 'automatic_rollback':
                    return 'warning';
                case 'update_scheduled':
                    return 'info';
                default:
                    return 'neutral';
            }
        },
        
        getEventIcon: function(eventType) {
            switch (eventType) {
                case 'update_completed':
                    return '<span class="dashicons dashicons-yes-alt"></span>';
                case 'update_failed':
                    return '<span class="dashicons dashicons-dismiss"></span>';
                case 'automatic_rollback':
                    return '<span class="dashicons dashicons-undo"></span>';
                case 'update_scheduled':
                    return '<span class="dashicons dashicons-calendar-alt"></span>';
                case 'updates_found':
                    return '<span class="dashicons dashicons-search"></span>';
                default:
                    return '<span class="dashicons dashicons-info"></span>';
            }
        },
        
        getEventText: function(eventType, data) {
            switch (eventType) {
                case 'update_completed':
                    return 'Actualizaciones completadas exitosamente';
                case 'update_failed':
                    return 'Error en actualización: ' + (data.error || 'Error desconocido');
                case 'automatic_rollback':
                    return 'Rollback automático ejecutado';
                case 'update_scheduled':
                    return 'Actualización programada';
                case 'updates_found':
                    return data.count + ' actualizaciones encontradas (' + data.types.join(', ') + ')';
                default:
                    return 'Evento: ' + eventType;
            }
        },
        
        formatDateTime: function(datetime) {
            var date = new Date(datetime);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        },
        
        loadMoreHistory: function() {
            // Implementar carga de más historial si es necesario
            var currentItems = $('.history-item').length;
            var self = this;
            
            $.ajax({
                url: rphub_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rphub_get_update_history',
                    site_id: this.siteId,
                    limit: 10,
                    offset: currentItems,
                    nonce: rphub_ajax.nonce
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        // Agregar nuevos elementos al historial existente
                        self.appendUpdateHistory(response.data);
                    } else {
                        $('#load-more-history').hide();
                    }
                }
            });
        },
        
        appendUpdateHistory: function(newHistory) {
            var container = $('.history-list');
            var self = this;
            
            newHistory.forEach(function(event) {
                var eventClass = self.getEventClass(event.event_type);
                var eventIcon = self.getEventIcon(event.event_type);
                var eventText = self.getEventText(event.event_type, event.data);
                
                var html = '<div class="history-item ' + eventClass + '">';
                html += '<div class="history-icon">' + eventIcon + '</div>';
                html += '<div class="history-content">';
                html += '<div class="history-text">' + eventText + '</div>';
                html += '<div class="history-time">' + self.formatDateTime(event.timestamp) + '</div>';
                html += '</div>';
                html += '</div>';
                
                container.append(html);
            });
        }
    };
    
    // Inicializar cuando el documento esté listo
    $(document).ready(function() {
        if (typeof rphub_ajax !== 'undefined' && rphub_ajax.site_id) {
            RphubUpdates.init();
        }
    });
    
})(jQuery);

// CSS adicional para el historial
var historyStyles = `
<style>
.history-list {
    max-height: 400px;
    overflow-y: auto;
}

.history-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px;
    border-bottom: 1px solid #f3f4f6;
    transition: background-color 0.2s;
}

.history-item:hover {
    background-color: #f9fafb;
}

.history-item:last-child {
    border-bottom: none;
}

.history-icon {
    flex-shrink: 0;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}

.history-item.success .history-icon {
    background: #d1fae5;
    color: #065f46;
}

.history-item.error .history-icon {
    background: #fee2e2;
    color: #991b1b;
}

.history-item.warning .history-icon {
    background: #fef3c7;
    color: #92400e;
}

.history-item.info .history-icon {
    background: #dbeafe;
    color: #1e40af;
}

.history-item.neutral .history-icon {
    background: #f3f4f6;
    color: #6b7280;
}

.history-content {
    flex: 1;
}

.history-text {
    font-weight: 500;
    color: #374151;
    margin-bottom: 4px;
}

.history-time {
    font-size: 12px;
    color: #6b7280;
}
</style>
`;

$('head').append(historyStyles);
