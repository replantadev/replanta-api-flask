<?php
namespace TEW\Reporting;

use function __;
use function array_key_exists;
use function is_numeric;
use function max;
use function min;
use function round;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Scorecard_Builder {

    private const WEIGHTS = [
        'mobile_performance'  => 0.45,
        'desktop_performance' => 0.25,
        'carbon_intensity'    => 0.20,
        'green_hosting'       => 0.10,
    ];

    private const TREE_ABSORPTION_KG_PER_YEAR = 21.0;

    /**
     * Construye la tarjeta de puntuación propia combinando métricas clave.
     *
     * @param string $url
     * @param array  $metrics
     * @param array  $errors
     *
     * @return array
     */
    public function build( $url, array $metrics, array $errors = [] ) {
        $components    = [];
        $weight_sum    = 0.0;
        $weighted_sum  = 0.0;

        if ( isset( $metrics['pagespeed']['mobile']['score'] ) ) {
            $score  = (float) $metrics['pagespeed']['mobile']['score'];
            $weight = self::WEIGHTS['mobile_performance'];

            $components[] = [
                'key'            => 'mobile_performance',
                'label'          => __( 'Rendimiento móvil', 'test-eco-website' ),
                'score'          => round( $score, 1 ),
                'weight'         => $weight,
                'weight_percent' => $weight * 100,
                'status'         => $this->status_from_score( $score ),
                'description'    => __( 'Puntuación Lighthouse Performance en móvil.', 'test-eco-website' ),
                'meta'           => [
                    'lcp_ms'  => $metrics['pagespeed']['mobile']['lcp_ms'] ?? null,
                    'tbt_ms'  => $metrics['pagespeed']['mobile']['tbt_ms'] ?? null,
                    'inp_ms'  => $metrics['pagespeed']['mobile']['inp_ms'] ?? null,
                    'cls'     => $metrics['pagespeed']['mobile']['cls'] ?? null,
                    'ttfb_ms' => $metrics['pagespeed']['mobile']['ttfb_ms'] ?? null,
                ],
            ];

            $weighted_sum += $score * $weight;
            $weight_sum   += $weight;
        }

        if ( isset( $metrics['pagespeed']['desktop']['score'] ) ) {
            $score  = (float) $metrics['pagespeed']['desktop']['score'];
            $weight = self::WEIGHTS['desktop_performance'];

            $components[] = [
                'key'            => 'desktop_performance',
                'label'          => __( 'Rendimiento escritorio', 'test-eco-website' ),
                'score'          => round( $score, 1 ),
                'weight'         => $weight,
                'weight_percent' => $weight * 100,
                'status'         => $this->status_from_score( $score ),
                'description'    => __( 'Puntuación Lighthouse Performance en escritorio.', 'test-eco-website' ),
                'meta'           => [
                    'lcp_ms'  => $metrics['pagespeed']['desktop']['lcp_ms'] ?? null,
                    'tbt_ms'  => $metrics['pagespeed']['desktop']['tbt_ms'] ?? null,
                    'inp_ms'  => $metrics['pagespeed']['desktop']['inp_ms'] ?? null,
                    'cls'     => $metrics['pagespeed']['desktop']['cls'] ?? null,
                    'ttfb_ms' => $metrics['pagespeed']['desktop']['ttfb_ms'] ?? null,
                ],
            ];

            $weighted_sum += $score * $weight;
            $weight_sum   += $weight;
        }

        $carbon_metrics = $metrics['websitecarbon'] ?? [];
        $carbon_score   = $this->compute_carbon_score( $carbon_metrics );
        if ( null !== $carbon_score ) {
            $weight = self::WEIGHTS['carbon_intensity'];

            $components[] = [
                'key'            => 'carbon_intensity',
                'label'          => __( 'Huella de carbono', 'test-eco-website' ),
                'score'          => round( $carbon_score, 1 ),
                'weight'         => $weight,
                'weight_percent' => $weight * 100,
                'status'         => $this->status_from_carbon( $carbon_metrics ),
                'description'    => __( 'Comparativa global y gramos de CO₂ emitidos por visita.', 'test-eco-website' ),
                'meta'           => [
                    'co2_per_view' => $carbon_metrics['co2_per_view'] ?? null,
                    'cleaner_than' => $carbon_metrics['cleaner_than'] ?? null,
                    'rating'       => $carbon_metrics['rating'] ?? null,
                ],
            ];

            $weighted_sum += $carbon_score * $weight;
            $weight_sum   += $weight;
        }

        $green_metrics = $metrics['greenweb'] ?? [];
        if ( array_key_exists( 'is_green', $green_metrics ) ) {
            $weight    = self::WEIGHTS['green_hosting'];
            $is_green  = (bool) $green_metrics['is_green'];
            $score     = $is_green ? 100.0 : 35.0;
            $status    = $is_green ? 'excellent' : 'attention';
            $copy      = $is_green
                ? __( 'Proveedor verificado como verde según The Green Web Foundation.', 'test-eco-website' )
                : __( 'Proveedor sin certificación verde en The Green Web Foundation.', 'test-eco-website' );

            $components[] = [
                'key'            => 'green_hosting',
                'label'          => __( 'Energía del hosting', 'test-eco-website' ),
                'score'          => round( $score, 1 ),
                'weight'         => $weight,
                'weight_percent' => $weight * 100,
                'status'         => $status,
                'description'    => $copy,
                'meta'           => [
                    'provider' => $green_metrics['hosted_by'] ?? null,
                    'country'  => $green_metrics['country'] ?? null,
                    'checked'  => $green_metrics['checked_on'] ?? null,
                ],
            ];

            $weighted_sum += $score * $weight;
            $weight_sum   += $weight;
        }

        $global_score = $weight_sum > 0 ? round( $weighted_sum / $weight_sum, 1 ) : null;

        return [
            'global_score' => $global_score,
            'components'   => $components,
            'status'       => $global_score !== null ? $this->status_from_score( $global_score ) : 'unknown',
            'carbon'       => $this->build_carbon_highlights( $carbon_metrics ),
            'green'        => [
                'is_green' => $green_metrics['is_green'] ?? null,
                'provider' => $green_metrics['hosted_by'] ?? null,
            ],
        ];
    }

    private function compute_carbon_score( array $carbon_metrics ) {
        if ( isset( $carbon_metrics['cleaner_than'] ) && is_numeric( $carbon_metrics['cleaner_than'] ) ) {
            return (float) $carbon_metrics['cleaner_than'];
        }

        if ( isset( $carbon_metrics['co2_per_view'] ) && is_numeric( $carbon_metrics['co2_per_view'] ) ) {
            $co2 = max( 0.0, (float) $carbon_metrics['co2_per_view'] );
            if ( $co2 <= 0.1 ) {
                return 100.0;
            }

            // Map 0.2 g -> 90, 1.5 g -> 5 roughly.
            $normalized = 100 - ( ( $co2 - 0.2 ) / ( 1.5 - 0.2 ) * 95 );
            return round( max( 5, min( 100, $normalized ) ), 1 );
        }

        return null;
    }

    private function status_from_score( $score ) {
        if ( $score >= 85 ) {
            return 'excellent';
        }
        if ( $score >= 70 ) {
            return 'good';
        }
        if ( $score >= 55 ) {
            return 'attention';
        }

        return 'critical';
    }

    private function status_from_carbon( array $carbon_metrics ) {
        if ( isset( $carbon_metrics['co2_per_view'] ) && is_numeric( $carbon_metrics['co2_per_view'] ) ) {
            $co2 = (float) $carbon_metrics['co2_per_view'];
            if ( $co2 <= 0.5 ) {
                return 'excellent';
            }
            if ( $co2 <= 0.9 ) {
                return 'good';
            }
            if ( $co2 <= 1.4 ) {
                return 'attention';
            }

            return 'critical';
        }

        if ( isset( $carbon_metrics['cleaner_than'] ) && is_numeric( $carbon_metrics['cleaner_than'] ) ) {
            return $this->status_from_score( (float) $carbon_metrics['cleaner_than'] );
        }

        return 'unknown';
    }

    private function build_carbon_highlights( array $carbon_metrics ) {
        $co2_per_view = isset( $carbon_metrics['co2_per_view'] ) && is_numeric( $carbon_metrics['co2_per_view'] )
            ? round( (float) $carbon_metrics['co2_per_view'], 3 )
            : null;

        $per_1000_views_kg  = null;
        $per_10000_views_kg = null;
        $trees_for_10000    = null;

        if ( null !== $co2_per_view ) {
            $per_1000_views_kg  = round( $co2_per_view * 1000 / 1000, 2 );
            $per_10000_views_kg = round( $co2_per_view * 10000 / 1000, 2 );
            $trees_for_10000    = $this->trees_equivalent( $per_10000_views_kg );
        }

        return [
            'co2_per_view'            => $co2_per_view,
            'co2_per_1000_views_kg'   => $per_1000_views_kg,
            'co2_per_10000_views_kg'  => $per_10000_views_kg,
            'cleaner_than'            => isset( $carbon_metrics['cleaner_than'] ) && is_numeric( $carbon_metrics['cleaner_than'] ) ? (float) $carbon_metrics['cleaner_than'] : null,
            'rating'                  => $carbon_metrics['rating'] ?? null,
            'is_green_energy'         => $carbon_metrics['is_green'] ?? null,
            'trees_for_10000_views'   => $trees_for_10000,
        ];
    }

    private function trees_equivalent( $kg ) {
        if ( ! $kg || $kg <= 0 ) {
            return null;
        }

        return round( $kg / self::TREE_ABSORPTION_KG_PER_YEAR, 2 );
    }
}
