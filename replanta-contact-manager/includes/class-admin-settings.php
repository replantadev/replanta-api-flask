<?php
/**
 * Página de ajustes
 */

if (!defined('ABSPATH')) exit;

class RCM_Admin_Settings {
    
    private static $instance = null;
    const MENU_SLUG = 'rcm-settings';
    const OPTION_GROUP = 'rcm_options';
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'handle_staffkit_save']);  // Procesar guardado manual
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widget']);
    }
    
    public function add_menu() {
        add_submenu_page(
            'edit.php?post_type=' . RCM_CPT::POST_TYPE,
            __('Ajustes', 'replanta-contact-manager'),
            __('Ajustes', 'replanta-contact-manager'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render_page']
        );
    }
    
    public function register_settings() {
        // Settings simples
        $settings = [
            'email_to',
            'turnstile_enabled',
            'geo_enabled',
            'rate_limit_max',
            'rate_limit_window',
            'honeypot_field',
            'elementor_capture',
        ];
        
        foreach ($settings as $setting) {
            register_setting(self::OPTION_GROUP, 'rcm_' . $setting);
        }
        
        // StaffKit settings con sanitización
        register_setting(self::OPTION_GROUP, 'rcm_staffkit_url', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',  // Usar sanitize_text_field en lugar de esc_url_raw
            'default' => ''
        ]);
        
        register_setting(self::OPTION_GROUP, 'rcm_staffkit_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ]);
    }
    
    /**
     * Procesar guardado de StaffKit directamente
     * Para evitar problemas con el settings API de WordPress
     */
    public function handle_staffkit_save() {
        // Solo en la página de ajustes, método POST y nonce válido
        if (!isset($_POST['option_page']) || $_POST['option_page'] !== self::OPTION_GROUP) {
            return;
        }
        
        if (!isset($_POST['action']) || $_POST['action'] !== 'update') {
            return;
        }
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Procesar StaffKit URL
        if (isset($_POST['rcm_staffkit_url'])) {
            $url = sanitize_text_field($_POST['rcm_staffkit_url']);
            update_option('rcm_staffkit_url', $url);
        }
        
        // Procesar StaffKit API Key
        if (isset($_POST['rcm_staffkit_api_key'])) {
            $key = sanitize_text_field($_POST['rcm_staffkit_api_key']);
            update_option('rcm_staffkit_api_key', $key);
        }
    }
    
    public function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (isset($_GET['settings-updated'])) {
            add_settings_error('rcm_messages', 'rcm_message', __('Ajustes guardados.', 'replanta-contact-manager'), 'updated');
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php settings_errors('rcm_messages'); ?>
            
            <form action="options.php" method="post">
                <?php settings_fields(self::OPTION_GROUP); ?>
                
                <table class="form-table">
                    <tr>
                        <th colspan="2"><h2>📧 Notificaciones</h2></th>
                    </tr>
                    <tr>
                        <th><label for="rcm_email_to"><?php _e('Email de notificaciones', 'replanta-contact-manager'); ?></label></th>
                        <td>
                            <input type="email" id="rcm_email_to" name="rcm_email_to" 
                                   value="<?php echo esc_attr(Replanta_Contact_Manager::get_option('email_to', get_option('admin_email'))); ?>" 
                                   class="regular-text" />
                            <p class="description"><?php _e('Email que recibirá las notificaciones de nuevas solicitudes.', 'replanta-contact-manager'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th colspan="2"><h2>🔒 Seguridad</h2></th>
                    </tr>
                    
                    <?php if (defined('CF_TURNSTILE_SITEKEY') && CF_TURNSTILE_SITEKEY): ?>
                    <tr>
                        <th><?php _e('Cloudflare Turnstile', 'replanta-contact-manager'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="rcm_turnstile_enabled" value="1" 
                                       <?php checked(Replanta_Contact_Manager::get_option('turnstile_enabled', 1), 1); ?> />
                                <?php _e('Activar verificación Turnstile', 'replanta-contact-manager'); ?>
                            </label>
                            <p class="description">
                                <strong style="color:#00a32a;">✓ Configurado en wp-config.php</strong><br>
                                Site Key: <code><?php echo esc_html(CF_TURNSTILE_SITEKEY); ?></code>
                            </p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <th><?php _e('Cloudflare Turnstile', 'replanta-contact-manager'); ?></th>
                        <td>
                            <p class="description" style="color:#d63638;">
                                <strong>⚠ No configurado.</strong> Añade en wp-config.php:<br>
                                <code>define('CF_TURNSTILE_SITEKEY', 'tu-site-key');</code><br>
                                <code>define('CF_TURNSTILE_SECRET', 'tu-secret-key');</code>
                            </p>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <tr>
                        <th><?php _e('Geo-bloqueo', 'replanta-contact-manager'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="rcm_geo_enabled" value="1" 
                                       <?php checked(Replanta_Contact_Manager::get_option('geo_enabled', 1), 1); ?> />
                                <?php _e('Activar bloqueo geográfico (solo España, Andorra y LATAM)', 'replanta-contact-manager'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="rcm_rate_limit_max"><?php _e('Rate limiting', 'replanta-contact-manager'); ?></label></th>
                        <td>
                            <input type="number" id="rcm_rate_limit_max" name="rcm_rate_limit_max" 
                                   value="<?php echo esc_attr(Replanta_Contact_Manager::get_option('rate_limit_max', 5)); ?>" 
                                   min="1" max="100" class="small-text" />
                            solicitudes cada
                            <input type="number" id="rcm_rate_limit_window" name="rcm_rate_limit_window" 
                                   value="<?php echo esc_attr(Replanta_Contact_Manager::get_option('rate_limit_window', 15)); ?>" 
                                   min="1" max="1440" class="small-text" />
                            minutos
                            <p class="description"><?php _e('Límite de solicitudes por IP.', 'replanta-contact-manager'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="rcm_honeypot_field"><?php _e('Campo honeypot', 'replanta-contact-manager'); ?></label></th>
                        <td>
                            <input type="text" id="rcm_honeypot_field" name="rcm_honeypot_field" 
                                   value="<?php echo esc_attr(Replanta_Contact_Manager::get_option('honeypot_field', 'fax_number')); ?>" 
                                   class="regular-text" />
                            <p class="description"><?php _e('Nombre del campo oculto anti-bots (ej: fax_number, company_fax).', 'replanta-contact-manager'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th colspan="2"><h2>🌱 Integración con StaffKit</h2></th>
                    </tr>
                    <tr>
                        <th><label for="rcm_staffkit_url"><?php _e('URL de StaffKit', 'replanta-contact-manager'); ?></label></th>
                        <td>
                            <input type="url" id="rcm_staffkit_url" name="rcm_staffkit_url" 
                                   value="<?php echo esc_attr(Replanta_Contact_Manager::get_option('staffkit_url', '')); ?>" 
                                   placeholder="https://staff.replanta.dev"
                                   class="regular-text" />
                            <p class="description"><?php _e('URL base de tu instalación de StaffKit. Deja vacío para no enviar leads a StaffKit.', 'replanta-contact-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="rcm_staffkit_api_key"><?php _e('API Key de StaffKit', 'replanta-contact-manager'); ?></label></th>
                        <td>
                            <input type="text" id="rcm_staffkit_api_key" name="rcm_staffkit_api_key" 
                                   value="<?php echo esc_attr(Replanta_Contact_Manager::get_option('staffkit_api_key', '')); ?>" 
                                   placeholder="sk_live_..."
                                   class="regular-text" />
                            <p class="description"><?php _e('API Key para autenticar con StaffKit (X-API-Key header).', 'replanta-contact-manager'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th colspan="2"><h2>📝 Integración con Elementor</h2></th>
                    </tr>
                    <tr>
                        <th><?php _e('Capturar formularios Elementor', 'replanta-contact-manager'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="rcm_elementor_capture" value="1" 
                                       <?php checked(Replanta_Contact_Manager::get_option('elementor_capture', 1), 1); ?> />
                                <?php _e('Capturar automáticamente todos los envíos de Elementor Forms', 'replanta-contact-manager'); ?>
                            </label>
                            <p class="description"><?php _e('Incluye Contact Form (ff38150) y cualquier otro formulario Elementor.', 'replanta-contact-manager'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Guardar cambios', 'replanta-contact-manager')); ?>
            </form>
            
            <hr>
            
            <h2><?php _e('📊 Estadísticas', 'replanta-contact-manager'); ?></h2>
            <?php $this->render_stats(); ?>
            
            <hr>
            
            <h2><?php _e('🔗 Endpoints REST API', 'replanta-contact-manager'); ?></h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Endpoint</th>
                        <th>Uso</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Plan Solidario</strong></td>
                        <td><code>/wp-json/replanta/v1/contact/solidario</code></td>
                        <td>Formularios de plan solidario</td>
                    </tr>
                    <tr>
                        <td><strong>Auditoría</strong></td>
                        <td><code>/wp-json/replanta/v1/contact/auditoria</code></td>
                        <td>Solicitudes de auditoría WP</td>
                    </tr>
                    <tr>
                        <td><strong>Contacto general</strong></td>
                        <td><code>/wp-json/replanta/v1/contact/general</code></td>
                        <td>Otros formularios de contacto</td>
                    </tr>
                    <tr>
                        <td><strong>Elementor</strong></td>
                        <td><em>(automático)</em></td>
                        <td>Se captura automáticamente vía hook</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    private function render_stats() {
        $types = get_terms(['taxonomy' => RCM_CPT::TAXONOMY, 'hide_empty' => false]);
        
        echo '<div style="display:flex;gap:20px;margin:20px 0;">';
        
        foreach ($types as $term) {
            $count = wp_count_posts(RCM_CPT::POST_TYPE);
            $term_count = 0;
            
            $query = new WP_Query([
                'post_type'      => RCM_CPT::POST_TYPE,
                'posts_per_page' => 1,
                'tax_query'      => [
                    [
                        'taxonomy' => RCM_CPT::TAXONOMY,
                        'field'    => 'slug',
                        'terms'    => $term->slug,
                    ],
                ],
            ]);
            
            $term_count = $query->found_posts;
            $color = get_term_meta($term->term_id, 'color', true) ?: '#999';
            
            printf(
                '<div style="flex:1;padding:20px;background:%s;color:#fff;border-radius:8px;text-align:center;">
                    <h3 style="margin:0 0 10px;font-size:48px;font-weight:700;">%d</h3>
                    <p style="margin:0;opacity:0.9;font-size:14px;">%s</p>
                </div>',
                esc_attr($color),
                $term_count,
                esc_html($term->name)
            );
        }
        
        echo '</div>';
    }
    
    /**
     * Widget del dashboard
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'rcm_dashboard_widget',
            '📬 Solicitudes de contacto',
            [$this, 'render_dashboard_widget']
        );
    }
    
    public function render_dashboard_widget() {
        $statuses = [
            'rcm_pending'   => 'Pendientes',
            'rcm_contacted' => 'Contactados',
            'rcm_converted' => 'Convertidos',
        ];
        
        echo '<div class="rcm-stats">';
        
        foreach ($statuses as $status => $label) {
            $count = wp_count_posts(RCM_CPT::POST_TYPE)->$status ?? 0;
            
            $colors = [
                'rcm_pending'   => '#d63638',
                'rcm_contacted' => '#dba617',
                'rcm_converted' => '#00a32a',
            ];
            
            printf(
                '<div class="rcm-stat-box" style="background:%s;">
                    <h3>%d</h3>
                    <p>%s</p>
                </div>',
                $colors[$status],
                $count,
                $label
            );
        }
        
        echo '</div>';
        echo '<p style="text-align:center;margin:15px 0 0;"><a href="' . admin_url('edit.php?post_type=' . RCM_CPT::POST_TYPE) . '" class="button button-primary">Ver todas las solicitudes</a></p>';
    }
}
