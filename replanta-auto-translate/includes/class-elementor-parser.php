<?php
/**
 * Clase para parsear y reconstruir contenido de Elementor
 */

if (!defined('ABSPATH')) {
    exit;
}

class Replanta_Auto_Translate_Elementor_Parser {
    
    private static $instance = null;
    
    /**
     * Campos traducibles por tipo de widget
     * Mapeo de widget_type => campos que contienen texto traducible
     */
    private $translatable_fields = [
        // Widgets basicos
        'heading' => ['title'],
        'text-editor' => ['editor'],
        'button' => ['text', 'link' => ['custom_attributes']],
        'image' => ['caption'],
        'image-box' => ['title_text', 'description_text'],
        'icon-box' => ['title_text', 'description_text'],
        'star-rating' => ['title'],
        'image-carousel' => ['slides' => ['caption']],
        'image-gallery' => [], // Las imagenes no se traducen
        'icon-list' => ['icon_list' => ['text']],
        'counter' => ['title', 'suffix', 'prefix'],
        'progress' => ['title', 'inner_text'],
        'testimonial' => ['testimonial_content', 'testimonial_name', 'testimonial_job'],
        'tabs' => ['tabs' => ['tab_title', 'tab_content']],
        'accordion' => ['tabs' => ['tab_title', 'tab_content']],
        'toggle' => ['tabs' => ['tab_title', 'tab_content']],
        'alert' => ['alert_title', 'alert_description'],
        'html' => ['html'], // Widget HTML - se traducira con parser DOM que preserva estructura
        'video' => [],
        'shortcode' => [],
        'divider' => [],
        'spacer' => [],
        'google_maps' => [],
        
        // Widgets de formulario
        'form' => [
            'form_fields' => ['field_label', 'placeholder', 'field_options'],
            'button_text',
            'success_message',
            'error_message',
            'required_field_message',
        ],
        
        // Widgets Pro
        'posts' => [],
        'portfolio' => [],
        'slides' => ['slides' => ['heading', 'description', 'button_text']],
        'price-table' => [
            'heading', 
            'sub_heading', 
            'price', 
            'period', 
            'features_list' => ['item_text'],
            'button_text',
            'footer_additional_info',
            'ribbon_title',
        ],
        'price-list' => ['price_list' => ['title', 'description', 'price']],
        'flip-box' => [
            'title_text_a', 'description_text_a',
            'title_text_b', 'description_text_b',
            'button_text',
        ],
        'call-to-action' => ['title', 'description', 'button', 'ribbon_title'],
        'animated-headline' => ['before_text', 'highlighted_text', 'rotating_text', 'after_text'],
        'blockquote' => ['blockquote_content', 'tweet_button_label'],
        
        // Widgets de terceros comunes
        'eael-content-timeline' => ['eael_content_timeline_items' => ['eael_content_timeline_title', 'eael_content_timeline_content']],
        'eael-creative-button' => ['creative_button_text'],
        'eael-info-box' => ['eael_infobox_title', 'eael_infobox_text'],
        
        // Theme Builder
        'site-title' => [],
        'site-logo' => [],
        'page-title' => [],
        'post-title' => [],
        'post-excerpt' => [],
        'post-content' => [],
        'author-box' => [],
        'post-comments' => [],
        'search-form' => ['placeholder'],
    ];
    
    /**
     * Campos que contienen HTML y necesitan tratamiento especial
     */
    private $html_fields = ['editor', 'tab_content', 'description_text', 'testimonial_content', 'eael_content_timeline_content', 'html'];
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Permitir extension de campos traducibles
        add_filter('replanta_elementor_translatable_fields', [$this, 'filter_translatable_fields']);
    }
    
    /**
     * Filtro para extender campos traducibles
     */
    public function filter_translatable_fields($fields) {
        return apply_filters('replanta_elementor_translatable_fields', $fields);
    }
    
    /**
     * Verificar si un post usa Elementor
     */
    public function is_elementor_post($post_id) {
        $elementor_data = get_post_meta($post_id, '_elementor_data', true);
        return !empty($elementor_data);
    }
    
    /**
     * Obtener datos de Elementor de un post
     */
    public function get_elementor_data($post_id) {
        $data = get_post_meta($post_id, '_elementor_data', true);
        
        if (empty($data)) {
            return null;
        }
        
        // Puede estar almacenado como string JSON o como array
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
        
        return $data;
    }
    
    /**
     * Extraer todos los textos traducibles de los datos de Elementor
     * Retorna un array con paths y textos para traducir
     */
    public function extract_translatable_texts($elementor_data) {
        $texts = [];
        
        if (!is_array($elementor_data)) {
            return $texts;
        }
        
        $this->walk_elements($elementor_data, '', function($element, $path) use (&$texts) {
            $widget_type = $element['widgetType'] ?? $element['elType'] ?? null;
            
            if (!$widget_type || !isset($element['settings'])) {
                return;
            }
            
            $settings = $element['settings'];
            $translatable = $this->get_translatable_fields_for_widget($widget_type);
            
            foreach ($translatable as $field => $sub_fields) {
                if (is_numeric($field)) {
                    // Campo simple
                    $field = $sub_fields;
                    $sub_fields = null;
                }
                
                if (!isset($settings[$field])) {
                    continue;
                }
                
                if (is_array($sub_fields) && is_array($settings[$field])) {
                    // Campo con items (ej: tabs, icon_list)
                    foreach ($settings[$field] as $item_index => $item) {
                        foreach ($sub_fields as $sub_field) {
                            if (isset($item[$sub_field]) && $this->is_translatable_value($item[$sub_field])) {
                                $texts[] = [
                                    'path' => "{$path}.settings.{$field}.{$item_index}.{$sub_field}",
                                    'text' => $item[$sub_field],
                                    'widget' => $widget_type,
                                    'field' => "{$field}.{$sub_field}",
                                    'is_html' => in_array($sub_field, $this->html_fields),
                                ];
                            }
                        }
                    }
                } else {
                    // Campo simple
                    if ($this->is_translatable_value($settings[$field])) {
                        $texts[] = [
                            'path' => "{$path}.settings.{$field}",
                            'text' => $settings[$field],
                            'widget' => $widget_type,
                            'field' => $field,
                            'is_html' => in_array($field, $this->html_fields),
                        ];
                    }
                }
            }
        });
        
        return $texts;
    }
    
    /**
     * Aplicar traducciones a los datos de Elementor
     * $translations es un array con path => texto_traducido
     */
    public function apply_translations($elementor_data, $translations) {
        if (!is_array($elementor_data) || empty($translations)) {
            return $elementor_data;
        }
        
        foreach ($translations as $path => $translated_text) {
            $elementor_data = $this->set_value_by_path($elementor_data, $path, $translated_text);
        }
        
        return $elementor_data;
    }
    
    /**
     * Guardar datos de Elementor traducidos
     */
    public function save_elementor_data($post_id, $elementor_data) {
        if (!is_array($elementor_data)) {
            return false;
        }
        
        // Elementor espera los datos como string JSON
        $json_data = wp_json_encode($elementor_data);
        
        // Guardar como meta
        update_post_meta($post_id, '_elementor_data', $json_data);
        
        // Marcar como editado con Elementor
        update_post_meta($post_id, '_elementor_edit_mode', 'builder');
        
        // Limpiar cache de Elementor si esta disponible
        if (class_exists('\Elementor\Plugin')) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }
        
        return true;
    }
    
    /**
     * Recorrer recursivamente todos los elementos
     */
    private function walk_elements($elements, $current_path, $callback) {
        foreach ($elements as $index => $element) {
            $element_path = $current_path ? "{$current_path}.{$index}" : "{$index}";
            
            // Llamar callback para este elemento
            $callback($element, $element_path);
            
            // Procesar hijos (elements)
            if (!empty($element['elements']) && is_array($element['elements'])) {
                $this->walk_elements($element['elements'], "{$element_path}.elements", $callback);
            }
        }
    }
    
    /**
     * Obtener campos traducibles para un tipo de widget
     */
    private function get_translatable_fields_for_widget($widget_type) {
        // Buscar en el mapeo
        if (isset($this->translatable_fields[$widget_type])) {
            return $this->translatable_fields[$widget_type];
        }
        
        // Campos genericos para widgets desconocidos
        // Intentar traducir campos comunes
        return ['title', 'text', 'description', 'content', 'heading', 'label', 'button_text'];
    }
    
    /**
     * Verificar si un valor es traducible (texto no vacio)
     */
    private function is_translatable_value($value) {
        if (!is_string($value)) {
            return false;
        }
        
        $value = trim($value);
        
        // Ignorar valores vacios
        if (empty($value)) {
            return false;
        }
        
        // Ignorar si es solo numeros
        if (is_numeric($value)) {
            return false;
        }
        
        // Ignorar si es una URL
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // Ignorar si es solo HTML vacio o espacios
        $stripped = strip_tags($value);
        if (empty(trim($stripped))) {
            // Pero si contiene atributos alt o title, podria ser traducible
            if (strpos($value, 'alt="') !== false || strpos($value, 'title="') !== false) {
                return true;
            }
            return false;
        }
        
        // Ignorar shortcodes solos
        if (preg_match('/^\[[\w\-]+(\s+[^\]]+)?\]$/', $value)) {
            return false;
        }
        
        // Ignorar valores que parecen ser IDs o codigos
        if (preg_match('/^[a-f0-9\-]{20,}$/i', $value)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Establecer valor en array usando path con notacion de punto
     */
    private function set_value_by_path($array, $path, $value) {
        $keys = explode('.', $path);
        $current = &$array;
        
        foreach ($keys as $key) {
            if (!isset($current[$key])) {
                return $array; // Path no existe, retornar sin cambios
            }
            $current = &$current[$key];
        }
        
        $current = $value;
        return $array;
    }
    
    /**
     * Obtener valor de array usando path con notacion de punto
     */
    public function get_value_by_path($array, $path) {
        $keys = explode('.', $path);
        $current = $array;
        
        foreach ($keys as $key) {
            if (!isset($current[$key])) {
                return null;
            }
            $current = $current[$key];
        }
        
        return $current;
    }
    
    /**
     * Traducir completamente los datos de Elementor
     * Usa el traductor configurado
     */
    public function translate_elementor_data($post_id, $source_lang, $target_lang) {
        $elementor_data = $this->get_elementor_data($post_id);
        
        if (!$elementor_data) {
            return null;
        }
        
        // Extraer textos traducibles
        $texts = $this->extract_translatable_texts($elementor_data);
        
        if (empty($texts)) {
            return $elementor_data; // No hay nada que traducir
        }
        
        // Obtener el traductor
        $translator = Replanta_Auto_Translate_Translator::instance();
        
        // Agrupar textos para traducir de forma eficiente
        $translations = [];
        
        foreach ($texts as $text_info) {
            $text = $text_info['text'];
            $is_html = $text_info['is_html'];
            $widget = $text_info['widget'];
            $field = $text_info['field'];
            
            // Para widgets HTML, usar el parser DOM que preserva estructura
            if ($widget === 'html' && $field === 'html') {
                $result = $translator->translate_html_with_dom($text, $source_lang, $target_lang);
            } elseif ($is_html) {
                // Otros campos HTML (text-editor content, etc)
                $result = $translator->translate_html($text, $source_lang, $target_lang);
            } else {
                // Texto plano
                $result = $translator->translate($text, $source_lang, $target_lang, [
                    'context' => "Widget: {$widget}, Campo: {$field}"
                ]);
            }
            
            if (!is_wp_error($result)) {
                $translations[$text_info['path']] = $result;
            }
        }
        
        // Aplicar traducciones
        $translated_data = $this->apply_translations($elementor_data, $translations);
        
        return $translated_data;
    }
    
    /**
     * Copiar estructura de Elementor sin contenido (para debug)
     */
    public function get_structure_only($elementor_data) {
        $structure = [];
        
        $this->walk_elements($elementor_data, '', function($element, $path) use (&$structure) {
            $structure[] = [
                'path' => $path,
                'type' => $element['elType'] ?? 'unknown',
                'widget' => $element['widgetType'] ?? null,
            ];
        });
        
        return $structure;
    }
    
    /**
     * Obtener resumen de contenido traducible
     */
    public function get_translation_summary($post_id) {
        $elementor_data = $this->get_elementor_data($post_id);
        
        if (!$elementor_data) {
            return [
                'is_elementor' => false,
                'total_texts' => 0,
                'widgets' => [],
            ];
        }
        
        $texts = $this->extract_translatable_texts($elementor_data);
        
        $widgets_count = [];
        $total_chars = 0;
        
        foreach ($texts as $text_info) {
            $widget = $text_info['widget'];
            if (!isset($widgets_count[$widget])) {
                $widgets_count[$widget] = 0;
            }
            $widgets_count[$widget]++;
            $total_chars += strlen(strip_tags($text_info['text']));
        }
        
        return [
            'is_elementor' => true,
            'total_texts' => count($texts),
            'total_characters' => $total_chars,
            'widgets' => $widgets_count,
            'estimated_cost' => $this->estimate_translation_cost($total_chars),
        ];
    }
    
    /**
     * Estimar costo de traduccion basado en caracteres
     */
    private function estimate_translation_cost($characters) {
        // Estimaciones muy aproximadas
        // OpenAI: ~$0.01 por 1000 tokens (~750 words, ~4000 chars)
        // Google: ~$20 por millon de caracteres
        
        $openai_cost = ($characters / 4000) * 0.01;
        $google_cost = ($characters / 1000000) * 20;
        
        return [
            'openai' => round($openai_cost, 4),
            'google' => round($google_cost, 4),
        ];
    }
    
    /**
     * Agregar campos traducibles personalizados
     */
    public function add_translatable_fields($widget_type, $fields) {
        $this->translatable_fields[$widget_type] = $fields;
    }
    
    /**
     * Obtener todos los widgets usados en un post
     */
    public function get_used_widgets($post_id) {
        $elementor_data = $this->get_elementor_data($post_id);
        
        if (!$elementor_data) {
            return [];
        }
        
        $widgets = [];
        
        $this->walk_elements($elementor_data, '', function($element) use (&$widgets) {
            if (!empty($element['widgetType'])) {
                $widgets[] = $element['widgetType'];
            }
        });
        
        return array_unique($widgets);
    }
    
    /**
     * Actualizar referencias a templates/plantillas en los datos de Elementor
     * Reemplaza IDs de plantillas ES por IDs de plantillas EN traducidas
     *
     * @param array $elementor_data Datos de Elementor decodificados
     * @param string $target_lang Idioma destino (ej: 'en')
     * @return array Datos de Elementor con referencias actualizadas
     */
    public function update_template_references($elementor_data, $target_lang) {
        if (!is_array($elementor_data) || !function_exists('pll_get_post')) {
            return $elementor_data;
        }
        
        $updated = false;
        $log_messages = [];
        
        // Recorrer recursivamente todos los elementos
        $elementor_data = $this->walk_and_update_elements($elementor_data, function($element) use ($target_lang, &$updated, &$log_messages) {
            // 1. Widget tipo "template" de Elementor Pro
            if (isset($element['widgetType']) && $element['widgetType'] === 'template') {
                if (isset($element['settings']['template_id'])) {
                    $old_id = (int) $element['settings']['template_id'];
                    $new_id = pll_get_post($old_id, $target_lang);
                    
                    if ($new_id && $new_id !== $old_id) {
                        $element['settings']['template_id'] = (string) $new_id;
                        $updated = true;
                        $log_messages[] = "Widget template: $old_id → $new_id";
                    }
                }
            }
            
            // 2. Global Widgets (widgets globales)
            if (isset($element['templateID'])) {
                $old_id = (int) $element['templateID'];
                $new_id = pll_get_post($old_id, $target_lang);
                
                if ($new_id && $new_id !== $old_id) {
                    $element['templateID'] = $new_id;
                    $updated = true;
                    $log_messages[] = "Global widget: $old_id → $new_id";
                }
            }
            
            // 3. Shortcodes en campos de texto (editor, html, etc.)
            if (isset($element['settings'])) {
                foreach ($element['settings'] as $field => &$value) {
                    if (is_string($value) && strpos($value, '[elementor-template') !== false) {
                        $value = $this->update_shortcode_template_ids($value, $target_lang, $log_messages, $updated);
                    }
                    // También revisar contenido HTML que pueda tener shortcodes
                    if (is_string($value) && strpos($value, 'elementor-template') !== false) {
                        $value = $this->update_shortcode_template_ids($value, $target_lang, $log_messages, $updated);
                    }
                }
            }
            
            return $element;
        });
        
        // Registrar cambios en el log
        if ($updated && class_exists('Replanta_Auto_Translate')) {
            foreach ($log_messages as $msg) {
                Replanta_Auto_Translate::log("Template reference actualizada: $msg", 'info');
            }
        }
        
        return $elementor_data;
    }
    
    /**
     * Actualizar IDs en shortcodes [elementor-template id="X"]
     *
     * @param string $content Contenido con shortcodes
     * @param string $target_lang Idioma destino
     * @param array &$log_messages Array para registrar cambios
     * @param bool &$updated Flag de actualización
     * @return string Contenido con IDs actualizados
     */
    private function update_shortcode_template_ids($content, $target_lang, &$log_messages, &$updated) {
        // Patrón para [elementor-template id="123"] o id='123' o id=123
        $pattern = '/\[elementor-template\s+([^\]]*?)id\s*=\s*["\']?(\d+)["\']?([^\]]*?)\]/i';
        
        $content = preg_replace_callback($pattern, function($matches) use ($target_lang, &$log_messages, &$updated) {
            $old_id = (int) $matches[2];
            $new_id = function_exists('pll_get_post') ? pll_get_post($old_id, $target_lang) : null;
            
            if ($new_id && $new_id !== $old_id) {
                $updated = true;
                $log_messages[] = "Shortcode: $old_id → $new_id";
                return '[elementor-template ' . $matches[1] . 'id="' . $new_id . '"' . $matches[3] . ']';
            }
            
            return $matches[0]; // Sin cambios
        }, $content);
        
        return $content;
    }
    
    /**
     * Recorrer y actualizar elementos recursivamente
     * Similar a walk_elements pero permite modificar los elementos
     *
     * @param array $elements Elementos a recorrer
     * @param callable $callback Función que recibe y retorna elemento
     * @return array Elementos actualizados
     */
    private function walk_and_update_elements($elements, $callback) {
        if (!is_array($elements)) {
            return $elements;
        }
        
        foreach ($elements as $index => &$element) {
            // Aplicar callback a este elemento
            $element = $callback($element);
            
            // Procesar hijos recursivamente
            if (!empty($element['elements']) && is_array($element['elements'])) {
                $element['elements'] = $this->walk_and_update_elements($element['elements'], $callback);
            }
        }
        
        return $elements;
    }
    
    /**
     * Actualizar referencias a templates en post_content (no Elementor)
     * Para shortcodes en el contenido del post estándar
     *
     * @param string $content Contenido del post
     * @param string $target_lang Idioma destino
     * @return string Contenido actualizado
     */
    public function update_content_template_references($content, $target_lang) {
        if (empty($content) || !function_exists('pll_get_post')) {
            return $content;
        }
        
        $log_messages = [];
        $updated = false;
        
        $content = $this->update_shortcode_template_ids($content, $target_lang, $log_messages, $updated);
        
        if ($updated && class_exists('Replanta_Auto_Translate')) {
            foreach ($log_messages as $msg) {
                Replanta_Auto_Translate::log("Content template reference actualizada: $msg", 'info');
            }
        }
        
        return $content;
    }
}
