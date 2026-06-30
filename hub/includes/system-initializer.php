<?php
/**
 * System Initialization Script for Replanta Hub & Care
 * This script sets up the complete system for testing
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Complete System Initializer
 */
class Replanta_System_Initializer {
    
    public static function init_complete_system() {
        $results = array();
        
        // 1. Initialize Hub Database
        if (class_exists('RPHUB_Enhanced_Database_Setup')) {
            $results['hub_database'] = RPHUB_Enhanced_Database_Setup::create_all_tables();
            $results['hub_status'] = RPHUB_Enhanced_Database_Setup::get_setup_status();
        }
        
        // 2. Use unified Plans Manager
        if (class_exists('RPHUB_Plans_Manager')) {
            $plans_manager = new RPHUB_Plans_Manager();
            $results['plans_database'] = $plans_manager->create_tables();
        }
        
        // 3. Create sample data
        $results['sample_data'] = self::create_sample_data();
        
        // 4. Initialize components
        $results['components'] = self::initialize_components();
        
        return $results;
    }
    
    /**
     * Create sample data for testing
     */
    private static function create_sample_data() {
        global $wpdb;
        
        $results = array();
        
        // Add sample sites to Hub
        $sites_table = $wpdb->prefix . 'rphub_sites';
        
        $sample_sites = array(
            array(
                'name' => 'Ejemplo Site 1',
                'url' => 'https://ejemplo1.com',
                'token' => wp_generate_password(32, false),
                'status' => 'online',
                'health_score' => 95,
                'wp_version' => '6.4.2',
                'php_version' => '8.2.0',
                'plugins_count' => 15,
                'updates_available' => 3
            ),
            array(
                'name' => 'Ejemplo Site 2',
                'url' => 'https://ejemplo2.com',
                'token' => wp_generate_password(32, false),
                'status' => 'warning',
                'health_score' => 78,
                'wp_version' => '6.4.1',
                'php_version' => '8.1.0',
                'plugins_count' => 22,
                'updates_available' => 7
            ),
            array(
                'name' => 'Ejemplo Site 3',
                'url' => 'https://ejemplo3.com',
                'token' => wp_generate_password(32, false),
                'status' => 'online',
                'health_score' => 88,
                'wp_version' => '6.4.2',
                'php_version' => '8.2.0',
                'plugins_count' => 18,
                'updates_available' => 1
            )
        );
        
        foreach ($sample_sites as $site) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $sites_table WHERE url = %s",
                $site['url']
            ));
            
            if (!$existing) {
                $wpdb->insert($sites_table, $site);
                $results['sites_created'][] = $site['name'];
            }
        }
        
        // Assign plans to sites
        $plans_table = $wpdb->prefix . 'rphub_plans';
        $site_plans_table = $wpdb->prefix . 'rphub_site_plans';
        
        $plans = $wpdb->get_results("SELECT id, slug FROM $plans_table");
        $sites = $wpdb->get_results("SELECT id, name FROM $sites_table LIMIT 3");
        
        if ($plans && $sites) {
            $plan_assignments = array(
                0 => 'semilla',    // Site 1 -> Semilla
                1 => 'raiz',       // Site 2 -> Raíz  
                2 => 'ecosistema'  // Site 3 -> Ecosistema
            );
            
            foreach ($sites as $index => $site) {
                $plan_slug = $plan_assignments[$index] ?? 'semilla';
                $plan = array_filter($plans, function($p) use ($plan_slug) {
                    return $p->slug === $plan_slug;
                });
                $plan = reset($plan);
                
                if ($plan) {
                    $existing_assignment = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM $site_plans_table WHERE site_id = %d",
                        $site->id
                    ));
                    
                    if (!$existing_assignment) {
                        $wpdb->insert($site_plans_table, array(
                            'site_id' => $site->id,
                            'plan_id' => $plan->id,
                            'is_active' => 1
                        ));
                        $results['plan_assignments'][] = $site->name . ' -> ' . $plan_slug;
                    }
                }
            }
        }
        
        // Create sample activities
        $activities_table = $wpdb->prefix . 'rphub_activities';
        
        $sample_activities = array(
            array(
                'site_id' => null,
                'action' => 'system_init',
                'details' => 'Sistema Replanta inicializado correctamente'
            ),
            array(
                'site_id' => 1,
                'action' => 'backup_created',
                'details' => 'Backup automático completado (245 MB)'
            ),
            array(
                'site_id' => 2,
                'action' => 'plugin_updated',
                'details' => 'WooCommerce actualizado a versión 8.5.0'
            ),
            array(
                'site_id' => 3,
                'action' => 'health_check',
                'details' => 'Verificación de salud completada - Score: 88/100'
            )
        );
        
        foreach ($sample_activities as $activity) {
            $wpdb->insert($activities_table, $activity);
        }
        
        $results['activities_created'] = count($sample_activities);
        
        return $results;
    }
    
    /**
     * Initialize system components
     */
    private static function initialize_components() {
        $results = array();
        
        // Initialize Hub components
        if (class_exists('RPHUB_Plans_Manager')) {
            new RPHUB_Plans_Manager();
            $results['hub_plans_manager'] = 'initialized';
        }
        
        if (class_exists('RP_Hub_Enhanced_Dashboard')) {
            new RP_Hub_Enhanced_Dashboard();
            $results['hub_enhanced_dashboard'] = 'initialized';
        }
        
        // Initialize Care components
        if (class_exists('RP_Care_Dashboard')) {
            new RP_Care_Dashboard();
            $results['care_dashboard'] = 'initialized';
        }
        
        if (class_exists('RP_Care_Update_Control')) {
            new RP_Care_Update_Control();
            $results['care_update_control'] = 'initialized';
        }
        
        return $results;
    }
    
    /**
     * Get system status
     */
    public static function get_system_status() {
        $status = array();
        
        // Hub status
        if (class_exists('RPHUB_Enhanced_Database_Setup')) {
            $status['hub'] = RPHUB_Enhanced_Database_Setup::get_setup_status();
        }
        
        // Care status
        global $wpdb;
        $care_tables = array('rphub_plans', 'rphub_plan_features');
        $care_status = array('existing' => array(), 'missing' => array());
        
        foreach ($care_tables as $table) {
            $table_name = $wpdb->prefix . $table;
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'")) {
                $care_status['existing'][] = $table;
            } else {
                $care_status['missing'][] = $table;
            }
        }
        
        $status['care'] = array(
            'is_complete' => empty($care_status['missing']),
            'existing_tables' => count($care_status['existing']),
            'missing_tables' => count($care_status['missing']),
            'missing_list' => $care_status['missing']
        );
        
        // Site count
        $sites_table = $wpdb->prefix . 'rphub_sites';
        $status['sites_count'] = $wpdb->get_var("SELECT COUNT(*) FROM $sites_table");
        
        // Plans count
        $plans_table = $wpdb->prefix . 'rphub_plans';
        $status['plans_count'] = $wpdb->get_var("SELECT COUNT(*) FROM $plans_table");
        
        return $status;
    }
}

// Manual initialization via URL
if (isset($_GET['replanta_init_system']) && current_user_can('manage_options')) {
    $results = Replanta_System_Initializer::init_complete_system();
    $status = Replanta_System_Initializer::get_system_status();
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title> Replanta System Initialization</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 1000px; margin: 20px auto; padding: 20px; }
            .success { background: #d4edda; color: #155724; padding: 15px; border: 1px solid #c3e6cb; border-radius: 8px; margin: 10px 0; }
            .warning { background: #fff3cd; color: #856404; padding: 15px; border: 1px solid #ffeaa7; border-radius: 8px; margin: 10px 0; }
            .error { background: #f8d7da; color: #721c24; padding: 15px; border: 1px solid #f5c6cb; border-radius: 8px; margin: 10px 0; }
            .code { background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0; font-family: monospace; }
            .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0; }
            .card { background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #e9ecef; }
            h1 { color: #28a745; }
            h2 { color: #495057; border-bottom: 2px solid #e9ecef; padding-bottom: 10px; }
        </style>
    </head>
    <body>
        <h1> Replanta System Initialization Complete</h1>
        
        <div class="grid">
            <div class="card">
                <h3> Hub Status</h3>
                <?php if ($status['hub']['is_complete']): ?>
                    <div class="success">
                        <strong> Hub Database Complete</strong><br>
                        Tables: <?php echo $status['hub']['existing_tables']; ?>/<?php echo $status['hub']['total_tables']; ?>
                    </div>
                <?php else: ?>
                    <div class="error">
                        <strong> Hub Setup Incomplete</strong><br>
                        Missing: <?php echo implode(', ', $status['hub']['missing_list']); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <h3> Care Status</h3>
                <?php if ($status['care']['is_complete']): ?>
                    <div class="success">
                        <strong> Care Database Complete</strong><br>
                        Tables: <?php echo $status['care']['existing_tables']; ?>
                    </div>
                <?php else: ?>
                    <div class="error">
                        <strong> Care Setup Incomplete</strong><br>
                        Missing: <?php echo implode(', ', $status['care']['missing_list']); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <h2> System Overview</h2>
        <div class="grid">
            <div class="card">
                <h4> Statistics</h4>
                <p><strong>Sites:</strong> <?php echo $status['sites_count']; ?></p>
                <p><strong>Plans:</strong> <?php echo $status['plans_count']; ?></p>
            </div>
            
            <div class="card">
                <h4> Quick Actions</h4>
                <p><a href="<?php echo admin_url('admin.php?page=replanta-hub'); ?>">Hub Dashboard</a></p>
                <p><a href="<?php echo admin_url('index.php'); ?>">Care Dashboard</a></p>
                <p><a href="<?php echo admin_url('admin.php?rphub_setup_enhanced=1'); ?>">Database Setup</a></p>
            </div>
        </div>
        
        <h2> Initialization Results</h2>
        <div class="code">
            <pre><?php print_r($results); ?></pre>
        </div>
        
        <h2> Next Steps</h2>
        <ol>
            <li><strong>Visit Hub Dashboard:</strong> <a href="<?php echo admin_url('admin.php?page=replanta-hub'); ?>">Go to Hub</a></li>
            <li><strong>View Client Dashboard:</strong> <a href="<?php echo admin_url('index.php'); ?>">Go to WordPress Dashboard</a></li>
            <li><strong>Test Plan Features:</strong> Verify update control and dashboard widgets</li>
            <li><strong>Configure API:</strong> Set up communication between Hub and Care</li>
        </ol>
        
        <div class="success">
            <strong> System Ready!</strong> Both Replanta Hub and Care plugins are now initialized and ready for use.
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
