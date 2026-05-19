<?php
/**
 * SAPWCC_AI — Claude / OpenAI fallback for issue explanations.
 *
 * Tries Claude (claude-haiku-4-5-20251001) first; falls back to OpenAI
 * (gpt-4o-mini) if the Claude key is missing or the call fails.
 * Results are cached in transients for 1 hour to avoid repeated API calls
 * for the same issue ID.
 *
 * Keys are stored AES-256-CBC encrypted in wp_options via SAPWCC_Sites helpers.
 *
 * @package SAPWCC
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SAPWCC_AI {

    const CLAUDE_URL   = 'https://api.anthropic.com/v1/messages';
    const OPENAI_URL   = 'https://api.openai.com/v1/chat/completions';
    const CLAUDE_MODEL = 'claude-haiku-4-5-20251001';
    const OPENAI_MODEL = 'gpt-4o-mini';
    const CACHE_TTL    = 3600;

    // ── Key management ───────────────────────────────────────────────────────

    public static function is_configured(): bool {
        return ! empty( self::get_claude_key() ) || ! empty( self::get_openai_key() );
    }

    public static function active_provider(): string {
        if ( ! empty( self::get_claude_key() ) ) return 'claude';
        if ( ! empty( self::get_openai_key() ) ) return 'openai';
        return '';
    }

    public static function get_claude_key(): string {
        $v = get_option( 'sapwcc_ai_claude_key', '' );
        return $v ? SAPWCC_Sites::decrypt( $v ) : '';
    }

    public static function get_openai_key(): string {
        $v = get_option( 'sapwcc_ai_openai_key', '' );
        return $v ? SAPWCC_Sites::decrypt( $v ) : '';
    }

    public static function save_claude_key( string $key ): void {
        if ( empty( $key ) ) {
            delete_option( 'sapwcc_ai_claude_key' );
            return;
        }
        update_option( 'sapwcc_ai_claude_key', SAPWCC_Sites::encrypt( $key ), false );
    }

    public static function save_openai_key( string $key ): void {
        if ( empty( $key ) ) {
            delete_option( 'sapwcc_ai_openai_key' );
            return;
        }
        update_option( 'sapwcc_ai_openai_key', SAPWCC_Sites::encrypt( $key ), false );
    }

    // ── Main entry point ─────────────────────────────────────────────────────

    /**
     * Get an AI-generated explanation for a detected issue.
     *
     * @param string $issue_type  e.g. 'retry_exhausted', 'inactive_customer'
     * @param array  $context     Raw issue context array from the Vigilante.
     * @param string $site_label  Human-readable site name.
     * @param string $issue_id    Stable unique ID (used as cache key).
     * @return array{explanation:string,steps:string[],prevention:string}|null
     */
    public static function explain( string $issue_type, array $context, string $site_label, string $issue_id ): ?array {
        if ( ! self::is_configured() ) {
            return null;
        }

        $cache_key = 'sapwcc_ai_' . md5( $issue_id . $issue_type . wp_json_encode( $context ) );
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) {
            return $cached;
        }

        $prompt = self::build_prompt( $issue_type, $context, $site_label );

        $raw = self::call_claude( $prompt );
        if ( ! $raw ) {
            $raw = self::call_openai( $prompt );
        }
        if ( ! $raw ) {
            return null;
        }

        $parsed = self::parse_response( $raw );
        if ( ! $parsed ) {
            return null;
        }

        set_transient( $cache_key, $parsed, self::CACHE_TTL );
        return $parsed;
    }

    // ── Prompt builder ───────────────────────────────────────────────────────

    private static function build_prompt( string $issue_type, array $context, string $site_label ): string {
        $ctx = wp_json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
        return <<<PROMPT
Eres el asistente técnico de SAP Woo Suite, un conector entre WooCommerce y SAP Business One.
Se ha detectado un problema en el sitio "{$site_label}".

Tipo de problema: {$issue_type}
Datos del problema:
{$ctx}

Responde ÚNICAMENTE con un objeto JSON válido con estas 3 claves exactas (sin texto adicional):
{
  "explanation": "qué salió mal, en 1-2 frases, sin jerga técnica, orientado al operador",
  "steps": ["acción concreta 1", "acción concreta 2", "acción concreta 3 si aplica"],
  "prevention": "cómo evitar que vuelva a ocurrir, en 1 frase"
}

Responde en español. Sé directo y accionable. Máximo 3 pasos.
PROMPT;
    }

    // ── Provider calls ───────────────────────────────────────────────────────

    private static function call_claude( string $prompt ): string {
        $key = self::get_claude_key();
        if ( empty( $key ) ) return '';

        $response = wp_remote_post( self::CLAUDE_URL, [
            'timeout' => 25,
            'headers' => [
                'x-api-key'         => $key,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ],
            'body' => wp_json_encode( [
                'model'      => self::CLAUDE_MODEL,
                'max_tokens' => 500,
                'messages'   => [ [ 'role' => 'user', 'content' => $prompt ] ],
            ] ),
        ] );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return '';
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        return $body['content'][0]['text'] ?? '';
    }

    private static function call_openai( string $prompt ): string {
        $key = self::get_openai_key();
        if ( empty( $key ) ) return '';

        $response = wp_remote_post( self::OPENAI_URL, [
            'timeout' => 25,
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode( [
                'model'      => self::OPENAI_MODEL,
                'max_tokens' => 500,
                'messages'   => [ [ 'role' => 'user', 'content' => $prompt ] ],
            ] ),
        ] );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return '';
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        return $body['choices'][0]['message']['content'] ?? '';
    }

    // ── Response parser ──────────────────────────────────────────────────────

    private static function parse_response( string $raw ): ?array {
        // Strip markdown code fences if present.
        $raw = preg_replace( '/^```(?:json)?\s*/m', '', $raw );
        $raw = preg_replace( '/\s*```$/m', '', $raw );

        $parsed = json_decode( trim( $raw ), true );
        if ( ! is_array( $parsed ) ) return null;
        if ( empty( $parsed['explanation'] ) ) return null;

        return [
            'explanation' => (string) ( $parsed['explanation'] ?? '' ),
            'steps'       => array_values( array_filter( (array) ( $parsed['steps'] ?? [] ) ) ),
            'prevention'  => (string) ( $parsed['prevention'] ?? '' ),
        ];
    }
}
