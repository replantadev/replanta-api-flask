<?php
namespace TEW;

use function \add_action;
use function \add_shortcode;
use function \__;
use function \esc_attr_e;
use function \esc_attr;
use function \esc_html_e;
use function \esc_js;
use function \esc_url;
use function \esc_html;
use function \rest_url;
use function \shortcode_atts;
use function \wp_create_nonce;
use function \wp_enqueue_script;
use function \wp_enqueue_style;
use function \wp_generate_password;
use function \wp_localize_script;
use function \wp_register_script;
use function \wp_register_style;
use function \wp_unslash;
use function \esc_url_raw;
use function \sanitize_text_field;
use const \AUTH_KEY;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Shortcode {

    const TAG = 'eco_performance_snapshot';

    /**
     * @var Settings
     */
    private $settings;

    /**
     * @param Settings $settings Gestor de opciones.
     */
    public function __construct( Settings $settings ) {
        $this->settings = $settings;

        add_shortcode( self::TAG, [ $this, 'render' ] );
        add_shortcode( 'eco_form_only', [ $this, 'render_form_only' ] );
        add_shortcode( 'eco_cta', [ $this, 'render_cta' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
    }

    /**
     * Registra estilos y scripts del frontal.
     */
    public function register_assets() {
    // Phosphor Icons: registrar fallback si el tema no lo proporciona
    if ( ! wp_style_is( 'phosphor-icons', 'registered' ) ) {
        wp_register_style( 'phosphor-icons', 'https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css', [], null );
    }

    // Dependencias: solo incluir las que estén registradas
    $css_deps = [ 'phosphor-icons' ];
    if ( wp_style_is( 'replanta-kit', 'registered' ) ) {
        $css_deps[] = 'replanta-kit';
    }

    wp_register_style( 'tew-frontend', TEW_PLUGIN_URL . 'assets/css/frontend.css', $css_deps, TEW_VERSION );
    wp_register_script( 'tew-frontend', TEW_PLUGIN_URL . 'assets/js/frontend.js', [], TEW_VERSION, true );
    
    // Registrar Cloudflare Turnstile si está configurado
    if ( \defined( 'CF_TURNSTILE_SITEKEY' ) && \constant('CF_TURNSTILE_SITEKEY') ) {
        wp_register_script( 'cf-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', [], null, true );
    }

        $settings = $this->settings->all();
        $config   = [
            'endpoint'           => rest_url( 'tew/v1/audit' ),
            'reportEndpoint'     => rest_url( 'tew/v1/reports' ),
            'historyEndpoint'    => rest_url( 'tew/v1/reports/history' ),
            'emailEndpoint'      => rest_url( 'tew/v1/save-email' ),
            'nonce'              => wp_create_nonce( 'wp_rest' ),
            'pluginUrl'          => TEW_PLUGIN_URL,
            'turnstileSiteKey'   => \defined( 'CF_TURNSTILE_SITEKEY' ) ? \constant('CF_TURNSTILE_SITEKEY') : '',
            'messages'           => [
                'placeholder' => __( 'Introduce la URL del sitio a auditar', 'test-eco-website' ),
                'cta'         => __( 'Generar snapshot', 'test-eco-website' ),
                'loading'     => __( 'Preparando informe eco-performance...', 'test-eco-website' ),
                'error'       => __( 'No se pudo completar la auditoría. Inténtalo de nuevo.', 'test-eco-website' ),
                'partial'     => __( 'Algunos servicios no respondieron. Revisa las tarjetas marcadas.', 'test-eco-website' ),
            ],
            'sandbox'            => ! empty( $settings['sandbox_mode'] ),
        ];

        wp_localize_script( 'tew-frontend', 'TEWAudit', $config );
    }

    /**
     * Renderiza el formulario y contenedor del informe.
     */
    public function render( $atts, $content = null ) {
        wp_enqueue_style( 'tew-frontend' );
        wp_enqueue_script( 'tew-frontend' );
        
        // Detectar si viene de un formulario de redirección con token válido
        $from_redirect = isset( $_GET['tew_form_redirect'] ) && $_GET['tew_form_redirect'] === '1';
        $from_campaign = isset( $_GET['from_campaign'] ) && $_GET['from_campaign'] === '1';
        $auto_mode = isset( $_GET['auto'] ) && $_GET['auto'] === '1';
        $site_param = isset( $_GET['site'] ) ? sanitize_text_field( wp_unslash( $_GET['site'] ) ) : '';
        
        $has_valid_token = false;
        $auto_start_url = '';
        $prefill_url = ''; // URL para pre-rellenar aunque el token no sea válido
        
        // Si viene de /r/dominio con from_campaign + auto, bypass completo
        if ( $from_campaign && $auto_mode && ! empty( $site_param ) ) {
            $has_valid_token = true;
            $auto_start_url = 'https://' . $site_param;
            $prefill_url = $auto_start_url;
        }
        // O si viene de redirect tradicional con audit_url
        elseif ( $from_redirect && isset( $_GET['audit_url'] ) ) {
            $url = sanitize_text_field( wp_unslash( $_GET['audit_url'] ) );
            $prefill_url = esc_url_raw( $url );
            
            // Verificar token HMAC si existe
            if ( isset( $_GET['tew_token'] ) ) {
                $token = sanitize_text_field( wp_unslash( $_GET['tew_token'] ) );
                
                if ( $this->verify_redirect_token( $token ) ) {
                    $has_valid_token = true;
                    $auto_start_url = $prefill_url;
                }
            }
        }
        
        // Cargar Turnstile SOLO si está configurado Y NO viene de redirect con token válido Y NO viene de campaña
        $show_turnstile = \defined( 'CF_TURNSTILE_SITEKEY' ) && \constant('CF_TURNSTILE_SITEKEY') && ! $has_valid_token && ! $from_campaign;
        if ( $show_turnstile ) {
            wp_enqueue_script( 'cf-turnstile' );
        }
        
        // Si viene de redirect pero token no válido, al menos pre-rellenamos la URL
        $input_value = $has_valid_token ? $auto_start_url : $prefill_url;

        ob_start();
        ?>
        <section class="tew-snapshot" data-tew-snapshot <?php echo $has_valid_token ? 'data-auto-start="' . esc_attr( $auto_start_url ) . '" data-skip-turnstile="1"' : ''; ?> <?php echo $from_campaign ? 'data-from-campaign="1" data-skip-turnstile="1"' : ''; ?>>
            <div class="tew-snapshot__intro">
                <h1><?php esc_html_e( 'Análisis de Sostenibilidad Web', 'test-eco-website' ); ?></h1>
                <p><?php esc_html_e( 'Descubre el impacto ambiental y la salud técnica de tu sitio en cuestión de minutos.', 'test-eco-website' ); ?></p>
            </div>

            <form class="tew-snapshot__form" novalidate <?php echo $has_valid_token ? 'data-from-redirect="1"' : ''; ?> <?php echo $from_campaign ? 'data-from-campaign="1"' : ''; ?>>
                <label for="tew-snapshot-url" class="screen-reader-text"><?php esc_html_e( 'URL del sitio', 'test-eco-website' ); ?></label>
                <div class="tew-snapshot__input">
                    <i class="ph-bold ph-globe" aria-hidden="true"></i>
                    <input type="url" id="tew-snapshot-url" name="tew-url" placeholder="<?php esc_attr_e( 'https://tu-sitio.org', 'test-eco-website' ); ?>" value="<?php echo esc_attr( $input_value ); ?>" required />
                </div>
                
                <?php if ( $show_turnstile ) : ?>
                <div class="tew-turnstile-wrapper">
                    <div class="cf-turnstile" data-sitekey="<?php echo esc_attr( \constant('CF_TURNSTILE_SITEKEY') ); ?>" data-theme="light"></div>
                </div>
                <?php endif; ?>
                
                <button type="submit" class="tew-snapshot__submit">
                    <i class="ph-bold ph-lightning" aria-hidden="true"></i>
                    <?php esc_html_e( 'Generar informe', 'test-eco-website' ); ?>
                </button>
            </form>

            <div class="tew-snapshot__feedback" hidden>
                <div class="tew-snapshot__spinner"></div>
                <p><?php esc_html_e( 'Recopilando datos de rendimiento y huella de carbono...', 'test-eco-website' ); ?></p>
            </div>

            <div class="tew-snapshot__results" hidden>
                <div class="tew-snapshot__summary" data-tew-summary></div>
                <div class="tew-snapshot__metrics" data-tew-metrics></div>
                <div class="tew-snapshot__gallery" data-tew-gallery hidden></div>
                <div class="tew-snapshot__actions" data-tew-actions></div>
                <?php if ( ! $from_campaign ) : ?>
                <div class="tew-snapshot__email-capture" data-tew-email-capture></div>
                <?php endif; ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza SOLO el formulario que redirige a una página de resultados.
     * 
     * @param array $atts Atributos del shortcode:
     *                   - redirect_page: URL o slug de la página donde mostrar resultados (por defecto: '/eco-informe/')
     *                   - button_text: Texto del botón (por defecto: 'Generar informe')
     *                   - title: Título del formulario (opcional)
     *                   - description: Descripción del formulario (opcional)
     */
    public function render_form_only( $atts, $content = null ) {
        $atts = \shortcode_atts( array(
            'redirect_page' => '/calculadora-huella/',
            'button_text'   => __( 'Generar informe', 'test-eco-website' ),
            'title'         => __( 'Análisis de Sostenibilidad Web', 'test-eco-website' ),
            'description'   => __( 'Introduce la URL de tu sitio web para generar un informe completo de sostenibilidad.', 'test-eco-website' ),
        ), $atts, 'eco_form_only' );

        wp_enqueue_style( 'tew-frontend' );
        
        // Cargar Turnstile si está configurado
        $has_turnstile = \defined( 'CF_TURNSTILE_SITEKEY' ) && \constant('CF_TURNSTILE_SITEKEY');
        if ( $has_turnstile ) {
            wp_enqueue_script( 'cf-turnstile' );
        }

        // Añadir estilos específicos para el formulario usando output buffer
        static $styles_added = false;
        if ( ! $styles_added ) {
            add_action( 'wp_footer', function() {
                echo '<style id="tew-form-only-styles">
                /* ===== Formulario Replanta - Estilo integrado ===== */
                .tew-form-only .tew-snapshot__form.tew-form-redirect {
                    background: var(--rep-white, #FFFFFF) !important;
                    border: 2px solid var(--rep-teal, #41999F) !important;
                    border-radius: 16px !important;
                    padding: 24px !important;
                    box-shadow: var(--rep-shadow-md, 0 4px 6px -1px rgba(30,47,35,.10), 0 2px 4px -1px rgba(30,47,35,.06)) !important;
                    transition: all 0.3s ease !important;
                    margin-bottom: 0 !important;
                    position: relative !important;
                }
                
                .tew-form-only .tew-snapshot__form.tew-form-redirect:hover {
                    box-shadow: var(--rep-shadow-lg, 0 10px 15px -3px rgba(30,47,35,.10), 0 4px 6px -2px rgba(30,47,35,.05)) !important;
                    transform: translateY(-2px) !important;
                }

                .tew-form-only .tew-snapshot__intro h2 {
                    font-family: var(--rep-font-display, \'Sora\', sans-serif) !important;
                    font-size: 1.5rem !important;
                    font-weight: 600 !important;
                    line-height: 1.3 !important;
                    color: var(--rep-forest, #1E2F23) !important;
                    margin: 0 0 8px 0 !important;
                    text-align: center !important;
                }

                .tew-form-only .tew-snapshot__intro p {
                    font-family: var(--rep-font-body, \'Inter\', system-ui, -apple-system, sans-serif) !important;
                    font-size: 1rem !important;
                    line-height: 1.6 !important;
                    color: var(--rep-text-secondary, #3B4B45) !important;
                    margin: 0 0 20px 0 !important;
                    text-align: center !important;
                }

                .tew-form-only .tew-snapshot__input {
                    position: relative !important;
                    margin-bottom: 20px !important;
                    display: flex !important;
                    align-items: center !important;
                }

                .tew-form-only .tew-snapshot__input input {
                    width: 100% !important;
                    padding: 14px 16px 14px 44px !important;
                    border: 1px solid var(--rep-border, #E6F3EF) !important;
                    border-radius: 12px !important;
                    font-family: var(--rep-font-body, \'Inter\', system-ui, -apple-system, sans-serif) !important;
                    font-size: 1rem !important;
                    color: var(--rep-text-secondary, #3B4B45) !important;
                    background: var(--rep-bg-light, #F7FBF9) !important;
                    transition: all 0.3s ease !important;
                    box-sizing: border-box !important;
                }

                .tew-form-only .tew-snapshot__input input:focus {
                    outline: none !important;
                    border-color: var(--rep-teal, #41999F) !important;
                    background: var(--rep-white, #FFFFFF) !important;
                    box-shadow: 0 0 0 3px rgba(65, 153, 159, 0.1) !important;
                }

                .tew-form-only .tew-snapshot__input input::placeholder {
                    color: var(--rep-text-muted, #6B7D76) !important;
                    font-style: italic !important;
                }

                .tew-form-only .tew-snapshot__input [class*="ph-"] {
                    position: absolute !important;
                    left: 12px !important;
                    top: 50% !important;
                    transform: translateY(-50%) !important;
                    color: var(--rep-teal, #41999F) !important;
                    font-size: 20px !important;
                    z-index: 2 !important;
                    pointer-events: none !important;
                }

                .tew-form-only .tew-snapshot__submit {
                    width: 100% !important;
                    padding: 14px 24px !important;
                    font-family: var(--rep-font-body, \'Inter\', system-ui, -apple-system, sans-serif) !important;
                    font-size: 1rem !important;
                    font-weight: 600 !important;
                    background: var(--rep-teal, #41999F) !important;
                    color: var(--rep-white, #FFFFFF) !important;
                    border: none !important;
                    border-radius: 12px !important;
                    cursor: pointer !important;
                    transition: all 0.3s ease !important;
                    display: flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    gap: 8px !important;
                    text-transform: none !important;
                    letter-spacing: normal !important;
                }

                .tew-form-only .tew-snapshot__submit:hover {
                    background: #368f95 !important;
                    transform: translateY(-2px) !important;
                    box-shadow: var(--rep-shadow-md, 0 4px 6px -1px rgba(30,47,35,.10), 0 2px 4px -1px rgba(30,47,35,.06)) !important;
                }

                .tew-form-only .tew-snapshot__submit:active {
                    transform: translateY(0) !important;
                    box-shadow: var(--rep-shadow-sm, 0 1px 2px 0 rgba(30,47,35,.05)) !important;
                }

                .tew-form-only .tew-snapshot__submit [class*="ph-"] {
                    font-size: 18px !important;
                    margin: 0 !important;
                }

                .tew-form-only .tew-snapshot__form.tew-form-redirect::before {
                    content: "Análisis gratuito";
                    position: absolute !important;
                    top: -12px !important;
                    left: 50% !important;
                    transform: translateX(-50%) !important;
                    background: var(--rep-sun, #F7D450) !important;
                    color: var(--rep-forest, #1E2F23) !important;
                    padding: 4px 12px !important;
                    border-radius: 12px !important;
                    font-family: var(--rep-font-body, \'Inter\', system-ui, -apple-system, sans-serif) !important;
                    font-size: 0.75rem !important;
                    font-weight: 600 !important;
                    text-transform: uppercase !important;
                    letter-spacing: 0.5px !important;
                }

                @media (max-width: 768px) {
                    .tew-form-only .tew-snapshot__form.tew-form-redirect {
                        padding: 20px !important;
                        margin: 0 -10px !important;
                    }
                    
                    .tew-form-only .tew-snapshot__intro h2 {
                        font-size: 1.25rem !important;
                    }
                    
                    .tew-form-only .tew-snapshot__intro p {
                        font-size: 0.9rem !important;
                    }
                    
                    .tew-form-only .tew-snapshot__input input {
                        padding: 12px 14px 12px 40px !important;
                        font-size: 0.95rem !important;
                    }
                    
                    .tew-form-only .tew-snapshot__submit {
                        padding: 12px 20px !important;
                        font-size: 0.95rem !important;
                    }
                }

                .tew-form-only.tew-form-minimal .tew-snapshot__form.tew-form-redirect::before {
                    display: none !important;
                }

                .tew-form-only .tew-snapshot__submit:focus-visible {
                    outline: 2px solid var(--rep-sun, #F7D450) !important;
                    outline-offset: 2px !important;
                }

                .tew-form-only .tew-snapshot__input input:focus-visible {
                    box-shadow: 0 0 0 3px rgba(65, 153, 159, 0.2) !important;
                }
                
                /* Validación de URL user-friendly */
                .tew-form-only .tew-url-feedback {
                    font-size: 0.85rem;
                    margin-top: 6px;
                    padding: 6px 10px;
                    border-radius: 8px;
                    display: none;
                    align-items: center;
                    gap: 6px;
                }
                .tew-form-only .tew-url-feedback.show { display: flex; }
                .tew-form-only .tew-url-feedback.valid {
                    background: rgba(76, 175, 80, 0.1);
                    color: #2e7d32;
                }
                .tew-form-only .tew-url-feedback.invalid {
                    background: rgba(244, 67, 54, 0.1);
                    color: #c62828;
                }
                .tew-form-only .tew-url-feedback.warning {
                    background: rgba(255, 152, 0, 0.1);
                    color: #e65100;
                }
                .tew-form-only .tew-url-feedback [class*="ph-"] {
                    font-size: 16px;
                }
                
                /* Turnstile wrapper en form-only */
                .tew-form-only .tew-turnstile-wrapper {
                    margin: 12px 0;
                    display: flex;
                    justify-content: center;
                }
                
                /* Estado de carga del botón */
                .tew-form-only .tew-snapshot__submit.is-validating {
                    opacity: 0.7;
                    pointer-events: none;
                }
                </style>';
            }, 20 );
            $styles_added = true;
        }

        // Determinar clases adicionales según parámetros
        $wrapper_classes = 'tew-snapshot tew-form-only';
        if ( empty( $atts['title'] ) && empty( $atts['description'] ) ) {
            $wrapper_classes .= ' tew-form-minimal';
        }
        
        // Generar ID único para este formulario
        $form_id = 'tew-form-' . wp_generate_password( 8, false );

        ob_start();
        ?>
        <section class="<?php echo esc_attr( $wrapper_classes ); ?>" data-tew-form-only>
            <?php if ( ! empty( $atts['title'] ) ) : ?>
                <div class="tew-snapshot__intro">
                    <h2><?php echo esc_html( $atts['title'] ); ?></h2>
                    <?php if ( ! empty( $atts['description'] ) ) : ?>
                        <p><?php echo esc_html( $atts['description'] ); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form id="<?php echo esc_attr( $form_id ); ?>" class="tew-snapshot__form tew-form-redirect" method="GET" action="<?php echo esc_url( $atts['redirect_page'] ); ?>">
                <label for="<?php echo esc_attr( $form_id ); ?>-url" class="screen-reader-text"><?php esc_html_e( 'URL del sitio', 'test-eco-website' ); ?></label>
                <div class="tew-snapshot__input">
                    <i class="ph-bold ph-globe" aria-hidden="true"></i>
                    <input type="text" 
                           id="<?php echo esc_attr( $form_id ); ?>-url" 
                           name="audit_url" 
                           placeholder="<?php esc_attr_e( 'ejemplo.com o https://ejemplo.com', 'test-eco-website' ); ?>" 
                           autocomplete="url"
                           spellcheck="false"
                           required />
                </div>
                <div class="tew-url-feedback" id="<?php echo esc_attr( $form_id ); ?>-feedback">
                    <i class="ph-bold" aria-hidden="true"></i>
                    <span class="tew-url-feedback__text"></span>
                </div>
                
                <?php if ( $has_turnstile ) : ?>
                <div class="tew-turnstile-wrapper">
                    <div class="cf-turnstile" data-sitekey="<?php echo esc_attr( \constant('CF_TURNSTILE_SITEKEY') ); ?>" data-theme="light" data-callback="tewTurnstileCallback_<?php echo esc_attr( str_replace( '-', '_', $form_id ) ); ?>"></div>
                </div>
                <?php endif; ?>
                
                <button type="submit" class="tew-snapshot__submit" <?php echo $has_turnstile ? 'disabled' : ''; ?>>
                    <i class="ph-bold ph-lightning" aria-hidden="true"></i>
                    <?php echo esc_html( $atts['button_text'] ); ?>
                </button>

                <!-- Campos ocultos -->
                <input type="hidden" name="tew_form_redirect" value="1" />
                <input type="hidden" name="tew_token" value="<?php echo esc_attr( $this->generate_redirect_token() ); ?>" />
            </form>
        </section>
        
        <script>
        (function() {
            var form = document.getElementById('<?php echo esc_js( $form_id ); ?>');
            var input = document.getElementById('<?php echo esc_js( $form_id ); ?>-url');
            var feedback = document.getElementById('<?php echo esc_js( $form_id ); ?>-feedback');
            var submitBtn = form.querySelector('.tew-snapshot__submit');
            var hasTurnstile = <?php echo $has_turnstile ? 'true' : 'false'; ?>;
            var turnstileValid = !hasTurnstile;
            
            // Callback para Turnstile
            window['tewTurnstileCallback_<?php echo esc_js( str_replace( '-', '_', $form_id ) ); ?>'] = function(token) {
                turnstileValid = true;
                updateSubmitState();
            };
            
            function updateSubmitState() {
                var urlValid = isValidUrl(normalizeUrl(input.value.trim()));
                submitBtn.disabled = !(urlValid && turnstileValid);
            }
            
            function normalizeUrl(url) {
                url = url.trim();
                if (!url) return '';
                // Quitar espacios
                url = url.replace(/\s+/g, '');
                // Añadir https si no tiene protocolo
                if (!/^https?:\/\//i.test(url)) {
                    url = 'https://' + url;
                }
                return url;
            }
            
            function isValidUrl(url) {
                if (!url) return false;
                try {
                    var parsed = new URL(url);
                    // Verificar que tiene un dominio válido (al menos un punto)
                    if (!parsed.hostname.includes('.')) return false;
                    // No permitir localhost ni IPs privadas
                    if (/^(localhost|127\.|192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)/i.test(parsed.hostname)) {
                        return false;
                    }
                    return true;
                } catch (e) {
                    return false;
                }
            }
            
            function showFeedback(type, message, icon) {
                feedback.className = 'tew-url-feedback show ' + type;
                var iconMap = {'info': 'ph-info', 'check_circle': 'ph-check-circle', 'error': 'ph-warning-circle', 'security': 'ph-shield-check'};
                var iconEl = feedback.querySelector('[class*="ph-"]');
                iconEl.className = 'ph-bold ' + (iconMap[icon] || 'ph-info');
                feedback.querySelector('.tew-url-feedback__text').textContent = message;
            }
            
            function hideFeedback() {
                feedback.className = 'tew-url-feedback';
            }
            
            // Validación en tiempo real
            var debounceTimer;
            input.addEventListener('input', function() {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function() {
                    var raw = input.value.trim();
                    if (!raw) {
                        hideFeedback();
                        updateSubmitState();
                        return;
                    }
                    
                    var normalized = normalizeUrl(raw);
                    
                    if (isValidUrl(normalized)) {
                        // URL válida
                        if (!/^https?:\/\//i.test(raw)) {
                            showFeedback('warning', 'Se añadirá https:// automáticamente', 'info');
                        } else {
                            showFeedback('valid', 'URL válida', 'check_circle');
                        }
                    } else {
                        // URL inválida
                        if (raw.length < 4) {
                            hideFeedback();
                        } else {
                            showFeedback('invalid', 'Introduce una URL válida (ej: midominio.com)', 'error');
                        }
                    }
                    updateSubmitState();
                }, 300);
            });
            
            // Submit handler
            form.addEventListener('submit', function(e) {
                var raw = input.value.trim();
                var normalized = normalizeUrl(raw);
                
                if (!isValidUrl(normalized)) {
                    e.preventDefault();
                    showFeedback('invalid', 'Por favor, introduce una URL válida', 'error');
                    input.focus();
                    return false;
                }
                
                <?php if ( $has_turnstile ) : ?>
                if (!turnstileValid) {
                    e.preventDefault();
                    showFeedback('invalid', 'Completa la verificación de seguridad', 'security');
                    return false;
                }
                
                // Eliminar el campo cf-turnstile-response para que no se envíe en la URL
                var turnstileInput = form.querySelector('input[name="cf-turnstile-response"]');
                if (turnstileInput) {
                    turnstileInput.remove();
                }
                <?php endif; ?>
                
                // Normalizar URL antes de enviar
                input.value = normalized;
                
                // El token ya está pre-generado en el campo hidden
                submitBtn.classList.add('is-validating');
                submitBtn.innerHTML = '<i class="ph-bold ph-hourglass" aria-hidden="true"></i> Redirigiendo...';
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Genera un token HMAC para validar redirects.
     * El token es válido por 5 minutos y está firmado con AUTH_KEY.
     * 
     * @return string Token HMAC con timestamp.
     */
    private function generate_redirect_token() {
        $secret = \defined( 'AUTH_KEY' ) ? \AUTH_KEY : 'tew-fallback-secret-key';
        // Token válido por 5 minutos (bloque de tiempo)
        $time_block = intval( floor( time() / 300 ) );
        $hash = hash_hmac( 'sha256', 'tew_redirect|' . $time_block, $secret );
        // Devolvemos timestamp + hash para poder verificar
        return $time_block . ':' . substr( $hash, 0, 32 );
    }
    
    /**
     * Verifica un token de redirect.
     * 
     * @param string $token Token a verificar.
     * @return bool True si el token es válido.
     */
    private function verify_redirect_token( $token ) {
        if ( empty( $token ) || strpos( $token, ':' ) === false ) {
            return false;
        }
        
        $parts = explode( ':', $token, 2 );
        if ( count( $parts ) !== 2 ) {
            return false;
        }
        
        $token_time_block = intval( $parts[0] );
        $token_hash = $parts[1];
        
        $secret = \defined( 'AUTH_KEY' ) ? \AUTH_KEY : 'tew-fallback-secret-key';
        $current_time_block = intval( floor( time() / 300 ) );
        
        // Permitir el bloque actual y los 2 anteriores (máximo 15 minutos de validez)
        for ( $i = 0; $i <= 2; $i++ ) {
            $check_block = $current_time_block - $i;
            if ( $token_time_block === $check_block ) {
                $expected_hash = substr( hash_hmac( 'sha256', 'tew_redirect|' . $check_block, $secret ), 0, 32 );
                if ( hash_equals( $expected_hash, $token_hash ) ) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Renderiza el shortcode [eco_cta] para homepage Replanta.
     * CTA rápido: URL → datos eco (histórico o nuevo) → 3 líneas brutales.
     * 
     * @param array $atts Atributos (ninguno requerido por ahora).
     * @return string HTML del bloque CTA.
     */
    public function render_cta( $atts = [], $content = null ) {
        wp_enqueue_style( 'tew-frontend' );
        
        $atts = shortcode_atts( [
            'title' => '¿Qué pasa si te vienes?',
            'button_text' => 'Generar',
        ], $atts, 'eco_cta' );
        
        // ID único para múltiples instancias
        $cta_id = 'tew-cta-' . wp_generate_password( 6, false );
        
        ob_start();
        ?>
        <section class="tew-cta-replanta" id="<?php echo esc_attr( $cta_id ); ?>" data-tew-cta>
            <style>
                .tew-cta-replanta {
                    background: linear-gradient(135deg, rgb(30 47 35 / 79%) 0%, rgb(30 47 35 / 70%) 100%);
                    backdrop-filter: blur(10px);
                    border: 1px solid rgba(147,241,201,.12);
                    border-radius: 20px;
                    padding: 36px 32px;
                    max-width: 820px;
                    margin: 0 auto;
                    position: relative;
                    overflow: hidden;
                    box-shadow: 0 8px 32px rgba(0,0,0,.2);
                }
                .tew-cta-replanta::before {
                    content: '';
                    position: absolute;
                    top: -50%;
                    right: -50px;
                    width: 300px;
                    height: 300px;
                    background: radial-gradient(circle, rgba(147,241,201,.08) 0%, transparent 65%);
                    pointer-events: none;
                }
                .tew-cta__header {
                    display: flex;
                    align-items: center;
                    gap: 16px;
                    flex-wrap: wrap;
                    margin-bottom: 20px;
                }
                .tew-cta__title {
                    font-family: var(--rep-font-display, 'Sora', sans-serif);
                    font-size: 2rem;
                    font-weight: 700;
                    color: var(--rep-green, #93F1C9)!important;
                    margin: 0;
                    line-height: 1.2;
                }
                .tew-cta__form {
                    display: flex;
                    gap: 10px;
                    align-items: center;
                    flex: 1;
                    min-width: 280px;
                }
                .tew-cta__input-wrap {
                    flex: 1;
                    position: relative;
                }
                .tew-cta__input {
                    width: 100%;
                    padding: 12px 18px !important;
                    border: 1px solid rgba(147,241,201,.25)!important;
                    border-radius: 10px !important;
                    background: rgba(255,255,255,.06) !important;
                    color: #fff !important;
                    font-size: .95rem !important;
                    font-family: var(--rep-font-body, 'Inter', sans-serif) !important;
                    transition: all .3s ease;
                }
                .tew-cta__input::placeholder {
                    color: rgba(255,255,255,.45)!important;
                }
                .tew-cta__input:focus {
                    outline: none;
                    border-color: var(--rep-green, #93F1C9)!important;
                    background: rgba(255,255,255,.1)!important;
                    box-shadow: 0 0 0 3px rgba(147,241,201,.08)!important;
                }
                .tew-cta__button {
                    padding: 12px 24px !important;
                    border: none !important;
                    border-radius: 10px !important;
                    background: var(--rep-sun, #F7D450) !important;
                    color: var(--rep-forest, #1E2F23) !important;
                    font-size: .95rem !important;
                    font-weight: 600 !important;
                    font-family: var(--rep-font-body, 'Inter', sans-serif) !important;
                    cursor: pointer !important;
                    transition: all .3s ease !important;
                    white-space: nowrap !important;
                }
                .tew-cta__button:hover:not(:disabled) {
                    background: #f5cc3d;
                    transform: translateY(-1px);
                    box-shadow: 0 4px 12px rgba(247,212,80,.3);
                }
                .tew-cta__button:disabled {
                    opacity: .6;
                    cursor: not-allowed;
                }
                .tew-cta__analyzed-url {
                    display: none;
                    align-items: center;
                    gap: 8px;
                    padding: 8px 14px;
                    background: rgba(147,241,201,.1);
                    border: 1px solid rgba(147,241,201,.2);
                    border-radius: 8px;
                    flex: 1;
                    min-width: 280px;
                }
                .tew-cta__analyzed-url.active {
                    display: flex;
                }
                .tew-cta__analyzed-url-text {
                    font-family: var(--rep-font-body, 'Inter', sans-serif);
                    font-size: .9rem;
                    color: var(--rep-green, #93F1C9);
                    flex: 1;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }
                .tew-cta__loading {
                    display: none;
                    text-align: center;
                    padding: 16px 0;
                }
                .tew-cta__loading.active {
                    display: block;
                }
                .tew-cta__loading-text {
                    font-family: var(--rep-font-body, 'Inter', sans-serif);
                    font-size: 1rem;
                    color: var(--rep-green, #93F1C9);
                    animation: tew-pulse 2s ease-in-out infinite;
                }
                @keyframes tew-pulse {
                    0%, 100% { opacity: .55; }
                    50% { opacity: 1; }
                }
                .tew-cta__results {
                    display: none;
                    margin-top: 20px;
                }
                .tew-cta__results.active {
                    display: block;
                }
                .tew-cta__result-line {
                    background: rgba(147,241,201,.06);
                    border-left: 3px solid var(--rep-sun, #F7D450);
                    border-radius: 8px;
                    padding: 12px 16px;
                    margin-bottom: 10px;
                    font-family: var(--rep-font-body, 'Inter', sans-serif);
                    font-size: .98rem;
                    color: rgba(255,255,255,.9);
                    line-height: 1.5;
                }
                .tew-cta__result-line strong {
                    color: var(--rep-sun, #F7D450);
                    font-weight: 700;
                }
                .tew-cta__result-line em {
                    color: var(--rep-green, #93F1C9);
                    font-style: normal;
                    font-weight: 600;
                }
                .tew-cta__cta-btn {
                    margin-top: 16px;
                    text-align: center;
                }
                .tew-cta__cta-btn a {
                    display: inline-block;
                    padding: 12px 24px;
                    background: var(--rep-teal, #41999F);
                    color: #fff;
                    text-decoration: none;
                    border-radius: 10px;
                    font-size: .95rem;
                    font-weight: 600;
                    font-family: var(--rep-font-body, 'Inter', sans-serif);
                    transition: all .3s ease;
                }
                .tew-cta__cta-btn a:hover {
                    background: #368f95;
                    transform: translateY(-1px);
                    box-shadow: 0 4px 12px rgba(65,153,159,.3);
                }
                .tew-cta__error {
                    display: none;
                    margin-top: 12px;
                    padding: 12px 16px;
                    background: rgba(244,67,54,.12);
                    border-left: 3px solid #f44336;
                    border-radius: 8px;
                    color: rgba(255,204,203,.9);
                    font-size: .9rem;
                    font-family: var(--rep-font-body, 'Inter', sans-serif);
                }
                .tew-cta__error.active {
                    display: block;
                }
                @media (max-width: 768px) {
                    .tew-cta-replanta {
                        padding: 28px 20px;
                    }
                    .tew-cta__header {
                        flex-direction: column;
                        align-items: flex-start;
                        gap: 12px;
                    }
                    .tew-cta__title {
                        font-size: 1.65rem;
                    }
                    .tew-cta__form {
                        flex-direction: column;
                        width: 100%;
                    }
                    .tew-cta__button {
                        width: 100%;
                    }
                    .tew-cta__analyzed-url {
                        width: 100%;
                    }
                }
            </style>
            
            <div class="tew-cta__header">
                <h2 class="tew-cta__title"><?php echo esc_html( $atts['title'] ); ?></h2>
                
                <form class="tew-cta__form" data-tew-cta-form>
                    <div class="tew-cta__input-wrap">
                        <input 
                            type="text" 
                            class="tew-cta__input" 
                            name="cta_url" 
                            placeholder="midominio.com" 
                            required 
                            autocomplete="url"
                            spellcheck="false"
                        />
                    </div>
                    <button type="submit" class="tew-cta__button">
                        <?php echo esc_html( $atts['button_text'] ); ?>
                    </button>
                </form>
                
                <div class="tew-cta__analyzed-url">
                    <span class="tew-cta__analyzed-url-text" data-analyzed-domain></span>
                </div>
            </div>
            
            <div class="tew-cta__loading">
                <p class="tew-cta__loading-text">Calentando motores ecológicos... <i class="ph-duotone ph-plant" aria-hidden="true"></i></p>
            </div>
            
            <div class="tew-cta__error"></div>
            
            <div class="tew-cta__results">
                <div class="tew-cta__result-line" data-line="co2"></div>
                <div class="tew-cta__result-line" data-line="speed"></div>
                <div class="tew-cta__result-line" data-line="trees"></div>
                <div class="tew-cta__cta-btn">
                    <a href="/calculadora-huella/" data-result-link>¡Ahí está! Ver informe completo →</a>
                </div>
            </div>
        </section>
        
        <script>
        (function() {
            const ctaEl = document.getElementById('<?php echo esc_js( $cta_id ); ?>');
            const form = ctaEl.querySelector('[data-tew-cta-form]');
            const input = ctaEl.querySelector('[name="cta_url"]');
            const button = ctaEl.querySelector('.tew-cta__button');
            const loading = ctaEl.querySelector('.tew-cta__loading');
            const results = ctaEl.querySelector('.tew-cta__results');
            const errorEl = ctaEl.querySelector('.tew-cta__error');
            const resultLink = ctaEl.querySelector('[data-result-link]');
            const analyzedUrlEl = ctaEl.querySelector('.tew-cta__analyzed-url');
            const analyzedDomain = ctaEl.querySelector('[data-analyzed-domain]');
            
            const messages = [
                'Calentando motores ecológicos... <i class="ph-duotone ph-plant" aria-hidden="true"></i>',
                'Tu informe llega: árbol a la vista <i class="ph-duotone ph-tree" aria-hidden="true"></i>',
                'Un segundo... enseñándole a tu web a no contaminar como un V8 <i class="ph-duotone ph-car" aria-hidden="true"></i>'
            ];
            let msgIndex = 0;
            
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const rawUrl = input.value.trim();
                if (!rawUrl) return;
                
                // Normalizar URL
                let url = rawUrl;
                if (!/^https?:\/\//i.test(url)) {
                    url = 'https://' + url;
                }
                
                // Extraer dominio para mostrar
                const displayDomain = url.replace(/^https?:\/\/(www\.)?/, '').replace(/\/.*$/, '');
                
                // Ocultar errores previos y form
                errorEl.classList.remove('active');
                errorEl.textContent = '';
                form.style.display = 'none';
                analyzedUrlEl.classList.remove('active');
                results.classList.remove('active');
                loading.classList.add('active');
                
                // Rotar mensajes
                const loadingText = loading.querySelector('.tew-cta__loading-text');
                const msgInterval = setInterval(() => {
                    msgIndex = (msgIndex + 1) % messages.length;
                    loadingText.textContent = messages[msgIndex];
                }, 2000);
                
                try {
                    // Usar endpoint rápido optimizado para CTA
                    const quickUrl = '<?php echo esc_js( rest_url( 'tew/v1/cta-quick' ) ); ?>';
                    const quickRes = await fetch(quickUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>'
                        },
                        body: JSON.stringify({ 
                            url: url,
                            cf_turnstile_response: turnstile ? turnstile.getResponse() : null
                        })
                    });
                    
                    if (!quickRes.ok) {
                        const errData = await quickRes.json().catch(() => ({}));
                        throw new Error(errData.message || 'No se pudo analizar el sitio');
                    }
                    
                    const data = await quickRes.json();
                    
                    clearInterval(msgInterval);
                    
                    // Mostrar dominio analizado
                    analyzedDomain.textContent = '? ' + displayDomain;
                    analyzedUrlEl.classList.add('active');
                    
                    // Renderizar resultados
                    renderResults(data, url, displayDomain);
                    
                } catch (error) {
                    clearInterval(msgInterval);
                    loading.classList.remove('active');
                    form.style.display = 'flex';
                    analyzedUrlEl.classList.remove('active');
                    errorEl.textContent = error.message || 'Hubo un error al analizar el sitio. Intenta de nuevo.';
                    errorEl.classList.add('active');
                }
            });
            
            function renderResults(data, url, displayDomain) {
                loading.classList.remove('active');
                results.classList.add('active');
                
                console.log('TEW CTA - Datos recibidos:', data);
                
                // El endpoint rápido devuelve datos ya procesados
                const co2Monthly = parseFloat(data.co2_monthly) || 0;
                const co2Replanta = parseFloat(data.co2_replanta) || 0;
                const speedMs = parseFloat(data.speed_ms) || 3000;
                const treesYearly = parseFloat(data.trees_yearly) || 1;
                
                const currentSpeed = (speedMs / 1000).toFixed(2);
                const improvement = 0.25; // 25% mejora con Replanta
                const speedReplanta = ((speedMs * (1 - improvement)) / 1000).toFixed(2);
                const speedSaving = (improvement * 100).toFixed(0);
                
                console.log('TEW CTA - Métricas:', {
                    co2Monthly,
                    co2Replanta,
                    currentSpeed,
                    speedReplanta,
                    treesYearly,
                    source: data.source
                });
                
                // Línea 1: CO2
                const line1 = ctaEl.querySelector('[data-line="co2"]');
                if (co2Monthly > 0) {
                    line1.innerHTML = `Tu web ahora: <strong>${co2Monthly} kg CO2/mes</strong>. Con nosotros: <em>${co2Replanta} kg</em>.`;
                } else {
                    line1.innerHTML = `Tu web: mejora estimada de <strong>-${speedSaving}%</strong> en CO2.`;
                }
                
                // Línea 2: Velocidad
                const line2 = ctaEl.querySelector('[data-line="speed"]');
                if (currentSpeed) {
                    line2.innerHTML = `Carga en <strong>${currentSpeed}s</strong>. Nosotros: <em>${speedReplanta}s</em>. <strong>-${speedSaving}%</strong> más rápido.`;
                } else {
                    line2.innerHTML = `Velocidad mejorada: <strong>-${speedSaving}%</strong> más rápido (aprox. 1-2s menos de carga).`;
                }
                
                // Línea 3: árboles
                const line3 = ctaEl.querySelector('[data-line="trees"]');
                line3.innerHTML = `Eso son <strong>${treesYearly} árboles/año</strong>. <em>Te los plantamos ya.</em> <i class="ph-duotone ph-tree" aria-hidden="true"></i>`;
                
                // Actualizar link
                resultLink.href = '/calculadora-huella/?audit_url=' + encodeURIComponent(url);
            }
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
