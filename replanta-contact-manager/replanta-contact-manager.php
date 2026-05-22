<?php
/**
 * Plugin Name: Replanta Contact Manager
 * Plugin URI: https://replanta.net
 * Description: Sistema centralizado para gestionar todas las solicitudes de contacto: Plan Solidario, Auditorías, Formularios Elementor y cualquier otro form
 * Version: 1.0.0
 * Author: Replanta
 * Author URI: https://replanta.net
 * License: GPL v2 or later
 * Text Domain: replanta-contact-manager
 * Requires at least: 5.8
 * Tested up to: 7.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// Constantes
define('RCM_VERSION', '1.0.0');
define('RCM_FILE', __FILE__);
define('RCM_DIR', plugin_dir_path(__FILE__));
define('RCM_URL', plugin_dir_url(__FILE__));
define('RCM_BASENAME', plugin_basename(__FILE__));

// Autoload de clases
spl_autoload_register(function ($class) {
    if (strpos($class, 'RCM_') === 0) {
        $file = RCM_DIR . 'includes/class-' . str_replace('_', '-', strtolower(str_replace('RCM_', '', $class))) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

// Cargar clases principales
require_once RCM_DIR . 'includes/class-cpt.php';
require_once RCM_DIR . 'includes/class-security.php';
require_once RCM_DIR . 'includes/class-rest-api.php';
require_once RCM_DIR . 'includes/class-elementor-integration.php';
require_once RCM_DIR . 'includes/class-admin-ui.php';
require_once RCM_DIR . 'includes/class-admin-settings.php';

/**
 * Clase principal del plugin
 */
class Replanta_Contact_Manager {
    
    private static $instance = null;
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }
    
    public function init() {
        // Inicializar módulos
        RCM_CPT::instance();
        RCM_Security::instance();
        RCM_REST_API::instance();
        RCM_Elementor_Integration::instance();
        RCM_Admin_UI::instance();
        RCM_Admin_Settings::instance();
        
        // Traducciones
        load_plugin_textdomain('replanta-contact-manager', false, dirname(RCM_BASENAME) . '/languages');
    }
    
    public function activate() {
        // Registrar CPT y taxonomía
        RCM_CPT::instance()->register();
        
        // Opciones por defecto
        $defaults = [
            'email_to'                => get_option('admin_email'),
            'turnstile_enabled'       => 1,
            'geo_enabled'             => 1,
            'geo_allowed_countries'   => ['ES', 'AD', 'MX', 'AR', 'CO', 'CL', 'PE', 'EC', 'VE', 'BO', 'PY', 'UY', 'CR', 'PA', 'GT', 'HN', 'SV', 'NI', 'DO', 'CU', 'PR'],
            'rate_limit_max'          => 5,
            'rate_limit_window'       => 15,
            'honeypot_field'          => 'fax_number',
            'elementor_capture'       => 1,
            'elementor_exclude_forms' => [],
        ];
        
        foreach ($defaults as $key => $value) {
            $option_name = 'rcm_' . $key;
            if (get_option($option_name) === false) {
                add_option($option_name, $value);
            }
        }
        
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Helper para obtener opciones
     */
    public static function get_option($key, $default = '') {
        return get_option('rcm_' . $key, $default);
    }
    
    /**
     * Helper para actualizar opciones
     */
    public static function update_option($key, $value) {
        return update_option('rcm_' . $key, $value);
    }
}

// Inicializar
Replanta_Contact_Manager::instance();
