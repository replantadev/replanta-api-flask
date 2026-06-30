<?php
/**
 * Enhanced Database Setup for Replanta Hub
 * Creates all necessary database tables including plans system
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RPHUB_Enhanced_Database_Setup {
    
    /**
     * Create all required database tables
     */
    public static function create_all_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $results = array();
        
        // Sites table
        $table_sites = $wpdb->prefix . 'rphub_sites';
        $sql_sites = "CREATE TABLE IF NOT EXISTS $table_sites (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            url varchar(500) NOT NULL,
            token varchar(255) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            last_seen datetime DEFAULT NULL,
            last_check datetime DEFAULT NULL,
            last_success datetime DEFAULT NULL,
            health_score int(3) DEFAULT 0,
            wp_version varchar(20) DEFAULT '',
            php_version varchar(20) DEFAULT '',
            plugins_count int(5) DEFAULT 0,
            themes_count int(5) DEFAULT 0,
            updates_available int(5) DEFAULT 0,
            security_issues int(5) DEFAULT 0,
            ssl_status varchar(20) DEFAULT 'unknown',
            memory_usage varchar(50) DEFAULT '',
            storage_used varchar(50) DEFAULT '',
            notes text,
            whm_account varchar(100) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY url (url),
            KEY status (status),
            KEY last_check (last_check),
            KEY health_score (health_score)
        ) $charset_collate;";
        
        $results['sites'] = $wpdb->query($sql_sites);
        
        // Plans table
        $table_plans = $wpdb->prefix . 'rphub_plans';
        $sql_plans = "CREATE TABLE IF NOT EXISTS $table_plans (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            slug varchar(50) NOT NULL,
            description text,
            price decimal(10,2) NOT NULL DEFAULT 0.00,
            currency varchar(3) NOT NULL DEFAULT 'EUR',
            billing_cycle varchar(20) NOT NULL DEFAULT 'monthly',
            max_sites int(5) NOT NULL DEFAULT 1,
            features text,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            sort_order int(3) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY is_active (is_active),
            KEY price (price)
        ) $charset_collate;";
        
        $results['plans'] = $wpdb->query($sql_plans);
        
        // Plan features table
        $table_plan_features = $wpdb->prefix . 'rphub_plan_features';
        $sql_plan_features = "CREATE TABLE IF NOT EXISTS $table_plan_features (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            plan_id mediumint(9) NOT NULL,
            feature_key varchar(100) NOT NULL,
            feature_value text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY plan_id (plan_id),
            KEY feature_key (feature_key),
            UNIQUE KEY plan_feature (plan_id, feature_key),
            FOREIGN KEY (plan_id) REFERENCES $table_plans(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        $results['plan_features'] = $wpdb->query($sql_plan_features);
        
        // Site plans relationship table
        $table_site_plans = $wpdb->prefix . 'rphub_site_plans';
        $sql_site_plans = "CREATE TABLE IF NOT EXISTS $table_site_plans (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            site_id mediumint(9) NOT NULL,
            plan_id mediumint(9) NOT NULL,
            assigned_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime DEFAULT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            KEY site_id (site_id),
            KEY plan_id (plan_id),
            KEY is_active (is_active),
            UNIQUE KEY site_plan (site_id, plan_id),
            FOREIGN KEY (site_id) REFERENCES $table_sites(id) ON DELETE CASCADE,
            FOREIGN KEY (plan_id) REFERENCES $table_plans(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        $results['site_plans'] = $wpdb->query($sql_site_plans);
        
        // Tasks table
        $table_tasks = $wpdb->prefix . 'rphub_tasks';
        $sql_tasks = "CREATE TABLE IF NOT EXISTS $table_tasks (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            site_id mediumint(9) NOT NULL,
            task_type varchar(50) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            priority int(3) NOT NULL DEFAULT 5,
            scheduled_at datetime DEFAULT NULL,
            started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            result text,
            error_message text,
            attempts int(3) NOT NULL DEFAULT 0,
            max_attempts int(3) NOT NULL DEFAULT 3,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY site_id (site_id),
            KEY task_type (task_type),
            KEY status (status),
            KEY scheduled_at (scheduled_at),
            KEY priority (priority),
            FOREIGN KEY (site_id) REFERENCES $table_sites(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        $results['tasks'] = $wpdb->query($sql_tasks);
        
        // Reports table
        $table_reports = $wpdb->prefix . 'rphub_reports';
        $sql_reports = "CREATE TABLE IF NOT EXISTS $table_reports (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            site_id mediumint(9) NOT NULL,
            report_type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            content longtext,
            data longtext,
            severity varchar(20) NOT NULL DEFAULT 'info',
            is_read tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY site_id (site_id),
            KEY report_type (report_type),
            KEY severity (severity),
            KEY is_read (is_read),
            KEY created_at (created_at),
            FOREIGN KEY (site_id) REFERENCES $table_sites(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        $results['reports'] = $wpdb->query($sql_reports);
        
        // Notifications table
        $table_notifications = $wpdb->prefix . 'rphub_notifications';
        $sql_notifications = "CREATE TABLE IF NOT EXISTS $table_notifications (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            site_id mediumint(9) DEFAULT NULL,
            title varchar(255) NOT NULL,
            message text NOT NULL,
            type varchar(20) NOT NULL DEFAULT 'info',
            is_read tinyint(1) NOT NULL DEFAULT 0,
            is_dismissed tinyint(1) NOT NULL DEFAULT 0,
            action_url varchar(500) DEFAULT NULL,
            action_text varchar(100) DEFAULT NULL,
            expires_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY site_id (site_id),
            KEY type (type),
            KEY is_read (is_read),
            KEY created_at (created_at),
            FOREIGN KEY (site_id) REFERENCES $table_sites(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        $results['notifications'] = $wpdb->query($sql_notifications);
        
        // Activities/Logs table
        $table_activities = $wpdb->prefix . 'rphub_activities';
        $sql_activities = "CREATE TABLE IF NOT EXISTS $table_activities (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            site_id mediumint(9) DEFAULT NULL,
            user_id bigint(20) DEFAULT NULL,
            action varchar(100) NOT NULL,
            details text,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY site_id (site_id),
            KEY user_id (user_id),
            KEY action (action),
            KEY created_at (created_at),
            FOREIGN KEY (site_id) REFERENCES $table_sites(id) ON DELETE SET NULL
        ) $charset_collate;";
        
        $results['activities'] = $wpdb->query($sql_activities);
        
        // Backups table
        $table_backups = $wpdb->prefix . 'rphub_backups';
        $sql_backups = "CREATE TABLE IF NOT EXISTS $table_backups (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            site_id mediumint(9) NOT NULL,
            backup_name varchar(255) NOT NULL,
            backup_type varchar(50) NOT NULL DEFAULT 'full',
            file_size bigint(20) DEFAULT NULL,
            file_path varchar(500) DEFAULT NULL,
            storage_location varchar(100) DEFAULT 'local',
            status varchar(20) NOT NULL DEFAULT 'completed',
            error_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY site_id (site_id),
            KEY backup_type (backup_type),
            KEY status (status),
            KEY created_at (created_at),
            FOREIGN KEY (site_id) REFERENCES $table_sites(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        $results['backups'] = $wpdb->query($sql_backups);
        
        // Site meta table (key-value store for per-site metadata)
        $table_site_meta = $wpdb->prefix . 'rphub_site_meta';
        $sql_site_meta = "CREATE TABLE IF NOT EXISTS $table_site_meta (
            meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            site_id mediumint(9) unsigned NOT NULL DEFAULT 0,
            meta_key varchar(255) DEFAULT NULL,
            meta_value longtext,
            PRIMARY KEY (meta_id),
            KEY site_id (site_id),
            KEY meta_key (meta_key(191))
        ) $charset_collate;";
        
        $results['site_meta'] = $wpdb->query($sql_site_meta);
        
        // Insert default plans
        self::insert_default_plans();
        
        return $results;
    }
    
    /**
     * Insert default Replanta plans
     */
    public static function insert_default_plans() {
        global $wpdb;
        
        $table_plans = $wpdb->prefix . 'rphub_plans';
        $table_features = $wpdb->prefix . 'rphub_plan_features';
        
        // Check if plans already exist
        $existing_plans = $wpdb->get_var("SELECT COUNT(*) FROM $table_plans");
        if ($existing_plans > 0) {
            return; // Plans already exist
        }
        
        // Semilla Plan
        $semilla_id = $wpdb->insert($table_plans, array(
            'name' => 'Semilla',
            'slug' => 'semilla',
            'description' => 'Plan básico de mantenimiento para sitios web pequeños',
            'price' => 49.00,
            'currency' => 'EUR',
            'billing_cycle' => 'monthly',
            'max_sites' => 1,
            'is_active' => 1,
            'sort_order' => 1
        ));
        
        if ($semilla_id !== false) {
            $semilla_id = $wpdb->insert_id;
            
            // Semilla features
            $semilla_features = array(
                'backup_frequency' => 'weekly',
                'backup_retention' => '4',
                'plugin_updates' => 'minor',
                'core_updates' => 'minor',
                'uptime_monitoring' => 'yes',
                'ssl_monitoring' => 'yes',
                'security_scan' => 'weekly',
                'performance_monitoring' => 'basic',
                'support_level' => 'email',
                'maintenance_window' => 'business_hours'
            );
            
            foreach ($semilla_features as $key => $value) {
                $wpdb->insert($table_features, array(
                    'plan_id' => $semilla_id,
                    'feature_key' => $key,
                    'feature_value' => $value
                ));
            }
        }
        
        // Raíz Plan
        $raiz_id = $wpdb->insert($table_plans, array(
            'name' => 'Raíz',
            'slug' => 'raiz',
            'description' => 'Plan intermedio con características avanzadas',
            'price' => 89.00,
            'currency' => 'EUR',
            'billing_cycle' => 'monthly',
            'max_sites' => 3,
            'is_active' => 1,
            'sort_order' => 2
        ));
        
        if ($raiz_id !== false) {
            $raiz_id = $wpdb->insert_id;
            
            // Raíz features
            $raiz_features = array(
                'backup_frequency' => 'daily',
                'backup_retention' => '7',
                'plugin_updates' => 'all',
                'core_updates' => 'all',
                'uptime_monitoring' => 'yes',
                'ssl_monitoring' => 'yes',
                'security_scan' => 'daily',
                'performance_monitoring' => 'advanced',
                'cache_optimization' => 'yes',
                'image_optimization' => 'yes',
                'support_level' => 'priority',
                'maintenance_window' => 'extended'
            );
            
            foreach ($raiz_features as $key => $value) {
                $wpdb->insert($table_features, array(
                    'plan_id' => $raiz_id,
                    'feature_key' => $key,
                    'feature_value' => $value
                ));
            }
        }
        
        // Ecosistema Plan
        $ecosistema_id = $wpdb->insert($table_plans, array(
            'name' => 'Ecosistema',
            'slug' => 'ecosistema',
            'description' => 'Plan premium con todas las características incluidas',
            'price' => 149.00,
            'currency' => 'EUR',
            'billing_cycle' => 'monthly',
            'max_sites' => 10,
            'is_active' => 1,
            'sort_order' => 3
        ));
        
        if ($ecosistema_id !== false) {
            $ecosistema_id = $wpdb->insert_id;
            
            // Ecosistema features
            $ecosistema_features = array(
                'backup_frequency' => 'hourly',
                'backup_retention' => '30',
                'plugin_updates' => 'all',
                'core_updates' => 'all',
                'uptime_monitoring' => 'yes',
                'ssl_monitoring' => 'yes',
                'security_scan' => 'realtime',
                'performance_monitoring' => 'premium',
                'cache_optimization' => 'yes',
                'image_optimization' => 'yes',
                'cdn_integration' => 'yes',
                'seo_monitoring' => 'yes',
                'content_optimization' => 'yes',
                'white_label' => 'yes',
                'support_level' => '24/7',
                'maintenance_window' => 'any_time'
            );
            
            foreach ($ecosistema_features as $key => $value) {
                $wpdb->insert($table_features, array(
                    'plan_id' => $ecosistema_id,
                    'feature_key' => $key,
                    'feature_value' => $value
                ));
            }
        }
    }
    
    /**
     * Check which tables exist
     */
    public static function check_tables_exist() {
        global $wpdb;
        
        $tables_to_check = [
            'rphub_sites',
            'rphub_plans', 
            'rphub_plan_features',
            'rphub_site_plans',
            'rphub_tasks',
            'rphub_reports',
            'rphub_notifications',
            'rphub_activities',
            'rphub_backups',
            'rphub_site_meta'
        ];
        
        $existing_tables = array();
        $missing_tables = array();
        
        foreach ($tables_to_check as $table) {
            $table_name = $wpdb->prefix . $table;
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            if ($table_exists) {
                $existing_tables[] = $table;
            } else {
                $missing_tables[] = $table;
            }
        }
        
        return array(
            'existing' => $existing_tables,
            'missing' => $missing_tables
        );
    }
    
    /**
     * Get table creation status
     */
    public static function get_setup_status() {
        $tables_status = self::check_tables_exist();
        
        return array(
            'total_tables' => count($tables_status['existing']) + count($tables_status['missing']),
            'existing_tables' => count($tables_status['existing']),
            'missing_tables' => count($tables_status['missing']),
            'is_complete' => empty($tables_status['missing']),
            'missing_list' => $tables_status['missing']
        );
    }
}

// Manual setup functionality
if (isset($_GET['rphub_setup_enhanced']) && current_user_can('manage_options')) {
    $result = RPHUB_Enhanced_Database_Setup::create_all_tables();
    $status = RPHUB_Enhanced_Database_Setup::get_setup_status();
    
    echo '<div style="max-width: 800px; margin: 20px auto; padding: 20px; font-family: Arial, sans-serif;">';
    echo '<h1> Replanta Hub - Enhanced Database Setup</h1>';
    
    echo '<h2> Setup Results</h2>';
    echo '<div style="background: #f0f0f0; padding: 15px; border-radius: 8px;">';
    echo '<pre>' . print_r($result, true) . '</pre>';
    echo '</div>';
    
    echo '<h2> Current Status</h2>';
    if ($status['is_complete']) {
        echo '<div style="background: #d4edda; color: #155724; padding: 15px; border: 1px solid #c3e6cb; border-radius: 8px; margin: 10px 0;">';
        echo '<strong> All tables created successfully!</strong><br>';
        echo 'Total tables: ' . $status['total_tables'] . '<br>';
        echo 'Existing tables: ' . $status['existing_tables'];
        echo '</div>';
    } else {
        echo '<div style="background: #f8d7da; color: #721c24; padding: 15px; border: 1px solid #f5c6cb; border-radius: 8px; margin: 10px 0;">';
        echo '<strong> Setup incomplete</strong><br>';
        echo 'Missing tables: ' . implode(', ', $status['missing_list']);
        echo '</div>';
    }
    
    echo '<h2> Next Steps</h2>';
    echo '<ol>';
    echo '<li><a href="' . admin_url('admin.php?page=replanta-hub') . '">Go to Hub Dashboard</a></li>';
    echo '<li>Configure your first site</li>';
    echo '<li>Assign plans to sites</li>';
    echo '<li>Start monitoring and maintenance</li>';
    echo '</ol>';
    
    echo '</div>';
    exit;
}
