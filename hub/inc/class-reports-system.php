<?php
/**
 * Advanced Reporting System for Replanta Hub
 * 
 * Generates comprehensive reports combining all integrations:
 * WP Toolkit, Backuply, PageSpeed, Cloudflare
 */

class ReplantaHub_Reports_System {
    
    private $wptoolkit;
    private $backuply;
    private $pagespeed;
    private $cloudflare;
    
    public function __construct() {
        if (did_action('init')) {
            $this->init();
        } else {
            add_action('init', [$this, 'init']);
        }
    }
    
    public function init() {
        // Initialize integrations
        $this->wptoolkit = new ReplantaHub_WPToolkit_Integration();
        $this->backuply = new ReplantaHub_Backuply_Integration();
        $this->pagespeed = new ReplantaHub_PageSpeed_Integration();
        $this->cloudflare = new ReplantaHub_Cloudflare_Integration();
        
        // AJAX handlers
        add_action('wp_ajax_rphub_generate_comprehensive_report', [$this, 'ajax_generate_comprehensive_report']);
        add_action('wp_ajax_rphub_generate_security_report', [$this, 'ajax_generate_security_report']);
        add_action('wp_ajax_rphub_generate_performance_report', [$this, 'ajax_generate_performance_report']);
        add_action('wp_ajax_rphub_generate_maintenance_report', [$this, 'ajax_generate_maintenance_report']);
        add_action('wp_ajax_rphub_get_dashboard_summary', [$this, 'ajax_get_dashboard_summary']);
        add_action('wp_ajax_rphub_export_report', [$this, 'ajax_export_report']);
        
        // Scheduled reporting
        add_action('rphub_weekly_report_generation', [$this, 'scheduled_weekly_reports']);
        add_action('rphub_monthly_report_generation', [$this, 'scheduled_monthly_reports']);
        
        // Schedule events (specific times preserved via delay calculation)
        RPHUB_Scheduler::schedule('rphub_weekly_report_generation',  'weekly',     [], (int)(strtotime('next monday 8:00') - time()));
        RPHUB_Scheduler::schedule('rphub_monthly_report_generation', 'monthly',    [], (int)(strtotime('first day of next month 9:00') - time()));
    }
    
    /**
     * Generate comprehensive dashboard report
     */
    public function generate_comprehensive_report($site_id, $period = '7d') {
        global $wpdb;
        $table_sites = $wpdb->prefix . 'rphub_sites';
        
        $site = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_sites WHERE id = %d", $site_id), ARRAY_A);
        
        if (!$site) {
            return new WP_Error('site_not_found', 'Sitio no encontrado');
        }
        
        $domain = parse_url($site['url'], PHP_URL_HOST);
        $report_date = current_time('mysql');
        
        // Collect data from all integrations
        $report = [
            'site_info' => $this->get_site_basic_info($site),
            'report_period' => $period,
            'generated_at' => $report_date,
            'dr_info'     => $this->get_dr_section($site_id),
            'audit_scores' => $this->get_audit_section($site_id),
            'overall_health' => $this->calculate_overall_health($site_id, $domain),
            'security_status' => $this->get_security_status($site_id, $domain),
            'performance_metrics' => $this->get_performance_metrics($site_id, $domain),
            'backup_status' => $this->get_backup_status($site_id, $domain),
            'wordpress_health' => $this->get_wordpress_health($site_id, $domain),
            'cloudflare_analytics' => $this->get_cloudflare_summary($site_id, $domain),
            'recommendations' => $this->generate_recommendations($site_id, $domain),
            'alerts' => $this->get_active_alerts($site_id),
            'activity_summary' => $this->get_activity_summary($site_id, $period),
            'cost_analysis' => $this->get_cost_analysis($site_id, $period),
            'next_actions' => $this->get_next_actions($site_id, $domain)
        ];
        
        // Store report
        $this->store_report($site_id, 'comprehensive', $report);
        
        return $report;
    }
    
    /**
     * Generate security-focused report
     */
    public function generate_security_report($site_id) {
        global $wpdb;
        $table_sites = $wpdb->prefix . 'rphub_sites';
        
        $site = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_sites WHERE id = %d", $site_id), ARRAY_A);
        
        if (!$site) {
            return new WP_Error('site_not_found', 'Sitio no encontrado');
        }
        
        $domain = parse_url($site['url'], PHP_URL_HOST);
        
        // Vulnerability scan from WP Toolkit
        $vulnerability_scan = $this->wptoolkit->scan_vulnerabilities($domain);
        
        // Cloudflare security events
        $cf_security = $this->cloudflare->get_security_events($domain, 100);
        
        // SSL status
        $ssl_status = $this->cloudflare->get_ssl_status($domain);
        
        $report = [
            'site_info' => $this->get_site_basic_info($site),
            'generated_at' => current_time('mysql'),
            'security_score' => $this->calculate_security_score($vulnerability_scan, $cf_security, $ssl_status),
            'vulnerability_assessment' => $this->process_vulnerability_data($vulnerability_scan),
            'threat_analysis' => $this->process_threat_data($cf_security),
            'ssl_security' => $this->process_ssl_data($ssl_status),
            'security_recommendations' => $this->generate_security_recommendations($vulnerability_scan, $cf_security, $ssl_status),
            'compliance_status' => $this->check_security_compliance($site_id, $domain),
            'security_timeline' => $this->get_security_timeline($site_id, 30),
            'risk_assessment' => $this->assess_security_risk($vulnerability_scan, $cf_security)
        ];
        
        $this->store_report($site_id, 'security', $report);
        
        return $report;
    }
    
    /**
     * Generate performance report
     */
    public function generate_performance_report($site_id) {
        global $wpdb;
        $table_sites = $wpdb->prefix . 'rphub_sites';
        
        $site = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_sites WHERE id = %d", $site_id), ARRAY_A);
        
        if (!$site) {
            return new WP_Error('site_not_found', 'Sitio no encontrado');
        }
        
        $domain = parse_url($site['url'], PHP_URL_HOST);
        
        // PageSpeed analysis
        $pagespeed_mobile = $this->pagespeed->analyze_page($site['url'], 'mobile');
        $pagespeed_desktop = $this->pagespeed->analyze_page($site['url'], 'desktop');
        
        // Cloudflare performance data
        $cf_analytics = $this->cloudflare->get_analytics($domain, '7d');
        
        $report = [
            'site_info' => $this->get_site_basic_info($site),
            'generated_at' => current_time('mysql'),
            'performance_score' => $this->calculate_performance_score($pagespeed_mobile, $pagespeed_desktop, $cf_analytics),
            'pagespeed_analysis' => [
                'mobile' => $this->process_pagespeed_data($pagespeed_mobile),
                'desktop' => $this->process_pagespeed_data($pagespeed_desktop)
            ],
            'core_web_vitals' => $this->get_core_web_vitals_summary($pagespeed_mobile, $pagespeed_desktop),
            'cloudflare_performance' => $this->process_cf_performance_data($cf_analytics),
            'performance_trends' => $this->get_performance_trends($site_id, 30),
            'optimization_opportunities' => $this->get_optimization_opportunities($pagespeed_mobile, $pagespeed_desktop),
            'performance_recommendations' => $this->generate_performance_recommendations($pagespeed_mobile, $pagespeed_desktop, $cf_analytics),
            'competitive_analysis' => $this->get_competitive_performance_analysis($domain)
        ];
        
        $this->store_report($site_id, 'performance', $report);
        
        return $report;
    }
    
    /**
     * Generate maintenance report
     */
    public function generate_maintenance_report($site_id) {
        global $wpdb;
        $table_sites = $wpdb->prefix . 'rphub_sites';
        
        $site = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_sites WHERE id = %d", $site_id), ARRAY_A);
        
        if (!$site) {
            return new WP_Error('site_not_found', 'Sitio no encontrado');
        }
        
        $domain = parse_url($site['url'], PHP_URL_HOST);
        
        // WordPress info from WP Toolkit
        $wp_info = $this->wptoolkit->get_site_info($domain);
        
        // Update recommendations
        $update_recommendations = $this->wptoolkit->get_update_recommendations($domain);
        
        // Backup status
        $backup_stats = $this->backuply->get_backup_stats($domain);
        $backup_health = $this->backuply->get_backup_health($domain);
        
        $report = [
            'site_info' => $this->get_site_basic_info($site),
            'generated_at' => current_time('mysql'),
            'maintenance_score' => $this->calculate_maintenance_score($wp_info, $update_recommendations, $backup_health),
            'wordpress_status' => $this->process_wp_maintenance_data($wp_info),
            'update_status' => $this->process_update_data($update_recommendations),
            'backup_health' => $this->process_backup_health_data($backup_health),
            'maintenance_tasks' => $this->get_pending_maintenance_tasks($site_id, $domain),
            'automated_maintenance' => $this->get_automated_maintenance_status($site_id),
            'maintenance_schedule' => $this->get_maintenance_schedule($site_id),
            'maintenance_history' => $this->get_maintenance_history($site_id, 90),
            'recommendations' => $this->generate_maintenance_recommendations($wp_info, $update_recommendations, $backup_health)
        ];
        
        $this->store_report($site_id, 'maintenance', $report);
        
        return $report;
    }
    
    /**
     * Get dashboard summary for all sites
     */
    public function get_dashboard_summary() {
        global $wpdb;
        $table_sites = $wpdb->prefix . 'rphub_sites';
        
        $sites = $wpdb->get_results("SELECT * FROM $table_sites WHERE status = 'active'", ARRAY_A);
        
        $summary = [
            'total_sites' => count($sites),
            'sites_health' => [
                'excellent' => 0,
                'good' => 0,
                'warning' => 0,
                'critical' => 0
            ],
            'security_overview' => [
                'secure_sites' => 0,
                'vulnerabilities_found' => 0,
                'threats_blocked_today' => 0,
                'ssl_enabled' => 0
            ],
            'performance_overview' => [
                'average_performance_score' => 0,
                'sites_above_90' => 0,
                'sites_needs_improvement' => 0,
                'total_bandwidth_saved' => 0
            ],
            'maintenance_overview' => [
                'sites_up_to_date' => 0,
                'pending_updates' => 0,
                'backup_issues' => 0,
                'automated_maintenance' => 0
            ],
            'recent_activities' => $this->get_recent_dashboard_activities(),
            'alerts' => $this->get_dashboard_alerts(),
            'trends' => $this->get_dashboard_trends()
        ];
        
        foreach ($sites as $site) {
            $domain = parse_url($site['url'], PHP_URL_HOST);
            
            // Health assessment
            $health = $this->calculate_overall_health($site['id'], $domain);
            $summary['sites_health'][$health['status']]++;
            
            // Security metrics
            $security = $this->get_security_status($site['id'], $domain);
            if ($security['score'] >= 80) $summary['security_overview']['secure_sites']++;
            $summary['security_overview']['vulnerabilities_found'] += $security['vulnerabilities_count'] ?? 0;
            
            // Performance metrics
            $performance = $this->get_performance_metrics($site['id'], $domain);
            $summary['performance_overview']['average_performance_score'] += $performance['score'] ?? 0;
            if (($performance['score'] ?? 0) >= 90) $summary['performance_overview']['sites_above_90']++;
            if (($performance['score'] ?? 0) < 70) $summary['performance_overview']['sites_needs_improvement']++;
        }
        
        // Calculate averages
        if ($summary['total_sites'] > 0) {
            $summary['performance_overview']['average_performance_score'] = 
                round($summary['performance_overview']['average_performance_score'] / $summary['total_sites']);
        }
        
        return $summary;
    }
    
    /**
     * Calculate overall health score
     */
    private function calculate_overall_health($site_id, $domain) {
        $scores = [];
        
        // Security score (30%)
        $security = $this->get_security_status($site_id, $domain);
        $scores['security'] = ($security['score'] ?? 0) * 0.3;
        
        // Performance score (25%)
        $performance = $this->get_performance_metrics($site_id, $domain);
        $scores['performance'] = ($performance['score'] ?? 0) * 0.25;
        
        // Backup health (20%)
        $backup = $this->get_backup_status($site_id, $domain);
        $scores['backup'] = ($backup['health_score'] ?? 0) * 0.2;
        
        // WordPress health (15%)
        $wordpress = $this->get_wordpress_health($site_id, $domain);
        $scores['wordpress'] = ($wordpress['score'] ?? 0) * 0.15;
        
        // Cloudflare performance (10%)
        $cloudflare = $this->get_cloudflare_summary($site_id, $domain);
        $scores['cloudflare'] = ($cloudflare['performance_score'] ?? 0) * 0.1;
        
        $overall_score = array_sum($scores);
        
        $status = 'critical';
        if ($overall_score >= 90) {
            $status = 'excellent';
        } elseif ($overall_score >= 75) {
            $status = 'good';
        } elseif ($overall_score >= 60) {
            $status = 'warning';
        }
        
        return [
            'score' => round($overall_score),
            'status' => $status,
            'breakdown' => $scores,
            'last_updated' => current_time('mysql')
        ];
    }
    
    /**
     * Get security status
     */
    private function get_security_status($site_id, $domain) {
        // Get latest vulnerability scan
        global $wpdb;
        $table_reports = $wpdb->prefix . 'rphub_reports';
        
        $vuln_scan = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table_reports 
            WHERE site_id = %d AND type = 'vulnerability_scan' 
            ORDER BY created_at DESC LIMIT 1
        ", $site_id), ARRAY_A);
        
        $vulnerabilities = [];
        if ($vuln_scan) {
            $data = json_decode($vuln_scan['data'], true);
            $vulnerabilities = $data['vulnerabilities_found'] ?? [];
        }
        
        // Get Cloudflare security events
        $cf_security = $this->cloudflare->get_security_events($domain, 20);
        
        $security_score = 100;
        
        // Deduct points for vulnerabilities
        $critical_vulns = array_filter($vulnerabilities, function($v) { return $v['severity'] === 'critical'; });
        $high_vulns = array_filter($vulnerabilities, function($v) { return $v['severity'] === 'high'; });
        
        $security_score -= count($critical_vulns) * 20;
        $security_score -= count($high_vulns) * 10;
        $security_score -= (count($vulnerabilities) - count($critical_vulns) - count($high_vulns)) * 5;
        
        return [
            'score' => max(0, $security_score),
            'vulnerabilities_count' => count($vulnerabilities),
            'critical_vulnerabilities' => count($critical_vulns),
            'threats_blocked_24h' => !is_wp_error($cf_security) ? $cf_security['total_events'] : 0,
            'ssl_status' => $this->get_ssl_status_simple($domain),
            'last_scan' => $vuln_scan['created_at'] ?? null
        ];
    }
    
    /**
     * Get performance metrics
     */
    private function get_performance_metrics($site_id, $domain) {
        global $wpdb;
        $table_pagespeed = $wpdb->prefix . 'rphub_pagespeed_reports';
        
        $latest_report = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table_pagespeed 
            WHERE url LIKE %s 
            ORDER BY created_at DESC LIMIT 1
        ", '%' . $domain . '%'), ARRAY_A);
        
        if (!$latest_report) {
            return ['score' => 0, 'status' => 'no_data'];
        }
        
        $data = json_decode($latest_report['analysis_data'], true);
        $performance_score = $data['scores']['performance']['score'] ?? 0;
        
        return [
            'score' => $performance_score,
            'core_web_vitals' => $data['core_web_vitals'] ?? [],
            'opportunities_count' => count($data['opportunities'] ?? []),
            'last_analysis' => $latest_report['created_at']
        ];
    }
    
    /**
     * Get backup status
     */
    private function get_backup_status($site_id, $domain) {
        $backup_health = $this->backuply->get_backup_health($domain);
        
        if (is_wp_error($backup_health)) {
            return ['health_score' => 0, 'status' => 'no_data'];
        }
        
        return [
            'health_score' => $backup_health['score'] ?? 0,
            'status' => $backup_health['status'] ?? 'unknown',
            'last_backup' => $this->get_last_backup_date($domain),
            'backup_count' => $this->get_backup_count($domain)
        ];
    }
    
    /**
     * Get WordPress health
     */
    private function get_wordpress_health($site_id, $domain) {
        $wp_info = $this->wptoolkit->get_site_info($domain);
        
        if (is_wp_error($wp_info)) {
            return ['score' => 0, 'status' => 'no_data'];
        }
        
        $score = 100;
        
        // Check WordPress version
        $latest_wp = $this->get_latest_wp_version();
        if (version_compare($wp_info['wordpress_version'] ?? '0', $latest_wp, '<')) {
            $score -= 20;
        }
        
        // Check for plugin updates
        $plugin_updates = array_filter($wp_info['plugins'] ?? [], function($p) { 
            return $p['update_available'] ?? false; 
        });
        $score -= count($plugin_updates) * 5;
        
        return [
            'score' => max(0, $score),
            'wordpress_version' => $wp_info['wordpress_version'] ?? 'unknown',
            'plugin_updates_count' => count($plugin_updates),
            'security_status' => $wp_info['security_status'] ?? 'unknown'
        ];
    }
    
    /**
     * Get Cloudflare summary
     */
    private function get_cloudflare_summary($site_id, $domain) {
        $cf_performance = $this->cloudflare->get_performance_summary($domain);
        
        if (is_wp_error($cf_performance)) {
            return ['performance_score' => 0, 'status' => 'not_configured'];
        }
        
        // Convert cache hit ratio to score
        $performance_score = $cf_performance['cache_hit_ratio'] ?? 0;
        
        return [
            'performance_score' => $performance_score,
            'cache_hit_ratio' => $cf_performance['cache_hit_ratio'] ?? 0,
            'bandwidth_saved' => $cf_performance['bandwidth_saved'] ?? '0 MB',
            'threats_blocked' => $cf_performance['threats_blocked'] ?? 0,
            'ssl_status' => $cf_performance['ssl_status'] ?? 'unknown'
        ];
    }
    
    /**
     * Generate comprehensive recommendations
     */
    private function generate_recommendations($site_id, $domain) {
        $recommendations = [];
        
        // Security recommendations
        $security = $this->get_security_status($site_id, $domain);
        if ($security['vulnerabilities_count'] > 0) {
            $recommendations[] = [
                'type' => 'security',
                'priority' => 'high',
                'title' => 'Corregir vulnerabilidades de seguridad',
                'description' => sprintf('Se encontraron %d vulnerabilidades que requieren atención', $security['vulnerabilities_count']),
                'action' => 'Ejecutar actualizaciones de seguridad'
            ];
        }
        
        // Performance recommendations
        $performance = $this->get_performance_metrics($site_id, $domain);
        if ($performance['score'] < 70) {
            $recommendations[] = [
                'type' => 'performance',
                'priority' => 'medium',
                'title' => 'Mejorar rendimiento del sitio',
                'description' => sprintf('Puntuación actual: %d/100', $performance['score']),
                'action' => 'Optimizar imágenes y caché'
            ];
        }
        
        // Backup recommendations
        $backup = $this->get_backup_status($site_id, $domain);
        if ($backup['health_score'] < 80) {
            $recommendations[] = [
                'type' => 'backup',
                'priority' => $backup['health_score'] < 50 ? 'high' : 'medium',
                'title' => 'Revisar sistema de backups',
                'description' => 'El sistema de backups requiere atención',
                'action' => 'Configurar backups automáticos'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Store report in database
     */
    private function store_report($site_id, $type, $data) {
        global $wpdb;
        $table_reports = $wpdb->prefix . 'rphub_comprehensive_reports';
        
        $wpdb->insert($table_reports, [
            'site_id' => $site_id,
            'report_type' => $type,
            'report_data' => json_encode($data),
            'generated_at' => current_time('mysql')
        ]);
    }
    
    /**
     * Export report to PDF
     */
    public function export_report_pdf($report_data, $filename = '') {
        if (empty($filename)) {
            $filename = 'replanta-report-' . date('Y-m-d-H-i-s') . '.pdf';
        }
        
        // Generate HTML content for PDF
        $html = $this->generate_report_html($report_data);
        
        // Create PDF (simplified - would need a PDF library like TCPDF or DOMPDF)
        $pdf_content = $this->html_to_pdf($html);
        
        // Force download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf_content));
        echo $pdf_content;
        exit;
    }
    
    private function get_dr_section(int $site_id): ?array {
        if (!class_exists('RPHUB_DR_Bridge')) {
            return null;
        }
        $dr = RPHUB_DR_Bridge::get_site_dr_data($site_id);
        if (empty($dr['whm_server']) && empty($dr['cf_zone_id'])) {
            return null;
        }
        return [
            'server'         => $dr['whm_server']      ?: '',
            'php_version'    => $dr['php_version_whm'] ?: '',
            'cf_plan'        => $dr['cf_plan_name']    ?: '',
            'cf_zone_status' => $dr['cf_zone_status']  ?: '',
            'trees_planted'  => (int) ($dr['trees_planted'] ?? 0),
            'co2_evaded'     => (float) ($dr['co2_evaded'] ?? 0),
            'enriched_at'    => $dr['dr_enriched_at']  ?: '',
        ];
    }

    private function get_audit_section(int $site_id): ?array {
        if (!class_exists('RPHUB_Database')) {
            return null;
        }
        $cf_score   = (int) RPHUB_Database::get_site_meta($site_id, 'cf_score');
        $seo_score  = (int) RPHUB_Database::get_site_meta($site_id, 'seo_score');
        $perf_score = (int) RPHUB_Database::get_site_meta($site_id, 'perf_score');
        $last_run   = RPHUB_Database::get_site_meta($site_id, 'audit_last_run');
        if (!$cf_score && !$seo_score && !$perf_score) {
            return null;
        }
        return [
            'cf_score'   => $cf_score,
            'seo_score'  => $seo_score,
            'perf_score' => $perf_score,
            'last_run'   => $last_run ?: '',
            'cf_issues'  => json_decode(RPHUB_Database::get_site_meta($site_id, 'cf_issues_json')  ?: '[]', true) ?: [],
            'seo_issues' => json_decode(RPHUB_Database::get_site_meta($site_id, 'seo_issues_json') ?: '[]', true) ?: [],
        ];
    }

    /**
     * Generate HTML for report
     */
    private function generate_report_html($data) {
        ob_start();
        $site       = $data['site_info']   ?? [];
        $dr         = $data['dr_info']     ?? null;
        $audit      = $data['audit_scores'] ?? null;
        $health     = $data['overall_health'] ?? [];
        $logo_url   = $site['logo_url'] ?? '';
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Replanta Hub - Reporte Comprensivo</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; color: #1d2327; }
                .header { display: flex; align-items: center; gap: 16px; border-bottom: 2px solid #2271b1; padding-bottom: 16px; margin-bottom: 24px; }
                .header-logo { width: 48px; height: 48px; border-radius: 6px; flex-shrink: 0; }
                .header-text h1 { margin: 0 0 4px; font-size: 18px; color: #2271b1; }
                .header-text h2 { margin: 0 0 4px; font-size: 22px; }
                .header-text p  { margin: 0; font-size: 12px; color: #646970; }
                .section { margin: 20px 0; }
                .section h3 { font-size: 15px; border-left: 3px solid #2271b1; padding-left: 8px; }
                .scores-grid { display: flex; gap: 16px; flex-wrap: wrap; margin: 12px 0; }
                .score-box { border: 1px solid #dcdcde; border-radius: 6px; padding: 12px 20px; text-align: center; min-width: 90px; }
                .score-num { font-size: 28px; font-weight: 700; line-height: 1; }
                .score-label { font-size: 11px; text-transform: uppercase; letter-spacing: .3px; color: #646970; margin-top: 4px; }
                .good { color: #00a32a; }
                .warning { color: #dba617; }
                .critical, .danger { color: #d63638; }
                .dr-table { width: 100%; border-collapse: collapse; margin: 8px 0; }
                .dr-table td { padding: 6px 10px; border-bottom: 1px solid #f0f0f1; font-size: 13px; }
                .dr-table td:first-child { font-weight: 600; width: 40%; color: #646970; }
                .eco-pill { display: inline-block; background: #edfaef; color: #00a32a; border-radius: 10px; padding: 2px 10px; font-size: 12px; font-weight: 600; }
                .issues-list { margin: 6px 0; padding: 0; list-style: none; }
                .issues-list li { font-size: 12px; padding: 3px 0; border-bottom: 1px solid #f6f7f7; }
                .issue-critical { color: #d63638; } .issue-warning { color: #dba617; }
            </style>
        </head>
        <body>
            <div class="header">
                <?php if ($logo_url): ?>
                <img class="header-logo" src="<?php echo esc_url($logo_url); ?>" alt="Logo" onerror="this.style.display='none'">
                <?php endif; ?>
                <div class="header-text">
                    <h1>Replanta Hub &mdash; Reporte Comprensivo</h1>
                    <h2><?php echo esc_html($site['name'] ?? 'Sitio Web'); ?></h2>
                    <p><?php echo esc_url($site['url'] ?? ''); ?> &bull; Generado: <?php echo esc_html($data['generated_at'] ?? date('Y-m-d H:i:s')); ?></p>
                </div>
            </div>

            <?php if ($audit): ?>
            <div class="section">
                <h3>Puntuaciones de Auditor&iacute;a</h3>
                <div class="scores-grid">
                    <?php
                    $score_items = [
                        'CF'  => $audit['cf_score'],
                        'SEO' => $audit['seo_score'],
                        'WPO' => $audit['perf_score'],
                    ];
                    foreach ($score_items as $lbl => $val):
                        $cls = $val >= 80 ? 'good' : ($val >= 50 ? 'warning' : 'critical');
                    ?>
                    <div class="score-box">
                        <div class="score-num <?php echo $cls; ?>"><?php echo (int) $val; ?></div>
                        <div class="score-label"><?php echo esc_html($lbl); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (!empty($audit['last_run'])): ?>
                <p style="font-size:11px;color:#646970;">Última auditoría: <?php echo esc_html($audit['last_run']); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($dr): ?>
            <div class="section">
                <h3>Infraestructura</h3>
                <table class="dr-table">
                    <?php if ($dr['server']): ?>
                    <tr><td>Servidor</td><td><?php echo esc_html($dr['server']); ?></td></tr>
                    <?php endif; ?>
                    <?php if ($dr['php_version']): ?>
                    <tr><td>PHP (WHM)</td><td><?php echo esc_html($dr['php_version']); ?></td></tr>
                    <?php endif; ?>
                    <?php if ($dr['cf_plan']): ?>
                    <tr><td>Plan Cloudflare</td><td><?php echo esc_html(ucfirst($dr['cf_plan'])); ?> &mdash; <span style="color:<?php echo $dr['cf_zone_status'] === 'active' ? '#00a32a' : '#dba617'; ?>"><?php echo esc_html($dr['cf_zone_status']); ?></span></td></tr>
                    <?php endif; ?>
                    <?php if ($dr['trees_planted'] > 0 || $dr['co2_evaded'] > 0): ?>
                    <tr>
                        <td>Ecolog&iacute;a</td>
                        <td>
                            <?php if ($dr['trees_planted'] > 0): ?>
                            <span class="eco-pill">&#127807; <?php echo (int)$dr['trees_planted']; ?> &aacute;rboles plantados</span>
                            <?php endif; ?>
                            <?php if ($dr['co2_evaded'] > 0): ?>
                            <span class="eco-pill" style="margin-left:6px;">&#127807; <?php echo esc_html($dr['co2_evaded']); ?> kg CO&sup2; evitados</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            <?php endif; ?>

            <div class="section">
                <h3>Salud General</h3>
                <div class="scores-grid">
                    <div class="score-box">
                        <div class="score-num <?php echo $this->get_score_class($health['score'] ?? 0); ?>">
                            <?php echo esc_html($health['score'] ?? 0); ?>/100
                        </div>
                        <div class="score-label">Salud General</div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get CSS class for score
     */
    private function get_score_class($score) {
        if ($score >= 80) return 'good';
        if ($score >= 60) return 'warning';
        return 'critical';
    }
    
    /**
     * Simple HTML to PDF conversion (placeholder)
     */
    private function html_to_pdf($html) {
        // This would use a real PDF library like DOMPDF, TCPDF, or mPDF
        // For now, returning placeholder
        return "PDF content would be generated here from HTML: " . substr($html, 0, 100) . "...";
    }
    
    /**
     * Helper methods for data processing
     */
    private function get_site_basic_info($site) {
        $domain = parse_url($site['url'], PHP_URL_HOST) ?: '';
        return [
            'id'         => $site['id'],
            'name'       => $site['name'],
            'url'        => $site['url'],
            'created_at' => $site['created_at'],
            'logo_url'   => 'https://www.google.com/s2/favicons?domain=' . urlencode($domain) . '&sz=64',
        ];
    }
    
    private function get_ssl_status_simple($domain) {
        $ssl = $this->cloudflare->get_ssl_status($domain);
        return is_wp_error($ssl) ? 'unknown' : ($ssl['ssl_mode'] ?? 'unknown');
    }
    
    private function get_last_backup_date($domain) {
        $backups = $this->backuply->list_backups($domain, 1);
        return is_wp_error($backups) || empty($backups) ? null : $backups[0]['created_at'];
    }
    
    private function get_backup_count($domain) {
        $backups = $this->backuply->list_backups($domain);
        return is_wp_error($backups) ? 0 : count($backups);
    }
    
    private function get_latest_wp_version() {
        $version = get_transient('rphub_latest_wp_version');
        if ($version) return $version;
        
        $response = wp_remote_get('https://api.wordpress.org/core/version-check/1.7/');
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (!empty($body['offers'][0]['version'])) {
                $version = $body['offers'][0]['version'];
                set_transient('rphub_latest_wp_version', $version, DAY_IN_SECONDS);
                return $version;
            }
        }
        return get_bloginfo('version');
    }
    
    private function get_recent_dashboard_activities() {
        global $wpdb;
        $table_activities = $wpdb->prefix . 'rphub_activities';
        
        return $wpdb->get_results("
            SELECT * FROM $table_activities 
            ORDER BY created_at DESC 
            LIMIT 10
        ", ARRAY_A);
    }
    
    private function get_dashboard_alerts() {
        global $wpdb;
        $table_notifications = $wpdb->prefix . 'rphub_notifications';
        
        return $wpdb->get_results("
            SELECT * FROM $table_notifications 
            WHERE status = 'unread' 
            ORDER BY created_at DESC 
            LIMIT 5
        ", ARRAY_A);
    }
    
    private function get_dashboard_trends() {
        // Calculate trends for the dashboard
        return [
            'performance_trend' => '+5%',
            'security_trend' => '+2%',
            'backup_trend' => '0%'
        ];
    }
    
    /**
     * AJAX Handlers
     */
    public function ajax_generate_comprehensive_report() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $site_id = $_POST['site_id'] ?? 0;
        $period = $_POST['period'] ?? '7d';
        
        if (!$site_id) {
            wp_send_json_error('ID de sitio requerido');
        }
        
        $result = $this->generate_comprehensive_report($site_id, $period);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_generate_security_report() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $site_id = $_POST['site_id'] ?? 0;
        
        if (!$site_id) {
            wp_send_json_error('ID de sitio requerido');
        }
        
        $result = $this->generate_security_report($site_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_generate_performance_report() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $site_id = $_POST['site_id'] ?? 0;
        
        if (!$site_id) {
            wp_send_json_error('ID de sitio requerido');
        }
        
        $result = $this->generate_performance_report($site_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_generate_maintenance_report() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $site_id = $_POST['site_id'] ?? 0;
        
        if (!$site_id) {
            wp_send_json_error('ID de sitio requerido');
        }
        
        $result = $this->generate_maintenance_report($site_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_get_dashboard_summary() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $result = $this->get_dashboard_summary();
        wp_send_json_success($result);
    }
    
    public function ajax_export_report() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $report_id = $_POST['report_id'] ?? 0;
        $format = $_POST['format'] ?? 'pdf';
        
        if (!$report_id) {
            wp_send_json_error('ID de reporte requerido');
        }
        
        global $wpdb;
        $table_reports = $wpdb->prefix . 'rphub_comprehensive_reports';
        
        $report = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table_reports WHERE id = %d
        ", $report_id), ARRAY_A);
        
        if (!$report) {
            wp_send_json_error('Reporte no encontrado');
        }
        
        $data = json_decode($report['report_data'], true);
        
        if ($format === 'pdf') {
            $this->export_report_pdf($data);
        } else {
            wp_send_json_error('Formato no soportado');
        }
    }
    
    /**
     * Scheduled Tasks
     */
    public function scheduled_weekly_reports() {
        global $wpdb;
        $table_sites = $wpdb->prefix . 'rphub_sites';
        
        $sites = $wpdb->get_results("
            SELECT * FROM $table_sites 
            WHERE status = 'active' 
            AND weekly_reports = 1
        ", ARRAY_A);
        
        foreach ($sites as $site) {
            $this->generate_comprehensive_report($site['id'], '7d');
            
            // Send email notification if configured
            $this->send_report_notification($site['id'], 'weekly');
        }
    }
    
    public function scheduled_monthly_reports() {
        global $wpdb;
        $table_sites = $wpdb->prefix . 'rphub_sites';
        
        $sites = $wpdb->get_results("
            SELECT * FROM $table_sites 
            WHERE status = 'active' 
            AND monthly_reports = 1
        ", ARRAY_A);
        
        foreach ($sites as $site) {
            $this->generate_comprehensive_report($site['id'], '30d');
            
            // Send email notification if configured
            $this->send_report_notification($site['id'], 'monthly');
        }
    }
    
    /**
     * Send report notification
     */
    private function send_report_notification($site_id, $frequency) {
        // This would send email notifications about generated reports
        // Implementation would depend on email system setup
    }
    
    /**
     * Missing methods implementation
     */
    private function get_active_alerts($site_id) {
        global $wpdb;
        $table_notifications = $wpdb->prefix . 'rphub_notifications';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table_notifications 
            WHERE site_id = %d AND status = 'unread' 
            ORDER BY created_at DESC LIMIT 5
        ", $site_id), ARRAY_A);
    }
    
    private function get_activity_summary($site_id, $period) {
        global $wpdb;
        $table_activities = $wpdb->prefix . 'rphub_activities';
        
        $days = $this->period_to_days($period);
        $since = date('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table_activities 
            WHERE site_id = %d AND created_at >= %s 
            ORDER BY created_at DESC LIMIT 20
        ", $site_id, $since), ARRAY_A);
    }
    
    private function get_cost_analysis($site_id, $period) {
        // Calculate hosting and service costs
        return [
            'hosting_cost' => 0,
            'cloudflare_cost' => 0,
            'backup_cost' => 0,
            'total_cost' => 0,
            'cost_per_visitor' => 0
        ];
    }
    
    private function get_next_actions($site_id, $domain) {
        $actions = [];
        
        // Check for pending updates
        $wp_info = $this->wptoolkit->get_site_info($domain);
        if (!is_wp_error($wp_info)) {
            $updates = array_filter($wp_info['plugins'] ?? [], function($p) { 
                return $p['update_available'] ?? false; 
            });
            
            if (!empty($updates)) {
                $actions[] = [
                    'type' => 'update',
                    'priority' => 'medium',
                    'title' => 'Actualizar plugins',
                    'description' => count($updates) . ' plugins tienen actualizaciones disponibles'
                ];
            }
        }
        
        return $actions;
    }
    
    private function calculate_security_score($vulnerability_scan, $cf_security, $ssl_status) {
        $score = 100;
        
        if (!is_wp_error($vulnerability_scan)) {
            $vulns = $vulnerability_scan['vulnerabilities_found'] ?? [];
            $score -= count($vulns) * 10;
        }
        
        if (!is_wp_error($cf_security)) {
            // Good security events mean good protection
            $score += min(10, $cf_security['total_events'] / 10);
        }
        
        if (!is_wp_error($ssl_status) && $ssl_status['ssl_mode'] !== 'off') {
            $score += 10;
        }
        
        return max(0, min(100, $score));
    }
    
    private function process_vulnerability_data($vulnerability_scan) {
        if (is_wp_error($vulnerability_scan)) {
            return ['status' => 'error', 'message' => $vulnerability_scan->get_error_message()];
        }
        
        return [
            'total_vulnerabilities' => count($vulnerability_scan['vulnerabilities_found'] ?? []),
            'risk_level' => $vulnerability_scan['risk_level'] ?? 'low',
            'recommendations' => $vulnerability_scan['recommendations'] ?? []
        ];
    }
    
    private function process_threat_data($cf_security) {
        if (is_wp_error($cf_security)) {
            return ['status' => 'error', 'message' => $cf_security->get_error_message()];
        }
        
        return [
            'total_events' => $cf_security['total_events'] ?? 0,
            'threat_summary' => $cf_security['threat_summary'] ?? [],
            'top_countries' => $cf_security['top_countries'] ?? []
        ];
    }
    
    private function process_ssl_data($ssl_status) {
        if (is_wp_error($ssl_status)) {
            return ['status' => 'error', 'message' => $ssl_status->get_error_message()];
        }
        
        return [
            'ssl_mode' => $ssl_status['ssl_mode'] ?? 'off',
            'ssl_status' => $ssl_status['ssl_status'] ?? 'unknown',
            'recommendations' => $ssl_status['ssl_recommendations'] ?? []
        ];
    }
    
    private function generate_security_recommendations($vulnerability_scan, $cf_security, $ssl_status) {
        $recommendations = [];
        
        if (!is_wp_error($vulnerability_scan) && !empty($vulnerability_scan['vulnerabilities_found'])) {
            $recommendations[] = 'Corregir vulnerabilidades detectadas';
        }
        
        if (!is_wp_error($ssl_status) && $ssl_status['ssl_mode'] === 'off') {
            $recommendations[] = 'Activar SSL/TLS';
        }
        
        return $recommendations;
    }
    
    private function check_security_compliance($site_id, $domain) {
        return [
            'https_enabled' => true,
            'ssl_certificate' => 'valid',
            'security_headers' => 'partial',
            'compliance_score' => 85
        ];
    }
    
    private function get_security_timeline($site_id, $days) {
        global $wpdb;
        $table_reports = $wpdb->prefix . 'rphub_reports';
        
        $since = date('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table_reports 
            WHERE site_id = %d AND type = 'vulnerability_scan' AND created_at >= %s 
            ORDER BY created_at DESC
        ", $site_id, $since), ARRAY_A);
    }
    
    private function assess_security_risk($vulnerability_scan, $cf_security) {
        $risk_level = 'low';
        
        if (!is_wp_error($vulnerability_scan)) {
            $risk_level = $vulnerability_scan['risk_level'] ?? 'low';
        }
        
        return [
            'level' => $risk_level,
            'factors' => ['vulnerabilities', 'outdated_software'],
            'mitigation_steps' => ['Update software', 'Enable security monitoring']
        ];
    }
    
    private function calculate_performance_score($pagespeed_mobile, $pagespeed_desktop, $cf_analytics) {
        $mobile_score = 0;
        $desktop_score = 0;
        
        if (!is_wp_error($pagespeed_mobile)) {
            $mobile_score = $pagespeed_mobile['scores']['performance']['score'] ?? 0;
        }
        
        if (!is_wp_error($pagespeed_desktop)) {
            $desktop_score = $pagespeed_desktop['scores']['performance']['score'] ?? 0;
        }
        
        return round(($mobile_score + $desktop_score) / 2);
    }
    
    private function process_pagespeed_data($pagespeed_data) {
        if (is_wp_error($pagespeed_data)) {
            return ['status' => 'error', 'message' => $pagespeed_data->get_error_message()];
        }
        
        return [
            'performance_score' => $pagespeed_data['scores']['performance']['score'] ?? 0,
            'core_web_vitals' => $pagespeed_data['core_web_vitals'] ?? [],
            'opportunities' => array_slice($pagespeed_data['opportunities'] ?? [], 0, 5)
        ];
    }
    
    private function get_core_web_vitals_summary($pagespeed_mobile, $pagespeed_desktop) {
        $mobile_vitals = !is_wp_error($pagespeed_mobile) ? $pagespeed_mobile['core_web_vitals'] ?? [] : [];
        $desktop_vitals = !is_wp_error($pagespeed_desktop) ? $pagespeed_desktop['core_web_vitals'] ?? [] : [];
        
        return [
            'mobile' => $mobile_vitals,
            'desktop' => $desktop_vitals,
            'overall_status' => $this->assess_core_web_vitals_status($mobile_vitals, $desktop_vitals)
        ];
    }
    
    private function process_cf_performance_data($cf_analytics) {
        if (is_wp_error($cf_analytics)) {
            return ['status' => 'error', 'message' => $cf_analytics->get_error_message()];
        }
        
        return [
            'cache_hit_ratio' => $cf_analytics['cache_hit_ratio'] ?? 0,
            'bandwidth_saved' => $cf_analytics['bandwidth_saved'] ?? '0 MB',
            'total_requests' => $cf_analytics['total_requests'] ?? 0
        ];
    }
    
    private function get_performance_trends($site_id, $days) {
        global $wpdb;
        $table_pagespeed = $wpdb->prefix . 'rphub_pagespeed_reports';
        
        $since = date('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT performance_score, created_at FROM $table_pagespeed 
            WHERE site_id = %d AND created_at >= %s 
            ORDER BY created_at ASC
        ", $site_id, $since), ARRAY_A);
        
        return array_map(function($row) {
            return [
                'date' => $row['created_at'],
                'score' => $row['performance_score']
            ];
        }, $results);
    }
    
    private function get_optimization_opportunities($pagespeed_mobile, $pagespeed_desktop) {
        $opportunities = [];
        
        if (!is_wp_error($pagespeed_mobile)) {
            $opportunities = array_merge($opportunities, $pagespeed_mobile['opportunities'] ?? []);
        }
        
        if (!is_wp_error($pagespeed_desktop)) {
            $opportunities = array_merge($opportunities, $pagespeed_desktop['opportunities'] ?? []);
        }
        
        // Remove duplicates and sort by potential savings
        return array_slice($opportunities, 0, 10);
    }
    
    private function generate_performance_recommendations($pagespeed_mobile, $pagespeed_desktop, $cf_analytics) {
        $recommendations = [];
        
        if (!is_wp_error($pagespeed_mobile)) {
            $mobile_score = $pagespeed_mobile['scores']['performance']['score'] ?? 0;
            if ($mobile_score < 70) {
                $recommendations[] = 'Optimizar rendimiento móvil';
            }
        }
        
        if (!is_wp_error($cf_analytics)) {
            $cache_ratio = $cf_analytics['cache_hit_ratio'] ?? 0;
            if ($cache_ratio < 80) {
                $recommendations[] = 'Mejorar configuración de caché';
            }
        }
        
        return $recommendations;
    }
    
    private function get_competitive_performance_analysis($domain) {
        // This would compare against industry benchmarks
        return [
            'industry_average' => 65,
            'your_score' => 0,
            'percentile' => 50
        ];
    }
    
    private function calculate_maintenance_score($wp_info, $update_recommendations, $backup_health) {
        $score = 100;
        
        if (!is_wp_error($update_recommendations)) {
            $updates_needed = count($update_recommendations['plugins'] ?? []);
            $score -= $updates_needed * 5;
        }
        
        if (!is_wp_error($backup_health)) {
            if ($backup_health['status'] !== 'good') {
                $score -= 20;
            }
        }
        
        return max(0, $score);
    }
    
    private function process_wp_maintenance_data($wp_info) {
        if (is_wp_error($wp_info)) {
            return ['status' => 'error', 'message' => $wp_info->get_error_message()];
        }
        
        return [
            'wordpress_version' => $wp_info['wordpress_version'] ?? 'unknown',
            'php_version' => $wp_info['php_version'] ?? 'unknown',
            'security_status' => $wp_info['security_status'] ?? 'unknown'
        ];
    }
    
    private function process_update_data($update_recommendations) {
        if (is_wp_error($update_recommendations)) {
            return ['status' => 'error', 'message' => $update_recommendations->get_error_message()];
        }
        
        return [
            'wordpress_updates' => isset($update_recommendations['wordpress']),
            'plugin_updates' => count($update_recommendations['plugins'] ?? []),
            'theme_updates' => count($update_recommendations['themes'] ?? [])
        ];
    }
    
    private function process_backup_health_data($backup_health) {
        if (is_wp_error($backup_health)) {
            return ['status' => 'error', 'message' => $backup_health->get_error_message()];
        }
        
        return [
            'status' => $backup_health['status'] ?? 'unknown',
            'score' => $backup_health['score'] ?? 0,
            'issues' => $backup_health['issues'] ?? [],
            'recommendations' => $backup_health['recommendations'] ?? []
        ];
    }
    
    private function get_pending_maintenance_tasks($site_id, $domain) {
        $tasks = [];
        
        // Check for WordPress updates
        $wp_info = $this->wptoolkit->get_site_info($domain);
        if (!is_wp_error($wp_info)) {
            $updates = $this->wptoolkit->get_update_recommendations($domain);
            if (!is_wp_error($updates) && !empty($updates)) {
                $tasks[] = [
                    'type' => 'updates',
                    'title' => 'Actualizaciones pendientes',
                    'priority' => 'medium',
                    'estimated_time' => '15 minutos'
                ];
            }
        }
        
        return $tasks;
    }
    
    private function get_automated_maintenance_status($site_id) {
        global $wpdb;
        $table_sites = $wpdb->prefix . 'rphub_sites';
        
        $site = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_sites WHERE id = %d", $site_id), ARRAY_A);
        
        return [
            'auto_updates' => $site['auto_updates'] ?? 0,
            'auto_backups' => $site['auto_backups'] ?? 0,
            'maintenance_mode' => $site['maintenance_mode'] ?? 0
        ];
    }
    
    private function get_maintenance_schedule($site_id) {
        return [
            'next_backup' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'next_update_check' => date('Y-m-d H:i:s', strtotime('+1 week')),
            'next_security_scan' => date('Y-m-d H:i:s', strtotime('+1 day'))
        ];
    }
    
    private function get_maintenance_history($site_id, $days) {
        global $wpdb;
        $table_activities = $wpdb->prefix . 'rphub_activities';
        
        $since = date('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table_activities 
            WHERE site_id = %d AND type IN ('update', 'backup', 'maintenance') AND created_at >= %s 
            ORDER BY created_at DESC
        ", $site_id, $since), ARRAY_A);
    }
    
    private function generate_maintenance_recommendations($wp_info, $update_recommendations, $backup_health) {
        $recommendations = [];
        
        if (!is_wp_error($update_recommendations) && !empty($update_recommendations)) {
            $recommendations[] = 'Aplicar actualizaciones pendientes';
        }
        
        if (!is_wp_error($backup_health) && $backup_health['status'] !== 'good') {
            $recommendations[] = 'Revisar configuración de backups';
        }
        
        return $recommendations;
    }
    
    private function period_to_days($period) {
        switch ($period) {
            case '1d': return 1;
            case '7d': return 7;
            case '30d': return 30;
            case '90d': return 90;
            default: return 7;
        }
    }
    
    private function assess_core_web_vitals_status($mobile_vitals, $desktop_vitals) {
        $good_count = 0;
        $total_count = 0;
        
        foreach ([$mobile_vitals, $desktop_vitals] as $vitals) {
            foreach ($vitals as $vital) {
                $total_count++;
                if (($vital['rating'] ?? '') === 'good') {
                    $good_count++;
                }
            }
        }
        
        if ($total_count === 0) return 'no_data';
        
        $ratio = $good_count / $total_count;
        if ($ratio >= 0.8) return 'good';
        if ($ratio >= 0.5) return 'needs_improvement';
        return 'poor';
    }
}

// Initialize Reports system
function rphub_reports_init() {
    return new ReplantaHub_Reports_System();
}
add_action('plugins_loaded', 'rphub_reports_init');
