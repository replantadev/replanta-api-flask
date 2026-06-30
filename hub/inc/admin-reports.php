<?php
/**
 * Admin Reports Page
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPHUB_Admin_Reports {
    
    private $reports;
    
    public function __construct() {
        $this->reports = new RPHUB_Reports();
    }
    
    public function render() {
        ?>
        <div class="wrap rphub-reports">
            <h1 class="wp-heading-inline">Reportes</h1>
            <button class="page-title-action" onclick="rphubOpenGenerateReportModal()">Generar Reporte</button>
            
            <!-- Report Types -->
            <div class="rphub-report-types">
                <div class="rphub-report-type-card">
                    <div class="rphub-report-type-icon">
                        <span class="dashicons dashicons-chart-pie"></span>
                    </div>
                    <div class="rphub-report-type-content">
                        <h3>Reporte Resumen</h3>
                        <p>Vista general de todos los sitios, estadísticas de rendimiento y estado de salud.</p>
                        <button class="button button-primary" onclick="rphubGenerateReport('summary', 'monthly')">Generar Resumen</button>
                    </div>
                </div>
                
                <div class="rphub-report-type-card">
                    <div class="rphub-report-type-icon">
                        <span class="dashicons dashicons-admin-site-alt3"></span>
                    </div>
                    <div class="rphub-report-type-content">
                        <h3>Reportes por Sitio</h3>
                        <p>Reportes detallados de sitios individuales con métricas específicas.</p>
                        <button class="button" onclick="rphubOpenSiteReportModal()">Seleccionar Sitio</button>
                    </div>
                </div>
                
                <div class="rphub-report-type-card">
                    <div class="rphub-report-type-icon">
                        <span class="dashicons dashicons-shield"></span>
                    </div>
                    <div class="rphub-report-type-content">
                        <h3>Reporte de Seguridad</h3>
                        <p>Análisis de seguridad consolidado de todos los sitios monitorizados.</p>
                        <button class="button" onclick="rphubGenerateSecurityReport()">Generar Seguridad</button>
                    </div>
                </div>
                
                <div class="rphub-report-type-card">
                    <div class="rphub-report-type-icon">
                        <span class="dashicons dashicons-performance"></span>
                    </div>
                    <div class="rphub-report-type-content">
                        <h3>Reporte de Rendimiento</h3>
                        <p>Métricas de rendimiento y estadísticas de tareas ejecutadas.</p>
                        <button class="button" onclick="rphubGeneratePerformanceReport()">Generar Rendimiento</button>
                    </div>
                </div>
            </div>
            
            <!-- Recent Reports -->
            <div class="rphub-recent-reports">
                <h2>Reportes Recientes</h2>
                <div id="rphub-reports-list">
                    <div class="rphub-loading">Cargando reportes...</div>
                </div>
            </div>
        </div>
        
        <!-- Generate Report Modal -->
        <div id="rphub-generate-report-modal" class="rphub-modal" style="display: none;">
            <div class="rphub-modal-content">
                <div class="rphub-modal-header">
                    <h2>Generar Reporte</h2>
                    <button class="rphub-modal-close" onclick="rphubCloseGenerateReportModal()">&times;</button>
                </div>
                <form id="rphub-generate-report-form">
                    <div class="rphub-form-group">
                        <label for="report-type">Tipo de Reporte</label>
                        <select id="report-type" name="type" onchange="rphubReportTypeChanged()">
                            <option value="summary">Reporte Resumen</option>
                            <option value="site">Reporte por Sitio</option>
                        </select>
                    </div>
                    
                    <div class="rphub-form-group" id="site-selection" style="display: none;">
                        <label for="report-site">Seleccionar Sitio</label>
                        <select id="report-site" name="site_id">
                            <option value="">Cargando sitios...</option>
                        </select>
                    </div>
                    
                    <div class="rphub-form-group">
                        <label for="report-period">Período</label>
                        <select id="report-period" name="period">
                            <option value="daily">Último día</option>
                            <option value="weekly">Última semana</option>
                            <option value="monthly" selected>Último mes</option>
                            <option value="quarterly">Último trimestre</option>
                            <option value="yearly">Último año</option>
                        </select>
                    </div>
                    
                    <div class="rphub-form-group">
                        <label for="report-format">Formato</label>
                        <select id="report-format" name="format">
                            <option value="html">HTML</option>
                            <option value="pdf">PDF</option>
                            <option value="csv">CSV</option>
                            <option value="json">JSON</option>
                        </select>
                    </div>
                    
                    <div class="rphub-form-group">
                        <label>
                            <input type="checkbox" id="report-email" name="email_report" value="1">
                            Enviar por email al completar
                        </label>
                    </div>
                    
                    <div class="rphub-form-group" id="email-recipients" style="display: none;">
                        <label for="report-recipients">Destinatarios (separados por comas)</label>
                        <input type="email" id="report-recipients" name="recipients" placeholder="email1@ejemplo.com, email2@ejemplo.com" multiple>
                    </div>
                    
                    <div class="rphub-form-actions">
                        <button type="button" class="button" onclick="rphubCloseGenerateReportModal()">Cancelar</button>
                        <button type="submit" class="button button-primary" id="rphub-generate-report-btn">Generar Reporte</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Site Report Modal -->
        <div id="rphub-site-report-modal" class="rphub-modal" style="display: none;">
            <div class="rphub-modal-content">
                <div class="rphub-modal-header">
                    <h2>Reporte por Sitio</h2>
                    <button class="rphub-modal-close" onclick="rphubCloseSiteReportModal()">&times;</button>
                </div>
                <div class="rphub-sites-grid" id="rphub-sites-for-report">
                    <div class="rphub-loading">Cargando sitios...</div>
                </div>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Load recent reports
            loadRecentReports();
            
            // Load sites for dropdowns
            loadSitesForReports();
            
            // Form submission
            document.getElementById('rphub-generate-report-form').addEventListener('submit', function(e) {
                e.preventDefault();
                rphubSubmitGenerateReport();
            });
            
            // Email checkbox toggle
            document.getElementById('report-email').addEventListener('change', function() {
                document.getElementById('email-recipients').style.display = this.checked ? 'block' : 'none';
            });
        });
        
        function loadRecentReports() {
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=rphub_get_recent_reports&nonce=' + rphub_ajax.nonce
            })
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('rphub-reports-list');
                
                if (data.success && data.data.length > 0) {
                    let html = '<div class="rphub-reports-table-container">';
                    html += '<table class="wp-list-table widefat fixed striped">';
                    html += '<thead><tr><th>Reporte</th><th>Tipo</th><th>Período</th><th>Creado</th><th>Acciones</th></tr></thead>';
                    html += '<tbody>';
                    
                    data.data.forEach(report => {
                        html += `
                            <tr>
                                <td>
                                    <strong>${report.site_name || 'Reporte Resumen'}</strong>
                                    ${report.file_path ? '<br><small>' + report.file_path + '</small>' : ''}
                                </td>
                                <td>${report.report_type}</td>
                                <td>${report.period}</td>
                                <td>${rphubTimeAgo(report.created_at)}</td>
                                <td>
                                    <button class="button button-small" onclick="rphubDownloadReport(${report.id})">Descargar</button>
                                    <button class="button button-small" onclick="rphubEmailReport(${report.id})">Enviar</button>
                                    <button class="button button-small rphub-btn-danger" onclick="rphubDeleteReport(${report.id})">Eliminar</button>
                                </td>
                            </tr>
                        `;
                    });
                    
                    html += '</tbody></table></div>';
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<p class="rphub-no-data">No hay reportes generados aún.</p>';
                }
            })
            .catch(error => {
                console.error('Error loading reports:', error);
                document.getElementById('rphub-reports-list').innerHTML = '<p class="rphub-error">Error al cargar los reportes.</p>';
            });
        }
        
        function loadSitesForReports() {
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=rphub_get_sites_list&nonce=' + rphub_ajax.nonce
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update dropdown
                    const select = document.getElementById('report-site');
                    select.innerHTML = '<option value="">Seleccionar sitio...</option>';
                    
                    data.data.forEach(site => {
                        select.innerHTML += `<option value="${site.id}">${site.name} (${site.url})</option>`;
                    });
                    
                    // Update sites grid for modal
                    const grid = document.getElementById('rphub-sites-for-report');
                    let html = '';
                    
                    data.data.forEach(site => {
                        html += `
                            <div class="rphub-site-card" onclick="rphubGenerateSiteReport(${site.id})">
                                <div class="rphub-site-card-header">
                                    <h4>${site.name}</h4>
                                    <span class="rphub-site-card-plan">${site.plan}</span>
                                </div>
                                <div class="rphub-site-card-url">${site.url}</div>
                                <div class="rphub-site-card-health">
                                    Salud: <span style="color: ${rphubGetHealthColor(site.health_score)}">${site.health_score}%</span>
                                </div>
                            </div>
                        `;
                    });
                    
                    grid.innerHTML = html;
                }
            })
            .catch(error => {
                console.error('Error loading sites:', error);
            });
        }
        
        function rphubOpenGenerateReportModal() {
            document.getElementById('rphub-generate-report-modal').style.display = 'flex';
        }
        
        function rphubCloseGenerateReportModal() {
            document.getElementById('rphub-generate-report-modal').style.display = 'none';
        }
        
        function rphubOpenSiteReportModal() {
            document.getElementById('rphub-site-report-modal').style.display = 'flex';
        }
        
        function rphubCloseSiteReportModal() {
            document.getElementById('rphub-site-report-modal').style.display = 'none';
        }
        
        function rphubReportTypeChanged() {
            const type = document.getElementById('report-type').value;
            const siteSelection = document.getElementById('site-selection');
            
            if (type === 'site') {
                siteSelection.style.display = 'block';
            } else {
                siteSelection.style.display = 'none';
            }
        }
        
        function rphubSubmitGenerateReport() {
            const form = document.getElementById('rphub-generate-report-form');
            const formData = new FormData(form);
            const data = new URLSearchParams();
            
            data.append('action', 'rphub_generate_report');
            data.append('nonce', rphub_ajax.nonce);
            
            for (const [key, value] of formData.entries()) {
                data.append(key, value);
            }
            
            const btn = document.getElementById('rphub-generate-report-btn');
            const originalText = btn.textContent;
            btn.textContent = 'Generando...';
            btn.disabled = true;
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: data
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Reporte generado correctamente');
                    rphubCloseGenerateReportModal();
                    loadRecentReports();
                } else {
                    alert('Error: ' + data.data);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al generar el reporte');
            })
            .finally(() => {
                btn.textContent = originalText;
                btn.disabled = false;
            });
        }
        
        function rphubGenerateReport(type, period, format = 'html') {
            const data = new URLSearchParams();
            data.append('action', 'rphub_generate_report');
            data.append('nonce', rphub_ajax.nonce);
            data.append('type', type);
            data.append('period', period);
            data.append('format', format);
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: data
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Reporte generado correctamente');
                    loadRecentReports();
                } else {
                    alert('Error: ' + data.data);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al generar el reporte');
            });
        }
        
        function rphubGenerateSiteReport(siteId) {
            rphubCloseSiteReportModal();
            
            const data = new URLSearchParams();
            data.append('action', 'rphub_generate_report');
            data.append('nonce', rphub_ajax.nonce);
            data.append('type', 'site');
            data.append('site_id', siteId);
            data.append('period', 'monthly');
            data.append('format', 'html');
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: data
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Reporte generado correctamente');
                    loadRecentReports();
                } else {
                    alert('Error: ' + data.data);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al generar el reporte');
            });
        }
        
        function rphubGenerateSecurityReport() {
            rphubGenerateReport('security', 'monthly');
        }
        
        function rphubGeneratePerformanceReport() {
            rphubGenerateReport('performance', 'weekly');
        }
        
        function rphubDownloadReport(reportId) {
            window.open(ajaxurl + '?action=rphub_download_report&report_id=' + reportId + '&nonce=' + rphub_ajax.nonce, '_blank');
        }
        
        function rphubEmailReport(reportId) {
            const email = prompt('Introducir email de destino:');
            if (!email) return;
            
            const data = new URLSearchParams();
            data.append('action', 'rphub_email_report');
            data.append('nonce', rphub_ajax.nonce);
            data.append('report_id', reportId);
            data.append('recipients[]', email);
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: data
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Reporte enviado correctamente');
                } else {
                    alert('Error: ' + data.data);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al enviar el reporte');
            });
        }
        
        function rphubDeleteReport(reportId) {
            if (!confirm('¿Eliminar este reporte?')) return;
            
            const data = new URLSearchParams();
            data.append('action', 'rphub_delete_report');
            data.append('nonce', rphub_ajax.nonce);
            data.append('report_id', reportId);
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: data
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Reporte eliminado');
                    loadRecentReports();
                } else {
                    alert('Error: ' + data.data);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al eliminar el reporte');
            });
        }
        
        function rphubGetHealthColor(score) {
            if (score >= 90) return '#10b981';
            if (score >= 70) return '#f59e0b';
            if (score >= 50) return '#f97316';
            return '#ef4444';
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
        
        // Close modals when clicking outside
        document.getElementById('rphub-generate-report-modal').addEventListener('click', function(e) {
            if (e.target === this) rphubCloseGenerateReportModal();
        });
        
        document.getElementById('rphub-site-report-modal').addEventListener('click', function(e) {
            if (e.target === this) rphubCloseSiteReportModal();
        });
        </script>
        <?php
    }
}
