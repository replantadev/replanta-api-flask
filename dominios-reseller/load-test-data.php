<?php
/**
 * Script para cargar datos de prueba del Forest Program
 * Ejecutar una sola vez desde WP-CLI o acceso directo
 * 
 * Uso: wp eval-file load-test-data.php
 * O acceder a: /wp-content/plugins/dominios-reseller/load-test-data.php?run=1
 */

// Si se ejecuta directamente, cargar WordPress
if ( ! defined( 'ABSPATH' ) ) {
    // Buscar wp-load.php subiendo directorios
    $wp_load = dirname( __FILE__ );
    for ( $i = 0; $i < 10; $i++ ) {
        if ( file_exists( $wp_load . '/wp-load.php' ) ) {
            require_once $wp_load . '/wp-load.php';
            break;
        }
        $wp_load = dirname( $wp_load );
    }
}

// Verificar que estamos en WordPress y con permisos
if ( ! defined( 'ABSPATH' ) ) {
    die( 'WordPress no encontrado' );
}

// Solo admin puede ejecutar esto
if ( ! current_user_can( 'manage_options' ) && php_sapi_name() !== 'cli' ) {
    die( 'Acceso denegado' );
}

// Verificar parámetro de ejecución
if ( php_sapi_name() !== 'cli' && empty( $_GET['run'] ) ) {
    echo '<h2>Cargar datos de prueba Forest Program</h2>';
    echo '<p>Este script cargará dominios de ejemplo con datos de Upmind.</p>';
    echo '<p><strong>¿Estás seguro?</strong></p>';
    echo '<a href="?run=1" style="background:#16a34a;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;">Sí, cargar datos</a>';
    exit;
}

global $wpdb;
$table = $wpdb->prefix . 'dominios_reseller';

// Fecha base: 14 marzo 2026
$base_date = '2026-03-14';

// Datos de prueba extraídos de Upmind (solo activos + anuales + roble/cedro/sauce)
$test_domains = [
    // Roble - Anuales Activos
    [
        'domain'              => 'cogenspain.org',
        'upmind_client_name'  => 'Julio Iñigo Artiñano',
        'upmind_client_email' => 'julio@cogenspain.org',
        'upmind_product_slug' => 'roble',
        'billing_cycle'       => 'annual',
        'next_renewal_date'   => '2027-01-14', // in 10 months
        'forest_enabled'      => 1,
        'server'              => 'sp-uk',
    ],
    [
        'domain'              => 'legumbresdezamora.com',
        'upmind_client_name'  => 'Salvador Carrera',
        'upmind_client_email' => 'salvador@legumbresdezamora.com',
        'upmind_product_slug' => 'roble',
        'billing_cycle'       => 'annual',
        'next_renewal_date'   => '2026-12-14', // in 9 months
        'forest_enabled'      => 1,
        'server'              => 'sp-uk',
    ],
    [
        'domain'              => 'estegrafico.com',
        'upmind_client_name'  => 'Luis Javier Gil',
        'upmind_client_email' => 'luis@estegrafico.com',
        'upmind_product_slug' => 'roble',
        'billing_cycle'       => 'annual',
        'next_renewal_date'   => '2026-04-12', // renews soon
        'forest_enabled'      => 1,
        'server'              => 'sp-uk',
    ],
    
    // Sauce - Anuales Activos
    [
        'domain'              => 'crawla.agency',
        'upmind_client_name'  => 'Florencia Estévez',
        'upmind_client_email' => 'florencia@crawla.agency',
        'upmind_product_slug' => 'sauce',
        'billing_cycle'       => 'annual',
        'next_renewal_date'   => '2026-09-14', // in 6 months
        'forest_enabled'      => 1,
        'server'              => 'sp-us',
    ],
    [
        'domain'              => 'sergiomorenogarrido.com',
        'upmind_client_name'  => 'Sergio Moreno',
        'upmind_client_email' => 'sergio@sergiomorenogarrido.com',
        'upmind_product_slug' => 'sauce',
        'billing_cycle'       => 'annual',
        'next_renewal_date'   => '2026-09-14', // in 6 months
        'forest_enabled'      => 1,
        'server'              => 'sp-uk',
    ],
    [
        'domain'              => 'carani.es',
        'upmind_client_name'  => 'Ana Barrón',
        'upmind_client_email' => 'ana@carani.es',
        'upmind_product_slug' => 'sauce',
        'billing_cycle'       => 'annual',
        'next_renewal_date'   => '2026-07-14', // in 4 months
        'forest_enabled'      => 1,
        'server'              => 'sp-uk',
    ],
    [
        'domain'              => 'cabanaabogados.com',
        'upmind_client_name'  => 'Juan Cabana',
        'upmind_client_email' => 'juan@cabanaabogados.com',
        'upmind_product_slug' => 'sauce',
        'billing_cycle'       => 'annual',
        'next_renewal_date'   => '2026-08-14', // in 5 months
        'forest_enabled'      => 1,
        'server'              => 'sp-us',
    ],
    [
        'domain'              => 'pitagoras.edu.co',
        'upmind_client_name'  => 'Francisco Herrera',
        'upmind_client_email' => 'francisco@pitagoras.edu.co',
        'upmind_product_slug' => 'sauce',
        'billing_cycle'       => 'annual',
        'next_renewal_date'   => '2027-03-14', // in a year
        'forest_enabled'      => 1,
        'server'              => 'sp-us',
    ],
    [
        'domain'              => 'laolimpo.com',
        'upmind_client_name'  => 'Alfonso Parra',
        'upmind_client_email' => 'alfonso@laolimpo.com',
        'upmind_product_slug' => 'sauce',
        'billing_cycle'       => 'annual',
        'next_renewal_date'   => '2026-03-31', // in 17 days
        'forest_enabled'      => 1,
        'server'              => 'sp-uk',
    ],
    [
        'domain'              => 'bernaladriana.com',
        'upmind_client_name'  => 'Alfonso Parra',
        'upmind_client_email' => 'alfonso@bernaladriana.com',
        'upmind_product_slug' => 'sauce',
        'billing_cycle'       => 'annual',
        'next_renewal_date'   => '2026-08-14', // in 5 months
        'forest_enabled'      => 1,
        'server'              => 'sp-uk',
    ],
    [
        'domain'              => 'alfonsoparra.com',
        'upmind_client_name'  => 'Alfonso Parra',
        'upmind_client_email' => 'alfonso@alfonsoparra.com',
        'upmind_product_slug' => 'sauce',
        'billing_cycle'       => 'annual',
        'next_renewal_date'   => '2026-08-14', // in 5 months
        'forest_enabled'      => 1,
        'server'              => 'sp-uk',
    ],
    [
        'domain'              => 'damiangomez.es',
        'upmind_client_name'  => 'Damián Gomez',
        'upmind_client_email' => 'damian@damiangomez.es',
        'upmind_product_slug' => 'sauce',
        'billing_cycle'       => 'annual',
        'next_renewal_date'   => '2027-02-18', // renews in ~11 months (renews 24 days ago = ya renovó)
        'forest_enabled'      => 1,
        'server'              => 'sp-uk',
    ],
    [
        'domain'              => 'bellezabeatriz.com',
        'upmind_client_name'  => 'Beatriz Sanjuan Ramón',
        'upmind_client_email' => 'beatriz@bellezabeatriz.com',
        'upmind_product_slug' => 'sauce',
        'billing_cycle'       => 'annual',
        'next_renewal_date'   => '2027-02-14', // in a year
        'forest_enabled'      => 1,
        'server'              => 'sp-uk',
    ],
    [
        'domain'              => 'adfc.com.co',
        'upmind_client_name'  => 'Jorge Mario Vera',
        'upmind_client_email' => 'jorge@adfc.com.co',
        'upmind_product_slug' => 'sauce',
        'billing_cycle'       => 'annual',
        'next_renewal_date'   => '2027-04-02', // renews in ~1 year
        'forest_enabled'      => 1,
        'server'              => 'sp-uk',
    ],
    
    // Cedro - Anuales Activos
    [
        'domain'              => 'maquistoresas.com',
        'upmind_client_name'  => 'Anderson Vidal',
        'upmind_client_email' => 'anderson@maquistoresas.com',
        'upmind_product_slug' => 'cedro',
        'billing_cycle'       => 'annual',
        'next_renewal_date'   => '2026-08-14', // in 5 months
        'forest_enabled'      => 1,
        'server'              => 'sp-us',
    ],
    
    // ═══════════════════════════════════════════════════════════
    // Dominios NO elegibles (para probar filtros)
    // ═══════════════════════════════════════════════════════════
    
    // Mensual - NO elegible
    [
        'domain'              => 'zynco.cloud',
        'upmind_client_name'  => 'Guillaume MATA',
        'upmind_client_email' => 'guillaume@zynco.cloud',
        'upmind_product_slug' => 'roble',
        'billing_cycle'       => 'monthly',
        'next_renewal_date'   => '2026-04-14',
        'forest_enabled'      => 0,
        'server'              => 'sp-uk',
    ],
    
    // Sin producto asignado
    [
        'domain'              => 'ejemplo-sin-plan.com',
        'upmind_client_name'  => 'Test User',
        'upmind_client_email' => 'test@ejemplo.com',
        'upmind_product_slug' => null,
        'billing_cycle'       => null,
        'next_renewal_date'   => null,
        'forest_enabled'      => 0,
        'server'              => 'sp-uk',
    ],
];

// Insertar o actualizar dominios
$inserted = 0;
$updated  = 0;
$errors   = 0;

echo "<pre>\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "  CARGANDO DATOS DE PRUEBA - FOREST PROGRAM\n";
echo "═══════════════════════════════════════════════════════════\n\n";

foreach ( $test_domains as $domain_data ) {
    $domain = $domain_data['domain'];
    
    // Verificar si ya existe
    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$table} WHERE domain = %s",
        $domain
    ) );
    
    if ( $existing ) {
        // Actualizar
        $result = $wpdb->update(
            $table,
            [
                'upmind_client_name'  => $domain_data['upmind_client_name'],
                'upmind_client_email' => $domain_data['upmind_client_email'],
                'upmind_product_slug' => $domain_data['upmind_product_slug'],
                'billing_cycle'       => $domain_data['billing_cycle'],
                'next_renewal_date'   => $domain_data['next_renewal_date'],
                'forest_enabled'      => $domain_data['forest_enabled'],
            ],
            [ 'id' => $existing ],
            [ '%s', '%s', '%s', '%s', '%s', '%d' ],
            [ '%d' ]
        );
        
        if ( $result !== false ) {
            echo "✓ Actualizado: {$domain}\n";
            $updated++;
        } else {
            echo "✗ Error actualizando: {$domain} - {$wpdb->last_error}\n";
            $errors++;
        }
    } else {
        // Insertar nuevo
        $result = $wpdb->insert(
            $table,
            [
                'domain'              => $domain,
                'server'              => $domain_data['server'] ?? 'sp-uk',
                'upmind_client_name'  => $domain_data['upmind_client_name'],
                'upmind_client_email' => $domain_data['upmind_client_email'],
                'upmind_product_slug' => $domain_data['upmind_product_slug'],
                'billing_cycle'       => $domain_data['billing_cycle'],
                'next_renewal_date'   => $domain_data['next_renewal_date'],
                'forest_enabled'      => $domain_data['forest_enabled'],
                'trees_planted'       => 0,
                'created_at'          => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' ]
        );
        
        if ( $result ) {
            echo "✓ Insertado: {$domain}\n";
            $inserted++;
        } else {
            echo "✗ Error insertando: {$domain} - {$wpdb->last_error}\n";
            $errors++;
        }
    }
}

echo "\n═══════════════════════════════════════════════════════════\n";
echo "  RESUMEN\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "Insertados: {$inserted}\n";
echo "Actualizados: {$updated}\n";
echo "Errores: {$errors}\n";

// Estadísticas
$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
$eligible = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE upmind_product_slug IN ('roble','cedro','sauce') AND billing_cycle = 'annual'" );
$forest_on = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE forest_enabled = 1" );

echo "\n═══════════════════════════════════════════════════════════\n";
echo "  ESTADÍSTICAS ACTUALES\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "Total dominios: {$total}\n";
echo "Elegibles (anual + roble/cedro/sauce): {$eligible}\n";
echo "Forest habilitado: {$forest_on}\n";
echo "\n</pre>";

// Link para volver al admin
if ( php_sapi_name() !== 'cli' ) {
    $admin_url = admin_url( 'admin.php?page=dr-forest' );
    echo "<p><a href='{$admin_url}' style='background:#3b82f6;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;'>← Volver al Forest Program</a></p>";
}
