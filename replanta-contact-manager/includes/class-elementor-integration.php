<?php
/**
 * Integración con Elementor Forms
 * Captura automáticamente todos los envíos de formularios Elementor
 */

if (!defined('ABSPATH')) exit;

class RCM_Elementor_Integration {
    
    private static $instance = null;
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Hook cuando Elementor Pro está activo
        add_action('elementor_pro/forms/new_record', [$this, 'capture_form_submission'], 10, 2);
    }
    
    /**
     * Capturar envío de formulario Elementor
     */
    public function capture_form_submission($record, $handler) {
        // Verificar si la captura está habilitada
        if (!Replanta_Contact_Manager::get_option('elementor_capture', 1)) {
            return;
        }
        
        // Obtener datos del formulario
        $form_name = $record->get_form_settings('form_name');
        $form_id = $record->get_form_settings('id');
        
        // Verificar si este formulario está excluido
        $excluded_forms = Replanta_Contact_Manager::get_option('elementor_exclude_forms', []);
        if (in_array($form_id, $excluded_forms)) {
            return;
        }
        
        // Extraer campos del formulario
        $raw_fields = $record->get('fields');
        $fields = [];
        
        foreach ($raw_fields as $id => $field) {
            $fields[$field['id']] = $field['value'];
        }
        
        // Intentar identificar campos comunes
        $name = $this->find_field($fields, ['name', 'nombre', 'fullname', 'full_name']);
        $email = $this->find_field($fields, ['email', 'correo', 'e-mail', 'mail']);
        $phone = $this->find_field($fields, ['phone', 'telefono', 'tel', 'movil', 'celular']);
        $message = $this->find_field($fields, ['message', 'mensaje', 'comment', 'comentario', 'consulta']);
        
        // Si no hay email, no guardar (requisito mínimo)
        if (empty($email)) {
            return;
        }
        
        // Obtener metadatos de seguridad
        $security = RCM_Security::instance();
        $geo_check = $security->verify_country();
        
        // Preparar datos de contacto
        $contact_data = [
            'type'       => 'elementor',
            'name'       => sanitize_text_field($name ?: 'Sin nombre'),
            'email'      => sanitize_email($email),
            'phone'      => sanitize_text_field($phone),
            'message'    => sanitize_textarea_field($message),
            'ip'         => $geo_check['ip'],
            'country'    => $geo_check['country'],
            'user_agent' => $security->get_user_agent(),
            'source'     => sprintf('Elementor - %s (ID: %s)', $form_name, $form_id),
            'data'       => [
                'form_id'     => $form_id,
                'form_name'   => $form_name,
                'all_fields'  => $fields, // Guardar todos los campos
            ],
        ];
        
        // Crear solicitud
        $post_id = RCM_CPT::create_contact($contact_data);
        
        if ($post_id) {
            // Enviar notificación
            $this->send_notification($post_id, $form_name, $form_id);
            
            // Enviar a StaffKit si está configurado
            $this->send_to_staffkit($contact_data);
            
            // Añadir log al record de Elementor (opcional)
            do_action('rcm_elementor_captured', $post_id, $record);
        }
    }
    
    /**
     * Buscar campo por múltiples IDs posibles
     */
    private function find_field($fields, $possible_ids) {
        foreach ($possible_ids as $id) {
            // Búsqueda exacta
            if (isset($fields[$id]) && !empty($fields[$id])) {
                return $fields[$id];
            }
            
            // Búsqueda parcial (case-insensitive)
            foreach ($fields as $field_id => $value) {
                if (stripos($field_id, $id) !== false && !empty($value)) {
                    return $value;
                }
            }
        }
        
        return '';
    }
    
    /**
     * Enviar notificación
     */
    private function send_notification($post_id, $form_name, $form_id) {
        $to = Replanta_Contact_Manager::get_option('email_to', get_option('admin_email'));
        
        $name = get_post_meta($post_id, '_rcm_name', true);
        $email = get_post_meta($post_id, '_rcm_email', true);
        $message = get_post_meta($post_id, '_rcm_message', true);
        
        $subject = sprintf('[%s] Formulario Elementor: %s', get_bloginfo('name'), $form_name);
        
        $body = sprintf(
            "Nuevo envío de formulario Elementor:\n\n" .
            "Formulario: %s (ID: %s)\n" .
            "Nombre: %s\n" .
            "Email: %s\n\n" .
            "Mensaje:\n%s\n\n" .
            "Ver en admin: %s",
            $form_name,
            $form_id,
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
    private function send_to_staffkit($contact_data) {
        $staffkit_url = get_option('rcm_staffkit_url');
        $staffkit_key = get_option('rcm_staffkit_api_key');
        
        // Solo enviar si StaffKit está configurado
        if (empty($staffkit_url) || empty($staffkit_key)) {
            return false;
        }
        
        // Mapear datos al formato de StaffKit
        $payload = [
            'email'      => $contact_data['email'],
            'name'       => $contact_data['name'],
            'phone'      => $contact_data['phone'] ?? null,
            'source'     => $contact_data['source'],
            'source_url' => home_url($_SERVER['REQUEST_URI'] ?? ''),
            'user_agent' => $contact_data['user_agent'],
            'ip_address' => $contact_data['ip'],
            'metadata'   => [
                'type' => 'elementor',
                'country' => $contact_data['country'],
                'form_data' => $contact_data['data'] ?? []
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
            error_log('RCM → StaffKit Error (Elementor): ' . $response->get_error_message());
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            error_log('RCM → StaffKit HTTP ' . $code . ' (Elementor): ' . wp_remote_retrieve_body($response));
            return false;
        }
        
        return true;
    }
}
