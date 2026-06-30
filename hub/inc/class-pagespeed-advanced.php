<?php
/**
 * Integración con PageSpeed Insights API
 * Maneja análisis de rendimiento y Core Web Vitals
 */

if (!defined('ABSPATH')) {
    exit;
}

class RP_Hub_PageSpeed_Integration {
    
    private $api_key;
    private $api_url = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
    
    public function __construct() {
        $this->api_key = get_option('rphub_pagespeed_api_key', '');
        
        // AJAX handlers
        add_action('wp_ajax_rphub_site_action', array($this, 'handle_site_action'));
        add_action('wp_ajax_rphub_run_pagespeed_test', array($this, 'ajax_run_pagespeed_test'));
        add_action('wp_ajax_rphub_get_pagespeed_history', array($this, 'ajax_get_pagespeed_history'));
        
        // Cron jobs
        add_action('rphub_daily_pagespeed_check', array($this, 'run_daily_pagespeed_check'));
        
        // Settings
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Registra configuraciones de PageSpeed
     */
    public function register_settings() {
        register_setting('rphub_settings', 'rphub_pagespeed_api_key');
        register_setting('rphub_settings', 'rphub_pagespeed_auto_check');
        register_setting('rphub_settings', 'rphub_pagespeed_threshold_mobile');
        register_setting('rphub_settings', 'rphub_pagespeed_threshold_desktop');
    }

    /**
     * Maneja acciones AJAX del sitio
     */
    public function handle_site_action() {
        if (!wp_verify_nonce($_POST['nonce'], 'rphub_dashboard_nonce')) {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
            wp_send_json_error(['message' => 'Security check failed']); return;
        }

        $action = sanitize_text_field($_POST['site_action']);
        $site_id = intval($_POST['site_id']);

        switch ($action) {
            case 'run-pagespeed':
                return $this->run_pagespeed_analysis($site_id);
            case 'refresh-data':
                return $this->refresh_site_data($site_id);
        }

        wp_send_json_error('Acción no reconocida');
    }

    /**
     * Ejecuta análisis de PageSpeed para un sitio
     */
    public function run_pagespeed_analysis($site_id) {
        if (empty($this->api_key)) {
            wp_send_json_error('API Key de PageSpeed no configurada');
            return;
        }

        $site = RPHUB_Database::get_site($site_id);
        if (empty($site) || empty($site->url)) {
            wp_send_json_error('URL del sitio no encontrada');
            return;
        }
        $site_url = $site->url;

        try {
            // Análisis móvil
            $mobile_result = $this->analyze_url($site_url, 'mobile');
            $mobile_score = $this->extract_performance_score($mobile_result);
            $mobile_vitals = $this->extract_core_web_vitals($mobile_result);

            // Análisis desktop
            $desktop_result = $this->analyze_url($site_url, 'desktop');
            $desktop_score = $this->extract_performance_score($desktop_result);
            $desktop_vitals = $this->extract_core_web_vitals($desktop_result);

            // Guardar resultados
            RPHUB_Database::update_site_meta($site_id, 'pagespeed_mobile', $mobile_score);
            RPHUB_Database::update_site_meta($site_id, 'pagespeed_desktop', $desktop_score);
            RPHUB_Database::update_site_meta($site_id, 'core_web_vitals', json_encode(array(
                'mobile' => $mobile_vitals,
                'desktop' => $desktop_vitals
            )));
            RPHUB_Database::update_site_meta($site_id, 'last_pagespeed_check', current_time('mysql'));

            // Guardar historial
            $this->save_pagespeed_history($site_id, array(
                'mobile_score' => $mobile_score,
                'desktop_score' => $desktop_score,
                'mobile_vitals' => $mobile_vitals,
                'desktop_vitals' => $desktop_vitals,
                'timestamp' => current_time('mysql')
            ));

            // Generar recomendaciones
            $recommendations = $this->generate_recommendations($mobile_result, $desktop_result);
            RPHUB_Database::update_site_meta($site_id, 'pagespeed_recommendations', json_encode($recommendations));

            wp_send_json_success(array(
                'mobile_score' => $mobile_score,
                'desktop_score' => $desktop_score,
                'avg_score' => round(($mobile_score + $desktop_score) / 2),
                'recommendations' => $recommendations
            ));

        } catch (Exception $e) {
            error_log('PageSpeed Analysis Error: ' . $e->getMessage());
            wp_send_json_error('Error al analizar el sitio: ' . $e->getMessage());
        }
    }

    /**
     * Analiza una URL con PageSpeed Insights
     */
    private function analyze_url($url, $strategy = 'mobile') {
        $request_url = add_query_arg(array(
            'url' => urlencode($url),
            'key' => $this->api_key,
            'strategy' => $strategy,
            'category' => 'performance',
            'locale' => 'es'
        ), $this->api_url);

        $response = wp_remote_get($request_url, array(
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'Replanta Hub PageSpeed Analyzer'
            )
        ));

        if (is_wp_error($response)) {
            throw new Exception('Error de conexión: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['error'])) {
            throw new Exception('Error de API: ' . $data['error']['message']);
        }

        return $data;
    }

    /**
     * Extrae el score de performance
     */
    private function extract_performance_score($result) {
        if (isset($result['lighthouseResult']['categories']['performance']['score'])) {
            return round($result['lighthouseResult']['categories']['performance']['score'] * 100);
        }
        return 0;
    }

    /**
     * Extrae Core Web Vitals
     */
    private function extract_core_web_vitals($result) {
        $vitals = array();
        
        if (isset($result['lighthouseResult']['audits'])) {
            $audits = $result['lighthouseResult']['audits'];
            
            // Largest Contentful Paint
            if (isset($audits['largest-contentful-paint'])) {
                $vitals['lcp'] = array(
                    'value' => $audits['largest-contentful-paint']['numericValue'] ?? 0,
                    'displayValue' => $audits['largest-contentful-paint']['displayValue'] ?? 'N/A',
                    'score' => $audits['largest-contentful-paint']['score'] ?? 0
                );
            }

            // First Input Delay (CLS en PageSpeed)
            if (isset($audits['cumulative-layout-shift'])) {
                $vitals['cls'] = array(
                    'value' => $audits['cumulative-layout-shift']['numericValue'] ?? 0,
                    'displayValue' => $audits['cumulative-layout-shift']['displayValue'] ?? 'N/A',
                    'score' => $audits['cumulative-layout-shift']['score'] ?? 0
                );
            }

            // First Contentful Paint
            if (isset($audits['first-contentful-paint'])) {
                $vitals['fcp'] = array(
                    'value' => $audits['first-contentful-paint']['numericValue'] ?? 0,
                    'displayValue' => $audits['first-contentful-paint']['displayValue'] ?? 'N/A',
                    'score' => $audits['first-contentful-paint']['score'] ?? 0
                );
            }

            // Time to Interactive
            if (isset($audits['interactive'])) {
                $vitals['tti'] = array(
                    'value' => $audits['interactive']['numericValue'] ?? 0,
                    'displayValue' => $audits['interactive']['displayValue'] ?? 'N/A',
                    'score' => $audits['interactive']['score'] ?? 0
                );
            }

            // Total Blocking Time
            if (isset($audits['total-blocking-time'])) {
                $vitals['tbt'] = array(
                    'value' => $audits['total-blocking-time']['numericValue'] ?? 0,
                    'displayValue' => $audits['total-blocking-time']['displayValue'] ?? 'N/A',
                    'score' => $audits['total-blocking-time']['score'] ?? 0
                );
            }
        }

        return $vitals;
    }

    /**
     * Genera recomendaciones basadas en el análisis
     */
    private function generate_recommendations($mobile_result, $desktop_result) {
        $recommendations = array();
        
        // Analizar oportunidades de mejora
        $opportunities = array();
        
        if (isset($mobile_result['lighthouseResult']['audits'])) {
            $opportunities = array_merge($opportunities, $this->extract_opportunities($mobile_result['lighthouseResult']['audits'], 'mobile'));
        }
        
        if (isset($desktop_result['lighthouseResult']['audits'])) {
            $opportunities = array_merge($opportunities, $this->extract_opportunities($desktop_result['lighthouseResult']['audits'], 'desktop'));
        }

        // Priorizar recomendaciones
        usort($opportunities, function($a, $b) {
            return $b['impact'] <=> $a['impact'];
        });

        return array_slice($opportunities, 0, 10); // Top 10 recomendaciones
    }

    /**
     * Extrae oportunidades de mejora
     */
    private function extract_opportunities($audits, $device) {
        $opportunities = array();
        
        $key_audits = array(
            'render-blocking-resources' => array('title' => 'Eliminar recursos que bloquean el renderizado', 'impact' => 9),
            'unused-css-rules' => array('title' => 'Quitar CSS sin usar', 'impact' => 8),
            'unused-javascript' => array('title' => 'Reducir JavaScript sin usar', 'impact' => 8),
            'efficient-animated-content' => array('title' => 'Usar formatos de video para contenido animado', 'impact' => 7),
            'offscreen-images' => array('title' => 'Diferir imágenes fuera de pantalla', 'impact' => 7),
            'unminified-css' => array('title' => 'Minificar CSS', 'impact' => 6),
            'unminified-javascript' => array('title' => 'Minificar JavaScript', 'impact' => 6),
            'uses-optimized-images' => array('title' => 'Servir imágenes en formatos de próxima generación', 'impact' => 6),
            'uses-text-compression' => array('title' => 'Habilitar compresión de texto', 'impact' => 5),
            'uses-responsive-images' => array('title' => 'Servir imágenes con tamaño adecuado', 'impact' => 5)
        );

        foreach ($key_audits as $audit_key => $audit_info) {
            if (isset($audits[$audit_key]) && isset($audits[$audit_key]['details']['overallSavingsMs'])) {
                $savings = $audits[$audit_key]['details']['overallSavingsMs'];
                if ($savings > 0) {
                    $opportunities[] = array(
                        'title' => $audit_info['title'],
                        'impact' => $audit_info['impact'],
                        'savings_ms' => $savings,
                        'savings_display' => round($savings / 1000, 1) . 's',
                        'device' => $device,
                        'description' => $audits[$audit_key]['title'] ?? $audit_info['title']
                    );
                }
            }
        }

        return $opportunities;
    }

    /**
     * Guarda historial de PageSpeed
     */
    private function save_pagespeed_history($site_id, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rphub_pagespeed_history';
        
        // Crear tabla si no existe
        $this->create_history_table();
        
        $wpdb->insert(
            $table_name,
            array(
                'site_id' => $site_id,
                'mobile_score' => $data['mobile_score'],
                'desktop_score' => $data['desktop_score'],
                'mobile_vitals' => json_encode($data['mobile_vitals']),
                'desktop_vitals' => json_encode($data['desktop_vitals']),
                'created_at' => $data['timestamp']
            ),
            array('%d', '%d', '%d', '%s', '%s', '%s')
        );
    }

    /**
     * Crea tabla de historial
     */
    private function create_history_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rphub_pagespeed_history';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            site_id int(11) NOT NULL,
            mobile_score int(3) DEFAULT NULL,
            desktop_score int(3) DEFAULT NULL,
            mobile_vitals longtext,
            desktop_vitals longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY site_id (site_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * AJAX: Obtener historial de PageSpeed
     */
    public function ajax_get_pagespeed_history() {
        if (!wp_verify_nonce($_POST['nonce'], 'rphub_dashboard_nonce')) {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
            wp_send_json_error(['message' => 'Security check failed']); return;
        }

        $site_id = intval($_POST['site_id']);
        $limit = intval($_POST['limit'] ?? 30);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'rphub_pagespeed_history';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE site_id = %d ORDER BY created_at DESC LIMIT %d",
            $site_id,
            $limit
        ));

        if ($results) {
            foreach ($results as &$result) {
                $result->mobile_vitals = json_decode($result->mobile_vitals, true);
                $result->desktop_vitals = json_decode($result->desktop_vitals, true);
            }
        }

        wp_send_json_success($results);
    }

    /**
     * Chequeo diario de PageSpeed
     */
    public function run_daily_pagespeed_check() {
        $auto_check = get_option('rphub_pagespeed_auto_check', false);
        
        if (!$auto_check || empty($this->api_key)) {
            return;
        }

        // Obtener sitios activos
        $sites = RPHUB_Database::get_all_sites('connected');

        foreach ($sites as $site) {
            // Evitar sobrecargar la API
            wp_schedule_single_event(time() + wp_rand(1, 3600), 'rphub_analyze_site_pagespeed', array($site->id));
        }
    }

    /**
     * Refrescar datos del sitio
     */
    private function refresh_site_data($site_id) {
        // Ejecutar múltiples verificaciones
        $this->run_pagespeed_analysis($site_id);
        
        wp_send_json_success('Datos actualizados correctamente');
    }
}

// Inicializar la integración
new RP_Hub_PageSpeed_Integration();

// Action para análisis individual programado
add_action('rphub_analyze_site_pagespeed', function($site_id) {
    $integration = new RP_Hub_PageSpeed_Integration();
    $integration->run_pagespeed_analysis($site_id);
});
