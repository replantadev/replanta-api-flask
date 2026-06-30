<?php

namespace Replanta\AiChat\Chat;

defined( 'ABSPATH' ) || exit;

/**
 * Manages chat sessions via cookie + WP transient.
 * Each session stores the last N messages to maintain conversation context.
 */
class SessionManager {

    const COOKIE_NAME    = 'replanta_chat_session';
    const HISTORY_LIMIT  = 20; // max messages to retain per session
    const SESSION_TTL    = 2 * HOUR_IN_SECONDS;

    public function get_or_create_session_id(): string {
        $id = $_COOKIE[ self::COOKIE_NAME ] ?? '';

        if ( ! $id || ! $this->session_exists( $id ) ) {
            $id = $this->create_session();
            // Set cookie via header (before output)
            setcookie( self::COOKIE_NAME, $id, [
                'expires'  => time() + self::SESSION_TTL,
                'path'     => '/',
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ] );
        }

        return $id;
    }

    public function get_session_id_from_header( string $header_value = '' ): string {
        // REST API passes session via X-Replanta-Session header
        return sanitize_text_field( $header_value ) ?: $this->get_or_create_session_id();
    }

    public function get_history( string $session_id ): array {
        return get_transient( $this->key( $session_id ) ) ?: [];
    }

    public function append_message( string $session_id, string $role, string $content ): void {
        $history   = $this->get_history( $session_id );
        $history[] = [ 'role' => $role, 'content' => $content ];

        // Keep only last N messages
        if ( count( $history ) > self::HISTORY_LIMIT ) {
            $history = array_slice( $history, -self::HISTORY_LIMIT );
        }

        set_transient( $this->key( $session_id ), $history, self::SESSION_TTL );
    }

    public function clear( string $session_id ): void {
        delete_transient( $this->key( $session_id ) );
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    private function create_session(): string {
        $id = wp_generate_uuid4();
        set_transient( $this->key( $id ), [], self::SESSION_TTL );
        return $id;
    }

    private function session_exists( string $id ): bool {
        return false !== get_transient( $this->key( $id ) );
    }

    private function key( string $id ): string {
        return 'replanta_sess_' . md5( $id );
    }
}
