<?php

namespace Replanta\AiChat;

defined( 'ABSPATH' ) || exit;

/**
 * Handles license validation and automatic updates from replanta.dev API.
 * Distribution model: each site needs an active license key.
 */
class Updater {

    const API_BASE    = 'https://api.replanta.dev/plugins/ai-chat';
    const CACHE_KEY   = 'replanta_ai_chat_update_info';
    const CACHE_TTL   = 12 * HOUR_IN_SECONDS;

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init(): void {
        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );
        add_filter( 'plugins_api',                           [ $this, 'plugin_info' ], 10, 3 );
        add_action( 'admin_init',                            [ $this, 'handle_license_actions' ] );
    }

    // ── Update check ──────────────────────────────────────────────────────────

    public function check_for_update( object $transient ): object {
        if ( ! is_object( $transient ) ) {
            $transient = new \stdClass();
        }

        $info = $this->fetch_remote_info();
        if ( ! $info ) {
            return $transient;
        }

        if ( version_compare( $info['version'], REPLANTA_AI_CHAT_VERSION, '>' ) ) {
            $obj                           = new \stdClass();
            $obj->slug                     = 'replanta-ai-chat';
            $obj->plugin                   = REPLANTA_AI_CHAT_BASENAME;
            $obj->new_version              = $info['version'];
            $obj->url                      = $info['url'] ?? self::API_BASE;
            $obj->package                  = $info['package'] ?? '';
            $obj->requires                 = $info['requires'] ?? '6.4';
            $obj->requires_php             = $info['requires_php'] ?? '8.1';
            $obj->tested                   = $info['tested'] ?? '';
            $transient->response[ REPLANTA_AI_CHAT_BASENAME ] = $obj;
        }

        return $transient;
    }

    public function plugin_info( mixed $result, string $action, object $args ): mixed {
        if ( 'plugin_information' !== $action ) {
            return $result;
        }
        if ( ! isset( $args->slug ) || 'replanta-ai-chat' !== $args->slug ) {
            return $result;
        }

        $info = $this->fetch_remote_info();
        if ( ! $info ) {
            return $result;
        }

        $obj                = new \stdClass();
        $obj->name          = 'Replanta AI Chat';
        $obj->slug          = 'replanta-ai-chat';
        $obj->version       = $info['version'];
        $obj->author        = '<a href="https://replanta.dev">Replanta</a>';
        $obj->requires      = $info['requires'] ?? '6.4';
        $obj->tested        = $info['tested'] ?? '';
        $obj->requires_php  = $info['requires_php'] ?? '8.1';
        $obj->download_link = $info['package'] ?? '';
        $obj->sections      = [
            'description' => $info['description'] ?? '',
            'changelog'   => $info['changelog']   ?? '',
        ];

        return $obj;
    }

    // ── License ───────────────────────────────────────────────────────────────

    public function handle_license_actions(): void {
        if ( ! isset( $_POST['replanta_license_action'] ) ) {
            return;
        }
        if ( ! check_admin_referer( 'replanta_ai_chat_license' ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $action = sanitize_key( $_POST['replanta_license_action'] );
        $key    = sanitize_text_field( $_POST['replanta_license_key'] ?? '' );

        match ( $action ) {
            'activate'   => $this->activate_license( $key ),
            'deactivate' => $this->deactivate_license(),
            default      => null,
        };
    }

    public function activate_license( string $key ): void {
        $response = wp_remote_post( self::API_BASE . '/license/activate', [
            'timeout' => 15,
            'body'    => [
                'license_key' => $key,
                'site_url'    => home_url(),
                'plugin_ver'  => REPLANTA_AI_CHAT_VERSION,
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            add_settings_error( 'replanta_license', 'api_error', $response->get_error_message() );
            return;
        }

        $body   = json_decode( wp_remote_retrieve_body( $response ), true );
        $status = $body['status'] ?? 'invalid';

        Options::update( 'license', [
            'license_key'     => $key,
            'license_status'  => $status,
            'license_expires' => $body['expires'] ?? '',
        ] );

        delete_transient( self::CACHE_KEY );
    }

    public function deactivate_license(): void {
        $opts = Options::get_license();
        wp_remote_post( self::API_BASE . '/license/deactivate', [
            'timeout' => 15,
            'body'    => [
                'license_key' => $opts['license_key'],
                'site_url'    => home_url(),
            ],
        ] );

        Options::update( 'license', [
            'license_key'     => $opts['license_key'],
            'license_status'  => 'inactive',
            'license_expires' => '',
        ] );

        delete_transient( self::CACHE_KEY );
    }

    public function is_active(): bool {
        $opts = Options::get_license();
        return 'active' === ( $opts['license_status'] ?? '' );
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    private function fetch_remote_info(): ?array {
        $cached = get_transient( self::CACHE_KEY );
        if ( false !== $cached ) {
            return $cached ?: null;
        }

        $license = Options::get_license();
        $response = wp_remote_get( add_query_arg( [
            'license' => $license['license_key'] ?? '',
            'version' => REPLANTA_AI_CHAT_VERSION,
            'site'    => home_url(),
        ], self::API_BASE . '/info' ), [ 'timeout' => 15 ] );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            set_transient( self::CACHE_KEY, [], self::CACHE_TTL );
            return null;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) ) {
            set_transient( self::CACHE_KEY, [], self::CACHE_TTL );
            return null;
        }

        set_transient( self::CACHE_KEY, $data, self::CACHE_TTL );
        return $data;
    }
}
