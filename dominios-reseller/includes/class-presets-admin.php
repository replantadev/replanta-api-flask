<?php
/**
 * Presets Admin Page
 * 
 * Página de administración para editar presets de Cloudflare
 * 
 * @package Dominios_Reseller
 * @version 1.0.0
 * @since 1.5.4
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dominios_Reseller_Presets_Admin {

    private static ?self $instance = null;

    public static function get_instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks(): void {
        add_action('admin_menu', [$this, 'register_menu'], 20);
        
        // AJAX handlers
        add_action('wp_ajax_dr_preset_save', [$this, 'ajax_save_preset']);
        add_action('wp_ajax_dr_preset_delete', [$this, 'ajax_delete_preset']);
        add_action('wp_ajax_dr_preset_duplicate', [$this, 'ajax_duplicate_preset']);
        add_action('wp_ajax_dr_preset_reset', [$this, 'ajax_reset_defaults']);
    }

    /**
     * Asegurar que la tabla de presets existe
     */
    private function ensure_presets_table(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'dominios_reseller_cf_presets';
        
        // Verificar si la tabla existe usando SHOW TABLES (más fiable)
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        
        if (!$table_exists) {
            // Crear tabla directamente (más fiable que dbDelta)
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE IF NOT EXISTS $table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                preset_key varchar(50) NOT NULL,
                name varchar(100) NOT NULL,
                description text DEFAULT NULL,
                payload longtext NOT NULL,
                is_default tinyint(1) NOT NULL DEFAULT 0,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_preset_key (preset_key)
            ) $charset_collate;";
            
            $wpdb->query($sql);
            
            // Verificar si se creó
            $created = $wpdb->get_var("SHOW TABLES LIKE '$table'");
            
            if ($created) {
                // Insertar presets por defecto
                if (class_exists('Dominios_Reseller_Onboarding_DB')) {
                    Dominios_Reseller_Onboarding_DB::insert_default_presets();
                }
            } else {
                // Log del error si falla
                error_log("DR Presets: Failed to create table $table. Error: " . $wpdb->last_error);
            }
        }
    }

    public function register_menu(): void {
        add_submenu_page(
            'dominios-reseller',
            'CF Presets',
            '⚙️ CF Presets',
            'manage_options',
            'dominios-reseller-presets',
            [$this, 'render_page']
        );
    }

    public function render_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Acceso denegado');
        }

        // Asegurar que la tabla de presets existe
        $this->ensure_presets_table();

        $presets = Dominios_Reseller_Onboarding_DB::get_presets();
        $editing_key = isset($_GET['edit']) ? sanitize_text_field($_GET['edit']) : null;
        $editing_preset = null;
        
        if ($editing_key) {
            $editing_preset = Dominios_Reseller_Onboarding_DB::get_preset($editing_key);
        }
        
        ?>
        <div class="wrap dr-presets-page">
            <h1>⚙️ Cloudflare Presets</h1>
            <p class="description">Configura los presets de Cloudflare para el onboarding de dominios.</p>
            
            <?php $this->render_styles(); ?>
            
            <div class="dr-presets-layout">
                <!-- Lista de Presets -->
                <div class="dr-presets-list">
                    <h2>📋 Presets Disponibles</h2>
                    
                    <div class="dr-presets-grid">
                        <?php foreach ($presets as $preset): ?>
                            <?php 
                            $is_active = $editing_key === $preset['preset_key'];
                            $payload = json_decode($preset['payload'], true);
                            $settings_count = count($payload['settings'] ?? []);
                            $rules_count = count($payload['cache_rules'] ?? []);
                            ?>
                            <div class="dr-preset-card <?php echo $is_active ? 'active' : ''; ?>">
                                <div class="dr-preset-card-header">
                                    <span class="dr-preset-icon">
                                        <?php echo $preset['preset_key'] === 'woo' ? '🛒' : '📝'; ?>
                                    </span>
                                    <div class="dr-preset-info">
                                        <strong><?php echo esc_html($preset['name']); ?></strong>
                                        <code><?php echo esc_html($preset['preset_key']); ?></code>
                                    </div>
                                    <?php if ($preset['is_default']): ?>
                                        <span class="dr-badge dr-badge-default">Default</span>
                                    <?php endif; ?>
                                </div>
                                <p class="dr-preset-desc"><?php echo esc_html($preset['description'] ?? ''); ?></p>
                                <div class="dr-preset-stats">
                                    <span>⚙️ <?php echo $settings_count; ?> settings</span>
                                    <span>📝 <?php echo $rules_count; ?> rules</span>
                                </div>
                                <div class="dr-preset-actions">
                                    <a href="<?php echo admin_url('admin.php?page=dominios-reseller-presets&edit=' . urlencode($preset['preset_key'])); ?>" 
                                       class="button button-primary">
                                        ✏️ Editar
                                    </a>
                                    <button class="button dr-btn-duplicate" data-key="<?php echo esc_attr($preset['preset_key']); ?>">
                                        📋 Duplicar
                                    </button>
                                    <?php if (!$preset['is_default']): ?>
                                        <button class="button dr-btn-delete" data-key="<?php echo esc_attr($preset['preset_key']); ?>">
                                            🗑️
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Card para nuevo preset -->
                        <div class="dr-preset-card dr-preset-card-new">
                            <a href="<?php echo admin_url('admin.php?page=dominios-reseller-presets&edit=_new'); ?>" class="dr-new-preset-link">
                                <span class="dr-new-icon">➕</span>
                                <span>Crear Nuevo Preset</span>
                            </a>
                        </div>
                    </div>
                    
                    <div class="dr-presets-tools">
                        <button id="dr-reset-defaults" class="button">
                            🔄 Restaurar Presets por Defecto
                        </button>
                        <a href="<?php echo admin_url('admin.php?page=dominios-reseller-onboarding'); ?>" class="button">
                            ← Volver a Onboarding
                        </a>
                    </div>
                </div>
                
                <!-- Editor de Preset -->
                <?php if ($editing_key): ?>
                    <div class="dr-preset-editor">
                        <h2>
                            <?php echo $editing_key === '_new' ? '➕ Nuevo Preset' : '✏️ Editando: ' . esc_html($editing_preset['name'] ?? $editing_key); ?>
                        </h2>
                        
                        <form id="dr-preset-form" class="dr-preset-form">
                            <input type="hidden" name="original_key" value="<?php echo esc_attr($editing_key === '_new' ? '' : $editing_key); ?>">
                            
                            <!-- Info básica -->
                            <div class="dr-form-section">
                                <h3>ℹ️ Información Básica</h3>
                                <div class="dr-form-row">
                                    <label for="preset_key">Clave (slug):</label>
                                    <input type="text" id="preset_key" name="preset_key" 
                                           value="<?php echo esc_attr($editing_preset['preset_key'] ?? ''); ?>"
                                           pattern="[a-z0-9_-]+" 
                                           placeholder="mi_preset"
                                           <?php echo ($editing_key !== '_new' && ($editing_preset['is_default'] ?? false)) ? 'readonly' : ''; ?>
                                           required>
                                    <span class="dr-hint">Solo letras minúsculas, números, guiones y guiones bajos</span>
                                </div>
                                <div class="dr-form-row">
                                    <label for="preset_name">Nombre:</label>
                                    <input type="text" id="preset_name" name="name" 
                                           value="<?php echo esc_attr($editing_preset['name'] ?? ''); ?>"
                                           placeholder="Mi Preset Personalizado"
                                           required>
                                </div>
                                <div class="dr-form-row">
                                    <label for="preset_description">Descripción:</label>
                                    <textarea id="preset_description" name="description" rows="2"
                                              placeholder="Descripción del preset..."><?php echo esc_textarea($editing_preset['description'] ?? ''); ?></textarea>
                                </div>
                                <div class="dr-form-row dr-form-row-inline">
                                    <label>
                                        <input type="checkbox" name="is_default" value="1"
                                               <?php checked($editing_preset['is_default'] ?? false); ?>>
                                        Preset por defecto
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Settings -->
                            <div class="dr-form-section">
                                <h3>⚙️ Zone Settings</h3>
                                <p class="description">Configuración de la zona de Cloudflare (JSON)</p>
                                <?php 
                                $payload = $editing_preset['payload_decoded'] ?? ['settings' => [], 'cache_rules' => [], 'notes' => ''];
                                $settings_json = json_encode($payload['settings'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                                ?>
                                <textarea id="preset_settings" name="settings" rows="15" 
                                          class="dr-code-editor"><?php echo esc_textarea($settings_json); ?></textarea>
                                <div class="dr-editor-help">
                                    <details>
                                        <summary>📖 Settings disponibles</summary>
                                        <ul>
                                            <li><code>ssl</code>: "off", "flexible", "full", "strict"</li>
                                            <li><code>always_use_https</code>: "on", "off"</li>
                                            <li><code>min_tls_version</code>: "1.0", "1.1", "1.2", "1.3"</li>
                                            <li><code>security_level</code>: "off", "low", "medium", "high", "under_attack"</li>
                                            <li><code>browser_cache_ttl</code>: segundos (ej: 14400 = 4h)</li>
                                            <li><code>cache_level</code>: "bypass", "basic", "simplified", "aggressive"</li>
                                            <li><code>rocket_loader</code>: "on", "off" (⚠️ OFF para WP)</li>
                                            <li><code>brotli</code>: "on", "off"</li>
                                            <li><code>http3</code>: "on", "off"</li>
                                            <li><code>0rtt</code>: "on", "off"</li>
                                            <li><code>early_hints</code>: "on", "off"</li>
                                        </ul>
                                    </details>
                                </div>
                            </div>
                            
                            <!-- Cache Rules -->
                            <div class="dr-form-section">
                                <h3>📝 Cache Rules</h3>
                                <p class="description">Reglas de caché (JSON array)</p>
                                <?php 
                                $rules_json = json_encode($payload['cache_rules'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                                ?>
                                <textarea id="preset_rules" name="cache_rules" rows="20" 
                                          class="dr-code-editor"><?php echo esc_textarea($rules_json); ?></textarea>
                                <div class="dr-editor-help">
                                    <details>
                                        <summary>📖 Formato de reglas</summary>
                                        <pre>[
  {
    "name": "Bypass WordPress Admin",
    "if": "(http.request.uri.path contains \"/wp-admin\")",
    "action": "bypass"
  }
]</pre>
                                        <p><strong>Acciones:</strong></p>
                                        <ul>
                                            <li><code>bypass</code>: No cachear</li>
                                            <li><code>cache</code>: Cachear (respetar TTL origen)</li>
                                            <li><code>cache_aggressive</code>: Cache 7 días</li>
                                        </ul>
                                        <p><strong>Expresiones comunes:</strong></p>
                                        <ul>
                                            <li><code>http.request.uri.path contains "/wp-admin"</code></li>
                                            <li><code>http.request.uri.query contains "wc-ajax"</code></li>
                                            <li><code>http.cookie contains "wordpress_logged_in"</code></li>
                                            <li><code>http.request.uri.path.extension in {"css" "js" "jpg"}</code></li>
                                        </ul>
                                    </details>
                                </div>
                            </div>
                            
                            <!-- Notas -->
                            <div class="dr-form-section">
                                <h3>📋 Notas</h3>
                                <textarea id="preset_notes" name="notes" rows="3"
                                          placeholder="Notas sobre este preset..."><?php echo esc_textarea($payload['notes'] ?? ''); ?></textarea>
                            </div>
                            
                            <!-- Acciones -->
                            <div class="dr-form-actions">
                                <button type="submit" class="button button-primary button-large">
                                    💾 Guardar Preset
                                </button>
                                <button type="button" id="dr-validate-json" class="button">
                                    ✅ Validar JSON
                                </button>
                                <a href="<?php echo admin_url('admin.php?page=dominios-reseller-presets'); ?>" class="button">
                                    ❌ Cancelar
                                </a>
                            </div>
                        </form>
                        
                        <div id="dr-form-result" class="dr-form-result"></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php $this->render_scripts(); ?>
        <?php
    }

    private function render_styles(): void {
        ?>
        <style>
        .dr-presets-page {
            max-width: 1600px;
        }
        .dr-presets-layout {
            display: flex;
            gap: 30px;
            margin-top: 20px;
        }
        .dr-presets-list {
            flex: 0 0 400px;
        }
        .dr-preset-editor {
            flex: 1;
            min-width: 0;
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 8px;
            padding: 20px;
        }
        
        /* Grid de presets */
        .dr-presets-grid {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .dr-preset-card {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 8px;
            padding: 15px;
            transition: all 0.2s;
        }
        .dr-preset-card:hover {
            border-color: #2271b1;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .dr-preset-card.active {
            border-color: #2271b1;
            border-width: 2px;
            background: #f0f6fc;
        }
        .dr-preset-card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        .dr-preset-icon {
            font-size: 24px;
        }
        .dr-preset-info {
            flex: 1;
        }
        .dr-preset-info strong {
            display: block;
            font-size: 14px;
            color: #1d2327;
        }
        .dr-preset-info code {
            font-size: 11px;
            color: #646970;
            background: #f0f0f1;
            padding: 2px 6px;
            border-radius: 3px;
        }
        .dr-badge {
            font-size: 10px;
            padding: 3px 8px;
            border-radius: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .dr-badge-default {
            background: #dff0d8;
            color: #3c763d;
        }
        .dr-preset-desc {
            font-size: 12px;
            color: #646970;
            margin: 0 0 10px 0;
            line-height: 1.4;
        }
        .dr-preset-stats {
            display: flex;
            gap: 15px;
            font-size: 11px;
            color: #787c82;
            margin-bottom: 12px;
        }
        .dr-preset-actions {
            display: flex;
            gap: 8px;
        }
        .dr-preset-actions .button {
            font-size: 12px;
            padding: 4px 10px;
        }
        .dr-btn-delete {
            color: #d63638 !important;
        }
        
        /* Nuevo preset card */
        .dr-preset-card-new {
            border-style: dashed;
            background: #f9f9f9;
        }
        .dr-new-preset-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            text-decoration: none;
            color: #646970;
        }
        .dr-new-preset-link:hover {
            color: #2271b1;
        }
        .dr-new-icon {
            font-size: 32px;
            margin-bottom: 8px;
        }
        
        /* Tools */
        .dr-presets-tools {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #c3c4c7;
            display: flex;
            gap: 10px;
        }
        
        /* Editor form */
        .dr-preset-form h3 {
            margin: 0 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
            color: #1d2327;
        }
        .dr-form-section {
            margin-bottom: 25px;
        }
        .dr-form-row {
            margin-bottom: 15px;
        }
        .dr-form-row label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #1d2327;
        }
        .dr-form-row input[type="text"],
        .dr-form-row textarea {
            width: 100%;
            font-size: 13px;
        }
        .dr-form-row-inline label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: normal;
        }
        .dr-hint {
            display: block;
            font-size: 11px;
            color: #646970;
            margin-top: 4px;
        }
        .dr-code-editor {
            font-family: Consolas, Monaco, 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.5;
            background: #1e1e1e;
            color: #d4d4d4;
            border-radius: 6px;
            padding: 12px;
            tab-size: 2;
        }
        .dr-editor-help {
            margin-top: 10px;
        }
        .dr-editor-help summary {
            cursor: pointer;
            color: #2271b1;
            font-size: 12px;
        }
        .dr-editor-help details[open] {
            background: #f6f7f7;
            padding: 12px;
            border-radius: 4px;
            margin-top: 8px;
        }
        .dr-editor-help ul {
            margin: 8px 0;
            padding-left: 20px;
        }
        .dr-editor-help li {
            font-size: 12px;
            margin-bottom: 4px;
        }
        .dr-editor-help pre {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 10px;
            border-radius: 4px;
            font-size: 11px;
            overflow-x: auto;
        }
        
        /* Form actions */
        .dr-form-actions {
            display: flex;
            gap: 10px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        /* Result message */
        .dr-form-result {
            margin-top: 15px;
            padding: 12px 15px;
            border-radius: 4px;
            display: none;
        }
        .dr-form-result.success {
            display: block;
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .dr-form-result.error {
            display: block;
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .dr-presets-layout {
                flex-direction: column;
            }
            .dr-presets-list {
                flex: none;
            }
        }
        </style>
        <?php
    }

    private function render_scripts(): void {
        $nonce = wp_create_nonce('dr_presets_admin');
        ?>
        <script>
        jQuery(document).ready(function($) {
            var nonce = '<?php echo $nonce; ?>';
            
            // ===== GUARDAR PRESET =====
            $('#dr-preset-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $result = $('#dr-form-result');
                var $submitBtn = $form.find('button[type="submit"]');
                
                // Validar JSON primero
                var settingsValid = validateJson($('#preset_settings').val());
                var rulesValid = validateJson($('#preset_rules').val());
                
                if (!settingsValid.valid) {
                    showResult('error', 'Error en Settings JSON: ' + settingsValid.error);
                    return;
                }
                if (!rulesValid.valid) {
                    showResult('error', 'Error en Cache Rules JSON: ' + rulesValid.error);
                    return;
                }
                
                $submitBtn.prop('disabled', true).text('⏳ Guardando...');
                
                $.post(ajaxurl, {
                    action: 'dr_preset_save',
                    _nonce: nonce,
                    original_key: $form.find('[name="original_key"]').val(),
                    preset_key: $('#preset_key').val(),
                    name: $('#preset_name').val(),
                    description: $('#preset_description').val(),
                    is_default: $form.find('[name="is_default"]').is(':checked') ? 1 : 0,
                    settings: $('#preset_settings').val(),
                    cache_rules: $('#preset_rules').val(),
                    notes: $('#preset_notes').val()
                }, function(response) {
                    if (response.success) {
                        showResult('success', '✅ ' + response.data);
                        setTimeout(function() {
                            window.location.href = '<?php echo admin_url('admin.php?page=dominios-reseller-presets&edit='); ?>' + $('#preset_key').val();
                        }, 1000);
                    } else {
                        showResult('error', '❌ ' + response.data);
                        $submitBtn.prop('disabled', false).text('💾 Guardar Preset');
                    }
                }).fail(function() {
                    showResult('error', '❌ Error de conexión');
                    $submitBtn.prop('disabled', false).text('💾 Guardar Preset');
                });
            });
            
            // ===== VALIDAR JSON =====
            $('#dr-validate-json').on('click', function() {
                var settingsValid = validateJson($('#preset_settings').val());
                var rulesValid = validateJson($('#preset_rules').val());
                
                var messages = [];
                if (settingsValid.valid) {
                    messages.push('✅ Settings JSON válido');
                } else {
                    messages.push('❌ Settings JSON: ' + settingsValid.error);
                }
                if (rulesValid.valid) {
                    messages.push('✅ Cache Rules JSON válido');
                } else {
                    messages.push('❌ Cache Rules JSON: ' + rulesValid.error);
                }
                
                var allValid = settingsValid.valid && rulesValid.valid;
                showResult(allValid ? 'success' : 'error', messages.join('<br>'));
            });
            
            // ===== DUPLICAR PRESET =====
            $('.dr-btn-duplicate').on('click', function() {
                var key = $(this).data('key');
                var newKey = prompt('Introduce la clave para el nuevo preset:', key + '_copy');
                
                if (newKey && newKey !== key) {
                    $.post(ajaxurl, {
                        action: 'dr_preset_duplicate',
                        _nonce: nonce,
                        source_key: key,
                        new_key: newKey
                    }, function(response) {
                        if (response.success) {
                            window.location.href = '<?php echo admin_url('admin.php?page=dominios-reseller-presets&edit='); ?>' + newKey;
                        } else {
                            alert('Error: ' + response.data);
                        }
                    });
                }
            });
            
            // ===== ELIMINAR PRESET =====
            $('.dr-btn-delete').on('click', function() {
                var key = $(this).data('key');
                if (confirm('¿Seguro que quieres eliminar el preset "' + key + '"?')) {
                    $.post(ajaxurl, {
                        action: 'dr_preset_delete',
                        _nonce: nonce,
                        preset_key: key
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Error: ' + response.data);
                        }
                    });
                }
            });
            
            // ===== RESTAURAR DEFAULTS =====
            $('#dr-reset-defaults').on('click', function() {
                if (confirm('⚠️ Esto restaurará los presets "wp" y "woo" a sus valores por defecto.\n\n¿Continuar?')) {
                    var $btn = $(this);
                    $btn.prop('disabled', true).text('⏳ Restaurando...');
                    
                    $.post(ajaxurl, {
                        action: 'dr_preset_reset',
                        _nonce: nonce
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Error: ' + response.data);
                            $btn.prop('disabled', false).text('🔄 Restaurar Presets por Defecto');
                        }
                    });
                }
            });
            
            // ===== HELPERS =====
            function validateJson(str) {
                try {
                    JSON.parse(str);
                    return { valid: true };
                } catch (e) {
                    return { valid: false, error: e.message };
                }
            }
            
            function showResult(type, message) {
                var $result = $('#dr-form-result');
                $result.removeClass('success error').addClass(type).html(message).show();
                
                // Scroll to result
                $('html, body').animate({
                    scrollTop: $result.offset().top - 100
                }, 300);
            }
            
            // Tab en textareas de código
            $('.dr-code-editor').on('keydown', function(e) {
                if (e.key === 'Tab') {
                    e.preventDefault();
                    var start = this.selectionStart;
                    var end = this.selectionEnd;
                    this.value = this.value.substring(0, start) + '  ' + this.value.substring(end);
                    this.selectionStart = this.selectionEnd = start + 2;
                }
            });
        });
        </script>
        <?php
    }

    // =========================================================================
    // AJAX Handlers
    // =========================================================================

    public function ajax_save_preset(): void {
        check_ajax_referer('dr_presets_admin', '_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        // Asegurar que la tabla existe
        $this->ensure_presets_table();

        $original_key = sanitize_text_field($_POST['original_key'] ?? '');
        $preset_key = sanitize_text_field($_POST['preset_key'] ?? '');
        $name = sanitize_text_field($_POST['name'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $is_default = intval($_POST['is_default'] ?? 0);
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');

        // Validar campos requeridos
        if (empty($preset_key) || empty($name)) {
            wp_send_json_error('Clave y nombre son requeridos');
        }

        // Validar formato de clave
        if (!preg_match('/^[a-z0-9_-]+$/', $preset_key)) {
            wp_send_json_error('La clave solo puede contener letras minúsculas, números, guiones y guiones bajos');
        }

        // Parsear y validar JSON
        $settings_raw = stripslashes($_POST['settings'] ?? '{}');
        $rules_raw = stripslashes($_POST['cache_rules'] ?? '[]');

        $settings = json_decode($settings_raw, true);
        $cache_rules = json_decode($rules_raw, true);

        if ($settings === null && json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('Settings JSON inválido: ' . json_last_error_msg());
        }
        if ($cache_rules === null && json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('Cache Rules JSON inválido: ' . json_last_error_msg());
        }

        // Construir payload
        $payload = [
            'settings' => $settings ?: [],
            'cache_rules' => $cache_rules ?: [],
            'notes' => $notes
        ];

        global $wpdb;
        $table = Dominios_Reseller_Onboarding_DB::get_presets_table();

        // Verificar si es edición o creación
        if (!empty($original_key)) {
            // Actualizar existente
            $result = $wpdb->update(
                $table,
                [
                    'preset_key' => $preset_key,
                    'name' => $name,
                    'description' => $description,
                    'payload' => json_encode($payload),
                    'is_default' => $is_default
                ],
                ['preset_key' => $original_key]
            );

            if ($result === false) {
                wp_send_json_error('Error al actualizar: ' . $wpdb->last_error);
            }
            wp_send_json_success('Preset actualizado correctamente');
        } else {
            // Verificar que no exista
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE preset_key = %s",
                $preset_key
            ));

            if ($exists) {
                wp_send_json_error('Ya existe un preset con esa clave');
            }

            // Crear nuevo
            $result = $wpdb->insert($table, [
                'preset_key' => $preset_key,
                'name' => $name,
                'description' => $description,
                'payload' => json_encode($payload),
                'is_default' => $is_default
            ]);

            if ($result === false) {
                wp_send_json_error('Error al crear: ' . $wpdb->last_error);
            }
            wp_send_json_success('Preset creado correctamente');
        }
    }

    public function ajax_delete_preset(): void {
        check_ajax_referer('dr_presets_admin', '_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        $preset_key = sanitize_text_field($_POST['preset_key'] ?? '');
        if (empty($preset_key)) {
            wp_send_json_error('Clave requerida');
        }

        // No permitir eliminar presets por defecto
        if (in_array($preset_key, ['wp', 'woo'])) {
            wp_send_json_error('No se pueden eliminar los presets por defecto');
        }

        global $wpdb;
        $table = Dominios_Reseller_Onboarding_DB::get_presets_table();
        
        $result = $wpdb->delete($table, ['preset_key' => $preset_key]);
        
        if ($result === false) {
            wp_send_json_error('Error al eliminar');
        }
        
        wp_send_json_success('Preset eliminado');
    }

    public function ajax_duplicate_preset(): void {
        check_ajax_referer('dr_presets_admin', '_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        // Asegurar que la tabla existe
        $this->ensure_presets_table();

        $source_key = sanitize_text_field($_POST['source_key'] ?? '');
        $new_key = sanitize_text_field($_POST['new_key'] ?? '');

        if (empty($source_key) || empty($new_key)) {
            wp_send_json_error('Claves requeridas');
        }

        if (!preg_match('/^[a-z0-9_-]+$/', $new_key)) {
            wp_send_json_error('La nueva clave solo puede contener letras minúsculas, números, guiones y guiones bajos');
        }

        // Obtener preset original
        $source = Dominios_Reseller_Onboarding_DB::get_preset($source_key);
        if (!$source) {
            wp_send_json_error('Preset origen no encontrado');
        }

        global $wpdb;
        $table = Dominios_Reseller_Onboarding_DB::get_presets_table();

        // Verificar que no exista el nuevo
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE preset_key = %s",
            $new_key
        ));

        if ($exists) {
            wp_send_json_error('Ya existe un preset con esa clave');
        }

        // Duplicar
        $result = $wpdb->insert($table, [
            'preset_key' => $new_key,
            'name' => $source['name'] . ' (copia)',
            'description' => $source['description'],
            'payload' => $source['payload'],
            'is_default' => 0
        ]);

        if ($result === false) {
            wp_send_json_error('Error al duplicar');
        }

        wp_send_json_success('Preset duplicado');
    }

    public function ajax_reset_defaults(): void {
        check_ajax_referer('dr_presets_admin', '_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        // Asegurar que la tabla existe
        $this->ensure_presets_table();

        global $wpdb;
        $table = Dominios_Reseller_Onboarding_DB::get_presets_table();

        // Eliminar presets por defecto actuales
        $wpdb->delete($table, ['preset_key' => 'wp']);
        $wpdb->delete($table, ['preset_key' => 'woo']);

        // Reinsertar los defaults
        Dominios_Reseller_Onboarding_DB::insert_default_presets();

        wp_send_json_success('Presets restaurados');
    }
}

// Inicializar
if (is_admin()) {
    add_action('plugins_loaded', function() {
        if (class_exists('Dominios_Reseller_Onboarding_DB')) {
            Dominios_Reseller_Presets_Admin::get_instance();
        }
    });
}
