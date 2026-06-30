<?php

namespace Replanta\AiChat\Retrieval;

use Replanta\AiChat\Indexing\Embedder;
use Replanta\AiChat\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Combines vector search (semantic) + keyword search (exact/SKU) via
 * Reciprocal Rank Fusion, then enriches each result with live WC product data.
 */
class HybridRetriever {

    private VectorSearch  $vector;
    private KeywordSearch $keyword;
    private Embedder      $embedder;

    public function __construct() {
        $this->vector   = new VectorSearch();
        $this->keyword  = new KeywordSearch();
        $this->embedder = new Embedder();
    }

    /**
     * Main retrieval method. Returns an array of enriched product contexts
     * ready to be injected into the LLM prompt.
     */
    public function retrieve( string $user_query ): array {
        $opts    = Options::get_behaviour();
        $top_k   = (int) ( $opts['max_context_products'] ?? 5 );

        // Embed the query
        try {
            $query_vector = $this->embedder->embed( $user_query );
        } catch ( \Throwable $e ) {
            $query_vector = null;
        }

        $vector_results  = $query_vector ? $this->vector->search( $query_vector, $top_k * 2 ) : [];
        $keyword_results = $this->keyword->search( $user_query, $top_k * 2 );

        $merged = $this->reciprocal_rank_fusion( $vector_results, $keyword_results );
        $merged = array_slice( $merged, 0, $top_k );

        // Enrich with live WC data
        return array_filter( array_map( [ $this, 'enrich' ], $merged ) );
    }

    // ── RRF ───────────────────────────────────────────────────────────────────

    private function reciprocal_rank_fusion( array $list_a, array $list_b, int $k = 60 ): array {
        $scores = [];

        foreach ( $list_a as $rank => $item ) {
            $pid             = $item['product_id'];
            $scores[ $pid ]  = ( $scores[ $pid ] ?? 0 ) + 1 / ( $k + $rank + 1 );
        }
        foreach ( $list_b as $rank => $item ) {
            $pid             = $item['product_id'];
            $scores[ $pid ]  = ( $scores[ $pid ] ?? 0 ) + 1 / ( $k + $rank + 1 );
        }

        arsort( $scores );

        // Build merged results preserving metadata from vector results when available
        $vector_map  = array_column( $list_a, null, 'product_id' );
        $keyword_map = array_column( $list_b, null, 'product_id' );
        $result      = [];

        foreach ( array_keys( $scores ) as $pid ) {
            $base         = $vector_map[ $pid ] ?? $keyword_map[ $pid ] ?? [ 'product_id' => $pid ];
            $base['rrf_score'] = $scores[ $pid ];
            $result[]     = $base;
        }

        return $result;
    }

    // ── Enrichment ────────────────────────────────────────────────────────────

    private function enrich( array $item ): ?array {
        $product = wc_get_product( $item['product_id'] );
        if ( ! $product || $product->get_status() !== 'publish' ) {
            return null;
        }

        // Gather all indexed chunks for this product to give full context
        $chunks = $this->get_product_chunks( $item['product_id'] );

        return [
            'id'          => $product->get_id(),
            'name'        => $product->get_name(),
            'sku'         => $product->get_sku(),
            'url'         => get_permalink( $product->get_id() ),
            'price'       => $product->get_price(),
            'price_html'  => $product->get_price_html(),
            'in_stock'    => $product->is_in_stock(),
            'stock_qty'   => $product->get_stock_quantity(),
            'image_url'   => wp_get_attachment_url( $product->get_image_id() ) ?: '',
            'type'        => $product->get_type(),
            'variations'  => $this->get_variations( $product ),
            'chunks'      => $chunks,
            'rrf_score'   => $item['rrf_score'] ?? 0,
        ];
    }

    private function get_product_chunks( int $product_id ): array {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT chunk_type, chunk_key, content FROM {$wpdb->prefix}replanta_embeddings WHERE product_id = %d",
            $product_id
        ), ARRAY_A );

        return $rows ?: [];
    }

    private function get_variations( \WC_Product $product ): array {
        if ( ! $product->is_type( 'variable' ) ) {
            return [];
        }

        /** @var \WC_Product_Variable $product */
        $variation_ids = $product->get_children();
        $result        = [];

        foreach ( array_slice( $variation_ids, 0, 10 ) as $var_id ) {
            $var = wc_get_product( $var_id );
            if ( ! $var || ! $var->is_visible() ) {
                continue;
            }
            $result[] = [
                'id'         => $var->get_id(),
                'sku'        => $var->get_sku(),
                'price'      => $var->get_price(),
                'in_stock'   => $var->is_in_stock(),
                'attributes' => $var->get_variation_attributes(),
            ];
        }

        return $result;
    }
}
