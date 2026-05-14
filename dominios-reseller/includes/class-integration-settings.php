<?php
/**
 * Configuración de Integración Upmind & WHM
 *
 * Página de configuración para integrar con Upmind, WHM y cPanel
 *
 * @package Dominios_Reseller
 * @version 1.0.0
 * @since 1.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dominios_Reseller_Integration_Settings {

    /**
     * Instancia singleton
     */
    private static ?Dominios_Reseller_Integration_Settings $instance = null;

    /**
     * Page slug
     */
    const PAGE_SLUG = 'dr-integration-settings';

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Obtener instancia singleton
     */
    public static function get_instance(): Dominios_Reseller_Integration_Settings {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Inicializar hooks
     */
    private function init_hooks(): void {
        add_action('admin_menu', [$this, 'add_settings_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_dr_test_upmind_connection', [$this, 'ajax_test_upmind_connection']);
        add_action('wp_ajax_dr_test_whm_connection', [$this, 'ajax_test_whm_connection']);
    }

    /**
     * Añadir menú de configuración
     */
    public function add_settings_menu(): void {
        add_submenu_page(
            'dominios-reseller-admin',
            'Integraciones',
            '🔗 Integraciones',
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_settings_page']
        );
    }

    /**
     * Registrar configuraciones
     */
    public function register_settings(): void {
        // Upmind Settings
        register_setting('dr_integration_settings', 'dr_upmind_enabled');
        register_setting('dr_integration_settings', 'dr_upmind_api_url');
        register_setting('dr_integration_settings', 'dr_upmind_api_key');
        register_setting('dr_integration_settings', 'dr_upmind_webhook_secret');
        register_setting('dr_integration_settings', 'dr_upmind_auto_onboarding');

        // WHM/cPanel Settings
        register_setting('dr_integration_settings', 'dr_whm_enabled');
        register_setting('dr_integration_settings', 'dr_whm_hostname');
        register_setting('dr_integration_settings', 'dr_whm_username');
        register_setting('dr_integration_settings', 'dr_whm_api_token');
        register_setting('dr_integration_settings', 'dr_whm_port');

        // Auto-Discovery Settings
        register_setting('dr_integration_settings', 'dr_auto_discovery_enabled');
        register_setting('dr_integration_settings', 'dr_email_scanning_enabled');
        register_setting('dr_integration_settings', 'dr_imap_server');
        register_setting('dr_integration_settings', 'dr_imap_user');
        register_setting('dr_integration_settings', 'dr_imap_pass');
    }

    /**
     * Enqueue scripts
     */
    public function enqueue_scripts($hook): void {
        if ($hook !== 'dominios-reseller_page_' . self::PAGE_SLUG) {
            return;
        }

        wp_enqueue_script('dr-integration-settings', plugin_dir_url(__FILE__) . '../assets/js/integration-settings.js', ['jquery'], '1.0.0', true);
        wp_localize_script('dr-integration-settings', 'drIntegrationAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dr_integration_nonce'),
        ]);
    }

    /**
     * Renderizar página de configuración
     */
    public function render_settings_page(): void {
        // Obtener tab activo
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings';
        ?>
        <div class="wrap">
            <h1>🔗 Configuración de Integraciones</h1>

            <div class="dr-integration-container">
                <!-- Navigation Tabs -->
                <nav class="nav-tab-wrapper">
                    <a href="?page=<?php echo self::PAGE_SLUG; ?>&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                        ⚙️ Configuración
                    </a>
                    <a href="?page=<?php echo self::PAGE_SLUG; ?>&tab=documentation" class="nav-tab <?php echo $active_tab === 'documentation' ? 'nav-tab-active' : ''; ?>">
                        📚 Documentación
                    </a>
                    <a href="?page=<?php echo self::PAGE_SLUG; ?>&tab=operation" class="nav-tab <?php echo $active_tab === 'operation' ? 'nav-tab-active' : ''; ?>">
                        🚀 Operación
                    </a>
                </nav>

                <div class="tab-content">
                    <?php if ($active_tab === 'settings'): ?>
                        <form method="post" action="options.php">
                    <?php settings_fields('dr_integration_settings'); ?>

                    <!-- Upmind Integration -->
                    <div class="dr-settings-section">
                        <h2>🎯 Integración con Upmind</h2>
                        <p class="description">
                            Configura la integración con Upmind para onboarding automático como "optimización de bienvenida".
                        </p>

                        <table class="form-table">
                            <tr>
                                <th scope="row">Habilitar Upmind</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="dr_upmind_enabled" value="1" <?php checked(get_option('dr_upmind_enabled'), 1); ?>>
                                        Activar integración con Upmind
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">URL de API</th>
                                <td>
                                    <input type="url" name="dr_upmind_api_url" value="<?php echo esc_attr(get_option('dr_upmind_api_url')); ?>" class="regular-text" placeholder="https://api.upmind.com/v1">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">API Key</th>
                                <td>
                                    <input type="password" name="dr_upmind_api_key" value="<?php echo esc_attr(get_option('dr_upmind_api_key')); ?>" class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Webhook Secret</th>
                                <td>
                                    <input type="password" name="dr_upmind_webhook_secret" value="<?php echo esc_attr(get_option('dr_upmind_webhook_secret')); ?>" class="regular-text">
                                    <p class="description">Secreto para verificar webhooks de Upmind</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Onboarding Automático</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="dr_upmind_auto_onboarding" value="1" <?php checked(get_option('dr_upmind_auto_onboarding'), 1); ?>>
                                        Activar onboarding automático al recibir órdenes de hosting
                                    </label>
                                </td>
                            </tr>
                        </table>

                        <p>
                            <button type="button" id="test-upmind-connection" class="button button-secondary">
                                🧪 Probar Conexión con Upmind
                            </button>
                        </p>
                    </div>

                    <!-- WHM/cPanel Integration -->
                    <div class="dr-settings-section">
                        <h2>🏠 Integración con WHM/cPanel</h2>
                        <p class="description">
                            Configura el acceso a WHM para verificación de WP readiness y gestión automática.
                        </p>

                        <table class="form-table">
                            <tr>
                                <th scope="row">Habilitar WHM</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="dr_whm_enabled" value="1" <?php checked(get_option('dr_whm_enabled'), 1); ?>>
                                        Activar integración con WHM/cPanel
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">WHM Hostname</th>
                                <td>
                                    <input type="text" name="dr_whm_hostname" value="<?php echo esc_attr(get_option('dr_whm_hostname')); ?>" class="regular-text" placeholder="whm.tudominio.com">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">WHM Username</th>
                                <td>
                                    <input type="text" name="dr_whm_username" value="<?php echo esc_attr(get_option('dr_whm_username')); ?>" class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">API Token</th>
                                <td>
                                    <input type="password" name="dr_whm_api_token" value="<?php echo esc_attr(get_option('dr_whm_api_token')); ?>" class="regular-text">
                                    <p class="description">Token de API de WHM (no la contraseña)</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Puerto</th>
                                <td>
                                    <input type="number" name="dr_whm_port" value="<?php echo esc_attr(get_option('dr_whm_port', '2087')); ?>" class="small-text" placeholder="2087">
                                </td>
                            </tr>
                        </table>

                        <p>
                            <button type="button" id="test-whm-connection" class="button button-secondary">
                                🧪 Probar Conexión con WHM
                            </button>
                        </p>
                    </div>

                    <!-- Auto-Discovery Settings -->
                    <div class="dr-settings-section">
                        <h2>🔍 Auto-Discovery</h2>
                        <p class="description">
                            Configura la detección automática de nuevos dominios.
                        </p>

                        <table class="form-table">
                            <tr>
                                <th scope="row">Habilitar Auto-Discovery</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="dr_auto_discovery_enabled" value="1" <?php checked(get_option('dr_auto_discovery_enabled'), 1); ?>>
                                        Activar detección automática de dominios
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Escaneo de Emails</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="dr_email_scanning_enabled" value="1" <?php checked(get_option('dr_email_scanning_enabled'), 1); ?>>
                                        Escanear emails en busca de dominios
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">IMAP Server</th>
                                <td>
                                    <input type="text" name="dr_imap_server" value="<?php echo esc_attr(get_option('dr_imap_server')); ?>" class="regular-text" placeholder="imap.gmail.com:993/imap/ssl">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">IMAP Username</th>
                                <td>
                                    <input type="text" name="dr_imap_user" value="<?php echo esc_attr(get_option('dr_imap_user')); ?>" class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">IMAP Password</th>
                                <td>
                                    <input type="password" name="dr_imap_pass" value="<?php echo esc_attr(get_option('dr_imap_pass')); ?>" class="regular-text">
                                </td>
                            </tr>
                        </table>
                    </div>

                    <?php submit_button('Guardar Configuración'); ?>
                </form>

                <!-- Webhook URLs -->
                <div class="dr-settings-section">
                    <h2>🔗 URLs de Webhook</h2>
                    <p class="description">
                        Configura estas URLs en tu panel de Upmind para recibir notificaciones automáticas.
                    </p>

                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>Servicio</th>
                                <th>URL del Webhook</th>
                                <th>Eventos</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Upmind</td>
                                <td><code><?php echo esc_url(rest_url('dominios-reseller/v1/webhook/upmind')); ?></code></td>
                                <td>order.completed, service.provisioned, client.created</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- API Status -->
                <div class="dr-settings-section">
                    <h2>📊 Estado de APIs</h2>
                    <div id="api-status-container">
                        <div class="api-status-item">
                            <span class="status-indicator" id="upmind-status">🔄 Verificando...</span>
                            <span class="status-label">Upmind API</span>
                        </div>
                        <div class="api-status-item">
                            <span class="status-indicator" id="whm-status">🔄 Verificando...</span>
                            <span class="status-label">WHM API</span>
                        </div>
                        <div class="api-status-item">
                            <span class="status-indicator" id="cloudflare-status">🔄 Verificando...</span>
                            <span class="status-label">Cloudflare API</span>
                        </div>
                    </div>
                </div>

                        <?php submit_button('Guardar Cambios'); ?>
                    </form>

                    <?php elseif ($active_tab === 'documentation'): ?>
                        <div class="dr-documentation-content">
                            <h2>📚 Documentación de Integración con Upmind</h2>

                            <div class="dr-doc-section">
                                <h3>Conexión con Upmind</h3>
                                <p>Para conectar el plugin con Upmind y habilitar el onboarding automático:</p>

                                <ol>
                                    <li><strong>Accede a tu panel de Upmind</strong> como administrador</li>
                                    <li><strong>Ve a Settings → Webhooks</strong></li>
                                    <li><strong>Crea un nuevo webhook:</strong>
                                        <ul>
                                            <li><strong>Nombre:</strong> Dominios Reseller - Onboarding Automático</li>
                                            <li><strong>URL:</strong> <code><?php echo esc_url(rest_url('dominios-reseller/v1/webhook/upmind')); ?></code></li>
                                            <li><strong>Eventos:</strong> Selecciona "order.completed"</li>
                                            <li><strong>Método:</strong> POST</li>
                                            <li><strong>Formato:</strong> JSON</li>
                                        </ul>
                                    </li>
                                    <li><strong>Guarda el webhook</strong> y copia el secret generado</li>
                                    <li><strong>En esta página (tab Configuración):</strong>
                                        <ul>
                                            <li>Habilita "Integración con Upmind"</li>
                                            <li>Pega la URL de API de Upmind</li>
                                            <li>Pega tu API Key de Upmind</li>
                                            <li>Pega el webhook secret</li>
                                        </ul>
                                    </li>
                                </ol>
                            </div>

                            <div class="dr-doc-section">
                                <h3>Eventos Soportados</h3>
                                <p>El webhook procesa los siguientes eventos de Upmind:</p>
                                <ul>
                                    <li><code>order.completed</code> - Pedido completado con dominio y hosting</li>
                                    <li><code>service.provisioned</code> - Servicio provisionado (opcional)</li>
                                    <li><code>client.created</code> - Nuevo cliente creado (opcional)</li>
                                </ul>
                            </div>

                            <div class="dr-doc-section">
                                <h3>Datos Esperados en el Webhook</h3>
                                <p>El webhook espera recibir datos en el siguiente formato:</p>
                                <pre><code>{
  "event": "order.completed",
  "data": {
    "order_id": "ORD-12345",
    "domain": "cliente.com",
    "hosting_type": "wordpress",
    "customer_email": "cliente@email.com",
    "services": [
      {
        "type": "hosting",
        "plan": "wordpress_pro"
      },
      {
        "type": "domain",
        "name": "cliente.com"
      }
    ]
  }
}</code></pre>
                            </div>

                            <div class="dr-doc-section">
                                <h3>Configuración de WHM/cPanel</h3>
                                <p>Para verificar el readiness de WordPress automáticamente:</p>
                                <ol>
                                    <li><strong>En WHM:</strong> Crea un usuario con permisos de API</li>
                                    <li><strong>Genera un API Token:</strong> Home → Development → Manage API Tokens</li>
                                    <li><strong>En esta página:</strong> Configura hostname, usuario y token</li>
                                    <li><strong>Prueba la conexión</strong> usando el botón "Probar Conexión con WHM"</li>
                                </ol>
                            </div>
                        </div>

                    <?php elseif ($active_tab === 'operation'): ?>
                        <div class="dr-operation-content">
                            <h2>🚀 Guía de Operación</h2>

                            <div class="dr-doc-section">
                                <h3>Flujo de Onboarding Automático</h3>
                                <ol>
                                    <li><strong>Cliente compra hosting + dominio</strong> en Upmind</li>
                                    <li><strong>Upmind envía webhook</strong> "order.completed"</li>
                                    <li><strong>Plugin recibe y valida</strong> el webhook</li>
                                    <li><strong>Verificación WP readiness:</strong> Consulta WHM/cPanel</li>
                                    <li><strong>Creación zona Cloudflare:</strong> API automática</li>
                                    <li><strong>Aplicación de preset:</strong> Configuración según tipo de hosting</li>
                                    <li><strong>Actualización NS:</strong> Si está habilitado</li>
                                    <li><strong>Cliente recibe email</strong> con instrucciones</li>
                                </ol>
                            </div>

                            <div class="dr-doc-section">
                                <h3>Monitoreo y Troubleshooting</h3>
                                <p>Usa el <strong>Debug Hub</strong> para:</p>
                                <ul>
                                    <li><strong>Test de flujo completo:</strong> Simula todo el proceso</li>
                                    <li><strong>Ver estado de dominios:</strong> Revisa progreso individual</li>
                                    <li><strong>Logs en tiempo real:</strong> Diagnóstico de problemas</li>
                                    <li><strong>Reintentos manuales:</strong> Para dominios atascados</li>
                                </ul>
                            </div>

                            <div class="dr-doc-section">
                                <h3>Dashboard de Monitoreo</h3>
                                <p>Accede al dashboard desde el menú principal para ver:</p>
                                <ul>
                                    <li><strong>KPI en tiempo real:</strong> Éxito, fallos, tiempos promedio</li>
                                    <li><strong>Alertas activas:</strong> Problemas que requieren atención</li>
                                    <li><strong>Historial de operaciones:</strong> Últimas 24-48 horas</li>
                                    <li><strong>Estado de servicios:</strong> APIs conectadas</li>
                                </ul>
                            </div>

                            <div class="dr-doc-section">
                                <h3>Configuración de Auto-Discovery</h3>
                                <p>Para detectar dominios automáticamente:</p>
                                <ul>
                                    <li><strong>Email scanning:</strong> Configura IMAP para escanear emails</li>
                                    <li><strong>Upmind polling:</strong> Consulta API periódicamente</li>
                                    <li><strong>Openprovider:</strong> Sincronización con registros</li>
                                    <li><strong>Manual override:</strong> Siempre puedes añadir dominios manualmente</li>
                                </ul>
                            </div>
                        </div>

                    <?php endif; ?>
                </div>
            </div>
        </div>

        <style>
        .dr-integration-container {
            max-width: 1200px;
        }

        .dr-settings-section {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .dr-settings-section h2 {
            margin-top: 0;
            color: #23282d;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        .form-table th {
            width: 200px;
        }

        .api-status-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .status-indicator {
            font-size: 16px;
        }

        .status-label {
            font-weight: 500;
        }

        .widefat {
            margin-top: 10px;
        }

        .widefat code {
            background: #f1f1f1;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }

        /* Tab Styles */
        .nav-tab-wrapper {
            margin-bottom: 20px;
            border-bottom: 1px solid #ccc;
        }

        .nav-tab {
            display: inline-block;
            padding: 8px 16px;
            margin-right: 4px;
            margin-bottom: -1px;
            background: #f1f1f1;
            color: #666;
            text-decoration: none;
            border: 1px solid #ccc;
            border-bottom: none;
            border-radius: 4px 4px 0 0;
            font-size: 14px;
        }

        .nav-tab-active {
            background: white;
            color: #23282d;
            border-bottom: 1px solid white;
        }

        .tab-content {
            background: white;
            border: 1px solid #ddd;
            border-radius: 0 8px 8px 8px;
            padding: 20px;
        }

        .dr-documentation-content h2,
        .dr-operation-content h2 {
            margin-top: 0;
            color: #23282d;
        }

        .dr-doc-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }

        .dr-doc-section:last-child {
            border-bottom: none;
        }

        .dr-doc-section h3 {
            color: #23282d;
            margin-top: 0;
            margin-bottom: 15px;
        }

        .dr-doc-section code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }

        .dr-doc-section pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            border: 1px solid #e9ecef;
        }

        .dr-doc-section ol,
        .dr-doc-section ul {
            margin-left: 20px;
        }

        .dr-doc-section li {
            margin-bottom: 8px;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Auto-refresh API status
            function updateApiStatus() {
                // Implementar verificación de estado de APIs
                console.log('Actualizando estado de APIs...');
            }

            updateApiStatus();
            setInterval(updateApiStatus, 30000); // Cada 30 segundos
        });
        </script>
        <?php
    }

    /**
     * AJAX test Upmind connection
     */
    public function ajax_test_upmind_connection(): void {
        check_ajax_referer('dr_integration_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos');
        }

        try {
            $api_url = get_option('dr_upmind_api_url');
            $api_key = get_option('dr_upmind_api_key');

            if (empty($api_url) || empty($api_key)) {
                wp_send_json_error('Configuración de Upmind incompleta');
                return;
            }

            // Test básico de conectividad
            $response = wp_remote_get($api_url . '/status', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 10,
            ]);

            if (is_wp_error($response)) {
                wp_send_json_error('Error de conexión: ' . $response->get_error_message());
                return;
            }

            $status_code = wp_remote_retrieve_response_code($response);

            if ($status_code === 200) {
                wp_send_json_success('✅ Conexión exitosa con Upmind API');
            } else {
                wp_send_json_error("❌ Error HTTP {$status_code}: " . wp_remote_retrieve_response_message($response));
            }

        } catch (Exception $e) {
            wp_send_json_error('❌ Error: ' . $e->getMessage());
        }
    }

    /**
     * AJAX test WHM connection
     */
    public function ajax_test_whm_connection(): void {
        check_ajax_referer('dr_integration_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos');
        }

        try {
            $hostname = get_option('dr_whm_hostname');
            $username = get_option('dr_whm_username');
            $api_token = get_option('dr_whm_api_token');
            $port = get_option('dr_whm_port', '2087');

            if (empty($hostname) || empty($username) || empty($api_token)) {
                wp_send_json_error('Configuración de WHM incompleta');
                return;
            }

            // Test básico de conectividad WHM
            $url = "https://{$hostname}:{$port}/json-api/version";
            $response = wp_remote_get($url, [
                'headers' => [
                    'Authorization' => 'whm ' . $username . ':' . $api_token,
                ],
                'timeout' => 15,
            ]);

            if (is_wp_error($response)) {
                wp_send_json_error('Error de conexión: ' . $response->get_error_message());
                return;
            }

            $status_code = wp_remote_retrieve_response_code($response);

            if ($status_code === 200) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                if (isset($body['version'])) {
                    wp_send_json_success('✅ Conexión exitosa con WHM API (v' . $body['version'] . ')');
                } else {
                    wp_send_json_success('✅ Conexión exitosa con WHM API');
                }
            } else {
                wp_send_json_error("❌ Error HTTP {$status_code}: " . wp_remote_retrieve_response_message($response));
            }

        } catch (Exception $e) {
            wp_send_json_error('❌ Error: ' . $e->getMessage());
        }
    }
}

// Inicializar configuración
add_action('plugins_loaded', function() {
    Dominios_Reseller_Integration_Settings::get_instance();
});