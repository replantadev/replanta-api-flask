<?php
// Seguridad: Evita el acceso directo
if (!defined('ABSPATH')) exit;

/**
 * Calcula CO2 usando Website Carbon API
 * 
 * @param string $domain Dominio a analizar
 * @param int $trafico_bytes Tráfico mensual en bytes
 * @param int $dias_activo Días desde creación
 * @return array|false Array con datos o false si error
 */
function dr_calcular_co2_website_carbon($domain, $trafico_bytes, $dias_activo) {
    
    // 1. Verificar caché (24h)
    $cache_key = 'dr_co2_' . md5($domain);
    $cached = get_transient($cache_key);
    
    if ($cached !== false) {
        return $cached;
    }
    
    // 2. Limpiar dominio (sin http/https, sin www)
    $clean_domain = preg_replace('#^https?://(www\.)?#', '', $domain);
    $clean_domain = rtrim($clean_domain, '/');
    
    // 3. Llamar a Website Carbon API
    $url = 'https://api.websitecarbon.com/site?url=' . urlencode($clean_domain);
    
    $response = wp_remote_get($url, [
        'timeout' => 10,
        'headers' => [
            'User-Agent' => 'Replanta-Dominios-Reseller/1.5'
        ]
    ]);
    
    if (is_wp_error($response)) {
        error_log('Website Carbon API error: ' . $response->get_error_message());
        return false;
    }
    
    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code !== 200) {
        error_log("Website Carbon API returned status $status_code for $domain");
        return false;
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (!isset($data['statistics']['co2']['grid']['grams'])) {
        error_log('Website Carbon API: Invalid response structure for ' . $domain);
        return false;
    }
    
    // 4. Calcular emisiones totales
    $co2_por_vista = $data['statistics']['co2']['grid']['grams']; // CO2 por visita
    
    // Estimar visitas basadas en tráfico
    $bytes_promedio_por_vista = $data['bytes'] ?? 2000000; // ~2MB por defecto
    if ($bytes_promedio_por_vista < 100000) $bytes_promedio_por_vista = 2000000; // Mínimo 100KB
    
    $visitas_estimadas = $trafico_bytes > 0 ? $trafico_bytes / $bytes_promedio_por_vista : 0;
    
    // CO2 total = CO2 por visita × visitas estimadas
    $co2_total_trafico = $co2_por_vista * $visitas_estimadas;
    
    // Añadir emisiones base por hosting (servidor encendido 24/7)
    $co2_base_servidor = $dias_activo * 0.015; // 15mg por día (hosting renovable)
    
    $resultado = [
        'co2_trafico_gramos' => round($co2_total_trafico, 3),
        'co2_base_gramos' => round($co2_base_servidor, 3),
        'co2_total_gramos' => round($co2_total_trafico + $co2_base_servidor, 3),
        'visitas_estimadas' => round($visitas_estimadas),
        'bytes_por_vista' => $bytes_promedio_por_vista,
        'cleaner_than' => $data['cleanerThan'] ?? 0,
        'fuente' => 'website_carbon',
        'timestamp' => time()
    ];
    
    // 5. Cachear por 24 horas
    set_transient($cache_key, $resultado, DAY_IN_SECONDS);
    
    return $resultado;
}

/**
 * Fallback: Calcular CO2 con fórmula local (CO2.js style)
 * 
 * @param int $trafico_bytes Tráfico en bytes
 * @param int $dias_activo Días activo
 * @param string $server_location 'uk' o 'usa'
 * @return array Resultado del cálculo
 */
function dr_calcular_co2_local($trafico_bytes, $dias_activo, $server_location = 'uk') {
    
    // Grid intensity (gramos CO2 por kWh) - Datos 2024
    $grid_intensity = [
        'uk' => 233,   // Reino Unido: 233g CO2/kWh
        'usa' => 417   // USA promedio: 417g CO2/kWh
    ];
    
    $intensity = $grid_intensity[$server_location] ?? 300;
    
    // Sustainable Web Design Model (simplificado)
    // kWh por GB transferido = 0.81 kWh/GB (promedio datacenter + red + dispositivos)
    $kwh_por_gb = 0.81;
    $trafico_gb = $trafico_bytes / (1024 ** 3);
    
    // CO2 del tráfico = GB × kWh/GB × grid intensity (g/kWh)
    // Resultado en gramos
    $co2_trafico = $trafico_gb * $kwh_por_gb * $intensity;
    
    // CO2 base del servidor (hosting 24/7)
    // Asumimos hosting renovable: 15mg/día
    $co2_base = $dias_activo * 0.015; // gramos
    
    $resultado = [
        'co2_trafico_gramos' => round($co2_trafico, 3),
        'co2_base_gramos' => round($co2_base, 3),
        'co2_total_gramos' => round($co2_trafico + $co2_base, 3),
        'trafico_gb' => round($trafico_gb, 3),
        'grid_intensity' => $intensity,
        'fuente' => 'local_calculation',
        'timestamp' => time()
    ];
    
    return $resultado;
}

/**
 * Función principal: Intenta API, fallback a local
 * 
 * @param string $domain Dominio
 * @param int $trafico_bytes Tráfico en bytes
 * @param int $dias_activo Días activo
 * @param string $server_location Ubicación servidor ('uk' o 'usa')
 * @return array Resultado del cálculo
 */
function dr_calcular_co2_inteligente($domain, $trafico_bytes, $dias_activo, $server_location = 'uk') {
    
    // 1. Intentar con Website Carbon API
    $resultado_api = dr_calcular_co2_website_carbon($domain, $trafico_bytes, $dias_activo);
    
    if ($resultado_api !== false) {
        return $resultado_api;
    }
    
    // 2. Fallback a cálculo local
    error_log("Website Carbon API falló para $domain, usando cálculo local");
    return dr_calcular_co2_local($trafico_bytes, $dias_activo, $server_location);
}

/**
 * Limpiar caché de CO2 para un dominio
 * 
 * @param string $domain Dominio
 * @return bool Success
 */
function dr_limpiar_cache_co2($domain) {
    $cache_key = 'dr_co2_' . md5($domain);
    return delete_transient($cache_key);
}
