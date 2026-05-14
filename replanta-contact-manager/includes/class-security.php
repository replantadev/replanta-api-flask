<?php
/**
 * Módulo de seguridad (Turnstile, Geo-blocking, Rate limiting, Honeypot)
 */

if (!defined('ABSPATH')) exit;

class RCM_Security {
    
    private static $instance = null;
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {}
    
    /**
     * Verificar Cloudflare Turnstile
     */
    public function verify_turnstile($token) {
        if (!Replanta_Contact_Manager::get_option('turnstile_enabled', 1)) {
            return true;
        }
        
        $secret = defined('CF_TURNSTILE_SECRET') ? CF_TURNSTILE_SECRET : '';
        
        if (empty($secret)) {
            return true; // Si no hay secret, no verificar
        }
        
        if (empty($token)) {
            return new WP_Error('turnstile_missing', 'Token de verificación requerido');
        }
        
        $response = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'body' => [
                'secret'   => $secret,
                'response' => $token,
            ],
            'timeout' => 10,
        ]);
        
        if (is_wp_error($response)) {
            return new WP_Error('turnstile_error', 'Error al verificar Turnstile');
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['success']) || !$body['success']) {
            $error_codes = $body['error-codes'] ?? [];
            return new WP_Error('turnstile_failed', 'Verificación Turnstile fallida: ' . implode(', ', $error_codes));
        }
        
        return true;
    }
    
    /**
     * Verificar país
     */
    public function verify_country($ip = null) {
        if (!Replanta_Contact_Manager::get_option('geo_enabled', 1)) {
            return ['allowed' => true, 'country' => 'XX'];
        }
        
        if ($ip === null) {
            $ip = $this->get_client_ip();
        }
        
        $country = $this->get_country_code($ip);
        $allowed_countries = Replanta_Contact_Manager::get_option('geo_allowed_countries', ['ES', 'AD', 'MX', 'AR', 'CO', 'CL', 'PE']);
        
        $is_allowed = in_array($country, $allowed_countries, true);
        
        return [
            'allowed' => $is_allowed,
            'country' => $country,
            'ip'      => $ip,
        ];
    }
    
    /**
     * Rate limiting
     */
    public function check_rate_limit($ip = null) {
        if ($ip === null) {
            $ip = $this->get_client_ip();
        }
        
        $max = Replanta_Contact_Manager::get_option('rate_limit_max', 5);
        $window = Replanta_Contact_Manager::get_option('rate_limit_window', 15) * 60; // convertir a segundos
        
        $transient_key = 'rcm_rate_' . md5($ip);
        $attempts = get_transient($transient_key) ?: 0;
        
        if ($attempts >= $max) {
            return new WP_Error('rate_limit', 'Demasiadas solicitudes. Inténtalo más tarde.');
        }
        
        set_transient($transient_key, $attempts + 1, $window);
        
        return true;
    }
    
    /**
     * Verificar honeypot
     */
    public function verify_honeypot($data) {
        $field = Replanta_Contact_Manager::get_option('honeypot_field', 'fax_number');
        
        if (isset($data[$field]) && !empty($data[$field])) {
            return new WP_Error('honeypot', 'Bot detectado');
        }
        
        return true;
    }
    
    /**
     * Verificar duplicados
     */
    public function check_duplicate($email, $type = null, $hours = 24) {
        $args = [
            'post_type'      => RCM_CPT::POST_TYPE,
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'date_query'     => [
                [
                    'after' => $hours . ' hours ago',
                ],
            ],
            'meta_query' => [
                [
                    'key'     => '_rcm_email',
                    'value'   => $email,
                    'compare' => '=',
                ],
            ],
        ];
        
        if ($type) {
            $args['tax_query'] = [
                [
                    'taxonomy' => RCM_CPT::TAXONOMY,
                    'field'    => 'slug',
                    'terms'    => $type,
                ],
            ];
        }
        
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            return new WP_Error('duplicate', 'Ya existe una solicitud reciente con este email');
        }
        
        return true;
    }
    
    /**
     * Obtener IP del cliente
     */
    private function get_client_ip() {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = sanitize_text_field(wp_unslash($_SERVER[$header]));
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Obtener código de país
     */
    private function get_country_code($ip) {
        // Primero intentar header de Cloudflare
        if (!empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
            return sanitize_text_field($_SERVER['HTTP_CF_IPCOUNTRY']);
        }
        
        // Cache
        $cache_key = 'rcm_geo_' . md5($ip);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        // Fallback a ip-api.com
        $response = wp_remote_get("http://ip-api.com/json/{$ip}?fields=countryCode", [
            'timeout' => 3,
        ]);
        
        if (is_wp_error($response)) {
            return 'XX';
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $country = $body['countryCode'] ?? 'XX';
        
        set_transient($cache_key, $country, 24 * HOUR_IN_SECONDS);
        
        return $country;
    }
    
    /**
     * Obtener User Agent
     */
    public function get_user_agent() {
        return !empty($_SERVER['HTTP_USER_AGENT']) 
            ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) 
            : '';
    }
}
