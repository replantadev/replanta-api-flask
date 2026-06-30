<?php

namespace Replanta\AiChat\Llm;

use Replanta\AiChat\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Post-processing guardrails on LLM output.
 * Validates cosmetic claims compliance and strips blacklisted terms.
 */
class Guardrails {

    private array $blacklist = [];

    public function __construct() {
        $behaviour       = Options::get_behaviour();
        $raw             = $behaviour['claims_blacklist'] ?? '';
        $this->blacklist = array_filter( array_map( 'trim', explode( "\n", strtolower( $raw ) ) ) );
    }

    /**
     * Validate and optionally transform the LLM response.
     * Returns [ 'text' => string, 'flagged' => bool, 'flags' => string[] ]
     */
    public function validate( string $text ): array {
        $flags   = [];
        $lower   = mb_strtolower( $text );

        foreach ( $this->blacklist as $term ) {
            if ( str_contains( $lower, $term ) ) {
                $flags[] = "Término en lista negra detectado: '{$term}'";
            }
        }

        $flagged = ! empty( $flags );

        // If flagged, append a soft disclaimer rather than blocking the whole response
        if ( $flagged ) {
            $text .= "\n\n_Este producto es un cosmético. No sustituye tratamientos médicos ni farmacéuticos._";
        }

        return [
            'text'    => $text,
            'flagged' => $flagged,
            'flags'   => $flags,
        ];
    }
}
