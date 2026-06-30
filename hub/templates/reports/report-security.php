<?php
/**
 * Template para reporte de seguridad
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
    <title>Reporte de Seguridad - <?php echo esc_html($site_info['title']); ?></title>
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
        
        .security-overview {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 40px 0;
            gap: 40px;
        }
        
        .security-score {
            text-align: center;
        }
        
        .score-circle {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3em;
            font-weight: bold;
            color: white;
            margin: 0 auto 15px auto;
            position: relative;
        }
        
        .score-excellent { background: linear-gradient(135deg, #27ae60, #2ecc71); }
        .score-good { background: linear-gradient(135deg, #f39c12, #f1c40f); }
        .score-poor { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        
        .score-label {
            font-size: 1.3em;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .score-grade {
            font-size: 1.1em;
            color: #7f8c8d;
            margin-top: 5px;
        }
        
        .security-summary {
            flex: 1;
            max-width: 400px;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        
        .summary-item:last-child {
            border-bottom: none;
        }
        
        .summary-label {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .summary-value {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
        }
        
        .status-secure {
            background: #e8f5e8;
            color: #27ae60;
        }
        
        .status-warning {
            background: #fff3cd;
            color: #f39c12;
        }
        
        .status-danger {
            background: #f8d7da;
            color: #e74c3c;
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
        
        .ssl-status {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 30px;
            margin: 20px 0;
        }
        
        .ssl-valid {
            border-left: 4px solid #27ae60;
            background: #e8f5e8;
        }
        
        .ssl-invalid {
            border-left: 4px solid #e74c3c;
            background: #f8d7da;
        }
        
        .ssl-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .ssl-detail {
            text-align: center;
        }
        
        .ssl-detail-value {
            font-size: 1.5em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .ssl-detail-label {
            color: #7f8c8d;
            font-size: 0.9em;
        }
        
        .vulnerabilities {
            margin: 30px 0;
        }
        
        .vulnerability-item {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            position: relative;
        }
        
        .vuln-critical {
            border-left: 4px solid #e74c3c;
            background: #fff5f5;
        }
        
        .vuln-high {
            border-left: 4px solid #fd7e14;
            background: #fff8f0;
        }
        
        .vuln-medium {
            border-left: 4px solid #ffc107;
            background: #fffbf0;
        }
        
        .vuln-low {
            border-left: 4px solid #17a2b8;
            background: #f0faff;
        }
        
        .vuln-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .vuln-title {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.1em;
        }
        
        .vuln-severity {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .severity-critical {
            background: #e74c3c;
            color: white;
        }
        
        .severity-high {
            background: #fd7e14;
            color: white;
        }
        
        .severity-medium {
            background: #ffc107;
            color: #212529;
        }
        
        .severity-low {
            background: #17a2b8;
            color: white;
        }
        
        .vuln-description {
            color: #495057;
            margin-bottom: 15px;
            line-height: 1.6;
        }
        
        .vuln-solution {
            background: #e8f5e8;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .vuln-solution h5 {
            color: #27ae60;
            margin: 0 0 10px 0;
        }
        
        .security-checks {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .check-item {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        
        .check-passed {
            border-color: #27ae60;
            background: #e8f5e8;
        }
        
        .check-failed {
            border-color: #e74c3c;
            background: #fff5f5;
        }
        
        .check-icon {
            font-size: 2em;
            margin-bottom: 10px;
        }
        
        .check-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        
        .check-description {
            font-size: 0.9em;
            color: #7f8c8d;
        }
        
        .recommendations {
            background: #e8f5e8;
            border-left: 4px solid #27ae60;
            padding: 20px;
            border-radius: 0 8px 8px 0;
            margin: 30px 0;
        }
        
        .recommendations h4 {
            color: #27ae60;
            margin-top: 0;
        }
        
        .recommendation-item {
            margin-bottom: 15px;
            padding: 15px;
            background: white;
            border-radius: 4px;
            display: flex;
            align-items: flex-start;
        }
        
        .rec-priority {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-weight: bold;
            font-size: 0.8em;
            flex-shrink: 0;
        }
        
        .priority-1 { background: #e74c3c; }
        .priority-2 { background: #f39c12; }
        .priority-3 { background: #3498db; }
        
        .security-timeline {
            margin: 30px 0;
        }
        
        .timeline-item {
            display: flex;
            margin-bottom: 20px;
            position: relative;
            padding-left: 30px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 8px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #667eea;
        }
        
        .timeline-item::after {
            content: '';
            position: absolute;
            left: 13px;
            top: 16px;
            width: 2px;
            height: calc(100% + 4px);
            background: #e9ecef;
        }
        
        .timeline-item:last-child::after {
            display: none;
        }
        
        .timeline-content {
            flex: 1;
        }
        
        .timeline-date {
            font-size: 0.9em;
            color: #7f8c8d;
            margin-bottom: 5px;
        }
        
        .timeline-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .timeline-description {
            color: #495057;
            font-size: 0.9em;
        }
        
        @media (max-width: 768px) {
            .security-overview {
                flex-direction: column;
                gap: 20px;
            }
            
            .score-circle {
                width: 150px;
                height: 150px;
                font-size: 2.5em;
            }
            
            .security-checks {
                grid-template-columns: 1fr;
            }
            
            .ssl-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <!-- Header del Reporte -->
        <div class="report-header">
            <h1>🛡️ Reporte de Seguridad</h1>
            <div class="subtitle"><?php echo esc_html($site_info['title']); ?></div>
            <p><?php echo esc_html($site_info['url']); ?></p>
            <small>Generado el <?php echo date('d/m/Y H:i', strtotime($generated_at)); ?></small>
        </div>
        
        <div class="report-body">
            
            <!-- Puntuación General de Seguridad -->
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">🎯</div>
                    <h2 class="section-title">Puntuación General</h2>
                </div>
                
                <div class="security-overview">
                    <div class="security-score">
                        <div class="score-circle score-<?php echo $this->get_score_class($data['overall_score']['score'] ?? 0); ?>">
                            <?php echo $data['overall_score']['score'] ?? 0; ?>
                        </div>
                        <div class="score-label">Puntuación de Seguridad</div>
                        <div class="score-grade"><?php echo $data['overall_score']['grade'] ?? 'N/A'; ?></div>
                    </div>
                    
                    <div class="security-summary">
                        <div class="summary-item">
                            <span class="summary-label">Estado SSL</span>
                            <span class="summary-value status-<?php echo ($data['ssl_status']['valid'] ?? false) ? 'secure' : 'danger'; ?>">
                                <?php echo ($data['ssl_status']['valid'] ?? false) ? '✅ Válido' : '❌ Problema'; ?>
                            </span>
                        </div>
                        
                        <div class="summary-item">
                            <span class="summary-label">Vulnerabilidades</span>
                            <span class="summary-value status-<?php echo empty($data['vulnerabilities']) ? 'secure' : 'warning'; ?>">
                                <?php echo count($data['vulnerabilities'] ?? array()); ?> detectadas
                            </span>
                        </div>
                        
                        <div class="summary-item">
                            <span class="summary-label">Última actualización</span>
                            <span class="summary-value status-secure">
                                <?php echo date('d/m/Y', strtotime($data['last_update'] ?? 'now')); ?>
                            </span>
                        </div>
                        
                        <div class="summary-item">
                            <span class="summary-label">Plugins activos</span>
                            <span class="summary-value status-secure">
                                <?php echo $data['active_plugins'] ?? 0; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Estado SSL -->
            <?php if (isset($data['ssl_status'])): ?>
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">🔒</div>
                    <h2 class="section-title">Certificado SSL</h2>
                </div>
                
                <div class="ssl-status <?php echo ($data['ssl_status']['valid'] ?? false) ? 'ssl-valid' : 'ssl-invalid'; ?>">
                    <h4>
                        <?php if ($data['ssl_status']['valid'] ?? false): ?>
                            ✅ Certificado SSL válido y activo
                        <?php else: ?>
                            ❌ Problema con el certificado SSL
                        <?php endif; ?>
                    </h4>
                    
                    <p><?php echo esc_html($data['ssl_status']['message'] ?? 'Estado del SSL verificado'); ?></p>
                    
                    <?php if (isset($data['ssl_status']['details'])): ?>
                    <div class="ssl-details">
                        <div class="ssl-detail">
                            <div class="ssl-detail-value"><?php echo esc_html($data['ssl_status']['details']['issuer'] ?? 'N/A'); ?></div>
                            <div class="ssl-detail-label">Emisor</div>
                        </div>
                        
                        <div class="ssl-detail">
                            <div class="ssl-detail-value"><?php echo date('d/m/Y', strtotime($data['ssl_status']['details']['expires_at'] ?? 'now')); ?></div>
                            <div class="ssl-detail-label">Expira</div>
                        </div>
                        
                        <div class="ssl-detail">
                            <div class="ssl-detail-value"><?php echo $data['ssl_status']['details']['days_until_expiry'] ?? 'N/A'; ?></div>
                            <div class="ssl-detail-label">Días restantes</div>
                        </div>
                        
                        <div class="ssl-detail">
                            <div class="ssl-detail-value"><?php echo esc_html($data['ssl_status']['details']['cipher'] ?? 'N/A'); ?></div>
                            <div class="ssl-detail-label">Cifrado</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Verificaciones de Seguridad -->
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">✅</div>
                    <h2 class="section-title">Verificaciones de Seguridad</h2>
                </div>
                
                <div class="security-checks">
                    <div class="check-item <?php echo ($data['checks']['wp_version'] ?? false) ? 'check-passed' : 'check-failed'; ?>">
                        <div class="check-icon"><?php echo ($data['checks']['wp_version'] ?? false) ? '✅' : '❌'; ?></div>
                        <div class="check-title">WordPress Actualizado</div>
                        <div class="check-description">Versión de WordPress es la más reciente</div>
                    </div>
                    
                    <div class="check-item <?php echo ($data['checks']['admin_user'] ?? false) ? 'check-passed' : 'check-failed'; ?>">
                        <div class="check-icon"><?php echo ($data['checks']['admin_user'] ?? false) ? '✅' : '❌'; ?></div>
                        <div class="check-title">Usuario Admin</div>
                        <div class="check-description">No existe usuario 'admin' por defecto</div>
                    </div>
                    
                    <div class="check-item <?php echo ($data['checks']['login_protection'] ?? false) ? 'check-passed' : 'check-failed'; ?>">
                        <div class="check-icon"><?php echo ($data['checks']['login_protection'] ?? false) ? '✅' : '❌'; ?></div>
                        <div class="check-title">Protección Login</div>
                        <div class="check-description">Página de login protegida contra ataques</div>
                    </div>
                    
                    <div class="check-item <?php echo ($data['checks']['file_permissions'] ?? false) ? 'check-passed' : 'check-failed'; ?>">
                        <div class="check-icon"><?php echo ($data['checks']['file_permissions'] ?? false) ? '✅' : '❌'; ?></div>
                        <div class="check-title">Permisos de Archivos</div>
                        <div class="check-description">Permisos de archivos configurados correctamente</div>
                    </div>
                    
                    <div class="check-item <?php echo ($data['checks']['security_headers'] ?? false) ? 'check-passed' : 'check-failed'; ?>">
                        <div class="check-icon"><?php echo ($data['checks']['security_headers'] ?? false) ? '✅' : '❌'; ?></div>
                        <div class="check-title">Headers de Seguridad</div>
                        <div class="check-description">Headers HTTP de seguridad implementados</div>
                    </div>
                    
                    <div class="check-item <?php echo ($data['checks']['malware_scan'] ?? false) ? 'check-passed' : 'check-failed'; ?>">
                        <div class="check-icon"><?php echo ($data['checks']['malware_scan'] ?? false) ? '✅' : '❌'; ?></div>
                        <div class="check-title">Escaneo Malware</div>
                        <div class="check-description">Sin malware detectado en el sitio</div>
                    </div>
                </div>
            </div>
            
            <!-- Vulnerabilidades -->
            <?php if (isset($data['vulnerabilities']) && !empty($data['vulnerabilities'])): ?>
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">⚠️</div>
                    <h2 class="section-title">Vulnerabilidades Detectadas</h2>
                </div>
                
                <div class="vulnerabilities">
                    <?php foreach ($data['vulnerabilities'] as $vuln): ?>
                    <div class="vulnerability-item vuln-<?php echo strtolower($vuln['severity'] ?? 'medium'); ?>">
                        <div class="vuln-header">
                            <div class="vuln-title"><?php echo esc_html($vuln['title'] ?? 'Vulnerabilidad'); ?></div>
                            <span class="vuln-severity severity-<?php echo strtolower($vuln['severity'] ?? 'medium'); ?>">
                                <?php echo esc_html($vuln['severity'] ?? 'Medium'); ?>
                            </span>
                        </div>
                        
                        <div class="vuln-description">
                            <?php echo esc_html($vuln['description'] ?? 'Descripción no disponible'); ?>
                        </div>
                        
                        <?php if (isset($vuln['affected_component'])): ?>
                        <p><strong>Componente afectado:</strong> <?php echo esc_html($vuln['affected_component']); ?></p>
                        <?php endif; ?>
                        
                        <?php if (isset($vuln['solution'])): ?>
                        <div class="vuln-solution">
                            <h5>💡 Solución recomendada:</h5>
                            <p><?php echo esc_html($vuln['solution']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Historial de Seguridad -->
            <?php if (isset($data['security_events']) && !empty($data['security_events'])): ?>
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">📊</div>
                    <h2 class="section-title">Historial de Eventos</h2>
                </div>
                
                <div class="security-timeline">
                    <?php foreach (array_slice($data['security_events'], 0, 10) as $event): ?>
                    <div class="timeline-item">
                        <div class="timeline-content">
                            <div class="timeline-date"><?php echo date('d/m/Y H:i', strtotime($event['timestamp'] ?? 'now')); ?></div>
                            <div class="timeline-title"><?php echo esc_html($event['title'] ?? 'Evento de seguridad'); ?></div>
                            <div class="timeline-description"><?php echo esc_html($event['description'] ?? ''); ?></div>
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
                    <h2 class="section-title">Recomendaciones de Seguridad</h2>
                </div>
                
                <div class="recommendations">
                    <h4>Acciones recomendadas para mejorar la seguridad</h4>
                    
                    <?php
                    $recommendations = array(
                        array(
                            'priority' => 1,
                            'title' => 'Actualizar plugins y temas',
                            'description' => 'Mantener todos los plugins y temas actualizados a sus últimas versiones.'
                        ),
                        array(
                            'priority' => 1,
                            'title' => 'Implementar autenticación de dos factores',
                            'description' => 'Agregar una capa extra de seguridad para el acceso administrativo.'
                        ),
                        array(
                            'priority' => 2,
                            'title' => 'Configurar backups automáticos',
                            'description' => 'Establecer un sistema de backups regulares y automáticos.'
                        ),
                        array(
                            'priority' => 2,
                            'title' => 'Firewall de aplicación web',
                            'description' => 'Implementar un WAF para proteger contra ataques comunes.'
                        ),
                        array(
                            'priority' => 3,
                            'title' => 'Monitorización de archivos',
                            'description' => 'Configurar alertas para cambios no autorizados en archivos críticos.'
                        )
                    );
                    
                    foreach ($recommendations as $rec): ?>
                    <div class="recommendation-item">
                        <div class="rec-priority priority-<?php echo $rec['priority']; ?>"><?php echo $rec['priority']; ?></div>
                        <div>
                            <strong><?php echo esc_html($rec['title']); ?></strong>
                            <p><?php echo esc_html($rec['description']); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php
/**
 * Funciones auxiliares para el template de seguridad
 */
function get_score_class($score) {
    if ($score >= 80) return 'excellent';
    if ($score >= 60) return 'good';
    return 'poor';
}
?>
