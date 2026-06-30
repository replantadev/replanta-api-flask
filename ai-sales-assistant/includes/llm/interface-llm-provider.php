<?php

namespace Replanta\AiChat\Llm;

defined( 'ABSPATH' ) || exit;

interface LlmProvider {

    /**
     * Send a chat conversation and return the assistant's response.
     *
     * @param array  $messages  Array of [ 'role' => 'user'|'assistant'|'tool', 'content' => ... ]
     * @param array  $tools     Tool definitions in provider format
     * @param string $system    System prompt
     * @return LlmResponse
     */
    public function chat( array $messages, array $tools, string $system ): LlmResponse;

    /**
     * Same as chat() but streams via SSE. Calls $chunk_cb for each text chunk.
     * When the model wants to call a tool, returns a LlmResponse with tool_calls set.
     * Streaming stops at the first tool call so the caller can execute and continue.
     *
     * @param callable $chunk_cb  fn(string $text): void — called with each streamed text chunk
     */
    public function stream( array $messages, array $tools, string $system, callable $chunk_cb ): LlmResponse;
}
