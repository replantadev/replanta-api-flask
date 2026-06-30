<?php
/**
 * Script de migración de sitios de tabla a Custom Post Type
 * Convierte sitios existentes de sites_rphub_sites a posts de tipo rphub_site
 */

if (!defined('ABSPATH')) {
    exit;
}

class RP_Hub_Migration {
    
    private $batch_size = 50;
    
    public function __construct() {
        add_action('wp_ajax_rphub_start_migration', array($this, 'start_migration'));
        add_action('wp_ajax_rphub_check_migration_status', array($this, 'check_migration_status'));
        add_action('admin_notices', array($this, 'migration_notice'));
        add_action('admin_menu', array($this, 'add_migration_page'));
    }

    /**
     * Añade página de migración al menú
     */
    public function add_migration_page() {
        add_submenu_page(
            'replanta-hub',
            __('Migración de Sitios', 'replanta-hub'),
            __('Migración', 'replanta-hub'),
            'manage_options',
            'rphub-migration',
            array($this, 'migration_page')
        );
    }

    /**
     * Página de migración
     */
    public function migration_page() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sites_rphub_sites';
        
        // Verificar si la tabla existe antes de hacer consultas
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        if (!$table_exists) {
            ?>
            <div class="wrap">
                <h1><?php _e('Migración de Sitios a Custom Post Type', 'replanta-hub'); ?></h1>
                <div class="notice notice-info">
                    <p><?php _e('No hay datos antiguos para migrar. El sistema ya está utilizando el nuevo formato.', 'replanta-hub'); ?></p>
                </div>
            </div>
            <?php
            return;
        }
        
        $sites_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $migrated_count = $this->count_migrated_sites();
        $pending_count = $sites_count - $migrated_count;
        
        ?>
        <div class="wrap">
            <h1><?php _e('Migración de Sitios a Custom Post Type', 'replanta-hub'); ?></h1>
            
            <div class="notice notice-info">
                <p><?php _e('Esta herramienta migra los sitios existentes de la tabla de base de datos al nuevo sistema de Custom Post Type.', 'replanta-hub'); ?></p>
            </div>

            <div class="migration-stats">
                <h2><?php _e('Estado de la Migración', 'replanta-hub'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Total de sitios:', 'replanta-hub'); ?></th>
                        <td><strong><?php echo $sites_count; ?></strong></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Sitios migrados:', 'replanta-hub'); ?></th>
                        <td><strong><?php echo $migrated_count; ?></strong></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Sitios pendientes:', 'replanta-hub'); ?></th>
                        <td><strong><?php echo $pending_count; ?></strong></td>
                    </tr>
                </table>
            </div>

            <?php if ($pending_count > 0): ?>
                <div class="migration-actions">
                    <h2><?php _e('Acciones de Migración', 'replanta-hub'); ?></h2>
                    
                    <p><?php _e('Haz clic en el botón para iniciar la migración de sitios:', 'replanta-hub'); ?></p>
                    
                    <button id="start-migration" class="button button-primary button-hero">
                        <?php _e('Iniciar Migración', 'replanta-hub'); ?>
                    </button>
                    
                    <div id="migration-progress" style="display: none;">
                        <h3><?php _e('Progreso de la Migración', 'replanta-hub'); ?></h3>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 0%"></div>
                        </div>
                        <p class="progress-text">0 / <?php echo $pending_count; ?> sitios migrados</p>
                        <div class="migration-log"></div>
                    </div>
                </div>
            <?php else: ?>
                <div class="notice notice-success">
                    <p><?php _e('¡Todos los sitios han sido migrados exitosamente!', 'replanta-hub'); ?></p>
                </div>
            <?php endif; ?>

            <div class="migration-preview">
                <h2><?php _e('Vista Previa de Sitios', 'replanta-hub'); ?></h2>
                
                <?php $this->show_sites_preview(); ?>
            </div>
        </div>

        <style>
        .migration-stats {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #f1f1f1;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s ease;
        }
        
        .migration-log {
            max-height: 200px;
            overflow-y: auto;
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 10px;
            margin-top: 10px;
            font-family: monospace;
            font-size: 12px;
        }
        
        .sites-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .sites-table th,
        .sites-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        .sites-table th {
            background: #f1f1f1;
            font-weight: bold;
        }
        
        .status-migrated {
            color: #0073aa;
            font-weight: bold;
        }
        
        .status-pending {
            color: #d63638;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('#start-migration').on('click', function() {
                $(this).prop('disabled', true).text('<?php _e('Migrando...', 'replanta-hub'); ?>');
                $('#migration-progress').show();
                
                startMigration();
            });
            
            function startMigration() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rphub_start_migration',
                        nonce: '<?php echo wp_create_nonce('rphub_migration'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            checkMigrationStatus();
                        } else {
                            $('.migration-log').append('<div>Error: ' + response.data + '</div>');
                        }
                    }
                });
            }
            
            function checkMigrationStatus() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rphub_check_migration_status',
                        nonce: '<?php echo wp_create_nonce('rphub_migration'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var data = response.data;
                            var progress = (data.migrated / data.total) * 100;
                            
                            $('.progress-fill').css('width', progress + '%');
                            $('.progress-text').text(data.migrated + ' / ' + data.total + ' sitios migrados');
                            
                            if (data.logs) {
                                data.logs.forEach(function(log) {
                                    $('.migration-log').append('<div>' + log + '</div>');
                                });
                                $('.migration-log').scrollTop($('.migration-log')[0].scrollHeight);
                            }
                            
                            if (data.completed) {
                                $('#start-migration').text('<?php _e('Migración Completada', 'replanta-hub'); ?>');
                                $('.migration-log').append('<div><strong>¡Migración completada exitosamente!</strong></div>');
                                setTimeout(function() {
                                    location.reload();
                                }, 2000);
                            } else {
                                setTimeout(checkMigrationStatus, 2000);
                            }
                        }
                    }
                });
            }
        });
        </script>
        <?php
    }

    /**
     * Muestra vista previa de sitios
     */
    private function show_sites_preview() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sites_rphub_sites';
        
        // Verificar si la tabla existe antes de hacer consultas
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        if (!$table_exists) {
            echo '<p>' . __('No hay datos antiguos para mostrar. El sistema ya está usando el nuevo formato.', 'replanta-hub') . '</p>';
            return;
        }
        
        $sites = $wpdb->get_results("SELECT * FROM $table_name LIMIT 10");
        
        if (!$sites) {
            echo '<p>' . __('No se encontraron sitios en la tabla.', 'replanta-hub') . '</p>';
            return;
        }
        
        ?>
        <table class="sites-table">
            <thead>
                <tr>
                    <th><?php _e('ID', 'replanta-hub'); ?></th>
                    <th><?php _e('Nombre', 'replanta-hub'); ?></th>
                    <th><?php _e('URL', 'replanta-hub'); ?></th>
                    <th><?php _e('Plan', 'replanta-hub'); ?></th>
                    <th><?php _e('Estado', 'replanta-hub'); ?></th>
                    <th><?php _e('Migración', 'replanta-hub'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sites as $site): ?>
                    <tr>
                        <td><?php echo $site->id; ?></td>
                        <td><?php echo esc_html($site->name); ?></td>
                        <td><?php echo esc_html($site->url); ?></td>
                        <td><?php echo esc_html($site->plan); ?></td>
                        <td><?php echo esc_html($site->status); ?></td>
                        <td>
                            <?php if ($this->is_site_migrated($site->id)): ?>
                                <span class="status-migrated"><?php _e('Migrado', 'replanta-hub'); ?></span>
                            <?php else: ?>
                                <span class="status-pending"><?php _e('Pendiente', 'replanta-hub'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Verifica si un sitio ya fue migrado
     */
    private function is_site_migrated($site_id) {
        $post = get_posts(array(
            'post_type' => 'rphub_site',
            'meta_key' => '_legacy_site_id',
            'meta_value' => $site_id,
            'posts_per_page' => 1
        ));
        
        return !empty($post);
    }

    /**
     * Cuenta sitios migrados
     */
    private function count_migrated_sites() {
        $migrated = get_posts(array(
            'post_type' => 'rphub_site',
            'meta_key' => '_legacy_site_id',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
        
        return count($migrated);
    }

    /**
     * Inicia el proceso de migración
     */
    public function start_migration() {
        if (!wp_verify_nonce($_POST['nonce'], 'rphub_migration')) {
            wp_send_json_error(['message' => 'Security check failed']); return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']); return;
        }

        // Inicializar progreso de migración
        update_option('rphub_migration_progress', array(
            'total' => 0,
            'migrated' => 0,
            'current_batch' => 0,
            'logs' => array(),
            'completed' => false
        ));

        // Programar primera tarea de migración
        wp_schedule_single_event(time(), 'rphub_migrate_sites_batch');
        
        wp_send_json_success('Migration started');
    }

    /**
     * Verifica el estado de la migración
     */
    public function check_migration_status() {
        if (!wp_verify_nonce($_POST['nonce'], 'rphub_migration')) {
            wp_send_json_error(['message' => 'Security check failed']); return;
        }

        $progress = get_option('rphub_migration_progress', array(
            'total' => 0,
            'migrated' => 0,
            'current_batch' => 0,
            'logs' => array(),
            'completed' => false
        ));

        wp_send_json_success($progress);
    }

    /**
     * Migra un lote de sitios
     */
    public function migrate_sites_batch() {
        global $wpdb;
        
        $progress = get_option('rphub_migration_progress');
        $table_name = $wpdb->prefix . 'sites_rphub_sites';
        
        // Verificar si la tabla existe antes de hacer consultas
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        if (!$table_exists) {
            // Si no existe la tabla, marcar migración como completada
            update_option('rphub_migration_progress', array(
                'completed' => true,
                'current_batch' => 0,
                'total_sites' => 0,
                'migrated_sites' => 0,
                'errors' => array()
            ));
            return;
        }
        
        // Obtener total de sitios si es la primera vez
        if ($progress['total'] == 0) {
            $progress['total'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        }
        
        // Obtener sitios no migrados
        $offset = $progress['current_batch'] * $this->batch_size;
        $sites = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE id NOT IN (
                 SELECT CAST(meta_value AS UNSIGNED) 
                 FROM {$wpdb->postmeta} 
                 WHERE meta_key = '_legacy_site_id'
             ) 
             LIMIT %d OFFSET %d",
            $this->batch_size,
            $offset
        ));

        if (empty($sites)) {
            // Migración completada
            $progress['completed'] = true;
            $progress['logs'][] = date('H:i:s') . ' - Migración completada exitosamente';
            update_option('rphub_migration_progress', $progress);
            return;
        }

        // Migrar sitios del lote actual
        foreach ($sites as $site) {
            $this->migrate_single_site($site);
            $progress['migrated']++;
            $progress['logs'][] = date('H:i:s') . ' - Migrado: ' . $site->name;
        }

        $progress['current_batch']++;
        update_option('rphub_migration_progress', $progress);

        // Programar siguiente lote
        wp_schedule_single_event(time() + 5, 'rphub_migrate_sites_batch');
    }

    /**
     * Migra un sitio individual
     */
    private function migrate_single_site($site) {
        // Crear el post
        $post_data = array(
            'post_title' => $site->name,
            'post_content' => $site->description ?: '',
            'post_status' => 'publish',
            'post_type' => 'rphub_site',
            'post_author' => 1 // Admin user
        );

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            return false;
        }

        // Asignar taxonomías
        if ($site->plan) {
            wp_set_object_terms($post_id, $site->plan, 'site_plan');
        }

        if ($site->status) {
            wp_set_object_terms($post_id, $site->status, 'site_status');
        }

        // Migrar meta datos
        $meta_mapping = array(
            'url' => '_site_url',
            'token' => '_site_token',
            'last_connection' => '_last_connection',
            'connection_status' => '_connection_status',
            'wp_version' => '_wp_version',
            'php_version' => '_php_version',
            'mysql_version' => '_mysql_version',
            'server_info' => '_server_info',
            'pagespeed_mobile' => '_pagespeed_mobile',
            'pagespeed_desktop' => '_pagespeed_desktop',
            'core_web_vitals' => '_core_web_vitals',
            'security_score' => '_security_score',
            'vulnerabilities' => '_vulnerabilities',
            'security_plugins' => '_security_plugins'
        );

        foreach ($meta_mapping as $old_field => $new_field) {
            if (isset($site->$old_field) && $site->$old_field !== null) {
                update_post_meta($post_id, $new_field, $site->$old_field);
            }
        }

        // Guardar referencia al ID original para evitar duplicados
        update_post_meta($post_id, '_legacy_site_id', $site->id);
        
        // Timestamps
        update_post_meta($post_id, '_migrated_at', current_time('mysql'));

        return $post_id;
    }

    /**
     * Aviso de migración
     */
    public function migration_notice() {
        global $wpdb;
        
        $screen = get_current_screen();
        if ($screen->parent_base !== 'replanta-hub') {
            return;
        }

        $table_name = $wpdb->prefix . 'sites_rphub_sites';
        
        // Verificar si la tabla existe antes de hacer consultas
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        if (!$table_exists) {
            return; // Si la tabla no existe, no mostrar aviso
        }
        
        $sites_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $migrated_count = $this->count_migrated_sites();
        
        if ($sites_count > 0 && $migrated_count < $sites_count) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php _e('Migración Pendiente:', 'replanta-hub'); ?></strong>
                    <?php printf(
                        __('Hay %d sitios que necesitan ser migrados al nuevo sistema. <a href="%s">Ir a la página de migración</a>', 'replanta-hub'),
                        $sites_count - $migrated_count,
                        admin_url('admin.php?page=rphub-migration')
                    ); ?>
                </p>
            </div>
            <?php
        }
    }
}

// Inicializar migración
$rphub_migration = new RP_Hub_Migration();

// Registrar action para cron
add_action('rphub_migrate_sites_batch', array($rphub_migration, 'migrate_sites_batch'));
