<?php
/**
 * Clase para extraer contenido traducible de posts
 */

if (!defined('ABSPATH')) {
    exit;
}

class Replanta_Auto_Translate_Content_Extractor {
    
    private static $instance = null;
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Constructor vacio
    }
    
    /**
     * Extraer todo el contenido traducible de un post
     */
    public function extract($post_id) {
        $post = get_post($post_id);
        
        if (!$post) {
            return new WP_Error('post_not_found', 'Post no encontrado');
        }
        
        $content = [
            'post_id' => $post_id,
            'post_type' => $post->post_type,
            'title' => $post->post_title,
            'excerpt' => $post->post_excerpt,
            'slug' => $post->post_name,
            'content' => $post->post_content,
            'is_elementor' => false,
            'elementor_texts' => [],
            'seo' => [],
            'custom_fields' => [],
        ];
        
        // Verificar si usa Elementor
        $elementor_parser = Replanta_Auto_Translate_Elementor_Parser::instance();
        if ($elementor_parser->is_elementor_post($post_id)) {
            $content['is_elementor'] = true;
            $content['elementor_texts'] = $elementor_parser->extract_translatable_texts(
                $elementor_parser->get_elementor_data($post_id)
            );
        }
        
        // Extraer meta SEO
        $content['seo'] = $this->extract_seo_meta($post_id);
        
        // Extraer campos personalizados traducibles
        $content['custom_fields'] = $this->extract_custom_fields($post_id);
        
        return $content;
    }
    
    /**
     * Extraer meta datos SEO (Yoast y RankMath)
     */
    private function extract_seo_meta($post_id) {
        $seo = [
            'title' => '',
            'description' => '',
            'focus_keyword' => '',
        ];
        
        // Yoast SEO
        $yoast_title = get_post_meta($post_id, '_yoast_wpseo_title', true);
        $yoast_desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
        $yoast_keyword = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
        
        if ($yoast_title) {
            $seo['title'] = $yoast_title;
        }
        if ($yoast_desc) {
            $seo['description'] = $yoast_desc;
        }
        if ($yoast_keyword) {
            $seo['focus_keyword'] = $yoast_keyword;
        }
        
        // RankMath (si no hay datos de Yoast)
        if (empty($seo['title'])) {
            $rankmath_title = get_post_meta($post_id, 'rank_math_title', true);
            if ($rankmath_title) {
                $seo['title'] = $rankmath_title;
            }
        }
        
        if (empty($seo['description'])) {
            $rankmath_desc = get_post_meta($post_id, 'rank_math_description', true);
            if ($rankmath_desc) {
                $seo['description'] = $rankmath_desc;
            }
        }
        
        if (empty($seo['focus_keyword'])) {
            $rankmath_keyword = get_post_meta($post_id, 'rank_math_focus_keyword', true);
            if ($rankmath_keyword) {
                $seo['focus_keyword'] = $rankmath_keyword;
            }
        }
        
        return $seo;
    }
    
    /**
     * Extraer campos personalizados que podrian necesitar traduccion
     */
    private function extract_custom_fields($post_id) {
        $fields = [];
        
        // Lista de campos personalizados conocidos que podrian traducirse
        $translatable_meta_keys = apply_filters('replanta_translatable_meta_keys', [
            // ACF campos comunes
            'subtitle',
            'subheading',
            'button_text',
            'cta_text',
            'description',
            'summary',
            // Campos genericos
            'custom_title',
            'custom_description',
        ]);
        
        foreach ($translatable_meta_keys as $key) {
            $value = get_post_meta($post_id, $key, true);
            if (!empty($value) && is_string($value)) {
                $fields[$key] = $value;
            }
        }
        
        return $fields;
    }
    
    /**
     * Traducir todo el contenido extraido
     */
    public function translate_content($extracted_content, $source_lang, $target_lang) {
        $settings = Replanta_Auto_Translate::get_settings();
        $translator = Replanta_Auto_Translate_Translator::instance();
        
        $translated = [
            'title' => '',
            'excerpt' => '',
            'content' => '',
            'slug' => '',
            'elementor_data' => null,
            'seo_title' => '',
            'seo_description' => '',
            'custom_fields' => [],
            'status' => 'publish', // Publicar directamente
        ];
        
        // Traducir titulo
        if (!empty($extracted_content['title'])) {
            $result = $translator->translate($extracted_content['title'], $source_lang, $target_lang);
            $translated['title'] = is_wp_error($result) ? $extracted_content['title'] : $result;
        }
        
        // Traducir excerpt
        if (!empty($extracted_content['excerpt'])) {
            $result = $translator->translate($extracted_content['excerpt'], $source_lang, $target_lang);
            $translated['excerpt'] = is_wp_error($result) ? $extracted_content['excerpt'] : $result;
        }
        
        // Traducir slug si esta habilitado
        if (!empty($settings['translate_slugs']) && !empty($extracted_content['slug'])) {
            $translated['slug'] = $translator->translate_slug($extracted_content['slug'], $source_lang, $target_lang);
        }
        
        // Traducir contenido Elementor o contenido clasico
        if ($extracted_content['is_elementor']) {
            $elementor_parser = Replanta_Auto_Translate_Elementor_Parser::instance();
            $post_id = $extracted_content['post_id'];
            
            $translated_elementor = $elementor_parser->translate_elementor_data($post_id, $source_lang, $target_lang);
            
            if ($translated_elementor) {
                // Usar JSON_UNESCAPED_UNICODE para evitar problemas de encoding
                // El wp_slash se aplicara al guardar en polylang-bridge
                $translated['elementor_data'] = wp_json_encode($translated_elementor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            
            // Para Elementor, el content normal suele estar vacio o ser un fallback
            $translated['content'] = '';
        } else {
            // Contenido clasico (editor de bloques o clasico)
            if (!empty($extracted_content['content'])) {
                // Si el contenido es SOLO shortcodes (sin texto real que traducir),
                // copiarlo tal cual — evita que la IA corrompa los shortcodes.
                $content_without_shortcodes = preg_replace('/\[[\w\-]+[^\]]*\](?:[\s\S]*?\[\/[\w\-]+\])?/', '', $extracted_content['content']);
                $has_real_text = strlen(trim(strip_tags($content_without_shortcodes))) > 0;

                if (!$has_real_text) {
                    // Solo shortcodes: copiar sin traducir
                    $translated['content'] = $extracted_content['content'];
                } else {
                    $result = $translator->translate_html($extracted_content['content'], $source_lang, $target_lang);
                    $translated['content'] = is_wp_error($result) ? $extracted_content['content'] : $result;
                }
            }
        }
        
        // Traducir SEO si esta habilitado
        if (!empty($settings['translate_seo'])) {
            if (!empty($extracted_content['seo']['title'])) {
                $result = $translator->translate($extracted_content['seo']['title'], $source_lang, $target_lang);
                $translated['seo_title'] = is_wp_error($result) ? '' : $result;
            }
            
            if (!empty($extracted_content['seo']['description'])) {
                $result = $translator->translate($extracted_content['seo']['description'], $source_lang, $target_lang);
                $translated['seo_description'] = is_wp_error($result) ? '' : $result;
            }
        }
        
        // Traducir campos personalizados
        foreach ($extracted_content['custom_fields'] as $key => $value) {
            $result = $translator->translate($value, $source_lang, $target_lang);
            $translated['custom_fields'][$key] = is_wp_error($result) ? $value : $result;
        }
        
        return $translated;
    }
    
    /**
     * Obtener resumen del contenido a traducir
     */
    public function get_summary($post_id) {
        $extracted = $this->extract($post_id);
        
        if (is_wp_error($extracted)) {
            return $extracted;
        }
        
        $total_chars = 0;
        $total_items = 0;
        
        // Contar titulo
        if (!empty($extracted['title'])) {
            $total_chars += strlen($extracted['title']);
            $total_items++;
        }
        
        // Contar excerpt
        if (!empty($extracted['excerpt'])) {
            $total_chars += strlen($extracted['excerpt']);
            $total_items++;
        }
        
        // Contar contenido
        if (!empty($extracted['content'])) {
            $total_chars += strlen(strip_tags($extracted['content']));
            $total_items++;
        }
        
        // Contar Elementor
        if ($extracted['is_elementor']) {
            foreach ($extracted['elementor_texts'] as $text) {
                $total_chars += strlen(strip_tags($text['text']));
                $total_items++;
            }
        }
        
        // Contar SEO
        if (!empty($extracted['seo']['title'])) {
            $total_chars += strlen($extracted['seo']['title']);
            $total_items++;
        }
        if (!empty($extracted['seo']['description'])) {
            $total_chars += strlen($extracted['seo']['description']);
            $total_items++;
        }
        
        return [
            'post_id' => $post_id,
            'post_type' => $extracted['post_type'],
            'title' => $extracted['title'],
            'is_elementor' => $extracted['is_elementor'],
            'total_items' => $total_items,
            'total_characters' => $total_chars,
            'elementor_items' => count($extracted['elementor_texts']),
            'has_seo' => !empty($extracted['seo']['title']) || !empty($extracted['seo']['description']),
        ];
    }
    
    /**
     * Verificar si un post necesita traduccion
     */
    public function needs_translation($post_id, $target_lang) {
        $polylang = Replanta_Auto_Translate_Polylang_Bridge::instance();
        return !$polylang->has_translation($post_id, $target_lang);
    }
    
    /**
     * Obtener contenido de la pagina principal (Home)
     */
    public function get_homepage_content() {
        $homepage_id = get_option('page_on_front');
        
        if (!$homepage_id) {
            return null;
        }
        
        return $this->extract($homepage_id);
    }
    
    /**
     * Estimar tiempo de traduccion basado en caracteres
     */
    public function estimate_translation_time($post_id) {
        $summary = $this->get_summary($post_id);
        
        if (is_wp_error($summary)) {
            return 0;
        }
        
        // Estimacion: ~1000 caracteres por segundo con OpenAI
        $seconds = ceil($summary['total_characters'] / 1000);
        
        // Minimo 2 segundos por post
        return max(2, $seconds);
    }
}
