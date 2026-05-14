<?php
/**
 * Admin: Affiliate management (list, approve, edit, delete).
 *
 * @package Replanta_Affiliates
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raff_Admin_Affiliates {

    public static function init() {
        add_action( 'admin_post_raff_approve_affiliate', array( __CLASS__, 'handle_approve' ) );
        add_action( 'admin_post_raff_reject_affiliate', array( __CLASS__, 'handle_reject' ) );
        add_action( 'admin_post_raff_save_affiliate', array( __CLASS__, 'handle_save' ) );
        add_action( 'admin_post_raff_delete_affiliate', array( __CLASS__, 'handle_delete' ) );
        add_action( 'admin_post_raff_download_doc', array( __CLASS__, 'handle_download_doc' ) );
        add_action( 'admin_post_raff_assign_coupon', array( __CLASS__, 'handle_assign_coupon' ) );
        add_action( 'admin_post_raff_export_affiliates_csv', array( __CLASS__, 'handle_export_csv' ) );
        add_action( 'admin_post_raff_send_magic_link', array( __CLASS__, 'handle_send_magic_link' ) );
    }

    /* ── Page renderer ──────────────────────────────────── */
    public static function render_page() {
        $action = sanitize_text_field( $_GET['action'] ?? 'list' ); // phpcs:ignore WordPress.Security.NonceVerification
        $id     = intval( $_GET['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification

        switch ( $action ) {
            case 'edit':
                self::render_edit( $id );
                break;
            default:
                self::render_list();
                break;
        }
    }

    /* ── List view ──────────────────────────────────────── */
    private static function render_list() {
        $status   = sanitize_text_field( $_GET['status'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
        $search   = sanitize_text_field( $_GET['s'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
        $per_page = 20;
        $page     = max( 1, intval( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification

        $args = array(
            'per_page' => $per_page,
            'offset'   => ( $page - 1 ) * $per_page,
        );
        if ( $status ) {
            $args['status'] = $status;
        }
        if ( $search ) {
            $args['search'] = $search;
        }

        $affiliates = Raff_DB::list_affiliates( $args );
        $total      = Raff_DB::count_affiliates( $status );
        $msg_aff    = sanitize_text_field( $_GET['msg_aff'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
        $counts     = array(
            ''          => Raff_DB::count_affiliates(),
            'pending'   => Raff_DB::count_affiliates( 'pending' ),
            'approved'  => Raff_DB::count_affiliates( 'approved' ),
            'active'    => Raff_DB::count_affiliates( 'active' ),
            'suspended' => Raff_DB::count_affiliates( 'suspended' ),
        );
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Afiliados', 'replanta-affiliates' ); ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin-post.php?action=raff_export_affiliates_csv' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Exportar CSV', 'replanta-affiliates' ); ?></a>
            <hr class="wp-header-end" />

            <?php if ( 'magic_sent' === $msg_aff ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Enlace de acceso enviado correctamente.', 'replanta-affiliates' ); ?></p></div>
            <?php elseif ( 'magic_failed' === $msg_aff ) : ?>
                <div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Error al enviar el enlace de acceso. Comprueba la configuración SMTP.', 'replanta-affiliates' ); ?></p></div>
            <?php endif; ?>

            <!-- Status filters -->
            <ul class="subsubsub">
                <?php
                $labels = array(
                    ''          => __( 'Todos', 'replanta-affiliates' ),
                    'pending'   => __( 'Pendientes', 'replanta-affiliates' ),
                    'approved'  => __( 'Aprobados', 'replanta-affiliates' ),
                    'active'    => __( 'Activos', 'replanta-affiliates' ),
                    'suspended' => __( 'Suspendidos', 'replanta-affiliates' ),
                );
                $links = array();
                foreach ( $labels as $s => $label ) {
                    $url     = add_query_arg( array( 'page' => 'raff-affiliates', 'status' => $s ), admin_url( 'admin.php' ) );
                    $current = ( $status === $s ) ? ' class="current"' : '';
                    $links[] = sprintf(
                        '<li><a href="%s"%s>%s <span class="count">(%d)</span></a></li>',
                        esc_url( $url ),
                        $current,
                        esc_html( $label ),
                        intval( $counts[ $s ] ?? 0 )
                    );
                }
                echo implode( ' | ', $links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                ?>
            </ul>

            <!-- Search -->
            <form method="get" class="search-box">
                <input type="hidden" name="page" value="raff-affiliates" />
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Buscar afiliado...', 'replanta-affiliates' ); ?>" />
                <input type="submit" class="button" value="<?php esc_attr_e( 'Buscar', 'replanta-affiliates' ); ?>" />
            </form>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Nombre', 'replanta-affiliates' ); ?></th>
                        <th><?php esc_html_e( 'Email', 'replanta-affiliates' ); ?></th>
                        <th><?php esc_html_e( 'Código', 'replanta-affiliates' ); ?></th>
                        <th><?php esc_html_e( 'Cupón', 'replanta-affiliates' ); ?></th>
                        <th><?php esc_html_e( 'Comisión', 'replanta-affiliates' ); ?></th>
                        <th><?php esc_html_e( 'Estado', 'replanta-affiliates' ); ?></th>
                        <th><?php esc_html_e( 'Fecha alta', 'replanta-affiliates' ); ?></th>
                        <th><?php esc_html_e( 'Acciones', 'replanta-affiliates' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $affiliates ) ) : ?>
                        <tr><td colspan="8"><?php esc_html_e( 'No hay afiliados.', 'replanta-affiliates' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $affiliates as $aff ) : ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'raff-affiliates', 'action' => 'edit', 'id' => $aff->id ), admin_url( 'admin.php' ) ) ); ?>">
                                            <?php echo esc_html( $aff->name ); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td><?php echo esc_html( $aff->email ); ?></td>
                                <td><code><?php echo esc_html( $aff->ref_code ); ?></code></td>
                                <td><?php echo $aff->coupon_code ? '<code>' . esc_html( $aff->coupon_code ) . '</code>' : '&mdash;'; ?></td>
                                <td><?php echo esc_html( $aff->commission_pct ); ?>%</td>
                                <td><span class="raff-admin-badge raff-admin-badge--<?php echo esc_attr( $aff->status ); ?>"><?php echo esc_html( ucfirst( $aff->status ) ); ?></span></td>
                                <td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $aff->created_at ) ) ); ?></td>
                                <td>
                                    <?php if ( 'pending' === $aff->status ) : ?>
                                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=raff_approve_affiliate&id=' . $aff->id ), 'raff_approve_' . $aff->id ) ); ?>" class="button button-small button-primary"><?php esc_html_e( 'Aprobar', 'replanta-affiliates' ); ?></a>
                                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=raff_reject_affiliate&id=' . $aff->id ), 'raff_reject_' . $aff->id ) ); ?>" class="button button-small" onclick="return confirm('<?php esc_attr_e( '¿Rechazar este afiliado?', 'replanta-affiliates' ); ?>');"><?php esc_html_e( 'Rechazar', 'replanta-affiliates' ); ?></a>
                                    <?php endif; ?>
                                    <?php if ( in_array( $aff->status, array( 'approved', 'active' ), true ) ) : ?>
                                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=raff_send_magic_link&id=' . $aff->id ), 'raff_send_magic_' . $aff->id ) ); ?>" class="button button-small" title="<?php esc_attr_e( 'Enviar enlace de acceso al dashboard por email', 'replanta-affiliates' ); ?>"><?php esc_html_e( 'Enviar acceso', 'replanta-affiliates' ); ?></a>
                                    <?php endif; ?>
                                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'raff-affiliates', 'action' => 'edit', 'id' => $aff->id ), admin_url( 'admin.php' ) ) ); ?>" class="button button-small"><?php esc_html_e( 'Editar', 'replanta-affiliates' ); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php
            $total_pages = ceil( $total / $per_page );
            if ( $total_pages > 1 ) {
                echo '<div class="tablenav"><div class="tablenav-pages">';
                echo wp_kses_post( paginate_links( array(
                    'base'    => add_query_arg( 'paged', '%#%' ),
                    'format'  => '',
                    'current' => $page,
                    'total'   => $total_pages,
                ) ) );
                echo '</div></div>';
            }
            ?>
        </div>
        <?php
    }

    /* ── Edit single affiliate ──────────────────────────── */
    private static function render_edit( $id ) {
        $aff = Raff_DB::get_affiliate( $id );
        if ( ! $aff ) {
            wp_die( esc_html__( 'Afiliado no encontrado.', 'replanta-affiliates' ) );
        }

        $sales_count  = count( Raff_DB::list_sales( array( 'affiliate_id' => $id, 'per_page' => 999 ) ) );
        $balance      = Raff_DB::get_available_balance( $id );
        $visits       = Raff_DB::count_events( $id, 'visit' );
        $back_url     = add_query_arg( 'page', 'raff-affiliates', admin_url( 'admin.php' ) );
        ?>
        <div class="wrap">
            <h1>
                <a href="<?php echo esc_url( $back_url ); ?>">&larr;</a>
                <?php printf( esc_html__( 'Afiliado: %s', 'replanta-affiliates' ), esc_html( $aff->name ) ); ?>
            </h1>

            <div class="raff-admin-kpis">
                <div class="raff-admin-kpi"><strong><?php echo intval( $visits ); ?></strong><br><?php esc_html_e( 'Visitas', 'replanta-affiliates' ); ?></div>
                <div class="raff-admin-kpi"><strong><?php echo intval( $sales_count ); ?></strong><br><?php esc_html_e( 'Ventas', 'replanta-affiliates' ); ?></div>
                <div class="raff-admin-kpi"><strong><?php echo esc_html( number_format( $balance, 2, ',', '.' ) ); ?>€</strong><br><?php esc_html_e( 'Saldo', 'replanta-affiliates' ); ?></div>
            </div>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="raff_save_affiliate" />
                <input type="hidden" name="id" value="<?php echo intval( $aff->id ); ?>" />
                <?php wp_nonce_field( 'raff_save_affiliate_' . $aff->id ); ?>

                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Nombre', 'replanta-affiliates' ); ?></th>
                        <td><input type="text" name="name" value="<?php echo esc_attr( $aff->name ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Email', 'replanta-affiliates' ); ?></th>
                        <td><input type="email" name="email" value="<?php echo esc_attr( $aff->email ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Teléfono', 'replanta-affiliates' ); ?></th>
                        <td><input type="text" name="phone" value="<?php echo esc_attr( $aff->phone ?? '' ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'País', 'replanta-affiliates' ); ?></th>
                        <td><input type="text" name="country" value="<?php echo esc_attr( $aff->country ?? '' ); ?>" /></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Código de referido', 'replanta-affiliates' ); ?></th>
                        <td><code><?php echo esc_html( $aff->ref_code ); ?></code></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Cupón asignado', 'replanta-affiliates' ); ?></th>
                        <td>
                            <input type="text" name="coupon_code" value="<?php echo esc_attr( $aff->coupon_code ?? '' ); ?>" placeholder="LUISJA10" style="text-transform:uppercase;" />
                            <p class="description"><?php esc_html_e( 'Igual que el ref_code. Se usa para atribución en checkout.', 'replanta-affiliates' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Comisión %', 'replanta-affiliates' ); ?></th>
                        <td><input type="number" name="commission_pct" value="<?php echo esc_attr( $aff->commission_pct ); ?>" min="0" max="100" step="0.01" /></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Estado', 'replanta-affiliates' ); ?></th>
                        <td>
                            <select name="status">
                                <?php foreach ( array( 'pending', 'approved', 'active', 'suspended', 'rejected' ) as $s ) : ?>
                                    <option value="<?php echo esc_attr( $s ); ?>" <?php selected( $aff->status, $s ); ?>><?php echo esc_html( ucfirst( $s ) ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Web / Canal', 'replanta-affiliates' ); ?></th>
                        <td><input type="url" name="website" value="<?php echo esc_attr( $aff->website ?? '' ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Método de promoción', 'replanta-affiliates' ); ?></th>
                        <td><textarea name="promo_method" rows="3" class="large-text"><?php echo esc_textarea( $aff->promo_method ?? '' ); ?></textarea></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Documento', 'replanta-affiliates' ); ?></th>
                        <td>
                            <?php if ( ! empty( $aff->doc_file_path ) ) : ?>
                                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=raff_download_doc&id=' . $aff->id ), 'raff_download_doc_' . $aff->id ) ); ?>"><?php esc_html_e( 'Ver documento', 'replanta-affiliates' ); ?></a>
                                (<?php echo esc_html( ( $aff->doc_type ?? '' ) . ': ' . ( $aff->doc_number ?? '' ) ); ?>)
                            <?php else : ?>
                                <?php esc_html_e( 'No proporcionado', 'replanta-affiliates' ); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr><th colspan="2"><hr><h3 style="margin:0"><?php esc_html_e( 'Datos de pago', 'replanta-affiliates' ); ?></h3></th></tr>
                    <tr>
                        <th><?php esc_html_e( 'Método de pago', 'replanta-affiliates' ); ?></th>
                        <td>
                            <select name="payment_method">
                                <option value="paypal" <?php selected( $aff->payment_method ?? '', 'paypal' ); ?>>PayPal</option>
                                <option value="bank" <?php selected( $aff->payment_method ?? '', 'bank' ); ?>><?php esc_html_e( 'Transferencia bancaria', 'replanta-affiliates' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Email PayPal', 'replanta-affiliates' ); ?></th>
                        <td><input type="email" name="paypal_email" value="<?php echo esc_attr( $aff->paypal_email ?? '' ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'IBAN', 'replanta-affiliates' ); ?></th>
                        <td><input type="text" name="bank_iban" value="<?php echo esc_attr( $aff->bank_iban ?? '' ); ?>" class="regular-text" placeholder="ES00 0000 0000 0000 0000 0000" /></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Titular banco', 'replanta-affiliates' ); ?></th>
                        <td><input type="text" name="bank_name" value="<?php echo esc_attr( $aff->bank_name ?? '' ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr><th colspan="2"><hr><h3 style="margin:0"><?php esc_html_e( 'Admin', 'replanta-affiliates' ); ?></h3></th></tr>
                    <tr>
                        <th><?php esc_html_e( 'Notas internas', 'replanta-affiliates' ); ?></th>
                        <td><textarea name="notes" rows="4" class="large-text"><?php echo esc_textarea( $aff->notes ?? '' ); ?></textarea></td>
                    </tr>
                </table>

                <?php submit_button( __( 'Guardar cambios', 'replanta-affiliates' ) ); ?>
            </form>

            <hr />
            <p>
                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=raff_delete_affiliate&id=' . $aff->id ), 'raff_delete_' . $aff->id ) ); ?>" class="button button-link-delete" onclick="return confirm('<?php esc_attr_e( '¿Eliminar este afiliado permanentemente?', 'replanta-affiliates' ); ?>');">
                    <?php esc_html_e( 'Eliminar afiliado', 'replanta-affiliates' ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /* ── Action handlers ────────────────────────────────── */
    public static function handle_approve() {
        $id = intval( $_GET['id'] ?? 0 );
        check_admin_referer( 'raff_approve_' . $id );

        $aff = Raff_DB::get_affiliate( $id );
        if ( $aff ) {
            $update = array( 'status' => 'approved' );
            /* Auto-assign coupon_code = ref_code if not already set */
            if ( empty( $aff->coupon_code ) ) {
                $update['coupon_code'] = $aff->ref_code;
            }
            Raff_DB::update_affiliate( $id, $update );
            Raff_Email::send_approved( $aff );
        }

        wp_safe_redirect( add_query_arg( array( 'page' => 'raff-affiliates', 'msg' => 'approved' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function handle_reject() {
        $id = intval( $_GET['id'] ?? 0 );
        check_admin_referer( 'raff_reject_' . $id );

        Raff_DB::update_affiliate( $id, array( 'status' => 'rejected' ) );

        wp_safe_redirect( add_query_arg( array( 'page' => 'raff-affiliates', 'msg' => 'rejected' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function handle_save() {
        $id = intval( $_POST['id'] ?? 0 );
        check_admin_referer( 'raff_save_affiliate_' . $id );

        $text_fields = array( 'name', 'email', 'phone', 'country', 'status', 'website', 'promo_method', 'coupon_code', 'payment_method', 'paypal_email', 'bank_iban', 'bank_name', 'notes' );
        $update = array();
        foreach ( $text_fields as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                $value = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
                if ( 'coupon_code' === $field ) {
                    $value = strtoupper( $value );
                }
                $update[ $field ] = $value;
            }
        }
        if ( isset( $_POST['commission_pct'] ) ) {
            $update['commission_pct'] = floatval( $_POST['commission_pct'] );
        }

        Raff_DB::update_affiliate( $id, $update );

        wp_safe_redirect( add_query_arg( array( 'page' => 'raff-affiliates', 'action' => 'edit', 'id' => $id, 'msg' => 'saved' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function handle_delete() {
        $id = intval( $_GET['id'] ?? 0 );
        check_admin_referer( 'raff_delete_' . $id );

        global $wpdb;
        $table = $wpdb->prefix . 'raff_affiliates';
        $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

        wp_safe_redirect( add_query_arg( array( 'page' => 'raff-affiliates', 'msg' => 'deleted' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function handle_download_doc() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $id = intval( $_GET['id'] ?? 0 );
        check_admin_referer( 'raff_download_doc_' . $id );

        $aff = Raff_DB::get_affiliate( $id );
        if ( ! $aff || empty( $aff->doc_file_path ) ) {
            wp_die( esc_html__( 'Documento no encontrado.', 'replanta-affiliates' ) );
        }

        $doc_path = (string) $aff->doc_file_path;
        $real_path = '';

        if ( file_exists( $doc_path ) ) {
            $real_path = $doc_path;
        } else {
            $upload = wp_upload_dir();
            $candidate = trailingslashit( $upload['basedir'] ) . 'replanta-affiliates/docs/' . ltrim( basename( $doc_path ), '/\\' );
            if ( file_exists( $candidate ) ) {
                $real_path = $candidate;
            }
        }

        if ( '' === $real_path || ! is_readable( $real_path ) ) {
            wp_die( esc_html__( 'No se pudo leer el documento.', 'replanta-affiliates' ) );
        }

        $mime = function_exists( 'mime_content_type' ) ? mime_content_type( $real_path ) : 'application/octet-stream';
        $filename = basename( $real_path );

        nocache_headers();
        header( 'Content-Type: ' . $mime );
        header( 'Content-Disposition: inline; filename="' . $filename . '"' );
        header( 'Content-Length: ' . filesize( $real_path ) );
        readfile( $real_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
        exit;
    }

    public static function handle_assign_coupon() {
        $id = intval( $_POST['id'] ?? 0 );
        check_admin_referer( 'raff_assign_coupon_' . $id );

        $coupon = sanitize_text_field( wp_unslash( $_POST['coupon_code'] ?? '' ) );
        if ( $coupon ) {
            Raff_DB::update_affiliate( $id, array( 'coupon_code' => strtoupper( $coupon ) ) );
            $aff = Raff_DB::get_affiliate( $id );
            if ( $aff ) {
                Raff_Email::send_coupon_assigned( $aff );
            }
        }

        wp_safe_redirect( add_query_arg( array( 'page' => 'raff-affiliates', 'action' => 'edit', 'id' => $id, 'msg' => 'coupon_assigned' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function handle_export_csv() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $affiliates = Raff_DB::list_affiliates( array( 'per_page' => 9999 ) );

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=afiliados-' . gmdate( 'Y-m-d' ) . '.csv' );

        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, array( 'ID', 'Nombre', 'Email', 'Teléfono', 'País', 'Código', 'Cupón', 'Comisión %', 'Estado', 'Web', 'Fecha' ) );

        foreach ( $affiliates as $aff ) {
            fputcsv( $out, array(
                $aff->id,
                $aff->name,
                $aff->email,
                $aff->phone ?? '',
                $aff->country ?? '',
                $aff->ref_code,
                $aff->coupon_code ?? '',
                $aff->commission_pct,
                $aff->status,
                $aff->website ?? '',
                $aff->created_at,
            ) );
        }

        fclose( $out );
        exit;
    }

    public static function handle_send_magic_link() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $id = intval( $_REQUEST['id'] ?? 0 );
        check_admin_referer( 'raff_send_magic_' . $id );

        $aff = Raff_DB::get_affiliate( $id );
        $redirect_base = add_query_arg( 'page', 'raff-affiliates', admin_url( 'admin.php' ) );

        if ( ! $aff || ! in_array( $aff->status, array( 'approved', 'active' ), true ) ) {
            wp_safe_redirect( add_query_arg( 'msg_aff', 'magic_failed', $redirect_base ) );
            exit;
        }

        /* Generate fresh token (1h expiry) */
        $token = wp_generate_password( 48, false );
        Raff_DB::update_affiliate( $aff->id, array(
            'magic_token'     => $token,
            'magic_token_exp' => gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ),
        ) );

        $dashboard_path = Raff_DB::get_setting( 'dashboard_path', '/afiliados/dashboard/' );
        $dashboard_url  = add_query_arg( 'raff_token', $token, home_url( $dashboard_path ) );

        /* wp_mail returns bool — use it to detect delivery failure */
        $sent = Raff_Email::send_magic_link( $aff, $dashboard_url );

        $msg = $sent ? 'magic_sent' : 'magic_failed';
        wp_safe_redirect( add_query_arg( 'msg_aff', $msg, $redirect_base ) );
        exit;
    }
}
