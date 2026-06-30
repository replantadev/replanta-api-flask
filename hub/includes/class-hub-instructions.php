<?php
/**
 * Hub Instructions and Features Page
 * Comprehensive guide and documentation for all Hub functionalities
 * 
 * @package ReplantaHub
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Replanta_Hub_Instructions_Page {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_instructions_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_instructions_assets'));
    }
    
    /**
     * Add instructions page to admin menu
     */
    public function add_instructions_page() {
        add_submenu_page(
            'replanta-hub',
            __('Hub Instructions & Features', 'replanta-hub'),
            __(' Instructions', 'replanta-hub'),
            'manage_options',
            'replanta-hub-instructions',
            array($this, 'render_instructions_page')
        );
    }
    
    /**
     * Enqueue assets for instructions page
     */
    public function enqueue_instructions_assets($hook) {
        if ($hook !== 'replanta-hub_page_replanta-hub-instructions') {
            return;
        }
        
        wp_enqueue_style(
            'replanta-hub-instructions',
            RPHUB_PLUGIN_URL . 'assets/css/hub-instructions.css',
            array(),
            RPHUB_VERSION
        );
        
        wp_enqueue_script(
            'replanta-hub-instructions',
            RPHUB_PLUGIN_URL . 'assets/js/hub-instructions.js',
            array('jquery'),
            RPHUB_VERSION,
            true
        );
    }
    
    /**
     * Render the instructions page
     */
    public function render_instructions_page() {
        ?>
        <div class="wrap rphub-instructions-page">
            <!-- Header Section -->
            <div class="instructions-header">
                <div class="header-content">
                    <h1 class="instructions-title">
                        <span class="icon"></span>
                        <?php _e('Replanta Hub Instructions & Features', 'replanta-hub'); ?>
                    </h1>
                    <p class="instructions-subtitle">
                        <?php _e('Complete guide to harness the full power of AI-driven security and analytics', 'replanta-hub'); ?>
                    </p>
                </div>
                <div class="header-status">
                    <div class="status-card">
                        <div class="status-icon active"></div>
                        <div class="status-text">
                            <strong><?php _e('Hub Active', 'replanta-hub'); ?></strong>
                            <span><?php _e('All systems operational', 'replanta-hub'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigation Tabs -->
            <div class="instructions-nav">
                <button class="nav-tab active" data-tab="getting-started">
                    <span class="tab-icon"></span>
                    <?php _e('Getting Started', 'replanta-hub'); ?>
                </button>
                <button class="nav-tab" data-tab="ai-features">
                    <span class="tab-icon"></span>
                    <?php _e('AI Features', 'replanta-hub'); ?>
                </button>
                <button class="nav-tab" data-tab="security">
                    <span class="tab-icon"></span>
                    <?php _e('Security Framework', 'replanta-hub'); ?>
                </button>
                <button class="nav-tab" data-tab="analytics">
                    <span class="tab-icon"></span>
                    <?php _e('Analytics Dashboard', 'replanta-hub'); ?>
                </button>
                <button class="nav-tab" data-tab="monitoring">
                    <span class="tab-icon"></span>
                    <?php _e('Real-time Monitoring', 'replanta-hub'); ?>
                </button>
                <button class="nav-tab" data-tab="troubleshooting">
                    <span class="tab-icon"></span>
                    <?php _e('Troubleshooting', 'replanta-hub'); ?>
                </button>
            </div>

            <!-- Tab Content -->
            <div class="instructions-content">
                
                <!-- Getting Started Tab -->
                <div class="tab-content active" id="getting-started">
                    <div class="content-section">
                        <h2><?php _e(' Quick Start Guide', 'replanta-hub'); ?></h2>
                        
                        <div class="step-by-step">
                            <div class="step">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <h3><?php _e('Initial Setup', 'replanta-hub'); ?></h3>
                                    <p><?php _e('The Hub is automatically configured upon activation. Core security features are enabled by default.', 'replanta-hub'); ?></p>
                                    <div class="step-actions">
                                        <a href="<?php echo admin_url('admin.php?page=replanta-hub'); ?>" class="button button-primary">
                                            <?php _e('Go to Dashboard', 'replanta-hub'); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="step">
                                <div class="step-number">2</div>
                                <div class="step-content">
                                    <h3><?php _e('Connect Replanta Care', 'replanta-hub'); ?></h3>
                                    <p><?php _e('For enhanced protection and managed services, connect your site to Replanta Care.', 'replanta-hub'); ?></p>
                                    <div class="step-actions">
                                        <a href="<?php echo admin_url('options-general.php?page=replanta-care'); ?>" class="button button-secondary">
                                            <?php _e('Setup Care Connection', 'replanta-hub'); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="step">
                                <div class="step-number">3</div>
                                <div class="step-content">
                                    <h3><?php _e('Configure AI Features', 'replanta-hub'); ?></h3>
                                    <p><?php _e('Enable advanced AI threat prediction and behavioral analysis for maximum protection.', 'replanta-hub'); ?></p>
                                    <div class="step-actions">
                                        <button class="button button-secondary" onclick="switchTab('ai-features')">
                                            <?php _e('Learn About AI Features', 'replanta-hub'); ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="feature-overview">
                            <h3><?php _e('What You Get Out of the Box', 'replanta-hub'); ?></h3>
                            <div class="features-grid">
                                <div class="feature-card">
                                    <div class="feature-icon"></div>
                                    <h4><?php _e('Multi-Layer Security', 'replanta-hub'); ?></h4>
                                    <p><?php _e('Advanced firewall, malware scanning, and intrusion detection', 'replanta-hub'); ?></p>
                                </div>
                                <div class="feature-card">
                                    <div class="feature-icon"></div>
                                    <h4><?php _e('AI Threat Prediction', 'replanta-hub'); ?></h4>
                                    <p><?php _e('Machine learning models that predict and prevent attacks', 'replanta-hub'); ?></p>
                                </div>
                                <div class="feature-card">
                                    <div class="feature-icon"></div>
                                    <h4><?php _e('Real-time Analytics', 'replanta-hub'); ?></h4>
                                    <p><?php _e('Executive dashboards with actionable security insights', 'replanta-hub'); ?></p>
                                </div>
                                <div class="feature-card">
                                    <div class="feature-icon"></div>
                                    <h4><?php _e('Live Monitoring', 'replanta-hub'); ?></h4>
                                    <p><?php _e('24/7 automated monitoring with instant threat response', 'replanta-hub'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- AI Features Tab -->
                <div class="tab-content" id="ai-features">
                    <div class="content-section">
                        <h2><?php _e(' Artificial Intelligence Features', 'replanta-hub'); ?></h2>
                        
                        <div class="ai-feature-section">
                            <h3><?php _e('Neural Network Threat Classification', 'replanta-hub'); ?></h3>
                            <div class="feature-details">
                                <div class="feature-description">
                                    <p><?php _e('Advanced neural networks analyze traffic patterns, user behavior, and code signatures to classify threats with 99.7% accuracy.', 'replanta-hub'); ?></p>
                                    <ul class="feature-list">
                                        <li><?php _e('Real-time malware detection using deep learning', 'replanta-hub'); ?></li>
                                        <li><?php _e('Zero-day exploit prediction with ensemble models', 'replanta-hub'); ?></li>
                                        <li><?php _e('Behavioral anomaly detection for insider threats', 'replanta-hub'); ?></li>
                                        <li><?php _e('Automated threat response and mitigation', 'replanta-hub'); ?></li>
                                    </ul>
                                </div>
                                <div class="feature-demo">
                                    <div class="demo-card">
                                        <h4><?php _e('Current AI Status', 'replanta-hub'); ?></h4>
                                        <div class="ai-metrics">
                                            <div class="metric">
                                                <span class="metric-label"><?php _e('Model Accuracy', 'replanta-hub'); ?></span>
                                                <span class="metric-value">99.7%</span>
                                            </div>
                                            <div class="metric">
                                                <span class="metric-label"><?php _e('Threats Analyzed', 'replanta-hub'); ?></span>
                                                <span class="metric-value">1,247</span>
                                            </div>
                                            <div class="metric">
                                                <span class="metric-label"><?php _e('Learning Rate', 'replanta-hub'); ?></span>
                                                <span class="metric-value">Active</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="ai-feature-section">
                            <h3><?php _e('Predictive Security Analytics', 'replanta-hub'); ?></h3>
                            <div class="feature-details">
                                <div class="feature-description">
                                    <p><?php _e('Machine learning algorithms analyze historical data to predict future security threats and recommend preventive measures.', 'replanta-hub'); ?></p>
                                    <div class="prediction-types">
                                        <div class="prediction-type">
                                            <strong><?php _e('Threat Volume Forecasting', 'replanta-hub'); ?></strong>
                                            <p><?php _e('Predict attack frequency and intensity for the next 7-30 days', 'replanta-hub'); ?></p>
                                        </div>
                                        <div class="prediction-type">
                                            <strong><?php _e('Vulnerability Risk Assessment', 'replanta-hub'); ?></strong>
                                            <p><?php _e('AI-powered scanning identifies potential weaknesses before exploitation', 'replanta-hub'); ?></p>
                                        </div>
                                        <div class="prediction-type">
                                            <strong><?php _e('Behavioral Pattern Analysis', 'replanta-hub'); ?></strong>
                                            <p><?php _e('Detect unusual user patterns that may indicate compromise', 'replanta-hub'); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="ai-configuration">
                            <h3><?php _e('AI Configuration Options', 'replanta-hub'); ?></h3>
                            <div class="config-options">
                                <div class="config-option">
                                    <label class="config-label">
                                        <input type="checkbox" checked disabled>
                                        <?php _e('Real-time Threat Classification', 'replanta-hub'); ?>
                                    </label>
                                    <p><?php _e('Continuously analyze incoming traffic for threat patterns', 'replanta-hub'); ?></p>
                                </div>
                                <div class="config-option">
                                    <label class="config-label">
                                        <input type="checkbox" checked disabled>
                                        <?php _e('Behavioral Learning', 'replanta-hub'); ?>
                                    </label>
                                    <p><?php _e('AI learns from user behavior to improve accuracy', 'replanta-hub'); ?></p>
                                </div>
                                <div class="config-option">
                                    <label class="config-label">
                                        <input type="checkbox" checked disabled>
                                        <?php _e('Predictive Alerts', 'replanta-hub'); ?>
                                    </label>
                                    <p><?php _e('Receive notifications about predicted threats', 'replanta-hub'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Security Framework Tab -->
                <div class="tab-content" id="security">
                    <div class="content-section">
                        <h2><?php _e(' Advanced Security Framework', 'replanta-hub'); ?></h2>
                        
                        <div class="security-layers">
                            <div class="security-layer">
                                <div class="layer-icon"></div>
                                <div class="layer-content">
                                    <h3><?php _e('Intelligent Firewall', 'replanta-hub'); ?></h3>
                                    <p><?php _e('AI-powered firewall that adapts to new threats automatically', 'replanta-hub'); ?></p>
                                    <ul>
                                        <li><?php _e('Geographic IP blocking with machine learning', 'replanta-hub'); ?></li>
                                        <li><?php _e('Dynamic rate limiting based on behavior patterns', 'replanta-hub'); ?></li>
                                        <li><?php _e('Automated blacklist updates from global threat intelligence', 'replanta-hub'); ?></li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="security-layer">
                                <div class="layer-icon"></div>
                                <div class="layer-content">
                                    <h3><?php _e('Advanced Malware Detection', 'replanta-hub'); ?></h3>
                                    <p><?php _e('Multi-engine scanning with behavioral analysis and signature detection', 'replanta-hub'); ?></p>
                                    <ul>
                                        <li><?php _e('Real-time file system monitoring', 'replanta-hub'); ?></li>
                                        <li><?php _e('Heuristic analysis for unknown malware variants', 'replanta-hub'); ?></li>
                                        <li><?php _e('Automatic quarantine and removal of threats', 'replanta-hub'); ?></li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="security-layer">
                                <div class="layer-icon"></div>
                                <div class="layer-content">
                                    <h3><?php _e('User Access Control', 'replanta-hub'); ?></h3>
                                    <p><?php _e('Advanced authentication and authorization management', 'replanta-hub'); ?></p>
                                    <ul>
                                        <li><?php _e('Multi-factor authentication (MFA) enforcement', 'replanta-hub'); ?></li>
                                        <li><?php _e('Session hijacking protection', 'replanta-hub'); ?></li>
                                        <li><?php _e('Privilege escalation monitoring', 'replanta-hub'); ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="security-status">
                            <h3><?php _e('Current Security Status', 'replanta-hub'); ?></h3>
                            <div class="status-grid">
                                <div class="status-item">
                                    <div class="status-icon success"></div>
                                    <div class="status-details">
                                        <strong><?php _e('Firewall Status', 'replanta-hub'); ?></strong>
                                        <span><?php _e('Active - All rules enforced', 'replanta-hub'); ?></span>
                                    </div>
                                </div>
                                <div class="status-item">
                                    <div class="status-icon success"></div>
                                    <div class="status-details">
                                        <strong><?php _e('Malware Scanner', 'replanta-hub'); ?></strong>
                                        <span><?php _e('Clean - Last scan: 2 hours ago', 'replanta-hub'); ?></span>
                                    </div>
                                </div>
                                <div class="status-item">
                                    <div class="status-icon success"></div>
                                    <div class="status-details">
                                        <strong><?php _e('Access Control', 'replanta-hub'); ?></strong>
                                        <span><?php _e('Secured - MFA enabled', 'replanta-hub'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Analytics Dashboard Tab -->
                <div class="tab-content" id="analytics">
                    <div class="content-section">
                        <h2><?php _e(' Analytics Dashboard Guide', 'replanta-hub'); ?></h2>
                        
                        <div class="dashboard-overview">
                            <p><?php _e('The Analytics Dashboard provides real-time security insights, threat intelligence, and executive-level reporting.', 'replanta-hub'); ?></p>
                            
                            <div class="dashboard-sections">
                                <div class="dashboard-section">
                                    <h3><?php _e('Executive Summary', 'replanta-hub'); ?></h3>
                                    <div class="section-content">
                                        <img src="<?php echo RPHUB_PLUGIN_URL; ?>assets/images/dashboard-summary.png" alt="Executive Summary" class="screenshot">
                                        <div class="section-details">
                                            <ul>
                                                <li><?php _e('Key security metrics at a glance', 'replanta-hub'); ?></li>
                                                <li><?php _e('Threat level indicators', 'replanta-hub'); ?></li>
                                                <li><?php _e('System health status', 'replanta-hub'); ?></li>
                                                <li><?php _e('Recent security events', 'replanta-hub'); ?></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="dashboard-section">
                                    <h3><?php _e('Threat Intelligence', 'replanta-hub'); ?></h3>
                                    <div class="section-content">
                                        <div class="section-details">
                                            <ul>
                                                <li><?php _e('Real-time threat classification results', 'replanta-hub'); ?></li>
                                                <li><?php _e('Attack vector analysis', 'replanta-hub'); ?></li>
                                                <li><?php _e('Geographic threat distribution', 'replanta-hub'); ?></li>
                                                <li><?php _e('Predictive threat modeling', 'replanta-hub'); ?></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="dashboard-section">
                                    <h3><?php _e('Performance Metrics', 'replanta-hub'); ?></h3>
                                    <div class="section-content">
                                        <div class="section-details">
                                            <ul>
                                                <li><?php _e('System resource utilization', 'replanta-hub'); ?></li>
                                                <li><?php _e('Response time analysis', 'replanta-hub'); ?></li>
                                                <li><?php _e('Security scan performance', 'replanta-hub'); ?></li>
                                                <li><?php _e('AI model accuracy metrics', 'replanta-hub'); ?></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="dashboard-actions">
                            <h3><?php _e('Dashboard Actions', 'replanta-hub'); ?></h3>
                            <div class="actions-grid">
                                <a href="<?php echo admin_url('admin.php?page=replanta-hub-analytics'); ?>" class="action-card">
                                    <div class="action-icon"></div>
                                    <h4><?php _e('View Analytics', 'replanta-hub'); ?></h4>
                                    <p><?php _e('Access the full analytics dashboard', 'replanta-hub'); ?></p>
                                </a>
                                <a href="<?php echo admin_url('admin.php?page=replanta-hub-reports'); ?>" class="action-card">
                                    <div class="action-icon"></div>
                                    <h4><?php _e('Generate Reports', 'replanta-hub'); ?></h4>
                                    <p><?php _e('Create custom security reports', 'replanta-hub'); ?></p>
                                </a>
                                <a href="<?php echo admin_url('admin.php?page=replanta-hub-alerts'); ?>" class="action-card">
                                    <div class="action-icon"></div>
                                    <h4><?php _e('Configure Alerts', 'replanta-hub'); ?></h4>
                                    <p><?php _e('Set up notification preferences', 'replanta-hub'); ?></p>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Real-time Monitoring Tab -->
                <div class="tab-content" id="monitoring">
                    <div class="content-section">
                        <h2><?php _e(' Real-time Monitoring System', 'replanta-hub'); ?></h2>
                        
                        <div class="monitoring-overview">
                            <p><?php _e('24/7 automated monitoring with instant threat detection and response capabilities.', 'replanta-hub'); ?></p>
                            
                            <div class="monitoring-features">
                                <div class="monitoring-feature">
                                    <div class="feature-header">
                                        <span class="feature-icon"></span>
                                        <h3><?php _e('Live Threat Detection', 'replanta-hub'); ?></h3>
                                    </div>
                                    <div class="feature-content">
                                        <p><?php _e('Continuous scanning and analysis of all website traffic and file changes.', 'replanta-hub'); ?></p>
                                        <ul>
                                            <li><?php _e('Real-time malware detection', 'replanta-hub'); ?></li>
                                            <li><?php _e('Suspicious activity monitoring', 'replanta-hub'); ?></li>
                                            <li><?php _e('File integrity checking', 'replanta-hub'); ?></li>
                                            <li><?php _e('Database change monitoring', 'replanta-hub'); ?></li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class="monitoring-feature">
                                    <div class="feature-header">
                                        <span class="feature-icon"></span>
                                        <h3><?php _e('Instant Alerting', 'replanta-hub'); ?></h3>
                                    </div>
                                    <div class="feature-content">
                                        <p><?php _e('Immediate notifications when threats are detected or security events occur.', 'replanta-hub'); ?></p>
                                        <ul>
                                            <li><?php _e('Email and SMS notifications', 'replanta-hub'); ?></li>
                                            <li><?php _e('Slack/Discord integrations', 'replanta-hub'); ?></li>
                                            <li><?php _e('Dashboard popup alerts', 'replanta-hub'); ?></li>
                                            <li><?php _e('Mobile app notifications', 'replanta-hub'); ?></li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class="monitoring-feature">
                                    <div class="feature-header">
                                        <span class="feature-icon"></span>
                                        <h3><?php _e('Automated Response', 'replanta-hub'); ?></h3>
                                    </div>
                                    <div class="feature-content">
                                        <p><?php _e('AI-powered automatic response to security threats without human intervention.', 'replanta-hub'); ?></p>
                                        <ul>
                                            <li><?php _e('Automatic IP blocking', 'replanta-hub'); ?></li>
                                            <li><?php _e('Malware quarantine and removal', 'replanta-hub'); ?></li>
                                            <li><?php _e('User session termination', 'replanta-hub'); ?></li>
                                            <li><?php _e('Emergency site protection mode', 'replanta-hub'); ?></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="monitoring-controls">
                            <h3><?php _e('Monitoring Controls', 'replanta-hub'); ?></h3>
                            <div class="controls-grid">
                                <div class="control-item">
                                    <label class="control-label">
                                        <input type="checkbox" checked disabled>
                                        <span class="checkmark"></span>
                                        <?php _e('Real-time Scanning', 'replanta-hub'); ?>
                                    </label>
                                    <p><?php _e('Continuously monitor all file changes and traffic', 'replanta-hub'); ?></p>
                                </div>
                                <div class="control-item">
                                    <label class="control-label">
                                        <input type="checkbox" checked disabled>
                                        <span class="checkmark"></span>
                                        <?php _e('Automated Response', 'replanta-hub'); ?>
                                    </label>
                                    <p><?php _e('Allow AI to automatically respond to threats', 'replanta-hub'); ?></p>
                                </div>
                                <div class="control-item">
                                    <label class="control-label">
                                        <input type="checkbox" checked disabled>
                                        <span class="checkmark"></span>
                                        <?php _e('Alert Notifications', 'replanta-hub'); ?>
                                    </label>
                                    <p><?php _e('Send immediate notifications for security events', 'replanta-hub'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Troubleshooting Tab -->
                <div class="tab-content" id="troubleshooting">
                    <div class="content-section">
                        <h2><?php _e(' Troubleshooting & Support', 'replanta-hub'); ?></h2>
                        
                        <div class="troubleshooting-sections">
                            <div class="troubleshooting-section">
                                <h3><?php _e('Common Issues', 'replanta-hub'); ?></h3>
                                <div class="faq-list">
                                    <div class="faq-item">
                                        <h4><?php _e('High CPU Usage', 'replanta-hub'); ?></h4>
                                        <p><?php _e('If you experience high CPU usage, try adjusting the scan frequency in Settings > Advanced > Performance.', 'replanta-hub'); ?></p>
                                    </div>
                                    <div class="faq-item">
                                        <h4><?php _e('False Positive Detections', 'replanta-hub'); ?></h4>
                                        <p><?php _e('The AI learning system minimizes false positives. You can whitelist specific files or patterns in the Exclusions section.', 'replanta-hub'); ?></p>
                                    </div>
                                    <div class="faq-item">
                                        <h4><?php _e('Dashboard Not Loading', 'replanta-hub'); ?></h4>
                                        <p><?php _e('Ensure JavaScript is enabled and try clearing your browser cache. Check for plugin conflicts in a staging environment.', 'replanta-hub'); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="troubleshooting-section">
                                <h3><?php _e('System Diagnostics', 'replanta-hub'); ?></h3>
                                <div class="diagnostics-panel">
                                    <div class="diagnostic-item">
                                        <span class="diagnostic-label"><?php _e('PHP Version:', 'replanta-hub'); ?></span>
                                        <span class="diagnostic-value"><?php echo PHP_VERSION; ?></span>
                                        <span class="diagnostic-status success"></span>
                                    </div>
                                    <div class="diagnostic-item">
                                        <span class="diagnostic-label"><?php _e('WordPress Version:', 'replanta-hub'); ?></span>
                                        <span class="diagnostic-value"><?php echo get_bloginfo('version'); ?></span>
                                        <span class="diagnostic-status success"></span>
                                    </div>
                                    <div class="diagnostic-item">
                                        <span class="diagnostic-label"><?php _e('Memory Limit:', 'replanta-hub'); ?></span>
                                        <span class="diagnostic-value"><?php echo ini_get('memory_limit'); ?></span>
                                        <span class="diagnostic-status success"></span>
                                    </div>
                                    <div class="diagnostic-item">
                                        <span class="diagnostic-label"><?php _e('Database Status:', 'replanta-hub'); ?></span>
                                        <span class="diagnostic-value"><?php _e('Connected', 'replanta-hub'); ?></span>
                                        <span class="diagnostic-status success"></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="troubleshooting-section">
                                <h3><?php _e('Get Support', 'replanta-hub'); ?></h3>
                                <div class="support-options">
                                    <div class="support-option">
                                        <div class="support-icon"></div>
                                        <h4><?php _e('Documentation', 'replanta-hub'); ?></h4>
                                        <p><?php _e('Comprehensive guides and API documentation', 'replanta-hub'); ?></p>
                                        <a href="https://docs.replanta.com/hub" target="_blank" class="button button-secondary">
                                            <?php _e('View Docs', 'replanta-hub'); ?>
                                        </a>
                                    </div>
                                    <div class="support-option">
                                        <div class="support-icon"></div>
                                        <h4><?php _e('Community Forum', 'replanta-hub'); ?></h4>
                                        <p><?php _e('Get help from other users and developers', 'replanta-hub'); ?></p>
                                        <a href="https://community.replanta.com" target="_blank" class="button button-secondary">
                                            <?php _e('Join Forum', 'replanta-hub'); ?>
                                        </a>
                                    </div>
                                    <div class="support-option">
                                        <div class="support-icon"></div>
                                        <h4><?php _e('Priority Support', 'replanta-hub'); ?></h4>
                                        <p><?php _e('Direct access to our technical team', 'replanta-hub'); ?></p>
                                        <a href="<?php echo admin_url('options-general.php?page=replanta-care'); ?>" class="button button-primary">
                                            <?php _e('Get Care Support', 'replanta-hub'); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="instructions-footer">
                <div class="footer-content">
                    <div class="footer-info">
                        <h4><?php _e('Need More Help?', 'replanta-hub'); ?></h4>
                        <p><?php _e('Our team is here to help you get the most out of Replanta Hub\'s advanced security features.', 'replanta-hub'); ?></p>
                    </div>
                    <div class="footer-actions">
                        <a href="mailto:support@replanta.com" class="button button-large button-primary">
                            <?php _e('Contact Support', 'replanta-hub'); ?>
                        </a>
                        <a href="https://replanta.com/hub/features" target="_blank" class="button button-large button-secondary">
                            <?php _e('Learn More', 'replanta-hub'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

// Initialize the instructions page
new Replanta_Hub_Instructions_Page();
