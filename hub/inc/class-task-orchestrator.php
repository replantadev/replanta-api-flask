<?php
/**
 * Task Orchestrator Class
 * Manages and executes tasks across multiple sites
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPHUB_Task_Orchestrator {
    
    private $table_tasks;
    private $table_sites;
    private $api_client;
    
    public function __construct() {
        global $wpdb;
        $this->table_tasks = $wpdb->prefix . 'rphub_tasks';
        $this->table_sites = $wpdb->prefix . 'rphub_sites';
        $this->api_client = new RPHUB_API_Client();
        
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // AJAX hooks for task management
        add_action('wp_ajax_rphub_execute_task', [$this, 'ajax_execute_task']);
        add_action('wp_ajax_rphub_bulk_task', [$this, 'ajax_bulk_task']);
        add_action('wp_ajax_rphub_cancel_task', [$this, 'ajax_cancel_task']);
        add_action('wp_ajax_rphub_get_task_status', [$this, 'ajax_get_task_status']);
        
        // Cron hooks
        add_action('rphub_sync_site', [$this, 'sync_site_data']);
        add_action('rphub_execute_scheduled_task', [$this, 'execute_scheduled_task']);
    }
    
    /**
     * Schedule a task for execution
     */
    public function schedule_task($site_id, $task_type, $params = [], $priority = 5, $schedule_time = null) {
        global $wpdb;
        
        if ($schedule_time === null) {
            $schedule_time = current_time('mysql');
        }
        
        $task_data = [
            'site_id' => $site_id,
            'task_type' => $task_type,
            'status' => 'pending',
            'priority' => $priority,
            'scheduled_at' => $schedule_time,
            'created_at' => current_time('mysql')
        ];
        
        if (!empty($params)) {
            $task_data['result'] = json_encode(['params' => $params]);
        }
        
        $result = $wpdb->insert(
            $this->table_tasks,
            $task_data,
            ['%d', '%s', '%s', '%d', '%s', '%s', '%s']
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Error al programar la tarea');
        }
        
        $task_id = $wpdb->insert_id;
        
        // Schedule WordPress cron event
        if ($schedule_time <= current_time('mysql')) {
            wp_schedule_single_event(time(), 'rphub_execute_scheduled_task', [$task_id]);
        } else {
            $timestamp = strtotime($schedule_time);
            wp_schedule_single_event($timestamp, 'rphub_execute_scheduled_task', [$task_id]);
        }
        
        return $task_id;
    }
    
    /**
     * Execute a task immediately
     */
    public function execute_task($task_id, $force = false) {
        global $wpdb;
        
        $task = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_tasks} WHERE id = %d", $task_id)
        );
        
        if (!$task) {
            return new WP_Error('task_not_found', 'Tarea no encontrada');
        }
        
        if ($task->status === 'running' && !$force) {
            return new WP_Error('task_running', 'La tarea ya está en ejecución');
        }
        
        if ($task->status === 'completed' && !$force) {
            return new WP_Error('task_completed', 'La tarea ya está completada');
        }
        
        // Get site information
        $site = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_sites} WHERE id = %d", $task->site_id)
        );
        
        if (!$site) {
            return new WP_Error('site_not_found', 'Sitio no encontrado');
        }
        
        if ($site->status !== 'active') {
            return new WP_Error('site_inactive', 'El sitio no está activo');
        }
        
        // Update task status to running
        $wpdb->update(
            $this->table_tasks,
            [
                'status' => 'running',
                'started_at' => current_time('mysql')
            ],
            ['id' => $task_id],
            ['%s', '%s'],
            ['%d']
        );
        
        // Execute the task based on type
        $result = $this->execute_task_by_type($site, $task);
        
        // Update task with result
        if (is_wp_error($result)) {
            $update_data = [
                'status' => 'failed',
                'error_message' => $result->get_error_message(),
                'completed_at' => current_time('mysql'),
                'retry_count' => $task->retry_count + 1
            ];
            
            // Schedule retry if under limit
            if ($task->retry_count < $task->max_retries) {
                $retry_delay = pow(2, $task->retry_count) * 60; // Exponential backoff
                $result_data = !empty($task->result) ? json_decode($task->result, true) : [];
                $this->schedule_task(
                    $task->site_id,
                    $task->task_type,
                    $result_data['params'] ?? [],
                    $task->priority,
                    date('Y-m-d H:i:s', time() + $retry_delay)
                );
            }
        } else {
            $update_data = [
                'status' => 'completed',
                'result' => json_encode($result),
                'completed_at' => current_time('mysql')
            ];
        }
        
        $wpdb->update(
            $this->table_tasks,
            $update_data,
            ['id' => $task_id],
            array_fill(0, count($update_data), '%s'),
            ['%d']
        );
        
        return $result;
    }
    
    /**
     * Execute task based on its type
     */
    private function execute_task_by_type($site, $task) {
        $result_data = !empty($task->result) ? json_decode($task->result, true) : [];
        $params = $result_data['params'] ?? [];
        
        switch ($task->task_type) {
            case 'update_plugins':
                return $this->api_client->install_updates($site->url, $site->token, $params);
                
            case 'update_themes':
                return $this->api_client->install_updates($site->url, $site->token, $params);
                
            case 'update_core':
                return $this->api_client->install_updates($site->url, $site->token, ['core' => true]);
                
            case 'backup':
                $backup_type = $params['type'] ?? 'full';
                return $this->api_client->create_backup($site->url, $site->token, $backup_type);
                
            case 'security_scan':
                return $this->api_client->run_security_scan($site->url, $site->token);
                
            case 'cache_clear':
                return $this->api_client->clear_cache($site->url, $site->token);
                
            case 'cache_optimize':
                return $this->api_client->optimize_cache($site->url, $site->token);
                
            case 'sync_data':
                return $this->sync_site_data($site->id);
                
            case 'test_connection':
                return $this->api_client->test_connection($site->url, $site->token);
                
            case 'performance_check':
                return $this->api_client->get_performance_report($site->url, $site->token);
                
            default:
                return new WP_Error('unknown_task', 'Tipo de tarea desconocido: ' . $task->task_type);
        }
    }
    
    /**
     * Get pending tasks
     */
    public function get_pending_tasks($limit = 10) {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.*, s.name as site_name, s.url as site_url 
                 FROM {$this->table_tasks} t 
                 JOIN {$this->table_sites} s ON t.site_id = s.id 
                 WHERE t.status = 'pending' 
                 AND t.scheduled_at <= %s 
                 ORDER BY t.priority DESC, t.scheduled_at ASC 
                 LIMIT %d",
                current_time('mysql'),
                $limit
            )
        );
    }
    
    /**
     * Get running tasks
     */
    public function get_running_tasks() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT t.*, s.name as site_name, s.url as site_url 
             FROM {$this->table_tasks} t 
             JOIN {$this->table_sites} s ON t.site_id = s.id 
             WHERE t.status = 'running' 
             ORDER BY t.started_at ASC"
        );
    }
    
    /**
     * Get task history
     */
    public function get_task_history($site_id = null, $limit = 50) {
        global $wpdb;
        
        $where_clause = '';
        $params = [$limit];
        
        if ($site_id) {
            $where_clause = 'WHERE t.site_id = %d';
            array_unshift($params, $site_id);
        }
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.*, s.name as site_name, s.url as site_url 
                 FROM {$this->table_tasks} t 
                 JOIN {$this->table_sites} s ON t.site_id = s.id 
                 {$where_clause}
                 ORDER BY t.created_at DESC 
                 LIMIT %d",
                ...$params
            )
        );
    }
    
    /**
     * Cancel a task
     */
    public function cancel_task($task_id) {
        global $wpdb;
        
        $task = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_tasks} WHERE id = %d", $task_id)
        );
        
        if (!$task) {
            return new WP_Error('task_not_found', 'Tarea no encontrada');
        }
        
        if ($task->status === 'completed') {
            return new WP_Error('task_completed', 'No se puede cancelar una tarea completada');
        }
        
        $result = $wpdb->update(
            $this->table_tasks,
            [
                'status' => 'cancelled',
                'completed_at' => current_time('mysql'),
                'error_message' => 'Cancelada por el usuario'
            ],
            ['id' => $task_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Clean old completed tasks
     */
    public function cleanup_old_tasks($days = 30) {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_tasks} WHERE status = %s AND completed_at < %s",
                'completed',
                $cutoff_date
            )
        );
        
        return $result;
    }
    
    /**
     * Schedule bulk tasks for multiple sites
     */
    public function schedule_bulk_task($site_ids, $task_type, $params = [], $priority = 5) {
        $task_ids = [];
        $errors = [];
        
        foreach ($site_ids as $site_id) {
            $result = $this->schedule_task($site_id, $task_type, $params, $priority);
            
            if (is_wp_error($result)) {
                $errors[$site_id] = $result->get_error_message();
            } else {
                $task_ids[$site_id] = $result;
            }
        }
        
        return [
            'success' => $task_ids,
            'errors' => $errors
        ];
    }
    
    /**
     * Run hourly tasks
     */
    public static function run_hourly_tasks() {
        $orchestrator = new self();
        
        // Process pending tasks
        $pending_tasks = $orchestrator->get_pending_tasks(5);
        
        foreach ($pending_tasks as $task) {
            $orchestrator->execute_task($task->id);
            
            // Small delay between tasks
            sleep(1);
        }
        
        // Sync site data for sites not checked in last hour
        $site_manager = new RPHUB_Site_Manager();
        $sites = $site_manager->get_sites([
            'status' => 'active'
        ]);
        
        foreach ($sites as $site) {
            $last_check = strtotime($site->last_check);
            if ($last_check < strtotime('-1 hour')) {
                $orchestrator->schedule_task($site->id, 'sync_data', [], 3);
            }
        }
        
        // Monitor WHM connection if enabled
        $settings = get_option('rphub_settings', []);
        if (isset($settings['whm_enabled']) && $settings['whm_enabled'] && 
            isset($settings['whm_persistent_tokens']) && $settings['whm_persistent_tokens']) {
            $whm_integration = new RPHUB_WHM_Integration();
            $whm_integration->monitor_whm_connection();
        }
    }
    
    /**
     * Run daily tasks
     */
    public static function run_daily_tasks() {
        $orchestrator = new self();
        
        // Clean old completed tasks
        $orchestrator->cleanup_old_tasks(30);
        
        // Clean old notifications (read, older than 30 days)
        if (class_exists('RPHUB_Notifications')) {
            $notifications = new RPHUB_Notifications();
            $notifications->cleanup_old_notifications(30);
        }
        
        // Clean old activities, reports, pagespeed data, and security logs (90 days)
        if (class_exists('RPHUB_Database')) {
            $db = new RPHUB_Database();
            $db->cleanup_old_data(90);
        }
        
        // Schedule daily backups for sites with appropriate plans
        $site_manager = new RPHUB_Site_Manager();
        $sites = $site_manager->get_sites([
            'status' => 'active'
        ]);
        
        foreach ($sites as $site) {
            // Daily backups for Raiz and Ecosistema plans
            if (in_array($site->plan, ['raiz', 'ecosistema'])) {
                $orchestrator->schedule_task($site->id, 'backup', ['type' => 'full'], 4);
            }
            
            // Weekly security scans for all plans
            if (date('w') == 1) { // Monday
                $orchestrator->schedule_task($site->id, 'security_scan', [], 3);
            }
            
            // Performance checks for Ecosistema plan
            if ($site->plan === 'ecosistema') {
                $orchestrator->schedule_task($site->id, 'performance_check', [], 3);
            }
        }
    }
    
    /**
     * Execute scheduled task (cron callback)
     */
    public function execute_scheduled_task($task_id) {
        $this->execute_task($task_id);
    }
    
    /**
     * Sync site data
     */
    public function sync_site_data($site_id) {
        $site_manager = new RPHUB_Site_Manager();
        return $site_manager->sync_site_data($site_id);
    }
    
    // AJAX Handlers
    public function ajax_execute_task() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $task_type = sanitize_text_field($_POST['task_type'] ?? '');
        $site_id = intval($_POST['site_id'] ?? 0);
        $params = $_POST['params'] ?? [];
        
        if (empty($task_type) || empty($site_id)) {
            wp_send_json_error('Faltan parámetros requeridos');
        }
        
        $task_id = $this->schedule_task($site_id, $task_type, $params, 1);
        
        if (is_wp_error($task_id)) {
            wp_send_json_error($task_id->get_error_message());
        }
        
        // Execute immediately
        $result = $this->execute_task($task_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success([
                'task_id' => $task_id,
                'result' => $result,
                'message' => 'Tarea ejecutada correctamente'
            ]);
        }
    }
    
    public function ajax_bulk_task() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $task_type = sanitize_text_field($_POST['task_type'] ?? '');
        $site_ids = array_map('intval', $_POST['site_ids'] ?? []);
        $params = $_POST['params'] ?? [];
        
        if (empty($task_type) || empty($site_ids)) {
            wp_send_json_error('Faltan parámetros requeridos');
        }
        
        $result = $this->schedule_bulk_task($site_ids, $task_type, $params);
        
        wp_send_json_success([
            'scheduled' => count($result['success']),
            'errors' => count($result['errors']),
            'details' => $result
        ]);
    }
    
    public function ajax_cancel_task() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $task_id = intval($_POST['task_id'] ?? 0);
        
        if (empty($task_id)) {
            wp_send_json_error('ID de tarea requerido');
        }
        
        $result = $this->cancel_task($task_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else if ($result) {
            wp_send_json_success('Tarea cancelada correctamente');
        } else {
            wp_send_json_error('Error al cancelar la tarea');
        }
    }
    
    public function ajax_get_task_status() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $task_id = intval($_POST['task_id'] ?? 0);
        
        if (empty($task_id)) {
            wp_send_json_error('ID de tarea requerido');
        }
        
        global $wpdb;
        $task = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT t.*, s.name as site_name 
                 FROM {$this->table_tasks} t 
                 JOIN {$this->table_sites} s ON t.site_id = s.id 
                 WHERE t.id = %d",
                $task_id
            )
        );
        
        if (!$task) {
            wp_send_json_error('Tarea no encontrada');
        }
        
        wp_send_json_success($task);
    }
}
