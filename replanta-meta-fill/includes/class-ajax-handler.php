<?php
/**
 * AJAX Handler - Gestiona peticiones AJAX del admin
 *
 * @package Replanta_Meta_Fill
 */

if (!defined('ABSPATH')) {
    exit;
}

class Replanta_Meta_Fill_Ajax_Handler {
    
    private static $instance = null;
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Endpoints AJAX
        add_action('wp_ajax_rmf_generate_meta', [$this, 'ajax_generate_meta']);
        add_action('wp_ajax_rmf_check_status', [$this, 'ajax_check_status']);
        add_action('wp_ajax_rmf_bulk_generate', [$this, 'ajax_bulk_generate']);
        add_action('wp_ajax_rmf_validate_api_key', [$this, 'ajax_validate_api_key']);
    }
    
    /**
     * Generar meta descripción para un post
     */
    public function ajax_generate_meta() {
        // Verificar nonce
        check_ajax_referer('replanta_meta_fill_nonce', 'nonce');
        
        // Verificar permisos
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']);
        }
        
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        
        if (!$post_id) {
            wp_send_json_error(['message' => 'ID de post inválido']);
        }
        
        // Verificar que el post existe
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(['message' => 'Post no encontrado']);
        }
        
        // Generar meta descripción
        $openai = Replanta_Meta_Fill_OpenAI_Handler::instance();
        $result = $openai->generate_meta_description($post_id);
        
        if ($result['success']) {
            wp_send_json_success([
                'message' => 'Meta descripción generada correctamente',
                'meta_description' => $result['meta_description'],
                'previous_meta' => isset($result['previous_meta']) ? $result['previous_meta'] : null,
            ]);
        } else {
            wp_send_json_error([
                'message' => isset($result['error']) ? $result['error'] : 'Error desconocido'
            ]);
        }
    }
    
    /**
     * Verificar estado de meta descripción
     */
    public function ajax_check_status() {
        check_ajax_referer('replanta_meta_fill_nonce', 'nonce');
        
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        
        if (!$post_id) {
            wp_send_json_error(['message' => 'ID de post inválido']);
        }
        
        $crawler = Replanta_Meta_Fill_Content_Crawler::instance();
        $meta_description = $crawler->get_existing_meta_description($post_id);
        
        wp_send_json_success([
            'has_meta' => !empty($meta_description),
            'meta_description' => $meta_description,
            'length' => $meta_description ? strlen($meta_description) : 0,
        ]);
    }
    
    /**
     * Generación masiva de meta descripciones
     */
    public function ajax_bulk_generate() {
        check_ajax_referer('replanta_meta_fill_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']);
        }
        
        $post_ids = isset($_POST['post_ids']) ? array_map('intval', (array) $_POST['post_ids']) : [];
        
        if (empty($post_ids)) {
            wp_send_json_error(['message' => 'No se proporcionaron IDs de posts']);
        }
        
        // Limitar a 10 posts por petición (evitar timeouts)
        $post_ids = array_slice($post_ids, 0, 10);
        
        $results = [];
        $success_count = 0;
        $error_count = 0;
        
        $openai = Replanta_Meta_Fill_OpenAI_Handler::instance();
        
        foreach ($post_ids as $post_id) {
            $result = $openai->generate_meta_description($post_id);
            
            $results[$post_id] = [
                'success' => $result['success'],
                'message' => $result['success'] ? 'Generada' : (isset($result['error']) ? $result['error'] : 'Error'),
            ];
            
            if ($result['success']) {
                $success_count++;
            } else {
                $error_count++;
            }
            
            // Pequeño delay para evitar rate limiting de OpenAI
            if (count($post_ids) > 1) {
                sleep(1);
            }
        }
        
        wp_send_json_success([
            'message' => sprintf(
                '%d generadas, %d errores',
                $success_count,
                $error_count
            ),
            'results' => $results,
            'success_count' => $success_count,
            'error_count' => $error_count,
        ]);
    }
    
    /**
     * Validar API key de OpenAI
     */
    public function ajax_validate_api_key() {
        check_ajax_referer('replanta_meta_fill_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']);
        }
        
        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
        
        if (empty($api_key)) {
            wp_send_json_error(['message' => 'API key vacía']);
        }
        
        $openai = Replanta_Meta_Fill_OpenAI_Handler::instance();
        $validation = $openai->validate_api_key($api_key);
        
        if ($validation['valid']) {
            wp_send_json_success([
                'message' => $validation['message']
            ]);
        } else {
            wp_send_json_error([
                'message' => $validation['message']
            ]);
        }
    }
}
