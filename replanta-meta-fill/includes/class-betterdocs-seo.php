<?php
/**
 * Taxonomy SEO - Meta descripciones IA para categorías y taxonomías
 * Soporta: BetterDocs, Categorías de blog y otras taxonomías
 *
 * @package Replanta_Meta_Fill
 */

if (!defined('ABSPATH')) {
    exit;
}

class Replanta_Meta_Fill_BetterDocs_SEO {
    
    private static $instance = null;
    private $taxonomies = [];
    private $rankmath_vars_registered = false;
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', [$this, 'detect_taxonomies'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        
        // Rank Math integration - registrar variable con el método correcto
        add_action('rank_math/loaded', [$this, 'register_rankmath_variable'], 11);
        add_filter('rank_math/vars/register_extra_replacements', [$this, 'register_rankmath_variable'], 11);
        add_filter('rank_math/vars/replacements', [$this, 'replace_rankmath_variable'], 11, 2);
        
        // Hook adicional: reemplazar directamente en la meta description antes de output
        add_filter('rank_math/frontend/description', [$this, 'force_replace_description'], 999);
        
        // AJAX
        add_action('wp_ajax_rmf_generate_betterdocs_meta', [$this, 'ajax_generate_meta']);
    }
    
    /**
     * Detectar taxonomías: BetterDocs + Categorías de blog
     */
    public function detect_taxonomies() {
        $candidates = [
            'doc_category',
            'betterdocs_category',
            'docs_category',
            'knowledge_base',
            'category', // Categorías de blog
        ];
        
        foreach ($candidates as $tax) {
            if (taxonomy_exists($tax)) {
                $this->taxonomies[] = $tax;
                
                // Hooks
                add_action("{$tax}_add_form_fields", [$this, 'add_term_field']);
                add_action("{$tax}_edit_form_fields", [$this, 'edit_term_field']);
                add_action("created_{$tax}", [$this, 'save_term_field']);
                add_action("edited_{$tax}", [$this, 'save_term_field']);
                
                // Columna
                add_filter("manage_edit-{$tax}_columns", [$this, 'add_column']);
                add_filter("manage_{$tax}_custom_column", [$this, 'render_column'], 10, 3);
            }
        }
        
        if (empty($this->taxonomies)) {
            $this->taxonomies = ['doc_category'];
        }
    }
    
    /**
     * Campo en añadir término
     */
    public function add_term_field($taxonomy) {
        ?>
        <div class="form-field term-betterdocs-meta-wrap">
            <label for="rmf_betterdocs_meta">
                <?php esc_html_e('Meta Description SEO', 'replanta-meta-fill'); ?>
            </label>
            <textarea 
                name="rmf_betterdocs_meta" 
                id="rmf_betterdocs_meta" 
                rows="4" 
                maxlength="320" 
                style="width:100%;"
                placeholder="Descripción SEO para buscadores (120-155 caracteres)"></textarea>
            <p class="description">
                <?php esc_html_e('Meta descripción exclusiva para SEO. Usa en Rank Math:', 'replanta-meta-fill'); ?>
                <code>%term_ai_description%</code>
            </p>
        </div>
        <?php
    }
    
    /**
     * Campo en editar término
     */
    public function edit_term_field($term) {
        $value = get_term_meta($term->term_id, '_rmf_betterdocs_meta', true);
        $char_count = $value ? strlen($value) : 0;
        
        $status_class = 'empty';
        $status_text = __('Sin meta SEO', 'replanta-meta-fill');
        
        if ($char_count > 0) {
            if ($char_count >= 120 && $char_count <= 160) {
                $status_class = 'good';
                $status_text = __('Longitud óptima', 'replanta-meta-fill');
            } elseif ($char_count < 120) {
                $status_class = 'warning';
                $status_text = __('Muy corta', 'replanta-meta-fill');
            } else {
                $status_class = 'warning';
                $status_text = __('Muy larga', 'replanta-meta-fill');
            }
        }
        ?>
        <tr class="form-field term-betterdocs-meta-wrap">
            <th scope="row">
                <label for="rmf_betterdocs_meta">
                    <?php esc_html_e('Meta Description SEO', 'replanta-meta-fill'); ?>
                </label>
            </th>
            <td>
                <textarea 
                    name="rmf_betterdocs_meta" 
                    id="rmf_betterdocs_meta" 
                    rows="4" 
                    maxlength="320" 
                    style="width:100%; max-width: 600px;"><?php echo esc_textarea($value); ?></textarea>
                
                <div class="rmf-bd-meta-info" style="margin-top: 8px;">
                    <span class="rmf-bd-char-count">
                        <strong><?php echo $char_count; ?></strong> caracteres
                    </span>
                    <span class="rmf-bd-status rmf-bd-status-<?php echo esc_attr($status_class); ?>" style="margin-left: 15px;">
                        <?php echo esc_html($status_text); ?>
                    </span>
                </div>
                
                <p style="margin-top: 10px;">
                    <button 
                        type="button" 
                        class="button rmf-bd-generate-ai" 
                        data-term-id="<?php echo esc_attr($term->term_id); ?>" 
                        data-taxonomy="<?php echo esc_attr($term->taxonomy); ?>">
                        <span class="dashicons dashicons-lightbulb"></span> 
                        <?php esc_html_e('Generar con IA', 'replanta-meta-fill'); ?>
                    </button>
                    <?php if ($value): ?>
                    <button 
                        type="button" 
                        class="button rmf-bd-regenerate-ai" 
                        data-term-id="<?php echo esc_attr($term->term_id); ?>" 
                        data-taxonomy="<?php echo esc_attr($term->taxonomy); ?>">
                        <span class="dashicons dashicons-update-alt"></span> 
                        <?php esc_html_e('Regenerar', 'replanta-meta-fill'); ?>
                    </button>
                    <?php endif; ?>
                </p>
                
                <p class="description">
                    <?php esc_html_e('Meta SEO específica para esta categoría. No modifica la descripción pública.', 'replanta-meta-fill'); ?>
                    <br>
                    <strong><?php esc_html_e('Variable Rank Math:', 'replanta-meta-fill'); ?></strong> 
                    <code>%term_ai_description%</code>
                </p>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Guardar campo
     */
    public function save_term_field($term_id) {
        // Verificar que es un término, no un post
        if (!$term_id || !is_numeric($term_id)) {
            return;
        }
        
        // Solo procesar si viene de nuestro formulario
        if (!isset($_POST['rmf_betterdocs_meta'])) {
            return;
        }
        
        // Verificar que el term existe antes de guardar
        $term = get_term($term_id);
        if (!$term || is_wp_error($term)) {
            return;
        }
        
        $value = sanitize_textarea_field(wp_unslash($_POST['rmf_betterdocs_meta']));
        update_term_meta($term_id, '_rmf_betterdocs_meta', $value);
    }
    
    /**
     * Añadir columna
     */
    public function add_column($columns) {
        $new_columns = [];
        
        foreach ($columns as $key => $title) {
            if ($key === 'posts') {
                $new_columns['rmf_bd_meta'] = '<span class="dashicons dashicons-search"></span> Meta SEO';
            }
            $new_columns[$key] = $title;
        }
        
        return $new_columns;
    }
    
    /**
     * Renderizar columna
     */
    public function render_column($content, $column_name, $term_id) {
        if ($column_name !== 'rmf_bd_meta') {
            return $content;
        }
        
        $term = get_term($term_id);
        $meta = get_term_meta($term_id, '_rmf_betterdocs_meta', true);
        
        if (empty($meta)) {
            echo '<span style="color: #dc3232;">❌ Sin meta</span><br>';
            echo '<button type="button" class="button button-small rmf-bd-generate-ai-inline" ';
            echo 'data-term-id="' . esc_attr($term_id) . '" ';
            echo 'data-taxonomy="' . esc_attr($term->taxonomy) . '" ';
            echo 'style="margin-top: 5px;">';
            echo '<span class="dashicons dashicons-lightbulb" style="font-size: 13px; width: 13px; height: 13px; margin-top: 3px;"></span> ';
            echo 'Generar';
            echo '</button>';
        } else {
            $length = strlen($meta);
            
            if ($length >= 120 && $length <= 160) {
                $icon = '<span style="color: #46b450;">✅</span>';
            } else {
                $icon = '<span style="color: #f0b849;">⚠️</span>';
            }
            
            echo $icon . ' <strong>' . $length . '</strong> chars<br>';
            echo '<small style="color: #646970;">' . esc_html(wp_trim_words($meta, 8, '...')) . '</small><br>';
            echo '<button type="button" class="button button-small rmf-bd-regenerate-ai-inline" ';
            echo 'data-term-id="' . esc_attr($term_id) . '" ';
            echo 'data-taxonomy="' . esc_attr($term->taxonomy) . '" ';
            echo 'style="margin-top: 5px;">';
            echo '<span class="dashicons dashicons-update-alt" style="font-size: 13px; width: 13px; height: 13px; margin-top: 3px;"></span> ';
            echo 'Regenerar';
            echo '</button>';
        }
        
        return '';
    }
    
    /**
     * Registrar variable Rank Math
     * Formato correcto según documentación de Rank Math v1.0.34+
     */
    public function register_rankmath_variable($vars = []) {
        $definitions = $this->get_rankmath_variable_definitions();

        if (!$this->rankmath_vars_registered && function_exists('rank_math_register_var_replacement')) {
            foreach ($definitions as $definition) {
                rank_math_register_var_replacement(
                    $definition['variable'],
                    $definition,
                    [$this, 'get_term_ai_description']
                );
            }
            $this->rankmath_vars_registered = true;
        }

        if (is_array($vars)) {
            foreach ($definitions as $definition) {
                $vars[$definition['variable']] = $definition;
            }
        }

        return $vars;
    }

    /**
     * Definiciones para las variables personalizadas de Rank Math
     *
     * @return array
     */
    private function get_rankmath_variable_definitions() {
        $example = esc_html__('Aprende WordPress con tutoriales paso a paso. Guías completas para principiantes.', 'replanta-meta-fill');

        return [
            'term_ai_description' => [
                'name'        => esc_html__('AI Generated Term Description', 'replanta-meta-fill'),
                'description' => esc_html__('Meta descripción generada con IA para categorías y taxonomías', 'replanta-meta-fill'),
                'variable'    => 'term_ai_description',
                'example'     => $example,
            ],
            'bdcat_description' => [
                'name'        => esc_html__('BetterDocs Meta Description', 'replanta-meta-fill'),
                'description' => esc_html__('Meta SEO exclusiva para BetterDocs y categorías de blog', 'replanta-meta-fill'),
                'variable'    => 'bdcat_description',
                'example'     => $example,
            ],
        ];
    }
    
    /**
     * Obtener descripción IA del término (callback para Rank Math)
     */
    public function get_term_ai_description() {
        // Si estamos editando un post (no una taxonomía), retornar string vacío
        global $pagenow;
        if (is_admin() && ($pagenow === 'post.php' || $pagenow === 'post-new.php')) {
            return '';
        }
        
        $term = null;
        
        // Frontend: obtener término actual
        if (!empty($this->taxonomies) && is_tax($this->taxonomies)) {
            $term = get_queried_object();
        }
        // Admin: obtener de parámetros
        elseif (is_admin() && isset($_GET['taxonomy'], $_GET['tag_ID'])) {
            $term_id = absint($_GET['tag_ID']);
            $taxonomy = sanitize_text_field($_GET['taxonomy']);
            if (in_array($taxonomy, $this->taxonomies, true)) {
                $term = get_term($term_id, $taxonomy);
            }
        }
        
        if (!$term || is_wp_error($term)) {
            return '';
        }
        return $this->get_meta_description_for_term($term);
    }

    /**
     * Obtener la meta description limpia para un término
     *
     * @param WP_Term|null $term
     * @return string
     */
    private function get_meta_description_for_term($term) {
        if (!$term || empty($term->term_id)) {
            return '';
        }

        $value = get_term_meta($term->term_id, '_rmf_betterdocs_meta', true);
        if (empty($value)) {
            return '';
        }

        return $this->sanitize_meta_description($value);
    }

    /**
     * Sanitiza la meta description truncando y eliminando etiquetas
     *
     * @param string $value
     * @return string
     */
    private function sanitize_meta_description($value) {
        $value = trim(wp_strip_all_tags((string) $value));

        if ($value === '') {
            return '';
        }

        if (mb_strlen($value) > 160) {
            $value = mb_substr($value, 0, 157) . '...';
        }

        return $value;
    }
    
    /**
     * Reemplazar variable
     * Compatible con frontend y admin
     */
    public function replace_rankmath_variable($replacements, $vars = null) {
        $term = null;
        
        // Método 1: Obtener término desde queried object (frontend)
        if (is_category() || is_tax()) {
            $term = get_queried_object();
            if ($term && isset($term->taxonomy) && in_array($term->taxonomy, $this->taxonomies, true)) {
                // Término válido encontrado
            } else {
                $term = null;
            }
        }
        
        // Método 2: Admin edit screen
        if (!$term && is_admin() && isset($_GET['taxonomy'], $_GET['tag_ID'])) {
            $taxonomy = sanitize_text_field($_GET['taxonomy']);
            $term_id = absint($_GET['tag_ID']);
            
            if (in_array($taxonomy, $this->taxonomies, true)) {
                $term = get_term($term_id, $taxonomy);
            }
        }
        
        // Método 3: Fallback wp_query
        if (!$term) {
            global $wp_query;
            if ($wp_query && isset($wp_query->queried_object)) {
                $queried = $wp_query->queried_object;
                if (is_object($queried) && isset($queried->taxonomy) && in_array($queried->taxonomy, $this->taxonomies, true)) {
                    $term = $queried;
                }
            }
        }
        
        // Si no hay término, retornar vacío
        if (!$term || is_wp_error($term)) {
            return $replacements;
        }
        
        // Obtener meta descripción
        $meta_desc = $this->get_meta_description_for_term($term);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            $has_meta = !empty($meta_desc);
            error_log('[BetterDocs SEO] Meta desc found: ' . ($has_meta ? 'YES (' . mb_strlen($meta_desc) . ' chars)' : 'NO'));
        }

        if (!empty($meta_desc)) {
            $replacements['%term_ai_description%'] = $meta_desc;
            $replacements['%bdcat_description%'] = $meta_desc;

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[BetterDocs SEO] ✅ Reemplazando term/bdcat con: ' . substr($meta_desc, 0, 50) . '...');
            }
        } else {
            $replacements['%term_ai_description%'] = '';
            $replacements['%bdcat_description%'] = '';

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[BetterDocs SEO] ⚠️ Meta vacía o no encontrada para term_id: ' . $term->term_id);
            }
        }

        return $replacements;
    }
    
    /**
     * Forzar reemplazo directo en la meta description
     * Este hook se ejecuta DESPUÉS de que Rank Math procesa los placeholders
     * Si detecta que la descripción está vacía o contiene el placeholder sin procesar,
     * lo reemplaza directamente con nuestra meta generada
     */
    public function force_replace_description($description) {
        // Solo en frontend de taxonomías, NO en admin de posts
        if (is_admin()) {
            return $description;
        }
        
        // Solo en taxonomías configuradas (incluyendo categorías normales)
        $is_supported_taxonomy = false;
        if (is_category() && in_array('category', $this->taxonomies, true)) {
            $is_supported_taxonomy = true;
        } elseif (is_tax($this->taxonomies)) {
            $is_supported_taxonomy = true;
        }
        
        if (!$is_supported_taxonomy) {
            return $description;
        }
        
        $term = get_queried_object();
        
        if (!$term || is_wp_error($term)) {
            return $description;
        }
        
        // Si la descripción está vacía o contiene el placeholder sin procesar
        $needs_replacement = empty($description)
            || strpos($description, '%term_ai_description%') !== false
            || strpos($description, '%bdcat_description%') !== false;
        
        if ($needs_replacement) {
            $meta_desc = $this->get_meta_description_for_term($term);

            if (!empty($meta_desc)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[BetterDocs SEO] ✅ FORZANDO reemplazo directo en rank_math/frontend/description');
                    error_log('[BetterDocs SEO] Descripción original: ' . ($description ? 'existe' : 'vacía'));
                    error_log('[BetterDocs SEO] Nueva descripción: ' . substr($meta_desc, 0, 50) . '...');
                }
                
                return $meta_desc;
            }
        }
        
        return $description;
    }
    
    /**
     * AJAX: Generar meta
     */
    public function ajax_generate_meta() {
        check_ajax_referer('replanta_meta_fill_nonce', 'nonce');
        
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(['message' => __('Permisos insuficientes', 'replanta-meta-fill')]);
        }
        
        $term_id = isset($_POST['term_id']) ? intval($_POST['term_id']) : 0;
        $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field($_POST['taxonomy']) : '';
        
        if (!$term_id || !$taxonomy) {
            wp_send_json_error(['message' => __('Datos inválidos', 'replanta-meta-fill')]);
        }
        
        $term = get_term($term_id, $taxonomy);
        
        if (!$term || is_wp_error($term)) {
            wp_send_json_error(['message' => __('Término no encontrado', 'replanta-meta-fill')]);
        }
        
        // Preparar contexto
        $context = $this->prepare_term_context($term);
        
        // Generar con OpenAI
        $result = $this->generate_with_openai($context);
        
        if ($result['success']) {
            update_term_meta($term_id, '_rmf_betterdocs_meta', $result['meta_description']);
            
            wp_send_json_success([
                'message' => __('Meta descripción generada', 'replanta-meta-fill'),
                'meta_description' => $result['meta_description'],
                'length' => strlen($result['meta_description'])
            ]);
        } else {
            wp_send_json_error([
                'message' => isset($result['error']) ? $result['error'] : __('Error desconocido', 'replanta-meta-fill')
            ]);
        }
    }
    
    /**
     * Preparar contexto del término
     */
    private function prepare_term_context($term) {
        $context = [
            'name' => $term->name,
            'description' => $term->description,
            'count' => $term->count,
        ];
        
        // Obtener posts de ejemplo según la taxonomía
        $post_type = ($term->taxonomy === 'category') ? 'post' : 'docs';
        
        $posts = get_posts([
            'post_type' => $post_type,
            'tax_query' => [
                [
                    'taxonomy' => $term->taxonomy,
                    'field' => 'term_id',
                    'terms' => $term->term_id,
                ],
            ],
            'posts_per_page' => 5,
        ]);
        
        if (!empty($posts)) {
            $titles = [];
            foreach ($posts as $post) {
                $titles[] = $post->post_title;
            }
            $label = ($post_type === 'post') ? 'sample_posts' : 'sample_docs';
            $context[$label] = $titles;
        }
        
        return $context;
    }
    
    /**
     * Generar con OpenAI
     */
    private function generate_with_openai($context) {
        $openai_handler = Replanta_Meta_Fill_OpenAI_Handler::instance();
        
        if (!$openai_handler->is_configured()) {
            return [
                'success' => false,
                'error' => __('OpenAI no configurado. Ve a Meta Fill > Configuración.', 'replanta-meta-fill')
            ];
        }
        
        $prompt = $this->build_category_prompt($context);
        
        $options = get_option('replanta_meta_fill_options', []);
        $max_length = isset($options['max_length']) ? (int) $options['max_length'] : 155;
        
        $url = 'https://api.openai.com/v1/chat/completions';
        
        $body = [
            'model' => isset($options['openai_model']) ? $options['openai_model'] : 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Eres un experto en SEO y documentación técnica. Creas meta descripciones atractivas.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => isset($options['temperature']) ? (float) $options['temperature'] : 0.7,
            'max_tokens' => 200,
        ];
        
        $api_key = isset($options['openai_api_key']) ? $options['openai_api_key'] : '';
        
        $response = wp_remote_post($url, [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => json_encode($body),
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => __('Error de conexión:', 'replanta-meta-fill') . ' ' . $response->get_error_message()
            ];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);
        
        if ($response_code !== 200) {
            $error_message = isset($data['error']['message']) ? $data['error']['message'] : __('Error desconocido', 'replanta-meta-fill');
            return [
                'success' => false,
                'error' => "Error API ($response_code): $error_message"
            ];
        }
        
        if (!isset($data['choices'][0]['message']['content'])) {
            return [
                'success' => false,
                'error' => __('Respuesta inválida de OpenAI', 'replanta-meta-fill')
            ];
        }
        
        $meta_description = trim($data['choices'][0]['message']['content']);
        $meta_description = trim($meta_description, '"\'');
        
        if (strlen($meta_description) > $max_length + 10) {
            $meta_description = substr($meta_description, 0, $max_length);
            $last_space = strrpos($meta_description, ' ');
            if ($last_space !== false) {
                $meta_description = substr($meta_description, 0, $last_space) . '...';
            }
        }
        
        return [
            'success' => true,
            'meta_description' => $meta_description,
        ];
    }
    
    /**
     * Construir prompt para categorías
     */
    private function build_category_prompt($context) {
        $options = get_option('replanta_meta_fill_options', []);
        $max_length = isset($options['max_length']) ? (int) $options['max_length'] : 155;
        
        $type_label = isset($context['sample_posts']) ? 'CATEGORÍA de blog' : 'CATEGORÍA de documentación técnica';
        $prompt = "Genera una meta descripción SEO atractiva (máximo {$max_length} caracteres) para una {$type_label}.\n\n";
        $prompt .= "Nombre: {$context['name']}\n";
        
        if (!empty($context['description'])) {
            $prompt .= "Descripción: {$context['description']}\n";
        }
        
        if (!empty($context['sample_docs'])) {
            $prompt .= "Documentos de ejemplo:\n";
            foreach ($context['sample_docs'] as $title) {
                $prompt .= "- {$title}\n";
            }
        }
        
        if (!empty($context['sample_posts'])) {
            $prompt .= "Posts de ejemplo:\n";
            foreach ($context['sample_posts'] as $title) {
                $prompt .= "- {$title}\n";
            }
        }
        
        $content_type = isset($context['sample_posts']) ? 'artículos/posts' : 'documentación/guías';
        $prompt .= "\nDebe ser clara, persuasiva y mencionar que es una categoría de {$content_type}.\n";
        $prompt .= "Meta descripción:";
        
        return $prompt;
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets($hook) {
        if (!in_array($hook, ['edit-tags.php', 'term.php'])) {
            return;
        }
        
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->taxonomy, $this->taxonomies)) {
            return;
        }
        
        wp_add_inline_style('common', '
            .rmf-bd-status-empty { color: #dc3232; }
            .rmf-bd-status-good { color: #46b450; }
            .rmf-bd-status-warning { color: #f0b849; }
            .rmf-bd-char-count { font-weight: 600; }
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        ');
        
        wp_add_inline_script('common', $this->get_inline_js());
    }
    
    /**
     * JavaScript inline
     */
    private function get_inline_js() {
        $nonce = wp_create_nonce('replanta_meta_fill_nonce');
        
        return "
        jQuery(document).ready(function($) {
            // Contador
            $('#rmf_betterdocs_meta').on('input', function() {
                var count = $(this).val().length;
                var \$counter = $('.rmf-bd-char-count strong');
                var \$status = $('.rmf-bd-status');
                
                if (\$counter.length) {
                    \$counter.text(count);
                    
                    if (count >= 120 && count <= 160) {
                        \$status.removeClass('rmf-bd-status-warning rmf-bd-status-empty')
                               .addClass('rmf-bd-status-good')
                               .text('Longitud óptima');
                    } else if (count < 120 && count > 0) {
                        \$status.removeClass('rmf-bd-status-good rmf-bd-status-empty')
                               .addClass('rmf-bd-status-warning')
                               .text('Muy corta');
                    } else if (count > 160) {
                        \$status.removeClass('rmf-bd-status-good rmf-bd-status-empty')
                               .addClass('rmf-bd-status-warning')
                               .text('Muy larga');
                    }
                }
            });
            
            // Generar IA (formulario de edición)
            $('.rmf-bd-generate-ai, .rmf-bd-regenerate-ai').on('click', function(e) {
                e.preventDefault();
                
                var \$btn = $(this);
                var termId = \$btn.data('term-id');
                var taxonomy = \$btn.data('taxonomy');
                
                \$btn.prop('disabled', true).text('Generando...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rmf_generate_betterdocs_meta',
                        nonce: '{$nonce}',
                        term_id: termId,
                        taxonomy: taxonomy
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#rmf_betterdocs_meta').val(response.data.meta_description).trigger('input');
                            alert(response.data.message + '\\n\\nLongitud: ' + response.data.length + ' caracteres');
                            location.reload();
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                        \$btn.prop('disabled', false).html('<span class=\"dashicons dashicons-lightbulb\"></span> Generar con IA');
                    },
                    error: function() {
                        alert('Error de conexión');
                        \$btn.prop('disabled', false).html('<span class=\"dashicons dashicons-lightbulb\"></span> Generar con IA');
                    }
                });
            });
            
            // Generar IA (lista inline)
            $(document).on('click', '.rmf-bd-generate-ai-inline, .rmf-bd-regenerate-ai-inline', function(e) {
                e.preventDefault();
                
                var \$btn = $(this);
                var termId = \$btn.data('term-id');
                var taxonomy = \$btn.data('taxonomy');
                var originalHtml = \$btn.html();
                
                \$btn.prop('disabled', true).html('<span class=\"dashicons dashicons-update-alt\" style=\"animation: spin 1s linear infinite;\"></span> Generando...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rmf_generate_betterdocs_meta',
                        nonce: '{$nonce}',
                        term_id: termId,
                        taxonomy: taxonomy
                    },
                    success: function(response) {
                        if (response.success) {
                            // Recargar la página para mostrar el nuevo estado
                            location.reload();
                        } else {
                            alert('Error: ' + response.data.message);
                            \$btn.prop('disabled', false).html(originalHtml);
                        }
                    },
                    error: function() {
                        alert('Error de conexión con el servidor');
                        \$btn.prop('disabled', false).html(originalHtml);
                    }
                });
            });
        });
        ";
    }
}
