<?php
/**
 * Sistema de actualizaciones inteligentes para WordPress Hub
 * Maneja actualizaciones automáticas, rollback y programación
 */

class RphubSmartUpdates {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Hooks para actualizaciones
        add_action('rphub_check_updates', array($this, 'check_site_updates'));
        add_action('rphub_perform_updates', array($this, 'perform_scheduled_updates'));
        add_action('rphub_create_backup_before_update', array($this, 'create_backup_before_update'));
        
        // AJAX handlers
        add_action('wp_ajax_rphub_configure_auto_updates', array($this, 'ajax_configure_auto_updates'));
        add_action('wp_ajax_rphub_schedule_update', array($this, 'ajax_schedule_update'));
        add_action('wp_ajax_rphub_rollback_update', array($this, 'ajax_rollback_update'));
        add_action('wp_ajax_rphub_get_update_history', array($this, 'ajax_get_update_history'));
        add_action('wp_ajax_rphub_check_individual_updates', array($this, 'ajax_check_individual_updates'));
        add_action('wp_ajax_rphub_execute_immediate_update', array($this, 'ajax_execute_immediate_update'));
        
        // Task queue (Action Scheduler)
        RPHUB_Scheduler::schedule('rphub_check_updates',  'hourly');
        RPHUB_Scheduler::schedule('rphub_perform_updates', 'twicedaily');
    }
    
    /**
     * Verificar actualizaciones disponibles para todos los sitios
     */
    public function check_site_updates() {
        $sites = RPHUB_Database::get_all_sites();
        
        // Filter sites with auto updates enabled
        $sites = array_filter($sites, function($site) {
            return RPHUB_Database::get_site_meta($site->id, 'auto_updates_enabled') === '1';
        });
        
        foreach ($sites as $site) {
            $this->check_individual_site_updates($site->id);
        }
    }
    
    /**
     * Verificar actualizaciones de un sitio específico
     */
    private function check_individual_site_updates($site_id) {
        $site_url = RPHUB_Database::get_site($site_id)->url;
        $wp_toolkit = new RP_Hub_WPToolkit_Integration();
        
        // Obtener actualizaciones disponibles
        $updates = $wp_toolkit->get_available_updates($site_id);
        
        if ($updates && !empty($updates['updates'])) {
            // Guardar actualizaciones pendientes
            RPHUB_Database::update_site_meta($site_id, 'pending_updates', $updates);
            RPHUB_Database::update_site_meta($site_id, 'last_update_check', current_time('mysql'));

            // Evaluar riesgo de plugins con Claude antes de decidir estrategia
            $risk_data = ['max_score' => 0.0, 'assessments' => []];
            if (class_exists('RPHUB_Risk_Scorer')) {
                $assessments = RPHUB_Risk_Scorer::get_instance()->score_site_updates($site_id, $updates);
                if (!empty($assessments)) {
                    $scores = array_column(array_values($assessments), 'risk_score');
                    RPHUB_Database::update_site_meta($site_id, 'update_risk_assessments', $assessments);
                    RPHUB_Database::update_site_meta($site_id, 'update_risk_checked_at', current_time('mysql'));
                    $risk_data = ['max_score' => !empty($scores) ? max($scores) : 0.0, 'assessments' => $assessments];
                }
            }

            // Determinar si aplicar automáticamente
            $auto_update_config = RPHUB_Database::get_site_meta($site_id, 'auto_update_config');

            if ($this->should_auto_update($updates, $auto_update_config, $risk_data)) {
                $this->schedule_site_update($site_id, $updates);
            }

            // Log del evento
            $this->log_update_event($site_id, 'updates_found', array(
                'count'    => count($updates['updates']),
                'types'    => array_keys($updates['updates']),
                'max_risk' => $risk_data['max_score'] ?? null,
            ));
        }
    }
    
    /**
     * Determinar si debe aplicar actualizaciones automáticamente.
     * $risk_data = ['max_score' => float, 'assessments' => array]
     */
    private function should_auto_update($updates, $config, $risk_data = []) {
        if (!$config || !is_array($config)) {
            return false;
        }

        // RiskScorer gate: si el riesgo máximo supera 0.6, no actualizar automáticamente
        $max_risk = $risk_data['max_score'] ?? 0.0;
        if ($max_risk > 0.6) {
            return false; // admin debe revisar y aprobar manualmente
        }

        // Verificar configuración por tipo
        foreach ($updates['updates'] as $type => $items) {
            if (!isset($config[$type]) || !$config[$type]['enabled']) {
                continue;
            }
            
            // Verificar criterios específicos
            if ($type === 'core') {
                if ($config[$type]['major_versions'] && $this->is_major_version_update($items)) {
                    return false; // No actualizar versiones mayores automáticamente
                }
            }
            
            if ($type === 'plugins') {
                foreach ($items as $plugin) {
                    if (in_array($plugin['slug'], $config['plugins']['excluded_plugins'])) {
                        continue; // Skip excluded plugins
                    }
                }
            }
        }
        
        // Verificar horario permitido
        if (isset($config['schedule'])) {
            return $this->is_within_update_window($config['schedule']);
        }
        
        return true;
    }
    
    /**
     * Verificar si estamos en ventana de actualizaciones
     */
    private function is_within_update_window($schedule) {
        $current_time = current_time('H:i');
        $current_day = current_time('w'); // 0 = Sunday, 6 = Saturday
        
        // Verificar día de la semana
        if (isset($schedule['days']) && !in_array($current_day, $schedule['days'])) {
            return false;
        }
        
        // Verificar horario
        if (isset($schedule['start_time']) && isset($schedule['end_time'])) {
            return ($current_time >= $schedule['start_time'] && $current_time <= $schedule['end_time']);
        }
        
        return true;
    }
    
    /**
     * Programar actualización de un sitio
     */
    private function schedule_site_update($site_id, $updates) {
        $scheduled_updates = get_option('rphub_scheduled_updates', array());
        
        $update_data = array(
            'site_id' => $site_id,
            'updates' => $updates,
            'scheduled_at' => current_time('mysql'),
            'status' => 'pending',
            'backup_required' => true
        );
        
        $scheduled_updates[] = $update_data;
        update_option('rphub_scheduled_updates', $scheduled_updates);
        
        $this->log_update_event($site_id, 'update_scheduled', $update_data);
    }
    
    /**
     * Ejecutar actualizaciones programadas
     */
    public function perform_scheduled_updates() {
        $scheduled_updates = get_option('rphub_scheduled_updates', array());
        
        foreach ($scheduled_updates as $key => $update) {
            if ($update['status'] === 'pending') {
                $result = $this->execute_site_update($update);
                
                // Actualizar estado
                $scheduled_updates[$key]['status'] = $result['success'] ? 'completed' : 'failed';
                $scheduled_updates[$key]['completed_at'] = current_time('mysql');
                $scheduled_updates[$key]['result'] = $result;
                
                if (!$result['success']) {
                    $scheduled_updates[$key]['error'] = $result['error'];
                }
            }
        }
        
        update_option('rphub_scheduled_updates', $scheduled_updates);
    }
    
    /**
     * Ejecutar actualización de un sitio
     */
    private function execute_site_update($update_data) {
        $site_id = $update_data['site_id'];
        
        try {
            // 1. Crear backup si es requerido
            if ($update_data['backup_required']) {
                $backup_result = $this->create_backup_before_update($site_id);
                if (!$backup_result['success']) {
                    throw new Exception('Failed to create backup: ' . $backup_result['error']);
                }
                $backup_id = $backup_result['backup_id'];
            }

            // Snapshot pre-actualización para Delta Reporter
            if (class_exists('RPHUB_Delta_Reporter')) {
                RPHUB_Delta_Reporter::get_instance()->capture_snapshot($site_id, 'pre_update');
            }

            // 2. Ejecutar actualizaciones
            $wp_toolkit = new RP_Hub_WPToolkit_Integration();
            $update_results = array();
            
            foreach ($update_data['updates']['updates'] as $type => $items) {
                $result = $wp_toolkit->perform_updates($site_id, $type, $items);
                $update_results[$type] = $result;
                
                if (!$result['success']) {
                    throw new Exception("Failed to update $type: " . $result['error']);
                }
            }
            
            // 3. Verificar sitio después de actualizaciones
            $health_check = $this->verify_site_health_after_update($site_id);
            
            if (!$health_check['success']) {
                // Rollback automático si falla la verificación
                $this->perform_automatic_rollback($site_id, $backup_id ?? '');
                throw new Exception('Site health check failed after update');
            }
            
            // 4. Log de éxito + snapshot post-actualización
            $this->log_update_event($site_id, 'update_completed', array(
                'updates'   => $update_results,
                'backup_id' => isset($backup_id) ? $backup_id : null,
            ));
            if (class_exists('RPHUB_Delta_Reporter')) {
                RPHUB_Delta_Reporter::get_instance()->capture_snapshot($site_id, 'post_update');
            }
            
            // 5. Actualizar metadatos del sitio
            RPHUB_Database::update_site_meta($site_id, 'last_update', current_time('mysql'));
            RPHUB_Database::update_site_meta($site_id, 'pending_updates', '');

            // 6. Empujar portal_cache a Care para que el panel del cliente se actualice
            if (class_exists('RPHUB_Backblaze_Integration')) {
                RPHUB_Backblaze_Integration::get_instance()->pushPortalCacheToCare($site_id);
            }

            return array(
                'success' => true,
                'updates' => $update_results,
                'backup_id' => isset($backup_id) ? $backup_id : null
            );
            
        } catch (Exception $e) {
            $this->log_update_event($site_id, 'update_failed', array(
                'error' => $e->getMessage(),
                'backup_id' => isset($backup_id) ? $backup_id : null
            ));
            
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Crear backup antes de actualización.
     * Dispara un backup en Care (que lo sube a Backblaze B2) y espera confirmación.
     */
    public function create_backup_before_update($site_id) {
        $site  = RPHUB_Database::get_site($site_id);
        $error = null;

        if (!$site || empty($site->url) || empty($site->token)) {
            $error = 'Sitio no encontrado o sin token de acceso';
        } else {
            $response = wp_remote_post(
                trailingslashit($site->url) . 'wp-json/replanta/v1/run',
                array(
                    'timeout' => 60,
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $site->token,
                        'Content-Type'  => 'application/json',
                    ),
                    'body' => wp_json_encode(array('task' => 'backup', 'force' => true)),
                )
            );

            if (is_wp_error($response)) {
                $error = $response->get_error_message();
            } else {
                $code = wp_remote_retrieve_response_code($response);
                $body = json_decode(wp_remote_retrieve_body($response), true);
                if ($code !== 200 || empty($body['success'])) {
                    $error = 'Care backup devolvió HTTP ' . $code;
                }
            }
        }

        if ($error) {
            return array('success' => false, 'error' => $error);
        }

        // Dar 30s para que el backup se complete (corre en Action Scheduler de Care)
        sleep(30);

        $backup_id = 'pre_update_' . $site_id . '_' . gmdate('Ymd_His');
        RPHUB_Database::update_site_meta($site_id, 'last_pre_update_backup', $backup_id);

        return array('success' => true, 'backup_id' => $backup_id);
    }
    
    /**
     * Verificar salud del sitio después de actualización
     */
    private function verify_site_health_after_update($site_id) {
        $site_url = RPHUB_Database::get_site($site_id)->url;
        
        // Verificar que el sitio responda
        $response = wp_remote_get($site_url, array(
            'timeout' => 30,
            'sslverify' => true
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => 'Site not responding: ' . $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code !== 200) {
            return array(
                'success' => false,
                'error' => 'Site returning error code: ' . $response_code
            );
        }
        
        // Verificar contenido básico
        $body = wp_remote_retrieve_body($response);
        if (strpos($body, '<html') === false) {
            return array(
                'success' => false,
                'error' => 'Site not returning valid HTML'
            );
        }
        
        // Ejecutar PageSpeed test para verificar performance
        $performance_test = null;
        if (class_exists('RP_Hub_PageSpeed_Integration')) {
            $pagespeed = new RP_Hub_PageSpeed_Integration();
            $performance_test = $pagespeed->run_pagespeed_analysis($site_id);
        }
        
        return array(
            'success' => true,
            'response_code' => $response_code,
            'performance' => $performance_test
        );
    }
    
    /**
     * Rollback automático tras fallo de health-check.
     * Intenta revertir vía WP Toolkit si está disponible;
     * si no, alerta al admin con el backup_id de referencia para restauración manual.
     */
    private function perform_automatic_rollback($site_id, $backup_id) {
        $rollback_result = array('success' => false, 'error' => 'WP Toolkit no disponible para rollback automático');

        if (class_exists('RP_Hub_WPToolkit_Integration')) {
            $wp_toolkit = new RP_Hub_WPToolkit_Integration();
            if (method_exists($wp_toolkit, 'rollback_updates')) {
                $result = $wp_toolkit->rollback_updates($site_id);
                $rollback_result = is_wp_error($result)
                    ? array('success' => false, 'error' => $result->get_error_message())
                    : array('success' => true, 'result' => $result);
            }
        }

        // Alerta al admin si el rollback automático falló
        if (!$rollback_result['success']) {
            $site    = RPHUB_Database::get_site($site_id);
            $subject = sprintf('[ROLLBACK REQUERIDO] %s — backup ref: %s', $site->name ?? $site_id, $backup_id);
            $message = "El health-check post-update falló y el rollback automático no pudo completarse.\n\nSitio: {$site->url}\nBackup de referencia: {$backup_id}\n\nRevisa el sitio y restaura manualmente desde Backblaze B2.";

            if (class_exists('RPHUB_Alerting')) {
                RPHUB_Alerting::send_alert(array(
                    'site_id'  => $site_id,
                    'type'     => 'rollback_required',
                    'severity' => 'critical',
                    'subject'  => $subject,
                    'message'  => $message,
                    'data'     => array('backup_id' => $backup_id),
                ));
            } else {
                wp_mail(get_option('admin_email'), $subject, $message);
            }
        }

        $this->log_update_event($site_id, 'automatic_rollback', array(
            'backup_id' => $backup_id,
            'result'    => $rollback_result,
        ));

        return $rollback_result;
    }
    
    /**
     * Log de eventos de actualización
     */
    private function log_update_event($site_id, $event_type, $data = array()) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'site_id' => $site_id,
            'event_type' => $event_type,
            'data' => $data
        );
        
        $update_history = RPHUB_Database::get_site_meta($site_id, 'update_history');
        if (!is_array($update_history)) {
            $update_history = array();
        }
        
        array_unshift($update_history, $log_entry);
        
        // Mantener solo los últimos 50 eventos
        $update_history = array_slice($update_history, 0, 50);
        
        RPHUB_Database::update_site_meta($site_id, 'update_history', $update_history);
    }
    
    /**
     * AJAX: Configurar actualizaciones automáticas
     */
    public function ajax_configure_auto_updates() {
        check_ajax_referer('rphub_dashboard_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $site_id = intval($_POST['site_id']);
        $config = $_POST['config'];
        
        // Validar configuración
        $validated_config = $this->validate_auto_update_config($config);
        
        if (!$validated_config) {
            wp_send_json_error('Invalid configuration');
        }
        
        // Guardar configuración
        RPHUB_Database::update_site_meta($site_id, 'auto_update_config', $validated_config);
        RPHUB_Database::update_site_meta($site_id, 'auto_updates_enabled', $validated_config['enabled'] ? '1' : '0');
        
        wp_send_json_success(array(
            'message' => 'Configuración de actualizaciones automáticas guardada',
            'config' => $validated_config
        ));
    }
    
    /**
     * AJAX: Programar actualización manual
     */
    public function ajax_schedule_update() {
        check_ajax_referer('rphub_dashboard_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $site_id = intval($_POST['site_id']);
        $update_types = $_POST['update_types'];
        $schedule_time = $_POST['schedule_time'];
        
        // Obtener actualizaciones disponibles
        $wp_toolkit = new RP_Hub_WPToolkit_Integration();
        $available_updates = $wp_toolkit->get_available_updates($site_id);
        
        // Filtrar solo los tipos seleccionados
        $selected_updates = array();
        foreach ($update_types as $type) {
            if (isset($available_updates['updates'][$type])) {
                $selected_updates[$type] = $available_updates['updates'][$type];
            }
        }
        
        if (empty($selected_updates)) {
            wp_send_json_error('No hay actualizaciones disponibles para los tipos seleccionados');
        }
        
        // Programar actualización
        $update_data = array(
            'site_id' => $site_id,
            'updates' => array('updates' => $selected_updates),
            'scheduled_at' => $schedule_time,
            'status' => 'scheduled',
            'backup_required' => true,
            'manual' => true
        );
        
        $scheduled_updates = get_option('rphub_scheduled_updates', array());
        $scheduled_updates[] = $update_data;
        update_option('rphub_scheduled_updates', $scheduled_updates);
        
        wp_send_json_success(array(
            'message' => 'Actualización programada correctamente',
            'scheduled_time' => $schedule_time
        ));
    }
    
    /**
     * AJAX: Rollback de actualización
     */
    public function ajax_rollback_update() {
        check_ajax_referer('rphub_dashboard_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $site_id = intval($_POST['site_id']);
        $backup_id = $_POST['backup_id'];
        
        $rollback_result = $this->perform_automatic_rollback($site_id, $backup_id);
        
        if ($rollback_result['success']) {
            wp_send_json_success(array(
                'message' => 'Rollback completado exitosamente'
            ));
        } else {
            wp_send_json_error($rollback_result['error']);
        }
    }
    
    /**
     * AJAX: Obtener historial de actualizaciones
     */
    public function ajax_get_update_history() {
        check_ajax_referer('rphub_dashboard_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $site_id = intval($_POST['site_id']);
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 20;
        
        $update_history = RPHUB_Database::get_site_meta($site_id, 'update_history');
        if (!is_array($update_history)) {
            $update_history = array();
        }
        
        // Formatear para frontend
        $formatted_history = array_slice($update_history, 0, $limit);
        
        wp_send_json_success($formatted_history);
    }

    /**
     * AJAX: Verificar actualizaciones de un sitio bajo demanda
     */
    public function ajax_check_individual_updates() {
        check_ajax_referer('rphub_dashboard_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }

        $site_id = intval($_POST['site_id'] ?? 0);
        if (!$site_id || !RPHUB_Database::get_site($site_id)) {
            wp_send_json_error('Sitio no encontrado');
        }

        $this->check_individual_site_updates($site_id);

        wp_send_json_success(array(
            'message' => 'Verificación de actualizaciones completada',
            'pending_updates' => RPHUB_Database::get_site_meta($site_id, 'pending_updates'),
        ));
    }

    /**
     * AJAX: Ejecutar actualización inmediata (individual o todas)
     */
    public function ajax_execute_immediate_update() {
        check_ajax_referer('rphub_dashboard_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }

        $site_id = intval($_POST['site_id'] ?? 0);
        $update_type = sanitize_text_field($_POST['update_type'] ?? 'all');
        $create_backup = !empty($_POST['create_backup']);

        if (!$site_id || !RPHUB_Database::get_site($site_id)) {
            wp_send_json_error('Sitio no encontrado');
        }

        $wp_toolkit = new RP_Hub_WPToolkit_Integration();
        $available_updates = $wp_toolkit->get_available_updates($site_id);

        if (empty($available_updates['updates'])) {
            wp_send_json_error('No hay actualizaciones disponibles');
        }

        $selected_updates = array();
        if ($update_type === 'all') {
            $selected_updates = $available_updates['updates'];
        } elseif (isset($available_updates['updates'][$update_type])) {
            $selected_updates[$update_type] = $available_updates['updates'][$update_type];
        }

        if (empty($selected_updates)) {
            wp_send_json_error('No hay actualizaciones disponibles para el tipo seleccionado');
        }

        $result = $this->execute_site_update(array(
            'site_id' => $site_id,
            'updates' => array('updates' => $selected_updates),
            'backup_required' => $create_backup,
        ));

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => 'Actualización completada exitosamente',
                'result' => $result,
            ));
        } else {
            wp_send_json_error($result['error']);
        }
    }

    /**
     * Validar configuración de actualizaciones automáticas
     */
    private function validate_auto_update_config($config) {
        $default_config = array(
            'enabled' => false,
            'core' => array(
                'enabled' => false,
                'major_versions' => false
            ),
            'plugins' => array(
                'enabled' => false,
                'excluded_plugins' => array()
            ),
            'themes' => array(
                'enabled' => false,
                'excluded_themes' => array()
            ),
            'schedule' => array(
                'days' => array(0, 6), // Weekends
                'start_time' => '02:00',
                'end_time' => '06:00'
            ),
            'backup_before_update' => true,
            'rollback_on_failure' => true
        );
        
        return wp_parse_args($config, $default_config);
    }
    
    /**
     * Verificar si es actualización de versión mayor
     */
    private function is_major_version_update($core_updates) {
        if (empty($core_updates)) {
            return false;
        }
        
        $current_version = get_bloginfo('version');
        $new_version = $core_updates[0]['new_version'];
        
        $current_major = intval(explode('.', $current_version)[0]);
        $new_major = intval(explode('.', $new_version)[0]);
        
        return $new_major > $current_major;
    }
    
    /**
     * Obtener estadísticas de actualizaciones
     */
    public function get_update_statistics($site_id) {
        $update_history = RPHUB_Database::get_site_meta($site_id, 'update_history');
        
        if (!is_array($update_history)) {
            return array(
                'total_updates' => 0,
                'successful_updates' => 0,
                'failed_updates' => 0,
                'rollbacks' => 0,
                'last_update' => null
            );
        }
        
        $stats = array(
            'total_updates' => 0,
            'successful_updates' => 0,
            'failed_updates' => 0,
            'rollbacks' => 0,
            'last_update' => null
        );
        
        foreach ($update_history as $event) {
            switch ($event['event_type']) {
                case 'update_completed':
                    $stats['successful_updates']++;
                    $stats['total_updates']++;
                    if (!$stats['last_update']) {
                        $stats['last_update'] = $event['timestamp'];
                    }
                    break;
                    
                case 'update_failed':
                    $stats['failed_updates']++;
                    $stats['total_updates']++;
                    break;
                    
                case 'automatic_rollback':
                    $stats['rollbacks']++;
                    break;
            }
        }
        
        return $stats;
    }
    
    /**
     * Renderizar panel de actualizaciones en dashboard
     */
    public function render_updates_panel($site_id) {
        $pending_updates = RPHUB_Database::get_site_meta($site_id, 'pending_updates');
        $auto_config = RPHUB_Database::get_site_meta($site_id, 'auto_update_config');
        $update_stats = $this->get_update_statistics($site_id);
        
        include plugin_dir_path(__FILE__) . '../templates/updates-panel.php';
    }
}

// Inicializar
new RphubSmartUpdates();
