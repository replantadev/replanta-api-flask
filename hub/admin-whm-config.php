<?php
/**
 * Configuración Avanzada de WHM para Replanta Hub
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    wp_die('No tienes permisos suficientes');
}

// Procesar formulario
if (isset($_POST['save_whm_config'])) {
    check_admin_referer('rphub_whm_config');
    
    update_option('rphub_whm_enabled', !empty($_POST['whm_enabled']));
    update_option('rphub_whm_host', sanitize_text_field($_POST['whm_host']));
    update_option('rphub_whm_port', sanitize_text_field($_POST['whm_port']));
    update_option('rphub_whm_username', sanitize_text_field($_POST['whm_username']));
    update_option('rphub_whm_password', sanitize_text_field($_POST['whm_password']));
    // SSL verification is always enabled — no user toggle
    delete_option('rphub_whm_ssl_verify');
    
    echo '<div class="notice notice-success"><p>✅ Configuración WHM guardada correctamente</p></div>';
}

// Test de conexión
$test_result = null;
if (isset($_POST['test_whm_connection'])) {
    check_admin_referer('rphub_whm_config');
    
    if (class_exists('RPHUB_WHM_Integration')) {
        $whm = new RPHUB_WHM_Integration();
        $test_result = $whm->test_connection();
    }
}

// Obtener valores actuales
$whm_enabled = get_option('rphub_whm_enabled', false);
$whm_host = get_option('rphub_whm_host', '');
$whm_port = get_option('rphub_whm_port', '2087');
$whm_username = get_option('rphub_whm_username', '');
$whm_password = get_option('rphub_whm_password', '');

?>
<div class="wrap">
    <h1>🔧 Configuración WHM - Replanta Hub</h1>
    
    <div class="notice notice-info">
        <p><strong>💡 Información:</strong> Configure aquí las credenciales para conectar con su servidor WHM/cPanel.</p>
    </div>
    
    <?php if ($test_result): ?>
        <div class="notice notice-<?php echo $test_result['success'] ? 'success' : 'error'; ?>">
            <h4>🔍 Resultado del Test de Conexión:</h4>
            <p><strong>Estado:</strong> <?php echo $test_result['success'] ? '✅ Exitoso' : '❌ Error'; ?></p>
            <p><strong>Mensaje:</strong> <?php echo esc_html($test_result['message']); ?></p>
            
            <?php if (!$test_result['success'] && isset($test_result['details'])): ?>
                <div style="margin-top: 15px;">
                    <?php 
                    $details = $test_result['details'];
                    
                    // Mostrar diagnóstico específico si está disponible
                    if (isset($details['problem_type'])): ?>
                        <h4>🔧 Diagnóstico:</h4>
                        <p><strong>Tipo de problema:</strong> <?php echo esc_html($details['suggestion']); ?></p>
                        
                        <?php if (!empty($details['solutions'])): ?>
                            <h4>💡 Soluciones recomendadas:</h4>
                            <ol style="margin-left: 20px;">
                                <?php foreach ($details['solutions'] as $solution): ?>
                                    <li><?php echo esc_html($solution); ?></li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                        
                        <?php if (!empty($details['links'])): ?>
                            <h4>📖 Enlaces útiles:</h4>
                            <ul style="margin-left: 20px;">
                                <?php foreach ($details['links'] as $title => $url): ?>
                                    <li><a href="<?php echo esc_url($url); ?>" target="_blank"><?php echo esc_html($title); ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        
                    <?php endif; ?>
                    
                    <details style="margin-top: 10px;">
                        <summary>Ver información técnica completa</summary>
                        <pre style="background: #f1f1f1; padding: 10px; border-radius: 4px; margin-top: 10px; font-size: 12px; overflow-x: auto;"><?php echo esc_html(print_r($test_result['details'], true)); ?></pre>
                    </details>
                </div>
            <?php elseif ($test_result['success'] && isset($test_result['details'])): ?>
                <div style="margin-top: 15px;">
                    <h4>📋 Información de conexión:</h4>
                    <ul style="margin-left: 20px;">
                        <?php if (isset($details['version'])): ?>
                            <li><strong>Versión WHM:</strong> <?php echo esc_html($details['version']); ?></li>
                        <?php endif; ?>
                        <?php if (isset($details['host'])): ?>
                            <li><strong>Servidor:</strong> <?php echo esc_html($details['host']); ?></li>
                        <?php endif; ?>
                        <?php if (isset($details['environment'])): ?>
                            <li><strong>Entorno:</strong> <?php echo esc_html($details['environment']); ?></li>
                        <?php endif; ?>
                        <?php if (isset($details['timestamp'])): ?>
                            <li><strong>Última prueba:</strong> <?php echo esc_html($details['timestamp']); ?></li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <form method="post" action="">
        <?php wp_nonce_field('rphub_whm_config'); ?>
        
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="whm_enabled">Habilitar WHM</label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="whm_enabled" name="whm_enabled" value="1" <?php checked($whm_enabled); ?>>
                            Activar integración con WHM
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="whm_host">Servidor WHM</label>
                    </th>
                    <td>
                        <input type="text" id="whm_host" name="whm_host" value="<?php echo esc_attr($whm_host); ?>" class="regular-text" placeholder="ejemplo: server.tudominio.com">
                        <p class="description">Dirección IP o dominio del servidor WHM (sin https://)</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="whm_port">Puerto</label>
                    </th>
                    <td>
                        <input type="number" id="whm_port" name="whm_port" value="<?php echo esc_attr($whm_port); ?>" class="small-text" min="1" max="65535">
                        <p class="description">Puerto del servicio WHM (por defecto: 2087 para HTTPS, 2086 para HTTP)</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="whm_username">Usuario</label>
                    </th>
                    <td>
                        <input type="text" id="whm_username" name="whm_username" value="<?php echo esc_attr($whm_username); ?>" class="regular-text" placeholder="root">
                        <p class="description">Usuario del servidor WHM (normalmente 'root')</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="whm_password">Token/Password</label>
                    </th>
                    <td>
                        <input type="password" id="whm_password" name="whm_password" value="<?php echo esc_attr($whm_password); ?>" class="regular-text" placeholder="API Token (obligatorio si tienes 2FA)">
                        <p class="description"><strong style="color: #d63638;">⚠️ Si tienes 2FA habilitado:</strong> Debes usar API Token (no contraseña)</p>
                        <p class="description"><strong>Crear token:</strong> WHM → Development → Manage API Tokens → Generate Token</p>
                        <p class="description"><strong>Sin 2FA:</strong> Puedes usar API Token o contraseña del usuario root</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Verificación SSL</th>
                    <td>
                        <p><strong>✅ SSL siempre activo</strong> — la verificación de certificados está habilitada permanentemente por seguridad.</p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <div style="margin: 20px 0; display: flex; gap: 10px;">
            <input type="submit" name="save_whm_config" class="button button-primary" value="💾 Guardar Configuración">
            <input type="submit" name="test_whm_connection" class="button button-secondary" value="🔍 Probar Conexión">
        </div>
    </form>
    
    <div class="card" style="margin-top: 30px;">
        <h2>🔧 Guía de Configuración WHM</h2>
        
        <div class="notice notice-warning inline">
            <h4>🔐 ¿Tienes 2FA habilitado?</h4>
            <p><strong>Si tienes autenticación de dos factores (2FA) habilitada en WHM, NO puedes usar la contraseña normal.</strong> Debes crear un API Token específico.</p>
        </div>
        
        <h3>📋 Pasos para configurar WHM con 2FA:</h3>
        <ol>
            <li><strong>Crear API Token (OBLIGATORIO con 2FA):</strong>
                <ul>
                    <li>✅ Acceder a WHM como root</li>
                    <li>✅ Ir a "Development" → "Manage API Tokens"</li>
                    <li>✅ Hacer clic en "Generate Token"</li>
                    <li>✅ Darle un nombre al token (ej: "Replanta Hub")</li>
                    <li>✅ NO establecer fecha de expiración (o usar una fecha lejana)</li>
                    <li>✅ Copiar el token generado (solo se muestra una vez)</li>
                    <li>✅ Usar el token en el campo "Token/Password" arriba</li>
                </ul>
            </li>
            <li><strong>Configurar Firewall:</strong>
                <ul>
                    <li>Asegurar que el puerto 2087 (HTTPS) esté abierto</li>
                    <li>Permitir conexiones desde la IP del sitio web</li>
                </ul>
            </li>
            <li><strong>Verificar DNS:</strong>
                <ul>
                    <li>Confirmar que el hostname del servidor sea accesible</li>
                    <li>Usar IP directa si hay problemas de DNS</li>
                </ul>
            </li>
        </ol>
        
        <h3>❌ Solución para Error HTTP 400 con 2FA:</h3>
        <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 15px; margin: 10px 0;">
            <h4>🚨 Error más común: Usar contraseña con 2FA habilitado</h4>
            <ul>
                <li>❌ <strong>NO funciona:</strong> Usuario root + contraseña (con 2FA activo)</li>
                <li>✅ <strong>SÍ funciona:</strong> Usuario root + API Token</li>
            </ul>
            
            <h4>📝 Pasos específicos:</h4>
            <ol>
                <li>Ir a WHM → Development → Manage API Tokens</li>
                <li>Crear nuevo token sin fecha de expiración</li>
                <li>Copiar el token completo</li>
                <li>Pegarlo en el campo "Token/Password" de arriba</li>
                <li>Probar conexión</li>
            </ol>
        </div>
        
        <h3>🔗 Otros problemas comunes:</h3>
        <ul>
            <li>✅ Verificar que el hostname/IP sea correcto</li>
            <li>✅ Confirmar que el puerto sea el correcto (2087 para HTTPS)</li>
            <li>✅ Asegurar que el usuario root tenga permisos API</li>
            <li>✅ Probar desactivar verificación SSL para desarrollo</li>
            <li>✅ Verificar que no haya proxy/firewall bloqueando</li>
        </ul>
        
        <h3>🌐 URLs de Test Comunes:</h3>
        <ul>
            <li><code>https://server.tudominio.com:2087/json-api/version</code></li>
            <li><code>https://IP_SERVIDOR:2087/json-api/version</code></li>
        </ul>
    </div>
    
    <div class="card" style="margin-top: 20px;">
        <h2>📊 Test Manual de Conectividad</h2>
        <p>Puedes probar la conectividad manualmente usando estos comandos:</p>
        
        <h4>🔧 Test con cURL:</h4>
        <pre style="background: #f1f1f1; padding: 15px; border-radius: 4px; overflow-x: auto;">curl -k -u "root:TU_TOKEN" "https://<?php echo esc_html($whm_host ?: 'SERVER'); ?>:<?php echo esc_html($whm_port); ?>/json-api/version"</pre>
        
        <h4>🌐 Test en Navegador:</h4>
        <p>Accede a: <code>https://<?php echo esc_html($whm_host ?: 'SERVER'); ?>:<?php echo esc_html($whm_port); ?>/</code></p>
    </div>
</div>

<style>
.card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.card h2 {
    margin-top: 0;
}

.card ul, .card ol {
    margin-left: 20px;
}

.card li {
    margin-bottom: 5px;
}

pre {
    background: #f1f1f1;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    overflow-x: auto;
    font-family: 'Courier New', monospace;
}

code {
    background: #f1f1f1;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
}
</style>
