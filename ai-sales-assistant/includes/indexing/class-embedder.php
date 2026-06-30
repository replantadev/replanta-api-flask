<?php

namespace Replanta\AiChat\Indexing;

use Replanta\AiChat\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Calls OpenAI embeddings API and returns float vectors.
 */
class Embedder {

    const MODEL       = 'text-embedding-3-small';
    const DIMENSIONS  = 1536;
    const BATCH_SIZE  = 100;
    const API_URL     = 'https://api.openai.com/v1/embeddings';

    /**
     * Embed a single text. Returns float[] or null on failure.
     */
    public function embed( string $text ): ?array {
        $results = $this->embed_batch( [ $text ] );
        return $results[0] ?? null;
    }

    /**
     * Embed multiple texts in a single API call.
     * Returns array of float[] indexed same as input.
     */
    public function embed_batch( array $texts ): array {
        $key = Options::embeddings_key();
        if ( ! $key ) {
            throw new \RuntimeException( 'OpenAI embeddings API key not configured.' );
        }

        // Truncate texts to avoid token limit (8191 tokens max)
        $texts = array_map( [ $this, 'truncate' ], $texts );

        $response = wp_remote_post( self::API_URL, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => "Bearer {$key}",
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode( [
                'input'      => $texts,
                'model'      => self::MODEL,
                'dimensions' => self::DIMENSIONS,
            ] ),
        ] );

        if ( is_wp_error( $response ) ) {
            throw new \RuntimeException( 'Embeddings request failed: ' . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            throw new \RuntimeException( 'Embeddings API error ' . $code . ': ' . ( $body['error']['message'] ?? 'unknown' ) );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $data = $body['data'] ?? [];

        // Sort by index to match input order
        usort( $data, static fn( $a, $b ) => $a['index'] <=> $b['index'] );

        return array_column( $data, 'embedding' );
    }

    /**
     * Serialize float[] to binary blob for DB storage.
     * Uses PHP pack() for compact binary format: 4 bytes per float.
     */
    public static function serialize( array $floats ): string {
        return pack( 'f*', ...$floats );
    }

    /**
     * Deserialize binary blob back to float[].
     */
    public static function deserialize( string $blob ): array {
        return array_values( (array) unpack( 'f*', $blob ) );
    }

    /**
     * Cosine similarity between two float vectors. Returns 0.0–1.0.
     */
    public static function cosine_similarity( array $a, array $b ): float {
        $dot = 0.0;
        $na  = 0.0;
        $nb  = 0.0;
        $len = min( count( $a ), count( $b ) );

        for ( $i = 0; $i < $len; $i++ ) {
            $dot += $a[ $i ] * $b[ $i ];
            $na  += $a[ $i ] ** 2;
            $nb  += $b[ $i ] ** 2;
        }

        $denom = sqrt( $na ) * sqrt( $nb );
        return $denom > 0.0 ? (float) ( $dot / $denom ) : 0.0;
    }

    private function truncate( string $text ): string {
        // Rough approximation: 4 chars per token; keep under 8000 tokens
        return mb_substr( $text, 0, 32000 );
    }
}
