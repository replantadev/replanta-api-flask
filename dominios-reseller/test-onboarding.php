<?php
/**
 * Script de prueba para el sistema de onboarding
 */

require_once __DIR__ . '/wp-load.php';

if (!defined('ABSPATH')) {
    exit;
}

// Incluir las clases necesarias
require_once __DIR__ . '/includes/class-onboarding-db.php';
require_once __DIR__ . '/includes/class-onboarding-worker.php';

echo "=== PRUEBA DEL SISTEMA DE ONBOARDING ===\n\n";

// 1. Verificar que las tablas existen
echo "1. Verificando tablas...\n";
try {
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
        echo "   - $table: " . ($exists ? "✅ EXISTE" : "❌ NO EXISTE") . "\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error verificando tablas: " . $e->getMessage() . "\n";
}

echo "\n";

// 2. Verificar presets disponibles
echo "2. Verificando presets...\n";
try {
    $presets = Dominios_Reseller_Onboarding_DB::get_all_presets();
    echo "   - Presets encontrados: " . count($presets) . "\n";
    foreach ($presets as $preset) {
        echo "     * {$preset['preset_key']}: {$preset['name']}\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error obteniendo presets: " . $e->getMessage() . "\n";
}

echo "\n";

// 3. Probar encolado
echo "3. Probando encolado de dominio...\n";
try {
    $worker = Dominios_Reseller_Onboarding_Worker::get_instance();
    $result = $worker->enqueue('test-ejemplo.com', 'wp', true);

    echo "   - Resultado: " . ($result['success'] ? "✅ ÉXITO" : "❌ ERROR") . "\n";
    if ($result['success']) {
        echo "   - Run ID: {$result['run_id']}\n";
        echo "   - Mensaje: {$result['message']}\n";
    } else {
        echo "   - Error: {$result['error']}\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error en encolado: " . $e->getMessage() . "\n";
}

echo "\n";

// 4. Verificar estado después del encolado
echo "4. Verificando estado del dominio...\n";
try {
    $state = Dominios_Reseller_Onboarding_DB::get_onboarding_state('test-ejemplo.com');
    echo "   - Estado encontrado: " . ($state ? "✅ SÍ" : "❌ NO") . "\n";
    if ($state) {
        echo "   - Estado: {$state['state']}\n";
        echo "   - Run ID: {$state['last_run_id']}\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error obteniendo estado: " . $e->getMessage() . "\n";
}

echo "\n";

// 5. Verificar runs activos
echo "5. Verificando runs activos...\n";
try {
    $runs = Dominios_Reseller_Onboarding_DB::get_active_runs();
    echo "   - Runs activos: " . count($runs) . "\n";
    foreach ($runs as $run) {
        echo "     * {$run['run_id']}: {$run['primary_domain']} - {$run['state']}\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error obteniendo runs: " . $e->getMessage() . "\n";
}

echo "\n";

// 6. Verificar eventos programados
echo "6. Verificando eventos programados...\n";
try {
    $events = _get_cron_array();
    $found_events = [];

    foreach ($events as $timestamp => $hooks) {
        foreach ($hooks as $hook => $args) {
            if (strpos($hook, 'dr_onboarding') !== false) {
                $found_events[] = [
                    'hook' => $hook,
                    'timestamp' => $timestamp,
                    'args' => $args
                ];
            }
        }
    }

    echo "   - Eventos encontrados: " . count($found_events) . "\n";
    foreach ($found_events as $event) {
        echo "     * {$event['hook']} en " . date('H:i:s', $event['timestamp']) . "\n";
        foreach ($event['args'] as $arg) {
            if (isset($arg['args'])) {
                echo "       - Args: " . implode(', ', $arg['args']) . "\n";
            }
        }
    }
} catch (Exception $e) {
    echo "   ❌ Error obteniendo eventos: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DE PRUEBA ===\n";