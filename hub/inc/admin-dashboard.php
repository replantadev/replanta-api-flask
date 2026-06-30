<?php
/**
 * Admin Dashboard Page
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPHUB_Admin_Dashboard {
    
    private $enhanced_dashboard;
    
    public function __construct() {
        if (class_exists('RP_Hub_Enhanced_Dashboard')) {
            $this->enhanced_dashboard = new RP_Hub_Enhanced_Dashboard();
        }
    }
    
    public function render() {
        // Real stats from database
        $stats = $this->get_basic_stats();
        $recent_activity = $this->get_recent_activity();
        $health_overview = $this->get_health_overview();
        $task_performance = $this->get_task_performance();
        ?>
        <div class="wrap rphub-dashboard">
            <h1>Dashboard - Replanta Hub</h1>
            
            <!-- Stats Cards -->
            <div class="rphub-stats-grid">
                <div class="rphub-stat-card">
                    <div class="rphub-stat-icon">
                        <span class="dashicons dashicons-admin-site-alt3"></span>
                    </div>
                    <div class="rphub-stat-content">
                        <div class="rphub-stat-number"><?php echo esc_html($stats['total_sites']); ?></div>
                        <div class="rphub-stat-label">Total Sitios</div>
                    </div>
                </div>
                
                <div class="rphub-stat-card">
                    <div class="rphub-stat-icon active">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="rphub-stat-content">
                        <div class="rphub-stat-number"><?php echo esc_html($stats['active_sites']); ?></div>
                        <div class="rphub-stat-label">Sitios Activos</div>
                    </div>
                </div>
                
                <div class="rphub-stat-card">
                    <div class="rphub-stat-icon warning">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                    <div class="rphub-stat-content">
                        <div class="rphub-stat-number"><?php echo esc_html($stats['sites_with_issues']); ?></div>
                        <div class="rphub-stat-label">Con Problemas</div>
                    </div>
                </div>
                
                <div class="rphub-stat-card">
                    <div class="rphub-stat-icon">
                        <span class="dashicons dashicons-update"></span>
                    </div>
                    <div class="rphub-stat-content">
                        <div class="rphub-stat-number"><?php echo esc_html($stats['total_updates']); ?></div>
                        <div class="rphub-stat-label">Actualizaciones</div>
                    </div>
                </div>
                
                <div class="rphub-stat-card">
                    <div class="rphub-stat-icon <?php echo ($stats['total_security_issues'] > 0) ? 'error' : ''; ?>">
                        <span class="dashicons dashicons-shield"></span>
                    </div>
                    <div class="rphub-stat-content">
                        <div class="rphub-stat-number"><?php echo esc_html($stats['total_security_issues']); ?></div>
                        <div class="rphub-stat-label">Problemas Seguridad</div>
                    </div>
                </div>
                
                <div class="rphub-stat-card">
                    <div class="rphub-stat-icon">
                        <span class="dashicons dashicons-clock"></span>
                    </div>
                    <div class="rphub-stat-content">
                        <div class="rphub-stat-number"><?php echo esc_html($stats['pending_tasks']); ?></div>
                        <div class="rphub-stat-label">Tareas Pendientes</div>
                    </div>
                </div>
            </div>
            
            <!-- Main Content Grid -->
            <div class="rphub-dashboard-grid">
                <!-- Health Overview -->
                <div class="rphub-dashboard-widget">
                    <div class="rphub-widget-header">
                        <h3>Estado de Salud de Sitios</h3>
                    </div>
                    <div class="rphub-widget-content">
                        <div class="rphub-health-chart">
                            <canvas id="healthChart" width="300" height="200"></canvas>
                        </div>
                        
                        <?php if (!empty($health_overview['critical_sites'])): ?>
                        <div class="rphub-critical-sites">
                            <h4>Sitios que Requieren Atención</h4>
                            <ul class="rphub-sites-list">
                                <?php foreach (array_slice($health_overview['critical_sites'], 0, 5) as $site): ?>
                                <li class="rphub-site-item">
                                    <div class="rphub-site-info">
                                        <strong><?php echo esc_html($site->name); ?></strong>
                                        <span class="rphub-health-score" style="color: <?php echo RPHUB_Utils::get_health_score_color($site->health_score); ?>">
                                            <?php echo esc_html($site->health_score); ?>%
                                        </span>
                                    </div>
                                    <div class="rphub-site-issues">
                                        <?php if ($site->security_issues > 0): ?>
                                            <span class="rphub-issue security"><?php echo esc_html($site->security_issues); ?> seguridad</span>
                                        <?php endif; ?>
                                        <?php if ($site->updates_available > 0): ?>
                                            <span class="rphub-issue updates"><?php echo esc_html($site->updates_available); ?> actualizaciones</span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Task Performance -->
                <div class="rphub-dashboard-widget">
                    <div class="rphub-widget-header">
                        <h3>Rendimiento de Tareas (7 días)</h3>
                    </div>
                    <div class="rphub-widget-content">
                        <div class="rphub-performance-stats">
                            <div class="rphub-performance-item">
                                <div class="rphub-performance-label">Tasa de Éxito</div>
                                <div class="rphub-performance-value success">
                                    <?php echo esc_html(round($task_performance['success_rate'], 1)); ?>%
                                </div>
                            </div>
                            <div class="rphub-performance-item">
                                <div class="rphub-performance-label">Tareas Completadas</div>
                                <div class="rphub-performance-value">
                                    <?php echo esc_html($stats['completed_tasks_24h']); ?>
                                </div>
                            </div>
                            <div class="rphub-performance-item">
                                <div class="rphub-performance-label">Tareas Fallidas</div>
                                <div class="rphub-performance-value <?php echo ($stats['failed_tasks_24h'] > 0) ? 'error' : ''; ?>">
                                    <?php echo esc_html($stats['failed_tasks_24h']); ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($task_performance['tasks_by_type'])): ?>
                        <div class="rphub-tasks-by-type">
                            <h4>Tareas por Tipo</h4>
                            <ul class="rphub-task-types-list">
                                <?php foreach ($task_performance['tasks_by_type'] as $task_type): ?>
                                <li class="rphub-task-type-item">
                                    <span class="rphub-task-type-name"><?php echo esc_html(ucfirst(str_replace('_', ' ', $task_type->task_type ?? ''))); ?></span>
                                    <span class="rphub-task-type-count"><?php echo esc_html($task_type->count); ?></span>
                                    <span class="rphub-task-type-success">
                                        (<?php echo esc_html(RPHUB_Utils::calculate_percentage($task_type->completed ?? 0, $task_type->count)); ?>% éxito)
                                    </span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="rphub-dashboard-widget rphub-full-width">
                    <div class="rphub-widget-header">
                        <h3>Actividad Reciente</h3>
                    </div>
                    <div class="rphub-widget-content">
                        <?php if (!empty($recent_activity)): ?>
                        <ul class="rphub-activity-list">
                            <?php foreach ($recent_activity as $activity): ?>
                            <li class="rphub-activity-item">
                                <?php if ($activity['type'] === 'task'): ?>
                                    <div class="rphub-activity-icon task">
                                        <span class="dashicons dashicons-admin-tools"></span>
                                    </div>
                                    <div class="rphub-activity-content">
                                        <div class="rphub-activity-title">
                                            Tarea <strong><?php echo esc_html($activity['data']->task_type); ?></strong> 
                                            <?php echo esc_html($activity['data']->status === 'completed' ? 'completada' : 'falló'); ?>
                                        </div>
                                        <div class="rphub-activity-meta">
                                            Sitio: <?php echo esc_html($activity['data']->site_name); ?> • 
                                            <?php echo esc_html(RPHUB_Utils::time_ago($activity['timestamp'])); ?>
                                        </div>
                                    </div>
                                    <div class="rphub-activity-status <?php echo esc_attr($activity['data']->status); ?>">
                                        <?php echo esc_html(ucfirst($activity['data']->status)); ?>
                                    </div>
                                <?php else: ?>
                                    <div class="rphub-activity-icon notification">
                                        <span class="dashicons dashicons-bell"></span>
                                    </div>
                                    <div class="rphub-activity-content">
                                        <div class="rphub-activity-title">
                                            <?php echo esc_html($activity['data']->title); ?>
                                        </div>
                                        <div class="rphub-activity-meta">
                                            <?php if ($activity['data']->site_name): ?>
                                                Sitio: <?php echo esc_html($activity['data']->site_name); ?> • 
                                            <?php endif; ?>
                                            <?php echo esc_html(RPHUB_Utils::time_ago($activity['timestamp'])); ?>
                                        </div>
                                    </div>
                                    <div class="rphub-activity-severity <?php echo esc_attr($activity['data']->severity); ?>">
                                        <?php echo esc_html(ucfirst($activity['data']->severity)); ?>
                                    </div>
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <p class="rphub-no-data">No hay actividad reciente.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="rphub-dashboard-widget">
                    <div class="rphub-widget-header">
                        <h3>Acciones Rápidas</h3>
                    </div>
                    <div class="rphub-widget-content">
                        <div class="rphub-quick-actions">
                            <button class="rphub-btn rphub-btn-primary" onclick="rphubBulkAction('sync_data')">
                                <span class="dashicons dashicons-update"></span>
                                Sincronizar Todo
                            </button>
                            <button class="rphub-btn rphub-btn-secondary" onclick="rphubBulkAction('test_connection')">
                                <span class="dashicons dashicons-networking"></span>
                                Probar Conexiones
                            </button>
                            <button class="rphub-btn rphub-btn-secondary" onclick="rphubBulkAction('security_scan')">
                                <span class="dashicons dashicons-shield"></span>
                                Escaneo Seguridad
                            </button>
                            <button class="rphub-btn rphub-btn-secondary" onclick="rphubBulkAction('cache_clear')">
                                <span class="dashicons dashicons-performance"></span>
                                Limpiar Caché
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Notifications -->
                <div class="rphub-dashboard-widget">
                    <div class="rphub-widget-header">
                        <h3>Notificaciones</h3>
                        <span class="rphub-notification-count"><?php echo esc_html($stats['unread_notifications']); ?> sin leer</span>
                    </div>
                    <div class="rphub-widget-content">
                        <div id="rphub-notifications-widget">
                            <div class="rphub-loading">Cargando notificaciones...</div>
                        </div>
                        <div class="rphub-widget-actions">
                            <a href="<?php echo admin_url('admin.php?page=replanta-hub-notifications'); ?>" class="rphub-link">
                                Ver todas las notificaciones
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize health chart
            const healthCtx = document.getElementById('healthChart').getContext('2d');
            
            <?php if (!empty($health_overview['distribution'])): ?>
            const healthData = {
                labels: [
                    <?php foreach ($health_overview['distribution'] as $item): ?>
                    '<?php echo esc_js(ucfirst($item->status ?? '')); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    data: [
                        <?php foreach ($health_overview['distribution'] as $item): ?>
                        <?php echo esc_js($item->count); ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: ['#10b981', '#f59e0b', '#f97316', '#ef4444']
                }]
            };
            
            new Chart(healthCtx, {
                type: 'doughnut',
                data: healthData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            <?php endif; ?>
            
            // Load notifications
            loadNotificationsWidget();
            
            // Auto-refresh every 30 seconds
            setInterval(function() {
                loadNotificationsWidget();
            }, 30000);
        });
        
        function loadNotificationsWidget() {
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'rphub_get_notifications',
                    nonce: rphub_ajax.nonce,
                    limit: 5,
                    read_status: 0
                },
                success: function(data) {
                    const container = document.getElementById('rphub-notifications-widget');
                    
                    if (data.success && data.data.notifications.length > 0) {
                        let html = '<ul class="rphub-notifications-list">';
                        
                        data.data.notifications.forEach(notification => {
                            html += `
                                <li class="rphub-notification-item ${notification.severity}">
                                    <div class="rphub-notification-content">
                                        <div class="rphub-notification-title">${notification.title}</div>
                                        <div class="rphub-notification-meta">
                                            ${notification.site_name ? 'Sitio: ' + notification.site_name + ' • ' : ''}
                                            ${rphubTimeAgo(notification.created_at)}
                                        </div>
                                    </div>
                                </li>
                            `;
                        });
                        
                        html += '</ul>';
                        container.innerHTML = html;
                    } else {
                        container.innerHTML = '<p class="rphub-no-data">No hay notificaciones sin leer.</p>';
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading notifications:', error);
                    document.getElementById('rphub-notifications-widget').innerHTML = 
                        '<p class="rphub-error">Error al cargar notificaciones.</p>';
                }
            });
        }
        
        function rphubBulkAction(action) {
            if (!confirm('¿Ejecutar esta acción en todos los sitios activos?')) {
                return;
            }
            
            // Show loading state
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<span class="dashicons dashicons-update-alt rphub-spinning"></span> Ejecutando...';
            button.disabled = true;
            
            // Get all active site IDs (you'd need to implement this)
            jQuery.ajax({
                url: rphub_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rphub_bulk_action',
                    nonce: rphub_ajax.nonce,
                    bulk_action: action,
                    'site_ids[]': 'all_active'
                },
                success: function(data) {
                    if (data.success) {
                        alert(`Acción ejecutada: ${data.data.scheduled} tareas programadas`);
                    } else {
                        alert('Error: ' + data.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    alert('Error al ejecutar la acción');
                },
                complete: function() {
                    button.innerHTML = originalText;
                    button.disabled = false;
                }
            });
        }
        
        function rphubTimeAgo(datetime) {
            const now = new Date();
            const past = new Date(datetime);
            const diffInSeconds = Math.floor((now - past) / 1000);
            
            if (diffInSeconds < 60) return 'Hace ' + diffInSeconds + ' segundos';
            if (diffInSeconds < 3600) return 'Hace ' + Math.floor(diffInSeconds / 60) + ' minutos';
            if (diffInSeconds < 86400) return 'Hace ' + Math.floor(diffInSeconds / 3600) + ' horas';
            return 'Hace ' + Math.floor(diffInSeconds / 86400) + ' días';
        }
        </script>
        <?php
    }
    
    /**
     * Get recent activity from database
     */
    private function get_recent_activity() {
        global $wpdb;
        $activity = array();

        $table_activities = $wpdb->prefix . 'rphub_activities';
        $table_notifications = $wpdb->prefix . 'rphub_notifications';

        // Recent activities
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_activities))) {
            $rows = $wpdb->get_results(
                "SELECT a.*, s.name as site_name FROM {$table_activities} a
                 LEFT JOIN {$wpdb->prefix}rphub_sites s ON a.site_id = s.id
                 ORDER BY a.created_at DESC LIMIT 10"
            );
            foreach ($rows as $row) {
                $activity[] = array(
                    'type'      => 'task',
                    'timestamp' => $row->created_at,
                    'data'      => (object) array(
                        'task_type' => $row->action ?? $row->type ?? 'tarea',
                        'status'    => $row->status ?? 'completed',
                        'site_name' => $row->site_name ?? '',
                    ),
                );
            }
        }

        // Recent notifications (fill up to 10 total)
        $remaining = 10 - count($activity);
        if ($remaining > 0 && $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_notifications))) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT n.*, s.name as site_name FROM {$table_notifications} n
                 LEFT JOIN {$wpdb->prefix}rphub_sites s ON n.site_id = s.id
                 ORDER BY n.created_at DESC LIMIT %d",
                $remaining
            ));
            foreach ($rows as $row) {
                $activity[] = array(
                    'type'      => 'notification',
                    'timestamp' => $row->created_at,
                    'data'      => (object) array(
                        'title'     => $row->title ?? $row->message ?? '',
                        'severity'  => $row->severity ?? $row->type ?? 'info',
                        'site_name' => $row->site_name ?? '',
                    ),
                );
            }
        }

        // Sort combined activity by timestamp desc
        usort($activity, function ($a, $b) {
            return strtotime($b['timestamp'] ?? 0) - strtotime($a['timestamp'] ?? 0);
        });

        return array_slice($activity, 0, 10);
    }

    /**
     * Get health overview from database (sites with issues)
     */
    private function get_health_overview() {
        global $wpdb;
        $overview = array('critical_sites' => array(), 'distribution' => array());
        $table_sites = $wpdb->prefix . 'rphub_sites';

        if (!$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_sites))) {
            return $overview;
        }

        // Critical sites: those with status error/maintenance or low health
        $health_table = $wpdb->prefix . 'rphub_site_health';
        $has_health = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $health_table));

        if ($has_health) {
            $critical = $wpdb->get_results(
                "SELECT s.name, h.overall_score AS health_score, h.critical_issues AS security_issues, h.warning_issues AS updates_available
                 FROM {$health_table} h
                 JOIN {$table_sites} s ON h.site_id = s.id
                 WHERE h.overall_score < 70
                 ORDER BY h.overall_score ASC LIMIT 5"
            );
            if ($critical) {
                $overview['critical_sites'] = $critical;
            }
        } else {
            // Fallback: sites with problematic status
            $critical = $wpdb->get_results(
                "SELECT name, 0 as health_score, 0 as security_issues, 0 as updates_available
                 FROM {$table_sites}
                 WHERE status IN ('error', 'maintenance', 'inactive')
                 ORDER BY updated_at DESC LIMIT 5"
            );
            if ($critical) {
                $overview['critical_sites'] = $critical;
            }
        }

        // Distribution
        $dist = $wpdb->get_results(
            "SELECT COALESCE(status, 'unknown') as status, COUNT(*) as count FROM {$table_sites} GROUP BY status"
        );
        if ($dist) {
            $overview['distribution'] = $dist;
        }

        return $overview;
    }

    /**
     * Get task performance from database
     */
    private function get_task_performance() {
        global $wpdb;
        $perf = array('success_rate' => 0, 'tasks_by_type' => array());

        $table_tasks = $wpdb->prefix . 'rphub_automation_tasks';
        if (!$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_tasks))) {
            return $perf;
        }

        // Success rate (aggregate from run counts)
        $totals = $wpdb->get_row(
            "SELECT COALESCE(SUM(run_count),0) AS total_runs,
                    COALESCE(SUM(success_count),0) AS total_success,
                    COALESCE(SUM(failure_count),0) AS total_failed
             FROM {$table_tasks}"
        );
        $total_runs = (int) ($totals->total_runs ?? 0);
        $total_success = (int) ($totals->total_success ?? 0);
        $perf['success_rate'] = $total_runs > 0 ? round(($total_success / $total_runs) * 100, 1) : 0;

        // Tasks by type
        $by_type = $wpdb->get_results(
            "SELECT COALESCE(task_type, 'other') as task_type,
                    COUNT(*) as count,
                    COALESCE(SUM(success_count), 0) as completed
             FROM {$table_tasks}
             WHERE enabled = 1
             GROUP BY task_type
             ORDER BY count DESC LIMIT 10"
        );
        if ($by_type) {
            $perf['tasks_by_type'] = $by_type;
        }

        return $perf;
    }

    /**
     * Get basic dashboard statistics
     */
    private function get_basic_stats() {
        global $wpdb;
        
        $table_sites = $wpdb->prefix . 'rphub_sites';
        
        // Default stats structure
        $stats = array(
            'total_sites' => 0,
            'active_sites' => 0,
            'inactive_sites' => 0,
            'sites_with_issues' => 0,
            'total_updates' => 0,
            'total_security_issues' => 0,
            'pending_tasks' => 0,
            'completed_tasks_24h' => 0,
            'failed_tasks_24h' => 0,
            'total_revenue' => 0,
            'unread_notifications' => 0
        );
        
        // Check if tables exist before querying
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_sites
        ));
        
        if ($table_exists) {
            $stats['total_sites'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_sites}");
            $stats['active_sites'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_sites} WHERE status = 'active'");
            $stats['inactive_sites'] = $stats['total_sites'] - $stats['active_sites'];
            
            // Calculate additional metrics if available
            $issues_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_sites} WHERE status IN ('inactive', 'error', 'maintenance')");
            $stats['sites_with_issues'] = $issues_count;
            
            // Check for updates and security issues tables
            $updates_table = $wpdb->prefix . 'rphub_wptoolkit_updates';
            $vulns_table = $wpdb->prefix . 'rphub_wptoolkit_vulnerabilities';
            
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $updates_table))) {
                $stats['total_updates'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$updates_table}");
            }
            
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $vulns_table))) {
                $stats['total_security_issues'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$vulns_table}");
            }
        }
        
        // Task stats from automation_tasks table (uses enabled/run_count/success_count/failure_count, no status column)
        $tasks_table = $wpdb->prefix . 'rphub_automation_tasks';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tasks_table))) {
            $stats['pending_tasks'] = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$tasks_table} WHERE enabled = 1"
            );
            $stats['completed_tasks_24h'] = (int) $wpdb->get_var(
                "SELECT COALESCE(SUM(success_count),0) FROM {$tasks_table} WHERE last_run >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            );
            $stats['failed_tasks_24h'] = (int) $wpdb->get_var(
                "SELECT COALESCE(SUM(failure_count),0) FROM {$tasks_table} WHERE last_run >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            );
        }
        
        // Unread notifications
        $notif_table = $wpdb->prefix . 'rphub_notifications';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $notif_table))) {
            $stats['unread_notifications'] = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$notif_table} WHERE status = 'unread'"
            );
        }
        
        return $stats;
    }
}
