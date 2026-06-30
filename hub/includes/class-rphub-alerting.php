<?php
/**
 * RPHUB Alerting
 *
 * Centralised notification dispatcher for Replanta Hub.
 * Sends alerts via Slack webhook and/or email based on settings.
 *
 * @package ReplacantaHub
 * @since   1.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RPHUB_Alerting {

    /** Alert severity levels */
    const LEVEL_INFO    = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR   = 'error';

    /**
     * Send an alert through all configured channels.
     *
     * @param string $level   One of the LEVEL_* constants.
     * @param string $title   Short title shown in subject / Slack header.
     * @param string $message Human-readable detail.
     * @param array  $context Optional extra key→value pairs appended to the message.
     */
    public static function notify(
        string $level,
        string $title,
        string $message,
        array $context = []
    ): void {
        $settings = get_option( 'rphub_settings', [] );

        // Honour minimum level threshold.
        $min_level = $settings['alert_min_level'] ?? self::LEVEL_WARNING;
        if ( ! self::is_level_gte( $level, $min_level ) ) {
            return;
        }

        $site_name = get_bloginfo( 'name' );

        self::send_slack( $settings, $level, $title, $message, $context, $site_name );
        self::send_email( $settings, $level, $title, $message, $context, $site_name );
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    private static function send_slack(
        array  $settings,
        string $level,
        string $title,
        string $message,
        array  $context,
        string $site_name
    ): void {
        $webhook_url = trim( $settings['slack_webhook_url'] ?? '' );
        if ( empty( $webhook_url ) ) {
            return;
        }

        $color = self::level_color( $level );

        $fields = [];
        foreach ( $context as $key => $value ) {
            $fields[] = [
                'title' => (string) $key,
                'value' => (string) $value,
                'short' => true,
            ];
        }

        $payload = [
            'username'    => 'Replanta Hub',
            'icon_emoji'  => ':satellite_antenna:',
            'attachments' => [
                [
                    'fallback'  => "[$level] $title: $message",
                    'color'     => $color,
                    'title'     => "[$site_name] $title",
                    'text'      => $message,
                    'fields'    => $fields,
                    'footer'    => 'Replanta Hub',
                    'ts'        => time(),
                ],
            ],
        ];

        wp_remote_post( $webhook_url, [
            'headers'  => [ 'Content-Type' => 'application/json' ],
            'body'     => wp_json_encode( $payload ),
            'timeout'  => 8,
            'blocking' => false,
        ] );
    }

    private static function send_email(
        array  $settings,
        string $level,
        string $title,
        string $message,
        array  $context,
        string $site_name
    ): void {
        $to = trim( $settings['alert_email'] ?? '' );
        if ( empty( $to ) ) {
            $to = get_option( 'admin_email', '' );
        }
        if ( empty( $to ) ) {
            return;
        }

        $subject = sprintf( '[%s][%s] %s', strtoupper( $level ), $site_name, $title );

        $body  = $message . "\n\n";
        foreach ( $context as $key => $value ) {
            $body .= sprintf( "%-20s %s\n", $key . ':', $value );
        }
        $body .= "\n-- Replanta Hub\n" . home_url();

        wp_mail( $to, $subject, $body );
    }

    /**
     * Returns true if $level >= $min_level (in severity order).
     */
    private static function is_level_gte( string $level, string $min_level ): bool {
        $order = [ self::LEVEL_INFO => 0, self::LEVEL_WARNING => 1, self::LEVEL_ERROR => 2 ];
        $l = $order[ $level ]     ?? 0;
        $m = $order[ $min_level ] ?? 0;
        return $l >= $m;
    }

    private static function level_color( string $level ): string {
        $map = [
            self::LEVEL_INFO    => '#36a64f',
            self::LEVEL_WARNING => '#f0ad4e',
            self::LEVEL_ERROR   => '#cc0000',
        ];
        return $map[ $level ] ?? '#777777';
    }
}
