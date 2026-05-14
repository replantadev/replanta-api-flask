<?php
namespace TEW\Admin;

use TEW\Reporting\Report_Storage;
use TEW\Settings;
use TEW\Utils;
use function __;
use function absint;
use function add_action;
use function add_menu_page;
use function add_submenu_page;
use function admin_url;
use function current_user_can;
use function disabled;
use function do_settings_sections;
use function esc_attr;
use function esc_attr_e;
use function esc_html;
use function esc_html_e;
use function esc_url;
use function get_option;
use function number_format;
use function number_format_i18n;
use function rest_url;
use function sanitize_text_field;
use function selected;
use function settings_fields;
use function submit_button;
use function wp_create_nonce;
use function wp_date;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_localize_script;
use function wp_send_json_error;
use function wp_send_json_success;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin_Page {

    /**
     * @var Settings
     */
    private $settings;

    /**
     * @var Report_Storage
     */
    private $storage;

    /**
     * @param Settings $settings Gestor de opciones.
     */
    public function __construct( Settings $settings ) {
        file_put_contents( WP_CONTENT_DIR . '/tew-debug.log', '[' . date('Y-m-d H:i:s') . '] TEW CONSTRUCTOR - Admin_Page constructor ejecutado' . PHP_EOL, FILE_APPEND );
        
        $this->settings = $settings;
        $this->storage  = new Report_Storage();
        
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_tew_delete_success_case', [ $this, 'ajax_delete_success_case' ] );
        
        file_put_contents( WP_CONTENT_DIR . '/tew-debug.log', '[' . date('Y-m-d H:i:s') . '] TEW CONSTRUCTOR - Hook wp_ajax_tew_delete_success_case registrado' . PHP_EOL, FILE_APPEND );
    }

    /**
     * Registra el menú en el escritorio.
     */
    public function register_menu() {
        add_menu_page(
            __( 'Eco Snapshot', 'test-eco-website' ),
            __( 'Eco Snapshot', 'test-eco-website' ),
            'manage_options',
            'tew-settings',
            [ $this, 'render_page' ],
            'dashicons-leaf',
            58
        );

        // Submenú: Configuración (primer item explícito para controlar el orden)
        add_submenu_page(
            'tew-settings',
            __( 'Configuración', 'test-eco-website' ),
            __( 'Configuración', 'test-eco-website' ),
            'manage_options',
            'tew-settings',
            [ $this, 'render_page' ]
        );

        // Submenú: Dashboard de análisis
        add_submenu_page(
            'tew-settings',
            __( 'Análisis Realizados', 'test-eco-website' ),
            __( 'Dashboard', 'test-eco-website' ),
            'manage_options',
            'tew-dashboard',
            [ $this, 'render_dashboard' ]
        );

        // Submenú: Casos de Éxito
        add_submenu_page(
            'tew-settings',
            __( 'Casos de Éxito', 'test-eco-website' ),
            __( 'Casos de Éxito', 'test-eco-website' ),
            'manage_options',
            'tew-success-cases',
            [ $this, 'render_success_cases' ]
        );
    }

    /**
     * Encola estilos y scripts para la página de ajustes.
     */
    public function enqueue_assets( $hook ) {
        // Cargar en todas las páginas de TEW
        $tew_pages = [
            'toplevel_page_tew-settings',
            'eco-snapshot_page_tew-dashboard',
            'eco-snapshot_page_tew-success-cases',
        ];
        
        if ( ! in_array( $hook, $tew_pages, true ) ) {
            return;
        }

        wp_enqueue_style( 'tew-admin-google-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap', [], null );
        wp_enqueue_style( 'tew-admin-material-icons', 'https://fonts.googleapis.com/icon?family=Material+Icons+Round', [], null );
        wp_enqueue_style( 'tew-admin', TEW_PLUGIN_URL . 'assets/css/admin.css', [ 'wp-components' ], TEW_VERSION );

        wp_enqueue_script( 'tew-admin', TEW_PLUGIN_URL . 'assets/js/admin.js', [ 'jquery', 'wp-util' ], TEW_VERSION, true );
        wp_localize_script( 'tew-admin', 'TEWSettings', [
            'nonce'       => wp_create_nonce( 'wp_rest' ),
            'testEndpoint' => rest_url( 'tew/v1/test-credentials' ),
            'messages'    => [
                'testing'  => __( 'Comprobando...', 'test-eco-website' ),
                'success'  => __( '¡Conexión verificada!', 'test-eco-website' ),
                'error'    => __( 'No se pudo validar. Revisa la clave o inténtalo más tarde.', 'test-eco-website' ),
            ],
        ] );
    }

    /**
     * Renderiza la página de ajustes con una estética cuidada.
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $options = $this->settings->all();
        ?>
        <div class="wrap tew-admin">
            <header class="tew-admin__header">
                <div class="tew-admin__title-group">
                    <span class="material-icons-round">eco</span>
                    <div>
                        <h1><?php esc_html_e( 'Análisis de Sostenibilidad Web', 'test-eco-website' ); ?></h1>
                        <p><?php esc_html_e( 'Centraliza tus credenciales y prepárate para auditar webs con precisión ecológica.', 'test-eco-website' ); ?></p>
                    </div>
                </div>
                <div class="tew-admin__cta">
                    <code>[eco_performance_snapshot]</code>
                    <code>[eco_form_only]</code>
                    <code>[eco_cta]</code>
                    <button type="button" class="button button-primary tew-admin__preview" id="tew-preview-shortcode" data-preview-url="<?php echo esc_url( admin_url( 'admin-ajax.php?action=tew_preview' ) ); ?>">
                        <span class="material-icons-round">visibility</span>
                        <?php esc_html_e( 'Vista previa', 'test-eco-website' ); ?>
                    </button>
                </div>
            </header>

            <div class="tew-admin__grid">
                <section class="tew-card tew-card--form">
                    <h2><span class="material-icons-round">vpn_key</span><?php esc_html_e( 'Credenciales', 'test-eco-website' ); ?></h2>
                    <p class="tew-card__subtitle"><?php esc_html_e( 'Conecta con las plataformas para generar el informe completo en minutos.', 'test-eco-website' ); ?></p>

                    <form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" class="tew-form" novalidate>
                        <?php
                        settings_fields( 'tew_settings_group' );
                        do_settings_sections( 'tew_settings' );
                        submit_button( __( 'Guardar ajustes', 'test-eco-website' ), 'primary large', 'submit', false, [ 'class' => 'tew-form__submit' ] );
                        ?>
                    </form>
                </section>

                <section class="tew-card tew-card--status">
                    <h2><span class="material-icons-round">science</span><?php esc_html_e( 'Probar conexiones', 'test-eco-website' ); ?></h2>
                    <p class="tew-card__subtitle"><?php esc_html_e( 'Verifica que cada servicio responde antes de lanzar auditorías.', 'test-eco-website' ); ?></p>

                    <ul class="tew-status-list">
                        <?php foreach ( [
                            'pagespeed'     => [
                                'label'        => __( 'PageSpeed Insights', 'test-eco-website' ),
                                'key'          => 'pagespeed_api_key',
                                'requires_key' => true,
                                'missing_text' => __( 'Introduce la clave y guarda los cambios.', 'test-eco-website' ),
                                'success_text' => __( 'Clave guardada. Listo para comprobar.', 'test-eco-website' ),
                            ],
                            'websitecarbon' => [
                                'label'        => __( 'Website Carbon', 'test-eco-website' ),
                                'key'          => null,
                                'requires_key' => false,
                                'missing_text' => __( 'Sin clave: se consulta el endpoint público y se mezcla con PageSpeed.', 'test-eco-website' ),
                                'success_text' => __( 'Conexión pública disponible. Alimenta el Eco Snapshot Score.', 'test-eco-website' ),
                            ],
                            'greenweb'      => [
                                'label'        => __( 'Green Web Foundation', 'test-eco-website' ),
                                'key'          => null,
                                'requires_key' => false,
                                'missing_text' => __( 'Consulta automática sin claves.', 'test-eco-website' ),
                                'success_text' => __( 'Consulta automática sin claves.', 'test-eco-website' ),
                            ],
                        ] as $service => $data ) :
                            $has_key = ! $data['requires_key'] || ( $data['key'] && ! empty( $options[ $data['key'] ] ) );
                            $info_text = $has_key
                                ? ( isset( $data['success_text'] ) ? $data['success_text'] : __( 'Listo para comprobar', 'test-eco-website' ) )
                                : $data['missing_text'];
                            ?>
                            <li class="tew-status-list__item" data-service="<?php echo esc_attr( $service ); ?>">
                                <div class="tew-status-list__info">
                                    <span class="tew-status-list__icon material-icons-round">check_circle</span>
                                    <div>
                                        <h3><?php echo esc_html( $data['label'] ); ?></h3>
                                        <p><?php echo esc_html( $info_text ); ?></p>
                                    </div>
                                </div>
                                <button type="button" class="button button-secondary tew-status-list__button" data-service="<?php echo esc_attr( $service ); ?>" <?php disabled( $data['requires_key'] && ! $has_key ); ?>>
                                    <span class="material-icons-round">bolt</span>
                                    <?php esc_html_e( 'Probar', 'test-eco-website' ); ?>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="tew-toast" role="status" aria-live="polite" hidden>
                        <span class="material-icons-round tew-toast__icon" aria-hidden="true">info</span>
                        <div class="tew-toast__content">
                            <strong class="tew-toast__title">Eco Snapshot</strong>
                            <span class="tew-toast__message"></span>
                        </div>
                        <button type="button" class="tew-toast__close" aria-label="<?php esc_attr_e( 'Cerrar notificación', 'test-eco-website' ); ?>">
                            <span class="material-icons-round" aria-hidden="true">close</span>
                        </button>
                    </div>
                </section>

                <section class="tew-card tew-card--help">
                    <h2><span class="material-icons-round">menu_book</span><?php esc_html_e( 'Guía rápida', 'test-eco-website' ); ?></h2>
                    <ol class="tew-steps">
                        <li>
                            <strong><?php esc_html_e( 'Completa las credenciales', 'test-eco-website' ); ?></strong>
                            <span><?php esc_html_e( 'Google PSI es obligatoria. Website Carbon se consulta vía endpoint público y el hosting verde se comprueba automáticamente.', 'test-eco-website' ); ?></span>
                        </li>
                        <li>
                            <strong><?php esc_html_e( 'Guarda y verifica', 'test-eco-website' ); ?></strong>
                            <span><?php esc_html_e( 'Usa los botones de prueba para asegurarte de que todo responde.', 'test-eco-website' ); ?></span>
                        </li>
                        <li>
                            <strong><?php esc_html_e( 'Añade el shortcode', 'test-eco-website' ); ?></strong>
                            <span><?php esc_html_e( 'Inserta el formulario en la página de auditorías o un bloque HTML.', 'test-eco-website' ); ?></span>
                        </li>
                        <li>
                            <strong><?php esc_html_e( 'Ejecuta la auditoría', 'test-eco-website' ); ?></strong>
                            <span><?php esc_html_e( 'El Eco Snapshot Score combinará PageSpeed, Website Carbon y Green Web en minutos.', 'test-eco-website' ); ?></span>
                        </li>
                    </ol>
                </section>

                <section class="tew-card tew-card--shortcodes">
                    <h2><span class="material-icons-round">code</span><?php esc_html_e( 'Shortcodes disponibles', 'test-eco-website' ); ?></h2>
                    
                    <div class="tew-shortcode-item">
                        <h3><code>[eco_performance_snapshot]</code></h3>
                        <p><?php esc_html_e( 'Formulario completo que ejecuta el análisis y muestra los resultados en la misma página. Ideal para la página principal de auditoría.', 'test-eco-website' ); ?></p>
                    </div>

                    <div class="tew-shortcode-item">
                        <h3><code>[eco_form_only]</code></h3>
                        <p><?php esc_html_e( 'Solo el formulario de entrada. Al enviar, redirige a otra página donde se mostrarán los resultados. Perfecto para incrustar en landing pages, sidebars o CTAs sin mostrar el informe allí.', 'test-eco-website' ); ?></p>
                        <div class="tew-shortcode-params">
                            <h4><?php esc_html_e( 'Parámetros opcionales:', 'test-eco-website' ); ?></h4>
                            <ul>
                                <li><code>redirect_page="/calculadora-huella/"</code> — <?php esc_html_e( 'URL donde mostrar resultados (debe tener [eco_performance_snapshot])', 'test-eco-website' ); ?></li>
                                <li><code>button_text="Analizar mi web"</code> — <?php esc_html_e( 'Texto del botón de envío', 'test-eco-website' ); ?></li>
                                <li><code>title="Tu título"</code> — <?php esc_html_e( 'Título del formulario (opcional)', 'test-eco-website' ); ?></li>
                                <li><code>description="Tu descripción"</code> — <?php esc_html_e( 'Texto descriptivo (opcional)', 'test-eco-website' ); ?></li>
                            </ul>
                        </div>
                        <div class="tew-shortcode-example">
                            <h4><?php esc_html_e( 'Ejemplo de uso:', 'test-eco-website' ); ?></h4>
                            <code>[eco_form_only redirect_page="/eco-informe/" button_text="¡Analiza gratis!" title="" description=""]</code>
                        </div>
                    </div>
                    
                    <div class="tew-shortcode-item">
                        <h3><code>[eco_cta]</code></h3>
                        <p><?php esc_html_e( 'CTA brutal para homepage Replanta: formulario rápido + 3 líneas de impacto directo (CO2 actual vs. Replanta, velocidad, árboles). Usa histórico si existe, o auditoría express. Bloque verde oscuro optimizado para conversión.', 'test-eco-website' ); ?></p>
                        <div class="tew-shortcode-params">
                            <h4><?php esc_html_e( 'Parámetros opcionales:', 'test-eco-website' ); ?></h4>
                            <ul>
                                <li><code>title="¿Qué pasa si te vienes?"</code> — <?php esc_html_e( 'Título principal del CTA', 'test-eco-website' ); ?></li>
                                <li><code>button_text="Generar"</code> — <?php esc_html_e( 'Texto del botón', 'test-eco-website' ); ?></li>
                            </ul>
                        </div>
                        <div class="tew-shortcode-example">
                            <h4><?php esc_html_e( 'Ejemplo de uso:', 'test-eco-website' ); ?></h4>
                            <code>[eco_cta title="¿Listo para el cambio?" button_text="Calcular ahora"]</code>
                        </div>
                    </div>
                </section>
            </div>
        </div>
        <?php
    }

    /**
     * Renderiza el dashboard de análisis con tabla filtrable.
     */
    public function render_dashboard() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Obtener filtros
        $filter_green = isset( $_GET['filter_green'] ) ? sanitize_text_field( $_GET['filter_green'] ) : '';
        $filter_potential = isset( $_GET['filter_potential'] ) ? sanitize_text_field( $_GET['filter_potential'] ) : '';
        $filter_country = isset( $_GET['filter_country'] ) ? sanitize_text_field( $_GET['filter_country'] ) : '';

        // Construir args para la consulta
        $query_args = [];
        if ( $filter_green !== '' ) {
            $query_args['is_green'] = ( $filter_green === '1' );
        }
        if ( ! empty( $filter_potential ) ) {
            $query_args['potential'] = $filter_potential;
        }
        if ( ! empty( $filter_country ) ) {
            $query_args['country'] = $filter_country;
        }

        // Obtener análisis
        $analyses = $this->storage->get_all_analyses( $query_args );

        // Obtener países únicos para filtro
        $all_analyses = $this->storage->get_all_analyses( [ 'posts_per_page' => -1 ] );
        $countries = array_unique( array_filter( array_column( $all_analyses, 'country' ) ) );
        sort( $countries );

        ?>
        <div class="wrap tew-admin tew-dashboard">
            <header class="tew-admin__header">
                <div class="tew-admin__title-group">
                    <span class="material-icons-round">analytics</span>
                    <div>
                        <h1><?php esc_html_e( 'Dashboard de Análisis', 'test-eco-website' ); ?></h1>
                        <p><?php esc_html_e( 'Todos los análisis de sostenibilidad web realizados.', 'test-eco-website' ); ?></p>
                    </div>
                </div>
            </header>

            <div class="tew-admin__body">
                <!-- Stats resumidas -->
                <div class="tew-stats-grid">
                    <?php
                    $total = count( $all_analyses );
                    $total_green = count( array_filter( $all_analyses, function( $a ) { return $a['is_green']; } ) );
                    $total_high_potential = count( array_filter( $all_analyses, function( $a ) { return $a['potential'] === 'high'; } ) );
                    $total_with_email = count( array_filter( $all_analyses, function( $a ) { return ! empty( $a['email'] ); } ) );
                    ?>
                    <div class="tew-stat-card">
                        <span class="material-icons-round tew-stat-icon">assessment</span>
                        <div>
                            <strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong>
                            <span><?php esc_html_e( 'Análisis totales', 'test-eco-website' ); ?></span>
                        </div>
                    </div>
                    <div class="tew-stat-card tew-stat-card--green">
                        <span class="material-icons-round tew-stat-icon">eco</span>
                        <div>
                            <strong><?php echo esc_html( number_format_i18n( $total_green ) ); ?></strong>
                            <span><?php esc_html_e( 'Hosting verde', 'test-eco-website' ); ?></span>
                        </div>
                    </div>
                    <div class="tew-stat-card tew-stat-card--potential">
                        <span class="material-icons-round tew-stat-icon">trending_up</span>
                        <div>
                            <strong><?php echo esc_html( number_format_i18n( $total_high_potential ) ); ?></strong>
                            <span><?php esc_html_e( 'Alto potencial', 'test-eco-website' ); ?></span>
                        </div>
                    </div>
                    <div class="tew-stat-card tew-stat-card--email">
                        <span class="material-icons-round tew-stat-icon">email</span>
                        <div>
                            <strong><?php echo esc_html( number_format_i18n( $total_with_email ) ); ?></strong>
                            <span><?php esc_html_e( 'Con email', 'test-eco-website' ); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="tew-filters">
                    <form method="get" action="">
                        <input type="hidden" name="page" value="tew-dashboard">
                        
                        <select name="filter_green">
                            <option value=""><?php esc_html_e( 'Todos los hostings', 'test-eco-website' ); ?></option>
                            <option value="1" <?php selected( $filter_green, '1' ); ?>><?php esc_html_e( 'Solo hosting verde', 'test-eco-website' ); ?></option>
                            <option value="0" <?php selected( $filter_green, '0' ); ?>><?php esc_html_e( 'Solo no verde', 'test-eco-website' ); ?></option>
                        </select>

                        <select name="filter_potential">
                            <option value=""><?php esc_html_e( 'Todos los potenciales', 'test-eco-website' ); ?></option>
                            <option value="high" <?php selected( $filter_potential, 'high' ); ?>><?php esc_html_e( 'Alto potencial', 'test-eco-website' ); ?></option>
                            <option value="medium" <?php selected( $filter_potential, 'medium' ); ?>><?php esc_html_e( 'Potencial medio', 'test-eco-website' ); ?></option>
                            <option value="low" <?php selected( $filter_potential, 'low' ); ?>><?php esc_html_e( 'Bajo potencial', 'test-eco-website' ); ?></option>
                        </select>

                        <select name="filter_country">
                            <option value=""><?php esc_html_e( 'Todos los países', 'test-eco-website' ); ?></option>
                            <?php foreach ( $countries as $country ) : ?>
                                <option value="<?php echo esc_attr( $country ); ?>" <?php selected( $filter_country, $country ); ?>>
                                    <?php echo esc_html( $country ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <button type="submit" class="button"><?php esc_html_e( 'Filtrar', 'test-eco-website' ); ?></button>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=tew-dashboard' ) ); ?>" class="button"><?php esc_html_e( 'Limpiar', 'test-eco-website' ); ?></a>
                    </form>
                </div>

                <!-- Tabla de análisis -->
                <div class="tew-table-wrapper">
                    <?php if ( empty( $analyses ) ) : ?>
                        <div class="tew-empty-state">
                            <span class="material-icons-round">inbox</span>
                            <p><?php esc_html_e( 'No se encontraron análisis con los filtros seleccionados.', 'test-eco-website' ); ?></p>
                        </div>
                    <?php else : ?>
                        <table class="wp-list-table widefat fixed striped tew-analyses-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'URL', 'test-eco-website' ); ?></th>
                                    <th><?php esc_html_e( 'Score', 'test-eco-website' ); ?></th>
                                    <th><?php esc_html_e( 'Grado', 'test-eco-website' ); ?></th>
                                    <th><?php esc_html_e( 'Hosting', 'test-eco-website' ); ?></th>
                                    <th><?php esc_html_e( 'CO₂/visita', 'test-eco-website' ); ?></th>
                                    <th><?php esc_html_e( 'País', 'test-eco-website' ); ?></th>
                                    <th><?php esc_html_e( 'Email', 'test-eco-website' ); ?></th>
                                    <th><?php esc_html_e( 'Potencial', 'test-eco-website' ); ?></th>
                                    <th><?php esc_html_e( 'Fecha', 'test-eco-website' ); ?></th>
                                    <th><?php esc_html_e( 'Acciones', 'test-eco-website' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $analyses as $analysis ) : ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo esc_url( $analysis['url'] ); ?>" target="_blank" rel="noopener">
                                                <?php echo esc_html( $analysis['url'] ); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <strong class="tew-score tew-score--<?php echo esc_attr( $this->get_score_class( $analysis['score'] ) ); ?>">
                                                <?php echo esc_html( number_format( $analysis['score'], 1 ) ); ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <span class="tew-grade tew-grade--<?php echo esc_attr( strtolower( $analysis['grade'] ) ); ?>">
                                                <?php echo esc_html( $analysis['grade'] ?: '—' ); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ( $analysis['is_green'] ) : ?>
                                                <span class="tew-badge tew-badge--green">
                                                    <span class="material-icons-round">eco</span>
                                                    <?php esc_html_e( 'Verde', 'test-eco-website' ); ?>
                                                </span>
                                            <?php else : ?>
                                                <span class="tew-badge tew-badge--gray">
                                                    <span class="material-icons-round">warning</span>
                                                    <?php esc_html_e( 'No verde', 'test-eco-website' ); ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ( $analysis['hosting_provider'] ) : ?>
                                                <br><small><?php echo esc_html( $analysis['hosting_provider'] ); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html( number_format( $analysis['co2_per_view'], 2 ) ); ?> g</td>
                                        <td><?php echo esc_html( $analysis['country'] ?: '—' ); ?></td>
                                        <td>
                                            <?php if ( $analysis['email'] ) : ?>
                                                <a href="mailto:<?php echo esc_attr( $analysis['email'] ); ?>">
                                                    <?php echo esc_html( $analysis['email'] ); ?>
                                                </a>
                                            <?php else : ?>
                                                <span class="tew-text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $potential_labels = [
                                                'high'   => __( 'Alto', 'test-eco-website' ),
                                                'medium' => __( 'Medio', 'test-eco-website' ),
                                                'low'    => __( 'Bajo', 'test-eco-website' ),
                                            ];
                                            $potential = $analysis['potential'];
                                            ?>
                                            <span class="tew-potential tew-potential--<?php echo esc_attr( $potential ); ?>">
                                                <?php echo esc_html( $potential_labels[ $potential ] ?? $potential ); ?>
                                            </span>
                                        </td>
                                        <td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $analysis['generated_at'] ) ) ); ?></td>
                                        <td>
                                            <a href="<?php echo esc_url( $analysis['permalink'] ); ?>" class="button button-small" target="_blank">
                                                <?php esc_html_e( 'Ver informe', 'test-eco-website' ); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Obtiene la clase CSS según el score.
     */
    private function get_score_class( $score ) {
        if ( $score >= 75 ) return 'excellent';
        if ( $score >= 50 ) return 'good';
        if ( $score >= 25 ) return 'attention';
        return 'critical';
    }

    /**
     * Renderiza la página de Casos de Éxito.
     */
    public function render_success_cases() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Procesar formulario de creación de caso de éxito
        if ( isset( $_POST['tew_create_success_case'] ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'tew_create_success_case' ) ) {
            $after_id  = isset( $_POST['after_report_id'] ) ? absint( $_POST['after_report_id'] ) : 0;
            $before_id = isset( $_POST['before_report_id'] ) ? absint( $_POST['before_report_id'] ) : 0;
            $client    = isset( $_POST['client_name'] ) ? sanitize_text_field( $_POST['client_name'] ) : '';
            $testimonial = isset( $_POST['testimonial'] ) ? wp_kses_post( $_POST['testimonial'] ) : '';

            if ( $after_id && $before_id ) {
                $result = $this->storage->mark_as_success_case( $after_id, $before_id, $client, $testimonial );
                if ( ! is_wp_error( $result ) ) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Caso de éxito creado correctamente.', 'test-eco-website' ) . '</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
                }
            }
        }

        // Procesar hacer público/privado
        if ( isset( $_POST['tew_toggle_public'] ) && isset( $_POST['report_id'] ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'tew_toggle_public_' . $_POST['report_id'] ) ) {
            $report_id = absint( $_POST['report_id'] );
            $is_public = isset( $_POST['make_public'] ) ? (bool) $_POST['make_public'] : false;

            if ( $is_public ) {
                $this->storage->make_public( $report_id );
            } else {
                $this->storage->make_private( $report_id );
            }
        }

        // Obtener todos los informes para los selectores
        $all_reports_data = $this->storage->get_all_analyses( [ 'posts_per_page' => 200 ] );
        
        // Filtrar y preparar informes para el dropdown
        $all_reports = [];
        foreach ( $all_reports_data as $report ) {
            // Solo incluir informes completos (con URL y fecha)
            if ( empty( $report['url'] ) || empty( $report['generated_at'] ) ) {
                continue;
            }
            
            // Añadir campo 'date' para el dropdown
            $report['date'] = $report['generated_at'];
            $all_reports[] = $report;
        }
        
        $success_cases = $this->storage->get_success_cases( 100 );

        ?>
        <div class="wrap tew-admin">
            <h1 class="wp-heading-inline">
                <span class="material-icons-round">emoji_events</span>
                <?php esc_html_e( 'Casos de Éxito', 'test-eco-website' ); ?>
            </h1>
            <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Report_Storage::POST_TYPE ) ); ?>" class="page-title-action">
                <span class="dashicons dashicons-plus-alt" style="vertical-align: middle;"></span>
                <?php esc_html_e( 'Crear Informe Manual', 'test-eco-website' ); ?>
            </a>
            <hr class="wp-header-end">

            <div class="notice notice-info" style="margin-top: 20px;">
                <p>
                    <strong><?php esc_html_e( '💡 Tip:', 'test-eco-website' ); ?></strong>
                    <?php esc_html_e( 'Si no tienes acceso al sitio "antes" para hacer un análisis real, puedes crear un informe manual con valores estimados. Haz clic en "Crear Informe Manual" arriba y rellena los campos manualmente.', 'test-eco-website' ); ?>
                </p>
            </div>

            <div class="tew-success-admin">
                <!-- Formulario de creación -->
                <div class="tew-success-form">
                    <h2><?php esc_html_e( 'Crear Caso de Éxito', 'test-eco-website' ); ?></h2>
                    <form method="post">
                        <?php wp_nonce_field( 'tew_create_success_case' ); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="before_report_id"><?php esc_html_e( 'Informe "ANTES"', 'test-eco-website' ); ?></label>
                                </th>
                                <td>
                                    <select name="before_report_id" id="before_report_id" class="regular-text" required>
                                        <option value=""><?php esc_html_e( 'Selecciona el informe antes de la migración', 'test-eco-website' ); ?></option>
                                        <?php foreach ( $all_reports as $report ) : ?>
                                            <option value="<?php echo esc_attr( $report['id'] ); ?>">
                                                <?php echo esc_html( $report['url'] ); ?> - Score: <?php echo esc_html( number_format_i18n( $report['score'], 0 ) ); ?> (<?php echo esc_html( wp_date( 'Y-m-d H:i', strtotime( $report['date'] ) ) ); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="after_report_id"><?php esc_html_e( 'Informe "DESPUÉS"', 'test-eco-website' ); ?></label>
                                </th>
                                <td>
                                    <select name="after_report_id" id="after_report_id" class="regular-text" required>
                                        <option value=""><?php esc_html_e( 'Selecciona el informe después de la migración', 'test-eco-website' ); ?></option>
                                        <?php foreach ( $all_reports as $report ) : ?>
                                            <option value="<?php echo esc_attr( $report['id'] ); ?>">
                                                <?php echo esc_html( $report['url'] ); ?> - Score: <?php echo esc_html( number_format_i18n( $report['score'], 0 ) ); ?> (<?php echo esc_html( wp_date( 'Y-m-d H:i', strtotime( $report['date'] ) ) ); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="client_name"><?php esc_html_e( 'Nombre del Cliente', 'test-eco-website' ); ?></label>
                                </th>
                                <td>
                                    <input type="text" name="client_name" id="client_name" class="regular-text" placeholder="<?php esc_attr_e( 'Ej: Empresa XYZ', 'test-eco-website' ); ?>">
                                    <p class="description"><?php esc_html_e( 'Opcional. Si no se especifica, se usará el dominio.', 'test-eco-website' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="testimonial"><?php esc_html_e( 'Testimonio', 'test-eco-website' ); ?></label>
                                </th>
                                <td>
                                    <textarea name="testimonial" id="testimonial" rows="4" class="large-text" placeholder="<?php esc_attr_e( 'Testimonio del cliente sobre la mejora...', 'test-eco-website' ); ?>"></textarea>
                                    <p class="description"><?php esc_html_e( 'Opcional. Testimonio que aparecerá en la card del caso de éxito.', 'test-eco-website' ); ?></p>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <button type="submit" name="tew_create_success_case" class="button button-primary">
                                <span class="material-icons-round">add_circle</span>
                                <?php esc_html_e( 'Crear Caso de Éxito', 'test-eco-website' ); ?>
                            </button>
                        </p>
                    </form>
                </div>

                <!-- Lista de casos de éxito existentes -->
                <div class="tew-success-list">
                    <h2><?php esc_html_e( 'Casos de Éxito Creados', 'test-eco-website' ); ?></h2>
                    
                    <?php if ( empty( $success_cases ) ) : ?>
                        <p class="tew-empty-state">
                            <span class="material-icons-round">info</span>
                            <?php esc_html_e( 'No hay casos de éxito todavía. Crea uno usando el formulario de arriba.', 'test-eco-website' ); ?>
                        </p>
                    <?php else : ?>
                        <div class="tew-success-cards">
                            <?php foreach ( $success_cases as $case ) : 
                                $before = $case['before'];
                                $after  = $case['after'];
                                $improvements = $case['improvements'];
                                $after_id = isset( $case['after_id'] ) ? $case['after_id'] : 0;
                                $report_url = isset( $after['metadata']['report_url'] ) ? $after['metadata']['report_url'] : '#';
                            ?>
                                <div class="tew-success-card-admin">
                                    <div class="tew-success-card__header">
                                        <h3><?php echo esc_html( $case['client_name'] ?: Utils::get_domain( $after['url'] ) ); ?></h3>
                                        <span class="tew-badge-success">
                                            <span class="material-icons-round">emoji_events</span>
                                            <?php esc_html_e( 'Caso de Éxito', 'test-eco-website' ); ?>
                                        </span>
                                    </div>

                                    <div class="tew-success-card__stats">
                                        <div class="tew-stat-row">
                                            <strong><?php esc_html_e( 'Score:', 'test-eco-website' ); ?></strong>
                                            <?php echo number_format_i18n( $improvements['score']['before'], 0 ); ?> → 
                                            <?php echo number_format_i18n( $improvements['score']['after'], 0 ); ?>
                                            <span class="tew-improvement-badge">
                                                <?php echo $improvements['score']['diff'] > 0 ? '+' : ''; ?><?php echo number_format_i18n( $improvements['score']['diff'], 0 ); ?> pts
                                            </span>
                                        </div>

                                        <div class="tew-stat-row">
                                            <strong><?php esc_html_e( 'CO₂:', 'test-eco-website' ); ?></strong>
                                            <?php echo number_format_i18n( $improvements['co2']['before'], 2 ); ?>g → 
                                            <?php echo number_format_i18n( $improvements['co2']['after'], 2 ); ?>g
                                            <span class="tew-improvement-badge tew-improvement-badge--green">
                                                <?php echo number_format_i18n( $improvements['co2']['percent'], 1 ); ?>%
                                            </span>
                                        </div>

                                        <?php if ( $improvements['green_hosting']['improved'] ) : ?>
                                            <div class="tew-stat-row">
                                                <span class="material-icons-round">verified</span>
                                                <?php esc_html_e( 'Migrado a Hosting Verde', 'test-eco-website' ); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ( ! empty( $case['testimonial'] ) ) : ?>
                                        <div class="tew-success-card__testimonial">
                                            <?php echo wp_kses_post( $case['testimonial'] ); ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="tew-success-card__actions">
                                        <a href="<?php echo esc_url( $report_url ); ?>" class="button button-small" target="_blank">
                                            <?php esc_html_e( 'Ver informe', 'test-eco-website' ); ?>
                                        </a>
                                        <?php if ( $after_id > 0 ) : ?>
                                            <button 
                                                type="button" 
                                                class="button button-small tew-delete-success-case" 
                                                data-case-id="<?php echo esc_attr( $after_id ); ?>"
                                                data-nonce="<?php echo esc_attr( wp_create_nonce( 'tew_delete_success_case_' . $after_id ) ); ?>">
                                                <span class="material-icons-round">delete</span>
                                                <?php esc_html_e( 'Eliminar caso', 'test-eco-website' ); ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handler AJAX para eliminar un caso de éxito.
     */
    public function ajax_delete_success_case() {
        // Log forzado en archivo separado
        file_put_contents( WP_CONTENT_DIR . '/tew-debug.log', '[' . date('Y-m-d H:i:s') . '] TEW AJAX - Inicio ajax_delete_success_case' . PHP_EOL, FILE_APPEND );
        file_put_contents( WP_CONTENT_DIR . '/tew-debug.log', '[' . date('Y-m-d H:i:s') . '] TEW AJAX - POST: ' . print_r( $_POST, true ) . PHP_EOL, FILE_APPEND );
        
        // Verificar nonce
        if ( ! isset( $_POST['nonce'] ) || ! isset( $_POST['case_id'] ) ) {
            file_put_contents( WP_CONTENT_DIR . '/tew-debug.log', '[' . date('Y-m-d H:i:s') . '] TEW AJAX - ERROR: Faltan nonce o case_id' . PHP_EOL, FILE_APPEND );
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

        // Verificar permisos
        if ( ! \current_user_can( 'manage_options' ) ) {
            file_put_contents( WP_CONTENT_DIR . '/tew-debug.log', '[' . date('Y-m-d H:i:s') . '] TEW AJAX - ERROR: Sin permisos' . PHP_EOL, FILE_APPEND );
            \wp_send_json_error( [ 'message' => __( 'No tienes permisos para esta acción', 'test-eco-website' ) ] );
        }

        // Eliminar el caso de éxito
        file_put_contents( WP_CONTENT_DIR . '/tew-debug.log', '[' . date('Y-m-d H:i:s') . '] TEW AJAX - Llamando a delete_success_case' . PHP_EOL, FILE_APPEND );
        $result = $this->storage->delete_success_case( $case_id );
        file_put_contents( WP_CONTENT_DIR . '/tew-debug.log', '[' . date('Y-m-d H:i:s') . '] TEW AJAX - Resultado: ' . ( $result ? 'TRUE' : 'FALSE' ) . PHP_EOL, FILE_APPEND );

        if ( $result ) {
            file_put_contents( WP_CONTENT_DIR . '/tew-debug.log', '[' . date('Y-m-d H:i:s') . '] TEW AJAX - Enviando SUCCESS' . PHP_EOL, FILE_APPEND );
            \wp_send_json_success( [ 
                'message' => __( 'Caso de éxito eliminado correctamente', 'test-eco-website' ),
                'case_id' => $case_id,
            ] );
        } else {
            file_put_contents( WP_CONTENT_DIR . '/tew-debug.log', '[' . date('Y-m-d H:i:s') . '] TEW AJAX - Enviando ERROR' . PHP_EOL, FILE_APPEND );
            \wp_send_json_error( [ 'message' => __( 'No se pudo eliminar el caso de éxito', 'test-eco-website' ) ] );
        }
    }
}
