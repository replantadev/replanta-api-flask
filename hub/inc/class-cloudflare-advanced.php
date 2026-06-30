<?php
/**
 * Integración con Cloudflare para gestión de CDN y seguridad
 * Maneja cache, seguridad, analytics y configuración de dominios
 */

if (!defined('ABSPATH')) {
    exit;
}

class RP_Hub_Cloudflare_Integration {
    
    private $api_key;
    private $api_email;
    private $api_url = 'https://api.cloudflare.com/client/v4';
    
    public function __construct() {
        $this->api_key = get_option('rphub_cloudflare_api_key', '');
        $this->api_email = get_option('rphub_cloudflare_api_email', '');
        
        // AJAX handlers
        add_action('wp_ajax_rphub_cloudflare_get_zone_info', array($this, 'ajax_get_zone_info'));
        add_action('wp_ajax_rphub_cloudflare_purge_cache', array($this, 'ajax_purge_cache'));
        add_action('wp_ajax_rphub_cloudflare_get_analytics', array($this, 'ajax_get_analytics'));
        add_action('wp_ajax_rphub_cloudflare_get_security_events', array($this, 'ajax_get_security_events'));
        add_action('wp_ajax_rphub_cloudflare_update_settings', array($this, 'ajax_update_settings'));
        add_action('wp_ajax_rphub_cloudflare_get_dns_records', array($this, 'ajax_get_dns_records'));
        
        // Cron jobs
        add_action('rphub_cloudflare_daily_sync', array($this, 'run_daily_cloudflare_sync'));
        add_action('rphub_cloudflare_analytics_sync', array($this, 'sync_analytics_data'));
        
        // Settings
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Registra configuraciones de Cloudflare
     */
    public function register_settings() {
        register_setting('rphub_settings', 'rphub_cloudflare_api_key');
        register_setting('rphub_settings', 'rphub_cloudflare_api_email');
        register_setting('rphub_settings', 'rphub_cloudflare_auto_purge');
        register_setting('rphub_settings', 'rphub_cloudflare_security_level');
        register_setting('rphub_settings', 'rphub_cloudflare_analytics_sync');
    }

    /**
     * Realiza llamada a la API de Cloudflare
     */
    private function cloudflare_api_call($method, $endpoint, $data = array()) {
        if (empty($this->api_key) || empty($this->api_email)) {
            throw new Exception('API de Cloudflare no configurada');
        }

        $url = $this->api_url . '/' . ltrim($endpoint, '/');
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'X-Auth-Email' => $this->api_email,
                'X-Auth-Key' => $this->api_key,
                'Content-Type' => 'application/json'
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

        if (!$data['success']) {
            $error_msg = 'Error de API';
            if (isset($data['errors'][0]['message'])) {
                $error_msg = $data['errors'][0]['message'];
            }
            throw new Exception($error_msg);
        }

        return $data['result'];
    }

    /**
     * Obtiene el ID de zona de Cloudflare para un dominio
     */
    private function get_zone_id($domain) {
        try {
            $zones = $this->cloudflare_api_call('GET', 'zones?name=' . urlencode($domain));
            
            if (!empty($zones)) {
                return $zones[0]['id'];
            }
            
            return null;
        } catch (Exception $e) {
            error_log('Cloudflare Zone ID Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene información completa de una zona
     */
    public function get_zone_info($site_id) {
        $site_url = RPHUB_Database::get_site($site_id)->url;
        $domain = parse_url($site_url, PHP_URL_HOST);
        
        // Remover www si existe
        $domain = preg_replace('/^www\./', '', $domain);
        
        $zone_id = $this->get_zone_id($domain);
        
        if (!$zone_id) {
            return null;
        }

        try {
            // Información básica de la zona
            $zone = $this->cloudflare_api_call('GET', "zones/{$zone_id}");
            
            // Analytics de la zona
            $analytics = $this->get_zone_analytics($zone_id, 7); // Últimos 7 días
            
            // Configuraciones de seguridad
            $security_settings = $this->get_security_settings($zone_id);
            
            // Configuraciones de rendimiento
            $performance_settings = $this->get_performance_settings($zone_id);
            
            return array(
                'zone' => $zone,
                'analytics' => $analytics,
                'security' => $security_settings,
                'performance' => $performance_settings
            );
            
        } catch (Exception $e) {
            error_log('Cloudflare Zone Info Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene analytics de la zona
     */
    private function get_zone_analytics($zone_id, $days = 7) {
        $since = date('Y-m-d\TH:i:s\Z', strtotime("-{$days} days"));
        $until = date('Y-m-d\TH:i:s\Z');
        
        try {
            $analytics = $this->cloudflare_api_call('GET', 
                "zones/{$zone_id}/analytics/dashboard?since={$since}&until={$until}&continuous=true"
            );
            
            return array(
                'requests' => array(
                    'total' => $analytics['totals']['requests']['all'] ?? 0,
                    'cached' => $analytics['totals']['requests']['cached'] ?? 0,
                    'uncached' => $analytics['totals']['requests']['uncached'] ?? 0
                ),
                'bandwidth' => array(
                    'total' => $analytics['totals']['bandwidth']['all'] ?? 0,
                    'cached' => $analytics['totals']['bandwidth']['cached'] ?? 0,
                    'uncached' => $analytics['totals']['bandwidth']['uncached'] ?? 0
                ),
                'threats' => array(
                    'total' => $analytics['totals']['threats']['all'] ?? 0,
                    'types' => $analytics['totals']['threats']['type'] ?? array()
                ),
                'uniques' => array(
                    'total' => $analytics['totals']['uniques']['all'] ?? 0
                ),
                'cache_hit_rate' => $this->calculate_cache_hit_rate($analytics),
                'period' => array(
                    'since' => $since,
                    'until' => $until,
                    'days' => $days
                )
            );
            
        } catch (Exception $e) {
            error_log('Cloudflare Analytics Error: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Calcula el hit rate del cache
     */
    private function calculate_cache_hit_rate($analytics) {
        $cached = $analytics['totals']['requests']['cached'] ?? 0;
        $total = $analytics['totals']['requests']['all'] ?? 0;
        
        if ($total > 0) {
            return round(($cached / $total) * 100, 2);
        }
        
        return 0;
    }

    /**
     * Obtiene configuraciones de seguridad
     */
    private function get_security_settings($zone_id) {
        try {
            $settings = array();
            
            // Security Level
            $security_level = $this->cloudflare_api_call('GET', "zones/{$zone_id}/settings/security_level");
            $settings['security_level'] = $security_level['value'];
            
            // Challenge Passage
            $challenge_ttl = $this->cloudflare_api_call('GET', "zones/{$zone_id}/settings/challenge_ttl");
            $settings['challenge_ttl'] = $challenge_ttl['value'];
            
            // Browser Integrity Check
            $browser_check = $this->cloudflare_api_call('GET', "zones/{$zone_id}/settings/browser_check");
            $settings['browser_check'] = $browser_check['value'];
            
            // WAF (Web Application Firewall)
            $waf = $this->cloudflare_api_call('GET', "zones/{$zone_id}/settings/waf");
            $settings['waf'] = $waf['value'];
            
            return $settings;
            
        } catch (Exception $e) {
            error_log('Cloudflare Security Settings Error: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Obtiene configuraciones de rendimiento
     */
    private function get_performance_settings($zone_id) {
        try {
            $settings = array();
            
            // Caching Level
            $caching_level = $this->cloudflare_api_call('GET', "zones/{$zone_id}/settings/caching_level");
            $settings['caching_level'] = $caching_level['value'];
            
            // Browser Cache TTL
            $browser_cache_ttl = $this->cloudflare_api_call('GET', "zones/{$zone_id}/settings/browser_cache_ttl");
            $settings['browser_cache_ttl'] = $browser_cache_ttl['value'];
            
            // Minification
            $minify = $this->cloudflare_api_call('GET', "zones/{$zone_id}/settings/minify");
            $settings['minify'] = $minify['value'];
            
            // Rocket Loader
            $rocket_loader = $this->cloudflare_api_call('GET', "zones/{$zone_id}/settings/rocket_loader");
            $settings['rocket_loader'] = $rocket_loader['value'];
            
            // Always Online
            $always_online = $this->cloudflare_api_call('GET', "zones/{$zone_id}/settings/always_online");
            $settings['always_online'] = $always_online['value'];
            
            return $settings;
            
        } catch (Exception $e) {
            error_log('Cloudflare Performance Settings Error: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Obtiene eventos de seguridad
     */
    public function get_security_events($site_id, $hours = 24) {
        $site_url = RPHUB_Database::get_site($site_id)->url;
        $domain = parse_url($site_url, PHP_URL_HOST);
        $domain = preg_replace('/^www\./', '', $domain);
        
        $zone_id = $this->get_zone_id($domain);
        
        if (!$zone_id) {
            return array();
        }

        $since = date('Y-m-d\TH:i:s\Z', strtotime("-{$hours} hours"));
        
        try {
            $events = $this->cloudflare_api_call('GET', 
                "zones/{$zone_id}/security/events?since={$since}&per_page=50"
            );
            
            $processed_events = array();
            
            foreach ($events as $event) {
                $processed_events[] = array(
                    'occurred_at' => $event['occurred_at'],
                    'action' => $event['action'],
                    'source' => array(
                        'ip' => $event['source']['ip'] ?? 'unknown',
                        'country' => $event['source']['country'] ?? 'unknown',
                        'asn' => $event['source']['asn'] ?? 'unknown'
                    ),
                    'ray_id' => $event['ray_id'],
                    'kind' => $event['kind'],
                    'match' => $event['match'] ?? array(),
                    'metadata' => $event['metadata'] ?? array()
                );
            }
            
            return $processed_events;
            
        } catch (Exception $e) {
            error_log('Cloudflare Security Events Error: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Actualiza información de Cloudflare para un sitio
     */
    public function update_site_cloudflare_info($site_id) {
        $zone_info = $this->get_zone_info($site_id);
        
        if (!$zone_info) {
            return false;
        }

        // Guardar información de zona
        RPHUB_Database::update_site_meta($site_id, 'cloudflare_zone_info', json_encode($zone_info['zone']));
        RPHUB_Database::update_site_meta($site_id, 'cloudflare_analytics', json_encode($zone_info['analytics']));
        RPHUB_Database::update_site_meta($site_id, 'cloudflare_security_settings', json_encode($zone_info['security']));
        RPHUB_Database::update_site_meta($site_id, 'cloudflare_performance_settings', json_encode($zone_info['performance']));
        
        // Métricas principales
        if (isset($zone_info['analytics'])) {
            $analytics = $zone_info['analytics'];
            RPHUB_Database::update_site_meta($site_id, 'cloudflare_cache_hit_rate', $analytics['cache_hit_rate']);
            RPHUB_Database::update_site_meta($site_id, 'cloudflare_total_requests', $analytics['requests']['total']);
            RPHUB_Database::update_site_meta($site_id, 'cloudflare_threats_blocked', $analytics['threats']['total']);
            RPHUB_Database::update_site_meta($site_id, 'cloudflare_bandwidth_saved', $analytics['bandwidth']['cached']);
        }
        
        // Eventos de seguridad recientes
        $security_events = $this->get_security_events($site_id, 24);
        RPHUB_Database::update_site_meta($site_id, 'cloudflare_security_events', json_encode($security_events));
        RPHUB_Database::update_site_meta($site_id, 'cloudflare_threats_last_24h', count($security_events));
        
        RPHUB_Database::update_site_meta($site_id, 'last_cloudflare_sync', current_time('mysql'));

        return true;
    }

    /**
     * Purga cache de Cloudflare
     */
    public function purge_cache($site_id, $type = 'everything') {
        $site_url = RPHUB_Database::get_site($site_id)->url;
        $domain = parse_url($site_url, PHP_URL_HOST);
        $domain = preg_replace('/^www\./', '', $domain);
        
        $zone_id = $this->get_zone_id($domain);
        
        if (!$zone_id) {
            throw new Exception('Zona de Cloudflare no encontrada');
        }

        $data = array();
        
        if ($type === 'everything') {
            $data['purge_everything'] = true;
        } elseif ($type === 'urls' && isset($_POST['urls'])) {
            $data['files'] = $_POST['urls'];
        }

        try {
            $result = $this->cloudflare_api_call('POST', "zones/{$zone_id}/purge_cache", $data);
            return $result;
        } catch (Exception $e) {
            throw new Exception('Error al purgar cache: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: Obtener información de zona
     */
    public function ajax_get_zone_info() {
        if (!wp_verify_nonce($_POST['nonce'], 'rphub_dashboard_nonce')) {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
            wp_send_json_error(['message' => 'Security check failed']); return;
        }

        $site_id = intval($_POST['site_id']);
        
        try {
            $zone_info = $this->get_zone_info($site_id);
            
            if ($zone_info) {
                wp_send_json_success($zone_info);
            } else {
                wp_send_json_error('Zona no encontrada en Cloudflare');
            }
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Purgar cache
     */
    public function ajax_purge_cache() {
        if (!wp_verify_nonce($_POST['nonce'], 'rphub_dashboard_nonce')) {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
            wp_send_json_error(['message' => 'Security check failed']); return;
        }

        $site_id = intval($_POST['site_id']);
        $type = sanitize_text_field($_POST['purge_type'] ?? 'everything');
        
        try {
            $result = $this->purge_cache($site_id, $type);
            
            wp_send_json_success(array(
                'message' => 'Cache purgado correctamente',
                'id' => $result['id'] ?? null
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Obtener analytics
     */
    public function ajax_get_analytics() {
        if (!wp_verify_nonce($_POST['nonce'], 'rphub_dashboard_nonce')) {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
            wp_send_json_error(['message' => 'Security check failed']); return;
        }

        $site_id = intval($_POST['site_id']);
        $days = intval($_POST['days'] ?? 7);
        
        $site_url = RPHUB_Database::get_site($site_id)->url;
        $domain = parse_url($site_url, PHP_URL_HOST);
        $domain = preg_replace('/^www\./', '', $domain);
        
        $zone_id = $this->get_zone_id($domain);
        
        if (!$zone_id) {
            wp_send_json_error('Zona no encontrada');
            return;
        }

        try {
            $analytics = $this->get_zone_analytics($zone_id, $days);
            wp_send_json_success($analytics);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Obtener eventos de seguridad
     */
    public function ajax_get_security_events() {
        if (!wp_verify_nonce($_POST['nonce'], 'rphub_dashboard_nonce')) {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
            wp_send_json_error(['message' => 'Security check failed']); return;
        }

        $site_id = intval($_POST['site_id']);
        $hours = intval($_POST['hours'] ?? 24);
        
        try {
            $events = $this->get_security_events($site_id, $hours);
            wp_send_json_success($events);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Sincronización diaria de Cloudflare
     */
    public function run_daily_cloudflare_sync() {
        $analytics_sync = get_option('rphub_cloudflare_analytics_sync', false);
        
        if (!$analytics_sync) {
            return;
        }

        // Obtener todos los sitios
        $sites = RPHUB_Database::get_all_sites();

        foreach ($sites as $site) {
            // Actualizar información de Cloudflare
            $this->update_site_cloudflare_info($site->id);
            
            // Esperar entre sitios para respetar rate limits
            sleep(2);
        }
    }

    /**
     * Sincronización de analytics
     */
    public function sync_analytics_data() {
        $this->run_daily_cloudflare_sync();
    }
}

// Inicializar la integración
new RP_Hub_Cloudflare_Integration();
