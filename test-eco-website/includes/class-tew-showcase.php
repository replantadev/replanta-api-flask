<?php
namespace TEW;

use TEW\Reporting\Report_Storage;
use function add_action;
use function add_shortcode;
use function esc_attr;
use function esc_html;
use function esc_html_e;
use function esc_url;
use function get_permalink;
use function number_format_i18n;
use function wp_date;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_register_script;
use function wp_register_style;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Showcase {

    const TAG = 'tew_showcase';

    /**
     * @var Report_Storage
     */
    private $storage;

    /**
     * @param Report_Storage $storage
     */
    public function __construct( Report_Storage $storage ) {
        $this->storage = $storage;

        add_shortcode( self::TAG,           [ $this, 'render' ] );
        add_shortcode( 'tew_cases_slider',     [ $this, 'render_slider' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
    }

    /**
     * Registra estilos y scripts del showcase.
     */
    public function register_assets() {
        // Phosphor Icons: registrar fallback si el tema no lo proporciona
        if ( ! \wp_style_is( 'phosphor-icons', 'registered' ) ) {
            \wp_register_style( 'phosphor-icons', 'https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css', [], null );
        }

        $css_deps = [ 'phosphor-icons' ];
        if ( \wp_style_is( 'replanta-kit', 'registered' ) ) {
            $css_deps[] = 'replanta-kit';
        }

        wp_register_style( 'tew-showcase', TEW_PLUGIN_URL . 'assets/css/frontend-showcase.css', $css_deps, TEW_VERSION );
        wp_register_script( 'tew-showcase', TEW_PLUGIN_URL . 'assets/js/frontend-showcase.js', [], TEW_VERSION, true );
    }

    /**
     * Renderiza el showcase de informes.
     *
     * @param array  $atts    Atributos del shortcode.
     * @param string $content Contenido del shortcode.
     *
     * @return string
     */
    public function render( $atts = [], $content = null ) {
        wp_enqueue_style( 'tew-showcase' );
        wp_enqueue_script( 'tew-showcase' );

        $atts = shortcode_atts(
            [
                'type'  => 'all', // all, success, recent
                'limit' => 12,
            ],
            $atts,
            self::TAG
        );

        $type  = sanitize_key( $atts['type'] );
        $limit = absint( $atts['limit'] );

        ob_start();
        ?>
        <div class="tew-showcase" data-tew-showcase data-type="<?php echo esc_attr( $type ); ?>" data-view="grid">
            <div class="tew-showcase__header">
                <h1 class="tew-showcase__title">
                    <?php esc_html_e( 'Algunos casos de éxito', 'test-eco-website' ); ?>
                </h1>
                <p class="tew-showcase__subtitle">
                    <?php esc_html_e( 'Rendimiento medible. Sostenibilidad real. Hosting que funciona. Así ayudamos a nuestros clientes a reducir su huella digital mejorando su velocidad de carga.', 'test-eco-website' ); ?>
                </p>
                
                <!-- Toggle vista mosaico/lista -->
                <div class="tew-view-toggle">
                    <button class="tew-view-btn is-active" data-view="grid" aria-label="Vista mosaico">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <rect x="3" y="3" width="7" height="7" rx="1"/>
                            <rect x="14" y="3" width="7" height="7" rx="1"/>
                            <rect x="3" y="14" width="7" height="7" rx="1"/>
                            <rect x="14" y="14" width="7" height="7" rx="1"/>
                        </svg>
                    </button>
                    <button class="tew-view-btn" data-view="list" aria-label="Vista lista">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <line x1="4" y1="6" x2="20" y2="6"/>
                            <line x1="4" y1="12" x2="20" y2="12"/>
                            <line x1="4" y1="18" x2="20" y2="18"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="tew-showcase__grid">
                <?php echo $this->render_items( $type, $limit ); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza los items del showcase según el tipo.
     *
     * @param string $type  Tipo de items a mostrar.
     * @param int    $limit Límite de resultados.
     *
     * @return string
     */
    private function render_items( $type, $limit ) {
        $html = '';

        if ( $type === 'success' || $type === 'all' ) {
            $success_cases = $this->storage->get_success_cases( $limit );
            foreach ( $success_cases as $case ) {
                $html .= $this->render_success_card( $case );
            }
        }

        if ( $type === 'recent' || $type === 'all' ) {
            $regular_limit = $type === 'all' ? ( $limit - count( $success_cases ?? [] ) ) : $limit;
            $regular_reports = $this->storage->get_public_reports( [ 'limit' => $regular_limit ] );
            foreach ( $regular_reports as $report ) {
                $html .= $this->render_regular_card( $report );
            }
        }

        if ( empty( $html ) ) {
            $html = '<p class="tew-showcase__empty">' . esc_html__( 'No hay informes disponibles en este momento.', 'test-eco-website' ) . '</p>';
        }

        return $html;
    }

    /**
     * Renderiza una card de caso de éxito (estilo Replanta minimalista).
     *
     * @param array $case Datos del caso de éxito.
     *
     * @return string
     */
    private function render_success_card( $case ) {
        $before = $case['before'];
        $after  = $case['after'];
        $improvements = $case['improvements'];
        $client_name = $case['client_name'];
        $testimonial = $case['testimonial'];

        $before_score = $improvements['score']['before'];
        $after_score  = $improvements['score']['after'];
        $score_diff   = $improvements['score']['diff'];
        $score_percent = abs( $improvements['score']['percent'] );

        $before_co2 = $improvements['co2']['before'];
        $after_co2  = $improvements['co2']['after'];
        $co2_diff = abs( $before_co2 - $after_co2 );
        $co2_percent = abs( $improvements['co2']['percent'] );

        $green_improved = $improvements['green_hosting']['improved'];

        $after_url = isset( $after['metadata']['report_url'] ) ? esc_url( $after['metadata']['report_url'] ) : '#';
        $site_url  = isset( $after['url'] ) ? esc_url( $after['url'] ) : '';
        $domain    = Utils::get_domain( $site_url );

        ob_start();
        ?>
        <article class="tew-case-card" data-type="success">
            <div class="tew-case-card__header">
                <?php if ( ! empty( $client_name ) ) : ?>
                    <h3 class="tew-case-card__client"><?php echo esc_html( $client_name ); ?></h3>
                <?php endif; ?>
                <div class="tew-case-card__domain"><?php echo esc_html( $domain ); ?></div>
                <?php if ( $green_improved ) : ?>
                    <span class="tew-badge tew-badge--eco">
                        <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/><path d="M10.97 4.97a.235.235 0 0 0-.02.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05z"/></svg>
                        Hosting Verde
                    </span>
                <?php endif; ?>
            </div>

            <div class="tew-case-card__metrics">
                <div class="tew-metric">
                    <div class="tew-metric__label">Score</div>
                    <div class="tew-metric__values">
                        <span class="tew-metric__before"><?php echo number_format_i18n( $before_score, 0 ); ?></span>
                        <svg class="tew-metric__arrow" width="16" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M1 6h14m-4-4l4 4-4 4"/></svg>
                        <span class="tew-metric__after"><?php echo number_format_i18n( $after_score, 0 ); ?></span>
                    </div>
                    <?php if ( $score_diff > 0 ) : ?>
                        <div class="tew-metric__delta tew-metric__delta--positive">
                            +<?php echo number_format_i18n( $score_diff, 0 ); ?> pts
                        </div>
                    <?php endif; ?>
                </div>

                <div class="tew-metric">
                    <div class="tew-metric__label">CO₂ / visita</div>
                    <div class="tew-metric__values">
                        <span class="tew-metric__before"><?php echo number_format_i18n( $before_co2, 2 ); ?>g</span>
                        <svg class="tew-metric__arrow" width="16" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M1 6h14m-4-4l4 4-4 4"/></svg>
                        <span class="tew-metric__after"><?php echo number_format_i18n( $after_co2, 2 ); ?>g</span>
                    </div>
                    <?php if ( $co2_diff > 0 ) : ?>
                        <div class="tew-metric__delta tew-metric__delta--negative">
                            -<?php echo number_format_i18n( $co2_diff, 2 ); ?>g (-<?php echo number_format_i18n( $co2_percent, 0 ); ?>%)
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ( isset( $improvements['ttfb']['before'] ) && $improvements['ttfb']['before'] > 0 ) : ?>
                    <?php 
                        $before_ttfb = $improvements['ttfb']['before'];
                        $after_ttfb  = $improvements['ttfb']['after'];
                        $ttfb_diff   = abs( $improvements['ttfb']['diff'] );
                        $ttfb_percent = abs( $improvements['ttfb']['percent'] );
                    ?>
                    <div class="tew-metric">
                        <div class="tew-metric__label">TTFB</div>
                        <div class="tew-metric__values">
                            <span class="tew-metric__before"><?php echo number_format_i18n( $before_ttfb, 0 ); ?>ms</span>
                            <svg class="tew-metric__arrow" width="16" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M1 6h14m-4-4l4 4-4 4"/></svg>
                            <span class="tew-metric__after"><?php echo number_format_i18n( $after_ttfb, 0 ); ?>ms</span>
                        </div>
                        <?php if ( $improvements['ttfb']['diff'] < 0 ) : ?>
                            <div class="tew-metric__delta tew-metric__delta--positive">
                                -<?php echo number_format_i18n( $ttfb_diff, 0 ); ?>ms (-<?php echo number_format_i18n( $ttfb_percent, 0 ); ?>%)
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ( ! empty( $testimonial ) ) : ?>
                <blockquote class="tew-case-card__quote">
                    "<?php echo esc_html( $testimonial ); ?>"
                </blockquote>
            <?php endif; ?>

            <a href="<?php echo $after_url; ?>" class="tew-case-card__link">
                Ver análisis completo
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 1l6 6-6 6"/></svg>
            </a>
        </article>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza una card de informe regular.
     *
     * @param array $report Datos del informe.
     *
     * @return string
     */
    private function render_regular_card( $report ) {
        $score = isset( $report['summary']['overall_score'] ) ? (float) $report['summary']['overall_score'] : 0;
        $grade = isset( $report['summary']['grade'] ) ? $report['summary']['grade'] : 'N/A';
        $co2   = isset( $report['metrics']['carbon']['co2_per_view'] ) ? (float) $report['metrics']['carbon']['co2_per_view'] : 0;
        $is_green = isset( $report['metrics']['green_hosting']['is_green'] ) ? (bool) $report['metrics']['green_hosting']['is_green'] : false;

        // Extraer TTFB del móvil (prioritario) o escritorio
        $ttfb_ms = 0;
        if ( isset( $report['metrics']['performance']['mobile']['ttfb_ms'] ) ) {
            $ttfb_ms = (float) $report['metrics']['performance']['mobile']['ttfb_ms'];
        } elseif ( isset( $report['metrics']['performance']['desktop']['ttfb_ms'] ) ) {
            $ttfb_ms = (float) $report['metrics']['performance']['desktop']['ttfb_ms'];
        } elseif ( isset( $report['scorecard']['components'] ) ) {
            // Buscar en scorecard components
            foreach ( $report['scorecard']['components'] as $component ) {
                if ( isset( $component['meta']['ttfb_ms'] ) ) {
                    $ttfb_ms = (float) $component['meta']['ttfb_ms'];
                    break;
                }
            }
        }

        $report_url = isset( $report['metadata']['report_url'] ) ? esc_url( $report['metadata']['report_url'] ) : '#';
        $site_url   = isset( $report['url'] ) ? esc_url( $report['url'] ) : '';
        $domain     = Utils::get_domain( $site_url );
        $date       = isset( $report['metadata']['generated_at'] ) ? $report['metadata']['generated_at'] : '';
        $timestamp  = strtotime( $date );

        $grade_class = $this->get_grade_class( $grade );

        ob_start();
        ?>
        <div class="tew-showcase-card tew-showcase-card--regular" data-type="regular">
            <div class="tew-card__header">
                <h3 class="tew-card__domain"><?php echo esc_html( $domain ); ?></h3>
                <?php if ( $timestamp ) : ?>
                    <p class="tew-card__date">
                        <?php echo wp_date( get_option( 'date_format' ), $timestamp ); ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="tew-card__score">
                <div class="tew-score-circle">
                    <svg viewBox="0 0 36 36" class="tew-circular-chart">
                        <path class="tew-circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        <path class="tew-circle <?php echo esc_attr( $grade_class ); ?>" stroke-dasharray="<?php echo esc_attr( $score ); ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    </svg>
                    <div class="tew-score-text">
                        <span class="tew-score-value"><?php echo number_format_i18n( $score, 0 ); ?></span>
                        <span class="tew-score-grade"><?php echo esc_html( $grade ); ?></span>
                    </div>
                </div>
            </div>

            <div class="tew-card__metrics">
                <div class="tew-metric">
                    <span class="tew-metric__value"><?php echo number_format_i18n( $co2, 2 ); ?>g</span>
                    <span class="tew-metric__label"><?php esc_html_e( 'CO₂/visita', 'test-eco-website' ); ?></span>
                </div>
                <?php if ( $ttfb_ms > 0 ) : ?>
                    <div class="tew-metric">
                        <span class="tew-metric__value"><?php echo number_format_i18n( $ttfb_ms, 0 ); ?>ms</span>
                        <span class="tew-metric__label">TTFB</span>
                    </div>
                <?php endif; ?>
                <div class="tew-metric">
                    <?php if ( $is_green ) : ?>
                        <span class="tew-metric__value tew-metric__value--green"><?php esc_html_e( 'Hosting Verde', 'test-eco-website' ); ?></span>
                    <?php else : ?>
                        <span class="tew-metric__value tew-metric__value--gray"><?php esc_html_e( 'Hosting Convencional', 'test-eco-website' ); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="tew-card__actions">
                <a href="<?php echo $report_url; ?>" class="tew-card__button tew-card__button--ghost">
                    <?php esc_html_e( 'Ver informe', 'test-eco-website' ); ?>
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * [tew_cases_slider limit="3"]
     *
     * Strip horizontal y compacto de casos de éxito. Pensado para la home
     * (Elementor HTML widget o Shortcode widget), no para la página de showcase.
     * Muestra LCP o Score antes/después con delta %, dominio y enlace al informe.
     *
     * @param array $atts  limit (int, default 3)
     * @return string
     */
    public function render_slider( $atts = [] ) {
        $atts  = shortcode_atts( [ 'limit' => 3 ], $atts, 'tew_cases_slider' );
        $limit = max( 1, absint( $atts['limit'] ) );

        $cases = $this->storage->get_success_cases( $limit );
        if ( empty( $cases ) ) {
            return '';
        }

        // Phosphor Icons needed for the badge icon.
        wp_enqueue_style( 'phosphor-icons' );

        $cards = '';
        foreach ( $cases as $case ) {
            $imp         = $case['improvements'];
            $client_name = sanitize_text_field( $case['client_name'] );
            $site_url    = isset( $case['after']['url'] ) ? esc_url( $case['after']['url'] ) : '';
            $domain      = $site_url ? Utils::get_domain( $site_url ) : $client_name;
            $permalink   = isset( $case['after']['metadata']['report_url'] )
                ? esc_url( $case['after']['metadata']['report_url'] )
                : get_permalink( $case['after_id'] );

            if ( ! $client_name ) {
                $client_name = $domain;
            }

            // ── Choose primary metric: LCP > TTFB > Score ──────────────────
            $metric = '';
            $before_val = null;
            $after_val  = null;
            $unit       = '';
            $delta      = '';
            $delta_class = '';

            if ( ! empty( $imp['lcp']['before'] ) && ! empty( $imp['lcp']['after'] ) ) {
                $metric     = 'LCP';
                $before_val = (float) $imp['lcp']['before'] / 1000; // ms → s
                $after_val  = (float) $imp['lcp']['after']  / 1000;
                $unit       = 's';
                $pct        = $before_val > 0 ? ( $after_val - $before_val ) / $before_val * 100 : 0;
                if ( $pct < 0 ) {
                    $delta       = '−' . round( abs( $pct ) ) . '%';
                    $delta_class = 'tew-mini-delta--good';
                }
            } elseif ( ! empty( $imp['ttfb']['before'] ) && ! empty( $imp['ttfb']['after'] ) ) {
                $metric     = 'TTFB';
                $before_val = (float) $imp['ttfb']['before']; // ms
                $after_val  = (float) $imp['ttfb']['after'];
                $unit       = 'ms';
                $pct        = $before_val > 0 ? ( $after_val - $before_val ) / $before_val * 100 : 0;
                if ( $pct < 0 ) {
                    $delta       = '−' . round( abs( $pct ) ) . '%';
                    $delta_class = 'tew-mini-delta--good';
                }
            } elseif ( ! empty( $imp['score']['before'] ) && ! empty( $imp['score']['after'] ) ) {
                $metric     = 'Score';
                $before_val = (float) $imp['score']['before'];
                $after_val  = (float) $imp['score']['after'];
                $unit       = '/100';
                $pct        = $before_val > 0 ? ( $after_val - $before_val ) / $before_val * 100 : 0;
                if ( $pct > 0 ) {
                    $delta       = '+' . round( $pct ) . '%';
                    $delta_class = 'tew-mini-delta--good';
                }
            }

            $fmt_before = ( $before_val !== null )
                ? ( $unit === '/100' ? number_format_i18n( $before_val, 0 ) . $unit
                                     : number_format_i18n( $before_val, $unit === 'ms' ? 0 : 1 ) . $unit )
                : null;
            $fmt_after  = ( $after_val !== null )
                ? ( $unit === '/100' ? number_format_i18n( $after_val, 0 ) . $unit
                                     : number_format_i18n( $after_val,  $unit === 'ms' ? 0 : 1 ) . $unit )
                : null;

            // ── Card HTML ───────────────────────────────────────────────────
            $card  = '<article class="tew-slide" role="listitem">';
            $card .= '<div class="tew-slide__badge"><i class="ph ph-lightning" aria-hidden="true"></i> Eco-Performance</div>';

            if ( $fmt_after ) {
                $card .= '<div class="tew-slide__metrics">';
                if ( $fmt_before ) {
                    $card .= '<span class="tew-mini-before">' . esc_html( $fmt_before ) . '</span>';
                }
                $card .= '<span class="tew-mini-after">' . esc_html( $fmt_after )
                    . ' <small>' . esc_html( $metric ) . '</small></span>';
                if ( $delta ) {
                    $card .= '<span class="tew-mini-delta ' . esc_attr( $delta_class ) . '">' . esc_html( $delta ) . '</span>';
                }
                $card .= '</div>';
            }

            $card .= '<p class="tew-slide__domain">' . esc_html( $domain ) . '</p>';

            if ( $client_name && $client_name !== $domain ) {
                $card .= '<p class="tew-slide__client">' . esc_html( $client_name ) . '</p>';
            }

            if ( $permalink ) {
                $card .= '<a class="tew-slide__link" href="' . $permalink . '"'
                    . ' aria-label="' . esc_attr( sprintf( __( 'Ver informe de %s', 'test-eco-website' ), $client_name ) ) . '">';
                $card .= esc_html__( 'Ver informe', 'test-eco-website' );
                $card .= ' <i class="ph ph-arrow-right" aria-hidden="true"></i></a>';
            }

            $card   .= '</article>';
            $cards  .= $card;
        }

        return $this->slider_css()
            . '<div class="tew-slider-wrap" aria-label="' . esc_attr__( 'Casos de éxito Eco-Performance', 'test-eco-website' ) . '">'
            . '<div class="tew-slider" role="list">'
            . $cards
            . '</div></div>';
    }

    /**
     * Inline CSS for the slider — printed once per page via a static flag.
     *
     * @return string
     */
    private function slider_css() {
        static $printed = false;
        if ( $printed ) {
            return '';
        }
        $printed = true;

        return '
<style id="tew-cases-slider-css">
.tew-slider-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch;
  scrollbar-width:thin;scrollbar-color:rgba(65,153,159,.3) transparent;
  padding-bottom:8px;margin:0 auto;max-width:900px;}
.tew-slider-wrap::-webkit-scrollbar{height:4px;}
.tew-slider-wrap::-webkit-scrollbar-track{background:transparent;}
.tew-slider-wrap::-webkit-scrollbar-thumb{background:rgba(65,153,159,.35);border-radius:4px;}
.tew-slider{display:flex;gap:16px;scroll-snap-type:x mandatory;padding:4px 2px 12px;}
.tew-slide{flex:0 0 clamp(220px,26vw,260px);scroll-snap-align:start;
  background:#fff;border:1px solid var(--rep-border,#dde3e0);
  border-radius:var(--rep-radius-lg,12px);padding:20px 22px;
  display:flex;flex-direction:column;gap:10px;position:relative;
  overflow:hidden;transition:box-shadow .25s,transform .25s;}
.tew-slide::before{content:"";position:absolute;top:0;left:0;right:0;height:3px;
  background:var(--rep-grad,linear-gradient(90deg,#3a9a50,#41999f));
  opacity:0;transition:opacity .2s;}
.tew-slide:hover{box-shadow:0 8px 28px rgba(0,0,0,.1);transform:translateY(-3px);}
.tew-slide:hover::before{opacity:1;}
.tew-slide__badge{display:inline-flex;align-items:center;gap:5px;
  font:600 .7rem/1 var(--rep-font-body,system-ui);text-transform:uppercase;
  letter-spacing:.07em;color:var(--rep-teal,#41999f);
  background:rgba(65,153,159,.08);padding:4px 9px;
  border-radius:var(--rep-radius-full,99px);align-self:flex-start;}
.tew-slide__badge i{font-size:13px;}
.tew-slide__metrics{display:flex;align-items:baseline;gap:7px;flex-wrap:wrap;margin:2px 0;}
.tew-slide__domain{font:600 .82rem/1.3 var(--rep-font-body,system-ui);
  color:var(--rep-text-primary,#1a2420);margin:0;word-break:break-all;}
.tew-slide__client{font:400 .75rem/1.2 var(--rep-font-body,system-ui);
  color:var(--rep-text-muted,#7a8a85);margin:0;}
.tew-slide__link{display:inline-flex;align-items:center;gap:5px;margin-top:auto;
  font:600 .78rem/1 var(--rep-font-body,system-ui);color:var(--rep-teal,#41999f);
  text-decoration:none;padding:6px 0;}
.tew-slide__link:hover{text-decoration:underline;}
.tew-slide__link i{font-size:13px;}
.tew-mini-before{font:600 1rem/1 var(--rep-font-body,system-ui);
  color:#9BA3A0;text-decoration:line-through;
  text-decoration-color:rgba(155,163,160,.4);}
.tew-mini-after{font:700 1.25rem/1 var(--rep-font-body,system-ui);
  color:var(--rep-teal,#41999f);}
.tew-mini-after small{font-size:.65em;font-weight:600;opacity:.8;}
.tew-mini-delta{font:700 .68rem/1 var(--rep-font-body,system-ui);
  background:rgba(65,153,159,.12);color:var(--rep-teal,#41999f);
  padding:3px 6px;border-radius:4px;}
@media(max-width:640px){.tew-slide{flex:0 0 72vw;}}
</style>';
    }

    /**
     * Retorna la clase CSS según el grade.
     *
     * @param string $grade
     *
     * @return string
     */
    private function get_grade_class( $grade ) {
        $map = [
            'A+' => 'grade-a-plus',
            'A'  => 'grade-a',
            'B'  => 'grade-b',
            'C'  => 'grade-c',
            'D'  => 'grade-d',
            'E'  => 'grade-e',
            'F'  => 'grade-f',
        ];

        return isset( $map[ $grade ] ) ? $map[ $grade ] : 'grade-f';
    }
}
