<?php
/**
 * Image Alt Filler - Rellena ALT vacíos de imágenes usando IA o título
 *
 * Soporta:
 * - Imágenes de la Media Library
 * - Imágenes destacadas de productos WooCommerce
 * - Imágenes de galería de productos WooCommerce
 *
 * @package Replanta_Meta_Fill
 */

if (!defined('ABSPATH')) {
    exit;
}

class Replanta_Meta_Fill_Image_Alt_Filler {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Columna ALT en Media Library (modo lista)
        add_filter('manage_media_columns', [$this, 'add_alt_column']);
        add_action('manage_media_custom_column', [$this, 'render_alt_column'], 10, 2);

        // AJAX
        add_action('wp_ajax_rmf_generate_alt', [$this, 'ajax_generate_alt']);
        add_action('wp_ajax_rmf_bulk_generate_alts', [$this, 'ajax_bulk_generate_alts']);
        add_action('wp_ajax_rmf_get_missing_alts', [$this, 'ajax_get_missing_alts']);
    }

    // ------------------------------------------------------------------
    // Columna en Media Library
    // ------------------------------------------------------------------

    public function add_alt_column($columns) {
        $new = [];
        foreach ($columns as $key => $title) {
            $new[$key] = $title;
            if ($key === 'title') {
                $new['rmf_alt'] = '<span class="dashicons dashicons-format-image" title="Alt Text"></span> Alt';
            }
        }
        return $new;
    }

    public function render_alt_column($column_name, $post_id) {
        if ($column_name !== 'rmf_alt') {
            return;
        }

        // Solo imágenes
        if (!wp_attachment_is_image($post_id)) {
            echo '—';
            return;
        }

        $alt = get_post_meta($post_id, '_wp_attachment_image_alt', true);

        echo '<div class="rmf-alt-status" data-attachment-id="' . esc_attr($post_id) . '">';

        if (!empty(trim($alt))) {
            $len = mb_strlen($alt);
            echo '<span style="color:#46b450;" title="' . esc_attr($alt) . '">✅ ' . $len . ' chars</span>';
            echo '<br><small style="color:#646970;">' . esc_html(wp_trim_words($alt, 6, '…')) . '</small>';
            echo '<br><button type="button" class="button button-small rmf-regenerate-alt-btn" data-attachment-id="' . esc_attr($post_id) . '" style="margin-top:4px;">';
            echo '<span class="dashicons dashicons-update-alt" style="font-size:13px;width:13px;height:13px;margin-top:3px;"></span> Regenerar';
            echo '</button>';
        } else {
            echo '<span style="color:#dc3232;">❌ Sin alt</span>';
            echo '<br><button type="button" class="button button-primary button-small rmf-generate-alt-btn" data-attachment-id="' . esc_attr($post_id) . '" style="margin-top:4px;">';
            echo '<span class="dashicons dashicons-lightbulb" style="font-size:13px;width:13px;height:13px;margin-top:3px;"></span> Generar';
            echo '</button>';
        }

        echo '</div>';
    }

    // ------------------------------------------------------------------
    // Generación de ALT
    // ------------------------------------------------------------------

    /**
     * Generar alt text para un attachment
     *
     * @param int  $attachment_id
     * @param bool $use_ai  Si true, usa OpenAI; si false, usa título/filename
     * @return array
     */
    public function generate_alt($attachment_id, $use_ai = false) {
        $attachment = get_post($attachment_id);
        if (!$attachment || $attachment->post_type !== 'attachment') {
            return ['success' => false, 'error' => 'Adjunto no encontrado'];
        }

        if (!wp_attachment_is_image($attachment_id)) {
            return ['success' => false, 'error' => 'No es una imagen'];
        }

        if ($use_ai) {
            return $this->generate_alt_with_ai($attachment_id, $attachment);
        }

        return $this->generate_alt_from_context($attachment_id, $attachment);
    }

    /**
     * Generar ALT inteligente a partir de contexto (sin IA)
     */
    private function generate_alt_from_context($attachment_id, $attachment) {
        $alt = '';

        // 1. Intentar título del attachment si no es genérico
        $title = $attachment->post_title;
        if (!empty($title) && !$this->is_generic_title($title)) {
            $alt = $this->humanize_title($title);
        }

        // 2. Si el attachment es imagen destacada o galería de un producto, usar info del producto
        if (empty($alt)) {
            $product_context = $this->get_product_context($attachment_id);
            if ($product_context) {
                $alt = $product_context;
            }
        }

        // 3. Intentar título del post padre
        if (empty($alt) && $attachment->post_parent) {
            $parent = get_post($attachment->post_parent);
            if ($parent) {
                $alt = $parent->post_title;
            }
        }

        // 4. Último recurso: limpiar el filename
        if (empty($alt)) {
            $filename = pathinfo(get_attached_file($attachment_id), PATHINFO_FILENAME);
            $alt = $this->humanize_title($filename);
        }

        if (empty($alt)) {
            return ['success' => false, 'error' => 'No se pudo generar alt text'];
        }

        update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alt));

        return ['success' => true, 'alt_text' => $alt];
    }

    /**
     * Generar ALT con OpenAI (usa contexto enriquecido)
     */
    private function generate_alt_with_ai($attachment_id, $attachment) {
        $openai = Replanta_Meta_Fill_OpenAI_Handler::instance();
        if (!$openai->is_configured()) {
            // Fallback a contexto
            return $this->generate_alt_from_context($attachment_id, $attachment);
        }

        $context_parts = [];

        // Título del attachment
        $title = $attachment->post_title;
        if (!empty($title) && !$this->is_generic_title($title)) {
            $context_parts[] = "Imagen titulada: {$title}";
        }

        // Caption
        $caption = $attachment->post_excerpt;
        if (!empty($caption)) {
            $context_parts[] = "Caption: {$caption}";
        }

        // Contexto de producto WooCommerce
        $product_ctx = $this->get_product_context($attachment_id);
        if ($product_ctx) {
            $context_parts[] = "Producto asociado: {$product_ctx}";
        }

        // Post padre
        if ($attachment->post_parent) {
            $parent = get_post($attachment->post_parent);
            if ($parent) {
                $context_parts[] = "Publicación asociada: {$parent->post_title}";
            }
        }

        // Filename
        $filename = pathinfo(get_attached_file($attachment_id), PATHINFO_FILENAME);
        $context_parts[] = "Nombre de archivo: {$filename}";

        $context = implode("\n", $context_parts);

        $options = get_option('replanta_meta_fill_options', []);
        $api_key = isset($options['openai_api_key']) ? $options['openai_api_key'] : '';
        $model   = isset($options['openai_model']) ? $options['openai_model'] : 'gpt-4o-mini';
        $temp    = isset($options['temperature']) ? (float) $options['temperature'] : 0.7;

        $prompt = "Genera un texto ALT descriptivo y conciso (máximo 125 caracteres) para una imagen web. "
                . "El ALT debe describir la imagen de forma útil para SEO y accesibilidad. "
                . "No uses comillas. No empieces con 'Imagen de' ni 'Foto de'.\n\n"
                . "Contexto:\n{$context}\n\nTexto ALT:";

        $body = [
            'model'       => $model,
            'messages'    => [
                ['role' => 'system', 'content' => 'Eres un experto en SEO y accesibilidad web. Generas textos ALT descriptivos y concisos para imágenes.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => $temp,
            'max_tokens'  => 80,
        ];

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 30,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return $this->generate_alt_from_context($attachment_id, $attachment);
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200 || !isset($data['choices'][0]['message']['content'])) {
            return $this->generate_alt_from_context($attachment_id, $attachment);
        }

        $alt = trim($data['choices'][0]['message']['content']);
        $alt = trim($alt, '"\'');

        if (mb_strlen($alt) > 125) {
            $alt = mb_substr($alt, 0, 122) . '...';
        }

        update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alt));

        return ['success' => true, 'alt_text' => $alt];
    }

    // ------------------------------------------------------------------
    // Helpers: contexto de producto WooCommerce
    // ------------------------------------------------------------------

    /**
     * Obtener contexto descriptivo si la imagen pertenece a un producto
     */
    private function get_product_context($attachment_id) {
        if (!class_exists('WooCommerce')) {
            return false;
        }

        // ¿Es imagen destacada de un producto?
        global $wpdb;
        $product_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %s AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish') LIMIT 1",
            $attachment_id
        ));

        if ($product_id) {
            return $this->build_product_alt_context((int) $product_id, 'destacada');
        }

        // ¿Está en la galería de un producto?
        $product_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_product_image_gallery' AND meta_value LIKE %s AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish') LIMIT 1",
            '%' . $wpdb->esc_like($attachment_id) . '%'
        ));

        if ($product_id) {
            return $this->build_product_alt_context((int) $product_id, 'galería');
        }

        return false;
    }

    private function build_product_alt_context($product_id, $image_type) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return get_the_title($product_id);
        }

        $parts = [$product->get_name()];

        $cats = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'names']);
        if (!empty($cats) && !is_wp_error($cats)) {
            $parts[] = implode(', ', array_slice($cats, 0, 2));
        }

        $weight = $product->get_weight();
        if (!empty($weight)) {
            $parts[] = $weight . ' ' . get_option('woocommerce_weight_unit', 'kg');
        }

        return implode(' - ', $parts);
    }

    // ------------------------------------------------------------------
    // Helpers: títulos y filenames
    // ------------------------------------------------------------------

    private function is_generic_title($title) {
        $generic = [
            '/^IMG[_-]?\d+$/i',
            '/^DSC[_-]?\d+$/i',
            '/^P\d{6,}$/i',
            '/^Screenshot/i',
            '/^Captura/i',
            '/^image\d*$/i',
            '/^foto\d*$/i',
            '/^photo\d*$/i',
            '/^\d+$/i',
            '/^[a-f0-9]{8,}$/i',
        ];

        foreach ($generic as $pattern) {
            if (preg_match($pattern, $title)) {
                return true;
            }
        }
        return false;
    }

    private function humanize_title($title) {
        // Quitar extensión si la tiene
        $title = preg_replace('/\.[a-z]{2,4}$/i', '', $title);
        // Reemplazar guiones y underscores por espacios
        $title = str_replace(['-', '_'], ' ', $title);
        // Quitar números sueltos al final
        $title = preg_replace('/\s+\d+$/', '', $title);
        // Capitalizar
        $title = ucfirst(mb_strtolower(trim($title)));
        return $title;
    }

    // ------------------------------------------------------------------
    // Queries: imágenes sin ALT
    // ------------------------------------------------------------------

    /**
     * Obtener imágenes sin alt text
     *
     * @param string $scope  'all' | 'products' | 'posts'
     * @param int    $limit
     * @return array
     */
    public function get_images_without_alt($scope = 'all', $limit = 200) {
        global $wpdb;

        $base_query = "
            SELECT p.ID, p.post_title, p.post_parent, p.guid
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
            WHERE p.post_type = 'attachment'
              AND p.post_mime_type LIKE 'image/%%'
              AND p.post_status = 'inherit'
              AND (pm.meta_value IS NULL OR pm.meta_value = '')
        ";

        if ($scope === 'products' && class_exists('WooCommerce')) {
            // Solo imágenes asociadas a productos (destacada o galería)
            $base_query .= " AND (
                p.ID IN (SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product'))
                OR p.post_parent IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product')
            )";
        } elseif ($scope === 'posts') {
            $base_query .= " AND p.post_parent IN (SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('post','page'))";
        }

        $base_query .= " ORDER BY p.ID DESC LIMIT %d";

        return $wpdb->get_results($wpdb->prepare($base_query, $limit));
    }

    // ------------------------------------------------------------------
    // AJAX Handlers
    // ------------------------------------------------------------------

    public function ajax_generate_alt() {
        check_ajax_referer('replanta_meta_fill_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']);
        }

        $attachment_id = isset($_POST['attachment_id']) ? (int) $_POST['attachment_id'] : 0;
        $use_ai = isset($_POST['use_ai']) && $_POST['use_ai'] === '1';

        if (!$attachment_id) {
            wp_send_json_error(['message' => 'ID de adjunto inválido']);
        }

        $result = $this->generate_alt($attachment_id, $use_ai);

        if ($result['success']) {
            wp_send_json_success([
                'message'  => 'Alt text generado',
                'alt_text' => $result['alt_text'],
            ]);
        } else {
            wp_send_json_error(['message' => $result['error']]);
        }
    }

    public function ajax_bulk_generate_alts() {
        check_ajax_referer('replanta_meta_fill_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']);
        }

        $ids    = isset($_POST['attachment_ids']) ? array_map('intval', (array) $_POST['attachment_ids']) : [];
        $use_ai = isset($_POST['use_ai']) && $_POST['use_ai'] === '1';

        if (empty($ids)) {
            wp_send_json_error(['message' => 'No se proporcionaron IDs']);
        }

        // Limitar batch
        $ids = array_slice($ids, 0, $use_ai ? 5 : 20);

        $results = [];
        $ok = 0;
        $err = 0;

        foreach ($ids as $id) {
            $r = $this->generate_alt($id, $use_ai);
            $results[$id] = $r;
            $r['success'] ? $ok++ : $err++;

            if ($use_ai && count($ids) > 1) {
                sleep(1);
            }
        }

        wp_send_json_success([
            'message'       => sprintf('%d generados, %d errores', $ok, $err),
            'results'       => $results,
            'success_count' => $ok,
            'error_count'   => $err,
        ]);
    }

    public function ajax_get_missing_alts() {
        check_ajax_referer('replanta_meta_fill_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']);
        }

        $scope = isset($_POST['scope']) ? sanitize_text_field($_POST['scope']) : 'all';
        $images = $this->get_images_without_alt($scope);

        $data = [];
        foreach ($images as $img) {
            $thumb = wp_get_attachment_image_url($img->ID, 'thumbnail');
            $parent_title = $img->post_parent ? get_the_title($img->post_parent) : '';
            $data[] = [
                'id'           => $img->ID,
                'title'        => $img->post_title,
                'thumbnail'    => $thumb ?: '',
                'parent_title' => $parent_title,
                'parent_id'    => $img->post_parent,
            ];
        }

        wp_send_json_success([
            'count'  => count($data),
            'images' => $data,
        ]);
    }
}
