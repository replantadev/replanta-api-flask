<?php
/**
 * Dominios Reseller · Tree Nation Integration
 *
 * Registra árboles plantados y CO₂ evitado por dominio.
 * – Datos manuales editables por el admin.
 * – Auto-cálculo estimado por antigüedad del dominio.
 * – Sincronización con Tree-Nation API (totales del bosque).
 *
 * @package DominiosReseller
 * @since   1.8.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Dominios_Reseller_Tree_Nation {

    const OPTION_KEY = 'dr_tree_nation_settings';
    const API_BASE   = 'https://tree-nation.com/api';

    /* ──────────────────────────────────────────────
       Boot
    ────────────────────────────────────────────── */

    public static function init() {
        add_action( 'wp_ajax_dr_tn_save_settings', [ __CLASS__, 'ajax_save_settings' ] );
        add_action( 'wp_ajax_dr_tn_sync_api',      [ __CLASS__, 'ajax_sync_api'      ] );
        add_action( 'wp_ajax_dr_tn_auto_calc',     [ __CLASS__, 'ajax_auto_calc'     ] );
        add_action( 'wp_ajax_dr_tn_save_domain',   [ __CLASS__, 'ajax_save_domain'   ] );
        add_action( 'wp_ajax_dr_tn_save_all',      [ __CLASS__, 'ajax_save_all'      ] );
        add_action( 'rest_api_init',               [ __CLASS__, 'register_rest'      ] );
    }

    /* ──────────────────────────────────────────────
       REST API: public endpoint
       GET /wp-json/dr/v1/trees
       Returns { trees, co2, source } — cached 1h.
       Falls back to local DB sum if API not configured.
    ────────────────────────────────────────────── */

    public static function register_rest() {
        register_rest_route( 'dr/v1', '/trees', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'rest_get_trees' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public static function rest_get_trees( WP_REST_Request $request ): WP_REST_Response {
        $cache_key = 'dr_tn_public_totals';
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) {
            return new WP_REST_Response( $cached, 200 );
        }

        $settings = self::get_settings();

        // Try Tree-Nation API first
        if ( ! empty( $settings['api_token'] ) && ! empty( $settings['forest_id'] ) ) {
            $forest_id = intval( $settings['forest_id'] );
            $response  = wp_remote_get(
                self::API_BASE . "/forests/{$forest_id}",
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $settings['api_token'],
                        'Accept'        => 'application/json',
                    ],
                    'timeout' => 10,
                ]
            );

            if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                $data = [
                    'trees'  => intval( $body['total_trees']     ?? ( $body['trees_planted']   ?? 0 ) ),
                    'co2'    => round( floatval( $body['co2_compensated'] ?? 0 ), 1 ),
                    'source' => 'api',
                ];
                set_transient( $cache_key, $data, HOUR_IN_SECONDS );
                return new WP_REST_Response( $data, 200 );
            }
        }

        // Fallback: local DB sum
        global $wpdb;
        $table = $wpdb->prefix . 'dominios_reseller';
        $row   = $wpdb->get_row( "SELECT COALESCE(SUM(trees_planted),0) AS trees, COALESCE(SUM(co2_evaded),0) AS co2 FROM `{$table}` WHERE is_primary = 1" );
        $data  = [
            'trees'  => intval( $row->trees ?? 0 ),
            'co2'    => round( floatval( $row->co2 ?? 0 ), 1 ),
            'source' => 'db',
        ];
        set_transient( $cache_key, $data, 15 * MINUTE_IN_SECONDS );
        return new WP_REST_Response( $data, 200 );
    }

    /* ──────────────────────────────────────────────
       Settings helpers
    ────────────────────────────────────────────── */

    public static function get_settings(): array {
        return wp_parse_args( get_option( self::OPTION_KEY, [] ), [
            'api_token'        => '',
            'forest_id'        => '',
            'last_sync'        => '',
            'last_sync_result' => '',
        ] );
    }

    /* ──────────────────────────────────────────────
       Impact estimation
       1 tree per complete year of hosting.
       Each tree absorbs ~21.7 kg CO₂ / year.
    ────────────────────────────────────────────── */

    public static function calculate_impact( object $row ): array {
        $startdate = intval( $row->startdate ?? 0 );
        if ( ! $startdate ) {
            return [ 'trees' => 0, 'co2' => 0.0 ];
        }
        $age_years = ( time() - $startdate ) / ( 365.25 * DAY_IN_SECONDS );
        $trees     = max( 0, (int) floor( $age_years ) );
        $co2       = round( $trees * 21.7, 2 );
        return [ 'trees' => $trees, 'co2' => $co2 ];
    }

    /* ──────────────────────────────────────────────
       AJAX: Save API settings
    ────────────────────────────────────────────── */

    public static function ajax_save_settings() {
        check_ajax_referer( 'dr_tn_nonce', '_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permisos insuficientes' );
        }

        $settings              = self::get_settings();
        $settings['api_token'] = sanitize_text_field( wp_unslash( $_POST['api_token'] ?? '' ) );
        $settings['forest_id'] = sanitize_text_field( wp_unslash( $_POST['forest_id'] ?? '' ) );
        update_option( self::OPTION_KEY, $settings );

        wp_send_json_success( [ 'message' => 'Configuración guardada.' ] );
    }

    /* ──────────────────────────────────────────────
       AJAX: Sync forest totals from Tree-Nation API
    ────────────────────────────────────────────── */

    public static function ajax_sync_api() {
        check_ajax_referer( 'dr_tn_nonce', '_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permisos insuficientes' );
        }

        $settings = self::get_settings();
        if ( empty( $settings['api_token'] ) || empty( $settings['forest_id'] ) ) {
            wp_send_json_error( 'Configura el Token y el Forest ID primero.' );
        }

        $forest_id = intval( $settings['forest_id'] );
        $response  = wp_remote_get(
            self::API_BASE . "/forests/{$forest_id}",
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $settings['api_token'],
                    'Accept'        => 'application/json',
                ],
                'timeout' => 15,
            ]
        );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( 'Error de conexión: ' . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            wp_send_json_error(
                sprintf( 'API devolvió HTTP %d: %s', $code, $body['message'] ?? 'Error desconocido' )
            );
        }

        // Persist last sync info
        $settings['last_sync']        = current_time( 'mysql' );
        $settings['last_sync_result'] = wp_json_encode( [
            'trees' => $body['total_trees']      ?? ( $body['trees_planted'] ?? null ),
            'co2'   => $body['co2_compensated']  ?? null,
            'name'  => $body['name']             ?? '',
        ] );
        update_option( self::OPTION_KEY, $settings );
        delete_transient( 'dr_tn_public_totals' ); // bust REST cache

        wp_send_json_success( [
            'message' => 'Sincronización completada.',
            'data'    => $body,
        ] );
    }

    /* ──────────────────────────────────────────────
       AJAX: Auto-calculate impact for one domain
    ────────────────────────────────────────────── */

    public static function ajax_auto_calc() {
        check_ajax_referer( 'dr_tn_nonce', '_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permisos insuficientes' );
        }

        global $wpdb;
        $table  = $wpdb->prefix . 'dominios_reseller';
        $domain = sanitize_text_field( wp_unslash( $_POST['domain'] ?? '' ) );
        $server = sanitize_text_field( wp_unslash( $_POST['server'] ?? '' ) );
        $apply  = ! empty( $_POST['apply'] );

        if ( ! $domain || ! $server ) {
            wp_send_json_error( 'Datos incompletos' );
        }

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE domain = %s AND server = %s",
            $domain, $server
        ) );

        if ( ! $row ) {
            wp_send_json_error( 'Dominio no encontrado' );
        }

        $impact = self::calculate_impact( $row );

        if ( $apply ) {
            $wpdb->update(
                $table,
                [ 'trees_planted' => $impact['trees'], 'co2_evaded' => $impact['co2'] ],
                [ 'domain' => $domain, 'server' => $server ],
                [ '%d', '%f' ],
                [ '%s', '%s' ]
            );
        }

        wp_send_json_success( $impact );
    }

    /* ──────────────────────────────────────────────
       AJAX: Save a single domain row
    ────────────────────────────────────────────── */

    public static function ajax_save_domain() {
        check_ajax_referer( 'dr_tn_nonce', '_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permisos insuficientes' );
        }

        global $wpdb;
        $table  = $wpdb->prefix . 'dominios_reseller';
        $domain = sanitize_text_field( wp_unslash( $_POST['domain'] ?? '' ) );
        $server = sanitize_text_field( wp_unslash( $_POST['server'] ?? '' ) );
        $trees  = max( 0, intval( $_POST['trees'] ?? 0 ) );
        $co2    = max( 0.0, floatval( $_POST['co2'] ?? 0 ) );

        if ( ! $domain || ! $server ) {
            wp_send_json_error( 'Datos incompletos' );
        }

        $result = $wpdb->update(
            $table,
            [ 'trees_planted' => $trees, 'co2_evaded' => $co2 ],
            [ 'domain' => $domain, 'server' => $server ],
            [ '%d', '%f' ],
            [ '%s', '%s' ]
        );

        if ( $result === false ) {
            wp_send_json_error( 'Error al guardar: ' . $wpdb->last_error );
        }

        wp_send_json_success( [ 'trees' => $trees, 'co2' => $co2 ] );
    }

    /* ──────────────────────────────────────────────
       AJAX: Save all domain rows (bulk)
    ────────────────────────────────────────────── */

    public static function ajax_save_all() {
        check_ajax_referer( 'dr_tn_nonce', '_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permisos insuficientes' );
        }

        global $wpdb;
        $table   = $wpdb->prefix . 'dominios_reseller';
        $changes = $_POST['changes'] ?? [];

        if ( ! is_array( $changes ) ) {
            wp_send_json_error( 'Datos inválidos' );
        }

        $updated = 0;
        foreach ( $changes as $c ) {
            $domain = sanitize_text_field( wp_unslash( $c['domain'] ?? '' ) );
            $server = sanitize_text_field( wp_unslash( $c['server'] ?? '' ) );
            $trees  = max( 0, intval( $c['trees'] ?? 0 ) );
            $co2    = max( 0.0, floatval( $c['co2'] ?? 0 ) );

            if ( ! $domain || ! $server ) continue;

            $r = $wpdb->update(
                $table,
                [ 'trees_planted' => $trees, 'co2_evaded' => $co2 ],
                [ 'domain' => $domain, 'server' => $server ],
                [ '%d', '%f' ],
                [ '%s', '%s' ]
            );
            if ( $r !== false ) $updated++;
        }

        wp_send_json_success( [ 'updated' => $updated ] );
    }

    /* ──────────────────────────────────────────────
       Admin page render
    ────────────────────────────────────────────── */

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Acceso denegado' );
        }

        global $wpdb;
        $table    = $wpdb->prefix . 'dominios_reseller';
        $settings = self::get_settings();
        $nonce    = wp_create_nonce( 'dr_tn_nonce' );

        // Totals
        $totals = $wpdb->get_row( "
            SELECT COUNT(*) AS total_domains,
                   COALESCE(SUM(trees_planted),0) AS total_trees,
                   COALESCE(SUM(co2_evaded),0)    AS total_co2
            FROM `{$table}`
            WHERE is_primary = 1
        " );

        // Last API sync result
        $sync_data = $settings['last_sync_result']
            ? json_decode( $settings['last_sync_result'], true )
            : null;

        // All primary domains
        $domains = $wpdb->get_results( "
            SELECT * FROM `{$table}`
            WHERE is_primary = 1
            ORDER BY server, domain
        " );

        $api_configured = ! empty( $settings['api_token'] ) && ! empty( $settings['forest_id'] );
        ?>
        <div class="wrap dr-tn-wrap">
        <style>
        .dr-tn-wrap{max-width:1300px;}
        /* ── Header ── */
        .dr-tn-header{background:linear-gradient(135deg,#071a0e,#0f2d1a,#1a3d28);border-radius:12px;padding:26px 32px;margin-bottom:20px;color:#fff;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;}
        .dr-tn-header h1{color:#93F1C9;font-size:1.45rem;margin:0 0 4px;font-weight:700;}
        .dr-tn-header p{color:rgba(255,255,255,.65);margin:0;font-size:.87rem;}
        /* ── Stats ── */
        .dr-tn-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px;}
        .dr-tn-stat{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:18px 20px;text-align:center;}
        .dr-tn-stat .val{font-size:1.85rem;font-weight:700;line-height:1;color:#166534;}
        .dr-tn-stat .lbl{font-size:.73rem;color:#6b7280;margin-top:5px;text-transform:uppercase;letter-spacing:.06em;}
        .dr-tn-stat.teal .val{color:#41999F;}
        .dr-tn-stat.neutral .val{color:#374151;font-size:1.1rem;}
        /* ── Box ── */
        .dr-tn-box{background:#fff;border:1px solid #e5e7eb;border-radius:10px;margin-bottom:18px;overflow:hidden;}
        .dr-tn-box-head{padding:13px 20px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;gap:10px;cursor:pointer;user-select:none;}
        .dr-tn-box-head h2{font-size:.97rem;margin:0;flex:1;}
        .dr-tn-box-body{padding:20px;}
        .dr-tn-box-body.is-closed{display:none;}
        /* ── Settings form ── */
        .dr-tn-settings-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
        .dr-tn-settings-row label{display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:4px;}
        .dr-tn-settings-row input{width:100%;padding:8px 10px;border:1.5px solid #d1d5db;border-radius:6px;font-size:.9rem;font-family:monospace;}
        .dr-tn-settings-row input:focus{border-color:#41999F;outline:none;box-shadow:0 0 0 3px rgba(65,153,159,.15);}
        /* ── Table ── */
        .dr-tn-table{width:100%;border-collapse:collapse;font-size:.87rem;}
        .dr-tn-table th{background:#f9fafb;padding:9px 12px;text-align:left;font-size:.71rem;text-transform:uppercase;letter-spacing:.07em;color:#6b7280;border-bottom:2px solid #e5e7eb;white-space:nowrap;}
        .dr-tn-table td{padding:8px 12px;border-bottom:1px solid #f3f4f6;vertical-align:middle;}
        .dr-tn-table tbody tr:hover td{background:#f8fff4;}
        .dr-tn-table tbody tr:last-child td{border-bottom:none;}
        /* ── Inputs ── */
        .dr-tn-num{width:68px;padding:5px 7px;border:1.5px solid #d1d5db;border-radius:6px;font-size:.85rem;text-align:right;}
        .dr-tn-num:focus{border-color:#41999F;outline:none;}
        .dr-tn-num.is-dirty{border-color:#f59e0b!important;background:#fffbeb;}
        /* ── Badges ── */
        .bsrv{display:inline-block;padding:2px 8px;border-radius:4px;font-size:.7rem;font-weight:700;text-transform:uppercase;}
        .bsrv.uk{background:#dbeafe;color:#1d4ed8;}
        .bsrv.usa{background:#fef3c7;color:#92400e;}
        .bstat-ok{color:#15803d;font-weight:600;font-size:.8rem;}
        .bstat-ko{color:#dc2626;font-weight:600;font-size:.8rem;}
        .age-tag{background:#f0fdf4;color:#15803d;border-radius:4px;padding:2px 7px;font-size:.74rem;font-weight:600;}
        .age-tag.old{background:#fef9c3;color:#854d0e;}
        /* ── Buttons ── */
        .dr-btn{display:inline-flex;align-items:center;gap:5px;padding:7px 15px;border-radius:6px;font-size:.84rem;font-weight:600;border:none;cursor:pointer;transition:all .18s;line-height:1;}
        .dr-btn:disabled{opacity:.5;cursor:not-allowed;}
        .dr-btn-forest{background:#166534;color:#fff;}
        .dr-btn-forest:hover:not(:disabled){background:#15803d;}
        .dr-btn-teal{background:#41999F;color:#fff;}
        .dr-btn-teal:hover:not(:disabled){background:#368F95;}
        .dr-btn-sun{background:#F7D450;color:#1E2F23;}
        .dr-btn-sun:hover:not(:disabled){background:#f5cc3d;}
        .dr-btn-ghost{background:#f3f4f6;color:#374151;border:1px solid #e5e7eb;}
        .dr-btn-ghost:hover:not(:disabled){background:#e5e7eb;}
        .dr-btn-sm{padding:4px 9px;font-size:.76rem;}
        /* ── Notice ── */
        #dr-tn-notice{padding:10px 16px;border-radius:7px;font-size:.87rem;margin-bottom:14px;display:none;}
        #dr-tn-notice.is-ok{background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;display:block;}
        #dr-tn-notice.is-err{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;display:block;}
        /* ── API info strip ── */
        .dr-tn-api-strip{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:11px 16px;font-size:.84rem;margin-bottom:18px;}
        .dr-tn-api-strip strong{color:#15803d;}
        /* ── Responsive ── */
        @media(max-width:960px){.dr-tn-stats{grid-template-columns:1fr 1fr;}.dr-tn-settings-row{grid-template-columns:1fr;}}
        </style>

        <!-- ── HEADER ── -->
        <div class="dr-tn-header">
            <div>
                <h1>🌱 Impacto Ecológico · Tree Nation</h1>
                <p>Árboles plantados y CO₂ evitado por dominio — datos manuales + sincronización con Tree-Nation API</p>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button class="dr-btn dr-btn-teal" id="dr-tn-sync-btn" <?php echo $api_configured ? '' : 'disabled title="Configura el token primero"'; ?>>
                    ↻ Sincronizar API
                </button>
                <button class="dr-btn dr-btn-sun" id="dr-tn-save-all-btn">
                    💾 Guardar todos
                </button>
            </div>
        </div>

        <div id="dr-tn-notice"></div>

        <!-- ── STATS ── -->
        <div class="dr-tn-stats">
            <div class="dr-tn-stat">
                <div class="val" id="dr-stat-trees"><?php echo number_format( intval( $totals->total_trees ) ); ?></div>
                <div class="lbl">🌳 Árboles plantados</div>
            </div>
            <div class="dr-tn-stat teal">
                <div class="val"><?php echo number_format( floatval( $totals->total_co2 ), 1 ); ?> kg</div>
                <div class="lbl">💨 CO₂ evitado</div>
            </div>
            <div class="dr-tn-stat">
                <div class="val"><?php echo intval( $totals->total_domains ); ?></div>
                <div class="lbl">🌐 Dominios primarios</div>
            </div>
            <div class="dr-tn-stat neutral">
                <?php if ( $settings['last_sync'] ): ?>
                    <div class="val" style="color:#15803d;">✅ Conectada</div>
                    <div class="lbl">Hace <?php echo human_time_diff( strtotime( $settings['last_sync'] ), time() ); ?></div>
                <?php elseif ( $api_configured ): ?>
                    <div class="val" style="color:#d97706;">⚡ Token OK</div>
                    <div class="lbl">Sin sincronizar aún</div>
                <?php else: ?>
                    <div class="val" style="color:#9ca3af;">— Sin config</div>
                    <div class="lbl">API no configurada</div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ( $sync_data ): ?>
        <div class="dr-tn-api-strip">
            <strong>Último dato de Tree-Nation API</strong> ·
            <?php if ( ! empty( $sync_data['name'] ) ): ?>Bosque: <?php echo esc_html( $sync_data['name'] ); ?> · <?php endif; ?>
            <?php if ( ! empty( $sync_data['trees'] ) ): ?>🌳 <?php echo number_format( intval( $sync_data['trees'] ) ); ?> árboles · <?php endif; ?>
            <?php if ( ! empty( $sync_data['co2'] ) ): ?>💨 <?php echo number_format( floatval( $sync_data['co2'] ), 1 ); ?> kg CO₂ · <?php endif; ?>
            <span style="color:#6b7280;">Sincronizado: <?php echo esc_html( $settings['last_sync'] ); ?></span>
        </div>
        <?php endif; ?>

        <!-- ── SETTINGS ── -->
        <div class="dr-tn-box">
            <div class="dr-tn-box-head" id="dr-tn-cfg-toggle">
                <span>⚙️</span>
                <h2>Configuración Tree-Nation API</h2>
                <span id="dr-tn-cfg-arrow"><?php echo $api_configured ? '▼' : '▲'; ?></span>
            </div>
            <div class="dr-tn-box-body <?php echo $api_configured ? 'is-closed' : ''; ?>" id="dr-tn-cfg-body">
                <div class="dr-tn-settings-row">
                    <div>
                        <label for="dr-tn-token">API Token <span style="font-weight:400;color:#6b7280;">(Bearer auth)</span></label>
                        <input type="password" id="dr-tn-token"
                               value="<?php echo esc_attr( $settings['api_token'] ); ?>"
                               placeholder="5bWbB1Rx…" autocomplete="new-password">
                    </div>
                    <div>
                        <label for="dr-tn-forest-id">Forest ID</label>
                        <input type="text" id="dr-tn-forest-id"
                               value="<?php echo esc_attr( $settings['forest_id'] ); ?>"
                               placeholder="151329">
                    </div>
                </div>
                <div style="margin-top:14px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
                    <button class="dr-btn dr-btn-forest" id="dr-tn-save-cfg-btn">Guardar configuración</button>
                    <small style="color:#6b7280;">
                        Test: <code>info@replanta.dev</code> · Forest ID: <code>151329</code> ·
                        <a href="https://youcannevertestenough.tree-nation.com" target="_blank" rel="noopener" style="color:#41999F;">youcannevertestenough.tree-nation.com</a>
                    </small>
                </div>
            </div>
        </div>

        <!-- ── DOMAIN TABLE ── -->
        <div class="dr-tn-box">
            <div class="dr-tn-box-head" style="cursor:default;">
                <span>🌐</span>
                <h2>Impacto por dominio</h2>
                <div style="display:flex;gap:8px;margin-left:auto;">
                    <button class="dr-btn dr-btn-ghost dr-btn-sm" id="dr-tn-calc-all-btn">
                        ⚡ Auto-calcular todos
                    </button>
                </div>
            </div>
            <div style="overflow-x:auto;">
                <table class="dr-tn-table">
                    <thead>
                        <tr>
                            <th>Dominio</th>
                            <th>Servidor</th>
                            <th>Estado</th>
                            <th>Antigüedad</th>
                            <th>🌳 Árboles</th>
                            <th>💨 CO₂ (kg)</th>
                            <th style="width:110px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="dr-tn-tbody">
                    <?php foreach ( $domains as $row ):
                        $startdate = intval( $row->startdate ?? 0 );
                        $age_days  = $startdate ? ( time() - $startdate ) / DAY_IN_SECONDS : 0;
                        $age_str   = '';
                        $age_class = 'age-tag';
                        if ( $age_days > 0 ) {
                            if ( $age_days >= 365 ) {
                                $age_str   = round( $age_days / 365.25, 1 ) . ' años';
                                $age_class .= $age_days >= 730 ? ' old' : '';
                            } else {
                                $age_str = round( $age_days ) . ' días';
                            }
                        }
                        $est        = self::calculate_impact( $row );
                        $trees      = intval( $row->trees_planted );
                        $co2        = floatval( $row->co2_evaded );
                        $status_cl  = ( $row->status === 'Activo' ) ? 'bstat-ok' : 'bstat-ko';
                    ?>
                        <tr data-domain="<?php echo esc_attr( $row->domain ); ?>"
                            data-server="<?php echo esc_attr( $row->server ); ?>">
                            <td><strong><?php echo esc_html( $row->domain ); ?></strong></td>
                            <td><span class="bsrv <?php echo esc_attr( $row->server ); ?>"><?php echo strtoupper( esc_html( $row->server ) ); ?></span></td>
                            <td><span class="<?php echo $status_cl; ?>"><?php echo esc_html( $row->status ?? 'Activo' ); ?></span></td>
                            <td>
                                <?php if ( $age_str ): ?>
                                    <span class="<?php echo $age_class; ?>"><?php echo esc_html( $age_str ); ?></span>
                                <?php else: ?>
                                    <span style="color:#9ca3af;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <input type="number" class="dr-tn-num trees-input"
                                       min="0" value="<?php echo $trees; ?>"
                                       data-orig="<?php echo $trees; ?>"
                                       title="Estimado por antigüedad: <?php echo $est['trees']; ?> árbol(es)">
                            </td>
                            <td>
                                <input type="number" class="dr-tn-num co2-input"
                                       min="0" step="0.1" value="<?php echo $co2; ?>"
                                       data-orig="<?php echo $co2; ?>"
                                       title="Estimado: <?php echo $est['co2']; ?> kg">
                            </td>
                            <td style="white-space:nowrap;">
                                <button class="dr-btn dr-btn-ghost dr-btn-sm dr-calc-row"
                                        title="Auto-calcular por antigüedad (sobreescribe)">⚡</button>
                                <button class="dr-btn dr-btn-teal dr-btn-sm dr-save-row"
                                        style="margin-left:4px;" title="Guardar esta fila">✓</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <p style="color:#9ca3af;font-size:.8rem;">
            💡 <strong>Fórmula estimación:</strong> 1 árbol por año completo de hosting · cada árbol absorbe ~21,7 kg CO₂/año.
            Los valores son editables y se guardan manualmente — la sincronización con Tree-Nation actualiza el total del bosque (no por dominio).
        </p>

        <script>
        (function($){
            var nonce   = '<?php echo esc_js( $nonce ); ?>';
            var $notice = $('#dr-tn-notice');
            var $tbody  = $('#dr-tn-tbody');

            /* ── Notice ── */
            var noticeTimer;
            function showNotice(msg, ok) {
                clearTimeout(noticeTimer);
                $notice.text(msg).removeClass('is-ok is-err').addClass(ok ? 'is-ok' : 'is-err');
                noticeTimer = setTimeout(function(){ $notice.removeClass('is-ok is-err').text(''); }, 6000);
            }

            /* ── Settings toggle ── */
            $('#dr-tn-cfg-toggle').on('click', function(){
                var $body  = $('#dr-tn-cfg-body');
                var $arrow = $('#dr-tn-cfg-arrow');
                $body.toggleClass('is-closed');
                $arrow.text($body.hasClass('is-closed') ? '▼' : '▲');
            });

            /* ── Save settings ── */
            $('#dr-tn-save-cfg-btn').on('click', function(){
                var $btn = $(this).prop('disabled', true).text('Guardando…');
                $.post(ajaxurl, {
                    action:    'dr_tn_save_settings',
                    _nonce:    nonce,
                    api_token: $('#dr-tn-token').val(),
                    forest_id: $('#dr-tn-forest-id').val(),
                }, function(r){
                    $btn.prop('disabled', false).text('Guardar configuración');
                    if (r.success) {
                        showNotice('✅ ' + r.data.message, true);
                        $('#dr-tn-sync-btn').prop('disabled', false);
                        $('#dr-tn-cfg-body').addClass('is-closed');
                        $('#dr-tn-cfg-arrow').text('▼');
                    } else {
                        showNotice('❌ ' + r.data, false);
                    }
                });
            });

            /* ── Sync API ── */
            $('#dr-tn-sync-btn').on('click', function(){
                var $btn = $(this).prop('disabled', true).text('Sincronizando…');
                $.post(ajaxurl, {
                    action: 'dr_tn_sync_api',
                    _nonce: nonce,
                }, function(r){
                    $btn.prop('disabled', false).text('↻ Sincronizar API');
                    if (r.success) {
                        showNotice('✅ ' + r.data.message + ' Recargando…', true);
                        setTimeout(function(){ location.reload(); }, 1200);
                    } else {
                        showNotice('❌ ' + r.data, false);
                    }
                });
            });

            /* ── Mark dirty on change ── */
            $tbody.on('input', '.dr-tn-num', function(){
                var orig = $(this).data('orig');
                $(this).toggleClass('is-dirty', String($(this).val()) !== String(orig));
            });

            /* ── Auto-calc single row ── */
            $tbody.on('click', '.dr-calc-row', function(){
                var $btn = $(this).prop('disabled', true).text('…');
                var $tr  = $(this).closest('tr');
                $.post(ajaxurl, {
                    action: 'dr_tn_auto_calc',
                    _nonce: nonce,
                    domain: $tr.data('domain'),
                    server: $tr.data('server'),
                    apply:  '1',
                }, function(r){
                    $btn.prop('disabled', false).text('⚡');
                    if (r.success) {
                        $tr.find('.trees-input').val(r.data.trees).data('orig', r.data.trees).removeClass('is-dirty');
                        $tr.find('.co2-input').val(r.data.co2).data('orig', r.data.co2).removeClass('is-dirty');
                        showNotice('⚡ ' + $tr.data('domain') + ' → ' + r.data.trees + ' árbol(es) · ' + r.data.co2 + ' kg CO₂', true);
                    } else {
                        showNotice('❌ ' + r.data, false);
                    }
                });
            });

            /* ── Save single row ── */
            $tbody.on('click', '.dr-save-row', function(){
                var $btn = $(this).prop('disabled', true).text('…');
                var $tr  = $(this).closest('tr');
                var trees = $tr.find('.trees-input').val();
                var co2   = $tr.find('.co2-input').val();
                $.post(ajaxurl, {
                    action: 'dr_tn_save_domain',
                    _nonce: nonce,
                    domain: $tr.data('domain'),
                    server: $tr.data('server'),
                    trees:  trees,
                    co2:    co2,
                }, function(r){
                    $btn.prop('disabled', false).text('✓');
                    if (r.success) {
                        $tr.find('.trees-input').data('orig', r.data.trees).removeClass('is-dirty');
                        $tr.find('.co2-input').data('orig', r.data.co2).removeClass('is-dirty');
                        showNotice('✅ Guardado: ' + $tr.data('domain'), true);
                        updateTotal();
                    } else {
                        showNotice('❌ ' + r.data, false);
                    }
                });
            });

            /* ── Auto-calc ALL ── */
            $('#dr-tn-calc-all-btn').on('click', function(){
                if (!confirm('¿Auto-calcular y sobreescribir árboles/CO₂ en TODOS los dominios según su antigüedad?\n\nEsto sobreescribe los valores actuales.')) return;
                var $btn = $(this).prop('disabled', true).text('Calculando…');
                var $rows = $tbody.find('tr');
                var done  = 0;
                var total = $rows.length;
                if (!total) { $btn.prop('disabled', false).text('⚡ Auto-calcular todos'); return; }

                $rows.each(function(){
                    var $tr = $(this);
                    $.post(ajaxurl, {
                        action: 'dr_tn_auto_calc',
                        _nonce: nonce,
                        domain: $tr.data('domain'),
                        server: $tr.data('server'),
                        apply:  '1',
                    }, function(r){
                        done++;
                        if (r.success) {
                            $tr.find('.trees-input').val(r.data.trees).data('orig', r.data.trees).removeClass('is-dirty');
                            $tr.find('.co2-input').val(r.data.co2).data('orig', r.data.co2).removeClass('is-dirty');
                        }
                        if (done === total) {
                            $btn.prop('disabled', false).text('⚡ Auto-calcular todos');
                            showNotice('✅ Auto-cálculo completado para ' + done + ' dominios.', true);
                            updateTotal();
                        }
                    });
                });
            });

            /* ── Save ALL ── */
            $('#dr-tn-save-all-btn').on('click', function(){
                var $btn = $(this).prop('disabled', true).text('Guardando…');
                var changes = [];
                $tbody.find('tr').each(function(){
                    var $tr = $(this);
                    changes.push({
                        domain: $tr.data('domain'),
                        server: $tr.data('server'),
                        trees:  $tr.find('.trees-input').val(),
                        co2:    $tr.find('.co2-input').val(),
                    });
                });
                if (!changes.length) { $btn.prop('disabled', false).text('💾 Guardar todos'); return; }

                $.post(ajaxurl, {
                    action:  'dr_tn_save_all',
                    _nonce:  nonce,
                    changes: changes,
                }, function(r){
                    $btn.prop('disabled', false).text('💾 Guardar todos');
                    if (r.success) {
                        $tbody.find('.dr-tn-num').each(function(){
                            $(this).data('orig', $(this).val()).removeClass('is-dirty');
                        });
                        showNotice('✅ ' + r.data.updated + ' dominios guardados correctamente.', true);
                        updateTotal();
                    } else {
                        showNotice('❌ ' + r.data, false);
                    }
                });
            });

            /* ── Update total trees counter in stat card ── */
            function updateTotal() {
                var total = 0;
                $tbody.find('.trees-input').each(function(){ total += parseInt($(this).val(), 10) || 0; });
                $('#dr-stat-trees').text(total.toLocaleString());
            }

        }(jQuery));
        </script>
        </div>
        <?php
    }
}
