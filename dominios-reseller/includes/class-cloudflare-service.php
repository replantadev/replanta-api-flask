<?php
/**
 * Cloudflare Service Class
 * 
 * Gestiona la sincronización y consulta de zonas de Cloudflare
 * para marcar si los dominios están configurados en CF.
 * 
 * @package Dominios_Reseller
 * @version 1.0.0
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dominios_Reseller_Cloudflare_Service {

    /**
     * Nombre de la tabla de zonas CF
     */
    private string $table_name;

    /**
     * Nombre de la opción para el token
     */
    const OPTION_TOKEN = 'dominios_reseller_cf_token';
    
    /**
     * Nombre de la opción para email (usado con Global API Key)
     */
    const OPTION_EMAIL = 'dominios_reseller_cf_email';
    
    /**
     * Nombre de la opción para Global API Key
     */
    const OPTION_GLOBAL_KEY = 'dominios_reseller_cf_global_key';
    
    /**
     * Nombre de la opción para stats de sincronización
     */
    const OPTION_SYNC_STATS = 'dominios_reseller_cf_sync_stats';
    
    /**
     * Transient para cache del índice de zonas
     */
    const TRANSIENT_ZONES_INDEX = 'dr_cf_zones_index';
    
    /**
     * Transient para control de rate limit
     */
    const TRANSIENT_RATE_LIMIT = 'dr_cf_rate_limit';

    /**
     * Cloudflare API Base URL
     */
    const CF_API_URL = 'https://api.cloudflare.com/client/v4';

    /**
     * Instancia singleton
     */
    private static ?Dominios_Reseller_Cloudflare_Service $instance = null;

    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'dominios_reseller_cf_zones';
        
        // Auto-crear tabla si no existe
        $this->ensure_table_exists();
    }

    /**
     * Asegurar que la tabla existe
     */
    private function ensure_table_exists(): void {
        global $wpdb;
        
        // Verificar si la tabla existe
        $table_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $this->table_name
            )
        );
        
        if (!$table_exists) {
            error_log('[DR Cloudflare] Tabla ' . $this->table_name . ' no existe. Creándola...');
            self::create_table();
            error_log('[DR Cloudflare] Tabla creada.');
        }
    }

    /**
     * Obtener instancia singleton
     */
    public static function get_instance(): Dominios_Reseller_Cloudflare_Service {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Crear/actualizar tabla de zonas Cloudflare
     * Llamar en activación del plugin o upgrade
     */
    public static function create_table(): void {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dominios_reseller_cf_zones';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            zone_id varchar(32) NOT NULL,
            name varchar(255) NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'active',
            plan_name varchar(100) DEFAULT NULL,
            account_id varchar(32) DEFAULT NULL,
            account_name varchar(255) DEFAULT NULL,
            paused tinyint(1) NOT NULL DEFAULT 0,
            synced_at datetime NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            deleted_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uk_zone_id (zone_id),
            KEY idx_name (name),
            KEY idx_status (status),
            KEY idx_deleted (deleted_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Obtener el token de API guardado
     */
    public function get_token(): string {
        $options = get_option('dominios_reseller_options', []);
        return $options['cf_api_token'] ?? '';
    }

    /**
     * Obtener email de Cloudflare (para Global API Key)
     */
    public function get_email(): string {
        $options = get_option('dominios_reseller_options', []);
        return $options['cf_email'] ?? '';
    }

    /**
     * Obtener Global API Key
     */
    public function get_global_key(): string {
        $options = get_option('dominios_reseller_options', []);
        return $options['cf_global_key'] ?? '';
    }

    /**
     * Guardar el token de API
     */
    public function save_token(string $token): bool {
        $options = get_option('dominios_reseller_options', []);
        $options['cf_api_token'] = sanitize_text_field($token);
        return update_option('dominios_reseller_options', $options);
    }

    /**
     * Obtener headers de autenticación para API de Cloudflare
     * Soporta Bearer Token o Global API Key + Email
     * Prioridad: Global API Key > Bearer Token
     */
    private function get_auth_headers(): array {
        $token = $this->get_token();
        $email = $this->get_email();
        $global_key = $this->get_global_key();

        // Prioridad: API Token (Bearer Token) - método moderno y recomendado
        if (!empty($token)) {
            return [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
                'User-Agent'    => 'WordPress/Dominios-Reseller/1.5.7'
            ];
        }

        // Fallback: Global API Key (método legacy - mantener por compatibilidad)
        if (!empty($email) && !empty($global_key)) {
            return [
                'X-Auth-Email'  => $email,
                'X-Auth-Key'    => $global_key,
                'Content-Type'  => 'application/json',
                'User-Agent'    => 'WordPress/Dominios-Reseller/1.5.7'
            ];
        }

        return [];
    }

    /**
     * Verificar si hay rate limit activo
     */
    private function is_rate_limited(): bool {
        return (bool) get_transient(self::TRANSIENT_RATE_LIMIT);
    }

    /**
     * Activar rate limit (backoff)
     */
    private function set_rate_limit(int $seconds = 300): void {
        set_transient(self::TRANSIENT_RATE_LIMIT, time(), $seconds);
    }

    /**
     * Hacer petición GET a la API de Cloudflare
     * 
     * @param string $endpoint Endpoint relativo (sin base URL)
     * @param array $query_args Parámetros de query string
     * @return array|WP_Error
     */
    public function api_get(string $endpoint, array $query_args = []): array|WP_Error {
        $headers = $this->get_auth_headers();
        
        if (empty($headers)) {
            return new WP_Error('no_auth', 'Credenciales de Cloudflare no configuradas. Configura API Token o Global API Key + Email.');
        }

        if ($this->is_rate_limited()) {
            return new WP_Error('rate_limited', 'Cloudflare API en pausa por rate limit. Intenta más tarde.');
        }

        $url = self::CF_API_URL . $endpoint;
        if (!empty($query_args)) {
            $url = add_query_arg($query_args, $url);
        }

        $response = wp_remote_get($url, [
            'headers'   => $headers,
            'timeout'   => 12,
            'sslverify' => true
        ]);

        if (is_wp_error($response)) {
            error_log('[Dominios Reseller CF] API Error: ' . $response->get_error_message());
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Manejar rate limit de Cloudflare
        if ($status_code === 429) {
            $retry_after = wp_remote_retrieve_header($response, 'retry-after');
            $backoff = $retry_after ? intval($retry_after) : 300;
            $this->set_rate_limit($backoff);
            error_log('[Dominios Reseller CF] Rate limited. Backoff: ' . $backoff . 's');
            return new WP_Error('rate_limited', 'Rate limit de Cloudflare alcanzado. Backoff: ' . $backoff . 's');
        }

        if ($status_code !== 200) {
            $error_msg = $data['errors'][0]['message'] ?? 'Error desconocido';
            error_log('[Dominios Reseller CF] API Status ' . $status_code . ': ' . $error_msg);
            return new WP_Error('api_error', 'Error de API Cloudflare: ' . $error_msg, ['status' => $status_code]);
        }

        if (!isset($data['success']) || !$data['success']) {
            $error_msg = $data['errors'][0]['message'] ?? 'Respuesta inválida';
            return new WP_Error('api_error', $error_msg);
        }

        return $data;
    }

    /**
     * Obtener una página de zonas desde Cloudflare
     * 
     * @param int $page Número de página (1-based)
     * @param int $per_page Zonas por página (max 50)
     * @return array ['zones' => [], 'total_pages' => int, 'total_count' => int] | WP_Error
     */
    public function fetch_zones_page(int $page = 1, int $per_page = 50): array|WP_Error {
        $per_page = min($per_page, 50); // Cloudflare límite máximo

        $result = $this->api_get('/zones', [
            'page'     => $page,
            'per_page' => $per_page,
            'order'    => 'name',
            'direction'=> 'asc'
        ]);

        if (is_wp_error($result)) {
            return $result;
        }

        $zones = [];
        foreach ($result['result'] as $zone) {
            $zones[] = [
                'zone_id'      => $zone['id'],
                'name'         => strtolower($zone['name']),
                'status'       => $zone['status'],
                'plan_name'    => $zone['plan']['name'] ?? null,
                'account_id'   => $zone['account']['id'] ?? null,
                'account_name' => $zone['account']['name'] ?? null,
                'paused'       => $zone['paused'] ? 1 : 0
            ];
        }

        return [
            'zones'       => $zones,
            'total_pages' => $result['result_info']['total_pages'] ?? 1,
            'total_count' => $result['result_info']['total_count'] ?? count($zones),
            'page'        => $result['result_info']['page'] ?? $page
        ];
    }

    /**
     * Sincronizar todas las zonas desde Cloudflare a la base de datos local
     * Implementa upsert incremental y marcado de zonas eliminadas
     * 
     * @return array Stats de sincronización
     */
    public function sync_zones(): array {
        global $wpdb;
        
        $start_time = microtime(true);
        $stats = [
            'success'       => false,
            'zones_total'   => 0,
            'zones_added'   => 0,
            'zones_updated' => 0,
            'zones_deleted' => 0,
            'pages'         => 0,
            'duration'      => 0,
            'error'         => null,
            'synced_at'     => current_time('mysql')
        ];

        // Verificar rate limit
        if ($this->is_rate_limited()) {
            $stats['error'] = 'Sincronización pausada por rate limit de Cloudflare. Intenta más tarde.';
            $this->save_sync_stats($stats);
            return $stats;
        }

        // Obtener primera página para saber total
        $first_page = $this->fetch_zones_page(1, 50);
        
        if (is_wp_error($first_page)) {
            $stats['error'] = $first_page->get_error_message();
            $this->save_sync_stats($stats);
            return $stats;
        }

        $total_pages = $first_page['total_pages'];
        $stats['zones_total'] = $first_page['total_count'];
        $stats['pages'] = $total_pages;

        // Recopilar todas las zonas
        $all_zones = $first_page['zones'];

        // Obtener páginas restantes
        for ($page = 2; $page <= $total_pages; $page++) {
            $page_result = $this->fetch_zones_page($page, 50);
            
            if (is_wp_error($page_result)) {
                $stats['error'] = 'Error en página ' . $page . ': ' . $page_result->get_error_message();
                // Continuar con lo que tenemos
                break;
            }
            
            $all_zones = array_merge($all_zones, $page_result['zones']);
            
            // Pequeña pausa entre requests para no saturar
            usleep(100000); // 100ms
        }

        // Preparar lista de zone_ids actuales para comparar
        $current_zone_ids = array_column($all_zones, 'zone_id');
        $synced_at = current_time('mysql');

        // Upsert cada zona usando INSERT ... ON DUPLICATE KEY UPDATE
        foreach ($all_zones as $zone) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE zone_id = %s",
                $zone['zone_id']
            ));

            if ($existing) {
                // Update
                $wpdb->update(
                    $this->table_name,
                    [
                        'name'         => $zone['name'],
                        'status'       => $zone['status'],
                        'plan_name'    => $zone['plan_name'],
                        'account_id'   => $zone['account_id'],
                        'account_name' => $zone['account_name'],
                        'paused'       => $zone['paused'],
                        'synced_at'    => $synced_at,
                        'deleted_at'   => null // Restaurar si estaba marcada como eliminada
                    ],
                    ['zone_id' => $zone['zone_id']],
                    ['%s', '%s', '%s', '%s', '%s', '%d', '%s', null],
                    ['%s']
                );
                $stats['zones_updated']++;
            } else {
                // Insert
                $wpdb->insert(
                    $this->table_name,
                    [
                        'zone_id'      => $zone['zone_id'],
                        'name'         => $zone['name'],
                        'status'       => $zone['status'],
                        'plan_name'    => $zone['plan_name'],
                        'account_id'   => $zone['account_id'],
                        'account_name' => $zone['account_name'],
                        'paused'       => $zone['paused'],
                        'synced_at'    => $synced_at,
                        'created_at'   => $synced_at
                    ],
                    ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
                );
                $stats['zones_added']++;
            }
        }

        // Marcar como eliminadas las zonas que ya no existen en CF
        if (!empty($current_zone_ids)) {
            $placeholders = implode(',', array_fill(0, count($current_zone_ids), '%s'));
            $query = $wpdb->prepare(
                "UPDATE {$this->table_name} 
                 SET deleted_at = %s 
                 WHERE zone_id NOT IN ($placeholders) 
                 AND deleted_at IS NULL",
                array_merge([$synced_at], $current_zone_ids)
            );
            $deleted = $wpdb->query($query);
            $stats['zones_deleted'] = $deleted ?: 0;
        }

        // Limpiar cache del índice
        delete_transient(self::TRANSIENT_ZONES_INDEX);

        $stats['success'] = true;
        $stats['duration'] = round(microtime(true) - $start_time, 2);
        
        $this->save_sync_stats($stats);
        
        error_log('[Dominios Reseller CF] Sync completado: ' . json_encode($stats));
        
        return $stats;
    }

    /**
     * Guardar estadísticas de sincronización
     */
    private function save_sync_stats(array $stats): void {
        update_option(self::OPTION_SYNC_STATS, $stats);
    }

    /**
     * Obtener estadísticas de última sincronización
     */
    public function get_sync_stats(): array {
        return get_option(self::OPTION_SYNC_STATS, [
            'success'     => false,
            'zones_total' => 0,
            'synced_at'   => null,
            'error'       => 'Nunca sincronizado'
        ]);
    }

    /**
     * Obtener todas las zonas cacheadas desde la base de datos
     * 
     * @param bool $include_deleted Incluir zonas marcadas como eliminadas
     * @return array
     */
    public function get_cached_zones(bool $include_deleted = false): array {
        global $wpdb;
        
        $where = $include_deleted ? '' : 'WHERE deleted_at IS NULL';
        
        return $wpdb->get_results(
            "SELECT zone_id, name, status, plan_name, account_id, account_name, paused, synced_at 
             FROM {$this->table_name} 
             $where 
             ORDER BY name ASC",
            ARRAY_A
        ) ?: [];
    }

    /**
     * Obtener índice de zonas optimizado para matching
     * Cacheado en transient para rendimiento
     * 
     * Estructura retornada:
     * [
     *   'zones' => [
     *     'example.com' => ['zone_id' => '...', 'status' => '...', 'plan_name' => '...'],
     *     'subdomain.example.com' => [...],
     *   ],
     *   'sorted_names' => ['subdomain.example.com', 'example.com', ...] // Ordenados por longitud DESC
     *   'synced_at' => '2024-01-01 12:00:00'
     * ]
     * 
     * @return array
     */
    public function get_zones_index(): array {
        // Intentar obtener del transient
        $cached = get_transient(self::TRANSIENT_ZONES_INDEX);
        if ($cached !== false) {
            return $cached;
        }

        $zones = $this->get_cached_zones();
        
        if (empty($zones)) {
            return [
                'zones'        => [],
                'sorted_names' => [],
                'synced_at'    => null
            ];
        }

        $zones_map = [];
        $zone_names = [];
        $synced_at = null;

        foreach ($zones as $zone) {
            $name = strtolower(trim($zone['name'], '. '));
            $zones_map[$name] = [
                'zone_id'   => $zone['zone_id'],
                'status'    => $zone['status'],
                'plan_name' => $zone['plan_name'],
                'paused'    => $zone['paused']
            ];
            $zone_names[] = $name;
            
            if ($synced_at === null || $zone['synced_at'] > $synced_at) {
                $synced_at = $zone['synced_at'];
            }
        }

        // Ordenar por longitud descendente para match de sufijo correcto
        // Ej: "tienda.example.com" debe matchear antes que "example.com"
        usort($zone_names, function($a, $b) {
            return strlen($b) - strlen($a);
        });

        $index = [
            'zones'        => $zones_map,
            'sorted_names' => $zone_names,
            'synced_at'    => $synced_at
        ];

        // Cache por 1 hora (se invalida al sincronizar)
        set_transient(self::TRANSIENT_ZONES_INDEX, $index, HOUR_IN_SECONDS);

        return $index;
    }

    /**
     * Buscar si un dominio (primary_domain) está en Cloudflare
     * 
     * Matching logic:
     * 1. Normalizar dominio: lowercase, trim, quitar punto final
     * 2. Match exacto: example.com === example.com
     * 3. Match subdominio: tienda.example.com termina en .example.com
     * 
     * @param string $primary_domain El dominio principal a verificar
     * @param array|null $zones_index Índice de zonas (opcional, se obtiene automáticamente)
     * @return array|null ['zone_name' => '...', 'zone_id' => '...', 'status' => '...', 'plan_name' => '...', 'match_type' => 'exact'|'subdomain'] o null
     */
    public function match_domain_to_zone(string $primary_domain, ?array $zones_index = null): ?array {
        if (empty($primary_domain)) {
            return null;
        }

        if ($zones_index === null) {
            $zones_index = $this->get_zones_index();
        }

        if (empty($zones_index['zones'])) {
            return null;
        }

        // Normalizar dominio
        $domain = strtolower(trim($primary_domain, '. '));
        
        // Quitar www. si existe para normalizar
        if (str_starts_with($domain, 'www.')) {
            $domain = substr($domain, 4);
        }

        $zones = $zones_index['zones'];
        $sorted_names = $zones_index['sorted_names'];

        // 1. Match exacto primero
        if (isset($zones[$domain])) {
            return [
                'zone_name'  => $domain,
                'zone_id'    => $zones[$domain]['zone_id'],
                'status'     => $zones[$domain]['status'],
                'plan_name'  => $zones[$domain]['plan_name'],
                'paused'     => $zones[$domain]['paused'],
                'match_type' => 'exact'
            ];
        }

        // 2. Match de subdominio (iterar por longitud DESC)
        foreach ($sorted_names as $zone_name) {
            // Verificar si el dominio es subdominio de esta zona
            // domain = "tienda.example.com", zone_name = "example.com"
            // Debe terminar en ".example.com"
            $suffix = '.' . $zone_name;
            if (str_ends_with($domain, $suffix)) {
                return [
                    'zone_name'  => $zone_name,
                    'zone_id'    => $zones[$zone_name]['zone_id'],
                    'status'     => $zones[$zone_name]['status'],
                    'plan_name'  => $zones[$zone_name]['plan_name'],
                    'paused'     => $zones[$zone_name]['paused'],
                    'match_type' => 'subdomain'
                ];
            }
        }

        return null;
    }

    /**
     * Verificar estado de Cloudflare para múltiples dominios a la vez
     * Optimizado para uso en listados
     * 
     * @param array $primary_domains Array de primary_domain
     * @return array ['domain' => match_result|null, ...]
     */
    public function batch_match_domains(array $primary_domains): array {
        $zones_index = $this->get_zones_index();
        $results = [];

        foreach ($primary_domains as $domain) {
            $results[$domain] = $this->match_domain_to_zone($domain, $zones_index);
        }

        return $results;
    }

    /**
     * Verificar si hay datos de sincronización disponibles
     */
    public function has_sync_data(): bool {
        $stats = $this->get_sync_stats();
        return !empty($stats['synced_at']) && $stats['success'];
    }

    /**
     * Obtener conteo de zonas en la base de datos local
     */
    public function get_zones_count(): int {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE deleted_at IS NULL"
        );
    }

    /**
     * Verificar si el token es válido haciendo una petición de prueba
     */
    public function verify_token(): array {
        // Verificar qué tipo de autenticación se está usando
        $email = $this->get_email();
        $global_key = $this->get_global_key();
        $token = $this->get_token();
        
        // Si usa Global API Key, usar endpoint /user
        // Si usa Bearer Token, usar endpoint /user/tokens/verify
        if (!empty($email) && !empty($global_key)) {
            $result = $this->api_get('/user');
        } else if (!empty($token)) {
            $result = $this->api_get('/user/tokens/verify');
        } else {
            return [
                'valid' => false,
                'error' => 'No hay credenciales configuradas'
            ];
        }
        
        if (is_wp_error($result)) {
            return [
                'valid' => false,
                'error' => $result->get_error_message()
            ];
        }

        return [
            'valid'  => true,
            'status' => $result['result']['status'] ?? 'active'
        ];
    }

    /**
     * Limpiar todos los datos de Cloudflare (reset)
     */
    public function clear_all_data(): void {
        global $wpdb;
        
        $wpdb->query("TRUNCATE TABLE {$this->table_name}");
        delete_transient(self::TRANSIENT_ZONES_INDEX);
        delete_transient(self::TRANSIENT_RATE_LIMIT);
        delete_option(self::OPTION_SYNC_STATS);
    }

    // =========================================================================
    // FASE 2: Métodos para Onboarding (crear zonas, aplicar presets)
    // =========================================================================

    /**
     * Hacer petición POST a la API de Cloudflare
     * 
     * @param string $endpoint Endpoint relativo
     * @param array $body Datos a enviar
     * @return array|WP_Error
     */
    public function api_post(string $endpoint, array $body = []): array|WP_Error {
        $headers = $this->get_auth_headers();
        
        if (empty($headers)) {
            return new WP_Error('no_auth', 'Credenciales de Cloudflare no configuradas');
        }

        if ($this->is_rate_limited()) {
            return new WP_Error('rate_limited', 'Cloudflare API en pausa por rate limit. Intenta más tarde.');
        }

        $url = self::CF_API_URL . $endpoint;

        $response = wp_remote_post($url, [
            'headers'   => $headers,
            'body'      => json_encode($body),
            'timeout'   => 30,
            'sslverify' => true
        ]);

        return $this->handle_api_response($response);
    }

    /**
     * Hacer petición PATCH a la API de Cloudflare
     * 
     * @param string $endpoint Endpoint relativo
     * @param array $body Datos a enviar
     * @return array|WP_Error
     */
    public function api_patch(string $endpoint, array $body = []): array|WP_Error {
        $headers = $this->get_auth_headers();
        
        if (empty($headers)) {
            return new WP_Error('no_auth', 'Credenciales de Cloudflare no configuradas');
        }

        if ($this->is_rate_limited()) {
            return new WP_Error('rate_limited', 'Cloudflare API en pausa por rate limit. Intenta más tarde.');
        }

        $url = self::CF_API_URL . $endpoint;

        $response = wp_remote_request($url, [
            'method'    => 'PATCH',
            'headers'   => $headers,
            'body'      => json_encode($body),
            'timeout'   => 30,
            'sslverify' => true
        ]);

        return $this->handle_api_response($response);
    }

    /**
     * Hacer petición PUT a la API de Cloudflare
     * 
     * @param string $endpoint Endpoint relativo
     * @param array $body Datos a enviar
     * @return array|WP_Error
     */
    public function api_put(string $endpoint, array $body = []): array|WP_Error {
        $headers = $this->get_auth_headers();
        
        if (empty($headers)) {
            return new WP_Error('no_auth', 'Credenciales de Cloudflare no configuradas');
        }

        if ($this->is_rate_limited()) {
            return new WP_Error('rate_limited', 'Cloudflare API en pausa por rate limit. Intenta más tarde.');
        }

        $url = self::CF_API_URL . $endpoint;

        $response = wp_remote_request($url, [
            'method'    => 'PUT',
            'headers'   => $headers,
            'body'      => json_encode($body),
            'timeout'   => 30,
            'sslverify' => true
        ]);

        return $this->handle_api_response($response);
    }

    /**
     * Procesar respuesta de la API de Cloudflare
     */
    private function handle_api_response($response): array|WP_Error {
        if (is_wp_error($response)) {
            error_log('[Dominios Reseller CF] API Error: ' . $response->get_error_message());
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Manejar rate limit
        if ($status_code === 429) {
            $retry_after = wp_remote_retrieve_header($response, 'retry-after');
            $backoff = $retry_after ? intval($retry_after) : 300;
            $this->set_rate_limit($backoff);
            error_log('[Dominios Reseller CF] Rate limited. Backoff: ' . $backoff . 's');
            return new WP_Error('rate_limited', 'Rate limit de Cloudflare alcanzado. Backoff: ' . $backoff . 's');
        }

        if ($status_code < 200 || $status_code >= 300) {
            $error_msg = $data['errors'][0]['message'] ?? 'Error desconocido (HTTP ' . $status_code . ')';
            $error_code = $data['errors'][0]['code'] ?? $status_code;
            error_log('[Dominios Reseller CF] API Status ' . $status_code . ': ' . $error_msg);
            return new WP_Error('api_error_' . $error_code, $error_msg, ['status' => $status_code, 'response' => $data]);
        }

        if (!isset($data['success']) || !$data['success']) {
            $error_msg = $data['errors'][0]['message'] ?? 'Respuesta inválida';
            $error_code = $data['errors'][0]['code'] ?? 'unknown';
            return new WP_Error('api_error_' . $error_code, $error_msg, ['response' => $data]);
        }

        return $data;
    }

    /**
     * Obtener una zona por nombre (dominio)
     * 
     * @param string $domain Nombre del dominio
     * @return array|null Datos de la zona o null si no existe
     */
    public function get_zone(string $domain): ?array {
        $domain = strtolower(trim($domain, '. '));
        
        $result = $this->api_get('/zones', [
            'name' => $domain,
            'per_page' => 1
        ]);

        if (is_wp_error($result)) {
            error_log('[Dominios Reseller CF] Error buscando zona ' . $domain . ': ' . $result->get_error_message());
            return null;
        }

        if (empty($result['result'])) {
            return null;
        }

        $zone = $result['result'][0];
        return [
            'zone_id'      => $zone['id'],
            'name'         => $zone['name'],
            'status'       => $zone['status'],
            'name_servers' => $zone['name_servers'] ?? [],
            'plan_name'    => $zone['plan']['name'] ?? 'Free',
            'account_id'   => $zone['account']['id'] ?? null,
            'paused'       => $zone['paused'] ?? false
        ];
    }

    /**
     * Verificar que los NS reales de un dominio apuntan a Cloudflare
     *
     * Compara los nameservers actuales del dominio (DNS live) con los
     * nameservers asignados por Cloudflare. Evita falsos positivos como
     * adfc.com.co donde la zona existe en CF pero el dominio no migró.
     *
     * @param string $domain         Dominio a verificar
     * @param array  $expected_ns    NS asignados por CF (ej. ['aria.ns.cloudflare.com', 'seth.ns.cloudflare.com'])
     * @return array{verified: bool, actual_ns: string[], expected_ns: string[], message: string}
     */
    public function verify_domain_ns(string $domain, array $expected_ns): array {
        $domain = strtolower(trim($domain, '. '));
        $expected_ns = array_values(array_filter(array_map(
            fn($n) => strtolower(rtrim(trim((string) $n), '. ')),
            $expected_ns
        )));

        // Sin NS esperados no podemos verificar nada — devolver no-verificado.
        if (empty($expected_ns)) {
            return [
                'verified'    => false,
                'actual_ns'   => [],
                'expected_ns' => [],
                'message'     => "No se pudo verificar {$domain}: Cloudflare aún no ha asignado nameservers a la zona.",
            ];
        }

        // Intentar obtener los NS reales del dominio
        $actual_records = @dns_get_record($domain, DNS_NS);

        if ($actual_records === false || empty($actual_records)) {
            // Fallback: intentar con dig via shell si dns_get_record falla
            $actual_ns = $this->resolve_ns_fallback($domain);
            if (empty($actual_ns)) {
                return [
                    'verified'    => false,
                    'actual_ns'   => [],
                    'expected_ns' => $expected_ns,
                    'message'     => "No se pudieron resolver los NS de {$domain}. El dominio puede no existir o DNS no responde.",
                ];
            }
        } else {
            $actual_ns = array_map(fn($r) => strtolower(rtrim($r['target'] ?? '', '.')), $actual_records);
        }

        sort($actual_ns);
        sort($expected_ns);

        // Verificar que al menos un NS coincide con CF
        $matching = array_intersect($actual_ns, $expected_ns);

        // Verificación robusta: requiere al menos 2 coincidencias o (si CF asigna ≤2 NS) coincidencia total.
        $is_full_match = (count($expected_ns) <= 2)
            && (count($matching) === count($expected_ns))
            && (count($matching) > 0);

        if (count($matching) >= 2 || $is_full_match) {
            return [
                'verified'    => true,
                'actual_ns'   => $actual_ns,
                'expected_ns' => $expected_ns,
                'message'     => "NS verificados: {$domain} apunta a Cloudflare.",
            ];
        }

        return [
            'verified'    => false,
            'actual_ns'   => $actual_ns,
            'expected_ns' => $expected_ns,
            'message'     => "NS NO coinciden: {$domain} tiene [" . implode(', ', $actual_ns) . "] pero CF espera [" . implode(', ', $expected_ns) . "].",
        ];
    }

    /**
     * Pedir a Cloudflare que vuelva a comprobar los NS de una zona.
     *
     * Útil cuando una zona está en estado `moved` o `pending`: tras
     * actualizar los NS en el registrador, este endpoint le dice a CF
     * "vuelve a mirar ya, no esperes al ciclo automático". Si los NS
     * apuntan a CF, la zona pasará a `active`.
     *
     * @param string $zone_id ID de la zona Cloudflare.
     * @return array|WP_Error
     */
    public function trigger_zone_activation_check(string $zone_id): array|WP_Error {
        if (empty($zone_id)) {
            return new WP_Error('missing_zone_id', 'zone_id requerido');
        }
        // Cloudflare exige PUT con cuerpo vacío en /activation_check
        return $this->api_put('/zones/' . urlencode($zone_id) . '/activation_check', []);
    }

    /**
     * Fallback para resolver NS usando exec (cuando dns_get_record falla)
     */
    private function resolve_ns_fallback(string $domain): array {
        if (!function_exists('exec')) {
            return [];
        }

        $output = [];
        // Intentar nslookup (Windows + Linux)
        @exec("nslookup -type=NS {$domain} 8.8.8.8 2>&1", $output);

        $ns = [];
        foreach ($output as $line) {
            if (preg_match('/nameserver\s*=\s*(\S+)/i', $line, $m)) {
                $ns[] = strtolower(rtrim($m[1], '.'));
            }
        }

        return array_unique($ns);
    }

    /**
     * Crear una nueva zona en Cloudflare
     * 
     * @param string $domain Nombre del dominio
     * @param bool $jump_start Activar jump_start (escaneo DNS automático)
     * @return array|WP_Error Datos de la zona creada
     */
    public function create_zone(string $domain, bool $jump_start = false): array|WP_Error {
        $domain = strtolower(trim($domain, '. '));
        
        // Log de autenticación para debug
        $email = $this->get_email();
        $has_token = !empty($this->get_token());
        $has_global = !empty($this->get_global_key());
        error_log('[DR CF] Intentando crear zona: ' . $domain);
        error_log('[DR CF] Auth disponible: Token=' . ($has_token ? 'yes' : 'no') . ', Global=' . ($has_global ? 'yes' : 'no') . ', Email=' . ($email ?: 'empty'));
        
        // Verificar qué autenticación se usará realmente (según prioridad)
        if ($has_token) {
            error_log('[DR CF] Usando: Bearer Token (prioridad - método recomendado)');
        } else if (!empty($email) && !empty($this->get_global_key())) {
            error_log('[DR CF] Usando: Global API Key + Email (fallback legacy)');
        } else {
            error_log('[DR CF] ERROR: No hay credenciales válidas');
            return new WP_Error('no_auth', 'No hay credenciales de Cloudflare configuradas');
        }
        
        // Verificar si ya existe primero (idempotencia)
        $existing = $this->get_zone($domain);
        if ($existing) {
            error_log('[Dominios Reseller CF] Zona ya existe: ' . $domain . ' (zone_id: ' . $existing['zone_id'] . ')');
            return [
                'zone_id'      => $existing['zone_id'],
                'name'         => $existing['name'],
                'status'       => $existing['status'],
                'name_servers' => $existing['name_servers'],
                'plan_name'    => $existing['plan_name'],
                'already_existed' => true
            ];
        }

        // Crear zona sin especificar account_id (Cloudflare usará cuenta por defecto)
        // Esto evita errores de permisos en cuentas con restricciones
        $body = [
            'name'       => $domain,
            'jump_start' => $jump_start,
            'type'       => 'full' // full = cambio de NS, partial = CNAME setup
        ];

        error_log('[DR CF] Body de creación de zona: ' . json_encode($body));
        $result = $this->api_post('/zones', $body);

        if (is_wp_error($result)) {
            error_log('[DR CF] Error creando zona: ' . $result->get_error_message());
            return $result;
        }

        $zone = $result['result'];
        error_log('[DR CF] Zona creada exitosamente: ' . $zone['id'] . ' para ' . $zone['name']);
        
        // Sincronizar a DB local
        $this->upsert_zone_to_db([
            'zone_id'      => $zone['id'],
            'name'         => $zone['name'],
            'status'       => $zone['status'],
            'plan_name'    => $zone['plan']['name'] ?? 'Free',
            'account_id'   => $zone['account']['id'] ?? null,
            'account_name' => $zone['account']['name'] ?? null,
            'paused'       => $zone['paused'] ? 1 : 0
        ]);

        // Invalidar cache
        delete_transient(self::TRANSIENT_ZONES_INDEX);

        return [
            'zone_id'        => $zone['id'],
            'name'           => $zone['name'],
            'status'         => $zone['status'],
            'name_servers'   => $zone['name_servers'] ?? [],
            'plan_name'      => $zone['plan']['name'] ?? 'Free',
            'already_existed'=> false
        ];
    }

    /**
     * Obtener el account_id de la cuenta Cloudflare
     */
    private function get_account_id(): string|WP_Error {
        // Cache en transient
        $cached = get_transient('dr_cf_account_id');
        if ($cached) {
            return $cached;
        }

        $result = $this->api_get('/accounts', ['per_page' => 1]);
        
        if (is_wp_error($result)) {
            return $result;
        }

        if (empty($result['result'])) {
            return new WP_Error('no_account', 'No se encontró cuenta Cloudflare asociada al token');
        }

        $account_id = $result['result'][0]['id'];
        set_transient('dr_cf_account_id', $account_id, DAY_IN_SECONDS);
        
        return $account_id;
    }

    /**
     * Obtener los nameservers de una zona
     * 
     * @param string $zone_id ID de la zona
     * @return array|WP_Error Array de nameservers
     */
    public function get_zone_nameservers(string $zone_id): array|WP_Error {
        $result = $this->api_get('/zones/' . $zone_id);

        if (is_wp_error($result)) {
            return $result;
        }

        return $result['result']['name_servers'] ?? [];
    }

    /**
     * Obtener el estado de una zona por zone_id
     * 
     * @param string $zone_id ID de la zona en Cloudflare
     * @return array|WP_Error Array con status, name_servers, etc. o WP_Error
     */
    public function get_zone_status(string $zone_id): array|WP_Error {
        $result = $this->api_get('/zones/' . $zone_id);

        if (is_wp_error($result)) {
            return $result;
        }

        if (empty($result['result'])) {
            return new WP_Error('zone_not_found', 'Zona no encontrada: ' . $zone_id);
        }

        $zone = $result['result'];
        return [
            'zone_id'      => $zone['id'],
            'name'         => $zone['name'],
            'status'       => $zone['status'], // active, pending, initializing, moved, deleted
            'name_servers' => $zone['name_servers'] ?? [],
            'paused'       => $zone['paused'] ?? false,
            'plan_name'    => $zone['plan']['name'] ?? 'Free',
        ];
    }

    /**
     * Obtener configuración COMPLETA de una zona (para el modal)
     * 
     * Incluye: settings, cache rules, firewall rules, SSL mode, etc.
     * 
     * @param string $zone_id ID de la zona
     * @return array|WP_Error
     */
    public function get_zone_full_config(string $zone_id): array|WP_Error {
        $config = [
            'zone_id' => $zone_id,
            'settings' => [],
            'ssl_mode' => 'unknown',
            'cache_rules' => [],
            'firewall_rules' => [],
            'page_rules' => [],
            'nameservers' => [],
            'status' => 'unknown',
            'plan' => 'free',
            'fetched_at' => current_time('mysql'),
        ];
        
        // 1. Obtener info básica de la zona
        $zone_info = $this->api_get('/zones/' . $zone_id);
        if (!is_wp_error($zone_info) && isset($zone_info['result'])) {
            $zone = $zone_info['result'];
            $config['status'] = $zone['status'] ?? 'unknown';
            $config['plan'] = $zone['plan']['name'] ?? 'Free';
            $config['nameservers'] = $zone['name_servers'] ?? [];
            $config['paused'] = $zone['paused'] ?? false;
        }
        
        // 2. Obtener todos los settings de la zona
        $settings = $this->get_zone_settings($zone_id);
        if (!is_wp_error($settings)) {
            // Filtrar solo los settings importantes para mostrar
            $important_settings = [
                'ssl', 'always_use_https', 'min_tls_version', 'automatic_https_rewrites',
                'http3', 'brotli', '0rtt', 'early_hints', 'rocket_loader',
                'security_level', 'challenge_ttl', 'browser_check',
                'browser_cache_ttl', 'cache_level', 'development_mode', 'always_online',
                'email_obfuscation', 'hotlink_protection', 'ip_geolocation',
                'minify', 'polish', 'mirage', 'webp'
            ];
            
            foreach ($important_settings as $key) {
                if (isset($settings[$key])) {
                    $config['settings'][$key] = $settings[$key];
                }
            }
            $config['ssl_mode'] = $settings['ssl'] ?? 'unknown';
        }
        
        // 3. Obtener Cache Rules (Rulesets)
        $cache_rules = $this->get_cache_rules($zone_id);
        if (!is_wp_error($cache_rules) && is_array($cache_rules)) {
            $config['cache_rules'] = array_map(function($rule) {
                return [
                    'id' => $rule['id'] ?? '',
                    'name' => $rule['description'] ?? $rule['name'] ?? 'Sin nombre',
                    'expression' => $rule['expression'] ?? '',
                    'enabled' => $rule['enabled'] ?? true,
                    'action' => $rule['action'] ?? 'unknown',
                ];
            }, $cache_rules);
        }
        
        // 4. Obtener Firewall Rules (WAF Custom Rules)
        $firewall_rules = $this->api_get('/zones/' . $zone_id . '/firewall/rules');
        if (!is_wp_error($firewall_rules) && isset($firewall_rules['result'])) {
            $config['firewall_rules'] = array_map(function($rule) {
                return [
                    'id' => $rule['id'] ?? '',
                    'description' => $rule['description'] ?? 'Sin descripción',
                    'expression' => $rule['filter']['expression'] ?? '',
                    'action' => $rule['action'] ?? 'unknown',
                    'paused' => $rule['paused'] ?? false,
                ];
            }, $firewall_rules['result']);
        }
        
        // 5. Obtener Page Rules (legacy pero aún usadas)
        $page_rules = $this->api_get('/zones/' . $zone_id . '/pagerules');
        if (!is_wp_error($page_rules) && isset($page_rules['result'])) {
            $config['page_rules'] = array_map(function($rule) {
                $actions = [];
                foreach ($rule['actions'] as $action) {
                    $actions[] = $action['id'] . ': ' . json_encode($action['value'] ?? true);
                }
                return [
                    'id' => $rule['id'] ?? '',
                    'targets' => implode(', ', array_column($rule['targets'] ?? [], 'constraint')['value'] ?? []),
                    'actions' => implode(', ', $actions),
                    'status' => $rule['status'] ?? 'unknown',
                    'priority' => $rule['priority'] ?? 0,
                ];
            }, $page_rules['result']);
        }
        
        return $config;
    }

    /**
     * Obtener settings actuales de una zona
     * 
     * @param string $zone_id ID de la zona
     * @param array $setting_names Lista de settings a obtener (vacío = todos)
     * @return array|WP_Error
     */
    public function get_zone_settings(string $zone_id, array $setting_names = []): array|WP_Error {
        $result = $this->api_get('/zones/' . $zone_id . '/settings');

        if (is_wp_error($result)) {
            return $result;
        }

        $settings = [];
        foreach ($result['result'] as $setting) {
            if (empty($setting_names) || in_array($setting['id'], $setting_names)) {
                $settings[$setting['id']] = $setting['value'];
            }
        }

        return $settings;
    }

    /**
     * Aplicar un setting individual a una zona
     * 
     * @param string $zone_id ID de la zona
     * @param string $setting_name Nombre del setting
     * @param mixed $value Valor a aplicar
     * @return array|WP_Error
     */
    public function set_zone_setting(string $zone_id, string $setting_name, $value): array|WP_Error {
        return $this->api_patch('/zones/' . $zone_id . '/settings/' . $setting_name, [
            'value' => $value
        ]);
    }

    /**
     * Aplicar preset completo a una zona (idempotente)
     * 
     * @param string $zone_id ID de la zona
     * @param array $preset_payload Payload del preset con settings y reglas
     * @return array Resultado con éxitos y errores parciales
     */
    public function apply_preset(string $zone_id, array $preset_payload): array {
        $results = [
            'success'         => true,
            'partial'         => false,
            'settings_applied'=> [],
            'settings_skipped'=> [],
            'settings_failed' => [],
            'rules_applied'   => [],
            'rules_skipped'   => [],
            'rules_failed'    => [],
            'ai_crawlers'     => [],
            'bot_management'  => [],
            'security_headers'=> [],
            'firewall_rules'  => [],
            'errors'          => []
        ];

        // 1. Aplicar settings básicos
        if (!empty($preset_payload['settings'])) {
            $this->apply_settings($zone_id, $preset_payload['settings'], $results);
        }

        // 2. Aplicar Cache Rules
        if (!empty($preset_payload['cache_rules'])) {
            $this->apply_cache_rules_to_results($zone_id, $preset_payload['cache_rules'], $results);
        }

        // 3. Aplicar AI Crawl Control
        if (!empty($preset_payload['ai_crawlers']) && $preset_payload['ai_crawlers']['enabled'] ?? false) {
            $this->apply_ai_crawlers($zone_id, $preset_payload['ai_crawlers'], $results);
        }

        // 4. Aplicar Bot Management
        if (!empty($preset_payload['bot_management']) && $preset_payload['bot_management']['enabled'] ?? false) {
            $this->apply_bot_management($zone_id, $preset_payload['bot_management'], $results);
        }

        // 5. Aplicar Security Headers (Transform Rules)
        if (!empty($preset_payload['security_headers']) && $preset_payload['security_headers']['enabled'] ?? false) {
            $this->apply_security_headers($zone_id, $preset_payload['security_headers'], $results);
        }

        // 6. Aplicar Firewall Rules
        if (!empty($preset_payload['firewall_rules']) && $preset_payload['firewall_rules']['enabled'] ?? false) {
            $this->apply_firewall_rules($zone_id, $preset_payload['firewall_rules'], $results);
        }

        // Determinar éxito general
        $results['success'] = empty($results['settings_failed']) && empty($results['rules_failed']);

        return $results;
    }

    /**
     * Aplicar settings básicos a una zona
     */
    private function apply_settings(string $zone_id, array $settings, array &$results): void {
        // Obtener settings actuales para comparar (idempotencia)
        $current_settings = $this->get_zone_settings($zone_id, array_keys($settings));
        
        if (is_wp_error($current_settings)) {
            $results['errors'][] = 'No se pudieron leer settings actuales: ' . $current_settings->get_error_message();
            $current_settings = [];
        }

        // Settings que solo están disponibles en planes Pro/Business/Enterprise
        // o que pueden no estar disponibles en todas las zonas
        $pro_only_settings = [
            'polish', 'mirage', 'image_resizing', 'prefetch_preload',
            '0rtt', 'h2_prioritization', 'webp',
            'origin_error_page_pass_thru', 'true_client_ip_header',
        ];

        // Settings deprecated by CF — skip silently
        $deprecated_settings = ['minify', 'server_side_exclude', 'response_buffering', 'prefetch_preload'];

        foreach ($settings as $setting_name => $desired_value) {
            // Skip deprecated settings silently
            if (in_array($setting_name, $deprecated_settings)) {
                $results['settings_skipped'][] = $setting_name . ' (deprecated by CF)';
                continue;
            }

            // Verificar si ya tiene el valor correcto (idempotencia)
            if (isset($current_settings[$setting_name]) && $current_settings[$setting_name] === $desired_value) {
                $results['settings_skipped'][] = $setting_name;
                continue;
            }

            $apply_result = $this->set_zone_setting($zone_id, $setting_name, $desired_value);
            
            if (is_wp_error($apply_result)) {
                $error_msg = $apply_result->get_error_message();
                
                // Si el error es por plan (Free no soporta) o setting no disponible, registrar como degradación no crítica
                if (in_array($setting_name, $pro_only_settings) || 
                    stripos($error_msg, 'not available') !== false ||
                    stripos($error_msg, 'upgrade') !== false ||
                    stripos($error_msg, 'entitlement') !== false ||
                    stripos($error_msg, 'requires a Pro') !== false ||
                    stripos($error_msg, 'not found') !== false ||
                    stripos($error_msg, 'unknown setting') !== false ||
                    stripos($error_msg, 'invalid setting') !== false ||
                    stripos($error_msg, 'deprecated') !== false ||
                    stripos($error_msg, 'not allowed') !== false) {
                    $results['settings_skipped'][] = $setting_name . ' (no disponible en este plan/zona)';
                } else {
                    $results['settings_failed'][] = $setting_name;
                    $results['errors'][] = "Setting '$setting_name': " . $error_msg;
                    $results['partial'] = true;
                }
            } else {
                $results['settings_applied'][] = $setting_name;
            }

            usleep(100000); // 100ms
        }
    }

    /**
     * Aplicar Cache Rules y añadir a results
     */
    private function apply_cache_rules_to_results(string $zone_id, array $rules, array &$results): void {
        $rules_result = $this->apply_cache_rules($zone_id, $rules);
        $results['rules_applied'] = $rules_result['applied'];
        $results['rules_skipped'] = $rules_result['skipped'];
        $results['rules_failed'] = $rules_result['failed'];
        $results['errors'] = array_merge($results['errors'], $rules_result['errors']);
        if (!empty($rules_result['failed'])) {
            $results['partial'] = true;
        }
    }

    /**
     * Aplicar AI Crawl Control
     * 
     * Configura qué bots de IA pueden acceder al contenido.
     * Usa Cloudflare Firewall Rules para bloquear/permitir.
     */
    private function apply_ai_crawlers(string $zone_id, array $config, array &$results): void {
        $bots = $config['bots'] ?? [];
        $blocked_bots = [];
        
        foreach ($bots as $bot => $action) {
            if ($action === 'block') {
                $blocked_bots[] = $bot;
            }
        }
        
        if (empty($blocked_bots)) {
            $results['ai_crawlers'][] = 'No bots to block';
            return;
        }

        // Construir expresión para bloquear bots de IA
        $bot_patterns = array_map(function($bot) {
            return '(http.user_agent contains "' . $bot . '")';
        }, $blocked_bots);
        
        $expression = implode(' or ', $bot_patterns);
        
        // Crear regla de firewall
        $rule_result = $this->create_firewall_rule($zone_id, [
            'name'       => 'AI Crawlers Block (Dominios Reseller)',
            'expression' => $expression,
            'action'     => 'block'
        ]);

        if (is_wp_error($rule_result)) {
            $results['errors'][] = 'AI Crawlers: ' . $rule_result->get_error_message();
            $results['partial'] = true;
        } else {
            $results['ai_crawlers'][] = 'Blocked: ' . implode(', ', $blocked_bots);
        }
    }

    /**
     * Aplicar Bot Management (Super Bot Fight Mode)
     */
    private function apply_bot_management(string $zone_id, array $config, array &$results): void {
        $fight_mode = $config['fight_mode'] ?? 'medium';
        
        // Mapear fight_mode a setting de Cloudflare
        $sbfm_settings = match($fight_mode) {
            'off'    => ['sbfm_definitely_automated' => 'allow', 'sbfm_likely_automated' => 'allow'],
            'low'    => ['sbfm_definitely_automated' => 'managed_challenge', 'sbfm_likely_automated' => 'allow'],
            'medium' => ['sbfm_definitely_automated' => 'managed_challenge', 'sbfm_likely_automated' => 'managed_challenge'],
            'high'   => ['sbfm_definitely_automated' => 'block', 'sbfm_likely_automated' => 'managed_challenge'],
            default  => ['sbfm_definitely_automated' => 'managed_challenge', 'sbfm_likely_automated' => 'allow']
        };

        // Aplicar Super Bot Fight Mode via API
        $result = $this->api_put('/zones/' . $zone_id . '/bot_management', [
            'fight_mode'              => true,
            'sbfm_definitely_automated' => $sbfm_settings['sbfm_definitely_automated'],
            'sbfm_likely_automated'     => $sbfm_settings['sbfm_likely_automated'],
            'sbfm_static_resource_protection' => $config['static_resource_protection'] ?? false,
            'enable_js'               => $config['js_challenge_bad_bots'] ?? true
        ]);

        if (is_wp_error($result)) {
            // Bot Management puede no estar disponible en Free
            if (strpos($result->get_error_message(), 'not available') !== false) {
                $results['bot_management'][] = 'Skipped (requiere plan superior)';
            } else {
                $results['errors'][] = 'Bot Management: ' . $result->get_error_message();
                $results['partial'] = true;
            }
        } else {
            $results['bot_management'][] = "Fight mode: $fight_mode";
        }
    }

    /**
     * Aplicar Security Headers (Transform Rules)
     */
    private function apply_security_headers(string $zone_id, array $config, array &$results): void {
        $headers = $config['headers'] ?? [];
        
        if (empty($headers)) {
            return;
        }

        // Obtener o crear ruleset de Transform Rules
        $ruleset_id = $this->get_or_create_transform_ruleset($zone_id);
        
        if (is_wp_error($ruleset_id)) {
            $results['errors'][] = 'Security Headers: ' . $ruleset_id->get_error_message();
            $results['partial'] = true;
            return;
        }

        // Construir action_parameters para los headers
        $header_actions = [];
        foreach ($headers as $header_name => $header_value) {
            $header_actions[] = [
                'operation' => 'set',
                'header'    => [
                    'name'  => $header_name,
                    'value' => $header_value
                ]
            ];
        }

        // Crear regla de Transform
        $rule = [
            'expression'  => 'true', // Aplicar a todas las respuestas
            'description' => 'Security Headers (Dominios Reseller)',
            'action'      => 'rewrite',
            'action_parameters' => [
                'headers' => $header_actions
            ]
        ];

        $result = $this->api_post('/zones/' . $zone_id . '/rulesets/' . $ruleset_id . '/rules', $rule);

        if (is_wp_error($result)) {
            $results['errors'][] = 'Security Headers: ' . $result->get_error_message();
            $results['partial'] = true;
        } else {
            $results['security_headers'] = array_keys($headers);
        }
    }

    /**
     * Obtener o crear Transform Ruleset para Response Headers
     */
    private function get_or_create_transform_ruleset(string $zone_id): string|WP_Error {
        $result = $this->api_get('/zones/' . $zone_id . '/rulesets');
        
        if (!is_wp_error($result)) {
            foreach ($result['result'] as $ruleset) {
                if (($ruleset['phase'] ?? '') === 'http_response_headers_transform') {
                    return $ruleset['id'];
                }
            }
        }

        // Crear nuevo ruleset
        $create_result = $this->api_post('/zones/' . $zone_id . '/rulesets', [
            'name'        => 'Response Headers (Dominios Reseller)',
            'description' => 'Transform rules para headers de seguridad',
            'kind'        => 'zone',
            'phase'       => 'http_response_headers_transform',
            'rules'       => []
        ]);

        if (is_wp_error($create_result)) {
            return $create_result;
        }

        return $create_result['result']['id'];
    }

    /**
     * Aplicar Firewall Rules
     */
    private function apply_firewall_rules(string $zone_id, array $config, array &$results): void {
        $rules = $config['rules'] ?? [];
        
        foreach ($rules as $rule) {
            if (!($rule['enabled'] ?? true)) {
                $results['firewall_rules'][] = $rule['name'] . ' (skipped - disabled)';
                continue;
            }

            $result = $this->create_firewall_rule($zone_id, [
                'name'       => $rule['name'] . ' (DR)',
                'expression' => $rule['expression'],
                'action'     => $rule['action']
            ]);

            if (is_wp_error($result)) {
                $error_msg = $result->get_error_message();
                
                // Si ya existe una regla similar, saltar
                if (strpos($error_msg, 'already exists') !== false || 
                    strpos($error_msg, 'duplicate') !== false) {
                    $results['firewall_rules'][] = $rule['name'] . ' (ya existe)';
                } else {
                    $results['errors'][] = "Firewall '{$rule['name']}': $error_msg";
                    $results['partial'] = true;
                }
            } else {
                $results['firewall_rules'][] = $rule['name'];
            }

            usleep(200000); // 200ms entre reglas
        }
    }

    /**
     * Crear una Firewall Rule individual
     */
    private function create_firewall_rule(string $zone_id, array $rule): array|WP_Error {
        // Intentar crear usando Custom Firewall Rules (Rulesets API)
        $ruleset_id = $this->get_or_create_firewall_ruleset($zone_id);
        
        if (is_wp_error($ruleset_id)) {
            return $ruleset_id;
        }

        $cf_rule = [
            'expression'  => $rule['expression'],
            'description' => $rule['name'],
            'action'      => $this->map_firewall_action($rule['action'])
        ];

        return $this->api_post('/zones/' . $zone_id . '/rulesets/' . $ruleset_id . '/rules', $cf_rule);
    }

    /**
     * Obtener o crear Firewall Ruleset
     */
    private function get_or_create_firewall_ruleset(string $zone_id): string|WP_Error {
        $result = $this->api_get('/zones/' . $zone_id . '/rulesets');
        
        if (!is_wp_error($result)) {
            foreach ($result['result'] as $ruleset) {
                if (($ruleset['phase'] ?? '') === 'http_request_firewall_custom') {
                    return $ruleset['id'];
                }
            }
        }

        // Crear nuevo ruleset de firewall custom
        $create_result = $this->api_post('/zones/' . $zone_id . '/rulesets', [
            'name'        => 'Custom Firewall (Dominios Reseller)',
            'description' => 'Firewall rules creadas por Dominios Reseller',
            'kind'        => 'zone',
            'phase'       => 'http_request_firewall_custom',
            'rules'       => []
        ]);

        if (is_wp_error($create_result)) {
            return $create_result;
        }

        return $create_result['result']['id'];
    }

    /**
     * Mapear acciones de firewall a formato CF
     */
    private function map_firewall_action(string $action): string {
        return match($action) {
            'block'     => 'block',
            'challenge' => 'managed_challenge',
            'js_challenge' => 'js_challenge',
            'allow'     => 'skip',
            'log'       => 'log',
            default     => 'managed_challenge'
        };
    }

    /**
     * Aplicar Cache Rules a una zona
     * 
     * @param string $zone_id ID de la zona
     * @param array $rules Array de reglas a aplicar
     * @return array
     */
    private function apply_cache_rules(string $zone_id, array $rules): array {
        $results = [
            'applied' => [],
            'skipped' => [],
            'failed'  => [],
            'errors'  => []
        ];

        // Obtener reglas existentes
        $existing_rules = $this->get_cache_rules($zone_id);
        $existing_names = [];
        
        if (!is_wp_error($existing_rules)) {
            foreach ($existing_rules as $rule) {
                $existing_names[$rule['description'] ?? ''] = $rule['id'];
            }
        }

        foreach ($rules as $rule) {
            $rule_name = $rule['name'] ?? 'Unnamed rule';
            
            // Verificar si ya existe una regla con ese nombre (idempotencia)
            if (isset($existing_names[$rule_name])) {
                $results['skipped'][] = $rule_name;
                continue;
            }

            // Intentar crear la regla usando Rulesets API (moderno)
            $create_result = $this->create_cache_rule($zone_id, $rule);
            
            if (is_wp_error($create_result)) {
                // Si falla por plan (Free no soporta algunas reglas), registrar como degradación
                $error_msg = $create_result->get_error_message();
                if (strpos($error_msg, 'not available') !== false || 
                    strpos($error_msg, 'upgrade') !== false ||
                    strpos($error_msg, 'entitlement') !== false) {
                    $results['errors'][] = "Regla '$rule_name' no disponible en plan Free (degradación)";
                } else {
                    $results['errors'][] = "Regla '$rule_name': $error_msg";
                }
                $results['failed'][] = $rule_name;
            } else {
                $results['applied'][] = $rule_name;
            }

            usleep(200000); // 200ms entre reglas
        }

        return $results;
    }

    /**
     * Obtener cache rules existentes de una zona
     */
    private function get_cache_rules(string $zone_id): array|WP_Error {
        // Intentar obtener del Ruleset de cache
        $result = $this->api_get('/zones/' . $zone_id . '/rulesets', [
            'phase' => 'http_request_cache_settings'
        ]);

        if (is_wp_error($result)) {
            return $result;
        }

        // Buscar el ruleset de cache
        foreach ($result['result'] as $ruleset) {
            if (($ruleset['phase'] ?? '') === 'http_request_cache_settings') {
                return $ruleset['rules'] ?? [];
            }
        }

        return [];
    }

    /**
     * Crear una cache rule individual
     */
    private function create_cache_rule(string $zone_id, array $rule): array|WP_Error {
        // Primero obtener o crear el ruleset de cache
        $ruleset_id = $this->get_or_create_cache_ruleset($zone_id);
        
        if (is_wp_error($ruleset_id)) {
            return $ruleset_id;
        }

        // Construir la regla según el formato de CF Rulesets
        $cf_rule = [
            'expression'  => $rule['if'] ?? $rule['expression'] ?? '',
            'description' => $rule['name'] ?? '',
            'action'      => 'set_cache_settings',
            'action_parameters' => $this->build_cache_action_parameters($rule['action'] ?? 'bypass')
        ];

        // Añadir regla al ruleset
        return $this->api_post('/zones/' . $zone_id . '/rulesets/' . $ruleset_id . '/rules', $cf_rule);
    }

    /**
     * Obtener o crear el ruleset de cache para una zona
     */
    private function get_or_create_cache_ruleset(string $zone_id): string|WP_Error {
        // Buscar ruleset existente
        $result = $this->api_get('/zones/' . $zone_id . '/rulesets');
        
        if (!is_wp_error($result)) {
            foreach ($result['result'] as $ruleset) {
                if (($ruleset['phase'] ?? '') === 'http_request_cache_settings') {
                    return $ruleset['id'];
                }
            }
        }

        // Crear nuevo ruleset
        $create_result = $this->api_post('/zones/' . $zone_id . '/rulesets', [
            'name'        => 'Cache Rules (Dominios Reseller)',
            'description' => 'Reglas de cache creadas por Dominios Reseller',
            'kind'        => 'zone',
            'phase'       => 'http_request_cache_settings',
            'rules'       => []
        ]);

        if (is_wp_error($create_result)) {
            return $create_result;
        }

        return $create_result['result']['id'];
    }

    /**
     * Construir parámetros de acción para cache rules
     */
    private function build_cache_action_parameters(string $action): array {
        return match($action) {
            'bypass' => [
                'cache' => false
            ],
            'cache' => [
                'cache' => true,
                'edge_ttl' => [
                    'mode'    => 'respect_origin',
                    'default' => 86400
                ]
            ],
            'cache_aggressive' => [
                'cache' => true,
                'edge_ttl' => [
                    'mode'    => 'override_origin',
                    'default' => 604800 // 1 semana
                ]
            ],
            default => ['cache' => false]
        };
    }

    /**
     * Insertar o actualizar zona en DB local
     */
    private function upsert_zone_to_db(array $zone): void {
        global $wpdb;
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE zone_id = %s",
            $zone['zone_id']
        ));

        $synced_at = current_time('mysql');

        if ($existing) {
            $wpdb->update(
                $this->table_name,
                [
                    'name'         => $zone['name'],
                    'status'       => $zone['status'],
                    'plan_name'    => $zone['plan_name'],
                    'account_id'   => $zone['account_id'],
                    'account_name' => $zone['account_name'],
                    'paused'       => $zone['paused'],
                    'synced_at'    => $synced_at,
                    'deleted_at'   => null
                ],
                ['zone_id' => $zone['zone_id']]
            );
        } else {
            $wpdb->insert(
                $this->table_name,
                [
                    'zone_id'      => $zone['zone_id'],
                    'name'         => $zone['name'],
                    'status'       => $zone['status'],
                    'plan_name'    => $zone['plan_name'],
                    'account_id'   => $zone['account_id'],
                    'account_name' => $zone['account_name'],
                    'paused'       => $zone['paused'],
                    'synced_at'    => $synced_at,
                    'created_at'   => $synced_at
                ]
            );
        }
    }

    /**
     * Obtener zona por zone_id desde DB local
     */
    public function get_zone_from_db(string $zone_id): ?array {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE zone_id = %s AND deleted_at IS NULL",
            $zone_id
        ), ARRAY_A);
    }

    /**
     * Validar estructura de un preset payload
     */
    public function validate_preset_payload(array $payload): array {
        $errors = [];
        
        // Verificar estructura básica
        if (!isset($payload['settings']) 
            && !isset($payload['cache_rules']) 
            && !isset($payload['ai_crawlers'])
            && !isset($payload['bot_management'])
            && !isset($payload['security_headers'])
            && !isset($payload['firewall_rules'])) {
            $errors[] = 'El preset debe contener al menos una sección válida';
        }

        // Validar settings permitidos - Lista completa de Cloudflare Zone Settings
        // Note: deprecated settings removed in v1.7.0 (minify, server_side_exclude, response_buffering, prefetch_preload)
        $allowed_settings = [
            // Performance
            'http3', 'http2', 'brotli', '0rtt', 'rocket_loader',
            'early_hints', 'h2_prioritization',
            
            // SSL/TLS
            'ssl', 'ssl_recommender', 'always_use_https', 'min_tls_version',
            'automatic_https_rewrites', 'opportunistic_encryption', 'tls_1_3',
            
            // Security
            'security_level', 'challenge_ttl', 'browser_check', 
            'email_obfuscation', 'hotlink_protection', 'ip_geolocation',
            'waf', 'security_header', 'privacy_pass',
            
            // Image Optimization (Pro+)
            'polish', 'mirage', 'image_resizing', 'webp',
            
            // Caching
            'browser_cache_ttl', 'cache_level', 'development_mode', 
            'always_online', 'sort_query_string_for_cache',
            
            // Network
            'ipv6', 'websockets', 'pseudo_ipv4',
            'max_upload', 'true_client_ip_header',
            
            // Content
            'origin_error_page_pass_thru',
            'automatic_platform_optimization'
        ];

        // Settings deprecated by CF — skip silently if present in preset
        $deprecated_settings = [
            'minify', 'server_side_exclude', 'response_buffering', 'prefetch_preload',
        ];

        if (isset($payload['settings'])) {
            foreach (array_keys($payload['settings']) as $setting) {
                if (in_array($setting, $deprecated_settings)) {
                    error_log("[DR CF] Deprecated setting '{$setting}' en preset — será ignorado (eliminado por Cloudflare)");
                    continue;
                }
                if (!in_array($setting, $allowed_settings)) {
                    // Solo advertir, no bloquear - CF rechazará los inválidos
                    error_log("[DR CF] Warning: Setting '{$setting}' no está en lista conocida, pero se intentará aplicar");
                }
            }
        }

        // Validar cache_rules
        if (isset($payload['cache_rules'])) {
            foreach ($payload['cache_rules'] as $i => $rule) {
                if (empty($rule['name'])) {
                    $errors[] = "Cache rule #$i: falta 'name'";
                }
                if (empty($rule['if']) && empty($rule['expression'])) {
                    $errors[] = "Cache rule '{$rule['name']}': falta 'if' o 'expression'";
                }
            }
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors
        ];
    }
}
