<?php

namespace Replanta\AiChat\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a product (or variation) to the WooCommerce cart of the current session.
 */
class CartTool {

    public function definition(): array {
        return [
            'name'        => 'add_to_cart',
            'description' => 'Añade un producto al carrito del cliente. Requiere el ID del producto y cantidad. Para productos variables requiere también el ID de variación.',
            'input_schema'=> [
                'type'       => 'object',
                'properties' => [
                    'product_id' => [
                        'type'        => 'integer',
                        'description' => 'ID de WooCommerce del producto.',
                    ],
                    'quantity' => [
                        'type'        => 'integer',
                        'description' => 'Cantidad a añadir. Por defecto 1.',
                        'default'     => 1,
                    ],
                    'variation_id' => [
                        'type'        => 'integer',
                        'description' => 'ID de la variación (solo para productos variables).',
                    ],
                    'variation_attributes' => [
                        'type'        => 'object',
                        'description' => 'Atributos de la variación como clave-valor (ej: {"attribute_pa_talla": "M"}).',
                    ],
                ],
                'required'   => [ 'product_id' ],
            ],
        ];
    }

    public function execute( array $input ): array {
        // Cart manipulation must happen in a frontend (non-REST) context.
        // We return the data needed for the frontend widget to add to cart via AJAX.
        $product_id   = (int) ( $input['product_id'] ?? 0 );
        $quantity     = max( 1, (int) ( $input['quantity'] ?? 1 ) );
        $variation_id = (int) ( $input['variation_id'] ?? 0 );
        $variation    = (array) ( $input['variation_attributes'] ?? [] );

        if ( ! $product_id ) {
            return [ 'error' => 'product_id es obligatorio.' ];
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return [ 'error' => 'Producto no encontrado.' ];
        }

        if ( ! $product->is_in_stock() ) {
            return [
                'success' => false,
                'message' => 'Lo siento, este producto está agotado en este momento.',
            ];
        }

        // For REST API context: return action payload for frontend to execute
        return [
            'success'      => true,
            'action'       => 'add_to_cart',
            'product_id'   => $product_id,
            'variation_id' => $variation_id ?: null,
            'quantity'     => $quantity,
            'variation'    => $variation ?: null,
            'product_name' => $product->get_name(),
            'price_html'   => $product->get_price_html(),
            'cart_url'     => wc_get_cart_url(),
            'checkout_url' => wc_get_checkout_url(),
            'message'      => sprintf(
                __( '"%s" añadido al carrito.', 'replanta-ai-chat' ),
                $product->get_name()
            ),
        ];
    }
}
