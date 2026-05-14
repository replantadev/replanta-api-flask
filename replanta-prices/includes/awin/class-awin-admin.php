<?php
/**
 * Awin Admin Page - Dashboard and settings for Awin tracking.
 *
 * @package Replanta_Prices
 * @subpackage Awin
 */

defined( 'ABSPATH' ) || exit;

class Replanta_Awin_Admin {

    /** @var string Page slug */
    const PAGE_SLUG = 'replanta-awin';

    /**
     * Initialize hooks.
     */
    public static function init() {
        if ( ! is_admin() ) {
            return;
        }

        add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
    }

    /**
     * Add admin menu page.
     */
    public static function add_menu() {
        add_submenu_page(
            'options-general.php',
            __( 'Awin Tracking', 'replanta-prices' ),
            __( 'Awin Tracking', 'replanta-prices' ),
            'manage_options',
            self::PAGE_SLUG,
            array( __CLASS__, 'render_page' )
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook
     */
    public static function enqueue_assets( $hook ) {
        if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'replanta-awin-admin',
            REPLANTA_PRICES_URL . 'assets/css/awin-admin.css',
            array(),
            REPLANTA_PRICES_VERSION
        );
    }

    /**
     * Handle admin actions (form submissions).
     */
    public static function handle_actions() {
        if ( ! isset( $_POST['replanta_awin_action'] ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $action = sanitize_key( $_POST['replanta_awin_action'] );

        switch ( $action ) {
            case 'save_settings':
                self::handle_save_settings();
                break;
            case 'cleanup_logs':
                self::handle_cleanup_logs();
                break;
            case 'generate_secret':
                self::handle_generate_secret();
                break;
            case 'test_s2s':
                self::handle_test_s2s();
                break;
            case 'process_s2s':
                self::handle_process_s2s();
                break;
        }
    }

    /**
     * Handle settings save.
     */
    private static function handle_save_settings() {
        if ( ! check_admin_referer( 'replanta_awin_settings' ) ) {
            return;
        }

        $settings = array(
            'enabled'            => ! empty( $_POST['awin_enabled'] ),
            'advertiser_id'      => preg_replace( '/[^0-9]/', '', $_POST['advertiser_id'] ?? '' ),
            'inject_mastertag'   => ! empty( $_POST['inject_mastertag'] ),
            'cookie_name'        => sanitize_key( $_POST['cookie_name'] ?? 'replanta_awin_awc' ),
            'cookie_days'        => absint( $_POST['cookie_days'] ?? 90 ),
            'target_domain'      => sanitize_text_field( $_POST['target_domain'] ?? 'clientes.replanta.net' ),
            'webhook_secret'     => sanitize_text_field( $_POST['webhook_secret'] ?? '' ),
            'js_fallback'        => ! empty( $_POST['js_fallback'] ),
            'detailed_logs'      => ! empty( $_POST['detailed_logs'] ),
            'log_retention_days' => absint( $_POST['log_retention_days'] ?? 90 ),
        );

        Replanta_Awin_Cookie::save_settings( $settings );

        add_settings_error( 'replanta_awin', 'settings_saved',
            __( 'Configuración de Awin guardada.', 'replanta-prices' ), 'success' );
    }

    /**
     * Handle log cleanup.
     */
    private static function handle_cleanup_logs() {
        if ( ! check_admin_referer( 'replanta_awin_cleanup' ) ) {
            return;
        }

        $deleted = Replanta_Awin_Logger::cleanup_old_events();

        add_settings_error( 'replanta_awin', 'logs_cleaned',
            sprintf( __( 'Limpieza completada. %d eventos eliminados.', 'replanta-prices' ), $deleted ),
            'success' );
    }

    /**
     * Handle secret generation.
     */
    private static function handle_generate_secret() {
        if ( ! check_admin_referer( 'replanta_awin_generate_secret' ) ) {
            return;
        }

        $settings = Replanta_Awin_Cookie::get_settings();
        $settings['webhook_secret'] = Replanta_Awin_Webhook::generate_secret();
        Replanta_Awin_Cookie::save_settings( $settings );

        add_settings_error( 'replanta_awin', 'secret_generated',
            __( 'Nuevo secret generado.', 'replanta-prices' ), 'success' );
    }

    /**
     * Handle S2S connection test.
     */
    private static function handle_test_s2s() {
        if ( ! check_admin_referer( 'replanta_awin_test_s2s' ) ) {
            return;
        }

        $result = Replanta_Awin_S2S::test_connection();

        if ( $result['success'] ) {
            add_settings_error( 'replanta_awin', 's2s_test_ok',
                $result['message'], 'success' );
        } else {
            add_settings_error( 'replanta_awin', 's2s_test_error',
                $result['message'], 'error' );
        }
    }

    /**
     * Handle manual S2S processing.
     */
    private static function handle_process_s2s() {
        if ( ! check_admin_referer( 'replanta_awin_process_s2s' ) ) {
            return;
        }

        $results = Replanta_Awin_S2S::process_pending_conversions( 50 );

        $message = sprintf(
            __( 'S2S procesado: %d procesadas, %d enviadas OK, %d fallidas, %d omitidas.', 'replanta-prices' ),
            $results['processed'],
            $results['success'],
            $results['failed'],
            $results['skipped']
        );

        $type = $results['failed'] > 0 ? 'warning' : 'success';
        add_settings_error( 'replanta_awin', 's2s_processed', $message, $type );
    }

    /**
     * Render admin page.
     */
    public static function render_page() {
        $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';

        ?>
        <div class="wrap replanta-awin-wrap">
            <h1><?php esc_html_e( 'Awin Tracking', 'replanta-prices' ); ?></h1>

            <?php settings_errors( 'replanta_awin' ); ?>

            <nav class="nav-tab-wrapper">
                <a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=dashboard' ) ); ?>"
                   class="nav-tab <?php echo 'dashboard' === $tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Dashboard', 'replanta-prices' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=events' ) ); ?>"
                   class="nav-tab <?php echo 'events' === $tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Eventos', 'replanta-prices' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=settings' ) ); ?>"
                   class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Ajustes', 'replanta-prices' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=tools' ) ); ?>"
                   class="nav-tab <?php echo 'tools' === $tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Herramientas', 'replanta-prices' ); ?>
                </a>
            </nav>

            <div class="tab-content">
                <?php
                switch ( $tab ) {
                    case 'events':
                        self::render_events_tab();
                        break;
                    case 'settings':
                        self::render_settings_tab();
                        break;
                    case 'tools':
                        self::render_tools_tab();
                        break;
                    default:
                        self::render_dashboard_tab();
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render dashboard tab.
     */
    private static function render_dashboard_tab() {
        $settings = Replanta_Awin_Cookie::get_settings();
        $stats    = Replanta_Awin_Logger::get_stats();
        $has_awc  = Replanta_Awin_Cookie::has_awc();

        ?>
        <div class="awin-dashboard">
            <!-- Status Cards -->
            <div class="status-grid">
                <div class="status-card <?php echo $settings['enabled'] ? 'status-ok' : 'status-warn'; ?>">
                    <span class="status-icon dashicons <?php echo $settings['enabled'] ? 'dashicons-yes-alt' : 'dashicons-dismiss'; ?>"></span>
                    <div class="status-content">
                        <strong><?php esc_html_e( 'Tracking Awin', 'replanta-prices' ); ?></strong>
                        <span><?php echo $settings['enabled'] ? esc_html__( 'Activo', 'replanta-prices' ) : esc_html__( 'Desactivado', 'replanta-prices' ); ?></span>
                    </div>
                </div>

                <div class="status-card status-info">
                    <span class="status-icon dashicons dashicons-admin-network"></span>
                    <div class="status-content">
                        <strong><?php esc_html_e( 'Cookie AWC', 'replanta-prices' ); ?></strong>
                        <span><?php echo $has_awc ? esc_html__( 'Detectada en tu sesión', 'replanta-prices' ) : esc_html__( 'No detectada', 'replanta-prices' ); ?></span>
                    </div>
                </div>

                <div class="status-card <?php echo ! empty( $settings['webhook_secret'] ) ? 'status-ok' : 'status-warn'; ?>">
                    <span class="status-icon dashicons <?php echo ! empty( $settings['webhook_secret'] ) ? 'dashicons-lock' : 'dashicons-unlock'; ?>"></span>
                    <div class="status-content">
                        <strong><?php esc_html_e( 'Webhook Secret', 'replanta-prices' ); ?></strong>
                        <span><?php echo ! empty( $settings['webhook_secret'] ) ? esc_html__( 'Configurado', 'replanta-prices' ) : esc_html__( 'Sin configurar', 'replanta-prices' ); ?></span>
                    </div>
                </div>

                <?php $has_mastertag = ! empty( $settings['advertiser_id'] ); ?>
                <div class="status-card <?php echo $has_mastertag ? 'status-ok' : 'status-warn'; ?>">
                    <span class="status-icon dashicons <?php echo $has_mastertag ? 'dashicons-visibility' : 'dashicons-hidden'; ?>"></span>
                    <div class="status-content">
                        <strong><?php esc_html_e( 'MasterTag', 'replanta-prices' ); ?></strong>
                        <span>
                            <?php if ( $has_mastertag ) : ?>
                                ID <?php echo esc_html( $settings['advertiser_id'] ); ?>
                                <?php echo $settings['inject_mastertag'] ? '(auto)' : '(manual)'; ?>
                            <?php else : ?>
                                <?php esc_html_e( 'Sin configurar', 'replanta-prices' ); ?>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <div class="status-card status-info">
                    <span class="status-icon dashicons dashicons-rest-api"></span>
                    <div class="status-content">
                        <strong><?php esc_html_e( 'Endpoint Webhook', 'replanta-prices' ); ?></strong>
                        <code style="font-size:10px;word-break:break-all;"><?php echo esc_html( Replanta_Awin_Webhook::get_webhook_url() ); ?></code>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <h2><?php esc_html_e( 'Estadísticas', 'replanta-prices' ); ?></h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo esc_html( number_format_i18n( $stats['total_awc_captures'] ) ); ?></div>
                    <div class="stat-label"><?php esc_html_e( 'AWC Capturados', 'replanta-prices' ); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo esc_html( number_format_i18n( $stats['total_url_clicks'] ) ); ?></div>
                    <div class="stat-label"><?php esc_html_e( 'Clics con AWC', 'replanta-prices' ); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo esc_html( number_format_i18n( $stats['total_webhooks'] ) ); ?></div>
                    <div class="stat-label"><?php esc_html_e( 'Webhooks Recibidos', 'replanta-prices' ); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo esc_html( number_format_i18n( $stats['webhooks_success'] ) ); ?></div>
                    <div class="stat-label"><?php esc_html_e( 'Webhooks OK', 'replanta-prices' ); ?></div>
                </div>
                <div class="stat-card <?php echo $stats['webhooks_error'] > 0 ? 'stat-error' : ''; ?>">
                    <div class="stat-value"><?php echo esc_html( number_format_i18n( $stats['webhooks_error'] ) ); ?></div>
                    <div class="stat-label"><?php esc_html_e( 'Webhooks Error', 'replanta-prices' ); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo esc_html( number_format_i18n( $stats['conversions_pending'] ) ); ?></div>
                    <div class="stat-label"><?php esc_html_e( 'Conversiones Pendientes', 'replanta-prices' ); ?></div>
                </div>
                <div class="stat-card stat-success">
                    <div class="stat-value"><?php echo esc_html( number_format_i18n( $stats['conversions_sent'] ) ); ?></div>
                    <div class="stat-label"><?php esc_html_e( 'S2S Enviadas', 'replanta-prices' ); ?></div>
                </div>
                <div class="stat-card <?php echo ( $stats['s2s_errors'] ?? 0 ) > 0 ? 'stat-error' : ''; ?>">
                    <div class="stat-value"><?php echo esc_html( number_format_i18n( $stats['s2s_errors'] ?? 0 ) ); ?></div>
                    <div class="stat-label"><?php esc_html_e( 'S2S Errores', 'replanta-prices' ); ?></div>
                </div>
                <div class="stat-card stat-muted">
                    <div class="stat-value"><?php echo esc_html( number_format_i18n( $stats['conversions_skipped'] ?? 0 ) ); ?></div>
                    <div class="stat-label"><?php esc_html_e( 'Sin AWC', 'replanta-prices' ); ?></div>
                </div>
                <div class="stat-card stat-muted">
                    <div class="stat-value"><?php echo esc_html( number_format_i18n( $stats['recurring_ignored'] ?? 0 ) ); ?></div>
                    <div class="stat-label"><?php esc_html_e( 'Recurrentes (ignorados)', 'replanta-prices' ); ?></div>
                </div>
                <div class="stat-card stat-muted">
                    <div class="stat-value"><?php echo esc_html( number_format_i18n( $stats['duplicate_customers'] ?? 0 ) ); ?></div>
                    <div class="stat-label"><?php esc_html_e( 'Duplicados (ignorados)', 'replanta-prices' ); ?></div>
                </div>
            </div>

            <!-- Last Events -->
            <div class="last-events">
                <p>
                    <strong><?php esc_html_e( 'Último evento:', 'replanta-prices' ); ?></strong>
                    <?php echo $stats['last_event'] ? esc_html( $stats['last_event'] ) : esc_html__( 'Ninguno', 'replanta-prices' ); ?>
                </p>
                <p>
                    <strong><?php esc_html_e( 'Último webhook:', 'replanta-prices' ); ?></strong>
                    <?php echo $stats['last_webhook'] ? esc_html( $stats['last_webhook'] ) : esc_html__( 'Ninguno', 'replanta-prices' ); ?>
                </p>
            </div>

            <!-- MasterTag Status -->
            <?php 
            $has_advertiser_id = ! empty( $settings['advertiser_id'] );
            $mastertag_injected = ! empty( $settings['inject_mastertag'] );
            ?>
            <?php if ( $has_advertiser_id ) : ?>
                <div class="notice notice-success inline">
                    <p>
                        <span class="dashicons dashicons-yes-alt" style="color:#00a32a;"></span>
                        <strong><?php esc_html_e( 'MasterTag Awin:', 'replanta-prices' ); ?></strong>
                        <?php 
                        printf(
                            esc_html__( 'Advertiser ID %s configurado.', 'replanta-prices' ),
                            '<code>' . esc_html( $settings['advertiser_id'] ) . '</code>'
                        );
                        ?>
                        <?php if ( $mastertag_injected ) : ?>
                            <?php esc_html_e( 'El script se inyecta automáticamente en el footer.', 'replanta-prices' ); ?>
                        <?php else : ?>
                            <?php esc_html_e( 'Asegúrate de tener el MasterTag en GTM o manualmente.', 'replanta-prices' ); ?>
                        <?php endif; ?>
                    </p>
                </div>
            <?php else : ?>
                <div class="notice notice-warning inline">
                    <p>
                        <span class="dashicons dashicons-warning" style="color:#dba617;"></span>
                        <strong><?php esc_html_e( 'MasterTag Awin:', 'replanta-prices' ); ?></strong>
                        <?php esc_html_e( 'Configura tu Advertiser ID en Ajustes para habilitar el tracking completo.', 'replanta-prices' ); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render events tab.
     */
    private static function render_events_tab() {
        $page     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $per_page = 20;
        $offset   = ( $page - 1 ) * $per_page;

        $filter_type = isset( $_GET['event_type'] ) ? sanitize_key( $_GET['event_type'] ) : '';
        
        $args = array(
            'limit'  => $per_page,
            'offset' => $offset,
        );
        
        if ( $filter_type ) {
            $args['event_type'] = $filter_type;
        }

        $events       = Replanta_Awin_Logger::get_events( $args );
        $total_events = Replanta_Awin_Logger::count_events( $filter_type ? array( 'event_type' => $filter_type ) : array() );
        $total_pages  = ceil( $total_events / $per_page );

        ?>
        <div class="awin-events">
            <!-- Filters -->
            <div class="tablenav top">
                <div class="alignleft actions">
                    <select name="event_type_filter" id="event_type_filter">
                        <option value=""><?php esc_html_e( 'Todos los tipos', 'replanta-prices' ); ?></option>
                        <optgroup label="<?php esc_attr_e( 'Tracking', 'replanta-prices' ); ?>">
                            <option value="awc_captured" <?php selected( $filter_type, 'awc_captured' ); ?>><?php esc_html_e( 'AWC Capturado', 'replanta-prices' ); ?></option>
                            <option value="url_modified" <?php selected( $filter_type, 'url_modified' ); ?>><?php esc_html_e( 'URL Modificada', 'replanta-prices' ); ?></option>
                        </optgroup>
                        <optgroup label="<?php esc_attr_e( 'Webhooks', 'replanta-prices' ); ?>">
                            <option value="webhook_received" <?php selected( $filter_type, 'webhook_received' ); ?>><?php esc_html_e( 'Webhook Recibido', 'replanta-prices' ); ?></option>
                            <option value="webhook_error" <?php selected( $filter_type, 'webhook_error' ); ?>><?php esc_html_e( 'Webhook Error', 'replanta-prices' ); ?></option>
                            <option value="webhook_ignored_recurring" <?php selected( $filter_type, 'webhook_ignored_recurring' ); ?>><?php esc_html_e( 'Ignorado (Recurrente)', 'replanta-prices' ); ?></option>
                        </optgroup>
                        <optgroup label="<?php esc_attr_e( 'Conversiones', 'replanta-prices' ); ?>">
                            <option value="conversion_ready" <?php selected( $filter_type, 'conversion_ready' ); ?>><?php esc_html_e( 'Conversion Lista', 'replanta-prices' ); ?></option>
                            <option value="s2s_sent" <?php selected( $filter_type, 's2s_sent' ); ?>><?php esc_html_e( 'S2S Enviada OK', 'replanta-prices' ); ?></option>
                            <option value="s2s_error" <?php selected( $filter_type, 's2s_error' ); ?>><?php esc_html_e( 'S2S Error', 'replanta-prices' ); ?></option>
                        </optgroup>
                        <optgroup label="<?php esc_attr_e( 'No Reportadas', 'replanta-prices' ); ?>">
                            <option value="conversion_not_attributed" <?php selected( $filter_type, 'conversion_not_attributed' ); ?>><?php esc_html_e( 'Sin AWC', 'replanta-prices' ); ?></option>
                            <option value="conversion_duplicate_customer" <?php selected( $filter_type, 'conversion_duplicate_customer' ); ?>><?php esc_html_e( 'Cliente Duplicado', 'replanta-prices' ); ?></option>
                        </optgroup>
                    </select>
                    <button type="button" class="button" onclick="location.href='<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=events' ) ); ?>&event_type='+document.getElementById('event_type_filter').value">
                        <?php esc_html_e( 'Filtrar', 'replanta-prices' ); ?>
                    </button>
                </div>
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php printf( esc_html__( '%s elementos', 'replanta-prices' ), number_format_i18n( $total_events ) ); ?></span>
                </div>
            </div>

            <!-- Events Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:60px;"><?php esc_html_e( 'ID', 'replanta-prices' ); ?></th>
                        <th style="width:150px;"><?php esc_html_e( 'Fecha', 'replanta-prices' ); ?></th>
                        <th style="width:130px;"><?php esc_html_e( 'Tipo', 'replanta-prices' ); ?></th>
                        <th style="width:80px;"><?php esc_html_e( 'Estado', 'replanta-prices' ); ?></th>
                        <th><?php esc_html_e( 'AWC', 'replanta-prices' ); ?></th>
                        <th><?php esc_html_e( 'Referencia', 'replanta-prices' ); ?></th>
                        <th style="width:100px;"><?php esc_html_e( 'Importe', 'replanta-prices' ); ?></th>
                        <th style="width:80px;"><?php esc_html_e( 'Acciones', 'replanta-prices' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $events ) ) : ?>
                        <tr>
                            <td colspan="8"><?php esc_html_e( 'No hay eventos registrados.', 'replanta-prices' ); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $events as $event ) : ?>
                            <tr>
                                <td><?php echo esc_html( $event['id'] ); ?></td>
                                <td><?php echo esc_html( $event['created_at'] ); ?></td>
                                <td><code><?php echo esc_html( $event['event_type'] ); ?></code></td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr( $event['event_status'] ); ?>">
                                        <?php echo esc_html( $event['event_status'] ); ?>
                                    </span>
                                </td>
                                <td><code style="font-size:10px;"><?php echo esc_html( $event['awc'] ?: '-' ); ?></code></td>
                                <td><?php echo esc_html( $event['reference'] ?: '-' ); ?></td>
                                <td>
                                    <?php 
                                    if ( $event['amount'] ) {
                                        echo esc_html( number_format( $event['amount'], 2 ) . ' ' . ( $event['currency'] ?: 'EUR' ) );
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php $payload_json = wp_json_encode( $event['payload'] ?: new stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ); ?>
                                    <button type="button" class="button button-small awin-view-payload" 
                                            data-payload="<?php echo esc_attr( $payload_json ); ?>">
                                        <?php esc_html_e( 'Ver', 'replanta-prices' ); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ( $total_pages > 1 ) : ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links( array(
                            'base'      => add_query_arg( 'paged', '%#%' ),
                            'format'    => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total'     => $total_pages,
                            'current'   => $page,
                        ) );
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Payload Modal -->
        <div id="awin-payload-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:100000;">
            <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; padding:20px; border-radius:8px; max-width:800px; width:90%; max-height:80vh; display:flex; flex-direction:column;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <h3 style="margin:0;"><?php esc_html_e( 'Payload del Webhook', 'replanta-prices' ); ?></h3>
                    <button type="button" id="awin-modal-close" class="button">&times;</button>
                </div>
                <textarea id="awin-payload-content" readonly style="flex:1; min-height:300px; font-family:monospace; font-size:12px; resize:none;"></textarea>
                <div style="margin-top:10px; text-align:right;">
                    <button type="button" id="awin-copy-payload" class="button button-primary">
                        <?php esc_html_e( 'Copiar al portapapeles', 'replanta-prices' ); ?>
                    </button>
                </div>
            </div>
        </div>

        <script>
        (function($) {
            $('.awin-view-payload').on('click', function() {
                var payload = $(this).data('payload');
                if (typeof payload === 'object') {
                    payload = JSON.stringify(payload, null, 2);
                }
                $('#awin-payload-content').val(payload);
                $('#awin-payload-modal').show();
            });

            $('#awin-modal-close, #awin-payload-modal').on('click', function(e) {
                if (e.target === this) {
                    $('#awin-payload-modal').hide();
                }
            });

            $('#awin-copy-payload').on('click', function() {
                var textarea = document.getElementById('awin-payload-content');
                textarea.select();
                document.execCommand('copy');
                $(this).text('<?php echo esc_js( __( '¡Copiado!', 'replanta-prices' ) ); ?>');
                setTimeout(function() {
                    $('#awin-copy-payload').text('<?php echo esc_js( __( 'Copiar al portapapeles', 'replanta-prices' ) ); ?>');
                }, 2000);
            });
        })(jQuery);
        </script>
        <?php
    }

    /**
     * Render settings tab.
     */
    private static function render_settings_tab() {
        $settings = Replanta_Awin_Cookie::get_settings();

        ?>
        <form method="post" action="">
            <?php wp_nonce_field( 'replanta_awin_settings' ); ?>
            <input type="hidden" name="replanta_awin_action" value="save_settings">

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Activar Tracking Awin', 'replanta-prices' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="awin_enabled" value="1" <?php checked( $settings['enabled'] ); ?>>
                            <?php esc_html_e( 'Capturar AWC y añadir a URLs de compra', 'replanta-prices' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="advertiser_id"><?php esc_html_e( 'Advertiser ID', 'replanta-prices' ); ?></label></th>
                    <td>
                        <input type="text" name="advertiser_id" id="advertiser_id" 
                               value="<?php echo esc_attr( $settings['advertiser_id'] ); ?>" 
                               class="regular-text" placeholder="ej: 125596" pattern="[0-9]*" inputmode="numeric">
                        <p class="description">
                            <?php esc_html_e( 'Tu ID de anunciante en Awin (merchant). Lo encuentras en el codigo de tracking que te da Awin.', 'replanta-prices' ); ?>
                            <br>
                            <?php esc_html_e( 'Ejemplo:', 'replanta-prices' ); ?>
                            <code>merchant=<strong>125596</strong></code> o <code>https://www.dwin1.com/<strong>125596</strong>.js</code>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Inyectar MasterTag', 'replanta-prices' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="inject_mastertag" value="1" <?php checked( $settings['inject_mastertag'] ); ?>>
                            <?php esc_html_e( 'Añadir el script MasterTag automáticamente en el footer', 'replanta-prices' ); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'Si ya tienes el MasterTag via GTM u otro método, deja esta opción desactivada.', 'replanta-prices' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="cookie_name"><?php esc_html_e( 'Nombre Cookie', 'replanta-prices' ); ?></label></th>
                    <td>
                        <input type="text" name="cookie_name" id="cookie_name" 
                               value="<?php echo esc_attr( $settings['cookie_name'] ); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e( 'Nombre de la cookie para almacenar el AWC.', 'replanta-prices' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="cookie_days"><?php esc_html_e( 'Duración Cookie (días)', 'replanta-prices' ); ?></label></th>
                    <td>
                        <input type="number" name="cookie_days" id="cookie_days" 
                               value="<?php echo esc_attr( $settings['cookie_days'] ); ?>" min="1" max="365" class="small-text">
                        <p class="description"><?php esc_html_e( 'Awin normalmente usa 30-90 días. Verifica tu acuerdo.', 'replanta-prices' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="target_domain"><?php esc_html_e( 'Dominio Objetivo', 'replanta-prices' ); ?></label></th>
                    <td>
                        <input type="text" name="target_domain" id="target_domain" 
                               value="<?php echo esc_attr( $settings['target_domain'] ); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e( 'Dominio de las URLs de compra (ej: clientes.replanta.net).', 'replanta-prices' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="webhook_secret"><?php esc_html_e( 'Webhook Secret', 'replanta-prices' ); ?></label></th>
                    <td>
                        <input type="text" name="webhook_secret" id="webhook_secret" 
                               value="<?php echo esc_attr( $settings['webhook_secret'] ); ?>" class="regular-text">
                        <p class="description">
                            <?php esc_html_e( 'Secret compartido para validar webhooks de Upmind.', 'replanta-prices' ); ?>
                            <br>
                            <strong><?php esc_html_e( 'Endpoint:', 'replanta-prices' ); ?></strong>
                            <code><?php echo esc_html( Replanta_Awin_Webhook::get_webhook_url() ); ?></code>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'JS Fallback', 'replanta-prices' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="js_fallback" value="1" <?php checked( $settings['js_fallback'] ); ?>>
                            <?php esc_html_e( 'Usar JavaScript para modificar URLs no controladas por el plugin', 'replanta-prices' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Logs Detallados', 'replanta-prices' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="detailed_logs" value="1" <?php checked( $settings['detailed_logs'] ); ?>>
                            <?php esc_html_e( 'Registrar eventos de modificación de URL (más datos, más espacio)', 'replanta-prices' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="log_retention_days"><?php esc_html_e( 'Retención Logs (días)', 'replanta-prices' ); ?></label></th>
                    <td>
                        <input type="number" name="log_retention_days" id="log_retention_days" 
                               value="<?php echo esc_attr( $settings['log_retention_days'] ); ?>" min="7" max="365" class="small-text">
                        <p class="description"><?php esc_html_e( 'Los logs más antiguos se eliminarán automáticamente.', 'replanta-prices' ); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button( __( 'Guardar Configuración', 'replanta-prices' ) ); ?>
        </form>
        <?php
    }

    /**
     * Render tools tab.
     */
    private static function render_tools_tab() {
        $settings = Replanta_Awin_Cookie::get_settings();
        $awc      = Replanta_Awin_Cookie::get_awc();

        ?>
        <div class="awin-tools">
            <!-- AWC Status -->
            <div class="tool-card">
                <h3><?php esc_html_e( 'Estado AWC Actual', 'replanta-prices' ); ?></h3>
                <p><?php esc_html_e( 'Muestra el AWC activo en tu sesión actual.', 'replanta-prices' ); ?></p>
                <p>
                    <strong><?php esc_html_e( 'AWC en cookie:', 'replanta-prices' ); ?></strong>
                    <code><?php echo $awc ? esc_html( $awc ) : esc_html__( '(ninguno)', 'replanta-prices' ); ?></code>
                </p>
                <?php if ( $awc ) : ?>
                    <?php $is_trustworthy = Replanta_Awin_Cookie::is_awc_trustworthy( $awc ); ?>
                    <p>
                        <span class="dashicons <?php echo $is_trustworthy ? 'dashicons-yes' : 'dashicons-warning'; ?>" 
                              style="color:<?php echo $is_trustworthy ? '#00a32a' : '#dba617'; ?>;"></span>
                        <?php echo $is_trustworthy 
                            ? esc_html__( 'AWC válido y registrado en el sistema', 'replanta-prices' ) 
                            : esc_html__( 'AWC no verificable (no está en el log de capturas)', 'replanta-prices' ); 
                        ?>
                    </p>
                <?php endif; ?>
                <p class="description">
                    <?php esc_html_e( 'El AWC se captura automáticamente cuando un visitante llega desde Awin con el parámetro ?awc=... en la URL.', 'replanta-prices' ); ?>
                </p>
            </div>

            <!-- Cookie Consent Status -->
            <div class="tool-card">
                <h3><?php esc_html_e( 'Estado Consentimiento Cookies', 'replanta-prices' ); ?></h3>
                <p><?php esc_html_e( 'Complianz debe permitir cookies de marketing para capturar AWC.', 'replanta-prices' ); ?></p>
                <?php
                $has_consent = Replanta_Awin_Cookie::has_marketing_consent();
                $complianz_active = function_exists( 'cmplz_has_consent' );
                ?>
                <p>
                    <strong><?php esc_html_e( 'Complianz detectado:', 'replanta-prices' ); ?></strong>
                    <span class="dashicons <?php echo $complianz_active ? 'dashicons-yes' : 'dashicons-no-alt'; ?>" 
                          style="color:<?php echo $complianz_active ? '#00a32a' : '#d63638'; ?>;"></span>
                    <?php echo $complianz_active ? esc_html__( 'Sí', 'replanta-prices' ) : esc_html__( 'No', 'replanta-prices' ); ?>
                </p>
                <p>
                    <strong><?php esc_html_e( 'Consentimiento marketing:', 'replanta-prices' ); ?></strong>
                    <span class="dashicons <?php echo $has_consent ? 'dashicons-yes' : 'dashicons-no-alt'; ?>" 
                          style="color:<?php echo $has_consent ? '#00a32a' : '#dba617'; ?>;"></span>
                    <?php echo $has_consent 
                        ? esc_html__( 'Concedido', 'replanta-prices' ) 
                        : esc_html__( 'Pendiente o denegado', 'replanta-prices' ); 
                    ?>
                </p>
                <?php if ( $complianz_active && ! $has_consent ) : ?>
                    <p class="description" style="color:#dba617;">
                        <span class="dashicons dashicons-warning"></span>
                        <?php esc_html_e( 'Sin consentimiento, el AWC se guardará en memoria pero no en cookie hasta que el usuario acepte.', 'replanta-prices' ); ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Test URL Builder -->
            <div class="tool-card">
                <h3><?php esc_html_e( 'Probar Construcción de URL', 'replanta-prices' ); ?></h3>
                <p><?php esc_html_e( 'Verifica que las URLs incluyen el AWC correctamente.', 'replanta-prices' ); ?></p>
                <?php
                $test_pid = '6d530876-8251-d485-d80a-147e390921e6'; // Real PID (Sauce)
                $test_url = Replanta_Awin_URL_Helper::build_order_url( $test_pid );
                ?>
                <p>
                    <strong><?php esc_html_e( 'URL generada:', 'replanta-prices' ); ?></strong><br>
                    <code style="word-break:break-all;"><?php echo esc_html( $test_url ); ?></code>
                </p>
                <p>
                    <?php if ( $awc ) : ?>
                        <span class="dashicons dashicons-yes" style="color:#00a32a;"></span>
                        <?php esc_html_e( 'AWC incluido en la URL', 'replanta-prices' ); ?>
                    <?php else : ?>
                        <span class="dashicons dashicons-info" style="color:#2271b1;"></span>
                        <?php esc_html_e( 'Sin AWC (no hay cookie activa)', 'replanta-prices' ); ?>
                    <?php endif; ?>
                </p>
            </div>

            <!-- Generate Secret -->
            <div class="tool-card">
                <h3><?php esc_html_e( 'Generar Nuevo Secret', 'replanta-prices' ); ?></h3>
                <p><?php esc_html_e( 'Genera un nuevo secret aleatorio para webhooks.', 'replanta-prices' ); ?></p>
                <form method="post" action="">
                    <?php wp_nonce_field( 'replanta_awin_generate_secret' ); ?>
                    <input type="hidden" name="replanta_awin_action" value="generate_secret">
                    <button type="submit" class="button" onclick="return confirm('<?php esc_attr_e( '¿Generar nuevo secret? El anterior quedará inválido.', 'replanta-prices' ); ?>')">
                        <?php esc_html_e( 'Generar Secret', 'replanta-prices' ); ?>
                    </button>
                </form>
            </div>

            <!-- Cleanup Logs -->
            <div class="tool-card">
                <h3><?php esc_html_e( 'Limpiar Logs Antiguos', 'replanta-prices' ); ?></h3>
                <p>
                    <?php 
                    printf( 
                        esc_html__( 'Elimina eventos con más de %d días de antigüedad.', 'replanta-prices' ),
                        $settings['log_retention_days']
                    ); 
                    ?>
                </p>
                <form method="post" action="">
                    <?php wp_nonce_field( 'replanta_awin_cleanup' ); ?>
                    <input type="hidden" name="replanta_awin_action" value="cleanup_logs">
                    <button type="submit" class="button">
                        <?php esc_html_e( 'Ejecutar Limpieza', 'replanta-prices' ); ?>
                    </button>
                </form>
            </div>

            <!-- Test Webhook -->
            <div class="tool-card">
                <h3><?php esc_html_e( 'Probar Webhook', 'replanta-prices' ); ?></h3>
                <p><?php esc_html_e( 'Envía un webhook de prueba al endpoint.', 'replanta-prices' ); ?></p>
                <p>
                    <strong><?php esc_html_e( 'Endpoint:', 'replanta-prices' ); ?></strong><br>
                    <code><?php echo esc_html( Replanta_Awin_Webhook::get_webhook_url() . '/test' ); ?></code>
                </p>
                <p>
                    <button type="button" class="button" id="test-webhook-btn">
                        <?php esc_html_e( 'Enviar Test', 'replanta-prices' ); ?>
                    </button>
                    <span id="test-webhook-result"></span>
                </p>
                <script>
                document.getElementById('test-webhook-btn').addEventListener('click', function() {
                    var btn = this;
                    var result = document.getElementById('test-webhook-result');
                    btn.disabled = true;
                    result.textContent = '<?php esc_html_e( 'Enviando...', 'replanta-prices' ); ?>';
                    
                    fetch('<?php echo esc_url( Replanta_Awin_Webhook::get_webhook_url() . '/test' ); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>'
                        },
                        body: JSON.stringify({ test: true, timestamp: new Date().toISOString() })
                    })
                    .then(r => r.json())
                    .then(data => {
                        result.innerHTML = '<span style="color:green;">OK - ' + (data.message || 'Webhook registrado') + '</span>';
                    })
                    .catch(err => {
                        result.innerHTML = '<span style="color:red;">Error: ' + err.message + '</span>';
                    })
                    .finally(() => {
                        btn.disabled = false;
                    });
                });
                </script>
            </div>

            <!-- S2S Connection Test -->
            <div class="tool-card">
                <h3><?php esc_html_e( 'Test Conexion S2S Awin', 'replanta-prices' ); ?></h3>
                <p><?php esc_html_e( 'Verifica que la conexion con el endpoint S2S de Awin funciona correctamente.', 'replanta-prices' ); ?></p>
                <?php if ( empty( $settings['advertiser_id'] ) ) : ?>
                    <p class="notice notice-warning">
                        <span class="dashicons dashicons-warning"></span>
                        <?php esc_html_e( 'Configura el Advertiser ID primero en Ajustes.', 'replanta-prices' ); ?>
                    </p>
                <?php else : ?>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'replanta_awin_test_s2s' ); ?>
                        <input type="hidden" name="replanta_awin_action" value="test_s2s">
                        <button type="submit" class="button">
                            <?php esc_html_e( 'Probar Conexion S2S', 'replanta-prices' ); ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Process Pending S2S -->
            <?php $stats = Replanta_Awin_Logger::get_stats(); ?>
            <?php if ( $stats['conversions_pending'] > 0 ) : ?>
            <div class="tool-card">
                <h3><?php esc_html_e( 'Procesar Conversiones Pendientes', 'replanta-prices' ); ?></h3>
                <p>
                    <?php 
                    printf( 
                        esc_html__( 'Hay %d conversiones pendientes de enviar a Awin.', 'replanta-prices' ),
                        $stats['conversions_pending']
                    ); 
                    ?>
                </p>
                <form method="post" action="">
                    <?php wp_nonce_field( 'replanta_awin_process_s2s' ); ?>
                    <input type="hidden" name="replanta_awin_action" value="process_s2s">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e( 'Procesar Ahora', 'replanta-prices' ); ?>
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
