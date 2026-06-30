<?php

namespace Replanta\AiChat\Admin;

use Replanta\AiChat\Options;

defined( 'ABSPATH' ) || exit;

class Admin {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init(): void {
        add_action( 'admin_menu',            [ $this, 'add_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_replanta_check_api',         [ $this, 'ajax_check_api' ] );
        add_action( 'wp_ajax_replanta_get_conversation',  [ ConversationsPage::class, 'ajax_get_conversation' ] );
    }

    public function ajax_check_api(): void {
        check_ajax_referer( 'replanta_check_api' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
        }

        $results  = [];
        $provider = Options::get_provider()['llm_provider'] ?? 'anthropic';

        // Test active LLM provider
        if ( 'openai' === $provider ) {
            $oai_llm_key = Options::openai_key();
            if ( ! $oai_llm_key ) {
                $results['anthropic'] = [ 'ok' => false, 'message' => 'Clave OpenAI (LLM) no configurada.' ];
            } else {
                $resp = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
                    'timeout' => 15,
                    'headers' => [
                        'Authorization' => "Bearer {$oai_llm_key}",
                        'Content-Type'  => 'application/json',
                    ],
                    'body' => wp_json_encode( [
                        'model'      => 'gpt-4o-mini',
                        'max_tokens' => 5,
                        'messages'   => [ [ 'role' => 'user', 'content' => 'hi' ] ],
                    ] ),
                ] );
                if ( is_wp_error( $resp ) ) {
                    $results['anthropic'] = [ 'ok' => false, 'message' => $resp->get_error_message() ];
                } else {
                    $code = wp_remote_retrieve_response_code( $resp );
                    $body = json_decode( wp_remote_retrieve_body( $resp ), true );
                    $results['anthropic'] = 200 === $code
                        ? [ 'ok' => true,  'message' => 'OpenAI LLM (GPT-4o): Conexión OK ✓' ]
                        : [ 'ok' => false, 'message' => 'OpenAI LLM: ' . ( $body['error']['message'] ?? "HTTP $code" ) ];
                }
            }
        } else {
            $ant_key = Options::anthropic_key();
            if ( ! $ant_key ) {
                $results['anthropic'] = [ 'ok' => false, 'message' => 'Clave Anthropic no configurada.' ];
            } else {
                $resp = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
                    'timeout' => 15,
                    'headers' => [
                        'x-api-key'         => $ant_key,
                        'anthropic-version' => '2023-06-01',
                        'content-type'      => 'application/json',
                    ],
                    'body' => wp_json_encode( [
                        'model'      => 'claude-haiku-4-5-20251001',
                        'max_tokens' => 5,
                        'messages'   => [ [ 'role' => 'user', 'content' => 'hi' ] ],
                    ] ),
                ] );
                if ( is_wp_error( $resp ) ) {
                    $results['anthropic'] = [ 'ok' => false, 'message' => $resp->get_error_message() ];
                } else {
                    $code = wp_remote_retrieve_response_code( $resp );
                    $body = json_decode( wp_remote_retrieve_body( $resp ), true );
                    $results['anthropic'] = 200 === $code
                        ? [ 'ok' => true,  'message' => 'Anthropic Claude: Conexión OK ✓' ]
                        : [ 'ok' => false, 'message' => $body['error']['message'] ?? "HTTP $code" ];
                }
            }
        }

        // Test OpenAI Embeddings (always needed regardless of LLM provider)
        $oai_key = Options::embeddings_key();
        if ( ! $oai_key ) {
            $results['openai'] = [ 'ok' => false, 'message' => 'Clave no configurada.' ];
        } else {
            $resp2 = wp_remote_post( 'https://api.openai.com/v1/embeddings', [
                'timeout' => 15,
                'headers' => [
                    'Authorization' => "Bearer {$oai_key}",
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode( [
                    'input' => 'test',
                    'model' => 'text-embedding-3-small',
                ] ),
            ] );

            if ( is_wp_error( $resp2 ) ) {
                $results['openai'] = [ 'ok' => false, 'message' => $resp2->get_error_message() ];
            } else {
                $code2 = wp_remote_retrieve_response_code( $resp2 );
                $body2 = json_decode( wp_remote_retrieve_body( $resp2 ), true );
                if ( 200 === $code2 ) {
                    $results['openai'] = [ 'ok' => true, 'message' => 'Conexión OK ✓' ];
                } else {
                    $msg2 = $body2['error']['message'] ?? "HTTP $code2";
                    $results['openai'] = [ 'ok' => false, 'message' => $msg2 ];
                }
            }
        }

        wp_send_json_success( $results );
    }

    public function add_menu(): void {
        add_menu_page(
            __( 'Replanta AI Chat', 'replanta-ai-chat' ),
            __( 'AI Chat', 'replanta-ai-chat' ),
            'manage_options',
            'replanta-ai-chat',
            [ SettingsPage::class, 'render' ],
            'dashicons-format-chat',
            58
        );

        add_submenu_page(
            'replanta-ai-chat',
            __( 'Configuración', 'replanta-ai-chat' ),
            __( 'Configuración', 'replanta-ai-chat' ),
            'manage_options',
            'replanta-ai-chat',
            [ SettingsPage::class, 'render' ]
        );

        add_submenu_page(
            'replanta-ai-chat',
            __( 'Indexación', 'replanta-ai-chat' ),
            __( 'Indexación', 'replanta-ai-chat' ),
            'manage_options',
            'replanta-ai-chat-indexing',
            [ IndexingPage::class, 'render' ]
        );

        add_submenu_page(
            'replanta-ai-chat',
            __( 'Conversaciones', 'replanta-ai-chat' ),
            __( 'Conversaciones', 'replanta-ai-chat' ),
            'manage_options',
            'replanta-ai-chat-conversations',
            [ ConversationsPage::class, 'render' ]
        );
    }

    public function enqueue_assets( string $hook ): void {
        if ( strpos( $hook, 'replanta-ai-chat' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'replanta-ai-chat-admin',
            REPLANTA_AI_CHAT_URL . 'assets/css/admin.css',
            [ 'wp-components' ],
            REPLANTA_AI_CHAT_VERSION
        );

        wp_enqueue_script(
            'replanta-ai-chat-admin',
            REPLANTA_AI_CHAT_URL . 'assets/js/admin.js',
            [ 'wp-api-fetch', 'wp-i18n' ],
            REPLANTA_AI_CHAT_VERSION,
            true
        );

        wp_localize_script( 'replanta-ai-chat-admin', 'replantaAdmin', [
            'apiUrl'             => rest_url( 'replanta/v1/' ),
            'nonce'              => wp_create_nonce( 'wp_rest' ),
            'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
            'checkApiNonce'      => wp_create_nonce( 'replanta_check_api' ),
            'conversationNonce'  => wp_create_nonce( 'replanta_conversation' ),
        ] );
    }
}
