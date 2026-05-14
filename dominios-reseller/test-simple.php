<?php
/**
 * Test alternativo - especifica la ruta manualmente si es necesario
 */

// Si el script automático no encuentra wp-load.php, especifica la ruta aquí:
// define('WP_LOAD_PATH', '/ruta/absoluta/a/wp-load.php');

$wp_load_path = defined('WP_LOAD_PATH') ? WP_LOAD_PATH : null;

if (!$wp_load_path) {
    // Intentar rutas comunes automáticamente
    $wp_load_paths = [
        __DIR__ . '/../../../wp-load.php', // wp-content/plugins/dominios-reseller/../../../wp-load.php
        __DIR__ . '/../../wp-load.php',   // wp-content/plugins/dominios-reseller/../../wp-load.php
        __DIR__ . '/../wp-load.php',      // wp-content/plugins/dominios-reseller/../wp-load.php
        '/var/www/html/wp-load.php',      // Ruta común en servidores
        '/home/user/public_html/wp-load.php', // Otra ruta común
    ];

    foreach ($wp_load_paths as $path) {
        if (file_exists($path)) {
            $wp_load_path = $path;
            break;
        }
    }
}

if (!$wp_load_path) {
    echo "❌ ERROR: No se pudo encontrar wp-load.php\n\n";
    echo "Soluciones:\n";
    echo "1. Edita este archivo y define la ruta manualmente:\n";
    echo "   define('WP_LOAD_PATH', '/ruta/a/wp-load.php');\n\n";
    echo "2. O ejecuta desde el directorio correcto\n\n";
    echo "Rutas probadas:\n";
    foreach ($wp_load_paths as $path) {
        echo "   - $path\n";
    }
    exit(1);
}

echo "✅ WordPress encontrado en: $wp_load_path\n";
require_once $wp_load_path;

// Continuar con el test normal...
echo "=== TEST SISTEMA ONBOARDING ASÍNCRONO ===\n\n";

// Incluir clases del plugin
$plugin_files = [
    __DIR__ . '/includes/class-onboarding-db.php',
    __DIR__ . '/includes/class-onboarding-worker.php',
];

foreach ($plugin_files as $file) {
    if (file_exists($file)) {
        require_once $file;
        echo "✅ Cargado: " . basename($file) . "\n";
    } else {
        echo "❌ No encontrado: " . basename($file) . "\n";
    }
}

echo "\n";

// Resto del test igual que el original...
echo "1. VERIFICANDO TABLAS...\n";
global $wpdb;
$tables = [
    'dominios_reseller_cf_onboarding',
    'dominios_reseller_cf_runs',
    'dominios_reseller_cf_presets',
    'dominios_reseller_cf_onboarding_logs'
];

foreach ($tables as $table) {
    $table_name = $wpdb->prefix . $table;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    echo "   - $table: " . ($exists ? "✅" : "❌") . "\n";
}

echo "\n=== TEST BÁSICO COMPLETADO ===\n";
echo "Si ves ✅ en las tablas, WordPress y el plugin están funcionando.\n";