<?php
/**
 * Admin settings page for Replanta Affiliates.
 *
 * @package Replanta_Affiliates
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raff_Settings {

    const MENU_SLUG = 'replanta-affiliates';

    /* ── Bootstrap ──────────────────────────────────────── */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'handle_save' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ) );
    }

    public static function enqueue_admin( $hook ) {
        if ( strpos( $hook, 'raff' ) !== false || strpos( $hook, 'replanta-affiliates' ) !== false ) {
            wp_enqueue_style( 'raff-admin', RAFF_URL . 'assets/css/admin.css', array(), RAFF_VERSION );
            wp_enqueue_script( 'raff-admin', RAFF_URL . 'assets/js/admin.js', array(), RAFF_VERSION, true );
        }
    }

    /* ── Menu ───────────────────────────────────────────── */
    public static function register_menu() {
        add_menu_page(
            __( 'Replanta Affiliates', 'replanta-affiliates' ),
            __( 'Afiliados', 'replanta-affiliates' ),
            'manage_options',
            self::MENU_SLUG,
            array( __CLASS__, 'render_page' ),
            'dashicons-groups',
            58
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Dashboard', 'replanta-affiliates' ),
            __( 'Dashboard', 'replanta-affiliates' ),
            'manage_options',
            self::MENU_SLUG,
            array( __CLASS__, 'render_page' )
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Afiliados', 'replanta-affiliates' ),
            __( 'Afiliados', 'replanta-affiliates' ),
            'manage_options',
            'raff-affiliates',
            array( 'Raff_Admin_Affiliates', 'render_page' )
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Ventas', 'replanta-affiliates' ),
            __( 'Ventas', 'replanta-affiliates' ),
            'manage_options',
            'raff-sales',
            array( 'Raff_Admin_Sales', 'render_page' )
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Pagos', 'replanta-affiliates' ),
            __( 'Pagos', 'replanta-affiliates' ),
            'manage_options',
            'raff-payouts',
            array( 'Raff_Admin_Payouts', 'render_page' )
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Configuración', 'replanta-affiliates' ),
            __( 'Configuración', 'replanta-affiliates' ),
            'manage_options',
            self::MENU_SLUG . '-settings',
            array( __CLASS__, 'render_settings' )
        );
    }

    /* ── Dashboard page ─────────────────────────────────── */
    public static function render_page() {
        $total       = Raff_DB::count_affiliates();
        $pending     = Raff_DB::count_affiliates( 'pending' );
        $active      = Raff_DB::count_affiliates( 'active' );
        $recent      = Raff_DB::list_affiliates( array( 'per_page' => 5 ) );
        $financial   = Raff_DB::get_admin_financial_summary();

        ?>
        <div class="wrap">
            <h1><span class="dashicons dashicons-groups" style="font-size:1.2em;margin-right:8px;"></span>
                <?php esc_html_e( 'Replanta Affiliates', 'replanta-affiliates' ); ?>
            </h1>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin:24px 0;">
                <?php self::kpi_card( __( 'Total afiliados', 'replanta-affiliates' ), $total, '#2271b1' ); ?>
                <?php self::kpi_card( __( 'Pendientes', 'replanta-affiliates' ), $pending, '#dba617' ); ?>
                <?php self::kpi_card( __( 'Activos', 'replanta-affiliates' ), $active, '#00a32a' ); ?>
            </div>

            <h2><?php esc_html_e( 'Resumen financiero', 'replanta-affiliates' ); ?></h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin:12px 0 24px;">
                <?php self::kpi_card( __( 'Comisión pendiente', 'replanta-affiliates' ), number_format( (float) $financial['sales_pending'], 2, ',', '.' ) . '€', '#dba617' ); ?>
                <?php self::kpi_card( __( 'Comisión confirmada', 'replanta-affiliates' ), number_format( (float) $financial['sales_confirmed'], 2, ',', '.' ) . '€', '#2271b1' ); ?>
                <?php self::kpi_card( __( 'Pagos solicitados', 'replanta-affiliates' ), number_format( (float) $financial['payout_requested'], 2, ',', '.' ) . '€', '#8c4cd1' ); ?>
                <?php self::kpi_card( __( 'Pagos procesando', 'replanta-affiliates' ), number_format( (float) $financial['payout_processing'], 2, ',', '.' ) . '€', '#0a7f88' ); ?>
                <?php self::kpi_card( __( 'Total neto pagado', 'replanta-affiliates' ), number_format( (float) $financial['payout_paid'], 2, ',', '.' ) . '€', '#00a32a' ); ?>
            </div>

            <?php if ( $recent ) : ?>
            <h2><?php esc_html_e( 'Últimas solicitudes', 'replanta-affiliates' ); ?></h2>
            <table class="widefat striped" style="max-width:800px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Nombre', 'replanta-affiliates' ); ?></th>
                        <th><?php esc_html_e( 'Email', 'replanta-affiliates' ); ?></th>
                        <th><?php esc_html_e( 'Ref', 'replanta-affiliates' ); ?></th>
                        <th><?php esc_html_e( 'Estado', 'replanta-affiliates' ); ?></th>
                        <th><?php esc_html_e( 'Fecha', 'replanta-affiliates' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $recent as $aff ) : ?>
                    <tr>
                        <td><?php echo esc_html( $aff->name ); ?></td>
                        <td><?php echo esc_html( $aff->email ); ?></td>
                        <td><code><?php echo esc_html( $aff->ref_code ); ?></code></td>
                        <td><?php echo esc_html( ucfirst( $aff->status ) ); ?></td>
                        <td><?php echo esc_html( wp_date( 'd/m/Y', strtotime( $aff->created_at ) ) ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /* ── Settings page ──────────────────────────────────── */
    public static function render_settings() {
        $fields = self::settings_fields();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Configuración — Replanta Affiliates', 'replanta-affiliates' ); ?></h1>

            <?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Configuración guardada.', 'replanta-affiliates' ); ?></p></div>
            <?php endif; ?>

            <form method="post" action="">
                <?php wp_nonce_field( 'raff_save_settings', 'raff_nonce' ); ?>
                <table class="form-table" role="presentation">
                    <?php foreach ( $fields as $section_label => $section_fields ) : ?>
                        <tr><th colspan="2"><h2><?php echo esc_html( $section_label ); ?></h2></th></tr>
                        <?php foreach ( $section_fields as $key => $field ) : ?>
                        <tr>
                            <th scope="row"><label for="raff_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label></th>
                            <td>
                                <?php
                                $val = Raff_DB::get_setting( $key, $field['default'] ?? '' );
                                switch ( $field['type'] ) {
                                    case 'number':
                                        printf(
                                            '<input type="number" id="raff_%1$s" name="raff[%1$s]" value="%2$s" class="regular-text" step="%3$s" min="0" />',
                                            esc_attr( $key ),
                                            esc_attr( $val ),
                                            esc_attr( $field['step'] ?? '1' )
                                        );
                                        break;
                                    case 'textarea':
                                        printf(
                                            '<textarea id="raff_%1$s" name="raff[%1$s]" rows="3" class="large-text">%2$s</textarea>',
                                            esc_attr( $key ),
                                            esc_textarea( $val )
                                        );
                                        break;
                                    default: // text
                                        printf(
                                            '<input type="text" id="raff_%1$s" name="raff[%1$s]" value="%2$s" class="regular-text" />',
                                            esc_attr( $key ),
                                            esc_attr( $val )
                                        );
                                }
                                if ( ! empty( $field['desc'] ) ) {
                                    echo '<p class="description">' . esc_html( $field['desc'] ) . '</p>';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </table>
                <?php submit_button( __( 'Guardar configuración', 'replanta-affiliates' ) ); ?>
            </form>
        </div>
        <?php
    }

    /* ── Save handler ───────────────────────────────────── */
    public static function handle_save() {
        if ( ! isset( $_POST['raff_nonce'] ) ) {
            return;
        }
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['raff_nonce'] ) ), 'raff_save_settings' ) ) {
            wp_die( 'Security check failed.' );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized.' );
        }

        $fields = self::settings_fields();
        $data   = isset( $_POST['raff'] ) ? wp_unslash( $_POST['raff'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

        foreach ( $fields as $section_fields ) {
            foreach ( $section_fields as $key => $field ) {
                if ( ! isset( $data[ $key ] ) ) {
                    continue;
                }
                $value = $data[ $key ];
                switch ( $field['type'] ) {
                    case 'number':
                        $value = floatval( $value );
                        break;
                    default:
                        $value = sanitize_text_field( $value );
                }
                Raff_DB::set_setting( $key, $value );
            }
        }

        wp_safe_redirect( add_query_arg( 'saved', '1', wp_get_referer() ) );
        exit;
    }

    /* ── Fields definition ──────────────────────────────── */
    private static function settings_fields() {
        return array(
            __( 'Comisiones', 'replanta-affiliates' ) => array(
                'default_commission_pct' => array(
                    'label'   => __( 'Comisión por defecto (%)', 'replanta-affiliates' ),
                    'type'    => 'number',
                    'step'    => '0.01',
                    'default' => '20',
                    'desc'    => __( 'Se aplica a nuevos afiliados. Cada afiliado puede tener una comisión personalizada.', 'replanta-affiliates' ),
                ),
                'confirmation_days' => array(
                    'label'   => __( 'Días para confirmar venta', 'replanta-affiliates' ),
                    'type'    => 'number',
                    'default' => '30',
                    'desc'    => __( 'Las ventas pasan a "confirmada" automáticamente pasados estos días (plazo de garantía).', 'replanta-affiliates' ),
                ),
            ),
            __( 'Cookie y tracking', 'replanta-affiliates' ) => array(
                'dashboard_path' => array(
                    'label'   => __( 'Ruta del dashboard de afiliados', 'replanta-affiliates' ),
                    'type'    => 'text',
                    'default' => '/afiliados/dashboard/',
                    'desc'    => __( 'Ruta relativa a la URL raíz donde está publicado el shortcode [replanta_affiliate_dashboard].', 'replanta-affiliates' ),
                ),
                'cookie_days' => array(
                    'label'   => __( 'Duración de cookie (días)', 'replanta-affiliates' ),
                    'type'    => 'number',
                    'default' => '90',
                ),
                'checkout_host' => array(
                    'label'   => __( 'Host checkout para inyección de cupón', 'replanta-affiliates' ),
                    'type'    => 'text',
                    'default' => 'clientes.replanta.net',
                    'desc'    => __( 'Dominio del checkout donde se añade automáticamente &coupons=CODIGO.', 'replanta-affiliates' ),
                ),
            ),
            __( 'Pagos', 'replanta-affiliates' ) => array(
                'payout_threshold' => array(
                    'label'   => __( 'Umbral mínimo de pago (€)', 'replanta-affiliates' ),
                    'type'    => 'number',
                    'step'    => '0.01',
                    'default' => '50',
                ),
                'paypal_fee_pct' => array(
                    'label'   => __( 'Comisión PayPal (%)', 'replanta-affiliates' ),
                    'type'    => 'number',
                    'step'    => '0.01',
                    'default' => '3.49',
                ),
                'paypal_fee_fixed' => array(
                    'label'   => __( 'Comisión PayPal fija (€)', 'replanta-affiliates' ),
                    'type'    => 'number',
                    'step'    => '0.01',
                    'default' => '0.49',
                ),
                'bank_fee_sepa' => array(
                    'label'   => __( 'Comisión transferencia SEPA (€)', 'replanta-affiliates' ),
                    'type'    => 'number',
                    'step'    => '0.01',
                    'default' => '0',
                ),
                'bank_fee_intl' => array(
                    'label'   => __( 'Comisión transferencia internacional (€)', 'replanta-affiliates' ),
                    'type'    => 'number',
                    'step'    => '0.01',
                    'default' => '3',
                ),
            ),
            __( 'Datos empresa (facturas)', 'replanta-affiliates' ) => array(
                'company_name' => array(
                    'label'   => __( 'Nombre empresa', 'replanta-affiliates' ),
                    'type'    => 'text',
                    'default' => 'Replanta',
                ),
                'company_cif' => array(
                    'label'   => __( 'CIF / NIF', 'replanta-affiliates' ),
                    'type'    => 'text',
                    'default' => '',
                ),
                'company_address' => array(
                    'label'   => __( 'Dirección fiscal', 'replanta-affiliates' ),
                    'type'    => 'textarea',
                    'default' => '',
                ),
                'admin_email'  => array(
                    'label'   => __( 'Email notificaciones admin', 'replanta-affiliates' ),
                    'type'    => 'text',
                    'default' => '',
                    'desc'    => __( 'Recibe avisos de nuevas solicitudes y pagos.', 'replanta-affiliates' ),
                ),
            ),
        );
    }

    /* ── Helper: KPI card ───────────────────────────────── */
    private static function kpi_card( $label, $value, $color ) {
        printf(
            '<div style="background:#fff;border:1px solid #e0e0e0;border-top:3px solid %3$s;border-radius:8px;padding:20px;text-align:center;">
                <div style="font-size:2rem;font-weight:700;color:%3$s;">%2$s</div>
                <div style="color:#666;margin-top:4px;">%1$s</div>
            </div>',
            esc_html( $label ),
            esc_html( $value ),
            esc_attr( $color )
        );
    }
}
