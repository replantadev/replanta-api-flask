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
    }

    private function registerAreaControls(WP_Customize_Manager $wpCustomize, string $area, string $title, int $priority): void
    {
        $section = 'rlt_' . $area . '_builder';

        $wpCustomize->add_section($section, [
            'title' => $title,
            'priority' => $priority,
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
            }
        }
    }

    public static function sanitizeModule(mixed $value): string
    {
        $allowed = ['auto','widget','brand','menu_primary','menu_secondary','menu_footer','search','button','social','text','empty'];
        $key = sanitize_key((string) $value);
        return in_array($key, $allowed, true) ? $key : 'auto';
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
}
