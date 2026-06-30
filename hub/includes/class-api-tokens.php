<?php
/**
 * API Token Management for Replanta Hub Professional
 * 
 * Handles API token generation, validation, and management
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ReplantaHub_API_Tokens {
    
    private $token_length = 64;
    private $default_permissions = array('read');
    
    public function __construct() {
        add_action('wp_ajax_rphub_generate_api_token', array($this, 'ajax_generate_token'));
        add_action('wp_ajax_rphub_revoke_api_token', array($this, 'ajax_revoke_token'));
        add_action('wp_ajax_rphub_list_api_tokens', array($this, 'ajax_list_tokens'));
        add_action('wp_ajax_rphub_update_api_token', array($this, 'ajax_update_token'));
        
        // Cleanup expired tokens daily
        add_action('rphub_daily_cleanup', array($this, 'cleanup_expired_tokens'));
    }
    
    /**
     * Generate new API token
     */
    public function generate_token($user_id, $name, $permissions = array(), $expires_in = null) {
        global $wpdb;
        
        if (empty($permissions)) {
            $permissions = $this->default_permissions;
        }
        
        // Generate secure token
        $token = $this->generate_secure_token();
        $token_hash = hash('sha256', $token);
        
        // Calculate expiration
        $expires_at = null;
        if ($expires_in) {
            $expires_at = $this->calculate_expiration($expires_in);
        }
        
        // Insert token into database
        $result = $wpdb->insert(
            $wpdb->prefix . 'rphub_api_tokens',
            array(
                'user_id' => $user_id,
                'name' => $name,
                'token_hash' => $token_hash,
                'permissions' => json_encode($permissions),
                'status' => 'active',
                'expires_at' => $expires_at,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            return new WP_Error('token_creation_failed', 'Failed to create API token');
        }
        
        $token_id = $wpdb->insert_id;
        
        // Create default quota
        $this->create_default_quota($token_id);
        
        // Log token creation
        $this->log_token_activity($token_id, 'created', array(
            'user_id' => $user_id,
            'permissions' => $permissions
        ));
        
        return array(
            'token_id' => $token_id,
            'token' => $token, // Only returned once!
            'name' => $name,
            'permissions' => $permissions,
            'expires_at' => $expires_at
        );
    }
    
    /**
     * Validate API token
     */
    public function validate_token($token) {
        global $wpdb;
        
        $token_hash = hash('sha256', $token);
        
        $token_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rphub_api_tokens 
             WHERE token_hash = %s 
             AND status = 'active' 
             AND (expires_at IS NULL OR expires_at > NOW())",
            $token_hash
        ));
        
        if (!$token_data) {
            return false;
        }
        
        // Update last used timestamp
        $wpdb->update(
            $wpdb->prefix . 'rphub_api_tokens',
            array('last_used_at' => current_time('mysql')),
            array('id' => $token_data->id),
            array('%s'),
            array('%d')
        );
        
        return array(
            'token_id' => $token_data->id,
            'user_id' => $token_data->user_id,
            'name' => $token_data->name,
            'permissions' => json_decode($token_data->permissions, true),
            'created_at' => $token_data->created_at,
            'last_used_at' => current_time('mysql'),
            'expires_at' => $token_data->expires_at
        );
    }
    
    /**
     * Revoke API token
     */
    public function revoke_token($token_id, $user_id = null) {
        global $wpdb;
        
        $where = array('id' => $token_id);
        $where_format = array('%d');
        
        // If user_id provided, ensure user can only revoke their own tokens
        if ($user_id && !current_user_can('manage_options')) {
            $where['user_id'] = $user_id;
            $where_format[] = '%d';
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'rphub_api_tokens',
            array(
                'status' => 'revoked',
                'updated_at' => current_time('mysql')
            ),
            $where,
            array('%s', '%s'),
            $where_format
        );
        
        if ($result === false) {
            return new WP_Error('revoke_failed', 'Failed to revoke token');
        }
        
        if ($result === 0) {
            return new WP_Error('token_not_found', 'Token not found or access denied');
        }
        
        // Log token revocation
        $this->log_token_activity($token_id, 'revoked', array(
            'revoked_by_user_id' => get_current_user_id()
        ));
        
        return true;
    }
    
    /**
     * List API tokens for user
     */
    public function list_tokens($user_id = null, $include_revoked = false) {
        global $wpdb;
        
        $where_conditions = array();
        $where_values = array();
        
        if ($user_id) {
            $where_conditions[] = 'user_id = %d';
            $where_values[] = $user_id;
        }
        
        if (!$include_revoked) {
            $where_conditions[] = "status != 'revoked'";
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $query = "SELECT 
                    id, user_id, name, permissions, status, 
                    last_used_at, expires_at, created_at, updated_at
                  FROM {$wpdb->prefix}rphub_api_tokens 
                  {$where_clause} 
                  ORDER BY created_at DESC";
        
        $tokens = $wpdb->get_results(!empty($where_values) ? $wpdb->prepare($query, ...$where_values) : $query);
        
        // Format tokens for response
        $formatted_tokens = array();
        foreach ($tokens as $token) {
            $formatted_tokens[] = array(
                'id' => intval($token->id),
                'user_id' => intval($token->user_id),
                'name' => $token->name,
                'permissions' => json_decode($token->permissions, true),
                'status' => $token->status,
                'last_used_at' => $token->last_used_at,
                'expires_at' => $token->expires_at,
                'is_expired' => $token->expires_at && strtotime($token->expires_at) < time(),
                'created_at' => $token->created_at,
                'updated_at' => $token->updated_at
            );
        }
        
        return $formatted_tokens;
    }
    
    /**
     * Update token permissions
     */
    public function update_token_permissions($token_id, $permissions, $user_id = null) {
        global $wpdb;
        
        $where = array('id' => $token_id);
        $where_format = array('%d');
        
        // If user_id provided, ensure user can only update their own tokens
        if ($user_id && !current_user_can('manage_options')) {
            $where['user_id'] = $user_id;
            $where_format[] = '%d';
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'rphub_api_tokens',
            array(
                'permissions' => json_encode($permissions),
                'updated_at' => current_time('mysql')
            ),
            $where,
            array('%s', '%s'),
            $where_format
        );
        
        if ($result === false) {
            return new WP_Error('update_failed', 'Failed to update token');
        }
        
        if ($result === 0) {
            return new WP_Error('token_not_found', 'Token not found or access denied');
        }
        
        // Log permission update
        $this->log_token_activity($token_id, 'permissions_updated', array(
            'new_permissions' => $permissions,
            'updated_by_user_id' => get_current_user_id()
        ));
        
        return true;
    }
    
    /**
     * Check if token has specific permission
     */
    public function has_permission($token_data, $required_permission) {
        if (!$token_data || !isset($token_data['permissions'])) {
            return false;
        }
        
        $permissions = $token_data['permissions'];
        
        // Admin permission grants all access
        if (in_array('admin', $permissions)) {
            return true;
        }
        
        // Check for specific permission
        return in_array($required_permission, $permissions);
    }
    
    /**
     * Get token usage statistics
     */
    public function get_token_stats($token_id) {
        global $wpdb;
        
        $stats = array();
        
        // Get basic token info
        $token = $wpdb->get_row($wpdb->prepare(
            "SELECT name, status, created_at, last_used_at FROM {$wpdb->prefix}rphub_api_tokens WHERE id = %d",
            $token_id
        ));
        
        if (!$token) {
            return new WP_Error('token_not_found', 'Token not found');
        }
        
        // Get usage statistics from API logs
        $usage_stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_requests,
                COUNT(CASE WHEN response_status >= 200 AND response_status < 400 THEN 1 END) as successful_requests,
                COUNT(CASE WHEN response_status >= 400 THEN 1 END) as failed_requests,
                AVG(execution_time) as avg_execution_time,
                MAX(created_at) as last_request_at
            FROM {$wpdb->prefix}rphub_api_logs 
            WHERE token_id = %d
        ", $token_id));
        
        // Get recent activity (last 30 days)
        $recent_activity = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as requests,
                AVG(execution_time) as avg_time
            FROM {$wpdb->prefix}rphub_api_logs 
            WHERE token_id = %d 
            AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ", $token_id));
        
        // Get quota information
        $quota_info = $wpdb->get_row($wpdb->prepare("
            SELECT quota_limit, quota_used, quota_type, reset_at
            FROM {$wpdb->prefix}rphub_api_quotas 
            WHERE token_id = %d
        ", $token_id));
        
        return array(
            'token' => array(
                'name' => $token->name,
                'status' => $token->status,
                'created_at' => $token->created_at,
                'last_used_at' => $token->last_used_at
            ),
            'usage' => array(
                'total_requests' => intval($usage_stats->total_requests ?? 0),
                'successful_requests' => intval($usage_stats->successful_requests ?? 0),
                'failed_requests' => intval($usage_stats->failed_requests ?? 0),
                'success_rate' => $usage_stats->total_requests > 0 ? 
                    round(($usage_stats->successful_requests / $usage_stats->total_requests) * 100, 2) : 0,
                'avg_execution_time' => round($usage_stats->avg_execution_time ?? 0, 4),
                'last_request_at' => $usage_stats->last_request_at
            ),
            'quota' => $quota_info ? array(
                'limit' => intval($quota_info->quota_limit),
                'used' => intval($quota_info->quota_used),
                'remaining' => intval($quota_info->quota_limit) - intval($quota_info->quota_used),
                'type' => $quota_info->quota_type,
                'reset_at' => $quota_info->reset_at
            ) : null,
            'recent_activity' => $recent_activity
        );
    }
    
    /**
     * AJAX Handlers
     */
    
    public function ajax_generate_token() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $name = sanitize_text_field($_POST['name']);
        $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : array();
        $expires_in = sanitize_text_field($_POST['expires_in'] ?? '1y');
        
        if (empty($name)) {
            wp_send_json_error('Token name is required');
        }
        
        // Validate permissions
        $allowed_permissions = array('read', 'write', 'delete', 'admin');
        $permissions = array_intersect($permissions, $allowed_permissions);
        
        if (empty($permissions)) {
            $permissions = array('read');
        }
        
        $result = $this->generate_token(get_current_user_id(), $name, $permissions, $expires_in);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_revoke_token() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $token_id = intval($_POST['token_id']);
        
        if (!$token_id) {
            wp_send_json_error('Token ID is required');
        }
        
        $result = $this->revoke_token($token_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success('Token revoked successfully');
    }
    
    public function ajax_list_tokens() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $include_revoked = (bool) ($_POST['include_revoked'] ?? false);
        $user_id = current_user_can('manage_options') ? null : get_current_user_id();
        
        $tokens = $this->list_tokens($user_id, $include_revoked);
        
        wp_send_json_success($tokens);
    }
    
    public function ajax_update_token() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $token_id = intval($_POST['token_id']);
        $permissions = $_POST['permissions'] ?? array();
        
        if (!$token_id) {
            wp_send_json_error('Token ID is required');
        }
        
        // Validate permissions
        $allowed_permissions = array('read', 'write', 'delete', 'admin');
        $permissions = array_intersect($permissions, $allowed_permissions);
        
        $result = $this->update_token_permissions($token_id, $permissions);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success('Token updated successfully');
    }
    
    /**
     * Helper Methods
     */
    
    private function generate_secure_token() {
        return wp_generate_password($this->token_length, false, false);
    }
    
    private function calculate_expiration($expires_in) {
        $multipliers = array(
            'd' => 86400,    // days
            'w' => 604800,   // weeks
            'm' => 2592000,  // months (30 days)
            'y' => 31536000  // years (365 days)
        );
        
        $unit = substr($expires_in, -1);
        $value = intval(substr($expires_in, 0, -1));
        
        if (!isset($multipliers[$unit]) || $value <= 0) {
            return null; // No expiration
        }
        
        return date('Y-m-d H:i:s', time() + ($value * $multipliers[$unit]));
    }
    
    private function create_default_quota($token_id) {
        global $wpdb;
        
        $default_quotas = array(
            array(
                'quota_type' => 'daily',
                'quota_limit' => 10000,
                'reset_at' => date('Y-m-d 23:59:59')
            ),
            array(
                'quota_type' => 'monthly',
                'quota_limit' => 300000,
                'reset_at' => date('Y-m-t 23:59:59')
            )
        );
        
        foreach ($default_quotas as $quota) {
            $wpdb->insert(
                $wpdb->prefix . 'rphub_api_quotas',
                array_merge(array('token_id' => $token_id), $quota),
                array('%d', '%s', '%d', '%s')
            );
        }
    }
    
    private function log_token_activity($token_id, $action, $metadata = array()) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'rphub_api_logs',
            array(
                'token_id' => $token_id,
                'client_ip' => $this->get_client_ip(),
                'method' => 'SYSTEM',
                'endpoint' => 'token_management',
                'request_data' => json_encode(array(
                    'action' => $action,
                    'metadata' => $metadata
                )),
                'response_status' => 200,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%d', '%s')
        );
    }
    
    private function get_client_ip() {
        $ip_headers = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    /**
     * Cleanup expired tokens
     */
    public function cleanup_expired_tokens() {
        global $wpdb;
        
        // Mark expired tokens as expired
        $wpdb->query("
            UPDATE {$wpdb->prefix}rphub_api_tokens 
            SET status = 'expired' 
            WHERE status = 'active' 
            AND expires_at IS NOT NULL 
            AND expires_at < NOW()
        ");
        
        // Clean up very old revoked/expired tokens (keep for 1 year)
        $wpdb->query("
            DELETE FROM {$wpdb->prefix}rphub_api_tokens 
            WHERE status IN ('revoked', 'expired') 
            AND updated_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)
        ");
        
        error_log('RPHUB: API token cleanup completed');
    }
}

// Initialize API tokens manager
new ReplantaHub_API_Tokens();
