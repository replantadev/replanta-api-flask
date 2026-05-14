<?php
/**
 * Clase para integracion con Polylang
 */

if (!defined('ABSPATH')) {
    exit;
}

class Replanta_Auto_Translate_Polylang_Bridge {
    
    private static $instance = null;
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Constructor vacio - metodos se llaman directamente
    }
    
    /**
     * Verificar si Polylang esta activo
     */
    public function is_polylang_active() {
        return function_exists('pll_languages_list');
    }
    
    /**
     * Verificar si Polylang esta completamente inicializado
     * Esto es crucial para evitar errores cuando se llama desde CLI o demasiado temprano
     */
    public function is_polylang_ready() {
        if (!$this->is_polylang_active()) {
            return false;
        }
        
        // Verificar que el objeto PLL existe y tiene el modelo cargado
        if (!function_exists('PLL')) {
            return false;
        }
        
        $pll = PLL();
        if (!$pll || !is_object($pll)) {
            return false;
        }
        
        // Verificar que el modelo esta disponible
        if (!isset($pll->model) || !$pll->model) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Obtener lista de idiomas configurados
     */
    public function get_languages() {
        if (!$this->is_polylang_ready()) {
            return [];
        }
        
        try {
            return pll_languages_list(['fields' => []]);
        } catch (Exception $e) {
            error_log('[Replanta Auto Translate] Error en get_languages: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener idioma de un post
     */
    public function get_post_language($post_id) {
        if (!$this->is_polylang_ready()) {
            return null;
        }
        
        try {
            return pll_get_post_language($post_id);
        } catch (Exception $e) {
            error_log('[Replanta Auto Translate] Error en get_post_language: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obtener traduccion de un post en un idioma especifico
     */
    public function get_post_translation($post_id, $lang) {
        if (!$this->is_polylang_ready()) {
            return null;
        }
        
        try {
            return pll_get_post($post_id, $lang);
        } catch (Exception $e) {
            error_log('[Replanta Auto Translate] Error en get_post_translation: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Verificar si un post tiene traduccion en un idioma
     */
    public function has_translation($post_id, $lang) {
        $translation_id = $this->get_post_translation($post_id, $lang);
        return !empty($translation_id);
    }
    
    /**
     * Crear traduccion de un post
     * Este es el metodo principal que duplica un post y lo asocia como traduccion
     */
    public function create_translation($source_post_id, $target_lang, $translated_data = []) {
        if (!$this->is_polylang_ready()) {
            return new WP_Error('polylang_not_ready', 'Polylang no esta completamente inicializado. Intente mas tarde o ejecute desde WP-CLI.');
        }
        
        // Verificar que el post origen existe
        $source_post = get_post($source_post_id);
        if (!$source_post) {
            return new WP_Error('source_not_found', 'Post origen no encontrado');
        }
        
        // Verificar que no existe ya una traduccion
        if ($this->has_translation($source_post_id, $target_lang)) {
            return new WP_Error('translation_exists', 'Ya existe una traduccion para este idioma');
        }
        
        // Obtener idioma origen
        $source_lang = $this->get_post_language($source_post_id);
        if (!$source_lang) {
            return new WP_Error('no_source_lang', 'El post origen no tiene idioma asignado');
        }
        
        // Actualizar referencias a templates en el contenido antes de crear el post
        $content = $translated_data['content'] ?? $source_post->post_content;
        if (class_exists('Replanta_Auto_Translate_Elementor_Parser')) {
            $parser = Replanta_Auto_Translate_Elementor_Parser::instance();
            $content = $parser->update_content_template_references($content, $target_lang);
        }
        
        // Preparar datos del nuevo post
        $new_post_data = [
            'post_title'    => $translated_data['title'] ?? $source_post->post_title,
            'post_content'  => $content,
            'post_excerpt'  => $translated_data['excerpt'] ?? $source_post->post_excerpt,
            'post_status'   => $translated_data['status'] ?? 'draft', // Por defecto en borrador
            'post_type'     => $source_post->post_type,
            'post_author'   => $source_post->post_author,
            'post_parent'   => $this->get_translated_parent($source_post->post_parent, $target_lang),
            'menu_order'    => $source_post->menu_order,
            'comment_status'=> $source_post->comment_status,
            'ping_status'   => $source_post->ping_status,
        ];
        
        // Generar slug traducido si se proporciona
        if (!empty($translated_data['slug'])) {
            $new_post_data['post_name'] = sanitize_title($translated_data['slug']);
        }
        
        // Insertar el nuevo post
        $new_post_id = wp_insert_post($new_post_data, true);
        
        if (is_wp_error($new_post_id)) {
            return $new_post_id;
        }
        
        // Asignar idioma al nuevo post
        pll_set_post_language($new_post_id, $target_lang);
        
        // Vincular traducciones
        $this->link_translations($source_post_id, $new_post_id, $source_lang, $target_lang);
        
        // Copiar thumbnail/imagen destacada
        $this->copy_thumbnail($source_post_id, $new_post_id);
        
        // Copiar y traducir taxonomias
        $this->copy_taxonomies($source_post_id, $new_post_id, $target_lang);
        
        // Copiar meta datos (excepto los que se traducen)
        $this->copy_post_meta($source_post_id, $new_post_id, $translated_data);
        
        // Copiar template de pagina
        $template = get_page_template_slug($source_post_id);
        if ($template) {
            update_post_meta($new_post_id, '_wp_page_template', $template);
        }
        
        return $new_post_id;
    }
    
    /**
     * Vincular posts como traducciones
     */
    private function link_translations($post_id_1, $post_id_2, $lang_1, $lang_2) {
        // IMPORTANTE: Primero asegurar que el post original tiene idioma asignado
        $current_lang = pll_get_post_language($post_id_1);
        if (!$current_lang) {
            pll_set_post_language($post_id_1, $lang_1);
        }
        
        // Asegurar que el nuevo post tiene idioma asignado
        $new_lang = pll_get_post_language($post_id_2);
        if (!$new_lang) {
            pll_set_post_language($post_id_2, $lang_2);
        }
        
        // Obtener traducciones existentes del post original
        $translations = pll_get_post_translations($post_id_1);
        
        // Si no hay traducciones previas, inicializar el array
        if (empty($translations) || !is_array($translations)) {
            $translations = [];
        }
        
        // Construir el array completo de traducciones
        $translations[$lang_1] = (int) $post_id_1;
        $translations[$lang_2] = (int) $post_id_2;
        
        // Guardar las traducciones - esto vincula ambos posts
        pll_save_post_translations($translations);
        
        // Verificar que se vincularon correctamente
        $check = pll_get_post($post_id_1, $lang_2);
        if ($check != $post_id_2) {
            // Intentar metodo alternativo via taxonomia directamente
            $this->link_translations_direct($post_id_1, $post_id_2, $lang_1, $lang_2);
        }
    }
    
    /**
     * Metodo alternativo para vincular traducciones directamente via term
     */
    private function link_translations_direct($post_id_1, $post_id_2, $lang_1, $lang_2) {
        global $wpdb;
        
        // Buscar si ya existe un grupo de traducciones para post_id_1
        $term_taxonomy_id = $wpdb->get_var($wpdb->prepare(
            "SELECT tt.term_taxonomy_id 
             FROM {$wpdb->term_relationships} tr
             INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
             WHERE tr.object_id = %d AND tt.taxonomy = 'post_translations'",
            $post_id_1
        ));
        
        if ($term_taxonomy_id) {
            // Agregar post_id_2 al mismo grupo de traducciones
            $wpdb->replace(
                $wpdb->term_relationships,
                [
                    'object_id' => $post_id_2,
                    'term_taxonomy_id' => $term_taxonomy_id,
                    'term_order' => 0
                ]
            );
            
            // Actualizar descripcion del term con ambos posts
            $description = maybe_serialize([$lang_1 => $post_id_1, $lang_2 => $post_id_2]);
            $term_id = $wpdb->get_var($wpdb->prepare(
                "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id = %d",
                $term_taxonomy_id
            ));
            if ($term_id) {
                $wpdb->update(
                    $wpdb->terms,
                    ['slug' => 'pll_' . md5(serialize([$lang_1 => $post_id_1, $lang_2 => $post_id_2]))],
                    ['term_id' => $term_id]
                );
                $wpdb->update(
                    $wpdb->term_taxonomy,
                    ['description' => $description],
                    ['term_taxonomy_id' => $term_taxonomy_id]
                );
            }
        } else {
            // Crear nuevo grupo de traducciones
            $translations_array = [$lang_1 => $post_id_1, $lang_2 => $post_id_2];
            $slug = 'pll_' . md5(serialize($translations_array));
            
            // Insertar term
            $wpdb->insert(
                $wpdb->terms,
                [
                    'name' => $slug,
                    'slug' => $slug,
                    'term_group' => 0
                ]
            );
            $term_id = $wpdb->insert_id;
            
            // Insertar term_taxonomy
            $wpdb->insert(
                $wpdb->term_taxonomy,
                [
                    'term_id' => $term_id,
                    'taxonomy' => 'post_translations',
                    'description' => maybe_serialize($translations_array),
                    'count' => 2
                ]
            );
            $term_taxonomy_id = $wpdb->insert_id;
            
            // Vincular ambos posts al grupo
            $wpdb->insert(
                $wpdb->term_relationships,
                ['object_id' => $post_id_1, 'term_taxonomy_id' => $term_taxonomy_id, 'term_order' => 0]
            );
            $wpdb->insert(
                $wpdb->term_relationships,
                ['object_id' => $post_id_2, 'term_taxonomy_id' => $term_taxonomy_id, 'term_order' => 0]
            );
        }
        
        // Limpiar cache de Polylang
        clean_post_cache($post_id_1);
        clean_post_cache($post_id_2);
        wp_cache_flush();
    }
    
    /**
     * Obtener el padre traducido si existe
     */
    private function get_translated_parent($parent_id, $target_lang) {
        if (!$parent_id) {
            return 0;
        }
        
        $translated_parent = $this->get_post_translation($parent_id, $target_lang);
        return $translated_parent ? $translated_parent : 0;
    }
    
    /**
     * Copiar imagen destacada
     */
    private function copy_thumbnail($source_id, $target_id) {
        $thumbnail_id = get_post_thumbnail_id($source_id);
        if ($thumbnail_id) {
            set_post_thumbnail($target_id, $thumbnail_id);
        }
    }
    
    /**
     * Copiar taxonomias traducidas
     */
    private function copy_taxonomies($source_id, $target_id, $target_lang) {
        $post_type = get_post_type($source_id);
        $taxonomies = get_object_taxonomies($post_type);
        
        foreach ($taxonomies as $taxonomy) {
            // Saltar taxonomia de idioma de Polylang
            if ($taxonomy === 'language' || $taxonomy === 'post_translations') {
                continue;
            }
            
            $terms = wp_get_object_terms($source_id, $taxonomy, ['fields' => 'ids']);
            
            if (!empty($terms) && !is_wp_error($terms)) {
                $translated_terms = [];
                
                foreach ($terms as $term_id) {
                    // Intentar obtener termino traducido
                    if (function_exists('pll_get_term')) {
                        $translated_term_id = pll_get_term($term_id, $target_lang);
                        if ($translated_term_id) {
                            $translated_terms[] = $translated_term_id;
                        } else {
                            // Si no hay traduccion, usar el original
                            $translated_terms[] = $term_id;
                        }
                    } else {
                        $translated_terms[] = $term_id;
                    }
                }
                
                if (!empty($translated_terms)) {
                    wp_set_object_terms($target_id, $translated_terms, $taxonomy);
                }
            }
        }
    }
    
    /**
     * Copiar meta datos del post
     */
    private function copy_post_meta($source_id, $target_id, $translated_data = []) {
        $all_meta = get_post_meta($source_id);
        
        // Meta keys que no se deben copiar
        $excluded_keys = [
            '_edit_lock',
            '_edit_last',
            '_wp_old_slug',
            '_wp_old_date',
            // Polylang internals
            '_pll_strings_translations',
        ];
        
        // Meta keys que ya se manejan por separado
        $special_keys = [
            '_elementor_data',
            '_elementor_page_settings',
            '_yoast_wpseo_title',
            '_yoast_wpseo_metadesc',
            'rank_math_title',
            'rank_math_description',
            '_wp_page_template',
        ];
        
        foreach ($all_meta as $key => $values) {
            // Saltar keys excluidas
            if (in_array($key, $excluded_keys)) {
                continue;
            }
            
            // Saltar keys especiales (se manejan por separado)
            if (in_array($key, $special_keys)) {
                continue;
            }
            
            // Copiar todos los valores de este meta key
            foreach ($values as $value) {
                add_post_meta($target_id, $key, maybe_unserialize($value));
            }
        }
        
        // Copiar datos traducidos de SEO si se proporcionan
        if (!empty($translated_data['seo_title'])) {
            // Yoast
            update_post_meta($target_id, '_yoast_wpseo_title', $translated_data['seo_title']);
            // RankMath
            update_post_meta($target_id, 'rank_math_title', $translated_data['seo_title']);
        }
        
        if (!empty($translated_data['seo_description'])) {
            // Yoast
            update_post_meta($target_id, '_yoast_wpseo_metadesc', $translated_data['seo_description']);
            // RankMath
            update_post_meta($target_id, 'rank_math_description', $translated_data['seo_description']);
        }
        
        // Copiar Elementor data traducida si se proporciona
        if (!empty($translated_data['elementor_data'])) {
            $elementor_json = $translated_data['elementor_data'];
            
            // Actualizar referencias a templates (shortcodes, widgets de template, global widgets)
            // Detectar idioma del post destino
            $target_lang = pll_get_post_language($target_id);
            if ($target_lang && class_exists('Replanta_Auto_Translate_Elementor_Parser')) {
                $parser = Replanta_Auto_Translate_Elementor_Parser::instance();
                
                // Decodificar, actualizar referencias, y volver a codificar
                $elementor_array = json_decode($elementor_json, true);
                if (is_array($elementor_array)) {
                    $elementor_array = $parser->update_template_references($elementor_array, $target_lang);
                    $elementor_json = json_encode($elementor_array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    
                    Replanta_Auto_Translate::log("Referencias de templates actualizadas en elementor_data para post $target_id ($target_lang)", 'info');
                }
            }
            
            // IMPORTANTE: wp_slash() es necesario porque WordPress stripslashes en update_post_meta
            // Sin esto, las secuencias \uXXXX se corrompen a uXXXX
            update_post_meta($target_id, '_elementor_data', wp_slash($elementor_json));
            
            // Copiar configuracion de pagina de Elementor
            $page_settings = get_post_meta($source_id, '_elementor_page_settings', true);
            if ($page_settings) {
                update_post_meta($target_id, '_elementor_page_settings', $page_settings);
            }
            
            // Marcar como editado con Elementor
            update_post_meta($target_id, '_elementor_edit_mode', 'builder');
            
            // Copiar version de Elementor
            $elementor_version = get_post_meta($source_id, '_elementor_version', true);
            if ($elementor_version) {
                update_post_meta($target_id, '_elementor_version', $elementor_version);
            }
        }
    }
    
    /**
     * Actualizar traduccion existente
     */
    public function update_translation($post_id, $translated_data) {
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('post_not_found', 'Post no encontrado');
        }
        
        $update_data = ['ID' => $post_id];
        
        if (isset($translated_data['title'])) {
            $update_data['post_title'] = $translated_data['title'];
        }
        
        if (isset($translated_data['content'])) {
            $update_data['post_content'] = $translated_data['content'];
        }
        
        if (isset($translated_data['excerpt'])) {
            $update_data['post_excerpt'] = $translated_data['excerpt'];
        }
        
        if (isset($translated_data['slug'])) {
            $update_data['post_name'] = sanitize_title($translated_data['slug']);
        }
        
        if (isset($translated_data['status'])) {
            $update_data['post_status'] = $translated_data['status'];
        }
        
        $result = wp_update_post($update_data, true);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Actualizar meta datos traducidos
        if (!empty($translated_data['seo_title'])) {
            update_post_meta($post_id, '_yoast_wpseo_title', $translated_data['seo_title']);
            update_post_meta($post_id, 'rank_math_title', $translated_data['seo_title']);
        }
        
        if (!empty($translated_data['seo_description'])) {
            update_post_meta($post_id, '_yoast_wpseo_metadesc', $translated_data['seo_description']);
            update_post_meta($post_id, 'rank_math_description', $translated_data['seo_description']);
        }
        
        if (!empty($translated_data['elementor_data'])) {
            update_post_meta($post_id, '_elementor_data', $translated_data['elementor_data']);
        }
        
        return $post_id;
    }
    
    /**
     * Publicar traduccion (cambiar de draft a publish)
     */
    public function publish_translation($post_id) {
        return wp_update_post([
            'ID' => $post_id,
            'post_status' => 'publish'
        ]);
    }
    
    /**
     * Obtener todos los posts sin traduccion en un idioma
     */
    public function get_untranslated_posts($post_type, $source_lang, $target_lang, $limit = -1) {
        $args = [
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'lang' => $source_lang,
        ];
        
        $posts = get_posts($args);
        $untranslated = [];
        
        foreach ($posts as $post) {
            if (!$this->has_translation($post->ID, $target_lang)) {
                $untranslated[] = $post;
            }
        }
        
        return $untranslated;
    }
    
    /**
     * Contar posts sin traduccion
     */
    public function count_untranslated($post_type, $source_lang, $target_lang) {
        $untranslated = $this->get_untranslated_posts($post_type, $source_lang, $target_lang);
        return count($untranslated);
    }
    
    /**
     * Obtener estadisticas de traduccion
     */
    public function get_translation_stats($source_lang, $target_lang) {
        $stats = [];
        
        $post_types = ['page', 'post', 'elementor_library'];
        
        foreach ($post_types as $post_type) {
            $total = wp_count_posts($post_type);
            $total_published = $total->publish ?? 0;
            
            $untranslated = $this->count_untranslated($post_type, $source_lang, $target_lang);
            $translated = $total_published - $untranslated;
            
            $stats[$post_type] = [
                'total' => $total_published,
                'translated' => $translated,
                'untranslated' => $untranslated,
                'percentage' => $total_published > 0 ? round(($translated / $total_published) * 100) : 0
            ];
        }
        
        return $stats;
    }
    
    /**
     * Obtener nombre de idioma desde codigo
     */
    public function get_language_name($lang_code) {
        if (!$this->is_polylang_active()) {
            return $lang_code;
        }
        
        $languages = $this->get_languages();
        foreach ($languages as $lang) {
            if ($lang->slug === $lang_code) {
                return $lang->name;
            }
        }
        
        return $lang_code;
    }
    
    /**
     * Reparar vinculacion de traduccion entre dos posts
     * Usado para arreglar traducciones que no se vincularon correctamente
     * 
     * @param int $source_post_id ID del post en idioma origen
     * @param int $target_post_id ID del post traducido
     * @param string $source_lang Codigo idioma origen (ej: 'es')
     * @param string $target_lang Codigo idioma destino (ej: 'en')
     * @return bool|WP_Error
     */
    public function repair_translation_link($source_post_id, $target_post_id, $source_lang, $target_lang) {
        if (!$this->is_polylang_active()) {
            return new WP_Error('polylang_not_active', 'Polylang no esta activo');
        }
        
        // Verificar que ambos posts existen
        $source_post = get_post($source_post_id);
        $target_post = get_post($target_post_id);
        
        if (!$source_post || !$target_post) {
            return new WP_Error('post_not_found', 'Uno o ambos posts no existen');
        }
        
        // Primero, desvincular cualquier traduccion previa incorrecta
        $this->unlink_translation($source_post_id, $target_lang);
        $this->unlink_translation($target_post_id, $source_lang);
        
        // Asignar idiomas a cada post
        pll_set_post_language($source_post_id, $source_lang);
        pll_set_post_language($target_post_id, $target_lang);
        
        // Vincular usando el metodo directo (mas fiable)
        $this->link_translations_direct($source_post_id, $target_post_id, $source_lang, $target_lang);
        
        // Limpiar caches
        clean_post_cache($source_post_id);
        clean_post_cache($target_post_id);
        
        if (function_exists('pll_refresh_strings_translations')) {
            pll_refresh_strings_translations();
        }
        
        // Verificar que funciono
        $check = pll_get_post($source_post_id, $target_lang);
        if ($check == $target_post_id) {
            return true;
        }
        
        return new WP_Error('link_failed', 'No se pudo vincular las traducciones');
    }
    
    /**
     * Desvincular traduccion de un post para un idioma especifico
     */
    private function unlink_translation($post_id, $lang_to_remove) {
        global $wpdb;
        
        // Obtener el term_taxonomy_id del grupo de traducciones actual
        $term_taxonomy_id = $wpdb->get_var($wpdb->prepare(
            "SELECT tr.term_taxonomy_id 
             FROM {$wpdb->term_relationships} tr
             INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
             WHERE tr.object_id = %d AND tt.taxonomy = 'post_translations'",
            $post_id
        ));
        
        if ($term_taxonomy_id) {
            // Obtener descripcion actual (array serializado de traducciones)
            $description = $wpdb->get_var($wpdb->prepare(
                "SELECT description FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id = %d",
                $term_taxonomy_id
            ));
            
            $translations = maybe_unserialize($description);
            
            if (is_array($translations) && isset($translations[$lang_to_remove])) {
                // Quitar el post del grupo actual
                $wpdb->delete(
                    $wpdb->term_relationships,
                    ['object_id' => $post_id, 'term_taxonomy_id' => $term_taxonomy_id]
                );
            }
        }
    }
    
    /**
     * Buscar posts que podrian ser traducciones no vinculadas
     * Busca por titulo similar o slug similar
     */
    public function find_orphan_translations($source_lang, $target_lang) {
        global $wpdb;
        
        $orphans = [];
        
        // Obtener todos los posts en idioma destino (incluyendo templates)
        $target_posts = get_posts([
            'post_type' => ['page', 'post', 'elementor_library'],
            'post_status' => 'any',
            'posts_per_page' => -1,
            'lang' => $target_lang,
            'suppress_filters' => false,
        ]);
        
        foreach ($target_posts as $target_post) {
            // Verificar si ya tiene traduccion vinculada al idioma origen
            $linked_source = pll_get_post($target_post->ID, $source_lang);
            
            if (!$linked_source) {
                // Este post no tiene vinculacion - es huerfano
                $orphans[] = [
                    'id' => $target_post->ID,
                    'title' => $target_post->post_title,
                    'type' => $target_post->post_type,
                    'lang' => $target_lang,
                ];
            }
        }
        
        return $orphans;
    }
}
