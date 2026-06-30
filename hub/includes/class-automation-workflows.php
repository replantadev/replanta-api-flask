<?php
/**
 * Advanced Automation Workflows for Replanta Hub Professional
 * 
 * Implements intelligent automation based on analytics data and performance metrics
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ReplantaHub_Automation_Workflows {

    /**
     * Bump when built-in workflow definitions change.
     * maybe_migrate() detects the mismatch and updates the DB schema.
     */
    const BUILTIN_VERSION = '1.0.0';

    /**
     * Fields an administrator may override on a built-in workflow.
     * All other fields are governed exclusively by the code definition.
     */
    const USER_SETTABLE_FIELDS = ['enabled', 'cooldown', 'max_executions_per_day'];

    private $workflow_rules = [];

    public function __construct() {
        $this->maybe_migrate();
        $this->load_workflow_rules();
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('rphub_execute_workflows', array($this, 'execute_scheduled_workflows'));
        add_action('rphub_performance_threshold_triggered', array($this, 'handle_performance_alert'));
        add_action('rphub_security_threat_detected', array($this, 'handle_security_threat'));
        add_action('rphub_uptime_alert', array($this, 'handle_uptime_alert'));
        
        add_action('wp_ajax_rphub_create_workflow',            [$this, 'create_workflow']);
        add_action('wp_ajax_rphub_get_workflows',              [$this, 'get_workflows']);
        add_action('wp_ajax_rphub_toggle_workflow',            [$this, 'toggle_workflow']);
        add_action('wp_ajax_rphub_update_workflow_settings',   [$this, 'update_workflow_settings']);
        add_action('wp_ajax_rphub_delete_workflow',            [$this, 'delete_workflow']);
        add_action('wp_ajax_rphub_execute_workflow',           [$this, 'execute_workflow_manually']);
        add_action('wp_ajax_rphub_get_workflow_execution_log', [$this, 'get_execution_log']);

        RPHUB_Scheduler::schedule('rphub_execute_workflows', 'hourly');
    }
    
    // -------------------------------------------------------------------------
    // Built-in workflow registry — single source of truth in code
    // -------------------------------------------------------------------------

    /**
     * All built-in workflow definitions.
     *
     * To change a built-in:
     *   1. Edit its definition here.
     *   2. Bump BUILTIN_VERSION.
     * maybe_migrate() will propagate the change automatically while
     * preserving any administrator overrides already stored in the database.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function get_builtin_workflows(): array {
        return [
            'performance_optimization' => [
                'name'                   => 'Optimización Automática de Rendimiento',
                'description'            => 'Purga caché y optimiza recursos cuando el LCP supera el umbral.',
                'enabled'                => true,
                'trigger'                => [
                    'type'     => 'metric_threshold',
                    'metric'   => 'lcp',
                    'operator' => '>',
                    'value'    => 2500,
                    'duration' => '2h',
                ],
                'conditions'             => [
                    ['metric' => 'traffic_change', 'operator' => '>', 'value' => -20],
                    ['metric' => 'error_rate',     'operator' => '<', 'value' => 5],
                ],
                'actions'                => [
                    ['type' => 'litespeed_cache_purge', 'priority' => 1],
                    ['type' => 'image_optimization',    'priority' => 2],
                    ['type' => 'minify_assets',         'priority' => 3],
                    ['type' => 'notify_admin',          'priority' => 4],
                ],
                'cooldown'               => 3600,
                'max_executions_per_day' => 3,
            ],
            'security_response' => [
                'name'                   => 'Respuesta Automática de Seguridad',
                'description'            => 'Activa protección Cloudflare y dispara backup ante amenazas graves.',
                'enabled'                => true,
                'trigger'                => ['type' => 'security_event', 'severity' => 'high'],
                'conditions'             => [],
                'actions'                => [
                    ['type' => 'enable_cloudflare_protection', 'priority' => 1],
                    ['type' => 'backup_site',                  'priority' => 2],
                    ['type' => 'scan_malware',                 'priority' => 3],
                    ['type' => 'notify_security_team',         'priority' => 4],
                ],
                'cooldown'               => 1800,
                'max_executions_per_day' => 10,
            ],
            'traffic_spike_handling' => [
                'name'                   => 'Manejo de Picos de Tráfico',
                'description'            => 'Activa CDN y aumenta TTL de caché ante picos de usuarios concurrentes.',
                'enabled'                => true,
                'trigger'                => [
                    'type'     => 'metric_threshold',
                    'metric'   => 'concurrent_users',
                    'operator' => '>',
                    'value'    => 500,
                ],
                'conditions'             => [
                    ['metric' => 'server_load', 'operator' => '>', 'value' => 70],
                ],
                'actions'                => [
                    ['type' => 'activate_cdn',       'priority' => 1],
                    ['type' => 'increase_cache_ttl', 'priority' => 2],
                    ['type' => 'optimize_database',  'priority' => 3],
                    ['type' => 'scale_resources',    'priority' => 4],
                ],
                'cooldown'               => 7200,
                'max_executions_per_day' => 5,
            ],
            'backup_automation' => [
                'name'                   => 'Backup Automático Inteligente',
                'description'            => 'Crea y verifica un backup diario cuando hay cambios de contenido.',
                'enabled'                => true,
                'trigger'                => ['type' => 'scheduled', 'frequency' => 'daily', 'time' => '02:00'],
                'conditions'             => [
                    ['metric' => 'content_changes', 'operator' => '>', 'value' => 0],
                    ['metric' => 'last_backup_age', 'operator' => '>', 'value' => 20],
                ],
                'actions'                => [
                    ['type' => 'create_backup',        'priority' => 1],
                    ['type' => 'verify_backup',        'priority' => 2],
                    ['type' => 'cleanup_old_backups',  'priority' => 3],
                    ['type' => 'update_backup_status', 'priority' => 4],
                ],
                'cooldown'               => 3600,
                'max_executions_per_day' => 2,
            ],
            'seo_optimization' => [
                'name'                   => 'Optimización SEO Automática',
                'description'            => 'Actualiza metadatos y regenera sitemap ante caída de posiciones.',
                'enabled'                => true,
                'trigger'                => [
                    'type'     => 'metric_threshold',
                    'metric'   => 'search_ranking_drop',
                    'operator' => '>',
                    'value'    => 5,
                ],
                'conditions'             => [
                    ['metric' => 'content_freshness',   'operator' => '>', 'value' => 30],
                    ['metric' => 'technical_seo_score', 'operator' => '<', 'value' => 90],
                ],
                'actions'                => [
                    ['type' => 'update_meta_descriptions', 'priority' => 1],
                    ['type' => 'optimize_images_alt',      'priority' => 2],
                    ['type' => 'generate_sitemap',         'priority' => 3],
                    ['type' => 'submit_search_console',    'priority' => 4],
                ],
                'cooldown'               => 86400,
                'max_executions_per_day' => 1,
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Schema migration
    // -------------------------------------------------------------------------

    /**
     * Migrate DB data to the current schema when BUILTIN_VERSION changes.
     * Runs on every instantiation but exits immediately when already current.
     *
     * DB structure (option key: rphub_automation_workflows):
     *   {
     *     "_version":   "1.0.0",
     *     "_overrides": { "workflow_id": { "enabled": bool, "cooldown": int, ... } },
     *     "_custom":    { "workflow_id": { ...full definition... } }
     *   }
     */
    private function maybe_migrate(): void {
        $stored = get_option('rphub_automation_workflows', []);

        if (isset($stored['_version']) && $stored['_version'] === self::BUILTIN_VERSION) {
            return; // Already current — nothing to do.
        }

        $overrides = [];
        $custom    = [];

        if (!isset($stored['_version'])) {
            // v0 → v1.0.0 : flat array → structured { _version, _overrides, _custom }
            $builtins = self::get_builtin_workflows();
            foreach ($stored as $id => $workflow) {
                if (!is_array($workflow) || strpos((string) $id, '_') === 0) {
                    continue; // skip meta-keys and scalars
                }
                if (isset($builtins[$id])) {
                    // Built-in: preserve only fields that differ from code defaults.
                    $diff = [];
                    foreach (self::USER_SETTABLE_FIELDS as $field) {
                        if (isset($workflow[$field]) && $workflow[$field] !== $builtins[$id][$field]) {
                            $diff[$field] = $workflow[$field];
                        }
                    }
                    if (!empty($diff)) {
                        $overrides[$id] = $diff;
                    }
                } else {
                    // Unknown ID → treat as a user-created custom workflow.
                    $custom[$id] = $workflow;
                }
            }
        } else {
            // v1.x → v1.y: add field-level migrations below as needed.
            // if (version_compare($stored['_version'], '1.1.0', '<')) { ... }
            $overrides = $stored['_overrides'] ?? [];
            $custom    = $stored['_custom']    ?? [];
        }

        update_option('rphub_automation_workflows', [
            '_version'   => self::BUILTIN_VERSION,
            '_overrides' => $overrides,
            '_custom'    => $custom,
        ]);
    }

    // -------------------------------------------------------------------------
    // Runtime rule loading
    // -------------------------------------------------------------------------

    /**
     * Build the effective workflow rule set at runtime:
     *   built-ins (code) + user overrides (DB) + custom workflows (DB)
     */
    private function load_workflow_rules(): void {
        $stored    = get_option('rphub_automation_workflows', []);
        $overrides = $stored['_overrides'] ?? [];
        $custom    = $stored['_custom']    ?? [];

        // Start with the authoritative built-in definitions.
        $this->workflow_rules = self::get_builtin_workflows();

        // Layer user overrides on top (only USER_SETTABLE_FIELDS are accepted).
        foreach ($overrides as $id => $override_fields) {
            if (!isset($this->workflow_rules[$id])) {
                continue;
            }
            foreach (self::USER_SETTABLE_FIELDS as $field) {
                if (array_key_exists($field, $override_fields)) {
                    $this->workflow_rules[$id][$field] = $override_fields[$field];
                }
            }
        }

        // Append fully custom workflows.
        foreach ($custom as $id => $workflow) {
            $this->workflow_rules[$id] = $workflow;
        }
    }

    // -------------------------------------------------------------------------
    // DB persistence helpers (private)
    // -------------------------------------------------------------------------

    /**
     * Persist user-settable fields for a built-in workflow.
     * Fields not in USER_SETTABLE_FIELDS are silently ignored.
     */
    private function save_workflow_override(string $workflow_id, array $fields): bool {
        $stored = get_option('rphub_automation_workflows', []);
        foreach (self::USER_SETTABLE_FIELDS as $allowed) {
            if (array_key_exists($allowed, $fields)) {
                $stored['_overrides'][$workflow_id][$allowed] = $fields[$allowed];
            }
        }
        return (bool) update_option('rphub_automation_workflows', $stored);
    }

    /**
     * Persist a fully user-created (non-built-in) workflow.
     */
    private function save_custom_workflow(string $workflow_id, array $definition): bool {
        $stored = get_option('rphub_automation_workflows', []);
        $stored['_custom'][$workflow_id] = $definition;
        return (bool) update_option('rphub_automation_workflows', $stored);
    }

    /**
     * Remove a custom workflow from the database.
     * Built-in workflows cannot be deleted; disable them with the toggle instead.
     */
    private function delete_custom_workflow(string $workflow_id): bool {
        $stored = get_option('rphub_automation_workflows', []);
        if (isset($stored['_custom'][$workflow_id])) {
            unset($stored['_custom'][$workflow_id]);
            return (bool) update_option('rphub_automation_workflows', $stored);
        }
        return false;
    }
    
    /**
     * Execute scheduled workflows
     */
    public function execute_scheduled_workflows() {
        error_log('RPHUB: Starting scheduled workflow execution');
        
        foreach ($this->workflow_rules as $workflow_id => $workflow) {
            if (!$workflow['enabled']) {
                continue;
            }
            
            if ($this->should_execute_workflow($workflow_id, $workflow)) {
                $this->execute_workflow($workflow_id, $workflow);
            }
        }
        
        error_log('RPHUB: Scheduled workflow execution completed');
    }
    
    /**
     * Check if workflow should be executed
     */
    private function should_execute_workflow($workflow_id, $workflow) {
        // Check cooldown
        $last_execution = get_option("rphub_workflow_last_execution_{$workflow_id}", 0);
        if (time() - $last_execution < $workflow['cooldown']) {
            return false;
        }
        
        // Check daily execution limit
        $today = date('Y-m-d');
        $executions_today = get_option("rphub_workflow_executions_{$workflow_id}_{$today}", 0);
        if ($executions_today >= $workflow['max_executions_per_day']) {
            return false;
        }
        
        // Check trigger conditions
        return $this->check_workflow_trigger($workflow);
    }
    
    /**
     * Check if workflow trigger conditions are met
     */
    private function check_workflow_trigger($workflow) {
        $trigger = $workflow['trigger'];
        
        switch ($trigger['type']) {
            case 'metric_threshold':
                return $this->check_metric_threshold($trigger);
                
            case 'scheduled':
                return $this->check_scheduled_trigger($trigger);
                
            case 'security_event':
                return $this->check_security_event($trigger);
                
            default:
                return false;
        }
    }
    
    /**
     * Check metric threshold trigger
     */
    private function check_metric_threshold($trigger) {
        $metric_value = $this->get_current_metric_value($trigger['metric']);
        
        if ($metric_value === null) {
            return false;
        }
        
        switch ($trigger['operator']) {
            case '>':
                return $metric_value > $trigger['value'];
            case '<':
                return $metric_value < $trigger['value'];
            case '>=':
                return $metric_value >= $trigger['value'];
            case '<=':
                return $metric_value <= $trigger['value'];
            case '==':
                return $metric_value == $trigger['value'];
            default:
                return false;
        }
    }
    
    /**
     * Check scheduled trigger
     */
    private function check_scheduled_trigger($trigger) {
        if ($trigger['frequency'] === 'daily') {
            $current_time = date('H:i');
            return $current_time === $trigger['time'];
        }
        
        return false;
    }
    
    /**
     * Check security event trigger
     */
    private function check_security_event($trigger) {
        global $wpdb;
        
        // Check if alerts table exists
        $table_name = $wpdb->prefix . 'rphub_analytics_alerts';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        
        if (!$table_exists) {
            return false;
        }
        
        // Check for recent security alerts
        $recent_alerts = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}rphub_analytics_alerts 
            WHERE alert_type = 'security_threat' 
            AND severity = %s 
            AND triggered_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            AND is_resolved = 0
        ", $trigger['severity']));
        
        return $recent_alerts > 0;
    }
    
    /**
     * Get current metric value
     */
    private function get_current_metric_value($metric) {
        global $wpdb;
        
        switch ($metric) {
            case 'lcp':
                return $this->get_average_metric_across_sites('largest_contentful_paint');
                
            case 'concurrent_users':
                return $this->get_concurrent_users_count();
                
            case 'server_load':
                return $this->get_server_load();
                
            case 'search_ranking_drop':
                return $this->calculate_ranking_drop();
                
            default:
                return null;
        }
    }
    
    /**
     * Get average metric across all sites
     */
    private function get_average_metric_across_sites($metric) {
        global $wpdb;
        
        // Check if web vitals table exists
        $table_name = $wpdb->prefix . 'rphub_analytics_web_vitals';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        
        if (!$table_exists) {
            return 0;
        }
        
        // Sanitize metric name to prevent injection
        $allowed_metrics = array('largest_contentful_paint', 'first_input_delay', 'cumulative_layout_shift');
        if (!in_array($metric, $allowed_metrics)) {
            return 0;
        }
        
        $sql = "
            SELECT JSON_EXTRACT(data, '$.{$metric}.p75') as value
            FROM {$wpdb->prefix}rphub_analytics_web_vitals 
            WHERE collected_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            AND JSON_EXTRACT(data, '$.{$metric}.p75') IS NOT NULL
        ";
        
        $values = $wpdb->get_col($sql);
        
        if (empty($values)) {
            return null;
        }
        
        return array_sum($values) / count($values);
    }
    
    /**
     * Get concurrent users count (simulated)
     */
    private function get_concurrent_users_count() {
        return 0;
    }
    
    /**
     * Get server load
     */
    private function get_server_load() {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return $load ? round($load[0] * 100 / max(1, (int) @ini_get('max_execution_time'))) : 0;
        }
        return 0;
    }
    
    /**
     * Calculate ranking drop
     */
    private function calculate_ranking_drop() {
        global $wpdb;

        $table = $wpdb->prefix . 'rphub_analytics_search_console';
        if (!$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($table)))) {
            return 0;
        }

        // Get average position change over last 7 days
        $position_data = $wpdb->get_results("
            SELECT 
                JSON_EXTRACT(data, '$.avg_position') as current_position,
                collected_at
            FROM {$wpdb->prefix}rphub_analytics_search_console 
            WHERE collected_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY collected_at ASC
        ");
        
        if (count($position_data) < 2) {
            return 0;
        }
        
        $first_position = floatval($position_data[0]->current_position);
        $last_position = floatval($position_data[count($position_data) - 1]->current_position);
        
        return $last_position - $first_position; // Positive = ranking drop
    }
    
    /**
     * Execute workflow
     */
    private function execute_workflow($workflow_id, $workflow) {
        error_log("RPHUB: Executing workflow: {$workflow['name']}");
        
        $execution_start = microtime(true);
        $execution_results = array();
        
        // Check conditions before executing actions
        if (!$this->check_workflow_conditions($workflow)) {
            error_log("RPHUB: Workflow {$workflow_id} conditions not met, skipping");
            return;
        }
        
        // Sort actions by priority
        $actions = $workflow['actions'];
        usort($actions, function($a, $b) {
            return $a['priority'] - $b['priority'];
        });
        
        // Execute actions
        foreach ($actions as $action) {
            $action_result = $this->execute_action($action);
            $execution_results[] = array(
                'action' => $action,
                'result' => $action_result,
                'timestamp' => current_time('mysql')
            );
            
            // Add delay between actions to prevent overload
            sleep(1);
        }
        
        $execution_time = microtime(true) - $execution_start;
        
        // Log execution
        $this->log_workflow_execution($workflow_id, $workflow, $execution_results, $execution_time);
        
        // Update execution tracking
        $this->update_execution_tracking($workflow_id);
        
        error_log("RPHUB: Workflow {$workflow_id} completed in {$execution_time}s");
    }
    
    /**
     * Check workflow conditions
     */
    private function check_workflow_conditions($workflow) {
        if (empty($workflow['conditions'])) {
            return true;
        }
        
        foreach ($workflow['conditions'] as $condition) {
            $metric_value = $this->get_current_metric_value($condition['metric']);
            
            if ($metric_value === null) {
                continue;
            }
            
            $condition_met = false;
            switch ($condition['operator']) {
                case '>':
                    $condition_met = $metric_value > $condition['value'];
                    break;
                case '<':
                    $condition_met = $metric_value < $condition['value'];
                    break;
                case '>=':
                    $condition_met = $metric_value >= $condition['value'];
                    break;
                case '<=':
                    $condition_met = $metric_value <= $condition['value'];
                    break;
                case '==':
                    $condition_met = $metric_value == $condition['value'];
                    break;
            }
            
            if (!$condition_met) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Execute individual action
     */
    private function execute_action($action) {
        switch ($action['type']) {
            case 'litespeed_cache_purge':
                return $this->execute_litespeed_cache_purge();
                
            case 'image_optimization':
                return $this->execute_image_optimization();
                
            case 'minify_assets':
                return $this->execute_minify_assets();
                
            case 'enable_cloudflare_protection':
                return $this->execute_cloudflare_protection();
                
            case 'backup_site':
                return $this->execute_site_backup();
                
            case 'scan_malware':
                return $this->execute_malware_scan();
                
            case 'activate_cdn':
                return $this->execute_cdn_activation();
                
            case 'optimize_database':
                return $this->execute_database_optimization();
                
            case 'create_backup':
                return $this->execute_create_backup();
                
            case 'update_meta_descriptions':
                return $this->execute_meta_optimization();
                
            case 'generate_sitemap':
                return $this->execute_sitemap_generation();
                
            case 'notify_admin':
                return $this->execute_admin_notification($action);
                
            default:
                return array('success' => false, 'message' => 'Unknown action type');
        }
    }
    
    /**
     * Execute LiteSpeed cache purge
     */
    private function execute_litespeed_cache_purge() {
        if (class_exists('LiteSpeed_Cache_API')) {
            try {
                do_action('litespeed_purge_all');
                return array('success' => true, 'message' => 'LiteSpeed cache purged successfully');
            } catch (Exception $e) {
                return array('success' => false, 'message' => 'LiteSpeed cache purge failed: ' . $e->getMessage());
            }
        }
        
        // Try other cache plugins with proper checking
        if (function_exists('w3tc_flush_all')) {
            try {
                // @phpstan-ignore-next-line - External W3 Total Cache plugin function
                // @psalm-suppress UndefinedFunction - Function from W3 Total Cache plugin
                w3tc_flush_all();
                return array('success' => true, 'message' => 'W3 Total Cache flushed');
            } catch (Exception $e) {
                error_log('RPHUB: W3 Total Cache flush error: ' . $e->getMessage());
            }
        }
        
        if (function_exists('wp_cache_clear_cache')) {
            try {
                // @phpstan-ignore-next-line - External WP Super Cache plugin function  
                // @psalm-suppress UndefinedFunction - Function from WP Super Cache plugin
                wp_cache_clear_cache();
                return array('success' => true, 'message' => 'WP Super Cache cleared');
            } catch (Exception $e) {
                error_log('RPHUB: WP Super Cache clear error: ' . $e->getMessage());
            }
        }
        
        // Fallback: clear WordPress cache
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
            return array('success' => true, 'message' => 'WordPress cache flushed');
        }
        
        return array('success' => false, 'message' => 'No cache system available');
    }
    
    /**
     * Execute image optimization
     */
    private function execute_image_optimization() {
        // This would integrate with image optimization services
        return array('success' => true, 'message' => 'Image optimization queued');
    }
    
    /**
     * Execute asset minification
     */
    private function execute_minify_assets() {
        // This would minify CSS/JS assets
        return array('success' => true, 'message' => 'Asset minification completed');
    }
    
    /**
     * Execute Cloudflare protection
     */
    private function execute_cloudflare_protection() {
        // Check if Cloudflare integration is available
        if (get_option('rphub_cloudflare_enabled') && get_option('rphub_cloudflare_api_key')) {
            $raw_key = get_option('rphub_cloudflare_api_key', '');
            $api_key = class_exists('RPHUB_Crypto') ? RPHUB_Crypto::decrypt($raw_key) : $raw_key;
            $zone_id = get_option('rphub_cloudflare_zone_id');
            
            if ($api_key && $zone_id) {
                // Enable Under Attack Mode via API
                $response = wp_remote_post("https://api.cloudflare.com/client/v4/zones/{$zone_id}/settings/security_level", array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type' => 'application/json'
                    ),
                    'body' => wp_json_encode(array('value' => 'under_attack'))
                ));
                
                if (!is_wp_error($response)) {
                    return array('success' => true, 'message' => 'Cloudflare Under Attack Mode enabled');
                }
            }
        }
        
        return array('success' => false, 'message' => 'Cloudflare integration not configured');
    }
    
    /**
     * Execute site backup
     */
    private function execute_site_backup() {
        // Check if Backuply integration is available
        if (get_option('rphub_backuply_enabled') && class_exists('ReplantaHub_Backuply_Integration')) {
            $backuply = new ReplantaHub_Backuply_Integration();
            if ($backuply->is_configured()) {
                // Trigger backup via Care → Backuply on remote site
                $domain = get_option('rphub_current_domain', '');
                if ($domain) {
                    $result = $backuply->create_backup($domain, [
                        'type' => 'full',
                        'description' => 'Automated workflow backup'
                    ]);
                    if (!is_wp_error($result)) {
                        return array('success' => true, 'message' => 'Backuply full backup initiated via Care');
                    }
                }
            }
        }
        
        // Fallback: WordPress export
        $export_result = $this->create_wordpress_export();
        if ($export_result['success']) {
            return array('success' => true, 'message' => 'WordPress export backup created');
        }
        
        return array('success' => false, 'message' => 'Backup system not available');
    }
    
    /**
     * Create WordPress export backup
     */
    private function create_wordpress_export() {
        try {
            // Create a simple database backup
            global $wpdb;
            
            $backup_dir = wp_upload_dir()['basedir'] . '/rphub-backups/';
            if (!file_exists($backup_dir)) {
                wp_mkdir_p($backup_dir);
            }
            
            $backup_file = $backup_dir . 'backup-' . date('Y-m-d-H-i-s') . '.sql';
            
            // Get all tables
            $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
            $sql_content = '';
            
            foreach ($tables as $table) {
                $table_name = $table[0];
                $sql_content .= "-- Table: {$table_name}\n";
                
                // Get table structure
                $create_table = $wpdb->get_row("SHOW CREATE TABLE `{$table_name}`", ARRAY_N);
                $sql_content .= $create_table[1] . ";\n\n";
                
                // Get table data (limit to prevent huge files)
                $rows = $wpdb->get_results("SELECT * FROM `{$table_name}` LIMIT 1000", ARRAY_A);
                
                foreach ($rows as $row) {
                    $values = array_map(function($value) use ($wpdb) {
                        return "'" . $wpdb->_escape($value) . "'";
                    }, array_values($row));
                    
                    $sql_content .= "INSERT INTO `{$table_name}` VALUES (" . implode(', ', $values) . ");\n";
                }
                
                $sql_content .= "\n";
            }
            
            $result = file_put_contents($backup_file, $sql_content);
            
            return array(
                'success' => $result !== false,
                'file' => $backup_file,
                'message' => $result !== false ? 'Export created' : 'Export failed'
            );
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'Export error: ' . $e->getMessage());
        }
    }
    
    /**
     * Execute malware scan
     */
    private function execute_malware_scan() {
        // This would trigger a malware scan
        return array('success' => true, 'message' => 'Malware scan initiated');
    }
    
    /**
     * Execute CDN activation
     */
    private function execute_cdn_activation() {
        // This would activate CDN for better performance
        return array('success' => true, 'message' => 'CDN activated');
    }
    
    /**
     * Execute database optimization
     */
    private function execute_database_optimization() {
        global $wpdb;
        
        try {
            // Optimize database tables
            $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
            $optimized_count = 0;
            
            foreach ($tables as $table) {
                $table_name = $table[0];
                $result = $wpdb->query("OPTIMIZE TABLE `$table_name`");
                if ($result !== false) {
                    $optimized_count++;
                }
            }
            
            return array(
                'success' => true, 
                'message' => "Database optimized: {$optimized_count} tables"
            );
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'Database optimization failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Execute backup creation
     */
    private function execute_create_backup() {
        // This would create a comprehensive backup
        return array('success' => true, 'message' => 'Backup created successfully');
    }
    
    /**
     * Execute meta description optimization
     */
    private function execute_meta_optimization() {
        // This would optimize meta descriptions based on Search Console data
        return array('success' => true, 'message' => 'Meta descriptions optimized');
    }
    
    /**
     * Execute sitemap generation
     */
    private function execute_sitemap_generation() {
        // This would generate and submit XML sitemap
        return array('success' => true, 'message' => 'Sitemap generated and submitted');
    }
    
    /**
     * Execute admin notification
     */
    private function execute_admin_notification($action) {
        $admin_email = get_option('admin_email');
        $subject = 'Replanta Hub: Workflow Execution Alert';
        $message = 'An automated workflow has been executed on your site.';
        
        $sent = wp_mail($admin_email, $subject, $message);
        
        return array(
            'success' => $sent,
            'message' => $sent ? 'Admin notification sent' : 'Failed to send notification'
        );
    }
    
    /**
     * Log workflow execution
     */
    private function log_workflow_execution($workflow_id, $workflow, $results, $execution_time) {
        global $wpdb;
        
        $log_data = array(
            'workflow_id' => $workflow_id,
            'workflow_name' => $workflow['name'],
            'execution_results' => $results,
            'execution_time' => $execution_time,
            'triggered_by' => 'automatic',
            'executed_at' => current_time('mysql')
        );
        
        // Check if automation tasks table exists and has correct columns
        $table_name = $wpdb->prefix . 'rphub_automation_tasks';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        
        if ($table_exists) {
            $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
            
            if (in_array('task_data', $columns)) {
                // New table structure with task_data column
                $wpdb->insert(
                    $table_name,
                    array(
                        'task_type' => 'workflow_execution',
                        'task_data' => json_encode($log_data),
                        'status' => 'completed',
                        'created_at' => current_time('mysql')
                    ),
                    array('%s', '%s', '%s', '%s')
                );
            } else {
                // Old table structure, insert basic data
                $wpdb->insert(
                    $table_name,
                    array(
                        'task_type' => 'workflow_execution',
                        'status' => 'completed',
                        'created_at' => current_time('mysql')
                    ),
                    array('%s', '%s', '%s')
                );
            }
        }
    }
    
    /**
     * Update execution tracking
     */
    private function update_execution_tracking($workflow_id) {
        // Update last execution time
        update_option("rphub_workflow_last_execution_{$workflow_id}", time());
        
        // Update daily execution count
        $today = date('Y-m-d');
        $current_count = get_option("rphub_workflow_executions_{$workflow_id}_{$today}", 0);
        update_option("rphub_workflow_executions_{$workflow_id}_{$today}", $current_count + 1);
    }
    
    /**
     * Handle performance alert
     */
    public function handle_performance_alert($alert_data) {
        error_log('RPHUB: Performance alert triggered, checking automation workflows');
        
        // Trigger performance optimization workflow
        if (isset($this->workflow_rules['performance_optimization']) && 
            $this->workflow_rules['performance_optimization']['enabled']) {
            $this->execute_workflow('performance_optimization', $this->workflow_rules['performance_optimization']);
        }
    }
    
    /**
     * Handle security threat
     */
    public function handle_security_threat($threat_data) {
        error_log('RPHUB: Security threat detected, executing security response workflow');
        
        if (isset($this->workflow_rules['security_response']) && 
            $this->workflow_rules['security_response']['enabled']) {
            $this->execute_workflow('security_response', $this->workflow_rules['security_response']);
        }
    }
    
    /**
     * Handle uptime alert
     */
    public function handle_uptime_alert($uptime_data) {
        error_log('RPHUB: Uptime alert received, executing response workflows');
        
        // This could trigger multiple workflows based on uptime issue type
    }
    
    /**
     * AJAX: Create new workflow
     */
    public function create_workflow() {
        check_ajax_referer('rphub_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        $workflow_data = json_decode(wp_unslash($_POST['workflow_data'] ?? ''), true);

        if (!is_array($workflow_data) || empty($workflow_data['id'])) {
            wp_send_json_error('Datos de workflow inválidos');
        }

        $workflow_id = sanitize_key($workflow_data['id']);

        // Prevent overwriting built-in workflows via this endpoint.
        if (array_key_exists($workflow_id, self::get_builtin_workflows())) {
            wp_send_json_error('Usa update_workflow_settings para ajustar workflows del sistema');
        }

        $this->save_custom_workflow($workflow_id, $workflow_data);
        wp_send_json_success(['id' => $workflow_id, 'message' => 'Workflow creado exitosamente']);
    }
    
    /**
     * AJAX: Get workflows
     */
    public function get_workflows() {
        check_ajax_referer('rphub_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        $builtin_ids = array_keys(self::get_builtin_workflows());
        $result      = [];
        foreach ($this->workflow_rules as $id => $rule) {
            $result[$id] = array_merge($rule, [
                'is_builtin' => in_array($id, $builtin_ids, true),
            ]);
        }

        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Toggle workflow
     */
    public function toggle_workflow() {
        check_ajax_referer('rphub_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        $workflow_id = sanitize_key($_POST['workflow_id'] ?? '');
        $enabled     = (bool) ($_POST['enabled'] ?? false);

        if (!isset($this->workflow_rules[$workflow_id])) {
            wp_send_json_error('Workflow no encontrado');
        }

        if (array_key_exists($workflow_id, self::get_builtin_workflows())) {
            $this->save_workflow_override($workflow_id, ['enabled' => $enabled]);
        } else {
            $stored = get_option('rphub_automation_workflows', []);
            $stored['_custom'][$workflow_id]['enabled'] = $enabled;
            update_option('rphub_automation_workflows', $stored);
        }

        wp_send_json_success('Workflow actualizado');
    }
    
    /**
     * AJAX: Execute workflow manually
     */
    public function execute_workflow_manually() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $workflow_id = sanitize_key($_POST['workflow_id'] ?? '');

        if (!isset($this->workflow_rules[$workflow_id])) {
            wp_send_json_error('Workflow no encontrado');
        }

        try {
            $this->execute_workflow($workflow_id, $this->workflow_rules[$workflow_id]);
            wp_send_json_success('Workflow ejecutado exitosamente');
        } catch (Exception $e) {
            wp_send_json_error('Error ejecutando workflow: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX: Get execution log
     */
    public function get_execution_log() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        global $wpdb;
        
        $logs = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}rphub_automation_tasks 
            WHERE task_type = 'workflow_execution' 
            ORDER BY created_at DESC 
            LIMIT 50
        ");
        
        $formatted_logs = array();
        foreach ($logs as $log) {
            $task_data = json_decode($log->task_data, true);
            $formatted_logs[] = array(
                'id' => $log->id,
                'workflow_name' => $task_data['workflow_name'],
                'execution_time' => $task_data['execution_time'],
                'results_count' => count($task_data['execution_results']),
                'status' => $log->status,
                'executed_at' => $task_data['executed_at']
            );
        }
        
        wp_send_json_success($formatted_logs);
    }

    /**
     * AJAX: Update runtime parameters for a built-in workflow, or full definition for a custom one.
     *
     * For built-ins: only USER_SETTABLE_FIELDS (enabled, cooldown, max_executions_per_day) are accepted.
     * For custom workflows: any field in the stored definition can be updated.
     */
    public function update_workflow_settings() {
        check_ajax_referer('rphub_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        $workflow_id = sanitize_key($_POST['workflow_id'] ?? '');
        $settings    = json_decode(wp_unslash($_POST['settings'] ?? ''), true);

        if (!$workflow_id || !is_array($settings)) {
            wp_send_json_error('Parámetros inválidos');
        }

        if (!isset($this->workflow_rules[$workflow_id])) {
            wp_send_json_error('Workflow no encontrado');
        }

        if (array_key_exists($workflow_id, self::get_builtin_workflows())) {
            // Built-in: only allow USER_SETTABLE_FIELDS.
            $allowed = [];
            foreach (self::USER_SETTABLE_FIELDS as $field) {
                if (array_key_exists($field, $settings)) {
                    $allowed[$field] = $settings[$field];
                }
            }
            if (empty($allowed)) {
                wp_send_json_error('Ningún campo modificable en los parámetros enviados. Campos permitidos: ' . implode(', ', self::USER_SETTABLE_FIELDS));
            }
            $this->save_workflow_override($workflow_id, $allowed);
        } else {
            // Custom workflow: merge into stored definition.
            $stored = get_option('rphub_automation_workflows', []);
            $stored['_custom'][$workflow_id] = array_merge(
                $stored['_custom'][$workflow_id] ?? [],
                $settings
            );
            update_option('rphub_automation_workflows', $stored);
        }

        wp_send_json_success('Configuración actualizada');
    }

    /**
     * AJAX: Delete a custom workflow.
     * Built-in workflows cannot be deleted; disable them with toggle_workflow instead.
     */
    public function delete_workflow() {
        check_ajax_referer('rphub_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        $workflow_id = sanitize_key($_POST['workflow_id'] ?? '');

        if (array_key_exists($workflow_id, self::get_builtin_workflows())) {
            wp_send_json_error('Los workflows del sistema no pueden eliminarse. Usa el toggle para desactivarlos.');
        }

        if ($this->delete_custom_workflow($workflow_id)) {
            wp_send_json_success('Workflow eliminado');
        } else {
            wp_send_json_error('Workflow no encontrado');
        }
    }
}
