<?php
/**
 * Page template.
 *
 * @package ReplantaLite
 */

declare(strict_types=1);

get_header();
?>
<main id="main-content" class="rlt-main" role="main">
    <div class="rlt-container">
        <?php while (have_posts()) : the_post(); ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class('rlt-page'); ?>>
                <header class="rlt-entry-header">
                    <h1 class="rlt-entry-title"><?php the_title(); ?></h1>
                </header>
                <div class="rlt-entry-content">
                    <?php the_content(); ?>
                </div>
            </article>
        <?php endwhile; ?>
    </div>
</main>
<?php
get_footer();
