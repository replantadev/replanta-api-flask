<?php
/**
 * Clase para la pagina de ajustes del plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Replanta_Auto_Translate_Admin_Settings {
    
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
     * Agregar menu de administracion
     */
    public function add_admin_menu() {
        // Menu principal
        add_menu_page(
            __('Auto Translate', 'replanta-auto-translate'),
            __('Auto Translate', 'replanta-auto-translate'),
            'manage_options',
            'replanta-auto-translate',
            [$this, 'render_settings_page'],
            'dashicons-translation',
            80
        );
        
        // Submenu de ajustes
        add_submenu_page(
            'replanta-auto-translate',
            __('Ajustes', 'replanta-auto-translate'),
            __('Ajustes', 'replanta-auto-translate'),
            'manage_options',
            'replanta-auto-translate',
            [$this, 'render_settings_page']
        );
        
        // Submenu de traduccion masiva
        add_submenu_page(
            'replanta-auto-translate',
            __('Traducir Sitio', 'replanta-auto-translate'),
            __('Traducir Sitio', 'replanta-auto-translate'),
            'manage_options',
            'replanta-auto-translate-bulk',
            [$this, 'render_bulk_page']
        );
        
        // Submenu de logs
        add_submenu_page(
            'replanta-auto-translate',
            __('Historial', 'replanta-auto-translate'),
            __('Historial', 'replanta-auto-translate'),
            'manage_options',
            'replanta-auto-translate-logs',
            [$this, 'render_logs_page']
        );
    }
    
    /**
     * Registrar ajustes
     */
    public function register_settings() {
        register_setting(
            'replanta_auto_translate_settings_group',
            'replanta_auto_translate_settings',
            [$this, 'sanitize_settings']
        );
        
        // Seccion de APIs
        add_settings_section(
            'api_section',
            __('Configuracion de APIs', 'replanta-auto-translate'),
            [$this, 'render_api_section'],
            'replanta-auto-translate'
        );
        
        // Campo OpenAI API Key
        add_settings_field(
            'openai_api_key',
            __('OpenAI API Key', 'replanta-auto-translate'),
            [$this, 'render_text_field'],
            'replanta-auto-translate',
            'api_section',
            [
                'field' => 'openai_api_key',
                'type' => 'password',
                'description' => __('Tu API key de OpenAI para traducciones de alta calidad.', 'replanta-auto-translate')
            ]
        );
        
        // Campo Google API Key
        add_settings_field(
            'google_api_key',
            __('Google Translate API Key', 'replanta-auto-translate'),
            [$this, 'render_text_field'],
            'replanta-auto-translate',
            'api_section',
            [
                'field' => 'google_api_key',
                'type' => 'password',
                'description' => __('Tu API key de Google Cloud Translation.', 'replanta-auto-translate')
            ]
        );
        
        // Motor por defecto
        add_settings_field(
            'default_engine',
            __('Motor de Traduccion', 'replanta-auto-translate'),
            [$this, 'render_select_field'],
            'replanta-auto-translate',
            'api_section',
            [
                'field' => 'default_engine',
                'options' => [
                    'openai' => 'OpenAI (Recomendado)',
                    'google' => 'Google Translate',
                ],
                'description' => __('OpenAI ofrece mejor calidad contextual. Google es mas rapido.', 'replanta-auto-translate')
            ]
        );
        
        // Modelo OpenAI
        add_settings_field(
            'openai_model',
            __('Modelo OpenAI', 'replanta-auto-translate'),
            [$this, 'render_select_field'],
            'replanta-auto-translate',
            'api_section',
            [
                'field' => 'openai_model',
                'options' => [
                    'gpt-4o-mini' => 'GPT-4o Mini (Rapido y economico)',
                    'gpt-4o' => 'GPT-4o (Mayor calidad)',
                    'gpt-4-turbo' => 'GPT-4 Turbo',
                ],
                'description' => __('Modelo a usar para traducciones con OpenAI.', 'replanta-auto-translate')
            ]
        );
        
        // Seccion de idiomas
        add_settings_section(
            'language_section',
            __('Configuracion de Idiomas', 'replanta-auto-translate'),
            [$this, 'render_language_section'],
            'replanta-auto-translate'
        );
        
        // Idioma origen
        add_settings_field(
            'source_language',
            __('Idioma de Origen', 'replanta-auto-translate'),
            [$this, 'render_language_select'],
            'replanta-auto-translate',
            'language_section',
            [
                'field' => 'source_language',
                'description' => __('El idioma principal de tu sitio.', 'replanta-auto-translate')
            ]
        );
        
        // Idioma destino
        add_settings_field(
            'target_language',
            __('Idioma de Destino', 'replanta-auto-translate'),
            [$this, 'render_language_select'],
            'replanta-auto-translate',
            'language_section',
            [
                'field' => 'target_language',
                'description' => __('El idioma al que traducir.', 'replanta-auto-translate')
            ]
        );
        
        // Seccion de opciones
        add_settings_section(
            'options_section',
            __('Opciones de Traduccion', 'replanta-auto-translate'),
            [$this, 'render_options_section'],
            'replanta-auto-translate'
        );
        
        // Traducir slugs
        add_settings_field(
            'translate_slugs',
            __('Traducir URLs (slugs)', 'replanta-auto-translate'),
            [$this, 'render_checkbox_field'],
            'replanta-auto-translate',
            'options_section',
            [
                'field' => 'translate_slugs',
                'description' => __('Traducir los slugs de las paginas (ej: /servicios/ -> /services/).', 'replanta-auto-translate')
            ]
        );
        
        // Traducir SEO
        add_settings_field(
            'translate_seo',
            __('Traducir Meta SEO', 'replanta-auto-translate'),
            [$this, 'render_checkbox_field'],
            'replanta-auto-translate',
            'options_section',
            [
                'field' => 'translate_seo',
                'description' => __('Traducir meta title y description (Yoast/RankMath).', 'replanta-auto-translate')
            ]
        );
        
        // Batch size
        add_settings_field(
            'batch_size',
            __('Paginas por Lote', 'replanta-auto-translate'),
            [$this, 'render_number_field'],
            'replanta-auto-translate',
            'options_section',
            [
                'field' => 'batch_size',
                'min' => 1,
                'max' => 20,
                'description' => __('Numero de paginas a procesar por lote (1-20).', 'replanta-auto-translate')
            ]
        );
        
        // Delay entre requests
        add_settings_field(
            'delay_between_requests',
            __('Delay entre Peticiones (ms)', 'replanta-auto-translate'),
            [$this, 'render_number_field'],
            'replanta-auto-translate',
            'options_section',
            [
                'field' => 'delay_between_requests',
                'min' => 500,
                'max' => 10000,
                'description' => __('Milisegundos de espera entre peticiones API (500-10000).', 'replanta-auto-translate')
            ]
        );
    }
    
    /**
     * Sanitizar ajustes
     */
    public function sanitize_settings($input) {
        $sanitized = [];
        
        $sanitized['openai_api_key'] = sanitize_text_field($input['openai_api_key'] ?? '');
        $sanitized['google_api_key'] = sanitize_text_field($input['google_api_key'] ?? '');
        $sanitized['default_engine'] = in_array($input['default_engine'] ?? '', ['openai', 'google']) 
            ? $input['default_engine'] 
            : 'openai';
        $sanitized['openai_model'] = sanitize_text_field($input['openai_model'] ?? 'gpt-4o-mini');
        $sanitized['source_language'] = sanitize_text_field($input['source_language'] ?? 'es');
        $sanitized['target_language'] = sanitize_text_field($input['target_language'] ?? 'en');
        $sanitized['translate_slugs'] = !empty($input['translate_slugs']);
        $sanitized['translate_seo'] = !empty($input['translate_seo']);
        $sanitized['batch_size'] = min(20, max(1, intval($input['batch_size'] ?? 5)));
        $sanitized['delay_between_requests'] = min(10000, max(500, intval($input['delay_between_requests'] ?? 1000)));
        
        return $sanitized;
    }
    
    /**
     * Renderizar seccion de APIs
     */
    public function render_api_section() {
        echo '<p>' . __('Configura tus API keys para los servicios de traduccion.', 'replanta-auto-translate') . '</p>';
    }
    
    /**
     * Renderizar seccion de idiomas
     */
    public function render_language_section() {
        echo '<p>' . __('Configura los idiomas de origen y destino. Deben estar configurados en Polylang.', 'replanta-auto-translate') . '</p>';
    }
    
    /**
     * Renderizar seccion de opciones
     */
    public function render_options_section() {
        echo '<p>' . __('Opciones adicionales para el proceso de traduccion.', 'replanta-auto-translate') . '</p>';
    }
    
    /**
     * Renderizar campo de texto
     */
    public function render_text_field($args) {
        $settings = Replanta_Auto_Translate::get_settings();
        $value = $settings[$args['field']] ?? '';
        $type = $args['type'] ?? 'text';
        
        echo '<input type="' . esc_attr($type) . '" ';
        echo 'id="' . esc_attr($args['field']) . '" ';
        echo 'name="replanta_auto_translate_settings[' . esc_attr($args['field']) . ']" ';
        echo 'value="' . esc_attr($value) . '" ';
        echo 'class="regular-text" />';
        
        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }
    
    /**
     * Renderizar campo select
     */
    public function render_select_field($args) {
        $settings = Replanta_Auto_Translate::get_settings();
        $value = $settings[$args['field']] ?? '';
        
        echo '<select id="' . esc_attr($args['field']) . '" ';
        echo 'name="replanta_auto_translate_settings[' . esc_attr($args['field']) . ']">';
        
        foreach ($args['options'] as $key => $label) {
            echo '<option value="' . esc_attr($key) . '"' . selected($value, $key, false) . '>';
            echo esc_html($label);
            echo '</option>';
        }
        
        echo '</select>';
        
        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }
    
    /**
     * Renderizar selector de idioma (desde Polylang)
     */
    public function render_language_select($args) {
        $settings = Replanta_Auto_Translate::get_settings();
        $value = $settings[$args['field']] ?? '';
        
        echo '<select id="' . esc_attr($args['field']) . '" ';
        echo 'name="replanta_auto_translate_settings[' . esc_attr($args['field']) . ']">';
        
        // Obtener idiomas de Polylang
        if (function_exists('pll_languages_list')) {
            $languages = pll_languages_list(['fields' => []]);
            foreach ($languages as $lang) {
                echo '<option value="' . esc_attr($lang->slug) . '"' . selected($value, $lang->slug, false) . '>';
                echo esc_html($lang->name);
                echo '</option>';
            }
        } else {
            // Fallback si Polylang no esta activo
            $fallback = ['es' => 'Espanol', 'en' => 'English', 'fr' => 'Francais', 'de' => 'Deutsch'];
            foreach ($fallback as $code => $name) {
                echo '<option value="' . esc_attr($code) . '"' . selected($value, $code, false) . '>';
                echo esc_html($name);
                echo '</option>';
            }
        }
        
        echo '</select>';
        
        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }
    
    /**
     * Renderizar campo checkbox
     */
    public function render_checkbox_field($args) {
        $settings = Replanta_Auto_Translate::get_settings();
        $value = $settings[$args['field']] ?? false;
        
        echo '<input type="checkbox" ';
        echo 'id="' . esc_attr($args['field']) . '" ';
        echo 'name="replanta_auto_translate_settings[' . esc_attr($args['field']) . ']" ';
        echo 'value="1"' . checked($value, true, false) . ' />';
        
        if (!empty($args['description'])) {
            echo '<label for="' . esc_attr($args['field']) . '">' . esc_html($args['description']) . '</label>';
        }
    }
    
    /**
     * Renderizar campo numerico
     */
    public function render_number_field($args) {
        $settings = Replanta_Auto_Translate::get_settings();
        $value = $settings[$args['field']] ?? '';
        
        echo '<input type="number" ';
        echo 'id="' . esc_attr($args['field']) . '" ';
        echo 'name="replanta_auto_translate_settings[' . esc_attr($args['field']) . ']" ';
        echo 'value="' . esc_attr($value) . '" ';
        echo 'min="' . esc_attr($args['min'] ?? 0) . '" ';
        echo 'max="' . esc_attr($args['max'] ?? 100) . '" ';
        echo 'class="small-text" />';
        
        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }
    
    /**
     * Renderizar pagina de ajustes
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        ?>
        <div class="wrap replanta-auto-translate-settings">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('replanta_auto_translate_settings_group');
                do_settings_sections('replanta-auto-translate');
                submit_button(__('Guardar Ajustes', 'replanta-auto-translate'));
                ?>
            </form>
            
            <hr />
            
            <h2><?php _e('Test de Conexion', 'replanta-auto-translate'); ?></h2>
            <p><?php _e('Verifica que tus API keys funcionan correctamente.', 'replanta-auto-translate'); ?></p>
            
            <button type="button" id="test-openai-connection" class="button">
                <?php _e('Probar OpenAI', 'replanta-auto-translate'); ?>
            </button>
            <button type="button" id="test-google-connection" class="button">
                <?php _e('Probar Google Translate', 'replanta-auto-translate'); ?>
            </button>
            
            <div id="connection-test-result" style="margin-top: 15px;"></div>
        </div>
        <?php
    }
    
    /**
     * Renderizar pagina de traduccion masiva
     */
    public function render_bulk_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $settings = Replanta_Auto_Translate::get_settings();
        $source_lang = $settings['source_language'] ?? 'es';
        $target_lang = $settings['target_language'] ?? 'en';
        
        // Obtener paginas sin traduccion
        $untranslated_pages = $this->get_untranslated_pages($source_lang, $target_lang);
        $untranslated_posts = $this->get_untranslated_posts($source_lang, $target_lang);
        $untranslated_templates = $this->get_untranslated_templates($source_lang, $target_lang);
        
        ?>
        <div class="wrap replanta-auto-translate-bulk">
            <h1><?php _e('Traducir Sitio', 'replanta-auto-translate'); ?></h1>
            
            <div class="replanta-bulk-header">
                <p>
                    <?php 
                    printf(
                        __('Traduciendo de <strong>%s</strong> a <strong>%s</strong>', 'replanta-auto-translate'),
                        strtoupper($source_lang),
                        strtoupper($target_lang)
                    ); 
                    ?>
                </p>
                
                <div class="replanta-stats">
                    <div class="stat-box">
                        <span class="stat-number"><?php echo count($untranslated_pages); ?></span>
                        <span class="stat-label"><?php _e('Paginas sin traducir', 'replanta-auto-translate'); ?></span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-number"><?php echo count($untranslated_posts); ?></span>
                        <span class="stat-label"><?php _e('Posts sin traducir', 'replanta-auto-translate'); ?></span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-number"><?php echo count($untranslated_templates); ?></span>
                        <span class="stat-label"><?php _e('Templates Elementor', 'replanta-auto-translate'); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="replanta-bulk-actions">
                <h2><?php _e('Acciones Rapidas', 'replanta-auto-translate'); ?></h2>
                
                <div class="action-buttons">
                    <button type="button" id="translate-all-pages" class="button button-primary button-hero" 
                            data-post-type="page" 
                            data-count="<?php echo count($untranslated_pages); ?>">
                        <?php _e('Traducir Todas las Paginas', 'replanta-auto-translate'); ?>
                    </button>
                    
                    <button type="button" id="translate-all-posts" class="button button-primary button-hero"
                            data-post-type="post"
                            data-count="<?php echo count($untranslated_posts); ?>">
                        <?php _e('Traducir Todos los Posts', 'replanta-auto-translate'); ?>
                    </button>
                    
                    <button type="button" id="translate-menus" class="button button-secondary button-hero">
                        <?php _e('Traducir Menus', 'replanta-auto-translate'); ?>
                    </button>
                    
                    <button type="button" id="translate-all-templates" class="button button-secondary button-hero"
                            data-post-type="elementor_library"
                            data-count="<?php echo count($untranslated_templates); ?>">
                        <?php _e('Traducir Templates Elementor', 'replanta-auto-translate'); ?>
                    </button>
                </div>
                
                <div class="replanta-fix-all-section" style="margin-top: 30px; padding: 20px; background: #f0f7ff; border: 2px solid #0073aa; border-radius: 8px;">
                    <h3 style="margin-top: 0; color: #0073aa;">
                        <span class="dashicons dashicons-admin-tools"></span>
                        <?php _e('Completar Traduccion', 'replanta-auto-translate'); ?>
                    </h3>
                    <p><?php _e('Este boton ejecuta automaticamente:', 'replanta-auto-translate'); ?></p>
                    <ol style="margin-left: 20px;">
                        <li><?php _e('Traduce todas las plantillas Elementor pendientes', 'replanta-auto-translate'); ?></li>
                        <li><?php _e('Actualiza las referencias a plantillas en paginas ya traducidas', 'replanta-auto-translate'); ?></li>
                        <li><?php _e('Configura y puebla los menus en ingles', 'replanta-auto-translate'); ?></li>
                    </ol>
                    <button type="button" id="fix-all-translations" class="button button-primary button-hero" style="font-size: 18px; padding: 15px 30px; height: auto;">
                        <span class="dashicons dashicons-yes-alt" style="font-size: 24px; vertical-align: middle;"></span>
                        <?php _e('Completar Todo', 'replanta-auto-translate'); ?>
                    </button>
                    
                    <div id="fix-all-progress" style="display: none; margin-top: 20px;">
                        <style>
                            #fix-all-progress .log-entry { padding: 5px 0; border-bottom: 1px solid #eee; }
                            #fix-all-progress .log-info { color: #666; }
                            #fix-all-progress .log-success { color: #46b450; }
                            #fix-all-progress .log-warning { color: #ffb900; }
                            #fix-all-progress .log-error { color: #dc3232; }
                        </style>
                        <div class="progress-log" style="background: #fff; border: 1px solid #ddd; padding: 15px; max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 12px;"></div>
                    </div>
                </div>
            </div>
            
            <div id="translation-progress" class="replanta-progress" style="display: none;">
                <h3><?php _e('Progreso de Traduccion', 'replanta-auto-translate'); ?></h3>
                <div class="progress-bar-container">
                    <div class="progress-bar" style="width: 0%;"></div>
                </div>
                <div class="progress-text">
                    <span class="current">0</span> / <span class="total">0</span> 
                    <?php _e('elementos procesados', 'replanta-auto-translate'); ?>
                </div>
                <div class="progress-log"></div>
                <button type="button" id="cancel-translation" class="button button-secondary">
                    <?php _e('Cancelar', 'replanta-auto-translate'); ?>
                </button>
            </div>
            
            <hr />
            
            <h2><?php _e('Seleccion Manual', 'replanta-auto-translate'); ?></h2>
            
            <div class="replanta-manual-selection">
                <h3><?php _e('Paginas', 'replanta-auto-translate'); ?></h3>
                <?php if (!empty($untranslated_pages)) : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <td class="check-column">
                                    <input type="checkbox" id="select-all-pages" />
                                </td>
                                <th><?php _e('Titulo', 'replanta-auto-translate'); ?></th>
                                <th><?php _e('Tipo', 'replanta-auto-translate'); ?></th>
                                <th><?php _e('Fecha', 'replanta-auto-translate'); ?></th>
                                <th><?php _e('Acciones', 'replanta-auto-translate'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($untranslated_pages as $page) : ?>
                                <tr data-post-id="<?php echo $page->ID; ?>">
                                    <td class="check-column">
                                        <input type="checkbox" class="page-checkbox" value="<?php echo $page->ID; ?>" />
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($page->post_title); ?></strong>
                                        <?php if ($this->is_elementor_page($page->ID)) : ?>
                                            <span class="elementor-badge">Elementor</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($page->post_type); ?></td>
                                    <td><?php echo esc_html(get_the_date('Y-m-d', $page)); ?></td>
                                    <td>
                                        <button type="button" class="button translate-single" data-post-id="<?php echo $page->ID; ?>">
                                            <?php _e('Traducir', 'replanta-auto-translate'); ?>
                                        </button>
                                        <span class="translation-status"></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <p>
                        <button type="button" id="translate-selected-pages" class="button button-primary">
                            <?php _e('Traducir Seleccionadas', 'replanta-auto-translate'); ?>
                        </button>
                    </p>
                <?php else : ?>
                    <p class="description"><?php _e('Todas las paginas estan traducidas.', 'replanta-auto-translate'); ?></p>
                <?php endif; ?>
            </div>
            
            <div class="replanta-manual-selection">
                <h3><?php _e('Posts', 'replanta-auto-translate'); ?></h3>
                <?php if (!empty($untranslated_posts)) : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <td class="check-column">
                                    <input type="checkbox" id="select-all-posts" />
                                </td>
                                <th><?php _e('Titulo', 'replanta-auto-translate'); ?></th>
                                <th><?php _e('Categoria', 'replanta-auto-translate'); ?></th>
                                <th><?php _e('Fecha', 'replanta-auto-translate'); ?></th>
                                <th><?php _e('Acciones', 'replanta-auto-translate'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($untranslated_posts, 0, 50) as $post) : ?>
                                <tr data-post-id="<?php echo $post->ID; ?>">
                                    <td class="check-column">
                                        <input type="checkbox" class="post-checkbox" value="<?php echo $post->ID; ?>" />
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($post->post_title); ?></strong>
                                        <?php if ($this->is_elementor_page($post->ID)) : ?>
                                            <span class="elementor-badge">Elementor</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $cats = get_the_category($post->ID);
                                        echo !empty($cats) ? esc_html($cats[0]->name) : '-';
                                        ?>
                                    </td>
                                    <td><?php echo esc_html(get_the_date('Y-m-d', $post)); ?></td>
                                    <td>
                                        <button type="button" class="button translate-single" data-post-id="<?php echo $post->ID; ?>">
                                            <?php _e('Traducir', 'replanta-auto-translate'); ?>
                                        </button>
                                        <span class="translation-status"></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php if (count($untranslated_posts) > 50) : ?>
                        <p class="description">
                            <?php printf(__('Mostrando 50 de %d posts. Usa "Traducir Todos los Posts" para procesar todos.', 'replanta-auto-translate'), count($untranslated_posts)); ?>
                        </p>
                    <?php endif; ?>
                    
                    <p>
                        <button type="button" id="translate-selected-posts" class="button button-primary">
                            <?php _e('Traducir Seleccionados', 'replanta-auto-translate'); ?>
                        </button>
                    </p>
                <?php else : ?>
                    <p class="description"><?php _e('Todos los posts estan traducidos.', 'replanta-auto-translate'); ?></p>
                <?php endif; ?>
            </div>
            
            <div class="replanta-manual-selection">
                <h3><?php _e('Templates de Elementor', 'replanta-auto-translate'); ?></h3>
                <p class="description"><?php _e('Plantillas reutilizables: headers, footers, secciones de precios, testimonios, etc.', 'replanta-auto-translate'); ?></p>
                <?php if (!empty($untranslated_templates)) : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <td class="check-column">
                                    <input type="checkbox" id="select-all-templates" />
                                </td>
                                <th><?php _e('Nombre', 'replanta-auto-translate'); ?></th>
                                <th><?php _e('Tipo de Template', 'replanta-auto-translate'); ?></th>
                                <th><?php _e('Acciones', 'replanta-auto-translate'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($untranslated_templates as $template) : ?>
                                <tr data-post-id="<?php echo $template->ID; ?>">
                                    <td class="check-column">
                                        <input type="checkbox" class="template-checkbox" value="<?php echo $template->ID; ?>" />
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($template->post_title); ?></strong>
                                        <span class="elementor-badge">Elementor</span>
                                    </td>
                                    <td>
                                        <?php 
                                        $template_type = get_post_meta($template->ID, '_elementor_template_type', true);
                                        echo esc_html($template_type ?: 'section');
                                        ?>
                                    </td>
                                    <td>
                                        <button type="button" class="button translate-single" data-post-id="<?php echo $template->ID; ?>">
                                            <?php _e('Traducir', 'replanta-auto-translate'); ?>
                                        </button>
                                        <span class="translation-status"></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <p>
                        <button type="button" id="translate-selected-templates" class="button button-primary">
                            <?php _e('Traducir Seleccionados', 'replanta-auto-translate'); ?>
                        </button>
                    </p>
                <?php else : ?>
                    <p class="description"><?php _e('Todos los templates estan traducidos.', 'replanta-auto-translate'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Renderizar pagina de logs
     */
    public function render_logs_page() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'replanta_translate_log';
        $logs = $wpdb->get_results(
            "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 100"
        );
        
        ?>
        <div class="wrap replanta-auto-translate-logs">
            <h1><?php _e('Historial de Traducciones', 'replanta-auto-translate'); ?></h1>
            
            <?php if (!empty($logs)) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('ID', 'replanta-auto-translate'); ?></th>
                            <th><?php _e('Post Original', 'replanta-auto-translate'); ?></th>
                            <th><?php _e('Post Traducido', 'replanta-auto-translate'); ?></th>
                            <th><?php _e('Idiomas', 'replanta-auto-translate'); ?></th>
                            <th><?php _e('Motor', 'replanta-auto-translate'); ?></th>
                            <th><?php _e('Estado', 'replanta-auto-translate'); ?></th>
                            <th><?php _e('Fecha', 'replanta-auto-translate'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log) : ?>
                            <tr>
                                <td><?php echo $log->id; ?></td>
                                <td>
                                    <?php 
                                    $original = get_post($log->post_id);
                                    if ($original) {
                                        echo '<a href="' . get_edit_post_link($log->post_id) . '">' . esc_html($original->post_title) . '</a>';
                                    } else {
                                        echo '#' . $log->post_id . ' (eliminado)';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($log->translated_post_id) {
                                        $translated = get_post($log->translated_post_id);
                                        if ($translated) {
                                            echo '<a href="' . get_edit_post_link($log->translated_post_id) . '">' . esc_html($translated->post_title) . '</a>';
                                        } else {
                                            echo '#' . $log->translated_post_id . ' (eliminado)';
                                        }
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php echo strtoupper($log->source_lang) . ' -> ' . strtoupper($log->target_lang); ?>
                                </td>
                                <td><?php echo esc_html($log->engine); ?></td>
                                <td>
                                    <span class="status-<?php echo esc_attr($log->status); ?>">
                                        <?php echo esc_html($log->status); ?>
                                    </span>
                                    <?php if ($log->error_message) : ?>
                                        <br /><small class="error-message"><?php echo esc_html($log->error_message); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($log->created_at); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php _e('No hay traducciones registradas aun.', 'replanta-auto-translate'); ?></p>
            <?php endif; ?>
            
            <form method="post" style="margin-top: 20px;">
                <?php wp_nonce_field('clear_translation_logs'); ?>
                <input type="hidden" name="action" value="clear_logs" />
                <button type="submit" class="button" onclick="return confirm('<?php _e('Seguro que quieres limpiar el historial?', 'replanta-auto-translate'); ?>');">
                    <?php _e('Limpiar Historial', 'replanta-auto-translate'); ?>
                </button>
            </form>
        </div>
        <?php
        
        // Procesar limpieza de logs
        if (isset($_POST['action']) && $_POST['action'] === 'clear_logs') {
            if (wp_verify_nonce($_POST['_wpnonce'], 'clear_translation_logs')) {
                $wpdb->query("TRUNCATE TABLE $table_name");
                echo '<script>location.reload();</script>';
            }
        }
    }
    
    /**
     * Obtener paginas sin traduccion
     */
    private function get_untranslated_pages($source_lang, $target_lang) {
        $args = [
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'lang' => $source_lang,
        ];
        
        $pages = get_posts($args);
        $untranslated = [];
        
        foreach ($pages as $page) {
            // Verificar si tiene traduccion en el idioma destino
            if (function_exists('pll_get_post')) {
                $translation_id = pll_get_post($page->ID, $target_lang);
                if (!$translation_id) {
                    $untranslated[] = $page;
                }
            }
        }
        
        return $untranslated;
    }
    
    /**
     * Obtener posts sin traduccion
     */
    private function get_untranslated_posts($source_lang, $target_lang) {
        $args = [
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'lang' => $source_lang,
        ];
        
        $posts = get_posts($args);
        $untranslated = [];
        
        foreach ($posts as $post) {
            if (function_exists('pll_get_post')) {
                $translation_id = pll_get_post($post->ID, $target_lang);
                if (!$translation_id) {
                    $untranslated[] = $post;
                }
            }
        }
        
        return $untranslated;
    }
    
    /**
     * Verificar si una pagina usa Elementor
     */
    private function is_elementor_page($post_id) {
        $elementor_data = get_post_meta($post_id, '_elementor_data', true);
        return !empty($elementor_data);
    }
    
    /**
     * Obtener templates de Elementor sin traduccion
     */
    private function get_untranslated_templates($source_lang, $target_lang) {
        $args = [
            'post_type' => 'elementor_library',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'lang' => $source_lang,
        ];
        
        $templates = get_posts($args);
        $untranslated = [];
        
        foreach ($templates as $template) {
            if (function_exists('pll_get_post')) {
                $translation_id = pll_get_post($template->ID, $target_lang);
                if (!$translation_id) {
                    $untranslated[] = $template;
                }
            }
        }
        
        return $untranslated;
    }
}
