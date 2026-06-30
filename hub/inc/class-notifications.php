<?php
/**
 * Notifications Class
 * Manages notifications and alerts for the hub
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPHUB_Notifications {
    
    private $table_notifications;
    private $table_sites;
    
    public function __construct() {
        global $wpdb;
        $this->table_notifications = $wpdb->prefix . 'rphub_notifications';
        $this->table_sites = $wpdb->prefix . 'rphub_sites';
        
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // AJAX hooks for notifications
        add_action('wp_ajax_rphub_get_notifications', [$this, 'ajax_get_notifications']);
        add_action('wp_ajax_rphub_mark_notification_read', [$this, 'ajax_mark_notification_read']);
        add_action('wp_ajax_rphub_mark_all_read', [$this, 'ajax_mark_all_read']);
        add_action('wp_ajax_rphub_delete_notification', [$this, 'ajax_delete_notification']);
        
        // Hook into various events to create notifications
        add_action('rphub_site_error', [$this, 'create_site_error_notification'], 10, 2);
        add_action('rphub_task_failed', [$this, 'create_task_failed_notification'], 10, 2);
        add_action('rphub_security_issue', [$this, 'create_security_notification'], 10, 3);
        add_action('rphub_backup_failed', [$this, 'create_backup_failed_notification'], 10, 2);
        add_action('rphub_updates_available', [$this, 'create_updates_notification'], 10, 2);
        
        // Email notifications
        add_action('wp', [$this, 'schedule_email_notifications']);
        add_action('rphub_send_email_notifications', [$this, 'send_email_notifications']);
    }
    
    /**
     * Create a new notification
     */
    public function create_notification($data) {
        global $wpdb;
        
        $defaults = [
            'site_id' => null,
            'type' => 'info',
            'severity' => 'info',
            'title' => '',
            'message' => '',
            'data' => null,
            'email_sent' => 0
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        // Validate required fields
        if (empty($data['title']) || empty($data['message'])) {
            return new WP_Error('missing_data', 'Título y mensaje son requeridos');
        }
        
        // Encode data if it's an array
        if (is_array($data['data'])) {
            $data['data'] = json_encode($data['data']);
        }
        
        $result = $wpdb->insert(
            $this->table_notifications,
            [
                'site_id' => $data['site_id'],
                'type' => sanitize_text_field($data['type']),
                'severity' => sanitize_text_field($data['severity']),
                'title' => sanitize_text_field($data['title']),
                'message' => sanitize_textarea_field($data['message']),
                'data' => $data['data'],
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Error al crear la notificación');
        }
        
        $notification_id = $wpdb->insert_id;
        
        // Schedule email notification if it's important
        if (in_array($data['severity'], ['warning', 'error', 'critical'])) {
            $this->schedule_email_notification($notification_id);
        }
        
        return $notification_id;
    }
    
    /**
     * Get notifications with pagination and filtering
     */
    public function get_notifications($args = []) {
        global $wpdb;
        
        $defaults = [
            'site_id' => null,
            'type' => '',
            'severity' => '',
            'read_status' => '',
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where_clauses = [];
        $values = [];
        
        if ($args['site_id']) {
            $where_clauses[] = "n.site_id = %d";
            $values[] = $args['site_id'];
        }
        
        if (!empty($args['type'])) {
            $where_clauses[] = "n.type = %s";
            $values[] = $args['type'];
        }
        
        if (!empty($args['severity'])) {
            $where_clauses[] = "n.severity = %s";
            $values[] = $args['severity'];
        }
        
        if ($args['read_status'] !== '') {
            $where_clauses[] = "n.read_status = %d";
            $values[] = intval($args['read_status']);
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = "WHERE " . implode(' AND ', $where_clauses);
        }
        
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        $order_sql = $orderby ? "ORDER BY $orderby" : '';
        
        $limit_sql = '';
        if ($args['limit'] > 0) {
            $limit_sql = $wpdb->prepare("LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }
        
        $sql = "SELECT n.*, s.name as site_name, s.url as site_url
                FROM {$this->table_notifications} n
                LEFT JOIN {$this->table_sites} s ON n.site_id = s.id
                $where_sql $order_sql $limit_sql";
        
        if (!empty($values)) {
            return $wpdb->get_results($wpdb->prepare($sql, ...$values));
        } else {
            return $wpdb->get_results($sql);
        }
    }

    /**
     * Get notifications count
     */
    public function get_notifications_count($args = []) {
        global $wpdb;
        
        $where_clauses = [];
        $values = [];
        
        if (isset($args['site_id']) && $args['site_id']) {
            $where_clauses[] = "site_id = %d";
            $values[] = $args['site_id'];
        }
        
        if (!empty($args['type'])) {
            $where_clauses[] = "type = %s";
            $values[] = $args['type'];
        }
        
        if (!empty($args['severity'])) {
            $where_clauses[] = "severity = %s";
            $values[] = $args['severity'];
        }
        
        if (isset($args['read_status']) && $args['read_status'] !== '') {
            $where_clauses[] = "read_status = %d";
            $values[] = intval($args['read_status']);
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = "WHERE " . implode(' AND ', $where_clauses);
        }
        
        $sql = "SELECT COUNT(*) FROM {$this->table_notifications} $where_sql";
        
        if (!empty($values)) {
            return $wpdb->get_var($wpdb->prepare($sql, ...$values));
        } else {
            return $wpdb->get_var($sql);
        }
    }
    
    /**
     * Mark notification as read
     */
    public function mark_as_read($notification_id) {
        global $wpdb;
        
        $result = $wpdb->update(
            $this->table_notifications,
            ['read_status' => 1],
            ['id' => $notification_id],
            ['%d'],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Mark all notifications as read
     */
    public function mark_all_as_read($site_id = null) {
        global $wpdb;
        
        $where = ['read_status' => 0];
        $where_format = ['%d'];
        
        if ($site_id) {
            $where['site_id'] = $site_id;
            $where_format[] = '%d';
        }
        
        $result = $wpdb->update(
            $this->table_notifications,
            ['read_status' => 1],
            $where,
            ['%d'],
            $where_format
        );
        
        return $result !== false;
    }
    
    /**
     * Delete notification
     */
    public function delete_notification($notification_id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $this->table_notifications,
            ['id' => $notification_id],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Clean old notifications
     */
    public function cleanup_old_notifications($days = 30) {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_notifications} WHERE read_status = %d AND created_at < %s",
                1,
                $cutoff_date
            )
        );
        
        return $result;
    }
    
    /**
     * Create site error notification
     */
    public function create_site_error_notification($site_id, $error_message) {
        global $wpdb;
        
        $site = $wpdb->get_row(
            $wpdb->prepare("SELECT name FROM {$this->table_sites} WHERE id = %d", $site_id)
        );
        
        if (!$site) {
            return false;
        }
        
        return $this->create_notification([
            'site_id' => $site_id,
            'type' => 'site_error',
            'severity' => 'error',
            'title' => 'Error en el sitio: ' . $site->name,
            'message' => $error_message,
            'data' => ['error' => $error_message]
        ]);
    }
    
    /**
     * Create task failed notification
     */
    public function create_task_failed_notification($task_id, $error_message) {
        global $wpdb;
        
        $task = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT t.*, s.name as site_name 
                 FROM {$wpdb->prefix}rphub_tasks t 
                 JOIN {$this->table_sites} s ON t.site_id = s.id 
                 WHERE t.id = %d",
                $task_id
            )
        );
        
        if (!$task) {
            return false;
        }
        
        return $this->create_notification([
            'site_id' => $task->site_id,
            'type' => 'task_failed',
            'severity' => 'warning',
            'title' => 'Tarea fallida: ' . $task->task_type,
            'message' => "La tarea '{$task->task_type}' falló en {$task->site_name}: {$error_message}",
            'data' => [
                'task_id' => $task_id,
                'task_type' => $task->task_type,
                'error' => $error_message
            ]
        ]);
    }
    
    /**
     * Create security issue notification
     */
    public function create_security_notification($site_id, $issue_type, $details) {
        global $wpdb;
        
        $site = $wpdb->get_row(
            $wpdb->prepare("SELECT name FROM {$this->table_sites} WHERE id = %d", $site_id)
        );
        
        if (!$site) {
            return false;
        }
        
        return $this->create_notification([
            'site_id' => $site_id,
            'type' => 'security',
            'severity' => 'critical',
            'title' => 'Problema de seguridad detectado: ' . $site->name,
            'message' => "Se detectó un problema de seguridad ({$issue_type}) en {$site->name}. " . $details,
            'data' => [
                'issue_type' => $issue_type,
                'details' => $details
            ]
        ]);
    }
    
    /**
     * Create backup failed notification
     */
    public function create_backup_failed_notification($site_id, $error_message) {
        global $wpdb;
        
        $site = $wpdb->get_row(
            $wpdb->prepare("SELECT name FROM {$this->table_sites} WHERE id = %d", $site_id)
        );
        
        if (!$site) {
            return false;
        }
        
        return $this->create_notification([
            'site_id' => $site_id,
            'type' => 'backup_failed',
            'severity' => 'warning',
            'title' => 'Fallo en backup: ' . $site->name,
            'message' => "El backup automático falló en {$site->name}: {$error_message}",
            'data' => ['error' => $error_message]
        ]);
    }
    
    /**
     * Create updates available notification
     */
    public function create_updates_notification($site_id, $updates_count) {
        global $wpdb;
        
        $site = $wpdb->get_row(
            $wpdb->prepare("SELECT name FROM {$this->table_sites} WHERE id = %d", $site_id)
        );
        
        if (!$site) {
            return false;
        }
        
        return $this->create_notification([
            'site_id' => $site_id,
            'type' => 'updates',
            'severity' => 'info',
            'title' => 'Actualizaciones disponibles: ' . $site->name,
            'message' => "Hay {$updates_count} actualizaciones disponibles en {$site->name}",
            'data' => ['updates_count' => $updates_count]
        ]);
    }
    
    /**
     * Schedule email notification
     */
    private function schedule_email_notification($notification_id) {
        wp_schedule_single_event(time() + 300, 'rphub_send_single_notification', [$notification_id]);
    }
    
    /**
     * Schedule email notifications check
     */
    public function schedule_email_notifications() {
        RPHUB_Scheduler::schedule('rphub_send_email_notifications', 'hourly');
    }
    
    /**
     * Send email notifications
     */
    public function send_email_notifications() {
        $notifications = $this->get_notifications([
            'severity' => ['warning', 'error', 'critical'],
            'read_status' => 0,
            'limit' => 10
        ]);
        
        if (empty($notifications)) {
            return;
        }
        
        $admin_email = get_option('admin_email');
        $notification_emails = get_option('rphub_notification_emails', [$admin_email]);
        
        foreach ($notifications as $notification) {
            $this->send_single_notification($notification, $notification_emails);
        }
    }
    
    /**
     * Send single email notification
     */
    private function send_single_notification($notification, $recipients) {
        global $wpdb;
        
        $subject = "Replanta Hub Alert: {$notification->title}";
        
        $message = "<h2>{$notification->title}</h2>";
        $message .= "<p><strong>Sitio:</strong> {$notification->site_name}</p>";
        $message .= "<p><strong>Severidad:</strong> {$notification->severity}</p>";
        $message .= "<p><strong>Mensaje:</strong> {$notification->message}</p>";
        $message .= "<p><strong>Fecha:</strong> {$notification->created_at}</p>";
        
        if ($notification->data) {
            $data = json_decode($notification->data, true);
            if ($data) {
                $message .= "<h3>Detalles adicionales:</h3>";
                $message .= "<pre>" . print_r($data, true) . "</pre>";
            }
        }
        
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: Replanta Hub <' . get_option('admin_email') . '>'
        ];
        
        $sent = wp_mail($recipients, $subject, $message, $headers);
        
        if ($sent) {
            // Mark as email sent
            $wpdb->update(
                $this->table_notifications,
                ['email_sent' => 1],
                ['id' => $notification->id],
                ['%d'],
                ['%d']
            );
        }
        
        return $sent;
    }
    
    // AJAX Handlers
    public function ajax_get_notifications() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $args = [
            'site_id' => intval($_POST['site_id'] ?? 0) ?: null,
            'type' => sanitize_text_field($_POST['type'] ?? ''),
            'severity' => sanitize_text_field($_POST['severity'] ?? ''),
            'read_status' => $_POST['read_status'] ?? '',
            'limit' => intval($_POST['limit'] ?? 20),
            'offset' => intval($_POST['offset'] ?? 0)
        ];
        
        $notifications = $this->get_notifications($args);
        $total = $this->get_notifications_count($args);
        
        wp_send_json_success([
            'notifications' => $notifications,
            'total' => $total
        ]);
    }
    
    public function ajax_mark_notification_read() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $notification_id = intval($_POST['notification_id'] ?? 0);
        
        if (empty($notification_id)) {
            wp_send_json_error('ID de notificación requerido');
        }
        
        $result = $this->mark_as_read($notification_id);
        
        if ($result) {
            wp_send_json_success('Notificación marcada como leída');
        } else {
            wp_send_json_error('Error al marcar la notificación');
        }
    }
    
    public function ajax_mark_all_read() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $site_id = intval($_POST['site_id'] ?? 0) ?: null;
        $result = $this->mark_all_as_read($site_id);
        
        if ($result) {
            wp_send_json_success('Todas las notificaciones marcadas como leídas');
        } else {
            wp_send_json_error('Error al marcar las notificaciones');
        }
    }
    
    public function ajax_delete_notification() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $notification_id = intval($_POST['notification_id'] ?? 0);
        
        if (empty($notification_id)) {
            wp_send_json_error('ID de notificación requerido');
        }
        
        $result = $this->delete_notification($notification_id);
        
        if ($result) {
            wp_send_json_success('Notificación eliminada');
        } else {
            wp_send_json_error('Error al eliminar la notificación');
        }
    }
}
