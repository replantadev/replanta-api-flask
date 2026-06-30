<?php
/**
 * Security Configuration for Replanta Hub
 * 
 * This file should contain sensitive configuration and be excluded from version control
 * Copy config-sample.php to config.php and update with your values
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// GitHub Token for Auto-Updates (Private Repository)
// Get your token from: https://github.com/settings/tokens
// Required scopes: repo (for private repositories)
if (!defined('RPHUB_GITHUB_TOKEN')) {
    define('RPHUB_GITHUB_TOKEN', ''); // Add your token here
}

// Optional override for update repository URL and branch.
if (!defined('RPHUB_GITHUB_REPO_URL')) {
    define('RPHUB_GITHUB_REPO_URL', 'https://github.com/replantadev/hub/');
}

if (!defined('RPHUB_GITHUB_BRANCH')) {
    define('RPHUB_GITHUB_BRANCH', 'main');
}

// API Rate Limiting Configuration
if (!defined('RPHUB_API_RATE_LIMIT')) {
    define('RPHUB_API_RATE_LIMIT', 60); // Requests per minute
}

// Debug Mode
if (!defined('RPHUB_DEBUG')) {
    define('RPHUB_DEBUG', false);
}

// Error Logging Level
if (!defined('RPHUB_LOG_LEVEL')) {
    define('RPHUB_LOG_LEVEL', 'error'); // debug, info, warning, error, critical
}

// Database Cache TTL (seconds)
if (!defined('RPHUB_CACHE_TTL')) {
    define('RPHUB_CACHE_TTL', 3600); // 1 hour
}

// External API Timeout (seconds)
if (!defined('RPHUB_API_TIMEOUT')) {
    define('RPHUB_API_TIMEOUT', 30);
}

// CyberPanel API for automated provisioning (Plan Cedro)
if (!defined('RPHUB_CYBERPANEL_URL')) {
    define('RPHUB_CYBERPANEL_URL', 'https://178.105.220.233:8090');
}
if (!defined('RPHUB_CYBERPANEL_ADMIN')) {
    define('RPHUB_CYBERPANEL_ADMIN', '');
}
if (!defined('RPHUB_CYBERPANEL_PASS')) {
    define('RPHUB_CYBERPANEL_PASS', '');
}

// Upmind webhook: shared secret (set in Upmind > Settings > Webhooks as custom header X-Upmind-Secret)
if (!defined('RPHUB_UPMIND_SECRET')) {
    define('RPHUB_UPMIND_SECRET', '');
}

// Upmind product_id for Plan Cedro (find it in Upmind > Products > Plan Cedro > URL/ID)
if (!defined('RPHUB_UPMIND_CEDRO_PRODUCT_ID')) {
    define('RPHUB_UPMIND_CEDRO_PRODUCT_ID', '');
}
