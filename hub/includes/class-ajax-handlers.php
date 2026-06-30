<?php
/**
 * AJAX Handlers for Replanta Hub Professional
 * 
 * All AJAX endpoints for the admin interface
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ReplantaHub_AJAX_Handlers {
    
    public function __construct() {
        // Database operations
        add_action('wp_ajax_rphub_create_database_tables', array($this, 'create_database_tables'));
        add_action('wp_ajax_rphub_verify_database_tables', array($this, 'verify_database_tables'));
        
        // Site management
        add_action('wp_ajax_rphub_delete_site', array($this, 'delete_site'));
        // Note: add_site, update_site, remove_site are handled by class-site-manager.php to avoid conflicts
        add_action('wp_ajax_rphub_scan_site', array($this, 'scan_site'));
        
        // Reports
        // rphub_generate_report is handled by RphubReportGenerator (includes/class-report-generator.php)
        add_action('wp_ajax_rphub_export_report_pdf', array($this, 'export_report_pdf'));
        
        // Dashboard data
        add_action('wp_ajax_rphub_get_dashboard_data', array($this, 'get_dashboard_data'));
        add_action('wp_ajax_rphub_get_real_time_metrics', array($this, 'get_real_time_metrics'));
        
        // Automation
        add_action('wp_ajax_rphub_run_maintenance', array($this, 'run_maintenance'));
        add_action('wp_ajax_rphub_toggle_automation', array($this, 'toggle_automation'));
        
        // Enhanced admin functionality
        add_action('wp_ajax_rphub_bulk_action', array($this, 'handle_bulk_action'));
        add_action('wp_ajax_rphub_quick_action', array($this, 'handle_quick_action'));
        add_action('wp_ajax_rphub_file_upload', array($this, 'handle_file_upload'));
        add_action('wp_ajax_rphub_heartbeat', array($this, 'handle_heartbeat'));
        
        // Security actions
        add_action('wp_ajax_rphub_security_run_scan', array($this, 'run_security_scan'));
        add_action('wp_ajax_rphub_security_get_events', array($this, 'get_security_events'));
        add_action('wp_ajax_rphub_security_get_event_details', array($this, 'get_security_event_details'));
        
        // Performance actions  
        add_action('wp_ajax_rphub_run_pagespeed_test', array($this, 'run_pagespeed_test'));
        add_action('wp_ajax_rphub_get_pagespeed_history', array($this, 'get_pagespeed_history'));
        
        // Backup actions
        add_action('wp_ajax_rphub_backup_create', array($this, 'create_backup'));
        add_action('wp_ajax_rphub_backup_restore', array($this, 'restore_backup'));
        add_action('wp_ajax_rphub_backup_download', array($this, 'download_backup'));
        add_action('wp_ajax_rphub_backup_delete', array($this, 'delete_backup'));
        
        // Cloudflare actions
        add_action('wp_ajax_rphub_cloudflare_purge_cache', array($this, 'purge_cloudflare_cache'));
        
        // Care integration
        add_action('wp_ajax_rphub_connect_care', array($this, 'connect_care'));
        add_action('wp_ajax_rphub_check_care_connection', array($this, 'check_care_connection'));
    }
    
    public function create_database_tables() {
        check_ajax_referer('replanta_hub_setup', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        try {
            $database = new ReplantaHub_Database();
            $result = $database->create_tables();
            
            if ($result) {
                wp_send_json_success('Tablas creadas exitosamente');
            } else {
                wp_send_json_error('Error al crear las tablas');
            }
        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }
    
    public function verify_database_tables() {
        check_ajax_referer('replanta_hub_setup', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        global $wpdb;
        
        $tables = array(
            'rphub_sites' => 'Sitios Web',
            'rphub_maintenance_logs' => 'Logs de Monitoreo',
            'rphub_wptoolkit_vulnerabilities' => 'Vulnerabilidades WP Toolkit',
            'rphub_wptoolkit_updates' => 'Actualizaciones WP Toolkit',
            'rphub_backuply_jobs' => 'Trabajos Backuply',
            'rphub_backups' => 'Backups',
            'rphub_pagespeed_reports' => 'Reportes PageSpeed',
            'rphub_cloudflare_analytics' => 'Analytics Cloudflare',
            'rphub_cloudflare_security_events' => 'Eventos Seguridad Cloudflare',
            'rphub_comprehensive_reports' => 'Reportes Comprensivos',
            'rphub_automation_tasks' => 'Tareas Automatización',
            'rphub_notifications' => 'Notificaciones',
            'rphub_performance_metrics' => 'Métricas Rendimiento',
            'rphub_site_health' => 'Salud del Sistema'
        );
        
        $status_html = '<div class="table-status-grid">';
        
        foreach ($tables as $table => $description) {
            $full_table_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_table_name)) === $full_table_name;
            
            $status_class = $exists ? 'table-exists' : 'table-missing';
            $status_icon = $exists ? '' : '';
            $status_text = $exists ? 'Existe' : 'No existe';
            
            if ($exists) {
                $count = $wpdb->get_var("SELECT COUNT(*) FROM $full_table_name");
                $status_text .= " ($count registros)";
            }
            
            $status_html .= "<div class='table-status-item $status_class'>";
            $status_html .= "<span class='status-icon'>$status_icon</span>";
            $status_html .= "<span class='table-name'>$description</span>";
            $status_html .= "<span class='status-text'>$status_text</span>";
            $status_html .= "</div>";
        }
        
        $status_html .= '</div>';
        
        $status_html .= '<style>
        .table-status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        .table-status-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border-radius: 6px;
            gap: 10px;
        }
        .table-exists {
            background: #d4edda;
            border: 1px solid #c3e6cb;
        }
        .table-missing {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
        }
        .status-icon {
            font-size: 1.2em;
        }
        .table-name {
            font-weight: 500;
            flex-grow: 1;
        }
        .status-text {
            font-size: 0.9em;
            color: #6c757d;
        }
        </style>';
        
        wp_send_json_success($status_html);
    }
    
    public function delete_site() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $site_id = intval($_POST['site_id']);
        
        global $wpdb;
        $table = $wpdb->prefix . 'rphub_sites';
        
        $result = $wpdb->delete($table, array('id' => $site_id));
        
        if ($result) {
            wp_send_json_success('Sitio eliminado exitosamente');
        } else {
            wp_send_json_error('Error al eliminar el sitio');
        }
    }
    
    public function scan_site() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $site_id = intval($_POST['site_id']);
        
        global $wpdb;
        $site = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rphub_sites WHERE id = %d",
            $site_id
        ));
        
        if (!$site) {
            wp_send_json_error('Sitio no encontrado');
        }
        
        try {
            // Initialize integrations for scanning
            $results = array();
            
            // PageSpeed analysis
            if (class_exists('ReplantaHub_PageSpeed_Integration')) {
                $pagespeed = new ReplantaHub_PageSpeed_Integration();
                $pagespeed_result = $pagespeed->analyze_page($site->url);
                $results['pagespeed'] = $pagespeed_result;
            }
            
            // Security scan from site meta
            $security_score = RPHUB_Database::get_site_meta($site_id, 'security_score');
            $vulns = RPHUB_Database::get_site_meta($site_id, 'security_vulnerabilities');
            $results['security'] = array(
                'vulnerabilities_found' => is_array($vulns) ? count($vulns) : 0,
                'security_score' => $security_score ? (int) $security_score : null,
                'last_scan' => current_time('mysql')
            );
            
            // Update site health scores
            $health_data = array(
                'health_score' => isset($results['security']['security_score']) ? $results['security']['security_score'] : 85,
                'performance_score' => isset($results['pagespeed']['overall_score']) ? $results['pagespeed']['overall_score'] : 80,
                'security_score' => isset($results['security']['security_score']) ? $results['security']['security_score'] : 90,
                'last_scan' => current_time('mysql')
            );
            
            $wpdb->update(
                $wpdb->prefix . 'rphub_sites',
                $health_data,
                array('id' => $site_id)
            );
            
            wp_send_json_success(array(
                'message' => 'Escaneo completado exitosamente',
                'results' => $results
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Error durante el escaneo: ' . $e->getMessage());
        }
    }
    
    public function get_dashboard_data() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        try {
            global $wpdb;
            
            // Get basic statistics
            $sites_total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rphub_sites");
            $sites_active = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rphub_sites WHERE status = 'active'");
            $sites_inactive = $sites_total - $sites_active;
            
            // Get recent activity
            $recent_scans = $wpdb->get_results("
                SELECT name, last_scan 
                FROM {$wpdb->prefix}rphub_sites 
                WHERE last_scan IS NOT NULL 
                ORDER BY last_scan DESC 
                LIMIT 5
            ");
            
            // Get average scores
            $avg_scores = $wpdb->get_row("
                SELECT 
                    AVG(health_score) as avg_health,
                    AVG(performance_score) as avg_performance,
                    AVG(security_score) as avg_security
                FROM {$wpdb->prefix}rphub_sites 
                WHERE status = 'active'
            ");
            
            $dashboard_data = array(
                'stats' => array(
                    'total_sites' => intval($sites_total),
                    'active_sites' => intval($sites_active),
                    'inactive_sites' => intval($sites_inactive),
                    'avg_health_score' => round($avg_scores->avg_health ?? 0),
                    'avg_performance_score' => round($avg_scores->avg_performance ?? 0),
                    'avg_security_score' => round($avg_scores->avg_security ?? 0)
                ),
                'recent_activity' => $recent_scans,
                'system_status' => array(
                    'database' => 'online',
                    'integrations' => 'connected',
                    'automation' => 'running'
                )
            );
            
            wp_send_json_success($dashboard_data);
            
        } catch (Exception $e) {
            wp_send_json_error('Error obteniendo datos del dashboard: ' . $e->getMessage());
        }
    }
    
    public function get_real_time_metrics() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        try {
            $site_id = intval($_POST['site_id'] ?? 0);
            $site = RPHUB_Database::get_site($site_id);
            $last_backup_meta = RPHUB_Database::get_site_meta($site_id, 'last_backup');
            $alerts = RPHUB_Database::get_site_meta($site_id, 'security_alerts');
            $metrics = array(
                'cpu_usage' => null,
                'memory_usage' => null,
                'active_connections' => null,
                'response_time' => null,
                'uptime' => $site ? $site->status : 'unknown',
                'last_backup' => $last_backup_meta ?: null,
                'security_alerts' => is_array($alerts) ? count($alerts) : 0,
                'performance_issues' => 0
            );
            
            wp_send_json_success($metrics);
            
        } catch (Exception $e) {
            wp_send_json_error('Error obteniendo métricas: ' . $e->getMessage());
        }
    }
    
    public function run_maintenance() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        try {
            // Initialize intelligent maintenance system
            if (!class_exists('ReplantaHub_Intelligent_Maintenance')) {
                require_once RPHUB_PLUGIN_DIR . 'includes/class-intelligent-maintenance.php';
            }
            
            $maintenance_system = new ReplantaHub_Intelligent_Maintenance();
            
            // Perform comprehensive maintenance
            $maintenance_results = $maintenance_system->perform_intelligent_maintenance();
            
            if (isset($maintenance_results['error'])) {
                wp_send_json_error('Error en mantenimiento: ' . $maintenance_results['error']);
            }
            
            // Format response for frontend
            $response = array(
                'message' => 'Mantenimiento inteligente ejecutado exitosamente',
                'health_score_before' => $maintenance_results['health_analysis']['overall_score'],
                'health_score_after' => isset($maintenance_results['improvement_validation']['current_score']) ? 
                    $maintenance_results['improvement_validation']['current_score'] : 
                    $maintenance_results['health_analysis']['overall_score'],
                'optimizations_applied' => count($maintenance_results['optimization_results']),
                'performance_improvement' => isset($maintenance_results['improvement_validation']['improvement_delta']) ? 
                    $maintenance_results['improvement_validation']['improvement_delta'] : 0,
                'recommendations' => $maintenance_results['maintenance_report']['recommendations'],
                'results' => $maintenance_results
            );
            
            wp_send_json_success($response);
            
        } catch (Exception $e) {
            error_log('RPHUB: Maintenance error: ' . $e->getMessage());
            wp_send_json_error('Error ejecutando mantenimiento inteligente: ' . $e->getMessage());
        }
    }
    
    public function toggle_automation() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $automation_type = sanitize_text_field($_POST['automation_type']);
        $enabled = (bool) $_POST['enabled'];
        
        $automation_settings = get_option('replanta_hub_automation_settings', array());
        $automation_settings[$automation_type] = $enabled;
        
        update_option('replanta_hub_automation_settings', $automation_settings);
        
        wp_send_json_success(array(
            'message' => 'Configuración de automatización actualizada',
            'automation_type' => $automation_type,
            'enabled' => $enabled
        ));
    }
    
    // Enhanced admin functionality handlers
    public function handle_bulk_action() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $bulk_action = sanitize_text_field($_POST['bulk_action'] ?? '');
        
        // Accept both 'items' and 'site_ids' parameter names (dashboard sends site_ids[])
        $raw_items = $_POST['items'] ?? $_POST['site_ids'] ?? [];
        if (!is_array($raw_items)) {
            $raw_items = [$raw_items];
        }
        $items = array_map('sanitize_text_field', $raw_items);
        
        $result = $this->execute_bulk_action($bulk_action, $items);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    private function execute_bulk_action($action, $items) {
        switch ($action) {
            case 'delete_sites':
                return $this->bulk_delete_sites($items);
            case 'scan_sites':
            case 'security_scan':
                return $this->bulk_scan_sites($items);
            case 'backup_sites':
                return $this->bulk_backup_sites($items);
            case 'update_sites':
                return $this->bulk_update_sites($items);
            case 'sync_data':
                return $this->bulk_sync_data($items);
            case 'test_connection':
                return $this->bulk_test_connection($items);
            case 'cache_clear':
                return $this->bulk_clear_cache($items);
            default:
                return array('success' => false, 'message' => 'Acción no válida: ' . $action);
        }
    }
    
    private function bulk_delete_sites($site_ids) {
        global $wpdb;
        $table = $wpdb->prefix . 'rphub_sites';
        
        $deleted = 0;
        foreach ($site_ids as $site_id) {
            $result = $wpdb->delete($table, array('id' => intval($site_id)), array('%d'));
            if ($result) $deleted++;
        }
        
        return array(
            'success' => true,
            'message' => sprintf('%d sitios eliminados exitosamente', $deleted)
        );
    }
    
    private function bulk_scan_sites($site_ids) {
        // Implement bulk security scanning
        $scanned = 0;
        foreach ($site_ids as $site_id) {
            // Trigger security scan for each site
            do_action('rphub_schedule_security_scan', $site_id);
            $scanned++;
        }
        
        return array(
            'success' => true,
            'message' => sprintf('Escaneo iniciado para %d sitios', $scanned)
        );
    }
    
    private function bulk_backup_sites($site_ids) {
        $backed_up = 0;
        foreach ($site_ids as $site_id) {
            // Trigger backup for each site
            do_action('rphub_schedule_backup', $site_id);
            $backed_up++;
        }
        
        return array(
            'success' => true,
            'message' => sprintf('Backup iniciado para %d sitios', $backed_up)
        );
    }
    
    private function bulk_update_sites($site_ids) {
        $updated = 0;
        foreach ($site_ids as $site_id) {
            // Trigger updates for each site
            do_action('rphub_schedule_updates', $site_id);
            $updated++;
        }
        
        return array(
            'success' => true,
            'message' => sprintf('Actualizaciones iniciadas para %d sitios', $updated)
        );
    }
    
    /**
     * Sync data from WHM servers and update local sites table.
     */
    private function bulk_sync_data($items) {
        // Check if WHM integration is available
        if (!class_exists('RPHUB_WHM_Integration')) {
            return array('success' => false, 'message' => 'WHM Integration no disponible');
        }
        
        try {
            $whm = new RPHUB_WHM_Integration();
            
            if (!$whm->is_configured()) {
                return array('success' => false, 'message' => 'WHM no está configurado. Configure los servidores en Ajustes > WHM.');
            }
            
            // Get all accounts from all configured WHM servers
            $accounts = $whm->get_accounts();
            
            if (is_wp_error($accounts)) {
                return array('success' => false, 'message' => 'Error WHM: ' . $accounts->get_error_message());
            }
            
            // Sync accounts to local sites table
            global $wpdb;
            $table = $wpdb->prefix . 'rphub_sites';
            $synced = 0;
            $updated = 0;
            $errors = [];
            
            foreach ($accounts as $account) {
                $domain = $account['domain'] ?? '';
                if (empty($domain)) continue;
                
                $site_data = array(
                    'name'            => $account['user'] ?? $domain,
                    'domain'          => $domain,
                    'url'             => 'https://' . $domain,
                    'plan'            => $account['plan'] ?? 'semilla',
                    'cpanel_username' => $account['user'] ?? '',
                    'whm_server'      => $account['_whm_server'] ?? '',
                    'status'          => ($account['suspended'] ?? false) ? 'suspended' : 'active',
                    'updated_at'      => current_time('mysql'),
                );
                
                // Check if the site already exists (by domain)
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$table} WHERE domain = %s",
                    $domain
                ));
                
                if ($existing) {
                    // Update existing
                    $wpdb->update($table, $site_data, array('id' => $existing));
                    $updated++;
                } else {
                    // Insert new
                    $site_data['created_at'] = current_time('mysql');
                    $wpdb->insert($table, $site_data);
                    $synced++;
                }
            }
            
            $total = count($accounts);
            return array(
                'success'   => true,
                'message'   => sprintf('Sincronización completada: %d cuentas encontradas, %d nuevas, %d actualizadas', $total, $synced, $updated),
                'scheduled' => $total,
                'synced'    => $synced,
                'updated'   => $updated,
            );
        } catch (\Throwable $e) {
            error_log('[Replanta Hub] Sync error: ' . $e->getMessage());
            return array('success' => false, 'message' => 'Error de sincronización: ' . $e->getMessage());
        }
    }
    
    /**
     * Test connection to all active sites.
     */
    private function bulk_test_connection($items) {
        global $wpdb;
        $table = $wpdb->prefix . 'rphub_sites';
        
        $sites = $wpdb->get_results("SELECT id, domain FROM {$table} WHERE status = 'active'", ARRAY_A);
        $tested = 0;
        
        foreach ($sites as $site) {
            do_action('rphub_test_site_connection', $site['id']);
            $tested++;
        }
        
        return array(
            'success'   => true,
            'message'   => sprintf('Test de conexión iniciado para %d sitios', $tested),
            'scheduled' => $tested,
        );
    }
    
    /**
     * Clear cache on all active sites.
     */
    private function bulk_clear_cache($items) {
        global $wpdb;
        $table = $wpdb->prefix . 'rphub_sites';
        
        $sites = $wpdb->get_results("SELECT id, domain FROM {$table} WHERE status = 'active'", ARRAY_A);
        $cleared = 0;
        
        foreach ($sites as $site) {
            do_action('rphub_clear_site_cache', $site['id']);
            $cleared++;
        }
        
        return array(
            'success'   => true,
            'message'   => sprintf('Limpieza de caché iniciada para %d sitios', $cleared),
            'scheduled' => $cleared,
        );
    }
    
    public function handle_quick_action() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $quick_action = sanitize_text_field($_POST['quick_action']);
        $target = sanitize_text_field($_POST['target']);
        
        $result = $this->execute_quick_action($quick_action, $target);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    private function execute_quick_action($action, $target) {
        switch ($action) {
            case 'security_scan':
                return $this->quick_security_scan($target);
            case 'backup_create':
                return $this->quick_backup_create($target);
            case 'performance_test':
                return $this->quick_performance_test($target);
            case 'cache_purge':
                return $this->quick_cache_purge($target);
            default:
                return array('success' => false, 'message' => 'Acción rápida no válida');
        }
    }
    
    private function quick_security_scan($site_id) {
        // Implement quick security scan
        do_action('rphub_schedule_security_scan', $site_id);
        
        return array(
            'success' => true,
            'message' => 'Escaneo de seguridad iniciado',
            'scan_id' => uniqid('scan_')
        );
    }
    
    private function quick_backup_create($site_id) {
        // Implement quick backup creation
        do_action('rphub_schedule_backup', $site_id);
        
        return array(
            'success' => true,
            'message' => 'Backup iniciado',
            'backup_id' => uniqid('backup_')
        );
    }
    
    private function quick_performance_test($site_id) {
        // Implement quick performance test
        do_action('rphub_schedule_performance_test', $site_id);
        
        return array(
            'success' => true,
            'message' => 'Test de rendimiento iniciado',
            'test_id' => uniqid('perf_')
        );
    }
    
    private function quick_cache_purge($site_id) {
        // Implement cache purging
        do_action('rphub_purge_site_cache', $site_id);
        
        return array(
            'success' => true,
            'message' => 'Caché purgado exitosamente'
        );
    }
    
    public function handle_file_upload() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error('Permisos insuficientes para subir archivos');
        }
        
        if (!isset($_FILES['file'])) {
            wp_send_json_error('No se encontró archivo');
        }
        
        $upload_type = sanitize_text_field($_POST['upload_type']);
        $file = $_FILES['file'];
        
        // Validate file
        $allowed_types = $this->get_allowed_file_types($upload_type);
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error('Tipo de archivo no permitido');
        }
        
        // Handle upload
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        $uploaded_file = wp_handle_upload($file, array('test_form' => false));
        
        if (isset($uploaded_file['error'])) {
            wp_send_json_error($uploaded_file['error']);
        }
        
        wp_send_json_success(array(
            'message' => 'Archivo subido exitosamente',
            'file_url' => $uploaded_file['url'],
            'file_path' => $uploaded_file['file']
        ));
    }
    
    private function get_allowed_file_types($upload_type) {
        $types = array(
            'general' => array('image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'text/plain'),
            'backup' => array('application/zip', 'application/x-tar', 'application/gzip'),
            'config' => array('text/plain', 'application/json', 'text/xml')
        );
        
        return isset($types[$upload_type]) ? $types[$upload_type] : $types['general'];
    }
    
    public function handle_heartbeat() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        // Get pending notifications
        global $wpdb;
        $table = $wpdb->prefix . 'rphub_notifications';
        
        $notifications = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE status = 'unread' AND created_at > %s ORDER BY created_at DESC LIMIT 5",
            date('Y-m-d H:i:s', strtotime('-5 minutes'))
        ), ARRAY_A);
        
        // Mark as read
        if (!empty($notifications)) {
            $notification_ids = array_column($notifications, 'id');
            $wpdb->query(
                "UPDATE $table SET status = 'read' WHERE id IN (" . 
                implode(',', array_map('intval', $notification_ids)) . ")"
            );
        }
        
        wp_send_json_success(array(
            'notifications' => $notifications,
            'timestamp' => current_time('timestamp')
        ));
    }
    
    // Security handlers
    public function run_security_scan() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $site_id = intval($_POST['site_id']);
        $scan_type = sanitize_text_field($_POST['scan_type']);
        
        // Schedule comprehensive security scan
        do_action('rphub_schedule_security_scan', $site_id, $scan_type);
        
        wp_send_json_success(array(
            'message' => 'Escaneo de seguridad iniciado',
            'scan_id' => uniqid('scan_' . $site_id . '_')
        ));
    }
    
    public function get_security_events() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $site_id = intval($_POST['site_id']);
        $filter = sanitize_text_field($_POST['filter']);
        $limit = intval($_POST['limit']) ?: 50;
        
        global $wpdb;
        $table = $wpdb->prefix . 'rphub_security_events';
        
        $where = array('site_id = %d');
        $values = array($site_id);
        
        if ($filter && $filter !== 'all') {
            $where[] = 'event_type = %s';
            $values[] = $filter;
        }
        
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE " . implode(' AND ', $where) . 
            " ORDER BY created_at DESC LIMIT %d",
            array_merge($values, array($limit))
        ), ARRAY_A);
        
        // Format events for display
        foreach ($events as &$event) {
            $event['formatted_time'] = human_time_diff(strtotime($event['created_at'])) . ' ago';
        }
        
        wp_send_json_success($events);
    }
    
    public function get_security_event_details() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $event_id = sanitize_text_field($_POST['event_id']);
        
        global $wpdb;
        $table = $wpdb->prefix . 'rphub_security_events';
        
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %s",
            $event_id
        ), ARRAY_A);
        
        if (!$event) {
            wp_send_json_error('Evento no encontrado');
        }
        
        // Format event details
        $event['formatted_datetime'] = date('Y-m-d H:i:s', strtotime($event['created_at']));
        $event['actions_taken'] = json_decode($event['actions_taken'], true) ?: array();
        
        wp_send_json_success($event);
    }
    
    // Performance handlers  
    public function run_pagespeed_test() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $site_id = intval($_POST['site_id']);
        
        // Schedule PageSpeed test
        do_action('rphub_schedule_pagespeed_test', $site_id);
        
        wp_send_json_success(array(
            'message' => 'Test de PageSpeed iniciado',
            'test_id' => uniqid('pagespeed_' . $site_id . '_')
        ));
    }
    
    public function get_pagespeed_history() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $site_id = intval($_POST['site_id']);
        $limit = intval($_POST['limit']) ?: 30;
        
        global $wpdb;
        $table = $wpdb->prefix . 'rphub_pagespeed_reports';
        
        $reports = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE site_id = %d ORDER BY created_at DESC LIMIT %d",
            $site_id, $limit
        ), ARRAY_A);
        
        wp_send_json_success($reports);
    }
    
    // Backup handlers
    public function create_backup() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $site_id = intval($_POST['site_id']);
        
        // Schedule backup creation
        do_action('rphub_schedule_backup', $site_id);
        
        wp_send_json_success(array(
            'message' => 'Backup iniciado',
            'backup_id' => uniqid('backup_' . $site_id . '_')
        ));
    }
    
    public function restore_backup() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $backup_id = sanitize_text_field($_POST['backup_id']);
        
        // Schedule backup restoration
        do_action('rphub_schedule_backup_restore', $backup_id);
        
        wp_send_json_success(array(
            'message' => 'Restauración de backup iniciada'
        ));
    }
    
    public function download_backup() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $backup_id = sanitize_text_field($_POST['backup_id']);
        
        // Generate download URL
        $download_url = admin_url('admin.php?page=replanta-hub-download&backup_id=' . $backup_id . '&nonce=' . wp_create_nonce('download_backup'));
        
        wp_send_json_success(array(
            'message' => 'Descarga preparada',
            'download_url' => $download_url
        ));
    }
    
    public function delete_backup() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $backup_id = sanitize_text_field($_POST['backup_id']);
        
        global $wpdb;
        $table = $wpdb->prefix . 'rphub_backups';
        
        $result = $wpdb->delete($table, array('id' => $backup_id), array('%s'));
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'Backup eliminado exitosamente'
            ));
        } else {
            wp_send_json_error('Error al eliminar backup');
        }
    }
    
    // Cloudflare handlers
    public function purge_cloudflare_cache() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $site_id = intval($_POST['site_id']);
        $purge_type = sanitize_text_field($_POST['purge_type']);
        
        // Schedule Cloudflare cache purge
        do_action('rphub_schedule_cloudflare_purge', $site_id, $purge_type);
        
        wp_send_json_success(array(
            'message' => 'Purga de caché Cloudflare iniciada'
        ));
    }
    
    // Care integration handlers
    public function connect_care() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $connection_key = sanitize_text_field($_POST['connection_key']);
        
        // Validate connection key with Care API
        $api_response = wp_remote_post('https://care.replanta.com/api/connect', array(
            'body' => array(
                'key' => $connection_key,
                'site_url' => home_url(),
                'admin_email' => get_option('admin_email')
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($api_response)) {
            wp_send_json_error('Error de conexión con Care API');
        }
        
        $response_body = wp_remote_retrieve_body($api_response);
        $response_data = json_decode($response_body, true);
        
        if ($response_data['success']) {
            // Save Care connection data
            $care_status = array(
                'connected' => true,
                'plan' => $response_data['plan'],
                'site_name' => $response_data['site_name'],
                'last_sync' => current_time('timestamp'),
                'update_management' => true,
                'features_enabled' => $response_data['features']
            );
            
            update_option('rphub_care_status', $care_status);
            
            wp_send_json_success(array(
                'message' => 'Conexión a Care establecida exitosamente',
                'plan' => $response_data['plan']
            ));
        } else {
            wp_send_json_error($response_data['message'] ?: 'Error al conectar con Care');
        }
    }
    
    public function check_care_connection() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $care_status = get_option('rphub_care_status', array(
            'connected' => false,
            'plan' => 'none',
            'last_check' => 0
        ));
        
        wp_send_json_success($care_status);
    }
}

// Initialize AJAX handlers
new ReplantaHub_AJAX_Handlers();
