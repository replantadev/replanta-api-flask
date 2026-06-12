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
        add_action('wp_head', [$this, 'printDynamicThemeCss'], 20);
        add_action('wp_footer', [$this, 'printHeaderBehaviorScript'], 99);

        (new RLTCustomizer())->register();
        (new RLTPageOptions())->register();
    }

    public function printDynamicThemeCss(): void
    {
        $vars = RLTLayout::cssVariables();
        echo '<style id="rlt-theme-vars">:root{' . esc_html($vars) . '}';
        echo '.rlt-site-header.rlt-is-fixed{position:fixed;left:0;right:0;top:0;z-index:1000;background:var(--rlt-header-bg,rgba(255,255,255,.98));backdrop-filter:saturate(150%) blur(8px);}';
        echo '.admin-bar .rlt-site-header.rlt-is-fixed{top:32px;}';
        echo '.rlt-site-header.rlt-is-transparent{position:absolute;left:0;right:0;top:0;z-index:1000;background:transparent;border-bottom-color:transparent;}';
        echo '.rlt-site-header.rlt-is-transparent.rlt-scrolled{background:var(--rlt-header-bg,rgba(255,255,255,.98));border-bottom-color:var(--rlt-border);position:fixed;}';
        echo 'body.rlt-has-fixed-header{padding-top:var(--rlt-header-offset,0px);}';
        $hideTitleGlobal = get_theme_mod('rlt_hide_page_title', 'show') === 'hide';
        $hideTitlePage = false;
        if (is_singular()) {
            $post = get_post(null);
            if ($post instanceof WP_Post) {
                $override = (string) get_post_meta($post->ID, RLTPageOptions::META_HIDE_TITLE, true);
                if ($override === 'hide') {
                    $hideTitlePage = true;
                } elseif ($override === 'show') {
                    $hideTitleGlobal = false;
                }
            }
        }
        if ($hideTitleGlobal || $hideTitlePage) {
            echo '.rlt-entry-title{display:none!important}';
        }
        echo '</style>';
    }

    public function printHeaderBehaviorScript(): void
    {
        echo '<script>(function(){';
        // Header scroll / fixed-offset behaviour
        echo 'var h=document.querySelector(".rlt-site-header");if(h){';
        echo 'var body=document.body;var fixed=h.classList.contains("rlt-is-fixed")||h.classList.contains("rlt-is-transparent");';
        echo 'if(fixed){body.classList.add("rlt-has-fixed-header");var setOff=function(){document.documentElement.style.setProperty("--rlt-header-offset",h.offsetHeight+"px");};setOff();window.addEventListener("resize",setOff,{passive:true});}';
        echo 'var onScroll=function(){if(h.classList.contains("rlt-is-transparent")){h.classList.toggle("rlt-scrolled",window.scrollY>8);}};onScroll();window.addEventListener("scroll",onScroll,{passive:true});';
        echo '}';
        // Mobile hamburger nav toggle
        echo 'var btn=document.querySelector(".rlt-menu-toggle");';
        echo 'var nav=document.getElementById("rlt-nav-primary");';
        echo 'if(btn&&nav){';
        echo 'btn.addEventListener("click",function(){';
        echo 'var open=nav.classList.toggle("rlt-nav-open");';
        echo 'btn.setAttribute("aria-expanded",String(open));';
        echo 'document.body.classList.toggle("rlt-mobile-nav-open",open);';
        echo '});';
        echo 'document.addEventListener("keydown",function(e){';
        echo 'if(e.key==="Escape"&&nav.classList.contains("rlt-nav-open")){';
        echo 'nav.classList.remove("rlt-nav-open");';
        echo 'btn.setAttribute("aria-expanded","false");';
        echo 'document.body.classList.remove("rlt-mobile-nav-open");';
        echo 'btn.focus();';
        echo '}});';
        echo 'document.addEventListener("click",function(e){';
        echo 'if(nav.classList.contains("rlt-nav-open")&&!nav.contains(e.target)&&!btn.contains(e.target)){';
        echo 'nav.classList.remove("rlt-nav-open");';
        echo 'btn.setAttribute("aria-expanded","false");';
        echo 'document.body.classList.remove("rlt-mobile-nav-open");';
        echo '}});';
        echo '}';
        echo '})();</script>';
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
        add_theme_support('customize-selective-refresh-widgets');

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
