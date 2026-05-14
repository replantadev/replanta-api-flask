<?php
/**
 * Test específico para el problema de NS de Cloudflare con dominio .es
 */

require_once 'includes/class-openprovider-service.php';

echo "=== TEST NS CLOUDFLARE PARA .ES ===\n\n";

// Simular los NS que está intentando usar el sistema
$cloudflare_ns = ['doug.ns.cloudflare.com', 'nia.ns.cloudflare.com'];

echo "NS a validar: " . implode(', ', $cloudflare_ns) . "\n\n";

// Verificar si Openprovider está configurado
$op_service = Dominios_Reseller_Openprovider_Service::get_instance();

if (!$op_service->is_configured()) {
    echo "❌ Openprovider no configurado\n";
    exit(1);
}

echo "✅ Openprovider configurado\n";

// Verificar dominio
$domain_check = $op_service->domain_exists('carani.es');
if (!$domain_check['exists']) {
    echo "❌ Dominio carani.es no encontrado en Openprovider\n";
    exit(1);
}

echo "✅ Dominio carani.es encontrado\n";

// Probar validación de NS
echo "\n=== VALIDACIÓN DE NS ===\n";
$validation = $op_service->validate_nameservers($cloudflare_ns, 'es');

if (is_wp_error($validation)) {
    echo "❌ Validación fallida: " . $validation->get_error_message() . "\n";
} else {
    echo "✅ Validación exitosa\n";
}

// Intentar actualizar NS (esto debería fallar con el mismo error)
echo "\n=== INTENTO DE ACTUALIZACIÓN ===\n";
$result = $op_service->update_nameservers('carani.es', $cloudflare_ns);

if (is_wp_error($result)) {
    echo "❌ Error esperado: " . $result->get_error_message() . "\n";
    echo "Código de error: " . $result->get_error_code() . "\n";
} else {
    echo "✅ Actualización exitosa (inesperado)\n";
}

echo "\n=== ANÁLISIS ===\n";
echo "El problema parece ser específico de la validación de Openprovider para dominios .es\n";
echo "Los NS de Cloudflare deberían estar autorizados, pero Openprovider los rechaza.\n";
echo "\nPosibles soluciones:\n";
echo "1. Contactar a Openprovider para autorizar NS de Cloudflare para .es\n";
echo "2. Usar NS alternativos autorizados para .es\n";
echo "3. Verificar si hay restricciones específicas del registro español\n";

echo "\n=== NS AUTORIZADOS PARA .ES ===\n";
echo "Los NS de Cloudflare deberían estar en la lista autorizada por Red.es\n";
echo "Si no lo están, habría que usar NS alternativos o contactar a Cloudflare\n";