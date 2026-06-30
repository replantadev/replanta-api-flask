<?php
/**
 * WHM Integration Class
 * Handles cPanel/WHM integration for hosting management
 * Supports multiple WHM reseller servers (e.g. EU + US)
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPHUB_WHM_Integration {
    
    /**
     * Legacy single-server properties (kept for backward compatibility).
     * Point to the default (first) configured server.
     */
    private $whm_host;
    private $whm_user;
    private $whm_token;
    private $api_client;

    /**
     * Multi-server support.
     * Array of server configs keyed by server ID.
     * Each entry: [id, label, host, username, token, region, port, verify_ssl, enabled]
     */
    private $servers = [];
    private $default_server_id = null;

    /**
     * Cache: domain → server_id mapping built from WHM accounts
     */
    private $domain_server_map = null;
    
    public function __construct() {
        $this->load_servers();

        // Set legacy properties from default server for backward compat
        $default = $this->get_default_server();
        if ($default) {
            $this->whm_host  = $default['host']     ?? '';
            $this->whm_user  = $default['username']  ?? '';
            $this->whm_token = $default['token']     ?? '';
        } else {
            $this->whm_host  = '';
            $this->whm_user  = '';
            $this->whm_token = '';
        }
        
        if ($this->is_configured()) {
            $this->init_hooks();
        }
    }

    /**
     * Load server configurations.
     * Reads new multi-server option first; falls back to legacy single-server options.
     */
    private function load_servers() {
        $settings = get_option('rphub_settings', []);
        $servers_raw = $settings['whm_servers'] ?? [];

        if (!empty($servers_raw) && is_array($servers_raw)) {
            foreach ($servers_raw as $srv) {
                $id = sanitize_key($srv['id'] ?? '');
                if (empty($id)) continue;
                $this->servers[$id] = [
                    'id'         => $id,
                    'label'      => $srv['label']      ?? $id,
                    'host'       => $srv['host']        ?? '',
                    'username'   => $srv['username']    ?? 'replanta',
                    'token'      => RPHUB_Crypto::decrypt( $srv['token'] ?? '' ),
                    'region'     => $srv['region']      ?? '',
                    'port'       => intval($srv['port'] ?? 2087),
                    'verify_ssl' => isset($srv['verify_ssl']) ? (bool) $srv['verify_ssl'] : true,
                    'enabled'    => isset($srv['enabled']) ? (bool) $srv['enabled'] : true,
                ];
            }
        }

        // If no multi-server config, try legacy single-server options and migrate
        if (empty($this->servers)) {
            $host = get_option('rphub_whm_host', '') ?: 
                    get_option('rphub_whm_url', '') ?: 
                    get_option('replanta_hub_whm_server', '') ?:
                    ($settings['whm_url'] ?? '');

            $user = get_option('rphub_whm_username', '') ?: 
                    get_option('replanta_hub_whm_username', '') ?:
                    ($settings['whm_username'] ?? '') ?: 'replanta';

            // Migrar 'root' a 'replanta'
            if ($user === 'root') {
                $user = 'replanta';
                update_option('rphub_whm_username', 'replanta');
            }

            $token = RPHUB_Crypto::decrypt(
                         get_option('rphub_whm_password', '') ?:
                         get_option('rphub_whm_token', '') ?:
                         get_option('replanta_hub_whm_api_token', '') ?:
                         ($settings['whm_api_token'] ?? '')
                     );

            if (!empty($host) && !empty($token)) {
                $this->servers['default'] = [
                    'id'         => 'default',
                    'label'      => 'Servidor Principal',
                    'host'       => $host,
                    'username'   => $user,
                    'token'      => $token,
                    'region'     => '',
                    'port'       => intval(get_option('rphub_whm_port', 2087)),
                    'verify_ssl' => (bool) ($settings['whm_verify_ssl'] ?? true),
                    'enabled'    => true,
                ];
            }
        }

        // Set default server ID
        if (!empty($this->servers)) {
            $this->default_server_id = array_key_first($this->servers);
        }
    }

    /* ------------------------------------------------------------------
     * Multi-server accessors
     * ----------------------------------------------------------------*/

    /**
     * Get all configured servers.
     * @return array
     */
    public function get_all_servers() {
        return $this->servers;
    }

    /**
     * Get a specific server config by ID.
     * @param string $server_id
     * @return array|null
     */
    public function get_server($server_id) {
        return $this->servers[$server_id] ?? null;
    }

    /**
     * Get the default server config.
     * @return array|null
     */
    public function get_default_server() {
        if ($this->default_server_id && isset($this->servers[$this->default_server_id])) {
            return $this->servers[$this->default_server_id];
        }
        return !empty($this->servers) ? reset($this->servers) : null;
    }

    /**
     * Resolve which server manages a given domain.
     * Strategy:
     *  1. Check rphub_sites.whm_server column
     *  2. Build a domain→server cache by querying all servers
     *  3. Fall back to default server
     *
     * @param string $domain
     * @return string Server ID
     */
    public function get_server_for_domain($domain) {
        global $wpdb;

        // 1. Check if the site already has a server assignment
        $table = $wpdb->prefix . 'rphub_sites';
        $col_exists = $wpdb->get_var("SHOW COLUMNS FROM `{$table}` LIKE 'whm_server'");
        if ($col_exists) {
            $server_id = $wpdb->get_var($wpdb->prepare(
                "SELECT whm_server FROM {$table} WHERE domain = %s AND whm_server != '' LIMIT 1",
                $domain
            ));
            if ($server_id && isset($this->servers[$server_id])) {
                return $server_id;
            }
        }

        // 2. Build domain map if not cached
        if ($this->domain_server_map === null) {
            $this->build_domain_server_map();
        }

        $domain_clean = strtolower(preg_replace('/^www\./', '', $domain));
        if (isset($this->domain_server_map[$domain_clean])) {
            return $this->domain_server_map[$domain_clean];
        }

        // 3. Default server
        return $this->default_server_id ?: '';
    }

    /**
     * Build domain→server_id map by querying all enabled servers.
     */
    private function build_domain_server_map() {
        $this->domain_server_map = [];

        foreach ($this->servers as $id => $srv) {
            if (!$srv['enabled']) continue;

            $accounts = $this->get_accounts($id);
            if (is_wp_error($accounts) || !is_array($accounts)) continue;

            foreach ($accounts as $acct) {
                $d = strtolower(preg_replace('/^www\./', '', $acct['domain'] ?? ''));
                if (!empty($d)) {
                    $this->domain_server_map[$d] = $id;
                }
            }
        }
    }

    /**
     * Migrate old single-server settings to multi-server format.
     * Called once from activation/upgrade routine.
     */
    public static function maybe_migrate_to_multi_server() {
        $settings = get_option('rphub_settings', []);

        // Already migrated
        if (!empty($settings['whm_servers'])) {
            return;
        }

        $host  = $settings['whm_url']       ?? '';
        $user  = $settings['whm_username']   ?? '';
        $token = $settings['whm_api_token']  ?? '';

        if (empty($host) && empty($token)) {
            return; // Nothing to migrate
        }

        $settings['whm_servers'] = [
            [
                'id'         => 'default',
                'label'      => 'Servidor Principal',
                'host'       => $host,
                'username'   => $user ?: 'replanta',
                'token'      => $token,
                'region'     => '',
                'port'       => 2087,
                'verify_ssl' => (bool) ($settings['whm_verify_ssl'] ?? true),
                'enabled'    => true,
            ]
        ];

        update_option('rphub_settings', $settings);
    }
    
    private function init_hooks() {
        // AJAX hooks for WHM operations
        add_action('wp_ajax_rphub_whm_get_accounts', [$this, 'ajax_get_accounts']);
        add_action('wp_ajax_rphub_whm_create_account', [$this, 'ajax_create_account']);
        add_action('wp_ajax_rphub_whm_suspend_account', [$this, 'ajax_suspend_account']);
        add_action('wp_ajax_rphub_whm_unsuspend_account', [$this, 'ajax_unsuspend_account']);
        add_action('wp_ajax_rphub_whm_get_account_info', [$this, 'ajax_get_account_info']);
        add_action('wp_ajax_rphub_whm_get_disk_usage', [$this, 'ajax_get_disk_usage']);
        add_action('wp_ajax_rphub_whm_create_backup', [$this, 'ajax_create_backup']);
        add_action('wp_ajax_rphub_whm_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_rphub_whm_run_diagnostics', [$this, 'ajax_run_diagnostics']);
        add_action('wp_ajax_rphub_whm_get_servers', [$this, 'ajax_get_servers']);
        add_action('wp_ajax_rphub_whm_test_server', [$this, 'ajax_test_server']);
        
        // Automatic account monitoring
        add_action('rphub_whm_monitor', [$this, 'monitor_accounts']);
    }
    
    /**
     * Check if WHM is properly configured (at least one enabled server).
     */
    public function is_configured() {
        // Check if WHM is enabled globally
        if (!get_option('rphub_whm_enabled', false)) {
            $settings = get_option('rphub_settings', []);
            if (empty($settings['whm_enabled'])) {
                return false;
            }
        }
        
        // Check that at least one server has valid credentials
        foreach ($this->servers as $srv) {
            if ($srv['enabled'] && !empty($srv['host']) && !empty($srv['username']) && !empty($srv['token'])) {
                return true;
            }
        }

        return false;
    }
    
    /**
     * Make WHM API request to a specific server (or default).
     *
     * @param string      $endpoint  WHM JSON API endpoint
     * @param array       $params    Query/body parameters
     * @param string      $method    HTTP method
     * @param string|null $server_id Server to target (null = default)
     * @return array|WP_Error
     */
    private function make_whm_request($endpoint, $params = [], $method = 'GET', $server_id = null) {
        // Resolve server
        $srv = $server_id ? $this->get_server($server_id) : $this->get_default_server();
        if (!$srv || empty($srv['host']) || empty($srv['token'])) {
            return new WP_Error('whm_not_configured', 'WHM no está configurado correctamente' . ($server_id ? " (servidor: {$server_id})" : ''));
        }
        
        $whm_port = $srv['port'] ?: 2087;
        $settings = get_option('rphub_settings', []);
        $timeout  = intval($settings['whm_timeout'] ?? 60);
        
        $whm_url = 'https://' . $srv['host'] . ':' . $whm_port;
        $url = rtrim($whm_url, '/') . "/json-api/{$endpoint}";
        
        // Añadir api.version=1 a la URL para compatibilidad
        if ($method === 'GET' && !empty($params)) {
            $params['api.version'] = '1';
            $url = add_query_arg($params, $url);
        } else {
            $url = add_query_arg(['api.version' => '1'], $url);
        }
        
        $headers = [
            'Authorization' => 'whm ' . $srv['username'] . ':' . $srv['token']
        ];
        
        $args = [
            'method'    => $method,
            'headers'   => $headers,
            'timeout'   => $timeout,
            'sslverify' => (bool) $srv['verify_ssl'],
        ];
        
        if ($method === 'POST' && !empty($params)) {
            // WHM API expects form-encoded body, NOT JSON
            $args['body'] = $params;
        }
        
        // Log the request for debugging
        if (defined('RPHUB_DEBUG') && RPHUB_DEBUG) {
            $safe_token = substr($srv['token'], 0, 8) . '...';
            $label = $srv['label'] ?? $srv['id'];
            error_log("[Replanta Hub WHM:{$label}] Request: {$url} user:{$srv['username']} token:{$safe_token}");
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            $label = $srv['label'] ?? $srv['id'];
            $err_msg = $response->get_error_message();
            error_log("[Replanta Hub WHM:{$label}] HTTP Error: " . $err_msg);
            
            // Provide actionable hint for SSL certificate errors
            if (stripos($err_msg, 'SSL') !== false || stripos($err_msg, 'certificate') !== false) {
                $err_msg .= ' — Si conectas por IP, desactiva "Verificar SSL" en Ajustes > WHM para este servidor.';
            }
            
            return new WP_Error('whm_request_error', "Error de conexión WHM ({$label}): " . $err_msg);
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        $label = $srv['label'] ?? $srv['id'];
        if (defined('RPHUB_DEBUG') && RPHUB_DEBUG) {
            error_log("[Replanta Hub WHM:{$label}] Response code: {$response_code} endpoint: {$endpoint}");
        }
        
        if ($response_code >= 400) {
            error_log("[Replanta Hub WHM:{$label}] HTTP Error {$response_code} - Body: " . substr($response_body, 0, 500));
            
            switch ($response_code) {
                case 401:
                    return new WP_Error('whm_auth_error', "Error de autenticación WHM (401) en {$label}. Verifique usuario y token.");
                case 403:
                    return new WP_Error('whm_forbidden', "Acceso denegado WHM (403) en {$label}. Verifique permisos del usuario.");
                case 404:
                    return new WP_Error('whm_not_found', "Endpoint WHM no encontrado (404) en {$label}.");
                case 500:
                    return new WP_Error('whm_server_error', "Error interno del servidor WHM (500) en {$label}.");
                default:
                    return new WP_Error('whm_http_error', "HTTP {$response_code}: Error en la API de WHM ({$label})");
            }
        }
        
        $data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('whm_json_error', "Respuesta JSON inválida de WHM ({$label})");
        }
        
        return $data;
    }
    
    /**
     * Get all cPanel accounts.
     *
     * @param string|null $server_id  Specific server, or null to aggregate all servers.
     * @return array|WP_Error
     */
    public function get_accounts($server_id = null) {
        // If a specific server is requested
        if ($server_id !== null) {
            $response = $this->make_whm_request('listaccts', [], 'GET', $server_id);
            if (is_wp_error($response)) {
                return $response;
            }
            $accounts = isset($response['data']['acct']) ? $response['data']['acct'] : [];
            // Tag each account with its server ID
            foreach ($accounts as &$acct) {
                $acct['_whm_server'] = $server_id;
                $acct['_whm_label']  = $this->servers[$server_id]['label'] ?? $server_id;
                $acct['_whm_region'] = $this->servers[$server_id]['region'] ?? '';
            }
            return $accounts;
        }

        // Aggregate from ALL enabled servers
        $all_accounts = [];
        $errors = [];

        foreach ($this->servers as $id => $srv) {
            if (!$srv['enabled']) continue;

            $response = $this->make_whm_request('listaccts', [], 'GET', $id);
            if (is_wp_error($response)) {
                $errors[$id] = $response->get_error_message();
                continue;
            }

            $accounts = isset($response['data']['acct']) ? $response['data']['acct'] : [];
            foreach ($accounts as &$acct) {
                $acct['_whm_server'] = $id;
                $acct['_whm_label']  = $srv['label'] ?? $id;
                $acct['_whm_region'] = $srv['region'] ?? '';
            }
            $all_accounts = array_merge($all_accounts, $accounts);
        }

        // If all servers failed, return last error
        if (empty($all_accounts) && !empty($errors)) {
            return new WP_Error('whm_all_servers_failed', 'Todos los servidores WHM fallaron: ' . implode('; ', $errors));
        }

        return $all_accounts;
    }
    
    /**
     * Create new cPanel account
     * @param string      $domain
     * @param string      $username
     * @param string      $plan
     * @param string      $email
     * @param string|null $server_id Target WHM server (null = auto-detect or default)
     */
    public function create_account($domain, $username, $plan, $email = '', $server_id = null) {
        $params = [
            'domain' => $domain,
            'username' => $username,
            'plan' => $plan,
            'contactemail' => $email ?: get_option('admin_email'),
            'password' => wp_generate_password(16, true)
        ];
        
        $response = $this->make_whm_request('createacct', $params, 'POST', $server_id);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        if (isset($response['metadata']['result']) && $response['metadata']['result'] == 1) {
            return [
                'success' => true,
                'username' => $username,
                'password' => $params['password'],
                'message' => $response['metadata']['reason'] ?? 'Cuenta creada exitosamente'
            ];
        } else {
            return new WP_Error('whm_create_failed', $response['metadata']['reason'] ?? 'Error desconocido al crear la cuenta');
        }
    }
    
    /**
     * Suspend cPanel account
     * @param string|null $server_id Target WHM server
     */
    public function suspend_account($username, $reason = 'Suspendido por Replanta Hub', $server_id = null) {
        $params = [
            'user' => $username,
            'reason' => $reason
        ];
        
        $response = $this->make_whm_request('suspendacct', $params, 'POST', $server_id);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return isset($response['metadata']['result']) && $response['metadata']['result'] == 1;
    }
    
    /**
     * Unsuspend cPanel account
     * @param string|null $server_id Target WHM server
     */
    public function unsuspend_account($username, $server_id = null) {
        $params = ['user' => $username];
        
        $response = $this->make_whm_request('unsuspendacct', $params, 'POST', $server_id);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return isset($response['metadata']['result']) && $response['metadata']['result'] == 1;
    }
    
    /**
     * Get account information
     * @param string|null $server_id Target WHM server
     */
    public function get_account_info($username, $server_id = null) {
        $params = ['user' => $username];
        
        $response = $this->make_whm_request('accountsummary', $params, 'GET', $server_id);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return isset($response['data']['acct'][0]) ? $response['data']['acct'][0] : [];
    }
    
    /**
     * Get disk usage for account
     * @param string|null $server_id Target WHM server
     */
    public function get_disk_usage($username, $server_id = null) {
        $params = ['user' => $username];
        
        $response = $this->make_whm_request('showbw', $params, 'GET', $server_id);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $response['data'] ?? [];
    }
    
    /**
     * Create backup for account
     * @param string|null $server_id Target WHM server
     */
    public function create_backup($username, $backup_type = 'full', $server_id = null) {
        $params = [
            'user' => $username,
            'type' => $backup_type
        ];
        
        $response = $this->make_whm_request('fullbackup', $params, 'POST', $server_id);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return isset($response['metadata']['result']) && $response['metadata']['result'] == 1;
    }
    
    /**
     * Get server information
     * @param string|null $server_id Target WHM server (null = all servers)
     */
    public function get_server_info($server_id = null) {
        // If no specific server, return info for all servers
        if ($server_id === null && count($this->servers) > 1) {
            $all_info = [];
            foreach ($this->servers as $id => $srv) {
                if (!$srv['enabled']) continue;
                $all_info[$id] = $this->get_server_info($id);
            }
            return $all_info;
        }

        $response = $this->make_whm_request('systemloadavg', [], 'GET', $server_id);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $load_avg = $response['data'] ?? [];
        
        // Get additional server stats
        $stats_response = $this->make_whm_request('systemstats', [], 'GET', $server_id);
        $stats = is_wp_error($stats_response) ? [] : $stats_response['data'];
        
        return [
            'load_average' => $load_avg,
            'system_stats' => $stats
        ];
    }
    
    /**
     * Monitor accounts for issues across all servers
     */
    public function monitor_accounts() {
        $accounts = $this->get_accounts(); // aggregates all servers
        
        if (is_wp_error($accounts)) {
            error_log('WHM monitoring failed: ' . $accounts->get_error_message());
            return;
        }
        
        foreach ($accounts as $account) {
            $this->check_account_health($account);
        }
    }
    
    /**
     * Check individual account health
     */
    private function check_account_health($account) {
        $username = $account['user'];
        $notifications = new RPHUB_Notifications();
        
        // Check disk usage
        if (isset($account['disklimit']) && isset($account['diskused'])) {
            $disk_usage_percent = ($account['diskused'] / $account['disklimit']) * 100;
            
            if ($disk_usage_percent > 90) {
                $notifications->create_notification([
                    'type' => 'whm_disk_usage',
                    'severity' => 'warning',
                    'title' => 'Alto uso de disco en cPanel',
                    'message' => "La cuenta {$username} está usando {$disk_usage_percent}% del espacio en disco",
                    'data' => [
                        'username' => $username,
                        'disk_usage_percent' => $disk_usage_percent,
                        'disk_used' => $account['diskused'],
                        'disk_limit' => $account['disklimit']
                    ]
                ]);
            }
        }
        
        // Check if account is suspended
        if (isset($account['suspended']) && $account['suspended'] == 1) {
            $notifications->create_notification([
                'type' => 'whm_account_suspended',
                'severity' => 'error',
                'title' => 'Cuenta cPanel suspendida',
                'message' => "La cuenta {$username} está suspendida",
                'data' => [
                    'username' => $username,
                    'suspend_reason' => $account['suspendreason'] ?? 'Razón no especificada'
                ]
            ]);
        }
        
        // Check bandwidth usage
        if (isset($account['bwlimit']) && isset($account['bwusage'])) {
            $bw_usage_percent = ($account['bwusage'] / $account['bwlimit']) * 100;
            
            if ($bw_usage_percent > 90) {
                $notifications->create_notification([
                    'type' => 'whm_bandwidth_usage',
                    'severity' => 'warning',
                    'title' => 'Alto uso de ancho de banda',
                    'message' => "La cuenta {$username} está usando {$bw_usage_percent}% del ancho de banda",
                    'data' => [
                        'username' => $username,
                        'bw_usage_percent' => $bw_usage_percent,
                        'bw_used' => $account['bwusage'],
                        'bw_limit' => $account['bwlimit']
                    ]
                ]);
            }
        }
    }
    
    /**
     * Sync WHM accounts with managed sites (multi-server aware).
     * Tags each site with its whm_server and cpanel_username.
     */
    public function sync_whm_accounts() {
        global $wpdb;
        
        $accounts = $this->get_accounts(); // aggregates all servers
        if (is_wp_error($accounts)) {
            return $accounts;
        }
        
        $sites_table = $wpdb->prefix . 'rphub_sites';

        // Ensure whm_server column exists
        $col = $wpdb->get_var("SHOW COLUMNS FROM `{$sites_table}` LIKE 'whm_server'");
        if (!$col) {
            $wpdb->query("ALTER TABLE `{$sites_table}` ADD COLUMN `whm_server` VARCHAR(50) DEFAULT '' AFTER `cpanel_username`");
        }

        $synced = 0;
        
        foreach ($accounts as $account) {
            $domain    = $account['domain'];
            $username  = $account['user'];
            $server_id = $account['_whm_server'] ?? '';
            
            // Try to find matching site by domain
            $site = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$sites_table} WHERE domain = %s OR url LIKE %s",
                    $domain,
                    '%' . $domain . '%'
                )
            );
            
            if ($site) {
                $wpdb->update(
                    $sites_table,
                    [
                        'cpanel_username' => $username,
                        'whm_server'      => $server_id,
                    ],
                    ['id' => $site->id],
                    ['%s', '%s'],
                    ['%d']
                );
                $synced++;
            }
        }
        
        return $synced;
    }
    
    // AJAX Handlers
    public function ajax_get_accounts() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $server_id = sanitize_key($_POST['server_id'] ?? '') ?: null;
        $accounts = $this->get_accounts($server_id);
        
        if (is_wp_error($accounts)) {
            wp_send_json_error($accounts->get_error_message());
        } else {
            wp_send_json_success($accounts);
        }
    }
    
    public function ajax_create_account() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $domain    = sanitize_text_field($_POST['domain'] ?? '');
        $username  = sanitize_text_field($_POST['username'] ?? '');
        $plan      = sanitize_text_field($_POST['plan'] ?? '');
        $email     = sanitize_email($_POST['email'] ?? '');
        $server_id = sanitize_key($_POST['server_id'] ?? '') ?: null;
        
        if (empty($domain) || empty($username) || empty($plan)) {
            wp_send_json_error('Faltan parámetros requeridos');
        }
        
        $result = $this->create_account($domain, $username, $plan, $email, $server_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }
    
    public function ajax_suspend_account() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $username  = sanitize_text_field($_POST['username'] ?? '');
        $reason    = sanitize_text_field($_POST['reason'] ?? 'Suspendido por Replanta Hub');
        $server_id = sanitize_key($_POST['server_id'] ?? '') ?: null;
        
        if (empty($username)) {
            wp_send_json_error('Nombre de usuario requerido');
        }
        
        $result = $this->suspend_account($username, $reason, $server_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else if ($result) {
            wp_send_json_success('Cuenta suspendida correctamente');
        } else {
            wp_send_json_error('Error al suspender la cuenta');
        }
    }
    
    public function ajax_unsuspend_account() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $username  = sanitize_text_field($_POST['username'] ?? '');
        $server_id = sanitize_key($_POST['server_id'] ?? '') ?: null;
        
        if (empty($username)) {
            wp_send_json_error('Nombre de usuario requerido');
        }
        
        $result = $this->unsuspend_account($username, $server_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else if ($result) {
            wp_send_json_success('Cuenta reactivada correctamente');
        } else {
            wp_send_json_error('Error al reactivar la cuenta');
        }
    }
    
    public function ajax_get_account_info() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $username  = sanitize_text_field($_POST['username'] ?? '');
        $server_id = sanitize_key($_POST['server_id'] ?? '') ?: null;
        
        if (empty($username)) {
            wp_send_json_error('Nombre de usuario requerido');
        }
        
        $result = $this->get_account_info($username, $server_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }
    
    public function ajax_get_disk_usage() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $username  = sanitize_text_field($_POST['username'] ?? '');
        $server_id = sanitize_key($_POST['server_id'] ?? '') ?: null;
        
        if (empty($username)) {
            wp_send_json_error('Nombre de usuario requerido');
        }
        
        $result = $this->get_disk_usage($username, $server_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }
    
    public function ajax_create_backup() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $username    = sanitize_text_field($_POST['username'] ?? '');
        $backup_type = sanitize_text_field($_POST['backup_type'] ?? 'full');
        $server_id   = sanitize_key($_POST['server_id'] ?? '') ?: null;
        
        if (empty($username)) {
            wp_send_json_error('Nombre de usuario requerido');
        }
        
        $result = $this->create_backup($username, $backup_type, $server_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else if ($result) {
            wp_send_json_success('Backup iniciado correctamente');
        } else {
            wp_send_json_error('Error al iniciar el backup');
        }
    }
    
    /**
     * Check if persistent tokens are enabled
     */
    public function persistent_tokens_enabled() {
        $settings = get_option('rphub_settings', []);
        return isset($settings['whm_persistent_tokens']) && $settings['whm_persistent_tokens'] == 1;
    }
    
    /**
     * Check if token needs renewal (within 24 hours of expiration or if connection fails)
     */
    public function token_needs_renewal() {
        if (!$this->persistent_tokens_enabled()) {
            return false;
        }
        
        $token_created = get_option('rphub_whm_token_created', 0);
        if (!$token_created) {
            return true; // No timestamp means we need to set one
        }
        
        // WHM tokens typically expire after 24-48 hours
        $token_age = time() - $token_created;
        $renewal_threshold = 24 * 3600; // 24 hours
        
        // Check age-based renewal
        if ($token_age >= $renewal_threshold) {
            return true;
        }
        
        // Also check if token is actually working
        $test_response = $this->make_whm_request('listaccts');
        if (is_wp_error($test_response)) {
            // If it's an auth error, token needs renewal
            if (strpos($test_response->get_error_message(), '401') !== false || 
                strpos($test_response->get_error_message(), '403') !== false ||
                strpos($test_response->get_error_message(), 'authentication') !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Renew WHM token automatically
     */
    public function renew_whm_token() {
        if (!$this->persistent_tokens_enabled()) {
            return new WP_Error('persistent_tokens_disabled', 'Tokens persistentes no están activados');
        }
        
        $settings = get_option('rphub_settings', []);
        
        // For WHM API tokens, we need to create a new token
        // This requires the WHM username and password (not just the token)
        // Since we don't store the password for security reasons, we'll try a different approach
        
        // First, let's try to test if the current token still works
        $test_response = $this->make_whm_request('listaccts');
        
        if (!is_wp_error($test_response)) {
            // Token still works, just update the timestamp
            $this->update_token_timestamp();
            if (defined('RPHUB_DEBUG') && RPHUB_DEBUG) {
                error_log('[Replanta Hub] WHM token is still valid, updated timestamp');
            }
            return true;
        }
        
        // If token doesn't work, we need manual intervention
        // In a production environment, you might want to:
        // 1. Store encrypted WHM password
        // 2. Use it to generate a new token automatically
        // 3. Or implement a notification system to alert admin
        
        error_log('[Replanta Hub] WHM token renewal failed - manual re-authentication required');
        
        // Send admin notification
        $admin_email = get_option('admin_email');
        $subject = '[Replanta Hub] Renovación de Token WHM Requerida';
        $message = "El token de API de WHM ha expirado y requiere renovación manual.\n\n";
        $message .= "Por favor, accede a la configuración de Replanta Hub y actualiza el token de API de WHM.\n\n";
        $message .= "URL de configuración: " . admin_url('admin.php?page=replanta-hub');
        
        wp_mail($admin_email, $subject, $message);
        
        return new WP_Error('manual_renewal_required', 'La renovación del token WHM requiere re-autenticación manual. Se ha enviado una notificación al administrador.');
    }
    
    /**
     * Handle WHM API request with automatic token renewal on auth failure
     */
    public function make_whm_request_with_retry($endpoint, $params = [], $method = 'GET', $server_id = null, $retry_count = 0) {
        $response = $this->make_whm_request($endpoint, $params, $method, $server_id);
        
        // If we get an authentication error and haven't retried yet
        if (is_wp_error($response) && 
            ($response->get_error_code() === 'whm_http_error' || 
             strpos($response->get_error_message(), '401') !== false ||
             strpos($response->get_error_message(), '403') !== false) && 
            $retry_count < 1) {
            
            // Try to renew token
            $renewal_result = $this->renew_whm_token();
            
            if (!is_wp_error($renewal_result)) {
                // Retry the request with new token
                return $this->make_whm_request_with_retry($endpoint, $params, $method, $server_id, $retry_count + 1);
            }
        }
        
        return $response;
    }
    
    /**
     * Update token timestamp when token is set/changed
     */
    public function update_token_timestamp() {
        update_option('rphub_whm_token_created', time());
        
        // Also update the settings with current timestamp for reference
        $settings = get_option('rphub_settings', []);
        $settings['whm_token_updated'] = time();
        update_option('rphub_settings', $settings);
        
        if (defined('RPHUB_DEBUG') && RPHUB_DEBUG) {
            error_log('[Replanta Hub] WHM token timestamp updated');
        }
    }
    
    /**
     * Run comprehensive WHM diagnostics (multi-server)
     * @param string|null $server_id Specific server or null for all
     */
    public function run_diagnostics($server_id = null) {
        $diagnostics = [
            'servers_count' => count($this->servers),
            'servers' => [],
            'recommendations' => []
        ];
        
        $settings = get_option('rphub_settings', []);
        $diagnostics['global'] = [
            'whm_enabled' => isset($settings['whm_enabled']) && $settings['whm_enabled'],
            'whm_persistent_tokens' => isset($settings['whm_persistent_tokens']) && $settings['whm_persistent_tokens'],
        ];

        $servers_to_check = [];
        if ($server_id && isset($this->servers[$server_id])) {
            $servers_to_check = [$server_id => $this->servers[$server_id]];
        } else {
            $servers_to_check = $this->servers;
        }

        foreach ($servers_to_check as $id => $srv) {
            $srv_diag = [
                'id'    => $id,
                'label' => $srv['label'] ?? $id,
                'region' => $srv['region'] ?? '',
                'configuration' => [
                    'host'       => !empty($srv['host']),
                    'username'   => !empty($srv['username']),
                    'token'      => !empty($srv['token']),
                    'verify_ssl' => $srv['verify_ssl'],
                    'port'       => $srv['port'],
                    'enabled'    => $srv['enabled'],
                ],
                'connectivity' => [],
                'authentication' => [],
            ];

            // Connectivity test
            $whm_url = 'https://' . ($srv['host'] ?? '') . ':' . ($srv['port'] ?: 2087);
            $conn_test = wp_remote_head($whm_url, [
                'timeout' => 10,
                'sslverify' => (bool) $srv['verify_ssl'],
                'redirection' => 0
            ]);
            $srv_diag['connectivity'] = [
                'http_reachable' => !is_wp_error($conn_test),
                'response_code'  => !is_wp_error($conn_test) ? wp_remote_retrieve_response_code($conn_test) : null,
            ];

            // Auth test
            if ($srv['enabled'] && !empty($srv['host']) && !empty($srv['token'])) {
                $auth_test = $this->get_accounts($id);
                $srv_diag['authentication'] = [
                    'auth_successful' => !is_wp_error($auth_test),
                    'error_message'   => is_wp_error($auth_test) ? $auth_test->get_error_message() : null,
                    'accounts_found'  => is_array($auth_test) ? count($auth_test) : 0,
                ];
            } else {
                $srv_diag['authentication'] = [
                    'auth_successful' => false,
                    'error_message'   => 'Servidor deshabilitado o sin credenciales',
                    'accounts_found'  => 0,
                ];
            }

            $diagnostics['servers'][$id] = $srv_diag;
        }
        
        // Recommendations
        if (empty($this->servers)) {
            $diagnostics['recommendations'][] = 'No hay servidores WHM configurados. Añada al menos uno en Configuración → WHM.';
        }
        foreach ($diagnostics['servers'] as $sd) {
            if (!$sd['connectivity']['http_reachable']) {
                $diagnostics['recommendations'][] = "Servidor {$sd['label']}: No es accesible. Verifique host y puerto.";
            }
            if (isset($sd['authentication']['auth_successful']) && !$sd['authentication']['auth_successful']) {
                $diagnostics['recommendations'][] = "Servidor {$sd['label']}: Error de autenticación — " . ($sd['authentication']['error_message'] ?? '');
            }
        }
        if (empty($diagnostics['recommendations'])) {
            $diagnostics['recommendations'][] = ' Todos los servidores WHM están funcionando correctamente.';
        }

        return $diagnostics;
    }
    
    
    /**
     * Monitor and maintain WHM connection (all servers)
     */
    public function monitor_whm_connection() {
        if (!$this->is_configured() || !$this->persistent_tokens_enabled()) {
            return;
        }
        
        if (defined('RPHUB_DEBUG') && RPHUB_DEBUG) {
            error_log('[Replanta Hub] Iniciando monitoreo de conexión WHM (multi-server)');
        }
        
        foreach ($this->servers as $id => $srv) {
            if (!$srv['enabled']) continue;

            $label = $srv['label'] ?? $id;
            $test_response = $this->make_whm_request('listaccts', [], 'GET', $id);
            
            if (is_wp_error($test_response)) {
                error_log("[Replanta Hub] WHM:{$label} connection test failed: " . $test_response->get_error_message());
                
                if (strpos($test_response->get_error_message(), '401') !== false || 
                    strpos($test_response->get_error_message(), '403') !== false ||
                    strpos($test_response->get_error_message(), 'authentication') !== false) {
                    
                    $renewal_result = $this->renew_whm_token();
                    
                    if (is_wp_error($renewal_result)) {
                        $this->notify_admin_connection_issue("Servidor {$label}: " . $test_response->get_error_message());
                    } else {
                        $retry_response = $this->make_whm_request('listaccts', [], 'GET', $id);
                        if (is_wp_error($retry_response)) {
                            $this->notify_admin_connection_issue("Servidor {$label}: " . $retry_response->get_error_message());
                        }
                    }
                } else {
                    $this->notify_admin_connection_issue("Servidor {$label}: " . $test_response->get_error_message());
                }
            } else {
                if (defined('RPHUB_DEBUG') && RPHUB_DEBUG) {
                    error_log("[Replanta Hub] WHM:{$label} connection test successful");
                }
                $this->update_token_timestamp();
            }
        }
    }
    
    /**
     * Notify admin about WHM connection issues
     */
    private function notify_admin_connection_issue($error_message) {
        $admin_email = get_option('admin_email');
        $subject = '[Replanta Hub] Problema de Conexión WHM';
        $message = "Se ha detectado un problema con la conexión WHM:\n\n";
        $message .= "Error: {$error_message}\n\n";
        $message .= "Por favor, verifica la configuración WHM en Replanta Hub.\n\n";
        $message .= "URL de configuración: " . admin_url('admin.php?page=replanta-hub');
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Test WHM connection with comprehensive diagnostic.
     * @param string|null $server_id Specific server to test, or null for all.
     * @return array Test result(s).
     */
    public function test_connection($server_id = null) {
        // If testing all servers
        if ($server_id === null && count($this->servers) > 1) {
            $results = [];
            foreach ($this->servers as $id => $srv) {
                $results[$id] = $this->test_connection($id);
            }
            return $results;
        }

        $srv = $server_id ? $this->get_server($server_id) : $this->get_default_server();
        if (!$srv || empty($srv['host']) || empty($srv['token'])) {
            return [
                'success' => false,
                'server_id' => $server_id ?? 'default',
                'message' => 'Servidor WHM no configurado correctamente.',
                'details' => ['suggestion' => 'Completa todos los campos requeridos en la configuración WHM']
            ];
        }

        $label    = $srv['label'] ?? $srv['id'] ?? 'default';
        $is_local = (strpos($srv['host'], 'localhost') !== false || strpos($srv['host'], '127.0.0.1') !== false);
        $is_2087  = ($srv['port'] == 2087);
        
        $test_result = $this->make_whm_request('listaccts', [], 'GET', $server_id);
        
        if (is_wp_error($test_result)) {
            $error_message = $test_result->get_error_message();
            $error_code    = $test_result->get_error_code();
            $diagnostic    = $this->analyze_connection_error($error_message, $error_code, $is_local, $is_2087);
            
            return [
                'success'   => false,
                'server_id' => $server_id ?? $this->default_server_id,
                'label'     => $label,
                'message'   => "Error de conexión WHM ({$label}): " . $error_message,
                'details'   => array_merge([
                    'error_code' => $error_code,
                    'host'       => $srv['host'],
                    'port'       => $srv['port'],
                    'region'     => $srv['region'] ?? '',
                    'is_local'   => $is_local,
                    'timestamp'  => current_time('mysql'),
                ], $diagnostic)
            ];
        }
        
        $account_count = isset($test_result['data']['acct']) ? count($test_result['data']['acct']) : 0;
        return [
            'success'   => true,
            'server_id' => $server_id ?? $this->default_server_id,
            'label'     => $label,
            'message'   => "Conexión WHM exitosa ({$label})",
            'details'   => [
                'host'           => $srv['host'],
                'user'           => $srv['username'],
                'port'           => $srv['port'],
                'region'         => $srv['region'] ?? '',
                'accounts_found' => $account_count,
                'timestamp'      => current_time('mysql'),
                'environment'    => $is_local ? 'local' : 'remote',
            ]
        ];
    }
    
    /**
     * Analyze connection errors and provide specific suggestions
     * @param string $error_message Error message from WHM
     * @param string $error_code Error code 
     * @param bool $is_local Whether connection is to localhost
     * @param bool $is_2087 Whether using WHM port 2087
     * @return array Diagnostic information and suggestions
     */
    private function analyze_connection_error($error_message, $error_code, $is_local, $is_2087) {
        $diagnostic = [
            'problem_type' => 'unknown',
            'suggestion' => 'Error no identificado',
            'solutions' => [],
            'links' => []
        ];
        
        // Error de conexión (no se puede conectar al servidor)
        if (strpos($error_message, 'connect') !== false || strpos($error_message, 'Connection') !== false) {
            $diagnostic['problem_type'] = 'connection_failed';
            
            if ($is_local) {
                $diagnostic['suggestion'] = 'No hay servidor WHM corriendo en localhost';
                $diagnostic['solutions'] = [
                    '1. Cambiar a servidor remoto: Configura host/puerto de tu servidor real',
                    '2. Para desarrollo: Usar servidor de prueba o staging',
                    '3. WHM local: Instalar cPanel/WHM en VM (solo si es necesario)'
                ];
            } else {
                $diagnostic['suggestion'] = 'No se puede conectar al servidor WHM remoto';
                $diagnostic['solutions'] = [
                    '1. Verificar que el servidor esté funcionando',
                    '2. Confirmar que el puerto ' . get_option('rphub_whm_port', '2087') . ' esté abierto',
                    '3. Revisar firewall del servidor',
                    '4. Verificar que tu IP no esté bloqueada',
                    '5. Probar acceso web directo al panel'
                ];
            }
        }
        
        // Error de resolución DNS
        else if (strpos($error_message, 'resolve') !== false || strpos($error_message, 'host') !== false) {
            $diagnostic['problem_type'] = 'dns_resolution';
            $diagnostic['suggestion'] = 'No se puede resolver el nombre del servidor';
            $diagnostic['solutions'] = [
                '1. Verificar que el dominio/hostname sea correcto',
                '2. Probar con la IP directa del servidor en lugar del dominio',
                '3. Verificar configuración DNS local',
                '4. Confirmar que el dominio esté activo y accesible'
            ];
        }
        
        // Error de timeout
        else if (strpos($error_message, 'timeout') !== false || strpos($error_message, 'timed out') !== false) {
            $diagnostic['problem_type'] = 'timeout';
            $diagnostic['suggestion'] = 'La conexión está tardando demasiado';
            $diagnostic['solutions'] = [
                '1. Verificar la velocidad de la conexión a internet',
                '2. Comprobar si el servidor responde lentamente',
                '3. Aumentar el timeout en la configuración del plugin',
                '4. Verificar posibles problemas de red intermitentes'
            ];
        }
        
        // Error HTTP 400 - Probable problema de 2FA
        else if (strpos($error_message, '400') !== false) {
            $diagnostic['problem_type'] = '2fa_authentication';
            $diagnostic['suggestion'] = 'Probable problema de autenticación 2FA';
            $diagnostic['solutions'] = [
                '1. SI TIENES 2FA HABILITADO: Crear API Token en WHM → Development → Manage API Tokens',
                '2. Usar el API Token en lugar de la contraseña',
                '3. Verificar que el usuario tenga permisos API',
                '4. Confirmar que el usuario/contraseña sean correctos'
            ];
            $diagnostic['links'] = [
                'API Tokens Guide' => 'https://documentation.cpanel.net/display/DD/Guide+to+API+Tokens'
            ];
        }
        
        // Error HTTP 401 - Credenciales incorrectas
        else if (strpos($error_message, '401') !== false) {
            $diagnostic['problem_type'] = 'authentication';
            $diagnostic['suggestion'] = 'Credenciales incorrectas o sin permisos';
            $diagnostic['solutions'] = [
                '1. Verificar usuario y contraseña/token',
                '2. Confirmar que el usuario tenga acceso WHM',
                '3. Si usas 2FA, usar API Token en lugar de contraseña',
                '4. Verificar permisos del usuario en WHM'
            ];
        }
        
        // Error HTTP 403 - Sin permisos
        else if (strpos($error_message, '403') !== false) {
            $diagnostic['problem_type'] = 'permissions';
            $diagnostic['suggestion'] = 'Usuario sin permisos API o IP bloqueada';
            $diagnostic['solutions'] = [
                '1. Verificar permisos API del usuario en WHM',
                '2. Revisar restricciones de IP en WHM',
                '3. Confirmar que la cuenta tenga privilegios suficientes',
                '4. Verificar configuración de firewall'
            ];
        }
        
        return $diagnostic;
    }
    
    /**
     * AJAX handler for WHM connection test (all servers or specific)
     */
    public function ajax_test_connection() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos para probar la conexión WHM');
        }
        
        if (!$this->is_configured()) {
            wp_send_json_error('WHM no está configurado correctamente. Verifica la configuración.');
        }
        
        $server_id = sanitize_key($_POST['server_id'] ?? '') ?: null;
        $test_results = $this->test_connection($server_id);
        
        // Normalize to always return an array of results
        if (isset($test_results['success'])) {
            // Single server result
            wp_send_json_success([
                'servers' => [$test_results],
                'token_status' => $this->persistent_tokens_enabled() ? 'persistente' : 'temporal'
            ]);
        } else {
            // Multiple server results
            wp_send_json_success([
                'servers' => array_values($test_results),
                'token_status' => $this->persistent_tokens_enabled() ? 'persistente' : 'temporal'
            ]);
        }
    }
    
    /**
     * AJAX handler for WHM diagnostics
     */
    public function ajax_run_diagnostics() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos para ejecutar diagnósticos');
        }
        
        $server_id = sanitize_key($_POST['server_id'] ?? '') ?: null;
        $diagnostics = $this->run_diagnostics($server_id);
        
        wp_send_json_success($diagnostics);
    }

    /**
     * AJAX: Return list of configured servers (for JS consumption)
     */
    public function ajax_get_servers() {
        check_ajax_referer('rphub_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        $servers = [];
        foreach ($this->servers as $id => $srv) {
            $servers[] = [
                'id'      => $id,
                'label'   => $srv['label'],
                'region'  => $srv['region'],
                'host'    => $srv['host'],
                'port'    => $srv['port'],
                'enabled' => $srv['enabled'],
            ];
        }

        wp_send_json_success($servers);
    }

    /**
     * AJAX: Test a single server connection
     */
    public function ajax_test_server() {
        check_ajax_referer('rphub_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        $server_id = sanitize_key($_POST['server_id'] ?? '');
        if (empty($server_id) || !isset($this->servers[$server_id])) {
            wp_send_json_error('Servidor no encontrado: ' . $server_id);
        }

        $result = $this->test_connection($server_id);
        if ($result['success'] ?? false) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}
