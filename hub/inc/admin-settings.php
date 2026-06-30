<?php
/**
 * Admin Settings Page
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPHUB_Admin_Settings {
    
    public function render() {
        // Handle form submission
        if (isset($_POST['submit_settings']) && wp_verify_nonce($_POST['settings_nonce'], 'rphub_settings')) {
            $this->save_settings();
        }
        if (isset($_POST['check_plugin_updates']) && wp_verify_nonce($_POST['settings_nonce'], 'rphub_settings')) {
            $this->force_plugin_update_check();
        }
        
        // Get current settings
        $settings = get_option('rphub_settings', array());
        $default_settings = $this->get_default_settings();
        $settings = wp_parse_args($settings, $default_settings);
        ?>
        <div class="wrap rphub-settings">
            <h1>Configuración de Replanta Hub</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('rphub_settings', 'settings_nonce'); ?>
                
                <div class="rphub-settings-tabs">
                    <button type="button" class="rphub-tab-button active" data-tab="general">General</button>
                    <button type="button" class="rphub-tab-button" data-tab="api">API</button>
                    <button type="button" class="rphub-tab-button" data-tab="notifications">Notificaciones</button>
                    <button type="button" class="rphub-tab-button" data-tab="whm">WHM</button>
                    <button type="button" class="rphub-tab-button" data-tab="reports">Reportes</button>
                    <button type="button" class="rphub-tab-button" data-tab="tasks">Tareas</button>
                    <button type="button" class="rphub-tab-button" data-tab="backup">Backup</button>
                </div>
                
                <!-- General Settings -->
                <div class="rphub-tab-content active" id="tab-general">
                    <h2>Configuración General</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Nombre del Hub</th>
                            <td>
                                <input type="text" name="settings[hub_name]" value="<?php echo esc_attr($settings['hub_name']); ?>" class="regular-text" />
                                <p class="description">Nombre identificativo de este hub.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Timeout de conexión</th>
                            <td>
                                <input type="number" name="settings[connection_timeout]" value="<?php echo esc_attr($settings['connection_timeout']); ?>" min="10" max="300" />
                                <p class="description">Tiempo límite para conexiones (segundos).</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Máximo sitios por página</th>
                            <td>
                                <input type="number" name="settings[sites_per_page]" value="<?php echo esc_attr($settings['sites_per_page']); ?>" min="10" max="100" />
                                <p class="description">Número de sitios a mostrar por página.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Modo debug</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="settings[debug_mode]" value="1" <?php checked($settings['debug_mode']); ?> />
                                    Activar modo debug
                                </label>
                                <p class="description">Registra información adicional en los logs.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Limpieza automática de logs</th>
                            <td>
                                <select name="settings[log_cleanup_days]">
                                    <option value="7" <?php selected($settings['log_cleanup_days'], '7'); ?>>7 días</option>
                                    <option value="14" <?php selected($settings['log_cleanup_days'], '14'); ?>>14 días</option>
                                    <option value="30" <?php selected($settings['log_cleanup_days'], '30'); ?>>30 días</option>
                                    <option value="60" <?php selected($settings['log_cleanup_days'], '60'); ?>>60 días</option>
                                    <option value="90" <?php selected($settings['log_cleanup_days'], '90'); ?>>90 días</option>
                                </select>
                                <p class="description">Eliminar logs automáticamente después de este período.</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- API Settings -->
                <div class="rphub-tab-content" id="tab-api">
                    <h2>Configuración de API</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Clave secreta JWT</th>
                            <td>
                                <input type="text" name="settings[jwt_secret]" value="<?php echo esc_attr($settings['jwt_secret']); ?>" class="large-text" />
                                <button type="button" class="button" onclick="rphubGenerateJWTSecret()">Generar Nueva</button>
                                <p class="description">Clave para firmar tokens JWT. <strong>¡Importante!</strong> Cambiar esta clave desconectará todos los sitios.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Expiración de tokens</th>
                            <td>
                                <select name="settings[jwt_expiration]">
                                    <option value="3600" <?php selected($settings['jwt_expiration'], '3600'); ?>>1 hora</option>
                                    <option value="86400" <?php selected($settings['jwt_expiration'], '86400'); ?>>24 horas</option>
                                    <option value="604800" <?php selected($settings['jwt_expiration'], '604800'); ?>>7 días</option>
                                    <option value="2592000" <?php selected($settings['jwt_expiration'], '2592000'); ?>>30 días</option>
                                </select>
                                <p class="description">Tiempo de vida de los tokens JWT.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Verificar SSL</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="settings[verify_ssl]" value="1" <?php checked($settings['verify_ssl']); ?> />
                                    Verificar certificados SSL en las conexiones
                                </label>
                                <p class="description">Desactivar solo para entornos de desarrollo.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">User Agent</th>
                            <td>
                                <input type="text" name="settings[user_agent]" value="<?php echo esc_attr($settings['user_agent']); ?>" class="large-text" />
                                <p class="description">User Agent para las peticiones HTTP.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Máximo reintentos</th>
                            <td>
                                <input type="number" name="settings[max_retries]" value="<?php echo esc_attr($settings['max_retries']); ?>" min="1" max="10" />
                                <p class="description">Número máximo de reintentos para conexiones fallidas.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Token GitHub (updates)</th>
                            <td>
                                <input type="password" name="settings[github_token]" value="<?php echo esc_attr($settings['github_token']); ?>" class="large-text" autocomplete="off" />
                                <p class="description">Token para repositorio privado de actualizaciones de Replanta Hub.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Deploy Token</th>
                            <td>
                                <?php
                                $deploy_token = get_option('rphub_deploy_token', '');
                                if (empty($deploy_token)) {
                                    $deploy_token = wp_generate_password(32, false);
                                    update_option('rphub_deploy_token', $deploy_token);
                                }
                                ?>
                                <input type="text" id="rphub-deploy-token" value="<?php echo esc_attr($deploy_token); ?>" class="large-text" readonly style="font-family:monospace;background:#f9f9f9;" />
                                <button type="button" class="button" onclick="navigator.clipboard.writeText(document.getElementById('rphub-deploy-token').value).then(()=>{this.textContent='¡Copiado!';setTimeout(()=>{this.textContent='Copiar'},2000)})">Copiar</button>
                                <p class="description">Añade este valor como secret <code>HUB_DEPLOY_TOKEN</code> en los repos de GitHub de Care y Hub para activar el deploy automático.</p>
                            </td>
                        </tr>
                    </table>

                    <h3 style="margin-top:28px;border-top:1px solid #ddd;padding-top:20px;">Google APIs</h3>
                    <table class="form-table">
                        <?php
                        $g_client_id     = get_option('replanta_hub_google_client_id', '');
                        $g_client_secret = get_option('replanta_hub_google_client_secret', '');
                        $g_api_key       = get_option('replanta_hub_google_api_key', '');
                        $rum_enabled     = get_option('rphub_rum_enabled', 1);
                        $rum_sample_rate = get_option('rphub_rum_sample_rate', 1.0);
                        $rum_batch_size  = get_option('rphub_rum_batch_size', 10);
                        ?>
                        <tr>
                            <th scope="row">Google Client ID</th>
                            <td>
                                <input type="text" name="google_client_id" value="<?php echo esc_attr($g_client_id); ?>" class="large-text" autocomplete="off" />
                                <p class="description">OAuth 2.0 Client ID para Google Analytics 4 y Search Console. <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Google Client Secret</th>
                            <td>
                                <input type="password" name="google_client_secret" value="<?php echo esc_attr($g_client_secret); ?>" class="large-text" autocomplete="off" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Google API Key</th>
                            <td>
                                <input type="text" name="google_api_key" value="<?php echo esc_attr($g_api_key); ?>" class="large-text" autocomplete="off" />
                                <p class="description">Para Chrome UX Report (Core Web Vitals) y PageSpeed Insights.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Real User Monitoring</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="rum_enabled" value="1" <?php checked($rum_enabled); ?> />
                                    Activar RUM (recopilar métricas de rendimiento reales de usuarios)
                                </label>
                                <br><br>
                                <label>Tasa de muestreo: <input type="number" name="rum_sample_rate" value="<?php echo esc_attr((int)($rum_sample_rate * 100)); ?>" min="1" max="100" class="small-text" />%</label>
                                &nbsp;&nbsp;
                                <label>Tamaño de lote: <input type="number" name="rum_batch_size" value="<?php echo esc_attr($rum_batch_size); ?>" min="5" max="50" class="small-text" /></label>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Notifications Settings -->
                <div class="rphub-tab-content" id="tab-notifications">
                    <h2>Configuración de Notificaciones</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Activar notificaciones</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="settings[notifications_enabled]" value="1" <?php checked($settings['notifications_enabled']); ?> />
                                    Activar sistema de notificaciones
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Email de notificaciones</th>
                            <td>
                                <input type="email" name="settings[notification_email]" value="<?php echo esc_attr($settings['notification_email']); ?>" class="regular-text" />
                                <p class="description">Email donde enviar notificaciones importantes.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Nivel mínimo de notificación</th>
                            <td>
                                <select name="settings[min_notification_level]">
                                    <option value="info" <?php selected($settings['min_notification_level'], 'info'); ?>>Info</option>
                                    <option value="warning" <?php selected($settings['min_notification_level'], 'warning'); ?>>Warning</option>
                                    <option value="error" <?php selected($settings['min_notification_level'], 'error'); ?>>Error</option>
                                    <option value="critical" <?php selected($settings['min_notification_level'], 'critical'); ?>>Critical</option>
                                </select>
                                <p class="description">Nivel mínimo para enviar notificaciones por email.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Notificar cuando sitio está offline</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="settings[notify_site_down]" value="1" <?php checked($settings['notify_site_down']); ?> />
                                    Enviar notificación cuando un sitio no responda
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Notificar actualizaciones disponibles</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="settings[notify_updates]" value="1" <?php checked($settings['notify_updates']); ?> />
                                    Notificar cuando hay actualizaciones disponibles
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Limpiar notificaciones después de</th>
                            <td>
                                <select name="settings[notification_cleanup_days]">
                                    <option value="7" <?php selected($settings['notification_cleanup_days'], '7'); ?>>7 días</option>
                                    <option value="14" <?php selected($settings['notification_cleanup_days'], '14'); ?>>14 días</option>
                                    <option value="30" <?php selected($settings['notification_cleanup_days'], '30'); ?>>30 días</option>
                                    <option value="60" <?php selected($settings['notification_cleanup_days'], '60'); ?>>60 días</option>
                                </select>
                                <p class="description">Eliminar notificaciones leídas automáticamente.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Slack Webhook URL</th>
                            <td>
                                <input type="url" name="settings[slack_webhook_url]" value="<?php echo esc_attr($settings['slack_webhook_url'] ?? ''); ?>" class="large-text" placeholder="https://hooks.slack.com/services/..." />
                                <p class="description">URL del webhook de Slack para alertas. Deja vacío para desactivar.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Email de alertas</th>
                            <td>
                                <input type="email" name="settings[alert_email]" value="<?php echo esc_attr($settings['alert_email'] ?? ''); ?>" class="regular-text" />
                                <p class="description">Email de destino para alertas críticas (RPHUB_Alerting). Vacío = admin_email.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Nivel mínimo de alerta</th>
                            <td>
                                <select name="settings[alert_min_level]">
                                    <option value="info"    <?php selected($settings['alert_min_level'] ?? 'warning', 'info'); ?>>Info</option>
                                    <option value="warning" <?php selected($settings['alert_min_level'] ?? 'warning', 'warning'); ?>>Warning</option>
                                    <option value="error"   <?php selected($settings['alert_min_level'] ?? 'warning', 'error'); ?>>Error</option>
                                </select>
                                <p class="description">Alertas con nivel igual o mayor serán enviadas.</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- WHM Settings (Multi-Server) -->
                <div class="rphub-tab-content" id="tab-whm">
                    <h2>Configuración WHM/cPanel — Servidores Múltiples</h2>
                    <p class="description">Configura uno o más servidores WHM reseller (ej. EU y US). Cada sitio se asocia automáticamente al servidor que lo gestiona.</p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Activar integración WHM</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="settings[whm_enabled]" value="1" <?php checked($settings['whm_enabled']); ?> />
                                    Activar integración con WHM
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Tokens Persistentes</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="settings[whm_persistent_tokens]" value="1" <?php checked($settings['whm_persistent_tokens']); ?> />
                                    Renovación automática de tokens WHM
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Timeout WHM</th>
                            <td>
                                <input type="number" name="settings[whm_timeout]" value="<?php echo esc_attr($settings['whm_timeout']); ?>" min="30" max="300" />
                                <p class="description">Timeout global para operaciones WHM (segundos).</p>
                            </td>
                        </tr>
                    </table>

                    <h3>Servidores WHM</h3>
                    <div id="whm-servers-container">
                        <?php
                        $whm_servers = $settings['whm_servers'] ?? [];
                        if (empty($whm_servers)) {
                            $whm_servers = [['id' => 'eu', 'label' => 'Servidor EU', 'host' => '', 'username' => 'replanta', 'token' => '', 'region' => 'eu', 'port' => 2087, 'verify_ssl' => 1, 'enabled' => 1]];
                        }
                        foreach ($whm_servers as $idx => $srv) :
                            $srv = wp_parse_args($srv, ['id' => '', 'label' => '', 'host' => '', 'username' => 'replanta', 'token' => '', 'region' => '', 'port' => 2087, 'verify_ssl' => 1, 'enabled' => 1]);
                        ?>
                        <div class="whm-server-block" style="border:1px solid #ccd0d4;padding:15px;margin-bottom:15px;background:#f9f9f9;border-radius:4px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                                <strong style="font-size:14px;"> Servidor <?php echo esc_html($srv['label'] ?: ($idx + 1)); ?></strong>
                                <button type="button" class="button button-link-delete whm-remove-server" title="Eliminar servidor"> Eliminar</button>
                            </div>
                            <table class="form-table" style="margin:0;">
                                <tr>
                                    <th style="width:150px;">ID (clave única)</th>
                                    <td><input type="text" name="settings[whm_servers][<?php echo $idx; ?>][id]" value="<?php echo esc_attr($srv['id']); ?>" class="regular-text" placeholder="eu, us, etc." required pattern="[a-z0-9_-]+" /></td>
                                </tr>
                                <tr>
                                    <th>Etiqueta</th>
                                    <td><input type="text" name="settings[whm_servers][<?php echo $idx; ?>][label]" value="<?php echo esc_attr($srv['label']); ?>" class="regular-text" placeholder="Servidor EU" /></td>
                                </tr>
                                <tr>
                                    <th>Región</th>
                                    <td>
                                        <select name="settings[whm_servers][<?php echo $idx; ?>][region]">
                                            <option value="eu" <?php selected($srv['region'], 'eu'); ?>> Europa (EU)</option>
                                            <option value="us" <?php selected($srv['region'], 'us'); ?>> Estados Unidos (US)</option>
                                            <option value="other" <?php selected($srv['region'], 'other'); ?>>Otro</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Host WHM</th>
                                    <td><input type="text" name="settings[whm_servers][<?php echo $idx; ?>][host]" value="<?php echo esc_attr($srv['host']); ?>" class="regular-text" placeholder="eu.servidor.com" /></td>
                                </tr>
                                <tr>
                                    <th>Puerto</th>
                                    <td><input type="number" name="settings[whm_servers][<?php echo $idx; ?>][port]" value="<?php echo esc_attr($srv['port']); ?>" min="1" max="65535" style="width:80px;" /></td>
                                </tr>
                                <tr>
                                    <th>Usuario WHM</th>
                                    <td><input type="text" name="settings[whm_servers][<?php echo $idx; ?>][username]" value="<?php echo esc_attr($srv['username']); ?>" class="regular-text" /></td>
                                </tr>
                                <tr>
                                    <th>Token de API</th>
                                    <td><input type="password" name="settings[whm_servers][<?php echo $idx; ?>][token]" value="" placeholder="<?php echo !empty($srv['token']) ? '(guardado)' : 'Token de API WHM'; ?>" class="large-text" /></td>
                                </tr>
                                <tr>
                                    <th>Verificar SSL</th>
                                    <td><label><input type="checkbox" name="settings[whm_servers][<?php echo $idx; ?>][verify_ssl]" value="1" <?php checked($srv['verify_ssl']); ?> /> Verificar certificado SSL</label></td>
                                </tr>
                                <tr>
                                    <th>Habilitado</th>
                                    <td><label><input type="checkbox" name="settings[whm_servers][<?php echo $idx; ?>][enabled]" value="1" <?php checked($srv['enabled']); ?> /> Servidor activo</label></td>
                                </tr>
                                <tr>
                                    <th>
                                        <button type="button" class="button whm-test-server-btn" data-index="<?php echo $idx; ?>">Probar Conexión</button>
                                    </th>
                                    <td><span class="whm-server-test-result" data-index="<?php echo $idx; ?>"></span></td>
                                </tr>
                            </table>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <p>
                        <button type="button" class="button button-secondary" id="whm-add-server">+ Añadir Servidor WHM</button>
                        <button type="button" class="button" onclick="rphubTestWHMConnection()">Probar Todos</button>
                        <button type="button" class="button button-secondary" onclick="rphubRunWHMDiagnostics()">Diagnóstico Completo</button>
                    </p>
                    <div id="whm-test-result"></div>
                    <div id="whm-diagnostics-result" style="display: none;"></div>
                </div>
                
                <!-- Reports Settings -->
                <div class="rphub-tab-content" id="tab-reports">
                    <h2>Configuración de Reportes</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Directorio de reportes</th>
                            <td>
                                <input type="text" name="settings[reports_directory]" value="<?php echo esc_attr($settings['reports_directory']); ?>" class="large-text" />
                                <p class="description">Directorio donde guardar los reportes generados.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Generar reportes automáticamente</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="settings[auto_reports]" value="1" <?php checked($settings['auto_reports']); ?> />
                                    Generar reportes automáticamente
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Frecuencia de reportes automáticos</th>
                            <td>
                                <select name="settings[auto_reports_frequency]">
                                    <option value="daily" <?php selected($settings['auto_reports_frequency'], 'daily'); ?>>Diario</option>
                                    <option value="weekly" <?php selected($settings['auto_reports_frequency'], 'weekly'); ?>>Semanal</option>
                                    <option value="monthly" <?php selected($settings['auto_reports_frequency'], 'monthly'); ?>>Mensual</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Formato de reportes automáticos</th>
                            <td>
                                <select name="settings[auto_reports_format]">
                                    <option value="html" <?php selected($settings['auto_reports_format'], 'html'); ?>>HTML</option>
                                    <option value="pdf" <?php selected($settings['auto_reports_format'], 'pdf'); ?>>PDF</option>
                                    <option value="csv" <?php selected($settings['auto_reports_format'], 'csv'); ?>>CSV</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Email para reportes automáticos</th>
                            <td>
                                <textarea name="settings[auto_reports_emails]" rows="3" class="large-text"><?php echo esc_textarea($settings['auto_reports_emails']); ?></textarea>
                                <p class="description">Emails separados por comas para enviar reportes automáticos.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Eliminar reportes después de</th>
                            <td>
                                <select name="settings[reports_cleanup_days]">
                                    <option value="30" <?php selected($settings['reports_cleanup_days'], '30'); ?>>30 días</option>
                                    <option value="60" <?php selected($settings['reports_cleanup_days'], '60'); ?>>60 días</option>
                                    <option value="90" <?php selected($settings['reports_cleanup_days'], '90'); ?>>90 días</option>
                                    <option value="180" <?php selected($settings['reports_cleanup_days'], '180'); ?>>180 días</option>
                                    <option value="365" <?php selected($settings['reports_cleanup_days'], '365'); ?>>1 año</option>
                                </select>
                                <p class="description">Eliminar reportes automáticamente después de este período.</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Tasks Settings -->
                <div class="rphub-tab-content" id="tab-tasks">
                    <h2>Configuración de Tareas</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Máximo tareas simultáneas</th>
                            <td>
                                <input type="number" name="settings[max_concurrent_tasks]" value="<?php echo esc_attr($settings['max_concurrent_tasks']); ?>" min="1" max="20" />
                                <p class="description">Número máximo de tareas que pueden ejecutarse simultáneamente.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Timeout de tareas</th>
                            <td>
                                <input type="number" name="settings[task_timeout]" value="<?php echo esc_attr($settings['task_timeout']); ?>" min="60" max="3600" />
                                <p class="description">Tiempo máximo para ejecutar una tarea (segundos).</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Máximo reintentos de tarea</th>
                            <td>
                                <input type="number" name="settings[max_task_retries]" value="<?php echo esc_attr($settings['max_task_retries']); ?>" min="0" max="10" />
                                <p class="description">Número máximo de reintentos para tareas fallidas.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Limpiar tareas completadas</th>
                            <td>
                                <select name="settings[task_cleanup_days]">
                                    <option value="1" <?php selected($settings['task_cleanup_days'], '1'); ?>>1 día</option>
                                    <option value="7" <?php selected($settings['task_cleanup_days'], '7'); ?>>7 días</option>
                                    <option value="14" <?php selected($settings['task_cleanup_days'], '14'); ?>>14 días</option>
                                    <option value="30" <?php selected($settings['task_cleanup_days'], '30'); ?>>30 días</option>
                                </select>
                                <p class="description">Eliminar tareas completadas después de este período.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Activar heartbeat de sitios</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="settings[enable_heartbeat]" value="1" <?php checked($settings['enable_heartbeat']); ?> />
                                    Verificar estado de sitios periódicamente
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Frecuencia de heartbeat</th>
                            <td>
                                <select name="settings[heartbeat_frequency]">
                                    <option value="5" <?php selected($settings['heartbeat_frequency'], '5'); ?>>5 minutos</option>
                                    <option value="15" <?php selected($settings['heartbeat_frequency'], '15'); ?>>15 minutos</option>
                                    <option value="30" <?php selected($settings['heartbeat_frequency'], '30'); ?>>30 minutos</option>
                                    <option value="60" <?php selected($settings['heartbeat_frequency'], '60'); ?>>1 hora</option>
                                </select>
                                <p class="description">Intervalo entre verificaciones de estado.</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Backup Settings -->
                <div class="rphub-tab-content" id="tab-backup">
                    <h2>Configuración de Backups</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Activar backups automáticos</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="settings[auto_backups]" value="1" <?php checked($settings['auto_backups']); ?> />
                                    Activar sistema de backups automáticos
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Frecuencia de backups</th>
                            <td>
                                <select name="settings[backup_frequency]">
                                    <option value="daily" <?php selected($settings['backup_frequency'], 'daily'); ?>>Diario</option>
                                    <option value="weekly" <?php selected($settings['backup_frequency'], 'weekly'); ?>>Semanal</option>
                                    <option value="monthly" <?php selected($settings['backup_frequency'], 'monthly'); ?>>Mensual</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Retener backups</th>
                            <td>
                                <input type="number" name="settings[backup_retention]" value="<?php echo esc_attr($settings['backup_retention']); ?>" min="1" max="365" />
                                <p class="description">Número de días para retener backups automáticos.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Incluir archivos en backup</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="settings[backup_include_files]" value="1" <?php checked($settings['backup_include_files']); ?> />
                                    Incluir archivos del sitio en los backups
                                </label>
                                <p class="description">Nota: Los backups con archivos serán más grandes y lentos.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Comprimir backups</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="settings[backup_compress]" value="1" <?php checked($settings['backup_compress']); ?> />
                                    Comprimir archivos de backup
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Directorio de backups</th>
                            <td>
                                <input type="text" name="settings[backup_directory]" value="<?php echo esc_attr($settings['backup_directory']); ?>" class="large-text" />
                                <p class="description">Directorio donde almacenar los backups. Debe ser escribible.</p>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2"><hr style="margin:8px 0;"><strong>Backup externo Replanta</strong>
                                <p class="description" style="margin-top:4px;">Credenciales para almacenamiento externo de backups. Se envían automáticamente a los sitios gestionados al aplicar el plan.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Key ID</th>
                            <td>
                                <input type="text" name="settings[b2_key_id]" value="<?php echo esc_attr($settings['b2_key_id'] ?? ''); ?>" class="regular-text" autocomplete="off" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Application Key</th>
                            <td>
                                <input type="password" name="settings[b2_app_key]" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo !empty($settings['b2_app_key']) ? 'Configurada — dejar vacio para mantener' : 'Introducir clave'; ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Bucket ID</th>
                            <td>
                                <input type="text" name="settings[b2_bucket_id]" value="<?php echo esc_attr($settings['b2_bucket_id'] ?? ''); ?>" class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Bucket Name</th>
                            <td>
                                <input type="text" name="settings[b2_bucket_name]" value="<?php echo esc_attr($settings['b2_bucket_name'] ?? ''); ?>" class="regular-text" />
                                <button type="button" class="button" style="margin-left:8px;" onclick="rphubTestB2()">Verificar conexión</button>
                                <span id="rphub-b2-test-result" style="margin-left:10px;font-size:12px;"></span>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <p class="submit">
                    <input type="submit" name="submit_settings" class="button-primary" value="Guardar Configuración" />
                    <input type="submit" name="check_plugin_updates" class="button" value="Comprobar actualizaciones ahora" />
                    <button type="button" class="button" onclick="rphubResetSettings()">Restablecer por Defecto</button>
                </p>
            </form>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab switching
            const tabButtons = document.querySelectorAll('.rphub-tab-button');
            const tabContents = document.querySelectorAll('.rphub-tab-content');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const tabName = this.dataset.tab;
                    
                    // Remove active class from all tabs
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));
                    
                    // Add active class to clicked tab
                    this.classList.add('active');
                    document.getElementById('tab-' + tabName).classList.add('active');
                });
            });
        });
        
        function rphubGenerateJWTSecret() {
            if (confirm('¿Generar una nueva clave JWT? Esto desconectará todos los sitios conectados.')) {
                const charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()_+-=[]{}|;:,.<>?';
                let secret = '';
                for (let i = 0; i < 64; i++) {
                    secret += charset.charAt(Math.floor(Math.random() * charset.length));
                }
                document.querySelector('input[name="settings[jwt_secret]"]').value = secret;
            }
        }
        
        function rphubTestWHMConnection() {
            const resultDiv = document.getElementById('whm-test-result');
            const diagnosticsDiv = document.getElementById('whm-diagnostics-result');
            
            diagnosticsDiv.style.display = 'none';
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<span style="color: blue;">Probando conexión a todos los servidores WHM...</span>';
            
            const data = new URLSearchParams();
            data.append('action', 'rphub_whm_test_connection');
            data.append('nonce', rphub_ajax.nonce);
            
            fetch(ajaxurl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: data })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const servers = data.data.servers || [];
                    let html = '<h4>Resultado de conexión WHM:</h4>';
                    servers.forEach(s => {
                        const icon = s.success ? '' : '';
                        const color = s.success ? 'green' : 'red';
                        html += `<p style="color:${color}">${icon} <strong>${s.label || s.server_id}</strong>: ${s.message}`;
                        if (s.details && s.details.accounts_found !== undefined) html += ` (${s.details.accounts_found} cuentas)`;
                        html += '</p>';
                    });
                    resultDiv.innerHTML = html;
                } else {
                    resultDiv.innerHTML = '<span style="color: red;"> ' + data.data + '</span>';
                }
            })
            .catch(() => { resultDiv.innerHTML = '<span style="color: red;"> Error al probar conexión</span>'; });
        }
        
        function rphubRunWHMDiagnostics() {
            const resultDiv = document.getElementById('whm-diagnostics-result');
            const testDiv = document.getElementById('whm-test-result');
            
            testDiv.style.display = 'none';
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<span style="color: blue;">Ejecutando diagnóstico WHM...</span>';
            
            const data = new URLSearchParams();
            data.append('action', 'rphub_whm_run_diagnostics');
            data.append('nonce', rphub_ajax.nonce);
            
            fetch(ajaxurl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: data })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const d = data.data;
                    let html = '<h4>Diagnóstico WHM (Multi-Servidor):</h4>';
                    html += `<p>Servidores configurados: <strong>${d.servers_count}</strong></p>`;
                    
                    if (d.servers) {
                        for (const [id, srv] of Object.entries(d.servers)) {
                            const connOk = srv.connectivity.http_reachable;
                            const authOk = srv.authentication.auth_successful;
                            html += `<div style="border:1px solid #ccd0d4;padding:10px;margin:8px 0;border-radius:4px;background:#fff;">`;
                            html += `<strong> ${srv.label} (${id})</strong> — Región: ${srv.region || 'N/D'}<br>`;
                            html += `Conectividad: ${connOk ? '' : ''} | Auth: ${authOk ? '' : ''}`;
                            if (authOk) html += ` | Cuentas: ${srv.authentication.accounts_found}`;
                            if (!authOk && srv.authentication.error_message) html += `<br><small style="color:red">${srv.authentication.error_message}</small>`;
                            html += '</div>';
                        }
                    }
                    
                    html += '<h5> Recomendaciones:</h5><ul>';
                    (d.recommendations || []).forEach(r => { html += `<li>${r}</li>`; });
                    html += '</ul>';
                    resultDiv.innerHTML = html;
                } else {
                    resultDiv.innerHTML = '<span style="color: red;"> ' + data.data + '</span>';
                }
            })
            .catch(() => { resultDiv.innerHTML = '<span style="color: red;"> Error al ejecutar diagnóstico</span>'; });
        }

        // WHM multi-server: Add / Remove / Test individual server
        (function() {
            const container = document.getElementById('whm-servers-container');
            if (!container) return;

            // Add server
            document.getElementById('whm-add-server').addEventListener('click', function() {
                const blocks = container.querySelectorAll('.whm-server-block');
                const idx = blocks.length;
                const tpl = `
                <div class="whm-server-block" style="border:1px solid #ccd0d4;padding:15px;margin-bottom:15px;background:#f9f9f9;border-radius:4px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                        <strong style="font-size:14px;"> Nuevo Servidor</strong>
                        <button type="button" class="button button-link-delete whm-remove-server" title="Eliminar servidor"> Eliminar</button>
                    </div>
                    <table class="form-table" style="margin:0;">
                        <tr><th style="width:150px;">ID (clave única)</th><td><input type="text" name="settings[whm_servers][${idx}][id]" value="" class="regular-text" placeholder="us" required pattern="[a-z0-9_-]+"/></td></tr>
                        <tr><th>Etiqueta</th><td><input type="text" name="settings[whm_servers][${idx}][label]" value="" class="regular-text" placeholder="Servidor US"/></td></tr>
                        <tr><th>Región</th><td><select name="settings[whm_servers][${idx}][region]"><option value="eu"> Europa (EU)</option><option value="us" selected> Estados Unidos (US)</option><option value="other">Otro</option></select></td></tr>
                        <tr><th>Host WHM</th><td><input type="text" name="settings[whm_servers][${idx}][host]" value="" class="regular-text" placeholder="us.servidor.com"/></td></tr>
                        <tr><th>Puerto</th><td><input type="number" name="settings[whm_servers][${idx}][port]" value="2087" min="1" max="65535" style="width:80px;"/></td></tr>
                        <tr><th>Usuario WHM</th><td><input type="text" name="settings[whm_servers][${idx}][username]" value="replanta" class="regular-text"/></td></tr>
                        <tr><th>Token de API</th><td><input type="password" name="settings[whm_servers][${idx}][token]" value="" class="large-text"/></td></tr>
                        <tr><th>Verificar SSL</th><td><label><input type="checkbox" name="settings[whm_servers][${idx}][verify_ssl]" value="1" checked/> Verificar certificado SSL</label></td></tr>
                        <tr><th>Habilitado</th><td><label><input type="checkbox" name="settings[whm_servers][${idx}][enabled]" value="1" checked/> Servidor activo</label></td></tr>
                        <tr><th><button type="button" class="button whm-test-server-btn" data-index="${idx}">Probar Conexión</button></th><td><span class="whm-server-test-result" data-index="${idx}"></span></td></tr>
                    </table>
                </div>`;
                container.insertAdjacentHTML('beforeend', tpl);
            });

            // Remove server (delegated)
            container.addEventListener('click', function(e) {
                if (e.target.classList.contains('whm-remove-server')) {
                    if (confirm('¿Eliminar este servidor WHM?')) {
                        e.target.closest('.whm-server-block').remove();
                    }
                }
            });

            // Test individual server (delegated)
            container.addEventListener('click', function(e) {
                const btn = e.target.closest('.whm-test-server-btn');
                if (!btn) return;
                const idx = btn.getAttribute('data-index');
                const block = btn.closest('.whm-server-block');
                const resultEl = block.querySelector('.whm-server-test-result');
                const serverId = block.querySelector('input[name$="[id]"]').value;
                
                if (!serverId) { resultEl.innerHTML = '<span style="color:red">Guarda primero el servidor para poder probarlo</span>'; return; }
                resultEl.innerHTML = '<span style="color:blue">Probando...</span>';
                
                const data = new URLSearchParams();
                data.append('action', 'rphub_whm_test_server');
                data.append('server_id', serverId);
                data.append('nonce', rphub_ajax.nonce);
                
                fetch(ajaxurl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: data })
                .then(r => r.json())
                .then(resp => {
                    const res = resp.success ? resp.data : resp.data;
                    const ok = res.success || resp.success;
                    const icon = ok ? '' : '';
                    const color = ok ? 'green' : 'red';
                    resultEl.innerHTML = `<span style="color:${color}">${icon} ${res.message || 'Error desconocido'}</span>`;
                })
                .catch(() => { resultEl.innerHTML = '<span style="color:red"> Error de red</span>'; });
            });
        })();
        
        function rphubResetSettings() {
            if (confirm('¿Restablecer todas las configuraciones a los valores por defecto? Esta acción no se puede deshacer.')) {
                window.location.href = window.location.href + '&reset_settings=1';
            }
        }

        function rphubTestB2() {
            var result = document.getElementById('rphub-b2-test-result');
            result.textContent = 'Verificando…';
            result.style.color = '#888';
            jQuery.post(ajaxurl, {
                action: 'rphub_b2_test_connection',
                key_id:      document.querySelector('[name="settings[b2_key_id]"]').value,
                app_key:     document.querySelector('[name="settings[b2_app_key]"]').value,
                bucket_id:   document.querySelector('[name="settings[b2_bucket_id]"]').value,
                bucket_name: document.querySelector('[name="settings[b2_bucket_name]"]').value,
                nonce:       <?php echo wp_json_encode(wp_create_nonce('rphub_ajax')); ?>
            }, function(resp) {
                if (resp.success) {
                    result.textContent = 'Conexión correcta';
                    result.style.color = '#00a32a';
                } else {
                    result.textContent = 'Error: ' + (resp.data || 'Fallo de conexión');
                    result.style.color = '#d63638';
                }
            });
        }
        </script>
        <?php
    }
    
    private function save_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos para realizar esta acción.'));
        }
        
        $settings = $_POST['settings'] ?? array();
        
        // Sanitize settings
        $clean_settings = array();
        
        // General settings
        $clean_settings['hub_name'] = sanitize_text_field($settings['hub_name'] ?? '');
        $clean_settings['connection_timeout'] = intval($settings['connection_timeout'] ?? 30);
        $clean_settings['sites_per_page'] = intval($settings['sites_per_page'] ?? 25);
        $clean_settings['debug_mode'] = isset($settings['debug_mode']) ? 1 : 0;
        $clean_settings['log_cleanup_days'] = intval($settings['log_cleanup_days'] ?? 30);
        
        // API settings
        $clean_settings['jwt_secret'] = sanitize_text_field($settings['jwt_secret'] ?? '');
        $clean_settings['jwt_expiration'] = intval($settings['jwt_expiration'] ?? 86400);
        $clean_settings['verify_ssl'] = isset($settings['verify_ssl']) ? 1 : 0;
        $clean_settings['user_agent'] = sanitize_text_field($settings['user_agent'] ?? '');
        $clean_settings['max_retries'] = intval($settings['max_retries'] ?? 3);
        $clean_settings['github_token'] = sanitize_text_field($settings['github_token'] ?? '');
        
        // Notification settings
        $clean_settings['notifications_enabled'] = isset($settings['notifications_enabled']) ? 1 : 0;
        $clean_settings['notification_email'] = sanitize_email($settings['notification_email'] ?? '');
        $clean_settings['min_notification_level'] = sanitize_text_field($settings['min_notification_level'] ?? 'warning');
        $clean_settings['notify_site_down'] = isset($settings['notify_site_down']) ? 1 : 0;
        $clean_settings['notify_updates'] = isset($settings['notify_updates']) ? 1 : 0;
        $clean_settings['notification_cleanup_days'] = intval($settings['notification_cleanup_days'] ?? 30);
        // Alerting (Slack + email dispatcher)
        $clean_settings['slack_webhook_url'] = esc_url_raw( trim( $settings['slack_webhook_url'] ?? '' ) );
        $clean_settings['alert_email']       = sanitize_email( $settings['alert_email'] ?? '' );
        $clean_settings['alert_min_level']   = in_array( $settings['alert_min_level'] ?? '', [ 'info', 'warning', 'error' ], true )
            ? $settings['alert_min_level']
            : 'warning';
        
        // WHM global settings
        $clean_settings['whm_enabled'] = isset($settings['whm_enabled']) ? 1 : 0;
        $clean_settings['whm_persistent_tokens'] = isset($settings['whm_persistent_tokens']) ? 1 : 0;
        $clean_settings['whm_timeout'] = intval($settings['whm_timeout'] ?? 60);
        
        // WHM multi-server settings
        $whm_servers_raw = $settings['whm_servers'] ?? [];
        $existing_settings = get_option('rphub_settings', []);
        $existing_whm = [];
        foreach ($existing_settings['whm_servers'] ?? [] as $es) {
            $existing_whm[ $es['id'] ?? '' ] = $es;
        }
        $clean_servers = [];
        if (is_array($whm_servers_raw)) {
            foreach ($whm_servers_raw as $srv) {
                $id = sanitize_key($srv['id'] ?? '');
                if (empty($id)) continue;
                $new_token = sanitize_text_field($srv['token'] ?? '');
                $stored_token = $new_token !== ''
                    ? RPHUB_Crypto::encrypt($new_token)
                    : ($existing_whm[$id]['token'] ?? '');
                $clean_servers[] = [
                    'id'         => $id,
                    'label'      => sanitize_text_field($srv['label'] ?? $id),
                    'host'       => sanitize_text_field($srv['host'] ?? ''),
                    'username'   => sanitize_text_field($srv['username'] ?? 'replanta'),
                    'token'      => $stored_token,
                    'region'     => sanitize_text_field($srv['region'] ?? ''),
                    'port'       => intval($srv['port'] ?? 2087),
                    'verify_ssl' => isset($srv['verify_ssl']) ? 1 : 0,
                    'enabled'    => isset($srv['enabled']) ? 1 : 0,
                ];
            }
        }
        $clean_settings['whm_servers'] = $clean_servers;
        
        // Keep legacy fields for backward compat (from first server)
        if (!empty($clean_servers)) {
            $first = $clean_servers[0];
            $clean_settings['whm_url']       = $first['host'] ? ('https://' . $first['host'] . ':' . $first['port']) : '';
            $clean_settings['whm_username']   = $first['username'];
            $clean_settings['whm_api_token']  = $first['token'];
            $clean_settings['whm_verify_ssl'] = $first['verify_ssl'];
        } else {
            $clean_settings['whm_url']       = '';
            $clean_settings['whm_username']   = '';
            $clean_settings['whm_api_token']  = '';
            $clean_settings['whm_verify_ssl'] = 1;
        }
        
        // Reports settings
        $clean_settings['reports_directory'] = sanitize_text_field($settings['reports_directory'] ?? '');
        $clean_settings['auto_reports'] = isset($settings['auto_reports']) ? 1 : 0;
        $clean_settings['auto_reports_frequency'] = sanitize_text_field($settings['auto_reports_frequency'] ?? 'monthly');
        $clean_settings['auto_reports_format'] = sanitize_text_field($settings['auto_reports_format'] ?? 'html');
        $clean_settings['auto_reports_emails'] = sanitize_textarea_field($settings['auto_reports_emails'] ?? '');
        $clean_settings['reports_cleanup_days'] = intval($settings['reports_cleanup_days'] ?? 90);
        
        // Tasks settings
        $clean_settings['max_concurrent_tasks'] = intval($settings['max_concurrent_tasks'] ?? 5);
        $clean_settings['task_timeout'] = intval($settings['task_timeout'] ?? 300);
        $clean_settings['max_task_retries'] = intval($settings['max_task_retries'] ?? 3);
        $clean_settings['task_cleanup_days'] = intval($settings['task_cleanup_days'] ?? 7);
        $clean_settings['enable_heartbeat'] = isset($settings['enable_heartbeat']) ? 1 : 0;
        $clean_settings['heartbeat_frequency'] = intval($settings['heartbeat_frequency'] ?? 15);
        
        // Backup settings
        $clean_settings['auto_backups'] = isset($settings['auto_backups']) ? 1 : 0;
        $clean_settings['backup_frequency'] = sanitize_text_field($settings['backup_frequency'] ?? 'weekly');
        $clean_settings['backup_retention'] = intval($settings['backup_retention'] ?? 30);
        $clean_settings['backup_include_files'] = isset($settings['backup_include_files']) ? 1 : 0;
        $clean_settings['backup_compress'] = isset($settings['backup_compress']) ? 1 : 0;
        $clean_settings['backup_directory']  = sanitize_text_field($settings['backup_directory'] ?? '');
        $clean_settings['b2_key_id']      = sanitize_text_field($settings['b2_key_id']     ?? '');
        $clean_settings['b2_bucket_id']   = sanitize_text_field($settings['b2_bucket_id']  ?? '');
        $clean_settings['b2_bucket_name'] = sanitize_text_field($settings['b2_bucket_name'] ?? '');
        $new_app_key = sanitize_text_field($settings['b2_app_key'] ?? '');
        if (!empty($new_app_key)) {
            $clean_settings['b2_app_key'] = class_exists('RPHUB_Crypto')
                ? RPHUB_Crypto::encrypt($new_app_key)
                : $new_app_key;
        } else {
            $existing = get_option('rphub_settings', []);
            $clean_settings['b2_app_key'] = $existing['b2_app_key'] ?? '';
        }

        $existing_settings = get_option('rphub_settings', []);
        $b2_changed = (
            ($existing_settings['b2_key_id']     ?? '') !== $clean_settings['b2_key_id'] ||
            ($existing_settings['b2_app_key']    ?? '') !== $clean_settings['b2_app_key'] ||
            ($existing_settings['b2_bucket_id']  ?? '') !== $clean_settings['b2_bucket_id'] ||
            ($existing_settings['b2_bucket_name'] ?? '') !== $clean_settings['b2_bucket_name']
        );

        update_option('rphub_settings', $clean_settings);
        update_option('rphub_github_token', $clean_settings['github_token']);

        if ($b2_changed) {
            delete_transient(ReplantaHub_Backblaze_Integration::AUTH_TRANSIENT);
            if (class_exists('ReplantaHub_Backblaze_Integration')
                && !empty($clean_settings['b2_key_id'])
                && !empty($clean_settings['b2_app_key'])
                && !empty($clean_settings['b2_bucket_id'])) {
                $broadcast = ReplantaHub_Backblaze_Integration::get_instance()->push_config_to_all_sites();
                $msg = sprintf(
                    'Credenciales B2 propagadas: %d sitios OK, %d con error.',
                    intval($broadcast['pushed']),
                    intval($broadcast['failed'])
                );
                if (!empty($broadcast['errors'])) {
                    $msg .= ' Detalle: ' . esc_html(implode(' | ', array_slice($broadcast['errors'], 0, 5)));
                }
                echo '<div class="notice notice-info"><p>' . $msg . '</p></div>';
            }
        }

        // Auto-generate deploy token on first save if not set
        if (!empty($clean_settings['deploy_token'])) {
            update_option('rphub_deploy_token', sanitize_text_field($clean_settings['deploy_token']));
        } elseif (!get_option('rphub_deploy_token')) {
            update_option('rphub_deploy_token', wp_generate_password(32, false));
        }

        // Encrypt standalone API key options — only update when a new value is submitted
        $new_cf = sanitize_text_field($settings['cloudflare_api_key'] ?? '');
        if ($new_cf !== '') {
            update_option('rphub_cloudflare_api_key', RPHUB_Crypto::encrypt($new_cf));
        }
        $new_psi = sanitize_text_field($settings['pagespeed_api_key'] ?? '');
        if ($new_psi !== '') {
            update_option('rphub_pagespeed_api_key', RPHUB_Crypto::encrypt($new_psi));
        }
        $new_ls = sanitize_text_field($settings['litespeed_api_key'] ?? '');
        if ($new_ls !== '') {
            update_option('rphub_litespeed_api_key', RPHUB_Crypto::encrypt($new_ls));
        }

        // Google OAuth + RUM (moved from Analytics/WPO page)
        if (isset($_POST['google_client_id'])) {
            update_option('replanta_hub_google_client_id',     sanitize_text_field($_POST['google_client_id']));
            update_option('replanta_hub_google_client_secret', sanitize_text_field($_POST['google_client_secret'] ?? ''));
            update_option('replanta_hub_google_api_key',       sanitize_text_field($_POST['google_api_key'] ?? ''));
            update_option('rphub_rum_enabled',     isset($_POST['rum_enabled']) ? 1 : 0);
            $rum_rate = max(1, min(100, intval($_POST['rum_sample_rate'] ?? 100)));
            update_option('rphub_rum_sample_rate', $rum_rate / 100.0);
            update_option('rphub_rum_batch_size',  max(5, min(50, intval($_POST['rum_batch_size'] ?? 10))));
        }

        echo '<div class="notice notice-success"><p>Configuración guardada correctamente.</p></div>';
    }

    private function force_plugin_update_check() {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos para realizar esta acción.'));
        }

        delete_site_transient('update_plugins');
        wp_clean_plugins_cache(true);
        wp_update_plugins();

        echo '<div class="notice notice-info"><p>Comprobación de actualizaciones ejecutada. Revisa Plugins > Plugins instalados.</p></div>';
    }
    
    private function get_default_settings() {
        return array(
            // General
            'hub_name' => 'Replanta Hub',
            'connection_timeout' => 30,
            'sites_per_page' => 25,
            'debug_mode' => 0,
            'log_cleanup_days' => 30,
            
            // API
            'jwt_secret' => wp_generate_password(64, true, true),
            'jwt_expiration' => 86400,
            'verify_ssl' => 1,
            'user_agent' => 'Replanta Hub/1.0',
            'max_retries' => 3,
            'github_token' => get_option('rphub_github_token', ''),
            
            // Notifications
            'notifications_enabled' => 1,
            'notification_email' => get_option('admin_email'),
            'min_notification_level' => 'warning',
            'notify_site_down' => 1,
            'notify_updates' => 1,
            'notification_cleanup_days' => 30,
            
            // WHM
            'whm_enabled' => 0,
            'whm_url' => '',
            'whm_username' => '',
            'whm_api_token' => '',
            'whm_persistent_tokens' => 1,
            'whm_verify_ssl' => 1,
            'whm_timeout' => 60,
            'whm_servers' => [],
            
            // Reports
            'reports_directory' => WP_CONTENT_DIR . '/replanta-hub-reports',
            'auto_reports' => 0,
            'auto_reports_frequency' => 'monthly',
            'auto_reports_format' => 'html',
            'auto_reports_emails' => get_option('admin_email'),
            'reports_cleanup_days' => 90,
            
            // Tasks
            'max_concurrent_tasks' => 5,
            'task_timeout' => 300,
            'max_task_retries' => 3,
            'task_cleanup_days' => 7,
            'enable_heartbeat' => 1,
            'heartbeat_frequency' => 15,
            
            // Backup
            'auto_backups' => 0,
            'backup_frequency' => 'weekly',
            'backup_retention' => 30,
            'backup_include_files' => 0,
            'backup_compress'    => 1,
            'backup_directory'   => WP_CONTENT_DIR . '/replanta-hub-backups',
            'b2_key_id'          => '',
            'b2_app_key'         => '',
            'b2_bucket_id'       => '',
            'b2_bucket_name'     => '',
        );
    }
}
