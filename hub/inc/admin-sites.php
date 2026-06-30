<?php
/**
 * Admin Sites Page
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPHUB_Admin_Sites {
    
    private $site_manager;
    private $bulk_actions;
    
    public function __construct() {
        $this->site_manager = new RPHUB_Site_Manager();
        $this->bulk_actions = new RPHUB_Bulk_Actions();
    }
    
    public function render() {
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($current_page - 1) * $per_page;
        
        // Get filter parameters
        $status_filter = sanitize_text_field($_GET['status'] ?? '');
        $plan_filter = sanitize_text_field($_GET['plan'] ?? '');
        $search = sanitize_text_field($_GET['s'] ?? '');
        
        $args = [
            'limit' => $per_page,
            'offset' => $offset
        ];
        
        if ($status_filter) {
            $args['status'] = $status_filter;
        }
        
        if ($plan_filter) {
            $args['plan'] = $plan_filter;
        }
        
        $sites = $this->site_manager->get_sites($args);
        $total_sites = $this->site_manager->get_sites_count($args);
        $total_pages = ceil($total_sites / $per_page);
        
        ?>
        <div class="wrap rphub-sites">
            <h1 class="wp-heading-inline">Sitios Web</h1>
            <button class="page-title-action" onclick="rphubOpenAddSiteModal()">Añadir Sitio</button>

            <?php $this->render_care_deploy_panel(); ?>

            <!-- Filters -->
            <div class="rphub-filters" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:12px 0;">
                <form method="get" action="" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <input type="hidden" name="page" value="replanta-hub-sites">

                    <select name="status" onchange="this.form.submit()">
                        <option value="">Todos los estados</option>
                        <option value="active" <?php selected($status_filter, 'active'); ?>>Activos</option>
                        <option value="error" <?php selected($status_filter, 'error'); ?>>Con errores</option>
                        <option value="inactive" <?php selected($status_filter, 'inactive'); ?>>Inactivos</option>
                    </select>

                    <select name="plan" onchange="this.form.submit()">
                        <option value="">Todos los planes</option>
                        <option value="semilla" <?php selected($plan_filter, 'semilla'); ?>>Semilla</option>
                        <option value="raiz" <?php selected($plan_filter, 'raiz'); ?>>Raíz</option>
                        <option value="ecosistema" <?php selected($plan_filter, 'ecosistema'); ?>>Ecosistema</option>
                    </select>

                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Buscar sitios...">
                    <button type="submit" class="button">Filtrar</button>
                </form>
                <div style="margin-left:auto;display:flex;gap:6px;align-items:center;">
                    <span style="font-size:13px;color:#646970;">Vista:</span>
                    <button type="button" id="rphub-view-table" class="button button-primary" onclick="rphubSetView('table')" title="Vista tabla">
                        <span class="dashicons dashicons-list-view" style="margin-top:3px;"></span>
                    </button>
                    <button type="button" id="rphub-view-cards" class="button" onclick="rphubSetView('cards')" title="Vista tarjetas">
                        <span class="dashicons dashicons-grid-view" style="margin-top:3px;"></span>
                    </button>
                </div>
            </div>
            
            <!-- Bulk Actions -->
            <div class="rphub-bulk-actions">
                <form id="rphub-bulk-form">
                    <select id="rphub-bulk-action">
                        <option value="">Acciones en lote</option>
                        <option value="sync_data">Sincronizar datos</option>
                        <option value="test_connection">Probar conexión</option>
                        <option value="update_plugins">Actualizar plugins</option>
                        <option value="update_themes">Actualizar temas</option>
                        <option value="update_core">Actualizar WordPress</option>
                        <option value="backup">Crear backup</option>
                        <option value="security_scan">Escaneo de seguridad</option>
                        <option value="cache_clear">Limpiar caché</option>
                    </select>
                    <button type="button" class="button" onclick="rphubExecuteBulkAction()">Aplicar</button>
                </form>
            </div>
            
            <!-- Sites Table -->
            <div class="rphub-sites-table-container">
                <table class="wp-list-table widefat fixed striped rphub-sites-table">
                    <thead>
                        <tr>
                            <td class="check-column">
                                <input type="checkbox" id="cb-select-all">
                            </td>
                            <th>Sitio</th>
                            <th>Plan</th>
                            <th>Estado</th>
                            <th>Salud</th>
                            <th>Actualizaciones</th>
                            <th>Seguridad</th>
                            <th>Última Verificación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($sites)): ?>
                            <?php foreach ($sites as $site): ?>
                            <tr data-site-id="<?php echo esc_attr($site->id); ?>">
                                <th class="check-column">
                                    <input type="checkbox" name="site_ids[]" value="<?php echo esc_attr($site->id); ?>">
                                </th>
                                <td class="rphub-site-info">
                                    <div class="rphub-site-name">
                                        <strong><?php echo esc_html($site->name); ?></strong>
                                    </div>
                                    <div class="rphub-site-url">
                                        <a href="<?php echo esc_url($site->url); ?>" target="_blank" rel="noopener">
                                            <?php echo esc_html($site->url); ?>
                                        </a>
                                    </div>
                                    <?php if ($site->notes): ?>
                                    <div class="rphub-site-notes">
                                        <?php echo esc_html(wp_trim_words($site->notes, 10)); ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td class="rphub-plan">
                                    <span class="rphub-plan-badge rphub-plan-<?php echo esc_attr($site->plan); ?>">
                                        <?php 
                                        $plan_info = RPHUB_Utils::get_plan_features($site->plan);
                                        echo esc_html($plan_info['name']);
                                        ?>
                                    </span>
                                </td>
                                <td class="rphub-status">
                                    <span class="rphub-status-badge rphub-status-<?php echo esc_attr($site->status); ?>">
                                        <?php echo esc_html(ucfirst($site->status)); ?>
                                    </span>
                                </td>
                                <td class="rphub-health">
                                    <div class="rphub-health-score">
                                        <span class="rphub-health-number" style="color: <?php echo RPHUB_Utils::get_health_score_color($site->health_score); ?>">
                                            <?php echo esc_html($site->health_score); ?>%
                                        </span>
                                        <div class="rphub-health-bar">
                                            <div class="rphub-health-fill" style="width: <?php echo esc_attr($site->health_score); ?>%; background-color: <?php echo RPHUB_Utils::get_health_score_color($site->health_score); ?>"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="rphub-updates">
                                    <?php if ($site->updates_available > 0): ?>
                                        <span class="rphub-updates-count warning">
                                            <?php echo esc_html($site->updates_available); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="rphub-updates-count success">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="rphub-security">
                                    <?php if ($site->security_issues > 0): ?>
                                        <span class="rphub-security-issues error">
                                            <?php echo esc_html($site->security_issues); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="rphub-security-issues success">
                                            <span class="dashicons dashicons-shield-alt"></span>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="rphub-last-check">
                                    <?php echo esc_html(RPHUB_Utils::time_ago($site->last_check)); ?>
                                </td>
                                <td class="rphub-actions">
                                    <div class="rphub-action-buttons">
                                        <button class="rphub-btn rphub-btn-small" onclick="rphubSyncSite(<?php echo esc_attr($site->id); ?>)" title="Sincronizar">
                                            <span class="dashicons dashicons-update"></span>
                                        </button>
                                        <button class="rphub-btn rphub-btn-small" onclick="rphubTestConnection(<?php echo esc_attr($site->id); ?>)" title="Probar conexión">
                                            <span class="dashicons dashicons-networking"></span>
                                        </button>
                                        <button class="rphub-btn rphub-btn-small" onclick="rphubOpenSiteModal(<?php echo esc_attr($site->id); ?>)" title="Editar">
                                            <span class="dashicons dashicons-edit"></span>
                                        </button>
                                        <button class="rphub-btn rphub-btn-small rphub-btn-danger" onclick="rphubRemoveSite(<?php echo esc_attr($site->id); ?>)" title="Eliminar">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="rphub-no-sites">
                                    <div class="rphub-empty-state">
                                        <span class="dashicons dashicons-admin-site-alt3"></span>
                                        <h3>No hay sitios registrados</h3>
                                        <p>Añade tu primer sitio para comenzar el monitoreo.</p>
                                        <button class="button button-primary" onclick="rphubOpenAddSiteModal()">Añadir Sitio</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Cards View (loaded via AJAX) -->
            <div id="rphub-cards-view" style="display:none;">
                <div id="rphub-cards-client-filter" style="margin-bottom:12px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <span style="font-size:13px;color:#646970;">Cliente:</span>
                    <button type="button" class="button rphub-client-btn active" data-client="" onclick="rphubFilterClient(this,'')">Todos</button>
                </div>
                <div id="rphub-cards-grid"></div>
                <div id="rphub-cards-loading" style="text-align:center;padding:40px;color:#646970;">Cargando sitios…</div>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="rphub-pagination">
                <?php
                $pagination_args = [
                    'base' => add_query_arg(['paged' => '%#%']),
                    'format' => '',
                    'total' => $total_pages,
                    'current' => $current_page,
                    'prev_text' => '‹ Anterior',
                    'next_text' => 'Siguiente ›'
                ];
                
                echo paginate_links($pagination_args);
                ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Add/Edit Site Modal -->
        <div id="rphub-site-modal" class="rphub-modal" style="display: none;">
            <div class="rphub-modal-content">
                <div class="rphub-modal-header">
                    <h2 id="rphub-modal-title">Añadir Sitio</h2>
                    <button class="rphub-modal-close" onclick="rphubCloseSiteModal()" aria-label="Cerrar">&times;</button>
                </div>
                <form id="rphub-site-form" class="rphub-modal-body">

                    <fieldset class="rphub-fieldset">
                        <legend>Información básica</legend>
                        <div class="rphub-form-grid">
                            <div class="rphub-form-group">
                                <label for="site-name">Nombre del Sitio *</label>
                                <input type="text" id="site-name" name="name" required>
                            </div>
                            <div class="rphub-form-group">
                                <label for="site-client-name">Cliente / Empresa</label>
                                <input type="text" id="site-client-name" name="client_name" placeholder="Nombre del cliente o empresa">
                            </div>
                            <div class="rphub-form-group">
                                <label for="site-url">URL del Sitio *</label>
                                <input type="url" id="site-url" name="url" required placeholder="https://ejemplo.com">
                            </div>
                            <div class="rphub-form-group">
                                <label for="site-plan">Plan</label>
                                <select id="site-plan" name="plan">
                                    <option value="semilla">Semilla (€49)</option>
                                    <option value="raiz">Raíz (€89)</option>
                                    <option value="ecosistema">Ecosistema (€149)</option>
                                </select>
                            </div>
                            <div class="rphub-form-group">
                                <label for="site-domain-type">Tipo de dominio</label>
                                <select id="site-domain-type" name="domain_type">
                                    <option value="external">Externo (cliente alojado fuera)</option>
                                    <option value="internal">Interno (alojado en mi WHM)</option>
                                </select>
                                <small>Define si gestionamos hosting WHM/cPanel o solo damos mantenimiento WP.</small>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="rphub-fieldset" id="rphub-fs-whm">
                        <legend>Hosting WHM / cPanel</legend>
                        <div class="rphub-form-grid">
                            <div class="rphub-form-group">
                                <label for="site-whm-server">Servidor WHM</label>
                                <select id="site-whm-server" name="whm_server">
                                    <option value="">— Selecciona servidor —</option>
                                </select>
                                <small>Servidores configurados en Ajustes → WHM.</small>
                            </div>
                            <div class="rphub-form-group">
                                <label for="site-whm-account">Cuenta cPanel</label>
                                <select id="site-whm-account-select" data-target="site-whm-account">
                                    <option value="">— Primero selecciona servidor —</option>
                                </select>
                                <input type="hidden" id="site-whm-account" name="whm_account">
                                <small id="site-whm-account-status" style="color:#646970;"></small>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="rphub-fieldset">
                        <legend>Avisos por email</legend>
                        <div class="rphub-form-grid">
                            <div class="rphub-form-group">
                                <label for="site-client-email">Email del cliente (informes mensuales)</label>
                                <input type="email" id="site-client-email" name="client_email" placeholder="cliente@ejemplo.com">
                            </div>
                            <div class="rphub-form-group">
                                <label for="site-alert-email">Email de alertas (caídas y errores)</label>
                                <input type="email" id="site-alert-email" name="alert_email" placeholder="ops@replanta.dev">
                            </div>
                        </div>
                        <div class="rphub-form-group">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin:0;">
                                <input type="checkbox" id="site-uptime-enabled" name="uptime_monitoring_enabled" value="1" style="width:auto;margin:0;">
                                <span>Activar monitoreo de disponibilidad (1 min)</span>
                            </label>
                        </div>
                    </fieldset>

                    <fieldset class="rphub-fieldset">
                        <legend>Integraciones (Analytics &amp; SEO)</legend>
                        <div class="rphub-form-grid">
                            <div class="rphub-form-group">
                                <label for="site-ga4-property-select">GA4 Property</label>
                                <select id="site-ga4-property-select" data-target="site-ga4-property">
                                    <option value="">— Carga propiedades… —</option>
                                </select>
                                <input type="hidden" id="site-ga4-property" name="ga4_property_id">
                                <small id="site-ga4-status" style="color:#646970;"></small>
                            </div>
                            <div class="rphub-form-group">
                                <label for="site-sc-domain-select">Propiedad Search Console</label>
                                <select id="site-sc-domain-select" data-target="site-sc-domain">
                                    <option value="">— Carga propiedades… —</option>
                                </select>
                                <input type="hidden" id="site-sc-domain" name="sc_domain">
                                <small id="site-sc-status" style="color:#646970;"></small>
                            </div>
                        </div>
                        <p class="rphub-help">Si el desplegable está vacío, conecta Google en <a href="<?php echo esc_url(admin_url('admin.php?page=replanta-hub-integrations')); ?>">Integraciones</a>.</p>
                    </fieldset>

                    <fieldset class="rphub-fieldset">
                        <legend>Notas</legend>
                        <div class="rphub-form-group">
                            <textarea id="site-notes" name="notes" rows="3" placeholder="Notas internas sobre este sitio…"></textarea>
                        </div>
                    </fieldset>

                    <fieldset class="rphub-fieldset">
                        <legend style="cursor:pointer;user-select:none;" onclick="var b=this.parentElement.querySelector('.rphub-ftp-body');b.style.display=b.style.display==='none'?'':'none'">
                            Acceso FTP de emergencia
                            <span style="font-weight:400;font-size:12px;color:#64748b"> — para actualizar Care cuando el site tiene error fatal</span>
                        </legend>
                        <div class="rphub-ftp-body" style="display:none;margin-top:12px;">
                            <div class="rphub-form-grid">
                                <div class="rphub-form-group">
                                    <label for="site-ftp-host">Servidor FTP / IP</label>
                                    <input type="text" id="site-ftp-host" name="ftp_host" placeholder="ftp.ejemplo.com">
                                </div>
                                <div class="rphub-form-group">
                                    <label for="site-ftp-port">Puerto</label>
                                    <input type="number" id="site-ftp-port" name="ftp_port" value="21" min="1" max="65535" style="max-width:90px;">
                                </div>
                            </div>
                            <div class="rphub-form-grid">
                                <div class="rphub-form-group">
                                    <label for="site-ftp-user">Usuario FTP</label>
                                    <input type="text" id="site-ftp-user" name="ftp_user" placeholder="usuario" autocomplete="off">
                                </div>
                                <div class="rphub-form-group">
                                    <label for="site-ftp-pass">Contrasena FTP</label>
                                    <input type="password" id="site-ftp-pass" name="ftp_pass" placeholder="(en blanco = no cambiar)" autocomplete="new-password">
                                    <small id="site-ftp-pass-hint" style="display:none;color:#3b82f6;">Contrasena guardada. Dejar en blanco para mantenerla.</small>
                                </div>
                            </div>
                            <div class="rphub-form-group">
                                <label for="site-ftp-path">Ruta absoluta a wp-content/plugins</label>
                                <input type="text" id="site-ftp-path" name="ftp_path" placeholder="/home/usuario/public_html/wp-content/plugins">
                                <small>Se auto-rellena desde la cuenta WHM si esta configurada.</small>
                            </div>
                            <div class="rphub-form-group">
                                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin:0;">
                                    <input type="checkbox" id="site-ftp-ssl" name="ftp_ssl" value="1" style="width:auto;margin:0;">
                                    <span>Usar FTPS (FTP sobre SSL)</span>
                                </label>
                            </div>
                            <div class="rphub-form-group" style="display:flex;align-items:center;gap:12px;">
                                <button type="button" class="button" onclick="rphubTestFtp(this)">Probar conexion FTP</button>
                                <span id="rphub-ftp-test-result" style="font-size:13px;"></span>
                            </div>
                        </div>
                    </fieldset>
                        <div class="rphub-form-group">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin:0;">
                                <input type="checkbox" id="site-addon-ecommerce" name="addon_ecommerce" value="1" style="width:auto;margin:0;" onchange="document.getElementById('rphub-ecom-cfg').style.display=this.checked?'':'none'">
                                <span>Addon eCommerce <span style="color:#646970;font-weight:400;">(+35&euro;/mes)</span></span>
                            </label>
                            <small>Activa checkout monitor 15min, peak scheduler, revenue anomaly y backups cada 12h.</small>
                        </div>
                        <div id="rphub-ecom-cfg" style="display:none;margin-top:12px;padding-top:12px;border-top:1px solid #e2e8f0;">
                            <div class="rphub-form-grid">
                                <div class="rphub-form-group">
                                    <label for="site-ecom-revenue-threshold">Umbral alerta ingresos (%)</label>
                                    <input type="number" id="site-ecom-revenue-threshold" name="ecom_revenue_threshold" value="35" min="5" max="90">
                                    <small>Caida respecto a misma ventana hace 7 dias.</small>
                                </div>
                                <div class="rphub-form-group">
                                    <label for="site-ecom-alert-email">Email de alertas eCommerce</label>
                                    <input type="email" id="site-ecom-alert-email" name="ecom_alert_email" placeholder="(usa el email de alertas del sitio)">
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <div class="rphub-form-group" id="rphub-token-group" style="display: none;">
                        <label for="site-token">Token de conexión (Replanta Care)</label>
                        <div class="rphub-token-container" style="display:flex;gap:8px;align-items:center;">
                            <input type="text" id="site-token" name="token" style="font-family: monospace; font-size: 12px; flex:1;" readonly>
                            <button type="button" class="button" onclick="rphubGenerateToken()" title="Generar nuevo token">↺</button>
                            <button type="button" class="button button-primary" onclick="rphubCopyToken()">Copiar</button>
                        </div>
                        <small>Copia este token y pégalo en <strong>Replanta Care → Ajustes → Token del sitio</strong>.</small>
                    </div>

                    <div class="rphub-modal-footer">
                        <button type="button" class="button" onclick="rphubCloseSiteModal()">Cancelar</button>
                        <button type="submit" class="button button-primary" id="rphub-save-site">Guardar Sitio</button>
                    </div>
                </form>
            </div>
        </div>

        <style>
        .rphub-modal{position:fixed;inset:0;background:rgba(15,23,42,.55);display:none;align-items:flex-start;justify-content:center;z-index:100000;overflow-y:auto;padding:40px 20px;}
        .rphub-modal[style*="flex"]{display:flex!important;}
        .rphub-modal-content{background:#fff;border-radius:10px;box-shadow:0 20px 60px rgba(0,0,0,.25);width:100%;max-width:760px;margin:auto;display:flex;flex-direction:column;max-height:calc(100vh - 80px);}
        .rphub-modal-header{display:flex;align-items:center;justify-content:space-between;padding:20px 28px;border-bottom:1px solid #e2e8f0;}
        .rphub-modal-header h2{margin:0;font-size:20px;font-weight:600;color:#0f172a;}
        .rphub-modal-close{background:none;border:0;font-size:28px;line-height:1;cursor:pointer;color:#64748b;padding:4px 10px;border-radius:4px;}
        .rphub-modal-close:hover{background:#f1f5f9;color:#0f172a;}
        .rphub-modal-body{padding:24px 28px;overflow-y:auto;display:flex;flex-direction:column;gap:18px;}
        .rphub-modal-footer{display:flex;justify-content:flex-end;gap:10px;padding:18px 28px;border-top:1px solid #e2e8f0;background:#f8fafc;border-radius:0 0 10px 10px;}
        .rphub-fieldset{border:1px solid #e2e8f0;border-radius:8px;padding:16px 18px 18px;margin:0;background:#fff;}
        .rphub-fieldset legend{padding:0 8px;font-weight:600;font-size:13px;color:#475569;text-transform:uppercase;letter-spacing:.04em;}
        .rphub-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
        @media (max-width: 600px){.rphub-form-grid{grid-template-columns:1fr;}}
        .rphub-form-group{display:flex;flex-direction:column;gap:6px;}
        .rphub-form-group label{font-weight:500;font-size:13px;color:#334155;}
        .rphub-form-group input[type=text],
        .rphub-form-group input[type=url],
        .rphub-form-group input[type=email],
        .rphub-form-group select,
        .rphub-form-group textarea{padding:8px 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;background:#fff;}
        .rphub-form-group input:focus,
        .rphub-form-group select:focus,
        .rphub-form-group textarea:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.15);}
        .rphub-form-group small{color:#64748b;font-size:12px;}
        .rphub-help{font-size:12px;color:#64748b;margin:10px 0 0;}
        .rphub-fieldset[hidden]{display:none;}
        </style>
        
        <script>
        // Ensure ajaxurl is available for this page
        if (typeof ajaxurl === 'undefined') {
            var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        }
        window.rphubIntNonce = '<?php echo wp_create_nonce('rphub_integrations'); ?>';
        window.rphubAjaxNonce = '<?php echo wp_create_nonce('rphub_ajax'); ?>';
        
        // Toast notification system - Defined globally
        function rphubShowToast(type, title, message, duration = 5000) {
            let container = document.getElementById('rphub-toast-container');
            if (!container) {
                // Create container if it doesn't exist
                container = document.createElement('div');
                container.id = 'rphub-toast-container';
                container.className = 'rphub-toast-container';
                document.body.appendChild(container);
            }
            
            const toast = document.createElement('div');
            toast.className = `rphub-toast ${type}`;
            
            toast.innerHTML = `
                <button class="rphub-toast-close" onclick="rphubCloseToast(this)">&times;</button>
                <div class="rphub-toast-title">${title}</div>
                <div class="rphub-toast-message">${message}</div>
            `;
            
            container.appendChild(toast);
            
            // Trigger animation
            setTimeout(() => toast.classList.add('show'), 10);
            
            // Auto remove
            if (duration > 0) {
                setTimeout(() => rphubCloseToast(toast.querySelector('.rphub-toast-close')), duration);
            }
        }
        
        function rphubCloseToast(closeButton) {
            const toast = closeButton.parentElement;
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }
        
        // Convenience functions
        function rphubToastSuccess(title, message, duration) {
            rphubShowToast('success', title, message, duration);
        }
        
        function rphubToastError(title, message, duration) {
            rphubShowToast('error', title, message, duration);
        }
        
        function rphubToastWarning(title, message, duration) {
            rphubShowToast('warning', title, message, duration);
        }
        
        function rphubToastInfo(title, message, duration) {
            rphubShowToast('info', title, message, duration);
        }
        
        function rphubMakeToken() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            const arr = new Uint8Array(32);
            window.crypto.getRandomValues(arr);
            return Array.from(arr, v => chars[v % chars.length]).join('');
        }
        function rphubGenerateToken() {
            const input = document.getElementById('site-token');
            if (input) input.value = rphubMakeToken();
        }
        function rphubCopyToken() {
            const tokenInput = document.getElementById('site-token');
            if (tokenInput && tokenInput.value) {
                navigator.clipboard.writeText(tokenInput.value).then(function() {
                    rphubToastSuccess('¡Copiado!', 'Token copiado al portapapeles');
                }).catch(function() {
                    // Fallback for older browsers
                    tokenInput.select();
                    document.execCommand('copy');
                    rphubToastSuccess('¡Copiado!', 'Token copiado al portapapeles');
                });
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Verify rphub_ajax is available after DOM is loaded
            if (typeof rphub_ajax === 'undefined') {
                console.warn(' rphub_ajax no está disponible aún. Esperando carga de scripts...');
                // Wait a bit for scripts to load
                setTimeout(function() {
                    if (typeof rphub_ajax === 'undefined') {
                        console.error(' rphub_ajax is not defined after waiting. The plugin may not work correctly.');
                    } else {
                        console.log(' rphub_ajax cargado correctamente después de esperar');
                    }
                }, 500);
            }
            
            // Select all checkbox functionality
            document.getElementById('cb-select-all').addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('input[name="site_ids[]"]');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            });
            
            // Site form submission
            document.getElementById('rphub-site-form').addEventListener('submit', function(e) {
                e.preventDefault();
                rphubSaveSite();
            });
        });
        
        let rphubCurrentSiteId = null;

        // --- Modal helpers: domain_type toggle + lazy dropdowns -----------
        function rphubToggleWhmSection() {
            const type = document.getElementById('site-domain-type').value;
            const fs = document.getElementById('rphub-fs-whm');
            if (!fs) return;
            fs.hidden = (type !== 'internal');
            if (type === 'internal') rphubLoadWhmServers();
        }

        function rphubBindSelectToHidden(selectId) {
            const sel = document.getElementById(selectId);
            if (!sel || sel.dataset.bound) return;
            sel.dataset.bound = '1';
            sel.addEventListener('change', function(){
                const targetId = this.dataset.target;
                const hidden = targetId ? document.getElementById(targetId) : null;
                if (hidden) hidden.value = this.value;
            });
        }

        function rphubFillSelect(selectId, items, valueKey, labelKey, currentValue) {
            const sel = document.getElementById(selectId);
            if (!sel) return;
            sel.innerHTML = '<option value="">— Selecciona —</option>';
            (items || []).forEach(function(it){
                const opt = document.createElement('option');
                opt.value = it[valueKey] || '';
                opt.textContent = it[labelKey] || it[valueKey] || '';
                if (currentValue && String(currentValue) === String(opt.value)) opt.selected = true;
                sel.appendChild(opt);
            });
            // Ensure hidden mirror reflects current selection
            const hidden = document.getElementById(sel.dataset.target);
            if (hidden && sel.value) hidden.value = sel.value;
        }

        function rphubLoadWhmServers(current) {
            jQuery.post(ajaxurl, {
                action: 'rphub_whm_get_servers',
                nonce: window.rphubAjaxNonce
            }).done(function(r){
                if (!r.success) return;
                const sel = document.getElementById('site-whm-server');
                if (!sel) return;
                const cur = current || sel.value || document.getElementById('site-whm-server').dataset.preselect || '';
                sel.innerHTML = '<option value="">— Selecciona servidor —</option>';
                (r.data || []).forEach(function(s){
                    const opt = document.createElement('option');
                    opt.value = s.id;
                    opt.textContent = (s.label || s.id) + (s.region ? ' (' + s.region + ')' : '');
                    if (cur && cur === s.id) opt.selected = true;
                    sel.appendChild(opt);
                });
                if (sel.value) rphubLoadWhmAccounts(sel.value);
            });
        }

        function rphubLoadWhmAccounts(serverId, currentAccount) {
            const status = document.getElementById('site-whm-account-status');
            const sel = document.getElementById('site-whm-account-select');
            if (!sel) return;
            if (!serverId) {
                sel.innerHTML = '<option value="">— Primero selecciona servidor —</option>';
                return;
            }
            sel.innerHTML = '<option value="">Cargando cuentas…</option>';
            if (status) status.textContent = '';
            jQuery.post(ajaxurl, {
                action: 'rphub_whm_get_accounts',
                nonce: window.rphubAjaxNonce,
                server_id: serverId
            }).done(function(r){
                if (!r.success) {
                    sel.innerHTML = '<option value="">— Error —</option>';
                    if (status) status.textContent = (r.data || 'Error cargando cuentas');
                    return;
                }
                const items = (r.data || []).map(function(a){
                    return { user: a.user, label: (a.user || '') + (a.domain ? ' — ' + a.domain : '') };
                });
                const cur = currentAccount || document.getElementById('site-whm-account').value || '';
                rphubFillSelect('site-whm-account-select', items, 'user', 'label', cur);
            }).fail(function(){
                sel.innerHTML = '<option value="">— Error de red —</option>';
            });
        }

        function rphubLoadIntegrationList(type, selectId, valueKey, labelKey, currentValue, statusId) {
            const sel = document.getElementById(selectId);
            const status = statusId ? document.getElementById(statusId) : null;
            if (!sel) return;
            sel.innerHTML = '<option value="">Cargando…</option>';
            jQuery.post(ajaxurl, {
                action: 'rphub_list_integration_options',
                nonce: window.rphubIntNonce,
                type: type
            }).done(function(r){
                if (!r.success) {
                    sel.innerHTML = '<option value="">— Sin conexión Google —</option>';
                    if (status) status.textContent = (r.data || 'Conecta Google en Integraciones.');
                    return;
                }
                const items = (r.data && r.data.items) ? r.data.items : [];
                rphubFillSelect(selectId, items.map(function(i){
                    return { v: i[valueKey], l: i[labelKey] || i[valueKey] };
                }), 'v', 'l', currentValue);
                if (status) status.textContent = items.length ? '' : 'No hay propiedades visibles para esta cuenta.';
            }).fail(function(){
                sel.innerHTML = '<option value="">— Error de red —</option>';
            });
        }

        function rphubPrepareModalCommon() {
            rphubBindSelectToHidden('site-whm-server');
            rphubBindSelectToHidden('site-whm-account-select');
            rphubBindSelectToHidden('site-ga4-property-select');
            rphubBindSelectToHidden('site-sc-domain-select');

            const dt = document.getElementById('site-domain-type');
            if (dt && !dt.dataset.bound) {
                dt.dataset.bound = '1';
                dt.addEventListener('change', rphubToggleWhmSection);
            }
            const srv = document.getElementById('site-whm-server');
            if (srv && !srv.dataset.boundChange) {
                srv.dataset.boundChange = '1';
                srv.addEventListener('change', function(){ rphubLoadWhmAccounts(this.value); });
            }
        }

        function rphubOpenAddSiteModal() {
            rphubCurrentSiteId = null;
            document.getElementById('rphub-modal-title').textContent = 'Añadir Sitio';
            document.getElementById('rphub-save-site').textContent = 'Guardar Sitio';
            document.getElementById('rphub-site-form').reset();
            document.getElementById('rphub-token-group').style.display = 'none';
            rphubPrepareModalCommon();
            rphubToggleWhmSection();
            rphubLoadIntegrationList('ga4', 'site-ga4-property-select', 'property_id', 'display', '', 'site-ga4-status');
            rphubLoadIntegrationList('sc',  'site-sc-domain-select',    'site_url',    'site_url', '', 'site-sc-status');
            document.getElementById('rphub-site-modal').style.display = 'flex';
        }
        
        function rphubOpenSiteModal(siteId) {
            if (typeof rphub_ajax === 'undefined') {
                rphubToastError('Error de Configuración', 'Variables AJAX no disponibles');
                return;
            }
            
            rphubCurrentSiteId = siteId;
            
            if (siteId) {
                // Edit mode - load site data
                document.getElementById('rphub-modal-title').textContent = 'Editar Sitio';
                document.getElementById('rphub-save-site').textContent = 'Actualizar Sitio';
                
                // Load site data via AJAX using jQuery
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rphub_get_site_data',
                        nonce: rphub_ajax.nonce,
                        site_id: siteId
                    },
                    success: function(result) {
                        if (result.success) {
                            const site = result.data;
                            // Populate form fields with null checks
                            const siteNameEl = document.getElementById('site-name');
                            const siteUrlEl = document.getElementById('site-url');
                            const sitePlanEl = document.getElementById('site-plan');
                            const siteStatusEl = document.getElementById('site-status');
                            const siteNotesEl = document.getElementById('site-notes');
                            const siteWhmAccountEl = document.getElementById('site-whm-account');
                            const siteTokenEl = document.getElementById('site-token');
                            const tokenGroupEl = document.getElementById('rphub-token-group');
                            
                            if (siteNameEl) siteNameEl.value = site.name || '';
                            if (siteUrlEl) siteUrlEl.value = site.url || '';
                            if (sitePlanEl) sitePlanEl.value = site.plan || '';
                            if (siteStatusEl) siteStatusEl.value = site.status || '';
                            if (siteNotesEl) siteNotesEl.value = site.notes || '';
                            if (siteWhmAccountEl) siteWhmAccountEl.value = site.whm_account || '';
                            const el = (id) => document.getElementById(id);
                            if (el('site-client-name'))  el('site-client-name').value  = site.client_name  || '';
                            if (el('site-client-email')) el('site-client-email').value = site.client_email || '';
                            if (el('site-alert-email'))  el('site-alert-email').value  = site.alert_email  || '';
                            if (el('site-ga4-property')) el('site-ga4-property').value = site.ga4_property_id || '';
                            if (el('site-sc-domain'))    el('site-sc-domain').value    = site.sc_domain    || '';
                            if (el('site-domain-type'))  el('site-domain-type').value  = site.domain_type || 'external';
                            if (el('site-whm-server'))   el('site-whm-server').dataset.preselect = site.whm_server || '';
                            const uptimeEl = el('site-uptime-enabled');
                            if (uptimeEl) uptimeEl.checked = (site.uptime_monitoring_enabled === '1' || site.uptime_monitoring_enabled === 1);

                            // Addon eCommerce
                            const addonEcomEl = el('site-addon-ecommerce');
                            const ecomCfgEl   = el('rphub-ecom-cfg');
                            if (addonEcomEl) {
                                addonEcomEl.checked = (site.addon_ecommerce === '1' || site.addon_ecommerce === 1);
                                if (ecomCfgEl) ecomCfgEl.style.display = addonEcomEl.checked ? '' : 'none';
                            }
                            if (el('site-ecom-revenue-threshold')) el('site-ecom-revenue-threshold').value = site.ecom_revenue_threshold || '35';
                            if (el('site-ecom-alert-email'))       el('site-ecom-alert-email').value       = site.ecom_alert_email       || '';

                            // FTP credentials
                            if (el('site-ftp-host')) el('site-ftp-host').value = site.ftp_host || '';
                            if (el('site-ftp-user')) el('site-ftp-user').value = site.ftp_user || '';
                            if (el('site-ftp-port')) el('site-ftp-port').value = site.ftp_port || '21';
                            if (el('site-ftp-ssl'))  el('site-ftp-ssl').checked = (site.ftp_ssl === '1' || site.ftp_ssl === 1);
                            if (el('site-ftp-path')) el('site-ftp-path').value = site.ftp_path || '';
                            if (el('site-ftp-pass')) el('site-ftp-pass').value = '';
                            var ftpHint = el('site-ftp-pass-hint');
                            if (ftpHint) ftpHint.style.display = site.ftp_has_pass ? '' : 'none';
                            if (site.ftp_host && el('site-ftp-host').closest('.rphub-ftp-body')) {
                                el('site-ftp-host').closest('.rphub-ftp-body').style.display = '';
                            }

                            rphubPrepareModalCommon();
                            rphubToggleWhmSection();
                            rphubLoadIntegrationList('ga4', 'site-ga4-property-select', 'property_id', 'display', site.ga4_property_id || '', 'site-ga4-status');
                            rphubLoadIntegrationList('sc',  'site-sc-domain-select',    'site_url',    'site_url', site.sc_domain || '',    'site-sc-status');

                            // Show token field — always in edit mode; auto-generate if empty
                            if (siteTokenEl && tokenGroupEl) {
                                if (site.token) {
                                    siteTokenEl.value = site.token;
                                } else {
                                    siteTokenEl.value = rphubMakeToken();
                                }
                                tokenGroupEl.style.display = 'block';
                            }
                        } else {
                            console.error('Error loading site data:', result.data);
                            rphubToastError('Error de Carga', 'Error al cargar los datos del sitio: ' + result.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', error);
                        rphubToastError('Error de Conexión', 'Error de conexión al cargar los datos del sitio');
                    }
                });
            } else {
                // Add mode - clear form
                document.getElementById('rphub-modal-title').textContent = 'Agregar Sitio';
                document.getElementById('rphub-save-site').textContent = 'Agregar Sitio';
                document.getElementById('rphub-site-form').reset();
                
                // Hide token field for new sites
                const tokenGroupEl = document.getElementById('rphub-token-group');
                const siteTokenNewEl = document.getElementById('site-token');
                if (tokenGroupEl && siteTokenNewEl) {
                    siteTokenNewEl.value = rphubMakeToken();
                    tokenGroupEl.style.display = 'block';
                }
            }
            
            document.getElementById('rphub-site-modal').style.display = 'flex';
        }
        
        function rphubCloseSiteModal() {
            document.getElementById('rphub-site-modal').style.display = 'none';
            rphubCurrentSiteId = null;
        }

        function rphubTestFtp(btn) {
            var siteId = rphubCurrentSiteId;
            if (!siteId) { alert('Guarda el sitio primero para poder probar la conexion FTP.'); return; }
            var result = document.getElementById('rphub-ftp-test-result');
            btn.disabled = true;
            result.textContent = 'Probando...';
            result.style.color = '#64748b';
            jQuery.post(ajaxurl, {
                action: 'rphub_test_ftp',
                nonce: rphub_ajax.nonce,
                site_id: siteId
            }, function(resp) {
                btn.disabled = false;
                if (resp.success) {
                    result.textContent = 'OK: ' + resp.data;
                    result.style.color = '#16a34a';
                } else {
                    result.textContent = 'Error: ' + (resp.data || 'fallo de conexion');
                    result.style.color = '#dc2626';
                }
            });
        }

        function rphubSaveSite() {
            if (typeof rphub_ajax === 'undefined') {
                rphubToastError('Error de Configuración', 'Variables AJAX no disponibles');
                return;
            }
            
            const form = document.getElementById('rphub-site-form');
            const formData = new FormData(form);
            
            // Convert to plain object for jQuery
            const data = {
                action: rphubCurrentSiteId ? 'rphub_update_site' : 'rphub_add_site',
                nonce: rphub_ajax.nonce
            };
            
            if (rphubCurrentSiteId) {
                data.site_id = rphubCurrentSiteId;
            }
            
            // Add form fields to data object
            for (const [key, value] of formData.entries()) {
                data[key] = value;
            }
            
            const saveButton = document.getElementById('rphub-save-site');
            const originalText = saveButton.textContent;
            saveButton.textContent = 'Guardando...';
            saveButton.disabled = true;
            
            // Use jQuery instead of fetch to avoid CORS issues
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: data,
                success: function(result) {
                    if (result.success) {
                        rphubToastSuccess('Éxito', rphubCurrentSiteId ? 'Sitio actualizado correctamente' : 'Sitio añadido correctamente');
                        
                        // Show token for new sites
                        if (!rphubCurrentSiteId && result.data && result.data.token) {
                            const tokenMessage = `
                                <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 10px 0; border-radius: 5px;">
                                    <h4> Token del Sitio Generado</h4>
                                    <p><strong>Copia este token y configúralo en el plugin Replanta Care del sitio:</strong></p>
                                    <p style="background: #f8f9fa; padding: 10px; border-radius: 3px; font-family: monospace; word-break: break-all;">
                                        ${result.data.token}
                                    </p>
                                    <p><small>Este token es único para este sitio y permite la comunicación segura con el HUB.</small></p>
                                </div>
                            `;
                            
                            // Show token in a prominent way
                            setTimeout(() => {
                                alert(' Sitio creado exitosamente!\n\n TOKEN GENERADO:\n' + result.data.token + '\n\nCopia este token y configúralo en Replanta Care del sitio.');
                            }, 500);
                        }
                        
                        rphubCloseSiteModal();
                        // Update the site row without reloading the page
                        if (rphubCurrentSiteId) {
                            // For updates, refresh only the affected row
                            rphubRefreshSiteRow(rphubCurrentSiteId);
                        } else {
                            // For new sites, refresh the full table
                            setTimeout(() => {
                                location.reload();
                            }, 1500);
                        }
                    } else {
                        rphubToastError('Error', result.data || 'Error desconocido al guardar el sitio');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    rphubToastError('Error de Conexión', 'Error al guardar el sitio');
                },
                complete: function() {
                    saveButton.textContent = originalText;
                    saveButton.disabled = false;
                }
            });
        }
        
        function rphubSyncSite(siteId) {
            rphubSiteAction(siteId, 'sync_data', 'Sincronizando...', 'Datos sincronizados');
        }
        
        function rphubTestConnection(siteId) {
            rphubSiteAction(siteId, 'test_connection', 'Probando conexión...', 'Conexión exitosa');
        }
        
        function rphubRemoveSite(siteId) {
            if (typeof rphub_ajax === 'undefined') {
                rphubToastError('Error de Configuración', 'Variables AJAX no disponibles');
                return;
            }
            
            if (!confirm('¿Estás seguro de que quieres eliminar este sitio? Esta acción no se puede deshacer.')) {
                return;
            }
            
            // Use jQuery instead of fetch to avoid CORS issues
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'rphub_remove_site',
                    nonce: rphub_ajax.nonce,
                    site_id: siteId
                },
                success: function(data) {
                    if (data.success) {
                        rphubToastSuccess('Eliminado', 'Sitio eliminado correctamente');
                        location.reload();
                    } else {
                        rphubToastError('Error de Eliminación', 'Error al eliminar: ' + data.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    rphubToastError('Error de Conexión', 'Error al eliminar el sitio');
                }
            });
        }
        
        function rphubSiteAction(siteId, action, loadingText, successText) {
            if (typeof rphub_ajax === 'undefined') {
                rphubToastError('Error de Configuración', 'Variables AJAX no disponibles');
                return;
            }
            
            const row = document.querySelector(`tr[data-site-id="${siteId}"]`);
            const originalStatus = row.querySelector('.rphub-status-badge');
            const tempStatus = originalStatus.cloneNode(true);
            
            tempStatus.textContent = loadingText;
            tempStatus.className = 'rphub-status-badge rphub-status-loading';
            originalStatus.parentNode.replaceChild(tempStatus, originalStatus);
            
            // Map action to correct AJAX handler
            let ajaxAction;
            switch(action) {
                case 'sync_data':
                    ajaxAction = 'rphub_sync_site_data';
                    break;
                case 'test_connection':
                    ajaxAction = 'rphub_test_connection';
                    break;
                default:
                    ajaxAction = 'rphub_sync_site_data'; // Default fallback
            }
            
            // Use jQuery instead of fetch to avoid CORS issues
            jQuery.ajax({
                url: ajaxurl, // Use WordPress global ajaxurl instead of rphub_ajax.ajax_url
                type: 'POST',
                data: {
                    action: ajaxAction, // Use mapped action
                    nonce: rphub_ajax.nonce,
                    site_id: siteId
                },
                success: function(data) {
                    if (data.success) {
                        tempStatus.textContent = successText;
                        tempStatus.className = 'rphub-status-badge rphub-status-success';
                        
                        rphubToastSuccess('Éxito', data.data || successText);
                        
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        tempStatus.textContent = 'Error';
                        tempStatus.className = 'rphub-status-badge rphub-status-error';
                        rphubToastError('Error', data.data || 'Error en la operación');
                        
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    console.error('Status:', status);
                    console.error('Response:', xhr.responseText);
                    
                    tempStatus.textContent = 'Error';
                    tempStatus.className = 'rphub-status-badge rphub-status-error';
                    rphubToastError('Error de Conexión', 'Error al procesar la solicitud');
                    
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                }
            });
        }
        
        function rphubExecuteBulkAction() {
            const action = document.getElementById('rphub-bulk-action').value;
            const checkedSites = document.querySelectorAll('input[name="site_ids[]"]:checked');
            
            if (!action) {
                alert('Selecciona una acción');
                return;
            }
            
            if (checkedSites.length === 0) {
                alert('Selecciona al menos un sitio');
                return;
            }
            
            if (!confirm(`¿Ejecutar "${action}" en ${checkedSites.length} sitio(s) seleccionado(s)?`)) {
                return;
            }
            
            const siteIds = Array.from(checkedSites).map(cb => cb.value);
            
            // Use jQuery instead of fetch to avoid CORS issues
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: `rphub_bulk_${action}`,
                    nonce: rphub_ajax.nonce,
                    site_ids: siteIds
                },
                success: function(data) {
                    if (data.success) {
                        const result = data.data;
                        alert(`Acción ejecutada:\n Exitosos: ${Object.keys(result.success).length}\n Errores: ${Object.keys(result.errors).length}`);
                        location.reload();
                    } else {
                        alert('Error: ' + data.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    alert('Error de conexión: ' + error);
                }
            });
        }
        
        function rphubRefreshSiteRow(siteId) {
            console.log(' Refreshing site row for ID:', siteId);
            
            // Get updated site data
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'rphub_get_site_data',
                    site_id: siteId,
                    nonce: rphub_ajax.nonce
                },
                success: function(result) {
                    if (result.success && result.data) {
                        const site = result.data;
                        const row = document.querySelector(`tr[data-site-id="${siteId}"]`);
                        
                        if (row) {
                            // Update site name and URL
                            const infoCell = row.querySelector('.rphub-site-info');
                            if (infoCell) {
                                infoCell.innerHTML = `
                                    <strong>${site.name}</strong><br>
                                    <a href="${site.url}" target="_blank">${site.url}</a>
                                `;
                            }
                            
                            // Update plan
                            const planCell = row.querySelector('.rphub-plan');
                            if (planCell) {
                                planCell.textContent = site.plan || 'N/A';
                            }
                            
                            console.log(' Site row updated successfully');
                        }
                    } else {
                        console.error(' Failed to get updated site data:', result.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error(' Error refreshing site row:', error);
                }
            });
        }
        
        // Close modal when clicking outside
        document.getElementById('rphub-site-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                rphubCloseSiteModal();
            }
        });
        </script>
        <?php
        $this->render_cards_scripts();
    }

    private function render_cards_scripts() {
        ?>
        <style>
        .rphub-card { background:#fff; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.06); transition:box-shadow .15s; }
        .rphub-card:hover { box-shadow:0 4px 12px rgba(0,0,0,.1); }
        .rphub-card-header { padding:10px 14px; display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid #f0f0f1; }
        .rphub-card-client { font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:#646970; font-weight:600; }
        .rphub-card-status-dot { width:9px; height:9px; border-radius:50%; flex-shrink:0; }
        .rphub-card-status-dot.active   { background:#00a32a; }
        .rphub-card-status-dot.error    { background:#d63638; }
        .rphub-card-status-dot.inactive { background:#dcdcde; }
        .rphub-card-body { padding:12px 14px; }
        .rphub-card-name { font-size:15px; font-weight:600; color:#1d2327; margin:0 0 2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .rphub-card-url  { font-size:12px; color:#2271b1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .rphub-card-url a { color:inherit; text-decoration:none; }
        .rphub-card-url a:hover { text-decoration:underline; }
        .rphub-card-metrics { display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; margin:12px 0; }
        .rphub-card-metric { text-align:center; background:#f6f7f7; border-radius:6px; padding:8px 4px; }
        .rphub-card-metric-value { font-size:20px; font-weight:700; line-height:1; }
        .rphub-card-metric-label { font-size:10px; color:#646970; margin-top:3px; text-transform:uppercase; letter-spacing:.3px; }
        .rphub-card-metric-value.good    { color:#00a32a; }
        .rphub-card-metric-value.warning { color:#dba617; }
        .rphub-card-metric-value.danger  { color:#d63638; }
        .rphub-card-versions { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:10px; }
        .rphub-version-pill { font-size:11px; background:#f0f0f1; border-radius:20px; padding:2px 8px; color:#3c434a; white-space:nowrap; }
        .rphub-version-pill b { color:#1d2327; }
        .rphub-card-footer { padding:8px 14px; background:#f6f7f7; border-top:1px solid #f0f0f1; display:flex; align-items:center; justify-content:space-between; gap:6px; }
        .rphub-card-plan-badge { font-size:11px; font-weight:600; padding:2px 8px; border-radius:20px; text-transform:uppercase; letter-spacing:.3px; }
        .rphub-card-plan-badge.semilla    { background:#e6f3ff; color:#2271b1; }
        .rphub-card-plan-badge.raiz       { background:#edfaef; color:#00a32a; }
        .rphub-card-plan-badge.ecosistema { background:#fef8ee; color:#c67b00; }
        .rphub-card-actions { display:flex; gap:4px; }
        .rphub-card-actions .button { padding:0 6px; min-height:26px; }
        .rphub-last-check-text { font-size:11px; color:#646970; }
        .rphub-client-btn.button-primary { background:#2271b1; border-color:#2271b1; color:#fff; }
        .rphub-cf-badge { font-size:11px; display:inline-flex; align-items:center; padding:1px 6px; border-radius:10px; font-weight:600; white-space:nowrap; }
        .rphub-cf-badge.cf-active  { background:#e6f9eb; color:#00a32a; }
        .rphub-cf-badge.cf-pending { background:#fef8ee; color:#c67b00; }
        .rphub-cf-badge.cf-none    { background:#f0f0f1; color:#646970; }
        .rphub-ssl-badge { font-size:11px; display:inline-flex; align-items:center; padding:1px 6px; border-radius:10px; font-weight:600; white-space:nowrap; }
        .rphub-ssl-badge.ssl-le   { background:#e6f0fd; color:#1565c0; }
        .rphub-ssl-badge.ssl-as   { background:#ede7f6; color:#4527a0; }
        .rphub-ssl-badge.ssl-paid { background:#fff8e1; color:#f57f17; }
        .rphub-ssl-badge.ssl-cf   { background:#e6f9eb; color:#00a32a; }
        .rphub-ssl-badge.ssl-ok   { background:#e6f9eb; color:#00a32a; }
        .rphub-ssl-badge.ssl-warn { background:#ffebee; color:#c62828; }
        .rphub-card-meta-row { display:flex; align-items:center; gap:6px; flex-wrap:wrap; margin:4px 0 8px; font-size:13px; }
        .rphub-ecology-pill { font-size:11px; background:#edfaef; color:#00a32a; padding:2px 7px; border-radius:10px; }
        .rphub-audit-scores { display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; margin:10px 0 4px; }
        .rphub-audit-score-label { font-size:10px; text-transform:uppercase; letter-spacing:.3px; color:#646970; margin-bottom:3px; }
        .rphub-score-bar-bg   { background:#f0f0f1; border-radius:3px; height:4px; overflow:hidden; }
        .rphub-score-bar-fill { height:100%; border-radius:3px; transition:width .4s; }
        .rphub-score-bar-fill.good    { background:#00a32a; }
        .rphub-score-bar-fill.warning { background:#dba617; }
        .rphub-score-bar-fill.danger  { background:#d63638; }
        .rphub-score-bar-fill.empty   { background:#dcdcde; }
        .rphub-audit-score-num { font-size:12px; font-weight:700; margin-top:3px; }
        .rphub-audit-score-num.good    { color:#00a32a; }
        .rphub-audit-score-num.warning { color:#dba617; }
        .rphub-audit-score-num.danger  { color:#d63638; }
        .rphub-audit-score-num.empty   { color:#646970; }
        </style>

        <script>
        var rphubCardsData = null;
        var rphubActiveClient = '';

        function rphubSetView(mode) {
            const tableWrap  = document.querySelector('.rphub-sites-table-container');
            const bulkBar    = document.querySelector('.rphub-bulk-actions');
            const cardsView  = document.getElementById('rphub-cards-view');
            const btnTable   = document.getElementById('rphub-view-table');
            const btnCards   = document.getElementById('rphub-view-cards');

            if (mode === 'cards') {
                if (tableWrap)  tableWrap.style.display  = 'none';
                if (bulkBar)    bulkBar.style.display    = 'none';
                if (cardsView)  cardsView.style.display  = 'block';
                if (btnTable)   { btnTable.classList.remove('button-primary'); btnTable.classList.add('button'); }
                if (btnCards)   { btnCards.classList.remove('button'); btnCards.classList.add('button-primary'); }
                if (!rphubCardsData) rphubLoadCards();
            } else {
                if (tableWrap)  tableWrap.style.display  = 'block';
                if (bulkBar)    bulkBar.style.display    = 'block';
                if (cardsView)  cardsView.style.display  = 'none';
                if (btnTable)   { btnTable.classList.remove('button'); btnTable.classList.add('button-primary'); }
                if (btnCards)   { btnCards.classList.remove('button-primary'); btnCards.classList.add('button'); }
            }
            try { localStorage.setItem('rphubSitesView', mode); } catch(e) {}
        }

        function rphubLoadCards() {
            document.getElementById('rphub-cards-loading').style.display = 'block';
            document.getElementById('rphub-cards-grid').innerHTML = '';
            jQuery.post(ajaxurl, { action: 'rphub_get_sites_cards', nonce: rphub_ajax.nonce })
                .done(function(r) {
                    document.getElementById('rphub-cards-loading').style.display = 'none';
                    if (!r.success) { document.getElementById('rphub-cards-grid').innerHTML = '<p style="color:#d63638;">Error al cargar los sitios: ' + (r.data || '') + '</p>'; return; }
                    rphubCardsData = r.data;
                    rphubBuildClientFilter(r.data.clients);
                    rphubRenderCards(r.data.sites, rphubActiveClient);
                })
                .fail(function(xhr) {
                    jQuery.ajax({
                        url: rphub_ajax.rest_url + 'sites-cards',
                        type: 'GET',
                        headers: { 'X-WP-Nonce': rphub_ajax.rest_nonce }
                    }).done(function(data) {
                        document.getElementById('rphub-cards-loading').style.display = 'none';
                        rphubCardsData = data;
                        rphubBuildClientFilter(data.clients);
                        rphubRenderCards(data.sites, rphubActiveClient);
                    }).fail(function() {
                        document.getElementById('rphub-cards-loading').style.display = 'none';
                        document.getElementById('rphub-cards-grid').innerHTML = '<p style="color:#d63638;">Error de red al cargar sitios. (HTTP ' + xhr.status + ')</p>';
                    });
                });
        }

        function rphubBuildClientFilter(clients) {
            const bar = document.getElementById('rphub-cards-client-filter');
            bar.innerHTML = '<span style="font-size:13px;color:#646970;">Cliente:</span>';
            const all = document.createElement('button');
            all.type = 'button';
            all.className = 'button rphub-client-btn' + (rphubActiveClient === '' ? ' button-primary' : '');
            all.textContent = 'Todos';
            all.onclick = function() { rphubFilterClient(this, ''); };
            bar.appendChild(all);
            clients.forEach(function(c) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'button rphub-client-btn' + (rphubActiveClient === c ? ' button-primary' : '');
                btn.textContent = c;
                btn.onclick = function() { rphubFilterClient(this, c); };
                bar.appendChild(btn);
            });
        }

        function rphubFilterClient(btn, client) {
            rphubActiveClient = client;
            document.querySelectorAll('.rphub-client-btn').forEach(function(b) {
                b.classList.remove('button-primary'); b.classList.add('button');
            });
            btn.classList.remove('button'); btn.classList.add('button-primary');
            if (rphubCardsData) rphubRenderCards(rphubCardsData.sites, client);
        }

        function rphubRenderCards(sites, clientFilter) {
            const grid = document.getElementById('rphub-cards-grid');
            const filtered = clientFilter ? sites.filter(function(s) { return (s.client_name || 'Sin cliente') === clientFilter; }) : sites;
            if (!filtered.length) { grid.innerHTML = '<p style="color:#646970;padding:20px 0;">No hay sitios para mostrar.</p>'; return; }
            grid.innerHTML = filtered.map(rphubBuildCard).join('');
        }

        function escHtml(str) {
            return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        function rphubHealthColor(score) {
            score = parseInt(score) || 0;
            if (score >= 80) return 'good';
            if (score >= 50) return 'warning';
            return 'danger';
        }

        function rphubCfBadgeHtml(s) {
            if (s.cf_zone_status === 'active') return '<span class="rphub-cf-badge cf-active">CF</span>';
            if (s.cf_zone_id)                  return '<span class="rphub-cf-badge cf-pending">CF</span>';
            return '<span class="rphub-cf-badge cf-none">CF</span>';
        }

        function rphubSslBadgeHtml(s) {
            if (s.cf_zone_status === 'active') return '';
            const t = s.ssl_type || '';
            if (t === 'le')      return '<span class="rphub-ssl-badge ssl-le" title="Let\'s Encrypt">LE</span>';
            if (t === 'autossl') return '<span class="rphub-ssl-badge ssl-as" title="cPanel AutoSSL">AS</span>';
            if (t === 'paid')    return '<span class="rphub-ssl-badge ssl-paid" title="SSL de pago">$</span>';
            if (t === 'cf')      return '<span class="rphub-ssl-badge ssl-cf" title="Cloudflare SSL">CF</span>';
            if (t === 'none')    return '<span class="rphub-ssl-badge ssl-warn" title="Sin SSL">!</span>';
            if (s.url && s.url.startsWith('https://')) return '<span class="rphub-ssl-badge ssl-ok" title="HTTPS">S</span>';
            return '<span class="rphub-ssl-badge ssl-warn" title="Sin HTTPS">!</span>';
        }

        function rphubServerFlag(server) {
            if (!server) return '';
            const s = String(server).toLowerCase();
            if (s.includes('uk') || s.includes('gb') || s.includes('lon')) return 'GB';
            if (s.includes('us') || s.includes('ny') || s.includes('sf') || s.includes('la') || s.includes('dfw')) return 'US';
            if (s.includes('es') || s.includes('mad')) return 'ES';
            if (s.includes('de') || s.includes('fra') || s.includes('ger')) return 'DE';
            if (s.includes('fr') || s.includes('par')) return 'FR';
            return '';
        }

        function rphubScoreBarHtml(score, label) {
            const n   = parseInt(score) || 0;
            const cls = n >= 80 ? 'good' : (n >= 50 ? 'warning' : (n > 0 ? 'danger' : 'empty'));
            const num = n > 0 ? n : '-';
            return '<div><div class="rphub-audit-score-label">' + label + '</div>' +
                '<div class="rphub-score-bar-bg"><div class="rphub-score-bar-fill ' + cls + '" style="width:' + n + '%"></div></div>' +
                '<div class="rphub-audit-score-num ' + cls + '">' + num + '</div></div>';
        }

        function rphubBuildCard(s) {
            const id       = parseInt(s.id);
            const status   = escHtml(s.status || 'inactive');
            const name     = escHtml(s.name);
            const client   = escHtml(s.client_name || 'Sin cliente');
            const plan     = escHtml(s.plan || 'semilla');
            const planLabel = { semilla:'Semilla', raiz:'Ra\u00edz', ecosistema:'Ecosistema', basic:'Basic', advanced:'Est\u00e1ndar', premium:'Premium' };
            const health   = parseInt(s.health_score) || 0;
            const updates  = parseInt(s.updates_available) || parseInt(s.pending_updates_count) || 0;
            const security = parseInt(s.security_issues) || 0;
            const lastCheck = escHtml(s.last_check || s.last_seen || '');
            const phpVer   = s.php_version ? '<span class="rphub-version-pill"><b>PHP</b> ' + escHtml(s.php_version) + '</span>' : '';
            const wpVer    = s.wp_version  ? '<span class="rphub-version-pill"><b>WP</b> '  + escHtml(s.wp_version)  + '</span>' : '';
            const careVer  = s.care_version ? '<span class="rphub-version-pill"><b>Care</b> ' + escHtml(s.care_version) + '</span>' : '';
            const ecology  = s.trees_planted > 0 ? '<span class="rphub-ecology-pill">' + parseInt(s.trees_planted) + ' arboles</span>' : '';
            const server   = rphubServerFlag(s.server_region || s.whm_server_id || '');
            const serverSpan = server ? '<span style="font-size:11px;color:#646970;">' + server + '</span>' : '';

            return '<div class="rphub-card">' +
                '<div class="rphub-card-header">' +
                    '<span class="rphub-card-client">' + client + '</span>' +
                    '<div style="display:flex;align-items:center;gap:5px;">' +
                        rphubCfBadgeHtml(s) + rphubSslBadgeHtml(s) +
                        '<span class="rphub-card-status-dot ' + status + '" title="' + status + '"></span>' +
                    '</div>' +
                '</div>' +
                '<div class="rphub-card-body">' +
                    '<div class="rphub-card-name" title="' + name + '">' + name + '</div>' +
                    '<div class="rphub-card-url"><a href="' + escHtml(s.url) + '" target="_blank" rel="noopener">' + escHtml(s.url) + '</a></div>' +
                    '<div class="rphub-card-meta-row">' + serverSpan + ecology + '</div>' +
                    '<div class="rphub-card-versions">' + phpVer + wpVer + careVer + '</div>' +
                    '<div class="rphub-card-metrics">' +
                        '<div class="rphub-card-metric"><div class="rphub-card-metric-value ' + rphubHealthColor(health) + '">' + (health || '-') + '</div><div class="rphub-card-metric-label">Salud</div></div>' +
                        '<div class="rphub-card-metric"><div class="rphub-card-metric-value ' + (updates > 0 ? 'warning' : 'good') + '">' + updates + '</div><div class="rphub-card-metric-label">Updates</div></div>' +
                        '<div class="rphub-card-metric"><div class="rphub-card-metric-value ' + (security > 0 ? 'danger' : 'good') + '">' + security + '</div><div class="rphub-card-metric-label">Seguridad</div></div>' +
                    '</div>' +
                    '<div class="rphub-audit-scores">' +
                        rphubScoreBarHtml(s.seo_score, 'SEO') +
                        rphubScoreBarHtml(s.perf_score, 'Perf') +
                        rphubScoreBarHtml(s.cf_score, 'CF') +
                    '</div>' +
                '</div>' +
                '<div class="rphub-card-footer">' +
                    '<span class="rphub-card-plan-badge ' + plan + '">' + escHtml(planLabel[s.plan] || s.plan) + '</span>' +
                    '<div class="rphub-card-actions">' +
                        '<button class="button" onclick="rphubSyncSite(' + id + ')" title="Sincronizar"><span class="dashicons dashicons-update" style="margin-top:3px;"></span></button>' +
                        '<button class="button" onclick="rphubOpenSiteModal(' + id + ')" title="Editar"><span class="dashicons dashicons-edit" style="margin-top:3px;"></span></button>' +
                        '<button class="button" onclick="rphubCareUpgrade(' + id + ',this)" title="Actualizar Care"><span class="dashicons dashicons-cloud-upload" style="margin-top:3px;"></span></button>' +
                        '<button class="button" onclick="rphubAuditSite(' + id + ',this)" title="Auditar"><span class="dashicons dashicons-search" style="margin-top:3px;"></span></button>' +
                    '</div>' +
                    '<span class="rphub-last-check-text">' + lastCheck + '</span>' +
                '</div>' +
            '</div>';
        }

        // Restore last view on page load
        document.addEventListener('DOMContentLoaded', function() {
            var saved = '';
            try { saved = localStorage.getItem('rphubSitesView') || ''; } catch(e) {}
            if (saved === 'cards') rphubSetView('cards');
        });
        </script>
        <?php
    }

    private function render_care_deploy_panel() {
        $latest = get_option('rphub_care_latest_version', '');
        $site_count = $this->site_manager->get_sites_count();
        ?>
        <div class="rphub-care-deploy" style="background:#fff;border:1px solid #c3c4c7;border-left:4px solid #2271b1;padding:14px 18px;margin:16px 0;border-radius:4px;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                <div>
                    <strong style="font-size:14px;">Replanta Care &mdash; Despliegue</strong><br>
                    <span style="color:#646970;">Versi&oacute;n servida actualmente: <code><?php echo esc_html($latest ?: '-'); ?></code> &middot; <?php echo intval($site_count); ?> sitio(s) gestionado(s)</span>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="button" class="button" id="rphub-care-refresh-cache">
                        <span class="dashicons dashicons-update" style="vertical-align:middle;margin-top:3px;"></span>
                        Refrescar cach&eacute; desde GitHub
                    </button>
                    <button type="button" class="button button-primary" id="rphub-care-update-all">
                        <span class="dashicons dashicons-update-alt" style="vertical-align:middle;margin-top:3px;"></span>
                        Actualizar Care en todos los sitios
                    </button>
                </div>
            </div>
            <div id="rphub-care-deploy-log" style="margin-top:12px;font-family:monospace;font-size:12px;max-height:240px;overflow:auto;display:none;background:#f6f7f7;padding:10px;border-radius:3px;"></div>
        </div>
        <script>
        (function($){
            const log = function(line, status) {
                const $log = $('#rphub-care-deploy-log').show();
                const color = status === 'error' ? '#d63638' : (status === 'ok' ? '#00a32a' : '#1d2327');
                $log.append('<div style="color:' + color + ';">[' + new Date().toLocaleTimeString() + '] ' + line + '</div>');
                $log.scrollTop($log[0].scrollHeight);
            };
            $('#rphub-care-refresh-cache').on('click', function(){
                const $btn = $(this).prop('disabled', true);
                log('Solicitando ultima release de GitHub...');
                $.post(ajaxurl, { action: 'rphub_refresh_care_cache', nonce: rphub_ajax.nonce })
                    .done(function(r){
                        if (r.success) { log('Hub cacheo Care ' + (r.data.version || '?') + ' OK', 'ok'); setTimeout(function(){ location.reload(); }, 1200); }
                        else { log('Error: ' + (r.data || 'desconocido'), 'error'); }
                    })
                    .fail(function(x){ log('Error de red: ' + x.statusText, 'error'); })
                    .always(function(){ $btn.prop('disabled', false); });
            });
            $('#rphub-care-update-all').on('click', function(){
                if (!confirm('Esto disparara la actualizacion de Care en TODOS los sitios gestionados. Continuar?')) return;
                const sites = <?php
                    $all_sites_for_deploy = $this->site_manager->get_sites();
                    echo wp_json_encode(array_values(array_filter(array_map(function($s){ return $s->url ?? ''; }, $all_sites_for_deploy))));
                ?>;
                const $btn = $(this).prop('disabled', true);
                log('Actualizando ' + sites.length + ' sitio(s)...');
                let i = 0;
                const next = function(){
                    if (i >= sites.length) { log('Hecho.', 'ok'); $btn.prop('disabled', false); return; }
                    const url = sites[i++];
                    log('-> ' + url);
                    $.post(ajaxurl, { action: 'rphub_update_care_on_site', nonce: rphub_ajax.nonce, site_url: url })
                        .done(function(r){
                            if (r.success) {
                                const d = r.data || {};
                                const msg = d.upgraded ? ('actualizado ' + d.version_before + ' -> ' + d.version_after) : (d.message || 'ya estaba al dia');
                                log('  OK ' + msg, 'ok');
                            } else { log('  FAIL ' + (r.data || 'error'), 'error'); }
                        })
                        .fail(function(x){ log('  red: ' + x.statusText, 'error'); })
                        .always(next);
                };
                next();
            });
            $(document).on('click', '.rphub-update-care-btn', function(){
                const $btn = $(this);
                const url = $btn.data('site-url');
                if (!url) return;
                $btn.prop('disabled', true).text('Actualizando...');
                $.post(ajaxurl, { action: 'rphub_update_care_on_site', nonce: rphub_ajax.nonce, site_url: url })
                    .done(function(r){
                        if (r.success) {
                            const d = r.data || {};
                            $btn.text(d.upgraded ? ('OK ' + d.version_after) : 'Al dia');
                        } else { $btn.text('Error'); alert(r.data || 'Error'); }
                    })
                    .fail(function(){ $btn.text('Error de red'); })
                    .always(function(){ setTimeout(function(){ $btn.prop('disabled', false).text('Actualizar Care'); }, 3000); });
            });
        })(jQuery);
        </script>
        <?php
    }

    private function render_managed_sites_section() {
        $managed_sites = get_option('rphub_managed_sites', []);
        
        if (empty($managed_sites)) {
            echo '<div class="notice notice-info"><p>No hay sitios conectados al hub aún. Los sitios aparecerán aquí cuando se conecten automáticamente.</p></div>';
            return;
        }
        
        ?>
        <div class="rphub-card">
            <h2>Sitios Conectados al Hub</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Dominio</th>
                        <th>Plan</th>
                        <th>Estado</th>
                        <th>Registrado</th>
                        <th>Última Conexión</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($managed_sites as $domain => $site_data): ?>
                        <tr>
                            <td><strong><?php echo esc_html($domain); ?></strong></td>
                            <td>
                                <span class="rphub-plan-badge plan-<?php echo esc_attr($site_data['plan']); ?>">
                                    <?php echo esc_html(ucfirst($site_data['plan'])); ?>
                                </span>
                            </td>
                            <td>
                                <span class="rphub-status-badge status-<?php echo esc_attr($site_data['status'] ?? 'active'); ?>">
                                    <?php echo esc_html($site_data['status'] ?? 'Active'); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($site_data['registered_at'] ?? 'N/A'); ?></td>
                            <td><?php echo esc_html($site_data['last_seen'] ?? 'N/A'); ?></td>
                            <td>
                                <button class="button button-small" onclick="editSitePlan('<?php echo esc_js($domain); ?>', '<?php echo esc_js($site_data['plan']); ?>')">
                                    Editar Plan
                                </button>
                                <button class="button button-small button-link-delete" onclick="removeSite('<?php echo esc_js($domain); ?>')">
                                    Eliminar
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Toast Container -->
        <div id="rphub-toast-container" class="rphub-toast-container"></div>
        
        <style>
        .rphub-plan-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .plan-basic { background: #e3f2fd; color: #1976d2; }
        .plan-advanced { background: #f3e5f5; color: #7b1fa2; }
        .plan-premium { background: #fff3e0; color: #f57c00; }
        
        .rphub-status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-active { background: #e8f5e8; color: #2e7d32; }
        .status-inactive { background: #ffebee; color: #c62828; }
        
        /* Toast Notifications */
        .rphub-toast-container {
            position: fixed;
            top: 32px;
            right: 20px;
            z-index: 10000;
            pointer-events: none;
        }
        
        .rphub-toast {
            background: #fff;
            border-left: 4px solid;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            margin-bottom: 10px;
            max-width: 350px;
            opacity: 0;
            padding: 15px 20px;
            pointer-events: auto;
            position: relative;
            transform: translateX(100%);
            transition: all 0.3s ease;
        }
        
        .rphub-toast.show {
            opacity: 1;
            transform: translateX(0);
        }
        
        .rphub-toast.success {
            border-left-color: #46b450;
        }
        
        .rphub-toast.error {
            border-left-color: #dc3232;
        }
        
        .rphub-toast.warning {
            border-left-color: #ffb900;
        }
        
        .rphub-toast.info {
            border-left-color: #00a0d2;
        }
        
        .rphub-toast-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .rphub-toast-message {
            color: #666;
            font-size: 14px;
        }
        
        .rphub-toast-close {
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            font-size: 18px;
            position: absolute;
            right: 10px;
            top: 10px;
        }
        
        .rphub-toast-close:hover {
            color: #333;
        }

        /* ── Cards view ── */
        #rphub-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
            margin-top: 4px;
        }
        .rphub-card {
            background: #fff;
            border: 1px solid #dcdcde;
            border-radius: 8px;
            padding: 0;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
            transition: box-shadow .15s;
        }
        .rphub-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.1); }
        .rphub-card-header {
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #f0f0f1;
        }
        .rphub-card-client { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: #646970; font-weight: 600; }
        .rphub-card-status-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
        .rphub-card-status-dot.active   { background: #00a32a; }
        .rphub-card-status-dot.error    { background: #d63638; }
        .rphub-card-status-dot.inactive { background: #dcdcde; }
        .rphub-card-body { padding: 12px 14px; }
        .rphub-card-name { font-size: 15px; font-weight: 600; color: #1d2327; margin: 0 0 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .rphub-card-url  { font-size: 12px; color: #2271b1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .rphub-card-url a { color: inherit; text-decoration: none; }
        .rphub-card-url a:hover { text-decoration: underline; }
        .rphub-card-metrics {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
            margin: 12px 0;
        }
        .rphub-card-metric {
            text-align: center;
            background: #f6f7f7;
            border-radius: 6px;
            padding: 8px 4px;
        }
        .rphub-card-metric-value { font-size: 20px; font-weight: 700; line-height: 1; }
        .rphub-card-metric-label { font-size: 10px; color: #646970; margin-top: 3px; text-transform: uppercase; letter-spacing: .3px; }
        .rphub-card-metric-value.good    { color: #00a32a; }
        .rphub-card-metric-value.warning { color: #dba617; }
        .rphub-card-metric-value.danger  { color: #d63638; }
        .rphub-card-versions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        .rphub-version-pill {
            font-size: 11px;
            background: #f0f0f1;
            border-radius: 20px;
            padding: 2px 8px;
            color: #3c434a;
            white-space: nowrap;
        }
        .rphub-version-pill b { color: #1d2327; }
        .rphub-card-footer {
            padding: 8px 14px;
            background: #f6f7f7;
            border-top: 1px solid #f0f0f1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
        }
        .rphub-card-plan-badge {
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: .3px;
        }
        .rphub-card-plan-badge.semilla     { background:#e6f3ff; color:#2271b1; }
        .rphub-card-plan-badge.raiz        { background:#edfaef; color:#00a32a; }
        .rphub-card-plan-badge.ecosistema  { background:#fef8ee; color:#c67b00; }
        .rphub-card-plan-badge.basic       { background:#e6f3ff; color:#2271b1; }
        .rphub-card-plan-badge.advanced    { background:#edfaef; color:#00a32a; }
        .rphub-card-plan-badge.premium     { background:#fef8ee; color:#c67b00; }
        .rphub-card-actions { display: flex; gap: 4px; }
        .rphub-card-actions .button { padding: 0 6px; min-height: 26px; }
        .rphub-last-check-text { font-size: 11px; color: #646970; }
        .rphub-client-btn.active { background: #2271b1; border-color: #2271b1; color: #fff; }

        /* CF zone badge */
        .rphub-cf-badge { font-size: 11px; display: inline-flex; align-items: center; padding: 1px 6px; border-radius: 10px; font-weight: 600; white-space: nowrap; }
        .rphub-cf-badge.cf-active  { background: #e6f9eb; color: #00a32a; }
        .rphub-cf-badge.cf-pending { background: #fef8ee; color: #c67b00; }
        .rphub-cf-badge.cf-none    { background: #f0f0f1; color: #646970; }
        .rphub-ssl-badge { font-size: 11px; display: inline-flex; align-items: center; padding: 1px 6px; border-radius: 10px; font-weight: 600; white-space: nowrap; }
        .rphub-ssl-badge.ssl-le   { background: #e6f0fd; color: #1565c0; }
        .rphub-ssl-badge.ssl-as   { background: #ede7f6; color: #4527a0; }
        .rphub-ssl-badge.ssl-paid { background: #fff8e1; color: #f57f17; }
        .rphub-ssl-badge.ssl-cf   { background: #e6f9eb; color: #00a32a; }
        .rphub-ssl-badge.ssl-ok   { background: #e6f9eb; color: #00a32a; }
        .rphub-ssl-badge.ssl-warn { background: #ffebee; color: #c62828; }
        /* Card meta row (server flag + ecology) */
        .rphub-card-meta-row { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin: 4px 0 8px; font-size: 13px; }
        .rphub-ecology-pill { font-size: 11px; background: #edfaef; color: #00a32a; padding: 2px 7px; border-radius: 10px; }
        /* Audit score mini-bars */
        .rphub-audit-scores { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; margin: 10px 0 4px; }
        .rphub-audit-score-label { font-size: 10px; text-transform: uppercase; letter-spacing: .3px; color: #646970; margin-bottom: 3px; }
        .rphub-score-bar-bg   { background: #f0f0f1; border-radius: 3px; height: 4px; overflow: hidden; }
        .rphub-score-bar-fill { height: 100%; border-radius: 3px; transition: width .4s; }
        .rphub-score-bar-fill.good    { background: #00a32a; }
        .rphub-score-bar-fill.warning { background: #dba617; }
        .rphub-score-bar-fill.danger  { background: #d63638; }
        .rphub-score-bar-fill.empty   { background: #dcdcde; }
        .rphub-audit-score-num { font-size: 12px; font-weight: 700; margin-top: 3px; }
        .rphub-audit-score-num.good    { color: #00a32a; }
        .rphub-audit-score-num.warning { color: #dba617; }
        .rphub-audit-score-num.danger  { color: #d63638; }
        .rphub-audit-score-num.empty   { color: #646970; }
        </style>
        
        <script>
        function editSitePlan(domain, currentPlan) {
            const newPlan = prompt(`Cambiar plan para ${domain}.\nPlan actual: ${currentPlan}\n\nIngrese nuevo plan (semilla, raiz, ecosistema):`, currentPlan);
            if (newPlan && ['semilla', 'raiz', 'ecosistema'].includes(newPlan.toLowerCase())) {
                // Use jQuery instead of fetch to avoid CORS issues
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rphub_update_site_plan',
                        domain: domain,
                        plan: newPlan.toLowerCase(),
                        nonce: rphub_ajax.nonce
                    },
                    success: function(data) {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Error al actualizar el plan: ' + data.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', error);
                        alert('Error de conexión al actualizar el plan');
                    }
                });
            }
        }
        
        function removeSite(domain) {
            if (confirm(`¿Está seguro que desea eliminar ${domain} del hub?`)) {
                // Use jQuery instead of fetch to avoid CORS issues
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rphub_remove_site',
                        domain: domain,
                        nonce: rphub_ajax.nonce
                    },
                    success: function(data) {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Error al eliminar el sitio: ' + data.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', error);
                        alert('Error de conexión al eliminar el sitio');
                    }
                });
            }
        }

        /* ── Cards view ── */
        var rphubCardsData = null;
        var rphubActiveClient = '';

        function rphubSetView(mode) {
            const tableWrap  = document.querySelector('.rphub-sites-table-container');
            const bulkBar    = document.querySelector('.rphub-bulk-actions');
            const cardsView  = document.getElementById('rphub-cards-view');
            const btnTable   = document.getElementById('rphub-view-table');
            const btnCards   = document.getElementById('rphub-view-cards');

            if (mode === 'cards') {
                if (tableWrap)  tableWrap.style.display  = 'none';
                if (bulkBar)    bulkBar.style.display    = 'none';
                if (cardsView)  cardsView.style.display  = 'block';
                if (btnTable)   { btnTable.classList.remove('button-primary'); btnTable.classList.add('button'); }
                if (btnCards)   { btnCards.classList.remove('button'); btnCards.classList.add('button-primary'); }
                if (!rphubCardsData) rphubLoadCards();
            } else {
                if (tableWrap)  tableWrap.style.display  = 'block';
                if (bulkBar)    bulkBar.style.display    = 'block';
                if (cardsView)  cardsView.style.display  = 'none';
                if (btnTable)   { btnTable.classList.remove('button'); btnTable.classList.add('button-primary'); }
                if (btnCards)   { btnCards.classList.remove('button-primary'); btnCards.classList.add('button'); }
            }
            try { localStorage.setItem('rphubSitesView', mode); } catch(e) {}
        }

        function rphubLoadCards() {
            document.getElementById('rphub-cards-loading').style.display = 'block';
            document.getElementById('rphub-cards-grid').innerHTML = '';
            jQuery.post(ajaxurl, { action: 'rphub_get_sites_cards', nonce: rphub_ajax.nonce })
                .done(function(r) {
                    document.getElementById('rphub-cards-loading').style.display = 'none';
                    if (!r.success) { document.getElementById('rphub-cards-grid').innerHTML = '<p style="color:#d63638;">Error al cargar los sitios: ' + (r.data || '') + '</p>'; return; }
                    rphubCardsData = r.data;
                    rphubBuildClientFilter(r.data.clients);
                    rphubRenderCards(r.data.sites, rphubActiveClient);
                })
                .fail(function(xhr) {
                    // Fallback to REST endpoint (bypasses admin-ajax.php CF restrictions)
                    jQuery.ajax({
                        url: rphub_ajax.rest_url + 'sites-cards',
                        type: 'GET',
                        headers: { 'X-WP-Nonce': rphub_ajax.rest_nonce }
                    }).done(function(data) {
                        document.getElementById('rphub-cards-loading').style.display = 'none';
                        rphubCardsData = data;
                        rphubBuildClientFilter(data.clients);
                        rphubRenderCards(data.sites, rphubActiveClient);
                    }).fail(function() {
                        document.getElementById('rphub-cards-loading').style.display = 'none';
                        document.getElementById('rphub-cards-grid').innerHTML = '<p style="color:#d63638;">Error de red al cargar sitios. (HTTP ' + xhr.status + ')</p>';
                    });
                });
        }

        function rphubBuildClientFilter(clients) {
            const bar = document.getElementById('rphub-cards-client-filter');
            bar.innerHTML = '<span style="font-size:13px;color:#646970;">Cliente:</span>';
            const all = document.createElement('button');
            all.type = 'button';
            all.className = 'button rphub-client-btn' + (rphubActiveClient === '' ? ' button-primary' : '');
            all.textContent = 'Todos';
            all.onclick = function() { rphubFilterClient(this, ''); };
            bar.appendChild(all);
            clients.forEach(function(c) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'button rphub-client-btn' + (rphubActiveClient === c ? ' button-primary' : '');
                btn.textContent = c;
                btn.onclick = function() { rphubFilterClient(this, c); };
                bar.appendChild(btn);
            });
        }

        function rphubFilterClient(btn, client) {
            rphubActiveClient = client;
            document.querySelectorAll('.rphub-client-btn').forEach(function(b) {
                b.classList.remove('button-primary'); b.classList.add('button');
            });
            btn.classList.remove('button'); btn.classList.add('button-primary');
            if (rphubCardsData) rphubRenderCards(rphubCardsData.sites, client);
        }

        function rphubRenderCards(sites, clientFilter) {
            const grid = document.getElementById('rphub-cards-grid');
            const filtered = clientFilter ? sites.filter(function(s) { return (s.client_name || 'Sin cliente') === clientFilter; }) : sites;
            if (!filtered.length) { grid.innerHTML = '<p style="color:#646970;padding:20px 0;">No hay sitios para mostrar.</p>'; return; }
            grid.innerHTML = filtered.map(rphubBuildCard).join('');
        }

        function escHtml(str) {
            return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        function rphubHealthColor(score) {
            score = parseInt(score) || 0;
            if (score >= 80) return 'good';
            if (score >= 50) return 'warning';
            return 'danger';
        }

        function rphubCfBadgeHtml(s) {
            if (s.cf_zone_status === 'active') return '<span class="rphub-cf-badge cf-active">CF</span>';
            if (s.cf_zone_id)                  return '<span class="rphub-cf-badge cf-pending">CF</span>';
            return '<span class="rphub-cf-badge cf-none">CF</span>';
        }

        function rphubSslBadgeHtml(s) {
            // CF active → SSL is implicit (CF Universal SSL), no separate badge needed
            if (s.cf_zone_status === 'active') return '';
            // Known ssl_type from Care metrics
            const t = s.ssl_type || '';
            if (t === 'le')      return '<span class="rphub-ssl-badge ssl-le"  title="Let\'s Encrypt">LE</span>';
            if (t === 'autossl') return '<span class="rphub-ssl-badge ssl-as"  title="cPanel AutoSSL">AS</span>';
            if (t === 'paid')    return '<span class="rphub-ssl-badge ssl-paid" title="SSL de pago">$</span>';
            if (t === 'cf')      return '<span class="rphub-ssl-badge ssl-cf"  title="Cloudflare SSL">CF</span>';
            if (t === 'none')    return '<span class="rphub-ssl-badge ssl-warn" title="Sin SSL detectado">⚠</span>';
            // Fallback: derive from URL
            if (s.url && s.url.startsWith('https://')) return '<span class="rphub-ssl-badge ssl-ok" title="HTTPS">🔒</span>';
            return '<span class="rphub-ssl-badge ssl-warn" title="Sin HTTPS">⚠</span>';
        }

        function rphubServerFlag(server) {
            if (!server) return '';
            const s = String(server).toLowerCase();
            if (s.includes('uk') || s.includes('gb') || s.includes('lon')) return '\uD83C\uDDEC\uD83C\uDDE7';
            if (s.includes('us') || s.includes('ny') || s.includes('sf') || s.includes('la') || s.includes('dfw')) return '\uD83C\uDDFA\uD83C\uDDF8';
            if (s.includes('es') || s.includes('mad')) return '\uD83C\uDDEA\uD83C\uDDF8';
            if (s.includes('de') || s.includes('fra') || s.includes('ger')) return '\uD83C\uDDE9\uD83C\uDDEA';
            if (s.includes('fr') || s.includes('par')) return '\uD83C\uDDEB\uD83C\uDDF7';
            return '\uD83D\uDDA5';
        }

        function rphubScoreBarHtml(score, label) {
            const n   = parseInt(score) || 0;
            const cls = n >= 80 ? 'good' : (n >= 50 ? 'warning' : (n > 0 ? 'danger' : 'empty'));
            const num = n > 0 ? n : '\u2013';
            return '<div><div class="rphub-audit-score-label">' + label + '</div>' +
                '<div class="rphub-score-bar-bg"><div class="rphub-score-bar-fill ' + cls + '" style="width:' + n + '%"></div></div>' +
                '<div class="rphub-audit-score-num ' + cls + '">' + num + '</div></div>';
        }

        function rphubBuildCard(s) {
            const statusClass  = (s.status === 'active') ? 'active' : (s.status === 'error' ? 'error' : 'inactive');
            const healthClass  = rphubHealthColor(s.health_score);
            const updClass     = parseInt(s.pending_updates_count) > 0 ? 'warning' : 'good';
            const secClass     = parseInt(s.security_issues) > 0 ? 'danger' : 'good';
            const planLabel    = { semilla:'Semilla', raiz:'Ra\u00edz', ecosistema:'Ecosistema', basic:'Basic', advanced:'Est\u00e1ndar', premium:'Premium' };
            const plan         = escHtml(s.plan || 'semilla');
            const client       = escHtml(s.client_name || 'Sin cliente');
            const name         = escHtml(s.name);
            const url          = escHtml(s.url);
            const id           = parseInt(s.id);

            const cfBadge  = rphubCfBadgeHtml(s);
            const sslBadge = rphubSslBadgeHtml(s);
            const flag    = rphubServerFlag(s.whm_server);
            const trees   = parseInt(s.trees_planted) || 0;
            const co2     = parseFloat(s.co2_evaded)  || 0;

            let metaHtml = '';
            if (flag || trees > 0 || co2 > 0) {
                metaHtml = '<div class="rphub-card-meta-row">';
                if (flag) metaHtml += '<span title="' + escHtml(s.whm_server || '') + '">' + flag + '</span>';
                if (trees > 0) metaHtml += '<span class="rphub-ecology-pill">\uD83C\uDF3F ' + trees + ' \u00e1rbol' + (trees !== 1 ? 'es' : '') + '</span>';
                else if (co2 > 0) metaHtml += '<span class="rphub-ecology-pill">\uD83C\uDF3F ' + co2 + ' kg CO\u2082</span>';
                metaHtml += '</div>';
            }

            const auditHtml = '<div class="rphub-audit-scores">' +
                rphubScoreBarHtml(s.cf_score,   'CF')  +
                rphubScoreBarHtml(s.seo_score,  'SEO') +
                rphubScoreBarHtml(s.perf_score, 'WPO') +
                '</div>';

            const cfFixBtn = s.cf_zone_id
                ? `<button class="button" onclick="rphubApplyPlanFixes(${id},'${plan}',this)" title="Aplicar Fixes CF"><span class="dashicons dashicons-shield" style="margin-top:3px;"></span></button>`
                : '';

            return `<div class="rphub-card">
                <div class="rphub-card-header">
                    <span class="rphub-card-client">${client}</span>
                    <div style="display:flex;align-items:center;gap:6px;">${cfBadge}${sslBadge}<span class="rphub-card-status-dot ${statusClass}" title="${escHtml(s.status)}"></span></div>
                </div>
                <div class="rphub-card-body">
                    <p class="rphub-card-name" title="${name}">${name}</p>
                    <p class="rphub-card-url"><a href="${url}" target="_blank" rel="noopener">${url}</a></p>
                    ${metaHtml}
                    <div class="rphub-card-metrics">
                        <div class="rphub-card-metric">
                            <div class="rphub-card-metric-value ${healthClass}">${escHtml(s.health_score)}%</div>
                            <div class="rphub-card-metric-label">Salud</div>
                        </div>
                        <div class="rphub-card-metric">
                            <div class="rphub-card-metric-value ${updClass}">${escHtml(s.pending_updates_count)}</div>
                            <div class="rphub-card-metric-label">Updates</div>
                        </div>
                        <div class="rphub-card-metric">
                            <div class="rphub-card-metric-value ${secClass}">${escHtml(s.security_issues)}</div>
                            <div class="rphub-card-metric-label">Seguridad</div>
                        </div>
                    </div>
                    ${auditHtml}
                    <div class="rphub-card-versions">
                        <span class="rphub-version-pill"><b>WP</b> ${escHtml(s.wp_version)}</span>
                        <span class="rphub-version-pill"><b>PHP</b> ${escHtml(s.php_version)}</span>
                        <span class="rphub-version-pill"><b>Care</b> ${escHtml(s.care_version)}</span>
                    </div>
                </div>
                <div class="rphub-card-footer">
                    <span class="rphub-card-plan-badge ${plan}">${escHtml(planLabel[s.plan] || s.plan)}</span>
                    <div class="rphub-card-actions">
                        <button class="button" onclick="rphubSyncSite(${id})" title="Sincronizar"><span class="dashicons dashicons-update" style="margin-top:3px;"></span></button>
                        <button class="button" onclick="rphubOpenSiteModal(${id})" title="Editar"><span class="dashicons dashicons-edit" style="margin-top:3px;"></span></button>
                        <button class="button" onclick="rphubCareUpgrade(${id},this)" title="Actualizar Care"><span class="dashicons dashicons-cloud-upload" style="margin-top:3px;"></span></button>
                        <button class="button" onclick="rphubAuditSite(${id},this)" title="Auditar"><span class="dashicons dashicons-search" style="margin-top:3px;"></span></button>
                        ${cfFixBtn}
                        <button class="button" onclick="rphubSyncDR(${id},this)" title="Sync DR"><span class="dashicons dashicons-database" style="margin-top:3px;"></span></button>
                    </div>
                </div>
            </div>`;
        }

        function rphubCareUpgrade(siteId, btn) {
            if (!confirm('¿Actualizar Replanta Care en este sitio?')) return;
            btn.disabled = true;
            jQuery.post(ajaxurl, { action: 'rphub_trigger_care_upgrade', nonce: rphub_ajax.nonce, site_id: siteId })
                .done(function(r) {
                    btn.disabled = false;
                    if (r.success) {
                        const d = r.data || {};
                        alert(d.message || 'Actualización completada.');
                        rphubCardsData = null;
                        rphubLoadCards();
                    } else {
                        alert('Error: ' + (r.data || 'desconocido'));
                    }
                })
                .fail(function(x) { btn.disabled = false; alert('Error de red: ' + x.statusText); });
        }

        function rphubAuditSite(siteId, btn) {
            btn.disabled = true;
            jQuery.post(ajaxurl, { action: 'rphub_run_site_audit', nonce: rphub_ajax.nonce, site_id: siteId, force: 1 })
                .done(function(r) {
                    btn.disabled = false;
                    if (r.success) {
                        rphubCardsData = null;
                        rphubLoadCards();
                    } else {
                        alert('Error al auditar: ' + (r.data || 'desconocido'));
                    }
                })
                .fail(function(x) { btn.disabled = false; alert('Error de red: ' + x.statusText); });
        }

        function rphubApplyPlanFixes(siteId, plan, btn) {
            if (!confirm('¿Aplicar correcciones CF del plan ' + plan + ' en este sitio?')) return;
            btn.disabled = true;
            jQuery.post(ajaxurl, { action: 'rphub_apply_plan_cf_fixes', nonce: rphub_ajax.nonce, site_id: siteId })
                .done(function(r) {
                    btn.disabled = false;
                    if (r.success) {
                        const res = r.data.results || {};
                        const ok  = Object.values(res).filter(function(x) { return x.success; }).length;
                        const err = Object.values(res).filter(function(x) { return !x.success; }).length;
                        alert('CF Fixes: ' + ok + ' OK, ' + err + ' errores.');
                    } else {
                        alert('Error: ' + (r.data || 'desconocido'));
                    }
                })
                .fail(function(x) { btn.disabled = false; alert('Error de red: ' + x.statusText); });
        }

        function rphubSyncDR(siteId, btn) {
            btn.disabled = true;
            jQuery.post(ajaxurl, { action: 'rphub_enrich_site_from_dr', nonce: rphub_ajax.nonce, site_id: siteId })
                .done(function(r) {
                    btn.disabled = false;
                    if (r.success) {
                        rphubCardsData = null;
                        rphubLoadCards();
                    } else {
                        alert('Error al sincronizar DR: ' + (r.data || 'desconocido'));
                    }
                })
                .fail(function(x) { btn.disabled = false; alert('Error de red: ' + x.statusText); });
        }

        // Restore last view on page load
        jQuery(function() {
            try {
                var saved = localStorage.getItem('rphubSitesView');
                if (saved === 'cards') rphubSetView('cards');
            } catch(e) {}
        });
        </script>
        <?php
    }
}
