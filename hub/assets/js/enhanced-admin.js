/**
 * Enhanced Admin JavaScript for Replanta Hub
 * Complete functionality for all admin interactions
 */

(function($) {
    'use strict';

    // Global dashboard object
    window.ReplantaHubAdmin = {
        initialized: false,
        activeConnections: new Map(),
        notificationQueue: [],
        
        init: function() {
            if (this.initialized) return;
            
            this.bindGlobalEvents();
            this.initializeModules();
            this.setupAjaxDefaults();
            this.startHeartbeat();
            this.initialized = true;
        },

        bindGlobalEvents: function() {
            var self = this;

            // Global AJAX error handling
            $(document).ajaxError(function(event, xhr, settings, thrownError) {
                if (xhr.status === 403) {
                    self.showNotification('error', 'Sesión expirada. Por favor, recarga la página.');
                } else if (xhr.status === 0) {
                    self.showNotification('warning', 'Conexión perdida. Intentando reconectar...');
                }
            });

            // Handle bulk actions
            $('.rphub-bulk-actions').on('click', function(e) {
                e.preventDefault();
                self.handleBulkAction($(this));
            });

            // Handle quick actions
            $('.rphub-quick-action').on('click', function(e) {
                e.preventDefault();
                self.handleQuickAction($(this));
            });

            // Modal management
            $(document).on('click', '.rphub-modal-trigger', function(e) {
                e.preventDefault();
                var modalId = $(this).data('modal');
                self.openModal(modalId);
            });

            $(document).on('click', '.rphub-modal-close, .rphub-modal-backdrop', function(e) {
                e.preventDefault();
                self.closeModal($(this).closest('.rphub-modal'));
            });

            // Form validation
            $('.rphub-form').on('submit', function(e) {
                if (!self.validateForm($(this))) {
                    e.preventDefault();
                }
            });

            // Real-time updates toggle
            $('.rphub-realtime-toggle').on('change', function() {
                self.toggleRealTimeUpdates($(this).is(':checked'));
            });

            // Filter and search
            $('.rphub-filter, .rphub-search').on('change keyup', function() {
                clearTimeout(self.filterTimeout);
                self.filterTimeout = setTimeout(function() {
                    self.applyFilters();
                }, 300);
            });
        },

        setupAjaxDefaults: function() {
            $.ajaxSetup({
                beforeSend: function(xhr, settings) {
                    if (settings.data && typeof settings.data === 'string') {
                        settings.data += '&_wpnonce=' + (rphub_ajax.nonce || '');
                    } else if (settings.data && typeof settings.data === 'object') {
                        settings.data._wpnonce = rphub_ajax.nonce || '';
                    }
                }
            });
        },

        handleBulkAction: function($element) {
            var action = $element.data('action');
            var selectedItems = $('.rphub-bulk-checkbox:checked').map(function() {
                return $(this).val();
            }).get();

            if (selectedItems.length === 0) {
                this.showNotification('warning', 'Selecciona al menos un elemento.');
                return;
            }

            var confirmMessage = $element.data('confirm');
            if (confirmMessage && !confirm(confirmMessage)) {
                return;
            }

            this.executeBulkAction(action, selectedItems);
        },

        executeBulkAction: function(action, items) {
            var self = this;
            
            this.showLoading('Procesando acción en lote...');

            $.ajax({
                url: rphub_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rphub_bulk_action',
                    bulk_action: action,
                    items: items,
                    nonce: rphub_ajax.nonce
                },
                success: function(response) {
                    self.hideLoading();
                    
                    if (response.success) {
                        self.showNotification('success', response.data.message || 'Acción completada exitosamente');
                        
                        // Refresh the current view
                        if (typeof self.refreshCurrentView === 'function') {
                            self.refreshCurrentView();
                        }
                    } else {
                        self.showNotification('error', response.data || 'Error al procesar la acción');
                    }
                },
                error: function() {
                    self.hideLoading();
                    self.showNotification('error', 'Error de conexión al procesar la acción');
                }
            });
        },

        handleQuickAction: function($element) {
            var action = $element.data('action');
            var target = $element.data('target');
            var confirm = $element.data('confirm');

            if (confirm && !window.confirm(confirm)) {
                return;
            }

            this.executeQuickAction(action, target, $element);
        },

        executeQuickAction: function(action, target, $element) {
            var self = this;
            var originalText = $element.text();
            
            $element.prop('disabled', true).text('Procesando...');

            $.ajax({
                url: rphub_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rphub_quick_action',
                    quick_action: action,
                    target: target,
                    nonce: rphub_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotification('success', response.data.message || 'Acción completada');
                        
                        // Handle specific action responses
                        self.handleActionResponse(action, response.data, $element);
                    } else {
                        self.showNotification('error', response.data || 'Error en la acción');
                    }
                },
                error: function() {
                    self.showNotification('error', 'Error de conexión');
                },
                complete: function() {
                    $element.prop('disabled', false).text(originalText);
                }
            });
        },

        handleActionResponse: function(action, data, $element) {
            switch (action) {
                case 'security_scan':
                    this.updateSecurityStatus(data);
                    break;
                case 'backup_create':
                    this.updateBackupList(data);
                    break;
                case 'performance_test':
                    this.updatePerformanceMetrics(data);
                    break;
                case 'cache_purge':
                    this.updateCacheStats(data);
                    break;
                default:
                    // Generic refresh
                    if (typeof this.refreshCurrentView === 'function') {
                        this.refreshCurrentView();
                    }
            }
        },

        initializeModules: function() {
            // Initialize charts if Chart.js is available
            if (typeof Chart !== 'undefined') {
                this.initCharts();
            }

            // Initialize data tables
            this.initDataTables();

            // Initialize tooltips
            this.initTooltips();

            // Initialize file uploads
            this.initFileUploads();

            // Initialize code editors
            // this.initCodeEditors(); // TODO: Function not implemented yet
        },

        initCharts: function() {
            var self = this;
            
            $('.rphub-chart').each(function() {
                var $chart = $(this);
                var type = $chart.data('chart-type') || 'line';
                var chartData = $chart.data('chart-data') || {};
                
                self.createChart(this, type, chartData);
            });
        },

        createChart: function(canvas, type, data) {
            var ctx = canvas.getContext('2d');
            
            var config = {
                type: type,
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: type !== 'doughnut' && type !== 'pie' ? {
                        y: {
                            beginAtZero: true
                        }
                    } : {}
                }
            };

            return new Chart(ctx, config);
        },

        initDataTables: function() {
            if (typeof $.fn.DataTable === 'undefined') return;

            $('.rphub-datatable').each(function() {
                var $table = $(this);
                var options = $table.data('table-options') || {};
                
                var defaultOptions = {
                    responsive: true,
                    pageLength: 25,
                    language: {
                        url: rphub_ajax.datatable_lang_url || ''
                    },
                    dom: '<"top"lf>rt<"bottom"ip>',
                    order: [[0, 'desc']]
                };

                $table.DataTable($.extend(defaultOptions, options));
            });
        },

        initTooltips: function() {
            $('[data-tooltip]').each(function() {
                var $element = $(this);
                var text = $element.data('tooltip');
                var position = $element.data('tooltip-position') || 'top';
                
                $element.hover(
                    function() {
                        this.showTooltip($element, text, position);
                    }.bind(this),
                    function() {
                        this.hideTooltip();
                    }.bind(this)
                );
            }.bind(this));
        },

        showTooltip: function($element, text, position) {
            var $tooltip = $('<div class="rphub-tooltip">' + text + '</div>');
            $('body').append($tooltip);
            
            var offset = $element.offset();
            var elementWidth = $element.outerWidth();
            var elementHeight = $element.outerHeight();
            var tooltipWidth = $tooltip.outerWidth();
            var tooltipHeight = $tooltip.outerHeight();
            
            var top, left;
            
            switch (position) {
                case 'bottom':
                    top = offset.top + elementHeight + 5;
                    left = offset.left + (elementWidth / 2) - (tooltipWidth / 2);
                    break;
                case 'left':
                    top = offset.top + (elementHeight / 2) - (tooltipHeight / 2);
                    left = offset.left - tooltipWidth - 5;
                    break;
                case 'right':
                    top = offset.top + (elementHeight / 2) - (tooltipHeight / 2);
                    left = offset.left + elementWidth + 5;
                    break;
                default: // top
                    top = offset.top - tooltipHeight - 5;
                    left = offset.left + (elementWidth / 2) - (tooltipWidth / 2);
            }
            
            $tooltip.css({ top: top, left: left }).fadeIn(200);
        },

        hideTooltip: function() {
            $('.rphub-tooltip').fadeOut(200, function() {
                $(this).remove();
            });
        },

        initFileUploads: function() {
            var self = this;
            
            $('.rphub-file-upload').on('change', function() {
                var $input = $(this);
                var files = $input[0].files;
                
                if (files.length > 0) {
                    self.handleFileUpload($input, files[0]);
                }
            });

            // Drag and drop
            $('.rphub-dropzone').on('dragover', function(e) {
                e.preventDefault();
                $(this).addClass('dragover');
            }).on('dragleave', function(e) {
                e.preventDefault();
                $(this).removeClass('dragover');
            }).on('drop', function(e) {
                e.preventDefault();
                $(this).removeClass('dragover');
                
                var files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    self.handleFileUpload($(this), files[0]);
                }
            });
        },

        handleFileUpload: function($element, file) {
            var self = this;
            var maxSize = $element.data('max-size') || 10485760; // 10MB default
            var allowedTypes = $element.data('allowed-types') || '';
            
            // Validate file size
            if (file.size > maxSize) {
                this.showNotification('error', 'El archivo es demasiado grande. Tamaño máximo: ' + this.formatFileSize(maxSize));
                return;
            }
            
            // Validate file type
            if (allowedTypes && !allowedTypes.split(',').includes(file.type)) {
                this.showNotification('error', 'Tipo de archivo no permitido.');
                return;
            }
            
            var formData = new FormData();
            formData.append('file', file);
            formData.append('action', 'rphub_file_upload');
            formData.append('upload_type', $element.data('upload-type') || 'general');
            formData.append('nonce', rphub_ajax.nonce);
            
            $.ajax({
                url: rphub_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            var percentComplete = (e.loaded / e.total) * 100;
                            self.updateUploadProgress($element, percentComplete);
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotification('success', 'Archivo subido exitosamente');
                        self.handleUploadSuccess($element, response.data);
                    } else {
                        self.showNotification('error', response.data || 'Error al subir archivo');
                    }
                },
                error: function() {
                    self.showNotification('error', 'Error de conexión al subir archivo');
                },
                complete: function() {
                    self.hideUploadProgress($element);
                }
            });
        },

        updateUploadProgress: function($element, percent) {
            var $progress = $element.siblings('.upload-progress');
            if ($progress.length === 0) {
                $progress = $('<div class="upload-progress"><div class="progress-bar"></div></div>');
                $element.after($progress);
            }
            
            $progress.find('.progress-bar').css('width', percent + '%');
        },

        hideUploadProgress: function($element) {
            $element.siblings('.upload-progress').remove();
        },

        validateForm: function($form) {
            var isValid = true;
            var self = this;
            
            $form.find('[data-validate]').each(function() {
                var $field = $(this);
                var rules = $field.data('validate').split('|');
                var value = $field.val();
                
                $field.removeClass('error');
                $field.siblings('.field-error').remove();
                
                rules.forEach(function(rule) {
                    if (!self.validateField($field, rule, value)) {
                        isValid = false;
                    }
                });
            });
            
            return isValid;
        },

        validateField: function($field, rule, value) {
            var isValid = true;
            var errorMessage = '';
            
            switch (rule) {
                case 'required':
                    if (!value || value.trim() === '') {
                        isValid = false;
                        errorMessage = 'Este campo es obligatorio';
                    }
                    break;
                case 'email':
                    if (value && !this.isValidEmail(value)) {
                        isValid = false;
                        errorMessage = 'Formato de email inválido';
                    }
                    break;
                case 'url':
                    if (value && !this.isValidUrl(value)) {
                        isValid = false;
                        errorMessage = 'Formato de URL inválido';
                    }
                    break;
                case 'numeric':
                    if (value && !$.isNumeric(value)) {
                        isValid = false;
                        errorMessage = 'Solo se permiten números';
                    }
                    break;
            }
            
            if (!isValid) {
                $field.addClass('error');
                $field.after('<span class="field-error">' + errorMessage + '</span>');
            }
            
            return isValid;
        },

        isValidEmail: function(email) {
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        },

        isValidUrl: function(url) {
            var urlRegex = /^https?:\/\/[^\s$.?#].[^\s]*$/i;
            return urlRegex.test(url);
        },

        openModal: function(modalId) {
            var $modal = $('#' + modalId);
            if ($modal.length === 0) return;
            
            $modal.addClass('active');
            $('body').addClass('modal-open');
            
            // Focus first input
            setTimeout(function() {
                $modal.find('input, textarea, select').first().focus();
            }, 100);
        },

        closeModal: function($modal) {
            $modal.removeClass('active');
            $('body').removeClass('modal-open');
        },

        showLoading: function(message) {
            var $loading = $('#rphub-global-loading');
            
            if ($loading.length === 0) {
                $loading = $(`
                    <div id="rphub-global-loading" class="rphub-loading-overlay">
                        <div class="loading-content">
                            <div class="loading-spinner"></div>
                            <p class="loading-message">Cargando...</p>
                        </div>
                    </div>
                `);
                $('body').append($loading);
            }
            
            $loading.find('.loading-message').text(message || 'Cargando...');
            $loading.fadeIn(300);
        },

        hideLoading: function() {
            $('#rphub-global-loading').fadeOut(300);
        },

        showNotification: function(type, message, duration) {
            var self = this;
            duration = duration || 5000;
            
            var $notification = $(`
                <div class="rphub-notification rphub-notification-${type}">
                    <div class="notification-content">
                        <span class="notification-icon">${this.getNotificationIcon(type)}</span>
                        <span class="notification-message">${message}</span>
                        <button class="notification-close">&times;</button>
                    </div>
                </div>
            `);
            
            $('.rphub-notifications-container').append($notification);
            
            setTimeout(function() {
                $notification.addClass('show');
            }, 10);
            
            // Auto remove
            if (duration > 0) {
                setTimeout(function() {
                    self.removeNotification($notification);
                }, duration);
            }
            
            // Close button
            $notification.find('.notification-close').on('click', function() {
                self.removeNotification($notification);
            });
        },

        removeNotification: function($notification) {
            $notification.removeClass('show');
            setTimeout(function() {
                $notification.remove();
            }, 300);
        },

        getNotificationIcon: function(type) {
            var icons = {
                'success': '',
                'error': '',
                'warning': '',
                'info': 'ℹ'
            };
            return icons[type] || 'ℹ';
        },

        startHeartbeat: function() {
            var self = this;
            
            this.heartbeatInterval = setInterval(function() {
                self.sendHeartbeat();
            }, 60000); // Every minute
        },

        sendHeartbeat: function() {
            $.ajax({
                url: rphub_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rphub_heartbeat',
                    nonce: rphub_ajax.nonce
                },
                success: function(response) {
                    if (response.success && response.data.notifications) {
                        response.data.notifications.forEach(function(notification) {
                            this.showNotification(notification.type, notification.message);
                        }.bind(this));
                    }
                }.bind(this),
                error: function() {
                    // Silent fail for heartbeat
                }
            });
        },

        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            var k = 1024;
            var sizes = ['Bytes', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        formatNumber: function(num) {
            if (num >= 1000000) {
                return (num / 1000000).toFixed(1) + 'M';
            } else if (num >= 1000) {
                return (num / 1000).toFixed(1) + 'K';
            }
            return num.toString();
        },

        debounce: function(func, wait, immediate) {
            var timeout;
            return function() {
                var context = this, args = arguments;
                var later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                var callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        // Create notifications container if it doesn't exist
        if ($('.rphub-notifications-container').length === 0) {
            $('body').append('<div class="rphub-notifications-container"></div>');
        }
        
        // Initialize admin functionality
        ReplantaHubAdmin.init();
    });

    // Global error handler
    window.onerror = function(msg, url, lineNo, columnNo, error) {
        if (window.ReplantaHubAdmin && typeof ReplantaHubAdmin.showNotification === 'function') {
            ReplantaHubAdmin.showNotification('error', 'Error de JavaScript detectado. Revisa la consola para más detalles.');
        }
        return false;
    };

})(jQuery);

// Add required CSS for enhanced functionality
var enhancedStyles = `
<style>
.rphub-loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 999999;
    display: none;
}

.loading-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    color: white;
}

.loading-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid rgba(255, 255, 255, 0.3);
    border-top: 4px solid white;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 15px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.rphub-notifications-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 999998;
    max-width: 400px;
}

.rphub-notification {
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    margin-bottom: 10px;
    opacity: 0;
    transform: translateX(100%);
    transition: all 0.3s ease;
    border-left: 4px solid #ccc;
}

.rphub-notification.show {
    opacity: 1;
    transform: translateX(0);
}

.rphub-notification-success {
    border-left-color: #10B981;
}

.rphub-notification-error {
    border-left-color: #EF4444;
}

.rphub-notification-warning {
    border-left-color: #F59E0B;
}

.rphub-notification-info {
    border-left-color: #3B82F6;
}

.notification-content {
    padding: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.notification-icon {
    font-size: 18px;
    flex-shrink: 0;
}

.notification-message {
    flex: 1;
    font-size: 14px;
    color: #374151;
}

.notification-close {
    background: none;
    border: none;
    font-size: 18px;
    color: #9CA3AF;
    cursor: pointer;
    padding: 0;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.notification-close:hover {
    color: #374151;
}

.rphub-tooltip {
    position: absolute;
    background: #1F2937;
    color: white;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 12px;
    z-index: 999997;
    display: none;
    white-space: nowrap;
    max-width: 300px;
    word-wrap: break-word;
}

.field-error {
    color: #EF4444;
    font-size: 12px;
    display: block;
    margin-top: 5px;
}

.rphub-form input.error,
.rphub-form textarea.error,
.rphub-form select.error {
    border-color: #EF4444;
    box-shadow: 0 0 0 1px #EF4444;
}

.upload-progress {
    width: 100%;
    height: 4px;
    background: #E5E7EB;
    border-radius: 2px;
    margin-top: 5px;
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    background: #10B981;
    transition: width 0.3s ease;
}

.rphub-dropzone {
    border: 2px dashed #D1D5DB;
    border-radius: 8px;
    padding: 40px;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
}

.rphub-dropzone.dragover {
    border-color: #10B981;
    background: rgba(16, 185, 129, 0.05);
}

.modal-open {
    overflow: hidden;
}

.rphub-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 999996;
    display: none;
}

.rphub-modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

.rphub-modal-content {
    background: white;
    border-radius: 8px;
    max-width: 90%;
    max-height: 90%;
    overflow: auto;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
}

@media (max-width: 768px) {
    .rphub-notifications-container {
        top: 10px;
        right: 10px;
        left: 10px;
        max-width: none;
    }
    
    .rphub-notification {
        transform: translateY(-100%);
    }
    
    .rphub-notification.show {
        transform: translateY(0);
    }
}
</style>
`;

jQuery('head').append(enhancedStyles);
