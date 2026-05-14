/**
 * Replanta Auto Translate - Admin JavaScript
 */

(function($) {
    'use strict';
    
    var RAT = {
        /**
         * Inicializar
         */
        init: function() {
            this.bindEvents();
        },
        
        /**
         * Vincular eventos
         */
        bindEvents: function() {
            // Traducir post individual
            $(document).on('click', '.translate-single', this.translateSingle);
            
            // Traducir todas las paginas
            $('#translate-all-pages').on('click', this.translateAllPages);
            
            // Traducir todos los posts
            $('#translate-all-posts').on('click', this.translateAllPosts);
            
            // Traducir todos los templates
            $('#translate-all-templates').on('click', this.translateAllTemplates);
            
            // Traducir seleccionados
            $('#translate-selected-pages, #translate-selected-posts, #translate-selected-templates').on('click', this.translateSelected);
            
            // Traducir menus
            $('#translate-menus').on('click', this.translateMenus);
            
            // Cancelar traduccion
            $('#cancel-translation').on('click', this.cancelTranslation);
            
            // Seleccionar todos
            $('#select-all-pages').on('change', function() {
                $('.page-checkbox').prop('checked', $(this).is(':checked'));
            });
            $('#select-all-posts').on('change', function() {
                $('.post-checkbox').prop('checked', $(this).is(':checked'));
            });
            $('#select-all-templates').on('change', function() {
                $('.template-checkbox').prop('checked', $(this).is(':checked'));
            });
            
            // Test de conexion
            $('#test-openai-connection').on('click', function() {
                RAT.testConnection('openai');
            });
            $('#test-google-connection').on('click', function() {
                RAT.testConnection('google');
            });
        },
        
        /**
         * Traducir post individual
         */
        translateSingle: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var postId = $button.data('post-id');
            var $status = $button.siblings('.translation-status');
            var $row = $button.closest('tr');
            
            if ($button.hasClass('translating')) {
                return;
            }
            
            $button.addClass('translating').text(replantaAutoTranslate.strings.translating);
            $status.removeClass('success error').addClass('loading').text('');
            
            $.ajax({
                url: replantaAutoTranslate.ajax_url,
                type: 'POST',
                data: {
                    action: 'replanta_translate_single',
                    nonce: replantaAutoTranslate.nonce,
                    post_id: postId
                },
                success: function(response) {
                    $button.removeClass('translating');
                    
                    if (response.success) {
                        $button.text(replantaAutoTranslate.strings.translated).prop('disabled', true);
                        $status.removeClass('loading').addClass('success').text('ID: ' + response.data.translated_id);
                        $row.addClass('translated');
                        RAT.showNotification('Traducido: ' + response.data.title, 'success');
                    } else {
                        $button.text('Traducir');
                        $status.removeClass('loading').addClass('error').text(response.data.message);
                        RAT.showNotification('Error: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    $button.removeClass('translating').text('Traducir');
                    $status.removeClass('loading').addClass('error').text('Error de conexion');
                    RAT.showNotification('Error de conexion', 'error');
                }
            });
        },
        
        /**
         * Traducir todas las paginas
         */
        translateAllPages: function(e) {
            e.preventDefault();
            
            var count = $(this).data('count');
            if (!confirm(replantaAutoTranslate.strings.confirm_bulk.replace('%d', count))) {
                return;
            }
            
            RAT.startBulkTranslation('page');
        },
        
        /**
         * Traducir todos los posts
         */
        translateAllPosts: function(e) {
            e.preventDefault();
            
            var count = $(this).data('count');
            if (!confirm(replantaAutoTranslate.strings.confirm_bulk.replace('%d', count))) {
                return;
            }
            
            RAT.startBulkTranslation('post');
        },
        
        /**
         * Traducir todos los templates
         */
        translateAllTemplates: function(e) {
            e.preventDefault();
            
            var count = $(this).data('count');
            if (!confirm(replantaAutoTranslate.strings.confirm_bulk.replace('%d', count))) {
                return;
            }
            
            RAT.startBulkTranslation('elementor_library');
        },
        
        /**
         * Traducir seleccionados
         */
        translateSelected: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var buttonId = $button.attr('id');
            var checkboxClass;
            
            if (buttonId === 'translate-selected-pages') {
                checkboxClass = '.page-checkbox';
            } else if (buttonId === 'translate-selected-posts') {
                checkboxClass = '.post-checkbox';
            } else if (buttonId === 'translate-selected-templates') {
                checkboxClass = '.template-checkbox';
            } else {
                checkboxClass = '.page-checkbox, .post-checkbox, .template-checkbox';
            }
            
            var selectedIds = [];
            $(checkboxClass + ':checked').each(function() {
                selectedIds.push($(this).val());
            });
            
            if (selectedIds.length === 0) {
                alert('Selecciona al menos un elemento para traducir');
                return;
            }
            
            RAT.startBulkTranslation('mixed', selectedIds);
        },
        
        /**
         * Iniciar traduccion masiva
         */
        startBulkTranslation: function(postType, postIds) {
            var $progress = $('#translation-progress');
            var $progressBar = $progress.find('.progress-bar');
            var $progressText = $progress.find('.progress-text');
            var $progressLog = $progress.find('.progress-log');
            var $current = $progress.find('.current');
            var $total = $progress.find('.total');
            
            // Mostrar seccion de progreso
            $progress.show();
            $progressBar.css('width', '0%').removeClass('completed error');
            $progressLog.empty();
            
            // Deshabilitar botones
            $('.action-buttons .button, .translate-single').prop('disabled', true);
            
            // Iniciar proceso
            var data = {
                action: 'replanta_translate_bulk_start',
                nonce: replantaAutoTranslate.nonce,
                post_type: postType
            };
            
            if (postIds && postIds.length > 0) {
                data.post_ids = postIds.join(',');
            }
            
            $.ajax({
                url: replantaAutoTranslate.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        $total.text(response.data.total);
                        $current.text('0');
                        RAT.addLogEntry('Iniciando traduccion de ' + response.data.total + ' elementos...', 'info');
                        RAT.processBulkBatch();
                    } else {
                        RAT.addLogEntry('Error: ' + response.data.message, 'error');
                        RAT.finishBulkTranslation(false);
                    }
                },
                error: function() {
                    RAT.addLogEntry('Error de conexion', 'error');
                    RAT.finishBulkTranslation(false);
                }
            });
        },
        
        /**
         * Procesar siguiente lote
         */
        processBulkBatch: function() {
            var $progress = $('#translation-progress');
            var $progressBar = $progress.find('.progress-bar');
            var $current = $progress.find('.current');
            var $total = $progress.find('.total');
            
            $.ajax({
                url: replantaAutoTranslate.ajax_url,
                type: 'POST',
                data: {
                    action: 'replanta_translate_bulk_process',
                    nonce: replantaAutoTranslate.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var state = response.data.state;
                        var percent = (state.processed / state.total) * 100;
                        
                        $progressBar.css('width', percent + '%');
                        $current.text(state.processed);
                        
                        // Agregar logs de resultados
                        if (response.data.results) {
                            response.data.results.forEach(function(result) {
                                if (result.success) {
                                    RAT.addLogEntry('Traducido: ' + result.title + ' (ID: ' + result.translated_id + ')', 'success');
                                } else {
                                    RAT.addLogEntry('Error en post #' + result.post_id + ': ' + result.error, 'error');
                                }
                            });
                        }
                        
                        // Continuar o finalizar
                        if (response.data.completed) {
                            $progressBar.addClass('completed');
                            RAT.addLogEntry('Proceso completado. ' + state.successful + ' exitosos, ' + state.failed + ' errores.', 'info');
                            RAT.finishBulkTranslation(true);
                        } else {
                            // Continuar con el siguiente lote
                            RAT.processBulkBatch();
                        }
                    } else {
                        RAT.addLogEntry('Error: ' + response.data.message, 'error');
                        RAT.finishBulkTranslation(false);
                    }
                },
                error: function() {
                    RAT.addLogEntry('Error de conexion durante el proceso', 'error');
                    RAT.finishBulkTranslation(false);
                }
            });
        },
        
        /**
         * Cancelar traduccion
         */
        cancelTranslation: function(e) {
            e.preventDefault();
            
            if (!confirm('Seguro que quieres cancelar el proceso?')) {
                return;
            }
            
            $.ajax({
                url: replantaAutoTranslate.ajax_url,
                type: 'POST',
                data: {
                    action: 'replanta_translate_bulk_cancel',
                    nonce: replantaAutoTranslate.nonce
                },
                success: function(response) {
                    RAT.addLogEntry('Proceso cancelado por el usuario', 'info');
                    RAT.finishBulkTranslation(false);
                }
            });
        },
        
        /**
         * Finalizar traduccion masiva
         */
        finishBulkTranslation: function(success) {
            var $progress = $('#translation-progress');
            var $progressBar = $progress.find('.progress-bar');
            
            // Habilitar botones
            $('.action-buttons .button').prop('disabled', false);
            
            if (!success) {
                $progressBar.addClass('error');
            }
            
            // Mostrar notificacion
            if (success) {
                RAT.showNotification('Traduccion completada', 'success');
            } else {
                RAT.showNotification('Proceso finalizado con errores', 'error');
            }
        },
        
        /**
         * Traducir menus
         */
        translateMenus: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            
            if ($button.hasClass('translating')) {
                return;
            }
            
            $button.addClass('translating').text(replantaAutoTranslate.strings.translating);
            
            $.ajax({
                url: replantaAutoTranslate.ajax_url,
                type: 'POST',
                data: {
                    action: 'replanta_translate_menus',
                    nonce: replantaAutoTranslate.nonce
                },
                success: function(response) {
                    $button.removeClass('translating').text('Traducir Menus');
                    
                    if (response.success) {
                        var msg = 'Menus traducidos: ' + response.data.menus_translated;
                        if (response.data.errors > 0) {
                            msg += ' (' + response.data.errors + ' errores)';
                        }
                        RAT.showNotification(msg, response.data.errors > 0 ? 'error' : 'success');
                    } else {
                        RAT.showNotification('Error: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    $button.removeClass('translating').text('Traducir Menus');
                    RAT.showNotification('Error de conexion', 'error');
                }
            });
        },
        
        /**
         * Probar conexion con API
         */
        testConnection: function(engine) {
            var $result = $('#connection-test-result');
            $result.removeClass('success error').text('Probando conexion...');
            
            $.ajax({
                url: replantaAutoTranslate.ajax_url,
                type: 'POST',
                data: {
                    action: 'replanta_test_connection',
                    nonce: replantaAutoTranslate.nonce,
                    engine: engine
                },
                success: function(response) {
                    if (response.success) {
                        $result.addClass('success').html(
                            '<strong>' + response.data.message + '</strong><br>' +
                            'Test: "Hello" -> "' + response.data.test_translation + '"'
                        );
                    } else {
                        $result.addClass('error').text('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    $result.addClass('error').text('Error de conexion');
                }
            });
        },
        
        /**
         * Agregar entrada al log
         */
        addLogEntry: function(message, type) {
            var $log = $('#translation-progress .progress-log');
            var timestamp = new Date().toLocaleTimeString();
            var $entry = $('<div class="log-entry log-' + type + '">[' + timestamp + '] ' + message + '</div>');
            $log.append($entry);
            $log.scrollTop($log[0].scrollHeight);
        },
        
        /**
         * Mostrar notificacion
         */
        showNotification: function(message, type) {
            var $notification = $('<div class="replanta-notification ' + type + '">' + message + '</div>');
            $('body').append($notification);
            
            setTimeout(function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 4000);
        },
        
        /**
         * Completar todo: plantillas, referencias y menús
         */
        fixAll: function() {
            var $button = $('#fix-all-translations');
            var $progress = $('#fix-all-progress');
            var $log = $progress.find('.progress-log');
            
            $button.prop('disabled', true).text('Procesando...');
            $progress.show();
            $log.empty();
            
            RAT.addFixLogEntry('Iniciando proceso completo...', 'info', $log);
            
            // Ejecutar pasos secuencialmente
            RAT.fixAllStep('templates', $log)
                .then(function() {
                    return RAT.fixAllStep('references', $log);
                })
                .then(function() {
                    return RAT.fixAllStep('menus', $log);
                })
                .then(function() {
                    RAT.addFixLogEntry('¡Proceso completado!', 'success', $log);
                    $button.prop('disabled', false).text('Completar Todo');
                    RAT.showNotification('Traducción completada', 'success');
                })
                .catch(function(error) {
                    RAT.addFixLogEntry('Error: ' + error, 'error', $log);
                    $button.prop('disabled', false).text('Completar Todo');
                    RAT.showNotification('Error en el proceso', 'error');
                });
        },
        
        /**
         * Ejecutar un paso del fix all
         */
        fixAllStep: function(step, $log) {
            var stepNames = {
                'templates': 'Traduciendo plantillas',
                'references': 'Actualizando referencias',
                'menus': 'Configurando menús'
            };
            
            RAT.addFixLogEntry(stepNames[step] + '...', 'info', $log);
            
            return new Promise(function(resolve, reject) {
                $.ajax({
                    url: replantaAutoTranslate.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'replanta_fix_all',
                        nonce: replantaAutoTranslate.nonce,
                        step: step
                    },
                    success: function(response) {
                        if (response.success) {
                            RAT.addFixLogEntry('✓ ' + response.data.message, 'success', $log);
                            
                            // Mostrar errores si los hay
                            if (response.data.errors && response.data.errors.length > 0) {
                                response.data.errors.forEach(function(err) {
                                    RAT.addFixLogEntry('  ⚠ ' + err, 'warning', $log);
                                });
                            }
                            
                            resolve(response.data);
                        } else {
                            RAT.addFixLogEntry('✗ Error: ' + response.data.message, 'error', $log);
                            reject(response.data.message);
                        }
                    },
                    error: function() {
                        RAT.addFixLogEntry('✗ Error de conexión', 'error', $log);
                        reject('Error de conexión');
                    }
                });
            });
        },
        
        /**
         * Agregar entrada al log de fix all
         */
        addFixLogEntry: function(message, type, $log) {
            var timestamp = new Date().toLocaleTimeString();
            var $entry = $('<div class="log-entry log-' + type + '">[' + timestamp + '] ' + message + '</div>');
            $log.append($entry);
            $log.scrollTop($log[0].scrollHeight);
        }
    };
    
    // Inicializar cuando el DOM este listo
    $(document).ready(function() {
        RAT.init();
        
        // Bind fix all button
        $('#fix-all-translations').on('click', function() {
            RAT.fixAll();
        });
    });
    
})(jQuery);
