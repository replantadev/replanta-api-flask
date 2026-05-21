<?php
/**
 * Admin settings page: Settings > Replanta Prices.
 * Four tabs: Config, Products & Prices, Features, AWIN Analytics.
 *
 * @package Replanta_Prices
 */

defined( 'ABSPATH' ) || exit;

class Replanta_Prices_Admin {

    const PAGE_SLUG = 'replanta-prices';

    public static function init() {
        if ( ! is_admin() ) {
            return;
        }
        add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'admin_init', array( __CLASS__, 'handle_price_save' ) );
        add_action( 'admin_init', array( __CLASS__, 'handle_features_save' ) );
        add_action( 'admin_init', array( __CLASS__, 'handle_awin_s2s_queue_process' ) );
        add_action( 'admin_init', array( __CLASS__, 'handle_feed_actions' ) );
    }

    /* ─── Menu ─────────────────────────────────────────────────────── */

    public static function add_menu() {
        add_options_page(
            'Replanta Precios',
            'Replanta Precios',
            'manage_options',
            self::PAGE_SLUG,
            array( __CLASS__, 'render_page' )
        );
    }

    /* ─── Register Settings ────────────────────────────────────────── */

    public static function register_settings() {
        register_setting( 'replanta_prices_settings_group', 'replanta_prices_settings', array(
            'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
        ) );
    }

    public static function sanitize_settings( $input ) {
        $clean = array();
        $clean['api_token']        = isset( $input['api_token'] )        ? sanitize_text_field( $input['api_token'] )        : '';
        $clean['api_base_url']     = isset( $input['api_base_url'] )     ? esc_url_raw( $input['api_base_url'] )             : 'https://api.upmind.io';
        $clean['upmind_order_url'] = isset( $input['upmind_order_url'] ) ? esc_url_raw( $input['upmind_order_url'] )         : 'https://clientes.replanta.net/order/';
        $clean['cache_ttl']        = isset( $input['cache_ttl'] )        ? absint( $input['cache_ttl'] )                     : 21600;
        $clean['awin_mastertag']   = ! empty( $input['awin_mastertag'] ) ? 1 : 0;
        $clean['awin_landing_script'] = ! empty( $input['awin_landing_script'] ) ? 1 : 0;
        $clean['awin_s2s_enabled'] = ! empty( $input['awin_s2s_enabled'] ) ? 1 : 0;
        $clean['awin_s2s_merchant_id'] = isset( $input['awin_s2s_merchant_id'] ) ? absint( $input['awin_s2s_merchant_id'] ) : 125596;
        $clean['awin_s2s_channel'] = isset( $input['awin_s2s_channel'] ) ? sanitize_key( $input['awin_s2s_channel'] ) : 'aw';
        $clean['awin_s2s_testmode'] = ! empty( $input['awin_s2s_testmode'] ) ? 1 : 0;
        $clean['awin_s2s_voucher'] = isset( $input['awin_s2s_voucher'] ) ? sanitize_text_field( $input['awin_s2s_voucher'] ) : '';
        $clean['awin_s2s_endpoint'] = isset( $input['awin_s2s_endpoint'] ) ? esc_url_raw( $input['awin_s2s_endpoint'] ) : 'https://www.awin1.com/sread.php';
        $clean['awin_s2s_commission_map'] = isset( $input['awin_s2s_commission_map'] ) ? sanitize_textarea_field( $input['awin_s2s_commission_map'] ) : '';

        if ( $clean['cache_ttl'] < 3600 ) {
            $clean['cache_ttl'] = 3600;
        }

        if ( $clean['awin_s2s_merchant_id'] <= 0 ) {
            $clean['awin_s2s_merchant_id'] = 125596;
        }

        if ( '' === $clean['awin_s2s_channel'] ) {
            $clean['awin_s2s_channel'] = 'aw';
        }

        if ( '' === $clean['awin_s2s_endpoint'] ) {
            $clean['awin_s2s_endpoint'] = 'https://www.awin1.com/sread.php';
        }

        return $clean;
    }

    public static function handle_awin_s2s_queue_process() {
        if ( ! isset( $_GET['page'], $_GET['tab'], $_GET['action'] ) ) {
            return;
        }
        if ( self::PAGE_SLUG !== $_GET['page'] || 'awin' !== $_GET['tab'] || 'process_awin_s2s_queue' !== $_GET['action'] ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        check_admin_referer( 'replanta_awin_s2s_process' );

        if ( class_exists( 'Replanta_Prices_Awin_Analytics' ) ) {
            $result = Replanta_Prices_Awin_Analytics::process_s2s_queue_now( 50 );
            add_settings_error(
                'replanta_prices',
                'awin_s2s_processed',
                sprintf(
                    __( 'Cola S2S procesada. Procesados: %1$d, enviados: %2$d, con error/retry: %3$d.', 'replanta-prices' ),
                    isset( $result['processed'] ) ? (int) $result['processed'] : 0,
                    isset( $result['sent'] ) ? (int) $result['sent'] : 0,
                    isset( $result['failed'] ) ? (int) $result['failed'] : 0
                ),
                'success'
            );
        }

        wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=awin' ) );
        exit;
    }

    /**
     * Handle POST actions on the Feeds tab (e.g. regenerate_feeds cache bust).
     * Hooked to admin_init.
     */
    public static function handle_feed_actions() {
        if ( ! isset( $_POST['replanta_feed_action'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        check_admin_referer( 'replanta_feed_regenerate', 'replanta_feed_nonce' );

        $action = sanitize_key( $_POST['replanta_feed_action'] );

        if ( 'regenerate_feeds' === $action && class_exists( 'Replanta_Prices_Product_Feed' ) ) {
            Replanta_Prices_Product_Feed::bust_cache();
            add_settings_error(
                'replanta_prices_feeds',
                'feeds_regenerated',
                __( 'Caché de feeds eliminado. Los feeds se regenerarán en la próxima petición.', 'replanta-prices' ),
                'success'
            );
        }

        wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=feeds' ) );
        exit;
    }



    public static function enqueue_assets( $hook ) {
        if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
            return;
        }
        wp_enqueue_media(); // needed for media picker buttons
        wp_enqueue_style(
            'replanta-prices-admin',
            REPLANTA_PRICES_URL . 'assets/css/admin.css',
            array(),
            REPLANTA_PRICES_VERSION
        );
        wp_enqueue_script(
            'replanta-prices-admin',
            REPLANTA_PRICES_URL . 'assets/js/admin.js',
            array( 'jquery', 'media' ),
            REPLANTA_PRICES_VERSION,
            true
        );
        wp_localize_script( 'replanta-prices-admin', 'replantaPricesAdmin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'replanta_prices_nonce' ),
        ) );
    }

    /** Currencies that get manual price fields in admin */
    const EXTRA_CURRENCIES = array( 'USD', 'MXN', 'COP', 'CLP', 'ARS', 'PEN' );

    /* ─── Handle manual price save ─────────────────────────────────── */

    public static function handle_price_save() {
        if ( ! isset( $_POST['replanta_prices_save_prices'] ) ) {
            return;
        }
        if ( ! check_admin_referer( 'replanta_prices_save_prices_nonce' ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $products = Replanta_Prices_Cache::get_all();

        foreach ( $products as $type => &$category ) {
            if ( ! isset( $category['plans'] ) ) {
                continue;
            }

            if ( 'sapwoo' === $type ) {
                $quote_target_key = "quote_target_{$type}";
                if ( isset( $_POST[ $quote_target_key ] ) ) {
                    $quote_target = sanitize_key( wp_unslash( $_POST[ $quote_target_key ] ) );
                    $category['quote_target'] = in_array( $quote_target, array( 'contact', 'modal' ), true ) ? $quote_target : 'contact';
                }
            }

            foreach ( $category['plans'] as $slug => &$plan ) {
                $key_m = "price_{$type}_{$slug}_m";
                $key_y = "price_{$type}_{$slug}_y";
                if ( isset( $_POST[ $key_m ] ) ) {
                    $plan['price_m'] = floatval( str_replace( ',', '.', sanitize_text_field( $_POST[ $key_m ] ) ) );
                }
                if ( isset( $_POST[ $key_y ] ) ) {
                    $plan['price_y'] = floatval( str_replace( ',', '.', sanitize_text_field( $_POST[ $key_y ] ) ) );
                }

                // Setup price (sapwoo)
                $key_setup = "price_{$type}_{$slug}_setup";
                if ( isset( $_POST[ $key_setup ] ) ) {
                    $plan['price_setup'] = floatval( str_replace( ',', '.', sanitize_text_field( $_POST[ $key_setup ] ) ) );
                }

                if ( 'sapwoo' === $type ) {
                    $quote_key = "price_{$type}_{$slug}_quote";
                    $plan['quote_request'] = isset( $_POST[ $quote_key ] );
                }

                // Image URL for feeds — esc_url_raw directly, no sanitize_text_field (that strips URL chars).
                $key_img = "price_{$type}_{$slug}_image_url";
                if ( array_key_exists( $key_img, $_POST ) ) {
                    $plan['image_url'] = esc_url_raw( wp_unslash( $_POST[ $key_img ] ) );
                }

                // LATAM / USD prices
                if ( ! isset( $plan['prices'] ) ) {
                    $plan['prices'] = array();
                }
                foreach ( self::EXTRA_CURRENCIES as $cur ) {
                    $k_m = "price_{$type}_{$slug}_{$cur}_m";
                    $k_y = "price_{$type}_{$slug}_{$cur}_y";
                    if ( isset( $_POST[ $k_m ] ) ) {
                        $plan['prices'][ $cur ]['m'] = floatval( str_replace( ',', '.', sanitize_text_field( $_POST[ $k_m ] ) ) );
                    }
                    if ( isset( $_POST[ $k_y ] ) ) {
                        $plan['prices'][ $cur ]['y'] = floatval( str_replace( ',', '.', sanitize_text_field( $_POST[ $k_y ] ) ) );
                    }
                    $k_setup = "price_{$type}_{$slug}_{$cur}_setup";
                    if ( isset( $_POST[ $k_setup ] ) ) {
                        $plan['prices'][ $cur ]['setup'] = floatval( str_replace( ',', '.', sanitize_text_field( $_POST[ $k_setup ] ) ) );
                    }
                }
            }
            unset( $plan );
        }
        unset( $category );

        update_option( Replanta_Prices_Cache::OPT_PRODUCTS, $products, true );

        add_settings_error( 'replanta_prices', 'prices_saved',
            __( 'Precios actualizados correctamente.', 'replanta-prices' ), 'success' );
    }

    /* ─── Handle features save ─────────────────────────────────────── */

    public static function handle_features_save() {
        if ( ! isset( $_POST['replanta_prices_save_features'] ) ) {
            return;
        }
        if ( ! check_admin_referer( 'replanta_prices_save_features_nonce' ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $products = Replanta_Prices_Cache::get_all();

        foreach ( $products as $type => &$category ) {
            if ( ! isset( $category['plans'] ) ) {
                continue;
            }

            // Category title & subtitle
            $title_key    = "cat_title_{$type}";
            $subtitle_key = "cat_subtitle_{$type}";
            $footer_key   = "cat_footer_{$type}";
            if ( isset( $_POST[ $title_key ] ) ) {
                $category['title'] = wp_kses_post( wp_unslash( $_POST[ $title_key ] ) );
            }
            if ( isset( $_POST[ $subtitle_key ] ) ) {
                $category['subtitle'] = wp_kses_post( wp_unslash( $_POST[ $subtitle_key ] ) );
            }
            if ( isset( $_POST[ $footer_key ] ) ) {
                $category['footer_note'] = wp_kses_post( wp_unslash( $_POST[ $footer_key ] ) );
            }

            foreach ( $category['plans'] as $slug => &$plan ) {
                // Plan name, subtitle, cta_text
                $name_key     = "feat_{$type}_{$slug}_name";
                $subtitle_key = "feat_{$type}_{$slug}_subtitle";
                $cta_key      = "feat_{$type}_{$slug}_cta";

                if ( isset( $_POST[ $name_key ] ) ) {
                    $plan['name'] = sanitize_text_field( wp_unslash( $_POST[ $name_key ] ) );
                }
                if ( isset( $_POST[ $subtitle_key ] ) ) {
                    $plan['subtitle'] = sanitize_text_field( wp_unslash( $_POST[ $subtitle_key ] ) );
                }
                if ( isset( $_POST[ $cta_key ] ) ) {
                    $plan['cta_text'] = sanitize_text_field( wp_unslash( $_POST[ $cta_key ] ) );
                }

                // Features (one per line)
                $feat_key = "feat_{$type}_{$slug}_features";
                if ( isset( $_POST[ $feat_key ] ) ) {
                    $raw_lines = explode( "\n", wp_unslash( $_POST[ $feat_key ] ) );
                    $plan['features'] = self::parse_features_lines( $raw_lines, $type );
                }

                // Features extra (hosting only, one per line, plain HTML)
                $extra_key = "feat_{$type}_{$slug}_features_extra";
                if ( isset( $_POST[ $extra_key ] ) ) {
                    $raw_lines = explode( "\n", wp_unslash( $_POST[ $extra_key ] ) );
                    $plan['features_extra'] = array_values( array_filter( array_map( function( $line ) {
                        return wp_kses_post( trim( $line ) );
                    }, $raw_lines ) ) );
                }
            }
            unset( $plan );
        }
        unset( $category );

        update_option( Replanta_Prices_Cache::OPT_PRODUCTS, $products, true );

        add_settings_error( 'replanta_prices', 'features_saved',
            __( 'Características actualizadas correctamente.', 'replanta-prices' ), 'success' );
    }

    /**
     * Parse feature lines from textarea.
     *
     * For mantenimiento: "Text || Tooltip" format supported.
     * For hosting: plain HTML lines.
     *
     * @param  array  $lines
     * @param  string $type  'hosting' or 'mantenimiento'
     * @return array
     */
    private static function parse_features_lines( $lines, $type ) {
        $features = array();
        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( '' === $line ) {
                continue;
            }
            if ( in_array( $type, array( 'mantenimiento', 'sapwoo' ), true ) && strpos( $line, '||' ) !== false ) {
                $parts = explode( '||', $line, 2 );
                $features[] = array(
                    'text' => wp_kses_post( trim( $parts[0] ) ),
                    'tip'  => wp_kses_post( trim( $parts[1] ) ),
                );
            } elseif ( in_array( $type, array( 'mantenimiento', 'sapwoo' ), true ) ) {
                $features[] = array(
                    'text' => wp_kses_post( $line ),
                    'tip'  => '',
                );
            } else {
                $features[] = wp_kses_post( $line );
            }
        }
        return $features;
    }

    /* ─── Render Page ──────────────────────────────────────────────── */

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'config';
        $settings   = get_option( 'replanta_prices_settings', array() );
        $last_sync  = get_option( Replanta_Prices_Cache::OPT_LAST_SYNC, 0 );
        $sync_log   = get_option( Replanta_Prices_Cache::OPT_SYNC_LOG, array() );
        ?>
        <div class="wrap replanta-prices-admin">
            <h1>Replanta Precios</h1>
            <p class="description">v<?php echo esc_html( REPLANTA_PRICES_VERSION ); ?> — <?php esc_html_e( 'Precios dinámicos con detección geográfica multi-divisa', 'replanta-prices' ); ?></p>

            <nav class="nav-tab-wrapper">
                <a href="?page=<?php echo esc_attr( self::PAGE_SLUG ); ?>&tab=config"
                   class="nav-tab <?php echo 'config' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Configuración', 'replanta-prices' ); ?>
                </a>
                <a href="?page=<?php echo esc_attr( self::PAGE_SLUG ); ?>&tab=prices"
                   class="nav-tab <?php echo 'prices' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Productos & Precios', 'replanta-prices' ); ?>
                </a>
                <a href="?page=<?php echo esc_attr( self::PAGE_SLUG ); ?>&tab=features"
                   class="nav-tab <?php echo 'features' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Características', 'replanta-prices' ); ?>
                </a>
                <a href="?page=<?php echo esc_attr( self::PAGE_SLUG ); ?>&tab=awin"
                   class="nav-tab <?php echo 'awin' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'AWIN Analytics', 'replanta-prices' ); ?>
                </a>
                <a href="?page=<?php echo esc_attr( self::PAGE_SLUG ); ?>&tab=awin-docs"
                   class="nav-tab <?php echo 'awin-docs' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'AWIN Docs', 'replanta-prices' ); ?>
                </a>
                <a href="?page=<?php echo esc_attr( self::PAGE_SLUG ); ?>&tab=feeds"
                   class="nav-tab <?php echo 'feeds' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Feeds', 'replanta-prices' ); ?>
                </a>
            </nav>

            <div class="tab-content" style="margin-top:20px;">
                <?php
                if ( 'prices' === $active_tab ) {
                    self::render_prices_tab();
                } elseif ( 'features' === $active_tab ) {
                    self::render_features_tab();
                } elseif ( 'awin' === $active_tab ) {
                    self::render_awin_tab();
                } elseif ( 'awin-docs' === $active_tab ) {
                    self::render_awin_docs_tab();
                } elseif ( 'feeds' === $active_tab ) {
                    self::render_feeds_tab();
                } else {
                    self::render_config_tab( $settings, $last_sync, $sync_log );
                }
                ?>
            </div>
        </div>
        <?php
    }

    /* ─── Config Tab ───────────────────────────────────────────────── */

    private static function render_config_tab( $settings, $last_sync, $sync_log ) {
        $api_token        = isset( $settings['api_token'] )        ? $settings['api_token']        : '';
        $api_base_url     = isset( $settings['api_base_url'] )     ? $settings['api_base_url']     : 'https://api.upmind.io';
        $upmind_order_url = isset( $settings['upmind_order_url'] ) ? $settings['upmind_order_url'] : 'https://clientes.replanta.net/order/';
        $cache_ttl        = isset( $settings['cache_ttl'] )        ? $settings['cache_ttl']        : 21600;
        $awin_s2s_enabled = ! empty( $settings['awin_s2s_enabled'] ) ? 1 : 0;
        $awin_s2s_merchant_id = isset( $settings['awin_s2s_merchant_id'] ) ? (int) $settings['awin_s2s_merchant_id'] : 125596;
        $awin_s2s_channel = isset( $settings['awin_s2s_channel'] ) ? $settings['awin_s2s_channel'] : 'aw';
        $awin_s2s_testmode = ! empty( $settings['awin_s2s_testmode'] ) ? 1 : 0;
        $awin_s2s_voucher = isset( $settings['awin_s2s_voucher'] ) ? $settings['awin_s2s_voucher'] : '';
        $awin_s2s_endpoint = isset( $settings['awin_s2s_endpoint'] ) ? $settings['awin_s2s_endpoint'] : 'https://www.awin1.com/sread.php';
        $awin_s2s_commission_map = isset( $settings['awin_s2s_commission_map'] ) ? $settings['awin_s2s_commission_map'] : '';
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'replanta_prices_settings_group' ); ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="api_token"><?php esc_html_e( 'API Token', 'replanta-prices' ); ?></label></th>
                    <td>
                        <input type="password" id="api_token" name="replanta_prices_settings[api_token]"
                               value="<?php echo esc_attr( $api_token ); ?>"
                               class="regular-text" autocomplete="off">
                        <button type="button" id="replanta-prices-test-btn" class="button button-secondary" style="margin-left:8px;vertical-align:middle;">
                            <?php esc_html_e( 'Verificar conexión', 'replanta-prices' ); ?>
                        </button>
                        <span id="replanta-prices-test-status" style="margin-left:10px;vertical-align:middle;font-weight:600;"></span>
                        <p class="description"><?php
                            printf(
                                /* translators: %s: link to generate API token */
                                esc_html__( 'Token de Admin API de Upmind. %s', 'replanta-prices' ),
                                '<a href="https://clientes.replanta.net/admin/settings/api-tokens" target="_blank">' . esc_html__( 'Generar token →', 'replanta-prices' ) . '</a>'
                            );
                        ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="api_base_url"><?php esc_html_e( 'Base URL', 'replanta-prices' ); ?></label></th>
                    <td>
                        <input type="url" id="api_base_url" name="replanta_prices_settings[api_base_url]"
                               value="<?php echo esc_attr( $api_base_url ); ?>"
                               class="regular-text">
                        <p class="description"><?php esc_html_e( 'URL de la API de Upmind (por defecto: api.upmind.io).', 'replanta-prices' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="upmind_order_url"><?php esc_html_e( 'Upmind Order URL', 'replanta-prices' ); ?></label></th>
                    <td>
                        <input type="url" id="upmind_order_url" name="replanta_prices_settings[upmind_order_url]"
                               value="<?php echo esc_attr( $upmind_order_url ); ?>"
                               class="regular-text" placeholder="https://clientes.replanta.net/order/">
                        <p class="description"><?php esc_html_e( 'URL base del checkout de Upmind. Usada por [replanta_domains] para el atributo order-config-url del widget upm-dac. Defecto: https://clientes.replanta.net/order/', 'replanta-prices' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="cache_ttl"><?php esc_html_e( 'Cache TTL', 'replanta-prices' ); ?></label></th>
                    <td>
                        <input type="number" id="cache_ttl" name="replanta_prices_settings[cache_ttl]"
                               value="<?php echo esc_attr( $cache_ttl ); ?>"
                               min="3600" step="3600" class="small-text">
                        <span class="description"><?php esc_html_e( 'segundos (mínimo 3600 = 1 hora)', 'replanta-prices' ); ?></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Awin MasterTag', 'replanta-prices' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="replanta_prices_settings[awin_mastertag]" value="1"
                                   <?php checked( 1, isset( $settings['awin_mastertag'] ) ? (int) $settings['awin_mastertag'] : 0 ); ?>>
                            <?php esc_html_e( 'Insertar MasterTag de Awin en todas las páginas (dwin1.com/125596.js)', 'replanta-prices' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Carga el script base de Awin sin depender de GTM ni del consentimiento de cookies. Necesario para que Awin valide el client-side tracking.', 'replanta-prices' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Awin Landing Script', 'replanta-prices' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="replanta_prices_settings[awin_landing_script]" value="1"
                                   <?php checked( 1, isset( $settings['awin_landing_script'] ) ? (int) $settings['awin_landing_script'] : 0 ); ?>>
                            <?php esc_html_e( 'Insertar script AWIN completo en todas las páginas (captura AWC, arrival, begin_checkout, consent, MasterTag fallback)', 'replanta-prices' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Inyecta el script de tracking AWIN vía wp_head. Sustituye al widget HTML de Elementor — no necesitas pegarlo manualmente en cada página.', 'replanta-prices' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'AWIN S2S Relay', 'replanta-prices' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="replanta_prices_settings[awin_s2s_enabled]" value="1"
                                   <?php checked( 1, (int) $awin_s2s_enabled ); ?>>
                            <?php esc_html_e( 'Enviar compras purchase a AWIN desde servidor (relay S2S vía endpoint del plugin).', 'replanta-prices' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Cuando está activo, cada evento purchase recibido en /awin-event se encola y se reintenta por WP-Cron hasta confirmar envío.', 'replanta-prices' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="awin_s2s_merchant_id"><?php esc_html_e( 'AWIN Merchant ID', 'replanta-prices' ); ?></label></th>
                    <td>
                        <input type="number" id="awin_s2s_merchant_id" name="replanta_prices_settings[awin_s2s_merchant_id]"
                               value="<?php echo esc_attr( $awin_s2s_merchant_id ); ?>"
                               min="1" class="small-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="awin_s2s_endpoint"><?php esc_html_e( 'AWIN Endpoint S2S', 'replanta-prices' ); ?></label></th>
                    <td>
                        <input type="url" id="awin_s2s_endpoint" name="replanta_prices_settings[awin_s2s_endpoint]"
                               value="<?php echo esc_attr( $awin_s2s_endpoint ); ?>"
                               class="regular-text">
                        <p class="description"><?php esc_html_e( 'Por defecto usa sread.php. Si AWIN te facilita otro endpoint server-side, indícalo aquí.', 'replanta-prices' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="awin_s2s_channel"><?php esc_html_e( 'Canal', 'replanta-prices' ); ?></label></th>
                    <td>
                        <input type="text" id="awin_s2s_channel" name="replanta_prices_settings[awin_s2s_channel]"
                               value="<?php echo esc_attr( $awin_s2s_channel ); ?>"
                               class="small-text" maxlength="12">
                        <span class="description"><?php esc_html_e( 'Normalmente: aw', 'replanta-prices' ); ?></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="awin_s2s_voucher"><?php esc_html_e( 'Voucher (vc)', 'replanta-prices' ); ?></label></th>
                    <td>
                        <input type="text" id="awin_s2s_voucher" name="replanta_prices_settings[awin_s2s_voucher]"
                               value="<?php echo esc_attr( $awin_s2s_voucher ); ?>"
                               class="regular-text">
                        <p class="description"><?php esc_html_e( 'Opcional. Si no usas cupones, déjalo vacío.', 'replanta-prices' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Test Mode S2S', 'replanta-prices' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="replanta_prices_settings[awin_s2s_testmode]" value="1"
                                   <?php checked( 1, (int) $awin_s2s_testmode ); ?>>
                            <?php esc_html_e( 'Enviar testmode=1 (solo para pruebas)', 'replanta-prices' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="awin_s2s_commission_map"><?php esc_html_e( 'Mapa PID → Comisión', 'replanta-prices' ); ?></label></th>
                    <td>
                        <textarea id="awin_s2s_commission_map" name="replanta_prices_settings[awin_s2s_commission_map]" rows="6" class="large-text code"><?php echo esc_textarea( $awin_s2s_commission_map ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Formato: un PID por línea con "PID=GRUPO". Ejemplo: 6d...=HOSTING. Si un PID no existe en el mapa, se usa DEFAULT.', 'replanta-prices' ); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button( __( 'Guardar configuración', 'replanta-prices' ) ); ?>
        </form>

        <hr>

        <h2><?php esc_html_e( 'Sincronización con Upmind', 'replanta-prices' ); ?></h2>
        <p>
            <strong><?php esc_html_e( 'Última sincronización:', 'replanta-prices' ); ?></strong>
            <?php
            if ( $last_sync > 0 ) {
                echo esc_html( wp_date( 'd/m/Y H:i:s', $last_sync ) );
            } else {
                echo '<em>' . esc_html__( 'Nunca', 'replanta-prices' ) . '</em>';
            }
            ?>
        </p>
        <p>
            <button type="button" id="replanta-prices-sync-btn" class="button button-secondary">
                <?php esc_html_e( 'Sincronizar ahora', 'replanta-prices' ); ?>
            </button>
            <span id="replanta-prices-sync-status" style="margin-left:12px;"></span>
        </p>

        <?php if ( ! empty( $sync_log ) ) : ?>
            <h3><?php esc_html_e( 'Log última sincronización', 'replanta-prices' ); ?></h3>
            <div class="replanta-sync-log">
                <?php foreach ( $sync_log as $entry ) : ?>
                    <code><?php echo esc_html( $entry ); ?></code><br>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <hr>

        <h2><?php esc_html_e( 'Shortcodes disponibles', 'replanta-prices' ); ?></h2>
        <table class="widefat fixed" style="max-width:700px;">
            <thead>
                <tr>
                    <th>Shortcode</th>
                    <th><?php esc_html_e( 'Descripción', 'replanta-prices' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>[replanta_pricing type="hosting"]</code></td>
                    <td><?php esc_html_e( 'Grid de 3 tarjetas de hosting con toggle mensual/anual', 'replanta-prices' ); ?></td>
                </tr>
                <tr>
                    <td><code>[replanta_pricing type="mantenimiento"]</code></td>
                    <td><?php esc_html_e( 'Grid de 3 tarjetas de mantenimiento (solo mensual)', 'replanta-prices' ); ?></td>
                </tr>
                <tr>
                    <td><code>[replanta_price plan="sauce" period="monthly"]</code></td>
                    <td><?php esc_html_e( 'Precio inline de un plan concreto', 'replanta-prices' ); ?></td>
                </tr>
                <tr>
                    <td><code>[replanta_pricing type="sapwoo"]</code></td>
                    <td><?php esc_html_e( 'Grid de planes SAP WooCommerce (setup + mensual)', 'replanta-prices' ); ?></td>
                </tr>
            </tbody>
        </table>

        <hr>

        <h2><?php esc_html_e( 'Test geolocalización', 'replanta-prices' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Añade ?geo_test=XX a cualquier URL para simular un país (solo admins). Ejemplos:', 'replanta-prices' ); ?></p>
        <ul style="list-style:disc;padding-left:20px;">
            <li><code>?geo_test=ES</code> → EUR</li>
            <li><code>?geo_test=US</code> → USD</li>
            <li><code>?geo_test=MX</code> → MXN</li>
            <li><code>?geo_test=CO</code> → COP</li>
            <li><code>?geo_test=BR</code> → BRL</li>
        </ul>
        <?php
    }

    /* ─── Prices Tab ───────────────────────────────────────────────── */

    private static function render_prices_tab() {
        $products  = Replanta_Prices_Cache::get_all();
        $has_annual = array( 'hosting' => true, 'mantenimiento' => false, 'sapwoo' => false );
        $has_setup  = array( 'hosting' => false, 'mantenimiento' => false, 'sapwoo' => true );
        settings_errors( 'replanta_prices' );
        ?>
        <form method="post">
            <?php wp_nonce_field( 'replanta_prices_save_prices_nonce' ); ?>

            <?php foreach ( $products as $type => $category ) :
                $show_annual = ! empty( $has_annual[ $type ] );
                $show_setup  = ! empty( $has_setup[ $type ] );
                $supports_quote = ( 'sapwoo' === $type );
                $quote_target = isset( $category['quote_target'] ) ? $category['quote_target'] : 'contact';
            ?>
                <h2 style="margin-top:24px;"><?php echo esc_html( ucfirst( $type ) ); ?> — <?php esc_html_e( 'Precios base (EUR)', 'replanta-prices' ); ?></h2>
                <table class="widefat striped" style="max-width:960px;">
                    <thead>
                        <tr>
                            <th style="width:160px;"><?php esc_html_e( 'Plan', 'replanta-prices' ); ?></th>
                            <th style="width:280px;">Upmind PID</th>
                            <th style="width:120px;"><?php esc_html_e( 'Mensual (€)', 'replanta-prices' ); ?></th>
                            <th style="width:120px;"><?php esc_html_e( 'Anual (€)', 'replanta-prices' ); ?></th>
                            <?php if ( $show_setup ) : ?>
                            <th style="width:120px;"><?php esc_html_e( 'Setup (€)', 'replanta-prices' ); ?></th>
                            <?php endif; ?>
                            <th style="width:95px;"><?php esc_html_e( 'Featured', 'replanta-prices' ); ?></th>
                            <th><?php esc_html_e( 'Imagen feed (URL)', 'replanta-prices' ); ?></th>
                            <?php if ( $supports_quote ) : ?>
                            <th style="width:135px;"><?php esc_html_e( 'Solicitar presupuesto', 'replanta-prices' ); ?></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( isset( $category['plans'] ) ) : foreach ( $category['plans'] as $slug => $plan ) : ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $plan['name'] ); ?></strong>
                                    <br><small><?php echo esc_html( $plan['subtitle'] ); ?></small>
                                </td>
                                <td>
                                    <code style="font-size:11px;"><?php echo esc_html( $plan['pid'] ); ?></code>
                                </td>
                                <td>
                                    <input type="text"
                                           name="price_<?php echo esc_attr( $type . '_' . $slug ); ?>_m"
                                           value="<?php echo esc_attr( $plan['price_m'] ); ?>"
                                           class="small-text" style="width:90px;">
                                </td>
                                <td>
                                    <?php if ( $show_annual ) : ?>
                                        <input type="text"
                                               name="price_<?php echo esc_attr( $type . '_' . $slug ); ?>_y"
                                               value="<?php echo esc_attr( $plan['price_y'] ); ?>"
                                               class="small-text" style="width:90px;">
                                    <?php else : ?>
                                        <span class="description">N/A</span>
                                        <input type="hidden"
                                               name="price_<?php echo esc_attr( $type . '_' . $slug ); ?>_y"
                                               value="0">
                                    <?php endif; ?>
                                </td>
                                <?php if ( $show_setup ) : ?>
                                <td>
                                    <input type="text"
                                           name="price_<?php echo esc_attr( $type . '_' . $slug ); ?>_setup"
                                           value="<?php echo esc_attr( isset( $plan['price_setup'] ) ? $plan['price_setup'] : '' ); ?>"
                                           class="small-text" style="width:90px;">
                                </td>
                                <?php endif; ?>
                                <td>
                                    <?php echo $plan['featured'] ? esc_html__( 'Sí', 'replanta-prices' ) : '—'; ?>
                                </td>
                <td>
                                    <?php
                                    $img_id  = 'rp_img_' . esc_attr( $type . '_' . $slug );
                                    $img_val = isset( $plan['image_url'] ) ? $plan['image_url'] : '';
                                    ?>
                                    <div style="display:flex;align-items:center;gap:6px;">
                                        <input type="url"
                                               id="<?php echo $img_id; ?>"
                                               name="price_<?php echo esc_attr( $type . '_' . $slug ); ?>_image_url"
                                               value="<?php echo esc_attr( $img_val ); ?>"
                                               class="regular-text rp-img-url" style="flex:1;min-width:0">
                                        <button type="button" class="button rp-media-pick"
                                                data-target="<?php echo $img_id; ?>">
                                            Elegir
                                        </button>
                                        <?php if ( $img_val ) : ?>
                                            <img src="<?php echo esc_url( $img_val ); ?>"
                                                 style="height:32px;width:32px;object-fit:cover;border-radius:3px;border:1px solid #ddd;"
                                                 alt="">
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <?php if ( $supports_quote ) : ?>
                                <td>
                                    <label>
                                        <input type="checkbox"
                                               name="price_<?php echo esc_attr( $type . '_' . $slug ); ?>_quote"
                                               value="1"
                                               <?php checked( ! empty( $plan['quote_request'] ) ); ?>>
                                        <?php esc_html_e( 'Sí', 'replanta-prices' ); ?>
                                    </label>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>

                <?php if ( $supports_quote ) : ?>
                <p style="margin:10px 0 2px;">
                    <label for="quote_target_<?php echo esc_attr( $type ); ?>" style="margin-right:8px;"><strong><?php esc_html_e( 'Destino de "Solicitar presupuesto"', 'replanta-prices' ); ?>:</strong></label>
                    <select id="quote_target_<?php echo esc_attr( $type ); ?>" name="quote_target_<?php echo esc_attr( $type ); ?>">
                        <option value="contact" <?php selected( 'contact', $quote_target ); ?>><?php esc_html_e( 'Página de contacto (/contacto/)', 'replanta-prices' ); ?></option>
                        <option value="modal" <?php selected( 'modal', $quote_target ); ?>><?php esc_html_e( 'Modal breve de captura', 'replanta-prices' ); ?></option>
                    </select>
                </p>
                <?php endif; ?>

                <!-- LATAM / USD prices -->
                <h3 style="margin-top:20px;"><?php echo esc_html( ucfirst( $type ) ); ?> — <?php esc_html_e( 'Precios LATAM & USD', 'replanta-prices' ); ?></h3>
                <p class="description"><?php esc_html_e( 'Si una moneda LATAM no tiene precio declarado, el visitante verá USD automáticamente.', 'replanta-prices' ); ?></p>

                <table class="widefat fixed striped" style="max-width:100%;overflow-x:auto;margin-top:8px;">
                    <thead>
                        <tr>
                            <th style="width:120px;"><?php esc_html_e( 'Plan', 'replanta-prices' ); ?></th>
                            <?php foreach ( self::EXTRA_CURRENCIES as $cur ) : ?>
                                <th style="text-align:center;" colspan="<?php echo 1 + ( $show_annual ? 1 : 0 ) + ( $show_setup ? 1 : 0 ); ?>"><?php echo esc_html( $cur ); ?></th>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <th></th>
                            <?php foreach ( self::EXTRA_CURRENCIES as $cur ) : ?>
                                <?php if ( $show_setup ) : ?>
                                <th style="font-weight:normal;font-size:11px;text-align:center;">setup</th>
                                <?php endif; ?>
                                <th style="font-weight:normal;font-size:11px;text-align:center;">/mes</th>
                                <?php if ( $show_annual ) : ?>
                                <th style="font-weight:normal;font-size:11px;text-align:center;">/año</th>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( isset( $category['plans'] ) ) : foreach ( $category['plans'] as $slug => $plan ) :
                            $prices = isset( $plan['prices'] ) ? $plan['prices'] : array();
                        ?>
                            <tr>
                                <td><strong><?php echo esc_html( $plan['name'] ); ?></strong></td>
                                <?php foreach ( self::EXTRA_CURRENCIES as $cur ) :
                                    $val_m = isset( $prices[ $cur ]['m'] ) ? $prices[ $cur ]['m'] : '';
                                    $val_y = isset( $prices[ $cur ]['y'] ) ? $prices[ $cur ]['y'] : '';
                                ?>
                                    <?php if ( $show_setup ) : ?>
                                    <td style="text-align:center;">
                                        <input type="text"
                                               name="price_<?php echo esc_attr( $type . '_' . $slug . '_' . $cur ); ?>_setup"
                                               value="<?php echo esc_attr( isset( $prices[ $cur ]['setup'] ) ? $prices[ $cur ]['setup'] : '' ); ?>"
                                               class="small-text" style="width:80px;text-align:right;">
                                    </td>
                                    <?php endif; ?>
                                    <td style="text-align:center;">
                                        <input type="text"
                                               name="price_<?php echo esc_attr( $type . '_' . $slug . '_' . $cur ); ?>_m"
                                               value="<?php echo esc_attr( $val_m ); ?>"
                                               class="small-text" style="width:80px;text-align:right;">
                                    </td>
                                    <?php if ( $show_annual ) : ?>
                                    <td style="text-align:center;">
                                        <input type="text"
                                               name="price_<?php echo esc_attr( $type . '_' . $slug . '_' . $cur ); ?>_y"
                                               value="<?php echo esc_attr( $val_y ); ?>"
                                               class="small-text" style="width:80px;text-align:right;">
                                    </td>
                                    <?php else : ?>
                                        <input type="hidden"
                                               name="price_<?php echo esc_attr( $type . '_' . $slug . '_' . $cur ); ?>_y"
                                               value="0">
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>

            <?php endforeach; ?>

            <p style="margin-top:16px;">
                <input type="submit" name="replanta_prices_save_prices"
                       class="button button-primary" value="<?php esc_attr_e( 'Guardar precios', 'replanta-prices' ); ?>">
                <span class="description" style="margin-left:12px;">
                    <?php esc_html_e( 'Precios EUR = base. LATAM/USD = precios locales manuales. Si una moneda queda a 0, el visitante verá USD.', 'replanta-prices' ); ?>
                </span>
            </p>
        </form>
        <?php
    }

    /* ─── Features Tab ─────────────────────────────────────────────── */

    private static function render_features_tab() {
        $products = Replanta_Prices_Cache::get_all();
        settings_errors( 'replanta_prices' );
        ?>
        <form method="post">
            <?php wp_nonce_field( 'replanta_prices_save_features_nonce' ); ?>

            <p class="description" style="margin-bottom:16px;">
                <?php esc_html_e( 'Edita el nombre, subtítulo, texto del botón y características de cada plan. Se admite HTML básico (<b>, <em>, <strong>, &bull;).', 'replanta-prices' ); ?>
                <br>
                <?php esc_html_e( 'Para mantenimiento: usa "Texto || Tooltip" para añadir tooltip a una característica.', 'replanta-prices' ); ?>
            </p>

            <?php foreach ( $products as $type => $category ) : ?>
                <h2 style="margin-top:32px;border-bottom:2px solid #1E2F23;padding-bottom:8px;">
                    <?php echo esc_html( ucfirst( $type ) ); ?>
                </h2>

                <!-- Category title & subtitle -->
                <table class="form-table" style="max-width:800px;">
                    <tr>
                        <th><label><?php esc_html_e( 'Título de categoría', 'replanta-prices' ); ?></label></th>
                        <td>
                            <input type="text" name="cat_title_<?php echo esc_attr( $type ); ?>"
                                   value="<?php echo esc_attr( $category['title'] ); ?>"
                                   class="regular-text large-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Subtítulo', 'replanta-prices' ); ?></label></th>
                        <td>
                            <input type="text" name="cat_subtitle_<?php echo esc_attr( $type ); ?>"
                                   value="<?php echo esc_attr( $category['subtitle'] ); ?>"
                                   class="regular-text large-text">
                        </td>
                    </tr>
                    <?php if ( in_array( $type, array( 'mantenimiento', 'sapwoo' ), true ) ) : ?>
                    <tr>
                        <th><label><?php esc_html_e( 'Nota al pie', 'replanta-prices' ); ?></label></th>
                        <td>
                            <input type="text" name="cat_footer_<?php echo esc_attr( $type ); ?>"
                                   value="<?php echo esc_attr( isset( $category['footer_note'] ) ? $category['footer_note'] : '' ); ?>"
                                   class="regular-text large-text">
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>

                <?php if ( isset( $category['plans'] ) ) : foreach ( $category['plans'] as $slug => $plan ) : ?>
                    <div style="background:#f9f9f9;border:1px solid #e0e0e0;border-radius:6px;padding:16px 20px;margin:16px 0;max-width:800px;">
                        <h3 style="margin-top:0;">
                            <?php echo esc_html( $plan['name'] ); ?>
                            <?php if ( $plan['featured'] ) : ?> <span style="color:#41999F;font-weight:bold;">(destacado)</span><?php endif; ?>
                        </h3>

                        <table class="form-table" style="margin-top:0;">
                            <tr>
                                <th style="width:140px;"><label><?php esc_html_e( 'Nombre', 'replanta-prices' ); ?></label></th>
                                <td>
                                    <input type="text" name="feat_<?php echo esc_attr( $type . '_' . $slug ); ?>_name"
                                           value="<?php echo esc_attr( $plan['name'] ); ?>"
                                           class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php esc_html_e( 'Subtítulo', 'replanta-prices' ); ?></label></th>
                                <td>
                                    <input type="text" name="feat_<?php echo esc_attr( $type . '_' . $slug ); ?>_subtitle"
                                           value="<?php echo esc_attr( $plan['subtitle'] ); ?>"
                                           class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php esc_html_e( 'Texto botón CTA', 'replanta-prices' ); ?></label></th>
                                <td>
                                    <input type="text" name="feat_<?php echo esc_attr( $type . '_' . $slug ); ?>_cta"
                                           value="<?php echo esc_attr( $plan['cta_text'] ); ?>"
                                           class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th>
                                    <label><?php esc_html_e( 'Características', 'replanta-prices' ); ?></label>
                                    <p class="description" style="font-weight:normal;"><?php esc_html_e( 'Una por línea', 'replanta-prices' ); ?></p>
                                </th>
                                <td>
                                    <textarea name="feat_<?php echo esc_attr( $type . '_' . $slug ); ?>_features"
                                              rows="<?php echo count( $plan['features'] ) + 2; ?>"
                                              class="large-text code"
                                              style="font-size:13px;"><?php
                                        echo esc_textarea( self::features_to_text( $plan['features'] ) );
                                    ?></textarea>
                                </td>
                            </tr>
                            <?php if ( 'hosting' === $type ) : ?>
                            <tr>
                                <th>
                                    <label><?php esc_html_e( 'Características extra', 'replanta-prices' ); ?></label>
                                    <p class="description" style="font-weight:normal;"><?php esc_html_e( '"Ver más" expandible', 'replanta-prices' ); ?></p>
                                </th>
                                <td>
                                    <textarea name="feat_<?php echo esc_attr( $type . '_' . $slug ); ?>_features_extra"
                                              rows="4"
                                              class="large-text code"
                                              style="font-size:13px;"><?php
                                        $extra = isset( $plan['features_extra'] ) ? $plan['features_extra'] : array();
                                        echo esc_textarea( implode( "\n", $extra ) );
                                    ?></textarea>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                <?php endforeach; endif; ?>
            <?php endforeach; ?>

            <p style="margin-top:20px;">
                <input type="submit" name="replanta_prices_save_features"
                       class="button button-primary" value="<?php esc_attr_e( 'Guardar características', 'replanta-prices' ); ?>">
            </p>
        </form>
        <?php
    }

    /**
     * Convert features array to text for textarea display.
     *
     * For mantenimiento (array of {text, tip}): "text || tip" per line.
     * For hosting (array of strings): one per line.
     *
     * @param array $features
     * @return string
     */
    private static function features_to_text( $features ) {
        $lines = array();
        foreach ( $features as $feat ) {
            if ( is_array( $feat ) ) {
                $line = isset( $feat['text'] ) ? $feat['text'] : '';
                if ( ! empty( $feat['tip'] ) ) {
                    $line .= ' || ' . $feat['tip'];
                }
                $lines[] = $line;
            } else {
                $lines[] = $feat;
            }
        }
        return implode( "\n", $lines );
    }

    /**
     * Render AWIN analytics tab.
     */
    private static function render_awin_tab() {
        if ( ! class_exists( 'Replanta_Prices_Awin_Analytics' ) ) {
            echo '<p>' . esc_html__( 'Módulo AWIN no disponible.', 'replanta-prices' ) . '</p>';
            return;
        }

        settings_errors( 'replanta_prices' );

        // Period selector (7 / 30 / 90 days).
        $period_days = isset( $_GET['days'] ) ? (int) $_GET['days'] : 30; // phpcs:ignore WordPress.Security.NonceVerification
        $period_days = in_array( $period_days, array( 7, 30, 90 ), true ) ? $period_days : 30;

        $report = Replanta_Prices_Awin_Analytics::get_report( $period_days );
        $totals = $report['totals'];
        $rows   = $report['rows'];
        $s2s    = isset( $report['s2s'] ) && is_array( $report['s2s'] ) ? $report['s2s'] : array();

        // Dashboard data (KPIs + chart series) when class is available.
        $dash = class_exists( 'Replanta_Prices_Awin_Dashboard' )
            ? Replanta_Prices_Awin_Dashboard::get_dashboard_data( $period_days )
            : null;

        $base_url = admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=awin' );
        ?>

        <!-- ── Period picker ────────────────────────────────────────── -->
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
            <h2 style="margin:0;"><?php esc_html_e( 'AWIN Analytics', 'replanta-prices' ); ?></h2>
            <div style="display:flex;gap:6px;">
                <?php foreach ( array( 7, 30, 90 ) as $opt ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'days', $opt, $base_url ) ); ?>"
                       style="padding:4px 12px;border-radius:4px;text-decoration:none;font-weight:<?php echo $period_days === $opt ? '700' : '400'; ?>;background:<?php echo $period_days === $opt ? '#2271b1' : '#f0f0f1'; ?>;color:<?php echo $period_days === $opt ? '#fff' : '#1d2327'; ?>;border:1px solid <?php echo $period_days === $opt ? '#2271b1' : '#ccd0d4'; ?>;">
                        <?php echo esc_html( $opt ); ?>d
                    </a>
                <?php endforeach; ?>
            </div>
            <p class="description" style="margin:0;"><?php esc_html_e( 'Datos desde GTM/snippets frontend. Métricas AWIN cuando llega awc válido.', 'replanta-prices' ); ?></p>
        </div>

        <?php if ( $dash ) : ?>
        <!-- ── KPI Cards ─────────────────────────────────────────────── -->
        <style>
            .rp-kpi-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:14px; max-width:1100px; margin-bottom:24px; }
            .rp-kpi-card { background:#fff; border:1px solid #ccd0d4; border-radius:8px; padding:16px 20px; }
            .rp-kpi-card .rp-kpi-label { font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:#646970; margin-bottom:4px; }
            .rp-kpi-card .rp-kpi-value { font-size:26px; font-weight:700; color:#1d2327; line-height:1.1; }
            .rp-kpi-card .rp-kpi-delta { font-size:12px; margin-top:4px; }
            .rp-kpi-delta.up   { color:#00a32a; }
            .rp-kpi-delta.down { color:#d63638; }
            .rp-kpi-delta.flat { color:#646970; }
            .rp-chart-wrap { background:#fff; border:1px solid #ccd0d4; border-radius:8px; padding:16px 20px; max-width:1100px; margin-bottom:24px; }
            .rp-chart-wrap h3 { margin:0 0 12px; font-size:14px; color:#1d2327; }
        </style>

        <?php
        $kpis      = $dash['kpis'];
        $prev      = $dash['prev_kpis'];
        $chart     = $dash['chart'];
        $top_days  = $dash['top_days'];

        $kpi_defs = array(
            array( 'key' => 'arrivals',        'label' => __( 'Llegadas', 'replanta-prices' ),       'fmt' => 'int' ),
            array( 'key' => 'checkouts_awin',  'label' => __( 'Checkouts AWIN', 'replanta-prices' ), 'fmt' => 'int' ),
            array( 'key' => 'purchases_awin',  'label' => __( 'Compras AWIN', 'replanta-prices' ),   'fmt' => 'int' ),
            array( 'key' => 'revenue_awin',    'label' => __( 'Revenue AWIN', 'replanta-prices' ),   'fmt' => 'eur' ),
        );
        ?>

        <div class="rp-kpi-grid">
        <?php foreach ( $kpi_defs as $def ) :
            $val      = isset( $kpis[ $def['key'] ] ) ? $kpis[ $def['key'] ] : 0;
            $prev_val = isset( $prev[ $def['key'] ] ) ? $prev[ $def['key'] ] : 0;
            $delta    = Replanta_Prices_Awin_Dashboard::delta( $val, $prev_val );
            $val_str  = ( 'eur' === $def['fmt'] ) ? number_format_i18n( (float) $val, 2 ) . ' €' : number_format_i18n( (int) $val );
            if ( null === $delta ) {
                $delta_html = '<span class="rp-kpi-delta flat">— sin período anterior</span>';
            } elseif ( $delta > 0 ) {
                $delta_html = '<span class="rp-kpi-delta up">▲ ' . esc_html( number_format_i18n( $delta, 1 ) ) . '% vs período anterior</span>';
            } elseif ( $delta < 0 ) {
                $delta_html = '<span class="rp-kpi-delta down">▼ ' . esc_html( number_format_i18n( abs( $delta ), 1 ) ) . '% vs período anterior</span>';
            } else {
                $delta_html = '<span class="rp-kpi-delta flat">= sin cambio</span>';
            }
            ?>
            <div class="rp-kpi-card">
                <div class="rp-kpi-label"><?php echo esc_html( $def['label'] ); ?></div>
                <div class="rp-kpi-value"><?php echo esc_html( $val_str ); ?></div>
                <?php echo $delta_html; // phpcs:ignore WordPress.Security.EscapeOutput — content escaped above ?>
            </div>
        <?php endforeach; ?>
        </div>

        <!-- ── Sparkline Chart (Canvas) ─────────────────────────────── -->
        <div class="rp-chart-wrap">
            <h3><?php printf( esc_html__( 'Evolución últimos %d días', 'replanta-prices' ), esc_html( (string) $period_days ) ); ?></h3>
            <canvas id="rp-awin-chart" width="1060" height="200" style="max-width:100%;height:auto;"></canvas>
        </div>

        <script>
        (function(){
            var labels = <?php echo wp_json_encode( $chart['labels'] ); ?>;
            var arrivals = <?php echo wp_json_encode( $chart['arrivals'] ); ?>;
            var purchases = <?php echo wp_json_encode( $chart['purchases_awin'] ); ?>;

            var canvas = document.getElementById('rp-awin-chart');
            if (!canvas || !canvas.getContext) return;
            var ctx = canvas.getContext('2d');

            var W = canvas.width, H = canvas.height;
            var padL = 42, padR = 12, padT = 14, padB = 36;
            var innerW = W - padL - padR, innerH = H - padT - padB;

            var maxVal = Math.max.apply(null, arrivals.concat(purchases)) || 1;

            function xPos(i) { return padL + (i / (labels.length - 1 || 1)) * innerW; }
            function yPos(v) { return padT + innerH - (v / maxVal) * innerH; }

            // Grid lines.
            ctx.strokeStyle = '#e0e0e0';
            ctx.lineWidth = 1;
            for (var g = 0; g <= 4; g++) {
                var gy = padT + (g / 4) * innerH;
                ctx.beginPath(); ctx.moveTo(padL, gy); ctx.lineTo(padL + innerW, gy); ctx.stroke();
                ctx.fillStyle = '#8c8f94';
                ctx.font = '10px sans-serif';
                ctx.textAlign = 'right';
                ctx.fillText(Math.round(maxVal * (1 - g/4)), padL - 6, gy + 4);
            }

            // X labels (show every N to avoid clutter).
            var step = Math.ceil(labels.length / 12);
            ctx.fillStyle = '#8c8f94';
            ctx.font = '10px sans-serif';
            ctx.textAlign = 'center';
            for (var i = 0; i < labels.length; i += step) {
                ctx.fillText(labels[i], xPos(i), H - padB + 16);
            }

            // Series helper.
            function drawLine(data, color, lineWidth) {
                ctx.strokeStyle = color;
                ctx.lineWidth = lineWidth || 2;
                ctx.lineJoin = 'round';
                ctx.beginPath();
                for (var j = 0; j < data.length; j++) {
                    var x = xPos(j), y = yPos(data[j]);
                    if (j === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
                }
                ctx.stroke();
            }

            drawLine(arrivals,  '#2271b1', 2);
            drawLine(purchases, '#00a32a', 2);

            // Legend.
            var lx = padL + 8, ly = padT + 16;
            [[arrivals,'#2271b1','Llegadas'],[purchases,'#00a32a','Compras AWIN']].forEach(function(s, i){
                ctx.fillStyle = s[1];
                ctx.fillRect(lx + i*130, ly, 12, 12);
                ctx.fillStyle = '#1d2327';
                ctx.font = '11px sans-serif';
                ctx.textAlign = 'left';
                ctx.fillText(s[2], lx + i*130 + 16, ly + 10);
            });
        })();
        </script>

        <?php if ( ! empty( $top_days ) ) : ?>
        <!-- ── Top días por revenue ──────────────────────────────────── -->
        <div style="max-width:600px;margin-bottom:24px;">
            <h3 style="font-size:13px;margin-bottom:8px;"><?php esc_html_e( 'Top días por Revenue AWIN', 'replanta-prices' ); ?></h3>
            <table class="widefat fixed striped">
                <thead><tr>
                    <th><?php esc_html_e( 'Fecha', 'replanta-prices' ); ?></th>
                    <th><?php esc_html_e( 'Revenue AWIN', 'replanta-prices' ); ?></th>
                    <th><?php esc_html_e( 'Compras AWIN', 'replanta-prices' ); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ( $top_days as $td ) : ?>
                    <tr>
                        <td><?php echo esc_html( $td['day'] ); ?></td>
                        <td><?php echo esc_html( number_format_i18n( (float) $td['revenue_awin'], 2 ) ); ?> €</td>
                        <td><?php echo esc_html( (string) $td['purchases_awin'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <hr style="max-width:1100px;margin-bottom:20px;">
        <?php endif; // end $dash ?>

        <!-- ── Totals summary table ──────────────────────────────────── -->
        <h3 style="margin-top:0;"><?php printf( esc_html__( 'Resumen últimos %d días', 'replanta-prices' ), esc_html( (string) $period_days ) ); ?></h3>
        <p class="description"><?php esc_html_e( 'Datos recibidos por endpoint desde GTM/snippets frontend. Métricas AWIN se calculan cuando el evento llega con awc válido.', 'replanta-prices' ); ?></p>

        <table class="widefat fixed striped" style="max-width:1100px;margin-top:12px;">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Llegadas', 'replanta-prices' ); ?></th>
                    <th><?php esc_html_e( 'Llegadas únicas', 'replanta-prices' ); ?></th>
                    <th><?php esc_html_e( 'Begin checkout', 'replanta-prices' ); ?></th>
                    <th><?php esc_html_e( 'Begin checkout AWIN', 'replanta-prices' ); ?></th>
                    <th><?php esc_html_e( 'Compras', 'replanta-prices' ); ?></th>
                    <th><?php esc_html_e( 'Compras AWIN', 'replanta-prices' ); ?></th>
                    <th><?php esc_html_e( 'Ingresos AWIN', 'replanta-prices' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong><?php echo esc_html( (string) $totals['arrival_total'] ); ?></strong></td>
                    <td><strong><?php echo esc_html( (string) $totals['arrival_unique'] ); ?></strong></td>
                    <td><strong><?php echo esc_html( (string) $totals['begin_checkout_total'] ); ?></strong></td>
                    <td><strong><?php echo esc_html( (string) $totals['begin_checkout_awin'] ); ?></strong></td>
                    <td><strong><?php echo esc_html( (string) $totals['purchase_total'] ); ?></strong></td>
                    <td><strong><?php echo esc_html( (string) $totals['purchase_awin'] ); ?></strong></td>
                    <td><strong><?php echo esc_html( number_format_i18n( (float) $totals['revenue_awin'], 2 ) ); ?></strong></td>
                </tr>
            </tbody>
        </table>

        <h3 style="margin-top:20px;"><?php esc_html_e( 'Detalle diario', 'replanta-prices' ); ?></h3>
        <table class="widefat fixed striped" style="max-width:1100px;">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Fecha', 'replanta-prices' ); ?></th>
                    <th><?php esc_html_e( 'Llegadas', 'replanta-prices' ); ?></th>
                    <th><?php esc_html_e( 'Únicas', 'replanta-prices' ); ?></th>
                    <th><?php esc_html_e( 'Checkout', 'replanta-prices' ); ?></th>
                    <th><?php esc_html_e( 'Checkout AWIN', 'replanta-prices' ); ?></th>
                    <th><?php esc_html_e( 'Compras', 'replanta-prices' ); ?></th>
                    <th><?php esc_html_e( 'Compras AWIN', 'replanta-prices' ); ?></th>
                    <th><?php esc_html_e( 'Revenue AWIN', 'replanta-prices' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $rows ) ) : ?>
                    <tr><td colspan="8"><?php esc_html_e( 'Sin datos todavía.', 'replanta-prices' ); ?></td></tr>
                <?php else : foreach ( $rows as $row ) : ?>
                    <tr>
                        <td><?php echo esc_html( $row['day'] ); ?></td>
                        <td><?php echo esc_html( (string) $row['arrival_total'] ); ?></td>
                        <td><?php echo esc_html( (string) $row['arrival_unique'] ); ?></td>
                        <td><?php echo esc_html( (string) $row['begin_checkout_total'] ); ?></td>
                        <td><?php echo esc_html( (string) $row['begin_checkout_awin'] ); ?></td>
                        <td><?php echo esc_html( (string) $row['purchase_total'] ); ?></td>
                        <td><?php echo esc_html( (string) $row['purchase_awin'] ); ?></td>
                        <td><?php echo esc_html( number_format_i18n( (float) $row['revenue_awin'], 2 ) ); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>

        <h3 style="margin-top:22px;"><?php esc_html_e( 'Endpoint para GTM / scripts', 'replanta-prices' ); ?></h3>
        <p><code><?php echo esc_html( $report['endpoint'] ); ?></code></p>
        <p class="description">
            <?php esc_html_e( 'Eventos aceptados: arrival, begin_checkout, purchase. Campos recomendados: awc, pid, order_id, value, currency.', 'replanta-prices' ); ?>
        </p>
        <p class="description">
            <?php esc_html_e( 'Nota: para compras AWIN debes enviar el evento purchase en tu página de éxito/thank-you (vía GTM) incluyendo awc + order_id.', 'replanta-prices' ); ?>
        </p>

        <hr>

        <h3><?php esc_html_e( 'Estado relay S2S (servidor)', 'replanta-prices' ); ?></h3>
        <table class="widefat fixed striped" style="max-width:1000px;">
            <tbody>
                <tr>
                    <th style="width:260px;"><?php esc_html_e( 'S2S activo', 'replanta-prices' ); ?></th>
                    <td><?php echo ! empty( $s2s['enabled'] ) ? esc_html__( 'Sí', 'replanta-prices' ) : esc_html__( 'No', 'replanta-prices' ); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Merchant ID', 'replanta-prices' ); ?></th>
                    <td><?php echo esc_html( isset( $s2s['merchant_id'] ) ? (string) $s2s['merchant_id'] : '-' ); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Endpoint S2S', 'replanta-prices' ); ?></th>
                    <td><code><?php echo esc_html( isset( $s2s['endpoint'] ) ? (string) $s2s['endpoint'] : '' ); ?></code></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Cola pendiente', 'replanta-prices' ); ?></th>
                    <td><?php echo esc_html( isset( $s2s['queue_size'] ) ? (string) (int) $s2s['queue_size'] : '0' ); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Test mode', 'replanta-prices' ); ?></th>
                    <td><?php echo ! empty( $s2s['testmode'] ) ? '1' : '0'; ?></td>
                </tr>
            </tbody>
        </table>

        <p style="margin-top:12px;">
            <a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=awin&action=process_awin_s2s_queue' ), 'replanta_awin_s2s_process' ) ); ?>">
                <?php esc_html_e( 'Procesar cola S2S ahora', 'replanta-prices' ); ?>
            </a>
        </p>

        <?php if ( ! empty( $s2s['log'] ) && is_array( $s2s['log'] ) ) : ?>
            <h4 style="margin-top:16px;"><?php esc_html_e( 'Últimos envíos S2S', 'replanta-prices' ); ?></h4>
            <table class="widefat fixed striped" style="max-width:1000px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Fecha', 'replanta-prices' ); ?></th>
                        <th><?php esc_html_e( 'Estado', 'replanta-prices' ); ?></th>
                        <th><?php esc_html_e( 'Pedido', 'replanta-prices' ); ?></th>
                        <th><?php esc_html_e( 'HTTP', 'replanta-prices' ); ?></th>
                        <th><?php esc_html_e( 'Detalle', 'replanta-prices' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $s2s['log'] as $entry ) : ?>
                        <tr>
                            <td><?php echo ! empty( $entry['time'] ) ? esc_html( wp_date( 'd/m/Y H:i:s', (int) $entry['time'] ) ) : '-'; ?></td>
                            <td><?php echo esc_html( isset( $entry['status'] ) ? (string) $entry['status'] : '-' ); ?></td>
                            <td><?php echo esc_html( isset( $entry['order'] ) ? (string) $entry['order'] : '-' ); ?></td>
                            <td><?php echo esc_html( isset( $entry['http_code'] ) ? (string) (int) $entry['http_code'] : '-' ); ?></td>
                            <td><?php echo esc_html( isset( $entry['error'] ) ? (string) $entry['error'] : ( isset( $entry['parts'] ) ? (string) $entry['parts'] : '' ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Sección de Limpieza de Logs -->
        <hr style="margin-top:30px;">
        <h3><?php esc_html_e( 'Limpieza de Logs AWIN', 'replanta-prices' ); ?></h3>
        <p class="description"><?php esc_html_e( 'Herramientas para limpiar, visualizar y exportar eventos de logs.', 'replanta-prices' ); ?></p>

        <?php 
        if ( class_exists( 'Replanta_Awin_Logs_Cleanup' ) ) {
            Replanta_Awin_Logs_Cleanup::render_cleanup_section();
        }
        ?>
        <?php
    }

    /* ─── AWIN Docs Tab ────────────────────────────────────────────── */

    private static function render_awin_docs_tab() {
        $settings = get_option( 'replanta_prices_settings', array() );
        $merchant_id = isset( $settings['awin_s2s_merchant_id'] ) ? (int) $settings['awin_s2s_merchant_id'] : 125596;
        ?>
        <style>
            .awin-docs-section { background:#fff; border:1px solid #ccd0d4; border-radius:6px; padding:20px 24px; margin-bottom:24px; max-width:1100px; }
            .awin-docs-section h2 { margin-top:0; font-size:1.3em; border-bottom:2px solid #2271b1; padding-bottom:8px; color:#1d2327; }
            .awin-docs-section h3 { color:#2271b1; margin-top:18px; }
            .awin-code-block { position:relative; background:#1e1e1e !important; color:#d4d4d4 !important; padding:16px 18px; border-radius:5px; font-family:'Fira Code',Consolas,'Courier New',monospace; font-size:12.5px; line-height:1.55; overflow-x:auto; white-space:pre; margin:10px 0 16px; }
            .awin-code-block code { background:transparent !important; color:#d4d4d4 !important; padding:0 !important; margin:0 !important; font-size:inherit !important; font-family:inherit !important; display:block; white-space:pre; border:none !important; box-shadow:none !important; }
            .awin-code-block code::selection, .awin-code-block code *::selection { background:#264f78 !important; color:#fff !important; }
            .awin-copy-btn { position:absolute; top:8px; right:8px; background:#2271b1; color:#fff; border:none; padding:5px 12px; border-radius:4px; cursor:pointer; font-size:11px; z-index:2; }
            .awin-copy-btn:hover { background:#135e96; }
            .awin-copy-btn.copied { background:#00a32a; }
            .awin-docs-table { border-collapse:collapse; width:100%; margin:10px 0; }
            .awin-docs-table th, .awin-docs-table td { border:1px solid #ccd0d4; padding:8px 12px; text-align:left; }
            .awin-docs-table th { background:#f0f0f1; font-weight:600; }
            .awin-docs-table tr:nth-child(even) td { background:#f9f9f9; }
            .awin-badge { display:inline-block; padding:2px 8px; border-radius:3px; font-size:11px; font-weight:600; }
            .awin-badge-gtm { background:#4285f4; color:#fff; }
            .awin-badge-elem { background:#93003c; color:#fff; }
            .awin-badge-plugin { background:#00a32a; color:#fff; }
            .awin-badge-step { background:#dba617; color:#1d2327; }
            .awin-flow-diagram { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin:12px 0; }
            .awin-flow-box { background:#f0f6fc; border:2px solid #2271b1; border-radius:6px; padding:8px 14px; font-weight:600; font-size:13px; text-align:center; min-width:120px; }
            .awin-flow-arrow { font-size:20px; color:#2271b1; font-weight:bold; }
        </style>

        <script>
        function awinCopyCode(btn) {
            var block = btn.parentElement.querySelector('code') || btn.parentElement;
            var text = block.innerText || block.textContent;
            navigator.clipboard.writeText(text).then(function() {
                btn.textContent = '✓ Copiado';
                btn.classList.add('copied');
                setTimeout(function() { btn.textContent = 'Copiar'; btn.classList.remove('copied'); }, 2000);
            });
        }
        </script>

                <!-- ═══════════════════════════════════════════════════════════ -->
                <!-- SECCIÓN 1: ARQUITECTURA PRODUCCIÓN                        -->
                <!-- ═══════════════════════════════════════════════════════════ -->
                <div class="awin-docs-section">
                        <h2>Arquitectura AWIN en Producci&oacute;n (modo exacto)</h2>
                        <p>Merchant ID: <strong><?php echo esc_html( (string) $merchant_id ); ?></strong> &nbsp;|&nbsp;
                             Dominios: <code>replanta.net</code> (landing) → <code>clientes.replanta.net</code> (checkout Upmind)</p>

                        <div style="background:#fff4e5; border-left:4px solid #dba617; padding:12px 16px; margin:12px 0 16px; border-radius:0 4px 4px 0;">
                                <strong>Pol&iacute;tica de producci&oacute;n:</strong> conversiones AWIN por <strong>S2S controlado por plugin</strong>. No usar tags client-side de conversi&oacute;n en <code>purchase</code>.
                        </div>
                        <div style="background:#f0f6fc; border-left:4px solid #2271b1; padding:12px 16px; margin:0 0 16px; border-radius:0 4px 4px 0;">
                            <strong>Nota operativa:</strong> para pasar verificaci&oacute;n y activar el programa en red, mant&eacute;n <strong>MasterTag activo</strong> en p&aacute;ginas de landing/checkout (incluida confirmaci&oacute;n). La conversi&oacute;n sigue siendo <code>tt=ss</code> por S2S; no actives p&iacute;xeles de conversi&oacute;n client-side en <code>purchase</code>.
                        </div>

                        <h3>Etiquetas exactas que deben existir</h3>
                        <table class="awin-docs-table">
                                <thead>
                                        <tr>
                                                <th>Etiqueta</th>
                                                <th>Estado</th>
                                                <th>D&oacute;nde</th>
                                                <th>Trigger</th>
                                        </tr>
                                </thead>
                                <tbody>
                                        <tr>
                                                <td><strong>Awin - S2S Bridge</strong></td>
                                                <td><span class="awin-badge awin-badge-plugin">OBLIGATORIA</span></td>
                                                <td>GTM en <code>clientes.replanta.net</code></td>
                                                <td><code>purchase</code></td>
                                        </tr>
                                        <tr>
                                                <td><strong>Tag GTM - Set AWC Cookie</strong></td>
                                                <td><span class="awin-badge awin-badge-plugin">OBLIGATORIA</span></td>
                                                <td>GTM en ambos dominios</td>
                                                <td>All Pages (con query <code>?awc=</code>)</td>
                                        </tr>
                                        <tr>
                                                <td><strong>Awin Landing Script</strong></td>
                                                <td><span class="awin-badge awin-badge-plugin">OBLIGATORIA</span></td>
                                                <td>Plugin en <code>replanta.net</code></td>
                                                <td>Inyecci&oacute;n autom&aacute;tica</td>
                                        </tr>
                                        <tr>
                                                <td><strong>Awin - Sale Data</strong></td>
                                                <td><span class="awin-badge awin-badge-gtm">DESACTIVADA</span></td>
                                                <td>GTM</td>
                                                <td>No debe disparar en <code>purchase</code></td>
                                        </tr>
                                        <tr>
                                                <td><strong>MasterTag (dwin1.com)</strong></td>
                                            <td><span class="awin-badge awin-badge-plugin">OBLIGATORIA</span></td>
                                            <td>Plugin y/o GTM en landing + checkout</td>
                                            <td>All Pages (excepto p&aacute;ginas sensibles de pago)</td>
                                        </tr>
                                        <tr>
                                                <td><strong>Awin - Conversion Pixel</strong></td>
                                                <td><span class="awin-badge awin-badge-gtm">DESACTIVADA</span></td>
                                                <td>GTM</td>
                                                <td>No debe disparar en <code>purchase</code></td>
                                        </tr>
                                </tbody>
                        </table>

                        <h3>Flujo de producci&oacute;n</h3>
                        <div class="awin-flow-diagram">
                                <div class="awin-flow-box">1. Llegada AWIN<br><small><code>?awc=...</code></small></div>
                                <div class="awin-flow-arrow">&rarr;</div>
                                <div class="awin-flow-box">2. Cookie AWC<br><small>plugin + GTM</small></div>
                                <div class="awin-flow-arrow">&rarr;</div>
                                <div class="awin-flow-box">3. Purchase<br><small>solo S2S Bridge</small></div>
                                <div class="awin-flow-arrow">&rarr;</div>
                                <div class="awin-flow-box">4. Servidor WP<br><small>env&iacute;o AWIN <code>tt=ss</code></small></div>
                        </div>
                </div>

                <!-- ═══════════════════════════════════════════════════════════ -->
                <!-- SECCIÓN 2: TAG — S2S BRIDGE (GTM → PLUGIN)                -->
                <!-- ═══════════════════════════════════════════════════════════ -->
                <div class="awin-docs-section">
                        <h2>Tag &mdash; Awin - S2S Bridge <span class="awin-badge awin-badge-gtm">GTM</span></h2>
                        <p>Custom HTML tag en GTM. Lee la cookie <code>replanta_awin_awc</code> y env&iacute;a los datos de compra al REST API de WordPress para que el plugin procese el S2S.<br>
                             <strong>Trigger:</strong> <code>purchase</code> event (dataLayer) &nbsp;|&nbsp; <strong>Sin secuencias</strong> (independiente).</p>

                        <h3>C&oacute;digo exacto (producci&oacute;n)</h3>
                        <div class="awin-code-block">
                                <button class="awin-copy-btn" onclick="awinCopyCode(this)">Copiar</button>
<code>&lt;script&gt;
(function() {
    // Leer cookies necesarias (AWC para AWIN, ref para afiliado interno)
    var cookies = document.cookie.split(';');
    var awc = '';
    var affiliateRef = '';
    for (var i = 0; i &lt; cookies.length; i++) {
        var c = cookies[i].trim();
        if (c.indexOf('replanta_awin_awc=') === 0) {
            awc = decodeURIComponent(c.substring(18));
        }
        if (c.indexOf('replanta_aff_ref=') === 0) {
            affiliateRef = decodeURIComponent(c.substring(17));
        }
    }

    var dl = window.dataLayer || [];
    var purchase = null;
    for (var i = dl.length - 1; i &gt;= 0; i--) {
        var e = dl[i];
        if (e &amp;&amp; e.event === 'purchase' &amp;&amp; e.ecommerce) {
            var v = Number(e.ecommerce.value || e.value || 0);
            if (v &gt; 0) { purchase = e; break; }
        }
    }
    if (!purchase) return;

    var ecom = purchase.ecommerce || {};
    var payload = JSON.stringify({
        event: 'purchase',
        awc: awc,
        ref_code: affiliateRef,
        order_id: ecom.transaction_id || purchase.transaction_id || '',
        value: Number(ecom.value || purchase.value || 0),
        currency: String(ecom.currency || purchase.currency || 'EUR').toUpperCase(),
        pid: (ecom.items &amp;&amp; ecom.items.length &amp;&amp; ecom.items[0])
            ? (ecom.items[0].item_id || '') : '',
        voucher: ecom.coupon || purchase.coupon || '',
        url: window.location.href
    });

    var endpoint = '<?php echo esc_url( rest_url( 'replanta-prices/v1/awin-event' ) ); ?>';

    if (navigator.sendBeacon) {
        try {
            var blob = new Blob([payload], { type: 'text/plain' });
            if (navigator.sendBeacon(endpoint, blob)) {
                return;
            }
        } catch (e) {}
    }

    try {
        fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'text/plain' },
            body: payload,
            mode: 'no-cors',
            keepalive: true,
            credentials: 'omit'
        });
    } catch (e) {}
})();
&lt;/script&gt;</code>
                        </div>

                        <h3>Configuraci&oacute;n en GTM</h3>
                        <table class="awin-docs-table">
                                <tr><th>Campo</th><th>Valor exacto</th></tr>
                                <tr><td>Tipo de tag</td><td>Custom HTML</td></tr>
                                <tr><td>Nombre</td><td><code>Awin - S2S Bridge</code></td></tr>
                                <tr><td>Trigger</td><td>Custom Event: <code>purchase</code> en <code>clientes.replanta.net</code></td></tr>
                                <tr><td>Tag Sequencing</td><td>Ninguno</td></tr>
                        </table>
                </div>

                <!-- ═══════════════════════════════════════════════════════════ -->
                <!-- SECCIÓN 3: TAGS A DESACTIVAR EN PURCHASE                  -->
                <!-- ═══════════════════════════════════════════════════════════ -->
                <div class="awin-docs-section">
                        <h2>Tags a desactivar en <code>purchase</code> <span class="awin-badge awin-badge-gtm">GTM</span></h2>
                        <p>Para evitar atribuciones duplicadas o no deseadas, estos tags no deben disparar en compra:</p>
                        <table class="awin-docs-table">
                                <tr><th>Tag</th><th>Acci&oacute;n requerida</th></tr>
                                <tr><td><code>Awin - Sale Data</code></td><td>Pausar o quitar trigger <code>purchase</code></td></tr>
                                <tr><td><code>script awin para clientes.replanta.net</code> (MasterTag)</td><td>Pausar o quitar trigger <code>purchase</code></td></tr>
                                <tr><td><code>Awin - Conversion Pixel</code></td><td>Pausar o quitar trigger <code>purchase</code></td></tr>
                        </table>
                </div>

        <!-- ═══════════════════════════════════════════════════════════ -->
        <!-- SECCIÓN 5: AWIN LANDING SCRIPT (PLUGIN)                   -->
        <!-- ═══════════════════════════════════════════════════════════ -->
        <div class="awin-docs-section">
            <h2>Awin Landing Script <span class="awin-badge awin-badge-plugin">Plugin</span></h2>

            <?php
            $ls_enabled = ! empty( $settings['awin_landing_script'] );
            ?>
            <div style="background:<?php echo $ls_enabled ? '#edf7ed' : '#fef3f2'; ?>; border-left:4px solid <?php echo $ls_enabled ? '#00a32a' : '#d63638'; ?>; padding:12px 16px; margin-bottom:16px; border-radius:0 4px 4px 0;">
                <strong>Estado:</strong>
                <?php if ( $ls_enabled ) : ?>
                    ACTIVO — el script se inyecta automáticamente en todas las páginas vía <code>wp_head</code>.
                <?php else : ?>
                    INACTIVO — actívalo en la pestaña <a href="?page=<?php echo esc_attr( self::PAGE_SLUG ); ?>&tab=config"><strong>Configuración</strong></a> → checkbox <em>"Awin Landing Script"</em>.
                <?php endif; ?>
            </div>

            <p>Este script reemplaza al widget HTML de Elementor. Se inyecta desde el plugin en <strong>todas las páginas de replanta.net</strong>. Ya no necesitas pegar código manualmente en cada landing.</p>

            <h3>Qué hace</h3>
            <table class="awin-docs-table">
                <tr><th>Función</th><th>Descripción</th></tr>
                <tr><td><strong>Captura AWC</strong></td><td>Lee <code>?awc=</code> de la URL, cookies MasterTag, o <code>window.AWIN</code> object</td></tr>
                <tr><td><strong>Gestión <code>?sn=1</code></strong></td><td>Para redirects Awin sin AWC visible: poll MasterTag cookies + AWIN object durante 10s</td></tr>
                <tr><td><strong>Cookie compartida</strong></td><td>Guarda <code>replanta_awin_awc</code> en <code>.replanta.net</code> (90 días)</td></tr>
                <tr><td><strong>Arrival tracking</strong></td><td>Envía <code>arrival</code> al REST API del plugin vía <code>sendBeacon</code></td></tr>
                <tr><td><strong>Begin checkout</strong></td><td>Detecta clicks en <code>a.plan-card-cta</code> → envía <code>begin_checkout</code> + push a dataLayer</td></tr>
                <tr><td><strong>MasterTag fallback</strong></td><td>Si <code>?sn=1</code> o <code>?awc=</code> y GTM no cargó el MasterTag, lo carga como fallback</td></tr>
                <tr><td><strong>Consent (Complianz)</strong></td><td>Escucha <code>cmplz_fire_categories</code> y <code>consent_update</code> → recarga MasterTag para resolver AWC</td></tr>
                <tr><td><strong>GA4 + Meta Pixel</strong></td><td>Push <code>begin_checkout</code> a <code>gtag</code> y <code>fbq</code> si están presentes</td></tr>
                <tr><td><strong>Purchase helper</strong></td><td>Expone <code>window.replantaTrackAwinPurchase(data)</code> para thank-you pages</td></tr>
            </table>

            <h3>PIDs configurados</h3>
            <table class="awin-docs-table">
                <tr><th>PID (Upmind)</th><th>Plan</th></tr>
                <tr><td><code>6d530876-8251-d485-d80a-147e390921e6</code></td><td>sauce</td></tr>
                <tr><td><code>e2e071d9-31d5-e460-555a-646028758396</code></td><td>cedro</td></tr>
                <tr><td><code>280d1639-e237-d439-6dea-54610589e572</code></td><td>roble</td></tr>
                <tr><td><code>2e071d93-1d5e-468e-935c-646028758396</code></td><td>sapwoo-setup</td></tr>
                <tr><td><code>61e50989-73d2-4753-988c-e45e610832d7</code></td><td>sapwoo-monthly</td></tr>
            </table>

            <p><strong>Nota:</strong> Si el checkbox "Awin Landing Script" está activo, puedes <strong>eliminar</strong> los widgets HTML de Elementor que contenían el script anterior. El plugin lo inyecta globalmente.</p>
        </div>

        <!-- ═══════════════════════════════════════════════════════════ -->
        <!-- SECCIÓN 6: TAG SET AWC COOKIE (GTM)                      -->
        <!-- ═══════════════════════════════════════════════════════════ -->
        <div class="awin-docs-section">
            <h2>Tag GTM — Set AWC Cookie <span class="awin-badge awin-badge-gtm">GTM</span></h2>
            <p>Captura <code>?awc=</code> de la URL y guarda la cookie compartida. Se activa en <strong>ambos dominios</strong>.</p>

            <h3>Código completo</h3>
            <div class="awin-code-block">
                <button class="awin-copy-btn" onclick="awinCopyCode(this)">Copiar</button>
<code>&lt;script&gt;
(function(){
    var params = new URLSearchParams(window.location.search);
    var awc = params.get('awc');
    if (!awc) return;
    var expires = new Date(Date.now() + 90*24*60*60*1000).toUTCString();
    document.cookie = 'replanta_awin_awc=' + encodeURIComponent(awc)
        + '; expires=' + expires
        + '; path=/; domain=.replanta.net; SameSite=Lax; Secure';
    console.log('[Awin Cookie] AWC guardado:', awc);
})();
&lt;/script&gt;</code>
            </div>

            <h3>Configuración en GTM</h3>
            <table class="awin-docs-table">
                <tr><th>Campo</th><th>Valor</th></tr>
                <tr><td>Nombre</td><td><code>Awin - Set AWC Cookie</code></td></tr>
                <tr><td>Trigger</td><td>All Pages (ambos dominios)</td></tr>
            </table>
        </div>

        <!-- ═══════════════════════════════════════════════════════════ -->
        <!-- SECCIÓN 7: MAPA DE COMISIONES                            -->
        <!-- ═══════════════════════════════════════════════════════════ -->
        <div class="awin-docs-section">
            <h2>Mapa de Comisiones</h2>
            <p>Define qué grupo de comisión aplica a cada producto. Se configura en la pestaña <strong>Configuración</strong> → campo "Commission Map".</p>

            <table class="awin-docs-table">
                <thead>
                    <tr>
                        <th>Grupo</th>
                        <th>Comisión</th>
                        <th>Productos incluidos</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>DEFAULT</strong></td>
                        <td>12%</td>
                        <td>Todos los productos que NO estén en HOSTING</td>
                    </tr>
                    <tr>
                        <td><strong>HOSTING</strong></td>
                        <td>15%</td>
                        <td>Productos de hosting (PIDs definidos en Sale Data tag)</td>
                    </tr>
                </tbody>
            </table>

            <h3>Formato del campo Commission Map</h3>
            <div class="awin-code-block">
                <button class="awin-copy-btn" onclick="awinCopyCode(this)">Copiar</button>
<code>prod_xxx1:HOSTING
prod_xxx2:HOSTING
*:DEFAULT</code>
            </div>
            <p><small>Una línea por producto. El <code>*</code> es el fallback para cualquier producto no listado.</small></p>
        </div>

        <!-- ═══════════════════════════════════════════════════════════ -->
        <!-- SECCIÓN 8: S2S (SERVER-TO-SERVER)                        -->
        <!-- ═══════════════════════════════════════════════════════════ -->
        <div class="awin-docs-section">
            <h2>Server-to-Server (S2S) — Plugin</h2>
            <p>El plugin envía conversiones server-side a Awin vía WP Cron. Esto es independiente de GTM.</p>

            <h3>Cómo funciona</h3>
            <ol>
                <li>Script de Elementor o GTM envía <code>purchase</code> al REST endpoint: <code>/wp-json/replanta-prices/v1/awin-event</code></li>
                <li>El plugin encola la compra en <code>wp_options</code> → <code>replanta_awin_s2s_queue</code></li>
                <li>WP Cron (<code>replanta_process_s2s_queue</code>) ejecuta cada 5 minutos</li>
                <li>Envía <code>wp_remote_get</code> a <code>https://www.awin1.com/sread.php?tt=ss&amp;...</code></li>
                <li>Si falla: reintentos con backoff exponencial (máx. 5 intentos)</li>
            </ol>

            <h3>Ajustes requeridos (pestaña Configuración)</h3>
            <table class="awin-docs-table">
                <tr><th>Campo</th><th>Valor</th></tr>
                <tr><td>S2S Habilitado</td><td><code>Sí</code></td></tr>
                <tr><td>Merchant ID</td><td><code><?php echo esc_html( (string) $merchant_id ); ?></code></td></tr>
                <tr><td>Channel</td><td><code>aw</code></td></tr>
                <tr><td>Endpoint</td><td><code>https://www.awin1.com/sread.php</code></td></tr>
                <tr><td>Test Mode</td><td><code>0</code> (producción) o <code>1</code> (pruebas)</td></tr>
            </table>

            <h3>Ejemplo de request S2S</h3>
            <div class="awin-code-block">
                <button class="awin-copy-btn" onclick="awinCopyCode(this)">Copiar</button>
<code>https://www.awin1.com/sread.php?tt=ss&amp;tv=2&amp;merchant=<?php echo (int) $merchant_id; ?>&amp;amount=29.99&amp;ch=aw&amp;cr=EUR&amp;parts=DEFAULT:29.99&amp;ref=PRF-000157&amp;vc=&amp;testmode=0&amp;cks=AWC_VALUE</code>
            </div>
            <p><small>Nota: S2S usa <code>tt=ss</code> y <code>cks</code> (cookie server). Client-side usa <code>tt=ns</code> y <code>vc</code>.</small></p>
        </div>

        <!-- ═══════════════════════════════════════════════════════════ -->
        <!-- SECCIÓN 9: TESTING / DEBUGGING                           -->
        <!-- ═══════════════════════════════════════════════════════════ -->
        <div class="awin-docs-section">
            <h2>Testing y Debugging</h2>

            <h3>1. Verificar cookie</h3>
            <p>En la consola del navegador en <code>clientes.replanta.net</code>:</p>
            <div class="awin-code-block">
                <button class="awin-copy-btn" onclick="awinCopyCode(this)">Copiar</button>
<code>document.cookie.split(';').find(c =&gt; c.includes('replanta_awin_awc'))</code>
            </div>

            <h3>2. Verificar AWIN.Tracking.Sale</h3>
            <p>Tras una compra de test, en la consola:</p>
            <div class="awin-code-block">
                <button class="awin-copy-btn" onclick="awinCopyCode(this)">Copiar</button>
<code>console.log(window.AWIN &amp;&amp; window.AWIN.Tracking &amp;&amp; window.AWIN.Tracking.Sale)</code>
            </div>

            <h3>3. Verificar en Awin Tracking Diagnosis</h3>
            <table class="awin-docs-table">
                <tr><th>Check</th><th>Esperado</th><th>Si falla</th></tr>
                <tr>
                    <td>MasterTag</td>
                    <td>Verde</td>
                    <td>Sale Data no se ejecuta antes que MasterTag. Revisar Tag Sequencing.</td>
                </tr>
                <tr>
                    <td>Server-to-server</td>
                    <td>Verde</td>
                    <td>S2S no habilitado en plugin, o WP Cron no funciona.</td>
                </tr>
                <tr>
                    <td>Fallback pixel</td>
                    <td>Verde</td>
                    <td>Conversion Pixel no dispara. Revisar trigger <code>purchase</code>.</td>
                </tr>
            </table>

            <h3>4. URL de test con AWC</h3>
            <div class="awin-code-block">
                <button class="awin-copy-btn" onclick="awinCopyCode(this)">Copiar</button>
<code>https://replanta.net/hosting/?awc=XXXXXX</code>
            </div>
            <p><small>Reemplaza <code>XXXXXX</code> con un AWC real de Awin (o genera uno de prueba desde tu cuenta de publisher).</small></p>

            <h3>5. Logs del plugin</h3>
            <p>Los logs S2S se pueden ver en la pestaña <strong>AWIN Analytics</strong> → sección "Últimos envíos S2S".<br>
               También se puede procesar la cola manualmente desde ahí con el botón "Procesar cola S2S ahora".</p>
        </div>

        <!-- ═══════════════════════════════════════════════════════════ -->
        <!-- SECCIÓN 10: REFERENCIA RÁPIDA                            -->
        <!-- ═══════════════════════════════════════════════════════════ -->
        <div class="awin-docs-section">
            <h2>Referencia Rápida</h2>
            <table class="awin-docs-table">
                <tr><th>Parámetro</th><th>Valor</th></tr>
                <tr><td>Merchant ID</td><td><code><?php echo esc_html( (string) $merchant_id ); ?></code></td></tr>
                <tr><td>MasterTag URL</td><td><code>https://www.dwin1.com/<?php echo esc_html( (string) $merchant_id ); ?>.js</code></td></tr>
                <tr><td>Client-side Endpoint</td><td><code>https://www.awin1.com/sread.img</code> (tt=ns)</td></tr>
                <tr><td>S2S Endpoint</td><td><code>https://www.awin1.com/sread.php</code> (tt=ss)</td></tr>
                <tr><td>Cookie</td><td><code>replanta_awin_awc</code> / domain=<code>.replanta.net</code> / 90 días</td></tr>
                <tr><td>REST API</td><td><code>/wp-json/replanta-prices/v1/awin-event</code></td></tr>
                <tr><td>GTM Container</td><td><code>GTM-M6WJKQ7K</code></td></tr>
                <tr><td>Comisión DEFAULT</td><td>12%</td></tr>
                <tr><td>Comisión HOSTING</td><td>15%</td></tr>
                <tr><td>Complianz</td><td>MasterTag se recarga tras aceptar cookies (categoría <code>statistics</code>)</td></tr>
            </table>
        </div>

        <?php
    }

    /* ─── Feeds Tab ────────────────────────────────────────────────── */

    private static function render_feeds_tab() {
        if ( ! class_exists( 'Replanta_Prices_Product_Feed' ) ) {
            echo '<p>' . esc_html__( 'Módulo Feed no disponible.', 'replanta-prices' ) . '</p>';
            return;
        }

        settings_errors( 'replanta_prices_feeds' );

        $preview = Replanta_Prices_Product_Feed::get_feed_preview( 'EUR', 'monthly' );

        $google_url = $preview['google_url'];
        $meta_url   = $preview['meta_url'];
        $count      = $preview['count'];
        $items      = $preview['items'];
        ?>
        <h2><?php esc_html_e( 'Feeds de productos', 'replanta-prices' ); ?></h2>
        <p class="description">
            <?php esc_html_e( 'Exporta el catálogo de productos para Google Merchant Center y Meta/Instagram. Los feeds se cachean 30 minutos. Usa el botón para regenerar.', 'replanta-prices' ); ?>
        </p>

        <!-- ── Feed URLs ─────────────────────────────────────────────── -->
        <div style="background:#fff;border:1px solid #ccd0d4;border-radius:8px;padding:20px 24px;max-width:860px;margin-bottom:24px;">
            <h3 style="margin-top:0;"><?php esc_html_e( 'URLs de los feeds', 'replanta-prices' ); ?></h3>

            <table class="widefat fixed" style="margin-bottom:16px;">
                <thead>
                    <tr>
                        <th style="width:140px;"><?php esc_html_e( 'Canal', 'replanta-prices' ); ?></th>
                        <th><?php esc_html_e( 'URL del feed', 'replanta-prices' ); ?></th>
                        <th style="width:80px;"><?php esc_html_e( 'Tipo', 'replanta-prices' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Google Merchant</strong></td>
                        <td><code style="word-break:break-all;"><?php echo esc_html( $google_url ); ?></code></td>
                        <td>XML RSS 2.0</td>
                    </tr>
                    <tr>
                        <td><strong>Meta / Instagram</strong></td>
                        <td><code style="word-break:break-all;"><?php echo esc_html( $meta_url ); ?></code></td>
                        <td>CSV</td>
                    </tr>
                </tbody>
            </table>

            <p class="description" style="margin-bottom:12px;">
                <?php esc_html_e( 'Parámetros opcionales: ?currency=USD&period=annual (monthly por defecto, EUR por defecto).', 'replanta-prices' ); ?>
            </p>

            <!-- Regenerate cache button -->
            <form method="post" action="">
                <?php wp_nonce_field( 'replanta_feed_regenerate', 'replanta_feed_nonce' ); ?>
                <input type="hidden" name="replanta_feed_action" value="regenerate_feeds">
                <button type="submit" class="button button-secondary">
                    <?php esc_html_e( '↺ Regenerar caché de feeds', 'replanta-prices' ); ?>
                </button>
                <span style="margin-left:10px;" class="description">
                    <?php esc_html_e( 'Borra el caché y fuerza la regeneración en la próxima petición.', 'replanta-prices' ); ?>
                </span>
            </form>
        </div>

        <!-- ── Google Shopping-style preview ────────────────────────── -->
        <div style="background:#fff;border:1px solid #ccd0d4;border-radius:8px;padding:20px 24px;max-width:1100px;">
            <h3 style="margin-top:0;font-size:15px;color:#3c4043;">
                <?php printf( esc_html__( 'Vista previa Google Shopping — %d productos (EUR, mensual)', 'replanta-prices' ), $count ); ?>
            </h3>
            <?php if ( empty( $items ) ) : ?>
                <p style="color:#666;"><?php esc_html_e( 'Sin productos en el catálogo.', 'replanta-prices' ); ?></p>
            <?php else : ?>
            <div style="display:flex;flex-wrap:wrap;gap:16px;">
                <?php foreach ( $items as $item ) :
                    $display_url = preg_replace( '#^https?://#', '', rtrim( $item['link'], '/' ) );
                    $desc_short  = mb_substr( $item['description'], 0, 120 );
                    if ( mb_strlen( $item['description'] ) > 120 ) {
                        $desc_short .= '…';
                    }
                ?>
                <div style="width:220px;border:1px solid #dadce0;border-radius:8px;overflow:hidden;font-family:Arial,sans-serif;box-shadow:0 1px 3px rgba(60,64,67,.1);">
                    <!-- Product image -->
                    <div style="height:160px;background:#f1f3f4;display:flex;align-items:center;justify-content:center;overflow:hidden;">
                        <?php if ( ! empty( $item['image_link'] ) ) : ?>
                            <img src="<?php echo esc_url( $item['image_link'] ); ?>"
                                 alt="<?php echo esc_attr( $item['title'] ); ?>"
                                 style="max-width:100%;max-height:160px;object-fit:contain;"
                                 onerror="this.parentNode.innerHTML='<span style=\'color:#aaa;font-size:12px;\'>Sin imagen</span>'">
                        <?php else : ?>
                            <span style="color:#aaa;font-size:12px;"><?php esc_html_e( 'Sin imagen', 'replanta-prices' ); ?></span>
                        <?php endif; ?>
                    </div>
                    <!-- Card body -->
                    <div style="padding:10px 12px;">
                        <!-- Merchant domain (green, Google style) -->
                        <div style="font-size:11px;color:#137333;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:2px;">
                            <?php echo esc_html( $display_url ); ?>
                        </div>
                        <!-- Title (Google blue link) -->
                        <a href="<?php echo esc_url( $item['link'] ); ?>" target="_blank" rel="noopener noreferrer"
                           style="display:block;font-size:13px;font-weight:600;color:#1a0dab;text-decoration:none;line-height:1.3;margin-bottom:4px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">
                            <?php echo esc_html( $item['title'] ); ?>
                        </a>
                        <!-- Price (bold, prominent) -->
                        <div style="font-size:16px;font-weight:700;color:#202124;margin-bottom:4px;">
                            <?php echo esc_html( $item['price'] ); ?>/mes
                        </div>
                        <!-- Description excerpt -->
                        <div style="font-size:11px;color:#5f6368;line-height:1.4;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">
                            <?php echo esc_html( $desc_short ); ?>
                        </div>
                        <!-- Brand pill -->
                        <div style="margin-top:6px;">
                            <span style="display:inline-block;background:#f1f3f4;border-radius:4px;padding:2px 6px;font-size:10px;color:#5f6368;">
                                <?php echo esc_html( $item['brand'] ); ?>
                            </span>
                            <?php if ( $item['featured'] ) : ?>
                                <span style="display:inline-block;background:#fef9c3;border:1px solid #fde68a;border-radius:4px;padding:2px 6px;font-size:10px;color:#92400e;margin-left:4px;">
                                    Destacado
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
