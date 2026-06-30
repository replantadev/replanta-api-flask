<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$tables = [
    'rphub_sites',
    'rphub_tasks',
    'rphub_reports',
    'rphub_backups',
    'rphub_maintenance_logs',
    'rphub_notifications',
    'rphub_activities',
    'rphub_wptoolkit_vulnerabilities',
    'rphub_wptoolkit_updates',
    'rphub_backuply_jobs',
    'rphub_pagespeed_reports',
    'rphub_cloudflare_analytics',
    'rphub_cloudflare_security_events',
    'rphub_comprehensive_reports',
    'rphub_automation_tasks',
    'rphub_automation_workflows',
    'rphub_site_health',
    'rphub_performance_metrics',
    'rphub_site_meta',
    'rphub_security_logs',
    'rphub_api_tokens',
    'rphub_api_logs',
    'rphub_webhook_events',
    'rphub_blocked_ips',
    'rphub_bulk_action_logs',
    'rphub_alert_rules',
    'rphub_analytics_rum',
    'rphub_analytics_web_vitals',
    'rphub_analytics_ga',
    'rphub_analytics_search_console',
    'rphub_analytics_benchmarks',
    'rphub_analytics_insights',
    'rphub_api_rate_limits',
    'rphub_api_quotas',
    'rphub_api_permissions',
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
}

// Clean up all plugin options
$options = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'rphub_%'");
foreach ($options as $opt) {
    delete_option($opt);
}

// Clean up transients
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_rphub_%' OR option_name LIKE '_transient_timeout_rphub_%'");

// Clear scheduled events
$cron_hooks = [
    'rphub_daily_check',
    'rphub_hourly_monitoring',
    'rphub_litespeed_optimize',
    'rphub_wptoolkit_vulnerability_scan',
    'rphub_pagespeed_analysis',
    'rphub_backuply_check',
    'rphub_cloudflare_sync',
    'rphub_hourly_tasks',
    'rphub_daily_tasks',
    'rphub_generate_scheduled_reports',
];
foreach ($cron_hooks as $hook) {
    wp_clear_scheduled_hook($hook);
}
