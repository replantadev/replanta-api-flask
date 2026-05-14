<?php
/**
 * Custom Post Type y taxonomía para solicitudes
 */

if (!defined('ABSPATH')) exit;

class RCM_CPT {
    
    private static $instance = null;
    const POST_TYPE = 'rcm_contact';
    const TAXONOMY = 'rcm_contact_type';
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', [$this, 'register']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_meta_boxes'], 10, 2);
    }
    
    public function register() {
        // Registrar taxonomía de tipos
        register_taxonomy(self::TAXONOMY, self::POST_TYPE, [
            'labels' => [
                'name'          => __('Tipos de solicitud', 'replanta-contact-manager'),
                'singular_name' => __('Tipo', 'replanta-contact-manager'),
                'all_items'     => __('Todos los tipos', 'replanta-contact-manager'),
            ],
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'hierarchical'      => false,
            'show_in_rest'      => true,
            'meta_box_cb'       => false, // Usaremos meta box personalizada
        ]);
        
        // Registrar CPT
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name'          => __('Solicitudes', 'replanta-contact-manager'),
                'singular_name' => __('Solicitud', 'replanta-contact-manager'),
                'add_new'       => __('Nueva solicitud', 'replanta-contact-manager'),
                'add_new_item'  => __('Añadir solicitud', 'replanta-contact-manager'),
                'edit_item'     => __('Editar solicitud', 'replanta-contact-manager'),
                'all_items'     => __('Todas las solicitudes', 'replanta-contact-manager'),
                'view_item'     => __('Ver solicitud', 'replanta-contact-manager'),
                'search_items'  => __('Buscar solicitudes', 'replanta-contact-manager'),
                'not_found'     => __('No se encontraron solicitudes', 'replanta-contact-manager'),
            ],
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'menu_icon'          => 'dashicons-email-alt',
            'menu_position'      => 26,
            'capability_type'    => 'post',
            'hierarchical'       => false,
            'has_archive'        => false,
            'supports'           => ['title'],
            'show_in_rest'       => false,
        ]);
        
        // Crear términos predefinidos
        $this->create_default_terms();
        
        // Registrar estados personalizados
        $this->register_custom_statuses();
    }
    
    private function create_default_terms() {
        $terms = [
            'solidario'   => ['name' => 'Plan Solidario', 'color' => '#10b981'],
            'auditoria'   => ['name' => 'Auditoría WP', 'color' => '#3b82f6'],
            'migrar-ya'   => ['name' => 'Migración Ya', 'color' => '#f59e0b'],
            'contacto'    => ['name' => 'Contacto General', 'color' => '#8b5cf6'],
            'sapwoo'      => ['name' => 'Contacto SAPWoo', 'color' => '#0f766e'],
            'elementor'   => ['name' => 'Elementor Form', 'color' => '#ec4899'],
            'afiliado'    => ['name' => 'Afiliado', 'color' => '#93F1C9'],
        ];
        
        foreach ($terms as $slug => $data) {
            if (!term_exists($slug, self::TAXONOMY)) {
                $term = wp_insert_term($data['name'], self::TAXONOMY, ['slug' => $slug]);
                if (!is_wp_error($term)) {
                    update_term_meta($term['term_id'], 'color', $data['color']);
                }
            }
        }
    }
    
    private function register_custom_statuses() {
        $statuses = [
            'rcm_pending' => [
                'label'                     => _x('Pendiente', 'post status', 'replanta-contact-manager'),
                'public'                    => false,
                'exclude_from_search'       => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop('Pendiente <span class="count">(%s)</span>', 'Pendientes <span class="count">(%s)</span>', 'replanta-contact-manager'),
            ],
            'rcm_contacted' => [
                'label'                     => _x('Contactado', 'post status', 'replanta-contact-manager'),
                'public'                    => false,
                'exclude_from_search'       => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop('Contactado <span class="count">(%s)</span>', 'Contactados <span class="count">(%s)</span>', 'replanta-contact-manager'),
            ],
            'rcm_processed' => [
                'label'                     => _x('Procesado', 'post status', 'replanta-contact-manager'),
                'public'                    => false,
                'exclude_from_search'       => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop('Procesado <span class="count">(%s)</span>', 'Procesados <span class="count">(%s)</span>', 'replanta-contact-manager'),
            ],
            'rcm_converted' => [
                'label'                     => _x('Convertido', 'post status', 'replanta-contact-manager'),
                'public'                    => false,
                'exclude_from_search'       => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop('Convertido <span class="count">(%s)</span>', 'Convertidos <span class="count">(%s)</span>', 'replanta-contact-manager'),
            ],
            'rcm_spam' => [
                'label'                     => _x('Spam', 'post status', 'replanta-contact-manager'),
                'public'                    => false,
                'exclude_from_search'       => true,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop('Spam <span class="count">(%s)</span>', 'Spam <span class="count">(%s)</span>', 'replanta-contact-manager'),
            ],
        ];
        
        foreach ($statuses as $status => $args) {
            register_post_status($status, $args);
        }
    }
    
    public function add_meta_boxes() {
        add_meta_box(
            'rcm_contact_info',
            __('Información de contacto', 'replanta-contact-manager'),
            [$this, 'render_contact_info'],
            self::POST_TYPE,
            'normal',
            'high'
        );
        
        add_meta_box(
            'rcm_contact_data',
            __('Datos específicos', 'replanta-contact-manager'),
            [$this, 'render_contact_data'],
            self::POST_TYPE,
            'normal',
            'high'
        );
        
        add_meta_box(
            'rcm_internal_notes',
            __('Notas internas', 'replanta-contact-manager'),
            [$this, 'render_internal_notes'],
            self::POST_TYPE,
            'side',
            'default'
        );
        
        add_meta_box(
            'rcm_meta_info',
            __('Metadatos', 'replanta-contact-manager'),
            [$this, 'render_meta_info'],
            self::POST_TYPE,
            'side',
            'low'
        );
    }
    
    public function render_contact_info($post) {
        wp_nonce_field('rcm_save_meta', 'rcm_meta_nonce');
        
        $email = get_post_meta($post->ID, '_rcm_email', true);
        $phone = get_post_meta($post->ID, '_rcm_phone', true);
        $name = get_post_meta($post->ID, '_rcm_name', true);
        ?>
        <style>
            .rcm-field { margin-bottom: 15px; }
            .rcm-field label { display: block; font-weight: 600; margin-bottom: 5px; }
            .rcm-field input, .rcm-field textarea { width: 100%; }
        </style>
        <div class="rcm-field">
            <label><?php _e('Nombre completo', 'replanta-contact-manager'); ?></label>
            <input type="text" name="rcm_name" value="<?php echo esc_attr($name); ?>" class="regular-text" />
        </div>
        <div class="rcm-field">
            <label><?php _e('Email', 'replanta-contact-manager'); ?></label>
            <input type="email" name="rcm_email" value="<?php echo esc_attr($email); ?>" class="regular-text" />
        </div>
        <div class="rcm-field">
            <label><?php _e('Teléfono', 'replanta-contact-manager'); ?></label>
            <input type="text" name="rcm_phone" value="<?php echo esc_attr($phone); ?>" class="regular-text" />
        </div>
        <?php
    }
    
    public function render_contact_data($post) {
        $terms = wp_get_post_terms($post->ID, self::TAXONOMY);
        $type = !empty($terms) ? $terms[0]->slug : '';
        
        $data = get_post_meta($post->ID, '_rcm_data', true) ?: [];
        $message = get_post_meta($post->ID, '_rcm_message', true);
        
        ?>
        <div class="rcm-field">
            <label><?php _e('Tipo de solicitud', 'replanta-contact-manager'); ?></label>
            <select name="rcm_type" style="width:100%;max-width:300px;">
                <?php
                $terms_list = get_terms(['taxonomy' => self::TAXONOMY, 'hide_empty' => false]);
                foreach ($terms_list as $term) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($term->slug),
                        selected($type, $term->slug, false),
                        esc_html($term->name)
                    );
                }
                ?>
            </select>
        </div>
        
        <?php if ($type === 'solidario'): 
            $entity_type = $data['entity_type'] ?? '';
            $entity_name = $data['entity_name'] ?? '';
            ?>
            <div class="rcm-field">
                <label><?php _e('Tipo de entidad', 'replanta-contact-manager'); ?></label>
                <input type="text" name="rcm_data[entity_type]" value="<?php echo esc_attr($entity_type); ?>" class="regular-text" />
            </div>
            <div class="rcm-field">
                <label><?php _e('Nombre de la entidad', 'replanta-contact-manager'); ?></label>
                <input type="text" name="rcm_data[entity_name]" value="<?php echo esc_attr($entity_name); ?>" class="regular-text" />
            </div>
        <?php endif; ?>
        
        <?php if ($type === 'auditoria'):
            $url = $data['url'] ?? '';
            ?>
            <div class="rcm-field">
                <label><?php _e('URL del sitio web', 'replanta-contact-manager'); ?></label>
                <input type="url" name="rcm_data[url]" value="<?php echo esc_attr($url); ?>" class="regular-text" />
            </div>
        <?php endif; ?>
        
        <div class="rcm-field">
            <label><?php _e('Mensaje del cliente', 'replanta-contact-manager'); ?></label>
            <textarea name="rcm_message" rows="5" style="width:100%;" readonly><?php echo esc_textarea($message); ?></textarea>
            <p class="description"><?php _e('Mensaje original del formulario (solo lectura)', 'replanta-contact-manager'); ?></p>
        </div>
        
        <?php if ($type === 'elementor'):
            $form_id = $data['form_id'] ?? '';
            $form_name = $data['form_name'] ?? '';
            ?>
            <div class="rcm-field">
                <label><?php _e('Formulario Elementor', 'replanta-contact-manager'); ?></label>
                <input type="text" value="<?php echo esc_attr($form_name . ' (ID: ' . $form_id . ')'); ?>" class="regular-text" readonly />
            </div>
        <?php endif; ?>
        <?php
    }
    
    public function render_internal_notes($post) {
        $notes = get_post_meta($post->ID, '_rcm_notes', true);
        ?>
        <textarea name="rcm_notes" rows="8" style="width:100%;"><?php echo esc_textarea($notes); ?></textarea>
        <p class="description"><?php _e('Notas internas para el equipo (no visibles para el cliente)', 'replanta-contact-manager'); ?></p>
        <?php
    }
    
    public function render_meta_info($post) {
        $ip = get_post_meta($post->ID, '_rcm_ip', true);
        $country = get_post_meta($post->ID, '_rcm_country', true);
        $user_agent = get_post_meta($post->ID, '_rcm_user_agent', true);
        $source = get_post_meta($post->ID, '_rcm_source', true);
        ?>
        <p><strong><?php _e('IP:', 'replanta-contact-manager'); ?></strong> <?php echo esc_html($ip); ?></p>
        <p><strong><?php _e('País:', 'replanta-contact-manager'); ?></strong> <?php echo esc_html($country); ?></p>
        <?php if ($source): ?>
        <p><strong><?php _e('Origen:', 'replanta-contact-manager'); ?></strong> <?php echo esc_html($source); ?></p>
        <?php endif; ?>
        <?php if ($user_agent): ?>
        <p><strong><?php _e('User Agent:', 'replanta-contact-manager'); ?></strong><br><small><?php echo esc_html($user_agent); ?></small></p>
        <?php endif; ?>
        <?php
    }
    
    public function save_meta_boxes($post_id, $post) {
        if (!isset($_POST['rcm_meta_nonce']) || !wp_verify_nonce($_POST['rcm_meta_nonce'], 'rcm_save_meta')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Guardar campos básicos
        if (isset($_POST['rcm_name'])) {
            update_post_meta($post_id, '_rcm_name', sanitize_text_field($_POST['rcm_name']));
        }
        if (isset($_POST['rcm_email'])) {
            update_post_meta($post_id, '_rcm_email', sanitize_email($_POST['rcm_email']));
        }
        if (isset($_POST['rcm_phone'])) {
            update_post_meta($post_id, '_rcm_phone', sanitize_text_field($_POST['rcm_phone']));
        }
        if (isset($_POST['rcm_message'])) {
            update_post_meta($post_id, '_rcm_message', sanitize_textarea_field($_POST['rcm_message']));
        }
        if (isset($_POST['rcm_notes'])) {
            update_post_meta($post_id, '_rcm_notes', sanitize_textarea_field($_POST['rcm_notes']));
        }
        
        // Guardar tipo (taxonomía)
        if (isset($_POST['rcm_type'])) {
            wp_set_object_terms($post_id, sanitize_key($_POST['rcm_type']), self::TAXONOMY);
        }
        
        // Guardar datos específicos
        if (isset($_POST['rcm_data']) && is_array($_POST['rcm_data'])) {
            $data = array_map('sanitize_text_field', $_POST['rcm_data']);
            update_post_meta($post_id, '_rcm_data', $data);
        }
    }
    
    /**
     * Crear nueva solicitud desde REST API
     */
    public static function create_contact($data) {
        $post_data = [
            'post_type'   => self::POST_TYPE,
            'post_title'  => sanitize_text_field($data['name'] ?? 'Sin nombre'),
            'post_status' => 'rcm_pending',
        ];
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            return false;
        }
        
        // Asignar tipo
        if (!empty($data['type'])) {
            wp_set_object_terms($post_id, $data['type'], self::TAXONOMY);
        }
        
        // Guardar metadatos
        $meta_fields = ['name', 'email', 'phone', 'message', 'ip', 'country', 'user_agent', 'source', 'notes'];
        foreach ($meta_fields as $field) {
            if (isset($data[$field])) {
                update_post_meta($post_id, '_rcm_' . $field, $data[$field]);
            }
        }
        
        // Guardar datos específicos
        if (!empty($data['data']) && is_array($data['data'])) {
            update_post_meta($post_id, '_rcm_data', $data['data']);
        }
        
        return $post_id;
    }
}
