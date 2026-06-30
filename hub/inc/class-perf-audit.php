<?php
if (!defined('ABSPATH')) exit;

class RPHUB_Perf_Audit {

    private string $site_url;
    private string $api_key;

    public function __construct(string $site_url, string $api_key) {
        $this->site_url = rtrim($site_url, '/');
        $this->api_key  = $api_key;
    }

    public function run(): array {
        if (empty($this->api_key)) {
            return ['score' => 0, 'checks' => [], 'error' => 'PSI API key not configured'];
        }

        $endpoint = add_query_arg([
            'url'      => $this->site_url,
            'strategy' => 'mobile',
            'key'      => $this->api_key,
            'category' => 'performance',
        ], 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed');

        $response = wp_remote_get($endpoint, ['timeout' => 60]);
        if (is_wp_error($response)) {
            return ['score' => 0, 'checks' => [], 'error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return ['score' => 0, 'checks' => [], 'error' => "PSI API returned HTTP {$code}"];
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data)) {
            return ['score' => 0, 'checks' => [], 'error' => 'PSI response empty'];
        }

        $lh    = $data['lighthouseResult']   ?? [];
        $crux  = $data['loadingExperience']  ?? [];

        $score = (int) round(($lh['categories']['performance']['score'] ?? 0) * 100);

        $audits = $lh['audits'] ?? [];
        $lcp    = $this->metric($audits, 'largest-contentful-paint');
        $cls    = $this->metric($audits, 'cumulative-layout-shift');
        $fcp    = $this->metric($audits, 'first-contentful-paint');
        $tbt    = $this->metric($audits, 'total-blocking-time');
        $ttfb   = $this->metric($audits, 'server-response-time');

        $crux_category = $crux['overall_category'] ?? 'NONE';

        $checks = [];
        $checks[] = $this->lh_check('lcp', 'LCP', $lcp, 2500, 4000, 'ms');
        $checks[] = $this->lh_check('cls', 'CLS', $cls, 0.1, 0.25, '');
        $checks[] = $this->lh_check('fcp', 'FCP', $fcp, 1800, 3000, 'ms');
        $checks[] = $this->lh_check('tbt', 'TBT', $tbt, 200, 600, 'ms');
        $checks[] = $this->lh_check('ttfb', 'TTFB', $ttfb, 800, 1800, 'ms');

        return [
            'score'         => $score,
            'lcp'           => $lcp,
            'cls'           => $cls,
            'fcp'           => $fcp,
            'tbt'           => $tbt,
            'ttfb'          => $ttfb,
            'crux_category' => $crux_category,
            'checks'        => $checks,
        ];
    }

    private function metric(array $audits, string $id): float {
        $val = $audits[$id]['numericValue'] ?? null;
        return $val !== null ? round((float) $val, 2) : 0.0;
    }

    private function lh_check(string $id, string $label, float $value, float $good, float $poor, string $unit): array {
        if ($value <= 0) {
            $status = 'info';
        } elseif ($value <= $good) {
            $status = 'good';
        } elseif ($value <= $poor) {
            $status = 'warning';
        } else {
            $status = 'critical';
        }
        return [
            'id'      => $id,
            'label'   => $label,
            'status'  => $status,
            'value'   => $value,
            'unit'    => $unit,
            'good_threshold' => $good,
            'poor_threshold' => $poor,
        ];
    }
}
