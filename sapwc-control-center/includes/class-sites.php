<?php
/**
 * SAPWCC_Sites — Gestión de sitios remotos y health polling.
 *
 * @package SAPWCC
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SAPWCC_Sites {

    const OPTION_KEY    = 'sapwcc_sites';
    const HEALTH_PREFIX = 'sapwcc_health_';
    const HEALTH_TTL    = 300; // 5 min cache

    // ─── CRUD ────────────────────────────────────────────────────────────────

    /**
     * Devuelve todos los sitios registrados.
     */
    public static function get_all(): array {
        return get_option( self::OPTION_KEY, [] );
    }

    /**
     * Añade un nuevo sitio.
     */
    public static function add( string $label, string $url, string $secret ): bool {
        $sites = self::get_all();
        $key   = sanitize_title( $label );

        if ( isset( $sites[ $key ] ) ) {
            $key .= '-' . wp_rand( 100, 999 );
        }

        $sites[ $key ] = [
            'label'         => $label,
            'url'           => untrailingslashit( $url ),
            'secret'        => self::encrypt( $secret ),
            'site_id'       => '',
            'added'         => current_time( 'Y-m-d' ),
            'client_name'   => '',
            'client_email'  => '',
            'contract_date' => '',
            'monthly_fee'   => 0,
        ];

        return update_option( self::OPTION_KEY, $sites, false );
    }

    /**
     * Update metadata fields for an existing site.
     *
     * @param string $key  Site key.
     * @param array  $meta Associative array of fields to update.
     * @return bool
     */
    public static function update_meta( string $key, array $meta ): bool {
        $sites = self::get_all();
        if ( ! isset( $sites[ $key ] ) ) {
            return false;
        }

        $allowed = [ 'client_name', 'client_email', 'contract_date', 'monthly_fee', 'quiet_from', 'quiet_to' ];

        foreach ( $meta as $field => $value ) {
            if ( in_array( $field, $allowed, true ) ) {
                $sites[ $key ][ $field ] = $value;
            }
        }

        return update_option( self::OPTION_KEY, $sites, false );
    }

    /**
     * Get the decrypted secret for a site (for proxying remote requests).
     */
    public static function get_decrypted_secret( string $key ): string {
        $sites = self::get_all();
        if ( ! isset( $sites[ $key ] ) ) {
            return '';
        }
        return self::decrypt( $sites[ $key ]['secret'] ?? '' );
    }

    /**
     * Update (replace) the secret for a site after a remote rotation.
     * The new secret is encrypted before being stored.
     *
     * @param string $key    Site key.
     * @param string $secret New plain-text secret returned by rotate-secret endpoint.
     * @return bool
     */
    public static function update_secret( string $key, string $secret ): bool {
        $sites = self::get_all();
        if ( ! isset( $sites[ $key ] ) ) {
            return false;
        }
        $sites[ $key ]['secret'] = self::encrypt( $secret );
        return update_option( self::OPTION_KEY, $sites, false );
    }

    /**
     * Calculate total MRR (Monthly Recurring Revenue) across all sites.
     *
     * @return array [ 'total' => float, 'count' => int, 'by_plan' => [...] ]
     */
    public static function get_mrr_summary(): array {
        $sites   = self::get_all();
        $flags   = class_exists( 'SAPWCC_Flags' ) ? SAPWCC_Flags::read() : [];
        $total   = 0.0;
        $count   = 0;
        $by_plan = [ 'starter' => 0.0, 'business' => 0.0, 'enterprise' => 0.0 ];

        foreach ( $sites as $key => $site ) {
            $fee = floatval( $site['monthly_fee'] ?? 0 );
            if ( $fee > 0 ) {
                $total += $fee;
                $count++;

                // Determine plan from flags.json.
                $sid  = $site['site_id'] ?? '';
                $plan = $flags['sites'][ $sid ]['plan'] ?? 'starter';
                if ( isset( $by_plan[ $plan ] ) ) {
                    $by_plan[ $plan ] += $fee;
                }
            }
        }

        return [
            'total'   => $total,
            'count'   => $count,
            'by_plan' => $by_plan,
        ];
    }

    /**
     * Get transient warnings — warnings that occurred but were later auto-resolved.
     *
     * Detects sync failures (timeouts, errors) that were later fixed by automatic
     * retry mechanisms. Useful for proactive monitoring even when issues self-recover.
     *
     * @param string $key   Site key.
     * @param int    $hours Lookback period in hours (default 24).
     * @return array [ 'count' => int, 'warnings' => array, 'orders_affected' => array ]
     */
    public static function get_transient_warnings( string $key, int $hours = 24 ): array {
        $sites = self::get_all();
        if ( ! isset( $sites[ $key ] ) ) {
            return [ 'count' => 0, 'warnings' => [], 'orders_affected' => [] ];
        }

        $site   = $sites[ $key ];
        $url    = untrailingslashit( $site['url'] );
        $secret = self::decrypt( $site['secret'] ?? '' );

        if ( empty( $url ) || empty( $secret ) ) {
            return [ 'count' => 0, 'warnings' => [], 'orders_affected' => [] ];
        }

        // Check transient cache (TTL 10 min) — avoids N HTTP requests per page load.
        $tw_cache_key = self::HEALTH_PREFIX . 'tw_' . $key . '_' . $hours;
        $cached_tw    = get_transient( $tw_cache_key );
        if ( is_array( $cached_tw ) ) {
            return $cached_tw;
        }

        // Fetch recent logs (warnings + errors + info) from remote site.
        $logs_url = add_query_arg(
            [ 'level' => 'all', 'limit' => 200 ],
            $url . '/wp-json/sapwc/v1/control/logs'
        );
        $response = wp_remote_get(
            $logs_url,
            [
                'headers'   => [
                    'X-SAPWC-Secret' => $secret,
                ],
                'sslverify' => ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ),
                'timeout'   => 15,
            ]
        );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return [ 'count' => 0, 'warnings' => [], 'orders_affected' => [] ];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $logs = $body['logs'] ?? [];

        if ( empty( $logs ) ) {
            return [ 'count' => 0, 'warnings' => [], 'orders_affected' => [] ];
        }

        // Filter to lookback window.
        $cutoff = current_time( 'timestamp' ) - ( $hours * HOUR_IN_SECONDS );
        $logs   = array_filter( $logs, function ( $log ) use ( $cutoff ) {
            $ts = strtotime( $log['timestamp'] ?? '' );
            return $ts && $ts >= $cutoff;
        } );

        // Identify warnings (timeouts, login failures, etc.).
        $warnings        = [];
        $successes       = [];
        $orders_affected = [];

        foreach ( $logs as $log ) {
            $lvl = strtolower( $log['level'] ?? '' );
            $msg = $log['message'] ?? '';
            $op  = $log['operation'] ?? '';

            // Extract order ID if present.
            $order_id = null;
            if ( preg_match( '/pedido[^\d]*(\d+)/i', $msg . ' ' . $op, $m ) ) {
                $order_id = intval( $m[1] );
            }

            // Classify as warning or success.
            if ( 'warning' === $lvl || 'error' === $lvl ) {
                $warnings[] = [
                    'timestamp' => $log['timestamp'],
                    'level'     => $lvl,
                    'operation' => $op,
                    'message'   => $msg,
                    'order_id'  => $order_id,
                ];
            } elseif ( 'info' === $lvl && $order_id ) {
                // Likely a success if "sincronizado" or "creado" in message.
                if ( stripos( $msg, 'sincronizado' ) !== false || stripos( $msg, 'creado' ) !== false ) {
                    $successes[ $order_id ] = $log['timestamp'];
                }
            }
        }

        // Filter: keep only warnings that have a corresponding later success.
        $transient_warnings = [];
        foreach ( $warnings as $w ) {
            if ( $w['order_id'] && isset( $successes[ $w['order_id'] ] ) ) {
                // Check if success came AFTER warning.
                $w_ts = strtotime( $w['timestamp'] );
                $s_ts = strtotime( $successes[ $w['order_id'] ] );
                if ( $s_ts > $w_ts ) {
                    $transient_warnings[] = $w;
                    $orders_affected[]    = $w['order_id'];
                }
            }
        }

        $result = [
            'count'           => count( $transient_warnings ),
            'warnings'        => $transient_warnings,
            'orders_affected' => array_unique( $orders_affected ),
        ];

        set_transient( $tw_cache_key, $result, 10 * MINUTE_IN_SECONDS );
        return $result;
    }

    /**
     * Elimina un sitio y su cache de health.
     */
    public static function remove( string $key ): void {
        $sites = self::get_all();
        unset( $sites[ $key ] );
        update_option( self::OPTION_KEY, $sites, false );
        delete_transient( self::HEALTH_PREFIX . $key );
        delete_transient( self::HEALTH_PREFIX . 'tw_' . $key . '_24' );
        delete_transient( self::HEALTH_PREFIX . 'tw_' . $key . '_48' );
    }

    // ─── Health Polling ──────────────────────────────────────────────────────

    /**
     * Llama al endpoint /wp-json/sapwc/v1/health de un sitio remoto.
     *
     * @return array|WP_Error
     */
    public static function fetch_health( string $key ) {
        $sites = self::get_all();
        if ( ! isset( $sites[ $key ] ) ) {
            return new WP_Error( 'not_found', 'Sitio no encontrado.' );
        }

        $site   = $sites[ $key ];
        $url    = $site['url'] . '/wp-json/sapwc/v1/health';
        $secret = self::decrypt( $site['secret'] );

        $response = wp_remote_get( $url, [
            'timeout'   => 25,
            'sslverify' => ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ),
            'headers'   => [
                'X-SAPWC-Secret' => $secret,
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            $health = [
                'status'     => 'unreachable',
                'error'      => $response->get_error_message(),
                'checked_at' => current_time( 'mysql' ),
            ];
            set_transient( self::HEALTH_PREFIX . $key, $health, self::HEALTH_TTL );
            return $health;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ( $code === 200 || $code === 503 ) && is_array( $body ) ) {
            $health               = $body;
            $health['checked_at'] = current_time( 'mysql' );
            $health['http_code']  = $code;

            // Auto-populate site_id
            $remote_site_id = $body['site']['site_id'] ?? '';
            if ( ! empty( $remote_site_id ) && empty( $site['site_id'] ) ) {
                $sites[ $key ]['site_id'] = sanitize_text_field( $remote_site_id );
                update_option( self::OPTION_KEY, $sites, false );
            }
        } else {
            $health = [
                'status'     => 'error',
                'http_code'  => $code,
                'error'      => 'Respuesta inesperada (HTTP ' . $code . ').',
                'checked_at' => current_time( 'mysql' ),
            ];
        }

        set_transient( self::HEALTH_PREFIX . $key, $health, self::HEALTH_TTL );
        return $health;
    }

    /**
     * Devuelve health cacheado o null si no hay cache.
     */
    public static function get_cached_health( string $key ): ?array {
        $cached = get_transient( self::HEALTH_PREFIX . $key );
        return is_array( $cached ) ? $cached : null;
    }

    /**
     * Health check de todos los sitios registrados.
     */
    public static function check_all_health(): array {
        $results = [];
        foreach ( array_keys( self::get_all() ) as $key ) {
            $result = self::fetch_health( $key );
            $results[ $key ] = is_wp_error( $result )
                ? [ 'status' => 'error', 'error' => $result->get_error_message() ]
                : $result;
        }
        return $results;
    }

    // ─── Recomendaciones ─────────────────────────────────────────────────────

    /**
     * Genera acciones recomendadas basadas en health data de todos los sitios.
     */
    public static function generate_recommendations(): array {
        $recs   = [];
        $sites  = self::get_all();
        $latest = defined( 'SAPWCC_LATEST_SUITE_VERSION' ) ? SAPWCC_LATEST_SUITE_VERSION : '0.0.0';

        foreach ( $sites as $key => $site ) {
            $health = self::get_cached_health( $key );

            if ( ! $health ) {
                $recs[] = [
                    'site_key' => $key,
                    'label'    => $site['label'],
                    'type'     => 'warning',
                    'icon'     => 'dashicons-clock',
                    'message'  => 'Sin datos de health — ejecuta un check.',
                    'action'   => 'check',
                ];
                continue;
            }

            $status = $health['status'] ?? 'unknown';

            // Unreachable
            if ( $status === 'unreachable' ) {
                $recs[] = [
                    'site_key' => $key,
                    'label'    => $site['label'],
                    'type'     => 'error',
                    'icon'     => 'dashicons-dismiss',
                    'message'  => 'Sitio inalcanzable: ' . ( $health['error'] ?? 'sin detalles' ),
                    'action'   => 'Verificar URL y conectividad',
                ];
            }

            // Critical
            if ( $status === 'critical' ) {
                $recs[] = [
                    'site_key' => $key,
                    'label'    => $site['label'],
                    'type'     => 'error',
                    'icon'     => 'dashicons-warning',
                    'message'  => 'Estado CRÍTICO — SAP desconectado o servidor caído.',
                    'action'   => 'Verificar SAP Business One y Service Layer',
                ];
            }

            // Degraded
            if ( $status === 'degraded' ) {
                $recs[] = [
                    'site_key' => $key,
                    'label'    => $site['label'],
                    'type'     => 'warning',
                    'icon'     => 'dashicons-flag',
                    'message'  => 'Estado degradado — posibles errores elevados o pedidos acumulados.',
                    'action'   => 'Revisar logs y cola de pedidos',
                ];
            }

            // Version check
            $version = $health['plugin']['version'] ?? '';
            if ( ! empty( $version ) && version_compare( $version, $latest, '<' ) ) {
                $recs[] = [
                    'site_key' => $key,
                    'label'    => $site['label'],
                    'type'     => 'info',
                    'icon'     => 'dashicons-update',
                    'message'  => "Versión {$version} — disponible {$latest}.",
                    'action'   => 'Programar actualización',
                ];
            }

            // High errors
            $errors24 = $health['errors_24h'] ?? 0;
            if ( $errors24 > 10 ) {
                $recs[] = [
                    'site_key' => $key,
                    'label'    => $site['label'],
                    'type'     => 'warning',
                    'icon'     => 'dashicons-info-outline',
                    'message'  => "{$errors24} errores en 24h.",
                    'action'   => 'Revisar logs de sincronización',
                ];
            }

            // Pending orders
            $pending = $health['pending_orders'] ?? 0;
            if ( $pending > 20 ) {
                $recs[] = [
                    'site_key' => $key,
                    'label'    => $site['label'],
                    'type'     => 'warning',
                    'icon'     => 'dashicons-cart',
                    'message'  => "{$pending} pedidos pendientes de sincronizar.",
                    'action'   => 'Verificar cron de pedidos y conectividad SAP',
                ];
            }

            // Plan inconsistency: compare flags.json plan vs health-reported plan.
            $site_id = $site['site_id'] ?? '';
            if ( ! empty( $site_id ) && $health && $status !== 'unreachable' && $status !== 'error' ) {
                $flags         = class_exists( 'SAPWCC_Flags' ) ? SAPWCC_Flags::read() : [];
                $flags_plan    = $flags['sites'][ $site_id ]['plan'] ?? '';
                $reported_plan = $health['site']['plan'] ?? '';

                // No plan assigned in flags.json.
                if ( empty( $flags_plan ) ) {
                    $recs[] = [
                        'site_key' => $key,
                        'label'    => $site['label'],
                        'type'     => 'warning',
                        'icon'     => 'dashicons-tag',
                        'message'  => "Site ID '{$site_id}' sin plan asignado en flags.json.",
                        'action'   => 'Asignar plan en Feature Flags > Configuracion por Sitio',
                    ];
                }

                // Mismatch between flags.json and what the site reports.
                if ( ! empty( $flags_plan ) && ! empty( $reported_plan ) && $flags_plan !== $reported_plan ) {
                    $recs[] = [
                        'site_key' => $key,
                        'label'    => $site['label'],
                        'type'     => 'warning',
                        'icon'     => 'dashicons-warning',
                        'message'  => "Inconsistencia de plan: flags.json={$flags_plan}, sitio reporta={$reported_plan}.",
                        'action'   => 'Verificar flags.json y publicar, o hacer health check para sincronizar',
                    ];
                }
            }

            // Transient warnings: errors/timeouts that later auto-resolved.
            $tw = self::get_transient_warnings( $key, 48 ); // 48h lookback.
            if ( $tw['count'] > 0 ) {
                $orders_list = implode( ', ', array_slice( $tw['orders_affected'], 0, 5 ) );
                if ( count( $tw['orders_affected'] ) > 5 ) {
                    $orders_list .= '...';
                }
                $recs[] = [
                    'site_key' => $key,
                    'label'    => $site['label'],
                    'type'     => 'info',
                    'icon'     => 'dashicons-info',
                    'message'  => "{$tw['count']} warnings auto-resueltos en 48h (pedidos: {$orders_list}).",
                    'action'   => 'Sistema se recuperó automáticamente. Revisar logs para patrones.',
                ];
            }
        }

        // Sort: error > warning > info
        usort( $recs, function ( $a, $b ) {
            $order = [ 'error' => 0, 'warning' => 1, 'info' => 2 ];
            return ( $order[ $a['type'] ] ?? 3 ) - ( $order[ $b['type'] ] ?? 3 );
        } );

        return $recs;
    }

    // ─── Encryption ──────────────────────────────────────────────────────────

    public static function encrypt( string $value ): string {
        if ( empty( $value ) ) {
            return '';
        }
        $key = hash( 'sha256', wp_salt( 'auth' ), true );
        $iv  = random_bytes( 16 );
        $enc = openssl_encrypt( $value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
        return base64_encode( $iv . $enc );
    }

    public static function decrypt( string $value ): string {
        if ( empty( $value ) ) {
            return '';
        }
        $key  = hash( 'sha256', wp_salt( 'auth' ), true );
        $data = base64_decode( $value, true );
        // Transparent migration: if decode fails or too short, value is plain-text.
        if ( false === $data || strlen( $data ) < 17 ) {
            return $value;
        }
        $iv  = substr( $data, 0, 16 );
        $enc = substr( $data, 16 );
        $dec = openssl_decrypt( $enc, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
        // If decryption fails the value was likely stored as plain-text (migration).
        return ( false !== $dec ) ? $dec : $value;
    }
}
