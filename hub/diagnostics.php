<?php
/**
 * Diagnóstico completo de Replanta Hub
 * 
 * Script para verificar todas las conexiones y configuraciones
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

function rphub_run_comprehensive_diagnostics() {
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos para ejecutar diagnósticos');
    }
    
    echo '<div class="wrap">';
    echo '<h1> Diagnóstico Completo de Replanta Hub</h1>';
    echo '<div style="font-family: monospace; background: #f1f1f1; padding: 20px; border-radius: 8px;">';
    
    // 1. Verificar carga de clases
    echo '<h2> 1. Verificación de Clases</h2>';
    $classes = [
        'RPHUB_WHM_Integration' => 'WHM Integration',
        'ReplantaHub_LiteSpeed_Integration' => 'LiteSpeed Integration',
        'ReplantaHub_WPToolkit_Integration' => 'WP Toolkit Integration',
        'ReplantaHub_PageSpeed_Integration' => 'PageSpeed Integration',
        'ReplantaHub_Backuply_Integration' => 'Backuply Integration',
        'ReplantaHub_Cloudflare_Integration' => 'Cloudflare Integration'
    ];
    
    foreach ($classes as $class => $name) {
        $exists = class_exists($class);
        echo '<p>' . ($exists ? '' : '') . ' ' . $name . ': ' . ($exists ? 'Cargada' : 'NO encontrada') . '</p>';
    }
    
    // 2. Verificar handlers AJAX
    echo '<h2> 2. Verificación de Handlers AJAX</h2>';
    $ajax_actions = [
        'rphub_test_whm_connection',
        'rphub_whm_run_diagnostics',
        'rphub_litespeed_test_connection',
        'rphub_wptoolkit_scan_vulnerabilities',
        'rphub_pagespeed_analyze',
        'rphub_backuply_create_backup',
        'rphub_cloudflare_purge_cache'
    ];
    
    global $wp_filter;
    foreach ($ajax_actions as $action) {
        $registered = isset($wp_filter['wp_ajax_' . $action]);
        echo '<p>' . ($registered ? '' : '') . ' wp_ajax_' . $action . ': ' . ($registered ? 'Registrado' : 'NO registrado') . '</p>';
    }
    
    // 3. Verificar configuraciones
    echo '<h2> 3. Verificación de Configuraciones</h2>';
    $settings = get_option('rphub_settings', []);
    
    echo '<h3>WHM Configuration:</h3>';
    echo '<p>URL: ' . (!empty($settings['whm_url']) ? ' Configurado' : ' No configurado') . '</p>';
    echo '<p>Usuario: ' . (!empty($settings['whm_username']) ? ' Configurado' : ' No configurado') . '</p>';
    echo '<p>Token: ' . (!empty($settings['whm_api_token']) ? ' Configurado' : ' No configurado') . '</p>';
    
    // 4. Test de conexiones
    echo '<h2> 4. Test de Conexiones</h2>';
    
    // Test WHM
    if (class_exists('RPHUB_WHM_Integration')) {
        $whm = new RPHUB_WHM_Integration();
        if ($whm->is_configured()) {
            echo '<p> Probando conexión WHM...</p>';
            $test = $whm->get_accounts();
            if (is_wp_error($test)) {
                echo '<p> WHM: ' . $test->get_error_message() . '</p>';
            } else {
                echo '<p> WHM: Conexión exitosa (' . (is_array($test) ? count($test) : 0) . ' cuentas)</p>';
            }
        } else {
            echo '<p> WHM: No configurado</p>';
        }
    }
    
    // Test LiteSpeed
    if (class_exists('ReplantaHub_LiteSpeed_Integration')) {
        $litespeed = new ReplantaHub_LiteSpeed_Integration();
        echo '<p> Probando conexión LiteSpeed...</p>';
        $test = $litespeed->test_connection();
        if (is_wp_error($test)) {
            echo '<p> LiteSpeed: ' . $test->get_error_message() . '</p>';
        } else {
            echo '<p> LiteSpeed: ' . $test['message'] . '</p>';
        }
    }
    
    // 5. Verificar JavaScript y CSS
    echo '<h2> 5. Verificación de Assets</h2>';
    $js_file = RPHUB_PLUGIN_DIR . 'assets/js/admin.js';
    $css_file = RPHUB_PLUGIN_DIR . 'assets/css/admin.css';
    
    echo '<p>' . (file_exists($js_file) ? '' : '') . ' admin.js: ' . (file_exists($js_file) ? 'Existe' : 'NO existe') . '</p>';
    echo '<p>' . (file_exists($css_file) ? '' : '') . ' admin.css: ' . (file_exists($css_file) ? 'Existe' : 'NO existe') . '</p>';
    
    // 6. Verificar base de datos
    echo '<h2> 6. Verificación de Base de Datos</h2>';
    global $wpdb;
    
    $tables = [
        'rphub_sites',
        'rphub_reports',
        'rphub_notifications',
        'rphub_tasks'
    ];
    
    foreach ($tables as $table) {
        $full_table = $wpdb->prefix . $table;
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table'") === $full_table;
        echo '<p>' . ($exists ? '' : '') . ' Tabla ' . $table . ': ' . ($exists ? 'Existe' : 'NO existe') . '</p>';
    }
    
    // 7. Verificar cron jobs
    echo '<h2> 7. Verificación de Cron Jobs</h2>';
    $cron_jobs = [
        'rphub_daily_check',
        'rphub_hourly_monitoring',
        'rphub_litespeed_optimize',
        'rphub_wptoolkit_vulnerability_scan'
    ];
    
    foreach ($cron_jobs as $job) {
        $scheduled = wp_next_scheduled($job);
        echo '<p>' . ($scheduled ? '' : '') . ' ' . $job . ': ' . ($scheduled ? 'Programado para ' . date('Y-m-d H:i:s', $scheduled) : 'NO programado') . '</p>';
    }
    
    // 8. Test de permisos
    echo '<h2> 8. Verificación de Permisos</h2>';
    echo '<p>Usuario actual: ' . wp_get_current_user()->user_login . '</p>';
    echo '<p>Permiso manage_options: ' . (current_user_can('manage_options') ? ' Sí' : ' No') . '</p>';
    
    // 9. Información del entorno
    echo '<h2> 9. Información del Entorno</h2>';
    echo '<p>WordPress: ' . get_bloginfo('version') . '</p>';
    echo '<p>PHP: ' . PHP_VERSION . '</p>';
    echo '<p>Plugin Version: ' . RPHUB_VERSION . '</p>';
    echo '<p>Plugin Dir: ' . RPHUB_PLUGIN_DIR . '</p>';
    echo '<p>Plugin URL: ' . RPHUB_PLUGIN_URL . '</p>';
    
    // 10. Recomendaciones
    echo '<h2> 10. Recomendaciones</h2>';
    echo '<ul>';
    
    if (!class_exists('RPHUB_WHM_Integration')) {
        echo '<li> Verificar que class-whm-integration.php esté en la carpeta inc/</li>';
    }
    
    if (empty($settings['whm_url'])) {
        echo '<li> Configurar credenciales WHM en Configuración → WHM</li>';
    }
    
    if (!file_exists($js_file)) {
        echo '<li> Verificar que admin.js esté en assets/js/</li>';
    }
    
    echo '<li> Verificar consola del navegador para errores JavaScript</li>';
    echo '<li> Usar las herramientas de desarrollador para debuggear AJAX</li>';
    echo '</ul>';
    
    echo '</div>';
    
    // Botón para test en vivo
    echo '<div style="margin-top: 20px;">';
    echo '<h3> Test en Vivo</h3>';
    echo '<button type="button" class="button button-primary" onclick="testWHMConnectionLive()">Probar Conexión WHM</button>';
    echo '<div id="live-test-results" style="margin-top: 10px;"></div>';
    echo '</div>';
    
    echo '<script>
    function testWHMConnectionLive() {
        const resultsDiv = document.getElementById("live-test-results");
        resultsDiv.innerHTML = " Probando conexión...";
        
        fetch(ajaxurl, {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: "action=rphub_test_whm_connection&nonce=' . wp_create_nonce('rphub_ajax') . '"
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                resultsDiv.innerHTML = " " + data.data.message;
            } else {
                resultsDiv.innerHTML = " Error: " + data.data;
            }
        })
        .catch(error => {
            resultsDiv.innerHTML = " Error de red: " + error;
            console.error("Error:", error);
        });
    }
    </script>';
    
    echo '</div>';
}

// Nota: La página de diagnóstico se maneja desde replanta-hub.php para evitar duplicación
// add_action('admin_menu', function() {
//     add_submenu_page(
//         'replanta-hub',
//         'Diagnósticos',
//         'Diagnósticos',
//         'manage_options',
//         'replanta-hub-diagnostics',
//         'rphub_run_comprehensive_diagnostics'
//     );
// });
