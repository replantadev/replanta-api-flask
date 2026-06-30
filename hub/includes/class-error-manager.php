<?php
/**
 * Centralized Error Manager for Replanta Hub
 * 
 * Handles all error logging, reporting, and debugging functionality
 * across all integrations and systems.
 *
 * @package ReplantaHub
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ReplantaHub_Error_Manager {
    
    /**
     * Error levels constants
     */
    const LEVEL_DEBUG = 1;
    const LEVEL_INFO = 2;
    const LEVEL_WARNING = 3;
    const LEVEL_ERROR = 4;
    const LEVEL_CRITICAL = 5;
    
    /**
     * Error types constants
     */
    const TYPE_API_ERROR = 'api_error';
    const TYPE_DB_ERROR = 'database_error';
    const TYPE_CONFIG_ERROR = 'config_error';
    const TYPE_INTEGRATION_ERROR = 'integration_error';
    const TYPE_SYSTEM_ERROR = 'system_error';
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Error log storage
     */
    private $error_log = array();
    
    /**
     * Debug mode flag
     */
    private $debug_mode = false;
    
    /**
     * Log file path
     */
    private $log_file_path = '';
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->debug_mode = defined('RPHUB_DEBUG') && RPHUB_DEBUG;
        $this->log_file_path = WP_CONTENT_DIR . '/uploads/replanta-hub-errors.log';
        
        // Ensure log directory exists
        $this->ensure_log_directory();
        
        // Register error handlers
        $this->register_handlers();
    }
    
    /**
     * Log an error with context
     */
    public function log_error($message, $level = self::LEVEL_ERROR, $type = self::TYPE_SYSTEM_ERROR, $context = array()) {
        $error_data = array(
            'timestamp' => current_time('mysql'),
            'level' => $level,
            'type' => $type,
            'message' => $message,
            'context' => $context,
            'backtrace' => $this->debug_mode ? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5) : null
        );
        
        // Store in memory log
        $this->error_log[] = $error_data;
        
        // Write to file if critical or debug mode
        if ($level >= self::LEVEL_ERROR || $this->debug_mode) {
            $this->write_to_log($error_data);
        }
        
        // Send to WordPress error log for critical errors
        if ($level >= self::LEVEL_CRITICAL) {
            error_log('ReplantaHub Critical Error: ' . $message . ' | Context: ' . json_encode($context));
        }
        
        // Trigger WordPress action for extensibility
        do_action('replanta_hub_error_logged', $error_data);
        
        return true;
    }
    
    /**
     * Log API errors specifically
     */
    public function log_api_error($api_name, $endpoint, $response_code, $error_message, $context = array()) {
        $enhanced_context = array_merge($context, array(
            'api_name' => $api_name,
            'endpoint' => $endpoint,
            'response_code' => $response_code,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ));
        
        return $this->log_error(
            sprintf('API Error in %s: %s (Code: %s)', $api_name, $error_message, $response_code),
            self::LEVEL_ERROR,
            self::TYPE_API_ERROR,
            $enhanced_context
        );
    }
    
    /**
     * Log database errors
     */
    public function log_db_error($query, $error_message, $context = array()) {
        $enhanced_context = array_merge($context, array(
            'query' => $query,
            'mysql_error' => $error_message
        ));
        
        return $this->log_error(
            'Database Error: ' . $error_message,
            self::LEVEL_ERROR,
            self::TYPE_DB_ERROR,
            $enhanced_context
        );
    }
    
    /**
     * Log configuration errors
     */
    public function log_config_error($setting, $error_message, $context = array()) {
        $enhanced_context = array_merge($context, array(
            'setting' => $setting,
            'current_value' => get_option($setting, 'NOT_SET')
        ));
        
        return $this->log_error(
            sprintf('Configuration Error for %s: %s', $setting, $error_message),
            self::LEVEL_WARNING,
            self::TYPE_CONFIG_ERROR,
            $enhanced_context
        );
    }
    
    /**
     * Log integration-specific errors
     */
    public function log_integration_error($integration_name, $function, $error_message, $context = array()) {
        $enhanced_context = array_merge($context, array(
            'integration' => $integration_name,
            'function' => $function
        ));
        
        return $this->log_error(
            sprintf('Integration Error in %s::%s: %s', $integration_name, $function, $error_message),
            self::LEVEL_ERROR,
            self::TYPE_INTEGRATION_ERROR,
            $enhanced_context
        );
    }
    
    /**
     * Get recent errors for debugging
     */
    public function get_recent_errors($limit = 50, $level = null) {
        $errors = $this->error_log;
        
        if ($level !== null) {
            $errors = array_filter($errors, function($error) use ($level) {
                return $error['level'] >= $level;
            });
        }
        
        return array_slice(array_reverse($errors), 0, $limit);
    }
    
    /**
     * Clear error log
     */
    public function clear_log() {
        $this->error_log = array();
        if (file_exists($this->log_file_path)) {
            unlink($this->log_file_path);
        }
        return true;
    }
    
    /**
     * Get error statistics
     */
    public function get_error_stats() {
        $stats = array(
            'total_errors' => count($this->error_log),
            'by_level' => array(),
            'by_type' => array(),
            'recent_24h' => 0
        );
        
        $yesterday = strtotime('-24 hours');
        
        foreach ($this->error_log as $error) {
            // Count by level
            $level_name = $this->get_level_name($error['level']);
            $stats['by_level'][$level_name] = ($stats['by_level'][$level_name] ?? 0) + 1;
            
            // Count by type
            $stats['by_type'][$error['type']] = ($stats['by_type'][$error['type']] ?? 0) + 1;
            
            // Count recent errors
            if (strtotime($error['timestamp']) > $yesterday) {
                $stats['recent_24h']++;
            }
        }
        
        return $stats;
    }
    
    /**
     * Write error to log file
     */
    private function write_to_log($error_data) {
        $log_entry = sprintf(
            "[%s] %s - %s: %s | Context: %s\n",
            $error_data['timestamp'],
            $this->get_level_name($error_data['level']),
            $error_data['type'],
            $error_data['message'],
            json_encode($error_data['context'])
        );
        
        file_put_contents($this->log_file_path, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Ensure log directory exists
     */
    private function ensure_log_directory() {
        $upload_dir = wp_upload_dir();
        if (!file_exists($upload_dir['basedir'])) {
            wp_mkdir_p($upload_dir['basedir']);
        }
    }
    
    /**
     * Register error handlers
     */
    private function register_handlers() {
        // Hook into WordPress error handling
        add_action('wp_die_handler', array($this, 'handle_wp_die'));
        
        // Hook into database errors
        add_action('wp_db_error', array($this, 'handle_db_error'));
    }
    
    /**
     * Handle WordPress die events
     */
    public function handle_wp_die($message) {
        if (is_string($message)) {
            $this->log_error('WordPress Die: ' . $message, self::LEVEL_CRITICAL);
        }
    }
    
    /**
     * Handle database errors
     */
    public function handle_db_error($error) {
        global $wpdb;
        $this->log_db_error($wpdb->last_query, $wpdb->last_error);
    }
    
    /**
     * Get human-readable level name
     */
    private function get_level_name($level) {
        $levels = array(
            self::LEVEL_DEBUG => 'DEBUG',
            self::LEVEL_INFO => 'INFO',
            self::LEVEL_WARNING => 'WARNING',
            self::LEVEL_ERROR => 'ERROR',
            self::LEVEL_CRITICAL => 'CRITICAL'
        );
        
        return $levels[$level] ?? 'UNKNOWN';
    }
    
    /**
     * Debug helper - output errors to screen
     */
    public function debug_output() {
        if (!$this->debug_mode) {
            return;
        }
        
        $recent_errors = $this->get_recent_errors(10);
        if (!empty($recent_errors)) {
            echo '<div style="background: #f1f1f1; padding: 10px; margin: 10px; border-left: 4px solid #dc3232;">';
            echo '<strong>ReplantaHub Debug - Recent Errors:</strong><br>';
            foreach ($recent_errors as $error) {
                echo sprintf(
                    '<small>[%s] %s: %s</small><br>',
                    $error['timestamp'],
                    $this->get_level_name($error['level']),
                    esc_html($error['message'])
                );
            }
            echo '</div>';
        }
    }
}

// Initialize error manager
function rphub_error_manager() {
    return ReplantaHub_Error_Manager::get_instance();
}

// Convenience functions for quick error logging
function rphub_log_error($message, $level = ReplantaHub_Error_Manager::LEVEL_ERROR, $context = array()) {
    return rphub_error_manager()->log_error($message, $level, ReplantaHub_Error_Manager::TYPE_SYSTEM_ERROR, $context);
}

function rphub_log_api_error($api_name, $endpoint, $response_code, $error_message, $context = array()) {
    return rphub_error_manager()->log_api_error($api_name, $endpoint, $response_code, $error_message, $context);
}

function rphub_log_db_error($query, $error_message, $context = array()) {
    return rphub_error_manager()->log_db_error($query, $error_message, $context);
}

function rphub_log_integration_error($integration, $function, $error_message, $context = array()) {
    return rphub_error_manager()->log_integration_error($integration, $function, $error_message, $context);
}
