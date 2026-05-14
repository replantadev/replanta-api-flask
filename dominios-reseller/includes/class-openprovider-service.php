<?php
/**
 * Openprovider Service
 * 
 * Gestiona la integración con Openprovider API para actualizar nameservers
 * 
 * @package Dominios_Reseller
 * @version 1.0.0
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dominios_Reseller_Openprovider_Service {

    /**
     * Openprovider API Base URL
     */
    const API_URL = 'https://api.openprovider.eu/v1beta';

    /**
     * Transient para token de sesión
     */
    const TRANSIENT_TOKEN = 'dr_openprovider_token';

    /**
     * Transient para rate limit
     */
    const TRANSIENT_RATE_LIMIT = 'dr_openprovider_rate_limit';

    /**
     * Transient para cache de todos los dominios
     */
    const TRANSIENT_DOMAINS_CACHE = 'dr_openprovider_domains_cache';

    /**
     * Instancia singleton
     */
    private static ?Dominios_Reseller_Openprovider_Service $instance = null;

    /**
     * Cache local de dominios (para evitar múltiples llamadas en misma request)
     */
    private ?array $domains_cache = null;

    /**
     * Constructor privado
     */
    private function __construct() {}

    /**
     * Obtener instancia singleton
     */
    public static function get_instance(): Dominios_Reseller_Openprovider_Service {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Verificar si está configurado
     */
    public function is_configured(): bool {
        $options = get_option('dominios_reseller_options', []);
        return !empty($options['op_username']) && !empty($options['op_password']);
    }

    /**
     * Obtener credenciales
     */
    private function get_credentials(): array {
        $options = get_option('dominios_reseller_options', []);
        return [
            'username' => $options['op_username'] ?? '',
            'password' => $options['op_password'] ?? ''
        ];
    }

    /**
     * Obtener token de autenticación (con cache)
     */
    private function get_auth_token(): string|WP_Error {
        // Intentar obtener de cache
        $cached_token = get_transient(self::TRANSIENT_TOKEN);
        if ($cached_token) {
            return $cached_token;
        }

        $credentials = $this->get_credentials();
        
        if (empty($credentials['username']) || empty($credentials['password'])) {
            return new WP_Error('not_configured', 'Credenciales de Openprovider no configuradas');
        }

        // Obtener nuevo token - Openprovider API v1beta
        $request_body = [
            'username' => $credentials['username'],
            'password' => $credentials['password']
        ];

        $response = wp_remote_post(self::API_URL . '/auth/login', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json'
            ],
            'body' => json_encode($request_body),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        // Openprovider devuelve code 0 para éxito
        if ($status_code === 200 && isset($body['code']) && $body['code'] === 0) {
            $token = $body['data']['token'] ?? null;
            
            if (!$token) {
                return new WP_Error('no_token', 'No se recibió token de Openprovider');
            }

            // Cache por 23 horas (tokens duran 24h)
            set_transient(self::TRANSIENT_TOKEN, $token, 23 * HOUR_IN_SECONDS);
            return $token;
        }

        // Error de autenticación
        $error_msg = $body['desc'] ?? $body['message'] ?? 'Error de autenticación (HTTP ' . $status_code . ')';
        return new WP_Error('auth_error', $error_msg);
    }

    /**
     * Hacer petición a la API de Openprovider
     */
    private function api_request(string $method, string $endpoint, array $data = []): array|WP_Error {
        // Verificar rate limit
        if (get_transient(self::TRANSIENT_RATE_LIMIT)) {
            return new WP_Error('rate_limited', 'Openprovider API en pausa por rate limit');
        }

        $token = $this->get_auth_token();
        if (is_wp_error($token)) {
            return $token;
        }

        $url = self::API_URL . $endpoint;
        
        $args = [
            'method'  => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json'
            ],
            'timeout' => 30
        ];

        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            error_log('[DR Openprovider] Request error: ' . $response->get_error_message());
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        // Manejar rate limit
        if ($status_code === 429) {
            $retry_after = wp_remote_retrieve_header($response, 'retry-after') ?: 60;
            set_transient(self::TRANSIENT_RATE_LIMIT, time(), intval($retry_after));
            return new WP_Error('rate_limited', 'Rate limit alcanzado. Retry after: ' . $retry_after . 's');
        }

        // Token expirado
        if ($status_code === 401) {
            delete_transient(self::TRANSIENT_TOKEN);
            return new WP_Error('token_expired', 'Token expirado. Intenta de nuevo.');
        }

        if ($status_code < 200 || $status_code >= 300) {
            $error_msg = $body['desc'] ?? $body['message'] ?? 'Error desconocido (HTTP ' . $status_code . ')';
            return new WP_Error('api_error', $error_msg, ['status' => $status_code, 'response' => $body]);
        }

        return $body;
    }

    /**
     * Parsear dominio en nombre y extensión
     */
    private function parse_domain(string $domain): array {
        $domain = strtolower(trim($domain, '. '));
        $parts = explode('.', $domain);
        
        // Manejar TLDs compuestos (co.uk, com.es, etc.)
        $compound_tlds = ['co.uk', 'com.es', 'org.uk', 'net.uk', 'com.au', 'co.nz', 'co.za'];
        
        $tld_parts = 1;
        if (count($parts) >= 3) {
            $potential_compound = implode('.', array_slice($parts, -2));
            if (in_array($potential_compound, $compound_tlds)) {
                $tld_parts = 2;
            }
        }

        $name = implode('.', array_slice($parts, 0, -$tld_parts));
        $extension = implode('.', array_slice($parts, -$tld_parts));

        return [
            'name'      => $name,
            'extension' => $extension
        ];
    }

    /**
     * Actualizar nameservers de un dominio
     * 
     * @param string $domain Nombre del dominio completo (ej: example.com)
     * @param array $nameservers Array de nameservers (ej: ['ns1.cloudflare.com', 'ns2.cloudflare.com'])
     * @return array|WP_Error
     */
    public function update_nameservers(string $domain, array $nameservers): array|WP_Error {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', 'Openprovider no configurado');
        }

        if (empty($nameservers)) {
            return new WP_Error('invalid_ns', 'Lista de nameservers vacía');
        }

        $parsed = $this->parse_domain($domain);
        
        // Validar NS antes de enviar (especialmente importante para .es)
        $validation = $this->validate_nameservers($nameservers, $parsed['extension']);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Construir array de NS para Openprovider
        $ns_array = array_map(function($ns, $index) {
            return [
                'name' => strtolower(trim($ns, '. ')),
                'seq_nr' => $index + 1
            ];
        }, $nameservers, array_keys($nameservers));

        error_log("[DR Openprovider] Actualizando NS para $domain: " . json_encode($nameservers));
        error_log("[DR Openprovider] Payload NS: " . json_encode($ns_array));

        // Hacer la petición de modificación
        $result = $this->api_request('PUT', '/domains/' . urlencode($parsed['name']) . '.' . urlencode($parsed['extension']), [
            'domain' => [
                'name'      => $parsed['name'],
                'extension' => $parsed['extension']
            ],
            'name_servers' => $ns_array
        ]);

        if (is_wp_error($result)) {
            // Log específico para ciertos errores
            $error_code = $result->get_error_code();
            $error_msg = $result->get_error_message();
            $error_data = $result->get_error_data();
            
            error_log("[DR Openprovider] Error actualizando NS para $domain: $error_msg");
            error_log("[DR Openprovider] Error code: $error_code");
            error_log("[DR Openprovider] Error data: " . json_encode($error_data));
            error_log("[DR Openprovider] Request payload: " . json_encode([
                'domain' => [
                    'name'      => $parsed['name'],
                    'extension' => $parsed['extension']
                ],
                'name_servers' => $ns_array
            ]));
            
            // Errores comunes por TLD
            if (strpos($error_msg, 'not allowed') !== false || 
                strpos($error_msg, 'registry') !== false ||
                strpos($error_msg, 'Invalid request') !== false) {
                return new WP_Error('tld_restriction', "El TLD '{$parsed['extension']}' no permite cambio de NS vía API o requiere proceso especial. Error: $error_msg");
            }
            
            return $result;
        }

        error_log("[DR Openprovider] NS actualizados para $domain: " . implode(', ', $nameservers));

        return [
            'success'     => true,
            'domain'      => $domain,
            'nameservers' => $nameservers,
            'response'    => $result
        ];
    }

    /**
     * Obtener TODOS los dominios de Openprovider (con cache)
     * Esta es la forma más fiable de buscar dominios en cuentas reseller con Upmind
     */
    public function get_all_domains(bool $force_refresh = false): array {
        // Intentar cache local primero
        if (!$force_refresh && $this->domains_cache !== null) {
            return $this->domains_cache;
        }

        // Intentar transient cache
        if (!$force_refresh) {
            $cached = get_transient(self::TRANSIENT_DOMAINS_CACHE);
            if ($cached !== false) {
                $this->domains_cache = $cached;
                error_log('[DR Openprovider] Usando cache de dominios (' . count($cached) . ' dominios)');
                return $cached;
            }
        }

        error_log('[DR Openprovider] Obteniendo TODOS los dominios de la cuenta...');

        $all_domains = [];
        $offset = 0;
        $limit = 100;
        $max_pages = 10; // Máximo 1000 dominios
        $page = 0;

        do {
            // Openprovider API v1beta usa GET con query params para listar dominios
            $endpoint = '/domains?' . http_build_query([
                'limit'  => $limit,
                'offset' => $offset,
                'with_additional_data' => 'true'
            ]);
            
            $result = $this->api_request('GET', $endpoint);

            if (is_wp_error($result)) {
                error_log('[DR Openprovider] Error obteniendo dominios: ' . $result->get_error_message());
                break;
            }

            // Debug: ver estructura de respuesta
            error_log('[DR Openprovider] Respuesta API: ' . json_encode(array_keys($result)));
            if (isset($result['data'])) {
                error_log('[DR Openprovider] Data keys: ' . json_encode(array_keys($result['data'])));
            }

            $domains = $result['data']['results'] ?? [];
            $total = $result['data']['total'] ?? 0;

            error_log('[DR Openprovider] Página ' . ($page + 1) . ': ' . count($domains) . ' dominios (total reportado: ' . $total . ')');

            foreach ($domains as $dom) {
                $fullName = strtolower(($dom['domain']['name'] ?? '') . '.' . ($dom['domain']['extension'] ?? ''));
                if (!empty($fullName) && $fullName !== '.') {
                    $all_domains[$fullName] = [
                        'status'      => $dom['status'] ?? 'unknown',
                        'expiry_date' => $dom['expiration_date'] ?? null,
                        'owner'       => $dom['owner_handle'] ?? null,
                        'auto_renew'  => $dom['autorenew'] ?? null,
                        'nameservers' => $dom['name_servers'] ?? []
                    ];
                }
            }

            $offset += $limit;
            $page++;

        } while (count($domains) === $limit && $page < $max_pages);

        error_log('[DR Openprovider] Total dominios obtenidos: ' . count($all_domains));
        
        // Guardar en cache (30 minutos)
        set_transient(self::TRANSIENT_DOMAINS_CACHE, $all_domains, 30 * MINUTE_IN_SECONDS);
        $this->domains_cache = $all_domains;

        return $all_domains;
    }

    /**
     * Limpiar cache de dominios (para forzar refresh)
     */
    public function clear_domains_cache(): void {
        delete_transient(self::TRANSIENT_DOMAINS_CACHE);
        $this->domains_cache = null;
        error_log('[DR Openprovider] Cache de dominios limpiado');
    }

    /**
     * Obtener información de un dominio desde el cache
     */
    public function get_domain_info(string $domain): array|WP_Error {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', 'Openprovider no configurado');
        }

        $domain = strtolower(trim($domain, '. '));
        error_log('[DR Openprovider] Buscando dominio: ' . $domain);

        // Obtener todos los dominios (usa cache)
        $all_domains = $this->get_all_domains();

        if (isset($all_domains[$domain])) {
            error_log('[DR Openprovider] ✅ Dominio encontrado en cache: ' . $domain);
            return ['data' => $all_domains[$domain]];
        }

        error_log('[DR Openprovider] ❌ Dominio NO encontrado: ' . $domain);
        return new WP_Error('not_found', 'Dominio no encontrado en Openprovider', ['status' => 404]);
    }

    /**
     * Verificar si un dominio existe en Openprovider (está registrado con nosotros)
     * 
     * @param string $domain Nombre del dominio
     * @return array ['exists' => bool, 'info' => array|null, 'error' => string|null]
     */
    public function domain_exists(string $domain): array {
        if (!$this->is_configured()) {
            return [
                'exists' => false,
                'info'   => null,
                'error'  => 'not_configured'
            ];
        }

        $result = $this->get_domain_info($domain);
        
        if (is_wp_error($result)) {
            $error_code = $result->get_error_code();
            // Si no se encontró, el dominio no existe en OP
            if ($error_code === 'not_found') {
                return [
                    'exists' => false,
                    'info'   => null,
                    'error'  => null
                ];
            }
            return [
                'exists' => false,
                'info'   => null,
                'error'  => $result->get_error_message()
            ];
        }

        // Si llegamos aquí, el dominio existe
        // Los datos ya vienen en formato simplificado del cache
        $domain_data = $result['data'] ?? [];
        
        return [
            'exists' => true,
            'info'   => [
                'status'      => $domain_data['status'] ?? 'unknown',
                'expiry_date' => $domain_data['expiry_date'] ?? null,
                'nameservers' => $domain_data['nameservers'] ?? [],
                'auto_renew'  => $domain_data['auto_renew'] ?? null
            ],
            'error'  => null
        ];
    }

    /**
     * Verificar múltiples dominios en lote (optimizado con cache)
     * 
     * @param array $domains Array de dominios a verificar
     * @return array ['domain' => ['exists' => bool, ...], ...]
     */
    public function batch_check_domains(array $domains): array {
        // Precargar todos los dominios una sola vez
        $this->get_all_domains();
        
        $results = [];
        foreach ($domains as $domain) {
            $results[$domain] = $this->domain_exists($domain);
        }
        
        return $results;
    }

    /**
     * Verificar conectividad con Openprovider
     */
    public function verify_connection(): array {
        // Limpiar tokens y cache para forzar re-autenticación
        $this->clear_token();
        $this->clear_domains_cache();
        
        $token = $this->get_auth_token();
        
        if (is_wp_error($token)) {
            return [
                'success' => false,
                'error'   => $token->get_error_message()
            ];
        }

        // Obtener y cachear todos los dominios
        $domains = $this->get_all_domains(true); // force refresh
        $count = count($domains);

        return [
            'success' => true,
            'message' => "Conexión exitosa. Se encontraron {$count} dominios en la cuenta."
        ];
    }

    /**
     * Limpiar token cacheado (para forzar re-autenticación)
     */
    public function clear_token(): void {
        delete_transient(self::TRANSIENT_TOKEN);
    }

    /**
     * Validar nameservers antes de actualizar
     * Especialmente importante para TLDs con restricciones como .es
     */
    private function validate_nameservers(array $nameservers, string $tld): array|WP_Error {
        // Validaciones básicas
        foreach ($nameservers as $ns) {
            $ns = trim($ns, '. ');
            if (empty($ns)) {
                return new WP_Error('invalid_ns', 'Nameserver vacío encontrado');
            }
            
            // Validar formato básico de dominio
            if (!preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $ns)) {
                return new WP_Error('invalid_ns_format', "Formato inválido de nameserver: $ns");
            }
        }

        // Validaciones específicas por TLD
        if ($tld === 'es') {
            // Para .es, verificar que no sean NS del registrador actual
            $current_ns = ['ns1.mysecurecloudhost.com', 'ns2.mysecurecloudhost.com', 
                          'ns3.mysecurecloudhost.com', 'ns4.mysecurecloudhost.com'];
            
            $conflicting = array_intersect($nameservers, $current_ns);
            if (!empty($conflicting)) {
                return new WP_Error('ns_conflict', 
                    'Los NS actuales del registrador no pueden usarse como destino. Conflicto: ' . implode(', ', $conflicting));
            }

            // Verificar que los NS de Cloudflare estén en la lista autorizada
            $cloudflare_ns = ['doug.ns.cloudflare.com', 'nia.ns.cloudflare.com'];
            $is_cloudflare = !array_diff($nameservers, $cloudflare_ns) && !array_diff($cloudflare_ns, $nameservers);
            
            if ($is_cloudflare) {
                error_log("[DR Openprovider] Detectados NS de Cloudflare para dominio .es - pueden requerir autorización especial");
            }
        }

        return ['valid' => true];
    }
}
