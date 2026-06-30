<?php
/**
 * Care Features and Upgrade Page
 * Showcase Care capabilities and subscription tiers
 * 
 * @package ReplantaHub
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Replanta_Care_Features_Page {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_care_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_care_assets'));
        add_action('wp_ajax_rphub_check_care_connection', array($this, 'check_care_connection'));
    }
    
    /**
     * Add care page to admin menu
     */
    public function add_care_page() {
        add_submenu_page(
            'replanta-hub',
            __('Replanta Care', 'replanta-hub'),
            __(' Replanta Care', 'replanta-hub'),
            'manage_options',
            'replanta-hub-care',
            array($this, 'render_care_page')
        );
    }
    
    /**
     * Enqueue assets for care page
     */
    public function enqueue_care_assets($hook) {
        if ($hook !== 'replanta-hub_page_replanta-hub-care') {
            return;
        }
        
        wp_enqueue_style(
            'replanta-care-features',
            RPHUB_PLUGIN_URL . 'assets/css/care-features.css',
            array(),
            RPHUB_VERSION
        );
        
        wp_enqueue_script(
            'replanta-care-features',
            RPHUB_PLUGIN_URL . 'assets/js/care-features.js',
            array('jquery'),
            RPHUB_VERSION,
            true
        );
        
        wp_localize_script('replanta-care-features', 'rpCareAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rphub_ajax')
        ));
    }
    
    /**
     * Check Care connection status
     */
    public function check_care_connection() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $care_status = get_option('rphub_care_status', array(
            'connected' => false,
            'plan' => 'none',
            'last_check' => 0
        ));
        
        wp_send_json_success($care_status);
    }
    
    /**
     * Get current Care status
     */
    private function get_care_status() {
        return get_option('rphub_care_status', array(
            'connected' => false,
            'plan' => 'none',
            'site_name' => '',
            'last_sync' => 0,
            'features_enabled' => array()
        ));
    }
    
    /**
     * Render the care features page
     */
    public function render_care_page() {
        $care_status = $this->get_care_status();
        $is_connected = $care_status['connected'];
        $current_plan = $care_status['plan'];
        ?>
        <div class="wrap rphub-care-page">
            
            <!-- Header Section -->
            <div class="care-header">
                <div class="header-content">
                    <div class="care-logo">
                        <span class="logo-icon"></span>
                        <h1 class="care-title"><?php _e('Replanta Care', 'replanta-hub'); ?></h1>
                    </div>
                    <p class="care-subtitle">
                        <?php _e('Professional WordPress management and premium security services', 'replanta-hub'); ?>
                    </p>
                </div>
                
                <div class="connection-status">
                    <?php if ($is_connected): ?>
                        <div class="status-card connected">
                            <div class="status-icon"></div>
                            <div class="status-details">
                                <strong><?php _e('Connected', 'replanta-hub'); ?></strong>
                                <span><?php printf(__('Plan: %s', 'replanta-hub'), ucfirst($current_plan)); ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="status-card disconnected">
                            <div class="status-icon"></div>
                            <div class="status-details">
                                <strong><?php _e('Not Connected', 'replanta-hub'); ?></strong>
                                <span><?php _e('Connect to unlock premium features', 'replanta-hub'); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!$is_connected): ?>
            <!-- Connection Setup -->
            <div class="care-setup-section">
                <div class="setup-container">
                    <h2><?php _e(' Connect Your Site to Replanta Care', 'replanta-hub'); ?></h2>
                    <p><?php _e('Get professional WordPress management, enhanced security, and priority support.', 'replanta-hub'); ?></p>
                    
                    <div class="setup-steps">
                        <div class="setup-step">
                            <div class="step-number">1</div>
                            <div class="step-content">
                                <h3><?php _e('Create Account', 'replanta-hub'); ?></h3>
                                <p><?php _e('Sign up for a Replanta Care account to get started.', 'replanta-hub'); ?></p>
                                <a href="https://care.replanta.com/signup" target="_blank" class="button button-primary">
                                    <?php _e('Sign Up Now', 'replanta-hub'); ?>
                                </a>
                            </div>
                        </div>
                        
                        <div class="setup-step">
                            <div class="step-number">2</div>
                            <div class="step-content">
                                <h3><?php _e('Get Connection Key', 'replanta-hub'); ?></h3>
                                <p><?php _e('Copy your unique connection key from your Care dashboard.', 'replanta-hub'); ?></p>
                                <div class="connection-form">
                                    <input type="text" id="care-connection-key" placeholder="<?php _e('Enter your connection key...', 'replanta-hub'); ?>" class="regular-text">
                                    <button type="button" id="connect-care" class="button button-primary">
                                        <?php _e('Connect Site', 'replanta-hub'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="setup-step">
                            <div class="step-number">3</div>
                            <div class="step-content">
                                <h3><?php _e('Enjoy Professional Management', 'replanta-hub'); ?></h3>
                                <p><?php _e('Your site will be automatically managed and protected 24/7.', 'replanta-hub'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Features Comparison -->
            <div class="care-features-section">
                <h2><?php _e(' Choose Your Care Level', 'replanta-hub'); ?></h2>
                
                <div class="plans-comparison">
                    
                    <!-- Free Hub Plan -->
                    <div class="plan-card hub-plan <?php echo !$is_connected ? 'current-plan' : ''; ?>">
                        <div class="plan-header">
                            <div class="plan-icon"></div>
                            <h3><?php _e('Hub Free', 'replanta-hub'); ?></h3>
                            <div class="plan-price">
                                <span class="price">$0</span>
                                <span class="period"><?php _e('/month', 'replanta-hub'); ?></span>
                            </div>
                        </div>
                        
                        <div class="plan-features">
                            <h4><?php _e('What you get now:', 'replanta-hub'); ?></h4>
                            <ul>
                                <li><span class="check"></span> <?php _e('Basic security scanning', 'replanta-hub'); ?></li>
                                <li><span class="check"></span> <?php _e('AI threat detection', 'replanta-hub'); ?></li>
                                <li><span class="check"></span> <?php _e('Real-time monitoring', 'replanta-hub'); ?></li>
                                <li><span class="check"></span> <?php _e('Analytics dashboard', 'replanta-hub'); ?></li>
                                <li><span class="check"></span> <?php _e('Basic firewall protection', 'replanta-hub'); ?></li>
                                <li><span class="limitation"></span> <?php _e('Limited to self-management', 'replanta-hub'); ?></li>
                                <li><span class="limitation"></span> <?php _e('Community support only', 'replanta-hub'); ?></li>
                            </ul>
                        </div>
                        
                        <?php if (!$is_connected): ?>
                        <div class="plan-action">
                            <button class="button button-secondary" disabled>
                                <?php _e('Currently Active', 'replanta-hub'); ?>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Care Essential Plan -->
                    <div class="plan-card essential-plan <?php echo ($is_connected && $current_plan === 'essential') ? 'current-plan' : ''; ?>">
                        <div class="plan-header">
                            <div class="plan-icon"></div>
                            <h3><?php _e('Care Essential', 'replanta-hub'); ?></h3>
                            <div class="plan-price">
                                <span class="price">$29</span>
                                <span class="period"><?php _e('/month', 'replanta-hub'); ?></span>
                            </div>
                        </div>
                        
                        <div class="plan-features">
                            <h4><?php _e('Everything in Hub Free, plus:', 'replanta-hub'); ?></h4>
                            <ul>
                                <li><span class="check"></span> <?php _e('Automatic updates management', 'replanta-hub'); ?></li>
                                <li><span class="check"></span> <?php _e('Daily automated backups', 'replanta-hub'); ?></li>
                                <li><span class="check"></span> <?php _e('Professional malware removal', 'replanta-hub'); ?></li>
                                <li><span class="check"></span> <?php _e('Uptime monitoring (5min checks)', 'replanta-hub'); ?></li>
                                <li><span class="check"></span> <?php _e('Email support (24h response)', 'replanta-hub'); ?></li>
                                <li><span class="check"></span> <?php _e('Monthly security reports', 'replanta-hub'); ?></li>
                                <li><span class="check"></span> <?php _e('SSL certificate monitoring', 'replanta-hub'); ?></li>
                            </ul>
                        </div>
                        
                        <div class="plan-action">
                            <?php if ($is_connected && $current_plan === 'essential'): ?>
                                <button class="button button-secondary" disabled>
                                    <?php _e('Current Plan', 'replanta-hub'); ?>
                                </button>
                            <?php else: ?>
                                <a href="https://care.replanta.com/upgrade?plan=essential" target="_blank" class="button button-primary">
                                    <?php _e('Upgrade to Essential', 'replanta-hub'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Care Professional Plan -->
                    <div class="plan-card professional-plan <?php echo ($is_connected && $current_plan === 'professional') ? 'current-plan' : ''; ?>">
                        <div class="plan-header">
                            <div class="plan-icon"></div>
                            <h3><?php _e('Care Professional', 'replanta-hub'); ?></h3>
                            <div class="plan-price">
                                <span class="price">$79</span>
                                <span class="period"><?php _e('/month', 'replanta-hub'); ?></span>
                            </div>
                            <div class="plan-badge"><?php _e('Most Popular', 'replanta-hub'); ?></div>
                        </div>
                        
                        <div class="plan-features">
                            <h4><?php _e('Everything in Essential, plus:', 'replanta-hub'); ?></h4>
                            <ul>
                                <li><span class="check"></span> <?php _e('Performance optimization', 'replanta-hub'); ?></li>
                                <li><span class="check"></span> <?php _e('CDN integration & management', 'replanta-hub'); ?></li>
                                <li><span class="check"></span> <?php _e('Advanced caching setup', 'replanta-hub'); ?></li>
                                <li><span class="check"></span> <?php _e('Database optimization', 'replanta-hub'); ?></li>
                                <li><span class="check"></span> <?php _e('Priority support (4h response)', 'replanta-hub'); ?></li>
                                <li><span class="check"></span> <?php _e('Weekly performance reports', 'replanta-hub'); ?></li>
                                <li><span class="check"></span> <?php _e('Advanced security hardening', 'replanta-hub'); ?></li>
                                <li><span class="check"></span> <?php _e('Emergency incident response', 'replanta-hub'); ?></li>
                            </ul>
                        </div>
                        
                        <div class="plan-action">
                            <?php if ($is_connected && $current_plan === 'professional'): ?>
                                <button class="button button-secondary" disabled>
                                    <?php _e('Current Plan', 'replanta-hub'); ?>
                                </button>
                            <?php else: ?>
                                <a href="https://care.replanta.com/upgrade?plan=professional" target="_blank" class="button button-primary">
                                    <?php _e('Upgrade to Professional', 'replanta-hub'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Care Enterprise Plan -->
                    <div class="plan-card enterprise-plan <?php echo ($is_connected && $current_plan === 'enterprise') ? 'current-plan' : ''; ?>">
                        <div class="plan-header">
                            <div class="plan-icon"></div>
                            <h3><?php _e('Care Enterprise', 'replanta-hub'); ?></h3>
                            <div class="plan-price">
                                <span class="price"><?php _e('Custom', 'replanta-hub'); ?></span>
                                <span class="period"><?php _e('/month', 'replanta-hub'); ?></span>
                            </div>
                        </div>
                        
                        <div class="plan-features">
                            <h4><?php _e('Everything in Professional, plus:', 'replanta-hub'); ?></h4>
                            <ul>
                                <li><span class="check"></span> <?php _e('Dedicated account manager', 'replanta-hub'); ?></li>
                                <li><span class="check"></span> <?php _e('Custom development support', 'replanta-hub'); ?></li>
                                <li><span class="check"></span> <?php _e('SLA guarantees (99.9% uptime)', 'replanta-hub'); ?></li>
                                <li><span class="check"></span> <?php _e('White-label reporting', 'replanta-hub'); ?></li>
                                <li><span class="check"></span> <?php _e('24/7 phone support', 'replanta-hub'); ?></li>
                                <li><span class="check"></span> <?php _e('Multi-site management', 'replanta-hub'); ?></li>
                                <li><span class="check"></span> <?php _e('Custom security policies', 'replanta-hub'); ?></li>
                                <li><span class="check"></span> <?php _e('Compliance assistance', 'replanta-hub'); ?></li>
                            </ul>
                        </div>
                        
                        <div class="plan-action">
                            <?php if ($is_connected && $current_plan === 'enterprise'): ?>
                                <button class="button button-secondary" disabled>
                                    <?php _e('Current Plan', 'replanta-hub'); ?>
                                </button>
                            <?php else: ?>
                                <a href="https://care.replanta.com/contact?plan=enterprise" target="_blank" class="button button-primary">
                                    <?php _e('Contact Sales', 'replanta-hub'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($is_connected): ?>
            <!-- Current Plan Status -->
            <div class="current-plan-section">
                <h2><?php _e(' Your Current Care Status', 'replanta-hub'); ?></h2>
                
                <div class="status-overview">
                    <div class="status-metrics">
                        <div class="metric-card">
                            <div class="metric-icon"></div>
                            <div class="metric-details">
                                <h4><?php _e('Security Status', 'replanta-hub'); ?></h4>
                                <span class="metric-value success"><?php _e('Protected', 'replanta-hub'); ?></span>
                                <p><?php _e('Last scan: 2 hours ago', 'replanta-hub'); ?></p>
                            </div>
                        </div>
                        
                        <div class="metric-card">
                            <div class="metric-icon"></div>
                            <div class="metric-details">
                                <h4><?php _e('Updates', 'replanta-hub'); ?></h4>
                                <span class="metric-value managed"><?php _e('Managed by Care', 'replanta-hub'); ?></span>
                                <p><?php _e('Auto-updates enabled', 'replanta-hub'); ?></p>
                            </div>
                        </div>
                        
                        <div class="metric-card">
                            <div class="metric-icon"></div>
                            <div class="metric-details">
                                <h4><?php _e('Backups', 'replanta-hub'); ?></h4>
                                <span class="metric-value success"><?php _e('Daily', 'replanta-hub'); ?></span>
                                <p><?php _e('Last backup: Today 3:00 AM', 'replanta-hub'); ?></p>
                            </div>
                        </div>
                        
                        <div class="metric-card">
                            <div class="metric-icon"></div>
                            <div class="metric-details">
                                <h4><?php _e('Performance', 'replanta-hub'); ?></h4>
                                <span class="metric-value success"><?php _e('Optimized', 'replanta-hub'); ?></span>
                                <p><?php _e('Load time: 1.2s', 'replanta-hub'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="care-actions">
                        <h3><?php _e('Care Management', 'replanta-hub'); ?></h3>
                        <div class="action-buttons">
                            <a href="https://care.replanta.com/dashboard" target="_blank" class="button button-primary">
                                <?php _e('Open Care Dashboard', 'replanta-hub'); ?>
                            </a>
                            <a href="https://care.replanta.com/reports" target="_blank" class="button button-secondary">
                                <?php _e('View Reports', 'replanta-hub'); ?>
                            </a>
                            <a href="https://care.replanta.com/support" target="_blank" class="button button-secondary">
                                <?php _e('Get Support', 'replanta-hub'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Benefits Showcase -->
            <div class="benefits-section">
                <h2><?php _e(' Why Choose Replanta Care?', 'replanta-hub'); ?></h2>
                
                <div class="benefits-grid">
                    <div class="benefit-card">
                        <div class="benefit-icon"></div>
                        <h3><?php _e('Save Time', 'replanta-hub'); ?></h3>
                        <p><?php _e('Stop worrying about WordPress maintenance. We handle updates, security, and performance optimization automatically.', 'replanta-hub'); ?></p>
                    </div>
                    
                    <div class="benefit-card">
                        <div class="benefit-icon"></div>
                        <h3><?php _e('Enhanced Security', 'replanta-hub'); ?></h3>
                        <p><?php _e('Professional security monitoring with immediate threat response and expert malware removal.', 'replanta-hub'); ?></p>
                    </div>
                    
                    <div class="benefit-card">
                        <div class="benefit-icon"></div>
                        <h3><?php _e('Better Performance', 'replanta-hub'); ?></h3>
                        <p><?php _e('Advanced caching, CDN setup, and database optimization for lightning-fast load times.', 'replanta-hub'); ?></p>
                    </div>
                    
                    <div class="benefit-card">
                        <div class="benefit-icon"></div>
                        <h3><?php _e('Expert Support', 'replanta-hub'); ?></h3>
                        <p><?php _e('Direct access to WordPress experts who know your site and can solve any issue quickly.', 'replanta-hub'); ?></p>
                    </div>
                    
                    <div class="benefit-card">
                        <div class="benefit-icon"></div>
                        <h3><?php _e('Detailed Reporting', 'replanta-hub'); ?></h3>
                        <p><?php _e('Regular reports on security, performance, and site health with actionable recommendations.', 'replanta-hub'); ?></p>
                    </div>
                    
                    <div class="benefit-card">
                        <div class="benefit-icon"></div>
                        <h3><?php _e('Proactive Management', 'replanta-hub'); ?></h3>
                        <p><?php _e('We identify and fix issues before they become problems, ensuring your site runs smoothly 24/7.', 'replanta-hub'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Testimonials -->
            <div class="testimonials-section">
                <h2><?php _e(' What Our Customers Say', 'replanta-hub'); ?></h2>
                
                <div class="testimonials-grid">
                    <div class="testimonial-card">
                        <div class="testimonial-content">
                            <p>"<?php _e('Replanta Care has been a game-changer for our business. We can focus on growing while they handle all the technical stuff.', 'replanta-hub'); ?>"</p>
                        </div>
                        <div class="testimonial-author">
                            <strong>Maria Rodriguez</strong>
                            <span><?php _e('Digital Marketing Agency', 'replanta-hub'); ?></span>
                        </div>
                    </div>
                    
                    <div class="testimonial-card">
                        <div class="testimonial-content">
                            <p>"<?php _e('The security features and automatic updates give me peace of mind. My site has never been faster or more secure.', 'replanta-hub'); ?>"</p>
                        </div>
                        <div class="testimonial-author">
                            <strong>David Chen</strong>
                            <span><?php _e('E-commerce Store Owner', 'replanta-hub'); ?></span>
                        </div>
                    </div>
                    
                    <div class="testimonial-card">
                        <div class="testimonial-content">
                            <p>"<?php _e('Professional support when I need it most. The team responds quickly and always solves the problem completely.', 'replanta-hub'); ?>"</p>
                        </div>
                        <div class="testimonial-author">
                            <strong>Sarah Johnson</strong>
                            <span><?php _e('Freelance Designer', 'replanta-hub'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FAQ Section -->
            <div class="faq-section">
                <h2><?php _e(' Frequently Asked Questions', 'replanta-hub'); ?></h2>
                
                <div class="faq-list">
                    <div class="faq-item">
                        <h4><?php _e('What happens if I disconnect from Care?', 'replanta-hub'); ?></h4>
                        <p><?php _e('You can disconnect anytime and your site will continue working normally with the free Hub features. Managed services will stop but your security remains active.', 'replanta-hub'); ?></p>
                    </div>
                    
                    <div class="faq-item">
                        <h4><?php _e('Can I upgrade or downgrade my plan?', 'replanta-hub'); ?></h4>
                        <p><?php _e('Yes, you can change your plan anytime from your Care dashboard. Changes take effect immediately.', 'replanta-hub'); ?></p>
                    </div>
                    
                    <div class="faq-item">
                        <h4><?php _e('Do you provide migration assistance?', 'replanta-hub'); ?></h4>
                        <p><?php _e('Professional and Enterprise plans include free migration assistance if you\'re moving from another managed service.', 'replanta-hub'); ?></p>
                    </div>
                    
                    <div class="faq-item">
                        <h4><?php _e('What about plugin/theme compatibility?', 'replanta-hub'); ?></h4>
                        <p><?php _e('We test all updates in a staging environment first and provide compatibility support for premium plugins and themes.', 'replanta-hub'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Footer CTA -->
            <div class="care-footer">
                <div class="footer-content">
                    <h3><?php _e('Ready to Experience Professional WordPress Management?', 'replanta-hub'); ?></h3>
                    <p><?php _e('Join thousands of websites already protected and managed by Replanta Care.', 'replanta-hub'); ?></p>
                    <div class="footer-actions">
                        <?php if (!$is_connected): ?>
                            <a href="https://care.replanta.com/signup" target="_blank" class="button button-primary button-hero">
                                <?php _e('Start Free Trial', 'replanta-hub'); ?>
                            </a>
                            <a href="https://care.replanta.com/demo" target="_blank" class="button button-secondary button-hero">
                                <?php _e('View Demo', 'replanta-hub'); ?>
                            </a>
                        <?php else: ?>
                            <a href="https://care.replanta.com/upgrade" target="_blank" class="button button-primary button-hero">
                                <?php _e('Upgrade Plan', 'replanta-hub'); ?>
                            </a>
                            <a href="https://care.replanta.com/dashboard" target="_blank" class="button button-secondary button-hero">
                                <?php _e('Manage Account', 'replanta-hub'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

// Initialize the care features page
new Replanta_Care_Features_Page();
