<?php
namespace TEW;

use TEW\Admin\Admin_Page;
use TEW\Admin\Report_Editor;
use TEW\Reporting\Custom_Report_Table;
use TEW\Reporting\Report_Storage;
use TEW\REST\Controller as Rest_Controller;
use TEW\Settings;
use TEW\Shortcode;
use function add_action;
use function add_filter;
use function file_exists;
use function is_singular;
use function load_plugin_textdomain;
use function plugin_basename;

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

class Plugin {

    private static $instance;
    private $settings;
    private $admin_page;
    private $report_editor;
    private $shortcode;
    private $showcase;
    private $rest_controller;
    private $report_storage;public static function instance() {
if ( null === self::$instance ) {
self::$instance = new self();
}
return self::$instance;
}

public function boot() {
$this->settings = new Settings();
$this->register_hooks();
}

private function register_hooks() {
add_action( 'init', [ $this, 'load_textdomain' ] );
add_action( 'admin_init', [ $this, 'register_settings' ] );
add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
add_action( 'init', [ $this, 'register_post_types' ] );
add_action( 'init', [ $this, 'register_shortcode' ] );
add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
add_filter( 'template_include', [ $this, 'maybe_override_single_template' ] );
add_action( 'wp_ajax_tew_delete_success_case', [ $this, 'ajax_delete_success_case' ] );
}

public function register_post_types() {
$this->report_storage()->register();

$custom_table = new Custom_Report_Table();
$custom_table->maybe_upgrade();
}

public function load_textdomain() {
load_plugin_textdomain( 'test-eco-website', false, dirname( plugin_basename( TEW_PLUGIN_FILE ) ) . '/languages' );
}

public function register_settings() {
$this->settings->register();
}

public function register_admin_page() {
$this->admin_page = new Admin_Page( $this->settings );
$this->admin_page->register_menu();
if ( null === $this->report_editor ) {
$this->report_editor = new Report_Editor();
}
}

    public function register_shortcode() {
        if ( null === $this->shortcode ) {
            $this->shortcode = new Shortcode( $this->settings );
        }
        if ( null === $this->showcase ) {
            $this->showcase = new Showcase( $this->report_storage() );
        }
    }public function register_rest_routes() {
if ( null === $this->rest_controller ) {
$this->rest_controller = new Rest_Controller( $this->settings, $this->report_storage() );
}
$this->rest_controller->register_routes();
}

private function report_storage() {
if ( null === $this->report_storage ) {
$this->report_storage = new Report_Storage();
}
return $this->report_storage;
}

public function maybe_override_single_template( $template ) {
if ( is_singular( Report_Storage::POST_TYPE ) ) {
$custom = TEW_PLUGIN_DIR . 'templates/single-' . Report_Storage::POST_TYPE . '.php';
if ( file_exists( $custom ) ) {
return $custom;
}
}
return $template;
}

public function ajax_delete_success_case() {
file_put_contents( WP_CONTENT_DIR . '/tew-debug.log', '[' . date('Y-m-d H:i:s') . '] TEW AJAX - Inicio en Plugin' . PHP_EOL, FILE_APPEND );

if ( ! isset( $_POST['nonce'] ) || ! isset( $_POST['case_id'] ) ) {
file_put_contents( WP_CONTENT_DIR . '/tew-debug.log', '[' . date('Y-m-d H:i:s') . '] TEW AJAX - ERROR: Faltan datos' . PHP_EOL, FILE_APPEND );
\wp_send_json_error( [ 'message' => __( 'Datos inválidos', 'test-eco-website' ) ] );
}

$case_id = absint( $_POST['case_id'] );
file_put_contents( WP_CONTENT_DIR . '/tew-debug.log', '[' . date('Y-m-d H:i:s') . '] TEW AJAX - Case ID: ' . $case_id . PHP_EOL, FILE_APPEND );

$nonce_check = \wp_verify_nonce( $_POST['nonce'], 'tew_delete_success_case_' . $case_id );
file_put_contents( WP_CONTENT_DIR . '/tew-debug.log', '[' . date('Y-m-d H:i:s') . '] TEW AJAX - Nonce: ' . ( $nonce_check ? 'OK' : 'FALLO' ) . PHP_EOL, FILE_APPEND );

if ( ! $nonce_check ) {
file_put_contents( WP_CONTENT_DIR . '/tew-debug.log', '[' . date('Y-m-d H:i:s') . '] TEW AJAX - ERROR: Nonce inválido' . PHP_EOL, FILE_APPEND );
\wp_send_json_error( [ 'message' => __( 'Verificación de seguridad falló', 'test-eco-website' ) ] );
}

if ( ! \current_user_can( 'manage_options' ) ) {
file_put_contents( WP_CONTENT_DIR . '/tew-debug.log', '[' . date('Y-m-d H:i:s') . '] TEW AJAX - ERROR: Sin permisos' . PHP_EOL, FILE_APPEND );
\wp_send_json_error( [ 'message' => __( 'No tienes permisos', 'test-eco-website' ) ] );
}

$storage = $this->report_storage();
$result = $storage->delete_success_case( $case_id );
file_put_contents( WP_CONTENT_DIR . '/tew-debug.log', '[' . date('Y-m-d H:i:s') . '] TEW AJAX - Resultado: ' . ( $result ? 'TRUE' : 'FALSE' ) . PHP_EOL, FILE_APPEND );

if ( $result ) {
\wp_send_json_success( [ 
'message' => __( 'Caso eliminado', 'test-eco-website' ),
'case_id' => $case_id,
] );
} else {
\wp_send_json_error( [ 'message' => __( 'No se pudo eliminar', 'test-eco-website' ) ] );
}
}
}