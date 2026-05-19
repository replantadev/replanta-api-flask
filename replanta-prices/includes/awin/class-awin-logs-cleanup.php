<?php
/**
 * AWIN Logs Cleanup & Maintenance Tool
 * 
 * Proporciona herramientas para limpiar, visualizar, y exportar logs de AWIN
 * con granularidad total (por fecha, tipo de evento, estado).
 *
 * @package Replanta_Prices
 * @subpackage Awin
 */

defined( 'ABSPATH' ) || exit;

class Replanta_Awin_Logs_Cleanup {

    /**
     * Initialize admin hooks.
     */
    public static function init() {
        if ( ! is_admin() ) {
            return;
        }

        add_action( 'admin_init', array( __CLASS__, 'handle_cleanup_actions' ) );
    }

    /**
     * Get table statistics (size, count, breakdown by type).
     */
    public static function get_table_stats() {
        global $wpdb;

        $table = Replanta_Awin_Logger::get_table_name();

        $stats = array(
            'total_events'    => 0,
            'table_size_kb'   => 0,
            'date_oldest'     => null,
            'date_newest'     => null,
            'breakdown'       => array(),
        );

        // Total count
        $stats['total_events'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

        // Table size
        $size_result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT ROUND(((data_length + index_length) / 1024), 2) AS size_kb 
                 FROM information_schema.TABLES 
                 WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $wpdb->prefix . 'replanta_awin_events'
            ),
            ARRAY_A
        );
        if ( $size_result ) {
            $stats['table_size_kb'] = (float) $size_result['size_kb'];
        }

        // Date range
        $dates = $wpdb->get_row(
            "SELECT MIN(created_at) as oldest, MAX(created_at) as newest FROM {$table}",
            ARRAY_A
        );
        if ( $dates ) {
            $stats['date_oldest'] = $dates['oldest'];
            $stats['date_newest'] = $dates['newest'];
        }

        // Breakdown by event type
        $breakdown = $wpdb->get_results(
            "SELECT event_type, COUNT(*) as count, event_status FROM {$table} 
             GROUP BY event_type, event_status 
             ORDER BY count DESC",
            ARRAY_A
        );
        foreach ( $breakdown as $row ) {
            $key = $row['event_type'];
            if ( ! isset( $stats['breakdown'][ $key ] ) ) {
                $stats['breakdown'][ $key ] = array( 'total' => 0, 'success' => 0, 'error' => 0, 'pending' => 0 );
            }
            $stats['breakdown'][ $key ]['total']                  += (int) $row['count'];
            $stats['breakdown'][ $key ][ $row['event_status'] ] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Delete events older than X days.
     */
    public static function cleanup_by_age( $days = 30 ) {
        global $wpdb;

        $days  = max( 7, absint( $days ) );
        $table = Replanta_Awin_Logger::get_table_name();
        $cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < %s",
                $cutoff
            )
        );

        return (int) $deleted;
    }

    /**
     * Delete events by status.
     */
    public static function cleanup_by_status( $status = 'error' ) {
        global $wpdb;

        $status = sanitize_key( $status );
        $table  = Replanta_Awin_Logger::get_table_name();

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE event_status = %s",
                $status
            )
        );

        return (int) $deleted;
    }

    /**
     * Get events for export.
     */
    public static function get_events_for_export( $filters = array() ) {
        global $wpdb;

        $table = Replanta_Awin_Logger::get_table_name();

        $query = "SELECT * FROM {$table} WHERE 1=1";
        $params = array();

        if ( ! empty( $filters['event_type'] ) ) {
            $query .= " AND event_type = %s";
            $params[] = $filters['event_type'];
        }

        if ( ! empty( $filters['event_status'] ) ) {
            $query .= " AND event_status = %s";
            $params[] = $filters['event_status'];
        }

        if ( ! empty( $filters['date_from'] ) ) {
            $query .= " AND created_at >= %s";
            $params[] = $filters['date_from'];
        }

        if ( ! empty( $filters['date_to'] ) ) {
            $query .= " AND created_at <= %s";
            $params[] = $filters['date_to'];
        }

        $query .= " ORDER BY created_at DESC LIMIT 5000";

        $events = $wpdb->get_results(
            empty( $params ) ? $query : $wpdb->prepare( $query, ...$params ),
            ARRAY_A
        );

        return $events ?: array();
    }

    /**
     * Export events as CSV.
     */
    public static function export_csv( $filters = array() ) {
        $events = self::get_events_for_export( $filters );

        if ( empty( $events ) ) {
            return '';
        }

        $output = fopen( 'php://temp', 'r+' );

        // Header
        fputcsv( $output, array(
            'Event ID',
            'Event Type',
            'Status',
            'AWC',
            'Reference',
            'Amount',
            'Currency',
            'IP Hash',
            'Created At',
            'Payload',
            'Metadata',
        ) );

        // Data
        foreach ( $events as $event ) {
            fputcsv( $output, array(
                $event['id'],
                $event['event_type'],
                $event['event_status'],
                $event['awc'],
                $event['reference'],
                $event['amount'],
                $event['currency'],
                $event['ip_hash'],
                $event['created_at'],
                $event['payload'],
                $event['metadata'],
            ) );
        }

        rewind( $output );
        $csv = stream_get_contents( $output );
        fclose( $output );

        return $csv;
    }

    /**
     * Handle cleanup form submissions.
     */
    public static function handle_cleanup_actions() {
        if ( ! isset( $_POST['replanta_awin_cleanup_action'] ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        if ( ! check_admin_referer( 'replanta_awin_cleanup_nonce' ) ) {
            wp_die( 'Invalid nonce' );
        }

        $action = sanitize_key( $_POST['replanta_awin_cleanup_action'] );
        $deleted = 0;

        switch ( $action ) {
            case 'cleanup_by_age':
                $days = isset( $_POST['cleanup_days'] ) ? absint( $_POST['cleanup_days'] ) : 30;
                $deleted = self::cleanup_by_age( $days );
                add_settings_error(
                    'replanta_prices',
                    'cleanup_by_age',
                    sprintf(
                        __( 'Limpieza completada. %d eventos con más de %d días eliminados.', 'replanta-prices' ),
                        $deleted,
                        $days
                    ),
                    'success'
                );
                break;

            case 'cleanup_by_status':
                $status = sanitize_key( $_POST['cleanup_status'] ?? 'error' );
                $deleted = self::cleanup_by_status( $status );
                add_settings_error(
                    'replanta_prices',
                    'cleanup_by_status',
                    sprintf(
                        __( 'Limpieza completada. %d eventos con estado "%s" eliminados.', 'replanta-prices' ),
                        $deleted,
                        $status
                    ),
                    'success'
                );
                break;

            case 'reset_analytics_data':
                Replanta_Prices_Awin_Analytics::clear_analytics_data();
                add_settings_error(
                    'replanta_prices',
                    'reset_analytics_data',
                    __( 'Analíticas AWIN reiniciadas. Métricas, cola S2S e historial eliminados.', 'replanta-prices' ),
                    'success'
                );
                break;

            case 'export_csv':
                $filters = array(
                    'event_type'  => sanitize_key( $_POST['export_event_type'] ?? '' ),
                    'event_status' => sanitize_key( $_POST['export_status'] ?? '' ),
                    'date_from'   => isset( $_POST['export_date_from'] ) ? sanitize_text_field( $_POST['export_date_from'] ) . ' 00:00:00' : '',
                    'date_to'     => isset( $_POST['export_date_to'] ) ? sanitize_text_field( $_POST['export_date_to'] ) . ' 23:59:59' : '',
                );

                $csv = self::export_csv( $filters );

                header( 'Content-Type: text/csv; charset=utf-8' );
                header( 'Content-Disposition: attachment; filename="awin-logs-' . gmdate( 'Y-m-d-H-i-s' ) . '.csv"' );
                header( 'Pragma: no-cache' );
                header( 'Expires: 0' );

                echo $csv;
                exit;
        }
    }

    /**
     * Render cleanup section inline (for embedding in other admin pages).
     */
    public static function render_cleanup_section() {
        $stats = self::get_table_stats();
        ?>
        
        <!-- Stats Box -->
        <div style="background: #f5f5f5; padding: 15px; margin-bottom: 15px; border-left: 4px solid #0073aa;">
            <p>
                <strong><?php esc_html_e( 'Total de eventos:', 'replanta-prices' ); ?></strong> 
                <?php echo esc_html( number_format_i18n( $stats['total_events'] ) ); ?> | 
                <strong><?php esc_html_e( 'Tamaño tabla:', 'replanta-prices' ); ?></strong> 
                <?php echo esc_html( number_format_i18n( $stats['table_size_kb'], 2 ) ); ?> KB
            </p>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">

            <!-- Cleanup by Age -->
            <div style="background: white; padding: 12px; border: 1px solid #ccc; border-radius: 4px;">
                <h4><?php esc_html_e( 'Eliminar por Antigüedad', 'replanta-prices' ); ?></h4>
                <form method="post" action="">
                    <?php wp_nonce_field( 'replanta_awin_cleanup_nonce' ); ?>
                    <input type="hidden" name="replanta_awin_cleanup_action" value="cleanup_by_age">
                    <label>
                        <?php esc_html_e( 'Días:', 'replanta-prices' ); ?><br>
                        <input type="number" name="cleanup_days" min="7" max="365" value="30" style="width: 70px;">
                    </label><br><br>
                    <button type="submit" class="button button-small" onclick="return confirm('<?php esc_attr_e( '¿Eliminar logs antiguos?', 'replanta-prices' ); ?>');">
                        <?php esc_html_e( 'Ejecutar', 'replanta-prices' ); ?>
                    </button>
                </form>
            </div>

            <!-- Cleanup by Status -->
            <div style="background: white; padding: 12px; border: 1px solid #ccc; border-radius: 4px;">
                <h4><?php esc_html_e( 'Eliminar por Estado', 'replanta-prices' ); ?></h4>
                <form method="post" action="">
                    <?php wp_nonce_field( 'replanta_awin_cleanup_nonce' ); ?>
                    <input type="hidden" name="replanta_awin_cleanup_action" value="cleanup_by_status">
                    <label>
                        <select name="cleanup_status" style="width: 100%;">
                            <option value="error"><?php esc_html_e( 'Error', 'replanta-prices' ); ?></option>
                            <option value="pending"><?php esc_html_e( 'Pending', 'replanta-prices' ); ?></option>
                        </select>
                    </label><br><br>
                    <button type="submit" class="button button-small" onclick="return confirm('<?php esc_attr_e( '¿Eliminar?', 'replanta-prices' ); ?>');">
                        <?php esc_html_e( 'Ejecutar', 'replanta-prices' ); ?>
                    </button>
                </form>
            </div>

        </div>

        <!-- Export CSV -->
        <div style="background: #f9f9f9; padding: 12px; border: 1px solid #ddd; border-radius: 4px; margin-top: 15px;">
            <h4><?php esc_html_e( 'Exportar CSV', 'replanta-prices' ); ?></h4>
            <form method="post" action="">
                <?php wp_nonce_field( 'replanta_awin_cleanup_nonce' ); ?>
                <input type="hidden" name="replanta_awin_cleanup_action" value="export_csv">
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                    <label>
                        <small><?php esc_html_e( 'Tipo evento:', 'replanta-prices' ); ?></small><br>
                        <select name="export_event_type" style="width: 100%; font-size: 12px;">
                            <option value=""><?php esc_html_e( 'Todos', 'replanta-prices' ); ?></option>
                            <option value="s2s_sent"><?php esc_html_e( 'S2S Enviado', 'replanta-prices' ); ?></option>
                            <option value="s2s_error"><?php esc_html_e( 'S2S Error', 'replanta-prices' ); ?></option>
                        </select>
                    </label>
                    <label>
                        <small><?php esc_html_e( 'Estado:', 'replanta-prices' ); ?></small><br>
                        <select name="export_status" style="width: 100%; font-size: 12px;">
                            <option value=""><?php esc_html_e( 'Todos', 'replanta-prices' ); ?></option>
                            <option value="success"><?php esc_html_e( 'Success', 'replanta-prices' ); ?></option>
                            <option value="error"><?php esc_html_e( 'Error', 'replanta-prices' ); ?></option>
                        </select>
                    </label>
                    <label>
                        <small><?php esc_html_e( 'Desde:', 'replanta-prices' ); ?></small><br>
                        <input type="date" name="export_date_from" style="width: 100%; font-size: 12px;">
                    </label>
                    <label>
                        <small><?php esc_html_e( 'Hasta:', 'replanta-prices' ); ?></small><br>
                        <input type="date" name="export_date_to" style="width: 100%; font-size: 12px;">
                    </label>
                </div>
                <button type="submit" class="button button-small">
                    <?php esc_html_e( 'Descargar CSV', 'replanta-prices' ); ?>
                </button>
            </form>
        </div>

        <div style="background: #fff7ed; padding: 12px; border: 1px solid #f59e0b; border-radius: 4px; margin-top: 15px;">
            <h4><?php esc_html_e( 'Reiniciar analíticas AWIN', 'replanta-prices' ); ?></h4>
            <p style="margin-top:0;">
                <?php esc_html_e( 'Borra las métricas acumuladas, la cola S2S y el historial de envíos de prueba que alimentan esta pestaña.', 'replanta-prices' ); ?>
            </p>
            <form method="post" action="">
                <?php wp_nonce_field( 'replanta_awin_cleanup_nonce' ); ?>
                <input type="hidden" name="replanta_awin_cleanup_action" value="reset_analytics_data">
                <button type="submit" class="button button-secondary" onclick="return confirm('<?php esc_attr_e( '¿Reiniciar analíticas AWIN? Esto borrará métricas y historial S2S.', 'replanta-prices' ); ?>');">
                    <?php esc_html_e( 'Reiniciar analíticas', 'replanta-prices' ); ?>
                </button>
            </form>
        </div>

        <?php
    }
}

// Auto-init
Replanta_Awin_Logs_Cleanup::init();
