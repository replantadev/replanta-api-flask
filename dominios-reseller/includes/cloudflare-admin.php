<?php
/**
 * Cloudflare Settings Admin Page
 * 
 * Gestiona la configuración y UI de Cloudflare en el admin de WordPress
 * 
 * @package Dominios_Reseller
 * @version 1.0.0
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dominios_Reseller_Cloudflare_Admin {

    /**
     * Instancia singleton
     */
    private static ?Dominios_Reseller_Cloudflare_Admin $instance = null;

    /**
     * Servicio de Cloudflare
     */
    private Dominios_Reseller_Cloudflare_Service $cf_service;

    /**
     * Constructor
     */
    private function __construct() {
        $this->cf_service = Dominios_Reseller_Cloudflare_Service::get_instance();
        $this->init_hooks();
    }

    /**
     * Obtener instancia singleton
     */
    public static function get_instance(): Dominios_Reseller_Cloudflare_Admin {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Inicializar hooks
     */
    private function init_hooks(): void {
        // Registrar settings
        add_action('admin_init', [$this, 'register_settings']);
        
        // AJAX handlers
        add_action('wp_ajax_dr_cf_sync', [$this, 'ajax_sync_zones']);
        add_action('wp_ajax_dr_cf_verify_token', [$this, 'ajax_verify_token']);
        add_action('wp_ajax_dr_cf_clear_data', [$this, 'ajax_clear_data']);
    }

    /**
     * Registrar settings de Cloudflare
     */
    public function register_settings(): void {
        // Sección Cloudflare
        add_settings_section(
            'dominios_reseller_cloudflare',
            '☁️ Integración Cloudflare',
            [$this, 'render_section_description'],
            'dominios-reseller'
        );

        // Campo API Token
        add_settings_field(
            'cf_api_token',
            'API Token Cloudflare',
            [$this, 'render_token_field'],
            'dominios-reseller',
            'dominios_reseller_cloudflare'
        );

        // Campo de estado de sincronización (solo lectura)
        add_settings_field(
            'cf_sync_status',
            'Estado de Sincronización',
            [$this, 'render_sync_status'],
            'dominios-reseller',
            'dominios_reseller_cloudflare'
        );
    }

    /**
     * Descripción de la sección Cloudflare
     */
    public function render_section_description(): void {
        echo '<p>Configura la integración con Cloudflare para marcar qué dominios están configurados en CF.</p>';
        echo '<p><strong>Nota:</strong> La sincronización se hace de forma manual o por cron. El listado de dominios NO hace llamadas a Cloudflare.</p>';
        
        echo '<div class="dr-cf-permissions-info" style="background: #f0f6fc; border-left: 4px solid #0073aa; padding: 12px 15px; margin: 15px 0;">';
        echo '<h4 style="margin-top: 0;">📋 Permisos mínimos recomendados del API Token</h4>';
        echo '<ul style="margin-bottom: 0;">';
        echo '<li><strong>Zone → Zone → Read</strong> - Alcance: <em>All zones</em> (o las cuentas específicas donde tienes acceso admin)</li>';
        echo '</ul>';
        echo '<p style="margin-bottom: 0; color: #666; font-size: 12px;">';
        echo 'Este es el permiso mínimo necesario para listar zonas. No se requieren permisos de escritura ni DNS.';
        echo '</p>';
        echo '</div>';
    }

    /**
     * Renderizar campo de API Token
     */
    public function render_token_field(): void {
        $opts = get_option('dominios_reseller_options', []);
        $token = $opts['cf_api_token'] ?? '';
        $email = $opts['cf_email'] ?? '';
        $global_key = $opts['cf_global_key'] ?? '';
        
        $has_token = !empty($token);
        $has_global = !empty($email) && !empty($global_key);
        
        echo '<div class="dr-cf-auth-wrapper">';
        echo '<p style="background: #f9f9f9; border-left: 4px solid #2271b1; padding: 12px; margin-bottom: 15px;">';
        echo '<strong>Opciones de autenticación:</strong><br>';
        echo '<span style="color: #666;">Se usa <strong>API Token</strong> primero (prioridad), luego <strong>Global API Key + Email</strong> como fallback.</span>';
        echo '</p>';
        
        // Opción 1: API Token (PRIORIDAD)
        echo '<div class="dr-cf-auth-option" style="margin-bottom: 25px; padding: 15px; border: 2px solid #007cba; background: #f0f8ff;">';
        echo '<h3 style="margin-top: 0; color: #007cba;">🔑 Opción 1: API Token (PRIORIDAD)</h3>';
        echo '<p style="color: #007cba; margin-top: 0; font-weight: bold;">Se usa primero si está configurado. Método moderno y recomendado.</p>';
        
        if ($has_token) {
            $display_value = str_repeat('•', 36) . substr($token, -4);
            echo '<input type="text" value="' . esc_attr($display_value) . '" class="regular-text" disabled style="font-family: monospace; background: #f0f0f0;">';
            echo '<input type="hidden" name="dominios_reseller_options[cf_api_token]" value="' . esc_attr($token) . '">';
            echo '<br>';
            echo '<button type="button" class="button button-secondary" id="dr-cf-delete-token" style="margin-top: 10px; color: #a00;">Eliminar Token</button> ';
            echo '<label style="margin-left: 10px;"><input type="checkbox" id="dr-cf-change-token"> Cambiar token</label>';
            echo '<div id="dr-cf-new-token-wrapper" style="display: none; margin-top: 10px;">';
            echo '<input type="password" id="dr-cf-new-token" class="regular-text" placeholder="Nuevo API Token..." autocomplete="off">';
            echo '<button type="button" class="button" id="dr-cf-save-new-token">Guardar nuevo token</button>';
            echo '</div>';
        } else {
            echo '<input type="password" name="dominios_reseller_options[cf_api_token]" value="" class="regular-text" placeholder="API Token de Cloudflare..." autocomplete="off">';
        }
        
        echo '<p class="description">Obtén un API Token en <a href="https://dash.cloudflare.com/profile/api-tokens" target="_blank">Cloudflare Dashboard → API Tokens</a></p>';
        echo '</div>';
        
        // Opción 2: Global API Key (FALLBACK)
        echo '<div class="dr-cf-auth-option" style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; background: #fffbf0;">';
        echo '<h3 style="margin-top: 0;">Opción 2: Global API Key + Email (FALLBACK)</h3>';
        echo '<p style="color: #666; margin-top: 0;">Solo se usa si no hay API Token configurado. Método legacy con permisos globales.</p>';
        
        echo '<p><label><strong>Email de tu cuenta Cloudflare:</strong><br>';
        echo '<input type="email" name="dominios_reseller_options[cf_email]" value="' . esc_attr($email) . '" class="regular-text" placeholder="tu@email.com"></label></p>';
        
        echo '<p><label><strong>Global API Key:</strong><br>';
        if ($has_global) {
            $display_key = str_repeat('•', 33) . substr($global_key, -4);
            echo '<input type="text" value="' . esc_attr($display_key) . '" class="regular-text" disabled style="font-family: monospace; background: #f0f0f0;">';
            echo '<input type="hidden" name="dominios_reseller_options[cf_global_key]" value="' . esc_attr($global_key) . '">';
            echo '<br>';
            echo '<button type="button" class="button button-secondary" id="dr-cf-delete-global" style="margin-top: 10px; color: #a00;">Eliminar Global Key</button> ';
            echo '<label style="margin-left: 10px;"><input type="checkbox" id="dr-cf-change-global"> Cambiar Global API Key</label>';
            echo '<div id="dr-cf-new-global-wrapper" style="display: none; margin-top: 10px;">';
            echo '<input type="password" id="dr-cf-new-global" class="regular-text" placeholder="Nueva Global API Key..." autocomplete="off">';
            echo '<button type="button" class="button" id="dr-cf-save-new-global">Guardar nueva key</button>';
            echo '</div>';
        } else {
            echo '<input type="password" name="dominios_reseller_options[cf_global_key]" value="" class="regular-text" placeholder="Global API Key..." autocomplete="off">';
        }
        echo '</label></p>';
        
        echo '<p class="description">Obtén tu Global API Key en <a href="https://dash.cloudflare.com/profile/api-tokens" target="_blank">Cloudflare Dashboard → API Tokens → View Global API Key</a></p>';
        echo '</div>';
        
        // Botón de verificación
        if ($has_token || $has_global) {
            echo '<p style="margin-top: 10px;">';
            echo '<button type="button" class="button button-secondary" id="dr-cf-verify-token">Verificar Credenciales</button> ';
            echo '<span id="dr-cf-verify-result"></span>';
            echo '</p>';
        }
        
        echo '</div>';
    }

    /**
     * Renderizar estado de sincronización
     */
    public function render_sync_status(): void {
        $stats = $this->cf_service->get_sync_stats();
        $zones_count = $this->cf_service->get_zones_count();
        $has_token = !empty($this->cf_service->get_token());
        
        echo '<div class="dr-cf-sync-wrapper">';
        
        // Mostrar última sincronización
        if (!empty($stats['synced_at'])) {
            $synced_ago = human_time_diff(strtotime($stats['synced_at']), current_time('timestamp'));
            
            echo '<div class="dr-cf-sync-info" style="margin-bottom: 15px;">';
            
            if ($stats['success']) {
                echo '<span style="color: #46b450;">Última sincronización exitosa</span><br>';
                echo '<small>Hace ' . esc_html($synced_ago) . ' (' . esc_html($stats['synced_at']) . ')</small><br>';
                echo '<small><strong>Zonas:</strong> ' . esc_html($zones_count) . ' | ';
                echo '<strong>Duración:</strong> ' . esc_html($stats['duration'] ?? 0) . 's | ';
                echo '<strong>Páginas:</strong> ' . esc_html($stats['pages'] ?? 0) . '</small>';
            } else {
                echo '<span style="color: #dc3232;">Error en última sincronización</span><br>';
                echo '<small>' . esc_html($stats['error'] ?? 'Error desconocido') . '</small>';
            }
            
            echo '</div>';
        } else {
            echo '<div class="dr-cf-sync-info" style="margin-bottom: 15px;">';
            echo '<span style="color: #996800;">Nunca sincronizado</span>';
            echo '</div>';
        }
        
        // Mostrar stats detallados si existen
        if (!empty($stats['zones_added']) || !empty($stats['zones_updated']) || !empty($stats['zones_deleted'])) {
            echo '<div class="dr-cf-sync-details" style="margin-bottom: 15px; padding: 10px; background: #f5f5f5; border-radius: 4px;">';
            echo '<small>';
            echo '<strong>Última operación:</strong> ';
            echo 'Añadidas: ' . esc_html($stats['zones_added'] ?? 0) . ' | ';
            echo 'Actualizadas: ' . esc_html($stats['zones_updated'] ?? 0) . ' | ';
            echo 'Eliminadas: ' . esc_html($stats['zones_deleted'] ?? 0);
            echo '</small>';
            echo '</div>';
        }
        
        // Botones de acción
        echo '<div class="dr-cf-sync-actions">';
        
        if ($has_token) {
            echo '<button type="button" class="button button-primary" id="dr-cf-sync-btn">';
            echo 'Sincronizar Cloudflare</button> ';
            echo '<button type="button" class="button" id="dr-cf-clear-btn" style="color: #a00;">';
            echo 'Limpiar datos</button>';
        } else {
            echo '<p class="description" style="color: #996800;">Configura primero las credenciales para sincronizar.</p>';
        }
        
        echo '</div>';
        
        echo '<div id="dr-cf-sync-progress" style="display: none; margin-top: 15px;">';
        echo '<span class="spinner" style="visibility: visible; float: none;"></span> Sincronizando...';
        echo '</div>';
        
        echo '<div id="dr-cf-sync-result" style="margin-top: 15px;"></div>';
        
        echo '</div>';
        
        // JavaScript para los botones
        $this->render_admin_scripts();
    }

    /**
     * Scripts de admin para la sección Cloudflare
     */
    private function render_admin_scripts(): void {
        $nonce = wp_create_nonce('dr_cf_admin');
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Toggle cambiar token
            $('#dr-cf-change-token').on('change', function() {
                $('#dr-cf-new-token-wrapper').toggle(this.checked);
            });
            
            // Toggle cambiar global key
            $('#dr-cf-change-global').on('change', function() {
                $('#dr-cf-new-global-wrapper').toggle(this.checked);
            });
            
            // Eliminar token
            $('#dr-cf-delete-token').on('click', function() {
                if (!confirm('¿Eliminar el API Token de Cloudflare?')) {
                    return;
                }
                $('input[name="dominios_reseller_options[cf_api_token]"]').val('');
                alert('Token eliminado. Guarda los cambios para aplicar.');
            });
            
            // Eliminar global key
            $('#dr-cf-delete-global').on('click', function() {
                if (!confirm('¿Eliminar la Global API Key de Cloudflare?')) {
                    return;
                }
                $('input[name="dominios_reseller_options[cf_global_key]"]').val('');
                alert('Global API Key eliminada. Guarda los cambios para aplicar.');
            });
            
            // Guardar nuevo token
            $('#dr-cf-save-new-token').on('click', function() {
                var newToken = $('#dr-cf-new-token').val().trim();
                if (!newToken) {
                    alert('Introduce un token válido');
                    return;
                }
                $('input[name="dominios_reseller_options[cf_api_token]"]').val(newToken);
                alert('Token actualizado. Guarda los cambios para aplicar.');
                $('#dr-cf-change-token').prop('checked', false).trigger('change');
            });
            
            // Guardar nueva global key
            $('#dr-cf-save-new-global').on('click', function() {
                var newKey = $('#dr-cf-new-global').val().trim();
                if (!newKey) {
                    alert('Introduce una Global API Key válida');
                    return;
                }
                $('input[name="dominios_reseller_options[cf_global_key]"]').val(newKey);
                alert('Global API Key actualizada. Guarda los cambios para aplicar.');
                $('#dr-cf-change-global').prop('checked', false).trigger('change');
            });
            
            // Verificar token
            $('#dr-cf-verify-token').on('click', function() {
                var $btn = $(this);
                var $result = $('#dr-cf-verify-result');
                
                $btn.prop('disabled', true);
                $result.html('<span class="spinner" style="visibility: visible; float: none;"></span> Verificando...');
                
                $.post(ajaxurl, {
                    action: 'dr_cf_verify_token',
                    _nonce: '<?php echo $nonce; ?>'
                }, function(response) {
                    $btn.prop('disabled', false);
                    if (response.success) {
                        $result.html('<span style="color: #46b450;">Token válido</span>');
                    } else {
                        $result.html('<span style="color: #dc3232;">' + response.data + '</span>');
                    }
                }).fail(function() {
                    $btn.prop('disabled', false);
                    $result.html('<span style="color: #dc3232;">Error de conexión</span>');
                });
            });
            
            // Sincronizar zonas
            $('#dr-cf-sync-btn').on('click', function() {
                var $btn = $(this);
                var $progress = $('#dr-cf-sync-progress');
                var $result = $('#dr-cf-sync-result');
                
                if (!confirm('¿Sincronizar zonas desde Cloudflare? Esto puede tardar unos segundos.')) {
                    return;
                }
                
                $btn.prop('disabled', true);
                $progress.show();
                $result.html('');
                
                $.post(ajaxurl, {
                    action: 'dr_cf_sync',
                    _nonce: '<?php echo $nonce; ?>'
                }, function(response) {
                    $btn.prop('disabled', false);
                    $progress.hide();
                    
                    if (response.success) {
                        var stats = response.data;
                        $result.html(
                            '<div style="padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;">' +
                            '<strong>Sincronización completada</strong><br>' +
                            'Total zonas: ' + stats.zones_total + '<br>' +
                            'Añadidas: ' + stats.zones_added + ' | Actualizadas: ' + stats.zones_updated + ' | Eliminadas: ' + stats.zones_deleted + '<br>' +
                            'Duración: ' + stats.duration + 's' +
                            '</div>'
                        );
                        // Recargar página después de 2s para actualizar stats
                        setTimeout(function() { location.reload(); }, 2000);
                    } else {
                        $result.html(
                            '<div style="padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">' +
                            '<strong>Error:</strong> ' + response.data +
                            '</div>'
                        );
                    }
                }).fail(function() {
                    $btn.prop('disabled', false);
                    $progress.hide();
                    $result.html('<span style="color: #dc3232;">Error de conexión</span>');
                });
            });
            
            // Limpiar datos
            $('#dr-cf-clear-btn').on('click', function() {
                if (!confirm('¿Eliminar TODOS los datos de Cloudflare? Esta acción no se puede deshacer.')) {
                    return;
                }
                
                var $result = $('#dr-cf-sync-result');
                
                $.post(ajaxurl, {
                    action: 'dr_cf_clear_data',
                    _nonce: '<?php echo $nonce; ?>'
                }, function(response) {
                    if (response.success) {
                        $result.html('<span style="color: #46b450;">Datos eliminados</span>');
                        setTimeout(function() { location.reload(); }, 1000);
                    } else {
                        $result.html('<span style="color: #dc3232;">' + response.data + '</span>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX: Sincronizar zonas
     */
    public function ajax_sync_zones(): void {
        check_ajax_referer('dr_cf_admin', '_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        $result = $this->cf_service->sync_zones();

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['error'] ?? 'Error desconocido');
        }
    }

    /**
     * AJAX: Verificar token
     */
    public function ajax_verify_token(): void {
        check_ajax_referer('dr_cf_admin', '_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        $result = $this->cf_service->verify_token();

        if ($result['valid']) {
            wp_send_json_success('Token válido');
        } else {
            wp_send_json_error($result['error'] ?? 'Token inválido');
        }
    }

    /**
     * AJAX: Limpiar datos
     */
    public function ajax_clear_data(): void {
        check_ajax_referer('dr_cf_admin', '_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        $this->cf_service->clear_all_data();
        wp_send_json_success('Datos eliminados');
    }

    /**
     * Renderizar columna Cloudflare para una fila del listado
     * Usar esta función desde mostrar_todos_los_dominios_unificados()
     * 
     * @param string $primary_domain El dominio principal de la fila
     * @param array|null $cf_match Resultado del match (precalculado) o null para calcular
     * @param bool $has_sync_data Si hay datos de sincronización disponibles
     * @return string HTML de la celda
     */
    public static function render_cloudflare_cell(string $primary_domain, ?array $cf_match = null, bool $has_sync_data = true): string {
        // Si no hay datos de sync, mostrar ?
        if (!$has_sync_data) {
            return '<span class="dr-cf-status dr-cf-unknown" title="Sin datos de sincronización Cloudflare">❓</span>';
        }

        if ($cf_match === null) {
            // Calcular match si no se proporcionó
            $cf_service = Dominios_Reseller_Cloudflare_Service::get_instance();
            $cf_match = $cf_service->match_domain_to_zone($primary_domain);
        }

        if ($cf_match) {
            // En Cloudflare
            $tooltip_parts = [
                'Zona: ' . $cf_match['zone_name'],
                'Estado: ' . ucfirst($cf_match['status']),
            ];
            
            if (!empty($cf_match['plan_name'])) {
                $tooltip_parts[] = 'Plan: ' . $cf_match['plan_name'];
            }
            
            if ($cf_match['match_type'] === 'subdomain') {
                $tooltip_parts[] = '(Subdominio de ' . $cf_match['zone_name'] . ')';
            }
            
            $tooltip = implode("\n", $tooltip_parts);
            $icon = $cf_match['paused'] ? '[Pausado]' : '[Activo]';
            $extra_class = $cf_match['paused'] ? ' dr-cf-paused' : '';
            
            return '<span class="dr-cf-status dr-cf-yes' . $extra_class . '" title="' . esc_attr($tooltip) . '">' . $icon . '</span>';
        } else {
            // No en Cloudflare
            return '<span class="dr-cf-status dr-cf-no" title="No encontrado en Cloudflare">-</span>';
        }
    }

    /**
     * Obtener estilos CSS para la columna Cloudflare
     */
    public static function get_cloudflare_styles(): string {
        return '
        .dr-cf-status {
            display: inline-block;
            font-size: 16px;
            cursor: help;
        }
        .dr-cf-yes {
            color: #46b450;
        }
        .dr-cf-paused {
            color: #996800;
        }
        .dr-cf-no {
            color: #dc3232;
        }
        .dr-cf-unknown {
            color: #999;
        }
        .cf-col {
            width: 50px;
            text-align: center;
        }
        ';
    }
}

// Inicializar solo en admin
if (is_admin()) {
    add_action('plugins_loaded', function() {
        Dominios_Reseller_Cloudflare_Admin::get_instance();
    });
}
