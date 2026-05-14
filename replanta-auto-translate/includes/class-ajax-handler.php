<?php
/**
 * Clase para manejar peticiones AJAX
 */

if (!defined('ABSPATH')) {
    exit;
}

class Replanta_Auto_Translate_Ajax_Handler {
    
    private static $instance = null;
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Registrar handlers AJAX
        add_action('wp_ajax_replanta_translate_single', [$this, 'translate_single']);
        add_action('wp_ajax_replanta_translate_bulk_start', [$this, 'bulk_start']);
        add_action('wp_ajax_replanta_translate_bulk_process', [$this, 'bulk_process']);
        add_action('wp_ajax_replanta_translate_bulk_cancel', [$this, 'bulk_cancel']);
        add_action('wp_ajax_replanta_translate_bulk_status', [$this, 'bulk_status']);
        add_action('wp_ajax_replanta_translate_menus', [$this, 'translate_menus']);
        add_action('wp_ajax_replanta_test_connection', [$this, 'test_connection']);
        add_action('wp_ajax_replanta_get_post_summary', [$this, 'get_post_summary']);
        add_action('wp_ajax_replanta_get_stats', [$this, 'get_stats']);
        add_action('wp_ajax_replanta_repair_translation', [$this, 'repair_translation']);
        add_action('wp_ajax_replanta_find_orphans', [$this, 'find_orphans']);
        add_action('wp_ajax_replanta_fix_all', [$this, 'fix_all']);
        add_action('wp_ajax_replanta_update_references', [$this, 'update_references']);
    }
    
    /**
     * Verificar nonce y permisos
     */
    private function verify_request() {
        if (!check_ajax_referer('replanta_auto_translate_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Nonce invalido'], 403);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes'], 403);
        }
        
        return true;
    }
    
    /**
     * Traducir un post individual
     */
    public function translate_single() {
        $this->verify_request();
        
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if (!$post_id) {
            wp_send_json_error(['message' => 'ID de post no valido']);
        }
        
        $processor = Replanta_Auto_Translate_Bulk_Processor::instance();
        $result = $processor->translate_single_post($post_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message(),
                'code' => $result->get_error_code(),
            ]);
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Iniciar traduccion masiva
     */
    public function bulk_start() {
        $this->verify_request();
        
        $post_type = sanitize_text_field($_POST['post_type'] ?? 'page');
        $post_ids = [];
        
        // Si se envian IDs especificos
        if (!empty($_POST['post_ids'])) {
            if (is_array($_POST['post_ids'])) {
                $post_ids = array_map('intval', $_POST['post_ids']);
            } else {
                $post_ids = array_map('intval', explode(',', $_POST['post_ids']));
            }
            $post_ids = array_filter($post_ids);
        }
        
        $processor = Replanta_Auto_Translate_Bulk_Processor::instance();
        
        // Limpiar estado anterior
        $processor->clear_process_state();
        
        $result = $processor->start_bulk_process($post_type, $post_ids);
        
        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message(),
            ]);
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Procesar siguiente lote
     */
    public function bulk_process() {
        $this->verify_request();
        
        $processor = Replanta_Auto_Translate_Bulk_Processor::instance();
        $result = $processor->process_next_batch();
        
        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message(),
            ]);
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Cancelar proceso
     */
    public function bulk_cancel() {
        $this->verify_request();
        
        $processor = Replanta_Auto_Translate_Bulk_Processor::instance();
        $result = $processor->cancel_process();
        
        wp_send_json_success($result);
    }
    
    /**
     * Obtener estado del proceso
     */
    public function bulk_status() {
        $this->verify_request();
        
        $processor = Replanta_Auto_Translate_Bulk_Processor::instance();
        $state = $processor->get_process_state();
        
        wp_send_json_success($state);
    }
    
    /**
     * Traducir menus
     */
    public function translate_menus() {
        $this->verify_request();
        
        $settings = Replanta_Auto_Translate::get_settings();
        $source_lang = $settings['source_language'];
        $target_lang = $settings['target_language'];
        
        $menu_translator = Replanta_Auto_Translate_Menu_Translator::instance();
        
        // Primero poblar menús vacíos que ya existen
        $populated = $menu_translator->populate_all_empty_menus($source_lang, $target_lang);
        
        // Luego crear menús que no existen
        $created = $menu_translator->translate_all_menus($source_lang, $target_lang);
        
        // Combinar resultados
        $total_success = count($populated['success']) + count($created['success']);
        $total_errors = count($populated['errors']) + count($created['errors']);
        
        wp_send_json_success([
            'success' => true,
            'menus_populated' => count($populated['success']),
            'menus_created' => count($created['success']),
            'menus_total' => $total_success,
            'errors' => $total_errors,
            'details' => [
                'populated' => $populated,
                'created' => $created,
            ],
        ]);
    }
    
    /**
     * Probar conexion con API
     */
    public function test_connection() {
        $this->verify_request();
        
        $engine = sanitize_text_field($_POST['engine'] ?? 'openai');
        $translator = Replanta_Auto_Translate_Translator::instance();
        
        if ($engine === 'openai') {
            $result = $translator->test_openai_connection();
        } else {
            $result = $translator->test_google_connection();
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message(),
            ]);
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Obtener resumen de un post antes de traducir
     */
    public function get_post_summary() {
        $this->verify_request();
        
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if (!$post_id) {
            wp_send_json_error(['message' => 'ID de post no valido']);
        }
        
        $extractor = Replanta_Auto_Translate_Content_Extractor::instance();
        $summary = $extractor->get_summary($post_id);
        
        if (is_wp_error($summary)) {
            wp_send_json_error([
                'message' => $summary->get_error_message(),
            ]);
        }
        
        // Agregar estimacion de tiempo
        $processor = Replanta_Auto_Translate_Bulk_Processor::instance();
        $time_estimate = $processor->estimate_bulk_time([$post_id]);
        
        $summary['estimated_time'] = $time_estimate['formatted'];
        
        wp_send_json_success($summary);
    }
    
    /**
     * Obtener estadisticas generales
     */
    public function get_stats() {
        $this->verify_request();
        
        $settings = Replanta_Auto_Translate::get_settings();
        $source_lang = $settings['source_language'];
        $target_lang = $settings['target_language'];
        
        $polylang = Replanta_Auto_Translate_Polylang_Bridge::instance();
        $processor = Replanta_Auto_Translate_Bulk_Processor::instance();
        $menu_translator = Replanta_Auto_Translate_Menu_Translator::instance();
        
        $stats = [
            'translation_stats' => $polylang->get_translation_stats($source_lang, $target_lang),
            'process_stats' => $processor->get_stats(),
            'menu_stats' => $menu_translator->get_menu_stats($source_lang, $target_lang),
            'settings' => [
                'source_language' => $source_lang,
                'target_language' => $target_lang,
                'engine' => $settings['default_engine'],
            ],
        ];
        
        wp_send_json_success($stats);
    }
    
    /**
     * Reparar vinculacion de traducciones
     */
    public function repair_translation() {
        $this->verify_request();
        
        $source_id = intval($_POST['source_id'] ?? 0);
        $target_id = intval($_POST['target_id'] ?? 0);
        
        if (!$source_id || !$target_id) {
            wp_send_json_error(['message' => 'IDs de posts no validos']);
        }
        
        $settings = Replanta_Auto_Translate::get_settings();
        $source_lang = $settings['source_language'];
        $target_lang = $settings['target_language'];
        
        $polylang = Replanta_Auto_Translate_Polylang_Bridge::instance();
        $result = $polylang->repair_translation_link($source_id, $target_id, $source_lang, $target_lang);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success([
            'message' => 'Traducciones vinculadas correctamente',
            'source_id' => $source_id,
            'target_id' => $target_id,
        ]);
    }
    
    /**
     * Buscar traducciones huerfanas (no vinculadas)
     */
    public function find_orphans() {
        $this->verify_request();
        
        $settings = Replanta_Auto_Translate::get_settings();
        $source_lang = $settings['source_language'];
        $target_lang = $settings['target_language'];
        
        $polylang = Replanta_Auto_Translate_Polylang_Bridge::instance();
        $orphans = $polylang->find_orphan_translations($source_lang, $target_lang);
        
        wp_send_json_success([
            'orphans' => $orphans,
            'count' => count($orphans),
        ]);
    }
    
    /**
     * Completar todo: traducir plantillas, actualizar referencias y configurar menús
     * Ejecuta paso a paso según el parámetro 'step'
     */
    public function fix_all() {
        $this->verify_request();
        
        $step = sanitize_text_field($_POST['step'] ?? 'templates');
        $settings = Replanta_Auto_Translate::get_settings();
        $source_lang = $settings['source_language'];
        $target_lang = $settings['target_language'];
        
        $result = [];
        
        switch ($step) {
            case 'templates':
                $result = $this->fix_all_templates($source_lang, $target_lang);
                break;
                
            case 'references':
                $result = $this->fix_all_references($target_lang);
                break;
                
            case 'menus':
                $result = $this->fix_all_menus($source_lang, $target_lang);
                break;
                
            default:
                wp_send_json_error(['message' => 'Paso no válido']);
                return;
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Paso 1: Traducir todas las plantillas pendientes
     */
    private function fix_all_templates($source_lang, $target_lang) {
        $templates = get_posts([
            'post_type' => 'elementor_library',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'lang' => $source_lang,
        ]);
        
        $translated = 0;
        $skipped = 0;
        $errors = [];
        $processor = Replanta_Auto_Translate_Bulk_Processor::instance();
        
        foreach ($templates as $template) {
            // Verificar si ya tiene traducción
            $existing = pll_get_post($template->ID, $target_lang);
            
            if ($existing) {
                $skipped++;
                continue;
            }
            
            // Traducir
            $result = $processor->translate_single_post($template->ID);
            
            if (is_wp_error($result)) {
                $errors[] = "#{$template->ID} '{$template->post_title}': " . $result->get_error_message();
            } else {
                $translated++;
            }
        }
        
        return [
            'step' => 'templates',
            'total' => count($templates),
            'translated' => $translated,
            'skipped' => $skipped,
            'errors' => $errors,
            'message' => "Plantillas: $translated traducidas, $skipped ya existían, " . count($errors) . " errores",
        ];
    }
    
    /**
     * Paso 2: Actualizar referencias a plantillas en páginas ya traducidas
     */
    private function fix_all_references($target_lang) {
        $parser = Replanta_Auto_Translate_Elementor_Parser::instance();
        
        // Obtener todas las páginas/posts en el idioma destino
        $pages = get_posts([
            'post_type' => ['page', 'post'],
            'post_status' => ['publish', 'draft'],
            'posts_per_page' => -1,
            'lang' => $target_lang,
        ]);
        
        $updated = 0;
        $unchanged = 0;
        
        foreach ($pages as $page) {
            $page_updated = false;
            
            // 1. Actualizar shortcodes en post_content
            $new_content = $parser->update_content_template_references($page->post_content, $target_lang);
            if ($new_content !== $page->post_content) {
                wp_update_post([
                    'ID' => $page->ID,
                    'post_content' => $new_content,
                ]);
                $page_updated = true;
            }
            
            // 2. Actualizar referencias en Elementor data
            $elementor_data = get_post_meta($page->ID, '_elementor_data', true);
            if ($elementor_data) {
                $data_array = json_decode($elementor_data, true);
                if (is_array($data_array)) {
                    $new_data = $parser->update_template_references($data_array, $target_lang);
                    $new_json = json_encode($new_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    
                    if ($new_json !== $elementor_data) {
                        update_post_meta($page->ID, '_elementor_data', wp_slash($new_json));
                        $page_updated = true;
                        
                        // Limpiar caché de Elementor
                        if (class_exists('\Elementor\Plugin')) {
                            \Elementor\Plugin::$instance->files_manager->clear_cache();
                        }
                    }
                }
            }
            
            if ($page_updated) {
                $updated++;
            } else {
                $unchanged++;
            }
        }
        
        return [
            'step' => 'references',
            'total' => count($pages),
            'updated' => $updated,
            'unchanged' => $unchanged,
            'message' => "Referencias: $updated páginas actualizadas, $unchanged sin cambios",
        ];
    }
    
    /**
     * Paso 3: Configurar y poblar menús
     */
    private function fix_all_menus($source_lang, $target_lang) {
        $translator = Replanta_Auto_Translate_Translator::instance();
        
        // Obtener menús en el idioma origen
        $menus = get_terms([
            'taxonomy' => 'nav_menu',
            'hide_empty' => false,
        ]);
        
        $populated = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($menus as $menu) {
            $menu_lang = pll_get_term_language($menu->term_id);
            
            if ($menu_lang !== $source_lang) {
                continue; // Solo procesar menús en idioma origen
            }
            
            // Buscar menú vinculado en idioma destino
            $target_menu_id = pll_get_term($menu->term_id, $target_lang);
            
            if (!$target_menu_id) {
                $errors[] = "Menú '{$menu->name}' no tiene traducción EN vinculada";
                continue;
            }
            
            // Verificar si el menú destino está vacío
            $target_items = wp_get_nav_menu_items($target_menu_id);
            $source_items = wp_get_nav_menu_items($menu->term_id);
            
            if (!empty($target_items)) {
                $skipped++; // Ya tiene items
                continue;
            }
            
            if (empty($source_items)) {
                $skipped++; // El origen está vacío
                continue;
            }
            
            // Poblar el menú destino
            foreach ($source_items as $item) {
                // Obtener la página traducida
                $translated_object_id = $item->object_id;
                if ($item->type === 'post_type') {
                    $tr_id = pll_get_post($item->object_id, $target_lang);
                    if ($tr_id) {
                        $translated_object_id = $tr_id;
                    }
                }
                
                // Traducir el título
                $translated_title = $translator->translate_text($item->title, $source_lang, $target_lang);
                if (is_wp_error($translated_title)) {
                    $translated_title = $item->title;
                }
                
                // Crear el item
                $new_item_data = [
                    'menu-item-object-id' => $translated_object_id,
                    'menu-item-object' => $item->object,
                    'menu-item-parent-id' => 0,
                    'menu-item-position' => $item->menu_order,
                    'menu-item-type' => $item->type,
                    'menu-item-title' => $translated_title,
                    'menu-item-url' => $item->url,
                    'menu-item-target' => $item->target,
                    'menu-item-classes' => implode(' ', (array)$item->classes),
                    'menu-item-status' => 'publish',
                ];
                
                wp_update_nav_menu_item($target_menu_id, 0, $new_item_data);
            }
            
            $populated++;
        }
        
        return [
            'step' => 'menus',
            'populated' => $populated,
            'skipped' => $skipped,
            'errors' => $errors,
            'message' => "Menús: $populated poblados, $skipped omitidos, " . count($errors) . " errores",
        ];
    }
    
    /**
     * Actualizar referencias en un post específico
     */
    public function update_references() {
        $this->verify_request();
        
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if (!$post_id) {
            wp_send_json_error(['message' => 'ID de post no válido']);
        }
        
        $settings = Replanta_Auto_Translate::get_settings();
        $target_lang = $settings['target_language'];
        
        $parser = Replanta_Auto_Translate_Elementor_Parser::instance();
        $post = get_post($post_id);
        
        if (!$post) {
            wp_send_json_error(['message' => 'Post no encontrado']);
        }
        
        $updated_content = false;
        $updated_elementor = false;
        
        // Actualizar post_content
        $new_content = $parser->update_content_template_references($post->post_content, $target_lang);
        if ($new_content !== $post->post_content) {
            wp_update_post([
                'ID' => $post_id,
                'post_content' => $new_content,
            ]);
            $updated_content = true;
        }
        
        // Actualizar Elementor data
        $elementor_data = get_post_meta($post_id, '_elementor_data', true);
        if ($elementor_data) {
            $data_array = json_decode($elementor_data, true);
            if (is_array($data_array)) {
                $new_data = $parser->update_template_references($data_array, $target_lang);
                $new_json = json_encode($new_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                
                if ($new_json !== $elementor_data) {
                    update_post_meta($post_id, '_elementor_data', wp_slash($new_json));
                    $updated_elementor = true;
                    
                    if (class_exists('\Elementor\Plugin')) {
                        \Elementor\Plugin::$instance->files_manager->clear_cache();
                    }
                }
            }
        }
        
        wp_send_json_success([
            'post_id' => $post_id,
            'updated_content' => $updated_content,
            'updated_elementor' => $updated_elementor,
            'message' => ($updated_content || $updated_elementor) ? 'Referencias actualizadas' : 'Sin cambios necesarios',
        ]);
    }
}
