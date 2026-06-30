<?php
/**
 * Fires when the plugin is deleted (not just deactivated).
 * Removes all plugin data from the database.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Drop tables
$tables = [
    'replanta_embeddings',
    'replanta_messages',
    'replanta_feedback',
    'replanta_conversations',
    'replanta_indexing_jobs',
];

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" );
}

// Remove options
$options = [
    'replanta_ai_chat_general',
    'replanta_ai_chat_provider',
    'replanta_ai_chat_indexing',
    'replanta_ai_chat_behaviour',
    'replanta_ai_chat_tools',
    'replanta_ai_chat_license',
    'replanta_ai_chat_db_version',
    'replanta_ai_chat_update_info',
];

foreach ( $options as $option ) {
    delete_option( $option );
}

// Clear transients
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_replanta%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_replanta%'" );
