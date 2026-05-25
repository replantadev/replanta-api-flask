<?php
/**
 * Connector-first AI execution service.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class RAICCAIConnectorService
{
    public const OPTION_SETTINGS = 'raicc_settings';

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function execute(string $operation, array $payload, array $context = []): array
    {
        $start = microtime(true);

        $activeConnector = $this->activeConnector();

        // Connector-first path via WP hooks (bridge for WP7 connector APIs).
        $connectorResult = apply_filters(
            'raicc_connector_execute',
            null,
            $activeConnector,
            $operation,
            $payload,
            $context
        );

        if (is_array($connectorResult)) {
            return $this->normalizeResult($connectorResult, $activeConnector, (int) round((microtime(true) - $start) * 1000));
        }

        // Fallback adapter: deterministic template-based draft.
        $fallback = $this->fallbackGenerateBlueprint($operation, $payload, $context);

        return $this->normalizeResult($fallback, 'fallback-local', (int) round((microtime(true) - $start) * 1000));
    }

    /** @return array<string,mixed> */
    public function status(): array
    {
        $active = $this->activeConnector();
        $caps = apply_filters('raicc_connector_capabilities', [], $active);
        $health = apply_filters('raicc_connector_healthcheck', ['ok' => false, 'message' => 'No connector healthcheck available'], $active);

        return [
            'ok' => true,
            'active_connector' => $active,
            'connector_health' => is_array($health) ? $health : ['ok' => false, 'message' => 'Invalid health payload'],
            'capabilities' => is_array($caps) ? $caps : [],
            'mode' => $active === 'none' ? 'fallback' : 'connector-first',
        ];
    }

    private function activeConnector(): string
    {
        $settings = (array) get_option(self::OPTION_SETTINGS, []);
        $connector = isset($settings['active_connector']) ? sanitize_key((string) $settings['active_connector']) : '';
        return $connector !== '' ? $connector : 'none';
    }

    /**
     * @param array<string,mixed> $result
     * @return array<string,mixed>
     */
    private function normalizeResult(array $result, string $connectorId, int $latencyMs): array
    {
        $ok = !empty($result['ok']);
        $blueprint = isset($result['blueprint_json']) && is_array($result['blueprint_json'])
            ? $result['blueprint_json']
            : [];
        $layout = isset($result['layout_json']) && is_array($result['layout_json'])
            ? $result['layout_json']
            : [];

        return [
            'ok' => $ok,
            'connector_id' => $connectorId,
            'latency_ms' => $latencyMs,
            'model_id' => isset($result['model_id']) ? (string) $result['model_id'] : '',
            'token_usage' => isset($result['token_usage']) && is_array($result['token_usage']) ? $result['token_usage'] : [],
            'warnings' => isset($result['warnings']) && is_array($result['warnings']) ? $result['warnings'] : [],
            'notes' => isset($result['notes']) ? (string) $result['notes'] : '',
            'blueprint_json' => $blueprint,
            'layout_json' => $layout,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private function fallbackGenerateBlueprint(string $operation, array $payload, array $context): array
    {
        $prompt = trim((string) ($payload['prompt'] ?? ''));
        $lang = trim((string) ($payload['lang'] ?? 'es'));
        $title = trim((string) ($payload['title'] ?? 'Nueva pagina'));

        if ($operation === 'theme_layout') {
            return [
                'ok' => true,
                'warnings' => ['Generated in fallback mode without external connector'],
                'notes' => 'Fallback theme layout generated',
                'layout_json' => $this->fallbackGenerateThemeLayout($prompt),
            ];
        }

        if ($operation !== 'create_page') {
            return [
                'ok' => false,
                'warnings' => ['Fallback adapter only supports create_page currently'],
                'notes' => 'Unsupported operation in fallback',
                'blueprint_json' => [],
            ];
        }

        return [
            'ok' => true,
            'warnings' => ['Generated in fallback mode without external connector'],
            'notes' => 'Fallback blueprint generated',
            'blueprint_json' => [
                'version' => '1.0',
                'lang' => $lang,
                'page' => [
                    'title' => $title,
                    'slug' => sanitize_title((string) ($payload['slug'] ?? $title)),
                    'description' => mb_substr($prompt !== '' ? $prompt : $title, 0, 155),
                    'canonical' => '',
                ],
                'sections' => [
                    [
                        'id' => 'hero-1',
                        'type' => 'hero',
                        'heading' => $title,
                        'body_markdown' => $prompt !== '' ? $prompt : 'Escribe aqui el resumen principal de la pagina.',
                        'items' => [],
                        'aria_label' => 'Hero principal',
                    ],
                    [
                        'id' => 'content-1',
                        'type' => 'content',
                        'heading' => 'Contenido',
                        'body_markdown' => 'Desarrolla el contenido principal con foco en claridad y accesibilidad.',
                        'items' => [],
                        'aria_label' => 'Contenido principal',
                    ],
                    [
                        'id' => 'cta-1',
                        'type' => 'cta',
                        'heading' => 'Siguiente paso',
                        'body_markdown' => 'Incluye un llamado a la accion claro y medible.',
                        'items' => [],
                        'aria_label' => 'Llamado a la accion',
                    ],
                ],
                'seo' => [
                    'meta_title' => $title,
                    'meta_description' => mb_substr($prompt !== '' ? $prompt : $title, 0, 155),
                    'og_title' => $title,
                    'og_description' => mb_substr($prompt !== '' ? $prompt : $title, 0, 155),
                ],
                'a11y' => [
                    'skip_link_label' => 'Saltar al contenido',
                    'landmarks_ok' => true,
                ],
                'context' => [
                    'source' => 'fallback-local',
                    'requested_by' => isset($context['user_id']) ? (int) $context['user_id'] : 0,
                ],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function fallbackGenerateThemeLayout(string $prompt): array
    {
        $promptLower = function_exists('mb_strtolower') ? mb_strtolower($prompt) : strtolower($prompt);
        $transparent = str_contains($promptLower, 'transparent');
        $fixed = str_contains($promptLower, 'fixed') || str_contains($promptLower, 'sticky');

        return [
            'header_mode' => $transparent ? 'transparent' : 'normal',
            'header_fixed' => $fixed,
            'areas' => [
                'header' => [
                    'top' => [
                        'visible' => false,
                        'cols' => 1,
                        'bg' => '',
                        'py' => 6,
                        'columns' => [
                            '1' => ['module' => 'empty', 'text' => '', 'button_label' => '', 'button_url' => ''],
                            '2' => ['module' => 'empty', 'text' => '', 'button_label' => '', 'button_url' => ''],
                            '3' => ['module' => 'empty', 'text' => '', 'button_label' => '', 'button_url' => ''],
                            '4' => ['module' => 'empty', 'text' => '', 'button_label' => '', 'button_url' => ''],
                        ],
                    ],
                    'main' => [
                        'visible' => true,
                        'cols' => 3,
                        'bg' => '',
                        'py' => 12,
                        'columns' => [
                            '1' => ['module' => 'brand', 'text' => '', 'button_label' => '', 'button_url' => ''],
                            '2' => ['module' => 'menu_primary', 'text' => '', 'button_label' => '', 'button_url' => ''],
                            '3' => ['module' => 'button', 'text' => '', 'button_label' => 'Contactar', 'button_url' => home_url('/contacto/')],
                            '4' => ['module' => 'empty', 'text' => '', 'button_label' => '', 'button_url' => ''],
                        ],
                    ],
                    'bottom' => [
                        'visible' => false,
                        'cols' => 1,
                        'bg' => '',
                        'py' => 6,
                        'columns' => [
                            '1' => ['module' => 'empty', 'text' => '', 'button_label' => '', 'button_url' => ''],
                            '2' => ['module' => 'empty', 'text' => '', 'button_label' => '', 'button_url' => ''],
                            '3' => ['module' => 'empty', 'text' => '', 'button_label' => '', 'button_url' => ''],
                            '4' => ['module' => 'empty', 'text' => '', 'button_label' => '', 'button_url' => ''],
                        ],
                    ],
                ],
                'footer' => [
                    'top' => [
                        'visible' => false,
                        'cols' => 1,
                        'bg' => '',
                        'py' => 10,
                        'columns' => [
                            '1' => ['module' => 'empty', 'text' => '', 'button_label' => '', 'button_url' => ''],
                            '2' => ['module' => 'empty', 'text' => '', 'button_label' => '', 'button_url' => ''],
                            '3' => ['module' => 'empty', 'text' => '', 'button_label' => '', 'button_url' => ''],
                            '4' => ['module' => 'empty', 'text' => '', 'button_label' => '', 'button_url' => ''],
                        ],
                    ],
                    'main' => [
                        'visible' => true,
                        'cols' => 3,
                        'bg' => '',
                        'py' => 18,
                        'columns' => [
                            '1' => ['module' => 'text', 'text' => 'WordPress rápido y semántico para Replanta', 'button_label' => '', 'button_url' => ''],
                            '2' => ['module' => 'menu_footer', 'text' => '', 'button_label' => '', 'button_url' => ''],
                            '3' => ['module' => 'social', 'text' => '', 'button_label' => '', 'button_url' => ''],
                            '4' => ['module' => 'empty', 'text' => '', 'button_label' => '', 'button_url' => ''],
                        ],
                    ],
                    'bottom' => [
                        'visible' => true,
                        'cols' => 1,
                        'bg' => '',
                        'py' => 10,
                        'columns' => [
                            '1' => ['module' => 'text', 'text' => get_bloginfo('name') . ' · ' . gmdate('Y'), 'button_label' => '', 'button_url' => ''],
                            '2' => ['module' => 'empty', 'text' => '', 'button_label' => '', 'button_url' => ''],
                            '3' => ['module' => 'empty', 'text' => '', 'button_label' => '', 'button_url' => ''],
                            '4' => ['module' => 'empty', 'text' => '', 'button_label' => '', 'button_url' => ''],
                        ],
                    ],
                ],
            ],
        ];
    }
}
