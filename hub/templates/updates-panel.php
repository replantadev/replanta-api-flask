<?php
/**
 * Template para el panel de actualizaciones inteligentes
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="rphub-updates-panel">
    <div class="panel-header">
        <h3>
            <span class="dashicons dashicons-update"></span>
            Actualizaciones Inteligentes
        </h3>
        <div class="panel-actions">
            <button class="button" id="check-updates" data-action="check-updates">
                <span class="dashicons dashicons-search"></span>
                Verificar Actualizaciones
            </button>
            <button class="button" id="configure-auto-updates">
                <span class="dashicons dashicons-admin-generic"></span>
                Configurar
            </button>
        </div>
    </div>

    <!-- Estado actual -->
    <div class="updates-status">
        <div class="status-grid">
            <div class="status-card">
                <div class="status-number"><?php echo $update_stats['total_updates']; ?></div>
                <div class="status-label">Total Actualizaciones</div>
            </div>
            <div class="status-card success">
                <div class="status-number"><?php echo $update_stats['successful_updates']; ?></div>
                <div class="status-label">Exitosas</div>
            </div>
            <div class="status-card error">
                <div class="status-number"><?php echo $update_stats['failed_updates']; ?></div>
                <div class="status-label">Fallidas</div>
            </div>
            <div class="status-card warning">
                <div class="status-number"><?php echo $update_stats['rollbacks']; ?></div>
                <div class="status-label">Rollbacks</div>
            </div>
        </div>
        
        <?php if ($update_stats['last_update']): ?>
        <div class="last-update">
            <span class="dashicons dashicons-clock"></span>
            Última actualización: <?php echo date('d/m/Y H:i', strtotime($update_stats['last_update'])); ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Configuración de actualizaciones automáticas -->
    <div class="auto-updates-config">
        <h4>Configuración Automática</h4>
        <div class="config-status">
            <?php if ($auto_config && $auto_config['enabled']): ?>
                <span class="status-badge enabled">
                    <span class="dashicons dashicons-yes-alt"></span>
                    Actualizaciones automáticas habilitadas
                </span>
            <?php else: ?>
                <span class="status-badge disabled">
                    <span class="dashicons dashicons-dismiss"></span>
                    Actualizaciones automáticas deshabilitadas
                </span>
            <?php endif; ?>
        </div>
        
        <?php if ($auto_config && $auto_config['enabled']): ?>
        <div class="config-details">
            <ul>
                <?php if ($auto_config['core']['enabled']): ?>
                <li><span class="dashicons dashicons-yes"></span> WordPress Core</li>
                <?php endif; ?>
                
                <?php if ($auto_config['plugins']['enabled']): ?>
                <li><span class="dashicons dashicons-yes"></span> Plugins</li>
                <?php endif; ?>
                
                <?php if ($auto_config['themes']['enabled']): ?>
                <li><span class="dashicons dashicons-yes"></span> Themes</li>
                <?php endif; ?>
            </ul>
            
            <?php if (isset($auto_config['schedule'])): ?>
            <div class="schedule-info">
                <strong>Horario:</strong> 
                <?php echo $auto_config['schedule']['start_time']; ?> - <?php echo $auto_config['schedule']['end_time']; ?>
                <br>
                <strong>Días:</strong> 
                <?php 
                $days = array('Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb');
                $scheduled_days = array();
                foreach ($auto_config['schedule']['days'] as $day) {
                    $scheduled_days[] = $days[$day];
                }
                echo implode(', ', $scheduled_days);
                ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Actualizaciones pendientes -->
    <?php if ($pending_updates && !empty($pending_updates['updates'])): ?>
    <div class="pending-updates">
        <h4>Actualizaciones Pendientes</h4>
        
        <?php foreach ($pending_updates['updates'] as $type => $items): ?>
        <div class="update-type-section">
            <h5><?php echo ucfirst($type); ?> (<?php echo count($items); ?>)</h5>
            
            <div class="update-items">
                <?php foreach ($items as $item): ?>
                <div class="update-item">
                    <div class="item-info">
                        <strong><?php echo esc_html($item['name']); ?></strong>
                        <?php if (isset($item['current_version']) && isset($item['new_version'])): ?>
                        <span class="version-info">
                            <?php echo $item['current_version']; ?> → <?php echo $item['new_version']; ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="item-actions">
                        <button class="button button-small schedule-update" 
                                data-type="<?php echo $type; ?>" 
                                data-item="<?php echo esc_attr(json_encode($item)); ?>">
                            Programar
                        </button>
                        
                        <?php if (!$auto_config || !$auto_config['enabled']): ?>
                        <button class="button button-primary button-small update-now" 
                                data-type="<?php echo $type; ?>" 
                                data-item="<?php echo esc_attr(json_encode($item)); ?>">
                            Actualizar Ahora
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <div class="bulk-actions">
            <button class="button button-primary" id="schedule-all-updates">
                <span class="dashicons dashicons-calendar-alt"></span>
                Programar Todas
            </button>
            
            <?php if (!$auto_config || !$auto_config['enabled']): ?>
            <button class="button button-secondary" id="update-all-now">
                <span class="dashicons dashicons-update"></span>
                Actualizar Todo Ahora
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Historial de actualizaciones -->
    <div class="update-history">
        <h4>Historial Reciente</h4>
        <div class="history-container" id="update-history-list">
            <div class="loading">Cargando historial...</div>
        </div>
        <button class="button" id="load-more-history">Ver Más</button>
    </div>
</div>

<!-- Modal de configuración -->
<div id="auto-updates-modal" class="rphub-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Configurar Actualizaciones Automáticas</h3>
            <button class="modal-close">&times;</button>
        </div>
        
        <div class="modal-body">
            <form id="auto-updates-form">
                <div class="form-section">
                    <label>
                        <input type="checkbox" id="enable-auto-updates" <?php echo ($auto_config && $auto_config['enabled']) ? 'checked' : ''; ?>>
                        Habilitar actualizaciones automáticas
                    </label>
                </div>
                
                <div class="form-section update-types">
                    <h4>Tipos de Actualizaciones</h4>
                    
                    <div class="update-type">
                        <label>
                            <input type="checkbox" name="core_enabled" <?php echo ($auto_config && $auto_config['core']['enabled']) ? 'checked' : ''; ?>>
                            WordPress Core
                        </label>
                        <div class="sub-options">
                            <label>
                                <input type="checkbox" name="core_major" <?php echo ($auto_config && $auto_config['core']['major_versions']) ? 'checked' : ''; ?>>
                                Incluir versiones mayores
                            </label>
                        </div>
                    </div>
                    
                    <div class="update-type">
                        <label>
                            <input type="checkbox" name="plugins_enabled" <?php echo ($auto_config && $auto_config['plugins']['enabled']) ? 'checked' : ''; ?>>
                            Plugins
                        </label>
                    </div>
                    
                    <div class="update-type">
                        <label>
                            <input type="checkbox" name="themes_enabled" <?php echo ($auto_config && $auto_config['themes']['enabled']) ? 'checked' : ''; ?>>
                            Themes
                        </label>
                    </div>
                </div>
                
                <div class="form-section schedule-config">
                    <h4>Programación</h4>
                    
                    <div class="schedule-time">
                        <label>Horario de actualizaciones:</label>
                        <input type="time" name="start_time" value="<?php echo isset($auto_config['schedule']['start_time']) ? $auto_config['schedule']['start_time'] : '02:00'; ?>">
                        <span>a</span>
                        <input type="time" name="end_time" value="<?php echo isset($auto_config['schedule']['end_time']) ? $auto_config['schedule']['end_time'] : '06:00'; ?>">
                    </div>
                    
                    <div class="schedule-days">
                        <label>Días de la semana:</label>
                        <div class="days-grid">
                            <?php 
                            $days = array('Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado');
                            $selected_days = isset($auto_config['schedule']['days']) ? $auto_config['schedule']['days'] : array(0, 6);
                            
                            for ($i = 0; $i < 7; $i++): ?>
                            <label>
                                <input type="checkbox" name="schedule_days[]" value="<?php echo $i; ?>" <?php echo in_array($i, $selected_days) ? 'checked' : ''; ?>>
                                <?php echo $days[$i]; ?>
                            </label>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
                
                <div class="form-section safety-options">
                    <h4>Opciones de Seguridad</h4>
                    
                    <label>
                        <input type="checkbox" name="backup_before_update" <?php echo ($auto_config && $auto_config['backup_before_update']) ? 'checked' : ''; ?>>
                        Crear backup antes de cada actualización
                    </label>
                    
                    <label>
                        <input type="checkbox" name="rollback_on_failure" <?php echo ($auto_config && $auto_config['rollback_on_failure']) ? 'checked' : ''; ?>>
                        Rollback automático en caso de fallo
                    </label>
                </div>
            </form>
        </div>
        
        <div class="modal-footer">
            <button class="button button-primary" id="save-auto-updates-config">Guardar Configuración</button>
            <button class="button" id="cancel-auto-updates-config">Cancelar</button>
        </div>
    </div>
</div>

<!-- Modal de programación -->
<div id="schedule-update-modal" class="rphub-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Programar Actualización</h3>
            <button class="modal-close">&times;</button>
        </div>
        
        <div class="modal-body">
            <form id="schedule-update-form">
                <div class="form-section">
                    <label>Fecha y Hora:</label>
                    <input type="datetime-local" name="schedule_datetime" required min="<?php echo date('Y-m-d\TH:i'); ?>">
                </div>
                
                <div class="form-section">
                    <label>
                        <input type="checkbox" name="create_backup" checked>
                        Crear backup antes de la actualización
                    </label>
                </div>
                
                <div class="selected-updates" id="selected-updates-list">
                    <!-- Se llena dinámicamente -->
                </div>
            </form>
        </div>
        
        <div class="modal-footer">
            <button class="button button-primary" id="confirm-schedule-update">Programar</button>
            <button class="button" id="cancel-schedule-update">Cancelar</button>
        </div>
    </div>
</div>

<style>
.rphub-updates-panel {
    background: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e5e7eb;
}

.panel-header h3 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #374151;
}

.panel-actions {
    display: flex;
    gap: 10px;
}

.status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}

.status-card {
    text-align: center;
    padding: 15px;
    border-radius: 6px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
}

.status-card.success {
    background: #f0fdf4;
    border-color: #bbf7d0;
}

.status-card.error {
    background: #fef2f2;
    border-color: #fecaca;
}

.status-card.warning {
    background: #fffbeb;
    border-color: #fed7aa;
}

.status-number {
    font-size: 24px;
    font-weight: bold;
    color: #374151;
    margin-bottom: 5px;
}

.status-label {
    font-size: 12px;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
}

.status-badge.enabled {
    background: #d1fae5;
    color: #065f46;
}

.status-badge.disabled {
    background: #fee2e2;
    color: #991b1b;
}

.update-type-section {
    margin-bottom: 20px;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 15px;
}

.update-type-section h5 {
    margin: 0 0 10px 0;
    color: #374151;
}

.update-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f3f4f6;
}

.update-item:last-child {
    border-bottom: none;
}

.version-info {
    font-size: 12px;
    color: #6b7280;
    margin-left: 10px;
}

.item-actions {
    display: flex;
    gap: 8px;
}

.bulk-actions {
    text-align: center;
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #e5e7eb;
}

.rphub-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    border-radius: 8px;
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #6b7280;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 20px;
    border-top: 1px solid #e5e7eb;
    text-align: right;
}

.form-section {
    margin-bottom: 20px;
}

.form-section h4 {
    margin: 0 0 10px 0;
    color: #374151;
}

.update-type {
    margin-bottom: 15px;
}

.sub-options {
    margin-left: 25px;
    margin-top: 5px;
}

.schedule-time {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
}

.days-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px;
}

.history-container {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    padding: 10px;
    margin-bottom: 10px;
}

.last-update {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #6b7280;
    font-size: 14px;
}
</style>
