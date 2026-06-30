<?php defined( 'ABSPATH' ) || exit; ?>

<replanta-ai-chat
    data-api-url="<?php echo esc_attr( rest_url( 'replanta/v1' ) ); ?>"
    data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
    data-assistant-name="<?php
        $g = \Replanta\AiChat\Options::get_general();
        echo esc_attr( $g['assistant_name'] ?? 'Asistente' );
    ?>"
></replanta-ai-chat>
