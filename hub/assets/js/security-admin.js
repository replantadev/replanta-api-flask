/**
 * Security Framework Admin JavaScript
 * Handles real-time monitoring, AJAX requests, and user interactions
 */

(function($) {
    'use strict';
    
    // Security Dashboard Object
    const SecurityDashboard = {
        
        // Initialize the dashboard
        init: function() {
            this.bindEvents();
            this.initCharts();
            this.startRealTimeMonitoring();
            this.initTabSwitching();
        },
        
        // Bind event handlers
        bindEvents: function() {
            $('#run-security-scan').on('click', this.runSecurityScan);

            // Settings form
            $('#security-settings-form').on('submit', this.saveSecuritySettings);
        },
        
        // Initialize charts
        initCharts: function() {
            // Charts are initialized in PHP template
            // This method can be used for dynamic chart updates
        },
        
        // Start real-time monitoring
        startRealTimeMonitoring: function() {
            // Update dashboard every 30 seconds
            setInterval(() => {
                this.refreshDashboardStats();
                this.refreshThreatList();
            }, 30000);
        },
        
        // Initialize tab switching
        initTabSwitching: function() {
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                
                const target = $(this).attr('href');
                
                // Update tab states
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                // Show/hide content
                $('.tab-content').hide();
                $(target).show();
            });
        },
        
        // Run security scan
        runSecurityScan: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const originalText = $button.text();
            
            $button.prop('disabled', true)
                   .html('<span class="dashicons dashicons-update-alt"></span> ' + rphub_security_ajax.strings.scanning);
            
            $.ajax({
                url: rphub_security_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rphub_security_scan',
                    nonce: rphub_security_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        SecurityDashboard.showNotification(rphub_security_ajax.strings.scan_complete, 'success');
                        SecurityDashboard.refreshDashboardStats();
                        SecurityDashboard.refreshThreatList();
                    } else {
                        SecurityDashboard.showNotification(response.data || 'Scan failed', 'error');
                    }
                },
                error: function() {
                    SecurityDashboard.showNotification('Network error occurred', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).html(originalText);
                }
            });
        },
        
        // Save security settings
        saveSecuritySettings: function(e) {
            e.preventDefault();
            
            const formData = $(this).serialize();
            const $button = $(this).find('[type="submit"]');
            const originalText = $button.text();
            
            $button.prop('disabled', true).text('Saving...');
            
            $.ajax({
                url: rphub_security_ajax.ajax_url,
                type: 'POST',
                data: formData + '&action=rphub_security_update_settings&nonce=' + rphub_security_ajax.nonce,
                success: function(response) {
                    if (response.success) {
                        SecurityDashboard.showNotification(rphub_security_ajax.strings.settings_saved, 'success');
                    } else {
                        SecurityDashboard.showNotification(response.data || 'Save failed', 'error');
                    }
                },
                error: function() {
                    SecurityDashboard.showNotification('Network error occurred', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },
        
        // Refresh dashboard statistics
        refreshDashboardStats: function() {
            $.ajax({
                url: rphub_security_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rphub_security_get_stats',
                    nonce: rphub_security_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        SecurityDashboard.updateDashboardStats(response.data);
                    }
                }
            });
        },
        
        // Refresh threat list
        refreshThreatList: function() {
            $.ajax({
                url: rphub_security_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rphub_security_get_threats',
                    nonce: rphub_security_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        SecurityDashboard.updateThreatList(response.data);
                    }
                }
            });
        },
        
        // Update dashboard statistics in UI
        updateDashboardStats: function(stats) {
            $('.stat-card.threat-level .stat-value')
                .removeClass('low medium high critical')
                .addClass(stats.threat_level)
                .text(stats.threat_level.charAt(0).toUpperCase() + stats.threat_level.slice(1));
            
            $('.stat-card.threat-level p').text(stats.threats_count + ' Active Threats');
            $('.stat-card.scans-completed .stat-value').text(stats.scans_today);
            $('.stat-card.blocked-attempts .stat-value').text(stats.blocked_today);
            $('.stat-card.compliance-score .stat-value').text(stats.compliance_score + '%');
        },
        
        // Update threat list in UI
        updateThreatList: function(threats) {
            const $threatList = $('.threat-list');
            $threatList.empty();
            
            threats.forEach(function(threat) {
                const threatHtml = `
                    <div class="threat-item severity-${threat.severity}">
                        <span class="threat-type">${threat.threat_type}</span>
                        <span class="threat-target">${threat.target}</span>
                        <span class="threat-severity ${threat.severity}">${threat.severity.charAt(0).toUpperCase() + threat.severity.slice(1)}</span>
                    </div>
                `;
                $threatList.append(threatHtml);
            });
        },
        
        // Show notification
        showNotification: function(message, type = 'info') {
            const notificationHtml = `
                <div class="notice notice-${type} is-dismissible">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `;
            
            $('.wrap h1').after(notificationHtml);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                $('.notice').fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Manual dismiss
            $(document).on('click', '.notice-dismiss', function() {
                $(this).closest('.notice').fadeOut(function() {
                    $(this).remove();
                });
            });
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        SecurityDashboard.init();
    });
    
    // Make SecurityDashboard globally available
    window.SecurityDashboard = SecurityDashboard;
    
})(jQuery);
