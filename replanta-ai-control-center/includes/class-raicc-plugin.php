<?php
/**
 * Plugin bootstrap and wiring.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class RAICCPlugin
{
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRest']);
        (new RAICCAdmin(
            new RAICCAIConnectorService(),
            new RAICCBlueprintValidator(),
            new RAICCPageService()
        ))->register();
    }

    public function registerRest(): void
    {
        (new RAICCREST(
            new RAICCAIConnectorService(),
            new RAICCBlueprintValidator(),
            new RAICCPageService()
        ))->registerRoutes();
    }
}
