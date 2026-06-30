<?php

namespace Replanta\AiChat\Rest;

use Replanta\AiChat\Indexing\Indexer;
use Replanta\AiChat\Retrieval\VectorSearch;

defined( 'ABSPATH' ) || exit;

class IndexingEndpoint {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init(): void {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
        add_action( 'replanta_ai_chat_index_product', [ $this, 'async_index_product' ], 10, 1 );
        add_action( 'replanta_ai_chat_full_reindex', [ $this, 'run_full_reindex_cron' ] );
    }

    public function run_full_reindex_cron(): void {
        try {
            ( new Indexer() )->full_reindex();
        } catch ( \Throwable $e ) {
            error_log( '[Replanta AI Chat] Full reindex cron failed: ' . $e->getMessage() );
        }
    }

    public function register_routes(): void {
        register_rest_route( 'replanta/v1', '/index', [
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'trigger_full_index' ],
                'permission_callback' => static fn() => current_user_can( 'manage_options' ),
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ $this, 'clear_index' ],
                'permission_callback' => static fn() => current_user_can( 'manage_options' ),
            ],
        ] );

        register_rest_route( 'replanta/v1', '/index/(?P<product_id>\d+)', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'index_single_product' ],
            'permission_callback' => static fn() => current_user_can( 'manage_options' ),
        ] );

        register_rest_route( 'replanta/v1', '/index/status', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_status' ],
            'permission_callback' => static fn() => current_user_can( 'manage_options' ),
        ] );
    }

    public function trigger_full_index( \WP_REST_Request $request ): \WP_REST_Response {
        $sync = filter_var( $request->get_param( 'sync' ), FILTER_VALIDATE_BOOLEAN );

        // For small catalogs or explicit sync request, run immediately
        $total = (int) wp_count_posts( 'product' )->publish;
        if ( $sync || $total <= 50 ) {
            try {
                ( new Indexer() )->full_reindex();
                return new \WP_REST_Response( [ 'queued' => false, 'done' => true ], 200 );
            } catch ( \Throwable $e ) {
                return new \WP_REST_Response( [ 'error' => $e->getMessage() ], 500 );
            }
        }

        // Large catalogs: schedule via WP-Cron and force spawn
        if ( ! wp_next_scheduled( 'replanta_ai_chat_full_reindex' ) ) {
            wp_schedule_single_event( time(), 'replanta_ai_chat_full_reindex' );
        }
        spawn_cron();

        return new \WP_REST_Response( [ 'queued' => true ], 202 );
    }

    public function clear_index(): \WP_REST_Response {
        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}replanta_embeddings" );
        VectorSearch::flush_cache();
        return new \WP_REST_Response( [ 'cleared' => true ], 200 );
    }

    public function index_single_product( \WP_REST_Request $request ): \WP_REST_Response {
        $product_id = (int) $request->get_param( 'product_id' );

        try {
            ( new Indexer() )->index_product( $product_id );
            VectorSearch::flush_cache();
            return new \WP_REST_Response( [ 'success' => true, 'product_id' => $product_id ], 200 );
        } catch ( \Throwable $e ) {
            return new \WP_REST_Response( [ 'error' => $e->getMessage() ], 500 );
        }
    }

    public function get_status(): \WP_REST_Response {
        global $wpdb;

        $indexed = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT product_id) FROM {$wpdb->prefix}replanta_embeddings" );
        $total   = (int) wp_count_posts( 'product' )->publish;
        $latest  = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}replanta_indexing_jobs ORDER BY created_at DESC LIMIT 1", ARRAY_A );

        return new \WP_REST_Response( [
            'indexed'   => $indexed,
            'total'     => $total,
            'pct'       => $total > 0 ? round( $indexed / $total * 100, 1 ) : 0,
            'last_job'  => $latest,
        ], 200 );
    }

    public function async_index_product( int $product_id ): void {
        try {
            ( new Indexer() )->index_product( $product_id );
            VectorSearch::flush_cache();
        } catch ( \Throwable $e ) {
            error_log( "[Replanta AI Chat] Failed to index product {$product_id}: " . $e->getMessage() );
        }
    }
}
