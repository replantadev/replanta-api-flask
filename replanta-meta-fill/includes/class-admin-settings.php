<?php
/**
 * Admin Settings - Página de configuración del plugin
 *
 * @package Replanta_Meta_Fill
 */

if (!defined('ABSPATH')) {
    exit;
}

class Replanta_Meta_Fill_Admin_Settings {
    
    private static $instance = null;
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }
    
    /**
     * Añadir menú de administración
     */
    public function add_admin_menu() {
        add_menu_page(
            'Replanta Meta Fill',
            'Meta Fill',
            'manage_options',
            'replanta-meta-fill',
            [$this, 'render_settings_page'],
            'dashicons-editor-quote',
            80
        );
        
        add_submenu_page(
            'replanta-meta-fill',
            'Configuración',
            'Configuración',
            'manage_options',
            'replanta-meta-fill',
            [$this, 'render_settings_page']
        );
        
        add_submenu_page(
            'replanta-meta-fill',
            'Generación Masiva',
            'Generación Masiva',
            'manage_options',
            'replanta-meta-fill-bulk',
            [$this, 'render_bulk_page']
        );
        
        add_submenu_page(
            'replanta-meta-fill',
            'ALT Imágenes',
            'ALT Imágenes',
            'manage_options',
            'replanta-meta-fill-alts',
            [$this, 'render_alts_page']
        );
    }
    
    /**
     * Registrar configuraciones
     */
    public function register_settings() {
        register_setting(
            'replanta_meta_fill_options',
            'replanta_meta_fill_options',
            [$this, 'sanitize_options']
        );
        
        // Sección: API de OpenAI
        add_settings_section(
            'rmf_openai_section',
            'Configuración de OpenAI',
            [$this, 'render_openai_section'],
            'replanta-meta-fill'
        );
        
        add_settings_field(
            'openai_api_key',
            'API Key de OpenAI',
            [$this, 'render_api_key_field'],
            'replanta-meta-fill',
            'rmf_openai_section'
        );
        
        add_settings_field(
            'openai_model',
            'Modelo de OpenAI',
            [$this, 'render_model_field'],
            'replanta-meta-fill',
            'rmf_openai_section'
        );
        
        add_settings_field(
            'temperature',
            'Creatividad (Temperature)',
            [$this, 'render_temperature_field'],
            'replanta-meta-fill',
            'rmf_openai_section'
        );
        
        // Sección: Configuración de generación
        add_settings_section(
            'rmf_generation_section',
            'Configuración de Generación',
            [$this, 'render_generation_section'],
            'replanta-meta-fill'
        );
        
        add_settings_field(
            'max_length',
            'Longitud máxima (caracteres)',
            [$this, 'render_max_length_field'],
            'replanta-meta-fill',
            'rmf_generation_section'
        );
        
        add_settings_field(
            'prompt_template',
            'Plantilla de Prompt',
            [$this, 'render_prompt_template_field'],
            'replanta-meta-fill',
            'rmf_generation_section'
        );
        
        add_settings_field(
            'auto_generate',
            'Generación automática',
            [$this, 'render_auto_generate_field'],
            'replanta-meta-fill',
            'rmf_generation_section'
        );
        
        add_settings_field(
            'use_ai_for_alts',
            'ALT de imágenes con IA',
            [$this, 'render_use_ai_for_alts_field'],
            'replanta-meta-fill',
            'rmf_generation_section'
        );
        
        // Sección: Compatibilidad SEO
        add_settings_section(
            'rmf_seo_section',
            'Compatibilidad con Plugins SEO',
            [$this, 'render_seo_section'],
            'replanta-meta-fill'
        );
        
        add_settings_field(
            'seo_plugin',
            'Plugin SEO',
            [$this, 'render_seo_plugin_field'],
            'replanta-meta-fill',
            'rmf_seo_section'
        );
    }
    
    /**
     * Renderizar página de configuración
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Verificar si OpenAI está configurado
        $openai = Replanta_Meta_Fill_OpenAI_Handler::instance();
        $is_configured = $openai->is_configured();
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php if (!$is_configured): ?>
            <div class="notice notice-warning">
                <p><strong>⚠️ Configuración requerida:</strong> Por favor, introduce tu API key de OpenAI para comenzar a generar meta descripciones.</p>
            </div>
            <?php endif; ?>
            
            <?php settings_errors('replanta_meta_fill_options'); ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('replanta_meta_fill_options');
                do_settings_sections('replanta-meta-fill');
                submit_button('Guardar Configuración');
                ?>
            </form>
            
            <hr>
            
            <h2>Estado del Sistema</h2>
            <table class="widefat striped">
                <tbody>
                    <tr>
                        <td><strong>Plugin SEO detectado:</strong></td>
                        <td>
                            <?php
                            $crawler = Replanta_Meta_Fill_Content_Crawler::instance();
                            $seo_plugin = $crawler->detect_seo_plugin();
                            
                            $seo_names = [
                                'rankmath' => 'Rank Math',
                                'yoast' => 'Yoast SEO',
                                'aioseo' => 'All in One SEO',
                                'none' => 'Ninguno (se usarán meta fields genéricos)'
                            ];
                            
                            echo esc_html($seo_names[$seo_plugin] ?? $seo_plugin);
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Estado OpenAI:</strong></td>
                        <td>
                            <?php echo $is_configured ? '✅ Configurado' : '❌ No configurado'; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Versión del plugin:</strong></td>
                        <td><?php echo esc_html(REPLANTA_META_FILL_VERSION); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Renderizar página de generación masiva
     */
    public function render_bulk_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Obtener posts sin meta descripción
        $crawler = Replanta_Meta_Fill_Content_Crawler::instance();
        $seo_plugin = $crawler->detect_seo_plugin();
        
        $meta_keys = [
            'rankmath' => 'rank_math_description',
            'yoast' => '_yoast_wpseo_metadesc',
            'aioseo' => '_aioseo_description',
            'none' => '_meta_description',
        ];
        
        $meta_key = $meta_keys[$seo_plugin] ?? '_meta_description';
        
        // Query de posts sin meta (separado por tipo)
        $posts_without_meta = get_posts([
            'post_type' => ['post', 'page', 'product'],
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => $meta_key,
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => $meta_key,
                    'value' => '',
                    'compare' => '=',
                ],
            ],
        ]);
        
        // Contar por tipo
        $counts = [
            'post' => 0,
            'page' => 0,
            'product' => 0,
            'total' => count($posts_without_meta),
        ];
        
        foreach ($posts_without_meta as $post) {
            if (isset($counts[$post->post_type])) {
                $counts[$post->post_type]++;
            }
        }
        
        ?>
        <div class="wrap">
            <h1>Generación Masiva de Meta Descripciones</h1>
            
            <div class="card" style="margin-bottom: 20px;">
                <h2>Filtrar por tipo</h2>
                <p style="margin-bottom: 15px;">
                    <label style="margin-right: 20px;">
                        <input type="radio" name="rmf_meta_scope" value="all" checked data-count="<?php echo $counts['total']; ?>"> 
                        <strong>Todos</strong> (<?php echo $counts['total']; ?>)
                    </label>
                    <label style="margin-right: 20px;">
                        <input type="radio" name="rmf_meta_scope" value="post" data-count="<?php echo $counts['post']; ?>"> 
                        <strong>📰 Posts</strong> (<?php echo $counts['post']; ?>)
                    </label>
                    <label style="margin-right: 20px;">
                        <input type="radio" name="rmf_meta_scope" value="page" data-count="<?php echo $counts['page']; ?>"> 
                        <strong>📄 Páginas</strong> (<?php echo $counts['page']; ?>)
                    </label>
                    <?php if ($counts['product'] > 0 || class_exists('WooCommerce')): ?>
                    <label>
                        <input type="radio" name="rmf_meta_scope" value="product" data-count="<?php echo $counts['product']; ?>"> 
                        <strong>🛒 Productos</strong> (<?php echo $counts['product']; ?>)
                    </label>
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="card">
                <h2>Items sin Meta Descripción <span id="rmf-filtered-count"></span></h2>
                <p id="rmf-stats-text"></p>
                
                <?php if (!empty($posts_without_meta)): ?>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th style="width: 30px;"><input type="checkbox" id="rmf-select-all"></th>
                            <th>Título</th>
                            <th style="width: 80px;">Tipo</th>
                            <th style="width: 120px;">Fecha</th>
                            <th style="width: 100px;">Estado</th>
                        </tr>
                    </thead>
                    <tbody id="rmf-items-tbody">
                        <?php foreach ($posts_without_meta as $post): ?>
                        <tr class="rmf-item-row" data-post-type="<?php echo esc_attr($post->post_type); ?>">
                            <td><input type="checkbox" class="rmf-post-checkbox" value="<?php echo esc_attr($post->ID); ?>"></td>
                            <td><?php echo esc_html($post->post_title); ?></td>
                            <td>
                                <?php 
                                $type_labels = [
                                    'post' => '📰 Post',
                                    'page' => '📄 Página',
                                    'product' => '🛒 Producto',
                                ];
                                echo esc_html($type_labels[$post->post_type] ?? ucfirst($post->post_type));
                                ?>
                            </td>
                            <td><?php echo esc_html(get_the_date('d/m/Y', $post)); ?></td>
                            <td class="rmf-status-<?php echo esc_attr($post->ID); ?>">
                                <span class="dashicons dashicons-warning"></span> Pendiente
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <p style="margin-top: 20px;">
                    <button type="button" class="button button-primary button-large" id="rmf-bulk-generate">
                        <span class="dashicons dashicons-lightbulb"></span> Generar Meta Descripciones Seleccionadas
                    </button>
                    &nbsp;
                    <button type="button" class="button" id="rmf-bulk-select-filtered">
                        <span class="dashicons dashicons-yes"></span> Seleccionar mostrados
                    </button>
                </p>
                
                <div id="rmf-bulk-progress" style="display: none; margin-top: 20px; padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1;">
                    <h3 style="margin-top: 0;">Progreso</h3>
                    <progress id="rmf-progress-bar" max="100" value="0" style="width: 100%; height: 30px;"></progress>
                    <p id="rmf-progress-text" style="text-align: center; font-weight: 600; margin-top: 10px;">0 / 0</p>
                </div>
                
                <?php else: ?>
                <p style="color: #2e7d32; background: #e8f5e9; padding: 15px; border-radius: 4px;">
                    <span class="dashicons dashicons-yes-alt" style="color: #2e7d32;"></span> 
                    <strong>¡Perfecto!</strong> Todos los posts tienen meta descripción.
                </p>
                <?php endif; ?>
            </div>
        </div>
        <script>
            jQuery(function($) {
                // Filtrar tabla por scope
                $('input[name="rmf_meta_scope"]').on('change', function() {
                    var scope = $(this).val();
                    var count = parseInt($(this).data('count')) || 0;
                    
                    if (scope === 'all') {
                        $('#rmf-items-tbody tr').show();
                        $('#rmf-filtered-count').text('');
                    } else {
                        $('#rmf-items-tbody tr').hide();
                        $('#rmf-items-tbody tr[data-post-type="' + scope + '"]').show();
                        $('#rmf-filtered-count').text(' (' + count + ')');
                    }
                    
                    // Desmarcar todos
                    $('#rmf-select-all').prop('checked', false);
                    $('.rmf-post-checkbox').prop('checked', false);
                });
                
                // Botón "Seleccionar mostrados"
                $('#rmf-bulk-select-filtered').on('click', function() {
                    $('#rmf-items-tbody tr:visible .rmf-post-checkbox').prop('checked', true);
                });
            });
        </script>
        <?php
    }
    
    // Render functions para cada campo
    
    public function render_openai_section() {
        echo '<p>Configura tu API key de OpenAI para generar meta descripciones automáticamente.</p>';
    }
    
    public function render_api_key_field() {
        $options = get_option('replanta_meta_fill_options', []);
        $api_key = isset($options['openai_api_key']) ? $options['openai_api_key'] : '';
        
        echo '<input type="password" name="replanta_meta_fill_options[openai_api_key]" value="' . esc_attr($api_key) . '" class="regular-text" autocomplete="off" placeholder="sk-...">';
        echo '<button type="button" class="button" id="rmf-validate-api-key">Validar Key</button>';
        echo '<p class="description">Obtén tu API key en <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a></p>';
        echo '<div id="rmf-api-validation-result"></div>';
    }
    
    public function render_model_field() {
        $options = get_option('replanta_meta_fill_options', []);
        $model = isset($options['openai_model']) ? $options['openai_model'] : 'gpt-4o-mini';
        
        echo '<select name="replanta_meta_fill_options[openai_model]">';
        echo '<option value="gpt-4o-mini"' . selected($model, 'gpt-4o-mini', false) . '>GPT-4o Mini (Recomendado)</option>';
        echo '<option value="gpt-4o"' . selected($model, 'gpt-4o', false) . '>GPT-4o</option>';
        echo '<option value="gpt-3.5-turbo"' . selected($model, 'gpt-3.5-turbo', false) . '>GPT-3.5 Turbo</option>';
        echo '</select>';
        echo '<p class="description">GPT-4o Mini ofrece el mejor balance calidad/precio para meta descripciones</p>';
    }
    
    public function render_temperature_field() {
        $options = get_option('replanta_meta_fill_options', []);
        $temperature = isset($options['temperature']) ? (float) $options['temperature'] : 0.7;
        
        echo '<input type="number" name="replanta_meta_fill_options[temperature]" value="' . esc_attr($temperature) . '" min="0" max="1" step="0.1" class="small-text">';
        echo '<p class="description">0 = Más conservador, 1 = Más creativo (recomendado: 0.7)</p>';
    }
    
    public function render_generation_section() {
        echo '<p>Personaliza cómo se generan las meta descripciones.</p>';
    }
    
    public function render_max_length_field() {
        $options = get_option('replanta_meta_fill_options', []);
        $max_length = isset($options['max_length']) ? (int) $options['max_length'] : 155;
        
        echo '<input type="number" name="replanta_meta_fill_options[max_length]" value="' . esc_attr($max_length) . '" min="100" max="320" class="small-text"> caracteres';
        echo '<p class="description">Recomendado: 120-155 caracteres para móvil, hasta 320 para desktop</p>';
    }
    
    public function render_prompt_template_field() {
        $options = get_option('replanta_meta_fill_options', []);
        $default_prompt = 'Genera una meta descripción SEO atractiva y concisa (máximo {max_length} caracteres) para el siguiente contenido. Debe ser persuasiva, incluir palabras clave relevantes y motivar al clic.

Título: {title}
Contenido: {content}

Meta descripción:';
        
        $prompt = isset($options['prompt_template']) ? $options['prompt_template'] : $default_prompt;
        
        echo '<textarea name="replanta_meta_fill_options[prompt_template]" rows="10" class="large-text code">' . esc_textarea($prompt) . '</textarea>';
        echo '<p class="description">Variables disponibles: {title}, {content}, {max_length}</p>';
    }
    
    public function render_auto_generate_field() {
        $options = get_option('replanta_meta_fill_options', []);
        $auto_generate = isset($options['auto_generate']) ? (int) $options['auto_generate'] : 0;
        
        echo '<label>';
        echo '<input type="checkbox" name="replanta_meta_fill_options[auto_generate]" value="1"' . checked($auto_generate, 1, false) . '>';
        echo ' Generar automáticamente al publicar un post sin meta descripción';
        echo '</label>';
        echo '<p class="description">Si está activado, se generará una meta descripción automáticamente cuando publiques un post que no tenga una.</p>';
    }
    
    public function render_use_ai_for_alts_field() {
        $options = get_option('replanta_meta_fill_options', []);
        $use_ai = isset($options['use_ai_for_alts']) ? (int) $options['use_ai_for_alts'] : 0;
        
        echo '<label>';
        echo '<input type="checkbox" name="replanta_meta_fill_options[use_ai_for_alts]" value="1"' . checked($use_ai, 1, false) . '>';
        echo ' Usar OpenAI para generar textos ALT de imágenes';
        echo '</label>';
        echo '<p class="description">Si está desactivado, los ALT se generarán a partir del título de la imagen, nombre del producto o nombre de archivo (sin coste API).</p>';
    }
    
    public function render_seo_section() {
        echo '<p>Configura la compatibilidad con plugins SEO.</p>';
    }
    
    public function render_seo_plugin_field() {
        $options = get_option('replanta_meta_fill_options', []);
        $seo_plugin = isset($options['seo_plugin']) ? $options['seo_plugin'] : 'auto';
        
        echo '<select name="replanta_meta_fill_options[seo_plugin]">';
        echo '<option value="auto"' . selected($seo_plugin, 'auto', false) . '>Auto-detectar</option>';
        echo '<option value="rankmath"' . selected($seo_plugin, 'rankmath', false) . '>Rank Math</option>';
        echo '<option value="yoast"' . selected($seo_plugin, 'yoast', false) . '>Yoast SEO</option>';
        echo '<option value="aioseo"' . selected($seo_plugin, 'aioseo', false) . '>All in One SEO</option>';
        echo '<option value="none"' . selected($seo_plugin, 'none', false) . '>Ninguno (meta fields genéricos)</option>';
        echo '</select>';
        echo '<p class="description">El plugin detectará automáticamente tu plugin SEO, pero puedes forzar uno específico.</p>';
    }
    
    /**
     * Sanitizar opciones
     */
    public function sanitize_options($input) {
        $sanitized = [];
        
        if (isset($input['openai_api_key'])) {
            $sanitized['openai_api_key'] = sanitize_text_field($input['openai_api_key']);
        }
        
        if (isset($input['openai_model'])) {
            $sanitized['openai_model'] = sanitize_text_field($input['openai_model']);
        }
        
        if (isset($input['max_length'])) {
            $sanitized['max_length'] = max(100, min(320, (int) $input['max_length']));
        }
        
        if (isset($input['temperature'])) {
            $sanitized['temperature'] = max(0, min(1, (float) $input['temperature']));
        }
        
        if (isset($input['prompt_template'])) {
            $sanitized['prompt_template'] = wp_kses_post($input['prompt_template']);
        }
        
        if (isset($input['auto_generate'])) {
            $sanitized['auto_generate'] = 1;
        } else {
            $sanitized['auto_generate'] = 0;
        }
        
        if (isset($input['seo_plugin'])) {
            $sanitized['seo_plugin'] = sanitize_text_field($input['seo_plugin']);
        }
        
        if (isset($input['use_ai_for_alts'])) {
            $sanitized['use_ai_for_alts'] = 1;
        } else {
            $sanitized['use_ai_for_alts'] = 0;
        }
        
        return $sanitized;
    }
    
    /**
     * Renderizar página de ALT imágenes
     */
    public function render_alts_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $options = get_option('replanta_meta_fill_options', []);
        $use_ai = isset($options['use_ai_for_alts']) ? (int) $options['use_ai_for_alts'] : 0;
        $has_woo = class_exists('WooCommerce');
        
        ?>
        <div class="wrap">
            <h1>ALT Imágenes — Rellenar textos alternativos vacíos</h1>
            
            <div class="card" style="max-width: 100%;">
                <h2>Filtrar imágenes sin ALT</h2>
                <p>Selecciona qué imágenes quieres analizar:</p>
                
                <p>
                    <label>
                        <input type="radio" name="rmf_alt_scope" value="all" checked> Todas las imágenes
                    </label>
                    <?php if ($has_woo): ?>
                    &nbsp;&nbsp;
                    <label>
                        <input type="radio" name="rmf_alt_scope" value="products"> Solo imágenes de productos
                    </label>
                    <?php endif; ?>
                    &nbsp;&nbsp;
                    <label>
                        <input type="radio" name="rmf_alt_scope" value="posts"> Solo imágenes de posts/páginas
                    </label>
                </p>
                
                <p>
                    <button type="button" class="button button-primary" id="rmf-scan-alts">
                        <span class="dashicons dashicons-search" style="margin-top: 4px;"></span> Escanear imágenes sin ALT
                    </button>
                    &nbsp;
                    <label>
                        <input type="checkbox" id="rmf-alt-use-ai" value="1" <?php checked($use_ai, 1); ?>>
                        Usar IA (OpenAI) para generar ALTs
                    </label>
                </p>
            </div>
            
            <div id="rmf-alts-results" style="display: none;">
                <div class="card" style="max-width: 100%;">
                    <h2>Imágenes sin texto ALT: <span id="rmf-alts-count">0</span></h2>
                    
                    <p>
                        <button type="button" class="button" id="rmf-select-all-alts">
                            <span class="dashicons dashicons-yes" style="margin-top: 4px;"></span> Seleccionar todo
                        </button>
                        &nbsp;
                        <button type="button" class="button button-primary button-large" id="rmf-bulk-generate-alts" disabled>
                            <span class="dashicons dashicons-lightbulb" style="margin-top: 4px;"></span> Generar ALT seleccionados
                        </button>
                    </p>
                    
                    <div id="rmf-alts-progress" style="display: none; margin: 15px 0; padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1;">
                        <progress id="rmf-alts-progress-bar" max="100" value="0" style="width: 100%; height: 30px;"></progress>
                        <p id="rmf-alts-progress-text" style="font-size: 16px; font-weight: 600; text-align: center; margin-top: 10px;">0 / 0</p>
                    </div>
                    
                    <table class="wp-list-table widefat striped" id="rmf-alts-table">
                        <thead>
                            <tr>
                                <th style="width: 30px;"><input type="checkbox" id="rmf-alts-check-all"></th>
                                <th style="width: 60px;">Preview</th>
                                <th>Título / Archivo</th>
                                <th>Post asociado</th>
                                <th style="width: 120px;">Estado</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }
}
