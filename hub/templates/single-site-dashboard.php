<?php
/**
 * Template para mostrar dashboard individual de sitio
 *
 * @deprecated CPT rphub_site has been removed. This template now uses RPHUB_Database.
 *             Pass $site_id via query-var or $_GET['site_id'].
 */

if (!defined('ABSPATH')) {
    exit;
}

// Resolve site_id: accept query-var, GET param, or legacy $post->ID
$site_id = get_query_var('site_id', isset($_GET['site_id']) ? absint($_GET['site_id']) : 0);
if (!$site_id && isset($post) && is_a($post, 'WP_Post')) {
    // Legacy fallback for any remaining CPT routing
    $site_id = $post->ID;
}

$site = $site_id ? RPHUB_Database::get_site($site_id) : null;
if (!$site) {
    echo '<div class="notice notice-error"><p>' . esc_html__('Sitio no encontrado.', 'replanta-hub') . '</p></div>';
    return;
}

// Obtener datos del sitio desde la tabla personalizada
$site_url = $site->url;
$site_token = $site->token;
$last_connection = RPHUB_Database::get_site_meta($site_id, 'last_connection');
$connection_status = $site->status;

// Datos técnicos
$wp_version = RPHUB_Database::get_site_meta($site_id, 'wp_version');
$php_version = RPHUB_Database::get_site_meta($site_id, 'php_version');
$mysql_version = RPHUB_Database::get_site_meta($site_id, 'mysql_version');

// Rendimiento
$pagespeed_mobile = RPHUB_Database::get_site_meta($site_id, 'pagespeed_mobile');
$pagespeed_desktop = RPHUB_Database::get_site_meta($site_id, 'pagespeed_desktop');
$core_web_vitals = RPHUB_Database::get_site_meta($site_id, 'core_web_vitals');

// Seguridad
$security_score = $site->security_score;
$vulnerabilities = RPHUB_Database::get_site_meta($site_id, 'vulnerabilities');

// Plan y estado (from custom table columns)
$site_plan = $site->plan ? array($site->plan) : array();
$site_status = $site->status ? array($site->status) : array();

?>

<div id="rphub-site-dashboard" class="rphub-dashboard-container">
    
    <!-- Hero Section -->
    <div class="rphub-hero-section">
        <div class="site-header">
            <div class="site-info">
                <h1 class="site-title"><?php echo esc_html($site->name); ?></h1>
                <div class="site-url">
                    <a href="<?php echo esc_url($site_url); ?>" target="_blank" rel="noopener">
                        <?php echo esc_html($site_url); ?>
                        <span class="external-link">↗</span>
                    </a>
                </div>
                <div class="site-meta">
                    <?php if (!empty($site_plan)): ?>
                        <span class="plan-badge plan-<?php echo esc_attr(strtolower($site_plan[0])); ?>">
                            <?php echo esc_html($site_plan[0]); ?>
                        </span>
                    <?php endif; ?>
                    
                    <?php if (!empty($site_status)): ?>
                        <span class="status-badge status-<?php echo esc_attr(strtolower($site_status[0])); ?>">
                            <?php echo esc_html($site_status[0]); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="connection-info">
                <div class="connection-status">
                    <span class="status-indicator <?php echo esc_attr($connection_status); ?>"></span>
                    <span class="status-text">
                        <?php 
                        switch($connection_status) {
                            case 'connected':
                                _e('Conectado', 'replanta-hub');
                                break;
                            case 'error':
                                _e('Error de conexión', 'replanta-hub');
                                break;
                            default:
                                _e('Sin conexión', 'replanta-hub');
                        }
                        ?>
                    </span>
                </div>
                <div class="last-check">
                    <small>
                        <?php _e('Última verificación:', 'replanta-hub'); ?>
                        <?php echo $last_connection ? date('d/m/Y H:i', strtotime($last_connection)) : __('Nunca', 'replanta-hub'); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Health Score Section -->
    <div class="rphub-health-section">
        <h2><?php _e('Salud del Sitio', 'replanta-hub'); ?></h2>
        
        <div class="health-cards">
            <!-- Performance Card -->
            <div class="health-card performance">
                <div class="card-header">
                    <h3><?php _e('Rendimiento', 'replanta-hub'); ?></h3>
                    <div class="score-circle">
                        <div class="score-value">
                            <?php 
                            $avg_score = ($pagespeed_mobile && $pagespeed_desktop) ? 
                                round(($pagespeed_mobile + $pagespeed_desktop) / 2) : 
                                '--';
                            echo $avg_score;
                            ?>
                        </div>
                    </div>
                </div>
                <div class="card-content">
                    <div class="metric">
                        <span class="label"><?php _e('Móvil:', 'replanta-hub'); ?></span>
                        <span class="value"><?php echo $pagespeed_mobile ?: '--'; ?></span>
                    </div>
                    <div class="metric">
                        <span class="label"><?php _e('Desktop:', 'replanta-hub'); ?></span>
                        <span class="value"><?php echo $pagespeed_desktop ?: '--'; ?></span>
                    </div>
                </div>
            </div>

            <!-- Security Card -->
            <div class="health-card security">
                <div class="card-header">
                    <h3><?php _e('Seguridad', 'replanta-hub'); ?></h3>
                    <div class="score-circle">
                        <div class="score-value">
                            <?php echo $security_score ?: '--'; ?>
                        </div>
                    </div>
                </div>
                <div class="card-content">
                    <div class="metric">
                        <span class="label"><?php _e('Vulnerabilidades:', 'replanta-hub'); ?></span>
                        <span class="value alert">
                            <?php 
                            $vuln_count = $vulnerabilities ? count(explode("\n", $vulnerabilities)) : 0;
                            echo $vuln_count;
                            ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Technical Card -->
            <div class="health-card technical">
                <div class="card-header">
                    <h3><?php _e('Técnico', 'replanta-hub'); ?></h3>
                    <div class="status-indicator <?php echo $wp_version ? 'good' : 'unknown'; ?>"></div>
                </div>
                <div class="card-content">
                    <div class="metric">
                        <span class="label"><?php _e('WordPress:', 'replanta-hub'); ?></span>
                        <span class="value"><?php echo $wp_version ?: '--'; ?></span>
                    </div>
                    <div class="metric">
                        <span class="label"><?php _e('PHP:', 'replanta-hub'); ?></span>
                        <span class="value"><?php echo $php_version ?: '--'; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="rphub-actions-section">
        <h2><?php _e('Acciones Rápidas', 'replanta-hub'); ?></h2>
        
        <div class="action-buttons">
            <button class="rphub-btn primary" data-action="refresh-data">
                <span class="icon">🔄</span>
                <?php _e('Actualizar Datos', 'replanta-hub'); ?>
            </button>
            
            <button class="rphub-btn secondary" data-action="test-connection">
                <span class="icon">🔗</span>
                <?php _e('Probar Conexión', 'replanta-hub'); ?>
            </button>
            
            <button class="rphub-btn secondary" data-action="run-pagespeed">
                <span class="icon">⚡</span>
                <?php _e('PageSpeed Test', 'replanta-hub'); ?>
            </button>
            
            <button class="rphub-btn secondary" data-action="security-scan">
                <span class="icon">🛡️</span>
                <?php _e('Escaneo Seguridad', 'replanta-hub'); ?>
            </button>
        </div>
    </div>

    <!-- Detailed Sections -->
    <div class="rphub-details-section">
        <div class="details-tabs">
            <button class="tab-button active" data-tab="performance"><?php _e('Rendimiento', 'replanta-hub'); ?></button>
            <button class="tab-button" data-tab="security"><?php _e('Seguridad', 'replanta-hub'); ?></button>
            <button class="tab-button" data-tab="technical"><?php _e('Técnico', 'replanta-hub'); ?></button>
            <button class="tab-button" data-tab="activity"><?php _e('Actividad', 'replanta-hub'); ?></button>
        </div>

        <!-- Performance Tab -->
        <div class="tab-content active" id="performance-tab">
            <div class="tab-inner">
                <h3><?php _e('Métricas de Rendimiento', 'replanta-hub'); ?></h3>
                
                <?php if ($core_web_vitals): ?>
                    <div class="core-vitals">
                        <h4><?php _e('Core Web Vitals', 'replanta-hub'); ?></h4>
                        <pre><?php echo esc_html($core_web_vitals); ?></pre>
                    </div>
                <?php endif; ?>
                
                <div class="pagespeed-details">
                    <div class="device-score">
                        <h4><?php _e('Puntuaciones PageSpeed', 'replanta-hub'); ?></h4>
                        <div class="score-bars">
                            <div class="score-bar">
                                <label><?php _e('Móvil', 'replanta-hub'); ?></label>
                                <div class="bar">
                                    <div class="fill" style="width: <?php echo $pagespeed_mobile ?: 0; ?>%"></div>
                                </div>
                                <span><?php echo $pagespeed_mobile ?: '--'; ?></span>
                            </div>
                            <div class="score-bar">
                                <label><?php _e('Desktop', 'replanta-hub'); ?></label>
                                <div class="bar">
                                    <div class="fill" style="width: <?php echo $pagespeed_desktop ?: 0; ?>%"></div>
                                </div>
                                <span><?php echo $pagespeed_desktop ?: '--'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Security Tab -->
        <div class="tab-content" id="security-tab">
            <div class="tab-inner">
                <h3><?php _e('Estado de Seguridad', 'replanta-hub'); ?></h3>
                
                <?php if ($vulnerabilities): ?>
                    <div class="vulnerabilities">
                        <h4><?php _e('Vulnerabilidades Detectadas', 'replanta-hub'); ?></h4>
                        <div class="vulnerability-list">
                            <?php
                            $vuln_lines = explode("\n", $vulnerabilities);
                            foreach ($vuln_lines as $vuln) {
                                if (trim($vuln)) {
                                    echo '<div class="vulnerability-item">' . esc_html($vuln) . '</div>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-vulnerabilities">
                        <p><?php _e('No se han detectado vulnerabilidades.', 'replanta-hub'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Technical Tab -->
        <div class="tab-content" id="technical-tab">
            <div class="tab-inner">
                <h3><?php _e('Información Técnica', 'replanta-hub'); ?></h3>
                
                <div class="tech-info">
                    <div class="info-group">
                        <h4><?php _e('Versiones', 'replanta-hub'); ?></h4>
                        <ul>
                            <li><strong><?php _e('WordPress:', 'replanta-hub'); ?></strong> <?php echo $wp_version ?: __('No disponible', 'replanta-hub'); ?></li>
                            <li><strong><?php _e('PHP:', 'replanta-hub'); ?></strong> <?php echo $php_version ?: __('No disponible', 'replanta-hub'); ?></li>
                            <li><strong><?php _e('MySQL:', 'replanta-hub'); ?></strong> <?php echo $mysql_version ?: __('No disponible', 'replanta-hub'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Tab -->
        <div class="tab-content" id="activity-tab">
            <div class="tab-inner">
                <h3><?php _e('Timeline de Actividad', 'replanta-hub'); ?></h3>
                
                <div class="activity-timeline">
                    <div class="timeline-item">
                        <div class="timeline-icon">🔄</div>
                        <div class="timeline-content">
                            <h4><?php _e('Última conexión', 'replanta-hub'); ?></h4>
                            <p><?php echo $last_connection ? date('d/m/Y H:i:s', strtotime($last_connection)) : __('Nunca', 'replanta-hub'); ?></p>
                        </div>
                    </div>
                    
                    <!-- Más items del timeline se añadirán dinámicamente -->
                    <div class="no-activity">
                        <p><?php _e('No hay actividad reciente disponible.', 'replanta-hub'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="rphub-loading" class="loading-overlay" style="display: none;">
        <div class="loading-spinner"></div>
        <p><?php _e('Cargando...', 'replanta-hub'); ?></p>
    </div>

</div>

<script>
// JavaScript básico para tabs y acciones
jQuery(document).ready(function($) {
    // Manejo de tabs
    $('.tab-button').on('click', function() {
        var tab = $(this).data('tab');
        
        $('.tab-button').removeClass('active');
        $('.tab-content').removeClass('active');
        
        $(this).addClass('active');
        $('#' + tab + '-tab').addClass('active');
    });
    
    // Manejo de acciones
    $('.rphub-btn[data-action]').on('click', function() {
        var action = $(this).data('action');
        var siteId = <?php echo (int) $site_id; ?>;
        
        $('#rphub-loading').show();
        
        $.ajax({
            url: rphub_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'rphub_site_action',
                site_action: action,
                site_id: siteId,
                nonce: rphub_ajax.nonce
            },
            success: function(response) {
                $('#rphub-loading').hide();
                if (response.success) {
                    // Recargar la página para mostrar datos actualizados
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                $('#rphub-loading').hide();
                alert('Error de conexión');
            }
        });
    });
});
</script>
