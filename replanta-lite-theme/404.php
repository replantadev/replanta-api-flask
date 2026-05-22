<?php
/**
 * 404 template.
 *
 * @package ReplantaLite
 */

declare(strict_types=1);

get_header();
?>
<main id="main-content" class="rlt-main" role="main">
    <div class="rlt-container">
        <article class="rlt-404">
            <header class="rlt-entry-header">
                <h1 class="rlt-entry-title"><?php esc_html_e('Página no encontrada', 'replanta-lite'); ?></h1>
            </header>
            <div class="rlt-entry-content">
                <p><?php esc_html_e('Lo sentimos, la URL no existe o fue movida.', 'replanta-lite'); ?></p>
                <?php get_search_form(); ?>
            </div>
        </article>
    </div>
</main>
<?php
get_footer();
