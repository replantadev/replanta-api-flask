<?php
namespace TEW\Reporting;

use TEW\Utils;
use WP_Error;
use WP_Query;
use function current_time;
use function delete_post_meta;
use function esc_url_raw;
use function get_option;
use function get_permalink;
use function get_post;
use function get_post_meta;
use function is_wp_error;
use function sanitize_email;
use function sanitize_text_field;
use function sanitize_title;
use function update_post_meta;
use function wp_date;
use function wp_delete_post;
use function wp_insert_post;
use function wp_json_encode;
use function wp_remote_get;
use function wp_remote_retrieve_body;
use function wp_slash;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Report_Storage {

    const POST_TYPE = 'tew_audit';
    const META_PAYLOAD = '_tew_report_payload';
    const META_URL = '_tew_report_url';
    const META_SCORE = '_tew_report_score';
    const META_GENERATED = '_tew_report_generated_at';
    const META_IS_GREEN = '_tew_report_is_green';
    const META_HOSTING_PROVIDER = '_tew_report_hosting_provider';
    const META_USER_IP = '_tew_report_user_ip';
    const META_USER_COUNTRY = '_tew_report_user_country';
    const META_USER_EMAIL = '_tew_report_user_email';
    const META_CO2_PER_VIEW = '_tew_report_co2_per_view';
    const META_GRADE = '_tew_report_grade';
    const META_POTENTIAL = '_tew_report_potential';
    
    // Success Case metadata
    const META_IS_SUCCESS_CASE = '_tew_is_success_case';
    const META_BEFORE_REPORT_ID = '_tew_before_report_id';
    const META_AFTER_REPORT_ID = '_tew_after_report_id';
    const META_CLIENT_NAME = '_tew_client_name';
    const META_TESTIMONIAL = '_tew_testimonial';
    const META_MIGRATION_DATE = '_tew_migration_date';
    const META_IS_PUBLIC = '_tew_is_public';

    /**
     * @var Custom_Report_Repository
     */
    private $custom_repository;

    /**
     * Registra el tipo de contenido y la metadata asociada.
     */
    public function register() {
        $this->register_post_type();
        $this->register_meta();
        
        // Limpiar cache cuando se borra un informe
        add_action( 'before_delete_post', [ $this, 'clear_cache_on_delete' ] );

        $table = new Custom_Report_Table();
        $table->maybe_upgrade();
    }
    
    /**
     * Limpia el cache cuando se borra un informe.
     *
     * @param int $post_id
     */
    public function clear_cache_on_delete( $post_id ) {
        // Verificar que es nuestro CPT
        if ( get_post_type( $post_id ) !== self::POST_TYPE ) {
            return;
        }
        
        // Obtener la URL del informe
        $url = get_post_meta( $post_id, self::META_URL, true );
        
        if ( ! empty( $url ) ) {
            // Limpiar cache
            $cache = new \TEW\Cache();
            $cache->delete( $url );
        }
    }

    /**
     * Persiste un informe y devuelve el identificador y enlace público.
     *
     * @param array $report
     *
     * @return array|WP_Error
     */
    public function save( array $report ) {
        $url = isset( $report['url'] ) ? esc_url_raw( $report['url'] ) : '';
        if ( empty( $url ) ) {
            return new WP_Error( 'tew_storage_missing_url', __( 'No se pudo guardar la auditoría sin URL de referencia.', 'test-eco-website' ) );
        }

        // Limpiar cache antes de guardar para que el próximo request obtenga datos frescos
        $cache = new \TEW\Cache();
        $cache->delete( $url );

        $generated_at = isset( $report['metadata']['generated_at'] ) ? $report['metadata']['generated_at'] : current_time( 'mysql', true );
        $timestamp    = strtotime( $generated_at ) ?: time();
        $domain       = Utils::get_domain( $url );
        $title        = sprintf(
            /* translators: 1: domain, 2: formatted date */
            __( 'Informe %1$s · %2$s', 'test-eco-website' ),
            $domain,
            wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp )
        );

        $post_id = wp_insert_post(
            [
                'post_type'   => self::POST_TYPE,
                'post_status' => 'publish',
                'post_title'  => $title,
                'post_name'   => sanitize_title( $domain . '-' . $generated_at ),
            ],
            true
        );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        $payload = wp_json_encode( $report );
        if ( false === $payload ) {
            wp_delete_post( $post_id, true );

            return new WP_Error( 'tew_storage_encode_error', __( 'No se pudo serializar el informe para guardarlo.', 'test-eco-website' ) );
        }

        update_post_meta( $post_id, self::META_PAYLOAD, wp_slash( $payload ) );
        update_post_meta( $post_id, self::META_URL, $url );

        // Guardar score y grade (intentar summary.score primero, luego overall_score)
        $score = null;
        if ( isset( $report['summary']['score'] ) ) {
            $score = (float) $report['summary']['score'];
        } elseif ( isset( $report['summary']['overall_score'] ) ) {
            $score = (float) $report['summary']['overall_score'];
        }
        
        if ( null !== $score ) {
            update_post_meta( $post_id, self::META_SCORE, $score );
        }

        if ( isset( $report['summary']['grade'] ) ) {
            update_post_meta( $post_id, self::META_GRADE, sanitize_text_field( $report['summary']['grade'] ) );
        }

        update_post_meta( $post_id, self::META_GENERATED, $generated_at );

        // Guardar datos de hosting verde (intentar greenweb primero, luego green_hosting)
        $is_green = null;
        if ( isset( $report['metrics']['greenweb']['is_green'] ) ) {
            $is_green = (bool) $report['metrics']['greenweb']['is_green'];
        } elseif ( isset( $report['metrics']['green_hosting']['is_green'] ) ) {
            $is_green = (bool) $report['metrics']['green_hosting']['is_green'];
        }
        
        if ( null !== $is_green ) {
            update_post_meta( $post_id, self::META_IS_GREEN, $is_green ? '1' : '0' );
        }

        // Hosting provider
        $provider = null;
        if ( isset( $report['metrics']['greenweb']['provider'] ) ) {
            $provider = $report['metrics']['greenweb']['provider'];
        } elseif ( isset( $report['metrics']['green_hosting']['hosted_by'] ) ) {
            $provider = $report['metrics']['green_hosting']['hosted_by'];
        } elseif ( isset( $report['metrics']['green_hosting']['hosting_provider'] ) ) {
            $provider = $report['metrics']['green_hosting']['hosting_provider'];
        }
        
        if ( $provider && $provider !== 'N/A' ) {
            update_post_meta( $post_id, self::META_HOSTING_PROVIDER, sanitize_text_field( $provider ) );
        }

        // Guardar huella de carbono (intentar websitecarbon primero, luego carbon)
        $co2 = null;
        if ( isset( $report['metrics']['websitecarbon']['co2_per_view'] ) ) {
            $co2 = (float) $report['metrics']['websitecarbon']['co2_per_view'];
        } elseif ( isset( $report['metrics']['carbon']['co2_per_view'] ) ) {
            $co2 = (float) $report['metrics']['carbon']['co2_per_view'];
        }
        
        if ( null !== $co2 ) {
            update_post_meta( $post_id, self::META_CO2_PER_VIEW, $co2 );
        }

        // Guardar datos del usuario (IP y país)
        $user_ip = $this->get_user_ip();
        if ( $user_ip ) {
            update_post_meta( $post_id, self::META_USER_IP, $user_ip );
            
            // Obtener país desde IP (usamos API gratuita)
            $country = $this->get_country_from_ip( $user_ip );
            if ( $country ) {
                update_post_meta( $post_id, self::META_USER_COUNTRY, $country );
            }
        }

        // Email del usuario (si se proporciona después)
        if ( isset( $report['metadata']['user_email'] ) && ! empty( $report['metadata']['user_email'] ) ) {
            update_post_meta( $post_id, self::META_USER_EMAIL, sanitize_email( $report['metadata']['user_email'] ) );
        }

        // Calcular potencial (score bajo + no verde = alto potencial)
        $potential = $this->calculate_potential( $report );
        update_post_meta( $post_id, self::META_POTENTIAL, $potential );

        // Marcar como público por defecto para que aparezca en el showcase
        update_post_meta( $post_id, self::META_IS_PUBLIC, '1' );

        $result = [
            'id'        => $post_id,
            'permalink' => get_permalink( $post_id ),
        ];

        if ( $this->is_dual_write_enabled() ) {
            $this->custom_repository()->upsert_for_legacy_post( $post_id, $report );
        }

        return $result;
    }

    /**
     * Recupera un informe persistido.
     *
     * @param int $post_id
     *
     * @return array|WP_Error
     */
    public function find( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post || self::POST_TYPE !== $post->post_type ) {
            return new WP_Error( 'tew_report_not_found', __( 'No se encontró el informe solicitado.', 'test-eco-website' ), [ 'status' => 404 ] );
        }

        $payload = '';

        if ( $this->is_custom_read_enabled() ) {
            $payload = (string) $this->custom_repository()->get_payload_by_legacy_post( $post_id );
        }

        if ( empty( $payload ) ) {
            $payload = get_post_meta( $post_id, self::META_PAYLOAD, true );
        }

        if ( empty( $payload ) ) {
            return new WP_Error( 'tew_report_empty', __( 'El informe guardado está vacío o dañado.', 'test-eco-website' ), [ 'status' => 500 ] );
        }

        $decoded = json_decode( $payload, true );
        if ( null === $decoded ) {
            return new WP_Error( 'tew_report_invalid', __( 'El informe guardado no se pudo interpretar.', 'test-eco-website' ), [ 'status' => 500 ] );
        }

        $permalink = get_permalink( $post_id );
        $decoded['metadata']['share_url']  = $permalink;
        $decoded['metadata']['report_url'] = $permalink;
        $decoded['metadata']['report_id']  = $post_id;
        $decoded['metadata']['history']    = $this->recent_for_url( $decoded['url'], 5 );

        return $decoded;
    }

    /**
     * Devuelve los últimos informes para una URL específica.
     *
     * @param string $url
     * @param int    $limit
     *
     * @return array
     */
    public function recent_for_url( $url, $limit = 5 ) {
        if ( $this->is_custom_read_enabled() ) {
            $rows = $this->custom_repository()->get_recent_by_url( $url, $limit );

            if ( ! empty( $rows ) ) {
                $items = [];

                foreach ( $rows as $row ) {
                    $post_id = isset( $row['legacy_post_id'] ) ? (int) $row['legacy_post_id'] : 0;
                    if ( $post_id <= 0 ) {
                        continue;
                    }

                    $items[] = [
                        'id'        => $post_id,
                        'permalink' => get_permalink( $post_id ),
                        'score'     => isset( $row['score'] ) ? (float) $row['score'] : 0.0,
                        'generated' => isset( $row['generated_at'] ) ? $row['generated_at'] : '',
                    ];
                }

                if ( ! empty( $items ) ) {
                    return $items;
                }
            }
        }

        $query = new WP_Query(
            [
                'post_type'      => self::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => $limit,
                'meta_key'       => self::META_URL,
                'meta_value'     => esc_url_raw( $url ),
                'orderby'        => 'date',
                'order'          => 'DESC',
                'no_found_rows'  => true,
                'fields'         => 'ids',
            ]
        );

        if ( empty( $query->posts ) ) {
            return [];
        }

        $items = [];
        foreach ( $query->posts as $post_id ) {
            $items[] = [
                'id'         => $post_id,
                'permalink'  => get_permalink( $post_id ),
                'score'      => (float) get_post_meta( $post_id, self::META_SCORE, true ),
                'generated'  => get_post_meta( $post_id, self::META_GENERATED, true ),
            ];
        }

        return $items;
    }

    private function register_post_type() {
        $labels = [
            'name'               => __( 'Todos los Informes', 'test-eco-website' ),
            'singular_name'      => __( 'Informe Eco', 'test-eco-website' ),
            'add_new'            => __( 'Añadir informe', 'test-eco-website' ),
            'add_new_item'       => __( 'Añadir nuevo informe', 'test-eco-website' ),
            'edit_item'          => __( 'Editar informe', 'test-eco-website' ),
            'view_item'          => __( 'Ver informe', 'test-eco-website' ),
            'search_items'       => __( 'Buscar informes', 'test-eco-website' ),
            'not_found'          => __( 'No se encontraron informes.', 'test-eco-website' ),
            'not_found_in_trash' => __( 'No hay informes en la papelera.', 'test-eco-website' ),
        ];

        register_post_type(
            self::POST_TYPE,
            [
                'labels'             => $labels,
                'public'             => true,
                'publicly_queryable' => true,
                'show_ui'            => current_user_can( 'manage_options' ),
                'show_in_menu'       => 'tew-settings', // Mostrar bajo Eco Snapshot
                'exclude_from_search'=> true,
                'has_archive'        => false,
                'rewrite'            => [ 'slug' => 'eco-report', 'with_front' => false ],
                'supports'           => [ 'title' ],
            ]
        );
    }

    private function register_meta() {
        register_post_meta(
            self::POST_TYPE,
            self::META_PAYLOAD,
            [
                'show_in_rest' => false,
                'single'       => true,
                'type'         => 'string',
            ]
        );

        register_post_meta(
            self::POST_TYPE,
            self::META_URL,
            [
                'show_in_rest' => false,
                'single'       => true,
                'type'         => 'string',
            ]
        );

        register_post_meta(
            self::POST_TYPE,
            self::META_SCORE,
            [
                'show_in_rest' => false,
                'single'       => true,
                'type'         => 'number',
            ]
        );

        register_post_meta(
            self::POST_TYPE,
            self::META_GENERATED,
            [
                'show_in_rest' => false,
                'single'       => true,
                'type'         => 'string',
            ]
        );

        register_post_meta(
            self::POST_TYPE,
            self::META_IS_GREEN,
            [
                'show_in_rest' => false,
                'single'       => true,
                'type'         => 'boolean',
            ]
        );

        register_post_meta(
            self::POST_TYPE,
            self::META_HOSTING_PROVIDER,
            [
                'show_in_rest' => false,
                'single'       => true,
                'type'         => 'string',
            ]
        );

        register_post_meta(
            self::POST_TYPE,
            self::META_USER_IP,
            [
                'show_in_rest' => false,
                'single'       => true,
                'type'         => 'string',
            ]
        );

        register_post_meta(
            self::POST_TYPE,
            self::META_USER_COUNTRY,
            [
                'show_in_rest' => false,
                'single'       => true,
                'type'         => 'string',
            ]
        );

        register_post_meta(
            self::POST_TYPE,
            self::META_USER_EMAIL,
            [
                'show_in_rest' => false,
                'single'       => true,
                'type'         => 'string',
            ]
        );

        register_post_meta(
            self::POST_TYPE,
            self::META_CO2_PER_VIEW,
            [
                'show_in_rest' => false,
                'single'       => true,
                'type'         => 'number',
            ]
        );

        register_post_meta(
            self::POST_TYPE,
            self::META_GRADE,
            [
                'show_in_rest' => false,
                'single'       => true,
                'type'         => 'string',
            ]
        );

        register_post_meta(
            self::POST_TYPE,
            self::META_POTENTIAL,
            [
                'show_in_rest' => false,
                'single'       => true,
                'type'         => 'string',
            ]
        );
    }

    /**
     * Obtiene la IP del usuario actual.
     *
     * @return string|null
     */
    private function get_user_ip() {
        $ip = null;

        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        // Validar IP
        if ( $ip && filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
            return $ip;
        }

        return null;
    }

    /**
     * Obtiene el país desde una IP usando API gratuita.
     *
     * @param string $ip
     * @return string|null
     */
    private function get_country_from_ip( $ip ) {
        // Usar API gratuita de ip-api.com (1000 req/día gratis)
        $response = wp_remote_get(
            "http://ip-api.com/json/{$ip}?fields=country,countryCode",
            [
                'timeout' => 3,
                'headers' => [ 'Accept' => 'application/json' ],
            ]
        );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( isset( $data['country'] ) ) {
            return sanitize_text_field( $data['country'] );
        }

        return null;
    }

    /**
     * Calcula el potencial de conversión basado en score y hosting.
     *
     * @param array $report
     * @return string 'high'|'medium'|'low'
     */
    private function calculate_potential( $report ) {
        $score = isset( $report['summary']['score'] ) ? (float) $report['summary']['score'] : 50;
        $is_green = isset( $report['metrics']['greenweb']['is_green'] ) ? (bool) $report['metrics']['greenweb']['is_green'] : false;

        // Alto potencial: score bajo Y no verde
        if ( $score < 50 && ! $is_green ) {
            return 'high';
        }

        // Medio potencial: score bajo O no verde
        if ( $score < 50 || ! $is_green ) {
            return 'medium';
        }

        // Bajo potencial: buen score Y verde
        return 'low';
    }

    /**
     * Actualiza el email de un informe existente.
     *
     * @param int    $post_id
     * @param string $email
     * @return bool
     */
    public function update_email( $post_id, $email ) {
        $email = sanitize_email( $email );
        if ( empty( $email ) ) {
            return false;
        }

        $updated = update_post_meta( $post_id, self::META_USER_EMAIL, $email );

        if ( $this->is_dual_write_enabled() ) {
            $this->custom_repository()->update_email_by_legacy_post( $post_id, $email );
        }

        return $updated;
    }

    /**
     * @return Custom_Report_Repository
     */
    private function custom_repository() {
        if ( null === $this->custom_repository ) {
            $this->custom_repository = new Custom_Report_Repository();
        }

        return $this->custom_repository;
    }

    /**
     * @return string
     */
    private function get_storage_mode() {
        $settings = get_option( 'tew_settings', [] );
        $mode     = isset( $settings['storage_mode'] ) ? sanitize_text_field( $settings['storage_mode'] ) : 'legacy';
        $allowed  = [ 'legacy', 'dual_write', 'custom_read' ];

        return in_array( $mode, $allowed, true ) ? $mode : 'legacy';
    }

    /**
     * @return bool
     */
    private function is_dual_write_enabled() {
        return in_array( $this->get_storage_mode(), [ 'dual_write', 'custom_read' ], true );
    }

    /**
     * @return bool
     */
    private function is_custom_read_enabled() {
        return 'custom_read' === $this->get_storage_mode();
    }

    /**
     * Obtiene todos los análisis con filtros opcionales.
     *
     * @param array $args Filtros: is_green, potential, country, has_email, etc.
     * @return array
     */
    public function get_all_analyses( $args = [] ) {
        $defaults = [
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $query_args = array_merge( $defaults, [
            'post_type'   => self::POST_TYPE,
            'post_status' => 'publish',
        ] );

        // Filtros por meta
        $meta_query = [ 'relation' => 'AND' ];

        if ( isset( $args['is_green'] ) ) {
            $meta_query[] = [
                'key'   => self::META_IS_GREEN,
                'value' => (bool) $args['is_green'],
                'type'  => 'BOOLEAN',
            ];
        }

        if ( isset( $args['potential'] ) ) {
            $meta_query[] = [
                'key'   => self::META_POTENTIAL,
                'value' => sanitize_text_field( $args['potential'] ),
            ];
        }

        if ( isset( $args['country'] ) ) {
            $meta_query[] = [
                'key'   => self::META_USER_COUNTRY,
                'value' => sanitize_text_field( $args['country'] ),
            ];
        }

        if ( isset( $args['has_email'] ) && $args['has_email'] ) {
            $meta_query[] = [
                'key'     => self::META_USER_EMAIL,
                'compare' => 'EXISTS',
            ];
        }

        if ( count( $meta_query ) > 1 ) {
            $query_args['meta_query'] = $meta_query;
        }

        if ( isset( $args['posts_per_page'] ) ) {
            $query_args['posts_per_page'] = (int) $args['posts_per_page'];
        }

        $query = new WP_Query( $query_args );

        $results = [];
        foreach ( $query->posts as $post ) {
            $score_meta = get_post_meta( $post->ID, self::META_SCORE, true );
            $score = $score_meta !== '' ? (float) $score_meta : 0.0;
            
            // Fallback: si el score es 0 o vacío, intentar extraer del payload
            if ( empty( $score ) || $score === 0.0 ) {
                $payload = get_post_meta( $post->ID, self::META_PAYLOAD, true );
                if ( ! empty( $payload ) ) {
                    $decoded = json_decode( $payload, true );
                    if ( isset( $decoded['summary']['overall_score'] ) ) {
                        $score = (float) $decoded['summary']['overall_score'];
                        // Actualizar el meta para que no sea necesario en el futuro
                        update_post_meta( $post->ID, self::META_SCORE, $score );
                    }
                }
            }
            
            $results[] = [
                'id'               => $post->ID,
                'url'              => get_post_meta( $post->ID, self::META_URL, true ),
                'score'            => $score,
                'grade'            => get_post_meta( $post->ID, self::META_GRADE, true ),
                'is_green'         => (bool) get_post_meta( $post->ID, self::META_IS_GREEN, true ),
                'hosting_provider' => get_post_meta( $post->ID, self::META_HOSTING_PROVIDER, true ),
                'country'          => get_post_meta( $post->ID, self::META_USER_COUNTRY, true ),
                'email'            => get_post_meta( $post->ID, self::META_USER_EMAIL, true ),
                'co2_per_view'     => (float) get_post_meta( $post->ID, self::META_CO2_PER_VIEW, true ),
                'potential'        => get_post_meta( $post->ID, self::META_POTENTIAL, true ),
                'generated_at'     => get_post_meta( $post->ID, self::META_GENERATED, true ),
                'permalink'        => get_permalink( $post->ID ),
            ];
        }

        return $results;
    }
    
    /**
     * Marca un informe como caso de éxito y lo vincula con informes antes/después.
     *
     * @param int    $after_report_id  ID del informe "después" (principal).
     * @param int    $before_report_id ID del informe "antes".
     * @param string $client_name      Nombre del cliente (opcional).
     * @param string $testimonial      Testimonio del cliente (opcional).
     *
     * @return bool|WP_Error
     */
    public function mark_as_success_case( $after_report_id, $before_report_id, $client_name = '', $testimonial = '' ) {
        $after_post  = get_post( $after_report_id );
        $before_post = get_post( $before_report_id );

        if ( ! $after_post || $after_post->post_type !== self::POST_TYPE ) {
            return new WP_Error( 'tew_invalid_after_report', __( 'El informe "después" no es válido.', 'test-eco-website' ) );
        }

        if ( ! $before_post || $before_post->post_type !== self::POST_TYPE ) {
            return new WP_Error( 'tew_invalid_before_report', __( 'El informe "antes" no es válido.', 'test-eco-website' ) );
        }

        update_post_meta( $after_report_id, self::META_IS_SUCCESS_CASE, true );
        update_post_meta( $after_report_id, self::META_BEFORE_REPORT_ID, $before_report_id );
        update_post_meta( $after_report_id, self::META_AFTER_REPORT_ID, $after_report_id );

        if ( ! empty( $client_name ) ) {
            update_post_meta( $after_report_id, self::META_CLIENT_NAME, sanitize_text_field( $client_name ) );
        }

        if ( ! empty( $testimonial ) ) {
            update_post_meta( $after_report_id, self::META_TESTIMONIAL, wp_slash( $testimonial ) );
        }

        update_post_meta( $after_report_id, self::META_MIGRATION_DATE, current_time( 'mysql', true ) );
        update_post_meta( $after_report_id, self::META_IS_PUBLIC, true );

        // Marcar el informe "antes" también
        update_post_meta( $before_report_id, self::META_IS_PUBLIC, true );

        return true;
    }

    /**
     * Obtiene todos los casos de éxito.
     *
     * @param int $limit Límite de resultados.
     *
     * @return array
     */
    public function get_success_cases( $limit = 10 ) {
        global $wpdb;
        
        // Query directa para evitar filtros de Polylang completamente
        $post_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = %s
             AND p.post_status = 'publish'
             AND pm.meta_key = '_tew_is_success_case'
             AND pm.meta_value = '1'
             ORDER BY p.post_date DESC
             LIMIT %d",
            self::POST_TYPE,
            $limit
        ) );

        $cases = [];
        foreach ( $post_ids as $post_id ) {
            $before_id = (int) get_post_meta( $post_id, self::META_BEFORE_REPORT_ID, true );
            
            // Si no hay before_id, omitir
            if ( empty( $before_id ) ) {
                continue;
            }
            
            $before = $this->find( $before_id );

            if ( is_wp_error( $before ) ) {
                error_log( '[TEW] Success case #' . $post_id . ' - before report #' . $before_id . ' not found: ' . $before->get_error_message() );
                continue;
            }

            $after = $this->find( $post_id );
            if ( is_wp_error( $after ) ) {
                error_log( '[TEW] Success case #' . $post_id . ' - after report error: ' . $after->get_error_message() );
                continue;
            }

            $cases[] = [
                'before'         => $before,
                'after'          => $after,
                'before_id'      => $before_id,
                'after_id'       => (int) $post_id,
                'client_name'    => get_post_meta( $post_id, self::META_CLIENT_NAME, true ),
                'testimonial'    => get_post_meta( $post_id, self::META_TESTIMONIAL, true ),
                'migration_date' => get_post_meta( $post_id, self::META_MIGRATION_DATE, true ),
                'improvements'   => $this->get_improvement_stats( $before, $after ),
            ];
        }

        return $cases;
    }

    /**
     * Obtiene informes públicos (no casos de éxito).
     *
     * @param array $args Argumentos de query.
     *
     * @return array
     */
    public function get_public_reports( $args = [] ) {
        global $wpdb;
        
        $defaults = [
            'limit'   => 10,
            'exclude_success_cases' => true,
        ];

        $args = array_merge( $defaults, $args );

        // Query directa para evitar filtros de Polylang completamente
        $exclude_clause = '';
        if ( $args['exclude_success_cases'] ) {
            $exclude_clause = "AND p.ID NOT IN (
                SELECT post_id FROM {$wpdb->postmeta} 
                WHERE meta_key = '_tew_is_success_case' AND meta_value = '1'
            )";
        }

        $post_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = %s
             AND p.post_status = 'publish'
             AND pm.meta_key = '_tew_is_public'
             AND pm.meta_value = '1'
             {$exclude_clause}
             ORDER BY p.post_date DESC
             LIMIT %d",
            self::POST_TYPE,
            $args['limit']
        ) );

        $reports = [];
        foreach ( $post_ids as $post_id ) {
            $report = $this->find( $post_id );
            if ( ! is_wp_error( $report ) ) {
                $reports[] = $report;
            }
        }

        return $reports;
    }

    /**
     * Calcula estadísticas de mejora entre dos informes.
     *
     * @param array $before Informe "antes".
     * @param array $after  Informe "después".
     *
     * @return array
     */
    public function get_improvement_stats( $before, $after ) {
        $before_score = isset( $before['summary']['overall_score'] ) ? (float) $before['summary']['overall_score'] : 0;
        $after_score  = isset( $after['summary']['overall_score'] ) ? (float) $after['summary']['overall_score'] : 0;

        $before_co2 = isset( $before['metrics']['carbon']['co2_per_view'] ) ? (float) $before['metrics']['carbon']['co2_per_view'] : 0;
        $after_co2  = isset( $after['metrics']['carbon']['co2_per_view'] ) ? (float) $after['metrics']['carbon']['co2_per_view'] : 0;

        $before_green = isset( $before['metrics']['green_hosting']['is_green'] ) ? (bool) $before['metrics']['green_hosting']['is_green'] : false;
        $after_green  = isset( $after['metrics']['green_hosting']['is_green'] ) ? (bool) $after['metrics']['green_hosting']['is_green'] : false;

        // Extraer TTFB del móvil (prioritario) o escritorio si no hay móvil
        $before_ttfb = $this->extract_ttfb_from_report( $before );
        $after_ttfb  = $this->extract_ttfb_from_report( $after );

        $score_diff = $after_score - $before_score;
        $score_percent = $before_score > 0 ? round( ( $score_diff / $before_score ) * 100, 1 ) : 0;

        $co2_diff = $after_co2 - $before_co2;
        $co2_percent = $before_co2 > 0 ? round( ( $co2_diff / $before_co2 ) * 100, 1 ) : 0;

        $ttfb_diff = $after_ttfb - $before_ttfb;
        $ttfb_percent = $before_ttfb > 0 ? round( ( $ttfb_diff / $before_ttfb ) * 100, 1 ) : 0;

        return [
            'score'        => [
                'before'  => $before_score,
                'after'   => $after_score,
                'diff'    => $score_diff,
                'percent' => $score_percent,
            ],
            'co2'          => [
                'before'  => $before_co2,
                'after'   => $after_co2,
                'diff'    => $co2_diff,
                'percent' => $co2_percent,
            ],
            'ttfb'         => [
                'before'  => $before_ttfb,
                'after'   => $after_ttfb,
                'diff'    => $ttfb_diff,
                'percent' => $ttfb_percent,
            ],
            'green_hosting' => [
                'before' => $before_green,
                'after'  => $after_green,
                'improved' => ! $before_green && $after_green,
            ],
        ];
    }

    /**
     * Extrae el TTFB de un informe (móvil prioritario, escritorio si no hay móvil).
     *
     * @param array $report Datos del informe.
     *
     * @return float TTFB en ms, o 0 si no existe.
     */
    private function extract_ttfb_from_report( $report ) {
        // Buscar en móvil primero
        if ( isset( $report['metrics']['performance']['mobile']['ttfb_ms'] ) ) {
            return (float) $report['metrics']['performance']['mobile']['ttfb_ms'];
        }

        // Si no hay móvil, buscar en escritorio
        if ( isset( $report['metrics']['performance']['desktop']['ttfb_ms'] ) ) {
            return (float) $report['metrics']['performance']['desktop']['ttfb_ms'];
        }

        // Buscar en scorecard components si no está en metrics directos
        if ( isset( $report['scorecard']['components'] ) ) {
            foreach ( $report['scorecard']['components'] as $component ) {
                if ( isset( $component['meta']['ttfb_ms'] ) ) {
                    return (float) $component['meta']['ttfb_ms'];
                }
            }
        }

        return 0;
    }

    /**
     * Hace un informe público.
     *
     * @param int $post_id ID del informe.
     *
     * @return bool
     */
    public function make_public( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== self::POST_TYPE ) {
            return false;
        }

        return update_post_meta( $post_id, self::META_IS_PUBLIC, true );
    }

    /**
     * Hace un informe privado.
     *
     * @param int $post_id ID del informe.
     *
     * @return bool
     */
    public function make_private( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== self::POST_TYPE ) {
            return false;
        }

        return update_post_meta( $post_id, self::META_IS_PUBLIC, false );
    }

    /**
     * Elimina un caso de éxito.
     *
     * @param int $post_id ID del informe "después" que es el caso de éxito.
     *
     * @return bool
     */
    public function delete_success_case( $post_id ) {
        $post = \get_post( $post_id );
        
        // Log para debug
        \error_log( "TEW Delete Case - Intentando eliminar caso: {$post_id}" );
        
        if ( ! $post || $post->post_type !== self::POST_TYPE ) {
            \error_log( "TEW Delete Case - Post no existe o tipo incorrecto: {$post_id}" );
            return false;
        }

        // Verificar que es un caso de éxito
        $is_success = \get_post_meta( $post_id, self::META_IS_SUCCESS_CASE, true );
        \error_log( "TEW Delete Case - Meta IS_SUCCESS_CASE para {$post_id}: " . var_export( $is_success, true ) );
        
        if ( ! $is_success ) {
            \error_log( "TEW Delete Case - No es un caso de éxito: {$post_id}" );
            return false;
        }

        // Limpiar todos los metadatos del caso de éxito
        \delete_post_meta( $post_id, self::META_IS_SUCCESS_CASE );
        \delete_post_meta( $post_id, self::META_BEFORE_REPORT_ID );
        \delete_post_meta( $post_id, self::META_AFTER_REPORT_ID );
        \delete_post_meta( $post_id, self::META_CLIENT_NAME );
        \delete_post_meta( $post_id, self::META_TESTIMONIAL );
        \delete_post_meta( $post_id, self::META_MIGRATION_DATE );
        \delete_post_meta( $post_id, self::META_IS_PUBLIC );

        \error_log( "TEW Delete Case - Caso eliminado correctamente: {$post_id}" );
        return true;
    }

    /**
     * Crea un informe manual desde cero (para clientes antiguos).
     *
     * @param array $data Datos del informe manual.
     *
     * @return int|WP_Error ID del post creado o error.
     */
    public function create_manual_report( $data ) {
        $defaults = [
            'url'              => '',
            'score'            => 0,
            'grade'            => 'F',
            'co2_per_view'     => 0,
            'is_green'         => false,
            'hosting_provider' => '',
        ];

        $data = array_merge( $defaults, $data );

        if ( empty( $data['url'] ) ) {
            return new WP_Error( 'tew_missing_url', __( 'Se requiere una URL para crear el informe.', 'test-eco-website' ) );
        }

        $domain = Utils::get_domain( $data['url'] );
        $title  = sprintf(
            __( 'Informe %s · Manual', 'test-eco-website' ),
            $domain
        );

        // Crear el post
        $post_id = wp_insert_post(
            [
                'post_type'   => self::POST_TYPE,
                'post_status' => 'publish',
                'post_title'  => $title,
                'post_name'   => sanitize_title( $domain . '-manual-' . time() ),
            ],
            true
        );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        // Crear payload básico
        $payload = [
            'url'      => $data['url'],
            'summary'  => [
                'overall_score' => (float) $data['score'],
                'grade'         => $data['grade'],
            ],
            'metrics'  => [
                'carbon'        => [
                    'co2_per_view' => (float) $data['co2_per_view'],
                ],
                'green_hosting' => [
                    'is_green'         => (bool) $data['is_green'],
                    'hosting_provider' => $data['hosting_provider'],
                ],
            ],
            'metadata' => [
                'generated_at' => current_time( 'mysql', true ),
                'manual'       => true,
            ],
        ];

        $encoded = wp_json_encode( $payload );
        if ( false === $encoded ) {
            wp_delete_post( $post_id, true );
            return new WP_Error( 'tew_encode_error', __( 'Error al crear el payload del informe.', 'test-eco-website' ) );
        }

        // Guardar metadata
        update_post_meta( $post_id, self::META_PAYLOAD, wp_slash( $encoded ) );
        update_post_meta( $post_id, self::META_URL, esc_url_raw( $data['url'] ) );
        update_post_meta( $post_id, self::META_SCORE, (float) $data['score'] );
        update_post_meta( $post_id, self::META_GRADE, $data['grade'] );
        update_post_meta( $post_id, self::META_CO2_PER_VIEW, (float) $data['co2_per_view'] );
        update_post_meta( $post_id, self::META_IS_GREEN, (bool) $data['is_green'] );
        update_post_meta( $post_id, self::META_HOSTING_PROVIDER, $data['hosting_provider'] );
        update_post_meta( $post_id, self::META_GENERATED, current_time( 'mysql', true ) );

        // Calcular potencial
        $potential = $this->calculate_potential( $payload );
        update_post_meta( $post_id, self::META_POTENTIAL, $potential );

        return $post_id;
    }
}
