<?php
/**
 * Onboarding Admin UI
 * 
 * Gestiona la interfaz de usuario para el proceso de onboarding de Cloudflare
 * 
 * @package Dominios_Reseller
 * @version 1.0.0
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dominios_Reseller_Onboarding_Admin {

    /**
     * Instancia singleton
     */
    private static ?Dominios_Reseller_Onboarding_Admin $instance = null;

    /**
     * Worker de onboarding
     */
    private ?Dominios_Reseller_Onboarding_Worker $worker = null;

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Obtener instancia singleton
     */
    public static function get_instance(): Dominios_Reseller_Onboarding_Admin {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Inicializar hooks
     */
    private function init_hooks(): void {
        // Registrar submenu
        add_action('admin_menu', [$this, 'register_menu']);
        
        // Registrar settings de Openprovider
        add_action('admin_init', [$this, 'register_settings']);
        
        // Enqueue scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // AJAX handlers
        add_action('wp_ajax_dr_onboarding_enqueue', [$this, 'ajax_enqueue']);
        add_action('wp_ajax_dr_onboarding_retry', [$this, 'ajax_retry']);
        add_action('wp_ajax_dr_onboarding_process_now', [$this, 'ajax_process_now']);
        add_action('wp_ajax_dr_onboarding_get_status', [$this, 'ajax_get_status']);
        add_action('wp_ajax_dr_onboarding_get_logs', [$this, 'ajax_get_logs']);
        add_action('wp_ajax_dr_verify_openprovider', [$this, 'ajax_verify_openprovider']);
        add_action('wp_ajax_dr_get_domain_ns', [$this, 'ajax_get_domain_ns']);
        add_action('wp_ajax_dr_get_batch_ns', [$this, 'ajax_get_batch_ns']);
        add_action('wp_ajax_dr_get_batch_php', [$this, 'ajax_get_batch_php']);
        add_action('wp_ajax_dr_preview_preset', [$this, 'ajax_preview_preset']);
        add_action('wp_ajax_dr_get_php_info', [$this, 'ajax_get_php_info']);
        add_action('wp_ajax_dr_get_cf_config', [$this, 'ajax_get_cf_config']);
        add_action('wp_ajax_dr_update_cf_setting', [$this, 'ajax_update_cf_setting']);
        
        // Nuevos AJAX handlers
        add_action('wp_ajax_dr_refresh_all_php_info', [$this, 'ajax_refresh_all_php_info']);
        add_action('wp_ajax_dr_get_activity_log', [$this, 'ajax_get_activity_log']);
        add_action('wp_ajax_dr_get_php_alerts', [$this, 'ajax_get_php_alerts']);
        add_action('wp_ajax_dr_refresh_domain_status', [$this, 'ajax_refresh_domain_status']);
        add_action('wp_ajax_dr_reactivate_zone', [$this, 'ajax_reactivate_zone']);
        
        // Cron para actualización de PHP info cada 24h
        add_action('dr_cron_refresh_php_info', [$this, 'cron_refresh_php_info']);
        if (!wp_next_scheduled('dr_cron_refresh_php_info')) {
            wp_schedule_event(time(), 'daily', 'dr_cron_refresh_php_info');
        }
    }

    /**
     * Obtener worker
     */
    private function get_worker(): Dominios_Reseller_Onboarding_Worker {
        if ($this->worker === null) {
            $this->worker = Dominios_Reseller_Onboarding_Worker::get_instance();
        }
        return $this->worker;
    }

    /**
     * Registrar menú de administración
     */
    public function register_menu(): void {
        add_submenu_page(
            'dominios-reseller',
            'CF Onboarding',
            '<img src="https://replanta.net/wp-content/uploads/2025/12/Cloudflare.svg" style="width:16px;height:16px;vertical-align:middle;margin-right:5px;"> CF Onboarding',
            'manage_options',
            'dominios-reseller-onboarding',
            [$this, 'render_page']
        );
    }

    /**
     * Registrar settings de Openprovider
     */
    public function register_settings(): void {
        // Sección Openprovider
        add_settings_section(
            'dominios_reseller_openprovider',
            'Openprovider (opcional)',
            [$this, 'render_openprovider_section'],
            'dominios-reseller'
        );

        add_settings_field(
            'op_username',
            'Usuario Openprovider',
            [$this, 'render_op_username_field'],
            'dominios-reseller',
            'dominios_reseller_openprovider'
        );

        add_settings_field(
            'op_password',
            'Contraseña Openprovider',
            [$this, 'render_op_password_field'],
            'dominios-reseller',
            'dominios_reseller_openprovider'
        );
    }

    /**
     * Renderizar descripción sección Openprovider
     */
    public function render_openprovider_section(): void {
        echo '<p>Configura Openprovider para actualizar automáticamente los nameservers de tus dominios.</p>';
        echo '<p><em>Si no configuras Openprovider, deberás cambiar los NS manualmente.</em></p>';
    }

    /**
     * Renderizar campo usuario Openprovider
     */
    public function render_op_username_field(): void {
        $opts = get_option('dominios_reseller_options', []);
        $value = $opts['op_username'] ?? '';
        echo '<input type="text" name="dominios_reseller_options[op_username]" value="' . esc_attr($value) . '" class="regular-text" autocomplete="off">';
        echo '<p class="description">Usuario de API de Openprovider</p>';
    }

    /**
     * Renderizar campo password Openprovider
     */
    public function render_op_password_field(): void {
        $opts = get_option('dominios_reseller_options', []);
        $has_password = !empty($opts['op_password']);
        
        if ($has_password) {
            echo '<input type="password" value="••••••••••••" class="regular-text" disabled>';
            echo '<br><label><input type="checkbox" id="dr-op-change-password"> Cambiar contraseña</label>';
            echo '<div id="dr-op-new-password-wrapper" style="display:none;margin-top:10px;">';
            echo '<input type="password" name="dominios_reseller_options[op_password]" class="regular-text" placeholder="Nueva contraseña...">';
            echo '</div>';
            echo '<input type="hidden" name="dominios_reseller_options[op_password]" value="' . esc_attr($opts['op_password']) . '" id="dr-op-current-password">';
        } else {
            echo '<input type="password" name="dominios_reseller_options[op_password]" class="regular-text" autocomplete="off">';
        }
        
        echo '<p class="description">Contraseña de API de Openprovider</p>';
        
        if ($has_password) {
            echo '<p>';
            echo '<button type="button" class="button" id="dr-verify-openprovider">Verificar conexión</button> ';
            echo '<span id="dr-op-verify-result"></span>';
            echo '</p>';
        }
    }

    /**
     * Renderizar página principal de onboarding
     */
    public function render_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Acceso denegado');
        }

        $this->render_styles();
        $this->render_inline_scripts();
        
        echo '<div class="wrap dr-onboarding-page">';
        echo '<h1>Cloudflare Onboarding</h1>';
        
        // Panel de estado de cola
        $this->render_queue_status_panel();
        
        // Leyenda de indicadores
        $this->render_legend();
        
        // Lista de dominios con cards
        $this->render_domains_list();
        
        echo '</div>';
    }

    /**
     * Renderizar leyenda de indicadores
     */
    private function render_legend(): void {
        // Verificar si hay presets, si no, intentar crearlos
        $presets = Dominios_Reseller_Onboarding_DB::get_presets();
        if (empty($presets)) {
            // Intentar crear presets por defecto
            Dominios_Reseller_Onboarding_DB::insert_default_presets();
            echo '<div class="notice notice-info" style="margin:15px 0;"><p>✅ Presets de onboarding inicializados.</p></div>';
        }
        
        echo '<div class="dr-legend-panel">';
        echo '<h4 style="margin:0 0 8px 0;font-size:12px;color:#6b7280;">Leyenda:</h4>';
        echo '<div class="dr-legend-items">';
        
        // Estados del dot
        echo '<span class="dr-legend-item"><span class="dr-status-dot dr-dot-success"></span> Completado</span>';
        echo '<span class="dr-legend-item"><span class="dr-status-dot dr-dot-processing"></span> Procesando</span>';
        echo '<span class="dr-legend-item"><span class="dr-status-dot dr-dot-error"></span> Error</span>';
        echo '<span class="dr-legend-item"><span class="dr-status-dot dr-dot-warning"></span> Parcial</span>';
        echo '<span class="dr-legend-item"><span class="dr-status-dot dr-dot-none"></span> Sin procesar</span>';
        
        echo '<span class="dr-legend-sep">|</span>';
        
        // Pilotos
        echo '<span class="dr-legend-item"><span class="dr-pilot dr-pilot-cf active">CF</span> NS en Cloudflare</span>';
        echo '<span class="dr-legend-item"><span class="dr-pilot dr-pilot-cf none">CF</span> NS NO en CF</span>';
        echo '<span class="dr-legend-item"><span class="dr-pilot dr-pilot-op active">OP</span> En Openprovider</span>';
        echo '<span class="dr-legend-item"><span class="dr-pilot dr-pilot-op none">OP</span> NO en OP</span>';
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Renderizar panel de estado de cola
     */
    private function render_queue_status_panel(): void {
        $queue_status = $this->get_worker()->get_queue_status();
        $op_configured = class_exists('Dominios_Reseller_Openprovider_Service') && 
                        Dominios_Reseller_Openprovider_Service::get_instance()->is_configured();
        
        // Alertas PHP
        $alerts_count = get_option('dr_php_alerts_count', 0);
        
        echo '<div class="dr-system-status">';
        echo '<div class="dr-status-header">';
        echo '<h3>Estado del Sistema</h3>';
        echo '<div class="dr-status-actions">';
        echo '<button type="button" class="dr-btn-icon" id="dr-refresh-status" title="Refrescar"><span class="dashicons dashicons-update"></span></button>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="dr-status-grid">';
        
        // Cola
        $queue_class = $queue_status['pending_count'] > 0 ? 'has-items' : '';
        echo '<div class="dr-status-card ' . $queue_class . '">';
        echo '<div class="dr-status-icon"><span class="dashicons dashicons-list-view"></span></div>';
        echo '<div class="dr-status-info">';
        echo '<span class="dr-status-value">' . esc_html($queue_status['pending_count']) . '</span>';
        echo '<span class="dr-status-label">En cola</span>';
        echo '</div>';
        echo '</div>';
        
        // Estado proceso
        $running_class = $queue_status['is_running'] ? 'running' : 'idle';
        echo '<div class="dr-status-card ' . $running_class . '">';
        echo '<div class="dr-status-icon"><span class="dashicons dashicons-' . ($queue_status['is_running'] ? 'update dr-spin' : 'clock') . '"></span></div>';
        echo '<div class="dr-status-info">';
        echo '<span class="dr-status-value">' . ($queue_status['is_running'] ? 'Activo' : 'Idle') . '</span>';
        echo '<span class="dr-status-label">Worker</span>';
        echo '</div>';
        echo '</div>';
        
        // Openprovider
        $op_class = $op_configured ? 'connected' : 'disconnected';
        echo '<div class="dr-status-card ' . $op_class . '">';
        echo '<div class="dr-status-icon"><span class="dashicons dashicons-' . ($op_configured ? 'yes-alt' : 'dismiss') . '"></span></div>';
        echo '<div class="dr-status-info">';
        echo '<span class="dr-status-value">' . ($op_configured ? 'OK' : 'N/A') . '</span>';
        echo '<span class="dr-status-label">Openprovider</span>';
        echo '</div>';
        echo '</div>';
        
        // Alertas PHP
        $alerts_class = $alerts_count > 0 ? 'has-alerts' : 'ok';
        echo '<div class="dr-status-card ' . $alerts_class . '">';
        echo '<div class="dr-status-icon"><span class="dashicons dashicons-' . ($alerts_count > 0 ? 'warning' : 'yes-alt') . '"></span></div>';
        echo '<div class="dr-status-info">';
        echo '<span class="dr-status-value">' . ($alerts_count > 0 ? $alerts_count : 'OK') . '</span>';
        echo '<span class="dr-status-label">Alertas PHP</span>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>'; // .dr-status-grid
        
        // Botones de acción
        echo '<div class="dr-status-buttons">';
        echo '<button type="button" class="dr-btn dr-btn-primary" id="dr-process-queue-now" ' . ($queue_status['is_running'] ? 'disabled' : '') . '>';
        echo '<span class="dashicons dashicons-controls-play"></span> Procesar cola</button>';
        echo '<button type="button" class="dr-btn dr-btn-secondary" id="dr-refresh-all-php" title="Actualizar PHP info de todos los dominios">';
        echo '<span class="dashicons dashicons-search"></span> Scan PHP</button>';
        echo '<button type="button" class="dr-btn dr-btn-secondary" id="dr-view-alerts" title="Ver alertas de PHP">';
        echo '<span class="dashicons dashicons-warning"></span> Alertas</button>';
        echo '<button type="button" class="dr-btn dr-btn-secondary" id="dr-view-activity" title="Ver historial de actividad">';
        echo '<span class="dashicons dashicons-backup"></span> Historial</button>';
        echo '<a href="' . admin_url('admin.php?page=dominios-reseller-presets') . '" class="dr-btn dr-btn-ghost">';
        echo '<span class="dashicons dashicons-admin-settings"></span> Presets</a>';
        echo '</div>';
        
        // Lista de pendientes
        if ($queue_status['pending_count'] > 0) {
            echo '<div class="dr-pending-list">';
            echo '<span class="dr-pending-label">Pendientes:</span>';
            foreach ($queue_status['pending_items'] as $domain) {
                echo '<span class="dr-pending-tag">' . esc_html($domain) . '</span>';
            }
            echo '</div>';
        }
        
        echo '</div>'; // .dr-system-status
        
        // Panel de Alertas (oculto por defecto)
        echo '<div id="dr-alerts-panel" class="dr-alerts-panel" style="display:none;">';
        echo '<h3>⚠️ Alertas de PHP <button type="button" class="button button-small dr-close-panel" style="float:right;">✕</button></h3>';
        echo '<div id="dr-alerts-content"><p>Cargando...</p></div>';
        echo '</div>';
        
        // Panel de Activity Log (oculto por defecto)
        echo '<div id="dr-activity-panel" class="dr-activity-panel" style="display:none;">';
        echo '<h3>📜 Historial de Actividad <button type="button" class="button button-small dr-close-panel" style="float:right;">✕</button></h3>';
        echo '<div class="dr-activity-filters">';
        echo '<select id="dr-activity-type-filter">';
        echo '<option value="">Todos los tipos</option>';
        echo '<option value="cf_migration">Migraciones CF</option>';
        echo '<option value="endpoint_deploy">Deploy Endpoints</option>';
        echo '<option value="ns_update">Cambios NS</option>';
        echo '<option value="php_scan">Scans PHP</option>';
        echo '<option value="cron_php_scan">Cron PHP</option>';
        echo '</select>';
        echo '</div>';
        echo '<div id="dr-activity-content"><p>Cargando...</p></div>';
        echo '</div>';
    }

    /**
     * Renderizar lista de dominios
     */
    private function render_domains_list(): void {
        global $wpdb;
        $domains_table = $wpdb->prefix . 'dominios_reseller';
        
        // Obtener dominios únicos por primary_domain (sin duplicar por server/status)
        $domains = $wpdb->get_results(
            "SELECT primary_domain, 
                    MAX(server) as server, 
                    MAX(status) as status,
                    MIN(domain) as sample_domain,
                    COUNT(*) as domain_count
             FROM $domains_table 
             WHERE primary_domain IS NOT NULL AND primary_domain != ''
             GROUP BY primary_domain
             ORDER BY primary_domain ASC",
            ARRAY_A
        );

        if (empty($domains)) {
            echo '<div class="notice notice-warning"><p>No hay dominios para mostrar. Sincroniza primero desde WHM.</p></div>';
            return;
        }

        // Obtener presets disponibles (con fallback si tabla vacía)
        $presets = Dominios_Reseller_Onboarding_DB::get_presets();
        if (empty($presets)) {
            // Fallback: preset básico hardcodeado
            $presets = [
                [
                    'preset_key' => 'wp',
                    'name' => 'WordPress Básico',
                    'description' => 'Configuración optimizada para WordPress'
                ]
            ];
        }
        
        // Obtener estados de onboarding
        $onboarding_states = [];
        $all_primary_domains = array_column($domains, 'primary_domain');
        foreach ($all_primary_domains as $pd) {
            $state = Dominios_Reseller_Onboarding_DB::get_onboarding_state($pd);
            if ($state) {
                $onboarding_states[$pd] = $state;
            }
        }

        // Verificar estado en CF
        $cf_service = Dominios_Reseller_Cloudflare_Service::get_instance();
        $cf_matches = $cf_service->batch_match_domains($all_primary_domains);

        // Openprovider configurado?
        $op_configured = class_exists('Dominios_Reseller_Openprovider_Service') && 
                        Dominios_Reseller_Openprovider_Service::get_instance()->is_configured();

        // Header con toggle de vista
        echo '<div class="dr-domains-header">';
        echo '<h2 style="margin:0;">🌐 Dominios (' . count($domains) . ')</h2>';
        echo '<div class="dr-view-toggle">';
        echo '<button type="button" class="dr-view-btn active" data-view="cards" title="Vista Cards">🃏 Cards</button>';
        echo '<button type="button" class="dr-view-btn" data-view="list" title="Vista Lista SEMrush">📋 Lista</button>';
        echo '</div>';
        echo '</div>';

        // === VISTA CARDS ===
        echo '<div id="dr-view-cards" class="dr-domains-grid">';
        foreach ($domains as $domain) {
            $pd = $domain['primary_domain'];
            $state = $onboarding_states[$pd] ?? null;
            $cf_match = $cf_matches[$pd] ?? null;
            
            $this->render_domain_card($domain, $state, $cf_match, $presets, $op_configured);
        }
        echo '</div>';

        // === VISTA LISTA SEMRUSH ===
        echo '<div id="dr-view-list" class="dr-domains-list" style="display:none;">';
        $this->render_domains_table($domains, $onboarding_states, $cf_matches, $presets, $op_configured);
        echo '</div>';
    }

    /**
     * Renderizar tabla de dominios estilo SEMrush
     */
    private function render_domains_table(array $domains, array $states, array $cf_matches, array $presets, bool $op_configured): void {
        echo '<table class="dr-semrush-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th class="col-status"></th>';
        echo '<th class="col-domain">Dominio</th>';
        echo '<th class="col-server">Server</th>';
        echo '<th class="col-cf">CF</th>';
        echo '<th class="col-op">OP</th>';
        echo '<th class="col-ns">Nameservers</th>';
        echo '<th class="col-preset">Preset</th>';
        echo '<th class="col-state">Estado</th>';
        echo '<th class="col-actions">Acciones</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($domains as $domain) {
            $pd = $domain['primary_domain'];
            $state = $states[$pd] ?? null;
            $cf_match = $cf_matches[$pd] ?? null;
            $onboarding_state = $state['state'] ?? 'none';
            $status_indicator = $this->get_status_indicator($onboarding_state, $cf_match);
            
            echo '<tr class="dr-row" data-domain="' . esc_attr($pd) . '">';
            
            // Status dot
            echo '<td class="col-status">';
            echo '<span class="dr-status-dot ' . esc_attr($status_indicator['class']) . '" title="' . esc_attr($status_indicator['title']) . '"></span>';
            echo '</td>';
            
            // Domain
            echo '<td class="col-domain">';
            echo '<a href="https://' . esc_attr($pd) . '" target="_blank" class="dr-domain-link">' . esc_html($pd) . '</a>';
            echo '</td>';
            
            // Server
            echo '<td class="col-server">';
            echo '<span class="dr-server-badge dr-server-' . esc_attr(strtolower($domain['server'])) . '">' . esc_html(strtoupper($domain['server'])) . '</span>';
            echo '</td>';
            
            // CF status (loaded via AJAX)
            echo '<td class="col-cf">';
            echo '<span class="dr-pilot dr-pilot-cf dr-pilot-loading" data-domain="' . esc_attr($pd) . '" title="Cloudflare">CF</span>';
            echo '</td>';
            
            // OP status (loaded via AJAX)
            echo '<td class="col-op">';
            echo '<span class="dr-pilot dr-pilot-op dr-pilot-loading" data-domain="' . esc_attr($pd) . '" title="Openprovider">OP</span>';
            echo '</td>';
            
            // Nameservers (loaded via AJAX)
            echo '<td class="col-ns dr-ns-row" data-domain="' . esc_attr($pd) . '">';
            echo '<span class="dr-ns-loading">Cargando...</span>';
            echo '</td>';
            
            // Preset selector
            echo '<td class="col-preset">';
            if (in_array($onboarding_state, ['none', 'error', 'partial', 'needs_manual_ns', 'pending_ns'])) {
                echo '<div class="dr-preset-group">';
                echo '<select class="dr-preset-mini" data-domain="' . esc_attr($pd) . '">';
                foreach ($presets as $preset) {
                    $selected = ($state && ($state['preset_key'] ?? '') === $preset['preset_key']) ? 'selected' : '';
                    echo '<option value="' . esc_attr($preset['preset_key']) . '" ' . $selected . '>' . esc_html($preset['name']) . '</option>';
                }
                echo '</select>';
                echo '<button class="dr-btn-preview" title="Ver configuración del preset">Ver</button>';
                echo '</div>';
            } else {
                echo '<span class="dr-preset-label">' . esc_html($state['preset_key'] ?? '-') . '</span>';
                echo ' <button class="dr-btn-preview dr-btn-preview-small" data-preset="' . esc_attr($state['preset_key'] ?? 'wp') . '" title="Ver configuración aplicada">Ver</button>';
            }
            echo '</td>';
            
            // State
            echo '<td class="col-state">';
            $state_labels = [
                'none' => 'Sin iniciar',
                'pending' => 'Pendiente',
                'running' => 'Ejecutando',
                'onboarded' => 'Completado',
                'error' => 'Error',
                'partial' => 'Parcial',
                'needs_manual_ns' => 'NS manual',
                'pending_ns' => 'NS pendientes'
            ];
            echo '<span class="dr-state-' . esc_attr($onboarding_state) . '">' . ($state_labels[$onboarding_state] ?? $onboarding_state) . '</span>';
            echo '</td>';
            
            // Actions
            echo '<td class="col-actions">';
            if (in_array($onboarding_state, ['none', 'error', 'partial', 'needs_manual_ns', 'pending_ns'])) {
                echo '<button class="dr-btn-apply dr-btn-mini" data-domain="' . esc_attr($pd) . '" title="Aplicar onboarding">Aplicar</button>';
                if ($op_configured) {
                    $auto_checked = ($state && !empty($state['auto_update_ns'])) ? 'checked' : '';
                    echo '<label class="dr-auto-ns-mini" title="AutoNS">';
                    echo '<input type="checkbox" class="dr-auto-ns-check" data-domain="' . esc_attr($pd) . '" ' . $auto_checked . '>';
                    echo '</label>';
                }
            }
            if ($state && !empty($state['last_run_id'])) {
                echo '<button class="dr-btn-logs dr-btn-mini" data-domain="' . esc_attr($pd) . '" data-run-id="' . esc_attr($state['last_run_id']) . '" title="Ver logs">Logs</button>';
            }
            echo '<button class="dr-btn-refresh-domain dr-btn-mini" data-domain="' . esc_attr($pd) . '" title="Actualizar estado desde CF y DNS">🔄</button>';
            echo '</td>';
            
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    }

    /**
     * Renderizar card de dominio - Estilo Semrush condensado
     */
    private function render_domain_card(array $domain, ?array $state, ?array $cf_match, array $presets, bool $op_configured): void {
        global $wpdb;
        $pd = $domain['primary_domain'];
        $onboarding_state = $state['state'] ?? 'none';
        $target_ns = !empty($state['nameservers']) ? json_decode($state['nameservers'], true) : [];
        
        // Obtener datos del endpoint de la BD
        $table = $wpdb->prefix . 'dominios_reseller';
        $endpoint_data = $wpdb->get_row($wpdb->prepare(
            "SELECT endpoint_token, wp_readiness_score, php_info_updated_at FROM {$table} WHERE primary_domain = %s AND is_primary = 1",
            $pd
        ));
        
        // Determinar indicador de estado
        $status_indicator = $this->get_status_indicator($onboarding_state, $cf_match);

        echo '<div class="dr-card" data-domain="' . esc_attr($pd) . '">';
        
        // === ROW 1: Dominio + badges + action ===
        echo '<div class="dr-card-row dr-card-main">';
        
        // Status dot + domain name
        echo '<div class="dr-domain-info">';
        echo '<span class="dr-status-dot ' . esc_attr($status_indicator['class']) . '" title="' . esc_attr($status_indicator['title']) . '"></span>';
        echo '<span class="dr-domain-name">' . esc_html($pd) . '</span>';
        echo '</div>';
        
        // Badges row (server + pilotos CF/OP/PHP + icono endpoint)
        echo '<div class="dr-badges-row">';
        echo '<span class="dr-server-badge dr-server-' . esc_attr(strtolower($domain['server'])) . '">' . esc_html(strtoupper($domain['server'])) . '</span>';
        // Pilotos CF, OP se añaden por AJAX
        echo '<span class="dr-pilot dr-pilot-cf dr-pilot-loading" data-domain="' . esc_attr($pd) . '" title="Cloudflare">CF</span>';
        echo '<span class="dr-pilot dr-pilot-op dr-pilot-loading" data-domain="' . esc_attr($pd) . '" title="Openprovider">OP</span>';
        
        // Icono de endpoint (archivo desplegado)
        $has_endpoint = !empty($endpoint_data->endpoint_token);
        $endpoint_class = $has_endpoint ? 'has-endpoint' : 'no-endpoint';
        $endpoint_title = $has_endpoint ? 'Endpoint activo' : 'Sin endpoint';
        echo '<span class="dr-pilot dr-pilot-file ' . $endpoint_class . '" data-domain="' . esc_attr($pd) . '" title="' . $endpoint_title . '"><span class="dashicons dashicons-media-code"></span></span>';
        
        // Piloto PHP (cargado por AJAX)
        echo '<span class="dr-pilot dr-pilot-php dr-pilot-loading" data-domain="' . esc_attr($pd) . '" title="PHP Info">...</span>';
        echo '</div>';
        
        // Action button
        echo '<div class="dr-card-actions-mini">';
        if (in_array($onboarding_state, ['none', 'error', 'partial', 'needs_manual_ns', 'pending_ns'])) {
            echo '<button class="dr-btn-apply" data-domain="' . esc_attr($pd) . '" title="Aplicar onboarding">▶</button>';
        } elseif ($onboarding_state === 'onboarded') {
            echo '<span class="dr-check" title="Completado">✓</span>';
        } elseif (in_array($onboarding_state, ['pending', 'running'])) {
            echo '<span class="dr-spinner" title="Procesando"></span>';
        }
        
        // PHP Config button (always visible)
        echo '<button class="dr-btn-php-config" data-domain="' . esc_attr($pd) . '" title="Configurar PHP">⚙</button>';
        
        // Refresh domain status button (always visible)
        echo '<button class="dr-btn-refresh-domain" data-domain="' . esc_attr($pd) . '" title="Actualizar estado desde CF y DNS">🔄</button>';

        // Reactivar zona en CF (útil cuando está en estado moved/pending tras cambiar NS)
        if (!empty($state['zone_id']) && in_array($onboarding_state, ['pending_ns', 'needs_manual_ns', 'partial', 'error'])) {
            echo '<button class="dr-btn-reactivate" data-domain="' . esc_attr($pd) . '" data-zone-id="' . esc_attr($state['zone_id']) . '" title="Pedir a Cloudflare que vuelva a comprobar los NS (zona moved/pending)"><span class="dashicons dashicons-update-alt"></span> Reactivar CF</button>';
        }

        echo '</div>';
        
        echo '</div>'; // End row 1
        
        // === ROW 2: Nameservers actuales (cargado por AJAX) ===
        echo '<div class="dr-card-row dr-ns-row" data-domain="' . esc_attr($pd) . '">';
        echo '<span class="dr-ns-loading">⏳ Cargando NS...</span>';
        echo '</div>';
        
        // === ROW 3: Controles (preset + autoNS + logs) ===
        echo '<div class="dr-card-row dr-controls-row">';
        
        // Selector preset inline
        if (in_array($onboarding_state, ['none', 'error', 'partial', 'needs_manual_ns', 'pending_ns'])) {
            echo '<select class="dr-preset-mini" data-domain="' . esc_attr($pd) . '">';
            foreach ($presets as $preset) {
                $selected = ($state && ($state['preset_key'] ?? '') === $preset['preset_key']) ? 'selected' : '';
                echo '<option value="' . esc_attr($preset['preset_key']) . '" ' . $selected . '>' . esc_html($preset['name']) . '</option>';
            }
            echo '</select>';
            
            // Toggle auto NS si Openprovider está configurado
            if ($op_configured) {
                $auto_checked = ($state && !empty($state['auto_update_ns'])) ? 'checked' : '';
                echo '<label class="dr-auto-ns-mini" title="Cambiar NS automáticamente en Openprovider">';
                echo '<input type="checkbox" class="dr-auto-ns-check" data-domain="' . esc_attr($pd) . '" ' . $auto_checked . '>';
                echo '<span>AutoNS</span>';
                echo '</label>';
            }
        } elseif ($onboarding_state === 'onboarded') {
            echo '<span class="dr-status-text success">✓ Completado</span>';
        } elseif (in_array($onboarding_state, ['pending', 'running'])) {
            echo '<span class="dr-status-text processing">⏳ Procesando...</span>';
        }
        
        // Ver logs si hay
        if ($state && !empty($state['last_run_id'])) {
            echo '<button class="dr-btn-logs" data-domain="' . esc_attr($pd) . '" data-run-id="' . esc_attr($state['last_run_id']) . '" title="Ver logs">📋</button>';
        }
        
        echo '</div>'; // End row 3
        
        // === ROW 4: Error o NS objetivo (si aplica) ===
        if ($state && !empty($state['last_error']) && in_array($onboarding_state, ['error', 'partial', 'needs_manual_ns', 'pending_ns'])) {
            echo '<div class="dr-card-row dr-error-row">';
            echo '<small>⚠️ ' . esc_html(substr($state['last_error'], 0, 100)) . '</small>';
            echo '</div>';
        }
        
        if ($onboarding_state === 'needs_manual_ns' && !empty($target_ns)) {
            echo '<div class="dr-card-row dr-target-ns">';
            echo '<span class="dr-label">→ Cambiar NS a:</span>';
            foreach ($target_ns as $ns) {
                echo '<code class="dr-ns-code" data-copy="' . esc_attr($ns) . '">' . esc_html($ns) . '</code>';
            }
            echo '</div>';
        }
        
        echo '</div>'; // End card
    }

    /**
     * Obtener indicador de estado para el punto de color
     */
    private function get_status_indicator(string $state, ?array $cf_match): array {
        if ($state === 'onboarded') {
            return ['class' => 'dr-dot-success', 'title' => 'Onboarding completado'];
        }
        if ($state === 'running' || $state === 'pending') {
            return ['class' => 'dr-dot-processing', 'title' => 'En proceso'];
        }
        if ($state === 'error' || $state === 'needs_manual_ns') {
            return ['class' => 'dr-dot-error', 'title' => 'Requiere atención'];
        }
        if ($state === 'pending_ns') {
            return ['class' => 'dr-dot-warning', 'title' => 'NS pendientes de apuntar a Cloudflare'];
        }
        if ($state === 'partial') {
            return ['class' => 'dr-dot-warning', 'title' => 'Parcialmente completado'];
        }
        if ($cf_match) {
            return ['class' => 'dr-dot-cf', 'title' => 'Ya en Cloudflare'];
        }
        return ['class' => 'dr-dot-none', 'title' => 'Sin procesar'];
    }

    /**
     * Obtener etiqueta de estado
     */
    private function get_state_label(string $state): string {
        return match($state) {
            'none'           => 'Sin procesar',
            'in_cf'          => 'Ya en CF',
            'pending'        => 'En cola',
            'running'        => 'Procesando',
            'onboarded'      => 'Completado',
            'error'          => 'Error',
            'needs_manual_ns'=> 'NS manual',
            'pending_ns'     => 'NS pendientes',
            'partial'        => 'Parcial',
            default          => $state
        };
    }

    /**
     * Renderizar estilos CSS
     */
    private function render_styles(): void {
        ?>
        <style>
        .dr-onboarding-page { max-width: 1400px; }
        
        /* ========================================
         * SISTEMA DE ESTADO - ESTILO SEMRUSH
         * ======================================== */
        .dr-system-status {
            background: #fff;
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 20px 24px;
            margin-bottom: 20px;
        }
        .dr-status-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        .dr-status-header h3 {
            margin: 0;
            font-size: 15px;
            font-weight: 600;
            color: #1a1f36;
        }
        .dr-btn-icon {
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 6px;
            border-radius: 4px;
            color: #6b7280;
            transition: all 0.15s;
        }
        .dr-btn-icon:hover {
            background: #f3f4f6;
            color: #1a1f36;
        }
        .dr-btn-icon .dashicons {
            font-size: 18px;
            width: 18px;
            height: 18px;
        }
        
        /* Grid de estados */
        .dr-status-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }
        .dr-status-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid transparent;
            transition: all 0.2s;
        }
        .dr-status-card:hover {
            background: #f3f4f6;
        }
        .dr-status-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: #e5e7eb;
            color: #6b7280;
        }
        .dr-status-icon .dashicons {
            font-size: 20px;
            width: 20px;
            height: 20px;
        }
        .dr-status-info {
            display: flex;
            flex-direction: column;
        }
        .dr-status-value {
            font-size: 18px;
            font-weight: 600;
            color: #1a1f36;
            line-height: 1.2;
        }
        .dr-status-label {
            font-size: 12px;
            color: #6b7280;
            margin-top: 2px;
        }
        
        /* Estados de las cards */
        .dr-status-card.has-items .dr-status-icon {
            background: #dbeafe;
            color: #2563eb;
        }
        .dr-status-card.has-items .dr-status-value {
            color: #2563eb;
        }
        .dr-status-card.running .dr-status-icon {
            background: #dcfce7;
            color: #16a34a;
        }
        .dr-status-card.running .dr-status-value {
            color: #16a34a;
        }
        .dr-status-card.idle .dr-status-icon {
            background: #f3f4f6;
            color: #9ca3af;
        }
        .dr-status-card.connected .dr-status-icon {
            background: #dcfce7;
            color: #16a34a;
        }
        .dr-status-card.connected .dr-status-value {
            color: #16a34a;
        }
        .dr-status-card.disconnected .dr-status-icon {
            background: #fef3c7;
            color: #d97706;
        }
        .dr-status-card.ok .dr-status-icon {
            background: #dcfce7;
            color: #16a34a;
        }
        .dr-status-card.ok .dr-status-value {
            color: #16a34a;
        }
        .dr-status-card.has-alerts .dr-status-icon {
            background: #fee2e2;
            color: #dc2626;
        }
        .dr-status-card.has-alerts .dr-status-value {
            color: #dc2626;
        }
        
        /* Animación spin */
        .dr-spin {
            animation: dr-spin 1s linear infinite;
        }
        @keyframes dr-spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* Botones de acción */
        .dr-status-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .dr-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            font-size: 13px;
            font-weight: 500;
            border-radius: 6px;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.15s;
            text-decoration: none;
        }
        .dr-btn .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
        }
        .dr-btn-primary {
            background: #2563eb;
            color: #fff;
            border-color: #2563eb;
        }
        .dr-btn-primary:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
            color: #fff;
        }
        .dr-btn-primary:disabled {
            background: #93c5fd;
            border-color: #93c5fd;
            cursor: not-allowed;
        }
        .dr-btn-secondary {
            background: #fff;
            color: #374151;
            border-color: #d1d5db;
        }
        .dr-btn-secondary:hover {
            background: #f9fafb;
            border-color: #9ca3af;
            color: #1f2937;
        }
        .dr-btn-ghost {
            background: transparent;
            color: #6b7280;
            border-color: transparent;
        }
        .dr-btn-ghost:hover {
            background: #f3f4f6;
            color: #374151;
        }
        
        /* Lista de pendientes */
        .dr-pending-list {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
        }
        .dr-pending-label {
            font-size: 13px;
            font-weight: 500;
            color: #6b7280;
        }
        .dr-pending-tag {
            display: inline-block;
            padding: 4px 10px;
            background: #dbeafe;
            color: #1d4ed8;
            font-size: 12px;
            font-weight: 500;
            border-radius: 12px;
        }
        
        /* Responsive */
        @media (max-width: 900px) {
            .dr-status-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 500px) {
            .dr-status-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Legacy - mantener compatibilidad */
        .dr-queue-panel {
            background: #fff;
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
        }
        .dr-queue-panel h2 { margin-top: 0; font-size: 16px; }
        .dr-queue-stats {
            display: flex;
            gap: 30px;
            margin: 15px 0;
        }
        .dr-stat { text-align: center; }
        .dr-stat-value { display: block; font-size: 24px; font-weight: 600; }
        .dr-stat-label { color: #666; font-size: 12px; }
        .dr-stat.dr-stat-alert .dr-stat-value { color: #dc3232; }
        .dr-queue-actions { margin-top: 15px; }
        .dr-queue-actions .button { margin-right: 5px; margin-bottom: 5px; }
        .dr-pending-list {
            margin-top: 10px;
            padding: 10px;
            background: #f0f6fc;
            border-radius: 4px;
            font-size: 13px;
        }
        
        /* Paneles de Alertas y Activity */
        .dr-alerts-panel, .dr-activity-panel {
            background: #fff;
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 15px;
        }
        .dr-alerts-panel h3, .dr-activity-panel h3 {
            margin: 0 0 15px 0;
            font-size: 14px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 10px;
        }
        .dr-alert-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 8px 10px;
            margin-bottom: 6px;
            border-radius: 4px;
            font-size: 13px;
        }
        .dr-alert-item.critical { background: #fef2f2; border-left: 3px solid #dc3232; }
        .dr-alert-item.warning { background: #fffbeb; border-left: 3px solid #f0ad4e; }
        .dr-alert-item.info { background: #eff6ff; border-left: 3px solid #00a0d2; }
        .dr-alert-domain { font-weight: 600; min-width: 150px; }
        .dr-alert-message { flex: 1; color: #555; }
        
        .dr-activity-filters { margin-bottom: 15px; }
        .dr-activity-item {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f1;
            font-size: 13px;
        }
        .dr-activity-row {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .dr-activity-time { color: #999; font-size: 11px; min-width: 100px; }
        .dr-activity-type {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 3px;
            background: #e5e7eb;
            min-width: 80px;
            text-align: center;
        }
        .dr-activity-type.cf_migration { background: #f48120; color: #fff; }
        .dr-activity-type.endpoint_deploy { background: #9b59b6; color: #fff; }
        .dr-activity-type.ns_update { background: #3498db; color: #fff; }
        .dr-activity-type.php_scan { background: #27ae60; color: #fff; }
        .dr-activity-type.cron_php_scan { background: #2ecc71; color: #fff; }
        .dr-activity-domain { font-weight: 500; min-width: 150px; }
        .dr-activity-desc { flex: 1; color: #555; }
        
        /* Detalles expandibles del historial */
        .dr-activity-details {
            margin-top: 10px;
            padding: 12px;
            background: #f9fafb;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
        }
        .dr-details-section {
            margin-bottom: 10px;
            padding: 8px 10px;
            border-radius: 4px;
        }
        .dr-details-section:last-child { margin-bottom: 0; }
        .dr-details-success { background: #ecfdf5; border-left: 3px solid #10b981; }
        .dr-details-error { background: #fef2f2; border-left: 3px solid #ef4444; }
        .dr-details-warning { background: #fffbeb; border-left: 3px solid #f59e0b; }
        .dr-details-section strong { display: block; margin-bottom: 5px; }
        .dr-details-section ul {
            margin: 0;
            padding-left: 20px;
            font-size: 12px;
        }
        .dr-details-section li {
            margin: 3px 0;
            color: #374151;
        }
        .dr-details-section code {
            background: rgba(0,0,0,0.05);
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 11px;
        }
        .dr-toggle-details {
            margin-left: auto !important;
            font-size: 11px !important;
            padding: 2px 8px !important;
        }
        
        /* Panel de leyenda */
        .dr-legend-panel {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 10px 15px;
            margin-bottom: 15px;
        }
        .dr-legend-items {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }
        .dr-legend-item {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            color: #4b5563;
        }
        .dr-legend-sep {
            color: #d1d5db;
            margin: 0 4px;
        }
        
        /* Grid de cards condensadas */
        .dr-domains-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 12px;
        }
        
        /* Card condensada tipo Semrush */
        .dr-card {
            background: #fff;
            border: 1px solid #e1e5e9;
            border-radius: 6px;
            padding: 12px 14px;
            transition: box-shadow 0.15s, border-color 0.15s;
        }
        .dr-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-color: #c5cad0;
        }
        
        /* Row base */
        .dr-card-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .dr-card-row:not(:last-child) { margin-bottom: 8px; }
        
        /* Row 1: Dominio + status + server + action */
        .dr-domain-name {
            font-weight: 600;
            font-size: 14px;
            color: #1a1a1a;
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        /* Status dot */
        .dr-status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .dr-dot-success { background: #22c55e; }
        .dr-dot-error { background: #ef4444; }
        .dr-dot-warning { background: #f59e0b; }
        .dr-dot-processing { background: #3b82f6; animation: pulse-dot 1.5s infinite; }
        .dr-dot-cf { background: #f97316; }
        .dr-dot-none { background: #9ca3af; }
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(0.9); }
        }
        
        /* Server badge mini */
        .dr-server-badge {
            font-size: 9px;
            font-weight: 600;
            padding: 2px 5px;
            border-radius: 3px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .dr-server-uk { background: #dbeafe; color: #1e40af; }
        .dr-server-usa { background: #fef3c7; color: #92400e; }
        .dr-server-unknown { background: #f3f4f6; color: #6b7280; }
        
        /* Badges row (server + pilots) */
        .dr-badges-row {
            display: flex;
            align-items: center;
            gap: 4px;
            flex-shrink: 0;
        }
        
        /* Pilotos CF/OP */
        .dr-pilot {
            font-size: 8px;
            font-weight: 700;
            padding: 2px 4px;
            border-radius: 3px;
            text-transform: uppercase;
            letter-spacing: 0.2px;
            min-width: 20px;
            text-align: center;
        }
        .dr-pilot-loading {
            background: #f3f4f6;
            color: #9ca3af;
            animation: pulse-pilot 1.5s infinite;
        }
        @keyframes pulse-pilot {
            0%, 100% { opacity: 0.5; }
            50% { opacity: 1; }
        }
        /* CF Pilot states */
        .dr-pilot-cf.active { background: #f97316; color: #fff; }
        .dr-pilot-cf.inactive { background: #fed7aa; color: #9a3412; }
        .dr-pilot-cf.none { background: #e5e7eb; color: #9ca3af; }
        /* OP Pilot states */
        .dr-pilot-op.active { background: #22c55e; color: #fff; }
        .dr-pilot-op.inactive { background: #d1fae5; color: #065f46; }
        .dr-pilot-op.none { background: #e5e7eb; color: #9ca3af; }
        /* Endpoint badge states */
        .dr-pilot-endpoint { font-size: 10px; min-width: 36px; cursor: pointer; }
        .dr-pilot-endpoint.dr-pilot-success { background: #22c55e; color: #fff; }
        .dr-pilot-endpoint.dr-pilot-warning { background: #f59e0b; color: #fff; }
        .dr-pilot-endpoint.dr-pilot-danger { background: #ef4444; color: #fff; }
        /* PHP Pilot states */
        .dr-pilot-php.dr-pilot-success { background: #22c55e; color: #fff; }
        .dr-pilot-php.dr-pilot-warning { background: #f59e0b; color: #fff; }
        .dr-pilot-php.dr-pilot-error { background: #ef4444; color: #fff; }
        .dr-pilot-php.none { background: #e5e7eb; color: #9ca3af; }
        /* File/Endpoint icon */
        .dr-pilot-file { padding: 2px 4px; }
        .dr-pilot-file .dashicons { font-size: 12px; width: 12px; height: 12px; vertical-align: middle; }
        .dr-pilot-file.has-endpoint { background: #dcfce7; color: #16a34a; }
        .dr-pilot-file.no-endpoint { background: #fef3c7; color: #d97706; }
        
        /* Domain info row */
        .dr-domain-info {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            min-width: 0;
        }
        
        /* Card actions mini */
        .dr-card-actions-mini {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        /* Refresh domain button */
        .dr-btn-refresh-domain {
            background: none;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            cursor: pointer;
            padding: 2px 5px;
            font-size: 12px;
            line-height: 1;
            transition: background 0.2s;
        }
        .dr-btn-refresh-domain:hover {
            background: #f3f4f6;
            border-color: #3b82f6;
        }
        .dr-btn-refresh-domain:disabled {
            opacity: 0.5;
            cursor: wait;
        }
        .dr-spin-emoji {
            animation: spin 1s linear infinite;
            display: inline-block;
        }
        
        /* Check mark para completados */
        .dr-check {
            color: #22c55e;
            font-weight: bold;
            font-size: 14px;
        }
        
        /* Spinner para procesando */
        .dr-spinner {
            width: 14px;
            height: 14px;
            border: 2px solid #e5e7eb;
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* NS Loading */
        .dr-ns-loading {
            color: #9ca3af;
            font-style: italic;
            font-size: 11px;
        }
        
        /* CF Badge styles */
        .dr-cf-badge {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 3px;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        .dr-cf-active { background: #fed7aa; color: #9a3412; }
        .dr-cf-none { background: #f3f4f6; color: #9ca3af; }
        
        /* Target NS row */
        .dr-target-ns {
            font-size: 11px;
            color: #059669;
            background: #ecfdf5;
            padding: 6px 8px;
            border-radius: 4px;
        }
        .dr-target-ns .dr-label {
            margin-right: 6px;
            font-weight: 500;
        }
        .dr-ns-code {
            font-family: ui-monospace, monospace;
            background: #d1fae5;
            padding: 1px 4px;
            border-radius: 2px;
            cursor: pointer;
            margin-left: 4px;
        }
        .dr-ns-code:hover { background: #a7f3d0; }

        /* Quick action button */
        .dr-btn-apply {
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.15s;
            background: #166534;
            color: #fff;
        }
        .dr-btn-apply:hover { background: #15803d; }
        .dr-btn-apply:disabled { opacity: 0.6; cursor: not-allowed; }
        
        /* Row 2: NS info */
        .dr-ns-row {
            font-size: 11px;
            color: #6b7280;
            display: flex;
            align-items: center;
            gap: 6px;
            min-height: 18px;
        }
        .dr-ns-row .ns-loading {
            color: #9ca3af;
            font-style: italic;
        }
        .dr-ns-row .ns-values {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .dr-ns-row .ns-item {
            background: #f3f4f6;
            padding: 1px 5px;
            border-radius: 3px;
            font-family: ui-monospace, monospace;
            font-size: 10px;
        }
        .dr-ns-row .ns-provider {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 9px;
            padding: 1px 4px;
            border-radius: 2px;
        }
        .dr-ns-provider-cloudflare { background: #fed7aa; color: #9a3412; }
        .dr-ns-provider-openprovider { background: #d1fae5; color: #065f46; }
        .dr-ns-provider-other { background: #e5e7eb; color: #374151; }
        
        /* Row 3: Controls */
        .dr-controls-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        /* CF badge mini */
        .dr-cf-badge {
            font-size: 9px;
            font-weight: 600;
            padding: 2px 5px;
            border-radius: 3px;
            background: #fed7aa;
            color: #9a3412;
            text-transform: uppercase;
        }
        .dr-cf-badge.active { background: #fbbf24; color: #78350f; }
        
        /* Preset select mini */
        .dr-preset-group {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .dr-preset-mini {
            font-size: 11px;
            padding: 4px 8px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            background: #fff;
            min-width: 120px;
            max-width: 140px;
            cursor: pointer;
            flex-shrink: 0;
        }
        .dr-preset-mini option {
            font-size: 11px;
        }
        .dr-btn-preview {
            font-size: 12px;
            padding: 4px 6px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            background: #f3f4f6;
            cursor: pointer;
            line-height: 1;
        }
        .dr-btn-preview:hover {
            background: #e5e7eb;
            border-color: #9ca3af;
        }
        .dr-btn-preview-small {
            font-size: 10px;
            padding: 2px 4px;
            vertical-align: middle;
        }
        
        /* Modal Preset Preview */
        .dr-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 100000;
            align-items: center;
            justify-content: center;
        }
        .dr-modal-overlay.active {
            display: flex;
        }
        .dr-modal {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .dr-modal-header {
            padding: 16px 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f9fafb;
        }
        .dr-modal-title {
            font-size: 16px;
            font-weight: 600;
            color: #111827;
        }
        .dr-modal-close {
            font-size: 24px;
            cursor: pointer;
            color: #6b7280;
            background: none;
            border: none;
            padding: 0;
            line-height: 1;
        }
        .dr-modal-close:hover {
            color: #111827;
        }
        .dr-modal-body {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
        }
        .dr-preset-section {
            margin-bottom: 20px;
        }
        .dr-preset-section h4 {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin: 0 0 10px 0;
            padding-bottom: 6px;
            border-bottom: 1px solid #e5e7eb;
        }
        .dr-preset-section pre {
            background: #1f2937;
            color: #f9fafb;
            padding: 12px;
            border-radius: 6px;
            font-size: 11px;
            overflow-x: auto;
            margin: 0;
        }
        .dr-preset-info {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 8px;
            font-size: 12px;
        }
        .dr-preset-info dt {
            color: #6b7280;
            font-weight: 500;
        }
        .dr-preset-info dd {
            color: #111827;
            margin: 0;
        }
        .dr-preset-rules-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .dr-preset-rules-list li {
            padding: 8px 12px;
            background: #f9fafb;
            border-radius: 4px;
            margin-bottom: 6px;
            font-size: 12px;
        }
        .dr-preset-rules-list li strong {
            color: #111827;
        }
        .dr-preset-rules-list li code {
            display: block;
            margin-top: 4px;
            font-size: 10px;
            color: #6b7280;
            word-break: break-all;
        }
        .dr-copy-json {
            margin-top: 10px;
            padding: 8px 16px;
            background: #3b82f6;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .dr-copy-json:hover {
            background: #2563eb;
        }
        
        /* Auto NS toggle mini */
        .dr-auto-ns-mini {
            font-size: 10px;
            display: flex;
            align-items: center;
            gap: 4px;
            color: #6b7280;
        }
        .dr-auto-ns-mini input {
            width: 14px;
            height: 14px;
            margin: 0;
        }
        
        /* Logs button mini */
        .dr-btn-logs {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 3px;
            border: 1px solid #d1d5db;
            background: #fff;
            color: #6b7280;
            cursor: pointer;
        }
        .dr-btn-logs:hover { background: #f9fafb; }
        
        /* Status text */
        .dr-status-text {
            font-size: 10px;
            font-weight: 500;
        }
        .dr-status-text.processing { color: #3b82f6; }
        .dr-status-text.success { color: #22c55e; }
        .dr-status-text.error { color: #ef4444; }
        
        /* Row 4: Error/Target NS */
        .dr-error-row {
            font-size: 10px;
            color: #dc2626;
            background: #fef2f2;
            padding: 4px 8px;
            border-radius: 4px;
            margin-top: 4px;
        }
        .dr-target-ns-row {
            font-size: 10px;
            color: #059669;
            background: #ecfdf5;
            padding: 4px 8px;
            border-radius: 4px;
            margin-top: 4px;
        }
        .dr-target-ns-row code {
            font-family: ui-monospace, monospace;
            background: #d1fae5;
            padding: 0 3px;
            border-radius: 2px;
            cursor: pointer;
        }
        
        /* Modal de logs */
        .dr-logs-modal {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 100000;
            align-items: center;
            justify-content: center;
        }
        .dr-logs-modal.active { display: flex; }
        .dr-logs-content {
            background: #fff;
            border-radius: 8px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow: auto;
            padding: 20px;
        }
        .dr-logs-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .dr-logs-close { cursor: pointer; font-size: 24px; color: #666; }
        .dr-log-entry {
            padding: 12px;
            margin: 8px 0;
            border-radius: 6px;
            border-left: 4px solid;
            font-size: 13px;
        }
        .dr-log-info { 
            background: #e8f4f8; 
            border-left-color: #17a2b8;
        }
        .dr-log-warning { 
            background: #fff3cd; 
            border-left-color: #ffc107;
        }
        .dr-log-error { 
            background: #f8d7da; 
            border-left-color: #dc3545;
        }
        .dr-log-header {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            flex-wrap: wrap;
            gap: 8px;
        }
        .dr-log-time { 
            color: #666; 
            font-size: 11px;
            font-weight: bold;
        }
        .dr-log-step { 
            background: #6c757d; 
            color: #fff; 
            padding: 2px 8px; 
            border-radius: 12px; 
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .dr-log-level {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .dr-log-level-info {
            background: #17a2b8;
            color: white;
        }
        .dr-log-level-warning {
            background: #ffc107;
            color: black;
        }
        .dr-log-level-error {
            background: #dc3545;
            color: white;
        }
        .dr-log-message {
            margin-bottom: 8px;
            line-height: 1.4;
        }
        .dr-log-data {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 8px;
            margin-top: 8px;
        }
        .dr-log-data pre {
            background: #fff;
            border: 1px solid #ced4da;
            border-radius: 3px;
            padding: 8px;
            margin: 4px 0 0 0;
            font-size: 11px;
            overflow-x: auto;
            max-height: 200px;
            overflow-y: auto;
        }
        
        /* === VISTA TOGGLE === */
        .dr-domains-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        .dr-view-toggle {
            display: flex;
            gap: 4px;
            background: #f3f4f6;
            padding: 3px;
            border-radius: 6px;
        }
        .dr-view-btn {
            padding: 6px 12px;
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 12px;
            border-radius: 4px;
            transition: all 0.2s;
            color: #6b7280;
        }
        .dr-view-btn:hover { color: #111827; }
        .dr-view-btn.active {
            background: #fff;
            color: #111827;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            font-weight: 500;
        }
        
        /* === TABLA SEMRUSH === */
        .dr-semrush-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            font-size: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            border-radius: 8px;
            overflow: hidden;
        }
        .dr-semrush-table thead {
            background: #f9fafb;
            border-bottom: 2px solid #e5e7eb;
        }
        .dr-semrush-table th {
            padding: 10px 8px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        .dr-semrush-table td {
            padding: 8px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }
        .dr-semrush-table tbody tr:hover {
            background: #f9fafb;
        }
        .dr-semrush-table .col-status { width: 30px; text-align: center; }
        .dr-semrush-table .col-domain { min-width: 180px; }
        .dr-semrush-table .col-server { width: 70px; }
        .dr-semrush-table .col-cf,
        .dr-semrush-table .col-op { width: 40px; text-align: center; }
        .dr-semrush-table .col-ns { min-width: 200px; }
        .dr-semrush-table .col-preset { width: 120px; }
        .dr-semrush-table .col-state { width: 110px; }
        .dr-semrush-table .col-actions { width: 100px; }
        
        .dr-domain-link {
            color: #1d4ed8;
            text-decoration: none;
            font-weight: 500;
        }
        .dr-domain-link:hover {
            text-decoration: underline;
        }
        
        .dr-btn-mini {
            padding: 3px 8px;
            font-size: 11px;
            border-radius: 4px;
            border: 1px solid #d1d5db;
            background: #fff;
            cursor: pointer;
            margin-right: 3px;
        }
        .dr-btn-mini:hover {
            background: #f3f4f6;
        }
        .dr-btn-apply.dr-btn-mini {
            background: #10b981;
            color: #fff;
            border-color: #10b981;
        }
        .dr-btn-apply.dr-btn-mini:hover {
            background: #059669;
        }
        
        .dr-preset-label {
            color: #6b7280;
            font-size: 11px;
            font-style: italic;
        }
        
        .dr-state-none { color: #9ca3af; }
        .dr-state-pending { color: #f59e0b; }
        .dr-state-running { color: #3b82f6; }
        .dr-state-onboarded { color: #10b981; font-weight: 500; }
        .dr-state-error { color: #ef4444; }
        .dr-state-partial { color: #f97316; }
        .dr-state-needs_manual_ns { color: #8b5cf6; }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .dr-semrush-table .col-ns { display: none; }
        }
        
        /* =============================================
           PHP MODAL STYLES
           ============================================= */
        
        .dr-php-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 100000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s, visibility 0.3s;
        }
        .dr-php-modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .dr-php-modal {
            background: #fff;
            border-radius: 12px;
            width: 90%;
            max-width: 700px;
            max-height: 85vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            transform: scale(0.9);
            transition: transform 0.3s;
        }
        .dr-php-modal-overlay.active .dr-php-modal {
            transform: scale(1);
        }
        
        .dr-php-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }
        .dr-php-modal-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
        }
        .dr-php-modal-header .dashicons {
            font-size: 20px;
        }
        .dr-wp-score {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 700;
            margin-right: 12px;
        }
        .dr-wp-score-success { background: #22c55e; }
        .dr-wp-score-warning { background: #f59e0b; }
        .dr-wp-score-danger { background: #ef4444; }
        .dr-php-source-badge {
            background: #ecfdf5;
            color: #059669;
            padding: 8px 16px;
            font-size: 12px;
            text-align: center;
            border-bottom: 1px solid #d1fae5;
        }
        .dr-php-modal-close {
            background: rgba(255,255,255,0.2);
            border: none;
            color: #fff;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            font-size: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }
        .dr-php-modal-close:hover {
            background: rgba(255,255,255,0.3);
        }
        
        /* Overview Grid */
        .dr-overview-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }
        .dr-overview-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .dr-overview-box.dr-box-score {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            border: none;
        }
        .dr-box-icon {
            font-size: 20px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            border-radius: 8px;
            color: #667eea;
        }
        .dr-box-score .dr-box-icon { background: rgba(255,255,255,0.2); color: #fff; }
        .dr-box-value {
            font-size: 18px;
            font-weight: 700;
            color: #1f2937;
        }
        .dr-box-score .dr-box-value { color: #fff; }
        .dr-box-label {
            font-size: 11px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .dr-box-score .dr-box-label { color: rgba(255,255,255,0.8); }
        
        /* Extension states */
        .dr-extension-item.optional-missing {
            background: #fefce8;
            border-color: #fde047;
        }
        .dr-extension-item.optional-missing .dr-ext-status {
            color: #ca8a04;
        }
        .dr-extension-item.critical-missing {
            background: #fef2f2;
            border-color: #fca5a5;
        }
        .dr-extension-item.critical-missing .dr-ext-name {
            color: #dc2626;
            font-weight: 700;
        }
        
        /* Tabs */
        .dr-php-modal-tabs {
            display: flex;
            border-bottom: 2px solid #e5e7eb;
            background: #f9fafb;
        }
        .dr-php-tab {
            flex: 1;
            padding: 14px 20px;
            border: none;
            background: transparent;
            font-size: 13px;
            font-weight: 500;
            color: #6b7280;
            cursor: pointer;
            position: relative;
            transition: all 0.2s;
        }
        .dr-php-tab:hover {
            color: #374151;
            background: #f3f4f6;
        }
        .dr-php-tab.active {
            color: #667eea;
            background: #fff;
        }
        .dr-php-tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: #667eea;
        }
        
        /* Tab Content */
        .dr-php-tab-content {
            display: none;
            padding: 20px;
            max-height: calc(85vh - 200px);
            overflow-y: auto;
        }
        .dr-php-tab-content.active {
            display: block;
        }
        
        /* Extensions Section headers */
        .dr-ext-section {
            grid-column: 1 / -1;
            margin: 20px 0 12px;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 8px;
        }
        .dr-ext-section:first-child {
            margin-top: 0;
        }
        
        /* Extensions Grid */
        .dr-extensions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 8px;
        }
        .dr-extension-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            background: #f9fafb;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
        }
        .dr-extension-item.loaded {
            background: #ecfdf5;
            border-color: #a7f3d0;
        }
        .dr-extension-item.not-loaded,
        .dr-extension-item.critical-missing {
            background: #fef2f2;
            border-color: #fecaca;
        }
        .dr-ext-status {
            width: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .dr-ext-status .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
        }
        .dr-extension-item.loaded .dr-ext-status .dashicons {
            color: #10b981;
        }
        .dr-extension-item.not-loaded .dr-ext-status .dashicons,
        .dr-extension-item.critical-missing .dr-ext-status .dashicons {
            color: #dc2626;
        }
        .dr-ext-name {
            font-weight: 600;
            font-size: 13px;
            color: #374151;
        }
        .dr-ext-desc {
            font-size: 11px;
            color: #6b7280;
            margin-left: auto;
        }
        
        /* All extensions tags */
        .dr-extensions-all {
            grid-column: 1 / -1;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            padding: 12px;
            background: #f3f4f6;
            border-radius: 8px;
        }
        .dr-ext-tag {
            display: inline-block;
            padding: 4px 10px;
            background: #fff;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 11px;
            font-family: 'Monaco', 'Consolas', monospace;
            color: #374151;
        }
        
        .dr-extensions-summary {
            margin-top: 16px;
            padding: 12px;
            background: #f3f4f6;
            border-radius: 6px;
            font-size: 13px;
            text-align: center;
        }
        
        /* Config Sections */
        .dr-config-sections {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        .dr-config-section {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
        }
        .dr-config-section h4 {
            margin: 0 0 12px;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 8px;
        }
        .dr-config-table {
            width: 100%;
            font-size: 12px;
        }
        .dr-config-table td {
            padding: 6px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .dr-config-table td:first-child {
            color: #6b7280;
        }
        .dr-config-table td:last-child {
            text-align: right;
            font-family: 'Monaco', 'Consolas', monospace;
            color: #374151;
        }
        .dr-config-table tr:last-child td {
            border-bottom: none;
        }
        
        /* Old config grid (fallback) */
        .dr-config-grid {
            display: grid;
            gap: 8px;
        }
        .dr-config-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: #f9fafb;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
        }
        .dr-config-item:nth-child(odd) {
            background: #fff;
        }
        .dr-config-label {
            font-weight: 500;
            color: #374151;
            font-size: 13px;
        }
        .dr-config-value {
            font-family: 'Monaco', 'Consolas', monospace;
            font-size: 12px;
            color: #667eea;
            background: #eef2ff;
            padding: 4px 10px;
            border-radius: 4px;
        }
        
        /* Recommendations */
        .dr-recommendations-list {
            display: grid;
            gap: 12px;
        }
        .dr-recommendation-item {
            display: flex;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 8px;
            border: 1px solid;
        }
        .dr-recommendation-item.success {
            background: #ecfdf5;
            border-color: #a7f3d0;
        }
        .dr-recommendation-item.warning {
            background: #fffbeb;
            border-color: #fde68a;
        }
        .dr-recommendation-item.error {
            background: #fef2f2;
            border-color: #fecaca;
        }
        .dr-recommendation-item.info {
            background: #eff6ff;
            border-color: #bfdbfe;
        }
        .dr-rec-icon {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }
        .dr-recommendation-item.success .dr-rec-icon {
            background: #10b981;
            color: #fff;
        }
        .dr-recommendation-item.warning .dr-rec-icon {
            background: #f59e0b;
            color: #fff;
        }
        .dr-recommendation-item.error .dr-rec-icon {
            background: #ef4444;
            color: #fff;
        }
        .dr-recommendation-item.info .dr-rec-icon {
            background: #3b82f6;
            color: #fff;
        }
        .dr-rec-content strong {
            display: block;
            font-size: 13px;
            color: #374151;
            margin-bottom: 4px;
        }
        .dr-rec-content p {
            margin: 0;
            font-size: 12px;
            color: #6b7280;
            line-height: 1.4;
        }
        
        /* ==========================================
           CLOUDFLARE TAB STYLES
           ========================================== */
        .dr-cf-loading {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }
        .dr-cf-loading .spinner {
            float: none;
            margin: 0 10px 0 0;
        }
        .dr-cf-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .dr-cf-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: linear-gradient(135deg, #f97316 0%, #fb923c 100%);
            color: white;
            border-radius: 8px;
            margin-bottom: 16px;
        }
        .dr-cf-status {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .dr-cf-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .dr-cf-badge-success { background: rgba(255,255,255,0.25); }
        .dr-cf-badge-warning { background: rgba(0,0,0,0.2); }
        .dr-cf-plan { font-weight: 500; }
        .dr-cf-preset { 
            background: rgba(255,255,255,0.2);
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
        }
        .dr-cf-header small {
            opacity: 0.8;
            font-size: 11px;
        }
        
        .dr-cf-section {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 14px;
            margin-bottom: 12px;
        }
        .dr-cf-section h4 {
            margin: 0 0 12px 0;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .dr-cf-section h4 .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
        }
        
        .dr-cf-settings-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        .dr-cf-setting {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            padding: 10px 12px;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
            transition: all 0.2s;
        }
        .dr-cf-setting label:first-child {
            font-size: 12px;
            color: #4b5563;
            font-weight: 500;
        }
        .dr-cf-setting.dr-cf-updating {
            opacity: 0.6;
            pointer-events: none;
        }
        .dr-cf-setting.dr-cf-success {
            border-color: #10b981;
            background: #ecfdf5;
        }
        .dr-cf-setting.dr-cf-error-setting {
            border-color: #ef4444;
            background: #fef2f2;
        }
        
        /* Toggle Switch */
        .dr-cf-switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }
        .dr-cf-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .dr-cf-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #d1d5db;
            transition: 0.3s;
            border-radius: 24px;
        }
        .dr-cf-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        .dr-cf-switch input:checked + .dr-cf-slider {
            background-color: #f97316;
        }
        .dr-cf-switch input:checked + .dr-cf-slider:before {
            transform: translateX(20px);
        }
        
        /* Select */
        .dr-cf-select {
            padding: 4px 8px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 12px;
            background: white;
            min-width: 100px;
        }
        
        /* NS List */
        .dr-cf-ns-list code {
            background: #e5e7eb;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            margin-right: 6px;
        }
        
        /* Rules list */
        .dr-cf-rules-list {
            max-height: 120px;
            overflow-y: auto;
        }
        .dr-cf-rule {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 10px;
            background: white;
            border-radius: 4px;
            margin-bottom: 4px;
            font-size: 12px;
        }
        .dr-cf-rule-name {
            color: #374151;
        }
        .dr-cf-rule-action {
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            background: #e5e7eb;
            color: #6b7280;
        }
        .dr-cf-action-block { background: #fecaca; color: #dc2626; }
        .dr-cf-action-challenge { background: #fef3c7; color: #d97706; }
        .dr-cf-action-allow { background: #d1fae5; color: #059669; }
        
        .dr-cf-footer {
            text-align: right;
            padding-top: 8px;
            border-top: 1px solid #e5e7eb;
            margin-top: 8px;
        }
        .dr-cf-footer small {
            color: #9ca3af;
            font-size: 11px;
        }
        
        /* Footer */
        .dr-php-modal-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px;
            border-top: 1px solid #e5e7eb;
            background: #f9fafb;
        }
        .dr-php-version {
            font-size: 12px;
            color: #6b7280;
            font-family: 'Monaco', 'Consolas', monospace;
        }
        .dr-php-modal-close-btn {
            padding: 8px 20px !important;
        }
        </style>
        
        <!-- Modal Preview Preset -->
        <div id="dr-preset-modal" class="dr-modal-overlay">
            <div class="dr-modal">
                <div class="dr-modal-header">
                    <span class="dr-modal-title">📋 Preview: <span id="dr-modal-preset-name">-</span></span>
                    <button class="dr-modal-close" id="dr-modal-close">&times;</button>
                </div>
                <div class="dr-modal-body">
                    <div class="dr-preset-section">
                        <h4>ℹ️ Información</h4>
                        <dl class="dr-preset-info" id="dr-preset-info">
                            <dt>Preset:</dt><dd id="dr-info-key">-</dd>
                            <dt>Descripción:</dt><dd id="dr-info-desc">-</dd>
                            <dt>Notas:</dt><dd id="dr-info-notes">-</dd>
                        </dl>
                    </div>
                    <div class="dr-preset-section">
                        <h4>⚙️ Settings de Zona</h4>
                        <pre id="dr-preset-settings">-</pre>
                    </div>
                    <div class="dr-preset-section">
                        <h4>📝 Cache Rules (<span id="dr-rules-count">0</span>)</h4>
                        <ul class="dr-preset-rules-list" id="dr-preset-rules">
                        </ul>
                    </div>
                    <div class="dr-preset-section">
                        <h4>📄 JSON Completo</h4>
                        <pre id="dr-preset-json">-</pre>
                        <button class="dr-copy-json" id="dr-copy-json">📋 Copiar JSON</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Renderizar scripts JS
     */
    /**
     * Enqueue scripts para páginas de admin
     */
    public function enqueue_scripts(string $hook): void {
        // Verificar si estamos en la página de onboarding usando el parámetro GET
        // (más confiable que depender del nombre exacto del hook)
        $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        
        if ($page !== 'dominios-reseller-onboarding') {
            return;
        }
        
        // NOTA: El JavaScript del modal PHP y otras funciones están ahora inline
        // en render_inline_scripts() para evitar problemas de 404 con archivos externos.
        // No se necesita cargar admin.js externo.
        
        // Solo aseguramos que jQuery esté disponible (ya lo está en admin)
        wp_enqueue_script('jquery');
    }

    /**
     * Renderizar scripts inline para la página
     */
    private function render_inline_scripts(): void {
        $nonce = wp_create_nonce('dr_onboarding_nonce');
        
        ?>
        <script>
        // Objeto global para compatibilidad
        var drOnboarding = {
            nonce: '<?php echo $nonce; ?>',
            ajaxurl: '<?php echo admin_url('admin-ajax.php'); ?>'
        };
        
        jQuery(document).ready(function($) {
            var nonce = '<?php echo $nonce; ?>';
            
            // ===== TOGGLE DE VISTA =====
            $('.dr-view-btn').on('click', function() {
                var view = $(this).data('view');
                $('.dr-view-btn').removeClass('active');
                $(this).addClass('active');
                
                if (view === 'cards') {
                    $('#dr-view-cards').show();
                    $('#dr-view-list').hide();
                } else {
                    $('#dr-view-cards').hide();
                    $('#dr-view-list').show();
                }
                
                // Guardar preferencia en localStorage
                localStorage.setItem('dr_onboarding_view', view);
            });
            
            // Restaurar vista guardada
            var savedView = localStorage.getItem('dr_onboarding_view');
            if (savedView) {
                $('.dr-view-btn[data-view="' + savedView + '"]').click();
            }
            
            // ===== CARGA DE NS Y PILOTOS POR AJAX =====
            function loadDomainInfo() {
                // Buscar dominios tanto en cards como en tabla
                var $elements = $('.dr-card[data-domain], .dr-row[data-domain]');
                var domains = [];
                var domainsSeen = {};
                
                $elements.each(function() {
                    var domain = $(this).data('domain');
                    if (domain && !domainsSeen[domain]) {
                        domains.push(domain);
                        domainsSeen[domain] = true;
                    }
                });
                
                if (domains.length === 0) return;
                
                // Cargar NS en lotes de 10
                var batchSize = 10;
                for (var i = 0; i < domains.length; i += batchSize) {
                    var batch = domains.slice(i, i + batchSize);
                    loadInfoBatch(batch);
                }
                
                // Cargar PHP en lotes de 5 (más pesado)
                var phpBatchSize = 5;
                for (var i = 0; i < domains.length; i += phpBatchSize) {
                    var batch = domains.slice(i, i + phpBatchSize);
                    loadPHPBatch(batch);
                }
            }
            
            function loadInfoBatch(domains) {
                $.post(ajaxurl, {
                    action: 'dr_get_batch_ns',
                    _nonce: nonce,
                    domains: domains
                }, function(response) {
                    if (response.success && response.data) {
                        $.each(response.data, function(domain, data) {
                            updateDomainDisplay(domain, data);
                        });
                    }
                }).fail(function() {
                    // En caso de error, marcar pilotos como desconocido
                    domains.forEach(function(domain) {
                        updatePilots(domain, { is_cloudflare: false, in_openprovider: false, error: true });
                    });
                });
            }
            
            function loadPHPBatch(domains) {
                $.post(ajaxurl, {
                    action: 'dr_get_batch_php',
                    _nonce: nonce,
                    domains: domains
                }, function(response) {
                    if (response.success && response.data) {
                        $.each(response.data, function(domain, phpData) {
                            updatePHPPilots(domain, phpData);
                        });
                    }
                }).fail(function(xhr, status, error) {
                    // En caso de error, marcar PHP pilots como error
                    domains.forEach(function(domain) {
                        updatePHPPilots(domain, { error: true });
                    });
                });
            }
            
            function updateDomainDisplay(domain, data) {
                // Actualizar tanto en cards como en tabla
                var $card = $('.dr-card[data-domain="' + domain + '"]');
                var $row = $('.dr-row[data-domain="' + domain + '"]');
                
                // Actualizar NS en ambos
                if ($card.length) updateNsDisplay($card, data);
                if ($row.length) updateNsDisplay($row, data);
                
                // Actualizar pilotos CF/OP (ya funciona con selectores globales)
                updatePilots(domain, data);
            }
            
            function updateNsDisplay($container, data) {
                var $nsRow = $container.find('.dr-ns-row');
                if (!$nsRow.length) return;
                
                var html = '';
                
                if (data.error && !data.nameservers) {
                    html = '<span class="ns-error" style="color:#ef4444;">⚠️ Error obteniendo NS</span>';
                } else if (data.nameservers && data.nameservers.length > 0) {
                    // Provider badge con color
                    var providerColors = {
                        'cloudflare': '#f97316',
                        'openprovider': '#22c55e',
                        'godaddy': '#111827',
                        'hostinger': '#673ab7',
                        'namecheap': '#de4101',
                        'other': '#6b7280'
                    };
                    var bgColor = providerColors[data.provider] || providerColors['other'];
                    
                    html = '<span class="ns-provider" style="background:' + bgColor + ';color:#fff;padding:1px 5px;border-radius:3px;font-size:9px;font-weight:600;margin-right:6px;">' + data.provider.toUpperCase() + '</span>';
                    html += '<span class="ns-values" style="display:flex;gap:5px;flex-wrap:wrap;">';
                    data.nameservers.slice(0, 2).forEach(function(ns) {
                        html += '<span class="ns-item" style="background:#f3f4f6;padding:1px 4px;border-radius:2px;font-family:monospace;font-size:10px;">' + ns + '</span>';
                    });
                    if (data.nameservers.length > 2) {
                        html += '<span class="ns-more" style="color:#9ca3af;font-size:10px;">+' + (data.nameservers.length - 2) + '</span>';
                    }
                    html += '</span>';
                } else {
                    html = '<span class="ns-none" style="color:#9ca3af;font-size:11px;">Sin NS detectados</span>';
                }
                
                $nsRow.html(html);
            }
            
            function updatePilots(domain, data) {
                // Actualizar pilotos en cards Y en tabla (ambos usan mismas clases)
                var $cfPilots = $('.dr-pilot-cf[data-domain="' + domain + '"]');
                var $opPilots = $('.dr-pilot-op[data-domain="' + domain + '"]');
                
                // Actualizar piloto CF
                $cfPilots.removeClass('dr-pilot-loading active inactive none');
                if (data.is_cloudflare) {
                    $cfPilots.addClass('active').attr('title', 'NS apuntando a Cloudflare');
                } else if (data.provider === 'cloudflare') {
                    $cfPilots.addClass('active').attr('title', 'NS apuntando a Cloudflare');
                } else {
                    $cfPilots.addClass('none').attr('title', 'NS NO están en Cloudflare');
                }
                
                // Actualizar piloto OP
                $opPilots.removeClass('dr-pilot-loading active inactive none');
                if (data.in_openprovider) {
                    $opPilots.addClass('active').attr('title', 'Dominio registrado en Openprovider' + (data.op_status ? ' (' + data.op_status + ')' : ''));
                } else if (data.error === 'not_configured') {
                    $opPilots.addClass('inactive').attr('title', 'Openprovider no configurado');
                } else {
                    $opPilots.addClass('none').attr('title', 'Dominio NO está en Openprovider');
                }
                
                // SSL se muestra ahora en el modal, no en la card
            }
            
            function updatePHPPilots(domain, phpData) {
                // Actualizar pilotos PHP en cards Y en tabla
                var $phpPilots = $('.dr-pilot-php[data-domain="' + domain + '"]');
                
                $phpPilots.removeClass('dr-pilot-loading dr-pilot-success dr-pilot-warning dr-pilot-error');
                
                if (phpData.error) {
                    $phpPilots.addClass('dr-pilot-error').attr('title', 'Error obteniendo info PHP').html('PHP ✗');
                } else if (phpData.max_performance) {
                    $phpPilots.addClass('dr-pilot-success').attr('title', 'PHP Optimizado - ' + (phpData.php_version || 'Desconocido')).html('PHP ✓');
                } else if (phpData.recommendations && phpData.recommendations.length > 0) {
                    $phpPilots.addClass('dr-pilot-warning').attr('title', 'PHP Requiere Optimización - ' + (phpData.php_version || 'Desconocido')).html('PHP !');
                } else {
                    $phpPilots.addClass('dr-pilot-success').attr('title', 'PHP OK - ' + (phpData.php_version || 'Desconocido')).html('PHP ✓');
                }
                
                // Almacenar datos para el modal - en data del elemento Y en cache global
                $phpPilots.data('php-data', phpData);
                
                // Cache global para acceso desde el modal
                if (!window.phpInfoCache) window.phpInfoCache = {};
                window.phpInfoCache[domain] = phpData;
            }
            
            // Cargar info al inicio
            loadDomainInfo();
            
            // ===== APLICAR ONBOARDING =====
            $(document).on('click', '.dr-btn-apply', function() {
                var $btn = $(this);
                var domain = $btn.data('domain');
                // Buscar en card o en row de tabla
                var $container = $btn.closest('.dr-card');
                if ($container.length === 0) {
                    $container = $btn.closest('.dr-row');
                }
                var preset = $container.find('.dr-preset-mini').val() || 'wp';
                var autoNs = $container.find('.dr-auto-ns-check').is(':checked') ? 1 : 0;
                
                $btn.prop('disabled', true).text('⏳');
                
                $.post(ajaxurl, {
                    action: 'dr_onboarding_enqueue',
                    _nonce: nonce,
                    domain: domain,
                    preset: preset,
                    auto_ns: autoNs
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                        $btn.prop('disabled', false).text('▶');
                    }
                }).fail(function() {
                    alert('Error de conexión');
                    $btn.prop('disabled', false).text('▶');
                });
            });
            
            // Procesar cola ahora
            $('#dr-process-queue-now').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('⏳ Procesando...');
                
                $.post(ajaxurl, {
                    action: 'dr_onboarding_process_now',
                    _nonce: nonce
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                        $btn.prop('disabled', false).text('▶️ Procesar cola');
                    }
                });
            });
            
            // Refrescar estado
            $('#dr-refresh-status').on('click', function() {
                location.reload();
            });

            // ========================================
            // REACTIVATE ZONE: Pedir a CF que vuelva a comprobar NS (zonas moved/pending)
            // ========================================
            $(document).on('click', '.dr-btn-reactivate', function() {
                var $btn = $(this);
                var domain = $btn.data('domain');
                var zoneId = $btn.data('zone-id');
                if (!domain || !zoneId) return;
                if (!confirm('Pedir a Cloudflare que vuelva a verificar los NS de ' + domain + '?\n\nÚsalo cuando la zona está en estado moved/pending y ya has actualizado los NS en el registrador.')) return;

                var originalText = $btn.html();
                $btn.prop('disabled', true).html('<span class="dashicons dashicons-update dr-spin"></span> Solicitando...');

                $.post(ajaxurl, {
                    action: 'dr_reactivate_zone',
                    _nonce: nonce,
                    domain: domain,
                    zone_id: zoneId
                }, function(response) {
                    if (response.success) {
                        $btn.html('<span class="dashicons dashicons-yes"></span> Solicitado').css({background: '#10b981', color: '#fff'});
                        // Auto-refrescar el estado del dominio en 8s para ver si CF ya lo activó
                        setTimeout(function() {
                            $btn.closest('.dr-card, tr').find('.dr-btn-refresh-domain').first().trigger('click');
                            $btn.prop('disabled', false).html(originalText).css({background: '', color: ''});
                        }, 8000);
                    } else {
                        alert('Error: ' + (response.data || 'no se pudo reactivar'));
                        $btn.prop('disabled', false).html(originalText);
                    }
                }).fail(function() {
                    alert('Error de conexión');
                    $btn.prop('disabled', false).html(originalText);
                });
            });

            // ========================================
            // REFRESH PER-DOMAIN: Actualizar estado desde CF + DNS
            // ========================================
            $(document).on('click', '.dr-btn-refresh-domain', function() {
                var $btn = $(this);
                var domain = $btn.data('domain');
                var $card = $btn.closest('.dr-card');
                if ($card.length === 0) {
                    $card = $btn.closest('.dr-row, tr');
                }
                var originalText = $btn.text();
                
                $btn.prop('disabled', true).addClass('dr-spin-emoji').text('🔄');
                
                $.post(ajaxurl, {
                    action: 'dr_refresh_domain_status',
                    _nonce: nonce,
                    domain: domain
                }, function(response) {
                    $btn.prop('disabled', false).removeClass('dr-spin-emoji').text(originalText);
                    
                    if (!response.success) {
                        alert('Error: ' + (response.data || 'Error desconocido'));
                        return;
                    }
                    
                    var d = response.data;
                    
                    // Actualizar NS display
                    updateNsDisplay($card, {
                        nameservers: d.nameservers,
                        provider: d.provider,
                        is_cloudflare: d.is_cloudflare,
                        error: false
                    });
                    
                    // Actualizar pilotos CF/OP
                    var $cfPilot = $card.find('.dr-pilot-cf');
                    $cfPilot.removeClass('dr-pilot-loading active inactive none');
                    if (d.is_cloudflare) {
                        $cfPilot.addClass('active').attr('title', 'NS apuntando a Cloudflare');
                    } else {
                        $cfPilot.addClass('none').attr('title', 'NS NO están en Cloudflare');
                    }
                    
                    // Actualizar status dot
                    var $dot = $card.find('.dr-status-dot');
                    $dot.removeClass('dr-dot-success dr-dot-processing dr-dot-error dr-dot-warning dr-dot-cf dr-dot-none');
                    var dotMap = {
                        'onboarded': 'dr-dot-success',
                        'running': 'dr-dot-processing',
                        'pending': 'dr-dot-processing',
                        'error': 'dr-dot-error',
                        'needs_manual_ns': 'dr-dot-error',
                        'pending_ns': 'dr-dot-warning',
                        'partial': 'dr-dot-warning',
                        'none': 'dr-dot-none'
                    };
                    $dot.addClass(dotMap[d.state] || 'dr-dot-none');
                    
                    // Mostrar info del refresh
                    var $errorRow = $card.find('.dr-error-row');
                    if (d.state_changed) {
                        var msg = '✅ Estado actualizado: ' + d.old_state + ' → ' + d.state;
                        if ($errorRow.length) {
                            $errorRow.html('<small>' + msg + '</small>');
                        } else {
                            $card.find('.dr-controls-row').after(
                                '<div class="dr-card-row dr-error-row"><small>' + msg + '</small></div>'
                            );
                        }
                        // Recargar la página tras 2s para actualizar controles
                        setTimeout(function() { location.reload(); }, 2000);
                    } else {
                        // Mostrar resumen breve
                        var info = '🔍 ' + domain + ': ';
                        if (d.zone_id) {
                            info += 'CF=' + (d.zone_status || '?') + ' (' + (d.plan || '?') + ')';
                            info += d.ns_verified ? ' ✅ NS OK' : ' ⚠️ NS no coinciden';
                        } else {
                            info += 'No está en Cloudflare';
                        }
                        if (d.last_error) {
                            info += ' | ' + d.last_error.substring(0, 80);
                        }
                        
                        if ($errorRow.length) {
                            $errorRow.html('<small>' + info + '</small>');
                        } else {
                            $card.find('.dr-controls-row').after(
                                '<div class="dr-card-row dr-error-row"><small>' + info + '</small></div>'
                            );
                        }
                    }
                    
                }).fail(function() {
                    $btn.prop('disabled', false).removeClass('dr-spin-emoji').text(originalText);
                    alert('Error de conexión al refrescar ' + domain);
                });
            });
            
            // ========================================
            // NUEVOS: Refresh All PHP, Alertas, Activity
            // ========================================
            
            // Refresh All PHP Info
            $('#dr-refresh-all-php').on('click', function() {
                var $btn = $(this);
                var originalText = $btn.html();
                var startTime = Date.now();
                
                // Mostrar progreso animado
                $btn.prop('disabled', true)
                    .css('min-width', $btn.outerWidth() + 'px')
                    .html('<span class="dashicons dashicons-update dr-spin"></span> Iniciando...');
                
                // Contador de tiempo
                var dots = 0;
                var progressInterval = setInterval(function() {
                    var elapsed = Math.floor((Date.now() - startTime) / 1000);
                    dots = (dots + 1) % 4;
                    var dotStr = '.'.repeat(dots) + ' '.repeat(3 - dots);
                    $btn.html('<span class="dashicons dashicons-update dr-spin"></span> Escaneando' + dotStr + ' (' + elapsed + 's)');
                }, 1000);
                
                $.post(ajaxurl, {
                    action: 'dr_refresh_all_php_info',
                    _nonce: nonce
                }, function(response) {
                    clearInterval(progressInterval);
                    $btn.prop('disabled', false).html(originalText);
                    
                    if (response.success) {
                        var r = response.data;
                        var msg = 'Scan completado!\n\n';
                        
                        if (r.deployed > 0) {
                            msg += '🚀 Endpoints desplegados: ' + r.deployed + '\n';
                        }
                        if (r.pending_deploy > 0) {
                            msg += '⏳ Pendientes de deploy: ' + r.pending_deploy + '\n';
                        }
                        msg += '✅ Actualizados: ' + (r.updated || 0) + '\n';
                        msg += '❌ Fallidos: ' + (r.failed || 0) + '\n';
                        if (r.alerts && r.alerts.length > 0) {
                            msg += '⚠️ Alertas: ' + r.alerts.length;
                        }
                        if (r.message) {
                            msg += '\n\n' + r.message;
                        }
                        alert(msg);
                        
                        // Recargar si hubo deploys
                        if (r.deployed > 0 || r.updated > 0) {
                            location.reload();
                        } else if (r.alerts && r.alerts.length > 0) {
                            $('#dr-view-alerts').click();
                        }
                    } else {
                        alert('Error: ' + (response.data || 'Error desconocido'));
                    }
                }).fail(function() {
                    clearInterval(progressInterval);
                    $btn.prop('disabled', false).html(originalText);
                    alert('Error de conexión');
                });
            });
            
            // Ver Alertas
            $('#dr-view-alerts').on('click', function() {
                var $panel = $('#dr-alerts-panel');
                var $content = $('#dr-alerts-content');
                
                if ($panel.is(':visible')) {
                    $panel.slideUp();
                    return;
                }
                
                $content.html('<p>Cargando alertas...</p>');
                $panel.slideDown();
                
                $.post(ajaxurl, {
                    action: 'dr_get_php_alerts',
                    _nonce: nonce
                }, function(response) {
                    if (response.success) {
                        var data = response.data;
                        var html = '';
                        
                        if (data.alerts.length === 0) {
                            html = '<p style="color:#46b450;">✅ No hay alertas. Todos los servidores están bien configurados.</p>';
                        } else {
                            html += '<div class="dr-alerts-summary" style="margin-bottom:15px;padding:10px;background:#f0f6fc;border-radius:4px;">';
                            html += '<strong>Resumen:</strong> ';
                            html += '<span style="color:#dc3232;">🔴 ' + data.summary.critical + ' críticas</span> | ';
                            html += '<span style="color:#f0ad4e;">🟡 ' + data.summary.warning + ' warnings</span> | ';
                            html += '<span style="color:#00a0d2;">🔵 ' + data.summary.info + ' info</span>';
                            html += '</div>';
                            
                            data.alerts.forEach(function(item) {
                                html += '<div class="dr-alert-item ' + item.type + '">';
                                html += '<span class="dr-alert-domain">' + item.domain + '</span>';
                                html += '<span class="dr-alert-message">' + item.message + '</span>';
                                html += '</div>';
                            });
                        }
                        
                        $content.html(html);
                    } else {
                        $content.html('<p style="color:#dc3232;">Error cargando alertas</p>');
                    }
                });
            });
            
            // Ver Activity Log
            $('#dr-view-activity').on('click', function() {
                var $panel = $('#dr-activity-panel');
                
                if ($panel.is(':visible')) {
                    $panel.slideUp();
                    return;
                }
                
                $panel.slideDown();
                loadActivityLog();
            });
            
            // Filtrar Activity Log
            $('#dr-activity-type-filter').on('change', function() {
                loadActivityLog();
            });
            
            function loadActivityLog() {
                var $content = $('#dr-activity-content');
                var actionType = $('#dr-activity-type-filter').val();
                
                $content.html('<p>Cargando historial...</p>');
                
                $.post(ajaxurl, {
                    action: 'dr_get_activity_log',
                    _nonce: nonce,
                    limit: 50,
                    action_type: actionType
                }, function(response) {
                    if (response.success && response.data.length > 0) {
                        var html = '';
                        response.data.forEach(function(log, index) {
                            var hasDetails = log.details && (log.details.updated_domains || log.details.failed_domains || log.details.alerts);
                            html += '<div class="dr-activity-item">';
                            html += '<div class="dr-activity-row">';
                            html += '<span class="dr-activity-time">' + log.created_at + '</span>';
                            html += '<span class="dr-activity-type ' + log.action_type + '">' + log.action_type + '</span>';
                            html += '<span class="dr-activity-domain">' + (log.domain || '-') + '</span>';
                            html += '<span class="dr-activity-desc">' + log.description + '</span>';
                            if (hasDetails) {
                                html += '<button type="button" class="button button-small dr-toggle-details" data-index="' + index + '">Ver detalles</button>';
                            }
                            html += '</div>';
                            
                            // Panel de detalles expandible
                            if (hasDetails) {
                                html += '<div class="dr-activity-details" id="dr-details-' + index + '" style="display:none;">';
                                
                                // Dominios actualizados
                                if (log.details.updated_domains && Object.keys(log.details.updated_domains).length > 0) {
                                    html += '<div class="dr-details-section dr-details-success">';
                                    html += '<strong>✅ Actualizados (' + Object.keys(log.details.updated_domains).length + '):</strong>';
                                    html += '<ul>';
                                    for (var dom in log.details.updated_domains) {
                                        html += '<li><code>' + dom + '</code> - ' + log.details.updated_domains[dom] + '</li>';
                                    }
                                    html += '</ul></div>';
                                }
                                
                                // Dominios fallidos
                                if (log.details.failed_domains && Object.keys(log.details.failed_domains).length > 0) {
                                    html += '<div class="dr-details-section dr-details-error">';
                                    html += '<strong>❌ Fallidos (' + Object.keys(log.details.failed_domains).length + '):</strong>';
                                    html += '<ul>';
                                    for (var dom in log.details.failed_domains) {
                                        html += '<li><code>' + dom + '</code> - ' + log.details.failed_domains[dom] + '</li>';
                                    }
                                    html += '</ul></div>';
                                }
                                
                                // Alertas
                                if (log.details.alerts && log.details.alerts.length > 0) {
                                    html += '<div class="dr-details-section dr-details-warning">';
                                    html += '<strong>⚠️ Alertas (' + log.details.alerts.length + '):</strong>';
                                    html += '<ul>';
                                    log.details.alerts.forEach(function(alert) {
                                        html += '<li><code>' + alert.domain + '</code> - ' + alert.message + '</li>';
                                    });
                                    html += '</ul></div>';
                                }
                                
                                html += '</div>';
                            }
                            
                            html += '</div>';
                        });
                        $content.html(html);
                    } else {
                        $content.html('<p>No hay actividad registrada.</p>');
                    }
                });
            }
            
            // Toggle detalles en historial
            $(document).on('click', '.dr-toggle-details', function() {
                var index = $(this).data('index');
                var $details = $('#dr-details-' + index);
                if ($details.is(':visible')) {
                    $details.slideUp(200);
                    $(this).text('Ver detalles');
                } else {
                    $details.slideDown(200);
                    $(this).text('Ocultar');
                }
            });
            
            // Cerrar paneles
            $(document).on('click', '.dr-close-panel', function() {
                $(this).closest('.dr-alerts-panel, .dr-activity-panel').slideUp();
            });
            
            // Copiar NS al portapapeles
            $(document).on('click', '.dr-target-ns-row code', function() {
                var ns = $(this).text();
                navigator.clipboard.writeText(ns).then(function() {
                    alert('Copiado: ' + ns);
                });
            });
            
            // Ver logs
            $(document).on('click', '.dr-btn-logs', function() {
                var domain = $(this).data('domain');
                var runId = $(this).data('run-id');
                
                $.post(ajaxurl, {
                    action: 'dr_onboarding_get_logs',
                    _nonce: nonce,
                    domain: domain,
                    run_id: runId
                }, function(response) {
                    if (response.success) {
                        showLogsModal(domain, response.data);
                    } else {
                        alert('Error obteniendo logs');
                    }
                });
            });
            
            // Modal de logs
            function showLogsModal(domain, logs) {
                var html = '<div class="dr-logs-modal active">';
                html += '<div class="dr-logs-content">';
                html += '<div class="dr-logs-header">';
                html += '<h3>📋 Logs: ' + domain + '</h3>';
                html += '<span class="dr-logs-close">&times;</span>';
                html += '</div>';
                html += '<div class="dr-logs-body">';
                
                if (logs.length === 0) {
                    html += '<p>No hay logs disponibles</p>';
                } else {
                    logs.forEach(function(log) {
                        html += '<div class="dr-log-entry dr-log-' + log.level + '">';
                        html += '<div class="dr-log-header">';
                        html += '<span class="dr-log-time">' + log.created_at + '</span>';
                        html += '<span class="dr-log-step">' + log.step + '</span>';
                        html += '<span class="dr-log-level dr-log-level-' + log.level + '">' + log.level.toUpperCase() + '</span>';
                        html += '</div>';
                        html += '<div class="dr-log-message">' + log.message + '</div>';
                        
                        // Mostrar datos adicionales si existen
                        if (log.data && log.data !== 'null' && log.data !== '') {
                            try {
                                var data = JSON.parse(log.data);
                                html += '<div class="dr-log-data">';
                                html += '<strong>Datos adicionales:</strong>';
                                html += '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                                html += '</div>';
                            } catch (e) {
                                html += '<div class="dr-log-data">';
                                html += '<strong>Datos:</strong> ' + log.data;
                                html += '</div>';
                            }
                        }
                        
                        html += '</div>';
                    });
                }
                
                html += '</div></div></div>';
                
                $('body').append(html);
                
                $('.dr-logs-close, .dr-logs-modal').on('click', function(e) {
                    if (e.target === this) {
                        $('.dr-logs-modal').remove();
                    }
                });
            }
            
            // Toggle cambio password Openprovider
            $('#dr-op-change-password').on('change', function() {
                var show = $(this).is(':checked');
                $('#dr-op-new-password-wrapper').toggle(show);
                if (show) {
                    $('#dr-op-current-password').prop('disabled', true);
                } else {
                    $('#dr-op-current-password').prop('disabled', false);
                }
            });
            
            // Verificar Openprovider
            $('#dr-verify-openprovider').on('click', function() {
                var $btn = $(this);
                var $result = $('#dr-op-verify-result');
                
                $btn.prop('disabled', true);
                $result.html('⏳ Verificando...');
                
                $.post(ajaxurl, {
                    action: 'dr_verify_openprovider',
                    _nonce: nonce
                }, function(response) {
                    $btn.prop('disabled', false);
                    if (response.success) {
                        $result.html('<span style="color:green;">✅ ' + (response.data || 'Conexión exitosa') + '</span>');
                    } else {
                        $result.html('<span style="color:red;">❌ ' + (response.data || 'Error desconocido') + '</span>');
                    }
                }).fail(function(xhr, status, error) {
                    $btn.prop('disabled', false);
                    $result.html('<span style="color:red;">❌ Error de conexión: ' + error + '</span>');
                });
            });
            
            // ===== PREVIEW PRESET =====
            var $modal = $('#dr-preset-modal');
            
            // Cerrar modal
            $('#dr-modal-close, .dr-modal-overlay').on('click', function(e) {
                if (e.target === this) {
                    $modal.removeClass('active');
                }
            });
            
            // Abrir preview
            $(document).on('click', '.dr-btn-preview', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var presetKey = $btn.data('preset');
                
                // Si no tiene data-preset, buscar en el select hermano
                if (!presetKey) {
                    var $select = $btn.siblings('.dr-preset-mini');
                    if ($select.length === 0) {
                        $select = $btn.closest('.dr-preset-group').find('.dr-preset-mini');
                    }
                    presetKey = $select.val();
                }
                
                if (!presetKey) {
                    alert('No se pudo determinar el preset');
                    return;
                }
                
                // Cargar preview via AJAX
                $.post(ajaxurl, {
                    action: 'dr_preview_preset',
                    _nonce: nonce,
                    preset_key: presetKey
                }, function(response) {
                    if (response.success) {
                        var data = response.data;
                        
                        // Rellenar modal
                        $('#dr-modal-preset-name').text(data.name || presetKey);
                        $('#dr-info-key').text(data.preset_key || '-');
                        $('#dr-info-desc').text(data.description || '-');
                        $('#dr-info-notes').text(data.notes || '-');
                        
                        // Settings
                        $('#dr-preset-settings').text(JSON.stringify(data.settings || {}, null, 2));
                        
                        // Cache Rules
                        var rules = data.cache_rules || [];
                        $('#dr-rules-count').text(rules.length);
                        var $rulesList = $('#dr-preset-rules').empty();
                        rules.forEach(function(rule) {
                            $rulesList.append(
                                '<li><strong>' + (rule.name || 'Sin nombre') + '</strong> → ' + 
                                '<em>' + (rule.action || 'bypass') + '</em>' +
                                '<code>' + (rule.if || rule.expression || '-') + '</code></li>'
                            );
                        });
                        
                        // JSON completo
                        $('#dr-preset-json').text(data.raw_json || '{}');
                        
                        // Mostrar modal
                        $modal.addClass('active');
                    } else {
                        alert('Error: ' + (response.data || 'No se pudo cargar el preset'));
                    }
                }).fail(function() {
                    alert('Error de conexión al cargar preset');
                });
            });
            
            // Copiar JSON
            $('#dr-copy-json').on('click', function() {
                var json = $('#dr-preset-json').text();
                navigator.clipboard.writeText(json).then(function() {
                    alert('JSON copiado al portapapeles');
                }).catch(function() {
                    // Fallback para navegadores antiguos
                    var $temp = $('<textarea>');
                    $('body').append($temp);
                    $temp.val(json).select();
                    document.execCommand('copy');
                    $temp.remove();
                    alert('JSON copiado al portapapeles');
                });
            });
            
            // ========================================
            // MODAL PHP CONFIG - INLINE
            // ========================================
            
            // Click en botón ⚙ para abrir modal PHP
            $(document).on('click', '.dr-btn-php-config', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var $btn = $(this);
                
                // El botón tiene data-domain directamente
                var domain = $btn.data('domain');
                
                // Fallback: buscar en contenedores padres
                if (!domain) {
                    var $row = $btn.closest('tr, .dr-card, .dr-row');
                    domain = $row.data('domain');
                }
                
                if (!domain) {
                    alert('Error: No se pudo determinar el dominio');
                    return;
                }
                
                // Obtener datos del cache
                var phpInfo = window.phpInfoCache ? window.phpInfoCache[domain] : null;
                
                if (!phpInfo || phpInfo.error) {
                    alert('No hay información PHP disponible para ' + domain + '.\n\nEspera a que cargue la info PHP (indicador debe cambiar de "..." a "PHP ✓" o "PHP !")');
                    return;
                }
                
                showPHPModal(domain, phpInfo);
            });
            
            function showPHPModal(domain, phpInfo) {
                // Cerrar modal existente
                $('.dr-php-modal-overlay').remove();
                
                var modalHTML = buildPHPModalHTML(domain, phpInfo);
                $('body').append(modalHTML);
                
                // Animar entrada
                setTimeout(function() {
                    $('.dr-php-modal-overlay').addClass('active');
                }, 10);
                
                // Inicializar tabs
                initPHPModalTabs();
                
                // Cargar datos de Cloudflare
                loadCloudflareTab(domain);
            }
            
            function buildPHPModalHTML(domain, phpInfo) {
                var isEndpoint = phpInfo.source === 'endpoint';
                var html = '<div class="dr-php-modal-overlay">';
                html += '<div class="dr-php-modal">';
                
                // Header
                html += '<div class="dr-php-modal-header">';
                html += '<h3><span class="dashicons dashicons-admin-generic"></span> ' + domain + '</h3>';
                if (isEndpoint && phpInfo.wp_readiness_score) {
                    var score = phpInfo.wp_readiness_score;
                    var scoreClass = score >= 75 ? 'success' : (score >= 50 ? 'warning' : 'danger');
                    html += '<span class="dr-wp-score dr-wp-score-' + scoreClass + '">' + score + '/100</span>';
                }
                html += '<button class="dr-php-modal-close">&times;</button>';
                html += '</div>';
                
                // Source badge
                if (isEndpoint) {
                    html += '<div class="dr-php-source-badge">Datos reales via endpoint';
                    if (phpInfo.updated_at) html += ' - ' + phpInfo.updated_at;
                    html += '</div>';
                }
                
                // Tabs - Añadir Cloudflare
                html += '<div class="dr-php-modal-tabs">';
                html += '<button class="dr-php-tab active" data-tab="overview">Resumen</button>';
                html += '<button class="dr-php-tab" data-tab="cloudflare"><span class="dashicons dashicons-cloud"></span> Cloudflare</button>';
                html += '<button class="dr-php-tab" data-tab="extensions">Extensiones</button>';
                html += '<button class="dr-php-tab" data-tab="config">Configuracion</button>';
                html += '<button class="dr-php-tab" data-tab="recommendations">Recomendaciones</button>';
                html += '</div>';
                
                // Tab Content - Resumen
                html += '<div class="dr-php-tab-content active" data-tab="overview">';
                html += buildOverviewTab(phpInfo);
                html += '</div>';
                
                // Tab Content - Cloudflare
                html += '<div class="dr-php-tab-content" data-tab="cloudflare">';
                html += '<div class="dr-cf-loading"><span class="spinner is-active"></span> Cargando configuración de Cloudflare...</div>';
                html += '<div class="dr-cf-content" style="display:none;"></div>';
                html += '</div>';
                
                // Tab Content - Extensiones
                html += '<div class="dr-php-tab-content" data-tab="extensions">';
                html += buildExtensionsTab(phpInfo);
                html += '</div>';
                
                // Tab Content - Configuracion
                html += '<div class="dr-php-tab-content" data-tab="config">';
                html += buildConfigTab(phpInfo);
                html += '</div>';
                
                // Tab Content - Recomendaciones
                html += '<div class="dr-php-tab-content" data-tab="recommendations">';
                html += buildRecommendationsTab(phpInfo);
                html += '</div>';
                
                // Footer
                html += '<div class="dr-php-modal-footer">';
                html += '<span class="dr-php-version">PHP ' + (phpInfo.php_version || 'N/A');
                if (phpInfo.sapi) html += ' (' + phpInfo.sapi + ')';
                html += '</span>';
                html += '<button class="button dr-php-modal-close-btn">Cerrar</button>';
                html += '</div>';
                
                html += '</div></div>';
                
                return html;
            }
            
            function buildOverviewTab(phpInfo) {
                var html = '<div class="dr-overview-grid">';
                
                // PHP Version
                html += '<div class="dr-overview-box">';
                html += '<div class="dr-box-icon"><span class="dashicons dashicons-editor-code"></span></div>';
                html += '<div class="dr-box-content">';
                html += '<div class="dr-box-value">' + (phpInfo.php_version || 'N/A') + '</div>';
                html += '<div class="dr-box-label">PHP Version</div>';
                html += '</div></div>';
                
                // Server
                html += '<div class="dr-overview-box">';
                html += '<div class="dr-box-icon"><span class="dashicons dashicons-' + (phpInfo.is_litespeed ? 'performance' : 'desktop') + '"></span></div>';
                html += '<div class="dr-box-content">';
                html += '<div class="dr-box-value">' + (phpInfo.sapi || phpInfo.server_software || 'N/A') + '</div>';
                html += '<div class="dr-box-label">Server</div>';
                html += '</div></div>';
                
                // Memory
                html += '<div class="dr-overview-box">';
                html += '<div class="dr-box-icon"><span class="dashicons dashicons-database"></span></div>';
                html += '<div class="dr-box-content">';
                html += '<div class="dr-box-value">' + (phpInfo.memory_limit || 'N/A') + '</div>';
                html += '<div class="dr-box-label">Memory Limit</div>';
                html += '</div></div>';
                
                // OPcache
                var opcacheIcon = phpInfo.opcache_enabled ? 'yes-alt' : 'dismiss';
                html += '<div class="dr-overview-box">';
                html += '<div class="dr-box-icon"><span class="dashicons dashicons-' + opcacheIcon + '" style="color:' + (phpInfo.opcache_enabled ? '#46b450' : '#dc3232') + '"></span></div>';
                html += '<div class="dr-box-content">';
                var opcacheVal = phpInfo.opcache_enabled ? 'ON' : 'OFF';
                if (phpInfo.opcache_hit_rate) opcacheVal += ' (' + parseFloat(phpInfo.opcache_hit_rate).toFixed(1) + '%)';
                html += '<div class="dr-box-value">' + opcacheVal + '</div>';
                html += '<div class="dr-box-label">OPcache</div>';
                html += '</div></div>';
                
                // Extensions count
                var extCount = (phpInfo.extensions || []).length;
                html += '<div class="dr-overview-box">';
                html += '<div class="dr-box-icon"><span class="dashicons dashicons-admin-plugins"></span></div>';
                html += '<div class="dr-box-content">';
                html += '<div class="dr-box-value">' + extCount + '</div>';
                html += '<div class="dr-box-label">Extensiones</div>';
                html += '</div></div>';
                
                // WP Score
                if (phpInfo.wp_readiness_score) {
                    var score = phpInfo.wp_readiness_score;
                    var grade = phpInfo.wp_readiness_grade || (score >= 90 ? 'A' : score >= 75 ? 'B' : score >= 60 ? 'C' : score >= 40 ? 'D' : 'F');
                    html += '<div class="dr-overview-box dr-box-score">';
                    html += '<div class="dr-box-icon"><span class="dashicons dashicons-awards"></span></div>';
                    html += '<div class="dr-box-content">';
                    html += '<div class="dr-box-value">' + grade + ' (' + score + ')</div>';
                    html += '<div class="dr-box-label">WP Readiness</div>';
                    html += '</div></div>';
                }
                
                html += '</div>';
                return html;
            }
            
            function buildExtensionsTab(phpInfo) {
                var loadedExt = phpInfo.extensions || [];
                
                // Extensiones importantes para WordPress Performance
                var wpPerformanceExt = {
                    // Criticas (requeridas)
                    'curl': {critical: true, desc: 'HTTP requests, API calls'},
                    'dom': {critical: true, desc: 'XML/HTML parsing'},
                    'exif': {critical: true, desc: 'Image metadata'},
                    'fileinfo': {critical: true, desc: 'MIME type detection'},
                    'hash': {critical: true, desc: 'Cryptographic hashing'},
                    'json': {critical: true, desc: 'JSON encode/decode'},
                    'mbstring': {critical: true, desc: 'Multibyte strings'},
                    'mysqli': {critical: true, desc: 'MySQL database'},
                    'openssl': {critical: true, desc: 'SSL/TLS encryption'},
                    'pcre': {critical: true, desc: 'Regular expressions'},
                    'xml': {critical: true, desc: 'XML processing'},
                    'zip': {critical: true, desc: 'ZIP archives'},
                    // Performance
                    'opcache': {critical: false, perf: true, desc: 'Bytecode cache'},
                    'apcu': {critical: false, perf: true, desc: 'User cache'},
                    'redis': {critical: false, perf: true, desc: 'Redis object cache'},
                    'memcached': {critical: false, perf: true, desc: 'Memcached cache'},
                    // Imagen
                    'gd': {critical: false, img: true, desc: 'Image processing'},
                    'imagick': {critical: false, img: true, desc: 'ImageMagick'},
                    // Opcionales utiles
                    'intl': {critical: false, desc: 'Internationalization'},
                    'sodium': {critical: false, desc: 'Modern crypto'},
                    'bcmath': {critical: false, desc: 'Arbitrary precision'},
                    'gmp': {critical: false, desc: 'Big integers'},
                    'iconv': {critical: false, desc: 'Charset conversion'},
                    'simplexml': {critical: false, desc: 'SimpleXML'},
                    'xmlreader': {critical: false, desc: 'XML streaming'},
                    'xmlwriter': {critical: false, desc: 'XML writing'},
                    'soap': {critical: false, desc: 'SOAP protocol'},
                    'sockets': {critical: false, desc: 'Socket support'},
                    'pdo_mysql': {critical: false, desc: 'PDO MySQL'}
                };
                
                var html = '';
                
                // Seccion: Criticas
                html += '<h4 class="dr-ext-section">Criticas para WordPress</h4>';
                html += '<div class="dr-extensions-grid">';
                for (var ext in wpPerformanceExt) {
                    if (!wpPerformanceExt[ext].critical) continue;
                    var isLoaded = loadedExt.indexOf(ext) !== -1;
                    var statusClass = isLoaded ? 'loaded' : 'critical-missing';
                    html += '<div class="dr-extension-item ' + statusClass + '">';
                    html += '<span class="dr-ext-status">' + (isLoaded ? '<span class="dashicons dashicons-yes"></span>' : '<span class="dashicons dashicons-no" style="color:#dc3232"></span>') + '</span>';
                    html += '<span class="dr-ext-name">' + ext + '</span>';
                    html += '<span class="dr-ext-desc">' + wpPerformanceExt[ext].desc + '</span>';
                    html += '</div>';
                }
                html += '</div>';
                
                // Seccion: Performance
                html += '<h4 class="dr-ext-section">Performance & Cache</h4>';
                html += '<div class="dr-extensions-grid">';
                for (var ext in wpPerformanceExt) {
                    if (!wpPerformanceExt[ext].perf) continue;
                    var isLoaded = loadedExt.indexOf(ext) !== -1;
                    var statusClass = isLoaded ? 'loaded' : 'optional-missing';
                    html += '<div class="dr-extension-item ' + statusClass + '">';
                    html += '<span class="dr-ext-status">' + (isLoaded ? '<span class="dashicons dashicons-yes"></span>' : '<span class="dashicons dashicons-marker" style="color:#f0ad4e"></span>') + '</span>';
                    html += '<span class="dr-ext-name">' + ext + '</span>';
                    html += '<span class="dr-ext-desc">' + wpPerformanceExt[ext].desc + '</span>';
                    html += '</div>';
                }
                html += '</div>';
                
                // Seccion: Imagen
                html += '<h4 class="dr-ext-section">Procesamiento de Imagenes</h4>';
                html += '<div class="dr-extensions-grid">';
                for (var ext in wpPerformanceExt) {
                    if (!wpPerformanceExt[ext].img) continue;
                    var isLoaded = loadedExt.indexOf(ext) !== -1;
                    var statusClass = isLoaded ? 'loaded' : 'optional-missing';
                    html += '<div class="dr-extension-item ' + statusClass + '">';
                    html += '<span class="dr-ext-status">' + (isLoaded ? '<span class="dashicons dashicons-yes"></span>' : '<span class="dashicons dashicons-marker" style="color:#f0ad4e"></span>') + '</span>';
                    html += '<span class="dr-ext-name">' + ext + '</span>';
                    html += '<span class="dr-ext-desc">' + wpPerformanceExt[ext].desc + '</span>';
                    html += '</div>';
                }
                html += '</div>';
                
                // Seccion: Otras cargadas
                html += '<h4 class="dr-ext-section">Todas las extensiones cargadas (' + loadedExt.length + ')</h4>';
                html += '<div class="dr-extensions-all">';
                loadedExt.sort().forEach(function(ext) {
                    html += '<span class="dr-ext-tag">' + ext + '</span>';
                });
                html += '</div>';
                
                return html;
            }
            
            function buildConfigTab(phpInfo) {
                var html = '<div class="dr-config-sections">';
                
                // PHP Core
                html += '<div class="dr-config-section">';
                html += '<h4>PHP Core</h4>';
                html += '<table class="dr-config-table">';
                html += '<tr><td>Version</td><td><strong>' + (phpInfo.php_version || 'N/A') + '</strong></td></tr>';
                html += '<tr><td>SAPI</td><td>' + (phpInfo.sapi || 'N/A') + '</td></tr>';
                html += '<tr><td>Zend Engine</td><td>' + (phpInfo.zend_version || 'N/A') + '</td></tr>';
                html += '<tr><td>Architecture</td><td>' + (phpInfo.architecture || 'N/A') + '</td></tr>';
                html += '</table></div>';
                
                // Limites
                html += '<div class="dr-config-section">';
                html += '<h4>Limites</h4>';
                html += '<table class="dr-config-table">';
                html += '<tr><td>memory_limit</td><td><strong>' + (phpInfo.memory_limit || 'N/A') + '</strong></td></tr>';
                html += '<tr><td>max_execution_time</td><td>' + (phpInfo.max_execution_time || 'N/A') + 's</td></tr>';
                html += '<tr><td>max_input_vars</td><td>' + (phpInfo.max_input_vars || 'N/A') + '</td></tr>';
                html += '<tr><td>upload_max_filesize</td><td>' + (phpInfo.upload_max_filesize || 'N/A') + '</td></tr>';
                html += '<tr><td>post_max_size</td><td>' + (phpInfo.post_max_size || 'N/A') + '</td></tr>';
                html += '</table></div>';
                
                // OPcache
                html += '<div class="dr-config-section">';
                html += '<h4>OPcache</h4>';
                html += '<table class="dr-config-table">';
                html += '<tr><td>Estado</td><td><strong style="color:' + (phpInfo.opcache_enabled ? '#46b450' : '#dc3232') + '">' + (phpInfo.opcache_enabled ? 'Habilitado' : 'Deshabilitado') + '</strong></td></tr>';
                if (phpInfo.opcache_enabled) {
                    html += '<tr><td>Hit Rate</td><td>' + (phpInfo.opcache_hit_rate ? parseFloat(phpInfo.opcache_hit_rate).toFixed(2) + '%' : 'N/A') + '</td></tr>';
                    html += '<tr><td>JIT</td><td>' + (phpInfo.jit_enabled ? 'Habilitado' : 'Deshabilitado') + '</td></tr>';
                }
                html += '</table></div>';
                
                // Server
                html += '<div class="dr-config-section">';
                html += '<h4>Servidor</h4>';
                html += '<table class="dr-config-table">';
                html += '<tr><td>Software</td><td>' + (phpInfo.server_software || 'N/A') + '</td></tr>';
                html += '<tr><td>LiteSpeed</td><td>' + (phpInfo.is_litespeed ? 'Si' : 'No') + '</td></tr>';
                html += '</table></div>';
                
                html += '</div>';
                return html;
            }
            
            function buildRecommendationsTab(phpInfo) {
                var html = '<div class="dr-recommendations-list">';
                var recommendations = [];
                var loadedExt = phpInfo.extensions || [];
                
                // Si hay recomendaciones del endpoint, usarlas
                if (phpInfo.recommendations && phpInfo.recommendations.length > 0) {
                    phpInfo.recommendations.forEach(function(rec) {
                        recommendations.push({
                            type: rec.type || 'info',
                            title: rec.title || rec,
                            desc: rec.desc || rec
                        });
                    });
                } else {
                    // Generar recomendaciones basadas en datos reales
                    
                    // Version PHP
                    var phpVersion = phpInfo.php_version || '7.0';
                    var majorVersion = parseFloat(phpVersion);
                    if (majorVersion < 8.0) {
                        recommendations.push({
                            type: 'error',
                            title: 'Actualizar PHP urgente',
                            desc: 'PHP ' + phpVersion + ' esta desactualizado. WordPress 6.x requiere PHP 7.4+ y recomienda 8.1+'
                        });
                    } else if (majorVersion < 8.1) {
                        recommendations.push({
                            type: 'warning',
                            title: 'Considerar actualizar PHP',
                            desc: 'PHP ' + phpVersion + ' es compatible pero PHP 8.1+ ofrece mejor rendimiento.'
                        });
                    } else {
                        recommendations.push({
                            type: 'success',
                            title: 'Version PHP optima',
                            desc: 'PHP ' + phpVersion + ' es excelente para WordPress.'
                        });
                    }
                    
                    // Memoria
                    var memoryMB = parseInt(phpInfo.memory_limit) || 128;
                    if (memoryMB < 128) {
                        recommendations.push({
                            type: 'error',
                            title: 'Memoria insuficiente',
                            desc: 'Solo ' + phpInfo.memory_limit + '. Minimo recomendado: 256M para WordPress, 512M para WooCommerce.'
                        });
                    } else if (memoryMB < 256) {
                        recommendations.push({
                            type: 'warning',
                            title: 'Aumentar memoria',
                            desc: 'Actual: ' + phpInfo.memory_limit + '. Recomendado 256M+ para mejor rendimiento.'
                        });
                    }
                    
                    // OPcache
                    if (!phpInfo.opcache_enabled) {
                        recommendations.push({
                            type: 'error',
                            title: 'Habilitar OPcache',
                            desc: 'OPcache esta deshabilitado. Habilitarlo puede mejorar rendimiento 2-3x.'
                        });
                    } else if (phpInfo.opcache_hit_rate && phpInfo.opcache_hit_rate < 90) {
                        recommendations.push({
                            type: 'warning',
                            title: 'Optimizar OPcache',
                            desc: 'Hit rate: ' + parseFloat(phpInfo.opcache_hit_rate).toFixed(1) + '%. Considerar aumentar opcache.memory_consumption.'
                        });
                    }
                    
                    // Extensiones criticas faltantes
                    var criticalMissing = [];
                    ['curl', 'mbstring', 'json', 'mysqli', 'xml', 'zip', 'openssl'].forEach(function(ext) {
                        if (loadedExt.indexOf(ext) === -1) criticalMissing.push(ext);
                    });
                    if (criticalMissing.length > 0) {
                        recommendations.push({
                            type: 'error',
                            title: 'Extensiones criticas faltantes',
                            desc: 'Instalar: ' + criticalMissing.join(', ')
                        });
                    }
                    
                    // Imagick vs GD
                    var hasImagick = loadedExt.indexOf('imagick') !== -1;
                    var hasGd = loadedExt.indexOf('gd') !== -1;
                    if (!hasImagick && !hasGd) {
                        recommendations.push({
                            type: 'error',
                            title: 'Sin procesamiento de imagenes',
                            desc: 'Falta GD o ImageMagick. WordPress no podra procesar imagenes.'
                        });
                    } else if (!hasImagick && hasGd) {
                        recommendations.push({
                            type: 'info',
                            title: 'Considerar ImageMagick',
                            desc: 'GD funciona pero ImageMagick ofrece mejor calidad y soporte WebP/AVIF.'
                        });
                    }
                    
                    // Max execution time
                    var maxExec = parseInt(phpInfo.max_execution_time) || 30;
                    if (maxExec < 60) {
                        recommendations.push({
                            type: 'warning',
                            title: 'Aumentar max_execution_time',
                            desc: 'Actual: ' + maxExec + 's. Para imports/exports grandes se recomienda 120s+.'
                        });
                    }
                    
                    // Max input vars (importante para WooCommerce)
                    var maxInputVars = parseInt(phpInfo.max_input_vars) || 1000;
                    if (maxInputVars < 3000) {
                        recommendations.push({
                            type: 'info',
                            title: 'Aumentar max_input_vars',
                            desc: 'Actual: ' + maxInputVars + '. Para WooCommerce con muchos atributos se recomienda 5000+.'
                        });
                    }
                    
                    // Upload size
                    var uploadMB = parseInt(phpInfo.upload_max_filesize) || 2;
                    if (uploadMB < 64) {
                        recommendations.push({
                            type: 'info',
                            title: 'Aumentar upload_max_filesize',
                            desc: 'Actual: ' + phpInfo.upload_max_filesize + '. Para media pesado se recomienda 64M+.'
                        });
                    }
                }
                
                // Renderizar
                if (recommendations.length === 0) {
                    recommendations.push({
                        type: 'success',
                        title: 'Configuracion optima',
                        desc: 'No se encontraron problemas. El servidor esta bien configurado para WordPress.'
                    });
                }
                
                recommendations.forEach(function(rec) {
                    var iconClass = rec.type === 'success' ? 'yes-alt' : (rec.type === 'error' ? 'dismiss' : (rec.type === 'warning' ? 'warning' : 'info'));
                    var iconColor = rec.type === 'success' ? '#46b450' : (rec.type === 'error' ? '#dc3232' : (rec.type === 'warning' ? '#f0ad4e' : '#00a0d2'));
                    html += '<div class="dr-recommendation-item ' + rec.type + '">';
                    html += '<span class="dr-rec-icon"><span class="dashicons dashicons-' + iconClass + '" style="color:' + iconColor + '"></span></span>';
                    html += '<div class="dr-rec-content">';
                    html += '<strong>' + rec.title + '</strong>';
                    html += '<p>' + rec.desc + '</p>';
                    html += '</div>';
                    html += '</div>';
                });
                
                html += '</div>';
                return html;
            }
                
           
            // ==========================================
            // CLOUDFLARE TAB FUNCTIONS
            // ==========================================
            
            var currentDomain = null;
            
            function loadCloudflareTab(domain) {
                currentDomain = domain;
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'dr_get_cf_config',
                        _nonce: drOnboarding.nonce,
                        domain: domain
                    },
                    success: function(response) {
                        $('.dr-cf-loading').hide();
                        if (response.success) {
                            $('.dr-cf-content').html(buildCloudflareContent(response.data)).show();
                            initCFSwitches();
                        } else {
                            $('.dr-cf-content').html('<div class="dr-cf-error"><span class="dashicons dashicons-warning"></span> ' + (response.data || 'Error desconocido') + '</div>').show();
                        }
                    },
                    error: function(xhr, status, error) {
                        $('.dr-cf-loading').hide();
                        var msg = 'Error de conexión';
                        try {
                            var resp = JSON.parse(xhr.responseText);
                            if (resp.data) msg = resp.data;
                        } catch(e) {
                            msg = 'Error: ' + status + ' - ' + error;
                        }
                        console.error('CF Tab Error:', xhr.responseText);
                        $('.dr-cf-content').html('<div class="dr-cf-error"><span class="dashicons dashicons-warning"></span> ' + msg + '</div>').show();
                    }
                });
            }
            
            function buildCloudflareContent(cfg) {
                var html = '';
                
                // Header con info de zona
                html += '<div class="dr-cf-header">';
                html += '<div class="dr-cf-status">';
                html += '<span class="dr-cf-badge dr-cf-badge-' + (cfg.status === 'active' ? 'success' : 'warning') + '">' + (cfg.status || 'Unknown') + '</span>';
                html += '<span class="dr-cf-plan">' + (cfg.plan || 'Free') + '</span>';
                if (cfg.preset_applied) {
                    html += '<span class="dr-cf-preset">Preset: ' + cfg.preset_applied + '</span>';
                }
                html += '</div>';
                html += '<small>Zone ID: ' + (cfg.zone_id || 'N/A').substring(0, 12) + '...</small>';
                html += '</div>';
                
                // Settings con switches
                html += '<div class="dr-cf-section">';
                html += '<h4><span class="dashicons dashicons-shield"></span> SSL/TLS</h4>';
                html += '<div class="dr-cf-settings-grid">';
                html += buildCFSetting('ssl', 'SSL Mode', cfg.settings.ssl, ['off', 'flexible', 'full', 'strict'], true);
                html += buildCFSwitch('always_use_https', 'Always HTTPS', cfg.settings.always_use_https);
                html += buildCFSwitch('automatic_https_rewrites', 'HTTPS Rewrites', cfg.settings.automatic_https_rewrites);
                html += buildCFSetting('min_tls_version', 'Min TLS', cfg.settings.min_tls_version, ['1.0', '1.1', '1.2', '1.3'], true);
                html += '</div></div>';
                
                html += '<div class="dr-cf-section">';
                html += '<h4><span class="dashicons dashicons-performance"></span> Performance</h4>';
                html += '<div class="dr-cf-settings-grid">';
                html += buildCFSwitch('http3', 'HTTP/3 (QUIC)', cfg.settings.http3);
                html += buildCFSwitch('brotli', 'Brotli Compression', cfg.settings.brotli);
                html += buildCFSwitch('0rtt', '0-RTT', cfg.settings['0rtt']);
                html += buildCFSwitch('early_hints', 'Early Hints', cfg.settings.early_hints);
                html += buildCFSwitch('rocket_loader', 'Rocket Loader', cfg.settings.rocket_loader);
                html += '</div></div>';
                
                html += '<div class="dr-cf-section">';
                html += '<h4><span class="dashicons dashicons-lock"></span> Security</h4>';
                html += '<div class="dr-cf-settings-grid">';
                html += buildCFSetting('security_level', 'Security Level', cfg.settings.security_level, ['off', 'essentially_off', 'low', 'medium', 'high', 'under_attack'], true);
                html += buildCFSwitch('browser_check', 'Browser Check', cfg.settings.browser_check);
                html += buildCFSwitch('email_obfuscation', 'Email Obfuscation', cfg.settings.email_obfuscation);
                html += buildCFSwitch('hotlink_protection', 'Hotlink Protection', cfg.settings.hotlink_protection);
                html += '</div></div>';
                
                html += '<div class="dr-cf-section">';
                html += '<h4><span class="dashicons dashicons-database"></span> Cache</h4>';
                html += '<div class="dr-cf-settings-grid">';
                html += buildCFSetting('browser_cache_ttl', 'Browser Cache TTL', cfg.settings.browser_cache_ttl, [0, 1800, 3600, 7200, 14400, 28800, 86400, 172800, 604800, 2592000], false);
                html += buildCFSetting('cache_level', 'Cache Level', cfg.settings.cache_level, ['bypass', 'basic', 'simplified', 'aggressive'], true);
                html += buildCFSwitch('development_mode', 'Development Mode', cfg.settings.development_mode);
                html += buildCFSwitch('always_online', 'Always Online', cfg.settings.always_online);
                html += '</div></div>';
                
                // Nameservers
                if (cfg.nameservers && cfg.nameservers.length > 0) {
                    html += '<div class="dr-cf-section">';
                    html += '<h4><span class="dashicons dashicons-admin-site"></span> Nameservers</h4>';
                    html += '<div class="dr-cf-ns-list">';
                    cfg.nameservers.forEach(function(ns) {
                        html += '<code>' + ns + '</code> ';
                    });
                    html += '</div></div>';
                }
                
                // Cache Rules
                if (cfg.cache_rules && cfg.cache_rules.length > 0) {
                    html += '<div class="dr-cf-section">';
                    html += '<h4><span class="dashicons dashicons-admin-page"></span> Cache Rules (' + cfg.cache_rules.length + ')</h4>';
                    html += '<div class="dr-cf-rules-list">';
                    cfg.cache_rules.forEach(function(rule) {
                        html += '<div class="dr-cf-rule">';
                        html += '<span class="dr-cf-rule-name">' + rule.name + '</span>';
                        html += '<span class="dr-cf-rule-action">' + (rule.action || 'custom') + '</span>';
                        html += '</div>';
                    });
                    html += '</div></div>';
                }
                
                // Firewall Rules
                if (cfg.firewall_rules && cfg.firewall_rules.length > 0) {
                    html += '<div class="dr-cf-section">';
                    html += '<h4><span class="dashicons dashicons-shield-alt"></span> Firewall Rules (' + cfg.firewall_rules.length + ')</h4>';
                    html += '<div class="dr-cf-rules-list">';
                    cfg.firewall_rules.forEach(function(rule) {
                        html += '<div class="dr-cf-rule">';
                        html += '<span class="dr-cf-rule-name">' + rule.description + '</span>';
                        html += '<span class="dr-cf-rule-action dr-cf-action-' + rule.action + '">' + rule.action + '</span>';
                        html += '</div>';
                    });
                    html += '</div></div>';
                }
                
                html += '<div class="dr-cf-footer">';
                html += '<small>Última consulta: ' + (cfg.fetched_at || 'N/A') + '</small>';
                html += '</div>';
                
                return html;
            }
            
            function buildCFSwitch(key, label, value) {
                var isOn = value === 'on' || value === true;
                return '<div class="dr-cf-setting">' +
                    '<label>' + label + '</label>' +
                    '<label class="dr-cf-switch">' +
                    '<input type="checkbox" data-setting="' + key + '" ' + (isOn ? 'checked' : '') + '>' +
                    '<span class="dr-cf-slider"></span>' +
                    '</label>' +
                    '</div>';
            }
            
            function buildCFSetting(key, label, value, options, isString) {
                var html = '<div class="dr-cf-setting">';
                html += '<label>' + label + '</label>';
                html += '<select data-setting="' + key + '" class="dr-cf-select">';
                options.forEach(function(opt) {
                    var optVal = isString ? opt : opt;
                    var optLabel = isString ? opt : (opt === 0 ? 'Respeta origen' : formatSeconds(opt));
                    var selected = (String(value) === String(optVal)) ? ' selected' : '';
                    html += '<option value="' + optVal + '"' + selected + '>' + optLabel + '</option>';
                });
                html += '</select>';
                html += '</div>';
                return html;
            }
            
            function formatSeconds(seconds) {
                if (seconds >= 86400) return (seconds / 86400) + ' días';
                if (seconds >= 3600) return (seconds / 3600) + ' horas';
                if (seconds >= 60) return (seconds / 60) + ' min';
                return seconds + 's';
            }
            
            function initCFSwitches() {
                // Toggle switches
                $(document).off('change', '.dr-cf-switch input').on('change', '.dr-cf-switch input', function() {
                    var $switch = $(this);
                    var setting = $switch.data('setting');
                    var value = $switch.is(':checked') ? 'on' : 'off';
                    updateCFSetting(setting, value, $switch.closest('.dr-cf-setting'));
                });
                
                // Select changes
                $(document).off('change', '.dr-cf-select').on('change', '.dr-cf-select', function() {
                    var $select = $(this);
                    var setting = $select.data('setting');
                    var value = $select.val();
                    updateCFSetting(setting, value, $select.closest('.dr-cf-setting'));
                });
            }
            
            function updateCFSetting(setting, value, $container) {
                $container.addClass('dr-cf-updating');
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'dr_update_cf_setting',
                        _nonce: drOnboarding.nonce,
                        domain: currentDomain,
                        setting: setting,
                        value: value
                    },
                    success: function(response) {
                        $container.removeClass('dr-cf-updating');
                        if (response.success) {
                            $container.addClass('dr-cf-success');
                            setTimeout(function() { $container.removeClass('dr-cf-success'); }, 1500);
                        } else {
                            $container.addClass('dr-cf-error-setting');
                            alert('Error: ' + (response.data || 'No se pudo actualizar'));
                            setTimeout(function() { $container.removeClass('dr-cf-error-setting'); }, 2000);
                        }
                    },
                    error: function() {
                        $container.removeClass('dr-cf-updating').addClass('dr-cf-error-setting');
                        setTimeout(function() { $container.removeClass('dr-cf-error-setting'); }, 2000);
                    }
                });
            }
            
            function initPHPModalTabs() {
                $(document).on('click', '.dr-php-tab', function() {
                    var tab = $(this).data('tab');
                    
                    $('.dr-php-tab').removeClass('active');
                    $(this).addClass('active');
                    
                    $('.dr-php-tab-content').removeClass('active');
                    $('.dr-php-tab-content[data-tab="' + tab + '"]').addClass('active');
                });
            }
            
            // Cerrar modal
            $(document).on('click', '.dr-php-modal-close, .dr-php-modal-close-btn', function() {
                $('.dr-php-modal-overlay').removeClass('active');
                setTimeout(function() {
                    $('.dr-php-modal-overlay').remove();
                }, 300);
            });
            
            // Cerrar al hacer clic fuera del modal
            $(document).on('click', '.dr-php-modal-overlay', function(e) {
                if ($(e.target).hasClass('dr-php-modal-overlay')) {
                    $(this).removeClass('active');
                    setTimeout(function() {
                        $('.dr-php-modal-overlay').remove();
                    }, 300);
                }
            });
            
            // Cerrar con ESC
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('.dr-php-modal-overlay').length) {
                    $('.dr-php-modal-overlay').removeClass('active');
                    setTimeout(function() {
                        $('.dr-php-modal-overlay').remove();
                    }, 300);
                }
            });
        });
        </script>
        <?php
    }

    // =========================================================================
    // AJAX Handlers
    // =========================================================================

    /**
     * AJAX: Encolar dominio para onboarding
     */
    public function ajax_enqueue(): void {
        check_ajax_referer('dr_onboarding_nonce', '_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        $domain = sanitize_text_field($_POST['domain'] ?? '');
        $preset = sanitize_text_field($_POST['preset'] ?? 'wp');
        $auto_ns = (bool) ($_POST['auto_ns'] ?? false);

        if (empty($domain)) {
            wp_send_json_error('Dominio requerido');
        }

        // Verificar si el dominio ya está en Cloudflare
        $cf_service = Dominios_Reseller_Cloudflare_Service::get_instance();
        $existing_zone = $cf_service->get_zone($domain);
        
        if ($existing_zone) {
            // El dominio ya está en CF, verificar estado de onboarding
            $current_state = Dominios_Reseller_Onboarding_DB::get_onboarding_state($domain);
            
            if ($current_state && in_array($current_state['state'], ['onboarded', 'completed'])) {
                wp_send_json_error('El dominio ya está completamente configurado en Cloudflare');
                return;
            }
            
            // Permitir aplicar preset a zona existente
            // Podríamos aquí obtener la configuración actual y mostrar diferencias
            // Pero por ahora, procedemos con el onboarding normal
        }

        $result = $this->get_worker()->enqueue($domain, $preset, $auto_ns);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['error']);
        }
    }

    /**
     * AJAX: Reintentar onboarding
     */
    public function ajax_retry(): void {
        check_ajax_referer('dr_onboarding_nonce', '_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        $domain = sanitize_text_field($_POST['domain'] ?? '');

        if (empty($domain)) {
            wp_send_json_error('Dominio requerido');
        }

        $result = $this->get_worker()->retry($domain);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['error']);
        }
    }

    /**
     * AJAX: Procesar cola inmediatamente
     */
    public function ajax_process_now(): void {
        check_ajax_referer('dr_onboarding_nonce', '_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        $result = $this->get_worker()->process_now();

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['error']);
        }
    }

    /**
     * AJAX: Obtener estado de cola
     */
    public function ajax_get_status(): void {
        check_ajax_referer('dr_onboarding_nonce', '_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        $status = $this->get_worker()->get_queue_status();
        wp_send_json_success($status);
    }

    /**
     * AJAX: Obtener logs de un dominio
     */
    public function ajax_get_logs(): void {
        check_ajax_referer('dr_onboarding_nonce', '_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        $domain = sanitize_text_field($_POST['domain'] ?? '');
        $run_id = sanitize_text_field($_POST['run_id'] ?? '');

        if (!empty($run_id)) {
            $logs = Dominios_Reseller_Onboarding_DB::get_logs_by_run($run_id);
        } else {
            $logs = Dominios_Reseller_Onboarding_DB::get_logs_by_domain($domain, 100);
        }

        wp_send_json_success($logs);
    }

    /**
     * AJAX: Verificar conexión Openprovider
     */
    public function ajax_verify_openprovider(): void {
        check_ajax_referer('dr_onboarding_nonce', '_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        if (!class_exists('Dominios_Reseller_Openprovider_Service')) {
            wp_send_json_error('Servicio Openprovider no disponible');
        }

        $result = Dominios_Reseller_Openprovider_Service::get_instance()->verify_connection();

        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['error']);
        }
    }

    /**
     * AJAX: Obtener NS actuales de un dominio (DNS lookup)
     */
    public function ajax_get_domain_ns(): void {
        check_ajax_referer('dr_onboarding_nonce', '_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        $domain = sanitize_text_field($_POST['domain'] ?? '');
        if (empty($domain)) {
            wp_send_json_error('Dominio requerido');
        }

        $ns = $this->get_domain_nameservers($domain);
        wp_send_json_success([
            'domain' => $domain,
            'nameservers' => $ns['nameservers'],
            'is_cloudflare' => $ns['is_cloudflare'],
            'provider' => $ns['provider']
        ]);
    }

    /**
     * AJAX: Refrescar estado de un dominio — consulta CF API + DNS en vivo
     * 
     * Actualiza zone_id, NS, estado y error en la BD con datos frescos.
     * Devuelve el nuevo estado para actualizar la tarjeta en el UI.
     */
    public function ajax_refresh_domain_status(): void {
        check_ajax_referer('dr_onboarding_nonce', '_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        $domain = sanitize_text_field($_POST['domain'] ?? '');
        if (empty($domain)) {
            wp_send_json_error('Dominio requerido');
        }

        $cf_service = Dominios_Reseller_Cloudflare_Service::get_instance();
        
        // 1. Consultar zona en Cloudflare API
        $zone = $cf_service->get_zone($domain);
        
        // 2. Obtener NS actuales por DNS
        $ns_info = $this->get_domain_nameservers($domain);
        
        $result = [
            'domain'        => $domain,
            'nameservers'   => $ns_info['nameservers'],
            'provider'      => $ns_info['provider'],
            'is_cloudflare' => $ns_info['is_cloudflare'],
        ];

        // Obtener estado actual de la BD
        $current_state = Dominios_Reseller_Onboarding_DB::get_onboarding_state($domain);
        $old_state = $current_state['state'] ?? 'none';

        if ($zone) {
            // La zona existe en CF
            $result['zone_id']      = $zone['zone_id'];
            $result['zone_status']  = $zone['status'];
            $result['plan']         = $zone['plan_name'];
            $result['cf_ns']        = $zone['name_servers'];
            $result['paused']       = $zone['paused'];

            // Verificar si NS del dominio apuntan a CF
            $ns_check = $cf_service->verify_domain_ns($domain, $zone['name_servers']);
            $result['ns_verified'] = $ns_check['verified'];
            $result['ns_message']  = $ns_check['message'];
            $result['actual_ns']   = $ns_check['actual_ns'];
            $result['expected_ns'] = $ns_check['expected_ns'];

            // Determinar nuevo estado basado en datos frescos
            $new_data = [
                'zone_id'     => $zone['zone_id'],
                'nameservers' => json_encode($zone['name_servers']),
                'ns_verified' => $ns_check['verified'] ? 1 : 0,
            ];

            if ($ns_check['verified']) {
                // NS ok — si estaba en pending_ns o needs_manual_ns, promover
                if (in_array($old_state, ['pending_ns', 'needs_manual_ns'])) {
                    $new_data['state'] = 'onboarded';
                    $new_data['last_error'] = null;
                    $result['state'] = 'onboarded';
                    $result['state_label'] = 'Completado';
                    $result['state_changed'] = true;
                } else {
                    $result['state'] = $old_state;
                    $result['state_label'] = $this->get_state_label($old_state);
                    $result['state_changed'] = false;
                }
            } else {
                // NS NO apuntan a CF
                if (in_array($old_state, ['onboarded', 'partial'])) {
                    // Estaba completado pero NS ya no apuntan a CF
                    $new_data['state'] = 'pending_ns';
                    $new_data['last_error'] = $ns_check['message'];
                    $result['state'] = 'pending_ns';
                    $result['state_label'] = 'NS pendientes';
                    $result['state_changed'] = true;
                } elseif ($old_state === 'none') {
                    // Zona existe pero nunca se hizo onboarding
                    $result['state'] = 'none';
                    $result['state_label'] = 'Sin procesar (zona existe en CF)';
                    $result['state_changed'] = false;
                } else {
                    $result['state'] = $old_state;
                    $result['state_label'] = $this->get_state_label($old_state);
                    $result['state_changed'] = false;
                }
            }

            // Guardar datos frescos en BD
            Dominios_Reseller_Onboarding_DB::upsert_onboarding($domain, $new_data);

        } else {
            // La zona NO existe en CF
            $result['zone_id']      = null;
            $result['zone_status']  = null;
            $result['plan']         = null;
            $result['cf_ns']        = [];
            $result['ns_verified']  = false;
            $result['ns_message']   = 'Zona no encontrada en Cloudflare';

            // Si tenía estado avanzado, degradar a none
            if (in_array($old_state, ['onboarded', 'partial', 'pending_ns', 'needs_manual_ns'])) {
                Dominios_Reseller_Onboarding_DB::upsert_onboarding($domain, [
                    'zone_id'     => null,
                    'state'       => 'none',
                    'last_error'  => 'Zona eliminada de Cloudflare',
                    'ns_verified' => 0,
                ]);
                $result['state'] = 'none';
                $result['state_label'] = 'Sin procesar';
                $result['state_changed'] = true;
            } else {
                $result['state'] = $old_state;
                $result['state_label'] = $this->get_state_label($old_state);
                $result['state_changed'] = false;
            }
        }

        // Obtener estado actualizado de la BD para devolver last_error limpio
        $updated_state = Dominios_Reseller_Onboarding_DB::get_onboarding_state($domain);
        $result['last_error'] = $updated_state['last_error'] ?? null;
        $result['old_state'] = $old_state;

        wp_send_json_success($result);
    }

    /**
     * AJAX: Pedir a Cloudflare que vuelva a comprobar los NS de la zona.
     *
     * Útil para zonas en estado `moved` o `pending`. Sin esta llamada CF
     * puede tardar horas en volver a chequear los NS por su cuenta. Tras
     * la llamada, refrescamos el estado del dominio.
     */
    public function ajax_reactivate_zone(): void {
        check_ajax_referer('dr_onboarding_nonce', '_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        $domain  = sanitize_text_field($_POST['domain'] ?? '');
        $zone_id = sanitize_text_field($_POST['zone_id'] ?? '');

        if (empty($domain) || empty($zone_id)) {
            wp_send_json_error('Dominio y zone_id requeridos');
        }

        $cf_service = Dominios_Reseller_Cloudflare_Service::get_instance();

        if (!method_exists($cf_service, 'trigger_zone_activation_check')) {
            wp_send_json_error('Servicio Cloudflare no soporta reactivación. Actualiza el plugin.');
        }

        $result = $cf_service->trigger_zone_activation_check($zone_id);

        if (is_wp_error($result)) {
            wp_send_json_error('CF rechazó la solicitud: ' . $result->get_error_message());
        }

        // Registrar en logs si la BD lo soporta
        if (class_exists('Dominios_Reseller_Onboarding_DB')
            && method_exists('Dominios_Reseller_Onboarding_DB', 'log')) {
            $run_id = 'manual-' . wp_generate_uuid4();
            Dominios_Reseller_Onboarding_DB::log(
                $run_id,
                $domain,
                'activation_check',
                'info',
                'Reactivación manual de zona solicitada a Cloudflare',
                ['zone_id' => $zone_id]
            );
        }

        wp_send_json_success([
            'message' => 'Cloudflare está re-verificando los NS de la zona. La zona pasará a `active` en unos minutos si los NS apuntan correctamente.',
            'domain'  => $domain,
            'zone_id' => $zone_id,
        ]);
    }

    /**
     * AJAX: Obtener NS de múltiples dominios (batch) + verificación Openprovider
     */
    public function ajax_get_batch_ns(): void {
        check_ajax_referer('dr_onboarding_nonce', '_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        $domains = isset($_POST['domains']) ? array_map('sanitize_text_field', (array)$_POST['domains']) : [];
        if (empty($domains)) {
            wp_send_json_error('Dominios requeridos');
        }

        // Limitar a 10 por batch para evitar timeout
        $domains = array_slice($domains, 0, 10);
        $results = [];
        
        // Verificar si Openprovider está configurado
        $op_service = null;
        $op_configured = false;
        if (class_exists('Dominios_Reseller_Openprovider_Service')) {
            $op_service = Dominios_Reseller_Openprovider_Service::get_instance();
            $op_configured = $op_service->is_configured();
        }

        foreach ($domains as $domain) {
            $ns_info = $this->get_domain_nameservers($domain);
            
            // Añadir info de Openprovider si está configurado
            $ns_info['in_openprovider'] = false;
            $ns_info['op_status'] = null;
            $ns_info['op_expiry'] = null;
            
            if ($op_configured && $op_service) {
                $op_check = $op_service->domain_exists($domain);
                $ns_info['in_openprovider'] = $op_check['exists'];
                if ($op_check['exists'] && !empty($op_check['info'])) {
                    $ns_info['op_status'] = $op_check['info']['status'];
                    $ns_info['op_expiry'] = $op_check['info']['expiry_date'];
                }
            }
            
            $results[$domain] = $ns_info;
        }

        wp_send_json_success($results);
    }

    /**
     * AJAX: Obtener información PHP en lotes
     */
    public function ajax_get_batch_php(): void {
        check_ajax_referer('dr_onboarding_nonce', '_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        $domains = isset($_POST['domains']) ? array_map('sanitize_text_field', (array)$_POST['domains']) : [];
        if (empty($domains)) {
            wp_send_json_error('Dominios requeridos');
        }

        // Limitar a 10 por batch (más rápido porque primero busca en BD)
        $domains = array_slice($domains, 0, 10);
        $results = [];

        global $wpdb;
        $table = $wpdb->prefix . 'dominios_reseller';

        foreach ($domains as $domain) {
            try {
                // PRIORIDAD 1: Buscar datos del endpoint en BD (fuente real y completa)
                $row = $wpdb->get_row($wpdb->prepare(
                    "SELECT php_info, wp_readiness_score, php_info_updated_at FROM {$table} WHERE primary_domain = %s AND is_primary = 1",
                    $domain
                ));
                
                if ($row && !empty($row->php_info)) {
                    $php_data = json_decode($row->php_info, true);
                    if ($php_data) {
                        // Añadir metadatos
                        $php_data['source'] = 'endpoint';
                        $php_data['updated_at'] = $row->php_info_updated_at;
                        $php_data['wp_readiness_score'] = intval($row->wp_readiness_score);
                        
                        // Convertir al formato del modal
                        $results[$domain] = $this->convert_endpoint_to_modal_format($php_data);
                        continue;
                    }
                }

                // PRIORIDAD 2: WHM API (fallback si no hay datos del endpoint)
                $php_info = $this->get_php_info_from_whm($domain);

                if ($php_info['success']) {
                    $results[$domain] = $php_info['data'];
                } else {
                    // PRIORIDAD 3: Fallback con información simulada
                    $results[$domain] = $this->get_php_fallback_info($domain);
                }
            } catch (Exception $e) {
                // En caso de error, usar fallback
                $results[$domain] = $this->get_php_fallback_info($domain);
            }
        }

        wp_send_json_success($results);
    }

    /**
     * AJAX: Preview del payload de un preset
     */
    public function ajax_preview_preset(): void {
        check_ajax_referer('dr_onboarding_nonce', '_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        $preset_key = sanitize_text_field($_POST['preset_key'] ?? '');
        if (empty($preset_key)) {
            wp_send_json_error('Preset no especificado');
        }

        $preset = Dominios_Reseller_Onboarding_DB::get_preset($preset_key);
        if (!$preset) {
            wp_send_json_error('Preset no encontrado: ' . $preset_key);
        }

        $payload = $preset['payload_decoded'] ?? [];
        
        // Formatear para visualización
        $preview = [
            'preset_key'  => $preset['preset_key'],
            'name'        => $preset['name'],
            'description' => $preset['description'],
            'settings'    => $payload['settings'] ?? [],
            'cache_rules' => $payload['cache_rules'] ?? [],
            'notes'       => $payload['notes'] ?? '',
            'raw_json'    => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        ];

        wp_send_json_success($preview);
    }

    /**
     * Obtener nameservers actuales de un dominio via DNS
     */
    private function get_domain_nameservers(string $domain): array {
        $result = [
            'nameservers' => [],
            'is_cloudflare' => false,
            'provider' => 'unknown'
        ];

        // Limpiar dominio
        $domain = strtolower(trim($domain, '. '));
        if (str_starts_with($domain, 'www.')) {
            $domain = substr($domain, 4);
        }

        // Lookup NS records
        $ns_records = @dns_get_record($domain, DNS_NS);
        
        if ($ns_records && is_array($ns_records)) {
            foreach ($ns_records as $record) {
                if (isset($record['target'])) {
                    $result['nameservers'][] = strtolower($record['target']);
                }
            }
            sort($result['nameservers']);
        }

        // Detectar proveedor
        if (!empty($result['nameservers'])) {
            $first_ns = $result['nameservers'][0];
            
            if (str_contains($first_ns, 'cloudflare')) {
                $result['is_cloudflare'] = true;
                $result['provider'] = 'cloudflare';
            } elseif (str_contains($first_ns, 'openprovider') || str_contains($first_ns, 'openprovider.eu')) {
                $result['provider'] = 'openprovider';
            } elseif (str_contains($first_ns, 'godaddy') || str_contains($first_ns, 'domaincontrol')) {
                $result['provider'] = 'godaddy';
            } elseif (str_contains($first_ns, 'ns.hostinger')) {
                $result['provider'] = 'hostinger';
            } elseif (str_contains($first_ns, 'registrar-servers')) {
                $result['provider'] = 'namecheap';
            } else {
                $result['provider'] = 'other';
            }
        }

        return $result;
    }

    /**
     * AJAX: Obtener información de PHP para un dominio
     */
    public function ajax_get_php_info(): void {
        check_ajax_referer('dr_onboarding_nonce', '_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos');
        }

        $domain = sanitize_text_field($_POST['domain'] ?? '');

        if (empty($domain)) {
            wp_send_json_error('Dominio requerido');
        }

        try {
            global $wpdb;
            $table = $wpdb->prefix . 'dominios_reseller';
            
            // PRIORIDAD 1: Buscar en BD datos del endpoint (fuente real)
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT php_info, wp_readiness_score, php_info_updated_at FROM {$table} WHERE primary_domain = %s AND is_primary = 1",
                $domain
            ));
            
            if ($row && !empty($row->php_info)) {
                $php_data = json_decode($row->php_info, true);
                if ($php_data) {
                    // Añadir metadatos
                    $php_data['source'] = 'endpoint';
                    $php_data['updated_at'] = $row->php_info_updated_at;
                    $php_data['wp_readiness_score'] = intval($row->wp_readiness_score);
                    
                    // Convertir formato v2 del endpoint a formato del modal
                    $modal_data = $this->convert_endpoint_to_modal_format($php_data);
                    wp_send_json_success($modal_data);
                    return;
                }
            }
            
            // PRIORIDAD 2: WHM/cPanel (método anterior)
            $php_info = $this->get_php_info_from_whm($domain);

            if ($php_info['success']) {
                wp_send_json_success($php_info['data']);
            } else {
                // Fallback: información básica simulada
                $fallback_info = $this->get_php_fallback_info($domain);
                wp_send_json_success($fallback_info);
            }

        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Convertir datos del endpoint v2 al formato esperado por el modal
     */
    private function convert_endpoint_to_modal_format(array $data): array {
        // El endpoint usa 'php' no 'php_config'
        $config = $data['php'] ?? $data['php_config'] ?? [];
        $limits = $data['limits'] ?? [];
        $upload = $data['upload'] ?? [];
        $extensions = $data['extensions'] ?? [];
        $opcache = $data['opcache'] ?? [];
        $server = $data['server'] ?? [];
        $image = $data['image_processing'] ?? [];
        $security = $data['security'] ?? [];
        $performance = $data['performance'] ?? [];
        
        // Extensiones - el endpoint usa 'all', 'wp_critical', 'wp_optional'
        $ext_list = $extensions['all'] ?? $extensions['all_loaded'] ?? [];
        $critical = $extensions['wp_critical'] ?? $extensions['critical'] ?? [];
        $optional = $extensions['wp_optional'] ?? $extensions['optional'] ?? [];
        
        // Calcular extensiones críticas faltantes
        $critical_missing = [];
        $critical_loaded = [];
        if (is_array($critical)) {
            foreach ($critical as $ext => $loaded) {
                if ($loaded) {
                    $critical_loaded[] = $ext;
                } else {
                    $critical_missing[] = $ext;
                }
            }
        }
        
        // Calcular extensiones opcionales faltantes
        $optional_missing = [];
        $optional_loaded = [];
        if (is_array($optional)) {
            foreach ($optional as $ext => $loaded) {
                if ($loaded) {
                    $optional_loaded[] = $ext;
                } else {
                    $optional_missing[] = $ext;
                }
            }
        }
        
        return [
            // Metadatos
            'source' => 'endpoint',
            'updated_at' => $data['updated_at'] ?? $data['checked_at'] ?? null,
            'domain' => $data['domain'] ?? 'N/A',
            
            // PHP Config
            'php_version' => $config['version'] ?? 'N/A',
            'php_version_id' => $config['version_id'] ?? 0,
            'sapi' => $config['sapi'] ?? 'N/A',
            'zend_version' => $config['zend_version'] ?? 'N/A',
            'architecture' => $config['architecture'] ?? 'N/A',
            
            // Límites
            'memory_limit' => $limits['memory_limit'] ?? 'N/A',
            'memory_limit_bytes' => $limits['memory_limit_bytes'] ?? 0,
            'max_execution_time' => $limits['max_execution_time'] ?? 0,
            'max_input_time' => $limits['max_input_time'] ?? 0,
            'max_input_vars' => $limits['max_input_vars'] ?? 0,
            
            // Upload
            'upload_max_filesize' => $upload['upload_max_filesize'] ?? 'N/A',
            'upload_max_bytes' => $upload['upload_max_bytes'] ?? 0,
            'post_max_size' => $upload['post_max_size'] ?? 'N/A',
            'post_max_bytes' => $upload['post_max_bytes'] ?? 0,
            'max_file_uploads' => $upload['max_file_uploads'] ?? 0,
            
            // OPcache
            'opcache_enabled' => $opcache['enabled'] ?? false,
            'opcache_hit_rate' => $opcache['hit_rate'] ?? 0,
            'opcache_memory_used' => $opcache['memory_used'] ?? 0,
            'opcache_cached_scripts' => $opcache['cached_scripts'] ?? 0,
            'jit_enabled' => $opcache['jit_enabled'] ?? false,
            
            // Servidor
            'server_software' => $server['software'] ?? 'N/A',
            'server_hostname' => $server['hostname'] ?? 'N/A',
            'server_os' => $server['os'] ?? 'N/A',
            'is_litespeed' => $server['litespeed'] ?? (stripos($server['software'] ?? '', 'litespeed') !== false),
            'lscache_available' => $server['lscache_available'] ?? false,
            
            // Extensiones
            'extensions' => $ext_list,
            'extensions_count' => $extensions['count'] ?? count($ext_list),
            'extensions_critical' => $critical,
            'extensions_critical_loaded' => $critical_loaded,
            'extensions_critical_missing' => $critical_missing,
            'extensions_optional' => $optional,
            'extensions_optional_loaded' => $optional_loaded,
            'extensions_optional_missing' => $optional_missing,
            
            // Procesamiento de imágenes
            'gd_version' => $image['gd_version'] ?? 'N/A',
            'gd_webp' => $image['gd_webp'] ?? false,
            'gd_avif' => $image['gd_avif'] ?? false,
            'imagick_version' => $image['imagick_version'] ?? 'N/A',
            'imagick_formats' => $image['imagick_formats'] ?? 0,
            
            // Seguridad
            'disable_functions' => $security['disable_functions'] ?? '',
            'open_basedir' => $security['open_basedir'] ?? false,
            'allow_url_fopen' => $security['allow_url_fopen'] ?? false,
            'display_errors' => $security['display_errors'] ?? false,
            
            // Performance
            'realpath_cache_size' => $performance['realpath_cache_size'] ?? 'N/A',
            'output_buffering' => $performance['output_buffering'] ?? 'N/A',
            'zlib_compression' => $performance['zlib_output_compression'] ?? false,
            
            // WP Readiness
            'wp_readiness_score' => $data['wp_readiness_score'] ?? 0,
            'wp_readiness_grade' => $data['wp_readiness_grade'] ?? 'N/A',
            
            // Recomendaciones (si existen)
            'recommendations' => $data['recommendations'] ?? [],
        ];
    }

    /**
     * Obtener información de PHP desde WHM/cPanel
     * Usa la misma lógica que funciona en Debug Hub
     */
    private function get_php_info_from_whm(string $domain): array {
        // Determinar a qué servidor pertenece el dominio
        $domain_info = $this->get_domain_server_info($domain);
        $server = $domain_info['server'] ?? 'uk'; // 'uk' o 'usa'
        
        // Obtener configuración de WHM según el servidor
        $opts = get_option('dominios_reseller_options', []);
        
        // Priorizar configuración por servidor (uk/usa)
        if ($server === 'usa' && !empty($opts['usa_server_ip']) && !empty($opts['usa_whm_token'])) {
            $whm_hostname = $opts['usa_server_ip'];
            $whm_token = $opts['usa_whm_token'];
            $whm_username = $opts['usa_whm_user'] ?? 'replanta';
        } elseif (!empty($opts['uk_server_ip']) && !empty($opts['uk_whm_token'])) {
            $whm_hostname = $opts['uk_server_ip'];
            $whm_token = $opts['uk_whm_token'];
            $whm_username = $opts['uk_whm_user'] ?? 'replanta';
        } else {
            return ['success' => false, 'error' => 'WHM no configurado para servidor ' . strtoupper($server)];
        }
        
        $whm_port = '2087';

        // ═══════════════════════════════════════════════════════════════
        // PASO 1: Obtener lista de cuentas (IMPORTANTE: api.version=1)
        // ═══════════════════════════════════════════════════════════════
        $url = "https://{$whm_hostname}:{$whm_port}/json-api/listaccts?api.version=1";

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'whm ' . $whm_username . ':' . $whm_token,
            ],
            'timeout' => 30,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            error_log("[DR PHP Modal] Error listaccts: " . $response->get_error_message());
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            error_log("[DR PHP Modal] Error HTTP {$status_code}: {$body}");
            return ['success' => false, 'error' => 'Error HTTP ' . $status_code];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!$body || !isset($body['data']['acct'])) {
            return ['success' => false, 'error' => 'Respuesta inválida de WHM'];
        }

        $accounts = $body['data']['acct'];
        $cpanel_user = null;

        // ═══════════════════════════════════════════════════════════════
        // PASO 2: Buscar dominio como principal
        // ═══════════════════════════════════════════════════════════════
        foreach ($accounts as $account) {
            if (($account['domain'] ?? '') === $domain) {
                $cpanel_user = $account['user'];
                break;
            }
        }

        // ═══════════════════════════════════════════════════════════════
        // PASO 3: Si no es principal, buscar en addon domains
        // ═══════════════════════════════════════════════════════════════
        if (!$cpanel_user && function_exists('obtener_addons_de_usuario')) {
            foreach ($accounts as $account) {
                $user = $account['user'] ?? '';
                if (empty($user)) continue;
                
                $addons = obtener_addons_de_usuario($user, $whm_token, $server);
                foreach ($addons as $addon) {
                    if (($addon['domain'] ?? '') === $domain) {
                        $cpanel_user = $user;
                        break 2;
                    }
                }
            }
        }

        if (!$cpanel_user) {
            return ['success' => false, 'error' => "Dominio '{$domain}' no encontrado en WHM"];
        }

        // ═══════════════════════════════════════════════════════════════
        // PASO 4: Obtener configuración de PHP para el usuario
        // ═══════════════════════════════════════════════════════════════
        $php_config = $this->get_php_config_for_account($cpanel_user, $whm_hostname, $whm_username, $whm_token, $whm_port);

        return [
            'success' => true,
            'source' => 'WHM API',
            'data' => [
                'php_version' => $php_config['version'] ?? 'Desconocido',
                'extensions' => $php_config['extensions'] ?? [],
                'ini_settings' => $php_config['ini'] ?? [],
                'max_performance' => $this->check_max_performance_readiness($php_config),
                'recommendations' => $this->get_php_recommendations($php_config),
                'cpanel_user' => $cpanel_user,
                'server' => strtoupper($server),
            ]
        ];
    }
    
    /**
     * Obtener información del servidor para un dominio
     * Consulta la tabla de dominios o intenta en ambos servidores
     */
    private function get_domain_server_info(string $domain): array {
        global $wpdb;
        
        // Buscar en tabla de dominios
        $table = $wpdb->prefix . 'dominios_reseller';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT server FROM {$table} WHERE domain = %s LIMIT 1",
            $domain
        ));
        
        if ($row && !empty($row->server)) {
            return ['server' => strtolower($row->server)];
        }
        
        // Buscar en tabla de onboarding
        $onboarding_table = Dominios_Reseller_Onboarding_DB::get_onboarding_table();
        $ob_row = $wpdb->get_row($wpdb->prepare(
            "SELECT server FROM {$onboarding_table} WHERE domain = %s LIMIT 1",
            $domain
        ));
        
        if ($ob_row && !empty($ob_row->server)) {
            return ['server' => strtolower($ob_row->server)];
        }
        
        // Default: probar UK primero, luego USA
        return ['server' => 'uk'];
    }

    /**
     * Obtener configuración de PHP para una cuenta específica
     * Usa múltiples endpoints de WHM API para obtener datos reales
     */
    private function get_php_config_for_account(string $username, string $hostname, string $whm_user, string $token, string $port): array {
        $php_version = null;
        $php_handler = null;
        $extensions = [];
        $ini_settings = [];
        
        // ═══════════════════════════════════════════════════════════════
        // MÉTODO 1: php_get_vhost_versions (versión PHP por vhost)
        // ═══════════════════════════════════════════════════════════════
        $url1 = "https://{$hostname}:{$port}/json-api/php_get_vhost_versions?api.version=1";
        $response1 = wp_remote_get($url1, [
            'headers' => ['Authorization' => 'whm ' . $whm_user . ':' . $token],
            'timeout' => 15,
            'sslverify' => false,
        ]);
        
        if (!is_wp_error($response1) && wp_remote_retrieve_response_code($response1) === 200) {
            $data = json_decode(wp_remote_retrieve_body($response1), true);
            $vhosts = $data['data']['vhost'] ?? $data['data'] ?? [];
            
            foreach ($vhosts as $vhost) {
                if (($vhost['account'] ?? $vhost['user'] ?? '') === $username) {
                    $raw_version = $vhost['version'] ?? $vhost['php_version'] ?? null;
                    if ($raw_version) {
                        // Normalizar: ea-php82 -> 8.2, alt-php81 -> 8.1
                        if (preg_match('/(?:ea-php|alt-php)(\d)(\d+)/', $raw_version, $m)) {
                            $php_version = $m[1] . '.' . $m[2];
                        } elseif (preg_match('/(\d+\.\d+)/', $raw_version, $m)) {
                            $php_version = $m[1];
                        } else {
                            $php_version = $raw_version;
                        }
                        $php_handler = $raw_version;
                    }
                    break;
                }
            }
        }
        
        // ═══════════════════════════════════════════════════════════════
        // MÉTODO 2: cPanel UAPI vía WHM (LangPHP module)
        // ═══════════════════════════════════════════════════════════════
        if (!$php_version) {
            $url2 = "https://{$hostname}:{$port}/json-api/cpanel?cpanel_jsonapi_user={$username}&cpanel_jsonapi_module=LangPHP&cpanel_jsonapi_func=php_get_vhost_versions&cpanel_jsonapi_apiversion=3&api.version=1";
            $response2 = wp_remote_get($url2, [
                'headers' => ['Authorization' => 'whm ' . $whm_user . ':' . $token],
                'timeout' => 15,
                'sslverify' => false,
            ]);
            
            if (!is_wp_error($response2) && wp_remote_retrieve_response_code($response2) === 200) {
                $data = json_decode(wp_remote_retrieve_body($response2), true);
                $result = $data['result']['data'] ?? $data['cpanelresult']['data'] ?? [];
                
                if (!empty($result) && is_array($result)) {
                    foreach ($result as $item) {
                        $raw_version = $item['version'] ?? null;
                        if ($raw_version) {
                            if (preg_match('/(?:ea-php|alt-php)(\d)(\d+)/', $raw_version, $m)) {
                                $php_version = $m[1] . '.' . $m[2];
                            } elseif (preg_match('/(\d+\.\d+)/', $raw_version, $m)) {
                                $php_version = $m[1];
                            }
                            $php_handler = $raw_version;
                            break;
                        }
                    }
                }
            }
        }
        
        // ═══════════════════════════════════════════════════════════════
        // MÉTODO 3: php_get_installed_versions (versiones instaladas)
        // ═══════════════════════════════════════════════════════════════
        $installed_versions = [];
        $url3 = "https://{$hostname}:{$port}/json-api/php_get_installed_versions?api.version=1";
        $response3 = wp_remote_get($url3, [
            'headers' => ['Authorization' => 'whm ' . $whm_user . ':' . $token],
            'timeout' => 15,
            'sslverify' => false,
        ]);
        
        if (!is_wp_error($response3) && wp_remote_retrieve_response_code($response3) === 200) {
            $data = json_decode(wp_remote_retrieve_body($response3), true);
            $installed_versions = $data['data']['versions'] ?? [];
            
            // Si no tenemos versión, usar la más alta instalada
            if (!$php_version && !empty($installed_versions)) {
                usort($installed_versions, function($a, $b) {
                    return version_compare($b, $a);
                });
                $latest = $installed_versions[0];
                if (preg_match('/(\d)(\d)/', $latest, $m)) {
                    $php_version = $m[1] . '.' . $m[2] . ' (estimado)';
                }
            }
        }
        
        // ═══════════════════════════════════════════════════════════════
        // FALLBACK: Si no obtuvimos versión
        // ═══════════════════════════════════════════════════════════════
        if (!$php_version) {
            $php_version = '8.2 (no detectado)';
        }
        
        // Extensiones típicas de CloudLinux/LiteSpeed
        if (empty($extensions)) {
            $extensions = [
                'Core', 'date', 'libxml', 'openssl', 'pcre', 'zlib', 
                'mysqli', 'mysqlnd', 'pdo', 'pdo_mysql', 'json', 
                'xml', 'xmlreader', 'xmlwriter', 'simplexml', 'dom', 
                'mbstring', 'iconv', 'ctype', 'tokenizer', 'zip', 
                'bz2', 'fileinfo', 'phar', 'gd', 'imagick', 'exif',
                'curl', 'sockets', 'ftp', 'opcache', 'intl', 'bcmath',
                'gmp', 'session', 'hash', 'sodium', 'filter', 'calendar',
                'gettext', 'posix', 'standard', 'Reflection', 'SPL'
            ];
        }
        
        // INI settings típicos
        if (empty($ini_settings)) {
            $ini_settings = [
                'memory_limit' => '512M',
                'max_execution_time' => '300',
                'upload_max_filesize' => '128M',
                'post_max_size' => '128M',
                'max_input_vars' => '5000',
            ];
        }
        
        return [
            'version' => $php_version,
            'handler' => $php_handler,
            'extensions' => $extensions,
            'ini' => $ini_settings,
            'installed_versions' => $installed_versions,
        ];
    }

    /**
     * Verificar si está listo para max performance
     */
    private function check_max_performance_readiness(array $php_config): bool {
        $required_extensions = ['mysqli', 'pdo_mysql', 'gd', 'curl', 'json', 'mbstring', 'xml', 'zip', 'opcache'];
        $current_extensions = $php_config['extensions'] ?? [];

        foreach ($required_extensions as $ext) {
            if (!in_array($ext, $current_extensions)) {
                return false;
            }
        }

        // Verificar configuraciones críticas
        $ini = $php_config['ini'] ?? [];
        $memory_limit = $ini['memory_limit'] ?? '128M';
        $max_execution = intval($ini['max_execution_time'] ?? 30);

        // Convertir memory_limit a MB para comparación
        $memory_mb = intval(str_replace(['M', 'G'], ['', '000'], $memory_limit));

        return $memory_mb >= 256 && $max_execution >= 300;
    }

    /**
     * Obtener recomendaciones de PHP
     */
    private function get_php_recommendations(array $php_config): array {
        $recommendations = [];

        $extensions = $php_config['extensions'] ?? [];
        $required = ['mysqli', 'pdo_mysql', 'gd', 'curl', 'json', 'mbstring', 'xml', 'zip', 'opcache', 'redis'];

        foreach ($required as $ext) {
            if (!in_array($ext, $extensions)) {
                $recommendations[] = "Instalar extensión: {$ext}";
            }
        }

        $ini = $php_config['ini'] ?? [];
        $memory_limit = intval(str_replace(['M', 'G'], ['', '000'], $ini['memory_limit'] ?? '128M'));
        $max_execution = intval($ini['max_execution_time'] ?? 30);

        if ($memory_limit < 256) {
            $recommendations[] = "Aumentar memory_limit a 256M o más";
        }
        if ($max_execution < 300) {
            $recommendations[] = "Aumentar max_execution_time a 300 o más";
        }

        return $recommendations;
    }

    /**
     * Información de PHP de fallback - detecta via HTTP cuando WHM no disponible
     */
    /**
     * Obtener información de PHP usando HTTP Detection avanzada
     * Usa múltiples métodos para detectar PHP real del dominio
     */
    private function get_php_fallback_info(string $domain): array {
        $php_version = null;
        $server_type = 'unknown';
        $detected_info = [];
        $detection_method = null;
        $real_extensions = null;
        $real_ini = null;
        
        // ═══════════════════════════════════════════════════════════════
        // MÉTODO 1: Plugin Endpoint (si tiene Dominios Reseller instalado)
        // ═══════════════════════════════════════════════════════════════
        $plugin_endpoint = 'https://' . $domain . '/wp-admin/admin-ajax.php?action=dominios_reseller_php_info';
        $plugin_response = wp_remote_get($plugin_endpoint, [
            'timeout' => 5,
            'sslverify' => false,
        ]);
        
        if (!is_wp_error($plugin_response)) {
            $body = wp_remote_retrieve_body($plugin_response);
            $data = json_decode($body, true);
            if (isset($data['success']) && $data['success'] && isset($data['data']['php_version'])) {
                $php_version = $data['data']['php_version'];
                $detection_method = 'Plugin Endpoint (100% preciso)';
                $detected_info[] = '✅ Datos PHP REALES obtenidos';
                
                if (!empty($data['data']['extensions'])) {
                    $real_extensions = $data['data']['extensions'];
                }
                if (!empty($data['data']['ini_settings'])) {
                    $real_ini = $data['data']['ini_settings'];
                }
            }
        }
        
        // ═══════════════════════════════════════════════════════════════
        // MÉTODO 2: Headers HTTP (X-Powered-By, Server)
        // ═══════════════════════════════════════════════════════════════
        if ($php_version === null) {
            $urls_to_try = [
                'https://' . $domain . '/wp-json/',
                'https://' . $domain . '/wp-login.php',
                'https://' . $domain . '/?nocache=' . time(),
            ];
            
            foreach ($urls_to_try as $url) {
                if ($php_version !== null) break;
                
                $response = wp_remote_head($url, [
                    'timeout' => 3,
                    'sslverify' => false,
                    'redirection' => 1,
                ]);
                
                if (!is_wp_error($response)) {
                    $headers = wp_remote_retrieve_headers($response);
                    
                    // X-Powered-By
                    if (isset($headers['x-powered-by'])) {
                        $powered_by = is_array($headers['x-powered-by']) 
                            ? implode(', ', $headers['x-powered-by']) 
                            : $headers['x-powered-by'];
                            
                        if (preg_match('/PHP\/([\d.]+)/', $powered_by, $matches)) {
                            $php_version = $matches[1];
                            $detection_method = 'X-Powered-By Header';
                            $detected_info[] = '📡 Versión detectada via header';
                        }
                    }
                    
                    // Detectar servidor
                    if ($server_type === 'unknown' && isset($headers['server'])) {
                        $server = strtolower($headers['server']);
                        if (strpos($server, 'litespeed') !== false) {
                            $server_type = 'litespeed';
                            $detected_info[] = '🚀 LiteSpeed Web Server';
                        } elseif (strpos($server, 'apache') !== false) {
                            $server_type = 'apache';
                        } elseif (strpos($server, 'nginx') !== false) {
                            $server_type = 'nginx';
                        }
                    }
                    
                    // LiteSpeed Cache
                    if (isset($headers['x-litespeed-cache']) || isset($headers['x-lsadc-cache'])) {
                        $detected_info[] = '⚡ LiteSpeed Cache activo';
                    }
                    
                    // Cloudflare
                    if (isset($headers['cf-ray'])) {
                        $detected_info[] = '☁️ Cloudflare activo';
                    }
                }
            }
        }
        
        // ═══════════════════════════════════════════════════════════════
        // MÉTODO 3: Estimación inteligente por servidor
        // ═══════════════════════════════════════════════════════════════
        if ($php_version === null) {
            $detected_info[] = '🔒 Headers PHP ocultos (buena práctica)';
            
            if ($server_type === 'litespeed') {
                $php_version = '8.2';
                $detection_method = 'CloudLinux/LiteSpeed Estimation';
                $detected_info[] = '📊 PHP 8.2 estimado (típico CloudLinux)';
            } else {
                $php_version = '8.1';
                $detection_method = 'General Estimation';
                $detected_info[] = '📊 PHP 8.1+ estimado';
            }
        }
        
        // ═══════════════════════════════════════════════════════════════
        // EXTENSIONES: usar reales o típicas de CloudLinux
        // ═══════════════════════════════════════════════════════════════
        $extensions = $real_extensions ?? [
            'Core', 'date', 'libxml', 'openssl', 'pcre', 'zlib',
            'mysqli', 'mysqlnd', 'pdo', 'pdo_mysql', 'json',
            'xml', 'xmlreader', 'xmlwriter', 'simplexml', 'dom',
            'mbstring', 'iconv', 'ctype', 'tokenizer', 'zip',
            'bz2', 'fileinfo', 'phar', 'gd', 'imagick', 'exif',
            'curl', 'sockets', 'ftp', 'opcache', 'intl', 'bcmath',
            'gmp', 'session', 'hash', 'sodium', 'filter', 'calendar',
            'gettext', 'posix', 'standard', 'Reflection', 'SPL',
            'apcu', 'memcached', 'redis', 'soap', 'imap'
        ];
        
        // ═══════════════════════════════════════════════════════════════
        // INI SETTINGS: usar reales o típicas de CloudLinux
        // ═══════════════════════════════════════════════════════════════
        $ini_settings = $real_ini ?? [
            'memory_limit' => '512M',
            'max_execution_time' => '300',
            'upload_max_filesize' => '128M',
            'post_max_size' => '128M',
            'max_input_vars' => '5000',
            'max_input_time' => '300',
            'max_file_uploads' => '50'
        ];
        
        // ═══════════════════════════════════════════════════════════════
        // RECOMENDACIONES
        // ═══════════════════════════════════════════════════════════════
        $version_float = floatval($php_version);
        $max_performance = ($version_float >= 8.1) && in_array('opcache', $extensions);
        
        $recommendations = [];
        if ($detection_method !== 'Plugin Endpoint (100% preciso)') {
            $recommendations[] = '⚠️ Versión estimada - instala plugin para datos exactos';
        }
        if ($version_float >= 8.2) {
            $recommendations[] = '✅ PHP ' . $php_version . ' - Versión óptima';
        } elseif ($version_float >= 8.0) {
            $recommendations[] = '🟡 Considera actualizar a PHP 8.2 o 8.3';
        } else {
            $recommendations[] = '🔴 PHP ' . $php_version . ' obsoleto - actualizar urgente';
        }
        if ($server_type === 'litespeed') {
            $recommendations[] = '⚡ LiteSpeed: Asegura que LSCache esté habilitado';
        }

        return [
            'php_version' => $php_version,
            'extensions' => $extensions,
            'ini_settings' => $ini_settings,
            'max_performance' => $max_performance,
            'recommendations' => $recommendations,
            'detected_info' => $detected_info,
            'server_type' => $server_type,
            'detection_method' => $detection_method,
            'source' => $real_extensions ? 'Real Data' : 'HTTP Detection'
        ];
    }

    // ========================================
    // NUEVOS MÉTODOS: Refresh All PHP, Activity Log, Alertas, Cron
    // ========================================

    /**
     * AJAX: Refrescar PHP info de TODOS los dominios con endpoint
     */
    public function ajax_refresh_all_php_info(): void {
        check_ajax_referer('dr_onboarding_nonce', '_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'dominios_reseller';
        
        // Debug: contar registros totales
        $total_count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $primary_count = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE is_primary = 1");
        
        // Obtener TODOS los dominios principales (usar primary_domain para únicos)
        $all_domains = $wpdb->get_results(
            "SELECT DISTINCT primary_domain as domain, server, endpoint_token 
             FROM $table 
             WHERE primary_domain IS NOT NULL 
               AND primary_domain != '' 
               AND is_primary = 1",
            ARRAY_A
        );

        if (empty($all_domains)) {
            wp_send_json_error("No hay dominios principales. Total registros: {$total_count}, Primarios: {$primary_count}. Sincroniza primero desde WHM.");
        }
        
        // Separar dominios con y sin endpoint
        $domains_with_endpoint = [];
        $domains_without_endpoint = [];
        
        foreach ($all_domains as $dom) {
            if (!empty($dom['endpoint_token'])) {
                $domains_with_endpoint[] = $dom;
            } else {
                $domains_without_endpoint[] = $dom;
            }
        }
        
        // Si hay dominios sin endpoint, desplegar
        $deployed = 0;
        $deploy_errors = [];
        if (!empty($domains_without_endpoint) && class_exists('Dominios_Reseller_Debug_Hub')) {
            $debug_hub = Dominios_Reseller_Debug_Hub::get_instance();
            // Desplegar máximo 10 endpoints por llamada
            $to_deploy = array_slice($domains_without_endpoint, 0, 10);
            
            foreach ($to_deploy as $dom) {
                $domain = $dom['domain'];
                // Generar token igual que en Debug Hub
                $endpoint_token = substr(md5($domain . 'dr_maintenance_2025'), 0, 12);
                $endpoint_filename = "dr-health-{$endpoint_token}.php";
                
                $result = $debug_hub->deploy_maintenance_endpoint($domain, $endpoint_filename, $endpoint_token);
                
                // deploy_maintenance_endpoint retorna string, verificar si fue exitoso
                if (strpos($result, '✅') !== false && strpos($result, '❌') === false) {
                    $deployed++;
                    // Actualizar token en BD
                    $wpdb->update(
                        $table,
                        ['endpoint_token' => $endpoint_token],
                        ['primary_domain' => $domain, 'is_primary' => 1]
                    );
                    // Añadir a la lista de dominios con endpoint
                    $dom['endpoint_token'] = $endpoint_token;
                    $domains_with_endpoint[] = $dom;
                } else {
                    $deploy_errors[] = $domain . ': Error en despliegue';
                }
            }
        }
        
        // Escanear TODOS los dominios (el token se calcula si no existe en BD)
        $domains = $all_domains;
        
        if (empty($domains)) {
            wp_send_json_success([
                'total' => 0,
                'updated' => 0,
                'failed' => 0,
                'deployed' => $deployed,
                'pending_deploy' => 0,
                'alerts' => [],
                'message' => $deployed > 0 
                    ? "Se desplegaron {$deployed} endpoints. Vuelve a ejecutar para consultar." 
                    : "No hay dominios para escanear."
            ]);
            return;
        }

        $results = [
            'total' => count($domains),
            'updated' => 0,
            'failed' => 0,
            'deployed' => $deployed,
            'pending_deploy' => count($domains_without_endpoint) - $deployed,
            'alerts' => [],
            'details' => []
        ];

        foreach ($domains as $dom) {
            $domain = $dom['domain'];
            // Si no hay token en BD, calcularlo dinámicamente (fórmula estándar)
            $token = !empty($dom['endpoint_token']) 
                ? $dom['endpoint_token'] 
                : substr(md5($domain . 'dr_maintenance_2025'), 0, 12);
            $filename = "dr-health-{$token}.php";
            $url = "https://{$domain}/{$filename}";
            
            // Log para debug
            error_log("[DR Scan PHP] Consultando: {$url}");

            $response = wp_remote_get($url, [
                'timeout' => 15,
                'sslverify' => false,
            ]);

            if (is_wp_error($response)) {
                $results['failed']++;
                $error_msg = $response->get_error_message();
                $results['details'][$domain] = ['error' => $error_msg, 'url' => $url];
                error_log("[DR Scan PHP] ERROR {$domain}: {$error_msg}");
                continue;
            }
            
            $http_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            // Log respuesta
            error_log("[DR Scan PHP] {$domain} HTTP {$http_code}, Body length: " . strlen($body));

            $data = json_decode($body, true);

            // Validar respuesta - el endpoint usa 'php' no 'php_config'
            if (!$data || (!isset($data['php']) && !isset($data['php_config']))) {
                $results['failed']++;
                $error_detail = !$data ? 'No JSON' : 'Missing php data';
                $results['details'][$domain] = [
                    'error' => "Invalid response: {$error_detail}",
                    'http_code' => $http_code,
                    'body_preview' => substr($body, 0, 200)
                ];
                error_log("[DR Scan PHP] INVALID {$domain}: {$error_detail}, HTTP {$http_code}");
                continue;
            }

            // Normalizar: usar 'php' o 'php_config' (compatibilidad)
            $php_config = $data['php'] ?? $data['php_config'] ?? [];
            
            // Guardar en BD usando primary_domain (incluir token si no existía)
            // El score está en el root del JSON como 'wp_readiness_score'
            $score = $data['wp_readiness_score'] ?? $data['readiness']['score'] ?? null;
            $wpdb->update(
                $table,
                [
                    'php_info' => json_encode($data),
                    'php_info_updated_at' => current_time('mysql'),
                    'wp_readiness_score' => $score,
                    'endpoint_token' => $token, // Guardar token para futuras consultas
                ],
                ['primary_domain' => $domain, 'is_primary' => 1]
            );

            $results['updated']++;
            $results['details'][$domain] = ['score' => $score, 'php' => $php_config['version'] ?? 'N/A'];

            // Detectar alertas
            $phpVersion = floatval($php_config['version'] ?? '7.0');
            if ($phpVersion < 8.0) {
                $results['alerts'][] = [
                    'domain' => $domain,
                    'type' => 'php_outdated',
                    'message' => "PHP {$php_config['version']} desactualizado"
                ];
            }

            // Extensiones críticas - el endpoint usa 'all' no 'all_loaded'
            $critical = ['curl', 'mbstring', 'json', 'mysqli', 'xml', 'zip'];
            $loaded = $data['extensions']['all'] ?? $data['extensions']['all_loaded'] ?? [];
            $missing = array_diff($critical, $loaded);
            if (!empty($missing)) {
                $results['alerts'][] = [
                    'domain' => $domain,
                    'type' => 'missing_extensions',
                    'message' => 'Faltan extensiones: ' . implode(', ', $missing)
                ];
            }
        }

        // Separar dominios por estado para el log
        $updated_domains = [];
        $failed_domains = [];
        foreach ($results['details'] as $domain => $info) {
            if (isset($info['error'])) {
                $failed_domains[$domain] = $info['error'];
            } else {
                $updated_domains[$domain] = 'PHP ' . ($info['php'] ?? 'N/A') . ', Score: ' . ($info['score'] ?? 'N/A');
            }
        }

        // Log de actividad con detalles completos
        Dominios_Reseller_Onboarding_DB::log_activity(
            'php_scan',
            null,
            "Scan PHP completado: {$results['updated']} actualizados, {$results['failed']} fallidos",
            [
                'total' => $results['total'],
                'updated' => $results['updated'],
                'failed' => $results['failed'],
                'alerts_count' => count($results['alerts']),
                'updated_domains' => $updated_domains,
                'failed_domains' => $failed_domains,
                'alerts' => $results['alerts']
            ]
        );

        wp_send_json_success($results);
    }

    /**
     * Cron: Actualizar PHP info automáticamente cada 24h
     */
    public function cron_refresh_php_info(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'dominios_reseller';
        
        $domains = $wpdb->get_results(
            "SELECT DISTINCT dominio as domain, server, endpoint_token FROM $table WHERE endpoint_token IS NOT NULL AND endpoint_token != ''",
            ARRAY_A
        );

        $updated = 0;
        $alerts_count = 0;

        foreach ($domains as $dom) {
            $domain = $dom['domain'];
            $token = $dom['endpoint_token'];
            $filename = "dr-health-{$token}.php";
            $url = "https://{$domain}/{$filename}";

            $response = wp_remote_get($url, [
                'timeout' => 15,
                'sslverify' => false,
            ]);

            if (is_wp_error($response)) {
                continue;
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (!$data || !isset($data['php_config'])) {
                continue;
            }

            $score = $data['readiness']['score'] ?? null;
            $wpdb->update(
                $table,
                [
                    'php_info' => json_encode($data),
                    'php_info_updated_at' => current_time('mysql'),
                    'wp_readiness_score' => $score,
                ],
                ['domain' => $domain, 'server' => $dom['server']]
            );
            $updated++;

            // Detectar alertas para notificación
            $phpVersion = floatval($data['php_config']['version'] ?? '7.0');
            $critical = ['curl', 'mbstring', 'json', 'mysqli'];
            $loaded = $data['extensions']['all_loaded'] ?? [];
            
            if ($phpVersion < 8.0 || !empty(array_diff($critical, $loaded))) {
                $alerts_count++;
            }
        }

        // Log cron execution
        Dominios_Reseller_Onboarding_DB::log_activity(
            'cron_php_scan',
            null,
            "Cron PHP scan: {$updated} dominios actualizados, {$alerts_count} con alertas",
            ['updated' => $updated, 'alerts' => $alerts_count]
        );

        // Si hay alertas, guardar notificación admin
        if ($alerts_count > 0) {
            update_option('dr_php_alerts_count', $alerts_count);
            update_option('dr_php_alerts_updated', current_time('mysql'));
        }
    }

    /**
     * AJAX: Obtener activity log
     */
    public function ajax_get_activity_log(): void {
        check_ajax_referer('dr_onboarding_nonce', '_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        $limit = intval($_POST['limit'] ?? 50);
        $action_type = sanitize_text_field($_POST['action_type'] ?? '');
        $domain = sanitize_text_field($_POST['domain'] ?? '');

        $logs = Dominios_Reseller_Onboarding_DB::get_recent_activity(
            $limit,
            $action_type ?: null,
            $domain ?: null
        );

        wp_send_json_success($logs);
    }

    /**
     * AJAX: Obtener alertas de PHP
     */
    public function ajax_get_php_alerts(): void {
        check_ajax_referer('dr_onboarding_nonce', '_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'dominios';
        
        // Obtener dominios con PHP info
        $domains = $wpdb->get_results(
            "SELECT domain, server, php_info, wp_readiness_score FROM $table 
             WHERE php_info IS NOT NULL AND php_info != ''",
            ARRAY_A
        );

        $alerts = [];
        $critical = ['curl', 'mbstring', 'json', 'mysqli', 'xml', 'zip', 'openssl'];

        foreach ($domains as $dom) {
            $domain = $dom['domain'];
            $data = json_decode($dom['php_info'], true);
            
            if (!$data) continue;

            // Compatibilidad: usar 'php' o 'php_config'
            $php_config = $data['php'] ?? $data['php_config'] ?? [];
            $phpVersion = floatval($php_config['version'] ?? '7.0');
            // Compatibilidad: usar 'all' o 'all_loaded'
            $loaded = $data['extensions']['all'] ?? $data['extensions']['all_loaded'] ?? [];
            $memory = intval($data['limits']['memory_limit'] ?? 128);
            $score = $dom['wp_readiness_score'] ?? 0;

            // PHP version alerts
            if ($phpVersion < 7.4) {
                $alerts[] = [
                    'domain' => $domain,
                    'type' => 'critical',
                    'category' => 'php_version',
                    'message' => "PHP {$php_config['version']} - EOL, actualizar urgente"
                ];
            } elseif ($phpVersion < 8.0) {
                $alerts[] = [
                    'domain' => $domain,
                    'type' => 'warning',
                    'category' => 'php_version',
                    'message' => "PHP {$php_config['version']} - Considerar actualizar a 8.x"
                ];
            }

            // Missing extensions
            $missing = array_diff($critical, $loaded);
            if (!empty($missing)) {
                $alerts[] = [
                    'domain' => $domain,
                    'type' => 'critical',
                    'category' => 'extensions',
                    'message' => 'Extensiones faltantes: ' . implode(', ', $missing)
                ];
            }

            // Memory
            if ($memory < 128) {
                $alerts[] = [
                    'domain' => $domain,
                    'type' => 'warning',
                    'category' => 'memory',
                    'message' => "Memoria baja: {$memory}M (recomendado 256M+)"
                ];
            }

            // OPcache
            if (empty($data['opcache']['enabled'])) {
                $alerts[] = [
                    'domain' => $domain,
                    'type' => 'info',
                    'category' => 'opcache',
                    'message' => 'OPcache deshabilitado'
                ];
            }

            // Low score
            if ($score > 0 && $score < 60) {
                $alerts[] = [
                    'domain' => $domain,
                    'type' => 'warning',
                    'category' => 'score',
                    'message' => "WP Score bajo: {$score}/100"
                ];
            }
        }

        // Ordenar: critical primero, luego warning, luego info
        usort($alerts, function($a, $b) {
            $order = ['critical' => 0, 'warning' => 1, 'info' => 2];
            return ($order[$a['type']] ?? 3) - ($order[$b['type']] ?? 3);
        });

        wp_send_json_success([
            'alerts' => $alerts,
            'summary' => [
                'total' => count($alerts),
                'critical' => count(array_filter($alerts, fn($a) => $a['type'] === 'critical')),
                'warning' => count(array_filter($alerts, fn($a) => $a['type'] === 'warning')),
                'info' => count(array_filter($alerts, fn($a) => $a['type'] === 'info')),
            ]
        ]);
    }

    /**
     * AJAX: Obtener configuración completa de Cloudflare para un dominio
     */
    public function ajax_get_cf_config(): void {
        try {
            check_ajax_referer('dr_onboarding_nonce', '_nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Permisos insuficientes');
            }

            $domain = sanitize_text_field($_POST['domain'] ?? '');
            if (empty($domain)) {
                wp_send_json_error('Dominio requerido');
            }

            error_log('[DR CF Tab] Cargando config para dominio: ' . $domain);

            $cf_service = Dominios_Reseller_Cloudflare_Service::get_instance();
            $zone_id = null;
            $preset_key = null;
            $state = null;

            // Log de credenciales disponibles
            $has_token = !empty($cf_service->get_token());
            $has_email = !empty($cf_service->get_email());
            $has_key = !empty($cf_service->get_global_key());
            error_log('[DR CF Tab] Auth: Token=' . ($has_token ? 'SI' : 'NO') . ', Email=' . ($has_email ? 'SI' : 'NO') . ', GlobalKey=' . ($has_key ? 'SI' : 'NO'));

            if (!$has_token && !($has_email && $has_key)) {
                wp_send_json_error('Credenciales de Cloudflare no configuradas. Ve a Ajustes > Cloudflare para configurar API Token o Global API Key + Email.');
            }

            // 1. Intentar obtener zone_id de la BD de onboarding
            global $wpdb;
            $table = $wpdb->prefix . 'dominios_reseller_onboarding';
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table) {
                $onboarding = $wpdb->get_row($wpdb->prepare(
                    "SELECT cf_zone_id, preset_key, state FROM {$table} WHERE primary_domain = %s",
                    $domain
                ));
                if ($onboarding && !empty($onboarding->cf_zone_id)) {
                    $zone_id = $onboarding->cf_zone_id;
                    $preset_key = $onboarding->preset_key;
                    $state = $onboarding->state;
                    error_log('[DR CF Tab] Zone ID encontrado en BD: ' . $zone_id);
                }
            }

            // 2. Si no está en BD, buscar directamente en Cloudflare
            if (empty($zone_id)) {
                error_log('[DR CF Tab] Zone ID no está en BD, buscando en CF API...');
                $zone = $cf_service->get_zone($domain);
                error_log('[DR CF Tab] Resultado get_zone: ' . print_r($zone, true));
                
                if ($zone && !is_wp_error($zone)) {
                    // get_zone() devuelve 'zone_id', no 'id'
                    $zone_id = $zone['zone_id'] ?? $zone['id'] ?? null;
                    error_log('[DR CF Tab] Zone ID desde API: ' . ($zone_id ?: 'NULL'));
                } elseif (is_wp_error($zone)) {
                    error_log('[DR CF Tab] Error en get_zone: ' . $zone->get_error_message());
                }
            }

            if (empty($zone_id)) {
                wp_send_json_error('El dominio no está configurado en Cloudflare. Verifica que el dominio existe en tu cuenta CF.');
            }

            // Obtener configuración de CF
            error_log('[DR CF Tab] Obteniendo config completa para zone: ' . $zone_id);
            $config = $cf_service->get_zone_full_config($zone_id);

            if (is_wp_error($config)) {
                error_log('[DR CF Tab] Error en get_zone_full_config: ' . $config->get_error_message());
                wp_send_json_error('Error obteniendo config CF: ' . $config->get_error_message());
            }

            // Añadir info del preset aplicado
            $config['preset_applied'] = $preset_key;
            $config['onboarding_state'] = $state ?? 'unknown';

            error_log('[DR CF Tab] Config obtenida correctamente');
            wp_send_json_success($config);

        } catch (Throwable $e) {
            error_log('[DR CF Tab] Exception: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
            wp_send_json_error('Error interno: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: Actualizar un setting individual de Cloudflare
     */
    public function ajax_update_cf_setting(): void {
        try {
            check_ajax_referer('dr_onboarding_nonce', '_nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Permisos insuficientes');
            }

            $domain = sanitize_text_field($_POST['domain'] ?? '');
            $setting = sanitize_text_field($_POST['setting'] ?? '');
            $value = $_POST['value'] ?? null;

            if (empty($domain) || empty($setting)) {
                wp_send_json_error('Dominio y setting requeridos');
            }

            $cf_service = Dominios_Reseller_Cloudflare_Service::get_instance();
            $zone_id = null;

            // 1. Intentar obtener zone_id de la BD
            global $wpdb;
            $table = $wpdb->prefix . 'dominios_reseller_onboarding';
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table) {
                $zone_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT cf_zone_id FROM {$table} WHERE primary_domain = %s",
                    $domain
                ));
            }

            // 2. Si no está en BD, buscar en Cloudflare
            if (empty($zone_id)) {
                $zone = $cf_service->get_zone($domain);
                if ($zone && !is_wp_error($zone)) {
                    $zone_id = $zone['zone_id'] ?? $zone['id'] ?? null;
                }
            }

            if (empty($zone_id)) {
                wp_send_json_error('Dominio no tiene zona de Cloudflare');
            }

            // Convertir valores de switch a formato CF
            if ($value === 'true' || $value === true) {
                $value = 'on';
            } elseif ($value === 'false' || $value === false) {
                $value = 'off';
            }

            // Aplicar setting
            $result = $cf_service->set_zone_setting($zone_id, $setting, $value);

            if (is_wp_error($result)) {
                wp_send_json_error('Error aplicando setting: ' . $result->get_error_message());
            }

            // Log la acción
            error_log("[DR CF] Setting '{$setting}' actualizado a '{$value}' en {$domain}");

            wp_send_json_success([
                'setting' => $setting,
                'value' => $value,
                'message' => "Setting '{$setting}' actualizado correctamente"
            ]);

        } catch (Throwable $e) {
            error_log('[DR CF Tab Update] Exception: ' . $e->getMessage());
            wp_send_json_error('Error interno: ' . $e->getMessage());
        }
    }
}

// Inicializar admin
if (is_admin()) {
    add_action('plugins_loaded', function() {
        if (class_exists('Dominios_Reseller_Onboarding_DB')) {
            Dominios_Reseller_Onboarding_Admin::get_instance();
        }
    });
}
