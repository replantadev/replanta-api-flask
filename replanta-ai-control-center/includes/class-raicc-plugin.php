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
    private ?RAICCAIConnectorService $connectorService = null;
    private ?RAICCBlueprintValidator $validator = null;
    private ?RAICCRateLimiter $rateLimiter = null;
    private ?RAICCOperationLogger $logger = null;
    private ?RAICCPublishGateValidator $publishGateValidator = null;
    private ?RAICCPageService $pageService = null;
    private ?RAICCThemeLayoutService $themeLayoutService = null;
    private ?RAICCElementorMigrationService $elementorMigrationService = null;

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRest']);
        (new RAICCAdmin(
            $this->connectorService(),
            $this->validator(),
            $this->pageService(),
            $this->rateLimiter(),
            $this->logger(),
            $this->themeLayoutService(),
            $this->elementorMigrationService()
        ))->register();
    }

    public function registerRest(): void
    {
        (new RAICCREST(
            $this->connectorService(),
            $this->validator(),
            $this->pageService(),
            $this->rateLimiter(),
            $this->logger(),
            $this->themeLayoutService()
        ))->registerRoutes();
    }

    private function connectorService(): RAICCAIConnectorService
    {
        if (!$this->connectorService) {
            $this->connectorService = new RAICCAIConnectorService();
        }

        return $this->connectorService;
    }

    private function validator(): RAICCBlueprintValidator
    {
        if (!$this->validator) {
            $this->validator = new RAICCBlueprintValidator();
        }

        return $this->validator;
    }

    private function rateLimiter(): RAICCRateLimiter
    {
        if (!$this->rateLimiter) {
            $this->rateLimiter = new RAICCRateLimiter();
        }

        return $this->rateLimiter;
    }

    private function logger(): RAICCOperationLogger
    {
        if (!$this->logger) {
            $this->logger = new RAICCOperationLogger();
        }

        return $this->logger;
    }

    private function publishGateValidator(): RAICCPublishGateValidator
    {
        if (!$this->publishGateValidator) {
            $this->publishGateValidator = new RAICCPublishGateValidator();
        }

        return $this->publishGateValidator;
    }

    private function pageService(): RAICCPageService
    {
        if (!$this->pageService) {
            $this->pageService = new RAICCPageService($this->publishGateValidator(), $this->logger());
        }

        return $this->pageService;
    }

    private function themeLayoutService(): RAICCThemeLayoutService
    {
        if (!$this->themeLayoutService) {
            $this->themeLayoutService = new RAICCThemeLayoutService();
        }

        return $this->themeLayoutService;
    }

    private function elementorMigrationService(): RAICCElementorMigrationService
    {
        if (!$this->elementorMigrationService) {
            $this->elementorMigrationService = new RAICCElementorMigrationService($this->logger());
        }

        return $this->elementorMigrationService;
    }
}
