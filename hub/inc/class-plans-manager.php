<?php
/**
 * Plans Manager Class
 * Handles maintenance plans and their features
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPHUB_Plans_Manager {
    
    private $table_plans;
    private $table_plan_features;
    
    public function __construct() {
        global $wpdb;
        $this->table_plans = $wpdb->prefix . 'rphub_plans';
        $this->table_plan_features = $wpdb->prefix . 'rphub_plan_features';
        
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // AJAX hooks for plan management
        add_action('wp_ajax_rphub_get_plans',             [$this, 'ajax_get_plans']);
        add_action('wp_ajax_rphub_create_plan',           [$this, 'ajax_create_plan']);
        add_action('wp_ajax_rphub_update_plan',           [$this, 'ajax_update_plan']);
        add_action('wp_ajax_rphub_delete_plan',           [$this, 'ajax_delete_plan']);
        add_action('wp_ajax_rphub_get_plan_features',     [$this, 'ajax_get_plan_features']);
        add_action('wp_ajax_rphub_update_plan_features',  [$this, 'ajax_update_plan_features']);
        // Ecommerce addon management
        add_action('wp_ajax_rphub_toggle_ecommerce_addon',[$this, 'ajax_toggle_ecommerce_addon']);
        add_action('wp_ajax_rphub_get_site_addons',       [$this, 'ajax_get_site_addons']);

        // Run DB migrations once per version bump
        add_action('admin_init', [$this, 'maybe_run_migrations']);
    }
    
    // -------------------------------------------------------------------------
    // DB migrations
    // -------------------------------------------------------------------------

    public function maybe_run_migrations() {
        $db_ver = get_option('rphub_plans_db_ver', '1.0');
        if (version_compare($db_ver, '1.1', '<')) {
            $this->migrate_1_1();
            update_option('rphub_plans_db_ver', '1.1');
        }
        if (version_compare($db_ver, '1.2', '<')) {
            $this->migrate_1_2();
            update_option('rphub_plans_db_ver', '1.2');
        }
    }

    /** Add is_addon + ecommerce spec features to existing plans */
    private function migrate_1_1() {
        global $wpdb;

        // Add is_addon column if missing
        $col = $wpdb->get_results("SHOW COLUMNS FROM {$this->table_plans} LIKE 'is_addon'");
        if (empty($col)) {
            $wpdb->query("ALTER TABLE {$this->table_plans} ADD COLUMN is_addon tinyint(1) NOT NULL DEFAULT 0 AFTER is_active");
        }

        // Upsert spec-aligned features for each base plan
        $spec_features = [
            'semilla' => [
                ['uptime_interval',   'Intervalo uptime',       'Frecuencia del check de disponibilidad (minutos)', '5'],
                ['ssl_alert_days',    'Alerta SSL (días)',       'Días de antelación para alertar sobre expiración SSL', '15'],
                ['staging_mode',      'Modo staging',           'rollback = backup previo y restauración si falla', 'rollback'],
                ['backup_destination','Destino backup externo', 'Servicio de backup externo', 'backblaze_b2'],
                ['backup_frequency',  'Frecuencia backup',      'Frecuencia del backup externo', 'weekly'],
                ['support_sla_hours', 'SLA soporte (horas)',    'Horas máximas de respuesta al cliente', '48'],
            ],
            'raiz' => [
                ['uptime_interval',   'Intervalo uptime',       'Frecuencia del check de disponibilidad (minutos)', '1'],
                ['ssl_alert_days',    'Alerta SSL (días)',       'Días de antelación para alertar sobre expiración SSL', '30'],
                ['staging_mode',      'Modo staging',           'conditional = staging cuando risk_score > umbral', 'conditional'],
                ['backup_destination','Destino backup externo', 'Servicio de backup externo', 'backblaze_b2'],
                ['backup_frequency',  'Frecuencia backup',      'Frecuencia del backup externo', 'daily'],
                ['support_sla_hours', 'SLA soporte (horas)',    'Horas máximas de respuesta al cliente', '24'],
                ['support_sla_critical_hours', 'SLA crítico (horas)', 'SLA para incidencias críticas', '4'],
                ['cloudflare_managed','Cloudflare gestionado',  'Gestión continua de reglas y caché CF', 'true'],
                ['risk_scorer',       'Análisis riesgo IA',     'Análisis de changelogs con Claude antes de actualizar', 'true'],
            ],
            'ecosistema' => [
                ['uptime_interval',   'Intervalo uptime',       'Frecuencia del check de disponibilidad (minutos)', '1'],
                ['ssl_alert_days',    'Alerta SSL (días)',       'Días de antelación para alertar sobre expiración SSL', '30'],
                ['staging_mode',      'Modo staging',           'always = staging en todas las actualizaciones', 'always'],
                ['backup_destination','Destino backup externo', 'Servicio de backup externo', 'backblaze_b2'],
                ['backup_frequency',  'Frecuencia backup',      'Frecuencia del backup externo', 'daily_dual'],
                ['backup_retention_days', 'Retención backup',   'Días de retención de backups externos', '60'],
                ['support_sla_hours', 'SLA soporte (horas)',    'Horas máximas de respuesta al cliente', '8'],
                ['support_sla_critical_hours', 'SLA crítico (horas)', 'SLA para incidencias críticas', '2'],
                ['cloudflare_managed','Cloudflare gestionado',  'Gestión continua avanzada de reglas y caché CF', 'advanced'],
                ['risk_scorer',       'Análisis riesgo IA',     'Análisis de changelogs con Claude antes de actualizar', 'true'],
                ['consulting_quarterly', 'Consultoría trimestral', 'Reunión técnica de roadmap cada trimestre', 'true'],
            ],
        ];

        foreach ($spec_features as $slug => $features) {
            $plan_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->table_plans} WHERE slug = %s", $slug
            ));
            if (!$plan_id) {
                continue;
            }
            foreach ($features as $idx => $f) {
                // Only insert if key doesn't already exist for this plan
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$this->table_plan_features} WHERE plan_id = %d AND feature_key = %s",
                    $plan_id, $f[0]
                ));
                if (!$exists) {
                    $wpdb->insert($this->table_plan_features, [
                        'plan_id'             => $plan_id,
                        'feature_key'         => $f[0],
                        'feature_name'        => $f[1],
                        'feature_description' => $f[2],
                        'feature_value'       => $f[3],
                        'is_active'           => 1,
                        'display_order'       => 100 + $idx,
                    ]);
                } else {
                    // Update value to keep in sync with specs
                    $wpdb->update(
                        $this->table_plan_features,
                        ['feature_value' => $f[3]],
                        ['id' => $exists]
                    );
                }
            }
        }
    }

    /** Insert ecommerce addon plan */
    private function migrate_1_2() {
        global $wpdb;

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_plans} WHERE slug = %s", 'ecommerce_addon'
        ));
        if ($exists) {
            return;
        }

        $addon_id = $wpdb->insert($this->table_plans, [
            'slug'           => 'ecommerce_addon',
            'name'           => 'Modificador Ecommerce',
            'description'    => 'Add-on para WooCommerce activo: backups cada 12h, staging siempre, monitorización checkout, SLA 30 min en críticos.',
            'price'          => 35.00,
            'billing_period' => 'monthly',
            'order_url'      => '',
            'is_active'      => 1,
            'is_addon'       => 1,
        ]);

        if (!$addon_id) {
            return;
        }

        $addon_id = $wpdb->insert_id;
        $features = [
            ['backup_frequency',      'Backup cada 12h',            'Copia de seguridad externa cada 12 horas', 'every_12h'],
            ['staging_mode',          'Staging siempre',            'Staging obligatorio antes de cualquier actualización', 'always'],
            ['update_window',         'Ventana de actualización',   'Actualizaciones fuera del horario de pico (análisis de tráfico)', 'off_peak'],
            ['checkout_monitoring',   'Monitorización checkout',    'Vigilancia del flujo carrito → pago → confirmación', 'true'],
            ['support_sla_critical_hours', 'SLA crítico 30 min',   'Respuesta máxima en errores de checkout', '0.5'],
            ['backup_retention_extra','Retención extra',            'Retención adicional de 90 días para registros fiscales', '90'],
            ['post_update_test',      'Test post-actualización',    'Verifica carrito → checkout → confirmación tras cada update', 'checkout_flow'],
        ];

        foreach ($features as $idx => $f) {
            $wpdb->insert($this->table_plan_features, [
                'plan_id'             => $addon_id,
                'feature_key'         => $f[0],
                'feature_name'        => $f[1],
                'feature_description' => $f[2],
                'feature_value'       => $f[3],
                'is_active'           => 1,
                'display_order'       => $idx + 1,
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Ecommerce addon — site-level management
    // -------------------------------------------------------------------------

    /**
     * Check if a site has the ecommerce addon active.
     */
    public function site_has_ecommerce_addon($site_id) {
        return $this->site_has_addon($site_id, 'ecommerce');
    }

    public function site_has_addon($site_id, $addon_slug) {
        $addons = RPHUB_Database::get_site_meta($site_id, 'active_addons');
        if (!is_array($addons)) {
            return false;
        }
        return in_array($addon_slug, $addons, true);
    }

    public function get_site_addons($site_id) {
        return RPHUB_Database::get_site_meta($site_id, 'active_addons') ?: [];
    }

    /**
     * Enable or disable an addon for a site.
     */
    public function toggle_site_addon($site_id, $addon_slug, $enable) {
        $addons = $this->get_site_addons($site_id);
        $key    = array_search($addon_slug, $addons, true);

        if ($enable && $key === false) {
            $addons[] = $addon_slug;
        } elseif (!$enable && $key !== false) {
            array_splice($addons, $key, 1);
        }

        RPHUB_Database::update_site_meta($site_id, 'active_addons', array_values($addons));
        return true;
    }

    // -------------------------------------------------------------------------

    /**
     * Create database tables for plans
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Plans table
        $table_plans = $wpdb->prefix . 'rphub_plans';
        $sql_plans = "CREATE TABLE $table_plans (
            id int(11) NOT NULL AUTO_INCREMENT,
            slug varchar(50) NOT NULL,
            name varchar(100) NOT NULL,
            description text,
            price decimal(10,2) NOT NULL,
            billing_period varchar(20) DEFAULT 'monthly',
            order_url varchar(500),
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        // Plan features table
        $table_plan_features = $wpdb->prefix . 'rphub_plan_features';
        $sql_plan_features = "CREATE TABLE $table_plan_features (
            id int(11) NOT NULL AUTO_INCREMENT,
            plan_id int(11) NOT NULL,
            feature_key varchar(100) NOT NULL,
            feature_name varchar(200) NOT NULL,
            feature_description text,
            feature_value varchar(500),
            is_active tinyint(1) DEFAULT 1,
            display_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY plan_id (plan_id),
            KEY feature_key (feature_key),
            KEY display_order (display_order),
            FOREIGN KEY (plan_id) REFERENCES $table_plans(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_plans);
        dbDelta($sql_plan_features);
        
        // Insert default plans
        self::insert_default_plans();
    }
    
    /**
     * Insert default Replanta plans
     */
    private static function insert_default_plans() {
        global $wpdb;
        
        $table_plans = $wpdb->prefix . 'rphub_plans';
        $table_plan_features = $wpdb->prefix . 'rphub_plan_features';
        
        // Check if plans already exist
        $existing_plans = $wpdb->get_var("SELECT COUNT(*) FROM $table_plans");
        if ($existing_plans > 0) {
            return;
        }
        
        // Plan Semilla
        $semilla_id = $wpdb->insert($table_plans, [
            'slug' => 'semilla',
            'name' => 'Plan Semilla',
            'description' => 'Ideal para webs pequeñas pero importantes',
            'price' => 49.00,
            'billing_period' => 'monthly',
            'order_url' => 'https://clientes.replanta.dev/order/product?pid=2e071d93-1d5e-4689-305b-646028758396',
            'is_active' => 1
        ]);
        $semilla_id = $wpdb->insert_id;
        
        // Plan Raíz
        $raiz_id = $wpdb->insert($table_plans, [
            'slug' => 'raiz',
            'name' => 'Plan Raíz',
            'description' => 'Para empresas que viven de su web',
            'price' => 89.00,
            'billing_period' => 'monthly',
            'order_url' => 'https://clientes.replanta.dev/order/product?pid=d5308768-251d-4852-057a-147e390921e6',
            'is_active' => 1
        ]);
        $raiz_id = $wpdb->insert_id;
        
        // Plan Ecosistema
        $ecosistema_id = $wpdb->insert($table_plans, [
            'slug' => 'ecosistema',
            'name' => 'Plan Ecosistema',
            'description' => 'Para proyectos que exigen velocidad y evolución',
            'price' => 149.00,
            'billing_period' => 'monthly',
            'order_url' => 'https://clientes.replanta.dev/order/product?pid=2e071d93-1d5e-4689-088f-646028758396',
            'is_active' => 1
        ]);
        $ecosistema_id = $wpdb->insert_id;
        
        // Features for Plan Semilla
        $semilla_features = [
            ['updates_frequency', 'Actualizaciones mensuales', 'Revisamos y actualizamos WordPress, plugins y temas de forma segura una vez al mes', 'monthly'],
            ['backup_frequency', 'Copias de seguridad semanales', 'Se generan backups automáticos y seguros cada semana para que nunca pierdas tu web', 'weekly'],
            ['wpo_optimization', 'Optimización básica WPO', 'Mejoramos la velocidad de carga eliminando archivos innecesarios y afinando recursos estáticos', 'basic'],
            ['performance_review', 'Revisión trimestral de rendimiento', 'Analizamos cada 3 meses el estado técnico y rendimiento general de tu web', 'quarterly'],
            ['support_type', 'Soporte por email', 'Puedes escribirnos con dudas o incidencias, y te respondemos con soluciones prácticas', 'email'],
            ['dashboard_access', 'Dashboard básico', 'Acceso al dashboard de estado básico del Care plugin', 'true'],
            ['update_control', 'Actualizaciones controladas', 'El cliente no puede actualizar plugins por su cuenta, excepto los de pago/licencia', 'true']
        ];
        
        foreach ($semilla_features as $index => $feature) {
            $wpdb->insert($table_plan_features, [
                'plan_id' => $semilla_id,
                'feature_key' => $feature[0],
                'feature_name' => $feature[1],
                'feature_description' => $feature[2],
                'feature_value' => $feature[3],
                'is_active' => 1,
                'display_order' => $index + 1
            ]);
        }
        
        // Features for Plan Raíz (includes all Semilla features plus new ones)
        $raiz_features = array_merge($semilla_features, [
            ['updates_frequency', 'Actualizaciones semanales', 'Actualizamos el core, plugins y temas una vez por semana para máxima seguridad y compatibilidad', 'weekly'],
            ['support_type', 'Soporte prioritario', 'Atendemos tus consultas antes que al resto de clientes. Tu web es prioridad', 'priority'],
            ['monitoring', 'Monitorización 24/7', 'Vigilamos tu sitio constantemente para detectar caídas, errores o ataques de forma proactiva', 'true'],
            ['seo_wpo_review', 'Revisión SEO + WPO mensual', 'Revisamos mensualmente tu posicionamiento y velocidad, aplicando mejoras si lo necesitas', 'monthly'],
            ['monthly_reports', 'Informes de estado mensuales', 'Te enviamos un resumen mensual con incidencias resueltas, estado de tu web y recomendaciones', 'true'],
            ['dashboard_access', 'Dashboard avanzado', 'Acceso completo al dashboard con métricas avanzadas y reportes', 'advanced']
        ]);
        
        // Update Raíz features (remove duplicates and add new ones)
        $raiz_unique_features = [
            ['updates_frequency', 'Actualizaciones semanales', 'Actualizamos el core, plugins y temas una vez por semana para máxima seguridad y compatibilidad', 'weekly'],
            ['backup_frequency', 'Copias de seguridad semanales', 'Se generan backups automáticos y seguros cada semana para que nunca pierdas tu web', 'weekly'],
            ['wpo_optimization', 'Optimización básica WPO', 'Mejoramos la velocidad de carga eliminando archivos innecesarios y afinando recursos estáticos', 'basic'],
            ['performance_review', 'Revisión trimestral de rendimiento', 'Analizamos cada 3 meses el estado técnico y rendimiento general de tu web', 'quarterly'],
            ['support_type', 'Soporte prioritario', 'Atendemos tus consultas antes que al resto de clientes. Tu web es prioridad', 'priority'],
            ['monitoring', 'Monitorización 24/7', 'Vigilamos tu sitio constantemente para detectar caídas, errores o ataques de forma proactiva', 'true'],
            ['seo_wpo_review', 'Revisión SEO + WPO mensual', 'Revisamos mensualmente tu posicionamiento y velocidad, aplicando mejoras si lo necesitas', 'monthly'],
            ['monthly_reports', 'Informes de estado mensuales', 'Te enviamos un resumen mensual con incidencias resueltas, estado de tu web y recomendaciones', 'true'],
            ['dashboard_access', 'Dashboard avanzado', 'Acceso completo al dashboard con métricas avanzadas y reportes', 'advanced'],
            ['update_control', 'Actualizaciones controladas', 'El cliente no puede actualizar plugins por su cuenta, excepto los de pago/licencia', 'true']
        ];
        
        foreach ($raiz_unique_features as $index => $feature) {
            $wpdb->insert($table_plan_features, [
                'plan_id' => $raiz_id,
                'feature_key' => $feature[0],
                'feature_name' => $feature[1],
                'feature_description' => $feature[2],
                'feature_value' => $feature[3],
                'is_active' => 1,
                'display_order' => $index + 1
            ]);
        }
        
        // Features for Plan Ecosistema (includes all Raíz features plus premium ones)
        $ecosistema_features = [
            ['updates_frequency', 'Actualizaciones semanales', 'Actualizamos el core, plugins y temas una vez por semana para máxima seguridad y compatibilidad', 'weekly'],
            ['backup_frequency', 'Copias de seguridad semanales', 'Se generan backups automáticos y seguros cada semana para que nunca pierdas tu web', 'weekly'],
            ['wpo_optimization', 'Optimización avanzada WPO', 'Optimización completa con CDN, Redis, Memcached y otras mejoras avanzadas', 'advanced'],
            ['performance_review', 'Revisión trimestral de rendimiento', 'Analizamos cada 3 meses el estado técnico y rendimiento general de tu web', 'quarterly'],
            ['support_type', 'Soporte prioritario', 'Atendemos tus consultas antes que al resto de clientes. Tu web es prioridad', 'priority'],
            ['monitoring', 'Monitorización 24/7', 'Vigilamos tu sitio constantemente para detectar caídas, errores o ataques de forma proactiva', 'true'],
            ['seo_wpo_review', 'Auditoría SEO/WPO trimestral', 'Analizamos a fondo tu posicionamiento, velocidad y arquitectura cada trimestre, con informe detallado', 'quarterly'],
            ['monthly_reports', 'Informes de estado mensuales', 'Te enviamos un resumen mensual con incidencias resueltas, estado de tu web y recomendaciones', 'true'],
            ['consulting', 'Consultoría técnica trimestral', 'Nos reunimos contigo cada 3 meses para proponerte mejoras técnicas adaptadas a tu web', 'quarterly'],
            ['hosting_included', 'Hosting ecológico incluido', 'Incluímos nuestro plan Cedro para el máximo rendimiento. Servidores rápidos alimentados 100% con energía verde, sin coste extra', 'true'],
            ['cdn_optimization', 'CDN y optimización avanzada', 'Instalamos y configuramos red de entrega de contenidos (CDN), Redis, Memcached y otras mejoras si tu web lo permite', 'true'],
            ['dashboard_access', 'Dashboard premium', 'Acceso completo al dashboard con todas las métricas, reportes avanzados y consultoría', 'premium'],
            ['update_control', 'Actualizaciones controladas', 'El cliente no puede actualizar plugins por su cuenta, excepto los de pago/licencia', 'true']
        ];
        
        foreach ($ecosistema_features as $index => $feature) {
            $wpdb->insert($table_plan_features, [
                'plan_id' => $ecosistema_id,
                'feature_key' => $feature[0],
                'feature_name' => $feature[1],
                'feature_description' => $feature[2],
                'feature_value' => $feature[3],
                'is_active' => 1,
                'display_order' => $index + 1
            ]);
        }
    }
    
    /**
     * Get all plans
     */
    public function get_plans($args = []) {
        global $wpdb;
        
        $defaults = [
            'active_only' => true,
            'include_features' => false
        ];
        $args = wp_parse_args($args, $defaults);
        
        $where = $args['active_only'] ? 'WHERE is_active = 1' : '';
        
        $plans = $wpdb->get_results("
            SELECT * FROM {$this->table_plans} 
            {$where}
            ORDER BY price ASC
        ");
        
        if ($args['include_features']) {
            foreach ($plans as &$plan) {
                $plan->features = $this->get_plan_features($plan->id);
            }
        }
        
        return $plans;
    }
    
    /**
     * Get plan by slug
     */
    public function get_plan_by_slug($slug) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$this->table_plans} 
            WHERE slug = %s AND is_active = 1
        ", $slug));
    }
    
    /**
     * Get plan features
     */
    public function get_plan_features($plan_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$this->table_plan_features} 
            WHERE plan_id = %d AND is_active = 1
            ORDER BY display_order ASC
        ", $plan_id));
    }
    
    /**
     * Get feature by key for a specific plan
     */
    public function get_plan_feature($plan_slug, $feature_key) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT pf.* FROM {$this->table_plan_features} pf
            INNER JOIN {$this->table_plans} p ON pf.plan_id = p.id
            WHERE p.slug = %s AND pf.feature_key = %s AND pf.is_active = 1
        ", $plan_slug, $feature_key));
    }
    
    /**
     * Check if a plan has a specific feature
     */
    public function plan_has_feature($plan_slug, $feature_key) {
        $feature = $this->get_plan_feature($plan_slug, $feature_key);
        return !empty($feature);
    }
    
    /**
     * Get feature value for a plan
     */
    public function get_plan_feature_value($plan_slug, $feature_key, $default = null) {
        $feature = $this->get_plan_feature($plan_slug, $feature_key);
        return $feature ? $feature->feature_value : $default;
    }
    
    /**
     * Update plan features
     */
    public function update_plan_features($plan_id, $features) {
        global $wpdb;
        
        // Begin transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Delete existing features
            $wpdb->delete($this->table_plan_features, ['plan_id' => $plan_id]);
            
            // Insert new features
            foreach ($features as $index => $feature) {
                $wpdb->insert($this->table_plan_features, [
                    'plan_id' => $plan_id,
                    'feature_key' => $feature['key'],
                    'feature_name' => $feature['name'],
                    'feature_description' => $feature['description'],
                    'feature_value' => $feature['value'],
                    'is_active' => 1,
                    'display_order' => $index + 1
                ]);
            }
            
            $wpdb->query('COMMIT');
            return true;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return false;
        }
    }
    
    /**
     * Get site plan (from site manager)
     */
    public function get_site_plan($site_id) {
        global $wpdb;
        
        $table_sites = $wpdb->prefix . 'rphub_sites';
        
        $site = $wpdb->get_row($wpdb->prepare("
            SELECT plan FROM $table_sites WHERE id = %d
        ", $site_id));
        
        if ($site && $site->plan) {
            return $this->get_plan_by_slug($site->plan);
        }
        
        return null;
    }
    
    // AJAX Handlers
    public function ajax_get_plans() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $include_features = $_POST['include_features'] ?? false;
        $plans = $this->get_plans(['include_features' => $include_features]);
        
        wp_send_json_success($plans);
    }
    
    public function ajax_get_plan_features() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $plan_id = intval($_POST['plan_id'] ?? 0);
        if (!$plan_id) {
            wp_send_json_error('Invalid plan ID');
        }
        
        $features = $this->get_plan_features($plan_id);
        wp_send_json_success($features);
    }
    
    public function ajax_update_plan_features() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $plan_id = intval($_POST['plan_id'] ?? 0);
        $features = $_POST['features'] ?? [];
        
        if (!$plan_id) {
            wp_send_json_error('Invalid plan ID');
        }
        
        $result = $this->update_plan_features($plan_id, $features);
        
        if ($result) {
            wp_send_json_success('Plan features updated successfully');
        } else {
            wp_send_json_error('Failed to update plan features');
        }
    }
    
    public function ajax_create_plan() {
        check_ajax_referer('rphub_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }

        $slug = sanitize_key($_POST['slug'] ?? '');
        $name = sanitize_text_field($_POST['name'] ?? '');

        if (empty($slug) || empty($name)) {
            wp_send_json_error('slug y name son requeridos');
            return;
        }

        // Prevent overwriting built-in plans
        if (in_array($slug, ['semilla', 'raiz', 'ecosistema'], true)) {
            wp_send_json_error('No se pueden crear planes con slugs reservados');
            return;
        }

        global $wpdb;

        if ($wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->table_plans} WHERE slug = %s", $slug))) {
            wp_send_json_error('Ya existe un plan con ese slug');
            return;
        }

        $result = $wpdb->insert(
            $this->table_plans,
            [
                'slug'           => $slug,
                'name'           => $name,
                'description'    => sanitize_textarea_field($_POST['description'] ?? ''),
                'price'          => floatval($_POST['price'] ?? 0),
                'billing_period' => sanitize_text_field($_POST['billing_period'] ?? 'monthly'),
                'order_url'      => esc_url_raw($_POST['order_url'] ?? ''),
                'is_active'      => 1,
            ],
            ['%s', '%s', '%s', '%f', '%s', '%s', '%d']
        );

        if ($result === false) {
            wp_send_json_error('Error al crear el plan en la base de datos');
            return;
        }

        wp_send_json_success(['plan_id' => $wpdb->insert_id, 'message' => 'Plan creado correctamente']);
    }

    public function ajax_update_plan() {
        check_ajax_referer('rphub_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }

        $plan_id = intval($_POST['plan_id'] ?? 0);
        if (!$plan_id) {
            wp_send_json_error('ID de plan no válido');
            return;
        }

        global $wpdb;

        if (!$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->table_plans} WHERE id = %d", $plan_id))) {
            wp_send_json_error('Plan no encontrado');
            return;
        }

        $field_map = [
            'name'           => ['sanitize_text_field', '%s'],
            'description'    => ['sanitize_textarea_field', '%s'],
            'price'          => ['floatval', '%f'],
            'billing_period' => ['sanitize_text_field', '%s'],
            'order_url'      => ['esc_url_raw', '%s'],
            'is_active'      => [null, '%d'],
        ];

        $update_data = [];
        $format      = [];

        foreach ($field_map as $field => [$sanitizer, $fmt]) {
            if (!isset($_POST[$field])) {
                continue;
            }
            if ($field === 'is_active') {
                $update_data[$field] = intval($_POST[$field]) ? 1 : 0;
            } elseif ($sanitizer) {
                $update_data[$field] = $sanitizer($_POST[$field]);
            }
            $format[] = $fmt;
        }

        if (empty($update_data)) {
            wp_send_json_error('No hay datos para actualizar');
            return;
        }

        $result = $wpdb->update(
            $this->table_plans,
            $update_data,
            ['id' => $plan_id],
            $format,
            ['%d']
        );

        if ($result === false) {
            wp_send_json_error('Error al actualizar el plan en la base de datos');
            return;
        }

        wp_send_json_success(['plan_id' => $plan_id, 'message' => 'Plan actualizado correctamente']);
    }

    public function ajax_delete_plan() {
        check_ajax_referer('rphub_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }

        $plan_id = intval($_POST['plan_id'] ?? 0);
        if (!$plan_id) {
            wp_send_json_error('ID de plan no válido');
            return;
        }

        global $wpdb;

        $plan = $wpdb->get_row($wpdb->prepare("SELECT slug FROM {$this->table_plans} WHERE id = %d", $plan_id));
        if (!$plan) {
            wp_send_json_error('Plan no encontrado');
            return;
        }

        // Protect built-in plans from deletion
        if (in_array($plan->slug, ['semilla', 'raiz', 'ecosistema'], true)) {
            wp_send_json_error('Los planes base de Replanta no se pueden eliminar');
            return;
        }

        // Check if any site uses this plan before deleting
        $in_use = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}rphub_sites WHERE plan = %s",
                $plan->slug
            )
        );
        if ($in_use > 0) {
            wp_send_json_error("No se puede eliminar: $in_use sitio(s) usan este plan");
            return;
        }

        $result = $wpdb->delete($this->table_plans, ['id' => $plan_id], ['%d']);

        if ($result === false) {
            wp_send_json_error('Error al eliminar el plan de la base de datos');
            return;
        }

        wp_send_json_success(['message' => 'Plan eliminado correctamente']);
    }

    public function ajax_toggle_ecommerce_addon() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }

        $site_id = intval($_POST['site_id'] ?? 0);
        $enable  = filter_var($_POST['enable'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (!$site_id) {
            wp_send_json_error('site_id requerido');
            return;
        }

        $this->toggle_site_addon($site_id, 'ecommerce', $enable);

        wp_send_json_success([
            'site_id' => $site_id,
            'ecommerce_addon' => $enable,
            'message' => $enable ? 'Modificador Ecommerce activado' : 'Modificador Ecommerce desactivado',
        ]);
    }

    public function ajax_get_site_addons() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }

        $site_id = intval($_POST['site_id'] ?? 0);
        if (!$site_id) {
            wp_send_json_error('site_id requerido');
            return;
        }

        wp_send_json_success([
            'site_id'          => $site_id,
            'addons'           => $this->get_site_addons($site_id),
            'ecommerce_addon'  => $this->site_has_ecommerce_addon($site_id),
        ]);
    }
}
