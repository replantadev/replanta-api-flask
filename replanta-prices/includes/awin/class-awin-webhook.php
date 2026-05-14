<?php
/**
 * Awin Webhook Controller - REST API endpoint for Upmind webhooks.
 *
 * @package Replanta_Prices
 * @subpackage Awin
 */

defined( 'ABSPATH' ) || exit;

class Replanta_Awin_Webhook {

    /** @var string REST namespace */
    const REST_NAMESPACE = 'replanta/v1';

    /** @var string REST route */
    const REST_ROUTE = 'upmind-webhook';

    /**
     * Upmind webhook event types we care about (first payment only).
     */
    const EVENTS_CONVERSION = array(
        'invoice.payment.captured',
        'invoice.paid',
        'transaction.captured',
        'order.completed',
        'order.paid',
        'payment.captured',
        'subscription.created',  // First subscription payment
        // Upmind specific hook codes
        'invoice_payment_received_hook',
        'invoice_paid_hook',
        'order_paid_hook',
    );

    /**
     * Events to explicitly IGNORE (recurring payments, renewals).
     */
    const EVENTS_IGNORE = array(
        'subscription.renewed',
        'subscription.renewal',
        'invoice.renewal',
        'recurring.payment',
        'payment.recurring',
        'subscription.updated',
        'subscription.upgraded',
        'subscription.downgraded',
    );

    /**
     * Initialize hooks.
     */
    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    /**
     * Register REST API routes.
     */
    public static function register_routes() {
        register_rest_route( self::REST_NAMESPACE, '/' . self::REST_ROUTE, array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'handle_webhook' ),
            'permission_callback' => array( __CLASS__, 'validate_webhook' ),
        ) );

        // Debug/test endpoint (admin only)
        register_rest_route( self::REST_NAMESPACE, '/' . self::REST_ROUTE . '/test', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'handle_test_webhook' ),
            'permission_callback' => function() {
                return current_user_can( 'manage_options' );
            },
        ) );
    }

    /**
     * Get the full webhook URL.
     *
     * @return string
     */
    public static function get_webhook_url() {
        return rest_url( self::REST_NAMESPACE . '/' . self::REST_ROUTE );
    }

    /**
     * Validate incoming webhook request.
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public static function validate_webhook( $request ) {
        $settings = Replanta_Awin_Cookie::get_settings();
        $secret   = isset( $settings['webhook_secret'] ) ? $settings['webhook_secret'] : '';

        // If no secret configured, allow all (not recommended for production)
        if ( empty( $secret ) ) {
            return true;
        }

        // Check for signature header
        // Upmind uses X-Webhook-Signature or similar (adjust based on actual implementation)
        $signature = $request->get_header( 'X-Webhook-Signature' );
        
        // Alternative headers to check
        if ( empty( $signature ) ) {
            $signature = $request->get_header( 'X-Upmind-Signature' );
        }
        if ( empty( $signature ) ) {
            $signature = $request->get_header( 'Authorization' );
        }

        // Simple secret comparison (for basic auth)
        if ( ! empty( $signature ) ) {
            // Try bearer token format
            if ( strpos( $signature, 'Bearer ' ) === 0 ) {
                $token = substr( $signature, 7 );
                if ( hash_equals( $secret, $token ) ) {
                    return true;
                }
            }

            // Try HMAC validation
            $body      = $request->get_body();
            $expected  = hash_hmac( 'sha256', $body, $secret );
            if ( hash_equals( $expected, $signature ) ) {
                return true;
            }

            // Direct comparison
            if ( hash_equals( $secret, $signature ) ) {
                return true;
            }
        }

        // Check query param as fallback
        $query_secret = $request->get_param( 'secret' );
        if ( ! empty( $query_secret ) && hash_equals( $secret, $query_secret ) ) {
            return true;
        }

        // Log failed validation
        Replanta_Awin_Logger::log_event( Replanta_Awin_Logger::EVENT_WEBHOOK_ERROR, array(
            'status'  => Replanta_Awin_Logger::STATUS_ERROR,
            'payload' => array(
                'error'   => 'Invalid signature',
                'headers' => self::get_safe_headers( $request ),
            ),
        ) );

        return new WP_Error(
            'invalid_signature',
            __( 'Invalid webhook signature', 'replanta-prices' ),
            array( 'status' => 401 )
        );
    }

    /**
     * Handle incoming webhook.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function handle_webhook( $request ) {
        $body = $request->get_body();
        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            Replanta_Awin_Logger::log_event( Replanta_Awin_Logger::EVENT_WEBHOOK_ERROR, array(
                'status'  => Replanta_Awin_Logger::STATUS_ERROR,
                'payload' => array(
                    'error'    => 'Invalid JSON',
                    'raw_body' => substr( $body, 0, 500 ),
                ),
            ) );

            return new WP_REST_Response( array(
                'success' => false,
                'error'   => 'Invalid JSON payload',
            ), 400 );
        }

        // Extract event type
        $event_type = self::extract_event_type( $data );

        // Check if this is an event we should IGNORE (recurring payments)
        if ( in_array( $event_type, self::EVENTS_IGNORE, true ) || self::is_recurring_payment( $data ) ) {
            Replanta_Awin_Logger::log_event( 'webhook_ignored_recurring', array(
                'status'     => 'skipped',
                'event_type' => $event_type,
                'reason'     => 'recurring_payment',
                'payload'    => $data,
            ) );

            return new WP_REST_Response( array(
                'success'    => true,
                'event_type' => $event_type,
                'action'     => 'ignored_recurring',
                'message'    => 'Recurring payment - not reported to Awin',
            ), 200 );
        }

        // Log the webhook receipt
        $log_data = array(
            'status'  => Replanta_Awin_Logger::STATUS_SUCCESS,
            'payload' => $data,
        );

        // Try to extract conversion-relevant data
        $conversion_data = self::extract_conversion_data( $data, $event_type );
        if ( $conversion_data ) {
            $log_data = array_merge( $log_data, $conversion_data );
        }

        $log_id = Replanta_Awin_Logger::log_event( Replanta_Awin_Logger::EVENT_WEBHOOK_RECEIVED, $log_data );

        // Check if this is a conversion event
        if ( in_array( $event_type, self::EVENTS_CONVERSION, true ) ) {
            // Check if this customer already had a conversion reported (prevent duplicates)
            $customer_id = self::extract_customer_id( $data );
            if ( $customer_id && self::customer_already_converted( $customer_id ) ) {
                Replanta_Awin_Logger::log_event( 'conversion_duplicate_customer', array(
                    'status'      => 'skipped',
                    'customer_id' => $customer_id,
                    'reason'      => 'customer_already_converted',
                    'reference'   => isset( $conversion_data['reference'] ) ? $conversion_data['reference'] : null,
                ) );

                return new WP_REST_Response( array(
                    'success'    => true,
                    'event_id'   => $log_id,
                    'event_type' => $event_type,
                    'action'     => 'skipped_duplicate',
                    'message'    => 'Customer already had a conversion - not reported again',
                ), 200 );
            }

            $awc = isset( $conversion_data['awc'] ) ? $conversion_data['awc'] : '';
            
            // ULTRA CONSERVATIVE: Only attribute if AWC is trustworthy
            // This means: valid format AND we captured it ourselves (in our log)
            $is_attributable = ! empty( $awc ) && Replanta_Awin_Cookie::is_awc_trustworthy( $awc );

            if ( $is_attributable ) {
                // Try to send conversion immediately via S2S
                $s2s_data = array(
                    'awc'         => $awc,
                    'order_ref'   => isset( $conversion_data['reference'] ) ? $conversion_data['reference'] : null,
                    'amount'      => isset( $conversion_data['amount'] ) ? floatval( $conversion_data['amount'] ) : 0,
                    'currency'    => isset( $conversion_data['currency'] ) ? $conversion_data['currency'] : 'EUR',
                    'voucher'     => isset( $conversion_data['voucher'] ) ? $conversion_data['voucher'] : '',
                    'customer_id' => $customer_id, // For duplicate detection
                );

                $s2s_result = Replanta_Awin_S2S::send_conversion( $s2s_data );

                if ( ! $s2s_result['success'] ) {
                    // S2S failed - log as pending for cron retry
                    Replanta_Awin_Logger::log_event( Replanta_Awin_Logger::EVENT_CONVERSION_READY, array(
                        'status'    => Replanta_Awin_Logger::STATUS_PENDING,
                        'awc'       => $awc,
                        'reference' => isset( $conversion_data['reference'] ) ? $conversion_data['reference'] : null,
                        'amount'    => isset( $conversion_data['amount'] ) ? $conversion_data['amount'] : null,
                        'currency'  => isset( $conversion_data['currency'] ) ? $conversion_data['currency'] : null,
                        'payload'   => array(
                            'original_data' => $data,
                            's2s_error'     => $s2s_result['message'],
                            'customer_id'   => $customer_id,
                        ),
                        'webhook_event_id' => $log_id,
                    ) );
                }
                // If success, S2S::send_conversion already logged it as EVENT_S2S_SENT
            } else {
                // Log that we received conversion but cannot attribute
                Replanta_Awin_Logger::log_event( 'conversion_not_attributed', array(
                    'status'    => 'skipped',
                    'awc'       => $awc ?: '(none)',
                    'reason'    => empty( $awc ) ? 'no_awc_in_webhook' : 'awc_not_in_capture_log',
                    'reference' => isset( $conversion_data['reference'] ) ? $conversion_data['reference'] : null,
                    'amount'    => isset( $conversion_data['amount'] ) ? $conversion_data['amount'] : null,
                ) );
            }

            /**
             * Fire purchase hook for affiliate attribution.
             * This ensures replanta-affiliates can attribute by voucher
             * even when there is no AWC (organic affiliate purchase with coupon).
             */
            do_action( 'replanta_prices_purchase_received', array(
                'order_id' => isset( $conversion_data['reference'] ) ? $conversion_data['reference'] : '',
                'pid'      => '',
                'value'    => isset( $conversion_data['amount'] ) ? floatval( $conversion_data['amount'] ) : 0,
                'currency' => isset( $conversion_data['currency'] ) ? $conversion_data['currency'] : 'EUR',
                'voucher'  => isset( $conversion_data['voucher'] ) ? $conversion_data['voucher'] : '',
                'awc'      => $awc,
                'url'      => '',
            ) );
        }

        return new WP_REST_Response( array(
            'success'       => true,
            'event_id'      => $log_id,
            'event_type'    => $event_type,
            'attributable'  => ! empty( $conversion_data['awc'] ),
        ), 200 );
    }

    /**
     * Handle test webhook (admin only).
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function handle_test_webhook( $request ) {
        $body = $request->get_body();
        $data = json_decode( $body, true ) ?: array();

        // Create test data if empty
        if ( empty( $data ) ) {
            $data = array(
                'event'  => 'test.webhook',
                'data'   => array(
                    'test'      => true,
                    'timestamp' => current_time( 'c' ),
                ),
            );
        }

        $log_id = Replanta_Awin_Logger::log_event( Replanta_Awin_Logger::EVENT_WEBHOOK_RECEIVED, array(
            'status'  => Replanta_Awin_Logger::STATUS_SUCCESS,
            'payload' => $data,
            'is_test' => true,
        ) );

        return new WP_REST_Response( array(
            'success'  => true,
            'event_id' => $log_id,
            'message'  => 'Test webhook received and logged',
        ), 200 );
    }

    /**
     * Extract event type from webhook payload.
     * Adjust based on actual Upmind webhook format.
     *
     * @param array $data
     * @return string
     */
    private static function extract_event_type( $data ) {
        // Common patterns for event type in webhook payloads
        // hook_code is used by Upmind
        $possible_keys = array( 'hook_code', 'event', 'type', 'event_type', 'action', 'webhook_type' );

        foreach ( $possible_keys as $key ) {
            if ( isset( $data[ $key ] ) && is_string( $data[ $key ] ) ) {
                return sanitize_text_field( $data[ $key ] );
            }
        }

        // Nested structure
        if ( isset( $data['data']['type'] ) ) {
            return sanitize_text_field( $data['data']['type'] );
        }

        return 'unknown';
    }

    /**
     * Extract conversion-relevant data from webhook payload.
     * This needs to be adjusted based on actual Upmind webhook structure.
     *
     * @param array  $data
     * @param string $event_type
     * @return array|null
     */
    private static function extract_conversion_data( $data, $event_type ) {
        $result = array();

        // Try to find AWC in various locations
        $awc_locations = array(
            // Direct fields
            array( 'awc' ),
            array( 'awin_click_id' ),
            array( 'affiliate_tracking' ),
            // Nested in data
            array( 'data', 'awc' ),
            array( 'data', 'metadata', 'awc' ),
            array( 'data', 'custom_fields', 'awc' ),
            array( 'data', 'order', 'awc' ),
            array( 'data', 'order', 'metadata', 'awc' ),
            // Nested in invoice/transaction
            array( 'invoice', 'metadata', 'awc' ),
            array( 'transaction', 'metadata', 'awc' ),
            array( 'order', 'metadata', 'awc' ),
            // Query string that was passed
            array( 'data', 'order', 'source_params', 'awc' ),
            array( 'metadata', 'query_params', 'awc' ),
            // Upmind specific
            array( 'object', 'metadata', 'awc' ),
            array( 'object', 'invoice', 'meta', 'awc' ),
            array( 'object', 'invoice', 'object_meta', 'awc' ),
        );

        foreach ( $awc_locations as $path ) {
            $value = self::get_nested_value( $data, $path );
            if ( ! empty( $value ) ) {
                $result['awc'] = Replanta_Awin_Cookie::sanitize_awc( $value );
                break;
            }
        }

        // Extract email for AWC lookup fallback
        $email = null;
        $email_locations = array(
            array( 'object', 'client', 'email' ),
            array( 'object', 'invoice', 'client', 'email' ),
            array( 'object', 'invoice', 'current_data', 'content', 'client_email' ),
            array( 'data', 'client', 'email' ),
            array( 'data', 'customer', 'email' ),
            array( 'client', 'email' ),
            array( 'customer', 'email' ),
            array( 'email' ),
        );

        foreach ( $email_locations as $path ) {
            $value = self::get_nested_value( $data, $path );
            if ( ! empty( $value ) && is_email( $value ) ) {
                $email = sanitize_email( $value );
                $result['email'] = $email;
                break;
            }
        }

        // Extract IP for AWC lookup fallback
        $ip = null;
        $ip_locations = array(
            array( 'object', 'invoice', 'ip' ),
            array( 'object', 'ip' ),
            array( 'data', 'ip' ),
            array( 'ip' ),
            array( 'client', 'ip_address' ),
            array( 'object', 'client', 'ip_address' ),
        );

        foreach ( $ip_locations as $path ) {
            $value = self::get_nested_value( $data, $path );
            if ( ! empty( $value ) && filter_var( $value, FILTER_VALIDATE_IP ) ) {
                $ip = $value;
                $result['ip'] = $ip;
                break;
            }
        }

        // If no AWC found in payload, try lookup by IP first (more reliable)
        if ( empty( $result['awc'] ) && ! empty( $ip ) ) {
            $awc_from_ip = self::lookup_awc_by_ip( $ip );
            if ( $awc_from_ip ) {
                $result['awc'] = $awc_from_ip;
                $result['awc_source'] = 'ip_lookup';
            }
        }

        // If still no AWC, try lookup by email
        if ( empty( $result['awc'] ) && ! empty( $email ) ) {
            $awc_from_email = self::lookup_awc_by_email( $email );
            if ( $awc_from_email ) {
                $result['awc'] = $awc_from_email;
                $result['awc_source'] = 'email_lookup';
            }
        }

        // Extract reference/order ID
        $ref_locations = array(
            // Upmind specific - invoice number is the best reference
            array( 'object', 'invoice', 'number' ),
            array( 'object', 'invoice', 'proforma_number' ),
            array( 'object', 'id' ),
            // Generic
            array( 'id' ),
            array( 'order_id' ),
            array( 'invoice_id' ),
            array( 'transaction_id' ),
            array( 'reference' ),
            array( 'data', 'id' ),
            array( 'data', 'order_id' ),
            array( 'data', 'invoice', 'id' ),
            array( 'data', 'transaction', 'id' ),
            array( 'invoice', 'id' ),
            array( 'transaction', 'id' ),
        );

        foreach ( $ref_locations as $path ) {
            $value = self::get_nested_value( $data, $path );
            if ( ! empty( $value ) ) {
                $result['reference'] = sanitize_text_field( $value );
                break;
            }
        }

        // Extract amount
        $amount_locations = array(
            // Upmind specific
            array( 'object', 'amount_captured' ),
            array( 'object', 'amount' ),
            array( 'object', 'invoice', 'total_amount' ),
            array( 'object', 'invoice', 'paid_amount' ),
            // Generic
            array( 'amount' ),
            array( 'total' ),
            array( 'total_amount' ),
            array( 'data', 'amount' ),
            array( 'data', 'total' ),
            array( 'data', 'invoice', 'total' ),
            array( 'data', 'transaction', 'amount' ),
            array( 'invoice', 'total' ),
            array( 'transaction', 'amount' ),
        );

        foreach ( $amount_locations as $path ) {
            $value = self::get_nested_value( $data, $path );
            if ( is_numeric( $value ) ) {
                // Upmind might send amounts in cents
                $result['amount'] = floatval( $value );
                // If amount looks like cents (> 1000 for typical hosting prices), convert
                if ( $result['amount'] > 1000 ) {
                    $result['amount'] = $result['amount'] / 100;
                }
                break;
            }
        }

        // Extract currency
        $currency_locations = array(
            // Upmind specific - nested in currency object
            array( 'object', 'currency', 'code' ),
            array( 'object', 'invoice', 'currency', 'code' ),
            array( 'object', 'document_currency', 'code' ),
            // Direct fields
            array( 'currency' ),
            array( 'currency_code' ),
            // Nested in data
            array( 'data', 'currency' ),
            array( 'data', 'currency_code' ),
            // Invoice/order nested
            array( 'data', 'invoice', 'currency' ),
            array( 'data', 'invoice', 'currency_code' ),
            array( 'data', 'order', 'currency' ),
            array( 'data', 'order', 'currency_code' ),
            array( 'invoice', 'currency' ),
            array( 'invoice', 'currency_code' ),
            array( 'order', 'currency' ),
            array( 'order', 'currency_code' ),
            // Transaction nested
            array( 'transaction', 'currency' ),
            array( 'transaction', 'currency_code' ),
            array( 'data', 'transaction', 'currency' ),
            // Payment nested
            array( 'payment', 'currency' ),
            array( 'data', 'payment', 'currency' ),
        );

        foreach ( $currency_locations as $path ) {
            $value = self::get_nested_value( $data, $path );
            if ( ! empty( $value ) && is_string( $value ) && strlen( $value ) === 3 ) {
                $result['currency'] = strtoupper( sanitize_text_field( $value ) );
                break;
            }
        }

        return ! empty( $result ) ? $result : null;
    }

    /**
     * Get nested array value by path.
     *
     * @param array $data
     * @param array $path
     * @return mixed|null
     */
    private static function get_nested_value( $data, $path ) {
        $current = $data;

        foreach ( $path as $key ) {
            if ( ! is_array( $current ) || ! isset( $current[ $key ] ) ) {
                return null;
            }
            $current = $current[ $key ];
        }

        return $current;
    }

    /**
     * Lookup AWC by customer email from our capture logs.
     * 
     * This is a fallback when Upmind doesn't pass the AWC in the webhook.
     * We look for recent AWC captures that match the customer email.
     *
     * @param string $email Customer email.
     * @return string|null AWC if found, null otherwise.
     */
    private static function lookup_awc_by_email( $email ) {
        if ( empty( $email ) || ! is_email( $email ) ) {
            return null;
        }

        // Get the AWC lookup table (stored in options or custom table)
        $awc_map = get_option( 'replanta_awin_email_awc_map', array() );
        
        if ( isset( $awc_map[ strtolower( $email ) ] ) ) {
            $entry = $awc_map[ strtolower( $email ) ];
            
            // Check if AWC is not too old (30 days max)
            if ( isset( $entry['timestamp'] ) ) {
                $age_days = ( time() - $entry['timestamp'] ) / DAY_IN_SECONDS;
                if ( $age_days > 30 ) {
                    return null;
                }
            }
            
            // Verify AWC is trustworthy
            if ( isset( $entry['awc'] ) && Replanta_Awin_Cookie::is_awc_trustworthy( $entry['awc'] ) ) {
                return $entry['awc'];
            }
        }

        return null;
    }

    /**
     * Store AWC mapping for a customer email.
     * Called when we know a customer's email and their AWC.
     *
     * @param string $email Customer email.
     * @param string $awc AWC value.
     * @return bool Success.
     */
    public static function store_email_awc_mapping( $email, $awc ) {
        if ( empty( $email ) || ! is_email( $email ) || empty( $awc ) ) {
            return false;
        }

        $awc_map = get_option( 'replanta_awin_email_awc_map', array() );
        
        $awc_map[ strtolower( $email ) ] = array(
            'awc'       => Replanta_Awin_Cookie::sanitize_awc( $awc ),
            'timestamp' => time(),
        );
        
        // Limit map size to prevent bloat (keep last 1000 entries)
        if ( count( $awc_map ) > 1000 ) {
            // Sort by timestamp and keep newest 1000
            uasort( $awc_map, function( $a, $b ) {
                return ( $b['timestamp'] ?? 0 ) - ( $a['timestamp'] ?? 0 );
            } );
            $awc_map = array_slice( $awc_map, 0, 1000, true );
        }
        
        return update_option( 'replanta_awin_email_awc_map', $awc_map, false );
    }

    /**
     * Lookup AWC by customer IP from our capture logs.
     * 
     * This is the primary fallback when Upmind doesn't pass the AWC.
     * We look for recent AWC captures that match the customer IP.
     *
     * @param string $ip Customer IP address.
     * @return string|null AWC if found, null otherwise.
     */
    private static function lookup_awc_by_ip( $ip ) {
        if ( empty( $ip ) || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            return null;
        }

        $awc_map = get_option( 'replanta_awin_ip_awc_map', array() );
        
        if ( isset( $awc_map[ $ip ] ) ) {
            $entry = $awc_map[ $ip ];
            
            // Check if AWC is not too old (7 days max for IP - IPs change more frequently)
            if ( isset( $entry['timestamp'] ) ) {
                $age_days = ( time() - $entry['timestamp'] ) / DAY_IN_SECONDS;
                if ( $age_days > 7 ) {
                    return null;
                }
            }
            
            // Verify AWC is trustworthy
            if ( isset( $entry['awc'] ) && Replanta_Awin_Cookie::is_awc_trustworthy( $entry['awc'] ) ) {
                return $entry['awc'];
            }
        }

        return null;
    }

    /**
     * Store AWC mapping for a customer IP.
     * Called when we capture an AWC and know the visitor's IP.
     *
     * @param string $ip Customer IP address.
     * @param string $awc AWC value.
     * @return bool Success.
     */
    public static function store_ip_awc_mapping( $ip, $awc ) {
        if ( empty( $ip ) || ! filter_var( $ip, FILTER_VALIDATE_IP ) || empty( $awc ) ) {
            return false;
        }

        $awc_map = get_option( 'replanta_awin_ip_awc_map', array() );
        
        $awc_map[ $ip ] = array(
            'awc'       => Replanta_Awin_Cookie::sanitize_awc( $awc ),
            'timestamp' => time(),
        );
        
        // Limit map size to prevent bloat (keep last 5000 entries for IPs)
        if ( count( $awc_map ) > 5000 ) {
            // Sort by timestamp and keep newest 5000
            uasort( $awc_map, function( $a, $b ) {
                return ( $b['timestamp'] ?? 0 ) - ( $a['timestamp'] ?? 0 );
            } );
            $awc_map = array_slice( $awc_map, 0, 5000, true );
        }
        
        return update_option( 'replanta_awin_ip_awc_map', $awc_map, false );
    }

    /**
     * Get safe headers for logging (exclude sensitive data).
     *
     * @param WP_REST_Request $request
     * @return array
     */
    private static function get_safe_headers( $request ) {
        $headers = array();
        $safe_headers = array(
            'Content-Type',
            'User-Agent',
            'X-Webhook-Signature',
            'X-Upmind-Signature',
            'X-Request-Id',
        );

        foreach ( $safe_headers as $header ) {
            $value = $request->get_header( $header );
            if ( $value ) {
                $headers[ $header ] = $value;
            }
        }

        return $headers;
    }

    /**
     * Generate a random webhook secret.
     *
     * @return string
     */
    public static function generate_secret() {
        return wp_generate_password( 32, false, false );
    }

    /**
     * Check if webhook data indicates a recurring payment.
     *
     * @param array $data Webhook payload.
     * @return bool
     */
    private static function is_recurring_payment( $data ) {
        // Check explicit flags
        $recurring_flags = array(
            array( 'is_recurring' ),
            array( 'recurring' ),
            array( 'is_renewal' ),
            array( 'renewal' ),
            array( 'data', 'is_recurring' ),
            array( 'data', 'recurring' ),
            array( 'data', 'is_renewal' ),
            array( 'invoice', 'is_recurring' ),
            array( 'invoice', 'is_renewal' ),
            array( 'subscription', 'is_renewal' ),
            array( 'data', 'invoice', 'is_recurring' ),
            array( 'data', 'subscription', 'renewal_count' ),
        );

        foreach ( $recurring_flags as $path ) {
            $value = self::get_nested_value( $data, $path );
            if ( $value === true || $value === 1 || $value === '1' || $value === 'true' ) {
                return true;
            }
            // renewal_count > 0 means it's not the first payment
            if ( is_numeric( $value ) && (int) $value > 0 ) {
                return true;
            }
        }

        // Check invoice/payment number (number > 1 = renewal)
        $number_fields = array(
            array( 'invoice_number' ),
            array( 'payment_number' ),
            array( 'data', 'invoice_number' ),
            array( 'data', 'payment_number' ),
            array( 'invoice', 'number' ),
            array( 'subscription', 'payment_count' ),
        );

        foreach ( $number_fields as $path ) {
            $value = self::get_nested_value( $data, $path );
            if ( is_numeric( $value ) && (int) $value > 1 ) {
                return true;
            }
        }

        // Check type field for renewal indicators
        $type_fields = array(
            array( 'type' ),
            array( 'invoice_type' ),
            array( 'data', 'type' ),
            array( 'data', 'invoice_type' ),
        );

        foreach ( $type_fields as $path ) {
            $value = self::get_nested_value( $data, $path );
            if ( is_string( $value ) ) {
                $value_lower = strtolower( $value );
                if ( strpos( $value_lower, 'renewal' ) !== false ||
                     strpos( $value_lower, 'recurring' ) !== false ||
                     strpos( $value_lower, 'subscription_renewal' ) !== false ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Extract customer identifier from webhook data.
     *
     * @param array $data Webhook payload.
     * @return string|null Customer ID or email hash.
     */
    private static function extract_customer_id( $data ) {
        // Try various customer ID fields
        $id_fields = array(
            array( 'customer_id' ),
            array( 'client_id' ),
            array( 'user_id' ),
            array( 'data', 'customer_id' ),
            array( 'data', 'client_id' ),
            array( 'data', 'user_id' ),
            array( 'customer', 'id' ),
            array( 'client', 'id' ),
            array( 'data', 'customer', 'id' ),
            array( 'data', 'client', 'id' ),
        );

        foreach ( $id_fields as $path ) {
            $value = self::get_nested_value( $data, $path );
            if ( ! empty( $value ) ) {
                return 'id:' . sanitize_text_field( $value );
            }
        }

        // Fallback to email (hashed for privacy)
        $email_fields = array(
            array( 'email' ),
            array( 'customer_email' ),
            array( 'data', 'email' ),
            array( 'data', 'customer_email' ),
            array( 'customer', 'email' ),
            array( 'client', 'email' ),
            array( 'data', 'customer', 'email' ),
            array( 'data', 'client', 'email' ),
        );

        foreach ( $email_fields as $path ) {
            $value = self::get_nested_value( $data, $path );
            if ( ! empty( $value ) && is_email( $value ) ) {
                // Hash email for privacy
                return 'email:' . md5( strtolower( trim( $value ) ) );
            }
        }

        return null;
    }

    /**
     * Check if a customer already had a conversion reported.
     *
     * @param string $customer_id Customer identifier.
     * @return bool
     */
    private static function customer_already_converted( $customer_id ) {
        global $wpdb;

        $table = Replanta_Awin_Logger::get_table_name();

        // Check if we have a successful S2S sent for this customer
        // Look in metadata for customer_id
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} 
             WHERE event_type = 's2s_sent' 
             AND event_status = 'success'
             AND metadata LIKE %s",
            '%' . $wpdb->esc_like( $customer_id ) . '%'
        );

        return (int) $wpdb->get_var( $sql ) > 0;
    }
}
