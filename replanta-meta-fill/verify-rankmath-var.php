<?php
/**
 * Script para verificar y forzar registro de variable Rank Math
 * Subir a: /wp-content/plugins/replanta-meta-fill/
 * Ejecutar: wp eval-file verify-rankmath-var.php
 * O visitar: https://tu-sitio.com/wp-content/plugins/replanta-meta-fill/verify-rankmath-var.php
 */

// Cargar WordPress
if (!defined('ABSPATH')) {
    require_once '../../../wp-load.php';
}

echo "=== VERIFICACIÓN RANK MATH VARIABLE ===\n\n";

// 1. Verificar que Rank Math está activo
if (!class_exists('RankMath')) {
    die("❌ ERROR: Rank Math no está instalado o activo\n");
}
echo "✅ Rank Math está activo\n";

// 2. Verificar que nuestro plugin está activo
if (!class_exists('Replanta_Meta_Fill_BetterDocs_SEO')) {
    die("❌ ERROR: Replanta Meta Fill - BetterDocs SEO no está cargado\n");
}
echo "✅ Replanta Meta Fill - BetterDocs SEO está cargado\n\n";

// 3. Obtener variables registradas
$vars = apply_filters('rank_math/vars/register_extra_replacements', []);

echo "Variables Rank Math registradas:\n";
echo str_repeat("-", 50) . "\n";

if (isset($vars['bdcat_description'])) {
    echo "✅ ENCONTRADA: bdcat_description\n";
    echo "   Nombre: " . $vars['bdcat_description']['name'] . "\n";
    echo "   Variable: %" . $vars['bdcat_description']['variable'] . "%\n";
    echo "   Descripción: " . $vars['bdcat_description']['description'] . "\n";
    echo "   Ejemplo: " . $vars['bdcat_description']['example'] . "\n";
} else {
    echo "❌ NO ENCONTRADA: bdcat_description\n";
    echo "   Variables disponibles: " . implode(', ', array_keys($vars)) . "\n";
}

echo "\n" . str_repeat("-", 50) . "\n";

// 4. Probar en una categoría real
$categories = get_terms([
    'taxonomy' => 'doc_category',
    'hide_empty' => false,
    'number' => 1
]);

if (!empty($categories)) {
    $cat = $categories[0];
    echo "\nProbando en categoría real:\n";
    echo "Categoría: " . $cat->name . " (ID: " . $cat->term_id . ")\n";
    
    $meta_desc = get_term_meta($cat->term_id, 'replanta_betterdocs_meta_description', true);
    
    if ($meta_desc) {
        echo "✅ Meta generada: " . substr($meta_desc, 0, 80) . "...\n";
        
        // Simular reemplazo
        $replacements = ['%bdcat_description%' => ''];
        $replacements = apply_filters('rank_math/vars/replacements', $replacements, new stdClass());
        
        if (!empty($replacements['%bdcat_description%'])) {
            echo "✅ Reemplazo funciona: " . substr($replacements['%bdcat_description%'], 0, 80) . "...\n";
        } else {
            echo "⚠️  Reemplazo retorna vacío (puede ser normal si no estás en contexto de categoría)\n";
        }
    } else {
        echo "⚠️  No hay meta descripción generada para esta categoría\n";
        echo "   Genera una primero desde: Docs → Categorías → [Editar]\n";
    }
} else {
    echo "\n⚠️  No hay categorías BetterDocs para probar\n";
}

echo "\n=== FIN DE VERIFICACIÓN ===\n";
echo "\nSi ves ✅ ENCONTRADA arriba, el placeholder está disponible.\n";
echo "Úsalo en Rank Math como: %bdcat_description%\n\n";
