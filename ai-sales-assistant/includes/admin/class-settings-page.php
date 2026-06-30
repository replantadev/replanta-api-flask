<?php

namespace Replanta\AiChat\Admin;

use Replanta\AiChat\Options;
use Replanta\AiChat\Updater;

defined( 'ABSPATH' ) || exit;

class SettingsPage {

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Save
        if ( isset( $_POST['replanta_ai_chat_save'] ) && check_admin_referer( 'replanta_ai_chat_settings' ) ) {
            self::save();
            echo '<div class="notice notice-success is-dismissible"><p>'
                . esc_html__( 'Ajustes guardados.', 'replanta-ai-chat' )
                . '</p></div>';
        }

        $tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
        $tabs = self::tabs();
        ?>
        <div class="wrap replanta-settings">
            <h1><?php esc_html_e( 'Replanta AI Chat', 'replanta-ai-chat' ); ?></h1>

            <nav class="nav-tab-wrapper">
                <?php foreach ( $tabs as $slug => $label ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'tab', $slug ) ); ?>"
                       class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html( $label ); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <form method="post" action="">
                <?php wp_nonce_field( 'replanta_ai_chat_settings' ); ?>
                <div class="replanta-tab-content">
                    <?php
                    match ( $tab ) {
                        'provider'  => self::tab_provider(),
                        'indexing'  => self::tab_indexing(),
                        'behaviour' => self::tab_behaviour(),
                        'tools'     => self::tab_tools(),
                        'license'   => self::tab_license(),
                        default     => self::tab_general(),
                    };
                    ?>
                </div>
                <?php if ( 'license' !== $tab ) : ?>
                    <p class="submit">
                        <button type="submit" name="replanta_ai_chat_save" class="button button-primary">
                            <?php esc_html_e( 'Guardar cambios', 'replanta-ai-chat' ); ?>
                        </button>
                    </p>
                <?php endif; ?>
            </form>
        </div>
        <?php
    }

    // ── Tabs ──────────────────────────────────────────────────────────────────

    private static function tabs(): array {
        return [
            'general'   => __( 'General', 'replanta-ai-chat' ),
            'provider'  => __( 'Proveedor IA', 'replanta-ai-chat' ),
            'indexing'  => __( 'Campos ACF/Meta', 'replanta-ai-chat' ),
            'behaviour' => __( 'Comportamiento', 'replanta-ai-chat' ),
            'tools'     => __( 'Herramientas', 'replanta-ai-chat' ),
            'license'   => __( 'Licencia', 'replanta-ai-chat' ),
        ];
    }

    // ── Tab: General ──────────────────────────────────────────────────────────

    private static function tab_general(): void {
        $o = Options::get_general();
        ?>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'Nombre del asistente', 'replanta-ai-chat' ); ?></th>
                <td>
                    <input type="text" name="general[assistant_name]"
                           value="<?php echo esc_attr( $o['assistant_name'] ); ?>" class="regular-text" />
                    <p class="description"><?php esc_html_e( 'Visible para el cliente en la cabecera del chat.', 'replanta-ai-chat' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Mensaje de bienvenida', 'replanta-ai-chat' ); ?></th>
                <td>
                    <input type="text" name="general[welcome_message]"
                           value="<?php echo esc_attr( $o['welcome_message'] ); ?>" class="large-text" />
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Posición del widget', 'replanta-ai-chat' ); ?></th>
                <td>
                    <select name="general[widget_position]">
                        <?php foreach ( [ 'bottom-right' => __( 'Inferior derecha', 'replanta-ai-chat' ), 'bottom-left' => __( 'Inferior izquierda', 'replanta-ai-chat' ) ] as $val => $label ) : ?>
                            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $o['widget_position'], $val ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Color principal', 'replanta-ai-chat' ); ?></th>
                <td>
                    <input type="color" name="general[primary_color]"
                           value="<?php echo esc_attr( $o['primary_color'] ); ?>" />
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Mostrar en', 'replanta-ai-chat' ); ?></th>
                <td>
                    <select name="general[show_on]">
                        <?php
                        $options = [
                            'all'     => __( 'Todo el sitio', 'replanta-ai-chat' ),
                            'shop'    => __( 'Solo tienda y categorías', 'replanta-ai-chat' ),
                            'product' => __( 'Solo fichas de producto', 'replanta-ai-chat' ),
                            'cart'    => __( 'Solo carrito y checkout', 'replanta-ai-chat' ),
                        ];
                        foreach ( $options as $val => $label ) : ?>
                            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $o['show_on'], $val ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Chat activo', 'replanta-ai-chat' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="general[chat_enabled]" value="1"
                               <?php checked( ! empty( $o['chat_enabled'] ) ); ?> />
                        <?php esc_html_e( 'Mostrar el widget en el frontend', 'replanta-ai-chat' ); ?>
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }

    // ── Tab: Proveedor IA ─────────────────────────────────────────────────────

    private static function tab_provider(): void {
        $o = Options::get_provider();
        ?>
        <h2><?php esc_html_e( 'Claves API', 'replanta-ai-chat' ); ?></h2>
        <p class="description">
            <?php esc_html_e( 'Las claves se guardan cifradas en la base de datos. Puedes definirlas también como constantes PHP en wp-config.php (REPLANTA_ANTHROPIC_KEY, REPLANTA_OPENAI_KEY, REPLANTA_EMBEDDINGS_KEY) para mayor seguridad.', 'replanta-ai-chat' ); ?>
        </p>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'Proveedor LLM', 'replanta-ai-chat' ); ?></th>
                <td>
                    <select name="provider[llm_provider]" id="replanta-llm-provider">
                        <option value="anthropic" <?php selected( $o['llm_provider'], 'anthropic' ); ?>>Anthropic (Claude)</option>
                        <option value="openai"    <?php selected( $o['llm_provider'], 'openai' ); ?>>OpenAI (GPT-4o)</option>
                    </select>
                </td>
            </tr>

            <tr class="replanta-provider-row replanta-anthropic" <?php echo $o['llm_provider'] !== 'anthropic' ? 'style="display:none"' : ''; ?>>
                <th><?php esc_html_e( 'API Key — Anthropic', 'replanta-ai-chat' ); ?></th>
                <td>
                    <input type="password" name="provider[anthropic_key]"
                           value="<?php echo esc_attr( $o['anthropic_key'] ); ?>" class="regular-text"
                           autocomplete="new-password" />
                </td>
            </tr>
            <tr class="replanta-provider-row replanta-anthropic" <?php echo $o['llm_provider'] !== 'anthropic' ? 'style="display:none"' : ''; ?>>
                <th><?php esc_html_e( 'Modelo Claude', 'replanta-ai-chat' ); ?></th>
                <td>
                    <select name="provider[anthropic_model]">
                        <?php foreach ( [ 'claude-sonnet-4-6' => 'Claude Sonnet 4.6 (recomendado)', 'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 (más rápido/barato)' ] as $val => $label ) : ?>
                            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $o['anthropic_model'], $val ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <tr class="replanta-provider-row replanta-openai" <?php echo $o['llm_provider'] !== 'openai' ? 'style="display:none"' : ''; ?>>
                <th><?php esc_html_e( 'API Key — OpenAI (LLM)', 'replanta-ai-chat' ); ?></th>
                <td>
                    <input type="password" name="provider[openai_key]"
                           value="<?php echo esc_attr( $o['openai_key'] ); ?>" class="regular-text"
                           autocomplete="new-password" />
                </td>
            </tr>
            <tr class="replanta-provider-row replanta-openai" <?php echo $o['llm_provider'] !== 'openai' ? 'style="display:none"' : ''; ?>>
                <th><?php esc_html_e( 'Modelo OpenAI', 'replanta-ai-chat' ); ?></th>
                <td>
                    <select name="provider[openai_llm_model]">
                        <?php foreach ( [ 'gpt-4o' => 'GPT-4o', 'gpt-4o-mini' => 'GPT-4o Mini' ] as $val => $label ) : ?>
                            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $o['openai_llm_model'], $val ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <tr>
                <th><?php esc_html_e( 'API Key — OpenAI Embeddings', 'replanta-ai-chat' ); ?></th>
                <td>
                    <input type="password" name="provider[embeddings_key]"
                           value="<?php echo esc_attr( $o['embeddings_key'] ); ?>" class="regular-text"
                           autocomplete="new-password" />
                    <p class="description"><?php esc_html_e( 'Para embeddings siempre se usa text-embedding-3-small de OpenAI. Si ya configuraste la clave OpenAI arriba puedes dejar esto vacío.', 'replanta-ai-chat' ); ?></p>
                </td>
            </tr>

            <tr>
                <th><?php esc_html_e( 'Temperatura', 'replanta-ai-chat' ); ?></th>
                <td>
                    <input type="number" name="provider[temperature]" min="0" max="1" step="0.05"
                           value="<?php echo esc_attr( $o['temperature'] ); ?>" class="small-text" />
                    <p class="description"><?php esc_html_e( 'Valor bajo (0.1–0.3) = respuestas más deterministas. Recomendado para ecommerce.', 'replanta-ai-chat' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Max tokens por respuesta', 'replanta-ai-chat' ); ?></th>
                <td>
                    <input type="number" name="provider[max_tokens]" min="256" max="4096" step="128"
                           value="<?php echo esc_attr( $o['max_tokens'] ); ?>" class="small-text" />
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Presupuesto mensual (USD)', 'replanta-ai-chat' ); ?></th>
                <td>
                    <input type="number" name="provider[monthly_budget]" min="0" step="1"
                           value="<?php echo esc_attr( $o['monthly_budget'] ); ?>" class="small-text" />
                    <p class="description"><?php esc_html_e( 'Alerta por email si se supera. 0 = sin límite.', 'replanta-ai-chat' ); ?></p>
                </td>
            </tr>
        </table>

        <p>
            <button type="button" id="replanta-check-api" class="button">
                <?php esc_html_e( 'Comprobar conexión APIs', 'replanta-ai-chat' ); ?>
            </button>
            <span id="replanta-api-status" style="margin-left:12px;vertical-align:middle;font-size:13px"></span>
        </p>
        <div id="replanta-api-results" style="display:none;margin-top:8px;padding:12px;background:#fff;border:1px solid #ddd;border-radius:4px;max-width:560px">
            <div id="replanta-api-anthropic"></div>
            <div id="replanta-api-openai" style="margin-top:6px"></div>
        </div>

        <script>
        document.getElementById('replanta-llm-provider').addEventListener('change', function() {
            document.querySelectorAll('.replanta-anthropic').forEach(el => el.style.display = this.value === 'anthropic' ? '' : 'none');
            document.querySelectorAll('.replanta-openai').forEach(el => el.style.display = this.value === 'openai' ? '' : 'none');
        });
        </script>
        <?php
    }

    // ── Tab: Campos ACF/Meta ──────────────────────────────────────────────────

    private static function tab_indexing(): void {
        $o = Options::get_indexing();
        ?>
        <h2><?php esc_html_e( 'Mapeo de campos personalizados', 'replanta-ai-chat' ); ?></h2>
        <p><?php esc_html_e( 'Selecciona qué campos ACF y meta de WooCommerce incluir en el índice semántico. Asigna una etiqueta descriptiva a cada campo para que el asistente entienda su contenido.', 'replanta-ai-chat' ); ?></p>

        <?php
        // Detect ACF fields from product post type
        $acf_groups  = self::get_acf_field_groups();
        $saved_acf   = array_column( $o['acf_fields'] ?? [], null, 'key' );
        $saved_meta  = array_column( $o['meta_fields'] ?? [], null, 'key' );
        ?>

        <?php if ( ! empty( $acf_groups ) ) : ?>
        <h3><?php esc_html_e( 'Campos ACF detectados', 'replanta-ai-chat' ); ?></h3>
        <table class="widefat replanta-field-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Incluir', 'replanta-ai-chat' ); ?></th>
                    <th><?php esc_html_e( 'Grupo ACF', 'replanta-ai-chat' ); ?></th>
                    <th><?php esc_html_e( 'Campo (key)', 'replanta-ai-chat' ); ?></th>
                    <th><?php esc_html_e( 'Etiqueta semántica', 'replanta-ai-chat' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $acf_groups as $group_title => $fields ) :
                    foreach ( $fields as $field ) :
                        $checked = isset( $saved_acf[ $field['key'] ] );
                        $label   = $saved_acf[ $field['key'] ]['label'] ?? $field['label'];
                        ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="acf_fields[<?php echo esc_attr( $field['key'] ); ?>][enabled]"
                                       value="1" <?php checked( $checked ); ?> />
                            </td>
                            <td><?php echo esc_html( $group_title ); ?></td>
                            <td>
                                <code><?php echo esc_html( $field['key'] ); ?></code>
                                <input type="hidden" name="acf_fields[<?php echo esc_attr( $field['key'] ); ?>][key]"
                                       value="<?php echo esc_attr( $field['key'] ); ?>" />
                            </td>
                            <td>
                                <input type="text" name="acf_fields[<?php echo esc_attr( $field['key'] ); ?>][label]"
                                       value="<?php echo esc_attr( $label ); ?>" class="regular-text"
                                       placeholder="<?php esc_attr_e( 'Ej: Ingredientes INCI', 'replanta-ai-chat' ); ?>" />
                            </td>
                        </tr>
                    <?php endforeach;
                endforeach; ?>
            </tbody>
        </table>
        <?php else : ?>
        <p class="description"><?php esc_html_e( 'No se han detectado grupos ACF para el tipo de post "product". Asegúrate de que ACF esté activo y tenga grupos asignados a productos.', 'replanta-ai-chat' ); ?></p>
        <?php endif; ?>

        <h3><?php esc_html_e( 'Campos meta personalizados (no ACF)', 'replanta-ai-chat' ); ?></h3>
        <p class="description"><?php esc_html_e( 'Añade manualmente meta keys adicionales (ej. _my_plugin_field).', 'replanta-ai-chat' ); ?></p>
        <div id="replanta-meta-fields">
            <?php
            $meta_fields = $o['meta_fields'] ?? [];
            foreach ( $meta_fields as $i => $mf ) :
                ?>
                <div class="replanta-meta-row">
                    <input type="text" name="meta_fields[<?php echo $i; ?>][key]"
                           value="<?php echo esc_attr( $mf['key'] ); ?>" placeholder="_meta_key" class="regular-text" />
                    <input type="text" name="meta_fields[<?php echo $i; ?>][label]"
                           value="<?php echo esc_attr( $mf['label'] ); ?>" placeholder="Etiqueta" class="regular-text" />
                    <button type="button" class="button replanta-remove-meta">&times;</button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" id="replanta-add-meta" class="button">
            <?php esc_html_e( '+ Añadir campo meta', 'replanta-ai-chat' ); ?>
        </button>

        <h3><?php esc_html_e( 'Opciones de indexación', 'replanta-ai-chat' ); ?></h3>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'Indexar productos sin stock', 'replanta-ai-chat' ); ?></th>
                <td>
                    <input type="checkbox" name="indexing[index_out_of_stock]" value="1"
                           <?php checked( ! empty( $o['index_out_of_stock'] ) ); ?> />
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Re-indexar al guardar producto', 'replanta-ai-chat' ); ?></th>
                <td>
                    <input type="checkbox" name="indexing[auto_index]" value="1"
                           <?php checked( ! empty( $o['auto_index'] ) ); ?> />
                </td>
            </tr>
        </table>
        <?php
    }

    // ── Tab: Comportamiento ───────────────────────────────────────────────────

    private static function tab_behaviour(): void {
        $o = Options::get_behaviour();
        ?>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'Instrucciones extra del sistema', 'replanta-ai-chat' ); ?></th>
                <td>
                    <textarea name="behaviour[system_prompt_extra]" rows="6" class="large-text"><?php
                        echo esc_textarea( $o['system_prompt_extra'] );
                    ?></textarea>
                    <p class="description"><?php esc_html_e( 'Se añaden al final del system prompt base. Usa esto para instrucciones específicas de marca o restricciones de catálogo.', 'replanta-ai-chat' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Mensaje de fallback', 'replanta-ai-chat' ); ?></th>
                <td>
                    <input type="text" name="behaviour[fallback_message]"
                           value="<?php echo esc_attr( $o['fallback_message'] ); ?>" class="large-text" />
                    <p class="description"><?php esc_html_e( 'Cuando el asistente no tiene información suficiente.', 'replanta-ai-chat' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Lista negra de claims (uno por línea)', 'replanta-ai-chat' ); ?></th>
                <td>
                    <textarea name="behaviour[claims_blacklist]" rows="6" class="regular-text"><?php
                        echo esc_textarea( $o['claims_blacklist'] );
                    ?></textarea>
                    <p class="description"><?php esc_html_e( 'El asistente no usará estas palabras en sus respuestas. Útil para cumplir regulación cosmética UE.', 'replanta-ai-chat' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Máx. productos en contexto', 'replanta-ai-chat' ); ?></th>
                <td>
                    <input type="number" name="behaviour[max_context_products]" min="1" max="10"
                           value="<?php echo esc_attr( $o['max_context_products'] ); ?>" class="small-text" />
                    <p class="description"><?php esc_html_e( 'Número de productos recuperados del índice para enviar al LLM. 3–5 es óptimo.', 'replanta-ai-chat' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Email de escalado', 'replanta-ai-chat' ); ?></th>
                <td>
                    <input type="email" name="behaviour[escalation_email]"
                           value="<?php echo esc_attr( $o['escalation_email'] ); ?>" class="regular-text" />
                    <p class="description"><?php esc_html_e( 'Recibe notificación cuando el asistente escala una conversación.', 'replanta-ai-chat' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Idioma por defecto', 'replanta-ai-chat' ); ?></th>
                <td>
                    <select name="behaviour[language]">
                        <?php foreach ( [ 'es' => 'Español', 'en' => 'English', 'ca' => 'Català', 'fr' => 'Français', 'pt' => 'Português' ] as $val => $label ) : ?>
                            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $o['language'], $val ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    // ── Tab: Herramientas ─────────────────────────────────────────────────────

    private static function tab_tools(): void {
        $o = Options::get_tools();
        $tools = [
            'cart_enabled'       => [ __( 'Añadir al carrito', 'replanta-ai-chat' ), __( 'Permite al asistente añadir productos al carrito del cliente.', 'replanta-ai-chat' ) ],
            'order_enabled'      => [ __( 'Preparar pedido', 'replanta-ai-chat' ), __( 'Genera un enlace a checkout pre-llenado con los productos sugeridos.', 'replanta-ai-chat' ) ],
            'escalation_enabled' => [ __( 'Escalar a humano', 'replanta-ai-chat' ), __( 'El asistente puede transferir la conversación a un asesor.', 'replanta-ai-chat' ) ],
            'search_enabled'     => [ __( 'Búsqueda adicional', 'replanta-ai-chat' ), __( 'El asistente puede buscar más productos si los resultados iniciales no son suficientes.', 'replanta-ai-chat' ) ],
        ];
        ?>
        <table class="form-table">
            <?php foreach ( $tools as $key => [ $label, $desc ] ) : ?>
            <tr>
                <th><?php echo esc_html( $label ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="tools[<?php echo esc_attr( $key ); ?>]" value="1"
                               <?php checked( ! empty( $o[ $key ] ) ); ?> />
                        <?php echo esc_html( $desc ); ?>
                    </label>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php
    }

    // ── Tab: Licencia ─────────────────────────────────────────────────────────

    private static function tab_license(): void {
        $o      = Options::get_license();
        $status = $o['license_status'] ?? 'inactive';
        $active = 'active' === $status;
        ?>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'Estado', 'replanta-ai-chat' ); ?></th>
                <td>
                    <span class="replanta-license-badge replanta-license-<?php echo esc_attr( $status ); ?>">
                        <?php echo esc_html( self::license_status_label( $status ) ); ?>
                    </span>
                    <?php if ( ! empty( $o['license_expires'] ) ) : ?>
                        <span class="description"> &mdash; <?php printf( esc_html__( 'Expira: %s', 'replanta-ai-chat' ), esc_html( $o['license_expires'] ) ); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Clave de licencia', 'replanta-ai-chat' ); ?></th>
                <td>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'replanta_ai_chat_license' ); ?>
                        <input type="text" name="replanta_license_key"
                               value="<?php echo $active ? str_repeat( '•', 20 ) : esc_attr( $o['license_key'] ); ?>"
                               class="regular-text" <?php echo $active ? 'readonly' : ''; ?> />
                        <?php if ( $active ) : ?>
                            <button type="submit" name="replanta_license_action" value="deactivate" class="button button-secondary">
                                <?php esc_html_e( 'Desactivar licencia', 'replanta-ai-chat' ); ?>
                            </button>
                        <?php else : ?>
                            <button type="submit" name="replanta_license_action" value="activate" class="button button-primary">
                                <?php esc_html_e( 'Activar licencia', 'replanta-ai-chat' ); ?>
                            </button>
                        <?php endif; ?>
                    </form>
                    <p class="description">
                        <?php esc_html_e( 'Introduce tu clave de licencia de replanta.dev para recibir actualizaciones automáticas.', 'replanta-ai-chat' ); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    // ── Save ──────────────────────────────────────────────────────────────────

    private static function save(): void {
        $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';

        match ( $tab ) {
            'provider'  => self::save_provider(),
            'indexing'  => self::save_indexing(),
            'behaviour' => self::save_behaviour(),
            'tools'     => self::save_tools(),
            default     => self::save_general(),
        };
    }

    private static function save_general(): void {
        $raw = $_POST['general'] ?? [];
        Options::update( 'general', [
            'assistant_name'  => sanitize_text_field( $raw['assistant_name'] ?? '' ),
            'welcome_message' => sanitize_text_field( $raw['welcome_message'] ?? '' ),
            'widget_position' => sanitize_key( $raw['widget_position'] ?? 'bottom-right' ),
            'primary_color'   => sanitize_hex_color( $raw['primary_color'] ?? '#2d6a4f' ),
            'show_on'         => sanitize_key( $raw['show_on'] ?? 'all' ),
            'chat_enabled'    => ! empty( $raw['chat_enabled'] ),
        ] );
    }

    private static function save_provider(): void {
        $raw      = $_POST['provider'] ?? [];
        $existing = Options::get_provider();

        // Preserve existing API keys when field is submitted blank (browser blanks password fields)
        $ant_key  = sanitize_text_field( $raw['anthropic_key'] ?? '' ) ?: $existing['anthropic_key'];
        $oai_key  = sanitize_text_field( $raw['openai_key'] ?? '' )    ?: $existing['openai_key'];
        $emb_key  = sanitize_text_field( $raw['embeddings_key'] ?? '' ) ?: $existing['embeddings_key'];

        Options::update( 'provider', [
            'llm_provider'     => sanitize_key( $raw['llm_provider'] ?? 'anthropic' ),
            'anthropic_key'    => $ant_key,
            'anthropic_model'  => sanitize_text_field( $raw['anthropic_model'] ?? 'claude-sonnet-4-6' ),
            'openai_key'       => $oai_key,
            'openai_llm_model' => sanitize_text_field( $raw['openai_llm_model'] ?? 'gpt-4o' ),
            'embeddings_key'   => $emb_key,
            'temperature'      => min( 1.0, max( 0.0, (float) ( $raw['temperature'] ?? 0.2 ) ) ),
            'max_tokens'       => min( 4096, max( 256, (int) ( $raw['max_tokens'] ?? 1024 ) ) ),
            'monthly_budget'   => max( 0, (int) ( $raw['monthly_budget'] ?? 0 ) ),
        ] );
    }

    private static function save_indexing(): void {
        // ACF fields
        $acf_raw = $_POST['acf_fields'] ?? [];
        $acf     = [];
        foreach ( $acf_raw as $key => $data ) {
            if ( empty( $data['enabled'] ) ) {
                continue;
            }
            $acf[] = [
                'key'   => sanitize_text_field( $data['key'] ?? $key ),
                'label' => sanitize_text_field( $data['label'] ?? $key ),
            ];
        }

        // Meta fields
        $meta_raw = $_POST['meta_fields'] ?? [];
        $meta     = [];
        foreach ( $meta_raw as $row ) {
            $k = sanitize_text_field( $row['key'] ?? '' );
            $l = sanitize_text_field( $row['label'] ?? '' );
            if ( $k ) {
                $meta[] = [ 'key' => $k, 'label' => $l ];
            }
        }

        $raw = $_POST['indexing'] ?? [];
        Options::update( 'indexing', [
            'acf_fields'          => $acf,
            'meta_fields'         => $meta,
            'index_out_of_stock'  => ! empty( $raw['index_out_of_stock'] ),
            'auto_index'          => ! empty( $raw['auto_index'] ),
        ] );
    }

    private static function save_behaviour(): void {
        $raw = $_POST['behaviour'] ?? [];
        Options::update( 'behaviour', [
            'system_prompt_extra'  => wp_kses_post( $raw['system_prompt_extra'] ?? '' ),
            'fallback_message'     => sanitize_text_field( $raw['fallback_message'] ?? '' ),
            'claims_blacklist'     => sanitize_textarea_field( $raw['claims_blacklist'] ?? '' ),
            'max_context_products' => min( 10, max( 1, (int) ( $raw['max_context_products'] ?? 5 ) ) ),
            'escalation_email'     => sanitize_email( $raw['escalation_email'] ?? '' ),
            'language'             => sanitize_key( $raw['language'] ?? 'es' ),
        ] );
    }

    private static function save_tools(): void {
        $raw = $_POST['tools'] ?? [];
        Options::update( 'tools', [
            'cart_enabled'       => ! empty( $raw['cart_enabled'] ),
            'order_enabled'      => ! empty( $raw['order_enabled'] ),
            'escalation_enabled' => ! empty( $raw['escalation_enabled'] ),
            'search_enabled'     => ! empty( $raw['search_enabled'] ),
        ] );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function get_acf_field_groups(): array {
        if ( ! function_exists( 'acf_get_field_groups' ) ) {
            return [];
        }

        $groups = acf_get_field_groups( [ 'post_type' => 'product' ] );
        $result = [];

        foreach ( $groups as $group ) {
            $fields = acf_get_fields( $group );
            if ( ! $fields ) {
                continue;
            }
            $result[ $group['title'] ] = array_map( static fn( $f ) => [
                'key'   => $f['key'],
                'label' => $f['label'],
                'type'  => $f['type'],
            ], $fields );
        }

        return $result;
    }

    private static function license_status_label( string $status ): string {
        return match ( $status ) {
            'active'   => __( 'Activa', 'replanta-ai-chat' ),
            'expired'  => __( 'Expirada', 'replanta-ai-chat' ),
            'invalid'  => __( 'Inválida', 'replanta-ai-chat' ),
            default    => __( 'Inactiva', 'replanta-ai-chat' ),
        };
    }
}
