<?php
/**
 * Professional Dashboard for Replanta Hub
 * 
 * Main dashboard page with comprehensive site management interface
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get dashboard data
$reports = new RPHUB_Reports();
$dashboard_summary = $reports->get_dashboard_summary();

// Get recent activities
global $wpdb;
$table_activities = $wpdb->prefix . 'rphub_activities';
$recent_activities = $wpdb->get_results("SELECT * FROM $table_activities ORDER BY created_at DESC LIMIT 10", ARRAY_A);

// Get active alerts
$table_notifications = $wpdb->prefix . 'rphub_notifications';
$active_alerts = $wpdb->get_results("SELECT * FROM $table_notifications WHERE status = 'unread' ORDER BY created_at DESC LIMIT 5", ARRAY_A);
?>

<div class="wrap replanta-hub-dashboard">
    <h1 class="wp-heading-inline">
        <span class="replanta-logo">🌱</span>
        Replanta Hub - Dashboard Profesional
    </h1>
    
    <!-- Quick Stats Overview -->
    <div class="replanta-stats-grid">
        <div class="stat-card total-sites">
            <div class="stat-icon">🌐</div>
            <div class="stat-content">
                <h3><?php echo esc_html($dashboard_summary['total_sites']); ?></h3>
                <p>Sitios Totales</p>
            </div>
        </div>
        
        <div class="stat-card security-score">
            <div class="stat-icon">🔒</div>
            <div class="stat-content">
                <h3><?php echo esc_html($dashboard_summary['security_overview']['secure_sites']); ?></h3>
                <p>Sitios Seguros</p>
                <small><?php echo esc_html($dashboard_summary['security_overview']['vulnerabilities_found']); ?> vulnerabilidades detectadas</small>
            </div>
        </div>
        
        <div class="stat-card performance-score">
            <div class="stat-icon">⚡</div>
            <div class="stat-content">
                <h3><?php echo esc_html($dashboard_summary['performance_overview']['average_performance_score']); ?>/100</h3>
                <p>Rendimiento Promedio</p>
                <small><?php echo esc_html($dashboard_summary['performance_overview']['sites_above_90']); ?> sitios excelentes</small>
            </div>
        </div>
        
        <div class="stat-card maintenance-score">
            <div class="stat-icon">🔧</div>
            <div class="stat-content">
                <h3><?php echo esc_html($dashboard_summary['maintenance_overview']['sites_up_to_date']); ?></h3>
                <p>Sitios Actualizados</p>
                <small><?php echo esc_html($dashboard_summary['maintenance_overview']['pending_updates']); ?> actualizaciones pendientes</small>
            </div>
        </div>
    </div>
    
    <!-- Health Status Distribution -->
    <div class="replanta-section">
        <h2>Estado de Salud de Sitios</h2>
        <div class="health-distribution">
            <div class="health-bar">
                <div class="health-segment excellent" style="width: <?php echo ($dashboard_summary['sites_health']['excellent'] / $dashboard_summary['total_sites']) * 100; ?>%">
                    <span>Excelente (<?php echo esc_html($dashboard_summary['sites_health']['excellent']); ?>)</span>
                </div>
                <div class="health-segment good" style="width: <?php echo ($dashboard_summary['sites_health']['good'] / $dashboard_summary['total_sites']) * 100; ?>%">
                    <span>Bueno (<?php echo esc_html($dashboard_summary['sites_health']['good']); ?>)</span>
                </div>
                <div class="health-segment warning" style="width: <?php echo ($dashboard_summary['sites_health']['warning'] / $dashboard_summary['total_sites']) * 100; ?>%">
                    <span>Advertencia (<?php echo esc_html($dashboard_summary['sites_health']['warning']); ?>)</span>
                </div>
                <div class="health-segment critical" style="width: <?php echo ($dashboard_summary['sites_health']['critical'] / $dashboard_summary['total_sites']) * 100; ?>%">
                    <span>Crítico (<?php echo esc_html($dashboard_summary['sites_health']['critical']); ?>)</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Dashboard Grid -->
    <div class="replanta-dashboard-grid">
        
        <!-- Sites Management Panel -->
        <div class="dashboard-panel sites-panel">
            <div class="panel-header">
                <h3>📊 Gestión de Sitios</h3>
                <button class="button button-primary" onclick="showAddSiteModal()">+ Agregar Sitio</button>
            </div>
            <div class="panel-content">
                <div id="sites-list-container">
                    <!-- Sites will be loaded via AJAX -->
                    <div class="loading-placeholder">Cargando sitios...</div>
                </div>
            </div>
        </div>
        
        <!-- Real-time Monitoring -->
        <div class="dashboard-panel monitoring-panel">
            <div class="panel-header">
                <h3>📈 Monitoreo en Tiempo Real</h3>
                <button class="button refresh-monitoring" onclick="refreshMonitoring()">🔄 Actualizar</button>
            </div>
            <div class="panel-content">
                <div class="monitoring-metrics">
                    <div class="metric">
                        <span class="metric-label">Amenazas Bloqueadas (24h)</span>
                        <span class="metric-value" id="threats-blocked"><?php echo esc_html($dashboard_summary['security_overview']['threats_blocked_today']); ?></span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Ancho de Banda Ahorrado</span>
                        <span class="metric-value" id="bandwidth-saved"><?php echo esc_html($dashboard_summary['performance_overview']['total_bandwidth_saved']); ?></span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Sitios con SSL</span>
                        <span class="metric-value" id="ssl-sites"><?php echo esc_html($dashboard_summary['security_overview']['ssl_enabled']); ?></span>
                    </div>
                </div>
                
                <div class="performance-chart">
                    <canvas id="performanceChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Alerts and Notifications -->
        <div class="dashboard-panel alerts-panel">
            <div class="panel-header">
                <h3>🚨 Alertas y Notificaciones</h3>
                <button class="button mark-all-read" onclick="markAllAlertsRead()">Marcar como leídas</button>
            </div>
            <div class="panel-content">
                <div class="alerts-list">
                    <?php if (empty($active_alerts)): ?>
                        <div class="no-alerts">
                            <span class="success-icon">✅</span>
                            <p>¡Excelente! No hay alertas activas</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($active_alerts as $alert): ?>
                            <div class="alert-item severity-<?php echo esc_attr($alert['severity']); ?>">
                                <div class="alert-icon">
                                    <?php
                                    switch ($alert['severity']) {
                                        case 'error': echo '🔴'; break;
                                        case 'warning': echo '🟡'; break;
                                        default: echo '🔵'; break;
                                    }
                                    ?>
                                </div>
                                <div class="alert-content">
                                    <h4><?php echo esc_html($alert['title']); ?></h4>
                                    <p><?php echo esc_html($alert['message']); ?></p>
                                    <small><?php echo esc_html(human_time_diff(strtotime($alert['created_at']))); ?> ago</small>
                                </div>
                                <button class="dismiss-alert" onclick="dismissAlert(<?php echo esc_attr($alert['id']); ?>)">×</button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Activities -->
        <div class="dashboard-panel activities-panel">
            <div class="panel-header">
                <h3>📝 Actividad Reciente</h3>
                <button class="button view-all" onclick="viewAllActivities()">Ver Todo</button>
            </div>
            <div class="panel-content">
                <div class="activities-list">
                    <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <?php
                                switch ($activity['type']) {
                                    case 'backup': echo '💾'; break;
                                    case 'update': echo '🔄'; break;
                                    case 'security': echo '🔒'; break;
                                    case 'performance': echo '⚡'; break;
                                    default: echo '📝'; break;
                                }
                                ?>
                            </div>
                            <div class="activity-content">
                                <p><?php echo esc_html($activity['description']); ?></p>
                                <small><?php echo esc_html(human_time_diff(strtotime($activity['created_at']))); ?> ago</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="dashboard-panel actions-panel">
            <div class="panel-header">
                <h3>⚡ Acciones Rápidas</h3>
            </div>
            <div class="panel-content">
                <div class="quick-actions">
                    <button class="action-button backup-all" onclick="backupAllSites()">
                        <span class="action-icon">💾</span>
                        <span class="action-text">Backup Todos</span>
                    </button>
                    
                    <button class="action-button update-all" onclick="updateAllSites()">
                        <span class="action-icon">🔄</span>
                        <span class="action-text">Actualizar Todos</span>
                    </button>
                    
                    <button class="action-button security-scan" onclick="runSecurityScan()">
                        <span class="action-icon">🔍</span>
                        <span class="action-text">Escaneo Seguridad</span>
                    </button>
                    
                    <button class="action-button performance-test" onclick="runPerformanceTest()">
                        <span class="action-icon">📊</span>
                        <span class="action-text">Test Rendimiento</span>
                    </button>
                    
                    <button class="action-button generate-report" onclick="generateComprehensiveReport()">
                        <span class="action-icon">📄</span>
                        <span class="action-text">Generar Reporte</span>
                    </button>
                    
                    <button class="action-button cloudflare-purge" onclick="purgeAllCloudflare()">
                        <span class="action-icon">🌐</span>
                        <span class="action-text">Purgar Cloudflare</span>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Integration Status -->
        <div class="dashboard-panel integrations-panel">
            <div class="panel-header">
                <h3>🔗 Estado de Integraciones</h3>
                <button class="button test-integrations" onclick="testAllIntegrations()">Probar Conexiones</button>
            </div>
            <div class="panel-content">
                <div class="integrations-status">
                    <?php 
                    $care_status = get_option('rphub_care_status', array('connected' => false, 'plan' => 'none'));
                    $is_care_connected = $care_status['connected'];
                    ?>
                    <div class="integration-item care-integration" id="care-status">
                        <span class="integration-icon">🌱</span>
                        <span class="integration-name">Replanta Care</span>
                        <span class="status-indicator" data-status="<?php echo $is_care_connected ? 'connected' : 'disconnected'; ?>">
                            <?php echo $is_care_connected ? '✅' : '⭕'; ?>
                        </span>
                        <?php if ($is_care_connected): ?>
                            <span class="care-plan"><?php echo esc_html(ucfirst($care_status['plan'])); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="integration-item" id="wptoolkit-status">
                        <span class="integration-icon">🛠️</span>
                        <span class="integration-name">WP Toolkit Pro</span>
                        <span class="status-indicator" data-status="checking">🔄</span>
                    </div>
                    
                    <div class="integration-item" id="backuply-status">
                        <span class="integration-icon">💾</span>
                        <span class="integration-name">Backuply</span>
                        <span class="status-indicator" data-status="checking">🔄</span>
                    </div>
                    
                    <div class="integration-item" id="pagespeed-status">
                        <span class="integration-icon">⚡</span>
                        <span class="integration-name">PageSpeed Insights</span>
                        <span class="status-indicator" data-status="checking">🔄</span>
                    </div>
                    
                    <div class="integration-item" id="cloudflare-status">
                        <span class="integration-icon">☁️</span>
                        <span class="integration-name">Cloudflare</span>
                        <span class="status-indicator" data-status="checking">🔄</span>
                    </div>
                </div>
                
                <?php if ($is_care_connected): ?>
                <div class="care-quick-actions">
                    <h4>🌱 Acciones de Care</h4>
                    <div class="care-actions-grid">
                        <a href="https://care.replanta.com/dashboard" target="_blank" class="care-action-btn">
                            <span class="action-icon">📊</span>
                            <span class="action-text">Dashboard Care</span>
                        </a>
                        <a href="https://care.replanta.com/updates" target="_blank" class="care-action-btn">
                            <span class="action-icon">🔄</span>
                            <span class="action-text">Gestionar Updates</span>
                        </a>
                        <a href="https://care.replanta.com/security" target="_blank" class="care-action-btn">
                            <span class="action-icon">🛡️</span>
                            <span class="action-text">Centro Seguridad</span>
                        </a>
                        <a href="<?php echo admin_url('options-general.php?page=replanta-care'); ?>" class="care-action-btn">
                            <span class="action-icon">⚙️</span>
                            <span class="action-text">Configurar Care</span>
                        </a>
                    </div>
                </div>
                <?php else: ?>
                <div class="care-upgrade-prompt">
                    <h4>🌟 Conecta Replanta Care</h4>
                    <p>Gestión profesional automática para tu sitio WordPress</p>
                    <div class="care-benefits">
                        <span class="benefit">🔄 Updates automáticos</span>
                        <span class="benefit">🛡️ Seguridad 24/7</span>
                        <span class="benefit">💾 Backups diarios</span>
                        <span class="benefit">⚡ Optimización</span>
                    </div>
                    <div class="care-upgrade-actions">
                        <a href="<?php echo admin_url('options-general.php?page=replanta-care'); ?>" class="button button-primary">
                            Conectar Care
                        </a>
                        <a href="https://care.replanta.com/signup" target="_blank" class="button button-secondary">
                            Crear Cuenta
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Trends Section -->
    <div class="replanta-section trends-section">
        <h2>📈 Tendencias y Análisis</h2>
        <div class="trends-grid">
            <div class="trend-card performance-trend">
                <h4>Rendimiento</h4>
                <div class="trend-value">
                    <span class="trend-number"><?php echo esc_html($dashboard_summary['trends']['performance_trend']); ?></span>
                    <span class="trend-period">vs mes anterior</span>
                </div>
                <canvas id="performanceTrendChart" width="200" height="100"></canvas>
            </div>
            
            <div class="trend-card security-trend">
                <h4>Seguridad</h4>
                <div class="trend-value">
                    <span class="trend-number"><?php echo esc_html($dashboard_summary['trends']['security_trend']); ?></span>
                    <span class="trend-period">vs mes anterior</span>
                </div>
                <canvas id="securityTrendChart" width="200" height="100"></canvas>
            </div>
            
            <div class="trend-card backup-trend">
                <h4>Backups</h4>
                <div class="trend-value">
                    <span class="trend-number"><?php echo esc_html($dashboard_summary['trends']['backup_trend']); ?></span>
                    <span class="trend-period">vs mes anterior</span>
                </div>
                <canvas id="backupTrendChart" width="200" height="100"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Add Site Modal -->
<div id="add-site-modal" class="replanta-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Agregar Nuevo Sitio</h3>
            <button class="modal-close" onclick="closeAddSiteModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="add-site-form">
                <div class="form-group">
                    <label for="site-name">Nombre del Sitio</label>
                    <input type="text" id="site-name" name="site_name" required>
                </div>
                
                <div class="form-group">
                    <label for="site-url">URL del Sitio</label>
                    <input type="url" id="site-url" name="site_url" required>
                </div>
                
                <div class="form-group">
                    <label for="cloudflare-token">Token de Cloudflare (Opcional)</label>
                    <input type="text" id="cloudflare-token" name="cloudflare_token">
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="enable-automation" name="enable_automation" checked>
                        Habilitar automatización inteligente
                    </label>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="enable-monitoring" name="enable_monitoring" checked>
                        Habilitar monitoreo continuo
                    </label>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="button button-secondary" onclick="closeAddSiteModal()">Cancelar</button>
            <button class="button button-primary" onclick="addNewSite()">Agregar Sitio</button>
        </div>
    </div>
</div>

<style>
/* Dashboard Styles */
.replanta-hub-dashboard {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    max-width: 1400px;
    margin: 0 auto;
}

.replanta-logo {
    font-size: 1.5em;
    margin-right: 10px;
}

/* Stats Grid */
.replanta-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-card.security-score {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
}

.stat-card.performance-score {
    background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
    color: #333;
}

.stat-card.maintenance-score {
    background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
    color: #333;
}

.stat-icon {
    font-size: 2.5em;
    margin-right: 15px;
}

.stat-content h3 {
    margin: 0;
    font-size: 2em;
    font-weight: 600;
}

.stat-content p {
    margin: 5px 0 0 0;
    opacity: 0.9;
}

.stat-content small {
    opacity: 0.7;
    font-size: 0.85em;
}

/* Health Distribution */
.health-distribution {
    margin: 20px 0;
}

.health-bar {
    display: flex;
    height: 40px;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.health-segment {
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.9em;
    transition: all 0.3s ease;
}

.health-segment:hover {
    filter: brightness(1.1);
}

.health-segment.excellent { background: #28a745; }
.health-segment.good { background: #17a2b8; }
.health-segment.warning { background: #ffc107; color: #333; }
.health-segment.critical { background: #dc3545; }

/* Dashboard Grid */
.replanta-dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin: 30px 0;
}

.dashboard-panel {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
    overflow: hidden;
    transition: box-shadow 0.3s ease;
}

.dashboard-panel:hover {
    box-shadow: 0 4px 25px rgba(0,0,0,0.12);
}

.panel-header {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.panel-header h3 {
    margin: 0;
    font-size: 1.1em;
    font-weight: 600;
}

.panel-content {
    padding: 20px;
}

/* Monitoring Metrics */
.monitoring-metrics {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.metric {
    text-align: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
}

.metric-label {
    display: block;
    font-size: 0.9em;
    color: #6c757d;
    margin-bottom: 5px;
}

.metric-value {
    display: block;
    font-size: 1.5em;
    font-weight: 600;
    color: #495057;
}

/* Alerts */
.alerts-list {
    max-height: 300px;
    overflow-y: auto;
}

.alert-item {
    display: flex;
    align-items: flex-start;
    padding: 12px;
    margin-bottom: 10px;
    border-left: 4px solid #ccc;
    background: #f8f9fa;
    border-radius: 6px;
    position: relative;
}

.alert-item.severity-error { border-left-color: #dc3545; }
.alert-item.severity-warning { border-left-color: #ffc107; }
.alert-item.severity-info { border-left-color: #17a2b8; }

.alert-icon {
    margin-right: 10px;
    font-size: 1.2em;
}

.alert-content h4 {
    margin: 0 0 5px 0;
    font-size: 0.95em;
    font-weight: 600;
}

.alert-content p {
    margin: 0 0 5px 0;
    font-size: 0.9em;
    color: #6c757d;
}

.alert-content small {
    color: #adb5bd;
}

.dismiss-alert {
    position: absolute;
    top: 5px;
    right: 10px;
    background: none;
    border: none;
    font-size: 1.2em;
    cursor: pointer;
    color: #adb5bd;
}

.dismiss-alert:hover {
    color: #6c757d;
}

/* Activities */
.activity-item {
    display: flex;
    align-items: center;
    padding: 10px;
    border-bottom: 1px solid #e9ecef;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    margin-right: 12px;
    font-size: 1.1em;
}

.activity-content p {
    margin: 0 0 3px 0;
    font-size: 0.9em;
}

.activity-content small {
    color: #6c757d;
}

/* Quick Actions */
.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
}

.action-button {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 15px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.action-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    color: white;
}

.action-icon {
    font-size: 1.5em;
    margin-bottom: 5px;
}

.action-text {
    font-size: 0.85em;
    font-weight: 500;
}

/* Integration Status */
.integration-item {
    display: flex;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #e9ecef;
}

.integration-item:last-child {
    border-bottom: none;
}

.integration-icon {
    margin-right: 10px;
    font-size: 1.2em;
}

.integration-name {
    flex: 1;
    font-weight: 500;
}

.status-indicator[data-status="connected"] { color: #28a745; }
.status-indicator[data-status="error"] { color: #dc3545; }
.status-indicator[data-status="checking"] { color: #ffc107; }

/* Trends Section */
.trends-section {
    margin: 40px 0;
}

.trends-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.trend-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
    text-align: center;
}

.trend-card h4 {
    margin: 0 0 10px 0;
    color: #495057;
}

.trend-value {
    margin: 15px 0;
}

.trend-number {
    font-size: 2em;
    font-weight: 600;
    color: #28a745;
}

.trend-period {
    display: block;
    font-size: 0.8em;
    color: #6c757d;
}

/* Modal Styles */
.replanta-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    display: flex;
    justify-content: center;
    align-items: center;
}

.modal-content {
    background: white;
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow: auto;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5em;
    cursor: pointer;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 20px;
    border-top: 1px solid #e9ecef;
    text-align: right;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

.form-group input[type="text"],
.form-group input[type="url"] {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-size: 14px;
}

.form-group input[type="checkbox"] {
    margin-right: 8px;
}

/* Responsive Design */
@media (max-width: 768px) {
    .replanta-stats-grid {
        grid-template-columns: 1fr;
    }
    
    .replanta-dashboard-grid {
        grid-template-columns: 1fr;
    }
    
    .monitoring-metrics {
        grid-template-columns: 1fr;
    }
    
    .quick-actions {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .trends-grid {
        grid-template-columns: 1fr;
    }
}

/* Loading States */
.loading-placeholder {
    text-align: center;
    padding: 40px;
    color: #6c757d;
    font-style: italic;
}

/* Success States */
.no-alerts {
    text-align: center;
    padding: 40px;
    color: #28a745;
}

.success-icon {
    font-size: 2em;
    display: block;
    margin-bottom: 10px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Initialize dashboard
    initializeDashboard();
    
    // Load sites list
    loadSitesList();
    
    // Test integrations status
    testIntegrationsStatus();
    
    // Initialize charts
    initializeCharts();
    
    // Auto-refresh every 5 minutes
    setInterval(function() {
        refreshMonitoring();
    }, 300000);
});

function initializeDashboard() {
    console.log('Replanta Hub Dashboard initialized');
}

function loadSitesList() {
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'rphub_get_sites_list',
            nonce: rphub_ajax.nonce
        },
        success: function(response) {
            if (response.success) {
                displaySitesList(response.data);
            }
        }
    });
}

function displaySitesList(sites) {
    let html = '';
    
    if (sites.length === 0) {
        html = '<div class="no-sites">No hay sitios configurados aún. ¡Agrega tu primer sitio!</div>';
    } else {
        html = '<div class="sites-grid">';
        sites.forEach(function(site) {
            html += `
                <div class="site-card" data-site-id="${site.id}">
                    <div class="site-header">
                        <h4>${site.name}</h4>
                        <div class="site-status status-${site.status}"></div>
                    </div>
                    <div class="site-url">${site.url}</div>
                    <div class="site-metrics">
                        <div class="metric">
                            <span class="metric-label">Salud</span>
                            <span class="metric-value health-${site.health_status}">${site.health_score}/100</span>
                        </div>
                        <div class="metric">
                            <span class="metric-label">Rendimiento</span>
                            <span class="metric-value">${site.performance_score}/100</span>
                        </div>
                    </div>
                    <div class="site-actions">
                        <button class="button button-small" onclick="viewSiteDetails(${site.id})">Detalles</button>
                        <button class="button button-small" onclick="runSiteMaintenance(${site.id})">Mantenimiento</button>
                    </div>
                </div>
            `;
        });
        html += '</div>';
    }
    
    jQuery('#sites-list-container').html(html);
}

function testIntegrationsStatus() {
    const integrations = ['wptoolkit', 'backuply', 'pagespeed', 'cloudflare'];
    
    integrations.forEach(function(integration) {
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: `rphub_test_${integration}_connection`,
                nonce: rphub_ajax.nonce
            },
            success: function(response) {
                const status = response.success ? 'connected' : 'error';
                const icon = response.success ? '✅' : '❌';
                
                jQuery(`#${integration}-status .status-indicator`)
                    .attr('data-status', status)
                    .text(icon);
            },
            error: function() {
                jQuery(`#${integration}-status .status-indicator`)
                    .attr('data-status', 'error')
                    .text('❌');
            }
        });
    });
}

function initializeCharts() {
    // Performance Chart
    const performanceCtx = document.getElementById('performanceChart');
    if (performanceCtx) {
        new Chart(performanceCtx, {
            type: 'line',
            data: {
                labels: ['6h', '5h', '4h', '3h', '2h', '1h', 'Ahora'],
                datasets: [{
                    label: 'Rendimiento Promedio',
                    data: [75, 78, 82, 80, 85, 83, 87],
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
    }
    
    // Trend Charts
    initializeTrendCharts();
}

function initializeTrendCharts() {
    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            x: {
                display: false
            },
            y: {
                display: false
            }
        }
    };
    
    // Performance Trend
    const perfTrendCtx = document.getElementById('performanceTrendChart');
    if (perfTrendCtx) {
        new Chart(perfTrendCtx, {
            type: 'line',
            data: {
                labels: ['', '', '', '', '', '', ''],
                datasets: [{
                    data: [70, 72, 75, 78, 80, 82, 85],
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: chartOptions
        });
    }
    
    // Security Trend
    const secTrendCtx = document.getElementById('securityTrendChart');
    if (secTrendCtx) {
        new Chart(secTrendCtx, {
            type: 'line',
            data: {
                labels: ['', '', '', '', '', '', ''],
                datasets: [{
                    data: [85, 87, 88, 90, 89, 91, 92],
                    borderColor: '#17a2b8',
                    backgroundColor: 'rgba(23, 162, 184, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: chartOptions
        });
    }
    
    // Backup Trend
    const backupTrendCtx = document.getElementById('backupTrendChart');
    if (backupTrendCtx) {
        new Chart(backupTrendCtx, {
            type: 'line',
            data: {
                labels: ['', '', '', '', '', '', ''],
                datasets: [{
                    data: [95, 94, 96, 95, 97, 96, 98],
                    borderColor: '#ffc107',
                    backgroundColor: 'rgba(255, 193, 7, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: chartOptions
        });
    }
}

// Modal Functions
function showAddSiteModal() {
    jQuery('#add-site-modal').show();
}

function closeAddSiteModal() {
    jQuery('#add-site-modal').hide();
}

function addNewSite() {
    const formData = {
        action: 'rphub_add_site',
        nonce: rphub_ajax.nonce,
        site_name: jQuery('#site-name').val(),
        site_url: jQuery('#site-url').val(),
        cloudflare_token: jQuery('#cloudflare-token').val(),
        enable_automation: jQuery('#enable-automation').is(':checked'),
        enable_monitoring: jQuery('#enable-monitoring').is(':checked')
    };
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: formData,
        success: function(response) {
            if (response.success) {
                closeAddSiteModal();
                loadSitesList();
                showNotification('Sitio agregado exitosamente', 'success');
            } else {
                showNotification('Error al agregar sitio: ' + response.data, 'error');
            }
        }
    });
}

// Quick Actions
function backupAllSites() {
    if (confirm('¿Estás seguro de que quieres crear backups de todos los sitios?')) {
        showNotification('Iniciando backup de todos los sitios...', 'info');
        
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'rphub_backup_all_sites',
                nonce: rphub_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('Backups iniciados exitosamente', 'success');
                } else {
                    showNotification('Error al iniciar backups: ' + response.data, 'error');
                }
            }
        });
    }
}

function updateAllSites() {
    if (confirm('¿Estás seguro de que quieres actualizar todos los sitios?')) {
        showNotification('Iniciando actualizaciones...', 'info');
        
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'rphub_update_all_sites',
                nonce: rphub_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('Actualizaciones iniciadas exitosamente', 'success');
                } else {
                    showNotification('Error al iniciar actualizaciones: ' + response.data, 'error');
                }
            }
        });
    }
}

function runSecurityScan() {
    showNotification('Iniciando escaneo de seguridad...', 'info');
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'rphub_security_scan_all',
            nonce: rphub_ajax.nonce
        },
        success: function(response) {
            if (response.success) {
                showNotification('Escaneo de seguridad iniciado', 'success');
            } else {
                showNotification('Error al iniciar escaneo: ' + response.data, 'error');
            }
        }
    });
}

function runPerformanceTest() {
    showNotification('Iniciando test de rendimiento...', 'info');
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'rphub_performance_test_all',
            nonce: rphub_ajax.nonce
        },
        success: function(response) {
            if (response.success) {
                showNotification('Test de rendimiento iniciado', 'success');
            } else {
                showNotification('Error al iniciar test: ' + response.data, 'error');
            }
        }
    });
}

function generateComprehensiveReport() {
    showNotification('Generando reporte comprensivo...', 'info');
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'rphub_generate_comprehensive_report_all',
            nonce: rphub_ajax.nonce
        },
        success: function(response) {
            if (response.success) {
                showNotification('Reporte generado exitosamente', 'success');
                // Could open report in new window or download
            } else {
                showNotification('Error al generar reporte: ' + response.data, 'error');
            }
        }
    });
}

function purgeAllCloudflare() {
    if (confirm('¿Estás seguro de que quieres purgar el caché de Cloudflare de todos los sitios?')) {
        showNotification('Purgando caché de Cloudflare...', 'info');
        
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'rphub_purge_cloudflare_all',
                nonce: rphub_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('Caché purgado exitosamente', 'success');
                } else {
                    showNotification('Error al purgar caché: ' + response.data, 'error');
                }
            }
        });
    }
}

// Utility Functions
function refreshMonitoring() {
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'rphub_get_dashboard_summary',
            nonce: rphub_ajax.nonce
        },
        success: function(response) {
            if (response.success) {
                updateDashboardMetrics(response.data);
            }
        }
    });
}

function updateDashboardMetrics(data) {
    jQuery('#threats-blocked').text(data.security_overview.threats_blocked_today);
    jQuery('#bandwidth-saved').text(data.performance_overview.total_bandwidth_saved);
    jQuery('#ssl-sites').text(data.security_overview.ssl_enabled);
}

function showNotification(message, type = 'info') {
    const notification = jQuery(`
        <div class="replanta-notification notification-${type}">
            ${message}
            <button class="notification-close">&times;</button>
        </div>
    `);
    
    jQuery('body').append(notification);
    
    notification.fadeIn();
    
    setTimeout(function() {
        notification.fadeOut(function() {
            notification.remove();
        });
    }, 5000);
    
    notification.find('.notification-close').click(function() {
        notification.fadeOut(function() {
            notification.remove();
        });
    });
}

function dismissAlert(alertId) {
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'rphub_dismiss_alert',
            nonce: rphub_ajax.nonce,
            alert_id: alertId
        },
        success: function(response) {
            if (response.success) {
                jQuery(`.alert-item`).filter(function() {
                    return jQuery(this).find('.dismiss-alert').attr('onclick').includes(alertId);
                }).fadeOut();
            }
        }
    });
}

function markAllAlertsRead() {
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'rphub_mark_all_alerts_read',
            nonce: rphub_ajax.nonce
        },
        success: function(response) {
            if (response.success) {
                jQuery('.alerts-list').html('<div class="no-alerts"><span class="success-icon">✅</span><p>¡Excelente! No hay alertas activas</p></div>');
            }
        }
    });
}

function viewSiteDetails(siteId) {
    window.location.href = `admin.php?page=replanta-hub-site-details&site_id=${siteId}`;
}

function runSiteMaintenance(siteId) {
    showNotification(`Iniciando mantenimiento del sitio...`, 'info');
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'rphub_run_maintenance_check',
            nonce: rphub_ajax.nonce,
            site_id: siteId
        },
        success: function(response) {
            if (response.success) {
                showNotification('Mantenimiento completado', 'success');
                loadSitesList(); // Refresh the sites list
            } else {
                showNotification('Error en mantenimiento: ' + response.data, 'error');
            }
        }
    });
}

function viewAllActivities() {
    window.location.href = 'admin.php?page=replanta-hub-activities';
}

function testAllIntegrations() {
    testIntegrationsStatus();
    showNotification('Probando conexiones de integraciones...', 'info');
}
</script>

<!-- Quick Care Navigation -->
<?php 
$care_status = get_option('rphub_care_status', array('connected' => false));
$is_care_connected = $care_status['connected'];
?>
<div class="hub-care-nav">
    <?php if ($is_care_connected): ?>
        <a href="https://care.replanta.com/dashboard" target="_blank">
            <span class="nav-icon">📊</span>
            <span>Care Dashboard</span>
        </a>
    <?php else: ?>
        <a href="<?php echo admin_url('options-general.php?page=replanta-care'); ?>">
            <span class="nav-icon">🌱</span>
            <span>Conectar Care</span>
        </a>
    <?php endif; ?>
    
    <a href="<?php echo admin_url('admin.php?page=replanta-hub-diagnostics'); ?>">
        <span class="nav-icon">🔧</span>
        <span>Diagnósticos</span>
    </a>
</div>
