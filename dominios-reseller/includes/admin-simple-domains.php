<?php
/**
 * Página de administración simplificada de dominios
 * 
 * Tabla limpia con:
 * - Dominios principales (sin addons inicialmente)
 * - Servidor (UK/USA)
 * - Árboles plantados (editable)
 * - CO2 evitado (calculado)
 * - Botón calcular por dominio
 * - Guardado automático en base de datos
 */

// Seguridad
if (!defined('ABSPATH')) exit;

/**
 * Renderiza la página de administración simplificada
 */
function dominios_reseller_render_simple_page() {
    global $wpdb;
    
    // Procesar actualización de árboles si viene del formulario
    if (isset($_POST['update_trees_nonce']) && wp_verify_nonce($_POST['update_trees_nonce'], 'update_trees_action')) {
        dominios_reseller_save_trees_data($_POST);
    }
    
    // Obtener dominios principales de la base de datos
    $table = $wpdb->prefix . 'dominios_reseller';
    
    // Debug: contar todos los dominios
    $total_domains = $wpdb->get_var("SELECT COUNT(*) FROM $table");
    $primary_domains = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE is_primary = 1");
    $addon_domains = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE is_primary = 0");
    
    $domains = $wpdb->get_results("
        SELECT id, domain, server, trees_planted, co2_evaded, startdate, fecha_emision, status, last_sync, is_primary, primary_domain
        FROM $table
        WHERE is_primary = 1
        ORDER BY domain ASC
    ");
    
    ?>
    <div class="wrap">
        <h1>🌳 Dominios y Emisiones CO2 - Gestión Simplificada</h1>
        
        <div class="notice notice-info" style="padding: 12px; margin: 15px 0;">
            <p><strong>📊 Estado de la base de datos:</strong></p>
            <ul style="margin: 5px 0 0 20px;">
                <li>Total de dominios: <strong><?php echo number_format($total_domains); ?></strong></li>
                <li>Dominios principales (mostrados aquí): <strong><?php echo number_format($primary_domains); ?></strong></li>
                <li>Dominios addon (ocultos): <strong><?php echo number_format($addon_domains); ?></strong></li>
            </ul>
            <p style="margin: 10px 0 0 0; color: #666;"><em>💡 Esta tabla muestra solo los dominios principales de cada cuenta cPanel. Los addons se calculan automáticamente al 20% del principal.</em></p>
        </div>
        
        <div class="card" style="max-width: none; margin-top: 20px;">
            <h2>📋 Tabla de Dominios</h2>
            <p>Gestiona los árboles plantados y calcula las emisiones de CO2 por dominio.</p>
            
            <form method="post" action="" id="domains-form">
                <?php wp_nonce_field('update_trees_action', 'update_trees_nonce'); ?>
                
                <div style="margin-bottom: 15px;">
                    <button type="button" class="button button-primary" id="calculate-all-btn">
                        🔄 Calcular CO2 de Todos
                    </button>
                    <button type="submit" class="button button-primary">
                        💾 Guardar Cambios
                    </button>
                    <span id="status-message" style="margin-left: 15px; font-weight: bold;"></span>
                </div>
                
                <table class="wp-list-table widefat fixed striped" id="domains-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;">#</th>
                            <th style="width: 250px;">Dominio</th>
                            <th style="width: 80px;">Servidor</th>
                            <th style="width: 100px;">Días Activo</th>
                            <th style="width: 120px;">🌳 Árboles</th>
                            <th style="width: 120px;">💨 CO2 (kg)</th>
                            <th style="width: 120px;">Estado</th>
                            <th style="width: 150px;">Última Sincro</th>
                            <th style="width: 150px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($domains)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 40px;">
                                    <strong>No hay dominios.</strong>
                                    <br>Sincroniza desde WHM primero.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $counter = 1; ?>
                            <?php foreach ($domains as $domain): ?>
                                <?php
                                // Calcular días activo
                                $dias_activo = 0;
                                if ($domain->startdate && $domain->startdate > 0) {
                                    $dias_activo = floor((time() - $domain->startdate) / 86400);
                                } elseif ($domain->fecha_emision) {
                                    $fecha_inicio = strtotime($domain->fecha_emision);
                                    if ($fecha_inicio) {
                                        $dias_activo = floor((time() - $fecha_inicio) / 86400);
                                    }
                                }
                                
                                // Color por servidor
                                $server_color = strtoupper($domain->server) === 'USA' ? '#28a745' : '#007cba';
                                $status_color = $domain->status === 'Activo' ? '#28a745' : '#dc3545';
                                ?>
                                <tr data-domain-id="<?php echo esc_attr($domain->id); ?>" data-domain="<?php echo esc_attr($domain->domain); ?>">
                                    <td><?php echo $counter++; ?></td>
                                    <td>
                                        <strong><?php echo esc_html($domain->domain); ?></strong>
                                    </td>
                                    <td>
                                        <span style="background: <?php echo $server_color; ?>; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600;">
                                            <?php echo strtoupper(esc_html($domain->server)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo number_format($dias_activo); ?> días
                                    </td>
                                    <td>
                                        <input 
                                            type="number" 
                                            name="trees[<?php echo $domain->id; ?>]" 
                                            value="<?php echo esc_attr($domain->trees_planted ?? 0); ?>"
                                            min="0"
                                            step="1"
                                            style="width: 80px; text-align: right;"
                                            class="trees-input"
                                        />
                                    </td>
                                    <td class="co2-cell">
                                        <span class="co2-value">
                                            <?php 
                                            if ($domain->co2_evaded && $domain->co2_evaded > 0) {
                                                // Convertir gramos a kg
                                                echo number_format($domain->co2_evaded / 1000, 3) . ' kg';
                                            } else {
                                                echo '—';
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="background: <?php echo $status_color; ?>; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600;">
                                            <?php echo esc_html($domain->status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($domain->last_sync) {
                                            echo esc_html(date('Y-m-d H:i', strtotime($domain->last_sync)));
                                        } else {
                                            echo '—';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <button 
                                            type="button" 
                                            class="button button-small calculate-co2-btn"
                                            data-domain-id="<?php echo esc_attr($domain->id); ?>"
                                            data-domain="<?php echo esc_attr($domain->domain); ?>"
                                            data-server="<?php echo esc_attr($domain->server); ?>"
                                        >
                                            🔄 Calcular
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <div style="margin-top: 15px;">
                    <button type="submit" class="button button-primary">
                        💾 Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Estadísticas globales -->
        <div class="card" style="max-width: none; margin-top: 20px;">
            <h2>📊 Estadísticas Globales</h2>
            <?php
            $stats = $wpdb->get_row("
                SELECT 
                    COUNT(*) as total_domains,
                    SUM(trees_planted) as total_trees,
                    SUM(co2_evaded) as total_co2,
                    SUM(CASE WHEN server = 'UK' THEN 1 ELSE 0 END) as uk_count,
                    SUM(CASE WHEN server = 'USA' THEN 1 ELSE 0 END) as usa_count
                FROM $table
                WHERE is_primary = 1 AND status = 'Activo'
            ");
            ?>
            <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 20px; margin-top: 15px;">
                <div style="text-align: center; padding: 20px; background: #f0f0f1; border-radius: 6px;">
                    <div style="font-size: 32px; font-weight: bold; color: #333;">
                        <?php echo number_format($stats->total_domains ?? 0); ?>
                    </div>
                    <div style="color: #666; margin-top: 5px;">Dominios Activos</div>
                </div>
                <div style="text-align: center; padding: 20px; background: #f0f0f1; border-radius: 6px;">
                    <div style="font-size: 32px; font-weight: bold; color: #28a745;">
                        <?php echo number_format($stats->total_trees ?? 0); ?>
                    </div>
                    <div style="color: #666; margin-top: 5px;">🌳 Árboles Plantados</div>
                </div>
                <div style="text-align: center; padding: 20px; background: #f0f0f1; border-radius: 6px;">
                    <div style="font-size: 32px; font-weight: bold; color: #007cba;">
                        <?php echo number_format(($stats->total_co2 ?? 0) / 1000, 2); ?>
                    </div>
                    <div style="color: #666; margin-top: 5px;">💨 CO2 Evitado (kg)</div>
                </div>
                <div style="text-align: center; padding: 20px; background: #f0f0f1; border-radius: 6px;">
                    <div style="font-size: 32px; font-weight: bold; color: #007cba;">
                        <?php echo number_format($stats->uk_count ?? 0); ?>
                    </div>
                    <div style="color: #666; margin-top: 5px;">🇬🇧 UK</div>
                </div>
                <div style="text-align: center; padding: 20px; background: #f0f0f1; border-radius: 6px;">
                    <div style="font-size: 32px; font-weight: bold; color: #28a745;">
                        <?php echo number_format($stats->usa_count ?? 0); ?>
                    </div>
                    <div style="color: #666; margin-top: 5px;">🇺🇸 USA</div>
                </div>
            </div>
        </div>
    </div>
    
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Definir ajaxurl si no existe (necesario en frontend)
        if (typeof ajaxurl === 'undefined') {
            var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        }
        
        // Calcular CO2 individual
        $('.calculate-co2-btn').on('click', function() {
            const btn = $(this);
            const row = btn.closest('tr');
            const domainId = btn.data('domain-id');
            const domain = btn.data('domain');
            const server = btn.data('server');
            
            btn.prop('disabled', true).text('⏳ Calculando...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'dominios_reseller_recalcular_co2',
                    nonce: '<?php echo wp_create_nonce('dominios_reseller_nonce'); ?>',
                    domain_id: domainId
                },
                success: function(response) {
                    console.log('🔍 DEBUG CO2 Response:', response);
                    
                    if (response.success) {
                        const co2Gramos = response.data.co2_evaded;
                        const co2Kg = (co2Gramos / 1000).toFixed(3);
                        
                        console.log('✅ ' + domain + ':', {
                            'CO2 gramos': co2Gramos,
                            'CO2 kg': co2Kg,
                            'Tráfico GB': response.data.detalles?.trafico_total_gb,
                            'Grid intensity': response.data.detalles?.grid_intensity,
                            'Fuente': response.data.detalles?.fuente
                        });
                        
                        // Mostrar en kg en la tabla
                        row.find('.co2-value').text(co2Kg + ' kg');
                        btn.text('✅ OK');
                        setTimeout(() => btn.prop('disabled', false).text('🔄 Calcular'), 2000);
                        
                        // Mostrar mensaje con detalles
                        $('#status-message')
                            .css('color', '#28a745')
                            .text('✅ ' + domain + ': ' + co2Kg + ' kg CO2')
                            .fadeIn();
                    } else {
                        btn.text('❌ Error');
                        setTimeout(() => btn.prop('disabled', false).text('🔄 Calcular'), 2000);
                        $('#status-message')
                            .css('color', '#dc3545')
                            .text('❌ Error: ' + (response.data?.message || 'Desconocido'))
                            .fadeIn();
                    }
                },
                error: function() {
                    btn.text('❌ Error');
                    setTimeout(() => btn.prop('disabled', false).text('🔄 Calcular'), 2000);
                    $('#status-message')
                        .css('color', '#dc3545')
                        .text('❌ Error de conexión')
                        .fadeIn();
                }
            });
        });
        
        // Calcular todos
        $('#calculate-all-btn').on('click', function() {
            const btn = $(this);
            const rows = $('#domains-table tbody tr[data-domain-id]');
            let processed = 0;
            const total = rows.length;
            
            if (total === 0) return;
            
            btn.prop('disabled', true).text('⏳ Calculando ' + processed + '/' + total + '...');
            
            rows.each(function(index) {
                const row = $(this);
                const domainId = row.data('domain-id');
                const domain = row.data('domain');
                
                setTimeout(function() {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'dominios_reseller_recalcular_co2',
                            nonce: '<?php echo wp_create_nonce('dominios_reseller_nonce'); ?>',
                            domain_id: domainId
                        },
                        success: function(response) {
                            processed++;
                            if (response.success) {
                                const co2Gramos = response.data.co2_evaded;
                                const co2Kg = (co2Gramos / 1000).toFixed(3);
                                row.find('.co2-value').text(co2Kg + ' kg');
                                row.css('background-color', '#d4edda');
                            } else {
                                row.css('background-color', '#f8d7da');
                            }
                            
                            btn.text('⏳ Calculando ' + processed + '/' + total + '...');
                            
                            if (processed === total) {
                                btn.text('✅ Completado').prop('disabled', false);
                                $('#status-message')
                                    .css('color', '#28a745')
                                    .text('✅ Calculados ' + total + ' dominios')
                                    .fadeIn();
                                setTimeout(() => {
                                    btn.text('🔄 Calcular CO2 de Todos');
                                    $('tr').css('background-color', '');
                                }, 3000);
                            }
                        }
                    });
                }, index * 500); // 500ms delay entre cada uno
            });
        });
    });
    </script>
    
    <style>
    #domains-table input[type="number"] {
        padding: 4px 8px;
        border: 1px solid #ddd;
        border-radius: 3px;
    }
    #domains-table .co2-cell {
        font-weight: 600;
        color: #007cba;
    }
    #status-message {
        display: none;
        animation: fadeIn 0.3s;
    }
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    </style>
    <?php
}

/**
 * Guarda los datos de árboles plantados en la base de datos
 */
function dominios_reseller_save_trees_data($post_data) {
    global $wpdb;
    
    if (!isset($post_data['trees']) || !is_array($post_data['trees'])) {
        return;
    }
    
    $table = $wpdb->prefix . 'dominios_reseller';
    $updated = 0;
    
    foreach ($post_data['trees'] as $domain_id => $trees_count) {
        $domain_id = intval($domain_id);
        $trees_count = intval($trees_count);
        
        if ($domain_id > 0) {
            $result = $wpdb->update(
                $table,
                ['trees_planted' => $trees_count],
                ['id' => $domain_id],
                ['%d'],
                ['%d']
            );
            
            if ($result !== false) {
                $updated++;
            }
        }
    }
    
    echo '<div class="notice notice-success is-dismissible"><p>';
    echo '<strong>✅ Datos guardados:</strong> ' . $updated . ' registros actualizados.';
    echo '</p></div>';
}
