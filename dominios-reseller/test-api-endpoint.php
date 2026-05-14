#!/usr/bin/env php
<?php
/**
 * Script de prueba para verificar el endpoint REST API
 * 
 * Uso:
 *   php test-api-endpoint.php tudominio.com
 * 
 * O desde PowerShell:
 *   php test-api-endpoint.php tudominio.com
 */

if (php_sapi_name() !== 'cli') {
    die('Este script solo se puede ejecutar desde la línea de comandos');
}

// Obtener el dominio del argumento
$domain = $argv[1] ?? null;

if (!$domain) {
    echo "❌ Error: Debes proporcionar un dominio como argumento\n";
    echo "\nUso:\n";
    echo "  php test-api-endpoint.php tudominio.com\n";
    exit(1);
}

// Limpiar el dominio
$domain = preg_replace('/^www\./', '', strtolower(trim($domain)));

echo "🔍 Probando API de Replanta para el dominio: $domain\n";
echo str_repeat('-', 60) . "\n";

// URL del endpoint
$url = 'https://replanta.net/wp-json/replanta/v1/check_domain';

echo "📡 Endpoint: $url\n";
echo "📋 Dominio a verificar: $domain\n";
echo str_repeat('-', 60) . "\n";

// Preparar datos
$data = json_encode(array('domain' => $domain));

// Configurar cURL
$ch = curl_init();
curl_setopt_array($ch, array(
    CURLOPT_URL => $url,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $data,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data)
    ),
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true
));

// Ejecutar petición
echo "⏳ Enviando petición...\n";
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// Verificar errores de conexión
if ($curl_error) {
    echo "❌ Error de conexión: $curl_error\n";
    exit(1);
}

echo "📊 Código HTTP: $http_code\n";
echo str_repeat('-', 60) . "\n";

// Decodificar respuesta
$data = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "❌ Error al decodificar JSON\n";
    echo "Respuesta cruda:\n";
    echo $response . "\n";
    exit(1);
}

// Mostrar resultado
echo "📦 Respuesta JSON:\n";
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
echo str_repeat('-', 60) . "\n";

// Interpretar resultado
if (isset($data['hosted'])) {
    if ($data['hosted'] === true) {
        echo "✅ DOMINIO ALOJADO EN REPLANTA\n";
        echo "\n📊 Información del dominio:\n";
        echo "  • Servidor: " . ($data['server'] ?? 'N/A') . "\n";
        echo "  • Estado: " . ($data['status'] ?? 'N/A') . "\n";
        echo "  • Árboles plantados: " . ($data['trees_planted'] ?? 0) . "\n";
        echo "  • CO2 evitado: " . ($data['co2_evaded'] ?? 0) . " kg\n";
        echo "  • Fecha emisión: " . ($data['fecha_emision'] ?? 'N/A') . "\n";
        echo "  • Validez: " . ($data['validez'] ?? 'N/A') . "\n";
        echo "\n🎉 El sello-replanta se mostrará correctamente en este dominio\n";
    } else {
        echo "❌ DOMINIO NO ALOJADO EN REPLANTA\n";
        if (isset($data['message'])) {
            echo "  Mensaje: " . $data['message'] . "\n";
        }
        echo "\n⚠️  El sello-replanta NO se mostrará en este dominio\n";
        echo "  Verifica que el dominio esté en la base de datos de dominios-reseller\n";
    }
} else {
    echo "⚠️  Respuesta inesperada del servidor\n";
}

echo str_repeat('-', 60) . "\n";
exit(0);
