<?php
/**
 * Plugin Name: Replanta AI Control Center
 * Description: Lightweight AI operations dashboard for native pages with connector-first execution.
 * Version: 0.1.0
 * Author: Replanta
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Text Domain: replanta-ai-control-center
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('RAICC_VERSION', '0.1.0');
define('RAICC_FILE', __FILE__);
define('RAICC_DIR', plugin_dir_path(__FILE__));
define('RAICC_URL', plugin_dir_url(__FILE__));

require_once RAICC_DIR . 'includes/class-raicc-plugin.php';
require_once RAICC_DIR . 'includes/class-raicc-rest.php';
require_once RAICC_DIR . 'includes/class-raicc-ai-connector-service.php';
require_once RAICC_DIR . 'includes/class-raicc-blueprint-validator.php';
require_once RAICC_DIR . 'includes/class-raicc-page-service.php';
require_once RAICC_DIR . 'includes/class-raicc-admin.php';

add_action('plugins_loaded', static function (): void {
    (new RAICCPlugin())->register();
});
