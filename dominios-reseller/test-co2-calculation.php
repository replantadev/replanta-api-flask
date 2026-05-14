<?php
/**
 * Test rápido del cálculo de CO2 híbrido
 * 
 * Ejecutar desde terminal:
 * wp eval-file test-co2-calculation.php
 * 
 * O desde navegador añadiendo al final de dominios-reseller.php:
 * if (isset($_GET['test_co2'])) { include('test-co2-calculation.php'); exit; }
 * Luego visitar: /wp-admin/?test_co2=1
 */

// Cargar WordPress si no está cargado
if (!defined('ABSPATH')) {
    require_once('../../../wp-load.php');
}

// Incluir funciones necesarias
require_once plugin_dir_path(__FILE__) . 'includes/emisiones-co2-api.php';

echo "<h1>🧪 Test de Cálculo CO2 Híbrido</h1>\n\n";

// Test 1: Website Carbon API
echo "<h2>Test 1: Website Carbon API</h2>\n";
$domain_test = 'replanta.net';
$trafico = 10 * 1024 * 1024 * 1024; // 10 GB
$dias = 30;

echo "Dominio: $domain_test\n";
echo "Tráfico: 10 GB\n";
echo "Días activo: $dias\n\n";

$resultado_api = dr_calcular_co2_website_carbon($domain_test, $trafico, $dias);

if ($resultado_api !== false) {
    echo "✅ Website Carbon API funcionó:\n";
    echo "   • CO2 tráfico: {$resultado_api['co2_trafico_gramos']} g\n";
    echo "   • CO2 base: {$resultado_api['co2_base_gramos']} g\n";
    echo "   • CO2 total: {$resultado_api['co2_total_gramos']} g\n";
    echo "   • Visitas estimadas: {$resultado_api['visitas_estimadas']}\n";
    echo "   • Más limpio que: " . round($resultado_api['cleaner_than'] * 100) . "% de sitios\n";
    echo "   • Fuente: {$resultado_api['fuente']}\n\n";
} else {
    echo "❌ Website Carbon API falló\n\n";
}

// Test 2: Cálculo local
echo "<h2>Test 2: Cálculo Local (Fallback)</h2>\n";
$resultado_local_uk = dr_calcular_co2_local($trafico, $dias, 'uk');

echo "Servidor UK:\n";
echo "   • CO2 tráfico: {$resultado_local_uk['co2_trafico_gramos']} g\n";
echo "   • CO2 base: {$resultado_local_uk['co2_base_gramos']} g\n";
echo "   • CO2 total: {$resultado_local_uk['co2_total_gramos']} g\n";
echo "   • Grid intensity: {$resultado_local_uk['grid_intensity']} g CO2/kWh\n";
echo "   • Fuente: {$resultado_local_uk['fuente']}\n\n";

$resultado_local_usa = dr_calcular_co2_local($trafico, $dias, 'usa');

echo "Servidor USA:\n";
echo "   • CO2 tráfico: {$resultado_local_usa['co2_trafico_gramos']} g\n";
echo "   • CO2 base: {$resultado_local_usa['co2_base_gramos']} g\n";
echo "   • CO2 total: {$resultado_local_usa['co2_total_gramos']} g\n";
echo "   • Grid intensity: {$resultado_local_usa['grid_intensity']} g CO2/kWh\n";
echo "   • Fuente: {$resultado_local_usa['fuente']}\n\n";

// Test 3: Función inteligente (híbrida)
echo "<h2>Test 3: Función Inteligente (Híbrida)</h2>\n";
$resultado_hibrido = dr_calcular_co2_inteligente($domain_test, $trafico, $dias, 'uk');

echo "Resultado final:\n";
echo "   • CO2 total: {$resultado_hibrido['co2_total_gramos']} g\n";
echo "   • Fuente usada: {$resultado_hibrido['fuente']}\n";
echo "   • Timestamp: " . date('Y-m-d H:i:s', $resultado_hibrido['timestamp']) . "\n\n";

// Test 4: Caché
echo "<h2>Test 4: Verificar Caché</h2>\n";
$cache_key = 'dr_co2_' . md5($domain_test);
$cached = get_transient($cache_key);

if ($cached !== false) {
    echo "✅ Caché activo para $domain_test\n";
    echo "   Expira en: " . human_time_diff(time(), time() + get_option('_transient_timeout_' . $cache_key)) . "\n\n";
} else {
    echo "⚠️ No hay caché para $domain_test\n\n";
}

// Test 5: Limpiar caché
echo "<h2>Test 5: Limpiar Caché</h2>\n";
$limpiado = dr_limpiar_cache_co2($domain_test);
echo $limpiado ? "✅ Caché limpiado\n" : "⚠️ No había caché para limpiar\n";

echo "\n<hr>\n";
echo "<strong>✅ Todos los tests completados</strong>\n";
echo "\n<p>Próximo paso: Prueba el botón 'Calcular' en /wp-admin/admin.php?page=dominios-reseller</p>\n";
