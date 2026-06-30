<?php
/**
 * Configuration and Setup Wizard for Replanta Hub Professional
 * 
 * Initial setup and configuration management
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ReplantaHub_Setup_Wizard {
    
    public function __construct() {
        add_action('admin_init', array($this, 'check_setup_status'));
        add_action('wp_ajax_rphub_save_configuration', array($this, 'save_configuration'));
        add_action('wp_ajax_rphub_test_api_credentials', array($this, 'test_api_credentials'));
    }
    
    public function check_setup_status() {
        $setup_completed = get_option('rphub_setup_completed', false)
                        || get_option('replanta_hub_setup_completed', false);
        
        if (!$setup_completed && isset($_GET['page']) && $_GET['page'] === 'replanta-hub') {
            wp_redirect(admin_url('admin.php?page=replanta-hub-wizard'));
            exit;
        }
    }
    
    public function display_setup_wizard() {
        // Load existing configuration values
        $pagespeed_api_key = get_option('replanta_hub_pagespeed_api_key', '');
        $cloudflare_email = get_option('replanta_hub_cloudflare_email', '');
        $cloudflare_api_key = get_option('replanta_hub_cloudflare_api_key', '');
        $whm_server = get_option('replanta_hub_whm_server', '');
        $whm_username = get_option('replanta_hub_whm_username', 'replanta');
        $whm_api_token = get_option('replanta_hub_whm_api_token', '');
        $backuply_enabled = get_option('rphub_backuply_enabled', true);
        $notification_email = get_option('replanta_hub_notification_email', get_option('admin_email'));
        
        ?>
        <div class="wrap replanta-hub-setup">
            <h1> Replanta Hub - Asistente de Configuración Inicial</h1>
            
            <div class="setup-container">
                <div class="setup-progress">
                    <div class="progress-step active" data-step="1">
                        <span class="step-number">1</span>
                        <span class="step-title">Bienvenida</span>
                    </div>
                    <div class="progress-step" data-step="2">
                        <span class="step-number">2</span>
                        <span class="step-title">APIs</span>
                    </div>
                    <div class="progress-step" data-step="3">
                        <span class="step-number">3</span>
                        <span class="step-title">Base de Datos</span>
                    </div>
                    <div class="progress-step" data-step="4">
                        <span class="step-number">4</span>
                        <span class="step-title">Automatización</span>
                    </div>
                    <div class="progress-step" data-step="5">
                        <span class="step-number">5</span>
                        <span class="step-title">Finalizar</span>
                    </div>
                </div>
                
                <!-- Step 1: Welcome -->
                <div class="setup-step" id="step-1">
                    <div class="step-content">
                        <h2>¡Bienvenido a Replanta Hub Professional!</h2>
                        
                        <div class="welcome-features">
                            <div class="feature-grid">
                                <div class="feature-card">
                                    <div class="feature-icon"></div>
                                    <h3>WP Toolkit Pro</h3>
                                    <p>Gestión avanzada de WordPress con actualizaciones inteligentes y análisis de seguridad</p>
                                </div>
                                
                                <div class="feature-card">
                                    <div class="feature-icon"></div>
                                    <h3>Backuply</h3>
                                    <p>Backups automáticos y restauración vía plugin Backuply en sitios cliente</p>
                                </div>
                                
                                <div class="feature-card">
                                    <div class="feature-icon"></div>
                                    <h3>PageSpeed Insights</h3>
                                    <p>Análisis de rendimiento con Core Web Vitals y optimizaciones</p>
                                </div>
                                
                                <div class="feature-card">
                                    <div class="feature-icon"></div>
                                    <h3>Cloudflare</h3>
                                    <p>Analytics, seguridad y gestión de CDN profesional</p>
                                </div>
                                
                                <div class="feature-card">
                                    <div class="feature-icon"></div>
                                    <h3>Reportes Avanzados</h3>
                                    <p>Reportes comprensivos con exportación PDF y dashboard profesional</p>
                                </div>
                                
                                <div class="feature-card">
                                    <div class="feature-icon"></div>
                                    <h3>Automatización IA</h3>
                                    <p>Mantenimiento automático inteligente 24/7 con aprendizaje</p>
                                </div>
                            </div>
                        </div>
                        
                        <p><strong>Este asistente te guiará para configurar todas las integraciones y comenzar a usar el sistema completo.</strong></p>
                    </div>
                </div>
                
                <!-- Step 2: API Configuration -->
                <div class="setup-step" id="step-2" style="display: none;">
                    <div class="step-content">
                        <h2> Configuración de APIs</h2>
                        <p>Configura las credenciales para las integraciones externas:</p>
                        
                        <form id="api-configuration-form">
                            <div class="api-section">
                                <h3> Google PageSpeed Insights API</h3>
                                <div class="form-group">
                                    <label for="pagespeed_api_key">API Key:</label>
                                    <input type="text" id="pagespeed_api_key" name="pagespeed_api_key" value="<?php echo esc_attr($pagespeed_api_key); ?>" placeholder="AIzaSyBOti4mM-6x9WDnZIjIeyEU21OpBXqWBgw">
                                    <small>Obtén tu API key desde <a href="https://developers.google.com/speed/docs/insights/v5/get-started" target="_blank">Google Cloud Console</a></small>
                                    <button type="button" class="button test-api" data-api="pagespeed">Probar</button>
                                    <span class="test-result" id="pagespeed-result"></span>
                                </div>
                            </div>
                            
                            <div class="api-section">
                                <h3> Cloudflare API</h3>
                                <div class="form-group">
                                    <label for="cloudflare_email">Email:</label>
                                    <input type="email" id="cloudflare_email" name="cloudflare_email" value="<?php echo esc_attr($cloudflare_email); ?>" placeholder="tu-email@dominio.com">
                                </div>
                                <div class="form-group">
                                    <label for="cloudflare_api_key">Global API Key:</label>
                                    <input type="text" id="cloudflare_api_key" name="cloudflare_api_key" value="<?php echo esc_attr($cloudflare_api_key); ?>" placeholder="c2547eb745079dac9320b638f5e225cf483cc5cfdda41">
                                    <small>Encuentra tu Global API Key en <a href="https://dash.cloudflare.com/profile/api-tokens" target="_blank">Cloudflare Dashboard</a></small>
                                    <button type="button" class="button test-api" data-api="cloudflare">Probar</button>
                                    <span class="test-result" id="cloudflare-result"></span>
                                </div>
                            </div>
                            
                            <div class="api-section">
                                <h3> WHM/cPanel API</h3>
                                <div class="form-group">
                                    <label for="whm_server">Servidor WHM:</label>
                                    <input type="text" id="whm_server" name="whm_server" value="<?php echo esc_attr($whm_server); ?>" placeholder="https://tu-servidor.com:2087">
                                </div>
                                <div class="form-group">
                                    <label for="whm_username">Usuario:</label>
                                    <input type="text" id="whm_username" name="whm_username" value="<?php echo esc_attr($whm_username); ?>" placeholder="replanta">
                                </div>
                                <div class="form-group">
                                    <label for="whm_api_token">API Token:</label>
                                    <input type="text" id="whm_api_token" name="whm_api_token" value="<?php echo esc_attr($whm_api_token); ?>" placeholder="API Token de WHM">
                                    <button type="button" class="button test-api" data-api="whm">Probar</button>
                                    <span class="test-result" id="whm-result"></span>
                                </div>
                            </div>
                            
                            <div class="api-section">
                                <h3> Backuply (Integrado vía Care)</h3>
                                <div class="form-group">
                                    <label>Backuply se gestiona automáticamente a través del plugin Replanta Care instalado en cada sitio cliente. No requiere configuración de API adicional.</label>
                                    <p class="description">Asegúrate de que el plugin Backuply esté instalado y activo en los sitios cliente.</p>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Step 3: Database Setup -->
                <div class="setup-step" id="step-3" style="display: none;">
                    <div class="step-content">
                        <h2> Configuración de Base de Datos</h2>
                        
                        <div class="database-status">
                            <h3>Estado de las Tablas:</h3>
                            <div id="database-tables-status">
                                <p>Verificando tablas...</p>
                            </div>
                            
                            <button type="button" class="button button-primary" id="create-tables">Crear/Actualizar Tablas</button>
                            <button type="button" class="button" id="verify-tables">Verificar Tablas</button>
                        </div>
                        
                    </div>
                </div>
                
                <!-- Step 4: Automation Setup -->
                <div class="setup-step" id="step-4" style="display: none;">
                    <div class="step-content">
                        <h2> Configuración de Automatización</h2>
                        
                        <div class="automation-settings">
                            <h3>Programación de Tareas:</h3>
                            
                            <div class="automation-option">
                                <label>
                                    <input type="checkbox" id="enable_hourly_monitoring" checked>
                                    <strong>Monitoreo Horario</strong>
                                </label>
                                <p>Verificación automática del estado de los sitios cada hora</p>
                            </div>
                            
                            <div class="automation-option">
                                <label>
                                    <input type="checkbox" id="enable_daily_maintenance" checked>
                                    <strong>Mantenimiento Diario</strong>
                                </label>
                                <p>Rutinas de mantenimiento automático cada día</p>
                            </div>
                            
                            <div class="automation-option">
                                <label>
                                    <input type="checkbox" id="enable_weekly_reports" checked>
                                    <strong>Reportes Semanales</strong>
                                </label>
                                <p>Generación automática de reportes comprensivos</p>
                            </div>
                            
                            <div class="automation-option">
                                <label>
                                    <input type="checkbox" id="enable_security_scans" checked>
                                    <strong>Escaneos de Seguridad</strong>
                                </label>
                                <p>Análisis automático de vulnerabilidades</p>
                            </div>
                            
                            <div class="automation-option">
                                <label>
                                    <input type="checkbox" id="enable_performance_monitoring" checked>
                                    <strong>Monitoreo de Rendimiento</strong>
                                </label>
                                <p>Análisis automático de PageSpeed Insights</p>
                            </div>
                        </div>
                        
                        <div class="notification-settings">
                            <h3>Configuración de Notificaciones:</h3>
                            
                            <div class="form-group">
                                <label for="notification_email">Email para Notificaciones:</label>
                                <input type="email" id="notification_email" name="notification_email" 
                                       value="<?php echo esc_attr($notification_email); ?>" placeholder="admin@tu-dominio.com">
                            </div>
                            
                            <div class="form-group">
                                <label for="notification_level">Nivel de Notificaciones:</label>
                                <select id="notification_level" name="notification_level">
                                    <option value="all">Todas las notificaciones</option>
                                    <option value="important" selected>Solo importantes</option>
                                    <option value="critical">Solo críticas</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Step 5: Completion -->
                <div class="setup-step" id="step-5" style="display: none;">
                    <div class="step-content">
                        <h2> ¡Configuración Completada!</h2>
                        
                        <div class="completion-summary">
                            <h3>Resumen de la Configuración:</h3>
                            <div id="configuration-summary">
                                <!-- Summary will be populated by JavaScript -->
                            </div>
                        </div>
                        
                        <div class="next-steps">
                            <h3>Próximos Pasos:</h3>
                            <ol>
                                <li>Agregar tus primeros sitios web</li>
                                <li>Configurar backups automáticos</li>
                                <li>Revisar el dashboard principal</li>
                                <li>Configurar alertas personalizadas</li>
                                <li>Generar tu primer reporte comprensivo</li>
                            </ol>
                        </div>
                        
                        <div class="quick-actions">
                            <a href="<?php echo admin_url('admin.php?page=replanta-hub'); ?>" 
                               class="button button-primary button-hero">Ir al Dashboard</a>
                            <a href="<?php echo admin_url('admin.php?page=replanta-hub-testing'); ?>" 
                               class="button button-secondary">Ejecutar Pruebas del Sistema</a>
                        </div>
                    </div>
                </div>
                
                <!-- Navigation -->
                <div class="setup-navigation">
                    <button type="button" class="button" id="prev-step" style="display: none;"> Anterior</button>
                    <button type="button" class="button button-primary" id="next-step">Siguiente </button>
                    <button type="button" class="button button-primary" id="finish-setup" style="display: none;"> Finalizar Configuración</button>
                </div>
            </div>
        </div>
        
        <style>
        .replanta-hub-setup {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .setup-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .setup-progress {
            display: flex;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            padding: 0;
        }
        
        .progress-step {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .progress-step:not(:last-child)::after {
            content: '';
            position: absolute;
            right: 0;
            top: 50%;
            width: 1px;
            height: 60%;
            background: #dee2e6;
            transform: translateY(-50%);
        }
        
        .progress-step.active {
            background: #007cba;
            color: white;
        }
        
        .progress-step.completed {
            background: #00a32a;
            color: white;
        }
        
        .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .progress-step.active .step-number,
        .progress-step.completed .step-number {
            background: rgba(255,255,255,0.9);
            color: #007cba;
        }
        
        .step-title {
            font-size: 0.9em;
            font-weight: 500;
        }
        
        .step-content {
            padding: 40px;
        }
        
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .feature-card {
            padding: 20px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .feature-icon {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .feature-card h3 {
            margin: 10px 0;
            color: #007cba;
        }
        
        .api-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .api-section h3 {
            margin-top: 0;
            color: #495057;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group select {
            width: 100%;
            max-width: 400px;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }
        
        .form-group small {
            display: block;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .test-api {
            margin-left: 10px;
            padding: 6px 12px;
        }
        
        .test-result {
            margin-left: 10px;
            font-weight: 500;
        }
        
        .test-result.success {
            color: #28a745;
        }
        
        .test-result.error {
            color: #dc3545;
        }
        
        .automation-option {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        
        .automation-option label {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 5px;
        }
        
        .setup-navigation {
            padding: 20px 40px;
            background: #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .completion-summary {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .quick-actions {
            text-align: center;
            margin-top: 30px;
        }
        
        .quick-actions .button {
            margin: 0 10px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            let currentStep = 1;
            const totalSteps = 5;
            
            // Navigation functions
            function showStep(stepNumber) {
                $('.setup-step').hide();
                $('#step-' + stepNumber).show();
                
                $('.progress-step').removeClass('active');
                $('.progress-step[data-step="' + stepNumber + '"]').addClass('active');
                
                // Update navigation buttons
                if (stepNumber === 1) {
                    $('#prev-step').hide();
                } else {
                    $('#prev-step').show();
                }
                
                if (stepNumber === totalSteps) {
                    $('#next-step').hide();
                    $('#finish-setup').show();
                } else {
                    $('#next-step').show();
                    $('#finish-setup').hide();
                }
            }
            
            $('#next-step').click(function() {
                if (currentStep < totalSteps) {
                    currentStep++;
                    showStep(currentStep);
                    
                    // Special actions for certain steps
                    if (currentStep === 3) {
                        verifyDatabaseTables();
                    }
                }
            });
            
            $('#prev-step').click(function() {
                if (currentStep > 1) {
                    currentStep--;
                    showStep(currentStep);
                }
            });
            
            // API Testing
            $('.test-api').click(function() {
                const apiType = $(this).data('api');
                const resultSpan = $('#' + apiType + '-result');
                
                resultSpan.text('Probando...').removeClass('success error');
                
                const formData = {
                    action: 'rphub_test_api_credentials',
                    nonce: '<?php echo wp_create_nonce("replanta_hub_setup"); ?>',
                    api_type: apiType,
                    credentials: getCredentials(apiType)
                };
                
                $.post(ajaxurl, formData, function(response) {
                    if (response.success) {
                        resultSpan.text(' Conectado').addClass('success');
                    } else {
                        resultSpan.text(' Error: ' + response.data).addClass('error');
                    }
                });
            });
            
            function getCredentials(apiType) {
                switch (apiType) {
                    case 'pagespeed':
                        return {
                            api_key: $('#pagespeed_api_key').val()
                        };
                    case 'cloudflare':
                        return {
                            email: $('#cloudflare_email').val(),
                            api_key: $('#cloudflare_api_key').val()
                        };
                    case 'whm':
                        return {
                            server: $('#whm_server').val(),
                            username: $('#whm_username').val(),
                            api_token: $('#whm_api_token').val()
                        };
                    case 'backuply':
                        return {
                            enabled: true
                        };
                }
                return {};
            }
            
            // Database operations
            $('#create-tables').click(function() {
                $(this).prop('disabled', true).text('Creando...');
                
                $.post(ajaxurl, {
                    action: 'rphub_create_database_tables',
                    nonce: '<?php echo wp_create_nonce("replanta_hub_setup"); ?>'
                }, function(response) {
                    $('#create-tables').prop('disabled', false).text('Crear/Actualizar Tablas');
                    verifyDatabaseTables();
                });
            });
            
            $('#verify-tables').click(function() {
                verifyDatabaseTables();
            });
            
            function verifyDatabaseTables() {
                $('#database-tables-status').html('<p>Verificando tablas...</p>');
                
                $.post(ajaxurl, {
                    action: 'rphub_verify_database_tables',
                    nonce: '<?php echo wp_create_nonce("replanta_hub_setup"); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#database-tables-status').html(response.data);
                    }
                });
            }
            
            // Finish setup
            $('#finish-setup').click(function() {
                const configData = {
                    action: 'rphub_save_configuration',
                    nonce: '<?php echo wp_create_nonce("replanta_hub_setup"); ?>',
                    apis: {
                        pagespeed: {
                            api_key: $('#pagespeed_api_key').val()
                        },
                        cloudflare: {
                            email: $('#cloudflare_email').val(),
                            api_key: $('#cloudflare_api_key').val()
                        },
                        whm: {
                            server: $('#whm_server').val(),
                            username: $('#whm_username').val(),
                            api_token: $('#whm_api_token').val()
                        },
                        backuply: {
                            enabled: true
                        }
                    },
                    automation: {
                        hourly_monitoring: $('#enable_hourly_monitoring').is(':checked'),
                        daily_maintenance: $('#enable_daily_maintenance').is(':checked'),
                        weekly_reports: $('#enable_weekly_reports').is(':checked'),
                        security_scans: $('#enable_security_scans').is(':checked'),
                        performance_monitoring: $('#enable_performance_monitoring').is(':checked')
                    },
                    notifications: {
                        email: $('#notification_email').val(),
                        level: $('#notification_level').val()
                    }
                };
                
                $(this).prop('disabled', true).text('Finalizando...');
                
                $.post(ajaxurl, configData, function(response) {
                    if (response.success) {
                        alert('¡Configuración completada exitosamente!');
                        window.location.href = '<?php echo admin_url("admin.php?page=replanta-hub"); ?>';
                    } else {
                        alert('Error al guardar configuración: ' + response.data);
                        $('#finish-setup').prop('disabled', false).text(' Finalizar Configuración');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    public function test_api_credentials() {
        check_ajax_referer('replanta_hub_setup', 'nonce');
        
        $api_type = sanitize_text_field($_POST['api_type']);
        $credentials = $_POST['credentials'];
        
        switch ($api_type) {
            case 'pagespeed':
                $result = $this->test_pagespeed_api($credentials);
                break;
            case 'cloudflare':
                $result = $this->test_cloudflare_api($credentials);
                break;
            case 'whm':
                $result = $this->test_whm_api($credentials);
                break;
            case 'backuply':
                $result = array('success' => true, 'message' => 'Backuply se gestiona via Care plugin');
                break;
            default:
                $result = array('success' => false, 'message' => 'API no soportada');
        }
        
        wp_send_json($result);
    }
    
    private function test_pagespeed_api($credentials) {
        if (empty($credentials['api_key'])) {
            return array('success' => false, 'message' => 'API Key requerida');
        }
        
        $test_url = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=https://google.com&key=' . $credentials['api_key'];
        $response = wp_remote_get($test_url);
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => 'Error de conexión');
        }
        
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code === 200) {
            return array('success' => true, 'message' => 'API conectada correctamente');
        } else {
            return array('success' => false, 'message' => 'API Key inválida');
        }
    }
    
    private function test_cloudflare_api($credentials) {
        if (empty($credentials['email']) || empty($credentials['api_key'])) {
            return array('success' => false, 'message' => 'Email y API Key requeridos');
        }
        
        $response = wp_remote_get('https://api.cloudflare.com/client/v4/user', array(
            'headers' => array(
                'X-Auth-Email' => $credentials['email'],
                'X-Auth-Key' => $credentials['api_key'],
                'Content-Type' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => 'Error de conexión');
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['success']) && $body['success']) {
            return array('success' => true, 'message' => 'API conectada correctamente');
        } else {
            return array('success' => false, 'message' => 'Credenciales inválidas');
        }
    }
    
    private function test_whm_api($credentials) {
        if (empty($credentials['server']) || empty($credentials['username']) || empty($credentials['api_token'])) {
            return array('success' => false, 'message' => 'Todos los campos son requeridos');
        }
        
        // Simulate WHM connection test
        return array('success' => true, 'message' => 'Configuración guardada (test simulado)');
    }
    
    private function test_backuply_status() {
        // Backuply is managed through Care plugin on remote sites
        return array('success' => true, 'message' => 'Backuply se gestiona via Care plugin');
    }
    
    public function save_configuration() {
        check_ajax_referer('replanta_hub_setup', 'nonce');
        
        $apis = $_POST['apis'];
        $automation = $_POST['automation'];
        $notifications = $_POST['notifications'];
        
        // Save API configurations
        update_option('replanta_hub_pagespeed_api_key', $apis['pagespeed']['api_key']);
        update_option('replanta_hub_cloudflare_email', $apis['cloudflare']['email']);
        update_option('replanta_hub_cloudflare_api_key', $apis['cloudflare']['api_key']);
        update_option('replanta_hub_whm_server', $apis['whm']['server']);
        update_option('replanta_hub_whm_username', $apis['whm']['username']);
        update_option('replanta_hub_whm_api_token', $apis['whm']['api_token']);
        
        // Enable WHM if credentials are provided
        if (!empty($apis['whm']['server']) && !empty($apis['whm']['username']) && !empty($apis['whm']['api_token'])) {
            update_option('rphub_whm_enabled', true);
        }
        // Backuply is enabled by default (managed via Care)
        update_option('rphub_backuply_enabled', true);
        
        // Save automation settings
        update_option('replanta_hub_automation_settings', $automation);
        
        // Save notification settings
        update_option('replanta_hub_notification_email', $notifications['email']);
        update_option('replanta_hub_notification_level', $notifications['level']);
        
        // Mark setup as completed
        update_option('rphub_setup_completed', true);
        update_option('replanta_hub_setup_completed', true);
        
        wp_send_json_success('Configuración guardada exitosamente');
    }
    
    // Removed: create_sample_data() - Phase 3 cleanup
}

// Wizard is instantiated and registered via ReplantaHub::add_admin_menu() in replanta-hub.php.
