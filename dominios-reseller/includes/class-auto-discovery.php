<?php
/**
 * Auto-Discovery System - Proof of Concept
 *
 * Sistema para detectar automáticamente nuevos dominios desde múltiples fuentes
 * y trigger el onboarding sin intervención manual.
 *
 * @package Dominios_Reseller
 * @version 1.0.0
 * @since 1.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dominios_Reseller_Auto_Discovery {

    /**
     * Hook para el cron job
     */
    const CRON_HOOK = 'dr_auto_discovery_check';

    /**
     * Intervalo de polling (segundos)
     */
    const POLL_INTERVAL = 300; // 5 minutos

    /**
     * Transient para evitar procesamiento duplicado
     */
    const PROCESSED_DOMAINS_TRANSIENT = 'dr_processed_domains';

    /**
     * Instancia singleton
     */
    private static ?Dominios_Reseller_Auto_Discovery $instance = null;

    /**
     * Servicio de onboarding
     */
    private Dominios_Reseller_Onboarding_Worker $onboarding_worker;

    /**
     * Constructor
     */
    private function __construct() {
        $this->onboarding_worker = Dominios_Reseller_Onboarding_Worker::get_instance();
        $this->init_hooks();
    }

    /**
     * Obtener instancia singleton
     */
    public static function get_instance(): Dominios_Reseller_Auto_Discovery {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Inicializar hooks
     */
    private function init_hooks(): void {
        // Cron job para polling continuo
        add_action(self::CRON_HOOK, [$this, 'process_auto_discovery']);

        // Activar cron si no está activo
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'five_minutes', self::CRON_HOOK);
        }

        // NOTA DE SEGURIDAD: Webhooks de WHMCS SOLO via REST API con verificación de firma
        // NO usar wp_ajax_nopriv_ para webhooks externos - es una práctica insegura
        // El endpoint correcto es: /wp-json/dominios-reseller/v1/webhook/whmcs

        // REST API endpoint (con verificación de firma)
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    /**
     * Registrar rutas REST API
     */
    public function register_rest_routes(): void {
        register_rest_route('dominios-reseller/v1', '/webhook/whmcs', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_whmcs_webhook'],
            'permission_callback' => [$this, 'verify_webhook_signature'],
        ]);
    }

    /**
     * Verificar firma del webhook (seguridad)
     */
    public function verify_webhook_signature(WP_REST_Request $request): bool {
        $signature = $request->get_header('X-WHMCS-Signature');
        $secret = get_option('dr_whmcs_webhook_secret', '');

        if (empty($signature) || empty($secret)) {
            return false;
        }

        $payload = $request->get_body();
        $expected_signature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected_signature, $signature);
    }

    /**
     * Procesar auto-discovery (cron job)
     */
    public function process_auto_discovery(): void {
        try {
            $this->log("Iniciando auto-discovery de dominios...");

            $new_domains = [];

            // 1. Polling de WHMCS API
            $whmcs_domains = $this->poll_whmcs_api();
            $new_domains = array_merge($new_domains, $whmcs_domains);

            // 2. Escaneo de emails (si está habilitado)
            if (get_option('dr_email_scanning_enabled', false)) {
                $email_domains = $this->scan_support_emails();
                $new_domains = array_merge($new_domains, $email_domains);
            }

            // 3. Verificar dominios de Openprovider
            $openprovider_domains = $this->check_openprovider_orders();
            $new_domains = array_merge($new_domains, $openprovider_domains);

            // Filtrar dominios ya procesados
            $new_domains = $this->filter_processed_domains($new_domains);

            if (!empty($new_domains)) {
                $this->log("Encontrados " . count($new_domains) . " nuevos dominios para onboarding");

                foreach ($new_domains as $domain_data) {
                    $this->trigger_onboarding($domain_data);
                }

                // Marcar como procesados
                $this->mark_domains_processed($new_domains);
            } else {
                $this->log("No se encontraron nuevos dominios");
            }

        } catch (Exception $e) {
            $this->log("Error en auto-discovery: " . $e->getMessage(), 'error');
        }
    }

    /**
     * Polling de WHMCS API para nuevos dominios
     */
    private function poll_whmcs_api(): array {
        $domains = [];

        try {
            $api_url = get_option('dr_whmcs_api_url');
            $api_key = get_option('dr_whmcs_api_key');
            $api_secret = get_option('dr_whmcs_api_secret');

            if (empty($api_url) || empty($api_key)) {
                return $domains;
            }

            // Obtener órdenes recientes (últimas 24 horas)
            $response = wp_remote_post($api_url, [
                'body' => [
                    'action' => 'GetOrders',
                    'username' => $api_key,
                    'password' => $api_secret,
                    'status' => 'Active',
                    'orderby' => 'date',
                    'order' => 'desc',
                    'limitnum' => 50,
                ]
            ]);

            if (is_wp_error($response)) {
                throw new Exception("Error conectando a WHMCS API: " . $response->get_error_message());
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);

            if (isset($data['orders']['order'])) {
                foreach ($data['orders']['order'] as $order) {
                    if (isset($order['lineitems']['lineitem'])) {
                        foreach ($order['lineitems']['lineitem'] as $item) {
                            if ($item['type'] === 'Domain') {
                                $domains[] = [
                                    'domain' => $item['domain'],
                                    'client_id' => $order['userid'],
                                    'order_id' => $order['id'],
                                    'source' => 'whmcs_api',
                                    'order_date' => $order['date'],
                                    'status' => $order['status']
                                ];
                            }
                        }
                    }
                }
            }

        } catch (Exception $e) {
            $this->log("Error en polling WHMCS: " . $e->getMessage(), 'error');
        }

        return $domains;
    }

    /**
     * Escanear emails de soporte en busca de dominios
     */
    private function scan_support_emails(): array {
        $domains = [];

        try {
            $imap_server = get_option('dr_imap_server');
            $imap_user = get_option('dr_imap_user');
            $imap_pass = get_option('dr_imap_pass');

            if (empty($imap_server) || empty($imap_user)) {
                return $domains;
            }

            // Conectar a IMAP
            $mailbox = imap_open($imap_server, $imap_user, $imap_pass);

            if (!$mailbox) {
                throw new Exception("No se pudo conectar a IMAP");
            }

            // Buscar emails no leídos de las últimas 24 horas
            $search_criteria = 'UNSEEN SINCE "' . date('d-M-Y', strtotime('-1 day')) . '"';
            $emails = imap_search($mailbox, $search_criteria);

            if ($emails) {
                foreach ($emails as $email_id) {
                    $header = imap_headerinfo($mailbox, $email_id);
                    $body = imap_body($mailbox, $email_id);

                    // Extraer dominios del contenido del email
                    $found_domains = $this->extract_domains_from_text($body);

                    foreach ($found_domains as $domain) {
                        $domains[] = [
                            'domain' => $domain,
                            'source' => 'email_scan',
                            'email_subject' => $header->subject,
                            'email_date' => $header->date,
                            'client_email' => $header->from[0]->mailbox . '@' . $header->from[0]->host
                        ];
                    }
                }
            }

            imap_close($mailbox);

        } catch (Exception $e) {
            $this->log("Error escaneando emails: " . $e->getMessage(), 'error');
        }

        return $domains;
    }

    /**
     * Verificar órdenes pendientes en Openprovider
     */
    private function check_openprovider_orders(): array {
        $domains = [];

        try {
            // Implementar API call a Openprovider para órdenes pendientes
            // Similar a la implementación existente pero para detección automática

            $this->log("Openprovider auto-discovery no implementado aún", 'info');

        } catch (Exception $e) {
            $this->log("Error verificando Openprovider: " . $e->getMessage(), 'error');
        }

        return $domains;
    }

    /**
     * Manejar webhook de WHMCS (notificación instantánea)
     */
    public function handle_whmcs_webhook(WP_REST_Request $request): WP_REST_Response {
        try {
            $data = $request->get_json_params();

            $this->log("Webhook WHMCS recibido: " . json_encode($data));

            if (isset($data['action']) && $data['action'] === 'order_created') {
                $domains = [];

                // Extraer dominios de la orden
                if (isset($data['lineitems'])) {
                    foreach ($data['lineitems'] as $item) {
                        if ($item['type'] === 'Domain') {
                            $domains[] = [
                                'domain' => $item['domain'],
                                'client_id' => $data['userid'],
                                'order_id' => $data['orderid'],
                                'source' => 'whmcs_webhook',
                                'order_date' => $data['date'],
                                'status' => 'Active'
                            ];
                        }
                    }
                }

                // Procesar inmediatamente
                foreach ($domains as $domain_data) {
                    $this->trigger_onboarding($domain_data);
                    $this->mark_domain_processed($domain_data['domain']);
                }

                return new WP_REST_Response(['status' => 'success'], 200);
            }

            return new WP_REST_Response(['status' => 'ignored'], 200);

        } catch (Exception $e) {
            $this->log("Error procesando webhook: " . $e->getMessage(), 'error');
            return new WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Extraer dominios de texto usando regex
     */
    private function extract_domains_from_text(string $text): array {
        $domains = [];

        // Regex para encontrar dominios
        $pattern = '/(?:https?:\/\/)?(?:www\.)?([a-zA-Z0-9-]+\.[a-zA-Z]{2,})(?:\/[^\s]*)?/i';

        if (preg_match_all($pattern, $text, $matches)) {
            foreach ($matches[1] as $domain) {
                $domain = strtolower($domain);
                if (!in_array($domain, $domains)) {
                    $domains[] = $domain;
                }
            }
        }

        return $domains;
    }

    /**
     * Filtrar dominios ya procesados
     */
    private function filter_processed_domains(array $domains): array {
        $processed = get_transient(self::PROCESSED_DOMAINS_TRANSIENT) ?: [];

        return array_filter($domains, function($domain_data) use ($processed) {
            return !in_array($domain_data['domain'], $processed);
        });
    }

    /**
     * Marcar dominios como procesados
     */
    private function mark_domains_processed(array $domains): void {
        $processed = get_transient(self::PROCESSED_DOMAINS_TRANSIENT) ?: [];

        foreach ($domains as $domain_data) {
            $processed[] = $domain_data['domain'];
        }

        // Mantener solo los últimos 1000 dominios procesados
        $processed = array_slice($processed, -1000);

        set_transient(self::PROCESSED_DOMAINS_TRANSIENT, $processed, DAY_IN_SECONDS);
    }

    /**
     * Marcar dominio individual como procesado
     */
    private function mark_domain_processed(string $domain): void {
        $processed = get_transient(self::PROCESSED_DOMAINS_TRANSIENT) ?: [];
        $processed[] = $domain;
        $processed = array_slice($processed, -1000);
        set_transient(self::PROCESSED_DOMAINS_TRANSIENT, $processed, DAY_IN_SECONDS);
    }

    /**
     * Trigger onboarding para un dominio
     */
    private function trigger_onboarding(array $domain_data): void {
        try {
            $this->log("Triggering onboarding para: " . $domain_data['domain']);

            // Verificar que no esté ya en proceso
            $existing_state = Dominios_Reseller_Onboarding_DB::get_onboarding_state($domain_data['domain']);

            if ($existing_state && in_array(($existing_state['state'] ?? ''), ['onboarded', 'running', 'pending'])) {
                $this->log("Dominio ya en proceso/completado: " . $domain_data['domain']);
                return;
            }

            // Crear/actualizar entrada de onboarding (estado inicial)
            Dominios_Reseller_Onboarding_DB::upsert_onboarding(
                $domain_data['domain'],
                [
                    'state'      => 'pending',
                    'preset_key' => 'wp',
                    'meta'       => wp_json_encode([
                        'source'          => $domain_data['source']    ?? 'auto_discovery',
                        'client_id'       => $domain_data['client_id'] ?? null,
                        'order_id'        => $domain_data['order_id']  ?? null,
                        'order_date'      => $domain_data['order_date']?? current_time('mysql'),
                        'auto_discovered' => true,
                    ]),
                ]
            );

            // Encolar en el worker con la firma real (domain, preset, auto_ns)
            $this->onboarding_worker->enqueue($domain_data['domain'], 'wp', false);

            $this->log("Onboarding queued para: " . $domain_data['domain']);

        } catch (Exception $e) {
            $this->log("Error triggering onboarding para {$domain_data['domain']}: " . $e->getMessage(), 'error');
        }
    }

    /**
     * Logging unificado
     */
    private function log(string $message, string $level = 'info'): void {
        // Usar log_activity (existe en la BD); el método log() requiere run_id
        // que aquí no tenemos, y log_onboarding_event() nunca existió.
        if ( method_exists( 'Dominios_Reseller_Onboarding_DB', 'log_activity' ) ) {
            Dominios_Reseller_Onboarding_DB::log_activity(
                'auto_discovery',
                null,
                $message,
                [ 'level' => $level, 'component' => 'auto_discovery' ]
            );
            return;
        }
        // Fallback defensivo
        error_log( "[DR Auto-Discovery] [{$level}] {$message}" );
    }

    /**
     * Obtener métricas del sistema
     */
    public function get_metrics(): array {
        $processed = get_transient(self::PROCESSED_DOMAINS_TRANSIENT) ?: [];

        return [
            'domains_processed_today' => count(array_filter($processed, function($domain) {
                // Lógica para contar dominios de hoy
                return true; // Placeholder
            })),
            'last_check' => get_option('dr_auto_discovery_last_check', 'never'),
            'queue_length' => (int) ( $this->onboarding_worker->get_queue_status()['pending_count'] ?? 0 ),
            'sources_active' => [
                'whmcs_api' => !empty(get_option('dr_whmcs_api_url')),
                'email_scan' => get_option('dr_email_scanning_enabled', false),
                'webhook' => !empty(get_option('dr_whmcs_webhook_secret')),
                'openprovider' => false // Placeholder
            ]
        ];
    }
}

// Inicializar el sistema
add_action('plugins_loaded', function() {
    if (get_option('dr_auto_discovery_enabled', false)) {
        Dominios_Reseller_Auto_Discovery::get_instance();
    }
});