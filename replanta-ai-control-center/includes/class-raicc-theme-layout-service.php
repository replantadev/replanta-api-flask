<?php
/**
 * Theme header/footer layout read/write service.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class RAICCThemeLayoutService
{
    /** @var array<int,string> */
    private const ROWS = ['top', 'main', 'bottom'];

    /** @var array<int,string> */
    private const AREAS = ['header', 'footer'];

    private const MAX_COLS = 4;

    /** @var array<int,string> */
    private const ALLOWED_MODULES = ['auto', 'widget', 'brand', 'menu_primary', 'menu_secondary', 'menu_footer', 'search', 'button', 'social', 'text', 'html', 'empty'];

    /** @return array<string,mixed> */
    public function currentLayout(): array
    {
        $areas = [];
        foreach (self::AREAS as $area) {
            $rows = [];
            foreach (self::ROWS as $row) {
                $rowData = [
                    'visible' => (bool) get_theme_mod(sprintf('rlt_%s_%s_visible', $area, $row), true),
                    'cols' => $this->sanitizeCols((int) get_theme_mod(sprintf('rlt_%s_%s_cols', $area, $row), $row === 'main' ? 3 : 1)),
                    'bg' => (string) get_theme_mod(sprintf('rlt_%s_%s_bg', $area, $row), ''),
                    'py' => $this->sanitizeSpacing((int) get_theme_mod(sprintf('rlt_%s_%s_py', $area, $row), $area === 'header' ? ($row === 'main' ? 12 : 8) : ($row === 'main' ? 20 : 12))),
                    'columns' => [],
                ];

                for ($col = 1; $col <= self::MAX_COLS; $col++) {
                    $rowData['columns'][(string) $col] = [
                        'module' => (string) get_theme_mod(sprintf('rlt_%s_%s_%d_module', $area, $row, $col), 'auto'),
                        'text' => (string) get_theme_mod(sprintf('rlt_%s_%s_%d_text', $area, $row, $col), ''),
                        'button_label' => (string) get_theme_mod(sprintf('rlt_%s_%s_%d_button_label', $area, $row, $col), ''),
                        'button_url' => (string) get_theme_mod(sprintf('rlt_%s_%s_%d_button_url', $area, $row, $col), ''),
                        'html' => (string) get_theme_mod(sprintf('rlt_%s_%s_%d_html', $area, $row, $col), ''),
                    ];
                }

                $rows[$row] = $rowData;
            }

            $areas[$area] = $rows;
        }

        return [
            'header_mode' => (string) get_theme_mod('rlt_header_mode', 'normal'),
            'header_fixed' => (bool) get_theme_mod('rlt_header_fixed', false),
            'areas' => $areas,
        ];
    }

    /**
     * @param array<string,mixed> $layout
     * @return array<string,mixed>
     */
    public function applyLayout(array $layout): array
    {
        if (!function_exists('set_theme_mod')) {
            return ['ok' => false, 'error' => 'theme mods API unavailable'];
        }

        $normalized = $this->normalizeLayout($layout);
        if (empty($normalized['ok'])) {
            return $normalized;
        }

        $data = isset($normalized['layout']) && is_array($normalized['layout']) ? $normalized['layout'] : [];

        set_theme_mod('rlt_header_mode', (string) ($data['header_mode'] ?? 'normal'));
        set_theme_mod('rlt_header_fixed', !empty($data['header_fixed']));

        $areas = isset($data['areas']) && is_array($data['areas']) ? $data['areas'] : [];
        foreach (self::AREAS as $area) {
            $rows = isset($areas[$area]) && is_array($areas[$area]) ? $areas[$area] : [];

            foreach (self::ROWS as $row) {
                $rowData = isset($rows[$row]) && is_array($rows[$row]) ? $rows[$row] : [];

                set_theme_mod(sprintf('rlt_%s_%s_visible', $area, $row), !empty($rowData['visible']));
                set_theme_mod(sprintf('rlt_%s_%s_cols', $area, $row), $this->sanitizeCols((int) ($rowData['cols'] ?? 1)));
                set_theme_mod(sprintf('rlt_%s_%s_bg', $area, $row), $this->sanitizeHex((string) ($rowData['bg'] ?? '')));
                set_theme_mod(sprintf('rlt_%s_%s_py', $area, $row), $this->sanitizeSpacing((int) ($rowData['py'] ?? 0)));

                $columns = isset($rowData['columns']) && is_array($rowData['columns']) ? $rowData['columns'] : [];
                for ($col = 1; $col <= self::MAX_COLS; $col++) {
                    $colData = isset($columns[(string) $col]) && is_array($columns[(string) $col]) ? $columns[(string) $col] : [];

                    set_theme_mod(sprintf('rlt_%s_%s_%d_module', $area, $row, $col), $this->sanitizeModule((string) ($colData['module'] ?? 'auto')));
                    set_theme_mod(sprintf('rlt_%s_%s_%d_text', $area, $row, $col), sanitize_textarea_field((string) ($colData['text'] ?? '')));
                    set_theme_mod(sprintf('rlt_%s_%s_%d_button_label', $area, $row, $col), sanitize_text_field((string) ($colData['button_label'] ?? '')));
                    set_theme_mod(sprintf('rlt_%s_%s_%d_button_url', $area, $row, $col), esc_url_raw((string) ($colData['button_url'] ?? '')));
                    set_theme_mod(sprintf('rlt_%s_%s_%d_html', $area, $row, $col), wp_kses_post((string) ($colData['html'] ?? '')));
                }
            }
        }

        return ['ok' => true, 'layout' => $this->currentLayout()];
    }

    /**
     * @param array<string,mixed> $layout
     * @return array<string,mixed>
     */
    public function normalizeLayout(array $layout): array
    {
        if (!isset($layout['areas']) || !is_array($layout['areas'])) {
            return ['ok' => false, 'error' => 'layout.areas is required'];
        }

        $headerMode = (string) ($layout['header_mode'] ?? 'normal');
        $headerMode = $headerMode === 'transparent' ? 'transparent' : 'normal';

        $normalized = [
            'header_mode' => $headerMode,
            'header_fixed' => !empty($layout['header_fixed']),
            'areas' => [],
        ];

        foreach (self::AREAS as $area) {
            $rows = isset($layout['areas'][$area]) && is_array($layout['areas'][$area]) ? $layout['areas'][$area] : [];
            $normalizedRows = [];

            foreach (self::ROWS as $row) {
                $rowData = isset($rows[$row]) && is_array($rows[$row]) ? $rows[$row] : [];
                $normalizedRow = [
                    'visible' => !empty($rowData['visible']),
                    'cols' => $this->sanitizeCols((int) ($rowData['cols'] ?? ($row === 'main' ? 3 : 1))),
                    'bg' => $this->sanitizeHex((string) ($rowData['bg'] ?? '')),
                    'py' => $this->sanitizeSpacing((int) ($rowData['py'] ?? ($area === 'header' ? ($row === 'main' ? 12 : 8) : ($row === 'main' ? 20 : 12)))),
                    'columns' => [],
                ];

                $columns = isset($rowData['columns']) && is_array($rowData['columns']) ? $rowData['columns'] : [];
                for ($col = 1; $col <= self::MAX_COLS; $col++) {
                    $colData = isset($columns[(string) $col]) && is_array($columns[(string) $col]) ? $columns[(string) $col] : [];
                    $normalizedRow['columns'][(string) $col] = [
                        'module' => $this->sanitizeModule((string) ($colData['module'] ?? 'auto')),
                        'text' => sanitize_textarea_field((string) ($colData['text'] ?? '')),
                        'button_label' => sanitize_text_field((string) ($colData['button_label'] ?? '')),
                        'button_url' => esc_url_raw((string) ($colData['button_url'] ?? '')),
                        'html' => wp_kses_post((string) ($colData['html'] ?? '')),
                    ];
                }

                $normalizedRows[$row] = $normalizedRow;
            }

            $normalized['areas'][$area] = $normalizedRows;
        }

        return ['ok' => true, 'layout' => $normalized];
    }

    private function sanitizeCols(int $value): int
    {
        if ($value < 1) {
            return 1;
        }
        if ($value > self::MAX_COLS) {
            return self::MAX_COLS;
        }

        return $value;
    }

    private function sanitizeSpacing(int $value): int
    {
        if ($value < 0) {
            return 0;
        }
        if ($value > 72) {
            return 72;
        }

        return $value;
    }

    private function sanitizeHex(string $value): string
    {
        $hex = sanitize_hex_color($value);
        return is_string($hex) ? $hex : '';
    }

    private function sanitizeModule(string $value): string
    {
        $module = sanitize_key($value);
        return in_array($module, self::ALLOWED_MODULES, true) ? $module : 'auto';
    }
}
