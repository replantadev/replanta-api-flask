<?php
/**
 * Clase de Diagnósticos Avanzados para Replanta Hub
 * 
 * @package ReplantaHub
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPHUB_Diagnostics {
    
    private $results = [];
    private $errors = [];
    private $warnings = [];
    
    public function __construct() {
        // Constructor vacío
    }
    
    /**
     * Ejecuta todos los diagnósticos
     */
    public function run_full_diagnostics() {
        // Check cache first (5 minutes)
        $cache_key = 'rphub_diagnostics_cache';
        $cached_results = get_transient($cache_key);
        
        if ($cached_results && !isset($_GET['force_refresh'])) {
            $cached_results['cached'] = true;
            return $cached_results;
        }
        
        $this->results = [];
        $this->errors = [];
        $this->warnings = [];
        
        $this->check_environment();
        $this->check_plugin_structure();
        $this->check_dependencies();
        $this->check_database();
        $this->check_integrations();
        $this->check_ajax_handlers();
        $this->check_cron_jobs();
        $this->check_configuration();
        $this->check_file_permissions();
        $this->check_api_endpoints();
        
        $results = [
            'results' => $this->results,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'summary' => $this->generate_summary(),
            'timestamp' => current_time('mysql'),
            'cached' => false
        ];
        
        // Cache for 5 minutes
        set_transient($cache_key, $results, 300);
        
        return $results;
    }
    
    /**
     * Verifica el entorno de WordPress
     */
    private function check_environment() {
        $this->results['environment'] = [];
        
        // Versión de WordPress
        global $wp_version;
        $min_wp_version = '5.0';
        $wp_check = version_compare($wp_version, $min_wp_version, '>=');
        $this->results['environment']['wordpress_version'] = [
            'status' => $wp_check ? 'success' : 'error',
            'message' => "WordPress {$wp_version} (mínimo: {$min_wp_version})",
            'value' => $wp_version
        ];
        
        if (!$wp_check) {
            $this->errors[] = "Versión de WordPress muy antigua: {$wp_version}";
        }
        
        // Versión de PHP
        $min_php_version = '7.4';
        $php_check = version_compare(PHP_VERSION, $min_php_version, '>=');
        $this->results['environment']['php_version'] = [
            'status' => $php_check ? 'success' : 'error',
            'message' => "PHP " . PHP_VERSION . " (mínimo: {$min_php_version})",
            'value' => PHP_VERSION
        ];
        
        if (!$php_check) {
            $this->errors[] = "Versión de PHP muy antigua: " . PHP_VERSION;
        }
        
        // Extensiones PHP necesarias
        $required_extensions = ['curl', 'json', 'openssl', 'zip'];
        foreach ($required_extensions as $ext) {
            $loaded = extension_loaded($ext);
            $this->results['environment']["php_ext_{$ext}"] = [
                'status' => $loaded ? 'success' : 'error',
                'message' => "Extensión PHP {$ext}" . ($loaded ? ' cargada' : ' NO cargada'),
                'value' => $loaded
            ];
            
            if (!$loaded) {
                $this->errors[] = "Extensión PHP faltante: {$ext}";
            }
        }
        
        // Límites de memoria
        $memory_limit = ini_get('memory_limit');
        $this->results['environment']['memory_limit'] = [
            'status' => 'info',
            'message' => "Límite de memoria: {$memory_limit}",
            'value' => $memory_limit
        ];
        
        // Tiempo máximo de ejecución
        $max_execution_time = ini_get('max_execution_time');
        $this->results['environment']['max_execution_time'] = [
            'status' => $max_execution_time >= 30 ? 'success' : 'warning',
            'message' => "Tiempo máximo de ejecución: {$max_execution_time}s",
            'value' => $max_execution_time
        ];
    }
    
    /**
     * Verifica la estructura del plugin
     */
    private function check_plugin_structure() {
        $this->results['plugin_structure'] = [];
        
        // Archivos principales
        $main_files = [
            'replanta-hub.php' => 'Archivo principal del plugin',
            'inc/class-whm-integration.php' => 'Integración WHM',
            'inc/class-litespeed-integration.php' => 'Integración LiteSpeed',
            'inc/class-wptoolkit-integration.php' => 'Integración WP Toolkit',
            'inc/class-pagespeed-integration.php' => 'Integración PageSpeed',
            'inc/class-backuply-integration.php' => 'Integración Backuply',
            'inc/class-cloudflare-integration.php' => 'Integración Cloudflare',
            'assets/js/admin.js' => 'JavaScript del admin',
            'assets/css/admin.css' => 'CSS del admin'
        ];
        
        foreach ($main_files as $file => $description) {
            $path = RPHUB_PLUGIN_DIR . $file;
            $exists = file_exists($path);
            $this->results['plugin_structure'][$file] = [
                'status' => $exists ? 'success' : 'error',
                'message' => $description . ($exists ? ' encontrado' : ' NO encontrado'),
                'value' => $exists,
                'path' => $path
            ];
            
            if (!$exists) {
                $this->errors[] = "Archivo faltante: {$file}";
            }
        }
    }
    
    /**
     * Verifica las dependencias y clases
     */
    private function check_dependencies() {
        $this->results['dependencies'] = [];
        
        // Clases principales
        $required_classes = [
            'ReplantaHub' => 'Clase principal del plugin',
            'RPHUB_WHM_Integration' => 'Integración WHM',
            'ReplantaHub_LiteSpeed' => 'Integración LiteSpeed',
            'ReplantaHub_WPToolkit' => 'Integración WP Toolkit',
            'ReplantaHub_PageSpeed' => 'Integración PageSpeed',
            'ReplantaHub_Backuply' => 'Integración Backuply',
            'ReplantaHub_Cloudflare' => 'Integración Cloudflare'
        ];
        
        foreach ($required_classes as $class => $description) {
            $exists = class_exists($class);
            $this->results['dependencies'][$class] = [
                'status' => $exists ? 'success' : 'error',
                'message' => $description . ($exists ? ' cargada' : ' NO cargada'),
                'value' => $exists
            ];
            
            if (!$exists) {
                $this->errors[] = "Clase faltante: {$class}";
            }
        }
        
        // Verificar instancia principal
        if (class_exists('ReplantaHub')) {
            $instance = ReplantaHub::get_instance();
            $this->results['dependencies']['main_instance'] = [
                'status' => $instance ? 'success' : 'error',
                'message' => 'Instancia principal' . ($instance ? ' inicializada' : ' NO inicializada'),
                'value' => !is_null($instance)
            ];
        }
    }
    
    /**
     * Verifica la base de datos
     */
    private function check_database() {
        global $wpdb;
        $this->results['database'] = [];
        
        // Verificar conexión a la base de datos
        $db_connection = !empty($wpdb->dbh); // Más rápido que check_connection()
        $this->results['database']['connection'] = [
            'status' => $db_connection ? 'success' : 'error',
            'message' => 'Conexión a base de datos' . ($db_connection ? ' OK' : ' FALLA'),
            'value' => $db_connection
        ];
        
        // Verificar tablas del plugin con una sola consulta
        $plugin_tables = [
            'rphub_sites',
            'rphub_tasks', 
            'rphub_reports',
            'rphub_notifications'
        ];
        
        // Get all tables at once
        $existing_tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}rphub_%'");
        $existing_table_names = array_map(function($table) use ($wpdb) {
            return str_replace($wpdb->prefix, '', $table);
        }, $existing_tables);
        
        foreach ($plugin_tables as $table_name) {
            $exists = in_array($table_name, $existing_table_names);
            $this->results['database'][$table_name] = [
                'status' => $exists ? 'success' : 'warning',
                'message' => "Tabla {$table_name}" . ($exists ? ' existe' : ' NO existe'),
                'value' => $exists
            ];
            
            if (!$exists) {
                $this->warnings[] = "Tabla de base de datos faltante: {$table_name}";
            }
        }
    }
    
    /**
     * Verifica las integraciones
     */
    private function check_integrations() {
        $this->results['integrations'] = [];
        
        // Verificar cada integración
        $integrations = [
            'whm' => ['class' => 'RPHUB_WHM_Integration', 'name' => 'WHM'],
            'litespeed' => ['class' => 'ReplantaHub_LiteSpeed_Integration', 'name' => 'LiteSpeed'],
            'wptoolkit' => ['class' => 'ReplantaHub_WPToolkit_Integration', 'name' => 'WP Toolkit'],
            'pagespeed' => ['class' => 'ReplantaHub_PageSpeed_Integration', 'name' => 'PageSpeed'],
            'backuply' => ['class' => 'ReplantaHub_Backuply_Integration', 'name' => 'Backuply'],
            'cloudflare' => ['class' => 'ReplantaHub_Cloudflare', 'name' => 'Cloudflare']
        ];
        
        foreach ($integrations as $key => $config) {
            $class_exists = class_exists($config['class']);
            $configured = false;
            $can_connect = false;
            
            if ($class_exists) {
                try {
                    $instance = new $config['class']();
                    $configured = method_exists($instance, 'is_configured') ? $instance->is_configured() : true;
                    
                    // Skip connection tests for performance - only check if configured
                    // Connection tests can be done manually from the interface
                    
                } catch (Exception $e) {
                    $this->errors[] = "Error al instanciar {$config['name']}: " . $e->getMessage();
                }
            }
            
            $this->results['integrations'][$key] = [
                'class_exists' => $class_exists,
                'configured' => $configured,
                'can_connect' => null, // Disabled for performance
                'status' => $class_exists && $configured ? 'success' : 'warning',
                'message' => $config['name'] . ': ' . 
                           ($class_exists ? 'Clase cargada' : 'Clase NO cargada') . ', ' .
                           ($configured ? 'Configurada' : 'NO configurada') . 
                           ' (Test de conexión disponible en la interfaz)'
            ];
        }
    }
    
    /**
     * Verifica los handlers AJAX
     */
    private function check_ajax_handlers() {
        global $wp_filter;
        $this->results['ajax_handlers'] = [];
        
        $expected_handlers = [
            'wp_ajax_rphub_whm_test_connection' => 'Test conexión WHM',
            'wp_ajax_rphub_whm_run_diagnostics' => 'Diagnósticos WHM',
            'wp_ajax_rphub_whm_get_servers' => 'Listar servidores WHM',
            'wp_ajax_rphub_whm_test_server' => 'Test servidor WHM individual',
            'wp_ajax_rphub_get_dashboard_stats' => 'Estadísticas dashboard',
            'wp_ajax_rphub_get_sites_list' => 'Lista de sitios',
            'wp_ajax_rphub_test_site_connection' => 'Test conexión sitio',
            'wp_ajax_rphub_get_site_status' => 'Estado del sitio',
            'wp_ajax_rphub_bulk_action' => 'Acciones masivas',
            'wp_ajax_rphub_update_site_plan' => 'Actualizar plan sitio',
            'wp_ajax_rphub_remove_site' => 'Remover sitio'
        ];
        
        foreach ($expected_handlers as $action => $description) {
            $registered = isset($wp_filter[$action]) && !empty($wp_filter[$action]->callbacks);
            $this->results['ajax_handlers'][$action] = [
                'status' => $registered ? 'success' : 'error',
                'message' => $description . ($registered ? ' registrado' : ' NO registrado'),
                'value' => $registered
            ];
            
            if (!$registered) {
                $this->errors[] = "Handler AJAX no registrado: {$action}";
            }
        }
    }
    
    /**
     * Verifica los cron jobs
     */
    private function check_cron_jobs() {
        $this->results['cron_jobs'] = [];
        
        $expected_crons = [
            'rphub_daily_check' => 'Verificación diaria',
            'rphub_hourly_monitoring' => 'Monitoreo por horas',
            'rphub_litespeed_optimize' => 'Optimización LiteSpeed',
            'rphub_wptoolkit_vulnerability_scan' => 'Escaneo vulnerabilidades',
            'rphub_pagespeed_analysis' => 'Análisis PageSpeed',
            'rphub_backuply_check' => 'Verificación Backuply',
            'rphub_cloudflare_sync' => 'Sincronización Cloudflare'
        ];
        
        foreach ($expected_crons as $cron => $description) {
            $scheduled = wp_next_scheduled($cron);
            $this->results['cron_jobs'][$cron] = [
                'status' => $scheduled ? 'success' : 'warning',
                'message' => $description . ($scheduled ? ' programado' : ' NO programado'),
                'value' => $scheduled,
                'next_run' => $scheduled ? date('Y-m-d H:i:s', $scheduled) : 'N/A'
            ];
            
            if (!$scheduled) {
                $this->warnings[] = "Cron job no programado: {$cron}";
            }
        }
    }
    
    /**
     * Verifica la configuración
     */
    private function check_configuration() {
        $this->results['configuration'] = [];
        
        $config_options = [
            'rphub_whm_enabled' => 'WHM habilitado',
            'rphub_settings' => 'Configuración Hub (incl. WHM servers)',
            'rphub_api_key' => 'API Key del plugin',
            'rphub_managed_sites' => 'Sitios gestionados'
        ];
        
        foreach ($config_options as $option => $description) {
            $value = get_option($option);
            $is_set = !empty($value);
            $display_value = (strpos($option, 'password') !== false || strpos($option, 'token') !== false) && $is_set ? str_repeat('*', 8) : 
                           (is_array($value) ? count($value) . ' elementos' : substr((string) $value, 0, 50));
                           
            $this->results['configuration'][$option] = [
                'status' => $is_set ? 'success' : 'warning',
                'message' => $description . ($is_set ? ' configurado' : ' NO configurado'),
                'value' => $display_value
            ];
        }
    }
    
    /**
     * Verifica permisos de archivos
     */
    private function check_file_permissions() {
        $this->results['file_permissions'] = [];
        
        $important_dirs = [
            RPHUB_PLUGIN_DIR => 'Directorio del plugin',
            RPHUB_PLUGIN_DIR . 'inc/' => 'Directorio inc',
            RPHUB_PLUGIN_DIR . 'assets/' => 'Directorio assets',
            WP_CONTENT_DIR . '/uploads/' => 'Directorio uploads'
        ];
        
        foreach ($important_dirs as $dir => $description) {
            $readable = is_readable($dir);
            $writable = is_writable($dir);
            
            $this->results['file_permissions'][$dir] = [
                'status' => ($readable && $writable) ? 'success' : 'warning',
                'message' => $description . ' - ' . 
                           ($readable ? 'Lectura OK' : 'Sin lectura') . ', ' .
                           ($writable ? 'Escritura OK' : 'Sin escritura'),
                'readable' => $readable,
                'writable' => $writable
            ];
            
            if (!$readable || !$writable) {
                $this->warnings[] = "Permisos insuficientes en: {$dir}";
            }
        }
    }
    
    /**
     * Verifica endpoints de API
     */
    private function check_api_endpoints() {
        $this->results['api_endpoints'] = [];
        
        // Test de endpoint interno
        $internal_test = $this->test_internal_api();
        $this->results['api_endpoints']['internal'] = [
            'status' => $internal_test['success'] ? 'success' : 'error',
            'message' => 'API interna: ' . $internal_test['message'],
            'response_time' => $internal_test['response_time'] ?? 'N/A'
        ];
        
        // Test de conectividad externa
        $external_test = $this->test_external_connectivity();
        $this->results['api_endpoints']['external'] = [
            'status' => $external_test['success'] ? 'success' : 'warning',
            'message' => 'Conectividad externa: ' . $external_test['message'],
            'response_time' => $external_test['response_time'] ?? 'N/A'
        ];
    }
    
    /**
     * Test de API interna
     */
    private function test_internal_api() {
        $start_time = microtime(true);
        
        try {
            $response = wp_remote_get(admin_url('admin-ajax.php?action=heartbeat'), [
                'timeout' => 3, // Reducido de 10 a 3 segundos
                'sslverify' => true
            ]);
            
            $response_time = round((microtime(true) - $start_time) * 1000, 2) . 'ms';
            
            if (is_wp_error($response)) {
                return [
                    'success' => false,
                    'message' => 'Error: ' . $response->get_error_message(),
                    'response_time' => $response_time
                ];
            }
            
            $code = wp_remote_retrieve_response_code($response);
            return [
                'success' => $code === 200,
                'message' => "HTTP {$code}",
                'response_time' => $response_time
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Excepción: ' . $e->getMessage(),
                'response_time' => 'N/A'
            ];
        }
    }
    
    /**
     * Test de conectividad externa
     */
    private function test_external_connectivity() {
        $start_time = microtime(true);
        
        try {
            $response = wp_remote_get('https://httpstat.us/200', [ // URL más rápida
                'timeout' => 3, // Reducido de 10 a 3 segundos
                'sslverify' => true
            ]);
            
            $response_time = round((microtime(true) - $start_time) * 1000, 2) . 'ms';
            
            if (is_wp_error($response)) {
                return [
                    'success' => false,
                    'message' => 'Error: ' . $response->get_error_message(),
                    'response_time' => $response_time
                ];
            }
            
            $code = wp_remote_retrieve_response_code($response);
            return [
                'success' => $code === 200,
                'message' => "HTTP {$code} - Conectividad OK",
                'response_time' => $response_time
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Sin conectividad externa',
                'response_time' => 'N/A'
            ];
        }
    }
    
    /**
     * Genera un resumen de los diagnósticos
     */
    private function generate_summary() {
        $total_checks = 0;
        $passed_checks = 0;
        $critical_errors = count($this->errors);
        $warnings = count($this->warnings);
        
        foreach ($this->results as $section => $checks) {
            foreach ($checks as $check) {
                $total_checks++;
                if (isset($check['status']) && $check['status'] === 'success') {
                    $passed_checks++;
                }
            }
        }
        
        $health_score = $total_checks > 0 ? round(($passed_checks / $total_checks) * 100) : 0;
        
        $status = 'excellent';
        if ($critical_errors > 0) {
            $status = 'critical';
        } elseif ($warnings > 5 || $health_score < 80) {
            $status = 'warning';
        } elseif ($health_score < 95) {
            $status = 'good';
        }
        
        return [
            'health_score' => $health_score,
            'status' => $status,
            'total_checks' => $total_checks,
            'passed_checks' => $passed_checks,
            'critical_errors' => $critical_errors,
            'warnings' => $warnings,
            'recommendations' => $this->get_recommendations()
        ];
    }
    
    /**
     * Genera recomendaciones basadas en los resultados
     */
    private function get_recommendations() {
        $recommendations = [];
        
        if (count($this->errors) > 0) {
            $recommendations[] = [
                'type' => 'critical',
                'title' => 'Errores Críticos Detectados',
                'description' => 'Hay ' . count($this->errors) . ' errores críticos que impiden el funcionamiento del plugin.',
                'actions' => $this->errors
            ];
        }
        
        if (count($this->warnings) > 0) {
            $recommendations[] = [
                'type' => 'warning',
                'title' => 'Advertencias Encontradas',
                'description' => 'Hay ' . count($this->warnings) . ' advertencias que pueden afectar el rendimiento.',
                'actions' => array_slice($this->warnings, 0, 5) // Mostrar solo las primeras 5
            ];
        }
        
        // Recomendaciones específicas
        if (isset($this->results['integrations'])) {
            $unconfigured = 0;
            foreach ($this->results['integrations'] as $integration) {
                if (!$integration['configured']) {
                    $unconfigured++;
                }
            }
            
            if ($unconfigured > 0) {
                $recommendations[] = [
                    'type' => 'info',
                    'title' => 'Configurar Integraciones',
                    'description' => "Hay {$unconfigured} integraciones sin configurar.",
                    'actions' => ['Configurar credenciales en Replanta Hub → Configuración']
                ];
            }
        }
        
        return $recommendations;
    }
}
