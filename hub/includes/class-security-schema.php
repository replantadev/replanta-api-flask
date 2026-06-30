<?php
/**
 * Security Schema
 * Database schema for security framework
 * 
 * @package ReplantaHub
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPHUB_Security_Schema {
    
    public function __construct() {
        add_action('init', array($this, 'create_tables'));
    }
    
    /**
     * Create security tables
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Security Scans table
        $table_name = $wpdb->prefix . 'rphub_security_scans';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            scan_id varchar(255) NOT NULL,
            scan_type varchar(50) NOT NULL,
            security_score int DEFAULT 0,
            threats_count int DEFAULT 0,
            vulnerabilities_count int DEFAULT 0,
            scan_results longtext,
            started_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime NULL,
            status varchar(20) DEFAULT 'running',
            PRIMARY KEY (id),
            UNIQUE KEY unique_scan_id (scan_id),
            INDEX idx_scan_type (scan_type),
            INDEX idx_security_score (security_score),
            INDEX idx_started_at (started_at),
            INDEX idx_status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Security Logs table
        $table_name = $wpdb->prefix . 'rphub_security_logs';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            log_type varchar(50) NOT NULL,
            event_type varchar(100) NOT NULL,
            severity varchar(20) DEFAULT 'medium',
            log_data longtext,
            ip_address varchar(45),
            user_agent text,
            user_id bigint(20) unsigned DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_log_type (log_type),
            INDEX idx_event_type (event_type),
            INDEX idx_severity (severity),
            INDEX idx_ip_address (ip_address),
            INDEX idx_user_id (user_id),
            INDEX idx_created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Blocked IPs table
        $table_name = $wpdb->prefix . 'rphub_blocked_ips';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            ip_address varchar(45) NOT NULL,
            block_reason varchar(255),
            block_type varchar(50) DEFAULT 'temporary',
            blocked_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime NULL,
            blocked_by bigint(20) unsigned DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY unique_ip (ip_address),
            INDEX idx_block_type (block_type),
            INDEX idx_blocked_at (blocked_at),
            INDEX idx_expires_at (expires_at),
            INDEX idx_is_active (is_active)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Security Rules table
        $table_name = $wpdb->prefix . 'rphub_security_rules';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            rule_name varchar(255) NOT NULL,
            rule_type varchar(50) NOT NULL,
            rule_pattern text,
            rule_action varchar(50) DEFAULT 'log',
            severity varchar(20) DEFAULT 'medium',
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_rule_name (rule_name),
            INDEX idx_rule_type (rule_type),
            INDEX idx_is_active (is_active)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Threat Intelligence table
        $table_name = $wpdb->prefix . 'rphub_threat_intelligence';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            threat_type varchar(100) NOT NULL,
            threat_signature text,
            threat_description text,
            severity varchar(20) DEFAULT 'medium',
            source varchar(100),
            confidence_score int DEFAULT 50,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_threat_type (threat_type),
            INDEX idx_severity (severity),
            INDEX idx_confidence_score (confidence_score),
            INDEX idx_is_active (is_active)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Compliance Reports table
        $table_name = $wpdb->prefix . 'rphub_compliance_reports';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            standard varchar(50) NOT NULL,
            compliance_score int DEFAULT 0,
            status varchar(20) DEFAULT 'pending',
            report_data longtext,
            generated_at datetime DEFAULT CURRENT_TIMESTAMP,
            generated_by bigint(20) unsigned DEFAULT 0,
            PRIMARY KEY (id),
            INDEX idx_standard (standard),
            INDEX idx_compliance_score (compliance_score),
            INDEX idx_status (status),
            INDEX idx_generated_at (generated_at)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Security Incidents table
        $table_name = $wpdb->prefix . 'rphub_security_incidents';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            incident_type varchar(100) NOT NULL,
            severity varchar(20) DEFAULT 'medium',
            status varchar(20) DEFAULT 'open',
            title varchar(255),
            description text,
            incident_data longtext,
            affected_resources text,
            detected_at datetime DEFAULT CURRENT_TIMESTAMP,
            resolved_at datetime NULL,
            assigned_to bigint(20) unsigned DEFAULT 0,
            resolution_notes text,
            PRIMARY KEY (id),
            INDEX idx_incident_type (incident_type),
            INDEX idx_severity (severity),
            INDEX idx_status (status),
            INDEX idx_detected_at (detected_at),
            INDEX idx_assigned_to (assigned_to)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // File Integrity table
        $table_name = $wpdb->prefix . 'rphub_file_integrity';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            file_path varchar(500) NOT NULL,
            file_hash varchar(255),
            file_size bigint DEFAULT 0,
            last_modified datetime,
            is_core_file tinyint(1) DEFAULT 0,
            is_monitored tinyint(1) DEFAULT 1,
            baseline_created_at datetime DEFAULT CURRENT_TIMESTAMP,
            last_checked_at datetime DEFAULT CURRENT_TIMESTAMP,
            status varchar(20) DEFAULT 'clean',
            PRIMARY KEY (id),
            UNIQUE KEY unique_file_path (file_path),
            INDEX idx_file_hash (file_hash),
            INDEX idx_is_core_file (is_core_file),
            INDEX idx_is_monitored (is_monitored),
            INDEX idx_last_checked_at (last_checked_at),
            INDEX idx_status (status)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Update database version
        update_option('rphub_security_db_version', '1.0.0');
        
        // Add default security rules
        $this->add_default_security_rules();
        
        // Add default threat intelligence
        $this->add_default_threat_intelligence();
    }
    
    /**
     * Add default security rules
     */
    private function add_default_security_rules() {
        global $wpdb;
        
        $default_rules = array(
            array(
                'rule_name' => 'SQL Injection Detection',
                'rule_type' => 'request_pattern',
                'rule_pattern' => '/union.*select|select.*from|insert.*into|delete.*from|drop.*table/i',
                'rule_action' => 'block',
                'severity' => 'high'
            ),
            array(
                'rule_name' => 'XSS Attack Detection',
                'rule_type' => 'request_pattern',
                'rule_pattern' => '/<script|javascript:|vbscript:|onload=|onerror=/i',
                'rule_action' => 'block',
                'severity' => 'high'
            ),
            array(
                'rule_name' => 'File Inclusion Attack',
                'rule_type' => 'request_pattern',
                'rule_pattern' => '/\.\.\/|\.\.\\\\|\/etc\/passwd|\/proc\/|\/dev\/|file:\/\//i',
                'rule_action' => 'block',
                'severity' => 'high'
            ),
            array(
                'rule_name' => 'Command Injection',
                'rule_type' => 'request_pattern',
                'rule_pattern' => '/\|.*ls|\|.*cat|\|.*grep|;.*rm|;.*mv|`.*`/i',
                'rule_action' => 'block',
                'severity' => 'critical'
            ),
            array(
                'rule_name' => 'Suspicious User Agent',
                'rule_type' => 'user_agent',
                'rule_pattern' => '/sqlmap|nikto|nmap|masscan|acunetix|burp|w3af/i',
                'rule_action' => 'log',
                'severity' => 'medium'
            ),
            array(
                'rule_name' => 'Brute Force Protection',
                'rule_type' => 'rate_limit',
                'rule_pattern' => 'wp-login.php',
                'rule_action' => 'throttle',
                'severity' => 'medium'
            )
        );
        
        foreach ($default_rules as $rule) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}rphub_security_rules WHERE rule_name = %s",
                $rule['rule_name']
            ));
            
            if (!$existing) {
                $wpdb->insert(
                    $wpdb->prefix . 'rphub_security_rules',
                    array_merge($rule, array(
                        'created_at' => current_time('mysql'),
                        'is_active' => 1
                    )),
                    array('%s', '%s', '%s', '%s', '%s', '%s', '%d')
                );
            }
        }
    }
    
    /**
     * Add default threat intelligence
     */
    private function add_default_threat_intelligence() {
        global $wpdb;
        
        $threat_signatures = array(
            array(
                'threat_type' => 'malware',
                'threat_signature' => 'eval(base64_decode(',
                'threat_description' => 'Base64 encoded malicious payload',
                'severity' => 'critical',
                'source' => 'internal',
                'confidence_score' => 95
            ),
            array(
                'threat_type' => 'malware',
                'threat_signature' => 'eval(gzinflate(',
                'threat_description' => 'Compressed malicious payload',
                'severity' => 'critical',
                'source' => 'internal',
                'confidence_score' => 95
            ),
            array(
                'threat_type' => 'backdoor',
                'threat_signature' => '$_POST[',
                'threat_description' => 'Potential backdoor using POST data',
                'severity' => 'high',
                'source' => 'internal',
                'confidence_score' => 80
            ),
            array(
                'threat_type' => 'shell',
                'threat_signature' => 'shell_exec(',
                'threat_description' => 'Shell command execution',
                'severity' => 'critical',
                'source' => 'internal',
                'confidence_score' => 90
            ),
            array(
                'threat_type' => 'injection',
                'threat_signature' => 'union select',
                'threat_description' => 'SQL injection attempt',
                'severity' => 'high',
                'source' => 'internal',
                'confidence_score' => 85
            )
        );
        
        foreach ($threat_signatures as $signature) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}rphub_threat_intelligence 
                 WHERE threat_signature = %s AND threat_type = %s",
                $signature['threat_signature'],
                $signature['threat_type']
            ));
            
            if (!$existing) {
                $wpdb->insert(
                    $wpdb->prefix . 'rphub_threat_intelligence',
                    array_merge($signature, array(
                        'created_at' => current_time('mysql'),
                        'is_active' => 1
                    )),
                    array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d')
                );
            }
        }
    }
    
    /**
     * Drop security tables (for uninstallation)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            'rphub_security_scans',
            'rphub_security_logs',
            'rphub_blocked_ips',
            'rphub_security_rules',
            'rphub_threat_intelligence',
            'rphub_compliance_reports',
            'rphub_security_incidents',
            'rphub_file_integrity'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
        }
        
        delete_option('rphub_security_db_version');
    }
    
    /**
     * Check if tables exist
     */
    public static function check_tables() {
        global $wpdb;
        
        $required_tables = array(
            'rphub_security_scans',
            'rphub_security_logs',
            'rphub_blocked_ips',
            'rphub_security_rules',
            'rphub_threat_intelligence',
            'rphub_compliance_reports',
            'rphub_security_incidents',
            'rphub_file_integrity'
        );
        
        $missing_tables = array();
        
        foreach ($required_tables as $table) {
            $table_name = $wpdb->prefix . $table;
            $result = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            
            if ($result !== $table_name) {
                $missing_tables[] = $table;
            }
        }
        
        if (!empty($missing_tables)) {
            $schema = new self();
            $schema->create_tables();
            return $missing_tables;
        }
        
        return array();
    }
}
