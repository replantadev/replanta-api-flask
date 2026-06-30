<?php

namespace Replanta\AiChat\Indexing;

use Replanta\AiChat\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Extracts all indexable content from a WooCommerce product,
 * including native fields, WC attributes/variations, and ACF/meta custom fields.
 */
class ProductExtractor {

    /**
     * Returns an array of chunks: [ 'type' => string, 'key' => string|null, 'content' => string ]
     * Returns empty array if product should not be indexed.
     */
    public function extract( int $product_id ): array {
        $product = wc_get_product( $product_id );
        if ( ! $product || $product->get_status() !== 'publish' ) {
            return [];
        }

        $index_opts = Options::get_indexing();
        if ( empty( $index_opts['index_out_of_stock'] ) && ! $product->is_in_stock() ) {
            return [];
        }

        $chunks = [];

        // ── Core product data ──────────────────────────────────────────────────
        $chunks[] = [
            'chunk_type' => 'main',
            'chunk_key'  => null,
            'content'    => $this->build_main_chunk( $product ),
        ];

        // ── Long description ───────────────────────────────────────────────────
        $desc = wp_strip_all_tags( $product->get_description() );
        if ( strlen( $desc ) > 50 ) {
            $chunks[] = [
                'chunk_type' => 'description',
                'chunk_key'  => null,
                'content'    => "Descripción completa de {$product->get_name()}:\n{$desc}",
            ];
        }

        // ── WooCommerce attributes ─────────────────────────────────────────────
        $attrs = $this->build_attributes_chunk( $product );
        if ( $attrs ) {
            $chunks[] = [
                'chunk_type' => 'attributes',
                'chunk_key'  => null,
                'content'    => $attrs,
            ];
        }

        // ── ACF fields ────────────────────────────────────────────────────────
        $acf_chunks = $this->extract_acf_fields( $product_id, $product->get_name() );
        $chunks     = array_merge( $chunks, $acf_chunks );

        // ── Native meta fields ────────────────────────────────────────────────
        $meta_chunks = $this->extract_meta_fields( $product_id, $product->get_name() );
        $chunks      = array_merge( $chunks, $meta_chunks );

        // Filter out empty chunks
        return array_values( array_filter( $chunks, static fn( $c ) => strlen( trim( $c['content'] ) ) > 20 ) );
    }

    // ── Builders ──────────────────────────────────────────────────────────────

    private function build_main_chunk( \WC_Product $product ): string {
        $parts = [];

        $parts[] = "Producto: {$product->get_name()}";
        $parts[] = "SKU: {$product->get_sku()}";
        $parts[] = "ID: {$product->get_id()}";

        $cats = wp_get_post_terms( $product->get_id(), 'product_cat', [ 'fields' => 'names' ] );
        if ( ! is_wp_error( $cats ) && $cats ) {
            $parts[] = 'Categorías: ' . implode( ', ', $cats );
        }

        $tags = wp_get_post_terms( $product->get_id(), 'product_tag', [ 'fields' => 'names' ] );
        if ( ! is_wp_error( $tags ) && $tags ) {
            $parts[] = 'Etiquetas: ' . implode( ', ', $tags );
        }

        $short = wp_strip_all_tags( $product->get_short_description() );
        if ( $short ) {
            $parts[] = "Descripción corta: {$short}";
        }

        $price = $product->get_price();
        if ( $price !== '' ) {
            $parts[] = 'Precio: ' . wc_price( $price );
        }

        $parts[] = 'Stock: ' . ( $product->is_in_stock() ? 'Disponible' : 'Sin stock' );

        // Variations summary for variable products
        if ( $product->is_type( 'variable' ) ) {
            /** @var \WC_Product_Variable $product */
            $variation_attrs = $product->get_variation_attributes();
            foreach ( $variation_attrs as $attr_name => $values ) {
                $parts[] = wc_attribute_label( $attr_name ) . ': ' . implode( ', ', $values );
            }
        }

        return implode( "\n", $parts );
    }

    private function build_attributes_chunk( \WC_Product $product ): string {
        $attrs = $product->get_attributes();
        if ( empty( $attrs ) ) {
            return '';
        }

        $lines = [ "Atributos de {$product->get_name()}:" ];
        foreach ( $attrs as $attr ) {
            $label  = wc_attribute_label( $attr->get_name() );
            $values = $attr->is_taxonomy()
                ? implode( ', ', wp_get_post_terms( $product->get_id(), $attr->get_name(), [ 'fields' => 'names' ] ) )
                : implode( ', ', $attr->get_options() );

            if ( $values ) {
                $lines[] = "{$label}: {$values}";
            }
        }

        return count( $lines ) > 1 ? implode( "\n", $lines ) : '';
    }

    private function extract_acf_fields( int $product_id, string $product_name ): array {
        if ( ! function_exists( 'get_field' ) ) {
            return [];
        }

        $opts       = Options::get_indexing();
        $acf_fields = $opts['acf_fields'] ?? [];
        $chunks     = [];

        foreach ( $acf_fields as $field_config ) {
            $key   = $field_config['key'] ?? '';
            $label = $field_config['label'] ?? $key;

            if ( ! $key ) {
                continue;
            }

            $value = get_field( $key, $product_id );
            $text  = $this->acf_value_to_string( $value, $key );

            if ( ! $text ) {
                continue;
            }

            $chunks[] = [
                'chunk_type' => 'acf',
                'chunk_key'  => $key,
                'content'    => "{$label} de {$product_name}:\n{$text}",
            ];
        }

        return $chunks;
    }

    private function extract_meta_fields( int $product_id, string $product_name ): array {
        $opts        = Options::get_indexing();
        $meta_fields = $opts['meta_fields'] ?? [];
        $chunks      = [];

        foreach ( $meta_fields as $field_config ) {
            $key   = $field_config['key'] ?? '';
            $label = $field_config['label'] ?? $key;

            if ( ! $key ) {
                continue;
            }

            $value = get_post_meta( $product_id, $key, true );
            $text  = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
            $text  = trim( wp_strip_all_tags( $text ) );

            if ( ! $text ) {
                continue;
            }

            $chunks[] = [
                'chunk_type' => 'meta',
                'chunk_key'  => $key,
                'content'    => "{$label} de {$product_name}:\n{$text}",
            ];
        }

        return $chunks;
    }

    /**
     * Convert an ACF field value to a plain string suitable for indexing.
     * Handles selection/checkbox fields by resolving labels from field config.
     *
     * @param mixed  $value    Raw value from get_field()
     * @param string $field_key ACF field key (field_xxx) to look up choices
     */
    private function acf_value_to_string( mixed $value, string $field_key = '' ): string {
        if ( is_null( $value ) || $value === '' || $value === false ) {
            return '';
        }

        if ( is_string( $value ) ) {
            return trim( wp_strip_all_tags( $value ) );
        }

        if ( is_array( $value ) ) {
            // ACF "Both" return format: [['value'=>..., 'label'=>...], ...]
            if ( isset( $value[0] ) && is_array( $value[0] ) && isset( $value[0]['label'] ) ) {
                return implode( ', ', array_column( $value, 'label' ) );
            }

            // Repeater / flexible content (rows are associative arrays)
            if ( isset( $value[0] ) && is_array( $value[0] ) && ! isset( $value[0]['label'] ) ) {
                $parts = [];
                foreach ( $value as $row ) {
                    $row_parts = [];
                    foreach ( $row as $k => $v ) {
                        if ( ! is_array( $v ) && $v !== '' ) {
                            $row_parts[] = ucfirst( $k ) . ': ' . wp_strip_all_tags( (string) $v );
                        }
                    }
                    $parts[] = implode( ', ', $row_parts );
                }
                return implode( "\n", $parts );
            }

            // Flat array (checkbox / multi-select values)
            // Try to resolve labels from ACF field choices
            if ( $field_key && function_exists( 'acf_get_field' ) ) {
                $field_obj = acf_get_field( $field_key );
                if ( $field_obj && ! empty( $field_obj['choices'] ) ) {
                    $labels = array_map(
                        static fn( $v ) => $field_obj['choices'][ $v ] ?? $v,
                        $value
                    );
                    return implode( ', ', $labels );
                }
            }

            return implode( ', ', array_map( 'wp_strip_all_tags', $value ) );
        }

        if ( is_object( $value ) && isset( $value->post_title ) ) {
            return $value->post_title;
        }

        return trim( (string) $value );
    }
}
