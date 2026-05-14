<?php
/**
 * Awin Logger - Stores events in a custom database table.
 *
 * @package Replanta_Prices
 * @subpackage Awin
 */

defined( 'ABSPATH' ) || exit;

class Replanta_Awin_Logger {

    /** @var string Table name (without prefix) */
    const TABLE_NAME = 'replanta_awin_events';

    /** @var string Database version for migrations */
    const DB_VERSION = '1.0';

    /** @var string Option key for DB version */
    const DB_VERSION_OPTION = 'replanta_awin_db_version';

    /**
     * Event types.
     */
    const EVENT_AWC_CAPTURED    = 'awc_captured';
    const EVENT_URL_MODIFIED    = 'url_modified';
    const EVENT_WEBHOOK_RECEIVED = 'webhook_received';
    const EVENT_WEBHOOK_ERROR   = 'webhook_error';
    const EVENT_CONVERSION_READY = 'conversion_ready';
    const EVENT_S2S_SENT        = 's2s_sent';
    const EVENT_S2S_ERROR       = 's2s_error';

    /**
     * Event statuses.
     */
    const STATUS_SUCCESS = 'success';
    const STATUS_ERROR   = 'error';
    const STATUS_PENDING = 'pending';

    /**
     * Initialize hooks.
     */
    public static function init() {
        // Run migration check on admin init
        add_action( 'admin_init', array( __CLASS__, 'maybe_upgrade_db' ) );
    }

    /**
     * Get full table name with prefix.
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Create or upgrade the events table.
     */
    public static function create_table() {
        global $wpdb;

        $table_name      = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            event_status varchar(20) NOT NULL DEFAULT 'success',
            awc varchar(200) DEFAULT NULL,
            reference varchar(100) DEFAULT NULL,
            amount decimal(12,2) DEFAULT NULL,
            currency varchar(10) DEFAULT NULL,
            payload longtext DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            ip_hash varchar(32) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_event_type (event_type),
            KEY idx_awc (awc),
            KEY idx_reference (reference),
            KEY idx_created_at (created_at),
            KEY idx_status (event_status)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
    }

    /**
     * Check and run migrations if needed.
     */
    public static function maybe_upgrade_db() {
        $current_version = get_option( self::DB_VERSION_OPTION, '0' );
        
        if ( version_compare( $current_version, self::DB_VERSION, '<' ) ) {
            self::create_table();
        }
    }

    /**
     * Log an event.
     *
     * @param string $event_type Event type constant
     * @param array  $data       Event data
     * @return int|false Insert ID or false on failure
     */
    public static function log_event( $event_type, $data = array() ) {
        global $wpdb;

        $settings = Replanta_Awin_Cookie::get_settings();
        
        // Skip if detailed logging is disabled and it's a minor event
        $minor_events = array( self::EVENT_URL_MODIFIED );
        if ( empty( $settings['detailed_logs'] ) && in_array( $event_type, $minor_events, true ) ) {
            return false;
        }

        $table = self::get_table_name();

        $insert_data = array(
            'event_type'   => $event_type,
            'event_status' => isset( $data['status'] ) ? $data['status'] : self::STATUS_SUCCESS,
            'awc'          => isset( $data['awc'] ) ? substr( $data['awc'], 0, 200 ) : null,
            'reference'    => isset( $data['reference'] ) ? substr( $data['reference'], 0, 100 ) : null,
            'amount'       => isset( $data['amount'] ) ? floatval( $data['amount'] ) : null,
            'currency'     => isset( $data['currency'] ) ? strtoupper( substr( $data['currency'], 0, 10 ) ) : null,
            'ip_hash'      => isset( $data['ip_hash'] ) ? $data['ip_hash'] : null,
            'created_at'   => current_time( 'mysql' ),
        );

        // Store payload and metadata as JSON
        if ( isset( $data['payload'] ) ) {
            $insert_data['payload'] = wp_json_encode( $data['payload'] );
        }

        $metadata = $data;
        unset( $metadata['status'], $metadata['awc'], $metadata['reference'], 
               $metadata['amount'], $metadata['currency'], $metadata['ip_hash'], $metadata['payload'] );
        
        if ( ! empty( $metadata ) ) {
            $insert_data['metadata'] = wp_json_encode( $metadata );
        }

        $result = $wpdb->insert( $table, $insert_data );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get events with optional filtering.
     *
     * @param array $args Query arguments
     * @return array
     */
    public static function get_events( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'event_type' => null,
            'status'     => null,
            'awc'        => null,
            'reference'  => null,
            'limit'      => 50,
            'offset'     => 0,
            'orderby'    => 'created_at',
            'order'      => 'DESC',
            'date_from'  => null,
            'date_to'    => null,
        );

        $args  = wp_parse_args( $args, $defaults );
        $table = self::get_table_name();

        $where  = array( '1=1' );
        $values = array();

        if ( $args['event_type'] ) {
            $where[]  = 'event_type = %s';
            $values[] = $args['event_type'];
        }

        if ( $args['status'] ) {
            $where[]  = 'event_status = %s';
            $values[] = $args['status'];
        }

        if ( $args['awc'] ) {
            $where[]  = 'awc = %s';
            $values[] = $args['awc'];
        }

        if ( $args['reference'] ) {
            $where[]  = 'reference = %s';
            $values[] = $args['reference'];
        }

        if ( $args['date_from'] ) {
            $where[]  = 'created_at >= %s';
            $values[] = $args['date_from'];
        }

        if ( $args['date_to'] ) {
            $where[]  = 'created_at <= %s';
            $values[] = $args['date_to'];
        }

        $where_sql = implode( ' AND ', $where );

        // Sanitize orderby
        $allowed_orderby = array( 'id', 'event_type', 'event_status', 'created_at', 'amount' );
        $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
        $order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        $limit  = absint( $args['limit'] );
        $offset = absint( $args['offset'] );

        $sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT {$limit} OFFSET {$offset}";

        if ( ! empty( $values ) ) {
            $sql = $wpdb->prepare( $sql, $values );
        }

        return $wpdb->get_results( $sql, ARRAY_A );
    }

    /**
     * Count events with optional filtering.
     *
     * @param array $args Query arguments
     * @return int
     */
    public static function count_events( $args = array() ) {
        global $wpdb;

        $table = self::get_table_name();

        $where  = array( '1=1' );
        $values = array();

        if ( ! empty( $args['event_type'] ) ) {
            $where[]  = 'event_type = %s';
            $values[] = $args['event_type'];
        }

        if ( ! empty( $args['status'] ) ) {
            $where[]  = 'event_status = %s';
            $values[] = $args['status'];
        }

        $where_sql = implode( ' AND ', $where );
        $sql       = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";

        if ( ! empty( $values ) ) {
            $sql = $wpdb->prepare( $sql, $values );
        }

        return (int) $wpdb->get_var( $sql );
    }

    /**
     * Get statistics summary.
     *
     * @return array
     */
    public static function get_stats() {
        global $wpdb;
        $table = self::get_table_name();

        // Check if table exists
        $table_exists = $wpdb->get_var( $wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table
        ) );

        if ( ! $table_exists ) {
            return array(
                'total_awc_captures'   => 0,
                'total_url_clicks'     => 0,
                'total_webhooks'       => 0,
                'webhooks_success'     => 0,
                'webhooks_error'       => 0,
                'conversions_pending'  => 0,
                'conversions_sent'     => 0,
                'last_event'           => null,
                'last_webhook'         => null,
            );
        }

        $stats = array();

        // AWC captures
        $stats['total_awc_captures'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE event_type = 'awc_captured'"
        );

        // URL modifications (clicks to Upmind with AWC)
        $stats['total_url_clicks'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE event_type = 'url_modified'"
        );

        // Webhooks
        $stats['total_webhooks'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE event_type IN ('webhook_received', 'webhook_error')"
        );

        $stats['webhooks_success'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE event_type = 'webhook_received' AND event_status = 'success'"
        );

        $stats['webhooks_error'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE event_type = 'webhook_error' OR (event_type = 'webhook_received' AND event_status = 'error')"
        );

        // Conversions
        $stats['conversions_pending'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE event_type = 'conversion_ready' AND event_status = 'pending'"
        );

        $stats['conversions_sent'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE event_type = 's2s_sent' AND event_status = 'success'"
        );

        $stats['s2s_errors'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE event_type = 's2s_error'"
        );

        $stats['conversions_skipped'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE event_type = 'conversion_not_attributed'"
        );

        // Recurring payments ignored (not reported to Awin)
        $stats['recurring_ignored'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE event_type = 'webhook_ignored_recurring'"
        );

        // Duplicate customers skipped
        $stats['duplicate_customers'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE event_type = 'conversion_duplicate_customer'"
        );

        // Last events
        $stats['last_event'] = $wpdb->get_var(
            "SELECT created_at FROM {$table} ORDER BY created_at DESC LIMIT 1"
        );

        $stats['last_webhook'] = $wpdb->get_var(
            "SELECT created_at FROM {$table} WHERE event_type LIKE 'webhook%' ORDER BY created_at DESC LIMIT 1"
        );

        return $stats;
    }

    /**
     * Get single event by ID.
     *
     * @param int $id Event ID
     * @return array|null
     */
    public static function get_event( $id ) {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
            ARRAY_A
        );
    }

    /**
     * Update event status.
     *
     * @param int    $id     Event ID
     * @param string $status New status
     * @return bool
     */
    public static function update_status( $id, $status ) {
        global $wpdb;
        $table = self::get_table_name();

        return (bool) $wpdb->update(
            $table,
            array(
                'event_status' => $status,
                'processed_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $id ),
            array( '%s', '%s' ),
            array( '%d' )
        );
    }

    /**
     * Delete old events based on retention policy.
     *
     * @return int Number of deleted rows
     */
    public static function cleanup_old_events() {
        global $wpdb;

        $settings       = Replanta_Awin_Cookie::get_settings();
        $retention_days = isset( $settings['log_retention_days'] ) ? absint( $settings['log_retention_days'] ) : 90;

        if ( $retention_days < 7 ) {
            $retention_days = 7; // Minimum 7 days
        }

        $table    = self::get_table_name();
        $cutoff   = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < %s",
                $cutoff
            )
        );

        return (int) $deleted;
    }

    /**
     * Drop the events table (for uninstall).
     */
    public static function drop_table() {
        global $wpdb;
        $table = self::get_table_name();
        $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
        delete_option( self::DB_VERSION_OPTION );
    }
}
