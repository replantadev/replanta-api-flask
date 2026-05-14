/**
 * Replanta Meta Fill - Admin JavaScript
 */

(function($) {
    'use strict';
    
    const ReplantaMetaFill = {
        
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            // Botones de generar/regenerar en columnas
            $(document).on('click', '.rmf-generate-btn, .rmf-regenerate-btn', this.handleGenerate);
            
            // Validar API key
            $('#rmf-validate-api-key').on('click', this.validateApiKey);
            
            // Generación masiva
            $('#rmf-select-all').on('change', this.toggleSelectAll);
            $('#rmf-bulk-generate').on('click', this.handleBulkGenerate);
            
            // ---- ALT Imágenes ----
            $(document).on('click', '.rmf-generate-alt-btn, .rmf-regenerate-alt-btn', this.handleGenerateAlt);
            $('#rmf-scan-alts').on('click', this.handleScanAlts);
            $('#rmf-select-all-alts').on('click', this.handleSelectAllAlts);
            $('#rmf-alts-check-all').on('change', this.toggleSelectAllAlts);
            $('#rmf-bulk-generate-alts').on('click', this.handleBulkGenerateAlts);
        },
        
        /**
         * Manejar generación individual
         */
        handleGenerate: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const postId = $btn.data('post-id');
            
            if ($btn.prop('disabled')) {
                return;
            }
            
            $btn.prop('disabled', true).addClass('loading');
            const originalText = $btn.html();
            $btn.html('<span class="dashicons dashicons-update-alt"></span> Generando...');
            
            $.ajax({
                url: replantaMetaFill.ajax_url,
                type: 'POST',
                data: {
                    action: 'rmf_generate_meta',
                    nonce: replantaMetaFill.nonce,
                    post_id: postId
                },
                success: function(response) {
                    if (response.success) {
                        ReplantaMetaFill.showNotice('success', response.data.message);
                        setTimeout(function() { location.reload(); }, 1000);
                    } else {
                        ReplantaMetaFill.showNotice('error', response.data.message || 'Error desconocido');
                        $btn.prop('disabled', false).removeClass('loading').html(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    ReplantaMetaFill.showNotice('error', 'Error de conexión: ' + error);
                    $btn.prop('disabled', false).removeClass('loading').html(originalText);
                }
            });
        },
        
        /**
         * Validar API key
         */
        validateApiKey: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const $input = $('input[name="replanta_meta_fill_options[openai_api_key]"]');
            const apiKey = $input.val();
            
            if (!apiKey) {
                $('#rmf-api-validation-result').html('<span class="error">Por favor, introduce una API key</span>');
                return;
            }
            
            $btn.prop('disabled', true).text('Validando...');
            $('#rmf-api-validation-result').html('');
            
            $.ajax({
                url: replantaMetaFill.ajax_url,
                type: 'POST',
                data: {
                    action: 'rmf_validate_api_key',
                    nonce: replantaMetaFill.nonce,
                    api_key: apiKey
                },
                success: function(response) {
                    $btn.prop('disabled', false).text('Validar Key');
                    if (response.success) {
                        $('#rmf-api-validation-result').html('<span class="success">✅ ' + response.data.message + '</span>');
                    } else {
                        $('#rmf-api-validation-result').html('<span class="error">❌ ' + response.data.message + '</span>');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('Validar Key');
                    $('#rmf-api-validation-result').html('<span class="error">Error de conexión</span>');
                }
            });
        },
        
        /**
         * Toggle select all checkboxes
         */
        toggleSelectAll: function() {
            var checked = $(this).prop('checked');
            // Solo seleccionar los visibles (respeta el filtro de scope)
            $('#rmf-items-tbody tr:visible .rmf-post-checkbox').prop('checked', checked);
        },
        
        /**
         * Manejar generación masiva
         */
        handleBulkGenerate: function(e) {
            e.preventDefault();
            
            // Solo contar los visibles (respeta el filtro)
            var $selectedPosts = $('#rmf-items-tbody tr:visible .rmf-post-checkbox:checked');
            if ($selectedPosts.length === 0) {
                alert('Por favor, selecciona al menos un elemento');
                return;
            }
            
            // Obtener el nombre del scope actual
            var scope = $('input[name="rmf_meta_scope"]:checked').val();
            var scopeNames = {
                'all': 'todos los tipos',
                'post': 'posts',
                'page': 'páginas',
                'product': 'productos'
            };
            var scopeName = scopeNames[scope] || 'elementos';
            
            if (!confirm('¿Generar meta descripciones para ' + $selectedPosts.length + ' ' + scopeName + '?\n\nEsto usará créditos de tu API de OpenAI.')) {
                return;
            }
            
            var postIds = $selectedPosts.map(function() { return $(this).val(); }).get();
            ReplantaMetaFill.processBulkGeneration(postIds);
        },
        
        /**
         * Procesar generación masiva
         */
        processBulkGeneration: function(postIds) {
            var total = postIds.length;
            var processed = 0;
            
            $('#rmf-bulk-progress').show();
            $('#rmf-progress-bar').attr('max', total).val(0);
            $('#rmf-progress-text').text('0 / ' + total);
            $('#rmf-bulk-generate').prop('disabled', true);
            
            var batchSize = 5;
            var batches = [];
            for (var i = 0; i < postIds.length; i += batchSize) {
                batches.push(postIds.slice(i, i + batchSize));
            }
            
            var processBatch = function(batchIndex) {
                if (batchIndex >= batches.length) {
                    ReplantaMetaFill.showNotice('success', '✅ Generación completada: ' + total + ' posts procesados');
                    $('#rmf-bulk-generate').prop('disabled', false);
                    setTimeout(function() { location.reload(); }, 2000);
                    return;
                }
                
                var batch = batches[batchIndex];
                
                $.ajax({
                    url: replantaMetaFill.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'rmf_bulk_generate',
                        nonce: replantaMetaFill.nonce,
                        post_ids: batch
                    },
                    success: function(response) {
                        if (response.success) {
                            processed += batch.length;
                            $('#rmf-progress-bar').val(processed);
                            $('#rmf-progress-text').text(processed + ' / ' + total);
                            
                            $.each(response.data.results, function(postId, result) {
                                var $status = $('.rmf-status-' + postId);
                                if (result.success) {
                                    $status.html('<span class="rmf-bulk-status success"><span class="dashicons dashicons-yes-alt"></span> Generada</span>');
                                } else {
                                    $status.html('<span class="rmf-bulk-status error"><span class="dashicons dashicons-warning"></span> ' + result.message + '</span>');
                                }
                            });
                            
                            setTimeout(function() { processBatch(batchIndex + 1); }, 2000);
                        } else {
                            ReplantaMetaFill.showNotice('error', 'Error en lote: ' + response.data.message);
                            $('#rmf-bulk-generate').prop('disabled', false);
                        }
                    },
                    error: function() {
                        ReplantaMetaFill.showNotice('error', 'Error de conexión en lote ' + (batchIndex + 1));
                        $('#rmf-bulk-generate').prop('disabled', false);
                    }
                });
            };
            
            processBatch(0);
        },
        
        // ==================================================================
        // ALT Imágenes
        // ==================================================================
        
        /**
         * Generar ALT individual (desde media library o página de ALTs)
         */
        handleGenerateAlt: function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var attachmentId = $btn.data('attachment-id');
            
            if ($btn.prop('disabled')) return;
            
            $btn.prop('disabled', true).addClass('loading');
            var originalText = $btn.html();
            $btn.html('<span class="dashicons dashicons-update-alt"></span> Generando...');
            
            var useAi = $('#rmf-alt-use-ai').length ? ($('#rmf-alt-use-ai').is(':checked') ? '1' : '0') : '0';
            
            $.ajax({
                url: replantaMetaFill.ajax_url,
                type: 'POST',
                data: {
                    action: 'rmf_generate_alt',
                    nonce: replantaMetaFill.nonce,
                    attachment_id: attachmentId,
                    use_ai: useAi
                },
                success: function(response) {
                    if (response.success) {
                        ReplantaMetaFill.showNotice('success', 'ALT generado: ' + response.data.alt_text);
                        
                        // Actualizar columna inline
                        var $container = $btn.closest('.rmf-alt-status, .rmf-alts-row-status');
                        if ($container.length) {
                            $container.html('<span style="color:#46b450;">✅ ' + response.data.alt_text + '</span>');
                        } else {
                            setTimeout(function() { location.reload(); }, 1000);
                        }
                    } else {
                        ReplantaMetaFill.showNotice('error', response.data.message || 'Error');
                        $btn.prop('disabled', false).removeClass('loading').html(originalText);
                    }
                },
                error: function() {
                    ReplantaMetaFill.showNotice('error', 'Error de conexión');
                    $btn.prop('disabled', false).removeClass('loading').html(originalText);
                }
            });
        },
        
        /**
         * Escanear imágenes sin ALT
         */
        handleScanAlts: function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            $btn.prop('disabled', true).text('Escaneando...');
            
            var scope = $('input[name="rmf_alt_scope"]:checked').val() || 'all';
            
            $.ajax({
                url: replantaMetaFill.ajax_url,
                type: 'POST',
                data: {
                    action: 'rmf_get_missing_alts',
                    nonce: replantaMetaFill.nonce,
                    scope: scope
                },
                success: function(response) {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-search" style="margin-top: 4px;"></span> Escanear imágenes sin ALT');
                    
                    if (response.success) {
                        var images = response.data.images;
                        $('#rmf-alts-count').text(response.data.count);
                        
                        var $tbody = $('#rmf-alts-table tbody');
                        $tbody.empty();
                        
                        if (images.length === 0) {
                            $tbody.append('<tr><td colspan="5" style="text-align:center; padding: 20px;">✅ Todas las imágenes tienen texto ALT.</td></tr>');
                            $('#rmf-bulk-generate-alts').prop('disabled', true);
                        } else {
                            $.each(images, function(i, img) {
                                var thumb = img.thumbnail ? '<img src="' + img.thumbnail + '" width="40" height="40" style="object-fit:cover; border-radius:4px;">' : '—';
                                var parent = img.parent_title ? '<a href="post.php?post=' + img.parent_id + '&action=edit">' + img.parent_title + '</a>' : '<em>Sin asociar</em>';
                                
                                $tbody.append(
                                    '<tr>' +
                                    '<td><input type="checkbox" class="rmf-alt-checkbox" value="' + img.id + '"></td>' +
                                    '<td>' + thumb + '</td>' +
                                    '<td>' + (img.title || '<em>Sin título</em>') + '</td>' +
                                    '<td>' + parent + '</td>' +
                                    '<td class="rmf-alts-row-status">' +
                                    '<button type="button" class="button button-small rmf-generate-alt-btn" data-attachment-id="' + img.id + '">' +
                                    '<span class="dashicons dashicons-lightbulb" style="font-size:13px;width:13px;height:13px;margin-top:3px;"></span> Generar' +
                                    '</button>' +
                                    '</td>' +
                                    '</tr>'
                                );
                            });
                            $('#rmf-bulk-generate-alts').prop('disabled', false);
                        }
                        
                        $('#rmf-alts-results').show();
                    } else {
                        ReplantaMetaFill.showNotice('error', response.data.message || 'Error al escanear');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-search" style="margin-top: 4px;"></span> Escanear imágenes sin ALT');
                    ReplantaMetaFill.showNotice('error', 'Error de conexión');
                }
            });
        },
        
        /**
         * Seleccionar/deseleccionar todas las imágenes ALT
         */
        handleSelectAllAlts: function(e) {
            e.preventDefault();
            var allChecked = $('.rmf-alt-checkbox').length === $('.rmf-alt-checkbox:checked').length;
            $('.rmf-alt-checkbox').prop('checked', !allChecked);
            $('#rmf-alts-check-all').prop('checked', !allChecked);
        },
        
        toggleSelectAllAlts: function() {
            var checked = $(this).prop('checked');
            $('.rmf-alt-checkbox').prop('checked', checked);
        },
        
        /**
         * Generación masiva de ALTs
         */
        handleBulkGenerateAlts: function(e) {
            e.preventDefault();
            
            var $selected = $('.rmf-alt-checkbox:checked');
            if ($selected.length === 0) {
                alert('Selecciona al menos una imagen');
                return;
            }
            
            if (!confirm('¿Generar texto ALT para ' + $selected.length + ' imágenes?')) {
                return;
            }
            
            var ids = $selected.map(function() { return parseInt($(this).val()); }).get();
            var useAi = $('#rmf-alt-use-ai').is(':checked') ? '1' : '0';
            
            var total = ids.length;
            var processed = 0;
            var batchSize = useAi === '1' ? 5 : 20;
            var batches = [];
            
            for (var i = 0; i < ids.length; i += batchSize) {
                batches.push(ids.slice(i, i + batchSize));
            }
            
            $('#rmf-alts-progress').show();
            $('#rmf-alts-progress-bar').attr('max', total).val(0);
            $('#rmf-alts-progress-text').text('0 / ' + total);
            $('#rmf-bulk-generate-alts').prop('disabled', true);
            
            var processBatch = function(batchIndex) {
                if (batchIndex >= batches.length) {
                    ReplantaMetaFill.showNotice('success', '✅ ALTs generados: ' + total + ' imágenes procesadas');
                    $('#rmf-bulk-generate-alts').prop('disabled', false);
                    return;
                }
                
                var batch = batches[batchIndex];
                
                $.ajax({
                    url: replantaMetaFill.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'rmf_bulk_generate_alts',
                        nonce: replantaMetaFill.nonce,
                        attachment_ids: batch,
                        use_ai: useAi
                    },
                    success: function(response) {
                        if (response.success) {
                            processed += batch.length;
                            $('#rmf-alts-progress-bar').val(processed);
                            $('#rmf-alts-progress-text').text(processed + ' / ' + total);
                            
                            // Actualizar filas individuales
                            $.each(response.data.results, function(attachId, result) {
                                var $row = $('.rmf-alt-checkbox[value="' + attachId + '"]').closest('tr');
                                var $status = $row.find('.rmf-alts-row-status');
                                
                                if (result.success) {
                                    $status.html('<span style="color:#46b450;">✅ ' + result.alt_text + '</span>');
                                } else {
                                    $status.html('<span style="color:#dc3232;">❌ ' + (result.error || 'Error') + '</span>');
                                }
                            });
                            
                            setTimeout(function() { processBatch(batchIndex + 1); }, useAi === '1' ? 2000 : 500);
                        } else {
                            ReplantaMetaFill.showNotice('error', 'Error en lote: ' + response.data.message);
                            $('#rmf-bulk-generate-alts').prop('disabled', false);
                        }
                    },
                    error: function() {
                        ReplantaMetaFill.showNotice('error', 'Error de conexión en lote');
                        $('#rmf-bulk-generate-alts').prop('disabled', false);
                    }
                });
            };
            
            processBatch(0);
        },
        
        /**
         * Mostrar notificación
         */
        showNotice: function(type, message) {
            var $notice = $('<div class="rmf-notice ' + type + '">')
                .html('<span class="rmf-notice-close">&times;</span>' + message);
            
            $('body').append($notice);
            
            setTimeout(function() {
                $notice.fadeOut(function() { $(this).remove(); });
            }, 5000);
            
            $notice.on('click', '.rmf-notice-close', function() {
                $notice.fadeOut(function() { $(this).remove(); });
            });
        }
    };
    
    $(document).ready(function() {
        ReplantaMetaFill.init();
    });
    
})(jQuery);
