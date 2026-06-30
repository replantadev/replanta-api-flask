<?php
/**
 * Cedro Service — CyberPanel API integration
 *
 * Gestiona el ciclo de vida de cuentas de hosting en el servidor Cedro
 * (CyberPanel sobre Hetzner). Corre en paralelo con WHM para clientes
 * existentes; los nuevos contratos Cedro van aquí.
 *
 * @package Dominios_Reseller
 * @since   1.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dominios_Reseller_Cedro_Service {

    private static ?Dominios_Reseller_Cedro_Service $instance = null;

    /** Identificador de servidor en la tabla wp_dominios_reseller */
    const SERVER_ID = 'cedro';

    private function __construct() {}

    public static function get_instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ── Credenciales ──────────────────────────────────────────────────────

    private function base(): string {
        return rtrim(get_option('dr_cedro_url', 'https://cedro.replanta.net:8090'), '/');
    }

    private function admin(): string {
        return get_option('dr_cedro_admin', '');
    }

    private function pass(): string {
        return get_option('dr_cedro_pass', '');
    }

    private function has_credentials(): bool {
        return !empty($this->admin()) && !empty($this->pass());
    }

    // ── API helper ────────────────────────────────────────────────────────

    private function post(string $endpoint, array $payload): array|WP_Error {
        $payload['adminUser'] = $this->admin();
        $payload['adminPass'] = $this->pass();

        $resp = wp_remote_post($this->base() . $endpoint, [
            'timeout'   => 20,
            'sslverify' => false,
            'headers'   => ['Content-Type' => 'application/json'],
            'body'      => wp_json_encode($payload),
        ]);

        if (is_wp_error($resp)) {
            return $resp;
        }

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if (!is_array($body)) {
            return new WP_Error('invalid_response', 'Respuesta no JSON de CyberPanel');
        }

        return $body;
    }

    // ── Listado y sincronización ──────────────────────────────────────────

    /**
     * Devuelve los websites Cedro conocidos desde el tracking de provisionamiento.
     * CyberPanel no expone un endpoint de lista en su API REST (/api/*).
     * Fuente de verdad: WP option dr_cedro_provisioned (se escribe al provisionar).
     */
    public function list_websites(): array {
        $provisioned = get_option('dr_cedro_provisioned', []);
        return array_values(array_map(fn($p) => [
            'domain'     => $p['domain'],
            'package'    => 'cedro',
            'adminEmail' => $p['email'] ?? '',
            'state'      => 'Active',
        ], $provisioned));
    }

    /**
     * Sincroniza los websites de Cedro a la tabla wp_dominios_reseller.
     * Devuelve número de dominios insertados/actualizados.
     */
    public function sync_to_db(): int {
        $websites = $this->list_websites();

        global $wpdb;
        $table   = $wpdb->prefix . 'dominios_reseller';
        $updated = 0;

        foreach ($websites as $site) {
            $domain = strtolower(trim($site['domain'] ?? ''));
            if (empty($domain)) {
                continue;
            }

            $status = ($site['state'] ?? 'Active') === 'Active' ? 'Activo' : 'Suspendido';

            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM $table WHERE domain = %s AND server = %s",
                $domain, self::SERVER_ID
            ));

            $data = [
                'domain'     => $domain,
                'server'     => self::SERVER_ID,
                'status'     => $status,
                'is_primary' => 1,
                'last_sync'  => current_time('mysql'),
            ];

            if ($existing) {
                $wpdb->update($table, $data, ['id' => $existing->id]);
            } else {
                $data['startdate'] = time();
                $wpdb->insert($table, $data);
            }

            $updated++;
        }

        return $updated;
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────

    public function provision(string $domain, string $email, string $name, string $order_id): array|WP_Error {
        if (!$this->has_credentials()) {
            return new WP_Error('no_credentials', 'CyberPanel no configurado');
        }

        $package = get_option('dr_cedro_package', 'cedro');

        // Generar usuario: prefijo del dominio + hash del order_id
        $prefix   = strtolower(preg_replace('/[^a-z0-9]/', '', explode('.', $domain)[0]));
        $cp_user  = substr($prefix, 0, 8) . substr(md5($order_id), 0, 4);
        $cp_pass  = wp_generate_password(16, false);

        // Pre-crear usuario con nombre del cliente
        $this->create_user($cp_user, $cp_pass, $email, $name);

        $result = $this->post('/api/createWebsite', [
            'domainName'    => $domain,
            'ownerEmail'    => $email,
            'ownerPassword' => $cp_pass,
            'packageName'   => $package,
            'websiteOwner'  => $cp_user,
            'php'           => 'PHP 8.3',
            'ssl'           => 1,
            'dkim'          => 1,
            'openBasedir'   => 1,
        ]);

        if (is_wp_error($result)) {
            return $result;
        }

        if (empty($result['createWebSiteStatus'])) {
            return new WP_Error('cp_provision_error',
                $result['error_message'] ?? $result['msg'] ?? 'Error en createWebsite');
        }

        $result['_cpUser']        = $cp_user;
        $result['_ownerPassword'] = $cp_pass;

        // Registrar en DB
        global $wpdb;
        $table = $wpdb->prefix . 'dominios_reseller';
        $wpdb->replace($table, [
            'domain'     => $domain,
            'server'     => self::SERVER_ID,
            'status'     => 'Activo',
            'is_primary' => 1,
            'startdate'  => time(),
            'last_sync'  => current_time('mysql'),
        ]);

        return $result;
    }

    public function suspend(string $domain): bool {
        $result = $this->post('/api/submitWebsiteStatus', [
            'websiteName' => $domain,
            'state'       => 'Suspend',
        ]);

        if (is_wp_error($result) || empty($result['websiteStatus'])) {
            error_log('[Cedro] suspend error for ' . $domain . ': ' . wp_json_encode($result));
            return false;
        }

        $this->update_db_status($domain, 'Suspendido');
        return true;
    }

    public function unsuspend(string $domain): bool {
        $result = $this->post('/api/submitWebsiteStatus', [
            'websiteName' => $domain,
            'state'       => 'Unsuspend',
        ]);

        if (is_wp_error($result) || empty($result['websiteStatus'])) {
            error_log('[Cedro] unsuspend error for ' . $domain . ': ' . wp_json_encode($result));
            return false;
        }

        $this->update_db_status($domain, 'Activo');
        return true;
    }

    /** Prueba la conexión con CyberPanel. Devuelve true o WP_Error. */
    public function test_connection(): bool|WP_Error {
        if (!$this->has_credentials()) {
            return new WP_Error('no_credentials', 'Credenciales no configuradas');
        }

        $result = $this->post('/api/verifyConn', []);

        if (is_wp_error($result)) {
            return $result;
        }

        if (empty($result['verifyConn'])) {
            return new WP_Error('auth_failed', $result['error_message'] ?? 'Autenticacion fallida en CyberPanel');
        }

        return true;
    }

    // ── Helpers privados ──────────────────────────────────────────────────

    private function create_user(string $username, string $password, string $email, string $name): void {
        $parts = preg_split('/\s+/', trim($name), 2);
        $first = preg_replace('/[^a-zA-Z\'\-,. ]/', '', $parts[0] ?? 'Client');
        $last  = preg_replace('/[^a-zA-Z\'\-,. ]/', '', $parts[1] ?? $first);

        if (strlen($first) < 3) { $first = str_pad($first, 3, 'a'); }
        if (strlen($last)  < 3) { $last  = str_pad($last,  3, 'a'); }

        $result = $this->post('/api/submitUserCreation', [
            'firstName'     => $first,
            'lastName'      => $last,
            'email'         => $email,
            'userName'      => $username,
            'password'      => $password,
            'websitesLimit' => 1,
            'selectedACL'   => 'user',
            'securityLevel' => 'LOW',
        ]);

        error_log('[Cedro] create_user ' . $username . ': ' . wp_json_encode($result));
    }

    private function update_db_status(string $domain, string $status): void {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'dominios_reseller',
            ['status' => $status, 'last_sync' => current_time('mysql')],
            ['domain' => $domain, 'server' => self::SERVER_ID]
        );
    }

    // ── Emails ciclo de vida ──────────────────────────────────────────────

    public function send_welcome_email(string $domain, string $email, string $name, string $cp_user, string $cp_pass): void {
        $panel   = $this->base();
        $ns1     = 'ns1.replanta.net';
        $ns2     = 'ns2.replanta.net';
        $support = 'info@replanta.dev';
        $greeting = !empty($name) ? 'Hola ' . esc_html($name) . ',' : 'Hola,';
        $headers  = ['Content-Type: text/html; charset=UTF-8', 'From: Replanta Hosting <info@replanta.dev>'];

        $msg = '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;color:#333;max-width:600px;margin:0 auto">
<h2 style="color:#2e7d32">¡Tu alojamiento web está activo!</h2>
<p>' . $greeting . '</p>
<p>Tu plan <strong>Cedro</strong> para <strong>' . esc_html($domain) . '</strong> ya está listo.</p>
<h3 style="color:#2e7d32">Panel de control</h3>
<table style="border-collapse:collapse;width:100%">
  <tr><td style="padding:6px;font-weight:bold">URL</td><td style="padding:6px"><a href="' . esc_url($panel) . '">' . esc_html($panel) . '</a></td></tr>
  <tr style="background:#f5f5f5"><td style="padding:6px;font-weight:bold">Usuario</td><td style="padding:6px"><code>' . esc_html($cp_user) . '</code></td></tr>
  <tr><td style="padding:6px;font-weight:bold">Contraseña</td><td style="padding:6px"><code>' . esc_html($cp_pass) . '</code></td></tr>
</table>
<h3 style="color:#2e7d32">Nameservers</h3>
<table style="border-collapse:collapse;width:100%">
  <tr><td style="padding:6px;font-weight:bold">NS1</td><td style="padding:6px"><code>' . $ns1 . '</code></td></tr>
  <tr style="background:#f5f5f5"><td style="padding:6px;font-weight:bold">NS2</td><td style="padding:6px"><code>' . $ns2 . '</code></td></tr>
</table>
<p style="margin-top:20px">Soporte: <a href="mailto:' . $support . '">' . $support . '</a></p>
<p style="color:#888;font-size:12px">Replanta · Alojamiento verde · replanta.net</p>
</body></html>';

        wp_mail($email, '¡Tu alojamiento Cedro está listo! — ' . $domain, $msg, $headers);

        $admin_msg = '<p>Nuevo sitio Cedro: <strong>' . esc_html($domain) . '</strong><br>
Email: ' . esc_html($email) . '<br>Nombre: ' . esc_html($name ?: '—') . '<br>
CP user: <code>' . esc_html($cp_user) . '</code> / pass: <code>' . esc_html($cp_pass) . '</code></p>';
        wp_mail('info@replanta.dev', '[Cedro] Provisionado: ' . $domain, $admin_msg, $headers);
    }

    public function send_suspension_email(string $domain, string $email): void {
        $headers = ['Content-Type: text/html; charset=UTF-8', 'From: Replanta Hosting <info@replanta.dev>'];
        if (!empty($email)) {
            wp_mail($email,
                'Tu alojamiento ' . $domain . ' ha sido suspendido',
                '<p>Tu alojamiento <strong>' . esc_html($domain) . '</strong> ha sido suspendido. '
                . 'Para reactivarlo contacta con <a href="mailto:info@replanta.dev">info@replanta.dev</a>. '
                . 'Tus datos se conservarán 30 días.</p>',
                $headers
            );
        }
        wp_mail('info@replanta.dev', '[Cedro] Suspendido: ' . $domain,
            '<p>Suspensión procesada para <strong>' . esc_html($domain) . '</strong>.</p>', $headers);
    }

    public function send_reactivation_email(string $domain, string $email): void {
        $headers = ['Content-Type: text/html; charset=UTF-8', 'From: Replanta Hosting <info@replanta.dev>'];
        if (!empty($email)) {
            wp_mail($email,
                '¡Tu alojamiento ' . $domain . ' está reactivado!',
                '<p>Tu alojamiento <strong>' . esc_html($domain) . '</strong> está activo de nuevo. '
                . 'Panel: <a href="' . esc_url($this->base()) . '">' . esc_url($this->base()) . '</a></p>',
                $headers
            );
        }
        wp_mail('info@replanta.dev', '[Cedro] Reactivado: ' . $domain,
            '<p>Reactivación procesada para <strong>' . esc_html($domain) . '</strong>.</p>', $headers);
    }

    public function send_cancellation_email(string $domain, string $email, string $order_id): void {
        $headers = ['Content-Type: text/html; charset=UTF-8', 'From: Replanta Hosting <info@replanta.dev>'];
        if (!empty($email)) {
            wp_mail($email,
                'Tu alojamiento ' . $domain . ' ha sido cancelado',
                '<p>Tu plan Cedro para <strong>' . esc_html($domain) . '</strong> ha sido cancelado. '
                . 'Tus datos se conservarán 30 días. Escríbenos si necesitas una copia: '
                . '<a href="mailto:info@replanta.dev">info@replanta.dev</a>.</p>',
                $headers
            );
        }
        wp_mail('info@replanta.dev',
            '[Cedro] Cancelado: ' . $domain,
            '<p>Cancelación de <strong>' . esc_html($domain) . '</strong> (orden: ' . esc_html($order_id) . '). '
            . 'Suspendido, eliminación en 30 días (opción rphub_upmind_pending_deletion).</p>',
            $headers
        );
    }

    // ── DNS ───────────────────────────────────────────────────────────────────

    /**
     * Obtener registros DNS de un dominio desde CyberPanel.
     *
     * Devuelve registros normalizados (misma estructura que WHM) para
     * su importación a Cloudflare.
     *
     * @param  string $domain  Dominio principal
     * @return array           Registros normalizados, o [] si error
     */
    public function get_dns_records(string $domain): array {
        if (!$this->has_credentials()) {
            error_log("[DR DNS] CyberPanel: sin credenciales para obtener DNS de $domain");
            return [];
        }

        // CyberPanel expone los registros DNS a través de su módulo DNS
        $result = $this->post('/dns/getRecords', ['domainName' => $domain]);

        if (is_wp_error($result)) {
            error_log("[DR DNS] CyberPanel DNS error para $domain: " . $result->get_error_message());
            return [];
        }

        // CyberPanel devuelve status=1 en éxito, mensaje de error si falla
        if (($result['status'] ?? 0) != 1 && ($result['errorMessage'] ?? '') !== 'None') {
            // Intentar endpoint alternativo: /dns/getZone
            $result = $this->post('/dns/getZone', ['domainName' => $domain]);
            if (is_wp_error($result) || ($result['status'] ?? 0) != 1) {
                error_log("[DR DNS] CyberPanel: zona no encontrada para $domain. Respuesta: " . json_encode($result));
                return [];
            }
        }

        $raw = $result['data'] ?? $result['records'] ?? [];
        if (empty($raw) || !is_array($raw)) {
            error_log("[DR DNS] CyberPanel: zona vacía para $domain");
            return [];
        }

        // Normalizar al formato común (usa la función de whm-functions.php)
        if (function_exists('dominios_reseller_normalize_dns_records')) {
            return dominios_reseller_normalize_dns_records($raw, $domain, 'cyberpanel');
        }

        return [];
    }

    public function send_expiry_email(string $domain, string $email, string $name, string $exp_date): void {
        $headers  = ['Content-Type: text/html; charset=UTF-8', 'From: Replanta Hosting <info@replanta.dev>'];
        $greeting = !empty($name) ? 'Hola ' . esc_html($name) . ',' : 'Hola,';
        $exp_fmt  = !empty($exp_date) ? date('d/m/Y', strtotime($exp_date)) : 'próximamente';

        wp_mail($email,
            'Tu alojamiento ' . $domain . ' vence el ' . $exp_fmt,
            '<p>' . $greeting . '</p><p>Tu plan Cedro para <strong>' . esc_html($domain) . '</strong> '
            . 'vence el <strong>' . esc_html($exp_fmt) . '</strong>. Renueva para evitar la suspensión.</p>',
            $headers
        );
        wp_mail('info@replanta.dev',
            '[Cedro] Próximo a vencer: ' . $domain . ' (' . $exp_fmt . ')',
            '<p>Recordatorio enviado a ' . esc_html($email) . ' para ' . esc_html($domain) . '.</p>',
            $headers
        );
    }
}
