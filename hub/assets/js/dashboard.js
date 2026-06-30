/**
 * Dashboard avanzado para sitios individuales
 * Maneja todas las interacciones y actualizaciones en tiempo real
 */

(function($) {
    'use strict';

    window.RphubDashboard = {
        siteId: null,
        refreshInterval: null,
        charts: {},
        
        init: function() {
            this.siteId = rphub_ajax.site_id;
            this.bindEvents();
            this.initCharts();
            this.startAutoRefresh();
            this.loadInitialData();
        },

        bindEvents: function() {
            var self = this;

            // Tabs
            $('.tab-button').on('click', function(e) {
                e.preventDefault();
                self.switchTab($(this).data('tab'));
            });

            // Action buttons
            $('.rphub-btn[data-action]').on('click', function(e) {
                e.preventDefault();
                var action = $(this).data('action');
                self.executeAction(action, $(this));
            });

            // Real-time updates toggle
            $('#realtime-toggle').on('change', function() {
                if ($(this).is(':checked')) {
                    self.startAutoRefresh();
                } else {
                    self.stopAutoRefresh();
                }
            });

            // Refresh button
            $('#manual-refresh').on('click', function(e) {
                e.preventDefault();
                self.refreshAllData();
            });

            // Performance chart period selector
            $('#performance-period').on('change', function() {
                self.updatePerformanceChart($(this).val());
            });

            // Security events filter
            $('#security-events-filter').on('change', function() {
                self.filterSecurityEvents($(this).val());
            });

            // Backup action buttons
            $('.backup-action').on('click', function(e) {
                e.preventDefault();
                var action = $(this).data('action');
                var backupId = $(this).data('backup-id');
                self.executeBackupAction(action, backupId);
            });

            // Cloudflare cache controls
            $('#cf-purge-cache').on('click', function(e) {
                e.preventDefault();
                self.purgeCloudflareCache();
            });

            // PageSpeed test
            $('#run-pagespeed').on('click', function(e) {
                e.preventDefault();
                self.runPageSpeedTest();
            });

            // Security scan
            $('#run-security-scan').on('click', function(e) {
                e.preventDefault();
                self.runSecurityScan();
            });
        },

        switchTab: function(tabName) {
            $('.tab-button').removeClass('active');
            $('.tab-content').removeClass('active');
            
            $('[data-tab="' + tabName + '"]').addClass('active');
            $('#' + tabName + '-tab').addClass('active');

            // Load tab-specific data
            this.loadTabData(tabName);
        },

        loadTabData: function(tabName) {
            switch(tabName) {
                case 'performance':
                    this.loadPerformanceData();
                    break;
                case 'security':
                    this.loadSecurityData();
                    break;
                case 'backups':
                    this.loadBackupData();
                    break;
                case 'analytics':
                    this.loadAnalyticsData();
                    break;
            }
        },

        executeAction: function(action, button) {
            var self = this;
            var originalText = button.text();
            
            button.prop('disabled', true).text('Procesando...');
            this.showLoading();

            $.ajax({
                url: rphub_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rphub_site_action',
                    site_action: action,
                    site_id: this.siteId,
                    nonce: rphub_ajax.nonce
                },
                success: function(response) {
                    self.hideLoading();
                    
                    if (response.success) {
                        self.showNotification('success', response.data.message || 'Acción completada exitosamente');
                        
                        // Refresh relevant data based on action
                        switch(action) {
                            case 'refresh-data':
                                self.refreshAllData();
                                break;
                            case 'test-connection':
                                self.updateConnectionStatus(response.data);
                                break;
                            case 'run-pagespeed':
                                self.updatePageSpeedResults(response.data);
                                break;
                            case 'security-scan':
                                self.updateSecurityResults(response.data);
                                break;
                        }
                    } else {
                        self.showNotification('error', response.data || 'Error al ejecutar la acción');
                    }
                },
                error: function(xhr, status, error) {
                    self.hideLoading();
                    self.showNotification('error', 'Error de conexión: ' + error);
                },
                complete: function() {
                    button.prop('disabled', false).text(originalText);
                }
            });
        },

        loadPerformanceData: function() {
            var self = this;
            
            $.ajax({
                url: rphub_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rphub_get_pagespeed_history',
                    site_id: this.siteId,
                    limit: 30,
                    nonce: rphub_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.updatePerformanceChart(response.data);
                        self.updateCoreWebVitals(response.data);
                    }
                }
            });
        },

        loadSecurityData: function() {
            var self = this;
            
            $.ajax({
                url: rphub_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rphub_security_get_dashboard',
                    site_id: this.siteId,
                    nonce: rphub_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.updateSecurityDashboard(response.data);
                    }
                }
            });
        },

        loadBackupData: function() {
            var self = this;
            
            $.ajax({
                url: rphub_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rphub_backuply_get_backups',
                    site_id: this.siteId,
                    nonce: rphub_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.updateBackupList(response.data.backups);
                        self.updateBackupStats(response.data.stats);
                    }
                }
            });
        },

        loadAnalyticsData: function() {
            var self = this;
            
            $.ajax({
                url: rphub_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rphub_cloudflare_get_analytics',
                    site_id: this.siteId,
                    days: 7,
                    nonce: rphub_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.updateAnalyticsCharts(response.data);
                    }
                }
            });
        },

        initCharts: function() {
            // Inicializar Chart.js
            if (typeof Chart !== 'undefined') {
                this.initPerformanceChart();
                this.initAnalyticsChart();
                this.initSecurityChart();
            }
        },

        initPerformanceChart: function() {
            var ctx = document.getElementById('performance-chart');
            if (!ctx) return;

            this.charts.performance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'PageSpeed Móvil',
                        data: [],
                        borderColor: '#3B82F6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'PageSpeed Desktop',
                        data: [],
                        borderColor: '#10B981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Evolución PageSpeed'
                        },
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Score'
                            }
                        }
                    }
                }
            });
        },

        initAnalyticsChart: function() {
            var ctx = document.getElementById('analytics-chart');
            if (!ctx) return;

            this.charts.analytics = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Cached', 'Uncached'],
                    datasets: [{
                        data: [0, 0],
                        backgroundColor: ['#10B981', '#EF4444'],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Cache Hit Rate'
                        }
                    }
                }
            });
        },

        updatePerformanceChart: function(data) {
            if (!this.charts.performance || !data) return;

            var labels = [];
            var mobileData = [];
            var desktopData = [];

            data.forEach(function(item) {
                labels.push(new Date(item.created_at).toLocaleDateString());
                mobileData.push(item.mobile_score);
                desktopData.push(item.desktop_score);
            });

            this.charts.performance.data.labels = labels;
            this.charts.performance.data.datasets[0].data = mobileData;
            this.charts.performance.data.datasets[1].data = desktopData;
            this.charts.performance.update();
        },

        updateAnalyticsCharts: function(data) {
            if (!this.charts.analytics || !data.requests) return;

            var cached = data.requests.cached || 0;
            var uncached = data.requests.uncached || 0;

            this.charts.analytics.data.datasets[0].data = [cached, uncached];
            this.charts.analytics.update();

            // Update stats display
            $('#cache-hit-rate').text(data.cache_hit_rate + '%');
            $('#total-requests').text(this.formatNumber(data.requests.total));
            $('#threats-blocked').text(this.formatNumber(data.threats.total));
        },

        updateSecurityDashboard: function(data) {
            // Update security score
            $('#security-score').text(data.overall_score.score);
            $('#security-grade').text(data.overall_score.grade);

            // Update vulnerabilities count
            $('#vulnerabilities-count').text(data.vulnerabilities.total);
            
            // Update security status indicators
            this.updateSecurityIndicators(data);

            // Update recommendations
            this.updateSecurityRecommendations(data.recommendations);
        },

        updateSecurityIndicators: function(data) {
            var indicators = {
                'ssl-status': data.ssl_status.valid ? 'good' : 'bad',
                'malware-status': data.malware_status.infected ? 'bad' : 'good',
                'firewall-status': data.firewall_stats.blocked_attacks > 0 ? 'good' : 'neutral'
            };

            $.each(indicators, function(id, status) {
                $('#' + id).removeClass('good bad neutral').addClass(status);
            });
        },

        updateBackupList: function(backups) {
            var container = $('#backup-list');
            container.empty();

            if (!backups || backups.length === 0) {
                container.html('<p>No se encontraron backups.</p>');
                return;
            }

            backups.forEach(function(backup) {
                var statusClass = backup.status === 'completed' ? 'success' : 
                                backup.status === 'failed' ? 'error' : 'warning';
                
                var row = $('<div class="backup-item">' +
                    '<div class="backup-info">' +
                        '<h4>' + backup.type + ' - ' + new Date(backup.created_at).toLocaleDateString() + '</h4>' +
                        '<p>Estado: <span class="status ' + statusClass + '">' + backup.status + '</span></p>' +
                        '<p>Tamaño: ' + backup.size_formatted + '</p>' +
                    '</div>' +
                    '<div class="backup-actions">' +
                        '<button class="button backup-action" data-action="restore" data-backup-id="' + backup.id + '">Restaurar</button>' +
                        '<button class="button backup-action" data-action="download" data-backup-id="' + backup.id + '">Descargar</button>' +
                    '</div>' +
                '</div>');
                
                container.append(row);
            });
        },

        runPageSpeedTest: function() {
            var self = this;
            var button = $('#run-pagespeed');
            var originalText = button.text();
            
            button.prop('disabled', true).text('Analizando...');
            this.showLoading('Ejecutando análisis PageSpeed...');

            $.ajax({
                url: rphub_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rphub_run_pagespeed_test',
                    site_id: this.siteId,
                    nonce: rphub_ajax.nonce
                },
                success: function(response) {
                    self.hideLoading();
                    
                    if (response.success) {
                        self.showNotification('success', 'Análisis PageSpeed completado');
                        self.updatePageSpeedResults(response.data);
                        self.loadPerformanceData();
                    } else {
                        self.showNotification('error', response.data || 'Error en el análisis');
                    }
                },
                error: function() {
                    self.hideLoading();
                    self.showNotification('error', 'Error de conexión');
                },
                complete: function() {
                    button.prop('disabled', false).text(originalText);
                }
            });
        },

        runSecurityScan: function() {
            var self = this;
            var button = $('#run-security-scan');
            var originalText = button.text();
            
            button.prop('disabled', true).text('Escaneando...');
            this.showLoading('Ejecutando escaneo de seguridad...');

            $.ajax({
                url: rphub_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rphub_security_run_scan',
                    site_id: this.siteId,
                    scan_type: 'full',
                    nonce: rphub_ajax.nonce
                },
                success: function(response) {
                    self.hideLoading();
                    
                    if (response.success) {
                        self.showNotification('success', 'Escaneo de seguridad iniciado');
                        // El escaneo se ejecuta en background
                        setTimeout(function() {
                            self.loadSecurityData();
                        }, 10000); // Verificar resultados en 10 segundos
                    } else {
                        self.showNotification('error', response.data || 'Error en el escaneo');
                    }
                },
                error: function() {
                    self.hideLoading();
                    self.showNotification('error', 'Error de conexión');
                },
                complete: function() {
                    button.prop('disabled', false).text(originalText);
                }
            });
        },

        purgeCloudflareCache: function() {
            var self = this;
            
            $.ajax({
                url: rphub_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rphub_cloudflare_purge_cache',
                    site_id: this.siteId,
                    purge_type: 'everything',
                    nonce: rphub_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotification('success', 'Cache de Cloudflare purgado correctamente');
                    } else {
                        self.showNotification('error', response.data || 'Error al purgar cache');
                    }
                },
                error: function() {
                    self.showNotification('error', 'Error de conexión');
                }
            });
        },

        startAutoRefresh: function() {
            var self = this;
            this.refreshInterval = setInterval(function() {
                self.refreshAllData();
            }, 300000); // 5 minutos
        },

        stopAutoRefresh: function() {
            if (this.refreshInterval) {
                clearInterval(this.refreshInterval);
                this.refreshInterval = null;
            }
        },

        refreshAllData: function() {
            this.loadInitialData();
            
            // Refresh current tab
            var activeTab = $('.tab-button.active').data('tab');
            if (activeTab) {
                this.loadTabData(activeTab);
            }
        },

        loadInitialData: function() {
            // Load basic site info and metrics
            this.updateLastCheck();
        },

        updateLastCheck: function() {
            $('#last-check-time').text(new Date().toLocaleTimeString());
        },

        showLoading: function(message) {
            var overlay = $('#rphub-loading');
            if (message) {
                overlay.find('p').text(message);
            }
            overlay.show();
        },

        hideLoading: function() {
            $('#rphub-loading').hide();
        },

        showNotification: function(type, message) {
            var notification = $('<div class="rphub-notification ' + type + '">' +
                '<span class="message">' + message + '</span>' +
                '<button class="close">×</button>' +
            '</div>');

            $('body').append(notification);

            notification.fadeIn();

            // Auto hide after 5 seconds
            setTimeout(function() {
                notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);

            // Manual close
            notification.find('.close').on('click', function() {
                notification.fadeOut(function() {
                    $(this).remove();
                });
            });
        },

        formatNumber: function(num) {
            if (num >= 1000000) {
                return (num / 1000000).toFixed(1) + 'M';
            } else if (num >= 1000) {
                return (num / 1000).toFixed(1) + 'K';
            }
            return num.toString();
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if (typeof rphub_ajax !== 'undefined' && rphub_ajax.site_id) {
            RphubDashboard.init();
        }
    });

})(jQuery);

// Notification styles
var notificationStyles = `
<style>
.rphub-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 15px 20px;
    border-radius: 6px;
    color: white;
    font-weight: 600;
    z-index: 10000;
    display: none;
    min-width: 300px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.rphub-notification.success {
    background: #10B981;
}

.rphub-notification.error {
    background: #EF4444;
}

.rphub-notification.warning {
    background: #F59E0B;
}

.rphub-notification .close {
    background: none;
    border: none;
    color: white;
    font-size: 18px;
    font-weight: bold;
    float: right;
    margin-left: 10px;
    cursor: pointer;
    padding: 0;
    line-height: 1;
}

.backup-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    margin-bottom: 10px;
}

.backup-info h4 {
    margin: 0 0 5px 0;
    color: #374151;
}

.backup-info p {
    margin: 0;
    font-size: 14px;
    color: #6b7280;
}

.status.success {
    color: #10B981;
}

.status.error {
    color: #EF4444;
}

.status.warning {
    color: #F59E0B;
}

.backup-actions {
    display: flex;
    gap: 10px;
}
</style>
`;

$('head').append(notificationStyles);
