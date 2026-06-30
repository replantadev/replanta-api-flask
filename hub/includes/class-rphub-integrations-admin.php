<?php
/**
 * Replanta Hub — Integrations admin page.
 *
 * Provides:
 *  - Google OAuth flow (client_id/secret form + Connect button + callback handler)
 *  - Cloudflare API token form
 *  - PageSpeed Insights API key form
 *  - Per-site mapping panel: choose GA4 property / SC site / CF zone for each managed site
 *
 * Menu registered as subpage under "Replanta Hub" with slug `replanta-hub-integrations`.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPHUB_Integrations_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu'], 20);
        add_action('admin_init', [$this, 'maybe_handle_actions']);
        add_action('wp_ajax_rphub_save_integration', [$this, 'ajax_save_integration']);
        add_action('wp_ajax_rphub_save_site_mapping', [$this, 'ajax_save_site_mapping']);
        add_action('wp_ajax_rphub_list_integration_options', [$this, 'ajax_list_options']);
    }

    public function register_menu() {
        add_submenu_page(
            'replanta-hub',
            'Integraciones',
            'Integraciones',
            'manage_options',
            'replanta-hub-integrations',
            [$this, 'render']
        );
    }

    public function maybe_handle_actions() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'replanta-hub-integrations') return;
        $action = $_GET['action'] ?? '';
        if ($action === 'google_connect') {
            $url = RPHUB_Integrations::get_google_authorization_url();
            if (empty($url)) {
                wp_die('Configura primero el Client ID / Client Secret de Google.');
            }
            wp_redirect($url);
            exit;
        }
        if ($action === 'google_callback' && !empty($_GET['code'])) {
            $result = RPHUB_Integrations::exchange_google_code(sanitize_text_field($_GET['code']));
            $flag = is_wp_error($result) ? 'google_error' : 'google_ok';
            wp_redirect(add_query_arg([
                'page'  => 'replanta-hub-integrations',
                $flag   => 1,
                'msg'   => is_wp_error($result) ? rawurlencode($result->get_error_message()) : '',
            ], admin_url('admin.php')));
            exit;
        }
        if ($action === 'google_disconnect') {
            check_admin_referer('rphub_google_disconnect');
            RPHUB_Integrations::disconnect_google();
            wp_redirect(admin_url('admin.php?page=replanta-hub-integrations&google_disconnected=1'));
            exit;
        }
    }

    public function ajax_save_integration() {
        check_ajax_referer('rphub_integrations', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Sin permisos', 403);

        $type = sanitize_text_field($_POST['type'] ?? '');
        switch ($type) {
            case 'google':
                RPHUB_Integrations::save_google_oauth_config(
                    $_POST['client_id'] ?? '',
                    $_POST['client_secret'] ?? '',
                    $_POST['redirect_uri'] ?? ''
                );
                wp_send_json_success(['message' => 'Google guardado']);
                break;
            case 'cloudflare':
                RPHUB_Integrations::save_cloudflare_token($_POST['token'] ?? '');
                wp_send_json_success(['message' => 'Cloudflare guardado']);
                break;
            case 'psi':
                RPHUB_Integrations::save_psi_key($_POST['key'] ?? '');
                wp_send_json_success(['message' => 'PSI guardado']);
                break;
            default:
                wp_send_json_error('Tipo desconocido', 400);
        }
    }

    public function ajax_list_options() {
        check_ajax_referer('rphub_integrations', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Sin permisos', 403);

        $type = sanitize_text_field($_POST['type'] ?? '');
        $list = null;
        switch ($type) {
            case 'ga4': $list = RPHUB_Integrations::list_ga4_properties(); break;
            case 'sc':  $list = RPHUB_Integrations::list_sc_sites(); break;
            case 'cf':  $list = RPHUB_Integrations::list_cloudflare_zones(); break;
            default:    wp_send_json_error('Tipo desconocido', 400);
        }
        if (is_wp_error($list)) wp_send_json_error($list->get_error_message(), 502);
        wp_send_json_success(['items' => $list]);
    }

    public function ajax_save_site_mapping() {
        check_ajax_referer('rphub_integrations', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Sin permisos', 403);
        $site_id = sanitize_text_field($_POST['site_id'] ?? '');
        $integrations = [
            'ga4_property_id' => sanitize_text_field($_POST['ga4_property_id'] ?? ''),
            'sc_site_url'     => esc_url_raw($_POST['sc_site_url'] ?? ''),
            'cf_zone_id'      => sanitize_text_field($_POST['cf_zone_id'] ?? ''),
        ];
        $ok = RPHUB_Integrations::save_site_mapping($site_id, $integrations);
        if (!$ok) wp_send_json_error('Sitio no encontrado', 404);
        wp_send_json_success(['integrations' => $integrations]);
    }

    public function render() {
        if (!current_user_can('manage_options')) return;

        $google_cfg     = RPHUB_Integrations::get_google_oauth_config();
        $google_email   = RPHUB_Integrations::get_google_account_email();
        $google_active  = RPHUB_Integrations::is_google_connected();
        $cf_token       = RPHUB_Integrations::get_cloudflare_token();
        $psi_key        = RPHUB_Integrations::get_psi_key();
        $sites          = get_option('rphub_managed_sites', []);
        $nonce          = wp_create_nonce('rphub_integrations');
        ?>
        <div class="wrap rphub-integrations">
            <h1>Integraciones</h1>

            <?php if (!empty($_GET['google_ok'])): ?>
                <div class="notice notice-success"><p>Google conectado correctamente.</p></div>
            <?php elseif (!empty($_GET['google_error'])): ?>
                <div class="notice notice-error"><p>Error conectando con Google: <?php echo esc_html(urldecode($_GET['msg'] ?? '')); ?></p></div>
            <?php elseif (!empty($_GET['google_disconnected'])): ?>
                <div class="notice notice-info"><p>Google desconectado.</p></div>
            <?php endif; ?>

            <style>
                .rphub-card { background:#fff; border:1px solid #e2e6ec; border-radius:12px; padding:24px; margin-bottom:20px; box-shadow:0 1px 2px rgba(0,0,0,.04); }
                .rphub-card h2 { margin-top:0; display:flex; align-items:center; gap:10px; }
                .rphub-dot { width:10px; height:10px; border-radius:50%; display:inline-block; }
                .rphub-dot.on { background:#10b981; box-shadow:0 0 0 3px rgba(16,185,129,.18); }
                .rphub-dot.off { background:#9ca3af; box-shadow:0 0 0 3px rgba(156,163,175,.15); }
                .rphub-row { display:grid; grid-template-columns: 200px 1fr; gap:12px; margin-bottom:12px; align-items:center; }
                .rphub-row input[type=text], .rphub-row input[type=password], .rphub-row select { width:100%; max-width:540px; }
                .rphub-actions { display:flex; gap:10px; margin-top:12px; }
                table.rphub-sites { width:100%; border-collapse:collapse; }
                table.rphub-sites th, table.rphub-sites td { padding:10px; border-bottom:1px solid #eef0f3; text-align:left; vertical-align:middle; }
                table.rphub-sites select { min-width:220px; }
                .rphub-mute { color:#6b7280; font-size:12px; }
            </style>

            <div class="rphub-card">
                <h2><span class="rphub-dot <?php echo $google_active ? 'on' : 'off'; ?>"></span> Google (Analytics 4 + Search Console)</h2>
                <p>Conecta una única cuenta de Google con acceso a las propiedades de tus clientes. El refresh token se cifra en BBDD.</p>
                <div class="rphub-row"><label>Client ID</label><input type="text" id="g-client-id" value="<?php echo esc_attr($google_cfg['client_id']); ?>"></div>
                <div class="rphub-row"><label>Client Secret</label><input type="password" id="g-client-secret" value="<?php echo esc_attr($google_cfg['client_secret']); ?>"></div>
                <div class="rphub-row"><label>Redirect URI</label><input type="text" id="g-redirect-uri" value="<?php echo esc_attr($google_cfg['redirect_uri']); ?>" readonly><span class="rphub-mute">Cópialo en la consola de Google Cloud</span></div>
                <div class="rphub-actions">
                    <button class="button button-primary" data-rphub-save="google">Guardar credenciales</button>
                    <?php if ($google_active): ?>
                        <span class="rphub-mute">Conectado como <strong><?php echo esc_html($google_email); ?></strong></span>
                        <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=replanta-hub-integrations&action=google_disconnect'), 'rphub_google_disconnect')); ?>">Desconectar</a>
                    <?php else: ?>
                        <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=replanta-hub-integrations&action=google_connect')); ?>">Conectar con Google</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="rphub-card">
                <h2><span class="rphub-dot <?php echo !empty($cf_token) ? 'on' : 'off'; ?>"></span> Cloudflare</h2>
                <p>API token con permisos <code>Account: Read</code> y <code>Zone: All - Read</code> (mínimo). Para gestión avanzada añade <code>Zone Settings: Edit</code>, <code>Cache Purge</code>, <code>Page Rules: Edit</code>.</p>
                <div class="rphub-row"><label>API Token</label><input type="password" id="cf-token" value="<?php echo esc_attr($cf_token); ?>"></div>
                <div class="rphub-actions"><button class="button button-primary" data-rphub-save="cloudflare">Guardar token</button></div>
            </div>

            <div class="rphub-card">
                <h2><span class="rphub-dot <?php echo !empty($psi_key) ? 'on' : 'off'; ?>"></span> PageSpeed Insights</h2>
                <p>API key (opcional). Sin key funciona pero con cuota baja. Crea una en console.cloud.google.com → APIs → PSI.</p>
                <div class="rphub-row"><label>API Key</label><input type="password" id="psi-key" value="<?php echo esc_attr($psi_key); ?>"></div>
                <div class="rphub-actions"><button class="button button-primary" data-rphub-save="psi">Guardar key</button></div>
            </div>

            <div class="rphub-card">
                <h2>Asignación por sitio</h2>
                <p>Por cada sitio gestionado, elige qué propiedad GA4, qué sitio de Search Console y qué zona Cloudflare le corresponden. El Hub usará esta asignación al servir métricas a Care.</p>
                <table class="rphub-sites">
                    <thead><tr><th>Sitio</th><th>GA4</th><th>Search Console</th><th>Cloudflare</th><th></th></tr></thead>
                    <tbody>
                    <?php if (empty($sites)): ?>
                        <tr><td colspan="5" class="rphub-mute">Aún no hay sitios gestionados.</td></tr>
                    <?php else: foreach ($sites as $id => $site):
                        $i = $site['integrations'] ?? []; ?>
                        <tr data-site-id="<?php echo esc_attr($id); ?>">
                            <td><strong><?php echo esc_html($site['url'] ?? $id); ?></strong></td>
                            <td><select class="rphub-mapping" data-key="ga4_property_id" data-source="ga4"><option value="">— sin asignar —</option><?php if (!empty($i['ga4_property_id'])): ?><option value="<?php echo esc_attr($i['ga4_property_id']); ?>" selected><?php echo esc_html($i['ga4_property_id']); ?></option><?php endif; ?></select></td>
                            <td><select class="rphub-mapping" data-key="sc_site_url" data-source="sc"><option value="">— sin asignar —</option><?php if (!empty($i['sc_site_url'])): ?><option value="<?php echo esc_attr($i['sc_site_url']); ?>" selected><?php echo esc_html($i['sc_site_url']); ?></option><?php endif; ?></select></td>
                            <td><select class="rphub-mapping" data-key="cf_zone_id" data-source="cf"><option value="">— sin asignar —</option><?php if (!empty($i['cf_zone_id'])): ?><option value="<?php echo esc_attr($i['cf_zone_id']); ?>" selected><?php echo esc_html($i['cf_zone_id']); ?></option><?php endif; ?></select></td>
                            <td><button class="button button-primary rphub-save-mapping">Guardar</button></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <script>
            (function($){
                const ajaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
                const nonce   = '<?php echo esc_js($nonce); ?>';
                const lists   = {ga4: null, sc: null, cf: null};

                function loadList(type, cb){
                    if (lists[type]) return cb(lists[type]);
                    $.post(ajaxUrl, {action:'rphub_list_integration_options', nonce, type}, function(r){
                        if (r.success){ lists[type] = r.data.items; cb(r.data.items); }
                        else cb([]);
                    }).fail(()=>cb([]));
                }

                $(document).on('click', '[data-rphub-save]', function(){
                    const type = $(this).data('rphub-save');
                    const data = {action:'rphub_save_integration', nonce, type};
                    if (type==='google'){
                        data.client_id     = $('#g-client-id').val();
                        data.client_secret = $('#g-client-secret').val();
                        data.redirect_uri  = $('#g-redirect-uri').val();
                    } else if (type==='cloudflare'){
                        data.token = $('#cf-token').val();
                    } else if (type==='psi'){
                        data.key = $('#psi-key').val();
                    }
                    const btn = $(this).prop('disabled', true).text('Guardando…');
                    $.post(ajaxUrl, data, function(r){
                        btn.prop('disabled', false).text('Guardar');
                        if (r.success) alert('Guardado'); else alert(r.data || 'Error');
                    }).fail(()=>{ btn.prop('disabled', false).text('Guardar'); alert('Error de red'); });
                });

                $('.rphub-mapping').on('focus', function(){
                    const $sel = $(this);
                    if ($sel.data('loaded')) return;
                    const source = $sel.data('source');
                    loadList(source, function(items){
                        items.forEach(it=>{
                            let val, label;
                            if (source==='ga4'){ val = it.property_id; label = it.display + ' ('+it.property_id+')'; }
                            else if (source==='sc'){ val = it.site_url; label = it.site_url; }
                            else { val = it.zone_id; label = it.name + ' ('+it.status+')'; }
                            if ($sel.find('option[value="'+val+'"]').length === 0) {
                                $sel.append($('<option>').val(val).text(label));
                            }
                        });
                        $sel.data('loaded', true);
                    });
                });

                $(document).on('click', '.rphub-save-mapping', function(){
                    const $row = $(this).closest('tr');
                    const data = {
                        action:'rphub_save_site_mapping', nonce,
                        site_id: $row.data('site-id'),
                        ga4_property_id: $row.find('[data-key="ga4_property_id"]').val(),
                        sc_site_url:     $row.find('[data-key="sc_site_url"]').val(),
                        cf_zone_id:      $row.find('[data-key="cf_zone_id"]').val(),
                    };
                    const btn = $(this).prop('disabled', true).text('Guardando…');
                    $.post(ajaxUrl, data, function(r){
                        btn.prop('disabled', false).text('Guardar');
                        if (!r.success) alert(r.data || 'Error');
                    }).fail(()=>{ btn.prop('disabled', false).text('Guardar'); alert('Error de red'); });
                });
            })(jQuery);
            </script>
        </div>
        <?php
    }
}

new RPHUB_Integrations_Admin();
