<?php
namespace TEW;

use function __;
use function absint;
use function add_settings_field;
use function add_settings_section;
use function checked;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_attr_e;
use function get_option;
use function register_setting;
use function sanitize_text_field;
use function wp_parse_args;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Settings {

    const OPTION_NAME = 'tew_settings';

    const NONCE_ACTION = 'tew_settings_save';

    /**
     * Valores por defecto.
     *
     * @return array
     */
    public static function defaults() {
        return [
            'pagespeed_api_key'  => '',
            'auto_refresh_hours' => 24,
            'enable_logging'     => false,
            'sandbox_mode'       => false,
            'storage_mode'       => 'legacy',
        ];
    }

    /**
     * Registra los ajustes y campos mediante la Settings API.
     */
    public function register() {
        register_setting( 'tew_settings_group', self::OPTION_NAME, [ $this, 'sanitize' ] );

        add_settings_section(
            'tew_api_section',
            __( 'Credenciales de APIs', 'test-eco-website' ),
            function () {
                echo '<p>' . esc_html__( 'Introduce la clave de PageSpeed (obligatoria). Website Carbon y Green Web se consultan sin credenciales y alimentan el Eco Snapshot Score.', 'test-eco-website' ) . '</p>';
            },
            'tew_settings'
        );

        add_settings_field(
            'pagespeed_api_key',
            __( 'PageSpeed Insights API Key', 'test-eco-website' ),
            [ $this, 'render_text_field' ],
            'tew_settings',
            'tew_api_section',
            [
                'name'        => 'pagespeed_api_key',
                'placeholder' => __( 'AIza... ', 'test-eco-website' ),
                'description' => __( 'Obtén tu clave desde Google Cloud Console. Necesaria para Lighthouse y métricas de rendimiento.', 'test-eco-website' ),
            ]
        );

        add_settings_section(
            'tew_preferences_section',
            __( 'Preferencias de auditoría', 'test-eco-website' ),
            function () {
                echo '<p>' . esc_html__( 'Controla la frecuencia de refresco y opciones de depuración.', 'test-eco-website' ) . '</p>';
            },
            'tew_settings'
        );

        add_settings_field(
            'auto_refresh_hours',
            __( 'Horas para refrescar cache', 'test-eco-website' ),
            [ $this, 'render_number_field' ],
            'tew_settings',
            'tew_preferences_section',
            [
                'name'        => 'auto_refresh_hours',
                'min'         => 1,
                'max'         => 168,
                'step'        => 1,
                'description' => __( 'Determina cada cuánto forzar una nueva consulta a las APIs.', 'test-eco-website' ),
            ]
        );

        add_settings_field(
            'sandbox_mode',
            __( 'Modo sandbox', 'test-eco-website' ),
            [ $this, 'render_toggle_field' ],
            'tew_settings',
            'tew_preferences_section',
            [
                'name'        => 'sandbox_mode',
                'label'       => __( 'Usar datos de prueba y evitar llamadas reales.', 'test-eco-website' ),
            ]
        );

        add_settings_field(
            'enable_logging',
            __( 'Activar registro', 'test-eco-website' ),
            [ $this, 'render_toggle_field' ],
            'tew_settings',
            'tew_preferences_section',
            [
                'name'        => 'enable_logging',
                'label'       => __( 'Guardar incidencias en el log para diagnóstico rápido.', 'test-eco-website' ),
            ]
        );

        add_settings_field(
            'storage_mode',
            __( 'Modo de almacenamiento', 'test-eco-website' ),
            [ $this, 'render_select_field' ],
            'tew_settings',
            'tew_preferences_section',
            [
                'name'        => 'storage_mode',
                'options'     => [
                    'legacy'     => __( 'Legacy (solo CPT + postmeta)', 'test-eco-website' ),
                    'dual_write' => __( 'Dual Write (legacy + tabla custom)', 'test-eco-website' ),
                    'custom_read'=> __( 'Lectura custom + fallback legacy', 'test-eco-website' ),
                ],
                'description' => __( 'Permite migrar por fases sin downtime. Recomendado: legacy -> dual_write -> custom_read.', 'test-eco-website' ),
            ]
        );
    }

    /**
     * Limpia y normaliza los valores antes de guardarlos.
     *
     * @param array $raw Valores crudos.
     *
     * @return array
     */
    public function sanitize( $raw ) {
        $defaults = self::defaults();
        $clean    = wp_parse_args( (array) $raw, $defaults );

        $clean['pagespeed_api_key']  = sanitize_text_field( $clean['pagespeed_api_key'] );
        $clean['auto_refresh_hours']    = max( 1, min( 168, absint( $clean['auto_refresh_hours'] ) ) );
        $clean['sandbox_mode']          = ! empty( $raw['sandbox_mode'] );
        $clean['enable_logging']        = ! empty( $raw['enable_logging'] );
        $allowed_storage_modes          = [ 'legacy', 'dual_write', 'custom_read' ];
        $clean['storage_mode']          = in_array( $clean['storage_mode'], $allowed_storage_modes, true ) ? $clean['storage_mode'] : 'legacy';

        return $clean;
    }

    /**
     * Obtiene la configuración almacenada.
     *
     * @return array
     */
    public function all() {
        $values = get_option( self::OPTION_NAME, [] );

        return wp_parse_args( $values, self::defaults() );
    }

    /**
     * Renderiza un campo de texto.
     *
     * @param array $args Parámetros.
     */
    public function render_text_field( $args ) {
        $options     = $this->all();
        $name        = $args['name'];
        $value       = isset( $options[ $name ] ) ? $options[ $name ] : '';
        $placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';
        $description = isset( $args['description'] ) ? $args['description'] : '';

        printf(
            '<div class="tew-field tew-field--text"><input type="text" id="tew_%1$s" name="%2$s[%1$s]" value="%3$s" placeholder="%4$s" autocomplete="off" class="regular-text" /><p class="description">%5$s</p></div>',
            esc_attr( $name ),
            esc_attr( self::OPTION_NAME ),
            esc_attr( $value ),
            esc_attr( $placeholder ),
            esc_html( $description )
        );
    }

    /**
     * Renderiza un campo numérico.
     *
     * @param array $args Parámetros.
     */
    public function render_number_field( $args ) {
        $options     = $this->all();
        $name        = $args['name'];
        $value       = isset( $options[ $name ] ) ? $options[ $name ] : '';
        $description = isset( $args['description'] ) ? $args['description'] : '';
        $min         = isset( $args['min'] ) ? intval( $args['min'] ) : 0;
        $max         = isset( $args['max'] ) ? intval( $args['max'] ) : '';
        $step        = isset( $args['step'] ) ? intval( $args['step'] ) : 1;

        printf(
            '<div class="tew-field tew-field--number"><input type="number" id="tew_%1$s" name="%2$s[%1$s]" value="%3$s" min="%4$s" max="%5$s" step="%6$s" class="small-text" /><p class="description">%7$s</p></div>',
            esc_attr( $name ),
            esc_attr( self::OPTION_NAME ),
            esc_attr( $value ),
            esc_attr( $min ),
            esc_attr( $max ),
            esc_attr( $step ),
            esc_html( $description )
        );
    }

    /**
     * Renderiza un interruptor toggle.
     *
     * @param array $args Parámetros.
     */
    public function render_toggle_field( $args ) {
        $options = $this->all();
        $name    = $args['name'];
        $label   = isset( $args['label'] ) ? $args['label'] : '';
        $checked = ! empty( $options[ $name ] );

        printf(
            '<label class="tew-switch"><input type="checkbox" id="tew_%1$s" name="%2$s[%1$s]" value="1" %3$s /><span class="tew-switch__slider" aria-hidden="true"></span><span class="tew-switch__label">%4$s</span></label>',
            esc_attr( $name ),
            esc_attr( self::OPTION_NAME ),
            checked( $checked, true, false ),
            esc_html( $label )
        );
    }

    /**
     * Renderiza un select simple.
     *
     * @param array $args Parámetros.
     */
    public function render_select_field( $args ) {
        $options     = $this->all();
        $name        = $args['name'];
        $current     = isset( $options[ $name ] ) ? $options[ $name ] : '';
        $choices     = isset( $args['options'] ) ? (array) $args['options'] : [];
        $description = isset( $args['description'] ) ? $args['description'] : '';

        printf( '<div class="tew-field tew-field--select"><select id="tew_%1$s" name="%2$s[%1$s]">', esc_attr( $name ), esc_attr( self::OPTION_NAME ) );

        foreach ( $choices as $value => $label ) {
            printf(
                '<option value="%1$s" %2$s>%3$s</option>',
                esc_attr( $value ),
                \selected( $current, $value, false ),
                esc_html( $label )
            );
        }

        echo '</select>';

        if ( ! empty( $description ) ) {
            printf( '<p class="description">%s</p>', esc_html( $description ) );
        }

        echo '</div>';
    }
}
