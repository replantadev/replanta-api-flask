<?php

namespace Replanta\AiChat\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Returns live product data (price, stock) directly from WooCommerce.
 * The LLM calls this to get authoritative, non-hallucinated price/stock.
 */
class ProductDetailTool {

    public function definition(): array {
        return [
            'name'        => 'get_product',
            'description' => 'Obtiene precio actual, stock y datos en tiempo real de un producto específico por ID o SKU. Usa esta herramienta para confirmar precio o disponibilidad antes de dar información al cliente.',
            'input_schema'=> [
                'type'       => 'object',
                'properties' => [
                    'product_id' => [
                        'type'        => 'integer',
                        'description' => 'ID de WooCommerce del producto.',
                    ],
                    'sku' => [
                        'type'        => 'string',
                        'description' => 'SKU del producto (alternativa al ID).',
                    ],
                ],
            ],
        ];
    }

    public function execute( array $input ): array {
        $product = null;

        if ( ! empty( $input['product_id'] ) ) {
            $product = wc_get_product( (int) $input['product_id'] );
        } elseif ( ! empty( $input['sku'] ) ) {
            $id      = wc_get_product_id_by_sku( sanitize_text_field( $input['sku'] ) );
            $product = $id ? wc_get_product( $id ) : null;
        }

        if ( ! $product ) {
            return [ 'error' => 'Producto no encontrado.' ];
        }

        $data = [
            'id'         => $product->get_id(),
            'name'       => $product->get_name(),
            'sku'        => $product->get_sku(),
            'price'      => $product->get_price(),
            'price_html' => $product->get_price_html(),
            'sale_price' => $product->get_sale_price(),
            'in_stock'   => $product->is_in_stock(),
            'stock_qty'  => $product->get_stock_quantity(),
            'url'        => get_permalink( $product->get_id() ),
            'type'       => $product->get_type(),
        ];

        if ( $product->is_type( 'variable' ) ) {
            /** @var \WC_Product_Variable $product */
            $variations = [];
            foreach ( $product->get_children() as $var_id ) {
                $var = wc_get_product( $var_id );
                if ( ! $var ) {
                    continue;
                }
                $variations[] = [
                    'id'         => $var->get_id(),
                    'sku'        => $var->get_sku(),
                    'price'      => $var->get_price(),
                    'in_stock'   => $var->is_in_stock(),
                    'attributes' => $var->get_variation_attributes(),
                ];
            }
            $data['variations'] = $variations;
        }

        return $data;
    }
}
