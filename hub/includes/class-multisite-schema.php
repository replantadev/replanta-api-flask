<?php
/**
 * Multi-site Management Schema
 * Database schema for multi-site management functionality
 * 
 * @package ReplantaHub
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPHUB_Multisite_Schema {
    
    public function __construct() {
        add_action('init', array($this, 'create_tables'));
    }
    
    /**
     * Create multisite management tables
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Site Groups table
        $table_name = $wpdb->prefix . 'rphub_site_groups';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            group_name varchar(255) NOT NULL,
            group_description text,
            group_config longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            created_by bigint(20) unsigned,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            status varchar(20) DEFAULT 'active',
            PRIMARY KEY (id),
            INDEX idx_group_name (group_name),
            INDEX idx_status (status),
            INDEX idx_created_by (created_by)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Site Group Members table
        $table_name = $wpdb->prefix . 'rphub_site_group_members';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            group_id bigint(20) unsigned NOT NULL,
            site_id bigint(20) unsigned NOT NULL,
            assigned_at datetime DEFAULT CURRENT_TIMESTAMP,
            assigned_by bigint(20) unsigned,
            PRIMARY KEY (id),
            UNIQUE KEY unique_group_site (group_id, site_id),
            INDEX idx_group_id (group_id),
            INDEX idx_site_id (site_id),
            INDEX idx_assigned_by (assigned_by)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Bulk Action Logs table
        $table_name = $wpdb->prefix . 'rphub_bulk_action_logs';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            site_id bigint(20) unsigned NOT NULL,
            action_type varchar(100) NOT NULL,
            action_config longtext,
            result longtext,
            executed_by bigint(20) unsigned,
            executed_at datetime DEFAULT CURRENT_TIMESTAMP,
            execution_time float DEFAULT 0,
            status varchar(20) DEFAULT 'completed',
            PRIMARY KEY (id),
            INDEX idx_site_id (site_id),
            INDEX idx_action_type (action_type),
            INDEX idx_executed_by (executed_by),
            INDEX idx_executed_at (executed_at),
            INDEX idx_status (status)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Sync Errors table
        $table_name = $wpdb->prefix . 'rphub_sync_errors';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            site_id bigint(20) unsigned NOT NULL,
            error_message text NOT NULL,
            error_code varchar(50),
            error_context longtext,
            error_time datetime DEFAULT CURRENT_TIMESTAMP,
            resolved_at datetime NULL,
            resolved_by bigint(20) unsigned NULL,
            resolution_notes text,
            PRIMARY KEY (id),
            INDEX idx_site_id (site_id),
            INDEX idx_error_time (error_time),
            INDEX idx_resolved_at (resolved_at),
            INDEX idx_error_code (error_code)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Network Reports table
        $table_name = $wpdb->prefix . 'rphub_network_reports';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            report_type varchar(50) NOT NULL,
            report_data longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            created_by bigint(20) unsigned,
            report_period_start datetime,
            report_period_end datetime,
            status varchar(20) DEFAULT 'generated',
            PRIMARY KEY (id),
            INDEX idx_report_type (report_type),
            INDEX idx_created_at (created_at),
            INDEX idx_report_period (report_period_start, report_period_end),
            INDEX idx_status (status)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Configuration Templates table
        $table_name = $wpdb->prefix . 'rphub_config_templates';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            template_name varchar(255) NOT NULL,
            template_description text,
            config_type varchar(100) NOT NULL,
            config_data longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            created_by bigint(20) unsigned,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_default tinyint(1) DEFAULT 0,
            status varchar(20) DEFAULT 'active',
            PRIMARY KEY (id),
            INDEX idx_template_name (template_name),
            INDEX idx_config_type (config_type),
            INDEX idx_created_by (created_by),
            INDEX idx_status (status),
            INDEX idx_is_default (is_default)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Site Dependencies table
        $table_name = $wpdb->prefix . 'rphub_site_dependencies';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            parent_site_id bigint(20) unsigned NOT NULL,
            dependent_site_id bigint(20) unsigned NOT NULL,
            dependency_type varchar(50) NOT NULL,
            dependency_config longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            status varchar(20) DEFAULT 'active',
            PRIMARY KEY (id),
            UNIQUE KEY unique_dependency (parent_site_id, dependent_site_id, dependency_type),
            INDEX idx_parent_site (parent_site_id),
            INDEX idx_dependent_site (dependent_site_id),
            INDEX idx_dependency_type (dependency_type),
            INDEX idx_status (status)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Deployment History table
        $table_name = $wpdb->prefix . 'rphub_deployment_history';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            deployment_name varchar(255) NOT NULL,
            target_sites longtext NOT NULL,
            deployment_config longtext NOT NULL,
            deployment_results longtext,
            started_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime NULL,
            deployed_by bigint(20) unsigned,
            status varchar(20) DEFAULT 'pending',
            success_count int DEFAULT 0,
            error_count int DEFAULT 0,
            total_sites int DEFAULT 0,
            PRIMARY KEY (id),
            INDEX idx_deployment_name (deployment_name),
            INDEX idx_deployed_by (deployed_by),
            INDEX idx_started_at (started_at),
            INDEX idx_status (status)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Update database version
        update_option('rphub_multisite_db_version', '1.0.0');
    }
    
    /**
     * Drop multisite tables (for uninstallation)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            'rphub_site_groups',
            'rphub_site_group_members',
            'rphub_bulk_action_logs',
            'rphub_sync_errors',
            'rphub_network_reports',
            'rphub_config_templates',
            'rphub_site_dependencies',
            'rphub_deployment_history'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
        }
        
        delete_option('rphub_multisite_db_version');
    }
    
    /**
     * Check if tables exist and create if missing
     */
    public static function check_tables() {
        global $wpdb;
        
        $required_tables = array(
            'rphub_site_groups',
            'rphub_site_group_members',
            'rphub_bulk_action_logs',
            'rphub_sync_errors',
            'rphub_network_reports',
            'rphub_config_templates',
            'rphub_site_dependencies',
            'rphub_deployment_history'
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
    
    /**
     * Add default configuration templates
     */
    public static function add_default_templates() {
        global $wpdb;
        
        $templates = array(
            array(
                'template_name' => 'Basic Security Configuration',
                'template_description' => 'Standard security settings for WordPress sites',
                'config_type' => 'security',
                'config_data' => wp_json_encode(array(
                    'disable_file_editing' => true,
                    'hide_wp_version' => true,
                    'disable_xmlrpc' => true,
                    'limit_login_attempts' => true,
                    'force_ssl_admin' => true,
                    'security_headers' => true
                )),
                'is_default' => 1
            ),
            array(
                'template_name' => 'Performance Optimization',
                'template_description' => 'Standard performance optimization settings',
                'config_type' => 'performance',
                'config_data' => wp_json_encode(array(
                    'enable_caching' => true,
                    'minify_css' => true,
                    'minify_js' => true,
                    'optimize_images' => true,
                    'enable_gzip' => true,
                    'browser_caching' => true
                )),
                'is_default' => 1
            ),
            array(
                'template_name' => 'SEO Configuration',
                'template_description' => 'Basic SEO settings and optimizations',
                'config_type' => 'seo',
                'config_data' => wp_json_encode(array(
                    'enable_sitemaps' => true,
                    'meta_descriptions' => true,
                    'clean_urls' => true,
                    'social_meta' => true,
                    'schema_markup' => true,
                    'robots_txt' => true
                )),
                'is_default' => 1
            ),
            array(
                'template_name' => 'Backup Configuration',
                'template_description' => 'Automated backup settings',
                'config_type' => 'backup',
                'config_data' => wp_json_encode(array(
                    'backup_frequency' => 'daily',
                    'backup_retention' => 30,
                    'include_database' => true,
                    'include_files' => true,
                    'include_uploads' => true,
                    'remote_storage' => false
                )),
                'is_default' => 1
            ),
            array(
                'template_name' => 'Maintenance Mode',
                'template_description' => 'Maintenance mode configuration',
                'config_type' => 'maintenance',
                'config_data' => wp_json_encode(array(
                    'enable_maintenance' => false,
                    'maintenance_message' => 'Site is under maintenance. Please check back soon.',
                    'allowed_ips' => array(),
                    'show_countdown' => false,
                    'maintenance_page' => 'default'
                )),
                'is_default' => 1
            )
        );
        
        foreach ($templates as $template) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}rphub_config_templates 
                 WHERE template_name = %s AND config_type = %s",
                $template['template_name'],
                $template['config_type']
            ));
            
            if (!$existing) {
                $template['created_at'] = current_time('mysql');
                $template['created_by'] = get_current_user_id();
                
                $wpdb->insert(
                    $wpdb->prefix . 'rphub_config_templates',
                    $template,
                    array('%s', '%s', '%s', '%s', '%s', '%d', '%d')
                );
            }
        }
    }
}
