<?php
/**
 * Replanta Forest Program
 *
 * Automatiza la plantación de árboles para clientes con planes elegibles.
 * – Integración con Tree-Nation API (plant trees)
 * – Sincronización con Upmind (renewal dates, client info)
 * – Emails personalizados con branding Replanta
 *
 * @package DominiosReseller
 * @since   1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Dominios_Reseller_Forest_Program {

    /* ══════════════════════════════════════════════════════════════
       CONSTANTS
    ══════════════════════════════════════════════════════════════ */

    const VERSION = '1.2.0';  // Reconciliation + retries + idempotency + public page
    
    // Settings
    const OPTION_KEY = 'dr_forest_program_settings';
    
    // Safety limits
    const MAX_TREES_PER_DAY_DEFAULT = 20;
    const MAX_RETRIES = 3;
    
    // Tree-Nation API
    const TN_API_LIVE    = 'https://tree-nation.com/api';
    const TN_API_SANDBOX = 'https://tree-nation.com/api'; // Same endpoint, different token
    
    // Eligible Upmind product slugs
    const ELIGIBLE_PRODUCTS = [ 'roble', 'cedro', 'sauce' ];
    
    // Planting window: days before renewal to plant
    const PLANT_DAYS_BEFORE = 60;
    const PLANT_WINDOW_DAYS = 4; // 58-62 days before
    
    // CO₂ absorbed per tree per year (kg)
    const CO2_PER_TREE_YEAR = 21.7;

    /* ══════════════════════════════════════════════════════════════
       SINGLETON
    ══════════════════════════════════════════════════════════════ */

    private static ?self $instance = null;

    public static function get_instance(): self {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    /* ══════════════════════════════════════════════════════════════
       HOOKS
    ══════════════════════════════════════════════════════════════ */

    private function init_hooks(): void {
        // Database setup on plugin activation
        add_action( 'plugins_loaded', [ $this, 'maybe_upgrade_db' ] );
        
        // Cron events
        add_action( 'dr_forest_daily_check',    [ $this, 'cron_daily_check' ] );
        add_action( 'dr_forest_process_queue',  [ $this, 'cron_process_queue' ] );
        add_action( 'dr_forest_reconcile',      [ $this, 'cron_reconcile' ] );
        
        // Schedule crons
        add_action( 'init', [ $this, 'schedule_crons' ] );
        
        // Admin AJAX
        add_action( 'wp_ajax_dr_forest_save_settings',   [ $this, 'ajax_save_settings' ] );
        add_action( 'wp_ajax_dr_forest_toggle_domain',   [ $this, 'ajax_toggle_domain' ] );
        add_action( 'wp_ajax_dr_forest_save_domain',     [ $this, 'ajax_save_domain' ] );
        add_action( 'wp_ajax_dr_forest_sync_upmind',     [ $this, 'ajax_sync_upmind' ] );
        add_action( 'wp_ajax_dr_forest_test_plant',      [ $this, 'ajax_test_plant' ] );
        add_action( 'wp_ajax_dr_forest_get_domains',     [ $this, 'ajax_get_domains' ] );
        add_action( 'wp_ajax_dr_forest_get_stats',       [ $this, 'ajax_get_stats' ] );
        add_action( 'wp_ajax_dr_forest_get_history',     [ $this, 'ajax_get_history' ] );
        add_action( 'wp_ajax_dr_forest_get_logs',        [ $this, 'ajax_get_logs' ] );
        add_action( 'wp_ajax_dr_forest_get_failed',      [ $this, 'ajax_get_failed' ] );
        add_action( 'wp_ajax_dr_forest_retry_failed',    [ $this, 'ajax_retry_failed' ] );
        add_action( 'wp_ajax_dr_forest_check_credits',   [ $this, 'ajax_check_credits' ] );
        add_action( 'wp_ajax_dr_forest_resend_email',    [ $this, 'ajax_resend_email' ] );
        add_action( 'wp_ajax_dr_forest_get_forecast',    [ $this, 'ajax_get_forecast' ] );
        
        // REST API
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
        
        // Upmind renewal hook (sync renewal dates from Upmind webhooks)
        add_action( 'dr_service_renewed', [ $this, 'handle_upmind_renewal' ], 10, 3 );
    }

    /* ══════════════════════════════════════════════════════════════
       DATABASE SCHEMA
    ══════════════════════════════════════════════════════════════ */

    /**
     * Create/upgrade tables
     */
    public static function create_tables(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        // ─── Table: Planting Queue ───
        $table_queue = $wpdb->prefix . 'dr_tree_planting_queue';
        $sql_queue = "CREATE TABLE {$table_queue} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            domain_id BIGINT UNSIGNED NOT NULL,
            domain VARCHAR(255) NOT NULL,
            client_email VARCHAR(255) DEFAULT NULL,
            client_name VARCHAR(255) DEFAULT NULL,
            scheduled_date DATE NOT NULL,
            renewal_date DATE DEFAULT NULL,
            status ENUM('pending','processing','completed','failed','cancelled') DEFAULT 'pending',
            attempts TINYINT UNSIGNED DEFAULT 0,
            next_retry_at DATETIME DEFAULT NULL,
            external_id VARCHAR(64) DEFAULT NULL,
            last_error TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            processed_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_scheduled (scheduled_date),
            KEY idx_domain (domain_id),
            KEY idx_retry (next_retry_at),
            UNIQUE KEY uk_external_id (external_id)
        ) {$charset};";
        dbDelta( $sql_queue );
        
        // ─── Table: Planted Trees History ───
        $table_trees = $wpdb->prefix . 'dr_planted_trees';
        $sql_trees = "CREATE TABLE {$table_trees} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            domain_id BIGINT UNSIGNED NOT NULL,
            domain VARCHAR(255) NOT NULL,
            queue_id BIGINT UNSIGNED DEFAULT NULL,
            tree_nation_id BIGINT UNSIGNED DEFAULT NULL,
            token VARCHAR(32) DEFAULT NULL,
            collect_url VARCHAR(512) DEFAULT NULL,
            certificate_url VARCHAR(512) DEFAULT NULL,
            species_id INT UNSIGNED DEFAULT NULL,
            species_name VARCHAR(255) DEFAULT NULL,
            project_id INT UNSIGNED DEFAULT NULL,
            project_name VARCHAR(255) DEFAULT NULL,
            project_url VARCHAR(512) DEFAULT NULL,
            country VARCHAR(100) DEFAULT NULL,
            co2_lifetime DECIMAL(10,2) DEFAULT NULL,
            client_email VARCHAR(255) DEFAULT NULL,
            client_name VARCHAR(255) DEFAULT NULL,
            planted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            email_sent_at DATETIME DEFAULT NULL,
            email_status ENUM('pending','sent','failed') DEFAULT 'pending',
            verified_at DATETIME DEFAULT NULL,
            verification_status ENUM('unverified','ok','missing','error') DEFAULT 'unverified',
            renewal_year YEAR DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_domain (domain_id),
            KEY idx_tree_nation (tree_nation_id),
            KEY idx_planted (planted_at),
            KEY idx_email (email_status),
            UNIQUE KEY uk_domain_year (domain_id, renewal_year)
        ) {$charset};";
        dbDelta( $sql_trees );
        
        // ─── Table: Activity Log ───
        $table_log = $wpdb->prefix . 'dr_forest_log';
        $sql_log = "CREATE TABLE {$table_log} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            level ENUM('info','warning','error','critical') DEFAULT 'info',
            message TEXT NOT NULL,
            context TEXT DEFAULT NULL,
            domain_id BIGINT UNSIGNED DEFAULT NULL,
            queue_id BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_level (level),
            KEY idx_created (created_at),
            KEY idx_domain (domain_id)
        ) {$charset};";
        dbDelta( $sql_log );
        
        // ─── Extend wp_dominios_reseller ───
        self::extend_dominios_table();
        
        update_option( 'dr_forest_db_version', self::VERSION );
    }

    /**
     * Add Forest Program columns to wp_dominios_reseller
     */
    private static function extend_dominios_table(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'dominios_reseller';
        
        $columns_to_add = [
            'forest_enabled'      => "TINYINT(1) DEFAULT 0 COMMENT 'Participa en Forest Program'",
            'upmind_client_id'    => "VARCHAR(50) DEFAULT NULL COMMENT 'ID cliente en Upmind'",
            'upmind_client_name'  => "VARCHAR(255) DEFAULT NULL COMMENT 'Nombre cliente desde Upmind'",
            'upmind_client_email' => "VARCHAR(255) DEFAULT NULL COMMENT 'Email cliente desde Upmind'",
            'upmind_product_id'   => "VARCHAR(50) DEFAULT NULL COMMENT 'ID producto en Upmind'",
            'upmind_product_slug' => "VARCHAR(100) DEFAULT NULL COMMENT 'Slug producto (roble/cedro/sauce)'",
            'billing_cycle'       => "VARCHAR(20) DEFAULT NULL COMMENT 'monthly/annual/etc'",
            'next_renewal_date'   => "DATE DEFAULT NULL COMMENT 'Próxima renovación'",
            'last_tree_planted'   => "DATE DEFAULT NULL COMMENT 'Última fecha de plantación'",
            'upmind_synced_at'    => "DATETIME DEFAULT NULL COMMENT 'Última sync con Upmind'",
        ];
        
        foreach ( $columns_to_add as $column => $definition ) {
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                DB_NAME, $table, $column
            ) );
            
            if ( ! $exists ) {
                $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}" );
            }
        }
        
        // Add index for forest queries
        $index_exists = $wpdb->get_var(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}' AND INDEX_NAME = 'idx_forest'"
        );
        if ( ! $index_exists ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD INDEX idx_forest (forest_enabled, next_renewal_date)" );
        }
    }

    /**
     * Check and upgrade DB if needed
     */
    public function maybe_upgrade_db(): void {
        $current = get_option( 'dr_forest_db_version', '0' );
        if ( version_compare( $current, self::VERSION, '<' ) ) {
            self::create_tables();
        }
    }

    /* ══════════════════════════════════════════════════════════════
       SETTINGS
    ══════════════════════════════════════════════════════════════ */

    public static function get_settings(): array {
        return wp_parse_args( get_option( self::OPTION_KEY, [] ), [
            // Tree-Nation
            'tn_api_token'      => '',
            'tn_forest_id'      => '',
            'tn_sandbox_mode'   => true,
            'tn_species_id'     => 0, // 0 = automático (más barato)
            'tn_message'        => '¡Gracias por hacer internet más verde con Replanta! 🌱',
            
            // Upmind
            'upmind_api_token'  => '',
            'upmind_api_url'    => 'https://api.upmind.io',
            
            // Email
            'email_from_name'   => 'Replanta',
            'email_from_email'  => 'hola@replanta.net',
            'email_subject'     => '🌳 ¡Tu árbol ya está plantado!',
            
            // Alerts
            'alert_credits_min' => 50,
            'alert_email'       => '',

            // Logging & summary
            'enable_logging'    => true,                 // store info/warning entries in dr_forest_log
            'summary_frequency' => 'weekly',             // 'disabled' | 'daily' | 'weekly'
            'last_summary_sent' => '',                   // YYYY-MM-DD of last summary email
            
            // Safety
            'max_trees_per_day' => self::MAX_TREES_PER_DAY_DEFAULT,
            'dry_run_mode'      => false,  // true = simulate without calling API
            
            // Sync
            'last_sync'         => '',
            
            // Internal (don't expose in UI)
            'last_credit_alert' => '',
            'trees_planted_today' => 0,
            'trees_planted_date'  => '',
        ] );
    }

    /* ══════════════════════════════════════════════════════════════
       CRON SCHEDULING
    ══════════════════════════════════════════════════════════════ */

    public function schedule_crons(): void {
        // Daily check at 3 AM
        if ( ! wp_next_scheduled( 'dr_forest_daily_check' ) ) {
            wp_schedule_event( strtotime( 'tomorrow 03:00' ), 'daily', 'dr_forest_daily_check' );
        }
        
        // Process queue every 5 minutes
        if ( ! wp_next_scheduled( 'dr_forest_process_queue' ) ) {
            wp_schedule_event( time(), 'dr_five_minutes', 'dr_forest_process_queue' );
        }

        // Reconciliation: weekly on Sundays at 04:00
        if ( ! wp_next_scheduled( 'dr_forest_reconcile' ) ) {
            wp_schedule_event( strtotime( 'next sunday 04:00' ), 'weekly', 'dr_forest_reconcile' );
        }
    }

    /**
     * Register custom cron interval
     */
    public static function add_cron_interval( $schedules ) {
        $schedules['dr_five_minutes'] = [
            'interval' => 300,
            'display'  => __( 'Every 5 Minutes', 'dominios-reseller' ),
        ];
        return $schedules;
    }

    /* ══════════════════════════════════════════════════════════════
       CRON: DAILY CHECK
       Find domains with renewal in 58-62 days and queue for planting
    ══════════════════════════════════════════════════════════════ */

    public function cron_daily_check(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'dominios_reseller';
        $queue = $wpdb->prefix . 'dr_tree_planting_queue';
        $trees = $wpdb->prefix . 'dr_planted_trees';
        
        $window_start = self::PLANT_DAYS_BEFORE - ( self::PLANT_WINDOW_DAYS / 2 );
        $window_end   = self::PLANT_DAYS_BEFORE + ( self::PLANT_WINDOW_DAYS / 2 );
        
        // Find eligible domains:
        // - forest_enabled = 1
        // - billing_cycle = 'annual' (exclude monthly)
        // - next_renewal_date between 58-62 days from now
        // - NOT already planted this renewal year
        // - NOT already in queue for this renewal
        $sql = $wpdb->prepare( "
            SELECT d.* 
            FROM `{$table}` d
            WHERE d.forest_enabled = 1
              AND d.is_primary = 1
              AND d.status = 'Activo'
              AND d.billing_cycle IN ('annual', 'yearly', 'annually')
              AND d.next_renewal_date BETWEEN DATE_ADD(NOW(), INTERVAL %d DAY)
                                          AND DATE_ADD(NOW(), INTERVAL %d DAY)
              AND d.upmind_product_slug IN ('roble', 'cedro', 'sauce')
              AND NOT EXISTS (
                  SELECT 1 FROM `{$trees}` t 
                  WHERE t.domain_id = d.id 
                    AND t.renewal_year = YEAR(d.next_renewal_date)
              )
              AND NOT EXISTS (
                  SELECT 1 FROM `{$queue}` q 
                  WHERE q.domain_id = d.id 
                    AND q.status IN ('pending', 'processing')
                    AND q.renewal_date = d.next_renewal_date
              )
        ", $window_start, $window_end );
        
        $domains = $wpdb->get_results( $sql );
        
        if ( empty( $domains ) ) {
            $this->log( 'Daily check: No domains to queue' );
            return;
        }
        
        $queued = 0;
        $skipped_no_email = 0;
        
        foreach ( $domains as $domain ) {
            // SAFETY: Validate email exists and is valid format
            $email = $domain->upmind_client_email ?? '';
            if ( empty( $email ) || ! is_email( $email ) ) {
                $this->log( "Daily check: Skipping {$domain->domain} - no valid email", 'warning', $domain->id );
                $skipped_no_email++;
                continue;
            }
            
            $inserted = $wpdb->insert( $queue, [
                'domain_id'      => $domain->id,
                'domain'         => $domain->domain,
                'client_email'   => $email,
                'client_name'    => $domain->upmind_client_name,
                'scheduled_date' => current_time( 'Y-m-d' ),
                'renewal_date'   => $domain->next_renewal_date,
                'status'         => 'pending',
            ], [ '%d', '%s', '%s', '%s', '%s', '%s', '%s' ] );
            
            if ( $inserted ) {
                $queued++;
            }
        }
        
        $msg = "Daily check: Queued {$queued} domains for planting";
        if ( $skipped_no_email > 0 ) {
            $msg .= " ({$skipped_no_email} skipped - no email)";
        }
        $this->log( $msg );
    }

    /* ══════════════════════════════════════════════════════════════
       CRON: PROCESS QUEUE
       Plant trees via Tree-Nation API
    ══════════════════════════════════════════════════════════════ */

    public function cron_process_queue(): void {
        global $wpdb;
        $queue = $wpdb->prefix . 'dr_tree_planting_queue';
        
        $settings = self::get_settings();
        
        // Skip if in dry-run mode (useful for testing)
        $dry_run = ! empty( $settings['dry_run_mode'] );
        
        if ( empty( $settings['tn_api_token'] ) && ! $dry_run ) {
            $this->log( 'Process queue: No Tree-Nation API token configured', 'warning' );
            return;
        }
        
        // Safety: Check daily limit before processing
        if ( ! $this->check_daily_limit() ) {
            $this->log( 'Process queue: Daily limit reached, skipping', 'warning' );
            return;
        }
        
        // Safety: Check we have credits available (unless dry-run)
        if ( ! $dry_run ) {
            $credits_check = $this->check_tn_credits();
            if ( is_wp_error( $credits_check ) ) {
                $this->log( 'Process queue: Cannot verify credits - ' . $credits_check->get_error_message(), 'warning' );
                // Continue anyway, API will fail if no credits
            } elseif ( ( $credits_check['credits'] ?? 0 ) < 1 ) {
                $this->log( 'Process queue: No credits available in Tree-Nation', 'error' );
                return;
            }
        }
        
        // Get pending items with row lock to prevent race conditions
        // Using SELECT FOR UPDATE ensures no other process can grab the same items.
        // Skip items still in backoff window (next_retry_at in the future).
        $items = $wpdb->get_results( $wpdb->prepare( "
            SELECT * FROM `{$queue}`
            WHERE status = 'pending'
              AND scheduled_date <= %s
              AND attempts < %d
              AND ( next_retry_at IS NULL OR next_retry_at <= %s )
            ORDER BY created_at ASC
            LIMIT 10
            FOR UPDATE
        ", current_time( 'Y-m-d' ), self::MAX_RETRIES, current_time( 'mysql' ) ) );
        
        if ( empty( $items ) ) {
            return;
        }
        
        $processed = 0;
        foreach ( $items as $item ) {
            // Re-check daily limit before each tree (in case limit reached mid-batch)
            if ( ! $this->check_daily_limit() ) {
                $this->log( "Process queue: Daily limit reached after {$processed} trees", 'warning' );
                break;
            }
            
            $this->process_queue_item( $item, $settings, $dry_run );
            $processed++;
        }
        
        if ( $processed > 0 ) {
            $this->log( "Process queue: Processed {$processed} items" . ( $dry_run ? ' (DRY-RUN)' : '' ) );
        }
    }

    /**
     * Process single queue item with safety checks
     */
    private function process_queue_item( object $item, array $settings, bool $dry_run = false ): void {
        global $wpdb;
        $queue = $wpdb->prefix . 'dr_tree_planting_queue';
        $trees = $wpdb->prefix . 'dr_planted_trees';
        $domains = $wpdb->prefix . 'dominios_reseller';
        
        // SAFETY: Double-check this domain+year hasn't been planted already
        $renewal_year = $item->renewal_date ? date( 'Y', strtotime( $item->renewal_date ) ) : date( 'Y' );
        $already_planted = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM `{$trees}` WHERE domain_id = %d AND renewal_year = %d LIMIT 1",
            $item->domain_id,
            $renewal_year
        ) );
        
        if ( $already_planted ) {
            $this->log( "DUPLICATE PREVENTED: {$item->domain} already has tree for year {$renewal_year}", 'warning', $item->domain_id );
            $wpdb->update( $queue,
                [ 'status' => 'cancelled', 'last_error' => 'Duplicate prevented: already planted this year' ],
                [ 'id' => $item->id ]
            );
            return;
        }
        
        // Mark as processing and ensure we have a stable external_id for idempotency.
        // Format: domain_id-renewal_year (1:1 with our UNIQUE constraint on planted_trees).
        $external_id = $item->external_id ?: sprintf( '%d-%d', $item->domain_id, $renewal_year );
        $wpdb->update( $queue,
            [
                'status'      => 'processing',
                'attempts'    => $item->attempts + 1,
                'external_id' => $external_id,
            ],
            [ 'id' => $item->id ],
            [ '%s', '%d', '%s' ],
            [ '%d' ]
        );
        $item->external_id = $external_id;
        
        // DRY-RUN mode: simulate success without calling API
        if ( $dry_run ) {
            $result = [
                'status' => 'ok',
                'trees'  => [[
                    'id'              => 'DRY-RUN-' . time(),
                    'token'           => 'dry-run-token',
                    'collect_url'     => 'https://example.com/dry-run',
                    'certificate_url' => 'https://example.com/dry-run-cert',
                    'species_id'      => 184,
                    'species_name'    => 'Dry Run Species',
                    'project_id'      => 0,
                    'project_name'    => 'Dry Run Project',
                ]],
            ];
            $this->log( "DRY-RUN: Would plant tree for {$item->domain}", 'info', $item->domain_id );
        } else {
            // Call Tree-Nation API
            $result = $this->plant_tree_api( $item, $settings );
        }
        
        if ( is_wp_error( $result ) ) {
            $max_retries     = self::MAX_RETRIES;
            $next_attempt    = $item->attempts + 1; // already incremented above
            $will_retry      = $next_attempt < $max_retries;
            // Exponential backoff: 15min, 1h, 4h
            $delay_minutes   = $will_retry ? min( 240, 15 * pow( 4, $next_attempt - 1 ) ) : 0;
            $next_retry_at   = $will_retry
                ? gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) + ( (int) $delay_minutes * 60 ) )
                : null;

            $wpdb->update( $queue,
                [
                    'status'        => $will_retry ? 'pending' : 'failed',
                    'last_error'    => $result->get_error_message(),
                    'next_retry_at' => $next_retry_at,
                ],
                [ 'id' => $item->id ],
                [ '%s', '%s', '%s' ],
                [ '%d' ]
            );
            $level = $will_retry ? 'warning' : 'error';
            $this->log(
                sprintf(
                    'Failed to plant tree for %s (attempt %d/%d)%s: %s',
                    $item->domain,
                    $next_attempt,
                    $max_retries,
                    $will_retry ? sprintf( ', retry in %dm', $delay_minutes ) : ' → dead-letter',
                    $result->get_error_message()
                ),
                $level,
                $item->domain_id
            );
            return;
        }
        
        // Success! Save tree data
        $tree_data = $result['trees'][0] ?? [];
        
        $wpdb->insert( $trees, [
            'domain_id'       => $item->domain_id,
            'domain'          => $item->domain,
            'queue_id'        => $item->id,
            'tree_nation_id'  => $tree_data['id'] ?? null,
            'token'           => $tree_data['token'] ?? null,
            'collect_url'     => $tree_data['collect_url'] ?? null,
            'certificate_url' => $tree_data['certificate_url'] ?? null,
            'species_id'      => $tree_data['species_id'] ?? null,
            'species_name'    => $tree_data['species_name'] ?? null,
            'project_id'      => $tree_data['project_id'] ?? null,
            'project_name'    => $tree_data['project_name'] ?? null,
            'project_url'     => $tree_data['project_url'] ?? null,
            'country'         => $tree_data['country'] ?? null,
            'co2_lifetime'    => $tree_data['species_life_time_CO2'] ?? null,
            'client_email'    => $item->client_email,
            'client_name'     => $item->client_name,
            'renewal_year'    => $item->renewal_date ? date( 'Y', strtotime( $item->renewal_date ) ) : date( 'Y' ),
            'email_status'    => 'pending',
        ] );
        
        $tree_id = $wpdb->insert_id;
        
        // Update queue status
        $wpdb->update( $queue,
            [ 'status' => 'completed', 'processed_at' => current_time( 'mysql' ) ],
            [ 'id' => $item->id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );
        
        // Update domain stats
        $wpdb->query( $wpdb->prepare( "
            UPDATE `{$domains}` 
            SET trees_planted = trees_planted + 1,
                co2_evaded = co2_evaded + %f,
                last_tree_planted = %s
            WHERE id = %d
        ", self::CO2_PER_TREE_YEAR, current_time( 'Y-m-d' ), $item->domain_id ) );
        
        // Increment daily safety counter
        $this->increment_daily_counter();
        
        $this->log( "Tree planted for {$item->domain}: Tree-Nation ID {$tree_data['id']}", 'info', $item->domain_id );
        
        // Queue email
        $this->send_tree_email( $tree_id );
    }

    /* ══════════════════════════════════════════════════════════════
       TREE-NATION API
    ══════════════════════════════════════════════════════════════ */

    /**
     * Check Tree-Nation forest credits
     * Returns available trees/credits in the forest
     * 
     * @return array|WP_Error {credits: int, trees_planted: int, co2: float} or error
     */
    public function check_tn_credits(): array|WP_Error {
        $settings = self::get_settings();
        
        if ( empty( $settings['tn_api_token'] ) || empty( $settings['tn_forest_id'] ) ) {
            return new WP_Error( 'not_configured', 'Tree-Nation API no configurada' );
        }
        
        $forest_id = $settings['tn_forest_id'];
        $url = self::TN_API_LIVE . "/forests/{$forest_id}";
        
        $response = wp_remote_get( $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $settings['tn_api_token'],
                'Accept'        => 'application/json',
            ],
            'timeout' => 15,
        ] );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( $code !== 200 ) {
            return new WP_Error( 'api_error', "HTTP {$code}: " . ( $data['message'] ?? $body ) );
        }
        
        // Extract credit info from forest data
        // Tree-Nation returns: {id, name, trees, tons_co2, credits, ...}
        $credits = intval( $data['credits'] ?? $data['available_trees'] ?? 0 );
        $trees   = intval( $data['trees'] ?? 0 );
        $co2     = floatval( $data['tons_co2'] ?? 0 );
        
        // Check if credits are low and send alert
        $min_credits = absint( $settings['alert_credits_min'] ?? 50 );
        if ( $credits > 0 && $credits <= $min_credits ) {
            $this->maybe_send_low_credits_alert( $credits, $min_credits );
        }
        
        return [
            'credits'       => $credits,
            'trees_planted' => $trees,
            'co2_tons'      => $co2,
            'forest_name'   => $data['name'] ?? '',
        ];
    }

    /**
     * Send email alert when credits are low (max once per day)
     */
    private function maybe_send_low_credits_alert( int $credits, int $min ): void {
        $settings = self::get_settings();
        
        // Only alert once per day
        $last_alert = $settings['last_credit_alert'] ?? '';
        if ( $last_alert === date( 'Y-m-d' ) ) {
            return;
        }
        
        $alert_email = $settings['alert_email'] ?: get_option( 'admin_email' );
        if ( ! $alert_email ) {
            return;
        }
        
        $subject = '⚠️ [Replanta Forest] Créditos bajos en Tree-Nation';
        $message = sprintf(
            "Quedan solo %d créditos disponibles en Tree-Nation.\n\n" .
            "El mínimo configurado es: %d\n\n" .
            "Por favor recarga créditos en tu cuenta de Tree-Nation para evitar interrupciones.\n\n" .
            "— Replanta Forest Program",
            $credits,
            $min
        );
        
        $sent = wp_mail( $alert_email, $subject, $message );
        
        if ( $sent ) {
            // Update last alert date
            $settings['last_credit_alert'] = date( 'Y-m-d' );
            update_option( self::OPTION_KEY, $settings );
            $this->log( "Low credits alert sent ({$credits} remaining)", 'warning' );
        }
    }

    /**
     * Check daily tree limit safety
     * 
     * @return bool True if can plant more today, false if limit reached
     */
    private function check_daily_limit(): bool {
        $settings = self::get_settings();
        $max_per_day = absint( $settings['max_trees_per_day'] ?? self::MAX_TREES_PER_DAY_DEFAULT );
        
        $today = date( 'Y-m-d' );
        
        // Reset counter if different day
        if ( ( $settings['trees_planted_date'] ?? '' ) !== $today ) {
            $settings['trees_planted_today'] = 0;
            $settings['trees_planted_date'] = $today;
            update_option( self::OPTION_KEY, $settings );
        }
        
        $planted_today = absint( $settings['trees_planted_today'] ?? 0 );
        
        if ( $planted_today >= $max_per_day ) {
            $this->log( "Daily limit reached ({$planted_today}/{$max_per_day})", 'warning' );
            return false;
        }
        
        return true;
    }

    /**
     * Increment daily tree counter
     */
    private function increment_daily_counter(): void {
        $settings = self::get_settings();
        $today = date( 'Y-m-d' );
        
        if ( ( $settings['trees_planted_date'] ?? '' ) !== $today ) {
            $settings['trees_planted_today'] = 1;
            $settings['trees_planted_date'] = $today;
        } else {
            $settings['trees_planted_today'] = absint( $settings['trees_planted_today'] ?? 0 ) + 1;
        }
        
        update_option( self::OPTION_KEY, $settings );
    }

    /**
     * Get the cheapest available species from Tree-Nation
     * Caches result for 1 hour to avoid excessive API calls
     * 
     * @return int|null Species ID or null if not found
     */
    private function get_cheapest_species(): ?int {
        // Check cache first
        $cached = get_transient( 'dr_forest_cheapest_species' );
        if ( $cached !== false ) {
            return $cached ?: null;
        }
        
        // Get list of projects
        $projects_response = wp_remote_get( 'https://tree-nation.com/api/projects', [
            'headers' => [ 'Accept' => 'application/json' ],
            'timeout' => 15,
        ] );
        
        if ( is_wp_error( $projects_response ) ) {
            return null;
        }
        
        $projects = json_decode( wp_remote_retrieve_body( $projects_response ), true );
        if ( ! is_array( $projects ) || empty( $projects ) ) {
            return null;
        }
        
        $cheapest_species = null;
        $cheapest_price = PHP_FLOAT_MAX;
        
        // Check first 5 projects for species (most common/active projects)
        foreach ( array_slice( $projects, 0, 5 ) as $project ) {
            $project_id = $project['id'] ?? null;
            if ( ! $project_id ) continue;
            
            $species_response = wp_remote_get( "https://tree-nation.com/api/projects/{$project_id}/species", [
                'headers' => [ 'Accept' => 'application/json' ],
                'timeout' => 10,
            ] );
            
            if ( is_wp_error( $species_response ) ) continue;
            
            $species_list = json_decode( wp_remote_retrieve_body( $species_response ), true );
            if ( ! is_array( $species_list ) ) continue;
            
            foreach ( $species_list as $species ) {
                $price = floatval( $species['price'] ?? 999 );
                if ( $price > 0 && $price < $cheapest_price ) {
                    $cheapest_price = $price;
                    $cheapest_species = intval( $species['id'] );
                }
            }
        }
        
        // Cache for 1 hour (0 if not found, to retry later)
        set_transient( 'dr_forest_cheapest_species', $cheapest_species ?: 0, HOUR_IN_SECONDS );
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "Tree-Nation: Cheapest species found: ID {$cheapest_species} at €{$cheapest_price}" );
        }
        
        return $cheapest_species;
    }

    /**
     * Plant tree via Tree-Nation API
     * 
     * @param object $item Queue item
     * @param array $settings Plugin settings
     * @return array|WP_Error API response or error
     */
    private function plant_tree_api( object $item, array $settings ): array|WP_Error {
        $api_url = self::TN_API_LIVE . '/plant';
        
        $body = [
            'quantity' => 1,
            'message'  => $settings['tn_message'] ?: 'Gracias por plantar árboles con nosotros',
        ];

        // Idempotency key: avoids duplicate plantings on network retries.
        // Tree-Nation supports `external_id` to deduplicate requests.
        if ( ! empty( $item->external_id ) ) {
            $body['external_id'] = $item->external_id;
        }
        
        // Species selection: user-configured or cheapest available
        $species_id = ! empty( $settings['tn_species_id'] ) ? intval( $settings['tn_species_id'] ) : 0;
        
        if ( ! $species_id ) {
            // Auto-select cheapest species
            $species_id = $this->get_cheapest_species();
        }
        
        if ( $species_id ) {
            $body['species_id'] = $species_id;
        }
        
        // Add recipient if we have email (offers the tree to client)
        // Format according to Tree-Nation API docs: language OUTSIDE recipients array
        if ( ! empty( $item->client_email ) ) {
            $body['recipients'] = [
                [
                    'name'  => $item->client_name ?: 'Cliente Replanta',
                    'email' => $item->client_email,
                ],
            ];
            $body['language'] = 'es';  // Outside recipients array per docs
        }
        
        // Log request in debug mode
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Tree-Nation API Request: ' . wp_json_encode( $body ) );
        }
        
        $response = wp_remote_post( $api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $settings['tn_api_token'],
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body'    => wp_json_encode( $body ),
            'timeout' => 30,
        ] );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code( $response );
        $raw_body = wp_remote_retrieve_body( $response );
        $data = json_decode( $raw_body, true );
        
        // Log response in debug mode
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Tree-Nation API Response (' . $code . '): ' . $raw_body );
        }
        
        if ( $code !== 200 && $code !== 201 ) {
            // Try to extract detailed error message
            $error_msg = $data['message'] ?? $data['error'] ?? $raw_body;
            if ( is_array( $error_msg ) ) {
                $error_msg = implode( ', ', array_map( function( $v ) {
                    return is_array( $v ) ? implode( ', ', $v ) : $v;
                }, $error_msg ) );
            }
            return new WP_Error( 
                'tree_nation_error',
                sprintf( 'HTTP %d: %s', $code, $error_msg ?: 'Unknown error' )
            );
        }
        
        if ( ( $data['status'] ?? '' ) !== 'ok' ) {
            return new WP_Error( 
                'tree_nation_error',
                $data['message'] ?? 'La API no devolvió status ok'
            );
        }
        
        return $data;
    }

    /* ══════════════════════════════════════════════════════════════
       EMAIL
    ══════════════════════════════════════════════════════════════ */

    /**
     * Send personalized tree email
     */
    private function send_tree_email( int $tree_id ): bool {
        global $wpdb;
        $trees = $wpdb->prefix . 'dr_planted_trees';
        $domains = $wpdb->prefix . 'dominios_reseller';
        
        $tree = $wpdb->get_row( $wpdb->prepare(
            "SELECT t.*, d.next_renewal_date, d.domain as domain_name
             FROM `{$trees}` t
             LEFT JOIN `{$domains}` d ON t.domain_id = d.id
             WHERE t.id = %d",
            $tree_id
        ) );
        
        if ( ! $tree || empty( $tree->client_email ) ) {
            return false;
        }
        
        $settings = self::get_settings();
        
        // Build email
        $subject = $settings['email_subject'];
        $html = $this->render_tree_email( $tree, $settings );
        
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            sprintf( 'From: %s <%s>', $settings['email_from_name'], $settings['email_from_email'] ),
        ];
        
        $sent = wp_mail( $tree->client_email, $subject, $html, $headers );
        
        // Update status
        $wpdb->update( $trees,
            [ 
                'email_status'  => $sent ? 'sent' : 'failed',
                'email_sent_at' => $sent ? current_time( 'mysql' ) : null,
            ],
            [ 'id' => $tree_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );
        
        if ( $sent ) {
            $this->log( "Email sent to {$tree->client_email} for tree {$tree_id}" );
        } else {
            $this->log( "Email FAILED for {$tree->client_email}, tree {$tree_id}", 'error' );
        }
        
        return $sent;
    }

    /**
     * Render tree email HTML
     */
    private function render_tree_email( object $tree, array $settings ): string {
        // Calculate days until renewal
        $days_until = '';
        if ( $tree->next_renewal_date ) {
            $diff = ( strtotime( $tree->next_renewal_date ) - time() ) / DAY_IN_SECONDS;
            $days_until = round( $diff );
        }

        // Decorate URLs with UTM params for analytics tracking.
        $tree_id_str = (string) ( $tree->tree_nation_id ?: $tree->id );
        if ( ! empty( $tree->collect_url ) ) {
            $tree->collect_url = $this->add_utm( $tree->collect_url, 'tree_email_collect', $tree_id_str );
        }
        if ( ! empty( $tree->certificate_url ) ) {
            $tree->certificate_url = $this->add_utm( $tree->certificate_url, 'tree_email_cert', $tree_id_str );
        }

        // i18n: switch to email locale (filterable; Polylang/WPML can hook in).
        $locale  = $this->determine_email_locale( $tree );
        $switched = false;
        if ( $locale && function_exists( 'switch_to_locale' ) && $locale !== get_locale() ) {
            $switched = switch_to_locale( $locale );
        }

        ob_start();
        include plugin_dir_path( __DIR__ ) . 'templates/email-tree-planted.php';
        $html = ob_get_clean();

        if ( $switched && function_exists( 'restore_previous_locale' ) ) {
            restore_previous_locale();
        }

        return $html;
    }

    /**
     * Append UTM parameters to a URL for tracking email opens/clicks.
     */
    private function add_utm( string $url, string $campaign, string $content = '' ): string {
        if ( empty( $url ) || $url === '#' ) {
            return $url;
        }
        $params = [
            'utm_source'   => 'replanta',
            'utm_medium'   => 'email',
            'utm_campaign' => $campaign,
        ];
        if ( $content !== '' ) {
            $params['utm_content'] = $content;
        }
        return add_query_arg( $params, $url );
    }

    /**
     * Determine the locale to render the client email in.
     * Defaults to 'es_ES'. Hookable via 'dr_forest_email_locale' filter
     * (Polylang/WPML integrations can plug in there).
     */
    private function determine_email_locale( object $tree ): string {
        $default = 'es_ES';

        // Polylang: try to detect language from a registered user matching the email
            if ( function_exists( 'pll_get_user_language' ) && ! empty( $tree->client_email ) ) {
            $user = get_user_by( 'email', $tree->client_email );
            if ( $user ) {
                $lang = pll_get_user_language( $user->ID );
                if ( $lang ) {
                    // Map ISO code to a WP locale (best effort)
                    $map = [ 'es' => 'es_ES', 'en' => 'en_US', 'ca' => 'ca', 'pt' => 'pt_PT', 'fr' => 'fr_FR' ];
                    $default = $map[ $lang ] ?? $default;
                }
            }
        }

        return (string) apply_filters( 'dr_forest_email_locale', $default, $tree );
    }

    /* ══════════════════════════════════════════════════════════════
       AJAX HANDLERS
    ══════════════════════════════════════════════════════════════ */

    /**
     * Save settings
     */
    public function ajax_save_settings(): void {
        check_ajax_referer( 'dr_forest_nonce', '_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permisos insuficientes' );
        }
        
        $settings = self::get_settings();
        
        // Tree-Nation
        $settings['tn_api_token']    = sanitize_text_field( $_POST['tn_api_token'] ?? '' );
        $settings['tn_forest_id']    = sanitize_text_field( $_POST['tn_forest_id'] ?? '' );
        $settings['tn_sandbox_mode'] = ! empty( $_POST['tn_sandbox_mode'] );
        $settings['tn_message']      = sanitize_textarea_field( $_POST['tn_message'] ?? '' );
        $settings['tn_species_id']   = absint( $_POST['tn_species_id'] ?? 0 );
        
        // Upmind
        $settings['upmind_api_token'] = sanitize_text_field( $_POST['upmind_api_token'] ?? '' );
        $settings['upmind_api_url']   = esc_url_raw( $_POST['upmind_api_url'] ?? '' );
        
        // Email
        $settings['email_from_name']  = sanitize_text_field( $_POST['email_from_name'] ?? '' );
        $settings['email_from_email'] = sanitize_email( $_POST['email_from_email'] ?? '' );
        $settings['email_subject']    = sanitize_text_field( $_POST['email_subject'] ?? '' );
        
        // Alerts
        $settings['alert_credits_min'] = absint( $_POST['alert_credits_min'] ?? 50 );
        $settings['alert_email']       = sanitize_email( $_POST['alert_email'] ?? '' );

        // Logging & summary
        $settings['enable_logging']    = ! empty( $_POST['enable_logging'] );
        $freq                          = sanitize_text_field( $_POST['summary_frequency'] ?? 'weekly' );
        $settings['summary_frequency'] = in_array( $freq, [ 'disabled', 'daily', 'weekly' ], true ) ? $freq : 'weekly';
        
        // Safety
        $settings['max_trees_per_day'] = absint( $_POST['max_trees_per_day'] ?? self::MAX_TREES_PER_DAY_DEFAULT );
        $settings['dry_run_mode']      = ! empty( $_POST['dry_run_mode'] );
        
        update_option( self::OPTION_KEY, $settings );

        // Re-evaluate summary cron immediately (handles freq changes)
        $this->schedule_daily_summary();
        
        wp_send_json_success( [ 'message' => 'Configuración guardada' ] );
    }

    /**
     * Toggle forest_enabled for a domain
     */
    /**
     * Save domain details manually (for when Upmind API is not available)
     */
    public function ajax_save_domain(): void {
        check_ajax_referer( 'dr_forest_nonce', '_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permisos insuficientes' );
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'dominios_reseller';
        
        $domain_id    = absint( $_POST['domain_id'] ?? 0 );
        $client_name  = sanitize_text_field( $_POST['client_name'] ?? '' );
        $client_email = sanitize_email( $_POST['client_email'] ?? '' );
        $product      = sanitize_text_field( $_POST['product'] ?? '' );
        $cycle        = sanitize_text_field( $_POST['cycle'] ?? '' );
        $renewal      = sanitize_text_field( $_POST['renewal'] ?? '' );
        
        if ( ! $domain_id ) {
            wp_send_json_error( 'ID de dominio inválido' );
        }
        
        // Validate product (only allow valid products)
        $valid_products = [ 'roble', 'cedro', 'sauce', '' ];
        if ( ! in_array( strtolower( $product ), $valid_products ) ) {
            $product = '';
        }
        
        // Validate cycle
        $valid_cycles = [ 'monthly', 'annual', '' ];
        if ( ! in_array( strtolower( $cycle ), $valid_cycles ) ) {
            $cycle = '';
        }
        
        // Validate renewal date format (YYYY-MM-DD)
        if ( $renewal && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $renewal ) ) {
            $renewal = '';
        }
        
        $result = $wpdb->update( $table,
            [
                'upmind_client_name'  => $client_name ?: null,
                'upmind_client_email' => $client_email ?: null,
                'upmind_product_slug' => $product ?: null,
                'billing_cycle'       => $cycle ?: null,
                'next_renewal_date'   => $renewal ?: null,
            ],
            [ 'id' => $domain_id ],
            [ '%s', '%s', '%s', '%s', '%s' ],
            [ '%d' ]
        );
        
        if ( $result === false ) {
            wp_send_json_error( 'Error al actualizar: ' . $wpdb->last_error );
        }
        
        wp_send_json_success( [ 
            'domain_id' => $domain_id,
            'message'   => 'Dominio actualizado correctamente',
        ] );
    }

    public function ajax_toggle_domain(): void {
        check_ajax_referer( 'dr_forest_nonce', '_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permisos insuficientes' );
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'dominios_reseller';
        
        $domain_id = absint( $_POST['domain_id'] ?? 0 );
        $enabled   = ! empty( $_POST['enabled'] ) ? 1 : 0;
        
        if ( ! $domain_id ) {
            wp_send_json_error( 'ID de dominio inválido' );
        }
        
        $result = $wpdb->update( $table,
            [ 'forest_enabled' => $enabled ],
            [ 'id' => $domain_id ],
            [ '%d' ],
            [ '%d' ]
        );
        
        if ( $result === false ) {
            wp_send_json_error( 'Error al actualizar' );
        }
        
        wp_send_json_success( [ 
            'domain_id' => $domain_id,
            'enabled'   => $enabled,
        ] );
    }

    /**
     * Get domains list (paginated, async)
     */
    public function ajax_get_domains(): void {
        check_ajax_referer( 'dr_forest_nonce', '_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permisos insuficientes' );
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'dominios_reseller';
        $trees = $wpdb->prefix . 'dr_planted_trees';
        
        $page     = max( 1, absint( $_POST['page'] ?? 1 ) );
        $per_page = 20;
        $offset   = ( $page - 1 ) * $per_page;
        $search   = sanitize_text_field( $_POST['search'] ?? '' );
        $filter   = sanitize_text_field( $_POST['filter'] ?? 'all' );
        
        $where = "d.is_primary = 1";
        
        if ( $search ) {
            $where .= $wpdb->prepare( " AND (d.domain LIKE %s OR d.upmind_client_name LIKE %s)",
                '%' . $wpdb->esc_like( $search ) . '%',
                '%' . $wpdb->esc_like( $search ) . '%'
            );
        }
        
        if ( $filter === 'forest' ) {
            $where .= " AND d.forest_enabled = 1";
        } elseif ( $filter === 'eligible' ) {
            $where .= " AND d.upmind_product_slug IN ('roble','cedro','sauce') AND d.billing_cycle IN ('annual','yearly','annually')";
        }
        
        $total = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` d WHERE {$where}" );
        
        $domains = $wpdb->get_results( "
            SELECT d.*, 
                   (SELECT COUNT(*) FROM `{$trees}` t WHERE t.domain_id = d.id) as total_trees,
                   (SELECT MAX(t.planted_at) FROM `{$trees}` t WHERE t.domain_id = d.id) as last_planted
            FROM `{$table}` d
            WHERE {$where}
            ORDER BY d.forest_enabled DESC, d.domain ASC
            LIMIT {$per_page} OFFSET {$offset}
        " );
        
        wp_send_json_success( [
            'domains'  => $domains,
            'total'    => (int) $total,
            'page'     => $page,
            'per_page' => $per_page,
            'pages'    => ceil( $total / $per_page ),
        ] );
    }

    /**
     * Sync with Upmind API
     */
    public function ajax_sync_upmind(): void {
        check_ajax_referer( 'dr_forest_nonce', '_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permisos insuficientes' );
        }
        
        $result = $this->sync_upmind_data();
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }
        
        wp_send_json_success( $result );
    }

    /**
     * Test plant (sandbox)
     */
    public function ajax_test_plant(): void {
        check_ajax_referer( 'dr_forest_nonce', '_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permisos insuficientes' );
        }
        
        $settings = self::get_settings();
        
        if ( empty( $settings['tn_api_token'] ) ) {
            wp_send_json_error( 'Configura el token de Tree-Nation primero' );
        }
        
        // Create mock item
        $item = (object) [
            'client_email' => sanitize_email( $_POST['email'] ?? '' ),
            'client_name'  => sanitize_text_field( $_POST['name'] ?? 'Test Replanta' ),
        ];
        
        $result = $this->plant_tree_api( $item, $settings );
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }
        
        wp_send_json_success( $result );
    }

    /**
     * Check Tree-Nation credits via AJAX
     */
    public function ajax_check_credits(): void {
        check_ajax_referer( 'dr_forest_nonce', '_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permisos insuficientes' );
        }
        
        $result = $this->check_tn_credits();
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }
        
        wp_send_json_success( $result );
    }

    /**
     * Get stats for dashboard
     */
    public function ajax_get_stats(): void {
        check_ajax_referer( 'dr_forest_nonce', '_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permisos insuficientes' );
        }
        
        global $wpdb;
        $domains = $wpdb->prefix . 'dominios_reseller';
        $trees   = $wpdb->prefix . 'dr_planted_trees';
        $queue   = $wpdb->prefix . 'dr_tree_planting_queue';
        
        $stats = [
            'total_domains'   => $wpdb->get_var( "SELECT COUNT(*) FROM `{$domains}` WHERE is_primary = 1" ),
            'forest_enabled'  => $wpdb->get_var( "SELECT COUNT(*) FROM `{$domains}` WHERE is_primary = 1 AND forest_enabled = 1" ),
            'eligible'        => $wpdb->get_var( "SELECT COUNT(*) FROM `{$domains}` WHERE is_primary = 1 AND upmind_product_slug IN ('roble','cedro','sauce') AND billing_cycle IN ('annual','yearly','annually')" ),
            'trees_planted'   => $wpdb->get_var( "SELECT COUNT(*) FROM `{$trees}`" ),
            'co2_total'       => $wpdb->get_var( "SELECT COALESCE(SUM(co2_lifetime), 0) FROM `{$trees}`" ),
            'queue_pending'   => $wpdb->get_var( "SELECT COUNT(*) FROM `{$queue}` WHERE status = 'pending'" ),
            'emails_pending'  => $wpdb->get_var( "SELECT COUNT(*) FROM `{$trees}` WHERE email_status = 'pending'" ),
            'this_month'      => $wpdb->get_var( "SELECT COUNT(*) FROM `{$trees}` WHERE planted_at >= DATE_FORMAT(NOW(),'%Y-%m-01')" ),
        ];
        
        wp_send_json_success( $stats );
    }

    /**
     * Get planting history (paginated)
     */
    public function ajax_get_history(): void {
        check_ajax_referer( 'dr_forest_nonce', '_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permisos insuficientes' );
        }
        
        global $wpdb;
        $trees = $wpdb->prefix . 'dr_planted_trees';
        
        $page     = max( 1, absint( $_POST['page'] ?? 1 ) );
        $per_page = 20;
        $offset   = ( $page - 1 ) * $per_page;
        
        $total = $wpdb->get_var( "SELECT COUNT(*) FROM `{$trees}`" );
        
        $history = $wpdb->get_results( $wpdb->prepare( "
            SELECT * FROM `{$trees}`
            ORDER BY planted_at DESC
            LIMIT %d OFFSET %d
        ", $per_page, $offset ) );
        
        wp_send_json_success( [
            'trees'    => $history,
            'total'    => (int) $total,
            'page'     => $page,
            'per_page' => $per_page,
            'pages'    => ceil( $total / $per_page ),
        ] );
    }

    /* ══════════════════════════════════════════════════════════════
       UPMIND SYNC
    ══════════════════════════════════════════════════════════════ */

    /**
     * Sync domain data from Upmind
     * TODO: Implementar cuando tengamos documentación API Upmind
     */
    public function sync_upmind_data(): array|WP_Error {
        $settings = self::get_settings();
        
        if ( empty( $settings['upmind_api_token'] ) ) {
            return new WP_Error( 'no_token', 'Token de Upmind no configurado' );
        }
        
        $api_url = rtrim( $settings['upmind_api_url'] ?: 'https://api.upmind.io', '/' );
        
        // Fetch active services from Upmind
        $services = $this->upmind_fetch_services( $api_url, $settings['upmind_api_token'] );
        
        if ( is_wp_error( $services ) ) {
            return $services;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'dominios_reseller';
        
        $updated = 0;
        $matched = 0;
        $eligible = 0;
        
        foreach ( $services as $svc ) {
            $domain = $this->extract_domain_from_upmind_service( $svc );
            if ( ! $domain ) continue;
            
            $matched++;
            
            // Check if domain exists in our database
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM `{$table}` WHERE domain = %s LIMIT 1",
                $domain
            ) );
            
            if ( ! $exists ) continue;
            
            // Extract data - handle different Upmind response structures
            // contract-products: {id, contract_id, product_id, billing_cycle_months, next_due_date, client: {...}, product: {...}}
            // contracts: {id, client_id, contract_products: [{...}], client: {...}}
            $client       = $svc['client'] ?? $svc['contract']['client'] ?? [];
            $contract     = $svc['contract'] ?? [];
            $product      = $svc['product'] ?? $contract['product'] ?? [];
            
            // Product slug/code
            $product_slug = strtolower( $product['code'] ?? $product['slug'] ?? $product['name'] ?? '' );
            
            // Billing cycle (months)
            $billing_months = (int) ( 
                $svc['billing_cycle_months'] ?? 
                $contract['billing_cycle_months'] ?? 
                $svc['term_months'] ?? 0 
            );
            $billing = $billing_months === 1 ? 'monthly' : ( $billing_months >= 12 ? 'annual' : '' );
            
            // Next due date
            $next_due = $svc['next_due_date'] ?? $svc['next_invoice_date'] ?? $contract['next_due_date'] ?? null;
            
            // Check eligibility (only roble/cedro/sauce + annual)
            if ( in_array( $product_slug, [ 'roble', 'cedro', 'sauce' ] ) && $billing === 'annual' ) {
                $eligible++;
            }
            
            // Update record
            $update = [
                'upmind_client_id'    => $client['id'] ?? null,
                'upmind_client_name'  => trim( ( $client['first_name'] ?? '' ) . ' ' . ( $client['last_name'] ?? '' ) ),
                'upmind_client_email' => $client['email'] ?? null,
                'upmind_product_id'   => $product['id'] ?? null,
                'upmind_product_slug' => $product_slug,
                'billing_cycle'       => $billing,
                'upmind_synced_at'    => current_time( 'mysql' ),
            ];
            
            if ( $next_due ) {
                $update['next_renewal_date'] = date( 'Y-m-d', strtotime( $next_due ) );
            }
            
            $result = $wpdb->update(
                $table,
                $update,
                [ 'domain' => $domain ],
                array_fill( 0, count( $update ), '%s' ),
                [ '%s' ]
            );
            
            if ( $result !== false ) {
                $updated++;
            }
        }
        
        return [
            'status'       => 'success',
            'message'      => "Sincronizados {$updated} dominios de {$matched} servicios encontrados. {$eligible} elegibles para Forest.",
            'total_services' => count( $services ),
            'matched'      => $matched,
            'updated'      => $updated,
            'eligible'     => $eligible,
        ];
    }

    /**
     * Fetch services from Upmind API
     * Prueba varios endpoints posibles
     */
    private function upmind_fetch_services( string $api_url, string $token ): array|WP_Error {
        // Posibles endpoints de Upmind para servicios/contratos
        $endpoints = [
            '/api/admin/contract-products',
            '/api/admin/contracts', 
            '/api/admin/services',
        ];
        
        $all_services = [];
        $working_endpoint = null;
        
        foreach ( $endpoints as $endpoint ) {
            $response = wp_remote_get( "{$api_url}{$endpoint}", [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Accept'        => 'application/json',
                    'Run-As'        => 'user',
                ],
                'body' => [
                    'page'  => 1,
                    'limit' => 1,
                    'with'  => 'client,product,contract',
                ],
            ] );
            
            if ( is_wp_error( $response ) ) continue;
            
            $code = wp_remote_retrieve_response_code( $response );
            if ( $code === 200 ) {
                $working_endpoint = $endpoint;
                $this->log( "Upmind API: endpoint encontrado: {$endpoint}" );
                break;
            }
        }
        
        if ( ! $working_endpoint ) {
            return new WP_Error( 
                'no_endpoint', 
                'No se encontró un endpoint válido en Upmind. Endpoints probados: ' . implode( ', ', $endpoints ) . 
                '. Verifica que el token tenga permisos de Admin API y la IP del servidor esté permitida.'
            );
        }
        
        // Ahora fetch paginado del endpoint que funcionó
        $page = 1;
        $per_page = 100;
        
        do {
            $response = wp_remote_get( "{$api_url}{$working_endpoint}", [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Accept'        => 'application/json',
                    'Run-As'        => 'user',
                ],
                'body' => [
                    'page'  => $page,
                    'limit' => $per_page,
                    'with'  => 'client,product,contract',
                ],
            ] );
            
            if ( is_wp_error( $response ) ) {
                return new WP_Error( 'api_error', 'Error conectando con Upmind: ' . $response->get_error_message() );
            }
            
            $code = wp_remote_retrieve_response_code( $response );
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );
            
            if ( $code === 401 ) {
                return new WP_Error( 'unauthorized', 'Token de Upmind inválido o expirado' );
            }
            
            if ( $code !== 200 ) {
                return new WP_Error( 'api_error', "Upmind API error ({$code}): " . ( $data['message'] ?? $body ) );
            }
            
            $services = $data['data'] ?? $data['contracts'] ?? [];
            
            if ( empty( $services ) || ! is_array( $services ) ) {
                break;
            }
            
            $all_services = array_merge( $all_services, $services );
            
            // Check if there are more pages
            $total = $data['total'] ?? $data['meta']['total'] ?? count( $services );
            $has_more = count( $all_services ) < $total;
            $page++;
            
        } while ( $has_more && $page <= 10 ); // Max 10 pages (1000 services)
        
        $this->log( "Upmind API: fetched " . count( $all_services ) . " services" );
        
        return $all_services;
    }

    /**
     * Extract domain from Upmind service data
     */
    private function extract_domain_from_upmind_service( array $svc ): ?string {
        // Try different field paths where domain might be (contract-products / contracts)
        $paths = [
            'domain',
            'hostname',
            'service_identifier',
            'identifier',
            'properties.domain',
            'properties.hostname',
            'attributes.domain',
            'custom_fields.domain',
            'provisioning_data.domain',
            'provisioning_data.hostname',
            'server_hostname',
            'contract.domain',
            'contract.properties.domain',
            'product.properties.domain',
        ];
        
        foreach ( $paths as $path ) {
            $value = $this->get_nested_value( $svc, $path );
            if ( $value && $this->is_valid_domain( $value ) ) {
                return strtolower( $value );
            }
        }
        
        // Try to extract from name if it looks like a domain
        $name = $svc['name'] ?? $svc['label'] ?? '';
        if ( preg_match( '/([a-zA-Z0-9][-a-zA-Z0-9]*\.)+[a-zA-Z]{2,}/', $name, $m ) ) {
            return strtolower( $m[0] );
        }
        
        return null;
    }

    /**
     * Get nested array value by dot notation path
     */
    private function get_nested_value( array $arr, string $path ) {
        $keys = explode( '.', $path );
        $value = $arr;
        
        foreach ( $keys as $key ) {
            if ( ! is_array( $value ) || ! isset( $value[ $key ] ) ) {
                return null;
            }
            $value = $value[ $key ];
        }
        
        return $value;
    }

    /**
     * Check if string is a valid domain
     */
    private function is_valid_domain( string $domain ): bool {
        return (bool) preg_match( '/^([a-zA-Z0-9][-a-zA-Z0-9]*\.)+[a-zA-Z]{2,}$/', $domain );
    }

    /* ══════════════════════════════════════════════════════════════
       REST API
    ══════════════════════════════════════════════════════════════ */

    public function register_rest_routes(): void {
        // Public endpoint: forest stats
        register_rest_route( 'dr/v1', '/forest/stats', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'rest_forest_stats' ],
            'permission_callback' => '__return_true',
        ] );
        
        // Public endpoint: domain certificate
        register_rest_route( 'dr/v1', '/forest/certificate/(?P<domain>[a-zA-Z0-9.-]+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'rest_domain_certificate' ],
            'permission_callback' => '__return_true',
        ] );

        // Public endpoint: recent plantings (anonymized for transparency page)
        register_rest_route( 'dr/v1', '/forest/recent', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'rest_forest_recent' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'limit' => [ 'default' => 50, 'sanitize_callback' => 'absint' ],
            ],
        ] );
    }

    /**
     * REST: Get global forest stats
     */
    public function rest_forest_stats(): WP_REST_Response {
        global $wpdb;
        $trees = $wpdb->prefix . 'dr_planted_trees';
        
        $cache_key = 'dr_forest_public_stats';
        $cached = get_transient( $cache_key );
        
        if ( $cached !== false ) {
            return new WP_REST_Response( $cached, 200 );
        }
        
        $stats = [
            'total_trees' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$trees}`" ),
            'co2_kg'      => (float) $wpdb->get_var( "SELECT COALESCE(SUM(co2_lifetime), 0) FROM `{$trees}`" ),
            'countries'   => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT country) FROM `{$trees}` WHERE country IS NOT NULL" ),
            'species'     => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT species_name) FROM `{$trees}` WHERE species_name IS NOT NULL" ),
        ];
        
        set_transient( $cache_key, $stats, HOUR_IN_SECONDS );
        
        return new WP_REST_Response( $stats, 200 );
    }

    /**
     * REST: Get domain's planted trees
     */
    public function rest_domain_certificate( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;
        $domains = $wpdb->prefix . 'dominios_reseller';
        $trees   = $wpdb->prefix . 'dr_planted_trees';
        
        $domain = sanitize_text_field( $request['domain'] );
        
        // Get domain
        $dom = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM `{$domains}` WHERE domain = %s AND is_primary = 1",
            $domain
        ) );
        
        if ( ! $dom || ! $dom->forest_enabled ) {
            return new WP_REST_Response( [ 'error' => 'Domain not found or not in forest program' ], 404 );
        }
        
        // Get trees
        $dom_trees = $wpdb->get_results( $wpdb->prepare(
            "SELECT species_name, project_name, country, co2_lifetime, certificate_url, planted_at
             FROM `{$trees}` 
             WHERE domain_id = %d 
             ORDER BY planted_at DESC",
            $dom->id
        ) );
        
        return new WP_REST_Response( [
            'domain'       => $domain,
            'trees_count'  => count( $dom_trees ),
            'co2_total_kg' => array_sum( array_column( $dom_trees, 'co2_lifetime' ) ),
            'trees'        => $dom_trees,
        ], 200 );
    }

    /* ══════════════════════════════════════════════════════════════
       UPMIND RENEWAL HOOK
    ══════════════════════════════════════════════════════════════ */

    /**
     * Handle Upmind service.renewed webhook (triggered by Upmind Integration)
     * 
     * This updates renewal data and potentially queues tree planting.
     *
     * @param string $domain     The domain being renewed
     * @param array  $update_data Data that was updated (client info, renewal date, etc.)
     * @param array  $raw_data    Full webhook payload from Upmind
     */
    public function handle_upmind_renewal( string $domain, array $update_data, array $raw_data ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'dominios_reseller';
        
        $this->log( "Upmind renewal hook received for {$domain}" );
        
        // Get full domain record
        $dom = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE domain = %s LIMIT 1",
            $domain
        ) );
        
        if ( ! $dom ) {
            $this->log( "Domain {$domain} not found in database" );
            return;
        }
        
        // Check if eligible for Forest Program
        if ( empty( $dom->forest_enabled ) ) {
            $this->log( "Domain {$domain} does not have forest_enabled" );
            return;
        }
        
        $product_slug  = strtolower( $dom->upmind_product_slug ?? '' );
        $billing_cycle = strtolower( $dom->billing_cycle ?? '' );
        
        // Verify eligible product
        if ( ! in_array( $product_slug, [ 'roble', 'cedro', 'sauce' ], true ) ) {
            $this->log( "Domain {$domain} product '{$product_slug}' not eligible" );
            return;
        }
        
        // Verify annual cycle
        if ( ! in_array( $billing_cycle, [ 'annual', 'yearly', 'annually' ], true ) ) {
            $this->log( "Domain {$domain} billing cycle '{$billing_cycle}' not eligible (must be annual)" );
            return;
        }
        
        // Check if already planted this year
        $one_year_ago = date( 'Y-m-d', strtotime( '-11 months' ) );
        $last_planted = $dom->last_tree_planted ?? null;
        
        if ( $last_planted && $last_planted > $one_year_ago ) {
            $this->log( "Domain {$domain} already has tree planted on {$last_planted}, skipping" );
            return;
        }
        
        // Check if already in queue
        $queue_table = $wpdb->prefix . 'dr_tree_planting_queue';
        $in_queue = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM `{$queue_table}` WHERE domain_id = %d AND status = 'pending'",
            $dom->id
        ) );
        
        if ( $in_queue ) {
            $this->log( "Domain {$domain} already in planting queue" );
            return;
        }
        
        // Add to queue immediately (renewal just happened, time to plant)
        $queued = $wpdb->insert( $queue_table, [
            'domain_id'    => $dom->id,
            'scheduled_at' => current_time( 'mysql' ),
            'status'       => 'pending',
            'reason'       => 'service_renewed_webhook',
            'created_at'   => current_time( 'mysql' ),
        ], [ '%d', '%s', '%s', '%s', '%s' ] );
        
        if ( $queued ) {
            $this->log( "Domain {$domain} queued for tree planting (service renewed)" );
            
            // Optionally process immediately
            // $this->cron_process_queue();
        }
    }

    /* ══════════════════════════════════════════════════════════════
       LOGGING & MONITORING
    ══════════════════════════════════════════════════════════════ */

    /**
     * Log message to database and optionally error_log
     * 
     * @param string $message Log message
     * @param string $level info|warning|error|critical
     * @param int|null $domain_id Optional domain ID for context
     * @param int|null $queue_id Optional queue ID for context
     */
    private function log( string $message, string $level = 'info', ?int $domain_id = null, ?int $queue_id = null ): void {
        global $wpdb;

        // Respect global logging toggle (errors/criticals always logged for safety)
        $settings = self::get_settings();
        $logging_enabled = ! empty( $settings['enable_logging'] );
        if ( ! $logging_enabled && ! in_array( $level, [ 'error', 'critical' ], true ) ) {
            return;
        }
        
        // Always log to error_log in debug mode
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "[DR Forest Program][{$level}] {$message}" );
        }
        
        // Log to database for auditing
        $log_table = $wpdb->prefix . 'dr_forest_log';
        
        // Check if table exists (may not exist before first activation)
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$log_table}'" );
        if ( ! $table_exists ) {
            return;
        }
        
        $wpdb->insert( $log_table, [
            'level'     => $level,
            'message'   => $message,
            'domain_id' => $domain_id,
            'queue_id'  => $queue_id,
        ], [ '%s', '%s', '%d', '%d' ] );
        
        // Auto-cleanup: Remove logs older than 90 days (once per day)
        $last_cleanup = get_transient( 'dr_forest_log_cleanup' );
        if ( ! $last_cleanup ) {
            $wpdb->query( "DELETE FROM `{$log_table}` WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)" );
            set_transient( 'dr_forest_log_cleanup', time(), DAY_IN_SECONDS );
        }
    }

    /**
     * Get recent logs for admin dashboard
     */
    public function ajax_get_logs(): void {
        check_ajax_referer( 'dr_forest_nonce', '_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permisos insuficientes' );
        }
        
        global $wpdb;
        $log_table = $wpdb->prefix . 'dr_forest_log';
        
        $level = sanitize_text_field( $_POST['level'] ?? 'all' );
        $page  = max( 1, absint( $_POST['page'] ?? 1 ) );
        $per_page = 50;
        $offset = ( $page - 1 ) * $per_page;
        
        $where = '1=1';
        if ( $level !== 'all' ) {
            $where = $wpdb->prepare( "level = %s", $level );
        }
        
        $total = $wpdb->get_var( "SELECT COUNT(*) FROM `{$log_table}` WHERE {$where}" );
        
        $logs = $wpdb->get_results( $wpdb->prepare( "
            SELECT * FROM `{$log_table}` 
            WHERE {$where}
            ORDER BY created_at DESC 
            LIMIT %d OFFSET %d
        ", $per_page, $offset ) );
        
        wp_send_json_success( [
            'logs'     => $logs,
            'total'    => (int) $total,
            'page'     => $page,
            'per_page' => $per_page,
            'pages'    => ceil( $total / $per_page ),
        ] );
    }

    /**
     * Get failed queue items for admin review
     */
    public function ajax_get_failed(): void {
        check_ajax_referer( 'dr_forest_nonce', '_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permisos insuficientes' );
        }
        
        global $wpdb;
        $queue = $wpdb->prefix . 'dr_tree_planting_queue';
        
        $failed = $wpdb->get_results( "
            SELECT * FROM `{$queue}` 
            WHERE status = 'failed'
            ORDER BY created_at DESC
            LIMIT 100
        " );
        
        wp_send_json_success( [ 'failed' => $failed ] );
    }

    /**
     * Retry a failed queue item
     */
    public function ajax_retry_failed(): void {
        check_ajax_referer( 'dr_forest_nonce', '_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permisos insuficientes' );
        }
        
        global $wpdb;
        $queue = $wpdb->prefix . 'dr_tree_planting_queue';
        
        $queue_id = absint( $_POST['queue_id'] ?? 0 );
        if ( ! $queue_id ) {
            wp_send_json_error( 'ID de cola inválido' );
        }
        
        // Reset to pending and clear attempts
        $result = $wpdb->update( $queue,
            [ 'status' => 'pending', 'attempts' => 0, 'last_error' => null ],
            [ 'id' => $queue_id, 'status' => 'failed' ],
            [ '%s', '%d', '%s' ],
            [ '%d', '%s' ]
        );
        
        if ( $result ) {
            $this->log( "Failed item {$queue_id} reset for retry", 'info' );
            wp_send_json_success( [ 'message' => 'Item reintentado' ] );
        } else {
            wp_send_json_error( 'No se pudo reintentar' );
        }
    }

    /**
     * Resend the per-tree email to the client (admin manual action).
     */
    public function ajax_resend_email(): void {
        check_ajax_referer( 'dr_forest_nonce', '_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permisos insuficientes' );
        }

        $tree_id = absint( $_POST['tree_id'] ?? 0 );
        if ( ! $tree_id ) {
            wp_send_json_error( 'ID de árbol inválido' );
        }

        $sent = $this->send_tree_email( $tree_id );
        if ( $sent ) {
            wp_send_json_success( [ 'message' => 'Email reenviado' ] );
        } else {
            wp_send_json_error( 'No se pudo reenviar el email (revisa logs)' );
        }
    }

    /**
     * Forecast of upcoming plantings, derived from `next_renewal_date` of
     * eligible domains. Returns counts for the next 30/60/90 days.
     */
    public function ajax_get_forecast(): void {
        check_ajax_referer( 'dr_forest_nonce', '_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permisos insuficientes' );
        }

        global $wpdb;
        $domains = $wpdb->prefix . 'dominios_reseller';
        $today   = current_time( 'Y-m-d' );

        // A planting is queued ~60 days before renewal, so a renewal in N days
        // implies a planting in (N - 60) days.
        // Forecast window for plantings = renewals between (today + 60) and (today + 60 + window)
        $bucket = function( int $days ) use ( $wpdb, $domains, $today ) {
            $start = date( 'Y-m-d', strtotime( $today . ' +' . self::PLANT_DAYS_BEFORE . ' days' ) );
            $end   = date( 'Y-m-d', strtotime( $today . ' +' . ( self::PLANT_DAYS_BEFORE + $days ) . ' days' ) );
            return (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$domains}`
                 WHERE is_primary = 1
                   AND forest_enabled = 1
                   AND upmind_product_slug IN ('roble','cedro','sauce')
                   AND billing_cycle IN ('annual','yearly','annually')
                   AND next_renewal_date BETWEEN %s AND %s",
                $start, $end
            ) );
        };

        // Last 12 months of plantings (history series)
        $trees = $wpdb->prefix . 'dr_planted_trees';
        $series = $wpdb->get_results(
            "SELECT DATE_FORMAT(planted_at, '%Y-%m') AS ym, COUNT(*) AS c, COALESCE(SUM(co2_lifetime),0) AS co2
             FROM `{$trees}`
             WHERE planted_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
             GROUP BY ym
             ORDER BY ym ASC"
        );

        // Credits forecast: avg trees/month from last 90 days × runway
        $avg_per_month = (float) $wpdb->get_var(
            "SELECT COUNT(*) / 3.0 FROM `{$trees}`
             WHERE planted_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );
        $credits_info = $this->check_tn_credits();
        $credits      = is_wp_error( $credits_info ) ? null : (int) ( $credits_info['credits'] ?? 0 );
        $runway_days  = ( $avg_per_month > 0 && $credits !== null )
            ? (int) round( ( $credits / $avg_per_month ) * 30 )
            : null;

        wp_send_json_success( [
            'next_30_days' => $bucket( 30 ),
            'next_60_days' => $bucket( 60 ),
            'next_90_days' => $bucket( 90 ),
            'monthly'      => $series ?: [],
            'avg_per_month' => round( $avg_per_month, 1 ),
            'credits'      => $credits,
            'runway_days'  => $runway_days,
        ] );
    }

    /**
     * Send activity summary email (daily or weekly) — called by cron.
     *
     * Range covered:
     *   - daily  : the previous calendar day
     *   - weekly : the last 7 calendar days (rolling window)
     *
     * Honors `summary_frequency` setting ('disabled' silences the cron firing here).
     */
    public function send_daily_summary(): void {
        $settings  = self::get_settings();
        $frequency = $settings['summary_frequency'] ?? 'weekly';

        if ( $frequency === 'disabled' ) {
            return;
        }

        $alert_email = $settings['alert_email'] ?: get_option( 'admin_email' );
        if ( ! $alert_email || ! is_email( $alert_email ) ) {
            return;
        }

        global $wpdb;
        $trees_t = $wpdb->prefix . 'dr_planted_trees';
        $queue_t = $wpdb->prefix . 'dr_tree_planting_queue';
        $log_t   = $wpdb->prefix . 'dr_forest_log';

        if ( $frequency === 'weekly' ) {
            $period_label = 'Últimos 7 días';
            $range_start  = date( 'Y-m-d', strtotime( '-6 days' ) );
            $range_end    = date( 'Y-m-d' );
            $where_planted = $wpdb->prepare( "DATE(planted_at) BETWEEN %s AND %s", $range_start, $range_end );
            $where_queue   = $wpdb->prepare( "DATE(created_at) BETWEEN %s AND %s", $range_start, $range_end );
            $where_log     = $wpdb->prepare( "DATE(created_at) BETWEEN %s AND %s", $range_start, $range_end );
        } else {
            $yesterday    = date( 'Y-m-d', strtotime( '-1 day' ) );
            $period_label = $yesterday;
            $range_start  = $yesterday;
            $range_end    = $yesterday;
            $where_planted = $wpdb->prepare( "DATE(planted_at) = %s", $yesterday );
            $where_queue   = $wpdb->prepare( "DATE(created_at) = %s", $yesterday );
            $where_log     = $wpdb->prepare( "DATE(created_at) = %s", $yesterday );
        }

        $planted = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$trees_t}` WHERE {$where_planted}" );
        $failed  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$queue_t}` WHERE status = 'failed' AND {$where_queue}" );
        $errors  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$log_t}` WHERE level IN ('error','critical') AND {$where_log}" );

        // Top 5 distinct error messages in the period (for actionable summary)
        $top_errors = $wpdb->get_results(
            "SELECT message, COUNT(*) AS c FROM `{$log_t}`
             WHERE level IN ('error','critical') AND {$where_log}
             GROUP BY message ORDER BY c DESC LIMIT 5"
        );

        // Skip silent periods (no activity, no issues)
        if ( $planted === 0 && $failed === 0 && $errors === 0 ) {
            return;
        }

        // Avoid duplicate sends within the same day
        $today = date( 'Y-m-d' );
        if ( ( $settings['last_summary_sent'] ?? '' ) === $today ) {
            return;
        }

        $credits_info = $this->check_tn_credits();
        $credits = is_wp_error( $credits_info ) ? '?' : (int) ( $credits_info['credits'] ?? 0 );

        $subject = sprintf( '[Replanta Forest] Resumen %s · %s', $frequency === 'weekly' ? 'semanal' : 'diario', $period_label );

        $admin_url = admin_url( 'admin.php?page=dominios-reseller-forest&tab=logs' );
        $html      = $this->render_summary_html( [
            'frequency'    => $frequency,
            'period_label' => $period_label,
            'range_start'  => $range_start,
            'range_end'    => $range_end,
            'planted'      => $planted,
            'failed'       => $failed,
            'errors'       => $errors,
            'credits'      => $credits,
            'top_errors'   => $top_errors ?: [],
            'admin_url'    => $admin_url,
        ] );

        $from_name  = $settings['email_from_name']  ?: 'Replanta';
        $from_email = $settings['email_from_email'] ?: get_option( 'admin_email' );
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            sprintf( 'From: %s <%s>', $from_name, $from_email ),
        ];

        $sent = wp_mail( $alert_email, $subject, $html, $headers );

        if ( $sent ) {
            $settings['last_summary_sent'] = $today;
            update_option( self::OPTION_KEY, $settings );
            $this->log( "Summary ({$frequency}) sent to {$alert_email}" );
        }
    }

    /**
     * Render the HTML body for the activity summary email.
     */
    private function render_summary_html( array $data ): string {
        $frequency    = $data['frequency'];
        $period_label = esc_html( $data['period_label'] );
        $planted      = (int) $data['planted'];
        $failed       = (int) $data['failed'];
        $errors       = (int) $data['errors'];
        $credits      = $data['credits'];
        $top_errors   = $data['top_errors'];
        $admin_url    = esc_url( $data['admin_url'] );

        $title = $frequency === 'weekly' ? 'Resumen semanal' : 'Resumen diario';

        $errors_html = '';
        if ( ! empty( $top_errors ) ) {
            $errors_html .= '<h3 style="font-size:15px;color:#0f172a;margin:24px 0 8px;">Errores más frecuentes</h3>';
            $errors_html .= '<ol style="margin:0;padding-left:20px;color:#334155;font-size:13px;line-height:1.55;">';
            foreach ( $top_errors as $row ) {
                $msg = esc_html( wp_trim_words( $row->message, 22, '…' ) );
                $errors_html .= sprintf(
                    '<li style="margin-bottom:6px;"><strong>×%d</strong> %s</li>',
                    (int) $row->c, $msg
                );
            }
            $errors_html .= '</ol>';
        }

        $stat = function( $value, $label, $color ) {
            return sprintf(
                '<td class="stat-cell" style="width:25%%;background:%s;border-radius:12px;padding:18px 12px;text-align:center;color:#fff;">'
                . '<div style="font-size:28px;font-weight:700;line-height:1;">%s</div>'
                . '<div style="font-size:12px;margin-top:6px;opacity:.9;">%s</div></td>',
                esc_attr( $color ), esc_html( (string) $value ), esc_html( $label )
            );
        };

        ob_start(); ?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo esc_html( $title ); ?></title>
<style>
@media screen and (max-width:600px){.wrapper{width:100%!important;padding:12px!important}.content{padding:24px 18px!important}.stat-grid{display:block!important}.stat-cell{display:block!important;width:100%!important;margin-bottom:8px!important}}
</style></head>
<body style="margin:0;padding:0;background:#f0fdf4;font-family:'Segoe UI',Tahoma,sans-serif;color:#0f172a;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f0fdf4;">
  <tr><td align="center" style="padding:24px 12px;">
    <table role="presentation" class="wrapper" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 2px 12px rgba(15,23,42,.06);">
      <tr><td style="background:linear-gradient(135deg,#16a34a 0%,#22c55e 100%);padding:28px 32px;color:#fff;">
        <div style="font-size:13px;opacity:.85;letter-spacing:.5px;text-transform:uppercase;">Replanta · Forest Program</div>
        <div style="font-size:24px;font-weight:700;margin-top:4px;"><?php echo esc_html( $title ); ?></div>
        <div style="font-size:14px;opacity:.9;margin-top:2px;">Periodo: <?php echo $period_label; ?></div>
      </td></tr>
      <tr><td class="content" style="padding:28px 32px;">
        <table role="presentation" class="stat-grid" width="100%" cellpadding="6" cellspacing="0"><tr>
          <?php echo $stat( $planted, 'Árboles plantados', '#16a34a' ); ?>
          <?php echo $stat( $failed,  'Fallidas',          '#ef4444' ); ?>
          <?php echo $stat( $errors,  'Errores',           '#f59e0b' ); ?>
          <?php echo $stat( $credits, 'Créditos TN',       '#0ea5e9' ); ?>
        </tr></table>

        <?php if ( $failed > 0 ) : ?>
        <div style="margin-top:20px;padding:14px 16px;background:#fef2f2;border-left:4px solid #ef4444;border-radius:8px;color:#991b1b;font-size:13px;line-height:1.5;">
          Hay <strong><?php echo $failed; ?></strong> plantaciones fallidas pendientes de revisión.
        </div>
        <?php endif; ?>

        <?php echo $errors_html; ?>

        <div style="margin-top:28px;text-align:center;">
          <a href="<?php echo $admin_url; ?>" style="display:inline-block;background:#16a34a;color:#fff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:600;font-size:14px;">Abrir panel de logs</a>
        </div>

        <p style="margin:28px 0 0;font-size:12px;color:#64748b;line-height:1.5;">
          Recibes este correo porque eres el destinatario configurado en <em>Forest Program → Alertas</em>.
          Puedes cambiar la frecuencia (diaria / semanal / desactivada) en los ajustes del plugin.
        </p>
      </td></tr>
    </table>
    <div style="margin-top:16px;font-size:11px;color:#64748b;">© Replanta · <?php echo esc_html( date( 'Y' ) ); ?></div>
  </td></tr>
</table>
</body></html>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Schedule (or unschedule) the activity summary cron based on settings.
     */
    public function schedule_daily_summary(): void {
        $settings  = self::get_settings();
        $frequency = $settings['summary_frequency'] ?? 'weekly';
        $hook      = 'dr_forest_daily_summary';

        // Disabled: clear any existing schedule and bail
        if ( $frequency === 'disabled' ) {
            $ts = wp_next_scheduled( $hook );
            if ( $ts ) {
                wp_unschedule_event( $ts, $hook );
            }
            return;
        }

        $desired_recurrence = $frequency === 'weekly' ? 'weekly' : 'daily';
        $existing_ts        = wp_next_scheduled( $hook );

        // If schedule exists but with wrong recurrence, reschedule
        if ( $existing_ts ) {
            $event = wp_get_scheduled_event( $hook );
            if ( $event && isset( $event->schedule ) && $event->schedule !== $desired_recurrence ) {
                wp_unschedule_event( $existing_ts, $hook );
                $existing_ts = false;
            }
        }

        if ( ! $existing_ts ) {
            $first = $desired_recurrence === 'weekly'
                ? strtotime( 'next monday 08:00' )
                : strtotime( 'tomorrow 08:00' );
            wp_schedule_event( $first, $desired_recurrence, $hook );
        }
    }

    /* ══════════════════════════════════════════════════════════════
       PUBLIC TRANSPARENCY
    ══════════════════════════════════════════════════════════════ */

    /**
     * REST: Recent plantings (anonymized) for public transparency page.
     */
    public function rest_forest_recent( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;
        $trees = $wpdb->prefix . 'dr_planted_trees';

        $limit = max( 1, min( 200, (int) $request->get_param( 'limit' ) ) );

        $cache_key = 'dr_forest_recent_' . $limit;
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) {
            return new WP_REST_Response( $cached, 200 );
        }

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT domain, species_name, project_name, country, co2_lifetime,
                    certificate_url, planted_at, verification_status
             FROM `{$trees}`
             ORDER BY planted_at DESC
             LIMIT %d",
            $limit
        ) );

        $items = array_map( function( $r ) {
            return [
                'domain'              => $this->anonymize_domain( $r->domain ),
                'species'             => $r->species_name,
                'project'             => $r->project_name,
                'country'             => $r->country,
                'co2_kg'              => (float) $r->co2_lifetime,
                'certificate_url'     => $r->certificate_url,
                'planted_at'          => $r->planted_at,
                'verification_status' => $r->verification_status ?: 'unverified',
            ];
        }, $rows );

        set_transient( $cache_key, $items, 15 * MINUTE_IN_SECONDS );
        return new WP_REST_Response( $items, 200 );
    }

    /**
     * Anonymize a domain for public display (e.g. midomain.com → m******n.com).
     */
    private function anonymize_domain( string $domain ): string {
        $parts = explode( '.', $domain, 2 );
        $name  = $parts[0] ?? $domain;
        $tld   = $parts[1] ?? '';
        $len   = mb_strlen( $name );
        if ( $len <= 2 ) {
            return $domain;
        }
        $masked = mb_substr( $name, 0, 1 ) . str_repeat( '*', max( 1, $len - 2 ) ) . mb_substr( $name, -1 );
        return $tld ? $masked . '.' . $tld : $masked;
    }

    /**
     * Shortcode [replanta_bosque_publico] — renders a transparency widget.
     * Pulls from the same REST endpoints client-side to leverage caching.
     */
    public static function shortcode_public_forest( $atts = [] ): string {
        $atts = shortcode_atts( [ 'limit' => 30 ], $atts, 'replanta_bosque_publico' );
        $limit = absint( $atts['limit'] );
        $base  = esc_url( rest_url( 'dr/v1/forest' ) );

        ob_start(); ?>
        <div class="rb-forest-public" data-rb-base="<?php echo $base; ?>" data-rb-limit="<?php echo $limit; ?>">
            <div class="rb-forest-stats" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin:0 0 24px;">
                <div class="rb-stat" style="background:#f0fdf4;padding:18px;border-radius:12px;text-align:center;">
                    <div class="rb-stat-num" data-rb-stat="total_trees" style="font-size:28px;font-weight:700;color:#16a34a;">—</div>
                    <div style="font-size:12px;color:#64748b;margin-top:4px;">Árboles plantados</div>
                </div>
                <div class="rb-stat" style="background:#f0fdf4;padding:18px;border-radius:12px;text-align:center;">
                    <div class="rb-stat-num" data-rb-stat="co2_kg" style="font-size:28px;font-weight:700;color:#16a34a;">—</div>
                    <div style="font-size:12px;color:#64748b;margin-top:4px;">kg CO₂ capturados</div>
                </div>
                <div class="rb-stat" style="background:#f0fdf4;padding:18px;border-radius:12px;text-align:center;">
                    <div class="rb-stat-num" data-rb-stat="countries" style="font-size:28px;font-weight:700;color:#16a34a;">—</div>
                    <div style="font-size:12px;color:#64748b;margin-top:4px;">Países</div>
                </div>
                <div class="rb-stat" style="background:#f0fdf4;padding:18px;border-radius:12px;text-align:center;">
                    <div class="rb-stat-num" data-rb-stat="species" style="font-size:28px;font-weight:700;color:#16a34a;">—</div>
                    <div style="font-size:12px;color:#64748b;margin-top:4px;">Especies</div>
                </div>
            </div>
            <h3 style="margin:0 0 12px;font-size:18px;color:#0f172a;">Plantaciones recientes</h3>
            <div class="rb-forest-list" style="border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;">
                <div class="rb-forest-loading" style="padding:24px;text-align:center;color:#64748b;">Cargando bosque…</div>
            </div>
        </div>
        <script>
        (function(){
            var root = document.currentScript.previousElementSibling;
            if (!root || !root.classList.contains('rb-forest-public')) return;
            var base  = root.dataset.rbBase;
            var limit = root.dataset.rbLimit || 30;
            function setStat(k, v){ var el = root.querySelector('[data-rb-stat="'+k+'"]'); if (el) el.textContent = v; }
            fetch(base + '/stats').then(function(r){return r.json();}).then(function(s){
                setStat('total_trees', (s.total_trees||0).toLocaleString('es'));
                setStat('co2_kg',      Math.round(s.co2_kg||0).toLocaleString('es'));
                setStat('countries',   s.countries||0);
                setStat('species',     s.species||0);
            }).catch(function(){});
            fetch(base + '/recent?limit=' + limit).then(function(r){return r.json();}).then(function(items){
                var list = root.querySelector('.rb-forest-list');
                if (!Array.isArray(items) || !items.length) { list.innerHTML = '<div style="padding:18px;color:#64748b;">Aún no hay plantaciones registradas.</div>'; return; }
                list.innerHTML = items.map(function(it){
                    var date = it.planted_at ? new Date(it.planted_at.replace(' ','T')).toLocaleDateString('es') : '';
                    var verified = it.verification_status === 'ok' ? '<span title="Verificado con Tree-Nation" style="color:#16a34a;font-size:12px;margin-left:6px;">✓</span>' : '';
                    var cert = it.certificate_url ? '<a href="'+it.certificate_url+'" target="_blank" rel="noopener" style="font-size:12px;color:#16a34a;">certificado</a>' : '';
                    return '<div style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1px solid #f1f5f9;font-size:14px;">'
                        +    '<div><strong>'+ (it.species||'Árbol') +'</strong>'+ verified +'<div style="font-size:12px;color:#64748b;">'+ (it.country||'') +' · '+ (it.project||'') +'</div></div>'
                        +    '<div style="text-align:right;"><div style="color:#64748b;font-size:12px;">'+ date +' · '+ it.domain +'</div>'+ cert +'</div>'
                        +  '</div>';
                }).join('');
            }).catch(function(){
                root.querySelector('.rb-forest-list').innerHTML = '<div style="padding:18px;color:#dc2626;">No se pudo cargar el bosque.</div>';
            });
        })();
        </script>
        <?php
        return (string) ob_get_clean();
    }

    /* ══════════════════════════════════════════════════════════════
       RECONCILIATION (cron weekly)
       Verify each planted tree still exists in Tree-Nation.
       Detect drift between local DB and provider.
    ══════════════════════════════════════════════════════════════ */

    public function cron_reconcile(): void {
        $settings = self::get_settings();
        if ( empty( $settings['tn_api_token'] ) ) {
            $this->log( 'Reconcile skipped: Tree-Nation token not configured', 'warning' );
            return;
        }
        if ( ! empty( $settings['dry_run_mode'] ) ) {
            $this->log( 'Reconcile skipped: dry-run mode active', 'info' );
            return;
        }

        global $wpdb;
        $trees = $wpdb->prefix . 'dr_planted_trees';

        // Verify in batches: oldest unverified first, plus any that errored before.
        // Skip DRY-RUN entries (no real tree_nation_id).
        $batch = $wpdb->get_results(
            "SELECT id, tree_nation_id, domain
             FROM `{$trees}`
             WHERE tree_nation_id IS NOT NULL
               AND tree_nation_id NOT LIKE 'DRY-RUN-%'
               AND ( verification_status IN ('unverified','error')
                     OR verified_at < DATE_SUB(NOW(), INTERVAL 90 DAY) )
             ORDER BY COALESCE(verified_at, '1970-01-01') ASC, id ASC
             LIMIT 25"
        );

        if ( empty( $batch ) ) {
            $this->log( 'Reconcile: nothing to verify', 'info' );
            return;
        }

        $ok = 0; $missing = 0; $errored = 0;
        foreach ( $batch as $row ) {
            $status = $this->verify_tree_with_tn( (int) $row->tree_nation_id, $settings );
            $wpdb->update( $trees,
                [ 'verification_status' => $status, 'verified_at' => current_time( 'mysql' ) ],
                [ 'id' => $row->id ],
                [ '%s', '%s' ],
                [ '%d' ]
            );
            if ( $status === 'ok' )      { $ok++; }
            elseif ( $status === 'missing' ) {
                $missing++;
                $this->log( "RECONCILE: tree {$row->tree_nation_id} ({$row->domain}) MISSING in Tree-Nation", 'critical', null, null );
            } else {
                $errored++;
            }
        }

        $msg = sprintf( 'Reconcile batch: %d ok, %d missing, %d errored', $ok, $missing, $errored );
        $this->log( $msg, $missing > 0 ? 'warning' : 'info' );

        // Alert if drift detected
        if ( $missing > 0 ) {
            $this->send_reconcile_alert( $missing, $batch );
        }
    }

    /**
     * Verify a single tree exists in Tree-Nation.
     *
     * @return string 'ok' | 'missing' | 'error'
     */
    private function verify_tree_with_tn( int $tree_id, array $settings ): string {
        $url = self::TN_API_LIVE . '/trees/' . $tree_id;
        $response = wp_remote_get( $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $settings['tn_api_token'],
                'Accept'        => 'application/json',
            ],
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            return 'error';
        }
        $code = wp_remote_retrieve_response_code( $response );
        if ( $code === 200 ) {
            return 'ok';
        }
        if ( $code === 404 ) {
            return 'missing';
        }
        return 'error';
    }

    /**
     * Send alert email when reconciliation detects missing trees.
     */
    private function send_reconcile_alert( int $missing_count, array $batch ): void {
        $settings = self::get_settings();
        $to = $settings['alert_email'] ?: get_option( 'admin_email' );
        if ( ! $to ) return;

        $list = '';
        foreach ( $batch as $row ) {
            $list .= sprintf( "<li><code>%s</code> · TN ID %d</li>", esc_html( $row->domain ), (int) $row->tree_nation_id );
        }
        $html = '<h2 style="color:#b91c1c;">⚠️ Reconciliación Forest Program</h2>'
              . '<p>Se han detectado <strong>' . (int) $missing_count . '</strong> árboles registrados localmente que ya no existen en Tree-Nation.</p>'
              . '<p>Lote verificado:</p><ul>' . $list . '</ul>'
              . '<p>Revisa la pestaña <em>Logs / Fallidos</em> y abre incidencia con Tree-Nation si procede.</p>';

        wp_mail(
            $to,
            '[Replanta Forest] Reconciliación: ' . $missing_count . ' árboles faltantes',
            $html,
            [ 'Content-Type: text/html; charset=UTF-8' ]
        );
    }
}

// Shortcode for public transparency page
add_shortcode( 'replanta_bosque_publico', [ 'Dominios_Reseller_Forest_Program', 'shortcode_public_forest' ] );

// Register cron interval
add_filter( 'cron_schedules', [ 'Dominios_Reseller_Forest_Program', 'add_cron_interval' ] );

// Schedule daily summary
add_action( 'init', function() {
    $instance = Dominios_Reseller_Forest_Program::get_instance();
    $instance->schedule_daily_summary();
} );

// Hook for daily summary
add_action( 'dr_forest_daily_summary', function() {
    $instance = Dominios_Reseller_Forest_Program::get_instance();
    $instance->send_daily_summary();
} );
