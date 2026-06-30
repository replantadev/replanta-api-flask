<?php

namespace Replanta\AiChat\Tools;

use Replanta\AiChat\Options;

defined( 'ABSPATH' ) || exit;

class EscalationTool {

    public function definition(): array {
        return [
            'name'        => 'escalate_to_human',
            'description' => 'Escala la conversación a un asesor humano. Úsalo cuando el cliente lo pida, la consulta supere tu capacidad, haya una reclamación o el cliente esté insatisfecho.',
            'input_schema'=> [
                'type'       => 'object',
                'properties' => [
                    'reason' => [
                        'type'        => 'string',
                        'description' => 'Motivo de la escalada.',
                    ],
                    'summary' => [
                        'type'        => 'string',
                        'description' => 'Resumen breve de la conversación para el asesor.',
                    ],
                ],
                'required'   => [ 'reason' ],
            ],
        ];
    }

    public function execute( array $input ): array {
        $reason  = sanitize_text_field( $input['reason'] ?? 'Sin especificar' );
        $summary = sanitize_textarea_field( $input['summary'] ?? '' );

        $this->notify_team( $reason, $summary );

        return [
            'success' => true,
            'action'  => 'escalate',
            'message' => __( 'Te pongo en contacto con un asesor. En breve recibirás respuesta.', 'replanta-ai-chat' ),
        ];
    }

    private function notify_team( string $reason, string $summary ): void {
        $behaviour = Options::get_behaviour();
        $email     = $behaviour['escalation_email'] ?? '';

        if ( ! $email || ! is_email( $email ) ) {
            return;
        }

        $general = Options::get_general();
        $subject = sprintf(
            __( '[%s] Nueva conversación escalada: %s', 'replanta-ai-chat' ),
            get_bloginfo( 'name' ),
            $reason
        );

        $message = sprintf(
            "Se ha escalado una conversación del asistente %s.\n\nMotivo: %s\n\nResumen:\n%s\n\nFecha: %s",
            $general['assistant_name'] ?? 'AI Chat',
            $reason,
            $summary ?: 'Sin resumen disponible',
            current_time( 'mysql' )
        );

        wp_mail( $email, $subject, $message );
    }
}
