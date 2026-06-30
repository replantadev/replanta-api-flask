<?php
/**
 * API Rate Limiter for Replanta Hub
 * 
 * Prevents API throttling by managing request rates to external services
 * like PageSpeed Insights, Cloudflare, etc.
 *
 * @package ReplantaHub
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ReplantaHub_Rate_Limiter {
    
    /**
     * Rate limits per API service (requests per hour)
     */
    private static $rate_limits = array(
        'pagespeed' => 25000,     // PageSpeed Insights: 25,000/day
        'cloudflare' => 1200,     // Cloudflare: 1,200/hour typical
        'wptoolkit' => 600,       // WP Toolkit: Conservative limit
        'jetbackup' => 300,       // JetBackup: Conservative limit
        'litespeed' => 120,       // LiteSpeed: Conservative limit
        'whm' => 600              // WHM: Reasonable limit
    );
    
    /**
     * Burst limits (requests per minute)
     */
    private static $burst_limits = array(
        'pagespeed' => 100,
        'cloudflare' => 100,
        'wptoolkit' => 30,
        'jetbackup' => 10,
        'litespeed' => 10,
        'whm' => 30
    );
    
    /**
     * Request tracking storage
     */
    private static $request_log = array();
    
    /**
     * Check if API request is allowed
     */
    public static function is_request_allowed($api_name, $endpoint = '') {
        $api_name = strtolower($api_name);
        
        // Check if API has rate limits configured
        if (!isset(self::$rate_limits[$api_name])) {
            rphub_error_manager()->log_error(
                "Unknown API for rate limiting: $api_name",
                ReplantaHub_Error_Manager::LEVEL_WARNING,
                ReplantaHub_Error_Manager::TYPE_API_ERROR,
                array('api_name' => $api_name, 'endpoint' => $endpoint)
            );
            return true; // Allow by default for unknown APIs
        }
        
        $current_time = time();
        $hour_window = $current_time - 3600; // 1 hour ago
        $minute_window = $current_time - 60; // 1 minute ago
        
        // Get request history from WordPress options
        $request_key = "rphub_rate_limit_$api_name";
        $requests = get_option($request_key, array());
        
        // Clean old requests (older than 1 hour)
        $requests = array_filter($requests, function($timestamp) use ($hour_window) {
            return $timestamp > $hour_window;
        });
        
        // Count requests in the last hour and minute
        $hourly_count = count($requests);
        $minute_count = count(array_filter($requests, function($timestamp) use ($minute_window) {
            return $timestamp > $minute_window;
        }));
        
        // Check hourly limit
        if ($hourly_count >= self::$rate_limits[$api_name]) {
            self::log_rate_limit_exceeded($api_name, 'hourly', $hourly_count, self::$rate_limits[$api_name]);
            return false;
        }
        
        // Check burst limit (per minute)
        if (isset(self::$burst_limits[$api_name]) && $minute_count >= self::$burst_limits[$api_name]) {
            self::log_rate_limit_exceeded($api_name, 'burst', $minute_count, self::$burst_limits[$api_name]);
            return false;
        }
        
        return true;
    }
    
    /**
     * Record an API request
     */
    public static function record_request($api_name, $endpoint = '', $response_code = 200) {
        $api_name = strtolower($api_name);
        $current_time = time();
        
        // Get current requests
        $request_key = "rphub_rate_limit_$api_name";
        $requests = get_option($request_key, array());
        
        // Add current request
        $requests[] = $current_time;
        
        // Keep only last hour of requests
        $hour_window = $current_time - 3600;
        $requests = array_filter($requests, function($timestamp) use ($hour_window) {
            return $timestamp > $hour_window;
        });
        
        // Store updated requests
        update_option($request_key, $requests, false); // Don't autoload
        
        // Log API usage statistics
        if (defined('RPHUB_DEBUG') && RPHUB_DEBUG) {
            rphub_error_manager()->log_error(
                sprintf('API request recorded: %s (%d requests in last hour)', $api_name, count($requests)),
                ReplantaHub_Error_Manager::LEVEL_DEBUG,
                ReplantaHub_Error_Manager::TYPE_API_ERROR,
                array(
                    'api_name' => $api_name,
                    'endpoint' => $endpoint,
                    'response_code' => $response_code,
                    'hourly_count' => count($requests)
                )
            );
        }
        
        // Check if approaching rate limit
        $limit = self::$rate_limits[$api_name] ?? 100;
        $usage_percentage = (count($requests) / $limit) * 100;
        
        if ($usage_percentage > 80) {
            rphub_error_manager()->log_error(
                sprintf('API rate limit warning: %s at %.1f%% usage (%d/%d requests)', 
                    $api_name, $usage_percentage, count($requests), $limit),
                ReplantaHub_Error_Manager::LEVEL_WARNING,
                ReplantaHub_Error_Manager::TYPE_API_ERROR,
                array(
                    'api_name' => $api_name,
                    'usage_percentage' => $usage_percentage,
                    'current_count' => count($requests),
                    'limit' => $limit
                )
            );
        }
    }
    
    /**
     * Get time until next request is allowed
     */
    public static function get_retry_after($api_name) {
        $api_name = strtolower($api_name);
        
        if (!isset(self::$rate_limits[$api_name])) {
            return 0;
        }
        
        $request_key = "rphub_rate_limit_$api_name";
        $requests = get_option($request_key, array());
        
        if (empty($requests)) {
            return 0;
        }
        
        $current_time = time();
        $hour_window = $current_time - 3600;
        $minute_window = $current_time - 60;
        
        // Clean old requests
        $requests = array_filter($requests, function($timestamp) use ($hour_window) {
            return $timestamp > $hour_window;
        });
        
        $hourly_count = count($requests);
        $minute_count = count(array_filter($requests, function($timestamp) use ($minute_window) {
            return $timestamp > $minute_window;
        }));
        
        // If hourly limit exceeded, wait until oldest request expires
        if ($hourly_count >= self::$rate_limits[$api_name]) {
            $oldest_request = min($requests);
            return max(0, $oldest_request + 3600 - $current_time);
        }
        
        // If burst limit exceeded, wait until oldest minute request expires
        if (isset(self::$burst_limits[$api_name]) && $minute_count >= self::$burst_limits[$api_name]) {
            $minute_requests = array_filter($requests, function($timestamp) use ($minute_window) {
                return $timestamp > $minute_window;
            });
            $oldest_minute_request = min($minute_requests);
            return max(0, $oldest_minute_request + 60 - $current_time);
        }
        
        return 0;
    }
    
    /**
     * Get current usage statistics for an API
     */
    public static function get_usage_stats($api_name) {
        $api_name = strtolower($api_name);
        
        if (!isset(self::$rate_limits[$api_name])) {
            return null;
        }
        
        $request_key = "rphub_rate_limit_$api_name";
        $requests = get_option($request_key, array());
        
        $current_time = time();
        $hour_window = $current_time - 3600;
        $minute_window = $current_time - 60;
        
        // Clean old requests
        $requests = array_filter($requests, function($timestamp) use ($hour_window) {
            return $timestamp > $hour_window;
        });
        
        $hourly_count = count($requests);
        $minute_count = count(array_filter($requests, function($timestamp) use ($minute_window) {
            return $timestamp > $minute_window;
        }));
        
        return array(
            'api_name' => $api_name,
            'hourly_limit' => self::$rate_limits[$api_name],
            'burst_limit' => self::$burst_limits[$api_name] ?? null,
            'hourly_usage' => $hourly_count,
            'minute_usage' => $minute_count,
            'hourly_percentage' => ($hourly_count / self::$rate_limits[$api_name]) * 100,
            'burst_percentage' => isset(self::$burst_limits[$api_name]) ? 
                ($minute_count / self::$burst_limits[$api_name]) * 100 : null,
            'retry_after' => self::get_retry_after($api_name)
        );
    }
    
    /**
     * Get usage stats for all APIs
     */
    public static function get_all_usage_stats() {
        $stats = array();
        
        foreach (array_keys(self::$rate_limits) as $api_name) {
            $stats[$api_name] = self::get_usage_stats($api_name);
        }
        
        return $stats;
    }
    
    /**
     * Execute API request with rate limiting
     */
    public static function execute_with_rate_limit($api_name, $callback, $endpoint = '') {
        if (!self::is_request_allowed($api_name, $endpoint)) {
            $retry_after = self::get_retry_after($api_name);
            
            return new WP_Error('rate_limit_exceeded', 
                sprintf('Rate limit exceeded for %s API. Retry after %d seconds.', $api_name, $retry_after),
                array('retry_after' => $retry_after)
            );
        }
        
        // Execute the callback
        $start_time = microtime(true);
        $result = call_user_func($callback);
        $execution_time = microtime(true) - $start_time;
        
        // Determine response code
        $response_code = 200;
        if (is_wp_error($result)) {
            $response_code = 500;
        }
        
        // Record the request
        self::record_request($api_name, $endpoint, $response_code);
        
        // Log execution
        rphub_error_manager()->log_error(
            sprintf('Rate-limited API call completed: %s (%s) - %.2fs', $api_name, $endpoint, $execution_time),
            ReplantaHub_Error_Manager::LEVEL_DEBUG,
            ReplantaHub_Error_Manager::TYPE_API_ERROR,
            array(
                'api_name' => $api_name,
                'endpoint' => $endpoint,
                'execution_time' => $execution_time,
                'response_code' => $response_code
            )
        );
        
        return $result;
    }
    
    /**
     * Clear rate limiting data for an API
     */
    public static function clear_rate_limit_data($api_name) {
        $api_name = strtolower($api_name);
        $request_key = "rphub_rate_limit_$api_name";
        delete_option($request_key);
        
        rphub_error_manager()->log_error(
            "Rate limit data cleared for API: $api_name",
            ReplantaHub_Error_Manager::LEVEL_INFO,
            ReplantaHub_Error_Manager::TYPE_API_ERROR,
            array('api_name' => $api_name)
        );
    }
    
    /**
     * Update rate limits (useful for custom configurations)
     */
    public static function update_rate_limits($limits) {
        self::$rate_limits = array_merge(self::$rate_limits, $limits);
    }
    
    /**
     * Update burst limits
     */
    public static function update_burst_limits($limits) {
        self::$burst_limits = array_merge(self::$burst_limits, $limits);
    }
    
    /**
     * Log rate limit exceeded event
     */
    private static function log_rate_limit_exceeded($api_name, $type, $current_count, $limit) {
        rphub_error_manager()->log_error(
            sprintf('Rate limit exceeded for %s API: %s limit reached (%d/%d)', 
                $api_name, $type, $current_count, $limit),
            ReplantaHub_Error_Manager::LEVEL_WARNING,
            ReplantaHub_Error_Manager::TYPE_API_ERROR,
            array(
                'api_name' => $api_name,
                'limit_type' => $type,
                'current_count' => $current_count,
                'limit' => $limit
            )
        );
    }
}

// Helper functions for easy usage
function rphub_is_api_request_allowed($api_name, $endpoint = '') {
    return ReplantaHub_Rate_Limiter::is_request_allowed($api_name, $endpoint);
}

function rphub_record_api_request($api_name, $endpoint = '', $response_code = 200) {
    return ReplantaHub_Rate_Limiter::record_request($api_name, $endpoint, $response_code);
}

function rphub_execute_with_rate_limit($api_name, $callback, $endpoint = '') {
    return ReplantaHub_Rate_Limiter::execute_with_rate_limit($api_name, $callback, $endpoint);
}

function rphub_get_api_usage_stats($api_name = null) {
    if ($api_name) {
        return ReplantaHub_Rate_Limiter::get_usage_stats($api_name);
    }
    return ReplantaHub_Rate_Limiter::get_all_usage_stats();
}
