<?php
/**
 * Template para reporte de backups
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
    <title>Reporte de Backups - <?php echo esc_html($site_info['title']); ?></title>
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
            background: linear-gradient(135deg, #43cea2 0%, #185a9d 100%);
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
        
        .backup-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 40px 0;
        }
        
        .overview-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 25px;
            text-align: center;
            border-left: 4px solid #43cea2;
        }
        
        .overview-value {
            font-size: 2.5em;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .overview-label {
            color: #7f8c8d;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .overview-trend {
            margin-top: 10px;
            font-size: 0.8em;
        }
        
        .trend-good {
            color: #27ae60;
        }
        
        .trend-warning {
            color: #f39c12;
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
            background: #43cea2;
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
        
        .backup-status {
            background: #e8f5e8;
            border-left: 4px solid #27ae60;
            padding: 25px;
            border-radius: 0 8px 8px 0;
            margin: 30px 0;
        }
        
        .backup-status-warning {
            background: #fff3cd;
            border-left: 4px solid #f39c12;
        }
        
        .backup-status-error {
            background: #f8d7da;
            border-left: 4px solid #e74c3c;
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
        
        .backups-timeline {
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
        
        .timeline-item.partial::before {
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
        
        .status-partial {
            background: #f39c12;
            color: white;
        }
        
        .timeline-details {
            color: #495057;
        }
        
        .timeline-type {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .timeline-meta {
            display: flex;
            gap: 15px;
            margin-top: 10px;
            font-size: 0.9em;
            color: #7f8c8d;
        }
        
        .backup-config {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 30px;
            margin: 30px 0;
        }
        
        .config-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .config-item {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #43cea2;
        }
        
        .config-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .config-value {
            color: #495057;
            font-size: 0.9em;
        }
        
        .storage-usage {
            margin: 30px 0;
        }
        
        .storage-bar {
            background: #e9ecef;
            border-radius: 10px;
            height: 20px;
            overflow: hidden;
            margin: 20px 0;
            position: relative;
        }
        
        .storage-fill {
            height: 100%;
            background: linear-gradient(90deg, #43cea2, #185a9d);
            transition: width 0.3s ease;
        }
        
        .storage-label {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 0.8em;
            font-weight: 600;
        }
        
        .storage-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .storage-detail {
            text-align: center;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        
        .storage-detail-value {
            font-size: 1.5em;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .storage-detail-label {
            color: #7f8c8d;
            font-size: 0.9em;
        }
        
        .retention-policy {
            background: #e8f4fd;
            border-left: 4px solid #3498db;
            padding: 20px;
            border-radius: 0 8px 8px 0;
            margin: 30px 0;
        }
        
        .retention-rules {
            margin-top: 15px;
        }
        
        .retention-rule {
            background: white;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .rule-description {
            font-weight: 600;
        }
        
        .rule-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: 600;
        }
        
        .rule-active {
            background: #27ae60;
            color: white;
        }
        
        .rule-inactive {
            background: #95a5a6;
            color: white;
        }
        
        .recovery-test {
            margin: 30px 0;
        }
        
        .test-result {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
        }
        
        .test-success {
            border-left: 4px solid #27ae60;
            background: #f8fff8;
        }
        
        .test-failed {
            border-left: 4px solid #e74c3c;
            background: #fff8f8;
        }
        
        .test-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .test-title {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .test-date {
            color: #7f8c8d;
            font-size: 0.9em;
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
        
        .rec-critical {
            background: #e74c3c;
            color: white;
        }
        
        .rec-important {
            background: #f39c12;
            color: white;
        }
        
        .rec-suggestion {
            background: #3498db;
            color: white;
        }
        
        @media (max-width: 768px) {
            .backup-overview {
                grid-template-columns: 1fr;
            }
            
            .timeline-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            
            .timeline-meta {
                flex-direction: column;
                gap: 5px;
            }
            
            .retention-rule {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <!-- Header del Reporte -->
        <div class="report-header">
            <h1>💾 Reporte de Backups</h1>
            <div class="subtitle"><?php echo esc_html($site_info['title']); ?></div>
            <p><?php echo esc_html($site_info['url']); ?></p>
            <small>Generado el <?php echo date('d/m/Y H:i', strtotime($generated_at)); ?></small>
        </div>
        
        <div class="report-body">
            
            <!-- Resumen General -->
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">📊</div>
                    <h2 class="section-title">Resumen General</h2>
                </div>
                
                <div class="backup-overview">
                    <div class="overview-card">
                        <div class="overview-value"><?php echo count($data['recent_backups'] ?? array()); ?></div>
                        <div class="overview-label">Backups Recientes</div>
                        <div class="overview-trend trend-good">Últimos 30 días</div>
                    </div>
                    
                    <div class="overview-card">
                        <div class="overview-value"><?php echo round($data['success_rate'] ?? 0); ?>%</div>
                        <div class="overview-label">Tasa de Éxito</div>
                        <div class="overview-trend trend-<?php echo ($data['success_rate'] ?? 0) >= 95 ? 'good' : 'warning'; ?>">
                            <?php echo ($data['success_rate'] ?? 0) >= 95 ? 'Excelente' : 'Necesita atención'; ?>
                        </div>
                    </div>
                    
                    <div class="overview-card">
                        <div class="overview-value"><?php echo $data['total_storage'] ?? 'N/A'; ?></div>
                        <div class="overview-label">Almacenamiento Total</div>
                        <div class="overview-trend">Espacio utilizado</div>
                    </div>
                    
                    <div class="overview-card">
                        <div class="overview-value"><?php echo date('d/m/Y', strtotime($data['last_backup_date'] ?? 'now')); ?></div>
                        <div class="overview-label">Último Backup</div>
                        <div class="overview-trend">
                            <?php 
                            $days_since = floor((time() - strtotime($data['last_backup_date'] ?? 'now')) / 86400);
                            echo $days_since <= 1 ? 'Reciente' : "$days_since días atrás";
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Estado del Sistema de Backup -->
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">⚙️</div>
                    <h2 class="section-title">Estado del Sistema</h2>
                </div>
                
                <div class="backup-status <?php echo $this->get_backup_status_class($data); ?>">
                    <h4 style="margin-top: 0;">
                        <?php echo $this->get_backup_status_message($data); ?>
                    </h4>
                    
                    <p><?php echo $this->get_backup_status_description($data); ?></p>
                    
                    <div class="status-grid">
                        <div class="status-item">
                            <div class="status-value"><?php echo ($data['automated'] ?? false) ? 'Activo' : 'Manual'; ?></div>
                            <div class="status-label">Modo de Backup</div>
                        </div>
                        
                        <div class="status-item">
                            <div class="status-value"><?php echo ucfirst($data['frequency'] ?? 'diario'); ?></div>
                            <div class="status-label">Frecuencia</div>
                        </div>
                        
                        <div class="status-item">
                            <div class="status-value"><?php echo date('H:i', strtotime($data['schedule_time'] ?? '02:00')); ?></div>
                            <div class="status-label">Hora Programada</div>
                        </div>
                        
                        <div class="status-item">
                            <div class="status-value"><?php echo ($data['notifications'] ?? false) ? 'Sí' : 'No'; ?></div>
                            <div class="status-label">Notificaciones</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Historial de Backups -->
            <?php if (isset($data['recent_backups']) && !empty($data['recent_backups'])): ?>
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">📝</div>
                    <h2 class="section-title">Historial de Backups</h2>
                </div>
                
                <div class="backups-timeline">
                    <?php foreach (array_slice($data['recent_backups'], 0, 15) as $backup): ?>
                    <div class="timeline-item <?php echo strtolower($backup['status'] ?? 'success'); ?>">
                        <div class="timeline-header">
                            <div class="timeline-date"><?php echo date('d/m/Y H:i', strtotime($backup['created_at'] ?? 'now')); ?></div>
                            <span class="timeline-status status-<?php echo strtolower($backup['status'] ?? 'success'); ?>">
                                <?php echo ucfirst($backup['status'] ?? 'completado'); ?>
                            </span>
                        </div>
                        
                        <div class="timeline-details">
                            <div class="timeline-type"><?php echo esc_html($backup['type'] ?? 'Backup completo'); ?></div>
                            <p><?php echo esc_html($backup['description'] ?? 'Backup automático completado'); ?></p>
                            
                            <div class="timeline-meta">
                                <span>Tamaño: <?php echo esc_html($backup['size_formatted'] ?? 'N/A'); ?></span>
                                <span>Duración: <?php echo esc_html($backup['duration'] ?? 'N/A'); ?></span>
                                <span>Método: <?php echo esc_html($backup['method'] ?? 'Automático'); ?></span>
                                <?php if (isset($backup['location'])): ?>
                                <span>Ubicación: <?php echo esc_html($backup['location']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Configuración de Backup -->
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">🔧</div>
                    <h2 class="section-title">Configuración Actual</h2>
                </div>
                
                <div class="backup-config">
                    <h4>Configuración del Sistema de Backup</h4>
                    
                    <div class="config-grid">
                        <div class="config-item">
                            <div class="config-title">Tipo de Backup</div>
                            <div class="config-value"><?php echo ucfirst($data['backup_type'] ?? 'Completo'); ?></div>
                        </div>
                        
                        <div class="config-item">
                            <div class="config-title">Contenido Incluido</div>
                            <div class="config-value">
                                <?php 
                                $includes = $data['includes'] ?? array('files', 'database');
                                echo implode(', ', array_map('ucfirst', $includes));
                                ?>
                            </div>
                        </div>
                        
                        <div class="config-item">
                            <div class="config-title">Compresión</div>
                            <div class="config-value"><?php echo ($data['compression'] ?? false) ? 'Habilitada' : 'Deshabilitada'; ?></div>
                        </div>
                        
                        <div class="config-item">
                            <div class="config-title">Cifrado</div>
                            <div class="config-value"><?php echo ($data['encryption'] ?? false) ? 'Activo' : 'Inactivo'; ?></div>
                        </div>
                        
                        <div class="config-item">
                            <div class="config-title">Almacenamiento Remoto</div>
                            <div class="config-value"><?php echo ($data['remote_storage'] ?? false) ? 'Configurado' : 'Local únicamente'; ?></div>
                        </div>
                        
                        <div class="config-item">
                            <div class="config-title">Retención</div>
                            <div class="config-value"><?php echo $data['retention_days'] ?? 30; ?> días</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Uso de Almacenamiento -->
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">📊</div>
                    <h2 class="section-title">Uso de Almacenamiento</h2>
                </div>
                
                <div class="storage-usage">
                    <h4>Espacio Utilizado</h4>
                    
                    <div class="storage-bar">
                        <div class="storage-fill" style="width: <?php echo min(($data['storage_used_gb'] ?? 0) / max(($data['storage_limit_gb'] ?? 100), 1) * 100, 100); ?>%"></div>
                        <div class="storage-label">
                            <?php echo $data['storage_used_gb'] ?? 0; ?>GB de <?php echo $data['storage_limit_gb'] ?? 100; ?>GB
                        </div>
                    </div>
                    
                    <div class="storage-details">
                        <div class="storage-detail">
                            <div class="storage-detail-value"><?php echo $data['storage_used_gb'] ?? 0; ?>GB</div>
                            <div class="storage-detail-label">Espacio Usado</div>
                        </div>
                        
                        <div class="storage-detail">
                            <div class="storage-detail-value"><?php echo ($data['storage_limit_gb'] ?? 100) - ($data['storage_used_gb'] ?? 0); ?>GB</div>
                            <div class="storage-detail-label">Espacio Libre</div>
                        </div>
                        
                        <div class="storage-detail">
                            <div class="storage-detail-value"><?php echo $data['avg_backup_size'] ?? 'N/A'; ?></div>
                            <div class="storage-detail-label">Tamaño Promedio</div>
                        </div>
                        
                        <div class="storage-detail">
                            <div class="storage-detail-value"><?php echo $data['estimated_backups_remaining'] ?? 'N/A'; ?></div>
                            <div class="storage-detail-label">Backups Restantes</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Política de Retención -->
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">🗂️</div>
                    <h2 class="section-title">Política de Retención</h2>
                </div>
                
                <div class="retention-policy">
                    <h4 style="color: #3498db; margin-top: 0;">Reglas de Retención Configuradas</h4>
                    
                    <div class="retention-rules">
                        <div class="retention-rule">
                            <span class="rule-description">Backups diarios: mantener 7 días</span>
                            <span class="rule-status rule-active">Activa</span>
                        </div>
                        
                        <div class="retention-rule">
                            <span class="rule-description">Backups semanales: mantener 4 semanas</span>
                            <span class="rule-status rule-active">Activa</span>
                        </div>
                        
                        <div class="retention-rule">
                            <span class="rule-description">Backups mensuales: mantener 12 meses</span>
                            <span class="rule-status rule-<?php echo ($data['monthly_retention'] ?? false) ? 'active' : 'inactive'; ?>">
                                <?php echo ($data['monthly_retention'] ?? false) ? 'Activa' : 'Inactiva'; ?>
                            </span>
                        </div>
                        
                        <div class="retention-rule">
                            <span class="rule-description">Limpieza automática de backups antiguos</span>
                            <span class="rule-status rule-<?php echo ($data['auto_cleanup'] ?? false) ? 'active' : 'inactive'; ?>">
                                <?php echo ($data['auto_cleanup'] ?? false) ? 'Activa' : 'Inactiva'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pruebas de Recuperación -->
            <?php if (isset($data['recovery_tests']) && !empty($data['recovery_tests'])): ?>
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">🔄</div>
                    <h2 class="section-title">Pruebas de Recuperación</h2>
                </div>
                
                <div class="recovery-test">
                    <h4>Resultados de las Últimas Pruebas</h4>
                    
                    <?php foreach (array_slice($data['recovery_tests'], 0, 5) as $test): ?>
                    <div class="test-result test-<?php echo strtolower($test['status'] ?? 'success'); ?>">
                        <div class="test-header">
                            <div class="test-title"><?php echo esc_html($test['type'] ?? 'Prueba de integridad'); ?></div>
                            <div class="test-date"><?php echo date('d/m/Y H:i', strtotime($test['date'] ?? 'now')); ?></div>
                        </div>
                        
                        <p><?php echo esc_html($test['description'] ?? 'Prueba de recuperación completada'); ?></p>
                        
                        <div style="font-size: 0.9em; color: #7f8c8d;">
                            <strong>Resultado:</strong> <?php echo esc_html($test['result'] ?? 'Exitoso'); ?>
                            | <strong>Duración:</strong> <?php echo esc_html($test['duration'] ?? 'N/A'); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Recomendaciones -->
            <?php if ($config['include_recommendations']): ?>
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">💡</div>
                    <h2 class="section-title">Recomendaciones</h2>
                </div>
                
                <div class="recommendations">
                    <h4 style="color: #27ae60; margin-top: 0;">Mejoras sugeridas para el sistema de backup</h4>
                    
                    <?php if (($data['success_rate'] ?? 0) < 95): ?>
                    <div class="recommendation-item">
                        <div class="rec-icon rec-critical">!</div>
                        <div>
                            <strong>Mejorar la tasa de éxito de backups</strong>
                            <p>La tasa de éxito actual es del <?php echo round($data['success_rate'] ?? 0); ?>%. Investigue las causas de los fallos y configure alertas.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!($data['remote_storage'] ?? false)): ?>
                    <div class="recommendation-item">
                        <div class="rec-icon rec-important">⚠</div>
                        <div>
                            <strong>Configurar almacenamiento remoto</strong>
                            <p>Configure un almacenamiento remoto (nube) para tener una copia de seguridad fuera del servidor principal.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!($data['encryption'] ?? false)): ?>
                    <div class="recommendation-item">
                        <div class="rec-icon rec-important">🔐</div>
                        <div>
                            <strong>Habilitar cifrado de backups</strong>
                            <p>Active el cifrado para proteger los datos sensibles en los archivos de backup.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (($data['storage_used_gb'] ?? 0) / max(($data['storage_limit_gb'] ?? 100), 1) > 0.8): ?>
                    <div class="recommendation-item">
                        <div class="rec-icon rec-important">📊</div>
                        <div>
                            <strong>Gestionar espacio de almacenamiento</strong>
                            <p>El espacio de almacenamiento está al <?php echo round(($data['storage_used_gb'] ?? 0) / max(($data['storage_limit_gb'] ?? 100), 1) * 100); ?>%. Configure la limpieza automática o aumente el límite.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="recommendation-item">
                        <div class="rec-icon rec-suggestion">✅</div>
                        <div>
                            <strong>Realizar pruebas de recuperación regulares</strong>
                            <p>Programe pruebas de recuperación mensuales para verificar que los backups funcionan correctamente.</p>
                        </div>
                    </div>
                    
                    <div class="recommendation-item">
                        <div class="rec-icon rec-suggestion">📋</div>
                        <div>
                            <strong>Documentar procedimientos de recuperación</strong>
                            <p>Mantenga actualizada la documentación sobre cómo restaurar desde backups en caso de emergencia.</p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php
/**
 * Funciones auxiliares para el template de backups
 */
function get_backup_status_class($data) {
    $success_rate = $data['success_rate'] ?? 0;
    $last_backup = strtotime($data['last_backup_date'] ?? 'now');
    $days_since = floor((time() - $last_backup) / 86400);
    
    if ($success_rate < 90 || $days_since > 3) {
        return 'backup-status-error';
    } elseif ($success_rate < 95 || $days_since > 1) {
        return 'backup-status-warning';
    }
    return '';
}

function get_backup_status_message($data) {
    $success_rate = $data['success_rate'] ?? 0;
    $last_backup = strtotime($data['last_backup_date'] ?? 'now');
    $days_since = floor((time() - $last_backup) / 86400);
    
    if ($success_rate < 90) {
        return '❌ Sistema de backup requiere atención inmediata';
    } elseif ($days_since > 3) {
        return '⚠️ Backup desactualizado - Último backup hace más de 3 días';
    } elseif ($success_rate < 95) {
        return '⚠️ Tasa de éxito de backup por debajo del objetivo';
    } else {
        return '✅ Sistema de backup funcionando correctamente';
    }
}

function get_backup_status_description($data) {
    $success_rate = $data['success_rate'] ?? 0;
    $automated = $data['automated'] ?? false;
    
    if (!$automated) {
        return 'Los backups están configurados en modo manual. Se recomienda habilitar el modo automático.';
    } elseif ($success_rate >= 95) {
        return 'El sistema de backup automático está funcionando de manera óptima con una alta tasa de éxito.';
    } else {
        return 'Hay problemas ocasionales con el sistema de backup. Revise los logs para identificar las causas.';
    }
}
?>
