<?php
/**
 * Plugin Name: StaffKit Connector
 * Plugin URI: https://staff.replanta.dev
 * Description: Conecta tu WordPress con StaffKit para capturar leads automáticamente
 * Version: 2.0.0
 * Author: Replanta
 * Author URI: https://replanta.net
 * Text Domain: staffkit-connector
 */

if (!defined('ABSPATH')) {
    exit;
}

define('STAFFKIT_VERSION', '2.0.0');
define('STAFFKIT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('STAFFKIT_PLUGIN_URL', plugin_dir_url(__FILE__));

class StaffKit_Connector {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        
        // Hooks para formularios populares
        add_action('wpcf7_before_send_mail', [$this, 'capture_cf7_lead']);
        add_filter('gform_after_submission', [$this, 'capture_gf_lead'], 10, 2);
        add_filter('wpforms_process_complete', [$this, 'capture_wpforms_lead'], 10, 4);
        
        // Elementor Forms
        add_action('elementor_pro/forms/new_record', [$this, 'capture_elementor_lead'], 10, 2);
        
        // Shortcodes
        add_shortcode('staffkit_sustainability', [$this, 'render_sustainability_widget']);
        
        // Dashboard widget
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widget']);
        
        // Retry queue via WP Cron
        add_action('staffkit_retry_failed_leads', [$this, 'process_retry_queue']);
        if (!wp_next_scheduled('staffkit_retry_failed_leads')) {
            wp_schedule_event(time(), 'five_minutes', 'staffkit_retry_failed_leads');
        }
        
        // Custom cron interval
        add_filter('cron_schedules', [$this, 'add_cron_intervals']);
    }
    
    /**
     * Añadir intervalo de cron personalizado
     */
    public function add_cron_intervals($schedules) {
        $schedules['five_minutes'] = [
            'interval' => 300,
            'display' => __('Cada 5 minutos')
        ];
        return $schedules;
    }
    
    /**
     * Menú de administración
     */
    public function add_admin_menu() {
        add_options_page(
            'StaffKit Connector',
            'StaffKit',
            'manage_options',
            'staffkit-connector',
            [$this, 'render_settings_page']
        );
    }
    
    /**
     * Registrar configuración
     */
    public function register_settings() {
        register_setting('staffkit_settings', 'staffkit_api_url', [
            'sanitize_callback' => 'esc_url_raw'
        ]);
        register_setting('staffkit_settings', 'staffkit_api_key', [
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('staffkit_settings', 'staffkit_webhook_secret', [
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('staffkit_settings', 'staffkit_auto_sync', [
            'sanitize_callback' => 'absint'
        ]);
    }
    
    /**
     * Página de configuración
     */
    public function render_settings_page() {
        $retry_count = count(get_option('staffkit_retry_queue', []));
        ?>
        <div class="wrap">
            <h1>🌱 StaffKit Connector <small style="color:#999;font-size:0.5em;">v<?php echo STAFFKIT_VERSION; ?></small></h1>
            <p>Conecta tu WordPress con StaffKit para gestión automática de leads.</p>
            
            <form method="post" action="options.php">
                <?php settings_fields('staffkit_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">URL de StaffKit</th>
                        <td>
                            <input type="url" 
                                   name="staffkit_api_url" 
                                   value="<?php echo esc_attr(get_option('staffkit_api_url', 'https://staff.replanta.dev')); ?>" 
                                   class="regular-text" 
                                   placeholder="https://staff.replanta.dev">
                            <p class="description">URL base de tu instalación de StaffKit</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">API Key</th>
                        <td>
                            <input type="password" 
                                   name="staffkit_api_key" 
                                   value="<?php echo esc_attr(get_option('staffkit_api_key')); ?>" 
                                   class="regular-text" 
                                   id="staffkit_api_key"
                                   placeholder="sk_...">
                            <button type="button" class="button button-secondary" onclick="toggleApiKeyVisibility()" style="vertical-align:baseline;">👁</button>
                            <p class="description">Obtén tu API Key desde StaffKit → Integraciones</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Webhook Secret</th>
                        <td>
                            <input type="password" 
                                   name="staffkit_webhook_secret" 
                                   value="<?php echo esc_attr(get_option('staffkit_webhook_secret')); ?>" 
                                   class="regular-text"
                                   id="staffkit_webhook_secret">
                            <button type="button" class="button button-secondary" onclick="toggleSecretVisibility()" style="vertical-align:baseline;">👁</button>
                            <p class="description">Secreto HMAC para verificar webhooks entrantes de StaffKit</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Auto-sincronizar</th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="staffkit_auto_sync" 
                                       value="1" 
                                       <?php checked(get_option('staffkit_auto_sync'), '1'); ?>>
                                Enviar leads automáticamente a StaffKit
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <hr>
            
            <h2>Webhooks Entrantes</h2>
            <p>Configura este webhook en StaffKit para recibir notificaciones:</p>
            <code style="background:#f0f0f0;padding:8px 12px;border-radius:4px;font-size:13px;"><?php echo esc_url(rest_url('staffkit/v1/webhook')); ?></code>
            
            <hr>
            
            <h2>Estado del Sistema</h2>
            <table class="widefat" style="max-width:500px;">
                <tr><td>Versión del plugin</td><td><strong><?php echo STAFFKIT_VERSION; ?></strong></td></tr>
                <tr><td>Auto-sync</td><td><?php echo get_option('staffkit_auto_sync') ? '✅ Activo' : '❌ Inactivo'; ?></td></tr>
                <tr><td>Cola de reintentos</td><td><?php echo $retry_count > 0 ? "⚠️ {$retry_count} leads pendientes" : '✅ Vacía'; ?></td></tr>
                <tr><td>Último evento recibido</td><td><?php 
                    $last = get_option('staffkit_last_webhook_event', null);
                    echo $last ? esc_html($last['event'] . ' — ' . $last['time']) : 'Ninguno';
                ?></td></tr>
            </table>
            
            <hr>
            
            <h2>Test de Conexión</h2>
            <button type="button" class="button" id="staffkit-test-btn" onclick="staffkitTestConnection()">Probar Conexión</button>
            <div id="staffkit-test-result" style="margin-top:15px;"></div>
        </div>
        
        <script>
        function staffkitTestConnection() {
            const result = document.getElementById('staffkit-test-result');
            const btn = document.getElementById('staffkit-test-btn');
            btn.disabled = true;
            result.innerHTML = '<p>⏳ Probando conexión...</p>';
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=staffkit_test_connection&_wpnonce=<?php echo wp_create_nonce('staffkit_test_connection'); ?>'
            })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                if (data.success) {
                    result.innerHTML = '<p style="color:green;">✅ ' + data.data.message + '</p>';
                } else {
                    result.innerHTML = '<p style="color:red;">❌ ' + (data.data ? data.data.message : 'Error desconocido') + '</p>';
                }
            })
            .catch(err => {
                btn.disabled = false;
                result.innerHTML = '<p style="color:red;">❌ Error: ' + err.message + '</p>';
            });
        }
        function toggleApiKeyVisibility() {
            const f = document.getElementById('staffkit_api_key');
            f.type = f.type === 'password' ? 'text' : 'password';
        }
        function toggleSecretVisibility() {
            const f = document.getElementById('staffkit_webhook_secret');
            f.type = f.type === 'password' ? 'text' : 'password';
        }
        </script>
        <?php
    }
    
    /**
     * Dashboard widget — últimos leads y estadísticas
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'staffkit_dashboard_widget',
            '🌱 StaffKit — Leads Recientes',
            [$this, 'render_dashboard_widget']
        );
    }
    
    public function render_dashboard_widget() {
        $stats = get_transient('staffkit_lead_stats');
        $recent = get_option('staffkit_recent_leads', []);
        $retry_count = count(get_option('staffkit_retry_queue', []));
        
        echo '<div style="margin-bottom:10px;">';
        echo '<strong>Leads enviados hoy:</strong> ' . intval($stats['today'] ?? 0) . ' | ';
        echo '<strong>Esta semana:</strong> ' . intval($stats['week'] ?? 0) . ' | ';
        echo '<strong>Este mes:</strong> ' . intval($stats['month'] ?? 0);
        if ($retry_count > 0) {
            echo ' | <span style="color:#d63638;">⚠️ ' . $retry_count . ' pendientes de reintento</span>';
        }
        echo '</div>';
        
        if (empty($recent)) {
            echo '<p style="color:#666;">No hay leads recientes. Los leads aparecerán aquí cuando se capturen desde formularios.</p>';
            return;
        }
        
        echo '<table class="widefat striped" style="margin-top:8px;"><thead><tr>';
        echo '<th>Email</th><th>Fuente</th><th>Fecha</th><th>Estado</th>';
        echo '</tr></thead><tbody>';
        
        foreach (array_slice(array_reverse($recent), 0, 10) as $lead) {
            $status = ($lead['success'] ?? false) 
                ? '<span style="color:green;">✅</span>' 
                : '<span style="color:red;">❌</span>';
            echo '<tr>';
            echo '<td>' . esc_html($lead['email'] ?? '—') . '</td>';
            echo '<td>' . esc_html($lead['source'] ?? 'WordPress') . '</td>';
            echo '<td>' . esc_html($lead['time'] ?? '') . '</td>';
            echo '<td>' . $status . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        echo '<p style="text-align:right;margin-top:5px;"><a href="' . admin_url('options-general.php?page=staffkit-connector') . '">Configuración →</a></p>';
    }
    
    // ═══════════════════════════════════════
    // CAPTURA DE LEADS
    // ═══════════════════════════════════════
    
    /**
     * Capturar lead de Contact Form 7
     */
    public function capture_cf7_lead($contact_form) {
        $submission = WPCF7_Submission::get_instance();
        if (!$submission) return;
        
        $data = $submission->get_posted_data();
        
        $lead = [
            'email' => $data['your-email'] ?? '',
            'name' => $data['your-name'] ?? '',
            'company' => $data['company'] ?? '',
            'website' => $data['website'] ?? '',
            'phone' => $data['your-phone'] ?? '',
            'source' => 'Contact Form 7 - ' . get_bloginfo('name'),
            'source_url' => wp_get_referer() ?: get_permalink()
        ];
        
        $this->send_to_staffkit($lead);
    }
    
    /**
     * Capturar lead de Gravity Forms
     */
    public function capture_gf_lead($entry, $form) {
        $lead = [
            'email' => rgar($entry, '2') ?: '',
            'name' => rgar($entry, '1') ?: '',
            'company' => rgar($entry, '3') ?: '',
            'phone' => rgar($entry, '4') ?: '',
            'source' => 'Gravity Forms - ' . ($form['title'] ?? 'Sin título'),
            'source_url' => rgar($entry, 'source_url') ?: get_permalink()
        ];
        
        $this->send_to_staffkit($lead);
    }
    
    /**
     * Capturar lead de WPForms
     */
    public function capture_wpforms_lead($fields, $entry, $form_data, $entry_id) {
        $lead = [
            'email' => $fields[2]['value'] ?? '',
            'name' => $fields[1]['value'] ?? '',
            'company' => $fields[3]['value'] ?? '',
            'source' => 'WPForms - ' . ($form_data['settings']['form_title'] ?? 'Sin título'),
            'source_url' => wp_get_referer() ?: get_permalink()
        ];
        
        $this->send_to_staffkit($lead);
    }
    
    /**
     * Capturar lead de Elementor Forms
     */
    public function capture_elementor_lead($record, $handler) {
        $raw = $record->get('fields');
        $fields = [];
        foreach ($raw as $id => $field) {
            $fields[$field['type']] = $field['value'];
        }
        
        $lead = [
            'email' => $fields['email'] ?? '',
            'name' => $fields['text'] ?? $fields['name'] ?? '',
            'phone' => $fields['tel'] ?? '',
            'source' => 'Elementor Forms - ' . ($record->get('form_settings')['form_name'] ?? get_bloginfo('name')),
            'source_url' => wp_get_referer() ?: get_permalink()
        ];
        
        $this->send_to_staffkit($lead);
    }
    
    // ═══════════════════════════════════════
    // ENVÍO A STAFFKIT + RETRY QUEUE
    // ═══════════════════════════════════════
    
    /**
     * Enviar lead a StaffKit con fallback a cola de reintentos
     */
    private function send_to_staffkit($lead) {
        if (!get_option('staffkit_auto_sync')) {
            return false;
        }
        
        $api_url = rtrim(get_option('staffkit_api_url'), '/');
        $api_key = get_option('staffkit_api_key');
        
        if (empty($api_url) || empty($api_key)) {
            return false;
        }
        
        $payload = $this->build_payload($lead);
        $success = $this->do_send($api_url, $api_key, $payload);
        
        // Log al dashboard
        $this->log_lead_attempt($payload, $success);
        
        // Si falló, encolar para reintento
        if (!$success) {
            $this->enqueue_for_retry($payload);
        }
        
        return $success;
    }
    
    /**
     * Construir payload normalizado
     */
    private function build_payload($lead) {
        return [
            'email' => $lead['email'] ?? null,
            'name' => $lead['name'] ?? null,
            'company' => $lead['company'] ?? null,
            'website' => $lead['website'] ?? null,
            'phone' => $lead['phone'] ?? null,
            'source' => $lead['source'] ?? 'WordPress',
            'source_url' => $lead['source_url'] ?? home_url(),
            'campaign_id' => $lead['campaign_id'] ?? null,
            'eco_score' => $lead['eco_score'] ?? null,
            'co2_visit' => $lead['co2_visit'] ?? null,
            'audit_data' => $lead['audit_data'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'ip_address' => $this->get_client_ip()
        ];
    }
    
    /**
     * Ejecutar envío HTTP real
     */
    private function do_send($api_url, $api_key, $payload) {
        $response = wp_remote_post($api_url . '/api/webhooks/lead-capture.php', [
            'headers' => [
                'X-API-Key' => $api_key,
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($payload),
            'timeout' => 15,
            'sslverify' => true
        ]);
        
        if (is_wp_error($response)) {
            error_log('[StaffKit] API Error: ' . $response->get_error_message());
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            error_log('[StaffKit] API Error: HTTP ' . $code . ' - ' . wp_remote_retrieve_body($response));
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return !empty($body['success']);
    }
    
    /**
     * Registrar intento de envío para el dashboard
     */
    private function log_lead_attempt($payload, $success) {
        $recent = get_option('staffkit_recent_leads', []);
        $recent[] = [
            'email' => $payload['email'] ?? '—',
            'source' => $payload['source'] ?? 'WordPress',
            'time' => current_time('Y-m-d H:i'),
            'success' => $success
        ];
        
        // Mantener solo los últimos 50
        if (count($recent) > 50) {
            $recent = array_slice($recent, -50);
        }
        update_option('staffkit_recent_leads', $recent, false);
        
        // Actualizar estadísticas
        $stats = get_transient('staffkit_lead_stats') ?: ['today' => 0, 'week' => 0, 'month' => 0, 'date' => ''];
        $today = current_time('Y-m-d');
        
        if ($stats['date'] !== $today) {
            $stats['today'] = 0;
            $stats['date'] = $today;
        }
        $stats['today']++;
        $stats['week']++;
        $stats['month']++;
        
        set_transient('staffkit_lead_stats', $stats, DAY_IN_SECONDS);
    }
    
    /**
     * Encolar lead fallido para reintento
     */
    private function enqueue_for_retry($payload) {
        $queue = get_option('staffkit_retry_queue', []);
        $queue[] = [
            'payload' => $payload,
            'attempts' => 0,
            'added_at' => time()
        ];
        
        // Máximo 100 en cola
        if (count($queue) > 100) {
            $queue = array_slice($queue, -100);
        }
        
        update_option('staffkit_retry_queue', $queue, false);
    }
    
    /**
     * Procesar cola de reintentos (wp-cron cada 5 min)
     */
    public function process_retry_queue() {
        $queue = get_option('staffkit_retry_queue', []);
        if (empty($queue)) return;
        
        $api_url = rtrim(get_option('staffkit_api_url'), '/');
        $api_key = get_option('staffkit_api_key');
        
        if (empty($api_url) || empty($api_key)) return;
        
        $remaining = [];
        $processed = 0;
        
        foreach ($queue as $item) {
            // Máximo 3 reintentos
            if ($item['attempts'] >= 3) {
                error_log('[StaffKit] Lead descartado tras 3 reintentos: ' . ($item['payload']['email'] ?? 'sin email'));
                continue;
            }
            
            // Descartar leads de más de 24h
            if ((time() - $item['added_at']) > 86400) {
                continue;
            }
            
            // Solo 5 por ejecución para no sobrecargar
            if ($processed >= 5) {
                $remaining[] = $item;
                continue;
            }
            
            $success = $this->do_send($api_url, $api_key, $item['payload']);
            
            if ($success) {
                $this->log_lead_attempt($item['payload'], true);
                $processed++;
            } else {
                $item['attempts']++;
                $remaining[] = $item;
                $processed++;
            }
        }
        
        update_option('staffkit_retry_queue', $remaining, false);
    }
    
    /**
     * Método público para enviar leads (usable por otros plugins)
     */
    public function send_lead_public($lead) {
        return $this->send_to_staffkit($lead);
    }
    
    /**
     * Obtener IP del cliente
     */
    private function get_client_ip() {
        $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = explode(',', $_SERVER[$key])[0];
                return trim($ip);
            }
        }
        
        return null;
    }
    
    /**
     * Widget de sostenibilidad — shortcode configurable
     * 
     * Uso: [staffkit_sustainability co2="1.5" grade="A" cta_text="Auditar mi web" cta_url="/eco-audit"]
     */
    public function render_sustainability_widget($atts) {
        $atts = shortcode_atts([
            'co2' => '',
            'grade' => '',
            'cta_text' => 'Analizar mi sitio',
            'cta_url' => home_url('/eco-audit'),
            'bg_color' => '#f5f5f5',
            'accent_color' => '#2C5F2D'
        ], $atts);
        
        // Si no se pasan datos, intentar obtener del transient (cacheado)
        $co2 = $atts['co2'];
        $grade = $atts['grade'];
        
        if (empty($co2)) {
            $cached = get_transient('staffkit_eco_data_' . md5(home_url()));
            if ($cached) {
                $co2 = $cached['co2'] ?? '';
                $grade = $cached['grade'] ?? '';
            }
        }
        
        $grade_color = '#2C5F2D';
        if ($grade) {
            $grade_colors = ['A' => '#2C5F2D', 'B' => '#6B8E23', 'C' => '#DAA520', 'D' => '#CD853F', 'E' => '#CD5C5C', 'F' => '#8B0000'];
            $grade_color = $grade_colors[strtoupper(substr($grade, 0, 1))] ?? '#2C5F2D';
        }
        
        ob_start();
        ?>
        <div class="staffkit-sustainability-widget" style="background:<?php echo esc_attr($atts['bg_color']); ?>;padding:30px;border-radius:12px;text-align:center;max-width:400px;margin:20px auto;">
            <h3 style="margin-top:0;">🌱 Sostenibilidad Web</h3>
            <p style="color:#555;">Descubre el impacto ambiental de tu sitio web</p>
            <?php if ($co2) : ?>
            <div style="display:flex;justify-content:center;gap:30px;margin:20px 0;">
                <div>
                    <span style="font-size:2rem;font-weight:700;color:<?php echo esc_attr($atts['accent_color']); ?>;"><?php echo esc_html($co2); ?>g</span>
                    <span style="display:block;color:#666;font-size:0.85rem;margin-top:4px;">CO₂ por visita</span>
                </div>
                <?php if ($grade) : ?>
                <div>
                    <span style="font-size:2rem;font-weight:700;color:<?php echo esc_attr($grade_color); ?>;"><?php echo esc_html(strtoupper($grade)); ?></span>
                    <span style="display:block;color:#666;font-size:0.85rem;margin-top:4px;">Eco Score</span>
                </div>
                <?php endif; ?>
            </div>
            <?php else : ?>
            <div style="margin:20px 0;">
                <span style="font-size:1.1rem;color:#666;">¿Tu web es sostenible?</span>
            </div>
            <?php endif; ?>
            <a href="<?php echo esc_url($atts['cta_url']); ?>" 
               style="display:inline-block;background:<?php echo esc_attr($atts['accent_color']); ?>;color:white;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;transition:opacity 0.2s;"
               onmouseover="this.style.opacity='0.85'" onmouseout="this.style.opacity='1'">
                <?php echo esc_html($atts['cta_text']); ?>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Inicializar plugin
StaffKit_Connector::get_instance();

// Cleanup al desactivar
register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('staffkit_retry_failed_leads');
});

/**
 * Función helper para enviar leads desde otros plugins
 * 
 * Ejemplo de uso:
 * staffkit_send_lead([
 *     'email' => 'lead@example.com',
 *     'name' => 'John Doe',
 *     'website' => 'example.com',
 *     'source' => 'Eco Audit',
 *     'eco_score' => 'A',
 *     'co2_visit' => 1.5
 * ]);
 * 
 * @param array $lead Datos del lead
 * @return bool True si se envió correctamente
 */
function staffkit_send_lead($lead) {
    $connector = StaffKit_Connector::get_instance();
    return $connector->send_lead_public($lead);
}

// ═══════════════════════════════════════════════
// REST API — Webhooks entrantes con HMAC
// ═══════════════════════════════════════════════

add_action('rest_api_init', function() {
    register_rest_route('staffkit/v1', '/webhook', [
        'methods' => 'POST',
        'callback' => 'staffkit_handle_webhook',
        'permission_callback' => '__return_true'
    ]);
});

function staffkit_handle_webhook(WP_REST_Request $request) {
    $raw_body = $request->get_body();
    $payload = json_decode($raw_body, true);
    
    if (empty($payload)) {
        return new WP_REST_Response(['error' => 'Invalid JSON'], 400);
    }
    
    // Verificar firma HMAC-SHA256
    $secret = get_option('staffkit_webhook_secret');
    if ($secret) {
        $signature = $request->get_header('X-StaffKit-Signature');
        $timestamp = $request->get_header('X-StaffKit-Timestamp');
        
        if (empty($signature) || empty($timestamp)) {
            // Fallback: verificar secreto plano (compatibilidad legacy)
            $plain_secret = $request->get_header('X-Webhook-Secret');
            if ($plain_secret !== $secret) {
                return new WP_REST_Response(['error' => 'Missing signature headers'], 401);
            }
        } else {
            // Rechazar webhooks con timestamp > 5 minutos (previene replay)
            if (abs(time() - intval($timestamp)) > 300) {
                return new WP_REST_Response(['error' => 'Webhook timestamp too old'], 401);
            }
            
            $expected = hash_hmac('sha256', $timestamp . '.' . $raw_body, $secret);
            if (!hash_equals($expected, $signature)) {
                return new WP_REST_Response(['error' => 'Invalid HMAC signature'], 401);
            }
        }
    }
    
    // Procesar evento
    $event = $payload['event'] ?? '';
    $data = $payload['data'] ?? [];
    
    // Log último evento recibido
    update_option('staffkit_last_webhook_event', [
        'event' => $event,
        'time' => current_time('Y-m-d H:i:s')
    ], false);
    
    // Disparar hooks para que otros plugins puedan reaccionar
    do_action('staffkit_webhook_' . str_replace('.', '_', $event), $data, $payload);
    do_action('staffkit_webhook', $event, $data, $payload);
    
    return new WP_REST_Response([
        'success' => true,
        'event' => $event,
        'message' => 'Webhook procesado'
    ], 200);
}

// ═══════════════════════════════════════════════
// AJAX — Test de conexión (con nonce)
// ═══════════════════════════════════════════════

add_action('wp_ajax_staffkit_test_connection', function() {
    check_ajax_referer('staffkit_test_connection', '_wpnonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permisos insuficientes']);
    }
    
    $api_url = rtrim(get_option('staffkit_api_url'), '/');
    $api_key = get_option('staffkit_api_key');
    
    if (empty($api_url) || empty($api_key)) {
        wp_send_json_error(['message' => 'Configura la URL y API Key primero']);
    }
    
    $response = wp_remote_get($api_url . '/api/v2/ping', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'X-API-Key' => $api_key
        ],
        'timeout' => 10,
        'sslverify' => true
    ]);
    
    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Error de conexión: ' . $response->get_error_message()]);
    }
    
    $code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    if ($code === 200 && !empty($body['success'])) {
        $prospects = isset($body['prospects']) ? ' | ' . $body['prospects'] . ' prospectos en BD' : '';
        wp_send_json_success(['message' => 'Conexión exitosa con StaffKit (v' . STAFFKIT_VERSION . ')' . $prospects]);
    } elseif ($code === 401) {
        wp_send_json_error(['message' => 'API Key inválida (HTTP 401)']);
    } else {
        $detail = !empty($body['error']) ? ': ' . $body['error'] : '';
        wp_send_json_error(['message' => 'Error HTTP ' . $code . $detail]);
    }
});
