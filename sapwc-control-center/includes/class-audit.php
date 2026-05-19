<?php
/**
 * SAPWCC_Audit — Local audit log for Control Center operations.
 *
 * Stores audit entries in wp_options as a JSON array (FIFO, max 200 entries).
 * Tracks plan changes, flag changes, site additions/removals, remote actions.
 *
 * @package SAPWCC
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SAPWCC_Audit {

    const OPTION_KEY  = 'sapwcc_audit_log';
    const MAX_ENTRIES = 200;

    /**
     * Log an audit entry.
     *
     * @param string $action  Action identifier (e.g. 'plan_change', 'flags_saved', 'site_added')
     * @param string $details Human-readable description
     * @param string $site    Site label or site_id (optional)
     */
    public static function log( string $action, string $details = '', string $site = '' ): void {
        $entries = get_option( self::OPTION_KEY, [] );

        if ( ! is_array( $entries ) ) {
            $entries = [];
        }

        $user = wp_get_current_user();

        array_unshift( $entries, [
            'timestamp' => current_time( 'Y-m-d H:i:s' ),
            'action'    => $action,
            'details'   => $details,
            'site'      => $site,
            'user'      => $user ? $user->user_login : 'system',
        ] );

        // FIFO: keep only the latest entries.
        if ( count( $entries ) > self::MAX_ENTRIES ) {
            $entries = array_slice( $entries, 0, self::MAX_ENTRIES );
        }

        update_option( self::OPTION_KEY, $entries, false );
    }

    /**
     * Get all audit entries.
     *
     * @param int $limit  Max entries to return (0 = all).
     * @return array
     */
    public static function get_all( int $limit = 0 ): array {
        $entries = get_option( self::OPTION_KEY, [] );

        if ( ! is_array( $entries ) ) {
            return [];
        }

        if ( $limit > 0 ) {
            return array_slice( $entries, 0, $limit );
        }

        return $entries;
    }

    /**
     * Clear the entire audit log.
     */
    public static function clear(): void {
        delete_option( self::OPTION_KEY );
    }

    /**
     * Action label mapping (for display).
     */
    const ACTION_LABELS = [
        'plan_change'      => 'Cambio de plan',
        'flags_saved'      => 'Flags guardados',
        'flags_published'  => 'Flags publicados (git push)',
        'site_added'       => 'Sitio añadido',
        'site_removed'     => 'Sitio eliminado',
        'site_meta_update' => 'Datos cliente actualizados',
        'remote_cache'     => 'Cache limpiada (remoto)',
        'remote_cron'      => 'Cron ejecutado (remoto)',
        'remote_maint'     => 'Mantenimiento toggle (remoto)',
        'remote_logs'      => 'Logs consultados (remoto)',
        'health_check'     => 'Health check ejecutado',
        'remote_update'    => 'Actualización ejecutada (remoto)',
        'rotate_secret'          => 'Secret rotado (remoto)',
        'set_cc_ip'              => 'IP allowlist propagada',
        'set_flags_hmac_secret'  => 'HMAC secret flags actualizado',
        'vigilante_config'       => 'Configuración Vigilante actualizada',
        'vigilante_alert'        => 'Alerta crítica Vigilante',
    ];

    /**
     * Get readable label for an action.
     */
    public static function get_label( string $action ): string {
        return self::ACTION_LABELS[ $action ] ?? ucfirst( str_replace( '_', ' ', $action ) );
    }

    /**
     * Icon mapping for actions (dashicons).
     */
    const ACTION_ICONS = [
        'plan_change'      => 'dashicons-tag',
        'flags_saved'      => 'dashicons-saved',
        'flags_published'  => 'dashicons-cloud-upload',
        'site_added'       => 'dashicons-plus-alt2',
        'site_removed'     => 'dashicons-trash',
        'site_meta_update' => 'dashicons-edit',
        'remote_cache'     => 'dashicons-performance',
        'remote_cron'      => 'dashicons-clock',
        'remote_maint'     => 'dashicons-admin-tools',
        'remote_logs'      => 'dashicons-clipboard',
        'health_check'     => 'dashicons-heart',
        'remote_update'    => 'dashicons-update-alt',
        'rotate_secret'          => 'dashicons-lock',
        'set_cc_ip'              => 'dashicons-shield',
        'set_flags_hmac_secret'  => 'dashicons-admin-network',
        'vigilante_config'       => 'dashicons-shield-alt',
        'vigilante_alert'        => 'dashicons-warning',
    ];

    /**
     * Get icon class for an action.
     */
    public static function get_icon( string $action ): string {
        return self::ACTION_ICONS[ $action ] ?? 'dashicons-marker';
    }
}
