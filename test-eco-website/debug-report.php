<?php
/**
 * Script de diagnóstico para informes TEW
 * 
 * Uso: Añade ?debug_tew_report=ID en la URL del admin
 * Ejemplo: /wp-admin/?debug_tew_report=123
 */

add_action( 'init', function() {
    if ( ! isset( $_GET['debug_tew_report'] ) || ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $post_id = absint( $_GET['debug_tew_report'] );

    echo '<pre style="background: #1e1e1e; color: #d4d4d4; padding: 20px; margin: 20px; font-family: monospace; font-size: 13px; line-height: 1.5; overflow-x: auto;">';
    echo "=== DIAGNÓSTICO INFORME TEW ID: {$post_id} ===\n\n";

    // Verificar que existe
    $post = get_post( $post_id );
    if ( ! $post ) {
        echo "❌ ERROR: No existe post con ID {$post_id}\n";
        echo '</pre>';
        exit;
    }

    echo "✅ Post encontrado\n";
    echo "   Título: {$post->post_title}\n";
    echo "   Tipo: {$post->post_type}\n";
    echo "   Estado: {$post->post_status}\n";
    echo "   Fecha: {$post->post_date}\n\n";

    // META FIELDS
    echo "=== META FIELDS ===\n\n";
    
    $meta_keys = [
        '_tew_report_payload',
        '_tew_report_url',
        '_tew_report_score',
        '_tew_report_grade',
        '_tew_report_is_green',
        '_tew_report_hosting_provider',
        '_tew_report_co2_per_view',
        '_tew_report_generated',
        '_tew_report_user_ip',
        '_tew_report_user_country',
        '_tew_report_user_email',
        '_tew_report_potential',
        '_tew_is_success_case',
        '_tew_is_public',
    ];

    foreach ( $meta_keys as $key ) {
        $value = get_post_meta( $post_id, $key, true );
        
        if ( $key === '_tew_report_payload' ) {
            if ( empty( $value ) ) {
                echo "   {$key}: (vacío)\n";
            } else {
                $decoded = json_decode( $value, true );
                if ( $decoded ) {
                    echo "   {$key}: [JSON válido - " . strlen( $value ) . " chars]\n";
                    
                    // Extraer info clave del payload
                    if ( isset( $decoded['summary'] ) ) {
                        echo "      summary.overall_score: " . ( $decoded['summary']['overall_score'] ?? 'NO EXISTE' ) . "\n";
                        echo "      summary.score: " . ( $decoded['summary']['score'] ?? 'NO EXISTE' ) . "\n";
                        echo "      summary.grade: " . ( $decoded['summary']['grade'] ?? 'NO EXISTE' ) . "\n";
                    }
                    
                    if ( isset( $decoded['metrics']['greenweb'] ) ) {
                        echo "      metrics.greenweb.is_green: " . ( $decoded['metrics']['greenweb']['is_green'] ? 'true' : 'false' ) . "\n";
                        echo "      metrics.greenweb.provider: " . ( $decoded['metrics']['greenweb']['provider'] ?? 'N/A' ) . "\n";
                    }
                    
                    if ( isset( $decoded['metrics']['carbon'] ) ) {
                        echo "      metrics.carbon.co2_per_view: " . ( $decoded['metrics']['carbon']['co2_per_view'] ?? 'N/A' ) . "\n";
                    }
                    
                    if ( isset( $decoded['metrics']['performance'] ) ) {
                        $mobile_score = $decoded['metrics']['performance']['mobile']['score'] ?? 'N/A';
                        $desktop_score = $decoded['metrics']['performance']['desktop']['score'] ?? 'N/A';
                        echo "      metrics.performance.mobile.score: {$mobile_score}\n";
                        echo "      metrics.performance.desktop.score: {$desktop_score}\n";
                    }
                } else {
                    echo "   {$key}: [JSON INVÁLIDO]\n";
                }
            }
        } else {
            $display_value = $value;
            if ( is_bool( $value ) ) {
                $display_value = $value ? 'true' : 'false';
            } elseif ( empty( $value ) && $value !== '0' && $value !== 0 ) {
                $display_value = '(vacío o no existe)';
            }
            echo "   {$key}: {$display_value}\n";
        }
    }

    echo "\n=== CÁLCULO DE SCORE ===\n\n";
    
    // Intentar extraer del payload
    $payload_raw = get_post_meta( $post_id, '_tew_report_payload', true );
    if ( ! empty( $payload_raw ) ) {
        $payload = json_decode( $payload_raw, true );
        
        if ( $payload && isset( $payload['summary']['overall_score'] ) ) {
            echo "✅ overall_score en payload: {$payload['summary']['overall_score']}\n";
        } else {
            echo "❌ NO hay overall_score en summary del payload\n";
        }
        
        if ( $payload && isset( $payload['summary']['score'] ) ) {
            echo "   summary.score (alternativo): {$payload['summary']['score']}\n";
        }
        
        // Mostrar métricas individuales
        if ( $payload && isset( $payload['metrics'] ) ) {
            $m = $payload['metrics'];
            
            echo "\n   Desglose de métricas:\n";
            
            if ( isset( $m['pagespeed']['mobile']['score'] ) ) {
                $ps_mobile = $m['pagespeed']['mobile']['score'];
                echo "   - PageSpeed Mobile: {$ps_mobile} (peso 45%)\n";
            }
            
            if ( isset( $m['pagespeed']['desktop']['score'] ) ) {
                $ps_desktop = $m['pagespeed']['desktop']['score'];
                echo "   - PageSpeed Desktop: {$ps_desktop} (peso 25%)\n";
            }
            
            if ( isset( $m['websitecarbon']['cleaner_than'] ) ) {
                $wc_score = $m['websitecarbon']['cleaner_than'];
                echo "   - WebsiteCarbon: {$wc_score} (peso 20%)\n";
            } elseif ( isset( $m['websitecarbon']['co2_per_view'] ) ) {
                $co2 = $m['websitecarbon']['co2_per_view'];
                $normalized = 100 - ( ( $co2 - 0.2 ) * 80 );
                $wc_score = max( 5, min( 100, $normalized ) );
                echo "   - WebsiteCarbon (CO2: {$co2}g): {$wc_score} (peso 20%)\n";
            }
            
            if ( isset( $m['greenweb']['is_green'] ) ) {
                $gw_score = $m['greenweb']['is_green'] ? 100 : 35;
                echo "   - Green Web: {$gw_score} (peso 10%)\n";
            }
            
            // Calcular score esperado
            echo "\n   📊 Cálculo esperado:\n";
            $calculated_score = 0;
            $total_weight = 0;
            
            if ( isset( $ps_mobile ) ) {
                $calculated_score += $ps_mobile * 0.45;
                $total_weight += 0.45;
            }
            if ( isset( $ps_desktop ) ) {
                $calculated_score += $ps_desktop * 0.25;
                $total_weight += 0.25;
            }
            if ( isset( $wc_score ) ) {
                $calculated_score += $wc_score * 0.20;
                $total_weight += 0.20;
            }
            if ( isset( $gw_score ) ) {
                $calculated_score += $gw_score * 0.10;
                $total_weight += 0.10;
            }
            
            if ( $total_weight > 0 ) {
                $final_score = round( $calculated_score / $total_weight, 1 );
                echo "   = {$final_score} / 100\n";
            }
        }
    } else {
        echo "❌ NO hay payload JSON guardado\n";
    }

    echo "\n=== RECOMENDACIONES ===\n\n";
    
    $score_meta = get_post_meta( $post_id, '_tew_report_score', true );
    if ( empty( $score_meta ) || $score_meta === '' || $score_meta === 0 || $score_meta === '0' ) {
        echo "⚠️  El meta field _tew_report_score está vacío o es 0\n";
        echo "   Solución: El sistema debería extraerlo del payload automáticamente\n";
        echo "   con el fallback que implementamos en get_all_analyses()\n";
    }
    
    $is_green_meta = get_post_meta( $post_id, '_tew_report_is_green', true );
    if ( empty( $is_green_meta ) && $is_green_meta !== '0' ) {
        echo "⚠️  El meta field _tew_report_is_green está vacío\n";
        echo "   Debería ser '1' o '0'\n";
    }

    echo "\n</pre>";
    exit;
} );
