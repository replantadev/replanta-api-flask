<?php
/**
 * Integración con WP Toolkit para gestión de WordPress
 * Maneja actualizaciones, plugins, themes y mantenimiento
 */

if (!defined('ABSPATH')) {
    exit;
}

class RP_Hub_WPToolkit_Integration {
    
    private $plesk_api_url;
    private $plesk_api_key;
    
    public function __construct() {
        $this->plesk_api_url = get_option('rphub_plesk_api_url', '');
        $this->plesk_api_key = get_option('rphub_plesk_api_key', '');
        
        // AJAX handlers
        add_action('wp_ajax_rphub_wptoolkit_get_sites', array($this, 'ajax_get_wptoolkit_sites'));
        add_action('wp_ajax_rphub_wptoolkit_update_plugins', array($this, 'ajax_update_plugins'));
        add_action('wp_ajax_rphub_wptoolkit_update_themes', array($this, 'ajax_update_themes'));
        add_action('wp_ajax_rphub_wptoolkit_update_core', array($this, 'ajax_update_core'));
        add_action('wp_ajax_rphub_wptoolkit_scan_security', array($this, 'ajax_scan_security'));
        add_action('wp_ajax_rphub_wptoolkit_maintenance_mode', array($this, 'ajax_maintenance_mode'));
        
        // Cron jobs
        add_action('rphub_wptoolkit_daily_check', array($this, 'run_daily_wptoolkit_check'));
        add_action('rphub_wptoolkit_vulnerability_scan', array($this, 'run_vulnerability_scan'));
        
        // Settings
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Registra configuraciones de WP Toolkit
     */
    public function register_settings() {
        register_setting('rphub_settings', 'rphub_plesk_api_url');
        register_setting('rphub_settings', 'rphub_plesk_api_key');
        register_setting('rphub_settings', 'rphub_wptoolkit_auto_updates');
        register_setting('rphub_settings', 'rphub_wptoolkit_security_scan');
    }

    /**
     * Realiza llamada a la API de Plesk
     */
    private function plesk_api_call($method, $endpoint, $data = array()) {
        if (empty($this->plesk_api_url) || empty($this->plesk_api_key)) {
            throw new Exception('API de Plesk no configurada');
        }

        $url = rtrim($this->plesk_api_url, '/') . '/api/v2/' . ltrim($endpoint, '/');
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->plesk_api_key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ),
            'timeout' => 30
        );

        if (!empty($data) && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            throw new Exception('Error de conexión: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code >= 400) {
            throw new Exception('Error de API: ' . ($data['message'] ?? 'Error desconocido'));
        }

        return $data;
    }

    /**
     * Obtiene información de sitios desde WP Toolkit
     */
    public function get_wptoolkit_sites() {
        try {
            $response = $this->plesk_api_call('GET', 'wp-instances');
            return $response['data'] ?? array();
        } catch (Exception $e) {
            error_log('WP Toolkit Error: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Obtiene información detallada de un sitio
     */
    public function get_site_details($site_url) {
        try {
            $sites = $this->get_wptoolkit_sites();
            
            foreach ($sites as $site) {
                if (isset($site['url']) && $site['url'] === $site_url) {
                    // Obtener detalles adicionales
                    $site_id = $site['id'];
                    $details = $this->plesk_api_call('GET', "wp-instances/{$site_id}");
                    
                    return array_merge($site, $details);
                }
            }
            
            return null;
        } catch (Exception $e) {
            error_log('WP Toolkit Site Details Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Actualiza información de un sitio en el Custom Post Type
     */
    public function update_site_info($site_id) {
        $site_data = RPHUB_Database::get_site($site_id);
        $site_url = $site_data ? $site_data->url : '';
        
        if (empty($site_url)) {
            return false;
        }

        $wptoolkit_data = $this->get_site_details($site_url);
        
        if (!$wptoolkit_data) {
            return false;
        }

        // Actualizar información técnica
        if (isset($wptoolkit_data['wp_version'])) {
            RPHUB_Database::update_site_meta($site_id, 'wp_version', $wptoolkit_data['wp_version']);
        }

        if (isset($wptoolkit_data['php_version'])) {
            RPHUB_Database::update_site_meta($site_id, 'php_version', $wptoolkit_data['php_version']);
        }

        // Plugins
        if (isset($wptoolkit_data['plugins'])) {
            $plugins_info = array(
                'total' => count($wptoolkit_data['plugins']),
                'active' => 0,
                'updates_available' => 0,
                'list' => array()
            );

            foreach ($wptoolkit_data['plugins'] as $plugin) {
                if ($plugin['status'] === 'active') {
                    $plugins_info['active']++;
                }
                
                if (isset($plugin['update_available']) && $plugin['update_available']) {
                    $plugins_info['updates_available']++;
                }

                $plugins_info['list'][] = array(
                    'name' => $plugin['name'],
                    'version' => $plugin['version'],
                    'status' => $plugin['status'],
                    'update_available' => $plugin['update_available'] ?? false,
                    'new_version' => $plugin['new_version'] ?? null
                );
            }

            RPHUB_Database::update_site_meta($site_id, 'plugins_info', json_encode($plugins_info));
        }

        // Themes
        if (isset($wptoolkit_data['themes'])) {
            $themes_info = array(
                'total' => count($wptoolkit_data['themes']),
                'active' => 0,
                'updates_available' => 0,
                'list' => array()
            );

            foreach ($wptoolkit_data['themes'] as $theme) {
                if ($theme['status'] === 'active') {
                    $themes_info['active']++;
                }
                
                if (isset($theme['update_available']) && $theme['update_available']) {
                    $themes_info['updates_available']++;
                }

                $themes_info['list'][] = array(
                    'name' => $theme['name'],
                    'version' => $theme['version'],
                    'status' => $theme['status'],
                    'update_available' => $theme['update_available'] ?? false,
                    'new_version' => $theme['new_version'] ?? null
                );
            }

            RPHUB_Database::update_site_meta($site_id, 'themes_info', json_encode($themes_info));
        }

        // Estado de seguridad
        if (isset($wptoolkit_data['security'])) {
            $security_info = array(
                'scan_date' => $wptoolkit_data['security']['last_scan'] ?? null,
                'vulnerabilities' => $wptoolkit_data['security']['vulnerabilities'] ?? array(),
                'security_plugins' => $wptoolkit_data['security']['security_plugins'] ?? array(),
                'file_integrity' => $wptoolkit_data['security']['file_integrity'] ?? 'unknown'
            );

            RPHUB_Database::update_site_meta($site_id, 'security_info', json_encode($security_info));
            
            // Calcular score de seguridad
            $security_score = $this->calculate_security_score($security_info);
            RPHUB_Database::update_site_meta($site_id, 'security_score', $security_score);
        }

        // Información del servidor
        if (isset($wptoolkit_data['server'])) {
            $server_info = array(
                'web_server' => $wptoolkit_data['server']['web_server'] ?? 'unknown',
                'database_version' => $wptoolkit_data['server']['database_version'] ?? 'unknown',
                'disk_usage' => $wptoolkit_data['server']['disk_usage'] ?? 0,
                'database_size' => $wptoolkit_data['server']['database_size'] ?? 0
            );

            RPHUB_Database::update_site_meta($site_id, 'server_info', json_encode($server_info));
            RPHUB_Database::update_site_meta($site_id, 'mysql_version', $server_info['database_version']);
        }

        RPHUB_Database::update_site_meta($site_id, 'last_wptoolkit_sync', current_time('mysql'));

        return true;
    }

    /**
     * Calcula score de seguridad basado en la información disponible
     */
    private function calculate_security_score($security_info) {
        $score = 100;

        // Restar puntos por vulnerabilidades
        if (isset($security_info['vulnerabilities'])) {
            $vuln_count = count($security_info['vulnerabilities']);
            $score -= min($vuln_count * 15, 60); // Máximo -60 puntos
        }

        // Verificar plugins de seguridad
        if (empty($security_info['security_plugins'])) {
            $score -= 20;
        }

        // Verificar integridad de archivos
        if (isset($security_info['file_integrity']) && $security_info['file_integrity'] === 'compromised') {
            $score -= 30;
        }

        return max($score, 0);
    }

    /**
     * AJAX: Obtener sitios de WP Toolkit
     */
    public function ajax_get_wptoolkit_sites() {
        if (!wp_verify_nonce($_POST['nonce'], 'rphub_dashboard_nonce')) {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
            wp_send_json_error(['message' => 'Security check failed']); return;
        }

        try {
            $sites = $this->get_wptoolkit_sites();
            wp_send_json_success($sites);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Actualizar plugins
     */
    public function ajax_update_plugins() {
        if (!wp_verify_nonce($_POST['nonce'], 'rphub_dashboard_nonce')) {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
            wp_send_json_error(['message' => 'Security check failed']); return;
        }

        $site_id = intval($_POST['site_id']);
        $site_data = RPHUB_Database::get_site($site_id);
        $site_url = $site_data ? $site_data->url : '';

        try {
            // Obtener ID del sitio en WP Toolkit
            $sites = $this->get_wptoolkit_sites();
            $wptoolkit_site_id = null;

            foreach ($sites as $site) {
                if ($site['url'] === $site_url) {
                    $wptoolkit_site_id = $site['id'];
                    break;
                }
            }

            if (!$wptoolkit_site_id) {
                throw new Exception('Sitio no encontrado en WP Toolkit');
            }

            // Ejecutar actualización de plugins
            $result = $this->plesk_api_call('POST', "wp-instances/{$wptoolkit_site_id}/plugins/update-all");

            // Actualizar información del sitio
            $this->update_site_info($site_id);

            wp_send_json_success(array(
                'message' => 'Plugins actualizados correctamente',
                'updated_count' => $result['updated_count'] ?? 0
            ));

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Actualizar themes
     */
    public function ajax_update_themes() {
        if (!wp_verify_nonce($_POST['nonce'], 'rphub_dashboard_nonce')) {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
            wp_send_json_error(['message' => 'Security check failed']); return;
        }

        $site_id = intval($_POST['site_id']);
        $site_data = RPHUB_Database::get_site($site_id);
        $site_url = $site_data ? $site_data->url : '';

        try {
            // Similar a update_plugins pero para themes
            $sites = $this->get_wptoolkit_sites();
            $wptoolkit_site_id = null;

            foreach ($sites as $site) {
                if ($site['url'] === $site_url) {
                    $wptoolkit_site_id = $site['id'];
                    break;
                }
            }

            if (!$wptoolkit_site_id) {
                throw new Exception('Sitio no encontrado en WP Toolkit');
            }

            $result = $this->plesk_api_call('POST', "wp-instances/{$wptoolkit_site_id}/themes/update-all");
            $this->update_site_info($site_id);

            wp_send_json_success(array(
                'message' => 'Themes actualizados correctamente',
                'updated_count' => $result['updated_count'] ?? 0
            ));

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Actualizar core de WordPress
     */
    public function ajax_update_core() {
        if (!wp_verify_nonce($_POST['nonce'], 'rphub_dashboard_nonce')) {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
            wp_send_json_error(['message' => 'Security check failed']); return;
        }

        $site_id = intval($_POST['site_id']);
        $site_data = RPHUB_Database::get_site($site_id);
        $site_url = $site_data ? $site_data->url : '';

        try {
            $sites = $this->get_wptoolkit_sites();
            $wptoolkit_site_id = null;

            foreach ($sites as $site) {
                if ($site['url'] === $site_url) {
                    $wptoolkit_site_id = $site['id'];
                    break;
                }
            }

            if (!$wptoolkit_site_id) {
                throw new Exception('Sitio no encontrado en WP Toolkit');
            }

            $result = $this->plesk_api_call('POST', "wp-instances/{$wptoolkit_site_id}/core/update");
            $this->update_site_info($site_id);

            wp_send_json_success(array(
                'message' => 'WordPress actualizado correctamente',
                'new_version' => $result['new_version'] ?? 'unknown'
            ));

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Escaneo de seguridad
     */
    public function ajax_scan_security() {
        if (!wp_verify_nonce($_POST['nonce'], 'rphub_dashboard_nonce')) {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
            wp_send_json_error(['message' => 'Security check failed']); return;
        }

        $site_id = intval($_POST['site_id']);
        $site_data = RPHUB_Database::get_site($site_id);
        $site_url = $site_data ? $site_data->url : '';

        try {
            $sites = $this->get_wptoolkit_sites();
            $wptoolkit_site_id = null;

            foreach ($sites as $site) {
                if ($site['url'] === $site_url) {
                    $wptoolkit_site_id = $site['id'];
                    break;
                }
            }

            if (!$wptoolkit_site_id) {
                throw new Exception('Sitio no encontrado en WP Toolkit');
            }

            $result = $this->plesk_api_call('POST', "wp-instances/{$wptoolkit_site_id}/security/scan");
            $this->update_site_info($site_id);

            wp_send_json_success(array(
                'message' => 'Escaneo de seguridad completado',
                'vulnerabilities_found' => $result['vulnerabilities_count'] ?? 0,
                'scan_id' => $result['scan_id'] ?? null
            ));

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Chequeo diario de WP Toolkit
     */
    public function run_daily_wptoolkit_check() {
        $auto_updates = get_option('rphub_wptoolkit_auto_updates', false);
        
        if (!$auto_updates) {
            return;
        }

        // Obtener todos los sitios
        $sites = RPHUB_Database::get_all_sites();

        foreach ($sites as $site) {
            // Actualizar información
            $this->update_site_info($site->id);
            
            // Esperar entre sitios para no sobrecargar la API
            sleep(2);
        }
    }

    /**
     * Escaneo de vulnerabilidades programado
     */
    public function run_vulnerability_scan() {
        $security_scan = get_option('rphub_wptoolkit_security_scan', false);
        
        if (!$security_scan) {
            return;
        }

        // Obtener sitios activos
        $sites = RPHUB_Database::get_all_sites();
        $sites = array_filter($sites, function($site) {
            return RPHUB_Database::get_site_meta($site->id, 'connection_status') === 'connected';
        });

        foreach ($sites as $site) {
            wp_schedule_single_event(time() + wp_rand(1, 1800), 'rphub_scan_site_security', array($site->id));
        }
    }
}

// Inicializar la integración
new RP_Hub_WPToolkit_Integration();

// Action para escaneo de seguridad programado
add_action('rphub_scan_site_security', function($site_id) {
    $integration = new RP_Hub_WPToolkit_Integration();
    $integration->update_site_info($site_id);
});
