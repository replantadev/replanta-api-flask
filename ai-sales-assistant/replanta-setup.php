<?php
/**
 * Replanta AI Chat — One-time setup script.
 * Upload to wp-content/replanta-setup.php, run ONCE, then delete.
 *
 * Protección: token secreto para evitar ejecución no autorizada.
 */

// ── Secret token (change before using) ────────────────────────────────────
define( 'SETUP_TOKEN', 'replanta_banban_setup_2026' );

if ( ( $_GET['token'] ?? '' ) !== SETUP_TOKEN ) {
    http_response_code( 403 );
    die( 'Forbidden' );
}

// Bootstrap WP
$wp_root = dirname( __DIR__ );
require_once $wp_root . '/wp-load.php';

if ( ! current_user_can( 'manage_options' ) && ! defined( 'WP_CLI' ) ) {
    // Allow unauthenticated for the first run since we have the token
}

$actions = [];

// ── 1. Activate plugin ─────────────────────────────────────────────────────
$plugin_file = 'replanta-ai-chat/replanta-ai-chat.php';
if ( ! is_plugin_active( $plugin_file ) ) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $result = activate_plugin( $plugin_file );
    $actions[] = is_wp_error( $result )
        ? 'ERROR activating plugin: ' . $result->get_error_message()
        : 'Plugin activated OK';
} else {
    $actions[] = 'Plugin already active';
}

// ── 2. Run installer (create tables) ──────────────────────────────────────
if ( class_exists( 'Replanta\AiChat\Installer' ) ) {
    \Replanta\AiChat\Installer::activate();
    $actions[] = 'DB tables created / updated';
}

// ── 3. Pre-configure options ───────────────────────────────────────────────

// General
update_option( 'replanta_ai_chat_general', [
    'assistant_name'  => 'Asistente Banban',
    'welcome_message' => '¡Hola! Soy el asistente de Banban Cosmetics. ¿Te ayudo a encontrar el producto perfecto para ti?',
    'widget_position' => 'bottom-right',
    'primary_color'   => '#c8a882',
    'show_on'         => 'all',
    'chat_enabled'    => true,
], false );
$actions[] = 'General config set';

// Provider — keys from wp-config.php constants (never hardcoded here)
update_option( 'replanta_ai_chat_provider', [
    'llm_provider'     => 'anthropic',
    'anthropic_key'    => defined( 'REPLANTA_ANTHROPIC_KEY' ) ? '' : '',  // Read from constant
    'anthropic_model'  => 'claude-sonnet-4-6',
    'openai_key'       => '',
    'openai_llm_model' => 'gpt-4o',
    'embeddings_key'   => '',
    'temperature'      => 0.2,
    'max_tokens'       => 1024,
    'monthly_budget'   => 0,
], false );
$actions[] = 'Provider config set (keys via wp-config.php constants)';

// ACF fields mapping for Banban
update_option( 'replanta_ai_chat_indexing', [
    'acf_fields' => [
        [ 'key' => 'sellos',              'label' => 'Sellos y certificaciones' ],
        [ 'key' => 'aliados',             'label' => 'Aliados del producto' ],
        [ 'key' => 'modo_de_uso',         'label' => 'Modo de uso' ],
        [ 'key' => 'principios_naturales','label' => 'Principios naturales activos' ],
        [ 'key' => 'ideal_para',          'label' => 'Ideal para' ],
        [ 'key' => 'resultados',          'label' => 'Resultados' ],
        [ 'key' => 'composicion',         'label' => 'Composición e ingredientes' ],
    ],
    'meta_fields'          => [],
    'post_types'           => [ 'product' ],
    'index_out_of_stock'   => true,
    'auto_index'           => true,
    'exclude_cats'         => [],
], false );
$actions[] = 'ACF field mapping configured (sellos, aliados, modo_de_uso, principios_naturales, ideal_para, resultados, composicion)';

// Behaviour
update_option( 'replanta_ai_chat_behaviour', [
    'system_prompt_extra'  => 'Eres el asistente de Banban Cosmetics, una marca de cosmética natural. Responde siempre en español. Sé amigable y profesional. Si el cliente pregunta por ingredientes INCI, responde con claridad pero sin tecnicismos innecesarios.',
    'fallback_message'     => 'No tengo esa información. ¿Quieres que te pase con uno de nuestros asesores?',
    'claims_blacklist'     => "cura\ntrata\nmedicamento\nprescripción\ndiagnóstico\nterapéutico\nfarmacéutico",
    'max_context_products' => 5,
    'escalation_email'     => get_option( 'admin_email' ),
    'language'             => 'es',
], false );
$actions[] = 'Behaviour config set';

// Tools
update_option( 'replanta_ai_chat_tools', [
    'cart_enabled'       => true,
    'order_enabled'      => true,
    'escalation_enabled' => true,
    'search_enabled'     => true,
], false );
$actions[] = 'Tools config set';

// ── 4. Output results ──────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Replanta Setup</title>
<style>body{font-family:monospace;max-width:700px;margin:40px auto;padding:20px}
h1{color:#2d6a4f}.ok{color:green}.err{color:red}
.warning{background:#fff3cd;border:1px solid #ffc107;padding:16px;border-radius:6px;margin-top:20px}
</style>
</head>
<body>
<h1>Replanta AI Chat — Setup</h1>
<ul>
<?php foreach ( $actions as $action ) : ?>
    <li class="<?php echo strpos( $action, 'ERROR' ) !== false ? 'err' : 'ok'; ?>">
        <?php echo esc_html( $action ); ?>
    </li>
<?php endforeach; ?>
</ul>

<div class="warning">
    <strong>⚠️ IMPORTANTE — Pasos finales:</strong>
    <ol>
        <li>Verifica que <code>wp-config.php</code> contiene las constantes <code>REPLANTA_ANTHROPIC_KEY</code> y <code>REPLANTA_OPENAI_KEY</code></li>
        <li>Ve a <strong>AI Chat → Indexación</strong> en el admin y pulsa "Reindexar todo el catálogo"</li>
        <li><strong>Borra este archivo</strong> inmediatamente: <code>wp-content/replanta-setup.php</code></li>
    </ol>
</div>

<p style="margin-top:20px">
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=replanta-ai-chat' ) ); ?>">
        → Ir al panel de Replanta AI Chat
    </a>
</p>
</body>
</html>
<?php
// Self-delete option (uncomment to auto-delete after successful run)
// unlink( __FILE__ );
