<?php
/**
 * UI del admin - Listado personalizado
 */

if (!defined('ABSPATH')) exit;

class RCM_Admin_UI {
    
    private static $instance = null;
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_filter('manage_' . RCM_CPT::POST_TYPE . '_posts_columns', [$this, 'custom_columns']);
        add_action('manage_' . RCM_CPT::POST_TYPE . '_posts_custom_column', [$this, 'custom_column_content'], 10, 2);
        add_filter('manage_edit-' . RCM_CPT::POST_TYPE . '_sortable_columns', [$this, 'sortable_columns']);
        add_action('restrict_manage_posts', [$this, 'add_filters']);
        add_filter('parse_query', [$this, 'filter_by_status']);
        add_filter('post_row_actions', [$this, 'row_actions'], 10, 2);
        add_action('admin_init', [$this, 'handle_status_action']);
        add_action('admin_head', [$this, 'admin_styles']);
    }

    public function row_actions($actions, $post) {
        if (!($post instanceof WP_Post) || $post->post_type !== RCM_CPT::POST_TYPE) {
            return $actions;
        }

        $base_url = admin_url('edit.php?post_type=' . RCM_CPT::POST_TYPE);
        $statuses = [
            'rcm_contacted' => __('Marcar contactado', 'replanta-contact-manager'),
            'rcm_processed' => __('Marcar procesado', 'replanta-contact-manager'),
            'rcm_converted' => __('Marcar convertido', 'replanta-contact-manager'),
        ];

        foreach ($statuses as $status => $label) {
            $url = add_query_arg([
                'rcm_action' => 'set_status',
                'post_id' => $post->ID,
                'new_status' => $status,
                '_wpnonce' => wp_create_nonce('rcm_set_status_' . $post->ID),
            ], $base_url);
            $actions['rcm_' . $status] = '<a href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }

        return $actions;
    }

    public function handle_status_action() {
        if (!is_admin() || !current_user_can('edit_posts')) {
            return;
        }

        if (empty($_GET['rcm_action']) || $_GET['rcm_action'] !== 'set_status') {
            return;
        }

        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
        $new_status = isset($_GET['new_status']) ? sanitize_key($_GET['new_status']) : '';
        $allowed = ['rcm_contacted', 'rcm_processed', 'rcm_converted', 'rcm_pending', 'rcm_spam'];

        if (!$post_id || !in_array($new_status, $allowed, true)) {
            return;
        }

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'rcm_set_status_' . $post_id)) {
            return;
        }

        $post = get_post($post_id);
        if (!($post instanceof WP_Post) || $post->post_type !== RCM_CPT::POST_TYPE) {
            return;
        }

        wp_update_post([
            'ID' => $post_id,
            'post_status' => $new_status,
        ]);

        $redirect = admin_url('edit.php?post_type=' . RCM_CPT::POST_TYPE . '&status_updated=1');
        wp_safe_redirect($redirect);
        exit;
    }
    
    public function custom_columns($columns) {
        return [
            'cb'           => $columns['cb'],
            'rcm_type'     => '<span class="dashicons dashicons-category" title="Tipo"></span>',
            'title'        => __('Nombre', 'replanta-contact-manager'),
            'rcm_email'    => __('Email', 'replanta-contact-manager'),
            'rcm_phone'    => __('Teléfono', 'replanta-contact-manager'),
            'rcm_status'   => __('Estado', 'replanta-contact-manager'),
            'rcm_country'  => '<span class="dashicons dashicons-location" title="País"></span>',
            'date'         => __('Fecha', 'replanta-contact-manager'),
        ];
    }
    
    public function custom_column_content($column, $post_id) {
        switch ($column) {
            case 'rcm_type':
                $terms = wp_get_post_terms($post_id, RCM_CPT::TAXONOMY);
                if (!empty($terms)) {
                    $term = $terms[0];
                    $color = get_term_meta($term->term_id, 'color', true) ?: '#999';
                    printf(
                        '<span style="display:inline-block;width:8px;height:8px;border-radius:50%%;background:%s;margin-right:5px;" title="%s"></span><strong>%s</strong>',
                        esc_attr($color),
                        esc_attr($term->name),
                        esc_html($term->name)
                    );
                }
                break;
                
            case 'rcm_email':
                $email = get_post_meta($post_id, '_rcm_email', true);
                if ($email) {
                    echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
                }
                break;
                
            case 'rcm_phone':
                $phone = get_post_meta($post_id, '_rcm_phone', true);
                echo $phone ? esc_html($phone) : '—';
                break;
                
            case 'rcm_status':
                $post = get_post($post_id);
                $statuses = [
                    'rcm_pending'   => ['label' => 'Pendiente', 'color' => '#d63638'],
                    'rcm_contacted' => ['label' => 'Contactado', 'color' => '#dba617'],
                    'rcm_processed' => ['label' => 'Procesado', 'color' => '#2271b1'],
                    'rcm_converted' => ['label' => 'Convertido', 'color' => '#00a32a'],
                    'rcm_spam'      => ['label' => 'Spam', 'color' => '#646970'],
                ];
                
                $status = $statuses[$post->post_status] ?? ['label' => $post->post_status, 'color' => '#999'];
                
                printf(
                    '<span style="display:inline-block;padding:4px 10px;border-radius:12px;background:%s;color:#fff;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;">%s</span>',
                    esc_attr($status['color']),
                    esc_html($status['label'])
                );
                break;
                
            case 'rcm_country':
                $country = get_post_meta($post_id, '_rcm_country', true);
                if ($country && $country !== 'XX') {
                    printf(
                        '<span class="rcm-flag" title="%s">%s</span>',
                        esc_attr($this->get_country_name($country)),
                        esc_html($country)
                    );
                }
                break;
        }
    }
    
    public function sortable_columns($columns) {
        $columns['rcm_email'] = 'rcm_email';
        $columns['rcm_status'] = 'post_status';
        return $columns;
    }
    
    public function add_filters($post_type) {
        if ($post_type !== RCM_CPT::POST_TYPE) {
            return;
        }
        
        // Filtro por tipo
        $terms = get_terms(['taxonomy' => RCM_CPT::TAXONOMY, 'hide_empty' => false]);
        if (!empty($terms)) {
            $current_type = isset($_GET['rcm_type_filter']) ? $_GET['rcm_type_filter'] : '';
            echo '<select name="rcm_type_filter">';
            echo '<option value="">Todos los tipos</option>';
            foreach ($terms as $term) {
                printf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr($term->slug),
                    selected($current_type, $term->slug, false),
                    esc_html($term->name)
                );
            }
            echo '</select>';
        }
        
        // Filtro por estado
        $current_status = isset($_GET['rcm_status_filter']) ? $_GET['rcm_status_filter'] : '';
        $statuses = [
            ''              => 'Todos los estados',
            'rcm_pending'   => 'Pendientes',
            'rcm_contacted' => 'Contactados',
            'rcm_processed' => 'Procesados',
            'rcm_converted' => 'Convertidos',
            'rcm_spam'      => 'Spam',
        ];
        
        echo '<select name="rcm_status_filter">';
        foreach ($statuses as $value => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($value),
                selected($current_status, $value, false),
                esc_html($label)
            );
        }
        echo '</select>';
    }
    
    public function filter_by_status($query) {
        global $pagenow, $typenow;
        
        if ($pagenow !== 'edit.php' || $typenow !== RCM_CPT::POST_TYPE) {
            return;
        }
        
        // Filtro por tipo
        if (!empty($_GET['rcm_type_filter'])) {
            $query->set('tax_query', [
                [
                    'taxonomy' => RCM_CPT::TAXONOMY,
                    'field'    => 'slug',
                    'terms'    => sanitize_key($_GET['rcm_type_filter']),
                ],
            ]);
        }
        
        // Filtro por estado
        if (!empty($_GET['rcm_status_filter'])) {
            $query->set('post_status', sanitize_key($_GET['rcm_status_filter']));
        } else {
            // Mostrar todos los estados menos spam por defecto
            $query->set('post_status', ['rcm_pending', 'rcm_contacted', 'rcm_processed', 'rcm_converted']);
        }
    }
    
    public function admin_styles() {
        global $typenow;
        
        if ($typenow !== RCM_CPT::POST_TYPE) {
            return;
        }
        ?>
        <style>
            /* Estilos para el listado */
            .fixed .column-rcm_type,
            .fixed .column-rcm_country { width: 40px; text-align: center; }
            .fixed .column-rcm_status { width: 120px; }
            .fixed .column-rcm_phone { width: 130px; }
            .fixed .column-rcm_email { width: 200px; }
            
            /* Mejorar legibilidad */
            .wp-list-table tr:hover td { background: #f6f7f7; }
            
            /* Flag emoji style */
            .rcm-flag {
                display: inline-block;
                padding: 2px 6px;
                background: #f0f0f1;
                border-radius: 3px;
                font-size: 11px;
                font-weight: 600;
                color: #646970;
            }
            
            /* Dashboard widget */
            #rcm_dashboard_widget .inside { margin: 0; padding: 0; }
            .rcm-stats { display: flex; flex-wrap: wrap; gap: 15px; padding: 12px; }
            .rcm-stat-box {
                flex: 1;
                min-width: 120px;
                padding: 15px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-radius: 8px;
                color: #fff;
                text-align: center;
            }
            .rcm-stat-box.type-solidario { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
            .rcm-stat-box.type-auditoria { background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); }
            .rcm-stat-box.type-contacto { background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%); }
            .rcm-stat-box.type-elementor { background: linear-gradient(135deg, #ec4899 0%, #be185d 100%); }
            .rcm-stat-box h3 { margin: 0 0 5px; font-size: 32px; font-weight: 700; }
            .rcm-stat-box p { margin: 0; opacity: 0.9; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; }
        </style>
        <?php
    }
    
    private function get_country_name($code) {
        $countries = [
            'ES' => 'España',
            'AD' => 'Andorra',
            'MX' => 'México',
            'AR' => 'Argentina',
            'CO' => 'Colombia',
            'CL' => 'Chile',
            'PE' => 'Perú',
            'EC' => 'Ecuador',
            'VE' => 'Venezuela',
        ];
        
        return $countries[$code] ?? $code;
    }
}
