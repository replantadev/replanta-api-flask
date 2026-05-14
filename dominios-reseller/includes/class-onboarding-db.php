<?php
/**
 * Onboarding Database Schema
 * 
 * Gestiona las tablas de onboarding y logs para el proceso de CF
 * 
 * @package Dominios_Reseller
 * @version 1.0.0
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dominios_Reseller_Onboarding_DB {

    /**
     * Nombre de la tabla de onboarding
     */
    public static function get_onboarding_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'dominios_reseller_cf_onboarding';
    }

    /**
     * Nombre de la tabla de logs
     */
    public static function get_logs_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'dominios_reseller_cf_onboarding_logs';
    }

    /**
     * Nombre de la tabla de presets
     */
    public static function get_presets_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'dominios_reseller_cf_presets';
    }

    /**
     * Nombre de la tabla de runs
     */
    public static function get_runs_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'dominios_reseller_cf_runs';
    }

    /**
     * Crear/actualizar tablas de onboarding
     */
    public static function create_tables(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Tabla de estado de onboarding por dominio
        $table_onboarding = self::get_onboarding_table();
        $sql_onboarding = "CREATE TABLE $table_onboarding (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            primary_domain varchar(255) NOT NULL,
            zone_id varchar(32) DEFAULT NULL,
            state varchar(30) NOT NULL DEFAULT 'none',
            preset_key varchar(50) DEFAULT NULL,
            nameservers text DEFAULT NULL,
            auto_update_ns tinyint(1) NOT NULL DEFAULT 0,
            ns_verified tinyint(1) NOT NULL DEFAULT 0,
            last_run_id varchar(36) DEFAULT NULL,
            last_error text DEFAULT NULL,
            applied_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_domain (primary_domain),
            KEY idx_state (state),
            KEY idx_zone_id (zone_id),
            KEY idx_run_id (last_run_id)
        ) $charset_collate;";
        dbDelta($sql_onboarding);

        // Tabla de logs de ejecución
        $table_logs = self::get_logs_table();
        $sql_logs = "CREATE TABLE $table_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            run_id varchar(36) NOT NULL,
            primary_domain varchar(255) NOT NULL,
            step varchar(50) NOT NULL,
            level varchar(20) NOT NULL DEFAULT 'info',
            message text NOT NULL,
            data longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_run_id (run_id),
            KEY idx_domain (primary_domain),
            KEY idx_level (level),
            KEY idx_created (created_at)
        ) $charset_collate;";
        dbDelta($sql_logs);

        // Tabla de presets
        $table_presets = self::get_presets_table();
        $sql_presets = "CREATE TABLE $table_presets (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            preset_key varchar(50) NOT NULL,
            name varchar(100) NOT NULL,
            description text DEFAULT NULL,
            payload longtext NOT NULL,
            is_default tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_preset_key (preset_key)
        ) $charset_collate;";
        dbDelta($sql_presets);

        // Tabla de runs de onboarding (nuevo sistema async)
        $table_runs = self::get_runs_table();
        $sql_runs = "CREATE TABLE $table_runs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            run_id varchar(36) NOT NULL,
            primary_domain varchar(255) NOT NULL,
            state varchar(30) NOT NULL DEFAULT 'queued',
            preset_key varchar(50) DEFAULT NULL,
            auto_update_ns tinyint(1) NOT NULL DEFAULT 0,
            zone_id varchar(32) DEFAULT NULL,
            nameservers text DEFAULT NULL,
            preset_applied tinyint(1) NOT NULL DEFAULT 0,
            preset_partial tinyint(1) NOT NULL DEFAULT 0,
            preset_errors text DEFAULT NULL,
            retries int(11) NOT NULL DEFAULT 0,
            error_message text DEFAULT NULL,
            started_at datetime NOT NULL,
            updated_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            failed_at datetime DEFAULT NULL,
            final_status varchar(20) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uk_run_id (run_id),
            KEY idx_domain (primary_domain),
            KEY idx_state (state),
            KEY idx_started (started_at)
        ) $charset_collate;";
        dbDelta($sql_runs);

        // Insertar presets por defecto si no existen
        self::insert_default_presets();
    }

    /**
     * Insertar presets por defecto
     * 
     * PRESETS v3.0 - Diciembre 2025
     * =============================
     * 
     * Incluye:
     * - Settings básicos (SSL, cache, rendimiento)
     * - AI Crawl Control (gestión de bots IA)
     * - Bot Management (Super Bot Fight Mode)
     * - Security Headers (Transform Rules)
     * - Firewall Rules (protección wp-admin, xmlrpc)
     * - Cache Rules (bypass dinámico, cache estático)
     * 
     * WordPress Básico:
     * - Prioriza velocidad y SEO
     * - Permite bots de IA para indexación
     * - Bot Fight Mode medio
     * - Firewall básico
     * 
     * WooCommerce:
     * - Protege transacciones y datos de clientes
     * - Bloquea bots IA en páginas sensibles
     * - Bot Fight Mode alto
     * - Firewall estricto
     */
    public static function insert_default_presets(): void {
        global $wpdb;
        $table = self::get_presets_table();

        // ================================================================
        // PRESET: WordPress Básico (sin WooCommerce) - v3.0
        // ================================================================
        $wp_preset = [
            'version' => '3.0',
            
            // === SETTINGS DE ZONA ===
            'settings' => [
                // --- Rendimiento ---
                'http3'                     => 'on',
                'brotli'                    => 'on',
                'rocket_loader'             => 'off',
                'early_hints'               => 'on',
                
                // --- SSL/TLS ---
                'always_use_https'          => 'on',
                'ssl'                       => 'strict',
                'min_tls_version'           => '1.2',
                'automatic_https_rewrites'  => 'on',
                'opportunistic_encryption'  => 'on',
                
                // --- Seguridad básica ---
                'security_level'            => 'medium',
                'security_header'           => [
                    'strict_transport_security' => [
                        'enabled'            => true,
                        'max_age'            => 31536000,
                        'include_subdomains' => true,
                        'nosniff'            => true,
                    ]
                ],
                
                // --- Cache ---
                'browser_cache_ttl'         => 14400,
                'development_mode'          => 'off'
            ],
            
            // === AI CRAWL CONTROL ===
            // Gestiona qué bots de IA pueden acceder al contenido
            'ai_crawlers' => [
                'enabled' => true,
                'bots' => [
                    'gptbot'        => 'allow',   // OpenAI GPT
                    'chatgpt-user'  => 'allow',   // ChatGPT navegando
                    'claudebot'     => 'allow',   // Anthropic Claude
                    'claude-web'    => 'allow',   // Claude navegando
                    'googlebot-ai'  => 'allow',   // Google AI/Bard
                    'google-extended' => 'allow', // Google AI training
                    'bingbot'       => 'allow',   // Microsoft Bing
                    'ccbot'         => 'block',   // Common Crawl (scraping masivo)
                    'bytespider'    => 'block',   // ByteDance/TikTok
                    'amazonbot'     => 'allow',   // Amazon Alexa
                    'facebookbot'   => 'allow',   // Meta
                    'cohere-ai'     => 'block',   // Cohere (entrenamiento)
                    'omgili'        => 'block',   // Scraper genérico
                    'diffbot'       => 'block'    // Scraper de datos
                ],
                'notes' => 'Permite bots de IA principales para SEO. Bloquea scrapers de entrenamiento.'
            ],
            
            // === BOT MANAGEMENT ===
            // Super Bot Fight Mode (Free tier)
            'bot_management' => [
                'enabled' => true,
                'fight_mode' => 'medium',          // off, low, medium, high
                'block_ai_bots' => false,          // No bloquear (usamos AI Crawl Control)
                'js_challenge_bad_bots' => true,   // Challenge a bots sospechosos
                'static_resource_protection' => false, // No proteger estáticos (ralentiza)
                'notes' => 'Bot Fight Mode medio para balance protección/usabilidad'
            ],
            
            // === SECURITY HEADERS (Transform Rules) ===
            'security_headers' => [
                'enabled' => true,
                'headers' => [
                    'X-Content-Type-Options'  => 'nosniff',
                    'X-Frame-Options'         => 'SAMEORIGIN',
                    'X-XSS-Protection'        => '1; mode=block',
                    'Referrer-Policy'         => 'strict-origin-when-cross-origin',
                    'Permissions-Policy'      => 'geolocation=(), microphone=(), camera=()'
                ],
                'notes' => 'Headers de seguridad estándar para WordPress'
            ],
            
            // === FIREWALL RULES ===
            'firewall_rules' => [
                'enabled' => true,
                'rules' => [
                    // Regla 1: Bloquear xmlrpc.php (vector de ataque común)
                    [
                        'name'        => 'Block XML-RPC',
                        'expression'  => '(http.request.uri.path contains "/xmlrpc.php")',
                        'action'      => 'block',
                        'enabled'     => true,
                        'notes'       => 'Bloquea XML-RPC (no necesario si usas REST API)'
                    ],
                    // Regla 2: Rate limit wp-login
                    [
                        'name'        => 'Rate Limit Login',
                        'expression'  => '(http.request.uri.path contains "/wp-login.php") and (http.request.method eq "POST")',
                        'action'      => 'challenge',
                        'enabled'     => true,
                        'notes'       => 'Challenge en intentos de login (anti brute-force)'
                    ],
                    // Regla 3: Proteger archivos sensibles
                    [
                        'name'        => 'Block Sensitive Files',
                        'expression'  => '(http.request.uri.path contains "wp-config") or (http.request.uri.path contains ".sql") or (http.request.uri.path contains ".log") or (http.request.uri.path eq "/.env")',
                        'action'      => 'block',
                        'enabled'     => true,
                        'notes'       => 'Bloquea acceso a archivos de configuración'
                    ]
                ],
                'notes' => '3 reglas básicas de firewall para WordPress'
            ],
            
            // === CACHE RULES ===
            'cache_rules' => [
                [
                    'name'   => 'Bypass WordPress Admin',
                    'if'     => '(http.request.uri.path contains "/wp-admin") or (http.request.uri.path contains "/wp-login.php") or (http.request.uri.path contains "/wp-cron.php")',
                    'action' => 'bypass',
                    'enabled' => true
                ],
                [
                    'name'   => 'Bypass WP AJAX y REST',
                    'if'     => '(http.request.uri.path contains "/wp-json") or (http.request.uri.path contains "admin-ajax.php") or (http.request.uri.path contains "wp-comments-post.php")',
                    'action' => 'bypass',
                    'enabled' => true
                ],
                [
                    'name'   => 'Bypass Previews y Feeds',
                    'if'     => '(http.request.uri.query contains "preview=true") or (http.request.uri.path contains "/feed") or (http.request.uri.query contains "s=")',
                    'action' => 'bypass',
                    'enabled' => true
                ],
                [
                    'name'   => 'Cache Estaticos 30 dias',
                    'if'     => '(http.request.uri.path.extension in {"css" "js" "jpg" "jpeg" "png" "gif" "webp" "avif" "ico" "svg" "woff" "woff2" "ttf" "eot" "otf"}) and not (http.request.uri.path contains "/wp-admin")',
                    'action' => 'cache_aggressive',
                    'enabled' => true
                ]
            ],
            
            'notes' => 'WordPress preset v3.0 - Cache agresivo, AI crawlers permitidos, firewall básico, headers de seguridad.'
        ];

        // ================================================================
        // PRESET: WooCommerce (Tienda Online) - v3.0
        // ================================================================
        $woo_preset = [
            'version' => '3.0',
            
            // === SETTINGS DE ZONA ===
            'settings' => [
                // --- Rendimiento ---
                'http3'                     => 'on',
                'brotli'                    => 'on',
                '0rtt'                      => 'on',
                'rocket_loader'             => 'off',  // Obligatorio off
                'polish'                    => 'lossless',
                'mirage'                    => 'on',
                'early_hints'               => 'on',
                
                // --- SSL/TLS (MÁS ESTRICTO) ---
                'always_use_https'          => 'on',
                'ssl'                       => 'strict',
                'min_tls_version'           => '1.2',
                'automatic_https_rewrites'  => 'on',
                'opportunistic_encryption'  => 'on',
                
                // --- Seguridad (MÁS ALTA) ---
                'security_level'            => 'high',
                'challenge_ttl'             => 3600,
                'browser_check'             => 'on',
                'email_obfuscation'         => 'on',
                'hotlink_protection'        => 'on',
                'ip_geolocation'            => 'on',
                'security_header'           => [
                    'strict_transport_security' => [
                        'enabled'            => true,
                        'max_age'            => 31536000,
                        'include_subdomains' => true,
                        'nosniff'            => true,
                    ]
                ],
                
                // --- Cache (MÁS CONSERVADOR) ---
                'browser_cache_ttl'         => 7200,
                'cache_level'               => 'standard',
                'development_mode'          => 'off',
                'always_online'             => 'off'  // Off para ecommerce
            ],
            
            // === AI CRAWL CONTROL ===
            // Más restrictivo para proteger datos de clientes
            'ai_crawlers' => [
                'enabled' => true,
                'bots' => [
                    'gptbot'        => 'allow',   // Permitir para productos
                    'chatgpt-user'  => 'allow',
                    'claudebot'     => 'allow',
                    'claude-web'    => 'allow',
                    'googlebot-ai'  => 'allow',
                    'google-extended' => 'block', // No entrenar con precios
                    'bingbot'       => 'allow',
                    'ccbot'         => 'block',
                    'bytespider'    => 'block',
                    'amazonbot'     => 'block',   // Competidor
                    'facebookbot'   => 'allow',
                    'cohere-ai'     => 'block',
                    'omgili'        => 'block',
                    'diffbot'       => 'block'
                ],
                'block_paths' => ['/cart', '/carrito', '/checkout', '/finalizar-compra', '/my-account', '/mi-cuenta'],
                'notes' => 'Bloquea AI en páginas sensibles (carrito, checkout, cuenta)'
            ],
            
            // === BOT MANAGEMENT ===
            // Más estricto para proteger transacciones
            'bot_management' => [
                'enabled' => true,
                'fight_mode' => 'high',            // Más estricto
                'block_ai_bots' => false,
                'js_challenge_bad_bots' => true,
                'static_resource_protection' => false,
                'notes' => 'Bot Fight Mode alto para proteger transacciones'
            ],
            
            // === SECURITY HEADERS ===
            'security_headers' => [
                'enabled' => true,
                'headers' => [
                    'X-Content-Type-Options'  => 'nosniff',
                    'X-Frame-Options'         => 'DENY',  // Más estricto
                    'X-XSS-Protection'        => '1; mode=block',
                    'Referrer-Policy'         => 'strict-origin-when-cross-origin',
                    'Permissions-Policy'      => 'geolocation=(), microphone=(), camera=(), payment=(self)'
                ],
                'notes' => 'Headers estrictos para ecommerce. Frame DENY para evitar clickjacking.'
            ],
            
            // === FIREWALL RULES ===
            'firewall_rules' => [
                'enabled' => true,
                'rules' => [
                    [
                        'name'        => 'Block XML-RPC',
                        'expression'  => '(http.request.uri.path contains "/xmlrpc.php")',
                        'action'      => 'block',
                        'enabled'     => true,
                        'notes'       => 'Bloquea XML-RPC'
                    ],
                    [
                        'name'        => 'Rate Limit Login',
                        'expression'  => '(http.request.uri.path contains "/wp-login.php") and (http.request.method eq "POST")',
                        'action'      => 'challenge',
                        'enabled'     => true,
                        'notes'       => 'Challenge en login'
                    ],
                    [
                        'name'        => 'Block Sensitive Files',
                        'expression'  => '(http.request.uri.path contains "wp-config") or (http.request.uri.path contains ".sql") or (http.request.uri.path contains ".log") or (http.request.uri.path eq "/.env")',
                        'action'      => 'block',
                        'enabled'     => true,
                        'notes'       => 'Bloquea archivos sensibles'
                    ],
                    [
                        'name'        => 'Protect Checkout',
                        'expression'  => '((http.request.uri.path contains "/checkout") or (http.request.uri.path contains "/finalizar-compra")) and (not http.request.method in {"GET" "POST"})',
                        'action'      => 'block',
                        'enabled'     => true,
                        'notes'       => 'Solo GET/POST en checkout'
                    ],
                    [
                        'name'        => 'Rate Limit WC-AJAX',
                        'expression'  => '(http.request.uri.query contains "wc-ajax") and (cf.threat_score gt 10)',
                        'action'      => 'challenge',
                        'enabled'     => true,
                        'notes'       => 'Challenge en AJAX sospechoso'
                    ]
                ],
                'notes' => '5 reglas de firewall para WooCommerce'
            ],
            
            // === CACHE RULES ===
            'cache_rules' => [
                [
                    'name'   => 'Bypass WordPress Admin',
                    'if'     => '(http.request.uri.path contains "/wp-admin") or (http.request.uri.path contains "/wp-login.php") or (http.request.uri.path contains "/wp-cron.php")',
                    'action' => 'bypass',
                    'enabled' => true
                ],
                [
                    'name'   => 'Bypass WP AJAX y REST',
                    'if'     => '(http.request.uri.path contains "/wp-json") or (http.request.uri.path contains "admin-ajax.php")',
                    'action' => 'bypass',
                    'enabled' => true
                ],
                [
                    'name'   => 'Bypass WooCommerce Dinamico',
                    'if'     => '(http.request.uri.path contains "/cart") or (http.request.uri.path contains "/carrito") or (http.request.uri.path contains "/checkout") or (http.request.uri.path contains "/finalizar-compra") or (http.request.uri.path contains "/my-account") or (http.request.uri.path contains "/mi-cuenta")',
                    'action' => 'bypass',
                    'enabled' => true
                ],
                [
                    'name'   => 'Bypass WC-AJAX',
                    'if'     => '(http.request.uri.query contains "wc-ajax") or (http.request.uri.query contains "add-to-cart") or (http.request.uri.query contains "remove_item")',
                    'action' => 'bypass',
                    'enabled' => true
                ],
                [
                    'name'   => 'Bypass Usuarios Logueados WC',
                    'if'     => '(http.cookie contains "woocommerce_cart_hash") or (http.cookie contains "woocommerce_items_in_cart") or (http.cookie contains "wp_woocommerce_session") or (http.cookie contains "wordpress_logged_in")',
                    'action' => 'bypass',
                    'enabled' => true
                ],
                [
                    'name'   => 'Bypass Pagos y Webhooks',
                    'if'     => '(http.request.uri.path contains "/wc-api") or (http.request.uri.path contains "paypal") or (http.request.uri.path contains "stripe") or (http.request.uri.path contains "redsys") or (http.request.uri.path contains "/payment")',
                    'action' => 'bypass',
                    'enabled' => true
                ],
                [
                    'name'   => 'Cache Imagenes Productos',
                    'if'     => '(http.request.uri.path.extension in {"jpg" "jpeg" "png" "gif" "webp" "avif"}) and (http.request.uri.path contains "/uploads/")',
                    'action' => 'cache',
                    'enabled' => true
                ],
                [
                    'name'   => 'Cache Assets Estaticos',
                    'if'     => '(http.request.uri.path.extension in {"css" "js" "woff" "woff2" "ttf" "eot" "otf" "ico" "svg"}) and not (http.request.uri.path contains "/wp-admin")',
                    'action' => 'cache_aggressive',
                    'enabled' => true
                ]
            ],
            
            'notes' => 'WooCommerce preset v3.0 - Seguridad alta, bypass checkout, AI restringido, firewall estricto.'
        ];

        // Insertar o ACTUALIZAR si ya existen
        $existing_wp = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE preset_key = %s",
            'wp'
        ));

        if (!$existing_wp) {
            $wpdb->insert($table, [
                'preset_key'  => 'wp',
                'name'        => 'WordPress Básico',
                'description' => 'Configuración optimizada para sitios WordPress (v3.0 - AI, Bot, Firewall)',
                'payload'     => json_encode($wp_preset),
                'is_default'  => 1
            ]);
        } else {
            // Actualizar a v3.0 si la versión es antigua
            $current = $wpdb->get_var($wpdb->prepare(
                "SELECT payload FROM $table WHERE preset_key = %s",
                'wp'
            ));
            $current_data = json_decode($current, true);
            if (($current_data['version'] ?? '1.0') < '3.0') {
                $wpdb->update($table, [
                    'description' => 'Configuración optimizada para sitios WordPress (v3.0 - AI, Bot, Firewall)',
                    'payload'     => json_encode($wp_preset)
                ], ['preset_key' => 'wp']);
            }
        }

        $existing_woo = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE preset_key = %s",
            'woo'
        ));

        if (!$existing_woo) {
            $wpdb->insert($table, [
                'preset_key'  => 'woo',
                'name'        => 'WooCommerce',
                'description' => 'Configuración optimizada para tiendas WooCommerce (v3.0 - AI, Bot, Firewall)',
                'payload'     => json_encode($woo_preset),
                'is_default'  => 0
            ]);
        } else {
            $current = $wpdb->get_var($wpdb->prepare(
                "SELECT payload FROM $table WHERE preset_key = %s",
                'woo'
            ));
            $current_data = json_decode($current, true);
            if (($current_data['version'] ?? '1.0') < '3.0') {
                $wpdb->update($table, [
                    'description' => 'Configuración optimizada para tiendas WooCommerce (v3.0 - AI, Bot, Firewall)',
                    'payload'     => json_encode($woo_preset)
                ], ['preset_key' => 'woo']);
            }
        }
    }

    /**
     * Estados válidos de onboarding
     */
    public static function get_valid_states(): array {
        return [
            'none',           // Sin procesar
            'in_cf',          // Ya existía en CF antes del onboarding
            'pending',        // En cola para procesar
            'running',        // Ejecutándose
            'onboarded',      // Completado exitosamente
            'error',          // Error en el proceso
            'needs_manual_ns',// Requiere cambio manual de NS
            'partial'         // Completado parcialmente (algunos settings no aplicados)
        ];
    }

    /**
     * Obtener estado de onboarding de un dominio
     */
    public static function get_onboarding_state(string $primary_domain): ?array {
        global $wpdb;
        $table = self::get_onboarding_table();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE primary_domain = %s",
            strtolower(trim($primary_domain))
        ), ARRAY_A);
    }

    /**
     * Crear o actualizar estado de onboarding
     */
    public static function upsert_onboarding(string $primary_domain, array $data): bool {
        global $wpdb;
        $table = self::get_onboarding_table();
        $primary_domain = strtolower(trim($primary_domain));

        $existing = self::get_onboarding_state($primary_domain);

        if ($existing) {
            $data['updated_at'] = current_time('mysql');
            return $wpdb->update($table, $data, ['primary_domain' => $primary_domain]) !== false;
        } else {
            $data['primary_domain'] = $primary_domain;
            $data['created_at'] = current_time('mysql');
            return $wpdb->insert($table, $data) !== false;
        }
    }

    /**
     * Actualizar estado de onboarding
     */
    public static function update_state(string $primary_domain, string $state, ?string $error = null): bool {
        $data = ['state' => $state];
        
        if ($error !== null) {
            $data['last_error'] = $error;
        }
        
        if ($state === 'onboarded' || $state === 'partial') {
            $data['applied_at'] = current_time('mysql');
        }

        return self::upsert_onboarding($primary_domain, $data);
    }

    /**
     * Registrar log de ejecución
     */
    public static function log(string $run_id, string $primary_domain, string $step, string $level, string $message, ?array $data = null): void {
        global $wpdb;
        $table = self::get_logs_table();

        $wpdb->insert($table, [
            'run_id'         => $run_id,
            'primary_domain' => strtolower(trim($primary_domain)),
            'step'           => $step,
            'level'          => $level,
            'message'        => $message,
            'data'           => $data ? json_encode($data) : null,
            'created_at'     => current_time('mysql')
        ]);
    }

    /**
     * Obtener logs de una ejecución
     */
    public static function get_logs_by_run(string $run_id): array {
        global $wpdb;
        $table = self::get_logs_table();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE run_id = %s ORDER BY created_at ASC",
            $run_id
        ), ARRAY_A) ?: [];
    }

    /**
     * Obtener logs de un dominio
     */
    public static function get_logs_by_domain(string $primary_domain, int $limit = 50): array {
        global $wpdb;
        $table = self::get_logs_table();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE primary_domain = %s ORDER BY created_at DESC LIMIT %d",
            strtolower(trim($primary_domain)),
            $limit
        ), ARRAY_A) ?: [];
    }

    /**
     * Obtener todos los presets
     */
    public static function get_presets(): array {
        global $wpdb;
        $table = self::get_presets_table();

        // Auto-crear tabla si no existe
        self::ensure_presets_table_exists();

        return $wpdb->get_results(
            "SELECT * FROM $table ORDER BY is_default DESC, name ASC",
            ARRAY_A
        ) ?: [];
    }

    /**
     * Obtener un preset por key
     */
    public static function get_preset(string $preset_key): ?array {
        global $wpdb;
        $table = self::get_presets_table();

        // Auto-crear tabla si no existe
        self::ensure_presets_table_exists();

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE preset_key = %s",
            $preset_key
        ), ARRAY_A);

        if ($row && !empty($row['payload'])) {
            $row['payload_decoded'] = json_decode($row['payload'], true);
        }

        return $row;
    }

    /**
     * Asegurar que la tabla de presets existe
     */
    private static function ensure_presets_table_exists(): void {
        global $wpdb;
        static $checked = false;
        
        // Solo verificar una vez por request
        if ($checked) {
            return;
        }
        $checked = true;
        
        $table = self::get_presets_table();
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        
        if (!$table_exists) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE IF NOT EXISTS $table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                preset_key varchar(50) NOT NULL,
                name varchar(100) NOT NULL,
                description text DEFAULT NULL,
                payload longtext NOT NULL,
                is_default tinyint(1) NOT NULL DEFAULT 0,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_preset_key (preset_key)
            ) $charset_collate;";
            
            $wpdb->query($sql);
            
            // Insertar presets por defecto
            self::insert_default_presets();
        }
    }

    /**
     * Obtener dominios pendientes de onboarding
     */
    public static function get_pending_domains(): array {
        global $wpdb;
        $table = self::get_onboarding_table();

        return $wpdb->get_results(
            "SELECT * FROM $table WHERE state IN ('queued', 'pending') ORDER BY updated_at ASC",
            ARRAY_A
        ) ?: [];
    }

    /**
     * Limpiar logs antiguos (más de 30 días)
     */
    public static function cleanup_old_logs(int $days = 30): int {
        global $wpdb;
        $table = self::get_logs_table();

        return $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }

    // ========================================
    // MÉTODOS PARA EL NUEVO SISTEMA ASYNC DE RUNS
    // ========================================

    /**
     * Inicializar un nuevo run de onboarding
     */
    public static function init_onboarding_run(string $run_id, string $primary_domain, array $data): bool {
        global $wpdb;
        $table = self::get_runs_table();

        $result = $wpdb->insert($table, [
            'run_id' => $run_id,
            'primary_domain' => $primary_domain,
            'state' => $data['state'] ?? 'queued',
            'preset_key' => $data['preset_key'] ?? null,
            'auto_update_ns' => $data['auto_update_ns'] ? 1 : 0,
            'retries' => $data['retries'] ?? 0,
            'started_at' => $data['started_at'] ?? current_time('mysql')
        ]);

        return $result !== false;
    }

    /**
     * Obtener datos de un run específico
     */
    public static function get_run_data(string $run_id): ?array {
        global $wpdb;
        $table = self::get_runs_table();

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE run_id = %s",
            $run_id
        ), ARRAY_A);

        return $result ?: null;
    }

    /**
     * Actualizar datos de un run
     */
    public static function update_run_data(string $run_id, array $data): bool {
        global $wpdb;
        $table = self::get_runs_table();

        // Asegurar que updated_at se actualice
        $data['updated_at'] = current_time('mysql');

        $result = $wpdb->update($table, $data, ['run_id' => $run_id]);

        return $result !== false;
    }

    /**
     * Obtener runs activos (no completados ni fallidos)
     */
    public static function get_active_runs(): array {
        global $wpdb;
        $table = self::get_runs_table();

        return $wpdb->get_results(
            "SELECT * FROM $table 
             WHERE state NOT IN ('completed', 'failed') 
             ORDER BY started_at ASC",
            ARRAY_A
        ) ?: [];
    }

    /**
     * Limpiar runs antiguos completados (más de 7 días)
     */
    public static function cleanup_old_runs(int $days = 7): int {
        global $wpdb;
        $table = self::get_runs_table();

        return $wpdb->query($wpdb->prepare(
            "DELETE FROM $table 
             WHERE (completed_at < DATE_SUB(NOW(), INTERVAL %d DAY) OR failed_at < DATE_SUB(NOW(), INTERVAL %d DAY))
             AND state IN ('completed', 'failed')",
            $days, $days
        ));
    }

    /**
     * Obtener el run más reciente de un dominio
     * 
     * @param string $domain Nombre del dominio
     * @return array|null Datos del run o null
     */
    public static function get_latest_run_by_domain(string $domain): ?array {
        global $wpdb;
        $table = self::get_runs_table();

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE primary_domain = %s ORDER BY started_at DESC LIMIT 1",
            $domain
        ), ARRAY_A);

        return $result ?: null;
    }

    /**
     * Generar run_id único
     */
    public static function generate_run_id(): string {
        return wp_generate_uuid4();
    }

    /**
     * Obtener todos los presets disponibles
     */
    public static function get_all_presets(): array {
        global $wpdb;
        $table = self::get_presets_table();

        $results = $wpdb->get_results(
            "SELECT * FROM $table ORDER BY name ASC",
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Crear o actualizar un preset
     */
    public static function save_preset(string $preset_key, string $name, string $description, array $payload, bool $is_default = false): bool {
        global $wpdb;
        $table = self::get_presets_table();

        $data = [
            'preset_key' => $preset_key,
            'name' => $name,
            'description' => $description,
            'payload' => wp_json_encode($payload),
            'is_default' => $is_default ? 1 : 0,
            'updated_at' => current_time('mysql')
        ];

        // Intentar insertar primero
        $result = $wpdb->insert($table, $data);

        // Si falla por clave duplicada, actualizar
        if (!$result && $wpdb->last_error && strpos($wpdb->last_error, 'Duplicate entry') !== false) {
            unset($data['preset_key']); // No actualizar la key
            $result = $wpdb->update($table, $data, ['preset_key' => $preset_key]);
        }

        return $result !== false;
    }

    /**
     * Actualizar presets existentes con versiones nuevas
     * Se ejecuta en activación del plugin para mantener presets actualizados
     */
    public static function update_existing_presets(): void {
        global $wpdb;
        $table = self::get_presets_table();

        // Obtener presets actuales
        $existing_presets = $wpdb->get_results(
            "SELECT preset_key, payload FROM $table",
            ARRAY_A
        );

        if (empty($existing_presets)) {
            return;
        }

        $existing_keys = array_column($existing_presets, 'preset_key');

        // Si existe el preset 'wp', verificar si necesita actualización
        if (in_array('wp', $existing_keys)) {
            $wp_preset = array_filter($existing_presets, function($p) {
                return $p['preset_key'] === 'wp';
            });
            $wp_preset = reset($wp_preset);

            $current_payload = json_decode($wp_preset['payload'], true);
            $current_version = $current_payload['version'] ?? '1.0';

            // Si la versión es anterior a 3.0, actualizar
            if (version_compare($current_version, '3.0', '<')) {
                self::insert_default_presets(); // Esto actualizará el preset existente
                error_log('[DR Onboarding] Preset WP actualizado de v' . $current_version . ' a v3.0');
            }
        }

        // Si existe el preset 'debug-test', verificar si necesita actualización
        if (in_array('debug-test', $existing_keys)) {
            $debug_preset = array_filter($existing_presets, function($p) {
                return $p['preset_key'] === 'debug-test';
            });
            $debug_preset = reset($debug_preset);

            $current_payload = json_decode($debug_preset['payload'], true);
            $current_version = $current_payload['version'] ?? '1.0';

            // Si la versión es anterior a 3.0, actualizar
            if (version_compare($current_version, '3.0', '<')) {
                self::insert_default_presets(); // Esto actualizará el preset existente
                error_log('[DR Onboarding] Preset debug-test actualizado de v' . $current_version . ' a v3.0');
            }
        }
    }

    // ========================================
    // SISTEMA DE ACTIVITY LOG
    // ========================================

    /**
     * Obtener nombre de tabla activity log
     */
    public static function get_activity_log_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'dr_activity_log';
    }

    /**
     * Crear tabla de activity log
     */
    public static function create_activity_log_table(): void {
        global $wpdb;
        $table = self::get_activity_log_table();
        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $sql = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            action_type varchar(50) NOT NULL,
            domain varchar(255) DEFAULT NULL,
            description text NOT NULL,
            details longtext DEFAULT NULL,
            user_id bigint(20) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_action_type (action_type),
            KEY idx_domain (domain),
            KEY idx_created (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }

    /**
     * Registrar actividad en el log
     * 
     * @param string $action_type Tipo: cf_migration, endpoint_deploy, ns_update, php_scan, etc.
     * @param string|null $domain Dominio relacionado (opcional)
     * @param string $description Descripción legible
     * @param array $details Detalles adicionales en JSON
     */
    public static function log_activity(string $action_type, ?string $domain, string $description, array $details = []): bool {
        global $wpdb;
        $table = self::get_activity_log_table();

        // Verificar que la tabla existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            self::create_activity_log_table();
        }

        return $wpdb->insert($table, [
            'action_type' => $action_type,
            'domain' => $domain,
            'description' => $description,
            'details' => !empty($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            'user_id' => get_current_user_id() ?: null,
            'created_at' => current_time('mysql'),
        ]) !== false;
    }

    /**
     * Obtener actividad reciente
     * 
     * @param int $limit Número de registros
     * @param string|null $action_type Filtrar por tipo
     * @param string|null $domain Filtrar por dominio
     */
    public static function get_recent_activity(int $limit = 50, ?string $action_type = null, ?string $domain = null): array {
        global $wpdb;
        $table = self::get_activity_log_table();

        // Verificar que la tabla existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return [];
        }

        $where = [];
        $params = [];

        if ($action_type) {
            $where[] = 'action_type = %s';
            $params[] = $action_type;
        }
        if ($domain) {
            $where[] = 'domain = %s';
            $params[] = $domain;
        }

        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $params[] = $limit;

        $sql = "SELECT * FROM $table $where_sql ORDER BY created_at DESC LIMIT %d";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }

        $results = $wpdb->get_results($sql, ARRAY_A) ?: [];

        // Decodificar details JSON
        foreach ($results as &$row) {
            if (!empty($row['details'])) {
                $row['details'] = json_decode($row['details'], true);
            }
        }

        return $results;
    }

    /**
     * Limpiar activity log antiguo
     */
    public static function cleanup_activity_log(int $days = 90): int {
        global $wpdb;
        $table = self::get_activity_log_table();

        return $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }
}
