<?php
/**
 * Plugin Name: Replanta Meta Fill
 * Plugin URI: https://replanta.net
 * Description: Generación automática de meta descripciones y textos ALT de imágenes usando OpenAI. Compatible con RankMath, Yoast SEO y WooCommerce. Incluye soporte para BetterDocs.
 * Version: 1.2.0
 * Author: Replanta
 * Author URI: https://replanta.net
 * License: GPL v2 or later
 * Text Domain: replanta-meta-fill
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// Constantes del plugin
define('REPLANTA_META_FILL_VERSION', '1.2.0');
define('REPLANTA_META_FILL_FILE', __FILE__);
define('REPLANTA_META_FILL_DIR', plugin_dir_path(__FILE__));
define('REPLANTA_META_FILL_URL', plugin_dir_url(__FILE__));
define('REPLANTA_META_FILL_BASENAME', plugin_basename(__FILE__));

// Cargar archivos principales
require_once REPLANTA_META_FILL_DIR . 'includes/class-content-crawler.php';
require_once REPLANTA_META_FILL_DIR . 'includes/class-openai-handler.php';
require_once REPLANTA_META_FILL_DIR . 'includes/class-admin-columns.php';
require_once REPLANTA_META_FILL_DIR . 'includes/class-ajax-handler.php';
require_once REPLANTA_META_FILL_DIR . 'includes/class-admin-settings.php';
require_once REPLANTA_META_FILL_DIR . 'includes/class-betterdocs-seo.php';
require_once REPLANTA_META_FILL_DIR . 'includes/class-image-alt-filler.php';

/**
 * Clase principal del plugin
 */
class Replanta_Meta_Fill {
    
    private static $instance = null;
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Inicializar componentes
        add_action('plugins_loaded', [$this, 'init']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Activación/Desactivación
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }
    
    public function init() {
        // Inicializar módulos
        Replanta_Meta_Fill_Content_Crawler::instance();
        Replanta_Meta_Fill_OpenAI_Handler::instance();
        Replanta_Meta_Fill_Admin_Columns::instance();
        Replanta_Meta_Fill_Ajax_Handler::instance();
        Replanta_Meta_Fill_Admin_Settings::instance();
        Replanta_Meta_Fill_BetterDocs_SEO::instance();
        Replanta_Meta_Fill_Image_Alt_Filler::instance();
        
        // Cargar traducciones
        load_plugin_textdomain('replanta-meta-fill', false, dirname(REPLANTA_META_FILL_BASENAME) . '/languages');
    }
    
    public function enqueue_admin_assets($hook) {
        // Solo en páginas relevantes
        if (!in_array($hook, ['edit.php', 'post.php', 'post-new.php', 'toplevel_page_replanta-meta-fill', 'meta-fill_page_replanta-meta-fill-bulk', 'meta-fill_page_replanta-meta-fill-alts', 'upload.php'])) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'replanta-meta-fill-admin',
            REPLANTA_META_FILL_URL . 'assets/css/admin.css',
            [],
            REPLANTA_META_FILL_VERSION
        );
        
        // JS
        wp_enqueue_script(
            'replanta-meta-fill-admin',
            REPLANTA_META_FILL_URL . 'assets/js/admin.js',
            ['jquery'],
            REPLANTA_META_FILL_VERSION,
            true
        );
        
        // Localizar script
        wp_localize_script('replanta-meta-fill-admin', 'replantaMetaFill', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('replanta_meta_fill_nonce'),
            'strings' => [
                'generating' => __('Generando...', 'replanta-meta-fill'),
                'success' => __('Meta descripción generada correctamente', 'replanta-meta-fill'),
                'error' => __('Error al generar meta descripción', 'replanta-meta-fill'),
                'confirm_bulk' => __('¿Generar meta descripciones para todos los posts seleccionados?', 'replanta-meta-fill'),
            ]
        ]);
    }
    
    public function activate() {
        // Opciones por defecto
        $default_options = [
            'openai_api_key' => '',
            'openai_model' => 'gpt-4o-mini',
            'max_length' => 155,
            'temperature' => 0.7,
            'prompt_template' => 'Genera una meta descripción SEO atractiva y concisa (máximo {max_length} caracteres) para el siguiente contenido. Debe ser persuasiva, incluir palabras clave relevantes y motivar al clic.

Título: {title}
Contenido: {content}

Meta descripción:',
            'auto_generate' => 0,
            'seo_plugin' => 'auto', // auto, rankmath, yoast, none
        ];
        
        $existing_options = get_option('replanta_meta_fill_options', []);
        $merged_options = array_merge($default_options, $existing_options);
        update_option('replanta_meta_fill_options', $merged_options);
        
        // Registrar capacidades
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('manage_replanta_meta_fill');
        }
        
        // Log activación
        $this->log('Plugin activado - versión ' . REPLANTA_META_FILL_VERSION, 'info');
    }
    
    public function deactivate() {
        // Limpiar trabajos programados si los hubiera
        wp_clear_scheduled_hook('replanta_meta_fill_cron');
        
        // Log desactivación
        $this->log('Plugin desactivado', 'info');
    }
    
    /**
     * Sistema de logging
     */
    public static function log($message, $level = 'info') {
        $timestamp = current_time('Y-m-d H:i:s');
        error_log("[{$timestamp}] [Replanta Meta Fill] [{$level}] {$message}");
        
        // Guardar en base de datos
        $logs = get_option('replanta_meta_fill_logs', []);
        array_unshift($logs, [
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $message
        ]);
        
        // Mantener solo últimos 100 logs
        $logs = array_slice($logs, 0, 100);
        update_option('replanta_meta_fill_logs', $logs);
    }
}

// Inicializar el plugin
Replanta_Meta_Fill::instance();
