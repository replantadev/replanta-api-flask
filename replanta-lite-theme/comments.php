<?php
/**
 * The comments template.
 *
 * @package ReplantaLite
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// Password-protected posts: show prompt instead of comments.
if (post_password_required()) {
    echo '<p class="rlt-comment-password">' . esc_html__('Introduce la contraseña para ver los comentarios.', 'replanta-lite') . '</p>';
    return;
}
?>
<div id="comments" class="rlt-comments-area">

    <?php if (have_comments()) : ?>
        <h2 class="rlt-comments-title">
            <?php
            $rlt_comment_count = get_comments_number();
            if ($rlt_comment_count === '1') {
                printf(
                    /* translators: %s: post title */
                    esc_html__('Un comentario en &ldquo;%s&rdquo;', 'replanta-lite'),
                    '<span>' . esc_html(get_the_title()) . '</span>'
                );
            } else {
                printf(
                    /* translators: 1: number of comments, 2: post title */
                    esc_html(_n(
                        '%1$s comentario en &ldquo;%2$s&rdquo;',
                        '%1$s comentarios en &ldquo;%2$s&rdquo;',
                        (int) $rlt_comment_count,
                        'replanta-lite'
                    )),
                    esc_html(number_format_i18n((int) $rlt_comment_count)),
                    '<span>' . esc_html(get_the_title()) . '</span>'
                );
            }
            ?>
        </h2>

        <ol class="comment-list">
            <?php
            wp_list_comments([
                'style'       => 'ol',
                'short_ping'  => true,
                'avatar_size' => 40,
            ]);
            ?>
        </ol>

        <?php the_comments_navigation(); ?>

    <?php endif; // have_comments() ?>

    <?php if (!comments_open() && get_comments_number() && post_type_supports(get_post_type(), 'comments')) : ?>
        <p class="no-comments">
            <?php esc_html_e('Los comentarios están cerrados.', 'replanta-lite'); ?>
        </p>
    <?php endif; ?>

    <?php
    comment_form([
        'title_reply_before' => '<h2 id="reply-title" class="rlt-comments-title">',
        'title_reply_after'  => '</h2>',
    ]);
    ?>

</div><!-- #comments -->
