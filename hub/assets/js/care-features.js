/**
 * Replanta Care Features Page JavaScript
 * Handles connection setup and status checking
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        initCareFeatures();
    });

    function initCareFeatures() {
        // Handle Care connection
        $('#connect-care').on('click', handleCareConnection);
        
        // Check connection status periodically if connected
        if ($('.status-card.connected').length > 0) {
            setInterval(checkConnectionStatus, 30000); // Check every 30 seconds
        }
        
        // Handle plan upgrade tracking
        trackPlanClicks();
        
        // Initialize FAQ toggles
        initFAQToggles();
        
        // Handle connection form validation
        validateConnectionForm();
    }

    function handleCareConnection() {
        const $button = $('#connect-care');
        const $input = $('#care-connection-key');
        const connectionKey = $input.val().trim();
        
        if (!connectionKey) {
            showNotice('Please enter your connection key.', 'error');
            $input.focus();
            return;
        }
        
        if (connectionKey.length < 32) {
            showNotice('Connection key appears to be invalid. Please check and try again.', 'error');
            $input.focus();
            return;
        }
        
        // Show loading state
        $button.prop('disabled', true);
        $button.text('Connecting...');
        
        // Simulate connection process (in real implementation, this would be an AJAX call)
        setTimeout(function() {
            // For demo purposes, we'll simulate a successful connection
            if (connectionKey.toLowerCase().includes('demo') || connectionKey.length >= 32) {
                connectToCare(connectionKey);
            } else {
                showConnectionError();
            }
        }, 2000);
    }
    
    function connectToCare(connectionKey) {
        // In real implementation, this would make an AJAX call to validate and connect
        $.ajax({
            url: rpCareAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'rphub_connect_care',
                connection_key: connectionKey,
                nonce: rpCareAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('Successfully connected to Replanta Care! Reloading page...', 'success');
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    showConnectionError(response.data);
                }
            },
            error: function() {
                showConnectionError('Network error. Please try again.');
            },
            complete: function() {
                $('#connect-care').prop('disabled', false).text('Connect Site');
            }
        });
    }
    
    function showConnectionError(message) {
        const defaultMessage = 'Invalid connection key. Please check your key and try again.';
        showNotice(message || defaultMessage, 'error');
        $('#connect-care').prop('disabled', false).text('Connect Site');
    }
    
    function checkConnectionStatus() {
        $.ajax({
            url: rpCareAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'rphub_check_care_connection',
                nonce: rpCareAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateConnectionStatus(response.data);
                }
            }
        });
    }
    
    function updateConnectionStatus(status) {
        // Update connection indicators
        if (status.connected) {
            $('.status-card').removeClass('disconnected').addClass('connected');
            $('.status-details strong').text('Connected');
            $('.status-details span').text('Plan: ' + status.plan.charAt(0).toUpperCase() + status.plan.slice(1));
        } else {
            $('.status-card').removeClass('connected').addClass('disconnected');
            $('.status-details strong').text('Not Connected');
            $('.status-details span').text('Connect to unlock premium features');
        }
        
        // Update metrics if available
        if (status.metrics) {
            updateMetrics(status.metrics);
        }
    }
    
    function updateMetrics(metrics) {
        // Update security status
        if (metrics.security_status) {
            $('.metric-card:contains("Security Status") .metric-value')
                .text(metrics.security_status.status)
                .removeClass('success warning error')
                .addClass(metrics.security_status.class);
            $('.metric-card:contains("Security Status") p')
                .text('Last scan: ' + metrics.security_status.last_scan);
        }
        
        // Update backup status
        if (metrics.backup_status) {
            $('.metric-card:contains("Backups") .metric-value')
                .text(metrics.backup_status.frequency);
            $('.metric-card:contains("Backups") p')
                .text('Last backup: ' + metrics.backup_status.last_backup);
        }
        
        // Update performance metrics
        if (metrics.performance) {
            $('.metric-card:contains("Performance") p')
                .text('Load time: ' + metrics.performance.load_time);
        }
    }
    
    function trackPlanClicks() {
        // Track plan upgrade clicks for analytics
        $('.plan-action .button').on('click', function() {
            const planName = $(this).closest('.plan-card').find('h3').text();
            const isUpgrade = $(this).text().includes('Upgrade') || $(this).text().includes('Contact');
            
            // Send tracking event (in real implementation)
            if (typeof gtag !== 'undefined') {
                gtag('event', 'plan_click', {
                    'plan_name': planName,
                    'action_type': isUpgrade ? 'upgrade' : 'view',
                    'current_page': 'care_features'
                });
            }
            
            // Show loading indicator for external links
            if ($(this).attr('href') && $(this).attr('href').includes('care.replanta.com')) {
                $(this).append(' <span class="loading-spinner"></span>');
            }
        });
    }
    
    function initFAQToggles() {
        // Make FAQ items clickable to toggle expanded content
        $('.faq-item h4').css('cursor', 'pointer').on('click', function() {
            const $content = $(this).next('p');
            const $item = $(this).parent();
            
            if ($content.is(':visible')) {
                $content.slideUp(200);
                $item.removeClass('expanded');
            } else {
                // Close other open FAQs
                $('.faq-item.expanded p').slideUp(200);
                $('.faq-item').removeClass('expanded');
                
                // Open clicked FAQ
                $content.slideDown(200);
                $item.addClass('expanded');
            }
        });
        
        // Add visual indicators for expandable FAQs
        $('.faq-item h4').append(' <span class="faq-toggle">+</span>');
        
        // Style the toggle indicators
        $('.faq-toggle').css({
            'float': 'right',
            'font-weight': 'bold',
            'color': '#059669',
            'font-size': '18px'
        });
    }
    
    function validateConnectionForm() {
        const $input = $('#care-connection-key');
        const $button = $('#connect-care');
        
        $input.on('input', function() {
            const value = $(this).val().trim();
            
            if (value.length === 0) {
                $button.prop('disabled', true);
                $(this).removeClass('valid invalid');
            } else if (value.length < 32) {
                $button.prop('disabled', true);
                $(this).addClass('invalid').removeClass('valid');
            } else {
                $button.prop('disabled', false);
                $(this).addClass('valid').removeClass('invalid');
            }
        });
        
        // Add CSS for input validation states
        $('<style>')
            .text(`
                #care-connection-key.valid {
                    border-color: #10b981;
                    box-shadow: 0 0 0 1px #10b981;
                }
                #care-connection-key.invalid {
                    border-color: #ef4444;
                    box-shadow: 0 0 0 1px #ef4444;
                }
                .loading-spinner {
                    margin-left: 5px;
                    animation: spin 1s linear infinite;
                }
                @keyframes spin {
                    from { transform: rotate(0deg); }
                    to { transform: rotate(360deg); }
                }
                .faq-item.expanded .faq-toggle {
                    transform: rotate(45deg);
                }
                .faq-toggle {
                    transition: transform 0.2s ease;
                }
            `)
            .appendTo('head');
    }
    
    function showNotice(message, type) {
        // Remove existing notices
        $('.care-notice').remove();
        
        // Create notice element
        const $notice = $('<div class="care-notice notice notice-' + type + ' is-dismissible">')
            .html('<p>' + message + '</p>')
            .hide();
        
        // Insert notice at top of page
        $('.rphub-care-page').prepend($notice);
        
        // Show with animation
        $notice.slideDown(300);
        
        // Auto dismiss success notices
        if (type === 'success') {
            setTimeout(function() {
                $notice.slideUp(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
        
        // Handle manual dismiss
        $notice.find('.notice-dismiss').on('click', function() {
            $notice.slideUp(300, function() {
                $(this).remove();
            });
        });
    }
    
    // Add smooth scrolling for anchor links
    $('a[href^="#"]').on('click', function(e) {
        e.preventDefault();
        const target = $($(this).attr('href'));
        if (target.length) {
            $('html, body').animate({
                scrollTop: target.offset().top - 100
            }, 500);
        }
    });
    
    // Add loading states for external links
    $('a[href*="care.replanta.com"]').on('click', function() {
        $(this).append(' ');
    });

})(jQuery);
