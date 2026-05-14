<?php
/**
 * Test del sistema de onboarding asíncrono
 * Ejecutar desde el directorio del plugin
 */

// Cargar WordPress
$wp_load_paths = [
    __DIR__ . '/../../../wp-load.php', // Si está en wp-content/plugins/
    __DIR__ . '/../../wp-load.php',   // Si está en wp-content/
    __DIR__ . '/../wp-load.php',      // Si está en wp-content/
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

// 1. Verificar tablas
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

echo "\n";

// 2. Verificar presets
echo "2. VERIFICANDO PRESETS...\n";
try {
    $presets = Dominios_Reseller_Onboarding_DB::get_all_presets();
    echo "   - Presets encontrados: " . count($presets) . "\n";
    if (count($presets) > 0) {
        echo "   - Primer preset: {$presets[0]['preset_key']} - {$presets[0]['name']}\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error presets: " . $e->getMessage() . "\n";
}

echo "\n";

// 3. Probar encolado
echo "3. PROBANDO ENCOLADO...\n";
$test_domain = 'test-' . time() . '.com';
$test_preset = 'wp'; // Usar el primer preset disponible

try {
    $worker = Dominios_Reseller_Onboarding_Worker::get_instance();
    $result = $worker->enqueue($test_domain, $test_preset, false);

    echo "   - Dominio: $test_domain\n";
    echo "   - Preset: $test_preset\n";
    echo "   - Resultado: " . ($result['success'] ? "✅ ÉXITO" : "❌ ERROR") . "\n";

    if ($result['success']) {
        echo "   - Run ID: {$result['run_id']}\n";
        echo "   - Mensaje: {$result['message']}\n";
    } else {
        echo "   - Error: {$result['error']}\n";
    }
} catch (Exception $e) {
    echo "   ❌ Excepción en encolado: " . $e->getMessage() . "\n";
}

echo "\n";

// 4. Verificar run creado
echo "4. VERIFICANDO RUN CREADO...\n";
if (isset($result) && $result['success']) {
    try {
        $run_data = Dominios_Reseller_Onboarding_DB::get_run_data($result['run_id']);
        echo "   - Run encontrado: " . ($run_data ? "✅" : "❌") . "\n";
        if ($run_data) {
            echo "   - Estado: {$run_data['state']}\n";
            echo "   - Dominio: {$run_data['primary_domain']}\n";
            echo "   - Preset: {$run_data['preset_key']}\n";
        }
    } catch (Exception $e) {
        echo "   ❌ Error obteniendo run: " . $e->getMessage() . "\n";
    }
}

echo "\n";

// 5. Verificar eventos programados
echo "5. VERIFICANDO EVENTOS PROGRAMADOS...\n";
try {
    $events = _get_cron_array();
    $onboarding_events = [];

    foreach ($events as $timestamp => $hooks) {
        foreach ($hooks as $hook => $hook_events) {
            if (strpos($hook, 'dr_onboarding') !== false) {
                foreach ($hook_events as $event) {
                    $onboarding_events[] = [
                        'hook' => $hook,
                        'timestamp' => $timestamp,
                        'args' => $event['args'] ?? []
                    ];
                }
            }
        }
    }

    echo "   - Eventos de onboarding: " . count($onboarding_events) . "\n";
    foreach ($onboarding_events as $event) {
        $time_diff = $event['timestamp'] - time();
        echo "     * {$event['hook']} en " . ($time_diff > 0 ? "+{$time_diff}s" : "{$time_diff}s") . "\n";
        if (!empty($event['args'])) {
            echo "       Args: " . implode(', ', $event['args']) . "\n";
        }
    }
} catch (Exception $e) {
    echo "   ❌ Error obteniendo eventos: " . $e->getMessage() . "\n";
}

echo "\n";

// 6. Verificar estado legacy
echo "6. VERIFICANDO ESTADO LEGACY...\n";
try {
    $legacy_state = Dominios_Reseller_Onboarding_DB::get_onboarding_state($test_domain);
    echo "   - Estado legacy encontrado: " . ($legacy_state ? "✅" : "❌") . "\n";
    if ($legacy_state) {
        echo "   - Estado: {$legacy_state['state']}\n";
        echo "   - Run ID: {$legacy_state['last_run_id']}\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error estado legacy: " . $e->getMessage() . "\n";
}

echo "\n=== TEST COMPLETADO ===\n";
echo "Si todo está en ✅, el sistema funciona correctamente.\n";
echo "Si hay ❌, indica dónde está el problema.\n";