<?php
/**
 * Replanta Hub Emergency Setup and Fix
 * This file will diagnose and fix all issues with the plugins
 */

if (!defined('ABSPATH')) {
    define('WP_USE_THEMES', false);
    require_once '../../../wp-config.php';
}

class RPHUB_Emergency_Fix {
    
    public static function run_complete_diagnosis() {
        echo "<h1>🔧 Replanta Hub - Diagnóstico Completo</h1>";
        
        global $wpdb;
        
        // 1. Check WordPress environment
        echo "<h2>1️⃣ Entorno WordPress</h2>";
        echo "✅ WordPress Version: " . get_bloginfo('version') . "<br>";
        echo "✅ PHP Version: " . PHP_VERSION . "<br>";
        echo "✅ MySQL Version: " . $wpdb->db_version() . "<br>";
        echo "✅ Site URL: " . get_site_url() . "<br>";
        echo "<br>";
        
        // 2. Check plugins status
        echo "<h2>2️⃣ Estado de Plugins</h2>";
        $active_plugins = get_option('active_plugins');
        $hub_active = false;
        $care_active = false;
        
        foreach ($active_plugins as $plugin) {
            if (strpos($plugin, 'replanta-hub') !== false) {
                echo "✅ Replanta Hub: ACTIVO ($plugin)<br>";
                $hub_active = true;
            } elseif (strpos($plugin, 'replanta-care') !== false) {
                echo "✅ Replanta Care: ACTIVO ($plugin)<br>";
                $care_active = true;
            }
        }
        
        if (!$hub_active) echo "❌ Replanta Hub: NO ACTIVO<br>";
        if (!$care_active) echo "❌ Replanta Care: NO ACTIVO<br>";
        echo "<br>";
        
        // 3. Check database tables
        echo "<h2>3️⃣ Tablas de Base de Datos</h2>";
        $required_tables = [
            'rphub_sites',
            'rphub_tasks', 
            'rphub_reports',
            'rphub_notifications'
        ];
        
        $missing_tables = [];
        foreach ($required_tables as $table) {
            $table_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            if ($exists) {
                $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
                echo "✅ $table_name: EXISTS ($count registros)<br>";
            } else {
                echo "❌ $table_name: NOT FOUND<br>";
                $missing_tables[] = $table;
            }
        }
        echo "<br>";
        
        // 4. Fix missing tables
        if (!empty($missing_tables)) {
            echo "<h2>4️⃣ Reparando Tablas Faltantes</h2>";
            
            if (self::create_missing_tables($missing_tables)) {
                echo "✅ Tablas creadas exitosamente<br>";
            } else {
                echo "❌ Error al crear tablas<br>";
            }
            echo "<br>";
        }
        
        // 5. Test AJAX endpoints
        echo "<h2>5️⃣ Endpoints AJAX</h2>";
        
        if (function_exists('wp_create_nonce')) {
            $nonce = wp_create_nonce('rphub_ajax');
            echo "✅ Nonce generado: $nonce<br>";
        } else {
            echo "❌ No se puede generar nonce<br>";
        }
        
        $ajax_actions = [
            'rphub_get_sites_list',
            'rphub_test_whm_connection',
            'rphub_get_recent_reports',
            'rphub_get_notifications_count'
        ];
        
        foreach ($ajax_actions as $action) {
            if (has_action("wp_ajax_$action")) {
                echo "✅ $action: REGISTRADO<br>";
            } else {
                echo "❌ $action: NO REGISTRADO<br>";
            }
        }
        echo "<br>";
        
        // 6. Add demo data
        echo "<h2>6️⃣ Añadiendo Datos de Demostración</h2>";
        self::add_demo_data();
        echo "<br>";
        
        // 7. Test WHM integration
        echo "<h2>7️⃣ Integración WHM</h2>";
        $whm_enabled = get_option('rphub_whm_enabled');
        $whm_server = get_option('rphub_whm_server');
        $whm_username = get_option('rphub_whm_username');
        
        echo "WHM Enabled: " . ($whm_enabled ? 'SÍ' : 'NO') . "<br>";
        echo "WHM Server: " . ($whm_server ?: 'NO CONFIGURADO') . "<br>";
        echo "WHM Username: " . ($whm_username ?: 'NO CONFIGURADO') . "<br>";
        echo "<br>";
        
        // 8. Final status
        echo "<h2>8️⃣ Resumen Final</h2>";
        
        $all_tables_exist = empty(self::check_missing_tables());
        $ajax_working = has_action('wp_ajax_rphub_get_sites_list');
        
        if ($hub_active && $all_tables_exist && $ajax_working) {
            echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px;'>";
            echo "<h3 style='color: #155724; margin: 0;'>🎉 SISTEMA COMPLETAMENTE FUNCIONAL</h3>";
            echo "<p style='color: #155724; margin: 5px 0 0 0;'>Todo está configurado correctamente. El Hub debería funcionar sin problemas.</p>";
            echo "</div>";
        } else {
            echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
            echo "<h3 style='color: #721c24; margin: 0;'>⚠️ PROBLEMAS DETECTADOS</h3>";
            echo "<p style='color: #721c24; margin: 5px 0 0 0;'>Algunos componentes necesitan atención.</p>";
            echo "</div>";
        }
        
        echo "<p><a href='" . admin_url('admin.php?page=replanta-hub') . "' style='background: #0073aa; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px;'>🚀 Ir al Dashboard</a></p>";
    }
    
    private static function create_missing_tables($missing_tables) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $success = true;
        
        foreach ($missing_tables as $table) {
            switch ($table) {
                case 'rphub_sites':
                    $table_name = $wpdb->prefix . 'rphub_sites';
                    $sql = "CREATE TABLE $table_name (
                        id mediumint(9) NOT NULL AUTO_INCREMENT,
                        name varchar(255) NOT NULL,
                        url varchar(500) NOT NULL,
                        token varchar(255) NOT NULL,
                        plan varchar(20) NOT NULL DEFAULT 'basic',
                        status varchar(20) NOT NULL DEFAULT 'active',
                        last_check datetime DEFAULT NULL,
                        last_success datetime DEFAULT NULL,
                        health_score int(3) DEFAULT 0,
                        wp_version varchar(20) DEFAULT '',
                        php_version varchar(20) DEFAULT '',
                        plugins_count int(5) DEFAULT 0,
                        themes_count int(5) DEFAULT 0,
                        updates_available int(5) DEFAULT 0,
                        security_issues int(5) DEFAULT 0,
                        notes text,
                        whm_account varchar(100) DEFAULT '',
                        created_at datetime DEFAULT CURRENT_TIMESTAMP,
                        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        UNIQUE KEY url (url),
                        KEY status (status),
                        KEY plan (plan),
                        KEY last_check (last_check)
                    ) $charset_collate;";
                    break;
                    
                case 'rphub_tasks':
                    $table_name = $wpdb->prefix . 'rphub_tasks';
                    $sql = "CREATE TABLE $table_name (
                        id mediumint(9) NOT NULL AUTO_INCREMENT,
                        site_id mediumint(9) NOT NULL,
                        task_type varchar(50) NOT NULL,
                        status varchar(20) NOT NULL DEFAULT 'pending',
                        priority int(2) NOT NULL DEFAULT 5,
                        scheduled_at datetime NOT NULL,
                        started_at datetime DEFAULT NULL,
                        completed_at datetime DEFAULT NULL,
                        result longtext,
                        error_message text,
                        retry_count int(2) DEFAULT 0,
                        max_retries int(2) DEFAULT 3,
                        created_at datetime DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        KEY site_id (site_id),
                        KEY task_type (task_type),
                        KEY status (status),
                        KEY scheduled_at (scheduled_at)
                    ) $charset_collate;";
                    break;
                    
                case 'rphub_reports':
                    $table_name = $wpdb->prefix . 'rphub_reports';
                    $sql = "CREATE TABLE $table_name (
                        id mediumint(9) NOT NULL AUTO_INCREMENT,
                        site_id mediumint(9) NOT NULL,
                        report_type varchar(50) NOT NULL,
                        period varchar(20) NOT NULL,
                        data longtext NOT NULL,
                        file_path varchar(500) DEFAULT '',
                        email_sent tinyint(1) DEFAULT 0,
                        created_at datetime DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        KEY site_id (site_id),
                        KEY report_type (report_type),
                        KEY period (period),
                        KEY created_at (created_at)
                    ) $charset_collate;";
                    break;
                    
                case 'rphub_notifications':
                    $table_name = $wpdb->prefix . 'rphub_notifications';
                    $sql = "CREATE TABLE $table_name (
                        id mediumint(9) NOT NULL AUTO_INCREMENT,
                        site_id mediumint(9) DEFAULT NULL,
                        type varchar(50) NOT NULL,
                        severity varchar(20) NOT NULL DEFAULT 'info',
                        title varchar(255) NOT NULL,
                        message text NOT NULL,
                        data text,
                        read_status tinyint(1) DEFAULT 0,
                        email_sent tinyint(1) DEFAULT 0,
                        created_at datetime DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        KEY site_id (site_id),
                        KEY type (type),
                        KEY severity (severity),
                        KEY read_status (read_status),
                        KEY created_at (created_at)
                    ) $charset_collate;";
                    break;
                    
                default:
                    continue 2;
            }
            
            $result = dbDelta($sql);
            echo "🔧 Creando $table: " . (is_array($result) ? 'OK' : 'ERROR') . "<br>";
            
            if (!is_array($result)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    private static function check_missing_tables() {
        global $wpdb;
        
        $required_tables = ['rphub_sites', 'rphub_tasks', 'rphub_reports', 'rphub_notifications'];
        $missing = [];
        
        foreach ($required_tables as $table) {
            $table_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            if (!$exists) {
                $missing[] = $table;
            }
        }
        
        return $missing;
    }
    
    private static function add_demo_data() {
        global $wpdb;
        
        $table_sites = $wpdb->prefix . 'rphub_sites';
        
        // Check if demo data already exists
        $existing_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_sites");
        
        if ($existing_count > 0) {
            echo "✅ Ya existen $existing_count sitios en la base de datos<br>";
            return;
        }
        
        // Add demo sites
        $demo_sites = [
            [
                'name' => 'Replanta.es',
                'url' => 'https://replanta.es',
                'token' => wp_generate_password(32, false),
                'plan' => 'premium',
                'status' => 'active',
                'health_score' => 95,
                'wp_version' => '6.3.1',
                'php_version' => '8.1.12',
                'plugins_count' => 15,
                'themes_count' => 3,
                'updates_available' => 0,
                'security_issues' => 0,
                'last_check' => current_time('mysql'),
                'last_success' => current_time('mysql'),
                'notes' => 'Sitio principal de Replanta'
            ],
            [
                'name' => 'Cliente Demo 1',
                'url' => 'https://cliente1.example.com',
                'token' => wp_generate_password(32, false),
                'plan' => 'basic',
                'status' => 'active',
                'health_score' => 88,
                'wp_version' => '6.2.2',
                'php_version' => '8.0.28',
                'plugins_count' => 12,
                'themes_count' => 2,
                'updates_available' => 2,
                'security_issues' => 0,
                'last_check' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                'last_success' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                'notes' => 'Cliente de plan básico'
            ],
            [
                'name' => 'Tienda Online Demo',
                'url' => 'https://tienda.example.com',
                'token' => wp_generate_password(32, false),
                'plan' => 'advanced',
                'status' => 'warning',
                'health_score' => 75,
                'wp_version' => '6.1.3',
                'php_version' => '7.4.33',
                'plugins_count' => 25,
                'themes_count' => 4,
                'updates_available' => 8,
                'security_issues' => 1,
                'last_check' => date('Y-m-d H:i:s', strtotime('-3 hours')),
                'last_success' => date('Y-m-d H:i:s', strtotime('-3 hours')),
                'notes' => 'WooCommerce store, necesita actualización PHP'
            ]
        ];
        
        $inserted = 0;
        foreach ($demo_sites as $site) {
            $result = $wpdb->insert($table_sites, $site);
            if ($result) {
                $inserted++;
                echo "✅ Sitio añadido: {$site['name']}<br>";
            } else {
                echo "❌ Error añadiendo: {$site['name']}<br>";
            }
        }
        
        echo "📊 Total sitios demo añadidos: $inserted<br>";
        
        // Add some demo notifications
        $table_notifications = $wpdb->prefix . 'rphub_notifications';
        $demo_notifications = [
            [
                'site_id' => null,
                'type' => 'system',
                'severity' => 'info',
                'title' => 'Sistema iniciado',
                'message' => 'Replanta Hub ha sido configurado correctamente',
                'created_at' => current_time('mysql')
            ],
            [
                'site_id' => 3,
                'type' => 'security',
                'severity' => 'warning',
                'title' => 'PHP desactualizado',
                'message' => 'El sitio está usando PHP 7.4, se recomienda actualizar a 8.0+',
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
            ]
        ];
        
        foreach ($demo_notifications as $notification) {
            $wpdb->insert($table_notifications, $notification);
        }
        
        echo "🔔 Notificaciones demo añadidas<br>";
    }
}

// Run the diagnosis
RPHUB_Emergency_Fix::run_complete_diagnosis();
?>
