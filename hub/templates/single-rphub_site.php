<?php
/**
 * Template personalizado para sitios individuales
 */

get_header(); ?>

<div class="wrap">
    <div class="rphub-site-container">
        
        <?php while (have_posts()) : the_post(); ?>
            
            <article id="post-<?php the_ID(); ?>" <?php post_class('rphub-site-article'); ?>>
                
                <!-- Breadcrumb -->
                <nav class="rphub-breadcrumb">
                    <a href="<?php echo admin_url('admin.php?page=replanta-hub'); ?>">
                        <?php _e('← Volver al Hub', 'replanta-hub'); ?>
                    </a>
                </nav>

                <!-- Site Content -->
                <div class="entry-content">
                    <?php the_content(); ?>
                </div>

            </article>
            
        <?php endwhile; ?>
        
    </div>
</div>

<style>
.rphub-site-container {
    max-width: 100%;
    margin: 0;
    padding: 0;
}

.rphub-breadcrumb {
    margin-bottom: 20px;
    padding: 10px 0;
    border-bottom: 1px solid #e1e5e9;
}

.rphub-breadcrumb a {
    color: #0073aa;
    text-decoration: none;
    font-weight: 500;
}

.rphub-breadcrumb a:hover {
    text-decoration: underline;
}

.rphub-site-article {
    background: #f7f7f7;
    min-height: 100vh;
}

.entry-content {
    margin: 0;
}

/* Ocultar elementos de WordPress que no necesitamos */
.entry-header,
.entry-meta,
.entry-footer,
.post-navigation,
.comments-area {
    display: none;
}

/* Asegurar que el dashboard ocupe toda la pantalla */
body.single-rphub_site {
    margin: 0;
    padding: 0;
}

body.single-rphub_site #main {
    margin: 0;
    padding: 0;
}

body.single-rphub_site .site-content {
    margin: 0;
    padding: 0;
}
</style>

<?php get_footer(); ?>
