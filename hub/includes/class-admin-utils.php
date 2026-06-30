<?php
/**
 * Replanta Hub Admin Utilities
 * 
 * Utility functions for admin interface (no menu duplication)
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ReplantaHub_Admin_Utils {
    
    public function __construct() {
        // Only utility functions, no menu registration
        add_action('wp_ajax_rphub_run_system_tests', array($this, 'ajax_run_system_tests'));
        add_action('wp_ajax_rphub_run_integration_test', array($this, 'ajax_run_integration_test'));
    }
    
    /**
     * AJAX handler for system tests
     */
    public function ajax_run_system_tests() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        try {
            // Run comprehensive diagnostics
            if (function_exists('rphub_run_comprehensive_diagnostics')) {
                $results = rphub_run_comprehensive_diagnostics();
                wp_send_json_success($results);
            } else {
                wp_send_json_error('Función de diagnósticos no encontrada');
            }
        } catch (Exception $e) {
            wp_send_json_error('Error ejecutando pruebas: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX handler for individual integration tests
     */
    public function ajax_run_integration_test() {
        check_ajax_referer('rphub_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }
        
        $integration = sanitize_text_field($_POST['integration'] ?? '');
        
        if (empty($integration)) {
            wp_send_json_error('Integración no especificada');
        }
        
        try {
            $result = $this->test_integration($integration);
            if ($result['success']) {
                wp_send_json_success($result['message']);
            } else {
                wp_send_json_error($result['message']);
            }
        } catch (Exception $e) {
            wp_send_json_error('Error probando integración: ' . $e->getMessage());
        }
    }
    
    /**
     * Test individual integration
     */
    private function test_integration($integration) {
        switch ($integration) {
            case 'cloudflare':
                if (class_exists('ReplantaHub_Cloudflare_Integration')) {
                    return array('success' => true, 'message' => 'Cloudflare disponible');
                }
                break;
                
            case 'wptoolkit':
                if (class_exists('ReplantaHub_WPToolkit_Integration')) {
                    return array('success' => true, 'message' => 'WP Toolkit disponible');
                }
                break;
                
            case 'backuply':
                if (class_exists('ReplantaHub_Backuply_Integration')) {
                    return array('success' => true, 'message' => 'Backuply disponible');
                }
                break;
                
            case 'litespeed':
                if (class_exists('ReplantaHub_LiteSpeed_Integration')) {
                    return array('success' => true, 'message' => 'LiteSpeed disponible');
                }
                break;
                
            case 'pagespeed':
                if (class_exists('ReplantaHub_PageSpeed_Integration')) {
                    return array('success' => true, 'message' => 'PageSpeed disponible');
                }
                break;
                
            default:
                return array('success' => false, 'message' => 'Integración no reconocida');
        }
        
        return array('success' => false, 'message' => 'Integración no disponible');
    }
    
    /**
     * Get system status for debugging
     */
    public static function get_system_status() {
        $status = array();
        
        // Check database tables
        global $wpdb;
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}rphub_sites'");
        $status['database'] = $table_exists ? 'OK' : 'Missing Tables';
        
        // Check setup completion
        $status['setup'] = get_option('replanta_hub_setup_completed', false) ? 'Completed' : 'Pending';
        
        // Check integrations
        $integrations = array(
            'cloudflare' => 'ReplantaHub_Cloudflare_Integration',
            'wptoolkit' => 'ReplantaHub_WPToolkit_Integration', 
            'backuply' => 'ReplantaHub_Backuply_Integration',
            'litespeed' => 'ReplantaHub_LiteSpeed_Integration',
            'pagespeed' => 'ReplantaHub_PageSpeed_Integration'
        );
        
        $status['integrations'] = array();
        foreach ($integrations as $name => $class) {
            $status['integrations'][$name] = class_exists($class) ? 'Available' : 'Not Found';
        }
        
        return $status;
    }
}

// Initialize admin utilities
if (is_admin()) {
    new ReplantaHub_Admin_Utils();
}
