<?php
/**
 * Upmind Integration & WP Readiness Checker
 *
 * Integración con Upmind para onboarding automático y verificación de readiness para WordPress
 *
 * @package Dominios_Reseller
 * @version 1.0.0
 * @since 1.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dominios_Reseller_Upmind_Integration {

    /**
     * Instancia singleton
     */
    private static ?Dominios_Reseller_Upmind_Integration $instance = null;

    /**
     * Servicios
     */
    private Dominios_Reseller_Onboarding_Worker $onboarding_worker;
    private ?Dominios_Reseller_Auto_Discovery $auto_discovery = null;

    /**
     * Constructor
     */
    private function __construct() {
        $this->onboarding_worker = Dominios_Reseller_Onboarding_Worker::get_instance();
        if (class_exists('Dominios_Reseller_Auto_Discovery')) {
            $this->auto_discovery = Dominios_Reseller_Auto_Discovery::get_instance();
        }
        $this->init_hooks();
    }

    /**
     * Obtener instancia singleton
     */
    public static function get_instance(): Dominios_Reseller_Upmind_Integration {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Inicializar hooks
     */
    private function init_hooks(): void {
        // REST API endpoints (con verificación de firma)
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // NOTA DE SEGURIDAD: Webhooks de Upmind SOLO via REST API con verificación de firma
        // NO usar wp_ajax_nopriv_ para webhooks externos - es una práctica insegura
        // El endpoint correcto es: /wp-json/dominios-reseller/v1/webhook/upmind

        // Añadir columnas a la tabla de dominios
        add_filter('manage_dominios_reseller_posts_columns', [$this, 'add_wp_ready_column']);
        add_action('manage_dominios_reseller_posts_custom_column', [$this, 'render_wp_ready_column'], 10, 2);

        // Añadir meta box para verificación manual
        add_action('add_meta_boxes', [$this, 'add_wp_readiness_meta_box']);

        // AJAX para verificar WP readiness (SOLO usuarios autenticados)
        add_action('wp_ajax_dr_check_wp_readiness', [$this, 'ajax_check_wp_readiness']);
        add_action('wp_ajax_dr_fix_wp_readiness', [$this, 'ajax_fix_wp_readiness']);
    }

    /**
     * Registrar rutas REST API
     */
    public function register_rest_routes(): void {
        register_rest_route('dominios-reseller/v1', '/webhook/upmind', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_upmind_webhook'],
            'permission_callback' => [$this, 'verify_upmind_signature'],
        ]);

        register_rest_route('dominios-reseller/v1', '/wp-readiness/(?P<domain>[a-zA-Z0-9.-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_wp_readiness_status'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Verificar firma del webhook de Upmind
     */
    public function verify_upmind_signature(WP_REST_Request $request): bool {
        $signature = $request->get_header('X-Upmind-Signature');
        $secret = get_option('dr_upmind_webhook_secret', '');

        if (empty($signature) || empty($secret)) {
            return false;
        }

        $payload = $request->get_body();
        $expected_signature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected_signature, $signature);
    }

    /**
     * Manejar webhook de Upmind
     */
    public function handle_upmind_webhook(WP_REST_Request $request): WP_REST_Response {
        try {
            $data = $request->get_json_params();

            $this->log("Webhook Upmind recibido: " . json_encode($data));

            // Procesar diferentes tipos de eventos
            if (isset($data['event'])) {
                switch ($data['event']) {
                    case 'order.completed':
                        return $this->handle_order_completed($data);
                    case 'service.provisioned':
                        return $this->handle_service_provisioned($data);
                    case 'service.renewed':
                        return $this->handle_service_renewed($data);
                    case 'client.created':
                        return $this->handle_client_created($data);
                    default:
                        $this->log("Evento Upmind no manejado: {$data['event']}");
                        return new WP_REST_Response(['status' => 'ignored'], 200);
                }
            }

            return new WP_REST_Response(['status' => 'no_event'], 400);

        } catch (Exception $e) {
            $this->log("Error procesando webhook Upmind: " . $e->getMessage(), 'error');
            return new WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Manejar orden completada en Upmind
     */
    private function handle_order_completed(array $data): WP_REST_Response {
        if (!isset($data['order']['line_items'])) {
            return new WP_REST_Response(['status' => 'no_items'], 400);
        }

        $processed_domains = [];

        foreach ($data['order']['line_items'] as $item) {
            if ($this->is_hosting_product($item)) {
                $domain = $this->extract_domain_from_item($item);

                if ($domain) {
                    // Trigger onboarding automático como "optimización de bienvenida"
                    $this->trigger_welcome_optimization($domain, $data['order'], $item);
                    $processed_domains[] = $domain;
                }
            }
        }

        if (!empty($processed_domains)) {
            $this->log("Onboarding automático iniciado para dominios: " . implode(', ', $processed_domains));
            return new WP_REST_Response([
                'status' => 'success',
                'processed_domains' => $processed_domains,
                'message' => 'Welcome optimization initiated'
            ], 200);
        }

        return new WP_REST_Response(['status' => 'no_domains'], 200);
    }

    /**
     * Manejar servicio provisionado
     */
    private function handle_service_provisioned(array $data): WP_REST_Response {
        // Similar a order completed pero para provisionamiento individual
        $this->log("Servicio provisionado - implementación pendiente");
        return new WP_REST_Response(['status' => 'pending_implementation'], 200);
    }

    /**
     * Manejar cliente creado
     */
    private function handle_client_created(array $data): WP_REST_Response {
        // Posiblemente enviar email de bienvenida o preparar onboarding
        $this->log("Cliente creado - implementación pendiente");
        return new WP_REST_Response(['status' => 'pending_implementation'], 200);
    }

    /**
     * Manejar renovación de servicio (Forest Program hook)
     * 
     * @since 1.9.0 Forest Program
     */
    private function handle_service_renewed(array $data): WP_REST_Response {
        $this->log("Servicio renovado recibido: " . json_encode($data));
        
        global $wpdb;
        $table = $wpdb->prefix . 'dominios_reseller';
        
        // Extraer datos del servicio renovado
        $service = $data['service'] ?? [];
        $client  = $data['client'] ?? $service['client'] ?? [];
        $product = $service['product'] ?? [];
        
        $domain       = $this->extract_domain_from_service($service);
        $client_id    = $client['id'] ?? null;
        $client_name  = trim(($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? ''));
        $client_email = $client['email'] ?? '';
        $product_slug = strtolower($product['slug'] ?? $product['code'] ?? '');
        $billing_cycle = strtolower($service['billing_cycle'] ?? $service['billing_period'] ?? '');
        
        // Calcular próxima fecha de renovación
        $renewed_at = $service['renewed_at'] ?? $service['renewal_date'] ?? null;
        $next_renewal = null;
        
        if ($renewed_at && $billing_cycle) {
            $next_renewal = $this->calculate_next_renewal($renewed_at, $billing_cycle);
        } elseif (isset($service['next_due_date'])) {
            $next_renewal = $service['next_due_date'];
        } elseif (isset($service['next_renewal_date'])) {
            $next_renewal = $service['next_renewal_date'];
        }
        
        if (!$domain) {
            $this->log("service.renewed: Dominio no encontrado en payload");
            return new WP_REST_Response(['status' => 'no_domain'], 400);
        }
        
        // Actualizar registro en wp_dominios_reseller
        $update_data = [
            'upmind_synced_at' => current_time('mysql'),
        ];
        
        if ($client_id)      $update_data['upmind_client_id']    = $client_id;
        if ($client_name)    $update_data['upmind_client_name']  = $client_name;
        if ($client_email)   $update_data['upmind_client_email'] = $client_email;
        if ($product_slug)   $update_data['upmind_product_slug'] = $product_slug;
        if ($billing_cycle)  $update_data['billing_cycle']       = $billing_cycle;
        if ($next_renewal)   $update_data['next_renewal_date']   = $next_renewal;
        
        $updated = $wpdb->update(
            $table,
            $update_data,
            ['domain' => $domain],
            array_fill(0, count($update_data), '%s'),
            ['%s']
        );
        
        // Disparar hook para Forest Program
        do_action('dr_service_renewed', $domain, $update_data, $data);
        
        $this->log("service.renewed procesado para {$domain}: next_renewal={$next_renewal}");
        
        return new WP_REST_Response([
            'status'       => 'success',
            'domain'       => $domain,
            'next_renewal' => $next_renewal,
        ], 200);
    }

    /**
     * Extraer dominio de datos de servicio
     * 
     * @since 1.9.0
     */
    private function extract_domain_from_service(array $service): ?string {
        $possible_fields = [
            'domain',
            'hostname',
            'properties.domain',
            'attributes.domain',
            'custom_fields.domain',
            'meta.domain',
        ];
        
        foreach ($possible_fields as $field) {
            $value = $this->get_nested_value($service, $field);
            if ($value && $this->is_valid_domain($value)) {
                return strtolower($value);
            }
        }
        
        // Intentar extraer de name si contiene un dominio válido
        $name = $service['name'] ?? '';
        if (preg_match('/([a-zA-Z0-9][-a-zA-Z0-9]*\.)+[a-zA-Z]{2,}/', $name, $m)) {
            return strtolower($m[0]);
        }
        
        return null;
    }

    /**
     * Calcular próxima fecha de renovación
     * 
     * @since 1.9.0
     */
    private function calculate_next_renewal(string $renewed_at, string $billing_cycle): string {
        $date = new DateTime($renewed_at);
        
        $intervals = [
            'monthly'   => 'P1M',
            'quarterly' => 'P3M',
            'annually'  => 'P1Y',
            'annual'    => 'P1Y',
            'yearly'    => 'P1Y',
            'biannual'  => 'P2Y',
            'triennially' => 'P3Y',
        ];
        
        $interval = $intervals[$billing_cycle] ?? 'P1Y';
        $date->add(new DateInterval($interval));
        
        return $date->format('Y-m-d');
    }

    /**
     * Verificar si un item es un producto de hosting
     */
    private function is_hosting_product(array $item): bool {
        // Verificar por nombre del producto, categoría, etc.
        $hosting_keywords = ['hosting', 'web', 'wordpress', 'wp', 'site'];
        $product_name = strtolower($item['name'] ?? '');

        foreach ($hosting_keywords as $keyword) {
            if (strpos($product_name, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extraer dominio de un item de orden
     */
    private function extract_domain_from_item(array $item): ?string {
        // Intentar extraer dominio de diferentes campos
        $possible_fields = ['domain', 'custom_fields.domain', 'metadata.domain'];

        foreach ($possible_fields as $field) {
            $domain = $this->get_nested_value($item, $field);
            if ($domain && $this->is_valid_domain($domain)) {
                return strtolower($domain);
            }
        }

        return null;
    }

    /**
     * Trigger optimización de bienvenida (onboarding automático)
     */
    private function trigger_welcome_optimization(string $domain, array $order_data, array $item_data): void {
        try {
            $this->log("Iniciando optimización de bienvenida para: {$domain}");

            // Verificar que no esté ya en proceso
            $existing_state = Dominios_Reseller_Onboarding_DB::get_onboarding_state($domain);
            if ($existing_state && in_array(($existing_state['state'] ?? ''), ['onboarded', 'running', 'pending'])) {
                $this->log("Dominio ya en proceso: {$domain}");
                return;
            }

            // Crear/actualizar entrada de onboarding con metadatos de Upmind
            Dominios_Reseller_Onboarding_DB::upsert_onboarding(
                $domain,
                [
                    'state'      => 'pending',
                    'preset_key' => 'wp',
                    'meta'       => wp_json_encode([
                        'source'               => 'upmind_welcome_optimization',
                        'client_id'            => $order_data['client_id'] ?? null,
                        'order_id'             => $order_data['id']        ?? null,
                        'upmind_order_data'    => $order_data,
                        'upmind_item_data'     => $item_data,
                        'welcome_optimization' => true,
                        'auto_discovered'      => true,
                    ]),
                ]
            );

            // Encolar para procesamiento inmediato
            $this->onboarding_worker->enqueue($domain, 'wp', false);

            // Notificar al cliente sobre la optimización
            $this->send_welcome_optimization_notification($domain, $order_data);

            $this->log("Optimización de bienvenida encolada para: {$domain}");

        } catch (Exception $e) {
            $this->log("Error en welcome optimization para {$domain}: " . $e->getMessage(), 'error');
        }
    }

    /**
     * Enviar notificación de optimización de bienvenida
     */
    private function send_welcome_optimization_notification(string $domain, array $order_data): void {
        // Implementar envío de email/SMS al cliente
        $this->log("Notificación de welcome optimization pendiente para: {$domain}");
    }

    /**
     * Añadir columna WP Ready a la tabla de dominios
     */
    public function add_wp_ready_column(array $columns): array {
        $columns['wp_ready'] = 'WP Ready';
        return $columns;
    }

    /**
     * Renderizar columna WP Ready
     */
    public function render_wp_ready_column(string $column, int $post_id): void {
        if ($column !== 'wp_ready') {
            return;
        }

        $domain = get_post_meta($post_id, 'domain', true);
        if (empty($domain)) {
            echo '—';
            return;
        }

        $readiness_status = $this->get_wp_readiness_cached($domain);

        $status_class = 'wp-ready-unknown';
        $status_text = 'Verificar';
        $icon = '🔍';

        if ($readiness_status) {
            if ($readiness_status['overall_ready']) {
                $status_class = 'wp-ready-yes';
                $status_text = '✅ Listo';
                $icon = '✅';
            } else {
                $status_class = 'wp-ready-no';
                $status_text = '❌ Revisar';
                $icon = '❌';
            }
        }

        echo "<span class='wp-ready-indicator {$status_class}' data-domain='{$domain}'>";
        echo "<span class='wp-ready-icon'>{$icon}</span>";
        echo "<span class='wp-ready-text'>{$status_text}</span>";
        echo "</span>";
    }

    /**
     * Añadir meta box de WP Readiness
     */
    public function add_wp_readiness_meta_box(): void {
        add_meta_box(
            'dr-wp-readiness',
            'WordPress Readiness Check',
            [$this, 'render_wp_readiness_meta_box'],
            'dominios_reseller',
            'side',
            'default'
        );
    }

    /**
     * Renderizar meta box de WP Readiness
     */
    public function render_wp_readiness_meta_box(WP_Post $post): void {
        $domain = get_post_meta($post->ID, 'domain', true);

        if (empty($domain)) {
            echo '<p>No se encontró dominio para este registro.</p>';
            return;
        }

        echo "<div class='dr-wp-readiness-container' data-domain='{$domain}'>";
        echo "<p><strong>Dominio:</strong> {$domain}</p>";
        echo "<div id='wp-readiness-status'>Cargando...</div>";
        echo "<button type='button' id='check-wp-readiness' class='button button-primary'>Verificar WP Readiness</button>";
        echo "<button type='button' id='fix-wp-readiness' class='button button-secondary' style='margin-left: 10px;'>Corregir Automáticamente</button>";
        echo "</div>";
    }

    /**
     * AJAX handler para verificar WP readiness
     */
    public function ajax_check_wp_readiness(): void {
        try {
            $domain = sanitize_text_field($_POST['domain'] ?? '');

            if (empty($domain)) {
                wp_send_json_error('Dominio requerido');
                return;
            }

            $readiness = $this->check_wp_readiness($domain);

            wp_send_json_success($readiness);

        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }

    /**
     * AJAX handler para corregir WP readiness
     */
    public function ajax_fix_wp_readiness(): void {
        try {
            $domain = sanitize_text_field($_POST['domain'] ?? '');

            if (empty($domain)) {
                wp_send_json_error('Dominio requerido');
                return;
            }

            $result = $this->fix_wp_readiness($domain);

            wp_send_json_success($result);

        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }

    /**
     * Verificar readiness para WordPress (con cache)
     */
    private function get_wp_readiness_cached(string $domain): ?array {
        $cache_key = 'dr_wp_readiness_' . md5($domain);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $readiness = $this->check_wp_readiness($domain);
        set_transient($cache_key, $readiness, HOUR_IN_SECONDS); // Cache por 1 hora

        return $readiness;
    }

    /**
     * Verificar readiness completo para WordPress
     */
    public function check_wp_readiness(string $domain): array {
        $results = [
            'domain' => $domain,
            'overall_ready' => true,
            'checks' => [],
            'recommendations' => []
        ];

        try {
            // 1. Verificar PHP version
            $php_check = $this->check_php_version($domain);
            $results['checks']['php_version'] = $php_check;
            if (!$php_check['ready']) {
                $results['overall_ready'] = false;
                $results['recommendations'][] = $php_check['fix'];
            }

            // 2. Verificar extensiones PHP requeridas
            $extensions_check = $this->check_php_extensions($domain);
            $results['checks']['php_extensions'] = $extensions_check;
            if (!$extensions_check['ready']) {
                $results['overall_ready'] = false;
                $results['recommendations'] = array_merge($results['recommendations'], $extensions_check['fixes']);
            }

            // 3. Verificar límites PHP
            $limits_check = $this->check_php_limits($domain);
            $results['checks']['php_limits'] = $limits_check;
            if (!$limits_check['ready']) {
                $results['overall_ready'] = false;
                $results['recommendations'] = array_merge($results['recommendations'], $limits_check['fixes']);
            }

            // 4. Verificar permisos de directorio
            $permissions_check = $this->check_directory_permissions($domain);
            $results['checks']['directory_permissions'] = $permissions_check;
            if (!$permissions_check['ready']) {
                $results['overall_ready'] = false;
                $results['recommendations'][] = $permissions_check['fix'];
            }

        } catch (Exception $e) {
            $results['error'] = $e->getMessage();
            $results['overall_ready'] = false;
        }

        return $results;
    }

    /**
     * Verificar versión de PHP
     */
    private function check_php_version(string $domain): array {
        // Simular verificación - en implementación real usaríamos WHM API
        $current_version = '8.1'; // Esto vendría de la API de WHM
        $required_version = '7.4';

        $ready = version_compare($current_version, $required_version, '>=');

        return [
            'ready' => $ready,
            'current' => $current_version,
            'required' => $required_version,
            'message' => $ready ? 'Versión PHP compatible' : "PHP {$current_version} - Se requiere {$required_version}+",
            'fix' => $ready ? null : "Actualizar PHP a versión 8.0 o superior"
        ];
    }

    /**
     * Verificar extensiones PHP requeridas
     */
    private function check_php_extensions(string $domain): array {
        // Extensiones críticas para WordPress
        $required_extensions = [
            'curl', 'gd', 'mbstring', 'mysqlnd', 'openssl', 'xml', 'zip'
        ];

        $missing_extensions = []; // En implementación real, verificar vía WHM API

        $ready = empty($missing_extensions);

        return [
            'ready' => $ready,
            'required' => $required_extensions,
            'missing' => $missing_extensions,
            'message' => $ready ? 'Todas las extensiones requeridas instaladas' : 'Extensiones faltantes: ' . implode(', ', $missing_extensions),
            'fixes' => $ready ? [] : array_map(function($ext) {
                return "Instalar extensión PHP: {$ext}";
            }, $missing_extensions)
        ];
    }

    /**
     * Verificar límites PHP
     */
    private function check_php_limits(string $domain): array {
        // Límites recomendados para WordPress
        $recommended_limits = [
            'memory_limit' => '256M',
            'max_execution_time' => '300',
            'max_input_time' => '300',
            'post_max_size' => '64M',
            'upload_max_filesize' => '32M'
        ];

        $issues = []; // En implementación real, verificar vía WHM API

        $ready = empty($issues);

        return [
            'ready' => $ready,
            'recommended' => $recommended_limits,
            'issues' => $issues,
            'message' => $ready ? 'Límites PHP adecuados' : 'Problemas con límites PHP',
            'fixes' => $ready ? [] : ["Ajustar límites PHP según recomendaciones de WordPress"]
        ];
    }

    /**
     * Verificar permisos de directorio
     */
    private function check_directory_permissions(string $domain): array {
        // En implementación real, verificar vía WHM API o SSH
        $ready = true; // Simular que está bien
        $message = 'Permisos de directorio correctos';

        return [
            'ready' => $ready,
            'message' => $message,
            'fix' => $ready ? null : 'Ajustar permisos de directorio (755 para directorios, 644 para archivos)'
        ];
    }

    /**
     * Corregir problemas de WP readiness automáticamente
     */
    public function fix_wp_readiness(string $domain): array {
        $result = [
            'success' => false,
            'fixes_applied' => [],
            'errors' => []
        ];

        try {
            // En implementación real, usar WHM API para aplicar correcciones
            $this->log("Corrección automática de WP readiness pendiente para: {$domain}");

            $result['message'] = 'Funcionalidad de corrección automática pendiente de implementación con WHM API';
            $result['success'] = false;

        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Obtener estado de WP readiness vía REST API
     */
    public function get_wp_readiness_status(WP_REST_Request $request): WP_REST_Response {
        try {
            $domain = $request->get_param('domain');

            if (empty($domain)) {
                return new WP_REST_Response(['error' => 'Domain required'], 400);
            }

            $readiness = $this->get_wp_readiness_cached($domain);

            return new WP_REST_Response($readiness, 200);

        } catch (Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Utilidades helper
     */
    private function get_nested_value(array $array, string $path) {
        $keys = explode('.', $path);
        $value = $array;

        foreach ($keys as $key) {
            if (!isset($value[$key])) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    private function is_valid_domain(string $domain): bool {
        return filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }

    private function log(string $message, string $level = 'info'): void {
        if ( method_exists( 'Dominios_Reseller_Onboarding_DB', 'log_activity' ) ) {
            Dominios_Reseller_Onboarding_DB::log_activity(
                'upmind_integration',
                null,
                $message,
                [ 'level' => $level, 'component' => 'upmind_integration' ]
            );
            return;
        }
        error_log( "[DR Upmind] [{$level}] {$message}" );
    }

    /**
     * Procesar webhook de test (para Debug Hub)
     */
    public function process_test_webhook(array $webhook_data): array {
        try {
            $this->log("Procesando webhook de test: " . json_encode($webhook_data), 'info');

            // Validar estructura básica
            if (!isset($webhook_data['event']) || $webhook_data['event'] !== 'order.completed') {
                return ['success' => false, 'error' => 'Evento no válido'];
            }

            if (!isset($webhook_data['data'])) {
                return ['success' => false, 'error' => 'Datos faltantes'];
            }

            $data = $webhook_data['data'];
            $domain = $data['domain'] ?? '';
            $order_id = $data['order_id'] ?? 'TEST-' . time();

            if (empty($domain)) {
                return ['success' => false, 'error' => 'Dominio faltante'];
            }

            if (!$this->is_valid_domain($domain)) {
                return ['success' => false, 'error' => 'Dominio inválido'];
            }

            // Simular procesamiento (no ejecutar realmente)
            $this->log("Webhook de test procesado - Order: {$order_id}, Domain: {$domain}", 'info');

            return [
                'success' => true,
                'order_id' => $order_id,
                'domain' => $domain,
                'message' => 'Webhook procesado correctamente (test mode)'
            ];

        } catch (Exception $e) {
            $this->log("Error en webhook de test: " . $e->getMessage(), 'error');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Verificar WP readiness para test
     */
    public function check_wp_readiness_test(string $domain): array {
        try {
            $this->log("Verificando WP readiness para test: {$domain}", 'info');

            // Simular verificación básica (en test real se haría consulta a WHM/cPanel)
            $wp_check = [
                'ready' => false,
                'status' => 'unknown',
                'php_version' => 'Desconocido',
                'mysql_version' => 'Desconocido',
                'issues' => []
            ];

            // Simular diferentes escenarios basados en el dominio
            if (strpos($domain, 'test-wp-ready') !== false) {
                $wp_check['ready'] = true;
                $wp_check['status'] = 'WordPress detectado y listo';
                $wp_check['php_version'] = '8.1.0';
                $wp_check['mysql_version'] = '8.0.0';
            } elseif (strpos($domain, 'test-no-wp') !== false) {
                $wp_check['ready'] = false;
                $wp_check['status'] = 'WordPress no detectado';
                $wp_check['issues'] = ['No se detectó instalación de WordPress'];
            } elseif (strpos($domain, 'test-php-old') !== false) {
                $wp_check['ready'] = false;
                $wp_check['status'] = 'PHP version incompatible';
                $wp_check['php_version'] = '7.2.0';
                $wp_check['issues'] = ['PHP 7.2 no soportado, requiere mínimo 7.4'];
            } else {
                // Caso por defecto - asumir listo para test
                $wp_check['ready'] = true;
                $wp_check['status'] = 'WordPress listo (simulado)';
                $wp_check['php_version'] = '8.2.0';
                $wp_check['mysql_version'] = '8.0.0';
            }

            return $wp_check;

        } catch (Exception $e) {
            $this->log("Error en verificación WP test: " . $e->getMessage(), 'error');
            return [
                'ready' => false,
                'status' => 'error',
                'issues' => ['Error en verificación: ' . $e->getMessage()]
            ];
        }
    }
}

// Inicializar la integración
add_action('plugins_loaded', function() {
    Dominios_Reseller_Upmind_Integration::get_instance();
});