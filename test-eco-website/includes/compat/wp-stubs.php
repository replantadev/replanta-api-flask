<?php
/**
 * Lightweight compatibility layer providing WordPress functions when the plugin
 * is executed in isolation (CLI tools, static analysis, unit tests).
 */

if ( ! isset( $GLOBALS['tew_stub_posts'] ) ) {
    $GLOBALS['tew_stub_posts'] = [];
}

if ( ! isset( $GLOBALS['tew_stub_post_meta'] ) ) {
    $GLOBALS['tew_stub_post_meta'] = [];
}

if ( ! isset( $GLOBALS['tew_stub_rest_routes'] ) ) {
    $GLOBALS['tew_stub_rest_routes'] = [];
}

if ( ! function_exists( 'add_action' ) ) {
    function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
        return true;
    }
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
    define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! function_exists( 'add_filter' ) ) {
    function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
        return true;
    }
}

if ( ! function_exists( 'add_shortcode' ) ) {
    function add_shortcode( $tag, $callback ) {
        return true;
    }
}

if ( ! function_exists( 'add_menu_page' ) ) {
    function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $callback = '', $icon_url = '', $position = null ) {
        return true;
    }
}

if ( ! function_exists( 'apply_filters' ) ) {
    function apply_filters( $hook_name, $value ) {
        return $value;
    }
}

if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = 'default' ) {
        return $text;
    }
}

if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( $text, $domain = 'default' ) {
        return esc_html( $text );
    }
}

if ( ! function_exists( '_x' ) ) {
    function _x( $text, $context, $domain = 'default' ) {
        return $text;
    }
}

if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( $text ) {
        return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_html_e' ) ) {
    function esc_html_e( $text, $domain = 'default' ) {
        echo esc_html( $text );
    }
}

if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( $text ) {
        return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_attr_e' ) ) {
    function esc_attr_e( $text, $domain = 'default' ) {
        echo esc_attr( $text );
    }
}

if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( $url ) {
        $sanitized = filter_var( $url, FILTER_SANITIZE_URL );
        return tew_stub_validate_url( $sanitized );
    }
}

if ( ! function_exists( 'esc_url_raw' ) ) {
    function esc_url_raw( $url ) {
        $sanitized = filter_var( $url, FILTER_SANITIZE_URL );
        return tew_stub_validate_url( $sanitized );
    }
}

if ( ! function_exists( 'tew_stub_validate_url' ) ) {
    function tew_stub_validate_url( $url ) {
        if ( false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return '';
        }

        $parts = parse_url( $url );
        if ( empty( $parts['host'] ) ) {
            return '';
        }

        $host = $parts['host'];
        if ( 'localhost' === $host ) {
            return $url;
        }

        return false === strpos( $host, '.' ) ? '' : $url;
    }
}

if ( ! function_exists( 'wp_parse_url' ) ) {
    function wp_parse_url( $url ) {
        return parse_url( $url );
    }
}

if ( ! function_exists( 'wp_parse_args' ) ) {
    function wp_parse_args( $args, $defaults = [] ) {
        if ( is_object( $args ) ) {
            $args = get_object_vars( $args );
        }

        if ( is_array( $args ) ) {
            return array_merge( $defaults, $args );
        }

        return $defaults;
    }
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
    function wp_create_nonce( $action = -1 ) {
        return substr( hash( 'sha1', $action . microtime( true ) ), 0, 12 );
    }
}

if ( ! function_exists( 'wp_register_style' ) ) {
    function wp_register_style( $handle, $src, $deps = [], $ver = false, $media = 'all' ) {
        $GLOBALS['wp_styles'][ $handle ] = compact( 'src', 'deps', 'ver', 'media' );
        return true;
    }
}

if ( ! function_exists( 'wp_register_script' ) ) {
    function wp_register_script( $handle, $src, $deps = [], $ver = false, $in_footer = false ) {
        $GLOBALS['wp_scripts'][ $handle ] = compact( 'src', 'deps', 'ver', 'in_footer' );
        return true;
    }
}

if ( ! function_exists( 'wp_localize_script' ) ) {
    function wp_localize_script( $handle, $object_name, $l10n ) {
        $GLOBALS['wp_localize'][ $handle ][ $object_name ] = $l10n;
        return true;
    }
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
    function wp_enqueue_style( $handle ) {
        return true;
    }
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
    function wp_enqueue_script( $handle ) {
        return true;
    }
}

if ( ! function_exists( 'get_transient' ) ) {
    function get_transient( $transient ) {
        return $GLOBALS['tew_stub_transients'][ $transient ] ?? false;
    }
}

if ( ! function_exists( 'set_transient' ) ) {
    function set_transient( $transient, $value, $expiration = 0 ) {
        $GLOBALS['tew_stub_transients'][ $transient ] = $value;
        return true;
    }
}

if ( ! function_exists( 'delete_transient' ) ) {
    function delete_transient( $transient ) {
        unset( $GLOBALS['tew_stub_transients'][ $transient ] );
        return true;
    }
}

if ( ! function_exists( 'settings_fields' ) ) {
    function settings_fields( $option_group ) {
        return true;
    }
}

if ( ! function_exists( 'do_settings_sections' ) ) {
    function do_settings_sections( $page ) {
        return true;
    }
}

if ( ! function_exists( 'submit_button' ) ) {
    function submit_button( $text = null, $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = null ) {
        return true;
    }
}

if ( ! function_exists( 'disabled' ) ) {
    function disabled( $disabled, $current = true, $display = true ) {
        return $display ? 'disabled' : true;
    }
}

if ( ! function_exists( 'checked' ) ) {
    function checked( $checked, $current = true, $display = true ) {
        $result = $checked == $current ? 'checked' : '';
        return $display ? $result : (bool) $result;
    }
}

if ( ! function_exists( 'register_post_type' ) ) {
    function register_post_type( $post_type, $args = [] ) {
        $GLOBALS['tew_stub_post_types'][ $post_type ] = $args;
        return true;
    }
}

if ( ! function_exists( 'register_post_meta' ) ) {
    function register_post_meta( $post_type, $meta_key, $args = [] ) {
        $GLOBALS['tew_stub_post_meta_schema'][ $post_type ][ $meta_key ] = $args;
        return true;
    }
}

if ( ! function_exists( 'register_setting' ) ) {
    function register_setting( $option_group, $option_name, $args = [] ) {
        return true;
    }
}

if ( ! function_exists( 'add_settings_section' ) ) {
    function add_settings_section( $id, $title, $callback, $page ) {
        return true;
    }
}

if ( ! function_exists( 'add_settings_field' ) ) {
    function add_settings_field( $id, $title, $callback, $page, $section = 'default', $args = [] ) {
        return true;
    }
}

if ( ! function_exists( 'register_rest_route' ) ) {
    function register_rest_route( $namespace, $route, $args = [] ) {
        $key = trim( $namespace, '/' ) . '/' . ltrim( $route, '/' );
        $GLOBALS['tew_stub_rest_routes'][ $key ] = $args;
        return true;
    }
}

if ( ! function_exists( 'rest_url' ) ) {
    function rest_url( $path = '', $scheme = 'json' ) {
        return 'https://example.test/wp-json/' . ltrim( $path, '/' );
    }
}

if ( ! function_exists( 'rest_sanitize_boolean' ) ) {
    function rest_sanitize_boolean( $value ) {
        if ( is_bool( $value ) ) {
            return $value;
        }

        return filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
    }
}

if ( ! function_exists( 'current_time' ) ) {
    function current_time( $type, $gmt = 0 ) {
        $timestamp = $gmt ? time() : time();

        if ( 'mysql' === $type ) {
            return gmdate( 'Y-m-d H:i:s', $timestamp );
        }

        return $timestamp;
    }
}

if ( ! function_exists( 'wp_date' ) ) {
    function wp_date( $format, $timestamp = null, $timezone = null ) {
        $timestamp = null === $timestamp ? time() : (int) $timestamp;
        return gmdate( $format, $timestamp );
    }
}

if ( ! function_exists( 'get_option' ) ) {
    function get_option( $option, $default = false ) {
        if ( 'date_format' === $option ) {
            return 'F j, Y';
        }

        if ( 'time_format' === $option ) {
            return 'g:i a';
        }

        return $default;
    }
}

if ( ! function_exists( 'get_bloginfo' ) ) {
    function get_bloginfo( $show = '', $filter = 'raw' ) {
        if ( 'version' === $show ) {
            return '6.4.0';
        }

        return 'Eco Snapshot';
    }
}

if ( ! function_exists( 'get_locale' ) ) {
    function get_locale() {
        return 'es_ES';
    }
}

if ( ! function_exists( 'sanitize_title' ) ) {
    function sanitize_title( $title ) {
        $title = strtolower( (string) $title );
        $title = preg_replace( '/[^a-z0-9]+/i', '-', $title );
        return trim( $title, '-' );
    }
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $text ) {
        return trim( filter_var( $text, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES ) );
    }
}

if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $key ) {
        $key = strtolower( (string) $key );
        return preg_replace( '/[^a-z0-9_]/', '', $key );
    }
}

if ( ! function_exists( 'absint' ) ) {
    function absint( $maybeint ) {
        return abs( intval( $maybeint ) );
    }
}

if ( ! function_exists( '__return_true' ) ) {
    function __return_true() {
        return true;
    }
}

if ( ! function_exists( '__return_false' ) ) {
    function __return_false() {
        return false;
    }
}

if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $data, $options = 0, $depth = 512 ) {
        return json_encode( $data, $options, $depth );
    }
}

if ( ! function_exists( 'wp_slash' ) ) {
    function wp_slash( $value ) {
        if ( is_array( $value ) ) {
            return array_map( 'wp_slash', $value );
        }

        return addslashes( (string) $value );
    }
}

if ( ! function_exists( 'wp_unslash' ) ) {
    function wp_unslash( $value ) {
        if ( is_array( $value ) ) {
            return array_map( 'wp_unslash', $value );
        }

        return stripslashes( (string) $value );
    }
}

if ( ! function_exists( 'wp_insert_post' ) ) {
    function wp_insert_post( $postarr, $wp_error = false ) {
        static $id = 1;

        if ( empty( $postarr['ID'] ) ) {
            $postarr['ID'] = $id++;
        }

        $postarr['post_type']   = $postarr['post_type'] ?? 'post';
        $postarr['post_status'] = $postarr['post_status'] ?? 'publish';
        $postarr['post_title']  = $postarr['post_title'] ?? 'Untitled';
        $postarr['post_date_gmt'] = $postarr['post_date_gmt'] ?? gmdate( 'Y-m-d H:i:s' );

        $GLOBALS['tew_stub_posts'][ $postarr['ID'] ] = $postarr;

        return $postarr['ID'];
    }
}

if ( ! function_exists( 'wp_update_post' ) ) {
    function wp_update_post( $postarr ) {
        if ( empty( $postarr['ID'] ) || empty( $GLOBALS['tew_stub_posts'][ $postarr['ID'] ] ) ) {
            return 0;
        }

        $GLOBALS['tew_stub_posts'][ $postarr['ID'] ] = array_merge(
            $GLOBALS['tew_stub_posts'][ $postarr['ID'] ],
            $postarr
        );

        return $postarr['ID'];
    }
}

if ( ! function_exists( 'wp_delete_post' ) ) {
    function wp_delete_post( $post_id, $force_delete = false ) {
        unset( $GLOBALS['tew_stub_posts'][ $post_id ], $GLOBALS['tew_stub_post_meta'][ $post_id ] );
        return true;
    }
}

if ( ! function_exists( 'update_post_meta' ) ) {
    function update_post_meta( $post_id, $meta_key, $value ) {
        $GLOBALS['tew_stub_post_meta'][ $post_id ][ $meta_key ] = $value;
        return true;
    }
}

if ( ! function_exists( 'get_post_meta' ) ) {
    function get_post_meta( $post_id, $meta_key, $single = false ) {
        if ( ! isset( $GLOBALS['tew_stub_post_meta'][ $post_id ][ $meta_key ] ) ) {
            return $single ? '' : [];
        }

        $value = $GLOBALS['tew_stub_post_meta'][ $post_id ][ $meta_key ];

        if ( $single && is_string( $value ) ) {
            return stripslashes( $value );
        }

        return $single ? $value : [ $value ];
    }
}

if ( ! function_exists( 'get_post' ) ) {
    function get_post( $post_id ) {
        if ( ! isset( $GLOBALS['tew_stub_posts'][ $post_id ] ) ) {
            return null;
        }

        return (object) $GLOBALS['tew_stub_posts'][ $post_id ];
    }
}

if ( ! function_exists( 'get_permalink' ) ) {
    function get_permalink( $post_id = 0 ) {
        return 'https://example.test/?p=' . (int) $post_id;
    }
}

if ( ! function_exists( 'home_url' ) ) {
    function home_url( $path = '' ) {
        return 'https://example.test/' . ltrim( $path, '/' );
    }
}

if ( ! function_exists( 'admin_url' ) ) {
    function admin_url( $path = '' ) {
        return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
    }
}

if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ) {
        return $thing instanceof WP_Error;
    }
}

if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( $capability ) {
        return true;
    }
}

if ( ! function_exists( 'get_header' ) ) {
    function get_header() {
        return true;
    }
}

if ( ! function_exists( 'get_footer' ) ) {
    function get_footer() {
        return true;
    }
}

if ( ! function_exists( 'the_title' ) ) {
    function the_title() {
        echo 'Análisis de Sostenibilidad Web';
    }
}

if ( ! function_exists( 'get_the_ID' ) ) {
    function get_the_ID() {
        return 0;
    }
}

if ( ! function_exists( 'is_singular' ) ) {
    function is_singular( $post_type = '' ) {
        return false;
    }
}

if ( ! function_exists( 'load_plugin_textdomain' ) ) {
    function load_plugin_textdomain( $domain, $deprecated = false, $plugin_rel_path = false ) {
        return true;
    }
}

if ( ! function_exists( 'plugin_basename' ) ) {
    function plugin_basename( $file ) {
        return basename( $file );
    }
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
    function plugin_dir_path( $file ) {
        return rtrim( str_replace( '\\', '/', dirname( $file ) ), '/' ) . '/';
    }
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
    function plugin_dir_url( $file ) {
        return 'https://example.test/wp-content/plugins/' . trim( basename( dirname( $file ) ), '/' ) . '/';
    }
}

if ( ! function_exists( 'register_activation_hook' ) ) {
    function register_activation_hook( $file, $callback ) {
        if ( is_callable( $callback ) ) {
            $callback();
        }
        return true;
    }
}

if ( ! function_exists( 'register_deactivation_hook' ) ) {
    function register_deactivation_hook( $file, $callback ) {
        return true;
    }
}

if ( ! function_exists( 'flush_rewrite_rules' ) ) {
    function flush_rewrite_rules() {
        return true;
    }
}

if ( ! function_exists( 'wp_remote_get' ) ) {
    function wp_remote_get( $url, $args = [] ) {
        return [
            'response' => [ 'code' => 200 ],
            'body'     => '',
        ];
    }
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
    function wp_remote_retrieve_response_code( $response ) {
        return isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
    }
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
    function wp_remote_retrieve_body( $response ) {
        return $response['body'] ?? '';
    }
}

if ( ! function_exists( 'trailingslashit' ) ) {
    function trailingslashit( $string ) {
        return rtrim( $string, "/\\" ) . '/';
    }
}

if ( ! function_exists( 'add_query_arg' ) ) {
    function add_query_arg( $args, $url = '' ) {
        $parsed = parse_url( $url );
        $query  = [];

        if ( ! empty( $parsed['query'] ) ) {
            parse_str( $parsed['query'], $query );
        }

        foreach ( (array) $args as $key => $value ) {
            $query[ $key ] = $value;
        }

        $base = isset( $parsed['scheme'] ) ? $parsed['scheme'] . '://' : '';
        $base .= $parsed['host'] ?? '';
        if ( ! empty( $parsed['port'] ) ) {
            $base .= ':' . $parsed['port'];
        }
        $base .= $parsed['path'] ?? '';

        $query_string = http_build_query( $query, '', '&' );

        return $base . ( $query_string ? '?' . $query_string : '' );
    }
}

if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        public $errors = [];
        public $error_data = [];

        public function __construct( $code = '', $message = '', $data = '' ) {
            if ( '' !== $code ) {
                $this->errors[ $code ][] = $message;
                if ( $data ) {
                    $this->error_data[ $code ] = $data;
                }
            }
        }

        public function get_error_code() {
            $codes = array_keys( $this->errors );
            return $codes ? $codes[0] : '';
        }

        public function get_error_message( $code = '' ) {
            if ( $code && isset( $this->errors[ $code ][0] ) ) {
                return $this->errors[ $code ][0];
            }

            $codes = array_keys( $this->errors );
            $code  = $codes ? $codes[0] : '';

            return $code && isset( $this->errors[ $code ][0] ) ? $this->errors[ $code ][0] : '';
        }

        public function get_error_data( $code = '' ) {
            if ( $code && isset( $this->error_data[ $code ] ) ) {
                return $this->error_data[ $code ];
            }

            $codes = array_keys( $this->error_data );
            $code  = $codes ? $codes[0] : '';

            return $code && isset( $this->error_data[ $code ] ) ? $this->error_data[ $code ] : null;
        }

        public function add( $code, $message, $data = '' ) {
            $this->errors[ $code ][] = $message;
            if ( $data ) {
                $this->error_data[ $code ] = $data;
            }
        }
    }
}

if ( ! class_exists( 'WP_Query' ) ) {
    class WP_Query {
        public $posts = [];

        public function __construct( $args = [] ) {
            $this->query( $args );
        }

        public function query( $args ) {
            $posts = $GLOBALS['tew_stub_posts'];
            $results = [];

            foreach ( $posts as $post_id => $post ) {
                if ( isset( $args['post_type'] ) && $post['post_type'] !== $args['post_type'] ) {
                    continue;
                }

                if ( isset( $args['post_status'] ) && $post['post_status'] !== $args['post_status'] ) {
                    continue;
                }

                if ( isset( $args['meta_key'] ) ) {
                    $meta_value = $GLOBALS['tew_stub_post_meta'][ $post_id ][ $args['meta_key'] ] ?? null;
                    if ( $meta_value !== $args['meta_value'] ) {
                        continue;
                    }
                }

                $results[] = ( 'ids' === ( $args['fields'] ?? '' ) ) ? $post_id : (object) $post;
            }

            if ( 'DESC' === ( $args['order'] ?? '' ) ) {
                usort(
                    $results,
                    function ( $a, $b ) use ( $args ) {
                        $get_date = function ( $item ) {
                            $post = is_object( $item ) ? (array) $item : ( $GLOBALS['tew_stub_posts'][ $item ] ?? [] );
                            return strtotime( $post['post_date_gmt'] ?? 'now' );
                        };

                        return $get_date( $b ) <=> $get_date( $a );
                    }
                );
            }

            if ( isset( $args['posts_per_page'] ) && -1 !== (int) $args['posts_per_page'] ) {
                $results = array_slice( $results, 0, (int) $args['posts_per_page'] );
            }

            $this->posts = $results;

            return $this->posts;
        }
    }
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
    class WP_REST_Request {
        protected $params = [];

        public function __construct( $params = [] ) {
            $this->params = $params;
        }

        public function get_param( $key ) {
            return $this->params[ $key ] ?? null;
        }

        public function get_params() {
            return $this->params;
        }
    }
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
    class WP_REST_Response {
        protected $data;
        protected $status;

        public function __construct( $data = null, $status = 200 ) {
            $this->data   = $data;
            $this->status = $status;
        }

        public function get_data() {
            return $this->data;
        }

        public function get_status() {
            return $this->status;
        }

        public function set_status( $status ) {
            $this->status = (int) $status;
        }
    }
}
