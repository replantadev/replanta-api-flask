<?php
namespace TEW\Reporting;

use TEW\API\Greenweb_Client;
use TEW\API\Pagespeed_Client;
use TEW\API\Websitecarbon_Client;
use TEW\Cache;
use TEW\Logger;
use TEW\Settings;
use TEW\Utils;
use WP_Error;
use function __;
use function current_time;
use function get_permalink;
use function is_wp_error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Report_Runner {

    /** @var Settings */
    private $settings;

    /** @var Cache */
    private $cache;

    /** @var Insight_Factory */
    private $insights;

    /** @var Report_Storage */
    private $storage;

    /** @var Scorecard_Builder */
    private $scorecard;

    /** @var array */
    private $clients = [];

    /**
     * @param Settings $settings
     */
    public function __construct( Settings $settings, Report_Storage $storage ) {
        $this->settings = $settings;
        $this->cache    = new Cache();
        $this->insights = new Insight_Factory();
        $this->storage  = $storage;
        $this->scorecard = new Scorecard_Builder();

        $options = $this->settings->all();

        Logger::set_enabled( ! empty( $options['enable_logging'] ) );

        $this->clients = [
            'pagespeed'     => new Pagespeed_Client( $options['pagespeed_api_key'] ),
            'websitecarbon' => new Websitecarbon_Client(),
            'greenweb'      => new Greenweb_Client(),
        ];
    }

    /**
     * Ejecuta la auditoría completa.
     *
     * @param string $url
     * @param bool   $force_refresh
     *
     * @return array|WP_Error
     */
    public function run( $url, $force_refresh = false, $bypass_validation = false ) {
        $normalized = Utils::normalize_url( $url );

        if ( false === $normalized ) {
            return new WP_Error( 'tew_invalid_url', __( 'La URL proporcionada no es válida.', 'test-eco-website' ), [ 'status' => 400 ] );
        }

        $options = $this->settings->all();

        if ( ! empty( $options['sandbox_mode'] ) ) {
            return $this->build_response( $normalized, $this->get_fixture_data( $normalized ), [], true );
        }

        // ✅ PRE-VALIDACIÓN: Verificar que el sitio sea accesible antes de llamar APIs (skip si bypass)
        if ( ! $bypass_validation ) {
            Logger::info( 'Iniciando pre-validación de URL', [ 'url' => $normalized ] );
            $validation = Utils::pre_validate_url( $normalized );
            
            if ( ! $validation['valid'] ) {
                Logger::error( 'Pre-validación fallida', [
                    'url'     => $normalized,
                    'error'   => $validation['error'],
                    'details' => $validation['details'],
                ] );
                
                // Si es error 403 y el dominio coincide con el sitio actual, continuar anyway
                $current_domain = parse_url( home_url(), PHP_URL_HOST );
                $target_domain = parse_url( $normalized, PHP_URL_HOST );
                
                if ( $validation['details']['status_code'] === 403 && $current_domain === $target_domain ) {
                    Logger::info( 'Continuando análisis del dominio propio a pesar del error 403', [
                        'url' => $normalized,
                        'reason' => 'Mismo dominio que el sitio actual'
                    ] );
                } else {
                    return new WP_Error(
                        'tew_url_not_accessible',
                        $validation['error'] . ' Puedes intentar analizando desde otro sitio o desactivando temporalmente tu firewall.',
                        [
                            'status'  => 400,
                            'details' => $validation['details'],
                        ]
                    );
                }
            }

            Logger::info( 'Pre-validación exitosa', [
                'url'           => $normalized,
                'response_time' => $validation['details']['response_time'] . 'ms',
                'status_code'   => $validation['details']['status_code'],
            ] );
        } else {
            Logger::info( 'Pre-validación saltada (bypass activado)', [ 'url' => $normalized ] );
        }

        if ( ! $force_refresh ) {
            $cached = $this->cache->get( $normalized );
            if ( $cached ) {
                $cached['metadata']['cached'] = true;
                $cached['metadata']['history'] = $this->storage->recent_for_url( $normalized, 5 );
                if ( ! empty( $cached['metadata']['report_id'] ) && empty( $cached['metadata']['share_url'] ) ) {
                    $cached['metadata']['share_url'] = get_permalink( (int) $cached['metadata']['report_id'] );
                }
                return $cached;
            }
        }

        $metrics = [];
        $errors  = [];

        $pagespeed_result = $this->clients['pagespeed']->audit( $normalized );
        if ( is_wp_error( $pagespeed_result ) ) {
            $errors['pagespeed'] = $pagespeed_result->get_error_message();

            $error_data = $pagespeed_result->get_error_data();
            if ( isset( $error_data['body'] ) ) {
                $error_data['body'] = mb_substr( $error_data['body'], 0, 800 );
            }

            Logger::error(
                'Fallo al obtener métricas',
                array_filter(
                    [
                        'service' => 'pagespeed',
                        'message' => $errors['pagespeed'],
                        'status'  => $error_data['status'] ?? null,
                        'url'     => $error_data['url'] ?? null,
                        'body'    => $error_data['body'] ?? null,
                    ],
                    static function ( $value ) {
                        return null !== $value && '' !== $value;
                    }
                )
            );
            $pagespeed_bytes = null;
        } else {
            $metrics['pagespeed'] = $pagespeed_result;
            $pagespeed_bytes      = $this->derive_bytes_from_pagespeed( $pagespeed_result );
            Logger::info( 'Métricas obtenidas', [ 'service' => 'pagespeed' ] );
        }

        $greenweb_result = $this->clients['greenweb']->audit( $normalized );
        if ( is_wp_error( $greenweb_result ) ) {
            $errors['greenweb'] = $greenweb_result->get_error_message();
            Logger::error( 'Fallo al obtener métricas', [ 'service' => 'greenweb', 'message' => $errors['greenweb'] ] );
            $is_green = false;
        } else {
            $metrics['greenweb'] = $greenweb_result;
            $is_green            = ! empty( $greenweb_result['is_green'] );
            Logger::info( 'Métricas obtenidas', [ 'service' => 'greenweb' ] );
        }

        if ( null === $pagespeed_bytes ) {
            $errors['websitecarbon'] = __( 'Website Carbon necesita el peso de la página, pero no se pudo calcular a partir de PageSpeed.', 'test-eco-website' );
            Logger::error( 'Fallo al obtener métricas', [ 'service' => 'websitecarbon', 'message' => $errors['websitecarbon'] ] );
        } else {
            $wc_result = $this->clients['websitecarbon']->audit( $normalized, $pagespeed_bytes, $is_green );
            if ( is_wp_error( $wc_result ) ) {
                $errors['websitecarbon'] = $wc_result->get_error_message();
                Logger::error( 'Fallo al obtener métricas', [ 'service' => 'websitecarbon', 'message' => $errors['websitecarbon'] ] );
            } else {
                $metrics['websitecarbon'] = $wc_result;
                Logger::info( 'Métricas obtenidas', [ 'service' => 'websitecarbon' ] );
            }
        }

    $response = $this->build_response( $normalized, $metrics, $errors, false );

        $response = $this->attach_share_metadata( $normalized, $response );

        if ( empty( $errors ) ) {
            $this->cache->set( $normalized, $response, $options['auto_refresh_hours'] );
        }

        return $response;
    }

    /**
     * Permite probar un servicio de forma aislada.
     *
     * @param string $service
     *
     * @return array|WP_Error
     */
    public function test_service( $service ) {
        if ( ! isset( $this->clients[ $service ] ) ) {
            return new WP_Error( 'tew_unknown_service', __( 'Servicio desconocido.', 'test-eco-website' ), [ 'status' => 400 ] );
        }

        $options = $this->settings->all();
        if ( ! empty( $options['sandbox_mode'] ) ) {
            return [
                'service' => $service,
                'message' => __( 'Sandbox activo: usando datos simulados.', 'test-eco-website' ),
                'payload' => $this->get_fixture_data( 'https://example.org' )[ $service ] ?? [],
            ];
        }

        $test_url = 'https://www.wikipedia.org';

        if ( 'websitecarbon' === $service ) {
            $pagespeed = $this->clients['pagespeed']->audit( $test_url );
            if ( is_wp_error( $pagespeed ) ) {
                return $pagespeed;
            }

            $bytes = $this->derive_bytes_from_pagespeed( $pagespeed );
            if ( null === $bytes ) {
                return new WP_Error( 'tew_wc_missing_bytes', __( 'No se pudo obtener el peso de la página para Website Carbon.', 'test-eco-website' ), [ 'status' => 400 ] );
            }

            $green_result = $this->clients['greenweb']->audit( $test_url );
            $is_green     = ! is_wp_error( $green_result ) && ! empty( $green_result['is_green'] );

            $result = $this->clients['websitecarbon']->audit( $test_url, $bytes, $is_green );
        } else {
            $result = $this->clients[ $service ]->audit( $test_url );
        }

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return [
            'service' => $service,
            'message' => __( 'Conexión verificada correctamente.', 'test-eco-website' ),
            'payload' => $result,
        ];
    }

    private function build_response( $url, array $metrics, array $errors, $sandbox ) {
        $scorecard = $metrics['scorecard'] ?? null;
        if ( null === $scorecard ) {
            $scorecard           = $this->scorecard->build( $url, $metrics, $errors );
            $metrics['scorecard'] = $scorecard;
        }

        $summary = $this->insights->build_summary( $url, $metrics, $errors, $scorecard );

        return [
            'url'      => $url,
            'summary'  => $summary,
            'metrics'  => $metrics,
            'errors'   => $errors,
            'snapshots'=> $this->build_snapshots( $url, $metrics ),
            'metadata' => [
                'generated_at' => current_time( 'mysql', true ),
                'cached'       => false,
                'sandbox'      => $sandbox,
            ],
        ];
    }

    private function build_snapshots( $url, array $metrics ) {
        $snapshots = [];

        if ( isset( $metrics['pagespeed']['mobile']['screenshot'] ) ) {
            $snapshots[] = [
                'service' => 'pagespeed_mobile',
                'label'   => __( 'Captura móvil PageSpeed', 'test-eco-website' ),
                'image'   => $metrics['pagespeed']['mobile']['screenshot'],
                'url'     => $metrics['pagespeed']['mobile']['report_url'] ?? null,
            ];
        }

        if ( isset( $metrics['pagespeed']['desktop']['screenshot'] ) ) {
            $snapshots[] = [
                'service' => 'pagespeed_desktop',
                'label'   => __( 'Captura desktop PageSpeed', 'test-eco-website' ),
                'image'   => $metrics['pagespeed']['desktop']['screenshot'],
                'url'     => $metrics['pagespeed']['desktop']['report_url'] ?? null,
            ];
        }

        $snapshots[] = [
            'service' => 'websitecarbon',
            'label'   => __( 'Ver cálculo Website Carbon', 'test-eco-website' ),
            'url'     => isset( $metrics['websitecarbon']['report_url'] ) ? $metrics['websitecarbon']['report_url'] : 'https://www.websitecarbon.com/',
            'image'   => null,
        ];

        $snapshots[] = [
            'service' => 'greenweb',
            'label'   => __( 'Comprobar en Green Web Foundation', 'test-eco-website' ),
            'url'     => 'https://www.thegreenwebfoundation.org/green-web-check/?url=' . urlencode( $url ),
            'image'   => null,
        ];

        return $snapshots;
    }

    private function attach_share_metadata( $url, array $response ) {
        $record = $this->storage->save( $response );

        if ( is_wp_error( $record ) ) {
            Logger::error( 'No se pudo guardar el informe', [
                'service' => 'storage',
                'message' => $record->get_error_message(),
            ] );

            $response['metadata']['history'] = $this->storage->recent_for_url( $url );
            return $response;
        }

        $response['metadata']['report_id'] = $record['id'];
        $response['metadata']['share_url'] = $record['permalink'];
        $response['metadata']['history']   = $this->storage->recent_for_url( $url, 5 );

        return $response;
    }

    private function derive_bytes_from_pagespeed( array $pagespeed_metrics ) {
        $candidates = [
            $pagespeed_metrics['mobile']['total_byte_weight'] ?? null,
            $pagespeed_metrics['desktop']['total_byte_weight'] ?? null,
        ];

        foreach ( $candidates as $value ) {
            if ( is_numeric( $value ) && $value > 0 ) {
                return (int) round( $value );
            }
        }

        return null;
    }

    private function get_fixture_data( $url ) {
        return [
            'pagespeed' => [
                'mobile'  => [
                    'score'        => 78,
                    'lcp_ms'       => 3200,
                    'tbt_ms'       => 180,
                    'inp_ms'       => 210,
                    'cls'          => 0.08,
                    'total_byte_weight' => 2400000,
                    'report_url'   => 'https://pagespeed.web.dev/analysis?url=' . rawurlencode( $url ) . '&hl=es',
                    'screenshot'   => null,
                ],
                'desktop' => [
                    'score'      => 92,
                    'lcp_ms'     => 1800,
                    'tbt_ms'     => 90,
                    'inp_ms'     => 60,
                    'cls'        => 0.04,
                    'total_byte_weight' => 2100000,
                    'report_url' => 'https://pagespeed.web.dev/analysis?url=' . rawurlencode( $url ) . '&hl=es&form_factor=desktop',
                    'screenshot' => null,
                ],
            ],
            'websitecarbon' => [
                'cleaner_than'      => 54,
                'co2_per_view'      => 0.72,
                'co2_renewable'     => 0.45,
                'is_green'          => false,
                'bytes_transferred' => 2400000,
                'report_url'        => 'https://www.websitecarbon.com/site/' . urlencode( $url ),
                'rating'            => 'C',
                'inputs'            => [
                    'bytes' => 2400000,
                    'green' => 0,
                ],
            ],
            'greenweb' => [
                'domain'    => Utils::get_domain( $url ),
                'is_green'  => false,
                'hosted_by' => 'Generic Host',
                'country'   => 'ES',
                'checked_on'=> current_time( 'mysql' ),
            ],
        ];
    }
}
