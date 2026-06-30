<?php
/**
 * Database Schema for Analytics Integration
 * 
 * Creates and manages database tables for analytics data storage
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ReplantaHub_Analytics_Schema {
    
    public static function create_analytics_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Google Analytics 4 data table
        $ga4_table = $wpdb->prefix . 'rphub_analytics_ga4';
        $ga4_sql = "CREATE TABLE $ga4_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            site_id bigint(20) NOT NULL,
            data longtext NOT NULL,
            collected_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY site_date (site_id, DATE(collected_at)),
            KEY site_id (site_id),
            KEY collected_at (collected_at)
        ) $charset_collate;";
        
        // Search Console data table
        $sc_table = $wpdb->prefix . 'rphub_analytics_search_console';
        $sc_sql = "CREATE TABLE $sc_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            site_id bigint(20) NOT NULL,
            data longtext NOT NULL,
            collected_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY site_date (site_id, DATE(collected_at)),
            KEY site_id (site_id),
            KEY collected_at (collected_at)
        ) $charset_collate;";
        
        // Web Vitals data table
        $vitals_table = $wpdb->prefix . 'rphub_analytics_web_vitals';
        $vitals_sql = "CREATE TABLE $vitals_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            site_id bigint(20) NOT NULL,
            data longtext NOT NULL,
            collected_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY site_date (site_id, DATE(collected_at)),
            KEY site_id (site_id),
            KEY collected_at (collected_at)
        ) $charset_collate;";
        
        // Real User Monitoring aggregated data table
        $rum_table = $wpdb->prefix . 'rphub_analytics_rum';
        $rum_sql = "CREATE TABLE $rum_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            site_id bigint(20) NOT NULL,
            data longtext NOT NULL,
            collected_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY site_date (site_id, DATE(collected_at)),
            KEY site_id (site_id),
            KEY collected_at (collected_at)
        ) $charset_collate;";
        
        // Real User Monitoring raw data table (temporary storage)
        $rum_data_table = $wpdb->prefix . 'rphub_rum_data';
        $rum_data_sql = "CREATE TABLE $rum_data_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            site_id bigint(20) NOT NULL,
            type varchar(50) NOT NULL,
            data longtext NOT NULL,
            url text,
            user_id bigint(20) DEFAULT 0,
            page_type varchar(50),
            device_type varchar(20),
            connection_type varchar(50),
            referrer text,
            user_agent text,
            collected_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY site_id (site_id),
            KEY type (type),
            KEY collected_at (collected_at),
            KEY user_id (user_id),
            KEY device_type (device_type)
        ) $charset_collate;";
        
        // Analytics insights table for processed data
        $insights_table = $wpdb->prefix . 'rphub_analytics_insights';
        $insights_sql = "CREATE TABLE $insights_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            site_id bigint(20) NOT NULL,
            insight_type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            description text,
            severity enum('info', 'warning', 'critical') DEFAULT 'info',
            data longtext,
            is_read tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime,
            PRIMARY KEY (id),
            KEY site_id (site_id),
            KEY insight_type (insight_type),
            KEY severity (severity),
            KEY is_read (is_read),
            KEY created_at (created_at),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        // Analytics performance benchmarks table
        $benchmarks_table = $wpdb->prefix . 'rphub_analytics_benchmarks';
        $benchmarks_sql = "CREATE TABLE $benchmarks_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            site_id bigint(20) NOT NULL,
            metric_name varchar(100) NOT NULL,
            baseline_value decimal(10,3),
            current_value decimal(10,3),
            target_value decimal(10,3),
            unit varchar(20),
            trend enum('improving', 'stable', 'declining') DEFAULT 'stable',
            last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY site_metric (site_id, metric_name),
            KEY site_id (site_id),
            KEY trend (trend),
            KEY last_updated (last_updated)
        ) $charset_collate;";
        
        // Analytics alerts table
        $alerts_table = $wpdb->prefix . 'rphub_analytics_alerts';
        $alerts_sql = "CREATE TABLE $alerts_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            site_id bigint(20) NOT NULL,
            alert_type varchar(50) NOT NULL,
            metric_name varchar(100),
            threshold_value decimal(10,3),
            current_value decimal(10,3),
            comparison_operator enum('>', '<', '>=', '<=', '=', '!=') DEFAULT '>',
            alert_title varchar(255) NOT NULL,
            alert_message text,
            severity enum('low', 'medium', 'high', 'critical') DEFAULT 'medium',
            is_active tinyint(1) DEFAULT 1,
            is_resolved tinyint(1) DEFAULT 0,
            triggered_at datetime DEFAULT CURRENT_TIMESTAMP,
            resolved_at datetime NULL,
            notification_sent tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            KEY site_id (site_id),
            KEY alert_type (alert_type),
            KEY severity (severity),
            KEY is_active (is_active),
            KEY is_resolved (is_resolved),
            KEY triggered_at (triggered_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create all tables
        dbDelta($ga4_sql);
        dbDelta($sc_sql);
        dbDelta($vitals_sql);
        dbDelta($rum_sql);
        dbDelta($rum_data_sql);
        dbDelta($insights_sql);
        dbDelta($benchmarks_sql);
        dbDelta($alerts_sql);
        
        // Create default benchmarks for common metrics
        self::create_default_benchmarks();
        
        // Update schema version
        update_option('rphub_analytics_schema_version', '1.0.0');
    }
    
    private static function create_default_benchmarks() {
        global $wpdb;
        
        $benchmarks_table = $wpdb->prefix . 'rphub_analytics_benchmarks';
        
        // Default benchmark values for common metrics
        $default_benchmarks = array(
            array(
                'metric_name' => 'largest_contentful_paint',
                'baseline_value' => 2500,
                'target_value' => 2000,
                'unit' => 'ms'
            ),
            array(
                'metric_name' => 'first_input_delay',
                'baseline_value' => 100,
                'target_value' => 50,
                'unit' => 'ms'
            ),
            array(
                'metric_name' => 'cumulative_layout_shift',
                'baseline_value' => 0.1,
                'target_value' => 0.05,
                'unit' => 'score'
            ),
            array(
                'metric_name' => 'first_contentful_paint',
                'baseline_value' => 1800,
                'target_value' => 1200,
                'unit' => 'ms'
            ),
            array(
                'metric_name' => 'time_to_first_byte',
                'baseline_value' => 800,
                'target_value' => 500,
                'unit' => 'ms'
            ),
            array(
                'metric_name' => 'page_load_time',
                'baseline_value' => 3000,
                'target_value' => 2000,
                'unit' => 'ms'
            ),
            array(
                'metric_name' => 'bounce_rate',
                'baseline_value' => 60,
                'target_value' => 40,
                'unit' => 'percent'
            ),
            array(
                'metric_name' => 'session_duration',
                'baseline_value' => 120,
                'target_value' => 180,
                'unit' => 'seconds'
            ),
            array(
                'metric_name' => 'pages_per_session',
                'baseline_value' => 2.0,
                'target_value' => 3.0,
                'unit' => 'count'
            ),
            array(
                'metric_name' => 'click_through_rate',
                'baseline_value' => 2.0,
                'target_value' => 5.0,
                'unit' => 'percent'
            )
        );
        
        // Get all active sites
        $sites = $wpdb->get_results("
            SELECT id FROM {$wpdb->prefix}rphub_sites 
            WHERE status = 'active'
        ");
        
        foreach ($sites as $site) {
            foreach ($default_benchmarks as $benchmark) {
                $wpdb->replace(
                    $benchmarks_table,
                    array_merge($benchmark, array('site_id' => $site->id)),
                    array('%d', '%s', '%f', '%f', '%s')
                );
            }
        }
    }
    
    public static function upgrade_analytics_schema() {
        $current_version = get_option('rphub_analytics_schema_version', '0.0.0');
        
        if (version_compare($current_version, '1.0.0', '<')) {
            self::create_analytics_tables();
        }
        
        // Future schema upgrades would go here
        // if (version_compare($current_version, '1.1.0', '<')) {
        //     self::upgrade_to_1_1_0();
        // }
    }
    
    public static function drop_analytics_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'rphub_analytics_ga4',
            $wpdb->prefix . 'rphub_analytics_search_console',
            $wpdb->prefix . 'rphub_analytics_web_vitals',
            $wpdb->prefix . 'rphub_analytics_rum',
            $wpdb->prefix . 'rphub_rum_data',
            $wpdb->prefix . 'rphub_analytics_insights',
            $wpdb->prefix . 'rphub_analytics_benchmarks',
            $wpdb->prefix . 'rphub_analytics_alerts'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        delete_option('rphub_analytics_schema_version');
    }
    
    public static function cleanup_old_analytics_data($days = 90) {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Clean up old raw RUM data (keep only 7 days)
        $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->prefix}rphub_rum_data 
            WHERE collected_at < %s
        ", date('Y-m-d H:i:s', strtotime('-7 days'))));
        
        // Clean up old insights (keep based on expiration date)
        $wpdb->query("
            DELETE FROM {$wpdb->prefix}rphub_analytics_insights 
            WHERE expires_at IS NOT NULL AND expires_at < NOW()
        ");
        
        // Clean up resolved alerts older than 30 days
        $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->prefix}rphub_analytics_alerts 
            WHERE is_resolved = 1 AND resolved_at < %s
        ", date('Y-m-d H:i:s', strtotime('-30 days'))));
        
        // Clean up old analytics data (configurable retention)
        $retention_days = get_option('rphub_analytics_retention_days', 90);
        $retention_cutoff = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        $analytics_tables = array(
            $wpdb->prefix . 'rphub_analytics_ga4',
            $wpdb->prefix . 'rphub_analytics_search_console',
            $wpdb->prefix . 'rphub_analytics_web_vitals',
            $wpdb->prefix . 'rphub_analytics_rum'
        );
        
        foreach ($analytics_tables as $table) {
            $wpdb->query($wpdb->prepare("
                DELETE FROM $table 
                WHERE collected_at < %s
            ", $retention_cutoff));
        }
    }
    
    public static function get_analytics_storage_stats() {
        global $wpdb;
        
        $stats = array();
        
        $tables = array(
            'rphub_analytics_ga4' => 'Google Analytics 4',
            'rphub_analytics_search_console' => 'Search Console',
            'rphub_analytics_web_vitals' => 'Web Vitals',
            'rphub_analytics_rum' => 'RUM Aggregated',
            'rphub_rum_data' => 'RUM Raw Data',
            'rphub_analytics_insights' => 'Insights',
            'rphub_analytics_benchmarks' => 'Benchmarks',
            'rphub_analytics_alerts' => 'Alerts'
        );
        
        foreach ($tables as $table => $name) {
            $full_table = $wpdb->prefix . $table;
            
            // Check if table exists
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table'") === $full_table;
            
            if ($exists) {
                $row_count = $wpdb->get_var("SELECT COUNT(*) FROM $full_table");
                $size_result = $wpdb->get_row("
                    SELECT 
                        ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                    FROM information_schema.TABLES 
                    WHERE table_schema = DATABASE() 
                    AND table_name = '$full_table'
                ");
                
                $stats[$table] = array(
                    'name' => $name,
                    'exists' => true,
                    'row_count' => intval($row_count),
                    'size_mb' => $size_result ? floatval($size_result->size_mb) : 0
                );
            } else {
                $stats[$table] = array(
                    'name' => $name,
                    'exists' => false,
                    'row_count' => 0,
                    'size_mb' => 0
                );
            }
        }
        
        return $stats;
    }
    
    public static function optimize_analytics_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'rphub_analytics_ga4',
            $wpdb->prefix . 'rphub_analytics_search_console',
            $wpdb->prefix . 'rphub_analytics_web_vitals',
            $wpdb->prefix . 'rphub_analytics_rum',
            $wpdb->prefix . 'rphub_rum_data',
            $wpdb->prefix . 'rphub_analytics_insights',
            $wpdb->prefix . 'rphub_analytics_benchmarks',
            $wpdb->prefix . 'rphub_analytics_alerts'
        );
        
        $results = array();
        
        foreach ($tables as $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
            
            if ($exists) {
                $result = $wpdb->query("OPTIMIZE TABLE $table");
                $results[$table] = $result !== false;
            }
        }
        
        return $results;
    }
}

// Initialize schema on plugin activation
register_activation_hook(__FILE__, array('ReplantaHub_Analytics_Schema', 'create_analytics_tables'));

// Schedule cleanup
add_action('rphub_daily_cleanup', array('ReplantaHub_Analytics_Schema', 'cleanup_old_analytics_data'));
