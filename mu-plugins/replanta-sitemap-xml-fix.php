<?php
/**
 * Plugin Name: Replanta Sitemap XML Fix
 * Description: Elimina BOM y whitespace del inicio de sitemaps
 * Version: 1.2
 */

// Fix agresivo para cualquier output de sitemap
add_action('rank_math/sitemap/output', function($output) {
    // Eliminar BOM
    $output = preg_replace('/^\xEF\xBB\xBF/', '', $output);
    // Eliminar cualquier whitespace al inicio
    $output = ltrim($output);
    return $output;
}, 1);

// Capturar al inicio del sitemap
add_action('rank_math/sitemap/index', function() {
    ob_start(function($buffer) {
        // Eliminar BOM
        $buffer = preg_replace('/^\xEF\xBB\xBF/', '', $buffer);
        // Eliminar whitespace al inicio
        $buffer = ltrim($buffer);
        return $buffer;
    });
}, 1);

// Hook muy temprano para sitemap requests
add_action('template_redirect', function() {
    if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'sitemap') !== false) {
        // Limpiar cualquier output previo
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Iniciar buffer limpio
        ob_start(function($buffer) {
            // Eliminar BOM y whitespace
            $buffer = preg_replace('/^\xEF\xBB\xBF/', '', $buffer);
            $buffer = ltrim($buffer);
            return $buffer;
        });
    }
}, 1);

// Hook final antes de enviar headers
add_action('send_headers', function() {
    if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'sitemap') !== false) {
        // Headers correctos para XML
        if (!headers_sent()) {
            header('Content-Type: application/xml; charset=UTF-8');
            header('X-Robots-Tag: noindex, follow');
        }
    }
}, 1);
