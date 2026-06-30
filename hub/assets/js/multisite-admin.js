/**
 * Multi-site Administration JavaScript
 * Handles all JavaScript functionality for multi-site management
 * 
 * @package ReplantaHub
 * @since 1.0.0
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Global variables
    var isExecuting = false;
    var selectedSites = [];
    var selectedGroups = [];
    var currentOperation = '';
    
    // Initialize components
    initializeTabs();
    initializeOperationSelector();
    initializeBulkOperations();
    initializeNetworkActions();
    initializeAnalytics();
    
    /**
     * Initialize tab functionality
     */
    function initializeTabs() {
        $('.tab-button').on('click', function() {
            var tabId = $(this).data('tab');
            
            // Update active tab button
            $('.tab-button').removeClass('active');
            $(this).addClass('active');
            
            // Update active tab content
            $('.tab-content').removeClass('active');
            $('#' + tabId).addClass('active');
        });
    }
    
    /**
     * Initialize operation selector
     */
    function initializeOperationSelector() {
        $('#operation-type').on('change', function() {
            var operationType = $(this).val();
            currentOperation = operationType;
            
            if (operationType) {
                loadOperationConfig(operationType);
                $('#operation-config').show();
            } else {
                $('#operation-config').hide();
            }
            
            updateExecuteButton();
        });
    }
    
    /**
     * Load operation configuration form
     */
    function loadOperationConfig(operationType) {
        var configHtml = '';
        
        switch (operationType) {
            case 'update_plugins':
                configHtml = `
                    <div class="config-section">
                        <h3>Plugin Update Configuration</h3>
                        <label>
                            <input type="checkbox" name="config[auto_update]" value="1" checked>
                            Enable automatic updates
                        </label>
                        <label>
                            <input type="checkbox" name="config[backup_before_update]" value="1" checked>
                            Create backup before updating
                        </label>
                        <label>
                            <input type="checkbox" name="config[exclude_major_updates]" value="1">
                            Exclude major version updates
                        </label>
                    </div>
                `;
                break;
                
            case 'update_themes':
                configHtml = `
                    <div class="config-section">
                        <h3>Theme Update Configuration</h3>
                        <label>
                            <input type="checkbox" name="config[backup_before_update]" value="1" checked>
                            Create backup before updating
                        </label>
                        <label>
                            <input type="checkbox" name="config[update_parent_themes]" value="1" checked>
                            Update parent themes
                        </label>
                        <label>
                            <input type="checkbox" name="config[preserve_customizations]" value="1" checked>
                            Preserve theme customizations
                        </label>
                    </div>
                `;
                break;
                
            case 'backup_site':
                configHtml = `
                    <div class="config-section">
                        <h3>Backup Configuration</h3>
                        <label>
                            <input type="text" name="config[backup_name]" placeholder="Backup name (optional)">
                            Backup name
                        </label>
                        <label>
                            <input type="checkbox" name="config[include_database]" value="1" checked>
                            Include database
                        </label>
                        <label>
                            <input type="checkbox" name="config[include_files]" value="1" checked>
                            Include files
                        </label>
                        <label>
                            <input type="checkbox" name="config[include_uploads]" value="1" checked>
                            Include uploads
                        </label>
                    </div>
                `;
                break;
                
            case 'optimize_database':
                configHtml = `
                    <div class="config-section">
                        <h3>Database Optimization Configuration</h3>
                        <label>
                            <input type="checkbox" name="config[optimize_tables]" value="1" checked>
                            Optimize tables
                        </label>
                        <label>
                            <input type="checkbox" name="config[repair_tables]" value="1">
                            Repair tables
                        </label>
                        <label>
                            <input type="checkbox" name="config[clean_revisions]" value="1" checked>
                            Clean post revisions
                        </label>
                        <label>
                            <input type="checkbox" name="config[clean_spam]" value="1" checked>
                            Clean spam comments
                        </label>
                    </div>
                `;
                break;
                
            case 'security_scan':
                configHtml = `
                    <div class="config-section">
                        <h3>Security Scan Configuration</h3>
                        <label>
                            <input type="checkbox" name="config[deep_scan]" value="1">
                            Deep scan (takes longer)
                        </label>
                        <label>
                            <input type="checkbox" name="config[scan_files]" value="1" checked>
                            Scan files for malware
                        </label>
                        <label>
                            <input type="checkbox" name="config[scan_database]" value="1" checked>
                            Scan database
                        </label>
                        <label>
                            <input type="checkbox" name="config[scan_permissions]" value="1" checked>
                            Check file permissions
                        </label>
                    </div>
                `;
                break;
                
            case 'performance_optimization':
                configHtml = `
                    <div class="config-section">
                        <h3>Performance Optimization Configuration</h3>
                        <label>
                            <input type="checkbox" name="config[clear_cache]" value="1" checked>
                            Clear cache
                        </label>
                        <label>
                            <input type="checkbox" name="config[optimize_images]" value="1">
                            Optimize images
                        </label>
                        <label>
                            <input type="checkbox" name="config[minify_assets]" value="1">
                            Minify CSS/JS
                        </label>
                        <label>
                            <input type="checkbox" name="config[database_cleanup]" value="1" checked>
                            Database cleanup
                        </label>
                        <label>
                            <input type="checkbox" name="config[preload_cache]" value="1">
                            Preload cache
                        </label>
                    </div>
                `;
                break;
                
            case 'content_sync':
                configHtml = `
                    <div class="config-section">
                        <h3>Content Synchronization Configuration</h3>
                        <label>
                            <select name="config[source_site_id]" required>
                                <option value="">Select source site...</option>
                                <!-- Options populated via AJAX -->
                            </select>
                            Source site
                        </label>
                        <label>
                            <input type="checkbox" name="config[sync_posts]" value="1">
                            Sync posts
                        </label>
                        <label>
                            <input type="checkbox" name="config[sync_pages]" value="1">
                            Sync pages
                        </label>
                        <label>
                            <input type="checkbox" name="config[sync_media]" value="1">
                            Sync media
                        </label>
                        <label>
                            <input type="checkbox" name="config[sync_settings]" value="1">
                            Sync settings
                        </label>
                    </div>
                `;
                break;
                
            case 'settings_deploy':
                configHtml = `
                    <div class="config-section">
                        <h3>Settings Deployment Configuration</h3>
                        <label>
                            <input type="checkbox" name="config[backup_current_settings]" value="1" checked>
                            Backup current settings
                        </label>
                        <label>
                            <input type="checkbox" name="config[force_overwrite]" value="1">
                            Force overwrite existing settings
                        </label>
                    </div>
                `;
                break;
        }
        
        $('#config-content').html(configHtml);
        
        // Load additional data for content sync
        if (operationType === 'content_sync') {
            loadSitesForSync();
        }
    }
    
    /**
     * Initialize bulk operations
     */
    function initializeBulkOperations() {
        // Site selection
        $('input[name="selected_sites[]"]').on('change', function() {
            updateSelectedSites();
            updateExecuteButton();
        });
        
        $('input[name="selected_groups[]"]').on('change', function() {
            updateSelectedGroups();
            updateExecuteButton();
        });
        
        // Select all/deselect all
        $('#select-all-sites').on('click', function() {
            $('input[name="selected_sites[]"]').prop('checked', true);
            updateSelectedSites();
            updateExecuteButton();
        });
        
        $('#deselect-all-sites').on('click', function() {
            $('input[name="selected_sites[]"]').prop('checked', false);
            updateSelectedSites();
            updateExecuteButton();
        });
        
        // Execute operation
        $('#execute-operation').on('click', function() {
            if (!validateOperation()) return;
            if (!confirm(rphubMultisite.strings.confirmBulkAction)) return;
            executeOperation();
        });
    }
    
    /**
     * Initialize network actions
     */
    function initializeNetworkActions() {
        $('#sync-all-sites').on('click', function() {
            syncAllSites();
        });
    }
    
    /**
     * Initialize analytics functionality
     */
    function initializeAnalytics() {
        $('#apply-filters').on('click', function() {
            loadNetworkAnalytics();
        });
        
        // Load initial analytics data
        loadNetworkAnalytics();
    }
    
    /**
     * Update selected sites array
     */
    function updateSelectedSites() {
        selectedSites = [];
        $('input[name="selected_sites[]"]:checked').each(function() {
            selectedSites.push($(this).val());
        });
    }
    
    /**
     * Update selected groups array
     */
    function updateSelectedGroups() {
        selectedGroups = [];
        $('input[name="selected_groups[]"]:checked').each(function() {
            selectedGroups.push($(this).val());
        });
    }
    
    /**
     * Update execute button state
     */
    function updateExecuteButton() {
        var hasTargets = selectedSites.length > 0 || selectedGroups.length > 0;
        var hasOperation = currentOperation !== '';
        
        $('#execute-operation').prop('disabled', !(hasTargets && hasOperation));
    }
    
    /**
     * Validate operation before execution
     */
    function validateOperation() {
        if (!currentOperation) {
            alert('Please select an operation.');
            return false;
        }
        
        if (selectedSites.length === 0 && selectedGroups.length === 0) {
            alert(rphubMultisite.strings.selectSites);
            return false;
        }
        
        return true;
    }
    
    /**
     * Execute operation
     */
    function executeOperation() {
        if (isExecuting) return;
        
        isExecuting = true;
        $('#execute-operation').prop('disabled', true).text(rphubMultisite.strings.actionInProgress);
        
        var targets = getOperationTargets();
        var config = getOperationConfig();
        
        $('#operation-results').show();
        $('#results-content').html('<div class="operation-progress"><p>Executing operation...</p><div class="progress-bar"><div class="progress-fill"></div></div></div>');
        
        $.ajax({
            url: rphubMultisite.ajaxUrl,
            type: 'POST',
            data: {
                action: 'rphub_multisite_bulk_action',
                nonce: rphubMultisite.nonce,
                action_type: currentOperation,
                site_ids: selectedSites,
                group_ids: selectedGroups,
                action_config: JSON.stringify(config)
            },
            success: function(response) {
                isExecuting = false;
                $('#execute-operation').prop('disabled', false).text('Execute Operation');
                
                if (response.success) {
                    displayExecutionResults(response.data);
                } else {
                    $('#results-content').html('<p class="error">' + rphubMultisite.strings.actionFailed + ': ' + response.data + '</p>');
                }
            },
            error: function() {
                isExecuting = false;
                $('#execute-operation').prop('disabled', false).text('Execute Operation');
                $('#results-content').html('<p class="error">' + rphubMultisite.strings.actionFailed + '</p>');
            }
        });
    }
    
    /**
     * Get operation targets
     */
    function getOperationTargets() {
        return {
            sites: selectedSites,
            groups: selectedGroups
        };
    }
    
    /**
     * Get operation configuration
     */
    function getOperationConfig() {
        var config = {};
        
        $('#operation-config input, #operation-config select').each(function() {
            var name = $(this).attr('name');
            if (name && name.startsWith('config[')) {
                var key = name.replace('config[', '').replace(']', '');
                
                if ($(this).is(':checkbox')) {
                    config[key] = $(this).is(':checked');
                } else {
                    config[key] = $(this).val();
                }
            }
        });
        
        return config;
    }
    
    /**
     * Display execution results
     */
    function displayExecutionResults(data) {
        var html = '<div class="execution-results">';
        html += '<h3>Operation Results</h3>';
        html += '<div class="results-summary">';
        html += '<p><strong>Total Sites:</strong> ' + data.summary.total + '</p>';
        html += '<p><strong>Successful:</strong> ' + data.summary.success + '</p>';
        html += '<p><strong>Failed:</strong> ' + data.summary.errors + '</p>';
        html += '</div>';
        
        if (data.results) {
            html += '<div class="detailed-results">';
            html += '<h4>Detailed Results</h4>';
            html += '<table class="widefat fixed striped">';
            html += '<thead><tr><th>Site</th><th>Status</th><th>Message</th></tr></thead>';
            html += '<tbody>';
            
            for (var siteId in data.results) {
                var result = data.results[siteId];
                var statusClass = result.success ? 'success' : 'error';
                html += '<tr>';
                html += '<td>Site ' + siteId + '</td>';
                html += '<td><span class="status-' + statusClass + '">' + (result.success ? 'Success' : 'Failed') + '</span></td>';
                html += '<td>' + result.message + '</td>';
                html += '</tr>';
            }
            
            html += '</tbody></table>';
            html += '</div>';
        }
        
        html += '</div>';
        $('#results-content').html(html);
    }
    
    /**
     * Sync all sites
     */
    function syncAllSites() {
        $.ajax({
            url: rphubMultisite.ajaxUrl,
            type: 'POST',
            data: {
                action: 'rphub_multisite_sync_sites',
                nonce: rphubMultisite.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Site synchronization completed successfully.');
                    location.reload();
                } else {
                    alert('Synchronization failed: ' + response.data);
                }
            }
        });
    }
    
    /**
     * Load network analytics
     */
    function loadNetworkAnalytics() {
        var dateRange = $('#date-range').val();
        var metrics = $('#metrics').val();
        var groupIds = $('input[name="selected_groups[]"]:checked').map(function() {
            return $(this).val();
        }).get();
        
        $.ajax({
            url: rphubMultisite.ajaxUrl,
            type: 'POST',
            data: {
                action: 'rphub_multisite_get_network_analytics',
                nonce: rphubMultisite.nonce,
                date_range: dateRange,
                metrics: metrics,
                group_ids: groupIds
            },
            success: function(response) {
                if (response.success) {
                    displayNetworkAnalytics(response.data);
                } else {
                    $('#network-summary-cards').html('<p class="error">Failed to load analytics: ' + response.data + '</p>');
                }
            }
        });
    }
    
    /**
     * Display network analytics
     */
    function displayNetworkAnalytics(data) {
        // Display summary cards
        var summaryHtml = '';
        if (data.network_summary) {
            var summary = data.network_summary;
            summaryHtml += '<div class="analytics-card"><h3>' + summary.total_pageviews.toLocaleString() + '</h3><p>Total Pageviews</p></div>';
            summaryHtml += '<div class="analytics-card"><h3>' + summary.total_sessions.toLocaleString() + '</h3><p>Total Sessions</p></div>';
            summaryHtml += '<div class="analytics-card"><h3>' + summary.total_users.toLocaleString() + '</h3><p>Total Users</p></div>';
            summaryHtml += '<div class="analytics-card"><h3>' + summary.average_bounce_rate.toFixed(2) + '%</h3><p>Avg Bounce Rate</p></div>';
        }
        $('#network-summary-cards').html(summaryHtml);
        
        // Display top performing sites
        if (data.network_summary && data.network_summary.top_performing_sites) {
            var topSitesHtml = '<table class="widefat fixed striped">';
            topSitesHtml += '<thead><tr><th>Site</th><th>Pageviews</th></tr></thead><tbody>';
            
            data.network_summary.top_performing_sites.forEach(function(site) {
                topSitesHtml += '<tr><td>' + site.site_name + '</td><td>' + site.pageviews.toLocaleString() + '</td></tr>';
            });
            
            topSitesHtml += '</tbody></table>';
            $('#top-sites-table').html(topSitesHtml);
        }
    }
    
    /**
     * Load sites for content sync
     */
    function loadSitesForSync() {
        $.ajax({
            url: rphubMultisite.ajaxUrl,
            type: 'POST',
            data: {
                action: 'rphub_get_sites_list',
                nonce: rphubMultisite.nonce
            },
            success: function(response) {
                if (response.success) {
                    var select = $('select[name="config[source_site_id]"]');
                    select.empty().append('<option value="">Select source site...</option>');
                    
                    response.data.forEach(function(site) {
                        select.append('<option value="' + site.id + '">' + site.site_name + '</option>');
                    });
                }
            }
        });
    }
});
