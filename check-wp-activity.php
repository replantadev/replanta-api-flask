<?php
/**
 * Script para revisar actividad reciente en WordPress
 * Subir a replanta.net y ejecutar vía navegador
 */

// Autenticación básica (cambiar estas credenciales)
define('CHECK_PASSWORD', 'tu_password_seguro_aqui_' . date('Ymd'));

if (!isset($_GET['pass']) || $_GET['pass'] !== CHECK_PASSWORD) {
    die('Acceso denegado');
}

// Cargar WordPress
require_once(__DIR__ . '/wp-load.php');

if (!current_user_can('manage_options')) {
    die('Requiere permisos de administrador');
}

echo '<pre>';
echo "=== AUDITORÍA DE SEGURIDAD REPLANTA.NET ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Listar usuarios admin
echo "--- USUARIOS ADMINISTRADORES ---\n";
$admins = get_users(array('role' => 'administrator'));
foreach ($admins as $admin) {
    $last_login = get_user_meta($admin->ID, 'last_login', true);
    echo sprintf(
        "ID: %d | User: %s | Email: %s | Registrado: %s | Último login: %s\n",
        $admin->ID,
        $admin->user_login,
        $admin->user_email,
        $admin->user_registered,
        $last_login ? $last_login : 'N/A'
    );
}

// 2. Plugins instalados recientemente (últimos 7 días)
echo "\n--- PLUGINS (Ordenados por fecha) ---\n";
$all_plugins = get_plugins();
$plugin_data = array();

foreach ($all_plugins as $plugin_file => $plugin_info) {
    $plugin_path = WP_PLUGIN_DIR . '/' . dirname($plugin_file);
    $modified = @filemtime($plugin_path);
    
    $plugin_data[] = array(
        'name' => $plugin_info['Name'],
        'version' => $plugin_info['Version'],
        'active' => is_plugin_active($plugin_file),
        'modified' => $modified,
        'file' => $plugin_file
    );
}

// Ordenar por fecha de modificación
usort($plugin_data, function($a, $b) {
    return $b['modified'] - $a['modified'];
});

foreach ($plugin_data as $p) {
    $age_days = round((time() - $p['modified']) / DAY_IN_SECONDS);
    $status = $p['active'] ? '[ACTIVO]' : '[Inactivo]';
    $recent = ($age_days <= 7) ? '🔴 RECIENTE' : '';
    
    echo sprintf(
        "%s %s - %s (v%s) - Modificado hace %d días %s\n",
        $status,
        $p['name'],
        basename(dirname($p['file'])),
        $p['version'],
        $age_days,
        $recent
    );
}

// 3. Buscar Koko Analytics específicamente
echo "\n--- KOKO ANALYTICS ---\n";
$koko_active = is_plugin_active('koko-analytics/koko-analytics.php');
if ($koko_active || file_exists(WP_PLUGIN_DIR . '/koko-analytics')) {
    $koko_dir = WP_PLUGIN_DIR . '/koko-analytics';
    $install_date = @filemtime($koko_dir);
    
    echo "Estado: " . ($koko_active ? 'ACTIVO' : 'Instalado pero inactivo') . "\n";
    echo "Instalado: " . date('Y-m-d H:i:s', $install_date) . " (" . human_time_diff($install_date) . " ago)\n";
    
    // Opciones de Koko
    $koko_settings = get_option('koko_analytics_settings');
    if ($koko_settings) {
        echo "Configuración encontrada: " . print_r($koko_settings, true) . "\n";
    }
} else {
    echo "Koko Analytics NO está instalado\n";
}

// 4. Themes activos
echo "\n--- THEMES ---\n";
$current_theme = wp_get_theme();
echo "Actual: " . $current_theme->get('Name') . " v" . $current_theme->get('Version') . "\n";

// 5. Actividad reciente (si hay plugins de log)
echo "\n--- LOGS DE ACTIVIDAD (si disponible) ---\n";
if (function_exists('get_simple_history_entries')) {
    echo "Simple History detectado\n";
} else {
    echo "No hay plugin de logs activo\n";
}

// 6. Archivos modificados recientemente en wp-content
echo "\n--- ARCHIVOS MODIFICADOS ÚLTIMOS 2 DÍAS ---\n";
$recent_files = array();
$dirs_to_check = array(
    WP_CONTENT_DIR . '/plugins',
    WP_CONTENT_DIR . '/themes',
    WP_CONTENT_DIR . '/uploads'
);

foreach ($dirs_to_check as $base_dir) {
    if (!is_dir($base_dir)) continue;
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $mtime = $file->getMTime();
            if ($mtime > (time() - (2 * DAY_IN_SECONDS))) {
                $recent_files[] = array(
                    'path' => str_replace(ABSPATH, '', $file->getPathname()),
                    'modified' => $mtime
                );
            }
        }
    }
}

// Limitar a 50 archivos más recientes
usort($recent_files, function($a, $b) {
    return $b['modified'] - $a['modified'];
});

foreach (array_slice($recent_files, 0, 50) as $file) {
    echo date('Y-m-d H:i:s', $file['modified']) . " - " . $file['path'] . "\n";
}

echo "\n=== FIN DE AUDITORÍA ===\n";
echo "</pre>";

// Autodestruir después de 1 hora (medida de seguridad)
$script_age = time() - filemtime(__FILE__);
if ($script_age > 3600) {
    @unlink(__FILE__);
    echo "\n<strong>Script autodestruido después de 1 hora.</strong>";
}
