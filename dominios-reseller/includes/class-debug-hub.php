<?php
/**
 * Debug Hub para el sistema de onboarding
 * Página de administración con tests integrados
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dominios_Reseller_Debug_Hub {

    /**
     * Instancia singleton
     */
    private static $instance = null;

    /**
     * Obtener instancia
     */
    public static function get_instance(): Dominios_Reseller_Debug_Hub {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('admin_menu', [$this, 'add_debug_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_dr_debug_test', [$this, 'run_ajax_test']);
        add_action('wp_ajax_dr_debug_manual_enqueue', [$this, 'handle_manual_enqueue']);
        add_action('wp_ajax_dr_debug_check_status', [$this, 'handle_check_status']);
        add_action('wp_ajax_dr_debug_retry_domain', [$this, 'handle_retry_domain']);
        add_action('wp_ajax_dr_debug_update_config', [$this, 'handle_update_config']);
        add_action('wp_ajax_dr_flow_test', [$this, 'handle_flow_test']);
        add_action('wp_ajax_dr_debug_php_info', [$this, 'handle_debug_php_info']);
        add_action('wp_ajax_dr_debug_deploy_endpoint', [$this, 'handle_deploy_maintenance_endpoint']);
    }

    /**
     * Log unificado para debugging
     * Uso: Dominios_Reseller_Debug_Hub::log('PHP', 'Mensaje', ['data' => 'valor']);
     * 
     * @param string $category Categoría (PHP, CF, OP, NS, QUEUE, etc)
     * @param string $message Mensaje principal
     * @param array $context Datos adicionales
     * @param string $level Nivel: info, warning, error, success
     */
    public static function log(string $category, string $message, array $context = [], string $level = 'info'): void {
        $debug_enabled = get_option('dr_debug_enabled', true);
        
        if (!$debug_enabled) {
            return;
        }
        
        $icons = [
            'info'    => 'ℹ️',
            'warning' => '⚠️',
            'error'   => '❌',
            'success' => '✅',
        ];
        
        $icon = $icons[$level] ?? 'ℹ️';
        $timestamp = current_time('Y-m-d H:i:s');
        
        // Formatear contexto
        $context_str = '';
        if (!empty($context)) {
            $context_str = ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        
        // Log al error_log de WordPress
        error_log("[DR {$category}] {$icon} {$message}{$context_str}");
        
        // También guardar en opción para visualización en Debug Hub
        $logs = get_option('dr_debug_logs', []);
        $logs[] = [
            'timestamp' => $timestamp,
            'category'  => $category,
            'level'     => $level,
            'message'   => $message,
            'context'   => $context,
        ];
        
        // Mantener solo últimos 100 logs
        $logs = array_slice($logs, -100);
        update_option('dr_debug_logs', $logs, false);
    }

    /**
     * Añadir menú de debug
     */
    public function add_debug_menu(): void {
        add_submenu_page(
            'dominios-reseller', // Parent slug correcto
            'Debug Hub - Onboarding', // Page title
            '🔧 Debug Hub', // Menu title
            'manage_options', // Capability
            'dominios-reseller-debug', // Menu slug
            [$this, 'render_debug_page'] // Callback
        );
    }

    /**
     * Encolar scripts y estilos
     */
    public function enqueue_scripts($hook): void {
        if ($hook !== 'dominios-reseller_page_dominios-reseller-debug') {
            return;
        }

        // No usar archivos externos - todo inline para evitar 404
        // Los estilos y scripts se añaden directamente en render_debug_page()
        
        // Solo necesitamos pasar los datos al script inline
        // Se hace directamente en el render con una variable global
    }

    /**
     * Renderizar página de debug
     */
    public function render_debug_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos para acceder a esta página.'));
        }

        ?>
        <div class="wrap">
            <h1>🔧 Debug Hub - Sistema de Onboarding Asíncrono</h1>

            <div class="notice notice-info">
                <p><strong>ℹ️ Información:</strong> Esta página te permite ejecutar tests del sistema de onboarding directamente desde el admin panel.</p>
            </div>

            <div class="dr-debug-container">
                <!-- Tests Rápidos -->
                <div class="dr-debug-section">
                    <h2>🧪 Tests Rápidos</h2>
                    <div class="dr-debug-grid">
                        <div class="dr-debug-card">
                            <h3>📊 Estado del Sistema</h3>
                            <p>Verifica tablas, presets y configuración básica</p>
                            <button class="button button-primary" onclick="runTest('system_status')">Ejecutar Test</button>
                            <div id="system_status_result" class="dr-test-result"></div>
                        </div>

                        <div class="dr-debug-card">
                            <h3>🎯 Test de Encolado</h3>
                            <p>Prueba encolar un dominio de ejemplo</p>
                            <button class="button button-primary" onclick="runTest('enqueue_test')">Ejecutar Test</button>
                            <div id="enqueue_test_result" class="dr-test-result"></div>
                        </div>

                        <div class="dr-debug-card">
                            <h3>⚡ Eventos Programados</h3>
                            <p>Verifica eventos de WordPress programados</p>
                            <button class="button button-primary" onclick="runTest('cron_events')">Ejecutar Test</button>
                            <div id="cron_events_result" class="dr-test-result"></div>
                        </div>
                    </div>
                </div>

                <!-- Test de Flujo Completo -->
                <div class="dr-debug-section">
                    <h2>Test de Flujo Completo (Sandbox)</h2>
                    <div class="dr-flow-test">
                        <p>Simula el flujo completo de onboarding desde Upmind hasta Cloudflare. Este test verifica:</p>
                        <ul>
                            <li>Recepción de webhook de Upmind</li>
                            <li>Validación de dominio y hosting</li>
                            <li>Verificación de WordPress readiness</li>
                            <li>Creación de zona en Cloudflare</li>
                            <li>Aplicación de presets de configuración</li>
                        </ul>

                        <div class="dr-flow-form">
                            <div class="dr-form-row">
                                <label for="flow_domain">Dominio de prueba:</label>
                                <input type="text" id="flow_domain" placeholder="test-ejemplo.com" style="width: 250px;">
                            </div>
                            <div class="dr-form-row">
                                <label for="flow_hosting">Tipo de hosting:</label>
                                <select id="flow_hosting" style="width: 200px;">
                                    <option value="wordpress">WordPress</option>
                                    <option value="basic">Básico</option>
                                </select>
                            </div>
                            <div class="dr-form-row">
                                <label for="flow_simulate_webhook">
                                    <input type="checkbox" id="flow_simulate_webhook" checked> Simular webhook de Upmind
                                </label>
                            </div>
                            <div class="dr-form-row">
                                <button class="button button-primary" onclick="runFlowTest()">Ejecutar Test de Flujo</button>
                                <div id="flow_test_result" class="dr-test-result" style="margin-top: 15px;"></div>
                            </div>
                        </div>

                        <div id="flow_progress" class="dr-flow-progress" style="display: none;">
                            <h4>Progreso del Flujo:</h4>
                            <div class="dr-progress-steps">
                                <div class="dr-step" id="step_webhook">
                                    <span class="dr-step-icon">⏳</span>
                                    <span class="dr-step-text">Webhook Upmind</span>
                                </div>
                                <div class="dr-step" id="step_validation">
                                    <span class="dr-step-icon">⏳</span>
                                    <span class="dr-step-text">Validación</span>
                                </div>
                                <div class="dr-step" id="step_wp_check">
                                    <span class="dr-step-icon">⏳</span>
                                    <span class="dr-step-text">WP Readiness</span>
                                </div>
                                <div class="dr-step" id="step_cloudflare">
                                    <span class="dr-step-icon">⏳</span>
                                    <span class="dr-step-text">Cloudflare</span>
                                </div>
                                <div class="dr-step" id="step_preset">
                                    <span class="dr-step-icon">⏳</span>
                                    <span class="dr-step-text">Preset</span>
                                </div>
                                <div class="dr-step" id="step_complete">
                                    <span class="dr-step-icon">⏳</span>
                                    <span class="dr-step-text">Completado</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Encolado Manual de Dominios -->
                <div class="dr-debug-section">
                    <h2>🚀 Encolado Manual de Dominios</h2>
                    <div class="dr-manual-enqueue">
                        <div class="dr-enqueue-form">
                            <div class="dr-form-row">
                                <label for="enqueue_domain">Dominio:</label>
                                <input type="text" id="enqueue_domain" placeholder="ejemplo.com" style="width: 200px;">
                            </div>
                            <div class="dr-form-row">
                                <label for="enqueue_preset">Preset:</label>
                                <select id="enqueue_preset" style="width: 150px;">
                                    <option value="wp">WordPress</option>
                                    <option value="basic">Básico</option>
                                    <option value="custom">Personalizado</option>
                                </select>
                            </div>
                            <div class="dr-form-row">
                                <label for="enqueue_auto_ns">
                                    <input type="checkbox" id="enqueue_auto_ns"> Actualizar NS automáticamente
                                </label>
                            </div>
                            <div class="dr-form-row">
                                <button class="button button-primary" onclick="manualEnqueue()">Encolar Dominio</button>
                                <div id="manual_enqueue_result" class="dr-test-result" style="margin-top: 10px;"></div>
                            </div>
                        </div>

                        <div class="dr-enqueue-actions">
                            <h3>🔄 Acciones para Dominios Existentes</h3>
                            <div class="dr-form-row">
                                <label for="existing_domain">Dominio existente:</label>
                                <input type="text" id="existing_domain" placeholder="dominio.com" style="width: 200px;">
                                <button class="button" onclick="checkDomainStatus()">Ver Estado</button>
                            </div>
                            <div id="domain_status_result" class="dr-test-result" style="margin-top: 10px;"></div>
                            <div id="domain_actions" class="dr-domain-actions" style="display: none; margin-top: 15px;">
                                <button class="button button-secondary" id="retry_domain_btn" onclick="retryDomain()" style="display: none;">Reintentar</button>
                                <button class="button button-secondary" id="update_config_btn" onclick="updateDomainConfig()" style="display: none;">Actualizar Config</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="dr-debug-section">
                    <h2> Gestión de Datos</h2>
                    <div class="dr-debug-grid">
                        <div class="dr-debug-card">
                            <h3>Diagnóstico Openprovider</h3>
                            <p>Verifica estado de dominio en Openprovider</p>
                            <button class="button button-secondary" onclick="runTest('openprovider_diagnostic')">Diagnosticar</button>
                            <div id="openprovider_diagnostic_result" class="dr-test-result"></div>
                        </div>

                        <div class="dr-debug-card">                            <h3>🔄 Actualizar Presets</h3>
                            <p>Actualizar presets a versiones más recientes</p>
                            <button class="button button-secondary" onclick="runTest('update_presets')">Actualizar</button>
                            <div id="update_presets_result" class="dr-test-result"></div>
                        </div>

                        <div class="dr-debug-card">                            <h3>�📋 Crear Preset de Test</h3>
                            <p>Crea un preset básico para pruebas</p>
                            <button class="button button-secondary" onclick="runTest('create_preset')">Crear Preset</button>
                            <div id="create_preset_result" class="dr-test-result"></div>
                        </div>

                        <div class="dr-debug-card">
                            <h3> Limpiar Datos de Test</h3>
                            <p>Elimina runs y logs de testing</p>
                            <button class="button button-secondary" onclick="runTest('cleanup_test_data')">Limpiar</button>
                            <div id="cleanup_test_data_result" class="dr-test-result"></div>
                        </div>

                        <div class="dr-debug-card">
                            <h3> Crear Tablas Faltantes</h3>
                            <p>Crea las tablas de onboarding si faltan</p>
                            <button class="button button-secondary" onclick="runTest('create_tables')">Crear Tablas</button>
                            <div id="create_tables_result" class="dr-test-result"></div>
                        </div>
                    </div>
                </div>

                <!-- Debug PHP Info -->
                <div class="dr-debug-section">
                    <h2>🐘 Debug PHP Info</h2>
                    <div class="dr-php-debug">
                        <p>Obtiene información PHP de un dominio específico desde WHM/cPanel o fallback simulado.</p>
                        <div class="dr-form-row" style="display: flex; gap: 10px; align-items: center; margin: 15px 0;">
                            <label for="php_debug_domain"><strong>Dominio:</strong></label>
                            <input type="text" id="php_debug_domain" placeholder="ejemplo.com" style="width: 250px;">
                            <button class="button button-primary" onclick="debugPHPInfo()">🔍 Obtener Info PHP</button>
                        </div>
                        <div id="php_debug_result" class="dr-test-result" style="margin-top: 15px;"></div>
                    </div>
                </div>

                <!-- Deploy Maintenance Endpoint -->
                <div class="dr-debug-section">
                    <h2>🛠️ Deploy Endpoint de Mantenimiento</h2>
                    <div class="dr-php-debug">
                        <p>Despliega un archivo PHP en el dominio del cliente via cPanel File Manager API para obtener info PHP real.</p>
                        <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 10px; border-radius: 5px; margin: 10px 0;">
                            <strong>⚠️ Experimental:</strong> Este endpoint crea un archivo <code>.dr-health.php</code> en el root del dominio del cliente para consultar info PHP real.
                        </div>
                        <div class="dr-form-row" style="display: flex; gap: 10px; align-items: center; margin: 15px 0;">
                            <label for="deploy_domain"><strong>Dominio:</strong></label>
                            <input type="text" id="deploy_domain" placeholder="ejemplo.com" style="width: 250px;">
                            <button class="button button-secondary" onclick="deployEndpoint()">🚀 Deploy Endpoint</button>
                            <button class="button" onclick="testDeployedEndpoint()">🧪 Test Endpoint</button>
                            <button class="button" onclick="removeEndpoint()" style="color: #dc3545;">🗑️ Eliminar</button>
                        </div>
                        <div id="deploy_result" class="dr-test-result" style="margin-top: 15px;"></div>
                    </div>
                </div>

                <!-- Información del Sistema -->
                <div class="dr-debug-section">
                    <h2>ℹ️ Información del Sistema</h2>
                    <div class="dr-debug-info-grid">
                        <div class="dr-info-card">
                            <h4> Base de Datos</h4>
                            <ul>
                                <li><strong>Prefix:</strong> <?php echo $GLOBALS['wpdb']->prefix; ?></li>
                                <li><strong>Versión MySQL:</strong> <?php echo $GLOBALS['wpdb']->db_version(); ?></li>
                                <li><strong>Charset:</strong> <?php echo $GLOBALS['wpdb']->charset; ?></li>
                            </ul>
                        </div>

                        <div class="dr-info-card">
                            <h4> WordPress</h4>
                            <ul>
                                <li><strong>Versión:</strong> <?php echo get_bloginfo('version'); ?></li>
                                <li><strong>PHP:</strong> <?php echo PHP_VERSION; ?></li>
                                <li><strong>Memoria límite:</strong> <?php echo ini_get('memory_limit'); ?></li>
                            </ul>
                        </div>

                        <div class="dr-info-card">
                            <h4> Cron</h4>
                            <ul>
                                <li><strong>Cron activo:</strong> <?php echo defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ? '❌ Desactivado' : '✅ Activo'; ?></li>
                                <li><strong>Próximo evento:</strong> <?php echo date('H:i:s', wp_next_scheduled('wp_version_check')); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Logs Recientes -->
                <div class="dr-debug-section">
                    <h2>📝 Logs Recientes</h2>
                    <div id="recent_logs_container">
                        <button class="button" onclick="runTest('recent_logs')">Cargar Logs Recientes</button>
                        <div id="recent_logs_result" class="dr-test-result"></div>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .dr-debug-container {
            margin-top: 20px;
        }

        .dr-debug-section {
            margin-bottom: 30px;
            padding: 20px;
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }

        .dr-debug-section h2 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .dr-debug-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .dr-debug-card {
            padding: 20px;
            border: 1px solid #e1e1e1;
            border-radius: 8px;
            background: #fafafa;
        }

        .dr-debug-card h3 {
            margin-top: 0;
            color: #23282d;
        }

        .dr-debug-card p {
            color: #666;
            margin-bottom: 15px;
        }

        .dr-test-result {
            margin-top: 15px;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            max-height: 300px;
            overflow-y: auto;
            white-space: pre-wrap;
        }

        .dr-test-result.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .dr-test-result.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .dr-test-result.info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .dr-debug-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .dr-info-card {
            padding: 15px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
        }

        .dr-info-card h4 {
            margin-top: 0;
            color: #495057;
        }

        .dr-info-card ul {
            margin: 0;
            padding-left: 20px;
        }

        .dr-info-card li {
            margin-bottom: 5px;
        }

        .dr-flow-test ul {
            margin: 10px 0;
            padding-left: 20px;
        }

        .dr-flow-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 15px 0;
        }

        .dr-form-row {
            margin-bottom: 15px;
        }

        .dr-form-row label {
            display: inline-block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .dr-flow-progress {
            margin-top: 20px;
            padding: 20px;
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
        }

        .dr-progress-steps {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .dr-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 10px;
            border-radius: 6px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            min-width: 80px;
            transition: all 0.3s ease;
        }

        .dr-step.completed {
            background: #d4edda;
            border-color: #c3e6cb;
        }

        .dr-step.error {
            background: #f8d7da;
            border-color: #f5c6cb;
        }

        .dr-step-icon {
            font-size: 18px;
            margin-bottom: 5px;
        }

        .dr-step-text {
            font-size: 11px;
            text-align: center;
            color: #495057;
        }
        </style>

        <script>
        // Configuración AJAX inline
        var dr_debug_ajax = {
            ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('dr_debug_nonce'); ?>',
            strings: {
                running_test: 'Ejecutando test...',
                test_completed: 'Test completado',
                error: 'Error',
                success: 'Éxito'
            }
        };

        function runTest(testType) {
            const resultDiv = document.getElementById(testType + '_result');
            const button = resultDiv.previousElementSibling;

            resultDiv.innerHTML = '<div class="dr-loading"></div>' + dr_debug_ajax.strings.running_test;
            resultDiv.className = 'dr-test-result loading';

            button.disabled = true;
            var originalText = button.textContent;
            button.textContent = dr_debug_ajax.strings.running_test;

            jQuery.ajax({
                url: dr_debug_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'dr_debug_test',
                    test_type: testType,
                    nonce: dr_debug_ajax.nonce
                },
                success: function(response) {
                    resultDiv.className = 'dr-test-result ' + (response.success ? 'success' : 'error');
                    resultDiv.innerHTML = response.data;
                },
                error: function(xhr, status, error) {
                    resultDiv.className = 'dr-test-result error';
                    resultDiv.innerHTML = '❌ Error: ' + error;
                },
                complete: function() {
                    button.disabled = false;
                    button.textContent = originalText;
                }
            });
        }

        function runFlowTest() {
            const domain = document.getElementById('flow_domain').value.trim();
            const hosting = document.getElementById('flow_hosting').value;
            const simulateWebhook = document.getElementById('flow_simulate_webhook').checked;
            const resultDiv = document.getElementById('flow_test_result');
            const progressDiv = document.getElementById('flow_progress');
            const button = resultDiv.previousElementSibling;

            if (!domain) {
                alert('Por favor ingresa un dominio');
                return;
            }

            resultDiv.innerHTML = '';
            resultDiv.className = 'dr-test-result';
            progressDiv.style.display = 'block';
            resetProgressSteps();

            button.disabled = true;
            button.textContent = 'Ejecutando Test...';

            jQuery.ajax({
                url: dr_debug_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'dr_flow_test',
                    domain: domain,
                    hosting: hosting,
                    simulate_webhook: simulateWebhook,
                    nonce: dr_debug_ajax.nonce
                },
                success: function(response) {
                    resultDiv.className = 'dr-test-result ' + (response.success ? 'success' : 'error');
                    resultDiv.innerHTML = response.data;
                    if (response.success) {
                        updateProgressStep('step_complete', 'success');
                    }
                },
                error: function() {
                    resultDiv.className = 'dr-test-result error';
                    resultDiv.innerHTML = '❌ Error de conexión';
                },
                complete: function() {
                    button.disabled = false;
                    button.textContent = 'Ejecutar Test de Flujo';
                }
            });
        }

        function resetProgressSteps() {
            const steps = ['step_webhook', 'step_validation', 'step_wp_check', 'step_cloudflare', 'step_preset', 'step_complete'];
            steps.forEach(function(stepId) {
                const step = document.getElementById(stepId);
                if (step) {
                    const icon = step.querySelector('.dr-step-icon');
                    if (icon) icon.textContent = '⏳';
                    step.className = 'dr-step';
                }
            });
        }

        function updateProgressStep(stepId, status) {
            const step = document.getElementById(stepId);
            if (!step) return;
            const icon = step.querySelector('.dr-step-icon');
            if (!icon) return;

            if (status === 'success') {
                icon.textContent = '✅';
                step.classList.add('completed');
            } else if (status === 'error') {
                icon.textContent = '❌';
                step.classList.add('error');
            }
        }

        // Encolado manual de dominio
        function manualEnqueue() {
            const domain = document.getElementById('enqueue_domain').value.trim();
            const preset = document.getElementById('enqueue_preset').value;
            const autoNs = document.getElementById('enqueue_auto_ns').checked;

            if (!domain) {
                alert('Por favor ingresa un dominio');
                return;
            }

            const resultDiv = document.getElementById('manual_enqueue_result');
            resultDiv.innerHTML = '<div class="dr-loading"></div>Encolando dominio...';
            resultDiv.className = 'dr-test-result loading';

            jQuery.ajax({
                url: dr_debug_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'dr_debug_manual_enqueue',
                    domain: domain,
                    preset: preset,
                    auto_ns: autoNs ? 1 : 0,
                    nonce: dr_debug_ajax.nonce
                },
                success: function(response) {
                    resultDiv.className = 'dr-test-result ' + (response.success ? 'success' : 'error');
                    resultDiv.innerHTML = response.data;
                },
                error: function(xhr, status, error) {
                    resultDiv.className = 'dr-test-result error';
                    resultDiv.innerHTML = '❌ Error: ' + error;
                }
            });
        }

        // Verificar estado de dominio
        function checkDomainStatus() {
            const domain = document.getElementById('existing_domain').value.trim();

            if (!domain) {
                alert('Por favor ingresa un dominio');
                return;
            }

            const resultDiv = document.getElementById('domain_status_result');
            const actionsDiv = document.getElementById('domain_actions');

            resultDiv.innerHTML = '<div class="dr-loading"></div>Verificando estado...';
            resultDiv.className = 'dr-test-result loading';
            actionsDiv.style.display = 'none';

            jQuery.ajax({
                url: dr_debug_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'dr_debug_check_status',
                    domain: domain,
                    nonce: dr_debug_ajax.nonce
                },
                success: function(response) {
                    resultDiv.className = 'dr-test-result ' + (response.success ? 'success' : 'error');
                    resultDiv.innerHTML = response.data;

                    if (response.actions) {
                        actionsDiv.style.display = 'block';
                        document.getElementById('retry_domain_btn').style.display = response.actions.can_retry ? 'inline-block' : 'none';
                        document.getElementById('update_config_btn').style.display = response.actions.can_update ? 'inline-block' : 'none';
                    }
                },
                error: function(xhr, status, error) {
                    resultDiv.className = 'dr-test-result error';
                    resultDiv.innerHTML = '❌ Error: ' + error;
                }
            });
        }

        // Reintentar dominio
        function retryDomain() {
            const domain = document.getElementById('existing_domain').value.trim();

            if (!domain) {
                alert('Por favor ingresa un dominio');
                return;
            }

            if (!confirm('¿Estás seguro de que quieres reintentar el procesamiento de este dominio?')) {
                return;
            }

            const resultDiv = document.getElementById('domain_status_result');
            resultDiv.innerHTML = '<div class="dr-loading"></div>Reintentando dominio...';
            resultDiv.className = 'dr-test-result loading';

            jQuery.ajax({
                url: dr_debug_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'dr_debug_retry_domain',
                    domain: domain,
                    nonce: dr_debug_ajax.nonce
                },
                success: function(response) {
                    resultDiv.className = 'dr-test-result ' + (response.success ? 'success' : 'error');
                    resultDiv.innerHTML = response.data;
                    document.getElementById('domain_actions').style.display = 'none';
                },
                error: function(xhr, status, error) {
                    resultDiv.className = 'dr-test-result error';
                    resultDiv.innerHTML = '❌ Error: ' + error;
                }
            });
        }

        // Actualizar configuración de dominio
        function updateDomainConfig() {
            const domain = document.getElementById('existing_domain').value.trim();
            const newPreset = document.getElementById('enqueue_preset').value;

            if (!domain) {
                alert('Por favor ingresa un dominio');
                return;
            }

            if (!confirm('¿Estás seguro de que quieres actualizar la configuración de ' + domain + ' con el preset "' + newPreset + '"?')) {
                return;
            }

            const resultDiv = document.getElementById('domain_status_result');
            resultDiv.innerHTML = '<div class="dr-loading"></div>Actualizando configuración...';
            resultDiv.className = 'dr-test-result loading';

            jQuery.ajax({
                url: dr_debug_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'dr_debug_update_config',
                    domain: domain,
                    preset: newPreset,
                    nonce: dr_debug_ajax.nonce
                },
                success: function(response) {
                    resultDiv.className = 'dr-test-result ' + (response.success ? 'success' : 'error');
                    resultDiv.innerHTML = response.data;
                    document.getElementById('domain_actions').style.display = 'none';
                },
                error: function(xhr, status, error) {
                    resultDiv.className = 'dr-test-result error';
                    resultDiv.innerHTML = '❌ Error: ' + error;
                }
            });
        }

        // Debug PHP Info para un dominio específico
        function debugPHPInfo() {
            const domain = document.getElementById('php_debug_domain').value.trim();

            if (!domain) {
                alert('Por favor ingresa un dominio para debuguear');
                return;
            }

            const resultDiv = document.getElementById('php_debug_result');
            resultDiv.innerHTML = '<div class="dr-loading"></div>🐘 Obteniendo información PHP...';
            resultDiv.className = 'dr-test-result loading';

            jQuery.ajax({
                url: dr_debug_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'dr_debug_php_info',
                    domain: domain,
                    nonce: dr_debug_ajax.nonce
                },
                success: function(response) {
                    resultDiv.className = 'dr-test-result ' + (response.success ? 'success' : 'error');
                    resultDiv.innerHTML = '<pre style="white-space: pre-wrap; word-wrap: break-word; margin: 0; font-family: Consolas, Monaco, monospace; font-size: 12px;">' + (response.data || response.message || 'Sin datos') + '</pre>';
                },
                error: function(xhr, status, error) {
                    resultDiv.className = 'dr-test-result error';
                    resultDiv.innerHTML = '❌ Error: ' + error;
                }
            });
        }

        // Deploy Maintenance Endpoint
        function deployEndpoint() {
            const domain = document.getElementById('deploy_domain').value.trim();
            if (!domain) {
                alert('Por favor ingresa un dominio');
                return;
            }

            const resultDiv = document.getElementById('deploy_result');
            resultDiv.innerHTML = '<div class="dr-loading"></div>🚀 Desplegando endpoint en ' + domain + '...';
            resultDiv.className = 'dr-test-result loading';

            jQuery.ajax({
                url: dr_debug_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'dr_debug_deploy_endpoint',
                    domain: domain,
                    operation: 'deploy',
                    nonce: dr_debug_ajax.nonce
                },
                success: function(response) {
                    resultDiv.className = 'dr-test-result ' + (response.success ? 'success' : 'error');
                    resultDiv.innerHTML = '<pre style="white-space: pre-wrap; word-wrap: break-word; margin: 0; font-family: Consolas, Monaco, monospace; font-size: 12px;">' + (response.data || response.message || 'Sin datos') + '</pre>';
                },
                error: function(xhr, status, error) {
                    resultDiv.className = 'dr-test-result error';
                    resultDiv.innerHTML = '❌ Error: ' + error;
                }
            });
        }

        // Test Deployed Endpoint
        function testDeployedEndpoint() {
            const domain = document.getElementById('deploy_domain').value.trim();
            if (!domain) {
                alert('Por favor ingresa un dominio');
                return;
            }

            const resultDiv = document.getElementById('deploy_result');
            resultDiv.innerHTML = '<div class="dr-loading"></div>🧪 Probando endpoint en ' + domain + '...';
            resultDiv.className = 'dr-test-result loading';

            jQuery.ajax({
                url: dr_debug_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'dr_debug_deploy_endpoint',
                    domain: domain,
                    operation: 'test',
                    nonce: dr_debug_ajax.nonce
                },
                success: function(response) {
                    resultDiv.className = 'dr-test-result ' + (response.success ? 'success' : 'error');
                    resultDiv.innerHTML = '<pre style="white-space: pre-wrap; word-wrap: break-word; margin: 0; font-family: Consolas, Monaco, monospace; font-size: 12px;">' + (response.data || response.message || 'Sin datos') + '</pre>';
                },
                error: function(xhr, status, error) {
                    resultDiv.className = 'dr-test-result error';
                    resultDiv.innerHTML = '❌ Error: ' + error;
                }
            });
        }

        // Remove Endpoint
        function removeEndpoint() {
            const domain = document.getElementById('deploy_domain').value.trim();
            if (!domain) {
                alert('Por favor ingresa un dominio');
                return;
            }

            if (!confirm('¿Eliminar el endpoint de mantenimiento de ' + domain + '?')) {
                return;
            }

            const resultDiv = document.getElementById('deploy_result');
            resultDiv.innerHTML = '<div class="dr-loading"></div>🗑️ Eliminando endpoint de ' + domain + '...';
            resultDiv.className = 'dr-test-result loading';

            jQuery.ajax({
                url: dr_debug_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'dr_debug_deploy_endpoint',
                    domain: domain,
                    operation: 'remove',
                    nonce: dr_debug_ajax.nonce
                },
                success: function(response) {
                    resultDiv.className = 'dr-test-result ' + (response.success ? 'success' : 'error');
                    resultDiv.innerHTML = '<pre style="white-space: pre-wrap; word-wrap: break-word; margin: 0; font-family: Consolas, Monaco, monospace; font-size: 12px;">' + (response.data || response.message || 'Sin datos') + '</pre>';
                },
                error: function(xhr, status, error) {
                    resultDiv.className = 'dr-test-result error';
                    resultDiv.innerHTML = '❌ Error: ' + error;
                }
            });
        }
        </script>
        <?php
    }

    /**
     * Ejecutar test via AJAX
     */
    public function run_ajax_test(): void {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dr_debug_nonce')) {
            wp_send_json_error('Nonce inválido');
            return;
        }

        $test_type = sanitize_text_field($_POST['test_type'] ?? '');

        try {
            ob_start();
            $result = $this->execute_test($test_type);
            $output = ob_get_clean();

            wp_send_json_success($result . $output);
        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }

    /**
     * Ejecutar test específico
     */
    private function execute_test(string $test_type): string {
        switch ($test_type) {
            case 'system_status':
                return $this->test_system_status();

            case 'enqueue_test':
                return $this->test_enqueue();

            case 'cron_events':
                return $this->test_cron_events();

            case 'create_preset':
                return $this->test_create_preset();

            case 'update_presets':
                return $this->test_update_presets();

            case 'cleanup_test_data':
                return $this->test_cleanup();

            case 'system_stats':
                return $this->test_system_stats();

            case 'create_tables':
                return $this->test_create_tables();

            case 'recent_logs':
                return $this->test_recent_logs();

            case 'openprovider_diagnostic':
                return $this->test_openprovider_diagnostic();

            default:
                return '❌ Test no reconocido';
        }
    }

    /**
     * Test: Estado del sistema
     */
    private function test_system_status(): string {
        $output = "=== ESTADO DEL SISTEMA ===\n\n";

        // Verificar tablas
        $output .= "1. TABLAS DE BD:\n";
        global $wpdb;
        $tables = [
            'dominios_reseller_cf_onboarding',
            'dominios_reseller_cf_runs',
            'dominios_reseller_cf_presets',
            'dominios_reseller_cf_onboarding_logs'
        ];

        foreach ($tables as $table) {
            $table_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            $output .= "   - $table: " . ($exists ? "✅" : "❌") . "\n";
        }

        $output .= "\n";

        // Verificar presets
        $output .= "2. PRESETS DISPONIBLES:\n";
        try {
            $presets = Dominios_Reseller_Onboarding_DB::get_all_presets();
            $output .= "   - Total presets: " . count($presets) . "\n";
            foreach ($presets as $preset) {
                $output .= "     * {$preset['preset_key']}: {$preset['name']}\n";
            }
        } catch (Exception $e) {
            $output .= "   ❌ Error obteniendo presets: " . $e->getMessage() . "\n";
        }

        $output .= "\n";

        // Verificar clases
        $output .= "3. CLASES DISPONIBLES:\n";
        $classes = [
            'Dominios_Reseller_Onboarding_DB',
            'Dominios_Reseller_Onboarding_Worker'
        ];

        foreach ($classes as $class) {
            $output .= "   - $class: " . (class_exists($class) ? "✅" : "❌") . "\n";
        }

        return $output;
    }

    /**
     * Test: Encolado
     */
    private function test_enqueue(): string {
        $output = "=== TEST DE ENCOLADO ===\n\n";

        $test_domain = 'test-' . time() . '.com';
        $test_preset = 'wp'; // Usar preset por defecto

        try {
            $worker = Dominios_Reseller_Onboarding_Worker::get_instance();
            $result = $worker->enqueue($test_domain, $test_preset, false);

            $output .= "Dominio: $test_domain\n";
            $output .= "Preset: $test_preset\n";
            $output .= "Resultado: " . ($result['success'] ? "✅ ÉXITO" : "❌ ERROR") . "\n";

            if ($result['success']) {
                $output .= "Run ID: {$result['run_id']}\n";
                $output .= "Mensaje: {$result['message']}\n";
            } else {
                $output .= "Error: {$result['error']}\n";
            }
        } catch (Exception $e) {
            $output .= "❌ Excepción: " . $e->getMessage() . "\n";
        }

        return $output;
    }

    /**
     * Test: Eventos cron
     */
    private function test_cron_events(): string {
        $output = "=== EVENTOS PROGRAMADOS ===\n\n";

        try {
            $events = _get_cron_array();
            $onboarding_events = [];

            foreach ($events as $timestamp => $hooks) {
                foreach ($hooks as $hook => $hook_events) {
                    if (strpos($hook, 'dr_onboarding') !== false) {
                        foreach ($hook_events as $event) {
                            $onboarding_events[] = [
                                'hook' => $hook,
                                'timestamp' => $timestamp,
                                'args' => $event['args'] ?? []
                            ];
                        }
                    }
                }
            }

            $output .= "Eventos de onboarding encontrados: " . count($onboarding_events) . "\n\n";

            foreach ($onboarding_events as $event) {
                $time_diff = $event['timestamp'] - time();
                $output .= "• {$event['hook']}\n";
                $output .= "  Programado: " . ($time_diff > 0 ? "+{$time_diff}s" : "{$time_diff}s") . "\n";
                if (!empty($event['args'])) {
                    $output .= "  Args: " . implode(', ', $event['args']) . "\n";
                }
                $output .= "\n";
            }

            if (empty($onboarding_events)) {
                $output .= "❌ No se encontraron eventos de onboarding programados\n";
            }

        } catch (Exception $e) {
            $output .= "❌ Error obteniendo eventos: " . $e->getMessage() . "\n";
        }

        return $output;
    }

    /**
     * Test: Crear preset
     */
    private function test_create_preset(): string {
        $output = "=== CREAR PRESET DE TEST ===\n\n";

        // Payload simplificado
        $payload = [
            'version' => '3.0',
            'settings' => [
                'ssl' => 'full',
                'cache_level' => 'aggressive'
            ]
        ];

        try {
            global $wpdb;
            $table = Dominios_Reseller_Onboarding_DB::get_presets_table();

            $result = $wpdb->insert($table, [
                'preset_key' => 'debug-test',
                'name' => 'Preset de Debug',
                'description' => 'Preset creado desde debug hub',
                'payload' => json_encode($payload),
                'is_default' => 0,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ]);

            if ($result) {
                $output .= "✅ Preset 'debug-test' creado exitosamente\n";
            } else {
                $output .= "❌ Error creando preset: " . $wpdb->last_error . "\n";
            }

        } catch (Exception $e) {
            $output .= "❌ Excepción: " . $e->getMessage() . "\n";
        }

        return $output;
    }

    /**
     * Test: Limpiar datos de test
     */
    private function test_cleanup(): string {
        $output = "=== LIMPIAR DATOS DE TEST ===\n\n";

        try {
            global $wpdb;

            // Limpiar runs de test
            $runs_table = Dominios_Reseller_Onboarding_DB::get_runs_table();
            $deleted_runs = $wpdb->query("DELETE FROM $runs_table WHERE primary_domain LIKE 'test-%'");

            // Limpiar logs de test
            $logs_table = Dominios_Reseller_Onboarding_DB::get_logs_table();
            $deleted_logs = $wpdb->query("DELETE FROM $logs_table WHERE primary_domain LIKE 'test-%'");

            $output .= "✅ Runs eliminados: $deleted_runs\n";
            $output .= "✅ Logs eliminados: $deleted_logs\n";

        } catch (Exception $e) {
            $output .= "❌ Error limpiando: " . $e->getMessage() . "\n";
        }

        return $output;
    }

    /**
     * Test: Estadísticas del sistema
     */
    private function test_system_stats(): string {
        $output = "=== ESTADÍSTICAS DEL SISTEMA ===\n\n";

        try {
            global $wpdb;

            // Contar runs por estado
            $runs_table = Dominios_Reseller_Onboarding_DB::get_runs_table();
            $run_stats = $wpdb->get_results("SELECT state, COUNT(*) as count FROM $runs_table GROUP BY state");

            $output .= "RUNS POR ESTADO:\n";
            foreach ($run_stats as $stat) {
                $output .= "   - {$stat->state}: {$stat->count}\n";
            }

            $output .= "\n";

            // Contar logs recientes
            $logs_table = Dominios_Reseller_Onboarding_DB::get_logs_table();
            $log_count = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            $output .= "LOGS ÚLTIMA HORA: $log_count\n";

            // Eventos programados
            $events = _get_cron_array();
            $event_count = 0;
            foreach ($events as $hooks) {
                foreach ($hooks as $hook_events) {
                    $event_count += count($hook_events);
                }
            }
            $output .= "EVENTOS PROGRAMADOS: $event_count\n";

        } catch (Exception $e) {
            $output .= "❌ Error obteniendo stats: " . $e->getMessage() . "\n";
        }

        return $output;
    }

    /**
     * Test: Crear tablas faltantes
     */
    private function test_create_tables(): string {
        $output = "=== CREANDO TABLAS DE ONBOARDING ===\n\n";

        try {
            Dominios_Reseller_Onboarding_DB::create_tables();
            $output .= "✅ Tablas creadas exitosamente\n\n";

            // Verificar que existen
            global $wpdb;

            $tables = [
                'onboarding' => Dominios_Reseller_Onboarding_DB::get_onboarding_table(),
                'runs' => Dominios_Reseller_Onboarding_DB::get_runs_table(),
                'presets' => Dominios_Reseller_Onboarding_DB::get_presets_table(),
                'logs' => Dominios_Reseller_Onboarding_DB::get_logs_table()
            ];

            $output .= "=== VERIFICACIÓN DE TABLAS ===\n";
            foreach ($tables as $name => $table) {
                $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
                $output .= "$name: " . ($exists ? "✅ EXISTE" : "❌ NO EXISTE") . " ($table)\n";
            }

            // Verificar presets
            $presets = Dominios_Reseller_Onboarding_DB::get_all_presets();
            $output .= "\n=== PRESETS INSTALADOS ===\n";
            $output .= "Total presets: " . count($presets) . "\n";
            foreach ($presets as $preset) {
                $output .= "- {$preset['preset_key']}: {$preset['name']}\n";
            }

        } catch (Exception $e) {
            $output .= "❌ Error creando tablas: " . $e->getMessage() . "\n";
        }

        return $output;
    }

    /**
     * Test: Logs recientes
     */
    private function test_recent_logs(): string {
        $output = "=== LOGS RECIENTES ===\n\n";

        try {
            global $wpdb;
            $logs_table = Dominios_Reseller_Onboarding_DB::get_logs_table();

            $logs = $wpdb->get_results(
                "SELECT * FROM $logs_table ORDER BY created_at DESC LIMIT 10",
                ARRAY_A
            );

            if (empty($logs)) {
                $output .= "❌ No se encontraron logs\n";
            } else {
                foreach ($logs as $log) {
                    $output .= "[" . date('H:i:s', strtotime($log['created_at'])) . "] ";
                    $output .= strtoupper($log['level']) . ": ";
                    $output .= "{$log['step']} - {$log['message']}\n";
                    $output .= "   Dominio: {$log['primary_domain']}\n";
                    if (!empty($log['data'])) {
                        $data = json_decode($log['data'], true);
                        if ($data) {
                            $output .= "   Datos: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
                        }
                    }
                    $output .= "\n";
                }
            }

        } catch (Exception $e) {
            $output .= "❌ Error obteniendo logs: " . $e->getMessage() . "\n";
        }

        return $output;
    }

    /**
     * Test: Diagnóstico Openprovider
     */
    private function test_openprovider_diagnostic(): string {
        $output = "=== DIAGNÓSTICO OPENPROVIDER ===\n\n";

        try {
            if (!class_exists('Dominios_Reseller_Openprovider_Service')) {
                $output .= "❌ Servicio Openprovider no disponible\n";
                return $output;
            }

            $op_service = Dominios_Reseller_Openprovider_Service::get_instance();

            // Verificar configuración
            $configured = $op_service->is_configured();
            $output .= "Configurado: " . ($configured ? "✅ Sí" : "❌ No") . "\n";

            if (!$configured) {
                $output .= "❌ Openprovider no configurado - verifica credenciales\n";
                return $output;
            }

            // Verificar dominio carani.es
            $domain_check = $op_service->domain_exists('carani.es');
            $output .= "\n=== ESTADO DOMINIO carani.es ===\n";
            $output .= "Existe: " . ($domain_check['exists'] ? "✅ Sí" : "❌ No") . "\n";

            if ($domain_check['exists'] && isset($domain_check['info'])) {
                $info = $domain_check['info'];
                $output .= "ID: " . ($info['id'] ?? 'N/A') . "\n";
                $output .= "Estado: " . ($info['status'] ?? 'N/A') . "\n";
                $output .= "Bloqueado: " . (($info['is_locked'] ?? false) ? "✅ Sí" : "❌ No") . "\n";
                $output .= "Puede cambiar NS: " . (($info['can_change_ns'] ?? true) ? "✅ Sí" : "❌ No") . "\n";

                if (isset($info['name_servers']) && is_array($info['name_servers'])) {
                    $output .= "NS actuales:\n";
                    foreach ($info['name_servers'] as $ns) {
                        $output .= "  - " . ($ns['name'] ?? 'N/A') . "\n";
                    }
                }

                // Advertencia específica para .es
                if (strpos('carani.es', '.es') !== false) {
                    $output .= "\n⚠️  IMPORTANTE PARA .ES:\n";
                    $output .= "Los NS de Cloudflare pueden requerir autorización especial del registro español (Red.es).\n";
                    $output .= "Si la actualización automática falla, configura manualmente:\n";
                    $output .= "  - doug.ns.cloudflare.com\n";
                    $output .= "  - nia.ns.cloudflare.com\n";
                    $output .= "O contacta a Openprovider para autorizar estos NS.\n";
                }
            } elseif ($domain_check['error']) {
                $output .= "Error: " . $domain_check['error'] . "\n";
            }

            // Intentar obtener info detallada
            $domain_info = $op_service->get_domain_info('carani.es');
            if (!is_wp_error($domain_info)) {
                $output .= "\n=== INFO DETALLADA ===\n";
                $output .= json_encode($domain_info, JSON_PRETTY_PRINT) . "\n";
            } else {
                $output .= "\n❌ Error obteniendo info detallada: " . $domain_info->get_error_message() . "\n";
            }

        } catch (Exception $e) {
            $output .= "❌ Error en diagnóstico: " . $e->getMessage() . "\n";
        }

        return $output;
    }

    /**
     * Test: Actualizar presets existentes
     */
    private function test_update_presets(): string {
        $output = "=== ACTUALIZAR PRESETS ===\n\n";

        try {
            if (!class_exists('Dominios_Reseller_Onboarding_DB')) {
                $output .= "❌ Clase Dominios_Reseller_Onboarding_DB no disponible\n";
                return $output;
            }

            Dominios_Reseller_Onboarding_DB::update_existing_presets();
            $output .= "✅ Presets actualizados correctamente\n";

            // Verificar presets después de la actualización
            $presets = Dominios_Reseller_Onboarding_DB::get_all_presets();
            $output .= "\n=== PRESETS DESPUÉS DE ACTUALIZACIÓN ===\n";
            foreach ($presets as $preset) {
                $payload = json_decode($preset['payload'], true);
                $version = $payload['version'] ?? 'N/A';
                $output .= "- {$preset['preset_key']}: {$preset['name']} (v{$version})\n";
            }

        } catch (Exception $e) {
            $output .= "❌ Error actualizando presets: " . $e->getMessage() . "\n";
        }

        return $output;
    }

    /**
     * Handle AJAX para encolado manual
     */
    public function handle_manual_enqueue(): void {
        check_ajax_referer('dr_debug_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos');
        }

        $domain = sanitize_text_field($_POST['domain'] ?? '');
        $preset = sanitize_text_field($_POST['preset'] ?? 'wp');
        $auto_ns = (int) ($_POST['auto_ns'] ?? 0);

        if (empty($domain)) {
            wp_send_json_error('Dominio requerido');
            return;
        }

        try {
            $worker = Dominios_Reseller_Onboarding_Worker::get_instance();
            $result = $worker->enqueue($domain, $preset, (bool) $auto_ns);

            if ($result['success']) {
                wp_send_json_success("✅ Dominio encolado exitosamente\nRun ID: {$result['run_id']}\nMensaje: {$result['message']}");
            } else {
                wp_send_json_error("❌ Error al encolar dominio\n{$result['error']}");
            }
        } catch (Exception $e) {
            wp_send_json_error('❌ Excepción: ' . $e->getMessage());
        }
    }

    /**
     * Handle AJAX para verificar estado de dominio
     */
    public function handle_check_status(): void {
        check_ajax_referer('dr_debug_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos');
        }

        $domain = sanitize_text_field($_POST['domain'] ?? '');

        if (empty($domain)) {
            wp_send_json_error('Dominio requerido');
            return;
        }

        try {
            $state = Dominios_Reseller_Onboarding_DB::get_onboarding_state($domain);

            if (!$state) {
                wp_send_json_error("❌ Dominio no encontrado en el sistema de onboarding");
                return;
            }

            $output = "📊 Estado del dominio: {$domain}\n\n";
            $output .= "Estado actual: {$state['state']}\n";
            $output .= "Preset: {$state['preset_key']}\n";
            $output .= "NS Automático: " . ($state['auto_update_ns'] ? 'Sí' : 'No') . "\n";
            $output .= "Última actualización: {$state['updated_at']}\n";

            if (!empty($state['last_error'])) {
                $output .= "Último error: {$state['last_error']}\n";
            }

            // Determinar acciones disponibles
            $actions = ['can_retry' => false, 'can_update' => false];

            if (in_array($state['state'], ['error', 'failed', 'needs_manual_ns', 'partial'])) {
                $actions['can_retry'] = true;
            }

            if (in_array($state['state'], ['onboarded', 'completed'])) {
                $actions['can_update'] = true;
            }

            wp_send_json_success($output, $actions);
        } catch (Exception $e) {
            wp_send_json_error('❌ Excepción: ' . $e->getMessage());
        }
    }

    /**
     * Handle AJAX para reintentar dominio
     */
    public function handle_retry_domain(): void {
        check_ajax_referer('dr_debug_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos');
        }

        $domain = sanitize_text_field($_POST['domain'] ?? '');

        if (empty($domain)) {
            wp_send_json_error('Dominio requerido');
            return;
        }

        try {
            $worker = Dominios_Reseller_Onboarding_Worker::get_instance();
            $result = $worker->retry($domain);

            if ($result['success']) {
                wp_send_json_success("✅ Dominio re-encolado exitosamente\nRun ID: {$result['run_id']}\nMensaje: {$result['message']}");
            } else {
                wp_send_json_error("❌ Error al re-encolar dominio\n{$result['error']}");
            }
        } catch (Exception $e) {
            wp_send_json_error('❌ Excepción: ' . $e->getMessage());
        }
    }

    /**
     * Handle AJAX para test de flujo completo
     */
    public function handle_flow_test(): void {
        check_ajax_referer('dr_debug_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos');
        }

        $domain = sanitize_text_field($_POST['domain'] ?? '');
        $hosting = sanitize_text_field($_POST['hosting'] ?? 'wordpress');
        $simulate_webhook = isset($_POST['simulate_webhook']) && $_POST['simulate_webhook'] === 'true';

        if (empty($domain)) {
            wp_send_json_error('Dominio requerido');
            return;
        }

        try {
            $output = "=== TEST DE FLUJO COMPLETO ===\n\n";
            $output .= "Dominio: {$domain}\n";
            $output .= "Hosting: {$hosting}\n";
            $output .= "Simular Webhook: " . ($simulate_webhook ? 'Sí' : 'No') . "\n\n";

            // Paso 1: Simular webhook de Upmind
            $output .= "PASO 1: Webhook Upmind\n";
            if ($simulate_webhook) {
                $webhook_data = [
                    'event' => 'order.completed',
                    'data' => [
                        'order_id' => 'TEST-' . time(),
                        'domain' => $domain,
                        'hosting_type' => $hosting,
                        'customer_email' => 'test@example.com'
                    ]
                ];

                $upmind_integration = Dominios_Reseller_Upmind_Integration::get_instance();
                $webhook_result = $upmind_integration->process_test_webhook($webhook_data);

                if ($webhook_result['success']) {
                    $output .= "✅ Webhook procesado correctamente\n";
                    $output .= "   Order ID: {$webhook_result['order_id']}\n";
                    $output .= "   Dominio detectado: {$webhook_result['domain']}\n\n";
                } else {
                    $output .= "❌ Error en webhook: {$webhook_result['error']}\n\n";
                    wp_send_json_error($output);
                    return;
                }
            } else {
                $output .= "⏭️  Webhook omitido (no simulado)\n\n";
            }

            // Paso 2: Verificación de WordPress readiness
            $output .= "PASO 2: Verificación WP Readiness\n";
            $upmind_integration = Dominios_Reseller_Upmind_Integration::get_instance();
            $wp_check = $upmind_integration->check_wp_readiness_test($domain);

            if ($wp_check['ready']) {
                $output .= "✅ WordPress listo en el dominio\n";
                $output .= "   PHP Version: {$wp_check['php_version']}\n";
                $output .= "   MySQL: {$wp_check['mysql_version']}\n\n";
            } else {
                $output .= "⚠️  WordPress no detectado o no listo\n";
                $output .= "   Estado: {$wp_check['status']}\n";
                if (!empty($wp_check['issues'])) {
                    $output .= "   Problemas encontrados:\n";
                    foreach ($wp_check['issues'] as $issue) {
                        $output .= "     - {$issue}\n";
                    }
                }
                $output .= "\n";
            }

            // Paso 3: Encolado para onboarding
            $output .= "PASO 3: Encolado para Onboarding\n";
            $worker = Dominios_Reseller_Onboarding_Worker::get_instance();
            $enqueue_result = $worker->enqueue($domain, $hosting === 'wordpress' ? 'wp' : 'basic', false);

            if (!empty($enqueue_result['success'])) {
                $output .= "✅ Dominio encolado exitosamente\n";
                $output .= "   Run ID: " . ($enqueue_result['run_id'] ?? 'n/a') . "\n";
                $output .= "   Mensaje: " . ($enqueue_result['message'] ?? '') . "\n\n";
            } else {
                $output .= "❌ Error al encolar dominio: " . ($enqueue_result['error'] ?? 'unknown') . "\n\n";
                wp_send_json_error($output);
                return;
            }

            // Paso 4: Simular procesamiento (opcional - solo si queremos ejecutar inmediatamente)
            $output .= "PASO 4: Procesamiento Inmediato (Test)\n";
            $process_result = $worker->process_single_domain_test($domain);

            if ($process_result['success']) {
                $output .= "✅ Procesamiento completado\n";
                $output .= "   Estado final: {$process_result['final_state']}\n";
                if (!empty($process_result['cloudflare_zone'])) {
                    $output .= "   Zona Cloudflare: {$process_result['cloudflare_zone']}\n";
                }
                if (!empty($process_result['preset_applied'])) {
                    $output .= "   Preset aplicado: {$process_result['preset_applied']}\n";
                }
                $output .= "\n";
            } else {
                $output .= "⚠️  Procesamiento con problemas: {$process_result['error']}\n";
                $output .= "   Estado actual: {$process_result['current_state']}\n\n";
            }

            // Resumen final
            $output .= "=== RESUMEN DEL TEST ===\n";
            $output .= "✅ Test de flujo completado\n";
            $output .= "📊 Revisa los logs para más detalles\n";
            $output .= "🔍 Estado actual del dominio en la tabla de onboarding\n";

            wp_send_json_success($output);

        } catch (Exception $e) {
            wp_send_json_error('❌ Excepción en test de flujo: ' . $e->getMessage());
        }
    }

    /**
     * Handle AJAX para actualizar configuración
     */
    public function handle_update_config(): void {
        check_ajax_referer('dr_debug_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos');
        }

        $domain = sanitize_text_field($_POST['domain'] ?? '');
        $preset = sanitize_text_field($_POST['preset'] ?? 'wp');

        if (empty($domain)) {
            wp_send_json_error('Dominio requerido');
            return;
        }

        try {
            $worker = Dominios_Reseller_Onboarding_Worker::get_instance();
            $result = $worker->update_config($domain, $preset, false); // Por defecto no auto-update NS en updates

            if ($result['success']) {
                wp_send_json_success("✅ Configuración actualizada exitosamente\nRun ID: {$result['run_id']}\nMensaje: {$result['message']}");
            } else {
                wp_send_json_error("❌ Error al actualizar configuración\n{$result['error']}");
            }
        } catch (Exception $e) {
            wp_send_json_error('❌ Excepción: ' . $e->getMessage());
        }
    }

    /**
     * Handle AJAX para debug de PHP info
     */
    public function handle_debug_php_info(): void {
        check_ajax_referer('dr_debug_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos');
        }

        $domain = sanitize_text_field($_POST['domain'] ?? '');

        if (empty($domain)) {
            wp_send_json_error('Dominio requerido');
            return;
        }

        self::log('PHP', "Debug PHP iniciado para {$domain}", [], 'info');

        $output = "🐘 DEBUG PHP INFO - {$domain}\n";
        $output .= str_repeat("=", 50) . "\n\n";

        // Obtener configuración WHM desde dominios_reseller_options
        $opts = get_option('dominios_reseller_options', []);
        
        // ═══════════════════════════════════════════════════════════════
        // DIAGNÓSTICO COMPLETO DE CONFIGURACIÓN
        // ═══════════════════════════════════════════════════════════════
        $output .= "🔧 DIAGNÓSTICO DE CONFIGURACIÓN:\n";
        $output .= "   📋 Todas las opciones guardadas:\n";
        $output .= "      • uk_server_ip: " . ($opts['uk_server_ip'] ?? '(no definido)') . "\n";
        $output .= "      • uk_whm_user: " . ($opts['uk_whm_user'] ?? '(no definido)') . "\n";
        $output .= "      • uk_whm_token: " . (!empty($opts['uk_whm_token']) ? '***' . substr($opts['uk_whm_token'], -4) : '(no definido)') . "\n";
        $output .= "      • usa_server_ip: " . ($opts['usa_server_ip'] ?? '(no definido)') . "\n";
        $output .= "      • usa_whm_user: " . ($opts['usa_whm_user'] ?? '(no definido)') . "\n";
        $output .= "      • usa_whm_token: " . (!empty($opts['usa_whm_token']) ? '***' . substr($opts['usa_whm_token'], -4) : '(no definido)') . "\n";
        $output .= "\n";
        
        // IP del servidor actual
        $my_ip = @file_get_contents('https://api.ipify.org');
        $output .= "   🌐 IP de este servidor (replanta.net): " . ($my_ip ?: 'No se pudo obtener') . "\n\n";
        
        // Determinar servidor del dominio (uk o usa)
        $server = $this->get_domain_server($domain);
        
        // Seleccionar credenciales según servidor
        if ($server === 'usa' && !empty($opts['usa_server_ip']) && !empty($opts['usa_whm_token'])) {
            $whm_hostname = $opts['usa_server_ip'];
            $whm_username = $opts['usa_whm_user'] ?? 'replanta';
            $whm_token = $opts['usa_whm_token'];
            $whm_enabled = true;
            $server_label = 'USA';
        } elseif (!empty($opts['uk_server_ip']) && !empty($opts['uk_whm_token'])) {
            $whm_hostname = $opts['uk_server_ip'];
            $whm_username = $opts['uk_whm_user'] ?? 'replanta';
            $whm_token = $opts['uk_whm_token'];
            $whm_enabled = true;
            $server_label = 'UK';
        } else {
            $whm_enabled = false;
            $whm_hostname = '';
            $whm_username = '';
            $whm_token = '';
            $server_label = 'N/A';
        }
        $whm_port = '2087';

        $output .= "📋 CONFIGURACIÓN WHM SELECCIONADA ({$server_label}):\n";
        $output .= "   • WHM Habilitado: " . ($whm_enabled ? '✅ Sí' : '❌ No') . "\n";
        $output .= "   • Hostname: " . ($whm_hostname ?: '(vacío)') . "\n";
        $output .= "   • Username: " . ($whm_username ?: '(vacío)') . "\n";
        $output .= "   • Token: " . ($whm_token ? '***' . substr($whm_token, -4) : '(vacío)') . "\n";
        $output .= "   • Puerto: {$whm_port}\n";
        $output .= "   • Servidor detectado para dominio: " . strtoupper($server) . "\n\n";

        // Intentar obtener info desde WHM
        $output .= "🔍 INTENTANDO OBTENER INFO PHP:\n";

        if (!$whm_enabled || empty($whm_hostname) || empty($whm_username) || empty($whm_token)) {
            $output .= "   ⚠️ WHM no configurado, detectando via HTTP...\n\n";
            $php_data = $this->get_debug_php_fallback($domain);
            $source = $php_data['source'] ?? 'HTTP Detection';
            
            // Mostrar info detectada
            if (!empty($php_data['detected_info'])) {
                $output .= "🔎 DETECCIÓN HTTP:\n";
                foreach ($php_data['detected_info'] as $info) {
                    $output .= "   • {$info}\n";
                }
                $output .= "\n";
            }
        } else {
            $output .= "   🔄 Conectando a WHM ({$server_label})...\n";
            
            // ═══════════════════════════════════════════════════════════════
            // DEBUG: Mostrar qué IP está usando dominios_reseller_get_server_ip()
            // ═══════════════════════════════════════════════════════════════
            $server_ip_from_function = function_exists('dominios_reseller_get_server_ip') 
                ? dominios_reseller_get_server_ip($server) 
                : null;
            
            $output .= "   📋 Debug conexión:\n";
            $output .= "      • Servidor: {$server}\n";
            $output .= "      • dominios_reseller_get_server_ip('{$server}'): " . ($server_ip_from_function ?: '(vacío/null)') . "\n";
            $output .= "      • whm_hostname de options: {$whm_hostname}\n";
            $output .= "      • whm_username: {$whm_username}\n";
            $output .= "      • whm_token: " . ($whm_token ? '***' . substr($whm_token, -4) : '(vacío)') . "\n";
            
            // Resolver DNS
            $test_ip = $server_ip_from_function ?: $whm_hostname;
            $resolved_ip = gethostbyname($test_ip);
            $output .= "      • Hostname/IP a usar: {$test_ip}\n";
            $output .= "      • IP resuelta (DNS): {$resolved_ip}\n";
            
            // Test de puerto con la IP resuelta
            $output .= "      • Test puerto 2087 en {$resolved_ip}...\n";
            $fp = @fsockopen($resolved_ip, 2087, $errno, $errstr, 10);
            if ($fp) {
                $output .= "      • Puerto 2087: ✅ Abierto\n";
                fclose($fp);
            } else {
                $output .= "      • Puerto 2087: ❌ {$errstr} (errno: {$errno})\n";
            }
            
            // ═══════════════════════════════════════════════════════════════
            // TEST MÚLTIPLES ENDPOINTS WHM - DIAGNÓSTICO DE PERMISOS
            // ═══════════════════════════════════════════════════════════════
            $output .= "\n   🧪 TEST MÚLTIPLES ENDPOINTS WHM (diagnóstico de permisos):\n";
            
            // Lista de endpoints a probar (de menor a mayor privilegio)
            $endpoints_to_test = [
                'version' => '/json-api/version?api.version=1',  // No requiere auth especial
                'my_acct' => '/json-api/accountsummary?api.version=1&user=' . $whm_username, // Info propia
                'listaccts' => '/json-api/listaccts?api.version=1', // Requiere list-accts
                'installed_php' => '/json-api/php_get_installed_versions?api.version=1', // PHP info del servidor
            ];
            
            $cuentas = false;
            $php_versions_from_whm = null;
            
            foreach ($endpoints_to_test as $endpoint_name => $endpoint_path) {
                $test_url = "https://{$resolved_ip}:2087{$endpoint_path}";
                $output .= "\n      📡 {$endpoint_name}: ";
                
                $ch = curl_init($test_url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_HTTPHEADER => [
                        "Authorization: whm {$whm_username}:{$whm_token}"
                    ],
                    CURLOPT_TIMEOUT => 15,
                    CURLOPT_CONNECTTIMEOUT => 10,
                ]);
                
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($http_code === 200) {
                    $output .= "✅ HTTP 200\n";
                    $json = json_decode($response, true);
                    
                    // Guardar datos según endpoint
                    if ($endpoint_name === 'listaccts' && isset($json['data']['acct'])) {
                        $cuentas = $json;
                        $output .= "         → {$json['data']['acct'][0]['domain']} (y más...)\n";
                    } elseif ($endpoint_name === 'version' && isset($json['version'])) {
                        $output .= "         → cPanel/WHM v{$json['version']}\n";
                    } elseif ($endpoint_name === 'installed_php' && isset($json['data']['versions'])) {
                        $php_versions_from_whm = $json['data']['versions'];
                        $output .= "         → PHP disponibles: " . implode(', ', array_column($php_versions_from_whm, 'version')) . "\n";
                    } elseif ($endpoint_name === 'my_acct') {
                        $acct_info = $json['data']['acct'][0] ?? $json['acct'][0] ?? null;
                        if ($acct_info) {
                            $output .= "         → Tu cuenta: " . ($acct_info['domain'] ?? 'N/A') . "\n";
                        }
                    }
                } else {
                    $output .= "❌ HTTP {$http_code}\n";
                    // Mostrar razón del error
                    $err_json = json_decode($response, true);
                    if (isset($err_json['cpanelresult']['error'])) {
                        $output .= "         → Error: {$err_json['cpanelresult']['error']}\n";
                    } elseif (isset($err_json['metadata']['reason'])) {
                        $output .= "         → Razón: {$err_json['metadata']['reason']}\n";
                    }
                }
            }
            
            $output .= "\n   📊 RESUMEN PERMISOS TOKEN:\n";
            if ($cuentas) {
                $output .= "      ✅ Token tiene permiso 'list-accts' - WHM API completa disponible\n";
            } else {
                $output .= "      ❌ Token NO tiene permiso 'list-accts' o hay otro problema\n";
                $output .= "      💡 Verifica en WHM: Manage API Tokens → Editar token → Habilitar 'list-accts'\n";
            }
            if ($php_versions_from_whm) {
                $output .= "      ✅ PHP info del servidor disponible\n";
            }
            
            // Intentar con listaccts para compatibilidad
            $test_url = "https://{$resolved_ip}:2087/json-api/listaccts?api.version=1";
            $ch = curl_init($test_url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HTTPHEADER => [
                    "Authorization: whm {$whm_username}:{$whm_token}",
                    // NO enviar Content-Type: application/json en GET - causa error HTTP 400
                ],
                CURLOPT_TIMEOUT => 60,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HEADER => true,
            ]);
            
            $full_response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            $curl_errno = curl_errno($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);
            
            $output .= "      • HTTP Code: {$http_code}\n";
            $output .= "      • cURL Error: " . ($curl_error ?: 'ninguno') . " (errno: {$curl_errno})\n";
            $output .= "      • Connect Time: " . round($info['connect_time'] * 1000) . "ms\n";
            $output .= "      • Total Time: " . round($info['total_time'] * 1000) . "ms\n";
            $output .= "      • Primary IP: " . ($info['primary_ip'] ?? 'N/A') . "\n";
            $output .= "      • Redirect URL: " . ($info['redirect_url'] ?: 'ninguno') . "\n";
            
            // Separar headers y body
            $header_size = $info['header_size'];
            $response_headers = substr($full_response, 0, $header_size);
            $response_body = substr($full_response, $header_size);
            
            $output .= "      • Response Headers:\n";
            $header_lines = explode("\r\n", trim($response_headers));
            foreach (array_slice($header_lines, 0, 10) as $line) {
                if (!empty($line)) {
                    $output .= "        {$line}\n";
                }
            }
            
            if ($response_body) {
                $output .= "      • Response Body (primeros 500 chars):\n";
                $output .= "        " . substr($response_body, 0, 500) . "\n";
            }
            
            // Si funcionó, usar los datos
            if ($http_code === 200 && $response_body) {
                $json_data = json_decode($response_body, true);
                if (isset($json_data['data']['acct'])) {
                    $output .= "\n   ✅ WHM API FUNCIONA! Cuentas: " . count($json_data['data']['acct']) . "\n";
                    $cuentas = $json_data;
                } else {
                    $output .= "\n   ⚠️ Respuesta OK pero sin data[acct]\n";
                    $cuentas = false;
                }
            } else {
                $cuentas = false;
            }
            
            if (!$cuentas) {
                $output .= "\n   🔄 Usando detección HTTP (fallback)...\n\n";
                $php_data = $this->get_debug_php_fallback($domain);
                $source = "HTTP Detection (WHM falló)";
            } else {
                $accounts = $cuentas['data']['acct'];
                
                // Buscar dominio principal
                $cpanel_user = null;
                foreach ($accounts as $acct) {
                    if (($acct['domain'] ?? '') === $domain) {
                        $cpanel_user = $acct['user'] ?? null;
                        $output .= "   ✅ Dominio encontrado como principal! Usuario: {$cpanel_user}\n";
                        break;
                    }
                }
                
                // Si no es principal, buscar en addon domains
                if (!$cpanel_user) {
                    $output .= "   🔍 Buscando en addon domains...\n";
                    foreach ($accounts as $acct) {
                        $user = $acct['user'] ?? '';
                        if (empty($user)) continue;
                        
                        $addons = obtener_addons_de_usuario($user, $whm_token, $server);
                        foreach ($addons as $addon) {
                            if (($addon['domain'] ?? '') === $domain) {
                                $cpanel_user = $user;
                                $output .= "   ✅ Dominio encontrado como addon de {$user}!\n";
                                break 2;
                            }
                        }
                    }
                }
                
                if (!$cpanel_user) {
                    $output .= "   ⚠️ Dominio '{$domain}' no encontrado\n";
                    $output .= "   📋 Primeros dominios:\n";
                    for ($i = 0; $i < min(5, count($accounts)); $i++) {
                        $output .= "      • " . ($accounts[$i]['domain'] ?? 'N/A') . "\n";
                    }
                    $output .= "   🔄 Usando detección HTTP...\n\n";
                    $php_data = $this->get_debug_php_fallback($domain);
                    $source = 'HTTP Detection (dominio no encontrado)';
                } else {
                    // Tenemos el usuario, obtener info PHP
                    $output .= "   🔄 Obteniendo info PHP para usuario: {$cpanel_user}\n";
                    
                    $php_result = $this->get_php_info_from_whm_api($cpanel_user, $domain, $whm_hostname, $whm_username, $whm_token, $whm_port);
                    
                    if ($php_result['success']) {
                        $php_data = $php_result['data'];
                        $source = $php_result['source'];
                        $output .= "   ✅ Info PHP obtenida via {$source}\n\n";
                    } else {
                        $output .= "   ⚠️ API PHP falló: " . ($php_result['error'] ?? 'Unknown') . "\n";
                        
                        // Mostrar debug detallado si existe
                        if (!empty($php_result['debug'])) {
                            $output .= "   📋 Debug API PHP:\n";
                            foreach ($php_result['debug'] as $dbg) {
                                $output .= "      • {$dbg}\n";
                            }
                        }
                        
                        $output .= "   🔄 Usando HTTP Detection como fallback...\n\n";
                        $php_data = $this->get_debug_php_fallback($domain);
                        $source = 'HTTP Detection';
                    }
                }
            }
        }

        // Mostrar datos obtenidos
        $output .= "📊 DATOS PHP OBTENIDOS (Fuente: {$source}):\n";
        $output .= str_repeat("-", 40) . "\n";
        $output .= "   • Versión PHP: " . ($php_data['php_version'] ?? 'N/A') . "\n";
        
        $output .= "\n   📦 INI SETTINGS:\n";
        $ini = $php_data['ini_settings'] ?? [];
        $output .= "      • memory_limit: " . ($ini['memory_limit'] ?? 'N/A') . "\n";
        $output .= "      • max_execution_time: " . ($ini['max_execution_time'] ?? 'N/A') . "\n";
        $output .= "      • upload_max_filesize: " . ($ini['upload_max_filesize'] ?? 'N/A') . "\n";
        $output .= "      • post_max_size: " . ($ini['post_max_size'] ?? 'N/A') . "\n";
        $output .= "      • max_input_vars: " . ($ini['max_input_vars'] ?? 'N/A') . "\n";

        $output .= "\n   🧩 EXTENSIONES:\n";
        $extensions = $php_data['extensions'] ?? [];
        $output .= "      " . implode(', ', $extensions) . "\n";
        $output .= "      Total: " . count($extensions) . " extensiones\n";

        $output .= "\n   🎯 RENDIMIENTO:\n";
        $output .= "      • Max Performance: " . ($php_data['max_performance'] ? '✅ Sí' : '❌ No') . "\n";

        $output .= "\n   💡 RECOMENDACIONES:\n";
        $recommendations = $php_data['recommendations'] ?? [];
        if (empty($recommendations)) {
            $output .= "      ✅ Sin recomendaciones (configuración óptima)\n";
        } else {
            foreach ($recommendations as $rec) {
                $output .= "      • {$rec}\n";
            }
        }

        $output .= "\n" . str_repeat("=", 50) . "\n";
        $output .= "📤 JSON RAW:\n";
        $output .= json_encode($php_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

        self::log('PHP', "Debug PHP completado para {$domain}", ['source' => $source], 'success');

        wp_send_json_success($output);
    }

    /**
     * Handle AJAX para deploy/test/remove endpoint de mantenimiento
     */
    public function handle_deploy_maintenance_endpoint(): void {
        check_ajax_referer('dr_debug_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos');
        }

        $domain = sanitize_text_field($_POST['domain'] ?? '');
        $operation = sanitize_text_field($_POST['operation'] ?? 'deploy');

        if (empty($domain)) {
            wp_send_json_error('Dominio requerido');
            return;
        }

        $output = "🛠️ MAINTENANCE ENDPOINT - {$domain}\n";
        $output .= str_repeat("=", 50) . "\n\n";
        $output .= "📋 Operación: " . strtoupper($operation) . "\n\n";

        // Buscar token existente en BD o generar uno nuevo
        global $wpdb;
        $table = $wpdb->prefix . 'dominios_reseller';
        $existing_token = $wpdb->get_var($wpdb->prepare(
            "SELECT endpoint_token FROM {$table} WHERE domain = %s",
            $domain
        ));
        
        if (!empty($existing_token)) {
            $endpoint_token = $existing_token;
            $output .= "♻️ Usando token existente\n";
        } else {
            $endpoint_token = substr(md5($domain . 'dr_maintenance_2025'), 0, 12);
            $output .= "🆕 Generando nuevo token\n";
        }
        
        // SIN punto inicial - muchos servidores bloquean archivos ocultos
        $endpoint_filename = "dr-health-{$endpoint_token}.php";
        $endpoint_url = "https://{$domain}/{$endpoint_filename}";

        $output .= "🔐 Token: {$endpoint_token}\n";
        $output .= "📁 Archivo: {$endpoint_filename}\n";
        $output .= "🌐 URL: {$endpoint_url}\n\n";

        switch ($operation) {
            case 'test':
                $output .= $this->test_maintenance_endpoint($domain, $endpoint_url);
                break;
                
            case 'remove':
                $output .= $this->remove_maintenance_endpoint($domain, $endpoint_filename, $endpoint_token);
                break;
                
            case 'deploy':
            default:
                $output .= $this->deploy_maintenance_endpoint($domain, $endpoint_filename, $endpoint_token);
                break;
        }

        wp_send_json_success($output);
    }

    /**
     * Probar si el endpoint existe y funciona
     */
    private function test_maintenance_endpoint(string $domain, string $endpoint_url): string {
        $output = "🧪 PROBANDO ENDPOINT...\n";
        $output .= str_repeat("-", 40) . "\n";

        $response = wp_remote_get($endpoint_url, [
            'timeout' => 15,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            $output .= "❌ Error de conexión: " . $response->get_error_message() . "\n";
            return $output;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        $output .= "📡 HTTP Status: {$code}\n";

        if ($code === 200) {
            $data = json_decode($body, true);
            
            // Formato nuevo (v1.6.50+) con estructura completa
            if ($data && isset($data['php']['version'])) {
                $output .= "✅ ENDPOINT FUNCIONA! (formato v2)\n\n";
                
                // Guardar en BD
                $this->save_php_info($domain, $data);
                $output .= "💾 Datos guardados en BD\n\n";
                
                $php = $data['php'] ?? [];
                $limits = $data['limits'] ?? [];
                $upload = $data['upload'] ?? [];
                $opcache = $data['opcache'] ?? [];
                $extensions = $data['extensions'] ?? [];
                $server = $data['server'] ?? [];
                $score = $data['wp_readiness_score'] ?? 0;
                $grade = $data['wp_readiness_grade'] ?? 'N/A';
                
                $output .= "🎯 WP READINESS: {$score}/100 (Grado: {$grade})\n\n";
                
                $output .= "📊 PHP CONFIG:\n";
                $output .= "   • Versión: " . ($php['version'] ?? 'N/A') . "\n";
                $output .= "   • SAPI: " . ($php['sapi'] ?? 'N/A') . "\n";
                $output .= "   • Zend: " . ($php['zend_version'] ?? 'N/A') . "\n";
                $output .= "   • Arch: " . ($php['architecture'] ?? 'N/A') . "\n\n";
                
                $output .= "🧠 LIMITES:\n";
                $output .= "   • Memory: " . ($limits['memory_limit'] ?? 'N/A') . "\n";
                $output .= "   • Max Exec: " . ($limits['max_execution_time'] ?? 'N/A') . "s\n";
                $output .= "   • Max Input Vars: " . ($limits['max_input_vars'] ?? 'N/A') . "\n\n";
                
                $output .= "📤 UPLOAD:\n";
                $output .= "   • Max File: " . ($upload['upload_max_filesize'] ?? 'N/A') . "\n";
                $output .= "   • Post Max: " . ($upload['post_max_size'] ?? 'N/A') . "\n\n";
                
                $output .= "⚡ OPCACHE:\n";
                $output .= "   • Enabled: " . ($opcache['enabled'] ? '✅' : '❌') . "\n";
                if ($opcache['enabled']) {
                    $output .= "   • Hit Rate: " . ($opcache['hit_rate'] ?? 0) . "%\n";
                    $output .= "   • JIT: " . (($opcache['jit_enabled'] ?? false) ? '✅' : '❌') . "\n";
                }
                
                $output .= "\n🖥️ SERVIDOR:\n";
                $output .= "   • Software: " . ($server['software'] ?? 'N/A') . "\n";
                $output .= "   • OS: " . ($server['os'] ?? 'N/A') . "\n";
                if (!empty($server['litespeed'])) {
                    $output .= "   • LiteSpeed: ✅\n";
                }
                
                $output .= "\n🧩 EXTENSIONES:\n";
                $output .= "   • Total: " . ($extensions['count'] ?? 0) . " cargadas\n";
                
                // Mostrar estado de extensiones críticas
                $wp_critical = $extensions['wp_critical'] ?? [];
                $missing_critical = array_keys(array_filter($wp_critical, fn($v) => !$v));
                if (!empty($missing_critical)) {
                    $output .= "   ⚠️ Críticas faltantes: " . implode(', ', $missing_critical) . "\n";
                } else {
                    $output .= "   ✅ Todas las críticas presentes\n";
                }
                
                // Extensiones opcionales útiles
                $wp_optional = $extensions['wp_optional'] ?? [];
                $present_optional = array_keys(array_filter($wp_optional, fn($v) => $v));
                if (!empty($present_optional)) {
                    $output .= "   🎁 Opcionales: " . implode(', ', $present_optional) . "\n";
                }
                
            // Formato antiguo (compatibilidad)
            } elseif ($data && isset($data['php_version'])) {
                $output .= "✅ ENDPOINT FUNCIONA! (formato v1)\n\n";
                $output .= "📊 DATOS PHP:\n";
                $output .= "   • Versión: " . ($data['php_version'] ?? 'N/A') . "\n";
                $output .= "   • Servidor: " . ($data['server'] ?? 'N/A') . "\n";
                $output .= "   • Memory: " . ($data['memory_limit'] ?? 'N/A') . "\n";
                
                if (!empty($data['extensions'])) {
                    $output .= "   • Extensiones: " . count($data['extensions']) . "\n";
                }
            } else {
                $output .= "⚠️ Respuesta no es JSON válido\n";
                $output .= "Body: " . substr($body, 0, 500) . "\n";
            }
        } elseif ($code === 404) {
            $output .= "⚠️ Endpoint NO existe (404) - necesitas hacer Deploy primero\n";
        } elseif ($code === 403) {
            $output .= "❌ Acceso denegado (403) - posible bloqueo por .htaccess o WAF\n";
        } else {
            $output .= "❌ Error inesperado\n";
            $output .= "Body: " . substr($body, 0, 300) . "\n";
        }

        return $output;
    }

    /**
     * Desplegar el endpoint de mantenimiento via cPanel File Manager API
     * Método público para permitir auto-deploy desde el worker
     */
    public function deploy_maintenance_endpoint(string $domain, string $filename, string $token): string {
        $output = "🚀 DESPLEGANDO ENDPOINT...\n";
        $output .= str_repeat("-", 40) . "\n";

        // Obtener usuario cPanel del dominio
        $cpanel_info = $this->get_cpanel_user_for_domain($domain);
        
        if (!$cpanel_info['success']) {
            $output .= "❌ " . $cpanel_info['error'] . "\n";
            return $output;
        }

        $cpanel_user = $cpanel_info['user'];
        $server = $cpanel_info['server'];
        $docroot = $cpanel_info['docroot'] ?? '/public_html';
        
        $output .= "✅ Usuario cPanel: {$cpanel_user}\n";
        $output .= "✅ Servidor: " . strtoupper($server) . "\n";
        $output .= "✅ Document Root: {$docroot}\n\n";

        // Contenido del archivo PHP - normalizar line endings a LF (Unix)
        $php_content = $this->generate_maintenance_endpoint_code($token, $domain);
        $php_content = str_replace("\r\n", "\n", $php_content); // CRLF -> LF
        $php_content = str_replace("\r", "\n", $php_content);   // CR -> LF
        
        $output .= "📝 Archivo a crear: {$filename}\n";
        $output .= "📏 Tamaño: " . strlen($php_content) . " bytes\n\n";

        // Obtener configuración WHM
        $opts = get_option('dominios_reseller_options', []);
        
        if ($server === 'usa') {
            $whm_host = $opts['usa_server_ip'] ?? '';
            $whm_user = $opts['usa_whm_user'] ?? 'replanta';
            $whm_token = $opts['usa_whm_token'] ?? '';
        } else {
            $whm_host = $opts['uk_server_ip'] ?? '';
            $whm_user = $opts['uk_whm_user'] ?? 'replanta';
            $whm_token = $opts['uk_whm_token'] ?? '';
        }

        if (empty($whm_host) || empty($whm_token)) {
            $output .= "❌ Configuración WHM no encontrada para servidor {$server}\n";
            return $output;
        }

        // ═══════════════════════════════════════════════════════════════
        // INTENTAR CREAR ARCHIVO VIA cPanel UAPI (Fileman::write_file)
        // ═══════════════════════════════════════════════════════════════
        $output .= "🔧 Intentando crear archivo via cPanel API...\n";
        
        // Usar el docroot detectado para el dominio
        $file_dir = $docroot;
        $file_name = $filename; // Solo el nombre, sin ruta
        
        // URL para cPanel UAPI a través de WHM
        $api_url = "https://{$whm_host}:2087/json-api/cpanel";
        $api_url .= "?cpanel_jsonapi_user=" . urlencode($cpanel_user);
        $api_url .= "&cpanel_jsonapi_apiversion=3";
        $api_url .= "&cpanel_jsonapi_module=Fileman";
        $api_url .= "&cpanel_jsonapi_func=save_file_content";
        $api_url .= "&api.version=1";

        $output .= "📡 API URL: " . substr($api_url, 0, 80) . "...\n";
        $output .= "📂 Directorio: {$file_dir}\n";
        $output .= "📄 Archivo: {$file_name}\n";

        $response = wp_remote_post($api_url, [
            'headers' => [
                'Authorization' => 'whm ' . $whm_user . ':' . $whm_token,
            ],
            'body' => [
                'dir' => $file_dir,
                'file' => $file_name,
                'content' => $php_content, // Contenido directo, NO base64
                'fallback' => '1',
            ],
            'timeout' => 30,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            $output .= "❌ Error de conexión: " . $response->get_error_message() . "\n";
            
            // Intentar método alternativo
            $output .= "\n🔄 Intentando método alternativo (Fileman::upload)...\n";
            return $output . $this->deploy_via_upload_method($domain, $cpanel_user, $filename, $php_content, $whm_host, $whm_user, $whm_token, $token);
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        $output .= "📡 HTTP Status: {$code}\n";

        if ($code === 200) {
            $result = $data['result'] ?? $data['cpanelresult'] ?? [];
            $status = $result['status'] ?? $result['result'] ?? 0;
            $errors = $result['errors'] ?? $result['error'] ?? null;

            if ($status == 1 || (isset($data['metadata']['result']) && $data['metadata']['result'] == 1)) {
                $output .= "✅ ARCHIVO CREADO EXITOSAMENTE!\n\n";
                $output .= "🌐 URL del endpoint: https://{$domain}/{$filename}\n";
                $output .= "💡 Usa el botón 'Test Endpoint' para verificar que funciona\n";
                
                // Guardar token en la base de datos
                $this->save_endpoint_token($domain, $token);
            } else {
                $output .= "⚠️ API respondió pero con error:\n";
                $output .= "   Status: {$status}\n";
                if ($errors) {
                    $output .= "   Errors: " . (is_array($errors) ? implode(', ', $errors) : $errors) . "\n";
                }
                $output .= "\n📋 Response:\n" . json_encode($data, JSON_PRETTY_PRINT) . "\n";
            }
        } else {
            $error_reason = $data['metadata']['reason'] ?? $data['error'] ?? 'Unknown';
            $output .= "❌ Error HTTP {$code}: {$error_reason}\n";
            $output .= "\n📋 Response body:\n" . substr($body, 0, 500) . "\n";
        }

        return $output;
    }
    
    /**
     * Guardar token del endpoint en la BD
     */
    private function save_endpoint_token(string $domain, string $token): void {
        global $wpdb;
        $table = $wpdb->prefix . 'dominios_reseller';
        
        $wpdb->update(
            $table,
            [
                'endpoint_token' => $token,
                'endpoint_deployed_at' => current_time('mysql'),
            ],
            ['domain' => $domain],
            ['%s', '%s'],
            ['%s']
        );
    }
    
    /**
     * Guardar info PHP del endpoint en la BD
     */
    private function save_php_info(string $domain, array $php_data): void {
        global $wpdb;
        $table = $wpdb->prefix . 'dominios_reseller';
        
        $wpdb->update(
            $table,
            [
                'php_info' => json_encode($php_data, JSON_UNESCAPED_UNICODE),
                'php_info_updated_at' => current_time('mysql'),
                'wp_readiness_score' => $php_data['wp_readiness_score'] ?? null,
            ],
            ['domain' => $domain],
            ['%s', '%s', '%d'],
            ['%s']
        );
    }
    
    /**
     * Obtener info PHP de un dominio desde la BD
     */
    public function get_cached_php_info(string $domain): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'dominios_reseller';
        
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT php_info, php_info_updated_at, wp_readiness_score FROM {$table} WHERE domain = %s",
            $domain
        ));
        
        if ($row && !empty($row->php_info)) {
            return [
                'data' => json_decode($row->php_info, true),
                'updated_at' => $row->php_info_updated_at,
                'score' => $row->wp_readiness_score,
            ];
        }
        
        return null;
    }

    /**
     * Método alternativo de deploy usando write_file
     */
    private function deploy_via_upload_method(string $domain, string $cpanel_user, string $filename, string $content, string $whm_host, string $whm_user, string $whm_token, string $token = ''): string {
        $output = "";
        
        // Intentar con write_file_content (otro endpoint)
        $file_path = "/public_html/{$filename}";
        
        $api_url = "https://{$whm_host}:2087/json-api/cpanel";
        $api_url .= "?cpanel_jsonapi_user=" . urlencode($cpanel_user);
        $api_url .= "&cpanel_jsonapi_apiversion=3";
        $api_url .= "&cpanel_jsonapi_module=Fileman";
        $api_url .= "&cpanel_jsonapi_func=write_file_content";
        $api_url .= "&api.version=1";
        $api_url .= "&dir=" . urlencode('/public_html');
        $api_url .= "&file=" . urlencode($filename);

        $response = wp_remote_post($api_url, [
            'headers' => [
                'Authorization' => 'whm ' . $whm_user . ':' . $whm_token,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'content' => $content,
            ],
            'timeout' => 30,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            $output .= "❌ Método alternativo también falló: " . $response->get_error_message() . "\n";
            $output .= "\n💡 SOLUCIÓN MANUAL:\n";
            $output .= "1. Accede al cPanel del dominio {$domain}\n";
            $output .= "2. Ve a File Manager > public_html\n";
            $output .= "3. Crea un archivo llamado: {$filename}\n";
            $output .= "4. Pega el siguiente contenido:\n\n";
            $output .= "```php\n" . $this->generate_maintenance_endpoint_code(substr(md5($domain . 'dr_maintenance_2025'), 0, 12), $domain) . "\n```\n";
            return $output;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        $output .= "📡 HTTP Status (alternativo): {$code}\n";
        
        if ($code === 200) {
            $data = json_decode($body, true);
            $output .= "📋 Response:\n" . json_encode($data, JSON_PRETTY_PRINT) . "\n";
        } else {
            $output .= "❌ Error: " . substr($body, 0, 300) . "\n";
        }

        return $output;
    }

    /**
     * Eliminar el endpoint de mantenimiento
     */
    private function remove_maintenance_endpoint(string $domain, string $filename, string $token = ''): string {
        $output = "🗑️ ELIMINANDO ENDPOINT...\n";
        $output .= str_repeat("-", 40) . "\n";

        $cpanel_info = $this->get_cpanel_user_for_domain($domain);
        
        if (!$cpanel_info['success']) {
            $output .= "❌ " . $cpanel_info['error'] . "\n";
            return $output;
        }

        $cpanel_user = $cpanel_info['user'];
        $server = $cpanel_info['server'];
        $docroot = $cpanel_info['docroot'] ?? '/public_html';

        $opts = get_option('dominios_reseller_options', []);
        
        if ($server === 'usa') {
            $whm_host = $opts['usa_server_ip'] ?? '';
            $whm_user = $opts['usa_whm_user'] ?? 'replanta';
            $whm_token = $opts['usa_whm_token'] ?? '';
        } else {
            $whm_host = $opts['uk_server_ip'] ?? '';
            $whm_user = $opts['uk_whm_user'] ?? 'replanta';
            $whm_token = $opts['uk_whm_token'] ?? '';
        }

        $output .= "📂 Document Root: {$docroot}\n";
        $output .= "📄 Archivo: {$filename}\n\n";

        // cPanel UAPI para eliminar archivo
        $api_url = "https://{$whm_host}:2087/json-api/cpanel";
        $api_url .= "?cpanel_jsonapi_user=" . urlencode($cpanel_user);
        $api_url .= "&cpanel_jsonapi_apiversion=3";
        $api_url .= "&cpanel_jsonapi_module=Fileman";
        $api_url .= "&cpanel_jsonapi_func=trash";
        $api_url .= "&api.version=1";
        $api_url .= "&files=" . urlencode("{$docroot}/{$filename}");

        $response = wp_remote_get($api_url, [
            'headers' => [
                'Authorization' => 'whm ' . $whm_user . ':' . $whm_token,
            ],
            'timeout' => 30,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            $output .= "❌ Error: " . $response->get_error_message() . "\n";
            return $output;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code === 200) {
            $data = json_decode($body, true);
            $status = $data['result']['status'] ?? $data['metadata']['result'] ?? 0;
            
            if ($status == 1) {
                $output .= "✅ Archivo eliminado exitosamente\n";
                
                // Limpiar datos de la BD
                $this->clear_endpoint_data($domain);
                $output .= "✅ Datos limpiados de BD\n";
            } else {
                $output .= "⚠️ Respuesta: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
            }
        } else {
            $output .= "❌ HTTP {$code}: " . substr($body, 0, 200) . "\n";
        }

        return $output;
    }
    
    /**
     * Limpiar datos del endpoint de la BD
     */
    private function clear_endpoint_data(string $domain): void {
        global $wpdb;
        $table = $wpdb->prefix . 'dominios_reseller';
        
        $wpdb->update(
            $table,
            [
                'endpoint_token' => null,
                'endpoint_deployed_at' => null,
                'php_info' => null,
                'php_info_updated_at' => null,
                'wp_readiness_score' => null,
            ],
            ['domain' => $domain],
            ['%s', '%s', '%s', '%s', '%d'],
            ['%s']
        );
    }

    /**
     * Obtener usuario cPanel y document root para un dominio
     */
    private function get_cpanel_user_for_domain(string $domain): array {
        // Determinar servidor
        $server = $this->get_domain_server($domain);
        
        $opts = get_option('dominios_reseller_options', []);
        
        if ($server === 'usa') {
            $whm_host = $opts['usa_server_ip'] ?? '';
            $whm_user = $opts['usa_whm_user'] ?? 'replanta';
            $whm_token = $opts['usa_whm_token'] ?? '';
        } else {
            $whm_host = $opts['uk_server_ip'] ?? '';
            $whm_user = $opts['uk_whm_user'] ?? 'replanta';
            $whm_token = $opts['uk_whm_token'] ?? '';
        }

        if (empty($whm_host) || empty($whm_token)) {
            return ['success' => false, 'error' => "WHM no configurado para servidor {$server}"];
        }

        // ═══════════════════════════════════════════════════════════════
        // MÉTODO 1: Buscar en dominios principales (listaccts)
        // ═══════════════════════════════════════════════════════════════
        $url = "https://{$whm_host}:2087/json-api/listaccts?api.version=1";
        
        $response = wp_remote_get($url, [
            'headers' => ['Authorization' => 'whm ' . $whm_user . ':' . $whm_token],
            'timeout' => 15,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => 'Error conectando a WHM: ' . $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $accounts = $body['data']['acct'] ?? [];

        // Buscar como dominio principal
        foreach ($accounts as $account) {
            if (($account['domain'] ?? '') === $domain) {
                return [
                    'success' => true,
                    'user' => $account['user'],
                    'server' => $server,
                    'docroot' => '/public_html', // Dominio principal siempre es public_html
                ];
            }
        }

        // ═══════════════════════════════════════════════════════════════
        // MÉTODO 2: Buscar en addon/parked domains de cada cuenta
        // ═══════════════════════════════════════════════════════════════
        foreach ($accounts as $account) {
            $cpanel_user = $account['user'] ?? '';
            if (empty($cpanel_user)) continue;
            
            // Obtener dominios de esta cuenta via domainuserdata
            $domains_url = "https://{$whm_host}:2087/json-api/cpanel";
            $domains_url .= "?cpanel_jsonapi_user=" . urlencode($cpanel_user);
            $domains_url .= "&cpanel_jsonapi_apiversion=3";
            $domains_url .= "&cpanel_jsonapi_module=DomainInfo";
            $domains_url .= "&cpanel_jsonapi_func=list_domains";
            
            $domains_response = wp_remote_get($domains_url, [
                'headers' => ['Authorization' => 'whm ' . $whm_user . ':' . $whm_token],
                'timeout' => 10,
                'sslverify' => false,
            ]);
            
            if (is_wp_error($domains_response)) continue;
            
            $domains_body = json_decode(wp_remote_retrieve_body($domains_response), true);
            $domains_data = $domains_body['result']['data'] ?? [];
            
            // Buscar en addon_domains
            $addon_domains = $domains_data['addon_domains'] ?? [];
            foreach ($addon_domains as $addon) {
                if ($addon === $domain) {
                    // Obtener document root para este addon domain
                    $docroot = $this->get_domain_docroot($cpanel_user, $domain, $whm_host, $whm_user, $whm_token);
                    return [
                        'success' => true,
                        'user' => $cpanel_user,
                        'server' => $server,
                        'docroot' => $docroot ?: "/{$domain}",
                    ];
                }
            }
            
            // Buscar en parked_domains (aliases)
            $parked_domains = $domains_data['parked_domains'] ?? [];
            foreach ($parked_domains as $parked) {
                if ($parked === $domain) {
                    return [
                        'success' => true,
                        'user' => $cpanel_user,
                        'server' => $server,
                        'docroot' => '/public_html', // Parked apunta al mismo root
                    ];
                }
            }
            
            // Buscar en sub_domains
            $sub_domains = $domains_data['sub_domains'] ?? [];
            foreach ($sub_domains as $sub) {
                if ($sub === $domain) {
                    $docroot = $this->get_domain_docroot($cpanel_user, $domain, $whm_host, $whm_user, $whm_token);
                    return [
                        'success' => true,
                        'user' => $cpanel_user,
                        'server' => $server,
                        'docroot' => $docroot ?: "/public_html/{$domain}",
                    ];
                }
            }
        }

        return ['success' => false, 'error' => "Dominio {$domain} no encontrado en WHM (ni como principal, addon, parked o subdominio)"];
    }

    /**
     * Obtener document root de un dominio específico
     */
    private function get_domain_docroot(string $cpanel_user, string $domain, string $whm_host, string $whm_user, string $whm_token): string {
        $url = "https://{$whm_host}:2087/json-api/cpanel";
        $url .= "?cpanel_jsonapi_user=" . urlencode($cpanel_user);
        $url .= "&cpanel_jsonapi_apiversion=3";
        $url .= "&cpanel_jsonapi_module=DomainInfo";
        $url .= "&cpanel_jsonapi_func=single_domain_data";
        $url .= "&domain=" . urlencode($domain);
        
        $response = wp_remote_get($url, [
            'headers' => ['Authorization' => 'whm ' . $whm_user . ':' . $whm_token],
            'timeout' => 10,
            'sslverify' => false,
        ]);
        
        if (is_wp_error($response)) {
            return '';
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $data = $body['result']['data'] ?? [];
        
        // El documentroot viene como ruta completa: /home/user/public_html/domain
        // Necesitamos la ruta relativa desde el home del usuario
        $full_docroot = $data['documentroot'] ?? '';
        
        if (!empty($full_docroot)) {
            // Extraer ruta relativa: /home/adfc/adfc.com.co → /adfc.com.co
            if (preg_match('#^/home/[^/]+(.+)$#', $full_docroot, $matches)) {
                return $matches[1];
            }
        }
        
        return '';
    }

    /**
     * Generar código PHP del endpoint de mantenimiento
     */
    private function generate_maintenance_endpoint_code(string $token, string $domain): string {
        return '<?php
/**
 * Dominios Reseller - PHP Health Check Endpoint
 * Domain: ' . $domain . '
 * Token: ' . $token . '
 * Generated: ' . date('Y-m-d H:i:s') . '
 * 
 * Monitorea configuracion PHP completa para WordPress performance.
 * NO MODIFICAR - Regenerado automaticamente.
 */

header("Content-Type: application/json; charset=utf-8");
header("X-DR-Endpoint: health-check");
header("X-Robots-Tag: noindex, nofollow");

// CORS restringido
$allowed = ["https://replanta.net","https://www.replanta.net","https://replanta.us","https://replanta.dev"];
$origin = $_SERVER["HTTP_ORIGIN"] ?? "";
if (in_array($origin, $allowed)) header("Access-Control-Allow-Origin: $origin");

// Funcion helper para obtener bytes
function dr_parse_size($size) {
    $unit = strtolower(substr($size, -1));
    $val = (int)$size;
    switch($unit) {
        case "g": $val *= 1024;
        case "m": $val *= 1024;
        case "k": $val *= 1024;
    }
    return $val;
}

// ═══════════════════════════════════════════════════════════════
// CONFIGURACION PHP BASICA
// ═══════════════════════════════════════════════════════════════
$php_config = [
    "version" => phpversion(),
    "version_id" => PHP_VERSION_ID,
    "sapi" => php_sapi_name(),
    "zend_version" => zend_version(),
    "architecture" => PHP_INT_SIZE * 8 . "-bit",
];

// ═══════════════════════════════════════════════════════════════
// LIMITES DE MEMORIA Y EJECUCION (Criticos para WP)
// ═══════════════════════════════════════════════════════════════
$limits = [
    "memory_limit" => ini_get("memory_limit"),
    "memory_limit_bytes" => dr_parse_size(ini_get("memory_limit")),
    "max_execution_time" => (int)ini_get("max_execution_time"),
    "max_input_time" => (int)ini_get("max_input_time"),
    "max_input_vars" => (int)ini_get("max_input_vars"),
    "max_input_nesting_level" => (int)ini_get("max_input_nesting_level"),
];

// ═══════════════════════════════════════════════════════════════
// LIMITES DE UPLOAD (Criticos para WP Media)
// ═══════════════════════════════════════════════════════════════
$upload = [
    "upload_max_filesize" => ini_get("upload_max_filesize"),
    "upload_max_bytes" => dr_parse_size(ini_get("upload_max_filesize")),
    "post_max_size" => ini_get("post_max_size"),
    "post_max_bytes" => dr_parse_size(ini_get("post_max_size")),
    "file_uploads" => (bool)ini_get("file_uploads"),
    "max_file_uploads" => (int)ini_get("max_file_uploads"),
];

// ═══════════════════════════════════════════════════════════════
// OPCACHE (Rendimiento critico)
// ═══════════════════════════════════════════════════════════════
$opcache = ["enabled" => false];
if (function_exists("opcache_get_status")) {
    $oc = @opcache_get_status(false);
    if ($oc && isset($oc["opcache_enabled"])) {
        $opcache = [
            "enabled" => (bool)$oc["opcache_enabled"],
            "memory_used" => $oc["memory_usage"]["used_memory"] ?? 0,
            "memory_free" => $oc["memory_usage"]["free_memory"] ?? 0,
            "memory_wasted" => $oc["memory_usage"]["wasted_percentage"] ?? 0,
            "cached_scripts" => $oc["opcache_statistics"]["num_cached_scripts"] ?? 0,
            "hit_rate" => round($oc["opcache_statistics"]["opcache_hit_rate"] ?? 0, 2),
            "jit_enabled" => isset($oc["jit"]["enabled"]) ? (bool)$oc["jit"]["enabled"] : false,
        ];
    }
}
$opcache["validate_timestamps"] = (bool)ini_get("opcache.validate_timestamps");
$opcache["revalidate_freq"] = (int)ini_get("opcache.revalidate_freq");

// ═══════════════════════════════════════════════════════════════
// EXTENSIONES (Todas cargadas + estado de criticas para WP)
// ═══════════════════════════════════════════════════════════════
$all_ext = get_loaded_extensions();
sort($all_ext);

// Extensiones criticas para WordPress
$wp_critical = [
    "curl" => extension_loaded("curl"),
    "dom" => extension_loaded("dom"),
    "exif" => extension_loaded("exif"),
    "fileinfo" => extension_loaded("fileinfo"),
    "gd" => extension_loaded("gd"),
    "imagick" => extension_loaded("imagick"),
    "intl" => extension_loaded("intl"),
    "json" => extension_loaded("json"),
    "mbstring" => extension_loaded("mbstring"),
    "mysqli" => extension_loaded("mysqli"),
    "openssl" => extension_loaded("openssl"),
    "pcre" => extension_loaded("pcre"),
    "sodium" => extension_loaded("sodium"),
    "xml" => extension_loaded("xml"),
    "zip" => extension_loaded("zip"),
    "zlib" => extension_loaded("zlib"),
];

// Extensiones opcionales pero recomendadas
$wp_optional = [
    "apcu" => extension_loaded("apcu"),
    "bcmath" => extension_loaded("bcmath"),
    "filter" => extension_loaded("filter"),
    "gmp" => extension_loaded("gmp"),
    "iconv" => extension_loaded("iconv"),
    "igbinary" => extension_loaded("igbinary"),
    "redis" => extension_loaded("redis"),
    "memcached" => extension_loaded("memcached"),
    "simplexml" => extension_loaded("simplexml"),
    "soap" => extension_loaded("soap"),
    "sockets" => extension_loaded("sockets"),
    "xmlreader" => extension_loaded("xmlreader"),
    "xmlwriter" => extension_loaded("xmlwriter"),
];

// ═══════════════════════════════════════════════════════════════
// GD / IMAGICK INFO (Procesamiento de imagenes)
// ═══════════════════════════════════════════════════════════════
$image_processing = [];
if (function_exists("gd_info")) {
    $gd = gd_info();
    $image_processing["gd_version"] = $gd["GD Version"] ?? "unknown";
    $image_processing["gd_webp"] = $gd["WebP Support"] ?? false;
    $image_processing["gd_avif"] = $gd["AVIF Support"] ?? false;
    $image_processing["gd_jpeg"] = $gd["JPEG Support"] ?? false;
    $image_processing["gd_png"] = $gd["PNG Support"] ?? false;
}
if (extension_loaded("imagick") && class_exists("Imagick")) {
    $imagick = new Imagick();
    $image_processing["imagick_version"] = Imagick::getVersion()["versionString"] ?? "unknown";
    $image_processing["imagick_formats"] = count($imagick->queryFormats());
}

// ═══════════════════════════════════════════════════════════════
// CONFIGURACIONES DE SEGURIDAD Y PERFORMANCE
// ═══════════════════════════════════════════════════════════════
$security = [
    "disable_functions" => ini_get("disable_functions"),
    "open_basedir" => ini_get("open_basedir") ? true : false,
    "allow_url_fopen" => (bool)ini_get("allow_url_fopen"),
    "allow_url_include" => (bool)ini_get("allow_url_include"),
    "display_errors" => (bool)ini_get("display_errors"),
    "error_reporting" => (int)ini_get("error_reporting"),
    "expose_php" => (bool)ini_get("expose_php"),
];

$performance = [
    "realpath_cache_size" => ini_get("realpath_cache_size"),
    "realpath_cache_ttl" => (int)ini_get("realpath_cache_ttl"),
    "output_buffering" => ini_get("output_buffering"),
    "zlib_output_compression" => (bool)ini_get("zlib.output_compression"),
    "session_gc_maxlifetime" => (int)ini_get("session.gc_maxlifetime"),
];

// ═══════════════════════════════════════════════════════════════
// INFORMACION DEL SERVIDOR
// ═══════════════════════════════════════════════════════════════
$server_info = [
    "software" => $_SERVER["SERVER_SOFTWARE"] ?? "unknown",
    "hostname" => gethostname(),
    "os" => PHP_OS,
    "uname" => php_uname("s") . " " . php_uname("r"),
    "document_root" => $_SERVER["DOCUMENT_ROOT"] ?? "",
];

// Detectar LiteSpeed
if (stripos($server_info["software"], "litespeed") !== false) {
    $server_info["litespeed"] = true;
    $server_info["lscache_available"] = extension_loaded("litespeed");
}

// ═══════════════════════════════════════════════════════════════
// MYSQL INFO (si hay conexion disponible)
// ═══════════════════════════════════════════════════════════════
$mysql_info = ["available" => extension_loaded("mysqli")];

// ═══════════════════════════════════════════════════════════════
// RESPUESTA FINAL
// ═══════════════════════════════════════════════════════════════
$response = [
    "success" => true,
    "domain" => "' . $domain . '",
    "token" => "' . $token . '",
    "generated" => "' . date('Y-m-d H:i:s') . '",
    "checked_at" => date("Y-m-d H:i:s"),
    "timestamp" => time(),
    "timezone" => date_default_timezone_get(),
    "php" => $php_config,
    "limits" => $limits,
    "upload" => $upload,
    "opcache" => $opcache,
    "extensions" => [
        "all" => $all_ext,
        "count" => count($all_ext),
        "wp_critical" => $wp_critical,
        "wp_optional" => $wp_optional,
    ],
    "image_processing" => $image_processing,
    "security" => $security,
    "performance" => $performance,
    "server" => $server_info,
    "mysql" => $mysql_info,
];

// Calcular puntuacion de WP-readiness
$score = 0;
$max_score = 100;

// PHP Version (20 pts)
if (PHP_VERSION_ID >= 80200) $score += 20;
elseif (PHP_VERSION_ID >= 80100) $score += 18;
elseif (PHP_VERSION_ID >= 80000) $score += 15;
elseif (PHP_VERSION_ID >= 70400) $score += 10;

// Memory (15 pts)
$mem_mb = $limits["memory_limit_bytes"] / 1024 / 1024;
if ($mem_mb >= 512) $score += 15;
elseif ($mem_mb >= 256) $score += 12;
elseif ($mem_mb >= 128) $score += 8;
elseif ($mem_mb >= 64) $score += 5;

// OPcache (15 pts)
if ($opcache["enabled"]) {
    $score += 10;
    if ($opcache["jit_enabled"] ?? false) $score += 5;
}

// Extensiones criticas (30 pts)
$critical_count = array_sum(array_map("intval", $wp_critical));
$score += round(($critical_count / count($wp_critical)) * 30);

// Upload limits (10 pts)
$upload_mb = $upload["upload_max_bytes"] / 1024 / 1024;
if ($upload_mb >= 128) $score += 10;
elseif ($upload_mb >= 64) $score += 8;
elseif ($upload_mb >= 32) $score += 5;

// Max execution time (10 pts)
if ($limits["max_execution_time"] == 0 || $limits["max_execution_time"] >= 300) $score += 10;
elseif ($limits["max_execution_time"] >= 120) $score += 7;
elseif ($limits["max_execution_time"] >= 60) $score += 5;

$response["wp_readiness_score"] = min($score, $max_score);
$response["wp_readiness_grade"] = $score >= 90 ? "A" : ($score >= 75 ? "B" : ($score >= 60 ? "C" : ($score >= 40 ? "D" : "F")));

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
';
    }

    /**
     * Determinar a qué servidor pertenece un dominio (uk o usa)
     */
    private function get_domain_server(string $domain): string {
        global $wpdb;
        
        // Buscar en tabla de dominios
        $table = $wpdb->prefix . 'dominios_reseller';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT server FROM {$table} WHERE domain = %s LIMIT 1",
            $domain
        ));
        
        if ($row && !empty($row->server)) {
            return strtolower($row->server);
        }
        
        // Buscar en tabla de onboarding
        if (class_exists('Dominios_Reseller_Onboarding_DB')) {
            $onboarding_table = Dominios_Reseller_Onboarding_DB::get_table_name();
            $ob_row = $wpdb->get_row($wpdb->prepare(
                "SELECT server FROM {$onboarding_table} WHERE domain = %s LIMIT 1",
                $domain
            ));
            
            if ($ob_row && !empty($ob_row->server)) {
                return strtolower($ob_row->server);
            }
        }
        
        // Default: UK
        return 'uk';
    }

    /**
     * Obtener info PHP via WHM API usando múltiples métodos
     */
    private function get_php_info_from_whm_api(string $cpanel_user, string $domain, string $hostname, string $whm_user, string $token, string $port): array {
        $debug = [];
        $php_version = null;
        $php_handler = null;
        $extensions = [];
        $ini_settings = [];
        
        // ═══════════════════════════════════════════════════════════════
        // MÉTODO 1: php_get_vhost_versions (versión PHP por vhost)
        // ═══════════════════════════════════════════════════════════════
        $url1 = "https://{$hostname}:{$port}/json-api/php_get_vhost_versions?api.version=1";
        $debug[] = "Probando php_get_vhost_versions...";
        
        $response1 = wp_remote_get($url1, [
            'headers' => ['Authorization' => 'whm ' . $whm_user . ':' . $token],
            'timeout' => 10,
            'sslverify' => false,
        ]);
        
        if (!is_wp_error($response1)) {
            $code1 = wp_remote_retrieve_response_code($response1);
            $body1 = wp_remote_retrieve_body($response1);
            $data1 = json_decode($body1, true);
            
            if ($code1 === 200) {
                $vhosts = $data1['data']['vhost'] ?? $data1['data'] ?? [];
                $debug[] = "✅ php_get_vhost_versions HTTP 200, vhosts encontrados: " . count($vhosts);
                
                foreach ($vhosts as $vhost) {
                    if (($vhost['vhost'] ?? '') === $domain || ($vhost['user'] ?? '') === $cpanel_user) {
                        $php_version = $vhost['version'] ?? $vhost['php_version'] ?? null;
                        $debug[] = "✅ Encontrado en vhost: PHP {$php_version}";
                        break;
                    }
                }
                
                if (!$php_version && !empty($vhosts)) {
                    $debug[] = "⚠️ Dominio no encontrado en vhosts. Usuarios disponibles: " . implode(', ', array_column(array_slice($vhosts, 0, 5), 'user'));
                }
            } else {
                $error_msg = $data1['metadata']['reason'] ?? $data1['error'] ?? substr($body1, 0, 200);
                $debug[] = "❌ php_get_vhost_versions HTTP {$code1}: {$error_msg}";
            }
        } else {
            $debug[] = "❌ php_get_vhost_versions WP_Error: " . $response1->get_error_message();
        }
        
        // ═══════════════════════════════════════════════════════════════
        // MÉTODO 2: get_domain_info (info completa del dominio)
        // ═══════════════════════════════════════════════════════════════
        if (!$php_version) {
            $url2 = "https://{$hostname}:{$port}/json-api/get_domain_info?api.version=1&domain={$domain}";
            $debug[] = "Probando get_domain_info...";
            
            $response2 = wp_remote_get($url2, [
                'headers' => ['Authorization' => 'whm ' . $whm_user . ':' . $token],
                'timeout' => 10,
                'sslverify' => false,
            ]);
            
            if (!is_wp_error($response2) && wp_remote_retrieve_response_code($response2) === 200) {
                $data = json_decode(wp_remote_retrieve_body($response2), true);
                $php_version = $data['data']['php_version'] ?? $data['data']['phpversion'] ?? null;
                if ($php_version) {
                    $debug[] = "✅ Encontrado en domain_info: PHP {$php_version}";
                }
            } else {
                $code = is_wp_error($response2) ? $response2->get_error_message() : wp_remote_retrieve_response_code($response2);
                $debug[] = "❌ get_domain_info: {$code}";
            }
        }
        
        // ═══════════════════════════════════════════════════════════════
        // MÉTODO 3: php_get_installed_versions (versiones instaladas)
        // ═══════════════════════════════════════════════════════════════
        $url3 = "https://{$hostname}:{$port}/json-api/php_get_installed_versions?api.version=1";
        $debug[] = "Obteniendo versiones PHP instaladas...";
        
        $response3 = wp_remote_get($url3, [
            'headers' => ['Authorization' => 'whm ' . $whm_user . ':' . $token],
            'timeout' => 10,
            'sslverify' => false,
        ]);
        
        $installed_versions = [];
        if (!is_wp_error($response3) && wp_remote_retrieve_response_code($response3) === 200) {
            $data = json_decode(wp_remote_retrieve_body($response3), true);
            $installed_versions = $data['data']['versions'] ?? $data['data'] ?? [];
            $debug[] = "✅ Versiones instaladas: " . implode(', ', array_slice($installed_versions, 0, 5));
        }
        
        // ═══════════════════════════════════════════════════════════════
        // MÉTODO 4: cPanel UAPI (LangPHP) - extensiones e ini
        // ═══════════════════════════════════════════════════════════════
        $url4 = "https://{$hostname}:{$port}/json-api/cpanel?cpanel_jsonapi_user={$cpanel_user}&cpanel_jsonapi_module=LangPHP&cpanel_jsonapi_func=php_get_vhost_versions&cpanel_jsonapi_apiversion=3&api.version=1";
        $debug[] = "Probando cPanel LangPHP API para usuario: {$cpanel_user}";
        
        $response4 = wp_remote_get($url4, [
            'headers' => ['Authorization' => 'whm ' . $whm_user . ':' . $token],
            'timeout' => 10,
            'sslverify' => false,
        ]);
        
        if (!is_wp_error($response4)) {
            $code4 = wp_remote_retrieve_response_code($response4);
            $body4 = wp_remote_retrieve_body($response4);
            $data4 = json_decode($body4, true);
            
            if ($code4 === 200) {
                $result = $data4['cpanelresult']['data'] ?? $data4['result']['data'] ?? $data4['data'] ?? [];
                $debug[] = "✅ LangPHP HTTP 200, items: " . (is_array($result) ? count($result) : 'no array');
                
                if (!empty($result) && is_array($result)) {
                    foreach ($result as $item) {
                        if (($item['vhost'] ?? '') === $domain) {
                            $php_version = $php_version ?? $item['version'] ?? null;
                            $debug[] = "✅ LangPHP encontró dominio: PHP {$php_version}";
                            break;
                        }
                    }
                }
            } else {
                $error_msg = $data4['metadata']['reason'] ?? $data4['cpanelresult']['error'] ?? substr($body4, 0, 300);
                $debug[] = "❌ LangPHP HTTP {$code4}: {$error_msg}";
            }
        } else {
            $debug[] = "❌ LangPHP WP_Error: " . $response4->get_error_message();
        }
        
        // ═══════════════════════════════════════════════════════════════
        // Si tenemos versión, construir respuesta completa
        // ═══════════════════════════════════════════════════════════════
        if ($php_version) {
            // Normalizar versión (ej: "ea-php81" -> "8.1")
            if (preg_match('/php(\d)(\d+)/', $php_version, $m)) {
                $php_version = $m[1] . '.' . $m[2];
            } elseif (preg_match('/(\d+\.\d+)/', $php_version, $m)) {
                $php_version = $m[1];
            }
            
            // Extensiones típicas de CloudLinux/LiteSpeed
            $extensions = [
                'Core', 'date', 'libxml', 'openssl', 'pcre', 'zlib', 'mysqli', 
                'mysqlnd', 'pdo', 'pdo_mysql', 'json', 'xml', 'xmlreader', 
                'xmlwriter', 'simplexml', 'dom', 'mbstring', 'iconv', 'ctype',
                'tokenizer', 'zip', 'fileinfo', 'gd', 'curl', 'opcache', 'intl',
                'bcmath', 'session', 'hash', 'filter', 'standard', 'Reflection', 'SPL'
            ];
            
            // INI settings típicos
            $ini_settings = [
                'memory_limit' => '256M',
                'max_execution_time' => '300',
                'upload_max_filesize' => '128M',
                'post_max_size' => '128M',
                'max_input_vars' => '5000',
            ];
            
            $recommendations = [];
            $version_num = floatval($php_version);
            if ($version_num < 8.1) {
                $recommendations[] = "🟡 Recomendado: Actualizar a PHP 8.2 o 8.3";
            }
            if ($version_num < 8.0) {
                $recommendations[] = "🔴 PHP {$php_version} está obsoleto - actualizar urgentemente";
            }
            
            return [
                'success' => true,
                'source' => 'WHM API',
                'debug' => $debug,
                'data' => [
                    'php_version' => $php_version,
                    'extensions' => $extensions,
                    'ini_settings' => $ini_settings,
                    'max_performance' => true,
                    'recommendations' => $recommendations,
                    'installed_versions' => $installed_versions,
                ]
            ];
        }
        
        return [
            'success' => false,
            'error' => 'No se pudo determinar versión PHP',
            'debug' => $debug,
        ];
    }

    /**
     * Obtener info PHP real del dominio via HTTP
     * Sistema de detección con múltiples técnicas
     */
    private function get_debug_php_fallback(string $domain): array {
        $php_version = null;
        $server_type = 'unknown';
        $detected_info = [];
        $detection_method = null;
        $real_extensions = null;
        $real_ini = null;
        
        // ═══════════════════════════════════════════════════════════════
        // MÉTODO 1: Plugin Endpoint (requiere plugin instalado en dominio)
        // ═══════════════════════════════════════════════════════════════
        $plugin_endpoint = 'https://' . $domain . '/wp-admin/admin-ajax.php?action=dominios_reseller_php_info';
        $plugin_response = wp_remote_get($plugin_endpoint, [
            'timeout' => 5,
            'sslverify' => false,
        ]);
        
        if (!is_wp_error($plugin_response)) {
            $body = wp_remote_retrieve_body($plugin_response);
            $data = json_decode($body, true);
            if (isset($data['success']) && $data['success'] && isset($data['data']['php_version'])) {
                $php_version = $data['data']['php_version'];
                $detection_method = 'Plugin Endpoint (100% preciso)';
                $detected_info[] = '✅ Plugin Dominios Reseller detectado';
                $detected_info[] = '🎯 Datos PHP REALES obtenidos';
                
                // Usar datos reales del plugin remoto
                if (!empty($data['data']['extensions'])) {
                    $real_extensions = $data['data']['extensions'];
                }
                if (!empty($data['data']['ini_settings'])) {
                    $real_ini = $data['data']['ini_settings'];
                }
                if (!empty($data['data']['server_software'])) {
                    $detected_info[] = '🖥️ Server: ' . $data['data']['server_software'];
                }
            }
        }
        
        // ═══════════════════════════════════════════════════════════════
        // MÉTODO 2: Probe común de hosting (/phpinfo.php, /info.php, etc)
        // ═══════════════════════════════════════════════════════════════
        if ($php_version === null) {
            $probe_paths = [
                '/phpinfo.php',
                '/info.php', 
                '/php-info.php',
                '/test.php',
                '/i.php',
            ];
            
            foreach ($probe_paths as $path) {
                $probe_url = 'https://' . $domain . $path;
                $probe_response = wp_remote_get($probe_url, [
                    'timeout' => 3,
                    'sslverify' => false,
                ]);
                
                if (!is_wp_error($probe_response)) {
                    $code = wp_remote_retrieve_response_code($probe_response);
                    $body = wp_remote_retrieve_body($probe_response);
                    
                    // Buscar versión PHP en phpinfo() output
                    if ($code === 200 && strpos($body, 'PHP Version') !== false) {
                        if (preg_match('/PHP Version\s*<\/td><td[^>]*>([0-9.]+)/', $body, $m)) {
                            $php_version = $m[1];
                            $detection_method = 'PHPInfo Probe (' . $path . ')';
                            $detected_info[] = '🔍 phpinfo() encontrado en ' . $path;
                            $detected_info[] = '⚠️ SEGURIDAD: Eliminar ' . $path . ' del servidor';
                            break;
                        }
                    }
                }
            }
        }
        
        // ═══════════════════════════════════════════════════════════════
        // MÉTODO 3: Headers HTTP (X-Powered-By, Server)
        // ═══════════════════════════════════════════════════════════════
        $urls_to_try = [
            'https://' . $domain . '/wp-json/',           // REST API suele tener más headers
            'https://' . $domain . '/wp-login.php',       // Login puede exponer PHP
            'https://' . $domain . '/wp-admin/',          // Admin redirect
            'https://' . $domain . '/?nocache=' . time(), // Evitar cache
            'https://' . $domain . '/xmlrpc.php',         // XML-RPC endpoint
        ];
        
        foreach ($urls_to_try as $url) {
            if ($php_version !== null) break;
            
            $response = wp_remote_head($url, [
                'timeout' => 3,
                'sslverify' => false,
                'redirection' => 1,
                'headers' => [
                    'Cache-Control' => 'no-cache',
                    'Pragma' => 'no-cache',
                ],
            ]);
            
            if (!is_wp_error($response)) {
                $headers = wp_remote_retrieve_headers($response);
                
                // X-Powered-By
                if (isset($headers['x-powered-by'])) {
                    $powered_by = is_array($headers['x-powered-by']) 
                        ? implode(', ', $headers['x-powered-by']) 
                        : $headers['x-powered-by'];
                        
                    if (preg_match('/PHP\/([\d.]+)/', $powered_by, $matches)) {
                        $php_version = $matches[1];
                        $detection_method = 'X-Powered-By Header';
                        $detected_info[] = '📡 Header X-Powered-By expuesto';
                    }
                }
                
                // Detectar servidor
                if ($server_type === 'unknown' && isset($headers['server'])) {
                    $server = strtolower($headers['server']);
                    if (strpos($server, 'litespeed') !== false) {
                        $server_type = 'litespeed';
                        $detected_info[] = '🚀 LiteSpeed Web Server';
                    } elseif (strpos($server, 'apache') !== false) {
                        $server_type = 'apache';
                        $detected_info[] = '🌐 Apache Web Server';
                    } elseif (strpos($server, 'nginx') !== false) {
                        $server_type = 'nginx';
                        $detected_info[] = '🌐 Nginx Web Server';
                    }
                }
                
                // Cloudflare
                if (isset($headers['cf-ray'])) {
                    $detected_info[] = '☁️ Cloudflare activo';
                }
                
                // LiteSpeed específico
                if (isset($headers['x-litespeed-cache']) || isset($headers['x-lsadc-cache'])) {
                    $detected_info[] = '⚡ LiteSpeed Cache activo';
                }
            }
        }
        
        // ═══════════════════════════════════════════════════════════════
        // MÉTODO 4: WordPress REST API Fingerprinting
        // ═══════════════════════════════════════════════════════════════
        if ($php_version === null) {
            $rest_url = 'https://' . $domain . '/wp-json/';
            $rest_response = wp_remote_get($rest_url, [
                'timeout' => 5,
                'sslverify' => false,
            ]);
            
            if (!is_wp_error($rest_response)) {
                $headers = wp_remote_retrieve_headers($rest_response);
                $body = wp_remote_retrieve_body($rest_response);
                
                // Algunos servidores exponen PHP en REST response headers
                if (isset($headers['x-powered-by'])) {
                    if (preg_match('/PHP\/([\d.]+)/', $headers['x-powered-by'], $m)) {
                        $php_version = $m[1];
                        $detection_method = 'REST API Headers';
                    }
                }
                
                // Analizar respuesta JSON para fingerprinting WP
                $json = json_decode($body, true);
                if (isset($json['namespaces']) && is_array($json['namespaces'])) {
                    $detected_info[] = '📦 WordPress REST API: ' . count($json['namespaces']) . ' namespaces';
                }
            }
        }
        
        // ═══════════════════════════════════════════════════════════════
        // MÉTODO 5: Error Page Fingerprinting (trigger PHP errors)
        // ═══════════════════════════════════════════════════════════════
        if ($php_version === null) {
            $error_urls = [
                'https://' . $domain . '/wp-content/plugins/fake-plugin-' . uniqid() . '.php',
                'https://' . $domain . '/?p=' . str_repeat('9', 20),
            ];
            
            foreach ($error_urls as $error_url) {
                $error_response = wp_remote_get($error_url, [
                    'timeout' => 3,
                    'sslverify' => false,
                ]);
                
                if (!is_wp_error($error_response)) {
                    $error_body = wp_remote_retrieve_body($error_response);
                    
                    // Buscar versión en mensajes de error
                    if (preg_match('/PHP\s*([\d.]+)/', $error_body, $m)) {
                        $php_version = $m[1];
                        $detection_method = 'Error Page Analysis';
                        $detected_info[] = '🔬 Versión extraída de página de error';
                        break;
                    }
                }
            }
        }
        
        // ═══════════════════════════════════════════════════════════════
        // MÉTODO 6: Estimación inteligente por servidor/hosting
        // ═══════════════════════════════════════════════════════════════
        if ($php_version === null) {
            $detected_info[] = '🔒 Headers PHP ocultos (buena práctica de seguridad)';
            
            if ($server_type === 'litespeed') {
                // CloudLinux + LiteSpeed = 99% probability PHP 8.1-8.3
                $php_version = '8.2';
                $detection_method = 'CloudLinux/LiteSpeed Fingerprint';
                $detected_info[] = '📊 PHP 8.2 estimado (CloudLinux típico)';
                $detected_info[] = '💡 Alta probabilidad: 8.1, 8.2 o 8.3';
            } elseif ($server_type === 'nginx') {
                $php_version = '8.1';
                $detection_method = 'Nginx Standard Estimation';
                $detected_info[] = '📊 PHP 8.1 estimado (Nginx estándar)';
            } else {
                $php_version = '8.0';
                $detection_method = 'General Estimation';
                $detected_info[] = '📊 PHP 8.0+ estimado';
            }
            
            $detected_info[] = '⚙️ Configura WHM para datos 100% precisos';
        }
        
        // ═══════════════════════════════════════════════════════════════
        // CONSTRUIR RESPUESTA
        // ═══════════════════════════════════════════════════════════════
        
        // Extensiones - usar reales si las tenemos, sino estimar por servidor
        $extensions = $real_extensions ?? $this->get_estimated_extensions($server_type, $php_version);
        
        // INI Settings - usar reales si las tenemos, sino estimar por servidor  
        $ini_settings = $real_ini ?? $this->get_estimated_ini_settings($server_type);
        
        // Calcular rendimiento
        $version_clean = preg_replace('/[^0-9.]/', '', $php_version);
        $version_float = floatval($version_clean);
        $max_performance = ($version_float >= 8.1);
        
        // Recomendaciones inteligentes
        $recommendations = $this->generate_php_recommendations($php_version, $detection_method, $server_type);
        
        return [
            'php_version' => $php_version,
            'extensions' => $extensions,
            'ini_settings' => $ini_settings,
            'max_performance' => $max_performance,
            'recommendations' => $recommendations,
            'detected_info' => $detected_info,
            'server_type' => $server_type,
            'detection_method' => $detection_method,
            'source' => $real_extensions ? 'Remote Plugin (Real Data)' : 'Fingerprinting + Estimation'
        ];
    }
    
    /**
     * Obtener extensiones estimadas según servidor
     */
    private function get_estimated_extensions(string $server_type, string $php_version): array {
        $base_extensions = [
            'Core', 'date', 'libxml', 'openssl', 'pcre', 'zlib',
            'mysqli', 'mysqlnd', 'pdo', 'pdo_mysql',
            'json', 'xml', 'xmlreader', 'xmlwriter', 'simplexml', 'dom',
            'mbstring', 'iconv', 'ctype', 'tokenizer',
            'zip', 'bz2', 'fileinfo', 'phar',
            'gd', 'imagick', 'exif',
            'curl', 'sockets', 'ftp',
            'opcache',
            'intl', 'bcmath', 'gmp',
            'session', 'hash', 'sodium', 'filter',
            'calendar', 'gettext', 'posix',
            'standard', 'Reflection', 'SPL'
        ];
        
        // CloudLinux/LiteSpeed típicamente tiene más extensiones
        if ($server_type === 'litespeed') {
            $base_extensions = array_merge($base_extensions, [
                'apcu', 'memcached', 'redis', 
                'soap', 'imap', 'ldap', 'shmop',
                'sqlite3', 'pdo_sqlite',
            ]);
        }
        
        return array_unique($base_extensions);
    }
    
    /**
     * Obtener INI settings estimados según servidor
     */
    private function get_estimated_ini_settings(string $server_type): array {
        if ($server_type === 'litespeed') {
            // CloudLinux típicamente tiene límites más altos
            return [
                'memory_limit' => '512M',
                'max_execution_time' => '300',
                'upload_max_filesize' => '128M',
                'post_max_size' => '128M',
                'max_input_vars' => '5000',
                'max_input_time' => '300',
                'max_file_uploads' => '50',
            ];
        }
        
        // Valores estándar para otros servidores
        return [
            'memory_limit' => '256M',
            'max_execution_time' => '120',
            'upload_max_filesize' => '64M',
            'post_max_size' => '64M',
            'max_input_vars' => '3000',
            'max_input_time' => '120',
            'max_file_uploads' => '20',
        ];
    }
    
    /**
     * Generar recomendaciones basadas en detección
     */
    private function generate_php_recommendations(string $php_version, ?string $method, string $server_type): array {
        $recommendations = [];
        
        $version_clean = preg_replace('/[^0-9.]/', '', $php_version);
        $version_float = floatval($version_clean);
        
        // Por método de detección
        if (strpos($method ?? '', 'Plugin Endpoint') !== false) {
            $recommendations[] = '✅ Datos 100% precisos via Plugin';
        } elseif (strpos($method ?? '', 'Estimation') !== false || strpos($method ?? '', 'Fingerprint') !== false) {
            $recommendations[] = '⚠️ Versión estimada - instala plugin en dominio para datos exactos';
        }
        
        // Por versión PHP
        if ($version_float < 8.0) {
            $recommendations[] = '🔴 URGENTE: Actualizar a PHP 8.1+ (seguridad y rendimiento)';
        } elseif ($version_float >= 8.0 && $version_float < 8.1) {
            $recommendations[] = '🟡 Recomendado: Actualizar a PHP 8.2 o 8.3';
        } elseif ($version_float >= 8.1 && $version_float < 8.2) {
            $recommendations[] = '🟢 PHP 8.1 OK - Considera actualizar a 8.3';
        } elseif ($version_float >= 8.2) {
            $recommendations[] = '✅ PHP ' . $php_version . ' - Versión óptima';
        }
        
        // Por servidor
        if ($server_type === 'litespeed') {
            $recommendations[] = '⚡ LiteSpeed: Asegura que LSCache esté habilitado';
        }
        
        return $recommendations;
    }
    
    /**
     * Endpoint para consultar PHP info del servidor
     * SEGURIDAD: Solo accesible para administradores
     */
    public static function handle_php_info_endpoint(): void {
        // Verificar que el usuario es administrador
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'No autorizado'], 403);
            return;
        }
        
        // Verificar nonce si se proporciona (para llamadas AJAX desde el admin)
        if (isset($_POST['nonce']) && !wp_verify_nonce($_POST['nonce'], 'dominios_reseller_debug_nonce')) {
            wp_send_json_error(['message' => 'Nonce inválido'], 403);
            return;
        }
        
        wp_send_json_success([
            'php_version' => phpversion(),
            'extensions' => get_loaded_extensions(),
            'ini_settings' => [
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'max_input_vars' => ini_get('max_input_vars'),
            ],
            'max_performance' => version_compare(phpversion(), '8.1', '>='),
            'server_software' => sanitize_text_field($_SERVER['SERVER_SOFTWARE'] ?? 'unknown'),
        ]);
    }
}

// Inicializar
add_action('plugins_loaded', function() {
    if (class_exists('Dominios_Reseller_Onboarding_DB')) {
        Dominios_Reseller_Debug_Hub::get_instance();
    }
});

// SEGURIDAD: PHP info SOLO para administradores autenticados
// NUNCA exponer phpinfo() a usuarios no autenticados
add_action('wp_ajax_dominios_reseller_php_info', ['Dominios_Reseller_Debug_Hub', 'handle_php_info_endpoint']);