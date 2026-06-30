<?php

namespace Replanta\AiChat\Chat;

use Replanta\AiChat\Llm\LlmProvider;
use Replanta\AiChat\Llm\AnthropicProvider;
use Replanta\AiChat\Llm\OpenAiProvider;
use Replanta\AiChat\Llm\PromptBuilder;
use Replanta\AiChat\Llm\Guardrails;
use Replanta\AiChat\Retrieval\HybridRetriever;
use Replanta\AiChat\Tools\ToolRegistry;
use Replanta\AiChat\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Main chat orchestrator.
 *
 * Flow per turn:
 *   1. Retrieve relevant products (RAG)
 *   2. Build messages with product context
 *   3. Call LLM → if tool_call → execute → append result → repeat (max 5 loops)
 *   4. Apply guardrails
 *   5. Persist to DB
 *   6. Return final response
 */
class ChatService {

    const MAX_TOOL_LOOPS = 5;

    private LlmProvider $llm;
    private PromptBuilder     $prompt_builder;
    private Guardrails        $guardrails;
    private HybridRetriever   $retriever;
    private ToolRegistry      $tools;
    private SessionManager    $session_mgr;

    public function __construct() {
        $provider             = Options::get_provider()['llm_provider'] ?? 'anthropic';
        $this->llm            = 'openai' === $provider ? new OpenAiProvider() : new AnthropicProvider();
        $this->prompt_builder = new PromptBuilder();
        $this->guardrails     = new Guardrails();
        $this->retriever      = new HybridRetriever();
        $this->tools          = new ToolRegistry();
        $this->session_mgr    = new SessionManager();
    }

    /**
     * Process a user message and return the assistant response.
     *
     * @return array {
     *   text: string,
     *   tool_actions: array,  // frontend actions (add_to_cart, prepare_order, escalate)
     *   products: array,      // retrieved product context (for UI)
     *   conversation_id: int,
     *   flagged: bool,
     * }
     */
    public function handle( string $user_message, string $session_id ): array {
        // 1. Retrieve relevant products
        $products = $this->retriever->retrieve( $user_message );

        // 2. Get conversation history
        $history = $this->session_mgr->get_history( $session_id );

        // 3. Build the contextualised user message
        $context_message = $this->prompt_builder->build_context_message( $user_message, $products );
        $system          = $this->prompt_builder->build_system_prompt();

        // 4. Build messages array (history + new turn)
        $messages   = $history;
        $messages[] = [ 'role' => 'user', 'content' => $context_message ];

        // 5. Tool calling loop
        $tool_actions  = [];
        $total_tokens  = 0;

        for ( $loop = 0; $loop < self::MAX_TOOL_LOOPS; $loop++ ) {
            $response      = $this->llm->chat( $messages, $this->tools->get_definitions(), $system );
            $total_tokens += $response->input_tokens + $response->output_tokens;

            if ( ! $response->has_tool_calls() ) {
                // Final text response — break out
                break;
            }

            // Process tool calls
            $messages[] = [
                'role'    => 'assistant',
                'content' => $response->text ?: '',
            ];

            foreach ( $response->tool_calls as $tool_call ) {
                $result = $this->tools->execute( $tool_call['name'], $tool_call['input'] );

                // Collect frontend actions
                $result_array = json_decode( $result, true );
                if ( is_array( $result_array ) && ! empty( $result_array['action'] ) ) {
                    $tool_actions[] = $result_array;
                }

                // Tool use block in assistant turn
                $messages[] = [
                    'role'        => 'tool_use',
                    'tool_use_id' => $tool_call['id'],
                    'name'        => $tool_call['name'],
                    'input'       => $tool_call['input'],
                ];

                // Tool result in user turn
                $messages[] = [
                    'role'        => 'tool_result',
                    'tool_use_id' => $tool_call['id'],
                    'content'     => $result,
                ];
            }
        }

        // 6. Apply guardrails
        $validated = $this->guardrails->validate( $response->text ?? '' );

        // 7. Persist conversation
        $conversation_id = $this->persist(
            session_id:   $session_id,
            user_message: $user_message,
            assistant_msg:$validated['text'],
            products:     $products,
            tokens:       $total_tokens,
            tool_calls:   $response->tool_calls ?? [],
        );

        // 8. Update session history (store raw user message, not context-enhanced)
        $this->session_mgr->append_message( $session_id, 'user',      $user_message );
        $this->session_mgr->append_message( $session_id, 'assistant', $validated['text'] );

        return [
            'text'            => $validated['text'],
            'tool_actions'    => $tool_actions,
            'products'        => array_map( static fn( $p ) => [
                'id'       => $p['id'],
                'name'     => $p['name'],
                'url'      => $p['url'],
                'image'    => $p['image_url'],
                'price'    => $p['price_html'],
                'in_stock' => $p['in_stock'],
            ], $products ),
            'conversation_id' => $conversation_id,
            'flagged'         => $validated['flagged'],
        ];
    }

    // ── Persistence ───────────────────────────────────────────────────────────

    private function persist(
        string $session_id,
        string $user_message,
        string $assistant_msg,
        array  $products,
        int    $tokens,
        array  $tool_calls
    ): int {
        global $wpdb;

        // Get or create conversation record
        $conv_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}replanta_conversations WHERE session_id = %s ORDER BY started_at DESC LIMIT 1",
            $session_id
        ) );

        if ( ! $conv_id ) {
            $wpdb->insert( $wpdb->prefix . 'replanta_conversations', [
                'session_id' => $session_id,
                'user_id'    => get_current_user_id() ?: null,
                'started_at' => current_time( 'mysql' ),
            ] );
            $conv_id = (int) $wpdb->insert_id;
        }

        $product_ids = wp_json_encode( array_column( $products, 'id' ) );

        // User message
        $wpdb->insert( $wpdb->prefix . 'replanta_messages', [
            'conversation_id' => $conv_id,
            'role'            => 'user',
            'content'         => $user_message,
            'created_at'      => current_time( 'mysql' ),
        ] );

        // Assistant message
        $wpdb->insert( $wpdb->prefix . 'replanta_messages', [
            'conversation_id' => $conv_id,
            'role'            => 'assistant',
            'content'         => $assistant_msg,
            'tool_calls'      => ! empty( $tool_calls ) ? wp_json_encode( $tool_calls ) : null,
            'products_cited'  => $product_ids,
            'tokens_used'     => $tokens,
            'created_at'      => current_time( 'mysql' ),
        ] );

        // Update conversation message count
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->prefix}replanta_conversations SET total_messages = total_messages + 2, product_ids = %s WHERE id = %d",
            $product_ids,
            $conv_id
        ) );

        return (int) $conv_id;
    }
}
