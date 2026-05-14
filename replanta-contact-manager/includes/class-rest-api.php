<?php
/**
 * REST API endpoints para recibir formularios
 */

if (!defined('ABSPATH')) exit;

class RCM_REST_API {
    
    private static $instance = null;
    const NAMESPACE = 'replanta/v1';
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('wp_enqueue_scripts', [$this, 'localize_nonce']);
    }
    
    public function register_routes() {
        // Endpoint para Plan Solidario
        register_rest_route(self::NAMESPACE, '/contact/solidario', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_solidario'],
            'permission_callback' => '__return_true',
        ]);
        
        // Endpoint para Auditoría
        register_rest_route(self::NAMESPACE, '/contact/auditoria', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_auditoria'],
            'permission_callback' => '__return_true',
        ]);
        
        // Endpoint para Migrar Ya
        register_rest_route(self::NAMESPACE, '/contact/migrar-ya', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_migrar_ya'],
            'permission_callback' => '__return_true',
        ]);
        
        // Endpoint genérico
        register_rest_route(self::NAMESPACE, '/contact/general', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_general'],
            'permission_callback' => '__return_true',
        ]);
    }
    
    public function localize_nonce() {
        if (function_exists('wp_get_current_user')) {
            wp_localize_script('jquery', 'replantaContactNonce', [
                'nonce'   => wp_create_nonce('wp_rest'),
                'sitekey' => defined('CF_TURNSTILE_SITEKEY') ? CF_TURNSTILE_SITEKEY : '',
            ]);
        }
    }
    
    /**
     * Handler para Plan Solidario
     */
    public function handle_solidario(WP_REST_Request $request) {
        $data = $request->get_json_params();
        
        // Mapear nombres de campos del formulario HTML a campos internos
        $email = $data['email'] ?? $data['org-email'] ?? null;
        $orgName = $data['entity_name'] ?? $data['org-name'] ?? null;
        $entityType = $data['entity_type'] ?? $data['org-type'] ?? null;
        $website = $data['website'] ?? $data['org-web'] ?? null;
        
        // Validar campos requeridos
        if (empty($email)) {
            return new WP_Error('missing_field', "Campo requerido: email", ['status' => 400]);
        }
        if (empty($orgName)) {
            return new WP_Error('missing_field', "Campo requerido: nombre de organización", ['status' => 400]);
        }
        if (empty($entityType)) {
            return new WP_Error('missing_field', "Campo requerido: tipo de proyecto", ['status' => 400]);
        }
        
        // Validaciones de seguridad
        $security = RCM_Security::instance();
        
        // 1. Verificar Turnstile primero (más importante para formularios públicos)
        $turnstile_token = $data['token'] ?? $data['turnstile_token'] ?? '';
        $turnstile_check = $security->verify_turnstile($turnstile_token);
        if (is_wp_error($turnstile_check)) {
            return $turnstile_check;
        }
        
        // Nonce opcional (solo si viene en header)
        $nonce_header = $request->get_header('X-WP-Nonce');
        if ($nonce_header && !wp_verify_nonce($nonce_header, 'wp_rest')) {
            return new WP_Error('invalid_nonce', 'Token inválido', ['status' => 403]);
        }
        
        // 2. Verificar honeypot
        $honeypot_check = $security->verify_honeypot($data);
        if (is_wp_error($honeypot_check)) {
            return $honeypot_check;
        }
        
        // 3. Verificar país
        $geo_check = $security->verify_country();
        if (!$geo_check['allowed']) {
            return new WP_Error('country_blocked', 'País no permitido', ['status' => 403]);
        }
        
        // 4. Rate limiting
        $rate_check = $security->check_rate_limit();
        if (is_wp_error($rate_check)) {
            return $rate_check;
        }
        
        // 6. Verificar duplicados
        $duplicate_check = $security->check_duplicate($email, 'solidario');
        if (is_wp_error($duplicate_check)) {
            return $duplicate_check;
        }
        
        // Crear solicitud
        $contact_data = [
            'type'       => 'solidario',
            'name'       => sanitize_text_field($orgName),
            'email'      => sanitize_email($email),
            'phone'      => sanitize_text_field($data['phone'] ?? ''),
            'message'    => sanitize_textarea_field($data['message'] ?? ''),
            'ip'         => $geo_check['ip'],
            'country'    => $geo_check['country'],
            'user_agent' => $security->get_user_agent(),
            'source'     => 'REST API - Plan Solidario',
            'data'       => [
                'entity_type' => sanitize_text_field($entityType),
                'entity_name' => sanitize_text_field($orgName),
                'website'     => esc_url_raw($website),
                'is_ong'      => !empty($data['ong']),
                'is_empresa'  => !empty($data['empresa']),
            ],
        ];
        
        $post_id = RCM_CPT::create_contact($contact_data);
        
        if (!$post_id) {
            return new WP_Error('creation_failed', 'Error al crear la solicitud', ['status' => 500]);
        }
        
        // Enviar email de notificación
        $this->send_notification($post_id, 'solidario');
        
        // Enviar a StaffKit si está configurado
        $this->send_to_staffkit($contact_data, 'solidario');
        
        return rest_ensure_response([
            'ok' => true,
            'success' => true,
            'message' => 'Solicitud recibida correctamente',
            'post_id' => $post_id,
        ]);
    }
    
    /**
     * Handler para Auditoría
     */
    public function handle_auditoria(WP_REST_Request $request) {
        $data = $request->get_json_params();
        
        // Validar campos requeridos
        $required = ['name', 'email', 'url'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return new WP_Error('missing_field', "Campo requerido: {$field}", ['status' => 400]);
            }
        }
        
        // Validaciones de seguridad (igual que solidario)
        $security = RCM_Security::instance();
        
        // 1. Verificar Turnstile primero
        $turnstile_token = $data['token'] ?? $data['turnstile_token'] ?? '';
        $turnstile_check = $security->verify_turnstile($turnstile_token);
        if (is_wp_error($turnstile_check)) {
            return $turnstile_check;
        }
        
        // Nonce opcional (solo si viene en header)
        $nonce_header = $request->get_header('X-WP-Nonce');
        if ($nonce_header && !wp_verify_nonce($nonce_header, 'wp_rest')) {
            return new WP_Error('invalid_nonce', 'Token inválido', ['status' => 403]);
        }
        
        $honeypot_check = $security->verify_honeypot($data);
        if (is_wp_error($honeypot_check)) {
            return $honeypot_check;
        }
        
        $geo_check = $security->verify_country();
        if (!$geo_check['allowed']) {
            return new WP_Error('country_blocked', 'País no permitido', ['status' => 403]);
        }
        
        $rate_check = $security->check_rate_limit();
        if (is_wp_error($rate_check)) {
            return $rate_check;
        }
        
        $duplicate_check = $security->check_duplicate($data['email'], 'auditoria');
        if (is_wp_error($duplicate_check)) {
            return $duplicate_check;
        }
        
        // Crear solicitud
        $contact_data = [
            'type'       => 'auditoria',
            'name'       => sanitize_text_field($data['name']),
            'email'      => sanitize_email($data['email']),
            'phone'      => sanitize_text_field($data['phone'] ?? ''),
            'message'    => sanitize_textarea_field($data['note'] ?? $data['message'] ?? ''),
            'ip'         => $geo_check['ip'],
            'country'    => $geo_check['country'],
            'user_agent' => $security->get_user_agent(),
            'source'     => 'REST API - Auditoría WP',
            'data'       => [
                'url' => esc_url_raw($data['url']),
            ],
        ];
        
        $post_id = RCM_CPT::create_contact($contact_data);
        
        if (!$post_id) {
            return new WP_Error('creation_failed', 'Error al crear la solicitud', ['status' => 500]);
        }
        
        $this->send_notification($post_id, 'auditoria');
        
        // Enviar a StaffKit si está configurado
        $this->send_to_staffkit($contact_data, 'auditoria');
        
        return rest_ensure_response([
            'ok' => true,
            'success' => true,
            'message' => 'Solicitud de auditoría recibida',
            'post_id' => $post_id,
        ]);
    }
    
    /**
     * Handler para Migrar Ya
     */
    public function handle_migrar_ya(WP_REST_Request $request) {
        $data = $request->get_json_params();
        
        // Validar campos requeridos
        $required = ['name', 'email'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return new WP_Error('missing_field', "Campo requerido: {$field}", ['status' => 400]);
            }
        }
        
        // Validaciones de seguridad
        $security = RCM_Security::instance();
        
        // 1. Verificar Turnstile
        $turnstile_token = $data['token'] ?? $data['turnstile_token'] ?? '';
        $turnstile_check = $security->verify_turnstile($turnstile_token);
        if (is_wp_error($turnstile_check)) {
            return $turnstile_check;
        }
        
        // Nonce opcional (solo si viene en header)
        $nonce_header = $request->get_header('X-WP-Nonce');
        if ($nonce_header && !wp_verify_nonce($nonce_header, 'wp_rest')) {
            return new WP_Error('invalid_nonce', 'Token inválido', ['status' => 403]);
        }
        
        // 2. Verificar honeypot
        $honeypot_check = $security->verify_honeypot($data);
        if (is_wp_error($honeypot_check)) {
            return $honeypot_check;
        }
        
        // 3. Verificar país
        $geo_check = $security->verify_country();
        if (!$geo_check['allowed']) {
            return new WP_Error('country_blocked', 'País no permitido', ['status' => 403]);
        }
        
        // 4. Rate limiting
        $rate_check = $security->check_rate_limit();
        if (is_wp_error($rate_check)) {
            return $rate_check;
        }
        
        // 5. Verificar duplicados
        $duplicate_check = $security->check_duplicate($data['email'], 'migrar-ya');
        if (is_wp_error($duplicate_check)) {
            return $duplicate_check;
        }
        
        // Crear solicitud
        $contact_data = [
            'type'       => 'migrar-ya',
            'name'       => sanitize_text_field($data['name']),
            'email'      => sanitize_email($data['email']),
            'phone'      => sanitize_text_field($data['phone'] ?? ''),
            'message'    => sanitize_textarea_field($data['message'] ?? ''),
            'ip'         => $geo_check['ip'],
            'country'    => $geo_check['country'],
            'user_agent' => $security->get_user_agent(),
            'source'     => 'REST API - Migrar Ya',
            'data'       => [
                'platform'     => sanitize_text_field($data['platform'] ?? 'no especificada'),
                'current_url'  => esc_url_raw($data['current_url'] ?? ''),
                'new_domain'   => sanitize_text_field($data['new_domain'] ?? ''),
                'urgency'      => sanitize_text_field($data['urgency'] ?? 'normal'),
            ],
        ];
        
        $post_id = RCM_CPT::create_contact($contact_data);
        
        if (!$post_id) {
            return new WP_Error('creation_failed', 'Error al crear la solicitud', ['status' => 500]);
        }
        
        // Enviar email de notificación
        $this->send_notification($post_id, 'migrar-ya');
        
        // Enviar a StaffKit si está configurado
        $this->send_to_staffkit($contact_data, 'migrar-ya');
        
        return rest_ensure_response([
            'ok' => true,
            'success' => true,
            'message' => 'Solicitud de migración recibida',
            'post_id' => $post_id,
        ]);
    }
    
    /**
     * Handler genérico
     */
    public function handle_general(WP_REST_Request $request) {
        $data = $request->get_json_params();
        
        $required = ['name', 'email'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return new WP_Error('missing_field', "Campo requerido: {$field}", ['status' => 400]);
            }
        }
        
        $security = RCM_Security::instance();
        
        if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
            return new WP_Error('invalid_nonce', 'Token inválido', ['status' => 403]);
        }
        
        $honeypot_check = $security->verify_honeypot($data);
        if (is_wp_error($honeypot_check)) {
            return $honeypot_check;
        }
        
        $geo_check = $security->verify_country();
        if (!$geo_check['allowed']) {
            return new WP_Error('country_blocked', 'País no permitido', ['status' => 403]);
        }
        
        $rate_check = $security->check_rate_limit();
        if (is_wp_error($rate_check)) {
            return $rate_check;
        }
        
        $turnstile_check = $security->verify_turnstile($data['turnstile_token'] ?? '');
        if (is_wp_error($turnstile_check)) {
            return $turnstile_check;
        }
        
        $duplicate_check = $security->check_duplicate($data['email'], 'contacto');
        if (is_wp_error($duplicate_check)) {
            return $duplicate_check;
        }
        
        $contact_data = [
            'type'       => 'contacto',
            'name'       => sanitize_text_field($data['name']),
            'email'      => sanitize_email($data['email']),
            'phone'      => sanitize_text_field($data['phone'] ?? ''),
            'message'    => sanitize_textarea_field($data['message'] ?? ''),
            'ip'         => $geo_check['ip'],
            'country'    => $geo_check['country'],
            'user_agent' => $security->get_user_agent(),
            'source'     => 'REST API - Contacto general',
        ];
        
        $post_id = RCM_CPT::create_contact($contact_data);
        
        if (!$post_id) {
            return new WP_Error('creation_failed', 'Error al crear la solicitud', ['status' => 500]);
        }
        
        $this->send_notification($post_id, 'contacto');
        
        return rest_ensure_response([
            'success' => true,
            'message' => 'Mensaje recibido',
            'post_id' => $post_id,
        ]);
    }
    
    /**
     * Enviar notificación por email
     */
    private function send_notification($post_id, $type) {
        $to = Replanta_Contact_Manager::get_option('email_to', get_option('admin_email'));
        
        $name = get_post_meta($post_id, '_rcm_name', true);
        $email = get_post_meta($post_id, '_rcm_email', true);
        $message = get_post_meta($post_id, '_rcm_message', true);
        
        $type_labels = [
            'solidario' => 'Plan Solidario',
            'auditoria' => 'Auditoría WordPress',
            'migrar-ya' => 'Migración Ya',
            'contacto'  => 'Contacto General',
        ];
        
        $subject = sprintf('[%s] Nueva solicitud de %s', get_bloginfo('name'), $type_labels[$type] ?? $type);
        
        $body = sprintf(
            "Nueva solicitud recibida:\n\n" .
            "Tipo: %s\n" .
            "Nombre: %s\n" .
            "Email: %s\n\n" .
            "Mensaje:\n%s\n\n" .
            "Ver en admin: %s",
            $type_labels[$type] ?? $type,
            $name,
            $email,
            $message,
            admin_url('post.php?post=' . $post_id . '&action=edit')
        );
        
        wp_mail($to, $subject, $body);
    }
    
    /**
     * Enviar lead a StaffKit
     */
    private function send_to_staffkit($contact_data, $type) {
        $staffkit_url = get_option('rcm_staffkit_url');
        $staffkit_key = get_option('rcm_staffkit_api_key');
        
        // Debug: log valores obtenidos
        error_log('RCM Debug send_to_staffkit: URL=' . ($staffkit_url ?: '[empty]') . ', Key=' . ($staffkit_key ? '***' . substr($staffkit_key, -4) : '[empty]'));
        
        // Solo enviar si StaffKit está configurado
        if (empty($staffkit_url) || empty($staffkit_key)) {
            error_log('RCM: Skipping StaffKit - URL or Key empty');
            return false;
        }
        
        // Mapear datos al formato de StaffKit
        $payload = [
            'email'      => $contact_data['email'],
            'name'       => $contact_data['name'],
            'company'    => $contact_data['company'] ?? null,
            'phone'      => $contact_data['phone'] ?? null,
            'website'    => $contact_data['data']['url'] ?? null,
            'source'     => $contact_data['source'],
            'source_url' => home_url($_SERVER['REQUEST_URI'] ?? ''),
            'user_agent' => $contact_data['user_agent'],
            'ip_address' => $contact_data['ip'],
            'metadata'   => [
                'type' => $type,
                'country' => $contact_data['country'],
                'extra_data' => $contact_data['data'] ?? []
            ]
        ];
        
        // Enviar a StaffKit webhook
        $response = wp_remote_post(rtrim($staffkit_url, '/') . '/api/webhooks/lead-capture.php', [
            'headers' => [
                'X-API-Key' => $staffkit_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($payload),
            'timeout' => 15
        ]);
        
        if (is_wp_error($response)) {
            error_log('RCM → StaffKit Error: ' . $response->get_error_message());
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            error_log('RCM → StaffKit HTTP ' . $code . ': ' . wp_remote_retrieve_body($response));
            return false;
        }
        
        return true;
    }
}
