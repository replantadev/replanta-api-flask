<?php
/**
 * REST routes for connector status and prompt-based page creation.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class RAICCREST
{
    public const NS = 'replanta-ai/v1';

    public function __construct(
        private RAICCAIConnectorService $connectorService,
        private RAICCBlueprintValidator $validator,
        private RAICCPageService $pageService,
        private RAICCRateLimiter $rateLimiter,
        private RAICCOperationLogger $logger
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NS, '/connectors/status', [
            'methods' => 'GET',
            'callback' => [$this, 'connectorsStatus'],
            'permission_callback' => static fn(): bool => current_user_can('manage_options'),
        ]);

        register_rest_route(self::NS, '/pages/create-from-prompt', [
            'methods' => 'POST',
            'callback' => [$this, 'createFromPrompt'],
            'permission_callback' => static fn(): bool => current_user_can('edit_pages'),
        ]);

        register_rest_route(self::NS, '/pages/(?P<id>\d+)/publish', [
            'methods' => 'POST',
            'callback' => [$this, 'publishPage'],
            'permission_callback' => static fn(): bool => current_user_can('edit_pages'),
        ]);

        register_rest_route(self::NS, '/pages/(?P<id>\d+)/unpublish', [
            'methods' => 'POST',
            'callback' => [$this, 'unpublishPage'],
            'permission_callback' => static fn(): bool => current_user_can('edit_pages'),
        ]);
    }

    public function connectorsStatus(): \WP_REST_Response
    {
        return new \WP_REST_Response($this->connectorService->status());
    }

    public function createFromPrompt(\WP_REST_Request $request): \WP_REST_Response
    {
        $body = (array) $request->get_json_params();
        $prompt = trim((string) ($body['prompt'] ?? ''));
        $userId = get_current_user_id();
        $limit = $this->rateLimiter->check('rest_create_from_prompt', $userId, 12, 60);

        if (empty($limit['allowed'])) {
            $this->logger->log('rate_limited', [
                'bucket' => 'rest_create_from_prompt',
                'user_id' => $userId,
                'retry_after' => (int) ($limit['retry_after'] ?? 60),
            ]);

            return new \WP_REST_Response([
                'ok' => false,
                'error' => 'rate limit exceeded',
                'retry_after' => (int) ($limit['retry_after'] ?? 60),
            ], 429);
        }

        $status = 201;
        $response = [
            'ok' => false,
            'error' => 'unknown error',
        ];

        if ($prompt === '') {
            $status = 400;
            $response = [
                'ok' => false,
                'error' => 'prompt required',
            ];
        } else {
            $operation = 'create_page';

            $connector = $this->connectorService->execute($operation, [
                'prompt' => $prompt,
                'title' => isset($body['title']) ? (string) $body['title'] : 'Nueva pagina',
                'slug' => isset($body['slug']) ? (string) $body['slug'] : '',
                'lang' => isset($body['lang']) ? (string) $body['lang'] : 'es',
            ], [
                'user_id' => $userId,
            ]);

            $this->logger->log('connector_execution', [
                'operation' => $operation,
                'user_id' => $userId,
                'ok' => !empty($connector['ok']) ? 1 : 0,
                'connector_id' => (string) ($connector['connector_id'] ?? ''),
                'latency_ms' => (int) ($connector['latency_ms'] ?? 0),
            ]);

            if (empty($connector['ok'])) {
                $status = 400;
                $response = [
                    'ok' => false,
                    'error' => 'connector execution failed',
                    'connector' => $connector,
                ];
            } else {
                $blueprint = isset($connector['blueprint_json']) && is_array($connector['blueprint_json'])
                    ? $connector['blueprint_json']
                    : [];

                $valid = $this->validator->validate($blueprint);
                if (empty($valid['ok'])) {
                    $status = 422;
                    $response = [
                        'ok' => false,
                        'error' => 'invalid blueprint schema',
                        'validation' => $valid,
                        'connector' => [
                            'connector_id' => (string) ($connector['connector_id'] ?? ''),
                            'latency_ms' => (int) ($connector['latency_ms'] ?? 0),
                        ],
                    ];
                } else {
                    $created = $this->pageService->createPageFromBlueprint($blueprint, $prompt);
                    if (empty($created['ok'])) {
                        $status = 500;
                        $response = $created;
                    } else {
                        $status = 201;
                        $response = [
                            'ok' => true,
                            'created' => $created,
                            'connector' => [
                                'connector_id' => (string) ($connector['connector_id'] ?? ''),
                                'latency_ms' => (int) ($connector['latency_ms'] ?? 0),
                                'warnings' => isset($connector['warnings']) && is_array($connector['warnings']) ? $connector['warnings'] : [],
                            ],
                        ];
                    }
                }
            }
        }

        $this->logger->log('rest_create_from_prompt_result', [
            'user_id' => $userId,
            'http_status' => $status,
            'ok' => !empty($response['ok']) ? 1 : 0,
        ]);

        return new \WP_REST_Response($response, $status);
    }

    public function publishPage(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $limit = $this->rateLimiter->check('rest_publish', $userId, 30, 60);
        if (empty($limit['allowed'])) {
            return new \WP_REST_Response([
                'ok' => false,
                'error' => 'rate limit exceeded',
                'retry_after' => (int) ($limit['retry_after'] ?? 60),
            ], 429);
        }

        $id = (int) $request->get_param('id');
        $res = $this->pageService->setPageStatus($id, 'publish');
        $this->logger->log('rest_publish', [
            'user_id' => $userId,
            'post_id' => $id,
            'ok' => !empty($res['ok']) ? 1 : 0,
            'error' => isset($res['error']) ? (string) $res['error'] : '',
        ]);
        return new \WP_REST_Response($res, !empty($res['ok']) ? 200 : 400);
    }

    public function unpublishPage(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $limit = $this->rateLimiter->check('rest_unpublish', $userId, 30, 60);
        if (empty($limit['allowed'])) {
            return new \WP_REST_Response([
                'ok' => false,
                'error' => 'rate limit exceeded',
                'retry_after' => (int) ($limit['retry_after'] ?? 60),
            ], 429);
        }

        $id = (int) $request->get_param('id');
        $res = $this->pageService->setPageStatus($id, 'draft');
        $this->logger->log('rest_unpublish', [
            'user_id' => $userId,
            'post_id' => $id,
            'ok' => !empty($res['ok']) ? 1 : 0,
            'error' => isset($res['error']) ? (string) $res['error'] : '',
        ]);
        return new \WP_REST_Response($res, !empty($res['ok']) ? 200 : 400);
    }
}
