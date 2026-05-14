<?php
/**
 * Script para completar traducciones: plantillas, referencias y menús
 * 
 * IMPORTANTE: Este script solo debe ejecutarse desde WP-CLI, NO desde web directamente.
 * 
 * Uso correcto:
 *   wp eval-file fix-all-translations.php
 * 
 * O con dry-run:
 *   wp eval-file fix-all-translations.php --dry-run
 */

// ============================================
// SEGURIDAD: Bloquear acceso directo desde web
// ============================================
if (php_sapi_name() !== 'cli' && !defined('WP_CLI')) {
    // Si se accede desde web sin contexto WP-CLI
    if (!defined('ABSPATH')) {
        http_response_code(403);
        die('Acceso denegado. Este script solo puede ejecutarse desde WP-CLI.');
    }
    
    // Si está en contexto WP pero no es admin
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos para ejecutar este script.', 'Error de permisos', ['response' => 403]);
    }
}

// ============================================
// Configurar entorno si estamos en CLI
// ============================================
if (php_sapi_name() === 'cli' && !defined('ABSPATH')) {
    // Simular entorno HTTP para que Polylang funcione desde CLI
    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'replanta.net';
    $_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
    $_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'replanta.net';
    $_SERVER['SERVER_PORT'] = $_SERVER['SERVER_PORT'] ?? '443';
    $_SERVER['HTTPS'] = $_SERVER['HTTPS'] ?? 'on';
    $_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    // Cargar WordPress
    $wp_load_paths = [
        dirname(__FILE__) . '/../../../wp-load.php',
        '/home/replanta/replanta.net/wp-load.php',
        '/var/www/replanta.net/wp-load.php',
        '/var/www/html/wp-load.php',
    ];
    
    $loaded = false;
    foreach ($wp_load_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $loaded = true;
            break;
        }
    }
    
    if (!$loaded) {
        die("No se pudo cargar WordPress\n");
    }
}

// ============================================
// Verificar dependencias
// ============================================
if (!class_exists('Replanta_Auto_Translate')) {
    die("El plugin Replanta Auto Translate no está activo\n");
}

// Verificar Polylang completamente inicializado
if (!function_exists('pll_get_post') || !function_exists('PLL')) {
    die("Polylang no está instalado o activo\n");
}

// Esperar a que Polylang esté completamente listo
$pll = PLL();
if (!$pll || !isset($pll->model) || !$pll->model) {
    // Intentar forzar inicialización completa
    if (!did_action('pll_init')) {
        do_action('pll_init');
    }
    if (!did_action('init')) {
        do_action('init');
    }
    
    // Re-verificar
    $pll = PLL();
    if (!$pll || !isset($pll->model) || !$pll->model) {
        die("Error: Polylang no está completamente inicializado. Ejecuta con: wp eval-file fix-all-translations.php\n");
    }
}

// Configuración
$source_lang = 'es';
$target_lang = 'en';
$dry_run = false; // Cambiar a true para simular sin hacer cambios

// ============================================
// Helper function para llamadas seguras a Polylang
// ============================================
function safe_pll_get_post($post_id, $lang) {
    if (!function_exists('pll_get_post')) {
        return null;
    }
    
    // Verificar que el modelo de Polylang está disponible
    $pll = function_exists('PLL') ? PLL() : null;
    if (!$pll || !isset($pll->model) || !$pll->model) {
        return null;
    }
    
    try {
        return pll_get_post($post_id, $lang);
    } catch (Exception $e) {
        error_log('[Replanta Auto Translate] Error en pll_get_post: ' . $e->getMessage());
        return null;
    }
}

function safe_pll_get_term($term_id, $lang) {
    if (!function_exists('pll_get_term')) {
        return null;
    }
    
    $pll = function_exists('PLL') ? PLL() : null;
    if (!$pll || !isset($pll->model) || !$pll->model) {
        return null;
    }
    
    try {
        return pll_get_term($term_id, $lang);
    } catch (Exception $e) {
        error_log('[Replanta Auto Translate] Error en pll_get_term: ' . $e->getMessage());
        return null;
    }
}

echo "=== REPLANTA AUTO TRANSLATE - FIX ALL ===\n";
echo "Fuente: $source_lang → Destino: $target_lang\n";
echo "Modo: " . ($dry_run ? "SIMULACIÓN" : "EJECUCIÓN") . "\n\n";

// Instancias necesarias
$polylang = Replanta_Auto_Translate_Polylang_Bridge::instance();
$parser = Replanta_Auto_Translate_Elementor_Parser::instance();
$translator = Replanta_Auto_Translate_Translator::instance();
$processor = Replanta_Auto_Translate_Bulk_Processor::instance();

// ============================================
// PASO 1: TRADUCIR PLANTILLAS (elementor_library)
// ============================================
echo "--- PASO 1: TRADUCIR PLANTILLAS ---\n";

$templates = get_posts([
    'post_type' => 'elementor_library',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'lang' => $source_lang,
]);

echo "Plantillas en $source_lang: " . count($templates) . "\n";

$templates_translated = 0;
$templates_skipped = 0;
$templates_errors = 0;

foreach ($templates as $template) {
    // Verificar si ya tiene traducción (usando función segura)
    $existing = safe_pll_get_post($template->ID, $target_lang);
    
    if ($existing) {
        echo "  [SKIP] #{$template->ID} '{$template->post_title}' - ya tiene traducción EN #{$existing}\n";
        $templates_skipped++;
        continue;
    }
    
    echo "  [TRAD] #{$template->ID} '{$template->post_title}'... ";
    
    if ($dry_run) {
        echo "SIMULADO\n";
        $templates_translated++;
        continue;
    }
    
    // Traducir la plantilla
    $result = $processor->translate_single_post($template->ID);
    
    if (is_wp_error($result)) {
        echo "ERROR: " . $result->get_error_message() . "\n";
        $templates_errors++;
    } else {
        echo "OK → #{$result['new_post_id']}\n";
        $templates_translated++;
        
        // Pequeña pausa para no saturar la API
        sleep(1);
    }
}

echo "\nPlantillas: $templates_translated traducidas, $templates_skipped ya existían, $templates_errors errores\n\n";

// ============================================
// PASO 2: ACTUALIZAR REFERENCIAS EN PÁGINAS EN
// ============================================
echo "--- PASO 2: ACTUALIZAR REFERENCIAS EN PÁGINAS EN ---\n";

// Obtener todas las páginas en inglés
$en_pages = get_posts([
    'post_type' => ['page', 'post'],
    'post_status' => ['publish', 'draft'],
    'posts_per_page' => -1,
    'lang' => $target_lang,
]);

echo "Páginas/Posts en $target_lang: " . count($en_pages) . "\n";

$pages_updated = 0;
$pages_skipped = 0;

foreach ($en_pages as $page) {
    $updated_content = false;
    $updated_elementor = false;
    
    // 1. Actualizar shortcodes en post_content
    $new_content = $parser->update_content_template_references($page->post_content, $target_lang);
    if ($new_content !== $page->post_content) {
        $updated_content = true;
        if (!$dry_run) {
            wp_update_post([
                'ID' => $page->ID,
                'post_content' => $new_content,
            ]);
        }
    }
    
    // 2. Actualizar referencias en Elementor data
    $elementor_data = get_post_meta($page->ID, '_elementor_data', true);
    if ($elementor_data) {
        $data_array = json_decode($elementor_data, true);
        if (is_array($data_array)) {
            $new_data = $parser->update_template_references($data_array, $target_lang);
            $new_json = json_encode($new_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            
            if ($new_json !== $elementor_data) {
                $updated_elementor = true;
                if (!$dry_run) {
                    update_post_meta($page->ID, '_elementor_data', wp_slash($new_json));
                    
                    // Limpiar caché de Elementor
                    if (class_exists('\Elementor\Plugin')) {
                        \Elementor\Plugin::$instance->files_manager->clear_cache();
                    }
                }
            }
        }
    }
    
    if ($updated_content || $updated_elementor) {
        $changes = [];
        if ($updated_content) $changes[] = 'content';
        if ($updated_elementor) $changes[] = 'elementor';
        echo "  [UPD] #{$page->ID} '{$page->post_title}' - " . implode(', ', $changes) . "\n";
        $pages_updated++;
    } else {
        $pages_skipped++;
    }
}

echo "\nPáginas: $pages_updated actualizadas, $pages_skipped sin cambios\n\n";

// ============================================
// PASO 3: CONFIGURAR MENÚS
// ============================================
echo "--- PASO 3: CONFIGURAR MENÚS ---\n";

// Obtener menús en español
$es_menus = get_terms([
    'taxonomy' => 'nav_menu',
    'hide_empty' => false,
]);

// Filtrar menús por idioma
$es_menu_list = [];
$en_menu_list = [];

foreach ($es_menus as $menu) {
    $menu_lang = pll_get_term_language($menu->term_id);
    if ($menu_lang === $source_lang) {
        $es_menu_list[$menu->term_id] = $menu;
    } elseif ($menu_lang === $target_lang) {
        $en_menu_list[$menu->term_id] = $menu;
    }
}

echo "Menús en ES: " . count($es_menu_list) . "\n";
echo "Menús en EN: " . count($en_menu_list) . "\n";

// Para cada menú ES, verificar si tiene traducción EN y si está vacío
foreach ($es_menu_list as $es_menu_id => $es_menu) {
    $en_menu_id = safe_pll_get_term($es_menu_id, $target_lang);
    
    echo "\n  Menú ES #{$es_menu_id} '{$es_menu->name}':\n";
    
    if (!$en_menu_id) {
        echo "    [!] No tiene menú EN vinculado\n";
        continue;
    }
    
    $en_menu = get_term($en_menu_id, 'nav_menu');
    echo "    Vinculado a EN #{$en_menu_id} '{$en_menu->name}'\n";
    
    // Verificar si el menú EN está vacío
    $en_items = wp_get_nav_menu_items($en_menu_id);
    $es_items = wp_get_nav_menu_items($es_menu_id);
    
    echo "    Items ES: " . count($es_items ?: []) . " | Items EN: " . count($en_items ?: []) . "\n";
    
    if (empty($en_items) && !empty($es_items)) {
        echo "    [POBLAR] Copiando items del menú ES al EN...\n";
        
        if (!$dry_run) {
            foreach ($es_items as $item) {
                // Obtener la página traducida si el item apunta a una página
                $translated_object_id = $item->object_id;
                if ($item->type === 'post_type') {
                    $tr_id = safe_pll_get_post($item->object_id, $target_lang);
                    if ($tr_id) {
                        $translated_object_id = $tr_id;
                    }
                }
                
                // Traducir el título del item
                $translated_title = $translator->translate_text($item->title, $source_lang, $target_lang);
                if (is_wp_error($translated_title)) {
                    $translated_title = $item->title;
                }
                
                // Crear el item en el menú EN
                $new_item_data = [
                    'menu-item-object-id' => $translated_object_id,
                    'menu-item-object' => $item->object,
                    'menu-item-parent-id' => 0, // Se actualizará después para jerarquía
                    'menu-item-position' => $item->menu_order,
                    'menu-item-type' => $item->type,
                    'menu-item-title' => $translated_title,
                    'menu-item-url' => $item->url,
                    'menu-item-target' => $item->target,
                    'menu-item-classes' => implode(' ', $item->classes),
                    'menu-item-status' => 'publish',
                ];
                
                $new_item_id = wp_update_nav_menu_item($en_menu_id, 0, $new_item_data);
                
                if (!is_wp_error($new_item_id)) {
                    echo "      + '{$translated_title}' → #{$new_item_id}\n";
                } else {
                    echo "      ! Error: " . $new_item_id->get_error_message() . "\n";
                }
                
                // Pausa para API de traducción
                usleep(500000); // 0.5 segundos
            }
        }
    } else {
        echo "    [OK] El menú EN ya tiene items\n";
    }
}

// ============================================
// RESUMEN FINAL
// ============================================
echo "\n\n=== RESUMEN ===\n";
echo "Plantillas traducidas: $templates_translated\n";
echo "Páginas actualizadas: $pages_updated\n";
echo "Modo: " . ($dry_run ? "SIMULACIÓN (no se hicieron cambios reales)" : "EJECUCIÓN COMPLETADA") . "\n";
echo "\n¡Proceso completado!\n";
