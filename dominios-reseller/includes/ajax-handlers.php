<?php
// Seguridad: evitar acceso directo
if (!defined('ABSPATH')) exit;

/**
 * Seguridad: evita llamadas sin nonce válido
 * IMPORTANTE: Siempre verificar nonce Y capacidades del usuario
 */
function verificar_nonce_ajax() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'dominios_reseller_nonce')) {
        wp_send_json_error(['message' => 'Permiso denegado.']);
        exit;
    }
}

// SOLO registrar AJAX para usuarios autenticados (admin)
// NUNCA usar wp_ajax_nopriv_ para funciones administrativas
add_action('wp_ajax_actualizar_dominio', 'dominios_reseller_actualizar_dominio');
add_action('wp_ajax_recalcular_co2', 'dominios_reseller_recalcular_co2');
add_action('wp_ajax_dominios_reseller_recalcular_co2', 'dominios_reseller_recalcular_co2');

/**
 * AJAX: Actualiza árboles plantados y CO2 manualmente
 */
function dominios_reseller_actualizar_dominio()
{
    verificar_nonce_ajax();
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No autorizado.']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'dominios_reseller';

    $domain = sanitize_text_field($_POST['domain'] ?? '');
    $trees = intval($_POST['trees_planted'] ?? 0);
    $co2 = floatval($_POST['co2_evaded'] ?? 0);

    if (!$domain) {
        wp_send_json_error(['message' => 'Dominio no válido.']);
    }

    $updated = $wpdb->update($table, [
        'trees_planted' => $trees,
        'co2_evaded'    => $co2,
    ], ['domain' => $domain]);

    if ($updated !== false) {
        wp_send_json_success(['message' => 'Datos actualizados correctamente.']);
    } else {
        wp_send_json_error(['message' => 'Error al actualizar datos.']);
    }
}

/**
 * AJAX: Recalcula y guarda el CO2 evitado automáticamente usando API híbrida
 */
function dominios_reseller_recalcular_co2()
{
    verificar_nonce_ajax();
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No autorizado.']);
    }

    // Soportar tanto domain_id como domain (para nueva y vieja interfaz)
    $domain_id = isset($_POST['domain_id']) ? intval($_POST['domain_id']) : 0;
    $domain = sanitize_text_field($_POST['domain'] ?? '');
    $server = sanitize_text_field($_POST['server'] ?? 'uk');

    global $wpdb;
    $table = $wpdb->prefix . 'dominios_reseller';

    // Obtener datos del dominio (por ID o nombre)
    if ($domain_id > 0) {
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $domain_id));
    } elseif ($domain) {
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE domain = %s", $domain));
    } else {
        wp_send_json_error(['message' => 'Dominio no válido.']);
    }
    
    if (!$row) {
        wp_send_json_error(['message' => 'Dominio no encontrado en BD.']);
    }
    
    // Usar datos del registro
    $domain = $row->domain;
    $server = $row->server;

    // Calcular días y meses activos desde startdate o fecha_emision
    $fecha_inicio = null;
    if (!empty($row->startdate) && $row->startdate > 0) {
        $fecha_inicio = $row->startdate;
    } elseif (!empty($row->fecha_emision)) {
        $fecha_inicio = strtotime($row->fecha_emision);
    }
    
    if (!$fecha_inicio || $fecha_inicio > time()) {
        $fecha_inicio = strtotime('-30 days'); // Fallback: 1 mes
    }
    
    $dias_activo = max(1, round((time() - $fecha_inicio) / 86400));
    $meses_activos = max(1, round($dias_activo / 30.44)); // Promedio días/mes

    // Obtener tráfico total acumulado de WHM
    $opts = get_option('dominios_reseller_options');
    $token_key = $server . '_whm_token';
    $token = sanitize_text_field($opts[$token_key] ?? '');

    if (!$token) {
        wp_send_json_error(['message' => 'API token no configurado para ' . strtoupper($server)]);
    }

    $trafico_total_bytes = obtener_trafico_real($domain, $token, $server);

    if ($trafico_total_bytes === false) {
        // Si falla WHM, usar estimación del dominio principal si es addon
        if ($row->status === 'Addon' && !empty($row->primary_domain)) {
            $trafico_total_bytes = obtener_trafico_real($row->primary_domain, $token, $server);
            if ($trafico_total_bytes !== false) {
                // Estimar addon como 20% del tráfico principal
                $trafico_total_bytes = round($trafico_total_bytes * 0.2);
            }
        }
        
        if ($trafico_total_bytes === false) {
            wp_send_json_error(['message' => 'Error al obtener tráfico de WHM. Verifica la conexión con el servidor.']);
        }
    }
    
    // Calcular tráfico promedio mensual para estadísticas
    $trafico_mensual_promedio = $meses_activos > 0 ? round($trafico_total_bytes / $meses_activos) : $trafico_total_bytes;

    // Usar cálculo inteligente con API híbrida
    require_once plugin_dir_path(__FILE__) . 'emisiones-co2-api.php';
    
    $resultado = dr_calcular_co2_inteligente($domain, $trafico_total_bytes, $dias_activo, $server);

    // Guardar en BD (usando ID para mayor precisión)
    $updated = $wpdb->update($table, [
        'co2_evaded' => $resultado['co2_total_gramos']
    ], ['id' => $row->id], ['%f'], ['%d']);

    if ($updated !== false) {
        wp_send_json_success([
            'co2_evaded' => $resultado['co2_total_gramos'],
            'detalles' => array_merge($resultado, [
                'trafico_mensual_promedio_gb' => round($trafico_mensual_promedio / (1024**3), 2),
                'meses_activos' => $meses_activos,
                'dias_activo' => $dias_activo,
                'trafico_total_gb' => round($trafico_total_bytes / (1024**3), 2)
            ]),
            'message' => sprintf(
                'CO2 calculado: %.2f g acumulados en %d meses (Fuente: %s)',
                $resultado['co2_total_gramos'],
                $meses_activos,
                $resultado['fuente'] === 'website_carbon' ? 'Website Carbon API' : 'Cálculo local'
            )
        ]);
    } else {
        wp_send_json_error(['message' => 'Error al guardar en BD.']);
    }
}