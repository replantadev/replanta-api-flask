<?php
/**
 * Database management for Replanta Hub
 * 
 * Handles all database operations, table creation, and data management
 * for the comprehensive site management system
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ReplantaHub_Database {
    
    private $version = '2.4';
    
    public function __construct() {
        add_action('admin_init', array($this, 'check_database_version'));
    }
    
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Temporarily disable foreign key checks to avoid constraint issues
        $wpdb->query('SET FOREIGN_KEY_CHECKS = 0');
        
        // Create all required tables
        $this->create_sites_table($charset_collate);
        $this->create_backups_table($charset_collate);
        $this->create_maintenance_logs_table($charset_collate);
        $this->create_notifications_table($charset_collate);
        $this->create_activities_table($charset_collate);
        
        // New tables for advanced integrations
        $this->create_wptoolkit_vulnerabilities_table($charset_collate);
        $this->create_wptoolkit_updates_table($charset_collate);
        $this->create_backuply_jobs_table($charset_collate);
        $this->create_pagespeed_reports_table($charset_collate);
        $this->create_cloudflare_analytics_table($charset_collate);
        $this->create_cloudflare_security_events_table($charset_collate);
        $this->create_comprehensive_reports_table($charset_collate);
        $this->create_automation_tasks_table($charset_collate);
        $this->create_site_health_table($charset_collate);
        $this->create_performance_metrics_table($charset_collate);
        $this->create_site_meta_table($charset_collate);
        
        // Re-enable foreign key checks
        $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');
        
        // Update database version
        update_option('replanta_hub_db_version', $this->version);
    }
    
    private function create_sites_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rphub_sites';
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            url varchar(500) NOT NULL,
            domain varchar(255) NOT NULL,
            token varchar(255),
            plan varchar(50) DEFAULT 'semilla',
            cpanel_username varchar(100),
            whm_server varchar(50) DEFAULT '',
            cloudflare_token varchar(255),
            automation_enabled tinyint(1) DEFAULT 1,
            monitoring_enabled tinyint(1) DEFAULT 1,
            backup_enabled tinyint(1) DEFAULT 1,
            performance_monitoring tinyint(1) DEFAULT 1,
            security_monitoring tinyint(1) DEFAULT 1,
            status varchar(20) DEFAULT 'active',
            health_score int(3) DEFAULT 0,
            performance_score int(3) DEFAULT 0,
            security_score int(3) DEFAULT 0,
            last_check datetime,
            last_backup datetime,
            last_update datetime,
            notes text,
            ga4_property_id varchar(20) NOT NULL DEFAULT '',
            sc_domain varchar(255) NOT NULL DEFAULT '',
            client_email varchar(255) NOT NULL DEFAULT '',
            alert_email varchar(255) NOT NULL DEFAULT '',
            whm_account varchar(100) NOT NULL DEFAULT '',
            settings longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_domain (domain),
            KEY idx_status (status),
            KEY idx_health_score (health_score),
            KEY idx_whm_server (whm_server)
        ) $charset_collate;";
        
        $this->execute_sql($sql);
    }
    
    private function create_backups_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rphub_backups';
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            site_id int(11) NOT NULL,
            backup_type varchar(50) DEFAULT 'full',
            file_path varchar(500),
            file_size bigint,
            backup_method varchar(50) DEFAULT 'backuply',
            status varchar(20) DEFAULT 'pending',
            backup_id varchar(100),
            download_url varchar(500),
            expiry_date datetime,
            error_message text,
            started_at datetime,
            completed_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_site_id (site_id),
            KEY idx_status (status),
            KEY idx_backup_type (backup_type)
        ) $charset_collate;";
        
        $this->execute_sql($sql);
    }
    
    private function create_maintenance_logs_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rphub_maintenance_logs';
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            site_id int(11) NOT NULL,
            task_type varchar(100) NOT NULL,
            task_description text,
            status varchar(20) DEFAULT 'pending',
            priority varchar(20) DEFAULT 'medium',
            result text,
            error_message text,
            execution_time int(11),
            scheduled_at datetime,
            started_at datetime,
            completed_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_site_id (site_id),
            KEY idx_task_type (task_type),
            KEY idx_status (status)
        ) $charset_collate;";
        
        $this->execute_sql($sql);
    }
    
    private function create_notifications_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rphub_notifications';
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            site_id int(11),
            type varchar(50) NOT NULL,
            severity varchar(20) DEFAULT 'info',
            title varchar(255) NOT NULL,
            message text NOT NULL,
            action_required tinyint(1) DEFAULT 0,
            status varchar(20) DEFAULT 'unread',
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            read_at datetime,
            PRIMARY KEY (id),
            KEY idx_site_id (site_id),
            KEY idx_type (type),
            KEY idx_severity (severity),
            KEY idx_status (status)
        ) $charset_collate;";
        
        $this->execute_sql($sql);
    }
    
    private function create_activities_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rphub_activities';
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            site_id int(11),
            user_id int(11),
            type varchar(50) NOT NULL,
            action varchar(100) NOT NULL,
            description text NOT NULL,
            metadata longtext,
            ip_address varchar(45),
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_site_id (site_id),
            KEY idx_user_id (user_id),
            KEY idx_type (type),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        
        $this->execute_sql($sql);
    }
    
    private function create_wptoolkit_vulnerabilities_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rphub_wptoolkit_vulnerabilities';
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            site_id int(11) NOT NULL,
            vulnerability_id varchar(100),
            component_type varchar(50),
            component_name varchar(255),
            vulnerability_type varchar(100),
            severity varchar(20),
            description text,
            affected_version varchar(50),
            fixed_version varchar(50),
            cvss_score decimal(3,1),
            cve_id varchar(50),
            status varchar(20) DEFAULT 'open',
            detected_at datetime,
            fixed_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_site_id (site_id),
            KEY idx_severity (severity),
            KEY idx_status (status)
        ) $charset_collate;";
        
        $this->execute_sql($sql);
    }
    
    private function create_wptoolkit_updates_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rphub_wptoolkit_updates';
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            site_id int(11) NOT NULL,
            component_type varchar(50),
            component_name varchar(255),
            current_version varchar(50),
            available_version varchar(50),
            update_type varchar(50),
            priority varchar(20),
            auto_update_enabled tinyint(1) DEFAULT 0,
            status varchar(20) DEFAULT 'pending',
            update_notes text,
            scheduled_at datetime,
            updated_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_site_id (site_id),
            KEY idx_status (status),
            KEY idx_priority (priority)
        ) $charset_collate;";
        
        $this->execute_sql($sql);
    }
    
    private function create_backuply_jobs_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rphub_backuply_jobs';
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            site_id int(11) NOT NULL,
            job_id varchar(100),
            job_type varchar(50),
            schedule_type varchar(50),
            frequency varchar(50),
            retention_days int(11),
            backup_destinations text,
            compression_level varchar(20),
            encryption_enabled tinyint(1) DEFAULT 0,
            bandwidth_limit int(11),
            status varchar(20) DEFAULT 'active',
            last_run datetime,
            next_run datetime,
            success_count int(11) DEFAULT 0,
            failure_count int(11) DEFAULT 0,
            settings longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_site_id (site_id),
            KEY idx_status (status),
            KEY idx_next_run (next_run)
        ) $charset_collate;";
        
        $this->execute_sql($sql);
    }
    
    private function create_pagespeed_reports_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rphub_pagespeed_reports';
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            site_id int(11) NOT NULL,
            url varchar(500) NOT NULL,
            strategy varchar(20) DEFAULT 'desktop',
            performance_score int(3),
            fcp_score decimal(5,2),
            lcp_score decimal(5,2),
            cls_score decimal(5,3),
            fid_score decimal(5,2),
            ttfb_score decimal(5,2),
            opportunities longtext,
            diagnostics longtext,
            lab_data longtext,
            field_data longtext,
            screenshot_url varchar(500),
            report_url varchar(500),
            analyzed_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_site_id (site_id),
            KEY idx_strategy (strategy),
            KEY idx_performance_score (performance_score),
            KEY idx_analyzed_at (analyzed_at)
        ) $charset_collate;";
        
        $this->execute_sql($sql);
    }
    
    private function create_cloudflare_analytics_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rphub_cloudflare_analytics';
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            site_id int(11) NOT NULL,
            zone_id varchar(100),
            date_analyzed date,
            total_requests bigint DEFAULT 0,
            cached_requests bigint DEFAULT 0,
            uncached_requests bigint DEFAULT 0,
            bandwidth_total bigint DEFAULT 0,
            bandwidth_cached bigint DEFAULT 0,
            bandwidth_uncached bigint DEFAULT 0,
            threats_blocked int(11) DEFAULT 0,
            unique_visitors int(11) DEFAULT 0,
            page_views int(11) DEFAULT 0,
            cache_hit_ratio decimal(5,2),
            response_times longtext,
            status_codes longtext,
            countries longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_site_id (site_id),
            KEY idx_date_analyzed (date_analyzed),
            KEY idx_zone_id (zone_id)
        ) $charset_collate;";
        
        $this->execute_sql($sql);
    }
    
    private function create_cloudflare_security_events_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rphub_cloudflare_security_events';
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            site_id int(11) NOT NULL,
            zone_id varchar(100),
            event_id varchar(100),
            event_type varchar(100),
            action varchar(50),
            ip_address varchar(45),
            country varchar(5),
            user_agent text,
            url varchar(500),
            referer varchar(500),
            threat_score int(3),
            rule_id varchar(100),
            rule_message text,
            occurred_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_site_id (site_id),
            KEY idx_event_type (event_type),
            KEY idx_ip_address (ip_address),
            KEY idx_occurred_at (occurred_at)
        ) $charset_collate;";
        
        $this->execute_sql($sql);
    }
    
    private function create_comprehensive_reports_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rphub_comprehensive_reports';
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            report_type varchar(50) NOT NULL,
            site_id int(11),
            report_period varchar(50),
            start_date datetime,
            end_date datetime,
            report_data longtext,
            summary longtext,
            pdf_path varchar(500),
            html_path varchar(500),
            status varchar(20) DEFAULT 'generating',
            file_size bigint,
            generated_by int(11),
            generated_at datetime,
            expires_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_site_id (site_id),
            KEY idx_report_type (report_type),
            KEY idx_status (status),
            KEY idx_generated_at (generated_at)
        ) $charset_collate;";
        
        $this->execute_sql($sql);
    }
    
    private function create_automation_tasks_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rphub_automation_tasks';
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            site_id int(11),
            task_name varchar(255) NOT NULL,
            task_type varchar(100) NOT NULL,
            automation_level varchar(50) DEFAULT 'basic',
            conditions longtext,
            actions longtext,
            priority int(3) DEFAULT 5,
            enabled tinyint(1) DEFAULT 1,
            schedule_type varchar(50),
            schedule_config longtext,
            last_run datetime,
            next_run datetime,
            run_count int(11) DEFAULT 0,
            success_count int(11) DEFAULT 0,
            failure_count int(11) DEFAULT 0,
            average_execution_time int(11),
            settings longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_site_id (site_id),
            KEY idx_task_type (task_type),
            KEY idx_enabled (enabled),
            KEY idx_next_run (next_run)
        ) $charset_collate;";
        
        $this->execute_sql($sql);
    }
    
    private function create_site_health_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rphub_site_health';
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            site_id int(11) NOT NULL,
            check_date datetime,
            overall_score int(3),
            security_score int(3),
            performance_score int(3),
            backup_score int(3),
            uptime_score int(3),
            ssl_score int(3),
            critical_issues int(11) DEFAULT 0,
            warning_issues int(11) DEFAULT 0,
            info_issues int(11) DEFAULT 0,
            recommendations longtext,
            detailed_results longtext,
            check_duration int(11),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_site_id (site_id),
            KEY idx_check_date (check_date),
            KEY idx_overall_score (overall_score)
        ) $charset_collate;";
        
        $this->execute_sql($sql);
    }
    
    private function execute_sql($sql) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function check_database_version() {
        $installed_version = get_option('replanta_hub_db_version', '0');
        
        if (version_compare($installed_version, $this->version, '<')) {
            $this->upgrade_database($installed_version);
        }
    }
    
    private function upgrade_database($current_version) {
        if (version_compare($current_version, '2.0', '<')) {
            $this->upgrade_to_v2();
        }
        if (version_compare($current_version, '2.1', '<')) {
            $this->upgrade_to_v2_1();
        }
        if (version_compare($current_version, '2.2', '<')) {
            $this->upgrade_to_v2_2();
        }
        if (version_compare($current_version, '2.3', '<')) {
            $this->upgrade_to_v2_3();
        }
        if (version_compare($current_version, '2.4', '<')) {
            $this->upgrade_to_v2_4();
        }
    }
    
    private function upgrade_to_v2() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Add new columns to existing sites table if they don't exist
        $sites_table = $wpdb->prefix . 'rphub_sites';
        
        $columns_to_add = array(
            'cloudflare_token' => "ALTER TABLE $sites_table ADD COLUMN cloudflare_token varchar(255) AFTER wp_admin_password",
            'automation_enabled' => "ALTER TABLE $sites_table ADD COLUMN automation_enabled tinyint(1) DEFAULT 1 AFTER cloudflare_token",
            'monitoring_enabled' => "ALTER TABLE $sites_table ADD COLUMN monitoring_enabled tinyint(1) DEFAULT 1 AFTER automation_enabled",
            'backup_enabled' => "ALTER TABLE $sites_table ADD COLUMN backup_enabled tinyint(1) DEFAULT 1 AFTER monitoring_enabled",
            'performance_monitoring' => "ALTER TABLE $sites_table ADD COLUMN performance_monitoring tinyint(1) DEFAULT 1 AFTER backup_enabled",
            'security_monitoring' => "ALTER TABLE $sites_table ADD COLUMN security_monitoring tinyint(1) DEFAULT 1 AFTER performance_monitoring",
            'health_score' => "ALTER TABLE $sites_table ADD COLUMN health_score int(3) DEFAULT 0 AFTER status",
            'performance_score' => "ALTER TABLE $sites_table ADD COLUMN performance_score int(3) DEFAULT 0 AFTER health_score",
            'security_score' => "ALTER TABLE $sites_table ADD COLUMN security_score int(3) DEFAULT 0 AFTER performance_score",
            'settings' => "ALTER TABLE $sites_table ADD COLUMN settings longtext AFTER notes"
        );
        
        foreach ($columns_to_add as $column => $sql) {
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $sites_table LIKE '$column'");
            if (empty($column_exists)) {
                $wpdb->query($sql);
            }
        }
        
        // Create new tables for v2.0
        $this->create_wptoolkit_vulnerabilities_table($wpdb->get_charset_collate());
        $this->create_wptoolkit_updates_table($wpdb->get_charset_collate());
        $this->create_backuply_jobs_table($wpdb->get_charset_collate());
        $this->create_pagespeed_reports_table($wpdb->get_charset_collate());
        $this->create_cloudflare_analytics_table($wpdb->get_charset_collate());
        $this->create_cloudflare_security_events_table($wpdb->get_charset_collate());
        $this->create_comprehensive_reports_table($wpdb->get_charset_collate());
        $this->create_automation_tasks_table($wpdb->get_charset_collate());
        $this->create_site_health_table($wpdb->get_charset_collate());
        
        // Update version
        update_option('replanta_hub_db_version', '2.0');
    }
    
    /**
     * v2.1: Security hardening — remove plaintext password columns
     */
    private function upgrade_to_v2_1() {
        global $wpdb;
        $sites_table = $wpdb->prefix . 'rphub_sites';
        
        // Drop plaintext password columns (security critical)
        $columns_to_drop = ['cpanel_password', 'wp_admin_password', 'wp_admin_username'];
        foreach ($columns_to_drop as $column) {
            $exists = $wpdb->get_results("SHOW COLUMNS FROM $sites_table LIKE '$column'");
            if (!empty($exists)) {
                $wpdb->query("ALTER TABLE $sites_table DROP COLUMN $column");
            }
        }
        
        // Add token/plan columns if missing (may already exist from initial schema)
        $columns_to_add = [
            'token' => "ALTER TABLE $sites_table ADD COLUMN token varchar(255) AFTER domain",
            'plan' => "ALTER TABLE $sites_table ADD COLUMN plan varchar(50) DEFAULT 'semilla' AFTER token",
        ];
        foreach ($columns_to_add as $column => $sql) {
            $exists = $wpdb->get_results("SHOW COLUMNS FROM $sites_table LIKE '$column'");
            if (empty($exists)) {
                $wpdb->query($sql);
            }
        }
        
        // Create site_meta table for advanced integration data
        $this->create_site_meta_table($wpdb->get_charset_collate());

        update_option('replanta_hub_db_version', '2.1');
    }

    /**
     * v2.2: Per-site analytics config + contact fields.
     *
     * ga4_property_id  — client's own GA4 numeric property (e.g. "123456789")
     * sc_domain        — client's Search Console property (e.g. "https://example.com")
     * client_email     — recipient for monthly reports
     * alert_email      — ops/admin email for downtime alerts
     * whm_account      — canonical column name (was cpanel_username)
     */
    private function upgrade_to_v2_2() {
        global $wpdb;
        $t = $wpdb->prefix . 'rphub_sites';

        $columns = [
            'ga4_property_id' => "ALTER TABLE $t ADD COLUMN ga4_property_id varchar(20)  NOT NULL DEFAULT '' AFTER notes",
            'sc_domain'       => "ALTER TABLE $t ADD COLUMN sc_domain       varchar(255) NOT NULL DEFAULT '' AFTER ga4_property_id",
            'client_email'    => "ALTER TABLE $t ADD COLUMN client_email    varchar(255) NOT NULL DEFAULT '' AFTER sc_domain",
            'alert_email'     => "ALTER TABLE $t ADD COLUMN alert_email     varchar(255) NOT NULL DEFAULT '' AFTER client_email",
            'whm_account'     => "ALTER TABLE $t ADD COLUMN whm_account     varchar(100) NOT NULL DEFAULT '' AFTER alert_email",
        ];

        foreach ($columns as $col => $sql) {
            if (empty($wpdb->get_results("SHOW COLUMNS FROM $t LIKE '$col'"))) {
                $wpdb->query($sql);
            }
        }

        update_option('replanta_hub_db_version', '2.2');
    }

    /**
     * v2.3: Unification of rphub_managed_sites (option) into wp_rphub_sites (table).
     *
     * Adds columns previously stored only in the option, then migrates the option
     * into the table. After this migration the table is the single source of truth;
     * RPHUB_Sites::all() reads from it. The option is kept untouched in this
     * release as a safety net and will be removed in the next.
     *
     * New columns:
     *  care_url        — Care plugin home_url (for push commands back to client)
     *  care_token      — Care plugin token (for outbound Hub→Care REST auth)
     *  registered_at   — when the site auto-registered via Care heartbeat
     *  last_seen       — last successful heartbeat from Care
     *  sc_site_url     — normalized Search Console property (mirrors sc_domain but with scheme)
     *  cf_zone_id      — Cloudflare zone id (per-site integration mapping)
     *  integrations    — JSON blob with all integration mappings (forward-compatible)
     *  source          — how the row was created: manual | care_heartbeat | wizard
     */
    private function upgrade_to_v2_3() {
        global $wpdb;
        $t = $wpdb->prefix . 'rphub_sites';

        $columns = [
            'care_url'      => "ALTER TABLE $t ADD COLUMN care_url      varchar(500) NOT NULL DEFAULT '' AFTER url",
            'care_token'    => "ALTER TABLE $t ADD COLUMN care_token    varchar(255) NOT NULL DEFAULT '' AFTER care_url",
            'registered_at' => "ALTER TABLE $t ADD COLUMN registered_at datetime     NULL DEFAULT NULL AFTER created_at",
            'last_seen'     => "ALTER TABLE $t ADD COLUMN last_seen     datetime     NULL DEFAULT NULL AFTER registered_at",
            'sc_site_url'   => "ALTER TABLE $t ADD COLUMN sc_site_url   varchar(255) NOT NULL DEFAULT '' AFTER sc_domain",
            'cf_zone_id'    => "ALTER TABLE $t ADD COLUMN cf_zone_id    varchar(64)  NOT NULL DEFAULT '' AFTER sc_site_url",
            'integrations'  => "ALTER TABLE $t ADD COLUMN integrations  longtext     NULL DEFAULT NULL AFTER settings",
            'source'        => "ALTER TABLE $t ADD COLUMN source        varchar(32)  NOT NULL DEFAULT 'manual' AFTER status",
        ];

        foreach ($columns as $col => $sql) {
            if (empty($wpdb->get_results("SHOW COLUMNS FROM $t LIKE '$col'"))) {
                $wpdb->query($sql);
            }
        }

        // Index on token + care_token for the integration lookups.
        $existing_indexes = $wpdb->get_results("SHOW INDEX FROM $t WHERE Key_name = 'idx_token'");
        if (empty($existing_indexes)) {
            $wpdb->query("ALTER TABLE $t ADD INDEX idx_token (token)");
        }

        // Migrate option → table.
        $this->migrate_managed_sites_option_to_table();

        update_option('replanta_hub_db_version', '2.3');
    }

    /**
     * v2.4: Per-site domain_type (internal|external) and whm_server (server id).
     * Lets the Sites modal branch UI between hosted-by-us (WHM dropdown enabled)
     * and external (WHM section hidden).
     */
    private function upgrade_to_v2_4() {
        global $wpdb;
        $t = $wpdb->prefix . 'rphub_sites';

        $columns = [
            'domain_type' => "ALTER TABLE $t ADD COLUMN domain_type varchar(16) NOT NULL DEFAULT 'external' AFTER source",
            'whm_server'  => "ALTER TABLE $t ADD COLUMN whm_server  varchar(50) NOT NULL DEFAULT '' AFTER domain_type",
        ];
        foreach ($columns as $col => $sql) {
            if (empty($wpdb->get_results("SHOW COLUMNS FROM $t LIKE '$col'"))) {
                $wpdb->query($sql);
            }
        }

        update_option('replanta_hub_db_version', '2.4');
    }

    /**
     * One-shot migration of rphub_managed_sites option into wp_rphub_sites table.
     * Idempotent: existing rows (matched by domain) are updated, missing rows inserted.
     * Per user decision: table fields win; only fields missing in table are filled from option.
     */
    private function migrate_managed_sites_option_to_table() {
        global $wpdb;
        $t = $wpdb->prefix . 'rphub_sites';
        $managed = get_option('rphub_managed_sites', []);
        if (!is_array($managed) || empty($managed)) return;

        $migrated = 0;
        foreach ($managed as $domain_key => $item) {
            if (!is_array($item)) continue;
            $domain = sanitize_text_field($item['domain'] ?? $domain_key);
            if (empty($domain)) continue;

            $url       = esc_url_raw($item['url']   ?? ('https://' . $domain));
            $token     = sanitize_text_field($item['token']     ?? '');
            $care_url  = esc_url_raw($item['care_url']  ?? $url);
            $care_tok  = sanitize_text_field($item['care_token'] ?? '');
            $plan      = sanitize_text_field($item['plan']      ?? 'semilla');
            $status    = sanitize_text_field($item['status']    ?? 'active');
            $reg_at    = sanitize_text_field($item['registered_at'] ?? current_time('mysql'));
            $last_seen = sanitize_text_field($item['last_seen']     ?? current_time('mysql'));
            $integrations_arr = is_array($item['integrations'] ?? null) ? $item['integrations'] : [];

            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT id, token, care_url, care_token, plan, registered_at, integrations FROM $t WHERE domain = %s LIMIT 1",
                $domain
            ));

            $row = [
                'name'          => $item['name'] ?? $domain,
                'url'           => $url,
                'domain'        => $domain,
                'care_url'      => $care_url,
                'care_token'    => $care_tok,
                'status'        => $status,
                'last_seen'     => $last_seen,
                'source'        => 'care_heartbeat',
                'integrations'  => wp_json_encode($integrations_arr),
                'ga4_property_id' => $integrations_arr['ga4_property_id'] ?? '',
                'sc_site_url'     => $integrations_arr['sc_site_url']     ?? '',
                'cf_zone_id'      => $integrations_arr['cf_zone_id']      ?? '',
            ];

            if ($existing) {
                // Table wins on non-empty fields; only fill blanks from option.
                $update = [];
                if (empty($existing->token) && !empty($token))         $update['token']      = $token;
                if (empty($existing->care_url) && !empty($care_url))   $update['care_url']   = $care_url;
                if (empty($existing->care_token) && !empty($care_tok)) $update['care_token'] = $care_tok;
                if (empty($existing->plan) && !empty($plan))           $update['plan']       = $plan;
                if (empty($existing->registered_at) && !empty($reg_at))$update['registered_at'] = $reg_at;
                if (empty($existing->integrations)) {
                    $update['integrations']    = $row['integrations'];
                    $update['ga4_property_id'] = $row['ga4_property_id'];
                    $update['sc_site_url']     = $row['sc_site_url'];
                    $update['cf_zone_id']      = $row['cf_zone_id'];
                }
                $update['last_seen'] = $last_seen;
                if (!empty($update)) {
                    $wpdb->update($t, $update, ['id' => $existing->id]);
                    $migrated++;
                }
            } else {
                $row['token']         = $token;
                $row['plan']          = $plan;
                $row['registered_at'] = $reg_at;
                $row['created_at']    = current_time('mysql');
                $wpdb->insert($t, $row);
                $migrated++;
            }
        }

        update_option('rphub_managed_sites_migrated_at', current_time('mysql'), false);
        update_option('rphub_managed_sites_migrated_count', $migrated, false);
    }

    // Utility methods for data operations (get_site / get_all_sites are static, see below)
    
    public function update_site_scores($site_id, $scores) {
        global $wpdb;
        $table = $wpdb->prefix . 'rphub_sites';
        
        $update_data = array();
        
        if (isset($scores['health_score'])) {
            $update_data['health_score'] = $scores['health_score'];
        }
        
        if (isset($scores['performance_score'])) {
            $update_data['performance_score'] = $scores['performance_score'];
        }
        
        if (isset($scores['security_score'])) {
            $update_data['security_score'] = $scores['security_score'];
        }
        
        if (!empty($update_data)) {
            $update_data['updated_at'] = current_time('mysql');
            
            return $wpdb->update(
                $table,
                $update_data,
                array('id' => $site_id),
                array('%d', '%d', '%d', '%s'),
                array('%d')
            );
        }
        
        return false;
    }
    
    public function log_activity($site_id, $type, $action, $description, $metadata = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'rphub_activities';
        
        return $wpdb->insert(
            $table,
            array(
                'site_id' => $site_id,
                'user_id' => get_current_user_id(),
                'type' => $type,
                'action' => $action,
                'description' => $description,
                'metadata' => $metadata ? json_encode($metadata) : null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    public function create_notification($site_id, $type, $severity, $title, $message, $metadata = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'rphub_notifications';
        
        return $wpdb->insert(
            $table,
            array(
                'site_id' => $site_id,
                'type' => $type,
                'severity' => $severity,
                'title' => $title,
                'message' => $message,
                'metadata' => $metadata ? json_encode($metadata) : null,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    public function cleanup_old_data($days = 90) {
        global $wpdb;
        
        $date_threshold = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        // Clean up old activities
        $activities_table = $wpdb->prefix . 'rphub_activities';
        $wpdb->query($wpdb->prepare("DELETE FROM $activities_table WHERE created_at < %s", $date_threshold));
        
        // Clean up old notifications
        $notifications_table = $wpdb->prefix . 'rphub_notifications';
        $wpdb->query($wpdb->prepare("DELETE FROM $notifications_table WHERE status = 'read' AND read_at < %s", $date_threshold));
        
        // Clean up old reports
        $reports_table = $wpdb->prefix . 'rphub_comprehensive_reports';
        $wpdb->query($wpdb->prepare("DELETE FROM $reports_table WHERE expires_at < %s", current_time('mysql')));
        
        // Clean up old PageSpeed reports (keep last 30 days)
        $pagespeed_table = $wpdb->prefix . 'rphub_pagespeed_reports';
        $pagespeed_threshold = date('Y-m-d H:i:s', strtotime('-30 days'));
        $wpdb->query($wpdb->prepare("DELETE FROM $pagespeed_table WHERE analyzed_at < %s", $pagespeed_threshold));
        
        // Clean up old security logs (keep configurable days)
        $security_table = $wpdb->prefix . 'rphub_security_logs';
        if ($wpdb->get_var("SHOW TABLES LIKE '$security_table'") === $security_table) {
            $wpdb->query($wpdb->prepare("DELETE FROM $security_table WHERE created_at < %s", $date_threshold));
        }
    }
    
    private function create_performance_metrics_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rphub_performance_metrics';
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            site_id int(11) NOT NULL,
            metric_type varchar(50) NOT NULL,
            metric_value decimal(10,2),
            page_url varchar(500),
            load_time decimal(8,3),
            response_time decimal(8,3),
            ttfb decimal(8,3),
            page_size int(11),
            requests_count int(11),
            performance_score int(3),
            largest_contentful_paint decimal(8,3),
            first_input_delay decimal(8,3),
            cumulative_layout_shift decimal(8,3),
            first_contentful_paint decimal(8,3),
            time_to_interactive decimal(8,3),
            speed_index decimal(8,3),
            total_blocking_time decimal(8,3),
            collected_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_site_id (site_id),
            KEY idx_metric_type (metric_type),
            KEY idx_collected_at (collected_at)
        ) $charset_collate;";
        
        $this->execute_sql($sql);
    }
    
    /**
     * Site meta table — replaces wp_postmeta for site-related data.
     * Used by advanced integration classes (security, cloudflare, pagespeed, etc.)
     */
    private function create_site_meta_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rphub_site_meta';
        
        $sql = "CREATE TABLE $table_name (
            meta_id bigint(20) NOT NULL AUTO_INCREMENT,
            site_id int(11) NOT NULL,
            meta_key varchar(255) NOT NULL,
            meta_value longtext,
            PRIMARY KEY (meta_id),
            KEY idx_site_id (site_id),
            KEY idx_meta_key (meta_key(191)),
            KEY idx_site_key (site_id, meta_key(191))
        ) $charset_collate;";
        
        $this->execute_sql($sql);
    }
    
    /**
     * Get a site row from rphub_sites by ID.
     */
    public static function get_site($site_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rphub_sites';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $site_id));
    }

    /**
     * Get all active sites from rphub_sites.
     */
    public static function get_all_sites($status = 'active') {
        global $wpdb;
        $table = $wpdb->prefix . 'rphub_sites';
        if ($status) {
            return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE status = %s ORDER BY name ASC", $status));
        }
        return $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");
    }

    /**
     * Site meta CRUD helpers — drop-in replacement for get_post_meta/update_post_meta
     */
    public static function get_site_meta($site_id, $meta_key, $single = true) {
        global $wpdb;
        $table = $wpdb->prefix . 'rphub_site_meta';
        
        if ($single) {
            $value = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM $table WHERE site_id = %d AND meta_key = %s ORDER BY meta_id DESC LIMIT 1",
                $site_id, $meta_key
            ));
            return $value !== null ? maybe_unserialize($value) : '';
        }
        
        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT meta_value FROM $table WHERE site_id = %d AND meta_key = %s",
            $site_id, $meta_key
        ));
        return array_map('maybe_unserialize', array_filter($results, 'is_string'));
    }
    
    public static function update_site_meta($site_id, $meta_key, $meta_value) {
        global $wpdb;
        $table = $wpdb->prefix . 'rphub_site_meta';
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_id FROM $table WHERE site_id = %d AND meta_key = %s LIMIT 1",
            $site_id, $meta_key
        ));
        
        $serialized = maybe_serialize($meta_value);
        
        if ($existing) {
            return $wpdb->update(
                $table,
                ['meta_value' => $serialized],
                ['meta_id' => $existing],
                ['%s'],
                ['%d']
            );
        }
        
        return $wpdb->insert($table, [
            'site_id' => $site_id,
            'meta_key' => $meta_key,
            'meta_value' => $serialized,
        ], ['%d', '%s', '%s']);
    }
    
    public static function delete_site_meta($site_id, $meta_key) {
        global $wpdb;
        $table = $wpdb->prefix . 'rphub_site_meta';
        
        return $wpdb->delete($table, [
            'site_id' => $site_id,
            'meta_key' => $meta_key,
        ], ['%d', '%s']);
    }
}

// Alias for backward compatibility — refactored code uses RPHUB_Database
if (!class_exists('RPHUB_Database')) {
    class_alias('ReplantaHub_Database', 'RPHUB_Database');
}
