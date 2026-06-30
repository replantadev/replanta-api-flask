<?php
// includes/whm-functions.php

if (!defined('ABSPATH')) exit;

function mostrar_lista_dominios()
{
    $options = get_option('dominios_reseller_options');
    $token = $options['whm_token'] ?? '';

    if (empty($token)) {
        echo '<p>Por favor, introduce el API Token para obtener los dominios.</p>';
        return;
    }

    $cuentas = obtener_cuentas_whm($token);
    if (current_user_can('manage_options')) {
        // echo '<pre>Respuesta WHM: ' . esc_html(print_r($cuentas, true)) . '</pre>';
    }


    if (!$cuentas || empty($cuentas['data']['acct'])) {
        echo '<pre>Token actual: ' . esc_html($token) . '</pre>';

        echo '<p>No se encontraron cuentas en WHM o hubo un error.</p>';
        return;
    }

    global $wpdb;
    $tabla = $wpdb->prefix . 'dominios_reseller';

    echo '<table class="widefat fixed striped" id="dominios-table">';
    echo '<thead><tr><th>Dominio</th><th>Inicio WHM</th><th>Alta en Replanta</th><th>Tráfico (GB)</th><th>Árboles</th><th>CO2 Evitado (g)</th><th>Estado</th><th>Acción</th></tr></thead><tbody>';

    foreach ($cuentas['data']['acct'] as $cuenta) {
        $dominio = esc_html($cuenta['domain']);
        $startdate = intval($cuenta['unix_startdate']);
        $activo = $cuenta['suspended'] ? 'Suspendido' : 'Activo';

        $existente = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabla WHERE domain = %s", $dominio));
        // Insertar si no existía
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


        echo '<tr>';
        echo "<td>$dominio</td>";
        echo '<td>' . date('Y-m-d', $startdate) . '</td>';
        echo '<td>' . esc_html($existente->fecha_emision ?? '(No reg.)') . '</td>';

        $trafico_bytes = obtener_trafico_real($dominio, $token);
        $trafico_gb = $trafico_bytes ? round($trafico_bytes / (1024 ** 3), 2) : 'N/A';
        echo "<td>$trafico_gb</td>";

        echo "<td><input type='number' class='trees-input' data-domain='$dominio' value='$trees' min='0' /></td>";
        echo "<td><input type='number' class='co2-input' data-domain='$dominio' value='$co2' step='0.01' /></td>";
        echo "<td>$activo</td>";
        echo "<td><button class='button calcular-emisiones' data-domain='$dominio'>Calcular</button></td>";
        echo '</tr>';
        // Añadir dominios adicionales (addon domains)
        $addons = obtener_addons_de_usuario($cuenta['user'], $token);
        
        // VALIDACIÓN: Verificar que $addons sea un array válido
        if (!is_array($addons) || empty($addons)) {
            continue; // Saltar si no hay addons o hay error
        }
        
        foreach ($addons as $addon) {
            // VALIDACIÓN: Verificar que $addon sea array y tenga 'domain'
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

            echo '<tr>';
            echo "<td>&nbsp;&nbsp;&nbsp;&nbsp;&rarr; $addon_domain</td>";
            echo '<td>' . date('Y-m-d', $startdate) . '</td>';
            echo '<td>' . esc_html($addon_existente->fecha_emision ?? '(No reg.)') . '</td>';



            $trafico_bytes = obtener_trafico_real($addon_domain, $token);
            $trafico_gb = $trafico_bytes ? round($trafico_bytes / (1024 ** 3), 2) : 'N/A';
            echo "<td>$trafico_gb</td>";

            echo "<td><input type='number' class='trees-input' data-domain='$addon_domain' value='$trees_addon' min='0' /></td>";
            echo "<td><input type='number' class='co2-input' data-domain='$addon_domain' value='$co2_addon' step='0.01' /></td>";
            echo "<td>Addon</td>";
            echo "<td><button class='button calcular-emisiones' data-domain='$addon_domain'>Calcular</button></td>";
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
            // Insertar si no existía
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
            // actualizar objeto local
            $existente = (object)[
                'fecha_emision' => $fecha_emision_calculada,
                'validez'       => $validez_calculada
            ];
        }
    }
    echo '</tbody></table>';
    echo '<button id="guardar-cambios" class="button button-primary">Guardar cambios</button>';
}

function obtener_cuentas_whm($token, $server = 'uk')
{
    $server_ip = dominios_reseller_get_server_ip($server);
    $whm_user = function_exists('dominios_reseller_get_whm_user')
        ? dominios_reseller_get_whm_user($server)
        : 'root';
    
    if (empty($server_ip)) {
        error_log('[Dominios Reseller] Error: IP del servidor ' . strtoupper($server) . ' no configurada');
        return false;
    }

    $url = 'https://' . $server_ip . ':2087/json-api/listaccts?api.version=1';

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => ["Authorization: whm {$whm_user}:{$token}"],
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 30
    ]);

    $resp = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if ($resp === false) {
        error_log('[Dominios Reseller] Error WHM: ' . curl_error($curl));
        curl_close($curl);
        return false;
    }

    if ($http_code !== 200) {
        error_log("[Dominios Reseller] HTTP Error $http_code en listaccts. Respuesta: " . substr($resp, 0, 500));
        curl_close($curl);
        return false;
    }

    curl_close($curl);

    $response_array = json_decode($resp, true);

    if (!is_array($response_array)) {
        error_log('[Dominios Reseller] Respuesta no es array. Original: ' . substr($resp, 0, 300));
        return false;
    }

    if (!isset($response_array['data']['acct']) || !is_array($response_array['data']['acct'])) {
        error_log('[Dominios Reseller] WHM: No se encontró data[acct] válido en la respuesta: ' . print_r($response_array, true));
        return false;
    }

    return $response_array;
}

function obtener_addons_de_usuario($cpanel_user, $token, $server = 'uk')
{
    $server_ip = dominios_reseller_get_server_ip($server);
    $whm_user = function_exists('dominios_reseller_get_whm_user')
        ? dominios_reseller_get_whm_user($server)
        : 'root';
    
    if (empty($server_ip)) {
        error_log('[Dominios Reseller] Error: IP del servidor ' . strtoupper($server) . ' no configurada para addons');
        return [];
    }

    $query = "https://" . $server_ip . ":2087/json-api/cpanel?cpanel_jsonapi_user={$cpanel_user}&cpanel_jsonapi_apiversion=2&cpanel_jsonapi_module=AddonDomain&cpanel_jsonapi_func=listaddondomains";

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $query,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => ["Authorization: whm {$whm_user}:{$token}"],
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 30
    ]);

    $resp = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if ($resp === false) {
        error_log("[Dominios Reseller] Error en obtener_addons_de_usuario para $cpanel_user: " . curl_error($curl));
        curl_close($curl);
        return [];
    }

    if ($http_code !== 200) {
        error_log("[Dominios Reseller] HTTP Error $http_code para addons de $cpanel_user. Respuesta: " . substr($resp, 0, 500));
        curl_close($curl);
        return [];
    }

    curl_close($curl);
    $data = json_decode($resp, true);

    // VALIDACIÓN ROBUSTA: Verificar estructura de respuesta
    if (!is_array($data)) {
        error_log("[Dominios Reseller] Respuesta no es array para addons de $cpanel_user: " . substr($resp, 0, 200));
        return [];
    }

    if (!isset($data['cpanelresult']['data']) || !is_array($data['cpanelresult']['data'])) {
        error_log("[Dominios Reseller] Estructura inválida en addons para $cpanel_user: " . print_r($data, true));
        return [];
    }

    return $data['cpanelresult']['data'];
}

function obtener_trafico_real($domain, $token, $server = 'uk')
{
    $server_ip = dominios_reseller_get_server_ip($server);
    $whm_user = function_exists('dominios_reseller_get_whm_user')
        ? dominios_reseller_get_whm_user($server)
        : 'root';
    
    if (empty($server_ip)) {
        error_log('[Dominios Reseller] Error: IP del servidor ' . strtoupper($server) . ' no configurada para tráfico');
        return false;
    }

    $url = "https://" . $server_ip . ":2087/json-api/showbw?api.version=1&searchtype=domain&search=" . urlencode($domain);

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => ["Authorization: whm {$whm_user}:{$token}"],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10
    ]);

    $resp = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if ($resp === false) {
        error_log("[Dominios Reseller] Error al obtener tráfico para $domain: " . curl_error($curl));
        curl_close($curl);
        return false;
    }

    if ($http_code !== 200) {
        error_log("[Dominios Reseller] HTTP Error $http_code para tráfico de $domain");
        curl_close($curl);
        return false;
    }

    curl_close($curl);
    $data = json_decode($resp, true);

    if (!is_array($data) || empty($data['data']['acct'])) {
        error_log("[Dominios Reseller] WHM: Sin datos de tráfico para $domain. Respuesta: " . print_r($data, true));
        return false;
    }

    foreach ($data['data']['acct'] as $acct) {
        // PRIORIDAD 1: Usar totalbytes (tráfico acumulado total de la cuenta)
        if (isset($acct['totalbytes']) && $acct['totalbytes'] > 0) {
            error_log("[Dominios Reseller] Tráfico total acumulado para $domain: " . $acct['totalbytes'] . " bytes (" . round($acct['totalbytes'] / (1024**3), 2) . " GB)");
            return intval($acct['totalbytes']);
        }
        
        // PRIORIDAD 2: Usar usage del dominio específico (período actual)
        if (!empty($acct['bwusage']) && is_array($acct['bwusage'])) {
            foreach ($acct['bwusage'] as $entry) {
                if (isset($entry['domain']) && $entry['domain'] === $domain && isset($entry['usage'])) {
                    error_log("[Dominios Reseller] Tráfico del período actual para $domain: " . $entry['usage'] . " bytes");
                    return intval($entry['usage']);
                }
            }
        }
    }

    error_log("[Dominios Reseller] WHM: No se encontró tráfico para $domain en respuesta");
    return false;
}

/**
 * Obtener registros DNS de un dominio desde WHM usando la ZoneEdit cPanel API.
 *
 * No necesita permiso `zone-edit` a nivel WHM: usa la API cPanel de ZoneEdit
 * (el mismo mecanismo que para addon domains), que solo requiere el token WHM
 * con permiso `cpanel-api`. El usuario cPanel se detecta automáticamente.
 *
 * @param  string $domain  Dominio (principal o addon)
 * @param  string $server  'uk' | 'usa'
 * @return array           Registros normalizados, o [] si error
 */
function dominios_reseller_get_whm_dns_records(string $domain, string $server = 'uk'): array
{
    $server_ip = dominios_reseller_get_server_ip($server);
    $whm_user  = function_exists('dominios_reseller_get_whm_user')
        ? dominios_reseller_get_whm_user($server)
        : 'root';

    $options = get_option('dominios_reseller_options', []);
    $token   = $options["{$server}_whm_token"] ?? '';

    if (empty($server_ip) || empty($token)) {
        error_log("[DR DNS] WHM $server: credenciales no configuradas para $domain");
        return [];
    }

    // Paso 1: encontrar el usuario cPanel dueño del dominio
    $cpanel_user = dominios_reseller_find_whm_cpanel_user($domain, $server, $server_ip, $token, $whm_user);

    if (empty($cpanel_user)) {
        error_log("[DR DNS] WHM: no se encontró usuario cPanel para $domain en $server_ip. ¿Es addon domain de otra cuenta?");
        return [];
    }

    error_log("[DR DNS] WHM: usuario cPanel de $domain = $cpanel_user en $server");

    // Paso 2: obtener registros DNS via ZoneEdit cPanel API (no requiere zone-edit WHM)
    $url = "https://{$server_ip}:2087/json-api/cpanel"
         . "?cpanel_jsonapi_user=" . urlencode($cpanel_user)
         . "&cpanel_jsonapi_apiversion=2"
         . "&cpanel_jsonapi_module=ZoneEdit"
         . "&cpanel_jsonapi_func=fetchzone"
         . "&domain=" . urlencode($domain)
         . "&customonly=0";

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => ["Authorization: whm {$whm_user}:{$token}"],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $resp      = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($curl);
    curl_close($curl);

    if ($resp === false || $http_code !== 200) {
        error_log("[DR DNS] WHM ZoneEdit HTTP $http_code para $domain (curl: $curl_err). Resp: " . substr($resp ?: '', 0, 300));
        return [];
    }

    $data = json_decode($resp, true);
    $raw  = $data['cpanelresult']['data'][0]['record'] ?? [];

    if (empty($raw)) {
        error_log("[DR DNS] WHM ZoneEdit: zona vacía para $domain. Resp: " . substr($resp, 0, 300));
        return [];
    }

    error_log("[DR DNS] WHM ZoneEdit: " . count($raw) . " registros para $domain (user=$cpanel_user)");
    return dominios_reseller_normalize_dns_records($raw, $domain, 'whm');
}

/**
 * Buscar qué usuario cPanel es dueño de un dominio (principal o addon).
 * Cachea el resultado 10 minutos para no saturar la API WHM.
 */
function dominios_reseller_find_whm_cpanel_user(
    string $domain, string $server, string $server_ip, string $token, string $whm_user
): string {
    $cache_key = 'dr_whm_user_' . md5($server . $domain);
    $cached    = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }

    $domain = strtolower($domain);

    // Obtener todas las cuentas WHM (reutiliza función existente)
    $accounts = obtener_cuentas_whm($token, $server);
    if (empty($accounts['data']['acct'])) {
        error_log("[DR DNS] WHM: listaccts vacío o sin respuesta para $server_ip");
        return '';
    }

    // Comprobar si es dominio principal de alguna cuenta
    foreach ($accounts['data']['acct'] as $acct) {
        if (strtolower($acct['domain']) === $domain) {
            set_transient($cache_key, $acct['user'], 600);
            return $acct['user'];
        }
    }

    // No es dominio principal — buscar en addon domains de cada cuenta
    foreach ($accounts['data']['acct'] as $acct) {
        $addons = obtener_addons_de_usuario($acct['user'], $token, $server);
        if (!is_array($addons)) {
            continue;
        }
        foreach ($addons as $addon) {
            if (strtolower($addon['domain'] ?? '') === $domain) {
                set_transient($cache_key, $acct['user'], 600);
                return $acct['user'];
            }
        }
    }

    set_transient($cache_key, '', 60); // Caché negativa: 1 min
    return '';
}

/**
 * Normalizar registros DNS crudos de cualquier fuente al formato Cloudflare.
 *
 * @param  array  $raw     Registros en formato nativo del servidor
 * @param  string $domain  Dominio base para calcular nombres relativos
 * @param  string $source  'whm' | 'cyberpanel'
 * @return array           Registros normalizados
 */
function dominios_reseller_normalize_dns_records(array $raw, string $domain, string $source): array
{
    $records = [];
    $domain  = strtolower(rtrim($domain, '.'));
    // Tipos a importar; SOA y NS son gestionados por CF
    $allowed_types = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'CAA', 'SRV', 'PTR'];

    foreach ($raw as $r) {
        $type = strtoupper($r['type'] ?? '');

        if (!in_array($type, $allowed_types)) {
            continue;
        }

        // Nombre: convertir FQDN a relativo (o '@' para el apex)
        $raw_name = strtolower(rtrim($r['name'] ?? '', '.'));
        if ($raw_name === $domain || $raw_name === '') {
            $name = '@';
        } elseif (str_ends_with($raw_name, '.' . $domain)) {
            $name = substr($raw_name, 0, -(strlen($domain) + 1));
        } else {
            $name = $raw_name; // nombre ya relativo (CyberPanel puede devolverlo así)
        }

        // Contenido según tipo
        $content = match ($type) {
            'A'     => $r['address'] ?? ($r['content'] ?? ''),
            'AAAA'  => $r['address'] ?? ($r['content'] ?? ''),
            'CNAME' => rtrim($r['cname'] ?? ($r['content'] ?? ''), '.'),
            'MX'    => rtrim($r['exchange'] ?? ($r['content'] ?? ''), '.'),
            'TXT'   => trim($r['txtdata'] ?? ($r['content'] ?? ''), '"'),
            default => $r['content'] ?? ($r['value'] ?? ''),
        };

        if (empty($content)) {
            continue;
        }

        // Proxied: activar en A/AAAA/CNAME para apex y www (tráfico web principal)
        $proxied = false;
        if (in_array($type, ['A', 'AAAA', 'CNAME'])) {
            $proxied = in_array($name, ['@', 'www']);
        }

        $record = [
            'type'    => $type,
            'name'    => $name,
            'content' => $content,
            'ttl'     => min(86400, max(60, intval($r['ttl'] ?? 3600))),
            'proxied' => $proxied,
        ];

        if ($type === 'MX') {
            $record['priority'] = intval($r['preference'] ?? ($r['priority'] ?? 10));
        }

        $records[] = $record;
    }

    error_log("[DR DNS] Normalizados " . count($records) . " registros de $source para $domain");
    return $records;
}