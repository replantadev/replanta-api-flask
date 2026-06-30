<?php

namespace Replanta\AiChat\Llm;

defined( 'ABSPATH' ) || exit;

class LlmResponse {

    public function __construct(
        public readonly string  $text,
        public readonly array   $tool_calls   = [],  // [ ['id'=>..., 'name'=>..., 'input'=>[...]] ]
        public readonly ?string $stop_reason  = null,
        public readonly int     $input_tokens = 0,
        public readonly int     $output_tokens= 0,
    ) {}

    public function has_tool_calls(): bool {
        return ! empty( $this->tool_calls );
    }
}
