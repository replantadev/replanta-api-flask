<?php
/**
 * Plugin Name: Replanta Auto Translate
 * Plugin URI: https://replanta.net
 * Description: Traduccion automatica de sitios WordPress con Polylang y Elementor. Usa Google Translate y OpenAI.
 * Version: 1.0.0
 * Author: Replanta
 * Author URI: https://replanta.net
 * License: GPL v2 or later
 * Text Domain: replanta-auto-translate
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// Constantes del plugin
define('REPLANTA_AUTO_TRANSLATE_VERSION', '1.0.0');
define('REPLANTA_AUTO_TRANSLATE_FILE', __FILE__);
define('REPLANTA_AUTO_TRANSLATE_DIR', plugin_dir_path(__FILE__));
define('REPLANTA_AUTO_TRANSLATE_URL', plugin_dir_url(__FILE__));
define('REPLANTA_AUTO_TRANSLATE_BASENAME', plugin_basename(__FILE__));

// Cargar archivos principales
require_once REPLANTA_AUTO_TRANSLATE_DIR . 'includes/class-admin-settings.php';
require_once REPLANTA_AUTO_TRANSLATE_DIR . 'includes/class-polylang-bridge.php';
require_once REPLANTA_AUTO_TRANSLATE_DIR . 'includes/class-elementor-parser.php';
require_once REPLANTA_AUTO_TRANSLATE_DIR . 'includes/class-translator.php';
require_once REPLANTA_AUTO_TRANSLATE_DIR . 'includes/class-content-extractor.php';
require_once REPLANTA_AUTO_TRANSLATE_DIR . 'includes/class-menu-translator.php';
require_once REPLANTA_AUTO_TRANSLATE_DIR . 'includes/class-bulk-processor.php';
require_once REPLANTA_AUTO_TRANSLATE_DIR . 'includes/class-ajax-handler.php';

/**
 * Clase principal del plugin
 */
class Replanta_Auto_Translate {
    
    private static $instance = null;
    
    /**
     * Obtener instancia singleton
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_notices', [$this, 'check_dependencies']);
        
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }
    
    /**
     * Inicializar plugin
     */
    public function init() {
        // Verificar dependencias antes de inicializar
        if (!$this->dependencies_met()) {
            return;
        }
        
        // Inicializar modulos
        Replanta_Auto_Translate_Admin_Settings::instance();
        Replanta_Auto_Translate_Polylang_Bridge::instance();
        Replanta_Auto_Translate_Elementor_Parser::instance();
        Replanta_Auto_Translate_Translator::instance();
        Replanta_Auto_Translate_Content_Extractor::instance();
        Replanta_Auto_Translate_Menu_Translator::instance();
        Replanta_Auto_Translate_Bulk_Processor::instance();
        Replanta_Auto_Translate_Ajax_Handler::instance();
        
        // Cargar traducciones
        load_plugin_textdomain('replanta-auto-translate', false, dirname(REPLANTA_AUTO_TRANSLATE_BASENAME) . '/languages');
    }
    
    /**
     * Verificar si las dependencias estan instaladas
     */
    public function dependencies_met() {
        // Verificar Polylang
        if (!function_exists('pll_languages_list')) {
            return false;
        }
        return true;
    }
    
    /**
     * Mostrar avisos de dependencias faltantes
     */
    public function check_dependencies() {
        if (!function_exists('pll_languages_list')) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>Replanta Auto Translate:</strong> ';
            echo 'Este plugin requiere Polylang instalado y activado.';
            echo '</p></div>';
        }
        
        $settings = get_option('replanta_auto_translate_settings', []);
        $has_api = !empty($settings['openai_api_key']) || !empty($settings['google_api_key']);
        
        if ($this->dependencies_met() && !$has_api) {
            echo '<div class="notice notice-warning"><p>';
            echo '<strong>Replanta Auto Translate:</strong> ';
            echo 'Configura al menos una API key (OpenAI o Google Translate) en ';
            echo '<a href="' . admin_url('admin.php?page=replanta-auto-translate') . '">Ajustes</a>.';
            echo '</p></div>';
        }
    }
    
    /**
     * Cargar assets de administracion
     */
    public function enqueue_admin_assets($hook) {
        // Solo en paginas del plugin
        $allowed_hooks = [
            'toplevel_page_replanta-auto-translate',
            'auto-translate_page_replanta-auto-translate-bulk'
        ];
        
        if (!in_array($hook, $allowed_hooks)) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'replanta-auto-translate-admin',
            REPLANTA_AUTO_TRANSLATE_URL . 'assets/css/admin.css',
            [],
            REPLANTA_AUTO_TRANSLATE_VERSION
        );
        
        // JS
        wp_enqueue_script(
            'replanta-auto-translate-admin',
            REPLANTA_AUTO_TRANSLATE_URL . 'assets/js/admin.js',
            ['jquery'],
            REPLANTA_AUTO_TRANSLATE_VERSION,
            true
        );
        
        // Localizar script
        wp_localize_script('replanta-auto-translate-admin', 'replantaAutoTranslate', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('replanta_auto_translate_nonce'),
            'strings' => [
                'translating' => __('Traduciendo...', 'replanta-auto-translate'),
                'translated' => __('Traducido', 'replanta-auto-translate'),
                'error' => __('Error', 'replanta-auto-translate'),
                'confirm_bulk' => __('Esto traducira todas las paginas seleccionadas. Continuar?', 'replanta-auto-translate'),
                'processing' => __('Procesando', 'replanta-auto-translate'),
                'completed' => __('Completado', 'replanta-auto-translate'),
                'of' => __('de', 'replanta-auto-translate'),
                'pages' => __('paginas', 'replanta-auto-translate'),
            ]
        ]);
    }
    
    /**
     * Activacion del plugin
     */
    public function activate() {
        // Crear opciones por defecto
        $default_settings = [
            'openai_api_key' => '',
            'google_api_key' => '',
            'default_engine' => 'openai',
            'source_language' => 'es',
            'target_language' => 'en',
            'openai_model' => 'gpt-4o-mini',
            'translate_slugs' => true,
            'translate_seo' => true,
            'batch_size' => 1, // 1 por defecto para evitar timeouts
            'delay_between_requests' => 500,
        ];
        
        if (!get_option('replanta_auto_translate_settings')) {
            add_option('replanta_auto_translate_settings', $default_settings);
        }
        
        // Crear tabla de log
        $this->create_log_table();
        
        // Limpiar cache
        flush_rewrite_rules();
    }
    
    /**
     * Desactivacion del plugin
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Crear tabla de log para traducciones
     */
    private function create_log_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'replanta_translate_log';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            translated_post_id bigint(20) DEFAULT NULL,
            source_lang varchar(10) NOT NULL,
            target_lang varchar(10) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            engine varchar(20) NOT NULL,
            error_message text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Obtener configuracion
     */
    public static function get_settings() {
        return get_option('replanta_auto_translate_settings', []);
    }
    
    /**
     * Obtener una configuracion especifica
     */
    public static function get_setting($key, $default = '') {
        $settings = self::get_settings();
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
    
    /**
     * Registrar mensaje en el log
     *
     * @param string $message Mensaje a registrar
     * @param string $type Tipo de mensaje: info, success, warning, error
     */
    public static function log($message, $type = 'info') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $prefix = '[Replanta Auto Translate]';
            $type_label = strtoupper($type);
            error_log("$prefix [$type_label] $message");
        }
    }
}

// Inicializar plugin
function replanta_auto_translate() {
    return Replanta_Auto_Translate::instance();
}

replanta_auto_translate();
