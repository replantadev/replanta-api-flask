<?php
/**
 * Search results template.
 *
 * @package ReplantaLite
 */

declare(strict_types=1);

get_header();
?>
<main id="main-content" class="rlt-main" role="main">
    <div class="rlt-container">
        <header class="rlt-entry-header">
            <h1 class="rlt-entry-title">
                <?php
                printf(
                    /* translators: %s search query */
                    esc_html__('Resultados para: %s', 'replanta-lite'),
                    '<span>' . esc_html(get_search_query()) . '</span>'
                );
                ?>
            </h1>
        </header>
        <?php if (have_posts()) : ?>
            <?php while (have_posts()) : the_post(); ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class('rlt-article'); ?>>
                    <h2 class="rlt-entry-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    <div class="rlt-entry-content"><?php the_excerpt(); ?></div>
                </article>
            <?php endwhile; ?>
            <?php the_posts_pagination(); ?>
        <?php else : ?>
            <p><?php esc_html_e('No se encontraron resultados.', 'replanta-lite'); ?></p>
        <?php endif; ?>
    </div>
</main>
<?php
get_footer();
