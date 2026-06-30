<?php
/**
 * Template para reporte de disponibilidad (uptime)
 * Variables disponibles: $site_info, $data, $config, $generated_at
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Disponibilidad - <?php echo esc_html($site_info['title']); ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f8f9fa;
        }
        
        .report-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .report-header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .report-header h1 {
            margin: 0 0 10px 0;
            font-size: 2.5em;
            font-weight: 300;
        }
        
        .report-header .subtitle {
            font-size: 1.2em;
            opacity: 0.9;
        }
        
        .report-body {
            padding: 40px;
        }
        
        .uptime-overview {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 40px 0;
            gap: 40px;
        }
        
        .uptime-circle {
            text-align: center;
        }
        
        .circle {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5em;
            font-weight: bold;
            color: white;
            margin: 0 auto 15px auto;
            position: relative;
            background: conic-gradient(#27ae60 0deg <?php echo ($data['uptime_percentage'] ?? 0) * 3.6; ?>deg, #e9ecef <?php echo ($data['uptime_percentage'] ?? 0) * 3.6; ?>deg 360deg);
        }
        
        .circle::before {
            content: '';
            position: absolute;
            width: 140px;
            height: 140px;
            background: white;
            border-radius: 50%;
        }
        
        .circle-content {
            position: relative;
            z-index: 1;
            color: #2c3e50;
        }
        
        .uptime-label {
            font-size: 1.3em;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .uptime-stats {
            flex: 1;
            max-width: 400px;
        }
        
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .stat-item {
            text-align: center;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
        
        .stat-value {
            font-size: 1.8em;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .section {
            margin-bottom: 40px;
            border-bottom: 1px solid #eee;
            padding-bottom: 30px;
        }
        
        .section:last-child {
            border-bottom: none;
        }
        
        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .section-icon {
            width: 40px;
            height: 40px;
            background: #4facfe;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 18px;
        }
        
        .section-title {
            font-size: 1.8em;
            margin: 0;
            color: #2c3e50;
        }
        
        .status-calendar {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 30px;
            margin: 30px 0;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(12px, 1fr));
            gap: 2px;
            margin-top: 20px;
        }
        
        .calendar-day {
            width: 12px;
            height: 12px;
            border-radius: 2px;
            position: relative;
        }
        
        .day-up {
            background: #27ae60;
        }
        
        .day-down {
            background: #e74c3c;
        }
        
        .day-partial {
            background: #f39c12;
        }
        
        .day-no-data {
            background: #e9ecef;
        }
        
        .calendar-legend {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 15px;
            font-size: 0.9em;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 2px;
        }
        
        .incidents-timeline {
            margin: 30px 0;
        }
        
        .incident-item {
            background: white;
            border: 1px solid #e9ecef;
            border-left: 4px solid #e74c3c;
            border-radius: 0 8px 8px 0;
            padding: 20px;
            margin-bottom: 15px;
            position: relative;
        }
        
        .incident-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .incident-time {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .incident-duration {
            background: #e74c3c;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: 600;
        }
        
        .incident-description {
            color: #495057;
            margin-bottom: 10px;
        }
        
        .incident-status {
            font-size: 0.9em;
            color: #7f8c8d;
        }
        
        .response-time-chart {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 30px;
            margin: 30px 0;
            text-align: center;
        }
        
        .response-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .response-stat {
            text-align: center;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
        
        .response-value {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .response-excellent {
            color: #27ae60;
        }
        
        .response-good {
            color: #f39c12;
        }
        
        .response-poor {
            color: #e74c3c;
        }
        
        .response-label {
            color: #7f8c8d;
            font-size: 0.9em;
        }
        
        .uptime-goals {
            background: #e8f5e8;
            border-left: 4px solid #27ae60;
            padding: 20px;
            border-radius: 0 8px 8px 0;
            margin: 30px 0;
        }
        
        .goal-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #c3e6cb;
        }
        
        .goal-item:last-child {
            border-bottom: none;
        }
        
        .goal-label {
            font-weight: 600;
        }
        
        .goal-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: 600;
        }
        
        .goal-met {
            background: #27ae60;
            color: white;
        }
        
        .goal-not-met {
            background: #e74c3c;
            color: white;
        }
        
        .monitoring-status {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 30px 0;
            text-align: center;
        }
        
        .monitoring-active {
            color: #27ae60;
            font-size: 1.2em;
            margin-bottom: 10px;
        }
        
        .monitoring-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .monitoring-detail {
            text-align: center;
        }
        
        .detail-value {
            font-size: 1.5em;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .detail-label {
            color: #7f8c8d;
            font-size: 0.9em;
        }
        
        @media (max-width: 768px) {
            .uptime-overview {
                flex-direction: column;
                gap: 20px;
            }
            
            .circle {
                width: 150px;
                height: 150px;
                font-size: 2em;
            }
            
            .circle::before {
                width: 120px;
                height: 120px;
            }
            
            .stat-grid {
                grid-template-columns: 1fr;
            }
            
            .calendar-grid {
                grid-template-columns: repeat(auto-fit, minmax(8px, 1fr));
            }
            
            .calendar-day {
                width: 8px;
                height: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <!-- Header del Reporte -->
        <div class="report-header">
            <h1>📈 Reporte de Disponibilidad</h1>
            <div class="subtitle"><?php echo esc_html($site_info['title']); ?></div>
            <p><?php echo esc_html($site_info['url']); ?></p>
            <small>Generado el <?php echo date('d/m/Y H:i', strtotime($generated_at)); ?></small>
        </div>
        
        <div class="report-body">
            
            <!-- Resumen de Uptime -->
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">🎯</div>
                    <h2 class="section-title">Resumen General</h2>
                </div>
                
                <div class="uptime-overview">
                    <div class="uptime-circle">
                        <div class="circle">
                            <div class="circle-content">
                                <?php echo number_format($data['uptime_percentage'] ?? 0, 2); ?>%
                            </div>
                        </div>
                        <div class="uptime-label">Uptime</div>
                    </div>
                    
                    <div class="uptime-stats">
                        <div class="stat-grid">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $data['avg_response_time'] ?? 'N/A'; ?>ms</div>
                                <div class="stat-label">Tiempo Respuesta</div>
                            </div>
                            
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $data['incidents_count'] ?? 0; ?></div>
                                <div class="stat-label">Incidentes</div>
                            </div>
                            
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $this->format_duration($data['total_downtime'] ?? 0); ?></div>
                                <div class="stat-label">Tiempo Caída</div>
                            </div>
                            
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $data['checks_performed'] ?? 0; ?></div>
                                <div class="stat-label">Verificaciones</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Estado de Monitorización -->
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">🔍</div>
                    <h2 class="section-title">Estado del Monitoreo</h2>
                </div>
                
                <div class="monitoring-status">
                    <div class="monitoring-active">
                        ✅ Monitorización activa cada <?php echo $data['check_interval'] ?? 1; ?> minuto(s)
                    </div>
                    
                    <p>El sistema de monitorización está funcionando correctamente y verificando la disponibilidad de su sitio web.</p>
                    
                    <div class="monitoring-details">
                        <div class="monitoring-detail">
                            <div class="detail-value"><?php echo date('d/m/Y H:i', strtotime($data['last_check'] ?? 'now')); ?></div>
                            <div class="detail-label">Última Verificación</div>
                        </div>
                        
                        <div class="monitoring-detail">
                            <div class="detail-value"><?php echo $data['consecutive_successes'] ?? 0; ?></div>
                            <div class="detail-label">Éxitos Consecutivos</div>
                        </div>
                        
                        <div class="monitoring-detail">
                            <div class="detail-value"><?php echo number_format(($data['success_rate'] ?? 0), 2); ?>%</div>
                            <div class="detail-label">Tasa de Éxito</div>
                        </div>
                        
                        <div class="monitoring-detail">
                            <div class="detail-value"><?php echo date('d/m/Y', strtotime($data['monitoring_since'] ?? 'now')); ?></div>
                            <div class="detail-label">Monitoreando Desde</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Calendario de Estado -->
            <?php if (isset($data['daily_status']) && $config['include_charts']): ?>
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">📅</div>
                    <h2 class="section-title">Calendario de Disponibilidad</h2>
                </div>
                
                <div class="status-calendar">
                    <h4>Últimos <?php echo count($data['daily_status']); ?> días</h4>
                    <p>Cada cuadro representa un día. Verde = funcionando, Rojo = caída, Amarillo = parcial.</p>
                    
                    <div class="calendar-grid">
                        <?php foreach ($data['daily_status'] as $day): ?>
                        <div class="calendar-day day-<?php echo $this->get_day_status($day['uptime_percentage'] ?? 0); ?>" 
                             title="<?php echo date('d/m/Y', strtotime($day['date'])); ?>: <?php echo number_format($day['uptime_percentage'] ?? 0, 1); ?>% uptime"></div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="calendar-legend">
                        <div class="legend-item">
                            <div class="legend-color day-up"></div>
                            <span>Funcionando (>99%)</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color day-partial"></div>
                            <span>Parcial (95-99%)</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color day-down"></div>
                            <span>Problemas (<95%)</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color day-no-data"></div>
                            <span>Sin datos</span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Tiempos de Respuesta -->
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">⚡</div>
                    <h2 class="section-title">Tiempos de Respuesta</h2>
                </div>
                
                <div class="response-stats">
                    <div class="response-stat">
                        <div class="response-value response-<?php echo $this->get_response_class($data['avg_response_time'] ?? 0); ?>">
                            <?php echo $data['avg_response_time'] ?? 'N/A'; ?>ms
                        </div>
                        <div class="response-label">Promedio</div>
                    </div>
                    
                    <div class="response-stat">
                        <div class="response-value response-<?php echo $this->get_response_class($data['min_response_time'] ?? 0); ?>">
                            <?php echo $data['min_response_time'] ?? 'N/A'; ?>ms
                        </div>
                        <div class="response-label">Mínimo</div>
                    </div>
                    
                    <div class="response-stat">
                        <div class="response-value response-<?php echo $this->get_response_class($data['max_response_time'] ?? 0); ?>">
                            <?php echo $data['max_response_time'] ?? 'N/A'; ?>ms
                        </div>
                        <div class="response-label">Máximo</div>
                    </div>
                    
                    <div class="response-stat">
                        <div class="response-value response-<?php echo $this->get_response_class($data['p95_response_time'] ?? 0); ?>">
                            <?php echo $data['p95_response_time'] ?? 'N/A'; ?>ms
                        </div>
                        <div class="response-label">Percentil 95</div>
                    </div>
                </div>
                
                <?php if ($config['include_charts']): ?>
                <div class="response-time-chart">
                    <h4>Evolución de Tiempos de Respuesta</h4>
                    <p><em>Gráfico de tiempos de respuesta en los últimos <?php echo $this->format_period($config['period']); ?></em></p>
                    <div style="height: 250px; display: flex; align-items: center; justify-content: center; background: #f8f9fa; border-radius: 4px;">
                        <span style="color: #7f8c8d;">Gráfico de tiempos de respuesta disponible en la versión completa</span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Incidentes -->
            <?php if (isset($data['incidents']) && !empty($data['incidents'])): ?>
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">🚨</div>
                    <h2 class="section-title">Incidentes Recientes</h2>
                </div>
                
                <div class="incidents-timeline">
                    <?php foreach (array_slice($data['incidents'], 0, 10) as $incident): ?>
                    <div class="incident-item">
                        <div class="incident-header">
                            <div class="incident-time">
                                <?php echo date('d/m/Y H:i', strtotime($incident['started_at'] ?? 'now')); ?>
                            </div>
                            <div class="incident-duration">
                                <?php echo $this->format_duration($incident['duration'] ?? 0); ?>
                            </div>
                        </div>
                        
                        <div class="incident-description">
                            <strong>Error:</strong> <?php echo esc_html($incident['error_message'] ?? 'Error desconocido'); ?>
                        </div>
                        
                        <div class="incident-status">
                            Estado: <?php echo ucfirst($incident['status'] ?? 'resuelto'); ?>
                            <?php if (isset($incident['resolved_at'])): ?>
                            | Resuelto: <?php echo date('d/m/Y H:i', strtotime($incident['resolved_at'])); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">✅</div>
                    <h2 class="section-title">Sin Incidentes</h2>
                </div>
                
                <div style="background: #e8f5e8; border-left: 4px solid #27ae60; padding: 20px; border-radius: 0 8px 8px 0;">
                    <h4 style="color: #27ae60; margin-top: 0;">🎉 ¡Excelente!</h4>
                    <p>No se han detectado incidentes en el período seleccionado. Su sitio web ha mantenido una disponibilidad estable.</p>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Objetivos de Uptime -->
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">🎯</div>
                    <h2 class="section-title">Objetivos de Disponibilidad</h2>
                </div>
                
                <div class="uptime-goals">
                    <h4 style="color: #27ae60; margin-top: 0;">Cumplimiento de SLA</h4>
                    
                    <div class="goal-item">
                        <span class="goal-label">99.9% Uptime (SLA Premium)</span>
                        <span class="goal-status <?php echo ($data['uptime_percentage'] ?? 0) >= 99.9 ? 'goal-met' : 'goal-not-met'; ?>">
                            <?php echo ($data['uptime_percentage'] ?? 0) >= 99.9 ? '✅ Cumplido' : '❌ No Cumplido'; ?>
                        </span>
                    </div>
                    
                    <div class="goal-item">
                        <span class="goal-label">99.5% Uptime (SLA Estándar)</span>
                        <span class="goal-status <?php echo ($data['uptime_percentage'] ?? 0) >= 99.5 ? 'goal-met' : 'goal-not-met'; ?>">
                            <?php echo ($data['uptime_percentage'] ?? 0) >= 99.5 ? '✅ Cumplido' : '❌ No Cumplido'; ?>
                        </span>
                    </div>
                    
                    <div class="goal-item">
                        <span class="goal-label">Tiempo de respuesta < 2000ms</span>
                        <span class="goal-status <?php echo ($data['avg_response_time'] ?? 999999) < 2000 ? 'goal-met' : 'goal-not-met'; ?>">
                            <?php echo ($data['avg_response_time'] ?? 999999) < 2000 ? '✅ Cumplido' : '❌ No Cumplido'; ?>
                        </span>
                    </div>
                    
                    <div class="goal-item">
                        <span class="goal-label">Menos de 5 incidentes/mes</span>
                        <span class="goal-status <?php echo ($data['incidents_count'] ?? 999) < 5 ? 'goal-met' : 'goal-not-met'; ?>">
                            <?php echo ($data['incidents_count'] ?? 999) < 5 ? '✅ Cumplido' : '❌ No Cumplido'; ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Recomendaciones -->
            <?php if ($config['include_recommendations']): ?>
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">💡</div>
                    <h2 class="section-title">Recomendaciones</h2>
                </div>
                
                <div style="background: #e8f5e8; border-left: 4px solid #27ae60; padding: 20px; border-radius: 0 8px 8px 0;">
                    <h4 style="color: #27ae60; margin-top: 0;">Sugerencias para mejorar la disponibilidad:</h4>
                    
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php if (($data['uptime_percentage'] ?? 0) < 99.9): ?>
                        <li>🔴 <strong>Prioridad Alta:</strong> Investigar las causas de los tiempos de inactividad</li>
                        <?php endif; ?>
                        
                        <?php if (($data['avg_response_time'] ?? 0) > 2000): ?>
                        <li>🟡 <strong>Prioridad Media:</strong> Optimizar el tiempo de respuesta del servidor</li>
                        <?php endif; ?>
                        
                        <?php if (($data['incidents_count'] ?? 0) > 5): ?>
                        <li>🟡 <strong>Prioridad Media:</strong> Analizar patrones en los incidentes frecuentes</li>
                        <?php endif; ?>
                        
                        <li>🔵 <strong>Mantenimiento:</strong> Configurar alertas proactivas para tiempo de respuesta</li>
                        <li>🔵 <strong>Mejora:</strong> Considerar implementar un CDN para mejorar la velocidad global</li>
                        <li>🔵 <strong>Backup:</strong> Asegurar que los backups automáticos están funcionando correctamente</li>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php
/**
 * Funciones auxiliares para el template de uptime
 */
function format_duration($seconds) {
    if ($seconds < 60) return $seconds . 's';
    if ($seconds < 3600) return round($seconds / 60) . 'm';
    if ($seconds < 86400) return round($seconds / 3600, 1) . 'h';
    return round($seconds / 86400, 1) . 'd';
}

function get_day_status($uptime_percentage) {
    if ($uptime_percentage >= 99) return 'up';
    if ($uptime_percentage >= 95) return 'partial';
    if ($uptime_percentage > 0) return 'down';
    return 'no-data';
}

function get_response_class($response_time) {
    if ($response_time <= 500) return 'excellent';
    if ($response_time <= 2000) return 'good';
    return 'poor';
}

function format_period($period) {
    switch($period) {
        case '7d': return 'últimos 7 días';
        case '30d': return 'últimos 30 días';
        case '90d': return 'últimos 90 días';
        default: return $period;
    }
}
?>
