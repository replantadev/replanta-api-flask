<?php
/**
 * Template para reporte integral por defecto
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
    <title>Reporte <?php echo esc_html($report_title); ?> - <?php echo esc_html($site_info['title']); ?></title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            margin-bottom: 20px;
        }
        
        .report-meta {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 20px;
        }
        
        .meta-item {
            text-align: center;
        }
        
        .meta-label {
            font-size: 0.9em;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .meta-value {
            font-size: 1.1em;
            font-weight: 600;
            margin-top: 5px;
        }
        
        .report-body {
            padding: 40px;
        }
        
        .section {
            margin-bottom: 40px;
            border-bottom: 1px solid #eee;
            padding-bottom: 30px;
        }
        
        .section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .section-icon {
            width: 40px;
            height: 40px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 18px;
        }
        
        .section-title {
            font-size: 1.5em;
            margin: 0;
            color: #2c3e50;
        }
        
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .metric-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            border-left: 4px solid #667eea;
        }
        
        .metric-value {
            font-size: 2em;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .metric-label {
            color: #7f8c8d;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .metric-trend {
            margin-top: 10px;
            font-size: 0.8em;
        }
        
        .trend-up {
            color: #27ae60;
        }
        
        .trend-down {
            color: #e74c3c;
        }
        
        .trend-stable {
            color: #f39c12;
        }
        
        .chart-container {
            background: white;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .recommendations {
            background: #e8f5e8;
            border-left: 4px solid #27ae60;
            padding: 20px;
            border-radius: 0 8px 8px 0;
            margin: 20px 0;
        }
        
        .recommendations h4 {
            color: #27ae60;
            margin-top: 0;
        }
        
        .recommendation-item {
            margin-bottom: 15px;
            padding: 10px;
            background: white;
            border-radius: 4px;
        }
        
        .priority-high {
            border-left: 4px solid #e74c3c;
        }
        
        .priority-medium {
            border-left: 4px solid #f39c12;
        }
        
        .priority-low {
            border-left: 4px solid #3498db;
        }
        
        .incidents-list {
            background: #fff5f5;
            border: 1px solid #fed7d7;
            border-radius: 8px;
            padding: 20px;
        }
        
        .incident-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #fed7d7;
        }
        
        .incident-item:last-child {
            border-bottom: none;
        }
        
        .incident-duration {
            font-weight: bold;
            color: #e74c3c;
        }
        
        .backups-timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .backup-item {
            position: relative;
            padding: 15px 0;
            border-left: 2px solid #eee;
            padding-left: 25px;
            margin-bottom: 10px;
        }
        
        .backup-item::before {
            content: '';
            position: absolute;
            left: -6px;
            top: 20px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #27ae60;
        }
        
        .backup-success::before {
            background: #27ae60;
        }
        
        .backup-failed::before {
            background: #e74c3c;
        }
        
        .security-score {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 20px 0;
        }
        
        .score-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2em;
            font-weight: bold;
            color: white;
            margin-right: 20px;
        }
        
        .score-excellent {
            background: #27ae60;
        }
        
        .score-good {
            background: #f39c12;
        }
        
        .score-poor {
            background: #e74c3c;
        }
        
        .vulnerabilities-list {
            background: #fff5f5;
            border-radius: 8px;
            padding: 20px;
        }
        
        .vulnerability-item {
            padding: 10px;
            margin-bottom: 10px;
            background: white;
            border-radius: 4px;
            border-left: 4px solid #e74c3c;
        }
        
        .report-footer {
            background: #2c3e50;
            color: white;
            padding: 30px 40px;
            text-align: center;
        }
        
        .footer-logo {
            margin-bottom: 15px;
        }
        
        .footer-text {
            opacity: 0.8;
            font-size: 0.9em;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .report-container {
                box-shadow: none;
            }
        }
        
        @media (max-width: 768px) {
            .metrics-grid {
                grid-template-columns: 1fr;
            }
            
            .report-meta {
                flex-direction: column;
                gap: 15px;
            }
            
            .report-header,
            .report-body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <!-- Header del Reporte -->
        <div class="report-header">
            <h1><?php echo esc_html($report_title); ?></h1>
            <div class="subtitle"><?php echo esc_html($site_info['title']); ?></div>
            
            <div class="report-meta">
                <div class="meta-item">
                    <div class="meta-label">Período</div>
                    <div class="meta-value"><?php echo $this->format_period($config['period']); ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Generado</div>
                    <div class="meta-value"><?php echo date('d/m/Y H:i', strtotime($generated_at)); ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">URL</div>
                    <div class="meta-value"><?php echo esc_html($site_info['url']); ?></div>
                </div>
            </div>
        </div>
        
        <div class="report-body">
            
            <!-- Resumen Ejecutivo -->
            <?php if (isset($data['overview'])): ?>
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">📊</div>
                    <h2 class="section-title">Resumen Ejecutivo</h2>
                </div>
                
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-value"><?php echo $data['uptime']['uptime_percentage'] ?? 'N/A'; ?>%</div>
                        <div class="metric-label">Disponibilidad</div>
                        <div class="metric-trend trend-<?php echo $this->get_trend_class($data['uptime']['uptime_percentage'] ?? 0, 99); ?>">
                            <?php echo $this->get_trend_text($data['uptime']['uptime_percentage'] ?? 0, 99); ?>
                        </div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-value"><?php echo $data['performance']['average_mobile_score'] ?? 'N/A'; ?></div>
                        <div class="metric-label">Performance Móvil</div>
                        <div class="metric-trend trend-<?php echo $data['performance']['trend'] ?? 'stable'; ?>">
                            <?php echo ucfirst($data['performance']['trend'] ?? 'stable'); ?>
                        </div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-value"><?php echo $data['security']['overall_score']['score'] ?? 'N/A'; ?></div>
                        <div class="metric-label">Puntuación Seguridad</div>
                        <div class="metric-trend trend-<?php echo $this->get_security_trend($data['security']['overall_score']['score'] ?? 0); ?>">
                            <?php echo $data['security']['overall_score']['grade'] ?? 'N/A'; ?>
                        </div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-value"><?php echo $data['updates']['statistics']['successful_updates'] ?? 0; ?></div>
                        <div class="metric-label">Actualizaciones Exitosas</div>
                        <div class="metric-trend trend-up">
                            Últimos 30 días
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Rendimiento -->
            <?php if (isset($data['performance'])): ?>
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">⚡</div>
                    <h2 class="section-title">Rendimiento</h2>
                </div>
                
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-value"><?php echo $data['performance']['average_mobile_score'] ?? 'N/A'; ?></div>
                        <div class="metric-label">PageSpeed Móvil</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value"><?php echo $data['performance']['average_desktop_score'] ?? 'N/A'; ?></div>
                        <div class="metric-label">PageSpeed Desktop</div>
                    </div>
                </div>
                
                <?php if (isset($data['core_web_vitals'])): ?>
                <h4>Core Web Vitals</h4>
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-value"><?php echo number_format($data['core_web_vitals']['lcp'] ?? 0, 2); ?>s</div>
                        <div class="metric-label">LCP</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value"><?php echo number_format($data['core_web_vitals']['fid'] ?? 0, 0); ?>ms</div>
                        <div class="metric-label">FID</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value"><?php echo number_format($data['core_web_vitals']['cls'] ?? 0, 3); ?></div>
                        <div class="metric-label">CLS</div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($config['include_charts']): ?>
                <div class="chart-container">
                    <h4>Evolución del Rendimiento</h4>
                    <p><em>Gráfico de evolución del PageSpeed en los últimos <?php echo $config['period']; ?></em></p>
                    <!-- Aquí se insertaría el gráfico -->
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Seguridad -->
            <?php if (isset($data['security'])): ?>
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">🛡️</div>
                    <h2 class="section-title">Seguridad</h2>
                </div>
                
                <div class="security-score">
                    <div class="score-circle score-<?php echo $this->get_score_class($data['security']['overall_score']['score'] ?? 0); ?>">
                        <?php echo $data['security']['overall_score']['score'] ?? 0; ?>
                    </div>
                    <div>
                        <h3>Puntuación General: <?php echo $data['security']['overall_score']['grade'] ?? 'N/A'; ?></h3>
                        <p>Estado del SSL: <?php echo $data['security']['ssl_status']['valid'] ? '✅ Válido' : '❌ Problema'; ?></p>
                        <p>Vulnerabilidades: <?php echo count($data['security']['vulnerabilities'] ?? array()); ?></p>
                    </div>
                </div>
                
                <?php if (!empty($data['security']['vulnerabilities'])): ?>
                <h4>Vulnerabilidades Detectadas</h4>
                <div class="vulnerabilities-list">
                    <?php foreach ($data['security']['vulnerabilities'] as $vuln): ?>
                    <div class="vulnerability-item">
                        <strong><?php echo esc_html($vuln['title'] ?? 'Vulnerabilidad'); ?></strong>
                        <p><?php echo esc_html($vuln['description'] ?? ''); ?></p>
                        <small>Severidad: <?php echo esc_html($vuln['severity'] ?? 'Media'); ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Disponibilidad -->
            <?php if (isset($data['uptime'])): ?>
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">📈</div>
                    <h2 class="section-title">Disponibilidad</h2>
                </div>
                
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-value"><?php echo $data['uptime']['uptime_percentage']; ?>%</div>
                        <div class="metric-label">Uptime</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value"><?php echo $data['uptime']['avg_response_time']; ?>ms</div>
                        <div class="metric-label">Tiempo de Respuesta Promedio</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value"><?php echo $data['uptime']['incidents_count']; ?></div>
                        <div class="metric-label">Incidentes</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value"><?php echo $this->format_duration($data['uptime']['total_downtime']); ?></div>
                        <div class="metric-label">Tiempo Total de Caída</div>
                    </div>
                </div>
                
                <?php if (!empty($data['uptime']['incidents'])): ?>
                <h4>Incidentes Recientes</h4>
                <div class="incidents-list">
                    <?php foreach (array_slice($data['uptime']['incidents'], 0, 5) as $incident): ?>
                    <div class="incident-item">
                        <div>
                            <strong><?php echo date('d/m/Y H:i', strtotime($incident['started_at'])); ?></strong>
                            <p><?php echo esc_html($incident['error_message'] ?? 'Error desconocido'); ?></p>
                        </div>
                        <div class="incident-duration">
                            <?php echo $this->format_duration($incident['duration'] ?? 0); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Actualizaciones -->
            <?php if (isset($data['updates'])): ?>
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">🔄</div>
                    <h2 class="section-title">Actualizaciones</h2>
                </div>
                
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-value"><?php echo $data['updates']['statistics']['total_updates']; ?></div>
                        <div class="metric-label">Total Actualizaciones</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value"><?php echo $data['updates']['statistics']['successful_updates']; ?></div>
                        <div class="metric-label">Exitosas</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value"><?php echo $data['updates']['statistics']['failed_updates']; ?></div>
                        <div class="metric-label">Fallidas</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value"><?php echo $data['updates']['statistics']['rollbacks']; ?></div>
                        <div class="metric-label">Rollbacks</div>
                    </div>
                </div>
                
                <?php if (isset($data['updates']['auto_config']) && $data['updates']['auto_config']['enabled']): ?>
                <div style="background: #e8f5e8; padding: 15px; border-radius: 8px; margin: 20px 0;">
                    <h4 style="color: #27ae60; margin-top: 0;">✅ Actualizaciones Automáticas Habilitadas</h4>
                    <p>Las actualizaciones se ejecutan automáticamente según la configuración establecida.</p>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Backups -->
            <?php if (isset($data['backups'])): ?>
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">💾</div>
                    <h2 class="section-title">Backups</h2>
                </div>
                
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-value"><?php echo count($data['backups']['recent_backups'] ?? array()); ?></div>
                        <div class="metric-label">Backups Recientes</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value"><?php echo round($data['backups']['success_rate'] ?? 0); ?>%</div>
                        <div class="metric-label">Tasa de Éxito</div>
                    </div>
                </div>
                
                <?php if (!empty($data['backups']['recent_backups'])): ?>
                <h4>Backups Recientes</h4>
                <div class="backups-timeline">
                    <?php foreach (array_slice($data['backups']['recent_backups'], 0, 10) as $backup): ?>
                    <div class="backup-item backup-<?php echo $backup['status'] ?? 'success'; ?>">
                        <strong><?php echo date('d/m/Y H:i', strtotime($backup['created_at'] ?? '')); ?></strong>
                        <p><?php echo esc_html($backup['type'] ?? 'Backup'); ?> - <?php echo esc_html($backup['size_formatted'] ?? ''); ?></p>
                        <small>Estado: <?php echo ucfirst($backup['status'] ?? 'completado'); ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Recomendaciones -->
            <?php if ($config['include_recommendations'] && isset($data['recommendations'])): ?>
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">💡</div>
                    <h2 class="section-title">Recomendaciones</h2>
                </div>
                
                <div class="recommendations">
                    <h4>Sugerencias para Mejorar el Rendimiento</h4>
                    <?php foreach ($data['recommendations'] as $rec): ?>
                    <div class="recommendation-item priority-<?php echo $rec['priority'] ?? 'low'; ?>">
                        <strong><?php echo esc_html($rec['title'] ?? ''); ?></strong>
                        <p><?php echo esc_html($rec['description'] ?? ''); ?></p>
                        <small>Prioridad: <?php echo ucfirst($rec['priority'] ?? 'baja'); ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Footer del Reporte -->
        <?php if ($config['branding']): ?>
        <div class="report-footer">
            <div class="footer-logo">
                <strong>Replanta Hub</strong>
            </div>
            <div class="footer-text">
                Este reporte fue generado automáticamente por Replanta Hub.<br>
                Para más información, contacta con nuestro equipo de soporte.
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
/**
 * Funciones auxiliares para el template
 */
function format_period($period) {
    switch($period) {
        case '7d': return 'Últimos 7 días';
        case '30d': return 'Últimos 30 días';
        case '90d': return 'Últimos 90 días';
        default: return $period;
    }
}

function get_trend_class($current, $target) {
    if ($current >= $target) return 'up';
    if ($current >= $target * 0.8) return 'stable';
    return 'down';
}

function get_trend_text($current, $target) {
    if ($current >= $target) return 'Excelente';
    if ($current >= $target * 0.8) return 'Bueno';
    return 'Necesita mejoras';
}

function get_security_trend($score) {
    if ($score >= 80) return 'up';
    if ($score >= 60) return 'stable';
    return 'down';
}

function get_score_class($score) {
    if ($score >= 80) return 'excellent';
    if ($score >= 60) return 'good';
    return 'poor';
}

function format_duration($seconds) {
    if ($seconds < 60) return $seconds . 's';
    if ($seconds < 3600) return round($seconds / 60) . 'm';
    if ($seconds < 86400) return round($seconds / 3600, 1) . 'h';
    return round($seconds / 86400, 1) . 'd';
}
?>
