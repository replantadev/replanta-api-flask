<?php
/**
 * Script de debug para verificar configuración de StaffKit
 * Lugar: http://replanta.local/wp-content/plugins/replanta-contact-manager/debug-staffkit.php
 */

// Cargar WordPress
require_once dirname(__FILE__) . '/../../../../wp-load.php';

// Debug info
echo "=== DEBUG STAFFKIT ===\n\n";

// 1. Verificar si los options existen
$url = get_option('rcm_staffkit_url');
$key = get_option('rcm_staffkit_api_key');

echo "rcm_staffkit_url: " . ($url ? $url : '[VACÍO]') . "\n";
echo "rcm_staffkit_api_key: " . ($key ? '***' . substr($key, -4) : '[VACÍO]') . "\n\n";

// 2. Verificar la base de datos directamente
global $wpdb;
$result = $wpdb->get_results(
    "SELECT option_name, option_value FROM {$wpdb->options} 
     WHERE option_name LIKE '%staffkit%' OR option_name LIKE '%rcm%'
     LIMIT 20"
);

echo "Options en la BD:\n";
echo json_encode($result, JSON_PRETTY_PRINT) . "\n\n";

// 3. Verificar si el plugin está activado
$active_plugins = get_option('active_plugins');
$replanta_active = in_array('replanta-contact-manager/replanta-contact-manager.php', $active_plugins);
echo "Plugin activado: " . ($replanta_active ? 'SÍ' : 'NO') . "\n";

// 4. Verificar clases
echo "\nClases disponibles:\n";
echo "RCM_Admin_Settings: " . (class_exists('RCM_Admin_Settings') ? 'SÍ' : 'NO') . "\n";
echo "RCM_REST_API: " . (class_exists('RCM_REST_API') ? 'SÍ' : 'NO') . "\n";
echo "RCM_Elementor_Integration: " . (class_exists('RCM_Elementor_Integration') ? 'SÍ' : 'NO') . "\n";

// 5. Verificar último error
if (function_exists('get_lasterror')) {
    echo "\nÚltimo error PHP:\n";
    print_r(error_get_last());
}

echo "\n=== FIN DEBUG ===\n";
?>
