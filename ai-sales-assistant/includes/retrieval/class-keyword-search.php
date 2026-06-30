<?php

namespace Replanta\AiChat\Retrieval;

defined( 'ABSPATH' ) || exit;

/**
 * Exact/SKU keyword search via WooCommerce and WP_Query.
 * Catches queries like "ref 12345" or exact product names that semantic search might miss.
 */
class KeywordSearch {

    /**
     * Returns [ [ 'product_id' => int, 'score' => float, 'chunk_type' => string, 'content' => string ] ]
     */
    public function search( string $query, int $top_k = 5 ): array {
        $results = [];

        // Try SKU match first (exact + partial)
        $sku_ids = $this->search_by_sku( $query );
        foreach ( $sku_ids as $id ) {
            $results[ $id ] = [ 'product_id' => $id, 'score' => 0.9, 'chunk_type' => 'sku_match', 'content' => '' ];
        }

        // WooCommerce search
        $wc_ids = $this->wc_search( $query, $top_k );
        foreach ( $wc_ids as $id ) {
            if ( ! isset( $results[ $id ] ) ) {
                $results[ $id ] = [ 'product_id' => $id, 'score' => 0.6, 'chunk_type' => 'keyword_match', 'content' => '' ];
            }
        }

        return array_values( array_slice( $results, 0, $top_k ) );
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    private function search_by_sku( string $query ): array {
        // Extract potential SKU from query (alphanumeric sequences)
        preg_match_all( '/[A-Za-z0-9\-]{3,}/', $query, $matches );
        $candidates = $matches[0] ?? [];

        $ids = [];
        foreach ( $candidates as $candidate ) {
            $product_id = wc_get_product_id_by_sku( $candidate );
            if ( $product_id ) {
                $ids[] = $product_id;
            }
        }

        return array_unique( $ids );
    }

    private function wc_search( string $query, int $limit ): array {
        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'fields'         => 'ids',
            's'              => $query,
        ];

        $query_obj = new \WP_Query( $args );
        return $query_obj->posts;
    }
}
