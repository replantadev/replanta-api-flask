<?php
/**
 * Template para reporte de rendimiento
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
    <title>Reporte de Rendimiento - <?php echo esc_html($site_info['title']); ?></title>
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
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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
        
        .speed-score {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin: 40px 0;
        }
        
        .score-gauge {
            text-align: center;
        }
        
        .score-circle {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5em;
            font-weight: bold;
            color: white;
            margin: 0 auto 15px auto;
            position: relative;
        }
        
        .score-poor { background: #e74c3c; }
        .score-average { background: #f39c12; }
        .score-good { background: #27ae60; }
        
        .gauge-label {
            font-size: 1.2em;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .core-vitals {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 30px;
            margin: 40px 0;
        }
        
        .vitals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        
        .vital-item {
            text-align: center;
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .vital-value {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .vital-good { color: #27ae60; }
        .vital-needs-improvement { color: #f39c12; }
        .vital-poor { color: #e74c3c; }
        
        .vital-label {
            font-size: 1em;
            color: #7f8c8d;
            margin-bottom: 5px;
        }
        
        .vital-description {
            font-size: 0.9em;
            color: #95a5a6;
            margin-top: 10px;
        }
        
        .opportunities {
            margin: 40px 0;
        }
        
        .opportunity-item {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .opportunity-info {
            flex: 1;
        }
        
        .opportunity-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .opportunity-description {
            color: #7f8c8d;
            font-size: 0.9em;
            margin-bottom: 10px;
        }
        
        .opportunity-impact {
            background: #e8f5e8;
            color: #27ae60;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: 600;
        }
        
        .opportunity-savings {
            text-align: right;
            margin-left: 20px;
        }
        
        .savings-value {
            font-size: 1.5em;
            font-weight: bold;
            color: #27ae60;
        }
        
        .savings-label {
            font-size: 0.8em;
            color: #7f8c8d;
        }
        
        .diagnostics {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin: 40px 0;
        }
        
        .diagnostic-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #ffeaa7;
        }
        
        .diagnostic-item:last-child {
            border-bottom: none;
        }
        
        .diagnostic-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-weight: bold;
        }
        
        .icon-warning { background: #f39c12; }
        .icon-error { background: #e74c3c; }
        .icon-info { background: #3498db; }
        
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
            background: #f093fb;
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
        
        .chart-container {
            background: white;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 30px;
            margin: 30px 0;
            text-align: center;
        }
        
        .historical-trend {
            margin: 40px 0;
        }
        
        .trend-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .trend-item {
            text-align: center;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
        
        .trend-value {
            font-size: 1.8em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .trend-up { color: #27ae60; }
        .trend-down { color: #e74c3c; }
        .trend-stable { color: #3498db; }
        
        .trend-label {
            color: #7f8c8d;
            font-size: 0.9em;
        }
        
        @media (max-width: 768px) {
            .speed-score {
                flex-direction: column;
                gap: 20px;
            }
            
            .score-circle {
                width: 120px;
                height: 120px;
                font-size: 2em;
            }
            
            .opportunity-item {
                flex-direction: column;
                text-align: center;
            }
            
            .opportunity-savings {
                margin-left: 0;
                margin-top: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <!-- Header del Reporte -->
        <div class="report-header">
            <h1>📈 Reporte de Rendimiento</h1>
            <div class="subtitle"><?php echo esc_html($site_info['title']); ?></div>
            <p><?php echo esc_html($site_info['url']); ?></p>
            <small>Generado el <?php echo date('d/m/Y H:i', strtotime($generated_at)); ?></small>
        </div>
        
        <div class="report-body">
            
            <!-- Puntuaciones PageSpeed -->
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">⚡</div>
                    <h2 class="section-title">Puntuaciones PageSpeed Insights</h2>
                </div>
                
                <div class="speed-score">
                    <div class="score-gauge">
                        <div class="score-circle score-<?php echo $this->get_score_class($data['mobile_score'] ?? 0); ?>">
                            <?php echo $data['mobile_score'] ?? 'N/A'; ?>
                        </div>
                        <div class="gauge-label">📱 Móvil</div>
                    </div>
                    
                    <div class="score-gauge">
                        <div class="score-circle score-<?php echo $this->get_score_class($data['desktop_score'] ?? 0); ?>">
                            <?php echo $data['desktop_score'] ?? 'N/A'; ?>
                        </div>
                        <div class="gauge-label">🖥️ Desktop</div>
                    </div>
                </div>
            </div>
            
            <!-- Core Web Vitals -->
            <?php if (isset($data['core_web_vitals'])): ?>
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">🎯</div>
                    <h2 class="section-title">Core Web Vitals</h2>
                </div>
                
                <div class="core-vitals">
                    <p>Los Core Web Vitals son métricas esenciales que miden la experiencia real del usuario.</p>
                    
                    <div class="vitals-grid">
                        <div class="vital-item">
                            <div class="vital-value vital-<?php echo $this->get_vital_status($data['core_web_vitals']['lcp'] ?? 0, 'lcp'); ?>">
                                <?php echo number_format($data['core_web_vitals']['lcp'] ?? 0, 2); ?>s
                            </div>
                            <div class="vital-label">LCP</div>
                            <div class="vital-description">Largest Contentful Paint</div>
                        </div>
                        
                        <div class="vital-item">
                            <div class="vital-value vital-<?php echo $this->get_vital_status($data['core_web_vitals']['fid'] ?? 0, 'fid'); ?>">
                                <?php echo number_format($data['core_web_vitals']['fid'] ?? 0, 0); ?>ms
                            </div>
                            <div class="vital-label">FID</div>
                            <div class="vital-description">First Input Delay</div>
                        </div>
                        
                        <div class="vital-item">
                            <div class="vital-value vital-<?php echo $this->get_vital_status($data['core_web_vitals']['cls'] ?? 0, 'cls'); ?>">
                                <?php echo number_format($data['core_web_vitals']['cls'] ?? 0, 3); ?>
                            </div>
                            <div class="vital-label">CLS</div>
                            <div class="vital-description">Cumulative Layout Shift</div>
                        </div>
                        
                        <div class="vital-item">
                            <div class="vital-value vital-<?php echo $this->get_vital_status($data['core_web_vitals']['fcp'] ?? 0, 'fcp'); ?>">
                                <?php echo number_format($data['core_web_vitals']['fcp'] ?? 0, 2); ?>s
                            </div>
                            <div class="vital-label">FCP</div>
                            <div class="vital-description">First Contentful Paint</div>
                        </div>
                        
                        <div class="vital-item">
                            <div class="vital-value vital-<?php echo $this->get_vital_status($data['core_web_vitals']['ttfb'] ?? 0, 'ttfb'); ?>">
                                <?php echo number_format($data['core_web_vitals']['ttfb'] ?? 0, 0); ?>ms
                            </div>
                            <div class="vital-label">TTFB</div>
                            <div class="vital-description">Time to First Byte</div>
                        </div>
                        
                        <div class="vital-item">
                            <div class="vital-value vital-<?php echo $this->get_vital_status($data['core_web_vitals']['tbt'] ?? 0, 'tbt'); ?>">
                                <?php echo number_format($data['core_web_vitals']['tbt'] ?? 0, 0); ?>ms
                            </div>
                            <div class="vital-label">TBT</div>
                            <div class="vital-description">Total Blocking Time</div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Oportunidades de Mejora -->
            <?php if (isset($data['opportunities']) && !empty($data['opportunities'])): ?>
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">💡</div>
                    <h2 class="section-title">Oportunidades de Mejora</h2>
                </div>
                
                <div class="opportunities">
                    <p>Estas optimizaciones pueden mejorar significativamente el rendimiento de tu sitio web.</p>
                    
                    <?php foreach ($data['opportunities'] as $opportunity): ?>
                    <div class="opportunity-item">
                        <div class="opportunity-info">
                            <div class="opportunity-title"><?php echo esc_html($opportunity['title'] ?? ''); ?></div>
                            <div class="opportunity-description"><?php echo esc_html($opportunity['description'] ?? ''); ?></div>
                            <span class="opportunity-impact">
                                Impacto: <?php echo ucfirst($opportunity['impact'] ?? 'medio'); ?>
                            </span>
                        </div>
                        <?php if (isset($opportunity['potential_savings'])): ?>
                        <div class="opportunity-savings">
                            <div class="savings-value"><?php echo number_format($opportunity['potential_savings'], 2); ?>s</div>
                            <div class="savings-label">Ahorro potencial</div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Diagnósticos -->
            <?php if (isset($data['diagnostics']) && !empty($data['diagnostics'])): ?>
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">🔍</div>
                    <h2 class="section-title">Diagnósticos</h2>
                </div>
                
                <div class="diagnostics">
                    <h4>Información adicional sobre el rendimiento</h4>
                    
                    <?php foreach ($data['diagnostics'] as $diagnostic): ?>
                    <div class="diagnostic-item">
                        <div class="diagnostic-icon icon-<?php echo $diagnostic['type'] ?? 'info'; ?>">
                            <?php echo $this->get_diagnostic_icon($diagnostic['type'] ?? 'info'); ?>
                        </div>
                        <div>
                            <strong><?php echo esc_html($diagnostic['title'] ?? ''); ?></strong>
                            <p><?php echo esc_html($diagnostic['description'] ?? ''); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Tendencia Histórica -->
            <?php if (isset($data['historical_data']) && $config['include_charts']): ?>
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">📊</div>
                    <h2 class="section-title">Tendencia Histórica</h2>
                </div>
                
                <div class="historical-trend">
                    <div class="trend-summary">
                        <div class="trend-item">
                            <div class="trend-value trend-<?php echo $data['trends']['mobile_trend'] ?? 'stable'; ?>">
                                <?php echo $data['historical_data']['avg_mobile_score'] ?? 'N/A'; ?>
                            </div>
                            <div class="trend-label">Promedio Móvil</div>
                        </div>
                        
                        <div class="trend-item">
                            <div class="trend-value trend-<?php echo $data['trends']['desktop_trend'] ?? 'stable'; ?>">
                                <?php echo $data['historical_data']['avg_desktop_score'] ?? 'N/A'; ?>
                            </div>
                            <div class="trend-label">Promedio Desktop</div>
                        </div>
                        
                        <div class="trend-item">
                            <div class="trend-value trend-<?php echo $data['trends']['lcp_trend'] ?? 'stable'; ?>">
                                <?php echo number_format($data['historical_data']['avg_lcp'] ?? 0, 2); ?>s
                            </div>
                            <div class="trend-label">LCP Promedio</div>
                        </div>
                        
                        <div class="trend-item">
                            <div class="trend-value">
                                <?php echo $data['historical_data']['total_tests'] ?? 0; ?>
                            </div>
                            <div class="trend-label">Tests Realizados</div>
                        </div>
                    </div>
                    
                    <div class="chart-container">
                        <h4>Evolución del Rendimiento</h4>
                        <p><em>Gráfico de evolución de las puntuaciones PageSpeed en los últimos <?php echo $this->format_period($config['period']); ?></em></p>
                        <!-- Aquí se insertaría el gráfico con Chart.js o similar -->
                        <div style="height: 300px; display: flex; align-items: center; justify-content: center; background: #f8f9fa; border-radius: 4px;">
                            <span style="color: #7f8c8d;">Gráfico de tendencias disponible en la versión completa</span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Recomendaciones Específicas -->
            <?php if ($config['include_recommendations']): ?>
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">🎯</div>
                    <h2 class="section-title">Recomendaciones de Acción</h2>
                </div>
                
                <div style="background: #e8f5e8; border-left: 4px solid #27ae60; padding: 20px; border-radius: 0 8px 8px 0;">
                    <h4 style="color: #27ae60; margin-top: 0;">Próximos pasos recomendados:</h4>
                    
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php if (($data['mobile_score'] ?? 0) < 50): ?>
                        <li>🔴 <strong>Prioridad Alta:</strong> Optimizar imágenes y recursos para mejorar el rendimiento móvil</li>
                        <?php endif; ?>
                        
                        <?php if (($data['core_web_vitals']['lcp'] ?? 0) > 2.5): ?>
                        <li>🟡 <strong>Prioridad Media:</strong> Mejorar el LCP optimizando el contenido principal</li>
                        <?php endif; ?>
                        
                        <?php if (($data['core_web_vitals']['cls'] ?? 0) > 0.1): ?>
                        <li>🟡 <strong>Prioridad Media:</strong> Reducir cambios de diseño inesperados (CLS)</li>
                        <?php endif; ?>
                        
                        <li>🔵 <strong>Mantenimiento:</strong> Continuar monitoreando el rendimiento regularmente</li>
                        <li>🔵 <strong>Optimización:</strong> Considerar implementar un CDN para mejorar tiempos de carga</li>
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
 * Funciones auxiliares para el template de rendimiento
 */
function get_score_class($score) {
    if ($score >= 90) return 'good';
    if ($score >= 50) return 'average';
    return 'poor';
}

function get_vital_status($value, $metric) {
    switch($metric) {
        case 'lcp':
            if ($value <= 2.5) return 'good';
            if ($value <= 4.0) return 'needs-improvement';
            return 'poor';
        case 'fid':
            if ($value <= 100) return 'good';
            if ($value <= 300) return 'needs-improvement';
            return 'poor';
        case 'cls':
            if ($value <= 0.1) return 'good';
            if ($value <= 0.25) return 'needs-improvement';
            return 'poor';
        case 'fcp':
            if ($value <= 1.8) return 'good';
            if ($value <= 3.0) return 'needs-improvement';
            return 'poor';
        case 'ttfb':
            if ($value <= 600) return 'good';
            if ($value <= 1200) return 'needs-improvement';
            return 'poor';
        case 'tbt':
            if ($value <= 200) return 'good';
            if ($value <= 600) return 'needs-improvement';
            return 'poor';
        default:
            return 'good';
    }
}

function get_diagnostic_icon($type) {
    switch($type) {
        case 'warning': return '⚠️';
        case 'error': return '❌';
        case 'info': return 'ℹ️';
        default: return 'ℹ️';
    }
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
