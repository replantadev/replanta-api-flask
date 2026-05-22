<?php
/**
 * Index template.
 *
 * @package ReplantaLite
 */

declare(strict_types=1);

get_header();
?>
<main id="main-content" class="rlt-main" role="main">
    <div class="rlt-container">
        <?php if (have_posts()) : ?>
            <?php while (have_posts()) : the_post(); ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class('rlt-article'); ?>>
                    <header class="rlt-entry-header">
                        <h1 class="rlt-entry-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h1>
                    </header>
                    <div class="rlt-entry-content">
                        <?php the_excerpt(); ?>
                    </div>
                </article>
            <?php endwhile; ?>
            <nav class="rlt-pagination" aria-label="<?php esc_attr_e('Posts navigation', 'replanta-lite'); ?>">
                <?php the_posts_pagination(); ?>
            </nav>
        <?php else : ?>
            <section class="rlt-empty" aria-label="<?php esc_attr_e('No posts', 'replanta-lite'); ?>">
                <h1><?php esc_html_e('No content found', 'replanta-lite'); ?></h1>
            </section>
        <?php endif; ?>
    </div>
</main>
<?php
get_footer();
