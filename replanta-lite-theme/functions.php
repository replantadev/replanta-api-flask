<?php
/**
 * Replanta Lite Theme bootstrap.
 *
 * @package ReplantaLite
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('RLT_THEME_VERSION', '0.1.0');
define('RLT_THEME_DIR', trailingslashit(get_template_directory()));
define('RLT_THEME_URI', trailingslashit(get_template_directory_uri()));

require_once RLT_THEME_DIR . 'inc/class-rlt-customizer.php';
require_once RLT_THEME_DIR . 'inc/class-rlt-layout.php';
require_once RLT_THEME_DIR . 'inc/class-rlt-theme.php';

add_action('after_setup_theme', static function (): void {
    (new RLTTheme())->register();
});
