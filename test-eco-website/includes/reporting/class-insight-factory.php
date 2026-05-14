<?php
namespace TEW\Reporting;

use TEW\Utils;
use function __;
use function array_slice;
use function is_array;
use function max;
use function min;
use function number_format;
use function round;
use function sprintf;
use function ucfirst;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Insight_Factory {

	/**
	 * Construye el resumen ejecutivo con hallazgos, acciones y narrativas.
	 */
	public function build_summary( $url, array $metrics, array $errors = [], array $scorecard = null ) {
		$scorecard = $scorecard ?? ( isset( $metrics['scorecard'] ) && is_array( $metrics['scorecard'] ) ? $metrics['scorecard'] : null );

		$score = $this->compute_score( $metrics, $scorecard );
		$grade = $this->score_to_grade( $score );
		$findings = $this->generate_findings( $metrics, $errors, $scorecard );
		$actions  = $this->generate_actions( $metrics, $errors, $scorecard );
		$narratives = $this->build_narratives( $url, $metrics, $scorecard, $score, $grade, $errors );

		return [
			'url'                => $url,
			'score'              => $score,
			'overall_score'      => $score, // Alias para compatibilidad
			'grade'              => $grade,
			'key_findings'       => array_slice( $findings, 0, 3 ),
			'prioritized_actions'=> array_slice( $actions, 0, 5 ),
			'narratives'         => $narratives,
			'score_breakdown'    => $scorecard['components'] ?? [],
			'status'             => [
				'overall' => $scorecard['status'] ?? $this->status_from_score( $score ),
			],
		];
	}

	private function compute_score( array $metrics, array $scorecard = null ) {
		if ( $scorecard && isset( $scorecard['global_score'] ) && null !== $scorecard['global_score'] ) {
			return round( (float) $scorecard['global_score'], 1 );
		}

		$weights = [
			'pagespeed_mobile'  => 0.45,
			'pagespeed_desktop' => 0.25,
			'websitecarbon'     => 0.20,
			'greenweb'          => 0.10,
		];

		$score = 0;
		$total_weights = 0;

		if ( isset( $metrics['pagespeed']['mobile']['score'] ) ) {
			$score += $metrics['pagespeed']['mobile']['score'] * $weights['pagespeed_mobile'];
			$total_weights += $weights['pagespeed_mobile'];
		}

		if ( isset( $metrics['pagespeed']['desktop']['score'] ) ) {
			$score += $metrics['pagespeed']['desktop']['score'] * $weights['pagespeed_desktop'];
			$total_weights += $weights['pagespeed_desktop'];
		}

		if ( isset( $metrics['websitecarbon']['cleaner_than'] ) ) {
			$score += $metrics['websitecarbon']['cleaner_than'] * $weights['websitecarbon'];
			$total_weights += $weights['websitecarbon'];
		} elseif ( isset( $metrics['websitecarbon']['co2_per_view'] ) ) {
			$co2 = max( 0.0, (float) $metrics['websitecarbon']['co2_per_view'] );
			$normalized = 100 - ( ( $co2 - 0.2 ) * 80 );
			$score += max( 5, min( 100, $normalized ) ) * $weights['websitecarbon'];
			$total_weights += $weights['websitecarbon'];
		}

		if ( isset( $metrics['greenweb']['is_green'] ) ) {
			$score += ( $metrics['greenweb']['is_green'] ? 100 : 35 ) * $weights['greenweb'];
			$total_weights += $weights['greenweb'];
		}

		if ( $total_weights <= 0 ) {
			return 0;
		}

		return round( $score / $total_weights, 1 );
	}

	private function score_to_grade( $score ) {
		if ( $score >= 85 ) {
			return 'A';
		}
		if ( $score >= 70 ) {
			return 'B';
		}
		if ( $score >= 55 ) {
			return 'C';
		}
		if ( $score >= 40 ) {
			return 'D';
		}

		return 'E';
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

	private function generate_findings( array $metrics, array $errors, array $scorecard = null ) {
		$findings = [];

		if ( $scorecard && isset( $scorecard['global_score'] ) ) {
			$findings[] = sprintf(
				__( 'Score Eco Snapshot: %.1f / 100 (grado %s).', 'test-eco-website' ),
				$scorecard['global_score'],
				$this->score_to_grade( $scorecard['global_score'] )
			);
		}

		if ( isset( $metrics['pagespeed']['mobile']['lcp_ms'] ) ) {
			$lcp = Utils::ms_to_seconds( $metrics['pagespeed']['mobile']['lcp_ms'], 2 );
			if ( $lcp > 4 ) {
				$findings[] = sprintf( __( 'LCP móvil elevado (%.2f s). El contenido principal tarda demasiado en pintar.', 'test-eco-website' ), $lcp );
			} elseif ( $lcp > 2.5 ) {
				$findings[] = sprintf( __( 'LCP móvil aceptable (%.2f s) pero mejorable para ofrecer una experiencia rápida.', 'test-eco-website' ), $lcp );
			} else {
				$findings[] = sprintf( __( 'Excelente LCP móvil (%.2f s), refleja una carga inicial ágil.', 'test-eco-website' ), $lcp );
			}
		}

		if ( isset( $metrics['websitecarbon']['co2_per_view'] ) ) {
			$co2 = (float) $metrics['websitecarbon']['co2_per_view'];
			if ( $co2 > 1.0 ) {
				$findings[] = sprintf( __( 'Huella de carbono por visita alta (%.2f g CO₂). Optimizar recursos y hosting ayudará a reducirla.', 'test-eco-website' ), $co2 );
			} elseif ( $co2 > 0.5 ) {
				$findings[] = sprintf( __( 'Huella de carbono moderada (%.2f g CO₂). Hay margen para recortar emisiones.', 'test-eco-website' ), $co2 );
			} else {
				$findings[] = sprintf( __( 'Huella de carbono baja (%.2f g CO₂); el sitio es eficiente energéticamente.', 'test-eco-website' ), $co2 );
			}
		}

		if ( $scorecard && isset( $scorecard['green']['is_green'] ) ) {
			if ( $scorecard['green']['is_green'] ) {
				$findings[] = __( 'El proveedor de hosting figura como verde en The Green Web Foundation.', 'test-eco-website' );
			} else {
				$findings[] = __( 'El hosting no aparece verificado como verde; revisar proveedor o certificados energéticos.', 'test-eco-website' );
			}
		} elseif ( isset( $metrics['greenweb']['is_green'] ) ) {
			if ( $metrics['greenweb']['is_green'] ) {
				$findings[] = __( 'El proveedor de hosting figura como verde en The Green Web Foundation.', 'test-eco-website' );
			} else {
				$findings[] = __( 'El hosting no aparece verificado como verde; revisar proveedor o certificados energéticos.', 'test-eco-website' );
			}
		}

		foreach ( $errors as $service => $error ) {
			$findings[] = sprintf( __( '%1$s no respondió: %2$s', 'test-eco-website' ), ucfirst( $service ), $error );
		}

		return $findings;
	}

	private function generate_actions( array $metrics, array $errors, array $scorecard = null ) {
		$actions = [];

		if ( isset( $metrics['pagespeed']['mobile']['lcp_ms'] ) && $metrics['pagespeed']['mobile']['lcp_ms'] > 2500 ) {
			$actions[] = [
				'title'       => __( 'Optimizar hero y recursos críticos', 'test-eco-website' ),
				'impact'      => 'alto',
				'effort'      => 'medio',
				'description' => __( 'Reducir peso de imágenes hero, implementar lazy-loading nativo y servir fuentes con font-display:swap.', 'test-eco-website' ),
			];
		}

		if ( isset( $metrics['pagespeed']['mobile']['tbt_ms'] ) && $metrics['pagespeed']['mobile']['tbt_ms'] > 300 ) {
			$actions[] = [
				'title'       => __( 'Dividir bundles JavaScript y eliminar bloqueos', 'test-eco-website' ),
				'impact'      => 'alto',
				'effort'      => 'alto',
				'description' => __( 'Identificar scripts pesados, cargar en diferido e introducir code-splitting para reducir el tiempo de bloqueo total.', 'test-eco-website' ),
			];
		}

		if ( isset( $metrics['websitecarbon']['cleaner_than'] ) && $metrics['websitecarbon']['cleaner_than'] < 50 ) {
			$actions[] = [
				'title'       => __( 'Reducir recursos estáticos y caché eficiente', 'test-eco-website' ),
				'impact'      => 'medio',
				'effort'      => 'medio',
				'description' => __( 'Minificar CSS/JS, implementar HTTP/3 y CDN verde para mejorar la eficiencia energética.', 'test-eco-website' ),
			];
		} elseif ( isset( $metrics['websitecarbon']['co2_per_view'] ) && $metrics['websitecarbon']['co2_per_view'] > 0.9 ) {
			$actions[] = [
				'title'       => __( 'Auditar imágenes y vídeo', 'test-eco-website' ),
				'impact'      => 'medio',
				'effort'      => 'medio',
				'description' => __( 'Comprimir recursos multimedia, servir versiones adaptativas y aplicar streaming eficiente para bajar la huella de carbono.', 'test-eco-website' ),
			];
		}

		if ( $scorecard && isset( $scorecard['green']['is_green'] ) && ! $scorecard['green']['is_green'] ) {
			$actions[] = [
				'title'       => __( 'Migrar a hosting con energía renovable', 'test-eco-website' ),
				'impact'      => 'alto',
				'effort'      => 'alto',
				'description' => __( 'Evaluar proveedores con certificaciones verdes (Green Web Foundation) y planificar migración progresiva.', 'test-eco-website' ),
			];
		}

		if ( isset( $metrics['pagespeed']['mobile']['cls'] ) && $metrics['pagespeed']['mobile']['cls'] > 0.15 ) {
			$actions[] = [
				'title'       => __( 'Estabilizar layout y reservar espacios', 'test-eco-website' ),
				'impact'      => 'medio',
				'effort'      => 'bajo',
				'description' => __( 'Definir dimensiones para imágenes y componentes dinámicos para reducir el CLS.', 'test-eco-website' ),
			];
		}

		if ( empty( $actions ) ) {
			$actions[] = [
				'title'       => __( 'Mantener monitorización trimestral', 'test-eco-website' ),
				'impact'      => 'medio',
				'effort'      => 'bajo',
				'description' => __( 'Repetir la auditoría cada trimestre y establecer un backlog de mejoras incrementales.', 'test-eco-website' ),
			];
		}

		foreach ( $errors as $service => $error ) {
			$actions[] = [
				'title'       => sprintf( __( 'Revisar credenciales de %s', 'test-eco-website' ), ucfirst( $service ) ),
				'impact'      => 'alto',
				'effort'      => 'bajo',
				'description' => $error,
			];
		}

		return $actions;
	}

	private function build_narratives( $url, array $metrics, array $scorecard = null, $score = null, $grade = null, array $errors = [] ) {
		$technical_bullets = [];
		$emotional_bullets = [];

		if ( isset( $metrics['pagespeed']['mobile'] ) ) {
			$mobile = $metrics['pagespeed']['mobile'];
			$technical_bullets[] = sprintf(
				__( 'Móvil · %1$s/100 · LCP %2$s s · TBT %3$s ms · CLS %4$s.', 'test-eco-website' ),
				$this->format_decimal( $mobile['score'] ?? null, 0 ),
				$this->format_seconds( $mobile['lcp_ms'] ?? null ),
				$this->format_ms( $mobile['tbt_ms'] ?? $mobile['inp_ms'] ?? null ),
				$this->format_decimal( $mobile['cls'] ?? null, 2 )
			);
		}

		if ( isset( $metrics['pagespeed']['desktop'] ) ) {
			$desktop = $metrics['pagespeed']['desktop'];
			$technical_bullets[] = sprintf(
				__( 'Escritorio · %1$s/100 · LCP %2$s s · TBT %3$s ms · CLS %4$s.', 'test-eco-website' ),
				$this->format_decimal( $desktop['score'] ?? null, 0 ),
				$this->format_seconds( $desktop['lcp_ms'] ?? null ),
				$this->format_ms( $desktop['tbt_ms'] ?? $desktop['inp_ms'] ?? null ),
				$this->format_decimal( $desktop['cls'] ?? null, 2 )
			);
		}

		if ( $scorecard && ! empty( $scorecard['components'] ) ) {
			foreach ( $scorecard['components'] as $component ) {
				if ( 'carbon_intensity' === $component['key'] ) {
					continue;
				}
				$technical_bullets[] = sprintf(
					__( '%1$s · %2$s/100 (%3$s).', 'test-eco-website' ),
					$component['label'],
					$this->format_decimal( $component['score'] ?? null, 1 ),
					$this->humanize_status( $component['status'] ?? 'unknown' )
				);
			}
		}

		$carbon = $scorecard['carbon'] ?? [];
		if ( isset( $carbon['co2_per_view'] ) ) {
			$cleaner_clause = isset( $carbon['cleaner_than'] )
				? sprintf( __( 'Estás más limpio que el %s%% de sitios web analizados a nivel mundial', 'test-eco-website' ), $this->format_decimal( $carbon['cleaner_than'], 0 ) )
				: '';
			$emotional_bullets[] = sprintf( __( 'Emisión por visita: %1$s g CO₂ — %2$s', 'test-eco-website' ), $this->format_decimal( $carbon['co2_per_view'], 2 ), $cleaner_clause );
		}

		if ( isset( $carbon['co2_per_1000_views_kg'] ) ) {
			$emotional_bullets[] = sprintf( __( 'Impacto mensual estimado: Con 1.000 visitas generas %s kg CO₂ (equivalente a cargar 125 smartphones)', 'test-eco-website' ), $this->format_decimal( $carbon['co2_per_1000_views_kg'], 2 ) );
		}

		if ( isset( $carbon['co2_per_10000_views_kg'] ) ) {
			$trees = isset( $carbon['trees_for_10000_views'] ) ? $this->format_tree_equivalent( $carbon['trees_for_10000_views'] ) : null;
			if ( $trees ) {
				$emotional_bullets[] = sprintf( __( 'Impacto anual estimado: Con 10.000 visitas generas %1$s kg CO₂. Necesitarías %2$s árboles plantados durante un año completo para compensar esta huella', 'test-eco-website' ), $this->format_decimal( $carbon['co2_per_10000_views_kg'], 2 ), $trees );
			} else {
				$emotional_bullets[] = sprintf( __( 'Impacto anual estimado: Con 10.000 visitas generas %s kg CO₂ (equivalente a conducir 32 km en coche)', 'test-eco-website' ), $this->format_decimal( $carbon['co2_per_10000_views_kg'], 2 ) );
			}
		}

		if ( $scorecard && isset( $scorecard['green']['is_green'] ) ) {
			if ( $scorecard['green']['is_green'] ) {
				$emotional_bullets[] = __( 'Tu proveedor de hosting: Utiliza energía 100% renovable certificada. ¡Esto reduce tu huella hasta un 70%! Es una de las mejores decisiones para el planeta', 'test-eco-website' );
			} else {
				$emotional_bullets[] = __( 'Oportunidad de oro: Migrar a hosting verde es la acción más rápida para reducir tu huella digital. Cambia de proveedor y reduce hasta un 70% tus emisiones sin tocar una línea de código', 'test-eco-website' );
			}
		}

		$technical_summary = null !== $score
			? sprintf( __( 'Score técnico: %.1f/100 · grado %s.', 'test-eco-website' ), $score, $grade )
			: __( 'Score técnico pendiente: faltan datos de PageSpeed o Website Carbon.', 'test-eco-website' );

		$emotional_summary = isset( $carbon['co2_per_view'] )
			? sprintf( __( 'Tu sitio web tiene una huella de %s g CO₂ por cada visita. Cada clic, cada scroll, cada imagen que se carga consume energía en servidores, redes y dispositivos. La buena noticia: pequeños cambios técnicos pueden hacer una gran diferencia para el planeta.', 'test-eco-website' ), $this->format_decimal( $carbon['co2_per_view'], 2 ) )
			: __( 'Activa Website Carbon para conocer el impacto ambiental real de tu sitio web.', 'test-eco-website' );

		if ( isset( $carbon['cleaner_than'] ) ) {
			$percentage = $this->format_decimal( $carbon['cleaner_than'], 0 );
			if ( $percentage > 70 ) {
				$emotional_summary .= sprintf( __( ' Tu sitio está entre el %s%% más limpio del mundo. ¡Sigue así!', 'test-eco-website' ), $percentage );
			} elseif ( $percentage > 50 ) {
				$emotional_summary .= sprintf( __( ' Estás más limpio que el %s%% de sitios web. Vas por buen camino, pero hay espacio para mejorar.', 'test-eco-website' ), $percentage );
			} else {
				$emotional_summary .= sprintf( __( ' Solo el %s%% de sitios están por debajo tuyo. Hay oportunidades claras para reducir tu impacto.', 'test-eco-website' ), $percentage );
			}
		}

		return [
			'technical' => [
				'title'    => __( 'Informe técnico', 'test-eco-website' ),
				'summary'  => $technical_summary,
				'bullets'  => $technical_bullets,
			],
			'emotional' => [
				'title'    => __( 'Historia que conecta', 'test-eco-website' ),
				'summary'  => $emotional_summary,
				'bullets'  => $emotional_bullets,
			],
		];
	}

	private function humanize_status( $status ) {
		switch ( $status ) {
			case 'excellent':
				return __( 'excelente', 'test-eco-website' );
			case 'good':
				return __( 'sólido', 'test-eco-website' );
			case 'attention':
				return __( 'atención', 'test-eco-website' );
			case 'critical':
				return __( 'crítico', 'test-eco-website' );
		}

		return __( 'sin datos', 'test-eco-website' );
	}

	private function format_seconds( $milliseconds ) {
		$seconds = Utils::ms_to_seconds( $milliseconds, 2 );
		if ( null === $seconds ) {
			return '—';
		}

		return number_format( (float) $seconds, 2 );
	}

	private function format_ms( $value ) {
		if ( null === $value ) {
			return '—';
		}

		return number_format( (float) $value, 0 );
	}

	private function format_decimal( $value, $decimals = 1 ) {
		if ( null === $value ) {
			return '—';
		}

		return number_format( (float) $value, $decimals, '.', '' );
	}

	private function format_tree_equivalent( $trees ) {
		if ( null === $trees || $trees <= 0 ) {
			return null;
		}

		return number_format( (float) $trees, 2, '.', '' );
	}
}
