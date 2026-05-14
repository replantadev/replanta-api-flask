<?php
/**
 * Content Crawler - Extrae y prepara contenido para OpenAI
 *
 * @package Replanta_Meta_Fill
 */

if (!defined('ABSPATH')) {
    exit;
}

class Replanta_Meta_Fill_Content_Crawler {
    
    private static $instance = null;
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Constructor vacío - todo bajo demanda
    }
    
    /**
     * Obtener contenido del post para análisis
     *
     * @param int $post_id ID del post
     * @return array Array con title, content, excerpt, y contexto específico por tipo
     */
    public function get_post_content($post_id) {
        $post = get_post($post_id);
        
        if (!$post) {
            return false;
        }
        
        // Obtener título
        $title = get_the_title($post_id);
        
        // Obtener contenido y limpiarlo
        $content = $post->post_content;
        $content = $this->clean_content($content);
        
        // Obtener excerpt si existe
        $excerpt = $post->post_excerpt;
        if (empty($excerpt)) {
            $excerpt = wp_trim_words($content, 30, '...');
        }
        
        // Base de datos
        $data = [
            'title' => $title,
            'content' => $content,
            'excerpt' => $excerpt,
            'categories' => [],
            'tags' => [],
            'word_count' => str_word_count($content),
        ];
        
        // Contexto específico para WooCommerce products
        if ($post->post_type === 'product' && class_exists('WooCommerce')) {
            $product = wc_get_product($post_id);
            if ($product) {
                // Añadir SKU, precio, categorías de producto
                $data['sku'] = $product->get_sku() ?: 'N/A';
                $data['price'] = $product->get_price() ? wc_price($product->get_price()) : 'Precio no definido';
                
                // Categorías de producto
                $product_cats = wp_get_post_terms($post_id, 'product_cat', ['fields' => 'names']);
                $data['categories'] = !empty($product_cats) ? $product_cats : [];
                
                // Tags de producto
                $product_tags = wp_get_post_terms($post_id, 'product_tag', ['fields' => 'names']);
                $data['tags'] = !empty($product_tags) ? $product_tags : [];
                
                // Descripción corta si existe
                if ($product->get_short_description()) {
                    $short_desc = $this->clean_content($product->get_short_description());
                    $data['short_description'] = $short_desc;
                }
            }
        } else {
            // Para posts y páginas: categorías y tags normales
            $data['categories'] = wp_get_post_categories($post_id, ['fields' => 'names']);
            $data['tags'] = wp_get_post_tags($post_id, ['fields' => 'names']);
        }
        
        return $data;
    }
    
    /**
     * Limpiar contenido HTML y shortcodes
     *
     * @param string $content Contenido raw
     * @return string Contenido limpio
     */
    private function clean_content($content) {
        // Eliminar shortcodes
        $content = strip_shortcodes($content);
        
        // Eliminar bloques de Gutenberg
        $content = preg_replace('/<!-- wp:.*? -->/', '', $content);
        $content = preg_replace('/<!-- \/wp:.*? -->/', '', $content);
        
        // Eliminar HTML
        $content = wp_strip_all_tags($content);
        
        // Eliminar múltiples espacios y saltos de línea
        $content = preg_replace('/\s+/', ' ', $content);
        
        // Trim
        $content = trim($content);
        
        return $content;
    }
    
    /**
     * Preparar contexto optimizado para OpenAI (limitar tokens)
     *
     * @param array $post_data Datos del post
     * @param int $max_words Máximo de palabras a enviar
     * @return string Contexto resumido
     */
    public function prepare_context_for_ai($post_data, $max_words = 500) {
        $content = $post_data['content'];
        
        // Si el contenido es muy largo, tomar inicio y final
        $word_count = str_word_count($content);
        
        if ($word_count > $max_words) {
            $words = explode(' ', $content);
            
            // Tomar primeras 60% y últimas 40%
            $start_words = (int) ($max_words * 0.6);
            $end_words = (int) ($max_words * 0.4);
            
            $start = implode(' ', array_slice($words, 0, $start_words));
            $end = implode(' ', array_slice($words, -$end_words));
            
            $content = $start . ' [...] ' . $end;
        }
        
        // Añadir contexto de categorías/tags si existen
        $context = $content;
        
        if (!empty($post_data['categories'])) {
            $context .= "\n\nCategorías: " . implode(', ', $post_data['categories']);
        }
        
        if (!empty($post_data['tags'])) {
            $context .= "\nEtiquetas: " . implode(', ', $post_data['tags']);
        }
        
        // Contexto adicional para productos
        if (isset($post_data['sku'])) {
            $context .= "\n\nContexto de Producto:";
            $context .= "\n- SKU: " . $post_data['sku'];
            if (isset($post_data['price'])) {
                $context .= "\n- Precio: " . $post_data['price'];
            }
            if (isset($post_data['short_description']) && !empty($post_data['short_description'])) {
                $context .= "\n- Descripción corta: " . $post_data['short_description'];
            }
        }
        
        return $context;
    }
    
    /**
     * Verificar si el post tiene meta descripción
     *
     * @param int $post_id ID del post
     * @return bool|string False si no tiene, string con la meta si existe
     */
    public function get_existing_meta_description($post_id) {
        // Detectar plugin SEO activo
        $seo_plugin = $this->detect_seo_plugin();
        
        $meta_description = false;
        
        switch ($seo_plugin) {
            case 'rankmath':
                $meta_description = get_post_meta($post_id, 'rank_math_description', true);
                break;
                
            case 'yoast':
                $meta_description = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
                break;
                
            case 'aioseo':
                $meta_description = get_post_meta($post_id, '_aioseo_description', true);
                break;
                
            default:
                // Buscar en meta genérica
                $meta_description = get_post_meta($post_id, '_meta_description', true);
                break;
        }
        
        return !empty($meta_description) ? $meta_description : false;
    }
    
    /**
     * Detectar plugin SEO activo
     *
     * @return string Nombre del plugin detectado
     */
    public function detect_seo_plugin() {
        // Verificar opciones guardadas
        $options = get_option('replanta_meta_fill_options', []);
        
        if (isset($options['seo_plugin']) && $options['seo_plugin'] !== 'auto') {
            return $options['seo_plugin'];
        }
        
        // Auto-detección
        if (class_exists('RankMath')) {
            return 'rankmath';
        }
        
        if (defined('WPSEO_VERSION')) {
            return 'yoast';
        }
        
        if (class_exists('AIOSEO\\Plugin\\AIOSEO')) {
            return 'aioseo';
        }
        
        return 'none';
    }
    
    /**
     * Guardar meta descripción en el plugin SEO correspondiente
     *
     * @param int $post_id ID del post
     * @param string $meta_description Meta descripción a guardar
     * @return bool True si se guardó correctamente
     */
    public function save_meta_description($post_id, $meta_description) {
        $seo_plugin = $this->detect_seo_plugin();
        
        Replanta_Meta_Fill::log("Guardando meta para post $post_id en $seo_plugin: " . substr($meta_description, 0, 50) . '...', 'info');
        
        switch ($seo_plugin) {
            case 'rankmath':
                update_post_meta($post_id, 'rank_math_description', $meta_description);
                break;
                
            case 'yoast':
                update_post_meta($post_id, '_yoast_wpseo_metadesc', $meta_description);
                break;
                
            case 'aioseo':
                update_post_meta($post_id, '_aioseo_description', $meta_description);
                break;
                
            default:
                // Guardar en meta genérica
                update_post_meta($post_id, '_meta_description', $meta_description);
                break;
        }
        
        // Registrar timestamp de generación
        update_post_meta($post_id, '_replanta_meta_fill_generated_at', current_time('mysql'));
        
        return true;
    }
}
