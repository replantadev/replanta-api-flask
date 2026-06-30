<?php
/**
 * Utils Class
 * Utility functions for the hub
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPHUB_Utils {
    
    /**
     * Format bytes to human readable format
     */
    public static function format_bytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Format time duration
     */
    public static function format_duration($seconds) {
        if ($seconds < 60) {
            return round($seconds) . 's';
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $secs = $seconds % 60;
            return $minutes . 'm ' . round($secs) . 's';
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return $hours . 'h ' . $minutes . 'm';
        }
    }
    
    /**
     * Get time ago format
     */
    public static function time_ago($datetime) {
        if (empty($datetime)) {
            return 'Nunca';
        }
        
        $time = time() - strtotime($datetime);
        
        if ($time < 60) {
            return 'Hace ' . $time . ' segundos';
        } elseif ($time < 3600) {
            return 'Hace ' . round($time / 60) . ' minutos';
        } elseif ($time < 86400) {
            return 'Hace ' . round($time / 3600) . ' horas';
        } elseif ($time < 2592000) {
            return 'Hace ' . round($time / 86400) . ' días';
        } elseif ($time < 31536000) {
            return 'Hace ' . round($time / 2592000) . ' meses';
        } else {
            return 'Hace ' . round($time / 31536000) . ' años';
        }
    }
    
    /**
     * Get health score color
     */
    public static function get_health_score_color($score) {
        if ($score >= 90) {
            return '#10b981'; // Green
        } elseif ($score >= 70) {
            return '#f59e0b'; // Yellow
        } elseif ($score >= 50) {
            return '#f97316'; // Orange
        } else {
            return '#ef4444'; // Red
        }
    }
    
    /**
     * Get health score label
     */
    public static function get_health_score_label($score) {
        if ($score >= 90) {
            return 'Excelente';
        } elseif ($score >= 70) {
            return 'Bueno';
        } elseif ($score >= 50) {
            return 'Regular';
        } else {
            return 'Crítico';
        }
    }
    
    /**
     * Get plan features
     */
    public static function get_plan_features($plan) {
        $features = [
            'semilla' => [
                'name' => 'Semilla',
                'price' => '49€',
                'features' => [
                    'Actualizaciones automáticas',
                    'Monitoreo básico',
                    'Soporte por email',
                    'Reportes mensuales'
                ]
            ],
            'raiz' => [
                'name' => 'Raíz',
                'price' => '89€',
                'features' => [
                    'Todo lo de Semilla',
                    'Backups diarios',
                    'Scans de seguridad',
                    'Optimización de caché',
                    'Soporte prioritario',
                    'Reportes semanales'
                ]
            ],
            'ecosistema' => [
                'name' => 'Ecosistema',
                'price' => '149€',
                'features' => [
                    'Todo lo de Raíz',
                    'Monitoreo avanzado',
                    'Análisis de rendimiento',
                    'Integración WHM/cPanel',
                    'Soporte 24/7',
                    'Reportes personalizados'
                ]
            ]
        ];
        
        return $features[$plan] ?? $features['semilla'];
    }
    
    /**
     * Get severity color
     */
    public static function get_severity_color($severity) {
        $colors = [
            'info' => '#3b82f6',
            'warning' => '#f59e0b',
            'error' => '#ef4444',
            'critical' => '#dc2626'
        ];
        
        return $colors[$severity] ?? $colors['info'];
    }
    
    /**
     * Get task status color
     */
    public static function get_task_status_color($status) {
        $colors = [
            'pending' => '#6b7280',
            'running' => '#3b82f6',
            'completed' => '#10b981',
            'failed' => '#ef4444',
            'cancelled' => '#9ca3af'
        ];
        
        return $colors[$status] ?? $colors['pending'];
    }
    
    /**
     * Generate secure token
     */
    public static function generate_token($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Validate URL
     */
    public static function validate_url($url) {
        $url = filter_var($url, FILTER_VALIDATE_URL);
        
        if (!$url) {
            return false;
        }
        
        $parsed = parse_url($url);
        
        if (!isset($parsed['scheme']) || !in_array($parsed['scheme'], ['http', 'https'])) {
            return false;
        }
        
        return $url;
    }
    
    /**
     * Sanitize site name for file names
     */
    public static function sanitize_site_name($name) {
        $name = remove_accents($name);
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        $name = trim($name, '_-');
        
        return strtolower($name);
    }
    
    /**
     * Get WordPress version info
     */
    public static function get_wp_version_info($version) {
        global $wp_version;
        
        $current_version = $wp_version;
        $is_outdated = version_compare($version, $current_version, '<');
        
        return [
            'version' => $version,
            'current' => $current_version,
            'outdated' => $is_outdated,
            'major_update' => $is_outdated && substr($version, 0, 3) !== substr($current_version, 0, 3)
        ];
    }
    
    /**
     * Get PHP version info
     */
    public static function get_php_version_info($version) {
        $recommended_version = '8.0';
        $minimum_version = '7.4';
        
        $is_supported = version_compare($version, $minimum_version, '>=');
        $is_recommended = version_compare($version, $recommended_version, '>=');
        
        return [
            'version' => $version,
            'supported' => $is_supported,
            'recommended' => $is_recommended,
            'status' => $is_recommended ? 'good' : ($is_supported ? 'warning' : 'critical')
        ];
    }
    
    /**
     * Calculate percentage
     */
    public static function calculate_percentage($value, $total) {
        if ($total == 0) {
            return 0;
        }
        
        return round(($value / $total) * 100, 2);
    }
    
    /**
     * Generate chart colors
     */
    public static function generate_chart_colors($count) {
        $base_colors = [
            '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
            '#06b6d4', '#84cc16', '#f97316', '#ec4899', '#6366f1'
        ];
        
        $colors = [];
        for ($i = 0; $i < $count; $i++) {
            $colors[] = $base_colors[$i % count($base_colors)];
        }
        
        return $colors;
    }
    
    /**
     * Log hub activity
     */
    public static function log_activity($action, $details = [], $site_id = null) {
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'action' => $action,
            'details' => $details,
            'site_id' => $site_id,
            'user_id' => get_current_user_id(),
            'ip_address' => self::get_client_ip()
        ];
        
        // Store in option (or consider using a dedicated table)
        $logs = get_option('rphub_activity_log', []);
        array_unshift($logs, $log_entry);
        
        // Keep only last 1000 entries
        $logs = array_slice($logs, 0, 1000);
        
        update_option('rphub_activity_log', $logs);
    }
    
    /**
     * Get client IP address
     */
    public static function get_client_ip() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                
                if (filter_var(trim($ip), FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return trim($ip);
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Check if feature is available for plan
     */
    public static function is_feature_available($feature, $plan) {
        $plan_features = [
            'semilla' => [
                'basic_monitoring',
                'auto_updates',
                'monthly_reports'
            ],
            'raiz' => [
                'basic_monitoring',
                'auto_updates',
                'monthly_reports',
                'daily_backups',
                'security_scans',
                'cache_optimization',
                'weekly_reports'
            ],
            'ecosistema' => [
                'basic_monitoring',
                'auto_updates',
                'monthly_reports',
                'daily_backups',
                'security_scans',
                'cache_optimization',
                'weekly_reports',
                'advanced_monitoring',
                'performance_analysis',
                'whm_integration',
                'custom_reports'
            ]
        ];
        
        return in_array($feature, $plan_features[$plan] ?? []);
    }
    
    /**
     * Get dashboard statistics cache key
     */
    public static function get_cache_key($key, $site_id = null) {
        $base_key = 'rphub_' . $key;
        
        if ($site_id) {
            $base_key .= '_site_' . $site_id;
        }
        
        return $base_key;
    }
    
    /**
     * Cache data with expiration
     */
    public static function cache_set($key, $data, $expiration = 3600) {
        return set_transient(self::get_cache_key($key), $data, $expiration);
    }
    
    /**
     * Get cached data
     */
    public static function cache_get($key) {
        return get_transient(self::get_cache_key($key));
    }
    
    /**
     * Delete cached data
     */
    public static function cache_delete($key) {
        return delete_transient(self::get_cache_key($key));
    }
    
    /**
     * Clean expired cache entries
     */
    public static function clean_cache() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_timeout_rphub_%' 
             AND option_value < UNIX_TIMESTAMP()"
        );
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_rphub_%' 
             AND option_name NOT IN (
                 SELECT CONCAT('_transient_', SUBSTRING(option_name, 19)) 
                 FROM {$wpdb->options} t2 
                 WHERE t2.option_name LIKE '_transient_timeout_rphub_%'
             )"
        );
    }
}
