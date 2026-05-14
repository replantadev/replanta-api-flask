<?php
/**
 * Herramienta para guardar configuración de StaffKit directamente
 * Lugar: c:\Users\programacion2\Local Sites\repos\replanta-contact-manager\fix-staffkit-config.php
 * 
 * USO: 
 * 1. Ejecutar en terminal: php fix-staffkit-config.php
 * 2. O crear un endpoint temporal en WordPress
 */

// Cargar WordPress si se ejecuta desde WP
if (function_exists('get_option')) {
    echo "WordPress ya cargado\n";
    $url = 'https://staff.replanta.dev';
    $key = 'sk_live_replanta_2026_webhook_secure_key';
    
    update_option('rcm_staffkit_url', $url);
    update_option('rcm_staffkit_api_key', $key);
    
    echo "✓ Configuración de StaffKit guardada\n";
    echo "  URL: " . get_option('rcm_staffkit_url') . "\n";
    echo "  Key: " . (get_option('rcm_staffkit_api_key') ? '***' . substr(get_option('rcm_staffkit_api_key'), -4) : '[vacío]') . "\n";
} else {
    echo "WordPress no cargado. Necesitas ejecutar esto dentro de WordPress.\n";
    echo "\nAlternativa: Ejecuta este código en wp-admin > Herramientas > Temas > Editor y pega esto en functions.php temporal:\n";
    echo <<<'PHP'
add_action('admin_init', function() {
    if (isset($_GET['fix_staffkit'])) {
        update_option('rcm_staffkit_url', 'https://staff.replanta.dev');
        update_option('rcm_staffkit_api_key', 'sk_live_replanta_2026_webhook_secure_key');
        wp_die('StaffKit config saved!');
    }
});
PHP;
}
?>
