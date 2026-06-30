<?php
/**
 * Database Query Optimizer for Replanta Hub
 * 
 * Provides optimized database operations with proper indexing,
 * limits, and performance monitoring.
 *
 * @package ReplantaHub
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ReplantaHub_Query_Optimizer {
    
    /**
     * Cache for commonly used queries
     */
    private static $query_cache = array();
    
    /**
     * Query performance tracking
     */
    private static $query_stats = array();
    
    /**
     * Default query limits
     */
    const DEFAULT_LIMIT = 50;
    const MAX_LIMIT = 1000;
    
    /**
     * Cache TTL in seconds
     */
    const CACHE_TTL = 300; // 5 minutes
    
    /**
     * Get optimized sites list with proper limits and indexing
     */
    public static function get_sites($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => null,
            'limit' => self::DEFAULT_LIMIT,
            'offset' => 0,
            'orderby' => 'name',
            'order' => 'ASC',
            'search' => '',
            'plan_id' => null,
            'health_min' => null,
            'health_max' => null,
            'cache' => true
        );
        
        $args = wp_parse_args($args, $defaults);
        $args['limit'] = min($args['limit'], self::MAX_LIMIT);
        
        // Build cache key
        $cache_key = 'rphub_sites_' . md5(serialize($args));
        
        if ($args['cache'] && isset(self::$query_cache[$cache_key])) {
            $cached = self::$query_cache[$cache_key];
            if (time() - $cached['time'] < self::CACHE_TTL) {
                return $cached['data'];
            }
        }
        
        $table_sites = $wpdb->prefix . 'rphub_sites';
        
        // Build WHERE clause with prepared statements
        $where_clauses = array('1=1');
        $prepare_values = array();
        
        if (!empty($args['status'])) {
            $where_clauses[] = 'status = %s';
            $prepare_values[] = $args['status'];
        }
        
        if (!empty($args['search'])) {
            $where_clauses[] = '(name LIKE %s OR url LIKE %s OR description LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $prepare_values[] = $search_term;
            $prepare_values[] = $search_term;
            $prepare_values[] = $search_term;
        }
        
        if (!is_null($args['plan_id'])) {
            $where_clauses[] = 'plan_id = %d';
            $prepare_values[] = $args['plan_id'];
        }
        
        if (!is_null($args['health_min'])) {
            $where_clauses[] = 'health_score >= %d';
            $prepare_values[] = $args['health_min'];
        }
        
        if (!is_null($args['health_max'])) {
            $where_clauses[] = 'health_score <= %d';
            $prepare_values[] = $args['health_max'];
        }
        
        // Build ORDER BY clause
        $allowed_orderby = array('id', 'name', 'url', 'created_at', 'updated_at', 'health_score', 'status');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'name';
        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';
        
        // Build final query
        $where_sql = implode(' AND ', $where_clauses);
        
        $sql = "SELECT * FROM $table_sites 
                WHERE $where_sql 
                ORDER BY $orderby $order 
                LIMIT %d OFFSET %d";
        
        $prepare_values[] = $args['limit'];
        $prepare_values[] = $args['offset'];
        
        $start_time = microtime(true);
        $prepared_sql = $wpdb->prepare($sql, ...$prepare_values);
        $results = $wpdb->get_results($prepared_sql, ARRAY_A);
        $query_time = microtime(true) - $start_time;
        
        // Log performance if slow
        if ($query_time > 1.0) {
            rphub_error_manager()->log_error(
                sprintf('Slow query detected: get_sites took %.2f seconds', $query_time),
                ReplantaHub_Error_Manager::LEVEL_WARNING,
                ReplantaHub_Error_Manager::TYPE_DB_ERROR,
                array(
                    'query_time' => $query_time,
                    'args' => $args,
                    'result_count' => count($results)
                )
            );
        }
        
        // Store performance stats
        self::track_query_performance('get_sites', $query_time, count($results));
        
        // Cache results
        if ($args['cache']) {
            self::$query_cache[$cache_key] = array(
                'data' => $results,
                'time' => time()
            );
        }
        
        return $results;
    }
    
    /**
     * Get optimized dashboard statistics with single query
     */
    public static function get_dashboard_stats() {
        global $wpdb;
        
        $cache_key = 'rphub_dashboard_stats';
        
        if (isset(self::$query_cache[$cache_key])) {
            $cached = self::$query_cache[$cache_key];
            if (time() - $cached['time'] < 60) { // 1 minute cache for stats
                return $cached['data'];
            }
        }
        
        $table_sites = $wpdb->prefix . 'rphub_sites';
        $table_tasks = $wpdb->prefix . 'rphub_tasks';
        $table_notifications = $wpdb->prefix . 'rphub_notifications';
        
        $start_time = microtime(true);
        
        // Single optimized query for all stats
        $sql = "
            SELECT 
                (SELECT COUNT(*) FROM $table_sites) as total_sites,
                (SELECT COUNT(*) FROM $table_sites WHERE status = 'active') as active_sites,
                (SELECT COUNT(*) FROM $table_sites WHERE status = 'inactive') as inactive_sites,
                (SELECT COUNT(*) FROM $table_tasks WHERE status = 'pending') as pending_tasks,
                (SELECT COUNT(*) FROM $table_tasks WHERE status = 'running') as running_tasks,
                (SELECT COUNT(*) FROM $table_notifications WHERE is_read = 0) as unread_notifications,
                (SELECT AVG(health_score) FROM $table_sites WHERE health_score > 0) as avg_health_score,
                (SELECT COUNT(*) FROM $table_sites WHERE health_score >= 80) as healthy_sites,
                (SELECT COUNT(*) FROM $table_sites WHERE health_score < 60 AND health_score > 0) as warning_sites
        ";
        
        $stats = $wpdb->get_row($sql, ARRAY_A);
        $query_time = microtime(true) - $start_time;
        
        // Log if slow
        if ($query_time > 0.5) {
            rphub_log_db_error($sql, sprintf('Dashboard stats query slow: %.2f seconds', $query_time));
        }
        
        self::track_query_performance('get_dashboard_stats', $query_time, 1);
        
        // Cache for 1 minute
        self::$query_cache[$cache_key] = array(
            'data' => $stats,
            'time' => time()
        );
        
        return $stats;
    }
    
    /**
     * Get paginated notifications with optimized query
     */
    public static function get_notifications($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'user_id' => get_current_user_id(),
            'is_read' => null,
            'type' => null,
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        $args['limit'] = min($args['limit'], 100); // Max 100 notifications
        
        $table_notifications = $wpdb->prefix . 'rphub_notifications';
        
        $where_clauses = array();
        $prepare_values = array();
        
        if ($args['user_id']) {
            $where_clauses[] = 'user_id = %d';
            $prepare_values[] = $args['user_id'];
        }
        
        if (!is_null($args['is_read'])) {
            $where_clauses[] = 'is_read = %d';
            $prepare_values[] = $args['is_read'] ? 1 : 0;
        }
        
        if (!empty($args['type'])) {
            $where_clauses[] = 'type = %s';
            $prepare_values[] = $args['type'];
        }
        
        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
        
        $sql = "SELECT * FROM $table_notifications 
                $where_sql 
                ORDER BY {$args['orderby']} {$args['order']} 
                LIMIT %d OFFSET %d";
        
        $prepare_values[] = $args['limit'];
        $prepare_values[] = $args['offset'];
        
        $start_time = microtime(true);
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$prepare_values), ARRAY_A);
        $query_time = microtime(true) - $start_time;
        
        self::track_query_performance('get_notifications', $query_time, count($results));
        
        return $results;
    }
    
    /**
     * Get tasks with optimized filtering
     */
    public static function get_tasks($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'site_id' => null,
            'status' => null,
            'type' => null,
            'priority' => null,
            'limit' => self::DEFAULT_LIMIT,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'date_from' => null,
            'date_to' => null
        );
        
        $args = wp_parse_args($args, $defaults);
        $args['limit'] = min($args['limit'], self::MAX_LIMIT);
        
        $table_tasks = $wpdb->prefix . 'rphub_tasks';
        
        $where_clauses = array();
        $prepare_values = array();
        
        if (!is_null($args['site_id'])) {
            $where_clauses[] = 'site_id = %d';
            $prepare_values[] = $args['site_id'];
        }
        
        if (!empty($args['status'])) {
            $where_clauses[] = 'status = %s';
            $prepare_values[] = $args['status'];
        }
        
        if (!empty($args['type'])) {
            $where_clauses[] = 'type = %s';
            $prepare_values[] = $args['type'];
        }
        
        if (!empty($args['priority'])) {
            $where_clauses[] = 'priority = %s';
            $prepare_values[] = $args['priority'];
        }
        
        if (!empty($args['date_from'])) {
            $where_clauses[] = 'created_at >= %s';
            $prepare_values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where_clauses[] = 'created_at <= %s';
            $prepare_values[] = $args['date_to'];
        }
        
        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
        
        $sql = "SELECT * FROM $table_tasks 
                $where_sql 
                ORDER BY {$args['orderby']} {$args['order']} 
                LIMIT %d OFFSET %d";
        
        $prepare_values[] = $args['limit'];
        $prepare_values[] = $args['offset'];
        
        $start_time = microtime(true);
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$prepare_values), ARRAY_A);
        $query_time = microtime(true) - $start_time;
        
        self::track_query_performance('get_tasks', $query_time, count($results));
        
        return $results;
    }
    
    /**
     * Get pagespeed reports with date filtering and limits
     */
    public static function get_pagespeed_reports($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'site_id' => null,
            'device_type' => null,
            'limit' => 50,
            'offset' => 0,
            'date_from' => null,
            'date_to' => null,
            'min_score' => null,
            'max_score' => null
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table_reports = $wpdb->prefix . 'rphub_pagespeed_reports';
        
        $where_clauses = array();
        $prepare_values = array();
        
        if (!is_null($args['site_id'])) {
            $where_clauses[] = 'site_id = %d';
            $prepare_values[] = $args['site_id'];
        }
        
        if (!empty($args['device_type'])) {
            $where_clauses[] = 'device_type = %s';
            $prepare_values[] = $args['device_type'];
        }
        
        if (!empty($args['date_from'])) {
            $where_clauses[] = 'created_at >= %s';
            $prepare_values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where_clauses[] = 'created_at <= %s';
            $prepare_values[] = $args['date_to'];
        }
        
        if (!is_null($args['min_score'])) {
            $where_clauses[] = 'performance_score >= %d';
            $prepare_values[] = $args['min_score'];
        }
        
        if (!is_null($args['max_score'])) {
            $where_clauses[] = 'performance_score <= %d';
            $prepare_values[] = $args['max_score'];
        }
        
        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
        
        $sql = "SELECT * FROM $table_reports 
                $where_sql 
                ORDER BY created_at DESC 
                LIMIT %d OFFSET %d";
        
        $prepare_values[] = $args['limit'];
        $prepare_values[] = $args['offset'];
        
        $start_time = microtime(true);
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$prepare_values), ARRAY_A);
        $query_time = microtime(true) - $start_time;
        
        self::track_query_performance('get_pagespeed_reports', $query_time, count($results));
        
        return $results;
    }
    
    /**
     * Clear query cache
     */
    public static function clear_cache($pattern = null) {
        if ($pattern) {
            foreach (self::$query_cache as $key => $data) {
                if (strpos($key, $pattern) !== false) {
                    unset(self::$query_cache[$key]);
                }
            }
        } else {
            self::$query_cache = array();
        }
    }
    
    /**
     * Get query performance statistics
     */
    public static function get_performance_stats() {
        return self::$query_stats;
    }
    
    /**
     * Track query performance
     */
    private static function track_query_performance($query_type, $execution_time, $result_count) {
        if (!isset(self::$query_stats[$query_type])) {
            self::$query_stats[$query_type] = array(
                'count' => 0,
                'total_time' => 0,
                'avg_time' => 0,
                'max_time' => 0,
                'min_time' => PHP_FLOAT_MAX,
                'total_results' => 0
            );
        }
        
        $stats = &self::$query_stats[$query_type];
        $stats['count']++;
        $stats['total_time'] += $execution_time;
        $stats['avg_time'] = $stats['total_time'] / $stats['count'];
        $stats['max_time'] = max($stats['max_time'], $execution_time);
        $stats['min_time'] = min($stats['min_time'], $execution_time);
        $stats['total_results'] += $result_count;
        
        // Log slow queries
        if ($execution_time > 2.0) {
            rphub_error_manager()->log_error(
                sprintf('Very slow query: %s took %.2f seconds', $query_type, $execution_time),
                ReplantaHub_Error_Manager::LEVEL_ERROR,
                ReplantaHub_Error_Manager::TYPE_DB_ERROR,
                array(
                    'query_type' => $query_type,
                    'execution_time' => $execution_time,
                    'result_count' => $result_count
                )
            );
        }
    }
    
    /**
     * Create database indexes for optimization
     */
    public static function create_indexes() {
        global $wpdb;
        
        $indexes = array(
            // Sites table indexes
            $wpdb->prefix . 'rphub_sites' => array(
                'idx_status' => 'status',
                'idx_health_score' => 'health_score',
                'idx_plan_id' => 'plan_id',
                'idx_created_at' => 'created_at',
                'idx_name_search' => 'name(50)', // Partial index for names
                'idx_status_health' => 'status, health_score' // Composite index
            ),
            
            // Tasks table indexes
            $wpdb->prefix . 'rphub_tasks' => array(
                'idx_site_id' => 'site_id',
                'idx_status' => 'status',
                'idx_type' => 'type',
                'idx_priority' => 'priority',
                'idx_created_at' => 'created_at',
                'idx_site_status' => 'site_id, status', // Composite index
                'idx_status_priority' => 'status, priority' // Composite index
            ),
            
            // Notifications table indexes
            $wpdb->prefix . 'rphub_notifications' => array(
                'idx_user_id' => 'user_id',
                'idx_is_read' => 'is_read',
                'idx_type' => 'type',
                'idx_created_at' => 'created_at',
                'idx_user_read' => 'user_id, is_read' // Composite index
            ),
            
            // PageSpeed reports indexes
            $wpdb->prefix . 'rphub_pagespeed_reports' => array(
                'idx_site_id' => 'site_id',
                'idx_device_type' => 'device_type',
                'idx_created_at' => 'created_at',
                'idx_performance_score' => 'performance_score',
                'idx_site_device' => 'site_id, device_type' // Composite index
            )
        );
        
        foreach ($indexes as $table => $table_indexes) {
            foreach ($table_indexes as $index_name => $columns) {
                $sql = "CREATE INDEX IF NOT EXISTS $index_name ON $table ($columns)";
                $result = $wpdb->query($sql);
                
                if ($result === false) {
                    rphub_log_db_error($sql, $wpdb->last_error, array('table' => $table, 'index' => $index_name));
                } else {
                    rphub_error_manager()->log_error(
                        "Database index created: $index_name on $table",
                        ReplantaHub_Error_Manager::LEVEL_INFO,
                        ReplantaHub_Error_Manager::TYPE_DB_ERROR,
                        array('table' => $table, 'index' => $index_name, 'columns' => $columns)
                    );
                }
            }
        }
    }
}

// Hook to create indexes on activation
add_action('rphub_create_database_indexes', array('ReplantaHub_Query_Optimizer', 'create_indexes'));

// Helper functions for backward compatibility
function rphub_get_sites_optimized($args = array()) {
    return ReplantaHub_Query_Optimizer::get_sites($args);
}

function rphub_get_dashboard_stats_optimized() {
    return ReplantaHub_Query_Optimizer::get_dashboard_stats();
}

function rphub_get_notifications_optimized($args = array()) {
    return ReplantaHub_Query_Optimizer::get_notifications($args);
}

function rphub_get_tasks_optimized($args = array()) {
    return ReplantaHub_Query_Optimizer::get_tasks($args);
}

function rphub_clear_query_cache($pattern = null) {
    return ReplantaHub_Query_Optimizer::clear_cache($pattern);
}
