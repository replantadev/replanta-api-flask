<?php

namespace Replanta\AiChat\Retrieval;

use Replanta\AiChat\Indexing\Embedder;

defined( 'ABSPATH' ) || exit;

/**
 * Semantic search over stored embeddings using cosine similarity in PHP.
 * Efficient for catalogs up to ~20k products; no external vector DB needed.
 */
class VectorSearch {

    const CACHE_KEY     = 'replanta_embeddings_cache';
    const CACHE_TTL     = 10 * MINUTE_IN_SECONDS;
    const MIN_SCORE     = 0.35;

    /**
     * Returns [ [ 'product_id' => int, 'score' => float, 'chunk_type' => string, 'content' => string ] ]
     * sorted by score descending.
     */
    public function search( array $query_vector, int $top_k = 5 ): array {
        $embeddings = $this->load_embeddings();
        if ( empty( $embeddings ) ) {
            return [];
        }

        $scores = [];
        foreach ( $embeddings as $row ) {
            $vec   = Embedder::deserialize( $row['embedding'] );
            $score = Embedder::cosine_similarity( $query_vector, $vec );

            if ( $score < self::MIN_SCORE ) {
                continue;
            }

            $pid = (int) $row['product_id'];
            // Keep the best chunk per product
            if ( ! isset( $scores[ $pid ] ) || $score > $scores[ $pid ]['score'] ) {
                $scores[ $pid ] = [
                    'product_id' => $pid,
                    'score'      => $score,
                    'chunk_type' => $row['chunk_type'],
                    'content'    => $row['content'],
                ];
            }
        }

        usort( $scores, static fn( $a, $b ) => $b['score'] <=> $a['score'] );

        return array_values( array_slice( $scores, 0, $top_k ) );
    }

    /**
     * Flush the embeddings cache (call after re-indexing).
     */
    public static function flush_cache(): void {
        delete_transient( self::CACHE_KEY );
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    private function load_embeddings(): array {
        $cached = get_transient( self::CACHE_KEY );
        if ( false !== $cached ) {
            return $cached;
        }

        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT product_id, chunk_type, content, embedding
             FROM {$wpdb->prefix}replanta_embeddings
             WHERE embedding IS NOT NULL",
            ARRAY_A
        );

        $rows = $rows ?: [];
        set_transient( self::CACHE_KEY, $rows, self::CACHE_TTL );
        return $rows;
    }
}
