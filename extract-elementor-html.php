<?php
/**
 * Extractor de widgets HTML de Elementor
 * Subir a replanta.net y ejecutar vía navegador
 */

// Autenticación básica
define('EXTRACT_PASSWORD', 'extract_' . date('Ymd') . '_replanta');

if (!isset($_GET['pass']) || $_GET['pass'] !== EXTRACT_PASSWORD) {
    die('Acceso denegado. Usa: ?pass=' . EXTRACT_PASSWORD);
}

// Cargar WordPress
require_once(__DIR__ . '/wp-load.php');

if (!current_user_can('manage_options')) {
    die('Requiere permisos de administrador');
}

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Extractor de HTML - Elementor</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        .widget { background: #2d2d30; border: 2px solid #007acc; margin: 20px 0; padding: 15px; border-radius: 5px; }
        .widget-header { background: #007acc; color: white; padding: 10px; margin: -15px -15px 15px -15px; font-weight: bold; }
        .widget-meta { color: #858585; font-size: 12px; margin-bottom: 10px; }
        .widget-content { background: #1e1e1e; padding: 15px; border-radius: 3px; overflow-x: auto; }
        pre { margin: 0; white-space: pre-wrap; word-wrap: break-word; }
        .download-btn { background: #0e639c; color: white; padding: 8px 15px; text-decoration: none; border-radius: 3px; display: inline-block; margin: 5px 0; }
        .download-btn:hover { background: #1177bb; }
        .stats { background: #2d2d30; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <h1>🔍 Extractor de HTML Custom - Elementor</h1>
    
<?php

// Buscar posts/pages con Elementor
global $wpdb;

// Buscar en postmeta widgets HTML personalizados
$query = "
    SELECT p.ID, p.post_title, p.post_type, p.post_status, p.post_modified,
           pm.meta_value as elementor_data
    FROM {$wpdb->posts} p
    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
    WHERE pm.meta_key = '_elementor_data'
    AND pm.meta_value LIKE '%html%'
    AND p.post_status IN ('publish', 'draft', 'private')
    ORDER BY p.post_modified DESC
";

$results = $wpdb->get_results($query);

if (empty($results)) {
    echo '<div class="stats">❌ No se encontraron páginas con Elementor con HTML custom</div>';
} else {
    echo '<div class="stats">✅ Encontradas <strong>' . count($results) . '</strong> páginas con Elementor</div>';
    
    $html_widgets_found = 0;
    
    foreach ($results as $page) {
        $elementor_data = json_decode($page->elementor_data, true);
        
        if (!$elementor_data) {
            continue;
        }
        
        // Función recursiva para buscar widgets HTML
        $html_widgets = [];
        
        function extract_html_widgets($elements, &$html_widgets) {
            if (!is_array($elements)) {
                return;
            }
            
            foreach ($elements as $element) {
                // Buscar widgets HTML, code, html, text editor
                if (isset($element['widgetType']) && 
                    in_array($element['widgetType'], ['html', 'code', 'text-editor', 'shortcode'])) {
                    
                    $html_content = '';
                    
                    // Extraer contenido según el tipo
                    if (isset($element['settings']['html'])) {
                        $html_content = $element['settings']['html'];
                    } elseif (isset($element['settings']['code'])) {
                        $html_content = $element['settings']['code'];
                    } elseif (isset($element['settings']['editor'])) {
                        $html_content = $element['settings']['editor'];
                    } elseif (isset($element['settings']['shortcode'])) {
                        $html_content = $element['settings']['shortcode'];
                    }
                    
                    if (!empty($html_content) && strlen($html_content) > 50) {
                        $html_widgets[] = [
                            'type' => $element['widgetType'],
                            'id' => $element['id'] ?? 'unknown',
                            'content' => $html_content
                        ];
                    }
                }
                
                // Recursivo en elementos anidados
                if (isset($element['elements'])) {
                    extract_html_widgets($element['elements'], $html_widgets);
                }
            }
        }
        
        extract_html_widgets($elementor_data, $html_widgets);
        
        if (!empty($html_widgets)) {
            $html_widgets_found += count($html_widgets);
            
            echo '<div class="widget">';
            echo '<div class="widget-header">';
            echo '📄 ' . esc_html($page->post_title) . ' (' . esc_html($page->post_type) . ')';
            echo '</div>';
            echo '<div class="widget-meta">';
            echo 'ID: ' . $page->ID . ' | ';
            echo 'Estado: ' . $page->post_status . ' | ';
            echo 'Modificado: ' . $page->post_modified . ' | ';
            echo 'Widgets HTML: ' . count($html_widgets);
            echo ' | <a href="' . get_edit_post_link($page->ID) . '" target="_blank">Editar en WP</a>';
            echo '</div>';
            
            foreach ($html_widgets as $index => $widget) {
                echo '<h3>Widget #' . ($index + 1) . ' - Tipo: ' . esc_html($widget['type']) . ' (ID: ' . esc_html($widget['id']) . ')</h3>';
                
                // Botón de descarga
                $filename = sanitize_file_name($page->post_title . '_widget_' . ($index + 1) . '.html');
                $download_url = admin_url('admin-ajax.php?action=download_html_widget&post_id=' . $page->ID . '&widget_index=' . $index);
                
                echo '<div class="widget-content">';
                echo '<pre>' . esc_html($widget['content']) . '</pre>';
                echo '</div>';
                
                // Crear archivo descargable
                echo '<a href="data:text/html;charset=utf-8,' . rawurlencode($widget['content']) . '" download="' . $filename . '" class="download-btn">⬇️ Descargar ' . $filename . '</a>';
            }
            
            echo '</div>';
        }
    }
    
    echo '<div class="stats">📊 Total widgets HTML encontrados: <strong>' . $html_widgets_found . '</strong></div>';
}

// Buscar también en options (por si hay widgets guardados en settings)
$elementor_options = $wpdb->get_results("
    SELECT option_name, option_value 
    FROM {$wpdb->options} 
    WHERE option_name LIKE '%elementor%' 
    AND option_value LIKE '%html%'
    LIMIT 20
");

if (!empty($elementor_options)) {
    echo '<h2>⚙️ Configuraciones de Elementor con HTML</h2>';
    foreach ($elementor_options as $option) {
        if (strlen($option->option_value) > 100) {
            echo '<div class="widget">';
            echo '<div class="widget-header">' . esc_html($option->option_name) . '</div>';
            echo '<div class="widget-content">';
            echo '<pre>' . esc_html(substr($option->option_value, 0, 500)) . '...</pre>';
            echo '</div>';
            echo '</div>';
        }
    }
}

?>

<hr>
<p><small>Script autodestruible - Se eliminará automáticamente en 1 hora</small></p>

</body>
</html>

<?php

// Autodestruir después de 1 hora
$script_age = time() - filemtime(__FILE__);
if ($script_age > 3600) {
    @unlink(__FILE__);
}
