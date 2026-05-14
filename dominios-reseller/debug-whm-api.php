<?php
/**
 * DEBUG WHM API - Script de diagnóstico completo
 * Ejecutar desde: tudominio.com/wp-content/plugins/dominios-reseller/debug-whm-api.php
 * O desde WP-CLI: wp eval-file debug-whm-api.php
 */

// Cargar WordPress
$wp_load_paths = [
    dirname(__FILE__) . '/../../../wp-load.php',
    dirname(__FILE__) . '/../../../../wp-load.php',
    'C:/Users/programacion2/Local Sites/repos/wp-load.php',
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
    die("❌ No se pudo cargar WordPress. Ejecuta desde el directorio correcto.\n");
}

// Verificar permisos
if (!current_user_can('manage_options') && php_sapi_name() !== 'cli') {
    die("❌ Necesitas ser administrador para ejecutar este script.\n");
}

echo "<pre style='font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; overflow: auto;'>";
echo "═══════════════════════════════════════════════════════════════\n";
echo "🔍 DEBUG WHM API - DIAGNÓSTICO COMPLETO\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// 1. Verificar opciones guardadas
echo "📋 1. OPCIONES GUARDADAS EN WORDPRESS:\n";
echo str_repeat("-", 60) . "\n";

$opts = get_option('dominios_reseller_options', []);
echo "dominios_reseller_options:\n";
echo "  uk_server_ip: " . ($opts['uk_server_ip'] ?? '(vacío)') . "\n";
echo "  uk_whm_user: " . ($opts['uk_whm_user'] ?? '(vacío)') . "\n";
echo "  uk_whm_token: " . ($opts['uk_whm_token'] ? '***' . substr($opts['uk_whm_token'], -4) : '(vacío)') . "\n";
echo "  usa_server_ip: " . ($opts['usa_server_ip'] ?? '(vacío)') . "\n";
echo "  usa_whm_user: " . ($opts['usa_whm_user'] ?? '(vacío)') . "\n";
echo "  usa_whm_token: " . ($opts['usa_whm_token'] ? '***' . substr($opts['usa_whm_token'], -4) : '(vacío)') . "\n";
echo "\n";

// 2. Verificar función dominios_reseller_get_server_ip
echo "📋 2. FUNCIÓN dominios_reseller_get_server_ip():\n";
echo str_repeat("-", 60) . "\n";

if (function_exists('dominios_reseller_get_server_ip')) {
    $uk_ip = dominios_reseller_get_server_ip('uk');
    $usa_ip = dominios_reseller_get_server_ip('usa');
    echo "  UK IP: " . ($uk_ip ?: '(vacío/null)') . "\n";
    echo "  USA IP: " . ($usa_ip ?: '(vacío/null)') . "\n";
} else {
    echo "  ❌ Función no existe!\n";
}
echo "\n";

// 3. Test de conectividad básica
echo "📋 3. TEST DE CONECTIVIDAD (sin autenticación):\n";
echo str_repeat("-", 60) . "\n";

$servers = [
    'UK' => $opts['uk_server_ip'] ?? '',
    'USA' => $opts['usa_server_ip'] ?? '',
];

foreach ($servers as $label => $ip) {
    if (empty($ip)) {
        echo "  {$label}: ⚠️ IP no configurada\n";
        continue;
    }
    
    echo "  {$label} ({$ip}):\n";
    
    // Test puerto 2087
    $start = microtime(true);
    $fp = @fsockopen($ip, 2087, $errno, $errstr, 5);
    $time = round((microtime(true) - $start) * 1000);
    
    if ($fp) {
        echo "    Puerto 2087: ✅ Abierto ({$time}ms)\n";
        fclose($fp);
    } else {
        echo "    Puerto 2087: ❌ Cerrado/Timeout - {$errstr} ({$errno})\n";
    }
    
    // Test HTTPS básico
    $test_url = "https://{$ip}:2087/";
    $ch = curl_init($test_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_NOBODY => true,
    ]);
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code > 0) {
        echo "    HTTPS: ✅ Responde (HTTP {$http_code})\n";
    } else {
        echo "    HTTPS: ❌ {$curl_error}\n";
    }
}
echo "\n";

// 4. Test de autenticación WHM
echo "📋 4. TEST DE AUTENTICACIÓN WHM API:\n";
echo str_repeat("-", 60) . "\n";

$test_configs = [
    'UK' => [
        'ip' => $opts['uk_server_ip'] ?? '',
        'user' => $opts['uk_whm_user'] ?? 'root',
        'token' => $opts['uk_whm_token'] ?? '',
    ],
    'USA' => [
        'ip' => $opts['usa_server_ip'] ?? '',
        'user' => $opts['usa_whm_user'] ?? 'root',
        'token' => $opts['usa_whm_token'] ?? '',
    ],
];

foreach ($test_configs as $label => $config) {
    if (empty($config['ip']) || empty($config['token']) || empty($config['user'])) {
        echo "  {$label}: ⚠️ Configuración incompleta\n";
        continue;
    }
    
    echo "  {$label}:\n";
    
    // Test 1: API version (más simple)
    $url1 = "https://{$config['ip']}:2087/json-api/version";
    echo "    📡 Test 1: {$url1}\n";
    
    $ch = curl_init($url1);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => ["Authorization: whm {$config['user']}:{$config['token']}"],
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    
    $start = microtime(true);
    $resp1 = curl_exec($ch);
    $time = round((microtime(true) - $start) * 1000);
    $http1 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err1 = curl_error($ch);
    curl_close($ch);
    
    if ($resp1 === false) {
        echo "       ❌ cURL Error: {$err1}\n";
    } elseif ($http1 !== 200) {
        echo "       ❌ HTTP {$http1} ({$time}ms)\n";
        echo "       Respuesta: " . substr($resp1, 0, 200) . "\n";
    } else {
        $data1 = json_decode($resp1, true);
        echo "       ✅ HTTP 200 ({$time}ms)\n";
        echo "       Version: " . ($data1['version'] ?? json_encode($data1)) . "\n";
    }
    
    // Test 2: listaccts (el que usa el plugin)
    $url2 = "https://{$config['ip']}:2087/json-api/listaccts?api.version=1";
    echo "    📡 Test 2: {$url2}\n";
    
    $ch = curl_init($url2);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => ["Authorization: whm {$config['user']}:{$config['token']}"],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    
    $start = microtime(true);
    $resp2 = curl_exec($ch);
    $time = round((microtime(true) - $start) * 1000);
    $http2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err2 = curl_error($ch);
    curl_close($ch);
    
    if ($resp2 === false) {
        echo "       ❌ cURL Error: {$err2}\n";
    } elseif ($http2 !== 200) {
        echo "       ❌ HTTP {$http2} ({$time}ms)\n";
        echo "       Respuesta: " . substr($resp2, 0, 300) . "\n";
    } else {
        $data2 = json_decode($resp2, true);
        $count = count($data2['data']['acct'] ?? $data2['acct'] ?? []);
        echo "       ✅ HTTP 200 ({$time}ms)\n";
        echo "       Cuentas encontradas: {$count}\n";
        
        if ($count > 0) {
            $accts = $data2['data']['acct'] ?? $data2['acct'] ?? [];
            echo "       Primeros 3 dominios:\n";
            for ($i = 0; $i < min(3, count($accts)); $i++) {
                echo "         • " . ($accts[$i]['domain'] ?? 'N/A') . " (user: " . ($accts[$i]['user'] ?? 'N/A') . ")\n";
            }
        }
    }
    echo "\n";
}

// 5. Test específico de SSO/API para un usuario cPanel
$target_user = '';
if (php_sapi_name() === 'cli') {
    $target_user = $argv[1] ?? '';
} else {
    $target_user = sanitize_text_field($_GET['cpuser'] ?? '');
}

echo "📋 5. TEST SSO/API (accountsummary + create_user_session):\n";
echo str_repeat("-", 60) . "\n";

if (empty($target_user)) {
    echo "  ⚠️ No se indicó usuario cPanel objetivo.\n";
    echo "  CLI: wp eval-file debug-whm-api.php zyncoclo\n";
    echo "  Web: .../debug-whm-api.php?cpuser=zyncoclo\n\n";
} else {
    echo "  Usuario cPanel objetivo: {$target_user}\n\n";

    foreach ($test_configs as $label => $config) {
        if (empty($config['ip']) || empty($config['token']) || empty($config['user'])) {
            echo "  {$label}: ⚠️ Configuración incompleta\n";
            continue;
        }

        echo "  {$label}:\n";

        $auth_header = "Authorization: whm {$config['user']}:{$config['token']}";

        // accountsummary (el endpoint que aparece en tu error de Upmind)
        $accountsummary_url = "https://{$config['ip']}:2087/json-api/accountsummary?api.version=1&user=" . urlencode($target_user);
        echo "    📡 accountsummary: {$accountsummary_url}\n";

        $ch = curl_init($accountsummary_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [$auth_header],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $resp3 = curl_exec($ch);
        $http3 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err3 = curl_error($ch);
        curl_close($ch);

        if ($resp3 === false) {
            echo "       ❌ cURL Error: {$err3}\n";
        } elseif ($http3 !== 200) {
            echo "       ❌ HTTP {$http3}\n";
            echo "       Respuesta: " . substr($resp3, 0, 300) . "\n";
        } else {
            $data3 = json_decode($resp3, true);
            $ok3 = !empty($data3['data']['acct']) || !empty($data3['acct']);
            if ($ok3) {
                echo "       ✅ HTTP 200 - accountsummary OK\n";
            } else {
                echo "       ⚠️ HTTP 200 pero sin datos de cuenta: " . substr($resp3, 0, 300) . "\n";
            }
        }

        // create_user_session (SSO a cPanel)
        $create_sso_url = "https://{$config['ip']}:2087/json-api/create_user_session?api.version=1&user=" . urlencode($target_user) . "&service=cpaneld";
        echo "    📡 create_user_session: {$create_sso_url}\n";

        $ch = curl_init($create_sso_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [$auth_header],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $resp4 = curl_exec($ch);
        $http4 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err4 = curl_error($ch);
        curl_close($ch);

        if ($resp4 === false) {
            echo "       ❌ cURL Error: {$err4}\n";
        } elseif ($http4 !== 200) {
            echo "       ❌ HTTP {$http4}\n";
            echo "       Respuesta: " . substr($resp4, 0, 300) . "\n";
        } else {
            $data4 = json_decode($resp4, true);
            $url4 = $data4['data']['url'] ?? $data4['url'] ?? '';
            if (!empty($url4)) {
                echo "       ✅ HTTP 200 - SSO URL generada\n";
                echo "       URL (recortada): " . substr($url4, 0, 120) . "...\n";
            } else {
                echo "       ⚠️ HTTP 200 pero sin URL SSO: " . substr($resp4, 0, 300) . "\n";
            }
        }

        echo "\n";
    }
}

// 6. Test usando la función del plugin
echo "📋 6. TEST USANDO obtener_cuentas_whm():\n";
echo str_repeat("-", 60) . "\n";

if (function_exists('obtener_cuentas_whm')) {
    foreach (['uk', 'usa'] as $server) {
        $token = $opts[$server . '_whm_token'] ?? '';
        if (empty($token)) {
            echo "  {$server}: ⚠️ Token no configurado\n";
            continue;
        }
        
        echo "  " . strtoupper($server) . ":\n";
        $start = microtime(true);
        $result = obtener_cuentas_whm($token, $server);
        $time = round((microtime(true) - $start) * 1000);
        
        if ($result === false) {
            echo "    ❌ Retornó FALSE ({$time}ms)\n";
            echo "    Revisar error_log para más detalles\n";
        } elseif (empty($result['data']['acct'])) {
            echo "    ❌ Sin cuentas ({$time}ms)\n";
            echo "    Estructura: " . json_encode(array_keys($result)) . "\n";
        } else {
            $count = count($result['data']['acct']);
            echo "    ✅ OK - {$count} cuentas ({$time}ms)\n";
        }
    }
} else {
    echo "  ❌ Función obtener_cuentas_whm() no existe\n";
}
echo "\n";

// 7. Info del servidor
echo "📋 7. INFO DEL SERVIDOR (este WordPress):\n";
echo str_repeat("-", 60) . "\n";
echo "  PHP: " . phpversion() . "\n";
echo "  cURL: " . (function_exists('curl_version') ? curl_version()['version'] : 'No disponible') . "\n";
echo "  OpenSSL: " . (defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : 'No disponible') . "\n";

// Obtener IP saliente
$ip_check = @file_get_contents('https://api.ipify.org?format=json');
if ($ip_check) {
    $ip_data = json_decode($ip_check, true);
    echo "  IP Saliente: " . ($ip_data['ip'] ?? 'desconocida') . "\n";
}
echo "\n";

echo "═══════════════════════════════════════════════════════════════\n";
echo "FIN DEL DIAGNÓSTICO\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "</pre>";
