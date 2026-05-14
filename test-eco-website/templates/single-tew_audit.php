<?php
/**
 * Template for displaying persisted Eco-Performance reports.
 */

use TEW\Reporting\Report_Storage;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Agregar meta tags para SEO antes del header
add_action( 'wp_head', function() {
    if ( ! is_singular( Report_Storage::POST_TYPE ) ) {
        return;
    }
    
    $storage = new Report_Storage();
    $report  = $storage->find( get_the_ID() );
    
    if ( is_wp_error( $report ) || empty( $report ) ) {
        return;
    }
    
    // Extraer datos del informe para meta tags
    $url = $report['url'] ?? '';
    $domain = $url ? parse_url( $url, PHP_URL_HOST ) : '';
    $score = $report['summary']['score'] ?? 0;
    $grade = $report['summary']['grade'] ?? 'N/A';
    $co2 = $report['metrics']['carbon']['co2_per_view'] ?? $report['metrics']['websitecarbon']['co2_per_view'] ?? 0;
    $is_green = $report['metrics']['greenweb']['is_green'] ?? $report['metrics']['green_hosting']['is_green'] ?? false;
    
    // Título SEO optimizado
    $seo_title = $domain ? "Análisis Eco-Performance de {$domain} - Score {$score}" : get_the_title();
    
    // Descripción SEO optimizada
    $seo_description = sprintf( 
        'Informe de sostenibilidad web para %s: Score %s (Grade %s), %.2fg CO2 por visita. %s. Análisis completo de rendimiento y impacto ambiental.',
        $domain ?: 'sitio web',
        $score,
        $grade,
        $co2,
        $is_green ? 'Hosting verde verificado' : 'Hosting convencional'
    );
    
    // Meta tags básicos
    echo '<meta name="description" content="' . esc_attr( $seo_description ) . '">' . PHP_EOL;
    echo '<meta name="robots" content="index, follow">' . PHP_EOL;
    
    // Open Graph para redes sociales
    echo '<meta property="og:title" content="' . esc_attr( $seo_title ) . '">' . PHP_EOL;
    echo '<meta property="og:description" content="' . esc_attr( $seo_description ) . '">' . PHP_EOL;
    echo '<meta property="og:type" content="article">' . PHP_EOL;
    echo '<meta property="og:url" content="' . esc_url( get_permalink() ) . '">' . PHP_EOL;
    echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . PHP_EOL;
    echo '<meta property="og:image" content="https://replanta.net/wp-content/uploads/2024/02/og-eco-snapshot.jpg">' . PHP_EOL;
    
    // Twitter Card
    echo '<meta name="twitter:card" content="summary_large_image">' . PHP_EOL;
    echo '<meta name="twitter:title" content="' . esc_attr( $seo_title ) . '">' . PHP_EOL;
    echo '<meta name="twitter:description" content="' . esc_attr( $seo_description ) . '">' . PHP_EOL;
    echo '<meta name="twitter:image" content="https://replanta.net/wp-content/uploads/2024/02/og-eco-snapshot.jpg">' . PHP_EOL;
    
    // Schema.org structured data para informes
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Report',
        'name' => $seo_title,
        'description' => $seo_description,
        'url' => get_permalink(),
        'about' => [
            '@type' => 'WebSite',
            'url' => $url,
            'name' => $domain
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => get_bloginfo( 'name' ),
            'url' => home_url()
        ],
        'datePublished' => get_the_date( 'c' ),
        'dateModified' => get_the_modified_date( 'c' ),
        'keywords' => 'sostenibilidad web, análisis eco-performance, huella de carbono, hosting verde, PageSpeed Insights'
    ];
    
    echo '<script type="application/ld+json">' . PHP_EOL;
    echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . PHP_EOL;
    echo '</script>' . PHP_EOL;
    
    // Meta tags específicos para RankMath
    echo '<meta name="rankmath_focus_keyword" content="análisis sostenibilidad web ' . esc_attr( $domain ) . '">' . PHP_EOL;
    echo '<meta name="rankmath_seo_score" content="' . min( 100, max( 0, $score ) ) . '">' . PHP_EOL;
}, 1 );

get_header();

$storage = new Report_Storage();
$report  = $storage->find( get_the_ID() );

wp_enqueue_style( 'tew-frontend' );
wp_enqueue_script( 'tew-frontend' );

$initial_payload = [];

if ( ! is_wp_error( $report ) ) {
    $initial_payload = $report;
}
?>

<main class="tew-report-view" data-tew-report-container>
    <section
        class="tew-snapshot tew-snapshot--view"
        data-tew-report-view
        data-report-id="<?php echo esc_attr( get_the_ID() ); ?>"
        data-initial-report="<?php echo esc_attr( wp_json_encode( $initial_payload ) ); ?>"
        data-endpoint="<?php echo esc_url( rest_url( 'tew/v1/reports' ) ); ?>"
        data-history-endpoint="<?php echo esc_url( rest_url( 'tew/v1/reports/history' ) ); ?>"
    >
        <div class="tew-snapshot__intro">
            <?php
            // Limpiar título: extraer solo "Informe de [dominio]"
            $full_title = get_the_title();
            $clean_title = 'Informe eco-performance';
            $date_subtitle = '';
            
            // Obtener fecha del post
            $post_date = get_the_date('F j, Y \a \l\a\s g:i a');
            $date_subtitle = $post_date;
            
            // Intentar extraer dominio del título (ej: "Informe de crawla.agency")
            if ( preg_match('/informe\s+de\s+([^\s·]+)/i', $full_title, $matches) ) {
                $domain = trim($matches[1]);
                $clean_title = "Informe de {$domain}";
            } elseif ( ! empty( $report['url'] ) ) {
                // Si no hay dominio en el título, extraerlo de la URL del report
                $domain = parse_url( $report['url'], PHP_URL_HOST );
                if ( $domain ) {
                    $clean_title = "Informe de {$domain}";
                }
            }
            ?>
            
            <div class="tew-report-view__header">
                <div class="tew-report-view__header-content">
                    <span class="tew-report-view__kicker"><?php esc_html_e( 'Análisis eco-performance', 'test-eco-website' ); ?></span>
                    <h1 class="tew-report-view__title"><?php echo esc_html( $clean_title ); ?></h1>
                    <?php if ( ! empty( $date_subtitle ) ) : ?>
                        <p class="tew-report-view__subtitle">
                            <i class="ph-bold ph-calendar-blank" aria-hidden="true"></i>
                            <?php echo esc_html( $date_subtitle ); ?>
                        </p>
                    <?php endif; ?>
                    <?php if ( ! is_wp_error( $report ) && ! empty( $report['url'] ) ) : ?>
                        <p class="tew-report-view__url">
                            <i class="ph-bold ph-link" aria-hidden="true"></i>
                            <?php echo esc_html( $report['url'] ); ?>
                        </p>
                    <?php endif; ?>
                </div>
                
                <!-- Botones compartir y descargar PDF -->
                <div class="tew-report-view__toolbar" role="toolbar" aria-label="<?php esc_attr_e( 'Acciones del informe', 'test-eco-website' ); ?>">
                    <button class="tew-toolbar-btn" data-tew-pdf-trigger aria-label="<?php esc_attr_e( 'Descargar PDF', 'test-eco-website' ); ?>">
                        <i class="ph-bold ph-file-pdf" aria-hidden="true"></i>
                        <span class="tew-toolbar-btn__label"><?php esc_html_e( 'PDF', 'test-eco-website' ); ?></span>
                    </button>
                    <button class="tew-toolbar-btn" data-tew-share-trigger aria-label="<?php esc_attr_e( 'Compartir informe', 'test-eco-website' ); ?>">
                        <i class="ph-bold ph-share-network" aria-hidden="true"></i>
                        <span class="tew-toolbar-btn__label"><?php esc_html_e( 'Compartir', 'test-eco-website' ); ?></span>
                    </button>
                </div>
            </div>
            
            <p class="tew-report-view__lead"><?php esc_html_e( 'Informe eco-performance guardado para compartir con tu equipo o tus clientes.', 'test-eco-website' ); ?></p>
            
            <div class="tew-report-share" data-tew-share hidden>
                <div class="tew-share-card__header">
                    <h3><?php esc_html_e( 'Enlace listo para compartir', 'test-eco-website' ); ?></h3>
                    <p><?php esc_html_e( 'Envía este snapshot eco a tus colaboradores o clientes para que revisen el rendimiento.', 'test-eco-website' ); ?></p>
                </div>
                <a class="tew-share-link" href="<?php echo esc_url( get_permalink() ); ?>" target="_blank" rel="noopener" data-tew-share-link>
                    <i class="ph-bold ph-link" aria-hidden="true"></i>
                    <span class="tew-share-link__url"><?php echo esc_html( get_permalink() ); ?></span>
                </a>
                <div class="tew-share-actions">
                    <a class="tew-button" href="<?php echo esc_url( get_permalink() ); ?>" rel="noopener" data-tew-copy-button>
                        <?php esc_html_e( 'Copiar enlace público', 'test-eco-website' ); ?>
                    </a>
                    <a class="tew-button tew-button--ghost" href="<?php echo esc_url( home_url() ); ?>">
                        <?php esc_html_e( 'Generar un nuevo informe', 'test-eco-website' ); ?>
                    </a>
                </div>
            </div>
        </div>

        <div class="tew-snapshot__feedback" data-state="loading">
            <div class="tew-snapshot__spinner"></div>
            <p><?php esc_html_e( 'Cargando informe guardado…', 'test-eco-website' ); ?></p>
        </div>

        <div class="tew-snapshot__results" hidden>
            <div class="tew-snapshot__summary" data-tew-summary></div>
            <div class="tew-snapshot__metrics" data-tew-metrics></div>
            <div class="tew-snapshot__gallery" data-tew-gallery hidden></div>
            <div class="tew-snapshot__actions" data-tew-actions></div>
            
            <!-- CTA final elegante estilo Replanta -->
            <div class="tew-cta-final">
                <div class="tew-cta-final__content">
                    <div class="tew-cta-final__text">
                        <h3 class="tew-cta-final__title">¿Listo para replantear tu presencia digital?</h3>
                        <p class="tew-cta-final__subtitle">Analiza el impacto ambiental de tu sitio web y descubre cómo optimizarlo.</p>
                    </div>
                    <div class="tew-cta-final__actions">
                        <a class="tew-cta-button tew-cta-button--primary" href="https://replanta.net/calculadora-huella/">
                            <i class="ph-bold ph-chart-bar" aria-hidden="true"></i>
                            Analizar mi sitio web
                        </a>
                        <a class="tew-cta-button tew-cta-button--secondary" href="<?php echo esc_url( home_url( '/contacto' ) ); ?>">
                            <i class="ph-bold ph-leaf" aria-hidden="true"></i>
                            Consultoría sostenible
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="tew-report-history" data-tew-history hidden>
                <h2><?php esc_html_e( 'Histórico de snapshots', 'test-eco-website' ); ?></h2>
                <ul class="tew-report-history__list"></ul>
            </div>
        </div>
    </section>

    <?php if ( is_wp_error( $report ) ) : ?>
        <div class="tew-report-view__error">
            <p><?php echo esc_html( $report->get_error_message() ); ?></p>
        </div>
    <?php endif; ?>

    <noscript>
        <p class="tew-report-view__noscript"><?php esc_html_e( 'Activa JavaScript para visualizar los datos del informe eco-performance.', 'test-eco-website' ); ?></p>
    </noscript>
</main>

<?php
get_footer();
