<?php

namespace Replanta\AiChat\Admin;

defined( 'ABSPATH' ) || exit;

class IndexingPage {

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        global $wpdb;

        $table   = $wpdb->prefix . 'replanta_embeddings';
        $indexed = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT product_id) FROM $table" );
        $total   = (int) wp_count_posts( 'product' )->publish;
        ?>
        <div class="wrap replanta-settings">
            <h1><?php esc_html_e( 'Indexación de productos', 'replanta-ai-chat' ); ?></h1>

            <div class="replanta-index-status">
                <p>
                    <?php printf(
                        esc_html__( 'Productos indexados: %1$d de %2$d', 'replanta-ai-chat' ),
                        $indexed,
                        $total
                    ); ?>
                </p>
                <div class="replanta-progress-bar">
                    <div class="replanta-progress-fill" style="width: <?php echo $total ? round( $indexed / $total * 100 ) : 0; ?>%"></div>
                </div>
            </div>

            <div class="replanta-index-actions">
                <button id="replanta-full-index" class="button button-primary">
                    <?php esc_html_e( 'Reindexar todo el catálogo', 'replanta-ai-chat' ); ?>
                </button>
                <button id="replanta-clear-index" class="button button-secondary">
                    <?php esc_html_e( 'Limpiar índice', 'replanta-ai-chat' ); ?>
                </button>
            </div>

            <div id="replanta-index-log" class="replanta-log-box" style="display:none">
                <h3><?php esc_html_e( 'Log de indexación', 'replanta-ai-chat' ); ?></h3>
                <pre id="replanta-log-content"></pre>
            </div>

            <h2><?php esc_html_e( 'Últimos trabajos de indexación', 'replanta-ai-chat' ); ?></h2>
            <?php
            $jobs = $wpdb->get_results(
                "SELECT * FROM {$wpdb->prefix}replanta_indexing_jobs ORDER BY created_at DESC LIMIT 10"
            );
            if ( $jobs ) :
            ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'ID', 'replanta-ai-chat' ); ?></th>
                        <th><?php esc_html_e( 'Tipo', 'replanta-ai-chat' ); ?></th>
                        <th><?php esc_html_e( 'Estado', 'replanta-ai-chat' ); ?></th>
                        <th><?php esc_html_e( 'Progreso', 'replanta-ai-chat' ); ?></th>
                        <th><?php esc_html_e( 'Fecha', 'replanta-ai-chat' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $jobs as $job ) : ?>
                    <tr>
                        <td><?php echo esc_html( $job->id ); ?></td>
                        <td><?php echo esc_html( $job->type ); ?></td>
                        <td><span class="replanta-status replanta-status-<?php echo esc_attr( $job->status ); ?>"><?php echo esc_html( $job->status ); ?></span></td>
                        <td><?php printf( '%d / %d', (int) $job->processed, (int) $job->total ); ?></td>
                        <td><?php echo esc_html( $job->created_at ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else : ?>
            <p><?php esc_html_e( 'No hay trabajos de indexación registrados.', 'replanta-ai-chat' ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
}
