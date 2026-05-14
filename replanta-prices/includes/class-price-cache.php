<?php
/**
 * Price cache with WP-Cron sync and default product data.
 *
 * @package Replanta_Prices
 */

defined( 'ABSPATH' ) || exit;

class Replanta_Prices_Cache {

    /** Option keys */
    const OPT_PRODUCTS  = 'replanta_prices_products';
    const OPT_LAST_SYNC = 'replanta_prices_last_sync';
    const OPT_SYNC_LOG  = 'replanta_prices_sync_log';
    const OPT_TLDS      = 'replanta_tld_data';

    /** ─── Default product catalogue ──────────────────────────────── */

    public static function get_defaults() {
        return array(
            'hosting' => array(
                'title'    => 'Elige tu plan',
                'subtitle' => 'Los 3 incluyen la base técnica. Escalas por <em>recursos</em>, <em>automatización</em> y <em>seguridad reforzada</em>.',
                'billing'  => 'toggle', // toggle = monthly/annual
                'plans'    => array(
                    'sauce' => array(
                        'name'       => 'Sauce',
                        'subtitle'   => 'Personal',
                        'featured'   => false,
                        'pid'        => '6d530876-8251-d485-d80a-147e390921e6',
                        'price_m'    => 12.99,
                        'price_y'    => 129.00,
                        'prices'     => array(
                            'USD' => array( 'm' => 12.99, 'y' => 129 ),
                            'MXN' => array( 'm' => 269,   'y' => 2690 ),
                            'COP' => array( 'm' => 56900, 'y' => 569000 ),
                            'CLP' => array( 'm' => 12990, 'y' => 129900 ),
                            'ARS' => array( 'm' => 21490, 'y' => 214900 ),
                            'PEN' => array( 'm' => 49.90, 'y' => 499 ),
                        ),
                        'features'   => array(
                            '1 sitio (1 dominio)',
                            '<b>50 GB NVMe</b>',
                            '5 cuentas de email',
                            'Redis Object Cache (habilitado)',
                            'LiteSpeed &bull; HTTP/3 &bull; Brotli',
                            'Cloudflare + reglas optimizadas',
                        ),
                        'features_extra' => array(
                            'Staging 1‑click',
                            'Smart Updates (según plan)',
                            'Imunify360 &bull; Backups externos',
                        ),
                        'cta_text'      => 'Comenzar ahora',
                    ),
                    'roble' => array(
                        'name'       => 'Roble',
                        'subtitle'   => 'Corporativo',
                        'featured'   => true,
                        'pid'        => '280d1639-e237-d439-6dea-54610589e572',
                        'price_m'    => 19.99,
                        'price_y'    => 199.00,
                        'prices'     => array(
                            'USD' => array( 'm' => 19.99, 'y' => 199 ),
                            'MXN' => array( 'm' => 399,   'y' => 3990 ),
                            'COP' => array( 'm' => 86900, 'y' => 869000 ),
                            'CLP' => array( 'm' => 19990, 'y' => 199900 ),
                            'ARS' => array( 'm' => 32900, 'y' => 329000 ),
                            'PEN' => array( 'm' => 79.90, 'y' => 799 ),
                        ),
                        'features'   => array(
                            'Sitios y dominios ilimitados',
                            '<b>100 GB NVMe</b> — Email ilimitado',
                            'Redis Object Cache (tuning básico)',
                            '2 vCPU / 2 GB RAM',
                            'Smart Updates programadas',
                            'Soporte prioritario',
                        ),
                        'features_extra' => array(
                            'Staging 1‑click &bull; WP‑CLI / SSH',
                            'Smart Updates (según plan)',
                            'Imunify360 &bull; Backups externos',
                        ),
                        'cta_text'      => 'Comenzar ahora',
                    ),
                    'cedro' => array(
                        'name'       => 'Cedro',
                        'subtitle'   => 'WooCommerce',
                        'featured'   => false,
                        'pid'        => 'e2e071d9-31d5-e460-555a-646028758396',
                        'price_m'    => 29.99,
                        'price_y'    => 299.00,
                        'prices'     => array(
                            'USD' => array( 'm' => 29.99, 'y' => 299 ),
                            'MXN' => array( 'm' => 599,   'y' => 5990 ),
                            'COP' => array( 'm' => 129900,'y' => 1299000 ),
                            'CLP' => array( 'm' => 29990, 'y' => 299900 ),
                            'ARS' => array( 'm' => 48900, 'y' => 489000 ),
                            'PEN' => array( 'm' => 119,   'y' => 1190 ),
                        ),
                        'features'   => array(
                            'WooCommerce y sitios ilimitados',
                            '<b>200 GB NVMe</b> — Email ilimitado',
                            'Redis optimizado Woo',
                            '2 vCPU / 4 GB RAM',
                            'Turnstile + WPO inicial',
                            'WAF opcional (CF Pro)',
                        ),
                        'features_extra' => array(
                            'Staging 1‑click &bull; WP‑CLI / SSH',
                            'Smart Updates (según plan)',
                            'Imunify360 &bull; Backups externos',
                        ),
                        'cta_text'      => 'Comenzar ahora',
                    ),
                ),
            ),
            'mantenimiento' => array(
                'title'    => 'Planes de mantenimiento WordPress',
                'subtitle' => 'Sin permanencias. Si tu proyecto es WooCommerce, membership o LMS, te recomendamos empezar en Raíz o Ecosistema.',
                'billing'  => 'monthly', // monthly only
                'plans'    => array(
                    'semilla' => array(
                        'name'       => 'Plan Semilla',
                        'subtitle'   => 'Para webs pequeñas pero importantes',
                        'featured'   => false,
                        'pid'        => '2e071d93-1d5e-4689-305b-646028758396',
                        'price_m'    => 49.00,
                        'price_y'    => 0,
                        'prices'     => array(
                            'USD' => array( 'm' => 49, 'y' => 0 ),
                            'MXN' => array( 'm' => 990, 'y' => 0 ),
                            'COP' => array( 'm' => 209900, 'y' => 0 ),
                            'CLP' => array( 'm' => 49900, 'y' => 0 ),
                            'ARS' => array( 'm' => 79900, 'y' => 0 ),
                            'PEN' => array( 'm' => 189, 'y' => 0 ),
                        ),
                        'features'   => array(
                            array( 'text' => 'Actualizaciones <strong>mensuales</strong> (WP, plugins y temas)', 'tip' => 'Backup previo + punto de restauración. Prueba rápida del frontal tras actualizar. Si algo falla, revertimos y proponemos alternativa segura.' ),
                            array( 'text' => 'Copias de seguridad <strong>semanales</strong> fuera del servidor', 'tip' => 'Backups externos con JetBackup. Restauración granular (archivos/BD/correo) en 1 clic.' ),
                            array( 'text' => 'Optimización básica WPO', 'tip' => 'Presets de LSCache, limpieza de base de datos mensual y control de medios huérfanos.' ),
                            array( 'text' => 'Revisión trimestral de rendimiento', 'tip' => 'Informe con métricas clave (TTFB/LCP/Core Web Vitals) y 2–3 acciones recomendadas.' ),
                            array( 'text' => 'Soporte por email', 'tip' => 'Te acompañamos en incidencias habituales (errores tras actualización, dudas de configuración, etc.).' ),
                        ),
                        'features_extra' => array(),
                        'cta_text'      => 'Contratar este plan',
                        'cta_secondary' => '',
                    ),
                    'raiz' => array(
                        'name'       => 'Plan Raíz',
                        'subtitle'   => 'Para empresas que viven de su web',
                        'featured'   => true,
                        'pid'        => 'd5308768-251d-4852-057a-147e390921e6',
                        'price_m'    => 89.00,
                        'price_y'    => 0,
                        'prices'     => array(
                            'USD' => array( 'm' => 89, 'y' => 0 ),
                            'MXN' => array( 'm' => 1790, 'y' => 0 ),
                            'COP' => array( 'm' => 389000, 'y' => 0 ),
                            'CLP' => array( 'm' => 89900, 'y' => 0 ),
                            'ARS' => array( 'm' => 144900, 'y' => 0 ),
                            'PEN' => array( 'm' => 349, 'y' => 0 ),
                        ),
                        'features'   => array(
                            array( 'text' => 'Todo lo del plan Semilla', 'tip' => '' ),
                            array( 'text' => 'Actualizaciones <strong>semanales</strong> + staging si procede', 'tip' => 'Cambios de riesgo probados en clon. Ventanas programadas para evitar impacto en ventas/conversión.' ),
                            array( 'text' => 'Monitorización 24/7 con alertas', 'tip' => 'Detección de caídas, 5xx, caducidad de SSL y picos anómalos. Actuación prioritaria según criticidad.' ),
                            array( 'text' => 'Revisión SEO técnico + WPO <strong>mensual</strong>', 'tip' => 'Salud de indexación (robots/sitemaps), CWV, limpieza de 404 y redirecciones. Acciones concretas cada mes.' ),
                            array( 'text' => 'Soporte prioritario', 'tip' => 'Tu ticket salta a la primera cola. Gestión proactiva en incidencias sensibles (checkout/contacto).' ),
                            array( 'text' => 'Cloudflare Free <em>configurado</em>', 'tip' => 'CDN global, reglas para WordPress/Woo y security headers base. Te lo dejamos listo y documentado.' ),
                        ),
                        'features_extra' => array(),
                        'cta_text'      => 'Empezar con este plan',
                        'cta_secondary' => '',
                    ),
                    'ecosistema' => array(
                        'name'       => 'Plan Ecosistema',
                        'subtitle'   => 'Para proyectos que crecen sin freno',
                        'featured'   => false,
                        'pid'        => '2e071d93-1d5e-4689-088f-646028758396',
                        'price_m'    => 149.00,
                        'price_y'    => 0,
                        'prices'     => array(
                            'USD' => array( 'm' => 149, 'y' => 0 ),
                            'MXN' => array( 'm' => 2990, 'y' => 0 ),
                            'COP' => array( 'm' => 649000, 'y' => 0 ),
                            'CLP' => array( 'm' => 149900, 'y' => 0 ),
                            'ARS' => array( 'm' => 244900, 'y' => 0 ),
                            'PEN' => array( 'm' => 589, 'y' => 0 ),
                        ),
                        'features'   => array(
                            array( 'text' => 'Todo lo del plan Raíz', 'tip' => '' ),
                            array( 'text' => 'Consultoría técnica <strong>trimestral</strong>', 'tip' => 'Reunión de mejora continua: roadmap técnico, priorización por impacto y revisión de métricas de negocio.' ),
                            array( 'text' => '<strong>Hosting ecológico incluido</strong> (plan Cedro)', 'tip' => 'NVMe + LiteSpeed + Redis sobre energía 100 % renovable. Migración sin caídas y soporte prioritario.' ),
                            array( 'text' => 'Auditoría SEO/WPO <strong>trimestral</strong>', 'tip' => 'Crawling profundo, Core Web Vitals, arquitectura interna y rendimiento por tipo de plantilla/URL.' ),
                            array( 'text' => 'Ajustes avanzados de caché/CDN', 'tip' => 'Tuning específico para tu stack (WP/Woo/LMS/memberships): reglas por ruta, TTL y exclusiones críticas.' ),
                        ),
                        'features_extra' => array(),
                        'cta_text'      => 'Solicitar este plan',
                        'cta_secondary' => '',
                    ),
                ),
                'footer_note' => '* Precios sin IVA. Rangos orientativos según estado inicial del sitio. WAF perimetral de Cloudflare Pro no incluido (opcional).',
            ),
            'sapwoo' => array(
                'title'    => 'Conector SAP ↔ WooCommerce',
                'subtitle' => 'Conexión directa vía Service Layer. Setup profesional + mantenimiento mensual con soporte incluido.',
                'billing'  => 'setup_monthly',
                'pid_monthly' => '61e50989-73d2-4753-988c-e45e610832d7',
                'plans'    => array(
                    'starter' => array(
                        'name'        => 'SAP Starter',
                        'subtitle'    => 'Para tiendas B2C con SAP B1',
                        'featured'    => false,
                        'pid'         => '2e071d93-1d5e-468e-935c-646028758396',
                        'price_setup' => 990,
                        'price_m'     => 99,
                        'price_y'     => 0,
                        'prices'      => array(
                            'USD' => array( 'setup' => 1090,    'm' => 109,    'y' => 0 ),
                            'MXN' => array( 'setup' => 18990,   'm' => 1990,   'y' => 0 ),
                            'COP' => array( 'setup' => 4290000, 'm' => 429000, 'y' => 0 ),
                            'CLP' => array( 'setup' => 990000,  'm' => 99900,  'y' => 0 ),
                            'ARS' => array( 'setup' => 1590000, 'm' => 159000, 'y' => 0 ),
                            'PEN' => array( 'setup' => 3990,    'm' => 399,    'y' => 0 ),
                        ),
                        'features'    => array(
                            array( 'text' => 'Pedidos WooCommerce → SAP B1',    'tip' => 'Cada pedido confirmado crea el documento SAP automáticamente con Business Partner.' ),
                            array( 'text' => 'Stock básico desde SAP',           'tip' => 'Sincronización de inventario desde el almacén principal configurado.' ),
                            array( 'text' => 'Logs de sincronización',           'tip' => 'Registro básico de operaciones para diagnóstico y seguimiento.' ),
                            array( 'text' => 'Modo B2C (cliente genérico)',      'tip' => 'Un Business Partner genérico por región. Ideal para tiendas B2C sin gestión individual.' ),
                            array( 'text' => 'Soporte por email',               'tip' => 'Asistencia técnica por email para incidencias y configuración.' ),
                        ),
                        'features_extra' => array(),
                        'cta_text'      => 'Solicitar presupuesto',
                    ),
                    'business' => array(
                        'name'        => 'SAP Business',
                        'subtitle'    => 'Para empresas con operativa diaria',
                        'featured'    => true,
                        'pid'         => '2e071d93-1d5e-468e-935c-646028758396',
                        'price_setup' => 1500,
                        'price_m'     => 149,
                        'price_y'     => 0,
                        'prices'      => array(
                            'USD' => array( 'setup' => 1690,    'm' => 169,    'y' => 0 ),
                            'MXN' => array( 'setup' => 29900,   'm' => 2990,   'y' => 0 ),
                            'COP' => array( 'setup' => 6490000, 'm' => 649000, 'y' => 0 ),
                            'CLP' => array( 'setup' => 1490000, 'm' => 149900, 'y' => 0 ),
                            'ARS' => array( 'setup' => 2490000, 'm' => 249000, 'y' => 0 ),
                            'PEN' => array( 'setup' => 5990,    'm' => 599,    'y' => 0 ),
                        ),
                        'features'    => array(
                            array( 'text' => 'Todo lo del plan Starter',                          'tip' => '' ),
                            array( 'text' => 'Precios y stock <strong>por almacén</strong>',      'tip' => 'Mapeo almacén-tarifa por región (Península, Canarias, Portugal). Listas de precios SAP reflejadas en WooCommerce.' ),
                            array( 'text' => 'Catálogo completo desde SAP',                       'tip' => 'Importación masiva de productos, categorías por ItemGroups y preview de campos antes de importar.' ),
                            array( 'text' => 'Clientes B2B con CardCode',                         'tip' => 'Cada usuario WordPress vinculado a un Business Partner SAP. Tarifas B2B personalizadas.' ),
                            array( 'text' => 'Multicanal: TikTok + Amazon',                       'tip' => 'Pedidos de TikTok Shop y Amazon entran en SAP por el mismo flujo. Channel Manager nativo.' ),
                            array( 'text' => 'Soporte prioritario con SLA',                       'tip' => 'Tu ticket salta a primera cola. SLA definido para incidencias críticas.' ),
                        ),
                        'features_extra' => array(),
                        'cta_text'      => 'Solicitar presupuesto',
                    ),
                    'enterprise' => array(
                        'name'        => 'SAP Enterprise',
                        'subtitle'    => 'Para operaciones multicanal',
                        'featured'    => false,
                        'pid'         => '2e071d93-1d5e-468e-935c-646028758396',
                        'price_setup' => 2500,
                        'price_m'     => 249,
                        'price_y'     => 0,
                        'prices'      => array(
                            'USD' => array( 'setup' => 2790,     'm' => 279,     'y' => 0 ),
                            'MXN' => array( 'setup' => 49900,    'm' => 4990,    'y' => 0 ),
                            'COP' => array( 'setup' => 10900000, 'm' => 1090000, 'y' => 0 ),
                            'CLP' => array( 'setup' => 2490000,  'm' => 249900,  'y' => 0 ),
                            'ARS' => array( 'setup' => 3990000,  'm' => 399000,  'y' => 0 ),
                            'PEN' => array( 'setup' => 9990,     'm' => 999,     'y' => 0 ),
                        ),
                        'features'    => array(
                            array( 'text' => 'Todo lo del plan Business',                                 'tip' => '' ),
                            array( 'text' => 'Miravia incluido',                                          'tip' => 'Canal Miravia activado sin coste adicional de activación.' ),
                            array( 'text' => '14 hooks de extensión',                                     'tip' => 'Adapta la lógica de negocio sin tocar el núcleo. Filtros pre/post sincronización, personalización por canal.' ),
                            array( 'text' => 'Consultoría técnica <strong>trimestral</strong>',            'tip' => 'Reunión de mejora continua: roadmap técnico, optimización de flujos y revisión de métricas.' ),
                            array( 'text' => 'Soporte premium dedicado',                                  'tip' => 'Canal directo con el equipo de desarrollo. Tiempos de respuesta garantizados.' ),
                        ),
                        'features_extra' => array(),
                        'cta_text'      => 'Solicitar presupuesto',
                    ),
                ),
                'footer_note' => '* Precios sin IVA. Setup incluye llamada de análisis, configuración y puesta en producción (máx. 28 días). El precio final depende de la complejidad del proyecto.',
            ),
        );
    }

    /* ─── Init ─────────────────────────────────────────────────────── */

    public static function init() {
        add_action( 'replanta_prices_sync_cron', array( __CLASS__, 'sync_all_prices' ) );
        add_action( 'wp_ajax_replanta_prices_sync', array( __CLASS__, 'ajax_sync' ) );
        add_action( 'wp_ajax_replanta_prices_test', array( __CLASS__, 'ajax_test_connection' ) );
    }

    /* ─── Seed defaults on activation ──────────────────────────────── */

    public static function maybe_seed_defaults() {
        $existing = get_option( self::OPT_PRODUCTS );
        if ( empty( $existing ) ) {
            update_option( self::OPT_PRODUCTS, self::get_defaults(), true ); // autoload = yes
        }
    }

    /* ─── Read ─────────────────────────────────────────────────────── */

    /**
     * Get all product data.
     *
     * Merges saved data with defaults so that new categories (e.g. sapwoo)
     * added in code are available even if the option was already seeded.
     *
     * @return array
     */
    public static function get_all() {
        $data     = get_option( self::OPT_PRODUCTS );
        $defaults = self::get_defaults();

        if ( empty( $data ) || ! is_array( $data ) ) {
            update_option( self::OPT_PRODUCTS, $defaults, true );
            return $defaults;
        }

        // Inject any new default categories not yet present in saved data.
        $missing = array_diff_key( $defaults, $data );
        if ( ! empty( $missing ) ) {
            $data = array_merge( $data, $missing );
            update_option( self::OPT_PRODUCTS, $data, true );
        }

        return $data;
    }

    /* ─── TLD catalogue ────────────────────────────────────────────── */

    /**
     * Default TLD entries (no prices — shows "Ver precio" links).
     * When the Upmind pro API is available, sync prices via
     * `replanta_prices_sync_cron` and store them with `set_tlds()`.
     *
     * @return array<int, array{ext:string, desc:string, price:float, eco:bool}>
     */
    public static function get_default_tlds() {
        return array(
            array( 'ext' => 'com',   'desc' => 'La extensión universal',   'price' => 0, 'eco' => false ),
            array( 'ext' => 'es',    'desc' => 'Presencia en España',       'price' => 0, 'eco' => false ),
            array( 'ext' => 'net',   'desc' => 'Tecnología y redes',        'price' => 0, 'eco' => false ),
            array( 'ext' => 'org',   'desc' => 'ONGs y comunidades',        'price' => 0, 'eco' => false ),
            array( 'ext' => 'eco',   'desc' => 'Compromiso ecológico',      'price' => 0, 'eco' => true  ),
            array( 'ext' => 'green', 'desc' => 'Marcas sostenibles',        'price' => 0, 'eco' => true  ),
            array( 'ext' => 'shop',  'desc' => 'Tu tienda online',          'price' => 0, 'eco' => false ),
            array( 'ext' => 'dev',   'desc' => 'Desarrolladores',           'price' => 0, 'eco' => false ),
        );
    }

    /**
     * Read TLD catalogue from WP option, falling back to defaults.
     *
     * @return array
     */
    public static function get_tlds() {
        $raw = get_option( self::OPT_TLDS, null );
        if ( is_array( $raw ) && ! empty( $raw ) ) {
            return $raw;
        }
        return self::get_default_tlds();
    }

    /**
     * Persist the TLD catalogue (called by the API sync or admin save).
     *
     * @param array $tlds
     */
    public static function set_tlds( array $tlds ) {
        update_option( self::OPT_TLDS, $tlds, false );
    }

    /**
     * Get a specific category (hosting / mantenimiento).
     *
     * @param string $type
     * @return array|null
     */
    public static function get_category( $type ) {
        $all = self::get_all();
        $cat = isset( $all[ $type ] ) ? $all[ $type ] : null;

        // Auto-translate to English when on an English page
        if ( $cat && Replanta_Prices_Geo::is_english() ) {
            $cat = self::apply_en( $cat );
        }

        return $cat;
    }

    /**
     * Get a single plan's data.
     *
     * @param string $type  'hosting' or 'mantenimiento'
     * @param string $slug  Plan slug (sauce, roble, etc.)
     * @return array|null
     */
    public static function get_plan( $type, $slug ) {
        $cat = self::get_category( $type );
        if ( $cat && isset( $cat['plans'][ $slug ] ) ) {
            return $cat['plans'][ $slug ];
        }
        return null;
    }

    /**
     * Get a single price for a plan (currency-aware).
     *
     * @param string $type    'hosting' or 'mantenimiento'
     * @param string $slug    Plan slug
     * @param string $period  'monthly' or 'annual'
     * @return float
     */
    public static function get_price( $type, $slug, $period = 'monthly' ) {
        $plan = self::get_plan( $type, $slug );
        if ( ! $plan ) {
            return 0;
        }
        return self::get_localized_amount( $plan, $period );
    }

    /**
     * Resolve the correct price amount for the visitor's detected currency.
     *
     * Priority:
     *  1. EUR → base price_m / price_y
     *  2. Exact match in prices[CURRENCY]
     *  3. Fallback to prices['USD'] (covers unknown LATAM currencies)
     *  4. Ultimate fallback → EUR base
     *
     * @param array  $plan    Plan data array (must contain price_m/price_y and optionally prices).
     * @param string $period  'monthly' or 'annual'
     * @return float
     */
    public static function get_localized_amount( $plan, $period = 'monthly' ) {
        $currency = Replanta_Prices_Geo::get_currency_code();

        // Map period → key used in prices arrays
        if ( 'setup' === $period ) {
            $key = 'setup';
        } elseif ( 'annual' === $period ) {
            $key = 'y';
        } else {
            $key = 'm';
        }

        // EUR → base prices (always stored)
        if ( 'EUR' === $currency ) {
            if ( 'setup' === $period ) {
                return isset( $plan['price_setup'] ) ? (float) $plan['price_setup'] : 0;
            }
            return ( 'annual' === $period )
                ? ( isset( $plan['price_y'] ) ? (float) $plan['price_y'] : 0 )
                : ( isset( $plan['price_m'] ) ? (float) $plan['price_m'] : 0 );
        }

        // Exact currency match (USD, MXN, COP, CLP, ARS, PEN…)
        if ( ! empty( $plan['prices'][ $currency ][ $key ] ) ) {
            return (float) $plan['prices'][ $currency ][ $key ];
        }

        // Fallback → USD (handles LATAM visitors whose currency isn't declared)
        if ( ! empty( $plan['prices']['USD'][ $key ] ) ) {
            return (float) $plan['prices']['USD'][ $key ];
        }

        // Ultimate fallback → EUR base
        if ( 'setup' === $period ) {
            return isset( $plan['price_setup'] ) ? (float) $plan['price_setup'] : 0;
        }
        return ( 'annual' === $period )
            ? ( isset( $plan['price_y'] ) ? (float) $plan['price_y'] : 0 )
            : ( isset( $plan['price_m'] ) ? (float) $plan['price_m'] : 0 );
    }

    /**
     * Get the currency code that actually matches the resolved price.
     *
     * If the detected currency (e.g. PYG) has no prices defined,
     * we serve USD amounts → the display currency must also be USD.
     *
     * @param  array  $plan  Plan data array.
     * @return string        Currency code (EUR, USD, MXN, …).
     */
    public static function get_effective_currency( $plan ) {
        $currency = Replanta_Prices_Geo::get_currency_code();
        if ( 'EUR' === $currency ) {
            return 'EUR';
        }
        if ( ! empty( $plan['prices'][ $currency ] ) ) {
            return $currency;
        }
        return 'USD'; // fallback
    }

    /**
     * Convenience: get the fully formatted price HTML for a plan/period.
     * Handles currency resolution + formatting in one call.
     *
     * @param  array  $plan    Plan data.
     * @param  string $period  'monthly' or 'annual'.
     * @return string          HTML like "$269" or "12,99€".
     */
    public static function format_plan_price( $plan, $period = 'monthly' ) {
        $amount   = self::get_localized_amount( $plan, $period );
        $currency = self::get_effective_currency( $plan );
        return Replanta_Prices_Geo::format_price_html( $amount, $currency );
    }

    /**
     * Convenience: plain-text formatted price (no HTML wrapping of decimals).
     *
     * @param  array  $plan    Plan data.
     * @param  string $period  'monthly' or 'annual'.
     * @return string          Plain text like "$269" or "12,99€".
     */
    public static function format_plan_price_text( $plan, $period = 'monthly' ) {
        $amount   = self::get_localized_amount( $plan, $period );
        $currency = self::get_effective_currency( $plan );
        return Replanta_Prices_Geo::format_price( $amount, $currency );
    }

    /**
     * Convenience: formatted setup price HTML.
     *
     * @param  array  $plan  Plan data (must have price_setup).
     * @return string        HTML like "$1,690" or "1.500€".
     */
    public static function format_plan_setup_price( $plan ) {
        return self::format_plan_price( $plan, 'setup' );
    }

    /* ─── Sync from Upmind ─────────────────────────────────────────── */

    /**
     * Sync all product prices from Upmind API.
     *
     * @return array  Log of results
     */
    public static function sync_all_prices() {
        $products = self::get_all();
        $log      = array();
        $updated  = false;

        foreach ( $products as $type => &$category ) {
            if ( ! isset( $category['plans'] ) ) {
                continue;
            }
            foreach ( $category['plans'] as $slug => &$plan ) {
                $pid = isset( $plan['pid'] ) ? $plan['pid'] : '';
                if ( empty( $pid ) ) {
                    $log[] = sprintf( '[%s/%s] Sin PID, omitido.', $type, $slug );
                    continue;
                }

                $result = Replanta_Prices_Upmind_Api::get_product( $pid );

                if ( is_wp_error( $result ) ) {
                    $log[] = sprintf( '[%s/%s] Error: %s', $type, $slug, $result->get_error_message() );
                    continue;
                }

                $prices = Replanta_Prices_Upmind_Api::extract_prices( $result );

                if ( $prices['monthly'] > 0 ) {
                    $plan['price_m'] = $prices['monthly'];
                    $updated = true;
                    $log[] = sprintf( '[%s/%s] Mensual → %.2f', $type, $slug, $prices['monthly'] );
                }
                if ( $prices['annual'] > 0 ) {
                    $plan['price_y'] = $prices['annual'];
                    $updated = true;
                    $log[] = sprintf( '[%s/%s] Anual → %.2f', $type, $slug, $prices['annual'] );
                }

                if ( $prices['monthly'] <= 0 && $prices['annual'] <= 0 ) {
                    $log[] = sprintf( '[%s/%s] Sin precios en respuesta, se mantienen los actuales.', $type, $slug );
                }
            }
            unset( $plan );
        }
        unset( $category );

        if ( $updated ) {
            update_option( self::OPT_PRODUCTS, $products, true );
        }

        update_option( self::OPT_LAST_SYNC, time() );
        update_option( self::OPT_SYNC_LOG, $log );

        return $log;
    }

    /* ─── AJAX sync handler ────────────────────────────────────────── */

    public static function ajax_sync() {
        check_ajax_referer( 'replanta_prices_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Sin permisos.' );
        }

        $log = self::sync_all_prices();

        wp_send_json_success( array(
            'log'       => $log,
            'last_sync' => gmdate( 'Y-m-d H:i:s', get_option( self::OPT_LAST_SYNC, 0 ) ),
        ) );
    }

    /* ─── AJAX connection test ──────────────────────────────────────── */

    public static function ajax_test_connection() {
        check_ajax_referer( 'replanta_prices_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Sin permisos.' );
        }

        $result = Replanta_Prices_Upmind_Api::test_connection();

        if ( true === $result ) {
            wp_send_json_success( array( 'message' => 'Conexión correcta ✓' ) );
        } else {
            wp_send_json_error( $result->get_error_message() );
        }
    }

    /* ─── Manual price update from admin ───────────────────────────── */

    /**
     * Update a single plan's prices from admin form.
     *
     * @param string $type
     * @param string $slug
     * @param float  $price_m
     * @param float  $price_y
     * @return bool
     */
    public static function update_plan_price( $type, $slug, $price_m, $price_y = 0 ) {
        $products = self::get_all();

        if ( isset( $products[ $type ]['plans'][ $slug ] ) ) {
            $products[ $type ]['plans'][ $slug ]['price_m'] = (float) $price_m;
            $products[ $type ]['plans'][ $slug ]['price_y'] = (float) $price_y;
            return update_option( self::OPT_PRODUCTS, $products, true );
        }

        return false;
    }

    /* ─── English translations ─────────────────────────────────────── */

    /**
     * Map of Spanish default strings → English equivalents.
     * Only matches text that hasn't been customised via admin.
     */
    private static function en_strings() {
        return array(
            /* ── Category level ─────────────────────────────────── */
            'Elige tu plan'
                => 'Choose your plan',
            'Los 3 incluyen la base técnica. Escalas por <em>recursos</em>, <em>automatización</em> y <em>seguridad reforzada</em>.'
                => 'All 3 include the technical foundation. Scale by <em>resources</em>, <em>automation</em> and <em>enhanced security</em>.',
            'Planes de mantenimiento WordPress'
                => 'WordPress Maintenance Plans',
            'Sin permanencias. Si tu proyecto es WooCommerce, membership o LMS, te recomendamos empezar en Raíz o Ecosistema.'
                => 'No lock-in. If your project runs WooCommerce, membership or LMS, we recommend starting with Raíz or Ecosistema.',
            '* Precios sin IVA. Rangos orientativos según estado inicial del sitio. WAF perimetral de Cloudflare Pro no incluido (opcional).'
                => '* Prices exclude VAT. Indicative ranges based on initial site condition. Cloudflare Pro perimeter WAF not included (optional).',

            /* ── Hosting — Plan names / subtitles / CTA ─────────── */
            'Personal'                     => 'Personal',
            'Corporativo'                  => 'Business',
            'Comenzar ahora'               => 'Get started',

            /* ── Hosting — Features ─────────────────────────────── */
            '1 sitio (1 dominio)'                        => '1 site (1 domain)',
            '5 cuentas de email'                         => '5 email accounts',
            'Redis Object Cache (habilitado)'            => 'Redis Object Cache (enabled)',
            'Cloudflare + reglas optimizadas'             => 'Cloudflare + optimised rules',
            'Staging 1‑click'                            => '1-click staging',
            'Smart Updates (según plan)'                 => 'Smart Updates (plan-dependent)',
            'Imunify360 &bull; Backups externos'         => 'Imunify360 &bull; Off-server backups',
            'Sitios y dominios ilimitados'                => 'Unlimited sites &amp; domains',
            '<b>100 GB NVMe</b> — Email ilimitado'       => '<b>100 GB NVMe</b> — Unlimited email',
            'Redis Object Cache (tuning básico)'         => 'Redis Object Cache (basic tuning)',
            'Smart Updates programadas'                  => 'Scheduled Smart Updates',
            'Soporte prioritario'                        => 'Priority support',
            'Staging 1‑click &bull; WP‑CLI / SSH'       => '1-click staging &bull; WP-CLI / SSH',
            'WooCommerce y sitios ilimitados'             => 'WooCommerce &amp; unlimited sites',
            '<b>200 GB NVMe</b> — Email ilimitado'       => '<b>200 GB NVMe</b> — Unlimited email',
            'Redis optimizado Woo'                       => 'Woo-optimised Redis',
            'Turnstile + WPO inicial'                    => 'Turnstile + Initial WPO',
            'WAF opcional (CF Pro)'                      => 'Optional WAF (CF Pro)',

            /* ── Mantenimiento — Plan names / subtitles / CTA ───── */
            'Plan Semilla'                               => 'Seed Plan',
            'Para webs pequeñas pero importantes'        => 'For small but important websites',
            'Contratar este plan'                        => 'Choose this plan',
            'Plan Raíz'                                  => 'Root Plan',
            'Para empresas que viven de su web'          => 'For businesses that depend on their website',
            'Empezar con este plan'                      => 'Start with this plan',
            'Plan Ecosistema'                            => 'Ecosystem Plan',
            'Para proyectos que crecen sin freno'        => 'For projects that scale without limits',
            'Solicitar este plan'                        => 'Request this plan',

            /* ── Mantenimiento — Feature text ───────────────────── */
            'Actualizaciones <strong>mensuales</strong> (WP, plugins y temas)'
                => '<strong>Monthly</strong> updates (WP, plugins &amp; themes)',
            'Copias de seguridad <strong>semanales</strong> fuera del servidor'
                => '<strong>Weekly</strong> off-server backups',
            'Optimización básica WPO'
                => 'Basic WPO optimisation',
            'Revisión trimestral de rendimiento'
                => 'Quarterly performance review',
            'Soporte por email'
                => 'Email support',
            'Todo lo del plan Semilla'
                => 'Everything in the Seed Plan',
            'Actualizaciones <strong>semanales</strong> + staging si procede'
                => '<strong>Weekly</strong> updates + staging when needed',
            'Monitorización 24/7 con alertas'
                => '24/7 monitoring with alerts',
            'Revisión SEO técnico + WPO <strong>mensual</strong>'
                => 'Technical SEO + WPO review <strong>monthly</strong>',
            'Cloudflare Free <em>configurado</em>'
                => 'Cloudflare Free <em>configured</em>',
            'Todo lo del plan Raíz'
                => 'Everything in the Root Plan',
            'Consultoría técnica <strong>trimestral</strong>'
                => '<strong>Quarterly</strong> technical consultancy',
            '<strong>Hosting ecológico incluido</strong> (plan Cedro)'
                => '<strong>Green hosting included</strong> (Cedro plan)',
            'Auditoría SEO/WPO <strong>trimestral</strong>'
                => '<strong>Quarterly</strong> SEO/WPO audit',
            'Ajustes avanzados de caché/CDN'
                => 'Advanced cache/CDN tuning',

            /* ── Mantenimiento — Tooltip tips ────────────────────── */
            'Backup previo + punto de restauración. Prueba rápida del frontal tras actualizar. Si algo falla, revertimos y proponemos alternativa segura.'
                => 'Pre-update backup + restore point. Quick front-end check after updating. If anything breaks, we roll back and propose a safe alternative.',
            'Backups externos con JetBackup. Restauración granular (archivos/BD/correo) en 1 clic.'
                => 'Off-server backups with JetBackup. Granular restore (files/DB/email) in 1 click.',
            'Presets de LSCache, limpieza de base de datos mensual y control de medios huérfanos.'
                => 'LSCache presets, monthly database cleanup and orphaned media control.',
            'Informe con métricas clave (TTFB/LCP/Core Web Vitals) y 2–3 acciones recomendadas.'
                => 'Report with key metrics (TTFB/LCP/Core Web Vitals) and 2–3 recommended actions.',
            'Te acompañamos en incidencias habituales (errores tras actualización, dudas de configuración, etc.).'
                => 'We support you with common issues (post-update errors, configuration questions, etc.).',
            'Cambios de riesgo probados en clon. Ventanas programadas para evitar impacto en ventas/conversión.'
                => 'Risky changes tested on a clone. Scheduled windows to avoid sales/conversion impact.',
            'Detección de caídas, 5xx, caducidad de SSL y picos anómalos. Actuación prioritaria según criticidad.'
                => 'Downtime, 5xx, SSL expiry and anomalous spike detection. Priority action by severity.',
            'Salud de indexación (robots/sitemaps), CWV, limpieza de 404 y redirecciones. Acciones concretas cada mes.'
                => 'Indexation health (robots/sitemaps), CWV, 404 cleanup and redirects. Concrete actions every month.',
            'Tu ticket salta a la primera cola. Gestión proactiva en incidencias sensibles (checkout/contacto).'
                => 'Your ticket jumps to the front of the queue. Proactive handling of sensitive issues (checkout/contact).',
            'CDN global, reglas para WordPress/Woo y security headers base. Te lo dejamos listo y documentado.'
                => 'Global CDN, WordPress/Woo rules and base security headers. We set it up and document it.',
            'Reunión de mejora continua: roadmap técnico, priorización por impacto y revisión de métricas de negocio.'
                => 'Continuous improvement meeting: technical roadmap, impact-based prioritisation and business metrics review.',
            'NVMe + LiteSpeed + Redis sobre energía 100 % renovable. Migración sin caídas y soporte prioritario.'
                => 'NVMe + LiteSpeed + Redis on 100 % renewable energy. Zero-downtime migration and priority support.',
            'Crawling profundo, Core Web Vitals, arquitectura interna y rendimiento por tipo de plantilla/URL.'
                => 'Deep crawl, Core Web Vitals, internal architecture and performance by template/URL type.',
            'Tuning específico para tu stack (WP/Woo/LMS/memberships): reglas por ruta, TTL y exclusiones críticas.'
                => 'Stack-specific tuning (WP/Woo/LMS/memberships): per-route rules, TTL and critical exclusions.',
        );
    }

    /**
     * Replace Spanish default strings with English equivalents in a category array.
     * Only strings that exactly match a known default are translated;
     * admin-customised text is left untouched.
     *
     * @param  array $category  Category data from get_all().
     * @return array            Category data with EN text.
     */
    private static function apply_en( $category ) {
        $map = self::en_strings();

        // Category-level strings
        foreach ( array( 'title', 'subtitle', 'footer_note' ) as $key ) {
            if ( isset( $category[ $key ] ) && isset( $map[ $category[ $key ] ] ) ) {
                $category[ $key ] = $map[ $category[ $key ] ];
            }
        }

        if ( empty( $category['plans'] ) ) {
            return $category;
        }

        foreach ( $category['plans'] as $slug => &$plan ) {
            // Plan-level strings
            foreach ( array( 'name', 'subtitle', 'cta_text' ) as $key ) {
                if ( isset( $plan[ $key ] ) && isset( $map[ $plan[ $key ] ] ) ) {
                    $plan[ $key ] = $map[ $plan[ $key ] ];
                }
            }

            // Features (string or array with text + tip)
            foreach ( array( 'features', 'features_extra' ) as $fkey ) {
                if ( empty( $plan[ $fkey ] ) ) {
                    continue;
                }
                foreach ( $plan[ $fkey ] as $i => $feat ) {
                    if ( is_array( $feat ) ) {
                        if ( isset( $feat['text'] ) && isset( $map[ $feat['text'] ] ) ) {
                            $plan[ $fkey ][ $i ]['text'] = $map[ $feat['text'] ];
                        }
                        if ( ! empty( $feat['tip'] ) && isset( $map[ $feat['tip'] ] ) ) {
                            $plan[ $fkey ][ $i ]['tip'] = $map[ $feat['tip'] ];
                        }
                    } else {
                        if ( isset( $map[ $feat ] ) ) {
                            $plan[ $fkey ][ $i ] = $map[ $feat ];
                        }
                    }
                }
            }
        }
        unset( $plan );

        return $category;
    }
}
