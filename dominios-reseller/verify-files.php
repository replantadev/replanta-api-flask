<?php
/**
 * Verificación de Archivos - Dominios Reseller v1.5.7
 * Script simple para validar estructura de archivos
 */

echo "=== VERIFICACIÓN DE ARCHIVOS v1.5.7 ===\n";

// Verificar archivos principales
$files = [
    'dominios-reseller.php',
    'includes/class-onboarding-db.php',
    'includes/class-debug-hub.php',
    'includes/class-onboarding-admin.php',
    'update-presets.php',
    'verify-final.php',
    'CHANGELOG.md'
];

$base_path = __DIR__ . '/';

echo "\n=== ARCHIVOS PRINCIPALES ===\n";
foreach ($files as $file) {
    $full_path = $base_path . $file;
    if (file_exists($full_path)) {
        echo "✅ $file\n";
    } else {
        echo "❌ $file - NO ENCONTRADO\n";
    }
}

// Verificar contenido de archivos clave
echo "\n=== VERIFICACIÓN DE CONTENIDO ===\n";

// Verificar versión en el archivo principal
$main_file = $base_path . 'dominios-reseller.php';
if (file_exists($main_file)) {
    $content = file_get_contents($main_file);
    if (strpos($content, "define('DOMINIOS_RESELLER_VERSION', '1.5.7')") !== false) {
        echo "✅ Versión 1.5.7 definida correctamente\n";
    } else {
        echo "❌ Versión 1.5.7 NO encontrada\n";
    }

    if (strpos($content, 'update_existing_presets') !== false) {
        echo "✅ Llamada a update_existing_presets encontrada\n";
    } else {
        echo "❌ Llamada a update_existing_presets NO encontrada\n";
    }
}

// Verificar método update_existing_presets
$db_file = $base_path . 'includes/class-onboarding-db.php';
if (file_exists($db_file)) {
    $content = file_get_contents($db_file);
    if (strpos($content, 'function update_existing_presets') !== false) {
        echo "✅ Método update_existing_presets encontrado\n";
    } else {
        echo "❌ Método update_existing_presets NO encontrado\n";
    }
}

// Verificar test_update_presets en Debug Hub
$debug_file = $base_path . 'includes/class-debug-hub.php';
if (file_exists($debug_file)) {
    $content = file_get_contents($debug_file);
    if (strpos($content, 'function test_update_presets') !== false) {
        echo "✅ Método test_update_presets encontrado\n";
    } else {
        echo "❌ Método test_update_presets NO encontrado\n";
    }
}

// Verificar mejoras en el modal de logs
$admin_file = $base_path . 'includes/class-onboarding-admin.php';
if (file_exists($admin_file)) {
    $content = file_get_contents($admin_file);
    if (strpos($content, 'dr-logs-modal') !== false) {
        echo "✅ Modal de logs mejorado encontrado\n";
    } else {
        echo "❌ Modal de logs mejorado NO encontrado\n";
    }
}

echo "\n=== VERIFICACIÓN COMPLETADA ===\n";
echo "Si todos los checks son ✅, el deployment está listo.\n";
?>