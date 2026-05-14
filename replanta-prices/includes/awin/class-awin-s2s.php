<?php
/**
 * Awin Server-to-Server (S2S) API Integration.
 *
 * Reports conversions to Awin via their S2S tracking endpoint.
 * Used as alternative when pixel cannot be placed on checkout confirmation.
 *
 * @package Replanta_Prices
 * @subpackage Awin
 * @see https://wiki.awin.com/index.php/Advertiser_Tracking_Guide/Conversion_Pixel_Only_Implementation
 */

defined( 'ABSPATH' ) || exit;

class Replanta_Awin_S2S {

    /** @var string S2S tracking endpoint */
    const S2S_ENDPOINT = 'https://www.awin1.com/sread.php';

    /** @var string Default commission group */
    const DEFAULT_COMMISSION_GROUP = 'DEFAULT';

    /**
     * Send conversion to Awin via S2S.
     *
     * @param array $conversion_data {
     *     Conversion data.
     *     @type string $awc           AWC click reference (required).
     *     @type string $order_ref     Order reference/ID (required).
     *     @type float  $amount        Order subtotal (required).
     *     @type string $currency      Currency code, default EUR.
     *     @type string $voucher       Voucher/coupon code if used.
     *     @type string $channel       Channel, default 'aw'.
     *     @type string $commission_group Commission group code.
     *     @type bool   $is_new_customer Whether customer is new.
     * }
     * @return array {
     *     @type bool   $success  Whether the request succeeded.
     *     @type string $message  Response message.
     *     @type int    $code     HTTP response code.
     * }
     */
    public static function send_conversion( $conversion_data ) {
        $settings = Replanta_Awin_Cookie::get_settings();

        // Validate required settings
        if ( empty( $settings['advertiser_id'] ) ) {
            return array(
                'success' => false,
                'message' => 'Advertiser ID not configured',
                'code'    => 0,
            );
        }

        // Validate required data
        if ( empty( $conversion_data['awc'] ) ) {
            return array(
                'success' => false,
                'message' => 'AWC is required',
                'code'    => 0,
            );
        }

        if ( empty( $conversion_data['order_ref'] ) ) {
            return array(
                'success' => false,
                'message' => 'Order reference is required',
                'code'    => 0,
            );
        }

        if ( ! isset( $conversion_data['amount'] ) || $conversion_data['amount'] <= 0 ) {
            return array(
                'success' => false,
                'message' => 'Valid amount is required',
                'code'    => 0,
            );
        }

        // ULTRA CONSERVATIVE: Verify AWC is trustworthy before sending
        if ( ! Replanta_Awin_Cookie::is_awc_trustworthy( $conversion_data['awc'] ) ) {
            return array(
                'success' => false,
                'message' => 'AWC not verified in capture log - conversion not sent',
                'code'    => 0,
            );
        }

        // Build S2S request parameters
        $params = self::build_params( $conversion_data, $settings );

        // Make the request
        $url = self::S2S_ENDPOINT . '?' . http_build_query( $params );

        $response = wp_remote_get( $url, array(
            'timeout'    => 15,
            'user-agent' => 'Replanta-Awin/' . REPLANTA_PRICES_VERSION,
        ) );

        if ( is_wp_error( $response ) ) {
            $result = array(
                'success' => false,
                'message' => $response->get_error_message(),
                'code'    => 0,
            );

            // Log error
            Replanta_Awin_Logger::log_event( Replanta_Awin_Logger::EVENT_S2S_ERROR, array(
                'status'    => Replanta_Awin_Logger::STATUS_ERROR,
                'awc'       => $conversion_data['awc'],
                'reference' => $conversion_data['order_ref'],
                'amount'    => $conversion_data['amount'],
                'currency'  => $conversion_data['currency'] ?? 'EUR',
                'payload'   => array(
                    'url'   => $url,
                    'error' => $result['message'],
                ),
            ) );

            return $result;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        // Awin returns 200 for success
        $success = $code === 200;

        $result = array(
            'success' => $success,
            'message' => $success ? 'Conversion sent successfully' : "HTTP $code: $body",
            'code'    => $code,
        );

        // Log the result
        $log_type = $success ? Replanta_Awin_Logger::EVENT_S2S_SENT : Replanta_Awin_Logger::EVENT_S2S_ERROR;
        $log_status = $success ? Replanta_Awin_Logger::STATUS_SUCCESS : Replanta_Awin_Logger::STATUS_ERROR;

        Replanta_Awin_Logger::log_event( $log_type, array(
            'status'    => $log_status,
            'awc'       => $conversion_data['awc'],
            'reference' => $conversion_data['order_ref'],
            'amount'    => $conversion_data['amount'],
            'currency'  => $conversion_data['currency'] ?? 'EUR',
            'payload'   => array(
                'params'        => $params,
                'response_code' => $code,
                'response_body' => substr( $body, 0, 500 ),
            ),
            // Customer ID for duplicate detection
            'customer_id' => $conversion_data['customer_id'] ?? null,
        ) );

        return $result;
    }

    /**
     * Build S2S request parameters.
     *
     * @param array $conversion_data Conversion data.
     * @param array $settings        Plugin settings.
     * @return array Query parameters.
     */
    private static function build_params( $conversion_data, $settings ) {
        $amount   = number_format( floatval( $conversion_data['amount'] ), 2, '.', '' );
        $currency = strtoupper( $conversion_data['currency'] ?? 'EUR' );
        $group    = $conversion_data['commission_group'] ?? self::DEFAULT_COMMISSION_GROUP;

        $params = array(
            'tt'       => 'ss',  // Server-to-server tracking type
            'tv'       => '2',   // Tracking version
            'merchant' => $settings['advertiser_id'],
            'amount'   => $amount,
            'cr'       => $currency,
            'ref'      => sanitize_text_field( $conversion_data['order_ref'] ),
            'parts'    => $group . ':' . $amount,
            'ch'       => $conversion_data['channel'] ?? 'aw',
            'testmode' => '0',   // Production mode
            'cks'      => $conversion_data['awc'],  // The AWC/click checksum
        );

        // Optional: Voucher code
        if ( ! empty( $conversion_data['voucher'] ) ) {
            $params['vc'] = sanitize_text_field( $conversion_data['voucher'] );
        }

        // Optional: Customer acquisition (1 = new, 0 = existing)
        if ( isset( $conversion_data['is_new_customer'] ) ) {
            $params['customeracquisition'] = $conversion_data['is_new_customer'] ? '1' : '0';
        }

        return $params;
    }

    /**
     * Process pending conversions from the log.
     * Called by cron or manually to retry failed/pending S2S calls.
     *
     * @param int $limit Max conversions to process.
     * @return array Results summary.
     */
    public static function process_pending_conversions( $limit = 10 ) {
        $pending = Replanta_Awin_Logger::get_events( array(
            'event_type' => Replanta_Awin_Logger::EVENT_CONVERSION_READY,
            'status'     => Replanta_Awin_Logger::STATUS_PENDING,
            'limit'      => $limit,
            'orderby'    => 'created_at',
            'order'      => 'ASC',
        ) );

        $results = array(
            'processed' => 0,
            'success'   => 0,
            'failed'    => 0,
            'skipped'   => 0,
        );

        foreach ( $pending as $event ) {
            $results['processed']++;

            // Extract conversion data from event
            $conversion_data = array(
                'awc'       => $event['awc'],
                'order_ref' => $event['reference'],
                'amount'    => $event['amount'],
                'currency'  => $event['currency'] ?: 'EUR',
            );

            // Try to get additional data from payload
            if ( ! empty( $event['payload'] ) ) {
                $payload = json_decode( $event['payload'], true );
                if ( is_array( $payload ) ) {
                    if ( ! empty( $payload['voucher'] ) ) {
                        $conversion_data['voucher'] = $payload['voucher'];
                    }
                    if ( isset( $payload['is_new_customer'] ) ) {
                        $conversion_data['is_new_customer'] = $payload['is_new_customer'];
                    }
                }
            }

            // Skip if missing required data
            if ( empty( $conversion_data['awc'] ) || empty( $conversion_data['order_ref'] ) || empty( $conversion_data['amount'] ) ) {
                Replanta_Awin_Logger::update_status( $event['id'], 'skipped' );
                $results['skipped']++;
                continue;
            }

            // Send to Awin
            $result = self::send_conversion( $conversion_data );

            if ( $result['success'] ) {
                Replanta_Awin_Logger::update_status( $event['id'], Replanta_Awin_Logger::STATUS_SUCCESS );
                $results['success']++;
            } else {
                // Keep as pending for retry, or mark as error after multiple attempts
                $metadata = json_decode( $event['metadata'] ?? '{}', true );
                $attempts = ( $metadata['s2s_attempts'] ?? 0 ) + 1;

                if ( $attempts >= 3 ) {
                    Replanta_Awin_Logger::update_status( $event['id'], Replanta_Awin_Logger::STATUS_ERROR );
                }

                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Test connection to Awin S2S endpoint.
     *
     * @return array Test result.
     */
    public static function test_connection() {
        $settings = Replanta_Awin_Cookie::get_settings();

        if ( empty( $settings['advertiser_id'] ) ) {
            return array(
                'success' => false,
                'message' => 'Advertiser ID not configured',
            );
        }

        // Make a test request with testmode=1
        $params = array(
            'tt'       => 'ss',
            'tv'       => '2',
            'merchant' => $settings['advertiser_id'],
            'amount'   => '0.00',
            'cr'       => 'EUR',
            'ref'      => 'TEST_' . time(),
            'parts'    => 'DEFAULT:0.00',
            'ch'       => 'aw',
            'testmode' => '1',  // Test mode - won't record actual conversion
            'cks'      => 'test_connection_check',
        );

        $url = self::S2S_ENDPOINT . '?' . http_build_query( $params );

        $response = wp_remote_get( $url, array(
            'timeout'    => 10,
            'user-agent' => 'Replanta-Awin/' . REPLANTA_PRICES_VERSION,
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => 'Connection failed: ' . $response->get_error_message(),
            );
        }

        $code = wp_remote_retrieve_response_code( $response );

        return array(
            'success' => $code === 200,
            'message' => $code === 200 
                ? 'Connection OK - Advertiser ID ' . $settings['advertiser_id'] . ' accepted'
                : 'HTTP ' . $code . ' - Check Advertiser ID',
            'code'    => $code,
        );
    }
}
