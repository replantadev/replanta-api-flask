<?php
/**
 * API Database Schema for Replanta Hub Professional
 * 
 * Creates and manages API-related database tables
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ReplantaHub_API_Schema {
    
    public function __construct() {
        add_action('rphub_create_api_tables', array($this, 'create_tables'));
        add_action('rphub_upgrade_api_tables', array($this, 'upgrade_tables'));
    }
    
    /**
     * Create API-related tables
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // API Tokens table
        $api_tokens_table = $wpdb->prefix . 'rphub_api_tokens';
        $api_tokens_sql = "CREATE TABLE IF NOT EXISTS $api_tokens_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            name varchar(255) NOT NULL,
            token_hash varchar(64) NOT NULL,
            permissions text NOT NULL,
            status enum('active','revoked','expired') DEFAULT 'active',
            last_used_at datetime DEFAULT NULL,
            expires_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY token_hash (token_hash),
            KEY user_id (user_id),
            KEY status (status),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        // API Rate Limiting table
        $rate_limits_table = $wpdb->prefix . 'rphub_api_rate_limits';
        $rate_limits_sql = "CREATE TABLE IF NOT EXISTS $rate_limits_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            client_identifier varchar(255) NOT NULL,
            endpoint varchar(255) NOT NULL,
            requests_count int(11) NOT NULL DEFAULT 0,
            window_start datetime NOT NULL,
            window_end datetime NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY client_endpoint_window (client_identifier, endpoint, window_start),
            KEY window_end (window_end)
        ) $charset_collate;";
        
        // API Logs table
        $api_logs_table = $wpdb->prefix . 'rphub_api_logs';
        $api_logs_sql = "CREATE TABLE IF NOT EXISTS $api_logs_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            token_id bigint(20) unsigned DEFAULT NULL,
            client_ip varchar(45) NOT NULL,
            user_agent text,
            method varchar(10) NOT NULL,
            endpoint varchar(255) NOT NULL,
            request_data longtext,
            response_status int(11) NOT NULL,
            response_data longtext,
            execution_time decimal(8,4) DEFAULT NULL,
            memory_usage int(11) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY token_id (token_id),
            KEY client_ip (client_ip),
            KEY endpoint (endpoint),
            KEY response_status (response_status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Webhook Events table
        $webhook_events_table = $wpdb->prefix . 'rphub_webhook_events';
        $webhook_events_sql = "CREATE TABLE IF NOT EXISTS $webhook_events_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(100) NOT NULL,
            source_service varchar(50) NOT NULL,
            payload longtext NOT NULL,
            signature varchar(255) DEFAULT NULL,
            status enum('pending','processed','failed','ignored') DEFAULT 'pending',
            attempts int(11) DEFAULT 0,
            last_attempt_at datetime DEFAULT NULL,
            processed_at datetime DEFAULT NULL,
            error_message text,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY source_service (source_service),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // API Quotas table
        $api_quotas_table = $wpdb->prefix . 'rphub_api_quotas';
        $api_quotas_sql = "CREATE TABLE IF NOT EXISTS $api_quotas_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            token_id bigint(20) unsigned NOT NULL,
            quota_type enum('daily','monthly','custom') NOT NULL DEFAULT 'daily',
            quota_limit int(11) NOT NULL,
            quota_used int(11) NOT NULL DEFAULT 0,
            reset_at datetime NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY token_quota_type (token_id, quota_type),
            KEY reset_at (reset_at)
        ) $charset_collate;";
        
        // API Endpoints table
        $api_endpoints_table = $wpdb->prefix . 'rphub_api_endpoints';
        $api_endpoints_sql = "CREATE TABLE IF NOT EXISTS $api_endpoints_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            endpoint varchar(255) NOT NULL,
            method varchar(10) NOT NULL,
            description text,
            required_permissions text,
            rate_limit_per_minute int(11) DEFAULT NULL,
            rate_limit_per_hour int(11) DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            version varchar(10) DEFAULT 'v1',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY endpoint_method (endpoint, method),
            KEY is_active (is_active),
            KEY version (version)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $results = array();
        
        $results['api_tokens'] = dbDelta($api_tokens_sql);
        $results['rate_limits'] = dbDelta($rate_limits_sql);
        $results['api_logs'] = dbDelta($api_logs_sql);
        $results['webhook_events'] = dbDelta($webhook_events_sql);
        $results['api_quotas'] = dbDelta($api_quotas_sql);
        $results['api_endpoints'] = dbDelta($api_endpoints_sql);
        
        // Add indexes for performance
        $this->add_indexes();
        
        // Insert default endpoint configurations
        $this->insert_default_endpoints();
        
        error_log('RPHUB: API database tables created/updated');
        
        return $results;
    }
    
    /**
     * Add performance indexes
     */
    private function add_indexes() {
        global $wpdb;
        
        $indexes = array(
            "CREATE INDEX IF NOT EXISTS idx_api_logs_performance ON {$wpdb->prefix}rphub_api_logs (created_at, response_status, execution_time)",
            "CREATE INDEX IF NOT EXISTS idx_webhook_events_processing ON {$wpdb->prefix}rphub_webhook_events (status, created_at, attempts)",
            "CREATE INDEX IF NOT EXISTS idx_rate_limits_cleanup ON {$wpdb->prefix}rphub_api_rate_limits (window_end, created_at)",
            "CREATE INDEX IF NOT EXISTS idx_api_tokens_active ON {$wpdb->prefix}rphub_api_tokens (status, expires_at, last_used_at)"
        );
        
        foreach ($indexes as $index_sql) {
            $wpdb->query($index_sql);
        }
    }
    
    /**
     * Insert default API endpoint configurations
     */
    private function insert_default_endpoints() {
        global $wpdb;
        
        $default_endpoints = array(
            array(
                'endpoint' => '/rphub/v1/sites',
                'method' => 'GET',
                'description' => 'List all sites',
                'required_permissions' => json_encode(array('read')),
                'rate_limit_per_minute' => 60,
                'rate_limit_per_hour' => 1000
            ),
            array(
                'endpoint' => '/rphub/v1/sites',
                'method' => 'POST',
                'description' => 'Create new site',
                'required_permissions' => json_encode(array('write')),
                'rate_limit_per_minute' => 10,
                'rate_limit_per_hour' => 100
            ),
            array(
                'endpoint' => '/rphub/v1/analytics/overview',
                'method' => 'GET',
                'description' => 'Get analytics overview',
                'required_permissions' => json_encode(array('read')),
                'rate_limit_per_minute' => 30,
                'rate_limit_per_hour' => 500
            ),
            array(
                'endpoint' => '/rphub/v1/monitoring/uptime',
                'method' => 'GET',
                'description' => 'Get uptime status',
                'required_permissions' => json_encode(array('read')),
                'rate_limit_per_minute' => 60,
                'rate_limit_per_hour' => 1000
            ),
            array(
                'endpoint' => '/rphub/v1/automation/maintenance',
                'method' => 'POST',
                'description' => 'Run maintenance tasks',
                'required_permissions' => json_encode(array('write')),
                'rate_limit_per_minute' => 5,
                'rate_limit_per_hour' => 50
            ),
            array(
                'endpoint' => '/rphub/v1/system/status',
                'method' => 'GET',
                'description' => 'Get system status',
                'required_permissions' => json_encode(array('read')),
                'rate_limit_per_minute' => 30,
                'rate_limit_per_hour' => 300
            )
        );
        
        $table = $wpdb->prefix . 'rphub_api_endpoints';
        
        foreach ($default_endpoints as $endpoint) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE endpoint = %s AND method = %s",
                $endpoint['endpoint'],
                $endpoint['method']
            ));
            
            if (!$existing) {
                $wpdb->insert($table, $endpoint);
            }
        }
    }
    
    /**
     * Upgrade tables for new versions
     */
    public function upgrade_tables() {
        $current_version = get_option('rphub_api_schema_version', '1.0');
        
        if (version_compare($current_version, '1.1', '<')) {
            $this->upgrade_to_v1_1();
            update_option('rphub_api_schema_version', '1.1');
        }
        
        if (version_compare($current_version, '1.2', '<')) {
            $this->upgrade_to_v1_2();
            update_option('rphub_api_schema_version', '1.2');
        }
    }
    
    /**
     * Upgrade to version 1.1
     */
    private function upgrade_to_v1_1() {
        global $wpdb;
        
        // Add new columns to existing tables
        $wpdb->query("ALTER TABLE {$wpdb->prefix}rphub_api_tokens ADD COLUMN IF NOT EXISTS scope text AFTER permissions");
        $wpdb->query("ALTER TABLE {$wpdb->prefix}rphub_api_logs ADD COLUMN IF NOT EXISTS request_id varchar(36) AFTER id");
        
        error_log('RPHUB: API schema upgraded to v1.1');
    }
    
    /**
     * Upgrade to version 1.2
     */
    private function upgrade_to_v1_2() {
        global $wpdb;
        
        // Add API monitoring table
        $charset_collate = $wpdb->get_charset_collate();
        
        $monitoring_table = $wpdb->prefix . 'rphub_api_monitoring';
        $monitoring_sql = "CREATE TABLE IF NOT EXISTS $monitoring_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            endpoint varchar(255) NOT NULL,
            response_time_avg decimal(8,4) NOT NULL,
            response_time_p95 decimal(8,4) NOT NULL,
            error_rate decimal(5,2) NOT NULL,
            request_count int(11) NOT NULL,
            hour_bucket datetime NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY endpoint_hour (endpoint, hour_bucket),
            KEY hour_bucket (hour_bucket)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($monitoring_sql);
        
        error_log('RPHUB: API schema upgraded to v1.2');
    }
    
    /**
     * Clean up old data
     */
    public function cleanup_old_data() {
        global $wpdb;
        
        // Clean up old API logs (keep last 30 days)
        $wpdb->query("DELETE FROM {$wpdb->prefix}rphub_api_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        
        // Clean up old rate limit data (keep last 24 hours)
        $wpdb->query("DELETE FROM {$wpdb->prefix}rphub_api_rate_limits WHERE window_end < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        
        // Clean up processed webhook events (keep last 7 days)
        $wpdb->query("DELETE FROM {$wpdb->prefix}rphub_webhook_events WHERE status = 'processed' AND processed_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        
        // Reset expired quotas
        $wpdb->query("UPDATE {$wpdb->prefix}rphub_api_quotas SET quota_used = 0, reset_at = DATE_ADD(reset_at, INTERVAL 1 DAY) WHERE reset_at < NOW() AND quota_type = 'daily'");
        $wpdb->query("UPDATE {$wpdb->prefix}rphub_api_quotas SET quota_used = 0, reset_at = DATE_ADD(reset_at, INTERVAL 1 MONTH) WHERE reset_at < NOW() AND quota_type = 'monthly'");
    }
    
    /**
     * Get table statistics
     */
    public function get_table_stats() {
        global $wpdb;
        
        $tables = array(
            'api_tokens' => $wpdb->prefix . 'rphub_api_tokens',
            'api_logs' => $wpdb->prefix . 'rphub_api_logs',
            'webhook_events' => $wpdb->prefix . 'rphub_webhook_events',
            'rate_limits' => $wpdb->prefix . 'rphub_api_rate_limits',
            'api_quotas' => $wpdb->prefix . 'rphub_api_quotas',
            'api_endpoints' => $wpdb->prefix . 'rphub_api_endpoints'
        );
        
        $stats = array();
        
        foreach ($tables as $table_key => $table_name) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            $size = $wpdb->get_var("SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'size_mb' FROM information_schema.TABLES WHERE table_schema = DATABASE() AND table_name = '$table_name'");
            
            $stats[$table_key] = array(
                'name' => $table_name,
                'count' => intval($count),
                'size_mb' => floatval($size)
            );
        }
        
        return $stats;
    }
    
    /**
     * Verify table integrity
     */
    public function verify_tables() {
        global $wpdb;
        
        $required_tables = array(
            'rphub_api_tokens',
            'rphub_api_rate_limits',
            'rphub_api_logs',
            'rphub_webhook_events',
            'rphub_api_quotas',
            'rphub_api_endpoints'
        );
        
        $missing_tables = array();
        
        foreach ($required_tables as $table) {
            $full_table_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") === $full_table_name;
            
            if (!$exists) {
                $missing_tables[] = $table;
            }
        }
        
        return array(
            'all_exist' => empty($missing_tables),
            'missing' => $missing_tables,
            'total_required' => count($required_tables),
            'total_existing' => count($required_tables) - count($missing_tables)
        );
    }
}

// Initialize API schema
new ReplantaHub_API_Schema();
