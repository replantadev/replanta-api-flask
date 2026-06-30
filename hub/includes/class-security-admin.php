<?php
/**
 * Security Framework Admin Interface
 * Provides comprehensive security management dashboard with real-time monitoring
 * 
 * @package ReplantaHub
 * @since 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPHUB_Security_Admin {
    
    private $security_framework;
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_security_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_rphub_security_scan', array($this, 'handle_security_scan'));
        add_action('wp_ajax_rphub_security_get_threats', array($this, 'get_threats'));
        add_action('wp_ajax_rphub_security_block_ip', array($this, 'block_ip'));
        add_action('wp_ajax_rphub_security_get_logs', array($this, 'get_security_logs'));
        add_action('wp_ajax_rphub_security_update_settings', array($this, 'update_security_settings'));
        add_action('wp_ajax_rphub_security_whitelist_file', array($this, 'whitelist_file'));
        add_action('wp_ajax_rphub_security_get_compliance_report', array($this, 'get_compliance_report'));
        add_action('wp_ajax_rphub_security_get_stats', array($this, 'ajax_get_stats'));
    }
    
    public function init() {
        if (class_exists('RPHUB_Security_Framework')) {
            $this->security_framework = new RPHUB_Security_Framework();
        }
    }
    
    public function add_security_menu() {
        // Add security submenu under Replanta Hub
        add_submenu_page(
            'replanta-hub',
            __('Seguridad', 'replanta-hub'),
            __('Seguridad', 'replanta-hub'),
            'manage_options',
            'rphub-security',
            array($this, 'render_security_dashboard')
        );
        
        // @removed v1.5.1 — Threat Center merged into Security dashboard, Compliance removed (hardcoded fake data)
    }
    
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'rphub-security') === false) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true);
        wp_enqueue_script('rphub-security-admin', plugin_dir_url(__FILE__) . '../assets/js/security-admin.js', array('jquery', 'chartjs'), '7.0.0', true);
        wp_enqueue_style('rphub-security-admin', plugin_dir_url(__FILE__) . '../assets/css/security-admin.css', array(), '7.0.0');
        
        wp_localize_script('rphub-security-admin', 'rphub_security_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rphub_security_nonce'),
            'strings' => array(
                'scanning' => __('Scanning...', 'replanta-hub'),
                'scan_complete' => __('Scan Complete', 'replanta-hub'),
                'threat_blocked' => __('Threat Blocked', 'replanta-hub'),
                'ip_blocked' => __('IP Address Blocked', 'replanta-hub'),
                'file_whitelisted' => __('File Whitelisted', 'replanta-hub'),
                'settings_saved' => __('Settings Saved', 'replanta-hub'),
                'confirm_block' => __('Are you sure you want to block this IP?', 'replanta-hub'),
                'confirm_whitelist' => __('Are you sure you want to whitelist this file?', 'replanta-hub')
            )
        ));
    }
    
    public function render_security_dashboard() {
        // Get security statistics
        $security_stats = $this->get_security_statistics();
        $recent_scans = $this->get_recent_scans();
        $active_threats = $this->get_active_threats();
        $security_settings = $this->get_security_settings();
        
        ?>
        <div class="wrap rphub-security-dashboard">
            <h1><?php _e('Security Framework Dashboard', 'replanta-hub'); ?></h1>
            
            <!-- Security Overview -->
            <div class="rphub-security-overview">
                <div class="security-stat-cards">
                    <div class="stat-card threat-level">
                        <h3><?php _e('Threat Level', 'replanta-hub'); ?></h3>
                        <div class="stat-value <?php echo esc_attr($security_stats['threat_level']); ?>">
                            <?php echo esc_html(ucfirst($security_stats['threat_level'])); ?>
                        </div>
                        <p><?php echo esc_html($security_stats['threats_count']); ?> <?php _e('Active Threats', 'replanta-hub'); ?></p>
                    </div>
                    
                    <div class="stat-card scans-completed">
                        <h3><?php _e('Security Scans', 'replanta-hub'); ?></h3>
                        <div class="stat-value"><?php echo esc_html($security_stats['scans_today']); ?></div>
                        <p><?php _e('Scans Today', 'replanta-hub'); ?></p>
                    </div>
                    
                    <div class="stat-card blocked-attempts">
                        <h3><?php _e('Blocked Attempts', 'replanta-hub'); ?></h3>
                        <div class="stat-value"><?php echo esc_html($security_stats['blocked_today']); ?></div>
                        <p><?php _e('Attacks Blocked Today', 'replanta-hub'); ?></p>
                    </div>
                    
                    <div class="stat-card compliance-score">
                        <h3><?php _e('Compliance Score', 'replanta-hub'); ?></h3>
                        <div class="stat-value"><?php echo esc_html($security_stats['compliance_score']); ?>%</div>
                        <p><?php _e('Security Standards', 'replanta-hub'); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Real-time Security Monitor -->
            <div class="rphub-security-monitor">
                <h2><?php _e('Real-time Security Monitor', 'replanta-hub'); ?></h2>
                <div class="monitor-grid">
                    <div class="monitor-panel">
                        <h3><?php _e('Threat Detection', 'replanta-hub'); ?></h3>
                        <canvas id="threatDetectionChart"></canvas>
                    </div>
                    
                    <div class="monitor-panel">
                        <h3><?php _e('Attack Vectors', 'replanta-hub'); ?></h3>
                        <canvas id="attackVectorsChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="rphub-quick-actions">
                <h2><?php _e('Quick Security Actions', 'replanta-hub'); ?></h2>
                <div class="action-buttons">
                    <button id="run-security-scan" class="button button-primary">
                        <span class="dashicons dashicons-shield-alt"></span>
                        <?php _e('Run Full Security Scan', 'replanta-hub'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Recent Security Activity -->
            <div class="rphub-recent-activity">
                <h2><?php _e('Recent Security Activity', 'replanta-hub'); ?></h2>
                <div class="activity-grid">
                    <div class="activity-panel">
                        <h3><?php _e('Latest Scans', 'replanta-hub'); ?></h3>
                        <div class="activity-list">
                            <?php foreach ($recent_scans as $scan): ?>
                            <div class="activity-item">
                                <span class="activity-time"><?php echo esc_html($scan['scan_date']); ?></span>
                                <span class="activity-type"><?php echo esc_html($scan['scan_type']); ?></span>
                                <span class="activity-status <?php echo esc_attr($scan['status']); ?>">
                                    <?php echo esc_html($scan['status']); ?>
                                </span>
                                <span class="activity-details"><?php echo esc_html($scan['threats_found']); ?> threats found</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="activity-panel">
                        <h3><?php _e('Active Threats', 'replanta-hub'); ?></h3>
                        <div class="threat-list">
                            <?php foreach ($active_threats as $threat): ?>
                            <div class="threat-item severity-<?php echo esc_attr($threat['severity']); ?>">
                                <span class="threat-type"><?php echo esc_html($threat['threat_type']); ?></span>
                                <span class="threat-target"><?php echo esc_html($threat['target']); ?></span>
                                <span class="threat-severity"><?php echo esc_html(ucfirst($threat['severity'])); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Security Settings -->
            <div class="rphub-security-settings">
                <h2><?php _e('Security Settings', 'replanta-hub'); ?></h2>
                <form id="security-settings-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Auto Scan Frequency', 'replanta-hub'); ?></th>
                            <td>
                                <select name="auto_scan_frequency">
                                    <option value="hourly" <?php selected($security_settings['auto_scan_frequency'], 'hourly'); ?>>
                                        <?php _e('Every Hour', 'replanta-hub'); ?>
                                    </option>
                                    <option value="daily" <?php selected($security_settings['auto_scan_frequency'], 'daily'); ?>>
                                        <?php _e('Daily', 'replanta-hub'); ?>
                                    </option>
                                    <option value="weekly" <?php selected($security_settings['auto_scan_frequency'], 'weekly'); ?>>
                                        <?php _e('Weekly', 'replanta-hub'); ?>
                                    </option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Threat Detection Sensitivity', 'replanta-hub'); ?></th>
                            <td>
                                <select name="threat_sensitivity">
                                    <option value="low" <?php selected($security_settings['threat_sensitivity'], 'low'); ?>>
                                        <?php _e('Low', 'replanta-hub'); ?>
                                    </option>
                                    <option value="medium" <?php selected($security_settings['threat_sensitivity'], 'medium'); ?>>
                                        <?php _e('Medium', 'replanta-hub'); ?>
                                    </option>
                                    <option value="high" <?php selected($security_settings['threat_sensitivity'], 'high'); ?>>
                                        <?php _e('High', 'replanta-hub'); ?>
                                    </option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Auto Block Threats', 'replanta-hub'); ?></th>
                            <td>
                                <input type="checkbox" name="auto_block_threats" value="1" 
                                       <?php checked($security_settings['auto_block_threats'], 1); ?> />
                                <label><?php _e('Automatically block detected threats', 'replanta-hub'); ?></label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Email Notifications', 'replanta-hub'); ?></th>
                            <td>
                                <input type="checkbox" name="email_notifications" value="1" 
                                       <?php checked($security_settings['email_notifications'], 1); ?> />
                                <label><?php _e('Send email alerts for security events', 'replanta-hub'); ?></label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Notification Email', 'replanta-hub'); ?></th>
                            <td>
                                <input type="email" name="notification_email" 
                                       value="<?php echo esc_attr($security_settings['notification_email']); ?>" 
                                       class="regular-text" />
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Firewall Mode', 'replanta-hub'); ?></th>
                            <td>
                                <select name="firewall_mode">
                                    <option value="learning" <?php selected($security_settings['firewall_mode'], 'learning'); ?>>
                                        <?php _e('Learning Mode', 'replanta-hub'); ?>
                                    </option>
                                    <option value="protective" <?php selected($security_settings['firewall_mode'], 'protective'); ?>>
                                        <?php _e('Protective Mode', 'replanta-hub'); ?>
                                    </option>
                                    <option value="aggressive" <?php selected($security_settings['firewall_mode'], 'aggressive'); ?>>
                                        <?php _e('Aggressive Mode', 'replanta-hub'); ?>
                                    </option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary">
                            <?php _e('Save Security Settings', 'replanta-hub'); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Initialize real-time monitoring charts
            initSecurityCharts();
        });
        
        function initSecurityCharts() {
            // Threat Detection Chart
            const threatCtx = document.getElementById('threatDetectionChart').getContext('2d');
            new Chart(threatCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($security_stats['threat_chart_labels']); ?>,
                    datasets: [{
                        label: 'Threats Detected',
                        data: <?php echo json_encode($security_stats['threat_chart_data']); ?>,
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
            
            // Attack Vectors Chart
            const attackCtx = document.getElementById('attackVectorsChart').getContext('2d');
            new Chart(attackCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($security_stats['attack_vector_labels']); ?>,
                    datasets: [{
                        data: <?php echo json_encode($security_stats['attack_vector_data']); ?>,
                        backgroundColor: [
                            '#dc3545',
                            '#fd7e14',
                            '#ffc107',
                            '#28a745',
                            '#17a2b8'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
        </script>
        <?php
    }
    
    // AJAX Handlers
    public function handle_security_scan() {
        check_ajax_referer('rphub_security_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }

        if (!$this->security_framework) {
            wp_send_json_error(['message' => 'Security framework no disponible en este servidor']);
            return;
        }

        try {
            $scan_result = $this->security_framework->run_comprehensive_scan();
            wp_send_json_success($scan_result);
        } catch (\Throwable $e) {
            error_log('[Replanta Hub] Security scan error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Error en el escaneo: ' . $e->getMessage()]);
        }
    }
    
    public function get_threats() {
        check_ajax_referer('rphub_security_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $threats = $this->get_active_threats();
        wp_send_json_success($threats);
    }
    
    public function block_ip() {
        check_ajax_referer('rphub_security_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $ip_address = sanitize_text_field($_POST['ip_address']);
        $reason = sanitize_text_field($_POST['reason']);
        
        if ($this->security_framework) {
            $result = $this->security_framework->block_ip($ip_address, $reason);
            wp_send_json_success($result);
        } else {
            wp_send_json_error(__('Security framework not available', 'replanta-hub'));
        }
    }
    
    public function get_security_logs() {
        check_ajax_referer('rphub_security_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $logs = $this->get_recent_security_logs();
        wp_send_json_success($logs);
    }
    
    public function update_security_settings() {
        check_ajax_referer('rphub_security_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $settings = array(
            'auto_scan_frequency' => sanitize_text_field($_POST['auto_scan_frequency']),
            'threat_sensitivity' => sanitize_text_field($_POST['threat_sensitivity']),
            'auto_block_threats' => isset($_POST['auto_block_threats']) ? 1 : 0,
            'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
            'notification_email' => sanitize_email($_POST['notification_email']),
            'firewall_mode' => sanitize_text_field($_POST['firewall_mode'])
        );
        
        update_option('rphub_security_settings', $settings);
        wp_send_json_success(__('Settings saved successfully', 'replanta-hub'));
    }
    
    public function whitelist_file() {
        check_ajax_referer('rphub_security_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $file_path = sanitize_text_field($_POST['file_path']);
        
        if ($this->security_framework) {
            $result = $this->security_framework->whitelist_file($file_path);
            wp_send_json_success($result);
        } else {
            wp_send_json_error(__('Security framework not available', 'replanta-hub'));
        }
    }
    
    public function get_compliance_report() {
        check_ajax_referer('rphub_security_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $report_type = sanitize_text_field($_POST['report_type']);
        
        if ($this->security_framework) {
            $report = $this->security_framework->generate_compliance_report($report_type);
            wp_send_json_success($report);
        } else {
            wp_send_json_error(['message' => 'Security framework no disponible']);
        }
    }

    public function ajax_get_stats() {
        check_ajax_referer('rphub_security_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        $stats = $this->get_security_statistics();
        wp_send_json_success($stats);
    }

    // Helper Methods
    private function get_security_statistics() {
        global $wpdb;
        
        $table_scans = $wpdb->prefix . 'rphub_security_scans';
        $table_threats = $wpdb->prefix . 'rphub_security_threats';
        $table_logs = $wpdb->prefix . 'rphub_security_logs';
        
        // Calculate threat level
        $critical_threats = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_threats WHERE status = 'active' AND severity = %s",
            'critical'
        ));
        
        $threat_level = 'low';
        if ($critical_threats > 0) {
            $threat_level = 'critical';
        } elseif ($wpdb->get_var("SELECT COUNT(*) FROM $table_threats WHERE status = 'active' AND severity = 'high'") > 0) {
            $threat_level = 'high';
        } elseif ($wpdb->get_var("SELECT COUNT(*) FROM $table_threats WHERE status = 'active'") > 0) {
            $threat_level = 'medium';
        }
        
        return array(
            'threat_level' => $threat_level,
            'threats_count' => $wpdb->get_var("SELECT COUNT(*) FROM $table_threats WHERE status = 'active'"),
            'scans_today' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_scans WHERE DATE(scan_date) = %s",
                current_time('Y-m-d')
            )),
            'blocked_today' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_logs WHERE DATE(log_date) = %s AND event_type = %s",
                current_time('Y-m-d'),
                'threat_blocked'
            )),
            'compliance_score' => $this->calculate_compliance_score(),
            'threat_chart_labels' => $this->get_threat_chart_labels(),
            'threat_chart_data' => $this->get_threat_chart_data(),
            'attack_vector_labels' => $this->get_attack_vector_labels(),
            'attack_vector_data' => $this->get_attack_vector_data()
        );
    }
    
    private function get_recent_scans() {
        global $wpdb;
        
        $table_scans = $wpdb->prefix . 'rphub_security_scans';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT scan_type, scan_date, status, threats_found 
             FROM $table_scans 
             ORDER BY scan_date DESC 
             LIMIT %d",
            10
        ), ARRAY_A);
    }
    
    private function get_active_threats() {
        global $wpdb;
        
        $table_threats = $wpdb->prefix . 'rphub_security_threats';
        
        return $wpdb->get_results(
            "SELECT id, threat_type, target, severity, detected_date, status 
             FROM $table_threats 
             WHERE status = 'active' 
             ORDER BY severity DESC, detected_date DESC 
             LIMIT 10",
            ARRAY_A
        );
    }
    
    private function get_security_settings() {
        return wp_parse_args(get_option('rphub_security_settings', array()), array(
            'auto_scan_frequency' => 'daily',
            'threat_sensitivity' => 'medium',
            'auto_block_threats' => 1,
            'email_notifications' => 1,
            'notification_email' => get_option('admin_email'),
            'firewall_mode' => 'protective'
        ));
    }
    
    private function calculate_compliance_score() {
        global $wpdb;
        $score = 0;
        $checks = 0;

        // SSL/HTTPS check
        $checks++;
        if (is_ssl()) $score++;

        // WP auto-updates enabled
        $checks++;
        if (defined('WP_AUTO_UPDATE_CORE') && WP_AUTO_UPDATE_CORE) $score++;

        // Debug mode disabled
        $checks++;
        if (!defined('WP_DEBUG') || !WP_DEBUG) $score++;

        // File editing disabled
        $checks++;
        if (defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT) $score++;

        // Table prefix not default
        $checks++;
        if ($wpdb->prefix !== 'wp_') $score++;

        return $checks > 0 ? round(($score / $checks) * 100) : 0;
    }
    
    private function get_threat_chart_labels() {
        $labels = array();
        for ($i = 6; $i >= 0; $i--) {
            $labels[] = date('M j', strtotime("-$i days"));
        }
        return $labels;
    }
    
    private function get_threat_chart_data() {
        global $wpdb;
        
        $table_threats = $wpdb->prefix . 'rphub_security_threats';
        $data = array();
        
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_threats WHERE DATE(detected_date) = %s",
                $date
            ));
            $data[] = (int) $count;
        }
        
        return $data;
    }
    
    private function get_attack_vector_labels() {
        return array('SQL Injection', 'XSS', 'Malware', 'Brute Force', 'Other');
    }
    
    private function get_attack_vector_data() {
        global $wpdb;
        
        $table_threats = $wpdb->prefix . 'rphub_security_threats';
        
        $vectors = array(
            'sql_injection' => $wpdb->get_var("SELECT COUNT(*) FROM $table_threats WHERE threat_type LIKE '%sql%'"),
            'xss' => $wpdb->get_var("SELECT COUNT(*) FROM $table_threats WHERE threat_type LIKE '%xss%'"),
            'malware' => $wpdb->get_var("SELECT COUNT(*) FROM $table_threats WHERE threat_type LIKE '%malware%'"),
            'brute_force' => $wpdb->get_var("SELECT COUNT(*) FROM $table_threats WHERE threat_type LIKE '%brute%'"),
            'other' => $wpdb->get_var("SELECT COUNT(*) FROM $table_threats WHERE threat_type NOT LIKE '%sql%' AND threat_type NOT LIKE '%xss%' AND threat_type NOT LIKE '%malware%' AND threat_type NOT LIKE '%brute%'")
        );
        
        return array_values($vectors);
    }
    
    private function get_recent_security_logs() {
        global $wpdb;
        
        $table_logs = $wpdb->prefix . 'rphub_security_logs';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_logs 
             ORDER BY log_date DESC 
             LIMIT %d",
            100
        ), ARRAY_A);
    }
}
