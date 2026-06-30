<?php
/**
 * Template para reporte personalizado
 * Variables disponibles: $site_info, $data, $config, $generated_at, $custom_sections
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
    <title><?php echo esc_html($config['title'] ?? 'Reporte Personalizado'); ?> - <?php echo esc_html($site_info['title']); ?></title>
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
            background: <?php echo $config['header_color'] ?? 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'; ?>;
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
        
        .executive-summary {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 30px;
            margin: 40px 0;
            border-left: 4px solid #667eea;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .summary-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .summary-value {
            font-size: 2em;
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
            background: <?php echo $config['accent_color'] ?? '#667eea'; ?>;
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
        
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .metric-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            border-left: 4px solid <?php echo $config['accent_color'] ?? '#667eea'; ?>;
        }
        
        .metric-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .metric-title {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .metric-trend {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
        }
        
        .trend-up {
            background: #e8f5e8;
            color: #27ae60;
        }
        
        .trend-down {
            background: #f8d7da;
            color: #e74c3c;
        }
        
        .trend-stable {
            background: #e8f4fd;
            color: #3498db;
        }
        
        .metric-value {
            font-size: 2.2em;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .metric-description {
            color: #7f8c8d;
            font-size: 0.9em;
        }
        
        .chart-container {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 30px;
            margin: 30px 0;
            text-align: center;
        }
        
        .chart-placeholder {
            height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            border-radius: 4px;
            color: #7f8c8d;
            font-style: italic;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .data-table th {
            background: <?php echo $config['accent_color'] ?? '#667eea'; ?>;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        .data-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        .status-indicator {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-success {
            background: #e8f5e8;
            color: #27ae60;
        }
        
        .status-warning {
            background: #fff3cd;
            color: #f39c12;
        }
        
        .status-error {
            background: #f8d7da;
            color: #e74c3c;
        }
        
        .status-info {
            background: #e8f4fd;
            color: #3498db;
        }
        
        .insights-panel {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 20%, #4facfe 100%);
            color: white;
            border-radius: 8px;
            padding: 30px;
            margin: 40px 0;
        }
        
        .insights-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .insight-item {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 20px;
            backdrop-filter: blur(10px);
        }
        
        .insight-title {
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 1.1em;
        }
        
        .insight-description {
            opacity: 0.9;
            font-size: 0.9em;
            line-height: 1.5;
        }
        
        .comparison-section {
            margin: 40px 0;
        }
        
        .comparison-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 20px;
        }
        
        .comparison-item {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 25px;
            position: relative;
        }
        
        .comparison-header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .comparison-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .comparison-subtitle {
            color: #7f8c8d;
            font-size: 0.9em;
        }
        
        .comparison-metrics {
            display: flex;
            justify-content: space-around;
            text-align: center;
        }
        
        .comparison-metric {
            flex: 1;
        }
        
        .comparison-value {
            font-size: 1.8em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .comparison-label {
            color: #7f8c8d;
            font-size: 0.8em;
        }
        
        .recommendations-custom {
            background: #e8f5e8;
            border-left: 4px solid #27ae60;
            padding: 25px;
            border-radius: 0 8px 8px 0;
            margin: 40px 0;
        }
        
        .recommendations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .recommendation-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            border-left: 4px solid #27ae60;
        }
        
        .rec-priority {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .priority-high {
            background: #e74c3c;
            color: white;
        }
        
        .priority-medium {
            background: #f39c12;
            color: white;
        }
        
        .priority-low {
            background: #3498db;
            color: white;
        }
        
        .rec-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .rec-description {
            color: #495057;
            font-size: 0.9em;
            line-height: 1.5;
        }
        
        .footer-notes {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 40px;
            font-size: 0.9em;
            color: #7f8c8d;
        }
        
        @media (max-width: 768px) {
            .summary-grid,
            .metrics-grid,
            .insights-grid,
            .comparison-grid,
            .recommendations-grid {
                grid-template-columns: 1fr;
            }
            
            .comparison-metrics {
                flex-direction: column;
                gap: 15px;
            }
            
            .report-header,
            .report-body {
                padding: 20px;
            }
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .report-container {
                box-shadow: none;
            }
            
            .insights-panel {
                background: #f8f9fa !important;
                color: #333 !important;
            }
            
            .insight-item {
                background: white !important;
                border: 1px solid #e9ecef !important;
            }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <!-- Header del Reporte -->
        <div class="report-header">
            <h1><?php echo esc_html($config['title'] ?? 'Reporte Personalizado'); ?></h1>
            <div class="subtitle"><?php echo esc_html($site_info['title']); ?></div>
            <p><?php echo esc_html($site_info['url']); ?></p>
            <small>Generado el <?php echo date('d/m/Y H:i', strtotime($generated_at)); ?></small>
        </div>
        
        <div class="report-body">
            
            <!-- Resumen Ejecutivo -->
            <?php if ($config['include_executive_summary'] ?? true): ?>
            <div class="executive-summary">
                <h3 style="margin-top: 0; color: #2c3e50;">📊 Resumen Ejecutivo</h3>
                <p><?php echo esc_html($config['summary_text'] ?? 'Este reporte personalizado proporciona una vista integral del estado y rendimiento del sitio web.'); ?></p>
                
                <div class="summary-grid">
                    <?php foreach ($data['executive_metrics'] ?? array() as $metric): ?>
                    <div class="summary-card">
                        <div class="summary-value" style="color: <?php echo $metric['color'] ?? '#2c3e50'; ?>">
                            <?php echo esc_html($metric['value'] ?? 'N/A'); ?>
                        </div>
                        <div class="summary-label"><?php echo esc_html($metric['label'] ?? ''); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Secciones Personalizadas -->
            <?php foreach ($custom_sections ?? array() as $section): ?>
            <div class="section">
                <div class="section-header">
                    <div class="section-icon"><?php echo $section['icon'] ?? '📋'; ?></div>
                    <h2 class="section-title"><?php echo esc_html($section['title'] ?? 'Sección'); ?></h2>
                </div>
                
                <?php if (isset($section['description'])): ?>
                <p><?php echo esc_html($section['description']); ?></p>
                <?php endif; ?>
                
                <!-- Métricas de la Sección -->
                <?php if (isset($section['metrics']) && !empty($section['metrics'])): ?>
                <div class="metrics-grid">
                    <?php foreach ($section['metrics'] as $metric): ?>
                    <div class="metric-card">
                        <div class="metric-header">
                            <div class="metric-title"><?php echo esc_html($metric['title'] ?? ''); ?></div>
                            <?php if (isset($metric['trend'])): ?>
                            <div class="metric-trend trend-<?php echo $metric['trend']; ?>">
                                <?php echo $this->get_trend_icon($metric['trend']); ?>
                                <?php echo ucfirst($metric['trend']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="metric-value"><?php echo esc_html($metric['value'] ?? 'N/A'); ?></div>
                        <div class="metric-description"><?php echo esc_html($metric['description'] ?? ''); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- Tabla de Datos -->
                <?php if (isset($section['table_data']) && !empty($section['table_data'])): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <?php foreach ($section['table_headers'] ?? array() as $header): ?>
                            <th><?php echo esc_html($header); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($section['table_data'] as $row): ?>
                        <tr>
                            <?php foreach ($row as $cell): ?>
                            <td>
                                <?php if (isset($cell['type']) && $cell['type'] === 'status'): ?>
                                <span class="status-indicator status-<?php echo $cell['status'] ?? 'info'; ?>">
                                    <?php echo esc_html($cell['value'] ?? ''); ?>
                                </span>
                                <?php else: ?>
                                <?php echo esc_html($cell['value'] ?? $cell ?? ''); ?>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
                
                <!-- Gráfico -->
                <?php if (isset($section['chart']) && $config['include_charts']): ?>
                <div class="chart-container">
                    <h4><?php echo esc_html($section['chart']['title'] ?? 'Gráfico'); ?></h4>
                    <div class="chart-placeholder">
                        <?php echo esc_html($section['chart']['placeholder'] ?? 'Gráfico disponible en la versión completa'); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            
            <!-- Panel de Insights -->
            <?php if (isset($data['insights']) && !empty($data['insights'])): ?>
            <div class="insights-panel">
                <h3 style="margin-top: 0;">🔍 Insights y Análisis</h3>
                <p>Análisis automático basado en los datos recopilados y tendencias identificadas.</p>
                
                <div class="insights-grid">
                    <?php foreach ($data['insights'] as $insight): ?>
                    <div class="insight-item">
                        <div class="insight-title"><?php echo esc_html($insight['title'] ?? ''); ?></div>
                        <div class="insight-description"><?php echo esc_html($insight['description'] ?? ''); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Sección de Comparación -->
            <?php if (isset($data['comparisons']) && !empty($data['comparisons'])): ?>
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">⚖️</div>
                    <h2 class="section-title">Análisis Comparativo</h2>
                </div>
                
                <div class="comparison-section">
                    <div class="comparison-grid">
                        <?php foreach ($data['comparisons'] as $comparison): ?>
                        <div class="comparison-item">
                            <div class="comparison-header">
                                <div class="comparison-title"><?php echo esc_html($comparison['title'] ?? ''); ?></div>
                                <div class="comparison-subtitle"><?php echo esc_html($comparison['subtitle'] ?? ''); ?></div>
                            </div>
                            
                            <div class="comparison-metrics">
                                <?php foreach ($comparison['metrics'] ?? array() as $metric): ?>
                                <div class="comparison-metric">
                                    <div class="comparison-value" style="color: <?php echo $metric['color'] ?? '#2c3e50'; ?>">
                                        <?php echo esc_html($metric['value'] ?? 'N/A'); ?>
                                    </div>
                                    <div class="comparison-label"><?php echo esc_html($metric['label'] ?? ''); ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Recomendaciones Personalizadas -->
            <?php if ($config['include_recommendations'] && isset($data['custom_recommendations'])): ?>
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">💡</div>
                    <h2 class="section-title">Recomendaciones Personalizadas</h2>
                </div>
                
                <div class="recommendations-custom">
                    <h4 style="color: #27ae60; margin-top: 0;">Acciones recomendadas basadas en el análisis</h4>
                    
                    <div class="recommendations-grid">
                        <?php foreach ($data['custom_recommendations'] as $rec): ?>
                        <div class="recommendation-card">
                            <span class="rec-priority priority-<?php echo strtolower($rec['priority'] ?? 'medium'); ?>">
                                <?php echo strtoupper($rec['priority'] ?? 'MEDIUM'); ?>
                            </span>
                            
                            <div class="rec-title"><?php echo esc_html($rec['title'] ?? ''); ?></div>
                            <div class="rec-description"><?php echo esc_html($rec['description'] ?? ''); ?></div>
                            
                            <?php if (isset($rec['expected_impact'])): ?>
                            <div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 4px; font-size: 0.9em;">
                                <strong>Impacto esperado:</strong> <?php echo esc_html($rec['expected_impact']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Notas del Pie -->
            <?php if (isset($config['footer_notes'])): ?>
            <div class="footer-notes">
                <h5 style="margin-top: 0; color: #2c3e50;">📋 Notas Adicionales</h5>
                <p><?php echo esc_html($config['footer_notes']); ?></p>
                
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e9ecef;">
                    <small>
                        <strong>Metodología:</strong> Este reporte se genera automáticamente basado en <?php echo esc_html($config['data_sources'] ?? 'múltiples fuentes de datos'); ?>.
                        Los datos se recopilan durante un período de <?php echo esc_html($this->format_period($config['period'])); ?>.
                    </small>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php
/**
 * Funciones auxiliares para el template personalizado
 */
function get_trend_icon($trend) {
    switch($trend) {
        case 'up': return '📈';
        case 'down': return '📉';
        case 'stable': return '➡️';
        default: return '📊';
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
