<?php

namespace Replanta\AiChat\Admin;

defined( 'ABSPATH' ) || exit;

class ConversationsPage {

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        global $wpdb;

        $per_page = 25;
        $page     = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
        $offset   = ( $page - 1 ) * $per_page;

        // ── Stats ──────────────────────────────────────────────────────────
        $stats = self::get_stats();

        // ── List ───────────────────────────────────────────────────────────
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}replanta_conversations" );
        $rows  = $wpdb->get_results( $wpdb->prepare(
            "SELECT c.*,
                    COUNT(m.id)                                                  AS message_count,
                    COALESCE(SUM(m.tokens_used), 0)                              AS tokens,
                    MAX(CASE WHEN m.tool_calls IS NOT NULL THEN 1 ELSE 0 END)    AS had_tool_call,
                    AVG(f.rating)                                                AS avg_rating
             FROM {$wpdb->prefix}replanta_conversations c
             LEFT JOIN {$wpdb->prefix}replanta_messages m  ON m.conversation_id = c.id
             LEFT JOIN {$wpdb->prefix}replanta_feedback  f ON f.message_id = m.id
             GROUP BY c.id
             ORDER BY c.started_at DESC
             LIMIT %d OFFSET %d",
            $per_page, $offset
        ) );
        ?>
        <div class="wrap replanta-settings">
            <h1><?php esc_html_e( 'Conversaciones', 'replanta-ai-chat' ); ?></h1>

            <!-- Stats grid -->
            <div class="replanta-stats-grid">
                <div class="replanta-stat-card">
                    <span class="replanta-stat-number"><?php echo esc_html( number_format( $stats['total_conversations'] ) ); ?></span>
                    <span class="replanta-stat-label"><?php esc_html_e( 'Conversaciones', 'replanta-ai-chat' ); ?></span>
                </div>
                <div class="replanta-stat-card">
                    <span class="replanta-stat-number"><?php echo esc_html( number_format( $stats['total_messages'] ) ); ?></span>
                    <span class="replanta-stat-label"><?php esc_html_e( 'Mensajes', 'replanta-ai-chat' ); ?></span>
                </div>
                <div class="replanta-stat-card replanta-stat-card--green">
                    <span class="replanta-stat-number"><?php echo esc_html( $stats['cart_conversations'] ); ?></span>
                    <span class="replanta-stat-label"><?php esc_html_e( 'Con acción de carrito', 'replanta-ai-chat' ); ?></span>
                </div>
                <div class="replanta-stat-card">
                    <span class="replanta-stat-number"><?php echo $stats['avg_rating'] ? esc_html( $stats['avg_rating'] . ' ★' ) : '—'; ?></span>
                    <span class="replanta-stat-label"><?php esc_html_e( 'Valoración media', 'replanta-ai-chat' ); ?></span>
                </div>
                <div class="replanta-stat-card">
                    <span class="replanta-stat-number"><?php echo esc_html( number_format( $stats['total_tokens'] ) ); ?></span>
                    <span class="replanta-stat-label"><?php esc_html_e( 'Tokens usados', 'replanta-ai-chat' ); ?></span>
                </div>
            </div>

            <?php if ( $rows ) : ?>

            <table class="widefat replanta-conv-table" id="replanta-conv-table">
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th><?php esc_html_e( 'Sesión', 'replanta-ai-chat' ); ?></th>
                        <th><?php esc_html_e( 'Msgs', 'replanta-ai-chat' ); ?></th>
                        <th><?php esc_html_e( 'Tokens', 'replanta-ai-chat' ); ?></th>
                        <th><?php esc_html_e( 'Carrito', 'replanta-ai-chat' ); ?></th>
                        <th><?php esc_html_e( 'Rating', 'replanta-ai-chat' ); ?></th>
                        <th><?php esc_html_e( 'Inicio', 'replanta-ai-chat' ); ?></th>
                        <th><?php esc_html_e( 'Acciones', 'replanta-ai-chat' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $rows as $row ) : ?>
                    <tr class="replanta-conv-row" data-conv-id="<?php echo esc_attr( $row->id ); ?>">
                        <td><?php echo esc_html( $row->id ); ?></td>
                        <td><code title="<?php echo esc_attr( $row->session_id ); ?>"><?php echo esc_html( substr( $row->session_id, 0, 12 ) ); ?>…</code></td>
                        <td><?php echo esc_html( $row->message_count ); ?></td>
                        <td><?php echo esc_html( number_format( (int) $row->tokens ) ); ?></td>
                        <td><?php echo $row->had_tool_call ? '<span class="replanta-badge replanta-badge--green">✓</span>' : '<span class="replanta-badge replanta-badge--gray">—</span>'; ?></td>
                        <td><?php echo $row->avg_rating ? esc_html( round( (float) $row->avg_rating, 1 ) . ' ★' ) : '—'; ?></td>
                        <td><?php echo esc_html( wp_date( 'd/m/y H:i', strtotime( $row->started_at ) ) ); ?></td>
                        <td>
                            <button class="button button-small replanta-expand-conv"
                                    data-conv-id="<?php echo esc_attr( $row->id ); ?>">
                                <?php esc_html_e( 'Ver', 'replanta-ai-chat' ); ?>
                            </button>
                        </td>
                    </tr>
                    <tr class="replanta-conv-detail" id="replanta-conv-detail-<?php echo esc_attr( $row->id ); ?>" style="display:none">
                        <td colspan="8">
                            <div class="replanta-thread-wrap">
                                <div class="replanta-thread-loading"><?php esc_html_e( 'Cargando…', 'replanta-ai-chat' ); ?></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="tablenav bottom" style="margin-top:12px">
                <?php
                $pages = (int) ceil( $total / $per_page );
                if ( $pages > 1 ) {
                    echo paginate_links( [
                        'base'    => add_query_arg( 'paged', '%#%' ),
                        'format'  => '',
                        'total'   => $pages,
                        'current' => $page,
                    ] );
                }
                ?>
            </div>

            <?php else : ?>
            <div class="replanta-empty-state">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <p><?php esc_html_e( 'Aún no hay conversaciones. El asistente empezará a registrarlas cuando los clientes comiencen a chatear.', 'replanta-ai-chat' ); ?></p>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    // ── Stats queries ──────────────────────────────────────────────────────

    private static function get_stats(): array {
        global $wpdb;

        $total_conv  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}replanta_conversations" );
        $total_msgs  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}replanta_messages WHERE role IN ('user','assistant')" );
        $total_tok   = (int) $wpdb->get_var( "SELECT COALESCE(SUM(tokens_used),0) FROM {$wpdb->prefix}replanta_messages" );
        $cart_convs  = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT conversation_id) FROM {$wpdb->prefix}replanta_messages WHERE tool_calls IS NOT NULL"
        );
        $avg_rating  = $wpdb->get_var( "SELECT ROUND(AVG(rating),1) FROM {$wpdb->prefix}replanta_feedback" );

        return [
            'total_conversations' => $total_conv,
            'total_messages'      => $total_msgs,
            'total_tokens'        => $total_tok,
            'cart_conversations'  => $cart_convs,
            'avg_rating'          => $avg_rating,
        ];
    }

    // ── AJAX: load messages for a conversation ─────────────────────────────

    public static function ajax_get_conversation(): void {
        check_ajax_referer( 'replanta_conversation' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Forbidden', 403 );
        }

        $conv_id = (int) ( $_POST['conv_id'] ?? 0 );
        if ( ! $conv_id ) {
            wp_send_json_error( 'Invalid ID', 400 );
        }

        global $wpdb;
        $messages = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, role, content, tool_calls, tokens_used, created_at
             FROM {$wpdb->prefix}replanta_messages
             WHERE conversation_id = %d
               AND role IN ('user','assistant')
             ORDER BY created_at ASC",
            $conv_id
        ), ARRAY_A );

        $ratings = $wpdb->get_results( $wpdb->prepare(
            "SELECT f.message_id, f.rating
             FROM {$wpdb->prefix}replanta_feedback f
             JOIN {$wpdb->prefix}replanta_messages m ON m.id = f.message_id
             WHERE m.conversation_id = %d",
            $conv_id
        ), ARRAY_A );

        $rating_map = array_column( $ratings, 'rating', 'message_id' );

        foreach ( $messages as &$msg ) {
            $msg['rating']     = $rating_map[ $msg['id'] ] ?? null;
            $msg['has_tool']   = ! empty( $msg['tool_calls'] );
            unset( $msg['tool_calls'] );
        }
        unset( $msg );

        wp_send_json_success( $messages );
    }
}
