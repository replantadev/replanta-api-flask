<?php

namespace Replanta\AiChat\Llm;

use Replanta\AiChat\Options;

defined( 'ABSPATH' ) || exit;

/**
 * OpenAI GPT provider — implements LlmProvider using chat completions API.
 * Normalises the Anthropic-style message format used by ChatService into
 * OpenAI format before each API call.
 */
class OpenAiProvider implements LlmProvider {

    const API_URL = 'https://api.openai.com/v1/chat/completions';

    public function chat( array $messages, array $tools, string $system ): LlmResponse {
        $key = Options::openai_key();
        if ( ! $key ) {
            throw new \RuntimeException( 'OpenAI API key not configured.' );
        }

        $opts   = Options::get_provider();
        $model  = $opts['openai_llm_model'] ?? 'gpt-4o';
        $params = [
            'model'       => $model,
            'temperature' => (float) ( $opts['temperature'] ?? 0.2 ),
            'max_tokens'  => (int)   ( $opts['max_tokens']  ?? 1024 ),
            'messages'    => $this->to_openai_messages( $messages, $system ),
        ];

        if ( ! empty( $tools ) ) {
            $params['tools']       = $this->to_openai_tools( $tools );
            $params['tool_choice'] = 'auto';
        }

        $response = wp_remote_post( self::API_URL, [
            'timeout' => 60,
            'headers' => [
                'Authorization' => "Bearer {$key}",
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode( $params ),
        ] );

        if ( is_wp_error( $response ) ) {
            throw new \RuntimeException( 'OpenAI request failed: ' . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            throw new \RuntimeException( 'OpenAI API ' . $code . ': ' . ( $body['error']['message'] ?? 'unknown' ) );
        }

        $body   = json_decode( wp_remote_retrieve_body( $response ), true );
        $choice = $body['choices'][0] ?? [];
        $msg    = $choice['message'] ?? [];
        $usage  = $body['usage'] ?? [];

        $text       = $msg['content'] ?? '';
        $tool_calls = [];

        foreach ( $msg['tool_calls'] ?? [] as $tc ) {
            $tool_calls[] = [
                'id'    => $tc['id'],
                'name'  => $tc['function']['name'],
                'input' => json_decode( $tc['function']['arguments'], true ) ?? [],
            ];
        }

        return new LlmResponse(
            text:          $text ?? '',
            tool_calls:    $tool_calls,
            stop_reason:   $choice['finish_reason'] ?? null,
            input_tokens:  (int) ( $usage['prompt_tokens'] ?? 0 ),
            output_tokens: (int) ( $usage['completion_tokens'] ?? 0 ),
        );
    }

    public function stream( array $messages, array $tools, string $system, callable $chunk_cb ): LlmResponse {
        // Fallback to non-streaming for now — streaming OpenAI SSE is functionally identical
        // but adds complexity not needed for the tool-calling loop.
        return $this->chat( $messages, $tools, $system );
    }

    // ── Normalisation ─────────────────────────────────────────────────────────

    /**
     * Convert ChatService's Anthropic-style messages to OpenAI format.
     *
     * ChatService produces:
     *   {role:'user', content:'...'}
     *   {role:'assistant', content:'...'}       ← text part of tool-call turn
     *   {role:'tool_use', tool_use_id, name, input}  ← one per tool
     *   {role:'tool_result', tool_use_id, content}   ← one per tool
     *
     * OpenAI expects:
     *   {role:'user', content:'...'}
     *   {role:'assistant', content:'...', tool_calls:[{id, type:'function', function:{name, arguments}}]}
     *   {role:'tool', tool_call_id:'...', content:'...'}
     */
    private function to_openai_messages( array $messages, string $system ): array {
        $out = [ [ 'role' => 'system', 'content' => $system ] ];

        $i   = 0;
        $len = count( $messages );

        while ( $i < $len ) {
            $msg = $messages[ $i ];

            if ( $msg['role'] === 'tool_use' || $msg['role'] === 'tool_result' ) {
                // Already handled below — skip stray entries
                $i++;
                continue;
            }

            if ( $msg['role'] === 'assistant' ) {
                // Collect consecutive tool_use entries that follow
                $tool_calls = [];
                $j          = $i + 1;
                while ( $j < $len && $messages[ $j ]['role'] === 'tool_use' ) {
                    $tu = $messages[ $j ];
                    $tool_calls[] = [
                        'id'       => $tu['tool_use_id'],
                        'type'     => 'function',
                        'function' => [
                            'name'      => $tu['name'],
                            'arguments' => wp_json_encode( $tu['input'] ),
                        ],
                    ];
                    $j++;
                }

                $entry = [ 'role' => 'assistant', 'content' => $msg['content'] ?: null ];
                if ( ! empty( $tool_calls ) ) {
                    $entry['tool_calls'] = $tool_calls;
                }
                $out[] = $entry;

                // Now emit tool results
                while ( $j < $len && $messages[ $j ]['role'] === 'tool_result' ) {
                    $tr    = $messages[ $j ];
                    $out[] = [
                        'role'         => 'tool',
                        'tool_call_id' => $tr['tool_use_id'],
                        'content'      => is_string( $tr['content'] ) ? $tr['content'] : wp_json_encode( $tr['content'] ),
                    ];
                    $j++;
                }

                $i = $j;
                continue;
            }

            // user or other roles — pass through as-is
            $out[] = [ 'role' => $msg['role'], 'content' => $msg['content'] ];
            $i++;
        }

        return $out;
    }

    /**
     * Convert Anthropic tool definitions to OpenAI function format.
     *
     * Anthropic: {name, description, input_schema:{type,properties,required}}
     * OpenAI:    {type:'function', function:{name, description, parameters:{...}}}
     */
    private function to_openai_tools( array $tools ): array {
        return array_map( static fn( $t ) => [
            'type'     => 'function',
            'function' => [
                'name'        => $t['name'],
                'description' => $t['description'] ?? '',
                'parameters'  => $t['input_schema'] ?? [ 'type' => 'object', 'properties' => [] ],
            ],
        ], $tools );
    }
}
