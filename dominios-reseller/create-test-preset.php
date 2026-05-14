<?php
/**
 * Payload de ejemplo para testing del sistema de onboarding
 * Este archivo contiene un preset básico para pruebas
 */

// Cargar WordPress
$wp_load_paths = [
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../wp-load.php',
    __DIR__ . '/../wp-load.php',
];

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die("❌ No se pudo encontrar wp-load.php\n");
}

require_once __DIR__ . '/includes/class-onboarding-db.php';

echo "=== CREANDO PRESET DE EJEMPLO ===\n\n";

// Payload de ejemplo simplificado para testing
$example_payload = [
    'version' => '3.0',
    'settings' => [
        'ssl' => 'full',
        'always_use_https' => 'on',
        'automatic_https_rewrites' => 'on',
        'min_tls_version' => '1.2',
        'opportunistic_encryption' => 'on',
        'cache_level' => 'aggressive',
        'browser_cache_ttl' => 14400,
        'always_online' => 'on'
    ],
    'rules' => [
        [
            'description' => 'Security Headers Básicos',
            'expression' => 'true',
            'action' => 'rewrite',
            'action_parameters' => [
                'headers' => [
                    'X-Frame-Options' => 'SAMEORIGIN',
                    'X-Content-Type-Options' => 'nosniff',
                    'Referrer-Policy' => 'strict-origin-when-cross-origin'
                ]
            ]
        ]
    ]
];

// Insertar preset de ejemplo
try {
    global $wpdb;
    $table = Dominios_Reseller_Onboarding_DB::get_presets_table();

    $result = $wpdb->insert($table, [
        'preset_key' => 'test-basic',
        'name' => 'Preset Básico de Testing',
        'description' => 'Preset simplificado para pruebas del sistema asíncrono',
        'payload' => json_encode($example_payload),
        'is_default' => 0,
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ]);

    if ($result) {
        echo "✅ Preset 'test-basic' creado exitosamente\n";
        echo "   - Payload version: {$example_payload['version']}\n";
        echo "   - Settings: " . count($example_payload['settings']) . "\n";
        echo "   - Rules: " . count($example_payload['rules']) . "\n";
    } else {
        echo "❌ Error creando preset: " . $wpdb->last_error . "\n";
    }

} catch (Exception $e) {
    echo "❌ Excepción: " . $e->getMessage() . "\n";
}

echo "\n=== PRESET CREADO ===\n";
echo "Ahora puedes usar el preset 'test-basic' para probar el sistema.\n";