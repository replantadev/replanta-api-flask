<?php

namespace Replanta\AiChat;

defined( 'ABSPATH' ) || exit;

/**
 * Centralizes all plugin options with type-safe getters and a defaults registry.
 */
class Options {

    const PREFIX = 'replanta_ai_chat_';

    // ── Keys ──────────────────────────────────────────────────────────────────

    const KEY_GENERAL    = self::PREFIX . 'general';
    const KEY_PROVIDER   = self::PREFIX . 'provider';
    const KEY_INDEXING   = self::PREFIX . 'indexing';
    const KEY_BEHAVIOUR  = self::PREFIX . 'behaviour';
    const KEY_TOOLS      = self::PREFIX . 'tools';
    const KEY_LICENSE    = self::PREFIX . 'license';

    // ── Defaults ──────────────────────────────────────────────────────────────

    public static function defaults(): array {
        return [
            self::KEY_GENERAL   => [
                'assistant_name'    => 'Asistente',
                'welcome_message'   => '¡Hola! ¿En qué puedo ayudarte hoy?',
                'widget_position'   => 'bottom-right',
                'primary_color'     => '#2d6a4f',
                'chat_enabled'      => true,
                'show_on'           => 'all', // 'all', 'shop', 'product', 'cart'
            ],
            self::KEY_PROVIDER  => [
                'llm_provider'      => 'anthropic', // 'anthropic' | 'openai'
                'anthropic_key'     => '',
                'anthropic_model'   => 'claude-sonnet-4-6',
                'openai_key'        => '',
                'openai_llm_model'  => 'gpt-4o',
                'embeddings_key'    => '', // OpenAI key for embeddings
                'embeddings_model'  => 'text-embedding-3-small',
                'temperature'       => 0.2,
                'max_tokens'        => 1024,
                'monthly_budget'    => 0, // 0 = unlimited
            ],
            self::KEY_INDEXING  => [
                'post_types'        => [ 'product' ],
                'acf_fields'        => [], // [ ['key'=>'field_xxx','label'=>'Ingredientes'], ... ]
                'meta_fields'       => [], // [ ['key'=>'_custom_meta','label'=>'...'], ... ]
                'exclude_cats'      => [],
                'index_out_of_stock'=> true,
                'auto_index'        => true, // re-index on product save
            ],
            self::KEY_BEHAVIOUR => [
                'system_prompt_extra' => '',
                'fallback_message'    => 'No tengo esa información. ¿Quieres que te pase con un asesor?',
                'claims_blacklist'    => "cura\ntrata\nmedicamento\nprescripción\ndiagnóstico",
                'max_context_products'=> 5,
                'escalation_email'    => '',
                'language'            => 'es',
            ],
            self::KEY_TOOLS     => [
                'cart_enabled'        => true,
                'order_enabled'       => true,
                'escalation_enabled'  => true,
                'search_enabled'      => true,
            ],
            self::KEY_LICENSE   => [
                'license_key'         => '',
                'license_status'      => 'inactive', // 'active' | 'inactive' | 'expired' | 'invalid'
                'license_expires'     => '',
            ],
        ];
    }

    // ── Read ──────────────────────────────────────────────────────────────────

    public static function get( string $key ): array {
        $defaults = self::defaults();
        $stored   = get_option( self::PREFIX . $key, [] );
        $default  = $defaults[ self::PREFIX . $key ] ?? [];

        return wp_parse_args( $stored, $default );
    }

    public static function get_general(): array   { return self::get( 'general' ); }
    public static function get_provider(): array  { return self::get( 'provider' ); }
    public static function get_indexing(): array  { return self::get( 'indexing' ); }
    public static function get_behaviour(): array { return self::get( 'behaviour' ); }
    public static function get_tools(): array     { return self::get( 'tools' ); }
    public static function get_license(): array   { return self::get( 'license' ); }

    // ── Write ─────────────────────────────────────────────────────────────────

    public static function update( string $key, array $data ): bool {
        return update_option( self::PREFIX . $key, $data, false );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public static function anthropic_key(): string {
        $p = self::get_provider();
        return defined( 'REPLANTA_ANTHROPIC_KEY' ) ? REPLANTA_ANTHROPIC_KEY : ( $p['anthropic_key'] ?? '' );
    }

    public static function openai_key(): string {
        $p = self::get_provider();
        return defined( 'REPLANTA_OPENAI_KEY' ) ? REPLANTA_OPENAI_KEY : ( $p['openai_key'] ?? '' );
    }

    public static function embeddings_key(): string {
        $p = self::get_provider();
        $k = defined( 'REPLANTA_EMBEDDINGS_KEY' ) ? REPLANTA_EMBEDDINGS_KEY : ( $p['embeddings_key'] ?? '' );
        // Fallback to OpenAI key if same provider
        return $k ?: self::openai_key();
    }
}
