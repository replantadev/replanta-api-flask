<?php
/**
 * Sistema de Monitoreo de Disponibilidad (Uptime Monitoring)
 */

if (!defined('ABSPATH')) {
    exit;
}

class RphubUptimeMonitoring {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'rphub_uptime_logs';
        
        add_action('init', array($this, 'init'));
        add_action('wp_loaded', array($this, 'create_uptime_table'));
    }
    
    public function init() {
        // Hooks para monitoreo
        add_action('rphub_check_uptime', array($this, 'check_all_sites_uptime'));
        add_action('rphub_uptime_alert', array($this, 'send_uptime_alerts'));
        
        // AJAX handlers
        add_action('wp_ajax_rphub_get_uptime_data',          array($this, 'ajax_get_uptime_data'));
        add_action('wp_ajax_rphub_pause_uptime_monitoring',  array($this, 'ajax_pause_monitoring'));
        add_action('wp_ajax_rphub_configure_uptime_alerts',  array($this, 'ajax_configure_alerts'));
        add_action('wp_ajax_rphub_get_downtime_incidents',   array($this, 'ajax_get_downtime_incidents'));
        add_action('wp_ajax_rphub_get_ssl_status',           array($this, 'ajax_get_ssl_status'));
        
        // Task queue (Action Scheduler) — every minute for uptime checks
        add_filter('cron_schedules', array($this, 'add_custom_cron_intervals'));

        // Task queue (Action Scheduler) every minute for uptime checks.
        RPHUB_Scheduler::schedule('rphub_check_uptime', 'rphub_every_minute');
    }
    
    /**
     * Agregar intervalos personalizados de cron
     */
    public function add_custom_cron_intervals($schedules) {
        $schedules['rphub_every_minute'] = array(
            'interval' => 60, // 1 minuto
            'display' => 'Every Minute'
        );
        
        $schedules['rphub_every_five_minutes'] = array(
            'interval' => 300, // 5 minutos
            'display' => 'Every 5 Minutes'
        );
        
        return $schedules;
    }
    
    /**
     * Crear tabla para logs de uptime
     */
    public function create_uptime_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id int(11) NOT NULL AUTO_INCREMENT,
            site_id int(11) NOT NULL,
            checked_at datetime DEFAULT CURRENT_TIMESTAMP,
            status varchar(20) NOT NULL,
            response_time int(11) DEFAULT NULL,
            response_code int(11) DEFAULT NULL,
            error_message text,
            location varchar(50) DEFAULT 'local',
            PRIMARY KEY (id),
            KEY site_id (site_id),
            KEY checked_at (checked_at),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Verificar uptime de todos los sitios
     */
    public function check_all_sites_uptime() {
        $all_sites = RPHUB_Database::get_all_sites();
        $sites = array_filter($all_sites, function($site) {
            return RPHUB_Database::get_site_meta($site->id, 'uptime_monitoring_enabled') === '1';
        });
        
        foreach ($sites as $site) {
            $this->check_site_uptime($site->id);
            // SSL check runs once per day per site (throttled internally)
            $this->check_ssl_expiry($site->id);
        }

        // Verificar si necesitamos enviar alertas
        $this->process_uptime_alerts();
    }
    
    /**
     * Verificar uptime de un sitio específico
     */
    public function check_site_uptime($site_id) {
        $site_url = RPHUB_Database::get_site($site_id)->url;
        
        if (empty($site_url)) {
            return false;
        }
        
        // Configuración del check
        $timeout = RPHUB_Database::get_site_meta($site_id, 'uptime_timeout') ?: 30;
        $follow_redirects = RPHUB_Database::get_site_meta($site_id, 'uptime_follow_redirects') ?: true;
        
        $start_time = microtime(true);
        
        $response = wp_remote_get($site_url, array(
            'timeout' => $timeout,
            'redirection' => $follow_redirects ? 5 : 0,
            'user-agent' => 'Replanta Hub Uptime Monitor/1.0',
            'sslverify' => true,
            'headers' => array(
                'Cache-Control' => 'no-cache',
                'Pragma' => 'no-cache'
            )
        ));
        
        $response_time = round((microtime(true) - $start_time) * 1000); // milisegundos
        
        $status = 'down';
        $response_code = null;
        $error_message = null;
        
        if (!is_wp_error($response)) {
            $response_code = wp_remote_retrieve_response_code($response);
            
            // Considerar exitoso cualquier código 2xx o 3xx
            if ($response_code >= 200 && $response_code < 400) {
                $status = 'up';
            } else {
                $status = 'down';
                $error_message = 'HTTP ' . $response_code;
            }
        } else {
            $error_message = $response->get_error_message();
        }
        
        // Guardar resultado
        $this->log_uptime_check($site_id, $status, $response_time, $response_code, $error_message);
        
        // Actualizar metadatos del sitio
        RPHUB_Database::update_site_meta($site_id, 'last_uptime_check', current_time('mysql'));
        RPHUB_Database::update_site_meta($site_id, 'last_uptime_status', $status);
        RPHUB_Database::update_site_meta($site_id, 'last_response_time', $response_time);
        
        // Verificar si hay cambio de estado
        $previous_status = RPHUB_Database::get_site_meta($site_id, 'previous_uptime_status');
        
        if ($previous_status !== $status) {
            $this->handle_status_change($site_id, $previous_status, $status, $response_time, $error_message);
            RPHUB_Database::update_site_meta($site_id, 'previous_uptime_status', $status);
        }
        
        return array(
            'status' => $status,
            'response_time' => $response_time,
            'response_code' => $response_code,
            'error_message' => $error_message
        );
    }
    
    /**
     * Guardar log de verificación de uptime
     */
    private function log_uptime_check($site_id, $status, $response_time, $response_code, $error_message) {
        global $wpdb;
        
        return $wpdb->insert(
            $this->table_name,
            array(
                'site_id' => $site_id,
                'checked_at' => current_time('mysql'),
                'status' => $status,
                'response_time' => $response_time,
                'response_code' => $response_code,
                'error_message' => $error_message,
                'location' => 'local'
            ),
            array('%d', '%s', '%s', '%d', '%d', '%s', '%s')
        );
    }
    
    /**
     * Manejar cambio de estado (up/down)
     */
    private function handle_status_change($site_id, $previous_status, $current_status, $response_time, $error_message) {
        // Crear incidente si el sitio se cae
        if ($current_status === 'down' && $previous_status === 'up') {
            $this->create_downtime_incident($site_id, $error_message);
        }
        
        // Resolver incidente si el sitio vuelve a funcionar
        if ($current_status === 'up' && $previous_status === 'down') {
            $this->resolve_downtime_incident($site_id, $response_time);
        }
        
        // Log del cambio de estado
        $this->log_status_change($site_id, $previous_status, $current_status, $error_message);
    }
    
    /**
     * Crear incidente de downtime
     */
    private function create_downtime_incident($site_id, $error_message) {
        $incidents = RPHUB_Database::get_site_meta($site_id, 'downtime_incidents');
        if (!is_array($incidents)) {
            $incidents = array();
        }
        
        $incident = array(
            'id' => uniqid(),
            'started_at' => current_time('mysql'),
            'resolved_at' => null,
            'duration' => null,
            'error_message' => $error_message,
            'status' => 'ongoing',
            'alerts_sent' => array()
        );
        
        array_unshift($incidents, $incident);
        
        // Mantener solo los últimos 50 incidentes
        $incidents = array_slice($incidents, 0, 50);
        
        RPHUB_Database::update_site_meta($site_id, 'downtime_incidents', $incidents);
        RPHUB_Database::update_site_meta($site_id, 'current_incident_id', $incident['id']);
        
        // Programar alertas
        $this->schedule_downtime_alerts($site_id, $incident['id']);
    }
    
    /**
     * Resolver incidente de downtime
     */
    private function resolve_downtime_incident($site_id, $response_time) {
        $current_incident_id = RPHUB_Database::get_site_meta($site_id, 'current_incident_id');
        
        if (empty($current_incident_id)) {
            return;
        }
        
        $incidents = RPHUB_Database::get_site_meta($site_id, 'downtime_incidents');
        if (!is_array($incidents)) {
            return;
        }
        
        // Encontrar y resolver el incidente actual
        foreach ($incidents as &$incident) {
            if ($incident['id'] === $current_incident_id && $incident['status'] === 'ongoing') {
                $incident['resolved_at'] = current_time('mysql');
                $incident['status'] = 'resolved';
                $incident['duration'] = strtotime($incident['resolved_at']) - strtotime($incident['started_at']);
                break;
            }
        }
        
        RPHUB_Database::update_site_meta($site_id, 'downtime_incidents', $incidents);
        RPHUB_Database::delete_site_meta($site_id, 'current_incident_id');
        
        // Enviar alerta de recuperación
        $this->send_recovery_alert($site_id, $incident, $response_time);
    }
    
    /**
     * Programar alertas de downtime
     */
    private function schedule_downtime_alerts($site_id, $incident_id) {
        $alert_config = RPHUB_Database::get_site_meta($site_id, 'uptime_alert_config');
        
        if (!$alert_config || !$alert_config['enabled']) {
            return;
        }
        
        // Alerta inmediata
        wp_schedule_single_event(time() + 60, 'rphub_send_downtime_alert', array($site_id, $incident_id, 'immediate'));
        
        // Alertas de seguimiento
        if (isset($alert_config['follow_up_intervals'])) {
            foreach ($alert_config['follow_up_intervals'] as $interval) {
                wp_schedule_single_event(time() + ($interval * 60), 'rphub_send_downtime_alert', array($site_id, $incident_id, 'follow_up'));
            }
        }
    }
    
    /**
     * Procesar alertas de uptime pendientes
     */
    private function process_uptime_alerts() {
        // Esta función se ejecuta cada minuto para verificar alertas pendientes
        // Se puede usar para alertas programadas o escalation
    }
    
    /**
     * Enviar alerta de downtime.
     * Uses the site's alert_email column as primary recipient; falls back to uptime_alert_config.
     */
    public function send_downtime_alert($site_id, $incident_id, $alert_type) {
        $site_obj = RPHUB_Database::get_site($site_id);
        if (!$site_obj) {
            return;
        }
        $site_title   = $site_obj->name;
        $site_url     = $site_obj->url;
        $alert_config = RPHUB_Database::get_site_meta($site_id, 'uptime_alert_config');

        // Build recipient list: per-site alert_email first, then alert_config, then admin email.
        $recipients = $this->resolve_alert_recipients($site_obj, $alert_config);
        if (empty($recipients)) {
            return;
        }

        $incidents = RPHUB_Database::get_site_meta($site_id, 'downtime_incidents');
        $incident  = null;
        if (is_array($incidents)) {
            foreach ($incidents as $inc) {
                if ($inc['id'] === $incident_id) {
                    $incident = $inc;
                    break;
                }
            }
        }

        if (!$incident || $incident['status'] !== 'ongoing') {
            return;
        }

        $downtime_duration = time() - strtotime($incident['started_at']);
        $duration_text     = $this->format_duration($downtime_duration);

        $subject = "[DOWNTIME] {$site_title} - Sitio No Disponible";
        $message = $this->get_downtime_alert_template($site_title, $site_url, $incident, $duration_text, $alert_type);
        $headers = array('Content-Type: text/html; charset=UTF-8');

        foreach ($recipients as $email) {
            wp_mail($email, $subject, $message, $headers);
        }
        
        // Webhook notifications
        if (isset($alert_config['webhook_url']) && !empty($alert_config['webhook_url'])) {
            $this->send_webhook_alert($alert_config['webhook_url'], $site_id, $incident, $alert_type);
        }
        
        // SMS notifications (si está configurado)
        if (isset($alert_config['sms_enabled']) && $alert_config['sms_enabled']) {
            $this->send_sms_alert($alert_config, $site_title, $duration_text);
        }
        
        // Marcar alerta como enviada
        $incidents = RPHUB_Database::get_site_meta($site_id, 'downtime_incidents');
        foreach ($incidents as &$inc) {
            if ($inc['id'] === $incident_id) {
                $inc['alerts_sent'][] = array(
                    'type' => $alert_type,
                    'sent_at' => current_time('mysql')
                );
                break;
            }
        }
        RPHUB_Database::update_site_meta($site_id, 'downtime_incidents', $incidents);
    }
    
    /**
     * Resolve alert recipients for a site.
     * Priority: per-site alert_email → uptime_alert_config recipients → WP admin email.
     */
    private function resolve_alert_recipients($site_obj, $alert_config) {
        $recipients = array();

        // 1. Per-site alert_email column (added in DB v2.2)
        if (!empty($site_obj->alert_email) && is_email($site_obj->alert_email)) {
            $recipients[] = $site_obj->alert_email;
        }

        // 2. alert_config email_recipients (from per-site settings UI)
        if (!empty($alert_config['email_recipients']) && is_array($alert_config['email_recipients'])) {
            foreach ($alert_config['email_recipients'] as $email) {
                if (is_email($email) && !in_array($email, $recipients, true)) {
                    $recipients[] = $email;
                }
            }
        }

        // 3. Fall back to WP admin email
        if (empty($recipients)) {
            $admin_email = get_option('admin_email', '');
            if (is_email($admin_email)) {
                $recipients[] = $admin_email;
            }
        }

        return $recipients;
    }

    /**
     * Enviar alerta de recuperación.
     */
    private function send_recovery_alert($site_id, $incident, $response_time) {
        if (!$incident) {
            return;
        }
        $site_obj = RPHUB_Database::get_site($site_id);
        if (!$site_obj) {
            return;
        }
        $site_title   = $site_obj->name;
        $site_url     = $site_obj->url;
        $alert_config = RPHUB_Database::get_site_meta($site_id, 'uptime_alert_config');

        if (!empty($alert_config) && empty($alert_config['recovery_alerts'])) {
            return;
        }

        $recipients    = $this->resolve_alert_recipients($site_obj, $alert_config);
        if (empty($recipients)) {
            return;
        }

        $duration_text = $this->format_duration($incident['duration'] ?? 0);
        $subject       = "[RECOVERY] {$site_title} - Sitio Recuperado";
        $message       = $this->get_recovery_alert_template($site_title, $site_url, $incident, $duration_text, $response_time);
        $headers       = array('Content-Type: text/html; charset=UTF-8');

        foreach ($recipients as $email) {
            wp_mail($email, $subject, $message, $headers);
        }
        
        // Webhook de recuperación
        if (isset($alert_config['webhook_url']) && !empty($alert_config['webhook_url'])) {
            $this->send_webhook_alert($alert_config['webhook_url'], $site_id, $incident, 'recovery');
        }
    }
    
    /**
     * Obtener estadísticas de uptime
     */
    public function get_uptime_statistics($site_id, $period = '7d') {
        global $wpdb;
        
        // Calcular fecha de inicio según el período
        switch ($period) {
            case '24h':
                $start_date = date('Y-m-d H:i:s', strtotime('-24 hours'));
                break;
            case '7d':
                $start_date = date('Y-m-d H:i:s', strtotime('-7 days'));
                break;
            case '30d':
                $start_date = date('Y-m-d H:i:s', strtotime('-30 days'));
                break;
            case '90d':
                $start_date = date('Y-m-d H:i:s', strtotime('-90 days'));
                break;
            default:
                $start_date = date('Y-m-d H:i:s', strtotime('-7 days'));
        }
        
        // Total de checks
        $total_checks = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE site_id = %d AND checked_at >= %s",
            $site_id, $start_date
        ));
        
        // Checks exitosos
        $successful_checks = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE site_id = %d AND checked_at >= %s AND status = 'up'",
            $site_id, $start_date
        ));
        
        // Calcular uptime percentage
        $uptime_percentage = $total_checks > 0 ? round(($successful_checks / $total_checks) * 100, 2) : 0;
        
        // Tiempo de respuesta promedio
        $avg_response_time = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(response_time) FROM {$this->table_name} 
             WHERE site_id = %d AND checked_at >= %s AND status = 'up'",
            $site_id, $start_date
        ));
        
        // Incidentes en el período
        $incidents = $this->get_incidents_in_period($site_id, $start_date);
        
        return array(
            'period' => $period,
            'uptime_percentage' => $uptime_percentage,
            'total_checks' => $total_checks,
            'successful_checks' => $successful_checks,
            'failed_checks' => $total_checks - $successful_checks,
            'avg_response_time' => round($avg_response_time),
            'incidents_count' => count($incidents),
            'total_downtime' => $this->calculate_total_downtime($incidents),
            'incidents' => $incidents
        );
    }
    
    /**
     * Obtener incidentes en un período
     */
    private function get_incidents_in_period($site_id, $start_date) {
        $all_incidents = RPHUB_Database::get_site_meta($site_id, 'downtime_incidents');
        if (!is_array($all_incidents)) {
            return array();
        }
        
        $period_incidents = array();
        
        foreach ($all_incidents as $incident) {
            if (strtotime($incident['started_at']) >= strtotime($start_date)) {
                $period_incidents[] = $incident;
            }
        }
        
        return $period_incidents;
    }
    
    /**
     * Calcular tiempo total de downtime
     */
    private function calculate_total_downtime($incidents) {
        $total_downtime = 0;
        
        foreach ($incidents as $incident) {
            if ($incident['status'] === 'resolved' && $incident['duration']) {
                $total_downtime += $incident['duration'];
            } elseif ($incident['status'] === 'ongoing') {
                $total_downtime += time() - strtotime($incident['started_at']);
            }
        }
        
        return $total_downtime;
    }
    
    /**
     * Formatear duración en texto legible
     */
    private function format_duration($seconds) {
        if ($seconds < 60) {
            return $seconds . ' segundos';
        } elseif ($seconds < 3600) {
            return round($seconds / 60) . ' minutos';
        } elseif ($seconds < 86400) {
            return round($seconds / 3600, 1) . ' horas';
        } else {
            return round($seconds / 86400, 1) . ' días';
        }
    }
    
    /**
     * Template para alerta de downtime
     */
    private function get_downtime_alert_template($site_title, $site_url, $incident, $duration, $alert_type) {
        $template = '
        <html>
        <body style="font-family: Arial, sans-serif; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <div style="background: #dc3545; color: white; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                    <h2 style="margin: 0;"> Sitio Web No Disponible</h2>
                </div>
                
                <h3>Detalles del Incidente:</h3>
                <ul>
                    <li><strong>Sitio:</strong> ' . esc_html($site_title) . '</li>
                    <li><strong>URL:</strong> <a href="' . esc_url($site_url) . '">' . esc_html($site_url) . '</a></li>
                    <li><strong>Iniciado:</strong> ' . date('d/m/Y H:i:s', strtotime($incident['started_at'])) . '</li>
                    <li><strong>Duración:</strong> ' . $duration . '</li>
                    <li><strong>Error:</strong> ' . esc_html($incident['error_message']) . '</li>
                </ul>
                
                <p>Este es un mensaje automático del sistema de monitoreo de Replanta Hub.</p>
                
                <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; margin-top: 20px;">
                    <small>Tiempo del incidente se mide desde el primer check fallido hasta la recuperación.</small>
                </div>
            </div>
        </body>
        </html>';
        
        return $template;
    }
    
    /**
     * Template para alerta de recuperación
     */
    private function get_recovery_alert_template($site_title, $site_url, $incident, $duration, $response_time) {
        $template = '
        <html>
        <body style="font-family: Arial, sans-serif; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <div style="background: #28a745; color: white; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                    <h2 style="margin: 0;"> Sitio Web Recuperado</h2>
                </div>
                
                <h3>Detalles de la Recuperación:</h3>
                <ul>
                    <li><strong>Sitio:</strong> ' . esc_html($site_title) . '</li>
                    <li><strong>URL:</strong> <a href="' . esc_url($site_url) . '">' . esc_html($site_url) . '</a></li>
                    <li><strong>Recuperado:</strong> ' . date('d/m/Y H:i:s') . '</li>
                    <li><strong>Duración del Downtime:</strong> ' . $duration . '</li>
                    <li><strong>Tiempo de Respuesta Actual:</strong> ' . $response_time . 'ms</li>
                </ul>
                
                <p>El sitio web está nuevamente disponible y funcionando correctamente.</p>
                
                <div style="background: #d4edda; padding: 10px; border-radius: 4px; margin-top: 20px;">
                    <small> Monitoreo automático continúa activo</small>
                </div>
            </div>
        </body>
        </html>';
        
        return $template;
    }
    
    /**
     * AJAX: Obtener datos de uptime
     */
    public function ajax_get_uptime_data() {
        check_ajax_referer('rphub_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $site_id = intval($_POST['site_id']);
        $period = sanitize_text_field($_POST['period']) ?: '7d';
        
        $statistics = $this->get_uptime_statistics($site_id, $period);
        $recent_checks = $this->get_recent_checks($site_id, 50);
        
        wp_send_json_success(array(
            'statistics' => $statistics,
            'recent_checks' => $recent_checks
        ));
    }
    
    /**
     * Obtener checks recientes
     */
    private function get_recent_checks($site_id, $limit = 50) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE site_id = %d 
             ORDER BY checked_at DESC 
             LIMIT %d",
            $site_id, $limit
        ), ARRAY_A);
    }
    
    /**
     * AJAX: Pausar monitoreo
     */
    public function ajax_pause_monitoring() {
        check_ajax_referer('rphub_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $site_id = intval($_POST['site_id']);
        $pause_duration = intval($_POST['duration']); // minutos
        
        $pause_until = date('Y-m-d H:i:s', time() + ($pause_duration * 60));
        RPHUB_Database::update_site_meta($site_id, 'uptime_paused_until', $pause_until);
        
        wp_send_json_success(array(
            'message' => 'Monitoreo pausado hasta ' . date('d/m/Y H:i', time() + ($pause_duration * 60)),
            'paused_until' => $pause_until
        ));
    }
    
    /**
     * AJAX: Configurar alertas
     */
    public function ajax_configure_alerts() {
        check_ajax_referer('rphub_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $site_id = intval($_POST['site_id']);
        $config = $_POST['alert_config'];
        
        // Validar configuración
        $validated_config = $this->validate_alert_config($config);
        
        RPHUB_Database::update_site_meta($site_id, 'uptime_alert_config', $validated_config);
        
        wp_send_json_success(array(
            'message' => 'Configuración de alertas guardada',
            'config' => $validated_config
        ));
    }
    
    /**
     * Validar configuración de alertas
     */
    private function validate_alert_config($config) {
        $default_config = array(
            'enabled' => false,
            'email_recipients' => array(),
            'webhook_url' => '',
            'recovery_alerts' => true,
            'follow_up_intervals' => array(5, 15, 30, 60), // minutos
            'sms_enabled' => false,
            'sms_numbers' => array()
        );
        
        return wp_parse_args($config, $default_config);
    }
    
    /**
     * Renderizar panel de uptime en dashboard
     */
    public function render_uptime_panel($site_id) {
        $statistics = $this->get_uptime_statistics($site_id, '7d');
        $monitoring_enabled = RPHUB_Database::get_site_meta($site_id, 'uptime_monitoring_enabled');
        $alert_config = RPHUB_Database::get_site_meta($site_id, 'uptime_alert_config');
        $paused_until = RPHUB_Database::get_site_meta($site_id, 'uptime_paused_until');

        include plugin_dir_path(__FILE__) . '../templates/uptime-panel.php';
    }

    // -------------------------------------------------------------------------
    // SSL certificate expiry monitoring
    // -------------------------------------------------------------------------

    /**
     * Check SSL expiry for a site once per day.
     * Called from check_all_sites_uptime() with daily throttle.
     */
    public function check_ssl_expiry($site_id) {
        $site = RPHUB_Database::get_site($site_id);
        if (!$site || empty($site->url)) {
            return;
        }

        // Throttle: run once per 24h per site
        $last_check = RPHUB_Database::get_site_meta($site_id, 'ssl_last_checked');
        if ($last_check && (time() - strtotime($last_check)) < DAY_IN_SECONDS) {
            return;
        }

        $hostname = wp_parse_url($site->url, PHP_URL_HOST);
        if (empty($hostname) || strpos($site->url, 'https') !== 0) {
            return; // Not HTTPS — nothing to check
        }

        $days_remaining = $this->get_ssl_days_remaining($hostname);

        RPHUB_Database::update_site_meta($site_id, 'ssl_last_checked',   current_time('mysql'));
        RPHUB_Database::update_site_meta($site_id, 'ssl_days_remaining', $days_remaining);

        if ($days_remaining === null) {
            return; // Could not retrieve cert
        }

        $threshold = $this->get_ssl_alert_threshold($site_id);

        if ($days_remaining <= 0) {
            $this->send_ssl_alert($site_id, $days_remaining, 'expired');
        } elseif ($days_remaining <= $threshold) {
            $this->send_ssl_alert($site_id, $days_remaining, 'expiring');
        }
    }

    /**
     * Returns days until the SSL certificate expires, or null on failure.
     * Uses a raw TLS socket so it works without cURL and without a full HTTP request.
     */
    public function get_ssl_days_remaining($hostname) {
        if (!function_exists('openssl_x509_parse')) {
            return null;
        }

        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer'       => false, // We only care about the expiry date, not trust chain
                'verify_peer_name'  => false,
                'SNI_enabled'       => true,
                'peer_name'         => $hostname,
            ],
        ]);

        $stream = @stream_socket_client(
            'ssl://' . $hostname . ':443',
            $errno, $errstr, 10,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$stream) {
            return null;
        }

        $params = stream_context_get_params($stream);
        fclose($stream);

        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        if (!$cert) {
            return null;
        }

        $info   = openssl_x509_parse($cert);
        $expiry = $info['validTo_time_t'] ?? null;

        if (!$expiry) {
            return null;
        }

        return (int) floor(($expiry - time()) / DAY_IN_SECONDS);
    }

    /**
     * Returns alert threshold in days based on the site's plan.
     * Semilla → 15 days, Raíz/Ecosistema → 30 days.
     */
    private function get_ssl_alert_threshold($site_id) {
        $plan_slug = RPHUB_Database::get_site_meta($site_id, 'plan')
            ?: (RPHUB_Database::get_site($site_id)->plan ?? 'semilla');

        if (class_exists('RPHUB_Plans_Manager')) {
            $pm        = new RPHUB_Plans_Manager();
            $threshold = $pm->get_plan_feature_value($plan_slug, 'ssl_alert_days');
            if ($threshold !== null) {
                return (int) $threshold;
            }
        }

        return match ($plan_slug) {
            'raiz', 'ecosistema' => 30,
            default              => 15,
        };
    }

    /**
     * Send SSL expiry alert email (deduped: max one alert per level per 7 days).
     */
    private function send_ssl_alert($site_id, $days_remaining, $type) {
        $dedup_key  = 'ssl_alert_sent_' . $type;
        $last_sent  = RPHUB_Database::get_site_meta($site_id, $dedup_key);

        if ($last_sent && (time() - strtotime($last_sent)) < 7 * DAY_IN_SECONDS) {
            return;
        }

        $site      = RPHUB_Database::get_site($site_id);
        $site_name = $site->name ?? 'desconocido';
        $site_url  = $site->url  ?? '';

        if ($type === 'expired') {
            $subject = "[SSL CADUCADO] {$site_name} — certificado expirado";
            $msg     = "El certificado SSL de <strong>{$site_name}</strong> ({$site_url}) ha <strong>caducado</strong>. "
                     . "Los visitantes verán un aviso de seguridad. Renueva el certificado urgentemente.";
        } else {
            $subject = "[SSL] {$site_name} — expira en {$days_remaining} días";
            $msg     = "El certificado SSL de <strong>{$site_name}</strong> ({$site_url}) "
                     . "expira en <strong>{$days_remaining} días</strong>. "
                     . "Renueva el certificado antes de que cause incidencias.";
        }

        $severity  = $days_remaining <= 7 ? 'critical' : 'warning';
        $headers   = ['Content-Type: text/html; charset=UTF-8'];

        if (class_exists('RPHUB_Alerting')) {
            RPHUB_Alerting::send_alert([
                'site_id'  => $site_id,
                'type'     => 'ssl_expiry',
                'severity' => $severity,
                'subject'  => $subject,
                'message'  => $msg,
                'data'     => ['days_remaining' => $days_remaining, 'type' => $type],
            ]);
        } else {
            $recipients = $this->resolve_alert_recipients($site, []);
            foreach ($recipients as $email) {
                wp_mail($email, $subject, $msg, $headers);
            }
        }

        RPHUB_Database::update_site_meta($site_id, $dedup_key, current_time('mysql'));
    }

    /**
     * AJAX: Get SSL status for a site.
     */
    public function ajax_get_ssl_status() {
        check_ajax_referer('rphub_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }

        $site_id = intval($_POST['site_id'] ?? 0);
        if (!$site_id) {
            wp_send_json_error('site_id required');
        }

        $site = RPHUB_Database::get_site($site_id);
        if (!$site) {
            wp_send_json_error('Site not found');
        }

        $hostname = wp_parse_url($site->url, PHP_URL_HOST);
        $days     = $hostname ? $this->get_ssl_days_remaining($hostname) : null;

        RPHUB_Database::update_site_meta($site_id, 'ssl_days_remaining', $days);
        RPHUB_Database::update_site_meta($site_id, 'ssl_last_checked',   current_time('mysql'));

        wp_send_json_success([
            'days_remaining' => $days,
            'threshold'      => $this->get_ssl_alert_threshold($site_id),
            'status'         => $days === null ? 'unknown'
                              : ($days <= 0 ? 'expired'
                              : ($days <= $this->get_ssl_alert_threshold($site_id) ? 'expiring' : 'ok')),
        ]);
    }
}

// Instantiated by replanta-hub.php — do not instantiate here.
