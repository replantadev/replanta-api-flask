/**
 * Enhanced Hub Dashboard JavaScript
 * Handles site management, bulk actions, and real-time updates
 */

jQuery(document).ready(function($) {
    'use strict';

    // Global Hub Dashboard object
    window.RPHubDashboard = {
        initialized: false,
        currentPage: 1,
        selectedSites: [],
        refreshInterval: null,
        
        init: function() {
            if (this.initialized) return;
            
            this.bindEvents();
            this.loadSitesOverview();
            this.loadMaintenanceSummary();
            this.loadRevenueStats();
            this.startAutoRefresh();
            this.initialized = true;
            
            console.log('Replanta Hub Enhanced Dashboard initialized');
        },
        
        bindEvents: function() {
            var self = this;
            
            // Filter controls
            $('#rphub-plan-filter, #rphub-status-filter').on('change', function() {
                self.currentPage = 1;
                self.loadSitesOverview();
            });
            
            // Refresh button
            $('#rphub-refresh-sites').on('click', function(e) {
                e.preventDefault();
                self.loadSitesOverview();
            });
            
            // Bulk actions
            $('#rphub-bulk-action').on('change', function() {
                var action = $(this).val();
                if (action === 'assign_plan') {
                    $('#rphub-bulk-plan').show();
                } else {
                    $('#rphub-bulk-plan').hide();
                }
            });
            
            $('#rphub-apply-bulk').on('click', function(e) {
                e.preventDefault();
                self.applyBulkAction();
            });
            
            // Pagination
            $('#rphub-prev-page').on('click', function(e) {
                e.preventDefault();
                if (self.currentPage > 1) {
                    self.currentPage--;
                    self.loadSitesOverview();
                }
            });
            
            $('#rphub-next-page').on('click', function(e) {
                e.preventDefault();
                self.currentPage++;
                self.loadSitesOverview();
            });
            
            // Site card interactions
            $(document).on('click', '.rphub-site-card', function(e) {
                if ($(e.target).is('.rphub-site-checkbox') || $(e.target).closest('.rphub-site-actions').length) {
                    return;
                }
                
                var siteId = $(this).data('site-id');
                self.showSiteDetails(siteId);
            });
            
            // Site selection
            $(document).on('change', '.rphub-site-checkbox', function(e) {
                e.stopPropagation();
                var siteId = parseInt($(this).data('site-id'));
                var $card = $(this).closest('.rphub-site-card');
                
                if ($(this).is(':checked')) {
                    self.selectedSites.push(siteId);
                    $card.addClass('selected');
                } else {
                    self.selectedSites = self.selectedSites.filter(function(id) {
                        return id !== siteId;
                    });
                    $card.removeClass('selected');
                }
                
                self.updateBulkActionState();
            });
            
            // Select all sites
            $(document).on('change', '#rphub-select-all', function() {
                var isChecked = $(this).is(':checked');
                $('.rphub-site-checkbox').prop('checked', isChecked).trigger('change');
            });
            
            // Site actions
            $(document).on('click', '[data-rphub-action]', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var action = $(this).data('rphub-action');
                var siteId = $(this).data('site-id');
                self.executeSiteAction(action, siteId);
            });
            
            // Modal close
            $(document).on('click', '.rphub-site-modal-close, .rphub-site-modal', function(e) {
                if (e.target === this) {
                    self.closeSiteModal();
                }
            });
            
            // Revenue stats period
            $('#rphub-stats-period').on('change', function() {
                self.loadRevenueStats();
            });
            
            // Escape key to close modal
            $(document).on('keydown', function(e) {
                if (e.keyCode === 27) {
                    self.closeSiteModal();
                }
            });
        },
        
        loadSitesOverview: function() {
            var self = this;
            var $grid = $('#rphub-sites-grid');
            
            this.showLoading($grid, 'Loading sites...');
            
            $.ajax({
                url: rphub_dashboard.ajax_url,
                type: 'POST',
                data: {
                    action: 'rphub_get_sites_overview',
                    nonce: rphub_dashboard.nonce,
                    page: this.currentPage,
                    per_page: 12,
                    plan_filter: $('#rphub-plan-filter').val(),
                    status_filter: $('#rphub-status-filter').val()
                },
                success: function(response) {
                    if (response.success) {
                        self.renderSitesGrid(response.data);
                        self.updatePagination(response.data.pagination);
                    } else {
                        self.showError('Error loading sites: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    self.showError('AJAX error: ' + error);
                },
                complete: function() {
                    self.hideLoading($grid);
                }
            });
        },
        
        renderSitesGrid: function(data) {
            var $grid = $('#rphub-sites-grid');
            var html = '';
            
            if (data.sites && data.sites.length > 0) {
                $.each(data.sites, function(index, site) {
                    html += '<div class="rphub-site-card rphub-fade-in" data-site-id="' + site.id + '">';
                    html += '<input type="checkbox" class="rphub-site-checkbox" data-site-id="' + site.id + '">';
                    
                    html += '<div class="rphub-site-card-header">';
                    html += '<div>';
                    html += '<h3 class="rphub-site-title">' + site.name + '</h3>';
                    html += '<a href="' + site.url + '" class="rphub-site-url" target="_blank">' + site.url + '</a>';
                    html += '</div>';
                    html += '<div class="rphub-site-status ' + site.status + '">';
                    html += '<span class="rphub-status-indicator"></span>' + site.status.toUpperCase();
                    html += '</div>';
                    html += '</div>';
                    
                    if (site.plan && site.plan.name) {
                        html += '<div class="rphub-site-plan">';
                        html += '<span class="rphub-plan-badge rphub-plan-' + site.plan.slug + '">' + site.plan.name + '</span>';
                        html += '<span class="rphub-plan-price">€' + site.plan.price + '/month</span>';
                        html += '</div>';
                    }
                    
                    html += '<div class="rphub-site-stats">';
                    html += '<div class="rphub-stat">';
                    html += '<span class="rphub-stat-value">' + (site.health_score || 0) + '%</span>';
                    html += '<span class="rphub-stat-label">Health Score</span>';
                    html += '</div>';
                    html += '<div class="rphub-stat">';
                    html += '<span class="rphub-stat-value">' + (site.updates_available || 0) + '</span>';
                    html += '<span class="rphub-stat-label">Updates</span>';
                    html += '</div>';
                    html += '</div>';
                    
                    html += '<div class="rphub-site-actions">';
                    html += '<button type="button" class="button button-small" data-rphub-action="backup" data-site-id="' + site.id + '">Backup</button>';
                    html += '<button type="button" class="button button-small" data-rphub-action="update" data-site-id="' + site.id + '">Update</button>';
                    html += '<button type="button" class="button button-small" data-rphub-action="health" data-site-id="' + site.id + '">Health</button>';
                    html += '</div>';
                    
                    html += '</div>';
                });
            } else {
                html = '<div class="rphub-no-sites">';
                html += '<p>No sites found matching the current filters.</p>';
                html += '</div>';
            }
            
            $grid.html(html);
            this.selectedSites = [];
            this.updateBulkActionState();
        },
        
        updatePagination: function(pagination) {
            var $pagination = $('#rphub-pagination');
            
            if (pagination.total_pages > 1) {
                $pagination.show();
                
                $('#rphub-prev-page').prop('disabled', pagination.current_page <= 1);
                $('#rphub-next-page').prop('disabled', pagination.current_page >= pagination.total_pages);
                
                $('#rphub-page-info').text(
                    'Page ' + pagination.current_page + ' of ' + pagination.total_pages +
                    ' (' + pagination.total_sites + ' sites)'
                );
            } else {
                $pagination.hide();
            }
        },
        
        updateBulkActionState: function() {
            var hasSelection = this.selectedSites.length > 0;
            $('#rphub-apply-bulk').prop('disabled', !hasSelection);
            
            if (hasSelection) {
                $('#rphub-apply-bulk').text('Apply to ' + this.selectedSites.length + ' sites');
            } else {
                $('#rphub-apply-bulk').text('Apply');
            }
        },
        
        applyBulkAction: function() {
            var action = $('#rphub-bulk-action').val();
            var planId = $('#rphub-bulk-plan').val();
            
            if (!action) {
                alert(rphub_dashboard.strings.error);
                return;
            }
            
            if (this.selectedSites.length === 0) {
                alert(rphub_dashboard.strings.no_sites_selected);
                return;
            }
            
            if (action === 'assign_plan' && !planId) {
                alert('Please select a plan');
                return;
            }
            
            if (!confirm(rphub_dashboard.strings.confirm_bulk)) {
                return;
            }
            
            this.showLoading($('#rphub-sites-grid'), 'Applying bulk action...');
            
            var self = this;
            $.ajax({
                url: rphub_dashboard.ajax_url,
                type: 'POST',
                data: {
                    action: 'rphub_bulk_action',
                    nonce: rphub_dashboard.nonce,
                    action_type: action,
                    site_ids: this.selectedSites,
                    plan_id: planId
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Bulk action completed successfully');
                        self.loadSitesOverview();
                        self.loadMaintenanceSummary();
                    } else {
                        self.showError('Bulk action failed: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    self.showError('AJAX error: ' + error);
                },
                complete: function() {
                    self.hideLoading($('#rphub-sites-grid'));
                }
            });
        },
        
        executeSiteAction: function(action, siteId) {
            var self = this;
            
            var actionMessages = {
                'backup': 'run backup',
                'update': 'update plugins',
                'health': 'run health check',
                'clear_cache': 'clear cache'
            };
            
            var message = actionMessages[action] || action;
            
            if (!confirm('Are you sure you want to ' + message + ' for this site?')) {
                return;
            }
            
            $.ajax({
                url: rphub_dashboard.ajax_url,
                type: 'POST',
                data: {
                    action: 'rphub_site_action',
                    nonce: rphub_dashboard.nonce,
                    site_action: action,
                    site_id: siteId
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Action completed successfully');
                        self.loadSitesOverview();
                    } else {
                        self.showError('Action failed: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    self.showError('AJAX error: ' + error);
                }
            });
        },
        
        showSiteDetails: function(siteId) {
            var self = this;
            
            // Create modal
            var modalHtml = '<div class="rphub-site-modal">';
            modalHtml += '<div class="rphub-site-modal-content">';
            modalHtml += '<div class="rphub-site-modal-header">';
            modalHtml += '<h2>Site Details</h2>';
            modalHtml += '<button type="button" class="rphub-site-modal-close">&times;</button>';
            modalHtml += '</div>';
            modalHtml += '<div class="rphub-site-modal-body">';
            modalHtml += '<div class="rphub-loading"><div class="rphub-spinner"></div><p>Loading site details...</p></div>';
            modalHtml += '</div>';
            modalHtml += '</div>';
            modalHtml += '</div>';
            
            $('body').append(modalHtml);
            
            // Load site details
            $.ajax({
                url: rphub_dashboard.ajax_url,
                type: 'POST',
                data: {
                    action: 'rphub_get_site_details',
                    nonce: rphub_dashboard.nonce,
                    site_id: siteId
                },
                success: function(response) {
                    if (response.success) {
                        self.renderSiteDetails(response.data);
                    } else {
                        $('.rphub-site-modal-body').html('<p>Error loading site details: ' + (response.data || 'Unknown error') + '</p>');
                    }
                },
                error: function(xhr, status, error) {
                    $('.rphub-site-modal-body').html('<p>AJAX error: ' + error + '</p>');
                }
            });
        },
        
        renderSiteDetails: function(data) {
            var site = data.site;
            var health = data.health_details;
            var backups = data.recent_backups;
            var activities = data.recent_activities;
            
            var html = '<div class="rphub-site-details">';
            
            // Site info
            html += '<div class="rphub-detail-section">';
            html += '<h3>' + site.name + '</h3>';
            html += '<p><strong>URL:</strong> <a href="' + site.url + '" target="_blank">' + site.url + '</a></p>';
            html += '<p><strong>Status:</strong> <span class="rphub-site-status ' + site.status + '">' + site.status.toUpperCase() + '</span></p>';
            html += '<p><strong>Plan:</strong> ' + (site.plan_name || 'No plan assigned') + '</p>';
            html += '<p><strong>Last Seen:</strong> ' + site.last_seen + '</p>';
            html += '</div>';
            
            // Health details
            if (health) {
                html += '<div class="rphub-detail-section">';
                html += '<h4>Health Score: ' + health.score + '%</h4>';
                html += '<div class="rphub-health-checks">';
                $.each(health.checks, function(index, check) {
                    html += '<div class="rphub-health-check rphub-status-' + check.status + '">';
                    html += '<strong>' + check.name + ':</strong> ' + check.message;
                    html += '</div>';
                });
                html += '</div>';
                html += '</div>';
            }
            
            // Recent backups
            if (backups && backups.length > 0) {
                html += '<div class="rphub-detail-section">';
                html += '<h4>Recent Backups</h4>';
                $.each(backups, function(index, backup) {
                    html += '<div class="rphub-backup-item">';
                    html += '<strong>' + backup.date + '</strong> - ' + backup.size + ' (' + backup.type + ')';
                    html += '</div>';
                });
                html += '</div>';
            }
            
            // Recent activities
            if (activities && activities.length > 0) {
                html += '<div class="rphub-detail-section">';
                html += '<h4>Recent Activities</h4>';
                $.each(activities, function(index, activity) {
                    html += '<div class="rphub-activity-item">';
                    html += '<div class="rphub-activity-content">';
                    html += '<div class="rphub-activity-action">' + activity.action + '</div>';
                    html += '<div class="rphub-activity-details">' + activity.details + '</div>';
                    html += '</div>';
                    html += '<div class="rphub-activity-time">' + activity.time + '</div>';
                    html += '</div>';
                });
                html += '</div>';
            }
            
            html += '</div>';
            
            $('.rphub-site-modal-body').html(html);
        },
        
        closeSiteModal: function() {
            $('.rphub-site-modal').remove();
        },
        
        loadMaintenanceSummary: function() {
            var self = this;
            
            $.ajax({
                url: rphub_dashboard.ajax_url,
                type: 'POST',
                data: {
                    action: 'rphub_get_maintenance_summary',
                    nonce: rphub_dashboard.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.updateMaintenanceSummary(response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading maintenance summary:', error);
                }
            });
        },
        
        updateMaintenanceSummary: function(data) {
            $('#rphub-backups-count').text(data.backups_today || 0);
            $('#rphub-updates-count').text(data.updates_available || 0);
            $('#rphub-issues-count').text(data.sites_with_issues || 0);
            $('#rphub-revenue-month').text('€' + (data.monthly_revenue || 0));
            
            // Update recent activities
            if (data.recent_activities) {
                var activitiesHtml = '';
                $.each(data.recent_activities, function(index, activity) {
                    activitiesHtml += '<div class="rphub-activity-item">';
                    activitiesHtml += '<div class="rphub-activity-content">';
                    activitiesHtml += '<div class="rphub-activity-action">' + activity.action + '</div>';
                    activitiesHtml += '<div class="rphub-activity-details">' + activity.details + '</div>';
                    activitiesHtml += '</div>';
                    activitiesHtml += '<div class="rphub-activity-time">' + activity.time + '</div>';
                    activitiesHtml += '</div>';
                });
                $('#rphub-recent-activities .rphub-activity-list').html(activitiesHtml);
            }
        },
        
        loadRevenueStats: function() {
            var self = this;
            var period = $('#rphub-stats-period').val() || 30;
            
            $.ajax({
                url: rphub_dashboard.ajax_url,
                type: 'POST',
                data: {
                    action: 'rphub_get_revenue_stats',
                    nonce: rphub_dashboard.nonce,
                    period: period
                },
                success: function(response) {
                    if (response.success) {
                        self.updateRevenueStats(response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading revenue stats:', error);
                }
            });
        },
        
        updateRevenueStats: function(data) {
            // Update plan counts and revenue
            $('#rphub-semilla-count').text(data.plans.semilla.count || 0);
            $('#rphub-semilla-revenue').text('€' + (data.plans.semilla.revenue || 0));
            
            $('#rphub-raiz-count').text(data.plans.raiz.count || 0);
            $('#rphub-raiz-revenue').text('€' + (data.plans.raiz.revenue || 0));
            
            $('#rphub-ecosistema-count').text(data.plans.ecosistema.count || 0);
            $('#rphub-ecosistema-revenue').text('€' + (data.plans.ecosistema.revenue || 0));
            
            $('#rphub-total-revenue').text('€' + (data.total_revenue || 0));
            
            // Update chart (placeholder - would need a charting library)
            this.updateRevenueChart(data.chart_data);
        },
        
        updateRevenueChart: function(chartData) {
            // Placeholder for chart implementation
            // Would use Chart.js, D3.js, or similar library
            var $chart = $('#rphub-revenue-chart');
            if ($chart.length) {
                // Chart implementation would go here
                console.log('Chart data:', chartData);
            }
        },
        
        startAutoRefresh: function() {
            var self = this;
            this.stopAutoRefresh();
            
            this.refreshInterval = setInterval(function() {
                self.loadMaintenanceSummary();
                // Don't auto-refresh sites grid to avoid interrupting user interaction
            }, 300000); // Refresh every 5 minutes
        },
        
        stopAutoRefresh: function() {
            if (this.refreshInterval) {
                clearInterval(this.refreshInterval);
                this.refreshInterval = null;
            }
        },
        
        showLoading: function($container, message) {
            var loadingHtml = '<div class="rphub-loading">';
            loadingHtml += '<div class="rphub-spinner"></div>';
            loadingHtml += '<p>' + (message || rphub_dashboard.strings.loading) + '</p>';
            loadingHtml += '</div>';
            
            $container.html(loadingHtml);
        },
        
        hideLoading: function($container) {
            $container.find('.rphub-loading').remove();
        },
        
        showSuccess: function(message) {
            this.showNotice(message, 'success');
        },
        
        showError: function(message) {
            this.showNotice(message, 'error');
        },
        
        showNotice: function(message, type) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible">' +
                          '<p>' + message + '</p>' +
                          '<button type="button" class="notice-dismiss">' +
                          '<span class="screen-reader-text">Dismiss this notice.</span>' +
                          '</button>' +
                          '</div>');
            
            $('#wpbody-content').prepend($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Manual dismiss
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            });
        }
    };
    
    // Initialize dashboard when ready
    if ($('.rphub-sites-overview').length) {
        RPHubDashboard.init();
    }
});
