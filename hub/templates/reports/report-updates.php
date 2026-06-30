<?php
/**
 * Template para reporte de actualizaciones
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
    <title>Reporte de Actualizaciones - <?php echo esc_html($site_info['title']); ?></title>
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
        }
        
        .report-body {
            padding: 40px;
        }
        
        .updates-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 40px 0;
        }
        
        .summary-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 25px;
            text-align: center;
            border-left: 4px solid #667eea;
        }
        
        .summary-value {
            font-size: 2.5em;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .summary-label {
            color: #7f8c8d;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .summary-trend {
            margin-top: 10px;
            font-size: 0.8em;
        }
        
        .trend-up {
            color: #27ae60;
        }
        
        .trend-stable {
            color: #3498db;
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
            font-size: 1.8em;
            margin: 0;
            color: #2c3e50;
        }
        
        .auto-updates-status {
            background: #e8f5e8;
            border-left: 4px solid #27ae60;
            padding: 25px;
            border-radius: 0 8px 8px 0;
            margin: 30px 0;
        }
        
        .auto-updates-disabled {
            background: #fff3cd;
            border-left: 4px solid #f39c12;
        }
        
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .status-item {
            text-align: center;
        }
        
        .status-value {
            font-size: 1.5em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .status-label {
            color: #7f8c8d;
            font-size: 0.9em;
        }
        
        .updates-timeline {
            position: relative;
            margin: 30px 0;
        }
        
        .timeline-item {
            position: relative;
            padding: 20px 0 20px 40px;
            border-left: 2px solid #e9ecef;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -6px;
            top: 25px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #27ae60;
        }
        
        .timeline-item.failed::before {
            background: #e74c3c;
        }
        
        .timeline-item.pending::before {
            background: #f39c12;
        }
        
        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .timeline-date {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .timeline-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-success {
            background: #27ae60;
            color: white;
        }
        
        .status-failed {
            background: #e74c3c;
            color: white;
        }
        
        .status-pending {
            background: #f39c12;
            color: white;
        }
        
        .timeline-details {
            color: #495057;
        }
        
        .timeline-component {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .available-updates {
            margin: 30px 0;
        }
        
        .update-item {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .update-critical {
            border-left: 4px solid #e74c3c;
            background: #fff5f5;
        }
        
        .update-security {
            border-left: 4px solid #f39c12;
            background: #fffbf0;
        }
        
        .update-normal {
            border-left: 4px solid #3498db;
            background: #f0faff;
        }
        
        .update-info {
            flex: 1;
        }
        
        .update-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .update-description {
            color: #7f8c8d;
            font-size: 0.9em;
            margin-bottom: 10px;
        }
        
        .update-meta {
            display: flex;
            gap: 15px;
            font-size: 0.8em;
            color: #95a5a6;
        }
        
        .update-actions {
            text-align: right;
        }
        
        .update-version {
            font-size: 1.2em;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .update-priority {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: 600;
        }
        
        .priority-critical {
            background: #e74c3c;
            color: white;
        }
        
        .priority-security {
            background: #f39c12;
            color: white;
        }
        
        .priority-normal {
            background: #3498db;
            color: white;
        }
        
        .schedule-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 30px 0;
        }
        
        .schedule-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .schedule-item {
            text-align: center;
            background: white;
            padding: 15px;
            border-radius: 4px;
        }
        
        .rollback-history {
            margin: 30px 0;
        }
        
        .rollback-item {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-left: 4px solid #f39c12;
            border-radius: 0 8px 8px 0;
            padding: 20px;
            margin-bottom: 15px;
        }
        
        .rollback-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .rollback-title {
            font-weight: 600;
            color: #856404;
        }
        
        .rollback-reason {
            color: #856404;
            font-size: 0.9em;
        }
        
        .backup-integration {
            background: #e8f5e8;
            border-left: 4px solid #27ae60;
            padding: 20px;
            border-radius: 0 8px 8px 0;
            margin: 30px 0;
        }
        
        .backup-status {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .backup-enabled {
            color: #27ae60;
            font-weight: 600;
        }
        
        .recommendations {
            background: #e8f5e8;
            border-left: 4px solid #27ae60;
            padding: 20px;
            border-radius: 0 8px 8px 0;
            margin: 30px 0;
        }
        
        .recommendation-item {
            margin-bottom: 15px;
            padding: 15px;
            background: white;
            border-radius: 4px;
            display: flex;
            align-items: flex-start;
        }
        
        .rec-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 16px;
            flex-shrink: 0;
        }
        
        .rec-high {
            background: #e74c3c;
            color: white;
        }
        
        .rec-medium {
            background: #f39c12;
            color: white;
        }
        
        .rec-low {
            background: #3498db;
            color: white;
        }
        
        @media (max-width: 768px) {
            .updates-summary {
                grid-template-columns: 1fr;
            }
            
            .update-item {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .timeline-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <!-- Header del Reporte -->
        <div class="report-header">
            <h1>🔄 Reporte de Actualizaciones</h1>
            <div class="subtitle"><?php echo esc_html($site_info['title']); ?></div>
            <p><?php echo esc_html($site_info['url']); ?></p>
            <small>Generado el <?php echo date('d/m/Y H:i', strtotime($generated_at)); ?></small>
        </div>
        
        <div class="report-body">
            
            <!-- Resumen de Actualizaciones -->
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">📊</div>
                    <h2 class="section-title">Resumen General</h2>
                </div>
                
                <div class="updates-summary">
                    <div class="summary-card">
                        <div class="summary-value"><?php echo $data['statistics']['total_updates'] ?? 0; ?></div>
                        <div class="summary-label">Total Actualizaciones</div>
                        <div class="summary-trend trend-stable">Últimos 30 días</div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-value"><?php echo $data['statistics']['successful_updates'] ?? 0; ?></div>
                        <div class="summary-label">Exitosas</div>
                        <div class="summary-trend trend-up">
                            <?php echo round(($data['statistics']['successful_updates'] ?? 0) / max(($data['statistics']['total_updates'] ?? 1), 1) * 100); ?>% tasa éxito
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-value"><?php echo $data['statistics']['failed_updates'] ?? 0; ?></div>
                        <div class="summary-label">Fallidas</div>
                        <div class="summary-trend">
                            <?php echo $data['statistics']['failed_updates'] ?? 0 > 0 ? 'Requiere atención' : 'Todo bien'; ?>
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-value"><?php echo $data['statistics']['rollbacks'] ?? 0; ?></div>
                        <div class="summary-label">Rollbacks</div>
                        <div class="summary-trend">Reversiones automáticas</div>
                    </div>
                </div>
            </div>
            
            <!-- Estado de Actualizaciones Automáticas -->
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">⚙️</div>
                    <h2 class="section-title">Configuración Automática</h2>
                </div>
                
                <div class="auto-updates-status <?php echo ($data['auto_config']['enabled'] ?? false) ? '' : 'auto-updates-disabled'; ?>">
                    <h4 style="margin-top: 0;">
                        <?php if ($data['auto_config']['enabled'] ?? false): ?>
                            ✅ Actualizaciones Automáticas Habilitadas
                        <?php else: ?>
                            ⚠️ Actualizaciones Automáticas Deshabilitadas
                        <?php endif; ?>
                    </h4>
                    
                    <p>
                        <?php if ($data['auto_config']['enabled'] ?? false): ?>
                            Las actualizaciones se ejecutan automáticamente según la configuración establecida.
                        <?php else: ?>
                            Las actualizaciones requieren intervención manual. Se recomienda habilitar las actualizaciones automáticas.
                        <?php endif; ?>
                    </p>
                    
                    <?php if ($data['auto_config']['enabled'] ?? false): ?>
                    <div class="status-grid">
                        <div class="status-item">
                            <div class="status-value"><?php echo ucfirst($data['auto_config']['schedule'] ?? 'diario'); ?></div>
                            <div class="status-label">Frecuencia</div>
                        </div>
                        
                        <div class="status-item">
                            <div class="status-value"><?php echo $data['auto_config']['backup_before'] ? 'Sí' : 'No'; ?></div>
                            <div class="status-label">Backup Automático</div>
                        </div>
                        
                        <div class="status-item">
                            <div class="status-value"><?php echo $data['auto_config']['rollback_enabled'] ? 'Sí' : 'No'; ?></div>
                            <div class="status-label">Rollback Automático</div>
                        </div>
                        
                        <div class="status-item">
                            <div class="status-value"><?php echo date('H:i', strtotime($data['auto_config']['maintenance_window'] ?? '02:00')); ?></div>
                            <div class="status-label">Ventana Mantenimiento</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Actualizaciones Disponibles -->
            <?php if (isset($data['available_updates']) && !empty($data['available_updates'])): ?>
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">📥</div>
                    <h2 class="section-title">Actualizaciones Disponibles</h2>
                </div>
                
                <div class="available-updates">
                    <?php foreach ($data['available_updates'] as $update): ?>
                    <div class="update-item update-<?php echo strtolower($update['priority'] ?? 'normal'); ?>">
                        <div class="update-info">
                            <div class="update-title"><?php echo esc_html($update['name'] ?? 'Componente'); ?></div>
                            <div class="update-description"><?php echo esc_html($update['description'] ?? 'Actualización disponible'); ?></div>
                            <div class="update-meta">
                                <span>Tipo: <?php echo ucfirst($update['type'] ?? 'plugin'); ?></span>
                                <span>Versión actual: <?php echo esc_html($update['current_version'] ?? 'N/A'); ?></span>
                                <span>Tamaño: <?php echo esc_html($update['size'] ?? 'N/A'); ?></span>
                            </div>
                        </div>
                        
                        <div class="update-actions">
                            <div class="update-version"><?php echo esc_html($update['new_version'] ?? 'N/A'); ?></div>
                            <span class="update-priority priority-<?php echo strtolower($update['priority'] ?? 'normal'); ?>">
                                <?php echo ucfirst($update['priority'] ?? 'Normal'); ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Historial de Actualizaciones -->
            <?php if (isset($data['update_history']) && !empty($data['update_history'])): ?>
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">📝</div>
                    <h2 class="section-title">Historial Reciente</h2>
                </div>
                
                <div class="updates-timeline">
                    <?php foreach (array_slice($data['update_history'], 0, 15) as $update): ?>
                    <div class="timeline-item <?php echo strtolower($update['status'] ?? 'success'); ?>">
                        <div class="timeline-header">
                            <div class="timeline-date"><?php echo date('d/m/Y H:i', strtotime($update['date'] ?? 'now')); ?></div>
                            <span class="timeline-status status-<?php echo strtolower($update['status'] ?? 'success'); ?>">
                                <?php echo ucfirst($update['status'] ?? 'success'); ?>
                            </span>
                        </div>
                        
                        <div class="timeline-details">
                            <div class="timeline-component"><?php echo esc_html($update['component'] ?? 'WordPress Core'); ?></div>
                            <p><?php echo esc_html($update['description'] ?? 'Actualización completada'); ?></p>
                            <?php if (isset($update['version_from']) && isset($update['version_to'])): ?>
                            <small>Versión: <?php echo esc_html($update['version_from']); ?> → <?php echo esc_html($update['version_to']); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Información de Programación -->
            <?php if (isset($data['schedule_info'])): ?>
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">📅</div>
                    <h2 class="section-title">Programación de Actualizaciones</h2>
                </div>
                
                <div class="schedule-info">
                    <h4>Próximas Actualizaciones Programadas</h4>
                    
                    <div class="schedule-grid">
                        <div class="schedule-item">
                            <div class="status-value"><?php echo date('d/m/Y', strtotime($data['schedule_info']['next_check'] ?? 'now')); ?></div>
                            <div class="status-label">Próxima Verificación</div>
                        </div>
                        
                        <div class="schedule-item">
                            <div class="status-value"><?php echo date('H:i', strtotime($data['schedule_info']['maintenance_window'] ?? '02:00')); ?></div>
                            <div class="status-label">Ventana de Mantenimiento</div>
                        </div>
                        
                        <div class="schedule-item">
                            <div class="status-value"><?php echo ucfirst($data['schedule_info']['frequency'] ?? 'diario'); ?></div>
                            <div class="status-label">Frecuencia</div>
                        </div>
                        
                        <div class="schedule-item">
                            <div class="status-value"><?php echo $data['schedule_info']['auto_minor'] ? 'Sí' : 'No'; ?></div>
                            <div class="status-label">Actualizaciones Menores</div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Historial de Rollbacks -->
            <?php if (isset($data['rollback_history']) && !empty($data['rollback_history'])): ?>
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">↩️</div>
                    <h2 class="section-title">Rollbacks Ejecutados</h2>
                </div>
                
                <div class="rollback-history">
                    <?php foreach ($data['rollback_history'] as $rollback): ?>
                    <div class="rollback-item">
                        <div class="rollback-header">
                            <div class="rollback-title"><?php echo esc_html($rollback['component'] ?? 'Componente'); ?></div>
                            <small><?php echo date('d/m/Y H:i', strtotime($rollback['date'] ?? 'now')); ?></small>
                        </div>
                        
                        <div class="rollback-reason">
                            <strong>Motivo:</strong> <?php echo esc_html($rollback['reason'] ?? 'Error después de actualización'); ?>
                        </div>
                        
                        <p><?php echo esc_html($rollback['description'] ?? 'Se revirtió automáticamente la actualización'); ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Integración con Backups -->
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">💾</div>
                    <h2 class="section-title">Integración con Backups</h2>
                </div>
                
                <div class="backup-integration">
                    <div class="backup-status">
                        <h4 style="margin: 0;">Estado de Backup Automático</h4>
                        <span class="backup-enabled">
                            <?php echo ($data['backup_integration']['enabled'] ?? false) ? '✅ Activo' : '❌ Inactivo'; ?>
                        </span>
                    </div>
                    
                    <p>
                        <?php if ($data['backup_integration']['enabled'] ?? false): ?>
                            Se crea automáticamente un backup antes de cada actualización para permitir rollbacks seguros.
                        <?php else: ?>
                            Los backups automáticos no están configurados. Se recomienda habilitar esta funcionalidad.
                        <?php endif; ?>
                    </p>
                    
                    <?php if ($data['backup_integration']['enabled'] ?? false): ?>
                    <div class="status-grid">
                        <div class="status-item">
                            <div class="status-value"><?php echo $data['backup_integration']['backups_created'] ?? 0; ?></div>
                            <div class="status-label">Backups Creados</div>
                        </div>
                        
                        <div class="status-item">
                            <div class="status-value"><?php echo date('d/m/Y', strtotime($data['backup_integration']['last_backup'] ?? 'now')); ?></div>
                            <div class="status-label">Último Backup</div>
                        </div>
                        
                        <div class="status-item">
                            <div class="status-value"><?php echo round($data['backup_integration']['success_rate'] ?? 100); ?>%</div>
                            <div class="status-label">Tasa de Éxito</div>
                        </div>
                        
                        <div class="status-item">
                            <div class="status-value"><?php echo $data['backup_integration']['storage_used'] ?? 'N/A'; ?></div>
                            <div class="status-label">Almacenamiento</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recomendaciones -->
            <?php if ($config['include_recommendations']): ?>
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">💡</div>
                    <h2 class="section-title">Recomendaciones</h2>
                </div>
                
                <div class="recommendations">
                    <h4 style="color: #27ae60; margin-top: 0;">Mejoras sugeridas para el sistema de actualizaciones</h4>
                    
                    <?php if (!($data['auto_config']['enabled'] ?? false)): ?>
                    <div class="recommendation-item">
                        <div class="rec-icon rec-high">!</div>
                        <div>
                            <strong>Habilitar actualizaciones automáticas</strong>
                            <p>Configure las actualizaciones automáticas para mantener el sitio seguro y actualizado sin intervención manual.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!($data['backup_integration']['enabled'] ?? false)): ?>
                    <div class="recommendation-item">
                        <div class="rec-icon rec-high">!</div>
                        <div>
                            <strong>Configurar backups antes de actualizaciones</strong>
                            <p>Active la creación automática de backups antes de cada actualización para poder hacer rollback si es necesario.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (($data['statistics']['failed_updates'] ?? 0) > 0): ?>
                    <div class="recommendation-item">
                        <div class="rec-icon rec-medium">⚠</div>
                        <div>
                            <strong>Investigar actualizaciones fallidas</strong>
                            <p>Revise los logs de las actualizaciones fallidas para identificar y resolver problemas recurrentes.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="recommendation-item">
                        <div class="rec-icon rec-low">i</div>
                        <div>
                            <strong>Revisar actualizaciones disponibles</strong>
                            <p>Mantenga todos los plugins, temas y WordPress Core actualizados a las últimas versiones.</p>
                        </div>
                    </div>
                    
                    <div class="recommendation-item">
                        <div class="rec-icon rec-low">i</div>
                        <div>
                            <strong>Programar ventana de mantenimiento</strong>
                            <p>Configure una ventana de mantenimiento durante las horas de menor tráfico para minimizar el impacto.</p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
