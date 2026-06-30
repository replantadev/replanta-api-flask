<?php

namespace Replanta\AiChat\Tools;

use Replanta\AiChat\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Registers available tools and executes tool calls from the LLM.
 * Tools are enabled/disabled via admin settings.
 */
class ToolRegistry {

    private array $tools = [];

    public function __construct() {
        $opts = Options::get_tools();

        if ( ! empty( $opts['search_enabled'] ) ) {
            $this->tools['search_products'] = new ProductSearchTool();
        }

        $this->tools['get_product'] = new ProductDetailTool();

        if ( ! empty( $opts['cart_enabled'] ) ) {
            $this->tools['add_to_cart'] = new CartTool();
        }

        if ( ! empty( $opts['order_enabled'] ) ) {
            $this->tools['prepare_order'] = new OrderTool();
        }

        if ( ! empty( $opts['escalation_enabled'] ) ) {
            $this->tools['escalate_to_human'] = new EscalationTool();
        }
    }

    /**
     * Returns tool definitions in Anthropic format.
     */
    public function get_definitions(): array {
        return array_values( array_map(
            static fn( $tool ) => $tool->definition(),
            $this->tools
        ) );
    }

    /**
     * Execute a tool call by name.
     * Returns the tool result as a string (serialized JSON or plain text).
     */
    public function execute( string $name, array $input ): string {
        if ( ! isset( $this->tools[ $name ] ) ) {
            return json_encode( [ 'error' => "Unknown tool: {$name}" ] );
        }

        try {
            $result = $this->tools[ $name ]->execute( $input );
            return is_string( $result ) ? $result : wp_json_encode( $result );
        } catch ( \Throwable $e ) {
            return wp_json_encode( [ 'error' => $e->getMessage() ] );
        }
    }
}
