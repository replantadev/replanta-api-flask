<?php
/**
 * REST API para verificación de dominios
 * Expone el endpoint /wp-json/replanta/v1/check_domain
 * para que el plugin sello-replanta pueda verificar si un dominio está alojado en Replanta
 */

if (!defined('ABSPATH')) exit;

/**
 * Registrar el endpoint REST API
 */
add_action('rest_api_init', function () {
    register_rest_route('replanta/v1', '/check_domain', array(
        'methods' => 'POST',
        'callback' => 'dominios_reseller_check_domain_callback',
        'permission_callback' => '__return_true', // Público, cualquiera puede consultar
        'args' => array(
            'domain' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function($param) {
                    // Validar formato de dominio
                    return !empty($param) && preg_match('/^[a-z0-9\-\.]+$/i', $param);
                }
            )
        )
    ));
});

/**
 * Callback para verificar si un dominio está en la base de datos
 * 
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function dominios_reseller_check_domain_callback($request) {
    global $wpdb;
    
    // Obtener el dominio del request
    $domain = $request->get_param('domain');
    
    // Limpiar el dominio (quitar www. si existe)
    $domain = preg_replace('/^www\./', '', strtolower($domain));
    
    // Log para debug
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[Dominios Reseller API] Verificando dominio: ' . $domain);
    }
    
    // Buscar en la base de datos
    $table = $wpdb->prefix . 'dominios_reseller';
    
    // Verificar si la tabla existe
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
    
    if (!$table_exists) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Dominios Reseller API] Tabla no existe: ' . $table);
        }
        return new WP_REST_Response(array(
            'hosted' => false,
            'error' => 'Database table not found'
        ), 200);
    }
    
    // Buscar el dominio en cualquier servidor (uk o usa)
    $result = $wpdb->get_row($wpdb->prepare(
        "SELECT domain, server, status, trees_planted, co2_evaded, fecha_emision, validez 
         FROM $table 
         WHERE domain = %s 
         ORDER BY CASE 
            WHEN status = 'Activo' THEN 1 
            WHEN status = 'Addon' THEN 2
            WHEN status = 'Suspendido' THEN 3 
            ELSE 4 
         END
         LIMIT 1",
        $domain
    ));
    
    if ($result) {
        // Dominio encontrado
        $hosted = true;
        
        // Solo considerar como "hosted" si está Activo o es Addon
        if ($result->status === 'Suspendido') {
            $hosted = false;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Dominios Reseller API] Dominio encontrado: ' . $domain . ' - Status: ' . $result->status . ' - Server: ' . $result->server);
        }
        
        return new WP_REST_Response(array(
            'hosted' => $hosted,
            'domain' => $result->domain,
            'server' => strtoupper($result->server),
            'status' => $result->status,
            'trees_planted' => (int)$result->trees_planted,
            'co2_evaded' => (float)$result->co2_evaded,
            'fecha_emision' => $result->fecha_emision,
            'validez' => $result->validez
        ), 200);
        
    } else {
        // Dominio no encontrado
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Dominios Reseller API] Dominio NO encontrado: ' . $domain);
        }
        
        return new WP_REST_Response(array(
            'hosted' => false,
            'domain' => $domain,
            'message' => 'Domain not found in our hosting database'
        ), 200);
    }
}

/**
 * Endpoint adicional para obtener estadísticas (opcional, para debug)
 */
add_action('rest_api_init', function () {
    register_rest_route('replanta/v1', '/stats', array(
        'methods' => 'GET',
        'callback' => 'dominios_reseller_stats_callback',
        'permission_callback' => function() {
            // Solo admins pueden ver estadísticas
            return current_user_can('manage_options');
        }
    ));
});

function dominios_reseller_stats_callback() {
    global $wpdb;
    $table = $wpdb->prefix . 'dominios_reseller';
    
    $total = $wpdb->get_var("SELECT COUNT(*) FROM $table");
    $active = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'Activo'");
    $suspended = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'Suspendido'");
    $addon = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'Addon'");
    $uk = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE server = 'uk'");
    $usa = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE server = 'usa'");
    $total_trees = $wpdb->get_var("SELECT SUM(trees_planted) FROM $table");
    $total_co2 = $wpdb->get_var("SELECT SUM(co2_evaded) FROM $table");
    
    return new WP_REST_Response(array(
        'total_domains' => (int)$total,
        'active' => (int)$active,
        'suspended' => (int)$suspended,
        'addon' => (int)$addon,
        'by_server' => array(
            'uk' => (int)$uk,
            'usa' => (int)$usa
        ),
        'environmental_impact' => array(
            'total_trees' => (int)$total_trees,
            'total_co2_evaded_kg' => (float)$total_co2
        )
    ), 200);
}
