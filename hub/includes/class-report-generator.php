<?php
/**
 * Sistema de Reportes Automatizados para WordPress Hub
 * Genera reportes personalizados con datos de múltiples fuentes
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RphubReportGenerator {
    
    private $report_types;
    private $data_sources;
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        $this->init_report_types();
        $this->init_data_sources();
    }
    
    public function init() {
        // AJAX handlers
        add_action('wp_ajax_rphub_generate_report', array($this, 'ajax_generate_report'));
        add_action('wp_ajax_rphub_download_report', array($this, 'ajax_download_report'));
        add_action('wp_ajax_rphub_email_report', array($this, 'ajax_email_report'));
        add_action('wp_ajax_rphub_delete_report', array($this, 'ajax_delete_report'));
        
        // Public AJAX for Care integration
        add_action('wp_ajax_nopriv_rphub_get_client_reports', array($this, 'ajax_get_client_reports'));
        add_action('wp_ajax_rphub_get_client_reports', array($this, 'ajax_get_client_reports'));
        add_action('wp_ajax_nopriv_rphub_view_report_modal', array($this, 'ajax_view_report_modal'));
        add_action('wp_ajax_rphub_view_report_modal', array($this, 'ajax_view_report_modal'));
        
        // Cron jobs
        add_action('rphub_generate_scheduled_reports', array($this, 'process_scheduled_reports'));
        add_action('rphub_generate_scheduled_reports', array($this, 'send_monthly_client_reports'));

        // Task queue (Action Scheduler)
        RPHUB_Scheduler::schedule('rphub_generate_scheduled_reports', 'daily');
        
        // @removed v1.5.1 — Duplicate admin menu removed (already registered by inc/admin-reports.php)
    }
    
    /**
     * Inicializar tipos de reportes disponibles
     */
    private function init_report_types() {
        $this->report_types = array(
            'performance' => array(
                'name' => 'Reporte de Rendimiento',
                'description' => 'Análisis completo de PageSpeed, Core Web Vitals y optimización',
                'sections' => array('pagespeed', 'core_web_vitals', 'optimization_recommendations'),
                'frequency' => array('weekly', 'monthly'),
                'icon' => 'performance'
            ),
            'security' => array(
                'name' => 'Reporte de Seguridad',
                'description' => 'Estado de seguridad, vulnerabilidades y recomendaciones',
                'sections' => array('security_score', 'vulnerabilities', 'ssl_status', 'firewall_stats'),
                'frequency' => array('weekly', 'monthly'),
                'icon' => 'shield-alt'
            ),
            'uptime' => array(
                'name' => 'Reporte de Disponibilidad',
                'description' => 'Estadísticas de uptime, incidentes y tiempo de respuesta',
                'sections' => array('uptime_stats', 'incidents', 'response_times'),
                'frequency' => array('weekly', 'monthly'),
                'icon' => 'chart-line'
            ),
            'updates' => array(
                'name' => 'Reporte de Actualizaciones',
                'description' => 'Historial de actualizaciones, WordPress Core, plugins y themes',
                'sections' => array('update_history', 'pending_updates', 'rollback_history'),
                'frequency' => array('weekly', 'monthly'),
                'icon' => 'sync-alt'
            ),
            'backups' => array(
                'name' => 'Reporte de Backups',
                'description' => 'Estado de backups, frecuencia y verificaciones de integridad',
                'sections' => array('backup_status', 'backup_history', 'storage_usage'),
                'frequency' => array('weekly', 'monthly'),
                'icon' => 'archive'
            ),
            'comprehensive' => array(
                'name' => 'Reporte Integral',
                'description' => 'Reporte completo con todos los aspectos del sitio',
                'sections' => array('overview', 'ga4', 'search_console', 'performance', 'security', 'uptime', 'updates', 'backups', 'sa_audit', 'ecological'),
                'frequency' => array('monthly', 'quarterly'),
                'icon' => 'clipboard-check'
            ),
            'custom' => array(
                'name' => 'Reporte Personalizado',
                'description' => 'Reporte con secciones personalizables según necesidades',
                'sections' => array(), // Se define por el usuario
                'frequency' => array('weekly', 'monthly', 'quarterly'),
                'icon' => 'cogs'
            )
        );
    }
    
    /**
     * Inicializar fuentes de datos.
     * Reutiliza la instancia global de RphubUptimeMonitoring para evitar registrar
     * hooks duplicados. Las integraciones opcionales se instancian solo si existen.
     */
    private function init_data_sources() {
        $this->data_sources = array(
            'pagespeed'  => class_exists('RP_Hub_PageSpeed_Integration')   ? new RP_Hub_PageSpeed_Integration()   : null,
            'security'   => class_exists('RP_Hub_Security_Panel')          ? new RP_Hub_Security_Panel()          : null,
            'uptime'     => $GLOBALS['rphub_uptime_monitoring']            ?? null,
            'updates'    => class_exists('RphubSmartUpdates')              ? new RphubSmartUpdates()              : null,
            'backups'    => class_exists('RP_Hub_Backuply_Integration')    ? new RP_Hub_Backuply_Integration()    : null,
            'cloudflare' => class_exists('RP_Hub_Cloudflare_Integration')  ? new RP_Hub_Cloudflare_Integration()  : null,
            'wptoolkit'  => class_exists('RP_Hub_WPToolkit_Integration')   ? new RP_Hub_WPToolkit_Integration()   : null,
            'analytics'  => $GLOBALS['rphub_analytics']                         ?? null,
        );
    }
    
    /**
     * Generar reporte
     */
    public function generate_report($site_id, $report_type, $config = array()) {
        $site = RPHUB_Database::get_site($site_id);
        $site_title = $site ? $site->name : '';
        $site_url = $site ? $site->url : '';
        
        // Configuración por defecto
        $default_config = array(
            'period' => '30d',
            'format' => 'html',
            'include_charts' => true,
            'include_recommendations' => true,
            'branding' => true
        );
        
        $config = wp_parse_args($config, $default_config);
        
        // Generar datos del reporte
        $report_data = $this->collect_report_data($site_id, $report_type, $config);
        
        // Generar HTML del reporte
        $report_html = $this->generate_report_html($site_id, $report_type, $report_data, $config);
        
        // Guardar reporte
        $report_id = $this->save_report($site_id, $report_type, $report_html, $report_data, $config);
        
        return array(
            'success' => true,
            'report_id' => $report_id,
            'report_html' => $report_html,
            'report_data' => $report_data,
            'generated_at' => current_time('mysql')
        );
    }
    
    /**
     * Recopilar datos para el reporte
     */
    private function collect_report_data($site_id, $report_type, $config) {
        $data = array(
            'site_info' => $this->get_site_info($site_id),
            'period' => $config['period'],
            'generated_at' => current_time('mysql')
        );
        
        $report_config = $this->report_types[$report_type];
        $sections = $report_config['sections'];
        
        // Recopilar datos según las secciones del reporte
        foreach ($sections as $section) {
            switch ($section) {
                case 'overview':
                    $data['overview'] = $this->get_overview_data($site_id, $config['period']);
                    break;

                case 'ga4':
                    $analytics = $this->data_sources['analytics'];
                    $data['ga4'] = $analytics ? ($analytics->get_site_analytics($site_id)['ga4'] ?? null) : null;
                    break;

                case 'search_console':
                    $analytics = $this->data_sources['analytics'];
                    $data['search_console'] = $analytics ? ($analytics->get_site_analytics($site_id)['search_console'] ?? null) : null;
                    break;

                case 'pagespeed':
                case 'performance':
                    $data['performance'] = $this->get_performance_data($site_id, $config['period']);
                    break;
                    
                case 'core_web_vitals':
                    $data['core_web_vitals'] = $this->get_core_web_vitals_data($site_id, $config['period']);
                    break;
                    
                case 'security':
                case 'security_score':
                    $data['security'] = $this->get_security_data($site_id, $config['period']);
                    break;
                    
                case 'vulnerabilities':
                    $data['vulnerabilities'] = $this->get_vulnerabilities_data($site_id);
                    break;
                    
                case 'uptime':
                case 'uptime_stats':
                    $data['uptime'] = $this->get_uptime_data($site_id, $config['period']);
                    break;
                    
                case 'incidents':
                    $data['incidents'] = $this->get_incidents_data($site_id, $config['period']);
                    break;
                    
                case 'updates':
                case 'update_history':
                    $data['updates'] = $this->get_updates_data($site_id, $config['period']);
                    break;
                    
                case 'backups':
                case 'backup_status':
                    $data['backups'] = $this->get_backups_data($site_id, $config['period']);
                    break;
                    
                case 'optimization_recommendations':
                    $data['recommendations'] = $this->get_optimization_recommendations($site_id);
                    break;

                case 'sa_audit':
                    $data['sa_audit']  = $this->get_sa_data($site_id);
                    $data['sa_history']= $this->get_sa_score_history($site_id);
                    break;

                case 'ecological':
                    $data['ecological'] = $this->get_ecological_data($site_id);
                    break;
            }
        }
        
        return $data;
    }
    
    /**
     * Obtener información básica del sitio
     */
    private function get_site_info($site_id) {
        $site = RPHUB_Database::get_site($site_id);
        return array(
            'id'          => $site_id,
            'title'       => $site ? $site->name : '',
            'url'         => $site ? $site->url : '',
            'plan'        => $site ? ($site->plan ?? '') : '',
            'status'      => $site ? ($site->status ?? '') : '',
            'wp_version'  => RPHUB_Database::get_site_meta($site_id, 'wp_version'),
            'php_version' => RPHUB_Database::get_site_meta($site_id, 'php_version'),
        );
    }
    
    /**
     * Obtener datos de rendimiento
     */
    private function get_performance_data($site_id, $period) {
        $pagespeed = $this->data_sources['pagespeed'];
        if (!$pagespeed) { return null; }

        $latest_results = $pagespeed->get_latest_results($site_id);
        $history = $pagespeed->get_performance_history($site_id, $this->period_to_days($period));
        
        return array(
            'latest' => $latest_results,
            'history' => $history,
            'average_mobile_score' => $this->calculate_average_score($history, 'mobile_score'),
            'average_desktop_score' => $this->calculate_average_score($history, 'desktop_score'),
            'trend' => $this->calculate_performance_trend($history)
        );
    }
    
    /**
     * Obtener datos de Core Web Vitals
     */
    private function get_core_web_vitals_data($site_id, $period) {
        $pagespeed = $this->data_sources['pagespeed'];
        if (!$pagespeed) return null;
        $latest_results = $pagespeed->get_latest_results($site_id);
        
        if (!$latest_results || !isset($latest_results['core_web_vitals'])) {
            return null;
        }
        
        $cwv = $latest_results['core_web_vitals'];
        
        return array(
            'lcp' => $cwv['lcp'] ?? null,
            'fid' => $cwv['fid'] ?? null,
            'cls' => $cwv['cls'] ?? null,
            'fcp' => $cwv['fcp'] ?? null,
            'ttfb' => $cwv['ttfb'] ?? null,
            'assessment' => $this->assess_core_web_vitals($cwv)
        );
    }
    
    /**
     * Obtener datos de seguridad
     */
    private function get_security_data($site_id, $period) {
        $security = $this->data_sources['security'];
        if (!$security) { return null; }

        return array(
            'overall_score' => $security->calculate_security_score($site_id),
            'ssl_status' => $security->check_ssl_status($site_id),
            'vulnerabilities' => $security->get_vulnerabilities($site_id),
            'firewall_stats' => $security->get_firewall_statistics($site_id),
            'security_plugins' => $security->get_security_plugins_status($site_id),
            'file_integrity' => $security->check_file_integrity($site_id)
        );
    }
    
    /**
     * Obtener datos de uptime
     */
    private function get_uptime_data($site_id, $period) {
        $uptime = $this->data_sources['uptime'];
        if (!$uptime) { return null; }

        return $uptime->get_uptime_statistics($site_id, $period);
    }
    
    /**
     * Obtener datos de actualizaciones
     */
    private function get_updates_data($site_id, $period) {
        $updates = $this->data_sources['updates'];
        if (!$updates) { return null; }

        return array(
            'statistics' => $updates->get_update_statistics($site_id),
            'pending_updates' => RPHUB_Database::get_site_meta($site_id, 'pending_updates'),
            'auto_config' => RPHUB_Database::get_site_meta($site_id, 'auto_update_config')
        );
    }
    
    /**
     * Obtener datos de backups
     */
    private function get_backups_data($site_id, $period) {
        $backups = $this->data_sources['backups'];
        if (!$backups) { return null; }

        return array(
            'recent_backups' => $backups->get_recent_backups($site_id, 10),
            'backup_schedule' => $backups->get_backup_schedule($site_id),
            'storage_usage' => $backups->get_storage_usage($site_id),
            'success_rate' => $backups->get_backup_success_rate($site_id, $this->period_to_days($period))
        );
    }

    /**
     * Get SA (replanta-site-audit) scores from site_meta.
     */
    private function get_sa_data($site_id) {
        return [
            'global_score'    => (int) RPHUB_Database::get_site_meta($site_id, 'sa_global_score'),
            'cf_score'        => (int) RPHUB_Database::get_site_meta($site_id, 'cf_score'),
            'seo_score'       => (int) RPHUB_Database::get_site_meta($site_id, 'seo_score'),
            'perf_score'      => (int) RPHUB_Database::get_site_meta($site_id, 'perf_score'),
            'critical_issues' => (int) RPHUB_Database::get_site_meta($site_id, 'sa_critical_issues'),
            'warning_issues'  => (int) RPHUB_Database::get_site_meta($site_id, 'sa_warning_issues'),
            'last_audit_at'   => RPHUB_Database::get_site_meta($site_id, 'sa_last_audit'),
        ];
    }

    /**
     * Get historical SA global_scores from the last N completed reports.
     * Used to render the sparkline trend in the fallback HTML.
     */
    private function get_sa_score_history($site_id, int $limit = 6): array {
        global $wpdb;
        $table  = $wpdb->prefix . 'rphub_reports';
        $rows   = $wpdb->get_results($wpdb->prepare(
            "SELECT report_data, generated_at
             FROM $table
             WHERE site_id = %d AND status = 'completed'
             ORDER BY generated_at DESC
             LIMIT %d",
            $site_id, $limit
        ), ARRAY_A) ?: [];

        $history = [];
        foreach (array_reverse($rows) as $row) {
            $d = json_decode($row['report_data'] ?? '', true);
            $score = (int)($d['sa_audit']['global_score'] ?? 0);
            if ($score > 0) {
                $history[] = ['date' => substr($row['generated_at'], 0, 10), 'score' => $score];
            }
        }
        return $history;
    }

    /**
     * Get ecological impact from dominios_reseller for the site's domain.
     */
    private function get_ecological_data($site_id): array {
        global $wpdb;
        $site = RPHUB_Database::get_site($site_id);
        if (!$site) return [];

        $host   = parse_url($site->url, PHP_URL_HOST) ?: '';
        $domain = strtolower(preg_replace('/^www\./i', '', $host));
        if (!$domain) return [];

        $dr_table = $wpdb->prefix . 'dominios_reseller';
        if (!$wpdb->get_var("SHOW TABLES LIKE '$dr_table'")) return [];

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT trees_planted, co2_evaded FROM $dr_table WHERE domain = %s OR primary_domain = %s LIMIT 1",
            $domain, $domain
        ), ARRAY_A);

        if (!$row) return [];

        return [
            'trees_planted' => (float)($row['trees_planted'] ?? 0),
            'co2_evaded'    => (float)($row['co2_evaded']    ?? 0),
        ];
    }

    /**
     * Generar HTML del reporte
     */
    private function generate_report_html($site_id, $report_type, $data, $config) {
        $template_path = $this->get_report_template_path($report_type);

        $vars = array(
            'site_info'    => $data['site_info'],
            'report_type'  => $report_type,
            'report_title' => $this->report_types[$report_type]['name'],
            'period'       => $config['period'],
            'generated_at' => $data['generated_at'],
            'data'         => $data,
            'config'       => $config,
            'branding'     => $config['branding'],
        );

        $html = $this->process_template($template_path, $vars);

        if ($config['include_charts']) {
            $html = $this->add_charts_to_report($html, $data);
        }

        return $html;
    }
    
    /**
     * Resolve report template file path; returns empty string when none exists.
     */
    private function get_report_template_path($report_type) {
        $base = plugin_dir_path(__FILE__) . '../templates/reports/';
        $specific = $base . "report-{$report_type}.php";
        if (file_exists($specific)) {
            return $specific;
        }
        $default = $base . 'report-default.php';
        return file_exists($default) ? $default : '';
    }

    /**
     * Render a report template file with the given variables.
     * Falls back to a minimal inline HTML when no template file is found.
     */
    private function process_template($template_path, $vars) {
        if (!empty($template_path) && file_exists($template_path)) {
            extract($vars); // phpcs:ignore WordPress.PHP.DontExtract
            ob_start();
            include $template_path;
            return ob_get_clean();
        }
        return $this->generate_fallback_html($vars);
    }

    /**
     * Minimal HTML report used when no template file is present.
     */
    private function generate_fallback_html($vars) {
        $site      = $vars['site_info']    ?? array();
        $title     = esc_html($vars['report_title'] ?? 'Reporte');
        $period    = esc_html($vars['period']       ?? '30d');
        $generated = esc_html($vars['generated_at'] ?? '');
        $data      = $vars['data']                  ?? array();

        $uptime = isset($data['uptime']['uptime_percentage'])
            ? round($data['uptime']['uptime_percentage'], 2) . '%'
            : 'N/D';

        $sessions  = $data['ga4']['total_sessions']  ?? $data['overview']['total_visitors'] ?? 'N/D';
        $clicks    = $data['search_console']['total_clicks'] ?? 'N/D';

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  body{font-family:Arial,sans-serif;color:#333;margin:0;padding:0;background:#f4f4f4}
  .wrap{max-width:680px;margin:0 auto;background:#fff;padding:32px;border-radius:6px}
  h1{color:#2ecc71;font-size:24px;border-bottom:2px solid #2ecc71;padding-bottom:8px}
  h2{color:#555;font-size:16px;margin-top:24px}
  .kpi{display:inline-block;background:#f9f9f9;border:1px solid #ddd;border-radius:4px;
       padding:12px 20px;margin:8px 4px;min-width:120px;text-align:center}
  .kpi strong{display:block;font-size:22px;color:#2ecc71}
  .footer{margin-top:32px;font-size:11px;color:#999;border-top:1px solid #eee;padding-top:12px}
</style>
</head>
<body>
<div class="wrap">
  <h1><?php echo $title; ?></h1>
  <p>
    <strong>Sitio:</strong> <?php echo esc_html($site['title'] ?? ''); ?> —
    <a href="<?php echo esc_url($site['url'] ?? ''); ?>"><?php echo esc_html($site['url'] ?? ''); ?></a><br>
    <strong>Período:</strong> <?php echo $period; ?> &nbsp;|&nbsp;
    <strong>Generado:</strong> <?php echo $generated; ?>
  </p>

  <h2>Resumen del Período</h2>
  <div>
    <div class="kpi"><strong><?php echo esc_html((string)$uptime); ?></strong>Disponibilidad</div>
    <div class="kpi"><strong><?php echo esc_html((string)$sessions); ?></strong>Sesiones GA4</div>
    <div class="kpi"><strong><?php echo esc_html((string)$clicks); ?></strong>Clics GSC</div>
  </div>

  <?php if (!empty($data['updates'])): ?>
  <h2>Actualizaciones Realizadas</h2>
  <pre style="background:#f4f4f4;padding:12px;font-size:12px"><?php echo esc_html(wp_json_encode($data['updates'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
  <?php endif; ?>

  <?php if (!empty($data['security'])): ?>
  <h2>Seguridad</h2>
  <p>Puntuación de seguridad: <strong><?php echo esc_html((string)($data['security']['overall_score'] ?? 'N/D')); ?></strong></p>
  <?php endif; ?>

  <?php if (!empty($data['sa_audit']) && $data['sa_audit']['global_score'] > 0): ?>
  <?php $sa = $data['sa_audit']; $history = $data['sa_history'] ?? []; ?>
  <h2>Auditoría del Sitio (SA)</h2>
  <div>
    <div class="kpi"><strong><?php echo esc_html($sa['global_score']); ?></strong>Score Global</div>
    <div class="kpi"><strong><?php echo esc_html($sa['cf_score']); ?></strong>Cloudflare</div>
    <div class="kpi"><strong><?php echo esc_html($sa['seo_score']); ?></strong>SEO</div>
    <div class="kpi"><strong><?php echo esc_html($sa['perf_score']); ?></strong>Rendimiento</div>
    <div class="kpi" style="<?php echo $sa['critical_issues'] > 0 ? 'border-color:#e74c3c;' : ''; ?>">
      <strong style="<?php echo $sa['critical_issues'] > 0 ? 'color:#e74c3c;' : ''; ?>"><?php echo esc_html($sa['critical_issues']); ?></strong>Críticos
    </div>
    <div class="kpi"><strong><?php echo esc_html($sa['warning_issues']); ?></strong>Avisos</div>
  </div>
  <?php if (!empty($history) && count($history) >= 2): ?>
  <p style="color:#666;font-size:12px;margin-top:8px;">Tendencia score SA</p>
  <?php
    $max = max(array_column($history, 'score')) ?: 100;
    $w = 280; $h = 48; $n = count($history);
    $pts = '';
    foreach ($history as $i => $pt) {
        $x = (int)($i / max($n - 1, 1) * ($w - 20)) + 10;
        $y = (int)($h - 4 - ($pt['score'] / $max) * ($h - 8));
        $pts .= "{$x},{$y} ";
    }
  ?>
  <svg width="<?php echo $w; ?>" height="<?php echo $h; ?>" style="background:#f9f9f9;border-radius:4px;">
    <polyline fill="none" stroke="#2ecc71" stroke-width="2" points="<?php echo esc_attr(trim($pts)); ?>"/>
    <?php foreach ($history as $i => $pt):
      $x = (int)($i / max($n - 1, 1) * ($w - 20)) + 10;
      $y = (int)($h - 4 - ($pt['score'] / $max) * ($h - 8));
    ?>
    <circle cx="<?php echo $x; ?>" cy="<?php echo $y; ?>" r="3" fill="#2ecc71"/>
    <text x="<?php echo $x; ?>" y="<?php echo $h; ?>" font-size="9" fill="#999" text-anchor="middle"><?php echo esc_html(substr($pt['date'], 5)); ?></text>
    <?php endforeach; ?>
  </svg>
  <?php endif; ?>
  <?php if ($sa['last_audit_at']): ?>
  <p style="font-size:11px;color:#999;margin-top:4px;">Última auditoría: <?php echo esc_html($sa['last_audit_at']); ?></p>
  <?php endif; ?>
  <?php endif; ?>

  <?php if (!empty($data['ecological']) && ($data['ecological']['trees_planted'] > 0 || $data['ecological']['co2_evaded'] > 0)): ?>
  <?php $eco = $data['ecological']; ?>
  <h2>🌱 Impacto Ecológico</h2>
  <div>
    <div class="kpi" style="border-color:#2ecc71;background:#f0fdf4;">
      <strong style="color:#16a34a;"><?php echo esc_html(number_format($eco['trees_planted'], 1)); ?></strong>Árboles plantados
    </div>
    <div class="kpi" style="border-color:#16a34a;background:#f0fdf4;">
      <strong style="color:#16a34a;"><?php echo esc_html(number_format($eco['co2_evaded'], 1)); ?> kg</strong>CO₂ evitado
    </div>
  </div>
  <p style="font-size:11px;color:#666;margin-top:4px;">Datos de Tree-Nation vía Replanta.</p>
  <?php endif; ?>

  <div class="footer">Este reporte ha sido generado automáticamente por Replanta Hub. &copy; <?php echo date('Y'); ?> Replanta.net</div>
</div>
</body>
</html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Guardar reporte en base de datos
     */
    private function save_report($site_id, $report_type, $html, $data, $config) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rphub_reports';
        
        $report_id = uniqid('rpt_');
        
        $wpdb->insert(
            $table_name,
            array(
                'report_id' => $report_id,
                'site_id' => $site_id,
                'report_type' => $report_type,
                'period' => $config['period'] ?? '30d',
                'report_html' => $html,
                'report_data' => json_encode($data),
                'config' => json_encode($config),
                'generated_at' => current_time('mysql'),
                'status' => 'completed'
            ),
            array('%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        return $report_id;
    }
    
    /**
     * Crear tabla de reportes. Llamado desde activate() del plugin principal.
     */
    public static function create_reports_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rphub_reports';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id int(11) NOT NULL AUTO_INCREMENT,
            report_id varchar(50) NOT NULL,
            site_id int(11) NOT NULL,
            report_type varchar(50) NOT NULL,
            report_html longtext,
            report_data longtext,
            config text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            generated_at datetime DEFAULT CURRENT_TIMESTAMP,
            sent_at datetime NULL,
            status varchar(20) DEFAULT 'pending',
            PRIMARY KEY (id),
            UNIQUE KEY report_id (report_id),
            KEY site_id (site_id),
            KEY report_type (report_type),
            KEY created_at (created_at),
            KEY generated_at (generated_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Procesar reportes programados
     */
    public function process_scheduled_reports() {
        $scheduled_reports = get_option('rphub_scheduled_reports', array());
        
        foreach ($scheduled_reports as $key => $schedule) {
            if ($this->should_generate_report($schedule)) {
                $result = $this->generate_report($schedule['site_id'], $schedule['report_type'], $schedule['config']);
                
                if ($result['success']) {
                    // Enviar reporte si está configurado
                    if ($schedule['email_delivery']) {
                        $this->send_report_email($schedule['site_id'], $result['report_id'], $schedule['recipients']);
                    }
                    
                    // Actualizar próxima generación
                    $scheduled_reports[$key]['last_generated'] = current_time('mysql');
                    $scheduled_reports[$key]['next_generation'] = $this->calculate_next_generation($schedule['frequency']);
                }
            }
        }
        
        update_option('rphub_scheduled_reports', $scheduled_reports);
    }
    
    /**
     * Determinar si debe generar reporte
     */
    private function should_generate_report($schedule) {
        if (!isset($schedule['next_generation'])) {
            return true; // Primera generación
        }
        
        return strtotime($schedule['next_generation']) <= time();
    }
    
    /**
     * Calcular próxima generación
     */
    private function calculate_next_generation($frequency) {
        switch ($frequency) {
            case 'daily':
                return date('Y-m-d H:i:s', strtotime('+1 day'));
            case 'weekly':
                return date('Y-m-d H:i:s', strtotime('+1 week'));
            case 'monthly':
                return date('Y-m-d H:i:s', strtotime('+1 month'));
            case 'quarterly':
                return date('Y-m-d H:i:s', strtotime('+3 months'));
            default:
                return date('Y-m-d H:i:s', strtotime('+1 week'));
        }
    }
    
    /**
     * AJAX: Generar reporte
     */
    public function ajax_generate_report() {
        check_ajax_referer('rphub_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        $site_id     = intval($_POST['site_id'] ?? 0);
        // Accept 'type' (sent by admin-reports.php) with fallback to 'report_type'
        $report_type = sanitize_text_field($_POST['report_type'] ?? $_POST['type'] ?? '');
        // Map generic types from the reports UI to valid generator types
        $type_map = [
            'site'        => 'comprehensive',
            'summary'     => 'comprehensive',
            'security'    => 'security',
            'performance' => 'performance',
        ];
        if (isset($type_map[$report_type])) {
            $report_type = $type_map[$report_type];
        }
        if (empty($report_type) || !isset($this->report_types[$report_type])) {
            $report_type = 'comprehensive';
        }
        $config      = $this->sanitize_report_config($_POST['config'] ?? array());

        $result = $this->generate_report($site_id, $report_type, $config);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => 'Reporte generado exitosamente',
                'report_id' => $result['report_id'],
                'download_url' => admin_url('admin-ajax.php?action=rphub_download_report&report_id=' . $result['report_id'])
            ));
        } else {
            wp_send_json_error('Error al generar el reporte');
        }
    }
    
    /**
     * Buscar un reporte por id numérico de fila o por report_id (string).
     * La tabla tiene filas de distintas épocas: algunas con report_html,
     * otras con file_path en disco y otras solo con report_data/data JSON.
     */
    private function find_report($identifier) {
        global $wpdb;
        $table = $wpdb->prefix . 'rphub_reports';

        if (is_numeric($identifier)) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d", (int) $identifier
            ), ARRAY_A);
            if ($row) {
                return $row;
            }
        }

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE report_id = %s", (string) $identifier
        ), ARRAY_A);
    }

    /**
     * AJAX: Descargar reporte (GET desde la página de reportes).
     * Sirve report_html inline, file_path desde uploads o report_data como JSON.
     */
    public function ajax_download_report() {
        check_ajax_referer('rphub_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Permisos insuficientes', 403);
        }

        $identifier = sanitize_text_field(wp_unslash($_REQUEST['report_id'] ?? ''));
        $report = $identifier !== '' ? $this->find_report($identifier) : null;

        if (!$report) {
            wp_die('Reporte no encontrado', 404);
        }

        $filename_base = sanitize_file_name(
            'replanta-report-' . ($report['report_type'] ?: 'general') . '-' . ($report['report_id'] ?: $report['id'])
        );

        if (!empty($report['report_html'])) {
            header('Content-Type: text/html; charset=UTF-8');
            header('Content-Disposition: inline; filename="' . $filename_base . '.html"');
            echo $report['report_html'];
            exit;
        }

        if (!empty($report['file_path'])) {
            $path = $report['file_path'];
            // Las filas antiguas pueden guardar ruta relativa a uploads
            if (!file_exists($path)) {
                $uploads = wp_upload_dir();
                $path = trailingslashit($uploads['basedir']) . ltrim($report['file_path'], '/\\');
            }
            $real    = realpath($path);
            $uploads = wp_upload_dir();
            $base    = realpath($uploads['basedir']);
            if ($real && $base && strpos($real, $base) === 0 && is_readable($real)) {
                $ext   = strtolower(pathinfo($real, PATHINFO_EXTENSION));
                $types = array('pdf' => 'application/pdf', 'html' => 'text/html', 'csv' => 'text/csv', 'json' => 'application/json');
                header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
                header('Content-Disposition: attachment; filename="' . basename($real) . '"');
                header('Content-Length: ' . filesize($real));
                readfile($real);
                exit;
            }
        }

        $json = $report['report_data'] ?? $report['data'] ?? '';
        if (!empty($json)) {
            header('Content-Type: application/json; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename_base . '.json"');
            echo $json;
            exit;
        }

        wp_die('Este reporte no tiene contenido descargable', 404);
    }

    /**
     * AJAX: Enviar reporte por email.
     */
    public function ajax_email_report() {
        check_ajax_referer('rphub_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        $identifier = sanitize_text_field(wp_unslash($_POST['report_id'] ?? ''));
        $report = $identifier !== '' ? $this->find_report($identifier) : null;

        if (!$report) {
            wp_send_json_error('Reporte no encontrado');
        }

        $recipients = array_filter(array_map('sanitize_email', (array) ($_POST['recipients'] ?? array())), 'is_email');
        if (empty($recipients)) {
            $site = RPHUB_Database::get_site((int) $report['site_id']);
            if ($site && !empty($site->client_email) && is_email($site->client_email)) {
                $recipients = array($site->client_email);
            }
        }
        if (empty($recipients)) {
            wp_send_json_error('No hay destinatarios válidos');
        }

        $site      = RPHUB_Database::get_site((int) $report['site_id']);
        $site_name = $site ? $site->name : '';
        $type_name = $this->report_types[$report['report_type']]['name'] ?? $report['report_type'];
        $subject   = sprintf('[Replanta] %s — %s', $type_name, $site_name);

        $sent = false;
        if (!empty($report['report_html'])) {
            $headers = array('Content-Type: text/html; charset=UTF-8');
            foreach ($recipients as $email) {
                $sent = wp_mail($email, $subject, $report['report_html'], $headers) || $sent;
            }
        } elseif (!empty($report['file_path']) && file_exists($report['file_path'])) {
            $body = 'Adjuntamos el reporte solicitado de ' . $site_name . '.';
            foreach ($recipients as $email) {
                $sent = wp_mail($email, $subject, $body, array(), array($report['file_path'])) || $sent;
            }
        } else {
            wp_send_json_error('Este reporte no tiene contenido enviable');
        }

        if (!$sent) {
            wp_send_json_error('No se pudo enviar el email');
        }

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'rphub_reports',
            array('sent_at' => current_time('mysql'), 'status' => 'sent'),
            array('id' => (int) $report['id']),
            array('%s', '%s'),
            array('%d')
        );

        wp_send_json_success(array('message' => 'Reporte enviado a ' . implode(', ', $recipients)));
    }

    /**
     * AJAX: Eliminar reporte (fila + archivo en disco si existe).
     */
    public function ajax_delete_report() {
        check_ajax_referer('rphub_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        $identifier = sanitize_text_field(wp_unslash($_POST['report_id'] ?? ''));
        $report = $identifier !== '' ? $this->find_report($identifier) : null;

        if (!$report) {
            wp_send_json_error('Reporte no encontrado');
        }

        if (!empty($report['file_path'])) {
            $real    = realpath($report['file_path']);
            $uploads = wp_upload_dir();
            $base    = realpath($uploads['basedir']);
            if ($real && $base && strpos($real, $base) === 0) {
                @unlink($real);
            }
        }

        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . 'rphub_reports',
            array('id' => (int) $report['id']),
            array('%d')
        );

        wp_send_json_success(array('message' => 'Reporte eliminado'));
    }

    /**
     * AJAX: Obtener reportes del cliente (para Care)
     */
    public function ajax_get_client_reports() {
        $client_id = $this->verify_client_access();

        if (!$client_id) {
            wp_send_json_error(['message' => 'Token inválido o sitio no registrado en Replanta.']); return;
        }
        
        // Obtener sitios del cliente
        $client_sites = $this->get_client_sites($client_id);
        $reports = array();
        
        foreach ($client_sites as $site_id) {
            $site_reports = $this->get_site_reports($site_id, 10);
            foreach ($site_reports as $report) {
                $rtype = $report['report_type'];
                $reports[] = array(
                    'report_id'        => $report['report_id'],
                    'site_title'       => ($s = RPHUB_Database::get_site($site_id)) ? $s->name : '',
                    'report_type'      => $rtype,
                    'report_type_name' => $this->report_types[$rtype]['name'] ?? $rtype,
                    'generated_at'     => $report['generated_at'],
                    'can_download'     => true,
                );
            }
        }
        
        wp_send_json_success($reports);
    }
    
    /**
     * AJAX: Ver reporte en modal (para Care)
     */
    public function ajax_view_report_modal() {
        $report_id = sanitize_text_field($_POST['report_id'] ?? '');
        $client_id = $this->verify_client_access();

        if (!$client_id) {
            wp_send_json_error(['message' => 'Token inválido o sitio no registrado en Replanta.']); return;
        }
        
        $report = $this->get_report_by_id($report_id);
        
        if (!$report || !$this->client_can_access_report($client_id, $report['site_id'])) {
            wp_send_json_error('Reporte no encontrado o acceso denegado');
        }
        
        wp_send_json_success(array(
            'html' => $report['report_html'],
            'title' => $this->report_types[$report['report_type']]['name'],
            'generated_at' => $report['generated_at']
        ));
    }
    
    /**
     * Verificar acceso del cliente vía Bearer token contra rphub_sites.token.
     * Acepta el token en el header Authorization o en $_POST['client_token'].
     * Devuelve el site_id si el token es válido, false en caso contrario.
     */
    private function verify_client_access() {
        $token = '';
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];
            if (strncmp($auth, 'Bearer ', 7) === 0) {
                $token = substr($auth, 7);
            }
        }
        if (empty($token) && !empty($_POST['client_token'])) {
            $token = sanitize_text_field(wp_unslash($_POST['client_token']));
        }
        if (empty($token)) {
            return false;
        }
        global $wpdb;
        $site_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}rphub_sites WHERE token = %s AND status = 'active'",
            $token
        ));
        return $site_id ? (int) $site_id : false;
    }

    /**
     * El client_id devuelto por verify_client_access() ES el site_id.
     */
    private function get_client_sites($client_id) {
        return array($client_id);
    }
    
    /**
     * Sanitize report config coming from POST data.
     */
    private function sanitize_report_config($raw) {
        if (!is_array($raw)) {
            return array();
        }
        $allowed_periods  = array('7d', '30d', '90d');
        $allowed_formats  = array('html', 'pdf');
        return array(
            'period'                   => in_array($raw['period'] ?? '', $allowed_periods, true) ? $raw['period'] : '30d',
            'format'                   => in_array($raw['format'] ?? '', $allowed_formats, true) ? $raw['format'] : 'html',
            'include_charts'           => !empty($raw['include_charts']),
            'include_recommendations'  => !empty($raw['include_recommendations']),
            'branding'                 => !empty($raw['branding']),
        );
    }

    /**
     * Utilidades
     */
    private function period_to_days($period) {
        switch ($period) {
            case '7d': return 7;
            case '30d': return 30;
            case '90d': return 90;
            default: return 30;
        }
    }
    
    private function calculate_average_score($history, $field) {
        if (empty($history)) return 0;
        
        $scores = array_column($history, $field);
        return round(array_sum($scores) / count($scores));
    }
    
    private function calculate_performance_trend($history) {
        if (count($history) < 2) return 'stable';
        
        $recent = array_slice($history, 0, 5);
        $older = array_slice($history, -5);
        
        $recent_avg = $this->calculate_average_score($recent, 'mobile_score');
        $older_avg = $this->calculate_average_score($older, 'mobile_score');
        
        $diff = $recent_avg - $older_avg;
        
        if ($diff > 5) return 'improving';
        if ($diff < -5) return 'declining';
        return 'stable';
    }
    
    // Missing methods - stubs for functionality
    private function get_overview_data($site_id, $period) {
        $analytics = $this->data_sources['analytics'];
        if ($analytics) {
            $data = $analytics->get_site_analytics($site_id);
            $ga4  = $data['ga4']            ?? array();
            $sc   = $data['search_console'] ?? array();
            return array(
                'total_visitors'    => $ga4['total_users']      ?? 0,
                'total_sessions'    => $ga4['total_sessions']   ?? 0,
                'page_views'        => $ga4['total_pageviews']  ?? 0,
                'bounce_rate'       => $ga4['avg_bounce_rate']  ?? 0,
                'total_clicks'      => $sc['total_clicks']      ?? 0,
                'total_impressions' => $sc['total_impressions'] ?? 0,
                'top_queries'       => array_keys($sc['top_queries'] ?? array()),
                'last_sync'         => $ga4['collected_at']     ?? null,
            );
        }
        return array(
            'total_visitors'    => 0,
            'total_sessions'    => 0,
            'page_views'        => 0,
            'bounce_rate'       => 0,
            'total_clicks'      => 0,
            'total_impressions' => 0,
            'last_sync'         => null,
        );
    }
    
    private function get_vulnerabilities_data($site_id) {
        return array(
            'total_vulnerabilities' => 0,
            'high_risk' => 0,
            'medium_risk' => 0,
            'low_risk' => 0
        );
    }
    
    private function get_incidents_data($site_id, $period) {
        return array(
            'total_incidents' => 0,
            'resolved' => 0,
            'pending' => 0
        );
    }
    
    private function get_optimization_recommendations($site_id) {
        return array(
            'image_optimization' => 'Compress images to improve load times',
            'cache_optimization' => 'Enable browser caching',
            'database_optimization' => 'Clean up database tables'
        );
    }
    
    private function assess_core_web_vitals($cwv) {
        return array(
            'lcp_assessment' => $cwv['lcp'] < 2.5 ? 'good' : ($cwv['lcp'] < 4.0 ? 'needs_improvement' : 'poor'),
            'fid_assessment' => $cwv['fid'] < 100 ? 'good' : ($cwv['fid'] < 300 ? 'needs_improvement' : 'poor'),
            'cls_assessment' => $cwv['cls'] < 0.1 ? 'good' : ($cwv['cls'] < 0.25 ? 'needs_improvement' : 'poor')
        );
    }
    
    private function add_charts_to_report($html, $data) {
        // Add chart placeholders
        $charts_html = '<div class="charts-section">';
        $charts_html .= '<h3>Performance Charts</h3>';
        $charts_html .= '<div class="chart-placeholder">Charts will be rendered here</div>';
        $charts_html .= '</div>';
        
        return str_replace('</body>', $charts_html . '</body>', $html);
    }
    
    /**
     * Send a generated report to the given recipients.
     * When $recipients is empty, uses the site's client_email from rphub_sites.
     */
    private function send_report_email($site_id, $report_id, $recipients = array()) {
        global $wpdb;

        // Resolve recipients
        if (empty($recipients)) {
            $site = RPHUB_Database::get_site($site_id);
            if ($site && !empty($site->client_email) && is_email($site->client_email)) {
                $recipients = array($site->client_email);
            }
        }
        $recipients = array_filter((array) $recipients, 'is_email');
        if (empty($recipients)) {
            return;
        }

        // Load report HTML from DB
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT report_html, report_type, generated_at FROM {$wpdb->prefix}rphub_reports WHERE report_id = %s",
            $report_id
        ));
        if (!$row) {
            return;
        }

        $site      = RPHUB_Database::get_site($site_id);
        $site_name = $site ? $site->name : '';
        $type_name = $this->report_types[$row->report_type]['name'] ?? $row->report_type;
        $month     = date_i18n('F Y', strtotime($row->generated_at));

        $subject = sprintf('[Replanta] %s — %s (%s)', $type_name, $site_name, $month);
        $headers = array('Content-Type: text/html; charset=UTF-8');

        foreach ($recipients as $email) {
            wp_mail($email, $subject, $row->report_html, $headers);
        }

        // Mark as sent
        $wpdb->update(
            $wpdb->prefix . 'rphub_reports',
            array('sent_at' => current_time('mysql'), 'status' => 'sent'),
            array('report_id' => $report_id),
            array('%s', '%s'),
            array('%s')
        );
    }

    /**
     * Generate and email monthly comprehensive reports to every site that has a client_email.
     * Called once per day; fires only on the configured report day (default: 1st of month).
     */
    public function send_monthly_client_reports() {
        $day = (int) get_option('rphub_monthly_report_day', 1);
        if ((int) date('j') !== $day) {
            return;
        }

        global $wpdb;
        $sites = $wpdb->get_results(
            "SELECT id, client_email FROM {$wpdb->prefix}rphub_sites
             WHERE status = 'active' AND client_email != '' AND client_email IS NOT NULL"
        );

        foreach ($sites as $site) {
            if (!is_email($site->client_email)) {
                continue;
            }
            $result = $this->generate_report($site->id, 'comprehensive', array('period' => '30d'));
            if ($result['success']) {
                $this->send_report_email($site->id, $result['report_id'], array($site->client_email));
            }
        }
    }
    
    private function get_site_reports($site_id, $limit) {
        global $wpdb;
        $table = $wpdb->prefix . 'rphub_reports';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE site_id = %d ORDER BY generated_at DESC LIMIT %d",
            $site_id, $limit
        ), ARRAY_A);
    }

    private function get_report_by_id($report_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rphub_reports';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE report_id = %s",
            $report_id
        ), ARRAY_A);
    }
    
    private function client_can_access_report($client_id, $site_id) {
        return (int) $client_id === (int) $site_id;
    }
    
}

// Instantiated by replanta-hub.php — do not instantiate here.
