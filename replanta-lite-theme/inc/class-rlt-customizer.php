<?php
/**
 * Customizer controls for header/footer row and column builder.
 *
 * @package ReplantaLite
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class RLTCustomizer
{
    /** @return array<string,string> */
    private function moduleChoices(): array
    {
        return [
            'auto'           => __('Auto', 'replanta-lite'),
            'widget'         => __('Widget Area', 'replanta-lite'),
            'brand'          => __('Brand', 'replanta-lite'),
            'menu_primary'   => __('Primary Menu', 'replanta-lite'),
            'menu_secondary' => __('Secondary Menu', 'replanta-lite'),
            'menu_footer'    => __('Footer Menu', 'replanta-lite'),
            'search'         => __('Search Form', 'replanta-lite'),
            'button'         => __('CTA Button', 'replanta-lite'),
            'social'         => __('Social Links', 'replanta-lite'),
            'text'           => __('Text Block', 'replanta-lite'),
            'html'           => __('Custom HTML', 'replanta-lite'),
            'empty'          => __('Empty', 'replanta-lite'),
        ];
    }

    private function moduleDefault(string $area, string $row, int $col): string
    {
        if ($area === 'header' && $row === 'main' && $col === 1) {
            return 'brand';
        }
        if ($area === 'header' && $row === 'main' && $col === 2) {
            return 'menu_primary';
        }
        if ($area === 'header' && $row === 'main' && $col === 3) {
            return 'button';
        }
        if ($area === 'footer' && $row === 'main' && $col === 1) {
            return 'text';
        }
        if ($area === 'footer' && $row === 'main' && $col === 2) {
            return 'menu_footer';
        }
        if ($area === 'footer' && $row === 'main' && $col === 3) {
            return 'social';
        }
        return 'widget';
    }

    private function defaultRowPadding(string $area, string $row): int
    {
        if ($area === 'header') {
            return $row === 'main' ? 12 : 8;
        }

        return $row === 'main' ? 20 : 12;
    }

    public function register(): void
    {
        add_action('customize_register', [$this, 'customizeRegister']);
    }

    public function customizeRegister(WP_Customize_Manager $wpCustomize): void
    {
        $this->registerAreaControls($wpCustomize, 'header', __('Header Builder', 'replanta-lite'), 30);
        $this->registerAreaControls($wpCustomize, 'footer', __('Footer Builder', 'replanta-lite'), 31);

        $wpCustomize->add_section('rlt_branding', [
            'title' => __('Replanta Branding', 'replanta-lite'),
            'priority' => 29,
        ]);

        $wpCustomize->add_setting('rlt_cta_label', [
            'default' => __('Empezar', 'replanta-lite'),
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        $wpCustomize->add_control('rlt_cta_label', [
            'type' => 'text',
            'section' => 'rlt_branding',
            'label' => __('CTA Label', 'replanta-lite'),
        ]);

        $wpCustomize->add_setting('rlt_cta_url', [
            'default' => home_url('/contacto/'),
            'sanitize_callback' => 'esc_url_raw',
        ]);
        $wpCustomize->add_control('rlt_cta_url', [
            'type' => 'url',
            'section' => 'rlt_branding',
            'label' => __('CTA URL', 'replanta-lite'),
        ]);

        $wpCustomize->add_setting('rlt_brand_text', [
            'default' => __('WordPress rápido y semántico para Replanta', 'replanta-lite'),
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
        $wpCustomize->add_control('rlt_brand_text', [
            'type' => 'textarea',
            'section' => 'rlt_branding',
            'label' => __('Brand Text Block', 'replanta-lite'),
        ]);

        foreach (['x','linkedin','youtube','instagram'] as $social) {
            $key = 'rlt_social_' . $social;
            $wpCustomize->add_setting($key, [
                'default' => '',
                'sanitize_callback' => 'esc_url_raw',
            ]);
            $wpCustomize->add_control($key, [
                'type' => 'url',
                'section' => 'rlt_branding',
                'label' => sprintf(__('Social URL: %s', 'replanta-lite'), ucfirst($social)),
            ]);
        }

        $wpCustomize->add_section('rlt_global_layout', [
            'title' => __('Global Layout', 'replanta-lite'),
            'priority' => 32,
        ]);

        $wpCustomize->add_section('rlt_design', [
            'title' => __('Design & Header Behavior', 'replanta-lite'),
            'priority' => 33,
        ]);

        $wpCustomize->add_setting('rlt_palette_preset', [
            'default' => 'replanta',
            'sanitize_callback' => [self::class, 'sanitizePalette'],
        ]);
        $wpCustomize->add_control('rlt_palette_preset', [
            'type' => 'select',
            'section' => 'rlt_design',
            'label' => __('Palette preset', 'replanta-lite'),
            'choices' => [
                'replanta' => __('Replanta', 'replanta-lite'),
                'forest' => __('Forest', 'replanta-lite'),
                'ocean' => __('Ocean', 'replanta-lite'),
                'contrast' => __('High Contrast', 'replanta-lite'),
            ],
        ]);

        foreach ([
            'rlt_color_bg' => __('Background color', 'replanta-lite'),
            'rlt_color_text' => __('Text color', 'replanta-lite'),
            'rlt_color_accent' => __('Accent color', 'replanta-lite'),
            'rlt_color_border' => __('Border color', 'replanta-lite'),
            'rlt_color_header_bg' => __('Header background color', 'replanta-lite'),
        ] as $key => $label) {
            $wpCustomize->add_setting($key, [
                'default' => '',
                'sanitize_callback' => [self::class, 'sanitizeColor'],
            ]);
            $wpCustomize->add_control(new WP_Customize_Color_Control($wpCustomize, $key, [
                'label' => $label,
                'section' => 'rlt_design',
            ]));
        }

        $wpCustomize->add_setting('rlt_header_mode', [
            'default' => 'normal',
            'sanitize_callback' => [self::class, 'sanitizeHeaderMode'],
        ]);
        $wpCustomize->add_control('rlt_header_mode', [
            'type' => 'select',
            'section' => 'rlt_design',
            'label' => __('Header mode', 'replanta-lite'),
            'choices' => [
                'normal' => __('Normal', 'replanta-lite'),
                'transparent' => __('Transparent', 'replanta-lite'),
            ],
        ]);

        $wpCustomize->add_setting('rlt_header_fixed', [
            'default' => false,
            'sanitize_callback' => [self::class, 'sanitizeBool'],
        ]);
        $wpCustomize->add_control('rlt_header_fixed', [
            'type' => 'checkbox',
            'section' => 'rlt_design',
            'label' => __('Fixed header', 'replanta-lite'),
        ]);

        $wpCustomize->add_setting('rlt_container_max_width', [
            'default' => 1200,
            'sanitize_callback' => [self::class, 'sanitizeWidth'],
        ]);

        $wpCustomize->add_control('rlt_container_max_width', [
            'type' => 'number',
            'section' => 'rlt_global_layout',
            'label' => __('Container max width (px)', 'replanta-lite'),
            'input_attrs' => ['min' => 960, 'max' => 1680, 'step' => 10],
        ]);

        $wpCustomize->add_setting('rlt_container_full_width', [
            'default' => false,
            'sanitize_callback' => [self::class, 'sanitizeBool'],
            'transport' => 'refresh',
        ]);
        $wpCustomize->add_control('rlt_container_full_width', [
            'type' => 'checkbox',
            'section' => 'rlt_global_layout',
            'label' => __('Full width (ignora max-width, 100% del viewport)', 'replanta-lite'),
        ]);

        $wpCustomize->add_setting('rlt_hide_page_title', [
            'default' => 'show',
            'sanitize_callback' => [self::class, 'sanitizeHideTitles'],
            'transport' => 'refresh',
        ]);
        $wpCustomize->add_control('rlt_hide_page_title', [
            'type' => 'select',
            'section' => 'rlt_global_layout',
            'label' => __('Títulos de página', 'replanta-lite'),
            'choices' => [
                'show' => __('Mostrar', 'replanta-lite'),
                'hide' => __('Ocultar', 'replanta-lite'),
            ],
        ]);

        $this->registerSelectiveRefreshPartials($wpCustomize);
    }

    private function registerSelectiveRefreshPartials(WP_Customize_Manager $wpCustomize): void
    {
        if (!isset($wpCustomize->selective_refresh)) {
            return;
        }

        $sharedSettings = ['rlt_cta_label', 'rlt_cta_url', 'rlt_brand_text'];
        foreach (['x', 'linkedin', 'youtube', 'instagram'] as $s) {
            $sharedSettings[] = 'rlt_social_' . $s;
        }

        $headerSettings = array_merge($sharedSettings, ['rlt_header_mode', 'rlt_header_fixed']);
        $footerSettings = $sharedSettings;

        foreach (RLTTheme::AREAS as $area) {
            foreach (RLTTheme::ROWS as $row) {
                $rowKeys = [
                    sprintf('rlt_%s_%s_visible', $area, $row),
                    sprintf('rlt_%s_%s_cols', $area, $row),
                    sprintf('rlt_%s_%s_bg', $area, $row),
                    sprintf('rlt_%s_%s_py', $area, $row),
                ];
                for ($col = 1; $col <= RLTTheme::MAX_COLS; $col++) {
                    $rowKeys[] = sprintf('rlt_%s_%s_%d_module', $area, $row, $col);
                    $rowKeys[] = sprintf('rlt_%s_%s_%d_text', $area, $row, $col);
                    $rowKeys[] = sprintf('rlt_%s_%s_%d_button_label', $area, $row, $col);
                    $rowKeys[] = sprintf('rlt_%s_%s_%d_button_url', $area, $row, $col);
                    $rowKeys[] = sprintf('rlt_%s_%s_%d_html', $area, $row, $col);
                }
                if ($area === 'header') {
                    $headerSettings = array_merge($headerSettings, $rowKeys);
                } else {
                    $footerSettings = array_merge($footerSettings, $rowKeys);
                }
            }
        }

        $wpCustomize->selective_refresh->add_partial('rlt_site_header', [
            'selector' => '.rlt-site-header',
            'render_callback' => ['RLTLayout', 'renderHeader'],
            'container_inclusive' => true,
            'settings' => $headerSettings,
        ]);

        $wpCustomize->selective_refresh->add_partial('rlt_site_footer', [
            'selector' => '.rlt-site-footer',
            'render_callback' => ['RLTLayout', 'renderFooter'],
            'container_inclusive' => true,
            'settings' => $footerSettings,
        ]);
    }

    private function registerAreaControls(WP_Customize_Manager $wpCustomize, string $area, string $title, int $priority): void
    {
        $section = 'rlt_' . $area . '_builder';

        $wpCustomize->add_section($section, [
            'title' => $title,
            'priority' => $priority,
            'description' => __('Configura filas, columnas y contenido de cada celda para el área seleccionada.', 'replanta-lite'),
        ]);

        foreach (RLTTheme::ROWS as $row) {
            $visibleSetting = sprintf('rlt_%s_%s_visible', $area, $row);
            $colsSetting = sprintf('rlt_%s_%s_cols', $area, $row);

            $wpCustomize->add_setting($visibleSetting, [
                'default' => true,
                'sanitize_callback' => [self::class, 'sanitizeBool'],
            ]);

            $wpCustomize->add_control($visibleSetting, [
                'type' => 'checkbox',
                'section' => $section,
                'label' => sprintf(
                    /* translators: 1: row name */
                    __('Show %s row', 'replanta-lite'),
                    ucfirst($row)
                ),
            ]);

            $wpCustomize->add_setting($colsSetting, [
                'default' => $row === 'main' ? 3 : 1,
                'sanitize_callback' => [self::class, 'sanitizeCols'],
            ]);

            $wpCustomize->add_control($colsSetting, [
                'type' => 'number',
                'section' => $section,
                'label' => sprintf(
                    /* translators: 1: row name */
                    __('%s row columns', 'replanta-lite'),
                    ucfirst($row)
                ),
                'input_attrs' => ['min' => 1, 'max' => RLTTheme::MAX_COLS, 'step' => 1],
            ]);

            $rowBgSetting = sprintf('rlt_%s_%s_bg', $area, $row);
            $wpCustomize->add_setting($rowBgSetting, [
                'default' => '',
                'sanitize_callback' => [self::class, 'sanitizeColor'],
            ]);
            $wpCustomize->add_control(new WP_Customize_Color_Control($wpCustomize, $rowBgSetting, [
                'label' => sprintf(__('Background for %s row', 'replanta-lite'), ucfirst($row)),
                'section' => $section,
            ]));

            $rowPaddingSetting = sprintf('rlt_%s_%s_py', $area, $row);
            $wpCustomize->add_setting($rowPaddingSetting, [
                'default' => $this->defaultRowPadding($area, $row),
                'sanitize_callback' => [self::class, 'sanitizeSpacing'],
            ]);
            $wpCustomize->add_control($rowPaddingSetting, [
                'type' => 'number',
                'section' => $section,
                'label' => sprintf(__('Vertical padding for %s row (px)', 'replanta-lite'), ucfirst($row)),
                'input_attrs' => ['min' => 0, 'max' => 72, 'step' => 1],
            ]);

            for ($col = 1; $col <= RLTTheme::MAX_COLS; $col++) {
                $moduleSetting = sprintf('rlt_%s_%s_%d_module', $area, $row, $col);
                $wpCustomize->add_setting($moduleSetting, [
                    'default' => $this->moduleDefault($area, $row, $col),
                    'sanitize_callback' => [self::class, 'sanitizeModule'],
                ]);
                $wpCustomize->add_control($moduleSetting, [
                    'type' => 'select',
                    'section' => $section,
                    'label' => sprintf(
                        /* translators: 1: row name, 2: column number */
                        __('Module in %1$s row, column %2$d', 'replanta-lite'),
                        ucfirst($row),
                        $col
                    ),
                    'choices' => $this->moduleChoices(),
                ]);

                $textSetting = sprintf('rlt_%s_%s_%d_text', $area, $row, $col);
                $wpCustomize->add_setting($textSetting, [
                    'default' => '',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ]);
                $wpCustomize->add_control($textSetting, [
                    'type' => 'textarea',
                    'section' => $section,
                    'label' => sprintf(__('Custom text in %1$s row, column %2$d', 'replanta-lite'), ucfirst($row), $col),
                    'description' => __('Se usa cuando el módulo es Text.', 'replanta-lite'),
                ]);

                $buttonLabelSetting = sprintf('rlt_%s_%s_%d_button_label', $area, $row, $col);
                $wpCustomize->add_setting($buttonLabelSetting, [
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ]);
                $wpCustomize->add_control($buttonLabelSetting, [
                    'type' => 'text',
                    'section' => $section,
                    'label' => sprintf(__('Button label in %1$s row, column %2$d', 'replanta-lite'), ucfirst($row), $col),
                    'description' => __('Se usa cuando el módulo es Button.', 'replanta-lite'),
                ]);

                $buttonUrlSetting = sprintf('rlt_%s_%s_%d_button_url', $area, $row, $col);
                $wpCustomize->add_setting($buttonUrlSetting, [
                    'default' => '',
                    'sanitize_callback' => 'esc_url_raw',
                ]);
                $wpCustomize->add_control($buttonUrlSetting, [
                    'type' => 'url',
                    'section' => $section,
                    'label' => sprintf(__('Button URL in %1$s row, column %2$d', 'replanta-lite'), ucfirst($row), $col),
                    'description' => __('Se usa cuando el módulo es Button.', 'replanta-lite'),
                ]);

                $htmlSetting = sprintf('rlt_%s_%s_%d_html', $area, $row, $col);
                $wpCustomize->add_setting($htmlSetting, [
                    'default' => '',
                    'sanitize_callback' => [self::class, 'sanitizeHtml'],
                ]);
                $wpCustomize->add_control($htmlSetting, [
                    'type' => 'textarea',
                    'section' => $section,
                    'label' => sprintf(__('Custom HTML in %1$s row, column %2$d', 'replanta-lite'), ucfirst($row), $col),
                    'description' => __('Se usa cuando el módulo es Custom HTML.', 'replanta-lite'),
                ]);
            }
        }
    }

    public static function sanitizeModule(mixed $value): string
    {
        $allowed = ['auto','widget','brand','menu_primary','menu_secondary','menu_footer','search','button','social','text','html','empty'];
        $key = sanitize_key((string) $value);
        return in_array($key, $allowed, true) ? $key : 'auto';
    }

    public static function sanitizeHtml(mixed $value): string
    {
        return wp_kses_post((string) $value);
    }

    public static function sanitizeBool(mixed $value): bool
    {
        return (bool) $value;
    }

    public static function sanitizeCols(mixed $value): int
    {
        $int = (int) $value;
        if ($int < 1) {
            return 1;
        }
        if ($int > RLTTheme::MAX_COLS) {
            return RLTTheme::MAX_COLS;
        }
        return $int;
    }

    public static function sanitizeWidth(mixed $value): int
    {
        $int = (int) $value;
        if ($int < 960) {
            return 960;
        }
        if ($int > 1680) {
            return 1680;
        }
        return $int;
    }

    public static function sanitizeSpacing(mixed $value): int
    {
        $int = (int) $value;
        if ($int < 0) {
            return 0;
        }
        if ($int > 72) {
            return 72;
        }
        return $int;
    }

    public static function sanitizePalette(mixed $value): string
    {
        $key = sanitize_key((string) $value);
        return in_array($key, ['replanta', 'forest', 'ocean', 'contrast'], true) ? $key : 'replanta';
    }

    public static function sanitizeColor(mixed $value): string
    {
        $hex = sanitize_hex_color((string) $value);
        return is_string($hex) ? $hex : '';
    }

    public static function sanitizeHeaderMode(mixed $value): string
    {
        $key = sanitize_key((string) $value);
        return in_array($key, ['normal', 'transparent'], true) ? $key : 'normal';
    }

    public static function sanitizeHideTitles(mixed $value): string
    {
        $key = sanitize_key((string) $value);
        return in_array($key, ['show', 'hide'], true) ? $key : 'show';
    }
}
