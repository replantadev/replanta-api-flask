<?php
/**
 * Exportador de páginas para recuperar front/ desde WordPress.
 *
 * Uso:
 * 1. Subir este archivo a la raíz del WordPress (junto a wp-load.php).
 * 2. Abrir en navegador con ?pass=front_YYYYMMDD_replanta
 * 3. Exportar todas o una selección y descargar el ZIP.
 */

define('EXPORT_FRONT_PASSWORD', 'front_' . gmdate('Ymd') . '_replanta');

if (!isset($_GET['pass']) || $_GET['pass'] !== EXPORT_FRONT_PASSWORD) {
    die('Acceso denegado. Usa ?pass=' . EXPORT_FRONT_PASSWORD);
}

// Capturar cualquier salida de WordPress (hooks, plugins) para no corromper el ZIP
ob_start();

$wp_load = __DIR__ . '/wp-load.php';
if (!file_exists($wp_load)) {
    ob_end_clean();
    die('No se encontró wp-load.php en este directorio.');
}

require_once $wp_load;

if (!current_user_can('manage_options')) {
    ob_end_clean();
    die('Requiere permisos de administrador.');
}

if (!empty($_GET['debug']) || !empty($_POST['debug_mode'])) {
    @ini_set('display_errors', '1');
    @ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

function front_exporter_last_fatal_message()
{
    $error = error_get_last();
    if (!$error) {
        return '';
    }

    $fatal_types = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($error['type'], $fatal_types, true)) {
        return '';
    }

    return sprintf(
        'Fatal: %s in %s:%d',
        $error['message'],
        $error['file'],
        (int) $error['line']
    );
}

function front_exporter_slug($post)
{
    $slug = sanitize_title($post->post_name ?: $post->post_title ?: ('page-' . $post->ID));

    return $slug ?: ('page-' . $post->ID);
}

function front_exporter_is_elementor_page($post_id)
{
    return (bool) get_post_meta($post_id, '_elementor_edit_mode', true)
        || (bool) get_post_meta($post_id, '_elementor_data', true);
}

function front_exporter_render_html($post)
{
    if (
        front_exporter_is_elementor_page($post->ID)
        && class_exists('\\Elementor\\Plugin')
    ) {
        $html = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display($post->ID, true);
        if (is_string($html) && trim($html) !== '') {
            return $html;
        }
    }

    return apply_filters('the_content', $post->post_content);
}

function front_exporter_collect_css($post)
{
    $chunks = [];
    $uploads = wp_get_upload_dir();

    if (!empty($uploads['basedir'])) {
        $base_dir = trailingslashit($uploads['basedir']) . 'elementor/css/';
        $candidate_files = [
            $base_dir . 'global.css',
            $base_dir . 'post-' . $post->ID . '.css',
        ];

        $kit_id = (int) get_option('elementor_active_kit');
        if ($kit_id > 0) {
            $candidate_files[] = $base_dir . 'post-' . $kit_id . '.css';
        }

        foreach ($candidate_files as $candidate_file) {
            if (is_readable($candidate_file)) {
                $css = file_get_contents($candidate_file);
                if (is_string($css) && trim($css) !== '') {
                    $chunks[] = "/* Source: " . basename($candidate_file) . " */\n" . trim($css);
                }
            }
        }
    }

    $page_settings = get_post_meta($post->ID, '_elementor_page_settings', true);
    if (is_array($page_settings) && !empty($page_settings['custom_css'])) {
        $chunks[] = "/* Source: _elementor_page_settings.custom_css */\n" . trim((string) $page_settings['custom_css']);
    }

    $document_css = get_post_meta($post->ID, '_elementor_css', true);
    if (is_array($document_css) && !empty($document_css['css'])) {
        $chunks[] = "/* Source: _elementor_css */\n" . trim((string) $document_css['css']);
    }

    $css = implode("\n\n", array_filter($chunks));

    return trim($css);
}

function front_exporter_collect_stylesheet_urls()
{
    $urls = [];

    $stylesheet_uri = get_stylesheet_uri();
    if ($stylesheet_uri) {
        $urls[] = $stylesheet_uri;
    }

    if (defined('ELEMENTOR_VERSION') && defined('ELEMENTOR__FILE')) {
        $urls[] = plugins_url('assets/css/frontend.min.css', ELEMENTOR__FILE);
        $urls[] = plugins_url('assets/lib/swiper/v8/css/swiper.min.css', ELEMENTOR__FILE);
    }

    if (defined('ELEMENTOR_PRO_VERSION') && defined('ELEMENTOR_PRO_PLUGIN_BASE')) {
        $elementor_pro_file = defined('ELEMENTOR_PRO__FILE') ? ELEMENTOR_PRO__FILE : (defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR . '/' . ELEMENTOR_PRO_PLUGIN_BASE : false);
        if ($elementor_pro_file) {
            $urls[] = plugins_url('assets/css/frontend.min.css', $elementor_pro_file);
        }
    }

    return array_values(array_unique(array_filter($urls)));
}

function front_exporter_build_full_html($post, $content_html, $css)
{
    $title = wp_strip_all_tags(get_the_title($post));
    $permalink = get_permalink($post);
    $stylesheet_urls = front_exporter_collect_stylesheet_urls();
    $links = [];

    foreach ($stylesheet_urls as $stylesheet_url) {
        $links[] = '<link rel="stylesheet" href="' . esc_url($stylesheet_url) . '">';
    }

    $head = implode("\n    ", $links);
    $inline_css = $css !== '' ? "\n    <style>\n" . $css . "\n    </style>" : '';

    return "<!DOCTYPE html>\n"
        . "<html lang=\"" . esc_attr(get_bloginfo('language')) . "\">\n"
        . "<head>\n"
        . "    <meta charset=\"utf-8\">\n"
        . "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n"
        . "    <title>" . esc_html($title) . "</title>\n"
        . "    <meta name=\"x-export-source\" content=\"" . esc_url($permalink) . "\">\n"
        . ($head !== '' ? "    " . $head . "\n" : '')
        . $inline_css
        . "\n</head>\n"
        . "<body class=\"front-export\">\n"
        . "    <main data-post-id=\"" . (int) $post->ID . "\" data-slug=\"" . esc_attr(front_exporter_slug($post)) . "\">\n"
        . $content_html
        . "\n    </main>\n"
        . "</body>\n"
        . "</html>\n";
}

function front_exporter_collect_posts()
{
    return get_posts([
        'post_type' => ['page', 'post'],
        'post_status' => ['publish', 'draft', 'private'],
        'posts_per_page' => -1,
        'orderby' => 'menu_order title',
        'order' => 'ASC',
        'meta_query' => [
            'relation' => 'OR',
            [
                'key' => '_elementor_data',
                'compare' => 'EXISTS',
            ],
            [
                'key' => '_elementor_edit_mode',
                'compare' => 'EXISTS',
            ],
        ],
    ]);
}

function front_exporter_rrmdir($dir)
{
    if (!is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    if (!is_array($items)) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            front_exporter_rrmdir($path);
        } else {
            @unlink($path);
        }
    }

    @rmdir($dir);
}

function front_exporter_tempnam($prefix)
{
    if (function_exists('wp_tempnam')) {
        $tmp = wp_tempnam($prefix);
        if ($tmp) {
            return $tmp;
        }
    }

    $base = function_exists('get_temp_dir') ? get_temp_dir() : sys_get_temp_dir();
    if (!is_dir($base) || !is_writable($base)) {
        $uploads = wp_get_upload_dir();
        $base = !empty($uploads['basedir']) ? $uploads['basedir'] : __DIR__;
    }

    $tmp = tempnam($base, $prefix . '-');
    return $tmp ?: false;
}

function front_exporter_send_zip($selected_ids)
{
    if (!class_exists('ZipArchive')) {
        wp_die('ZipArchive no está disponible en este servidor.');
    }

    $posts = get_posts([
        'post_type' => ['page', 'post'],
        'post_status' => ['publish', 'draft', 'private'],
        'posts_per_page' => -1,
        'post__in' => $selected_ids,
        'orderby' => 'post__in',
    ]);

    if (!$posts) {
        wp_die('No hay páginas para exportar.');
    }

    $tmp = front_exporter_tempnam('front-export');
    if (!$tmp) {
        wp_die('No se pudo crear el archivo temporal para el ZIP.');
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }

    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        @unlink($tmp);
        wp_die('No se pudo abrir el ZIP para escritura.');
    }

    // Compatibilidad Windows: crear carpeta front/ explícita y .keep
    $zip->addEmptyDir('front/');
    $zip->addFromString('front/.keep', "");

    $timestamp = gmdate('Ymd-His');
    $manifest = [];

    foreach ($posts as $post) {
        $slug = front_exporter_slug($post);
        $content_html = front_exporter_render_html($post);
        $css = front_exporter_collect_css($post);
        $full_html = front_exporter_build_full_html($post, $content_html, $css);

        $zip->addFromString('front/' . $slug . '.html', $full_html);
        $zip->addFromString('front/' . $slug . '.content.html', $content_html);
        if ($css !== '') {
            $zip->addFromString('front/' . $slug . '.css', $css . "\n");
        }

        $manifest[] = [
            'id' => (int) $post->ID,
            'slug' => $slug,
            'title' => get_the_title($post),
            'status' => $post->post_status,
            'type' => $post->post_type,
            'url' => get_permalink($post),
            'elementor' => front_exporter_is_elementor_page($post->ID),
        ];
    }

    $zip->addFromString(
        'front/manifest.json',
        wp_json_encode([
            'generated_at' => gmdate('c'),
            'site' => home_url('/'),
            'count' => count($manifest),
            'pages' => $manifest,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );

    $zip->addFromString(
        'front/README.txt',
        "Export generado desde WordPress para reconstruir front/.\n\n"
        . "Archivos:\n"
        . "- slug.html: versión standalone con <head>, CSS inline y solo el contenido de la página.\n"
        . "- slug.content.html: solo el HTML renderizado de Elementor/contenido.\n"
        . "- slug.css: CSS compilado de Elementor detectado para esa página.\n"
        . "- manifest.json: índice de páginas exportadas.\n"
    );

    $zip->close();

    // Descartar cualquier salida acumulada de WordPress antes de enviar el ZIP
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="front-export-' . $timestamp . '.zip"');
    header('Content-Length: ' . filesize($tmp));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: public');
    header('Expires: 0');
    readfile($tmp);
    @unlink($tmp);
    exit;
}

$export_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_front_pages'])) {
    try {
        check_admin_referer('front_export_pages');

        $selected_ids = array_map('intval', (array) ($_POST['post_ids'] ?? []));
        $selected_ids = array_values(array_filter($selected_ids));

        if (empty($selected_ids)) {
            throw new RuntimeException('Selecciona al menos una página para exportar.');
        }

        front_exporter_send_zip($selected_ids);
    } catch (Throwable $e) {
        $export_error = $e->getMessage();
    }

    $fatal = front_exporter_last_fatal_message();
    if ($fatal !== '') {
        $export_error = $fatal;
    }
}

$posts = front_exporter_collect_posts();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Exportar front</title>
    <style>
        body {
            background: #111827;
            color: #e5e7eb;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            margin: 0;
            padding: 32px;
        }
        .wrap {
            max-width: 1100px;
            margin: 0 auto;
        }
        .panel {
            background: #1f2937;
            border: 1px solid #374151;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        h1, h2 {
            margin-top: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px 12px;
            border-bottom: 1px solid #374151;
            text-align: left;
            vertical-align: top;
        }
        th {
            color: #9ca3af;
            font-size: 12px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .pill {
            display: inline-block;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 12px;
            background: #0f766e;
            color: #ecfeff;
        }
        .muted {
            color: #9ca3af;
        }
        .actions {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-top: 16px;
        }
        button {
            background: #10b981;
            border: 0;
            border-radius: 10px;
            color: #052e16;
            cursor: pointer;
            font-size: 15px;
            font-weight: 700;
            padding: 12px 18px;
        }
        button:hover {
            background: #34d399;
        }
        a {
            color: #93c5fd;
        }
        .small {
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="panel">
            <h1>Exportar páginas a front/</h1>
            <p>
                Este exportador genera un ZIP con páginas renderizadas de Elementor sin cabecera ni pie de Astra,
                más el CSS detectado de Elementor para cada una.
            </p>
            <p class="small muted">
                Contraseña de hoy: <?php echo esc_html(EXPORT_FRONT_PASSWORD); ?>
            </p>
        </div>

        <div class="panel">
            <h2>Páginas detectadas</h2>
            <?php if ($export_error !== '') : ?>
                <p style="background:#7f1d1d;color:#fecaca;border:1px solid #b91c1c;padding:10px 12px;border-radius:8px;">
                    Error de exportación: <?php echo esc_html($export_error); ?>
                </p>
            <?php endif; ?>
            <form method="post">
                <?php wp_nonce_field('front_export_pages'); ?>
                <input type="hidden" name="export_front_pages" value="1">
                <input type="hidden" name="debug_mode" value="1">
                <table>
                    <thead>
                        <tr>
                            <th>Exportar</th>
                            <th>Título</th>
                            <th>Slug</th>
                            <th>Estado</th>
                            <th>Tipo</th>
                            <th>Ver</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($posts)) : ?>
                        <tr>
                            <td colspan="6">No se encontraron páginas con Elementor.</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($posts as $post) : ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="post_ids[]" value="<?php echo (int) $post->ID; ?>" checked>
                                </td>
                                <td>
                                    <strong><?php echo esc_html(get_the_title($post)); ?></strong><br>
                                    <span class="muted small">ID <?php echo (int) $post->ID; ?></span>
                                </td>
                                <td><?php echo esc_html(front_exporter_slug($post)); ?></td>
                                <td><span class="pill"><?php echo esc_html($post->post_status); ?></span></td>
                                <td><?php echo esc_html($post->post_type); ?></td>
                                <td><a href="<?php echo esc_url(get_permalink($post)); ?>" target="_blank" rel="noopener">Abrir</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
                <div class="actions">
                    <button type="submit">Descargar ZIP</button>
                    <span class="muted small">Se generará una carpeta front/ dentro del ZIP.</span>
                </div>
            </form>
        </div>
    </div>
</body>
</html>