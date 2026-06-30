<?php
if (!defined('ABSPATH')) exit;

class RPHUB_CF_Audit {

    private string $zone_id;

    public function __construct(string $zone_id) {
        $this->zone_id = $zone_id;
    }

    public function run(): array {
        if (!class_exists('Dominios_Reseller_Cloudflare_Service')) {
            return ['score' => 0, 'checks' => [], 'error' => 'DR CF service unavailable'];
        }

        $cf  = Dominios_Reseller_Cloudflare_Service::get_instance();
        $cfg = $cf->get_zone_full_config($this->zone_id);

        if (is_wp_error($cfg)) {
            return ['score' => 0, 'checks' => [], 'error' => $cfg->get_error_message()];
        }

        $s      = $cfg['settings'] ?? [];
        $checks = [];

        $checks[] = $this->check_bool('always_use_https', 'HTTPS forzado', $s, 'on',  'critical', 'always_use_https');
        $checks[] = $this->check_ssl($s['ssl'] ?? 'off');
        $checks[] = $this->check_min_tls($s['min_tls_version'] ?? '1.0');
        $checks[] = $this->check_bool('automatic_https_rewrites', 'Reescritura HTTPS automática', $s, 'on', 'warning', 'automatic_https_rewrites');
        $checks[] = $this->check_bool('brotli', 'Compresión Brotli', $s, 'on', 'warning', 'brotli');
        $checks[] = $this->check_security_level($s['security_level'] ?? 'low');
        $checks[] = $this->check_dev_mode($s['development_mode'] ?? 'on');
        $checks[] = $this->check_bool('early_hints', 'Early Hints', $s, 'on', 'info', null);
        $checks[] = $this->check_bool('http3', 'HTTP/3 (QUIC)', $s, 'on', 'info', null);
        $checks[] = $this->check_rocket_loader($s['rocket_loader'] ?? 'on');
        $checks[] = $this->check_hsts($s['security_headers'] ?? $s['hsts'] ?? null);

        $score = 100;
        foreach ($checks as $c) {
            if ($c['status'] === 'critical') $score -= 20;
            elseif ($c['status'] === 'warning') $score -= 5;
        }

        return ['score' => max(0, $score), 'checks' => $checks];
    }

    private function check_bool(string $id, string $label, array $s, string $expected, string $severity, ?string $fix_id): array {
        $current = $s[$id] ?? null;
        $ok      = $current === $expected;
        return [
            'id'       => $id,
            'label'    => $label,
            'status'   => $ok ? 'good' : $severity,
            'current'  => $current ?? 'unknown',
            'expected' => $expected,
            'fix_id'   => $ok ? null : $fix_id,
        ];
    }

    private function check_ssl(string $current): array {
        $ok = in_array($current, ['full', 'strict'], true);
        return [
            'id'       => 'ssl',
            'label'    => 'Modo SSL',
            'status'   => $ok ? 'good' : ($current === 'flexible' ? 'warning' : 'critical'),
            'current'  => $current,
            'expected' => 'full',
            'fix_id'   => $ok ? null : 'ssl_full',
        ];
    }

    private function check_min_tls(string $current): array {
        $ok = in_array($current, ['1.2', '1.3'], true);
        return [
            'id'       => 'min_tls_version',
            'label'    => 'TLS mínimo',
            'status'   => $ok ? 'good' : 'critical',
            'current'  => $current,
            'expected' => '1.2',
            'fix_id'   => $ok ? null : 'min_tls_12',
        ];
    }

    private function check_security_level(string $current): array {
        $ok = in_array($current, ['medium', 'high', 'under_attack'], true);
        return [
            'id'       => 'security_level',
            'label'    => 'Nivel de seguridad',
            'status'   => $ok ? 'good' : 'warning',
            'current'  => $current,
            'expected' => 'medium',
            'fix_id'   => $ok ? null : 'security_medium',
        ];
    }

    private function check_dev_mode(string $current): array {
        $ok = $current === 'off';
        return [
            'id'       => 'development_mode',
            'label'    => 'Modo desarrollo',
            'status'   => $ok ? 'good' : 'warning',
            'current'  => $current,
            'expected' => 'off',
            'fix_id'   => $ok ? null : 'dev_mode_off',
        ];
    }

    private function check_rocket_loader($current): array {
        $ok = $current === 'off';
        return [
            'id'       => 'rocket_loader',
            'label'    => 'Rocket Loader',
            'status'   => $ok ? 'good' : 'info',
            'current'  => $current ?? 'unknown',
            'expected' => 'off',
            'fix_id'   => $ok ? null : 'rocket_loader_off',
        ];
    }

    private function check_hsts($value): array {
        $enabled = !empty($value) && ($value === 'on' || (is_array($value) && !empty($value['enabled'])));
        return [
            'id'       => 'hsts',
            'label'    => 'HSTS',
            'status'   => $enabled ? 'good' : 'info',
            'current'  => $enabled ? 'enabled' : 'disabled',
            'expected' => 'enabled',
            'fix_id'   => $enabled ? null : 'hsts',
        ];
    }
}
