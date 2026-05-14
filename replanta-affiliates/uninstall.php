<?php
/**
 * Fired when the plugin is deleted via WP admin.
 *
 * @package Replanta_Affiliates
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

require_once __DIR__ . '/includes/class-db.php';
Raff_DB::uninstall();
