<?php
/**
 * Archive template.
 *
 * @package ReplantaLite
 */

declare(strict_types=1);

get_header();
?>
<main id="main-content" class="rlt-main" role="main">
    <div class="rlt-container">
        <header class="rlt-entry-header">
            <h1 class="rlt-entry-title"><?php the_archive_title(); ?></h1>
            <?php the_archive_description('<div class="rlt-entry-content">', '</div>'); ?>
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
            <p><?php esc_html_e('No hay entradas en este archivo.', 'replanta-lite'); ?></p>
        <?php endif; ?>
    </div>
</main>
<?php
get_footer();
