<?php
/**
 * Script temporal para registrar el sitio actual en el HUB
 * Ejecutar en wp-admin/tools.php agregando ?register_site=1
 */

if (isset($_GET['register_site']) && current_user_can('manage_options')) {
    
    // Registrar el sitio actual en el HUB
    global $wpdb;
    
    $site_manager = new RPHUB_Site_Manager();
    $table_sites = $wpdb->prefix . 'rphub_sites';
    
    $site_url = site_url();
    $site_name = get_bloginfo('name');
    $site_token = wp_generate_password(32, false);
    
    // Verificar si ya existe
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_sites} WHERE url = %s",
        $site_url
    ));
    
    if ($existing) {
        echo '<div class="notice notice-info"><p>El sitio ya está registrado en el HUB. Token: ' . esc_html($existing->token) . '</p></div>';
    } else {
        // Insertar nuevo sitio
        $result = $wpdb->insert(
            $table_sites,
            [
                'name' => $site_name,
                'url' => $site_url,
                'plan' => 'basic',
                'token' => $site_token,
                'status' => 'active',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        if ($result) {
            echo '<div class="notice notice-success"><p>✅ Sitio registrado exitosamente en el HUB!<br>';
            echo '<strong>URL:</strong> ' . esc_html($site_url) . '<br>';
            echo '<strong>Token:</strong> ' . esc_html($site_token) . '<br>';
            echo '<strong>Plan:</strong> basic</p></div>';
            
            // Guardar el token en las opciones del Care
            $care_options = get_option('rpcare_options', []);
            $care_options['site_token'] = $site_token;
            $care_options['hub_url'] = site_url(); // Use current Hub URL dynamically
            update_option('rpcare_options', $care_options);
            
            echo '<div class="notice notice-info"><p>✅ Token guardado automáticamente en Replanta Care.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>❌ Error al registrar el sitio: ' . esc_html($wpdb->last_error) . '</p></div>';
        }
    }
}
?>

<h2>🛠️ Registro de Sitio en Replanta HUB</h2>
<p>Este script registra automáticamente el sitio actual en el HUB.</p>

<?php if (!isset($_GET['register_site'])): ?>
<a href="?register_site=1" class="button button-primary">
    Registrar este sitio en el HUB
</a>
<?php endif; ?>

<h3>📋 Estado Actual</h3>
<?php
// Mostrar estado actual
$care_options = get_option('rpcare_options', []);
echo '<p><strong>URL del sitio:</strong> ' . esc_html(site_url()) . '</p>';
echo '<p><strong>Nombre del sitio:</strong> ' . esc_html(get_bloginfo('name')) . '</p>';
echo '<p><strong>HUB URL (Care):</strong> ' . esc_html($care_options['hub_url'] ?? 'No configurado') . '</p>';
echo '<p><strong>Token (Care):</strong> ' . (isset($care_options['site_token']) && !empty($care_options['site_token']) ? 'Configurado' : 'No configurado') . '</p>';

// Verificar si existe en el HUB
global $wpdb;
$table_sites = $wpdb->prefix . 'rphub_sites';
$existing = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$table_sites} WHERE url = %s",
    site_url()
));

echo '<p><strong>Estado en HUB:</strong> ' . ($existing ? '✅ Registrado' : '❌ No registrado') . '</p>';
if ($existing) {
    echo '<p><strong>Plan en HUB:</strong> ' . esc_html($existing->plan) . '</p>';
    echo '<p><strong>Token en HUB:</strong> ' . esc_html($existing->token) . '</p>';
}
?>
