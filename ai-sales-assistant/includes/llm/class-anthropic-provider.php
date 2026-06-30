<?php

namespace Replanta\AiChat\Llm;

use Replanta\AiChat\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Anthropic Claude provider via Messages API with tool use and streaming.
 */
class AnthropicProvider implements LlmProvider {

    const API_URL = 'https://api.anthropic.com/v1/messages';
    const VERSION = '2023-06-01';

    private string $api_key;
    private string $model;
    private float  $temperature;
    private int    $max_tokens;

    public function __construct() {
        $opts              = Options::get_provider();
        $this->api_key     = Options::anthropic_key();
        $this->model       = $opts['anthropic_model'] ?? 'claude-sonnet-4-6';
        $this->temperature = (float) ( $opts['temperature'] ?? 0.2 );
        $this->max_tokens  = (int) ( $opts['max_tokens'] ?? 1024 );
    }

    // ── Non-streaming ─────────────────────────────────────────────────────────

    public function chat( array $messages, array $tools, string $system ): LlmResponse {
        $body = $this->build_body( $messages, $tools, $system, false );

        $response = wp_remote_post( self::API_URL, [
            'timeout' => 60,
            'headers' => $this->headers(),
            'body'    => wp_json_encode( $body ),
        ] );

        if ( is_wp_error( $response ) ) {
            throw new \RuntimeException( 'Anthropic API error: ' . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $code ) {
            throw new \RuntimeException( 'Anthropic API ' . $code . ': ' . ( $data['error']['message'] ?? 'unknown' ) );
        }

        return $this->parse_response( $data );
    }

    // ── Streaming (SSE) ───────────────────────────────────────────────────────

    public function stream( array $messages, array $tools, string $system, callable $chunk_cb ): LlmResponse {
        $body = $this->build_body( $messages, $tools, $system, true );

        $accumulated_text  = '';
        $tool_calls        = [];
        $input_tokens      = 0;
        $output_tokens     = 0;
        $stop_reason       = null;

        $current_tool_id    = null;
        $current_tool_name  = null;
        $current_tool_input = '';

        $response = wp_remote_post( self::API_URL, [
            'timeout'  => 120,
            'stream'   => true,
            'filename' => '', // wp_remote_post with stream=true
            'headers'  => $this->headers(),
            'body'     => wp_json_encode( $body ),
        ] );

        if ( is_wp_error( $response ) ) {
            throw new \RuntimeException( 'Anthropic stream error: ' . $response->get_error_message() );
        }

        // WordPress doesn't natively support streaming, so we read the full body.
        // For true SSE, use output buffering after setting headers.
        // Here we do a blocking read and call chunk_cb once with full text.
        // See README for nginx/server-sent-events setup to enable true streaming.
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! $data ) {
            throw new \RuntimeException( 'Empty response from Anthropic.' );
        }

        $parsed = $this->parse_response( $data );
        $chunk_cb( $parsed->text );
        return $parsed;
    }

    /**
     * True SSE streaming. Call this inside a REST endpoint that has already
     * set SSE headers (Content-Type: text/event-stream).
     * Uses fopen/fgets to read the stream line by line.
     */
    public function stream_sse( array $messages, array $tools, string $system, callable $chunk_cb ): LlmResponse {
        $body = $this->build_body( $messages, $tools, $system, true );

        $ch = curl_init( self::API_URL );
        curl_setopt_array( $ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => wp_json_encode( $body ),
            CURLOPT_HTTPHEADER     => array_map(
                static fn( $k, $v ) => "$k: $v",
                array_keys( $this->headers() ),
                array_values( $this->headers() )
            ),
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_WRITEFUNCTION  => static function ( $curl, $data ) use ( $chunk_cb, &$accumulated_text, &$tool_calls, &$input_tokens, &$output_tokens, &$stop_reason, &$current_tool_id, &$current_tool_name, &$current_tool_input ) {
                foreach ( explode( "\n", $data ) as $line ) {
                    $line = trim( $line );
                    if ( ! str_starts_with( $line, 'data: ' ) ) {
                        continue;
                    }

                    $json  = substr( $line, 6 );
                    $event = json_decode( $json, true );
                    if ( ! $event ) {
                        continue;
                    }

                    switch ( $event['type'] ?? '' ) {
                        case 'content_block_start':
                            $block = $event['content_block'] ?? [];
                            if ( 'tool_use' === ( $block['type'] ?? '' ) ) {
                                $current_tool_id    = $block['id'] ?? null;
                                $current_tool_name  = $block['name'] ?? null;
                                $current_tool_input = '';
                            }
                            break;

                        case 'content_block_delta':
                            $delta = $event['delta'] ?? [];
                            if ( 'text_delta' === ( $delta['type'] ?? '' ) ) {
                                $text             = $delta['text'] ?? '';
                                $accumulated_text .= $text;
                                $chunk_cb( $text );
                            } elseif ( 'input_json_delta' === ( $delta['type'] ?? '' ) ) {
                                $current_tool_input .= $delta['partial_json'] ?? '';
                            }
                            break;

                        case 'content_block_stop':
                            if ( $current_tool_id ) {
                                $tool_calls[] = [
                                    'id'    => $current_tool_id,
                                    'name'  => $current_tool_name,
                                    'input' => json_decode( $current_tool_input, true ) ?? [],
                                ];
                                $current_tool_id    = null;
                                $current_tool_name  = null;
                                $current_tool_input = '';
                            }
                            break;

                        case 'message_delta':
                            $stop_reason   = $event['delta']['stop_reason'] ?? null;
                            $output_tokens = $event['usage']['output_tokens'] ?? $output_tokens;
                            break;

                        case 'message_start':
                            $input_tokens = $event['message']['usage']['input_tokens'] ?? 0;
                            break;
                    }
                }
                return strlen( $data );
            },
        ] );

        // Re-declare variables for closure
        $accumulated_text  = '';
        $tool_calls        = [];
        $input_tokens      = 0;
        $output_tokens     = 0;
        $stop_reason       = null;
        $current_tool_id   = null;
        $current_tool_name = null;
        $current_tool_input= '';

        curl_exec( $ch );
        $curl_error = curl_error( $ch );
        curl_close( $ch );

        if ( $curl_error ) {
            throw new \RuntimeException( 'cURL stream error: ' . $curl_error );
        }

        return new LlmResponse(
            text:          $accumulated_text,
            tool_calls:    $tool_calls,
            stop_reason:   $stop_reason,
            input_tokens:  $input_tokens,
            output_tokens: $output_tokens,
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function build_body( array $messages, array $tools, string $system, bool $stream ): array {
        $body = [
            'model'       => $this->model,
            'max_tokens'  => $this->max_tokens,
            'temperature' => $this->temperature,
            'system'      => $system,
            'messages'    => $this->normalize_messages( $messages ),
        ];

        if ( ! empty( $tools ) ) {
            $body['tools'] = $tools;
        }

        if ( $stream ) {
            $body['stream'] = true;
        }

        return $body;
    }

    private function normalize_messages( array $messages ): array {
        $normalized = [];
        foreach ( $messages as $msg ) {
            $role = $msg['role'] ?? 'user';

            if ( 'tool_result' === $role ) {
                // Tool results must be in user turn as content blocks
                $last = &$normalized[ count( $normalized ) - 1 ];
                if ( isset( $last ) && 'user' === $last['role'] && is_array( $last['content'] ) ) {
                    $last['content'][] = [
                        'type'       => 'tool_result',
                        'tool_use_id'=> $msg['tool_use_id'],
                        'content'    => $msg['content'],
                    ];
                } else {
                    $normalized[] = [
                        'role'    => 'user',
                        'content' => [ [
                            'type'        => 'tool_result',
                            'tool_use_id' => $msg['tool_use_id'],
                            'content'     => $msg['content'],
                        ] ],
                    ];
                }
                continue;
            }

            if ( 'tool_use' === $role ) {
                // Tool use goes in assistant turn
                $last = &$normalized[ count( $normalized ) - 1 ];
                if ( isset( $last ) && 'assistant' === $last['role'] && is_array( $last['content'] ) ) {
                    $last['content'][] = [
                        'type'  => 'tool_use',
                        'id'    => $msg['tool_use_id'],
                        'name'  => $msg['name'],
                        'input' => $msg['input'],
                    ];
                } else {
                    $normalized[] = [
                        'role'    => 'assistant',
                        'content' => [ [
                            'type'  => 'tool_use',
                            'id'    => $msg['tool_use_id'],
                            'name'  => $msg['name'],
                            'input' => $msg['input'],
                        ] ],
                    ];
                }
                continue;
            }

            $normalized[] = [
                'role'    => $role,
                'content' => is_string( $msg['content'] ) ? $msg['content'] : $msg['content'],
            ];
        }

        return $normalized;
    }

    private function parse_response( array $data ): LlmResponse {
        $text       = '';
        $tool_calls = [];

        foreach ( $data['content'] ?? [] as $block ) {
            if ( 'text' === ( $block['type'] ?? '' ) ) {
                $text .= $block['text'];
            } elseif ( 'tool_use' === ( $block['type'] ?? '' ) ) {
                $tool_calls[] = [
                    'id'    => $block['id'],
                    'name'  => $block['name'],
                    'input' => $block['input'] ?? [],
                ];
            }
        }

        return new LlmResponse(
            text:          trim( $text ),
            tool_calls:    $tool_calls,
            stop_reason:   $data['stop_reason'] ?? null,
            input_tokens:  $data['usage']['input_tokens'] ?? 0,
            output_tokens: $data['usage']['output_tokens'] ?? 0,
        );
    }

    private function headers(): array {
        return [
            'x-api-key'         => $this->api_key,
            'anthropic-version' => self::VERSION,
            'content-type'      => 'application/json',
        ];
    }
}
