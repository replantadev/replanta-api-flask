<?php
/**
 * Script para actualizar presets existentes en la base de datos
 * Ejecutar después de actualizar el plugin
 */

echo "=== ACTUALIZACIÓN DE PRESETS v1.5.7 ===\n\n";

require_once 'includes/class-onboarding-db.php';

if (!class_exists('Dominios_Reseller_Onboarding_DB')) {
    echo "❌ Error: No se puede cargar la clase Dominios_Reseller_Onboarding_DB\n";
    exit(1);
}

echo "✅ Clase cargada correctamente\n";

// Verificar presets actuales antes de actualizar
$presets_before = Dominios_Reseller_Onboarding_DB::get_all_presets();
echo "\n=== PRESETS ANTES DE ACTUALIZACIÓN ===\n";
foreach ($presets_before as $preset) {
    $payload = json_decode($preset['payload'], true);
    $version = $payload['version'] ?? 'N/A';
    echo "- {$preset['preset_key']}: {$preset['name']} (v{$version})\n";
}

// Actualizar presets
echo "\n=== ACTUALIZANDO PRESETS ===\n";
try {
    Dominios_Reseller_Onboarding_DB::update_existing_presets();
    echo "✅ Presets actualizados correctamente\n";
} catch (Exception $e) {
    echo "❌ Error actualizando presets: " . $e->getMessage() . "\n";
    exit(1);
}

// Verificar presets después de actualizar
$presets_after = Dominios_Reseller_Onboarding_DB::get_all_presets();
echo "\n=== PRESETS DESPUÉS DE ACTUALIZACIÓN ===\n";
foreach ($presets_after as $preset) {
    $payload = json_decode($preset['payload'], true);
    $version = $payload['version'] ?? 'N/A';
    echo "- {$preset['preset_key']}: {$preset['name']} (v{$version})\n";
}

// Verificar cambios específicos
$wp_preset = null;
foreach ($presets_after as $preset) {
    if ($preset['preset_key'] === 'wp') {
        $wp_preset = $preset;
        break;
    }
}

if ($wp_preset) {
    $payload = json_decode($wp_preset['payload'], true);
    $settings = $payload['settings'] ?? [];

    echo "\n=== VERIFICACIÓN PRESET WP ===\n";
    echo "Versión: " . ($payload['version'] ?? 'N/A') . "\n";

    // Verificar que no tenga settings obsoletos
    $obsolete_settings = ['0rtt', 'polish', 'mirage', 'challenge_ttl', 'browser_check', 'email_obfuscation', 'hotlink_protection', 'ip_geolocation', 'cache_level', 'always_online'];
    $found_obsolete = array_intersect(array_keys($settings), $obsolete_settings);

    if (empty($found_obsolete)) {
        echo "✅ No se encontraron settings obsoletos\n";
    } else {
        echo "❌ Aún tiene settings obsoletos: " . implode(', ', $found_obsolete) . "\n";
    }

    // Verificar settings válidos
    $valid_settings = ['http3', 'brotli', 'minify', 'rocket_loader', 'early_hints', 'ssl', 'always_use_https', 'min_tls_version', 'automatic_https_rewrites', 'opportunistic_encryption', 'security_level', 'browser_cache_ttl', 'development_mode'];
    $valid_found = array_intersect(array_keys($settings), $valid_settings);

    echo "Settings válidos encontrados: " . count($valid_found) . "/" . count($valid_settings) . "\n";
}

echo "\n=== ACTUALIZACIÓN COMPLETADA ===\n";
echo "Los presets han sido actualizados a la versión 3.0 compatible con Cloudflare API v4\n";
echo "Los errores 'Setting no reconocido' deberían desaparecer ahora.\n";