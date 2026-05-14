<?php
/**
 * AWIN analytics collector for arrivals, checkout starts and purchases.
 *
 * Stores compact daily aggregates in wp_options and exposes a REST endpoint
 * for client-side tracking posts.
 *
 * @package Replanta_Prices
 */

defined( 'ABSPATH' ) || exit;

class Replanta_Prices_Awin_Analytics {

    const OPT_STATS = 'replanta_prices_awin_stats_v1';
    const OPT_S2S_QUEUE = 'replanta_prices_awin_s2s_queue_v1';
    const OPT_S2S_LOG = 'replanta_prices_awin_s2s_log_v1';
    const KEEP_DAYS = 120;
    const S2S_LOG_LIMIT = 80;
    const S2S_QUEUE_LIMIT = 500;
    const S2S_CRON_HOOK = 'replanta_prices_awin_s2s_cron';

    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
        add_action( self::S2S_CRON_HOOK, array( __CLASS__, 'process_s2s_queue_cron' ) );
    }

    public static function register_routes() {
        register_rest_route(
            'replanta-prices/v1',
            '/awin-event',
            array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'handle_event' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    public static function get_endpoint_url() {
        return rest_url( 'replanta-prices/v1/awin-event' );
    }

    public static function handle_event( WP_REST_Request $request ) {
        $data = $request->get_json_params();
        if ( ! is_array( $data ) || empty( $data ) ) {
            $data = $request->get_body_params();
        }
        // Fallback: parse raw body (handles text/plain from cross-origin sendBeacon).
        if ( ! is_array( $data ) || empty( $data ) ) {
            $body = $request->get_body();
            if ( ! empty( $body ) ) {
                $data = json_decode( $body, true );
            }
        }

        $event = isset( $data['event'] ) ? sanitize_key( $data['event'] ) : '';
        if ( ! in_array( $event, array( 'arrival', 'arrival_unresolved', 'begin_checkout', 'purchase' ), true ) ) {
            return new WP_REST_Response( array( 'ok' => false, 'reason' => 'invalid_event' ), 400 );
        }

        $awc      = isset( $data['awc'] ) ? sanitize_text_field( $data['awc'] ) : '';
        $pid      = isset( $data['pid'] ) ? sanitize_text_field( $data['pid'] ) : '';

        // Support legacy and GTM variants (orderRef/amount/coupon).
        $order_id = '';
        if ( isset( $data['order_id'] ) ) {
            $order_id = sanitize_text_field( $data['order_id'] );
        } elseif ( isset( $data['orderId'] ) ) {
            $order_id = sanitize_text_field( $data['orderId'] );
        } elseif ( isset( $data['orderRef'] ) ) {
            $order_id = sanitize_text_field( $data['orderRef'] );
        } elseif ( isset( $data['order_ref'] ) ) {
            $order_id = sanitize_text_field( $data['order_ref'] );
        }

        $currency = isset( $data['currency'] ) ? strtoupper( sanitize_key( $data['currency'] ) ) : '';

        $value = 0.0;
        if ( isset( $data['value'] ) ) {
            $value = (float) $data['value'];
        } elseif ( isset( $data['amount'] ) ) {
            $value = (float) $data['amount'];
        } elseif ( isset( $data['total'] ) ) {
            $value = (float) $data['total'];
        }

        $voucher = '';
        if ( isset( $data['voucher'] ) ) {
            $voucher = sanitize_text_field( $data['voucher'] );
        } elseif ( isset( $data['coupon'] ) ) {
            $voucher = sanitize_text_field( $data['coupon'] );
        } elseif ( isset( $data['vc'] ) ) {
            $voucher = sanitize_text_field( $data['vc'] );
        }

        $has_valid_awc = (bool) preg_match( '/^[a-zA-Z0-9_-]+$/', $awc );

        $stats = self::get_stats();
        $day   = wp_date( 'Y-m-d' );

        if ( empty( $stats['days'][ $day ] ) || ! is_array( $stats['days'][ $day ] ) ) {
            $stats['days'][ $day ] = self::new_day_row();
        }

        $row =& $stats['days'][ $day ];

        if ( 'arrival' === $event ) {
            $row['arrival_total']++;
            if ( $has_valid_awc ) {
                $awc_hash = sha1( strtolower( $awc ) );
                if ( empty( $row['awc_hashes'][ $awc_hash ] ) ) {
                    $row['awc_hashes'][ $awc_hash ] = 1;
                    $row['arrival_unique']++;
                }
            }
        }

        if ( 'begin_checkout' === $event ) {
            $row['begin_checkout_total']++;
            if ( $has_valid_awc ) {
                $row['begin_checkout_awin']++;
            }
        }

        if ( 'purchase' === $event ) {
            $row['purchase_total']++;

            $block_awin_by_coupon = self::is_internal_affiliate_coupon( $voucher );

            // Deduplicate purchase by order_id when available.
            if ( '' !== $order_id ) {
                $oid = strtolower( $order_id );
                if ( ! empty( $row['purchase_order_ids'][ $oid ] ) ) {
                    $stats['updated_at'] = time();
                    self::save_stats( $stats );
                    return new WP_REST_Response( array( 'ok' => true, 'deduped' => true ), 200 );
                }
                $row['purchase_order_ids'][ $oid ] = 1;
            }

            if ( $has_valid_awc && ! $block_awin_by_coupon ) {
                $row['purchase_awin']++;
                if ( $value > 0 ) {
                    $row['revenue_awin'] += $value;
                }
            }

            if ( '' !== $currency ) {
                $row['last_currency'] = $currency;
            }

            if ( '' !== $pid ) {
                $row['last_pid'] = $pid;
            }

            if ( ! $block_awin_by_coupon ) {
                self::queue_purchase_for_s2s( array(
                    'awc'      => $awc,
                    'pid'      => $pid,
                    'order_id' => $order_id,
                    'value'    => $value,
                    'currency' => $currency,
                    'voucher'  => $voucher,
                    'url'      => isset( $data['url'] ) ? esc_url_raw( $data['url'] ) : '',
                ) );
            } else {
                self::add_s2s_log( array(
                    'time'    => time(),
                    'status'  => 'blocked_internal_coupon',
                    'order'   => $order_id,
                    'voucher' => $voucher,
                    'reason'  => 'Internal affiliate coupon takes precedence over AWIN attribution.',
                ) );
            }

            /**
             * Fires after a purchase event is recorded.
             *
             * Used by replanta-affiliates to attribute sales.
             *
             * @param array $purchase_data {
             *     @type string $order_id  Order identifier.
             *     @type string $pid       Product PID.
             *     @type float  $value     Purchase amount.
             *     @type string $currency  Currency code.
             *     @type string $voucher   Coupon/voucher code.
             *     @type string $awc       Awin click reference.
             *     @type string $url       Source URL.
             * }
             */
            do_action( 'replanta_prices_purchase_received', array(
                'order_id' => $order_id,
                'pid'      => $pid,
                'value'    => $value,
                'currency' => $currency,
                'voucher'  => $voucher,
                'awc'      => $awc,
                'url'      => isset( $data['url'] ) ? esc_url_raw( $data['url'] ) : '',
            ) );
        }

        $stats['updated_at'] = time();
        self::cleanup_old_days( $stats );
        self::save_stats( $stats );

        return new WP_REST_Response( array( 'ok' => true ), 200 );
    }

    public static function get_report( $days = 30 ) {
        $days  = max( 1, min( 120, (int) $days ) );
        $stats = self::get_stats();

        $from_ts = strtotime( '-' . ( $days - 1 ) . ' days', current_time( 'timestamp' ) );

        $rows   = array();
        $totals = array(
            'arrival_total'        => 0,
            'arrival_unique'       => 0,
            'begin_checkout_total' => 0,
            'begin_checkout_awin'  => 0,
            'purchase_total'       => 0,
            'purchase_awin'        => 0,
            'revenue_awin'         => 0.0,
        );

        foreach ( $stats['days'] as $day => $row ) {
            $day_ts = strtotime( $day . ' 00:00:00' );
            if ( $day_ts < $from_ts ) {
                continue;
            }

            $clean_row = array(
                'day'                  => $day,
                'arrival_total'        => (int) $row['arrival_total'],
                'arrival_unique'       => (int) $row['arrival_unique'],
                'begin_checkout_total' => (int) $row['begin_checkout_total'],
                'begin_checkout_awin'  => (int) $row['begin_checkout_awin'],
                'purchase_total'       => (int) $row['purchase_total'],
                'purchase_awin'        => (int) $row['purchase_awin'],
                'revenue_awin'         => (float) $row['revenue_awin'],
            );

            $rows[] = $clean_row;

            $totals['arrival_total']        += $clean_row['arrival_total'];
            $totals['arrival_unique']       += $clean_row['arrival_unique'];
            $totals['begin_checkout_total'] += $clean_row['begin_checkout_total'];
            $totals['begin_checkout_awin']  += $clean_row['begin_checkout_awin'];
            $totals['purchase_total']       += $clean_row['purchase_total'];
            $totals['purchase_awin']        += $clean_row['purchase_awin'];
            $totals['revenue_awin']         += $clean_row['revenue_awin'];
        }

        usort(
            $rows,
            function( $a, $b ) {
                return strcmp( $b['day'], $a['day'] );
            }
        );

        return array(
            'days'       => $days,
            'totals'     => $totals,
            'rows'       => $rows,
            'endpoint'   => self::get_endpoint_url(),
            's2s'        => self::get_s2s_status(),
            'updated_at' => ! empty( $stats['updated_at'] ) ? (int) $stats['updated_at'] : 0,
        );
    }

    public static function process_s2s_queue_now( $limit = 20 ) {
        return self::process_s2s_queue( $limit );
    }

    public static function process_s2s_queue_cron() {
        self::process_s2s_queue( 20 );
    }

    private static function get_stats() {
        $stats = get_option( self::OPT_STATS, array() );
        if ( ! is_array( $stats ) ) {
            $stats = array();
        }
        if ( empty( $stats['days'] ) || ! is_array( $stats['days'] ) ) {
            $stats['days'] = array();
        }
        if ( empty( $stats['updated_at'] ) ) {
            $stats['updated_at'] = 0;
        }
        return $stats;
    }

    private static function save_stats( $stats ) {
        update_option( self::OPT_STATS, $stats, false );
    }

    private static function get_s2s_queue() {
        $queue = get_option( self::OPT_S2S_QUEUE, array() );
        if ( ! is_array( $queue ) ) {
            $queue = array();
        }
        return $queue;
    }

    private static function save_s2s_queue( $queue ) {
        update_option( self::OPT_S2S_QUEUE, $queue, false );
    }

    private static function get_s2s_log() {
        $log = get_option( self::OPT_S2S_LOG, array() );
        if ( ! is_array( $log ) ) {
            $log = array();
        }
        return $log;
    }

    private static function add_s2s_log( $entry ) {
        $log = self::get_s2s_log();
        array_unshift( $log, $entry );
        if ( count( $log ) > self::S2S_LOG_LIMIT ) {
            $log = array_slice( $log, 0, self::S2S_LOG_LIMIT );
        }
        update_option( self::OPT_S2S_LOG, $log, false );
    }

    private static function get_s2s_settings() {
        $settings = get_option( 'replanta_prices_settings', array() );
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }

        $settings['awin_s2s_enabled'] = ! empty( $settings['awin_s2s_enabled'] ) ? 1 : 0;
        $settings['awin_s2s_merchant_id'] = ! empty( $settings['awin_s2s_merchant_id'] ) ? (int) $settings['awin_s2s_merchant_id'] : 125596;
        $settings['awin_s2s_channel'] = ! empty( $settings['awin_s2s_channel'] ) ? sanitize_key( $settings['awin_s2s_channel'] ) : 'aw';
        $settings['awin_s2s_testmode'] = ! empty( $settings['awin_s2s_testmode'] ) ? 1 : 0;
        $settings['awin_s2s_voucher'] = isset( $settings['awin_s2s_voucher'] ) ? sanitize_text_field( $settings['awin_s2s_voucher'] ) : '';
        $settings['awin_s2s_endpoint'] = ! empty( $settings['awin_s2s_endpoint'] ) ? esc_url_raw( $settings['awin_s2s_endpoint'] ) : 'https://www.awin1.com/sread.php';
        $settings['awin_s2s_commission_map'] = isset( $settings['awin_s2s_commission_map'] ) ? (string) $settings['awin_s2s_commission_map'] : '';

        return $settings;
    }

    private static function queue_purchase_for_s2s( $payload ) {
        $settings = self::get_s2s_settings();
        if ( empty( $settings['awin_s2s_enabled'] ) ) {
            return;
        }

        $awc = isset( $payload['awc'] ) ? (string) $payload['awc'] : '';
        if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $awc ) ) {
            return;
        }

        $order_id = isset( $payload['order_id'] ) ? strtolower( trim( (string) $payload['order_id'] ) ) : '';
        $value    = isset( $payload['value'] ) ? (float) $payload['value'] : 0.0;
        $currency = isset( $payload['currency'] ) ? strtoupper( sanitize_key( (string) $payload['currency'] ) ) : '';

        if ( '' === $order_id || $value <= 0 || '' === $currency ) {
            return;
        }

        $commission_group = self::resolve_commission_group( isset( $payload['pid'] ) ? (string) $payload['pid'] : '', $settings );
        $queue = self::get_s2s_queue();

        if ( count( $queue ) >= self::S2S_QUEUE_LIMIT ) {
            self::add_s2s_log( array(
                'time'   => time(),
                'status' => 'dropped_queue_full',
                'order'  => $order_id,
                'error'  => 'Queue reached limit',
            ) );
            return;
        }

        if ( ! empty( $queue[ $order_id ] ) ) {
            return;
        }

        $amount = number_format( $value, 2, '.', '' );
        $queue[ $order_id ] = array(
            'order_id'         => $order_id,
            'awc'              => $awc,
            'pid'              => isset( $payload['pid'] ) ? (string) $payload['pid'] : '',
            'value'            => $value,
            'amount'           => $amount,
            'currency'         => $currency,
            'commission_group' => $commission_group,
            'parts'            => $commission_group . ':' . $amount,
            'voucher'          => isset( $payload['voucher'] ) ? sanitize_text_field( $payload['voucher'] ) : '',
            'url'              => isset( $payload['url'] ) ? esc_url_raw( $payload['url'] ) : '',
            'attempts'         => 0,
            'next_try_at'      => time(),
            'created_at'       => time(),
            'last_http_code'   => 0,
            'last_error'       => '',
        );

        self::save_s2s_queue( $queue );
        self::process_s2s_queue( 1 );
    }

    private static function process_s2s_queue( $limit = 20 ) {
        $settings = self::get_s2s_settings();
        if ( empty( $settings['awin_s2s_enabled'] ) ) {
            return array( 'processed' => 0, 'sent' => 0, 'failed' => 0 );
        }

        $queue = self::get_s2s_queue();
        if ( empty( $queue ) ) {
            return array( 'processed' => 0, 'sent' => 0, 'failed' => 0 );
        }

        $limit = max( 1, min( 100, (int) $limit ) );
        $now = time();
        $processed = 0;
        $sent = 0;
        $failed = 0;

        foreach ( $queue as $order_id => &$item ) {
            if ( $processed >= $limit ) {
                break;
            }

            if ( ! is_array( $item ) ) {
                unset( $queue[ $order_id ] );
                continue;
            }

            $next_try_at = isset( $item['next_try_at'] ) ? (int) $item['next_try_at'] : 0;
            if ( $next_try_at > $now ) {
                continue;
            }

            $processed++;
            $result = self::send_s2s_conversion( $item, $settings );

            if ( ! empty( $result['ok'] ) ) {
                $sent++;
                self::add_s2s_log( array(
                    'time'      => $now,
                    'status'    => 'sent',
                    'order'     => $item['order_id'],
                    'http_code' => isset( $result['http_code'] ) ? (int) $result['http_code'] : 0,
                    'parts'     => isset( $item['parts'] ) ? $item['parts'] : '',
                    'url'       => isset( $result['url'] ) ? $result['url'] : '',
                ) );
                unset( $queue[ $order_id ] );
                continue;
            }

            $failed++;
            $attempts = isset( $item['attempts'] ) ? (int) $item['attempts'] + 1 : 1;
            $item['attempts'] = $attempts;
            $item['last_http_code'] = isset( $result['http_code'] ) ? (int) $result['http_code'] : 0;
            $item['last_error'] = isset( $result['error'] ) ? (string) $result['error'] : 'Unknown error';
            $retry_in = min( HOUR_IN_SECONDS, (int) pow( 2, min( 8, $attempts ) ) * MINUTE_IN_SECONDS );
            $item['next_try_at'] = $now + max( MINUTE_IN_SECONDS, $retry_in );

            self::add_s2s_log( array(
                'time'      => $now,
                'status'    => 'retry',
                'order'     => $item['order_id'],
                'http_code' => $item['last_http_code'],
                'error'     => $item['last_error'],
                'attempts'  => $attempts,
                'next_try'  => $item['next_try_at'],
            ) );
        }
        unset( $item );

        self::save_s2s_queue( $queue );

        return array(
            'processed' => $processed,
            'sent'      => $sent,
            'failed'    => $failed,
        );
    }

    private static function send_s2s_conversion( $item, $settings ) {
        $endpoint = ! empty( $settings['awin_s2s_endpoint'] ) ? $settings['awin_s2s_endpoint'] : 'https://www.awin1.com/sread.php';
        $merchant = ! empty( $settings['awin_s2s_merchant_id'] ) ? (int) $settings['awin_s2s_merchant_id'] : 125596;
        $channel  = ! empty( $settings['awin_s2s_channel'] ) ? sanitize_key( $settings['awin_s2s_channel'] ) : 'aw';
        $global_voucher = isset( $settings['awin_s2s_voucher'] ) ? (string) $settings['awin_s2s_voucher'] : '';
        $txn_voucher    = isset( $item['voucher'] ) ? (string) $item['voucher'] : '';
        $voucher        = '' !== $txn_voucher ? $txn_voucher : $global_voucher;
        $testmode = ! empty( $settings['awin_s2s_testmode'] ) ? '1' : '0';

        $query = array(
            'tt'       => 'ss',
            'tv'       => '2',
            'merchant' => $merchant,
            'amount'   => isset( $item['amount'] ) ? (string) $item['amount'] : number_format( (float) $item['value'], 2, '.', '' ),
            'ch'       => $channel,
            'cr'       => isset( $item['currency'] ) ? strtoupper( (string) $item['currency'] ) : 'EUR',
            'ref'      => isset( $item['order_id'] ) ? (string) $item['order_id'] : '',
            'parts'    => isset( $item['parts'] ) ? (string) $item['parts'] : 'DEFAULT:0.00',
            'vc'       => $voucher,
            'testmode' => $testmode,
            'cks'      => isset( $item['awc'] ) ? (string) $item['awc'] : '',
        );

        $url = add_query_arg( $query, $endpoint );
        $response = wp_remote_get( $url, array(
            'timeout'    => 15,
            'redirection'=> 2,
            'user-agent' => 'ReplantaPrices/' . REPLANTA_PRICES_VERSION . '; ' . home_url(),
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'ok'    => false,
                'error' => $response->get_error_message(),
                'url'   => $url,
            );
        }

        $http_code = (int) wp_remote_retrieve_response_code( $response );
        $body = trim( (string) wp_remote_retrieve_body( $response ) );

        return array(
            'ok'        => ( $http_code >= 200 && $http_code < 300 ),
            'http_code' => $http_code,
            'body'      => substr( $body, 0, 500 ),
            'url'       => $url,
            'error'     => ( $http_code >= 200 && $http_code < 300 ) ? '' : 'HTTP ' . $http_code,
        );
    }

    /**
     * Returns true when the voucher belongs to the internal Replanta affiliates program.
     */
    private static function is_internal_affiliate_coupon( $voucher ) {
        $voucher = strtoupper( trim( (string) $voucher ) );
        if ( '' === $voucher ) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'raff_affiliates';

        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT 1
             FROM {$table}
             WHERE UPPER(coupon_code) = %s
               AND status IN ('approved','active')
             LIMIT 1",
            $voucher
        ) );

        return (bool) $exists;
    }

    private static function resolve_commission_group( $pid, $settings ) {
        $raw_map = isset( $settings['awin_s2s_commission_map'] ) ? (string) $settings['awin_s2s_commission_map'] : '';
        if ( '' === trim( $raw_map ) ) {
            return 'DEFAULT';
        }

        $pid = trim( (string) $pid );
        $lines = preg_split( '/\r\n|\r|\n/', $raw_map );
        if ( ! is_array( $lines ) ) {
            return 'DEFAULT';
        }

        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( '' === $line || 0 === strpos( $line, '#' ) ) {
                continue;
            }
            $parts = explode( '=', $line, 2 );
            if ( 2 !== count( $parts ) ) {
                continue;
            }
            $map_pid = trim( $parts[0] );
            $map_group = strtoupper( sanitize_key( trim( $parts[1] ) ) );
            if ( '' !== $map_pid && $map_pid === $pid && '' !== $map_group ) {
                return $map_group;
            }
        }

        return 'DEFAULT';
    }

    private static function get_s2s_status() {
        $settings = self::get_s2s_settings();
        $queue = self::get_s2s_queue();
        $log = self::get_s2s_log();

        $pending = 0;
        foreach ( $queue as $item ) {
            if ( is_array( $item ) ) {
                $pending++;
            }
        }

        return array(
            'enabled'          => ! empty( $settings['awin_s2s_enabled'] ),
            'merchant_id'      => (int) $settings['awin_s2s_merchant_id'],
            'channel'          => (string) $settings['awin_s2s_channel'],
            'testmode'         => ! empty( $settings['awin_s2s_testmode'] ),
            'endpoint'         => (string) $settings['awin_s2s_endpoint'],
            'queue_size'       => $pending,
            'queue_next_orders'=> array_slice( array_keys( $queue ), 0, 5 ),
            'last_log'         => ! empty( $log ) ? $log[0] : null,
            'log'              => array_slice( $log, 0, 20 ),
        );
    }

    private static function cleanup_old_days( &$stats ) {
        $cutoff = strtotime( '-' . self::KEEP_DAYS . ' days', current_time( 'timestamp' ) );

        foreach ( array_keys( $stats['days'] ) as $day ) {
            $day_ts = strtotime( $day . ' 00:00:00' );
            if ( $day_ts < $cutoff ) {
                unset( $stats['days'][ $day ] );
                continue;
            }

            // Keep internal maps bounded.
            if ( isset( $stats['days'][ $day ]['awc_hashes'] ) && is_array( $stats['days'][ $day ]['awc_hashes'] ) ) {
                if ( count( $stats['days'][ $day ]['awc_hashes'] ) > 5000 ) {
                    $stats['days'][ $day ]['awc_hashes'] = array_slice( $stats['days'][ $day ]['awc_hashes'], -5000, null, true );
                }
            }

            if ( isset( $stats['days'][ $day ]['purchase_order_ids'] ) && is_array( $stats['days'][ $day ]['purchase_order_ids'] ) ) {
                if ( count( $stats['days'][ $day ]['purchase_order_ids'] ) > 5000 ) {
                    $stats['days'][ $day ]['purchase_order_ids'] = array_slice( $stats['days'][ $day ]['purchase_order_ids'], -5000, null, true );
                }
            }
        }
    }

    private static function new_day_row() {
        return array(
            'arrival_total'        => 0,
            'arrival_unique'       => 0,
            'begin_checkout_total' => 0,
            'begin_checkout_awin'  => 0,
            'purchase_total'       => 0,
            'purchase_awin'        => 0,
            'revenue_awin'         => 0.0,
            'awc_hashes'           => array(),
            'purchase_order_ids'   => array(),
            'last_currency'        => '',
            'last_pid'             => '',
        );
    }
}
