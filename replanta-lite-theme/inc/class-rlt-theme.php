<?php
/**
 * Theme setup and WP hooks.
 *
 * @package ReplantaLite
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class RLTTheme
{
    public const ROWS = ['top', 'main', 'bottom'];
    public const AREAS = ['header', 'footer'];
    public const MAX_COLS = 4;

    public function register(): void
    {
        $this->setupThemeFeatures();

        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('widgets_init', [$this, 'registerWidgetAreas']);
        add_action('init', [$this, 'optimizeFrontend']);
        add_action('wp_enqueue_scripts', [$this, 'dequeueCoreBloat'], 100);

        (new RLTCustomizer())->register();
    }

    public function optimizeFrontend(): void
    {
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'wp_generator');
    }

    public function dequeueCoreBloat(): void
    {
        if (is_admin()) {
            return;
        }
        wp_dequeue_style('wp-block-library');
        wp_dequeue_style('wp-block-library-theme');
        wp_dequeue_style('classic-theme-styles');
        wp_dequeue_style('global-styles');
    }

    private function setupThemeFeatures(): void
    {
        add_theme_support('title-tag');
        add_theme_support('post-thumbnails');
        add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
        add_theme_support('custom-logo', [
            'height' => 80,
            'width' => 240,
            'flex-height' => true,
            'flex-width' => true,
        ]);

        register_nav_menus([
            'primary' => __('Primary Menu', 'replanta-lite'),
            'secondary' => __('Secondary Menu', 'replanta-lite'),
            'footer' => __('Footer Menu', 'replanta-lite'),
        ]);
    }

    public function enqueueAssets(): void
    {
        wp_enqueue_style(
            'replanta-lite-base',
            RLT_THEME_URI . 'assets/css/base.css',
            [],
            RLT_THEME_VERSION
        );
    }

    public function registerWidgetAreas(): void
    {
        foreach (self::AREAS as $area) {
            foreach (self::ROWS as $row) {
                for ($col = 1; $col <= self::MAX_COLS; $col++) {
                    $id = sprintf('rlt_%s_%s_%d', $area, $row, $col);
                    $name = sprintf(
                        /* translators: 1: Area name, 2: row name, 3: column number */
                        __('%1$s %2$s - Column %3$d', 'replanta-lite'),
                        ucfirst($area),
                        ucfirst($row),
                        $col
                    );

                    register_sidebar([
                        'name' => $name,
                        'id' => $id,
                        'before_widget' => '<section id="%1$s" class="widget %2$s" aria-label="Widget">',
                        'after_widget' => '</section>',
                        'before_title' => '<h3 class="widget-title">',
                        'after_title' => '</h3>',
                    ]);
                }
            }
        }
    }
}
