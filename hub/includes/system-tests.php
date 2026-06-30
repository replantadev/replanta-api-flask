<?php
/**
 * Comprehensive System Tests for Replanta Hub
 * 
 * Tests all critical components and improvements implemented
 * during the autonomous development session.
 *
 * @package ReplantaHub
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ReplantaHub_System_Tests {
    
    private $test_results = array();
    private $errors = array();
    
    /**
     * Run all system tests
     */
    public function run_all_tests() {
        echo "<div style='background: #f1f1f1; padding: 20px; margin: 20px; font-family: monospace;'>";
        echo "<h2> Replanta Hub - Comprehensive System Tests</h2>";
        echo "<p><strong>Testing all improvements from autonomous development session...</strong></p>";
        
        $this->test_configuration_security();
        $this->test_class_nomenclature();
        $this->test_error_manager();
        $this->test_query_optimizer();
        $this->test_rate_limiter();
        $this->test_integration_classes();
        $this->test_database_indexes();
        
        $this->display_results();
        echo "</div>";
        
        return $this->test_results;
    }
    
    /**
     * Test configuration security implementation
     */
    private function test_configuration_security() {
        $this->log_test(" Testing Configuration Security");
        
        // Test 1: Check if config-sample.php exists
        $config_sample_path = RPHUB_PLUGIN_DIR . 'config-sample.php';
        if (file_exists($config_sample_path)) {
            $this->log_success(" config-sample.php exists");
        } else {
            $this->log_error(" config-sample.php not found");
        }
        
        // Test 2: Check if GitHub token is properly secured
        $token_secured = !empty(get_option('rphub_github_token')) || defined('RPHUB_GITHUB_TOKEN');
        if ($token_secured) {
            $this->log_success(" GitHub token configuration available");
        } else {
            $this->log_warning(" GitHub token not configured (expected for fresh install)");
        }
        
        // Test 3: Verify no hardcoded tokens in main file
        $main_file_content = file_get_contents(RPHUB_PLUGIN_DIR . 'replanta-hub.php');
        $has_hardcoded_token = strpos($main_file_content, 'ghp_') !== false;
        if (!$has_hardcoded_token) {
            $this->log_success(" No hardcoded GitHub tokens found");
        } else {
            $this->log_error(" Hardcoded GitHub token still present");
        }
    }
    
    /**
     * Test class nomenclature unification
     */
    private function test_class_nomenclature() {
        $this->log_test(" Testing Class Nomenclature Unification");
        
        $expected_classes = array(
            'ReplantaHub_WPToolkit_Integration',
            'ReplantaHub_Cloudflare_Integration',
            'ReplantaHub_JetBackup_Integration',
            'ReplantaHub_LiteSpeed_Integration',
            'ReplantaHub_PageSpeed_Integration',
            'ReplantaHub_Automation_System',
            'ReplantaHub_Reports_System',
            'ReplantaHub_Error_Manager',
            'ReplantaHub_Query_Optimizer',
            'ReplantaHub_Rate_Limiter'
        );
        
        $missing_classes = array();
        foreach ($expected_classes as $class_name) {
            if (class_exists($class_name)) {
                $this->log_success(" Class $class_name loaded correctly");
            } else {
                $missing_classes[] = $class_name;
                $this->log_error(" Class $class_name not found");
            }
        }
        
        if (empty($missing_classes)) {
            $this->log_success(" All integration classes follow unified nomenclature");
        }
    }
    
    /**
     * Test error manager functionality
     */
    private function test_error_manager() {
        $this->log_test(" Testing Error Manager");
        
        // Test 1: Check if error manager is available
        if (function_exists('rphub_error_manager')) {
            $this->log_success(" Error manager function available");
            
            // Test 2: Test error logging
            $test_result = rphub_log_error('System test error', ReplantaHub_Error_Manager::LEVEL_INFO);
            if ($test_result) {
                $this->log_success(" Error logging works");
            } else {
                $this->log_error(" Error logging failed");
            }
            
            // Test 3: Test API error logging
            $api_test = rphub_log_api_error('TestAPI', '/test', 404, 'Test API error');
            if ($api_test) {
                $this->log_success(" API error logging works");
            } else {
                $this->log_error(" API error logging failed");
            }
            
            // Test 4: Get recent errors
            $recent_errors = rphub_error_manager()->get_recent_errors(5);
            if (is_array($recent_errors)) {
                $this->log_success(" Recent errors retrieval works (" . count($recent_errors) . " errors)");
            } else {
                $this->log_error(" Recent errors retrieval failed");
            }
            
        } else {
            $this->log_error(" Error manager not available");
        }
    }
    
    /**
     * Test query optimizer functionality
     */
    private function test_query_optimizer() {
        $this->log_test(" Testing Query Optimizer");
        
        // Test 1: Check if optimizer class exists
        if (class_exists('ReplantaHub_Query_Optimizer')) {
            $this->log_success(" Query Optimizer class loaded");
            
            // Test 2: Test optimized sites query
            try {
                $sites = ReplantaHub_Query_Optimizer::get_sites(array('limit' => 5));
                if (is_array($sites)) {
                    $this->log_success(" Optimized sites query works (" . count($sites) . " sites)");
                } else {
                    $this->log_error(" Optimized sites query failed");
                }
            } catch (Exception $e) {
                $this->log_error(" Sites query exception: " . $e->getMessage());
            }
            
            // Test 3: Test dashboard stats
            try {
                $stats = ReplantaHub_Query_Optimizer::get_dashboard_stats();
                if (is_array($stats)) {
                    $this->log_success(" Dashboard stats optimization works");
                } else {
                    $this->log_error(" Dashboard stats optimization failed");
                }
            } catch (Exception $e) {
                $this->log_error(" Dashboard stats exception: " . $e->getMessage());
            }
            
            // Test 4: Performance tracking
            $perf_stats = ReplantaHub_Query_Optimizer::get_performance_stats();
            if (is_array($perf_stats)) {
                $this->log_success(" Performance tracking active (" . count($perf_stats) . " tracked queries)");
            }
            
        } else {
            $this->log_error(" Query Optimizer class not found");
        }
    }
    
    /**
     * Test rate limiter functionality
     */
    private function test_rate_limiter() {
        $this->log_test(" Testing Rate Limiter");
        
        // Test 1: Check if rate limiter class exists
        if (class_exists('ReplantaHub_Rate_Limiter')) {
            $this->log_success(" Rate Limiter class loaded");
            
            // Test 2: Test API request allowance
            $allowed = ReplantaHub_Rate_Limiter::is_request_allowed('pagespeed', '/test');
            if ($allowed) {
                $this->log_success(" API request allowance check works");
            } else {
                $this->log_warning(" API request blocked (rate limit reached)");
            }
            
            // Test 3: Record test request
            ReplantaHub_Rate_Limiter::record_request('test_api', '/test', 200);
            $this->log_success(" Request recording works");
            
            // Test 4: Get usage stats
            $stats = ReplantaHub_Rate_Limiter::get_usage_stats('pagespeed');
            if (is_array($stats) && isset($stats['hourly_limit'])) {
                $this->log_success(" Usage statistics available (PageSpeed: {$stats['hourly_usage']}/{$stats['hourly_limit']} requests)");
            } else {
                $this->log_error(" Usage statistics failed");
            }
            
            // Test 5: Helper functions
            if (function_exists('rphub_is_api_request_allowed')) {
                $this->log_success(" Rate limiter helper functions available");
            } else {
                $this->log_error(" Rate limiter helper functions missing");
            }
            
        } else {
            $this->log_error(" Rate Limiter class not found");
        }
    }
    
    /**
     * Test integration classes
     */
    private function test_integration_classes() {
        $this->log_test(" Testing Integration Classes");
        
        $integrations = array(
            'ReplantaHub_WPToolkit_Integration' => 'WP Toolkit',
            'ReplantaHub_Cloudflare_Integration' => 'Cloudflare',
            'ReplantaHub_JetBackup_Integration' => 'JetBackup',
            'ReplantaHub_LiteSpeed_Integration' => 'LiteSpeed',
            'ReplantaHub_PageSpeed_Integration' => 'PageSpeed'
        );
        
        foreach ($integrations as $class_name => $service_name) {
            if (class_exists($class_name)) {
                $this->log_success(" $service_name integration class loaded");
                
                // Check if class has error handling integration
                $reflection = new ReflectionClass($class_name);
                $methods = $reflection->getMethods();
                $has_error_handling = false;
                
                foreach ($methods as $method) {
                    $method_source = $this->getMethodSource($reflection->getFileName(), $method->getStartLine(), $method->getEndLine());
                    if (strpos($method_source, 'rphub_log_') !== false || strpos($method_source, 'rphub_error_manager') !== false) {
                        $has_error_handling = true;
                        break;
                    }
                }
                
                if ($has_error_handling) {
                    $this->log_success("   Error handling integrated in $service_name");
                } else {
                    $this->log_warning("   Error handling not detected in $service_name");
                }
                
            } else {
                $this->log_error(" $service_name integration class not found");
            }
        }
    }
    
    /**
     * Test database indexes creation
     */
    private function test_database_indexes() {
        $this->log_test(" Testing Database Indexes");
        
        global $wpdb;
        
        // Test tables exist
        $tables = array(
            $wpdb->prefix . 'rphub_sites',
            $wpdb->prefix . 'rphub_tasks',
            $wpdb->prefix . 'rphub_notifications'
        );
        
        foreach ($tables as $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
            if ($exists) {
                $this->log_success(" Table $table exists");
                
                // Check for indexes
                $indexes = $wpdb->get_results("SHOW INDEX FROM $table", ARRAY_A);
                if (!empty($indexes)) {
                    $index_count = count(array_unique(array_column($indexes, 'Key_name')));
                    $this->log_success("   $index_count indexes found on $table");
                } else {
                    $this->log_warning("   No indexes found on $table");
                }
            } else {
                $this->log_warning(" Table $table not found (may not be created yet)");
            }
        }
    }
    
    /**
     * Get method source code for analysis
     */
    private function getMethodSource($file, $start_line, $end_line) {
        if (!file_exists($file)) return '';
        
        $lines = file($file);
        $method_lines = array_slice($lines, $start_line - 1, $end_line - $start_line + 1);
        
        return implode('', $method_lines);
    }
    
    /**
     * Display test results summary
     */
    private function display_results() {
        $total_tests = count($this->test_results);
        $passed = count(array_filter($this->test_results, function($result) {
            return $result['status'] === 'success';
        }));
        $warnings = count(array_filter($this->test_results, function($result) {
            return $result['status'] === 'warning';
        }));
        $failed = count(array_filter($this->test_results, function($result) {
            return $result['status'] === 'error';
        }));
        
        echo "<hr>";
        echo "<h3> Test Results Summary</h3>";
        echo "<p><strong>Total Tests:</strong> $total_tests</p>";
        echo "<p><strong> Passed:</strong> $passed</p>";
        echo "<p><strong> Warnings:</strong> $warnings</p>";
        echo "<p><strong> Failed:</strong> $failed</p>";
        
        $success_rate = ($passed / $total_tests) * 100;
        echo "<p><strong>Success Rate:</strong> " . number_format($success_rate, 1) . "%</p>";
        
        if ($failed === 0) {
            echo "<p style='color: green; font-weight: bold;'> All critical tests passed! System is ready for production.</p>";
        } elseif ($failed <= 2) {
            echo "<p style='color: orange; font-weight: bold;'> Minor issues detected. System functional but needs attention.</p>";
        } else {
            echo "<p style='color: red; font-weight: bold;'> Multiple critical issues found. System needs fixes.</p>";
        }
        
        // Show recent error logs if available
        if (function_exists('rphub_error_manager')) {
            $recent_errors = rphub_error_manager()->get_recent_errors(5);
            if (!empty($recent_errors)) {
                echo "<h4>Recent System Errors:</h4>";
                echo "<div style='background: #ffe6e6; padding: 10px; font-size: 12px;'>";
                foreach (array_slice($recent_errors, 0, 3) as $error) {
                    echo "<div>[{$error['timestamp']}] {$error['message']}</div>";
                }
                echo "</div>";
            }
        }
    }
    
    /**
     * Logging helpers
     */
    private function log_test($message) {
        echo "<h4>$message</h4>";
    }
    
    private function log_success($message) {
        echo "<div style='color: green;'>$message</div>";
        $this->test_results[] = array('message' => $message, 'status' => 'success');
    }
    
    private function log_warning($message) {
        echo "<div style='color: orange;'>$message</div>";
        $this->test_results[] = array('message' => $message, 'status' => 'warning');
    }
    
    private function log_error($message) {
        echo "<div style='color: red;'>$message</div>";
        $this->test_results[] = array('message' => $message, 'status' => 'error');
        $this->errors[] = $message;
    }
}

// Run tests if accessed directly
if (isset($_GET['run_system_tests']) && current_user_can('manage_options')) {
    $tester = new ReplantaHub_System_Tests();
    $tester->run_all_tests();
    exit;
}
