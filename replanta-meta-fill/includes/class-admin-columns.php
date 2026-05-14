<?php
/**
 * Admin Columns - Añade columna de estado de meta descripción
 *
 * @package Replanta_Meta_Fill
 */

if (!defined('ABSPATH')) {
    exit;
}

class Replanta_Meta_Fill_Admin_Columns {
    
    private static $instance = null;
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Añadir columnas a posts y pages
        add_filter('manage_posts_columns', [$this, 'add_meta_column']);
        add_filter('manage_pages_columns', [$this, 'add_meta_column']);
        
        // Renderizar contenido de la columna
        add_action('manage_posts_custom_column', [$this, 'render_meta_column'], 10, 2);
        add_action('manage_pages_custom_column', [$this, 'render_meta_column'], 10, 2);
        
        // Hacer la columna sortable (opcional)
        add_filter('manage_edit-post_sortable_columns', [$this, 'make_column_sortable']);
        add_filter('manage_edit-page_sortable_columns', [$this, 'make_column_sortable']);
        
        // WooCommerce product support
        add_filter('manage_edit-product_columns', [$this, 'add_meta_column']);
        add_action('manage_product_posts_custom_column', [$this, 'render_meta_column'], 10, 2);
        add_filter('manage_edit-product_sortable_columns', [$this, 'make_column_sortable']);
    }
    
    /**
     * Añadir columna de meta descripción
     *
     * @param array $columns Columnas existentes
     * @return array Columnas modificadas
     */
    public function add_meta_column($columns) {
        // Insertar antes de la columna de fecha
        $new_columns = [];
        
        foreach ($columns as $key => $title) {
            if ($key === 'date') {
                $new_columns['replanta_meta'] = '<span class="dashicons dashicons-search" title="Meta Description"></span> Meta';
            }
            $new_columns[$key] = $title;
        }
        
        return $new_columns;
    }
    
    /**
     * Renderizar contenido de la columna
     *
     * @param string $column_name Nombre de la columna
     * @param int $post_id ID del post
     */
    public function render_meta_column($column_name, $post_id) {
        if ($column_name !== 'replanta_meta') {
            return;
        }
        
        $crawler = Replanta_Meta_Fill_Content_Crawler::instance();
        $meta_description = $crawler->get_existing_meta_description($post_id);
        $generated_at = get_post_meta($post_id, '_replanta_meta_fill_generated_at', true);
        
        echo '<div class="rmf-meta-status" data-post-id="' . esc_attr($post_id) . '">';
        
        if ($meta_description) {
            // Meta descripción existe
            $meta_length = strlen($meta_description);
            $status_class = 'has-meta';
            
            // Verificar longitud óptima
            if ($meta_length < 120) {
                $status_class .= ' warning';
                $length_status = 'Corta';
            } elseif ($meta_length > 160) {
                $status_class .= ' warning';
                $length_status = 'Larga';
            } else {
                $status_class .= ' good';
                $length_status = 'OK';
            }
            
            echo '<div class="rmf-status-indicator ' . esc_attr($status_class) . '">';
            echo '<span class="dashicons dashicons-yes-alt"></span> ';
            echo '<span class="rmf-length">' . $meta_length . ' chars (' . esc_html($length_status) . ')</span>';
            echo '</div>';
            
            // Mostrar preview
            echo '<div class="rmf-meta-preview" title="' . esc_attr($meta_description) . '">';
            echo esc_html(wp_trim_words($meta_description, 10, '...'));
            echo '</div>';
            
            // Mostrar si fue generada por el plugin
            if ($generated_at) {
                $time_ago = human_time_diff(strtotime($generated_at), current_time('timestamp'));
                echo '<div class="rmf-generated-info">Generada hace ' . esc_html($time_ago) . '</div>';
            }
            
            // Botón para regenerar
            echo '<button type="button" class="button button-small rmf-regenerate-btn" data-post-id="' . esc_attr($post_id) . '">';
            echo '<span class="dashicons dashicons-update-alt"></span> Regenerar';
            echo '</button>';
            
        } else {
            // Sin meta descripción
            echo '<div class="rmf-status-indicator missing">';
            echo '<span class="dashicons dashicons-warning"></span> Sin meta';
            echo '</div>';
            
            // Botón para generar
            echo '<button type="button" class="button button-primary button-small rmf-generate-btn" data-post-id="' . esc_attr($post_id) . '">';
            echo '<span class="dashicons dashicons-lightbulb"></span> Generar';
            echo '</button>';
        }
        
        echo '</div>';
    }
    
    /**
     * Hacer la columna ordenable
     *
     * @param array $columns Columnas ordenables
     * @return array Columnas modificadas
     */
    public function make_column_sortable($columns) {
        $columns['replanta_meta'] = 'replanta_meta_status';
        return $columns;
    }
}
