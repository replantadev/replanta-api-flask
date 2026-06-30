/**
 * Replanta Hub Admin JavaScript
 */

(function($) {
    'use strict';

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function isDebug() {
        return !!(window.rphub_ajax && window.rphub_ajax.debug);
    }

    // Initialize when document is ready
    $(document).ready(function() {
        if (typeof rphub_ajax === 'undefined') {
            console.error(' rphub_ajax is not defined. The plugin may not work correctly.');
        }
        
        // Initialize components
        initializeComponents();
    });

    function initializeComponents() {
        // Initialize tooltips
        initTooltips();
        
        // Initialize modals
        initModals();
        
        // Initialize bulk actions
        initBulkActions();
        
        // Initialize search and filters
        initSearchAndFilters();
        
        // Initialize real-time updates
        initRealTimeUpdates();
        
        // Initialize form validations
        initFormValidations();
        
        // Initialize charts if Chart.js is available
        if (typeof Chart !== 'undefined') {
            initCharts();
        }
        
        if (isDebug()) {
            console.log('Replanta Hub Admin initialized');
        }
    }

    // Tooltips
    function initTooltips() {
        $('[data-tooltip]').each(function() {
            const $this = $(this);
            const tooltip = $this.data('tooltip');
            
            $this.hover(
                function() {
                    showTooltip($this, tooltip);
                },
                function() {
                    hideTooltip();
                }
            );
        });
    }

    function showTooltip($element, text) {
        const $tooltip = $('<div class="rphub-tooltip">' + text + '</div>');
        $('body').append($tooltip);
        
        const offset = $element.offset();
        const elementHeight = $element.outerHeight();
        const tooltipWidth = $tooltip.outerWidth();
        const tooltipHeight = $tooltip.outerHeight();
        
        $tooltip.css({
            position: 'absolute',
            top: offset.top - tooltipHeight - 10,
            left: offset.left - (tooltipWidth / 2) + ($element.outerWidth() / 2),
            zIndex: 999999
        });
        
        $tooltip.fadeIn(200);
    }

    function hideTooltip() {
        $('.rphub-tooltip').fadeOut(200, function() {
            $(this).remove();
        });
    }

    // Modals
    function initModals() {
        // Close modal when clicking outside
        $(document).on('click', '.rphub-modal', function(e) {
            if (e.target === this) {
                closeModal($(this));
            }
        });
        
        // Close modal with escape key
        $(document).on('keydown', function(e) {
            if (e.keyCode === 27) { // Escape key
                $('.rphub-modal:visible').each(function() {
                    closeModal($(this));
                });
            }
        });
        
        // Close button
        $(document).on('click', '.rphub-modal-close', function() {
            closeModal($(this).closest('.rphub-modal'));
        });
    }

    function openModal(modalId) {
        const $modal = $('#' + modalId);
        if ($modal.length) {
            $modal.css('display', 'flex').hide().fadeIn(300);
            $('body').addClass('rphub-modal-open');
            
            // Focus first input
            setTimeout(function() {
                $modal.find('input, select, textarea').first().focus();
            }, 300);
        }
    }

    function closeModal($modal) {
        $modal.fadeOut(300, function() {
            $(this).css('display', 'none');
        });
        $('body').removeClass('rphub-modal-open');
    }

    // Bulk Actions
    function initBulkActions() {
        // Select all checkbox
        $(document).on('change', '.rphub-select-all', function() {
            const isChecked = $(this).is(':checked');
            $(this).closest('table').find('.rphub-select-item').prop('checked', isChecked);
            updateBulkActionsVisibility();
        });
        
        // Individual checkboxes
        $(document).on('change', '.rphub-select-item', function() {
            updateBulkActionsVisibility();
            updateSelectAllState();
        });
        
        // Bulk action execution
        $(document).on('click', '.rphub-bulk-action-btn', function() {
            const action = $('.rphub-bulk-action-select').val();
            if (!action) {
                alert('Por favor selecciona una accion.');
                return;
            }
            
            const selectedItems = $('.rphub-select-item:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (selectedItems.length === 0) {
                alert('Por favor selecciona al menos un elemento.');
                return;
            }
            
            if (confirm('Estas seguro de que quieres ejecutar esta accion en ' + selectedItems.length + ' elementos?')) {
                executeBulkAction(action, selectedItems);
            }
        });
    }

    function updateBulkActionsVisibility() {
        const checkedItems = $('.rphub-select-item:checked').length;
        const $bulkActions = $('.rphub-bulk-actions');
        
        if (checkedItems > 0) {
            $bulkActions.slideDown();
        } else {
            $bulkActions.slideUp();
        }
    }

    function updateSelectAllState() {
        const $selectAll = $('.rphub-select-all');
        const totalItems = $('.rphub-select-item').length;
        const checkedItems = $('.rphub-select-item:checked').length;
        
        if (checkedItems === 0) {
            $selectAll.prop('indeterminate', false).prop('checked', false);
        } else if (checkedItems === totalItems) {
            $selectAll.prop('indeterminate', false).prop('checked', true);
        } else {
            $selectAll.prop('indeterminate', true);
        }
    }

    function executeBulkAction(action, items) {
        const $btn = $('.rphub-bulk-action-btn');
        const originalText = $btn.text();
        
        $btn.text('Ejecutando...').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl, // Use WordPress global ajaxurl instead of rphub_ajax.ajax_url
            type: 'POST',
            data: {
                action: 'rphub_bulk_action',
                bulk_action: action,
                items: items,
                nonce: rphub_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('success', 'Accion ejecutada correctamente.');
                    
                    // Reload page or update content
                    if (typeof refreshCurrentView === 'function') {
                        refreshCurrentView();
                    } else {
                        location.reload();
                    }
                } else {
                    showNotification('error', 'Error: ' + response.data);
                }
            },
            error: function() {
                showNotification('error', 'Error de conexion.');
            },
            complete: function() {
                $btn.text(originalText).prop('disabled', false);
            }
        });
    }

    // Search and Filters
    function initSearchAndFilters() {
        let searchTimeout;
        
        // Search input
        $(document).on('input', '.rphub-search-input', function() {
            const $input = $(this);
            const searchTerm = $input.val();
            
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                performSearch(searchTerm);
            }, 500);
        });
        
        // Filter selects
        $(document).on('change', '.rphub-filter-select', function() {
            performFilter();
        });
        
        // Clear filters
        $(document).on('click', '.rphub-clear-filters', function() {
            $('.rphub-search-input').val('');
            $('.rphub-filter-select').val('');
            performFilter();
        });
    }

    function performSearch(searchTerm) {
        // This will be implemented per page
        if (typeof window.rphubPerformSearch === 'function') {
            window.rphubPerformSearch(searchTerm);
        }
    }

    function performFilter() {
        // This will be implemented per page
        if (typeof window.rphubPerformFilter === 'function') {
            window.rphubPerformFilter();
        }
    }

    // Real-time Updates
    function initRealTimeUpdates() {
        // Update every 30 seconds
        setInterval(function() {
            updateRealTimeData();
        }, 30000);
    }

    function updateRealTimeData() {
        // Update notifications count
        updateNotificationsCount();
        
        // Update status indicators
        updateStatusIndicators();
        
        // Update last activity
        updateLastActivity();
    }

    function updateNotificationsCount() {
        $.ajax({
            url: ajaxurl, // Use WordPress global ajaxurl
            type: 'POST',
            data: {
                action: 'rphub_get_notifications_count',
                nonce: rphub_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    const count = response.data.count;
                    const $badge = $('.rphub-notifications-badge');
                    
                    if (count > 0) {
                        $badge.text(count).show();
                    } else {
                        $badge.hide();
                    }
                }
            }
        });
    }

    function updateStatusIndicators() {
        $('.rphub-status-indicator').each(function() {
            const $indicator = $(this);
            const siteId = $indicator.data('site-id');
            
            if (siteId) {
                $.ajax({
                    url: ajaxurl, // Use WordPress global ajaxurl
                    type: 'POST',
                    data: {
                        action: 'rphub_get_site_status',
                        site_id: siteId,
                        nonce: rphub_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $indicator.removeClass('online offline pending')
                                     .addClass(response.data.status);
                        }
                    }
                });
            }
        });
    }

    function updateLastActivity() {
        $('.rphub-last-activity').each(function() {
            const $element = $(this);
            const timestamp = $element.data('timestamp');
            
            if (timestamp) {
                const timeAgo = getTimeAgo(new Date(timestamp));
                $element.text(timeAgo);
            }
        });
    }

    // Form Validations
    function initFormValidations() {
        // Real-time validation
        $(document).on('blur', '.rphub-validate', function() {
            validateField($(this));
        });
        
        // Form submission validation
        $(document).on('submit', '.rphub-form', function(e) {
            if (!validateForm($(this))) {
                e.preventDefault();
                return false;
            }
        });
    }

    function validateField($field) {
        const value = $field.val();
        const type = $field.data('validate');
        let isValid = true;
        let message = '';
        
        // Remove existing validation
        $field.removeClass('rphub-field-error');
        $field.next('.rphub-field-error-message').remove();
        
        switch (type) {
            case 'required':
                if (!value.trim()) {
                    isValid = false;
                    message = 'Este campo es obligatorio.';
                }
                break;
                
            case 'email':
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (value && !emailRegex.test(value)) {
                    isValid = false;
                    message = 'Introduce un email valido.';
                }
                break;
                
            case 'url':
                const urlRegex = /^https?:\/\/.+/;
                if (value && !urlRegex.test(value)) {
                    isValid = false;
                    message = 'Introduce una URL valida.';
                }
                break;
                
            case 'number':
                if (value && isNaN(value)) {
                    isValid = false;
                    message = 'Introduce un numero valido.';
                }
                break;
        }
        
        if (!isValid) {
            $field.addClass('rphub-field-error');
            $field.after('<div class="rphub-field-error-message">' + message + '</div>');
        }
        
        return isValid;
    }

    function validateForm($form) {
        let isValid = true;
        
        $form.find('.rphub-validate').each(function() {
            if (!validateField($(this))) {
                isValid = false;
            }
        });
        
        return isValid;
    }

    // Charts
    function initCharts() {
        // Health Score Chart
        const healthCtx = document.getElementById('rphub-health-chart');
        if (healthCtx) {
            createHealthChart(healthCtx);
        }
        
        // Sites Status Chart
        const statusCtx = document.getElementById('rphub-status-chart');
        if (statusCtx) {
            createStatusChart(statusCtx);
        }
        
        // Tasks Chart
        const tasksCtx = document.getElementById('rphub-tasks-chart');
        if (tasksCtx) {
            createTasksChart(tasksCtx);
        }
    }

    function createHealthChart(ctx) {
        // This will be implemented with actual data
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Excelente', 'Bueno', 'Regular', 'Critico'],
                datasets: [{
                    data: [0, 0, 0, 0], // Will be populated with real data
                    backgroundColor: ['#10b981', '#f59e0b', '#f97316', '#ef4444']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    function createStatusChart(ctx) {
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Online', 'Offline', 'Pendiente'],
                datasets: [{
                    data: [0, 0, 0], // Will be populated with real data
                    backgroundColor: ['#10b981', '#ef4444', '#f59e0b']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    function createTasksChart(ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: [], // Will be populated with dates
                datasets: [{
                    label: 'Tareas Completadas',
                    data: [], // Will be populated with real data
                    borderColor: '#2271b1',
                    backgroundColor: 'rgba(34, 113, 177, 0.1)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Utility Functions
    function showNotification(type, message, duration = 5000) {
        const $notification = $('<div class="rphub-notification ' + type + '">' + message + '</div>');
        
        // Add close button
        $notification.append('<button class="rphub-notification-close">&times;</button>');
        
        // Add to container or create one
        let $container = $('.rphub-notifications-container');
        if (!$container.length) {
            $container = $('<div class="rphub-notifications-container"></div>');
            $('body').append($container);
        }
        
        $container.append($notification);
        
        // Show notification
        $notification.slideDown();
        
        // Auto-hide
        if (duration > 0) {
            setTimeout(function() {
                hideNotification($notification);
            }, duration);
        }
        
        // Close button
        $notification.find('.rphub-notification-close').click(function() {
            hideNotification($notification);
        });
    }

    function hideNotification($notification) {
        $notification.slideUp(function() {
            $(this).remove();
        });
    }

    function getTimeAgo(date) {
        const now = new Date();
        const diffInSeconds = Math.floor((now - date) / 1000);
        
        if (diffInSeconds < 60) {
            return 'Hace ' + diffInSeconds + ' segundos';
        } else if (diffInSeconds < 3600) {
            return 'Hace ' + Math.floor(diffInSeconds / 60) + ' minutos';
        } else if (diffInSeconds < 86400) {
            return 'Hace ' + Math.floor(diffInSeconds / 3600) + ' horas';
        } else {
            return 'Hace ' + Math.floor(diffInSeconds / 86400) + ' dias';
        }
    }

    function formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }

    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    function debounce(func, wait, immediate) {
        let timeout;
        return function executedFunction() {
            const context = this;
            const args = arguments;
            
            const later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            
            const callNow = immediate && !timeout;
            
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            
            if (callNow) func.apply(context, args);
        };
    }

    // AJAX Error Handler
    $(document).ajaxError(function(event, jqXHR, ajaxSettings, thrownError) {
        if (jqXHR.status !== 200) {
            let message = thrownError || jqXHR.statusText || 'error';
            if (jqXHR.status === 403) {
                message = '403: permiso insuficiente o nonce caducado. Recarga la pagina.';
            }
            console.error('AJAX Error:', {
                status: jqXHR.status,
                request: ajaxSettings && ajaxSettings.data,
                response: jqXHR.responseText
            });
            showNotification('error', 'Error de conexion: ' + message);
        }
    });

    // Loading States
    function showLoading($element) {
        $element.addClass('rphub-loading');
        
        if (!$element.find('.rphub-loading-overlay').length) {
            $element.append('<div class="rphub-loading-overlay"><div class="rphub-spinner"></div></div>');
        }
    }

    function hideLoading($element) {
        $element.removeClass('rphub-loading');
        $element.find('.rphub-loading-overlay').remove();
    }

    // WHM Integration Functions
    window.rphubTestWHMConnection = function() {
        const $button = $('#whm-test-connection');
        const $output = $('#whm-test-output');
        
        if (isDebug()) { console.log('Iniciando test de conexion WHM'); }
        if (isDebug()) { console.log('AJAX URL:', ajaxurl); }
        if (isDebug()) { console.log('WHM nonce available:', !!(window.rphub_ajax && rphub_ajax.nonce)); }
        
        // Show loading state
        $button.prop('disabled', true).text('Probando...');
        $output.html('<div class="rphub-loading-text">Probando conexion WHM...</div>');
        
        $.ajax({
            url: ajaxurl, // Use WordPress global ajaxurl
            type: 'POST',
            data: {
                action: 'rphub_test_whm_connection',
                nonce: rphub_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $output.html('<div class="notice notice-success"><p>' + escHtml(response.data.message) + '</p></div>');
                } else {
                    $output.html('<div class="notice notice-error"><p>Error: ' + escHtml(response.data) + '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error Details:', { xhr, status, error });
                $output.html('<div class="notice notice-error"><p>Error de conexion: ' + escHtml(error) + '</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Probar Conexion');
            }
        });
    };
    
    window.rphubRunWHMDiagnostics = function() {
        const $button = $('#whm-run-diagnostics');
        const $output = $('#whm-diagnostics-output');
        
        // Show loading state
        $button.prop('disabled', true).text('Ejecutando...');
        $output.html('<div class="rphub-loading-text">Ejecutando diagnosticos WHM...</div>');
        
        $.ajax({
            url: ajaxurl, // Use WordPress global ajaxurl
            type: 'POST',
            data: {
                action: 'rphub_whm_run_diagnostics',
                nonce: rphub_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    let html = '<div class="notice notice-success"><p>Diagnosticos completados</p></div>';
                    html += '<div class="whm-diagnostics-results">';
                    
                    if (response.data.tests) {
                        response.data.tests.forEach(function(test) {
                            const statusClass = test.passed ? 'success' : 'error';
                            html += `<div class="diagnostic-test ${escHtml(statusClass)}">
                                        <strong>${escHtml(test.name)}:</strong> ${escHtml(test.message)}
                                     </div>`;
                        });
                    }
                    
                    html += '</div>';
                    $output.html(html);
                } else {
                    $output.html('<div class="notice notice-error"><p>Error: ' + escHtml(response.data) + '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error Details:', { xhr, status, error });
                $output.html('<div class="notice notice-error"><p>Error de conexion: ' + escHtml(error) + '</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Ejecutar Diagnosticos');
            }
        });
    };
    
    // Dashboard Functions
    window.rphubLoadDashboardStats = function() {
        $.ajax({
            url: ajaxurl, // Use WordPress global ajaxurl
            type: 'POST',
            data: {
                action: 'rphub_get_dashboard_stats',
                nonce: rphub_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateDashboardStats(response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('Dashboard Stats Error:', { xhr, status, error });
            }
        });
    };
    
    function updateDashboardStats(stats) {
        $('#total-sites-count').text(stats.total_sites);
        $('#active-sites-count').text(stats.active_sites);
        $('#pending-tasks-count').text(stats.pending_tasks);
        $('#unread-notifications-count').text(stats.unread_notifications);
        $('#avg-health-score').text(stats.avg_health_score + '%');
    }
    
    window.rphubLoadSitesList = function() {
        $.ajax({
            url: ajaxurl, // Use WordPress global ajaxurl
            type: 'POST',
            data: {
                action: 'rphub_get_sites_list',
                nonce: rphub_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateSitesList(response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('Sites List Error:', { xhr, status, error });
            }
        });
    };
    
    function updateSitesList(sites) {
        const $container = $('#sites-list-container');
        let html = '';
        
        sites.forEach(function(site) {
            const statusClass = site.status === 'active' ? 'status-active' : 'status-warning';
            const healthClass = site.health_score >= 80 ? 'health-good' : 
                               site.health_score >= 60 ? 'health-warning' : 'health-poor';
            
            html += `<tr class="site-row ${escHtml(statusClass)}">
                        <td><strong>${escHtml(site.name)}</strong><br><small>${escHtml(site.url)}</small></td>
                        <td>${escHtml(site.plan)}</td>
                        <td><span class="health-score ${escHtml(healthClass)}">${escHtml(String(site.health_score))}%</span></td>
                        <td><span class="status-badge ${escHtml(statusClass)}">${escHtml(site.status)}</span></td>
                        <td>
                            <button onclick="rphubTestSiteConnection(${site.id})" class="button button-small">
                                Probar
                            </button>
                        </td>
                     </tr>`;
        });
        
        $container.html(html);
    }
    
    window.rphubTestSiteConnection = function(siteId) {
        $.ajax({
            url: ajaxurl, // Use WordPress global ajaxurl
            type: 'POST',
            data: {
                action: 'rphub_test_site_connection',
                site_id: siteId,
                nonce: rphub_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('success', response.data.message);
                } else {
                    showNotification('error', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('Test Site Connection Error:', { xhr, status, error });
                showNotification('error', 'Error de conexion.');
            }
        });
    };

    // Export global functions
    window.rphubShowNotification = showNotification;
    window.rphubOpenModal = openModal;
    window.rphubCloseModal = closeModal;
    window.rphubShowLoading = showLoading;
    window.rphubHideLoading = hideLoading;
    window.rphubGetTimeAgo = getTimeAgo;
    window.rphubFormatBytes = formatBytes;
    window.rphubFormatNumber = formatNumber;
    window.rphubDebounce = debounce;

})(jQuery);
