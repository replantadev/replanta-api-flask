<?php
/**
 * Test script para validar preset actualizado
 */

require_once 'includes/class-onboarding-db.php';

echo "=== VALIDACIÓN PRESET WP v3.0 ===\n\n";

// Obtener preset
$presets = Dominios_Reseller_Onboarding_DB::get_all_presets();
$wp_preset = null;

foreach ($presets as $preset) {
    if ($preset['preset_key'] === 'wp') {
        $wp_preset = $preset;
        break;
    }
}

if (!$wp_preset) {
    echo "❌ Preset 'wp' no encontrado\n";
    exit(1);
}

echo "✅ Preset encontrado: {$wp_preset['name']}\n";
echo "Versión: " . (isset($wp_preset['version']) ? $wp_preset['version'] : 'N/A') . "\n\n";

// Decodificar payload
$payload = json_decode($wp_preset['payload'], true);

if (!$payload) {
    echo "❌ Error decodificando payload\n";
    exit(1);
}

echo "=== SETTINGS VÁLIDOS ===\n";
$valid_settings = [
    'ssl', 'always_use_https', 'min_tls_version', 'automatic_https_rewrites',
    'opportunistic_encryption', 'security_level', 'browser_cache_ttl',
    'development_mode', 'http3', 'brotli', 'early_hints', 'rocket_loader', 'minify'
];

$settings = $payload['settings'] ?? [];
$valid_count = 0;
$invalid_count = 0;

foreach ($settings as $setting => $value) {
    if (in_array($setting, $valid_settings)) {
        echo "✅ {$setting}: " . json_encode($value) . "\n";
        $valid_count++;
    } else {
        echo "❌ {$setting}: " . json_encode($value) . " (NO VÁLIDO)\n";
        $invalid_count++;
    }
}

echo "\nResumen: {$valid_count} válidos, {$invalid_count} inválidos\n";

if ($invalid_count > 0) {
    echo "❌ Preset contiene settings obsoletos\n";
    exit(1);
}

echo "✅ Preset válido para Cloudflare API v4\n";
echo "\n=== FEATURES INCLUIDAS ===\n";
echo "- AI Crawl Control: " . (isset($payload['ai_crawlers']) ? "✅" : "❌") . "\n";
echo "- Bot Management: " . (isset($payload['bot_management']) ? "✅" : "❌") . "\n";
echo "- Security Headers: " . (isset($payload['security_headers']) ? "✅" : "❌") . "\n";
echo "- Firewall Rules: " . (isset($payload['firewall_rules']) ? "✅" : "❌") . "\n";
echo "- Cache Rules: " . (isset($payload['cache_rules']) ? "✅" : "❌") . "\n";

echo "\n✅ Validación completada exitosamente\n";