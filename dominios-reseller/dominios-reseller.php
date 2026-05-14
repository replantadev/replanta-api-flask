<?php
/*
Plugin Name: Dominios Reseller
Description: Certifica dominios ecológicos desde WHM, muestra árboles plantados y CO2 evitado. Integración con Cloudflare.
Version: 1.7.1
Author: Replanta
*/

define('DOMINIOS_RESELLER_VERSION', '1.7.1');
define('DOMINIOS_RESELLER_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Detectar tipo de SSL de un dominio
 * 
 * @param string $domain Dominio a verificar
 * @param array|null $cf_match Datos de Cloudflare si existe
 * @return string HTML con icono y tooltip
 */
function dominios_reseller_get_ssl_type(string $domain, ?array $cf_match = null): string {
    // Cache para evitar múltiples requests
    static $ssl_cache = [];
    
    if (isset($ssl_cache[$domain])) {
        return $ssl_cache[$domain];
    }
    
    // Si está en Cloudflare activo, SSL es de CF
    if ($cf_match && ($cf_match['status'] ?? '') === 'active') {
        $ssl_cache[$domain] = '<span class="ssl-badge ssl-cf" title="Cloudflare Universal SSL">CF</span>';
        return $ssl_cache[$domain];
    }
    
    // Intentar detectar SSL real
    $ssl_info = @stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => false]]);
    $stream = @stream_socket_client(
        "ssl://{$domain}:443",
        $errno, $errstr, 5,
        STREAM_CLIENT_CONNECT,
        $ssl_info
    );
    
    if (!$stream) {
        $ssl_cache[$domain] = '<span class="ssl-badge ssl-none" title="Sin SSL o error de conexión">✗</span>';
        return $ssl_cache[$domain];
    }
    
    $params = stream_context_get_params($stream);
    fclose($stream);
    
    if (!isset($params['options']['ssl']['peer_certificate'])) {
        $ssl_cache[$domain] = '<span class="ssl-badge ssl-none" title="Sin certificado">✗</span>';
        return $ssl_cache[$domain];
    }
    
    $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
    $issuer = $cert['issuer']['O'] ?? $cert['issuer']['CN'] ?? 'Unknown';
    
    // Detectar tipo según issuer
    if (stripos($issuer, 'Cloudflare') !== false) {
        $ssl_cache[$domain] = '<span class="ssl-badge ssl-cf" title="Cloudflare: ' . esc_attr($issuer) . '">CF</span>';
    } elseif (stripos($issuer, "Let's Encrypt") !== false || stripos($issuer, 'ISRG') !== false) {
        $ssl_cache[$domain] = '<span class="ssl-badge ssl-le" title="Let\'s Encrypt">LE</span>';
    } elseif (stripos($issuer, 'Sectigo') !== false || stripos($issuer, 'Comodo') !== false) {
        $ssl_cache[$domain] = '<span class="ssl-badge ssl-paid" title="Sectigo/Comodo: ' . esc_attr($issuer) . '">$</span>';
    } elseif (stripos($issuer, 'DigiCert') !== false || stripos($issuer, 'GeoTrust') !== false) {
        $ssl_cache[$domain] = '<span class="ssl-badge ssl-paid" title="' . esc_attr($issuer) . '">$</span>';
    } elseif (stripos($issuer, 'cPanel') !== false || stripos($issuer, 'AutoSSL') !== false) {
        $ssl_cache[$domain] = '<span class="ssl-badge ssl-autossl" title="cPanel AutoSSL">AS</span>';
    } else {
        $ssl_cache[$domain] = '<span class="ssl-badge ssl-other" title="' . esc_attr($issuer) . '">✓</span>';
    }
    
    return $ssl_cache[$domain];
}

// Activar plugin: crear tabla si no existe
register_activation_hook(__FILE__, 'dominios_reseller_create_table');
register_activation_hook(__FILE__, 'dominios_reseller_schedule_whm_sync');
register_deactivation_hook(__FILE__, 'dominios_reseller_unschedule_whm_sync');

function dominios_reseller_create_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'dominios_reseller';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        domain varchar(255) NOT NULL,
        server varchar(10) NOT NULL DEFAULT 'uk',
        trees_planted int(11) DEFAULT 0,
        co2_evaded decimal(10,2) DEFAULT 0,
        fecha_emision DATE DEFAULT NULL,
        validez DATE DEFAULT NULL,
        status varchar(20) DEFAULT 'Activo',
        primary_domain varchar(255) NOT NULL,
        is_primary tinyint(1) DEFAULT 1,
        startdate bigint(20) DEFAULT NULL,
        last_sync TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY domain_server (domain, server),
        KEY idx_domain (domain),
        KEY idx_server (server),
        KEY idx_status (status),
        KEY idx_primary (is_primary)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Actualizar tabla existente si es necesario
    dominios_reseller_upgrade_table();
    
    // Crear tabla de zonas Cloudflare
    dominios_reseller_create_cloudflare_table();
}

/**
 * Crear tabla de zonas Cloudflare
 */
function dominios_reseller_create_cloudflare_table() {
    // Cargar clase si no está cargada
    $class_file = plugin_dir_path(__FILE__) . 'includes/class-cloudflare-service.php';
    if (file_exists($class_file) && !class_exists('Dominios_Reseller_Cloudflare_Service')) {
        require_once $class_file;
    }
    if (class_exists('Dominios_Reseller_Cloudflare_Service')) {
        Dominios_Reseller_Cloudflare_Service::create_table();
    }
    
    // Crear tablas de onboarding
    dominios_reseller_create_onboarding_tables();
}

/**
 * Crear tablas de onboarding (Fase 2)
 */
function dominios_reseller_create_onboarding_tables() {
    $class_file = plugin_dir_path(__FILE__) . 'includes/class-onboarding-db.php';
    if (file_exists($class_file) && !class_exists('Dominios_Reseller_Onboarding_DB')) {
        require_once $class_file;
    }
    if (class_exists('Dominios_Reseller_Onboarding_DB')) {
        Dominios_Reseller_Onboarding_DB::create_tables();
        // Actualizar presets existentes con nuevas versiones
        Dominios_Reseller_Onboarding_DB::update_existing_presets();
    }
}

// Función para actualizar tabla en versiones existentes
function dominios_reseller_upgrade_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'dominios_reseller';
    
    // Añadir columna server si no existe
    $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                                WHERE table_name = '$table' AND column_name = 'server'");
    if(empty($row)) {
        $wpdb->query("ALTER TABLE $table ADD COLUMN server varchar(10) NOT NULL DEFAULT 'uk' AFTER domain");
        
        // Eliminar índice UNIQUE anterior si existe
        $wpdb->query("ALTER TABLE $table DROP INDEX domain, ADD KEY idx_domain_old (domain)");
        
        // Añadir nuevo índice único compuesto (domain + server)
        // Primero verificar que no haya duplicados
        $duplicates = $wpdb->get_results("
            SELECT domain, server, COUNT(*) as count 
            FROM $table 
            GROUP BY domain, server 
            HAVING count > 1
        ");
        
        if (!empty($duplicates)) {
            // Eliminar duplicados dejando el más reciente
            foreach ($duplicates as $dup) {
                $wpdb->query($wpdb->prepare("
                    DELETE t1 FROM $table t1
                    INNER JOIN $table t2 
                    WHERE t1.id < t2.id 
                    AND t1.domain = %s 
                    AND t1.server = %s
                ", $dup->domain, $dup->server));
            }
        }
        
        // Ahora sí añadir el índice único
        $wpdb->query("ALTER TABLE $table ADD UNIQUE KEY domain_server (domain, server)");
        $wpdb->query("ALTER TABLE $table ADD KEY idx_server (server)");
    }
    
    // Cambiar co2_evaded a DECIMAL para mayor precisión
    $wpdb->query("ALTER TABLE $table MODIFY co2_evaded decimal(10,2) DEFAULT 0");
    
    // Añadir last_sync si no existe
    $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                                WHERE table_name = '$table' AND column_name = 'last_sync'");
    if(empty($row)) {
        $wpdb->query("ALTER TABLE $table ADD COLUMN last_sync TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER startdate");
    }
    
    // ═══════════════════════════════════════════════════════════════
    // v1.6.50+ Columnas para PHP Health Check Endpoint
    // ═══════════════════════════════════════════════════════════════
    
    // endpoint_token - Token único del endpoint desplegado
    $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                                WHERE table_name = '$table' AND column_name = 'endpoint_token'");
    if(empty($row)) {
        $wpdb->query("ALTER TABLE $table ADD COLUMN endpoint_token VARCHAR(24) DEFAULT NULL");
    }
    
    // endpoint_deployed_at - Cuándo se desplegó el endpoint
    $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                                WHERE table_name = '$table' AND column_name = 'endpoint_deployed_at'");
    if(empty($row)) {
        $wpdb->query("ALTER TABLE $table ADD COLUMN endpoint_deployed_at DATETIME DEFAULT NULL");
    }
    
    // php_info - JSON con toda la info PHP del servidor (comprimido)
    $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                                WHERE table_name = '$table' AND column_name = 'php_info'");
    if(empty($row)) {
        $wpdb->query("ALTER TABLE $table ADD COLUMN php_info MEDIUMTEXT DEFAULT NULL");
    }
    
    // php_info_updated_at - Última actualización de PHP info
    $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                                WHERE table_name = '$table' AND column_name = 'php_info_updated_at'");
    if(empty($row)) {
        $wpdb->query("ALTER TABLE $table ADD COLUMN php_info_updated_at DATETIME DEFAULT NULL");
    }
    
    // wp_readiness_score - Puntuación de 0-100 para WP readiness
    $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                                WHERE table_name = '$table' AND column_name = 'wp_readiness_score'");
    if(empty($row)) {
        $wpdb->query("ALTER TABLE $table ADD COLUMN wp_readiness_score TINYINT UNSIGNED DEFAULT NULL");
    }
}

// ✅ OPTIMIZACIÓN: Upgrade solo en admin (evita ejecución en frontend)
add_action('admin_init', function() {
    $current_version = get_option('dominios_reseller_db_version', '0');
    if (version_compare($current_version, '1.6.50', '<')) {
        dominios_reseller_upgrade_table();
        update_option('dominios_reseller_db_version', '1.6.50');
    }
});

// ═══════════════════════════════════════════════════════════════
// CRON: Sincronización automática de WHM a las 3:00 AM
// ═══════════════════════════════════════════════════════════════

/**
 * Programar cron de sincronización WHM (se ejecuta en activación del plugin)
 */
function dominios_reseller_schedule_whm_sync() {
    if (!wp_next_scheduled('dominios_reseller_daily_whm_sync')) {
        // Calcular timestamp para las 3:00 AM de mañana en zona horaria del sitio
        $timezone = wp_timezone();
        $tomorrow_3am = new DateTime('tomorrow 03:00', $timezone);
        wp_schedule_event($tomorrow_3am->getTimestamp(), 'daily', 'dominios_reseller_daily_whm_sync');
    }
}

/**
 * Desprogramar cron (se ejecuta en desactivación del plugin)
 */
function dominios_reseller_unschedule_whm_sync() {
    $timestamp = wp_next_scheduled('dominios_reseller_daily_whm_sync');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'dominios_reseller_daily_whm_sync');
    }
}

/**
 * Ejecutar sincronización WHM (callback del cron)
 */
add_action('dominios_reseller_daily_whm_sync', 'dominios_reseller_cron_sync_whm');
function dominios_reseller_cron_sync_whm() {
    $opts = get_option('dominios_reseller_options', []);
    $results = [];
    
    // Sincronizar servidor UK
    if (!empty($opts['uk_whm_token'])) {
        $results['uk'] = dominios_reseller_sync_from_whm('uk', $opts['uk_whm_token']);
    }
    
    // Sincronizar servidor USA
    if (!empty($opts['usa_whm_token'])) {
        $results['usa'] = dominios_reseller_sync_from_whm('usa', $opts['usa_whm_token']);
    }
    
    // Log del resultado
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[Dominios Reseller] Sincronización WHM automática (3:00 AM): ' . print_r($results, true));
    }
    
    // Guardar timestamp de última sincronización automática
    update_option('dominios_reseller_last_auto_sync', [
        'timestamp' => current_time('timestamp'),
        'results' => $results
    ]);
}

// Asegurar que el cron esté programado (por si se activó antes de añadir esta función)
add_action('admin_init', function() {
    dominios_reseller_schedule_whm_sync();
}, 20);

// AJAX handler para guardar cambios de árboles y CO2
add_action('wp_ajax_dr_save_domain_data', function() {
    check_ajax_referer('dr_admin_nonce', '_nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permisos insuficientes');
    }
    
    $domain = sanitize_text_field($_POST['domain'] ?? '');
    $server = sanitize_text_field($_POST['server'] ?? '');
    $trees = intval($_POST['trees'] ?? 0);
    $co2 = floatval($_POST['co2'] ?? 0);
    
    if (empty($domain) || empty($server)) {
        wp_send_json_error('Datos incompletos');
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'dominios_reseller';
    
    $result = $wpdb->update(
        $table,
        [
            'trees_planted' => $trees,
            'co2_evaded' => $co2
        ],
        [
            'domain' => $domain,
            'server' => $server
        ],
        ['%d', '%f'],
        ['%s', '%s']
    );
    
    if ($result === false) {
        wp_send_json_error('Error al guardar: ' . $wpdb->last_error);
    }
    
    wp_send_json_success(['message' => 'Datos guardados correctamente']);
});

// AJAX handler para guardar múltiples cambios
add_action('wp_ajax_dr_save_bulk_domain_data', function() {
    check_ajax_referer('dominios_reseller_nonce', '_nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permisos insuficientes');
    }
    
    $changes = $_POST['changes'] ?? [];
    if (empty($changes) || !is_array($changes)) {
        wp_send_json_error('No hay cambios para guardar');
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'dominios_reseller';
    $updated = 0;
    $errors = [];
    
    foreach ($changes as $change) {
        $domain = sanitize_text_field($change['domain'] ?? '');
        $server = sanitize_text_field($change['server'] ?? '');
        $trees = intval($change['trees'] ?? 0);
        $co2 = floatval($change['co2'] ?? 0);
        
        if (empty($domain) || empty($server)) {
            $errors[] = "Datos incompletos para $domain";
            continue;
        }
        
        $result = $wpdb->update(
            $table,
            [
                'trees_planted' => $trees,
                'co2_evaded' => $co2
            ],
            [
                'domain' => $domain,
                'server' => $server
            ],
            ['%d', '%f'],
            ['%s', '%s']
        );
        
        if ($result === false) {
            $errors[] = "Error al guardar $domain: " . $wpdb->last_error;
        } else {
            $updated++;
        }
    }
    
    wp_send_json_success([
        'message' => "Guardados $updated dominios correctamente" . (count($errors) > 0 ? '. Errores: ' . count($errors) : ''),
        'updated' => $updated,
        'errors' => $errors
    ]);
});

// AJAX: Cargar más dominios con paginación
add_action('wp_ajax_dr_load_more_domains', function() {
    check_ajax_referer('dominios_reseller_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permisos insuficientes']);
    }
    
    global $wpdb;
    $tabla = $wpdb->prefix . 'dominios_reseller';
    
    $offset = intval($_POST['offset'] ?? 0);
    $limit = 50;
    $view = sanitize_text_field($_POST['view'] ?? 'table');
    
    // Obtener dominios
    $domains = $wpdb->get_results("SELECT * FROM $tabla ORDER BY server, domain LIMIT $limit OFFSET $offset");
    
    // Contar total
    $total = $wpdb->get_var("SELECT COUNT(*) FROM $tabla");
    
    // Obtener Cloudflare matches para los dominios
    $cf_matches = [];
    if (function_exists('dominios_reseller_check_all_cf')) {
        $cf_domains = dominios_reseller_check_all_cf();
        if (is_array($cf_domains)) {
            foreach ($cf_domains as $cf_domain) {
                $cf_matches[$cf_domain['name']] = $cf_domain;
            }
        }
    }
    
    // Agrupar por primary_domain
    $grouped_domains = [];
    $primary_list = [];
    foreach ($domains as $row) {
        $domain = $row->domain;
        $status = $row->status ?? 'Activo';
        $primary = $row->primary_domain ?? $domain;
        
        if ($status !== 'Addon' || $domain === $primary) {
            if (!isset($grouped_domains[$domain])) {
                $grouped_domains[$domain] = ['main' => $row, 'addons' => []];
                $primary_list[] = $domain;
            }
        } else {
            if (!isset($grouped_domains[$primary])) {
                $grouped_domains[$primary] = ['main' => null, 'addons' => []];
            }
            $grouped_domains[$primary]['addons'][] = $row;
        }
    }
    
    ob_start();
    
    if ($view === 'table') {
        // Renderizar filas de tabla
        foreach ($primary_list as $primary_domain) {
            $group = $grouped_domains[$primary_domain];
            $row = $group['main'];
            if (!$row) continue;
            
            $dominio = esc_html($row->domain);
            $server = strtoupper($row->server ?? 'UK');
            $server_lower = strtolower($server);
            $status = $row->status ?? 'Activo';
            $startdate = $row->startdate;
            $trees = (int)($row->trees_planted ?? 0);
            $co2 = (float)($row->co2_evaded ?? 0);
            $addon_count_this = count($group['addons']);
            $cf_match = $cf_matches[$primary_domain] ?? null;
            $status_class = ($status === 'Activo') ? 'status-active' : (($status === 'Suspendido') ? 'status-suspended' : '');
            $row_class = 'unified-domain-row main-domain-row';
            if ($status === 'Suspendido') $row_class .= ' suspended-row';
            if ($addon_count_this > 0) $row_class .= ' has-addons';
            
            echo '<tr class="' . $row_class . '" data-domain="' . esc_attr($dominio) . '" data-server="' . esc_attr($server_lower) . '" data-status="' . esc_attr($status) . '" data-primary="' . esc_attr($primary_domain) . '">';
            echo "<td class='domain-cell'>";
            if ($addon_count_this > 0) {
                echo "<button class='addon-toggle' data-primary='" . esc_attr($primary_domain) . "' title='Mostrar {$addon_count_this} addon(s)'>▶</button> ";
            }
            echo "<strong>$dominio</strong>";
            if ($addon_count_this > 0) {
                echo " <span class='addon-count'>+{$addon_count_this}</span>";
            }
            echo "</td>";
            echo "<td class='server-cell'><span class='badge-server badge-$server_lower'>$server</span></td>";
            echo "<td class='cf-cell'>";
            if ($cf_match) {
                $cf_status = $cf_match['status'] ?? 'unknown';
                $cf_icon = $cf_status === 'active' ? '🟢' : ($cf_status === 'pending' ? '🟡' : '🔴');
                echo "<span title='Cloudflare: $cf_status'>$cf_icon</span>";
            } else {
                echo "<span title='No en Cloudflare'>⚪</span>";
            }
            echo "</td>";
            echo "<td class='status-cell'><span class='badge-status $status_class'>$status</span></td>";
            echo "<td class='startdate-cell'><small>" . ($startdate ? esc_html($startdate) : 'N/A') . "</small></td>";
            echo "<td class='registered-cell'><small>" . esc_html($row->registered_at) . "</small></td>";
            echo "<td class='trees-cell'><input type='number' class='small-input trees-input' data-domain='$dominio' data-server='$server_lower' value='$trees' min='0' /></td>";
            echo "<td class='co2-cell'><input type='number' step='0.01' class='small-input co2-input' data-domain='$dominio' data-server='$server_lower' value='$co2' min='0' /></td>";
            echo "<td class='actions-cell'><button class='button button-small calculate-emissions' data-domain='$dominio' data-server='$server_lower'>Calcular</button></td>";
            echo '</tr>';
            
            // Addons ocultos
            foreach ($group['addons'] as $addon) {
                $addon_domain = esc_html($addon->domain);
                $addon_trees = (int)($addon->trees_planted ?? 0);
                $addon_co2 = (float)($addon->co2_evaded ?? 0);
                echo '<tr class="addon-row" data-primary="' . esc_attr($primary_domain) . '" style="display:none;">';
                echo "<td class='domain-cell addon-indent'>↳ $addon_domain <span class='addon-label'>Addon</span></td>";
                echo "<td class='server-cell'><span class='badge-server badge-$server_lower'>$server</span></td>";
                echo "<td class='cf-cell'>—</td>";
                echo "<td class='status-cell'><span class='badge-status'>Addon</span></td>";
                echo "<td class='startdate-cell'><small>—</small></td>";
                echo "<td class='registered-cell'><small>—</small></td>";
                echo "<td class='trees-cell'><input type='number' class='small-input trees-input' data-domain='$addon_domain' data-server='$server_lower' value='$addon_trees' min='0' /></td>";
                echo "<td class='co2-cell'><input type='number' step='0.01' class='small-input co2-input' data-domain='$addon_domain' data-server='$server_lower' value='$addon_co2' min='0' /></td>";
                echo "<td class='actions-cell'><button class='button button-small calculate-emissions' data-domain='$addon_domain' data-server='$server_lower'>Calcular</button></td>";
                echo '</tr>';
            }
        }
    } else {
        // Renderizar cards
        foreach ($primary_list as $primary_domain) {
            $group = $grouped_domains[$primary_domain];
            $row = $group['main'];
            if (!$row) continue;
            
            $dominio = esc_html($row->domain);
            $server = strtoupper($row->server ?? 'UK');
            $server_lower = strtolower($server);
            $status = $row->status ?? 'Activo';
            $startdate = $row->startdate;
            $trees = (int)($row->trees_planted ?? 0);
            $co2 = (float)($row->co2_evaded ?? 0);
            $cf_match = $cf_matches[$primary_domain] ?? null;
            
            echo '<div class="domain-card domain-card-pro" data-domain="' . esc_attr($dominio) . '" data-server="' . esc_attr($server_lower) . '" data-status="' . esc_attr($status) . '">';
            echo '<div class="card-header">';
            echo '<h4>' . $dominio . '</h4>';
            echo '<span class="badge-server badge-' . $server_lower . '">' . $server . '</span>';
            echo '</div>';
            echo '<div class="card-body">';
            echo '<div class="card-row"><strong>Estado:</strong> ' . $status . '</div>';
            if ($cf_match) {
                $cf_status = $cf_match['status'] ?? 'unknown';
                echo '<div class="card-row"><strong>Cloudflare:</strong> ' . ucfirst($cf_status) . '</div>';
            }
            echo '<div class="card-row"><strong>Inicio WHM:</strong> ' . ($startdate ?: 'N/A') . '</div>';
            echo '<div class="card-row"><strong>Alta Replanta:</strong> ' . esc_html($row->registered_at) . '</div>';
            echo '<div class="card-row"><label>Árboles:</label><input type="number" class="small-input trees-input-card" data-domain="' . $dominio . '" data-server="' . $server_lower . '" value="' . $trees . '" min="0" /></div>';
            echo '<div class="card-row"><label>CO2 (g):</label><input type="number" step="0.01" class="small-input co2-input-card" data-domain="' . $dominio . '" data-server="' . $server_lower . '" value="' . $co2 . '" min="0" /></div>';
            echo '</div>';
            echo '<div class="card-actions">';
            echo '<button class="button button-small calculate-emissions-card" data-domain="' . $dominio . '" data-server="' . $server_lower . '">Calcular</button>';
            echo '</div>';
            echo '</div>';
        }
    }
    
    $html = ob_get_clean();
    
    wp_send_json_success([
        'html' => $html,
        'loaded' => count($domains),
        'total' => intval($total),
        'has_more' => ($offset + $limit) < $total
    ]);
});

/**
 * Función PRO: Sincroniza dominios desde WHM a base de datos local
 * Esta es la función CENTRAL que reemplaza todas las consultas directas a WHM
 * @param string $server 'uk' o 'usa'
 * @param string $token Token de WHM
 * @return array Resultado de la sincronización
 */
function dominios_reseller_sync_from_whm($server, $token) {
    if (empty($token)) {
        return ['success' => false, 'error' => 'Token vacío'];
    }
    
    global $wpdb;
    $tabla = $wpdb->prefix . 'dominios_reseller';
    
    // Obtener cuentas desde WHM
    $cuentas = obtener_cuentas_whm($token, $server);
    if (!$cuentas || empty($cuentas['data']['acct'])) {
        return ['success' => false, 'error' => 'No se obtuvieron cuentas de WHM'];
    }
    
    $inserted = 0;
    $updated = 0;
    $deleted = 0;
    
    // Recopilar todos los dominios que existen en WHM (principales + addons)
    $dominios_en_whm = [];
    
    foreach ($cuentas['data']['acct'] as $cuenta) {
        $dominio = sanitize_text_field($cuenta['domain']);
        $dominios_en_whm[] = $dominio;
        
        $startdate = intval($cuenta['unix_startdate']);
        $status = $cuenta['suspended'] ? 'Suspendido' : 'Activo';
        $fecha_emision = date('Y-m-d', $startdate);
        $validez = date('Y-m-d', strtotime("$fecha_emision +1 year"));
        
        // Verificar si existe (por domain + server)
        $existe = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $tabla WHERE domain = %s AND server = %s",
            $dominio, $server
        ));
        
        if ($existe) {
            // Actualizar solo campos de WHM (no tocar trees_planted ni co2_evaded)
            $wpdb->update($tabla, [
                'status' => $status,
                'startdate' => $startdate,
                'fecha_emision' => $fecha_emision,
                'validez' => $validez
            ], [
                'domain' => $dominio,
                'server' => $server
            ]);
            $updated++;
        } else {
            // Insertar nuevo dominio
            $wpdb->insert($tabla, [
                'domain' => $dominio,
                'server' => $server,
                'status' => $status,
                'startdate' => $startdate,
                'fecha_emision' => $fecha_emision,
                'validez' => $validez,
                'primary_domain' => $dominio,
                'is_primary' => 1,
                'trees_planted' => 0,
                'co2_evaded' => 0
            ]);
            $inserted++;
        }
        
        // Procesar addons
        $addons = obtener_addons_de_usuario($cuenta['user'], $token, $server);
        if (is_array($addons)) {
            foreach ($addons as $addon) {
                if (!is_array($addon) || !isset($addon['domain'])) continue;
                
                $addon_domain = sanitize_text_field($addon['domain']);
                $dominios_en_whm[] = $addon_domain; // Añadir addon a la lista
                
                $existe_addon = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $tabla WHERE domain = %s AND server = %s",
                    $addon_domain, $server
                ));
                
                if ($existe_addon) {
                    $wpdb->update($tabla, [
                        'status' => 'Addon',
                        'startdate' => $startdate,
                        'fecha_emision' => $fecha_emision,
                        'validez' => $validez
                    ], [
                        'domain' => $addon_domain,
                        'server' => $server
                    ]);
                    $updated++;
                } else {
                    $wpdb->insert($tabla, [
                        'domain' => $addon_domain,
                        'server' => $server,
                        'status' => 'Addon',
                        'startdate' => $startdate,
                        'fecha_emision' => $fecha_emision,
                        'validez' => $validez,
                        'primary_domain' => $dominio,
                        'is_primary' => 0,
                        'trees_planted' => 0,
                        'co2_evaded' => 0
                    ]);
                    $inserted++;
                }
            }
        }
    }
    
    // LIMPIEZA: Eliminar dominios de este servidor que ya no existen en WHM
    $dominios_en_db = $wpdb->get_col($wpdb->prepare(
        "SELECT domain FROM $tabla WHERE server = %s",
        $server
    ));
    
    $dominios_a_eliminar = array_diff($dominios_en_db, $dominios_en_whm);
    
    foreach ($dominios_a_eliminar as $dominio_borrar) {
        $wpdb->delete($tabla, [
            'domain' => $dominio_borrar,
            'server' => $server
        ]);
        $deleted++;
        error_log("[Dominios Reseller] Dominio eliminado de DB (ya no existe en WHM): $dominio_borrar ($server)");
    }
    
    return [
        'success' => true,
        'inserted' => $inserted,
        'updated' => $updated,
        'deleted' => $deleted,
        'total' => count($cuentas['data']['acct'])
    ];
}

// Incluir archivos del plugin
foreach ([
    'includes/whm-functions.php',
    'includes/emisiones-functions.php',
    'includes/emisiones-co2-api.php',
    'includes/ajax-handlers.php',
    'includes/shortcodes.php',
    'includes/scripts.php',
    'includes/rest-api.php',
    'includes/class-cloudflare-service.php',
    'includes/cloudflare-admin.php',
    'includes/cloudflare-cron.php',
    // Fase 2: Onboarding Cloudflare
    'includes/class-onboarding-db.php',
    'includes/class-onboarding-worker.php',
    'includes/class-openprovider-service.php',
    'includes/class-onboarding-admin.php',
    'includes/class-presets-admin.php',
    'includes/class-debug-hub.php',
    // Nuevas integraciones v1.6.0
    'includes/class-auto-discovery.php',
    'includes/class-upmind-integration.php',
    'includes/class-integration-settings.php',
    'includes/class-monitoring-dashboard.php',
    // Página simple de gestión
    'includes/admin-simple-domains.php',
    // Módulo Tree Nation · Impacto ecológico
    'includes/class-tree-nation.php',
    // Forest Program · Automatización plantación de árboles v1.9.0
    'includes/class-forest-program.php',
    'includes/class-forest-admin.php',
] as $file) {
    $path = plugin_dir_path(__FILE__) . $file;
    if (file_exists($path)) require_once $path;
}

if (class_exists('Dominios_Reseller_Tree_Nation')) {
    Dominios_Reseller_Tree_Nation::init();
}

// Inicializar Forest Program
if (class_exists('Dominios_Reseller_Forest_Program')) {
    Dominios_Reseller_Forest_Program::get_instance();
}

// Cargar textdomain para traducciones
add_action('init', function() {
    load_plugin_textdomain('dominios-reseller', false, dirname(plugin_basename(__FILE__)) . '/languages/');
});

// Registrar página del plugin en el admin
add_action('admin_menu', function () {
    // Icono SVG de Cloudflare codificado en base64
    $cf_icon = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" fill="#F38020"><path d="M38.804 29.561l-.01-.04a1.089 1.089 0 00-1.05-.81H15.198a.545.545 0 01-.455-.845l1.305-2a1.09 1.09 0 01.91-.49h23.07c2.855 0 5.336-2 5.88-4.79a6.366 6.366 0 00-4.495-7.385 9.093 9.093 0 00-17.34-2.58 6.355 6.355 0 00-10.06 4.63 6.287 6.287 0 00.22 2.115A9.552 9.552 0 003.5 26.07a9.458 9.458 0 001.09 4.43c.187.34.548.555.943.555h32.21a1.09 1.09 0 001.06-1.49v-.005zm3.335-.375a.86.86 0 01-.05.425 1.09 1.09 0 01-1.045.775h-2.99a.545.545 0 01-.455-.845l1.305-2a1.09 1.09 0 01.91-.49h1.16c.515 0 .98.36 1.085.865.04.19.06.365.075.545a3.28 3.28 0 01.005.725z"/></svg>');
    
    add_menu_page(
        'Dominios Reseller',
        'Dominios Reseller',
        'manage_options',
        'dominios-reseller',
        'dominios_reseller_admin_page',
        $cf_icon,
        56
    );
    
    // Submenu: Impacto Ecológico (Tree Nation)
    add_submenu_page(
        'dominios-reseller',
        '🌱 Impacto Ecológico',
        '🌱 Impacto Ecológico',
        'manage_options',
        'dominios-reseller-tree-nation',
        ['Dominios_Reseller_Tree_Nation', 'render_page']
    );

    // Submenu de diagnóstico (oculto, solo accesible por URL)
    add_submenu_page(
        null, // No aparece en menú
        'Diagnóstico',
        'Diagnóstico',
        'manage_options',
        'dominios-reseller-diagnostic',
        'dominios_reseller_diagnostic_page'
    );
});

// Cargar assets inline como fallback - SIEMPRE
function dominios_reseller_inline_assets() {
    // Generar nonce para AJAX
    $dr_nonce = wp_create_nonce('dominios_reseller_nonce');
    
    // Forzar carga inline para desarrollo rápido
    echo '<style id="dominios-reseller-inline-css">
    /* Dominios Reseller Inline CSS v1.5.0 - Estilo Replanta */
    .dominios-reseller-admin {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif;
        max-width: none;
        margin: 20px 20px 20px 0;
    }
    
    /* Notices fuera de la cabecera */
    .dominios-reseller-admin .dr-notices {
        margin-bottom: 15px;
    }
    .dominios-reseller-admin .dr-notices .notice {
        margin: 0 0 10px 0;
    }
    
    /* Header Replanta Style */
    .dr-header {
        background: linear-gradient(135deg, #166534 0%, #15803d 50%, #22c55e 100%);
        border-radius: 16px 16px 0 0;
        padding: 0;
        position: relative;
        overflow: hidden;
    }
    .dr-header::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url("data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.05\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        opacity: 0.5;
    }
    .dr-header-content {
        position: relative;
        z-index: 1;
        padding: 30px 40px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 20px;
    }
    .dr-header-left {
        display: flex;
        align-items: center;
        gap: 20px;
    }
    .dr-logo {
        width: 60px;
        height: 60px;
        background: rgba(255,255,255,0.15);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(10px);
    }
    .dr-logo svg {
        width: 36px;
        height: 36px;
        fill: white;
    }
    .dr-header-text h1 {
        margin: 0 0 5px 0;
        font-size: 1.8em;
        font-weight: 700;
        color: white;
        text-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .dr-header-text .dr-tagline {
        margin: 0;
        font-size: 0.95em;
        color: rgba(255,255,255,0.9);
        font-weight: 400;
    }
    .dr-header-stats {
        display: flex;
        gap: 25px;
    }
    .dr-stat {
        text-align: center;
        background: rgba(255,255,255,0.1);
        padding: 12px 20px;
        border-radius: 12px;
        backdrop-filter: blur(10px);
    }
    .dr-stat-value {
        font-size: 1.6em;
        font-weight: 700;
        color: white;
        line-height: 1.2;
    }
    .dr-stat-label {
        font-size: 0.75em;
        color: rgba(255,255,255,0.8);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    /* Main container */
    .dr-main {
        background: #f8fafc;
        border-radius: 0 0 16px 16px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        overflow: hidden;
    }
    
    .server-tabs {
        background: white;
        min-height: 600px;
    }
    .tab-buttons {
        display: flex;
        background: white;
        border-bottom: 1px solid #e2e8f0;
        margin: 0;
        padding: 0 20px;
        gap: 5px;
    }
    .tab-button {
        background: transparent;
        border: none;
        border-bottom: 3px solid transparent;
        color: #64748b;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        padding: 16px 20px;
        transition: all 0.2s ease;
        white-space: nowrap;
        margin-bottom: -1px;
    }
    .tab-button:hover {
        background: #f1f5f9;
        color: #166534;
    }
    .tab-button.active {
        background: transparent;
        border-bottom-color: #22c55e;
        color: #166534;
    }
    .tab-pane {
        display: none;
        min-height: 500px;
        padding: 30px;
    }
    .tab-pane.active {
        display: block;
    }
    .unified-domains-table,
    .domains-table {
        background: white;
        border-collapse: collapse;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        overflow: hidden;
        width: 100%;
    }
    .unified-domains-table thead,
    .domains-table thead {
        background: linear-gradient(135deg, #166534 0%, #15803d 100%);
        color: white;
    }
    .unified-domains-table th,
    .domains-table th {
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 0.5px;
        padding: 14px 12px;
        text-align: left;
        text-transform: uppercase;
    }
    .unified-domains-table td,
    .domains-table td {
        border-bottom: 1px solid #f1f5f9;
        padding: 12px;
        vertical-align: middle;
    }
    .suspended-row {
        background-color: #fef2f2 !important;
        border-left: 4px solid #ef4444 !important;
    }
    .server-badge {
        border-radius: 6px;
        display: inline-block;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.5px;
        padding: 4px 10px;
        text-transform: uppercase;
    }
    .server-uk {
        background: #dbeafe;
        color: #1e40af;
    }
    .server-usa {
        background: #fee2e2;
        color: #991b1b;
    }
    .status-badge {
        border-radius: 6px;
        display: inline-block;
        font-size: 11px;
        font-weight: 600;
        padding: 4px 10px;
        text-transform: uppercase;
    }
    .status-active {
        background: #dcfce7;
        color: #166534;
    }
    .status-suspended {
        background: #fee2e2;
        color: #991b1b;
    }
    .filter-controls {
        align-items: center;
        display: flex;
        gap: 15px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    .filter-select {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        min-width: 150px;
        padding: 10px 14px;
        transition: border-color 0.2s;
    }
    .filter-select:focus {
        border-color: #22c55e;
        outline: none;
    }
    .save-all-unified, .refresh-unified {
        border: none;
        border-radius: 8px;
        color: white;
        cursor: pointer;
        font-weight: 600;
        margin-right: 10px;
        padding: 12px 24px;
        transition: transform 0.1s, box-shadow 0.2s;
    }
    .save-all-unified:hover, .refresh-unified:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .save-all-unified {
        background: linear-gradient(135deg, #166534 0%, #22c55e 100%);
    }
    .refresh-unified {
        background: #64748b;
    }
    
    /* Server section headers */
    .server-section .server-header h3 {
        color: #166534;
        font-size: 1.2em;
        margin: 0 0 15px 0;
    }
    .server-section .server-header h3 small {
        color: #64748b;
        font-weight: 400;
    }
    
    /* Form settings estilo */
    .form-table th {
        color: #334155;
        font-weight: 600;
    }
    .form-table input[type="text"],
    .form-table input[type="password"] {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 10px 14px;
    }
    .form-table input[type="text"]:focus,
    .form-table input[type="password"]:focus {
        border-color: #22c55e;
        box-shadow: 0 0 0 3px rgba(34,197,94,0.1);
        outline: none;
    }
    </style>';
    
    // JavaScript con nonce
    ?>
    <script id="dominios-reseller-inline-js">
    jQuery(document).ready(function($) {
        console.log("Dominios Reseller Inline JS v1.1.3 loaded");
        
        var drNonce = "<?php echo esc_js($dr_nonce); ?>";
        
        // Define AJAX object for compatibility
        window.dominios_reseller_ajax = {
            ajax_url: "<?php echo esc_js(admin_url('admin-ajax.php')); ?>",
            nonce: drNonce,
            mensaje_guardado: "<?php echo esc_js(__('Cambios guardados correctamente', 'dominios-reseller')); ?>",
            mensaje_error: "<?php echo esc_js(__('Error al guardar los cambios', 'dominios-reseller')); ?>"
        };
        
        // Tabs functionality
        $(".tab-button").on("click", function() {
            var targetTab = $(this).data("tab");
            $(".tab-button").removeClass("active");
            $(".tab-pane").removeClass("active");
            $(this).addClass("active");
            $("#" + targetTab + "-tab").addClass("active");
        });
        
        // Filter functionality
        $("#server-filter, #status-filter").on("change", function() {
            var serverFilter = $("#server-filter").val();
            var statusFilter = $("#status-filter").val();
            
            // Filtrar tabla
            $("#unified-domains-table tbody tr").each(function() {
                var $row = $(this);
                var server = $row.data("server");
                var status = $row.data("status");
                var showRow = true;
                
                if (serverFilter && server !== serverFilter.toLowerCase()) {
                    showRow = false;
                }
                if (statusFilter && status !== statusFilter) {
                    showRow = false;
                }
                
                if (showRow) {
                    $row.show();
                } else {
                    $row.hide();
                }
            });
            
            // Filtrar cards
            $(".domain-card-pro").each(function() {
                var $card = $(this);
                var server = $card.data("server");
                var status = $card.data("status");
                var showCard = true;
                
                if (serverFilter && server !== serverFilter.toLowerCase()) {
                    showCard = false;
                }
                if (statusFilter && status !== statusFilter) {
                    showCard = false;
                }
                
                if (showCard) {
                    $card.show();
                } else {
                    $card.hide();
                }
            });
        });
        
        // View toggle functionality
        $("#view-table, #view-cards").on("click", function() {
            var view = $(this).attr("id").replace("view-", "");
            $(".view-btn").removeClass("active");
            $(this).addClass("active");
            
            if (view === "table") {
                $("#unified-domains-table").show();
                $(".unified-actions").show();
                $("#cards-view").hide();
            } else {
                $("#unified-domains-table").hide();
                $(".unified-actions").hide();
                $("#cards-view").show();
            }
        });
        
        // Toggle addons (expandir/colapsar)
        $(document).on("click", ".addon-toggle", function() {
            var $btn = $(this);
            var primary = $btn.data("primary");
            var $addons = $(".addon-of-" + primary.replace(/\./g, "\\."));
            
            if ($btn.hasClass("expanded")) {
                $btn.removeClass("expanded").text("▶");
                $addons.slideUp(150);
            } else {
                $btn.addClass("expanded").text("▼");
                $addons.slideDown(150);
            }
        });
        
        // Expandir todos los addons
        $(".expand-all-addons").on("click", function() {
            $(".addon-toggle").addClass("expanded").text("▼");
            $(".addon-row").slideDown(150);
        });
        
        // Colapsar todos los addons
        $(".collapse-all-addons").on("click", function() {
            $(".addon-toggle").removeClass("expanded").text("▶");
            $(".addon-row").slideUp(150);
        });
        
        // Refresh functionality
        $(".refresh-unified, .refresh-cards").on("click", function() {
            location.reload();
        });
        
        // Save functionality for unified table
        $(".save-all-unified").on("click", function() {
            var $btn = $(this);
            var changes = [];
            
            $("#unified-domains-table .trees-input, #unified-domains-table .co2-input").each(function() {
                var $input = $(this);
                var domain = $input.data("domain");
                var server = $input.data("server");
                var field = $input.hasClass("trees-input") ? "trees" : "co2";
                var value = field === "trees" ? parseInt($input.val()) : parseFloat($input.val());
                
                // Find existing change or create new one
                var existing = changes.find(function(c) { return c.domain === domain && c.server === server; });
                if (!existing) {
                    existing = { domain: domain, server: server, trees: 0, co2: 0 };
                    changes.push(existing);
                }
                existing[field] = value;
            });
            
            if (changes.length === 0) {
                alert("No hay cambios para guardar");
                return;
            }
            
            $btn.prop("disabled", true).text("💾 Guardando...");
            
            $.post(ajaxurl, {
                action: "dr_save_bulk_domain_data",
                _nonce: drNonce,
                changes: changes
            }, function(response) {
                $btn.prop("disabled", false).text("💾 Guardar todos los cambios");
                
                if (response.success) {
                    alert(response.data.message);
                } else {
                    alert("Error: " + (response.data || "Error desconocido"));
                }
            }).fail(function() {
                $btn.prop("disabled", false).text("💾 Guardar todos los cambios");
                alert("Error de conexión");
            });
        });
        
        // Save functionality for cards
        $(".save-all-cards").on("click", function() {
            var $btn = $(this);
            var changes = [];
            
            $(".domain-card-pro .trees-input-card, .domain-card-pro .co2-input-card").each(function() {
                var $input = $(this);
                var domain = $input.data("domain");
                var server = $input.data("server");
                var field = $input.hasClass("trees-input-card") ? "trees" : "co2";
                var value = field === "trees" ? parseInt($input.val()) : parseFloat($input.val());
                
                // Find existing change or create new one
                var existing = changes.find(function(c) { return c.domain === domain && c.server === server; });
                if (!existing) {
                    existing = { domain: domain, server: server, trees: 0, co2: 0 };
                    changes.push(existing);
                }
                existing[field] = value;
            });
            
            if (changes.length === 0) {
                alert("No hay cambios para guardar");
                return;
            }
            
            $btn.prop("disabled", true).text("Guardando...");
            
            $.post(ajaxurl, {
                action: "dr_save_bulk_domain_data",
                _nonce: drNonce,
                changes: changes
            }, function(response) {
                $btn.prop("disabled", false).text("Guardar todos los cambios");
                
                if (response.success) {
                    alert(response.data.message);
                } else {
                    alert("Error: " + (response.data || "Error desconocido"));
                }
            }).fail(function() {
                $btn.prop("disabled", false).text("💾 Guardar todos los cambios");
                alert("Error de conexión");
            });
        });
        
        // Función para mostrar notificaciones
        function showNotice(message, type) {
            type = type || 'info';
            $('.dominios-reseller-notice').remove();
            
            var noticeClass = type === 'success' ? 'notice-success' :
                             type === 'error' ? 'notice-error' :
                             type === 'warning' ? 'notice-warning' : 'notice-info';
            
            var notice = $('<div class="notice ' + noticeClass + ' dominios-reseller-notice"><p>' + message + '</p></div>');
            $('.dominios-reseller-admin').prepend(notice);
            
            setTimeout(function() {
                notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
        
        // Calcular emisiones CO2 - TABLA VIEW
        $(document).on('click', '.calculate-emissions', function(e) {
            e.preventDefault();
            console.log('Click en botón calcular (tabla)');
            
            var button = $(this);
            var domain = button.data('domain');
            var server = button.data('server');
            var row = button.closest('tr');
            var co2Input = row.find('.co2-input');
            
            console.log('Domain:', domain, 'Server:', server);
            
            if (!domain || !server) {
                showNotice('⚠️ Datos incompletos del dominio', 'warning');
                return;
            }
            
            button.text('⏳ Calculando...').prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'recalcular_co2',
                    domain: domain,
                    server: server,
                    nonce: dominios_reseller_ajax.nonce
                },
                success: function(response) {
                    console.log('Respuesta AJAX:', response);
                    if (response.success) {
                        var co2 = response.data.co2_evaded;
                        var detalles = response.data.detalles;
                        
                        co2Input.val(co2).addClass('changed');
                        
                        var mensaje = '✅ ' + response.data.message + '\n\n';
                        mensaje += 'Detalles del cálculo acumulado:\n';
                        if (detalles.meses_activos) {
                            mensaje += '• Tiempo activo: ' + detalles.meses_activos + ' meses (' + detalles.dias_activo + ' días)\n';
                        }
                        if (detalles.trafico_total_gb !== undefined) {
                            mensaje += '• Tráfico TOTAL acumulado: ' + detalles.trafico_total_gb + ' GB\n';
                        }
                        if (detalles.trafico_mensual_promedio_gb !== undefined) {
                            mensaje += '• Promedio mensual: ' + detalles.trafico_mensual_promedio_gb + ' GB/mes\n';
                        }
                        mensaje += '• CO2 tráfico: ' + detalles.co2_trafico_gramos + ' g\n';
                        mensaje += '• CO2 hosting: ' + detalles.co2_base_gramos + ' g\n';
                        mensaje += '• Total CO2 evitado: ' + detalles.co2_total_gramos + ' g';
                        if (detalles.visitas_estimadas) {
                            mensaje += '\n• Visitas estimadas: ' + detalles.visitas_estimadas;
                        }
                        
                        showNotice(mensaje, 'success');
                    } else {
                        showNotice('❌ ' + (response.data && response.data.message ? response.data.message : 'Error desconocido'), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error AJAX:', error);
                    showNotice('❌ Error de conexión al calcular CO2', 'error');
                },
                complete: function() {
                    button.text('Calcular').prop('disabled', false);
                }
            });
        });
        
        // Calcular emisiones CO2 - CARDS VIEW
        $(document).on('click', '.calculate-emissions-card', function(e) {
            e.preventDefault();
            console.log('Click en botón calcular (card)');
            
            var button = $(this);
            var domain = button.data('domain');
            var server = button.data('server');
            var card = button.closest('.domain-card, .domain-card-pro');
            var co2Input = card.find('.co2-input-card');
            
            console.log('Domain:', domain, 'Server:', server);
            
            if (!domain || !server) {
                showNotice('⚠️ Datos incompletos del dominio', 'warning');
                return;
            }
            
            button.text('⏳ Calculando...').prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'recalcular_co2',
                    domain: domain,
                    server: server,
                    nonce: dominios_reseller_ajax.nonce
                },
                success: function(response) {
                    console.log('Respuesta AJAX:', response);
                    if (response.success) {
                        var co2 = response.data.co2_evaded;
                        var detalles = response.data.detalles;
                        
                        co2Input.val(co2).addClass('changed');
                        
                        var mensaje = '✅ ' + response.data.message + '\n\n';
                        mensaje += 'Detalles del cálculo acumulado:\n';
                        if (detalles.meses_activos) {
                            mensaje += '• Tiempo activo: ' + detalles.meses_activos + ' meses (' + detalles.dias_activo + ' días)\n';
                        }
                        if (detalles.trafico_total_gb !== undefined) {
                            mensaje += '• Tráfico TOTAL acumulado: ' + detalles.trafico_total_gb + ' GB\n';
                        }
                        if (detalles.trafico_mensual_promedio_gb !== undefined) {
                            mensaje += '• Promedio mensual: ' + detalles.trafico_mensual_promedio_gb + ' GB/mes\n';
                        }
                        mensaje += '• CO2 tráfico: ' + detalles.co2_trafico_gramos + ' g\n';
                        mensaje += '• CO2 hosting: ' + detalles.co2_base_gramos + ' g\n';
                        mensaje += '• Total CO2 evitado: ' + detalles.co2_total_gramos + ' g';
                        if (detalles.visitas_estimadas) {
                            mensaje += '\n• Visitas estimadas: ' + detalles.visitas_estimadas;
                        }
                        
                        showNotice(mensaje, 'success');
                    } else {
                        showNotice('❌ ' + (response.data && response.data.message ? response.data.message : 'Error desconocido'), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error AJAX:', error);
                    showNotice('❌ Error de conexión al calcular CO2', 'error');
                },
                complete: function() {
                    button.text('Calcular').prop('disabled', false);
                }
            });
        });
        
        // Cargar más dominios - PAGINACIÓN
        $(document).on('click', '.load-more-btn', function(e) {
            e.preventDefault();
            console.log('Click en cargar más');
            
            var button = $(this);
            var offset = parseInt(button.data('offset'));
            var view = button.data('view');
            var container = view === 'table' ? $('#unified-domains-table tbody') : $('.cards-grid');
            var spinner = button.next('.load-more-spinner');
            
            button.prop('disabled', true).hide();
            spinner.show();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'dr_load_more_domains',
                    nonce: dominios_reseller_ajax.nonce,
                    offset: offset,
                    view: view
                },
                success: function(response) {
                    console.log('Respuesta load more:', response);
                    if (response.success) {
                        // Agregar el HTML al contenedor
                        container.append(response.data.html);
                        
                        // Actualizar contador
                        var newOffset = offset + response.data.loaded;
                        button.data('offset', newOffset);
                        
                        var remaining = response.data.total - newOffset;
                        button.html('📥 Cargar 50 dominios más (' + remaining + ' restantes)');
                        
                        // Actualizar display de total
                        $('#domains-count-display').html('(' + newOffset + ' de ' + response.data.total + ')');
                        
                        // Mostrar botón si hay más
                        if (response.data.has_more) {
                            button.show();
                        } else {
                            button.closest('.load-more-container').html('<p style="color:#666;">✅ Todos los dominios cargados</p>');
                        }
                        
                        showNotice('✅ Cargados ' + response.data.loaded + ' dominios más', 'success');
                    } else {
                        showNotice('❌ ' + (response.data?.message || 'Error al cargar dominios'), 'error');
                        button.show();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error AJAX load more:', error);
                    showNotice('❌ Error de conexión al cargar más dominios', 'error');
                    button.show();
                },
                complete: function() {
                    spinner.hide();
                    button.prop('disabled', false);
                }
            });
        });
        
        console.log('Event handlers para calcular CO2 registrados');
    });
    </script>
    <?php
}

function dominios_reseller_admin_page() {
    // Inyectar CSS y JS inline como fallback si los archivos no existen
    dominios_reseller_inline_assets();
    
    // Obtener estadísticas para la cabecera
    global $wpdb;
    $tabla = $wpdb->prefix . 'dominios_reseller';
    $total_domains = $wpdb->get_var("SELECT COUNT(*) FROM $tabla");
    $active_domains = $wpdb->get_var("SELECT COUNT(*) FROM $tabla WHERE status = 'Activo'");
    $uk_count = $wpdb->get_var("SELECT COUNT(*) FROM $tabla WHERE server = 'uk'");
    $usa_count = $wpdb->get_var("SELECT COUNT(*) FROM $tabla WHERE server = 'usa'");

    echo '<div class="wrap dominios-reseller-admin">';
    
    // Zona de notices (FUERA de la cabecera)
    echo '<div class="dr-notices">';
    
    // FIX TEMPORAL: Reparar registros con primary_domain NULL
    if (isset($_POST['fix_primary_domain'])) {
        $updated_primary = $wpdb->query("
            UPDATE $tabla 
            SET primary_domain = domain 
            WHERE primary_domain IS NULL 
            AND (status != 'Addon' OR status IS NULL)
        ");
        
        echo '<div class="notice notice-success"><p>✅ Se repararon ' . $updated_primary . ' dominios principales</p></div>';
        
        $remaining = $wpdb->get_var("SELECT COUNT(*) FROM $tabla WHERE primary_domain IS NULL");
        if ($remaining > 0) {
            echo '<div class="notice notice-warning"><p>⚠️ Quedan ' . $remaining . ' registros addon sin parent_domain asignado</p></div>';
        }
    }

    // Mostrar botón de reparación si hay registros con primary_domain NULL
    $null_count = $wpdb->get_var("SELECT COUNT(*) FROM $tabla WHERE primary_domain IS NULL");
    if ($null_count > 0) {
        echo '<div class="notice notice-error">';
        echo '<p><strong>⚠️ PROBLEMA DETECTADO:</strong> Hay ' . $null_count . ' dominios con primary_domain NULL que impiden el registro correcto.</p>';
        echo '<form method="post" style="display:inline;">';
        echo '<input type="hidden" name="fix_primary_domain" value="1">';
        echo '<button type="submit" class="button button-primary">🔧 Reparar Ahora</button>';
        echo '</form>';
        echo '</div>';
    }

    // Manejar test de conexión
    $whm_debug_result = null;
    if (isset($_POST['test_whm_connection'])) {
        $server = sanitize_text_field($_POST['server'] ?? 'uk');
        $options = get_option('dominios_reseller_options');
        $token_key = $server . '_whm_token';
        $token = $options[$token_key] ?? '';

        if (empty($token)) {
            echo '<div class="notice notice-error"><p>❌ Error: Debes configurar primero el API Token de WHM para el servidor ' . strtoupper($server) . '.</p></div>';
        } else {
            echo '<div class="notice notice-info"><p>🔄 Probando conexión con WHM (' . strtoupper($server) . ')...</p></div>';
            $test_result = test_whm_connection($token, $server);

            if ($test_result['success']) {
                echo '<div class="notice notice-success"><p>✅ Conexión exitosa! Se encontraron ' . $test_result['count'] . ' cuentas en WHM (' . strtoupper($server) . ').</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>❌ Error de conexión: ' . esc_html($test_result['error']) . '</p></div>';
            }
        }
    }

    // Debug WHM profundo desde configuración (sin depender del archivo debug-whm-api.php)
    if (isset($_POST['run_whm_debug'])) {
        if (!isset($_POST['dr_whm_debug_nonce']) || !wp_verify_nonce($_POST['dr_whm_debug_nonce'], 'dr_whm_debug_action')) {
            echo '<div class="notice notice-error"><p>❌ Nonce inválido. Recarga la página e inténtalo de nuevo.</p></div>';
        } else {
            $debug_server = sanitize_text_field($_POST['debug_server'] ?? 'uk');
            $debug_cpuser = sanitize_text_field($_POST['debug_cpuser'] ?? '');

            if (empty($debug_cpuser)) {
                echo '<div class="notice notice-error"><p>❌ Debes indicar un usuario cPanel para hacer el diagnóstico.</p></div>';
            } else {
                $whm_debug_result = dominios_reseller_debug_whm_sso($debug_server, $debug_cpuser);
                if ($whm_debug_result['success']) {
                    echo '<div class="notice notice-success"><p>✅ Diagnóstico WHM ejecutado para ' . esc_html(strtoupper($debug_server)) . ' y usuario cPanel <code>' . esc_html($debug_cpuser) . '</code>.</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>❌ Error en diagnóstico WHM: ' . esc_html($whm_debug_result['error']) . '</p></div>';
                }
            }
        }
    }
    
    echo '</div>'; // Fin dr-notices
    
    // Nueva cabecera estilo Replanta
    echo '<div class="dr-header">';
    echo '<div class="dr-header-content">';
    echo '<div class="dr-header-left">';
    echo '<div class="dr-logo">';
    echo '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M17,8C8,10 5.9,16.17 3.82,21.34L5.71,22L6.66,19.7C7.14,19.87 7.64,20 8,20C19,20 22,3 22,3C21,5 14,5.25 9,6.25C4,7.25 2,11.5 2,13.5C2,15.5 3.75,17.25 3.75,17.25C7,8 17,8 17,8Z"/></svg>';
    echo '</div>';
    echo '<div class="dr-header-text">';
    echo '<h1>Dominios Reseller</h1>';
    echo '<p class="dr-tagline">Gestión de dominios certificados ecológicos 🌱</p>';
    echo '</div>';
    echo '</div>';
    echo '<div class="dr-header-stats">';
    echo '<div class="dr-stat"><div class="dr-stat-value">' . intval($total_domains) . '</div><div class="dr-stat-label">Total Dominios</div></div>';
    echo '<div class="dr-stat"><div class="dr-stat-value">' . intval($active_domains) . '</div><div class="dr-stat-label">Activos</div></div>';
    echo '<div class="dr-stat"><div class="dr-stat-value">🇬🇧 ' . intval($uk_count) . '</div><div class="dr-stat-label">UK</div></div>';
    echo '<div class="dr-stat"><div class="dr-stat-value">🇺🇸 ' . intval($usa_count) . '</div><div class="dr-stat-label">USA</div></div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    // Main container
    echo '<div class="dr-main">';

    // Interfaz con pestañas
    echo '<div class="server-tabs">';
    echo '<div class="tab-buttons">';
    echo '<button class="tab-button active" data-tab="all">📊 Todos los Dominios</button>';
    echo '<button class="tab-button" data-tab="uk">🇬🇧 Servidor UK</button>';
    echo '<button class="tab-button" data-tab="usa">🇺🇸 Servidor USA</button>';
    echo '<button class="tab-button" data-tab="settings">⚙️ Configuración</button>';
    echo '</div>';

    // Contenido de pestañas
    echo '<div class="tab-content">';

    // Pestaña Todos los Dominios
    echo '<div id="all-tab" class="tab-pane active">';
    mostrar_todos_los_dominios_unificados();
    echo '</div>';

    // Pestaña UK
    echo '<div id="uk-tab" class="tab-pane">';
    mostrar_servidor_dominios('uk', 'UK (Europa)');
    echo '</div>';

    // Pestaña USA
    echo '<div id="usa-tab" class="tab-pane">';
    mostrar_servidor_dominios('usa', 'USA');
    echo '</div>';

    // Pestaña Configuración
    echo '<div id="settings-tab" class="tab-pane">';
    echo '<div class="dr-settings-subtabs">';
    echo '<div class="dr-settings-subtab-buttons" style="margin:8px 0 16px 0; display:flex; gap:8px; flex-wrap:wrap;">';
    echo '<button type="button" class="button button-primary dr-settings-subtab-button active" data-subtab="general">⚙️ General</button>';
    echo '<button type="button" class="button dr-settings-subtab-button" data-subtab="debug">🧪 Debug WHM</button>';
    echo '</div>';

    echo '<div id="dr-settings-general" class="dr-settings-subtab-pane" style="display:block;">';
    echo '<form method="post" action="options.php">';
    settings_fields('dominios_reseller_options_group');
    do_settings_sections('dominios-reseller');
    submit_button('Guardar configuración');
    echo '</form>';
    echo '</div>';

    echo '<div id="dr-settings-debug" class="dr-settings-subtab-pane" style="display:none;">';
    echo '<div class="card" style="max-width:980px; padding:16px;">';
    echo '<h3 style="margin-top:0;">Debug WHM / Upmind SSO</h3>';
    echo '<p>Este test valida los endpoints que suelen fallar en Upmind: <code>accountsummary</code> y <code>create_user_session</code>.</p>';
    echo '<form method="post">';
    echo '<input type="hidden" name="run_whm_debug" value="1">';
    echo '<input type="hidden" name="dr_whm_debug_nonce" value="' . esc_attr(wp_create_nonce('dr_whm_debug_action')) . '">';
    echo '<table class="form-table" style="margin-top:0;">';
    echo '<tr><th scope="row"><label for="debug_server">Servidor</label></th><td>';
    echo '<select id="debug_server" name="debug_server">';
    echo '<option value="uk">UK</option>';
    echo '<option value="usa">USA</option>';
    echo '</select>';
    echo '</td></tr>';
    echo '<tr><th scope="row"><label for="debug_cpuser">Usuario cPanel</label></th><td>';
    echo '<input type="text" id="debug_cpuser" name="debug_cpuser" class="regular-text" placeholder="ej: zyncoclo" required>';
    echo '<p class="description">Usuario cPanel exacto que falla al abrir desde Upmind.</p>';
    echo '</td></tr>';
    echo '</table>';
    submit_button('Ejecutar diagnóstico WHM', 'secondary', 'submit', false);
    echo '</form>';

    if (is_array($whm_debug_result) && !empty($whm_debug_result['tests'])) {
        echo '<hr>';
        echo '<h4>Resultado diagnóstico</h4>';
        echo '<p><strong>Servidor:</strong> ' . esc_html(strtoupper($whm_debug_result['server'])) . ' | <strong>Host:</strong> ' . esc_html($whm_debug_result['host']) . ' | <strong>WHM user:</strong> ' . esc_html($whm_debug_result['whm_user']) . '</p>';
        echo '<table class="widefat striped" style="max-width:100%;">';
        echo '<thead><tr><th>Endpoint</th><th>HTTP</th><th>Estado</th><th>Detalle</th></tr></thead><tbody>';
        foreach ($whm_debug_result['tests'] as $test) {
            $ok = !empty($test['ok']);
            echo '<tr>';
            echo '<td><code>' . esc_html($test['name']) . '</code></td>';
            echo '<td>' . esc_html((string)($test['http_code'] ?? 0)) . '</td>';
            echo '<td>' . ($ok ? '✅ OK' : '❌ Error') . '</td>';
            echo '<td><small>' . esc_html($test['message'] ?? '') . '</small></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    // Script para verificar conexión Openprovider
    $op_nonce = wp_create_nonce('dr_onboarding_admin');
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Subtabs internas de configuración
        $('.dr-settings-subtab-button').on('click', function() {
            var subtab = $(this).data('subtab');
            $('.dr-settings-subtab-button').removeClass('active button-primary').addClass('button-secondary');
            $(this).addClass('active button-primary').removeClass('button-secondary');
            $('.dr-settings-subtab-pane').hide();
            $('#dr-settings-' + subtab).show();
        });

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
        
        // Verificar conexión Openprovider
        $('#dr-verify-openprovider').on('click', function() {
            var $btn = $(this);
            var $result = $('#dr-op-verify-result');
            
            $btn.prop('disabled', true);
            $result.html('⏳ Verificando...');
            
            $.post(ajaxurl, {
                action: 'dr_verify_openprovider',
                _nonce: '<?php echo $op_nonce; ?>'
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
    });
    </script>
    <?php
    echo '</div>';

    echo '</div>'; // Fin tab-content
    echo '</div>'; // Fin server-tabs
    echo '</div>'; // Fin dr-main
    echo '</div>'; // Fin wrap
}

/**
 * Diagnóstico WHM profundo para endpoints usados por Upmind SSO.
 */
function dominios_reseller_debug_whm_sso($server, $cpuser) {
    $opts = get_option('dominios_reseller_options', []);
    $host = $opts[$server . '_server_ip'] ?? '';
    $token = $opts[$server . '_whm_token'] ?? '';
    $whm_user = $opts[$server . '_whm_user'] ?? 'root';

    if (empty($host) || empty($token) || empty($whm_user)) {
        return [
            'success' => false,
            'error' => 'Configuración WHM incompleta para ' . strtoupper($server),
            'server' => $server,
            'host' => $host,
            'whm_user' => $whm_user,
            'tests' => [],
        ];
    }

    $auth = "Authorization: whm {$whm_user}:{$token}";
    $tests = [
        [
            'name' => 'version',
            'url' => "https://{$host}:2087/json-api/version?api.version=1",
        ],
        [
            'name' => 'accountsummary',
            'url' => "https://{$host}:2087/json-api/accountsummary?api.version=1&user=" . rawurlencode($cpuser),
        ],
        [
            'name' => 'create_user_session',
            'url' => "https://{$host}:2087/json-api/create_user_session?api.version=1&user=" . rawurlencode($cpuser) . "&service=cpaneld",
        ],
    ];

    $results = [];
    foreach ($tests as $test) {
        $ch = curl_init($test['url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [$auth],
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);

        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            $results[] = [
                'name' => $test['name'],
                'http_code' => 0,
                'ok' => false,
                'message' => 'cURL error: ' . $curl_err,
            ];
            continue;
        }

        $snippet = substr(trim((string)$resp), 0, 220);
        $results[] = [
            'name' => $test['name'],
            'http_code' => $http,
            'ok' => ($http === 200),
            'message' => ($http === 200 ? 'Respuesta correcta' : $snippet),
        ];
    }

    $all_ok = true;
    foreach ($results as $r) {
        if (empty($r['ok'])) {
            $all_ok = false;
            break;
        }
    }

    return [
        'success' => $all_ok,
        'error' => $all_ok ? '' : 'Uno o más endpoints devolvieron error',
        'server' => $server,
        'host' => $host,
        'whm_user' => $whm_user,
        'tests' => $results,
    ];
}

// Función para mostrar tabla unificada de todos los dominios
function mostrar_todos_los_dominios_unificados() {
    global $wpdb;
    $tabla = $wpdb->prefix . 'dominios_reseller';
    $options = get_option('dominios_reseller_options');
    $uk_token = $options['uk_whm_token'] ?? '';
    $usa_token = $options['usa_whm_token'] ?? '';

    // Sincronizar dominios desde WHM si hay tokens configurados
    $sync_results = [];
    $total_deleted = 0;
    
    if (!empty($uk_token)) {
        $result = dominios_reseller_sync_from_whm('uk', $uk_token);
        $sync_results[] = 'UK';
        if ($result['success'] && isset($result['deleted'])) {
            $total_deleted += $result['deleted'];
        }
    }
    if (!empty($usa_token)) {
        $result = dominios_reseller_sync_from_whm('usa', $usa_token);
        $sync_results[] = 'USA';
        if ($result['success'] && isset($result['deleted'])) {
            $total_deleted += $result['deleted'];
        }
    }
    
    if (!empty($sync_results)) {
        $msg = '✅ Sincronizados servidores: ' . implode(', ', $sync_results);
        if ($total_deleted > 0) {
            $msg .= ' | <strong>🗑️ ' . $total_deleted . ' dominio(s) eliminado(s)</strong> (ya no existen en WHM)';
        }
        echo '<div class="notice notice-success"><p>' . $msg . '</p></div>';
    } else {
        echo '<div class="notice notice-warning"><p>⚠️ No hay tokens WHM configurados. Ve a Configuración para añadirlos.</p></div>';
        return;
    }

    // Obtener TODOS los dominios desde la base de datos local (objetos stdClass)
    $total_domains = $wpdb->get_var("SELECT COUNT(*) FROM $tabla");
    $all_domains = $wpdb->get_results("SELECT * FROM $tabla ORDER BY server, domain LIMIT 50");

    if (empty($all_domains)) {
        echo '<div class="notice notice-warning"><p>⚠️ No hay dominios en la base de datos. Los dominios se sincronizarán automáticamente al cargar esta página.</p></div>';
        return;
    }

    // Contar por servidor
    $uk_count = 0;
    $usa_count = 0;
    foreach ($all_domains as $d) {
        if (strtolower($d->server) === 'uk') $uk_count++;
        if (strtolower($d->server) === 'usa') $usa_count++;
    }
    
    $loaded_count = count($all_domains);
    $showing_msg = $loaded_count < $total_domains 
        ? "Mostrando primeros {$loaded_count} de {$total_domains} dominios" 
        : "{$total_domains} dominios";
    
    echo '<div class="notice notice-info"><p>📊 ' . $showing_msg . ' (UK: ' . $uk_count . ' | USA: ' . $usa_count . ')</p></div>';

    echo '<div class="domains-unified-container">';
    echo '<div class="unified-header">';
    echo '<h3>📊 Dominios <span id="domains-count-display">(' . $loaded_count . ' de ' . $total_domains . ')</span></h3>';
    echo '<div class="view-controls">';
    echo '<div class="view-toggle">';
    echo '<button id="view-table" class="view-btn active" title="Vista de tabla">📋 Tabla</button>';
    echo '<button id="view-cards" class="view-btn" title="Vista de cards PRO">🎴 Cards PRO</button>';
    echo '</div>';
    echo '<div class="filter-controls">';
    echo '<select id="server-filter" class="filter-select">';
    echo '<option value="">Todos los servidores</option>';
    echo '<option value="UK">🇬🇧 UK (Europa)</option>';
    echo '<option value="USA">🇺🇸 USA (América)</option>';
    echo '</select>';
    echo '<select id="status-filter" class="filter-select">';
    echo '<option value="">Todos los estados</option>';
    echo '<option value="Activo">✅ Activos</option>';
    echo '<option value="Suspendido">❌ Suspendidos</option>';
    echo '<option value="Addon">🔗 Addon Domains</option>';
    echo '</select>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // =====================================================
    // CLOUDFLARE: Preparar matching en batch (optimizado)
    // =====================================================
    $cf_service = class_exists('Dominios_Reseller_Cloudflare_Service') 
        ? Dominios_Reseller_Cloudflare_Service::get_instance() 
        : null;
    
    $has_cf_sync = $cf_service && $cf_service->has_sync_data();
    $cf_matches = [];
    
    if ($has_cf_sync) {
        // Extraer todos los primary_domain para batch matching
        $primary_domains = array_map(function($d) { 
            return $d->primary_domain ?? $d->domain; 
        }, $all_domains);
        $cf_matches = $cf_service->batch_match_domains($primary_domains);
    }
    
    // Estilos CSS para columna Cloudflare
    if (class_exists('Dominios_Reseller_Cloudflare_Admin')) {
        echo '<style>' . Dominios_Reseller_Cloudflare_Admin::get_cloudflare_styles() . '</style>';
    }

    // =====================================================
    // ESTILOS PARA VISTA DE CARDS PRO Y TOGGLE
    // =====================================================
    echo '<style>
    .view-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    .view-toggle {
        display: flex;
        gap: 5px;
        background: #f1f1f1;
        border-radius: 6px;
        padding: 3px;
    }
    .view-btn {
        padding: 8px 16px;
        border: none;
        background: transparent;
        border-radius: 4px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 500;
        transition: all 0.2s;
    }
    .view-btn.active {
        background: #007cba;
        color: white;
        box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }
    .view-btn:hover:not(.active) {
        background: #e0e0e0;
    }

    /* Cards PRO */
    .cards-view-container {
        margin-top: 20px;
    }
    .cards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    .domain-card-pro {
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 0;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        transition: all 0.2s;
    }
    .domain-card-pro:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        transform: translateY(-1px);
    }
    .domain-card-pro.status-active { border-left: 4px solid #28a745; }
    .domain-card-pro.status-suspended { border-left: 4px solid #dc3545; }
    .domain-card-pro.status-addon { border-left: 4px solid #ffc107; }

    .card-header {
        padding: 15px 15px 10px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }
    .card-domain {
        margin: 0;
        font-size: 16px;
        font-weight: 600;
        color: #333;
        word-break: break-all;
    }
    .card-badges {
        display: flex;
        gap: 5px;
        flex-wrap: wrap;
    }
    .badge {
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
    }
    .server-badge.server-uk { background: #007cba; color: white; }
    .server-badge.server-usa { background: #28a745; color: white; }
    .status-badge.status-activo { background: #28a745; color: white; }
    .status-badge.status-suspendido { background: #dc3545; color: white; }
    .status-badge.status-addon { background: #ffc107; color: black; }
    .cf-badge { background: #f39c12; color: white; }
    .endpoint-badge { background: #9b59b6; color: white; cursor: help; }
    .endpoint-badge.score-good { background: #27ae60; }
    .endpoint-badge.score-ok { background: #f39c12; }
    .endpoint-badge.score-low { background: #e74c3c; }
    
    /* SSL Badges */
    .ssl-col { text-align: center; width: 40px; }
    .ssl-badge {
        display: inline-block;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 10px;
        font-weight: 600;
        cursor: help;
    }
    .ssl-badge.ssl-cf { background: #f48120; color: white; }
    .ssl-badge.ssl-le { background: #003a70; color: white; }
    .ssl-badge.ssl-autossl { background: #ff6c2c; color: white; }
    .ssl-badge.ssl-paid { background: #28a745; color: white; }
    .ssl-badge.ssl-other { background: #6c757d; color: white; }
    .ssl-badge.ssl-none { background: #dc3545; color: white; }

    .card-body {
        padding: 15px;
    }
    .card-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
        font-size: 13px;
    }
    .card-row label {
        font-weight: 600;
        color: #666;
        min-width: 100px;
    }
    .card-row input {
        width: 80px;
        text-align: right;
        padding: 2px 4px;
        border: 1px solid #ddd;
        border-radius: 3px;
    }

    .card-actions {
        padding: 10px 15px 15px;
        border-top: 1px solid #eee;
        text-align: center;
    }
    .cards-actions {
        text-align: center;
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid #ddd;
    }
    
    /* Toggle para addons colapsables */
    .addon-toggle {
        background: none;
        border: none;
        cursor: pointer;
        font-size: 10px;
        padding: 2px 6px;
        margin-right: 4px;
        color: #666;
        transition: transform 0.2s;
    }
    .addon-toggle.expanded {
        transform: rotate(90deg);
    }
    .addon-count {
        font-size: 11px;
        background: #f0f0f1;
        color: #666;
        padding: 1px 6px;
        border-radius: 10px;
        margin-left: 6px;
    }
    .main-domain-row.has-addons td:first-child {
        font-weight: 600;
    }
    .addon-row td {
        background: #f9f9f9 !important;
    }
    .addon-row td:first-child {
        padding-left: 30px;
        color: #666;
    }
    .expand-all-addons, .collapse-all-addons {
        margin-left: 10px;
    }
    </style>';
    // =====================================================

    // =====================================================
    // AGRUPAR DOMINIOS: Principales con sus Addons
    // =====================================================
    $grouped_domains = [];
    $primary_list = [];
    
    foreach ($all_domains as $row) {
        $domain = $row->domain;
        $status = $row->status ?? 'Activo';
        $primary = $row->primary_domain ?? $domain;
        
        // Ignorar subdominios (contienen punto antes del TLD principal)
        $parts = explode('.', $domain);
        if (count($parts) > 2) {
            // Verificar si es subdominio del primary (ej: sub.domain.com donde primary es domain.com)
            $potential_primary = implode('.', array_slice($parts, -2));
            if ($status !== 'Addon' && $domain !== $primary && strpos($domain, '.') !== strrpos($domain, '.')) {
                continue; // Es un subdominio, saltar
            }
        }
        
        // Si es el dominio principal (status Activo o Suspendido y domain == primary_domain)
        if ($status !== 'Addon' || $domain === $primary) {
            if (!isset($grouped_domains[$domain])) {
                $grouped_domains[$domain] = [
                    'main' => $row,
                    'addons' => []
                ];
                $primary_list[] = $domain;
            }
        } else {
            // Es addon, añadir al grupo de su primary
            if (!isset($grouped_domains[$primary])) {
                $grouped_domains[$primary] = ['main' => null, 'addons' => []];
            }
            $grouped_domains[$primary]['addons'][] = $row;
        }
    }

    // Contar solo principales
    $main_count = count(array_filter($grouped_domains, fn($g) => $g['main'] !== null));
    $addon_count = array_sum(array_map(fn($g) => count($g['addons']), $grouped_domains));

    echo '<table class="widefat fixed striped unified-domains-table" id="unified-domains-table">';
    echo '<thead><tr>';
    echo '<th class="domain-col">Dominio <small style="color:#999;">(' . $main_count . ' principales, ' . $addon_count . ' addons)</small></th>';
    echo '<th class="server-col">Servidor</th>';
    echo '<th class="cf-col" title="Estado en Cloudflare"><img src="https://replanta.net/wp-content/uploads/2025/12/Cloudflare.svg" style="width:16px;height:16px;vertical-align:middle;"></th>';
    echo '<th class="status-col">Estado</th>';
    echo '<th class="startdate-col">Inicio WHM</th>';
    echo '<th class="registered-col">Alta Replanta</th>';
    echo '<th class="trees-col">Árboles</th>';
    echo '<th class="co2-col">CO2 (g)</th>';
    echo '<th class="actions-col">Acciones</th>';
    echo '</tr></thead><tbody>';

    foreach ($primary_list as $primary_domain) {
        $group = $grouped_domains[$primary_domain];
        $row = $group['main'];
        
        if (!$row) continue; // Saltar si no hay dominio principal
        
        $dominio = esc_html($row->domain);
        $server = strtoupper($row->server ?? 'UK');
        $server_lower = strtolower($server);
        $status = $row->status ?? 'Activo';
        $startdate = $row->startdate;
        $trees = (int)($row->trees_planted ?? 0);
        $co2 = (float)($row->co2_evaded ?? 0);
        $addon_count_this = count($group['addons']);
        
        // Obtener match de Cloudflare
        $cf_match = $cf_matches[$primary_domain] ?? null;

        // Clases CSS
        $status_class = ($status === 'Activo') ? 'status-active' : (($status === 'Suspendido') ? 'status-suspended' : '');
        $row_class = 'unified-domain-row main-domain-row';
        if ($status === 'Suspendido') $row_class .= ' suspended-row';
        if ($addon_count_this > 0) $row_class .= ' has-addons';

        echo '<tr class="' . $row_class . '" data-domain="' . esc_attr($dominio) . '" data-server="' . esc_attr($server_lower) . '" data-status="' . esc_attr($status) . '" data-primary="' . esc_attr($primary_domain) . '">';
        
        // Columna dominio con toggle si tiene addons
        echo "<td class='domain-cell'>";
        if ($addon_count_this > 0) {
            echo "<button class='addon-toggle' data-primary='" . esc_attr($primary_domain) . "' title='Mostrar {$addon_count_this} addon(s)'>▶</button> ";
        }
        echo "<strong>$dominio</strong>";
        if ($addon_count_this > 0) {
            echo " <span class='addon-count'>+{$addon_count_this}</span>";
        }
        echo "</td>";
        
        echo "<td class='server-cell'><span class='server-badge server-{$server_lower}'>$server</span></td>";
        
        // Columna Cloudflare
        echo '<td class="cf-col">';
        if (class_exists('Dominios_Reseller_Cloudflare_Admin')) {
            echo Dominios_Reseller_Cloudflare_Admin::render_cloudflare_cell($primary_domain, $cf_match, $has_cf_sync);
        } else {
            echo '<span title="Módulo CF no disponible">-</span>';
        }
        echo '</td>';
        
        echo "<td class='status-cell'><span class='status-badge $status_class'>$status</span></td>";
        echo '<td class="startdate-cell">' . ($startdate ? date('Y-m-d', $startdate) : 'N/A') . '</td>';
        echo '<td class="registered-cell">' . esc_html($row->fecha_emision ?? '-') . '</td>';
        echo "<td class='trees-cell'><input type='number' class='trees-input' data-domain='$dominio' data-server='$server_lower' value='$trees' min='0' /></td>";
        echo "<td class='co2-cell'><input type='number' class='co2-input' data-domain='$dominio' data-server='$server_lower' value='$co2' step='0.01' /></td>";
        echo "<td class='actions-cell'><button class='button button-small calculate-emissions' data-domain='$dominio' data-server='$server_lower'>Calc</button></td>";
        echo '</tr>';
        
        // Renderizar addons (ocultos por defecto)
        foreach ($group['addons'] as $addon_row) {
            $addon_domain = esc_html($addon_row->domain);
            $addon_server_lower = strtolower($addon_row->server ?? 'uk');
            $addon_trees = (int)($addon_row->trees_planted ?? 0);
            $addon_co2 = (float)($addon_row->co2_evaded ?? 0);
            
            echo '<tr class="unified-domain-row addon-row addon-of-' . esc_attr($primary_domain) . '" data-domain="' . esc_attr($addon_domain) . '" data-server="' . esc_attr($addon_server_lower) . '" data-status="Addon" data-primary="' . esc_attr($primary_domain) . '" style="display:none;">';
            echo "<td class='domain-cell addon-domain'>└─ $addon_domain</td>";
            echo "<td class='server-cell'><span class='server-badge server-{$addon_server_lower}'>" . strtoupper($addon_row->server ?? 'UK') . "</span></td>";
            echo '<td class="cf-col">-</td>';
            echo "<td class='status-cell'><span class='status-badge status-addon'>Addon</span></td>";
            echo '<td class="startdate-cell">' . ($addon_row->startdate ? date('Y-m-d', $addon_row->startdate) : '-') . '</td>';
            echo '<td class="registered-cell">' . esc_html($addon_row->fecha_emision ?? '-') . '</td>';
            echo "<td class='trees-cell'><input type='number' class='trees-input' data-domain='$addon_domain' data-server='$addon_server_lower' value='$addon_trees' min='0' /></td>";
            echo "<td class='co2-cell'><input type='number' class='co2-input' data-domain='$addon_domain' data-server='$addon_server_lower' value='$addon_co2' step='0.01' /></td>";
            echo "<td class='actions-cell'><button class='button button-small calculate-emissions' data-domain='$addon_domain' data-server='$addon_server_lower'>Calc</button></td>";
            echo '</tr>';
        }
    }

    echo '</tbody></table>';
    
    // Botón cargar más para tabla
    if ($loaded_count < $total_domains) {
        echo '<div class="load-more-container" style="text-align:center; padding:20px;">';
        echo '<button id="load-more-table" class="button button-secondary load-more-btn" data-offset="50" data-view="table">';
        echo '📥 Cargar 50 dominios más (' . ($total_domains - $loaded_count) . ' restantes)';
        echo '</button>';
        echo '<div class="load-more-spinner" style="display:none; margin-top:10px;">⏳ Cargando...</div>';
        echo '</div>';
    }
    
    echo '<div class="unified-actions">';
    echo '<button class="button button-primary save-all-unified" data-table="unified">💾 Guardar todos</button>';
    echo '<button class="button refresh-unified" data-table="unified">🔄 Actualizar</button>';
    echo '<button class="button expand-all-addons">📂 Expandir addons</button>';
    echo '<button class="button collapse-all-addons">📁 Colapsar addons</button>';
    echo '</div>';

    // =====================================================
    // VISTA DE CARDS PRO
    // =====================================================
    echo '<div id="cards-view" class="cards-view-container" style="display:none;">';
    echo '<div class="cards-grid">';

    foreach ($all_domains as $row) {
        $dominio = esc_html($row->domain);
        $server = strtoupper($row->server ?? 'UK');
        $server_lower = strtolower($server);
        $status = $row->status ?? 'Activo';
        $startdate = $row->startdate;
        $trees = (int)($row->trees_planted ?? 0);
        $co2 = (float)($row->co2_evaded ?? 0);
        $primary_domain = $row->primary_domain ?? $dominio;
        $is_addon = ($status === 'Addon');

        // Obtener match de Cloudflare para este dominio
        $cf_match = $cf_matches[$primary_domain] ?? null;

        // Clases CSS para diferentes estados
        $card_class = 'domain-card-pro';
        switch($status) {
            case 'Activo':
                $card_class .= ' status-active';
                break;
            case 'Suspendido':
                $card_class .= ' status-suspended';
                break;
            case 'Addon':
                $card_class .= ' status-addon';
                break;
        }

        echo '<div class="' . $card_class . '" data-domain="' . esc_attr($dominio) . '" data-server="' . esc_attr($server_lower) . '" data-status="' . esc_attr($status) . '">';
        echo '<div class="card-header">';
        echo '<h4 class="card-domain">' . ($is_addon && $primary_domain !== $dominio ? '└─ ' : '') . $dominio . '</h4>';
        echo '<div class="card-badges">';
        echo '<span class="badge server-badge server-' . $server_lower . '">' . $server . '</span>';
        echo '<span class="badge status-badge status-' . strtolower($status) . '">' . $status . '</span>';
        if ($cf_match) {
            echo '<span class="badge cf-badge">☁️ CF</span>';
        }
        // Indicador de endpoint PHP desplegado
        $has_endpoint = !empty($row->endpoint_token);
        $wp_score = $row->wp_readiness_score ?? null;
        if ($has_endpoint) {
            $score_class = $wp_score >= 75 ? 'score-good' : ($wp_score >= 50 ? 'score-ok' : 'score-low');
            $score_title = $wp_score ? "WP Score: {$wp_score}/100" : "Endpoint activo - Test pendiente";
            echo '<span class="badge endpoint-badge ' . $score_class . '" title="' . esc_attr($score_title) . '">🔬' . ($wp_score ? " {$wp_score}" : '') . '</span>';
        }
        echo '</div>';
        echo '</div>';

        echo '<div class="card-body">';
        echo '<div class="card-row">';
        echo '<label>Inicio WHM:</label>';
        echo '<span>' . esc_html($startdate) . '</span>';
        echo '</div>';
        echo '<div class="card-row">';
        echo '<label>Alta Replanta:</label>';
        echo '<span>' . esc_html($row->fecha_emision ?? '(No reg.)') . '</span>';
        echo '</div>';
        echo '<div class="card-row">';
        echo '<label>Árboles:</label>';
        echo '<input type="number" class="trees-input-card" data-domain="' . $dominio . '" data-server="' . $server_lower . '" value="' . $trees . '" min="0" />';
        echo '</div>';
        echo '<div class="card-row">';
        echo '<label>CO2 Evitado:</label>';
        echo '<input type="number" class="co2-input-card" data-domain="' . $dominio . '" data-server="' . $server_lower . '" value="' . $co2 . '" step="0.01" />';
        echo '</div>';
        echo '</div>';

        echo '<div class="card-actions">';
        echo '<button class="button button-small calculate-emissions-card" data-domain="' . $dominio . '" data-server="' . $server_lower . '">Calcular</button>';
        echo '</div>';
        echo '</div>';
    }

    echo '</div>'; // Fin cards-grid
    
    // Botón cargar más para cards
    if ($loaded_count < $total_domains) {
        echo '<div class="load-more-container" style="text-align:center; padding:20px;">';
        echo '<button id="load-more-cards" class="button button-secondary load-more-btn" data-offset="50" data-view="cards">';
        echo '📥 Cargar 50 dominios más (' . ($total_domains - $loaded_count) . ' restantes)';
        echo '</button>';
        echo '<div class="load-more-spinner" style="display:none; margin-top:10px;">⏳ Cargando...</div>';
        echo '</div>';
    }
    
    echo '<div class="cards-actions">';
    echo '<button class="button button-primary save-all-cards">Guardar todos los cambios</button>';
    echo '<button class="button refresh-cards">Actualizar datos</button>';
    echo '</div>';
    echo '</div>'; // Fin cards-view-container

    echo '</div>'; // Fin domains-unified-container
}

add_action('admin_init', function () {
    register_setting('dominios_reseller_options_group', 'dominios_reseller_options', 'dominios_reseller_validate_options');

    // Configuración servidor UK (existente)
    add_settings_section('dominios_reseller_uk', 'Servidor UK (Europa)', function () {
        echo '<p>Configuración del servidor WHM en Reino Unido (replanta.dev).</p>';
    }, 'dominios-reseller');

    add_settings_field('uk_server_ip', 'IP/Hostname Servidor (UK)', function () {
        $opts = get_option('dominios_reseller_options');
        $ip = isset($opts['uk_server_ip']) ? esc_attr($opts['uk_server_ip']) : '';
        echo "<input type='text' name='dominios_reseller_options[uk_server_ip]' value='$ip' class='regular-text' placeholder='ej: replanta.dev'>";
        echo "<p class='description'>IP o hostname del servidor WHM (sin https:// ni puerto)</p>";
    }, 'dominios-reseller', 'dominios_reseller_uk');

    add_settings_field('uk_whm_user', 'Usuario WHM (UK)', function () {
        $opts = get_option('dominios_reseller_options');
        $user = isset($opts['uk_whm_user']) ? esc_attr($opts['uk_whm_user']) : 'root';
        echo "<input type='text' name='dominios_reseller_options[uk_whm_user]' value='$user' class='regular-text' placeholder='root'>";
        echo "<p class='description'>Usuario WHM (normalmente 'root' o tu usuario reseller)</p>";
    }, 'dominios-reseller', 'dominios_reseller_uk');

    add_settings_field('uk_whm_token', 'API Token WHM (UK)', function () {
        $opts = get_option('dominios_reseller_options');
        $token = isset($opts['uk_whm_token']) ? esc_attr($opts['uk_whm_token']) : '';
        echo "<input type='password' name='dominios_reseller_options[uk_whm_token]' value='$token' class='regular-text' autocomplete='off' placeholder='Token del servidor UK...'>";
        echo "<p class='description'>Token API de WHM → Development → Manage API Tokens</p>";
    }, 'dominios-reseller', 'dominios_reseller_uk');

    // Configuración servidor USA
    add_settings_section('dominios_reseller_usa', 'Servidor USA', function () {
        echo '<p>Configuración del servidor WHM en Estados Unidos (replanta.us).</p>';
    }, 'dominios-reseller');

    add_settings_field('usa_server_ip', 'IP/Hostname Servidor (USA)', function () {
        $opts = get_option('dominios_reseller_options');
        $ip = isset($opts['usa_server_ip']) ? esc_attr($opts['usa_server_ip']) : '';
        echo "<input type='text' name='dominios_reseller_options[usa_server_ip]' value='$ip' class='regular-text' placeholder='ej: replanta.us'>";
        echo "<p class='description'>IP o hostname del servidor WHM (sin https:// ni puerto)</p>";
    }, 'dominios-reseller', 'dominios_reseller_usa');

    add_settings_field('usa_whm_user', 'Usuario WHM (USA)', function () {
        $opts = get_option('dominios_reseller_options');
        $user = isset($opts['usa_whm_user']) ? esc_attr($opts['usa_whm_user']) : 'root';
        echo "<input type='text' name='dominios_reseller_options[usa_whm_user]' value='$user' class='regular-text' placeholder='root'>";
        echo "<p class='description'>Usuario WHM (normalmente 'root' o tu usuario reseller)</p>";
    }, 'dominios-reseller', 'dominios_reseller_usa');

    add_settings_field('usa_whm_token', 'API Token WHM (USA)', function () {
        $opts = get_option('dominios_reseller_options');
        $token = isset($opts['usa_whm_token']) ? esc_attr($opts['usa_whm_token']) : '';
        echo "<input type='password' name='dominios_reseller_options[usa_whm_token]' value='$token' class='regular-text' autocomplete='off' placeholder='Token del servidor USA...'>";
        echo "<p class='description'>Token API de WHM → Development → Manage API Tokens</p>";
    }, 'dominios-reseller', 'dominios_reseller_usa');

    // ── Cloudflare API ────────────────────────────────────────────────────────
    add_settings_section('dominios_reseller_cloudflare', 'Cloudflare', function () {
        echo '<p>Credenciales de la API de Cloudflare. Usadas para sincronizar zonas, '
           . 'detectar configuración de dominios y habilitar el auto-fix de caché desde '
           . '<strong>Replanta Site Audit</strong>.</p>'
           . '<p><strong>Recomendado:</strong> usa un <em>API Token</em> con permisos '
           . '<code>Zone:Read</code>, <code>Zone:Cache Settings</code> y <code>Zone:Edit</code>. '
           . 'Créalo en <a href="https://dash.cloudflare.com/profile/api-tokens" target="_blank">'
           . 'Cloudflare → My Profile → API Tokens</a>.</p>';
    }, 'dominios-reseller');

    add_settings_field('cf_api_token', 'API Token (recomendado)', function () {
        $opts  = get_option('dominios_reseller_options', []);
        $token = isset($opts['cf_api_token']) ? esc_attr($opts['cf_api_token']) : '';
        $masked = $token ? str_repeat('•', max(0, strlen($token) - 6)) . substr($token, -6) : '';
        echo "<input type='password' id='dr_cf_api_token' name='dominios_reseller_options[cf_api_token]'"
           . " value='" . esc_attr($token) . "' class='regular-text' autocomplete='off'"
           . " placeholder='Empieza con Bearer...' style='font-family:monospace'>";
        if ($masked) {
            echo "<span style='margin-left:8px;color:#888;font-size:12px'>Guardado: {$masked}</span>";
        }
        echo "<p class='description'>Token API de Cloudflare. "
           . "<strong>Este campo es el que usa Replanta Site Audit para auditar CF.</strong></p>";
    }, 'dominios-reseller', 'dominios_reseller_cloudflare');

    add_settings_field('cf_email', 'Email Cloudflare (Global Key — opcional)', function () {
        $opts  = get_option('dominios_reseller_options', []);
        $email = isset($opts['cf_email']) ? esc_attr($opts['cf_email']) : '';
        echo "<input type='email' name='dominios_reseller_options[cf_email]'"
           . " value='{$email}' class='regular-text' autocomplete='off'"
           . " placeholder='usuario@ejemplo.com'>";
        echo "<p class='description'>Solo necesario si usas Global API Key en lugar de API Token (método legacy).</p>";
    }, 'dominios-reseller', 'dominios_reseller_cloudflare');

    add_settings_field('cf_global_key', 'Global API Key (opcional, legacy)', function () {
        $opts = get_option('dominios_reseller_options', []);
        $key  = isset($opts['cf_global_key']) ? esc_attr($opts['cf_global_key']) : '';
        echo "<input type='password' name='dominios_reseller_options[cf_global_key]'"
           . " value='{$key}' class='regular-text' autocomplete='off'"
           . " placeholder='Global API Key de Cloudflare...' style='font-family:monospace'>";
        echo "<p class='description'>Solo si no puedes usar API Token. Requiere también el Email de arriba.</p>";
    }, 'dominios-reseller', 'dominios_reseller_cloudflare');

    // Sección para configuración de mensajes
    add_settings_section('dominios_reseller_messages', 'Mensajes para Shortcodes', function () {
        echo '<p>Configura los mensajes que se mostrarán cuando no se detecte un dominio específico</p>';
    }, 'dominios-reseller');

    add_settings_field('hero_title', 'Título Hero (H1)', function () {
        $opts = get_option('dominios_reseller_options');
        $title = isset($opts['hero_title']) ? esc_attr($opts['hero_title']) : 'Hosting Ecológico con Impacto Positivo';
        echo "<input type='text' name='dominios_reseller_options[hero_title]' value='$title' class='large-text' placeholder='Título principal cuando no hay dominio específico...'>";
        echo "<p class='description'>Este título aparecerá como H1 cuando no se pueda detectar el dominio</p>";
    }, 'dominios-reseller', 'dominios_reseller_messages');

    add_settings_field('hero_description', 'Descripción Hero', function () {
        $opts = get_option('dominios_reseller_options');
        $description = isset($opts['hero_description']) ? esc_textarea($opts['hero_description']) : 'Nuestro hosting funciona con energía 100% renovable y contribuye activamente a la reforestación del planeta. Cada sitio web alojado con nosotros ayuda a plantar árboles y reducir la huella de carbono.';
        echo "<textarea name='dominios_reseller_options[hero_description]' class='large-text' rows='4' placeholder='Descripción cuando no hay dominio específico...'>$description</textarea>";
        echo "<p class='description'>Esta descripción aparecerá debajo del título cuando no se pueda detectar el dominio</p>";
    }, 'dominios-reseller', 'dominios_reseller_messages');

    add_settings_field('new_domain_message', 'Mensaje para Dominios Nuevos', function () {
        $opts = get_option('dominios_reseller_options');
        $message = isset($opts['new_domain_message']) ? esc_textarea($opts['new_domain_message']) : '🌱 ¡Acabamos de comenzar este emocionante viaje juntos! Tu sitio web ya funciona con energía 100% renovable. Los árboles se plantan automáticamente cuando cumplimos nuestro primer año de colaboración. ¡Gracias por elegir un hosting que cuida el planeta!';
        echo "<textarea name='dominios_reseller_options[new_domain_message]' class='large-text' rows='3' placeholder='Mensaje para dominios sin árboles...'>$message</textarea>";
        echo "<p class='description'>Este mensaje aparece en lugar de '0 árboles plantados' para dominios nuevos</p>";
    }, 'dominios-reseller', 'dominios_reseller_messages');
});

function dominios_reseller_validate_options($input) {
    $old_opts = get_option('dominios_reseller_options', []);
    
    return [
        // Servidor UK
        'uk_server_ip' => sanitize_text_field($input['uk_server_ip'] ?? ''),
        'uk_whm_user' => sanitize_text_field($input['uk_whm_user'] ?? 'root'),
        'uk_whm_token' => sanitize_text_field($input['uk_whm_token'] ?? ''),
        // Servidor USA
        'usa_server_ip' => sanitize_text_field($input['usa_server_ip'] ?? ''),
        'usa_whm_user' => sanitize_text_field($input['usa_whm_user'] ?? 'root'),
        'usa_whm_token' => sanitize_text_field($input['usa_whm_token'] ?? ''),
        // Cloudflare Auth (Token o Global Key)
        'cf_api_token' => sanitize_text_field($input['cf_api_token'] ?? ''),
        'cf_email' => sanitize_email($input['cf_email'] ?? ''),
        'cf_global_key' => sanitize_text_field($input['cf_global_key'] ?? ''),
        'hero_title' => sanitize_text_field($input['hero_title'] ?? ''),
        'hero_description' => sanitize_textarea_field($input['hero_description'] ?? ''),
        'new_domain_message' => sanitize_textarea_field($input['new_domain_message'] ?? ''),
        // Openprovider (Fase 2)
        'op_username' => sanitize_text_field($input['op_username'] ?? ''),
        // Mantener password anterior si no se envía una nueva
        'op_password' => !empty($input['op_password']) 
            ? sanitize_text_field($input['op_password']) 
            : ($old_opts['op_password'] ?? ''),
    ];
}

/**
 * Función helper para obtener la IP del servidor desde las opciones
 * @param string $server 'uk' o 'usa'
 * @return string IP o hostname del servidor
 */
function dominios_reseller_get_server_ip($server) {
    $options = get_option('dominios_reseller_options', []);
    $key = $server . '_server_ip';
    return $options[$key] ?? '';
}

/**
 * Función helper para obtener el usuario WHM desde las opciones
 * @param string $server 'uk' o 'usa'
 * @return string Usuario WHM
 */
function dominios_reseller_get_whm_user($server) {
    $options = get_option('dominios_reseller_options', []);
    $key = $server . '_whm_user';
    return $options[$key] ?? 'root';
}

// Función para probar conexión WHM
function test_whm_connection($token, $server = 'uk') {
    if (empty($token)) {
        return [
            'success' => false,
            'error' => 'Token WHM no configurado'
        ];
    }

    $server_ip = dominios_reseller_get_server_ip($server);
    $whm_user = dominios_reseller_get_whm_user($server);
    
    if (empty($server_ip)) {
        return [
            'success' => false,
            'error' => 'IP/Hostname del servidor ' . strtoupper($server) . ' no configurado'
        ];
    }

    if (empty($whm_user)) {
        return [
            'success' => false,
            'error' => 'Usuario WHM del servidor ' . strtoupper($server) . ' no configurado'
        ];
    }

    $whm_url = 'https://' . $server_ip . ':2087/json-api/listaccts?api.version=1';

    // ✅ OPTIMIZACIÓN: Cache con transient (1 hora) - Reduce 95% de API calls
    $cache_key = 'dr_whm_test_' . $server . '_' . md5($token);
    $cached_result = get_transient($cache_key);
    
    if ($cached_result !== false) {
        error_log('[Dominios Reseller] Cache HIT para test connection: ' . $server);
        return $cached_result;
    }

    $response = wp_remote_get($whm_url, [
        'headers' => [
            'Authorization' => 'whm ' . $whm_user . ':' . $token,
            'Accept' => 'application/json',
            'User-Agent' => 'WordPress/Replanta-Plugin'
        ],
        'timeout' => 30,
        'sslverify' => false,
        'blocking' => true
    ]);

    if (is_wp_error($response)) {
        $error_msg = $response->get_error_message();
        error_log('[Dominios Reseller] WP Error: ' . $error_msg);
        $error_result = [
            'success' => false,
            'error' => 'Error de conexión: ' . $error_msg
        ];
        // Cache errores por 5 minutos para evitar reintentos constantes
        set_transient($cache_key, $error_result, 300);
        return $error_result;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    error_log('[Dominios Reseller] Test Connection - Server: ' . $server . ' Status: ' . $status_code . ' Body: ' . substr($body, 0, 200));

    if ($status_code !== 200) {
        $error_result = [
            'success' => false,
            'error' => 'Código de respuesta HTTP: ' . $status_code . ' - ' . substr($body, 0, 100)
        ];
        // Cache errores por 5 minutos
        set_transient($cache_key, $error_result, 300);
        return $error_result;
    }

    $data = json_decode($body, true);

    if (!$data || !isset($data['data']['acct'])) {
        $error_result = [
            'success' => false,
            'error' => 'Respuesta inválida del servidor WHM'
        ];
        // Cache errores por 5 minutos
        set_transient($cache_key, $error_result, 300);
        return $error_result;
    }

    $success_result = [
        'success' => true,
        'count' => count($data['data']['acct']),
        'message' => 'Conexión exitosa con WHM'
    ];
    
    // ✅ Cache respuestas exitosas por 1 hora
    set_transient($cache_key, $success_result, 3600);
    
    return $success_result;
}

// Función para mostrar dominios de un servidor específico
function mostrar_servidor_dominios($server, $server_name) {
    $options = get_option('dominios_reseller_options');
    $token_key = $server . '_whm_token';
    $token = $options[$token_key] ?? '';
    $server_ip = dominios_reseller_get_server_ip($server);

    echo '<div class="server-section">';
    echo '<div class="server-header">';
    
    if (!empty($server_ip)) {
        echo '<h3>' . esc_html($server_name) . ' <small>(' . esc_html($server_ip) . ')</small></h3>';
    } else {
        echo '<h3>' . esc_html($server_name) . ' <small style="color:#dc3545;">(IP no configurada)</small></h3>';
    }

    if (!empty($token)) {
        echo '<form method="post" style="display: inline-block; margin-left: 10px;">';
        echo '<input type="hidden" name="test_whm_connection" value="1">';
        echo '<input type="hidden" name="server" value="' . esc_attr($server) . '">';
        submit_button('🔧 Probar Conexión', 'secondary', 'test_connection_' . $server, false);
        echo '</form>';
    } else {
        echo '<div class="notice notice-warning inline"><p>⚠️ Configura el token WHM en la pestaña de configuración</p></div>';
    }
    echo '</div>';

    // Verificar que tanto IP como token estén configurados
    if (empty($server_ip)) {
        echo '<div class="no-config-message">';
        echo '<p>⚠️ Configure la IP/Hostname del servidor ' . strtoupper($server) . ' en la pestaña de configuración.</p>';
        echo '</div>';
        echo '</div>';
        return;
    }

    if (empty($token)) {
        echo '<div class="no-config-message">';
        echo '<p>Configure el token WHM para este servidor en la pestaña de configuración.</p>';
        echo '</div>';
        echo '</div>';
        return;
    }

    $cuentas = obtener_cuentas_whm($token, $server);
    if (!$cuentas || empty($cuentas['data']['acct'])) {
        echo '<div class="error-message">';
        echo '<p>No se encontraron cuentas en WHM o hubo un error de conexión.</p>';
        echo '</div>';
        echo '</div>';
        return;
    }

    global $wpdb;
    $tabla = $wpdb->prefix . 'dominios_reseller';

    echo '<div class="domains-table-container">';
    echo '<table class="widefat fixed striped domains-table" id="domains-table-' . $server . '" data-server="' . $server . '">';
    echo '<thead><tr>';
    echo '<th class="domain-col">Dominio</th>';
    echo '<th class="status-col">Estado</th>';
    echo '<th class="startdate-col">Inicio WHM</th>';
    echo '<th class="registered-col">Alta en Replanta</th>';
    echo '<th class="traffic-col">Tráfico (GB)</th>';
    echo '<th class="trees-col">Árboles</th>';
    echo '<th class="co2-col">CO2 Evitado (g)</th>';
    echo '<th class="actions-col">Acciones</th>';
    echo '</tr></thead><tbody>';

    foreach ($cuentas['data']['acct'] as $cuenta) {
        $dominio = esc_html($cuenta['domain']);
        $startdate = intval($cuenta['unix_startdate']);
        $suspended = $cuenta['suspended'];
        $activo = $suspended ? 'Suspendido' : 'Activo';
        $status_class = $suspended ? 'status-suspended' : 'status-active';

        $existente = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabla WHERE domain = %s", $dominio));

        $fecha_emision_calculada = date('Y-m-d', $startdate);
        $validez_calculada = date('Y-m-d', strtotime("$fecha_emision_calculada +1 year"));

        $needs_update = false;
        if ($existente) {
            if ($existente->fecha_emision !== $fecha_emision_calculada || $existente->validez !== $validez_calculada) {
                $wpdb->update($tabla, [
                    'fecha_emision' => $fecha_emision_calculada,
                    'validez'       => $validez_calculada,
                ], ['domain' => $dominio]);
                $existente->fecha_emision = $fecha_emision_calculada;
                $existente->validez = $validez_calculada;
            }
        }

        $trees = $existente->trees_planted ?? 0;
        $co2 = $existente->co2_evaded ?? 0;

        echo '<tr class="domain-row" data-domain="' . esc_attr($dominio) . '" data-server="' . $server . '">';
        echo "<td class='domain-cell'><strong>$dominio</strong></td>";
        echo "<td class='status-cell'><span class='status-badge $status_class'>$activo</span></td>";
        echo '<td class="startdate-cell">' . date('Y-m-d', $startdate) . '</td>';
        echo '<td class="registered-cell">' . esc_html($existente->fecha_emision ?? '(No reg.)') . '</td>';

        $trafico_bytes = obtener_trafico_real($dominio, $token, $server);
        $trafico_gb = $trafico_bytes ? round($trafico_bytes / (1024 ** 3), 2) : 'N/A';
        echo "<td class='traffic-cell'>$trafico_gb</td>";

        echo "<td class='trees-cell'><input type='number' class='trees-input' data-domain='$dominio' data-server='$server' value='$trees' min='0' /></td>";
        echo "<td class='co2-cell'><input type='number' class='co2-input' data-domain='$dominio' data-server='$server' value='$co2' step='0.01' /></td>";
        echo "<td class='actions-cell'><button class='button button-small calculate-emissions' data-domain='$dominio' data-server='$server'>Calcular</button></td>";
        echo '</tr>';

        // Añadir dominios adicionales (addon domains)
        $addons = obtener_addons_de_usuario($cuenta['user'], $token, $server);

        if (!is_array($addons) || empty($addons)) {
            continue;
        }

        foreach ($addons as $addon) {
            if (!is_array($addon) || !isset($addon['domain']) || empty($addon['domain'])) {
                error_log("[Dominios Reseller] Addon inválido para usuario {$cuenta['user']}: " . print_r($addon, true));
                continue;
            }

            $addon_domain = esc_html($addon['domain']);
            $addon_existente = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabla WHERE domain = %s", $addon_domain));
            $trees_addon = $addon_existente->trees_planted ?? 0;
            $co2_addon = $addon_existente->co2_evaded ?? 0;
            $fecha_emision_addon = date('Y-m-d', $startdate);
            $validez_addon = date('Y-m-d', strtotime("$fecha_emision_addon +1 year"));

            if ($addon_existente) {
                if ($addon_existente->fecha_emision !== $fecha_emision_addon || $addon_existente->validez !== $validez_addon) {
                    $wpdb->update($tabla, [
                        'fecha_emision' => $fecha_emision_addon,
                        'validez' => $validez_addon
                    ], ['domain' => $addon_domain]);
                    $addon_existente->fecha_emision = $fecha_emision_addon;
                    $addon_existente->validez = $validez_addon;
                }
            }

            echo '<tr class="addon-row" data-domain="' . esc_attr($addon_domain) . '" data-server="' . $server . '">';
            echo "<td class='domain-cell addon-domain'>└─ $addon_domain</td>";
            echo "<td class='status-cell'><span class='status-badge status-addon'>Addon</span></td>";
            echo '<td class="startdate-cell">' . date('Y-m-d', $startdate) . '</td>';
            echo '<td class="registered-cell">' . esc_html($addon_existente->fecha_emision ?? '(No reg.)') . '</td>';
            echo "<td class='traffic-cell'>N/A</td>";
            echo "<td class='trees-cell'><input type='number' class='trees-input' data-domain='$addon_domain' data-server='$server' value='$trees_addon' min='0' /></td>";
            echo "<td class='co2-cell'><input type='number' class='co2-input' data-domain='$addon_domain' data-server='$server' value='$co2_addon' step='0.01' /></td>";
            echo "<td class='actions-cell'><button class='button button-small calculate-emissions' data-domain='$addon_domain' data-server='$server'>Calcular</button></td>";
            echo '</tr>';

            if (!$addon_existente) {
                $wpdb->insert($tabla, [
                    'domain' => $addon_domain,
                    'startdate' => $startdate,
                    'fecha_emision' => date('Y-m-d', $startdate),
                    'status' => 'Addon',
                    'trees_planted' => 0,
                    'co2_evaded' => 0,
                    'primary_domain' => $dominio
                ]);
            }
        }

        if (!$existente) {
            $wpdb->insert($tabla, [
                'domain'         => $dominio,
                'startdate'      => $startdate,
                'fecha_emision'  => $fecha_emision_calculada,
                'validez'        => $validez_calculada,
                'status'         => $activo,
                'trees_planted'  => 0,
                'co2_evaded'     => 0,
                'primary_domain' => $dominio,
                'is_primary'     => 1
            ]);
        }
    }
    echo '</tbody></table>';
    echo '<div class="table-actions">';
    echo '<button class="button button-primary save-all-changes" data-server="' . $server . '">Guardar todos los cambios</button>';
    echo '<button class="button refresh-data" data-server="' . $server . '">Actualizar datos</button>';
    echo '</div>';
    echo '</div>'; // Fin domains-table-container
    echo '</div>'; // Fin server-section
}

/**
 * Página de Diagnóstico - Solo accesible por URL directa
 * URL: /wp-admin/admin.php?page=dominios-reseller-diagnostic
 */
function dominios_reseller_diagnostic_page() {
    global $wpdb;
    
    echo '<div class="wrap">';
    echo '<h1>Diagnóstico - Dominios Reseller</h1>';
    
    // 1. Versión del plugin
    echo '<div style="background:#fff; padding:20px; margin:20px 0; border-left:4px solid #2271b1;">';
    echo '<h2>Versión del Plugin</h2>';
    if (defined('DOMINIOS_RESELLER_VERSION')) {
        $version = DOMINIOS_RESELLER_VERSION;
        echo '<p style="font-size:18px;"><strong>Versión actual:</strong> <span style="color:#2271b1; font-weight:bold;">' . $version . '</span></p>';
        
        if (version_compare($version, '1.2.0', '>=')) {
            echo '<p style="color:green; font-weight:bold;">Version correcta (1.2.0+)</p>';
        } else {
            echo '<p style="color:red; font-weight:bold;">Version antigua (' . $version . ') - Necesitas actualizar</p>';
        }
    } else {
        echo '<p style="color:red; font-weight:bold;">Constante DOMINIOS_RESELLER_VERSION no definida (version MUY antigua)</p>';
        echo '<p>Sube el nuevo archivo dominios-reseller.php</p>';
    }
    echo '</div>';
    
    // 2. Estructura de la Base de Datos
    echo '<div style="background:#fff; padding:20px; margin:20px 0; border-left:4px solid #2271b1;">';
    echo '<h2>💾 Estructura de Base de Datos</h2>';
    $tabla = $wpdb->prefix . 'dominios_reseller';
    
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $tabla");
    if ($columns) {
        echo '<table class="widefat" style="margin-top:10px;"><thead><tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th></tr></thead><tbody>';
        $has_server = false;
        foreach ($columns as $col) {
            if ($col->Field === 'server') $has_server = true;
            $highlight = ($col->Field === 'server') ? ' style="background:#c6f6d5; font-weight:bold;"' : '';
            echo "<tr$highlight>";
            echo '<td>' . esc_html($col->Field) . '</td>';
            echo '<td>' . esc_html($col->Type) . '</td>';
            echo '<td>' . esc_html($col->Null) . '</td>';
            echo '<td>' . esc_html($col->Key) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        
        if ($has_server) {
            echo '<p style="color:green; font-weight:bold; font-size:16px;">✅ Campo <code>server</code> existe correctamente</p>';
        } else {
            echo '<p style="color:red; font-weight:bold; font-size:16px;">❌ Campo <code>server</code> NO existe</p>';
            echo '<p><strong>SOLUCIÓN:</strong> Ve a Plugins → Desactivar "Dominios Reseller" → Activar de nuevo</p>';
        }
    }
    echo '</div>';
    
    // 3. Contenido de la tabla
    echo '<div style="background:#fff; padding:20px; margin:20px 0; border-left:4px solid #2271b1;">';
    echo '<h2>📊 Datos en la Tabla</h2>';
    $total = $wpdb->get_var("SELECT COUNT(*) FROM $tabla");
    echo '<p style="font-size:16px;"><strong>Total de registros:</strong> ' . $total . '</p>';
    
    if (isset($has_server) && $has_server) {
        $uk_count = $wpdb->get_var("SELECT COUNT(*) FROM $tabla WHERE server = 'uk'");
        $usa_count = $wpdb->get_var("SELECT COUNT(*) FROM $tabla WHERE server = 'usa'");
        echo '<p style="font-size:16px;">🇬🇧 UK: <strong>' . $uk_count . '</strong> dominios | 🇺🇸 USA: <strong>' . $usa_count . '</strong> dominios</p>';
        
        // Mostrar algunos dominios de ejemplo
        $examples = $wpdb->get_results("SELECT domain, server, status FROM $tabla LIMIT 5");
        if ($examples) {
            echo '<p><strong>Ejemplos de dominios:</strong></p>';
            echo '<ul>';
            foreach ($examples as $ex) {
                $flag = strtolower($ex->server) === 'usa' ? '🇺🇸' : '🇬🇧';
                echo '<li>' . $flag . ' ' . esc_html($ex->domain) . ' (' . esc_html($ex->status) . ')</li>';
            }
            echo '</ul>';
        }
    }
    echo '</div>';
    
    // 4. Funciones críticas
    echo '<div style="background:#fff; padding:20px; margin:20px 0; border-left:4px solid #2271b1;">';
    echo '<h2>⚙️ Funciones Críticas</h2>';
    $functions = [
        'dominios_reseller_sync_from_whm' => 'Sincronización WHM (v1.2.0+)',
        'obtener_cuentas_whm' => 'Obtener cuentas WHM',
        'obtener_datos_dominio_actual' => 'Datos de dominio para shortcode'
    ];
    
    echo '<ul style="list-style:none; padding:0;">';
    foreach ($functions as $func => $desc) {
        if (function_exists($func)) {
            echo '<li style="color:green; font-size:16px; padding:5px 0;">✅ <code>' . esc_html($func) . '</code> - ' . esc_html($desc) . '</li>';
        } else {
            echo '<li style="color:red; font-size:16px; padding:5px 0;">❌ <code>' . esc_html($func) . '</code> - ' . esc_html($desc) . ' <strong>(FALTA)</strong></li>';
        }
    }
    echo '</ul>';
    echo '</div>';
    
    // 5. Archivos modificados recientemente
    echo '<div style="background:#fff; padding:20px; margin:20px 0; border-left:4px solid #2271b1;">';
    echo '<h2>📁 Archivos del Plugin</h2>';
    $plugin_dir = plugin_dir_path(__FILE__);
    $files = [
        'dominios-reseller.php' => 'Archivo principal',
        'includes/shortcodes.php' => 'Shortcodes',
        'includes/whm-functions.php' => 'Funciones WHM',
        'includes/ajax-handlers.php' => 'Handlers AJAX'
    ];
    
    echo '<table class="widefat"><thead><tr><th>Archivo</th><th>Estado</th><th>Última modificación</th><th>Tamaño</th></tr></thead><tbody>';
    foreach ($files as $file => $desc) {
        $path = $plugin_dir . $file;
        if (file_exists($path)) {
            $mtime = filemtime($path);
            $date = date('Y-m-d H:i:s', $mtime);
            $size = round(filesize($path) / 1024, 2) . ' KB';
            
            // Resaltar si es muy antiguo (más de 7 días)
            $is_old = (time() - $mtime) > (7 * 24 * 60 * 60);
            $row_style = $is_old ? ' style="background:#fff3cd;"' : '';
            
            echo '<tr' . $row_style . '>';
            echo '<td><code>' . esc_html($file) . '</code><br><small>' . esc_html($desc) . '</small></td>';
            echo '<td style="color:green;">✅ Existe</td>';
            echo '<td>' . $date . ($is_old ? ' <span style="color:#856404;">(⚠️ Más de 7 días)</span>' : '') . '</td>';
            echo '<td>' . $size . '</td>';
            echo '</tr>';
        } else {
            echo '<tr>';
            echo '<td><code>' . esc_html($file) . '</code></td>';
            echo '<td style="color:red;">❌ No existe</td>';
            echo '<td colspan="2">-</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table>';
    echo '</div>';
    
    // 6. Recomendaciones finales
    echo '<div style="background:#fff; padding:20px; margin:20px 0; border-left:4px solid #00a32a;">';
    echo '<h2>Recomendaciones</h2>';
    
    $all_ok = true;
    if (!defined('DOMINIOS_RESELLER_VERSION') || version_compare(DOMINIOS_RESELLER_VERSION, '1.2.0', '<')) {
        $all_ok = false;
        echo '<p style="color:red; font-weight:bold;">Version antigua del plugin</p>';
    }
    if (!isset($has_server) || !$has_server) {
        $all_ok = false;
        echo '<p style="color:red; font-weight:bold;">Base de datos no actualizada (falta campo server)</p>';
    }
    if (!function_exists('dominios_reseller_sync_from_whm')) {
        $all_ok = false;
        echo '<p style="color:red; font-weight:bold;">Funcion de sincronización no existe</p>';
    }
    
    if (!$all_ok) {
        echo '<div style="background:#f8d7da; padding:15px; border:1px solid #f5c6cb; border-radius:5px; margin-top:15px;">';
        echo '<h3 style="margin-top:0; color:#721c24;">ACCION REQUERIDA:</h3>';
        echo '<ol style="line-height:1.8;">';
        echo '<li><strong>Sube los nuevos archivos del plugin vía FTP</strong> (sobrescribe los existentes)</li>';
        echo '<li>Ve a <strong>Plugins → Desactivar "Dominios Reseller"</strong></li>';
        echo '<li>Ve a <strong>Plugins → Activar "Dominios Reseller"</strong></li>';
        echo '<li>Vuelve a esta página para verificar que todo esté en verde ✅</li>';
        echo '</ol>';
        echo '</div>';
    } else {
        echo '<div style="background:#d4edda; padding:15px; border:1px solid #c3e6cb; border-radius:5px;">';
        echo '<h3 style="margin-top:0; color:#155724;">TODO CORRECTO</h3>';
        echo '<p style="margin:0;">El plugin está actualizado y funcionando correctamente. Puedes usar:</p>';
        echo '<ul style="line-height:1.8;">';
        echo '<li>Admin: <a href="' . admin_url('admin.php?page=dominios-reseller') . '">Ver dominios</a></li>';
        echo '<li>Sincronizar datos desde WHM manualmente si es necesario</li>';
        echo '<li>Shortcode: <code>[mostrar_dominio]</code> en páginas públicas</li>';
        echo '</ul>';
        echo '</div>';
    }
    echo '</div>';
    
    echo '</div>'; // Fin wrap
}

/**
 * Renderiza la página simple de gestión de dominios
 */
function dominios_reseller_simple_page() {
    // Cargar función desde archivo
    if (function_exists('dominios_reseller_render_simple_page')) {
        dominios_reseller_render_simple_page();
    } else {
        echo '<div class="wrap">';
        echo '<h1>Error</h1>';
        echo '<p>No se pudo cargar la página de gestión simplificada.</p>';
        echo '</div>';
    }
}

// Endpoint temporal para crear tablas (DEBUG)
add_action('wp_ajax_dr_create_tables', function() {
    if (!current_user_can('manage_options')) {
        wp_die('No permissions');
    }

    try {
        Dominios_Reseller_Onboarding_DB::create_tables();
        wp_send_json_success('Tablas creadas exitosamente');
    } catch (Exception $e) {
        wp_send_json_error('Error: ' . $e->getMessage());
    }
});