<?php

namespace Replanta\AiChat\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Prepares a multi-item order and returns a checkout URL pre-filled with products.
 */
class OrderTool {

    public function definition(): array {
        return [
            'name'        => 'prepare_order',
            'description' => 'Prepara un pedido con uno o varios productos y devuelve un enlace directo al checkout con los productos ya añadidos. Úsalo cuando el cliente quiera comprar varios productos a la vez.',
            'input_schema'=> [
                'type'       => 'object',
                'properties' => [
                    'items' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'product_id'   => [ 'type' => 'integer' ],
                                'variation_id' => [ 'type' => 'integer' ],
                                'quantity'     => [ 'type' => 'integer', 'default' => 1 ],
                            ],
                            'required'   => [ 'product_id' ],
                        ],
                        'description' => 'Lista de productos a incluir en el pedido.',
                    ],
                ],
                'required'   => [ 'items' ],
            ],
        ];
    }

    public function execute( array $input ): array {
        $items = $input['items'] ?? [];
        if ( empty( $items ) ) {
            return [ 'error' => 'La lista de productos está vacía.' ];
        }

        // Return action payload for frontend — frontend handles cart filling
        $summary = [];
        $total   = 0.0;

        foreach ( $items as $item ) {
            $product_id   = (int) ( $item['product_id'] ?? 0 );
            $variation_id = (int) ( $item['variation_id'] ?? 0 );
            $quantity     = max( 1, (int) ( $item['quantity'] ?? 1 ) );

            $product = wc_get_product( $variation_id ?: $product_id );
            if ( ! $product ) {
                continue;
            }

            $price   = (float) $product->get_price();
            $total  += $price * $quantity;
            $summary[] = [
                'product_id'   => $product_id,
                'variation_id' => $variation_id ?: null,
                'quantity'     => $quantity,
                'name'         => wc_get_product( $product_id )->get_name(),
                'price'        => $price,
            ];
        }

        return [
            'success'      => true,
            'action'       => 'prepare_order',
            'items'        => $summary,
            'total_approx' => wc_price( $total ),
            'checkout_url' => wc_get_checkout_url(),
            'cart_url'     => wc_get_cart_url(),
            'message'      => sprintf(
                __( 'He preparado tu pedido con %d producto(s). Total estimado: %s', 'replanta-ai-chat' ),
                count( $summary ),
                wc_price( $total )
            ),
        ];
    }
}
