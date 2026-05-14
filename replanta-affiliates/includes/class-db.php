<?php
/**
 * Database schema, migrations, and CRUD helpers.
 *
 * @package Replanta_Affiliates
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raff_DB {

    /* ── Table names (no prefix) ────────────────────────── */
    const T_AFFILIATES = 'raff_affiliates';
    const T_SALES      = 'raff_sales';
    const T_PAYOUTS    = 'raff_payouts';
    const T_PAYOUT_SALES = 'raff_payout_sales';
    const T_EVENTS     = 'raff_events';
    const T_SETTINGS   = 'raff_settings';

    const DB_VERSION   = '1.0.0';
    const DB_OPT_KEY   = 'raff_db_version';

    /* ── Helpers: full table name ───────────────────────── */
    public static function table( $name ) {
        global $wpdb;
        return $wpdb->prefix . $name;
    }

    /* ══════════════════════════════════════════════════════
     *  ACTIVATION — Create / Update tables
     * ══════════════════════════════════════════════════════ */
    public static function activate() {
        self::create_tables();
        self::seed_defaults();
        update_option( self::DB_OPT_KEY, self::DB_VERSION );
    }

    public static function maybe_upgrade() {
        if ( get_option( self::DB_OPT_KEY ) !== self::DB_VERSION ) {
            self::create_tables();
            update_option( self::DB_OPT_KEY, self::DB_VERSION );
        }
    }

    /* ── Schema ─────────────────────────────────────────── */
    private static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql = [];

        /* 1. Affiliates */
        $t = self::table( self::T_AFFILIATES );
        $sql[] = "CREATE TABLE {$t} (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id         BIGINT UNSIGNED DEFAULT NULL,
            name            VARCHAR(200)    NOT NULL,
            email           VARCHAR(200)    NOT NULL,
            phone           VARCHAR(40)     DEFAULT '',
            country         CHAR(2)         DEFAULT '',
            website         VARCHAR(500)    DEFAULT '',
            promo_method    TEXT            DEFAULT NULL,
            doc_type        ENUM('dni','nif','passport') DEFAULT 'dni',
            doc_number      VARCHAR(40)     DEFAULT '',
            doc_file_path   VARCHAR(500)    DEFAULT '',
            ref_code        VARCHAR(60)     NOT NULL,
            coupon_code     VARCHAR(60)     DEFAULT '',
            commission_pct  DECIMAL(5,2)    NOT NULL DEFAULT 20.00,
            payment_method  ENUM('paypal','bank') DEFAULT 'paypal',
            paypal_email    VARCHAR(200)    DEFAULT '',
            bank_iban       VARCHAR(60)     DEFAULT '',
            bank_name       VARCHAR(200)    DEFAULT '',
            magic_token     VARCHAR(64)     DEFAULT '',
            magic_token_exp DATETIME        DEFAULT NULL,
            status          ENUM('pending','approved','active','suspended','rejected') NOT NULL DEFAULT 'pending',
            notes           TEXT            DEFAULT NULL,
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_email   (email),
            UNIQUE KEY idx_ref     (ref_code),
            KEY idx_coupon (coupon_code),
            KEY idx_status (status)
        ) {$charset};";

        /* 2. Sales */
        $t = self::table( self::T_SALES );
        $sql[] = "CREATE TABLE {$t} (
            id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            affiliate_id     BIGINT UNSIGNED NOT NULL,
            order_id         VARCHAR(60)     NOT NULL,
            product_pid      VARCHAR(60)     DEFAULT '',
            plan_name        VARCHAR(120)    DEFAULT '',
            amount           DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
            currency         CHAR(3)         NOT NULL DEFAULT 'EUR',
            commission_pct   DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
            commission_amount DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
            voucher_used     VARCHAR(60)     DEFAULT '',
            attribution_type ENUM('cookie','voucher','both') NOT NULL DEFAULT 'cookie',
            source_url       VARCHAR(500)    DEFAULT '',
            status           ENUM('pending','confirmed','paid','cancelled') NOT NULL DEFAULT 'pending',
            attributed_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            confirmed_at     DATETIME        DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY idx_order  (order_id),
            KEY idx_affiliate (affiliate_id),
            KEY idx_status    (status),
            KEY idx_date      (attributed_at)
        ) {$charset};";

        /* 3. Payouts */
        $t = self::table( self::T_PAYOUTS );
        $sql[] = "CREATE TABLE {$t} (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            affiliate_id  BIGINT UNSIGNED NOT NULL,
            amount        DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
            currency      CHAR(3)         NOT NULL DEFAULT 'EUR',
            fee           DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
            net_amount    DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
            method        ENUM('paypal','bank') NOT NULL DEFAULT 'paypal',
            payment_ref   VARCHAR(200)    DEFAULT '',
            invoice_number VARCHAR(60)    DEFAULT '',
            status        ENUM('requested','processing','paid','rejected') NOT NULL DEFAULT 'requested',
            invoice_path  VARCHAR(500)    DEFAULT '',
            requested_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            paid_at       DATETIME        DEFAULT NULL,
            processed_at  DATETIME        DEFAULT NULL,
            notes         TEXT            DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_affiliate (affiliate_id),
            KEY idx_status    (status)
        ) {$charset};";

        /* 3b. Payout -> Sales mapping (prevents paying same sale twice) */
        $t = self::table( self::T_PAYOUT_SALES );
        $sql[] = "CREATE TABLE {$t} (
            id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            payout_id         BIGINT UNSIGNED NOT NULL,
            sale_id           BIGINT UNSIGNED NOT NULL,
            commission_amount DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
            created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_sale_unique (sale_id),
            UNIQUE KEY idx_payout_sale (payout_id, sale_id),
            KEY idx_payout (payout_id)
        ) {$charset};";

        /* 4. Events (visits, checkouts, purchases) */
        $t = self::table( self::T_EVENTS );
        $sql[] = "CREATE TABLE {$t} (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            affiliate_id BIGINT UNSIGNED NOT NULL,
            event_type   ENUM('visit','checkout','purchase') NOT NULL,
            ref_code     VARCHAR(60)  NOT NULL,
            ip_hash      CHAR(64)     DEFAULT '',
            url          VARCHAR(500) DEFAULT '',
            created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_affiliate (affiliate_id),
            KEY idx_type_date (event_type, created_at),
            KEY idx_dedup     (affiliate_id, ip_hash, created_at)
        ) {$charset};";

        /* 5. Settings (key/value) */
        $t = self::table( self::T_SETTINGS );
        $sql[] = "CREATE TABLE {$t} (
            setting_key   VARCHAR(100) NOT NULL,
            setting_value LONGTEXT     DEFAULT NULL,
            PRIMARY KEY (setting_key)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ( $sql as $query ) {
            dbDelta( $query );
        }
    }

    /* ── Default settings seed ──────────────────────────── */
    private static function seed_defaults() {
        $defaults = array(
            'default_commission_pct' => '20',
            'cookie_days'            => '90',
            'checkout_host'          => 'clientes.replanta.net',
            'payout_threshold'       => '50',
            'confirmation_days'      => '30',
            'paypal_fee_pct'         => '3.49',
            'paypal_fee_fixed'       => '0.49',
            'bank_fee_sepa'          => '0',
            'bank_fee_intl'          => '3',
            'dashboard_path'          => '/afiliados/dashboard/',
            'admin_email'            => get_option( 'admin_email' ),
            'company_name'           => 'Replanta',
            'company_cif'            => '',
            'company_address'        => '',
        );

        global $wpdb;
        $t = self::table( self::T_SETTINGS );

        foreach ( $defaults as $key => $value ) {
            $wpdb->query( $wpdb->prepare(
                "INSERT IGNORE INTO {$t} (setting_key, setting_value) VALUES (%s, %s)",
                $key,
                $value
            ) );
        }
    }

    /* ══════════════════════════════════════════════════════
     *  UNINSTALL — Drop everything
     * ══════════════════════════════════════════════════════ */
    public static function uninstall() {
        global $wpdb;
        $tables = array(
            self::T_EVENTS,
            self::T_SALES,
            self::T_PAYOUT_SALES,
            self::T_PAYOUTS,
            self::T_AFFILIATES,
            self::T_SETTINGS,
        );
        foreach ( $tables as $name ) {
            $wpdb->query( "DROP TABLE IF EXISTS " . self::table( $name ) ); // phpcs:ignore WordPress.DB.PreparedSQL
        }
        delete_option( self::DB_OPT_KEY );
    }

    /* ══════════════════════════════════════════════════════
     *  SETTINGS CRUD
     * ══════════════════════════════════════════════════════ */
    public static function get_setting( $key, $default = '' ) {
        global $wpdb;
        $val = $wpdb->get_var( $wpdb->prepare(
            "SELECT setting_value FROM " . self::table( self::T_SETTINGS ) . " WHERE setting_key = %s",
            $key
        ) );
        return null !== $val ? $val : $default;
    }

    public static function set_setting( $key, $value ) {
        global $wpdb;
        $wpdb->replace(
            self::table( self::T_SETTINGS ),
            array(
                'setting_key'   => $key,
                'setting_value' => $value,
            ),
            array( '%s', '%s' )
        );
    }

    /* ══════════════════════════════════════════════════════
     *  AFFILIATE CRUD
     * ══════════════════════════════════════════════════════ */
    public static function insert_affiliate( $data ) {
        global $wpdb;
        $wpdb->insert( self::table( self::T_AFFILIATES ), $data );
        return $wpdb->insert_id;
    }

    public static function get_affiliate( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::table( self::T_AFFILIATES ) . " WHERE id = %d",
            $id
        ) );
    }

    public static function get_affiliate_by_email( $email ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::table( self::T_AFFILIATES ) . " WHERE email = %s",
            $email
        ) );
    }

    public static function get_affiliate_by_ref( $ref_code ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::table( self::T_AFFILIATES ) . " WHERE ref_code = %s AND status IN ('approved','active')",
            $ref_code
        ) );
    }

    public static function get_affiliate_by_coupon( $coupon ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::table( self::T_AFFILIATES ) . " WHERE coupon_code = %s AND status IN ('approved','active')",
            $coupon
        ) );
    }

    public static function get_affiliate_by_token( $token ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::table( self::T_AFFILIATES ) . " WHERE magic_token = %s AND magic_token_exp > NOW()",
            $token
        ) );
    }

    public static function update_affiliate( $id, $data ) {
        global $wpdb;
        return $wpdb->update(
            self::table( self::T_AFFILIATES ),
            $data,
            array( 'id' => $id ),
            null,
            array( '%d' )
        );
    }

    public static function list_affiliates( $args = array() ) {
        global $wpdb;
        $t = self::table( self::T_AFFILIATES );

        $where  = '1=1';
        $params = array();

        if ( ! empty( $args['status'] ) ) {
            $where   .= ' AND status = %s';
            $params[] = $args['status'];
        }
        if ( ! empty( $args['search'] ) ) {
            $like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where   .= ' AND (name LIKE %s OR email LIKE %s)';
            $params[] = $like;
            $params[] = $like;
        }

        $order  = ! empty( $args['orderby'] ) ? sanitize_sql_orderby( $args['orderby'] ) : 'created_at DESC';
        if ( ! $order ) {
            $order = 'created_at DESC';
        }
        $limit  = isset( $args['per_page'] ) ? absint( $args['per_page'] ) : 20;
        $offset = isset( $args['offset'] )   ? absint( $args['offset'] )   : 0;

        $sql = "SELECT * FROM {$t} WHERE {$where} ORDER BY {$order} LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL
    }

    public static function count_affiliates( $status = '' ) {
        global $wpdb;
        $t = self::table( self::T_AFFILIATES );
        if ( $status ) {
            return (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$t} WHERE status = %s",
                $status
            ) );
        }
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" );
    }

    /* ══════════════════════════════════════════════════════
     *  SALES CRUD
     * ══════════════════════════════════════════════════════ */
    public static function insert_sale( $data ) {
        global $wpdb;
        $wpdb->insert( self::table( self::T_SALES ), $data );
        return $wpdb->insert_id;
    }

    public static function get_sale_by_order( $order_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::table( self::T_SALES ) . " WHERE order_id = %s",
            $order_id
        ) );
    }

    public static function get_sale( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::table( self::T_SALES ) . " WHERE id = %d",
            $id
        ) );
    }

    public static function update_sale( $id, $data ) {
        global $wpdb;
        return $wpdb->update(
            self::table( self::T_SALES ),
            $data,
            array( 'id' => $id ),
            null,
            array( '%d' )
        );
    }

    public static function list_sales( $args = array() ) {
        global $wpdb;
        $t = self::table( self::T_SALES );

        $where  = '1=1';
        $params = array();

        if ( ! empty( $args['affiliate_id'] ) ) {
            $where   .= ' AND affiliate_id = %d';
            $params[] = $args['affiliate_id'];
        }
        if ( ! empty( $args['status'] ) ) {
            $where   .= ' AND status = %s';
            $params[] = $args['status'];
        }
        if ( ! empty( $args['date_from'] ) ) {
            $where   .= ' AND attributed_at >= %s';
            $params[] = $args['date_from'];
        }
        if ( ! empty( $args['date_to'] ) ) {
            $where   .= ' AND attributed_at <= %s';
            $params[] = $args['date_to'];
        }

        $order  = ! empty( $args['orderby'] ) ? sanitize_sql_orderby( $args['orderby'] ) : 'attributed_at DESC';
        if ( ! $order ) {
            $order = 'attributed_at DESC';
        }
        $limit  = isset( $args['per_page'] ) ? absint( $args['per_page'] ) : 20;
        $offset = isset( $args['offset'] )   ? absint( $args['offset'] )   : 0;

        $sql = "SELECT * FROM {$t} WHERE {$where} ORDER BY {$order} LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL
    }

    /**
     * Count sales for an affiliate (excludes cancelled).
     */
    public static function count_sales( $affiliate_id, $status = '' ) {
        global $wpdb;
        $t      = self::table( self::T_SALES );
        $params = array( $affiliate_id );
        $where  = 'affiliate_id = %d AND status != \'cancelled\'';
        if ( '' !== $status ) {
            $where   .= ' AND status = %s';
            $params[] = $status;
        }
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$t} WHERE {$where}",
            $params
        ) );
    }

    /**
     * Sum of confirmed commissions that have not yet been reserved for any payout.
     *
     * The JOIN against raff_payout_sales already excludes any sale that has been
     * reserved for a pending/processing payout or already paid — so no further
     * subtraction is needed or correct.
     */
    public static function get_available_balance( $affiliate_id ) {
        global $wpdb;

        return round( (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(s.commission_amount), 0)
             FROM " . self::table( self::T_SALES ) . " s
             LEFT JOIN " . self::table( self::T_PAYOUT_SALES ) . " ps ON ps.sale_id = s.id
             WHERE s.affiliate_id = %d
               AND s.status = 'confirmed'
               AND ps.id IS NULL",
            $affiliate_id
        ) ), 2 );
    }

    /**
     * Sum of pending commissions (not yet confirmed).
     */
    public static function get_pending_balance( $affiliate_id ) {
        global $wpdb;
        return (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(commission_amount), 0) FROM " . self::table( self::T_SALES ) .
            " WHERE affiliate_id = %d AND status = 'pending'",
            $affiliate_id
        ) );
    }

    /**
     * Confirm sales older than X days.
     */
    public static function confirm_old_sales( $days = 30 ) {
        global $wpdb;
        return $wpdb->query( $wpdb->prepare(
            "UPDATE " . self::table( self::T_SALES ) .
            " SET status = 'confirmed', confirmed_at = NOW() WHERE status = 'pending' AND attributed_at <= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ) );
    }

    /* ══════════════════════════════════════════════════════
     *  PAYOUTS CRUD
     * ══════════════════════════════════════════════════════ */
    public static function insert_payout( $data ) {
        global $wpdb;
        $wpdb->insert( self::table( self::T_PAYOUTS ), $data );
        return $wpdb->insert_id;
    }

    public static function get_payout( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::table( self::T_PAYOUTS ) . " WHERE id = %d",
            $id
        ) );
    }

    public static function update_payout( $id, $data ) {
        global $wpdb;
        return $wpdb->update(
            self::table( self::T_PAYOUTS ),
            $data,
            array( 'id' => $id ),
            null,
            array( '%d' )
        );
    }

    public static function list_payouts( $args = array() ) {
        global $wpdb;
        $t = self::table( self::T_PAYOUTS );

        $where  = '1=1';
        $params = array();

        if ( ! empty( $args['affiliate_id'] ) ) {
            $where   .= ' AND affiliate_id = %d';
            $params[] = $args['affiliate_id'];
        }
        if ( ! empty( $args['status'] ) ) {
            $where   .= ' AND status = %s';
            $params[] = $args['status'];
        }

        $order  = 'requested_at DESC';
        $limit  = isset( $args['per_page'] ) ? absint( $args['per_page'] ) : 20;
        $offset = isset( $args['offset'] )   ? absint( $args['offset'] )   : 0;

        $sql = "SELECT * FROM {$t} WHERE {$where} ORDER BY {$order} LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL
    }

    /* ══════════════════════════════════════════════════════
     *  EVENTS CRUD
     * ══════════════════════════════════════════════════════ */
    public static function insert_event( $data ) {
        global $wpdb;
        $wpdb->insert( self::table( self::T_EVENTS ), $data );
        return $wpdb->insert_id;
    }

    /**
     * Check if a visit from this IP+affiliate was already logged today.
     */
    public static function visit_exists_today( $affiliate_id, $ip_hash ) {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT 1 FROM " . self::table( self::T_EVENTS ) .
            " WHERE affiliate_id = %d AND ip_hash = %s AND event_type = 'visit' AND DATE(created_at) = CURDATE() LIMIT 1",
            $affiliate_id,
            $ip_hash
        ) );
    }

    /**
     * Count events for an affiliate (optionally filtered by type and date range).
     */
    public static function count_events( $affiliate_id, $type = '', $date_from = '', $date_to = '' ) {
        global $wpdb;
        $t = self::table( self::T_EVENTS );

        $where  = 'affiliate_id = %d';
        $params = array( $affiliate_id );

        if ( $type ) {
            $where   .= ' AND event_type = %s';
            $params[] = $type;
        }
        if ( $date_from ) {
            $where   .= ' AND created_at >= %s';
            $params[] = $date_from;
        }
        if ( $date_to ) {
            $where   .= ' AND created_at <= %s';
            $params[] = $date_to;
        }

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$t} WHERE {$where}",
            $params
        ) );
    }

    /**
     * Reserve confirmed, unpaid sales for a payout request.
     * Returns total reserved commission.
     */
    public static function reserve_sales_for_payout( $payout_id, $affiliate_id, $target_amount ) {
        global $wpdb;

        $target = (float) $target_amount;
        if ( $target <= 0 ) {
            return 0.0;
        }

        $sales_table  = self::table( self::T_SALES );
        $pivot_table  = self::table( self::T_PAYOUT_SALES );
        $reserved_sum = 0.0;

        $sales = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.id, s.commission_amount
             FROM {$sales_table} s
             LEFT JOIN {$pivot_table} ps ON ps.sale_id = s.id
             WHERE s.affiliate_id = %d
               AND s.status = 'confirmed'
               AND ps.id IS NULL
             ORDER BY s.attributed_at ASC, s.id ASC",
            $affiliate_id
        ) );

        foreach ( (array) $sales as $sale ) {
            $commission = (float) $sale->commission_amount;
            if ( $commission <= 0 ) {
                continue;
            }

            if ( ( $reserved_sum + $commission ) > ( $target + 0.0001 ) ) {
                continue;
            }

            $ok = $wpdb->insert(
                $pivot_table,
                array(
                    'payout_id'         => $payout_id,
                    'sale_id'           => $sale->id,
                    'commission_amount' => $commission,
                ),
                array( '%d', '%d', '%f' )
            );

            if ( false !== $ok ) {
                $reserved_sum += $commission;
                if ( $reserved_sum >= ( $target - 0.0001 ) ) {
                    break;
                }
            }
        }

        return round( $reserved_sum, 2 );
    }

    /**
     * Release all sales reserved for a payout back to 'confirmed' status,
     * then delete the reservation rows. Called when a payout is rejected.
     */
    public static function release_payout_reservation( $payout_id ) {
        global $wpdb;

        $sales_table = self::table( self::T_SALES );
        $pivot_table = self::table( self::T_PAYOUT_SALES );

        /* Restore sales to 'confirmed' — never touch already-paid sales */
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$sales_table}
             SET status = 'confirmed'
             WHERE id IN (SELECT sale_id FROM {$pivot_table} WHERE payout_id = %d)
               AND status != 'paid'",
            $payout_id
        ) ); // phpcs:ignore WordPress.DB.PreparedSQL

        /* Delete reservation rows */
        $wpdb->delete( $pivot_table, array( 'payout_id' => $payout_id ), array( '%d' ) );
    }

    /**
     * Whether a sale is already reserved for a payout.
     */
    public static function is_sale_reserved( $sale_id ) {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT 1 FROM " . self::table( self::T_PAYOUT_SALES ) . " WHERE sale_id = %d LIMIT 1",
            $sale_id
        ) );
    }

    /**
     * Aggregate totals for admin dashboard cards.
     */
    public static function get_admin_financial_summary() {
        global $wpdb;

        $sales_table   = self::table( self::T_SALES );
        $payouts_table = self::table( self::T_PAYOUTS );

        return array(
            'sales_pending'   => (float) $wpdb->get_var( "SELECT COALESCE(SUM(commission_amount),0) FROM {$sales_table} WHERE status='pending'" ),
            'sales_confirmed' => (float) $wpdb->get_var( "SELECT COALESCE(SUM(commission_amount),0) FROM {$sales_table} WHERE status='confirmed'" ),
            'sales_paid'      => (float) $wpdb->get_var( "SELECT COALESCE(SUM(commission_amount),0) FROM {$sales_table} WHERE status='paid'" ),
            'payout_requested'=> (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM {$payouts_table} WHERE status='requested'" ),
            'payout_processing'=> (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM {$payouts_table} WHERE status='processing'" ),
            'payout_paid'     => (float) $wpdb->get_var( "SELECT COALESCE(SUM(net_amount),0) FROM {$payouts_table} WHERE status='paid'" ),
        );
    }

    /**
     * Marks all sales linked to the payout as paid.
     */
    public static function mark_reserved_sales_paid( $payout_id ) {
        global $wpdb;

        $sales_table = self::table( self::T_SALES );
        $pivot_table = self::table( self::T_PAYOUT_SALES );

        return $wpdb->query( $wpdb->prepare(
            "UPDATE {$sales_table}
             SET status = 'paid'
             WHERE id IN (
                SELECT sale_id FROM {$pivot_table} WHERE payout_id = %d
             )",
            $payout_id
        ) );
    }
}
