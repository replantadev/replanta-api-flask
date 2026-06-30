<?php
/**
 * Bulk Actions Class
 * Handles bulk operations across multiple sites
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPHUB_Bulk_Actions {
    
    private $site_manager;
    private $task_orchestrator;
    private $api_client;
    
    public function __construct() {
        $this->site_manager = new RPHUB_Site_Manager();
        $this->task_orchestrator = new RPHUB_Task_Orchestrator();
        $this->api_client = new RPHUB_API_Client();
        
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // AJAX hooks for bulk actions
        add_action('wp_ajax_rphub_bulk_update_plugins', [$this, 'ajax_bulk_update_plugins']);
        add_action('wp_ajax_rphub_bulk_update_themes', [$this, 'ajax_bulk_update_themes']);
        add_action('wp_ajax_rphub_bulk_update_core', [$this, 'ajax_bulk_update_core']);
        add_action('wp_ajax_rphub_bulk_backup', [$this, 'ajax_bulk_backup']);
        add_action('wp_ajax_rphub_bulk_security_scan', [$this, 'ajax_bulk_security_scan']);
        add_action('wp_ajax_rphub_bulk_cache_clear', [$this, 'ajax_bulk_cache_clear']);
        add_action('wp_ajax_rphub_bulk_sync_data', [$this, 'ajax_bulk_sync_data']);
        add_action('wp_ajax_rphub_bulk_test_connection', [$this, 'ajax_bulk_test_connection']);
    }
    
    /**
     * Execute bulk action on multiple sites
     */
    public function execute_bulk_action($action, $site_ids, $params = []) {
        if (empty($site_ids) || !is_array($site_ids)) {
            return new WP_Error('invalid_sites', 'No hay sitios seleccionados');
        }
        
        $results = [
            'success' => [],
            'errors' => [],
            'total' => count($site_ids)
        ];
        
        foreach ($site_ids as $site_id) {
            $result = $this->execute_single_action($action, $site_id, $params);
            
            if (is_wp_error($result)) {
                $results['errors'][$site_id] = $result->get_error_message();
            } else {
                $results['success'][$site_id] = $result;
            }
            
            // Small delay between actions to avoid overwhelming servers
            usleep(500000); // 0.5 seconds
        }
        
        return $results;
    }
    
    /**
     * Execute single action on one site
     */
    private function execute_single_action($action, $site_id, $params = []) {
        $site = $this->site_manager->get_site($site_id);
        if (!$site) {
            return new WP_Error('site_not_found', 'Sitio no encontrado');
        }
        
        if ($site->status !== 'active') {
            return new WP_Error('site_inactive', 'El sitio no está activo');
        }
        
        switch ($action) {
            case 'update_plugins':
                return $this->update_plugins($site, $params);
                
            case 'update_themes':
                return $this->update_themes($site, $params);
                
            case 'update_core':
                return $this->update_core($site, $params);
                
            case 'backup':
                return $this->create_backup($site, $params);
                
            case 'security_scan':
                return $this->security_scan($site, $params);
                
            case 'cache_clear':
                return $this->cache_clear($site, $params);
                
            case 'cache_optimize':
                return $this->cache_optimize($site, $params);
                
            case 'sync_data':
                return $this->sync_data($site, $params);
                
            case 'test_connection':
                return $this->test_connection($site, $params);
                
            default:
                return new WP_Error('unknown_action', 'Acción desconocida: ' . $action);
        }
    }
    
    /**
     * Update plugins on site
     */
    private function update_plugins($site, $params) {
        // Schedule task for better tracking
        $task_id = $this->task_orchestrator->schedule_task(
            $site->id,
            'update_plugins',
            $params,
            1 // High priority
        );
        
        if (is_wp_error($task_id)) {
            return $task_id;
        }
        
        // Execute immediately
        return $this->task_orchestrator->execute_task($task_id);
    }
    
    /**
     * Update themes on site
     */
    private function update_themes($site, $params) {
        $task_id = $this->task_orchestrator->schedule_task(
            $site->id,
            'update_themes',
            $params,
            1
        );
        
        if (is_wp_error($task_id)) {
            return $task_id;
        }
        
        return $this->task_orchestrator->execute_task($task_id);
    }
    
    /**
     * Update WordPress core on site
     */
    private function update_core($site, $params) {
        $task_id = $this->task_orchestrator->schedule_task(
            $site->id,
            'update_core',
            $params,
            1
        );
        
        if (is_wp_error($task_id)) {
            return $task_id;
        }
        
        return $this->task_orchestrator->execute_task($task_id);
    }
    
    /**
     * Create backup on site
     */
    private function create_backup($site, $params) {
        $backup_type = $params['type'] ?? 'full';
        
        $task_id = $this->task_orchestrator->schedule_task(
            $site->id,
            'backup',
            ['type' => $backup_type],
            2
        );
        
        if (is_wp_error($task_id)) {
            return $task_id;
        }
        
        return $this->task_orchestrator->execute_task($task_id);
    }
    
    /**
     * Run security scan on site
     */
    private function security_scan($site, $params) {
        $task_id = $this->task_orchestrator->schedule_task(
            $site->id,
            'security_scan',
            $params,
            2
        );
        
        if (is_wp_error($task_id)) {
            return $task_id;
        }
        
        return $this->task_orchestrator->execute_task($task_id);
    }
    
    /**
     * Clear cache on site
     */
    private function cache_clear($site, $params) {
        $task_id = $this->task_orchestrator->schedule_task(
            $site->id,
            'cache_clear',
            $params,
            3
        );
        
        if (is_wp_error($task_id)) {
            return $task_id;
        }
        
        return $this->task_orchestrator->execute_task($task_id);
    }
    
    /**
     * Optimize cache on site
     */
    private function cache_optimize($site, $params) {
        $task_id = $this->task_orchestrator->schedule_task(
            $site->id,
            'cache_optimize',
            $params,
            3
        );
        
        if (is_wp_error($task_id)) {
            return $task_id;
        }
        
        return $this->task_orchestrator->execute_task($task_id);
    }
    
    /**
     * Sync site data
     */
    private function sync_data($site, $params) {
        return $this->site_manager->sync_site_data($site->id);
    }
    
    /**
     * Test connection to site
     */
    private function test_connection($site, $params) {
        return $this->site_manager->test_site_connection($site->id);
    }
    
    /**
     * Schedule bulk action for execution
     */
    public function schedule_bulk_action($action, $site_ids, $params = [], $schedule_time = null) {
        $results = [
            'scheduled' => [],
            'errors' => [],
            'total' => count($site_ids)
        ];
        
        foreach ($site_ids as $site_id) {
            $task_id = $this->task_orchestrator->schedule_task(
                $site_id,
                $action,
                $params,
                3, // Normal priority for scheduled tasks
                $schedule_time
            );
            
            if (is_wp_error($task_id)) {
                $results['errors'][$site_id] = $task_id->get_error_message();
            } else {
                $results['scheduled'][$site_id] = $task_id;
            }
        }
        
        return $results;
    }
    
    /**
     * Get bulk action status
     */
    public function get_bulk_action_status($task_ids) {
        global $wpdb;
        
        if (empty($task_ids) || !is_array($task_ids)) {
            return [];
        }
        
        $placeholders = implode(',', array_fill(0, count($task_ids), '%d'));
        
        $tasks = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.*, s.name as site_name 
                 FROM {$wpdb->prefix}rphub_tasks t 
                 JOIN {$wpdb->prefix}rphub_sites s ON t.site_id = s.id 
                 WHERE t.id IN ($placeholders) 
                 ORDER BY t.created_at DESC",
                ...$task_ids
            )
        );
        
        $status = [
            'pending' => 0,
            'running' => 0,
            'completed' => 0,
            'failed' => 0,
            'cancelled' => 0,
            'tasks' => $tasks
        ];
        
        foreach ($tasks as $task) {
            $status[$task->status]++;
        }
        
        return $status;
    }
    
    /**
     * Filter sites by criteria for bulk actions
     */
    public function filter_sites_for_bulk_action($criteria) {
        $args = [];
        
        // Filter by plan
        if (!empty($criteria['plan'])) {
            $args['plan'] = $criteria['plan'];
        }
        
        // Filter by status
        if (!empty($criteria['status'])) {
            $args['status'] = $criteria['status'];
        }
        
        // Get all matching sites
        $sites = $this->site_manager->get_sites($args);
        
        // Additional filtering
        $filtered_sites = [];
        
        foreach ($sites as $site) {
            $include = true;
            
            // Filter by health score
            if (isset($criteria['min_health_score'])) {
                if ($site->health_score < intval($criteria['min_health_score'])) {
                    $include = false;
                }
            }
            
            // Filter by updates available
            if (isset($criteria['has_updates']) && $criteria['has_updates']) {
                if ($site->updates_available == 0) {
                    $include = false;
                }
            }
            
            // Filter by security issues
            if (isset($criteria['has_security_issues']) && $criteria['has_security_issues']) {
                if ($site->security_issues == 0) {
                    $include = false;
                }
            }
            
            // Filter by last check time
            if (isset($criteria['last_check_hours'])) {
                $hours_ago = intval($criteria['last_check_hours']);
                $cutoff = date('Y-m-d H:i:s', strtotime("-{$hours_ago} hours"));
                
                if ($site->last_check && $site->last_check > $cutoff) {
                    $include = false;
                }
            }
            
            if ($include) {
                $filtered_sites[] = $site;
            }
        }
        
        return $filtered_sites;
    }
    
    // AJAX Handlers
    public function ajax_bulk_update_plugins() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $site_ids = array_map('intval', $_POST['site_ids'] ?? []);
        $params = $_POST['params'] ?? [];
        
        if (empty($site_ids)) {
            wp_send_json_error('No hay sitios seleccionados');
        }
        
        $results = $this->execute_bulk_action('update_plugins', $site_ids, $params);
        wp_send_json_success($results);
    }
    
    public function ajax_bulk_update_themes() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $site_ids = array_map('intval', $_POST['site_ids'] ?? []);
        $params = $_POST['params'] ?? [];
        
        if (empty($site_ids)) {
            wp_send_json_error('No hay sitios seleccionados');
        }
        
        $results = $this->execute_bulk_action('update_themes', $site_ids, $params);
        wp_send_json_success($results);
    }
    
    public function ajax_bulk_update_core() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $site_ids = array_map('intval', $_POST['site_ids'] ?? []);
        $params = $_POST['params'] ?? [];
        
        if (empty($site_ids)) {
            wp_send_json_error('No hay sitios seleccionados');
        }
        
        $results = $this->execute_bulk_action('update_core', $site_ids, $params);
        wp_send_json_success($results);
    }
    
    public function ajax_bulk_backup() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $site_ids = array_map('intval', $_POST['site_ids'] ?? []);
        $backup_type = sanitize_text_field($_POST['backup_type'] ?? 'full');
        
        if (empty($site_ids)) {
            wp_send_json_error('No hay sitios seleccionados');
        }
        
        $params = ['type' => $backup_type];
        $results = $this->execute_bulk_action('backup', $site_ids, $params);
        wp_send_json_success($results);
    }
    
    public function ajax_bulk_security_scan() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $site_ids = array_map('intval', $_POST['site_ids'] ?? []);
        $params = $_POST['params'] ?? [];
        
        if (empty($site_ids)) {
            wp_send_json_error('No hay sitios seleccionados');
        }
        
        $results = $this->execute_bulk_action('security_scan', $site_ids, $params);
        wp_send_json_success($results);
    }
    
    public function ajax_bulk_cache_clear() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $site_ids = array_map('intval', $_POST['site_ids'] ?? []);
        $params = $_POST['params'] ?? [];
        
        if (empty($site_ids)) {
            wp_send_json_error('No hay sitios seleccionados');
        }
        
        $results = $this->execute_bulk_action('cache_clear', $site_ids, $params);
        wp_send_json_success($results);
    }
    
    public function ajax_bulk_sync_data() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $site_ids = array_map('intval', $_POST['site_ids'] ?? []);
        $params = $_POST['params'] ?? [];
        
        if (empty($site_ids)) {
            wp_send_json_error('No hay sitios seleccionados');
        }
        
        $results = $this->execute_bulk_action('sync_data', $site_ids, $params);
        wp_send_json_success($results);
    }
    
    public function ajax_bulk_test_connection() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $site_ids = array_map('intval', $_POST['site_ids'] ?? []);
        $params = $_POST['params'] ?? [];
        
        if (empty($site_ids)) {
            wp_send_json_error('No hay sitios seleccionados');
        }
        
        $results = $this->execute_bulk_action('test_connection', $site_ids, $params);
        wp_send_json_success($results);
    }
}
