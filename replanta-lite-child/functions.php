<?php
/**
 * Replanta Lite child theme bootstrap.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', static function (): void {
    wp_enqueue_style(
        'replanta-lite-parent',
        get_template_directory_uri() . '/style.css',
        [],
        null
    );

    wp_enqueue_style(
        'replanta-lite-child',
        get_stylesheet_uri(),
        ['replanta-lite-parent'],
        wp_get_theme()->get('Version')
    );

    // Reuse the project global kit to preserve current visual output after migration.
    wp_enqueue_style(
        'replanta-global-kit',
        get_stylesheet_directory_uri() . '/assets/css/global.css',
        ['replanta-lite-child'],
        file_exists(get_stylesheet_directory() . '/assets/css/global.css')
            ? (string) filemtime(get_stylesheet_directory() . '/assets/css/global.css')
            : null
    );

    wp_enqueue_script(
        'phosphor-icons',
        'https://unpkg.com/@phosphor-icons/web@2.1.1',
        [],
        null,
        false
    );
}, 20);
