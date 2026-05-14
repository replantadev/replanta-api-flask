<?php
namespace TEW\API;

use WP_Error;
use function __;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Mantiene compatibilidad retroactiva tras retirar EcoGrader.
 *
 * El nuevo flujo utiliza el Eco Snapshot Score propio, por lo que cualquier llamada
 * a este cliente devolverá un error controlado.
 */
class Ecograder_Client extends Client_Base {

    /**
     * @inheritdoc
     */
    public function audit( $url, array $args = [] ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
        return new WP_Error(
            'tew_ecograder_retired',
            __( 'EcoGrader ha sido retirado del flujo. El informe ahora usa el Eco Snapshot Score interno.', 'test-eco-website' ),
            [ 'status' => 410 ]
        );
    }
}
