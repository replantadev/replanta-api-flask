<?php
/**
 * AWIN Analytics Dashboard Data
 *
 * Provides aggregated metrics for the inline dashboard shown in the
 * AWIN Analytics admin tab: KPI cards with deltas, time-series data
 * for the SVG sparkline chart, and a top-days-by-revenue list.
 *
 * @package Replanta_Prices
 */

defined( 'ABSPATH' ) || exit;

class Replanta_Prices_Awin_Dashboard {

    /**
     * Returns all dashboard data for a given period in days.
     *
     * @param int $period_days  7 | 30 | 90
     * @return array{
     *   period_days: int,
     *   kpis: array,
     *   chart: array,
     *   top_days: array,
     *   prev_kpis: array,
     * }
     */
    public static function get_dashboard_data( $period_days = 30 ) {
        $period_days = self::validated_period( $period_days );
        $stats       = self::load_stats();
        $today_ts    = current_time( 'timestamp' );

        // Date window for current period.
        $from_ts = strtotime( '-' . ( $period_days - 1 ) . ' days', $today_ts );
        // Date window for previous period (same length, immediately before).
        $prev_to_ts   = $from_ts - 1;
        $prev_from_ts = strtotime( '-' . ( $period_days - 1 ) . ' days', $prev_to_ts );

        $current  = self::aggregate_window( $stats, $from_ts, $today_ts );
        $previous = self::aggregate_window( $stats, $prev_from_ts, $prev_to_ts );

        $chart    = self::build_chart_series( $stats, $from_ts, $today_ts, $period_days );
        $top_days = self::build_top_days( $stats, $from_ts, $today_ts, 5 );

        return array(
            'period_days' => $period_days,
            'kpis'        => $current,
            'prev_kpis'   => $previous,
            'chart'       => $chart,
            'top_days'    => $top_days,
        );
    }

    /* ─── Helpers ───────────────────────────────────────────────────── */

    private static function validated_period( $days ) {
        $days = (int) $days;
        return in_array( $days, array( 7, 30, 90 ), true ) ? $days : 30;
    }

    private static function load_stats() {
        $stats = get_option( 'replanta_prices_awin_stats_v1', array() );
        if ( ! is_array( $stats ) || empty( $stats['days'] ) ) {
            return array( 'days' => array() );
        }
        return $stats;
    }

    /**
     * Sum all metric columns for days within [from_ts, to_ts].
     *
     * @return array{ arrivals: int, arrivals_unique: int, checkouts: int, checkouts_awin: int, purchases: int, purchases_awin: int, revenue_awin: float }
     */
    private static function aggregate_window( array $stats, $from_ts, $to_ts ) {
        $out = array(
            'arrivals'       => 0,
            'arrivals_unique'=> 0,
            'checkouts'      => 0,
            'checkouts_awin' => 0,
            'purchases'      => 0,
            'purchases_awin' => 0,
            'revenue_awin'   => 0.0,
        );

        foreach ( $stats['days'] as $day => $row ) {
            $ts = strtotime( $day . ' 00:00:00' );
            if ( $ts < $from_ts || $ts > $to_ts ) {
                continue;
            }
            $out['arrivals']        += (int) ( $row['arrival_total']        ?? 0 );
            $out['arrivals_unique'] += (int) ( $row['arrival_unique']       ?? 0 );
            $out['checkouts']       += (int) ( $row['begin_checkout_total'] ?? 0 );
            $out['checkouts_awin']  += (int) ( $row['begin_checkout_awin']  ?? 0 );
            $out['purchases']       += (int) ( $row['purchase_total']       ?? 0 );
            $out['purchases_awin']  += (int) ( $row['purchase_awin']        ?? 0 );
            $out['revenue_awin']    += (float) ( $row['revenue_awin']       ?? 0 );
        }

        return $out;
    }

    /**
     * Build a daily time series for the chart (full period, including zero days).
     *
     * @return array{ labels: string[], arrivals: int[], purchases_awin: int[], revenue_awin: float[] }
     */
    private static function build_chart_series( array $stats, $from_ts, $to_ts, $period_days ) {
        $labels         = array();
        $arrivals       = array();
        $purchases_awin = array();
        $revenue_awin   = array();

        // Iterate day by day (oldest → newest).
        for ( $offset = $period_days - 1; $offset >= 0; $offset-- ) {
            $day_ts  = strtotime( '-' . $offset . ' days', $to_ts );
            $day_key = gmdate( 'Y-m-d', $day_ts );
            $row     = isset( $stats['days'][ $day_key ] ) ? $stats['days'][ $day_key ] : array();

            $labels[]         = gmdate( 'd/m', $day_ts );
            $arrivals[]       = (int) ( $row['arrival_total']  ?? 0 );
            $purchases_awin[] = (int) ( $row['purchase_awin']  ?? 0 );
            $revenue_awin[]   = round( (float) ( $row['revenue_awin'] ?? 0 ), 2 );
        }

        return compact( 'labels', 'arrivals', 'purchases_awin', 'revenue_awin' );
    }

    /**
     * Returns the top N days sorted by AWIN revenue.
     *
     * @return array[]
     */
    private static function build_top_days( array $stats, $from_ts, $to_ts, $limit = 5 ) {
        $rows = array();

        foreach ( $stats['days'] as $day => $row ) {
            $ts = strtotime( $day . ' 00:00:00' );
            if ( $ts < $from_ts || $ts > $to_ts ) {
                continue;
            }
            $revenue = (float) ( $row['revenue_awin'] ?? 0 );
            if ( $revenue > 0 ) {
                $rows[] = array(
                    'day'            => $day,
                    'revenue_awin'   => $revenue,
                    'purchases_awin' => (int) ( $row['purchase_awin'] ?? 0 ),
                );
            }
        }

        usort( $rows, static function( $a, $b ) {
            return $b['revenue_awin'] <=> $a['revenue_awin'];
        } );

        return array_slice( $rows, 0, $limit );
    }

    /**
     * Compute a percentage delta between current and previous values.
     * Returns null when previous is 0 (no comparison possible).
     *
     * @return float|null   Signed percent, e.g. 25.0 means +25 %.
     */
    public static function delta( $current, $previous ) {
        if ( 0 === (int) $previous && 0.0 === (float) $previous ) {
            return null;
        }
        return round( ( ( $current - $previous ) / $previous ) * 100, 1 );
    }
}
