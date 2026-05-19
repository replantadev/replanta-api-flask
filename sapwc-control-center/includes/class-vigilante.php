<?php
/**
 * SAPWCC_Vigilante — Proactive sync monitoring engine.
 *
 * Polls each managed site's /control/pending-issues endpoint on a cron schedule,
 * analyses the response against known failure patterns, stores results, and
 * triggers alerts when new critical issues are detected.
 *
 * @package SAPWCC
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SAPWCC_Vigilante {

    const CRON_HOOK      = 'sapwcc_vigilante_scan';
    const CRON_DIGEST    = 'sapwcc_vigilante_digest';
    const RESULTS_OPTION = 'sapwcc_vig_results';

    const SEV_CRITICAL = 'critical';
    const SEV_WARNING  = 'warning';
    const SEV_INFO     = 'info';

    // ── Lifecycle ────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( self::CRON_HOOK,   [ __CLASS__, 'run_scheduled_scan' ] );
        add_action( self::CRON_DIGEST, [ __CLASS__, 'run_weekly_digest' ] );
    }

    public static function schedule(): void {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + 60, 'hourly', self::CRON_HOOK );
        }
        if ( ! wp_next_scheduled( self::CRON_DIGEST ) ) {
            // Weekly on Mondays at 08:00 local time.
            $next_monday = strtotime( 'next monday 08:00' );
            wp_schedule_event( $next_monday, 'weekly', self::CRON_DIGEST );
        }
    }

    public static function unschedule(): void {
        wp_clear_scheduled_hook( self::CRON_HOOK );
        wp_clear_scheduled_hook( self::CRON_DIGEST );
    }

    // ── Cron callbacks ───────────────────────────────────────────────────────

    public static function run_scheduled_scan(): void {
        foreach ( array_keys( SAPWCC_Sites::get_all() ) as $key ) {
            self::scan_site( $key );
        }
    }

    public static function run_weekly_digest(): void {
        if ( get_option( 'sapwcc_vig_digest_enabled', '1' ) !== '1' ) {
            return;
        }
        SAPWCC_Alerting::send_weekly_digest();
    }

    // ── Core scan ────────────────────────────────────────────────────────────

    /**
     * Scan a single site, store results, and fire alert for new critical issues.
     *
     * @param string $site_key
     * @return array Issues array (empty on fetch failure).
     */
    public static function scan_site( string $site_key ): array {
        $sites = SAPWCC_Sites::get_all();
        if ( ! isset( $sites[ $site_key ] ) ) {
            return [];
        }

        $site   = $sites[ $site_key ];
        $url    = rtrim( $site['url'], '/' ) . '/wp-json/sapwc/v1/control/pending-issues';
        $secret = SAPWCC_Sites::get_decrypted_secret( $site_key );

        $response = wp_remote_get( $url, [
            'timeout'   => 20,
            'sslverify' => ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ),
            'headers'   => [ 'X-SAPWC-Secret' => $secret ],
        ] );

        $all_results = get_option( self::RESULTS_OPTION, [] );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            $err_msg                  = is_wp_error( $response )
                ? $response->get_error_message()
                : 'HTTP ' . wp_remote_retrieve_response_code( $response );
            $all_results[ $site_key ] = [
                'scanned_at' => current_time( 'Y-m-d H:i:s' ),
                'scan_error' => $err_msg,
                'issues'     => [],
                'raw'        => [],
            ];
            update_option( self::RESULTS_OPTION, $all_results, false );
            return [];
        }

        $data   = json_decode( wp_remote_retrieve_body( $response ), true );
        $issues = self::analyze( $data ?? [] );
        $prev_issues             = $all_results[ $site_key ]['issues'] ?? [];
        $all_results[ $site_key ] = [
            'scanned_at' => current_time( 'Y-m-d H:i:s' ),
            'issues'     => $issues,
            'raw'        => $data,
        ];
        update_option( self::RESULTS_OPTION, $all_results, false );

        // Immediate alert for issues that weren't present in the previous scan.
        $new_criticals = self::diff_new( $prev_issues, $issues, self::SEV_CRITICAL );
        if ( ! empty( $new_criticals ) ) {
            SAPWCC_Alerting::send_critical_alert( $site['label'], $site_key, $new_criticals );
            SAPWCC_Audit::log( 'vigilante_alert', count( $new_criticals ) . ' nuevos críticos', $site['label'] );
        }

        return $issues;
    }

    // ── Detection rules ──────────────────────────────────────────────────────

    private static function analyze( array $data ): array {
        $issues = [];

        // Rule 1 — Retry-exhausted orders (human action required).
        foreach ( $data['retry_exhausted'] ?? [] as $order ) {
            $issues[] = [
                'id'       => 'retry_exhausted_' . $order['order_id'],
                'type'     => 'retry_exhausted',
                'severity' => self::SEV_CRITICAL,
                'title'    => 'Pedido #' . $order['order_id'] . ' bloqueado — reintentos agotados',
                'detail'   => $order['reason'] ?: 'Sin motivo registrado',
                'since'    => $order['failed_at'] ?? '',
                'context'  => $order,
            ];
        }

        // Rule 2 — Cron silence.
        $gap = (int) ( $data['cron_gap_minutes'] ?? 0 );
        if ( $gap > 90 ) {
            $issues[] = [
                'id'       => 'cron_gap',
                'type'     => 'cron_gap',
                'severity' => $gap > 240 ? self::SEV_CRITICAL : self::SEV_WARNING,
                'title'    => 'Sync automática sin ejecutar desde hace ' . $gap . ' minutos',
                'detail'   => 'El Action Scheduler puede estar detenido o el cron de WP desactivado.',
                'since'    => '',
                'context'  => [ 'gap_minutes' => $gap ],
            ];
        }

        // Rule 3 — Persistent skipped orders.
        $skipped = $data['skipped_persistent'] ?? [];
        if ( ! empty( $skipped['detected'] ) ) {
            $n        = (int) $skipped['consecutive_cycles'];
            $issues[] = [
                'id'       => 'skipped_persistent',
                'type'     => 'skipped_persistent',
                'severity' => self::SEV_WARNING,
                'title'    => 'Pedido en limbo — ignorado en ' . $n . ' crons consecutivos',
                'detail'   => 'Un pedido lleva múltiples ciclos siendo omitido sin fallar ni sincronizarse.',
                'since'    => $skipped['since'] ?? '',
                'context'  => $skipped,
            ];
        }

        // Rule 4 — Error spike.
        $errors_1h = (int) ( $data['errors_1h'] ?? 0 );
        if ( $errors_1h >= 5 ) {
            $issues[] = [
                'id'       => 'errors_spike',
                'type'     => 'errors_spike',
                'severity' => $errors_1h >= 10 ? self::SEV_CRITICAL : self::SEV_WARNING,
                'title'    => $errors_1h . ' errores en la última hora',
                'detail'   => 'Pico inusual. Puede indicar un fallo en SAP B1 o la conexión Service Layer.',
                'since'    => '',
                'context'  => [ 'count' => $errors_1h ],
            ];
        }

        // Rule 5 — Inactive customer in SAP.
        $seen_cardcodes = [];
        foreach ( $data['inactive_customer_errors'] ?? [] as $ie ) {
            $cc = $ie['cardcode'] ?? 'desconocido';
            if ( isset( $seen_cardcodes[ $cc ] ) ) {
                continue; // One issue per CardCode is enough.
            }
            $seen_cardcodes[ $cc ] = true;
            $issues[] = [
                'id'       => 'inactive_customer_' . $cc,
                'type'     => 'inactive_customer',
                'severity' => self::SEV_CRITICAL,
                'title'    => 'Cliente SAP inactivo bloquea pedido(s): ' . $cc,
                'detail'   => 'El socio de negocio ' . $cc . ' está marcado como Inválido en SAP B1.',
                'since'    => $ie['created_at'] ?? '',
                'context'  => $ie,
            ];
        }

        // Rule 6 — Old unsynced orders.
        foreach ( $data['pending_unsynced_old'] ?? [] as $po ) {
            $age      = (int) $po['age_minutes'];
            $issues[] = [
                'id'       => 'pending_old_' . $po['order_id'],
                'type'     => 'pending_old',
                'severity' => $age > 240 ? self::SEV_CRITICAL : self::SEV_WARNING,
                'title'    => 'Pedido #' . $po['order_id'] . ' sin sincronizar (' . $age . ' min)',
                'detail'   => 'Pedido en estado procesando/en espera que no ha llegado a SAP.',
                'since'    => $po['created'] ?? '',
                'context'  => $po,
            ];
        }

        return $issues;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private static function diff_new( array $prev, array $current, string $severity ): array {
        $prev_ids = array_flip( array_column(
            array_filter( $prev, fn( $i ) => $i['severity'] === $severity ),
            'id'
        ) );
        return array_values( array_filter(
            $current,
            fn( $i ) => $i['severity'] === $severity && ! isset( $prev_ids[ $i['id'] ] )
        ) );
    }

    public static function get_all_results(): array {
        return get_option( self::RESULTS_OPTION, [] );
    }

    public static function get_site_result( string $site_key ): array {
        return self::get_all_results()[ $site_key ] ?? [];
    }

    public static function count_by_severity( array $issues, string $severity ): int {
        return count( array_filter( $issues, fn( $i ) => $i['severity'] === $severity ) );
    }

    public static function total_critical_across_sites(): int {
        $total = 0;
        foreach ( self::get_all_results() as $r ) {
            $total += self::count_by_severity( $r['issues'] ?? [], self::SEV_CRITICAL );
        }
        return $total;
    }
}
