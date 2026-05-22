<?php
/**
 * SAPWCC_Vigilante — Proactive sync monitoring engine.
 *
 * Polls each managed site's /control/pending-issues endpoint on a cron schedule,
 * analyses the response against known failure patterns, stores results, and
 * triggers alerts when new critical issues are detected.
 *
 * Alerts are split by audience:
 *   - 'admin'    → CC admin email (technical issues)
 *   - 'sap_user' → SAP contact email stored in each site's plugin (SAP-side issues)
 *
 * Auto-resolution: cron_gap warnings (90–240 min) are auto-fixed by triggering
 * /control/run-cron on the affected site.
 *
 * ROI tracking: resolved issues are counted weekly for the digest email.
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
    const ROI_OPTION         = 'sapwcc_vig_roi';
    const GAP_HISTORY_OPTION = 'sapwcc_vig_gap_history';
    const MAX_GAP_HISTORY    = 60;

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
     * Scan a single site, store results, fire alerts, auto-resolve safe cases.
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

        $data           = json_decode( wp_remote_retrieve_body( $response ), true );
        $resolved_tasks = $data['resolved_tasks']    ?? [];  // {id => {resolved_at, ...}}
        $sap_email      = $data['sap_contact_email'] ?? '';
        $prev_issues    = $all_results[ $site_key ]['issues'] ?? [];

        // Track previous cron_gap for quiet-window recording and suppression.
        $prev_cron_gap  = null;
        foreach ( $prev_issues as $pi ) {
            if ( $pi['type'] === 'cron_gap' ) { $prev_cron_gap = $pi; break; }
        }

        $issues         = self::analyze( $data ?? [] );

        // ── Q1. Record natural gap resolutions for pattern learning ───────────
        $current_cron_gap = null;
        foreach ( $issues as $ci ) {
            if ( $ci['type'] === 'cron_gap' ) { $current_cron_gap = $ci; break; }
        }
        if ( $prev_cron_gap && ! $current_cron_gap ) {
            $prev_scan_at = $all_results[ $site_key ]['scanned_at'] ?? null;
            if ( $prev_scan_at ) {
                $gap_min     = (int) ( $prev_cron_gap['context']['gap_minutes'] ?? 0 );
                $gap_start_h = (int) date( 'G', strtotime( $prev_scan_at ) - $gap_min * 60 );
                $gap_end_h   = (int) current_time( 'G' );
                if ( $gap_start_h !== $gap_end_h ) {
                    self::record_gap_event( $site_key, $gap_start_h, $gap_end_h );
                }
            }
        }

        // ── Q2. Suppress cron_gap during quiet window (manual or auto-learned) ─
        $in_quiet_window = $current_cron_gap !== null && self::is_in_quiet_window( $site_key, $site );
        if ( $in_quiet_window ) {
            $issues = array_values( array_filter( $issues, fn( $i ) => $i['type'] !== 'cron_gap' ) );
        }

        // ── A1. Auto-resolve: cron_gap warning → kick the cron ───────────────
        foreach ( $issues as &$issue ) {
            if ( $issue['type'] === 'cron_gap' && $issue['severity'] === self::SEV_WARNING ) {
                $kicked = self::auto_run_cron( $site, $secret );
                if ( $kicked ) {
                    $issue['auto_resolved'] = true;
                    SAPWCC_Audit::log( 'vigilante_auto_cron', 'Cron relanzado automáticamente (gap: ' . ( $data['cron_gap_minutes'] ?? '?' ) . ' min)', $site['label'] );
                    self::increment_roi( $site_key, 'auto_resueltas' );
                }
            }
        }
        unset( $issue );

        // ── A3. Auto-resolve: missing_ship_to → call /control/repair-ship-to ─
        foreach ( $issues as &$issue ) {
            if ( $issue['type'] === 'missing_ship_to' ) {
                $repair_resp = wp_remote_post(
                    rtrim( $site['url'], '/' ) . '/wp-json/sapwc/v1/control/repair-ship-to',
                    [ 'timeout' => 30, 'headers' => [ 'X-SAPWC-Secret' => $secret ] ]
                );
                if ( ! is_wp_error( $repair_resp ) && 200 === wp_remote_retrieve_response_code( $repair_resp ) ) {
                    $body     = json_decode( wp_remote_retrieve_body( $repair_resp ), true );
                    $repaired = (int) ( $body['repaired'] ?? 0 );
                    if ( $repaired > 0 ) {
                        $issue['auto_resolved'] = true;
                        $issue['detail']       .= " Auto-reparados: $repaired pedido(s).";
                        SAPWCC_Audit::log( 'vigilante_auto_ship_to', "ShipToCode reparado en $repaired pedido(s)", $site['label'] );
                        self::increment_roi( $site_key, 'auto_resueltas' );
                    }
                }
                break;
            }
        }
        unset( $issue );

        // ── A4. Auto-resolve: duplicate_document → call /control/repair-duplicates ─
        foreach ( $issues as &$issue ) {
            if ( $issue['type'] === 'failure_type_duplicate_document' && ! $issue['auto_resolved'] ) {
                $repair_resp = wp_remote_post(
                    rtrim( $site['url'], '/' ) . '/wp-json/sapwc/v1/control/repair-duplicates',
                    [ 'timeout' => 30, 'headers' => [ 'X-SAPWC-Secret' => $secret ] ]
                );
                if ( ! is_wp_error( $repair_resp ) && 200 === wp_remote_retrieve_response_code( $repair_resp ) ) {
                    $body     = json_decode( wp_remote_retrieve_body( $repair_resp ), true );
                    $repaired = (int) ( $body['repaired'] ?? 0 );
                    if ( $repaired > 0 ) {
                        $issue['auto_resolved'] = true;
                        $issue['detail']       .= " Auto-reparados: $repaired pedido(s) duplicados.";
                        SAPWCC_Audit::log( 'vigilante_auto_duplicate', "Pedidos duplicados reparados: $repaired", $site['label'] );
                        self::increment_roi( $site_key, 'auto_resueltas' );
                    }
                }
                break;
            }
        }
        unset( $issue );

        // ── A2. ROI: detect recovered issues ────────────────────────────────
        foreach ( $prev_issues as $prev_issue ) {
            $still_present = false;
            foreach ( $issues as $cur ) {
                if ( $cur['id'] === $prev_issue['id'] ) {
                    $still_present = true;
                    break;
                }
            }
            if ( ! $still_present && in_array( $prev_issue['type'], [ 'retry_exhausted', 'pending_old' ], true ) ) {
                self::increment_roi( $site_key, 'pedidos_recuperados' );
            }
            // Also count recovered classified failures as recovered.
            if ( ! $still_present && str_starts_with( $prev_issue['type'], 'failure_type_' ) ) {
                self::increment_roi( $site_key, 'pedidos_recuperados' );
            }
        }
        if ( ! empty( $issues ) ) {
            self::increment_roi( $site_key, 'incidencias_detectadas', count( $issues ) );
        }

        // ── Store results ─────────────────────────────────────────────────────
        $all_results[ $site_key ] = [
            'scanned_at'        => current_time( 'Y-m-d H:i:s' ),
            'issues'            => $issues,
            'raw'               => $data,
            'resolved_tasks'    => $resolved_tasks,
            'sap_contact_email' => $sap_email,
            'in_quiet_window'   => $in_quiet_window,
        ];
        update_option( self::RESULTS_OPTION, $all_results, false );

        // ── Alerts: split by audience ────────────────────────────────────────
        $new_admin    = self::diff_new_audience( $prev_issues, $issues, self::SEV_CRITICAL, 'admin',    $resolved_tasks );
        $new_sap_user = self::diff_new_audience( $prev_issues, $issues, self::SEV_CRITICAL, 'sap_user', $resolved_tasks );

        if ( ! empty( $new_admin ) ) {
            SAPWCC_Alerting::send_critical_alert( $site['label'], $site_key, $new_admin );
            SAPWCC_Audit::log( 'vigilante_alert', count( $new_admin ) . ' nuevos críticos (admin)', $site['label'] );
        }

        if ( ! empty( $new_sap_user ) && ! empty( $sap_email ) ) {
            SAPWCC_Alerting::send_sap_user_alert( $site['label'], $sap_email, $new_sap_user, $site['url'] );
            SAPWCC_Audit::log( 'vigilante_alert_sap', count( $new_sap_user ) . ' tarea(s) SAP notificadas', $site['label'] );
        }

        return $issues;
    }

    // ── Detection rules ──────────────────────────────────────────────────────

    private static function analyze( array $data ): array {
        $issues = [];

        // Rule 1 — Classified failures (one issue per error type, audience from library).
        // Falls back to legacy retry_exhausted for old plugin versions without classified_failures.
        $classified = $data['classified_failures'] ?? [];
        if ( ! empty( $classified ) ) {
            foreach ( $classified as $type => $bucket ) {
                $count    = (int) $bucket['count'];
                $meta     = $bucket['type_meta'];
                $order_ids = array_column( $bucket['orders'], 'order_id' );
                $issues[] = [
                    'id'           => 'failure_type_' . $type,
                    'type'         => 'failure_type_' . $type,
                    'audience'     => $meta['audience'],
                    'severity'     => ( $meta['severity'] === 'critical' || $count >= 3 ) ? self::SEV_CRITICAL : self::SEV_WARNING,
                    'title'        => $count . ' pedido(s): ' . $meta['title'],
                    'detail'       => ( $meta['detail'] ?? '' ) . ' Afectados: #' . implode( ', #', $order_ids ),
                    'since'        => $bucket['orders'][0]['failed_at'] ?? '',
                    'context'      => [
                        'count'     => $count,
                        'orders'    => $bucket['orders'],
                        'steps_sap' => $meta['steps_sap'] ?? [],
                        'auto_fix'  => $meta['auto_fix'],
                    ],
                    'auto_resolved'=> false,
                ];
            }
        } else {
            // Backward compat: old plugin versions only expose retry_exhausted.
            foreach ( $data['retry_exhausted'] ?? [] as $order ) {
                $issues[] = [
                    'id'           => 'retry_exhausted_' . $order['order_id'],
                    'type'         => 'retry_exhausted',
                    'audience'     => 'admin',
                    'severity'     => self::SEV_CRITICAL,
                    'title'        => 'Pedido #' . $order['order_id'] . ' bloqueado — reintentos agotados',
                    'detail'       => $order['reason'] ?: 'Sin motivo registrado',
                    'since'        => $order['failed_at'] ?? '',
                    'context'      => $order,
                    'auto_resolved'=> false,
                ];
            }
        }

        // Rule 2 — Cron silence.
        $gap = (int) ( $data['cron_gap_minutes'] ?? 0 );
        if ( $gap > 90 ) {
            $issues[] = [
                'id'           => 'cron_gap',
                'type'         => 'cron_gap',
                'audience'     => 'admin',
                'severity'     => $gap > 240 ? self::SEV_CRITICAL : self::SEV_WARNING,
                'title'        => 'Sync automática sin ejecutar desde hace ' . $gap . ' minutos',
                'detail'       => 'El Action Scheduler puede estar detenido o el cron de WP desactivado.',
                'since'        => '',
                'context'      => [ 'gap_minutes' => $gap ],
                'auto_resolved'=> false,
            ];
        }

        // Rule 3 — Persistent skipped orders.
        $skipped = $data['skipped_persistent'] ?? [];
        if ( ! empty( $skipped['detected'] ) ) {
            $n        = (int) $skipped['consecutive_cycles'];
            $issues[] = [
                'id'           => 'skipped_persistent',
                'type'         => 'skipped_persistent',
                'audience'     => 'admin',
                'severity'     => self::SEV_WARNING,
                'title'        => 'Pedido en limbo — ignorado en ' . $n . ' crons consecutivos',
                'detail'       => 'Un pedido lleva múltiples ciclos siendo omitido sin fallar ni sincronizarse.',
                'since'        => $skipped['since'] ?? '',
                'context'      => $skipped,
                'auto_resolved'=> false,
            ];
        }

        // Rule 4 — Error spike.
        $errors_1h = (int) ( $data['errors_1h'] ?? 0 );
        if ( $errors_1h >= 5 ) {
            $issues[] = [
                'id'           => 'errors_spike',
                'type'         => 'errors_spike',
                'audience'     => 'admin',
                'severity'     => $errors_1h >= 10 ? self::SEV_CRITICAL : self::SEV_WARNING,
                'title'        => $errors_1h . ' errores en la última hora',
                'detail'       => 'Pico inusual. Puede indicar un fallo en SAP B1 o la conexión Service Layer.',
                'since'        => '',
                'context'      => [ 'count' => $errors_1h ],
                'auto_resolved'=> false,
            ];
        }

        // Rule 5 — Inactive customer in SAP. → SAP-user audience.
        $seen_cardcodes = [];
        foreach ( $data['inactive_customer_errors'] ?? [] as $ie ) {
            $cc = $ie['cardcode'] ?? 'desconocido';
            if ( isset( $seen_cardcodes[ $cc ] ) ) {
                continue;
            }
            $seen_cardcodes[ $cc ] = true;
            $issues[] = [
                'id'           => 'inactive_customer_' . $cc,
                'type'         => 'inactive_customer',
                'audience'     => 'sap_user',
                'severity'     => self::SEV_CRITICAL,
                'title'        => 'Cliente SAP inactivo bloquea pedido(s): ' . $cc,
                'detail'       => 'El socio de negocio ' . $cc . ' está marcado como Inválido en SAP B1.',
                'since'        => $ie['created_at'] ?? '',
                'context'      => $ie,
                'auto_resolved'=> false,
            ];
        }

        // Rule 6 — Old unsynced orders.
        foreach ( $data['pending_unsynced_old'] ?? [] as $po ) {
            $age      = (int) $po['age_minutes'];
            $issues[] = [
                'id'           => 'pending_old_' . $po['order_id'],
                'type'         => 'pending_old',
                'audience'     => 'admin',
                'severity'     => $age > 240 ? self::SEV_CRITICAL : self::SEV_WARNING,
                'title'        => 'Pedido #' . $po['order_id'] . ' sin sincronizar (' . $age . ' min)',
                'detail'       => 'Pedido en estado procesando/en espera que no ha llegado a SAP.',
                'since'        => $po['created'] ?? '',
                'context'      => $po,
                'auto_resolved'=> false,
            ];
        }

        // Rule 9 — Missing ShipToCode on exported orders.
        $missing_ship_to = $data['missing_ship_to'] ?? [];
        if ( ! empty( $missing_ship_to ) ) {
            $count    = count( $missing_ship_to );
            $order_ids = array_column( $missing_ship_to, 'order_id' );
            $issues[] = [
                'id'           => 'missing_ship_to',
                'type'         => 'missing_ship_to',
                'audience'     => 'admin',
                'severity'     => self::SEV_WARNING,
                'title'        => $count . ' pedido(s) en SAP sin direccion de envio asignada',
                'detail'       => 'ShipToCode vacio: la direccion existe en el BP pero no esta vinculada al pedido SAP. Se reparara automaticamente.',
                'since'        => $missing_ship_to[0]['created'] ?? '',
                'context'      => [ 'order_ids' => $order_ids, 'count' => $count ],
                'auto_resolved'=> false,
            ];
        }

        return $issues;
    }

    // ── Auto-resolution helpers ──────────────────────────────────────────────

    /**
     * Trigger /control/run-cron on a remote site. Returns true on success.
     */
    private static function auto_run_cron( array $site, string $secret ): bool {
        $run_url  = rtrim( $site['url'], '/' ) . '/wp-json/sapwc/v1/control/run-cron';
        $response = wp_remote_post( $run_url, [
            'timeout'   => 15,
            'sslverify' => ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ),
            'headers'   => [ 'X-SAPWC-Secret' => $secret ],
        ] );
        return ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200;
    }

    // ── ROI tracking ─────────────────────────────────────────────────────────

    private static function increment_roi( string $site_key, string $field, int $delta = 1 ): void {
        $roi   = get_option( self::ROI_OPTION, [] );
        $week  = gmdate( 'Y-W' );

        if ( ! isset( $roi[ $site_key ] ) || ( $roi[ $site_key ]['week'] ?? '' ) !== $week ) {
            $roi[ $site_key ] = [
                'week'                   => $week,
                'incidencias_detectadas' => 0,
                'pedidos_recuperados'    => 0,
                'auto_resueltas'         => 0,
            ];
        }

        $roi[ $site_key ][ $field ] = ( $roi[ $site_key ][ $field ] ?? 0 ) + $delta;
        update_option( self::ROI_OPTION, $roi, false );
    }

    public static function get_roi( string $site_key ): array {
        $roi = get_option( self::ROI_OPTION, [] );
        return $roi[ $site_key ] ?? [
            'week'                   => gmdate( 'Y-W' ),
            'incidencias_detectadas' => 0,
            'pedidos_recuperados'    => 0,
            'auto_resueltas'         => 0,
        ];
    }

    public static function get_all_roi(): array {
        return get_option( self::ROI_OPTION, [] );
    }

    // ── Diff helpers ─────────────────────────────────────────────────────────

    /**
     * Return issues that are new (not in prev), filtered by severity + audience,
     * excluding issues resolved by the SAP user within the last 24h.
     *
     * @param array  $prev           Previous scan issues.
     * @param array  $current        Current scan issues.
     * @param string $severity       Severity to filter.
     * @param string $audience       'admin' | 'sap_user'.
     * @param array  $resolved_tasks {task_id => {resolved_at: string, ...}}.
     */
    private static function diff_new_audience( array $prev, array $current, string $severity, string $audience, array $resolved_tasks ): array {
        $now      = time();
        $prev_ids = array_flip( array_column(
            array_filter( $prev, fn( $i ) => $i['severity'] === $severity && ( $i['audience'] ?? 'admin' ) === $audience ),
            'id'
        ) );

        return array_values( array_filter( $current, function ( $i ) use ( $severity, $audience, $prev_ids, $resolved_tasks, $now ) {
            if ( $i['severity'] !== $severity || ( $i['audience'] ?? 'admin' ) !== $audience ) {
                return false;
            }

            // Resolved within last 24h → respect SAP user's resolution.
            if ( isset( $resolved_tasks[ $i['id'] ]['resolved_at'] ) ) {
                $resolved_ts = strtotime( $resolved_tasks[ $i['id'] ]['resolved_at'] );
                if ( ( $now - $resolved_ts ) < DAY_IN_SECONDS ) {
                    return false;
                }
            }

            return ! isset( $prev_ids[ $i['id'] ] );
        } ) );
    }

    // ── Public getters ───────────────────────────────────────────────────────

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

    // ── Quiet-window helpers ─────────────────────────────────────────────────

    /**
     * Record a natural gap resolution event (gap appeared, then resolved on its own).
     * Used to learn the site's recurring downtime pattern.
     */
    private static function record_gap_event( string $site_key, int $start_h, int $end_h ): void {
        $history = get_option( self::GAP_HISTORY_OPTION, [] );
        if ( ! isset( $history[ $site_key ] ) ) {
            $history[ $site_key ] = [];
        }
        array_unshift( $history[ $site_key ], [
            'start_h' => $start_h,
            'end_h'   => $end_h,
            'date'    => current_time( 'Y-m-d' ),
        ] );
        if ( count( $history[ $site_key ] ) > self::MAX_GAP_HISTORY ) {
            $history[ $site_key ] = array_slice( $history[ $site_key ], 0, self::MAX_GAP_HISTORY );
        }
        update_option( self::GAP_HISTORY_OPTION, $history, false );
    }

    /**
     * Detect recurring downtime pattern from gap history.
     *
     * Builds a 24-hour coverage map from all recorded gap events and finds the
     * longest contiguous block of hours that appears in >= 60% of events.
     *
     * @return array{from:int,to:int,confidence:float,events:int}|null
     */
    public static function detect_quiet_window( string $site_key ): ?array {
        $history = get_option( self::GAP_HISTORY_OPTION, [] );
        $events  = $history[ $site_key ] ?? [];
        $n       = count( $events );

        if ( $n < 3 ) {
            return null;
        }

        $counts = array_fill( 0, 24, 0 );
        foreach ( $events as $ev ) {
            $h = (int) $ev['start_h'];
            while ( true ) {
                $counts[ $h ]++;
                if ( $h === (int) $ev['end_h'] ) {
                    break;
                }
                $h = ( $h + 1 ) % 24;
            }
        }

        $threshold  = max( 1, (int) ceil( $n * 0.6 ) );
        $best_from  = -1;
        $best_len   = 0;
        $best_sum   = 0;

        for ( $start = 0; $start < 24; $start++ ) {
            if ( $counts[ $start ] < $threshold ) {
                continue;
            }
            $len = 0;
            $sum = 0;
            for ( $i = 0; $i < 24; $i++ ) {
                $h = ( $start + $i ) % 24;
                if ( $counts[ $h ] < $threshold ) {
                    break;
                }
                $len++;
                $sum += $counts[ $h ];
            }
            if ( $len > $best_len ) {
                $best_from = $start;
                $best_len  = $len;
                $best_sum  = $sum;
            }
        }

        if ( $best_from === -1 || $best_len < 2 ) {
            return null;
        }

        return [
            'from'       => $best_from,
            'to'         => ( $best_from + $best_len - 1 ) % 24,
            'confidence' => round( $best_sum / ( $best_len * $n ), 2 ),
            'events'     => $n,
        ];
    }

    /**
     * Check whether the current local time falls within the site's quiet window.
     * Manual config takes priority over the auto-learned pattern.
     * Auto-detection requires >= 5 events and >= 0.65 confidence.
     */
    private static function is_in_quiet_window( string $site_key, array $site ): bool {
        $current_h = (int) current_time( 'G' );
        $from      = $site['quiet_from'] ?? '';
        $to        = $site['quiet_to']   ?? '';

        if ( $from !== '' && $to !== '' ) {
            return self::hour_in_range( $current_h, (int) $from, (int) $to );
        }

        $auto = self::detect_quiet_window( $site_key );
        if ( $auto && $auto['confidence'] >= 0.65 && $auto['events'] >= 5 ) {
            return self::hour_in_range( $current_h, $auto['from'], $auto['to'] );
        }

        return false;
    }

    /**
     * Check whether hour $h (0–23) is inside the range [$from, $to] inclusive.
     * Handles midnight-crossing ranges (e.g., from=22, to=7).
     */
    private static function hour_in_range( int $h, int $from, int $to ): bool {
        if ( $from <= $to ) {
            return $h >= $from && $h <= $to;
        }
        return $h >= $from || $h <= $to;
    }
}
