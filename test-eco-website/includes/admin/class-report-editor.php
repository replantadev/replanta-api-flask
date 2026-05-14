<?php
namespace TEW\Admin;

use TEW\Reporting\Report_Storage;
use TEW\Utils;
use function __;
use function add_action;
use function add_meta_box;
use function current_user_can;
use function esc_attr;
use function esc_html;
use function esc_html_e;
use function get_post_meta;
use function sanitize_text_field;
use function update_post_meta;
use function wp_nonce_field;
use function wp_verify_nonce;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Report_Editor {

    /**
     * @var Report_Storage
     */
    private $storage;

    public function __construct() {
        $this->storage = new Report_Storage();
        add_action( 'add_meta_boxes', [ $this, 'register_metabox' ] );
        add_action( 'add_meta_boxes', [ $this, 'remove_unnecessary_metaboxes' ], 999 );
        add_action( 'save_post_' . Report_Storage::POST_TYPE, [ $this, 'save_metabox' ], 10, 2 );
        add_filter( 'manage_' . Report_Storage::POST_TYPE . '_posts_columns', [ $this, 'add_custom_columns' ] );
        add_action( 'manage_' . Report_Storage::POST_TYPE . '_posts_custom_column', [ $this, 'render_custom_columns' ], 10, 2 );
    }

    /**
     * Registra el metabox de edición manual.
     */
    public function register_metabox() {
        add_meta_box(
            'tew_manual_edit',
            __( 'Edición Manual de Métricas', 'test-eco-website' ),
            [ $this, 'render_metabox' ],
            Report_Storage::POST_TYPE,
            'normal',
            'high'
        );
    }

    /**
     * Renderiza el metabox de edición manual.
     */
    public function render_metabox( $post ) {
        if ( ! current_user_can( 'edit_post', $post->ID ) ) {
            return;
        }

        wp_nonce_field( 'tew_manual_edit_' . $post->ID, 'tew_manual_edit_nonce' );

        $url              = get_post_meta( $post->ID, Report_Storage::META_URL, true );
        $score            = get_post_meta( $post->ID, Report_Storage::META_SCORE, true );
        $grade            = get_post_meta( $post->ID, Report_Storage::META_GRADE, true );
        $is_green         = get_post_meta( $post->ID, Report_Storage::META_IS_GREEN, true );
        $hosting_provider = get_post_meta( $post->ID, Report_Storage::META_HOSTING_PROVIDER, true );
        $co2_per_view     = get_post_meta( $post->ID, Report_Storage::META_CO2_PER_VIEW, true );
        $generated_date   = get_post_meta( $post->ID, Report_Storage::META_GENERATED, true );
        
        // Convertir fecha a formato compatible con input[type="datetime-local"]
        $date_value = '';
        if ( ! empty( $generated_date ) ) {
            $timestamp = strtotime( $generated_date );
            if ( $timestamp ) {
                $date_value = date( 'Y-m-d\TH:i', $timestamp );
            }
        }

        ?>
        <div class="tew-manual-edit">
            <p class="description">
                <span class="material-icons-round" style="vertical-align: middle; color: #d97706;">warning</span>
                <?php esc_html_e( 'Edita estos valores manualmente solo si no puedes generar un análisis real. Esto es útil para clientes antiguos donde no tienes acceso al sitio "antes" de la migración.', 'test-eco-website' ); ?>
            </p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="tew_manual_url"><?php esc_html_e( 'URL del Sitio', 'test-eco-website' ); ?></label>
                    </th>
                    <td>
                        <input 
                            type="url" 
                            id="tew_manual_url" 
                            name="tew_manual_url" 
                            value="<?php echo esc_attr( $url ); ?>" 
                            class="large-text"
                            placeholder="https://ejemplo.com"
                        >
                        <p class="description"><?php esc_html_e( 'URL completa del sitio web analizado.', 'test-eco-website' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="tew_manual_date"><?php esc_html_e( 'Fecha del Informe', 'test-eco-website' ); ?></label>
                    </th>
                    <td>
                        <input 
                            type="datetime-local" 
                            id="tew_manual_date" 
                            name="tew_manual_date" 
                            value="<?php echo esc_attr( $date_value ); ?>" 
                            class="regular-text"
                        >
                        <p class="description"><?php esc_html_e( 'Fecha y hora en que se generó el informe (o la fecha que quieras registrar).', 'test-eco-website' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="tew_manual_score"><?php esc_html_e( 'Score Global', 'test-eco-website' ); ?></label>
                    </th>
                    <td>
                        <input 
                            type="number" 
                            id="tew_manual_score" 
                            name="tew_manual_score" 
                            value="<?php echo esc_attr( $score ); ?>" 
                            min="0" 
                            max="100" 
                            step="0.1"
                            class="small-text"
                        >
                        <p class="description"><?php esc_html_e( 'Puntuación global entre 0 y 100.', 'test-eco-website' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="tew_manual_grade"><?php esc_html_e( 'Grade (Calificación)', 'test-eco-website' ); ?></label>
                    </th>
                    <td>
                        <select id="tew_manual_grade" name="tew_manual_grade">
                            <option value="A+" <?php selected( $grade, 'A+' ); ?>>A+</option>
                            <option value="A" <?php selected( $grade, 'A' ); ?>>A</option>
                            <option value="B" <?php selected( $grade, 'B' ); ?>>B</option>
                            <option value="C" <?php selected( $grade, 'C' ); ?>>C</option>
                            <option value="D" <?php selected( $grade, 'D' ); ?>>D</option>
                            <option value="E" <?php selected( $grade, 'E' ); ?>>E</option>
                            <option value="F" <?php selected( $grade, 'F' ); ?>>F</option>
                        </select>
                        <p class="description"><?php esc_html_e( 'Calificación basada en el score.', 'test-eco-website' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="tew_manual_co2"><?php esc_html_e( 'CO₂ por Visita (gramos)', 'test-eco-website' ); ?></label>
                    </th>
                    <td>
                        <input 
                            type="number" 
                            id="tew_manual_co2" 
                            name="tew_manual_co2" 
                            value="<?php echo esc_attr( $co2_per_view ); ?>" 
                            min="0" 
                            step="0.01"
                            class="small-text"
                            placeholder="0.50"
                        >
                        <span>gramos</span>
                        <p class="description">
                            <?php esc_html_e( 'Emisiones de CO₂ estimadas por cada visita.', 'test-eco-website' ); ?>
                            <br>
                            <strong><?php esc_html_e( 'Referencia:', 'test-eco-website' ); ?></strong> 
                            <?php esc_html_e( 'Sitios optimizados: 0.2-0.5g | Promedio: 0.5-1.0g | No optimizados: 1.0-2.5g', 'test-eco-website' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="tew_manual_is_green"><?php esc_html_e( 'Hosting Verde', 'test-eco-website' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input 
                                type="checkbox" 
                                id="tew_manual_is_green" 
                                name="tew_manual_is_green" 
                                value="1"
                                <?php checked( $is_green, '1' ); ?>
                            >
                            <?php esc_html_e( 'El sitio está alojado en hosting verde', 'test-eco-website' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Marca esta casilla si el sitio usa energía renovable.', 'test-eco-website' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="tew_manual_provider"><?php esc_html_e( 'Proveedor de Hosting', 'test-eco-website' ); ?></label>
                    </th>
                    <td>
                        <input 
                            type="text" 
                            id="tew_manual_provider" 
                            name="tew_manual_provider" 
                            value="<?php echo esc_attr( $hosting_provider ); ?>" 
                            class="regular-text"
                            placeholder="Ej: Replanta, SiteGround, etc."
                        >
                        <p class="description"><?php esc_html_e( 'Nombre del proveedor de hosting (opcional).', 'test-eco-website' ); ?></p>
                    </td>
                </tr>
            </table>

            <div class="tew-manual-edit__actions">
                <p class="submit">
                    <button type="submit" class="button button-primary button-large">
                        <span class="dashicons dashicons-saved" style="margin-top: 4px;"></span>
                        <?php esc_html_e( 'Guardar Cambios Manuales', 'test-eco-website' ); ?>
                    </button>
                </p>
            </div>
        </div>

        <style>
            .tew-manual-edit {
                padding: 20px;
                background: #f9fafb;
                border: 2px solid #e5e7eb;
                border-radius: 8px;
            }
            .tew-manual-edit .description {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 12px 16px;
                background: #fef3c7;
                border-left: 4px solid #f59e0b;
                border-radius: 4px;
                margin-bottom: 20px;
            }
            .tew-manual-edit table.form-table th {
                padding: 16px 10px 16px 0;
            }
            .tew-manual-edit table.form-table td {
                padding: 16px 10px;
            }
            .tew-manual-edit__actions {
                margin-top: 20px;
                padding-top: 20px;
                border-top: 1px solid #e5e7eb;
            }
        </style>
        <?php
    }

    /**
     * Guarda los valores del metabox.
     */
    public function save_metabox( $post_id, $post ) {
        // Verificar nonce
        if ( ! isset( $_POST['tew_manual_edit_nonce'] ) || ! \wp_verify_nonce( $_POST['tew_manual_edit_nonce'], 'tew_manual_edit_' . $post_id ) ) {
            return;
        }

        // Verificar permisos
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Evitar auto-save
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // CRÍTICO: Evitar loop infinito
        if ( defined( 'TEW_SAVING' ) && TEW_SAVING ) {
            return;
        }
        define( 'TEW_SAVING', true );

        // Recoger valores del formulario
        $url      = isset( $_POST['tew_manual_url'] ) ? \esc_url_raw( $_POST['tew_manual_url'] ) : '';
        $score    = isset( $_POST['tew_manual_score'] ) ? floatval( $_POST['tew_manual_score'] ) : 0;
        $grade    = isset( $_POST['tew_manual_grade'] ) ? sanitize_text_field( $_POST['tew_manual_grade'] ) : 'F';
        $co2      = isset( $_POST['tew_manual_co2'] ) ? floatval( $_POST['tew_manual_co2'] ) : 0;
        $is_green = isset( $_POST['tew_manual_is_green'] );
        $provider = isset( $_POST['tew_manual_provider'] ) ? sanitize_text_field( $_POST['tew_manual_provider'] ) : '';
        
        // Procesar fecha: convertir de datetime-local a formato MySQL
        $date = '';
        if ( isset( $_POST['tew_manual_date'] ) && ! empty( $_POST['tew_manual_date'] ) ) {
            // El input datetime-local devuelve formato: 2025-10-20T14:30
            $date_input = sanitize_text_field( $_POST['tew_manual_date'] );
            $timestamp = strtotime( $date_input );
            if ( $timestamp ) {
                // Convertir a formato MySQL UTC: 2025-10-20 14:30:00
                $date = gmdate( 'Y-m-d H:i:s', $timestamp );
            }
        }
        // Si no se especificó fecha, usar la actual
        if ( empty( $date ) ) {
            $date = \current_time( 'mysql', true );
        }

        // Validar URL obligatoria
        if ( empty( $url ) ) {
            return;
        }

        // Verificar si necesita payload inicial
        $payload = get_post_meta( $post_id, Report_Storage::META_PAYLOAD, true );
        if ( empty( $payload ) ) {
            $basic_payload = $this->create_basic_payload( $post_id, $url );
            update_post_meta( $post_id, Report_Storage::META_PAYLOAD, \wp_slash( $basic_payload ) );
        }

        // Actualizar metadatos individuales
        update_post_meta( $post_id, Report_Storage::META_URL, $url );
        update_post_meta( $post_id, Report_Storage::META_SCORE, $score );
        update_post_meta( $post_id, Report_Storage::META_GRADE, $grade );
        update_post_meta( $post_id, Report_Storage::META_CO2_PER_VIEW, $co2 );
        update_post_meta( $post_id, Report_Storage::META_IS_GREEN, $is_green );
        update_post_meta( $post_id, Report_Storage::META_HOSTING_PROVIDER, $provider );
        update_post_meta( $post_id, Report_Storage::META_GENERATED, $date );

        // Calcular potencial
        $potential = $is_green ? ( 100 - $score ) : ( 100 - $score + 30 );
        update_post_meta( $post_id, Report_Storage::META_POTENTIAL, min( max( $potential, 0 ), 100 ) );

        // Actualizar payload en batch
        $this->update_payload_bulk( $post_id, [
            'url'                                    => $url,
            'summary.overall_score'                  => $score,
            'summary.grade'                          => $grade,
            'metrics.carbon.co2_per_view'            => $co2,
            'metrics.green_hosting.is_green'         => $is_green,
            'metrics.green_hosting.hosting_provider' => $provider,
        ] );

        // Actualizar título del post SIN wp_update_post (evita loop)
        remove_action( 'save_post_' . Report_Storage::POST_TYPE, [ $this, 'save_metabox' ], 10 );
        
        $domain = Utils::get_domain( $url );
        global $wpdb;
        $wpdb->update(
            $wpdb->posts,
            [ 'post_title' => sprintf( __( 'Informe %s · Manual', 'test-eco-website' ), $domain ) ],
            [ 'ID' => $post_id ],
            [ '%s' ],
            [ '%d' ]
        );
        
        add_action( 'save_post_' . Report_Storage::POST_TYPE, [ $this, 'save_metabox' ], 10, 2 );
    }

    /**
     * Actualiza múltiples valores en el payload en una sola operación.
     */
    private function update_payload_bulk( $post_id, $updates ) {
        $payload = get_post_meta( $post_id, Report_Storage::META_PAYLOAD, true );
        
        if ( empty( $payload ) ) {
            return;
        }

        $decoded = json_decode( $payload, true );
        if ( null === $decoded ) {
            return;
        }

        // Aplicar todas las actualizaciones
        foreach ( $updates as $path => $value ) {
            $keys = explode( '.', $path );
            $current = &$decoded;
            
            for ( $i = 0; $i < count( $keys ) - 1; $i++ ) {
                $key = $keys[ $i ];
                if ( ! isset( $current[ $key ] ) ) {
                    $current[ $key ] = [];
                }
                $current = &$current[ $key ];
            }
            
            $current[ $keys[ count( $keys ) - 1 ] ] = $value;
        }

        // Guardar una sola vez
        $encoded = wp_json_encode( $decoded );
        if ( false !== $encoded ) {
            update_post_meta( $post_id, Report_Storage::META_PAYLOAD, wp_slash( $encoded ) );
        }
    }

    /**
     * Actualiza un valor dentro del payload JSON.
     */
    private function update_payload_value( $post_id, $path, $value ) {
        $payload = get_post_meta( $post_id, Report_Storage::META_PAYLOAD, true );
        
        if ( empty( $payload ) ) {
            // Crear payload básico si no existe
            $payload = $this->create_basic_payload( $post_id );
        }

        $decoded = json_decode( $payload, true );
        if ( null === $decoded ) {
            return;
        }

        // Navegar por el path y actualizar el valor
        $keys = explode( '.', $path );
        $current = &$decoded;
        
        for ( $i = 0; $i < count( $keys ) - 1; $i++ ) {
            $key = $keys[ $i ];
            if ( ! isset( $current[ $key ] ) ) {
                $current[ $key ] = [];
            }
            $current = &$current[ $key ];
        }
        
        $current[ $keys[ count( $keys ) - 1 ] ] = $value;

        // Guardar payload actualizado
        $encoded = wp_json_encode( $decoded );
        if ( false !== $encoded ) {
            update_post_meta( $post_id, Report_Storage::META_PAYLOAD, wp_slash( $encoded ) );
        }
    }

    /**
     * Crea un payload básico para informes manuales.
     */
    private function create_basic_payload( $post_id, $url = '' ) {
        if ( empty( $url ) ) {
            $url = get_post_meta( $post_id, Report_Storage::META_URL, true );
        }
        
        $basic = [
            'url'      => $url,
            'summary'  => [
                'overall_score' => 0,
                'grade'         => 'F',
            ],
            'metrics'  => [
                'carbon'        => [
                    'co2_per_view' => 0,
                ],
                'green_hosting' => [
                    'is_green'         => false,
                    'hosting_provider' => '',
                ],
            ],
            'metadata' => [
                'generated_at' => current_time( 'mysql', true ),
                'manual'       => true,
            ],
        ];

        return wp_json_encode( $basic );
    }

    /**
     * Elimina metaboxes innecesarios del post type tew_audit.
     */
    public function remove_unnecessary_metaboxes() {
        // Eliminar metaboxes de plugins/temas
        \remove_meta_box( 'rank_math_metabox', Report_Storage::POST_TYPE, 'normal' ); // Rank Math
        \remove_meta_box( 'postimagediv', Report_Storage::POST_TYPE, 'side' );        // Imagen destacada
        \remove_meta_box( 'commentstatusdiv', Report_Storage::POST_TYPE, 'normal' );  // Comentarios
        \remove_meta_box( 'commentsdiv', Report_Storage::POST_TYPE, 'normal' );       // Comentarios
        \remove_meta_box( 'slugdiv', Report_Storage::POST_TYPE, 'normal' );           // Slug
        \remove_meta_box( 'trackbacksdiv', Report_Storage::POST_TYPE, 'normal' );     // Trackbacks
        \remove_meta_box( 'authordiv', Report_Storage::POST_TYPE, 'normal' );         // Autor
        \remove_meta_box( 'revisionsdiv', Report_Storage::POST_TYPE, 'normal' );      // Revisiones
        
        // Elementor
        \remove_meta_box( 'elementor-editor', Report_Storage::POST_TYPE, 'normal' );
    }

    /**
     * Añade columnas personalizadas a la lista de informes.
     */
    public function add_custom_columns( $columns ) {
        $new_columns = [];
        
        foreach ( $columns as $key => $label ) {
            $new_columns[ $key ] = $label;
            
            // Después del título, añadir nuestras columnas
            if ( 'title' === $key ) {
                $new_columns['tew_url']       = __( 'URL', 'test-eco-website' );
                $new_columns['tew_score']     = __( 'Score', 'test-eco-website' );
                $new_columns['tew_co2']       = __( 'CO₂/visita', 'test-eco-website' );
                $new_columns['tew_green']     = __( 'Hosting Verde', 'test-eco-website' );
                $new_columns['tew_generated'] = __( 'Fecha Análisis', 'test-eco-website' );
            }
        }
        
        // Quitar columna de fecha por defecto
        unset( $new_columns['date'] );
        
        return $new_columns;
    }

    /**
     * Renderiza el contenido de las columnas personalizadas.
     */
    public function render_custom_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'tew_url':
                $url = get_post_meta( $post_id, Report_Storage::META_URL, true );
                if ( $url ) {
                    echo '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( Utils::get_domain( $url ) ) . '</a>';
                } else {
                    echo '—';
                }
                break;

            case 'tew_score':
                $score = get_post_meta( $post_id, Report_Storage::META_SCORE, true );
                $grade = get_post_meta( $post_id, Report_Storage::META_GRADE, true );
                if ( $score ) {
                    echo '<strong>' . number_format( $score, 0 ) . '</strong> <span style="color: #666;">(' . esc_html( $grade ) . ')</span>';
                } else {
                    echo '—';
                }
                break;

            case 'tew_co2':
                $co2 = get_post_meta( $post_id, Report_Storage::META_CO2_PER_VIEW, true );
                if ( $co2 ) {
                    echo number_format( $co2, 2 ) . 'g';
                } else {
                    echo '—';
                }
                break;

            case 'tew_green':
                $is_green = get_post_meta( $post_id, Report_Storage::META_IS_GREEN, true );
                if ( $is_green ) {
                    echo '<span style="color: #2d5c3f;">✓ Verde</span>';
                } else {
                    echo '<span style="color: #999;">✗ No verde</span>';
                }
                break;

            case 'tew_generated':
                $generated = get_post_meta( $post_id, Report_Storage::META_GENERATED, true );
                if ( $generated ) {
                    echo \wp_date( 'Y-m-d H:i', strtotime( $generated ) );
                } else {
                    echo '—';
                }
                break;
        }
    }
}