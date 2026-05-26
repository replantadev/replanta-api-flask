<?php
/**
 * SAPWCC_Flags — Lectura y escritura del flags.json local.
 *
 * @package SAPWCC
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SAPWCC_Flags {

    /**
     * Ruta por defecto al flags.json (repo sapwoo / docs).
     * Se usa sap-woo-suite (nombre real del plugin).
     */
    const DEFAULT_PATH = WP_CONTENT_DIR . '/plugins/sap-woo-suite/docs/flags.json';

    /**
     * Planes validos.
     */
    const VALID_PLANS = [ 'starter', 'business', 'enterprise' ];

    /**
     * Etiquetas de planes.
     */
    const PLAN_LABELS = [
        'starter'    => 'Starter',
        'business'   => 'Business',
        'enterprise' => 'Enterprise',
    ];

    /**
     * Colores de planes (para UI).
     */
    const PLAN_COLORS = [
        'starter'    => '#2271b1',
        'business'   => '#00a32a',
        'enterprise' => '#8c00b7',
    ];

    /**
     * Funcionalidades gestionadas por plan.
     */
    const PLAN_FEATURE_LABELS = [
        'multi_warehouse' => 'Multi-almacen',
        'catalog_import'  => 'Importacion catalogo',
        'b2b_mode'        => 'Modo B2B',
        'multichannel'    => 'Multichannel',
        'miravia'         => 'Miravia',
        'extension_hooks' => 'Hooks de extension',
        'volume_pricing'  => 'Volume Pricing',
    ];

    /**
     * Devuelve la ruta configurada al flags.json.
     *
     * Prioridad: opción guardada (respetada SIEMPRE si no está vacía) →
     * autodetect sap-woo (repo git) → sap-woo-suite → DEFAULT_PATH.
     *
     * Nota: si el directorio padre no existe, `write()` intentará crearlo con
     * `wp_mkdir_p()`. No descartamos la ruta aquí solo porque el dir no exista
     * todavía — esa comprobación es responsabilidad del paso de escritura.
     */
    public static function get_path(): string {
        $saved = trim( (string) get_option( 'sapwcc_flags_path', '' ) );

        if ( $saved !== '' ) {
            // Si el usuario guardó solo el directorio, anexa flags.json.
            if ( substr( $saved, -10 ) !== 'flags.json' ) {
                $saved = rtrim( $saved, '/\\' ) . '/flags.json';
            }
            return $saved;
        }

        // Auto-detect: priorizar sap-woo (repo git de GitHub Pages), luego sap-woo-suite.
        $candidates = [
            WP_CONTENT_DIR . '/plugins/sap-woo/docs/flags.json',
            WP_CONTENT_DIR . '/plugins/sap-woo-suite/docs/flags.json',
        ];

        foreach ( $candidates as $candidate ) {
            $plugin_root = dirname( dirname( $candidate ) );
            if ( file_exists( $candidate ) || is_dir( dirname( $candidate ) ) || is_dir( $plugin_root ) ) {
                return $candidate;
            }
        }

        return self::DEFAULT_PATH;
    }

    /**
     * Diagnóstico de por qué `read()` puede devolver vacío.
     *
     * @return array{path:string,exists:bool,readable:bool,writable_dir:bool,error:string}
     */
    public static function diagnose(): array {
        $path  = self::get_path();
        $dir   = dirname( $path );
        $diag  = [
            'path'         => $path,
            'exists'       => file_exists( $path ),
            'readable'     => is_readable( $path ),
            'writable_dir' => is_dir( $dir ) && is_writable( $dir ),
            'error'        => '',
        ];

        if ( ! $diag['exists'] ) {
            if ( ! is_dir( $dir ) ) {
                $diag['error'] = "El directorio no existe: {$dir}. Crea la carpeta o ajusta la ruta en Settings.";
            } else {
                $diag['error'] = "El archivo aún no existe. Edita y guarda en la pestaña Flags para crearlo.";
            }
        } elseif ( ! $diag['readable'] ) {
            $diag['error'] = "El archivo existe pero no tiene permisos de lectura para el usuario web.";
        }

        return $diag;
    }

    /**
     * Lee y parsea el flags.json.
     */
    public static function read(): array {
        $path = self::get_path();
        if ( ! file_exists( $path ) ) {
            return [];
        }

        $json = file_get_contents( $path );
        $data = json_decode( $json, true );

        return is_array( $data ) ? $data : [];
    }

    /**
     * Escribe datos al flags.json (formato pretty-print).
     * Crea el directorio si no existe.
     * Añade firma HMAC (_hmac) para que los sitios cliente puedan verificar integridad.
     */
    public static function write( array $data ): bool {
        $path = self::get_path();
        $dir  = dirname( $path );

        if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
            return false;
        }

        // Update timestamp.
        $data['_updated'] = current_time( 'Y-m-d' );

        // Remove any pre-existing _hmac before computing (prevent re-signing stale values).
        unset( $data['_hmac'] );

        // Compute canonical JSON for HMAC (same encoding used for verification on client).
        $canonical = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        if ( false !== $canonical ) {
            // Use the effective secret: wp-config override > auto-generated per-CC option.
            $secret = function_exists( 'sapwcc_get_flags_hmac_secret' ) ? sapwcc_get_flags_hmac_secret() : '';
            if ( ! empty( $secret ) ) {
                $data['_hmac'] = hash_hmac( 'sha256', $canonical, $secret );
            }
        }

        $json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        if ( false === $json ) {
            return false;
        }

        return false !== file_put_contents( $path, $json . "\n" );
    }

    /**
     * Etiquetas legibles para cada flag conocido.
     */
    public static function get_labels(): array {
        return [
            'cron_sapwc_cron_sync_orders'     => [ 'label' => 'Cron: Pedidos',    'group' => 'crons' ],
            'cron_sapwc_cron_sync_stock'      => [ 'label' => 'Cron: Stock',      'group' => 'crons' ],
            'cron_sapwc_cron_sync_products'   => [ 'label' => 'Cron: Productos',  'group' => 'crons' ],
            'cron_sapwc_cron_sync_categories' => [ 'label' => 'Cron: Categorías', 'group' => 'crons' ],
            'endpoint_sync-order'             => [ 'label' => 'EP: Sync Order',   'group' => 'endpoints' ],
            'endpoint_sync-products'          => [ 'label' => 'EP: Sync Products','group' => 'endpoints' ],
            'endpoint_stock-update'           => [ 'label' => 'EP: Stock Webhook','group' => 'endpoints' ],
            'realtime_order_sync'             => [ 'label' => 'Sync realtime',    'group' => 'features' ],
            'tiktok_channel'                  => [ 'label' => 'TikTok Shop',      'group' => 'features' ],
        ];
    }

    /**
     * Devuelve los site_ids conocidos de los sitios registrados.
     */
    public static function get_known_site_ids(): array {
        $sites = SAPWCC_Sites::get_all();
        $ids   = [];
        foreach ( $sites as $key => $site ) {
            if ( ! empty( $site['site_id'] ) ) {
                $ids[ $site['site_id'] ] = $site['label'];
            }
        }
        return $ids;
    }
}
