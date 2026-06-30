<?php
/**
 * Delta Reporter — captura snapshots antes/después de actualizaciones
 * y genera reportes de impacto (métricas SA, rendimiento, plugins) para
 * mostrar a clientes el valor del servicio de mantenimiento.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPHUB_Delta_Reporter {

    private static $instance = null;

    const MAX_SNAPSHOTS     = 10;   // por sitio — borra los más antiguos
    const SNAPSHOT_META_KEY = 'delta_snapshots';
    const MONTHLY_REPORT_TTL = 86400; // 24h cache para reportes mensuales

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_rphub_delta_capture_snapshot', [$this, 'ajax_capture_snapshot']);
        add_action('wp_ajax_rphub_delta_get_report',       [$this, 'ajax_get_report']);
        add_action('wp_ajax_rphub_delta_get_monthly',      [$this, 'ajax_get_monthly']);

        // Engancha la generación de datos delta en el reporte comprehensive
        add_filter('rphub_collect_report_data', [$this, 'inject_delta_section'], 10, 3);
    }

    // -------------------------------------------------------------------------
    // Captura de snapshots
    // -------------------------------------------------------------------------

    /**
     * Captura el estado actual del sitio y lo guarda como snapshot.
     *
     * @param int    $site_id
     * @param string $context  e.g. 'pre_update', 'post_update', 'manual'
     * @return array|WP_Error  snapshot guardado
     */
    public function capture_snapshot($site_id, $context = 'manual') {
        $site = RPHUB_Database::get_site($site_id);
        if (!$site || empty($site->url) || empty($site->token)) {
            return new WP_Error('no_site', 'Sitio no encontrado o sin token');
        }

        $snapshot = [
            'context'    => $context,
            'captured_at'=> current_time('mysql'),
            'timestamp'  => time(),
            'sa_summary' => $this->fetch_sa_summary($site),
            'metrics'    => $this->fetch_metrics($site),
            'risk_assessments' => RPHUB_Database::get_site_meta($site_id, 'update_risk_assessments') ?: [],
        ];

        $this->store_snapshot($site_id, $snapshot);

        return $snapshot;
    }

    // -------------------------------------------------------------------------
    // Generación de deltas
    // -------------------------------------------------------------------------

    /**
     * Compara dos snapshots (pre/post) y devuelve diferencias estructuradas.
     *
     * @param int    $site_id
     * @param string $context_before  e.g. 'pre_update'
     * @param string $context_after   e.g. 'post_update'
     * @return array|WP_Error
     */
    public function generate_delta($site_id, $context_before = 'pre_update', $context_after = 'post_update') {
        $snapshots = $this->get_snapshots($site_id);

        $before = $this->find_last_snapshot($snapshots, $context_before);
        $after  = $this->find_last_snapshot($snapshots, $context_after);

        if (!$before || !$after) {
            return new WP_Error('missing_snapshot', "No se encontraron snapshots {$context_before}/{$context_after} para el sitio {$site_id}");
        }

        return $this->compute_delta($before, $after, $site_id);
    }

    /**
     * Genera el resumen de datos delta para el reporte mensual.
     */
    public function get_monthly_summary($site_id) {
        $cache_key = 'rphub_delta_monthly_' . $site_id;
        $cached    = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $site = RPHUB_Database::get_site($site_id);
        if (!$site) {
            return [];
        }

        $update_history = RPHUB_Database::get_site_meta($site_id, 'update_history') ?: [];
        $snapshots      = $this->get_snapshots($site_id);
        $risk_data      = RPHUB_Database::get_site_meta($site_id, 'update_risk_assessments') ?: [];

        $month_start = strtotime('first day of this month midnight');

        $monthly_updates = array_filter($update_history, function ($entry) use ($month_start) {
            return strtotime($entry['timestamp'] ?? '') >= $month_start
                && in_array($entry['event_type'], ['update_completed', 'update_failed'], true);
        });

        // Pares de snapshots pre/post en este mes
        $deltas = [];
        $pre_snapshots  = array_values(array_filter($snapshots, fn($s) => $s['context'] === 'pre_update' && $s['timestamp'] >= $month_start));
        $post_snapshots = array_values(array_filter($snapshots, fn($s) => $s['context'] === 'post_update' && $s['timestamp'] >= $month_start));

        foreach ($post_snapshots as $post) {
            // Busca el pre_update más reciente anterior al post_update
            $pre = null;
            foreach (array_reverse($pre_snapshots) as $p) {
                if ($p['timestamp'] < $post['timestamp']) {
                    $pre = $p;
                    break;
                }
            }
            if ($pre) {
                $deltas[] = $this->compute_delta($pre, $post, $site_id);
            }
        }

        // Puntuaciones de riesgo promedio
        $avg_risk = 0.0;
        $risk_count = 0;
        foreach ($risk_data as $assessment) {
            if (isset($assessment['risk_score'])) {
                $avg_risk += $assessment['risk_score'];
                $risk_count++;
            }
        }

        $summary = [
            'period'           => date('Y-m', $month_start),
            'updates_total'    => count($monthly_updates),
            'updates_ok'       => count(array_filter($monthly_updates, fn($e) => $e['event_type'] === 'update_completed')),
            'updates_failed'   => count(array_filter($monthly_updates, fn($e) => $e['event_type'] === 'update_failed')),
            'avg_risk_score'   => $risk_count > 0 ? round($avg_risk / $risk_count, 2) : null,
            'deltas'           => $deltas,
            'sa_improvement'   => $this->compute_sa_trend($snapshots, $month_start),
            'site_name'        => $site->name,
            'site_url'         => $site->url,
        ];

        set_transient($cache_key, $summary, self::MONTHLY_REPORT_TTL);

        return $summary;
    }

    // -------------------------------------------------------------------------
    // Integración con RphubReportGenerator
    // -------------------------------------------------------------------------

    public function inject_delta_section($report_data, $site_id, $report_type) {
        if ($report_type !== 'comprehensive' && $report_type !== 'updates') {
            return $report_data;
        }

        $report_data['delta'] = $this->get_monthly_summary($site_id);

        return $report_data;
    }

    // -------------------------------------------------------------------------
    // Helpers internos
    // -------------------------------------------------------------------------

    private function fetch_sa_summary($site) {
        $response = wp_remote_get(
            trailingslashit($site->url) . 'wp-json/replanta/v1/sa/summary',
            [
                'timeout' => 20,
                'headers' => ['Authorization' => 'Bearer ' . $site->token],
            ]
        );

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['data'] ?? $body ?? null;
    }

    private function fetch_metrics($site) {
        $response = wp_remote_get(
            trailingslashit($site->url) . 'wp-json/replanta/v1/metrics',
            [
                'timeout' => 20,
                'headers' => ['Authorization' => 'Bearer ' . $site->token],
            ]
        );

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['data'] ?? $body ?? null;
    }

    private function store_snapshot($site_id, $snapshot) {
        $snapshots   = $this->get_snapshots($site_id);
        $snapshots[] = $snapshot;

        // Ordenar por timestamp desc y mantener solo MAX_SNAPSHOTS
        usort($snapshots, fn($a, $b) => ($b['timestamp'] ?? 0) - ($a['timestamp'] ?? 0));
        $snapshots = array_slice($snapshots, 0, self::MAX_SNAPSHOTS);

        RPHUB_Database::update_site_meta($site_id, self::SNAPSHOT_META_KEY, $snapshots);
    }

    private function get_snapshots($site_id) {
        $data = RPHUB_Database::get_site_meta($site_id, self::SNAPSHOT_META_KEY);
        return is_array($data) ? $data : [];
    }

    private function find_last_snapshot($snapshots, $context) {
        foreach ($snapshots as $s) {
            if (($s['context'] ?? '') === $context) {
                return $s;
            }
        }
        return null;
    }

    private function compute_delta($before, $after, $site_id) {
        $delta = [
            'before_at' => $before['captured_at'] ?? null,
            'after_at'  => $after['captured_at']  ?? null,
        ];

        // SA score delta
        $sa_before = $before['sa_summary']['score'] ?? null;
        $sa_after  = $after['sa_summary']['score']  ?? null;

        if ($sa_before !== null && $sa_after !== null) {
            $delta['sa_score'] = [
                'before' => (float) $sa_before,
                'after'  => (float) $sa_after,
                'change' => round((float) $sa_after - (float) $sa_before, 1),
            ];
        }

        // Issues delta (from SA summary)
        $issues_before = $before['sa_summary']['issues_total'] ?? null;
        $issues_after  = $after['sa_summary']['issues_total']  ?? null;

        if ($issues_before !== null && $issues_after !== null) {
            $delta['issues'] = [
                'before' => (int) $issues_before,
                'after'  => (int) $issues_after,
                'change' => (int) $issues_after - (int) $issues_before,
            ];
        }

        // Plugin count delta
        $plugins_before = $before['metrics']['plugins_count'] ?? null;
        $plugins_after  = $after['metrics']['plugins_count']  ?? null;

        if ($plugins_before !== null && $plugins_after !== null) {
            $delta['plugins_count'] = [
                'before' => (int) $plugins_before,
                'after'  => (int) $plugins_after,
                'change' => (int) $plugins_after - (int) $plugins_before,
            ];
        }

        // Pending updates delta
        $pending_before = $before['metrics']['pending_updates'] ?? null;
        $pending_after  = $after['metrics']['pending_updates']  ?? null;

        if ($pending_before !== null && $pending_after !== null) {
            $delta['pending_updates'] = [
                'before' => (int) $pending_before,
                'after'  => (int) $pending_after,
                'change' => (int) $pending_after - (int) $pending_before,
            ];
        }

        // Risk assessments del ciclo de actualización
        $delta['risk_assessments'] = $before['risk_assessments'] ?? [];

        return $delta;
    }

    private function compute_sa_trend($snapshots, $since_timestamp) {
        $relevant = array_filter($snapshots, fn($s) => ($s['timestamp'] ?? 0) >= $since_timestamp);

        if (count($relevant) < 2) {
            return null;
        }

        usort($relevant, fn($a, $b) => ($a['timestamp'] ?? 0) - ($b['timestamp'] ?? 0));
        $relevant = array_values($relevant);

        $first_score = $relevant[0]['sa_summary']['score'] ?? null;
        $last_score  = end($relevant)['sa_summary']['score']  ?? null;

        if ($first_score === null || $last_score === null) {
            return null;
        }

        return [
            'first_score' => (float) $first_score,
            'last_score'  => (float) $last_score,
            'change'      => round((float) $last_score - (float) $first_score, 1),
        ];
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    public function ajax_capture_snapshot() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes', 403);
        }

        $site_id = intval($_POST['site_id'] ?? 0);
        $context = sanitize_key($_POST['context'] ?? 'manual');

        if (!$site_id) {
            wp_send_json_error('site_id requerido');
            return;
        }

        $result = $this->capture_snapshot($site_id, $context);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }

        wp_send_json_success($result);
    }

    public function ajax_get_report() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes', 403);
        }

        $site_id        = intval($_POST['site_id'] ?? 0);
        $context_before = sanitize_key($_POST['context_before'] ?? 'pre_update');
        $context_after  = sanitize_key($_POST['context_after']  ?? 'post_update');

        if (!$site_id) {
            wp_send_json_error('site_id requerido');
            return;
        }

        $delta = $this->generate_delta($site_id, $context_before, $context_after);

        if (is_wp_error($delta)) {
            wp_send_json_error($delta->get_error_message());
            return;
        }

        wp_send_json_success($delta);
    }

    public function ajax_get_monthly() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes', 403);
        }

        $site_id = intval($_POST['site_id'] ?? 0);

        if (!$site_id) {
            wp_send_json_error('site_id requerido');
            return;
        }

        wp_send_json_success($this->get_monthly_summary($site_id));
    }
}
