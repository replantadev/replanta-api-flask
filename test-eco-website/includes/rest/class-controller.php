<?php
namespace TEW\REST;

use TEW\Reporting\Report_Runner;
use TEW\Reporting\Report_Storage;
use TEW\Settings;
use TEW\Utils;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use function __;
use function absint;
use function current_user_can;
use function is_wp_error;
use function json_decode;
use function register_rest_route;
use function rest_sanitize_boolean;
use function sanitize_text_field;
use function sanitize_key;
use function wp_remote_post;
use function wp_remote_retrieve_body;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Controller {

    const ROUTE_NAMESPACE = 'tew/v1';

    /** @var Settings */
    private $settings;

    /** @var Report_Runner */
    private $runner;

    /** @var Report_Storage */
    private $storage;

    public function __construct( Settings $settings, Report_Storage $storage ) {
        $this->settings = $settings;
        $this->storage  = $storage;
        $this->runner   = new Report_Runner( $settings, $storage );
    }

    public function register_routes() {
        register_rest_route(
            self::ROUTE_NAMESPACE,
            '/audit',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_audit' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'url' => [
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => function ( $value ) {
                            return ! empty( $value );
                        },
                    ],
                    'refresh' => [
                        'required' => false,
                        'type'     => 'boolean',
                    ],
                    'bypass_validation' => [
                        'required' => false,
                        'type'     => 'boolean',
                    ],
                    'cf_turnstile_response' => [
                        'required' => false,
                        'type'     => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]
        );

        register_rest_route(
            self::ROUTE_NAMESPACE,
            '/test-credentials',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_test' ],
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
                'args'                => [
                    'service' => [
                        'required' => true,
                        'type'     => 'string',
                    ],
                ],
            ]
        );

        register_rest_route(
            self::ROUTE_NAMESPACE,
            '/reports/(?P<id>\d+)',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'handle_report_get' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'id' => [
                        'required' => true,
                        'type'     => 'integer',
                    ],
                ],
            ]
        );

        register_rest_route(
            self::ROUTE_NAMESPACE,
            '/reports/history',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'handle_report_history' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'url' => [
                        'required' => true,
                        'type'     => 'string',
                    ],
                    'limit' => [
                        'required' => false,
                        'type'     => 'integer',
                        'default'  => 5,
                    ],
                ],
            ]
        );

        register_rest_route(
            self::ROUTE_NAMESPACE,
            '/save-email',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_save_email' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'report_id' => [
                        'required' => true,
                        'type'     => 'integer',
                    ],
                    'email' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_email',
                        'validate_callback' => function ( $value ) {
                            return \is_email( $value );
                        },
                    ],
                    'turnstile_token' => [
                        'required' => false,
                        'type'     => 'string',
                    ],
                    'from_campaign' => [
                        'required' => false,
                        'type'     => 'boolean',
                    ],
                ],
            ]
        );

        register_rest_route(
            self::ROUTE_NAMESPACE,
            '/cta-quick',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_cta_quick' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'url' => [
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => function ( $value ) {
                            return ! empty( $value );
                        },
                    ],
                    'cf_turnstile_response' => [
                        'required' => false,
                        'type'     => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]
        );
    }

    public function handle_audit( WP_REST_Request $request ) {
        $url     = $request->get_param( 'url' );
        $refresh = rest_sanitize_boolean( $request->get_param( 'refresh' ) );
        $bypass  = rest_sanitize_boolean( $request->get_param( 'bypass_validation' ) );
        $turnstile_response = $request->get_param( 'cf_turnstile_response' );
        $from_campaign = rest_sanitize_boolean( $request->get_param( 'from_campaign' ) );

        // Validar Turnstile SOLO si está configurado Y se envió un token Y NO viene de campaña
        if ( defined( 'CF_TURNSTILE_SECRET' ) && CF_TURNSTILE_SECRET && ! empty( $turnstile_response ) && ! $from_campaign ) {
            // Verificar el token
            $verify_result = $this->verify_turnstile( $turnstile_response );
            if ( ! $verify_result ) {
                return new WP_Error(
                    'turnstile_failed',
                    __( 'Verificación de seguridad fallida. Inténtalo de nuevo.', 'test-eco-website' ),
                    [ 'status' => 400 ]
                );
            }
        }

        $result = $this->runner->run( $url, $refresh, $bypass );

        if ( is_wp_error( $result ) ) {
            return $this->error_response( $result );
        }

        return new WP_REST_Response( $result, 200 );
    }

    public function handle_test( WP_REST_Request $request ) {
        $service = sanitize_key( $request->get_param( 'service' ) );
        $result  = $this->runner->test_service( $service );

        if ( is_wp_error( $result ) ) {
            return $this->error_response( $result );
        }

        return new WP_REST_Response( $result, 200 );
    }

    private function error_response( WP_Error $error ) {
        return new WP_REST_Response(
            [
                'code'    => $error->get_error_code(),
                'message' => $error->get_error_message(),
                'data'    => $error->get_error_data(),
            ],
            $error->get_error_data()['status'] ?? 500
        );
    }

    public function handle_report_get( WP_REST_Request $request ) {
        $report = $this->storage->find( (int) $request->get_param( 'id' ) );

        if ( is_wp_error( $report ) ) {
            return $this->error_response( $report );
        }

        return new WP_REST_Response( $report, 200 );
    }

    public function handle_report_history( WP_REST_Request $request ) {
        $url_raw = $request->get_param( 'url' );
        $limit   = absint( $request->get_param( 'limit' ) );
        $limit   = $limit > 0 ? $limit : 5;

        $normalized = Utils::normalize_url( $url_raw );
        if ( false === $normalized ) {
            return $this->error_response( new WP_Error( 'tew_invalid_url', __( 'La URL proporcionada no es válida.', 'test-eco-website' ), [ 'status' => 400 ] ) );
        }

        $items = $this->storage->recent_for_url( $normalized, $limit );

        return new WP_REST_Response(
            [
                'url'   => $normalized,
                'items' => $items,
            ],
            200
        );
    }

    public function handle_save_email( WP_REST_Request $request ) {
        $report_id = absint( $request->get_param( 'report_id' ) );
        $email = \sanitize_email( $request->get_param( 'email' ) );
        $turnstile_token = sanitize_text_field( $request->get_param( 'turnstile_token' ) );
        $from_campaign = rest_sanitize_boolean( $request->get_param( 'from_campaign' ) );

        // Verificar que el report existe
        $report = $this->storage->find( $report_id );
        if ( is_wp_error( $report ) ) {
            return $this->error_response( new WP_Error( 'tew_invalid_report', __( 'Informe no encontrado.', 'test-eco-website' ), [ 'status' => 404 ] ) );
        }

        // Validar Turnstile token SOLO si NO viene de campaña
        if ( ! $from_campaign ) {
            $turnstile_secret = '0x4AAAAAAAzyLmwJNBXjXCW0vGJ8Eqjiqih'; // Secret key
            $verify_response = \wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'body' => [
                    'secret'   => $turnstile_secret,
                    'response' => $turnstile_token,
                ],
            ] );

            if ( is_wp_error( $verify_response ) ) {
                return $this->error_response( new WP_Error( 'tew_turnstile_error', __( 'Error al verificar captcha.', 'test-eco-website' ), [ 'status' => 500 ] ) );
            }

            $verify_body = json_decode( wp_remote_retrieve_body( $verify_response ), true );
            if ( empty( $verify_body['success'] ) ) {
                return $this->error_response( new WP_Error( 'tew_turnstile_invalid', __( 'Verificación de captcha fallida.', 'test-eco-website' ), [ 'status' => 403 ] ) );
            }
        }

        // Guardar email en el report
        $updated = $this->storage->update_email( $report_id, $email );
        if ( ! $updated ) {
            return $this->error_response( new WP_Error( 'tew_email_save_error', __( 'Error al guardar el email.', 'test-eco-website' ), [ 'status' => 500 ] ) );
        }

        // Enviar lead a StaffKit si está instalado el connector
        if ( function_exists( 'staffkit_send_lead' ) ) {
            $domain = isset( $report['domain'] ) ? $report['domain'] : '';
            $score_grade = isset( $report['eco_snapshot_score_grade'] ) ? $report['eco_snapshot_score_grade'] : '';
            $co2_per_visit = isset( $report['co2_per_visit'] ) ? $report['co2_per_visit'] : null;
            
            staffkit_send_lead( [
                'email'       => $email,
                'website'     => $domain,
                'source'      => 'Eco Performance Audit - ' . \get_bloginfo( 'name' ),
                'source_url'  => \home_url( '/r/' . $domain ),
                'eco_score'   => $score_grade,
                'co2_visit'   => $co2_per_visit,
                'audit_data'  => [
                    'mobile_score'         => $report['mobile_score'] ?? null,
                    'desktop_score'        => $report['desktop_score'] ?? null,
                    'eco_snapshot_score'   => $report['eco_snapshot_score'] ?? null,
                    'page_weight'          => $report['page_weight'] ?? null,
                    'green_hosting'        => $report['is_green'] ?? null,
                    'carbon_rating'        => $report['carbon_rating'] ?? null,
                    'report_id'            => $report_id,
                ],
            ] );
        }

        return new WP_REST_Response(
            [
                'success' => true,
                'message' => __( '¡Gracias! Te enviaremos consejos para mejorar la sostenibilidad de tu sitio.', 'test-eco-website' ),
            ],
            200
        );
    }

    /**
     * Verifica el token de Cloudflare Turnstile.
     *
     * @param string $token Token de respuesta de Turnstile.
     * @return bool True si la verificación es exitosa.
     */
    private function verify_turnstile( $token ) {
        if ( ! defined( 'CF_TURNSTILE_SECRET' ) || ! CF_TURNSTILE_SECRET ) {
            return false;
        }

        $response = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'body' => [
                'secret'   => CF_TURNSTILE_SECRET,
                'response' => $token,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ],
            'timeout' => 10,
        ] );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        return ! empty( $body['success'] );
    }

    /**
     * Handler rápido para CTA: estima métricas usando solo histórico o estimaciones ligeras.
     * NO ejecuta auditoría completa de PageSpeed/Lighthouse.
     * 
     * Estrategia:
     * 1. Busca datos históricos (inmediato si existe)
     * 2. Si no hay histórico: Estima CO2 usando valores típicos (~2.5MB página promedio)
     * 3. Retorna en <1 segundo siempre
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_cta_quick( WP_REST_Request $request ) {
        $url     = $request->get_param( 'url' );
        $turnstile_response = $request->get_param( 'cf_turnstile_response' );

        // Validar Turnstile si está configurado
        if ( defined( 'CF_TURNSTILE_SECRET' ) && CF_TURNSTILE_SECRET && ! empty( $turnstile_response ) ) {
            $verify_result = $this->verify_turnstile( $turnstile_response );
            if ( ! $verify_result ) {
                return new WP_Error(
                    'turnstile_failed',
                    __( 'Verificación de seguridad fallida.', 'test-eco-website' ),
                    [ 'status' => 400 ]
                );
            }
        }

        // Normalizar URL
        $normalized = Utils::normalize_url( $url );
        if ( false === $normalized ) {
            return new WP_Error(
                'tew_invalid_url',
                __( 'La URL no es válida.', 'test-eco-website' ),
                [ 'status' => 400 ]
            );
        }

        // ESTRATEGIA 1: Buscar histórico (instantáneo)
        $history = $this->storage->recent_for_url( $normalized, 1 );
        
        if ( ! empty( $history ) && isset( $history[0] ) ) {
            $report = $history[0];
            
            // Extraer métricas del reporte histórico
            $co2_monthly = 0;
            $lcp_ms = 3000; // Default
            
            if ( isset( $report['data']['metrics']['websitecarbon']['co2_per_view'] ) ) {
                $co2_grams = (float) $report['data']['metrics']['websitecarbon']['co2_per_view'];
                $co2_monthly = $co2_grams * 10000 / 1000; // 10k visitas/mes → kg
            }
            
            if ( isset( $report['data']['metrics']['pagespeed']['lcp'] ) ) {
                $lcp_seconds = (float) $report['data']['metrics']['pagespeed']['lcp'];
                $lcp_ms = $lcp_seconds * 1000;
            }
            
            // ✅ VALIDACIÓN: Si el histórico tiene CO2=0, usar estimación en su lugar
            if ( $co2_monthly > 0 ) {
                return new WP_REST_Response( [
                    'co2_monthly'  => round( $co2_monthly, 2 ),
                    'co2_replanta' => round( $co2_monthly * 1.2, 2 ), // +20% compensación Replanta
                    'speed_ms'     => round( $lcp_ms ),
                    'trees_yearly' => max( 1, round( $co2_monthly * 12 / 21 ) ), // 21kg CO2/árbol/año
                    'source'       => 'historical',
                    'url'          => $normalized,
                ], 200 );
            }
            // Si CO2=0, continuar a la estimación (histórico incompleto)
        }

        // ESTRATEGIA 2: Estimación ligera (sin auditoría completa)
        // Usar valores promedio de la industria
        $avg_page_bytes = 2500000; // 2.5MB página web promedio (HTTP Archive 2024)
        $avg_green = false; // Asumir hosting no-verde por defecto
        
        // Calcular CO2 usando Website Carbon API (rápido: ~500ms)
        $carbon_client = new \TEW\API\Websitecarbon_Client();
        $carbon_result = $carbon_client->audit( $normalized, $avg_page_bytes, $avg_green );
        
        if ( is_wp_error( $carbon_result ) ) {
            // Si falla Website Carbon, usar estimación matemática directa
            // Fórmula SWD3: 0.81 kWh/GB * 442g CO2/kWh (grid mix global)
            $co2_per_view = ( $avg_page_bytes / 1073741824 ) * 0.81 * 442; // gramos CO2
            $co2_monthly = $co2_per_view * 10000 / 1000; // kg/mes (10k visitas)
        } else {
            $co2_per_view = $carbon_result['co2_per_view'] ?? 1.5; // fallback 1.5g
            $co2_monthly = $co2_per_view * 10000 / 1000;
        }
        
        // Estimar velocidad: página promedio carga en 2.5-3.5s (LCP)
        $estimated_lcp_ms = 3000; // 3s es valor conservador/típico
        
        return new WP_REST_Response( [
            'co2_monthly'  => round( $co2_monthly, 2 ),
            'co2_replanta' => round( $co2_monthly * 1.2, 2 ),
            'speed_ms'     => $estimated_lcp_ms,
            'trees_yearly' => max( 1, round( $co2_monthly * 12 / 21 ) ),
            'source'       => 'estimated',
            'url'          => $normalized,
        ], 200 );
    }
}
