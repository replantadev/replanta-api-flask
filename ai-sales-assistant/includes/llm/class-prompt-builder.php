<?php

namespace Replanta\AiChat\Llm;

use Replanta\AiChat\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the system prompt and user message context for each chat turn.
 * Grounding rules are built into the system prompt to prevent hallucination.
 */
class PromptBuilder {

    public function build_system_prompt(): string {
        $general  = Options::get_general();
        $behaviour= Options::get_behaviour();
        $tools    = Options::get_tools();

        $name     = $general['assistant_name'] ?? 'Asistente';
        $language = $behaviour['language'] ?? 'es';
        $fallback = $behaviour['fallback_message'] ?? 'No tengo esa información.';
        $extra    = trim( $behaviour['system_prompt_extra'] ?? '' );

        $lang_map = [
            'es' => 'español',
            'en' => 'English',
            'ca' => 'català',
            'fr' => 'français',
            'pt' => 'português',
        ];
        $lang_name = $lang_map[ $language ] ?? 'español';

        $parts = [];

        // Identity
        $parts[] = "Eres {$name}, un asistente de ventas especializado en ecommerce. Respondes en {$lang_name}.";

        // Grounding rules - THE CORE antihalucination rules
        $parts[] = <<<GROUNDING
## Reglas de grounding (OBLIGATORIAS)

1. **Solo uses información del CONTEXTO DE PRODUCTOS** que se te proporciona en cada mensaje. NUNCA inventes, extrapoles ni completes información que no está explícitamente en el contexto.
2. **Si no sabes algo**, responde exactamente: "{$fallback}". No inventes respuestas plausibles.
3. **Precios y stock**: siempre usa los datos en tiempo real del contexto, nunca valores que recuerdes de turnos anteriores.
4. **SKUs e IDs**: cuando cites un producto, incluye su SKU o ID para que el cliente pueda localizarlo.
5. **Variaciones**: si un producto tiene variaciones, pregunta al cliente qué variación quiere antes de añadir al carrito.
6. **No hagas promesas** de envío, devolución o precio que no estén en el contexto.
GROUNDING;

        // Cosmetic/regulatory guardrail
        $parts[] = <<<COSMETIC
## Lenguaje regulatorio cosmética (UE)

- Nunca uses términos de eficacia médica: "cura", "trata", "elimina", "remedia", "medicamento", "prescripción".
- Usa lenguaje cosmético correcto: "formulado para", "ayuda a", "contribuye a", "pieles con tendencia a", "apariencia de".
- No hagas claims médicos o farmacéuticos.
COSMETIC;

        // Tool usage instructions
        $tool_instructions = [];
        if ( ! empty( $tools['cart_enabled'] ) ) {
            $tool_instructions[] = '- Cuando respondas sobre un producto concreto que esté **en stock**, SIEMPRE termina tu respuesta ofreciendo añadirlo al carrito con una frase natural como "¿Quieres que lo añada al carrito?" o "¿Te lo añado?". No esperes a que el cliente lo solicite primero.';
            $tool_instructions[] = '- Usa `add_to_cart` en cuanto el cliente confirme que sí quiere comprar (respuesta afirmativa: "sí", "añádelo", "dale", "perfecto", etc.). Si el producto tiene variaciones, pregunta cuál quiere antes de añadirlo.';
        }
        if ( ! empty( $tools['order_enabled'] ) ) {
            $tool_instructions[] = '- Usa `prepare_order` para preparar un pedido con varios productos y dar un link directo a checkout.';
        }
        if ( ! empty( $tools['search_enabled'] ) ) {
            $tool_instructions[] = '- Usa `search_products` si el cliente busca algo que no está en el contexto actual.';
        }
        if ( ! empty( $tools['escalation_enabled'] ) ) {
            $tool_instructions[] = '- Usa `escalate_to_human` cuando: el cliente esté insatisfecho, la pregunta supere tu alcance, o el cliente lo pida explícitamente.';
        }

        if ( ! empty( $tool_instructions ) ) {
            $parts[] = "## Uso de herramientas\n\n" . implode( "\n", $tool_instructions );
        }

        // Tone
        $parts[] = <<<TONE
## Tono y formato

- Sé conciso, amigable y profesional.
- Usa bullet points para listar características o comparar productos.
- Si hay más de un producto relevante, compáralos brevemente.
- Respuestas máximo 3-4 párrafos cortos.
TONE;

        // Extra instructions from admin
        if ( $extra ) {
            $parts[] = "## Instrucciones adicionales del administrador\n\n{$extra}";
        }

        return implode( "\n\n", $parts );
    }

    /**
     * Build the user message that includes retrieved product context.
     */
    public function build_context_message( string $user_query, array $products ): string {
        if ( empty( $products ) ) {
            return $user_query;
        }

        $context_parts = [];
        foreach ( $products as $product ) {
            $context_parts[] = $this->format_product_context( $product );
        }

        $context = implode( "\n\n---\n\n", $context_parts );

        return <<<MSG
[CONTEXTO DE PRODUCTOS — usa SOLO esta información para responder]

{$context}

[FIN DEL CONTEXTO]

Pregunta del cliente: {$user_query}
MSG;
    }

    private function format_product_context( array $product ): string {
        $lines = [];

        $lines[] = "**Producto: {$product['name']}** (ID: {$product['id']}, SKU: {$product['sku']})";
        $lines[] = "URL: {$product['url']}";
        $lines[] = "Precio: {$product['price_html']}";
        $lines[] = "Stock: " . ( $product['in_stock'] ? 'Disponible' : 'Sin stock' );

        if ( ! empty( $product['variations'] ) ) {
            $lines[] = "Variaciones disponibles:";
            foreach ( $product['variations'] as $var ) {
                $attrs = implode( ', ', array_map(
                    static fn( $k, $v ) => "{$k}: {$v}",
                    array_keys( $var['attributes'] ),
                    array_values( $var['attributes'] )
                ) );
                $stock = $var['in_stock'] ? 'en stock' : 'sin stock';
                $lines[] = "  - ID {$var['id']} (SKU: {$var['sku']}) | {$attrs} | {$var['price']}€ | {$stock}";
            }
        }

        // Indexed chunks (description, ingredients, attributes, ACF...)
        foreach ( $product['chunks'] ?? [] as $chunk ) {
            $lines[] = "\n" . $chunk['content'];
        }

        return implode( "\n", $lines );
    }
}
