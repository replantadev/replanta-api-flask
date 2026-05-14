<?php
/**
 * Test de autenticación Cloudflare - Verificar prioridad API Token vs Global Key
 */

require_once 'includes/class-cloudflare-service.php';

echo "=== TEST AUTENTICACIÓN CLOUDFLARE ===\n\n";

$cf_service = new Dominios_Reseller_Cloudflare_Service();

// Verificar qué credenciales están disponibles
$token = $cf_service->get_token();
$email = $cf_service->get_email();
$global_key = $cf_service->get_global_key();

echo "Credenciales disponibles:\n";
echo "- API Token: " . (!empty($token) ? "✅ Configurado (" . substr($token, 0, 10) . "...)" : "❌ No configurado") . "\n";
echo "- Email: " . (!empty($email) ? "✅ $email" : "❌ No configurado") . "\n";
echo "- Global Key: " . (!empty($global_key) ? "✅ Configurado (" . substr($global_key, 0, 10) . "...)" : "❌ No configurado") . "\n\n";

// Verificar qué método se usará según la nueva lógica
echo "MÉTODO DE AUTENTICACIÓN (Nueva Lógica):\n";

if (!empty($token)) {
    echo "✅ Usará: API Token (Bearer Token) - PRIORIDAD\n";
    echo "   Headers esperados: Authorization: Bearer [token]\n";
} elseif (!empty($email) && !empty($global_key)) {
    echo "⚠️  Usará: Global API Key + Email - FALLBACK\n";
    echo "   Headers esperados: X-Auth-Email + X-Auth-Key\n";
} else {
    echo "❌ ERROR: No hay credenciales válidas configuradas\n";
    exit(1);
}

echo "\n=== VERIFICACIÓN DE HEADERS ===\n";

// Obtener headers usando el método interno (accediendo vía reflexión para test)
$reflection = new ReflectionClass($cf_service);
$method = $reflection->getMethod('get_auth_headers');
$method->setAccessible(true);
$headers = $method->invoke($cf_service);

if (empty($headers)) {
    echo "❌ ERROR: No se generaron headers de autenticación\n";
    exit(1);
}

echo "Headers generados:\n";
foreach ($headers as $key => $value) {
    if ($key === 'Authorization') {
        echo "- $key: Bearer [token oculto]\n";
    } elseif ($key === 'X-Auth-Key') {
        echo "- $key: [global-key oculta]\n";
    } else {
        echo "- $key: $value\n";
    }
}

echo "\n=== VALIDACIÓN ===\n";

if (isset($headers['Authorization']) && strpos($headers['Authorization'], 'Bearer ') === 0) {
    echo "✅ Correcto: Usando API Token (Bearer Token)\n";
} elseif (isset($headers['X-Auth-Email']) && isset($headers['X-Auth-Key'])) {
    echo "⚠️  Fallback: Usando Global API Key + Email\n";
} else {
    echo "❌ ERROR: Headers de autenticación inválidos\n";
}

echo "\n=== RECOMENDACIONES ===\n";
if (empty($token) && (!empty($email) || !empty($global_key))) {
    echo "⚠️  RECOMENDACIÓN: Configurar API Token en lugar de Global API Key\n";
    echo "   Los API Tokens son más seguros y tienen permisos granulares.\n";
} elseif (!empty($token)) {
    echo "✅ Configuración óptima: API Token configurado correctamente.\n";
}

echo "\nTest completado.\n";