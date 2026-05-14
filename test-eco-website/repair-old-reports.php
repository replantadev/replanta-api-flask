<?php
/**
 * Script de reparación para informes antiguos sin meta fields
 * 
 * Uso: Añade ?repair_tew_reports en la URL del admin
 * Ejemplo: /wp-admin/?repair_tew_reports
 */

add_action( 'init', function() {
    if ( ! isset( $_GET['repair_tew_reports'] ) || ! current_user_can( 'manage_options' ) ) {
        return;
    }

    echo '<pre style="background: #1e1e1e; color: #d4d4d4; padding: 20px; margin: 20px; font-family: monospace; font-size: 13px; line-height: 1.5; overflow-x: auto;">';
    echo "=== REPARACIÓN DE INFORMES TEW ===\n\n";

    // Obtener todos los informes
    $query = new WP_Query( [
        'post_type'      => 'tew_audit',
        'posts_per_page' => -1,
        'post_status'    => 'any',
    ] );

    if ( ! $query->have_posts() ) {
        echo "[ERROR] No se encontraron informes para reparar\n";
        echo '</pre>';
        exit;
    }

    $total = $query->post_count;
    $repaired = 0;
    $skipped = 0;

    echo "[INFO] Encontrados {$total} informes\n\n";

    foreach ( $query->posts as $post ) {
        $post_id = $post->ID;
        echo "Procesando #{$post_id} - {$post->post_title}\n";

        // Verificar si ya tiene score
        $current_score = get_post_meta( $post_id, '_tew_report_score', true );
        $current_is_green = get_post_meta( $post_id, '_tew_report_is_green', true );

        if ( ! empty( $current_score ) && $current_score !== '0' && $current_score !== 0 
             && ( $current_is_green === '1' || $current_is_green === '0' ) ) {
            echo "   [OK] Ya tiene meta fields correctos (score: {$current_score}, verde: {$current_is_green})\n";
            $skipped++;
            continue;
        }

        // Obtener payload
        $payload_raw = get_post_meta( $post_id, '_tew_report_payload', true );
        
        if ( empty( $payload_raw ) ) {
            echo "   [SKIP] Sin payload JSON - omitiendo\n";
            $skipped++;
            continue;
        }

        $payload = json_decode( $payload_raw, true );
        
        if ( ! $payload ) {
            echo "   [SKIP] JSON invalido - omitiendo\n";
            $skipped++;
            continue;
        }

        $updated = false;

        // Extraer y guardar SCORE
        $score = null;
        if ( isset( $payload['summary']['score'] ) ) {
            $score = (float) $payload['summary']['score'];
        } elseif ( isset( $payload['summary']['overall_score'] ) ) {
            $score = (float) $payload['summary']['overall_score'];
        }

        if ( null !== $score && ( empty( $current_score ) || $current_score === '0' || $current_score === 0 ) ) {
            update_post_meta( $post_id, '_tew_report_score', $score );
            echo "   [OK] Score actualizado: {$score}\n";
            $updated = true;
        }

        // Extraer y guardar GRADE
        if ( isset( $payload['summary']['grade'] ) ) {
            update_post_meta( $post_id, '_tew_report_grade', sanitize_text_field( $payload['summary']['grade'] ) );
            echo "   [OK] Grade actualizado: {$payload['summary']['grade']}\n";
            $updated = true;
        }

        // Extraer y guardar IS_GREEN
        $is_green = null;
        if ( isset( $payload['metrics']['greenweb']['is_green'] ) ) {
            $is_green = (bool) $payload['metrics']['greenweb']['is_green'];
        } elseif ( isset( $payload['metrics']['green_hosting']['is_green'] ) ) {
            $is_green = (bool) $payload['metrics']['green_hosting']['is_green'];
        }

        if ( null !== $is_green && empty( $current_is_green ) ) {
            update_post_meta( $post_id, '_tew_report_is_green', $is_green ? '1' : '0' );
            echo "   [OK] Hosting verde actualizado: " . ( $is_green ? 'SI' : 'NO' ) . "\n";
            $updated = true;
        }

        // Extraer y guardar HOSTING PROVIDER
        $provider = null;
        if ( isset( $payload['metrics']['greenweb']['provider'] ) && $payload['metrics']['greenweb']['provider'] !== 'N/A' ) {
            $provider = $payload['metrics']['greenweb']['provider'];
        } elseif ( isset( $payload['metrics']['green_hosting']['hosted_by'] ) ) {
            $provider = $payload['metrics']['green_hosting']['hosted_by'];
        } elseif ( isset( $payload['metrics']['green_hosting']['hosting_provider'] ) ) {
            $provider = $payload['metrics']['green_hosting']['hosting_provider'];
        }

        if ( $provider ) {
            update_post_meta( $post_id, '_tew_report_hosting_provider', sanitize_text_field( $provider ) );
            echo "   [OK] Provider actualizado: {$provider}\n";
            $updated = true;
        }

        // Extraer y guardar CO2
        $co2 = null;
        if ( isset( $payload['metrics']['websitecarbon']['co2_per_view'] ) ) {
            $co2 = (float) $payload['metrics']['websitecarbon']['co2_per_view'];
        } elseif ( isset( $payload['metrics']['carbon']['co2_per_view'] ) ) {
            $co2 = (float) $payload['metrics']['carbon']['co2_per_view'];
        }

        if ( null !== $co2 ) {
            update_post_meta( $post_id, '_tew_report_co2_per_view', $co2 );
            echo "   [OK] CO2 actualizado: {$co2}g\n";
            $updated = true;
        }

        // Extraer y guardar GENERATED DATE
        if ( isset( $payload['metadata']['generated_at'] ) ) {
            update_post_meta( $post_id, '_tew_report_generated', $payload['metadata']['generated_at'] );
            echo "   [OK] Fecha actualizada: {$payload['metadata']['generated_at']}\n";
            $updated = true;
        }

        // Marcar como PUBLICO si no lo esta (para que aparezca en showcase)
        $is_public = get_post_meta( $post_id, '_tew_is_public', true );
        if ( empty( $is_public ) ) {
            update_post_meta( $post_id, '_tew_is_public', '1' );
            echo "   [OK] Marcado como publico para showcase\n";
            $updated = true;
        }

        if ( $updated ) {
            $repaired++;
            echo "   [DONE] Informe reparado\n";
        } else {
            $skipped++;
            echo "   [SKIP] Sin cambios necesarios\n";
        }

        echo "\n";
    }

    echo "=== RESUMEN ===\n\n";
    echo "Total de informes: {$total}\n";
    echo "Reparados: {$repaired}\n";
    echo "Omitidos: {$skipped}\n\n";
    echo "Proceso completado\n";

    echo '</pre>';
    exit;
} );
