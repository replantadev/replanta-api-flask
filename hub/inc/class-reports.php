<?php
/**
 * Reports Class
 * Generates and manages reports for sites and tasks
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPHUB_Reports {
    
    private $table_sites;
    private $table_tasks;
    private $table_reports;
    private $reports_dir;
    
    public function __construct() {
        global $wpdb;
        $this->table_sites = $wpdb->prefix . 'rphub_sites';
        $this->table_tasks = $wpdb->prefix . 'rphub_tasks';
        $this->table_reports = $wpdb->prefix . 'rphub_reports';

        $upload_dir = wp_upload_dir();
        $this->reports_dir = $upload_dir['basedir'] . '/rphub-reports';

        // AJAX de reportes lo gestiona RphubReportGenerator (includes/class-report-generator.php).
        // Esta clase se instancia solo al renderizar páginas admin (dashboard/reports).
    }
    
    /**
     * Generate report for a specific site
     */
    public function generate_site_report($site_id, $period = 'monthly', $format = 'html') {
        global $wpdb;
        
        $site = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_sites} WHERE id = %d", $site_id)
        );
        
        if (!$site) {
            return new WP_Error('site_not_found', 'Sitio no encontrado');
        }
        
        // Calculate date range
        $date_range = $this->get_date_range($period);
        
        // Get report data
        $report_data = $this->collect_site_data($site_id, $date_range['start'], $date_range['end']);
        $report_data['site'] = $site;
        $report_data['period'] = $period;
        $report_data['date_range'] = $date_range;
        
        // Generate report file
        $filename = $this->generate_report_file($report_data, $format);
        
        if (is_wp_error($filename)) {
            return $filename;
        }
        
        // Save report record
        $report_id = $this->save_report_record($site_id, 'site', $period, $report_data, $filename);
        
        return [
            'report_id' => $report_id,
            'filename' => $filename,
            'data' => $report_data
        ];
    }
    
    /**
     * Generate summary report for all sites
     */
    public function generate_summary_report($period = 'monthly', $format = 'html') {
        // Calculate date range
        $date_range = $this->get_date_range($period);
        
        // Get summary data
        $report_data = $this->collect_summary_data($date_range['start'], $date_range['end']);
        $report_data['period'] = $period;
        $report_data['date_range'] = $date_range;
        
        // Generate report file
        $filename = $this->generate_report_file($report_data, $format, 'summary');
        
        if (is_wp_error($filename)) {
            return $filename;
        }
        
        // Save report record
        $report_id = $this->save_report_record(null, 'summary', $period, $report_data, $filename);
        
        return [
            'report_id' => $report_id,
            'filename' => $filename,
            'data' => $report_data
        ];
    }
    
    /**
     * Collect site-specific data for report
     */
    private function collect_site_data($site_id, $start_date, $end_date) {
        global $wpdb;
        
        $data = [];
        
        // Basic site info
        $site = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_sites} WHERE id = %d", $site_id)
        );
        
        $data['site_info'] = $site;
        
        // Task statistics
        $task_stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                    COUNT(*) as total_tasks,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_tasks,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_tasks
                 FROM {$this->table_tasks}
                 WHERE site_id = %d AND created_at BETWEEN %s AND %s",
                $site_id, $start_date, $end_date
            )
        );
        
        $data['task_statistics'] = $task_stats;
        
        // Tasks by type
        $tasks_by_type = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT task_type, COUNT(*) as count,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                 FROM {$this->table_tasks}
                 WHERE site_id = %d AND created_at BETWEEN %s AND %s
                 GROUP BY task_type
                 ORDER BY count DESC",
                $site_id, $start_date, $end_date
            )
        );
        
        $data['tasks_by_type'] = $tasks_by_type;
        
        // Recent completed tasks
        $recent_tasks = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT task_type, status, completed_at, 
                    TIMESTAMPDIFF(SECOND, started_at, completed_at) as duration
                 FROM {$this->table_tasks}
                 WHERE site_id = %d AND completed_at BETWEEN %s AND %s
                 ORDER BY completed_at DESC
                 LIMIT 20",
                $site_id, $start_date, $end_date
            )
        );
        
        $data['recent_tasks'] = $recent_tasks;
        
        // Health score history (if available)
        $health_history = $this->get_health_score_history($site_id, $start_date, $end_date);
        $data['health_history'] = $health_history;
        
        // Security issues history
        $security_history = $this->get_security_history($site_id, $start_date, $end_date);
        $data['security_history'] = $security_history;
        
        // Updates history
        $updates_history = $this->get_updates_history($site_id, $start_date, $end_date);
        $data['updates_history'] = $updates_history;
        
        return $data;
    }
    
    /**
     * Collect summary data for all sites
     */
    private function collect_summary_data($start_date, $end_date) {
        global $wpdb;
        
        $data = [];
        
        // Overall statistics
        $overall_stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_sites,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_sites,
                SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as error_sites,
                AVG(health_score) as avg_health_score,
                SUM(updates_available) as total_updates,
                SUM(security_issues) as total_security_issues
             FROM {$this->table_sites}"
        );
        
        $data['overall_stats'] = $overall_stats;
        
        // Sites by plan
        $sites_by_plan = $wpdb->get_results(
            "SELECT plan, COUNT(*) as count
             FROM {$this->table_sites}
             GROUP BY plan"
        );
        
        $data['sites_by_plan'] = $sites_by_plan;
        
        // Task performance
        $task_performance = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                    COUNT(*) as total_tasks,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_tasks,
                    AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as avg_duration
                 FROM {$this->table_tasks}
                 WHERE created_at BETWEEN %s AND %s",
                $start_date, $end_date
            )
        );
        
        $data['task_performance'] = $task_performance;
        
        // Top performing sites
        $top_sites = $wpdb->get_results(
            "SELECT name, url, health_score, plan
             FROM {$this->table_sites}
             WHERE status = 'active'
             ORDER BY health_score DESC
             LIMIT 10"
        );
        
        $data['top_sites'] = $top_sites;
        
        // Sites needing attention
        $attention_sites = $wpdb->get_results(
            "SELECT name, url, health_score, security_issues, updates_available
             FROM {$this->table_sites}
             WHERE status = 'active' AND (health_score < 70 OR security_issues > 0 OR updates_available > 5)
             ORDER BY health_score ASC, security_issues DESC
             LIMIT 10"
        );
        
        $data['attention_sites'] = $attention_sites;
        
        // Task trends
        $task_trends = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(created_at) as date, COUNT(*) as tasks,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                 FROM {$this->table_tasks}
                 WHERE created_at BETWEEN %s AND %s
                 GROUP BY DATE(created_at)
                 ORDER BY date ASC",
                $start_date, $end_date
            )
        );
        
        $data['task_trends'] = $task_trends;
        
        return $data;
    }
    
    /**
     * Generate report file in specified format
     */
    private function generate_report_file($data, $format, $type = 'site') {
        $timestamp = date('Y-m-d_H-i-s');
        $site_name = isset($data['site']) ? sanitize_file_name($data['site']->name) : 'summary';
        $filename = "report_{$type}_{$site_name}_{$data['period']}_{$timestamp}.{$format}";
        $filepath = $this->reports_dir . '/' . $filename;
        
        // Ensure directory exists
        if (!file_exists($this->reports_dir)) {
            wp_mkdir_p($this->reports_dir);
        }
        
        switch ($format) {
            case 'html':
                $content = $this->generate_html_report($data, $type);
                break;
            case 'pdf':
                $content = $this->generate_pdf_report($data, $type);
                break;
            case 'csv':
                $content = $this->generate_csv_report($data, $type);
                break;
            case 'json':
                $content = json_encode($data, JSON_PRETTY_PRINT);
                break;
            default:
                return new WP_Error('invalid_format', 'Formato de reporte no válido');
        }
        
        if (file_put_contents($filepath, $content) === false) {
            return new WP_Error('file_error', 'Error al crear el archivo de reporte');
        }
        
        return $filename;
    }
    
    /**
     * Generate HTML report
     */
    private function generate_html_report($data, $type) {
        $template = RPHUB_PLUGIN_DIR . "templates/reports/report-{$type}.php";
        if (!file_exists($template)) {
            $template = RPHUB_PLUGIN_DIR . 'templates/reports/report-default.php';
        }
        ob_start();
        include $template;
        return ob_get_clean();
    }
    
    /**
     * Generate PDF report
     */
    private function generate_pdf_report($data, $type) {
        // For PDF generation, we'll use the HTML content and convert it
        // This is a simplified version - in production you might use a library like TCPDF or DOMPDF
        $html_content = $this->generate_html_report($data, $type);
        
        // Basic PDF headers
        $pdf_content = $html_content; // Simplified - would need proper PDF library
        
        return $pdf_content;
    }
    
    /**
     * Generate CSV report
     */
    private function generate_csv_report($data, $type) {
        $csv_content = [];
        
        if ($type === 'site') {
            // Site report CSV format
            $csv_content[] = ['Metric', 'Value'];
            $csv_content[] = ['Site Name', $data['site']->name];
            $csv_content[] = ['URL', $data['site']->url];
            $csv_content[] = ['Plan', $data['site']->plan];
            $csv_content[] = ['Health Score', $data['site']->health_score];
            $csv_content[] = ['Total Tasks', $data['task_statistics']->total_tasks];
            $csv_content[] = ['Completed Tasks', $data['task_statistics']->completed_tasks];
            $csv_content[] = ['Failed Tasks', $data['task_statistics']->failed_tasks];
        } else {
            // Summary report CSV format
            $csv_content[] = ['Metric', 'Value'];
            $csv_content[] = ['Total Sites', $data['overall_stats']->total_sites];
            $csv_content[] = ['Active Sites', $data['overall_stats']->active_sites];
            $csv_content[] = ['Average Health Score', $data['overall_stats']->avg_health_score];
            $csv_content[] = ['Total Updates Available', $data['overall_stats']->total_updates];
            $csv_content[] = ['Total Security Issues', $data['overall_stats']->total_security_issues];
        }
        
        // Convert to CSV string
        $output = '';
        foreach ($csv_content as $row) {
            $output .= implode(',', array_map(function($field) {
                return '"' . str_replace('"', '""', $field) . '"';
            }, $row)) . "\n";
        }
        
        return $output;
    }
    
    /**
     * Save report record to database
     */
    private function save_report_record($site_id, $report_type, $period, $data, $filename) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $this->table_reports,
            [
                'site_id' => $site_id,
                'report_type' => $report_type,
                'period' => $period,
                'data' => json_encode($data),
                'file_path' => $filename,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s']
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Get date range for period
     */
    private function get_date_range($period) {
        $end_date = date('Y-m-d H:i:s');
        
        switch ($period) {
            case 'daily':
                $start_date = date('Y-m-d H:i:s', strtotime('-1 day'));
                break;
            case 'weekly':
                $start_date = date('Y-m-d H:i:s', strtotime('-1 week'));
                break;
            case 'monthly':
                $start_date = date('Y-m-d H:i:s', strtotime('-1 month'));
                break;
            case 'quarterly':
                $start_date = date('Y-m-d H:i:s', strtotime('-3 months'));
                break;
            case 'yearly':
                $start_date = date('Y-m-d H:i:s', strtotime('-1 year'));
                break;
            default:
                $start_date = date('Y-m-d H:i:s', strtotime('-1 month'));
        }
        
        return [
            'start' => $start_date,
            'end' => $end_date
        ];
    }
    
    /**
     * Get health score history (placeholder for future implementation)
     */
    private function get_health_score_history($site_id, $start_date, $end_date) {
        // This would require storing health score changes over time
        // For now, return empty array
        return [];
    }
    
    /**
     * Get security history
     */
    private function get_security_history($site_id, $start_date, $end_date) {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT completed_at as date, result
                 FROM {$this->table_tasks}
                 WHERE site_id = %d AND task_type = 'security_scan' 
                 AND completed_at BETWEEN %s AND %s
                 ORDER BY completed_at ASC",
                $site_id, $start_date, $end_date
            )
        );
    }
    
    /**
     * Get updates history
     */
    private function get_updates_history($site_id, $start_date, $end_date) {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT completed_at as date, result
                 FROM {$this->table_tasks}
                 WHERE site_id = %d AND task_type IN ('update_plugins', 'update_themes', 'update_core')
                 AND completed_at BETWEEN %s AND %s
                 ORDER BY completed_at ASC",
                $site_id, $start_date, $end_date
            )
        );
    }
    
    /**
     * Email report to specified recipients
     */
    public function email_report($report_id, $recipients) {
        global $wpdb;
        
        $report = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_reports} WHERE id = %d", $report_id)
        );
        
        if (!$report) {
            return new WP_Error('report_not_found', 'Reporte no encontrado');
        }
        
        $filepath = $this->reports_dir . '/' . $report->file_path;
        
        if (!file_exists($filepath)) {
            return new WP_Error('file_not_found', 'Archivo de reporte no encontrado');
        }
        
        $subject = "Reporte Replanta Hub - {$report->report_type} - {$report->period}";
        $message = "Adjunto encontrarás el reporte solicitado.\n\nGenerado: " . $report->created_at;
        
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $attachments = [$filepath];
        
        $sent = wp_mail($recipients, $subject, $message, $headers, $attachments);
        
        if ($sent) {
            // Mark as sent
            $wpdb->update(
                $this->table_reports,
                ['email_sent' => 1],
                ['id' => $report_id],
                ['%d'],
                ['%d']
            );
        }
        
        return $sent;
    }
}
