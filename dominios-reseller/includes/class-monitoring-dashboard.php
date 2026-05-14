<?php
/**
 * Real-Time Monitoring Dashboard
 *
 * Dashboard para monitoreo en tiempo real del sistema de onboarding automático
 *
 * @package Dominios_Reseller
 * @version 1.0.0
 * @since 1.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dominios_Reseller_Monitoring_Dashboard {

    /**
     * Instancia singleton
     */
    private static ?Dominios_Reseller_Monitoring_Dashboard $instance = null;

    /**
     * Servicios
     */
    private Dominios_Reseller_Onboarding_Worker $worker;
    private ?Dominios_Reseller_Auto_Discovery $auto_discovery = null;

    /**
     * Constructor
     */
    private function __construct() {
        $this->worker = Dominios_Reseller_Onboarding_Worker::get_instance();
        if (class_exists('Dominios_Reseller_Auto_Discovery')) {
            $this->auto_discovery = Dominios_Reseller_Auto_Discovery::get_instance();
        }
        $this->init_hooks();
    }

    /**
     * Obtener instancia singleton
     */
    public static function get_instance(): Dominios_Reseller_Monitoring_Dashboard {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Inicializar hooks
     */
    private function init_hooks(): void {
        add_action('admin_menu', [$this, 'add_monitoring_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_monitoring_scripts']);
        add_action('wp_ajax_dr_get_monitoring_data', [$this, 'ajax_get_monitoring_data']);
    }

    /**
     * Agregar menú de monitoreo
     */
    public function add_monitoring_menu(): void {
        add_submenu_page(
            'dominios-reseller-admin',
            'Monitoreo en Tiempo Real',
            '📊 Monitoreo',
            'manage_options',
            'dr-monitoring',
            [$this, 'render_monitoring_page']
        );
    }

    /**
     * Enqueue scripts y estilos
     */
    public function enqueue_monitoring_scripts($hook): void {
        if ($hook !== 'dominios-reseller_page_dr-monitoring') {
            return;
        }

        wp_enqueue_style('dr-monitoring-css', plugin_dir_url(__FILE__) . '../assets/css/monitoring.css', [], '1.0.0');
        wp_enqueue_script('dr-monitoring-js', plugin_dir_url(__FILE__) . '../assets/js/monitoring.js', ['jquery'], '1.0.0', true);

        wp_localize_script('dr-monitoring-js', 'drMonitoringAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dr_monitoring_nonce'),
        ]);
    }

    /**
     * Renderizar página de monitoreo
     */
    public function render_monitoring_page(): void {
        ?>
        <div class="wrap">
            <h1>📊 Monitoreo en Tiempo Real - Onboarding Automático</h1>

            <div class="dr-monitoring-container">
                <!-- KPIs Principales -->
                <div class="dr-kpi-grid">
                    <div class="dr-kpi-card" id="kpi-domains-today">
                        <h3>🏷️ Dominios Hoy</h3>
                        <div class="kpi-value">--</div>
                        <div class="kpi-trend">↗️ +0%</div>
                    </div>

                    <div class="dr-kpi-card" id="kpi-success-rate">
                        <h3>✅ Tasa de Éxito</h3>
                        <div class="kpi-value">--%</div>
                        <div class="kpi-trend">↗️ +0%</div>
                    </div>

                    <div class="dr-kpi-card" id="kpi-avg-time">
                        <h3>⏱️ Tiempo Promedio</h3>
                        <div class="kpi-value">--min</div>
                        <div class="kpi-trend">↘️ -0%</div>
                    </div>

                    <div class="dr-kpi-card" id="kpi-queue-length">
                        <h3>📋 En Cola</h3>
                        <div class="kpi-value">--</div>
                        <div class="kpi-trend">--</div>
                    </div>
                </div>

                <!-- Estado del Sistema -->
                <div class="dr-system-status">
                    <h2>🔧 Estado del Sistema</h2>
                    <div class="status-indicators">
                        <div class="status-item" id="status-worker">
                            <span class="status-dot" data-status="unknown"></span>
                            Worker de Onboarding
                        </div>
                        <div class="status-item" id="status-auto-discovery">
                            <span class="status-dot" data-status="unknown"></span>
                            Auto-Discovery
                        </div>
                        <div class="status-item" id="status-cloudflare">
                            <span class="status-dot" data-status="unknown"></span>
                            API Cloudflare
                        </div>
                        <div class="status-item" id="status-webhooks">
                            <span class="status-dot" data-status="unknown"></span>
                            Webhooks
                        </div>
                    </div>
                </div>

                <!-- Gráfico de Actividad -->
                <div class="dr-activity-chart">
                    <h2>📈 Actividad de Onboarding (Últimas 24h)</h2>
                    <canvas id="activityChart" width="400" height="200"></canvas>
                </div>

                <!-- Alertas Activas -->
                <div class="dr-alerts-panel">
                    <h2>🚨 Alertas Activas</h2>
                    <div id="alerts-container">
                        <div class="no-alerts">✅ No hay alertas activas</div>
                    </div>
                </div>

                <!-- Logs Recientes -->
                <div class="dr-recent-logs">
                    <h2>📝 Actividad Reciente</h2>
                    <div id="recent-logs-container">
                        <div class="loading">Cargando logs...</div>
                    </div>
                </div>

                <!-- Controles de Sistema -->
                <div class="dr-system-controls">
                    <h2>🎛️ Controles del Sistema</h2>
                    <div class="control-buttons">
                        <button id="btn-force-discovery" class="button button-secondary">
                            🔍 Forzar Auto-Discovery
                        </button>
                        <button id="btn-clear-queue" class="button button-secondary">
                            🧹 Limpiar Cola
                        </button>
                        <button id="btn-reset-metrics" class="button button-secondary">
                            🔄 Reset Métricas
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .dr-monitoring-container {
            margin-top: 20px;
        }

        .dr-kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .dr-kpi-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .dr-kpi-card h3 {
            margin: 0 0 15px 0;
            color: #23282d;
            font-size: 14px;
            font-weight: 600;
        }

        .kpi-value {
            font-size: 32px;
            font-weight: bold;
            color: #007cba;
            margin-bottom: 5px;
        }

        .kpi-trend {
            font-size: 12px;
            color: #666;
        }

        .dr-system-status {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .status-indicators {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .status-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }

        .status-dot[data-status="healthy"] { background-color: #46b450; }
        .status-dot[data-status="warning"] { background-color: #ffb900; }
        .status-dot[data-status="error"] { background-color: #dc3232; }
        .status-dot[data-status="unknown"] { background-color: #999; }

        .dr-activity-chart,
        .dr-alerts-panel,
        .dr-recent-logs {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .dr-system-controls {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
        }

        .control-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .no-alerts {
            color: #46b450;
            font-style: italic;
        }

        .loading {
            color: #666;
            font-style: italic;
        }
        </style>
        <?php
    }

    /**
     * AJAX handler para obtener datos de monitoreo
     */
    public function ajax_get_monitoring_data(): void {
        try {
            // Verificar nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dr_monitoring_nonce')) {
                wp_die('Security check failed');
            }

            $data = $this->get_monitoring_data();

            wp_send_json_success($data);

        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Obtener todos los datos de monitoreo
     */
    private function get_monitoring_data(): array {
        global $wpdb;

        // KPIs principales
        $kpis = $this->get_kpi_data();

        // Estado del sistema
        $system_status = $this->get_system_status();

        // Datos del gráfico (últimas 24 horas)
        $chart_data = $this->get_chart_data();

        // Alertas activas
        $alerts = $this->get_active_alerts();

        // Logs recientes
        $recent_logs = $this->get_recent_logs();

        return [
            'kpis' => $kpis,
            'system_status' => $system_status,
            'chart_data' => $chart_data,
            'alerts' => $alerts,
            'recent_logs' => $recent_logs,
            'timestamp' => current_time('timestamp')
        ];
    }

    /**
     * Obtener datos de KPIs
     */
    private function get_kpi_data(): array {
        global $wpdb;

        $table_logs = Dominios_Reseller_Onboarding_DB::get_logs_table();
        $table_onboarding = Dominios_Reseller_Onboarding_DB::get_onboarding_table();

        // Dominios procesados hoy
        $today_start = date('Y-m-d 00:00:00');
        $domains_today = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT domain) FROM {$table_logs}
             WHERE created_at >= %s AND level = 'info'
             AND message LIKE '%onboarding completed%'",
            $today_start
        ));

        // Tasa de éxito (últimos 100 onboardings)
        $recent_success = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_logs}
             WHERE level = 'info' AND message LIKE '%completed%'
             AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        ));

        $recent_total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT domain) FROM {$table_logs}
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        ));

        $success_rate = $recent_total > 0 ? round(($recent_success / $recent_total) * 100, 1) : 0;

        // Tiempo promedio de onboarding
        $avg_time = $wpdb->get_var(
            "SELECT AVG(TIMESTAMPDIFF(MINUTE, start_time, end_time))
             FROM {$table_onboarding}
             WHERE status = 'completed' AND end_time IS NOT NULL
             AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );

        // Longitud de cola
        $queue_length = $this->worker->get_queue_length();

        return [
            'domains_today' => (int) $domains_today,
            'success_rate' => $success_rate,
            'avg_time' => round((float) $avg_time, 1),
            'queue_length' => $queue_length
        ];
    }

    /**
     * Obtener estado del sistema
     */
    private function get_system_status(): array {
        $status = [];

        // Worker de onboarding
        $worker_status = $this->check_worker_status();
        $status['worker'] = $worker_status;

        // Auto-discovery
        $discovery_status = $this->check_auto_discovery_status();
        $status['auto_discovery'] = $discovery_status;

        // Cloudflare API
        $cf_status = $this->check_cloudflare_status();
        $status['cloudflare'] = $cf_status;

        // Webhooks
        $webhook_status = $this->check_webhook_status();
        $status['webhooks'] = $webhook_status;

        return $status;
    }

    /**
     * Verificar estado del worker
     */
    private function check_worker_status(): array {
        $last_run = get_option('dr_worker_last_run', 0);
        $now = current_time('timestamp');

        if (($now - $last_run) > 600) { // 10 minutos sin actividad
            return ['status' => 'error', 'message' => 'Worker inactivo'];
        }

        return ['status' => 'healthy', 'message' => 'Operativo'];
    }

    /**
     * Verificar estado del auto-discovery
     */
    private function check_auto_discovery_status(): array {
        if (!$this->auto_discovery) {
            return ['status' => 'warning', 'message' => 'No habilitado'];
        }

        $metrics = $this->auto_discovery->get_metrics();
        $last_check = strtotime($metrics['last_check'] ?? 'now');

        if ((current_time('timestamp') - $last_check) > 600) {
            return ['status' => 'warning', 'message' => 'Sin actividad reciente'];
        }

        return ['status' => 'healthy', 'message' => 'Activo'];
    }

    /**
     * Verificar estado de Cloudflare API
     */
    private function check_cloudflare_status(): array {
        $last_sync = get_option('dominios_reseller_cf_sync_stats', []);
        $last_sync_time = $last_sync['last_sync'] ?? 0;

        if ((current_time('timestamp') - $last_sync_time) > 3600) { // 1 hora
            return ['status' => 'warning', 'message' => 'Sin sincronización reciente'];
        }

        if (isset($last_sync['errors']) && $last_sync['errors'] > 0) {
            return ['status' => 'error', 'message' => 'Errores en API'];
        }

        return ['status' => 'healthy', 'message' => 'API operativa'];
    }

    /**
     * Verificar estado de webhooks
     */
    private function check_webhook_status(): array {
        $webhook_secret = get_option('dr_whmcs_webhook_secret', '');

        if (empty($webhook_secret)) {
            return ['status' => 'warning', 'message' => 'No configurado'];
        }

        // Verificar si ha recibido webhooks recientemente
        $last_webhook = get_option('dr_last_webhook_received', 0);

        if ((current_time('timestamp') - $last_webhook) > 86400) { // 24 horas
            return ['status' => 'warning', 'message' => 'Sin actividad reciente'];
        }

        return ['status' => 'healthy', 'message' => 'Activo'];
    }

    /**
     * Obtener datos para el gráfico
     */
    private function get_chart_data(): array {
        global $wpdb;

        $table_logs = Dominios_Reseller_Onboarding_DB::get_logs_table();

        // Onboardings por hora en las últimas 24 horas
        $data = $wpdb->get_results($wpdb->prepare(
            "SELECT
                DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour,
                COUNT(DISTINCT domain) as count
             FROM {$table_logs}
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             AND level = 'info'
             AND message LIKE '%completed%'
             GROUP BY hour
             ORDER BY hour",
            []
        ));

        $labels = [];
        $values = [];

        // Llenar datos para las últimas 24 horas
        for ($i = 23; $i >= 0; $i--) {
            $hour = date('Y-m-d H:00:00', strtotime("-{$i} hours"));
            $labels[] = date('H:i', strtotime($hour));

            $found = false;
            foreach ($data as $row) {
                if ($row->hour === $hour) {
                    $values[] = (int) $row->count;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $values[] = 0;
            }
        }

        return [
            'labels' => $labels,
            'data' => $values
        ];
    }

    /**
     * Obtener alertas activas
     */
    private function get_active_alerts(): array {
        $alerts = [];

        // Verificar cola muy larga
        $queue_length = $this->worker->get_queue_length();
        if ($queue_length > 10) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "Cola de onboarding muy larga: {$queue_length} dominios pendientes",
                'action' => 'Revisar estado del worker'
            ];
        }

        // Verificar tasa de éxito baja
        $kpis = $this->get_kpi_data();
        if ($kpis['success_rate'] < 80) {
            $alerts[] = [
                'type' => 'error',
                'message' => "Tasa de éxito baja: {$kpis['success_rate']}%",
                'action' => 'Revisar logs de errores'
            ];
        }

        // Verificar worker inactivo
        $worker_status = $this->check_worker_status();
        if ($worker_status['status'] === 'error') {
            $alerts[] = [
                'type' => 'error',
                'message' => 'Worker de onboarding inactivo',
                'action' => 'Reiniciar servicios'
            ];
        }

        return $alerts;
    }

    /**
     * Obtener logs recientes
     */
    private function get_recent_logs(): array {
        global $wpdb;

        $table_logs = Dominios_Reseller_Onboarding_DB::get_logs_table();

        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT domain, level, message, created_at
             FROM {$table_logs}
             ORDER BY created_at DESC
             LIMIT 20"
        ));

        return array_map(function($log) {
            return [
                'domain' => $log->domain,
                'level' => $log->level,
                'message' => $log->message,
                'timestamp' => strtotime($log->created_at)
            ];
        }, $logs);
    }
}

// Inicializar el dashboard
add_action('plugins_loaded', function() {
    Dominios_Reseller_Monitoring_Dashboard::get_instance();
});