<?php
/**
 * Semantic header/footer renderer with rows and columns.
 *
 * @package ReplantaLite
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class RLTLayout
{
    /** @return array<string,string> */
    private static function paletteDefaults(string $preset): array
    {
        switch ($preset) {
            case 'forest':
                return ['bg' => '#f7fbf8', 'text' => '#132218', 'accent' => '#1f6f45', 'border' => '#cfe0d5', 'header_bg' => 'rgba(247,251,248,.97)'];
            case 'ocean':
                return ['bg' => '#f6fbff', 'text' => '#112131', 'accent' => '#156f8f', 'border' => '#cfe0ea', 'header_bg' => 'rgba(246,251,255,.97)'];
            case 'contrast':
                return ['bg' => '#ffffff', 'text' => '#111111', 'accent' => '#005fcc', 'border' => '#cfcfcf', 'header_bg' => 'rgba(255,255,255,.98)'];
            case 'replanta':
            default:
                return ['bg' => '#ffffff', 'text' => '#122015', 'accent' => '#17653f', 'border' => '#d8e0da', 'header_bg' => 'rgba(255,255,255,.98)'];
        }
    }

    private static function currentPalettePreset(): string
    {
        $preset = (string) get_theme_mod('rlt_palette_preset', 'replanta');
        if (is_singular()) {
            $post = get_post(null);
            if ($post instanceof WP_Post) {
                $override = (string) get_post_meta($post->ID, RLTPageOptions::META_PALETTE, true);
                if ($override !== '' && $override !== 'global') {
                    $preset = $override;
                }
            }
        }
        return in_array($preset, ['replanta', 'forest', 'ocean', 'contrast'], true) ? $preset : 'replanta';
    }

    public static function cssVariables(): string
    {
        $preset = self::currentPalettePreset();
        $defaults = self::paletteDefaults($preset);

        $bg = (string) get_theme_mod('rlt_color_bg', '');
        $text = (string) get_theme_mod('rlt_color_text', '');
        $accent = (string) get_theme_mod('rlt_color_accent', '');
        $border = (string) get_theme_mod('rlt_color_border', '');
        $headerBg = (string) get_theme_mod('rlt_color_header_bg', '');

        $resolved = [
            'bg' => $bg !== '' ? $bg : $defaults['bg'],
            'text' => $text !== '' ? $text : $defaults['text'],
            'accent' => $accent !== '' ? $accent : $defaults['accent'],
            'border' => $border !== '' ? $border : $defaults['border'],
            'header_bg' => $headerBg !== '' ? $headerBg : $defaults['header_bg'],
        ];

        return '--rlt-bg:' . $resolved['bg']
            . ';--rlt-text:' . $resolved['text']
            . ';--rlt-accent:' . $resolved['accent']
            . ';--rlt-border:' . $resolved['border']
            . ';--rlt-header-bg:' . $resolved['header_bg']
            . ';--rlt-container:' . self::resolvedContainerWidth()
            . ';';
    }

    private static function resolvedContainerWidth(): string
    {
        if ((bool) get_theme_mod('rlt_container_full_width', false)) {
            return '100%';
        }
        $width = (int) get_theme_mod('rlt_container_max_width', 1200);
        $width = max(960, min(1680, $width));
        return $width . 'px';
    }

    private static function headerMode(): string
    {
        $mode = (string) get_theme_mod('rlt_header_mode', 'normal');
        if (is_singular()) {
            $post = get_post(null);
            if ($post instanceof WP_Post) {
                $override = (string) get_post_meta($post->ID, RLTPageOptions::META_HEADER_MODE, true);
                if ($override !== '' && $override !== 'global') {
                    $mode = $override;
                }
            }
        }
        return $mode === 'transparent' ? 'transparent' : 'normal';
    }

    private static function headerFixed(): bool
    {
        $fixed = (bool) get_theme_mod('rlt_header_fixed', false);
        if (is_singular()) {
            $post = get_post(null);
            if ($post instanceof WP_Post) {
                $override = (string) get_post_meta($post->ID, RLTPageOptions::META_HEADER_FIXED, true);
                if ($override === '1') {
                    $fixed = true;
                }
                if ($override === '0') {
                    $fixed = false;
                }
            }
        }
        return $fixed;
    }
    /** @return array<string,string> */
    private static function socialLinks(): array
    {
        $out = [];
        foreach (['x','linkedin','youtube','instagram'] as $key) {
            $url = (string) get_theme_mod('rlt_social_' . $key, '');
            if ($url !== '') {
                $out[$key] = $url;
            }
        }
        return $out;
    }

    private static function renderModule(string $module, string $area, string $row, int $col): void
    {
        switch ($module) {
            case 'empty':
                return;
            case 'brand':
                self::renderBrand();
                return;
            case 'menu_primary':
                self::renderMenu('primary', __('Primary navigation', 'replanta-lite'));
                return;
            case 'menu_secondary':
                self::renderMenu('secondary', __('Secondary navigation', 'replanta-lite'));
                return;
            case 'menu_footer':
                self::renderMenu('footer', __('Footer navigation', 'replanta-lite'));
                return;
            case 'search':
                echo '<div class="rlt-search">';
                get_search_form();
                echo '</div>';
                return;
            case 'button':
                $label = (string) get_theme_mod(sprintf('rlt_%s_%s_%d_button_label', $area, $row, $col), '');
                $url = (string) get_theme_mod(sprintf('rlt_%s_%s_%d_button_url', $area, $row, $col), '');
                if ($label === '') {
                    $label = (string) get_theme_mod('rlt_cta_label', __('Empezar', 'replanta-lite'));
                }
                if ($url === '') {
                    $url = (string) get_theme_mod('rlt_cta_url', home_url('/contacto/'));
                }
                if ($label !== '' && $url !== '') {
                    echo '<a class="rlt-cta" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
                }
                return;
            case 'social':
                $links = self::socialLinks();
                if ($links === []) {
                    return;
                }
                echo '<nav class="rlt-social" aria-label="' . esc_attr__('Social links', 'replanta-lite') . '"><ul>';
                foreach ($links as $name => $url) {
                    echo '<li><a href="' . esc_url($url) . '" rel="noopener" target="_blank">' . esc_html(strtoupper($name)) . '</a></li>';
                }
                echo '</ul></nav>';
                return;
            case 'text':
                $text = (string) get_theme_mod(sprintf('rlt_%s_%s_%d_text', $area, $row, $col), '');
                if ($text === '') {
                    $text = (string) get_theme_mod('rlt_brand_text', __('WordPress rápido y semántico para Replanta', 'replanta-lite'));
                }
                if ($text !== '') {
                    echo '<p class="rlt-brand-copy">' . esc_html($text) . '</p>';
                }
                return;
            case 'html':
                $html = (string) get_theme_mod(sprintf('rlt_%s_%s_%d_html', $area, $row, $col), '');
                if ($html !== '') {
                    echo '<div class="rlt-html-block">' . wp_kses_post($html) . '</div>';
                }
                return;
            case 'auto':
            default:
                if ($area === 'header') {
                    self::renderBrand();
                    self::renderMenu('primary', __('Primary navigation', 'replanta-lite'));
                }
                return;
        }
    }

    private static function renderMenu(string $location, string $label): void
    {
        wp_nav_menu([
            'theme_location'       => $location,
            'container'            => 'nav',
            'container_class'      => 'rlt-nav rlt-nav-' . $location,
            'container_aria_label' => $label,
            'container_id'         => $location === 'primary' ? 'rlt-nav-primary' : '',
            'fallback_cb'          => '__return_empty_string',
            'depth'                => $location === 'footer' ? 1 : 2,
        ]);
    }
    public static function containerStyleVar(): string
    {
        return '--rlt-container:' . self::resolvedContainerWidth();
    }

    public static function renderHeader(): void
    {
		$classes = ['rlt-site-header'];
		if (self::headerMode() === 'transparent') {
			$classes[] = 'rlt-is-transparent';
		}
		if (self::headerFixed()) {
			$classes[] = 'rlt-is-fixed';
		}
        echo '<header class="' . esc_attr(implode(' ', $classes)) . '" role="banner" style="' . esc_attr(self::containerStyleVar()) . '">';
        echo '<button class="rlt-menu-toggle" aria-expanded="false" aria-controls="rlt-nav-primary" aria-label="' . esc_attr__('Abrir menú de navegación', 'replanta-lite') . '">';
        echo '<span class="rlt-menu-toggle-icon" aria-hidden="true"></span>';
        echo '</button>';
        self::renderAreaRows('header');
        echo '</header>';
    }

    public static function renderFooter(): void
    {
        echo '<footer class="rlt-site-footer" role="contentinfo" style="' . esc_attr(self::containerStyleVar()) . '">';
        self::renderAreaRows('footer');
        echo '</footer>';
    }

    private static function renderAreaRows(string $area): void
    {
        foreach (RLTTheme::ROWS as $row) {
            $visible = (bool) get_theme_mod(sprintf('rlt_%s_%s_visible', $area, $row), true);
            if (!$visible) {
                continue;
            }

            $cols = (int) get_theme_mod(sprintf('rlt_%s_%s_cols', $area, $row), $row === 'main' ? 3 : 1);
            $cols = max(1, min(RLTTheme::MAX_COLS, $cols));

            $rowBg = (string) get_theme_mod(sprintf('rlt_%s_%s_bg', $area, $row), '');
            $rowPy = (int) get_theme_mod(sprintf('rlt_%s_%s_py', $area, $row), $area === 'header' ? ($row === 'main' ? 12 : 8) : ($row === 'main' ? 20 : 12));
            $rowPy = max(0, min(72, $rowPy));
            $rowStyle = 'padding-top:' . $rowPy . 'px;padding-bottom:' . $rowPy . 'px;';
            if ($rowBg !== '') {
                $rowStyle .= 'background:' . $rowBg . ';';
            }

            echo '<div class="rlt-row rlt-' . esc_attr($area) . '-' . esc_attr($row) . '" style="' . esc_attr($rowStyle) . '">';
            echo '<div class="rlt-container">';
            echo '<div class="rlt-grid cols-' . esc_attr((string) $cols) . '">';

            for ($col = 1; $col <= $cols; $col++) {
                self::renderColumn($area, $row, $col);
            }

            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
    }

    private static function renderColumn(string $area, string $row, int $col): void
    {
        $sidebarId = sprintf('rlt_%s_%s_%d', $area, $row, $col);
        $module = (string) get_theme_mod(sprintf('rlt_%s_%s_%d_module', $area, $row, $col), 'auto');

        echo '<div class="rlt-col rlt-col-' . esc_attr((string) $col) . '">';

        if ($module === 'widget' && is_active_sidebar($sidebarId)) {
            dynamic_sidebar($sidebarId);
            echo '</div>';
            return;
        }

        if ($module === 'widget' && !is_active_sidebar($sidebarId) && $area === 'footer' && $row === 'bottom' && $col === 1) {
            echo '<small class="rlt-copyright">' . esc_html(get_bloginfo('name')) . ' · ' . esc_html((string) gmdate('Y')) . '</small>';
            echo '</div>';
            return;
        }

        self::renderModule($module, $area, $row, $col);

        echo '</div>';
    }

    private static function renderBrand(): void
    {
        if (function_exists('the_custom_logo') && has_custom_logo()) {
            the_custom_logo();
            return;
        }

        $homeUrl = home_url('/');
        $name = get_bloginfo('name');

        echo '<a class="rlt-brand" href="' . esc_url($homeUrl) . '">';
        echo '<span class="rlt-brand-text">' . esc_html($name) . '</span>';
        echo '</a>';
    }
}
