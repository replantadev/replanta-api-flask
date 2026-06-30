<?php
/**
 * Analytics Settings Page for Replanta Hub Professional
 * 
 * Handles configuration of Google Analytics 4, Search Console, and other analytics integrations
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ReplantaHub_Analytics_Settings {
    
    public function __construct() {
        add_action('admin_init', array($this, 'register_analytics_settings'));
        add_action('wp_ajax_rphub_oauth_callback', array($this, 'handle_oauth_callback'));
        add_action('wp_ajax_rphub_revoke_analytics_access', array($this, 'revoke_analytics_access'));
    }

    // Menu removed — credentials now live in Ajustes > API > Google APIs.
    
    public function register_analytics_settings() {
        // Analytics general settings
        register_setting('rphub_analytics_settings', 'replanta_hub_analytics_settings', array(
            'sanitize_callback' => array($this, 'sanitize_analytics_settings')
        ));
        
        // Google API credentials
        register_setting('rphub_analytics_settings', 'replanta_hub_google_client_id');
        register_setting('rphub_analytics_settings', 'replanta_hub_google_client_secret');
        register_setting('rphub_analytics_settings', 'replanta_hub_google_api_key');
        
        // RUM settings
        register_setting('rphub_analytics_settings', 'rphub_rum_enabled');
        register_setting('rphub_analytics_settings', 'rphub_rum_sample_rate');
        register_setting('rphub_analytics_settings', 'rphub_rum_batch_size');
    }
    
    public function sanitize_analytics_settings($input) {
        $sanitized = array();
        
        if (isset($input['ga4_property_id'])) {
            $sanitized['ga4_property_id'] = sanitize_text_field($input['ga4_property_id']);
        }
        
        if (isset($input['search_console_domain'])) {
            $sanitized['search_console_domain'] = esc_url_raw($input['search_console_domain']);
        }
        
        if (isset($input['access_token'])) {
            $sanitized['access_token'] = sanitize_text_field($input['access_token']);
        }
        
        if (isset($input['refresh_token'])) {
            $sanitized['refresh_token'] = sanitize_text_field($input['refresh_token']);
        }
        
        if (isset($input['sync_frequency'])) {
            $sanitized['sync_frequency'] = sanitize_text_field($input['sync_frequency']);
        }
        
        return $sanitized;
    }
    
    public function render_analytics_settings_page() {
        $current_settings = get_option('replanta_hub_analytics_settings', array());
        $google_client_id = get_option('replanta_hub_google_client_id', '');
        $google_client_secret = get_option('replanta_hub_google_client_secret', '');
        $google_api_key = get_option('replanta_hub_google_api_key', '');
        $rum_enabled = get_option('rphub_rum_enabled', true);
        $rum_sample_rate = get_option('rphub_rum_sample_rate', 1.0);
        $rum_batch_size = get_option('rphub_rum_batch_size', 10);
        
        ?>
        <div class="wrap">
            <h1>Configuración de Analytics</h1>
            
            <div class="rphub-analytics-settings">
                <div class="rphub-settings-grid">
                    <!-- Google Analytics & Search Console Configuration -->
                    <div class="rphub-settings-card">
                        <h2>Google Analytics 4 y Search Console</h2>
                        
                        <form method="post" action="options.php">
                            <?php settings_fields('rphub_analytics_settings'); ?>
                            
                            <div class="rphub-form-group">
                                <label for="google_client_id">Google Client ID</label>
                                <input type="text" 
                                       id="google_client_id" 
                                       name="replanta_hub_google_client_id" 
                                       value="<?php echo esc_attr($google_client_id); ?>"
                                       class="regular-text" />
                                <p class="description">
                                    Obtén tus credenciales OAuth desde la 
                                    <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a>
                                </p>
                            </div>
                            
                            <div class="rphub-form-group">
                                <label for="google_client_secret">Google Client Secret</label>
                                <input type="password" 
                                       id="google_client_secret" 
                                       name="replanta_hub_google_client_secret" 
                                       value="<?php echo esc_attr($google_client_secret); ?>"
                                       class="regular-text" />
                            </div>
                            
                            <div class="rphub-form-group">
                                <label for="google_api_key">Google API Key</label>
                                <input type="text" 
                                       id="google_api_key" 
                                       name="replanta_hub_google_api_key" 
                                       value="<?php echo esc_attr($google_api_key); ?>"
                                       class="regular-text" />
                                <p class="description">
                                    Necesario para la API de Chrome UX Report (Web Vitals)
                                </p>
                            </div>
                            
                            <div class="rphub-form-group">
                                <label for="ga4_property_id">Google Analytics 4 Property ID</label>
                                <input type="text" 
                                       id="ga4_property_id" 
                                       name="replanta_hub_analytics_settings[ga4_property_id]" 
                                       value="<?php echo esc_attr($current_settings['ga4_property_id'] ?? ''); ?>"
                                       placeholder="123456789"
                                       class="regular-text" />
                            </div>
                            
                            <div class="rphub-form-group">
                                <label for="search_console_domain">Dominio de Search Console</label>
                                <input type="url" 
                                       id="search_console_domain" 
                                       name="replanta_hub_analytics_settings[search_console_domain]" 
                                       value="<?php echo esc_attr($current_settings['search_console_domain'] ?? ''); ?>"
                                       placeholder="https://example.com"
                                       class="regular-text" />
                            </div>
                            
                            <div class="rphub-form-group">
                                <label for="sync_frequency">Frecuencia de Sincronización</label>
                                <select id="sync_frequency" 
                                        name="replanta_hub_analytics_settings[sync_frequency]">
                                    <option value="hourly" <?php selected($current_settings['sync_frequency'] ?? 'hourly', 'hourly'); ?>>
                                        Cada hora
                                    </option>
                                    <option value="twicedaily" <?php selected($current_settings['sync_frequency'] ?? 'hourly', 'twicedaily'); ?>>
                                        Dos veces al día
                                    </option>
                                    <option value="daily" <?php selected($current_settings['sync_frequency'] ?? 'hourly', 'daily'); ?>>
                                        Diariamente
                                    </option>
                                </select>
                            </div>
                            
                            <?php submit_button('Guardar Configuración'); ?>
                        </form>
                        
                        <?php if (!empty($google_client_id) && !empty($google_client_secret)): ?>
                        <div class="rphub-oauth-section">
                            <h3>Autorización Google</h3>
                            
                            <?php if (empty($current_settings['access_token'])): ?>
                                <p>Necesitas autorizar el acceso a Google Analytics y Search Console:</p>
                                <button type="button" 
                                        class="button button-primary" 
                                        id="rphub-authorize-google">
                                    Autorizar Acceso a Google
                                </button>
                            <?php else: ?>
                                <p class="rphub-success"> Autorización completada</p>
                                <button type="button" 
                                        class="button button-secondary" 
                                        id="rphub-test-connection">
                                    Probar Conexión
                                </button>
                                <button type="button" 
                                        class="button button-secondary" 
                                        id="rphub-revoke-access">
                                    Revocar Acceso
                                </button>
                            <?php endif; ?>
                            
                            <div id="rphub-connection-results" style="margin-top: 15px;"></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Real User Monitoring Configuration -->
                    <div class="rphub-settings-card">
                        <h2>Real User Monitoring (RUM)</h2>
                        
                        <form method="post" action="options.php">
                            <?php settings_fields('rphub_analytics_settings'); ?>
                            
                            <div class="rphub-form-group">
                                <label>
                                    <input type="checkbox" 
                                           name="rphub_rum_enabled" 
                                           value="1" 
                                           <?php checked($rum_enabled); ?> />
                                    Habilitar Real User Monitoring
                                </label>
                                <p class="description">
                                    Recopila métricas de rendimiento real de los usuarios
                                </p>
                            </div>
                            
                            <div class="rphub-form-group">
                                <label for="rum_sample_rate">Tasa de Muestreo (%)</label>
                                <input type="number" 
                                       id="rum_sample_rate" 
                                       name="rphub_rum_sample_rate" 
                                       value="<?php echo esc_attr($rum_sample_rate * 100); ?>"
                                       min="1" 
                                       max="100" 
                                       step="1"
                                       class="small-text" />
                                <p class="description">
                                    Porcentaje de sesiones que se monitorizarán (100% = todas)
                                </p>
                            </div>
                            
                            <div class="rphub-form-group">
                                <label for="rum_batch_size">Tamaño de Lote</label>
                                <input type="number" 
                                       id="rum_batch_size" 
                                       name="rphub_rum_batch_size" 
                                       value="<?php echo esc_attr($rum_batch_size); ?>"
                                       min="5" 
                                       max="50" 
                                       step="1"
                                       class="small-text" />
                                <p class="description">
                                    Número de métricas a enviar en cada lote (recomendado: 10)
                                </p>
                            </div>
                            
                            <?php submit_button('Guardar Configuración RUM'); ?>
                        </form>
                    </div>
                    
                    <!-- Analytics Overview -->
                    <div class="rphub-settings-card">
                        <h2>Resumen de Analytics</h2>
                        
                        <div id="rphub-analytics-overview">
                            <p>Cargando resumen...</p>
                        </div>
                        
                        <div class="rphub-actions">
                            <button type="button" 
                                    class="button button-secondary" 
                                    id="rphub-manual-sync">
                                Sincronizar Ahora
                            </button>
                            <button type="button" 
                                    class="button button-secondary" 
                                    id="rphub-refresh-overview">
                                Actualizar Resumen
                            </button>
                        </div>
                    </div>
                    
                    <!-- Database Tables Info -->
                    <div class="rphub-settings-card">
                        <h2>Estado de Base de Datos</h2>
                        
                        <div id="rphub-database-status">
                            <?php $this->render_database_status(); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Authorize Google access
            $('#rphub-authorize-google').on('click', function() {
                const clientId = $('#google_client_id').val();
                if (!clientId) {
                    alert('Por favor, guarda primero el Client ID de Google');
                    return;
                }
                
                const redirectUri = '<?php echo admin_url('admin-ajax.php'); ?>?action=rphub_oauth_callback';
                const scope = 'https://www.googleapis.com/auth/analytics.readonly https://www.googleapis.com/auth/webmasters.readonly';
                const authUrl = 'https://accounts.google.com/oauth/authorize?' +
                    'client_id=' + encodeURIComponent(clientId) +
                    '&redirect_uri=' + encodeURIComponent(redirectUri) +
                    '&scope=' + encodeURIComponent(scope) +
                    '&response_type=code' +
                    '&access_type=offline' +
                    '&prompt=consent';
                
                window.open(authUrl, 'google_auth', 'width=500,height=600');
            });
            
            // Test connection
            $('#rphub-test-connection').on('click', function() {
                const button = $(this);
                const results = $('#rphub-connection-results');
                
                button.prop('disabled', true).text('Probando...');
                results.html('<p>Probando conexiones...</p>');
                
                $.post(ajaxurl, {
                    action: 'rphub_test_analytics_connection',
                    nonce: '<?php echo wp_create_nonce('rphub_ajax'); ?>'
                }, function(response) {
                    button.prop('disabled', false).text('Probar Conexión');
                    
                    if (response.success) {
                        let html = '<div class="notice notice-success"><p>Prueba completada</p></div>';
                        
                        if (response.data.results.ga4.success) {
                            html += '<p> Google Analytics 4: ' + response.data.results.ga4.message + '</p>';
                        } else {
                            html += '<p> Google Analytics 4: ' + response.data.results.ga4.message + '</p>';
                        }
                        
                        if (response.data.results.search_console.success) {
                            html += '<p> Search Console: ' + response.data.results.search_console.message + '</p>';
                        } else {
                            html += '<p> Search Console: ' + response.data.results.search_console.message + '</p>';
                        }
                        
                        if (response.data.results.web_vitals.success) {
                            html += '<p> Web Vitals: ' + response.data.results.web_vitals.message + '</p>';
                        } else {
                            html += '<p> Web Vitals: ' + response.data.results.web_vitals.message + '</p>';
                        }
                        
                        results.html(html);
                    } else {
                        results.html('<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>');
                    }
                });
            });
            
            // Revoke access
            $('#rphub-revoke-access').on('click', function() {
                if (!confirm('¿Estás seguro de que quieres revocar el acceso a Google Analytics?')) {
                    return;
                }
                
                $.post(ajaxurl, {
                    action: 'rphub_revoke_analytics_access',
                    nonce: '<?php echo wp_create_nonce('rphub_ajax'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error revocando acceso: ' + response.data);
                    }
                });
            });
            
            // Manual sync
            $('#rphub-manual-sync').on('click', function() {
                const button = $(this);
                button.prop('disabled', true).text('Sincronizando...');
                
                $.post(ajaxurl, {
                    action: 'rphub_sync_analytics',
                    nonce: '<?php echo wp_create_nonce('rphub_ajax'); ?>'
                }, function(response) {
                    button.prop('disabled', false).text('Sincronizar Ahora');
                    
                    if (response.success) {
                        alert('Sincronización completada: ' + response.data);
                        loadAnalyticsOverview();
                    } else {
                        alert('Error en sincronización: ' + response.data);
                    }
                });
            });
            
            // Refresh overview
            $('#rphub-refresh-overview').on('click', function() {
                loadAnalyticsOverview();
            });
            
            // Load analytics overview
            function loadAnalyticsOverview() {
                $('#rphub-analytics-overview').html('<p>Cargando resumen...</p>');
                
                $.post(ajaxurl, {
                    action: 'rphub_get_analytics_overview',
                    nonce: '<?php echo wp_create_nonce('rphub_ajax'); ?>'
                }, function(response) {
                    if (response.success) {
                        const data = response.data;
                        let html = '<div class="rphub-overview-stats">';
                        html += '<div class="stat-item"><strong>Sesiones (7d):</strong> ' + data.total_sessions.toLocaleString() + '</div>';
                        html += '<div class="stat-item"><strong>Usuarios (7d):</strong> ' + data.total_users.toLocaleString() + '</div>';
                        html += '<div class="stat-item"><strong>Páginas vistas (7d):</strong> ' + data.total_pageviews.toLocaleString() + '</div>';
                        html += '<div class="stat-item"><strong>Clics Search Console (7d):</strong> ' + data.total_clicks.toLocaleString() + '</div>';
                        html += '<div class="stat-item"><strong>Puntos de datos recientes:</strong> ' + data.recent_data_points + '</div>';
                        html += '<div class="stat-item"><strong>Última sincronización:</strong> ' + data.last_sync + '</div>';
                        html += '</div>';
                        
                        $('#rphub-analytics-overview').html(html);
                    } else {
                        $('#rphub-analytics-overview').html('<p>Error cargando resumen: ' + response.data + '</p>');
                    }
                });
            }
            
            // Load overview on page load
            loadAnalyticsOverview();
        });
        </script>
        
        <style>
        .rphub-analytics-settings {
            max-width: 1200px;
        }
        
        .rphub-settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        
        .rphub-settings-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        
        .rphub-settings-card h2 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .rphub-form-group {
            margin-bottom: 20px;
        }
        
        .rphub-form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .rphub-success {
            color: #46b450;
            font-weight: 600;
        }
        
        .rphub-oauth-section {
            border-top: 1px solid #eee;
            padding-top: 20px;
            margin-top: 20px;
        }
        
        .rphub-actions {
            margin-top: 15px;
        }
        
        .rphub-actions .button {
            margin-right: 10px;
        }
        
        .rphub-overview-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .stat-item {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 3px;
            border-left: 3px solid #0073aa;
        }
        
        @media (max-width: 768px) {
            .rphub-settings-grid {
                grid-template-columns: 1fr;
            }
            
            .rphub-overview-stats {
                grid-template-columns: 1fr;
            }
        }
        </style>
        <?php
    }
    
    private function render_database_status() {
        global $wpdb;
        
        $tables = array(
            'rphub_analytics_ga4' => 'Google Analytics 4',
            'rphub_analytics_search_console' => 'Search Console',
            'rphub_analytics_web_vitals' => 'Web Vitals',
            'rphub_analytics_rum' => 'Real User Monitoring',
            'rphub_rum_data' => 'RUM Raw Data'
        );
        
        echo '<div class="rphub-database-tables">';
        
        foreach ($tables as $table => $name) {
            $full_table = $wpdb->prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table'") === $full_table;
            
            if ($exists) {
                $count = $wpdb->get_var("SELECT COUNT(*) FROM $full_table");
                echo '<div class="table-status"><strong>' . $name . ':</strong>  ' . number_format($count) . ' registros</div>';
            } else {
                echo '<div class="table-status"><strong>' . $name . ':</strong>  Tabla no encontrada</div>';
            }
        }
        
        echo '</div>';
        
        echo '<style>
        .rphub-database-tables {
            margin-top: 10px;
        }
        
        .table-status {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        
        .table-status:last-child {
            border-bottom: none;
        }
        </style>';
    }
    
    public function handle_oauth_callback() {
        if (!current_user_can('manage_options')) {
            wp_die('Permisos insuficientes');
        }
        
        $code = $_GET['code'] ?? '';
        if (empty($code)) {
            wp_die('Código de autorización no recibido');
        }
        
        $client_id = get_option('replanta_hub_google_client_id');
        $client_secret = get_option('replanta_hub_google_client_secret');
        $redirect_uri = admin_url('admin-ajax.php') . '?action=rphub_oauth_callback';
        
        // Exchange code for tokens
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri' => $redirect_uri,
                'grant_type' => 'authorization_code',
                'code' => $code
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            wp_die('Error conectando con Google: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['access_token'])) {
            $settings = get_option('replanta_hub_analytics_settings', array());
            $settings['access_token'] = $data['access_token'];
            $settings['refresh_token'] = $data['refresh_token'] ?? '';
            $settings['token_expires'] = time() + ($data['expires_in'] ?? 3600);
            
            update_option('replanta_hub_analytics_settings', $settings);
            
            echo '<script>window.close(); window.opener.location.reload();</script>';
        } else {
            wp_die('Error obteniendo tokens: ' . json_encode($data));
        }
    }
    
    public function revoke_analytics_access() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        // Clear stored tokens
        $settings = get_option('replanta_hub_analytics_settings', array());
        unset($settings['access_token']);
        unset($settings['refresh_token']);
        unset($settings['token_expires']);
        
        update_option('replanta_hub_analytics_settings', $settings);
        
        wp_send_json_success('Acceso revocado correctamente');
    }
}

// Instantiated by replanta-hub.php — do not instantiate here.
