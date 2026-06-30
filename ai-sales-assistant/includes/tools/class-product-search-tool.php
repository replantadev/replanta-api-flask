<?php

namespace Replanta\AiChat\Tools;

use Replanta\AiChat\Retrieval\HybridRetriever;

defined( 'ABSPATH' ) || exit;

class ProductSearchTool {

    public function definition(): array {
        return [
            'name'        => 'search_products',
            'description' => 'Busca productos en el catálogo por consulta en lenguaje natural, nombre, SKU o características. Usa esta herramienta cuando el cliente busque un producto que no está en el contexto actual.',
            'input_schema'=> [
                'type'       => 'object',
                'properties' => [
                    'query' => [
                        'type'        => 'string',
                        'description' => 'Consulta de búsqueda. Puede ser nombre de producto, SKU, ingrediente, característica, uso, tipo de piel, etc.',
                    ],
                ],
                'required'   => [ 'query' ],
            ],
        ];
    }

    public function execute( array $input ): array {
        $query     = sanitize_text_field( $input['query'] ?? '' );
        $retriever = new HybridRetriever();
        $products  = $retriever->retrieve( $query );

        if ( empty( $products ) ) {
            return [ 'found' => false, 'message' => 'No se encontraron productos para esa búsqueda.' ];
        }

        return [
            'found'    => true,
            'products' => array_map( static fn( $p ) => [
                'id'       => $p['id'],
                'name'     => $p['name'],
                'sku'      => $p['sku'],
                'price'    => $p['price_html'],
                'in_stock' => $p['in_stock'],
                'url'      => $p['url'],
            ], $products ),
        ];
    }
}
