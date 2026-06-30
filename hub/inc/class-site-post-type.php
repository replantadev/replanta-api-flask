<?php
/**
 * Custom Post Type para sitios individuales
 * Maneja la creación y gestión de páginas individuales de sitios
 */

if (!defined('ABSPATH')) {
    exit;
}

class RP_Hub_Site_Post_Type {
    
    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_taxonomies'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_data'));
        add_filter('the_content', array($this, 'display_site_dashboard'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_dashboard_assets'));
        add_filter('template_include', array($this, 'load_custom_template'));
    }

    /**
     * Registra el Custom Post Type para sitios
     */
    public function register_post_type() {
        $labels = array(
            'name'                  => _x('Sitios', 'Post type general name', 'replanta-hub'),
            'singular_name'         => _x('Sitio', 'Post type singular name', 'replanta-hub'),
            'menu_name'             => _x('Sitios', 'Admin Menu text', 'replanta-hub'),
            'name_admin_bar'        => _x('Sitio', 'Add New on Toolbar', 'replanta-hub'),
            'add_new'               => __('Añadir Nuevo', 'replanta-hub'),
            'add_new_item'          => __('Añadir Nuevo Sitio', 'replanta-hub'),
            'new_item'              => __('Nuevo Sitio', 'replanta-hub'),
            'edit_item'             => __('Editar Sitio', 'replanta-hub'),
            'view_item'             => __('Ver Sitio', 'replanta-hub'),
            'all_items'             => __('Todos los Sitios', 'replanta-hub'),
            'search_items'          => __('Buscar Sitios', 'replanta-hub'),
            'parent_item_colon'     => __('Sitio Padre:', 'replanta-hub'),
            'not_found'             => __('No se encontraron sitios.', 'replanta-hub'),
            'not_found_in_trash'    => __('No se encontraron sitios en papelera.', 'replanta-hub'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => false, // Lo mostraremos en el menú personalizado
            'query_var'          => true,
            'rewrite'            => array('slug' => 'sitio'),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array('title', 'editor', 'thumbnail'),
            'show_in_rest'       => true,
        );

        register_post_type('rphub_site', $args);
    }

    /**
     * Registra taxonomías para sitios
     */
    public function register_taxonomies() {
        // Taxonomía para planes
        $plan_labels = array(
            'name'              => _x('Planes', 'taxonomy general name', 'replanta-hub'),
            'singular_name'     => _x('Plan', 'taxonomy singular name', 'replanta-hub'),
            'search_items'      => __('Buscar Planes', 'replanta-hub'),
            'all_items'         => __('Todos los Planes', 'replanta-hub'),
            'edit_item'         => __('Editar Plan', 'replanta-hub'),
            'update_item'       => __('Actualizar Plan', 'replanta-hub'),
            'add_new_item'      => __('Añadir Nuevo Plan', 'replanta-hub'),
            'new_item_name'     => __('Nuevo Nombre de Plan', 'replanta-hub'),
            'menu_name'         => __('Planes', 'replanta-hub'),
        );

        register_taxonomy('site_plan', array('rphub_site'), array(
            'hierarchical'      => false,
            'labels'            => $plan_labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'plan'),
            'show_in_rest'      => true,
        ));

        // Taxonomía para estado del sitio
        $status_labels = array(
            'name'              => _x('Estados', 'taxonomy general name', 'replanta-hub'),
            'singular_name'     => _x('Estado', 'taxonomy singular name', 'replanta-hub'),
            'search_items'      => __('Buscar Estados', 'replanta-hub'),
            'all_items'         => __('Todos los Estados', 'replanta-hub'),
            'edit_item'         => __('Editar Estado', 'replanta-hub'),
            'update_item'       => __('Actualizar Estado', 'replanta-hub'),
            'add_new_item'      => __('Añadir Nuevo Estado', 'replanta-hub'),
            'new_item_name'     => __('Nuevo Nombre de Estado', 'replanta-hub'),
            'menu_name'         => __('Estados', 'replanta-hub'),
        );

        register_taxonomy('site_status', array('rphub_site'), array(
            'hierarchical'      => false,
            'labels'            => $status_labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'estado'),
            'show_in_rest'      => true,
        ));
    }

    /**
     * Añade meta boxes para datos del sitio
     */
    public function add_meta_boxes() {
        add_meta_box(
            'site_connection',
            __('Conexión del Sitio', 'replanta-hub'),
            array($this, 'site_connection_meta_box'),
            'rphub_site',
            'normal',
            'high'
        );

        add_meta_box(
            'site_technical_data',
            __('Datos Técnicos', 'replanta-hub'),
            array($this, 'site_technical_meta_box'),
            'rphub_site',
            'normal',
            'high'
        );

        add_meta_box(
            'site_performance',
            __('Rendimiento', 'replanta-hub'),
            array($this, 'site_performance_meta_box'),
            'rphub_site',
            'normal',
            'default'
        );

        add_meta_box(
            'site_security',
            __('Seguridad', 'replanta-hub'),
            array($this, 'site_security_meta_box'),
            'rphub_site',
            'normal',
            'default'
        );
    }

    /**
     * Meta box para conexión del sitio
     */
    public function site_connection_meta_box($post) {
        wp_nonce_field('save_site_meta', 'site_meta_nonce');
        
        $site_url = get_post_meta($post->ID, '_site_url', true);
        $site_token = get_post_meta($post->ID, '_site_token', true);
        $last_connection = get_post_meta($post->ID, '_last_connection', true);
        $connection_status = get_post_meta($post->ID, '_connection_status', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="site_url"><?php _e('URL del Sitio', 'replanta-hub'); ?></label>
                </th>
                <td>
                    <input type="url" id="site_url" name="site_url" value="<?php echo esc_attr($site_url); ?>" class="regular-text" required />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="site_token"><?php _e('Token de Acceso', 'replanta-hub'); ?></label>
                </th>
                <td>
                    <input type="text" id="site_token" name="site_token" value="<?php echo esc_attr($site_token); ?>" class="regular-text" />
                    <button type="button" id="generate_token" class="button"><?php _e('Generar Token', 'replanta-hub'); ?></button>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Última Conexión', 'replanta-hub'); ?></th>
                <td>
                    <?php echo $last_connection ? date('d/m/Y H:i:s', strtotime($last_connection)) : __('Nunca', 'replanta-hub'); ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Estado de Conexión', 'replanta-hub'); ?></th>
                <td>
                    <span class="connection-status <?php echo esc_attr($connection_status); ?>">
                        <?php 
                        switch($connection_status) {
                            case 'connected':
                                _e('Conectado', 'replanta-hub');
                                break;
                            case 'error':
                                _e('Error de conexión', 'replanta-hub');
                                break;
                            default:
                                _e('Sin probar', 'replanta-hub');
                        }
                        ?>
                    </span>
                    <button type="button" id="test_connection" class="button"><?php _e('Probar Conexión', 'replanta-hub'); ?></button>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Meta box para datos técnicos
     */
    public function site_technical_meta_box($post) {
        $wp_version = get_post_meta($post->ID, '_wp_version', true);
        $php_version = get_post_meta($post->ID, '_php_version', true);
        $mysql_version = get_post_meta($post->ID, '_mysql_version', true);
        $server_info = get_post_meta($post->ID, '_server_info', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Versión WordPress', 'replanta-hub'); ?></th>
                <td><input type="text" name="wp_version" value="<?php echo esc_attr($wp_version); ?>" class="regular-text" readonly /></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Versión PHP', 'replanta-hub'); ?></th>
                <td><input type="text" name="php_version" value="<?php echo esc_attr($php_version); ?>" class="regular-text" readonly /></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Versión MySQL', 'replanta-hub'); ?></th>
                <td><input type="text" name="mysql_version" value="<?php echo esc_attr($mysql_version); ?>" class="regular-text" readonly /></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Información del Servidor', 'replanta-hub'); ?></th>
                <td><textarea name="server_info" rows="3" class="large-text" readonly><?php echo esc_textarea($server_info); ?></textarea></td>
            </tr>
        </table>
        <?php
    }

    /**
     * Meta box para rendimiento
     */
    public function site_performance_meta_box($post) {
        $pagespeed_mobile = get_post_meta($post->ID, '_pagespeed_mobile', true);
        $pagespeed_desktop = get_post_meta($post->ID, '_pagespeed_desktop', true);
        $core_web_vitals = get_post_meta($post->ID, '_core_web_vitals', true);
        $last_pagespeed_check = get_post_meta($post->ID, '_last_pagespeed_check', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('PageSpeed Móvil', 'replanta-hub'); ?></th>
                <td>
                    <input type="number" name="pagespeed_mobile" value="<?php echo esc_attr($pagespeed_mobile); ?>" min="0" max="100" readonly />
                    <span class="description"><?php _e('Score de 0-100', 'replanta-hub'); ?></span>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('PageSpeed Desktop', 'replanta-hub'); ?></th>
                <td>
                    <input type="number" name="pagespeed_desktop" value="<?php echo esc_attr($pagespeed_desktop); ?>" min="0" max="100" readonly />
                    <span class="description"><?php _e('Score de 0-100', 'replanta-hub'); ?></span>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Core Web Vitals', 'replanta-hub'); ?></th>
                <td>
                    <textarea name="core_web_vitals" rows="3" class="large-text" readonly placeholder="<?php _e('Datos de Core Web Vitals en JSON', 'replanta-hub'); ?>"><?php echo esc_textarea($core_web_vitals); ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Última Verificación', 'replanta-hub'); ?></th>
                <td>
                    <?php echo $last_pagespeed_check ? date('d/m/Y H:i:s', strtotime($last_pagespeed_check)) : __('Nunca', 'replanta-hub'); ?>
                    <button type="button" id="refresh_pagespeed" class="button"><?php _e('Actualizar Ahora', 'replanta-hub'); ?></button>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Meta box para seguridad
     */
    public function site_security_meta_box($post) {
        $security_score = get_post_meta($post->ID, '_security_score', true);
        $vulnerabilities = get_post_meta($post->ID, '_vulnerabilities', true);
        $last_security_scan = get_post_meta($post->ID, '_last_security_scan', true);
        $security_plugins = get_post_meta($post->ID, '_security_plugins', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Puntuación de Seguridad', 'replanta-hub'); ?></th>
                <td>
                    <input type="number" name="security_score" value="<?php echo esc_attr($security_score); ?>" min="0" max="100" readonly />
                    <span class="description"><?php _e('Score de 0-100', 'replanta-hub'); ?></span>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Vulnerabilidades', 'replanta-hub'); ?></th>
                <td>
                    <textarea name="vulnerabilities" rows="3" class="large-text" readonly placeholder="<?php _e('Lista de vulnerabilidades encontradas', 'replanta-hub'); ?>"><?php echo esc_textarea($vulnerabilities); ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Plugins de Seguridad', 'replanta-hub'); ?></th>
                <td>
                    <textarea name="security_plugins" rows="2" class="large-text" readonly placeholder="<?php _e('Plugins de seguridad instalados', 'replanta-hub'); ?>"><?php echo esc_textarea($security_plugins); ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Último Escaneo', 'replanta-hub'); ?></th>
                <td>
                    <?php echo $last_security_scan ? date('d/m/Y H:i:s', strtotime($last_security_scan)) : __('Nunca', 'replanta-hub'); ?>
                    <button type="button" id="run_security_scan" class="button"><?php _e('Escanear Ahora', 'replanta-hub'); ?></button>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Guarda los meta datos del sitio
     */
    public function save_meta_data($post_id) {
        if (!isset($_POST['site_meta_nonce']) || !wp_verify_nonce($_POST['site_meta_nonce'], 'save_site_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Campos editables
        $editable_fields = array(
            'site_url',
            'site_token'
        );

        foreach ($editable_fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
            }
        }

        // Campos de solo lectura (se actualizan via AJAX)
        $readonly_fields = array(
            'wp_version',
            'php_version', 
            'mysql_version',
            'server_info',
            'pagespeed_mobile',
            'pagespeed_desktop',
            'core_web_vitals',
            'security_score',
            'vulnerabilities',
            'security_plugins'
        );

        foreach ($readonly_fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_' . $field, sanitize_textarea_field($_POST[$field]));
            }
        }
    }

    /**
     * Muestra el dashboard del sitio en el frontend
     */
    public function display_site_dashboard($content) {
        if (is_singular('rphub_site') && is_main_query() && !is_admin()) {
            global $post;
            
            // Incluir template del dashboard
            ob_start();
            include plugin_dir_path(__FILE__) . '../templates/single-site-dashboard.php';
            $dashboard_content = ob_get_clean();
            
            return $content . $dashboard_content;
        }
        
        return $content;
    }

    /**
     * Carga assets para el dashboard
     */
    public function enqueue_dashboard_assets() {
        if (is_singular('rphub_site')) {
            wp_enqueue_style('rphub-dashboard', plugin_dir_url(__FILE__) . '../assets/css/dashboard.css', array(), '1.0.0');
            wp_enqueue_script('rphub-dashboard', plugin_dir_url(__FILE__) . '../assets/js/dashboard.js', array('jquery'), '1.0.0', true);
            wp_enqueue_script('rphub-updates', plugin_dir_url(__FILE__) . '../assets/js/updates.js', array('jquery', 'rphub-dashboard'), '1.0.0', true);
            
            wp_localize_script('rphub-dashboard', 'rphub_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('rphub_dashboard_nonce'),
                'site_id' => get_the_ID()
            ));
        }
    }

    /**
     * Carga template personalizado para sitios
     */
    public function load_custom_template($template) {
        if (is_singular('rphub_site')) {
            $custom_template = plugin_dir_path(__FILE__) . '../templates/single-rphub_site.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }
        return $template;
    }
}

// Inicializar la clase
new RP_Hub_Site_Post_Type();
