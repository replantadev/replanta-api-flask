<?php

namespace Replanta\AiChat\Indexing;

defined( 'ABSPATH' ) || exit;

/**
 * Orchestrates full and incremental indexing jobs.
 */
class Indexer {

    private ProductExtractor $extractor;
    private Embedder          $embedder;

    public function __construct() {
        $this->extractor = new ProductExtractor();
        $this->embedder  = new Embedder();
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Index a single product. Used by auto-index on save.
     */
    public function index_product( int $product_id ): void {
        $chunks = $this->extractor->extract( $product_id );
        if ( empty( $chunks ) ) {
            $this->delete_product_embeddings( $product_id );
            return;
        }

        // Compute embeddings for changed chunks only
        $existing = $this->get_existing_hashes( $product_id );
        $to_embed = [];
        $to_skip  = [];

        foreach ( $chunks as $chunk ) {
            $hash = md5( $chunk['content'] );
            $ck   = $chunk['chunk_type'] . '|' . ( $chunk['chunk_key'] ?? '' );

            if ( isset( $existing[ $ck ] ) && $existing[ $ck ] === $hash ) {
                $to_skip[] = $ck;
            } else {
                $to_embed[] = array_merge( $chunk, [ 'hash' => $hash ] );
            }
        }

        if ( empty( $to_embed ) ) {
            return; // All chunks unchanged
        }

        $texts     = array_column( $to_embed, 'content' );
        $vectors   = $this->embedder->embed_batch( $texts );

        foreach ( $to_embed as $i => $chunk ) {
            $vector = $vectors[ $i ] ?? null;
            $this->upsert_chunk( $product_id, $chunk, $vector );
        }

        // Remove chunks that no longer exist
        $this->cleanup_stale_chunks( $product_id, $chunks );
    }

    /**
     * Full reindex. Creates a job record and processes in batches.
     * Should be called from WP-CLI or via REST endpoint (admin).
     */
    public function full_reindex( callable $progress_cb = null ): void {
        global $wpdb;

        $job_id = $this->create_job( 'full' );

        $product_ids = $this->get_all_product_ids();
        $total       = count( $product_ids );

        $wpdb->update(
            $wpdb->prefix . 'replanta_indexing_jobs',
            [ 'status' => 'running', 'total' => $total, 'started_at' => current_time( 'mysql' ) ],
            [ 'id' => $job_id ]
        );

        $processed = 0;
        $failed    = 0;
        $log       = [];

        foreach ( $product_ids as $product_id ) {
            try {
                $this->index_product( $product_id );
                $processed++;
                $log[] = "[OK] Product {$product_id}";
            } catch ( \Throwable $e ) {
                $failed++;
                $log[] = "[ERROR] Product {$product_id}: " . $e->getMessage();
            }

            if ( $progress_cb ) {
                $progress_cb( $processed, $total, $product_id );
            }

            // Update job progress every 10 products
            if ( 0 === $processed % 10 ) {
                $wpdb->update(
                    $wpdb->prefix . 'replanta_indexing_jobs',
                    [ 'processed' => $processed, 'failed' => $failed, 'log' => implode( "\n", array_slice( $log, -50 ) ) ],
                    [ 'id' => $job_id ]
                );
            }
        }

        $wpdb->update(
            $wpdb->prefix . 'replanta_indexing_jobs',
            [
                'status'       => 'completed',
                'processed'    => $processed,
                'failed'       => $failed,
                'log'          => implode( "\n", $log ),
                'completed_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $job_id ]
        );
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    private function upsert_chunk( int $product_id, array $chunk, ?array $vector ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'replanta_embeddings';

        $blob = $vector ? Embedder::serialize( $vector ) : null;

        $existing_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $table WHERE product_id = %d AND chunk_type = %s AND (chunk_key = %s OR (chunk_key IS NULL AND %s IS NULL))",
            $product_id,
            $chunk['chunk_type'],
            $chunk['chunk_key'],
            $chunk['chunk_key']
        ) );

        $data = [
            'product_id'   => $product_id,
            'chunk_type'   => $chunk['chunk_type'],
            'chunk_key'    => $chunk['chunk_key'],
            'content'      => $chunk['content'],
            'embedding'    => $blob,
            'content_hash' => $chunk['hash'] ?? md5( $chunk['content'] ),
        ];

        if ( $existing_id ) {
            $wpdb->update( $table, $data, [ 'id' => $existing_id ] );
        } else {
            $wpdb->insert( $table, $data );
        }
    }

    private function get_existing_hashes( int $product_id ): array {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT chunk_type, chunk_key, content_hash FROM {$wpdb->prefix}replanta_embeddings WHERE product_id = %d",
            $product_id
        ) );

        $map = [];
        foreach ( $rows as $row ) {
            $k       = $row->chunk_type . '|' . ( $row->chunk_key ?? '' );
            $map[$k] = $row->content_hash;
        }
        return $map;
    }

    private function cleanup_stale_chunks( int $product_id, array $current_chunks ): void {
        global $wpdb;

        $current_keys = array_map( static fn( $c ) => $c['chunk_type'] . '|' . ( $c['chunk_key'] ?? '' ), $current_chunks );

        $stored = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, chunk_type, chunk_key FROM {$wpdb->prefix}replanta_embeddings WHERE product_id = %d",
            $product_id
        ) );

        foreach ( $stored as $row ) {
            $k = $row->chunk_type . '|' . ( $row->chunk_key ?? '' );
            if ( ! in_array( $k, $current_keys, true ) ) {
                $wpdb->delete( $wpdb->prefix . 'replanta_embeddings', [ 'id' => $row->id ] );
            }
        }
    }

    private function delete_product_embeddings( int $product_id ): void {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'replanta_embeddings', [ 'product_id' => $product_id ] );
    }

    private function get_all_product_ids(): array {
        return get_posts( [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );
    }

    private function create_job( string $type ): int {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'replanta_indexing_jobs', [
            'type'       => $type,
            'status'     => 'pending',
            'created_at' => current_time( 'mysql' ),
        ] );
        return (int) $wpdb->insert_id;
    }
}
