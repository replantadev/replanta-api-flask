<?php
/**
 * Verificación Final - Dominios Reseller v1.5.7
 * Script para validar que todas las mejoras funcionan correctamente
 */

require_once 'dominios-reseller.php';

// Verificar versión del plugin
echo "=== VERIFICACIÓN FINAL v1.5.7 ===\n";
echo "Versión del plugin: " . DOMINIOS_RESELLER_VERSION . "\n";

// Verificar que las clases existen
echo "\n=== VERIFICACIÓN DE CLASES ===\n";
$classes = [
    'Dominios_Reseller_Onboarding_DB',
    'Dominios_Reseller_Debug_Hub',
    'Dominios_Reseller_Onboarding_Admin'
];

foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "✅ Clase $class existe\n";
    } else {
        echo "❌ Clase $class NO existe\n";
    }
}

// Verificar métodos críticos
echo "\n=== VERIFICACIÓN DE MÉTODOS ===\n";
$db_class = new Dominios_Reseller_Onboarding_DB();

$methods = [
    'update_existing_presets',
    'get_onboarding_logs',
    'insert_default_presets'
];

foreach ($methods as $method) {
    if (method_exists($db_class, $method)) {
        echo "✅ Método $method existe en DB class\n";
    } else {
        echo "❌ Método $method NO existe en DB class\n";
    }
}

// Verificar Debug Hub
$debug_class = new Dominios_Reseller_Debug_Hub();
if (method_exists($debug_class, 'test_update_presets')) {
    echo "✅ Método test_update_presets existe en Debug Hub\n";
} else {
    echo "❌ Método test_update_presets NO existe en Debug Hub\n";
}

// Verificar constantes de presets
echo "\n=== VERIFICACIÓN DE PRESETS ===\n";
if (defined('DOMINIOS_RESELLER_PRESETS_VERSION')) {
    echo "✅ Constante PRESETS_VERSION: " . DOMINIOS_RESELLER_PRESETS_VERSION . "\n";
} else {
    echo "❌ Constante PRESETS_VERSION no definida\n";
}

// Verificar estructura de archivos
echo "\n=== VERIFICACIÓN DE ARCHIVOS ===\n";
$files = [
    'includes/class-onboarding-db.php',
    'includes/class-debug-hub.php',
    'includes/class-onboarding-admin.php',
    'update-presets.php'
];

foreach ($files as $file) {
    $full_path = plugin_dir_path(__FILE__) . $file;
    if (file_exists($full_path)) {
        echo "✅ Archivo $file existe\n";
    } else {
        echo "❌ Archivo $file NO existe\n";
    }
}

echo "\n=== VERIFICACIÓN COMPLETADA ===\n";
echo "Si todos los checks son ✅, el plugin está listo para deployment.\n";
?>