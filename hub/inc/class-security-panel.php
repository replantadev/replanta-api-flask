<?php
/**
 * Panel de Seguridad Avanzado
 * Consolida información de seguridad de múltiples fuentes
 */

if (!defined('ABSPATH')) {
    exit;
}

class RP_Hub_Security_Panel {
    
    public function __construct() {
        // AJAX handlers
        add_action('wp_ajax_rphub_security_get_dashboard', array($this, 'ajax_get_security_dashboard'));
        add_action('wp_ajax_rphub_security_run_scan', array($this, 'ajax_run_security_scan'));
        add_action('wp_ajax_rphub_security_get_vulnerabilities', array($this, 'ajax_get_vulnerabilities'));
        add_action('wp_ajax_rphub_security_get_malware_scan', array($this, 'ajax_get_malware_scan'));
        add_action('wp_ajax_rphub_security_get_firewall_events', array($this, 'ajax_get_firewall_events'));
        add_action('wp_ajax_rphub_security_update_settings', array($this, 'ajax_update_security_settings'));
        
        // Cron jobs
        add_action('rphub_daily_security_check', array($this, 'run_daily_security_check'));
        add_action('rphub_weekly_vulnerability_scan', array($this, 'run_weekly_vulnerability_scan'));
        
        // Hooks para integrar con otras fuentes
        add_action('rphub_security_scan_complete', array($this, 'process_security_scan_results'), 10, 2);
        
        // Alertas de seguridad
        add_action('init', array($this, 'check_security_alerts'));
    }

    /**
     * Obtiene dashboard completo de seguridad
     */
    public function get_security_dashboard($site_id) {
        $dashboard = array(
            'overall_score' => $this->calculate_overall_security_score($site_id),
            'last_scan' => RPHUB_Database::get_site_meta($site_id, 'last_security_scan'),
            'vulnerabilities' => $this->get_vulnerabilities_summary($site_id),
            'malware_status' => $this->get_malware_status($site_id),
            'firewall_stats' => $this->get_firewall_stats($site_id),
            'ssl_status' => $this->get_ssl_status($site_id),
            'security_plugins' => $this->get_security_plugins_status($site_id),
            'file_integrity' => $this->get_file_integrity_status($site_id),
            'login_security' => $this->get_login_security_status($site_id),
            'recommendations' => $this->get_security_recommendations($site_id),
            'alerts' => $this->get_security_alerts($site_id)
        );

        return $dashboard;
    }

    /**
     * Calcula puntuación general de seguridad
     */
    private function calculate_overall_security_score($site_id) {
        $score = 100;
        $factors = array();

        // Vulnerabilidades (-30 puntos máximo)
        $vulnerabilities = $this->get_vulnerabilities_count($site_id);
        $vuln_penalty = min($vulnerabilities * 5, 30);
        $score -= $vuln_penalty;
        $factors['vulnerabilities'] = array(
            'count' => $vulnerabilities,
            'penalty' => $vuln_penalty,
            'weight' => 30
        );

        // Malware (-40 puntos si está infectado)
        $malware_status = $this->get_malware_status($site_id);
        if ($malware_status['infected']) {
            $score -= 40;
            $factors['malware'] = array('penalty' => 40, 'weight' => 40);
        }

        // SSL (-15 puntos si no está configurado correctamente)
        $ssl_status = $this->get_ssl_status($site_id);
        if (!$ssl_status['valid'] || !$ssl_status['properly_configured']) {
            $score -= 15;
            $factors['ssl'] = array('penalty' => 15, 'weight' => 15);
        }

        // Plugins de seguridad (-10 puntos si no hay ninguno)
        $security_plugins = $this->get_security_plugins_status($site_id);
        if (empty($security_plugins['active_plugins'])) {
            $score -= 10;
            $factors['security_plugins'] = array('penalty' => 10, 'weight' => 10);
        }

        // File integrity (-20 puntos si hay archivos modificados)
        $file_integrity = $this->get_file_integrity_status($site_id);
        if ($file_integrity['compromised_files'] > 0) {
            $penalty = min($file_integrity['compromised_files'] * 2, 20);
            $score -= $penalty;
            $factors['file_integrity'] = array(
                'compromised_files' => $file_integrity['compromised_files'],
                'penalty' => $penalty,
                'weight' => 20
            );
        }

        // Login security (-5 puntos por cada problema)
        $login_security = $this->get_login_security_status($site_id);
        $login_issues = 0;
        if (!$login_security['2fa_enabled']) $login_issues++;
        if (!$login_security['strong_passwords']) $login_issues++;
        if (!$login_security['login_attempts_limited']) $login_issues++;
        
        $login_penalty = $login_issues * 5;
        $score -= $login_penalty;
        $factors['login_security'] = array(
            'issues' => $login_issues,
            'penalty' => $login_penalty,
            'weight' => 15
        );

        return array(
            'score' => max($score, 0),
            'factors' => $factors,
            'grade' => $this->get_security_grade(max($score, 0)),
            'calculated_at' => current_time('mysql')
        );
    }

    /**
     * Obtiene grado de seguridad basado en puntuación
     */
    private function get_security_grade($score) {
        if ($score >= 90) return 'A+';
        if ($score >= 80) return 'A';
        if ($score >= 70) return 'B';
        if ($score >= 60) return 'C';
        if ($score >= 50) return 'D';
        return 'F';
    }

    /**
     * Obtiene resumen de vulnerabilidades
     */
    private function get_vulnerabilities_summary($site_id) {
        $vulnerabilities_data = RPHUB_Database::get_site_meta($site_id, 'security_vulnerabilities');
        
        if (!$vulnerabilities_data) {
            return array(
                'total' => 0,
                'critical' => 0,
                'high' => 0,
                'medium' => 0,
                'low' => 0,
                'last_scan' => null,
                'details' => array()
            );
        }

        $vulnerabilities = json_decode($vulnerabilities_data, true);
        $summary = array(
            'total' => count($vulnerabilities),
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'last_scan' => RPHUB_Database::get_site_meta($site_id, 'last_vulnerability_scan'),
            'details' => array()
        );

        foreach ($vulnerabilities as $vuln) {
            $severity = strtolower($vuln['severity'] ?? 'low');
            if (isset($summary[$severity])) {
                $summary[$severity]++;
            }

            $summary['details'][] = array(
                'id' => $vuln['id'] ?? uniqid(),
                'title' => $vuln['title'] ?? 'Vulnerabilidad desconocida',
                'severity' => $vuln['severity'] ?? 'low',
                'component' => $vuln['component'] ?? 'unknown',
                'version' => $vuln['version'] ?? null,
                'fixed_version' => $vuln['fixed_version'] ?? null,
                'description' => $vuln['description'] ?? '',
                'cve' => $vuln['cve'] ?? null,
                'found_at' => $vuln['found_at'] ?? current_time('mysql')
            );
        }

        return $summary;
    }

    /**
     * Obtiene estado de malware
     */
    private function get_malware_status($site_id) {
        $malware_data = RPHUB_Database::get_site_meta($site_id, 'malware_scan_results');
        
        if (!$malware_data) {
            return array(
                'infected' => false,
                'last_scan' => null,
                'threats_found' => 0,
                'threats' => array(),
                'scan_engine' => null
            );
        }

        $data = json_decode($malware_data, true);
        
        return array(
            'infected' => $data['infected'] ?? false,
            'last_scan' => $data['scan_date'] ?? null,
            'threats_found' => count($data['threats'] ?? array()),
            'threats' => $data['threats'] ?? array(),
            'scan_engine' => $data['engine'] ?? 'unknown',
            'quarantined_files' => $data['quarantined'] ?? array()
        );
    }

    /**
     * Obtiene estadísticas de firewall
     */
    private function get_firewall_stats($site_id) {
        // Combinar datos de Cloudflare y otros firewalls
        $cloudflare_events = RPHUB_Database::get_site_meta($site_id, 'cloudflare_security_events');
        $waf_logs = RPHUB_Database::get_site_meta($site_id, 'waf_logs');
        
        $stats = array(
            'blocked_attacks' => 0,
            'blocked_ips' => array(),
            'attack_types' => array(),
            'last_24h' => 0,
            'last_week' => 0,
            'top_threats' => array(),
            'countries_blocked' => array()
        );

        // Procesar eventos de Cloudflare
        if ($cloudflare_events) {
            $events = json_decode($cloudflare_events, true);
            foreach ($events as $event) {
                $stats['blocked_attacks']++;
                
                // Contar ataques por tipo
                $action = $event['action'] ?? 'unknown';
                if (!isset($stats['attack_types'][$action])) {
                    $stats['attack_types'][$action] = 0;
                }
                $stats['attack_types'][$action]++;

                // IPs bloqueadas
                if (isset($event['source']['ip'])) {
                    $stats['blocked_ips'][] = $event['source']['ip'];
                }

                // Países
                if (isset($event['source']['country'])) {
                    $country = $event['source']['country'];
                    if (!isset($stats['countries_blocked'][$country])) {
                        $stats['countries_blocked'][$country] = 0;
                    }
                    $stats['countries_blocked'][$country]++;
                }

                // Contar por período
                $event_time = strtotime($event['occurred_at']);
                if ($event_time > (time() - 86400)) { // 24 horas
                    $stats['last_24h']++;
                }
                if ($event_time > (time() - 604800)) { // 7 días
                    $stats['last_week']++;
                }
            }
        }

        // Procesar logs de WAF adicionales
        if ($waf_logs) {
            $logs = json_decode($waf_logs, true);
            // Similar processing for other WAF sources
        }

        // Eliminar duplicados en IPs
        $stats['blocked_ips'] = array_unique($stats['blocked_ips']);
        
        // Top threats
        arsort($stats['attack_types']);
        $stats['top_threats'] = array_slice($stats['attack_types'], 0, 5, true);

        return $stats;
    }

    /**
     * Obtiene estado SSL
     */
    private function get_ssl_status($site_id) {
        $site = RPHUB_Database::get_site($site_id);
        $site_url = $site ? $site->url : '';
        $ssl_data = RPHUB_Database::get_site_meta($site_id, 'ssl_status');
        
        if (!$ssl_data) {
            // Hacer verificación en tiempo real si no hay datos
            $ssl_data = $this->check_ssl_status($site_url);
            RPHUB_Database::update_site_meta($site_id, 'ssl_status', json_encode($ssl_data));
        } else {
            $ssl_data = json_decode($ssl_data, true);
        }

        return $ssl_data;
    }

    /**
     * Verifica estado SSL de un sitio
     */
    private function check_ssl_status($url) {
        $parsed_url = parse_url($url);
        $host = $parsed_url['host'];
        $port = 443;

        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $status = array(
            'valid' => false,
            'expires_at' => null,
            'days_until_expiry' => null,
            'issuer' => null,
            'properly_configured' => false,
            'errors' => array(),
            'checked_at' => current_time('mysql')
        );

        try {
            $socket = stream_socket_client(
                "ssl://{$host}:{$port}",
                $errno,
                $errstr,
                30,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if ($socket) {
                $cert = stream_context_get_params($socket)['options']['ssl']['peer_certificate'];
                $cert_info = openssl_x509_parse($cert);

                if ($cert_info) {
                    $status['valid'] = true;
                    $status['expires_at'] = date('Y-m-d H:i:s', $cert_info['validTo_time_t']);
                    $status['days_until_expiry'] = floor(($cert_info['validTo_time_t'] - time()) / 86400);
                    $status['issuer'] = $cert_info['issuer']['CN'] ?? 'Unknown';
                    
                    // Verificar si está configurado correctamente
                    $status['properly_configured'] = $this->verify_ssl_configuration($url);
                }

                fclose($socket);
            }
        } catch (Exception $e) {
            $status['errors'][] = $e->getMessage();
        }

        return $status;
    }

    /**
     * Verifica configuración SSL
     */
    private function verify_ssl_configuration($url) {
        // Verificar redirección HTTP a HTTPS
        $http_url = str_replace('https://', 'http://', $url);
        
        $response = wp_remote_get($http_url, array(
            'timeout' => 10,
            'redirection' => 0
        ));

        if (!is_wp_error($response)) {
            $code = wp_remote_retrieve_response_code($response);
            $location = wp_remote_retrieve_header($response, 'location');
            
            // Debe redirigir a HTTPS
            return ($code === 301 || $code === 302) && strpos($location, 'https://') === 0;
        }

        return false;
    }

    /**
     * Obtiene estado de plugins de seguridad
     */
    private function get_security_plugins_status($site_id) {
        $plugins_info = RPHUB_Database::get_site_meta($site_id, 'plugins_info');
        
        if (!$plugins_info) {
            return array(
                'active_plugins' => array(),
                'total_security_plugins' => 0,
                'recommended_missing' => array()
            );
        }

        $plugins = json_decode($plugins_info, true);
        $security_plugins = array();
        
        // Lista de plugins de seguridad conocidos
        $known_security_plugins = array(
            'wordfence' => 'Wordfence Security',
            'sucuri-scanner' => 'Sucuri Security',
            'bulletproof-security' => 'BulletProof Security',
            'ithemes-security-pro' => 'iThemes Security Pro',
            'all-in-one-wp-security-and-firewall' => 'All In One WP Security',
            'jetpack' => 'Jetpack (Módulo de seguridad)',
            'wp-security-audit-log' => 'WP Security Audit Log'
        );

        if (isset($plugins['list'])) {
            foreach ($plugins['list'] as $plugin) {
                $plugin_slug = strtolower(str_replace(' ', '-', $plugin['name']));
                
                foreach ($known_security_plugins as $known_slug => $known_name) {
                    if (strpos($plugin_slug, $known_slug) !== false && $plugin['status'] === 'active') {
                        $security_plugins[] = array(
                            'name' => $plugin['name'],
                            'version' => $plugin['version'],
                            'update_available' => $plugin['update_available'] ?? false
                        );
                        break;
                    }
                }
            }
        }

        return array(
            'active_plugins' => $security_plugins,
            'total_security_plugins' => count($security_plugins),
            'recommended_missing' => $this->get_missing_security_plugins($security_plugins)
        );
    }

    /**
     * Obtiene plugins de seguridad recomendados faltantes
     */
    private function get_missing_security_plugins($current_plugins) {
        $recommended = array(
            'Firewall/Protection' => 'Wordfence, Sucuri, o similar',
            'Backup Security' => 'UpdraftPlus, BackWPup, o similar',
            'Login Security' => '2FA, Login LockDown, o similar',
            'File Monitoring' => 'WP Security Audit Log o similar'
        );

        $missing = array();
        $current_names = array_column($current_plugins, 'name');
        
        // Lógica simplificada - en producción sería más sofisticada
        if (empty($current_plugins)) {
            $missing = $recommended;
        }

        return $missing;
    }

    /**
     * Obtiene estado de integridad de archivos
     */
    private function get_file_integrity_status($site_id) {
        $integrity_data = RPHUB_Database::get_site_meta($site_id, 'file_integrity_status');
        
        if (!$integrity_data) {
            return array(
                'scan_date' => null,
                'compromised_files' => 0,
                'modified_files' => array(),
                'new_files' => array(),
                'deleted_files' => array(),
                'status' => 'unknown'
            );
        }

        return json_decode($integrity_data, true);
    }

    /**
     * Obtiene estado de seguridad de login
     */
    private function get_login_security_status($site_id) {
        $login_data = RPHUB_Database::get_site_meta($site_id, 'login_security_status');
        
        if (!$login_data) {
            return array(
                '2fa_enabled' => false,
                'strong_passwords' => false,
                'login_attempts_limited' => false,
                'admin_user_secure' => false,
                'failed_login_attempts' => 0,
                'last_failed_login' => null
            );
        }

        return json_decode($login_data, true);
    }

    /**
     * Obtiene recomendaciones de seguridad
     */
    private function get_security_recommendations($site_id) {
        $recommendations = array();
        
        // Análisis basado en el estado actual
        $vulnerabilities = $this->get_vulnerabilities_summary($site_id);
        $ssl_status = $this->get_ssl_status($site_id);
        $security_plugins = $this->get_security_plugins_status($site_id);
        $login_security = $this->get_login_security_status($site_id);

        // Recomendaciones por vulnerabilidades
        if ($vulnerabilities['total'] > 0) {
            $recommendations[] = array(
                'priority' => 'critical',
                'title' => 'Corregir vulnerabilidades detectadas',
                'description' => "Se encontraron {$vulnerabilities['total']} vulnerabilidades que requieren atención inmediata.",
                'action' => 'update_components'
            );
        }

        // Recomendaciones por SSL
        if (!$ssl_status['valid'] || !$ssl_status['properly_configured']) {
            $recommendations[] = array(
                'priority' => 'high',
                'title' => 'Configurar SSL correctamente',
                'description' => 'El certificado SSL no está configurado correctamente o está próximo a vencer.',
                'action' => 'configure_ssl'
            );
        }

        // Recomendaciones por plugins de seguridad
        if (empty($security_plugins['active_plugins'])) {
            $recommendations[] = array(
                'priority' => 'high',
                'title' => 'Instalar plugin de seguridad',
                'description' => 'Se recomienda instalar al menos un plugin de seguridad como Wordfence o Sucuri.',
                'action' => 'install_security_plugin'
            );
        }

        // Recomendaciones por login security
        if (!$login_security['2fa_enabled']) {
            $recommendations[] = array(
                'priority' => 'medium',
                'title' => 'Habilitar autenticación de dos factores',
                'description' => 'Mejora significativamente la seguridad del acceso administrativo.',
                'action' => 'enable_2fa'
            );
        }

        return $recommendations;
    }

    /**
     * Obtiene alertas de seguridad
     */
    private function get_security_alerts($site_id) {
        $alerts = RPHUB_Database::get_site_meta($site_id, 'security_alerts');
        
        if (!$alerts) {
            return array();
        }

        return json_decode($alerts, true);
    }

    /**
     * Obtiene conteo de vulnerabilidades
     */
    private function get_vulnerabilities_count($site_id) {
        $vulnerabilities = $this->get_vulnerabilities_summary($site_id);
        return $vulnerabilities['total'];
    }

    /**
     * AJAX: Obtener dashboard de seguridad
     */
    public function ajax_get_security_dashboard() {
        if (!wp_verify_nonce($_POST['nonce'], 'rphub_dashboard_nonce')) {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
            wp_send_json_error(['message' => 'Security check failed']); return;
        }

        $site_id = intval($_POST['site_id']);
        
        try {
            $dashboard = $this->get_security_dashboard($site_id);
            wp_send_json_success($dashboard);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Ejecutar escaneo de seguridad
     */
    public function ajax_run_security_scan() {
        if (!wp_verify_nonce($_POST['nonce'], 'rphub_dashboard_nonce')) {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
            wp_send_json_error(['message' => 'Security check failed']); return;
        }

        $site_id = intval($_POST['site_id']);
        $scan_type = sanitize_text_field($_POST['scan_type'] ?? 'full');
        
        try {
            $result = $this->run_security_scan($site_id, $scan_type);
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Ejecuta escaneo de seguridad
     */
    private function run_security_scan($site_id, $scan_type = 'full') {
        // Implementar lógica de escaneo según el tipo
        $scan_results = array(
            'scan_id' => uniqid('scan_'),
            'started_at' => current_time('mysql'),
            'type' => $scan_type,
            'status' => 'running'
        );

        // Programar el escaneo
        wp_schedule_single_event(time(), 'rphub_execute_security_scan', array($site_id, $scan_type, $scan_results['scan_id']));

        return $scan_results;
    }

    /**
     * Chequeo diario de seguridad
     */
    public function run_daily_security_check() {
        // Obtener todos los sitios
        $sites = RPHUB_Database::get_all_sites();

        foreach ($sites as $site) {
            // Actualizar información de seguridad
            $this->update_security_info($site->id);
            
            // Esperar entre sitios
            sleep(5);
        }
    }

    /**
     * Actualiza información de seguridad de un sitio
     */
    private function update_security_info($site_id) {
        // Recalcular score de seguridad
        $security_score = $this->calculate_overall_security_score($site_id);
        RPHUB_Database::update_site_meta($site_id, 'security_score', $security_score['score']);
        RPHUB_Database::update_site_meta($site_id, 'security_score_details', json_encode($security_score));
        
        // Verificar SSL
        $site = RPHUB_Database::get_site($site_id);
        $site_url = $site ? $site->url : '';
        if ($site_url) {
            $ssl_status = $this->check_ssl_status($site_url);
            RPHUB_Database::update_site_meta($site_id, 'ssl_status', json_encode($ssl_status));
        }

        RPHUB_Database::update_site_meta($site_id, 'last_security_update', current_time('mysql'));
    }

    /**
     * Verifica alertas de seguridad
     */
    public function check_security_alerts() {
        // Solo en admin y para usuarios autorizados
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        // Verificar si hay sitios con problemas críticos de seguridad
        $all_sites = RPHUB_Database::get_all_sites();
        $critical_sites = array_filter($all_sites, function($site) {
            $score = RPHUB_Database::get_site_meta($site->id, 'security_score');
            return $score !== null && intval($score) < 50;
        });

        if (!empty($critical_sites)) {
            // Crear notificación global
            $this->create_security_notification(count($critical_sites));
        }
    }

    /**
     * Crea notificación de seguridad
     */
    private function create_security_notification($sites_count) {
        $message = sprintf(
            'Hay %d sitio(s) con problemas críticos de seguridad que requieren atención inmediata.',
            $sites_count
        );

        // Guardar notificación (implementar según sistema de notificaciones)
        update_option('rphub_security_alert', array(
            'message' => $message,
            'sites_count' => $sites_count,
            'created_at' => current_time('mysql'),
            'dismissed' => false
        ));
    }
}

// Inicializar el panel de seguridad
new RP_Hub_Security_Panel();
